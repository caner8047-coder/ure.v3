<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Sipariş Senkronizasyon Servisi
 * Legacy tablolar kullanır: tbSiparisSatir, tbUrunler, tbAraUrun, tbUrunEslestirmeOnbellek, tbSetTanimlari, tbSetIcerikleri
 */
class OrderSyncService
{
    private const SPECIAL_PRODUCTION_CUSTOMER_PREFIX = 'ÖZEL ÜRETİM%';
    private ?array $activeSetLookup = null;

    public function __construct(
        protected StockMovementLogger $stockMovementLogger,
        protected WorkOrderEventLogger $workOrderEventLogger
    ) {}

    public function getOrders($filters)
    {
        $query = DB::table('tbSiparisSatir');

        if (!isset($filters['show_all'])) {
            $query->where('Aktif', 1);
        }

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('SiparisNo', 'LIKE', "%$s%")
                    ->orWhere('Musteri', 'LIKE', "%$s%")
                    ->orWhere('UrunAdi', 'LIKE', "%$s%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('Durum', $filters['status']);
        }

        $total = $query->count();

        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 50;

        $orders = $query->orderByDesc('SiparisTarihi')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['success' => true, 'data' => $orders, 'totalCount' => $total];
    }

    public function uploadOrders(array $rows)
    {
        $inserted = 0;
        $updated = 0;
        $matched = 0;
        $unmatched = 0;
        $passivated = 0;
        $giedAutoStockDeducted = 0;
        $giedAutoStockAlreadyClosed = 0;
        $giedAutoStockErrors = [];

        $dbProducts = DB::table('tbUrunler')->get();
        $matchCache = $this->buildLookupMap(DB::table('tbUrunEslestirmeOnbellek')->get(), 'ExcelUrunAdi');
        $sets = $this->buildLookupMap(DB::table('tbSetTanimlari')->where('Aktif', 1)->get(), ['ExcelSetAdi', 'SetAdi']);

        $touchedIds = [];
        $pendingWorkOrdersAlert = [];
        $newStockCodes = [];
        $stockFulfilledOrderNos = $this->activeStockFulfilledOrderItemNos();

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $siparisNo = $row['siparisNo'] ?? null;
                $urunAdi = $row['urunAdi'] ?? null;
                if (!$siparisNo || !$urunAdi) continue;

                $musteriNotu = $row['musteriNotu'] ?? null;
                $pazaryeri = $row['pazaryeri'] ?? null;
                $magaza = $row['magaza'] ?? null;
                $musteri = $row['musteri'] ?? null;
                $adet = (int)($row['adet'] ?? 1);
                $kategori = $row['kategori'] ?? null;
                $stokKodu = $row['stokKodu'] ?? null;
                $siparisTarihi = $this->parseDate($row['siparisTarihi'] ?? null);
                $kargoSonTeslim = $this->parseDate($row['kargoSonTeslim'] ?? null);

                $existing = $this->findExistingUploadedOrderLine($siparisNo, $urunAdi);

                $eslesenUrunNo = null;
                $eslesenUrunTur = null;
                $eslesmePuani = null;
                $eslesmeYontemi = null;
                $setDef = $this->findLookupMatch($sets, $urunAdi);
                if ($setDef && !$this->setDefinitionHasValidContents((int) $setDef->No)) {
                    $setDef = null;
                }
                $avoidSingleProductAutoMatch = !$setDef && $this->shouldAvoidSingleProductAutoMatch($urunAdi);

                // StokKodu ile eşleştirme
                if (!$avoidSingleProductAutoMatch && $stokKodu) {
                    $matchedProd = $dbProducts->firstWhere('SistemKodu', $stokKodu);
                    if ($matchedProd) {
                        $eslesenUrunNo = $matchedProd->No;
                        $eslesenUrunTur = 'Nihai';
                        $eslesmePuani = 100;
                        $eslesmeYontemi = 'StokKodu';
                    }
                }

                // Önbellek ile eşleştirme
                if (!$avoidSingleProductAutoMatch && !$eslesenUrunNo && ($cached = $this->findLookupMatch($matchCache, $urunAdi))) {
                    $eslesenUrunNo = $cached->EslesenUrunNo;
                    $eslesenUrunTur = $cached->EslesenUrunTur;
                    $eslesmePuani = 100;
                    $eslesmeYontemi = 'Onbellek';
                }

                // Tam isim eşleştirme
                if (!$avoidSingleProductAutoMatch && !$eslesenUrunNo) {
                    $urunAdiKeys = array_flip($this->matchKeys($urunAdi));
                    $exact = $dbProducts->first(function ($p) use ($urunAdiKeys) {
                        return $this->hasAnyMatchKey($p->SistemAdi ?? '', $urunAdiKeys)
                            || $this->hasAnyMatchKey($p->UrunID ?? '', $urunAdiKeys);
                    });
                    if ($exact) {
                        $eslesenUrunNo = $exact->No;
                        $eslesenUrunTur = 'Nihai';
                        $eslesmePuani = 100;
                        $eslesmeYontemi = 'TamIsim';
                    }
                }

                if ($eslesenUrunNo || $setDef) $matched++;
                else $unmatched++;

                if ($existing) {
                    $updateData = [
                        'Pazaryeri' => $pazaryeri,
                        'Magaza' => $magaza,
                        'SiparisTarihi' => $siparisTarihi,
                        'Musteri' => $musteri,
                        'Adet' => $adet,
                        'MusteriNotu' => $musteriNotu,
                        'KargoSonTeslim' => $kargoSonTeslim,
                        'Kategori' => $kategori,
                        'StokKodu' => $stokKodu,
                        'Aktif' => 1,
                        'GuncellemeTarihi' => now(),
                    ];

                    if (($existing->EslesmeYontemi ?? '') !== 'Manuel') {
                        $updateData['EslesenUrunNo'] = $eslesenUrunNo;
                        $updateData['EslesenUrunTur'] = $eslesenUrunTur;
                        $updateData['EslesmePuani'] = $eslesmePuani;
                        $updateData['EslesmeYontemi'] = $eslesmeYontemi;
                    }

                    if ($this->shouldRepairReservedStatus($existing)) {
                        $updateData['Durum'] = 'UretimdenKarsilaniyor';
                    } elseif (in_array((int) $existing->No, $stockFulfilledOrderNos, true)) {
                        $updateData['Durum'] = 'StokKarsilandi';
                    } elseif (!$this->isUploadLockedStatus($existing)) {
                        $updateData['Durum'] = 'UretimBekliyor';
                    }

                    $finalDurum = (string) ($updateData['Durum'] ?? $existing->Durum ?? '');
                    if ($finalDurum === 'StokKarsilandi') {
                        $updateData['Aktif'] = 0;
                    }

                    DB::table('tbSiparisSatir')->where('No', $existing->No)->update($updateData);
                    $touchedIds[] = $existing->No;
                    $updated++;

                    // Set Logic
                    if (!$this->isUploadLockedStatus($existing) && $setDef) {
                        DB::table('tbSiparisSatir')->where('No', $existing->No)->update([
                            'SetMi' => 1, 'SetNo' => $setDef->No,
                            'EslesenUrunNo' => null, 'EslesenUrunTur' => null, 'EslesmeYontemi' => 'Set'
                        ]);
                        $touchedIds = array_merge(
                            $touchedIds,
                            $this->syncSetChildren((int) $existing->No, $setDef, $adet)
                        );
                    }
                } else {
                    $newNo = DB::table('tbSiparisSatir')->insertGetId([
                        'SiparisNo' => $siparisNo,
                        'Pazaryeri' => $pazaryeri,
                        'Magaza' => $magaza,
                        'SiparisTarihi' => $siparisTarihi,
                        'Musteri' => $musteri,
                        'UrunAdi' => $urunAdi,
                        'Adet' => $adet,
                        'MusteriNotu' => $musteriNotu,
                        'KargoSonTeslim' => $kargoSonTeslim,
                        'Kategori' => $kategori,
                        'StokKodu' => $stokKodu,
                        'Durum' => 'UretimBekliyor',
                        'Aktif' => 1,
                        'EslesenUrunNo' => $eslesenUrunNo,
                        'EslesenUrunTur' => $eslesenUrunTur,
                        'EslesmePuani' => $eslesmePuani,
                        'EslesmeYontemi' => $eslesmeYontemi,
                        'SetMi' => 0,
                        'SetNo' => null,
                        'AnaSetSatirNo' => null,
                        'YuklemeTarihi' => now(),
                    ], 'No');

                    $touchedIds[] = $newNo;
                    $inserted++;

                    // Set Logic Insert
                    if ($setDef) {
                        DB::table('tbSiparisSatir')->where('No', $newNo)->update([
                            'SetMi' => 1, 'SetNo' => $setDef->No,
                            'EslesenUrunNo' => null, 'EslesenUrunTur' => null, 'EslesmeYontemi' => 'Set'
                        ]);
                        $touchedIds = array_merge(
                            $touchedIds,
                            $this->syncSetChildren((int) $newNo, $setDef, $adet)
                        );
                    }
                }
            }

