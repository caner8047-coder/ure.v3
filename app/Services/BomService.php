<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BOM (Bill of Materials) Engine — AnaSayfa.aspx.cs + SiparisApi.ashx'den birebir çeviri.
 *
 * Recursive fonksiyonlar:
 *  - birAdimOncesiUrunAdlari: Alt bileşen No'larını döndürür
 *  - oncekiUrunAdlariBul: Recursive tüm alt bileşenleri bulur
 *  - tumYolHazirla: BOM path string oluşturur
 *  - uretimAdetBelirle: Çarpan uygular
 *  - kacParca: Bileşen çarpanı
 *  - adetBelirle: Stok bazlı üretilebilir adet (darboğaz)
 *  - araStokTamponAzalt: Stok tamponu düşer
 *  - minAraUrunUretimiDenetle: Orchestrator
 *  - isEmriVerRecursive: Recursive görev oluşturma
 *  - restoreTamponFromJson: İptal sırasında tampon geri yükleme
 */
class BomService
{
    private ?bool $supportsProductionOrderTraceCache = null;

    public function resolveLegacyProductNo(int|string $urunIDNo): int
    {
        if (is_int($urunIDNo)) {
            return $urunIDNo;
        }

        $raw = trim((string) $urunIDNo);
        if ($raw === '') {
            return 0;
        }

        if (ctype_digit($raw)) {
            return intval($raw);
        }

        $match = DB::table('tbUrunler')->where('UrunID', $raw)->value('No');

        return $match !== null ? intval($match) : 502;
    }

    public function supportsProductionOrderTrace(): bool
    {
        if ($this->supportsProductionOrderTraceCache === null) {
            $this->supportsProductionOrderTraceCache =
                Schema::hasColumn('tbBolumHavuz', 'SiparisSatirNo') &&
                Schema::hasColumn('tbBolumHavuz', 'SiparisNo') &&
                Schema::hasColumn('tbPersonelGorev', 'SiparisSatirNo') &&
                Schema::hasColumn('tbPersonelGorev', 'SiparisNo') &&
                Schema::hasColumn('tbGorevler', 'SiparisSatirNo') &&
                Schema::hasColumn('tbGorevler', 'SiparisNo');
        }

        return $this->supportsProductionOrderTraceCache;
    }

    public function normalizeTraceContext(array $traceContext = []): array
    {
        return [
            'siparisSatirNo' => max(0, intval($traceContext['siparisSatirNo'] ?? $traceContext['SiparisSatirNo'] ?? 0)),
            'siparisNo' => trim((string) ($traceContext['siparisNo'] ?? $traceContext['SiparisNo'] ?? '')),
        ];
    }

    public function traceContextFromRecord(object|array|null $record): array
    {
        if ($record === null) {
            return $this->normalizeTraceContext();
        }

        if (is_array($record)) {
            return $this->normalizeTraceContext($record);
        }

        return $this->normalizeTraceContext([
            'SiparisSatirNo' => $record->SiparisSatirNo ?? 0,
            'SiparisNo' => $record->SiparisNo ?? '',
        ]);
    }

    public function buildTracePayload(array $traceContext = []): array
    {
        if (!$this->supportsProductionOrderTrace()) {
            return [];
        }

        $trace = $this->normalizeTraceContext($traceContext);

        if ($trace['siparisSatirNo'] <= 0 && $trace['siparisNo'] === '') {
            return [];
        }

        return [
            'SiparisSatirNo' => $trace['siparisSatirNo'] > 0 ? $trace['siparisSatirNo'] : null,
            'SiparisNo' => $trace['siparisNo'] !== '' ? $trace['siparisNo'] : null,
        ];
    }

    public function scopeQueryToTrace($query, array $traceContext = [], bool $matchUntraced = false, ?string $tableAlias = null)
    {
        if (!$this->supportsProductionOrderTrace()) {
            return $query;
        }

        $trace = $this->normalizeTraceContext($traceContext);
        $siparisSatirNoColumn = $tableAlias ? $tableAlias . '.SiparisSatirNo' : 'SiparisSatirNo';
        $siparisNoColumn = $tableAlias ? $tableAlias . '.SiparisNo' : 'SiparisNo';

        if ($trace['siparisSatirNo'] > 0) {
            return $query->where($siparisSatirNoColumn, $trace['siparisSatirNo']);
        }

        if ($trace['siparisNo'] !== '') {
            return $query->where($siparisNoColumn, $trace['siparisNo']);
        }

        if ($matchUntraced) {
            return $query->where(function ($subQuery) use ($siparisSatirNoColumn) {
                $subQuery->whereNull($siparisSatirNoColumn)
                    ->orWhere($siparisSatirNoColumn, 0);
            });
        }

        return $query;
    }

    /**
     * Alt bileşen No'ları — tbAraUrun.Yol'dan parse eder.
     * Yol formatı: "3-4:4-8" → bileşen 3 (4 adet) ve bileşen 4 (8 adet)
     * Döndürür: "3:4" (sadece No'lar)
     */
    public function birAdimOncesiUrunAdlari(string $refUrunAdiNo): string
    {
        $araNo = intval($refUrunAdiNo);
        $yol = DB::table('tbAraUrun')->where('No', $araNo)->value('Yol') ?? '';
        $yol = trim($yol);
        if (empty($yol)) return '';

        $result = '';
        if (str_contains($yol, ':')) {
            foreach (explode(':', $yol) as $seg) {
                $result .= explode('-', $seg)[0] . ':';
            }
        } else {
            $result .= trim(explode('-', $yol)[0]) . ':';
        }

        return rtrim($result, ':');
    }

