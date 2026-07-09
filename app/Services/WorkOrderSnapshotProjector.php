<?php

namespace App\Services;

use App\Models\WorkOrderEvent;
use App\Models\WorkOrderSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkOrderSnapshotProjector
{
    private ?bool $hasSnapshotTable = null;
    private ?bool $hasEventsTable = null;
    private ?bool $hasTraceColumns = null;

    public function __construct(
        protected WorkOrderAnomalyDetector $anomalyDetector,
        protected BomService $bomService
    ) {}

    public function projectFromEvent(WorkOrderEvent $event): ?WorkOrderSnapshot
    {
        if (!$this->supportsSnapshots()) {
            return null;
        }

        if (intval($event->order_item_no ?? 0) > 0) {
            return $this->projectOrderItem(intval($event->order_item_no), $event);
        }

        if (($event->aggregate_type ?? '') === 'work_order' && intval($event->aggregate_id ?? 0) > 0) {
            return $this->projectWorkOrder(intval($event->aggregate_id), $event);
        }

        return null;
    }

    public function projectOrderItem(int $orderItemNo, ?WorkOrderEvent $event = null): ?WorkOrderSnapshot
    {
        if ($orderItemNo <= 0 || !$this->supportsSnapshots()) {
            return null;
        }

        $order = DB::table('tbSiparisSatir')->where('No', $orderItemNo)->first();
        if (!$order) {
            return null;
        }

        [$poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows, $completedTaskRows, $blockedReadyTaskRows] = $this->loadOrderProductionRows($order);
        [$holderType, $holderId, $holderName] = $this->resolveHolder($order, $poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows);
        [$currentStage, $nextExpectedAction] = $this->resolveStageAndNextAction($order, $poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows);
        $openTaskRowsForAlerts = $activeTaskRows
            ->concat($readyTaskRows)
            ->concat($assignedWaitingTaskRows);
        $alerts = $this->anomalyDetector->detectOrderItemAlerts($order, $poolRows, $openTaskRowsForAlerts, $completedTaskRows);
        $alerts = array_merge($alerts, $this->blockedReadyTaskAlerts($blockedReadyTaskRows));

        $snapshotPayload = [
            'order_item_no' => $orderItemNo,
            'order_no' => (string) ($order->SiparisNo ?? ''),
            'work_order_no' => intval($order->GorevNo ?? 0) > 0 ? intval($order->GorevNo) : null,
            'matched_product_no' => intval($order->EslesenUrunNo ?? 0),
            'matched_product_type' => (string) ($order->EslesenUrunTur ?? ''),
            'special_production_no' => intval($order->BagliOlduguOzelUretimNo ?? 0) > 0 ? intval($order->BagliOlduguOzelUretimNo) : null,
            'counts' => [
                'pool' => $poolRows->count(),
                'active_tasks' => $activeTaskRows->count(),
                'ready_tasks' => $readyTaskRows->count(),
                'assigned_waiting_tasks' => $assignedWaitingTaskRows->count(),
                'blocked_ready_tasks' => $blockedReadyTaskRows->count(),
                'completed_tasks' => $completedTaskRows->count(),
            ],
            'holder' => [
                'type' => $holderType,
                'id' => $holderId,
                'name' => $holderName,
            ],
            'alerts' => $alerts,
        ];

        return WorkOrderSnapshot::updateOrCreate(
            [
                'aggregate_type' => 'order_item',
                'aggregate_id' => $orderItemNo,
            ],
            [
                'order_item_no' => $orderItemNo,
                'order_no' => (string) ($order->SiparisNo ?? ''),
                'work_order_no' => intval($order->GorevNo ?? 0) > 0 ? intval($order->GorevNo) : null,
                'current_status' => (string) ($order->Durum ?? ''),
                'current_stage' => $currentStage,
                'current_holder_type' => $holderType,
                'current_holder_id' => $holderId,
                'current_holder_name' => $holderName,
                'linked_special_production_no' => intval($order->BagliOlduguOzelUretimNo ?? 0) > 0 ? intval($order->BagliOlduguOzelUretimNo) : null,
                'next_expected_action' => $nextExpectedAction,
                'last_event_id' => $event?->id,
                'last_changed_at' => $event?->happened_at ?? now(),
                'alert_count' => count($alerts),
                'snapshot' => $snapshotPayload,
            ]
        );
    }

    public function projectWorkOrder(int $workOrderNo, ?WorkOrderEvent $event = null): ?WorkOrderSnapshot
    {
        if ($workOrderNo <= 0 || !$this->supportsSnapshots()) {
            return null;
        }

        $workOrder = DB::table('tbGorevler')->where('No', $workOrderNo)->first();
        if (!$workOrder) {
            return null;
        }

        $departmentName = '';
        if (intval($workOrder->BolumAdiNo ?? 0) > 0) {
            $departmentName = trim((string) (DB::table('tbBolum')->where('No', intval($workOrder->BolumAdiNo))->value('BolumAdi') ?? ''));
        }

        $alerts = $this->anomalyDetector->detectWorkOrderAlerts($workOrder, $departmentName);

        return WorkOrderSnapshot::updateOrCreate(
            [
                'aggregate_type' => 'work_order',
                'aggregate_id' => $workOrderNo,
            ],
            [
                'order_item_no' => intval($workOrder->SiparisSatirNo ?? 0) > 0 ? intval($workOrder->SiparisSatirNo) : null,
                'order_no' => trim((string) ($workOrder->SiparisNo ?? '')) ?: null,
                'work_order_no' => $workOrderNo,
                'current_status' => 'IsEmriVerildi',
                'current_stage' => 'issued',
                'current_holder_type' => $departmentName !== '' ? 'department' : null,
                'current_holder_id' => intval($workOrder->BolumAdiNo ?? 0) > 0 ? (string) intval($workOrder->BolumAdiNo) : null,
                'current_holder_name' => $departmentName !== '' ? $departmentName : null,
                'linked_special_production_no' => null,
                'next_expected_action' => 'Havuz veya personel gorevi takibi yapilmali.',
                'last_event_id' => $event?->id,
                'last_changed_at' => $event?->happened_at ?? now(),
                'alert_count' => count($alerts),
                'snapshot' => [
                    'work_order_no' => $workOrderNo,
                    'department_name' => $departmentName,
                    'toplam_adet' => intval($workOrder->ToplamAdet ?? 0),
                    'alerts' => $alerts,
                ],
            ]
        );
    }

    private function loadOrderProductionRows(object $order): array
    {
        $orderItemNo = intval($order->No ?? 0);
        $matchedProductNo = intval($order->EslesenUrunNo ?? 0);
        $matchedProductType = (string) ($order->EslesenUrunTur ?? '');

        if ($this->hasTraceColumns()) {
            $poolRows = DB::table('tbBolumHavuz as h')
                ->leftJoin('tbBolum as b', 'h.BolumAdiNo', '=', 'b.No')
                ->select('h.*', DB::raw("IFNULL(b.BolumAdi, '') as bolum_adi"))
                ->where('h.SiparisSatirNo', $orderItemNo)
                ->get();

            $taskBaseQuery = DB::table('tbPersonelGorev as pg')
                ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
                ->select('pg.*', DB::raw("TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as personel_adi"))
                ->where('pg.SiparisSatirNo', $orderItemNo);

            $activeTaskRows = $this->applyActiveTaskFilter(clone $taskBaseQuery)
                ->get();

            [$readyTaskRows, $blockedReadyTaskRows] = $this->partitionReadyTaskRowsByAvailability(
                $this->applyReadyTaskFilter(clone $taskBaseQuery)->get()
            );

            $assignedWaitingTaskRows = $this->applyAssignedWaitingTaskFilter(clone $taskBaseQuery)
                ->get()
                ->concat($blockedReadyTaskRows)
                ->values();

            $completedTaskRows = DB::table('tbGorevler')
                ->where('SiparisSatirNo', $orderItemNo)
                ->where('ToplamAdet', '>', 0)
                ->whereNotNull('PersonelNo')
                ->where('PersonelNo', '>', 0)
                ->get();

            return [$poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows, $completedTaskRows, $blockedReadyTaskRows];
        }

        $poolQuery = DB::table('tbBolumHavuz as h')
            ->leftJoin('tbBolum as b', 'h.BolumAdiNo', '=', 'b.No')
            ->select('h.*', DB::raw("IFNULL(b.BolumAdi, '') as bolum_adi"));

        $taskQuery = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->select('pg.*', DB::raw("TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as personel_adi"));

        if ($matchedProductType === 'Nihai' && $matchedProductNo > 0) {
            $poolQuery->where('h.UrunIDNo', $matchedProductNo);
            $taskQuery->where('pg.UrunIDNo', $matchedProductNo);
        } elseif ($matchedProductType === 'Ara' && $matchedProductNo > 0) {
            $poolQuery->where('h.AraUrunAdiNo', $matchedProductNo);
            $taskQuery->where('pg.AraUrunAdiNo', $matchedProductNo);
        } else {
            $poolQuery->whereRaw('1 = 0');
            $taskQuery->whereRaw('1 = 0');
        }

        $activeTaskRows = $this->applyActiveTaskFilter(clone $taskQuery)
            ->get();

        [$readyTaskRows, $blockedReadyTaskRows] = $this->partitionReadyTaskRowsByAvailability(
            $this->applyReadyTaskFilter(clone $taskQuery)->get()
        );

        $assignedWaitingTaskRows = $this->applyAssignedWaitingTaskFilter(clone $taskQuery)
            ->get()
            ->concat($blockedReadyTaskRows)
            ->values();

        $completedTaskRows = Schema::hasTable('tbGorevler')
            ? DB::table('tbGorevler')
                ->where('ToplamAdet', '>', 0)
                ->whereNotNull('PersonelNo')
                ->where('PersonelNo', '>', 0)
                ->when($matchedProductType === 'Nihai' && $matchedProductNo > 0, function ($query) use ($matchedProductNo) {
                    $query->where('UrunIDNo', $matchedProductNo);
                })
                ->when($matchedProductType === 'Ara' && $matchedProductNo > 0, function ($query) use ($matchedProductNo) {
                    $query->where('AraUrunAdiNo', $matchedProductNo);
                })
                ->when(!in_array($matchedProductType, ['Nihai', 'Ara'], true) || $matchedProductNo <= 0, function ($query) {
                    $query->whereRaw('1 = 0');
                })
                ->get()
            : collect();

        return [$poolQuery->get(), $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows, $completedTaskRows, $blockedReadyTaskRows];
    }

    private function resolveHolder(object $order, $poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows): array
    {
        if (intval($order->BagliOlduguOzelUretimNo ?? 0) > 0) {
            $specialNo = intval($order->BagliOlduguOzelUretimNo);
            $specialOrderNo = trim((string) (DB::table('tbSiparisSatir')->where('No', $specialNo)->value('SiparisNo') ?? ''));

            return ['special_production', (string) $specialNo, $specialOrderNo !== '' ? 'Ozel Uretim ' . $specialOrderNo : 'Ozel Uretim #' . $specialNo];
        }

        if ($activeTaskRows->isNotEmpty()) {
            $task = $activeTaskRows->first();
            $personelAdi = trim((string) ($task->personel_adi ?? ''));
            $personelNo = intval($task->PersonelNo ?? 0);

            return ['personnel', $personelNo > 0 ? (string) $personelNo : null, $personelAdi !== '' ? $personelAdi : 'Personel gorevi'];
        }

        if ($readyTaskRows->isNotEmpty()) {
            $task = $readyTaskRows->first();
            $personelAdi = trim((string) ($task->personel_adi ?? ''));
            $personelNo = intval($task->PersonelNo ?? 0);

            return ['personnel', $personelNo > 0 ? (string) $personelNo : null, $personelAdi !== '' ? $personelAdi : 'Uretime hazir personel gorevi'];
        }

        if ($assignedWaitingTaskRows->isNotEmpty()) {
            $task = $assignedWaitingTaskRows->first();
            $personelAdi = trim((string) ($task->personel_adi ?? ''));
            $personelNo = intval($task->PersonelNo ?? 0);

            return ['personnel', $personelNo > 0 ? (string) $personelNo : null, $personelAdi !== '' ? $personelAdi : 'Bekleyen personel gorevi'];
        }

        if ($poolRows->isNotEmpty()) {
            $pool = $poolRows->first();
            $departmentName = trim((string) ($pool->bolum_adi ?? ''));
            $departmentNo = intval($pool->BolumAdiNo ?? 0);

            return ['department', $departmentNo > 0 ? (string) $departmentNo : null, $departmentName !== '' ? $departmentName : 'Havuz'];
        }

        $status = (string) ($order->Durum ?? '');
        if ($status === 'StokKarsilandi') {
            return ['stock', 'stock', 'Stok'];
        }

        if ($status === 'Pasif' || $status === 'PasifDevamEden') {
            return ['system', null, 'Kapali Kayit'];
        }

        return [null, null, null];
    }

    private function resolveStageAndNextAction(object $order, $poolRows, $activeTaskRows, $readyTaskRows, $assignedWaitingTaskRows): array
    {
        $status = (string) ($order->Durum ?? '');
        $stockAvailable = $this->resolveAvailableStockForOrder($order);
        $specialProductionAvailable = $this->hasEligibleSpecialProduction($order);

        $stage = match (true) {
            $status === 'PasifDevamEden' => 'cancelled_but_processing_tail',
            $status === 'Pasif' => 'cancelled',
            $status === 'StokKarsilandi' => 'fulfilled_from_stock',
            intval($order->BagliOlduguOzelUretimNo ?? 0) > 0 && $status === 'UretimdenKarsilaniyor' => 'linked_to_special_production',
            $activeTaskRows->isNotEmpty() => 'in_production',
            $readyTaskRows->isNotEmpty() => 'ready_for_production',
            $assignedWaitingTaskRows->isNotEmpty() => 'assigned_waiting',
            $poolRows->isNotEmpty() => 'in_pool',
            $status === 'IsEmriVerildi' => 'issued',
            default => 'waiting',
        };

        $availabilityIssue = $this->firstAvailabilityIssue($assignedWaitingTaskRows);

        $nextAction = match ($stage) {
            'waiting' => $stockAvailable >= intval($order->Adet ?? 0)
                ? 'Isterse stoktan karsilanabilir.'
                : ($specialProductionAvailable
                    ? 'Uygun ozel uretime baglanabilir veya is emri verilebilir.'
                    : 'Bu kayit icin is emri verilmesi bekleniyor.'),
            'issued' => 'Havuz veya personel gorevi takibi yapilmali.',
            'in_pool' => 'Personele gorev aktarimi veya personelin gorevi almasi bekleniyor.',
            'in_production' => 'Uretim girisi ve tamamlanma takibi yapilmali.',
            'ready_for_production' => 'Personelin gorevi uretime almasi bekleniyor.',
            'assigned_waiting' => $availabilityIssue ?: 'Atanmis gorev parca veya stok hazirligini bekliyor.',
            'linked_to_special_production' => 'Bagli ozel uretimin sonucu bekleniyor.',
            'fulfilled_from_stock' => 'Kayit stoktan kapanmis durumda; sevk veya son kontrol yapilabilir.',
            'cancelled_but_processing_tail' => 'Iptal edilmis ama sistemde kalan uretim kuyrugu kontrol edilmeli.',
            'cancelled' => 'Bu kayit kapali durumda; yeni islem gerekmiyor.',
            default => 'Sonraki adim hesaplanamadi.',
        };

        return [$stage, $nextAction];
    }

    private function partitionReadyTaskRowsByAvailability($readyTaskRows): array
    {
        $ready = collect();
        $blocked = collect();

        foreach ($readyTaskRows as $row) {
            $issue = $this->bomService->taskReadinessIssue($row);
            if ($issue !== null) {
                $row->availability_issue = $issue;
                $row->readiness_blocked = 1;
                $blocked->push($row);
                continue;
            }

            $ready->push($row);
        }

        return [$ready->values(), $blocked->values()];
    }

    private function firstAvailabilityIssue($rows): ?string
    {
        foreach ($rows as $row) {
            $issue = trim((string) ($row->availability_issue ?? ''));
            if ($issue !== '') {
                return $issue;
            }
        }

        return null;
    }

    private function blockedReadyTaskAlerts($rows): array
    {
        return collect($rows)
            ->map(function ($row) {
                $issue = trim((string) ($row->availability_issue ?? 'Alt parça stoğu yetersiz.'));

                return [
                    'code' => 'ready_task_has_missing_child_stock',
                    'severity' => 'high',
                    'message' => 'Personelde hazır görünen görev alt parça kontrolünden geçemiyor: ' . $issue,
                    'suggested_fix' => 'Eksik alt parçayı üretin/stoklayın veya görevin hazır-bekleyen adetlerini yeniden senkronlayın.',
                    'personnel_task_no' => intval($row->No ?? 0),
                    'component_no' => intval($row->AraUrunAdiNo ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function resolveAvailableStockForOrder(object $order): int
    {
        $araUrunNo = 0;
        $matchedProductNo = intval($order->EslesenUrunNo ?? 0);
        $matchedProductType = (string) ($order->EslesenUrunTur ?? '');

        if ($matchedProductType === 'Nihai' && $matchedProductNo > 0) {
            $urunId = trim((string) (DB::table('tbUrunler')->where('No', $matchedProductNo)->value('UrunID') ?? ''));
            if ($urunId !== '') {
                $araUrunNo = intval(DB::table('tbAraUrun')->where('AraUrunAdi', $urunId)->value('No') ?? 0);
            }
        } elseif ($matchedProductType === 'Ara' && $matchedProductNo > 0) {
            $araUrunNo = $matchedProductNo;
        }

        if ($araUrunNo <= 0) {
            return 0;
        }

        return intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->sum('Adet'));
    }

    private function hasEligibleSpecialProduction(object $order): bool
    {
        $matchedProductNo = intval($order->EslesenUrunNo ?? 0);
        $matchedProductType = (string) ($order->EslesenUrunTur ?? '');
        $quantity = intval($order->Adet ?? 0);

        if ($matchedProductNo <= 0 || $matchedProductType === '' || $quantity <= 0) {
            return false;
        }

        return DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
            ->where('sp.EslesenUrunNo', $matchedProductNo)
            ->where('sp.EslesenUrunTur', $matchedProductType)
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->where(function ($query) {
                $query->where('sp.GorevNo', '>', 0)
                    ->orWhereIn('sp.Durum', ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor']);
            })
            ->whereRaw(
                "(sp.Adet - IFNULL((SELECT SUM(ss.Adet) FROM tbSiparisSatir ss WHERE ss.BagliOlduguOzelUretimNo = sp.No AND ss.Aktif = 1 AND ss.Durum NOT IN ('Pasif', 'StokKarsilandi')), 0)) >= ?",
                [$quantity]
            )
            ->exists();
    }

    private function supportsSnapshots(): bool
    {
        if ($this->hasSnapshotTable === null) {
            $this->hasSnapshotTable = Schema::hasTable('work_order_snapshots');
        }

        return $this->hasSnapshotTable;
    }

    private function supportsEvents(): bool
    {
        if ($this->hasEventsTable === null) {
            $this->hasEventsTable = Schema::hasTable('work_order_events');
        }

        return $this->hasEventsTable;
    }

    private function hasTraceColumns(): bool
    {
        if ($this->hasTraceColumns === null) {
            $this->hasTraceColumns =
                Schema::hasTable('tbBolumHavuz') &&
                Schema::hasTable('tbPersonelGorev') &&
                Schema::hasTable('tbGorevler') &&
                Schema::hasColumn('tbBolumHavuz', 'SiparisSatirNo') &&
                Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo') &&
                Schema::hasColumn('tbGorevler', 'SiparisSatirNo');
        }

        return $this->hasTraceColumns;
    }

    private function openApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function productionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$normalized} IN ('hazir', 'ready'))";
    }

    private function activeProductionApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$normalized} IN ('0', 'false', 'hayir', 'hayır', 'no'))";
    }

    private function applyActiveTaskFilter($query)
    {
        return $query->where(function ($outerQuery) {
            $outerQuery->where(function ($query) {
                $query->where('pg.Adet', '>', 0)
                    ->whereRaw($this->openApprovalSql('pg.Onay'))
                    ->whereRaw('NOT ' . $this->productionReadyApprovalSql('pg.Onay'));
            })->orWhere(function ($query) {
                $query->where('pg.BekleyenAdet', '>', 0)
                    ->whereRaw($this->activeProductionApprovalSql('pg.Onay'));
            });
        });
    }

    private function applyReadyTaskFilter($query)
    {
        return $query->where(function ($outerQuery) {
            $outerQuery->where('pg.Adet', '>', 0)
                ->orWhere('pg.BekleyenAdet', '>', 0);
        })->whereRaw($this->productionReadyApprovalSql('pg.Onay'));
    }

    private function applyAssignedWaitingTaskFilter($query)
    {
        return $query->where('pg.BekleyenAdet', '>', 0)
            ->whereRaw($this->openApprovalSql('pg.Onay'))
            ->whereRaw('NOT ' . $this->activeProductionApprovalSql('pg.Onay'))
            ->whereRaw('NOT ' . $this->productionReadyApprovalSql('pg.Onay'));
    }
}
