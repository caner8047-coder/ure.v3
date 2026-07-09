<?php

namespace App\Services;

use App\Models\WorkOrderEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WorkOrderEventLogger
{
    private ?bool $supported = null;

    public function __construct(
        protected WorkOrderSnapshotProjector $snapshotProjector
    ) {}

    public function log(array $attributes): ?WorkOrderEvent
    {
        if (!$this->supportsEvents()) {
            return null;
        }

        $normalized = $this->normalize($attributes);
        $event = WorkOrderEvent::create($normalized);
        $this->snapshotProjector->projectFromEvent($event);

        return $event;
    }

    private function normalize(array $attributes): array
    {
        $actor = $this->resolveActor($attributes);
        $source = $this->resolveSource($attributes);
        $statusBefore = $attributes['status_before'] ?? data_get($attributes, 'payload_before.Durum') ?? null;
        $statusAfter = $attributes['status_after'] ?? data_get($attributes, 'payload_after.Durum') ?? null;
        $orderItemNo = intval($attributes['order_item_no'] ?? 0) > 0 ? intval($attributes['order_item_no']) : null;
        $workOrderNo = intval($attributes['work_order_no'] ?? 0) > 0 ? intval($attributes['work_order_no']) : null;
        $aggregateType = $attributes['aggregate_type'] ?? ($orderItemNo ? 'order_item' : ($workOrderNo ? 'work_order' : 'event'));
        $aggregateId = intval($attributes['aggregate_id'] ?? ($orderItemNo ?: $workOrderNo ?: 0));
        $eventType = trim((string) ($attributes['event_type'] ?? 'event_recorded'));

        $title = trim((string) ($attributes['title_human'] ?? $this->defaultTitle($eventType)));
        $summary = trim((string) ($attributes['summary_human'] ?? $this->defaultSummary(
            $title,
            $statusBefore,
            $statusAfter,
            $actor['actor_name'] ?? null,
            $source['source_screen'] ?? null
        )));

        return [
            'event_uuid' => (string) ($attributes['event_uuid'] ?? Str::uuid()),
            'correlation_id' => (string) ($attributes['correlation_id'] ?? $this->currentCorrelationId()),
            'aggregate_type' => (string) $aggregateType,
            'aggregate_id' => max(1, $aggregateId),
            'order_item_no' => $orderItemNo,
            'order_no' => $this->nullableString($attributes['order_no'] ?? null),
            'work_order_no' => $workOrderNo,
            'pool_no' => intval($attributes['pool_no'] ?? 0) > 0 ? intval($attributes['pool_no']) : null,
            'personnel_task_no' => intval($attributes['personnel_task_no'] ?? 0) > 0 ? intval($attributes['personnel_task_no']) : null,
            'special_production_no' => intval($attributes['special_production_no'] ?? 0) > 0 ? intval($attributes['special_production_no']) : null,
            'event_type' => $eventType,
            'event_group' => (string) ($attributes['event_group'] ?? $this->defaultGroup($eventType)),
            'source_screen' => $source['source_screen'],
            'source_action' => $source['source_action'],
            'source_route' => $source['source_route'],
            'actor_type' => $actor['actor_type'],
            'actor_id' => $actor['actor_id'],
            'actor_name' => $actor['actor_name'],
            'actor_department' => $actor['actor_department'],
            'status_before' => $this->nullableString($statusBefore),
            'status_after' => $this->nullableString($statusAfter),
            'title_human' => $title,
            'summary_human' => $summary,
            'next_step_human' => $this->nullableString($attributes['next_step_human'] ?? null),
            'payload_before' => $this->normalizePayload($attributes['payload_before'] ?? null),
            'payload_after' => $this->normalizePayload($attributes['payload_after'] ?? null),
            'context' => $this->normalizePayload($attributes['context'] ?? null),
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
        $departmentName = $departmentId > 0
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

        $action = trim((string) $request->input('action', ''));
        $path = trim((string) $request->path());
        $method = strtoupper((string) $request->method());

        $screen = 'Sistem';
        $sourceAction = $method . ' ' . $path;

        if ($path === 'SiparisApi.ashx') {
            $screen = match ($action) {
                'createManualWorkOrder' => 'Tekli Is Emri',
                default => 'Siparis Yonetimi',
            };

            $sourceAction = match ($action) {
                'createOrderWorkOrders' => 'Is Emri Ver',
                'createManualWorkOrder' => 'Manuel Is Emri Ver',
                'cancelWorkOrder' => 'Is Emri Iptal',
                'cancelBulkWorkOrders' => 'Toplu Is Emri Iptal',
                'linkOrderToSpecialProduction' => 'GIED Bagla',
                'cancelWipAllocation' => 'GIED Iptal',
                'deductStock' => 'Stoktan Dus',
                'deductStockBulk' => 'Toplu Stoktan Dus',
                'undoDeductStock' => 'Stoktan Dus Geri Al',
                'passivateWithWorkOrderCancel' => 'Pasife Al ve Is Emri Iptal',
                'reactivateOrder' => 'Tekrar Aktif Et',
                default => $action !== '' ? $action : $sourceAction,
            };
        } elseif ($path === 'TopluIsEmriApi.ashx') {
            $screen = 'Excel Toplu Is Emri';
            $sourceAction = match ($action) {
                'createWorkOrders' => 'Excelden Toplu Is Emri Ver',
                'getProducts' => 'Urunleri Getir',
                default => $action !== '' ? $action : $sourceAction,
            };
        } elseif (str_starts_with($path, 'api/panel')) {
            $screen = 'Personel Paneli';
            $sourceAction = match (true) {
                str_contains($path, '/take-task/') => 'Gorev Al',
                str_contains($path, '/complete') => 'Uretim Girisi',
                $method === 'DELETE' && str_contains($path, '/task/') => 'Gorev Sil',
                default => $sourceAction,
            };
        } elseif (str_starts_with($path, 'api/planning')) {
            $screen = 'Uretim Planlama';
            $sourceAction = match (true) {
                str_contains($path, '/increment/') => 'Planlama +1',
                str_contains($path, '/decrement/') => 'Planlama -1',
                str_contains($path, '/date') => 'Tarih Tasindi',
                default => $sourceAction,
            };
        } elseif (str_starts_with($path, 'api/database')) {
            $screen = 'Admin Database';
            $sourceAction = match (true) {
                str_contains($path, '/assign') => 'Personele Gorev Ata',
                default => $sourceAction,
            };
        }

        return [
            'source_screen' => $screen,
            'source_action' => $sourceAction,
            'source_route' => $path !== '' ? $path : null,
        ];
    }

    private function defaultTitle(string $eventType): string
    {
        return match ($eventType) {
            'work_order_created_single' => 'Siparise is emri verildi',
            'work_order_created_bulk' => 'Toplu is emri verildi',
            'work_order_created_manual' => 'Manuel is emri olusturuldu',
            'work_order_cancelled' => 'Is emri iptal edildi',
            'wip_linked' => 'Siparis ozel uretime baglandi',
            'wip_unlinked' => 'Ozel uretim baglantisi kaldirildi',
            'stock_deducted' => 'Siparis stoktan karsilandi',
            'stock_deduction_reversed' => 'Stoktan dusme geri alindi',
            'order_passivated' => 'Siparis pasife alindi',
            'order_reactivated' => 'Siparis tekrar aktif edildi',
            'personnel_task_taken' => 'Personel gorevi ustune aldi',
            'production_completed_partial' => 'Kismi uretim girisi yapildi',
            'production_completed_full' => 'Uretim tamamlandi',
            'personnel_task_deleted' => 'Personel gorevi silindi',
            'planning_incremented' => 'Planlamada gorev adedi artirildi',
            'planning_decremented' => 'Planlamada gorev adedi azaltildi',
            'planning_rescheduled' => 'Planlama tarihi degisti',
            'task_assigned_by_admin' => 'Yonetici personele gorev atadi',
            default => str_replace('_', ' ', $eventType),
        };
    }

    private function defaultGroup(string $eventType): string
    {
        return match ($eventType) {
            'work_order_created_single', 'work_order_created_bulk', 'work_order_created_manual' => 'create',
            'work_order_cancelled' => 'cancel',
            'wip_linked', 'wip_unlinked' => 'wip',
            'stock_deducted', 'stock_deduction_reversed' => 'stock',
            'order_passivated' => 'status',
            'personnel_task_taken', 'production_completed_partial', 'production_completed_full', 'personnel_task_deleted', 'task_assigned_by_admin' => 'production',
            'planning_incremented', 'planning_decremented', 'planning_rescheduled' => 'planning',
            'order_reactivated' => 'status',
            default => 'system',
        };
    }

    private function defaultSummary(
        string $title,
        mixed $statusBefore,
        mixed $statusAfter,
        ?string $actorName,
        ?string $sourceScreen
    ): string {
        $summary = '';

        if ($actorName && $sourceScreen) {
            $summary = $actorName . ', ' . $sourceScreen . ' ekranindan ' . mb_strtolower($title) . '.';
        } elseif ($actorName) {
            $summary = $actorName . ' ' . mb_strtolower($title) . '.';
        } else {
            $summary = $title . '.';
        }

        if ($statusBefore || $statusAfter) {
            $summary .= ' Once: ' . ($statusBefore ?: 'bilinmiyor') . '.';
            $summary .= ' Sonra: ' . ($statusAfter ?: 'bilinmiyor') . '.';
        }

        return $summary;
    }

    private function currentCorrelationId(): string
    {
        if (app()->bound('work_order_center.correlation_id')) {
            return (string) app('work_order_center.correlation_id');
        }

        $request = request();
        $correlationId = trim((string) ($request?->header('X-Correlation-Id') ?? ''));
        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        app()->instance('work_order_center.correlation_id', $correlationId);

        return $correlationId;
    }

    private function normalizePayload(mixed $value): ?array
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

    private function supportsEvents(): bool
    {
        if ($this->supported === null) {
            $this->supported = Schema::hasTable('work_order_events');
        }

        return $this->supported;
    }
}