            // Pasif hale getir
            if (!empty($touchedIds)) {
                $passivated = $this->onlyMarketplaceOrderRows(DB::table('tbSiparisSatir'))
                    ->where('Aktif', 1)
                    ->where('Durum', 'UretimBekliyor')
                    ->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) <= 0')
                    ->whereNotIn('No', $touchedIds)
                    ->when(!empty($stockFulfilledOrderNos), function ($query) use ($stockFulfilledOrderNos) {
                        $query->whereNotIn('No', $stockFulfilledOrderNos);
                    })
                    ->update(['Durum' => 'Pasif', 'Aktif' => 0]);

                $giedAutoClose = $this->autoCloseMissingGiedOrders($touchedIds, $stockFulfilledOrderNos);
                $giedAutoStockDeducted = $giedAutoClose['deducted'];
                $giedAutoStockAlreadyClosed = $giedAutoClose['alreadyClosed'];
                $giedAutoStockErrors = $giedAutoClose['errors'];

                $pendingAlerts = $this->onlyMarketplaceOrderRows(DB::table('tbSiparisSatir'))
                    ->where('Aktif', 1)
                    ->where('Durum', 'IsEmriVerildi')
                    ->whereNotIn('No', $touchedIds)
                    ->get();

                foreach ($pendingAlerts as $pa) {
                    $pendingWorkOrdersAlert[] = [
                        'no' => $pa->No,
                        'siparisNo' => $pa->SiparisNo,
                        'urunAdi' => $pa->UrunAdi,
                        'gorevNo' => $pa->GorevNo ?? 0
                    ];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Yükleme tamamlandı.',
                'inserted' => $inserted,
                'updated' => $updated,
                'passivated' => $passivated,
                'giedAutoStockDeducted' => $giedAutoStockDeducted,
                'giedAutoStockAlreadyClosed' => $giedAutoStockAlreadyClosed,
                'giedAutoStockErrors' => $giedAutoStockErrors,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'pendingWorkOrders' => $pendingWorkOrdersAlert,
                'stokKoduKaydedilecekler' => $newStockCodes
            ];
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
    }

    private function findExistingUploadedOrderLine(?string $siparisNo, ?string $urunAdi): ?object
    {
        $siparisNo = trim((string) $siparisNo);
        $urunAdi = trim((string) $urunAdi);

        if ($siparisNo === '' || $urunAdi === '') {
            return null;
        }

        return DB::table('tbSiparisSatir')
            ->where('SiparisNo', $siparisNo)
            ->where('UrunAdi', $urunAdi)
            ->where(function ($query) {
                $query->whereNull('AnaSetSatirNo')
                    ->orWhere('AnaSetSatirNo', 0);
            })
            ->orderByRaw("
                CASE
                    WHEN IFNULL(BagliOlduguOzelUretimNo, 0) > 0 THEN 0
                    WHEN Durum = 'IsEmriVerildi' OR IFNULL(GorevNo, 0) > 0 THEN 1
                    WHEN Durum = 'StokKarsilandi' THEN 2
                    WHEN Aktif = 1 AND Durum = 'UretimBekliyor' THEN 3
                    WHEN Aktif = 1 THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('No')
            ->lockForUpdate()
            ->first();
    }

    private function onlyMarketplaceOrderRows($query)
    {
        return $query->where(function ($q) {
            $q->where('Musteri', 'NOT LIKE', self::SPECIAL_PRODUCTION_CUSTOMER_PREFIX)
                ->orWhereNull('Musteri');
        });
    }

    private function isUploadLockedStatus(object $order): bool
    {
        return in_array((string) ($order->Durum ?? ''), [
            'IsEmriVerildi',
            'UretimdenKarsilaniyor',
            'StokKarsilandi',
            'PasifDevamEden',
        ], true)
            || (int) ($order->GorevNo ?? 0) > 0
            || $this->shouldRepairReservedStatus($order);
    }

    private function shouldRepairReservedStatus(object $order): bool
    {
        return (int) ($order->BagliOlduguOzelUretimNo ?? 0) > 0
            && !in_array((string) ($order->Durum ?? ''), ['Pasif', 'StokKarsilandi'], true);
    }

    private function activeStockFulfilledOrderItemNos(): array
    {
        if (!Schema::hasTable('stock_movements')) {
            return [];
        }

        return DB::table('stock_movements')
            ->select('order_item_no')
            ->whereNotNull('order_item_no')
            ->whereIn('movement_type', ['order_stock_out', 'order_stock_out_reversed'])
            ->groupBy('order_item_no')
            ->havingRaw('SUM(IFNULL(quantity_delta, 0)) < 0')
            ->pluck('order_item_no')
            ->map(fn ($no) => (int) $no)
            ->values()
            ->all();
    }

    private function autoCloseMissingGiedOrders(array $touchedIds, array $stockFulfilledOrderNos): array
    {
        $summary = [
            'deducted' => 0,
            'alreadyClosed' => 0,
            'errors' => [],
        ];

        $touchedIds = collect($touchedIds)
            ->map(fn ($no) => (int) $no)
            ->filter(fn ($no) => $no > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($touchedIds)) {
            return $summary;
        }

        $stockFulfilledLookup = array_fill_keys(
            collect($stockFulfilledOrderNos)
                ->map(fn ($no) => (int) $no)
                ->filter(fn ($no) => $no > 0)
                ->all(),
            true
        );

        $candidates = $this->onlyMarketplaceOrderRows(DB::table('tbSiparisSatir'))
            ->where('Aktif', 1)
            ->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) > 0')
            ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi'])
            ->whereNotIn('No', $touchedIds)
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $satir) {
            $satirNo = (int) ($satir->No ?? 0);
            if ($satirNo <= 0) {
                continue;
            }

            if (isset($stockFulfilledLookup[$satirNo])) {
                $this->markMissingGiedOrderAsStockFulfilled($satir);
                $summary['alreadyClosed']++;
                continue;
            }

            $result = $this->deductStockForMissingGiedOrder($satir);
            if ($result === 'OK') {
                $summary['deducted']++;
                continue;
            }

            $summary['errors'][] = [
                'no' => $satirNo,
                'siparisNo' => (string) ($satir->SiparisNo ?? ''),
                'musteri' => (string) ($satir->Musteri ?? ''),
                'urunAdi' => (string) ($satir->UrunAdi ?? ''),
                'message' => $result,
            ];
        }

        return $summary;
    }

    private function deductStockForMissingGiedOrder(object $satir): string
    {
        $satirNo = (int) ($satir->No ?? 0);
        $adet = (int) ($satir->Adet ?? 0);
        $eslesenUrunNo = (int) ($satir->EslesenUrunNo ?? 0);
        $eslesenUrunTur = (string) ($satir->EslesenUrunTur ?? '');
        $durum = (string) ($satir->Durum ?? '');

        if ($satirNo <= 0) {
            return 'Sipariş satırı geçersiz.';
        }

        if ($adet <= 0) {
            return 'Sipariş adedi geçersiz.';
        }

        if (!in_array($durum, ['UretimBekliyor', 'UretimdenKarsilaniyor'], true)) {
            return 'Sadece GİED ile üretime bağlı bekleyen siparişler otomatik stoktan düşülebilir.';
        }

        if ($eslesenUrunNo <= 0) {
            return 'Eşleşen ürün bulunamadı.';
        }

        $araUrunNo = $this->resolveMatchedAraUrunNo($eslesenUrunNo, $eslesenUrunTur);
        if ($araUrunNo <= 0) {
            return 'Ara ürün bulunamadı.';
        }

        $stokRows = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $araUrunNo)
            ->where('Adet', '>', 0)
            ->orderBy('No')
            ->lockForUpdate()
            ->get();

        $mevcutStok = (int) $stokRows->sum(fn ($row) => (int) ($row->Adet ?? 0));
        if ($mevcutStok < $adet) {
            return "Yetersiz stok. Mevcut: {$mevcutStok}, İstenen: {$adet}";
        }

        $remaining = $adet;
        foreach ($stokRows as $stokRow) {
            if ($remaining <= 0) {
                break;
            }

            $dusulecek = min($remaining, (int) ($stokRow->Adet ?? 0));
            $stockBefore = $stokRow;

            DB::table('tbBolumAraStok')->where('No', $stokRow->No)->update([
                'Adet' => DB::raw("Adet - {$dusulecek}"),
                'TamponMiktar' => DB::raw("CASE WHEN TamponMiktar - {$dusulecek} < 0 THEN 0 ELSE TamponMiktar - {$dusulecek} END"),
            ]);

            $stockAfter = DB::table('tbBolumAraStok')->where('No', $stokRow->No)->first();
            $this->logStockMovement($stockBefore, $stockAfter, [
                'movement_type' => 'order_stock_out',
                'source_type' => 'order_item',
                'source_id' => $satirNo,
                'order_item_no' => $satirNo,
                'order_no' => (string) ($satir->SiparisNo ?? ''),
                'work_order_no' => (int) ($satir->GorevNo ?? 0) > 0 ? (int) $satir->GorevNo : null,
                'source_screen' => 'Siparis Yonetimi',
                'source_action' => 'Excel GIED Otomatik Stok Dus',
                'source_route' => 'SiparisApi.ashx',
                'description' => 'Sipariş günlük aktif listede olmadığı için GİED kapanışı stoktan düşüldü.',
                'metadata' => [
                    'deducted_quantity' => $dusulecek,
                    'order_quantity' => $adet,
                    'matched_product_no' => $eslesenUrunNo,
                    'matched_product_type' => $eslesenUrunTur,
                    'auto_close_reason' => 'missing_from_active_upload',
                ],
            ]);

            $remaining -= $dusulecek;
        }

        DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
            'Durum' => 'StokKarsilandi',
            'Aktif' => 0,
            'BagliOlduguOzelUretimNo' => null,
            'GuncellemeTarihi' => now(),
        ]);

        $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
        $this->logCenterEvent([
            'event_type' => 'stock_deducted',
            'aggregate_type' => 'order_item',
            'aggregate_id' => $satirNo,
            'order_item_no' => $satirNo,
            'order_no' => (string) (($updatedSatir->SiparisNo ?? '') ?: ($satir->SiparisNo ?? '')),
            'work_order_no' => (int) ($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0) > 0
                ? (int) ($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0)
                : null,
            'status_before' => $durum,
            'status_after' => $updatedSatir->Durum ?? 'StokKarsilandi',
            'special_production_no' => (int) ($satir->BagliOlduguOzelUretimNo ?? 0) > 0
                ? (int) $satir->BagliOlduguOzelUretimNo
                : null,
            'source_screen' => 'Siparis Yonetimi',
            'source_action' => 'Excel GIED Otomatik Stok Dus',
            'source_route' => 'SiparisApi.ashx',
            'title_human' => 'GIED siparisi stoktan karsilandi',
            'next_step_human' => 'Kayit stoktan kapanmis durumda.',
            'payload_before' => $this->serializeRecord($satir),
            'payload_after' => $this->serializeRecord($updatedSatir),
            'context' => [
                'ara_urun_no' => $araUrunNo,
                'deducted_amount' => $adet,
                'auto_close_reason' => 'missing_from_active_upload',
            ],
        ]);

        return 'OK';
    }

    private function markMissingGiedOrderAsStockFulfilled(object $satir): void
    {
        $satirNo = (int) ($satir->No ?? 0);
        if ($satirNo <= 0) {
            return;
        }

        DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
            'Durum' => 'StokKarsilandi',
            'Aktif' => 0,
            'BagliOlduguOzelUretimNo' => null,
            'GuncellemeTarihi' => now(),
        ]);
    }

    private function resolveMatchedAraUrunNo(int $eslesenUrunNo, string $eslesenUrunTur): int
    {
        if ($eslesenUrunNo <= 0) {
            return 0;
        }

        if (in_array($eslesenUrunTur, ['Ara', 'HamMadde'], true)) {
            return $eslesenUrunNo;
        }

        if ($eslesenUrunTur === 'Nihai') {
            $urunID = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->value('UrunID');
            if (empty($urunID)) {
                return 0;
            }

            return (int) (DB::table('tbAraUrun')->where('AraUrunAdi', trim((string) $urunID))->value('No') ?? 0);
        }

        return 0;
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            $this->stockMovementLogger->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi ana siparis yukleme akisini bozmasin.
        }
    }

    private function logCenterEvent(array $attributes): void
    {
        try {
            $this->workOrderEventLogger->log($attributes);
        } catch (\Throwable) {
            // Event merkezi ana siparis yukleme akisini bozmasin.
        }
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

    public function findActiveSetDefinition(?string $productName)
    {
        if ($this->activeSetLookup === null) {
            $this->activeSetLookup = $this->buildLookupMap(
                DB::table('tbSetTanimlari')->where('Aktif', 1)->get(),
                ['ExcelSetAdi', 'SetAdi']
            );
        }

        return $this->findLookupMatch($this->activeSetLookup, $productName);
    }

    public function shouldAvoidSingleProductAutoMatch(?string $productName): bool
    {
        $normalized = $this->normalizeMatchKey($productName);
        if ($normalized === '') {
            return false;
        }

        $folded = $this->foldMatchKey($normalized);

        return preg_match('/\bseti?\b/u', $folded) === 1
            && (
                str_contains($folded, '(')
                || preg_match('/\d+\s*(adet|tane|parca|parça)?\b/u', $folded) === 1
            );
    }

    public function tryApplySetDefinitionToOrder(int $orderNo, ?string $productName, int $quantity = 1): bool
    {
        $setDef = $this->findActiveSetDefinition($productName);
        if (!$setDef) {
            return false;
        }

        if (!$this->setDefinitionHasValidContents((int) $setDef->No)) {
            return false;
        }

        $order = DB::table('tbSiparisSatir')->where('No', $orderNo)->first();
        if (!$order || (int) ($order->Aktif ?? 0) !== 1 || (string) ($order->Durum ?? '') !== 'UretimBekliyor' || $this->isUploadLockedStatus($order)) {
            return false;
        }

        return DB::transaction(function () use ($orderNo, $setDef, $order, $quantity) {
            DB::table('tbSiparisSatir')->where('No', $orderNo)->update([
                'SetMi' => 1,
                'SetNo' => $setDef->No,
                'EslesenUrunNo' => null,
                'EslesenUrunTur' => null,
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Set',
                'GuncellemeTarihi' => now(),
            ]);

            $this->syncSetChildren($orderNo, $setDef, max(1, $quantity));

            if ($this->activeSetChildCount($orderNo) > 0) {
                return true;
            }

            DB::table('tbSiparisSatir')->where('No', $orderNo)->update([
                'SetMi' => (int) ($order->SetMi ?? 0),
                'SetNo' => $order->SetNo ?? null,
                'EslesenUrunNo' => $order->EslesenUrunNo ?? null,
                'EslesenUrunTur' => $order->EslesenUrunTur ?? null,
                'EslesmePuani' => $order->EslesmePuani ?? null,
                'EslesmeYontemi' => $order->EslesmeYontemi ?? null,
                'GuncellemeTarihi' => now(),
            ]);

            return false;
        });
    }

    public function repairSetOrderChildren(int $orderNo): bool
    {
        $order = DB::table('tbSiparisSatir')->where('No', $orderNo)->first();
        if (!$order || intval($order->SetMi ?? 0) !== 1) {
            return false;
        }

        if ($this->isUploadLockedStatus($order)) {
            return false;
        }

        $setNo = (int) ($order->SetNo ?? 0);
        $setDef = $setNo > 0
            ? DB::table('tbSetTanimlari')->where('No', $setNo)->where('Aktif', 1)->first()
            : null;

        if (!$setDef || !$this->setDefinitionHasValidContents((int) $setDef->No)) {
            $setDef = $this->findActiveSetDefinition($order->UrunAdi ?? null);
        }

        if (!$setDef || !$this->setDefinitionHasValidContents((int) $setDef->No)) {
            $this->clearOrderMatch($orderNo, false);
            return false;
        }

        return DB::transaction(function () use ($orderNo, $order, $setDef) {
            DB::table('tbSiparisSatir')->where('No', $orderNo)->update([
                'SetNo' => $setDef->No,
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Set',
                'GuncellemeTarihi' => now(),
            ]);

            $this->syncSetChildren($orderNo, $setDef, max(1, (int) ($order->Adet ?? 1)));

            if ($this->activeSetChildCount($orderNo) > 0) {
                return true;
            }

            $this->clearOrderMatch($orderNo, false);
            return false;
        });
    }

    public function clearOrderMatch(int $orderNo, bool $deleteCache = true): array
    {
        return DB::transaction(function () use ($orderNo, $deleteCache) {
            $order = DB::table('tbSiparisSatir')->where('No', $orderNo)->lockForUpdate()->first();
            if (!$order) {
                throw new \Exception('Sipariş satırı bulunamadı.');
            }

            if ((int) ($order->AnaSetSatirNo ?? 0) > 0) {
                throw new \Exception('Set bileşeninin eşleşmesi ana set satırından iptal edilir.');
            }

            if ((int) ($order->Aktif ?? 0) !== 1 || (string) ($order->Durum ?? '') !== 'UretimBekliyor' || $this->isUploadLockedStatus($order)) {
                throw new \Exception('Eşleşme sadece üretim bekleyen aktif siparişlerde iptal edilebilir.');
            }

            $deletedChildren = 0;
            if ((int) ($order->SetMi ?? 0) === 1) {
                $children = DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $orderNo)->lockForUpdate()->get();
                $hasLockedChildren = $children->contains(function ($child) {
                    return in_array((string) ($child->Durum ?? ''), ['IsEmriVerildi', 'UretimdenKarsilaniyor', 'StokKarsilandi', 'PasifDevamEden'], true)
                        || (int) ($child->GorevNo ?? 0) > 0;
                });

                if ($hasLockedChildren) {
                    throw new \Exception('Bu setin üretime aktarılmış bileşeni var; eşleşme iptal edilemez.');
                }

                $deletedChildren = DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $orderNo)->delete();
            }

            DB::table('tbSiparisSatir')->where('No', $orderNo)->update([
                'EslesenUrunNo' => null,
                'EslesenUrunTur' => null,
                'EslesmePuani' => null,
                'EslesmeYontemi' => null,
                'SetMi' => 0,
                'SetNo' => null,
                'GuncellemeTarihi' => now(),
            ]);

            $deletedCache = 0;
            $urunAdi = trim((string) ($order->UrunAdi ?? ''));
            $oldMatchedNo = (int) ($order->EslesenUrunNo ?? 0);
            if ($deleteCache && $urunAdi !== '') {
                $cacheQuery = DB::table('tbUrunEslestirmeOnbellek')->where('ExcelUrunAdi', $urunAdi);
                if ($oldMatchedNo > 0) {
                    $cacheQuery->where('EslesenUrunNo', $oldMatchedNo);
                }
                $deletedCache = $cacheQuery->delete();
            }

            return [
                'success' => true,
                'deletedChildren' => $deletedChildren,
                'deletedCache' => $deletedCache,
            ];
        });
    }

    private function setDefinitionHasValidContents(int $setNo): bool
    {
        return DB::table('tbSetIcerikleri as i')
            ->join('tbUrunler as u', 'u.No', '=', 'i.UrunNo')
            ->where('i.SetNo', $setNo)
            ->exists();
    }

    private function activeSetChildCount(int $parentNo): int
    {
        return DB::table('tbSiparisSatir')
            ->where('AnaSetSatirNo', $parentNo)
            ->where('Aktif', 1)
            ->count();
    }

    private function buildLookupMap($items, string|array $fields): array
    {
        $map = [];
        $fields = is_array($fields) ? $fields : [$fields];

        foreach ($items as $item) {
            foreach ($fields as $field) {
                foreach ($this->matchKeys($item->{$field} ?? '') as $key) {
                    if (!isset($map[$key])) {
                        $map[$key] = $item;
                    }
                }
            }
        }

        return $map;
    }

    private function findLookupMatch(array $map, ?string $value)
    {
        foreach ($this->matchKeys($value) as $key) {
            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }

    private function hasAnyMatchKey(?string $value, array $targetKeys): bool
    {
        foreach ($this->matchKeys($value) as $key) {
            if (isset($targetKeys[$key])) {
                return true;
            }
        }

        return false;
    }

    private function matchKeys(?string $value): array
    {
        $normalized = $this->normalizeMatchKey($value);
        if ($normalized === '') {
            return [];
        }

        $quantityNormalized = $this->normalizeQuantityWords($normalized);
        $folded = $this->foldMatchKey($normalized);
        $quantityFolded = $this->foldMatchKey($quantityNormalized);

        $keys = [
            $normalized,
            $this->compactMatchKey($normalized),
            $quantityNormalized,
            $this->compactMatchKey($quantityNormalized),
            $folded,
            $this->compactMatchKey($folded),
            $quantityFolded,
            $this->compactMatchKey($quantityFolded),
        ];

        return array_values(array_unique(array_filter($keys)));
    }

    private function normalizeMatchKey(?string $value): string
    {
        $value = str_replace("\xc2\xa0", ' ', trim((string) $value));
        $value = strtr($value, [
            'İ' => 'i', 'I' => 'ı',
            '–' => '-', '—' => '-', '−' => '-',
        ]);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*([(),])\s*/u', '$1', $value) ?? $value;

        return trim($value);
    }

    private function normalizeQuantityWords(string $value): string
    {
        $value = preg_replace('/(\d+)\s*(adet|tane|parca|parça)\b/u', '$1 ', $value) ?? $value;
        $value = preg_replace('/\b(adet|tane|parca|parça)\b/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*([(),+\-])\s*/u', '$1', $value) ?? $value;

        return trim($value);
    }

    private function foldMatchKey(string $value): string
    {
        $value = strtr($value, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i',
            'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'I' => 'i', 'İ' => 'i',
            'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
        ]);

        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if ($decomposed !== false) {
                $value = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $value;
            }
        }

        return $value;
    }

    private function compactMatchKey(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    private function syncSetChildren(int $parentNo, $setDef, int $multiplier): array
    {
        $children = DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $parentNo)->get();
        $childIds = $children->pluck('No')->map(fn ($no) => (int) $no)->toArray();

        $hasLockedChildren = $children->contains(function ($child) {
            return in_array((string) ($child->Durum ?? ''), ['IsEmriVerildi', 'UretimdenKarsilaniyor', 'StokKarsilandi', 'PasifDevamEden'], true)
                || (int) ($child->GorevNo ?? 0) > 0;
        });

        if ($hasLockedChildren) {
            return $childIds;
        }

        DB::table('tbSiparisSatir')->where('AnaSetSatirNo', $parentNo)->delete();

        $parentItem = DB::table('tbSiparisSatir')->where('No', $parentNo)->first();
        if (!$parentItem) {
            return [];
        }

        return $this->createSetChildren($parentItem, $setDef, $multiplier);
    }

    private function createSetChildren($parentItem, $setDef, $multiplier)
    {
        $createdIds = [];
        $contents = DB::table('tbSetIcerikleri')->where('SetNo', $setDef->No)->get();
        foreach ($contents as $content) {
            $childProduct = DB::table('tbUrunler')->where('No', $content->UrunNo)->first();
            if (!$childProduct) continue;

            $createdIds[] = DB::table('tbSiparisSatir')->insertGetId([
                'SiparisNo' => $parentItem->SiparisNo,
                'Pazaryeri' => $parentItem->Pazaryeri,
                'Magaza' => $parentItem->Magaza,
                'SiparisTarihi' => $parentItem->SiparisTarihi,
                'Musteri' => $parentItem->Musteri,
                'UrunAdi' => $childProduct->SistemAdi ?? $childProduct->UrunID,
                'Adet' => $content->Adet * $multiplier,
                'MusteriNotu' => $parentItem->MusteriNotu,
                'KargoSonTeslim' => $parentItem->KargoSonTeslim,
                'Kategori' => $parentItem->Kategori,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'EslesenUrunNo' => $childProduct->No,
                'EslesenUrunTur' => 'Nihai',
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Set',
                'SetMi' => 0,
                'SetNo' => $setDef->No,
                'AnaSetSatirNo' => $parentItem->No,
                'YuklemeTarihi' => now(),
            ], 'No');
        }

        return $createdIds;
    }

    private function parseDate($str)
    {
        if (empty($str)) return null;
        try {
            return Carbon::parse($str)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
