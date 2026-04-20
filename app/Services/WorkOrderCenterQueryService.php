<?php

namespace App\Services;

use App\Models\WorkOrderEvent;
use App\Models\WorkOrderSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class WorkOrderCenterQueryService
{
    public function __construct(
        protected WorkOrderNarrationService $narrationService
    ) {}

    public function feed(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(100, intval($filters['per_page'] ?? 25)));
        $query = $this->buildFeedQuery($filters);

        $paginator = $query->paginate($perPage)->withQueryString();
        $rows = collect($paginator->items());
        $snapshotMap = $this->loadSnapshotMap($rows, $filters);

        $transformed = $rows->map(function (WorkOrderEvent $event) use ($snapshotMap) {
            return $this->transformFeedEvent($event, $this->resolveSnapshotForEvent($event, $snapshotMap));
        })->all();

        $paginator->setCollection(collect($transformed));

        return $paginator;
    }

    public function exportRows(array $filters = []): Collection
    {
        $limit = max(1, min(5000, intval($filters['limit'] ?? 2000)));
        $rows = $this->buildFeedQuery($filters)
            ->limit($limit)
            ->get();
        $snapshotMap = $this->loadSnapshotMap($rows, $filters);

        return $rows
            ->map(fn (WorkOrderEvent $event) => $this->transformFeedEvent($event, $this->resolveSnapshotForEvent($event, $snapshotMap)))
            ->values();
    }

    public function entity(string $type, int $id): array
    {
        $timelineFilters = match ($type) {
            'order_item' => ['order_item_no' => $id],
            'work_order' => ['work_order_no' => $id],
            default => ['aggregate_type' => $type, 'aggregate_id' => $id],
        };

        $timeline = $this->timeline(array_merge($timelineFilters, ['limit' => 50]));

        if (!in_array($type, ['order_item', 'work_order'], true)) {
            return $this->buildGenericEntityPayload($type, $id, $timeline);
        }

        $snapshot = WorkOrderSnapshot::query()
            ->where('aggregate_type', $type)
            ->where('aggregate_id', $id)
            ->first();

        if (!$snapshot && $type === 'order_item') {
            $snapshot = app(WorkOrderSnapshotProjector::class)->projectOrderItem($id);
        } elseif (!$snapshot && $type === 'work_order') {
            $snapshot = app(WorkOrderSnapshotProjector::class)->projectWorkOrder($id);
        }

        $snapshotData = $snapshot?->snapshot ?? [];
        $timelineInsights = $this->narrationService->buildTimelineInsights($timeline);
        $narration = $this->narrationService->buildSnapshotNarration([
            'current_status' => $snapshot?->current_status,
            'current_stage' => $snapshot?->current_stage,
            'current_holder_name' => $snapshot?->current_holder_name,
            'next_expected_action' => $snapshot?->next_expected_action,
        ], $timeline);

        return [
            'entity' => [
                'type' => $type,
                'id' => $id,
                'order_item_no' => $snapshot?->order_item_no,
                'order_no' => $snapshot?->order_no,
                'work_order_no' => $snapshot?->work_order_no,
                'current_status' => $snapshot?->current_status,
                'current_stage' => $snapshot?->current_stage,
                'current_holder_type' => $snapshot?->current_holder_type,
                'current_holder_name' => $snapshot?->current_holder_name,
                'linked_special_production_no' => $snapshot?->linked_special_production_no,
                'next_expected_action' => $snapshot?->next_expected_action,
                'last_changed_at' => $this->narrationService->formatDateTime($snapshot?->last_changed_at),
                'alert_count' => intval($snapshot?->alert_count ?? 0),
            ],
            'narration' => $narration,
            'insights' => $timelineInsights,
            'alerts' => collect($snapshotData['alerts'] ?? [])->values(),
            'diagnostics' => $this->buildDiagnostics($timeline, collect($snapshotData['alerts'] ?? [])),
            'related' => [
                'matched_product_no' => data_get($snapshotData, 'matched_product_no'),
                'matched_product_type' => data_get($snapshotData, 'matched_product_type'),
                'special_production_no' => data_get($snapshotData, 'special_production_no'),
                'holder' => data_get($snapshotData, 'holder'),
                'counts' => data_get($snapshotData, 'counts', []),
            ],
            'timeline' => $timeline->map(fn (WorkOrderEvent $event) => $this->transformTimelineEvent($event))->values(),
            'technical' => [
                'snapshot' => $snapshotData,
                'last_event_id' => $snapshot?->last_event_id,
            ],
        ];
    }

    public function timeline(array $filters = []): Collection
    {
        $limit = max(1, min(200, intval($filters['limit'] ?? 100)));
        $query = WorkOrderEvent::query()->orderByDesc('happened_at')->orderByDesc('id');

        if (!empty($filters['order_item_no'])) {
            $query->where('order_item_no', intval($filters['order_item_no']));
        }

        if (!empty($filters['work_order_no'])) {
            $query->where('work_order_no', intval($filters['work_order_no']));
        }

        if (!empty($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }

        if (!empty($filters['aggregate_type'])) {
            $query->where('aggregate_type', $filters['aggregate_type']);
        }

        if (!empty($filters['aggregate_id'])) {
            $query->where('aggregate_id', intval($filters['aggregate_id']));
        }

        return $query->limit($limit)->get();
    }

    public function timelinePayload(array $filters = []): Collection
    {
        return $this->timeline($filters)
            ->map(fn (WorkOrderEvent $event) => $this->transformTimelineEvent($event))
            ->values();
    }

    public function lookups(): array
    {
        if (!Schema::hasTable('work_order_events')) {
            return [
                'event_types' => [],
                'event_groups' => [],
                'source_screens' => [],
                'source_actions' => [],
                'actor_names' => [],
                'actor_types' => [],
                'statuses' => [],
            ];
        }

        return [
            'event_types' => WorkOrderEvent::query()->whereNotNull('event_type')->distinct()->orderBy('event_type')->pluck('event_type')->values(),
            'event_groups' => WorkOrderEvent::query()->whereNotNull('event_group')->distinct()->orderBy('event_group')->pluck('event_group')->values(),
            'source_screens' => WorkOrderEvent::query()->whereNotNull('source_screen')->distinct()->orderBy('source_screen')->pluck('source_screen')->values(),
            'source_actions' => WorkOrderEvent::query()->whereNotNull('source_action')->distinct()->orderBy('source_action')->pluck('source_action')->values(),
            'actor_names' => WorkOrderEvent::query()->whereNotNull('actor_name')->distinct()->orderBy('actor_name')->pluck('actor_name')->values(),
            'actor_types' => WorkOrderEvent::query()->whereNotNull('actor_type')->distinct()->orderBy('actor_type')->pluck('actor_type')->values(),
            'statuses' => collect(
                array_unique(array_merge(
                    WorkOrderEvent::query()->whereNotNull('status_before')->distinct()->pluck('status_before')->all(),
                    WorkOrderEvent::query()->whereNotNull('status_after')->distinct()->pluck('status_after')->all(),
                    WorkOrderSnapshot::query()->whereNotNull('current_status')->distinct()->pluck('current_status')->all()
                ))
            )->filter()->sort()->values(),
        ];
    }

    private function buildFeedQuery(array $filters = [])
    {
        $query = WorkOrderEvent::query()->orderByDesc('happened_at')->orderByDesc('id');

        if ($this->shouldHideSeededByDefault($filters)) {
            $query->where('event_type', '!=', 'state_seeded');
        }

        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('order_no', 'like', '%' . $q . '%')
                    ->orWhere('actor_name', 'like', '%' . $q . '%')
                    ->orWhere('actor_department', 'like', '%' . $q . '%')
                    ->orWhere('title_human', 'like', '%' . $q . '%')
                    ->orWhere('summary_human', 'like', '%' . $q . '%')
                    ->orWhere('source_screen', 'like', '%' . $q . '%')
                    ->orWhere('source_action', 'like', '%' . $q . '%')
                    ->orWhere('status_after', 'like', '%' . $q . '%')
                    ->orWhere('event_type', 'like', '%' . $q . '%');

                if (ctype_digit($q)) {
                    $subQuery->orWhere('order_item_no', intval($q))
                        ->orWhere('work_order_no', intval($q))
                        ->orWhere('personnel_task_no', intval($q))
                        ->orWhere('special_production_no', intval($q))
                        ->orWhere('aggregate_id', intval($q));
                }
            });
        }

        foreach (['event_type', 'event_group', 'actor_id', 'source_screen', 'source_action', 'actor_name', 'actor_type', 'aggregate_type'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (!empty($filters['status'])) {
            $this->applyStatusFilter($query, trim((string) $filters['status']));
        }

        if (!empty($filters['has_alerts'])) {
            $alertOrderIds = WorkOrderSnapshot::query()
                ->where('aggregate_type', 'order_item')
                ->where('alert_count', '>', 0)
                ->pluck('aggregate_id')
                ->map(fn ($id) => intval($id))
                ->filter()
                ->all();
            $alertWorkOrderIds = WorkOrderSnapshot::query()
                ->where('aggregate_type', 'work_order')
                ->where('alert_count', '>', 0)
                ->pluck('aggregate_id')
                ->map(fn ($id) => intval($id))
                ->filter()
                ->all();

            if (empty($alertOrderIds) && empty($alertWorkOrderIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($subQuery) use ($alertOrderIds, $alertWorkOrderIds) {
                    if (!empty($alertOrderIds)) {
                        $subQuery->whereIn('order_item_no', $alertOrderIds);
                    }

                    if (!empty($alertWorkOrderIds)) {
                        $method = !empty($alertOrderIds) ? 'orWhereIn' : 'whereIn';
                        $subQuery->{$method}('work_order_no', $alertWorkOrderIds)
                            ->orWhere(function ($workOrderQuery) use ($alertWorkOrderIds) {
                                $workOrderQuery->where('aggregate_type', 'work_order')
                                    ->whereIn('aggregate_id', $alertWorkOrderIds);
                            });
                    }
                });
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('happened_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('happened_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function shouldHideSeededByDefault(array $filters): bool
    {
        return empty($filters['include_seeded']);
    }

    private function applyStatusFilter($query, string $status): void
    {
        $orderItemSnapshotIds = WorkOrderSnapshot::query()
            ->where('aggregate_type', 'order_item')
            ->where('current_status', $status)
            ->pluck('aggregate_id')
            ->map(fn ($id) => intval($id))
            ->filter()
            ->all();

        $workOrderSnapshotIds = WorkOrderSnapshot::query()
            ->where('aggregate_type', 'work_order')
            ->where('current_status', $status)
            ->pluck('aggregate_id')
            ->map(fn ($id) => intval($id))
            ->filter()
            ->all();

        $query->where(function ($subQuery) use ($status, $orderItemSnapshotIds, $workOrderSnapshotIds) {
            $subQuery->where('status_after', $status)
                ->orWhere('status_before', $status);

            if (!empty($orderItemSnapshotIds)) {
                $subQuery->orWhereIn('order_item_no', $orderItemSnapshotIds);
            }

            if (!empty($workOrderSnapshotIds)) {
                $subQuery->orWhereIn('work_order_no', $workOrderSnapshotIds)
                    ->orWhere(function ($workOrderQuery) use ($workOrderSnapshotIds) {
                        $workOrderQuery->where('aggregate_type', 'work_order')
                            ->whereIn('aggregate_id', $workOrderSnapshotIds);
                    });
            }
        });
    }

    private function transformFeedEvent(WorkOrderEvent $event, ?WorkOrderSnapshot $snapshot): array
    {
        return [
            'id' => $event->id,
            'event_uuid' => $event->event_uuid,
            'correlation_id' => $event->correlation_id,
            'aggregate_type' => $event->aggregate_type,
            'aggregate_id' => $event->aggregate_id,
            'order_item_no' => $event->order_item_no,
            'order_no' => $event->order_no,
            'work_order_no' => $event->work_order_no,
            'event_type' => $event->event_type,
            'event_group' => $event->event_group,
            'title_human' => $event->title_human,
            'summary_human' => $event->summary_human,
            'next_step_human' => $event->next_step_human,
            'source_screen' => $event->source_screen,
            'source_action' => $event->source_action,
            'actor_name' => $event->actor_name,
            'actor_type' => $event->actor_type,
            'actor_department' => $event->actor_department,
            'status_before' => $event->status_before,
            'status_after' => $event->status_after,
            'happened_at' => $this->narrationService->formatDateTime($event->happened_at),
            'snapshot' => $snapshot ? [
                'current_status' => $snapshot->current_status,
                'current_stage' => $snapshot->current_stage,
                'current_holder_name' => $snapshot->current_holder_name,
                'next_expected_action' => $snapshot->next_expected_action,
                'alert_count' => intval($snapshot->alert_count ?? 0),
            ] : null,
        ];
    }

    private function buildGenericEntityPayload(string $type, int $id, Collection $timeline): array
    {
        $latestEvent = $timeline->first();
        $payloadAfter = $latestEvent?->payload_after ?? [];
        $timelineInsights = $this->narrationService->buildTimelineInsights($timeline);
        $narration = $this->narrationService->buildSnapshotNarration([
            'current_status' => $latestEvent?->status_after ?? $latestEvent?->status_before,
            'current_stage' => data_get($payloadAfter, 'current_stage'),
            'current_holder_name' => data_get($payloadAfter, 'current_holder_name'),
            'next_expected_action' => $latestEvent?->next_step_human,
        ], $timeline);

        return [
            'entity' => [
                'type' => $type,
                'id' => $id,
                'order_item_no' => $latestEvent?->order_item_no,
                'order_no' => $latestEvent?->order_no,
                'work_order_no' => $latestEvent?->work_order_no,
                'current_status' => $latestEvent?->status_after ?? $latestEvent?->status_before,
                'current_stage' => data_get($payloadAfter, 'current_stage'),
                'current_holder_type' => data_get($payloadAfter, 'current_holder_type'),
                'current_holder_name' => data_get($payloadAfter, 'current_holder_name'),
                'linked_special_production_no' => $latestEvent?->special_production_no,
                'next_expected_action' => $latestEvent?->next_step_human,
                'last_changed_at' => $this->narrationService->formatDateTime($latestEvent?->happened_at),
                'alert_count' => 0,
            ],
            'narration' => $narration,
            'insights' => $timelineInsights,
            'alerts' => [],
            'diagnostics' => $this->buildDiagnostics($timeline, collect()),
            'related' => [
                'matched_product_no' => data_get($payloadAfter, 'EslesenUrunNo'),
                'matched_product_type' => data_get($payloadAfter, 'EslesenUrunTur'),
                'special_production_no' => $latestEvent?->special_production_no,
                'holder' => [
                    'type' => data_get($payloadAfter, 'current_holder_type'),
                    'name' => data_get($payloadAfter, 'current_holder_name'),
                ],
                'counts' => [],
            ],
            'timeline' => $timeline->map(fn (WorkOrderEvent $event) => $this->transformTimelineEvent($event))->values(),
            'technical' => [
                'snapshot' => [],
                'last_event_id' => $latestEvent?->id,
                'aggregate_type' => $type,
                'aggregate_id' => $id,
            ],
        ];
    }

    private function transformTimelineEvent(WorkOrderEvent $event): array
    {
        return [
            'id' => $event->id,
            'event_uuid' => $event->event_uuid,
            'correlation_id' => $event->correlation_id,
            'event_type' => $event->event_type,
            'event_group' => $event->event_group,
            'title_human' => $event->title_human,
            'summary_human' => $event->summary_human,
            'next_step_human' => $event->next_step_human,
            'source_screen' => $event->source_screen,
            'source_action' => $event->source_action,
            'source_route' => $event->source_route,
            'actor_name' => $event->actor_name,
            'actor_type' => $event->actor_type,
            'actor_department' => $event->actor_department,
            'status_before' => $event->status_before,
            'status_after' => $event->status_after,
            'order_item_no' => $event->order_item_no,
            'order_no' => $event->order_no,
            'work_order_no' => $event->work_order_no,
            'pool_no' => $event->pool_no,
            'personnel_task_no' => $event->personnel_task_no,
            'special_production_no' => $event->special_production_no,
            'happened_at' => $this->narrationService->formatDateTime($event->happened_at),
            'payload_before' => $event->payload_before,
            'payload_after' => $event->payload_after,
            'context' => $event->context,
        ];
    }

    private function buildDiagnostics(Collection $timeline, Collection $baseAlerts): array
    {
        $normalizedBaseAlerts = $baseAlerts
            ->map(fn ($alert) => is_array($alert) ? $alert : (array) $alert)
            ->values();

        $diagnostics = [];

        if ($timeline->isEmpty()) {
            return $normalizedBaseAlerts->all();
        }

        $missingActorCount = $timeline->filter(fn (WorkOrderEvent $event) => trim((string) ($event->actor_name ?? '')) === '')->count();
        if ($missingActorCount > 0) {
            $diagnostics[] = [
                'severity' => 'low',
                'message' => 'Bazi eski adimlarda islemi yapan kisi bilgisi gorunmuyor.',
                'suggested_fix' => 'Bu eksik bilgi eski kayitlardan gelmis olabilir; gerekirse ilgili ekrani veya kullaniciyi manuel kontrol edin.',
            ];
        }

        $missingSourceCount = $timeline->filter(fn (WorkOrderEvent $event) => trim((string) ($event->source_screen ?? '')) === '')->count();
        if ($missingSourceCount > 0) {
            $diagnostics[] = [
                'severity' => 'low',
                'message' => 'Bazi eski adimlarda islemin hangi ekrandan yapildigi gorunmuyor.',
                'suggested_fix' => 'Bu eksik bilgi eski sistemden tasinmis olabilir; kritik bir kayitsa manuel kontrol edin.',
            ];
        }

        $backfilledCount = $timeline->filter(fn (WorkOrderEvent $event) => boolval(data_get($event->context, 'backfilled', false)))->count();
        if ($backfilledCount > 0) {
            $diagnostics[] = [
                'severity' => 'low',
                'message' => 'Bu hikayenin bir kismi eski sistemden merkeze tasindi.',
                'suggested_fix' => 'Eski kayitlarda kisi veya ekran bilgisi eksik olabilir; kritik karar vermeden once manuel dogrulama yapin.',
            ];
        }

        $seededCount = $timeline->filter(fn (WorkOrderEvent $event) => boolval(data_get($event->context, 'seeded_current_state', false)))->count();
        if ($seededCount > 0) {
            $diagnostics[] = [
                'severity' => 'medium',
                'message' => 'Bu kaydin eski gecmisi eksik oldugu icin hikaye mevcut durumdan baslatildi.',
                'suggested_fix' => 'Tam gecmis gerekiyorsa eski kayitlar veya ilgili operasyon loglari ayrica incelenmeli.',
            ];
        }

        $allSystem = $timeline->every(fn (WorkOrderEvent $event) => ($event->actor_type ?? '') === 'system');
        if ($allSystem && $timeline->count() > 0) {
            $diagnostics[] = [
                'severity' => 'medium',
                'message' => 'Bu kayitta gordugumuz adimlar kullanici yerine sistem kaydi olarak gorunuyor.',
                'suggested_fix' => 'Bu normal degilse islem loglarini ve actor bilgisini kontrol edin.',
            ];
        }

        $latestEvent = $timeline->first();
        if ($latestEvent && trim((string) ($latestEvent->status_before ?? '')) === trim((string) ($latestEvent->status_after ?? ''))
            && trim((string) ($latestEvent->status_after ?? '')) !== ''
        ) {
            $diagnostics[] = [
                'severity' => 'low',
                'message' => 'Son kayitta durum degismemis; bu sadece bilgi kaydi olabilir.',
                'suggested_fix' => 'Beklenmiyorsa ayni olaylarin tekrar yazilip yazilmadigi kontrol edilmeli.',
            ];
        }

        return $normalizedBaseAlerts
            ->concat($diagnostics)
            ->values()
            ->all();
    }

    private function loadSnapshotMap(Collection $events, array $filters = []): array
    {
        $orderItemNos = $events->pluck('order_item_no')->filter()->unique()->values();
        $workOrderNos = $events->pluck('work_order_no')->filter()->unique()->values();

        $map = [
            'order_item' => [],
            'work_order' => [],
        ];

        if ($orderItemNos->isNotEmpty()) {
            $query = WorkOrderSnapshot::query()
                ->where('aggregate_type', 'order_item')
                ->whereIn('aggregate_id', $orderItemNos);

            if (!empty($filters['has_alerts'])) {
                $query->where('alert_count', '>', 0);
            }

            $query->get()
                ->each(function (WorkOrderSnapshot $snapshot) use (&$map) {
                    $map['order_item'][$snapshot->aggregate_id] = $snapshot;
                });
        }

        if ($workOrderNos->isNotEmpty()) {
            $query = WorkOrderSnapshot::query()
                ->where('aggregate_type', 'work_order')
                ->whereIn('aggregate_id', $workOrderNos);

            if (!empty($filters['has_alerts'])) {
                $query->where('alert_count', '>', 0);
            }

            $query->get()
                ->each(function (WorkOrderSnapshot $snapshot) use (&$map) {
                    $map['work_order'][$snapshot->aggregate_id] = $snapshot;
                });
        }

        return $map;
    }

    private function resolveSnapshotForEvent(WorkOrderEvent $event, array $snapshotMap): ?WorkOrderSnapshot
    {
        if (intval($event->order_item_no ?? 0) > 0) {
            return $snapshotMap['order_item'][intval($event->order_item_no)] ?? null;
        }

        if (($event->aggregate_type ?? '') === 'work_order' && intval($event->aggregate_id ?? 0) > 0) {
            return $snapshotMap['work_order'][intval($event->aggregate_id)] ?? null;
        }

        return null;
    }
}
