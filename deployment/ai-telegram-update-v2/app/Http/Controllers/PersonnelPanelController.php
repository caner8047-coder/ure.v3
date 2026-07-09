<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Personnel;
use App\Services\BomService;
use App\Services\LegacyWorkOrderWriter;
use App\Services\PersonnelTaskMerger;
use App\Services\StockMovementLogger;
use App\Models\TelegramNotificationLog;
use App\Jobs\SendTelegramNotificationJob;
use App\Services\TelegramNotificationService;
use Carbon\Carbon;

/**
 * Personel Paneli API Controller
 * Görev listeleme, alma, tamamlama, dashboard istatistikleri
 */
class PersonnelPanelController extends Controller
{
    private function personelNo(Request $request): int
    {
        return intval($request->user()->personnel_no ?? 0);
    }

    private function mergeDuplicatePersonnelTasks(int $personelNo): void
    {
        try {
            app(PersonnelTaskMerger::class)->mergeOpenDuplicatesForPersonnel($personelNo);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function pendingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function approvedApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        return "({$normalized} IN ('1', 'true'))";
    }

    private function nullableLegacyColumnSql(string $table, string $column, string $alias): string
    {
        if (!Schema::hasColumn($table, $column)) {
            return 'NULL';
        }

        return "NULLIF({$alias}.{$column}, '')";
    }

    private function isFreeStockProductionOrder(?object $order): bool
    {
        if (!$order) {
            return false;
        }

        $type = strtolower(trim((string) ($order->StokUretimTipi ?? '')));
        if (in_array($type, ['ana', 'ilave'], true)) {
            return true;
        }

        $customer = trim((string) ($order->Musteri ?? ''));
        if (str_starts_with($customer, 'ÖZEL ÜRETİM (SERBEST') || str_contains($customer, 'Stok İlavesi')) {
            return true;
        }

        $orderNo = strtoupper(trim((string) ($order->SiparisNo ?? '')));
        $marketplace = strtolower(trim((string) ($order->Pazaryeri ?? '')));

        return str_starts_with($orderNo, 'STOK-')
            && $marketplace === 'stok'
            && str_starts_with($customer, 'ÖZEL ÜRETİM');
    }

    private function taskCompletesFreeStockTarget(?object $order, int $componentNo): bool
    {
        if (!$order || $componentNo <= 0 || !$this->isFreeStockProductionOrder($order)) {
            return false;
        }

        return $this->resolveMatchedAraUrunNoForOrder(
            intval($order->EslesenUrunNo ?? 0),
            trim((string) ($order->EslesenUrunTur ?? ''))
        ) === $componentNo;
    }

    private array $taskImageCache = [];

    private function enrichTaskImages($tasks)
    {
        if ($tasks instanceof \Illuminate\Support\Collection) {
            return $tasks->map(fn ($task) => $this->enrichTaskImage($task));
        }

        foreach ($tasks as $task) {
            $this->enrichTaskImage($task);
        }

        return $tasks;
    }

    private function enrichTaskImage(object $task): object
    {
        if (trim((string) ($task->Resim ?? '')) !== '') {
            return $task;
        }

        $componentNo = intval($task->AraUrunAdiNo ?? 0);
        if ($componentNo <= 0) {
            return $task;
        }

        $image = $this->resolveTaskImageFromBom($componentNo);
        if ($image !== '') {
            $task->Resim = $image;
        }

        return $task;
    }

    private function resolveTaskImageFromBom(int $componentNo): string
    {
        if (array_key_exists($componentNo, $this->taskImageCache)) {
            return $this->taskImageCache[$componentNo];
        }

        if (!Schema::hasTable('tbAraUrun') || !Schema::hasColumn('tbAraUrun', 'Yol') || !Schema::hasColumn('tbAraUrun', 'Resim')) {
            return $this->taskImageCache[$componentNo] = $this->resolveTaskImageFromProductPath($componentNo);
        }

        $frontier = [$componentNo];
        $visited = [];
        $fallbackImage = '';

        for ($depth = 0; $depth < 8; $depth++) {
            $lookupIds = array_values(array_unique(array_filter(
                array_map('intval', $frontier),
                fn ($id) => $id > 0 && !isset($visited[$id])
            )));

            if (empty($lookupIds)) {
                break;
            }

            foreach ($lookupIds as $lookupId) {
                $visited[$lookupId] = true;
            }

            $parents = DB::table('tbAraUrun')
                ->select('No', 'Resim', 'UrunCesidi', 'BolumAdiNo')
                ->where(function ($query) use ($lookupIds) {
                    foreach ($lookupIds as $lookupId) {
                        [$sql, $bindings] = $this->pathContainsComponentSql('Yol', $lookupId);
                        $query->orWhereRaw($sql, $bindings);
                    }
                })
                ->orderBy('No')
                ->get();

            if ($parents->isEmpty()) {
                break;
            }

            $frontier = [];
            foreach ($parents as $parent) {
                $image = trim((string) ($parent->Resim ?? ''));
                if ($image !== '') {
                    if ($this->isFinalProductComponent($parent)) {
                        return $this->taskImageCache[$componentNo] = $image;
                    }

                    $fallbackImage = $fallbackImage ?: $image;
                }

                $parentNo = intval($parent->No ?? 0);
                if ($parentNo > 0 && !isset($visited[$parentNo])) {
                    $frontier[] = $parentNo;
                }
            }
        }

        return $this->taskImageCache[$componentNo] = ($fallbackImage ?: $this->resolveTaskImageFromProductPath($componentNo));
    }

    private function resolveTaskImageFromProductPath(int $componentNo): string
    {
        if (!Schema::hasTable('tbUrunler') || !Schema::hasColumn('tbUrunler', 'AraAdlarYol') || !Schema::hasColumn('tbUrunler', 'Resim')) {
            return '';
        }

        [$sql, $bindings] = $this->pathContainsComponentSql('AraAdlarYol', $componentNo);
        $image = DB::table('tbUrunler')
            ->whereRaw($sql, $bindings)
            ->whereNotNull('Resim')
            ->where('Resim', '!=', '')
            ->orderBy('No')
            ->value('Resim');

        return trim((string) $image);
    }

    private function pathContainsComponentSql(string $column, int $componentNo): array
    {
        $needle = "%:{$componentNo}-%";

        if (DB::connection()->getDriverName() === 'sqlite') {
            return ["':' || REPLACE(IFNULL({$column}, ''), ' ', '') || ':' LIKE ?", [$needle]];
        }

        return ["CONCAT(':', REPLACE(IFNULL({$column}, ''), ' ', ''), ':') LIKE ?", [$needle]];
    }

    private function isFinalProductComponent(object $component): bool
    {
        if (intval($component->BolumAdiNo ?? 0) === 1023) {
            return true;
        }

        $type = strtr(strtolower(trim((string) ($component->UrunCesidi ?? ''))), [
            'ı' => 'i',
            'İ' => 'i',
            'ü' => 'u',
            'Ü' => 'u',
            ' ' => '',
        ]);

        return str_contains($type, 'nihaiurun') || str_contains($type, 'nihayiurun');
    }

    private function productionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        return "({$normalized} IN ('hazir', 'ready'))";
    }

