<?php

namespace App\Services;

use App\Events\CriticalStockAlert;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StockMovementLogger
{
    private ?bool $supported = null;

    public function logChange(object|array|null $before, object|array|null $after, array $attributes = []): ?StockMovement
    {
        $beforeRow = $this->normalizeRecord($before);
        $afterRow = $this->normalizeRecord($after);

        $quantityBefore = $beforeRow !== null ? intval($beforeRow['Adet'] ?? 0) : ($afterRow !== null ? 0 : null);
        $quantityAfter = $afterRow !== null ? intval($afterRow['Adet'] ?? 0) : ($quantityBefore !== null ? 0 : null);
        $bufferBefore = $beforeRow !== null ? intval($beforeRow['TamponMiktar'] ?? 0) : ($afterRow !== null ? 0 : null);
        $bufferAfter = $afterRow !== null ? intval($afterRow['TamponMiktar'] ?? 0) : ($bufferBefore !== null ? 0 : null);

        $quantityDelta = array_key_exists('quantity_delta', $attributes)
            ? intval($attributes['quantity_delta'])
            : (($quantityBefore !== null && $quantityAfter !== null) ? $quantityAfter - $quantityBefore : 0);

        $bufferDelta = array_key_exists('buffer_delta', $attributes)
            ? intval($attributes['buffer_delta'])
            : (($bufferBefore !== null && $bufferAfter !== null) ? $bufferAfter - $bufferBefore : 0);

        if ($quantityDelta === 0 && $bufferDelta === 0 && empty($attributes['force'])) {
            return null;
        }

        $metadata = array_filter([
            'before' => $beforeRow,
            'after' => $afterRow,
            'context' => $attributes['metadata'] ?? null,
        ], fn ($value) => $value !== null);

        $payload = array_merge([
            'stock_row_no' => $attributes['stock_row_no'] ?? $afterRow['No'] ?? $beforeRow['No'] ?? null,
            'component_no' => $attributes['component_no'] ?? $afterRow['AraUrunAdiNo'] ?? $beforeRow['AraUrunAdiNo'] ?? null,
            'department_no' => $attributes['department_no'] ?? $afterRow['BolumAdiNo'] ?? $beforeRow['BolumAdiNo'] ?? null,
            'product_no' => $attributes['product_no'] ?? $afterRow['UrunIDNo'] ?? $beforeRow['UrunIDNo'] ?? null,
            'quantity_before' => $quantityBefore,
            'quantity_delta' => $quantityDelta,
            'quantity_after' => $quantityAfter,
            'buffer_before' => $bufferBefore,
            'buffer_delta' => $bufferDelta,
            'buffer_after' => $bufferAfter,
        ], $attributes);

        $payload['metadata'] = $metadata;

        $movement = $this->log($payload);

        // Check critical stock thresholds and broadcast alert if needed
        if ($movement && $quantityAfter !== null) {
            $this->checkCriticalStockThreshold($movement, $quantityAfter, $payload);
        }

        return $movement;
    }

    public function log(array $attributes): ?StockMovement
    {
        if (!$this->supportsMovements()) {
            return null;
        }

        try {
            return StockMovement::create($this->normalize($attributes));
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalize(array $attributes): array
    {
        $actor = $this->resolveActor($attributes);
        $source = $this->resolveSource($attributes);
        $quantityDelta = intval($attributes['quantity_delta'] ?? 0);
        $bufferDelta = intval($attributes['buffer_delta'] ?? 0);
        $movementType = trim((string) ($attributes['movement_type'] ?? 'stock_adjusted'));

        return [
            'movement_uuid' => (string) ($attributes['movement_uuid'] ?? Str::uuid()),
            'stock_row_no' => $this->positiveIntOrNull($attributes['stock_row_no'] ?? null),
            'component_no' => $this->positiveIntOrNull($attributes['component_no'] ?? null),
            'department_no' => $this->positiveIntOrNull($attributes['department_no'] ?? null),
            'product_no' => $this->positiveIntOrNull($attributes['product_no'] ?? null),
            'movement_type' => $movementType,
            'direction' => (string) ($attributes['direction'] ?? $this->directionFor($quantityDelta, $bufferDelta)),
            'title_human' => trim((string) ($attributes['title_human'] ?? $this->defaultTitle($movementType))),
            'quantity_before' => $this->nullableInt($attributes['quantity_before'] ?? null),
            'quantity_delta' => $quantityDelta,
            'quantity_after' => $this->nullableInt($attributes['quantity_after'] ?? null),
            'buffer_before' => $this->nullableInt($attributes['buffer_before'] ?? null),
            'buffer_delta' => $bufferDelta,
            'buffer_after' => $this->nullableInt($attributes['buffer_after'] ?? null),
            'source_type' => $this->nullableString($attributes['source_type'] ?? null),
            'source_id' => $this->nullableString($attributes['source_id'] ?? null),
            'order_item_no' => $this->positiveIntOrNull($attributes['order_item_no'] ?? null),
            'order_no' => $this->nullableString($attributes['order_no'] ?? null),
            'work_order_no' => $this->positiveIntOrNull($attributes['work_order_no'] ?? null),
            'pool_no' => $this->positiveIntOrNull($attributes['pool_no'] ?? null),
            'personnel_task_no' => $this->positiveIntOrNull($attributes['personnel_task_no'] ?? null),
            'source_screen' => $source['source_screen'],
            'source_action' => $source['source_action'],
            'source_route' => $source['source_route'],
            'actor_type' => $actor['actor_type'],
            'actor_id' => $actor['actor_id'],
            'actor_name' => $actor['actor_name'],
            'actor_department' => $actor['actor_department'],
            'description' => $this->nullableString($attributes['description'] ?? null),
            'metadata' => $this->normalizeMetadata($attributes['metadata'] ?? null),
            'happened_at' => $attributes['happened_at'] ?? now(),
        ];
    }

    private function resolveActor(array $attributes): array
    {
        if (isset($attributes['actor_type']) || isset($attributes['actor_id']) || isset($attributes['actor_name'])) {
            return [
                'actor_type' => (string) ($attributes['actor_type'] ?? 'system'),
                'actor_id' => $this->nullableString($attributes['actor_id'] ?? null),
                'actor_name' => $this->nullableString($attributes['actor_name'] ?? null),
                'actor_department' => $this->nullableString($attributes['actor_department'] ?? null),
            ];
        }

        $user = Auth::user();
        if (!$user) {
            return [
                'actor_type' => 'system',
                'actor_id' => null,
                'actor_name' => 'Sistem',
                'actor_department' => null,
            ];
        }

        $firstName = trim((string) ($user->name ?? $user->Ad ?? ''));
        $lastName = trim((string) ($user->surname ?? $user->Soyad ?? ''));
        $fullName = trim($firstName . ' ' . $lastName);
        $departmentId = intval($user->department_id ?? $user->BolumAdiNo ?? 0);
        $departmentName = $departmentId > 0 && Schema::hasTable('tbBolum')
            ? trim((string) (DB::table('tbBolum')->where('No', $departmentId)->value('BolumAdi') ?? ''))
            : null;

        return [
            'actor_type' => method_exists($user, 'isAdmin') && $user->isAdmin() ? 'admin' : 'personnel',
            'actor_id' => $this->nullableString($user->id ?? $user->personnel_no ?? $user->PersonelNo ?? null),
            'actor_name' => $fullName !== '' ? $fullName : 'Bilinmeyen Kullanici',
            'actor_department' => $this->nullableString($departmentName),
        ];
    }

    private function resolveSource(array $attributes): array
    {
        if (isset($attributes['source_screen']) || isset($attributes['source_action']) || isset($attributes['source_route'])) {
            return [
                'source_screen' => $this->nullableString($attributes['source_screen'] ?? null),
                'source_action' => $this->nullableString($attributes['source_action'] ?? null),
                'source_route' => $this->nullableString($attributes['source_route'] ?? null),
            ];
        }

        $request = request();
        if (!$request) {
            return [
                'source_screen' => 'Sistem',
                'source_action' => 'Otomatik Islem',
                'source_route' => null,
            ];
        }

        $path = trim((string) $request->path());
        $method = strtoupper((string) $request->method());
        $action = trim((string) $request->input('action', ''));
        $sourceAction = $method . ' ' . $path;

        $screen = match (true) {
            $path === 'SiparisApi.ashx' => 'Siparis Yonetimi',
            str_starts_with($path, 'api/stocks') => 'Stok Yonetimi',
            str_starts_with($path, 'api/panel') => 'Personel Paneli',
            str_starts_with($path, 'api/database') => 'Admin Database',
            default => 'Sistem',
        };

        if ($action !== '') {
            $sourceAction = $action;
        }

        return [
            'source_screen' => $screen,
            'source_action' => $sourceAction,
            'source_route' => $path !== '' ? $path : null,
        ];
    }

    private function defaultTitle(string $movementType): string
    {
        return match ($movementType) {
            'manual_stock_created' => 'Manuel stok kaydi olusturuldu',
            'manual_stock_added' => 'Manuel stok girisi yapildi',
            'manual_stock_updated' => 'Manuel stok duzeltildi',
            'manual_stock_deleted' => 'Stok kaydi silindi',
            'csv_stock_imported' => 'CSV ile stok guncellendi',
            'buffer_reset' => 'Tampon stok esitlendi',
            'order_stock_out' => 'Siparis icin stok cikisi',
            'order_stock_out_reversed' => 'Siparis stok cikisi geri alindi',
            'order_auto_stock_out' => 'Siparis kapanisinda stok cikisi',
            'order_auto_stock_out_reversed' => 'Siparis kapanis stok cikisi geri alindi',
            'production_stock_in' => 'Uretimden stok girisi',
            'production_stock_in_reversed' => 'Uretim stok girisi geri alindi',
            'production_component_consumed_by_parent' => 'Alt parca ust gorevde tuketildi',
            'cancelled_production_stock_in' => 'Iptal edilen uretim stok girisi',
            'work_order_stock_movement_reversed' => 'Is emri stok hareketi geri alindi',
            'work_order_buffer_reserved' => 'Is emri icin tampon ayrildi',
            'work_order_buffer_released' => 'Is emri iptalinden tampon iade edildi',
            'pool_buffer_released' => 'Havuz iptalinden tampon iade edildi',
            default => str_replace('_', ' ', $movementType),
        };
    }

    private function directionFor(int $quantityDelta, int $bufferDelta): string
    {
        if ($quantityDelta > 0) {
            return 'in';
        }

        if ($quantityDelta < 0) {
            return 'out';
        }

        if ($bufferDelta < 0) {
            return 'reserve';
        }

        if ($bufferDelta > 0) {
            return 'release';
        }

        return 'neutral';
    }

    private function normalizeRecord(object|array|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true);
    }

    private function normalizeMetadata(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        }

        return ['value' => $value];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : intval($value);
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $int = intval($value ?? 0);

        return $int > 0 ? $int : null;
    }

    private function supportsMovements(): bool
    {
        if ($this->supported === null) {
            $this->supported = Schema::hasTable('stock_movements');
        }

        return $this->supported;
    }

    /**
     * Check if component quantity is below critical threshold and broadcast alert.
     */
    private function checkCriticalStockThreshold(StockMovement $movement, int $quantityAfter, array $payload): void
    {
        $componentNo = $movement->component_no;
        if (!$componentNo) {
            return;
        }

        try {
            $threshold = DB::table('tbKritikStokEsik')
                ->where('AraUrunAdiNo', $componentNo)
                ->where('Aktif', 1)
                ->first();

            if (!$threshold) {
                return;
            }

            $esikMiktar = intval($threshold->EsikMiktar);
            if ($quantityAfter <= $esikMiktar) {
                // Get component name
                $componentName = DB::table('tbAraUrun')
                    ->where('No', $componentNo)
                    ->value('AraUrunAdi') ?? 'Bilinmeyen Ürün';

                // Get department name
                $departmentNo = $movement->department_no;
                $departmentName = 'Genel Depo';
                if ($departmentNo) {
                    $departmentName = DB::table('tbBolum')
                        ->where('No', $departmentNo)
                        ->value('BolumAdi') ?? 'Departman #' . $departmentNo;
                }

                broadcast(new CriticalStockAlert(
                    stockRowNo: intval($movement->stock_row_no ?? 0),
                    componentNo: $componentNo,
                    departmentNo: $departmentNo,
                    productNo: $movement->product_no,
                    componentName: $componentName,
                    departmentName: $departmentName,
                    currentQuantity: $quantityAfter,
                    thresholdQuantity: $esikMiktar,
                    quantityDelta: intval($movement->quantity_delta),
                    forecastSummary: null
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('CriticalStockThreshold check or broadcast failed: ' . $e->getMessage());
        }
    }
}
