<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkOrderBomPreviewService
{
    private array $componentCache = [];
    private array $departmentCache = [];
    private array $stockCache = [];
    private array $simulatedFreeStock = [];
    private array $simulatedFlowByComponent = [];

    public function __construct(private BomService $bomService)
    {
    }

    public function buildManualPreview(int $urunNo, string $tur, int $adet, string $stokDurum = 'StokDahil'): array
    {
        $type = $this->normalizeType($tur);
        $stokDurum = $this->normalizeStockMode($stokDurum);
        $adet = max(0, $adet);

        if ($urunNo <= 0 || $adet <= 0) {
            return [
                'success' => false,
                'message' => 'Ürün ve adet bilgisi zorunlu.',
            ];
        }

        if ($type === 'Nihai') {
            $legacyRoot = DB::table('tbAraUrun')
                ->where('No', $urunNo)
                ->where(function ($query) {
                    $query->where('UrunCesidi', 'like', 'Nihayi%')
                        ->orWhere('UrunCesidi', 'like', 'Nihai%');
                })
                ->first();

            if ($legacyRoot) {
                $group = $this->buildComponentGroup(
                    (int) $legacyRoot->No,
                    $adet,
                    $stokDurum,
                    true,
                    'Nihai Ürün',
                    trim((string) ($legacyRoot->AraUrunAdi ?? 'Nihai Ürün')),
                    null,
                    false
                );

                return $this->wrapGroups('manual', [$group], []);
            }

            $group = $this->buildProductGroup($urunNo, $adet, $stokDurum);

            return $this->wrapGroups('manual', $group ? [$group] : [], $group ? [] : ['Nihai ürünün kök ara ürünü bulunamadı.']);
        }

        $withRecursion = $type !== 'Ham Madde';
        $group = $this->buildComponentGroup(
            $urunNo,
            $adet,
            $stokDurum,
            $withRecursion,
            $type,
            ''
        );

        return $this->wrapGroups('manual', [$group], []);
    }

    public function buildOrderPreview(array $satirNolar, int $surplus = 0, string $tur = 'Nihai', ?int $altBilesenNo = null, string $stokDurum = 'StokDahil'): array
    {
        $stokDurum = $this->normalizeStockMode($stokDurum);
        $satirNolar = collect($satirNolar)
            ->map(fn ($no) => (int) $no)
            ->filter(fn ($no) => $no > 0)
            ->values()
            ->all();

        if (empty($satirNolar)) {
            return [
                'success' => false,
                'message' => 'Önizleme için sipariş satırı bulunamadı.',
            ];
        }

        $rows = DB::table('tbSiparisSatir')
            ->whereIn('No', $satirNolar)
            ->get()
            ->keyBy('No');

        $groups = [];
        $errors = [];
        $requestedType = $this->normalizeType($tur);

        foreach ($satirNolar as $satirNo) {
            $item = $rows->get($satirNo);
            if (!$item) {
                $errors[] = "Satır bulunamadı: #{$satirNo}";
                continue;
            }

            if (($item->Durum ?? '') !== 'UretimBekliyor' || (int) ($item->Aktif ?? 0) !== 1) {
                $errors[] = "Önizlemeye uygun değil: {$item->SiparisNo}";
                continue;
            }

            if (!empty($item->BagliOlduguOzelUretimNo)) {
                $errors[] = "GİED ile rezerve edilmiş satır atlandı: {$item->SiparisNo}";
                continue;
            }

            $matchedNo = (int) ($item->EslesenUrunNo ?? 0);
            $matchedType = trim((string) ($item->EslesenUrunTur ?? ''));
            if ($matchedNo <= 0) {
                $errors[] = "Eşleşme yok: {$item->UrunAdi}";
                continue;
            }

            $targetType = $requestedType;
            $targetNo = $altBilesenNo && $altBilesenNo > 0 ? $altBilesenNo : $matchedNo;
            if (!in_array($requestedType, ['Ara', 'Ham Madde'], true)) {
                $targetType = $matchedType === 'Ara' ? 'Ara' : 'NihaiProduct';
                $targetNo = $matchedNo;
            }

            $key = $targetType . ':' . $targetNo;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'type' => $targetType,
                    'targetNo' => $targetNo,
                    'quantity' => 0,
                    'orders' => [],
                    'surplus' => 0,
                ];
            }

            $groups[$key]['quantity'] += max(0, (int) ($item->Adet ?? 0));
            $groups[$key]['orders'][] = [
                'no' => (int) ($item->No ?? 0),
                'siparisNo' => (string) ($item->SiparisNo ?? ''),
                'urunAdi' => (string) ($item->UrunAdi ?? ''),
                'adet' => (int) ($item->Adet ?? 0),
            ];
        }

        if ($surplus > 0 && count($groups) === 1) {
            $firstKey = array_key_first($groups);
            $groups[$firstKey]['quantity'] += $surplus;
            $groups[$firstKey]['surplus'] = $surplus;
        }

        $previewGroups = [];
        foreach ($groups as $group) {
            if ($group['type'] === 'NihaiProduct') {
                $preview = $this->buildProductGroup((int) $group['targetNo'], (int) $group['quantity'], $stokDurum);
            } else {
                $preview = $this->buildComponentGroup(
                    (int) $group['targetNo'],
                    (int) $group['quantity'],
                    $stokDurum,
                    $group['type'] !== 'Ham Madde',
                    $group['type'] === 'Ham Madde' ? 'Ham Madde' : 'Ara Mamül',
                    ''
                );
            }

            if (!$preview) {
                $errors[] = 'BOM önizlemesi oluşturulamadı.';
                continue;
            }

            $preview['orders'] = $group['orders'];
            $preview['surplus'] = (int) $group['surplus'];
            $previewGroups[] = $preview;
        }

        return $this->wrapGroups('orders', $previewGroups, $errors);
    }

    private function normalizeStockMode(string $stokDurum): string
    {
        return in_array($stokDurum, ['StokDahil', 'StokHaric'], true) ? $stokDurum : 'StokDahil';
    }

    private function buildProductGroup(int $productNo, int $quantity, string $stokDurum): ?array
    {
        $product = DB::table('tbUrunler')->where('No', $productNo)->first();
        if (!$product) {
            return null;
        }

        $root = $this->resolveRootComponentForProduct($product);
        if (!$root) {
            return null;
        }

        return $this->buildComponentGroup(
            (int) $root->No,
            $quantity,
            $stokDurum,
            true,
            'Nihai Ürün',
            trim((string) (($product->SistemAdi ?? '') ?: ($product->UrunID ?? 'Nihai Ürün'))),
            $productNo,
            false
        );
    }

    private function buildComponentGroup(
        int $rootNo,
        int $quantity,
        string $stokDurum,
        bool $withRecursion,
        string $targetType,
        string $displayName = '',
        ?int $productNo = null,
        bool $applyRootStock = true
    ): array {
        $root = $this->component($rootNo);
        if (!$root) {
            return [
                'success' => false,
                'message' => 'Ara ürün bulunamadı.',
                'target' => [
                    'no' => $rootNo,
                    'type' => $targetType,
                    'name' => $displayName !== '' ? $displayName : 'Bilinmeyen ürün',
                ],
                'quantity' => $quantity,
                'tree' => ['nodes' => [], 'edges' => []],
                'tasks' => [],
                'summary' => $this->summarizeTasks([]),
            ];
        }

        $rootName = trim((string) ($root->AraUrunAdi ?? ''));
        $tasks = $this->simulateTasks($rootNo, $quantity, $stokDurum, $withRecursion, $applyRootStock);
        $tree = $this->buildTree($rootNo, $quantity, $this->simulatedFlowByComponent);
        $summary = $this->summarizeTasks($tasks);
        $summary['stockReserved'] = array_sum(array_map(
            fn ($node) => max(0, (int) ($node['stockReserved'] ?? 0)),
            $tree['nodes'] ?? []
        ));

        return [
            'success' => true,
            'target' => [
                'no' => $rootNo,
                'productNo' => $productNo,
                'type' => $targetType,
                'name' => $displayName !== '' ? $displayName : $rootName,
                'rootName' => $rootName,
            ],
            'quantity' => $quantity,
            'stockMode' => $stokDurum,
            'tree' => $tree,
            'tasks' => $tasks,
            'summary' => $summary,
        ];
    }

    private function wrapGroups(string $mode, array $groups, array $errors): array
    {
        $validGroups = array_values(array_filter($groups, fn ($group) => (bool) ($group['success'] ?? false)));

        return [
            'success' => !empty($validGroups),
            'mode' => $mode,
            'groups' => $validGroups,
            'errors' => array_values($errors),
            'summary' => [
                'groupCount' => count($validGroups),
                'taskCount' => array_sum(array_map(fn ($group) => (int) ($group['summary']['taskCount'] ?? 0), $validGroups)),
                'totalTaskQuantity' => array_sum(array_map(fn ($group) => (int) ($group['summary']['totalTaskQuantity'] ?? 0), $validGroups)),
                'stockReserved' => array_sum(array_map(fn ($group) => (int) ($group['summary']['stockReserved'] ?? 0), $validGroups)),
            ],
            'message' => empty($validGroups) ? ($errors[0] ?? 'BOM önizlemesi oluşturulamadı.') : null,
        ];
    }

    private function buildTree(int $rootNo, int $quantity, array $flowByComponent = []): array
    {
        $nodes = [];
        $edges = [];
        $this->walkTree($rootNo, $quantity, 0, $nodes, $edges, []);

        foreach ($nodes as &$node) {
            $stock = $this->stock((int) $node['no']);
            $flow = $flowByComponent[(int) $node['no']] ?? null;
            $node['stock'] = $stock;
            $node['bomRequired'] = (int) $node['required'];
            $node['netRequired'] = $flow ? max(0, (int) ($flow['netRequired'] ?? 0)) : 0;
            $node['stockReserved'] = $flow ? max(0, (int) ($flow['reservedFromStock'] ?? 0)) : 0;
            $node['assignableQuantity'] = $flow ? max(0, (int) ($flow['assignableQuantity'] ?? 0)) : 0;
            $node['blockedQuantity'] = max(0, (int) $node['netRequired'] - (int) $node['assignableQuantity']);
            $node['flowStatus'] = $flow ? 'active' : 'covered_by_parent';
            $node['status'] = $flow
                ? $this->nodeNetStatus($node)
                : ['key' => 'covered', 'label' => 'Üst parça stoktan karşılandı'];
        }
        unset($node);

        usort($nodes, fn ($a, $b) => [$a['level'], $a['name']] <=> [$b['level'], $b['name']]);

        return [
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
        ];
    }

    private function walkTree(int $componentNo, int $required, int $level, array &$nodes, array &$edges, array $path): void
    {
        if ($componentNo <= 0 || $level > 20 || in_array($componentNo, $path, true)) {
            return;
        }

        $component = $this->component($componentNo);
        if (!$component) {
            return;
        }

        $key = (string) $componentNo;
        if (!isset($nodes[$key])) {
            $nodes[$key] = [
                'id' => $key,
                'no' => $componentNo,
                'name' => trim((string) ($component->AraUrunAdi ?? '')),
                'type' => trim((string) ($component->UrunCesidi ?? '')),
                'typeClass' => $this->typeClass((string) ($component->UrunCesidi ?? '')),
                'department' => $this->departmentName((int) ($component->BolumAdiNo ?? 0)),
                'level' => $level,
                'required' => 0,
            ];
        }

        $nodes[$key]['required'] += max(0, $required);
        $nodes[$key]['level'] = max((int) $nodes[$key]['level'], $level);

        foreach ($this->directChildren($componentNo) as $child) {
            $childNo = (int) $child['no'];
            $multiplier = max(1, (int) $child['multiplier']);
            $edgeKey = $childNo . ':' . $componentNo;
            if (!isset($edges[$edgeKey])) {
                $edges[$edgeKey] = [
                    'from' => (string) $childNo,
                    'to' => (string) $componentNo,
                    'quantity' => $multiplier,
                ];
            }

            $this->walkTree($childNo, $required * $multiplier, $level + 1, $nodes, $edges, [...$path, $componentNo]);
        }
    }

    private function simulateTasks(int $rootNo, int $quantity, string $stokDurum, bool $withRecursion, bool $applyRootStock): array
    {
        $this->simulatedFreeStock = [];
        $this->simulatedFlowByComponent = [];
        $path = $this->bomService->tumYolHazirla((string) $rootNo);
        $tasks = [];

        $rootStockMode = $applyRootStock ? $stokDurum : 'StokHaric';
        $rootResult = $this->simulateMinStep($rootNo, $path, $rootNo, $quantity, $rootStockMode);
        if ($rootResult['task']) {
            $tasks[] = $rootResult['task'];
        }

        if ($withRecursion && $rootResult['producedQuantity'] > 0 && !empty($this->directChildren($rootNo))) {
            $this->simulateRecursive($rootNo, $rootResult['producedQuantity'], $path, $stokDurum, $tasks, 1);
        }

        return $this->applyDependencyLocks($tasks);
    }

    private function simulateRecursive(int $parentNo, int $parentQuantity, string $path, string $stokDurum, array &$tasks, int $depth): void
    {
        if ($depth > 20) {
            return;
        }

        foreach ($this->directChildren($parentNo) as $child) {
            $childNo = (int) $child['no'];
            $childHasChildren = !empty($this->directChildren($childNo));

            $result = $this->simulateMinStep($parentNo, $path, $childNo, $parentQuantity, $stokDurum);
            if ($result['task']) {
                $tasks[] = $result['task'];
            }

            $childNetProduction = max(0, (int) ($result['producedQuantity'] ?? 0));
            if ($childNetProduction > 0 && $childHasChildren) {
                $this->simulateRecursive($childNo, $childNetProduction, $path, $stokDurum, $tasks, $depth + 1);
            }
        }
    }

    private function simulateMinStep(int $parentNo, string $path, int $componentNo, int $incomingQuantity, string $stokDurum): array
    {
        $requested = max(0, $this->bomService->uretimAdetBelirle((string) $componentNo, $path, $incomingQuantity));
        $remaining = $requested;
        $reservedFromStock = 0;
        $freeBefore = $this->simulatedFree($componentNo);

        if ($stokDurum === 'StokDahil') {
            $reservedFromStock = min($freeBefore, $remaining);
            $remaining -= $reservedFromStock;
            $this->simulatedFreeStock[$componentNo] = max(0, $freeBefore - $reservedFromStock);
        }

        if ($remaining <= 0) {
            $this->recordSimulatedFlow($parentNo, $componentNo, $requested, $reservedFromStock, 0, 0);

            return [
                'producedQuantity' => 0,
                'requestedQuantity' => $requested,
                'reservedFromStock' => $reservedFromStock,
                'task' => null,
            ];
        }

        $producible = $this->simulatedProducible($componentNo);
        $assignable = ($producible > $remaining || $producible < 0) ? $remaining : max(0, $producible);
        $this->recordSimulatedFlow($parentNo, $componentNo, $requested, $reservedFromStock, $remaining, $assignable);

        return [
            'producedQuantity' => $remaining,
            'requestedQuantity' => $requested,
            'reservedFromStock' => $reservedFromStock,
            'task' => $this->taskPayload($parentNo, $componentNo, $requested, $remaining, $assignable, $reservedFromStock),
        ];
    }

    private function recordSimulatedFlow(
        int $parentNo,
        int $componentNo,
        int $requested,
        int $reservedFromStock,
        int $netRequired,
        int $assignable
    ): void {
        if (!isset($this->simulatedFlowByComponent[$componentNo])) {
            $this->simulatedFlowByComponent[$componentNo] = [
                'componentNo' => $componentNo,
                'parentNos' => [],
                'requestedQuantity' => 0,
                'reservedFromStock' => 0,
                'netRequired' => 0,
                'assignableQuantity' => 0,
            ];
        }

        if (!in_array($parentNo, $this->simulatedFlowByComponent[$componentNo]['parentNos'], true)) {
            $this->simulatedFlowByComponent[$componentNo]['parentNos'][] = $parentNo;
        }

        $this->simulatedFlowByComponent[$componentNo]['requestedQuantity'] += max(0, $requested);
        $this->simulatedFlowByComponent[$componentNo]['reservedFromStock'] += max(0, $reservedFromStock);
        $this->simulatedFlowByComponent[$componentNo]['netRequired'] += max(0, $netRequired);
        $this->simulatedFlowByComponent[$componentNo]['assignableQuantity'] += max(0, $assignable);
    }

    private function taskPayload(int $parentNo, int $componentNo, int $requested, int $total, int $assignable, int $reservedFromStock): array
    {
        $component = $this->component($componentNo);
        $departmentNo = (int) ($component->BolumAdiNo ?? 0);
        $blocked = max(0, $total - $assignable);

        return [
            'componentNo' => $componentNo,
            'parentNo' => $parentNo,
            'componentName' => trim((string) ($component->AraUrunAdi ?? '')),
            'departmentNo' => $departmentNo,
            'departmentName' => $this->departmentName($departmentNo),
            'requestedQuantity' => $requested,
            'totalQuantity' => $total,
            'assignableQuantity' => $assignable,
            'blockedQuantity' => $blocked,
            'reservedFromStock' => $reservedFromStock,
            'status' => $blocked <= 0 ? 'Hazır' : ($assignable > 0 ? 'Kısmi hazır' : 'Stok bekler'),
            'statusKey' => $blocked <= 0 ? 'ready' : ($assignable > 0 ? 'partial' : 'blocked'),
        ];
    }

    private function applyDependencyLocks(array $tasks): array
    {
        $openTaskComponents = [];
        foreach ($tasks as $task) {
            if ((int) ($task['totalQuantity'] ?? 0) > 0) {
                $openTaskComponents[(int) ($task['componentNo'] ?? 0)] = true;
            }
        }

        foreach ($tasks as &$task) {
            $componentNo = (int) ($task['componentNo'] ?? 0);
            if ($componentNo <= 0 || !$this->hasOpenDescendantTask($componentNo, $openTaskComponents)) {
                continue;
            }

            $task['assignableQuantity'] = 0;
            $task['blockedQuantity'] = max(0, (int) ($task['totalQuantity'] ?? 0));
            $task['status'] = 'Alt görev bekler';
            $task['statusKey'] = 'blocked';
            $task['dependencyBlocked'] = true;
        }
        unset($task);

        $assignableByComponent = [];
        foreach ($tasks as $task) {
            $componentNo = (int) ($task['componentNo'] ?? 0);
            if ($componentNo > 0) {
                $assignableByComponent[$componentNo] = ($assignableByComponent[$componentNo] ?? 0)
                    + max(0, (int) ($task['assignableQuantity'] ?? 0));
            }
        }

        foreach ($assignableByComponent as $componentNo => $assignable) {
            if (isset($this->simulatedFlowByComponent[$componentNo])) {
                $this->simulatedFlowByComponent[$componentNo]['assignableQuantity'] = $assignable;
            }
        }

        return $tasks;
    }

    private function hasOpenDescendantTask(int $componentNo, array $openTaskComponents, array $path = []): bool
    {
        if (in_array($componentNo, $path, true)) {
            return false;
        }

        foreach ($this->directChildren($componentNo) as $child) {
            $childNo = (int) $child['no'];
            if ($childNo <= 0) {
                continue;
            }

            if (!empty($openTaskComponents[$childNo])) {
                return true;
            }

            if ($this->hasOpenDescendantTask($childNo, $openTaskComponents, [...$path, $componentNo])) {
                return true;
            }
        }

        return false;
    }

    private function summarizeTasks(array $tasks): array
    {
        $departments = [];
        foreach ($tasks as $task) {
            $name = trim((string) ($task['departmentName'] ?? ''));
            if ($name !== '') {
                $departments[$name] = true;
            }
        }

        return [
            'taskCount' => count($tasks),
            'totalTaskQuantity' => array_sum(array_map(fn ($task) => (int) ($task['totalQuantity'] ?? 0), $tasks)),
            'assignableQuantity' => array_sum(array_map(fn ($task) => (int) ($task['assignableQuantity'] ?? 0), $tasks)),
            'blockedQuantity' => array_sum(array_map(fn ($task) => (int) ($task['blockedQuantity'] ?? 0), $tasks)),
            'stockReserved' => array_sum(array_map(fn ($task) => (int) ($task['reservedFromStock'] ?? 0), $tasks)),
            'departments' => array_keys($departments),
        ];
    }

    private function simulatedProducible(int $componentNo): int
    {
        $children = $this->directChildren($componentNo);
        if (empty($children)) {
            return -1;
        }

        $min = PHP_INT_MAX;
        foreach ($children as $child) {
            $multiplier = max(1, (int) $child['multiplier']);
            $stock = $this->simulatedFree((int) $child['no']);
            $producible = intdiv(max(0, $stock), $multiplier);
            $min = min($min, $producible);
        }

        return $min === PHP_INT_MAX ? -1 : $min;
    }

    private function simulatedFree(int $componentNo): int
    {
        if (!array_key_exists($componentNo, $this->simulatedFreeStock)) {
            $this->simulatedFreeStock[$componentNo] = $this->stock($componentNo)['free'];
        }

        return $this->simulatedFreeStock[$componentNo];
    }

    private function directChildren(int $componentNo): array
    {
        $component = $this->component($componentNo);
        $path = trim((string) ($component->Yol ?? ''));
        if ($path === '') {
            return [];
        }

        $children = [];
        foreach (explode(':', $path) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            if (count($parts) < 2) {
                continue;
            }

            $childNo = (int) $parts[0];
            if ($childNo <= 0) {
                continue;
            }

            $children[] = [
                'no' => $childNo,
                'multiplier' => (int) ($parts[2] ?? $parts[1] ?? 1),
            ];
        }

        return $children;
    }

    private function stock(int $componentNo): array
    {
        if (!isset($this->stockCache[$componentNo])) {
            if (!Schema::hasTable('tbBolumAraStok')) {
                $this->stockCache[$componentNo] = ['warehouse' => 0, 'free' => 0, 'reserved' => 0];
                return $this->stockCache[$componentNo];
            }

            $row = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $componentNo)
                ->selectRaw('COALESCE(SUM(Adet), 0) as warehouse, COALESCE(SUM(TamponMiktar), 0) as free')
                ->first();

            $warehouse = max(0, (int) ($row->warehouse ?? 0));
            $free = max(0, min($warehouse, (int) ($row->free ?? 0)));
            $this->stockCache[$componentNo] = [
                'warehouse' => $warehouse,
                'reserved' => max(0, $warehouse - $free),
                'free' => $free,
            ];
        }

        return $this->stockCache[$componentNo];
    }

    private function nodeStatus(int $required, array $stock): array
    {
        if ($required <= 0) {
            return ['key' => 'neutral', 'label' => 'İhtiyaç yok'];
        }

        if ($stock['free'] >= $required) {
            return ['key' => 'ready', 'label' => 'Boşta stok yeterli'];
        }

        if ($stock['free'] > 0) {
            return ['key' => 'partial', 'label' => 'Kısmi stok'];
        }

        return ['key' => 'blocked', 'label' => 'Üretim gerekir'];
    }

    private function nodeNetStatus(array $node): array
    {
        $netRequired = max(0, (int) ($node['netRequired'] ?? 0));
        $reservedFromStock = max(0, (int) ($node['stockReserved'] ?? 0));
        $assignable = max(0, (int) ($node['assignableQuantity'] ?? 0));

        if ($netRequired <= 0) {
            return $reservedFromStock > 0
                ? ['key' => 'covered', 'label' => 'Boşta stoktan ayrılacak']
                : ['key' => 'covered', 'label' => 'Üretim gerekmiyor'];
        }

        $blocked = max(0, $netRequired - $assignable);
        if ($blocked <= 0) {
            return ['key' => 'ready', 'label' => 'Üretime hazır'];
        }

        if ($assignable > 0) {
            return ['key' => 'partial', 'label' => 'Kısmi üretilebilir'];
        }

        return ['key' => 'blocked', 'label' => 'Stok bekler'];
    }

    private function component(int $componentNo): ?object
    {
        if (!array_key_exists($componentNo, $this->componentCache)) {
            $this->componentCache[$componentNo] = DB::table('tbAraUrun')->where('No', $componentNo)->first();
        }

        return $this->componentCache[$componentNo];
    }

    private function departmentName(int $departmentNo): string
    {
        if ($departmentNo <= 0) {
            return '';
        }

        if (!array_key_exists($departmentNo, $this->departmentCache)) {
            $this->departmentCache[$departmentNo] = (string) (DB::table('tbBolum')->where('No', $departmentNo)->value('BolumAdi') ?? '');
        }

        return $this->departmentCache[$departmentNo];
    }

    private function resolveRootComponentForProduct(object $product): ?object
    {
        $urunId = trim((string) ($product->UrunID ?? ''));
        if ($urunId !== '') {
            $directMatch = DB::table('tbAraUrun')->where('AraUrunAdi', $urunId)->first();
            if ($directMatch) {
                return $directMatch;
            }
        }

        $path = trim((string) ($product->AraAdlarYol ?? ''));
        if ($path === '') {
            return null;
        }

        $firstSegment = trim(explode(':', $path)[0] ?? '');
        if (preg_match('/^\d+\-\d+/', $firstSegment) === 1) {
            $rootNo = (int) explode('-', $firstSegment)[0];
            if ($rootNo > 0) {
                return DB::table('tbAraUrun')->where('No', $rootNo)->first();
            }
        }

        $steps = preg_split('/[→>]|->|:/', $path) ?: [];
        foreach ($steps as $step) {
            $candidate = trim((string) $step);
            if ($candidate === '') {
                continue;
            }

            $component = DB::table('tbAraUrun')->where('AraUrunAdi', $candidate)->first();
            if ($component) {
                return $component;
            }
        }

        return null;
    }

    private function normalizeType(string $type): string
    {
        $normalized = mb_strtolower(trim($type), 'UTF-8');

        if (str_contains($normalized, 'ham')) {
            return 'Ham Madde';
        }

        if (str_contains($normalized, 'ara')) {
            return 'Ara';
        }

        return 'Nihai';
    }

    private function typeClass(string $type): string
    {
        $normalized = mb_strtolower($type, 'UTF-8');

        if (str_contains($normalized, 'ham')) {
            return 'ham';
        }

        if (str_contains($normalized, 'ara')) {
            return 'ara';
        }

        if (str_contains($normalized, 'nihai') || str_contains($normalized, 'nihayi')) {
            return 'nihai';
        }

        return 'parca';
    }
}
