<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\OrderItem;
use App\Models\ProductMatchCache;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use App\Services\OrderSyncService;
use App\Services\BomService;
use App\Services\WorkOrderService;

class SiparisApiController extends Controller
{
    public function __construct(
        protected \App\Services\OrderSyncService $orderSync,
        protected \App\Services\OrderToWorkOrderService $orderToWorkOrder,
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Handles all legacy ?action= AJAX requests seamlessly
     */
    public function handleEndpoint(Request $request)
    {
        $action = $request->input('action');

        try {
            switch ($action) {
                case 'uploadOrders':
                    return $this->uploadOrders($request);
                case 'getOrders':
                    return $this->getOrders($request);
                case 'matchProduct':
                    return $this->matchProduct($request);
                case 'createOrderWorkOrders':
                    return $this->createOrderWorkOrders($request);
                case 'createManualWorkOrder':
                    return $this->createManualWorkOrder($request);
                case 'passivateWithWorkOrderCancel':
                    return $this->passivateWithWorkOrderCancel($request);
                case 'saveStockCodes':
                    return $this->saveStockCodes($request);
                case 'getSummary':
                    return $this->getSummary($request);
                case 'getProducts':
                    return $this->getProductsList($request);
                case 'cancelWorkOrder':
                    return $this->cancelWorkOrder($request);
                case 'cancelBulkWorkOrders':
                    return $this->cancelBulkWorkOrders($request);
                case 'getPersoneller':
                    return $this->getPersoneller($request);
                case 'getBolumler':
                    return $this->getBolumler($request);
                case 'getAraUrunler':
                    return $this->getAraUrunler($request);
                case 'getUrunler':
                    return $this->getUrunler($request);
                case 'importPersoneller':
                    return $this->importPersoneller($request);
                case 'importBolumler':
                    return $this->importBolumler($request);
                case 'importAraUrunler':
                    return $this->importAraUrunler($request);
                case 'importUrunler':
                    return $this->importUrunler($request);
                case 'clearAllOrders':
                    return $this->clearAllOrders($request);
                case 'deductStock':
                    return $this->deductStock($request);
                case 'deductStockBulk':
                    return $this->deductStockBulk($request);
                case 'getThresholds':
                    return $this->getThresholds($request);
                case 'saveThreshold':
                    return $this->saveThreshold($request);
                case 'deleteThreshold':
                    return $this->deleteThreshold($request);
                case 'getCriticalStockAlerts':
                    return $this->getCriticalStockAlerts($request);
                case 'resetThresholds':
                    return $this->resetThresholds($request);
                case 'rematchOrders':
                    return $this->rematchOrders($request);
                case 'getMatchCache':
                    return $this->getMatchCache($request);
                case 'addMatchCache':
                    return $this->addMatchCache($request);
                case 'deleteMatchCache':
                    return $this->deleteMatchCache($request);
                case 'getSetDefinitions':
                    return $this->getSetDefinitions($request);
                case 'addSetDefinition':
                    return $this->addSetDefinition($request);
                case 'deleteSetDefinition':
                    return $this->deleteSetDefinition($request);
                case 'getWorkOrderHistory':
                    return $this->getWorkOrderHistory($request);
                case 'getAvailableSpecialProductions':
                    return $this->getAvailableSpecialProductions($request);
                case 'linkOrderToSpecialProduction':
                    return $this->linkOrderToSpecialProduction($request);
                case 'cancelWipAllocation':
                    return $this->cancelWipAllocation($request);
                case 'onlyUpdateStatusBulk':
                    return $this->onlyUpdateStatusBulk($request);
                case 'fixKayipOzelUretim':
                    return $this->fixKayipOzelUretim($request);
                case 'getPasifDevamEden':
                    return $this->getPasifDevamEden($request);
                case 'reactivateOrder':
                    return $this->reactivateOrder($request);
                default:
                    return response()->json(['success' => false, 'message' => "Geçersiz action parametresi: {$action}"]);
            }
        } catch (Exception $ex) {
            return response()->json([
                'success' => false,
                'message' => 'Hata: ' . $ex->getMessage(),
                'stack' => config('app.debug') ? $ex->getTraceAsString() : null
            ]);
        }
    }

    private function uploadOrders(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload || !isset($payload['rows'])) {
            return response()->json(['success' => false, 'message' => 'rows verisi bulunamadi.']);
        }
        $rows = $payload['rows'];
        if (empty($rows)) {
            return response()->json(['success' => false, 'message' => 'Boş veri.']);
        }
        $result = $this->orderSync->uploadOrders($rows);
        return response()->json($result);
    }

