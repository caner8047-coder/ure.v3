<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ProductMergeService
{
    private array $finalMatchTypes = [
        'Nihai',
        'Nihayi',
        'Nihai Ürün',
        'Nihayi Ürün',
        'Nihai Urun',
        'Nihayi Urun',
        'NihaiUrun',
        'NihayiUrun',
    ];

    public function preview(string $mergeType, int $sourceId, int $targetId, bool $includeLinkedComponent = false): array
    {
        [$type, $source, $target] = $this->validateMergePair($mergeType, $sourceId, $targetId);

        $sections = [];
        $warnings = [];
        $linkedComponent = null;

        if ($type === 'product') {
            $this->addProductSections($sections, $sourceId);
            $linkedComponent = $this->linkedComponentPair($source, $target);

            if ($includeLinkedComponent && $linkedComponent) {
                $this->validateMergePair('component', intval($linkedComponent['source']->No), intval($linkedComponent['target']->No));
                $this->addComponentSections($sections, intval($linkedComponent['source']->No), intval($linkedComponent['target']->No), 'linked_component_', 'Kök ara ürün: ');
            }

            $this->addLinkedComponentWarning($warnings, $linkedComponent, $includeLinkedComponent);
        } else {
            $this->addComponentSections($sections, $sourceId, $targetId);
        }

        $total = array_sum(array_map(fn ($section) => intval($section['count'] ?? 0), $sections));

        return [
            'merge_type' => $type,
            'type_label' => $type === 'product' ? 'Nihai Ürün' : 'Ara Ürün / Parça',
            'source' => $this->recordSummary($type, $source),
            'target' => $this->recordSummary($type, $target),
            'sections' => array_values($sections),
            'total_count' => $total,
            'warnings' => $warnings,
            'include_linked_component' => $type === 'product' && $includeLinkedComponent && $linkedComponent !== null,
            'linked_component' => $linkedComponent ? [
                'source' => $this->recordSummary('component', $linkedComponent['source']),
                'target' => $this->recordSummary('component', $linkedComponent['target']),
            ] : null,
        ];
    }

    public function merge(string $mergeType, int $sourceId, int $targetId, mixed $actor = null, bool $includeLinkedComponent = false): array
    {
        return DB::transaction(function () use ($mergeType, $sourceId, $targetId, $actor, $includeLinkedComponent) {
            [$type, $source, $target] = $this->validateMergePair($mergeType, $sourceId, $targetId);
            $preview = $this->preview($type, $sourceId, $targetId, $includeLinkedComponent);

            $changes = $type === 'product'
                ? $this->mergeProduct($sourceId, $targetId)
                : $this->mergeComponent($sourceId, $targetId, $target);

            if ($type === 'product' && $includeLinkedComponent) {
                $linkedComponent = $this->linkedComponentPair($source, $target);
                if ($linkedComponent) {
                    [, $componentSource, $componentTarget] = $this->validateMergePair(
                        'component',
                        intval($linkedComponent['source']->No),
                        intval($linkedComponent['target']->No)
                    );

                    $componentPreview = $this->preview(
                        'component',
                        intval($componentSource->No),
                        intval($componentTarget->No)
                    );
                    $componentChanges = $this->mergeComponent(intval($componentSource->No), intval($componentTarget->No), $componentTarget);

                    $this->markSourceMerged('component', intval($componentSource->No), intval($componentTarget->No), $actor);
                    $this->insertMergeLog('component', $componentSource, $componentTarget, $componentPreview, $componentChanges, $actor);

                    $changes['linked_component'] = [
                        'source' => $this->recordSummary('component', $componentSource),
                        'target' => $this->recordSummary('component', $componentTarget),
                        'changes' => $componentChanges,
                    ];
                }
            }

            $this->markSourceMerged($type, $sourceId, $targetId, $actor);
            $this->insertMergeLog($type, $source, $target, $preview, $changes, $actor);

            return [
                'merge_type' => $type,
                'source' => $this->recordSummary($type, $source),
                'target' => $this->recordSummary($type, $target),
                'preview' => $preview,
                'changes' => $changes,
            ];
        });
    }

    private function validateMergePair(string $mergeType, int $sourceId, int $targetId): array
    {
        $type = $this->normalizeMergeType($mergeType);

        if ($sourceId <= 0 || $targetId <= 0) {
            throw new InvalidArgumentException('Kaynak ve hedef ürün seçilmelidir.');
        }

        if ($sourceId === $targetId) {
            throw new InvalidArgumentException('Kaynak ve hedef aynı kayıt olamaz.');
        }

        $table = $type === 'product' ? 'tbUrunler' : 'tbAraUrun';
        if (!Schema::hasTable($table)) {
            throw new InvalidArgumentException('Ürün tablosu bulunamadı.');
        }

        $source = DB::table($table)->where('No', $sourceId)->first();
        $target = DB::table($table)->where('No', $targetId)->first();

        if (!$source) {
            throw new InvalidArgumentException('Kaynak ürün bulunamadı.');
        }

        if (!$target) {
            throw new InvalidArgumentException('Hedef ürün bulunamadı.');
        }

        if ($this->recordIsMerged($table, $source)) {
            throw new InvalidArgumentException('Kaynak ürün daha önce başka bir ürüne birleştirilmiş.');
        }

        if ($this->recordIsMerged($table, $target)) {
            throw new InvalidArgumentException('Hedef ürün daha önce başka bir ürüne birleştirilmiş; hedef olarak aktif bir ürün seçin.');
        }

        if ($type === 'component') {
            $sourceClass = $this->componentTypeClass((string) ($source->UrunCesidi ?? ''));
            $targetClass = $this->componentTypeClass((string) ($target->UrunCesidi ?? ''));

            if ($sourceClass !== $targetClass) {
                throw new InvalidArgumentException('Ara ürün/parça birleştirmede kaynak ve hedef ürün türü aynı olmalıdır.');
            }

            if ($this->pathContainsComponent((string) ($target->Yol ?? ''), $sourceId)) {
                throw new InvalidArgumentException('Hedef ürünün BOM yolunda kaynak ürün var. Bu birleştirme hedefi kendisine bağlayacağı için engellendi.');
            }

            $this->assertComponentMergeDoesNotCreateSelfReference($sourceId, $targetId);
        }

        return [$type, $source, $target];
    }

    private function normalizeMergeType(string $mergeType): string
    {
        $type = trim($mergeType);

        return match ($type) {
            'product', 'final', 'nihai' => 'product',
            'component', 'ara', 'part' => 'component',
            default => throw new InvalidArgumentException('Geçersiz birleştirme türü.'),
        };
    }

    private function recordIsMerged(string $table, object $record): bool
    {
        return Schema::hasColumn($table, 'MergedIntoNo') && intval($record->MergedIntoNo ?? 0) > 0;
    }

    private function recordSummary(string $type, object $record): array
    {
        return [
            'id' => intval($record->No ?? 0),
            'name' => $type === 'product'
                ? trim((string) ($record->UrunID ?? ''))
                : trim((string) ($record->AraUrunAdi ?? '')),
            'type' => $type === 'product'
                ? 'Nihai'
                : trim((string) ($record->UrunCesidi ?? '')),
        ];
    }

    private function addProductSections(array &$sections, int $sourceId): void
    {
        $this->addMatchSection($sections, 'orders', 'Sipariş eşleşmeleri', 'tbSiparisSatir', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'product');
        $this->addMatchSection($sections, 'match_cache', 'Ürün eşleştirme önbelleği', 'tbUrunEslestirmeOnbellek', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'product');
        $this->addMatchSection($sections, 'work_order_history', 'İş emri geçmişi', 'tbIsEmriGecmisi', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'product');
        $this->addMatchSection($sections, 'modern_order_items', 'Modern sipariş kayıtları', 'order_items', 'matched_product_id', 'matched_product_type', $sourceId, 'product');
        $this->addMatchSection($sections, 'modern_match_cache', 'Modern eşleştirme önbelleği', 'product_match_caches', 'matched_product_id', 'matched_product_type', $sourceId, 'product');

        foreach ($this->productReferenceSpecs() as $spec) {
            $this->addSimpleSection($sections, $spec['key'], $spec['label'], $spec['table'], $spec['column'], $sourceId);
        }
    }

    private function addComponentSections(array &$sections, int $sourceId, int $targetId, string $keyPrefix = '', string $labelPrefix = ''): void
    {
        $this->addMatchSection($sections, $keyPrefix . 'orders', $labelPrefix . 'Sipariş eşleşmeleri', 'tbSiparisSatir', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'component');
        $this->addMatchSection($sections, $keyPrefix . 'match_cache', $labelPrefix . 'Ürün eşleştirme önbelleği', 'tbUrunEslestirmeOnbellek', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'component');
        $this->addMatchSection($sections, $keyPrefix . 'work_order_history', $labelPrefix . 'İş emri geçmişi', 'tbIsEmriGecmisi', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, 'component');
        $this->addMatchSection($sections, $keyPrefix . 'modern_order_items', $labelPrefix . 'Modern sipariş kayıtları', 'order_items', 'matched_product_id', 'matched_product_type', $sourceId, 'component');
        $this->addMatchSection($sections, $keyPrefix . 'modern_match_cache', $labelPrefix . 'Modern eşleştirme önbelleği', 'product_match_caches', 'matched_product_id', 'matched_product_type', $sourceId, 'component');

        foreach ($this->componentReferenceSpecs() as $spec) {
            if ($spec['key'] === 'department_stocks') {
                $this->addSection($sections, $keyPrefix . $spec['key'], $labelPrefix . $spec['label'], $this->countSimpleRows($spec['table'], $spec['column'], $sourceId), $this->stockPreviewDetails($sourceId, $targetId));
                continue;
            }

            $this->addSimpleSection($sections, $keyPrefix . $spec['key'], $labelPrefix . $spec['label'], $spec['table'], $spec['column'], $sourceId);
        }

        $this->addSection($sections, $keyPrefix . 'component_bom_paths', $labelPrefix . 'Ara ürün BOM yolları', $this->countPathRows('tbAraUrun', 'Yol', $sourceId));
        $this->addSection($sections, $keyPrefix . 'product_bom_paths', $labelPrefix . 'Nihai ürün BOM yolları', $this->countPathRows('tbUrunler', 'AraAdlarYol', $sourceId));
    }

    private function linkedComponentPair(object $source, object $target): ?array
    {
        if (!Schema::hasTable('tbAraUrun')) {
            return null;
        }

        $sourceName = trim((string) ($source->UrunID ?? ''));
        $targetName = trim((string) ($target->UrunID ?? ''));
        if ($sourceName === '' || $targetName === '') {
            return null;
        }

        $sourceComponent = DB::table('tbAraUrun')->where('AraUrunAdi', $sourceName)->first();
        $targetComponent = DB::table('tbAraUrun')->where('AraUrunAdi', $targetName)->first();

        if (!$sourceComponent || !$targetComponent || intval($sourceComponent->No ?? 0) === intval($targetComponent->No ?? 0)) {
            return null;
        }

        return [
            'source' => $sourceComponent,
            'target' => $targetComponent,
        ];
    }

    private function addLinkedComponentWarning(array &$warnings, ?array $linkedComponent, bool $includeLinkedComponent): void
    {
        if ($linkedComponent && !$includeLinkedComponent) {
            $warnings[] = [
                'type' => 'linked_component',
                'message' => 'Bu nihai ürünlerin kök ara ürün kayıtları farklı. Stok/reçete kartları da tek kart olsun istiyorsanız kök ara ürünleri de birleştirme seçeneğini işaretleyin.',
                'source_component_no' => intval($linkedComponent['source']->No ?? 0),
                'target_component_no' => intval($linkedComponent['target']->No ?? 0),
            ];
        }
    }

    private function addMatchSection(array &$sections, string $key, string $label, string $table, string $idColumn, string $typeColumn, int $sourceId, string $mergeType): void
    {
        if (!$this->tableHasColumns($table, [$idColumn, $typeColumn])) {
            return;
        }

        $query = DB::table($table)->where($idColumn, $sourceId);
        $this->applyMatchTypeFilter($query, $typeColumn, $mergeType);

        $this->addSection($sections, $key, $label, $query->count());
    }

    private function addSimpleSection(array &$sections, string $key, string $label, string $table, string $column, int $sourceId): void
    {
        if (!$this->tableHasColumns($table, [$column])) {
            return;
        }

        $this->addSection($sections, $key, $label, $this->countSimpleRows($table, $column, $sourceId));
    }

    private function addSection(array &$sections, string $key, string $label, int $count, array $details = []): void
    {
        $section = [
            'key' => $key,
            'label' => $label,
            'count' => $count,
        ];

        if (!empty($details)) {
            $section['details'] = $details;
        }

        $sections[$key] = $section;
    }

    private function mergeProduct(int $sourceId, int $targetId): array
    {
        $changes = [];

        $changes['orders'] = $this->updateMatchedRows('tbSiparisSatir', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'product', 'Nihai');
        $changes['match_cache'] = $this->updateMatchedRows('tbUrunEslestirmeOnbellek', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'product', 'Nihai');
        $changes['work_order_history'] = $this->updateMatchedRows('tbIsEmriGecmisi', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'product', 'Nihai');
        $changes['modern_order_items'] = $this->updateMatchedRows('order_items', 'matched_product_id', 'matched_product_type', $sourceId, $targetId, 'product', 'Nihai');
        $changes['modern_match_cache'] = $this->updateMatchedRows('product_match_caches', 'matched_product_id', 'matched_product_type', $sourceId, $targetId, 'product', 'Nihai');

        foreach ($this->productReferenceSpecs() as $spec) {
            $changes[$spec['key']] = $this->updateSimpleRows($spec['table'], $spec['column'], $sourceId, $targetId);
        }

        return $changes;
    }

    private function mergeComponent(int $sourceId, int $targetId, object $target): array
    {
        $targetType = trim((string) ($target->UrunCesidi ?? ''));
        $changes = [];

        $changes['orders'] = $this->updateMatchedRows('tbSiparisSatir', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'component', $targetType);
        $changes['match_cache'] = $this->updateMatchedRows('tbUrunEslestirmeOnbellek', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'component', $targetType);
        $changes['work_order_history'] = $this->updateMatchedRows('tbIsEmriGecmisi', 'EslesenUrunNo', 'EslesenUrunTur', $sourceId, $targetId, 'component', $targetType);
        $changes['modern_order_items'] = $this->updateMatchedRows('order_items', 'matched_product_id', 'matched_product_type', $sourceId, $targetId, 'component', $targetType);
        $changes['modern_match_cache'] = $this->updateMatchedRows('product_match_caches', 'matched_product_id', 'matched_product_type', $sourceId, $targetId, 'component', $targetType);

        foreach ($this->componentReferenceSpecs() as $spec) {
            if ($spec['key'] === 'department_stocks') {
                $changes[$spec['key']] = $this->mergeDepartmentStocks($sourceId, $targetId);
                continue;
            }

            $changes[$spec['key']] = $this->updateSimpleRows($spec['table'], $spec['column'], $sourceId, $targetId);
        }

        $changes['component_bom_paths'] = $this->replacePathRows('tbAraUrun', 'Yol', $sourceId, $targetId);
        $changes['product_bom_paths'] = $this->replacePathRows('tbUrunler', 'AraAdlarYol', $sourceId, $targetId);

        return $changes;
    }

    private function productReferenceSpecs(): array
    {
        return [
            ['key' => 'pool_tasks', 'label' => 'Üretim havuzu', 'table' => 'tbBolumHavuz', 'column' => 'UrunIDNo'],
            ['key' => 'personnel_tasks', 'label' => 'Personel görevleri', 'table' => 'tbPersonelGorev', 'column' => 'UrunIDNo'],
            ['key' => 'completed_tasks', 'label' => 'Tamamlanan görevler', 'table' => 'tbGorevler', 'column' => 'UrunIDNo'],
            ['key' => 'department_stocks', 'label' => 'Stok kayıtları', 'table' => 'tbBolumAraStok', 'column' => 'UrunIDNo'],
            ['key' => 'critical_thresholds', 'label' => 'Kritik stok eşikleri', 'table' => 'tbKritikStokEsik', 'column' => 'UrunIDNo'],
            ['key' => 'issued_tasks', 'label' => 'Verilen görev kayıtları', 'table' => 'tbVerilenGorevler', 'column' => 'UrunIDNo'],
            ['key' => 'set_contents', 'label' => 'Set içerikleri', 'table' => 'tbSetIcerikleri', 'column' => 'UrunNo'],
        ];
    }

    private function componentReferenceSpecs(): array
    {
        return [
            ['key' => 'pool_tasks', 'label' => 'Üretim havuzu', 'table' => 'tbBolumHavuz', 'column' => 'AraUrunAdiNo'],
            ['key' => 'personnel_tasks', 'label' => 'Personel görevleri', 'table' => 'tbPersonelGorev', 'column' => 'AraUrunAdiNo'],
            ['key' => 'completed_tasks', 'label' => 'Tamamlanan görevler', 'table' => 'tbGorevler', 'column' => 'AraUrunAdiNo'],
            ['key' => 'department_stocks', 'label' => 'Stok kayıtları', 'table' => 'tbBolumAraStok', 'column' => 'AraUrunAdiNo'],
            ['key' => 'critical_thresholds', 'label' => 'Kritik stok eşikleri', 'table' => 'tbKritikStokEsik', 'column' => 'AraUrunAdiNo'],
            ['key' => 'critical_warnings', 'label' => 'Kritik stok uyarıları', 'table' => 'tbKritikStokUyari', 'column' => 'AraUrunAdiNo'],
            ['key' => 'issued_tasks', 'label' => 'Verilen görev kayıtları', 'table' => 'tbVerilenGorevler', 'column' => 'UrunIDNo'],
        ];
    }

    private function countSimpleRows(string $table, string $column, int $sourceId): int
    {
        if (!$this->tableHasColumns($table, [$column])) {
            return 0;
        }

        return DB::table($table)->where($column, $sourceId)->count();
    }

    private function updateSimpleRows(string $table, string $column, int $sourceId, int $targetId): int
    {
        if (!$this->tableHasColumns($table, [$column])) {
            return 0;
        }

        return DB::table($table)->where($column, $sourceId)->update([$column => $targetId]);
    }

    private function updateMatchedRows(string $table, string $idColumn, string $typeColumn, int $sourceId, int $targetId, string $mergeType, string $targetType): int
    {
        if (!$this->tableHasColumns($table, [$idColumn, $typeColumn])) {
            return 0;
        }

        $query = DB::table($table)->where($idColumn, $sourceId);
        $this->applyMatchTypeFilter($query, $typeColumn, $mergeType);

        return $query->update([
            $idColumn => $targetId,
            $typeColumn => $targetType,
        ]);
    }

    private function applyMatchTypeFilter($query, string $typeColumn, string $mergeType): void
    {
        if ($mergeType === 'product') {
            $query->whereIn($typeColumn, $this->finalMatchTypes);
            return;
        }

        $query->where(function ($inner) use ($typeColumn) {
            $inner->whereNull($typeColumn)
                ->orWhere($typeColumn, '')
                ->orWhereNotIn($typeColumn, $this->finalMatchTypes);
        });
    }

    private function stockPreviewDetails(int $sourceId, int $targetId): array
    {
        if (!$this->tableHasColumns('tbBolumAraStok', ['No', 'BolumAdiNo', 'AraUrunAdiNo', 'Adet', 'TamponMiktar'])) {
            return [];
        }

        $sourceRows = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $sourceId)->get();
        $mergeRows = 0;
        $moveRows = 0;
        $quantity = 0;

        foreach ($sourceRows as $row) {
            $quantity += max(0, intval($row->Adet ?? 0));
            $targetQuery = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $targetId);
            $this->whereSameDepartment($targetQuery, $row->BolumAdiNo ?? null);
            $targetQuery->exists() ? $mergeRows++ : $moveRows++;
        }

        return [
            'merge_rows' => $mergeRows,
            'move_rows' => $moveRows,
            'quantity' => $quantity,
        ];
    }

    private function mergeDepartmentStocks(int $sourceId, int $targetId): int
    {
        if (!$this->tableHasColumns('tbBolumAraStok', ['No', 'BolumAdiNo', 'AraUrunAdiNo', 'Adet', 'TamponMiktar'])) {
            return 0;
        }

        $sourceRows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $sourceId)
            ->orderBy('No')
            ->get();

        $handled = 0;

        foreach ($sourceRows as $sourceRow) {
            $targetQuery = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $targetId);
            $this->whereSameDepartment($targetQuery, $sourceRow->BolumAdiNo ?? null);
            $targetRow = $targetQuery->orderBy('No')->first();

            if ($targetRow) {
                $newQuantity = max(0, intval($targetRow->Adet ?? 0)) + max(0, intval($sourceRow->Adet ?? 0));
                $newBuffer = min(
                    $newQuantity,
                    max(0, intval($targetRow->TamponMiktar ?? 0)) + max(0, intval($sourceRow->TamponMiktar ?? 0))
                );

                $stockUpdates = [
                    'Adet' => $newQuantity,
                    'TamponMiktar' => $newBuffer,
                ];
                if (intval($targetRow->UrunIDNo ?? 0) <= 0 && intval($sourceRow->UrunIDNo ?? 0) > 0) {
                    $stockUpdates['UrunIDNo'] = intval($sourceRow->UrunIDNo);
                }

                DB::table('tbBolumAraStok')->where('No', $targetRow->No)->update($stockUpdates);
                DB::table('tbBolumAraStok')->where('No', $sourceRow->No)->delete();
            } else {
                DB::table('tbBolumAraStok')->where('No', $sourceRow->No)->update([
                    'AraUrunAdiNo' => $targetId,
                ]);
            }

            $handled++;
        }

        return $handled;
    }

    private function whereSameDepartment($query, mixed $departmentNo): void
    {
        $departmentNo === null
            ? $query->whereNull('BolumAdiNo')
            : $query->where('BolumAdiNo', $departmentNo);
    }

    private function countPathRows(string $table, string $column, int $sourceId): int
    {
        if (!$this->tableHasColumns($table, ['No', $column])) {
            return 0;
        }

        return DB::table($table)
            ->select('No', $column)
            ->get()
            ->filter(fn ($row) => $this->pathContainsComponent((string) ($row->{$column} ?? ''), $sourceId))
            ->count();
    }

    private function replacePathRows(string $table, string $column, int $sourceId, int $targetId): int
    {
        if (!$this->tableHasColumns($table, ['No', $column])) {
            return 0;
        }

        $changed = 0;
        $rows = DB::table($table)->select('No', $column)->get();

        foreach ($rows as $row) {
            $result = $this->replaceComponentInPath((string) ($row->{$column} ?? ''), $sourceId, $targetId);
            if (!$result['changed']) {
                continue;
            }

            DB::table($table)->where('No', $row->No)->update([$column => $result['path']]);
            $changed++;
        }

        return $changed;
    }

    private function assertComponentMergeDoesNotCreateSelfReference(int $sourceId, int $targetId): void
    {
        foreach ([['tbAraUrun', 'Yol'], ['tbUrunler', 'AraAdlarYol']] as [$table, $column]) {
            if (!$this->tableHasColumns($table, ['No', $column])) {
                continue;
            }

            $rows = DB::table($table)->select('No', $column)->get();
            foreach ($rows as $row) {
                $result = $this->replaceComponentInPath((string) ($row->{$column} ?? ''), $sourceId, $targetId);
                if ($result['self_reference']) {
                    throw new InvalidArgumentException('Bu birleştirme bazı BOM yollarında ürünün kendisine bağlanmasına neden olur. Önce BOM yolunu düzenleyin.');
                }
            }
        }
    }

    private function pathContainsComponent(string $path, int $componentNo): bool
    {
        foreach ($this->splitPathSegments($path) as $segment) {
            $parts = $this->splitPathParts($segment);
            if (empty($parts)) {
                continue;
            }

            if (intval($parts[0] ?? 0) === $componentNo) {
                return true;
            }

            if (count($parts) >= 3 && intval($parts[1] ?? 0) === $componentNo) {
                return true;
            }
        }

        return false;
    }

    private function replaceComponentInPath(string $path, int $sourceId, int $targetId): array
    {
        $segments = [];
        $changed = false;
        $selfReference = false;

        foreach ($this->splitPathSegments($path) as $segment) {
            $parts = $this->splitPathParts($segment);
            if (empty($parts)) {
                continue;
            }

            if (intval($parts[0] ?? 0) === $sourceId) {
                $parts[0] = (string) $targetId;
                $changed = true;
            }

            if (count($parts) >= 3 && intval($parts[1] ?? 0) === $sourceId) {
                $parts[1] = (string) $targetId;
                $changed = true;
            }

            if (count($parts) >= 3 && intval($parts[0] ?? 0) === $targetId && intval($parts[1] ?? 0) === $targetId) {
                $selfReference = true;
            }

            $segments[] = implode('-', $parts);
        }

        return [
            'path' => implode(':', $segments),
            'changed' => $changed,
            'self_reference' => $selfReference,
        ];
    }

    private function splitPathSegments(string $path): array
    {
        return array_values(array_filter(array_map('trim', explode(':', $path)), fn ($segment) => $segment !== ''));
    }

    private function splitPathParts(string $segment): array
    {
        return array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
    }

    private function markSourceMerged(string $type, int $sourceId, int $targetId, mixed $actor = null): void
    {
        $table = $type === 'product' ? 'tbUrunler' : 'tbAraUrun';
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'MergedIntoNo')) {
            return;
        }

        $updates = ['MergedIntoNo' => $targetId];
        if (Schema::hasColumn($table, 'MergedAt')) {
            $updates['MergedAt'] = now();
        }
        if (Schema::hasColumn($table, 'MergedBy')) {
            $updates['MergedBy'] = $this->actorId($actor);
        }

        DB::table($table)->where('No', $sourceId)->update($updates);
    }

    private function insertMergeLog(string $type, object $source, object $target, array $preview, array $changes, mixed $actor = null): void
    {
        if (!Schema::hasTable('product_merge_logs')) {
            return;
        }

        DB::table('product_merge_logs')->insert([
            'merge_type' => $type,
            'source_no' => intval($source->No ?? 0),
            'source_name' => $type === 'product' ? trim((string) ($source->UrunID ?? '')) : trim((string) ($source->AraUrunAdi ?? '')),
            'target_no' => intval($target->No ?? 0),
            'target_name' => $type === 'product' ? trim((string) ($target->UrunID ?? '')) : trim((string) ($target->AraUrunAdi ?? '')),
            'actor_user_id' => $this->actorId($actor),
            'actor_name' => $this->actorName($actor),
            'preview' => json_encode($preview, JSON_UNESCAPED_UNICODE),
            'applied_changes' => json_encode($changes, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    private function actorId(mixed $actor): ?int
    {
        $id = intval($actor?->id ?? 0);

        return $id > 0 ? $id : null;
    }

    private function actorName(mixed $actor): ?string
    {
        $name = trim((string) ($actor?->name ?? '') . ' ' . (string) ($actor?->surname ?? ''));

        return $name !== '' ? $name : null;
    }

    private function tableHasColumns(string $table, array $columns): bool
    {
        if (!Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function componentTypeClass(string $type): string
    {
        $key = $this->normalizeKey($type);

        if (in_array($key, ['nihaiurun', 'nihayiurun', 'nihai', 'nihayi'], true)) {
            return 'final';
        }

        if (in_array($key, ['hammadde', 'ham'], true)) {
            return 'raw';
        }

        if (in_array($key, ['aramamul', 'aramamül', 'ara'], true)) {
            return 'component';
        }

        return $key !== '' ? $key : 'unknown';
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = strtr($value, [
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
            'â' => 'a',
            'î' => 'i',
            'û' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
