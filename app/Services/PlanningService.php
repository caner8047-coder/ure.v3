<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\BomService;
use App\Services\PersonnelTaskMerger;
use App\Services\WorkOrderEventLogger;

class PlanningService
{
    private function pendingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function isApprovedValue(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'evet', 'yes'], true);
    }

    private function isActiveProductionValue(mixed $value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['0', 'false', 'hayir', 'hayır', 'no'], true);
    }

    private function serializeRecord(object|array|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true);
    }

    private function buildTaskEventBase(object $task): array
    {
        $orderItemNo = intval($task->SiparisSatirNo ?? 0);
        $orderNo = trim((string) ($task->SiparisNo ?? ''));
        $workOrderNo = null;

        if ($orderItemNo > 0 && Schema::hasTable('tbSiparisSatir')) {
            $orderRow = DB::table('tbSiparisSatir')
                ->where('No', $orderItemNo)
                ->select('SiparisNo', 'GorevNo')
                ->first();

            if ($orderRow) {
                if ($orderNo === '') {
                    $orderNo = trim((string) ($orderRow->SiparisNo ?? ''));
                }

                $resolvedWorkOrderNo = intval($orderRow->GorevNo ?? 0);
                $workOrderNo = $resolvedWorkOrderNo > 0 ? $resolvedWorkOrderNo : null;
            }
        }

        $taskNo = intval($task->No ?? 0);

        return [
            'aggregate_type' => $orderItemNo > 0 ? 'order_item' : 'personnel_task',
            'aggregate_id' => $orderItemNo > 0 ? $orderItemNo : max(1, $taskNo),
            'order_item_no' => $orderItemNo > 0 ? $orderItemNo : null,
            'order_no' => $orderNo !== '' ? $orderNo : null,
            'work_order_no' => $workOrderNo,
            'personnel_task_no' => $taskNo > 0 ? $taskNo : null,
        ];
    }

    public function mergeDuplicatePersonnelTasks(int $personelNo): void
    {
        try {
            app(PersonnelTaskMerger::class)->mergeOpenDuplicatesForPersonnel($personelNo);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function refreshPersonnelTaskReadiness(int $personelNo): void
    {
        if ($personelNo <= 0 || !Schema::hasTable('tbPersonelGorev')) {
            return;
        }

        try {
            $bomService = app(BomService::class);
            $tasks = DB::table('tbPersonelGorev')
                ->where('PersonelNo', $personelNo)
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->whereRaw($this->pendingApprovalSql('Onay'))
                ->limit(300)
                ->get();

            foreach ($tasks as $task) {
                $split = $bomService->personnelTaskReadySplit($task);
                $newReady = intval($split['ready']);
                $newWaiting = intval($split['waiting']);

                if ($newReady !== intval($task->Adet ?? 0) || $newWaiting !== intval($task->BekleyenAdet ?? 0)) {
                    DB::table('tbPersonelGorev')->where('No', $task->No)->update([
                        'Adet' => $newReady,
                        'BekleyenAdet' => $newWaiting,
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function logPlanningEvent(string $eventType, ?object $task, ?object $afterTask = null, array $attributes = []): void
    {
        $eventTask = $task ?? $afterTask;
        if (!$eventTask) {
            return;
        }

        app(WorkOrderEventLogger::class)->log(array_merge(
            $this->buildTaskEventBase($eventTask),
            [
                'event_type' => $eventType,
                'payload_before' => $this->serializeRecord($task),
                'payload_after' => $this->serializeRecord($afterTask),
            ],
            $attributes
        ));
    }

    private function taskQuantityToCheck(object $task): int
    {
        $readyQuantity = max(0, intval($task->Adet ?? 0));
        $waitingQuantity = max(0, intval($task->BekleyenAdet ?? 0));

        return $readyQuantity > 0 ? $readyQuantity : $waitingQuantity;
    }

    private function componentStockSnapshot(int $componentNo): array
    {
        if ($componentNo <= 0 || !Schema::hasTable('tbBolumAraStok')) {
            return ['total' => 0, 'free' => 0];
        }

        $rows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->get(['Adet', 'TamponMiktar']);

        return [
            'total' => intval($rows->sum(fn ($row) => max(0, intval($row->Adet ?? 0)))),
            'free' => intval($rows->sum(fn ($row) => max(0, min(intval($row->Adet ?? 0), intval($row->TamponMiktar ?? 0))))),
        ];
    }

    private function untracedHeldStockQuantity(int $componentNo): int
    {
        if ($componentNo <= 0 || !Schema::hasTable('tbBolumAraStok')) {
            return 0;
        }

        return intval(DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('Adet', '>', 0)
            ->get()
            ->sum(fn ($row) => max(0, intval($row->Adet ?? 0) - intval($row->TamponMiktar ?? 0))));
    }

    private function heldQuantityForTaskComponent(
        object $task,
        int $componentNo,
        int $requiredQuantity,
        BomService $bomService
    ): int {
        $traceHeldQuantity = $bomService->orderHeldStockQuantity(
            $componentNo,
            $bomService->traceContextFromRecord($task)
        );

        if ($traceHeldQuantity > 0) {
            return $traceHeldQuantity;
        }

        return min(max(0, $requiredQuantity), $this->untracedHeldStockQuantity($componentNo));
    }

    private function readinessIssueForTask(object $task, BomService $bomService): ?string
    {
        $quantityToCheck = $this->taskQuantityToCheck($task);
        if ($quantityToCheck <= 0) {
            return 'Parça/stok bekliyor.';
        }

        $requirements = $bomService->directChildRequirements(strval($task->AraUrunAdiNo ?? 0), $quantityToCheck);
        if (empty($requirements)) {
            return null;
        }

        foreach ($requirements as $componentNo => $requiredQuantity) {
            $componentNo = intval($componentNo);
            $requiredQuantity = max(0, intval($requiredQuantity));
            if ($componentNo <= 0 || $requiredQuantity <= 0) {
                continue;
            }

            $stock = $this->componentStockSnapshot($componentNo);
            $heldQuantity = $this->heldQuantityForTaskComponent($task, $componentNo, $requiredQuantity, $bomService);
            $heldUsable = min($requiredQuantity, max(0, $heldQuantity), $stock['total']);
            $freeUsable = min(max(0, $requiredQuantity - $heldUsable), $stock['free']);
            $usableQuantity = min($requiredQuantity, max(0, $heldUsable + $freeUsable), $stock['total']);

            if ($requiredQuantity > $usableQuantity) {
                $componentName = DB::table('tbAraUrun')->where('No', $componentNo)->value('AraUrunAdi');

                return trim((string) ($componentName ?: 'Alt parça')) . ' için yeterli alt parça stoğu yok.';
            }
        }

        return null;
    }

    private function supplierStatusLabel(object $task): string
    {
        $ready = max(0, intval($task->Adet ?? 0));
        $waiting = max(0, intval($task->BekleyenAdet ?? 0));
        $approval = strtolower(trim((string) ($task->Onay ?? '')));

        if ($ready <= 0 && $waiting > 0) {
            return 'Alt parça bekliyor';
        }

        if (in_array($approval, ['hazir', 'ready'], true)) {
            return 'Üretime hazır';
        }

        if (!$this->isApprovedValue($approval)) {
            return 'Üretimde';
        }

        return 'Açık görev';
    }

    private function supplierWaitReason(object $task, BomService $bomService): string
    {
        $issue = $this->readinessIssueForTask($task, $bomService);
        if ($issue !== null) {
            return $issue;
        }

        return match ($this->supplierStatusLabel($task)) {
            'Üretime hazır' => 'Personelin görevi kabul edip üretime alması bekleniyor.',
            'Üretimde' => 'Personelin üretim girişi yapması bekleniyor.',
            default => 'Görev açık.',
        };
    }

    public function enrichPlanningTaskAvailability($tasks)
    {
        $bomService = app(BomService::class);

        return collect($tasks)->map(function ($task) use ($bomService) {
            $ready = max(0, intval($task->Adet ?? 0));
            $waiting = max(0, intval($task->BekleyenAdet ?? 0));
            $active = $this->isActiveProductionValue($task->Onay ?? '');
            $issue = $active ? null : $this->readinessIssueForTask($task, $bomService);

            $task->HasShortage = $issue !== null ? 1 : 0;
            $task->BeklemeNedeni = $issue ?? '';
            $task->ButonMetni = $issue !== null
                ? (str_contains($issue, 'alt parça') ? 'Alt parça stoğu yetersiz' : $issue)
                : '';
            $task->Baslatilabilir = (!$active && $issue === null && $ready > 0) ? 1 : 0;

            if ($active) {
                $task->Durum = 'Üretimde';
            } elseif ($issue !== null) {
                $task->Durum = 'Alt parça bekliyor';
            } elseif ($ready > 0) {
                $task->Durum = $waiting > 0 ? 'Kısmi kabul edilebilir' : 'Kabul edilebilir';
            } elseif ($waiting > 0) {
                $task->Durum = 'Alt parça bekliyor';
            } else {
                $task->Durum = 'Plan bekliyor';
            }

            return $task;
        })->values();
    }

    private function dependencyShortagesForTask(object $task, BomService $bomService): array
    {
        $quantityToCheck = $this->taskQuantityToCheck($task);
        if ($quantityToCheck <= 0) {
            return [];
        }

        $requirements = $bomService->directChildRequirements(strval($task->AraUrunAdiNo ?? 0), $quantityToCheck);
        if (empty($requirements)) {
            return [];
        }

        $traceContext = $bomService->traceContextFromRecord($task);
        $shortages = [];

        foreach ($requirements as $componentNo => $requiredQuantity) {
            $componentNo = intval($componentNo);
            $requiredQuantity = max(0, intval($requiredQuantity));
            if ($componentNo <= 0 || $requiredQuantity <= 0) {
                continue;
            }

            $component = DB::table('tbAraUrun as au')
                ->leftJoin('tbBolum as b', 'au.BolumAdiNo', '=', 'b.No')
                ->where('au.No', $componentNo)
                ->select('au.No', 'au.AraUrunAdi', 'au.BolumAdiNo', DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"))
                ->first();

            $stock = $this->componentStockSnapshot($componentNo);
            $heldQuantity = $this->heldQuantityForTaskComponent($task, $componentNo, $requiredQuantity, $bomService);
            $heldUsable = min($requiredQuantity, max(0, $heldQuantity), $stock['total']);
            $freeUsable = min(max(0, $requiredQuantity - $heldUsable), $stock['free']);
            $usableQuantity = min($requiredQuantity, max(0, $heldUsable + $freeUsable), $stock['total']);
            $missingQuantity = max(0, $requiredQuantity - $usableQuantity);

            if ($missingQuantity <= 0) {
                continue;
            }

            $shortages[] = [
                'component_no' => $componentNo,
                'component_name' => trim((string) ($component->AraUrunAdi ?? 'Alt parça')),
                'department_no' => intval($component->BolumAdiNo ?? 0) ?: null,
                'department_name' => trim((string) ($component->BolumAdi ?? '')),
                'required_quantity' => $requiredQuantity,
                'stock_quantity' => $stock['total'],
                'free_stock_quantity' => $stock['free'],
                'held_quantity' => max(0, intval($heldQuantity)),
                'usable_quantity' => $usableQuantity,
                'missing_quantity' => $missingQuantity,
                'suppliers' => $this->dependencySuppliersForComponent($task, $componentNo, $missingQuantity, $traceContext, $bomService),
                'related_suppliers' => $this->dependencyRelatedSuppliersForComponent($task, $componentNo, $missingQuantity, $traceContext, $bomService),
                'pool' => $this->dependencyPoolRowsForComponent($componentNo, $traceContext, $bomService),
            ];
        }

        return $shortages;
    }

    private function dependencySuppliersForComponent(
        object $waitingTask,
        int $componentNo,
        int $missingQuantity,
        array $traceContext,
        BomService $bomService
    ): array {
        if ($componentNo <= 0 || !Schema::hasTable('tbPersonelGorev')) {
            return [];
        }

        $query = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.AraUrunAdiNo', $componentNo)
            ->where(function ($query) {
                $query->where('pg.Adet', '>', 0)
                    ->orWhere('pg.BekleyenAdet', '>', 0);
            })
            ->orderBy('pg.GorevBaslamaTarihi')
            ->orderBy('pg.No');

        $bomService->scopeQueryToTrace($query, $traceContext, true, 'pg');

        $supplierColumns = [
            'pg.No',
            'pg.PersonelNo',
            'pg.GorevBaslamaTarihi',
            'pg.Adet',
            'pg.BekleyenAdet',
            'pg.Onay',
            'pg.AraUrunAdiNo',
            'pg.BolumAdiNo',
            'pg.UrunIDNo',
            DB::raw("IFNULL(p.Ad, '') as PersonelAd"),
            DB::raw("IFNULL(p.Soyad, '') as PersonelSoyad"),
            DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
        ];
        $supplierColumns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')
            ? 'pg.SiparisSatirNo'
            : DB::raw('NULL as SiparisSatirNo');
        $supplierColumns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisNo')
            ? 'pg.SiparisNo'
            : DB::raw('NULL as SiparisNo');

        $rows = $query
            ->limit(20)
            ->get($supplierColumns);

        $remainingNeed = max(0, $missingQuantity);

        return $rows->map(function ($row) use (&$remainingNeed, $bomService) {
            $openQuantity = max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
            $expectedQuantity = $remainingNeed > 0 ? min($remainingNeed, $openQuantity) : 0;
            $remainingNeed = max(0, $remainingNeed - $expectedQuantity);
            $personelNo = intval($row->PersonelNo ?? 0);
            $fullName = trim((string) ($row->PersonelAd ?? '') . ' ' . (string) ($row->PersonelSoyad ?? ''));

            return [
                'task_no' => intval($row->No ?? 0),
                'personnel_no' => $personelNo,
                'personnel_name' => $fullName !== '' ? $fullName : ('Personel #' . $personelNo),
                'department_name' => trim((string) ($row->BolumAdi ?? '')),
                'ready_quantity' => max(0, intval($row->Adet ?? 0)),
                'waiting_quantity' => max(0, intval($row->BekleyenAdet ?? 0)),
                'open_quantity' => $openQuantity,
                'expected_quantity' => $expectedQuantity,
                'status' => $this->supplierStatusLabel($row),
                'wait_reason' => $this->supplierWaitReason($row, $bomService),
                'started_at' => trim((string) ($row->GorevBaslamaTarihi ?? '')),
                'can_notify' => $personelNo > 0,
            ];
        })->values()->all();
    }

    private function dependencyRelatedSuppliersForComponent(
        object $waitingTask,
        int $componentNo,
        int $missingQuantity,
        array $traceContext,
        BomService $bomService
    ): array {
        if ($componentNo <= 0 || !Schema::hasTable('tbPersonelGorev')) {
            return [];
        }

        $query = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.AraUrunAdiNo', $componentNo)
            ->where('pg.No', '!=', intval($waitingTask->No ?? 0))
            ->where(function ($query) {
                $query->where('pg.Adet', '>', 0)
                    ->orWhere('pg.BekleyenAdet', '>', 0);
            })
            ->orderByRaw('CASE WHEN COALESCE(pg.Adet, 0) > 0 THEN 0 ELSE 1 END')
            ->orderBy('pg.GorevBaslamaTarihi')
            ->orderBy('pg.No');

        $trace = $bomService->normalizeTraceContext($traceContext);
        if ($trace['siparisSatirNo'] > 0 && Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')) {
            $query->where(function ($query) use ($trace) {
                $query->whereNull('pg.SiparisSatirNo')
                    ->orWhere('pg.SiparisSatirNo', '<>', $trace['siparisSatirNo']);
            });
        } elseif ($trace['siparisNo'] !== '' && Schema::hasColumn('tbPersonelGorev', 'SiparisNo')) {
            $query->where(function ($query) use ($trace) {
                $query->whereNull('pg.SiparisNo')
                    ->orWhere('pg.SiparisNo', '<>', $trace['siparisNo']);
            });
        }

        $supplierColumns = [
            'pg.No',
            'pg.PersonelNo',
            'pg.GorevBaslamaTarihi',
            'pg.Adet',
            'pg.BekleyenAdet',
            'pg.Onay',
            'pg.AraUrunAdiNo',
            'pg.BolumAdiNo',
            'pg.UrunIDNo',
            DB::raw("IFNULL(p.Ad, '') as PersonelAd"),
            DB::raw("IFNULL(p.Soyad, '') as PersonelSoyad"),
            DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
        ];
        $supplierColumns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')
            ? 'pg.SiparisSatirNo'
            : DB::raw('NULL as SiparisSatirNo');
        $supplierColumns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisNo')
            ? 'pg.SiparisNo'
            : DB::raw('NULL as SiparisNo');

        $remainingNeed = max(0, $missingQuantity);

        return $query
            ->limit(10)
            ->get($supplierColumns)
            ->map(function ($row) use (&$remainingNeed, $bomService) {
                $openQuantity = max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
                $expectedQuantity = $remainingNeed > 0 ? min($remainingNeed, $openQuantity) : 0;
                $remainingNeed = max(0, $remainingNeed - $expectedQuantity);
                $personelNo = intval($row->PersonelNo ?? 0);
                $fullName = trim((string) ($row->PersonelAd ?? '') . ' ' . (string) ($row->PersonelSoyad ?? ''));
                $orderNo = trim((string) ($row->SiparisNo ?? ''));
                $orderItemNo = intval($row->SiparisSatirNo ?? 0);

                return [
                    'task_no' => intval($row->No ?? 0),
                    'personnel_no' => $personelNo,
                    'personnel_name' => $fullName !== '' ? $fullName : ('Personel #' . $personelNo),
                    'department_name' => trim((string) ($row->BolumAdi ?? '')),
                    'ready_quantity' => max(0, intval($row->Adet ?? 0)),
                    'waiting_quantity' => max(0, intval($row->BekleyenAdet ?? 0)),
                    'open_quantity' => $openQuantity,
                    'expected_quantity' => $expectedQuantity,
                    'status' => $this->supplierStatusLabel($row),
                    'wait_reason' => $this->supplierWaitReason($row, $bomService),
                    'started_at' => trim((string) ($row->GorevBaslamaTarihi ?? '')),
                    'order_item_no' => $orderItemNo ?: null,
                    'order_no' => $orderNo !== '' ? $orderNo : null,
                    'relation_label' => $orderNo !== ''
                        ? ('Başka sipariş: ' . $orderNo)
                        : ($orderItemNo > 0 ? ('Başka satır #' . $orderItemNo) : 'İz bilgisi yok'),
                    'can_notify' => false,
                ];
            })
            ->values()
            ->all();
    }

    private function dependencyPoolRowsForComponent(int $componentNo, array $traceContext, BomService $bomService): array
    {
        if ($componentNo <= 0 || !Schema::hasTable('tbBolumHavuz')) {
            return [];
        }

        $query = DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbBolum as b', 'bh.BolumAdiNo', '=', 'b.No')
            ->where('bh.AraUrunAdiNo', $componentNo)
            ->where('bh.ToplamAdet', '>', 0)
            ->orderBy('bh.GorevBaslangicTarihi')
            ->orderBy('bh.No');

        $bomService->scopeQueryToTrace($query, $traceContext, true, 'bh');

        return $query
            ->limit(10)
            ->get([
                'bh.No',
                'bh.Adet',
                'bh.ToplamAdet',
                'bh.GorevBaslangicTarihi',
                DB::raw(Schema::hasColumn('tbBolumHavuz', 'GorevBaslangicSaati') ? "IFNULL(bh.GorevBaslangicSaati, '') as GorevBaslangicSaati" : "'' as GorevBaslangicSaati"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
            ])
            ->map(fn ($row) => [
                'pool_no' => intval($row->No ?? 0),
                'ready_quantity' => max(0, intval($row->Adet ?? 0)),
                'open_quantity' => max(0, intval($row->ToplamAdet ?? 0)),
                'department_name' => trim((string) ($row->BolumAdi ?? '')),
                'scheduled_at' => trim((string) ($row->GorevBaslangicTarihi ?? '') . ' ' . (string) ($row->GorevBaslangicSaati ?? '')),
                'status' => 'Havuzda, personel ataması bekliyor',
            ])
            ->values()
            ->all();
    }

    public function dependencyInfo(int $id): ?array
    {
        $bomService = app(BomService::class);
        $personnelNameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, '')) as PersonelAdi"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as PersonelAdi";

        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->where('pg.No', $id)
            ->select(
                'pg.*',
                DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw($personnelNameSql)
            )
            ->first();

        if (!$task) {
            return null;
        }

        $this->refreshPersonnelTaskReadiness(intval($task->PersonelNo ?? 0));
        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->where('pg.No', $id)
            ->select(
                'pg.*',
                DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw($personnelNameSql)
            )
            ->first();

        $shortages = $this->dependencyShortagesForTask($task, $bomService);

        return [
            'task' => [
                'no' => intval($task->No ?? 0),
                'personnel_no' => intval($task->PersonelNo ?? 0),
                'personnel_name' => trim((string) ($task->PersonelAdi ?? '')),
                'component_no' => intval($task->AraUrunAdiNo ?? 0),
                'component_name' => trim((string) ($task->AraUrunAdi ?? '')),
                'department_name' => trim((string) ($task->BolumAdi ?? '')),
                'ready_quantity' => max(0, intval($task->Adet ?? 0)),
                'waiting_quantity' => max(0, intval($task->BekleyenAdet ?? 0)),
                'quantity_checked' => $this->taskQuantityToCheck($task),
            ],
            'has_shortage' => !empty($shortages),
            'shortages' => $shortages,
        ];
    }

    public function notifyDependency(int $id, int $componentNo, int $supplierTaskNo, ?object $user): array
    {
        if ($componentNo <= 0 || $supplierTaskNo <= 0) {
            return ['success' => false, 'message' => 'Bildirim için eksik bağımlılık bilgisi var.', 'code' => 422];
        }

        if (!Schema::hasTable('tbIletisim')) {
            return ['success' => false, 'message' => 'Mesaj tablosu bulunamadı.', 'code' => 500];
        }

        $bomService = app(BomService::class);
        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->where('pg.No', $id)
            ->select('pg.*', DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"))
            ->first();

        if (!$task) {
            return ['success' => false, 'message' => 'Görev bulunamadı.', 'code' => 404];
        }

        $selectedShortage = null;
        $selectedSupplier = null;
        foreach ($this->dependencyShortagesForTask($task, $bomService) as $shortage) {
            if (intval($shortage['component_no'] ?? 0) !== $componentNo) {
                continue;
            }

            foreach (($shortage['suppliers'] ?? []) as $supplier) {
                if (intval($supplier['task_no'] ?? 0) === $supplierTaskNo) {
                    $selectedShortage = $shortage;
                    $selectedSupplier = $supplier;
                    break 2;
                }
            }
        }

        if (!$selectedShortage || !$selectedSupplier) {
            return ['success' => false, 'message' => 'Beklenen personel görevi bulunamadı.', 'code' => 404];
        }

        $targetPersonnelNo = intval($selectedSupplier['personnel_no'] ?? 0);
        if ($targetPersonnelNo <= 0) {
            return ['success' => false, 'message' => 'Bu bağımlılık için bildirilecek personel bulunamadı.', 'code' => 422];
        }

        $trackingCode = 'BG-' . intval($task->No ?? 0) . '-' . $supplierTaskNo . '-' . $componentNo;
        $existing = DB::table('tbIletisim')
            ->where('PersonelNo', $targetPersonnelNo)
            ->where('Mesaj', 'like', '%' . $trackingCode . '%')
            ->orderByDesc('MesajNo')
            ->first();

        if ($existing) {
            return [
                'success' => true,
                'message' => 'Bu personele bu bekleme bildirimi daha önce gönderilmiş.',
                'already_sent' => true,
            ];
        }

        $target = DB::table('tbPersonel')->where('PersonelNo', $targetPersonnelNo)->first();
        $adminName = $user ? trim((string) ($user->name ?? 'Yönetici') . ' ' . (string) ($user->surname ?? '')) : 'Yönetici';
        $adminName = $adminName !== '' ? $adminName : 'Yönetici';
        $waitingTaskName = trim((string) ($task->AraUrunAdi ?? 'Görev'));
        $componentName = trim((string) ($selectedShortage['component_name'] ?? 'Alt parça'));
        $missingQuantity = intval($selectedShortage['missing_quantity'] ?? 0);
        $expectedQuantity = intval($selectedSupplier['expected_quantity'] ?? 0);
        $displayQuantity = $expectedQuantity > 0 ? $expectedQuantity : $missingQuantity;

        $message = implode("\n", array_filter([
            'Üretim Akışı Bildirimi',
            "Bekleyen görev: {$waitingTaskName} (#" . intval($task->No ?? 0) . ")",
            "Beklenen parça: {$componentName}",
            "Beklenen adet: {$displayQuantity}",
            "Aksiyon: Üretim durumunu kontrol edip stoğa giriş yapınız.",
            "Takip: {$trackingCode}",
        ]));

        $insert = [
            'PersonelNo' => $targetPersonnelNo,
            'BolumAdiNo' => null,
            'Mesaj' => $message,
            'Tarih' => now()->format('d/m/Y'),
        ];

        if (Schema::hasColumn('tbIletisim', 'Saat')) {
            $insert['Saat'] = now()->format('H:i');
        }
        if (Schema::hasColumn('tbIletisim', 'Mail')) {
            $insert['Mail'] = $target->Mail ?? null;
        }
        if (Schema::hasColumn('tbIletisim', 'AdSoyad')) {
            $insert['AdSoyad'] = $adminName;
        }
        if (Schema::hasColumn('tbIletisim', 'Okundu')) {
            $insert['Okundu'] = 0;
        }

        DB::table('tbIletisim')->insert(array_intersect_key($insert, array_flip(Schema::getColumnListing('tbIletisim'))));

        $this->logPlanningEvent('dependency_notification_sent_by_admin', $task, null, [
            'next_step_human' => 'Alt parcayi uretecek personele yonetici tarafindan bildirim gonderildi.',
            'context' => [
                'component_no' => $componentNo,
                'component_name' => $componentName,
                'missing_quantity' => $missingQuantity,
                'supplier_task_no' => $supplierTaskNo,
                'target_personnel_no' => $targetPersonnelNo,
                'tracking_code' => $trackingCode,
            ],
        ]);

        return [
            'success' => true,
            'message' => ($selectedSupplier['personnel_name'] ?? 'Personele') . ' bildirim gönderildi.',
            'already_sent' => false,
        ];
    }

    public function getPersonnelTasks(int $personnelNo): array
    {
        $this->mergeDuplicatePersonnelTasks($personnelNo);
        $this->refreshPersonnelTaskReadiness($personnelNo);

        $query = DB::table('tbPersonelGorev as pg')
            ->join('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->join('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.PersonelNo', $personnelNo)
            ->where(function ($query) {
                $query->where('pg.Adet', '>', 0)
                    ->orWhere('pg.BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('pg.BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->pendingApprovalSql('pg.Onay'));
            })
            ->select(
                'pg.No',
                'pg.PersonelNo',
                'pg.UrunIDNo',
                'pg.BolumAdiNo',
                'pg.GorevBaslamaTarihi',
                'au.AraUrunAdi',
                'au.No as AraUrunAdiNo',
                'b.BolumAdi',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.Onay',
                'au.Yol'
            );

        if (Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')) {
            $query->addSelect('pg.SiparisSatirNo');
        }
        if (Schema::hasColumn('tbPersonelGorev', 'SiparisNo')) {
            $query->addSelect('pg.SiparisNo');
        }

        $tasks = $query
            ->orderByRaw("STR_TO_DATE(SUBSTRING(pg.GorevBaslamaTarihi, 1, 10), '%d/%m/%Y') ASC")
            ->get();

        return $this->enrichPlanningTaskAvailability($tasks)->all();
    }

    public function getDepartmentPersonnelTasks(int $departmentId): array
    {
        $nameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, '')) as PersonelAdi"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as PersonelAdi";

        $query = DB::table('tbPersonelGorev as pg')
            ->join('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->join('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->join('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.BolumAdiNo', $departmentId)
            ->where(function ($q) {
                $q->where('pg.Adet', '>', 0)
                  ->orWhere('pg.BekleyenAdet', '>', 0);
            })
            ->where(function ($q) {
                $q->where('pg.BekleyenAdet', '>', 0)
                  ->orWhereRaw($this->pendingApprovalSql('pg.Onay'));
            })
            ->select(
                'pg.No',
                'pg.PersonelNo',
                'pg.UrunIDNo',
                'pg.BolumAdiNo',
                DB::raw($nameSql),
                'pg.GorevBaslamaTarihi',
                'au.AraUrunAdi',
                'au.No as AraUrunAdiNo',
                'b.BolumAdi',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.Onay',
                'au.Yol'
            );

        if (Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')) {
            $query->addSelect('pg.SiparisSatirNo');
        }
        if (Schema::hasColumn('tbPersonelGorev', 'SiparisNo')) {
            $query->addSelect('pg.SiparisNo');
        }

        $tasks = $query
            ->orderByRaw("STR_TO_DATE(SUBSTRING(pg.GorevBaslamaTarihi, 1, 10), '%d/%m/%Y') ASC")
            ->get();

        return $this->enrichPlanningTaskAvailability($tasks)->all();
    }

    public function getPoolTasks(int $departmentId): array
    {
        if (!Schema::hasTable('tbBolumHavuz')) {
            return [];
        }

        $query = DB::table('tbBolumHavuz as bh')
            ->leftJoin('tbAraUrun as au', 'bh.AraUrunAdiNo', '=', 'au.No')
            ->where('bh.BolumAdiNo', $departmentId)
            ->where('bh.Adet', '>', 0)
            ->select(
                'bh.No',
                'bh.AraUrunAdiNo',
                'au.AraUrunAdi',
                'bh.BolumAdiNo',
                'bh.Adet',
                'bh.ToplamAdet'
            );

        if (Schema::hasColumn('tbBolumHavuz', 'SiparisSatirNo')) {
            $query->addSelect('bh.SiparisSatirNo');
        }
        if (Schema::hasColumn('tbBolumHavuz', 'SiparisNo')) {
            $query->addSelect('bh.SiparisNo');
        }
        if (Schema::hasColumn('tbBolumHavuz', 'OzelUretimNo')) {
            $query->addSelect('bh.OzelUretimNo');
        }

        return $query->orderBy('bh.No', 'asc')->get()->all();
    }

    public function getPersonnelList(): array
    {
        $nameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, '')) as PersonelAdi"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as PersonelAdi";

        return DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->leftJoin(DB::raw('(SELECT PersonelNo, COUNT(*) as gorev_sayisi FROM tbPersonelGorev WHERE (Adet > 0 OR BekleyenAdet > 0) GROUP BY PersonelNo) as gs'), 'p.PersonelNo', '=', 'gs.PersonelNo')
            ->where('p.BolumAdiNo', '!=', 0)
            ->select(
                'p.PersonelNo',
                'p.BolumAdiNo',
                DB::raw($nameSql),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw("IFNULL(gs.gorev_sayisi, 0) as aktif_gorev_sayisi")
            )
            ->orderBy('p.Ad')
            ->get()->all();
    }

    public function setTaskQuantity(int $taskId, int $targetQuantity): array
    {
        return DB::transaction(function () use ($taskId, $targetQuantity) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['success' => false, 'message' => 'Görev bulunamadı.', 'code' => 404];
            }

            if ($this->isApprovedValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Tamamlanmış görev değiştirilemez.', 'code' => 422];
            }

            if ($this->isActiveProductionValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde. Adet değişikliği yapabilmek için önce personelin üretimi tamamlaması veya durdurması gerekiyor.', 'code' => 422];
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);
            $araUrunAdiNo = intval($task->AraUrunAdiNo ?? 0);
            $bolumAdiNo = intval($task->BolumAdiNo ?? 0);

            $currentReady = max(0, intval($task->Adet ?? 0));
            $currentWaiting = max(0, intval($task->BekleyenAdet ?? 0));
            $currentTotal = $currentReady + $currentWaiting;
            $diff = $targetQuantity - $currentTotal;

            if ($diff === 0) {
                return ['success' => true, 'message' => 'Adet zaten bu değerde.'];
            }

            if ($diff > 0) {
                $poolQuery = DB::table('tbBolumHavuz')
                    ->where('AraUrunAdiNo', $araUrunAdiNo)
                    ->where('BolumAdiNo', $bolumAdiNo)
                    ->where('Adet', '>', 0)
                    ->orderBy('No');
                $bomService->scopeQueryToTrace($poolQuery, $traceContext, true);
                $pool = $poolQuery->lockForUpdate()->first();

                if (!$pool) {
                    return ['success' => false, 'message' => 'Havuzda yeterli adet yok.'];
                }

                $poolAdet = max(0, intval($pool->Adet ?? 0));
                $poolToplam = max(0, intval($pool->ToplamAdet ?? 0));
                $canTake = min($diff, $poolAdet);

                if ($canTake < $diff) {
                    return [
                        'success' => false,
                        'message' => "Havuzda sadece {$canTake} adet mevcut (talep: {$diff}).",
                    ];
                }

                $newPoolToplam = $poolToplam - $diff;
                $newPoolAdet = max(0, $poolAdet - $diff);

                if ($newPoolToplam <= 0) {
                    DB::table('tbBolumHavuz')->where('No', $pool->No)->delete();
                } else {
                    DB::table('tbBolumHavuz')->where('No', $pool->No)->update([
                        'ToplamAdet' => $newPoolToplam,
                        'Adet' => $newPoolAdet,
                    ]);
                }
            } else {
                $returnCount = abs($diff);
                if ($returnCount > $currentTotal) {
                    $returnCount = $currentTotal;
                }

                $poolQuery = DB::table('tbBolumHavuz')
                    ->where('AraUrunAdiNo', $araUrunAdiNo)
                    ->where('BolumAdiNo', $bolumAdiNo)
                    ->orderBy('No');
                $bomService->scopeQueryToTrace($poolQuery, $traceContext, true);
                $existingPool = $poolQuery->lockForUpdate()->first();

                if ($existingPool) {
                    DB::table('tbBolumHavuz')->where('No', $existingPool->No)->update([
                        'ToplamAdet' => intval($existingPool->ToplamAdet ?? 0) + $returnCount,
                        'Adet' => intval($existingPool->Adet ?? 0) + $returnCount,
                    ]);
                } else {
                    $insertData = [
                        'AraUrunAdiNo' => $araUrunAdiNo,
                        'BolumAdiNo' => $bolumAdiNo,
                        'ToplamAdet' => $returnCount,
                        'Adet' => $returnCount,
                    ];

                    if (Schema::hasColumn('tbBolumHavuz', 'SiparisSatirNo') && isset($task->SiparisSatirNo)) {
                        $insertData['SiparisSatirNo'] = intval($task->SiparisSatirNo ?? 0);
                    }
                    if (Schema::hasColumn('tbBolumHavuz', 'SiparisNo') && isset($task->SiparisNo)) {
                        $insertData['SiparisNo'] = $task->SiparisNo;
                    }

                    DB::table('tbBolumHavuz')->insert($insertData);
                }
            }

            $split = $bomService->personnelTaskReadySplit($task, max(0, $targetQuantity));
            $newReady = intval($split['ready']);
            $newWaiting = intval($split['waiting']);

            if ($targetQuantity <= 0) {
                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'Adet' => $newReady,
                    'BekleyenAdet' => $newWaiting,
                    'Onay' => 'hazir',
                ]);
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            $updatedTask = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            $this->logPlanningEvent('planning_quantity_set', $task, $updatedTask, [
                'next_step_human' => 'Adet degistirildi; uretimdeki gorev guncellenmeli.',
                'context' => [
                    'previous_total' => $currentTotal,
                    'new_total' => $targetQuantity,
                    'diff' => $diff,
                ],
            ]);

            return [
                'success' => true,
                'message' => "Adet {$currentTotal} → {$targetQuantity} olarak güncellendi.",
            ];
        });
    }

    public function assignFromPool(int $poolId, int $personnelNo, string $targetDate): array
    {
        return DB::transaction(function () use ($poolId, $personnelNo, $targetDate) {
            $pool = DB::table('tbBolumHavuz')->where('No', $poolId)->lockForUpdate()->first();
            if (!$pool) {
                return ['success' => false, 'message' => 'Havuz görevi bulunamadı (önceden atanmış olabilir).', 'code' => 404];
            }

            $personnel = DB::table('tbPersonel')->where('PersonelNo', $personnelNo)->first();
            if (!$personnel) {
                return ['success' => false, 'message' => 'Personel bulunamadı.', 'code' => 404];
            }

            $bomService = app(BomService::class);
            $assignedQty = intval($pool->Adet ?? 0);
            if ($assignedQty <= 0) {
                return ['success' => false, 'message' => 'Bu havuz kaydında atanabilir adet yok.', 'code' => 422];
            }

            $insertData = [
                'UrunIDNo' => intval($pool->UrunIDNo ?? 0),
                'PersonelNo' => $personnelNo,
                'AraUrunAdiNo' => $pool->AraUrunAdiNo,
                'BolumAdiNo' => $pool->BolumAdiNo,
                'Adet' => 0,
                'BekleyenAdet' => 0,
                'GorevBaslamaTarihi' => date('d/m/Y', strtotime($targetDate)) . ' 00:00',
                'Onay' => 'hazir',
            ];

            if (Schema::hasColumn('tbPersonelGorev', 'GorevDurumu')) {
                $insertData['GorevDurumu'] = 'Aktif';
            }

            if (Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo') && isset($pool->SiparisSatirNo)) {
                $insertData['SiparisSatirNo'] = intval($pool->SiparisSatirNo);
            }
            if (Schema::hasColumn('tbPersonelGorev', 'SiparisNo') && isset($pool->SiparisNo)) {
                $insertData['SiparisNo'] = $pool->SiparisNo;
            }
            if (Schema::hasColumn('tbPersonelGorev', 'OzelUretimNo') && isset($pool->OzelUretimNo)) {
                $insertData['OzelUretimNo'] = $pool->OzelUretimNo;
            }

            $dummyTask = (object) $insertData;
            $split = $bomService->personnelTaskReadySplit($dummyTask, $assignedQty);
            $insertData['Adet'] = intval($split['ready']);
            $insertData['BekleyenAdet'] = intval($split['waiting']);

            $newTaskId = DB::table('tbPersonelGorev')->insertGetId($insertData);
            $newTask = DB::table('tbPersonelGorev')->where('No', $newTaskId)->first();

            $newToplam = max(0, intval($pool->ToplamAdet ?? 0) - $assignedQty);
            if ($newToplam <= 0) {
                DB::table('tbBolumHavuz')->where('No', $poolId)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $poolId)->update([
                    'ToplamAdet' => $newToplam,
                    'Adet' => 0,
                ]);
            }

            $this->mergeDuplicatePersonnelTasks($personnelNo);
            $bomService->personelGorevTabloGuncelle(strval($pool->AraUrunAdiNo));

            $this->logPlanningEvent('task_assigned_by_admin', null, $newTask, [
                'next_step_human' => 'Personel gorevi uretmesi bekleniyor.',
                'context' => [
                    'source_pool_no' => $poolId,
                    'assigned_qty' => $assignedQty,
                    'target_date' => $targetDate,
                ],
            ]);

            return [
                'success' => true,
                'message' => "Havuzdan {$assignedQty} adet görev personele atandı.",
            ];
        });
    }

    public function getTaskHistory(int $taskId): ?array
    {
        if (!Schema::hasTable('work_order_events')) {
            return ['events' => [], 'component_name' => '', 'no_table' => true];
        }

        $task = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
        if (!$task) {
            return null;
        }

        $taskNo = intval($task->No ?? 0);
        $orderItemNo = intval($task->SiparisSatirNo ?? 0);
        $componentNo = intval($task->AraUrunAdiNo ?? 0);

        $query = DB::table('work_order_events')
            ->where(function ($q) use ($taskNo, $orderItemNo) {
                $q->where('personnel_task_no', $taskNo);
                if ($orderItemNo > 0) {
                    $q->orWhere(function ($sub) use ($orderItemNo) {
                        $sub->where('aggregate_type', 'order_item')
                            ->where('aggregate_id', $orderItemNo);
                    });
                }
            })
            ->select(
                'event_type',
                'title_human',
                'summary_human',
                'actor_name',
                'source_screen',
                'happened_at',
                'context',
                'payload_before',
                'payload_after'
            )
            ->orderBy('happened_at', 'asc')
            ->limit(50)
            ->get();

        $iconMap = [
            'planning_incremented' => 'bi-chevron-up',
            'planning_decremented' => 'bi-chevron-down',
            'planning_rescheduled' => 'bi-calendar-event',
            'planning_quantity_set' => 'bi-hash',
            'planning_transferred' => 'bi-person-up',
            'personnel_task_taken' => 'bi-person-check',
            'production_completed_partial' => 'bi-play-circle',
            'production_completed_full' => 'bi-check-circle',
            'personnel_task_deleted' => 'bi-trash',
            'task_assigned_by_admin' => 'bi-person-plus',
            'work_order_created_single' => 'bi-plus-circle',
            'work_order_created_bulk' => 'bi-plus-circle',
            'work_order_created_manual' => 'bi-plus-circle',
            'work_order_cancelled' => 'bi-x-circle',
        ];

        $colorMap = [
            'planning_incremented' => '#059669',
            'planning_decremented' => '#d97706',
            'planning_rescheduled' => '#3b82f6',
            'planning_quantity_set' => '#6366f1',
            'planning_transferred' => '#d97706',
            'personnel_task_taken' => '#059669',
            'production_completed_partial' => '#d97706',
            'production_completed_full' => '#059669',
            'personnel_task_deleted' => '#dc2626',
            'task_assigned_by_admin' => '#6366f1',
            'work_order_created_single' => '#059669',
            'work_order_created_bulk' => '#059669',
            'work_order_created_manual' => '#059669',
            'work_order_cancelled' => '#dc2626',
        ];

        $events = $query->map(function ($event) use ($iconMap, $colorMap) {
            $type = $event->event_type ?? '';
            $context = is_string($event->context) ? json_decode($event->context, true) : ($event->context ?? []);

            $detail = '';
            if (!empty($context['previous_total']) || !empty($context['new_total'])) {
                $detail = 'Adet: ' . ($context['previous_total'] ?? '?') . ' → ' . ($context['new_total'] ?? '?');
            } elseif (!empty($context['diff'])) {
                $diff = intval($context['diff']);
                $detail = $diff > 0 ? "+{$diff} adet" : "{$diff} adet";
            }

            return [
                'type' => $type,
                'icon' => $iconMap[$type] ?? 'bi-circle',
                'color' => $colorMap[$type] ?? '#6b7280',
                'title' => $event->title_human ?? $type,
                'summary' => $event->summary_human ?? '',
                'detail' => $detail,
                'actor' => $event->actor_name ?? 'Sistem',
                'screen' => $event->source_screen ?? '',
                'date' => $event->happened_at ? date('d/m/Y H:i', strtotime($event->happened_at)) : '',
            ];
        });

        $componentName = DB::table('tbAraUrun')->where('No', $componentNo)->value('AraUrunAdi') ?? '';

        return [
            'events' => $events,
            'component_name' => $componentName,
        ];
    }

    public function incrementTask(int $taskId): array
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }

            if ($this->isApprovedValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Tamamlanmış görev artırılamaz.'];
            }

            if ($this->isActiveProductionValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde. Artırma işlemi yapabilmek için önce personelin üretimi tamamlaması gerekiyor.'];
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);

            $poolQuery = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($task->AraUrunAdiNo ?? 0))
                ->where('BolumAdiNo', intval($task->BolumAdiNo ?? 0))
                ->where('Adet', '>', 0)
                ->orderBy('No');
            $bomService->scopeQueryToTrace($poolQuery, $traceContext, true);
            $pool = $poolQuery->lockForUpdate()->first();

            if (!$pool) {
                return ['success' => false, 'message' => 'Bu görev için havuzda artırılabilecek adet kalmadı.'];
            }

            if ($bomService->hasOpenDescendantWork(strval($pool->AraUrunAdiNo ?? 0), $bomService->traceContextFromRecord($pool))) {
                return ['success' => false, 'message' => 'Alt bileşen görevleri tamamlanmadan bu görev artırılamaz.'];
            }

            DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                'Adet' => intval($task->Adet ?? 0) + 1,
                'BekleyenAdet' => intval($task->BekleyenAdet ?? 0),
                'Onay' => 'hazir',
            ]);

            $newToplam = intval($pool->ToplamAdet ?? 0) - 1;
            $newAdet = max(0, intval($pool->Adet ?? 0) - 1);

            if ($newToplam <= 0) {
                DB::table('tbBolumHavuz')->where('No', $pool->No)->delete();
            } else {
                DB::table('tbBolumHavuz')->where('No', $pool->No)->update([
                    'ToplamAdet' => $newToplam,
                    'Adet' => $newAdet,
                ]);
            }

            $bomService->personelGorevTabloGuncelle(strval($task->AraUrunAdiNo));

            $updatedTask = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            $this->logPlanningEvent('planning_incremented', $task, $updatedTask, [
                'next_step_human' => 'Artirilan miktar icin personel uretim girisi yapmali.',
                'context' => [
                    'consumed_pool_no' => intval($pool->No ?? 0),
                    'pool_remaining_adet' => $newAdet,
                    'pool_remaining_toplam' => $newToplam,
                ],
            ]);

            return ['success' => true];
        });
    }

    public function decrementTask(int $taskId): array
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }

            if ($this->isActiveProductionValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde. Azaltma işlemi yapabilmek için önce personelin üretimi tamamlaması gerekiyor.'];
            }

            $ready = max(0, intval($task->Adet ?? 0));
            if ($ready <= 0) {
                return ['success' => false, 'message' => 'Azaltılabilecek adet kalmadı.'];
            }

            $pending = max(0, intval($task->BekleyenAdet ?? 0));
            $newReady = max(0, $ready - 1);
            $newPending = $pending;
            $remainingTotal = $newReady + $newPending;
            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);

            if ($remainingTotal === 0) {
                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'Adet' => $newReady,
                    'BekleyenAdet' => $newPending,
                    'Onay' => 'hazir',
                ]);
            }

            $tamponDusumleri = [];
            $bomService->minAraUrunUretimiDenetle(
                intval($task->UrunIDNo ?? 0),
                '',
                strval($task->AraUrunAdiNo),
                1,
                'Planlama üzerinden görev azaltma',
                'StokHaric',
                $tamponDusumleri,
                $traceContext
            );
            $bomService->personelGorevTabloGuncelle(strval($task->AraUrunAdiNo));

            $updatedTask = $remainingTotal === 0
                ? null
                : DB::table('tbPersonelGorev')->where('No', $taskId)->first();

            $this->logPlanningEvent('planning_decremented', $task, $updatedTask, [
                'next_step_human' => $newPending > 0
                    ? 'Kalan miktar icin gorev devam ediyor.'
                    : 'Gorev sifirlandi; gerekiyorsa havuzdan yeniden planlanabilir.',
                'context' => [
                    'new_total_amount' => $remainingTotal,
                    'new_ready_amount' => $newReady,
                    'new_pending_amount' => $newPending,
                ],
            ]);

            return ['success' => true];
        });
    }

    public function deleteTask(int $taskId): array
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();
            if (!$task) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }

            if ($this->isActiveProductionValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde ve havuza iade edilemez. Önce personelin üretimi tamamlaması gerekiyor.', 'code' => 422];
            }

            $araUrunAdiNo = $task->AraUrunAdiNo;
            $bekleyenAdet = max(0, intval($task->BekleyenAdet ?? 0));
            $iadeAdet = max(0, intval($task->Adet ?? 0)) + $bekleyenAdet;

            DB::table('tbPersonelGorev')->where('No', $taskId)->delete();

            $bomService = app(BomService::class);
            if ($iadeAdet > 0) {
                $tamponDusumleri = [];
                $bomService->minAraUrunUretimiDenetle(
                    intval($task->UrunIDNo ?? 0),
                    '',
                    strval($araUrunAdiNo),
                    $iadeAdet,
                    'Personel üzerinden alınan görev',
                    'StokHaric',
                    $tamponDusumleri,
                    $bomService->traceContextFromRecord($task)
                );
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            $this->logPlanningEvent('personnel_task_deleted', $task, null, [
                'next_step_human' => $iadeAdet > 0
                    ? 'Acik miktar havuza dondu; yeniden planlama yapilabilir.'
                    : 'Gorev sifir miktarla silindi.',
                'context' => [
                    'returned_amount' => $iadeAdet,
                ],
            ]);

            return [
                'success' => true,
                'message' => $iadeAdet > 0
                    ? "{$iadeAdet} adet havuza geri aktarıldı."
                    : 'Görev silindi.',
            ];
        });
    }

    public function updateTaskDate(int $taskId, string $newDate): array
    {
        $formattedDate = \Carbon\Carbon::parse($newDate)->format('d/m/Y');

        return DB::transaction(function () use ($taskId, $formattedDate) {
            $mevcutGorev = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$mevcutGorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }

            if ($this->isActiveProductionValue($mevcutGorev->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde. Tarih değiştirmek için önce personelin üretimi tamamlaması gerekiyor.', 'code' => 422];
            }

            $araUrunAdiNo = intval($mevcutGorev->AraUrunAdiNo ?? 0);
            $hazirAdet = max(0, intval($mevcutGorev->Adet ?? 0));
            $bekleyenAdet = max(0, intval($mevcutGorev->BekleyenAdet ?? 0));
            $tasinacakAdet = $hazirAdet + $bekleyenAdet;
            if ($tasinacakAdet <= 0) {
                return ['success' => false, 'message' => 'Taşınacak aktif adet bulunamadı.'];
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($mevcutGorev);

            $mevcutKayitQuery = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $mevcutGorev->PersonelNo)
                ->whereRaw("SUBSTRING(GorevBaslamaTarihi, 1, 10) = ?", [$formattedDate])
                ->where('No', '!=', $taskId)
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->where(function ($query) {
                    $query->where('BekleyenAdet', '>', 0)
                        ->orWhereRaw($this->pendingApprovalSql());
                });

            $bomService->scopeQueryToTrace($mevcutKayitQuery, $traceContext, true);
            $mevcutKayit = $mevcutKayitQuery->lockForUpdate()->first();

            if ($mevcutKayit && !$this->isActiveProductionValue($mevcutKayit->Onay ?? null)) {
                $mevcutHazir = max(0, intval($mevcutKayit->Adet ?? 0));
                $mevcutBekleyen = max(0, intval($mevcutKayit->BekleyenAdet ?? 0));
                $split = $bomService->personnelTaskReadySplit(
                    $mevcutKayit,
                    $mevcutHazir + $mevcutBekleyen + $tasinacakAdet
                );

                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'Adet' => intval($split['ready']),
                    'BekleyenAdet' => intval($split['waiting']),
                    'Onay' => 'hazir',
                ]);

                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                $yeniTarih = $formattedDate . ' ' . now()->format('H:i');
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'GorevBaslamaTarihi' => $yeniTarih
                ]);
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            $updatedTask = DB::table('tbPersonelGorev')
                ->where('No', $mevcutKayit?->No ?? $taskId)
                ->first();

            $this->logPlanningEvent('planning_rescheduled', $mevcutGorev, $updatedTask, [
                'next_step_human' => 'Yeni tarihe gore uretim takibi devam etmeli.',
                'context' => [
                    'target_date' => $formattedDate,
                    'merged_into_task_no' => intval($mevcutKayit->No ?? 0) > 0 ? intval($mevcutKayit->No) : null,
                ],
            ]);

            return ['success' => true];
        });
    }

    public function getTransferOptions(int $taskId): ?array
    {
        $personnelNameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, '')) as PersonelAdi"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as PersonelAdi";

        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
            ->where('pg.No', $taskId)
            ->select(
                'pg.No',
                'pg.PersonelNo',
                'pg.AraUrunAdiNo',
                'pg.BolumAdiNo',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.GorevBaslamaTarihi',
                DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw($personnelNameSql)
            )
            ->first();

        if (!$task) {
            return null;
        }

        $bolumAdiNo = intval($task->BolumAdiNo ?? 0);
        $currentPersonelNo = intval($task->PersonelNo ?? 0);

        $availablePersonnel = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->where('p.BolumAdiNo', $bolumAdiNo)
            ->where('p.PersonelNo', '!=', $currentPersonelNo)
            ->select(
                'p.PersonelNo',
                DB::raw(DB::connection()->getDriverName() === 'sqlite'
                    ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, '')) as PersonelAdi"
                    : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, ''))) as PersonelAdi"
                )
            )
            ->orderBy('p.Ad')
            ->get();

        $readyQuantity = max(0, intval($task->Adet ?? 0));
        $waitingQuantity = max(0, intval($task->BekleyenAdet ?? 0));

        return [
            'task' => [
                'no' => intval($task->No ?? 0),
                'personnel_no' => $currentPersonelNo,
                'personnel_name' => trim((string) ($task->PersonelAdi ?? '')),
                'component_name' => trim((string) ($task->AraUrunAdi ?? '')),
                'department_name' => trim((string) ($task->BolumAdi ?? '')),
                'department_id' => $bolumAdiNo,
                'ready_quantity' => $readyQuantity,
                'waiting_quantity' => $waitingQuantity,
                'total_quantity' => $readyQuantity + $waitingQuantity,
                'date' => trim((string) ($task->GorevBaslamaTarihi ?? '')),
            ],
            'available_personnel' => $availablePersonnel,
        ];
    }

    public function transferTask(int $taskId, int $targetPersonnelNo): array
    {
        return DB::transaction(function () use ($taskId, $targetPersonnelNo) {
            $task = DB::table('tbPersonelGorev')
                ->where('No', $taskId)
                ->lockForUpdate()
                ->first();

            if (!$task) {
                return ['success' => false, 'message' => 'Görev bulunamadı.', 'code' => 404];
            }

            if ($this->isApprovedValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Tamamlanmış görev aktarılamaz.', 'code' => 422];
            }

            if ($this->isActiveProductionValue($task->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev şu anda üretimde ve aktarılamaz. Önce personelin üretimi tamamlaması gerekiyor.', 'code' => 422];
            }

            $currentPersonnelNo = intval($task->PersonelNo ?? 0);
            if ($currentPersonnelNo === $targetPersonnelNo) {
                return ['success' => false, 'message' => 'Görev zaten bu personelde.', 'code' => 422];
            }

            $targetPersonnel = DB::table('tbPersonel')
                ->where('PersonelNo', $targetPersonnelNo)
                ->first();

            if (!$targetPersonnel) {
                return ['success' => false, 'message' => 'Hedef personel bulunamadı.', 'code' => 404];
            }

            $taskDepartmentNo = intval($task->BolumAdiNo ?? 0);
            $targetDepartmentNo = intval($targetPersonnel->BolumAdiNo ?? 0);
            if ($taskDepartmentNo > 0 && $targetDepartmentNo !== $taskDepartmentNo) {
                return ['success' => false, 'message' => 'Hedef personel aynı bölümde değil.', 'code' => 422];
            }

            $araUrunAdiNo = intval($task->AraUrunAdiNo ?? 0);
            $hazirAdet = max(0, intval($task->Adet ?? 0));
            $bekleyenAdet = max(0, intval($task->BekleyenAdet ?? 0));
            $toplamAdet = $hazirAdet + $bekleyenAdet;

            if ($toplamAdet <= 0) {
                return ['success' => false, 'message' => 'Aktarılacak adet bulunamadı.', 'code' => 422];
            }

            $bomService = app(BomService::class);
            $traceContext = $bomService->traceContextFromRecord($task);
            $formattedDate = substr(trim((string) ($task->GorevBaslamaTarihi ?? '')), 0, 10);

            $existingQuery = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $targetPersonnelNo)
                ->whereRaw("SUBSTRING(GorevBaslamaTarihi, 1, 10) = ?", [$formattedDate])
                ->where('No', '!=', $taskId)
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->where(function ($query) {
                    $query->where('BekleyenAdet', '>', 0)
                        ->orWhereRaw($this->pendingApprovalSql());
                });

            $bomService->scopeQueryToTrace($existingQuery, $traceContext, true);
            $existing = $existingQuery->lockForUpdate()->first();

            $targetName = trim(($targetPersonnel->Ad ?? '') . ' ' . ($targetPersonnel->Soyad ?? ''));

            if ($existing && !$this->isActiveProductionValue($existing->Onay ?? null)) {
                $existingHazir = max(0, intval($existing->Adet ?? 0));
                $existingBekleyen = max(0, intval($existing->BekleyenAdet ?? 0));
                $newTotal = $existingHazir + $existingBekleyen + $toplamAdet;
                $split = $bomService->personnelTaskReadySplit($existing, $newTotal);

                DB::table('tbPersonelGorev')->where('No', $existing->No)->update([
                    'Adet' => intval($split['ready']),
                    'BekleyenAdet' => intval($split['waiting']),
                    'Onay' => 'hazir',
                ]);

                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'PersonelNo' => $targetPersonnelNo,
                    'Onay' => 'hazir',
                ]);
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            $updatedTask = DB::table('tbPersonelGorev')
                ->where('No', $existing->No ?? $taskId)
                ->first();

            $this->logPlanningEvent('planning_transferred', $task, $updatedTask, [
                'next_step_human' => 'Gorev baska personele aktarildi; hedef personelin isi takip edilmeli.',
                'context' => [
                    'source_personnel_no' => $currentPersonnelNo,
                    'target_personnel_no' => $targetPersonnelNo,
                    'target_personnel_name' => $targetName,
                    'transferred_quantity' => $toplamAdet,
                    'merged_into_task_no' => intval($existing->No ?? 0) > 0 ? intval($existing->No) : null,
                ],
            ]);

            return [
                'success' => true,
                'message' => "Görev {$targetName} personeline aktarıldı ({$toplamAdet} adet).",
            ];
        });
    }
}