    private function getOrders(Request $request)
    {
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
            $pazaryeriList = DB::table('tbSiparisSatir')
                ->whereNotNull('Pazaryeri')->where('Pazaryeri', '!=', '')
                ->distinct()->orderBy('Pazaryeri')->pluck('Pazaryeri')->toArray();
            $magazaList = DB::table('tbSiparisSatir')
                ->whereNotNull('Magaza')->where('Magaza', '!=', '')
                ->distinct()->orderBy('Magaza')->pluck('Magaza')->toArray();
            $kategoriList = DB::table('tbSiparisSatir')
                ->whereNotNull('Kategori')->where('Kategori', '!=', '')
                ->distinct()->orderBy('Kategori')->pluck('Kategori')->toArray();

            // ─── Ana sorgu ───
            $query = DB::table('tbSiparisSatir')
                ->select(
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
                    DB::raw("IFNULL(AnaSetSatirNo,0) AS AnaSetSatirNo")
                );

            // Özel üretim filtresi
            if ($ozelUretim === '1') {
                $query->where('Musteri', 'LIKE', 'ÖZEL ÜRETİM%');
            } else {
                $query->where(function ($q) {
                    $q->where('Musteri', 'NOT LIKE', 'ÖZEL ÜRETİM%')
                      ->orWhereNull('Musteri');
                });
            }

            // Durum filtresi — ASP.NET ile birebir aynı
            if ($durum === 'UretimBekliyor') {
                $query->where('Durum', 'UretimBekliyor')->where('Aktif', 1);
            } elseif ($durum === 'IsEmriVerildi') {
                $query->where('Durum', 'IsEmriVerildi');
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
            } elseif ($durum === 'StokKarsilandi') {
                $query->where('Durum', 'StokKarsilandi');
            } else {
                // Tümü — varsayılan olarak aktif
                $query->where('Aktif', 1);
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

            // Sıralama (ASP.NET ile aynı: önce pasif/inaktif sonunda, sonra kargo tarihi ASC)
            $query->orderByRaw("CASE WHEN Durum='Pasif' OR Aktif=0 THEN 1 ELSE 0 END ASC")
                  ->orderBy('KargoSonTeslim', 'asc')
                  ->orderBy('SiparisTarihi', 'asc');

            $rows = $query->get();

            // ─── Response formatı: ASP.NET ile birebir uyumlu ───
            $orders = $rows->map(function ($row) {
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
                    'durum'           => (string) ($row->Durum ?? ''),
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
                    'stokAdet'        => 0,       // Sonra doldurulacak
                    'uretimdeAdet'    => 0,        // Sonra doldurulacak
                ];
            })->values()->toArray();

            // ─── Özel Üretim ise bağlı sipariş bilgisi ekle ───
            if ($ozelUretim === '1') {
                foreach ($orders as &$ord) {
                    $bagliRows = DB::table('tbSiparisSatir')
                        ->where('BagliOlduguOzelUretimNo', $ord['no'])
                        ->where('Aktif', 1)
                        ->where('Durum', '!=', 'Pasif')
                        ->select('No', 'SiparisNo', 'Adet')
                        ->get();

                    $bagliList = [];
                    $reserveAdet = 0;
                    foreach ($bagliRows as $b) {
                        $bagliList[] = ['no' => $b->No, 'siparisNo' => $b->SiparisNo, 'adet' => (int) $b->Adet];
                        $reserveAdet += (int) $b->Adet;
                    }
                    $ord['bagliSiparisler'] = $bagliList;
                    $ord['bostaKalan'] = $ord['adet'] - $reserveAdet;
                    $ord['rezerveEdilen'] = $reserveAdet;
                }
                unset($ord);
            }

            // ─── Stok bilgisi ekle (ASP.NET birebir) ───
            // Önbellek: tbUrunler.No → UrunID → tbAraUrun.No (performans için toplu yükle)
            $urunlerMap = DB::table('tbUrunler')->pluck('UrunID', 'No'); // No → UrunID
            $araUrunMap = DB::table('tbAraUrun')->pluck('No', 'AraUrunAdi'); // AraUrunAdi → No
            $stokMap = DB::table('tbBolumAraStok')
                ->select('AraUrunAdiNo', DB::raw('SUM(Adet) as ToplamStok'))
                ->groupBy('AraUrunAdiNo')->pluck('ToplamStok', 'AraUrunAdiNo'); // AraUrunAdiNo → ToplamStok

            foreach ($orders as &$ord) {
                $esNo = $ord['eslesenUrunNo'];
                $esTur = $ord['eslesenUrunTur'];
                $ord['stokAdet'] = 0;

                if ($esNo > 0 && $esTur === 'Nihai') {
                    $urunID = $urunlerMap[$esNo] ?? '';
                    if (!empty($urunID)) {
                        $araNo = $araUrunMap[trim($urunID)] ?? 0;
                        if ($araNo > 0) {
                            $ord['stokAdet'] = (int) ($stokMap[$araNo] ?? 0);
                        }
                    }
                } elseif ($esNo > 0 && $esTur === 'Ara') {
                    $ord['stokAdet'] = (int) ($stokMap[$esNo] ?? 0);
                }
            }

            // ─── Üretimde adet bilgisi ekle (ASP.NET birebir) ───
            // Havuz (atanmamış) + PersonelGorev (atanmış devam eden)
            $havuzMap = DB::table('tbBolumHavuz')
                ->select('AraUrunAdiNo', DB::raw('SUM(IFNULL(ToplamAdet,0)) as Toplam'))
                ->groupBy('AraUrunAdiNo')->pluck('Toplam', 'AraUrunAdiNo');
            $pGorevMap = DB::table('tbPersonelGorev')
                ->select('AraUrunAdiNo', DB::raw('SUM(IFNULL(Adet,0) + IFNULL(BekleyenAdet,0)) as Toplam'))
                ->groupBy('AraUrunAdiNo')->pluck('Toplam', 'AraUrunAdiNo');

            foreach ($orders as &$ord) {
                $esNo = $ord['eslesenUrunNo'];
                $esTur = $ord['eslesenUrunTur'];
                $ord['uretimdeAdet'] = 0;

                if ($esNo > 0) {
                    $araUrunNoUretim = 0;
                    if ($esTur === 'Nihai') {
                        $urunID = $urunlerMap[$esNo] ?? '';
                        if (!empty($urunID)) {
                            $araUrunNoUretim = $araUrunMap[trim($urunID)] ?? 0;
                        }
                    } elseif ($esTur === 'Ara') {
                        $araUrunNoUretim = $esNo;
                    }
                    if ($araUrunNoUretim > 0) {
                        $ord['uretimdeAdet'] = (int)(($havuzMap[$araUrunNoUretim] ?? 0) + ($pGorevMap[$araUrunNoUretim] ?? 0));
                    }
                }
            }
            unset($ord);

            // ─── İstatistik sayıları (ASP.NET birebir) ───
            $statsRow = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE Aktif=1 AND IFNULL(AnaSetSatirNo,0)=0) AS Toplam,
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE Durum='UretimBekliyor' AND Aktif=1 AND IFNULL(AnaSetSatirNo,0)=0) AS UretimBekliyor,
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE Durum='IsEmriVerildi' AND IFNULL(AnaSetSatirNo,0)=0) AS IsEmriVerildi,
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE Durum='StokKarsilandi' AND IFNULL(AnaSetSatirNo,0)=0) AS StokKarsilandi,
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE Durum='PasifDevamEden' AND IFNULL(AnaSetSatirNo,0)=0) AS PasifDevamEden,
                    (SELECT COUNT(*) FROM tbSiparisSatir WHERE (EslesenUrunNo IS NULL OR EslesenUrunNo=0) AND IFNULL(SetMi,0)=0 AND IFNULL(AnaSetSatirNo,0)=0 AND Aktif=1) AS Eslesmeyenler,
                    (SELECT MAX(YuklemeTarihi) FROM tbSiparisSatir) AS SonYukleme
            ");

            $sonYukleme = '';
            if ($statsRow->SonYukleme) {
                try { $sonYukleme = Carbon::parse($statsRow->SonYukleme)->format('d.m.Y H:i'); } catch (\Exception $e) {}
            }

            $stats = [
                'toplam'          => (int) $statsRow->Toplam,
                'uretimBekliyor'  => (int) $statsRow->UretimBekliyor,
                'isEmriVerildi'   => (int) $statsRow->IsEmriVerildi,
                'stokKarsilandi'  => (int) $statsRow->StokKarsilandi,
                'pasifDevamEden'  => (int) $statsRow->PasifDevamEden,
                'eslesmeyenler'   => (int) $statsRow->Eslesmeyenler,
                'sonYukleme'      => $sonYukleme,
            ];

            return response()->json([
                'success' => true,
                'count'   => count($orders),
                'orders'  => $orders,
                'stats'   => $stats,
                'filters' => [
                    'pazaryeriList' => $pazaryeriList,
                    'magazaList'    => $magazaList,
                    'kategoriList'  => $kategoriList,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getMatchCache(Request $request)
    {
        try {
            $itemsQuery = DB::select("
                SELECT c.No as no, c.ExcelUrunAdi as excelUrunAdi, c.EslesenUrunNo as eslesenUrunNo, 
                       c.EslesenUrunTur as eslesenUrunTur, c.OlusturmaTarihi as olusturmaTarihi, 
                       IFNULL(u.SistemAdi, u.UrunID) AS sistemUrunAdi
                FROM tbUrunEslestirmeOnbellek c 
                LEFT JOIN tbUrunler u ON c.EslesenUrunNo = u.No 
                ORDER BY c.OlusturmaTarihi DESC
            ");

            $items = collect($itemsQuery)->map(function($row) {
                if ($row->olusturmaTarihi) {
                    $row->olusturmaTarihi = \Carbon\Carbon::parse($row->olusturmaTarihi)->format('d.m.Y H:i');
                }
                return $row;
            });

            return response()->json([
                'success' => true,
                'items' => $items,
                'total' => count($items)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function addMatchCache(Request $request)
    {
        try {
            $data = $request->json()->all();
            $excelUrunAdi = trim($data['excelUrunAdi'] ?? '');
            $eslesenUrunNo = intval($data['eslesenUrunNo'] ?? 0);
            $eslesenUrunTur = $data['eslesenUrunTur'] ?? 'Nihai';

            if (empty($excelUrunAdi)) {
                throw new \Exception("Excel ürün adı boş olamaz.");
            }

            // Using UPSERT logic
            DB::statement("
                INSERT INTO tbUrunEslestirmeOnbellek (ExcelUrunAdi, EslesenUrunNo, EslesenUrunTur)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE EslesenUrunNo = VALUES(EslesenUrunNo), EslesenUrunTur = VALUES(EslesenUrunTur), OlusturmaTarihi = CURRENT_TIMESTAMP
            ", [$excelUrunAdi, $eslesenUrunNo, $eslesenUrunTur]);

            return response()->json([
                'success' => true,
                'message' => 'Eşleştirme kaydedildi: ' . $excelUrunAdi
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function deleteMatchCache(Request $request)
    {
        try {
            $data = $request->json()->all();
            $deleted = 0;
            $clearedOrders = 0;

            $processDeletion = function ($no) use (&$deleted, &$clearedOrders) {
                $excelAdi = DB::table('tbUrunEslestirmeOnbellek')->where('No', $no)->value('ExcelUrunAdi');
                
                if ($excelAdi) {
                    $clearedOrders += DB::table('tbSiparisSatir')
                        ->where('UrunAdi', $excelAdi)
                        ->whereIn('EslesmeYontemi', ['Onbellek', 'Manuel'])
                        ->whereNotIn('Durum', ['IsEmriVerildi', 'StokKarsilandi'])
                        ->update([
                            'EslesenUrunNo' => null,
                            'EslesenUrunTur' => null,
                            'EslesmePuani' => null,
                            'EslesmeYontemi' => null
                        ]);
                }
                $deleted += DB::table('tbUrunEslestirmeOnbellek')->where('No', $no)->delete();
            };

            if (isset($data['no'])) {
                 $processDeletion($data['no']);
            } elseif (isset($data['noList']) && is_array($data['noList'])) {
                 foreach ($data['noList'] as $no) {
                     $processDeletion($no);
                 }
            }

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'clearedOrders' => $clearedOrders,
                'message' => $deleted . ' eşleştirme silindi.' . ($clearedOrders > 0 ? " $clearedOrders sipariş eşleşmesi de temizlendi." : "")
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getSetDefinitions(Request $request)
    {
        try {
            $setsQuery = DB::select("
                SELECT No as no, ExcelSetAdi as excelSetAdi, IFNULL(SetAdi,'') AS setAdi, 
                       OlusturmaTarihi as olusturmaTarihi, Aktif as aktif 
                FROM tbSetTanimlari 
                ORDER BY OlusturmaTarihi DESC
            ");

            $sets = collect($setsQuery)->map(function($set) {
                if ($set->olusturmaTarihi) {
                    $set->olusturmaTarihi = \Carbon\Carbon::parse($set->olusturmaTarihi)->format('d.m.Y H:i');
                }
                $set->aktif = (bool)$set->aktif;

                $iceriklerQuery = DB::select("
                    SELECT i.No as no, i.UrunNo as urunNo, i.Adet as adet, 
                           IFNULL(u.SistemAdi, u.UrunID) AS urunAdi
                    FROM tbSetIcerikleri i 
                    LEFT JOIN tbUrunler u ON i.UrunNo = u.No 
                    WHERE i.SetNo = ? 
                    ORDER BY i.No
                ", [$set->no]);

                $set->icerikler = $iceriklerQuery;
                return $set;
            });

            return response()->json([
                'success' => true,
                'sets' => $sets,
                'total' => count($sets)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function addSetDefinition(Request $request)
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
                        'OlusturmaTarihi' => now()
                    ]);
                } else {
                    $setNo = DB::table('tbSetTanimlari')->insertGetId([
                        'ExcelSetAdi' => $excelSetAdi,
                        'SetAdi' => $setAdi
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

            return response()->json([
                'success' => true,
                'setNo' => $setNo,
                'message' => "Set tanımı kaydedildi: " . (empty($setAdi) ? $excelSetAdi : $setAdi)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function deleteSetDefinition(Request $request)
    {
        try {
            $no = intval($request->json('no'));
            
            DB::transaction(function () use ($no) {
                DB::table('tbSetIcerikleri')->where('SetNo', $no)->delete();
                DB::table('tbSetTanimlari')->where('No', $no)->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Set tanımı silindi.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   CANCEL WORK ORDER — Tekli iş emri iptal
    // ================================================================
    private function cancelWorkOrder(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNo = intval($data['satirNo'] ?? 0);

            $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
            if (!$satir) throw new \Exception('Satır bulunamadı.');

            $gorevNo = intval($satir->GorevNo ?? 0);
            $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
            $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
            $tamponJson = $satir->TamponDusumleri ?? '';
            $durum = $satir->Durum ?? '';
            $musteri = $satir->Musteri ?? '';

            // ASP.NET durum kontrolü: IsEmriVerildi VEYA (UretimBekliyor + ÖZEL ÜRETİM)
            $isOzelUretim = ($durum === 'UretimBekliyor' && str_starts_with($musteri, 'ÖZEL ÜRETİM'));
            if ($durum !== 'IsEmriVerildi' && !$isOzelUretim) {
                return response()->json(['success' => false, 'message' => 'Bu durumdaki sipariş iptal edilemez.']);
            }

            // Tampon stoğu geri yükle
            $this->restoreTamponFromJson($tamponJson);

            // tbBolumHavuz'dan görevleri sil
            $deleted = 0;
            if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
                $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->delete();
            } elseif ($gorevNo > 0) {
                $deleted = DB::table('tbBolumHavuz')->where('No', $gorevNo)->delete();
            }

            // Log kaydi
            $this->logIsEmriGecmisi($satirNo, $gorevNo, $eslesenUrunNo, $eslesenUrunTur, 'IptalEdildi');

            // Sipariş durumunu geri al (ASP.NET: Özel Üretimse Pasife al)
            $yeniDurum = $isOzelUretim ? 'Pasif' : 'UretimBekliyor';
            $yeniAktif = $isOzelUretim ? 0 : 1;

            DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                'Durum' => $yeniDurum, 'Aktif' => $yeniAktif,
                'GorevNo' => null, 'IsEmriTarihi' => null,
                'TamponDusumleri' => null, 'GuncellemeTarihi' => now()
            ]);

            return response()->json(['success' => true, 'deleted' => $deleted, 'message' => "İş emri iptal edildi. {$deleted} görev silindi."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   CANCEL BULK WORK ORDERS — Toplu iş emri iptal
    // ================================================================
    private function cancelBulkWorkOrders(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNolar = $data['satirNolar'] ?? [];
            $toplamIptal = 0; $toplamSilinen = 0; $hatalar = [];

            foreach ($satirNolar as $satirNo) {
                try {
                    $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                    if (!$satir) continue;

                    $durum = $satir->Durum ?? '';
                    $musteri = $satir->Musteri ?? '';

                    // ASP.NET durum kontrolü: IsEmriVerildi VEYA (UretimBekliyor + ÖZEL ÜRETİM)
                    $isOzelUretim = ($durum === 'UretimBekliyor' && str_starts_with($musteri, 'ÖZEL ÜRETİM'));
                    if ($durum !== 'IsEmriVerildi' && !$isOzelUretim) continue;

                    $this->restoreTamponFromJson($satir->TamponDusumleri ?? '');

                    $deleted = 0;
                    $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                    $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                    $gorevNo = intval($satir->GorevNo ?? 0);

                    if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
                        $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->delete();
                    } elseif ($gorevNo > 0) {
                        $deleted = DB::table('tbBolumHavuz')->where('No', $gorevNo)->delete();
                    }

                    $this->logIsEmriGecmisi($satirNo, $gorevNo, $eslesenUrunNo, $eslesenUrunTur, 'IptalEdildi');

                    // ASP.NET: Özel Üretimse Pasife al
                    $yeniDurum = $isOzelUretim ? 'Pasif' : 'UretimBekliyor';
                    $yeniAktif = $isOzelUretim ? 0 : 1;

                    DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                        'Durum' => $yeniDurum, 'Aktif' => $yeniAktif,
                        'GorevNo' => null, 'IsEmriTarihi' => null,
                        'TamponDusumleri' => null, 'GuncellemeTarihi' => now()
                    ]);
                    $toplamIptal++; $toplamSilinen += $deleted;
                } catch (\Exception $ex) {
                    $hatalar[] = "Satır {$satirNo}: " . $ex->getMessage();
                }
            }

            return response()->json([
                'success' => true, 'iptalEdilen' => $toplamIptal, 'silinenGorev' => $toplamSilinen,
                'hatalar' => $hatalar, 'message' => "{$toplamIptal} iş emri iptal edildi. {$toplamSilinen} görev silindi." . (count($hatalar) > 0 ? ' (' . count($hatalar) . ' hata oluştu)' : '')
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   PASSIVATE WITH WORK ORDER CANCEL
    // ================================================================
    private function passivateWithWorkOrderCancel(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNolar = $data['satirNolar'] ?? [];
            $cancelled = 0; $deletedTotal = 0;

            foreach ($satirNolar as $satirNo) {
                try {
                    $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                    if (!$satir) continue;

                    $this->restoreTamponFromJson($satir->TamponDusumleri ?? '');

                    $deleted = 0;
                    $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                    $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                    $gorevNo = intval($satir->GorevNo ?? 0);

                    if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
                        $deleted = DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->delete();
                    } elseif ($gorevNo > 0) {
                        $deleted = DB::table('tbBolumHavuz')->where('No', $gorevNo)->delete();
                    }

                    $this->logIsEmriGecmisi($satirNo, $gorevNo, $eslesenUrunNo, $eslesenUrunTur, 'IptalEdildi');

                    DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                        'Durum' => 'Pasif', 'Aktif' => 0, 'GorevNo' => null, 'IsEmriTarihi' => null,
                        'TamponDusumleri' => null, 'GuncellemeTarihi' => now()
                    ]);
                    $cancelled++; $deletedTotal += $deleted;
                } catch (\Exception $ex) { /* skip */ }
            }

            return response()->json([
                'success' => true, 'cancelled' => $cancelled, 'deletedTasks' => $deletedTotal,
                'message' => "{$cancelled} sipariş pasife alındı, {$deletedTotal} görev silindi."
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   SAVE STOCK CODES
    // ================================================================
    private function saveStockCodes(Request $request)
    {
        try {
            $data = $request->json()->all();
            $items = $data['items'] ?? [];
            $saved = 0;

            foreach ($items as $item) {
                $urunNo = intval($item['urunNo'] ?? 0);
                $stokKodu = $item['stokKodu'] ?? '';
                $saved += DB::table('tbUrunler')->where('No', $urunNo)->update(['SistemKodu' => $stokKodu]);
            }

            return response()->json(['success' => true, 'saved' => $saved, 'message' => "{$saved} ürünün stok kodu kaydedildi."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   GET SUMMARY — Özet dashboard verileri
    // ================================================================
    private function getSummary(Request $request)
    {
        try {
            $kategori = $request->input('kategori', '');

            // Kategori listesi
            $kategoriList = DB::table('tbSiparisSatir')
                ->where('Durum', 'UretimBekliyor')->where('Aktif', 1)
                ->whereNotNull('Kategori')->where('Kategori', '!=', '')
                ->distinct()->pluck('Kategori')->sort()->values();

            $query = "
                SELECT MIN(S.UrunAdi) AS UrunAdi, SUM(S.Adet) AS ToplamAdet, COUNT(*) AS SiparisSayisi,
                    MIN(S.KargoSonTeslim) AS EnYakinKargo,
                    MAX(CASE WHEN IFNULL(S.EslesenUrunNo,0) > 0 THEN S.EslesenUrunNo ELSE IFNULL(C.EslesenUrunNo,0) END) AS EslesenUrunNo,
                    MAX(CASE WHEN IFNULL(S.EslesenUrunTur,'') != '' THEN S.EslesenUrunTur ELSE IFNULL(C.EslesenUrunTur,'') END) AS EslesenUrunTur
                FROM tbSiparisSatir S
                LEFT JOIN tbUrunEslestirmeOnbellek C ON S.UrunAdi = C.ExcelUrunAdi
                WHERE S.Durum='UretimBekliyor' AND S.Aktif=1 AND IFNULL(S.SetMi,0)=0
            ";
            $bindings = [];
            if (!empty($kategori)) {
                $query .= " AND S.Kategori=?";
                $bindings[] = $kategori;
            }
            $query .= " GROUP BY CASE WHEN IFNULL(S.EslesenUrunNo,0) > 0 THEN CONCAT(IFNULL(S.EslesenUrunNo,0), '_', IFNULL(S.EslesenUrunTur,'')) ELSE S.UrunAdi END";

            $bomService = app(\App\Services\BomService::class);

            $summary = collect(DB::select($query, $bindings))->map(function ($row) use ($bomService) {
                if ($row->EnYakinKargo) {
                    try { $row->EnYakinKargo = \Carbon\Carbon::parse($row->EnYakinKargo)->format('d.m.Y H:i'); } catch (\Exception $e) {}
                }
                // Sistem adi lookup
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
                    
                    // Üretim Planlama hesabı
                    $row->UretilebilirAdet = $bomService->uretilebilirNihaiAdet($no, $row->EslesenUrunTur ?? 'Nihayi Ürün');
                }
                return $row;
            });

            $toplamAdet = $summary->sum('ToplamAdet');

            return response()->json([
                'success' => true, 'count' => $summary->count(), 'toplamAdet' => $toplamAdet,
                'summary' => $summary, 'kategoriList' => $kategoriList
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   GET PRODUCTS — Ürün listesi (eşleştirme dropdown için)
    // ================================================================
    private function getProductsList(Request $request)
    {
        $products = DB::table('tbUrunler')
            ->select('No', 'UrunID', 'AraAdlarYol', 'SistemAdi', 'SistemKodu')
            ->orderBy('UrunID')
            ->get()
            ->map(function ($row) {
                return [
                    'no' => (string)$row->No,
                    'urunId' => trim($row->UrunID ?? ''),
                    'araAdlarYol' => trim($row->AraAdlarYol ?? ''),
                    'sistemAdi' => trim($row->SistemAdi ?? ''),
                    'sistemKodu' => trim($row->SistemKodu ?? ''),
                    'tur' => 'Nihai',
                ];
            });

        return response()->json(['success' => true, 'count' => $products->count(), 'products' => $products]);
    }

    // ================================================================
    //   GET PERSONELLER / BOLUMLER / ARAURUNLER / URUNLER — DB Yönetim
    // ================================================================
    private function getPersoneller(Request $request)
    {
        $data = DB::table('tbPersonel as p')
            ->leftJoin('tbBolum as b', 'p.BolumAdiNo', '=', 'b.No')
            ->select('p.PersonelNo', 'p.Ad', 'p.Soyad', 'p.Mail', 'p.BolumAdiNo', DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"))
            ->orderBy('p.Ad')->get();
        return response()->json(['success' => true, 'data' => $data, 'count' => $data->count()]);
    }

    private function getBolumler(Request $request)
    {
        $data = DB::table('tbBolum')->select('No', 'BolumAdi')->orderBy('BolumAdi')->get();
        return response()->json(['success' => true, 'data' => $data, 'count' => $data->count()]);
    }

    private function getAraUrunler(Request $request)
    {
        $data = DB::table('tbAraUrun as a')
            ->leftJoin('tbBolum as b', 'a.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'a.AraUrunAdi', '=', 'u.UrunID')
            ->select('a.No', 'a.AraUrunAdi', 'a.Performans', 'a.BolumAdiNo', 'a.MinAdet', 'a.UrunCesidi', 'a.Yol',
                'u.SistemKodu', 'u.UrunID',
                DB::raw("IFNULL(b.BolumAdi,'') as BolumAdi"))
            ->orderBy('a.AraUrunAdi')->get();
        return response()->json(['success' => true, 'data' => $data, 'count' => $data->count()]);
    }

    private function getUrunler(Request $request)
    {
        $data = DB::table('tbUrunler')
            ->select('No', 'UrunID', 'SistemAdi', 'SistemKodu', 'AraAdlarYol', 'Resim as image')
            ->orderBy('UrunID')->get();
        return response()->json(['success' => true, 'data' => $data, 'count' => $data->count()]);
    }

    // ================================================================
    //   IMPORT PERSONELLER / BOLUMLER / ARAURUNLER / URUNLER
    // ================================================================
    private function importPersoneller(Request $request)
    {
        try {
            $rows = $request->json()->all();
            $updated = 0; $inserted = 0;
            foreach ($rows as $row) {
                $no = $this->findVal($row, 'PersonelNo');
                if (!empty($no)) {
                    $exists = DB::table('tbPersonel')->where('PersonelNo', $no)->exists();
                    $vals = [];
                    if ($this->hasVal($row, 'Ad')) $vals['Ad'] = $this->findVal($row, 'Ad');
                    if ($this->hasVal($row, 'Soyad')) $vals['Soyad'] = $this->findVal($row, 'Soyad');
                    if ($this->hasVal($row, 'Mail')) $vals['Mail'] = $this->findVal($row, 'Mail');
                    if ($this->hasVal($row, 'BolumAdiNo')) $vals['BolumAdiNo'] = intval($this->findVal($row, 'BolumAdiNo'));
                    if ($exists && count($vals) > 0) { DB::table('tbPersonel')->where('PersonelNo', $no)->update($vals); $updated++; }
                    elseif (!$exists) { $vals['PersonelNo'] = $no; DB::table('tbPersonel')->insert($vals); $inserted++; }
                }
            }
            return response()->json(['success' => true, 'updated' => $updated, 'inserted' => $inserted, 'message' => "{$updated} güncellendi, {$inserted} eklendi."]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function importBolumler(Request $request)
    {
        try {
            $rows = $request->json()->all();
            $updated = 0; $inserted = 0;
            foreach ($rows as $row) {
                $no = $this->findVal($row, 'No');
                $bolumAdi = $this->findVal($row, 'BolumAdi', 'Bolum Adi', 'Bölüm Adı');
                if (!empty($no)) {
                    $exists = DB::table('tbBolum')->where('No', $no)->exists();
                    if ($exists) { DB::table('tbBolum')->where('No', $no)->update(['BolumAdi' => $bolumAdi]); $updated++; }
                    else { DB::table('tbBolum')->insert(['BolumAdi' => $bolumAdi]); $inserted++; }
                }
            }
            return response()->json(['success' => true, 'updated' => $updated, 'inserted' => $inserted, 'message' => "{$updated} güncellendi, {$inserted} eklendi."]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function importAraUrunler(Request $request)
    {
        try {
            $rows = $request->json()->all();
            $updated = 0; $inserted = 0;
            foreach ($rows as $row) {
                $no = $this->findVal($row, 'No');
                if (!empty($no) && $no !== '0') {
                    $vals = [];
                    if ($this->hasVal($row, 'AraUrunAdi')) $vals['AraUrunAdi'] = $this->findVal($row, 'AraUrunAdi', 'Ara Urun Adi');
                    if ($this->hasVal($row, 'Performans')) $vals['Performans'] = intval($this->findVal($row, 'Performans'));
                    if ($this->hasVal($row, 'BolumAdiNo')) $vals['BolumAdiNo'] = intval($this->findVal($row, 'BolumAdiNo'));
                    if ($this->hasVal($row, 'MinAdet')) $vals['MinAdet'] = intval($this->findVal($row, 'MinAdet'));
                    if ($this->hasVal($row, 'UrunCesidi')) $vals['UrunCesidi'] = $this->findVal($row, 'UrunCesidi', 'Urun Cesidi');
                    if ($this->hasVal($row, 'Yol')) $vals['Yol'] = $this->findVal($row, 'Yol');

                    $exists = DB::table('tbAraUrun')->where('No', $no)->exists();
                    if ($exists && count($vals) > 0) { DB::table('tbAraUrun')->where('No', $no)->update($vals); $updated++; }
                    elseif (!$exists && count($vals) > 0) { DB::table('tbAraUrun')->insert($vals); $inserted++; }
                }
            }
            return response()->json(['success' => true, 'updated' => $updated, 'inserted' => $inserted, 'message' => "{$updated} güncellendi, {$inserted} eklendi."]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    private function importUrunler(Request $request)
    {
        try {
            $rows = $request->json()->all();
            $updated = 0; $inserted = 0;
            foreach ($rows as $row) {
                $no = $this->findVal($row, 'No');
                if (!empty($no) && $no !== '0') {
                    $vals = [];
                    if ($this->hasVal($row, 'UrunID')) $vals['UrunID'] = $this->findVal($row, 'UrunID', 'Urun ID');
                    if ($this->hasVal($row, 'SistemAdi')) $vals['SistemAdi'] = $this->findVal($row, 'SistemAdi', 'Sistem Adi');
                    if ($this->hasVal($row, 'SistemKodu')) $vals['SistemKodu'] = $this->findVal($row, 'SistemKodu', 'Sistem Kodu');
                    if ($this->hasVal($row, 'AraAdlarYol')) $vals['AraAdlarYol'] = $this->findVal($row, 'AraAdlarYol', 'Ara Adlar Yol');

                    $exists = DB::table('tbUrunler')->where('No', $no)->exists();
                    if ($exists && count($vals) > 0) { DB::table('tbUrunler')->where('No', $no)->update($vals); $updated++; }
                    elseif (!$exists && count($vals) > 0) { $vals['No'] = $no; DB::table('tbUrunler')->insert($vals); $inserted++; }
                }
            }
            return response()->json(['success' => true, 'updated' => $updated, 'inserted' => $inserted, 'message' => "{$updated} güncellendi, {$inserted} eklendi."]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()]); }
    }

    // ================================================================
    //   CLEAR ALL ORDERS
    // ================================================================
    private function clearAllOrders(Request $request)
    {
        try {
            $count = DB::table('tbSiparisSatir')->count();
            DB::table('tbSiparisSatir')->delete();
            return response()->json(['success' => true, 'message' => "{$count} sipariş kaydı başarıyla silindi."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   DEDUCT STOCK — Tekli stok düşme
    // ================================================================
    private function deductStock(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNo = intval($data['no'] ?? 0);
            $result = $this->deductStockForOrder($satirNo);
            if ($result === 'OK') {
                return response()->json(['success' => true, 'message' => 'Stoktan düşüldü.']);
            }
            return response()->json(['success' => false, 'message' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   DEDUCT STOCK BULK — Toplu stok düşme
    // ================================================================
    private function deductStockBulk(Request $request)
    {
        try {
            $data = $request->json()->all();
            $nos = $data['nos'] ?? [];
            $basarili = 0; $hatalar = [];

            foreach ($nos as $satirNo) {
                $result = $this->deductStockForOrder(intval($satirNo));
                if ($result === 'OK') $basarili++;
                else $hatalar[] = "Satır {$satirNo}: {$result}";
            }

            return response()->json([
                'success' => true, 'basarili' => $basarili, 'hatalar' => $hatalar,
                'message' => "{$basarili} sipariş stoktan karşılandı." . (count($hatalar) > 0 ? ' (' . count($hatalar) . ' hata)' : '')
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   RESET THRESHOLDS
    // ================================================================
    private function resetThresholds(Request $request)
    {
        try {
            DB::transaction(function () {
                DB::table('tbKritikStokUyari')->delete();
                DB::table('tbKritikStokEsik')->delete();
            });
            return response()->json(['success' => true, 'message' => 'Tüm eşik tanımları ve uyarı logları başarıyla sıfırlandı.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Sıfırlama hatası: ' . $e->getMessage()]);
        }
    }

    // ================================================================
    //   REMATCH ORDERS — Siparişleri yeniden eşleştir
    // ================================================================
    private function rematchOrders(Request $request)
    {
        try {
            $dbProducts = DB::table('tbUrunler')->select('No', 'SistemAdi', 'UrunID', 'SistemKodu')->get();

            // Önbellek yükle
            $matchCache = DB::table('tbUrunEslestirmeOnbellek')->select('ExcelUrunAdi', 'EslesenUrunNo', 'EslesenUrunTur')->get()
                ->keyBy(fn($r) => strtolower(trim($r->ExcelUrunAdi)));

            // Eşleşmemiş satırlar
            $unmatched = DB::table('tbSiparisSatir')
                ->select('No', 'UrunAdi', 'StokKodu')
                ->where(function ($q) { $q->where('EslesenUrunNo', 0)->orWhereNull('EslesenUrunNo'); })
                ->where('Durum', '!=', 'Pasif')->where('Aktif', 1)
                ->get();

            $totalMatched = 0;
            foreach ($unmatched as $row) {
                $urunAdi = $row->UrunAdi ?? '';
                $stokKodu = trim($row->StokKodu ?? '');
                $eslesenUrunNo = 0; $eslesenUrunTur = ''; $yontem = '';

                // 1) Stok kodu ile
                if (!empty($stokKodu)) {
                    $match = $dbProducts->first(fn($p) => (trim($p->SistemKodu ?? '') !== '' && strcasecmp(trim($p->SistemKodu), $stokKodu) === 0)
                        || (trim($p->UrunID ?? '') !== '' && strcasecmp(trim($p->UrunID), $stokKodu) === 0));
                    if ($match) { $eslesenUrunNo = $match->No; $eslesenUrunTur = 'Nihai'; $yontem = 'StokKodu'; }
                }

                // 2) Önbellek
                if ($eslesenUrunNo === 0) {
                    $key = strtolower(trim($urunAdi));
                    if (isset($matchCache[$key])) {
                        $cached = $matchCache[$key];
                        $eslesenUrunNo = intval($cached->EslesenUrunNo); $eslesenUrunTur = $cached->EslesenUrunTur; $yontem = 'Onbellek';
                    }
                }

                // 3) Tam isim (Exact Match)
                if ($eslesenUrunNo === 0) {
                    $exactMatch = $this->findExactMatch($urunAdi, $dbProducts);
                    if ($exactMatch) {
                        $eslesenUrunNo = $exactMatch['no']; $eslesenUrunTur = 'Nihai'; $yontem = 'TamIsim';
                    }
                }

                // 4) Bulanık eşleşme (Fuzzy Match — Original: FindBestMatch)
                if ($eslesenUrunNo === 0) {
                    $bestMatch = $this->findBestMatch($urunAdi, $dbProducts);
                    if ($bestMatch) {
                        $eslesenUrunNo = $bestMatch['no']; $eslesenUrunTur = 'Nihai'; $yontem = 'Otomatik';
                    }
                }

                if ($eslesenUrunNo > 0) {
                    $score = ($yontem === 'StokKodu' || $yontem === 'TamIsim' || $yontem === 'Onbellek') ? 100 : ($bestMatch['score'] ?? 0);
                    DB::table('tbSiparisSatir')->where('No', $row->No)->update([
                        'EslesenUrunNo' => $eslesenUrunNo, 'EslesenUrunTur' => $eslesenUrunTur,
                        'EslesmePuani' => $score, 'EslesmeYontemi' => $yontem
                    ]);
                    $totalMatched++;
                }
            }

            return response()->json(['success' => true, 'total' => $unmatched->count(), 'matched' => $totalMatched, 'message' => "{$totalMatched} sipariş eşleştirildi."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   GET WORK ORDER HISTORY
    // ================================================================
    private function getWorkOrderHistory(Request $request)
    {
        try {
            $tarih = $request->input('tarih', '');
            $baslangic = $request->input('baslangic', '');
            $bitis = $request->input('bitis', '');
            $arama = $request->input('arama', '');
            $islemTipi = $request->input('islemTipi', '');

            $query = DB::table('tbIsEmriGecmisi as g')
                ->select('g.No', 'g.SiparisSatirNo', 'g.SiparisNo', 'g.Musteri', 'g.UrunAdi', 'g.SistemUrunAdi',
                    'g.Adet', 'g.Kategori', 'g.IsEmriTarihi', 'g.IslemTipi', 'g.IslemTarihi', 'g.GorevNo',
                    'g.EslesenUrunNo', 'g.EslesenUrunTur', 'g.KargoSonTeslim');

            if ($tarih === 'bugun') $query->whereDate('g.IslemTarihi', today());
            elseif ($tarih === 'hafta') $query->where('g.IslemTarihi', '>=', now()->subDays(7));
            elseif ($tarih === 'ay') $query->where('g.IslemTarihi', '>=', now()->subMonth());
            elseif ($tarih === '3ay') $query->where('g.IslemTarihi', '>=', now()->subMonths(3));
            elseif ($tarih === 'ozel' && !empty($baslangic) && !empty($bitis)) {
                $query->whereDate('g.IslemTarihi', '>=', $baslangic)->whereDate('g.IslemTarihi', '<=', $bitis);
            }

            if (!empty($islemTipi) && $islemTipi !== 'Hepsi') $query->where('g.IslemTipi', $islemTipi);

            if (!empty($arama)) {
                $query->where(function ($q) use ($arama) {
                    $q->where('g.UrunAdi', 'like', "%{$arama}%")
                      ->orWhere('g.SistemUrunAdi', 'like', "%{$arama}%")
                      ->orWhere('g.Musteri', 'like', "%{$arama}%")
                      ->orWhere('g.SiparisNo', 'like', "%{$arama}%");
                });
            }

            $items = $query->orderByDesc('g.IslemTarihi')->get()->map(function ($row) {
                if ($row->IsEmriTarihi) { try { $row->IsEmriTarihi = Carbon::parse($row->IsEmriTarihi)->format('d.m.Y H:i'); } catch (\Exception $e) {} }
                if ($row->IslemTarihi) { try { $row->IslemTarihi = Carbon::parse($row->IslemTarihi)->format('d.m.Y H:i'); } catch (\Exception $e) {} }
                if ($row->KargoSonTeslim) { try { $row->KargoSonTeslim = Carbon::parse($row->KargoSonTeslim)->format('d.m.Y H:i'); } catch (\Exception $e) {} }
                return $row;
            });

            return response()->json(['success' => true, 'items' => $items, 'count' => $items->count()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   GET AVAILABLE SPECIAL PRODUCTIONS
    // ================================================================
    private function getAvailableSpecialProductions(Request $request)
    {
        try {
            $data = $request->json()->all();
            $eslesenUrunNo = intval($data['eslesenUrunNo'] ?? 0);
            $eslesenUrunTur = $data['eslesenUrunTur'] ?? '';
            $istenenAdet = intval($data['adet'] ?? 0);

            $results = DB::select("
                SELECT sp.No, sp.SiparisNo, sp.UrunAdi, sp.Adet AS ToplamAdet,
                    IFNULL((SELECT SUM(Adet) FROM tbSiparisSatir WHERE BagliOlduguOzelUretimNo = sp.No AND Aktif=1 AND Durum != 'Pasif'), 0) AS RezerveAdet,
                    sp.Durum, sp.IsEmriTarihi, sp.GorevNo
                FROM tbSiparisSatir sp
                WHERE sp.Aktif = 1 AND sp.Musteri LIKE 'ÖZEL ÜRETİM%'
                    AND sp.EslesenUrunNo = ? AND sp.EslesenUrunTur = ? AND sp.Durum != 'Pasif'
                    AND (sp.Adet - IFNULL((SELECT SUM(Adet) FROM tbSiparisSatir WHERE BagliOlduguOzelUretimNo = sp.No AND Aktif=1 AND Durum != 'Pasif'), 0)) >= ?
                ORDER BY sp.IsEmriTarihi ASC
            ", [$eslesenUrunNo, $eslesenUrunTur, $istenenAdet]);

            $data = collect($results)->map(function ($row) {
                $row->bostaAdet = intval($row->ToplamAdet) - intval($row->RezerveAdet);
                if ($row->IsEmriTarihi) { try { $row->IsEmriTarihi = Carbon::parse($row->IsEmriTarihi)->format('d.m.Y H:i'); } catch (\Exception $e) {} }
                return $row;
            });

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   LINK ORDER TO SPECIAL PRODUCTION
    // ================================================================
    private function linkOrderToSpecialProduction(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisNo = intval($data['siparisNo'] ?? 0);
            $ozelUretimNo = intval($data['ozelUretimNo'] ?? 0);

            DB::transaction(function () use ($siparisNo, $ozelUretimNo) {
                $order = DB::table('tbSiparisSatir')->where('No', $siparisNo)->where('Aktif', 1)->first();
                if (!$order) throw new \Exception('Sipariş bulunamadı veya pasif.');
                if (!empty($order->BagliOlduguOzelUretimNo)) throw new \Exception('Bu sipariş zaten bir üretim rezervasyonuna bağlanmış!');
                if (($order->Durum ?? '') === 'Pasif') throw new \Exception('Pasif bir sipariş rezerve edilemez.');

                $bosta = DB::selectOne("
                    SELECT Adet - IFNULL((SELECT SUM(Adet) FROM tbSiparisSatir WHERE BagliOlduguOzelUretimNo = sp.No AND Aktif=1 AND Durum != 'Pasif'), 0) AS BostakiAdet
                    FROM tbSiparisSatir sp WHERE sp.No = ? AND sp.Aktif = 1
                ", [$ozelUretimNo]);

                if (!$bosta) throw new \Exception('Özel üretim kaydı bulunamadı.');
                if (intval($bosta->BostakiAdet) < intval($order->Adet))
                    throw new \Exception("Özel üretimin boşta kalan kapasitesi yetersiz! (Boşta: {$bosta->BostakiAdet}, İstenen: {$order->Adet})");

                DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                    'BagliOlduguOzelUretimNo' => $ozelUretimNo, 'Durum' => 'UretimdenKarsilaniyor'
                ]);
            });

            return response()->json(['success' => true, 'message' => 'Sipariş özel üretime bağlandı!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   CANCEL WIP ALLOCATION
    // ================================================================
    private function cancelWipAllocation(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisNo = intval($data['siparisNo'] ?? 0);

            $order = DB::table('tbSiparisSatir')->where('No', $siparisNo)->first();
            if (!$order) throw new \Exception('Sipariş bulunamadı.');

            DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                'BagliOlduguOzelUretimNo' => null, 'Durum' => 'UretimBekliyor', 'GuncellemeTarihi' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Rezervasyon iptal edildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    // ===============================================
    // KRİTİK STOK EŞİĞİ YÖNETİMİ
    // ===============================================
    private function getThresholds(Request $request)
    {
        try {
            // Thresholds
            $thresholdsQuery = DB::select("
                SELECT 
                    e.No as no, 
                    e.AraUrunAdiNo as araUrunAdiNo, 
                    e.EsikMiktar as esikMiktar, 
                    e.OtomatikIsEmri as otomatikIsEmri,
                    e.IsEmriAdet as isEmriAdet, 
                    e.UrunIDNo as urunIDNo, 
                    e.Aktif as aktif,
                    e.SonKontrolTarihi as sonKontrolTarihi, 
                    e.SonUyariTarihi as sonUyariTarihi, 
                    e.OlusturmaTarihi as olusturmaTarihi,
                    a.AraUrunAdi as araUrunAdi, 
                    IFNULL(a.UrunCesidi,'') AS urunCesidi,
                    IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = e.AraUrunAdiNo), 0) AS mevcutStok
                FROM tbKritikStokEsik e
                JOIN tbAraUrun a ON a.No = e.AraUrunAdiNo
                ORDER BY a.AraUrunAdi
            ");

            $thresholds = collect($thresholdsQuery)->map(function ($row) {
                // Formatting dates and calculating statuses
                $mevcutStok = intval($row->mevcutStok);
                $esik = intval($row->esikMiktar);
                
                $row->durum = $mevcutStok <= 0 ? 'Kritik' : ($mevcutStok < $esik ? 'Uyari' : 'Normal');
                $row->otomatikIsEmri = (bool)$row->otomatikIsEmri;
                return $row;
            });

            // Available Products (without thresholds)
            $availableQuery = DB::select("
                SELECT 
                    a.No as araUrunAdiNo, 
                    a.AraUrunAdi as araUrunAdi, 
                    IFNULL(a.UrunCesidi,'') AS urunCesidi,
                    IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = a.No), 0) AS mevcutStok
                FROM tbAraUrun a
                WHERE a.No NOT IN (SELECT AraUrunAdiNo FROM tbKritikStokEsik)
                AND a.No <> 1373
                ORDER BY a.AraUrunAdi
            ");

            return response()->json([
                'success' => true,
                'thresholds' => $thresholds,
                'available' => $availableQuery
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function saveThreshold(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        
        $araUrunAdiNo = $payload['araUrunAdiNo'] ?? 0;
        $esikMiktar = $payload['esikMiktar'] ?? 5;
        $otomatikIsEmri = isset($payload['otomatikIsEmri']) && $payload['otomatikIsEmri'] ? 1 : 0;
        $isEmriAdet = $payload['isEmriAdet'] ?? 10;
        $aktif = !isset($payload['aktif']) || $payload['aktif'] ? 1 : 0;

        if ($araUrunAdiNo <= 0) return response()->json(['success' => false, 'message' => 'Ürün seçilmedi.']);
        if ($esikMiktar < 0) return response()->json(['success' => false, 'message' => 'Eşik miktarı 0 dan küçük olamaz.']);

        try {
            DB::statement("
                INSERT INTO tbKritikStokEsik (AraUrunAdiNo, EsikMiktar, OtomatikIsEmri, IsEmriAdet, Aktif)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    EsikMiktar = VALUES(EsikMiktar), 
                    OtomatikIsEmri = VALUES(OtomatikIsEmri), 
                    IsEmriAdet = VALUES(IsEmriAdet), 
                    Aktif = VALUES(Aktif)
            ", [$araUrunAdiNo, $esikMiktar, $otomatikIsEmri, $isEmriAdet, $aktif]);

            return response()->json(['success' => true, 'message' => 'Eşik başarıyla kaydedildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function deleteThreshold(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $no = $payload['no'] ?? 0;
        
        if ($no <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz kayıt.']);
        
        try {
            DB::table('tbKritikStokEsik')->where('No', $no)->delete();
            return response()->json(['success' => true, 'message' => 'Eşik tanımı silindi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function getCriticalStockAlerts(Request $request)
    {
        try {
            // Alerts for active thresholds where current stock is lower than threshold
            $alertsQuery = DB::select("
                SELECT 
                    e.No as no, 
                    e.AraUrunAdiNo as araUrunAdiNo, 
                    e.EsikMiktar as esikMiktar, 
                    e.OtomatikIsEmri as otomatikIsEmri, 
                    e.IsEmriAdet as isEmriAdet,
                    a.AraUrunAdi as araUrunAdi, 
                    IFNULL(a.UrunCesidi,'') AS urunCesidi,
                    IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = e.AraUrunAdiNo), 0) AS mevcutStok
                FROM tbKritikStokEsik e
                JOIN tbAraUrun a ON a.No = e.AraUrunAdiNo
                WHERE e.Aktif = 1
                AND IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = e.AraUrunAdiNo), 0) < e.EsikMiktar
                ORDER BY 
                    CASE WHEN IFNULL((SELECT SUM(s2.Adet) FROM tbBolumAraStok s2 WHERE s2.AraUrunAdiNo = e.AraUrunAdiNo), 0) <= 0 THEN 0 ELSE 1 END,
                    a.AraUrunAdi
            ");

            $alerts = collect($alertsQuery)->map(function ($row) {
                $mevcutStok = intval($row->mevcutStok);
                $row->durum = $mevcutStok <= 0 ? 'Kritik' : 'Uyari';
                $row->otomatikIsEmri = (bool)$row->otomatikIsEmri;
                return $row;
            });

            // Recent Logs
            $logsQuery = DB::select("
                SELECT 
                    u.No as no, 
                    u.AraUrunAdiNo as araUrunAdiNo, 
                    u.EsikMiktar as esikMiktar, 
                    u.MevcutStok as mevcutStok,
                    u.UyariTipi as uyariTipi, 
                    u.OtomatikIsEmriVerildi as otomatikIsEmriVerildi, 
                    u.Okundu as okundu, 
                    u.OlusturmaTarihi as tarih,
                    a.AraUrunAdi as araUrunAdi
                FROM tbKritikStokUyari u
                JOIN tbAraUrun a ON a.No = u.AraUrunAdiNo
                ORDER BY u.OlusturmaTarihi DESC
                LIMIT 20
            ");

            $logs = collect($logsQuery)->map(function($row) {
                $row->otomatikIsEmriVerildi = (bool)$row->otomatikIsEmriVerildi;
                $row->okundu = (bool)$row->okundu;
                if ($row->tarih) {
                    $row->tarih = \Carbon\Carbon::parse($row->tarih)->format('d.m.Y H:i');
                }
                return $row;
            });

            $okunmamisCount = DB::table('tbKritikStokUyari')->where('Okundu', 0)->count();

            return response()->json([
                'success' => true,
                'alerts' => $alerts,
                'alertCount' => count($alerts),
                'logs' => $logs,
                'okunmamisCount' => $okunmamisCount
            ]);
        } catch (\Exception $e) {
             return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   CREATE ORDER WORK ORDERS
    // ================================================================
    private function createOrderWorkOrders(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload || !isset($payload['satirNolar'])) {
            return response()->json(['success' => false, 'message' => 'Satir numarasi bulunamadi.']);
        }
        $satirlar = $payload['satirNolar'];
        $surplus = $payload['surplus'] ?? 0;
        
        $result = $this->orderToWorkOrder->createOrderWorkOrders($satirlar, $surplus);
        return response()->json($result);
    }

    private function createManualWorkOrder(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        if (!$payload) {
            return response()->json(['success' => false, 'message' => 'Gecersiz is emri verisi.']);
        }

        $urunNo = intval($payload['urunNo'] ?? 0);
        $adet = intval($payload['adet'] ?? 0);
        $stokDurum = $payload['stokDurum'] ?? 'StokDahil';
        $aciklama = trim((string) ($payload['aciklama'] ?? ''));
        $tur = $payload['tur'] ?? 'Nihai';

        if ($urunNo <= 0 || $adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Urun ve adet bilgisi zorunlu.']);
        }

        $result = $this->workOrderService->createLegacyManualWorkOrder(
            $urunNo,
            (string) $tur,
            $adet,
            $stokDurum,
            $aciklama
        );

        if (!($result['success'] ?? false)) {
            return response()->json($result);
        }

        $gorevNo = intval($result['gorevNo'] ?? 0);
        $message = $gorevNo > 0
            ? "Is emri olusturuldu. Gorev No: {$gorevNo}"
            : 'Is emri olusturuldu.';

        return response()->json([
            'success' => true,
            'gorevNo' => $gorevNo,
            'tamponDusumleri' => $result['tamponDusumleri'] ?? [],
            'message' => $message,
        ]);
    }

    private function onlyUpdateStatusBulk(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $satirNolar = collect($payload['satirNolar'] ?? [])->map(fn ($no) => intval($no))->filter()->values();
        if ($satirNolar->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Satir numarasi bulunamadi.']);
        }

        $yeniDurum = $payload['yeniDurum'] ?? 'StokKarsilandi';
        $yeniAktif = isset($payload['yeniAktif']) ? intval($payload['yeniAktif']) : 1;

        DB::table('tbSiparisSatir')
            ->whereIn('No', $satirNolar->all())
            ->update([
                'Durum' => $yeniDurum,
                'Aktif' => $yeniAktif,
                'GuncellemeTarihi' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Seçilen ' . $satirNolar->count() . ' siparişin durumu ' . $yeniDurum . ' olarak güncellendi.',
        ]);
    }

    private function fixKayipOzelUretim(Request $request)
    {
        $etkilenen = DB::table('tbSiparisSatir')
            ->where('Musteri', 'like', 'ÖZEL ÜRETİM%')
            ->where('Durum', 'Pasif')
            ->where('Aktif', 0)
            ->update([
                'Durum' => 'UretimBekliyor',
                'Aktif' => 1,
                'GuncellemeTarihi' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Kayıp ' . $etkilenen . ' adet özel üretim kaydı başarıyla kurtarıldı.',
        ]);
    }

    private function getPasifDevamEden(Request $request)
    {
        try {
            $autoCompleted = 0;
            $kontrolListesi = DB::table('tbSiparisSatir')
                ->where('Durum', 'PasifDevamEden')
                ->select(
                    'No',
                    DB::raw('IFNULL(Adet, 0) as Adet'),
                    DB::raw('IFNULL(EslesenUrunNo, 0) as EslesenUrunNo'),
                    DB::raw("IFNULL(EslesenUrunTur, '') as EslesenUrunTur"),
                    DB::raw('IFNULL(GorevNo, 0) as GorevNo')
                )
                ->get();

            foreach ($kontrolListesi as $kayit) {
                $aktifGorev = 0;

                if ($kayit->EslesenUrunTur === 'Nihai' && intval($kayit->EslesenUrunNo) > 0) {
                    $aktifGorev =
                        DB::table('tbBolumHavuz')->where('UrunIDNo', $kayit->EslesenUrunNo)->count() +
                        DB::table('tbPersonelGorev')->where('UrunIDNo', $kayit->EslesenUrunNo)->count();
                } elseif (intval($kayit->GorevNo) > 0) {
                    $aktifGorev = DB::table('tbBolumHavuz')->where('No', $kayit->GorevNo)->count();
                }

                if ($aktifGorev !== 0) {
                    continue;
                }

                DB::transaction(function () use ($kayit, &$autoCompleted) {
                    DB::table('tbSiparisSatir')
                        ->where('No', $kayit->No)
                        ->update([
                            'Durum' => 'Pasif',
                            'GuncellemeTarihi' => now(),
                        ]);

                    $araUrunNo = $this->resolveMatchedAraUrunNo(intval($kayit->EslesenUrunNo), (string) $kayit->EslesenUrunTur);
                    if ($araUrunNo > 0) {
                        DB::table('tbBolumAraStok')
                            ->where('AraUrunAdiNo', $araUrunNo)
                            ->update([
                                'Adet' => DB::raw('CASE WHEN Adet >= ' . intval($kayit->Adet) . ' THEN Adet - ' . intval($kayit->Adet) . ' ELSE 0 END'),
                            ]);
                    }

                    $autoCompleted++;
                });
            }

            $items = DB::table('tbSiparisSatir')
                ->where('Durum', 'PasifDevamEden')
                ->orderByDesc('GuncellemeTarihi')
                ->get([
                    'No',
                    'SiparisNo',
                    'Pazaryeri',
                    'Magaza',
                    'SiparisTarihi',
                    'Musteri',
                    'UrunAdi',
                    'Adet',
                    'MusteriNotu',
                    'KargoSonTeslim',
                    'Kategori',
                    'Durum',
                    'Aktif',
                    'EslesenUrunNo',
                    'EslesenUrunTur',
                    'IsEmriTarihi',
                    'GorevNo',
                    'GuncellemeTarihi',
                    'YuklemeTarihi',
                ])
                ->map(function ($row) {
                    $eslesenUrunNo = intval($row->EslesenUrunNo ?? 0);
                    $eslesenUrunTur = (string) ($row->EslesenUrunTur ?? '');
                    $gorevNo = intval($row->GorevNo ?? 0);

                    [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler] = $this->buildPasifDevamEdenProgress($eslesenUrunNo, $eslesenUrunTur, $gorevNo);
                    $yuzde = ($bitenGorevSayisi > 0 || $aktifGorevSayisi > 0)
                        ? (int) round(($bitenGorevSayisi * 100) / ($bitenGorevSayisi + $aktifGorevSayisi))
                        : 0;

                    return [
                        'no' => intval($row->No),
                        'siparisNo' => (string) ($row->SiparisNo ?? ''),
                        'pazaryeri' => (string) ($row->Pazaryeri ?? ''),
                        'magaza' => (string) ($row->Magaza ?? ''),
                        'siparisTarihi' => $this->formatLegacyDateTime($row->SiparisTarihi),
                        'musteri' => (string) ($row->Musteri ?? ''),
                        'urunAdi' => (string) ($row->UrunAdi ?? ''),
                        'adet' => intval($row->Adet ?? 0),
                        'musteriNotu' => (string) ($row->MusteriNotu ?? ''),
                        'kargoSonTeslim' => $this->formatLegacyDateTime($row->KargoSonTeslim),
                        'kategori' => (string) ($row->Kategori ?? ''),
                        'durum' => (string) ($row->Durum ?? ''),
                        'eslesenUrunNo' => $eslesenUrunNo,
                        'eslesenUrunTur' => $eslesenUrunTur,
                        'isEmriTarihi' => $this->formatLegacyDateTime($row->IsEmriTarihi),
                        'gorevNo' => $gorevNo,
                        'pasifTarihi' => $this->formatLegacyDateTime($row->GuncellemeTarihi),
                        'sistemUrunAdi' => $this->resolveSystemProductName($eslesenUrunNo, $eslesenUrunTur),
                        'aktifGorevSayisi' => $aktifGorevSayisi,
                        'bitenGorevSayisi' => $bitenGorevSayisi,
                        'yuzde' => $yuzde,
                        'gecenSure' => $this->humanElapsedSince($row->IsEmriTarihi),
                        'aktifBolumler' => $aktifBolumler,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'count' => $items->count(),
                'items' => $items,
                'autoCompleted' => $autoCompleted,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
        }
    }

    private function reactivateOrder(Request $request)
    {
        try {
            $payload = json_decode($request->getContent(), true);
            $satirNo = intval($payload['satirNo'] ?? 0);
            if ($satirNo <= 0) {
                return response()->json(['success' => false, 'message' => 'Geçersiz satır numarası.']);
            }

            $mevcut = DB::table('tbSiparisSatir')->where('No', $satirNo)->value('Durum');
            if ($mevcut !== 'PasifDevamEden') {
                return response()->json(['success' => false, 'message' => 'Bu sipariş PasifDevamEden durumunda değil.']);
            }

            DB::table('tbSiparisSatir')
                ->where('No', $satirNo)
                ->update([
                    'Durum' => 'IsEmriVerildi',
                    'Aktif' => 1,
                    'GuncellemeTarihi' => now(),
                ]);

            return response()->json(['success' => true, 'message' => 'Sipariş tekrar aktif edildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
        }
    }

    // ================================================================
    //   YARDIMCI FONKSİYONLAR (Helper Methods)
    // ================================================================

    /**
     * Tampon stoğu geri yükle (TamponDusumleri JSON'dan)
     */
    private function restoreTamponFromJson(?string $tamponDusumleriJson): void
    {
        if (empty($tamponDusumleriJson)) return;
        try {
            $dusumleri = json_decode($tamponDusumleriJson, true);
            if (!is_array($dusumleri)) return;
            foreach ($dusumleri as $dusum) {
                $araNo = intval($dusum['araNo'] ?? 0);
                $adet = intval($dusum['adet'] ?? 0);
                if ($araNo > 0 && $adet > 0) {
                    DB::statement("
                        UPDATE tbBolumAraStok 
                        SET TamponMiktar = CASE WHEN TamponMiktar + ? > Adet THEN Adet ELSE TamponMiktar + ? END 
                        WHERE AraUrunAdiNo = ?
                    ", [$adet, $adet, $araNo]);
                }
            }
        } catch (\Exception $e) { /* Log hatası ana işlemi engellemesin */ }
    }

    /**
     * İş emri geçmişi log kaydı
     */
    private function logIsEmriGecmisi(int $satirNo, int $gorevNo, int $eslesenUrunNo, string $eslesenUrunTur, string $islemTipi): void
    {
        try {
            $sistemUrunAdi = null;
            if ($eslesenUrunNo > 0) {
                $urun = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->first();
                $sistemUrunAdi = $urun ? ($urun->SistemAdi ?: $urun->UrunID) : null;
            }

            $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
            if ($satir) {
                DB::table('tbIsEmriGecmisi')->insert([
                    'SiparisSatirNo' => $satirNo,
                    'SiparisNo' => $satir->SiparisNo ?? null,
                    'Musteri' => $satir->Musteri ?? null,
                    'UrunAdi' => $satir->UrunAdi ?? null,
                    'SistemUrunAdi' => $sistemUrunAdi,
                    'Adet' => $satir->Adet ?? 0,
                    'Kategori' => $satir->Kategori ?? null,
                    'IsEmriTarihi' => $satir->IsEmriTarihi ?? now(),
                    'IslemTipi' => $islemTipi,
                    'IslemTarihi' => now(),
                    'GorevNo' => $gorevNo > 0 ? $gorevNo : null,
                    'EslesenUrunNo' => $eslesenUrunNo > 0 ? $eslesenUrunNo : null,
                    'EslesenUrunTur' => !empty($eslesenUrunTur) ? $eslesenUrunTur : null,
                    'KargoSonTeslim' => $satir->KargoSonTeslim ?? null,
                ]);
            }
        } catch (\Exception $e) { /* Log hatası ana işlemi engellemesin */ }
    }

    /**
     * Stoktan düşme ortak iç metot
     */
    private function deductStockForOrder(int $satirNo): string
    {
        try {
            $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
            if (!$satir) return 'Sipariş bulunamadı.';

            $adet = intval($satir->Adet ?? 0);
            $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
            $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
            $durum = $satir->Durum ?? '';

            if ($durum !== 'UretimBekliyor' && $durum !== 'UretimdenKarsilaniyor') {
                return 'Sadece \'Üretim Bekliyor\' veya \'Üretime Bağlandı\' (GİED) durumundaki siparişler stoktan düşülebilir.';
            }
            if ($eslesenUrunNo <= 0) return 'Eşleşen ürün bulunamadı.';

            // AraUrunAdiNo'yu bul
            $araUrunNo = 0;
            if ($eslesenUrunTur === 'Nihai') {
                $urunID = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->value('UrunID');
                if (empty($urunID)) return 'Ürün adı bulunamadı.';
                $araUrunNo = DB::table('tbAraUrun')->where('AraUrunAdi', trim($urunID))->value('No') ?? 0;
            } elseif ($eslesenUrunTur === 'Ara') {
                $araUrunNo = $eslesenUrunNo;
            }

            if ($araUrunNo <= 0) return 'Ara ürün bulunamadı.';

            // Stok kontrol
            $mevcutStok = intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->sum('Adet'));
            if ($mevcutStok < $adet) return "Yetersiz stok. Mevcut: {$mevcutStok}, İstenen: {$adet}";

            // Stoktan düş
            $remaining = $adet;
            $stokRows = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araUrunNo)->where('Adet', '>', 0)->get();
            foreach ($stokRows as $stokRow) {
                if ($remaining <= 0) break;
                $dusulecek = min($remaining, intval($stokRow->Adet));
                DB::table('tbBolumAraStok')->where('No', $stokRow->No)->update([
                    'Adet' => DB::raw("Adet - {$dusulecek}"),
                    'TamponMiktar' => DB::raw("CASE WHEN TamponMiktar - {$dusulecek} < 0 THEN 0 ELSE TamponMiktar - {$dusulecek} END")
                ]);
                $remaining -= $dusulecek;
            }

            // Durumu güncelle
            DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                'Durum' => 'StokKarsilandi', 'GuncellemeTarihi' => now()
            ]);

            // Stok düşüşü sonrası eşik kontrolü (Original: CheckStockThreshold)
            $this->checkStockThreshold($araUrunNo);

            return 'OK';
        } catch (\Exception $e) {
            return 'Hata: ' . $e->getMessage();
        }
    }

    // ================================================================
    //   MATCH PRODUCT — Manuel ürün eşleştirme (tek satır)
    //   Original: SiparisApi.ashx Line 1173-1258
    // ================================================================
    private function matchProduct(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
            $eslesenUrunNo = intval($data['eslesenUrunNo'] ?? 0);
            $eslesenUrunTur = $data['eslesenUrunTur'] ?? 'Nihai';

            if ($siparisSatirNo <= 0 || $eslesenUrunNo <= 0) {
                throw new \Exception('Geçersiz parametreler.');
            }

            // 1) Sipariş satırını güncelle
            DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->update([
                'EslesenUrunNo' => $eslesenUrunNo,
                'EslesenUrunTur' => $eslesenUrunTur,
                'EslesmePuani' => 100,
                'EslesmeYontemi' => 'Manuel',
                'GuncellemeTarihi' => now()
            ]);

            // 2) Ürün adını al
            $urunAdi = DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->value('UrunAdi') ?? '';

            // 3) Eşleşen ürün adını al
            $cachedProductName = '';
            $urun = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->first();
            if ($urun) {
                $cachedProductName = !empty($urun->SistemAdi) ? $urun->SistemAdi : ($urun->UrunID ?? '');
            }

            $updatedCount = 0;
            if (!empty($urunAdi)) {
                // 4) Önbelleğe UPSERT
                DB::statement("
                    INSERT INTO tbUrunEslestirmeOnbellek (ExcelUrunAdi, EslesenUrunNo, EslesenUrunTur)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        EslesenUrunNo = VALUES(EslesenUrunNo), 
                        EslesenUrunTur = VALUES(EslesenUrunTur), 
                        OlusturmaTarihi = CURRENT_TIMESTAMP
                ", [$urunAdi, $eslesenUrunNo, $eslesenUrunTur]);

                // 5) Aynı ürün adına sahip diğer satırları da güncelle (bulk)
                $updatedCount = DB::table('tbSiparisSatir')
                    ->where('UrunAdi', $urunAdi)
                    ->where('EslesmeYontemi', '!=', 'Manuel')
                    ->where('No', '!=', $siparisSatirNo)
                    ->update([
                        'EslesenUrunNo' => $eslesenUrunNo,
                        'EslesenUrunTur' => $eslesenUrunTur,
                        'EslesmePuani' => 100,
                        'EslesmeYontemi' => 'Onbellek',
                        'GuncellemeTarihi' => now()
                    ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Eşleştirme kaydedildi.',
                'updatedCount' => $updatedCount,
                'cachedExcelName' => $urunAdi,
                'cachedProductName' => $cachedProductName
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ================================================================
    //   TEXT NORMALIZATION & FUZZY MATCHING ENGINE
    //   Original: SiparisApi.ashx Line 3279-3372
    // ================================================================
    private function normalizeText(string $text): string
    {
        if (empty($text)) return '';
        $text = mb_strtolower($text);
        $text = str_replace([',', '.', '-', '_', '(', ')', '/', '\\'], ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function calculateMatchScore(string $excelName, string $dbName): int
    {
        if (empty($excelName) || empty($dbName)) return 0;

        // Tam eşleşme
        if ($excelName === $dbName) return 100;

        // Contains kontrolü
        if (str_contains($excelName, $dbName) || str_contains($dbName, $excelName)) return 85;

        // Kelime bazlı
        $excelWords = array_filter(explode(' ', $excelName), fn($w) => mb_strlen($w) > 2);
        $dbWords = array_filter(explode(' ', $dbName), fn($w) => mb_strlen($w) > 2);

        if (empty($excelWords) || empty($dbWords)) return 0;

        $matchCount = 0;
        foreach ($dbWords as $dw) {
            foreach ($excelWords as $ew) {
                if (str_contains($ew, $dw) || str_contains($dw, $ew)) {
                    $matchCount++;
                    break;
                }
            }
        }

        return (int)round($matchCount / count($dbWords) * 100);
    }

    private function findBestMatch(string $excelProductName, $dbProducts): ?array
    {
        if (empty($excelProductName) || $dbProducts->isEmpty()) return null;

        $normalized = $this->normalizeText($excelProductName);
        $bestMatch = null;
        $bestScore = 0;

        foreach ($dbProducts as $dbProd) {
            $dbName = $this->normalizeText($dbProd->UrunID ?? '');
            if (empty($dbName)) continue;

            $score = $this->calculateMatchScore($normalized, $dbName);
            if ($score > $bestScore && $score >= 40) {
                $bestScore = $score;
                $bestMatch = [
                    'no' => $dbProd->No,
                    'name' => $dbProd->UrunID,
                    'sistemAdi' => $dbProd->SistemAdi ?? '',
                    'score' => $score
                ];
            }

            // SistemAdi ile de kontrol et
            $sysName = $this->normalizeText($dbProd->SistemAdi ?? '');
            if (!empty($sysName)) {
                $sysScore = $this->calculateMatchScore($normalized, $sysName);
                if ($sysScore > $bestScore && $sysScore >= 40) {
                    $bestScore = $sysScore;
                    $bestMatch = [
                        'no' => $dbProd->No,
                        'name' => $dbProd->UrunID,
                        'sistemAdi' => $dbProd->SistemAdi ?? '',
                        'score' => $sysScore
                    ];
                }
            }
        }

        return $bestMatch;
    }

    private function findExactMatch(string $excelProductName, $dbProducts): ?array
    {
        if (empty($excelProductName) || $dbProducts->isEmpty()) return null;

        $normalized = $this->normalizeText($excelProductName);

        foreach ($dbProducts as $dbProd) {
            $dbName = $this->normalizeText($dbProd->UrunID ?? '');
            if (!empty($dbName) && $normalized === $dbName) {
                return ['no' => $dbProd->No, 'name' => $dbProd->UrunID, 'sistemAdi' => $dbProd->SistemAdi ?? '', 'score' => 100];
            }
            $sysName = $this->normalizeText($dbProd->SistemAdi ?? '');
            if (!empty($sysName) && $normalized === $sysName) {
                return ['no' => $dbProd->No, 'name' => $dbProd->UrunID, 'sistemAdi' => $dbProd->SistemAdi ?? '', 'score' => 100];
            }
        }

        return null;
    }

    // ================================================================
    //   CHECK STOCK THRESHOLD — Stok düşüşünde otomatik kontrol
    //   Original: SiparisApi.ashx Line 4198-4348
    //   DeductStock sonrası çağrılır
    // ================================================================
    private function checkStockThreshold(int $araUrunAdiNo): void
    {
        try {
            // Bu ürün için eşik tanımlı mı?
            $esik = DB::selectOne("
                SELECT e.No, e.EsikMiktar, e.OtomatikIsEmri, e.IsEmriAdet,
                    IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = ?), 0) AS MevcutStok
                FROM tbKritikStokEsik e
                WHERE e.AraUrunAdiNo = ? AND e.Aktif = 1
            ", [$araUrunAdiNo, $araUrunAdiNo]);

            if (!$esik) return; // Eşik tanımlı değil

            $mevcutStok = intval($esik->MevcutStok);
            if ($mevcutStok >= intval($esik->EsikMiktar)) return; // Eşiğin üzerinde

            // Son 24 saat içinde uyarı verilmiş mi? (cooldown)
            $recentAlert = DB::table('tbKritikStokUyari')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('OlusturmaTarihi', '>', now()->subHours(24))
                ->exists();

            if ($recentAlert) return;

            // Otomatik iş emri (Faz 3)
            $isEmriVerildi = false;
            if ($esik->OtomatikIsEmri && intval($esik->IsEmriAdet) > 0) {
                try {
                    $isEmriAdet = intval($esik->IsEmriAdet);
                    $araUrunAdi = DB::table('tbAraUrun')->where('No', $araUrunAdiNo)->value('AraUrunAdi') ?? '';

                    // Nihai ürünü bul
                    $urunIDNo = DB::table('tbKritikStokEsik')->where('No', $esik->No)->value('UrunIDNo') ?? 0;
                    if ($urunIDNo <= 0) {
                        $urunIDNo = DB::table('tbUrunler')->where('UrunID', $araUrunAdi)->value('No') ?? 0;
                    }
                    if ($urunIDNo <= 0) $urunIDNo = 502;

                    // Zaten bekleyen iş emri var mı?
                    $isEmriMevcut = DB::table('tbBolumHavuz')
                        ->where('AraUrunAdiNo', $araUrunAdiNo)
                        ->where(function($q) { $q->where('Bitti', 0)->orWhereNull('Bitti'); })
                        ->exists();

                    if (!$isEmriMevcut) {
                        $yol = DB::table('tbAraUrun')->where('No', $araUrunAdiNo)->value('Yol') ?? '';
                        $aciklama = 'Kritik stok - Otomatik iş emri (' . now()->format('d.m.Y H:i') . ')';

                        // BomService ile iş emri ver
                        $bomService = app(BomService::class);
                        $bomService->minAraUrunUretimiDenetle($urunIDNo, $yol, (string)$araUrunAdiNo, $isEmriAdet, $aciklama, 'StokHaric');
                        $bomService->isEmriVerRecursive((string)$araUrunAdiNo, $isEmriAdet, $aciklama, $yol, $urunIDNo, 'StokHaric');

                        $isEmriVerildi = true;

                        // UrunIDNo'yu kaydet
                        if ($urunIDNo > 0 && $urunIDNo != 502) {
                            DB::table('tbKritikStokEsik')
                                ->where('No', $esik->No)
                                ->whereNull('UrunIDNo')
                                ->orWhere('UrunIDNo', 0)
                                ->update(['UrunIDNo' => $urunIDNo]);
                        }
                    }
                } catch (\Exception $e) {
                    $isEmriVerildi = false; // Otomatik iş emri hatası uyarıyı engellemesin
                }
            }

            // Uyarı logu oluştur
            $uyariTipi = $mevcutStok <= 0 ? 'Kritik' : 'Uyari';
            DB::table('tbKritikStokUyari')->insert([
                'AraUrunAdiNo' => $araUrunAdiNo,
                'EsikMiktar' => $esik->EsikMiktar,
                'MevcutStok' => $mevcutStok,
                'UyariTipi' => $uyariTipi,
                'OtomatikIsEmriVerildi' => $isEmriVerildi ? 1 : 0,
                'OlusturmaTarihi' => now()
            ]);

            // SonUyariTarihi güncelle
            DB::table('tbKritikStokEsik')->where('No', $esik->No)->update([
                'SonUyariTarihi' => now(),
                'SonKontrolTarihi' => now()
            ]);
        } catch (\Exception $e) {
            // Eşik kontrolü hata verirse ana işlemi engelleme
        }
    }

    /**
     * Excel import için esnek alan okuyucu
     */
    private function findVal(array $row, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key])) return (string)$row[$key];
            // Case-insensitive search
            foreach ($row as $k => $v) {
                if (strcasecmp($k, $key) === 0) return (string)$v;
            }
        }
        return '';
    }

    private function hasVal(array $row, string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) return true;
            foreach ($row as $k => $v) {
                if (strcasecmp($k, $key) === 0) return true;
            }
        }
        return false;
    }

    private function resolveMatchedAraUrunNo(int $eslesenUrunNo, string $eslesenUrunTur): int
    {
        if ($eslesenUrunNo <= 0) {
            return 0;
        }

        if ($eslesenUrunTur === 'Ara') {
            return $eslesenUrunNo;
        }

        if ($eslesenUrunTur === 'Nihai') {
            $urunID = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->value('UrunID');
            if (empty($urunID)) {
                return 0;
            }

            return intval(DB::table('tbAraUrun')->where('AraUrunAdi', trim((string) $urunID))->value('No') ?? 0);
        }

        return 0;
    }

    private function resolveSystemProductName(int $eslesenUrunNo, string $eslesenUrunTur): string
    {
        if ($eslesenUrunNo <= 0) {
            return '';
        }

        if ($eslesenUrunTur === 'Nihai') {
            $urun = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->first();
            return $urun ? (string) ($urun->SistemAdi ?: $urun->UrunID ?: '') : '';
        }

        if ($eslesenUrunTur === 'Ara') {
            return (string) (DB::table('tbAraUrun')->where('No', $eslesenUrunNo)->value('AraUrunAdi') ?? '');
        }

        return '';
    }

    private function buildPasifDevamEdenProgress(int $eslesenUrunNo, string $eslesenUrunTur, int $gorevNo): array
    {
        $aktifGorevSayisi = 0;
        $bitenGorevSayisi = 0;
        $aktifBolumler = '';

        if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
            $aktifHavuz = DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->count();
            $aktifPersonel = DB::table('tbPersonelGorev')->where('UrunIDNo', $eslesenUrunNo)->count();
            $aktifGorevSayisi = $aktifHavuz + $aktifPersonel;
            $bitenGorevSayisi = DB::table('tbGorevler')->where('UrunIDNo', $eslesenUrunNo)->count();

            if ($aktifGorevSayisi > 0) {
                $bekleyenBolumler = DB::table('tbBolumHavuz as h')
                    ->join('tbBolum as b', 'h.BolumAdiNo', '=', 'b.No')
                    ->where('h.UrunIDNo', $eslesenUrunNo)
                    ->distinct()
                    ->pluck(DB::raw("CONCAT(b.BolumAdi, ' (Bekliyor)')"))
                    ->toArray();

                $uretimdePersonel = DB::table('tbPersonelGorev as pg')
                    ->join('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
                    ->where('pg.UrunIDNo', $eslesenUrunNo)
                    ->distinct()
                    ->pluck(DB::raw("CONCAT(p.Ad, ' (Üretimde)')"))
                    ->toArray();

                $aktifBolumler = implode(', ', array_merge($bekleyenBolumler, $uretimdePersonel));
            }
        } elseif ($gorevNo > 0) {
            $aktifGorevSayisi = DB::table('tbBolumHavuz')->where('No', $gorevNo)->count();
            $bitenGorevSayisi = DB::table('tbGorevler')->where('No', $gorevNo)->count();
        }

        return [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler];
    }

    private function formatLegacyDateTime($value): string
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

    private function humanElapsedSince($value): string
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
}
