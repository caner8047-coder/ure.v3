<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OperationalDataResetService
{
    private const RESET_TABLES = [
        'tbSiparisSatir',
        'tbBolumHavuz',
        'tbPersonelGorev',
        'tbGorevler',
        'tbIsEmriGecmisi',
        'tbVerilenGorevler',
        'work_order_events',
        'work_order_snapshots',
        'stock_movements',
        'tasks',
        'work_orders',
        'work_order_histories',
        'order_items',
        'production_pools',
        'tbKritikStokUyari',
        'critical_stock_warnings',
    ];

    private const BACKUP_ONLY_TABLES = [
        'tbBolumAraStok',
    ];

    private const ORDER_STOCK_MOVEMENT_TYPES = [
        'order_stock_out',
        'order_stock_out_reversed',
        'order_auto_stock_out',
    ];

    private const OPERATIONAL_BUFFER_MOVEMENT_TYPES = [
        'work_order_buffer_reserved',
        'work_order_buffer_released',
        'pool_buffer_released',
    ];

    private const PRODUCTION_STOCK_MOVEMENT_TYPES = [
        'production_stock_in',
        'production_stock_in_reversed',
        'cancelled_production_stock_in',
    ];

    public function reset(bool $createBackup = true): array
    {
        $resetTables = $this->existingTables(self::RESET_TABLES);
        $backupTables = $this->existingTables(array_values(array_unique([
            ...self::RESET_TABLES,
            ...self::BACKUP_ONLY_TABLES,
        ])));

        $before = $this->countRows($resetTables);
        $statusBefore = $this->orderStatusBreakdown();
        $backupPath = $createBackup ? $this->createBackup($backupTables) : null;

        $deleted = [];
        $stockAdjustments = [];
        $productionStockAdjustments = [];
        $bufferRowsRestored = 0;
        $totalQuantityRestored = 0;
        $totalQuantityRemoved = 0;
        $productionQuantityRestored = 0;
        $productionQuantityRemoved = 0;

        $schema = DB::connection()->getSchemaBuilder();
        $schema->disableForeignKeyConstraints();

        try {
            DB::transaction(function () use (
                $resetTables,
                &$deleted,
                &$stockAdjustments,
                &$productionStockAdjustments,
                &$bufferRowsRestored,
                &$totalQuantityRestored,
                &$totalQuantityRemoved,
                &$productionQuantityRestored,
                &$productionQuantityRemoved
            ) {
                $stockResult = $this->restoreOrderStockDeductions();
                $stockAdjustments = $stockResult['adjusted_rows'];
                $totalQuantityRestored = $stockResult['quantity_restored'];
                $totalQuantityRemoved = $stockResult['quantity_removed'];
                $productionStockResult = $this->reverseProductionStockIns();
                $productionStockAdjustments = $productionStockResult['adjusted_rows'];
                $productionQuantityRestored = $productionStockResult['quantity_restored'];
                $productionQuantityRemoved = $productionStockResult['quantity_removed'];
                $bufferRowsRestored = $this->restoreOperationalBufferDeductions();

                foreach ($resetTables as $table) {
                    $deleted[$table] = DB::table($table)->delete();
                }
            });
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        foreach ($resetTables as $table) {
            $this->resetAutoIncrement($table);
        }

        return [
            'backup_path' => $backupPath,
            'before' => $before,
            'status_before' => $statusBefore,
            'stock' => [
                'order_stock_quantity_restored' => $totalQuantityRestored,
                'order_stock_quantity_removed' => $totalQuantityRemoved,
                'adjusted_rows' => $stockAdjustments,
                'production_stock_quantity_restored' => $productionQuantityRestored,
                'production_stock_quantity_removed' => $productionQuantityRemoved,
                'production_adjusted_rows' => $productionStockAdjustments,
                'buffer_rows_restored' => $bufferRowsRestored,
            ],
            'deleted' => $deleted,
            'after' => $this->countRows($resetTables),
        ];
    }

    private function restoreOrderStockDeductions(): array
    {
        if (!Schema::hasTable('stock_movements') || !Schema::hasTable('tbBolumAraStok')) {
            return [
                'quantity_restored' => 0,
                'quantity_removed' => 0,
                'adjusted_rows' => [],
            ];
        }

        $movements = DB::table('stock_movements')
            ->select(
                'stock_row_no',
                DB::raw('SUM(quantity_delta) as quantity_net'),
                DB::raw('SUM(buffer_delta) as buffer_net'),
                DB::raw('COUNT(*) as movement_count')
            )
            ->whereIn('movement_type', self::ORDER_STOCK_MOVEMENT_TYPES)
            ->whereNotNull('stock_row_no')
            ->groupBy('stock_row_no')
            ->havingRaw('SUM(quantity_delta) <> 0')
            ->get();

        $adjustedRows = [];
        $quantityRestored = 0;
        $quantityRemoved = 0;

        foreach ($movements as $movement) {
            $stockRowNo = intval($movement->stock_row_no ?? 0);
            $quantityAdjust = -1 * intval($movement->quantity_net ?? 0);
            $bufferAdjust = -1 * intval($movement->buffer_net ?? 0);

            if ($stockRowNo <= 0 || ($quantityAdjust === 0 && $bufferAdjust === 0)) {
                continue;
            }

            $beforeRow = DB::table('tbBolumAraStok')
                ->where('No', $stockRowNo)
                ->lockForUpdate()
                ->first();

            if (!$beforeRow) {
                continue;
            }

            $quantityExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'MAX(0, COALESCE(Adet, 0) + ' . $quantityAdjust . ')'
                : 'GREATEST(0, COALESCE(Adet, 0) + ' . $quantityAdjust . ')';
            $bufferExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'MAX(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')'
                : 'GREATEST(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')';

            DB::table('tbBolumAraStok')->where('No', $stockRowNo)->update([
                'Adet' => DB::raw($quantityExpression),
                'TamponMiktar' => DB::raw($bufferExpression),
            ]);
            $this->clampBufferToQuantity($stockRowNo);

            $afterRow = DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first();
            $adjustedRows[] = [
                'stock_row_no' => $stockRowNo,
                'quantity_before' => intval($beforeRow->Adet ?? 0),
                'quantity_adjust' => $quantityAdjust,
                'quantity_after' => intval($afterRow->Adet ?? 0),
                'buffer_before' => intval($beforeRow->TamponMiktar ?? 0),
                'buffer_adjust' => $bufferAdjust,
                'buffer_after' => intval($afterRow->TamponMiktar ?? 0),
                'movement_count' => intval($movement->movement_count ?? 0),
            ];

            if ($quantityAdjust > 0) {
                $quantityRestored += $quantityAdjust;
            } else {
                $quantityRemoved += abs($quantityAdjust);
            }
        }

        return [
            'quantity_restored' => $quantityRestored,
            'quantity_removed' => $quantityRemoved,
            'adjusted_rows' => $adjustedRows,
        ];
    }

    private function reverseProductionStockIns(): array
    {
        if (!Schema::hasTable('stock_movements') || !Schema::hasTable('tbBolumAraStok')) {
            return [
                'quantity_restored' => 0,
                'quantity_removed' => 0,
                'adjusted_rows' => [],
            ];
        }

        $movements = DB::table('stock_movements')
            ->select(
                'stock_row_no',
                DB::raw('SUM(quantity_delta) as quantity_net'),
                DB::raw('SUM(buffer_delta) as buffer_net'),
                DB::raw('COUNT(*) as movement_count')
            )
            ->whereIn('movement_type', self::PRODUCTION_STOCK_MOVEMENT_TYPES)
            ->whereNotNull('stock_row_no')
            ->groupBy('stock_row_no')
            ->havingRaw('SUM(quantity_delta) <> 0 OR SUM(buffer_delta) <> 0')
            ->get();

        $adjustedRows = [];
        $quantityRestored = 0;
        $quantityRemoved = 0;

        foreach ($movements as $movement) {
            $stockRowNo = intval($movement->stock_row_no ?? 0);
            $quantityAdjust = -1 * intval($movement->quantity_net ?? 0);
            $bufferAdjust = -1 * intval($movement->buffer_net ?? 0);

            if ($stockRowNo <= 0 || ($quantityAdjust === 0 && $bufferAdjust === 0)) {
                continue;
            }

            $beforeRow = DB::table('tbBolumAraStok')
                ->where('No', $stockRowNo)
                ->lockForUpdate()
                ->first();

            if (!$beforeRow) {
                continue;
            }

            $quantityExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'MAX(0, COALESCE(Adet, 0) + ' . $quantityAdjust . ')'
                : 'GREATEST(0, COALESCE(Adet, 0) + ' . $quantityAdjust . ')';
            $bufferExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'MAX(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')'
                : 'GREATEST(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')';

            DB::table('tbBolumAraStok')->where('No', $stockRowNo)->update([
                'Adet' => DB::raw($quantityExpression),
                'TamponMiktar' => DB::raw($bufferExpression),
            ]);
            $this->clampBufferToQuantity($stockRowNo);

            $afterRow = DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first();
            $adjustedRows[] = [
                'stock_row_no' => $stockRowNo,
                'quantity_before' => intval($beforeRow->Adet ?? 0),
                'quantity_adjust' => $quantityAdjust,
                'quantity_after' => intval($afterRow->Adet ?? 0),
                'buffer_before' => intval($beforeRow->TamponMiktar ?? 0),
                'buffer_adjust' => $bufferAdjust,
                'buffer_after' => intval($afterRow->TamponMiktar ?? 0),
                'movement_count' => intval($movement->movement_count ?? 0),
            ];

            if ($quantityAdjust > 0) {
                $quantityRestored += $quantityAdjust;
            } else {
                $quantityRemoved += abs($quantityAdjust);
            }
        }

        return [
            'quantity_restored' => $quantityRestored,
            'quantity_removed' => $quantityRemoved,
            'adjusted_rows' => $adjustedRows,
        ];
    }

    private function restoreOperationalBufferDeductions(): int
    {
        if (!Schema::hasTable('stock_movements') || !Schema::hasTable('tbBolumAraStok')) {
            return 0;
        }

        $movements = DB::table('stock_movements')
            ->select(
                'stock_row_no',
                DB::raw('SUM(buffer_delta) as buffer_net')
            )
            ->whereIn('movement_type', self::OPERATIONAL_BUFFER_MOVEMENT_TYPES)
            ->whereNotNull('stock_row_no')
            ->groupBy('stock_row_no')
            ->havingRaw('SUM(buffer_delta) <> 0')
            ->get();

        $restoredRows = 0;

        foreach ($movements as $movement) {
            $stockRowNo = intval($movement->stock_row_no ?? 0);
            $bufferAdjust = -1 * intval($movement->buffer_net ?? 0);

            if ($stockRowNo <= 0 || $bufferAdjust === 0) {
                continue;
            }

            $bufferExpression = DB::connection()->getDriverName() === 'sqlite'
                ? 'MAX(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')'
                : 'GREATEST(0, COALESCE(TamponMiktar, 0) + ' . $bufferAdjust . ')';

            DB::table('tbBolumAraStok')
                ->where('No', $stockRowNo)
                ->update(['TamponMiktar' => DB::raw($bufferExpression)]);
            $this->clampBufferToQuantity($stockRowNo);

            $restoredRows++;
        }

        return $restoredRows;
    }

    private function clampBufferToQuantity(int $stockRowNo): void
    {
        $expression = DB::connection()->getDriverName() === 'sqlite'
            ? 'MIN(COALESCE(TamponMiktar, 0), COALESCE(Adet, 0))'
            : 'LEAST(COALESCE(TamponMiktar, 0), COALESCE(Adet, 0))';

        DB::table('tbBolumAraStok')
            ->where('No', $stockRowNo)
            ->update(['TamponMiktar' => DB::raw($expression)]);
    }

    private function createBackup(array $tables): ?string
    {
        if (empty($tables) || DB::connection()->getDriverName() !== 'mysql') {
            return null;
        }

        if (!function_exists('proc_open')) {
            return null;
        }

        $binary = $this->findMysqlDumpBinary();
        if ($binary === null) {
            return null;
        }

        $connection = config('database.connections.' . DB::connection()->getName(), []);
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return null;
        }

        $backupDir = storage_path('backups');
        File::ensureDirectoryExists($backupDir);

        $path = $backupDir . '/operational-reset-' . now()->format('Ymd-His') . '.sql';
        $arguments = [
            $binary,
            '--single-transaction',
            '--no-tablespaces',
            '--host=' . (string) ($connection['host'] ?? '127.0.0.1'),
            '--port=' . (string) ($connection['port'] ?? 3306),
            '--user=' . $username,
            '--result-file=' . $path,
            $database,
            ...$tables,
        ];

        $process = new Process($arguments, base_path(), ['MYSQL_PWD' => $password]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($path);
            throw new RuntimeException('Sıfırlama öncesi yedek alınamadı: ' . trim($process->getErrorOutput()));
        }

        return $path;
    }

    private function findMysqlDumpBinary(): ?string
    {
        $paths = array_filter(explode(PATH_SEPARATOR, getenv('PATH') ?: ''));
        $commonPaths = [
            '/usr/bin',
            '/usr/local/bin',
            '/opt/homebrew/bin',
        ];

        foreach (array_unique([...$paths, ...$commonPaths]) as $directory) {
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mysqldump';

            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function orderStatusBreakdown(): array
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return [];
        }

        return DB::table('tbSiparisSatir')
            ->select('Durum', 'Aktif', DB::raw('COUNT(*) as count'))
            ->groupBy('Durum', 'Aktif')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'Durum' => $row->Durum,
                'Aktif' => intval($row->Aktif),
                'count' => intval($row->count),
            ])
            ->all();
    }

    private function countRows(array $tables): array
    {
        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }

    private function existingTables(array $tables): array
    {
        return array_values(array_filter(
            $tables,
            fn (string $table) => Schema::hasTable($table)
        ));
    }

    private function resetAutoIncrement(string $table): void
    {
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `' . str_replace('`', '``', $table) . '` AUTO_INCREMENT = 1');
                return;
            }

            if ($driver === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
                DB::table('sqlite_sequence')->where('name', $table)->delete();
            }
        } catch (Throwable) {
            // Sifirlama tamamlanmisken sayaç yenileme hatasi akisi bozmasin.
        }
    }
}
