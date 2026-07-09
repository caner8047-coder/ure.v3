<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;
use App\Services\StockMovementLogger;
use App\Services\StockExcelService;

class StockService
{
    private array $movementOrderContextCache = [];
    protected StockExcelService $excelService;

    public function __construct(StockExcelService $excelService)
    {
        $this->excelService = $excelService;
    }

    public function getStocks(array $filters = [], int $perPage = 20, int $page = 1, string $sortBy = 'No', string $sortDir = 'asc'): array
    {
        $query = $this->stockInventoryQuery();
        $this->applyStockFilters($query, $filters);
        $this->applyStockSort($query, $sortBy, $sortDir);

        $total = $query->count();
        $data = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $this->attachSpecialProductionCapacityToStocks($data);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
        ];
    }

    public function getLookups(): array
    {
        $departments = DB::table('tbBolum')->select('No as id', 'BolumAdi as name')->orderBy('BolumAdi')->get();
        $componentTypesQuery = DB::table('tbAraUrun')
            ->select('UrunCesidi')
            ->whereNotNull('UrunCesidi')->where('UrunCesidi', '!=', '');
        $componentsQuery = DB::table('tbAraUrun')->select('No as id', 'AraUrunAdi as name');

        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo')) {
            $componentTypesQuery->where(function ($q) {
                $q->whereNull('MergedIntoNo')->orWhere('MergedIntoNo', 0);
            });
            $componentsQuery->where(function ($q) {
                $q->whereNull('MergedIntoNo')->orWhere('MergedIntoNo', 0);
            });
        }

        $componentTypes = $componentTypesQuery->distinct()->orderBy('UrunCesidi')->pluck('UrunCesidi');
        $components = $componentsQuery->orderBy('AraUrunAdi')->get();

        return [
            'departments' => $departments,
            'componentTypes' => $componentTypes,
            'components' => $components,
        ];
    }

    public function storeStock(array $data): bool
    {
        $araUrunNo = intval($data['component_id'] ?? $data['AraUrunAdiNo'] ?? 0);
        $adet = max(0, intval($data['quantity'] ?? $data['Adet'] ?? 0));
        
        $hasBuffer = isset($data['buffer_quantity']) || isset($data['TamponMiktar']);
        $tampon = $hasBuffer
            ? max(0, intval($data['buffer_quantity'] ?? $data['TamponMiktar'] ?? 0))
            : $adet;

        $bolumAdiNo = intval($data['department_id'] ?? $data['BolumAdiNo'] ?? 0);
        if ($bolumAdiNo <= 0) {
            $bolumAdiNo = intval(DB::table('tbAraUrun')->where('No', $araUrunNo)->value('BolumAdiNo') ?? 0);
        }

        $existingQuery = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo);
        if ($bolumAdiNo > 0) {
            $existingQuery->where('BolumAdiNo', $bolumAdiNo);
        }

        $existing = $existingQuery->first();
        $eskiAdet = $existing ? intval($existing->Adet) : 0;
        $stockRowNo = $existing ? intval($existing->No) : null;

        if ($existing) {
            $yeniAdetForRow = max(0, $eskiAdet + $adet);
            $yeniTamponForRow = $this->clampStockBuffer(
                $yeniAdetForRow,
                intval($existing->TamponMiktar ?? 0) + $tampon
            );
            DB::table('tbBolumAraStok')->where('No', $existing->No)->update([
                'Adet' => $yeniAdetForRow,
                'TamponMiktar' => $yeniTamponForRow,
            ]);
        } else {
            $tampon = $this->clampStockBuffer($adet, $tampon);
            $stockRowNo = DB::table('tbBolumAraStok')->insertGetId([
                'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                'AraUrunAdiNo' => $araUrunNo,
                'Adet' => $adet,
                'TamponMiktar' => $tampon,
            ], 'No');
        }

        $updated = $stockRowNo
            ? DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first()
            : DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->first();
        $yeniAdet = intval($updated->Adet ?? 0);

        $bomService = app(BomService::class);
        $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $yeniAdet);
        $bomService->personelGorevTabloGuncelle(strval($araUrunNo));

        $this->logStockMovement($existing, $updated, [
            'movement_type' => $existing ? 'manual_stock_added' : 'manual_stock_created',
            'source_type' => 'stock_screen',
            'source_id' => $updated->No ?? $stockRowNo,
            'description' => $existing ? 'Stok ekranından manuel miktar eklendi.' : 'Stok ekranından yeni stok kaydı oluşturuldu.',
            'metadata' => [
                'requested_quantity' => $adet,
                'requested_buffer_quantity' => $tampon,
            ],
        ]);

        return true;
    }

    public function updateStock(int $id, array $data): bool
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        if (!$record) {
            return false;
        }

        $eskiAdet = intval($record->Adet);
        $araUrunNo = intval($record->AraUrunAdiNo);

        $adet = max(0, intval($data['quantity'] ?? $data['Adet'] ?? 0));
        $preserveReserved = filter_var($data['preserve_reserved'] ?? false, FILTER_VALIDATE_BOOL);
        
        $hasBuffer = isset($data['buffer_quantity']) || isset($data['TamponMiktar']);
        $tampon = $preserveReserved || !$hasBuffer
            ? $this->bufferPreservingReservedQuantity($record, $adet)
            : intval($data['buffer_quantity'] ?? $data['TamponMiktar'] ?? 0);
        $tampon = $this->clampStockBuffer($adet, $tampon);
        
        DB::table('tbBolumAraStok')->where('No', $id)->update(['Adet' => $adet, 'TamponMiktar' => $tampon]);

        if ($araUrunNo > 0) {
            $bomService = app(BomService::class);
            $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $adet);
            $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
        }

        $updated = DB::table('tbBolumAraStok')->where('No', $id)->first();
        $this->logStockMovement($record, $updated, [
            'movement_type' => 'manual_stock_updated',
            'source_type' => 'stock_screen',
            'source_id' => $id,
            'description' => 'Stok ekranından manuel düzeltme yapıldı.',
            'metadata' => [
                'requested_quantity' => $adet,
                'requested_buffer_quantity' => $tampon,
            ],
        ]);

        return true;
    }

    public function deleteStock(int $id): bool
    {
        $record = DB::table('tbBolumAraStok')->where('No', $id)->first();
        if ($record) {
            $araUrunNo = intval($record->AraUrunAdiNo);
            $eskiAdet = intval($record->Adet);

            DB::table('tbBolumAraStok')->where('No', $id)->delete();

            $this->logStockMovement($record, null, [
                'movement_type' => 'manual_stock_deleted',
                'source_type' => 'stock_screen',
                'source_id' => $id,
                'description' => 'Stok ekranından stok kaydı silindi.',
            ]);

            if ($araUrunNo > 0) {
                $bomService = app(BomService::class);
                $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, 0);
                $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
            }
        } else {
            DB::table('tbBolumAraStok')->where('No', $id)->delete();
        }

        return true;
    }

    public function resetBuffer(): array
    {
        $bomService = app(BomService::class);
        $beforeRows = DB::table('tbBolumAraStok')->get()->keyBy('No');
        
        foreach ($beforeRows as $row) {
            $componentNo = intval($row->AraUrunAdiNo ?? 0);
            if ($componentNo > 0 && $bomService->hasActiveBufferReservation($componentNo)) {
                continue;
            }

            DB::table('tbBolumAraStok')->where('No', $row->No)->update(['TamponMiktar' => $row->Adet]);
        }

        $afterRows = DB::table('tbBolumAraStok')->get();

        foreach ($afterRows as $after) {
            $before = $beforeRows->get($after->No);
            if (!$before || intval($before->TamponMiktar ?? 0) === intval($after->TamponMiktar ?? 0)) {
                continue;
            }

            $this->logStockMovement($before, $after, [
                'movement_type' => 'stock_buffer_recalibrated',
                'source_type' => 'recalibration_job',
                'source_id' => $after->No,
                'description' => 'Sistem tampon koruması aktif olmadığı için tampon serbest stok miktarına eşitlendi.',
            ]);
        }

        return ['success' => true];
    }

    public function importWorkbook($file): array
    {
        try {
            $rows = $this->excelService->readStockImportRows($file);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage() ?: 'Dosya okunamadı.',
                'code' => 422
            ];
        }

        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && $this->excelService->spreadsheetRowHasContent($row)));

        if (count($rows) < 2) {
            return ['success' => false, 'message' => 'İçeri aktarılacak satır bulunamadı.', 'code' => 422];
        }

        $header = array_shift($rows);
        $noIdx = $this->excelService->findCsvHeaderIndex($header, ['No', 'Stok No', 'stock_row_no', 'id']);
        $adetIdx = $this->excelService->findCsvHeaderIndex($header, ['Adet', 'Miktar', 'quantity', 'quantity_after']);
        $tamponIdx = $this->excelService->findCsvHeaderIndex($header, ['Boşta/Tampon', 'Boşta', 'Bosta', 'Tampon', 'Tampon Miktar', 'TamponMiktar', 'buffer_quantity', 'buffer_after']);

        if ($noIdx === false || $adetIdx === false || $tamponIdx === false) {
            return [
                'success' => false,
                'message' => 'Dosya başlıkları uygun değil. "No", "Adet" ve "Boşta/Tampon" sütunları gerekli.',
                'code' => 422
            ];
        }

        $updated = 0;
        $errors = 0;
        $bomService = app(BomService::class);

        $lineNumber = 1;
        foreach ($rows as $row) {
            $lineNumber++;
            if (count($row) <= max($noIdx, $adetIdx, $tamponIdx)) {
                $errors++;
                continue;
            }

            $noRaw = $this->excelService->normalizeSpreadsheetText($row[$noIdx] ?? '');
            if ($noRaw === '') {
                continue;
            }

            $no = $this->excelService->parseCsvInteger($noRaw);
            $adet = $this->excelService->parseCsvInteger($row[$adetIdx]);
            $tampon = $this->excelService->parseCsvInteger($row[$tamponIdx]);

            if ($no <= 0) {
                $errors++;
                continue;
            }

            $record = DB::table('tbBolumAraStok')->where('No', $no)->first();
            if (!$record) {
                $errors++;
                continue;
            }

            $eskiAdet = intval($record->Adet);
            $araUrunNo = intval($record->AraUrunAdiNo);

            DB::table('tbBolumAraStok')->where('No', $no)->update([
                'Adet' => $adet,
                'TamponMiktar' => $tampon,
            ]);

            if ($araUrunNo > 0) {
                $bomService->sonrakiUrunAdetleriniGuncelle(strval($araUrunNo), $eskiAdet, $adet);
                $bomService->personelGorevTabloGuncelle(strval($araUrunNo));
            }

            $updatedRecord = DB::table('tbBolumAraStok')->where('No', $no)->first();
            $this->logStockMovement($record, $updatedRecord, [
                'movement_type' => 'stock_spreadsheet_imported',
                'source_type' => 'stock_import',
                'source_id' => $file->getClientOriginalName(),
                'description' => 'Stok çalışma kitabı içe aktarımı ile stok güncellendi.',
                'metadata' => [
                    'file_name' => $file->getClientOriginalName(),
                    'line_number' => $lineNumber,
                ],
            ]);

            $updated++;
        }

        return [
            'success' => true,
            'message' => "{$updated} kayıt güncellendi"
                . ($errors > 0 ? ", {$errors} satır hatalı olduğu için atlandı" : '')
                . '.',
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function getMovements(int $id, array $filters = [], int $limit = 100): ?array
    {
        $stock = $this->stockDetailQuery()
            ->where('s.No', $id)
            ->first();

        if (!$stock) {
            return null;
        }

        if (!Schema::hasTable('stock_movements')) {
            return [
                'stock' => $stock,
                'movements' => [],
                'analysis' => [
                    'current' => $this->stockBalanceSnapshot($stock),
                    'formula' => [
                        'quantity_label' => 'Depodaki',
                        'free_label' => 'Boşta',
                        'reserved_label' => 'Görevdeki',
                        'text' => 'Görevdeki = Depodaki - Boşta',
                    ],
                    'opening' => $this->stockBalanceSnapshot($stock),
                    'net' => ['quantity_delta' => 0, 'free_delta' => 0, 'reserved_delta' => 0],
                    'expected' => $this->stockBalanceSnapshot($stock),
                    'reconciled' => ['quantity' => true, 'free' => true, 'reserved' => true],
                    'movement_count_total' => 0,
                    'source_totals' => [],
                    'reserved_sources' => [],
                    'movement_type_totals' => [],
                    'live_context' => ['pool' => ['count' => 0], 'personnel_tasks' => ['count' => 0], 'orders' => ['count' => 0]],
                ],
                'summary' => ['movement_count' => 0, 'total_in' => 0, 'total_out' => 0, 'last_movement_at' => null],
            ];
        }

        $baseQuery = $this->stockMovementQueryForStock($stock, $id);
        $query = clone $baseQuery;

        $this->applyMovementFilters($query, $filters);

        $limit = max(1, min(200, intval($limit)));
        $movements = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->limit($limit)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();
            
        $movements = $this->enrichMovementRows($movements);
        $analysis = $this->buildStockMovementAnalysis($stock, $baseQuery);

        return [
            'stock' => $stock,
            'movements' => $movements,
            'analysis' => $analysis,
            'summary' => [
                'movement_count' => $movements->count(),
                'total_in' => intval($movements->where('quantity_delta', '>', 0)->sum('quantity_delta')),
                'total_out' => abs(intval($movements->where('quantity_delta', '<', 0)->sum('quantity_delta'))),
                'last_movement_at' => optional($movements->first())->happened_at,
            ],
        ];
    }

    public function getMovementFeed(array $filters = [], int $perPage = 25): array
    {
        if (!Schema::hasTable('stock_movements')) {
            return ['data' => []];
        }

        $query = DB::table('stock_movements as m')
            ->leftJoin('tbAraUrun as a', 'm.component_no', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'm.department_no', '=', 'b.No');

        $this->applyMovementFilters($query, $filters);

        if (isset($filters['component_no'])) {
            $query->where('m.component_no', intval($filters['component_no']));
        }

        if (isset($filters['department_no'])) {
            $query->where('m.department_no', intval($filters['department_no']));
        }

        $perPage = max(1, min(100, intval($perPage)));
        $rows = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->paginate($perPage);

        return ['data' => $rows];
    }

    public function getMovementsForExport(int $id, array $filters = []): ?array
    {
        $stock = $this->stockDetailQuery()
            ->where('s.No', $id)
            ->first();

        if (!$stock) {
            return null;
        }

        $query = $this->stockMovementQueryForStock($stock, $id);
        $this->applyMovementFilters($query, $filters);

        $rows = $query
            ->orderByDesc('m.happened_at')
            ->orderByDesc('m.id')
            ->limit(5000)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();
            
        $rows = $this->enrichMovementRows($rows);

        return [
            'stock' => $stock,
            'rows' => $rows
        ];
    }

    private function stockDetailQuery()
    {
        return DB::table('tbBolumAraStok as s')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->select(
                's.*',
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi")
            );
    }

    private function stockInventoryQuery()
    {
        return DB::table('tbAraUrun as a')
            ->leftJoin('tbBolumAraStok as s', 's.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', function ($join) {
                $join->on('b.No', '=', DB::raw('COALESCE(s.BolumAdiNo, a.BolumAdiNo)'));
            })
            ->select(
                's.No',
                DB::raw('s.No as StockNo'),
                DB::raw('COALESCE(s.BolumAdiNo, a.BolumAdiNo) as BolumAdiNo'),
                DB::raw('a.No as AraUrunAdiNo'),
                DB::raw('COALESCE(s.Adet, 0) as Adet'),
                DB::raw('COALESCE(s.TamponMiktar, 0) as TamponMiktar'),
                DB::raw('COALESCE(s.UrunIDNo, 0) as UrunIDNo'),
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi,'') as AraUrunAdi"),
                DB::raw("IFNULL(a.UrunCesidi,'') as UrunCesidi"),
                DB::raw('CASE WHEN s.No IS NULL THEN 1 ELSE 0 END as IsVirtualStock')
            );
    }

    private function applyStockFilters($query, array $filters): void
    {
        if (Schema::hasColumn('tbAraUrun', 'MergedIntoNo') && !filter_var($filters['include_merged'] ?? false, FILTER_VALIDATE_BOOL)) {
            $query->where(function ($q) {
                $q->whereNull('a.MergedIntoNo')->orWhere('a.MergedIntoNo', 0);
            });
        }

        if (isset($filters['department_id']) && trim((string) $filters['department_id']) !== '') {
            $query->whereRaw('COALESCE(s.BolumAdiNo, a.BolumAdiNo) = ?', [intval($filters['department_id'])]);
        }

        if (isset($filters['component_type']) && trim((string) $filters['component_type']) !== '') {
            $query->where('a.UrunCesidi', $filters['component_type']);
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $s = trim((string) $filters['search']);
            $numericSearch = ctype_digit($s) ? intval($s) : null;
            $numericLike = "%{$s}%";

            $query->where(function ($q) use ($s, $numericSearch, $numericLike) {
                $q->where('a.AraUrunAdi', 'like', "%$s%")
                  ->orWhere('b.BolumAdi', 'like', "%$s%");

                if ($numericSearch !== null) {
                    $q->orWhere('s.No', $numericSearch)
                      ->orWhere('a.No', $numericSearch)
                      ->orWhere('s.AraUrunAdiNo', $numericSearch)
                      ->orWhere('s.UrunIDNo', $numericSearch)
                      ->orWhereRaw('CAST(s.No AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(a.No AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(s.AraUrunAdiNo AS CHAR) LIKE ?', [$numericLike])
                      ->orWhereRaw('CAST(s.UrunIDNo AS CHAR) LIKE ?', [$numericLike]);
                }
            });
        }

        $stockStatus = (string) ($filters['stock_status'] ?? $filters['status'] ?? '');
        if ($stockStatus === 'zero') {
            $query->whereRaw('COALESCE(s.Adet, 0) = 0');
        } elseif ($stockStatus === 'nonzero') {
            $query->whereRaw('COALESCE(s.Adet, 0) <> 0');
        }
    }

    private function applyStockSort($query, string $sortField, string $sortDir): void
    {
        $map = [
            'BolumAdi' => DB::raw("IFNULL(b.BolumAdi,'')"),
            'AraUrunAdi' => DB::raw("IFNULL(a.AraUrunAdi,'')"),
            'Adet' => DB::raw('COALESCE(s.Adet, 0)'),
            'TamponMiktar' => DB::raw('COALESCE(s.TamponMiktar, 0)'),
            'UrunCesidi' => DB::raw("IFNULL(a.UrunCesidi,'')"),
            'No' => DB::raw('s.No'),
            'StockNo' => DB::raw('s.No'),
        ];

        $expr = $map[$sortField] ?? DB::raw('s.No');
        $query->orderBy($expr, $sortDir);
    }

    private function attachSpecialProductionCapacityToStocks($stocks): void
    {
        if (!$stocks || $stocks->isEmpty()) {
            return;
        }

        foreach ($stocks as $stock) {
            $stock->UretimToplamAdet = 0;
            $stock->UretimRezerveAdet = 0;
            $stock->UretimMusaitAdet = 0;
        }

        if (!Schema::hasTable('tbSiparisSatir')) {
            return;
        }

        $finalProductNames = $stocks
            ->filter(fn ($stock) => $this->stockProductionMatchType((string) ($stock->UrunCesidi ?? '')) === 'Nihai')
            ->map(fn ($stock) => trim((string) ($stock->AraUrunAdi ?? '')))
            ->filter()
            ->unique()
            ->values();

        $finalProductNoByName = collect();
        if ($finalProductNames->isNotEmpty() && Schema::hasTable('tbUrunler')) {
            $finalProductNoByName = DB::table('tbUrunler')
                ->whereIn('UrunID', $finalProductNames->all())
                ->pluck('No', 'UrunID');
        }

        $pairs = [];
        $rowKeys = [];
        foreach ($stocks as $index => $stock) {
            $matchType = $this->stockProductionMatchType((string) ($stock->UrunCesidi ?? ''));
            if ($matchType === '') {
                continue;
            }

            $componentNo = intval($stock->AraUrunAdiNo ?? 0);
            $matchNo = $componentNo;
            if ($matchType === 'Nihai') {
                $name = trim((string) ($stock->AraUrunAdi ?? ''));
                $matchNo = intval($finalProductNoByName[$name] ?? 0) ?: $componentNo;
            }

            $key = $this->stockProductionCapacityKey($matchNo, $matchType);
            if ($key === '') {
                continue;
            }

            $pairs[] = ['no' => $matchNo, 'type' => $matchType, 'key' => $key];
            $rowKeys[$index] = $key;
        }

        $capacityMap = $this->buildStockSpecialProductionCapacityMap($pairs);
        if (empty($capacityMap)) {
            return;
        }

        foreach ($stocks as $index => $stock) {
            $key = $rowKeys[$index] ?? '';
            if ($key === '' || !isset($capacityMap[$key])) {
                continue;
            }

            $capacity = $capacityMap[$key];
            $stock->UretimToplamAdet = intval($capacity['total'] ?? 0);
            $stock->UretimRezerveAdet = intval($capacity['reserved'] ?? 0);
            $stock->UretimMusaitAdet = intval($capacity['available'] ?? 0);
        }
    }

    private function buildStockSpecialProductionCapacityMap(array $pairs): array
    {
        $pairs = collect($pairs)
            ->filter(fn ($pair) => intval($pair['no'] ?? 0) > 0 && trim((string) ($pair['type'] ?? '')) !== '')
            ->unique('key')
            ->values();

        if ($pairs->isEmpty() || !Schema::hasTable('tbSiparisSatir')) {
            return [];
        }

        $productionRows = DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where(function ($query) {
                $query->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
                    ->orWhere('sp.Musteri', 'like', 'OZEL URETIM%');
            })
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->where(function ($query) {
                $query->whereRaw('IFNULL(sp.GorevNo, 0) > 0')
                    ->orWhereIn('sp.Durum', ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor']);
            })
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($pairQuery) use ($pair) {
                        $pairQuery->where('sp.EslesenUrunNo', intval($pair['no']))
                            ->where('sp.EslesenUrunTur', (string) $pair['type']);
                    });
                }
            })
            ->get([
                'sp.No',
                'sp.EslesenUrunNo',
                'sp.EslesenUrunTur',
                'sp.Adet',
            ]);

        if ($productionRows->isEmpty()) {
            return [];
        }

        $productionNos = $productionRows
            ->pluck('No')
            ->map(fn ($no) => intval($no))
            ->filter(fn ($no) => $no > 0)
            ->values()
            ->all();

        $reservedMap = DB::table('tbSiparisSatir')
            ->whereIn('BagliOlduguOzelUretimNo', $productionNos)
            ->where('Aktif', 1)
            ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi'])
            ->select('BagliOlduguOzelUretimNo', DB::raw('SUM(Adet) as ReservedAdet'))
            ->groupBy('BagliOlduguOzelUretimNo')
            ->pluck('ReservedAdet', 'BagliOlduguOzelUretimNo');

        $capacity = [];
        foreach ($productionRows as $row) {
            $key = $this->stockProductionCapacityKey(
                intval($row->EslesenUrunNo ?? 0),
                (string) ($row->EslesenUrunTur ?? '')
            );
            if ($key === '') {
                continue;
            }

            $total = intval($row->Adet ?? 0);
            $reserved = intval($reservedMap[intval($row->No ?? 0)] ?? 0);
            if (!isset($capacity[$key])) {
                $capacity[$key] = [
                    'total' => 0,
                    'reserved' => 0,
                    'available' => 0,
                ];
            }

            $capacity[$key]['total'] += $total;
            $capacity[$key]['reserved'] += $reserved;
            $capacity[$key]['available'] += max(0, $total - $reserved);
        }

        return $capacity;
    }

    private function stockProductionMatchType(string $type): string
    {
        $type = trim($type);
        $lower = strtolower($type);

        if ($type === 'HamMadde' || str_contains($lower, 'ham')) {
            return 'HamMadde';
        }

        if ($type === 'Ara' || str_contains($lower, 'ara') || str_contains($lower, 'mamul') || str_contains($lower, 'mamül') || str_contains($lower, 'bile')) {
            return 'Ara';
        }

        if ($type === 'Nihai' || str_contains($lower, 'nihai') || str_contains($lower, 'nihayi')) {
            return 'Nihai';
        }

        return '';
    }

    private function stockProductionCapacityKey(int $productNo, string $productType): string
    {
        $productType = trim($productType);
        if ($productNo <= 0 || $productType === '') {
            return '';
        }

        return $productNo . '-' . $productType;
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Ekstre kaydı ana stok işlemini engellemesin.
        }
    }

    private function bufferPreservingReservedQuantity(?object $record, int $newQuantity): int
    {
        if (!$record) {
            return $newQuantity;
        }

        $eskiAdet = intval($record->Adet ?? 0);
        $eskiTampon = intval($record->TamponMiktar ?? 0);
        $eskiGorevdeki = max(0, $eskiAdet - $eskiTampon);

        $yeniTampon = $newQuantity - $eskiGorevdeki;

        return max(0, $yeniTampon);
    }

    private function clampStockBuffer(int $quantity, int $buffer): int
    {
        return max(0, min($quantity, $buffer));
    }

    private function stockMovementQueryForStock(object $stock, int $id)
    {
        $componentNo = intval($stock->AraUrunAdiNo ?? 0);
        $departmentNo = intval($stock->BolumAdiNo ?? 0);

        return DB::table('stock_movements as m')
            ->leftJoin('tbAraUrun as a', 'm.component_no', '=', 'a.No')
            ->leftJoin('tbBolum as b', 'm.department_no', '=', 'b.No')
            ->where(function ($q) use ($stock, $id) {
                $q->where('m.stock_row_no', $id);

                if (intval($stock->AraUrunAdiNo ?? 0) > 0) {
                    $q->orWhere(function ($componentQuery) use ($stock) {
                        $componentQuery->where('m.component_no', intval($stock->AraUrunAdiNo))
                            ->where(function ($departmentQuery) use ($stock) {
                                if (intval($stock->BolumAdiNo ?? 0) > 0) {
                                    $departmentQuery->where('m.department_no', intval($stock->BolumAdiNo))
                                        ->orWhereNull('m.department_no')
                                        ->orWhere('m.department_no', 0);
                                } else {
                                    $departmentQuery->whereNull('m.department_no')
                                        ->orWhere('m.department_no', 0);
                                }
                            });
                    });
                }
            });
    }

    private function applyMovementFilters($query, array $filters): void
    {
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $q = trim((string) $filters['search']);
            $query->where(function ($query) use ($q) {
                $query->where('m.description', 'like', "%{$q}%")
                    ->orWhere('m.source_type', 'like', "%{$q}%")
                    ->orWhere('m.source_id', 'like', "%{$q}%")
                    ->orWhere('m.movement_type', 'like', "%{$q}%")
                    ->orWhere('m.actor_name', 'like', "%{$q}%");

                if (ctype_digit($q)) {
                    $query->orWhere('m.order_no', 'like', "%{$q}%")
                        ->orWhere('m.order_item_no', intval($q))
                        ->orWhere('m.work_order_no', intval($q))
                        ->orWhere('m.personnel_task_no', intval($q))
                        ->orWhere('m.stock_row_no', intval($q));
                }
            });
        }
    }

    private function enrichMovementRows($rows)
    {
        return $rows->map(fn ($row) => $this->enrichMovementRow($row));
    }

    private function enrichMovementRow(object $row): object
    {
        $metadata = $this->decodeJsonField($row->metadata ?? null);
        $row->metadata = $metadata;

        $this->attachDerivedOrderContext($row, $metadata);

        $quantityBefore = $this->nullableMovementInt($row->quantity_before ?? null);
        $quantityAfter = $this->nullableMovementInt($row->quantity_after ?? null);
        $bufferBefore = $this->nullableMovementInt($row->buffer_before ?? null);
        $bufferAfter = $this->nullableMovementInt($row->buffer_after ?? null);

        $freeBefore = $this->freeBalance($quantityBefore, $bufferBefore);
        $freeAfter = $this->freeBalance($quantityAfter, $bufferAfter);
        $reservedBefore = $this->reservedBalance($quantityBefore, $bufferBefore);
        $reservedAfter = $this->reservedBalance($quantityAfter, $bufferAfter);

        $freeDelta = ($freeBefore !== null && $freeAfter !== null)
            ? $freeAfter - $freeBefore
            : intval($row->buffer_delta ?? 0);
        $reservedDelta = ($reservedBefore !== null && $reservedAfter !== null)
            ? $reservedAfter - $reservedBefore
            : intval($row->quantity_delta ?? 0) - $freeDelta;

        $row->quantity_before = $quantityBefore;
        $row->quantity_after = $quantityAfter;
        $row->buffer_before = $bufferBefore;
        $row->buffer_after = $bufferAfter;

        $row->free_before = $freeBefore;
        $row->free_delta = $freeDelta;
        $row->free_after = $freeAfter;
        $row->reserved_before = $reservedBefore;
        $row->reserved_delta = $reservedDelta;
        $row->reserved_after = $reservedAfter;

        $row->quantity_delta = ($quantityAfter !== null && $quantityBefore !== null)
            ? $quantityAfter - $quantityBefore
            : 0;
        $row->buffer_delta = ($bufferAfter !== null && $bufferBefore !== null)
            ? $bufferAfter - $bufferBefore
            : 0;

        $row->source_label = $this->movementSourceLabel($row);
        $row->reason_human = $this->movementReasonHuman($row, $metadata);
        $row->direction_label = $this->movementDirectionLabel((string) ($row->direction ?? 'neutral'));
        $row->direction = $this->movementDirectionLabel($row->direction ?? '');
        $row->title_human = $this->movementAllocationLabel($row);

        $row->order_item_no = $metadata['order_item_no'] ?? null;
        $row->order_no = $metadata['order_no'] ?? null;
        $row->work_order_no = $metadata['work_order_no'] ?? null;
        $row->personnel_task_no = $metadata['personnel_task_no'] ?? null;

        return $row;
    }

    private function attachDerivedOrderContext(object $row, array &$metadata): void
    {
        if (isset($metadata['order_item_no']) || isset($metadata['order_no'])) {
            return;
        }

        $componentNo = intval($row->component_no ?? 0);
        if ($componentNo <= 0) {
            return;
        }

        $movementType = (string) ($row->movement_type ?? '');
        $isBufferChange = str_contains(strtolower($movementType), 'buffer');
        $isManual = str_contains(strtolower($movementType), 'manual');

        if ($isBufferChange && !$isManual && trim((string) ($row->happened_at ?? '')) !== '') {
            $order = $this->findOrderForLegacyBufferMovement($componentNo, $row->happened_at);
            if ($order) {
                $metadata['order_item_no'] = intval($order->No ?? 0);
                $metadata['order_no'] = trim((string) ($order->SiparisNo ?? ''));
                if (intval($order->GorevNo ?? 0) > 0) {
                    $metadata['work_order_no'] = intval($order->GorevNo);
                }
            }
        }
    }

    private function findOrderForLegacyBufferMovement(int $componentNo, string $happenedAt): ?object
    {
        $cacheKey = $componentNo . '_' . date('YmdHi', strtotime($happenedAt));
        if (isset($this->movementOrderContextCache[$cacheKey])) {
            return $this->movementOrderContextCache[$cacheKey];
        }

        if (!Schema::hasTable('tbSiparisSatir')) {
            return null;
        }

        $targetDate = date('Y-m-d H:i:s', strtotime($happenedAt));
        $lookback = date('Y-m-d H:i:s', strtotime($happenedAt . ' -120 minutes'));

        $order = DB::table('tbSiparisSatir')
            ->where('EslesenUrunNo', $componentNo)
            ->where('EslesenUrunTur', 'Ara Mamül')
            ->where('Aktif', 1)
            ->whereBetween('created_at', [$lookback, $targetDate])
            ->orderByDesc('created_at')
            ->select('No', 'SiparisNo', 'GorevNo')
            ->first();

        if (!$order) {
            $likeNeedles = ['%stok%', '%serbest%', '%ilave%'];
            $order = DB::table('tbSiparisSatir')
                ->where('EslesenUrunNo', $componentNo)
                ->where('EslesenUrunTur', 'Ara Mamül')
                ->where('Aktif', 1)
                ->where(function ($q) use ($likeNeedles) {
                    foreach ($likeNeedles as $needle) {
                        $q->orWhere('Musteri', 'like', $needle);
                    }
                })
                ->where('created_at', '<=', $targetDate)
                ->orderByDesc('created_at')
                ->select('No', 'SiparisNo', 'GorevNo')
                ->first();
        }

        $this->movementOrderContextCache[$cacheKey] = $order;
        return $order;
    }

    private function buildStockMovementAnalysis(object $stock, $baseQuery): array
    {
        $current = $this->stockBalanceSnapshot($stock);
        $allMovements = (clone $baseQuery)
            ->orderBy('m.happened_at')
            ->orderBy('m.id')
            ->limit(5000)
            ->select(
                'm.*',
                DB::raw("IFNULL(a.AraUrunAdi, '') as component_name"),
                DB::raw("IFNULL(a.UrunCesidi, '') as component_type"),
                DB::raw("IFNULL(b.BolumAdi, '') as department_name")
            )
            ->get();

        $allMovements = $this->enrichMovementRows($allMovements);
        $netQuantity = intval($allMovements->sum(fn ($row) => intval($row->quantity_delta ?? 0)));
        $netFree = intval($allMovements->sum(fn ($row) => intval($row->free_delta ?? 0)));
        $netReserved = intval($allMovements->sum(fn ($row) => intval($row->reserved_delta ?? 0)));
        $firstMovement = $allMovements->first();

        $openingQuantity = $firstMovement && $firstMovement->quantity_before !== null
            ? intval($firstMovement->quantity_before)
            : $current['quantity'] - $netQuantity;
        $openingFree = $firstMovement && $firstMovement->free_before !== null
            ? intval($firstMovement->free_before)
            : $current['free'] - $netFree;
        $openingQuantity = max(0, $openingQuantity);
        $openingFree = max(0, min($openingQuantity, $openingFree));
        $openingReserved = max(0, $openingQuantity - $openingFree);

        $expectedQuantity = $openingQuantity + $netQuantity;
        $expectedFree = $openingFree + $netFree;
        $expectedFree = max(0, min(max(0, $expectedQuantity), $expectedFree));
        $expectedReserved = max(0, $expectedQuantity - $expectedFree);

        return [
            'current' => $current,
            'formula' => [
                'quantity_label' => 'Depodaki',
                'free_label' => 'Boşta',
                'reserved_label' => 'Görevdeki',
                'text' => 'Görevdeki = Depodaki - Boşta',
            ],
            'opening' => [
                'quantity' => $openingQuantity,
                'free' => $openingFree,
                'reserved' => $openingReserved,
            ],
            'net' => [
                'quantity_delta' => $netQuantity,
                'free_delta' => $netFree,
                'reserved_delta' => $netReserved,
            ],
            'expected' => [
                'quantity' => $expectedQuantity,
                'free' => $expectedFree,
                'reserved' => $expectedReserved,
            ],
            'reconciled' => [
                'quantity' => $expectedQuantity === $current['quantity'],
                'free' => $expectedFree === $current['free'],
                'reserved' => $expectedReserved === $current['reserved'],
            ],
            'movement_count_total' => $allMovements->count(),
            'source_totals' => $this->buildMovementSourceTotals($allMovements),
            'reserved_sources' => $this->buildReservedSourceBreakdown($allMovements),
            'movement_type_totals' => $this->buildMovementTypeTotals($allMovements),
            'live_context' => $this->buildLiveStockContext($stock, $allMovements),
        ];
    }

    private function stockBalanceSnapshot(object $stock): array
    {
        $q = intval($stock->Adet ?? 0);
        $f = intval($stock->TamponMiktar ?? 0);
        $r = max(0, $q - $f);

        return ['quantity' => $q, 'free' => $f, 'reserved' => $r];
    }

    private function buildReservedSourceBreakdown($movements): array
    {
        return array_values(array_filter(
            $this->buildMovementSourceTotals($movements),
            fn ($group) => intval($group['reserved_delta'] ?? 0) !== 0
        ));
    }

    private function buildMovementSourceTotals($movements): array
    {
        $groups = [];

        foreach ($movements as $movement) {
            $reservedDelta = intval($movement->reserved_delta ?? 0);
            $quantityDelta = intval($movement->quantity_delta ?? 0);
            $freeDelta = intval($movement->free_delta ?? 0);
            if ($reservedDelta === 0 && $quantityDelta === 0 && $freeDelta === 0) {
                continue;
            }

            $key = $this->movementAllocationKey($movement);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'label' => $this->movementAllocationLabel($movement),
                    'movement_type' => (string) ($movement->movement_type ?? ''),
                    'movement_title' => (string) ($movement->title_human ?? ''),
                    'source_label' => (string) ($movement->source_label ?? ''),
                    'quantity_delta' => 0,
                    'free_delta' => 0,
                    'reserved_delta' => 0,
                    'movement_count' => 0,
                    'first_at' => null,
                    'last_at' => null,
                ];
            }

            $groups[$key]['quantity_delta'] += $quantityDelta;
            $groups[$key]['free_delta'] += $freeDelta;
            $groups[$key]['reserved_delta'] += $reservedDelta;
            $groups[$key]['movement_count']++;
            $date = (string) ($movement->happened_at ?? '');
            if ($date !== '') {
                $groups[$key]['first_at'] = $groups[$key]['first_at'] === null
                    ? $date
                    : min($groups[$key]['first_at'], $date);
                $groups[$key]['last_at'] = $groups[$key]['last_at'] === null
                    ? $date
                    : max($groups[$key]['last_at'], $date);
            }
        }

        $groups = array_values($groups);

        usort($groups, function ($a, $b) {
            $deltaCompare = max(
                abs(intval($b['quantity_delta'] ?? 0)),
                abs(intval($b['free_delta'] ?? 0)),
                abs(intval($b['reserved_delta'] ?? 0))
            ) <=> max(
                abs(intval($a['quantity_delta'] ?? 0)),
                abs(intval($a['free_delta'] ?? 0)),
                abs(intval($a['reserved_delta'] ?? 0))
            );
            if ($deltaCompare !== 0) {
                return $deltaCompare;
            }

            return strcmp((string) ($b['last_at'] ?? ''), (string) ($a['last_at'] ?? ''));
        });

        return $groups;
    }

    private function buildMovementTypeTotals($movements): array
    {
        $totals = [];

        foreach ($movements as $movement) {
            $type = (string) ($movement->movement_type ?? 'stock_adjusted');
            if (!isset($totals[$type])) {
                $totals[$type] = [
                    'movement_type' => $type,
                    'title' => (string) ($movement->title_human ?? str_replace('_', ' ', $type)),
                    'quantity_delta' => 0,
                    'free_delta' => 0,
                    'reserved_delta' => 0,
                    'movement_count' => 0,
                ];
            }

            $totals[$type]['quantity_delta'] += intval($movement->quantity_delta ?? 0);
            $totals[$type]['free_delta'] += intval($movement->free_delta ?? 0);
            $totals[$type]['reserved_delta'] += intval($movement->reserved_delta ?? 0);
            $totals[$type]['movement_count']++;
        }

        return array_values($totals);
    }

    private function buildLiveStockContext(object $stock, $movements): array
    {
        $componentNo = intval($stock->AraUrunAdiNo ?? 0);
        $departmentNo = intval($stock->BolumAdiNo ?? 0);
        $context = [
            'pool' => ['count' => 0, 'total_quantity' => 0, 'ready_quantity' => 0, 'rows' => []],
            'personnel_tasks' => ['count' => 0, 'total_quantity' => 0, 'ready_quantity' => 0, 'waiting_quantity' => 0, 'rows' => []],
            'orders' => ['count' => 0, 'rows' => []],
        ];

        if ($componentNo > 0 && Schema::hasTable('tbBolumHavuz')) {
            $poolRows = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', $componentNo)
                ->when(
                    $departmentNo > 0 && Schema::hasColumn('tbBolumHavuz', 'BolumAdiNo'),
                    fn ($q) => $q->where('BolumAdiNo', $departmentNo)
                )
                ->orderBy('No')
                ->limit(20)
                ->get($this->existingColumns('tbBolumHavuz', ['No', 'SiparisSatirNo', 'SiparisNo', 'ToplamAdet', 'Adet', 'Aciklama']));

            $context['pool'] = [
                'count' => $poolRows->count(),
                'total_quantity' => intval($poolRows->sum(fn ($row) => intval($row->ToplamAdet ?? 0))),
                'ready_quantity' => intval($poolRows->sum(fn ($row) => intval($row->Adet ?? 0))),
                'rows' => $poolRows->values(),
            ];
        }

        if ($componentNo > 0 && Schema::hasTable('tbPersonelGorev')) {
            $taskRows = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $componentNo)
                ->when(
                    $departmentNo > 0 && Schema::hasColumn('tbPersonelGorev', 'BolumAdiNo'),
                    fn ($q) => $q->where('BolumAdiNo', $departmentNo)
                )
                ->orderBy('No')
                ->limit(20)
                ->get($this->existingColumns('tbPersonelGorev', ['No', 'SiparisSatirNo', 'SiparisNo', 'PersonelNo', 'Adet', 'BekleyenAdet', 'Onay']));

            $context['personnel_tasks'] = [
                'count' => $taskRows->count(),
                'total_quantity' => intval($taskRows->sum(fn ($row) => intval($row->Adet ?? 0) + intval($row->BekleyenAdet ?? 0))),
                'ready_quantity' => intval($taskRows->sum(fn ($row) => intval($row->Adet ?? 0))),
                'waiting_quantity' => intval($taskRows->sum(fn ($row) => intval($row->BekleyenAdet ?? 0))),
                'rows' => $taskRows->values(),
            ];
        }

        if (Schema::hasTable('tbSiparisSatir')) {
            $orderItemNos = $movements
                ->pluck('order_item_no')
                ->filter(fn ($value) => intval($value) > 0)
                ->map(fn ($value) => intval($value))
                ->unique()
                ->values();

            if ($orderItemNos->isNotEmpty()) {
                $orderRows = DB::table('tbSiparisSatir')
                    ->whereIn('No', $orderItemNos->all())
                    ->orderByDesc('No')
                    ->limit(20)
                    ->get($this->existingColumns('tbSiparisSatir', ['No', 'SiparisNo', 'Musteri', 'UrunAdi', 'Adet', 'Durum', 'GorevNo']));

                $context['orders'] = [
                    'count' => $orderRows->count(),
                    'rows' => $orderRows->values(),
                ];
            }
        }

        return $context;
    }

    private function existingColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
    }

    private function movementAllocationKey(object $movement): string
    {
        $metadata = $this->decodeJsonField($movement->metadata ?? null);

        if (isset($metadata['order_no']) && trim((string) $metadata['order_no']) !== '') {
            return 'order_' . trim((string) $metadata['order_no']);
        }

        if (isset($metadata['work_order_no']) && intval($metadata['work_order_no']) > 0) {
            return 'work_order_' . intval($metadata['work_order_no']);
        }

        $type = (string) ($movement->movement_type ?? '');
        if (str_contains(strtolower($type), 'manual')) {
            return 'manual';
        }

        return 'other';
    }

    private function movementAllocationLabel(object $movement): string
    {
        $metadata = $this->decodeJsonField($movement->metadata ?? null);

        if (isset($metadata['order_no']) && trim((string) $metadata['order_no']) !== '') {
            return 'Sipariş: ' . trim((string) $metadata['order_no']);
        }

        if (isset($metadata['work_order_no']) && intval($metadata['work_order_no']) > 0) {
            return 'İş Emri: #' . intval($metadata['work_order_no']);
        }

        $type = (string) ($movement->movement_type ?? '');
        if (str_contains(strtolower($type), 'manual')) {
            return 'Manuel Düzeltme';
        }

        return 'Sistem / Diğer';
    }

    private function movementSourceLabel(object $movement): string
    {
        $sourceType = trim((string) ($movement->source_type ?? ''));
        $sourceId = trim((string) ($movement->source_id ?? ''));

        if ($sourceType === 'stock_screen') {
            return 'Stok Ekranı';
        }
        if ($sourceType === 'stock_import') {
            return 'İçe Aktarım: ' . $sourceId;
        }
        if ($sourceType === 'work_order_center') {
            return 'İş Emri Merkezi';
        }
        if ($sourceType === 'personnel_task') {
            return 'Personel Görevi #' . $sourceId;
        }

        return $sourceType !== '' ? $sourceType : 'Sistem';
    }

    private function movementReasonHuman(object $movement, array $metadata): string
    {
        $context = is_array($metadata['context'] ?? null) ? $metadata['context'] : $metadata;
        $type = (string) ($movement->movement_type ?? '');
        $quantityDelta = intval($movement->quantity_delta ?? 0);
        $freeDelta = intval($movement->free_delta ?? $movement->buffer_delta ?? 0);
        $reservedForOrder = filter_var($context['reserved_for_order'] ?? false, FILTER_VALIDATE_BOOL);

        return match ($type) {
            'work_order_buffer_reserved' => 'İş emri açılırken boşta stok bu işe ayrıldı; depodaki değişmez, boşta azalır, görevdeki artar.',
            'work_order_buffer_released', 'pool_buffer_released' => 'İptal veya havuz geri dönüşü nedeniyle ayrılmış boşta stok iade edildi; görevdeki azalır.',
            'production_stock_in' => ($reservedForOrder || ($quantityDelta > 0 && $freeDelta === 0))
                ? 'Üretim sipariş/iş emri için kayda alındı; depodaki arttı fakat boşta artmadığı için görevdeki yükseldi.'
                : 'Üretim boşta stoğa eklendi; depodaki ve boşta birlikte arttı.',
            'production_stock_in_reversed', 'cancelled_production_stock_in' => 'Üretim girişi geri alındı; önceki stok dengesi ters hareketle düzeltildi.',
            'production_component_consumed_by_parent' => 'Üst ürün üretime alındığı için bu alt parça stoktan tüketildi.',
            'order_stock_out', 'order_auto_stock_out' => 'Sipariş stoktan karşılandı; depodaki stok ve varsa boşta stok düşürüldü.',
            'order_stock_out_reversed', 'order_auto_stock_out_reversed' => 'Stoktan karşılama geri alındı; stok ve boşta miktar iade edildi.',
            'manual_stock_created', 'manual_stock_added', 'manual_stock_updated' => 'Stok ekranından manuel düzeltme yapıldı; bu satır mevcut dengeyi doğrudan değiştirir.',
            'stock_spreadsheet_imported', 'csv_stock_imported' => 'Excel/CSV aktarımı mevcut stok değerlerini güncelledi.',
            'buffer_reset' => 'Boşta miktar depodaki adetle eşitlendi.',
            default => trim((string) ($movement->description ?? '')) !== ''
                ? (string) $movement->description
                : 'Stok hareketi mevcut dengeye uygulandı.',
        };
    }

    private function movementDirectionLabel(string $direction): string
    {
        $direction = strtolower(trim($direction));
        if ($direction === 'in' || $direction === 'giriş') {
            return 'Giriş';
        }
        if ($direction === 'out' || $direction === 'çıkış') {
            return 'Çıkış';
        }
        return 'Düzeltme';
    }

    private function decodeJsonField(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        try {
            $decoded = json_decode((string) $value, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function nullableMovementInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return intval($value);
    }

    private function freeBalance(?int $quantity, ?int $buffer): ?int
    {
        if ($quantity === null || $buffer === null) {
            return null;
        }
        return max(0, min($quantity, $buffer));
    }

    private function reservedBalance(?int $quantity, ?int $buffer): ?int
    {
        if ($quantity === null || $buffer === null) {
            return null;
        }
        return max(0, $quantity - $buffer);
    }
}