    private function notProductionReadyApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('hazir', 'ready'))";
    }

    private function inProductionApprovalSql(string $column = 'Onay'): string
    {
        return '(' . $this->pendingApprovalSql($column) . ' AND ' . $this->notProductionReadyApprovalSql($column) . ')';
    }

    private function assignedWaitingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        $notActive = "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('0', 'false', 'hayir', 'hayır', 'no'))";

        return '(' . $this->pendingApprovalSql($column) . ' AND ' . $notActive . ')';
    }

    private function isProductionReadyApproval(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['hazir', 'ready'], true);
    }

    private function refreshAssignedTaskReadinessForPersonnel(int $personelNo): void
    {
        if ($personelNo <= 0 || !Schema::hasTable('tbPersonelGorev')) {
            return;
        }

        try {
            $componentNos = DB::table('tbPersonelGorev')
                ->where('PersonelNo', $personelNo)
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->whereRaw($this->productionReadyApprovalSql('Onay'))
                ->distinct()
                ->limit(100)
                ->pluck('AraUrunAdiNo');
        } catch (\Throwable) {
            $componentNos = collect();
        }

        $bomService = app(BomService::class);
        foreach ($componentNos as $componentNo) {
            $componentNo = intval($componentNo);
            if ($componentNo <= 0) {
                continue;
            }

            try {
                $bomService->personelGorevTabloGuncelle(strval($componentNo));
            } catch (\Throwable) {
                // BOM senkronu tek bir kayitta patlarsa listeleme donusumu durmasin.
            }
        }

        try {
            $waitingTasks = DB::table('tbPersonelGorev')
                ->where('PersonelNo', $personelNo)
                ->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->orWhere('BekleyenAdet', '>', 0);
                })
                ->whereRaw($this->productionReadyApprovalSql('Onay'))
                ->get();

            foreach ($waitingTasks as $task) {
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
        } catch (\Throwable) {
            // Listeleme akisi, stok senkronu gecici hata alsa da acilabilsin.
        }
    }

    private function isApprovedValue(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'evet', 'yes'], true);
    }

    private function activeProductionApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

        return "({$normalized} IN ('0', 'false', 'hayir', 'hayır', 'no'))";
    }

    private function isActiveProductionApproval(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['0', 'false', 'hayir', 'hayır', 'no'], true);
    }

    private function taskDateKey(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        if (preg_match('/^(\d{2})[\/.](\d{2})[\/.](\d{4})/', $raw, $matches)) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        return substr($raw, 0, 10);
    }

    private function normalizeTaskGroupName(mixed $value): string
    {
        $name = strtolower(trim((string) $value));
        $name = strtr($name, [
            'ı' => 'i',
            'İ' => 'i',
            'ğ' => 'g',
            'Ğ' => 'g',
            'ü' => 'u',
            'Ü' => 'u',
            'ş' => 's',
            'Ş' => 's',
            'ö' => 'o',
            'Ö' => 'o',
            'ç' => 'c',
            'Ç' => 'c',
        ]);

        return preg_replace('/\s+/', ' ', $name) ?? '';
    }

    private function readyTaskVisualGroupKey(object $task): string
    {
        $componentNo = intval($task->AraUrunAdiNo ?? 0);
        $productNo = intval($task->UrunIDNo ?? 0);
        $nameKey = $this->normalizeTaskGroupName($task->AraUrunAdi ?? $task->UrunAdi ?? '');

        if ($componentNo > 0) {
            $identity = 'component:' . $componentNo;
        } elseif ($productNo > 0) {
            $identity = 'product:' . $productNo;
        } elseif ($nameKey !== '') {
            $identity = 'name:' . $nameKey;
        } else {
            return '';
        }

        return sha1(implode('|', [
            intval($task->PersonelNo ?? 0),
            $identity,
            intval($task->BolumAdiNo ?? 0),
            $this->taskDateKey($task->GorevBaslamaTarihi ?? $task->GorevTarihi ?? ''),
            'ready',
        ]));
    }

    private function isAssignedReadyTaskGroupable(object $task): bool
    {
        return trim((string) ($task->Kaynak ?? 'personel_gorev')) === 'personel_gorev'
            && intval($task->Baslatilabilir ?? 0) === 1
            && intval($task->Adet ?? 0) > 0
            && $this->readyTaskVisualGroupKey($task) !== '';
    }

    private function assignedReadyTaskBreakdown(object $task): array
    {
        return [
            'No' => intval($task->No ?? 0),
            'Adet' => max(0, intval($task->Adet ?? 0)),
            'BekleyenAdet' => max(0, intval($task->BekleyenAdet ?? 0)),
            'ToplamAdet' => max(0, intval($task->ToplamAdet ?? 0)),
            'GorevTarihi' => trim((string) ($task->GorevTarihi ?? $task->GorevBaslamaTarihi ?? '')),
            'SiparisNo' => trim((string) ($task->SiparisNo ?? '')),
            'SiparisSatirNo' => intval($task->SiparisSatirNo ?? 0),
            'UrunAdi' => trim((string) ($task->UrunAdi ?? '')),
        ];
    }

    private function buildAssignedReadyTaskGroup(array $rows, string $groupKey): object
    {
        $group = clone $rows[0];
        $group->Adet = array_sum(array_map(fn ($task) => max(0, intval($task->Adet ?? 0)), $rows));
        $group->BekleyenAdet = array_sum(array_map(fn ($task) => max(0, intval($task->BekleyenAdet ?? 0)), $rows));
        $group->ToplamAdet = array_sum(array_map(fn ($task) => max(0, intval($task->ToplamAdet ?? 0)), $rows));
        $group->UretilebilirAdet = $group->Adet;
        $group->GrupMu = true;
        $group->GrupAnahtari = $groupKey;
        $group->GrupKayitSayisi = count($rows);
        $group->GrupEtiketi = count($rows) . ' görev toplamı';
        $group->GrupGorevNoListesi = array_values(array_map(fn ($task) => intval($task->No ?? 0), $rows));
        $group->GrupDetaylari = array_values(array_map(fn ($task) => $this->assignedReadyTaskBreakdown($task), $rows));

        return $group;
    }

    private function groupAssignedReadyTasks($tasks)
    {
        $tasks = $tasks instanceof \Illuminate\Support\Collection ? $tasks : collect($tasks);
        $groups = [];

        foreach ($tasks as $task) {
            if (!$this->isAssignedReadyTaskGroupable($task)) {
                continue;
            }

            $groups[$this->readyTaskVisualGroupKey($task)][] = $task;
        }

        $seen = [];
        return $tasks->reduce(function ($carry, $task) use (&$seen, $groups) {
            if (!$this->isAssignedReadyTaskGroupable($task)) {
                $carry->push($task);
                return $carry;
            }

            $groupKey = $this->readyTaskVisualGroupKey($task);
            $rows = $groups[$groupKey] ?? [];

            if (count($rows) < 2) {
                $task->GrupMu = false;
                $task->GrupAnahtari = $groupKey;
                $task->GrupKayitSayisi = 1;
                $task->GrupGorevNoListesi = [intval($task->No ?? 0)];
                $task->GrupDetaylari = [$this->assignedReadyTaskBreakdown($task)];
                $carry->push($task);
                return $carry;
            }

            if (!isset($seen[$groupKey])) {
                $seen[$groupKey] = true;
                $carry->push($this->buildAssignedReadyTaskGroup($rows, $groupKey));
            }

            return $carry;
        }, collect())->values();
    }

    private function legacyDateOrderSql(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CASE WHEN {$column} LIKE '__/__/____%' OR {$column} LIKE '__.__.____%' THEN substr({$column}, 7, 4) || '-' || substr({$column}, 4, 2) || '-' || substr({$column}, 1, 2) || substr({$column}, 11) ELSE {$column} END";
        }

        return "COALESCE(STR_TO_DATE({$column}, '%d/%m/%Y %H:%i'), STR_TO_DATE({$column}, '%d.%m.%Y %H:%i'), STR_TO_DATE({$column}, '%Y-%m-%d %H:%i:%s'))";
    }

    private function dueTaskDateSql(string $column): string
    {
        $today = now()->toDateString();

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "({$column} IS NULL OR TRIM({$column}) = '' OR DATE(" . $this->legacyDateOrderSql($column) . ") <= DATE('{$today}'))";
        }

        return "({$column} IS NULL OR TRIM({$column}) = '' OR DATE(" . $this->legacyDateOrderSql($column) . ") <= DATE('{$today}'))";
    }

    private function whereTaskDue($query, string $column)
    {
        return $query->whereRaw($this->dueTaskDateSql($column));
    }

    private function isTaskDue(?string $startedAt): bool
    {
        $raw = trim((string) $startedAt);
        if ($raw === '') {
            return true;
        }

        $formats = ['d/m/Y H:i', 'd.m.Y H:i', 'd/m/Y H:i:s', 'd.m.Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y', 'd.m.Y', 'Y-m-d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $raw)->startOfDay()->lte(now()->startOfDay());
            } catch (\Throwable) {
            }
        }

        return true;
    }

    private function performanceMinutes(?string $startedAt, ?string $finishedAt): int
    {
        $formats = ['d/m/Y H:i', 'd.m.Y H:i', 'd/m/Y H:i:s', 'd.m.Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y', 'd.m.Y'];
        $start = null;
        $finish = null;

        foreach ($formats as $format) {
            if (!$start && trim((string) $startedAt) !== '') {
                try { $start = Carbon::createFromFormat($format, trim((string) $startedAt)); } catch (\Throwable) {}
            }
            if (!$finish && trim((string) $finishedAt) !== '') {
                try { $finish = Carbon::createFromFormat($format, trim((string) $finishedAt)); } catch (\Throwable) {}
            }
        }

        if (!$start || !$finish || $finish->lessThan($start)) {
            return 0;
        }

        return (int) $start->diffInMinutes($finish);
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

    private function logTaskEvent(string $eventType, object $task, ?object $afterTask = null, array $attributes = []): void
    {
        app(\App\Services\WorkOrderEventLogger::class)->log(array_merge(
            $this->buildTaskEventBase($task),
            [
                'event_type' => $eventType,
                'payload_before' => $this->serializeRecord($task),
                'payload_after' => $this->serializeRecord($afterTask),
            ],
            $attributes
        ));
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi personel akisini bozmasin.
        }
    }

    private function logRequiredStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        if (!Schema::hasTable('stock_movements')) {
            return;
        }

        $movement = app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        if (!$movement) {
            throw new \RuntimeException('Kritik stok hareketi kaydedilemedi.');
        }
    }

    private function personnelTaskSplitPayload(object $task, array $overrides = []): array
    {
        $columns = Schema::getColumnListing('tbPersonelGorev');
        $payload = [];

        foreach ($columns as $column) {
            if ($column === 'No') {
                continue;
            }

            if (property_exists($task, $column)) {
                $payload[$column] = $task->{$column};
            }
        }

        foreach ($overrides as $column => $value) {
            if (in_array($column, $columns, true) && $column !== 'No') {
                $payload[$column] = $value;
            }
        }

        return $payload;
    }

    private function rebalanceReadyPersonnelTask(int $taskNo, BomService $bomService): ?object
    {
        if ($taskNo <= 0) {
            return null;
        }

        $task = DB::table('tbPersonelGorev')->where('No', $taskNo)->lockForUpdate()->first();
        if (!$task) {
            return null;
        }

        $split = $bomService->personnelTaskReadySplit($task);
        $ready = intval($split['ready']);
        $waiting = intval($split['waiting']);

        if ($ready !== intval($task->Adet ?? 0) || $waiting !== intval($task->BekleyenAdet ?? 0)) {
            DB::table('tbPersonelGorev')->where('No', $taskNo)->update([
                'Adet' => $ready,
                'BekleyenAdet' => $waiting,
            ]);
        }

        return DB::table('tbPersonelGorev')->where('No', $taskNo)->first();
    }

    private function consumeDirectChildStocksForTask(object $task, int $parentQuantity, BomService $bomService): array
    {
        $requirements = $bomService->directChildRequirements(strval($task->AraUrunAdiNo ?? 0), $parentQuantity);
        if (empty($requirements)) {
            return ['success' => true, 'consumed' => []];
        }

        $traceContext = $bomService->traceContextFromRecord($task);
        $consumed = [];

        foreach ($requirements as $componentNo => $requiredQuantity) {
            $heldQuantity = $this->heldQuantityForTaskComponent(
                $task,
                intval($componentNo),
                intval($requiredQuantity),
                $bomService
            );
            $result = $this->consumeComponentStockForTask(
                $task,
                intval($componentNo),
                intval($requiredQuantity),
                $heldQuantity,
                true,
                true,
                'parent_start'
            );

            if (!($result['success'] ?? false)) {
                return $result;
            }

            $consumed[] = $result['consumed'];
            $bomService->personelGorevTabloGuncelle(strval($componentNo));
            $bomService->sonrakiUrunAdetleriniGuncelle(strval($componentNo), 0, 0);
        }

        return ['success' => true, 'consumed' => $consumed];
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

    private function readinessIssueForTask(object $task, BomService $bomService): ?string
    {
        $readyQuantity = max(0, intval($task->Adet ?? 0));
        $waitingQuantity = max(0, intval($task->BekleyenAdet ?? 0));
        $quantityToCheck = $readyQuantity > 0 ? $readyQuantity : $waitingQuantity;
        if ($quantityToCheck <= 0) {
            return 'Parça/stok bekliyor.';
        }

        $requirements = $bomService->directChildRequirements(strval($task->AraUrunAdiNo ?? 0), $quantityToCheck);
        if (empty($requirements)) {
            return null;
        }

        foreach ($requirements as $componentNo => $requiredQuantity) {
            $heldQuantity = $this->heldQuantityForTaskComponent(
                $task,
                intval($componentNo),
                intval($requiredQuantity),
                $bomService
            );
            $result = $this->inspectComponentStockAvailability(
                intval($componentNo),
                intval($requiredQuantity),
                $heldQuantity,
                true,
                true
            );

            if (!($result['success'] ?? false)) {
                return $result['message'] ?? 'Alt parça stoğu yetersiz.';
            }
        }

        return null;
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

        if ($this->isActiveProductionApproval($approval) || $approval === '') {
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

        $status = $this->supplierStatusLabel($task);

        return match ($status) {
            'Üretime hazır' => 'Personelin görevi kabul edip üretime alması bekleniyor.',
            'Üretimde' => 'Personelin üretim girişi yapması bekleniyor.',
            default => 'Görev açık.',
        };
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
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
            ->orderBy('pg.No');

        $bomService->scopeQueryToTrace($query, $traceContext, true, 'pg');

        $rows = $query
            ->limit(20)
            ->get([
                'pg.No',
                'pg.PersonelNo',
                'pg.GorevBaslamaTarihi',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.Onay',
                'pg.AraUrunAdiNo',
                'pg.BolumAdiNo',
                'pg.UrunIDNo',
                'pg.SiparisSatirNo',
                'pg.SiparisNo',
                DB::raw("IFNULL(p.Ad, '') as PersonelAd"),
                DB::raw("IFNULL(p.Soyad, '') as PersonelSoyad"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
            ]);

        $remainingNeed = max(0, $missingQuantity);
        $currentPersonelNo = intval($waitingTask->PersonelNo ?? 0);

        return $rows->map(function ($row) use (&$remainingNeed, $currentPersonelNo, $bomService) {
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
                'can_notify' => $personelNo > 0 && $personelNo !== $currentPersonelNo,
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
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
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

        $columns = [
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
        $columns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo')
            ? 'pg.SiparisSatirNo'
            : DB::raw('NULL as SiparisSatirNo');
        $columns[] = Schema::hasColumn('tbPersonelGorev', 'SiparisNo')
            ? 'pg.SiparisNo'
            : DB::raw('NULL as SiparisNo');

        $remainingNeed = max(0, $missingQuantity);

        return $query
            ->limit(10)
            ->get($columns)
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
            ->orderByRaw($this->legacyDateOrderSql('bh.GorevBaslangicTarihi') . ' ASC')
            ->orderBy('bh.No');

        $bomService->scopeQueryToTrace($query, $traceContext, true, 'bh');

        return $query
            ->limit(10)
            ->get([
                'bh.No',
                'bh.Adet',
                'bh.ToplamAdet',
                'bh.GorevBaslangicTarihi',
                'bh.GorevBaslangicSaati',
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

    private function inspectComponentStockAvailability(
        int $componentNo,
        int $requiredQuantity,
        int $heldQuantity,
        bool $allowFreeStock,
        bool $strictQuantity
    ): array {
        $requiredQuantity = max(0, $requiredQuantity);
        if ($componentNo <= 0 || $requiredQuantity <= 0) {
            return ['success' => true];
        }

        $component = DB::table('tbAraUrun')->where('No', $componentNo)->first(['No', 'AraUrunAdi', 'BolumAdiNo']);
        $stockRowsQuery = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('Adet', '>', 0);

        $departmentNo = intval($component->BolumAdiNo ?? 0);
        if ($departmentNo > 0) {
            $stockRowsQuery->orderByRaw('CASE WHEN BolumAdiNo = ' . $departmentNo . ' THEN 0 ELSE 1 END');
        }

        $stockRows = $stockRowsQuery->orderBy('No')->get();
        $totalStock = intval($stockRows->sum(fn ($row) => max(0, intval($row->Adet ?? 0))));
        $freeStock = intval($stockRows->sum(fn ($row) => max(0, min(intval($row->Adet ?? 0), intval($row->TamponMiktar ?? 0)))));
        $heldToUse = min($requiredQuantity, max(0, $heldQuantity), $totalStock);
        $freeToUse = $allowFreeStock ? max(0, $requiredQuantity - $heldToUse) : 0;
        $componentName = trim((string) ($component->AraUrunAdi ?? 'Alt parça'));

        if ($strictQuantity && $totalStock < $requiredQuantity) {
            return [
                'success' => false,
                'message' => "{$componentName} için yeterli alt parça stoğu yok.",
            ];
        }

        if ($strictQuantity && ($heldToUse + $freeToUse) < $requiredQuantity) {
            return [
                'success' => false,
                'message' => "{$componentName} için yeterli alt parça stoğu yok.",
            ];
        }

        if ($freeToUse > $freeStock && $strictQuantity) {
            return [
                'success' => false,
                'message' => "{$componentName} için boşta/tahsisli alt parça stoğu yetersiz.",
            ];
        }

        return ['success' => true];
    }

    private function enrichAssignedTaskAvailability($tasks)
    {
        $bomService = app(BomService::class);

        return $tasks->map(function ($task) use ($bomService) {
            if (intval($task->Baslatilabilir ?? 0) !== 1) {
                $issue = $this->readinessIssueForTask($task, $bomService);
                if ($issue !== null) {
                    $task->Durum = 'Alt parça bekliyor';
                    $task->BeklemeNedeni = $issue;
                    $task->ButonMetni = str_contains($issue, 'alt parça')
                        ? 'Alt parça stoğu yetersiz'
                        : $issue;
                    return $task;
                }

                $task->BeklemeNedeni = $task->BeklemeNedeni ?? '';
                $task->ButonMetni = $task->ButonMetni ?? 'Bekliyor';
                return $task;
            }

            $issue = $this->readinessIssueForTask($task, $bomService);
            if ($issue !== null) {
                $task->Baslatilabilir = 0;
                $task->Durum = 'Alt parça bekliyor';
                $task->BeklemeNedeni = $issue;
                $task->ButonMetni = str_contains($issue, 'alt parça')
                    ? 'Alt parça stoğu yetersiz'
                    : $issue;
                return $task;
            }

            $task->BeklemeNedeni = '';
            $task->ButonMetni = 'Görevi Kabul Et';

            return $task;
        });
    }

    private function cleanupDescendantHeldStocksForFinishedOrder(object $task, BomService $bomService): void
    {
        $traceContext = $bomService->traceContextFromRecord($task);
        if (intval($traceContext['siparisSatirNo'] ?? 0) <= 0) {
            return;
        }

        foreach ($bomService->descendantComponentNos(strval($task->AraUrunAdiNo ?? 0)) as $componentNo) {
            $heldQuantity = $bomService->orderHeldStockQuantity(intval($componentNo), $traceContext);
            if ($heldQuantity <= 0) {
                continue;
            }

            $this->consumeComponentStockForTask(
                $task,
                intval($componentNo),
                $heldQuantity,
                $heldQuantity,
                false,
                false,
                'finished_order_cleanup'
            );
        }
    }

    private function closePassiveContinuingOrderAfterProduction(object $order, object $task): array
    {
        $orderItemNo = intval($order->No ?? 0);
        $requestedQuantity = max(0, intval($order->Adet ?? 0));
        $componentNo = $this->resolveMatchedAraUrunNoForOrder(
            intval($order->EslesenUrunNo ?? 0),
            (string) ($order->EslesenUrunTur ?? '')
        );

        if ($componentNo <= 0) {
            $componentNo = intval($task->AraUrunAdiNo ?? 0);
        }

        $summary = [
            'order_item_no' => $orderItemNo,
            'requested_quantity' => $requestedQuantity,
            'component_no' => $componentNo,
            'stock_row_no' => null,
            'stock_out_quantity' => 0,
        ];

        if ($orderItemNo <= 0) {
            return $summary;
        }

        if (Schema::hasTable('tbBolumAraStok') && $requestedQuantity > 0 && $componentNo > 0) {
            $taskComponentNo = intval($task->AraUrunAdiNo ?? 0);
            $taskDepartmentNo = intval($task->BolumAdiNo ?? 0);
            $preferTaskDepartment = $componentNo === $taskComponentNo && $taskDepartmentNo > 0;

            $stockQuery = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $componentNo);
            if ($preferTaskDepartment) {
                $stockQuery->where('BolumAdiNo', $taskDepartmentNo);
            }

            $stockBefore = $stockQuery->orderBy('No')->lockForUpdate()->first();
            if (!$stockBefore && $preferTaskDepartment) {
                $stockBefore = DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', $componentNo)
                    ->orderBy('No')
                    ->lockForUpdate()
                    ->first();
            }

            if ($stockBefore) {
                $stockOutQuantity = min($requestedQuantity, max(0, intval($stockBefore->Adet ?? 0)));
                if ($stockOutQuantity > 0) {
                    $newQuantity = max(0, intval($stockBefore->Adet ?? 0) - $stockOutQuantity);
                    $newBuffer = min($newQuantity, max(0, intval($stockBefore->TamponMiktar ?? 0)));

                    DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->update([
                        'Adet' => $newQuantity,
                        'TamponMiktar' => $newBuffer,
                    ]);

                    $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();
                    $this->logStockMovement($stockBefore, $stockAfter, [
                        'movement_type' => 'order_auto_stock_out',
                        'source_type' => 'pasif_devam_eden_completion',
                        'source_id' => $orderItemNo,
                        'order_item_no' => $orderItemNo,
                        'order_no' => trim((string) ($order->SiparisNo ?? '')) ?: null,
                        'work_order_no' => intval($order->GorevNo ?? 0) > 0 ? intval($order->GorevNo) : null,
                        'personnel_task_no' => intval($task->No ?? 0) > 0 ? intval($task->No) : null,
                        'description' => 'Pasif devam eden sipariş üretim tamamlanınca stok girişini aynı anda kapattı.',
                        'metadata' => [
                            'requested_quantity' => $requestedQuantity,
                            'stock_out_quantity' => $stockOutQuantity,
                            'completed_component_no' => intval($task->AraUrunAdiNo ?? 0),
                            'closed_immediately' => true,
                        ],
                    ]);

                    $summary['stock_row_no'] = intval($stockBefore->No ?? 0) ?: null;
                    $summary['stock_out_quantity'] = $stockOutQuantity;
                }
            }
        }

        DB::table('tbSiparisSatir')->where('No', $orderItemNo)->update([
            'Durum' => 'Pasif',
            'Aktif' => 0,
            'GuncellemeTarihi' => now(),
        ]);

        return $summary;
    }

    private function resolveMatchedAraUrunNoForOrder(int $eslesenUrunNo, string $eslesenUrunTur): int
    {
        if ($eslesenUrunNo <= 0) {
            return 0;
        }

        if (in_array($eslesenUrunTur, ['Ara', 'HamMadde'], true)) {
            return $eslesenUrunNo;
        }

        if ($eslesenUrunTur !== 'Nihai' || !Schema::hasTable('tbUrunler') || !Schema::hasTable('tbAraUrun')) {
            return 0;
        }

        $urunID = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->value('UrunID');
        if (trim((string) $urunID) === '') {
            return 0;
        }

        return intval(DB::table('tbAraUrun')->where('AraUrunAdi', trim((string) $urunID))->value('No') ?? 0);
    }

    private function consumeComponentStockForTask(
        object $task,
        int $componentNo,
        int $requiredQuantity,
        int $heldQuantity,
        bool $allowFreeStock,
        bool $strictQuantity,
        string $reason
    ): array {
        $requiredQuantity = max(0, $requiredQuantity);
        if ($componentNo <= 0 || $requiredQuantity <= 0) {
            return [
                'success' => true,
                'consumed' => [
                    'component_no' => $componentNo,
                    'required_quantity' => $requiredQuantity,
                    'held_used' => 0,
                    'free_used' => 0,
                ],
            ];
        }

        $component = DB::table('tbAraUrun')->where('No', $componentNo)->first(['No', 'AraUrunAdi', 'BolumAdiNo']);
        $stockRowsQuery = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('Adet', '>', 0);

        $departmentNo = intval($component->BolumAdiNo ?? 0);
        if ($departmentNo > 0) {
            $stockRowsQuery->orderByRaw('CASE WHEN BolumAdiNo = ' . $departmentNo . ' THEN 0 ELSE 1 END');
        }

        $stockRows = $stockRowsQuery->orderBy('No')->lockForUpdate()->get();
        $totalStock = intval($stockRows->sum(fn ($row) => max(0, intval($row->Adet ?? 0))));
        $freeStock = intval($stockRows->sum(fn ($row) => max(0, min(intval($row->Adet ?? 0), intval($row->TamponMiktar ?? 0)))));
        $heldToUse = min($requiredQuantity, max(0, $heldQuantity), $totalStock);
        $freeToUse = $allowFreeStock ? max(0, $requiredQuantity - $heldToUse) : 0;

        if ($strictQuantity && $totalStock < $requiredQuantity) {
            $componentName = trim((string) ($component->AraUrunAdi ?? 'Alt parça'));
            return [
                'success' => false,
                'message' => "{$componentName} için yeterli alt parça stoğu yok.",
            ];
        }

        if ($strictQuantity && ($heldToUse + $freeToUse) < $requiredQuantity) {
            $componentName = trim((string) ($component->AraUrunAdi ?? 'Alt parça'));
            return [
                'success' => false,
                'message' => "{$componentName} için yeterli alt parça stoğu yok.",
            ];
        }

        if ($freeToUse > $freeStock) {
            if ($strictQuantity) {
                $componentName = trim((string) ($component->AraUrunAdi ?? 'Alt parça'));
                return [
                    'success' => false,
                    'message' => "{$componentName} için boşta/tahsisli alt parça stoğu yetersiz.",
                ];
            }

            $freeToUse = $freeStock;
        }

        if (!$strictQuantity && !$allowFreeStock) {
            $heldToUse = min($heldToUse, $totalStock);
            $freeToUse = 0;
        }

        $heldUsed = $this->consumeHeldQuantityFromStockRows($task, $componentNo, $heldToUse, $reason);
        $freeUsed = $this->consumeFreeQuantityFromStockRows($task, $componentNo, $freeToUse, $reason);

        return [
            'success' => true,
            'consumed' => [
                'component_no' => $componentNo,
                'required_quantity' => $requiredQuantity,
                'held_used' => $heldUsed,
                'free_used' => $freeUsed,
                'reason' => $reason,
            ],
        ];
    }

    private function consumeHeldQuantityFromStockRows(object $task, int $componentNo, int $quantity, string $reason): int
    {
        $remaining = max(0, $quantity);
        $used = 0;
        if ($remaining <= 0) {
            return 0;
        }

        $rows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('Adet', '>', 0)
            ->orderByRaw('(COALESCE(Adet, 0) - COALESCE(TamponMiktar, 0)) DESC')
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $heldCapacity = max(0, intval($row->Adet ?? 0) - intval($row->TamponMiktar ?? 0));
            $consume = min($heldCapacity, $remaining);
            if ($consume <= 0) {
                continue;
            }

            $this->applyComponentStockConsumption($task, $row, $componentNo, $consume, 0, $reason, 'held');
            $remaining -= $consume;
            $used += $consume;
        }

        if ($remaining > 0) {
            $rows = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $componentNo)
                ->where('Adet', '>', 0)
                ->orderBy('No')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                if ($remaining <= 0) {
                    break;
                }

                $consume = min(max(0, intval($row->Adet ?? 0)), $remaining);
                if ($consume <= 0) {
                    continue;
                }

                $this->applyComponentStockConsumption($task, $row, $componentNo, $consume, 0, $reason, 'held');
                $remaining -= $consume;
                $used += $consume;
            }
        }

        return $used;
    }

    private function consumeFreeQuantityFromStockRows(object $task, int $componentNo, int $quantity, string $reason): int
    {
        $remaining = max(0, $quantity);
        $used = 0;
        if ($remaining <= 0) {
            return 0;
        }

        $rows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('Adet', '>', 0)
            ->where('TamponMiktar', '>', 0)
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $consume = min(
                max(0, intval($row->Adet ?? 0)),
                max(0, intval($row->TamponMiktar ?? 0)),
                $remaining
            );

            if ($consume <= 0) {
                continue;
            }

            $this->applyComponentStockConsumption($task, $row, $componentNo, $consume, $consume, $reason, 'free');
            $remaining -= $consume;
            $used += $consume;
        }

        return $used;
    }

    private function applyComponentStockConsumption(
        object $task,
        object $stockBefore,
        int $componentNo,
        int $quantity,
        int $bufferQuantity,
        string $reason,
        string $stockKind
    ): void {
        $oldQuantity = max(0, intval($stockBefore->Adet ?? 0));
        $oldBuffer = max(0, intval($stockBefore->TamponMiktar ?? 0));
        $newQuantity = max(0, $oldQuantity - max(0, $quantity));
        $newBuffer = max(0, min($newQuantity, $oldBuffer - max(0, $bufferQuantity)));

        DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->update([
            'Adet' => $newQuantity,
            'TamponMiktar' => $newBuffer,
        ]);

        $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();
        $this->logStockMovement($stockBefore, $stockAfter, [
            'movement_type' => 'production_component_consumed_by_parent',
            'source_type' => 'personnel_task_start',
            'source_id' => intval($task->No ?? 0) > 0 ? intval($task->No) : null,
            'order_item_no' => intval($task->SiparisSatirNo ?? 0) > 0 ? intval($task->SiparisSatirNo) : null,
            'order_no' => trim((string) ($task->SiparisNo ?? '')) ?: null,
            'personnel_task_no' => intval($task->No ?? 0) > 0 ? intval($task->No) : null,
            'description' => $reason === 'finished_order_cleanup'
                ? 'İş emri tamamlandığı için elde kalmış tahsisli alt parça stoğu kapatıldı.'
                : 'Üst görev üretime alındığı için alt parça stoğu tüketildi.',
            'metadata' => [
                'parent_component_no' => intval($task->AraUrunAdiNo ?? 0),
                'child_component_no' => $componentNo,
                'consumed_quantity' => max(0, $quantity),
                'consumed_buffer_quantity' => max(0, $bufferQuantity),
                'stock_kind' => $stockKind,
                'reason' => $reason,
            ],
        ]);
    }

    /** Dashboard istatistikleri */
    public function dashboardStats(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $aktifGorevlerQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('BekleyenAdet', '>', 0)
                            ->whereRaw($this->activeProductionApprovalSql());
                    });
            })
            ->whereRaw($this->inProductionApprovalSql());
        $aktifGorevler = $this->whereTaskDue($aktifGorevlerQuery, 'GorevBaslamaTarihi')->count();

        $uretimeHazirQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where('Adet', '>', 0)
            ->whereRaw($this->productionReadyApprovalSql());
        $uretimeHazir = $this->whereTaskDue($uretimeHazirQuery, 'GorevBaslamaTarihi')->count();

        $tamamlanan = DB::table('tbGorevler')
            ->where('PersonelNo', $personelNo)
            ->where('ToplamAdet', '>', 0)
            ->count();

        $bekleyenAdetQuery = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            })
            ->where(function ($query) {
                $query->where('BekleyenAdet', '>', 0)
                    ->orWhereRaw($this->pendingApprovalSql());
            });
        $bekleyenAdet = $this->whereTaskDue($bekleyenAdetQuery, 'GorevBaslamaTarihi')->sum('BekleyenAdet');

        $alinabilir = DB::table('tbBolumHavuz as bh')
            ->join('tbPersonel as p', 'bh.BolumAdiNo', '=', 'p.BolumAdiNo')
            ->where('p.PersonelNo', $personelNo)
            ->where('bh.Adet', '>', 0)
            ->count();

        return response()->json([
            'success' => true,
            'aktifGorevler' => $aktifGorevler,
            'uretimeHazir' => $uretimeHazir,
            'tamamlanan' => $tamamlanan,
            'alinabilir' => $alinabilir,
            'bekleyenAdet' => intval($bekleyenAdet),
        ]);
    }

    /** Aktif görevlerim */
    public function myTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $tasks = DB::select("
            SELECT pg.No,
                   pg.AraUrunAdiNo,
                   pg.Adet,
                   CASE
                       WHEN COALESCE(pg.Adet, 0) > 0 THEN pg.Adet
                       WHEN COALESCE(pg.BekleyenAdet, 0) > 0 AND " . $this->activeProductionApprovalSql('pg.Onay') . " THEN pg.BekleyenAdet
                       ELSE pg.Adet
                   END AS UretilebilirAdet,
                   pg.BekleyenAdet,
                   (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi,
                   IFNULL(pg.Onay, '') AS Onay,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi,
                   COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                   COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
	            WHERE pg.PersonelNo = ?
                  AND (
                      (COALESCE(pg.Adet, 0) > 0 AND " . $this->inProductionApprovalSql('pg.Onay') . ")
                      OR (
                          COALESCE(pg.Adet, 0) <= 0
                          AND COALESCE(pg.BekleyenAdet, 0) > 0
                          AND " . $this->activeProductionApprovalSql('pg.Onay') . "
                      )
                  )
                  AND " . $this->dueTaskDateSql('pg.GorevBaslamaTarihi') . "
	            ORDER BY " . $this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . " DESC, pg.No DESC
	        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($tasks)]);
    }

    /** Görev detay */
    public function taskDetail(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $task = DB::selectOne("
            SELECT pg.*,
                   CASE
                       WHEN COALESCE(pg.Adet, 0) > 0 THEN pg.Adet
                       WHEN COALESCE(pg.BekleyenAdet, 0) > 0 AND " . $this->activeProductionApprovalSql('pg.Onay') . " THEN pg.BekleyenAdet
                       ELSE pg.Adet
                   END AS UretilebilirAdet,
                   (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihiFormatted,
                   IFNULL(pg.Onay, '') AS Onay,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi,
                   COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                   COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
	            WHERE pg.No = ? AND pg.PersonelNo = ?
                  AND (COALESCE(pg.Adet, 0) > 0 OR COALESCE(pg.BekleyenAdet, 0) > 0)
                  AND (COALESCE(pg.BekleyenAdet, 0) > 0 OR " . $this->pendingApprovalSql('pg.Onay') . ")
                  AND " . $this->dueTaskDateSql('pg.GorevBaslamaTarihi') . "
	        ", [$id, $personelNo]);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        return response()->json(['success' => true, 'task' => $this->enrichTaskImage($task)]);
    }

    /** Üretim girişi yap (adet tamamla) */
    public function completeProduction(Request $request, $id)
    {
        $adet = intval($request->input('adet', 0));
        if ($adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz adet']);
        }

        $personelNo = $this->personelNo($request);
        $sonuc = DB::transaction(function () use ($id, $personelNo, $adet) {
            $gorev = DB::table('tbPersonelGorev')
                ->where('No', $id)
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı'];
            }
            if (!$this->isTaskDue($gorev->GorevBaslamaTarihi ?? null)) {
                return ['success' => false, 'message' => 'Bu görev seçilen tarihten önce üretime alınamaz.'];
            }
            if ($this->isProductionReadyApproval($gorev->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev üretime hazır. Önce görevi onaylayıp üretime geçin.'];
            }
            if ($this->isApprovedValue($gorev->Onay ?? null)) {
                return ['success' => false, 'message' => 'Bu görev tamamlanmış görünüyor.'];
            }

            $linkedOrderBefore = intval($gorev->SiparisSatirNo ?? 0) > 0 && Schema::hasTable('tbSiparisSatir')
                ? DB::table('tbSiparisSatir')->where('No', intval($gorev->SiparisSatirNo))->first()
                : null;
            $uretilebilirAdet = max(0, intval($gorev->Adet ?? 0));
            $bekleyenAdet = max(0, intval($gorev->BekleyenAdet ?? 0));
            $aktifBekleyenDevam = $uretilebilirAdet <= 0
                && $bekleyenAdet > 0
                && $this->isActiveProductionApproval($gorev->Onay ?? null);
            $tamamlanabilirAdet = $aktifBekleyenDevam ? $bekleyenAdet : $uretilebilirAdet;

            if ($tamamlanabilirAdet <= 0) {
                return ['success' => false, 'message' => 'Bu görev için üretilebilir adet yok. Parça/stok bekleniyor.'];
            }

            if ($adet > $tamamlanabilirAdet) {
                return ['success' => false, 'message' => 'Girilen adet üretilebilir adetten fazla olamaz.'];
            }

            $gerceklesenAdet = $adet;
            $yeniAdet = $aktifBekleyenDevam ? 0 : max(0, $uretilebilirAdet - $gerceklesenAdet);
            $yeniBekleyenAdet = $aktifBekleyenDevam
                ? max(0, $bekleyenAdet - $gerceklesenAdet)
                : $bekleyenAdet;
            $taskCompleted = $yeniAdet <= 0 && $yeniBekleyenAdet <= 0;
            $finishedAt = now()->format('d/m/Y H:i');
            $isFreeStockProduction = $this->taskCompletesFreeStockTarget(
                $linkedOrderBefore,
                intval($gorev->AraUrunAdiNo ?? 0)
            );
            $isOrderTrackedProduction = intval($gorev->SiparisSatirNo ?? 0) > 0 && !$isFreeStockProduction;
            $bufferIncrease = $isOrderTrackedProduction ? 0 : $gerceklesenAdet;

            app(LegacyWorkOrderWriter::class)->insertLegacyWorkOrder([
                'UrunIDNo' => $gorev->UrunIDNo,
                'SiparisSatirNo' => $gorev->SiparisSatirNo ?? null,
                'SiparisNo' => $gorev->SiparisNo ?? null,
                'GorevBaslamaTarihi' => $gorev->GorevBaslamaTarihi,
                'GorevBitisTarihi' => $finishedAt,
                'ToplamAdet' => $gerceklesenAdet,
                'BolumAdiNo' => $gorev->BolumAdiNo,
                'PersonelNo' => $personelNo,
                'Performans' => $this->performanceMinutes($gorev->GorevBaslamaTarihi ?? null, $finishedAt),
                'AraUrunAdiNo' => $gorev->AraUrunAdiNo,
            ]);

            if ($taskCompleted) {
                DB::table('tbPersonelGorev')->where('No', $id)->delete();
            } else {
                DB::table('tbPersonelGorev')->where('No', $id)->update([
                    'Adet' => $yeniAdet,
                    'BekleyenAdet' => $yeniBekleyenAdet,
                    'Onay' => 'false',
                ]);
            }

            $stockRow = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $gorev->AraUrunAdiNo)
                ->where('BolumAdiNo', $gorev->BolumAdiNo)
                ->lockForUpdate()
                ->first();
            $stockQuantityBefore = intval($stockRow->Adet ?? 0);
            $bufferQuantityBefore = intval($stockRow->TamponMiktar ?? 0);

            if ($stockRow) {
                DB::table('tbBolumAraStok')
                    ->where('No', $stockRow->No)
                    ->update([
                        'Adet' => intval($stockRow->Adet ?? 0) + $gerceklesenAdet,
                        'TamponMiktar' => intval($stockRow->TamponMiktar ?? 0) + $bufferIncrease,
                    ]);
                $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockRow->No)->first();
            } else {
                $stockRowNo = DB::table('tbBolumAraStok')->insertGetId([
                    'BolumAdiNo' => $gorev->BolumAdiNo,
                    'Adet' => $gerceklesenAdet,
                    'AraUrunAdiNo' => $gorev->AraUrunAdiNo,
                    'UrunIDNo' => $gorev->UrunIDNo,
                    'TamponMiktar' => $bufferIncrease,
                ], 'No');
                $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first();
            }
            $stockQuantityAfter = $stockAfter
                ? intval($stockAfter->Adet ?? 0)
                : $stockQuantityBefore + $gerceklesenAdet;
            $bufferQuantityAfter = $stockAfter
                ? intval($stockAfter->TamponMiktar ?? 0)
                : $bufferQuantityBefore + $bufferIncrease;

            $this->logRequiredStockMovement($stockRow, $stockAfter ?? null, [
                'movement_type' => 'production_stock_in',
                'source_type' => 'personnel_task',
                'source_id' => $id,
                'quantity_before' => $stockQuantityBefore,
                'quantity_delta' => $gerceklesenAdet,
                'quantity_after' => $stockQuantityAfter,
                'buffer_before' => $bufferQuantityBefore,
                'buffer_delta' => $bufferIncrease,
                'buffer_after' => $bufferQuantityAfter,
                'order_item_no' => intval($gorev->SiparisSatirNo ?? 0) > 0 ? intval($gorev->SiparisSatirNo) : null,
                'order_no' => trim((string) ($gorev->SiparisNo ?? '')) ?: null,
                'personnel_task_no' => intval($id),
                'description' => 'Personel üretim girişi ile stok artırıldı.',
                'metadata' => [
                    'completed_quantity' => $gerceklesenAdet,
                    'remaining_quantity' => $yeniAdet + $yeniBekleyenAdet,
                    'personnel_no' => $personelNo,
                    'reserved_for_order' => $isOrderTrackedProduction,
                    'free_stock_production' => $isFreeStockProduction,
                ],
            ]);

            // ─── Hayalet sipariş önleme: Görev tamamlandıysa bağlı siparişi kapat ───
            $sipSatirNo = intval($gorev->SiparisSatirNo ?? 0);
            $orderProductionFinished = false;
            $passiveContinuingClose = null;
            if ($taskCompleted && $sipSatirNo > 0) {
                $kalanHavuz = Schema::hasTable('tbBolumHavuz')
                    ? intval(DB::table('tbBolumHavuz')->where('SiparisSatirNo', $sipSatirNo)->count())
                    : 0;

                $kalanAktifGorev = intval(DB::table('tbPersonelGorev')
                    ->where('SiparisSatirNo', $sipSatirNo)
                    ->where(function ($q) {
                        $q->where('Adet', '>', 0)
                          ->orWhere('BekleyenAdet', '>', 0);
                    })->count());

                if ($kalanHavuz <= 0 && $kalanAktifGorev <= 0) {
                    $siparis = Schema::hasTable('tbSiparisSatir')
                        ? DB::table('tbSiparisSatir')
                        ->where('No', $sipSatirNo)
                        ->whereIn('Durum', ['IsEmriVerildi', 'PasifDevamEden'])
                        ->lockForUpdate()
                        ->first()
                        : null;

                    if ($siparis) {
                        if (($siparis->Durum ?? '') === 'PasifDevamEden') {
                            $passiveContinuingClose = $this->closePassiveContinuingOrderAfterProduction($siparis, $gorev);
                        } else {
                            $isOzelUretim = str_starts_with($siparis->Musteri ?? '', 'ÖZEL ÜRETİM');
                            DB::table('tbSiparisSatir')->where('No', $sipSatirNo)->update([
                                'Durum' => $isOzelUretim ? 'StokKarsilandi' : 'UretimdenKarsilaniyor',
                                'GuncellemeTarihi' => now(),
                            ]);
                        }
                        $orderProductionFinished = true;
                    }
                }
            }

            $bomService = app(\App\Services\BomService::class);
            $bomService->personelGorevTabloGuncelle(strval($gorev->AraUrunAdiNo));
            $bomService->sonrakiUrunAdetleriniGuncelle(strval($gorev->AraUrunAdiNo), 0, 0);
            if ($orderProductionFinished) {
                $this->cleanupDescendantHeldStocksForFinishedOrder($gorev, $bomService);
            }
            if ($taskCompleted) {
                DB::table('tbPersonelGorev')->where('No', $id)->delete();
            }

            $updatedGorev = DB::table('tbPersonelGorev')->where('No', $id)->first();
            $linkedOrderAfter = intval($gorev->SiparisSatirNo ?? 0) > 0 && Schema::hasTable('tbSiparisSatir')
                ? DB::table('tbSiparisSatir')->where('No', intval($gorev->SiparisSatirNo))->first()
                : null;

            $this->logTaskEvent(
                $taskCompleted ? 'production_completed_full' : 'production_completed_partial',
                $gorev,
                $updatedGorev,
                [
                    'status_before' => $linkedOrderBefore->Durum ?? null,
                    'status_after' => $linkedOrderAfter->Durum ?? null,
                    'next_step_human' => $taskCompleted
                        ? 'Kaydin havuz ve siparis durumu kontrol edilmeli.'
                        : 'Kalan miktar icin uretim devam ediyor.',
                    'context' => [
                        'completed_amount' => $gerceklesenAdet,
                        'remaining_amount' => $yeniAdet + $yeniBekleyenAdet,
                        'linked_order_after' => $this->serializeRecord($linkedOrderAfter),
                        'passive_continuing_close' => $passiveContinuingClose,
                    ],
                ]
            );

            // ─── Telegram Bildirimi ───
            if ($taskCompleted) {
                $this->dispatchTelegramTaskCompletedNotification(
                    $id,
                    $gorev,
                    $gerceklesenAdet
                );
            }

            return [
                'success' => true,
                'message' => $gerceklesenAdet . ' adet üretim kaydedildi.',
                'kalanAdet' => $yeniAdet,
                'uretilebilirAdet' => $yeniAdet,
                'bekleyenAdet' => $yeniBekleyenAdet,
                'toplamAdet' => $yeniAdet + $yeniBekleyenAdet,
                'completed' => $taskCompleted,
            ];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    private function startReadyTaskInTransaction(int $id, int $personelNo, int $requestedQuantity): array
    {
            $gorev = DB::table('tbPersonelGorev')
                ->leftJoin('tbUrunler', 'tbPersonelGorev.UrunIDNo', '=', 'tbUrunler.No')
                ->leftJoin('tbAraUrun', 'tbPersonelGorev.AraUrunAdiNo', '=', 'tbAraUrun.No')
                ->leftJoin('tbBolum', 'tbPersonelGorev.BolumAdiNo', '=', 'tbBolum.No')
                ->leftJoin('tbPersonel', 'tbPersonelGorev.PersonelNo', '=', 'tbPersonel.PersonelNo')
                ->where('tbPersonelGorev.No', $id)
                ->where('tbPersonelGorev.PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first([
                    'tbPersonelGorev.*',
                    'tbUrunler.UrunID',
                    'tbUrunler.SistemAdi',
                    'tbAraUrun.AraUrunAdi',
                    'tbBolum.BolumAdi',
                    DB::raw("CONCAT(tbPersonel.Ad, ' ', tbPersonel.Soyad) AS PersonelAdi"),
                ]);

        if (!$gorev) {
            return ['success' => false, 'message' => 'Görev bulunamadı.', 'status' => 404];
        }
        if (!$this->isTaskDue($gorev->GorevBaslamaTarihi ?? null)) {
            return ['success' => false, 'message' => 'Bu görev seçilen tarihten önce üretime alınamaz.', 'status' => 422];
        }
        if (intval($gorev->Adet ?? 0) <= 0) {
            return ['success' => false, 'message' => 'Bu görev henüz üretime hazır değil; parça/stok bekliyor.', 'status' => 422];
        }
        if ($this->isApprovedValue($gorev->Onay ?? null)) {
            return ['success' => false, 'message' => 'Bu görev tamamlanmış görünüyor.', 'status' => 422];
        }
        if (!$this->isProductionReadyApproval($gorev->Onay ?? null)) {
            return ['success' => true, 'message' => 'Görev zaten üretimde.', 'status' => 200];
        }

        $bomService = app(BomService::class);
        $readyQuantity = max(0, intval($gorev->Adet ?? 0));
        $waitingQuantity = max(0, intval($gorev->BekleyenAdet ?? 0));
        $startQuantity = $requestedQuantity > 0 ? $requestedQuantity : $readyQuantity;

        if ($startQuantity <= 0) {
            return ['success' => false, 'message' => 'Üretime alınacak adet geçersiz.', 'status' => 422];
        }

        if ($startQuantity > $readyQuantity) {
            return ['success' => false, 'message' => 'Girilen adet hazır görev adedinden fazla olamaz.', 'status' => 422];
        }

        $remainingReadyQuantity = max(0, $readyQuantity - $startQuantity);
        $remainingWaitingQuantity = $waitingQuantity;
        $stockConsumption = $this->consumeDirectChildStocksForTask($gorev, $startQuantity, $bomService);
        if (!($stockConsumption['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $stockConsumption['message'] ?? 'Alt parça stoğu tüketilemedi.',
                'status' => 422,
            ];
        }

        $startedAt = now()->format('d/m/Y H:i');
        DB::table('tbPersonelGorev')->where('No', $id)->update([
            'Adet' => $startQuantity,
            'BekleyenAdet' => 0,
            'Onay' => 'false',
            'GorevBaslamaTarihi' => $startedAt,
        ]);

        $splitTaskNo = null;
        $splitTaskAfter = null;
        if (($remainingReadyQuantity + $remainingWaitingQuantity) > 0) {
            $splitTaskNo = DB::table('tbPersonelGorev')->insertGetId($this->personnelTaskSplitPayload($gorev, [
                'Adet' => $remainingReadyQuantity,
                'BekleyenAdet' => $remainingWaitingQuantity,
                'Onay' => 'hazir',
            ]), 'No');

            $splitTaskAfter = $this->rebalanceReadyPersonnelTask(intval($splitTaskNo), $bomService);
        }

        $updatedGorev = DB::table('tbPersonelGorev')->where('No', $id)->first();
        $this->logTaskEvent('personnel_task_started', $gorev, $updatedGorev, [
            'status_before' => 'UretimeHazir',
            'status_after' => 'Uretimde',
            'next_step_human' => 'Personel uretim girisi yapabilir.',
            'context' => [
                'started_at' => $startedAt,
                'accepted_quantity' => $startQuantity,
                'ready_quantity' => $readyQuantity,
                'waiting_quantity' => $waitingQuantity,
                'remaining_ready_quantity' => intval($splitTaskAfter->Adet ?? $remainingReadyQuantity),
                'remaining_waiting_quantity' => intval($splitTaskAfter->BekleyenAdet ?? $remainingWaitingQuantity),
                'split_task_no' => $splitTaskNo,
                'consumed_child_stock' => $stockConsumption['consumed'] ?? [],
            ],
        ]);

        $remainingTotal = intval($splitTaskAfter->Adet ?? $remainingReadyQuantity)
            + intval($splitTaskAfter->BekleyenAdet ?? $remainingWaitingQuantity);
        $message = $startQuantity . ' adet görev üretime alındı.';
        if ($remainingTotal > 0) {
            $message .= ' ' . $remainingTotal . ' adet sonraki parti için açık kaldı.';
        }

        // ─── Telegram Bildirimi: Görev Kabul ───
        $this->dispatchTelegramTaskStartedNotification($id, $gorev, $startQuantity);

        return [
            'success' => true,
            'message' => $message,
            'status' => 200,
            'accepted_quantity' => $startQuantity,
            'remaining_quantity' => $remainingTotal,
            'split_task_no' => $splitTaskNo,
        ];
    }

    /** Üretime hazır görevi personel onayı ile başlat */
    public function startTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);
        $requestedQuantity = intval($request->input('adet', 0));

        $sonuc = DB::transaction(fn () => $this->startReadyTaskInTransaction(
            intval($id),
            $personelNo,
            $requestedQuantity
        ));

        return response()->json(
            array_intersect_key($sonuc, array_flip([
                'success',
                'message',
                'accepted_quantity',
                'remaining_quantity',
                'split_task_no',
            ])) ?: ['success' => false, 'message' => 'İşlem tamamlanamadı.'],
            $sonuc['status'] ?? (($sonuc['success'] ?? false) ? 200 : 422)
        );
    }

    /** Görsel olarak gruplanan hazır görevleri gerçek kayıtlar üzerinde sırayla başlat */
    public function startTaskGroup(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);

        $taskIds = collect($request->input('task_ids', []))
            ->map(fn ($id) => intval($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $requestedQuantity = intval($request->input('adet', 0));
        $requestedGroupKey = trim((string) $request->input('group_key', ''));

        if (empty($taskIds)) {
            return response()->json(['success' => false, 'message' => 'Görev grubu bulunamadı.'], 422);
        }

        try {
            $sonuc = DB::transaction(function () use ($taskIds, $personelNo, $requestedQuantity, $requestedGroupKey) {
                $tasks = DB::table('tbPersonelGorev')
                    ->whereIn('No', $taskIds)
                    ->where('PersonelNo', $personelNo)
                    ->lockForUpdate()
                    ->get();

                if ($tasks->count() !== count($taskIds)) {
                    return ['success' => false, 'message' => 'Görev grubundaki bazı kayıtlar bulunamadı.', 'status' => 404];
                }

                $groupKeys = [];
                foreach ($tasks as $task) {
                    if (!$this->isTaskDue($task->GorevBaslamaTarihi ?? null)) {
                        return ['success' => false, 'message' => 'Bu grupta tarihi gelmemiş görev var.', 'status' => 422];
                    }
                    if (intval($task->Adet ?? 0) <= 0 || !$this->isProductionReadyApproval($task->Onay ?? null)) {
                        return ['success' => false, 'message' => 'Bu grupta üretime hazır olmayan görev var.', 'status' => 422];
                    }

                    $groupKey = $this->readyTaskVisualGroupKey($task);
                    if ($groupKey === '') {
                        return ['success' => false, 'message' => 'Görev grubu doğrulanamadı.', 'status' => 422];
                    }
                    if ($requestedGroupKey !== '' && $groupKey !== $requestedGroupKey) {
                        return ['success' => false, 'message' => 'Görev grubu güncel değil, lütfen listeyi yenileyin.', 'status' => 422];
                    }

                    $groupKeys[$groupKey] = true;
                }

                if (count($groupKeys) !== 1) {
                    return ['success' => false, 'message' => 'Farklı görevler tek grup olarak başlatılamaz.', 'status' => 422];
                }

                $tasks = $tasks
                    ->sortBy(fn ($task) => $this->taskDateKey($task->GorevBaslamaTarihi ?? '') . '-' . str_pad((string) intval($task->No ?? 0), 10, '0', STR_PAD_LEFT))
                    ->values();
                $readyTotal = intval($tasks->sum(fn ($task) => max(0, intval($task->Adet ?? 0))));
                $quantityToStart = $requestedQuantity > 0 ? $requestedQuantity : $readyTotal;

                if ($quantityToStart <= 0) {
                    return ['success' => false, 'message' => 'Üretime alınacak adet geçersiz.', 'status' => 422];
                }
                if ($quantityToStart > $readyTotal) {
                    return ['success' => false, 'message' => 'Girilen adet hazır görev toplamından fazla olamaz.', 'status' => 422];
                }

                $remainingToStart = $quantityToStart;
                $acceptedQuantity = 0;
                $startedTaskNos = [];
                $splitTaskNos = [];

                foreach ($tasks as $task) {
                    if ($remainingToStart <= 0) {
                        break;
                    }

                    $taskReady = max(0, intval($task->Adet ?? 0));
                    $startQuantity = min($taskReady, $remainingToStart);
                    $result = $this->startReadyTaskInTransaction(intval($task->No ?? 0), $personelNo, $startQuantity);

                    if (!($result['success'] ?? false)) {
                        throw new \RuntimeException($result['message'] ?? 'Görev grubu üretime alınamadı.');
                    }

                    $acceptedQuantity += intval($result['accepted_quantity'] ?? $startQuantity);
                    $remainingToStart -= $startQuantity;
                    $startedTaskNos[] = intval($task->No ?? 0);
                    if (intval($result['split_task_no'] ?? 0) > 0) {
                        $splitTaskNos[] = intval($result['split_task_no']);
                    }
                }

                $remainingQuantity = max(0, $readyTotal - $acceptedQuantity);
                $message = $acceptedQuantity . ' adet görev grubu üretime alındı.';
                if ($remainingQuantity > 0) {
                    $message .= ' ' . $remainingQuantity . ' adet hazır görev açık kaldı.';
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'status' => 200,
                    'accepted_quantity' => $acceptedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'started_task_nos' => $startedTaskNos,
                    'split_task_nos' => $splitTaskNos,
                ];
            });
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage() ?: 'Görev grubu üretime alınamadı.',
            ], 422);
        }

        return response()->json(
            array_intersect_key($sonuc, array_flip([
                'success',
                'message',
                'accepted_quantity',
                'remaining_quantity',
                'started_task_nos',
                'split_task_nos',
            ])) ?: ['success' => false, 'message' => 'İşlem tamamlanamadı.'],
            $sonuc['status'] ?? (($sonuc['success'] ?? false) ? 200 : 422)
        );
    }

    /** Alınabilir görevler (havuzdan) */
    public function availableTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');

        $tasks = DB::select("
            SELECT MAX(bh.No) AS No,
                   bh.AraUrunAdiNo,
                   CASE WHEN IFNULL(au.Yol, '') = '' THEN SUM(COALESCE(bh.Adet, 0)) ELSE MAX(COALESCE(bh.Adet, 0)) END AS Adet,
                   SUM(COALESCE(bh.ToplamAdet, 0)) AS ToplamAdet,
                   CONCAT(IFNULL(MAX(bh.GorevBaslangicTarihi), ''), CASE WHEN IFNULL(MAX(bh.GorevBaslangicSaati), '') <> '' THEN CONCAT(' ', MAX(bh.GorevBaslangicSaati)) ELSE '' END) AS GorevBaslangicTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(MAX(b.BolumAdi),'') AS BolumAdi,
                   IFNULL(MAX(u.UrunID),'') AS UrunAdi,
                   COALESCE(MAX({$componentImageSql}), MAX({$productImageSql}), '') AS Resim,
                   COALESCE(MAX({$componentTypeSql}), 'Ara Mamül') AS UrunCesidi,
                   IFNULL(MAX(bh.Aciklama), '') AS Aciklama,
                   IFNULL(MAX(bh.GorevBaslangicSaati), '') AS GorevBaslangicSaati
            FROM tbBolumHavuz bh
            LEFT JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON bh.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON bh.UrunIDNo = u.No
            WHERE bh.Adet > 0 AND bh.BolumAdiNo = ?
            GROUP BY bh.AraUrunAdiNo, au.AraUrunAdi, au.Yol
            ORDER BY au.AraUrunAdi ASC
        ", [$bolumAdiNo]);

        return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($tasks)]);
    }

    /** Görev al (havuzdan personele aktar) */
    public function takeTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $adet = intval($request->input('adet', 0));
        $sonuc = DB::transaction(function () use ($personelNo, $adet, $id) {
            $bomService = app(\App\Services\BomService::class);
            $personel = DB::table('tbPersonel')
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$personel) {
                return ['success' => false, 'message' => 'Personel bulunamadı'];
            }

            $havuz = DB::table('tbBolumHavuz')
                ->where('No', $id)
                ->lockForUpdate()
                ->first();

            if (!$havuz) {
                return ['success' => false, 'message' => 'Havuz kaydı bulunamadı'];
            }

            if (intval($personel->BolumAdiNo ?? 0) !== intval($havuz->BolumAdiNo ?? 0)) {
                return ['success' => false, 'message' => 'Farklı bölüm görevini alamazsınız.'];
            }

            if ($bomService->hasOpenDescendantWork(strval($havuz->AraUrunAdiNo ?? 0), $bomService->traceContextFromRecord($havuz))) {
                return ['success' => false, 'message' => 'Alt bileşen görevleri tamamlanmadan bu görev alınamaz.'];
            }

            $havuzKayitlari = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($havuz->AraUrunAdiNo ?? 0))
                ->where('BolumAdiNo', intval($havuz->BolumAdiNo ?? 0))
                ->where('Adet', '>', 0)
                ->orderByRaw($this->legacyDateOrderSql('GorevBaslangicTarihi') . ' ASC')
                ->orderBy('No')
                ->lockForUpdate()
                ->get();

            $havuzToplam = intval($havuzKayitlari->sum(fn ($row) => intval($row->Adet ?? 0)));
            $alinacakAdet = $adet;
            if ($alinacakAdet <= 0 || $alinacakAdet > $havuzToplam) {
                $alinacakAdet = $havuzToplam;
            }

            if ($alinacakAdet <= 0) {
                return ['success' => false, 'message' => 'Alınacak görev adedi kalmamış.'];
            }

            $kalanMiktar = $alinacakAdet;
            foreach ($havuzKayitlari as $kayit) {
                if ($kalanMiktar <= 0) {
                    break;
                }

                $kayitAdet = intval($kayit->Adet ?? 0);
                $dusulecek = min($kayitAdet, $kalanMiktar);
                $yeniAdet = max(0, $kayitAdet - $dusulecek);
                $yeniToplam = max(0, intval($kayit->ToplamAdet ?? 0) - $dusulecek);

                if ($yeniToplam <= 0) {
                    DB::table('tbBolumHavuz')->where('No', $kayit->No)->delete();
                } else {
                    DB::table('tbBolumHavuz')->where('No', $kayit->No)->update([
                        'Adet' => $yeniAdet,
                        'ToplamAdet' => $yeniToplam,
                    ]);
                }

                $kalanMiktar -= $dusulecek;
            }

            $taskId = DB::table('tbPersonelGorev')->insertGetId(array_merge([
                'UrunIDNo' => $havuz->UrunIDNo,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'PersonelNo' => $personelNo,
                'Adet' => $alinacakAdet,
                'BekleyenAdet' => 0,
                'Onay' => 'false',
                'AraUrunAdiNo' => $havuz->AraUrunAdiNo,
                'BolumAdiNo' => $havuz->BolumAdiNo,
            ], $bomService->buildTracePayload($bomService->traceContextFromRecord($havuz))));

            $createdTask = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            $this->logTaskEvent('personnel_task_taken', $havuz, $createdTask, [
                'aggregate_type' => intval($createdTask->SiparisSatirNo ?? 0) > 0 ? 'order_item' : 'personnel_task',
                'aggregate_id' => intval($createdTask->SiparisSatirNo ?? 0) > 0
                    ? intval($createdTask->SiparisSatirNo)
                    : intval($createdTask->No ?? $taskId),
                'order_item_no' => intval($createdTask->SiparisSatirNo ?? 0) > 0 ? intval($createdTask->SiparisSatirNo) : null,
                'order_no' => trim((string) ($createdTask->SiparisNo ?? $havuz->SiparisNo ?? '')) ?: null,
                'personnel_task_no' => intval($createdTask->No ?? $taskId),
                'status_before' => 'HavuzdaBekliyor',
                'status_after' => 'Personelde',
                'next_step_human' => 'Personelin uretim girisi yapmasi bekleniyor.',
                'payload_before' => $this->serializeRecord($havuz),
                'payload_after' => $this->serializeRecord($createdTask),
                'context' => [
                    'taken_amount' => $alinacakAdet,
                    'pool_no' => intval($havuz->No ?? 0),
                ],
            ]);

            return ['success' => true, 'message' => $alinacakAdet . ' adet görev alındı.'];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    /** Tamamlanan görevlerim */
    public function completedTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $tasks = DB::select("
            SELECT gr.No,
                   gr.ToplamAdet,
                   gr.ToplamAdet AS Adet,
                   IFNULL(gr.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi,
                   IFNULL(gr.GorevBitisTarihi, '') AS GorevBitisTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbGorevler gr
            LEFT JOIN tbAraUrun au ON gr.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON gr.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON gr.UrunIDNo = u.No
            WHERE gr.PersonelNo = ?
              AND COALESCE(gr.ToplamAdet, 0) > 0
            ORDER BY " . $this->legacyDateOrderSql('gr.GorevBitisTarihi') . " DESC, gr.No DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev raporlarım */
    public function taskReport(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $report = DB::select("
            SELECT IFNULL(au.AraUrunAdi,'Bilinmiyor') AS UrunAdi,
                   SUM(gr.ToplamAdet) AS ToplamUretim,
                   COUNT(*) AS GorevSayisi,
                   DATE_FORMAT(MIN(" . $this->legacyDateOrderSql('gr.GorevBaslamaTarihi') . "), '%d/%m/%Y %H:%i') AS IlkGorev,
                   DATE_FORMAT(MAX(" . $this->legacyDateOrderSql('gr.GorevBitisTarihi') . "), '%d/%m/%Y %H:%i') AS SonGorev
            FROM tbGorevler gr
            LEFT JOIN tbAraUrun au ON gr.AraUrunAdiNo = au.No
            WHERE gr.PersonelNo = ?
              AND COALESCE(gr.ToplamAdet, 0) > 0
            GROUP BY au.AraUrunAdi
            ORDER BY ToplamUretim DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'report' => $report]);
    }

    public function dependencyInfo(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);
        $bomService = app(BomService::class);

        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.No', intval($id))
            ->where('pg.PersonelNo', $personelNo)
            ->select(
                'pg.*',
                DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi")
            )
            ->first();

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        $shortages = $this->dependencyShortagesForTask($task, $bomService);

        return response()->json([
            'success' => true,
            'task' => [
                'no' => intval($task->No ?? 0),
                'component_no' => intval($task->AraUrunAdiNo ?? 0),
                'component_name' => trim((string) ($task->AraUrunAdi ?? '')),
                'department_name' => trim((string) ($task->BolumAdi ?? '')),
                'ready_quantity' => max(0, intval($task->Adet ?? 0)),
                'waiting_quantity' => max(0, intval($task->BekleyenAdet ?? 0)),
                'quantity_checked' => $this->taskQuantityToCheck($task),
            ],
            'has_shortage' => !empty($shortages),
            'shortages' => $shortages,
        ]);
    }

    public function notifyDependency(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $componentNo = intval($request->input('component_no', 0));
        $supplierTaskNo = intval($request->input('supplier_task_no', 0));

        if ($componentNo <= 0 || $supplierTaskNo <= 0) {
            return response()->json(['success' => false, 'message' => 'Bildirim için eksik bağımlılık bilgisi var.'], 422);
        }

        if (!Schema::hasTable('tbIletisim')) {
            return response()->json(['success' => false, 'message' => 'Mesaj tablosu bulunamadı.'], 500);
        }

        $bomService = app(BomService::class);
        $task = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->where('pg.No', intval($id))
            ->where('pg.PersonelNo', $personelNo)
            ->select('pg.*', DB::raw("IFNULL(au.AraUrunAdi, '') as AraUrunAdi"))
            ->first();

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
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
            return response()->json(['success' => false, 'message' => 'Beklenen personel görevi bulunamadı.'], 404);
        }

        $targetPersonnelNo = intval($selectedSupplier['personnel_no'] ?? 0);
        if ($targetPersonnelNo <= 0 || $targetPersonnelNo === $personelNo) {
            return response()->json(['success' => false, 'message' => 'Bu bağımlılık için bildirilecek farklı bir personel yok.'], 422);
        }

        $trackingCode = 'BG-' . intval($task->No ?? 0) . '-' . $supplierTaskNo . '-' . $componentNo;
        $existing = DB::table('tbIletisim')
            ->where('PersonelNo', $targetPersonnelNo)
            ->where('Mesaj', 'like', '%' . $trackingCode . '%')
            ->orderByDesc('MesajNo')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Bu personele bu bekleme bildirimi daha önce gönderilmiş.',
                'already_sent' => true,
            ]);
        }

        $sender = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $target = DB::table('tbPersonel')->where('PersonelNo', $targetPersonnelNo)->first();
        $senderName = trim((string) ($sender->Ad ?? '') . ' ' . (string) ($sender->Soyad ?? ''));
        $senderName = $senderName !== '' ? $senderName : ('Personel #' . $personelNo);
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
            $insert['AdSoyad'] = $senderName;
        }
        if (Schema::hasColumn('tbIletisim', 'Okundu')) {
            $insert['Okundu'] = 0;
        }

        DB::table('tbIletisim')->insert(array_intersect_key($insert, array_flip(Schema::getColumnListing('tbIletisim'))));

        $this->logTaskEvent('dependency_notification_sent', $task, null, [
            'next_step_human' => 'Alt parcayi uretecek personele bildirim gonderildi.',
            'context' => [
                'component_no' => $componentNo,
                'component_name' => $componentName,
                'missing_quantity' => $missingQuantity,
                'supplier_task_no' => $supplierTaskNo,
                'target_personnel_no' => $targetPersonnelNo,
                'tracking_code' => $trackingCode,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => ($selectedSupplier['personnel_name'] ?? 'Personele') . ' bildirim gönderildi.',
            'already_sent' => false,
        ]);
    }

    /** Bana verilen görevler (tbVerilenGorevler) */
    public function assignedToMe(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $this->refreshAssignedTaskReadinessForPersonnel($personelNo);
        $componentImageSql = $this->nullableLegacyColumnSql('tbAraUrun', 'Resim', 'au');
        $productImageSql = $this->nullableLegacyColumnSql('tbUrunler', 'Resim', 'u');
        $componentTypeSql = $this->nullableLegacyColumnSql('tbAraUrun', 'UrunCesidi', 'au');
        $dueTaskSql = $this->dueTaskDateSql('pg.GorevBaslamaTarihi');

        $readyTasks = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->where('pg.PersonelNo', $personelNo)
            ->where('pg.Adet', '>', 0)
            ->whereRaw($this->productionReadyApprovalSql('pg.Onay'));

        $readyTasks = $readyTasks
            ->selectRaw("
                pg.No,
                pg.PersonelNo,
                pg.UrunIDNo,
                pg.AraUrunAdiNo,
                pg.BolumAdiNo,
                pg.SiparisSatirNo,
                IFNULL(pg.SiparisNo, '') AS SiparisNo,
                pg.Adet,
                pg.BekleyenAdet,
                (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                IFNULL(pg.GorevBaslamaTarihi, '') AS GorevTarihi,
                IFNULL(au.AraUrunAdi, '') AS AraUrunAdi,
                IFNULL(u.UrunID, '') AS UrunAdi,
                COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi,
                IFNULL(b.BolumAdi, '') AS BolumAdi,
                '' AS Aciklama,
                'Admin' AS Veren,
                CASE WHEN {$dueTaskSql} THEN 'Üretime Hazır' ELSE 'Planlandı' END AS Durum,
                CASE WHEN {$dueTaskSql} THEN 1 ELSE 0 END AS Baslatilabilir,
                CASE WHEN {$dueTaskSql} THEN '' ELSE 'Görev tarihi gelmedi.' END AS BeklemeNedeni,
                CASE WHEN {$dueTaskSql} THEN 'Görevi Kabul Et' ELSE 'Tarihi bekliyor' END AS ButonMetni,
                'personel_gorev' AS Kaynak
            ")
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
            ->orderByDesc('pg.No')
            ->get();

        $waitingTasks = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->where('pg.PersonelNo', $personelNo)
            ->whereRaw('COALESCE(pg.Adet, 0) <= 0')
            ->where('pg.BekleyenAdet', '>', 0)
            ->whereRaw($this->assignedWaitingApprovalSql('pg.Onay'));

        $waitingTasks = $waitingTasks
            ->selectRaw("
                pg.No,
                pg.PersonelNo,
                pg.UrunIDNo,
                pg.AraUrunAdiNo,
                pg.BolumAdiNo,
                pg.SiparisSatirNo,
                IFNULL(pg.SiparisNo, '') AS SiparisNo,
                pg.Adet,
                pg.BekleyenAdet,
                (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                IFNULL(pg.GorevBaslamaTarihi, '') AS GorevTarihi,
                IFNULL(au.AraUrunAdi, '') AS AraUrunAdi,
                IFNULL(u.UrunID, '') AS UrunAdi,
                COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                COALESCE({$componentTypeSql}, 'Ara Mamül') AS UrunCesidi,
                IFNULL(b.BolumAdi, '') AS BolumAdi,
                '' AS Aciklama,
                'Admin' AS Veren,
                CASE WHEN {$dueTaskSql} THEN 'Bekliyor' ELSE 'Planlandı' END AS Durum,
                0 AS Baslatilabilir,
                CASE WHEN {$dueTaskSql} THEN '' ELSE 'Görev tarihi gelmedi.' END AS BeklemeNedeni,
                CASE WHEN {$dueTaskSql} THEN 'Bekliyor' ELSE 'Tarihi bekliyor' END AS ButonMetni,
                'personel_gorev' AS Kaynak
            ")
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
            ->orderByDesc('pg.No')
            ->get();

        $assignedPersonnelTasks = $this->groupAssignedReadyTasks(
            $this->enrichAssignedTaskAvailability($readyTasks->concat($waitingTasks)->values())
        );

        if (!Schema::hasTable('tbVerilenGorevler') || !Schema::hasColumn('tbVerilenGorevler', 'PersonelNo')) {
            return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($assignedPersonnelTasks)]);
        }

        $hasLegacyDate = Schema::hasColumn('tbVerilenGorevler', 'GorevTarihi');
        $hasComponentNo = Schema::hasColumn('tbVerilenGorevler', 'AraUrunAdiNo');
        $dateExpression = $hasLegacyDate
            ? "IFNULL(vg.GorevTarihi, '')"
            : "CONCAT(IFNULL(vg.GorevBaslangicTarihi, ''), CASE WHEN IFNULL(vg.GorevBaslangicSaati, '') <> '' THEN CONCAT(' ', vg.GorevBaslangicSaati) ELSE '' END)";
        $componentNameExpression = $hasComponentNo
            ? "COALESCE(au.AraUrunAdi, auLegacy.AraUrunAdi, u.UrunID, '')"
            : "COALESCE(auLegacy.AraUrunAdi, u.UrunID, '')";
        $componentNoExpression = $hasComponentNo
            ? "COALESCE(vg.AraUrunAdiNo, vg.UrunIDNo)"
            : "vg.UrunIDNo";

        $query = DB::table('tbVerilenGorevler as vg')
            ->leftJoin('tbAraUrun as auLegacy', 'vg.UrunIDNo', '=', 'auLegacy.No')
            ->leftJoin('tbUrunler as u', 'vg.UrunIDNo', '=', 'u.No');

        if ($hasComponentNo) {
            $query->leftJoin('tbAraUrun as au', 'vg.AraUrunAdiNo', '=', 'au.No');
        }

        $legacyTasks = $query
            ->where('vg.PersonelNo', $personelNo)
            ->selectRaw("vg.No, {$componentNoExpression} AS AraUrunAdiNo, vg.ToplamAdet, vg.ToplamAdet AS Adet, {$dateExpression} AS GorevTarihi, {$componentNameExpression} AS AraUrunAdi, '' AS UrunAdi, '' AS Resim, 'Ara Mamül' AS UrunCesidi, '' AS BolumAdi, IFNULL(vg.Aciklama, '') AS Aciklama, 'Sistem' AS Veren, 'Bekliyor' AS Durum, 0 AS Baslatilabilir, 'verilen_gorev' AS Kaynak")
            ->orderByDesc('vg.No')
            ->limit(100)
            ->get();

        $tasks = $assignedPersonnelTasks->concat($legacyTasks)->values();

        return response()->json(['success' => true, 'tasks' => $this->enrichTaskImages($tasks)]);
    }

    /** Mesajlar (tbIletisim) */
    public function messages(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);
        $hasOkundu = Schema::hasColumn('tbIletisim', 'Okundu');
        $hasAdSoyad = Schema::hasColumn('tbIletisim', 'AdSoyad');
        $hasSaat = Schema::hasColumn('tbIletisim', 'Saat');

        $senderExpression = $hasAdSoyad
            ? "COALESCE(NULLIF(m.AdSoyad, ''), CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')), 'Sistem')"
            : "COALESCE(NULLIF(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')), ' '), 'Sistem')";
        $okunduExpression = $hasOkundu ? 'm.Okundu' : '0';
        $saatExpression = $hasSaat ? "IFNULL(m.Saat, '')" : "''";

        $messages = DB::table('tbIletisim as m')
            ->leftJoin('tbPersonel as p', 'm.PersonelNo', '=', 'p.PersonelNo')
            ->where(function ($q) use ($personelNo, $bolumAdiNo) {
                $q->where('m.PersonelNo', $personelNo);
                if ($bolumAdiNo > 0) {
                    $q->orWhere('m.BolumAdiNo', $bolumAdiNo);
                }
            })
            ->selectRaw("m.MesajNo, m.Mesaj, m.Tarih, {$saatExpression} AS Saat, {$okunduExpression} AS Okundu, {$senderExpression} AS GonderenAdSoyad")
            ->orderByDesc('m.MesajNo')
            ->limit(50)
            ->get();

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    public function unreadMessageCount(Request $request)
    {
        $personelNo = $this->personelNo($request);
        if ($personelNo <= 0 || !Schema::hasTable('tbIletisim') || !Schema::hasColumn('tbIletisim', 'Okundu')) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
                'latest_message_no' => null,
                'latest_preview' => '',
            ]);
        }

        $unreadQuery = DB::table('tbIletisim')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) {
                $query->whereNull('Okundu')
                    ->orWhere('Okundu', 0)
                    ->orWhere('Okundu', '0');
            });

        $count = (clone $unreadQuery)->count();
        $latest = (clone $unreadQuery)
            ->orderByDesc('MesajNo')
            ->first(['MesajNo', 'Mesaj']);
        $preview = trim((string) ($latest->Mesaj ?? ''));
        $preview = preg_replace('/\s+/u', ' ', $preview) ?: '';

        return response()->json([
            'success' => true,
            'unread_count' => intval($count),
            'latest_message_no' => $latest ? intval($latest->MesajNo ?? 0) : null,
            'latest_preview' => function_exists('mb_substr') ? mb_substr($preview, 0, 120) : substr($preview, 0, 120),
        ]);
    }

    public function markMessagesRead(Request $request)
    {
        $personelNo = $this->personelNo($request);
        if ($personelNo <= 0 || !Schema::hasTable('tbIletisim') || !Schema::hasColumn('tbIletisim', 'Okundu')) {
            return response()->json(['success' => true, 'updated' => 0]);
        }

        $updated = DB::table('tbIletisim')
            ->where('PersonelNo', $personelNo)
            ->where(function ($query) {
                $query->whereNull('Okundu')
                    ->orWhere('Okundu', 0)
                    ->orWhere('Okundu', '0');
            })
            ->update(['Okundu' => 1]);

        return response()->json(['success' => true, 'updated' => intval($updated)]);
    }

    /** Mesaj gönder */
    public function sendMessage(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $mesaj = trim((string) $request->input('mesaj', ''));
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        if (empty($mesaj)) {
            return response()->json(['success' => false, 'message' => 'Mesaj boş olamaz.'], 422);
        }

        $insert = [
            'PersonelNo' => $personelNo,
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
            'Mesaj' => $mesaj,
            'Tarih' => now()->format('d/m/Y'),
        ];

        if (Schema::hasColumn('tbIletisim', 'Saat')) {
            $insert['Saat'] = now()->format('H:i');
        }
        if (Schema::hasColumn('tbIletisim', 'Mail')) {
            $insert['Mail'] = $personel->Mail ?? null;
        }
        if (Schema::hasColumn('tbIletisim', 'AdSoyad')) {
            $insert['AdSoyad'] = trim((string) ($personel->Ad ?? '') . ' ' . (string) ($personel->Soyad ?? ''));
        }

        DB::table('tbIletisim')->insert($insert);

        return response()->json(['success' => true, 'message' => 'Mesaj gönderildi.']);
    }

    /** Mesaj sil (E7: deleteMesaj.aspx karşılığı) */
    public function deleteMessage(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);
        $deleted = DB::table('tbIletisim')
            ->where('MesajNo', $id)
            ->where('PersonelNo', $personelNo)
            ->delete();
        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Mesaj silindi.']);
        }
        return response()->json(['success' => false, 'message' => 'Mesaj bulunamadı.'], 404);
    }

    /**
     * Personel görevini sil → havuza geri aktarma (E5: UretimPlanlama.aspx.cs SilGorev)
     * ASP.NET akışı gibi üretilebilir + bekleyen açık miktarı havuza iade eder.
     */
    public function deleteTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);

        $sonuc = DB::transaction(function () use ($id, $personelNo) {
            $gorev = DB::table('tbPersonelGorev')
                ->where('No', $id)
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }
            if (!$this->isTaskDue($gorev->GorevBaslamaTarihi ?? null)) {
                return ['success' => false, 'message' => 'Bu görev seçilen tarihten önce personel ekranından değiştirilemez.'];
            }

            $araUrunAdiNo = intval($gorev->AraUrunAdiNo);
            $bekleyenAdet = max(0, intval($gorev->BekleyenAdet ?? 0));
            $iadeAdet = max(0, intval($gorev->Adet ?? 0)) + $bekleyenAdet;

            // Görevi sil
            DB::table('tbPersonelGorev')->where('No', $id)->delete();

            $bomService = app(\App\Services\BomService::class);
            if ($iadeAdet > 0) {
                $tamponDusumleri = [];
                $bomService->minAraUrunUretimiDenetle(
                    intval($gorev->UrunIDNo ?? 0),
                    '',
                    strval($araUrunAdiNo),
                    $iadeAdet,
                    'Personel görev iptal — havuza iade',
                    'StokHaric',
                    $tamponDusumleri,
                    $bomService->traceContextFromRecord($gorev)
                );
            }

            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            // ─── Hayalet sipariş önleme: Bağlı siparişi kontrol et ───
            $sipSatirNo = intval($gorev->SiparisSatirNo ?? 0);
            $linkedOrderBefore = $sipSatirNo > 0 && Schema::hasTable('tbSiparisSatir')
                ? DB::table('tbSiparisSatir')->where('No', $sipSatirNo)->first()
                : null;
            if ($sipSatirNo > 0) {
                $kalanHavuz = intval(DB::table('tbBolumHavuz')
                    ->where('SiparisSatirNo', $sipSatirNo)->count());
                $kalanGorev = intval(DB::table('tbPersonelGorev')
                    ->where('SiparisSatirNo', $sipSatirNo)
                    ->where(function ($query) {
                        $query->where('Adet', '>', 0)
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->where(function ($query) {
                        $query->where('BekleyenAdet', '>', 0)
                            ->orWhereRaw($this->pendingApprovalSql());
                    })
                    ->count());

                if ($kalanHavuz <= 0 && $kalanGorev <= 0) {
                    DB::table('tbSiparisSatir')
                        ->where('No', $sipSatirNo)
                        ->where('Durum', 'IsEmriVerildi')
                        ->update([
                            'Durum' => 'UretimBekliyor',
                            'GorevNo' => null,
                            'IsEmriTarihi' => null,
                            'GuncellemeTarihi' => now(),
                        ]);
                }
            }

            $linkedOrderAfter = $sipSatirNo > 0 && Schema::hasTable('tbSiparisSatir')
                ? DB::table('tbSiparisSatir')->where('No', $sipSatirNo)->first()
                : null;

            $this->logTaskEvent('personnel_task_deleted', $gorev, null, [
                'status_before' => $linkedOrderBefore->Durum ?? null,
                'status_after' => $linkedOrderAfter->Durum ?? null,
                'next_step_human' => $iadeAdet > 0
                    ? 'Kalan miktar havuza geri dondu; yeniden planlama yapilabilir.'
                    : 'Tamamlanan miktar stokta birakildi, kayit kontrol edilmeli.',
                'context' => [
                    'returned_amount' => $iadeAdet,
                    'linked_order_after' => $this->serializeRecord($linkedOrderAfter),
                ],
            ]);

            return [
                'success' => true,
                'message' => $iadeAdet > 0
                    ? "Görev silindi ve {$iadeAdet} adet havuza geri aktarıldı."
                    : 'Görev silindi. Tamamlanan üretim stokta bırakıldı.',
            ];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    /**
     * Genel "üretimde iken iptal edilen stoğa ekle" fonksiyonu (E6).
     * Diğer controller'lardan da çağrılabilir.
     */
    public static function iptalEdilenStogaEkle(int $araUrunAdiNo, int $uretilenAdet): void
    {
        if ($uretilenAdet <= 0) return;

        $stok = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $araUrunAdiNo)
            ->first();

        if ($stok) {
            DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->update(['Adet' => intval($stok->Adet) + $uretilenAdet]);
            $after = DB::table('tbBolumAraStok')->where('No', $stok->No)->first();

            try {
                app(StockMovementLogger::class)->logChange($stok, $after, [
                    'movement_type' => 'cancelled_production_stock_in',
                    'source_type' => 'production_cancel',
                    'source_id' => $araUrunAdiNo,
                    'description' => 'İptal edilen üretimden kalan adet stoğa eklendi.',
                    'metadata' => [
                        'produced_quantity' => $uretilenAdet,
                    ],
                ]);
            } catch (\Throwable) {
                // Stok ekstresi ana iptal akisini bozmasin.
            }
        }
    }

    private function firstFilledString(array $values): string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function resolveTelegramProductName(object $gorev): string
    {
        $urunAdi = $this->firstFilledString([
            $gorev->UrunAdi ?? null,
            $gorev->SistemAdi ?? null,
            $gorev->UrunID ?? null,
        ]);

        $urunIdNo = intval($gorev->UrunIDNo ?? 0);
        if ($urunAdi === '' && $urunIdNo > 0 && Schema::hasTable('tbUrunler')) {
            $columns = array_values(array_filter(
                ['SistemAdi', 'UrunAdi', 'UrunID'],
                fn (string $column) => Schema::hasColumn('tbUrunler', $column)
            ));

            if (!empty($columns)) {
                $product = DB::table('tbUrunler')->where('No', $urunIdNo)->first($columns);
                if ($product) {
                    $urunAdi = $this->firstFilledString([
                        $product->SistemAdi ?? null,
                        $product->UrunAdi ?? null,
                        $product->UrunID ?? null,
                    ]);
                }
            }
        }

        if ($urunAdi === '') {
            $araUrunAdi = $this->resolveTelegramComponentName($gorev);
            if ($araUrunAdi !== '') {
                return $araUrunAdi;
            }
        }

        return $urunAdi;
    }

    private function resolveTelegramComponentName(object $gorev): string
    {
        $araUrunAdi = $this->firstFilledString([
            $gorev->AraUrunAdi ?? null,
            $gorev->ComponentName ?? null,
        ]);

        $araUrunAdiNo = intval($gorev->AraUrunAdiNo ?? 0);
        if ($araUrunAdi === '' && $araUrunAdiNo > 0 && Schema::hasTable('tbAraUrun')) {
            $araUrunAdi = (string) (
                DB::table('tbAraUrun')->where('No', $araUrunAdiNo)->value('AraUrunAdi') ?? ''
            );
        }

        return trim($araUrunAdi);
    }

    private function resolveTelegramPersonnelName(object $gorev): string
    {
        $personelAdi = $this->firstFilledString([
            $gorev->PersonelAdi ?? null,
            $gorev->AdSoyad ?? null,
        ]);

        $personelNo = intval($gorev->PersonelNo ?? 0);
        if ($personelAdi === '' && $personelNo > 0 && Schema::hasTable('tbPersonel')) {
            $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
            if ($personel) {
                $personelAdi = $this->firstFilledString([
                    trim((string) ($personel->Ad ?? '') . ' ' . (string) ($personel->Soyad ?? '')),
                    $personel->Ad ?? null,
                    $personel->Soyad ?? null,
                ]);
            }
        }

        if ($personelAdi === '' && $personelNo > 0 && Schema::hasTable('users') && Schema::hasColumn('users', 'personnel_no')) {
            $columns = array_values(array_filter(
                ['name', 'surname', 'email'],
                fn (string $column) => Schema::hasColumn('users', $column)
            ));
            $user = !empty($columns)
                ? DB::table('users')->where('personnel_no', $personelNo)->first($columns)
                : null;
            if ($user) {
                $personelAdi = $this->firstFilledString([
                    trim((string) ($user->name ?? '') . ' ' . (string) ($user->surname ?? '')),
                    $user->name ?? null,
                    $user->email ?? null,
                ]);
            }
        }

        return $personelAdi;
    }

    private function resolveTelegramDepartmentName(object $gorev): string
    {
        $bolumAdi = $this->firstFilledString([
            $gorev->BolumAdi ?? null,
            $gorev->DepartmentName ?? null,
        ]);

        $bolumAdiNo = intval($gorev->BolumAdiNo ?? 0);
        if ($bolumAdi === '' && $bolumAdiNo > 0 && Schema::hasTable('tbBolum')) {
            $bolumAdi = (string) (
                DB::table('tbBolum')->where('No', $bolumAdiNo)->value('BolumAdi') ?? ''
            );
        }

        return trim($bolumAdi);
    }

    private function dispatchTelegramTaskCompletedNotification(
        int $taskNo,
        object $gorev,
        int $gerceklesenAdet
    ): void {
        try {
            $telegram = app(TelegramNotificationService::class);
            if (!$telegram->isEnabled() || !$telegram->isTaskCompletedNotificationEnabled()) {
                return;
            }

            $existing = TelegramNotificationLog::where('event_type', 'task_completed')
                ->where('task_no', $taskNo)
                ->first();
            if ($existing) {
                return;
            }

            $urunAdi = $this->resolveTelegramProductName($gorev);
            $araUrunAdi = $this->resolveTelegramComponentName($gorev);
            $personelAdi = $this->resolveTelegramPersonnelName($gorev);
            $bolumAdi = $this->resolveTelegramDepartmentName($gorev);

            $messageBody = $telegram->buildTaskCompletedMessage([
                'task_no' => $taskNo,
                'product_name' => $urunAdi,
                'component_name' => $araUrunAdi,
                'personnel_name' => $personelAdi,
                'department_name' => $bolumAdi,
                'completed_quantity' => $gerceklesenAdet,
                'order_no' => $gorev->SiparisNo ?? null,
                'order_item_no' => $gorev->SiparisSatirNo ?? null,
            ]);

            $log = TelegramNotificationLog::create([
                'event_type' => 'task_completed',
                'task_no' => $taskNo,
                'order_no' => $gorev->SiparisNo ? (int) $gorev->SiparisNo : null,
                'order_item_no' => $gorev->SiparisSatirNo ? (int) $gorev->SiparisSatirNo : null,
                'message_body' => $messageBody,
                'status' => 'pending',
            ]);

            SendTelegramNotificationJob::dispatch($log->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function dispatchTelegramTaskStartedNotification(
        int $taskNo,
        object $gorev,
        int $acceptedQuantity
    ): void {
        try {
            $telegram = app(TelegramNotificationService::class);
            if (!$telegram->isEnabled() || !$telegram->isTaskStartedNotificationEnabled()) {
                return;
            }

            $existing = TelegramNotificationLog::where('event_type', 'task_started')
                ->where('task_no', $taskNo)
                ->first();
            if ($existing) {
                return;
            }

            $urunAdi = $this->resolveTelegramProductName($gorev);
            $araUrunAdi = $this->resolveTelegramComponentName($gorev);
            $personelAdi = $this->resolveTelegramPersonnelName($gorev);
            $bolumAdi = $this->resolveTelegramDepartmentName($gorev);

            $messageBody = $telegram->buildTaskStartedMessage([
                'task_no' => $taskNo,
                'product_name' => $urunAdi,
                'component_name' => $araUrunAdi,
                'personnel_name' => $personelAdi,
                'department_name' => $bolumAdi,
                'accepted_quantity' => $acceptedQuantity,
                'order_no' => $gorev->SiparisNo ?? null,
                'order_item_no' => $gorev->SiparisSatirNo ?? null,
            ]);

            $log = TelegramNotificationLog::create([
                'event_type' => 'task_started',
                'task_no' => $taskNo,
                'order_no' => $gorev->SiparisNo ? (int) $gorev->SiparisNo : null,
                'order_item_no' => $gorev->SiparisSatirNo ? (int) $gorev->SiparisSatirNo : null,
                'message_body' => $messageBody,
                'status' => 'pending',
            ]);

            SendTelegramNotificationJob::dispatch($log->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