    /**
     * Recursive tüm alt bileşenleri bulur.
     */
    public function oncekiUrunAdlariBul(string $refUrunAdiNo, int $depth = 0): string
    {
        if ($depth > 20) return $refUrunAdiNo;
        $str = $refUrunAdiNo;
        $source = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if ($source === '') return $refUrunAdiNo;
        $arr = str_contains($source, ':') ? explode(':', $source) : [$source];
        foreach ($arr as $item) {
            $str .= ':' . $this->oncekiUrunAdlariBul($item, $depth + 1);
        }
        return $str;
    }

    private function yolCarpani(array $parts): string
    {
        return trim((string) ($parts[2] ?? $parts[1] ?? '1'));
    }

    /**
     * BOM path string oluşturur. Format: "sourceNo-parentNo-multiplier:..."
     */
    public function tumYolHazirla(string $refUrunAdiNo): string
    {
        $allNodesStr = $this->oncekiUrunAdlariBul($refUrunAdiNo, 0);
        $allNodes = array_reverse(explode(':', $allNodesStr));
        $result = '';
        foreach ($allNodes as $nodeStr) {
            if (empty($nodeStr)) continue;
            $nodeNo = intval($nodeStr);
            $yol = trim(DB::table('tbAraUrun')->where('No', $nodeNo)->value('Yol') ?? '');
            if (empty($yol)) continue;
            if (str_contains($yol, ':')) {
                foreach (explode(':', $yol) as $seg) {
                    $parts = explode('-', $seg);
                    $result .= $parts[0] . '-' . $nodeStr . '-' . $this->yolCarpani($parts) . ':';
                }
            } else {
                $parts = explode('-', $yol);
                $result .= $parts[0] . '-' . $nodeStr . '-' . $this->yolCarpani($parts) . ':';
            }
        }
        return rtrim($result, ':');
    }

    /**
     * Çarpan uygula → Üretilmesi gereken toplam adet.
     */
    public function uretimAdetBelirle(string $refUrunAdiNo, string $yol, int $uretimAdet): int
    {
        if (str_contains($yol, ':')) {
            foreach (explode(':', $yol) as $seg) {
                if (str_contains(':' . $seg, ':' . $refUrunAdiNo . '-')) {
                    return intval(explode('-', $seg)[2] ?? 1) * $uretimAdet;
                }
            }
            return $uretimAdet;
        }
        if ($yol === '' || !str_contains($yol, '-')) return $uretimAdet;
        return intval(explode('-', $yol)[2] ?? 1) * $uretimAdet;
    }

    /**
     * Bileşen çarpanı.
     */
    public function kacParca(string $urunID, string $refUrunAdiNo): int
    {
        $source = $this->tumYolHazirla($urunID);
        if (str_contains($source, ':')) {
            foreach (explode(':', $source) as $seg) {
                if (str_contains(':' . $seg, ':' . $refUrunAdiNo . '-')) {
                    return intval(explode('-', $seg)[2] ?? 1);
                }
            }
            return 1;
        }
        if ($source === '' || !str_contains($source, '-')) return 1;
        return intval(explode('-', $source)[2] ?? 1);
    }

