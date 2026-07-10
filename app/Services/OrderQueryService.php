<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Services\BomService;
use App\Services\OrderToWorkOrderService;
use App\Services\OrderSyncService;

class OrderQueryService
{
    private const GIED_ALLOCATION_TABLE = 'tbSiparisOzelUretimRezervasyon';

    public function __construct(
        protected OrderSyncService $orderSync,
        protected OrderToWorkOrderService $orderToWorkOrder,
        protected BomService $bomService
    ) {}

    public function uploadOrders(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload || !isset($payload['rows'])) {
            return ['success' => false, 'message' => 'rows verisi bulunamadi.'];
        }
        $rows = $payload['rows'];
        if (empty($rows)) {
            return ['success' => false, 'message' => 'Boş veri.'];
        }
        return $this->orderSync->uploadOrders($rows);
    }

    public function getOrders(Request $request)
    {
        $stockFulfilledOrderNos = [];

        try {
            $durum = $request->input('durum', '');
            $pazaryeri = $request->input('pazaryeri', '');
            $magaza = $request->input('magaza', '');
            $kategori = $request->input('kategori', '');
            $arama = $request->input('arama', '');
            $tarihBaslangic = $request->input('tarihBaslangic', '');
            $tarihBitis = $request->input('tarihBitis', '');
            $eslesmeDurum = $request->input('eslesmeDurum', '');
            $ozelUretim = $request->input('ozelUretim', '0');

            // ─── Filtre dropdown değerlerini topla ───
            $pazaryeriQuery = DB::table('tbSiparisSatir')
                ->whereNotNull('Pazaryeri')->where('Pazaryeri', '!=', '');
            $this->applyOrderCustomerScope($pazaryeriQuery, $ozelUretim);
            $pazaryeriList = $pazaryeriQuery->distinct()->orderBy('Pazaryeri')->pluck('Pazaryeri')->toArray();

            $magazaQuery = DB::table('tbSiparisSatir')
                ->whereNotNull('Magaza')->where('Magaza', '!=', '');
            $this->applyOrderCustomerScope($magazaQuery, $ozelUretim);
            $magazaList = $magazaQuery->distinct()->orderBy('Magaza')->pluck('Magaza')->toArray();

            $kategoriQuery = DB::table('tbSiparisSatir')
                ->whereNotNull('Kategori')->where('Kategori', '!=', '');
            $this->applyOrderCustomerScope($kategoriQuery, $ozelUretim);
            $kategoriList = $kategoriQuery->distinct()->orderBy('Kategori')->pluck('Kategori')->toArray();

            // ─── Ana sorgu ───
            $stockProductionColumns = $this->stockProductionColumns();
            $stockFulfilledOrderNos = $this->activeStockFulfilledOrderItemNos();
            $orderSelects = [
                'No', 'SiparisNo', 'Pazaryeri', 'Magaza', 'SiparisTarihi', 'Musteri',
                'UrunAdi', 'Adet', 'MusteriNotu', 'KargoSonTeslim', 'Kategori', 'Durum', 'Aktif',
                DB::raw("IFNULL(EslesenUrunNo,0) AS EslesenUrunNo"),
                DB::raw("IFNULL(EslesenUrunTur,'') AS EslesenUrunTur"),
                DB::raw("IFNULL(EslesmePuani,0) AS EslesmePuani"),
                DB::raw("IFNULL(EslesmeYontemi,'') AS EslesmeYontemi"),
                'IsEmriTarihi',
                DB::raw("IFNULL(GorevNo,0) AS GorevNo"),
                'YuklemeTarihi', 'StokKodu', 'BagliOlduguOzelUretimNo',
                DB::raw("IFNULL(SetMi,0) AS SetMi"),
                DB::raw("IFNULL(SetNo,0) AS SetNo"),
                DB::raw("IFNULL(AnaSetSatirNo,0) AS AnaSetSatirNo"),
                $stockProductionColumns['AnaStokUretimNo']
                    ? DB::raw("IFNULL(AnaStokUretimNo,0) AS AnaStokUretimNo")
                    : DB::raw("0 AS AnaStokUretimNo"),
                $stockProductionColumns['StokUretimTipi']
                    ? DB::raw("IFNULL(StokUretimTipi,'') AS StokUretimTipi")
                    : DB::raw("'' AS StokUretimTipi"),
                $stockProductionColumns['StokUretimIlaveSira']
                    ? DB::raw("IFNULL(StokUretimIlaveSira,0) AS StokUretimIlaveSira")
                    : DB::raw("0 AS StokUretimIlaveSira"),
            ];

            $query = DB::table('tbSiparisSatir')->select($orderSelects);

            // Özel üretim filtresi
            $this->applyOrderCustomerScope($query, $ozelUretim);

            // Durum filtresi
            if ($durum === 'UretimBekliyor') {
                $query->where('Durum', 'UretimBekliyor')
                    ->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) <= 0')
                    ->where('Aktif', 1);
                if (!empty($stockFulfilledOrderNos)) {
                    $query->whereNotIn('No', $stockFulfilledOrderNos);
                }
            } elseif (in_array($durum, ['UretimdenKarsilaniyor', 'GiedYapilanlar'], true)) {
                $query->where('Aktif', 1)
                    ->where(function ($q) {
                        $q->where('Durum', 'UretimdenKarsilaniyor')
                            ->orWhere(function ($linked) {
                                $linked->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) > 0')
                                    ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi']);
                            });
                    });
                if (!empty($stockFulfilledOrderNos)) {
                    $query->whereNotIn('No', $stockFulfilledOrderNos);
                }
            } elseif ($durum === 'IsEmriVerildi') {
                $query->where('Durum', 'IsEmriVerildi')->where('Aktif', 1);
            } elseif ($durum === 'Pasif') {
                $query->where('Durum', 'Pasif');
            } elseif ($durum === 'PasifDevamEden') {
                $query->where('Durum', 'PasifDevamEden');
            } elseif ($durum === 'Eslesmeyenler') {
                $query->where(function ($q) {
                    $q->whereNull('EslesenUrunNo')->orWhere('EslesenUrunNo', 0);
                })
                ->where(DB::raw('IFNULL(SetMi,0)'), 0)
                ->where(DB::raw('IFNULL(AnaSetSatirNo,0)'), 0)
                ->where('Aktif', 1);
                if (!empty($stockFulfilledOrderNos)) {
                    $query->whereNotIn('No', $stockFulfilledOrderNos);
                }
            } elseif ($durum === 'StokKarsilandi') {
                $query->where(function ($q) use ($stockFulfilledOrderNos) {
                    $q->where('Durum', 'StokKarsilandi');
                    if (!empty($stockFulfilledOrderNos)) {
                        $q->orWhereIn('No', $stockFulfilledOrderNos);
                    }
                });
            } else {
                $query->where('Aktif', 1)
                    ->where(function ($q) {
                        $q->whereNull('Durum')->orWhere('Durum', '!=', 'StokKarsilandi');
                    });
                if (!empty($stockFulfilledOrderNos)) {
                    $query->whereNotIn('No', $stockFulfilledOrderNos);
                }
            }

            // Ek filtreler
            if (!empty($pazaryeri)) {
                $query->where('Pazaryeri', $pazaryeri);
            }
            if (!empty($magaza)) {
                $query->where('Magaza', $magaza);
            }
            if (!empty($kategori)) {
                $query->where('Kategori', $kategori);
            }
            if (!empty($arama)) {
                $query->where(function ($q) use ($arama) {
                    $q->where('SiparisNo', 'LIKE', "%{$arama}%")
                      ->orWhere('Musteri', 'LIKE', "%{$arama}%")
                      ->orWhere('UrunAdi', 'LIKE', "%{$arama}%");
                });
            }
            if (!empty($tarihBaslangic)) {
                try {
                    $dtStart = Carbon::createFromFormat('d.m.Y', $tarihBaslangic);
                    $query->where('KargoSonTeslim', '>=', $dtStart->startOfDay());
                } catch (\Exception $e) {
                    try {
                        $dtStart = Carbon::createFromFormat('Y-m-d', $tarihBaslangic);
                        $query->where('KargoSonTeslim', '>=', $dtStart->startOfDay());
                    } catch (\Exception $e2) {}
                }
            }
            if (!empty($tarihBitis)) {
                try {
                    $dtEnd = Carbon::createFromFormat('d.m.Y', $tarihBitis);
                    $query->where('KargoSonTeslim', '<=', $dtEnd->endOfDay());
                } catch (\Exception $e) {
                    try {
                        $dtEnd = Carbon::createFromFormat('Y-m-d', $tarihBitis);
                        $query->where('KargoSonTeslim', '<=', $dtEnd->endOfDay());
                    } catch (\Exception $e2) {}
                }
            }

            $query->orderByRaw("CASE WHEN Durum='Pasif' OR Aktif=0 THEN 1 ELSE 0 END ASC")
                  ->orderBy('KargoSonTeslim', 'asc')
                  ->orderBy('SiparisTarihi', 'asc');

            $rows = $query->get();

            $orders = $rows->map(function ($row) use ($stockFulfilledOrderNos) {
                $siparisTarihi = '';
                if ($row->SiparisTarihi) {
                    try { $siparisTarihi = Carbon::parse($row->SiparisTarihi)->format('d.m.Y H:i'); } catch (\Exception $e) { $siparisTarihi = (string)$row->SiparisTarihi; }
                }
                $kargoSonTeslim = '';
                if ($row->KargoSonTeslim) {
                    try { $kargoSonTeslim = Carbon::parse($row->KargoSonTeslim)->format('d.m.Y H:i'); } catch (\Exception $e) { $kargoSonTeslim = (string)$row->KargoSonTeslim; }
                }
                $isEmriTarihi = '';
                if ($row->IsEmriTarihi) {
                    try { $isEmriTarihi = Carbon::parse($row->IsEmriTarihi)->format('d.m.Y H:i'); } catch (\Exception $e) { $isEmriTarihi = (string)$row->IsEmriTarihi; }
                }

                $stokUretimTipi = (string) ($row->StokUretimTipi ?? '');
                $anaStokUretimNo = (int) ($row->AnaStokUretimNo ?? 0);
                $isSerbestStokUretimi = $this->isFreeStockProductionRow($row);
                $stokUretimRootNo = $isSerbestStokUretimi
                    ? ($anaStokUretimNo > 0 ? $anaStokUretimNo : (int) $row->No)
                    : 0;

                $rawDurum = (string) ($row->Durum ?? '');
                $bagliOzelUretimNo = (int) ($row->BagliOlduguOzelUretimNo ?? 0);
                $hasStockFulfillment = in_array((int) $row->No, $stockFulfilledOrderNos, true);
                $durum = $this->displayOrderStatus($rawDurum, $bagliOzelUretimNo, $hasStockFulfillment);

                return [
                    'no'              => (int) $row->No,
                    'siparisNo'       => (string) ($row->SiparisNo ?? ''),
                    'pazaryeri'       => (string) ($row->Pazaryeri ?? ''),
                    'magaza'          => (string) ($row->Magaza ?? ''),
                    'siparisTarihi'   => $siparisTarihi,
                    'musteri'         => (string) ($row->Musteri ?? ''),
                    'urunAdi'         => (string) ($row->UrunAdi ?? ''),
                    'adet'            => (int) ($row->Adet ?? 0),
                    'musteriNotu'     => (string) ($row->MusteriNotu ?? ''),
                    'kargoSonTeslim'  => $kargoSonTeslim,
                    'kategori'        => (string) ($row->Kategori ?? ''),
                    'durum'           => $durum,
                    'rawDurum'        => $rawDurum,
                    'bagliOlduguOzelUretimNo' => $bagliOzelUretimNo > 0 ? $bagliOzelUretimNo : null,
                    'aktif'           => (bool) $row->Aktif,
                    'eslesenUrunNo'   => (int) $row->EslesenUrunNo,
                    'eslesenUrunTur'  => (string) $row->EslesenUrunTur,
                    'eslesmePuani'    => (int) $row->EslesmePuani,
                    'eslesmeYontemi'  => (string) $row->EslesmeYontemi,
                    'isEmriTarihi'    => $isEmriTarihi,
                    'gorevNo'         => (int) $row->GorevNo,
                    'setMi'           => (bool) $row->SetMi,
                    'setNo'           => (int) $row->SetNo,
                    'anaSetSatirNo'   => (int) $row->AnaSetSatirNo,
                    'anaStokUretimNo' => $anaStokUretimNo,
                    'stokUretimTipi'  => $stokUretimTipi,
                    'stokUretimIlaveSira' => (int) ($row->StokUretimIlaveSira ?? 0),
                    'stokUretimRootNo' => $stokUretimRootNo,
                    'isSerbestStokUretimi' => $isSerbestStokUretimi,
                    'stokUretimIlaveMi' => $isSerbestStokUretimi && ($stokUretimTipi === 'ilave' || ($stokUretimRootNo > 0 && $stokUretimRootNo !== (int) $row->No)),
                    'stokUretimToplamAdet' => 0,
                    'stokUretimIlaveToplam' => 0,
                    'stokUretimIlaveSayisi' => 0,
                    'uretimOzelToplamAdet' => 0,
                    'uretimRezerveAdet' => 0,
                    'uretimMusaitAdet' => 0,
                    'stokAdet'        => 0,
                    'uretimdeAdet'    => 0,
                ];
            })->values()->toArray();

            $stockProductionGroups = [];
            foreach ($orders as $ord) {
                if (empty($ord['isSerbestStokUretimi']) || empty($ord['stokUretimRootNo'])) {
                    continue;
                }

                $rootNo = (int) $ord['stokUretimRootNo'];
                if (!isset($stockProductionGroups[$rootNo])) {
                    $stockProductionGroups[$rootNo] = [
                        'total' => 0,
                        'additionTotal' => 0,
                        'additionCount' => 0,
                    ];
                }

                $stockProductionGroups[$rootNo]['total'] += (int) $ord['adet'];
                if ((int) $ord['no'] !== $rootNo || !empty($ord['stokUretimIlaveMi'])) {
                    $stockProductionGroups[$rootNo]['additionTotal'] += (int) $ord['adet'];
                    $stockProductionGroups[$rootNo]['additionCount']++;
                }
            }

            if ($stockProductionColumns['AnaStokUretimNo'] && !empty($stockProductionGroups)) {
                $rootNos = array_keys($stockProductionGroups);
                $additionAmountCase = $stockProductionColumns['StokUretimTipi']
                    ? "CASE WHEN No <> AnaStokUretimNo OR StokUretimTipi = 'ilave' THEN Adet ELSE 0 END"
                    : "CASE WHEN No <> AnaStokUretimNo THEN Adet ELSE 0 END";
                $additionCountCase = $stockProductionColumns['StokUretimTipi']
                    ? "CASE WHEN No <> AnaStokUretimNo OR StokUretimTipi = 'ilave' THEN 1 ELSE 0 END"
                    : "CASE WHEN No <> AnaStokUretimNo THEN 1 ELSE 0 END";

                $groupRows = DB::table('tbSiparisSatir')
                    ->select(
                        'AnaStokUretimNo',
                        DB::raw('SUM(Adet) AS ToplamAdet'),
                        DB::raw("SUM({$additionAmountCase}) AS IlaveAdet"),
                        DB::raw("SUM({$additionCountCase}) AS IlaveSayisi")
                    )
                    ->whereIn('AnaStokUretimNo', $rootNos)
                    ->where('Aktif', 1)
                    ->where('Durum', '!=', 'Pasif')
                    ->groupBy('AnaStokUretimNo')
                    ->get();

                foreach ($groupRows as $groupRow) {
                    $rootNo = (int) $groupRow->AnaStokUretimNo;
                    $stockProductionGroups[$rootNo] = [
                        'total' => (int) ($groupRow->ToplamAdet ?? 0),
                        'additionTotal' => (int) ($groupRow->IlaveAdet ?? 0),
                        'additionCount' => (int) ($groupRow->IlaveSayisi ?? 0),
                    ];
                }
            }

            foreach ($orders as &$ord) {
                $rootNo = (int) ($ord['stokUretimRootNo'] ?? 0);
                if ($rootNo > 0 && isset($stockProductionGroups[$rootNo])) {
                    $ord['stokUretimToplamAdet'] = $stockProductionGroups[$rootNo]['total'];
                    $ord['stokUretimIlaveToplam'] = $stockProductionGroups[$rootNo]['additionTotal'];
                    $ord['stokUretimIlaveSayisi'] = $stockProductionGroups[$rootNo]['additionCount'];
                }
            }
            unset($ord);

            $specialProductionCapacityMap = $this->buildSpecialProductionCapacityMap($orders);
            foreach ($orders as &$ord) {
                $capacityKey = $this->specialProductionCapacityKey(
                    intval($ord['eslesenUrunNo'] ?? 0),
                    (string) ($ord['eslesenUrunTur'] ?? '')
                );
                $capacity = $specialProductionCapacityMap[$capacityKey] ?? null;
                if ($capacity) {
                    $ord['uretimOzelToplamAdet'] = intval($capacity['total'] ?? 0);
                    $ord['uretimRezerveAdet'] = intval($capacity['reserved'] ?? 0);
                    $ord['uretimMusaitAdet'] = intval($capacity['available'] ?? 0);
                }
            }
            unset($ord);

            // ─── Özel Üretim ise bağlı sipariş bilgisi ekle ───
            if ($ozelUretim === '1') {
                foreach ($orders as &$ord) {
                    $bagliRows = $this->specialProductionLinkedOrderRows((int) $ord['no']);

                    $giedDates = collect();
                    $bagliNos = $bagliRows->pluck('No')->map(fn ($no) => (int) $no)->filter()->values();
                    if ($bagliNos->isNotEmpty() && Schema::hasTable('work_order_events')) {
                        $giedDates = DB::table('work_order_events')
                            ->select('order_item_no', DB::raw('MAX(happened_at) as gied_tarihi'))
                            ->whereIn('order_item_no', $bagliNos->all())
                            ->where('event_type', 'wip_linked')
                            ->where('special_production_no', (int) $ord['no'])
                            ->groupBy('order_item_no')
                            ->pluck('gied_tarihi', 'order_item_no');
                    }

                    $bagliList = [];
                    $reserveAdet = 0;
                    foreach ($bagliRows as $b) {
                        $eventGiedTarihi = $giedDates->get((int) $b->No);
                        $giedTarihi = $eventGiedTarihi ?: ($b->GuncellemeTarihi ?? null);
                        $bagliList[] = [
                            'no' => (int) $b->No,
                            'siparisNo' => (string) ($b->SiparisNo ?? ''),
                            'pazaryeri' => (string) ($b->Pazaryeri ?? ''),
                            'magaza' => (string) ($b->Magaza ?? ''),
                            'musteri' => (string) ($b->Musteri ?? ''),
                            'urunAdi' => (string) ($b->UrunAdi ?? ''),
                            'adet' => (int) ($b->RezervasyonAdet ?? $b->Adet ?? 0),
                            'siparisAdet' => (int) ($b->SiparisAdet ?? $b->Adet ?? 0),
                            'durum' => (string) ($b->Durum ?? ''),
                            'siparisTarihi' => $this->formatLegacyDateTime($b->SiparisTarihi ?? null),
                            'kargoSonTeslim' => $this->formatLegacyDateTime($b->KargoSonTeslim ?? null),
                            'giedTarihi' => $this->formatLegacyDateTime($giedTarihi),
                            'giedGecenSure' => $this->humanElapsedSince($giedTarihi),
                            'giedKaynak' => $eventGiedTarihi ? 'GİED' : 'Son güncelleme',
                            'gorevNo' => (int) ($b->GorevNo ?? 0),
                        ];
                        $reserveAdet += (int) ($b->RezervasyonAdet ?? $b->Adet ?? 0);
                    }
                    $ord['bagliSiparisler'] = $bagliList;
                    $bostaKalan = intval($ord['adet'] ?? 0) - $reserveAdet;
                    $ord['bostaKalan'] = $bostaKalan;
                    $ord['musaitAdet'] = max(0, $bostaKalan);
                    $ord['rezerveEdilen'] = $reserveAdet;
                }
                unset($ord);
            }

            // ─── Stok bilgisi ekle ───
            $urunlerMap = DB::table('tbUrunler')->pluck('UrunID', 'No');
            $araUrunMap = DB::table('tbAraUrun')->pluck('No', 'AraUrunAdi');
            $stokMap = DB::table('tbBolumAraStok')
                ->select('AraUrunAdiNo', DB::raw('SUM(Adet) as ToplamStok'))
                ->groupBy('AraUrunAdiNo')->pluck('ToplamStok', 'AraUrunAdiNo');

            foreach ($orders as &$ord) {
                $esNo = $ord['eslesenUrunNo'];
                $esTur = $ord['eslesenUrunTur'];
                $ord['stokAdet'] = 0;

                $araNo = $this->resolveMatchedAraUrunNoFromMaps($esNo, $esTur, $urunlerMap, $araUrunMap);
                if ($araNo > 0) {
                    $ord['stokAdet'] = (int) ($stokMap[$araNo] ?? 0);
                }
            }

            // ─── Üretimde adet bilgisi ekle ───
            $havuzMap = DB::table('tbBolumHavuz as h')
                ->leftJoin('tbSiparisSatir as s', 'h.SiparisSatirNo', '=', 's.No')
                ->select(
                    'h.AraUrunAdiNo',
                    DB::raw('SUM(IFNULL(h.Adet,0)) as Toplam'),
                    DB::raw("SUM(CASE WHEN (s.Musteri LIKE 'ÖZEL ÜRETİM%' OR IFNULL(h.SiparisSatirNo, 0) = 0) THEN IFNULL(h.Adet,0) ELSE 0 END) as ToplamOzel")
                )
                ->groupBy('h.AraUrunAdiNo')
                ->get()
                ->keyBy('AraUrunAdiNo');

            $pendingApprovalSql = $this->pendingApprovalSql('h.Onay');
            $pGorevMap = DB::table('tbPersonelGorev as h')
                ->leftJoin('tbSiparisSatir as s', 'h.SiparisSatirNo', '=', 's.No')
                ->select(
                    'h.AraUrunAdiNo',
                    DB::raw("SUM(
                        CASE
                            WHEN IFNULL(h.BekleyenAdet, 0) > 0 THEN IFNULL(h.BekleyenAdet, 0)
                            WHEN {$pendingApprovalSql} THEN IFNULL(h.Adet, 0)
                            ELSE 0
                        END
                    ) as Toplam"),
                    DB::raw("SUM(
                        CASE WHEN (s.Musteri LIKE 'ÖZEL ÜRETİM%' OR IFNULL(h.SiparisSatirNo, 0) = 0) THEN
                            CASE
                                WHEN IFNULL(h.BekleyenAdet, 0) > 0 THEN IFNULL(h.BekleyenAdet, 0)
                                WHEN {$pendingApprovalSql} THEN IFNULL(h.Adet, 0)
                                ELSE 0
                            END
                        ELSE 0 END
                    ) as ToplamOzel")
                )
                ->groupBy('h.AraUrunAdiNo')
                ->get()
                ->keyBy('AraUrunAdiNo');

            $trackedOrderNos = collect($orders)
                ->pluck('no')
                ->map(fn ($no) => intval($no))
                ->filter(fn ($no) => $no > 0)
                ->values();
            $trackedProductionOrders = [];
            $trackedHavuzMap = [];
            $trackedPGorevMap = [];

            if ($trackedOrderNos->isNotEmpty() && $this->productionTraceEnabled()) {
                $trackedNoList = $trackedOrderNos->all();

                DB::table('tbBolumHavuz as h')
                    ->whereIn('h.SiparisSatirNo', $trackedNoList)
                    ->select(
                        'h.SiparisSatirNo',
                        'h.AraUrunAdiNo',
                        DB::raw('SUM(IFNULL(h.Adet,0)) as Toplam')
                    )
                    ->groupBy('h.SiparisSatirNo', 'h.AraUrunAdiNo')
                    ->get()
                    ->each(function ($row) use (&$trackedProductionOrders, &$trackedHavuzMap) {
                        $satirNo = intval($row->SiparisSatirNo ?? 0);
                        $araUrunNo = intval($row->AraUrunAdiNo ?? 0);
                        if ($satirNo <= 0 || $araUrunNo <= 0) {
                            return;
                        }

                        $trackedProductionOrders[$satirNo] = true;
                        $trackedHavuzMap[$satirNo][$araUrunNo] = intval($row->Toplam ?? 0);
                    });

                DB::table('tbPersonelGorev as h')
                    ->whereIn('h.SiparisSatirNo', $trackedNoList)
                    ->select(
                        'h.SiparisSatirNo',
                        'h.AraUrunAdiNo',
                        DB::raw("SUM(
                            CASE
                                WHEN IFNULL(h.BekleyenAdet, 0) > 0 THEN IFNULL(h.BekleyenAdet, 0)
                                WHEN {$pendingApprovalSql} THEN IFNULL(h.Adet, 0)
                                ELSE 0
                            END
                        ) as Toplam")
                    )
                    ->groupBy('h.SiparisSatirNo', 'h.AraUrunAdiNo')
                    ->get()
                    ->each(function ($row) use (&$trackedProductionOrders, &$trackedPGorevMap) {
                        $satirNo = intval($row->SiparisSatirNo ?? 0);
                        $araUrunNo = intval($row->AraUrunAdiNo ?? 0);
                        if ($satirNo <= 0 || $araUrunNo <= 0) {
                            return;
                        }

                        $trackedProductionOrders[$satirNo] = true;
                        $trackedPGorevMap[$satirNo][$araUrunNo] = intval($row->Toplam ?? 0);
                    });

                DB::table('tbGorevler')
                    ->whereIn('SiparisSatirNo', $trackedNoList)
                    ->distinct()
                    ->pluck('SiparisSatirNo')
                    ->each(function ($satirNo) use (&$trackedProductionOrders) {
                        $satirNo = intval($satirNo);
                        if ($satirNo > 0) {
                            $trackedProductionOrders[$satirNo] = true;
                        }
                    });
            }

            foreach ($orders as &$ord) {
                $esNo = $ord['eslesenUrunNo'];
                $esTur = $ord['eslesenUrunTur'];
                $ord['uretimdeAdet'] = 0;
                $ord['uretimdeOzel'] = 0;
                $ord['havuzAdet'] = 0;
                $ord['personelAdet'] = 0;

                if ($esNo > 0) {
                    $araUrunNoUretim = $this->resolveMatchedAraUrunNoFromMaps($esNo, $esTur, $urunlerMap, $araUrunMap);
                    if ($araUrunNoUretim > 0) {
                        $satirNo = intval($ord['no'] ?? 0);
                        if ($satirNo > 0 && isset($trackedProductionOrders[$satirNo])) {
                            $ord['havuzAdet'] = intval($trackedHavuzMap[$satirNo][$araUrunNoUretim] ?? 0);
                            $ord['personelAdet'] = intval($trackedPGorevMap[$satirNo][$araUrunNoUretim] ?? 0);
                        } else {
                            $havuzInfo = $havuzMap[$araUrunNoUretim] ?? null;
                            $pGorevInfo = $pGorevMap[$araUrunNoUretim] ?? null;

                            $ord['havuzAdet'] = (int)($havuzInfo->Toplam ?? 0);
                            $ord['personelAdet'] = (int)($pGorevInfo->Toplam ?? 0);
                        }
                        $ord['uretimdeAdet'] = $ord['havuzAdet'] + $ord['personelAdet'];
                        if ($satirNo > 0 && isset($trackedProductionOrders[$satirNo])) {
                            $ord['uretimdeOzel'] = $this->isSpecialProductionOrder($ord['musteri'] ?? '')
                                ? $ord['uretimdeAdet']
                                : 0;
                        } else {
                            $ord['uretimdeOzel'] = (int)(($havuzInfo->ToplamOzel ?? 0) + ($pGorevInfo->ToplamOzel ?? 0));
                        }
                    }
                }
            }
            unset($ord);

            // ─── İstatistik sayıları ───
            $statsBase = DB::table('tbSiparisSatir')->where(DB::raw('IFNULL(AnaSetSatirNo,0)'), 0);
            $this->applyOrderCustomerScope($statsBase, $ozelUretim);

            $statsWaiting = (clone $statsBase)
                ->where('Durum', 'UretimBekliyor')
                ->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) <= 0')
                ->where('Aktif', 1);
            if (!empty($stockFulfilledOrderNos)) {
                $statsWaiting->whereNotIn('No', $stockFulfilledOrderNos);
            }

            $statsReserved = (clone $statsBase)
                ->where('Aktif', 1)
                ->where(function ($q) {
                    $q->where('Durum', 'UretimdenKarsilaniyor')
                        ->orWhere(function ($linked) {
                            $linked->whereRaw('IFNULL(BagliOlduguOzelUretimNo, 0) > 0')
                                ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi']);
                        });
                });
            if (!empty($stockFulfilledOrderNos)) {
                $statsReserved->whereNotIn('No', $stockFulfilledOrderNos);
            }

            $statsStock = (clone $statsBase)
                ->where(function ($q) use ($stockFulfilledOrderNos) {
                    $q->where('Durum', 'StokKarsilandi');
                    if (!empty($stockFulfilledOrderNos)) {
                        $q->orWhereIn('No', $stockFulfilledOrderNos);
                    }
                });

            $statsActiveTotal = (clone $statsBase)
                ->where('Aktif', 1)
                ->where(function ($q) {
                    $q->whereNull('Durum')->orWhere('Durum', '!=', 'StokKarsilandi');
                });
            if (!empty($stockFulfilledOrderNos)) {
                $statsActiveTotal->whereNotIn('No', $stockFulfilledOrderNos);
            }

            $statsUnmatched = (clone $statsBase)
                ->where(function ($q) {
                    $q->whereNull('EslesenUrunNo')->orWhere('EslesenUrunNo', 0);
                })
                ->where(DB::raw('IFNULL(SetMi,0)'), 0)
                ->where('Aktif', 1);
            if (!empty($stockFulfilledOrderNos)) {
                $statsUnmatched->whereNotIn('No', $stockFulfilledOrderNos);
            }

            $statsRow = (object) [
                'Toplam' => $statsActiveTotal->count(),
                'UretimBekliyor' => $statsWaiting->count(),
                'UretimdenKarsilaniyor' => $statsReserved->count(),
                'IsEmriVerildi' => (clone $statsBase)->where('Durum', 'IsEmriVerildi')->where('Aktif', 1)->count(),
                'StokKarsilandi' => $statsStock->count(),
                'PasifDevamEden' => (clone $statsBase)->where('Durum', 'PasifDevamEden')->count(),
                'Eslesmeyenler' => $statsUnmatched->count(),
                'SonYukleme' => (clone $statsBase)->max('YuklemeTarihi'),
            ];

            $sonYukleme = '';
            if ($statsRow->SonYukleme) {
                try { $sonYukleme = Carbon::parse($statsRow->SonYukleme)->format('d.m.Y H:i'); } catch (\Exception $e) {}
            }

            $stats = [
                'toplam'          => (int) $statsRow->Toplam,
                'uretimBekliyor'  => (int) $statsRow->UretimBekliyor,
                'uretimdenKarsilaniyor' => (int) $statsRow->UretimdenKarsilaniyor,
                'isEmriVerildi'   => (int) $statsRow->IsEmriVerildi,
                'stokKarsilandi'  => (int) $statsRow->StokKarsilandi,
                'pasifDevamEden'  => (int) $statsRow->PasifDevamEden,
                'eslesmeyenler'   => (int) $statsRow->Eslesmeyenler,
                'sonYukleme'      => $sonYukleme,
            ];

            return [
                'success' => true,
                'count'   => count($orders),
                'orders'  => $orders,
                'stats'   => $stats,
                'filters' => [
                    'pazaryeriList' => $pazaryeriList,
                    'magazaList'    => $magazaList,
                    'kategoriList'  => $kategoriList,
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSummary(Request $request)
    {
        try {
            $kategori = $request->input('kategori', '');

            // Kategori listesi
            $kategoriQuery = DB::table('tbSiparisSatir')
                ->where('Durum', 'UretimBekliyor')->where('Aktif', 1)
                ->where(DB::raw('IFNULL(SetMi,0)'), 0)
                ->whereNotNull('Kategori')->where('Kategori', '!=', '')
                ->distinct();
            $this->applyOrderCustomerScope($kategoriQuery, '0');
            $kategoriList = $kategoriQuery->pluck('Kategori')->sort()->values();

            $driver = DB::connection()->getDriverName();
            $matchedGroupExpression = $driver === 'sqlite'
                ? "(CAST(IFNULL(S.EslesenUrunNo,0) AS TEXT) || '_' || IFNULL(S.EslesenUrunTur,''))"
                : "CONCAT(IFNULL(S.EslesenUrunNo,0), '_', IFNULL(S.EslesenUrunTur,''))";

            $query = "
                SELECT MIN(S.UrunAdi) AS UrunAdi, SUM(S.Adet) AS ToplamAdet, COUNT(DISTINCT S.No) AS SiparisSayisi,
                    GROUP_CONCAT(DISTINCT S.No) AS SatirNolar,
                    MIN(S.KargoSonTeslim) AS EnYakinKargo,
                    MAX(CASE WHEN IFNULL(S.EslesenUrunNo,0) > 0 THEN S.EslesenUrunNo ELSE IFNULL(C.EslesenUrunNo,0) END) AS EslesenUrunNo,
                    MAX(CASE WHEN IFNULL(S.EslesenUrunTur,'') != '' THEN S.EslesenUrunTur ELSE IFNULL(C.EslesenUrunTur,'') END) AS EslesenUrunTur
                FROM tbSiparisSatir S
                LEFT JOIN (
                    SELECT ExcelUrunAdi, MAX(EslesenUrunNo) AS EslesenUrunNo, MAX(EslesenUrunTur) AS EslesenUrunTur
                    FROM tbUrunEslestirmeOnbellek
                    GROUP BY ExcelUrunAdi
                ) C ON S.UrunAdi = C.ExcelUrunAdi
                WHERE S.Durum='UretimBekliyor' AND S.Aktif=1 AND IFNULL(S.SetMi,0)=0
                    AND (
                        S.Musteri IS NULL
                        OR (
                            S.Musteri NOT LIKE 'ÖZEL ÜRETİM%'
                            AND S.Musteri NOT LIKE 'OZEL URETIM%'
                        )
                    )
            ";
            $bindings = [];
            if (!empty($kategori)) {
                $query .= " AND S.Kategori=?";
                $bindings[] = $kategori;
            }
            $query .= " GROUP BY CASE WHEN IFNULL(S.EslesenUrunNo,0) > 0 THEN {$matchedGroupExpression} ELSE S.UrunAdi END";

            $summary = collect(DB::select($query, $bindings))->map(function ($row) {
                $row->SatirNolar = collect(explode(',', (string) ($row->SatirNolar ?? '')))
                    ->map(fn ($no) => intval($no))
                    ->filter(fn ($no) => $no > 0)
                    ->sort()
                    ->values()
                    ->all();

                if ($row->EnYakinKargo) {
                    try { $row->EnYakinKargo = Carbon::parse($row->EnYakinKargo)->format('d.m.Y H:i'); } catch (\Exception $e) {}
                }
                $no = intval($row->EslesenUrunNo ?? 0);
                $row->sistemAdi = '';
                $row->UretilebilirAdet = 0;

                if ($no > 0) {
                    $urun = DB::table('tbUrunler')->where('No', $no)->first();
                    if ($urun) $row->sistemAdi = $urun->SistemAdi ?: $urun->UrunID;
                    else {
                        $ara = DB::table('tbAraUrun')->where('No', $no)->first();
                        if ($ara) $row->sistemAdi = $ara->AraUrunAdi;
                    }

                    $row->UretilebilirAdet = $this->bomService->uretilebilirNihaiAdet($no, $row->EslesenUrunTur ?? 'Nihayi Ürün');

                    $stockName = $urun ? ($urun->UrunID ?? '') : ($ara->AraUrunAdi ?? '');
                    $stockRow = null;
                    if (!empty($stockName) && Schema::hasTable('tbBolumAraStok')) {
                        $araUrunNo = DB::table('tbAraUrun')->where('AraUrunAdi', $stockName)->value('No');
                        if ($araUrunNo) {
                            $stockRow = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->first();
                        }
                    }
                    if ($stockRow) {
                        $depodaki = max(0, intval($stockRow->Adet ?? 0));
                        $bosta = max(0, min($depodaki, intval($stockRow->TamponMiktar ?? 0)));
                        $gorevdeki = max(0, $depodaki - $bosta);
                        $row->StokDepodaki = $depodaki;
                        $row->StokGorevdeki = $gorevdeki;
                        $row->StokBosta = $bosta;
                    } else {
                        $row->StokDepodaki = null;
                        $row->StokGorevdeki = null;
                        $row->StokBosta = null;
                    }
                } else {
                    $row->StokDepodaki = null;
                    $row->StokGorevdeki = null;
                    $row->StokBosta = null;
                }
                return $row;
            });

            $capacityMap = $this->buildSpecialProductionCapacityMap(
                $summary->map(fn ($row) => [
                    'eslesenUrunNo' => intval($row->EslesenUrunNo ?? 0),
                    'eslesenUrunTur' => (string) ($row->EslesenUrunTur ?? ''),
                ])->all()
            );

            $summary = $summary->map(function ($row) use ($capacityMap) {
                $capacityKey = $this->specialProductionCapacityKey(
                    intval($row->EslesenUrunNo ?? 0),
                    (string) ($row->EslesenUrunTur ?? '')
                );
                $capacity = $capacityMap[$capacityKey] ?? null;

                $row->UretimToplamAdet = $capacity ? intval($capacity['total'] ?? 0) : 0;
                $row->UretimRezerveAdet = $capacity ? intval($capacity['reserved'] ?? 0) : 0;
                $row->UretimMusaitAdet = $capacity ? intval($capacity['available'] ?? 0) : 0;

                return $row;
            });

            $toplamAdet = $summary->sum('ToplamAdet');

            return [
                'success' => true, 'count' => $summary->count(), 'toplamAdet' => $toplamAdet,
                'summary' => $summary, 'kategoriList' => $kategoriList
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getProductsList(Request $request)
    {
        $products = DB::table('tbUrunler')
            ->select('No', 'UrunID', 'AraAdlarYol', 'SistemAdi', 'SistemKodu')
            ->orderBy('UrunID')
            ->get()
            ->map(function ($row) {
                $urunId = trim($row->UrunID ?? '');
                $sistemAdi = trim($row->SistemAdi ?? '');

                return [
                    'no' => (string)$row->No,
                    'urunId' => $urunId,
                    'urunID' => $urunId,
                    'araAdlarYol' => trim($row->AraAdlarYol ?? ''),
                    'sistemAdi' => $sistemAdi,
                    'sistemKodu' => trim($row->SistemKodu ?? ''),
                    'displayName' => $sistemAdi !== '' ? $sistemAdi : ($urunId !== '' ? $urunId : 'Urun #' . $row->No),
                    'tur' => 'Nihai',
                ];
            });

        return ['success' => true, 'count' => $products->count(), 'products' => $products];
    }

    public function getPersoneller(Request $request)
    {
        $data = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->select('p.PersonelNo', 'p.Ad', 'p.Soyad', 'p.Mail', 'p.BolumAdiNo', DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"))
            ->orderBy('p.Ad')->get();
        return ['success' => true, 'data' => $data, 'count' => $data->count()];
    }

    public function getBolumler(Request $request)
    {
        $data = DB::table('tbBolum')->select('No', 'BolumAdi')->orderBy('BolumAdi')->get();
        return ['success' => true, 'data' => $data, 'count' => $data->count()];
    }

    public function getAraUrunler(Request $request)
    {
        $turParam = trim((string) $request->query('tur', ''));
        $tur = $turParam !== '' ? $this->normalizeIndependentStockType($turParam) : '';
        $computedTypeSql = $this->computedComponentTypeSql('a');
        $hasChildrenSql = $this->componentHasChildrenSql('a');
        $hasParentSql = $this->componentHasParentSql('a');

        $query = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'a.AraUrunAdi', '=', 'u.UrunID')
            ->select('a.No', 'a.AraUrunAdi', 'a.Performans', 'a.BolumAdiNo', 'a.MinAdet', 'a.Yol',
                'u.SistemKodu', 'u.UrunID',
                DB::raw("{$computedTypeSql} as UrunCesidi"),
                DB::raw("a.UrunCesidi as KayitliUrunCesidi"),
                DB::raw("CASE WHEN {$hasChildrenSql} THEN 1 ELSE 0 END as BomAltindaVar"),
                DB::raw("CASE WHEN {$hasParentSql} THEN 1 ELSE 0 END as BomUstundeVar"),
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"));

        if ($tur === 'Nihai') {
            $query->whereRaw("({$computedTypeSql}) = ?", ['Nihayi Ürün']);
        } elseif ($tur === 'Ara') {
            $query->whereRaw("({$computedTypeSql}) = ?", ['Ara Mamül']);
        } elseif ($tur === 'HamMadde') {
            $query->whereRaw("({$computedTypeSql}) = ?", ['Ham Madde']);
        }

        $search = $request->query('search');
        if (!empty($search)) {
            $query->where('a.AraUrunAdi', 'like', '%' . $search . '%');
            $query->limit(100);
        }

        $data = $query->orderBy('a.AraUrunAdi')->get();
        return ['success' => true, 'data' => $data, 'count' => $data->count()];
    }

    public function getUrunler(Request $request)
    {
        $data = DB::table('tbUrunler')
            ->select('No', 'UrunID', 'SistemAdi', 'SistemKodu', 'AraAdlarYol', 'Resim as image')
            ->orderBy('UrunID')->get();
        return ['success' => true, 'data' => $data, 'count' => $data->count()];
    }

    public function getProductBomComponents(Request $request)
    {
        $urunNo = intval($request->query('urunNo') ?? 0);
        $tur = $request->query('tur') ?? 'Ara';

        if ($urunNo <= 0) {
            return ['success' => false, 'message' => 'Geçersiz ürün numarası.'];
        }

        $araUrunAdiNo = null;
        $uID = DB::table('tbUrunler')->where('No', $urunNo)->value('UrunID');
        if ($uID) {
            $araUrunAdiNo = DB::table('tbAraUrun')->where('AraUrunAdi', $uID)->value('No');
        }

        if (!$araUrunAdiNo) {
            $araUrunAdiNo = $urunNo;
        }

        $components = [];
        $visited = [];

        $this->fetchBomComponentsRecursive($araUrunAdiNo, 1, $components, $visited, $tur);

        $uniqueComps = [];
        $uniqueSet = [];
        foreach ($components as $c) {
            if (!isset($uniqueSet[$c['id']])) {
                $uniqueSet[$c['id']] = true;
                $uniqueComps[] = $c;
            }
        }

        return ['success' => true, 'data' => $uniqueComps, 'count' => count($uniqueComps)];
    }

    public function getSetDefinitions(Request $request)
    {
        try {
            $setsQuery = DB::select("
                SELECT No as no, ExcelSetAdi as excelSetAdi, IFNULL(SetAdi,'') AS setAdi,
                       OlusturmaTarihi as olusturmaTarihi, Aktif as aktif
                FROM tbSetTanimlari
                WHERE IFNULL(Aktif,1) = 1
                ORDER BY OlusturmaTarihi DESC
            ");

            $sets = collect($setsQuery)->map(function($set) {
                if ($set->olusturmaTarihi) {
                    $set->olusturmaTarihi = Carbon::parse($set->olusturmaTarihi)->format('d.m.Y H:i');
                }
                $set->aktif = (bool)$set->aktif;

                $iceriklerQuery = DB::select("
                    SELECT i.No as no, i.UrunNo as urunNo, i.Adet as adet,
                           COALESCE(NULLIF(TRIM(u.SistemAdi), ''), NULLIF(TRIM(u.UrunID), '')) AS urunAdi
                    FROM tbSetIcerikleri i
                    LEFT JOIN tbUrunler u ON i.UrunNo = u.No
                    WHERE i.SetNo = ?
                    ORDER BY i.No
                ", [$set->no]);

                $set->icerikler = $iceriklerQuery;
                return $set;
            });

            return [
                'success' => true,
                'sets' => $sets,
                'total' => count($sets)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addSetDefinition(Request $request)
    {
        try {
            $data = $request->json()->all();
            $excelSetAdi = trim($data['excelSetAdi'] ?? '');
            $setAdi = trim($data['setAdi'] ?? '');
            $icerikler = $data['icerikler'] ?? [];

            if (empty($excelSetAdi)) {
                throw new \Exception("Excel set adı boş olamaz.");
            }
            if (empty($icerikler)) {
                throw new \Exception("En az bir bileşen eklemelisiniz.");
            }

            $setNo = DB::transaction(function () use ($excelSetAdi, $setAdi, $icerikler) {
                $existing = DB::table('tbSetTanimlari')->where('ExcelSetAdi', $excelSetAdi)->first();

                if ($existing) {
                    $setNo = $existing->No;
                    DB::table('tbSetIcerikleri')->where('SetNo', $setNo)->delete();
                    DB::table('tbSetTanimlari')->where('No', $setNo)->update([
                        'SetAdi' => $setAdi,
                        'OlusturmaTarihi' => now(),
                        'Aktif' => 1
                    ]);
                } else {
                    $setNo = DB::table('tbSetTanimlari')->insertGetId([
                        'ExcelSetAdi' => $excelSetAdi,
                        'SetAdi' => $setAdi,
                        'Aktif' => 1
                    ]);
                }

                $insertData = collect($icerikler)->map(function($ic) use ($setNo) {
                    return [
                        'SetNo' => $setNo,
                        'UrunNo' => intval($ic['urunNo']),
                        'Adet' => intval($ic['adet'] ?? 1)
                    ];
                })->toArray();

                DB::table('tbSetIcerikleri')->insert($insertData);
                return $setNo;
            });

            return [
                'success' => true,
                'setNo' => $setNo,
                'message' => "Set tanımı kaydedildi: " . (empty($setAdi) ? $excelSetAdi : $setAdi)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteSetDefinition(Request $request)
    {
        try {
            $no = intval($request->json('no'));

            DB::transaction(function () use ($no) {
                DB::table('tbSetIcerikleri')->where('SetNo', $no)->delete();
                DB::table('tbSetTanimlari')->where('No', $no)->delete();
            });

            return [
                'success' => true,
                'message' => 'Set tanımı silindi.'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function addIndependentStockOrder(Request $request)
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $parentNo = intval($payload['parentNo'] ?? $payload['satirNo'] ?? 0);
        $adet = intval($payload['adet'] ?? 0);
        $aciklama = trim((string) ($payload['aciklama'] ?? ''));
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));

        if ($parentNo <= 0 || $adet <= 0) {
            return ['success' => false, 'message' => 'Geçerli stok üretimi veya ilave adet.'];
        }

        DB::beginTransaction();
        try {
            $parent = DB::table('tbSiparisSatir')->where('No', $parentNo)->lockForUpdate()->first();
            if (!$parent) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Ana stok üretimi bulunamadı.'];
            }

            if (!$this->isFreeStockProductionRow($parent)) {
                DB::rollBack();
                return ['success' => false, 'message' => 'İlave sadece serbest stok üretimi iş emirlerinde kullanılabilir.'];
            }

            if ((int) ($parent->Aktif ?? 0) !== 1 || (string) ($parent->Durum ?? '') === 'Pasif') {
                DB::rollBack();
                return ['success' => false, 'message' => 'Pasif stok üretimine ilave açılamaz.'];
            }

            if ((int) ($parent->GorevNo ?? 0) <= 0) {
                DB::rollBack();
                return ['success' => false, 'message' => 'İlave için önce ana stok üretimi iş emrine dönüştürülmüş olmalı.'];
            }

            $rootNo = $this->stockProductionRootNo($parent);
            $root = $rootNo !== (int) $parent->No
                ? DB::table('tbSiparisSatir')->where('No', $rootNo)->lockForUpdate()->first()
                : $parent;

            if (!$root || !$this->isFreeStockProductionRow($root)) {
                DB::rollBack();
                return ['success' => false, 'message' => 'Ana stok üretim bağlantısı doğrulanamadı.'];
            }

            $tur = $this->normalizeIndependentStockType((string) ($parent->EslesenUrunTur ?: $root->EslesenUrunTur ?: 'Nihai'));
            $eslesenUrunNo = (int) ($parent->EslesenUrunNo ?: $root->EslesenUrunNo ?: 0);
            $urunAdi = (string) ($parent->UrunAdi ?: $root->UrunAdi ?: '');

            if ($eslesenUrunNo <= 0 || $urunAdi === '') {
                DB::rollBack();
                return ['success' => false, 'message' => 'Stok üretiminin ürün eşleşmesi eksik.'];
            }

            $rootTag = ['AnaStokUretimNo' => $rootNo];
            if (trim((string) ($root->StokUretimTipi ?? '')) === '') {
                $rootTag['StokUretimTipi'] = 'ana';
            }
            if ((int) ($root->StokUretimIlaveSira ?? 0) <= 0) {
                $rootTag['StokUretimIlaveSira'] = 0;
            }
            $this->updateStockProductionColumns($rootNo, $rootTag);

            $sequence = $this->nextStockProductionAdditionSequence($rootNo);
            $rootOrderNo = (string) ($root->SiparisNo ?? $parent->SiparisNo ?? '');
            $note = $aciklama !== ''
                ? $aciklama
                : sprintf('%s için +%d ilave stok üretimi', $rootOrderNo, $adet);

            $insertPayload = [
                'SiparisNo' => $this->makeStockAdditionOrderNo($rootOrderNo, $sequence),
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST İLAVE)',
                'UrunAdi' => $urunAdi,
                'Adet' => $adet,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Özel Üretim',
                'Pazaryeri' => 'Stok',
                'Magaza' => 'Zem',
                'SiparisTarihi' => now(),
                'YuklemeTarihi' => now(),
                'EslesenUrunNo' => $eslesenUrunNo,
                'EslesenUrunTur' => $tur,
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Manuel',
                'MusteriNotu' => substr($note, 0, 500),
                'SetMi' => 0,
            ];

            $insertPayload = $this->withStockProductionColumns($insertPayload, [
                'AnaStokUretimNo' => $rootNo,
                'StokUretimTipi' => 'ilave',
                'StokUretimIlaveSira' => $sequence,
            ]);

            $satirNo = DB::table('tbSiparisSatir')->insertGetId($insertPayload, 'No');
            $result = $this->orderToWorkOrder->createOrderWorkOrders([$satirNo], 0, $tur, null, $stokDurum);

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            DB::commit();

            return [
                'success' => true,
                'message' => '+' . $adet . ' ilave stok üretimi için yeni iş emri oluşturuldu.',
                'created' => $result['created'] ?? 1,
                'additionNo' => $satirNo,
                'parentNo' => $parentNo,
                'rootNo' => $rootNo,
                'sequence' => $sequence,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createIndependentStockOrder(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $urunNo = intval($payload['urunNo'] ?? 0);
        $adet = intval($payload['adet'] ?? 1);
        $tur = $this->normalizeIndependentStockType($payload['tur'] ?? 'Nihai');
        $aciklama = $payload['aciklama'] ?? '';
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));

        if ($urunNo <= 0 || $adet <= 0) {
            return ['success' => false, 'message' => 'Geçersiz ürün veya adet.'];
        }

        $ara = DB::table('tbAraUrun')->where('No', $urunNo)->first();
        if (!$ara) {
            return ['success' => false, 'message' => 'Ürün bulunamadı.'];
        }

        $urunAdi = $ara->AraUrunAdi;

        $eslesenUrunTur = 'Nihai';
        $eslesenUrunNo = $urunNo;

        if ($tur === 'Nihai') {
            $u = DB::table('tbUrunler')->where('SistemAdi', $urunAdi)->orWhere('UrunID', $urunAdi)->first();
            if ($u) {
                $eslesenUrunNo = $u->No;
                $eslesenUrunTur = 'Nihai';
            } else {
                 return ['success' => false, 'message' => 'Bu ürün "Nihai Ürün" olarak tanımlı değil. Lütfen Ara Mamül veya Ham Madde seçin veya ürünü tbUrunler tablosuna ekleyin.'];
            }
        } else {
            $eslesenUrunTur = $tur;
            $eslesenUrunNo = $urunNo;
        }

        DB::beginTransaction();
        try {
            $insertPayload = [
                'SiparisNo' => 'STOK-' . date('Ymd') . '-' . rand(1000, 9999),
                'Musteri' => 'ÖZEL ÜRETİM (SERBEST)',
                'UrunAdi' => $urunAdi,
                'Adet' => $adet,
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'Kategori' => 'Özel Üretim',
                'Pazaryeri' => 'Stok',
                'Magaza' => 'Zem',
                'SiparisTarihi' => now(),
                'YuklemeTarihi' => now(),
                'EslesenUrunNo' => $eslesenUrunNo,
                'EslesenUrunTur' => $eslesenUrunTur,
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Manuel',
                'MusteriNotu' => $aciklama ?: 'Serbest Stok Üretimi',
                'SetMi' => 0,
            ];

            $insertPayload = $this->withStockProductionColumns($insertPayload, [
                'StokUretimTipi' => 'ana',
                'StokUretimIlaveSira' => 0,
            ]);

            $satirNo = DB::table('tbSiparisSatir')->insertGetId($insertPayload, 'No');
            $this->updateStockProductionColumns($satirNo, ['AnaStokUretimNo' => $satirNo]);

            $result = $this->orderToWorkOrder->createOrderWorkOrders([$satirNo], 0, $tur, null, $stokDurum);

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            DB::commit();
            return [
                'success' => true,
                'message' => 'Serbest stok üretimi iş emri başarıyla oluşturuldu.',
                'created' => $result['created'] ?? 1
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function clearAllOrders(Request $request)
    {
        try {
            $count = DB::table('tbSiparisSatir')->count();
            DB::table('tbSiparisSatir')->delete();
            return ['success' => true, 'message' => "{$count} sipariş kaydı başarıyla silindi."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Yardımcı metotlar ───
    private function applyOrderCustomerScope($query, string $ozelUretim)
    {
        if ($ozelUretim === '1') {
            return $query->where(function ($q) {
                $q->where('Musteri', 'LIKE', 'ÖZEL ÜRETİM%')
                  ->orWhere('Musteri', 'LIKE', 'OZEL URETIM%');
            });
        }

        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->where('Musteri', 'NOT LIKE', 'ÖZEL ÜRETİM%')
                    ->where('Musteri', 'NOT LIKE', 'OZEL URETIM%');
            })
              ->orWhereNull('Musteri');
        });
    }

    private function displayOrderStatus(string $rawStatus, int $linkedSpecialProductionNo, bool $hasActiveStockFulfillment = false): string
    {
        if ($rawStatus !== 'Pasif' && ($rawStatus === 'StokKarsilandi' || $hasActiveStockFulfillment)) {
            return 'StokKarsilandi';
        }

        if ($linkedSpecialProductionNo > 0 && !in_array($rawStatus, ['Pasif', 'StokKarsilandi'], true)) {
            return 'UretimdenKarsilaniyor';
        }

        return $rawStatus;
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

    private function stockProductionColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        if (!Schema::hasTable('tbSiparisSatir')) {
            return $columns = [
                'AnaStokUretimNo' => false,
                'StokUretimTipi' => false,
                'StokUretimIlaveSira' => false,
            ];
        }

        return $columns = [
            'AnaStokUretimNo' => Schema::hasColumn('tbSiparisSatir', 'AnaStokUretimNo'),
            'StokUretimTipi' => Schema::hasColumn('tbSiparisSatir', 'StokUretimTipi'),
            'StokUretimIlaveSira' => Schema::hasColumn('tbSiparisSatir', 'StokUretimIlaveSira'),
        ];
    }

    private function isFreeStockProductionRow(object $row): bool
    {
        $tip = trim((string) ($row->StokUretimTipi ?? ''));
        $musteri = trim((string) ($row->Musteri ?? ''));

        return in_array($tip, ['ana', 'ilave'], true)
            || str_starts_with($musteri, 'ÖZEL ÜRETİM (SERBEST');
    }

    private function stockProductionRootNo(object $row): int
    {
        $rootNo = (int) ($row->AnaStokUretimNo ?? 0);

        return $rootNo > 0 ? $rootNo : (int) ($row->No ?? 0);
    }

    private function nextStockProductionAdditionSequence(int $rootNo): int
    {
        $columns = $this->stockProductionColumns();

        if (!$columns['AnaStokUretimNo'] || !$columns['StokUretimIlaveSira']) {
            return 1;
        }

        $query = DB::table('tbSiparisSatir')->where('AnaStokUretimNo', $rootNo);
        if ($columns['StokUretimTipi']) {
            $query->where('StokUretimTipi', 'ilave');
        }

        return max(1, ((int) $query->max('StokUretimIlaveSira')) + 1);
    }

    private function normalizeIndependentStockType(?string $tur): string
    {
        $tur = trim((string) $tur);
        $lower = strtolower($tur);

        if ($tur === 'HamMadde' || str_contains($lower, 'ham')) {
            return 'HamMadde';
        }

        if ($tur === 'Ara' || str_contains($lower, 'ara')) {
            return 'Ara';
        }

        return 'Nihai';
    }

    private function makeStockAdditionOrderNo(string $baseOrderNo, int $sequence): string
    {
        $cleanBase = preg_replace('/[^A-Za-z0-9\-]/', '', $baseOrderNo);
        if ($cleanBase === '') {
            $cleanBase = 'STOK-' . date('Ymd') . '-' . rand(1000, 9999);
        }

        $suffix = '-EK' . $sequence;
        return substr($cleanBase, 0, max(1, 50 - strlen($suffix))) . $suffix;
    }

    private function withStockProductionColumns(array $payload, array $values): array
    {
        $columns = $this->stockProductionColumns();

        foreach (['AnaStokUretimNo', 'StokUretimTipi', 'StokUretimIlaveSira'] as $column) {
            if ($columns[$column] && array_key_exists($column, $values)) {
                $payload[$column] = $values[$column];
            }
        }

        return $payload;
    }

    private function updateStockProductionColumns(int $satirNo, array $values): void
    {
        $payload = $this->withStockProductionColumns([], $values);

        if (!empty($payload)) {
            DB::table('tbSiparisSatir')->where('No', $satirNo)->update($payload);
        }
    }

    private function isSpecialProductionOrder(string $customerName): bool
    {
        $normalized = strtolower(trim($customerName));
        return str_starts_with($normalized, 'özel üret') || str_starts_with($normalized, 'ozel uret');
    }

    private function formatLegacyDateTime(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    private function humanElapsedSince(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            $startedAt = Carbon::parse($value);
            $diff = $startedAt->diff(now());
            if ($diff->days > 0) {
                return $diff->days . ' Gün ' . $diff->h . ' Saat';
            }

            return $diff->h . ' Saat';
        } catch (\Exception $e) {
            return '';
        }
    }

    private function resolveMatchedAraUrunNoFromMaps(mixed $esNo, mixed $esTur, $urunlerMap, $araUrunMap): int
    {
        $esNo = intval($esNo);
        $esTur = trim((string) $esTur);

        if ($esNo <= 0) {
            return 0;
        }

        if ($esTur === 'Nihai' || $esTur === 'Nihayi Ürün' || $esTur === '') {
            $urunId = $urunlerMap->get($esNo);
            if ($urunId) {
                return intval($araUrunMap->get($urunId) ?? 0);
            }
        } else {
            return $esNo; // Zaten AraUrun ID'si
        }

        return 0;
    }

    private function pendingApprovalSql(string $column = 'Onay'): string
    {
        $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";
        return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
    }

    private function productionTraceEnabled(): bool
    {
        return true;
    }

    private function specialProductionLinkedOrderRows(int $specialProductionNo): \Illuminate\Support\Collection
    {
        if ($this->supportsSplitGiedAllocations()) {
            return DB::table(self::GIED_ALLOCATION_TABLE . ' as r')
                ->join('tbSiparisSatir as s', 's.No', '=', 'r.SiparisSatirNo')
                ->where('r.OzelUretimSatirNo', $specialProductionNo)
                ->where('r.Aktif', 1)
                ->select(
                    's.No',
                    's.SiparisNo',
                    's.Pazaryeri',
                    's.Magaza',
                    's.Musteri',
                    's.UrunAdi',
                    's.Durum',
                    's.SiparisTarihi',
                    's.KargoSonTeslim',
                    's.GorevNo',
                    's.GuncellemeTarihi',
                    'r.Adet as RezervasyonAdet',
                    's.Adet as SiparisAdet'
                )
                ->get();
        }

        return DB::table('tbSiparisSatir')
            ->where('BagliOlduguOzelUretimNo', $specialProductionNo)
            ->where('Aktif', 1)
            ->select('*', DB::raw('Adet as RezervasyonAdet'), DB::raw('Adet as SiparisAdet'))
            ->get();
    }

    private function buildSpecialProductionCapacityMap(array $orders): array
    {
        $items = collect($orders)->map(fn ($o) => [
            'no' => intval($o['eslesenUrunNo'] ?? 0),
            'tur' => trim((string) ($o['eslesenUrunTur'] ?? '')),
        ])
        ->filter(fn ($item) => $item['no'] > 0)
        ->unique(fn ($item) => $item['no'] . '_' . $item['tur'])
        ->values();

        if ($items->isEmpty()) {
            return [];
        }

        $results = [];
        $urunlerMap = DB::table('tbUrunler')->pluck('UrunID', 'No');
        $araUrunMap = DB::table('tbAraUrun')->pluck('No', 'AraUrunAdi');

        foreach ($items as $item) {
            $esNo = $item['no'];
            $esTur = $item['tur'];
            $key = $this->specialProductionCapacityKey($esNo, $esTur);

            $araNo = $this->resolveMatchedAraUrunNoFromMaps($esNo, $esTur, $urunlerMap, $araUrunMap);
            if ($araNo <= 0) {
                $results[$key] = ['total' => 0, 'reserved' => 0, 'available' => 0];
                continue;
            }

            $totalCapacity = intval(DB::table('tbOzelUretim')
                ->where('UrunIDNo', $araNo)
                ->where('Aktif', 1)
                ->where(function ($q) {
                    $q->whereNull('Durum')->orWhere('Durum', '')->orWhere('Durum', 'DevamEden');
                })
                ->sum('Adet'));

            $reserved = 0;
            $activeProductions = DB::table('tbOzelUretim')
                ->where('UrunIDNo', $araNo)
                ->where('Aktif', 1)
                ->where(function ($q) {
                    $q->whereNull('Durum')->orWhere('Durum', '')->orWhere('Durum', 'DevamEden');
                })
                ->pluck('No')
                ->all();

            if (!empty($activeProductions)) {
                $reservedMap = $this->specialProductionReservedQuantities($activeProductions);
                $reserved = intval(collect($reservedMap)->sum());
            }

            $results[$key] = [
                'total' => $totalCapacity,
                'reserved' => $reserved,
                'available' => max(0, $totalCapacity - $reserved),
            ];
        }

        return $results;
    }

    private function specialProductionCapacityKey(int $productNo, string $productType): string
    {
        return $productNo . '_' . trim($productType);
    }

    private function supportsSplitGiedAllocations(): bool
    {
        return Schema::hasTable(self::GIED_ALLOCATION_TABLE);
    }

    private function specialProductionReservedQuantities(array $productionNos): array
    {
        $productionNos = collect($productionNos)
            ->map(fn ($no) => intval($no))
            ->filter(fn ($no) => $no > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($productionNos)) {
            return [];
        }

        $reserved = array_fill_keys($productionNos, 0);
        $ordersWithAllocationRows = [];

        if ($this->supportsSplitGiedAllocations()) {
            $allocationRows = DB::table(self::GIED_ALLOCATION_TABLE . ' as r')
                ->join('tbSiparisSatir as s', 's.No', '=', 'r.SiparisSatirNo')
                ->whereIn('r.OzelUretimSatirNo', $productionNos)
                ->where('r.Aktif', 1)
                ->where('s.Aktif', 1)
                ->whereNotIn('s.Durum', ['Pasif', 'StokKarsilandi'])
                ->select('r.OzelUretimSatirNo', DB::raw('SUM(r.Adet) as ReservedAdet'))
                ->groupBy('r.OzelUretimSatirNo')
                ->get();

            foreach ($allocationRows as $row) {
                $specialNo = intval($row->OzelUretimSatirNo ?? 0);
                if ($specialNo > 0) {
                    $reserved[$specialNo] = intval($reserved[$specialNo] ?? 0) + intval($row->ReservedAdet ?? 0);
                }
            }

            $ordersWithAllocationRows = DB::table(self::GIED_ALLOCATION_TABLE . ' as r')
                ->join('tbSiparisSatir as s', 's.No', '=', 'r.SiparisSatirNo')
                ->whereIn('r.OzelUretimSatirNo', $productionNos)
                ->where('r.Aktif', 1)
                ->where('s.Aktif', 1)
                ->whereNotIn('s.Durum', ['Pasif', 'StokKarsilandi'])
                ->pluck('r.SiparisSatirNo')
                ->map(fn ($no) => intval($no))
                ->unique()
                ->values()
                ->all();
        }

        $legacyQuery = DB::table('tbSiparisSatir')
            ->whereIn('BagliOlduguOzelUretimNo', $productionNos)
            ->where('Aktif', 1)
            ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi']);

        if (!empty($ordersWithAllocationRows)) {
            $legacyQuery->whereNotIn('No', $ordersWithAllocationRows);
        }

        $legacyRows = $legacyQuery
            ->select('BagliOlduguOzelUretimNo', DB::raw('SUM(Adet) as ReservedAdet'))
            ->groupBy('BagliOlduguOzelUretimNo')
            ->get();

        foreach ($legacyRows as $row) {
            $specialNo = intval($row->BagliOlduguOzelUretimNo ?? 0);
            if ($specialNo > 0) {
                $reserved[$specialNo] = intval($reserved[$specialNo] ?? 0) + intval($row->ReservedAdet ?? 0);
            }
        }

        return $reserved;
    }

    private function componentHasChildrenSql(string $alias = 'a'): string
    {
        return "TRIM(IFNULL({$alias}.Yol, '')) <> ''";
    }

    private function componentHasParentSql(string $alias = 'a'): string
    {
        return "EXISTS (
            SELECT 1
            FROM tbAraUrun parent
            WHERE parent.No <> {$alias}.No
                AND CONCAT(':', REPLACE(IFNULL(parent.Yol, ''), ' ', ''), ':') LIKE CONCAT('%:', {$alias}.No, '-%')
        )";
    }

    private function computedComponentTypeSql(string $alias = 'a'): string
    {
        $hasChildrenSql = $this->componentHasChildrenSql($alias);
        $hasParentSql = $this->componentHasParentSql($alias);

        return "CASE
            WHEN NOT ({$hasChildrenSql}) THEN 'Ham Madde'
            WHEN NOT ({$hasParentSql}) THEN 'Nihayi Ürün'
            ELSE 'Ara Mamül'
        END";
    }

    private function fetchBomComponentsRecursive(int $no, int $level, array &$components, array &$visited, $targetTur)
    {
        if (isset($visited[$no])) return;
        $visited[$no] = true;

        if ($level > 6) return;

        $item = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->where('a.No', $no)
            ->select('a.No', 'a.AraUrunAdi', 'a.UrunCesidi', 'a.Yol', 'b.BolumAdi')
            ->first();

        if (!$item) return;

        if ($level > 1) {
            $cesit = trim($item->UrunCesidi);
            $isValid = false;

            if ($targetTur === 'HamMadde' && stripos((string)$cesit, 'Ham Madde') !== false) {
                $isValid = true;
            } elseif ($targetTur === 'Ara' && (stripos((string)$cesit, 'Ara Mamül') !== false || stripos((string)$cesit, 'Bileşen') !== false || empty($cesit))) {
                if (stripos((string)$cesit, 'Ham Madde') === false) {
                    $isValid = true;
                }
            } else {
                $isValid = true;
            }

            if ($isValid) {
                $components[] = [
                    'id' => $item->No,
                    'name' => $item->AraUrunAdi,
                    'type' => $cesit ?: 'Belirsiz',
                    'department' => $item->BolumAdi ?: '',
                    'level' => $level - 1,
                ];
            }
        }

        $yol = trim((string)$item->Yol);
        if ($yol) {
            $parts = explode(':', $yol);
            foreach ($parts as $part) {
                if (!$part) continue;
                $subNo = intval(explode('-', $part)[0]);
                if ($subNo > 0) {
                    $this->fetchBomComponentsRecursive($subNo, $level + 1, $components, $visited, $targetTur);
                }
            }
        }
    }

    private function normalizeWorkOrderStockMode(string $mode): string
    {
        $mode = trim($mode);
        if ($mode === 'StokHaric' || strtolower($mode) === 'stokharic') {
            return 'StokHaric';
        }
        return 'StokDahil';
    }
}