    /**
     * Boşta/tampon stok bazlı üretilebilir adet — darboğaz hesaplaması.
     */
    public function adetBelirle(string $refUrunAdiNo): int
    {
        $str = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if (empty($str)) return -1;
        $minVal = 1000000;
        foreach (explode(':', $str) as $subNoStr) {
            $subNo = intval($subNoStr);
            $mult = $this->kacParca($refUrunAdiNo, $subNoStr);
            $stock = intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $subNo)->sum('TamponMiktar') ?? 0);
            $producible = ($mult > 0) ? intval(floor($stock / $mult)) : 0;
            if ($producible < $minVal) $minVal = $producible;
        }
        return $minVal;
    }

    public function adetBelirleForTrace(string $refUrunAdiNo, array $traceContext = []): int
    {
        $str = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if (empty($str)) return -1;

        $minVal = 1000000;
        foreach (explode(':', $str) as $subNoStr) {
            $subNo = intval($subNoStr);
            if ($subNo <= 0) {
                continue;
            }

            $mult = $this->kacParca($refUrunAdiNo, $subNoStr);
            $freeStock = intval(
                DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', $subNo)
                    ->sum('TamponMiktar') ?? 0
            );
            $orderHeldStock = $this->orderHeldStockQuantity($subNo, $traceContext);
            $fallbackHeldStock = $orderHeldStock > 0 ? 0 : $this->untracedHeldStockQuantity($subNo);
            $usableStock = $freeStock + $orderHeldStock + $fallbackHeldStock;
            $producible = ($mult > 0) ? intval(floor($usableStock / $mult)) : 0;
            if ($producible < $minVal) $minVal = $producible;
        }

        return $minVal;
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

    public function orderHeldStockQuantity(int $componentNo, array $traceContext = []): int
    {
        $trace = $this->normalizeTraceContext($traceContext);
        $orderItemNo = $trace['siparisSatirNo'];
        $orderNo = $trace['siparisNo'];
        if ($componentNo <= 0 || ($orderItemNo <= 0 && $orderNo === '')) {
            return 0;
        }

        $reservedFromOrder = $orderItemNo > 0
            ? $this->reservedBufferQuantityForOrder($componentNo, $orderItemNo)
            : 0;
        $movementDelta = 0;
        $hasLoggedProductionStockIn = false;

        if (Schema::hasTable('stock_movements')) {
            $movementQuery = DB::table('stock_movements')
                ->where('component_no', $componentNo)
                ->whereIn('movement_type', [
                    'production_stock_in',
                    'production_stock_in_reversed',
                    'production_component_consumed_by_parent',
                    'order_stock_out',
                    'order_stock_out_reversed',
                    'order_auto_stock_out',
                    'order_auto_stock_out_reversed',
                ]);

            $this->scopeTraceColumns($movementQuery, $orderItemNo, $orderNo, 'order_item_no', 'order_no');

            $movementDelta = intval($movementQuery->sum('quantity_delta') ?? 0);

            $productionStockInQuery = DB::table('stock_movements')
                ->where('component_no', $componentNo)
                ->whereIn('movement_type', [
                    'production_stock_in',
                    'production_stock_in_reversed',
                ]);

            $this->scopeTraceColumns($productionStockInQuery, $orderItemNo, $orderNo, 'order_item_no', 'order_no');
            $hasLoggedProductionStockIn = $productionStockInQuery->exists();
        }

        $legacyCompletedFallback = $hasLoggedProductionStockIn
            ? 0
            : $this->legacyCompletedProductionQuantityForOrder($componentNo, $orderItemNo, $orderNo);

        return max(0, $reservedFromOrder + $movementDelta + $legacyCompletedFallback);
    }

    private function scopeTraceColumns(
        $query,
        int $orderItemNo,
        string $orderNo,
        string $orderItemColumn,
        string $orderNoColumn
    ) {
        return $query->where(function ($query) use ($orderItemNo, $orderNo, $orderItemColumn, $orderNoColumn) {
            if ($orderItemNo > 0) {
                $query->where($orderItemColumn, $orderItemNo);
            }

            if ($orderNo !== '') {
                $method = $orderItemNo > 0 ? 'orWhere' : 'where';
                $query->{$method}($orderNoColumn, $orderNo);
            }
        });
    }

    private function legacyCompletedProductionQuantityForOrder(int $componentNo, int $orderItemNo, string $orderNo): int
    {
        if (
            $componentNo <= 0
            || ($orderItemNo <= 0 && $orderNo === '')
            || !Schema::hasTable('tbGorevler')
            || !Schema::hasColumn('tbGorevler', 'SiparisSatirNo')
            || !Schema::hasColumn('tbGorevler', 'SiparisNo')
        ) {
            return 0;
        }

        $query = DB::table('tbGorevler')
            ->where('AraUrunAdiNo', $componentNo)
            ->where('ToplamAdet', '>', 0);

        $this->scopeTraceColumns($query, $orderItemNo, $orderNo, 'SiparisSatirNo', 'SiparisNo');

        return max(0, intval($query->sum('ToplamAdet') ?? 0));
    }

    public function directChildRequirements(string $refUrunAdiNo, int $parentQuantity): array
    {
        $parentQuantity = max(0, $parentQuantity);
        if ($parentQuantity <= 0) {
            return [];
        }

        if (!Schema::hasTable('tbAraUrun')) {
            return [];
        }

        $yol = trim((string) (DB::table('tbAraUrun')->where('No', intval($refUrunAdiNo))->value('Yol') ?? ''));
        if ($yol === '') {
            return [];
        }

        $requirements = [];
        foreach (explode(':', $yol) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $childNo = intval($parts[0] ?? 0);
            $multiplier = max(1, intval($this->yolCarpani($parts)));
            if ($childNo <= 0) {
                continue;
            }

            $requirements[$childNo] = intval($requirements[$childNo] ?? 0) + ($parentQuantity * $multiplier);
        }

        return $requirements;
    }

    public function taskQuantityToCheck(object $task): int
    {
        $readyQuantity = max(0, intval($task->Adet ?? 0));
        $waitingQuantity = max(0, intval($task->BekleyenAdet ?? 0));

        return $readyQuantity > 0 ? $readyQuantity : $waitingQuantity;
    }

    public function taskReadinessIssue(object $task, ?int $quantityToCheck = null): ?string
    {
        $quantityToCheck = $quantityToCheck === null
            ? $this->taskQuantityToCheck($task)
            : max(0, intval($quantityToCheck));

        if ($quantityToCheck <= 0) {
            return 'Parça/stok bekliyor.';
        }

        $requirements = $this->directChildRequirements(strval($task->AraUrunAdiNo ?? 0), $quantityToCheck);
        if (empty($requirements)) {
            return null;
        }

        $traceContext = $this->traceContextFromRecord($task);
        foreach ($requirements as $componentNo => $requiredQuantity) {
            $componentNo = intval($componentNo);
            $requiredQuantity = max(0, intval($requiredQuantity));
            if ($componentNo <= 0 || $requiredQuantity <= 0) {
                continue;
            }

            $heldQuantity = $this->orderHeldStockQuantity($componentNo, $traceContext);
            if ($heldQuantity <= 0) {
                $heldQuantity = min($requiredQuantity, $this->untracedHeldStockQuantity($componentNo));
            }

            $result = $this->inspectComponentStockAvailability(
                $componentNo,
                $requiredQuantity,
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

    public function inspectComponentStockAvailability(
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

        $component = Schema::hasTable('tbAraUrun')
            ? DB::table('tbAraUrun')->where('No', $componentNo)->first(['No', 'AraUrunAdi', 'BolumAdiNo'])
            : null;
        $stock = $this->componentStockSnapshot($componentNo);
        $totalStock = intval($stock['total'] ?? 0);
        $freeStock = intval($stock['free'] ?? 0);
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

    public function componentStockSnapshot(int $componentNo): array
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

    private function reservedBufferQuantityForOrder(int $componentNo, int $orderItemNo): int
    {
        if (
            $componentNo <= 0
            || $orderItemNo <= 0
            || !Schema::hasTable('tbSiparisSatir')
            || !Schema::hasColumn('tbSiparisSatir', 'TamponDusumleri')
        ) {
            return 0;
        }

        return $this->reservedBufferQuantityFromJson(
            $componentNo,
            DB::table('tbSiparisSatir')->where('No', $orderItemNo)->value('TamponDusumleri')
        );
    }

    private function reservedBufferQuantityFromJson(int $componentNo, mixed $json): int
    {
        $entries = json_decode((string) $json, true);
        if (!is_array($entries)) {
            return 0;
        }

        $quantity = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryComponentNo = intval($entry['araNo'] ?? $entry['AraUrunAdiNo'] ?? $entry['ara_urun_no'] ?? 0);
            if ($entryComponentNo !== $componentNo) {
                continue;
            }

            $quantity += max(0, intval($entry['adet'] ?? $entry['Adet'] ?? $entry['quantity'] ?? 0));
        }

        return $quantity;
    }

    public function activeBufferReservationQuantity(int $componentNo): int
    {
        if (
            $componentNo <= 0
            || !Schema::hasTable('tbSiparisSatir')
            || !Schema::hasColumn('tbSiparisSatir', 'TamponDusumleri')
        ) {
            return 0;
        }

        $needles = [
            '%"araNo":' . $componentNo . '%',
            '%"araNo": ' . $componentNo . '%',
            '%"AraUrunAdiNo":' . $componentNo . '%',
            '%"AraUrunAdiNo": ' . $componentNo . '%',
            '%"ara_urun_no":' . $componentNo . '%',
            '%"ara_urun_no": ' . $componentNo . '%',
        ];

        $query = DB::table('tbSiparisSatir')
            ->whereNotNull('TamponDusumleri')
            ->where('TamponDusumleri', '!=', '')
            ->where(function ($query) use ($needles) {
                foreach ($needles as $needle) {
                    $query->orWhere('TamponDusumleri', 'like', $needle);
                }
            });

        if (Schema::hasColumn('tbSiparisSatir', 'Aktif')) {
            $query->where('Aktif', 1);
        }

        if (Schema::hasColumn('tbSiparisSatir', 'Durum')) {
            $query->whereNotIn('Durum', ['Pasif', 'StokKarsilandi']);
        }

        return intval($query
            ->get(['TamponDusumleri'])
            ->sum(fn ($row) => $this->reservedBufferQuantityFromJson($componentNo, $row->TamponDusumleri ?? null)));
    }

    public function hasActiveBufferReservation(int $componentNo): bool
    {
        return $this->activeBufferReservationQuantity($componentNo) > 0;
    }

    public function stoklaUretimKarsilaniyor(string $refUrunAdiNo, int $uretimAdet): bool
    {
        if ($uretimAdet <= 0) {
            return true;
        }

        return $this->adetBelirle($refUrunAdiNo) >= $uretimAdet;
    }

    public function descendantComponentNos(string $refUrunAdiNo): array
    {
        $allNodes = explode(':', $this->oncekiUrunAdlariBul($refUrunAdiNo));
        $selfNo = intval($refUrunAdiNo);
        $descendants = [];

        foreach ($allNodes as $node) {
            $nodeNo = intval($node);
            if ($nodeNo > 0 && $nodeNo !== $selfNo) {
                $descendants[$nodeNo] = true;
            }
        }

        return array_keys($descendants);
    }

    public function hasOpenDescendantWork(string $refUrunAdiNo, array $traceContext = []): bool
    {
        $descendants = $this->descendantComponentNos($refUrunAdiNo);
        if (empty($descendants)) {
            return false;
        }

        if (!Schema::hasTable('tbBolumHavuz') || !Schema::hasTable('tbPersonelGorev')) {
            return false;
        }

        $poolQuery = DB::table('tbBolumHavuz')
            ->whereIn('AraUrunAdiNo', $descendants)
            ->where('ToplamAdet', '>', 0);
        $this->scopeQueryToTrace($poolQuery, $traceContext, true);

        if ($poolQuery->exists()) {
            return true;
        }

        $personnelQuery = DB::table('tbPersonelGorev')
            ->whereIn('AraUrunAdiNo', $descendants)
            ->where(function ($query) {
                $query->where('Adet', '>', 0)
                    ->orWhere('BekleyenAdet', '>', 0);
            });
        $this->scopeQueryToTrace($personnelQuery, $traceContext, true);

        return $personnelQuery->exists();
    }

    public function effectivePoolAssignableQuantity(object $poolRow, ?int $candidate = null): int
    {
        $total = max(0, intval($poolRow->ToplamAdet ?? 0));
        if ($total <= 0) {
            return 0;
        }

        if ($this->hasOpenDescendantWork(strval($poolRow->AraUrunAdiNo ?? 0), $this->traceContextFromRecord($poolRow))) {
            return 0;
        }

        $stockAssignable = $this->adetBelirleForTrace(
            strval($poolRow->AraUrunAdiNo ?? 0),
            $this->traceContextFromRecord($poolRow)
        );

        if ($stockAssignable < 0) {
            $stockAssignable = $candidate ?? $total;
        }

        return max(0, min($total, $stockAssignable));
    }

    public function personnelTaskReadySplit(object $task, ?int $totalQuantity = null): array
    {
        $readyQuantity = max(0, intval($task->Adet ?? 0));
        $waitingQuantity = max(0, intval($task->BekleyenAdet ?? 0));
        $total = $totalQuantity === null
            ? $readyQuantity + $waitingQuantity
            : max(0, intval($totalQuantity));

        if ($total <= 0) {
            return ['ready' => 0, 'waiting' => 0, 'capacity' => 0];
        }

        $capacity = $this->personnelTaskProductionCapacity(
            intval($task->AraUrunAdiNo ?? 0),
            $this->traceContextFromRecord($task)
        );

        if ($capacity < 0) {
            return ['ready' => $total, 'waiting' => 0, 'capacity' => $total];
        }

        $ready = max(0, min($total, $capacity));

        return [
            'ready' => $ready,
            'waiting' => max(0, $total - $ready),
            'capacity' => max(0, $capacity),
        ];
    }

    private function personnelTaskProductionCapacity(int $componentNo, array $traceContext = []): int
    {
        if ($componentNo <= 0 || !Schema::hasTable('tbAraUrun')) {
            return -1;
        }

        $subComponents = trim($this->birAdimOncesiUrunAdlari((string) $componentNo));
        if ($subComponents === '') {
            return -1;
        }

        if (!Schema::hasTable('tbBolumAraStok')) {
            return 0;
        }

        $trace = $this->normalizeTraceContext($traceContext);
        $hasTrace = $trace['siparisSatirNo'] > 0 || $trace['siparisNo'] !== '';
        $minVal = 1000000;

        foreach (explode(':', $subComponents) as $subNoStr) {
            $subNo = intval($subNoStr);
            if ($subNo <= 0) {
                continue;
            }

            $multiplier = $this->kacParca((string) $componentNo, (string) $subNo);
            if ($multiplier <= 0) {
                $minVal = 0;
                continue;
            }

            if ($hasTrace) {
                $freeStock = intval(
                    DB::table('tbBolumAraStok')
                        ->where('AraUrunAdiNo', $subNo)
                        ->sum('TamponMiktar') ?? 0
                );
                $orderHeldStock = $this->orderHeldStockQuantity($subNo, $trace);
                $fallbackHeldStock = $orderHeldStock > 0 ? 0 : $this->untracedHeldStockQuantity($subNo);
                $usableStock = $freeStock + $orderHeldStock + $fallbackHeldStock;
            } else {
                $usableStock = intval(
                    DB::table('tbBolumAraStok')
                        ->where('AraUrunAdiNo', $subNo)
                        ->sum('Adet') ?? 0
                );
            }

            $producible = intval(floor(max(0, $usableStock) / $multiplier));
            if ($producible < $minVal) {
                $minVal = $producible;
            }
        }

        return $minVal === 1000000 ? 0 : $minVal;
    }

    public function refreshPoolReadinessForTrace(array $traceContext = [], ?int $rootComponentNo = null): void
    {
        $query = DB::table('tbBolumHavuz')->where('ToplamAdet', '>', 0);
        $this->scopeQueryToTrace($query, $traceContext, true);

        if ($rootComponentNo && $rootComponentNo > 0) {
            $componentNos = array_merge([$rootComponentNo], $this->descendantComponentNos((string) $rootComponentNo));
            $query->whereIn('AraUrunAdiNo', array_values(array_unique($componentNos)));
        }

        $rows = $query->get();
        foreach ($rows as $row) {
            $newAssignable = $this->effectivePoolAssignableQuantity($row);
            if (intval($row->Adet ?? 0) !== $newAssignable) {
                DB::table('tbBolumHavuz')->where('No', $row->No)->update(['Adet' => $newAssignable]);
            }
        }
    }

    /**
     * Nihai/Ara ürün için toplam üretilebilir maksimum adeti stok dar bogazina göre hesaplar.
     */
    public function uretilebilirNihaiAdet(int $urunNo, string $tur): int
    {
        try {
            if ($tur === 'Ara Mamül') {
                return max(0, $this->adetBelirle((string)$urunNo));
            } else {
                $yol = DB::table('tbUrunler')->where('No', $urunNo)->value('AraAdlarYol');
                if (empty($yol)) return 0;
                
                $minVal = 1000000;
                $components = explode(':', trim($yol));
                
                foreach ($components as $parca) {
                    $parts = explode('-', trim($parca));
                    if (count($parts) >= 2) {
                        $altNo = intval($parts[0]);
                        $mult = floatval($parts[1]);
                        
                        $stock = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $altNo)->sum('Adet');
                        $stock = $stock ? floatval($stock) : 0;
                        
                        $producible = ($mult > 0) ? intval(floor($stock / $mult)) : 0;
                        if ($producible < $minVal) $minVal = $producible;
                    }
                }
                return $minVal === 1000000 ? 0 : $minVal;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Stok tamponu düşer ve düşürülen miktarı döndürür.
     */
    public function araStokTamponAzalt(string $araUrunAdiNo, int $adet, array $traceContext = []): int
    {
        return array_sum(array_map(
            fn ($reservation) => max(0, intval($reservation['adet'] ?? 0)),
            $this->araStokTamponAzaltDetayli($araUrunAdiNo, $adet, $traceContext)
        ));
    }

    /**
     * Tamponu birden fazla stok satırından güvenli şekilde düşer.
     * Aynı ara ürün farklı bölüm/depo satırlarında durabildiği için first()
     * kullanmak stok varken görev açılmasına sebep oluyordu.
     */
    public function araStokTamponAzaltDetayli(string $araUrunAdiNo, int $adet, array $traceContext = []): array
    {
        $araNo = intval($araUrunAdiNo);
        $remaining = max(0, $adet);
        if ($araNo <= 0 || $remaining <= 0) {
            return [];
        }

        $departmentNo = intval(DB::table('tbAraUrun')->where('No', $araNo)->value('BolumAdiNo') ?? 0);
        $query = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $araNo)
            ->where('TamponMiktar', '>', 0);

        if ($departmentNo > 0) {
            $query->orderByRaw('CASE WHEN BolumAdiNo = ? THEN 0 ELSE 1 END', [$departmentNo]);
        }

        $rows = $query
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        $trace = $this->normalizeTraceContext($traceContext);
        $reservations = [];

        foreach ($rows as $stockBefore) {
            if ($remaining <= 0) {
                break;
            }

            $available = max(0, min(
                intval($stockBefore->Adet ?? 0),
                intval($stockBefore->TamponMiktar ?? 0)
            ));
            if ($available <= 0) {
                continue;
            }

            $azaltilan = min($remaining, $available);
            $yeniTampon = max(0, intval($stockBefore->TamponMiktar ?? 0) - $azaltilan);

            DB::table('tbBolumAraStok')
                ->where('No', $stockBefore->No)
                ->update(['TamponMiktar' => $yeniTampon]);

            $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();
            $this->logStockMovement($stockBefore, $stockAfter, [
                'movement_type' => 'work_order_buffer_reserved',
                'source_type' => $trace['siparisSatirNo'] > 0 ? 'work_order_reservation' : 'component_reservation',
                'source_id' => $trace['siparisSatirNo'] > 0 ? $trace['siparisSatirNo'] : $araNo,
                'order_item_no' => $trace['siparisSatirNo'] > 0 ? $trace['siparisSatirNo'] : null,
                'order_no' => $trace['siparisNo'] !== '' ? $trace['siparisNo'] : null,
                'description' => 'İş emri oluşturulurken mevcut tampon stoktan ayrıldı.',
                'metadata' => [
                    'component_no' => $araNo,
                    'stock_row_no' => intval($stockBefore->No ?? 0),
                    'requested_buffer_quantity' => $adet,
                    'reserved_buffer_quantity' => $azaltilan,
                    'trace_context' => $trace,
                ],
            ]);

            $reservations[] = [
                'araNo' => $araNo,
                'adet' => $azaltilan,
                'stokNo' => intval($stockBefore->No ?? 0),
                'bolumNo' => intval($stockBefore->BolumAdiNo ?? 0),
            ];

            $remaining -= $azaltilan;
        }

        return $reservations;
    }

    /**
     * İptal sırasında tampon stoğu geri yükle.
     */
    public function restoreTamponFromJson(?string $tamponDusumleriJson): void
    {
        if (empty($tamponDusumleriJson)) return;
        try {
            $dusumleri = json_decode($tamponDusumleriJson, true);
            if (!is_array($dusumleri)) return;
            foreach ($dusumleri as $dusum) {
                $araNo = intval($dusum['araNo'] ?? 0);
                $adet = intval($dusum['adet'] ?? 0);
                $stokNo = intval($dusum['stokNo'] ?? $dusum['stockNo'] ?? $dusum['stok_no'] ?? 0);
                if ($araNo > 0 && $adet > 0) {
                    $this->restoreTamponQuantity($araNo, $adet, $stokNo);
                }
            }
        } catch (\Exception $e) { /* sessiz */ }
    }

    private function restoreTamponQuantity(int $araNo, int $adet, int $preferredStockNo = 0): void
    {
        $remaining = max(0, $adet);
        if ($araNo <= 0 || $remaining <= 0) {
            return;
        }

        $rows = collect();
        if ($preferredStockNo > 0) {
            $preferred = DB::table('tbBolumAraStok')
                ->where('No', $preferredStockNo)
                ->where('AraUrunAdiNo', $araNo)
                ->lockForUpdate()
                ->first();
            if ($preferred) {
                $rows->push($preferred);
            }
        }

        $otherRows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $araNo)
            ->when($preferredStockNo > 0, fn ($query) => $query->where('No', '!=', $preferredStockNo))
            ->whereRaw('COALESCE(TamponMiktar, 0) < COALESCE(Adet, 0)')
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        $rows = $rows->concat($otherRows);

        foreach ($rows as $stockBefore) {
            if ($remaining <= 0) {
                break;
            }

            $capacity = max(0, intval($stockBefore->Adet ?? 0) - intval($stockBefore->TamponMiktar ?? 0));
            if ($capacity <= 0) {
                continue;
            }

            $artis = min($remaining, $capacity);
            DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->update([
                'TamponMiktar' => intval($stockBefore->TamponMiktar ?? 0) + $artis,
            ]);

            $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();
            $this->logStockMovement($stockBefore, $stockAfter, [
                'movement_type' => 'work_order_buffer_released',
                'source_type' => 'work_order_cancel',
                'source_id' => $araNo,
                'description' => 'İş emri iptali nedeniyle tampon stok iade edildi.',
                'metadata' => [
                    'component_no' => $araNo,
                    'stock_row_no' => intval($stockBefore->No ?? 0),
                    'released_buffer_quantity' => $artis,
                    'requested_release_quantity' => $adet,
                ],
            ]);

            $remaining -= $artis;
        }
    }

    /**
     * Orchestrator — Tek bir ara ürün için iş emri oluşturur.
     */
    public function minAraUrunUretimiDenetle(
        int|string $urunIDNo, string $yol, string $refUrunAdiNo,
        int $uretimAdet, string $aciklama, string $stokDurum,
        array &$tamponDusumleri = [],
        array $traceContext = []
    ): int {
        try {
            $araUrunNo = intval($refUrunAdiNo);
            $legacyUrunIDNo = $this->resolveLegacyProductNo($urunIDNo);
            $normalizedTraceContext = $this->normalizeTraceContext($traceContext);
            $uretilebilir = $this->adetBelirle($refUrunAdiNo);
            $uretimAdet = $this->uretimAdetBelirle($refUrunAdiNo, $yol, $uretimAdet);

            if ($stokDurum === 'StokDahil') {
                $reservations = $this->araStokTamponAzaltDetayli($refUrunAdiNo, $uretimAdet, $normalizedTraceContext);
                $azaltilan = array_sum(array_map(
                    fn ($reservation) => max(0, intval($reservation['adet'] ?? 0)),
                    $reservations
                ));
                $uretimAdet -= $azaltilan;
                foreach ($reservations as $reservation) {
                    if (max(0, intval($reservation['adet'] ?? 0)) > 0) {
                        $tamponDusumleri[] = $reservation;
                    }
                }
            }

            if ($uretilebilir > $uretimAdet || $uretilebilir < 0) $uretilebilir = $uretimAdet;
            if ($uretimAdet <= 0) return 0;

            $bolumAdiNo = intval(DB::table('tbAraUrun')->where('No', $araUrunNo)->value('BolumAdiNo') ?? 0);
            $legacyDate = now()->format('d/m/Y');
            $legacyTime = now()->format('H:i');
            $legacyAciklama = trim($aciklama);
            // ASP.NET minAraUrunUretimiDenetle aktif sürümü her çağrıda yeni
            // tbBolumHavuz satırı açar; aynı ara ürünü burada birleştirmek BOM
            // ile havuz satırlarının bire bir izini bozuyordu.
            DB::table('tbBolumHavuz')->insert(array_merge([
                'UrunIDNo' => $legacyUrunIDNo,
                'AraUrunAdiNo' => $araUrunNo,
                'ToplamAdet' => $uretimAdet,
                'Adet' => $uretilebilir,
                'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                'Aciklama' => $legacyAciklama !== '' ? $legacyAciklama : null,
                'GorevBaslangicTarihi' => $legacyDate,
                'GorevBaslangicSaati' => $legacyTime,
            ], $this->buildTracePayload($normalizedTraceContext)));

            return $uretimAdet;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Recursive görev oluşturma — BOM ağacını traverse eder.
     */
    public function isEmriVerRecursive(
        string $refUrunAdiNo, int $uretimAdet, string $aciklama,
        string $yol, int|string $urunIDNo, string $stokDurum,
        array &$tamponDusumleri = [],
        array $traceContext = []
    ): void {
        $subComponents = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if (empty($subComponents)) return;

        foreach (explode(':', $subComponents) as $sub) {
            if (empty($sub)) continue;
            $subSubs = $this->birAdimOncesiUrunAdlari($sub);

            $result = $this->minAraUrunUretimiDenetle(
                $urunIDNo,
                $yol,
                $sub,
                $uretimAdet,
                $aciklama,
                $stokDurum,
                $tamponDusumleri,
                $traceContext
            );
            if ($result > 0 && trim($subSubs) !== '') {
                $this->isEmriVerRecursive(
                    $sub,
                    $result,
                    $aciklama,
                    $yol,
                    $urunIDNo,
                    $stokDurum,
                    $tamponDusumleri,
                    $traceContext
                );
            }
        }
    }

    /**
     * İş emri geçmişine kayıt ekle.
     */
    public function logIsEmriGecmisi(
        int $siparisSatirNo, ?string $siparisNo, ?string $musteri,
        ?string $urunAdi, ?string $sistemUrunAdi, ?int $adet,
        ?string $kategori, string $islemTipi, ?int $gorevNo = null,
        ?int $eslesenUrunNo = null, ?string $eslesenUrunTur = null,
        ?string $kargoSonTeslim = null
    ): void {
        DB::table('tbIsEmriGecmisi')->insert([
            'SiparisSatirNo' => $siparisSatirNo,
            'SiparisNo' => $siparisNo,
            'Musteri' => $musteri,
            'UrunAdi' => $urunAdi,
            'SistemUrunAdi' => $sistemUrunAdi,
            'Adet' => $adet,
            'Kategori' => $kategori,
            'IsEmriTarihi' => now(),
            'IslemTipi' => $islemTipi,
            'IslemTarihi' => now(),
            'GorevNo' => $gorevNo,
            'EslesenUrunNo' => $eslesenUrunNo,
            'EslesenUrunTur' => $eslesenUrunTur,
            'KargoSonTeslim' => $kargoSonTeslim,
        ]);
    }

    // ================================================================
    // sonrakiUrunAdlari — Bu parçayı YOL'unda kullanan üst ürünleri bulur
    // ================================================================
    public function sonrakiUrunAdlari(string $refUrunAdiNo): string
    {
        $results = [];

        $allAraUrunler = DB::table('tbAraUrun')
            ->whereNotNull('Yol')
            ->where('Yol', '!=', '')
            ->select('No', 'Yol')
            ->get();

        foreach ($allAraUrunler as $araUrun) {
            $yolParcalari = explode(':', $araUrun->Yol);
            foreach ($yolParcalari as $parca) {
                $parts = explode('-', $parca);
                if (isset($parts[0]) && trim($parts[0]) === $refUrunAdiNo) {
                    $results[] = strval($araUrun->No);
                    break;
                }
            }
        }

        return implode(':', array_unique($results));
    }

    // ================================================================
    // SonrakiUrunAdetleriniGuncelle2
    // Stok değiştiğinde, o parçayı kullanan üst ürünlerin havuzdaki
    // üretilebilir adetlerini (Adet) yeniden hesaplar.
    // ================================================================
    public function sonrakiUrunAdetleriniGuncelle(string $refUrunAdiNo, ?int $eskiAdet = null, ?int $yeniAdet = null): void
    {
        $oncekiUrunleriKontrolEt = false;
        $sonrakiAdlar = $this->sonrakiUrunAdlari($refUrunAdiNo);
        if (empty($sonrakiAdlar)) return;
        if (!Schema::hasTable('tbBolumHavuz')) return;

        $strings = explode(':', $sonrakiAdlar);

        foreach ($strings as $araNo) {
            $araNo = trim($araNo);
            if (empty($araNo)) continue;

            $araGorevler = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($araNo))
                ->get();

            foreach ($araGorevler as $gorev) {
                $newAdet = $this->effectivePoolAssignableQuantity($gorev);
                DB::table('tbBolumHavuz')->where('No', $gorev->No)->update(['Adet' => $newAdet]);
                $oncekiUrunleriKontrolEt = true;
            }
        }

        if ($eskiAdet !== null && $yeniAdet !== null && $yeniAdet < $eskiAdet && $oncekiUrunleriKontrolEt) {
            try {
                $aciklama = 'Stok guncelleme ile verilen görev...';
                $hazirdaVerilenIsSayisi = $this->verilenIsSayisi($refUrunAdiNo);
                $stokMiktar = $this->stokGetir($refUrunAdiNo);
                $uretimAdet = ($eskiAdet - $yeniAdet) + $hazirdaVerilenIsSayisi + $stokMiktar;
                $yol = $this->tumYolHazirla($refUrunAdiNo);

                $this->minAraUrunUretimiDenetle('Ara Mamül', $yol, $refUrunAdiNo, $uretimAdet, $aciklama, 'StokDahil');

                $recursiveUretimAdet = $uretimAdet - $stokMiktar;
                if (
                    $recursiveUretimAdet > 0
                    && trim($this->birAdimOncesiUrunAdlari($refUrunAdiNo)) !== ''
                ) {
                    $this->isEmriVerRecursive(
                        $refUrunAdiNo,
                        $recursiveUretimAdet,
                        $aciklama,
                        $yol,
                        'Ara Mamül',
                        'StokDahil'
                    );
                }
            } catch (\Throwable $e) {
                // Legacy akışta bu dal sessiz çalışıyordu; stok kaydını bozma.
            }
        }
    }

    public function verilenIsSayisi(string $refUrunAdiNo): int
    {
        $araUrunNo = intval($refUrunAdiNo);

        $havuz = intval(
            DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', $araUrunNo)
                ->sum('ToplamAdet')
        );

        $personel = intval(
            DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunNo)
                ->selectRaw('COALESCE(SUM(COALESCE(Adet, 0) + COALESCE(BekleyenAdet, 0)), 0) as toplam')
                ->value('toplam') ?? 0
        );

        return $havuz + $personel;
    }

    public function stokGetir(string $refUrunAdiNo): int
    {
        return intval(
            DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', intval($refUrunAdiNo))
                ->value('Adet') ?? 0
        );
    }

    // ================================================================
    // personelGorevTabloGuncelle
    // Stok/havuz değiştiğinde, onu bekleyen personel görevlerini (Adet ve 
    // BekleyenAdet) üretilebilir limite göre günceller.
    // ================================================================
    public function personelGorevTabloGuncelle(string $araUrunNo): void
    {
        $sonUrunMu = true;
        $kontrolUrunlerStr = $this->sonrakiUrunAdlari($araUrunNo);
        $kontrolUrunler = array_values(array_unique(array_filter(
            array_map('intval', explode(':', $kontrolUrunlerStr)),
            fn ($urunNo) => $urunNo > 0
        )));

        if (count($kontrolUrunler) > 0) {
            foreach ($kontrolUrunler as $urunNo) {
                $intUrnNo = intval($urunNo);
                $kayitlar = DB::table('tbPersonelGorev')
                    ->where('AraUrunAdiNo', $intUrnNo)
                    ->get();
                
                if ($kayitlar->isNotEmpty()) {
                    foreach ($kayitlar as $kayit) {
                        $sonUrunMu = false;

                        $bekleyenAdet = intval($kayit->BekleyenAdet ?? 0);
                        $adet = intval($kayit->Adet ?? 0);
                        $toplamAdet = max(0, $adet + $bekleyenAdet);
                        $split = $this->personnelTaskReadySplit($kayit, $toplamAdet);
                        $newAdet = intval($split['ready']);
                        $newBekleyen = intval($split['waiting']);

                        if ($newAdet !== $adet || $newBekleyen !== $bekleyenAdet) {
                            DB::table('tbPersonelGorev')->where('No', $kayit->No)->update([
                                'Adet' => $newAdet,
                                'BekleyenAdet' => $newBekleyen
                            ]);
                        }
                    }
                }
            }
        }

        if ($sonUrunMu) {
            $intUrnNo = intval($araUrunNo);
            if ($this->hasActiveBufferReservation($intUrnNo)) {
                return;
            }

            $kayitlar = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $intUrnNo)
                ->get();
            
            if ($kayitlar->isNotEmpty()) {
                foreach ($kayitlar as $kayit) {
                    $stockBefore = clone $kayit;
                    DB::table('tbBolumAraStok')->where('No', $kayit->No)->update([
                        'TamponMiktar' => $kayit->Adet
                    ]);
                    $stockAfter = DB::table('tbBolumAraStok')->where('No', $kayit->No)->first();
                    $this->logStockMovement($stockBefore, $stockAfter, [
                        'movement_type' => 'buffer_reset',
                        'source_type' => 'bom_rebalance',
                        'source_id' => $araUrunNo,
                        'description' => 'BOM senkronizasyonu sonrası tampon miktarı depodaki adet ile eşitlendi.',
                    ]);
                }
            }
        }
    }

    // ================================================================
    // tamponStokKontrol
    // Havuz kaydı silindiğinde, alt parçaların TamponMiktarını
    // geri artırır (ASP.NET AdminAnaSayfa.cs tamponStokKontrol)
    // ================================================================
    public function tamponStokKontrol(string $araUrunAdiNo, int $adet): void
    {
        $altParcalar = $this->birAdimOncesiUrunAdlari($araUrunAdiNo);
        if (trim($altParcalar) === '') return;

        $parcalar = explode(':', $altParcalar);
        foreach ($parcalar as $parcaNo) {
            $parcaNo = trim($parcaNo);
            if ($parcaNo === '') continue;

            $carpan = $this->kacParca($araUrunAdiNo, $parcaNo);
            $artis = $adet * $carpan;
            if ($artis > 0) {
                $stockBefore = DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', intval($parcaNo))
                    ->first();
                if (!$stockBefore) {
                    continue;
                }

                $yeniTampon = min(
                    intval($stockBefore->Adet ?? 0),
                    intval($stockBefore->TamponMiktar ?? 0) + $artis
                );

                DB::table('tbBolumAraStok')
                    ->where('No', $stockBefore->No)
                    ->update(['TamponMiktar' => $yeniTampon]);
                $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();

                $this->logStockMovement($stockBefore, $stockAfter, [
                    'movement_type' => 'pool_buffer_released',
                    'source_type' => 'production_pool',
                    'source_id' => $araUrunAdiNo,
                    'description' => 'Havuz kaydı silindiği için alt parça tampon stoğu iade edildi.',
                    'metadata' => [
                        'parent_component_no' => intval($araUrunAdiNo),
                        'released_buffer_quantity' => $artis,
                        'component_multiplier' => $carpan,
                    ],
                ]);
            }
        }
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi BOM hesaplamasini bozmasin.
        }
    }
}
