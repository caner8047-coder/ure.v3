<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\OrderItem;
use App\Models\ProductMatchCache;
use App\Models\Product;
use Carbon\Carbon;
use Exception;
use App\Services\OrderSyncService;
use App\Services\BomService;
use App\Services\WorkOrderService;
use App\Services\WorkOrderEventLogger;
use App\Services\StockMovementLogger;

class SiparisApiController extends Controller
{
    private const GIED_ALLOCATION_TABLE = 'tbSiparisOzelUretimRezervasyon';

    private const MUTATING_ACTIONS = [
        'uploadOrders',
        'matchProduct',
        'clearOrderMatch',
        'createOrderWorkOrders',
        'createManualWorkOrder',
        'passivateWithWorkOrderCancel',
        'saveStockCodes',
        'cancelWorkOrder',
        'cancelBulkWorkOrders',
        'createIndependentStockOrder',
        'addIndependentStockOrder',
        'importPersoneller',
        'importBolumler',
        'importAraUrunler',
        'importUrunler',
        'clearAllOrders',
        'deductStock',
        'deductStockBulk',
        'undoDeductStock',
        'saveThreshold',
        'deleteThreshold',
        'resetThresholds',
        'rematchOrders',
        'addMatchCache',
        'deleteMatchCache',
        'addSetDefinition',
        'deleteSetDefinition',
        'linkOrderToSpecialProduction',
        'linkOrdersToSpecialProductionBulk',
        'cancelWipAllocation',
        'onlyUpdateStatusBulk',
        'fixKayipOzelUretim',
        'reactivateOrder',
    ];

    public function __construct(
        protected \App\Services\OrderSyncService $orderSync,
        protected \App\Services\OrderToWorkOrderService $orderToWorkOrder,
        protected WorkOrderService $workOrderService,
        protected WorkOrderEventLogger $workOrderEventLogger,
        protected StockMovementLogger $stockMovementLogger
    ) {}

    /**
     * Handles all legacy ?action= AJAX requests seamlessly
     */
    public function handleEndpoint(Request $request)
    {
        $action = (string) $request->input('action', '');

        if ($guardResponse = $this->guardLegacyRequest($request, $action)) {
            return $guardResponse;
        }

        try {
            switch ($action) {
                case 'uploadOrders':
                    return $this->uploadOrders($request);
                case 'getOrders':
                    return $this->getOrders($request);
                case 'matchProduct':
                    return $this->matchProduct($request);
                case 'clearOrderMatch':
                    return $this->clearOrderMatch($request);
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
                case 'getProductBomComponents':
                    return $this->getProductBomComponents($request);
                case 'createIndependentStockOrder':
                    return $this->createIndependentStockOrder($request);
                case 'addIndependentStockOrder':
                    return $this->addIndependentStockOrder($request);
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
                case 'undoDeductStock':
                    return $this->undoDeductStock($request);
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
                case 'linkOrdersToSpecialProductionBulk':
                    return $this->linkOrdersToSpecialProductionBulk($request);
                case 'cancelWipAllocation':
                    return $this->cancelWipAllocation($request);
                case 'onlyUpdateStatusBulk':
                    return $this->onlyUpdateStatusBulk($request);
                case 'fixKayipOzelUretim':
                    return $this->fixKayipOzelUretim($request);
                case 'getPasifDevamEden':
                    return $this->getPasifDevamEden($request);
                case 'getOrderPipeline':
                    return $this->getOrderPipeline($request);
                case 'getProductionDetail':
                    return $this->getProductionDetail($request);
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

    private function guardLegacyRequest(Request $request, string $action)
    {
        $isMutatingAction = in_array($action, self::MUTATING_ACTIONS, true);

        if ($isMutatingAction && !$request->isMethod('POST')) {
            return response()->json([
                'success' => false,
                'message' => 'Bu işlem yalnızca POST isteğiyle yapılabilir.',
            ], 405);
        }

        if (($isMutatingAction || !$request->isMethodSafe()) && !$this->hasTrustedOrigin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Güvenlik doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.',
            ], 419);
        }

        return null;
    }

    private function hasTrustedOrigin(Request $request): bool
    {
        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        if (!$source) {
            return true;
        }

        $sourceHost = parse_url($source, PHP_URL_HOST);
        if (!$sourceHost) {
            return false;
        }

        $sourcePort = parse_url($source, PHP_URL_PORT);
        $sourceHostWithPort = strtolower($sourceHost . ($sourcePort ? ':' . $sourcePort : ''));
        $requestHost = strtolower($request->getHost());
        $requestHostWithPort = strtolower($request->getHttpHost());

        return $sourceHostWithPort === $requestHostWithPort || strtolower($sourceHost) === $requestHost;
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
            $stockFulfilledOrderNos = method_exists($this, 'activeStockFulfilledOrderItemNos')
                ? $this->activeStockFulfilledOrderItemNos()
                : [];
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

            // Durum filtresi — liste kapsamı ve üst kartlarla aynı aktif satır mantığı
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
                // Tümü — varsayılan olarak aksiyon bekleyen aktif siparişler
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

            // Sıralama (ASP.NET ile aynı: önce pasif/inaktif sonunda, sonra kargo tarihi ASC)
            $query->orderByRaw("CASE WHEN Durum='Pasif' OR Aktif=0 THEN 1 ELSE 0 END ASC")
                  ->orderBy('KargoSonTeslim', 'asc')
                  ->orderBy('SiparisTarihi', 'asc');

            $rows = $query->get();

            // ─── Response formatı: ASP.NET ile birebir uyumlu ───
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
                    'stokAdet'        => 0,       // Sonra doldurulacak
                    'uretimdeAdet'    => 0,        // Sonra doldurulacak
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

                $araNo = $this->resolveMatchedAraUrunNoFromMaps($esNo, $esTur, $urunlerMap, $araUrunMap);
                if ($araNo > 0) {
                    $ord['stokAdet'] = (int) ($stokMap[$araNo] ?? 0);
                }
            }

            // ─── Üretimde adet bilgisi ekle (ASP.NET birebir) ───
            // Havuz (atanmamış) + PersonelGorev (atanmış devam eden)
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

            // ─── İstatistik sayıları: ekrandaki normal/özel üretim kapsamıyla aynı ───
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

    private function getMatchCache(Request $request)
    {
        try {
            $itemsQuery = DB::select("
                SELECT c.No as no, c.ExcelUrunAdi as excelUrunAdi, c.EslesenUrunNo as eslesenUrunNo,
                       c.EslesenUrunTur as eslesenUrunTur, c.OlusturmaTarihi as olusturmaTarihi,
                       COALESCE(NULLIF(TRIM(u.SistemAdi), ''), NULLIF(TRIM(u.UrunID), '')) AS sistemUrunAdi
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
                WHERE IFNULL(Aktif,1) = 1
                ORDER BY OlusturmaTarihi DESC
            ");

            $sets = collect($setsQuery)->map(function($set) {
                if ($set->olusturmaTarihi) {
                    $set->olusturmaTarihi = \Carbon\Carbon::parse($set->olusturmaTarihi)->format('d.m.Y H:i');
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

            $result = DB::transaction(function () use ($satirNo) {
                $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->lockForUpdate()->first();
                if (!$satir) throw new \Exception('Satır bulunamadı.');

                $gorevNo = intval($satir->GorevNo ?? 0);
                $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                $tamponJson = $satir->TamponDusumleri ?? '';
                $durum = $satir->Durum ?? '';
                $musteri = $satir->Musteri ?? '';

                // ASP.NET durum kontrolü: IsEmriVerildi VEYA (UretimBekliyor + ÖZEL ÜRETİM)
                $isOzelUretim = $this->isSpecialProductionOrder($musteri);
                if (!$this->canCancelWorkOrderStatus($durum, $isOzelUretim)) {
                    return ['success' => false, 'message' => 'Bu durumdaki sipariş iptal edilemez.'];
                }

                $stockReversal = $this->reverseStockEffectsForWorkOrderCancellation($satir);

                // Tampon stoğu geri yükle
                $this->restoreTamponFromJson($tamponJson);

                $deleted = $this->deleteProductionRowsForOrder($satir);

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

                $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                $this->logCenterEvent([
                    'event_type' => 'work_order_cancelled',
                    'aggregate_type' => 'order_item',
                    'aggregate_id' => $satirNo,
                    'order_item_no' => $satirNo,
                    'order_no' => (string) ($updatedSatir->SiparisNo ?? $satir->SiparisNo ?? ''),
                    'work_order_no' => $gorevNo > 0 ? $gorevNo : null,
                    'status_before' => $durum,
                    'status_after' => $updatedSatir->Durum ?? $yeniDurum,
                    'next_step_human' => $isOzelUretim
                        ? 'Kayit pasife alindi; gerekirse tekrar aktive edilmeli.'
                        : 'Siparis tekrar is emri verilmeyi bekliyor.',
                    'payload_before' => $this->serializeRecord($satir),
                    'payload_after' => $this->serializeRecord($updatedSatir),
                    'context' => [
                        'deleted_production_rows' => $deleted,
                        'stock_reversal' => $stockReversal,
                        'is_special_production' => $isOzelUretim,
                    ],
                ]);

                return [
                    'success' => true,
                    'deleted' => $deleted,
                    'stockReversal' => $stockReversal,
                    'message' => "İş emri iptal edildi. {$deleted} görev silindi.",
                ];
            });

            return response()->json($result);
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

            if (empty($satirNolar)) {
                return response()->json(['success' => false, 'message' => 'İptal edilecek sipariş seçilmedi.']);
            }

            foreach ($satirNolar as $satirNo) {
                try {
                    $result = DB::transaction(function () use ($satirNo) {
                        $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->lockForUpdate()->first();
                        if (!$satir) return ['cancelled' => false, 'deleted' => 0];

                        $durum = $satir->Durum ?? '';
                        $musteri = $satir->Musteri ?? '';

                        // ASP.NET durum kontrolü: IsEmriVerildi VEYA (UretimBekliyor + ÖZEL ÜRETİM)
                        $isOzelUretim = $this->isSpecialProductionOrder($musteri);
                        if (!$this->canCancelWorkOrderStatus($durum, $isOzelUretim)) return ['cancelled' => false, 'deleted' => 0];

                        $stockReversal = $this->reverseStockEffectsForWorkOrderCancellation($satir);
                        $this->restoreTamponFromJson($satir->TamponDusumleri ?? '');

                        $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                        $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                        $gorevNo = intval($satir->GorevNo ?? 0);
                        $deleted = $this->deleteProductionRowsForOrder($satir);

                        $this->logIsEmriGecmisi($satirNo, $gorevNo, $eslesenUrunNo, $eslesenUrunTur, 'IptalEdildi');

                        // ASP.NET: Özel Üretimse Pasife al
                        $yeniDurum = $isOzelUretim ? 'Pasif' : 'UretimBekliyor';
                        $yeniAktif = $isOzelUretim ? 0 : 1;

                        DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                            'Durum' => $yeniDurum, 'Aktif' => $yeniAktif,
                            'GorevNo' => null, 'IsEmriTarihi' => null,
                            'TamponDusumleri' => null, 'GuncellemeTarihi' => now()
                        ]);

                        $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                        $this->logCenterEvent([
                            'event_type' => 'work_order_cancelled',
                            'aggregate_type' => 'order_item',
                            'aggregate_id' => intval($satirNo),
                            'order_item_no' => intval($satirNo),
                            'order_no' => (string) ($updatedSatir->SiparisNo ?? $satir->SiparisNo ?? ''),
                            'work_order_no' => $gorevNo > 0 ? $gorevNo : null,
                            'status_before' => $durum,
                            'status_after' => $updatedSatir->Durum ?? $yeniDurum,
                            'title_human' => 'Toplu is emri iptal edildi',
                            'next_step_human' => $isOzelUretim
                                ? 'Kayit pasife alindi; gerekirse tekrar aktive edilmeli.'
                                : 'Siparis tekrar is emri verilmeyi bekliyor.',
                            'payload_before' => $this->serializeRecord($satir),
                            'payload_after' => $this->serializeRecord($updatedSatir),
                            'context' => [
                                'deleted_production_rows' => $deleted,
                                'stock_reversal' => $stockReversal,
                                'bulk' => true,
                                'is_special_production' => $isOzelUretim,
                            ],
                        ]);

                        return ['cancelled' => true, 'deleted' => $deleted];
                    });

                    if (($result['cancelled'] ?? false) !== true) continue;
                    $toplamIptal++; $toplamSilinen += intval($result['deleted'] ?? 0);
                } catch (\Exception $ex) {
                    $hatalar[] = "Satır {$satirNo}: " . $ex->getMessage();
                }
            }

            if ($toplamIptal === 0) {
                return response()->json([
                    'success' => false,
                    'iptalEdilen' => 0,
                    'silinenGorev' => 0,
                    'hatalar' => $hatalar,
                    'message' => 'Seçilen satırlarda iptal edilebilir iş emri bulunamadı.'
                ]);
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
                    $result = DB::transaction(function () use ($satirNo) {
                        $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->lockForUpdate()->first();
                        if (!$satir) return ['cancelled' => false, 'deleted' => 0];

                        $stockReversal = $this->reverseStockEffectsForWorkOrderCancellation($satir);
                        $this->restoreTamponFromJson($satir->TamponDusumleri ?? '');

                        $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                        $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                        $gorevNo = intval($satir->GorevNo ?? 0);
                        $deleted = $this->deleteProductionRowsForOrder($satir);

                        $this->logIsEmriGecmisi($satirNo, $gorevNo, $eslesenUrunNo, $eslesenUrunTur, 'IptalEdildi');

                        DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                            'Durum' => 'Pasif', 'Aktif' => 0, 'GorevNo' => null, 'IsEmriTarihi' => null,
                            'TamponDusumleri' => null, 'GuncellemeTarihi' => now()
                        ]);

                        $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                        $this->logCenterEvent([
                            'event_type' => 'order_passivated',
                            'aggregate_type' => 'order_item',
                            'aggregate_id' => intval($satirNo),
                            'order_item_no' => intval($satirNo),
                            'order_no' => (string) ($updatedSatir->SiparisNo ?? $satir->SiparisNo ?? ''),
                            'work_order_no' => $gorevNo > 0 ? $gorevNo : null,
                            'status_before' => $satir->Durum ?? null,
                            'status_after' => $updatedSatir->Durum ?? 'Pasif',
                            'next_step_human' => 'Kayit pasif durumda. Gerekirse yeniden aktive edilmeden islem yapilamaz.',
                            'payload_before' => $this->serializeRecord($satir),
                            'payload_after' => $this->serializeRecord($updatedSatir),
                            'context' => [
                                'deleted_production_rows' => $deleted,
                                'stock_reversal' => $stockReversal,
                                'bulk' => true,
                            ],
                        ]);

                        return ['cancelled' => true, 'deleted' => $deleted];
                    });

                    if (($result['cancelled'] ?? false) !== true) continue;
                    $cancelled++; $deletedTotal += intval($result['deleted'] ?? 0);
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

            $bomService = app(\App\Services\BomService::class);

            $summary = collect(DB::select($query, $bindings))->map(function ($row) use ($bomService) {
                $row->SatirNolar = collect(explode(',', (string) ($row->SatirNolar ?? '')))
                    ->map(fn ($no) => intval($no))
                    ->filter(fn ($no) => $no > 0)
                    ->sort()
                    ->values()
                    ->all();

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

                    // Stok durumu: tbBolumAraStok üzerinden Depodaki / Görevdeki / Boşta
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

    private function getAraUrunler(Request $request)
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
            $query->limit(100); // Select2 gibi arama kutuları için limiti devreye sok
        }

        $data = $query->orderBy('a.AraUrunAdi')->get();
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
        return $this->importLegacyDatabaseRows($request, 'personnel');
    }

    private function importBolumler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'departments');
    }

    private function importAraUrunler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'components');
    }

    private function importUrunler(Request $request)
    {
        return $this->importLegacyDatabaseRows($request, 'products');
    }

    private function importLegacyDatabaseRows(Request $request, string $module)
    {
        $payload = $request->json()->all();
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : $payload;

        $request->merge(['rows' => is_array($rows) ? $rows : []]);

        return app(AdminDatabaseController::class)->importModule($request, $module);
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

            if (empty($nos)) {
                return response()->json(['success' => false, 'message' => 'Stoktan düşülecek sipariş seçilmedi.']);
            }

            foreach ($nos as $satirNo) {
                $result = $this->deductStockForOrder(intval($satirNo));
                if ($result === 'OK') $basarili++;
                else $hatalar[] = "Satır {$satirNo}: {$result}";
            }

            if ($basarili === 0) {
                return response()->json([
                    'success' => false,
                    'basarili' => 0,
                    'hatalar' => $hatalar,
                    'message' => count($hatalar) > 0 ? implode(' ', $hatalar) : 'Stoktan düşülebilecek sipariş bulunamadı.'
                ]);
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
    //   UNDO DEDUCT STOCK — Stoktan düşmeyi geri al
    // ================================================================
    private function undoDeductStock(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNo = intval($data['no'] ?? 0);
            $result = $this->undoStockDeductionForOrder($satirNo);

            if (($result['success'] ?? false) === true) {
                return response()->json($result);
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Stoktan düşme geri alınamadı.'
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

            $repairedSets = 0;
            $setParents = DB::table('tbSiparisSatir')
                ->select('No')
                ->where('Durum', 'UretimBekliyor')
                ->where('Aktif', 1)
                ->where(DB::raw('IFNULL(SetMi,0)'), 1)
                ->where(DB::raw('IFNULL(AnaSetSatirNo,0)'), 0)
                ->get();

            foreach ($setParents as $setParent) {
                $childCount = DB::table('tbSiparisSatir')
                    ->where('AnaSetSatirNo', (int) $setParent->No)
                    ->where('Aktif', 1)
                    ->count();

                if ($childCount === 0 && $this->orderSync->repairSetOrderChildren((int) $setParent->No)) {
                    $repairedSets++;
                }
            }

            $candidates = DB::table('tbSiparisSatir')
                ->select('No', 'UrunAdi', 'StokKodu', 'Adet', 'Durum', 'GorevNo', 'EslesenUrunNo', 'EslesmeYontemi')
                ->where('Durum', 'UretimBekliyor')
                ->where('Aktif', 1)
                ->where(DB::raw('IFNULL(SetMi,0)'), 0)
                ->where(DB::raw('IFNULL(AnaSetSatirNo,0)'), 0)
                ->get();

            $totalMatched = 0;
            $skippedSetLike = 0;
            $clearedSetLikeMatches = 0;
            foreach ($candidates as $row) {
                $urunAdi = $row->UrunAdi ?? '';
                $stokKodu = trim($row->StokKodu ?? '');
                $eslesenUrunNo = 0; $eslesenUrunTur = ''; $yontem = '';

                if (intval($row->GorevNo ?? 0) <= 0
                    && $this->orderSync->tryApplySetDefinitionToOrder((int) $row->No, $urunAdi, (int) ($row->Adet ?? 1))) {
                    $totalMatched++;
                    continue;
                }

                if ($this->orderSync->shouldAvoidSingleProductAutoMatch($urunAdi)) {
                    $method = (string) ($row->EslesmeYontemi ?? '');
                    if (intval($row->EslesenUrunNo ?? 0) > 0 && $method !== 'Manuel') {
                        $this->orderSync->clearOrderMatch((int) $row->No, true);
                        $clearedSetLikeMatches++;
                    }
                    $skippedSetLike++;
                    continue;
                }

                if (intval($row->EslesenUrunNo ?? 0) > 0) {
                    continue;
                }

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

            return response()->json([
                'success' => true,
                'total' => $candidates->count() + $setParents->count(),
                'matched' => $totalMatched,
                'repairedSets' => $repairedSets,
                'skippedSetLike' => $skippedSetLike,
                'clearedSetLikeMatches' => $clearedSetLikeMatches,
                'message' => "{$totalMatched} sipariş eşleştirildi.",
            ]);
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

            if ($eslesenUrunNo <= 0 || trim($eslesenUrunTur) === '' || $istenenAdet <= 0) {
                throw new \Exception('GİED için ürün ve adet bilgisi zorunludur.');
            }

            $capacity = $this->buildSpecialProductionAllocationPlan($eslesenUrunNo, $eslesenUrunTur, $istenenAdet);
            if (intval($capacity['total_available'] ?? 0) < $istenenAdet) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'totalAvailable' => intval($capacity['total_available'] ?? 0),
                    'requested' => $istenenAdet,
                ]);
            }

            $plannedQuantities = collect($capacity['allocations'] ?? [])
                ->mapWithKeys(fn ($allocation) => [intval($allocation['special_production_no'] ?? 0) => intval($allocation['quantity'] ?? 0)]);

            $data = collect($capacity['rows'] ?? [])->filter(function ($row) {
                return intval($row->BostaAdet ?? 0) > 0;
            })->map(function ($row) use ($plannedQuantities) {
                $isEmriTarihi = $row->IsEmriTarihi ?? null;
                if ($isEmriTarihi) {
                    try {
                        $isEmriTarihi = Carbon::parse($isEmriTarihi)->format('d.m.Y H:i');
                    } catch (\Exception $e) {
                        $isEmriTarihi = (string) $isEmriTarihi;
                    }
                }

                $toplamAdet = intval($row->ToplamAdet ?? 0);
                $rezerveAdet = intval($row->RezerveAdet ?? 0);
                $bostaAdet = max(0, $toplamAdet - $rezerveAdet);
                $no = intval($row->No ?? 0);
                $ayrilacakAdet = intval($plannedQuantities[$no] ?? 0);

                return [
                    'No' => $no,
                    'no' => $no,
                    'siparisNo' => (string) ($row->SiparisNo ?? ''),
                    'urunAdi' => (string) ($row->UrunAdi ?? ''),
                    'ToplamAdet' => $toplamAdet,
                    'toplamAdet' => $toplamAdet,
                    'RezerveAdet' => $rezerveAdet,
                    'rezerveAdet' => $rezerveAdet,
                    'BostaAdet' => $bostaAdet,
                    'bostaAdet' => $bostaAdet,
                    'ayrilacakAdet' => $ayrilacakAdet,
                    'durum' => (string) ($row->Durum ?? ''),
                    'isEmriTarihi' => $isEmriTarihi ?: '',
                    'gorevNo' => intval($row->GorevNo ?? 0),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data->values(),
                'requested' => $istenenAdet,
                'totalAvailable' => intval($capacity['total_available'] ?? 0),
                'splitRequired' => count($capacity['allocations'] ?? []) > 1,
                'allocationPlan' => $capacity['allocations'] ?? [],
            ]);
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

            $eventPayload = DB::transaction(function () use ($siparisNo, $ozelUretimNo) {
                if ($siparisNo <= 0 || $ozelUretimNo <= 0) {
                    throw new \Exception('GİED için sipariş ve özel üretim seçimi zorunludur.');
                }

                $order = DB::table('tbSiparisSatir')
                    ->where('No', $siparisNo)
                    ->where('Aktif', 1)
                    ->lockForUpdate()
                    ->first();
                if (!$order) throw new \Exception('Sipariş bulunamadı veya pasif.');

                $specialProduction = DB::table('tbSiparisSatir')
                    ->where('No', $ozelUretimNo)
                    ->where('Aktif', 1)
                    ->where('Musteri', 'like', 'ÖZEL ÜRETİM%')
                    ->lockForUpdate()
                    ->first();

                if (!$specialProduction) throw new \Exception('Özel üretim kaydı bulunamadı.');
                if (intval($specialProduction->EslesenUrunNo ?? 0) !== intval($order->EslesenUrunNo ?? 0)
                    || (string) ($specialProduction->EslesenUrunTur ?? '') !== (string) ($order->EslesenUrunTur ?? '')
                ) {
                    throw new \Exception('Seçilen özel üretim sipariş ile aynı ürüne ait değil.');
                }

                $linkedSpecialProductionNo = intval($order->BagliOlduguOzelUretimNo ?? 0);
                if ($linkedSpecialProductionNo > 0) {
                    if ($linkedSpecialProductionNo !== $ozelUretimNo) {
                        throw new \Exception("Bu sipariş zaten başka bir üretim rezervasyonuna bağlı. (Bağlı kayıt: {$linkedSpecialProductionNo})");
                    }

                    $currentStatus = (string) ($order->Durum ?? '');
                    if (in_array($currentStatus, ['Pasif', 'StokKarsilandi'], true)) {
                        throw new \Exception('Bu sipariş zaten kapanmış; GİED bağlantısı yeniden uygulanamaz.');
                    }

                    if ($currentStatus !== 'UretimdenKarsilaniyor') {
                        DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                            'Durum' => 'UretimdenKarsilaniyor',
                            'GuncellemeTarihi' => now(),
                        ]);

                        return [
                            'before' => $order,
                            'after' => DB::table('tbSiparisSatir')->where('No', $siparisNo)->first(),
                            'special_production_no' => $ozelUretimNo,
                            'should_log' => true,
                            'message' => 'Sipariş zaten bu özel üretime bağlıydı; durum GİED rezervasyonu olarak düzeltildi.',
                        ];
                    }

                    return [
                        'before' => $order,
                        'after' => $order,
                        'special_production_no' => $ozelUretimNo,
                        'should_log' => false,
                        'message' => 'Sipariş zaten bu özel üretime bağlı.',
                    ];
                }

                if (($order->Durum ?? '') !== 'UretimBekliyor') throw new \Exception('Sadece üretim bekleyen siparişler GİED ile bağlanabilir.');

                $specialStatus = (string) ($specialProduction->Durum ?? '');
                if (in_array($specialStatus, ['Pasif', 'StokKarsilandi'], true)) {
                    throw new \Exception('Bu özel üretim artık rezervasyona uygun değil.');
                }

                if (intval($specialProduction->GorevNo ?? 0) <= 0
                    && !in_array($specialStatus, ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor'], true)
                ) {
                    throw new \Exception('Henüz üretim bandına alınmamış özel üretime rezervasyon bağlanamaz.');
                }

                $allocationPlan = $this->buildSpecialProductionAllocationPlan(
                    intval($order->EslesenUrunNo ?? 0),
                    (string) ($order->EslesenUrunTur ?? ''),
                    intval($order->Adet ?? 0),
                    $ozelUretimNo,
                    true,
                    true
                );
                $allocations = $allocationPlan['allocations'] ?? [];
                if (intval($allocationPlan['total_available'] ?? 0) < intval($order->Adet ?? 0) || empty($allocations)) {
                    throw new \Exception("Özel üretimlerin toplam boş kapasitesi yetersiz! (Boşta: " . intval($allocationPlan['total_available'] ?? 0) . ", İstenen: {$order->Adet})");
                }
                if (count($allocations) > 1 && !$this->supportsSplitGiedAllocations()) {
                    throw new \Exception('Bu sipariş birden fazla özel üretimden karşılanmalı; bunun için GİED paylaştırma tablosu kurulmalı.');
                }

                $primarySpecialProductionNo = intval($allocations[0]['special_production_no'] ?? $ozelUretimNo);

                DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                    'BagliOlduguOzelUretimNo' => $primarySpecialProductionNo,
                    'Durum' => 'UretimdenKarsilaniyor',
                    'GuncellemeTarihi' => now(),
                ]);
                $this->replaceGiedAllocations($siparisNo, $allocations);

                return [
                    'before' => $order,
                    'after' => DB::table('tbSiparisSatir')->where('No', $siparisNo)->first(),
                    'special_production_no' => $primarySpecialProductionNo,
                    'allocations' => $allocations,
                    'should_log' => true,
                    'message' => count($allocations) > 1
                        ? 'Sipariş özel üretimlere paylaştırılarak bağlandı!'
                        : 'Sipariş özel üretime bağlandı!',
                ];
            });

            if (!empty($eventPayload['should_log'])) {
                foreach (($eventPayload['allocations'] ?? []) as $allocation) {
                    $this->logWipLinkedEvent($eventPayload, intval($allocation['special_production_no'] ?? 0));
                }
                if (empty($eventPayload['allocations'])) {
                    $this->logWipLinkedEvent($eventPayload, intval($eventPayload['special_production_no'] ?? $ozelUretimNo));
                }
            }

            return response()->json(['success' => true, 'message' => $eventPayload['message']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function linkOrdersToSpecialProductionBulk(Request $request)
    {
        try {
            $data = $request->json()->all();
            $satirNolar = $data['satirNolar'] ?? ($data['nos'] ?? []);
            if (!is_array($satirNolar)) {
                $satirNolar = [$satirNolar];
            }

            $satirNolar = collect($satirNolar)
                ->map(fn ($no) => intval($no))
                ->filter(fn ($no) => $no > 0)
                ->unique()
                ->values()
                ->all();

            if (empty($satirNolar)) {
                return response()->json(['success' => false, 'message' => 'GİED yapılacak sipariş seçilmedi.']);
            }

            $basarili = 0;
            $hatalar = [];
            $baglantilar = [];

            foreach ($satirNolar as $satirNo) {
                try {
                    $eventPayload = $this->linkOrderToFirstAvailableSpecialProduction($satirNo);
                    if (!empty($eventPayload['should_log'])) {
                        foreach (($eventPayload['allocations'] ?? []) as $allocation) {
                            $this->logWipLinkedEvent($eventPayload, intval($allocation['special_production_no'] ?? 0));
                        }
                        if (empty($eventPayload['allocations'])) {
                            $this->logWipLinkedEvent($eventPayload, intval($eventPayload['special_production_no'] ?? 0));
                        }
                    }

                    $basarili++;
                    $baglantilar[] = [
                        'satirNo' => $satirNo,
                        'ozelUretimNo' => intval($eventPayload['special_production_no'] ?? 0),
                        'dagitim' => $eventPayload['allocations'] ?? [],
                    ];
                } catch (\Throwable $e) {
                    $hatalar[] = "Satır {$satirNo}: {$e->getMessage()}";
                }
            }

            if ($basarili === 0) {
                return response()->json([
                    'success' => false,
                    'basarili' => 0,
                    'hatalar' => $hatalar,
                    'message' => count($hatalar) > 0 ? implode(' ', $hatalar) : 'GİED yapılabilecek sipariş bulunamadı.',
                ]);
            }

            return response()->json([
                'success' => true,
                'basarili' => $basarili,
                'hatalar' => $hatalar,
                'baglantilar' => $baglantilar,
                'message' => "{$basarili} sipariş GİED yapıldı." . (count($hatalar) > 0 ? ' (' . count($hatalar) . ' hata)' : ''),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function linkOrderToFirstAvailableSpecialProduction(int $siparisNo): array
    {
        return DB::transaction(function () use ($siparisNo) {
            if ($siparisNo <= 0) {
                throw new \Exception('GİED için sipariş seçimi zorunludur.');
            }

            $order = DB::table('tbSiparisSatir')
                ->where('No', $siparisNo)
                ->where('Aktif', 1)
                ->lockForUpdate()
                ->first();

            if (!$order) throw new \Exception('Sipariş bulunamadı veya pasif.');
            if ($this->isSpecialProductionOrder($order->Musteri ?? null)) {
                throw new \Exception('Özel üretim kayıtları toplu GİED ile başka üretime bağlanamaz.');
            }
            if (intval($order->BagliOlduguOzelUretimNo ?? 0) > 0) {
                throw new \Exception('Bu sipariş zaten bir GİED rezervasyonuna bağlı.');
            }
            if (($order->Durum ?? '') !== 'UretimBekliyor') {
                throw new \Exception('Sadece üretim bekleyen siparişler GİED ile bağlanabilir.');
            }

            $eslesenUrunNo = intval($order->EslesenUrunNo ?? 0);
            $eslesenUrunTur = (string) ($order->EslesenUrunTur ?? '');
            $adet = intval($order->Adet ?? 0);
            if ($eslesenUrunNo <= 0 || trim($eslesenUrunTur) === '' || $adet <= 0) {
                throw new \Exception('GİED için ürün ve adet bilgisi zorunludur.');
            }

            $allocationPlan = $this->buildSpecialProductionAllocationPlan($eslesenUrunNo, $eslesenUrunTur, $adet, null, false, true);
            $allocations = $allocationPlan['allocations'] ?? [];
            if (intval($allocationPlan['total_available'] ?? 0) < $adet || empty($allocations)) {
                throw new \Exception('Uygun boş kapasiteli özel/stok üretim bulunamadı.');
            }
            if (count($allocations) > 1 && !$this->supportsSplitGiedAllocations()) {
                throw new \Exception('Bu sipariş birden fazla özel üretimden karşılanmalı; bunun için GİED paylaştırma tablosu kurulmalı.');
            }

            $ozelUretimNo = intval($allocations[0]['special_production_no'] ?? 0);
            DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                'BagliOlduguOzelUretimNo' => $ozelUretimNo,
                'Durum' => 'UretimdenKarsilaniyor',
                'GuncellemeTarihi' => now(),
            ]);
            $this->replaceGiedAllocations($siparisNo, $allocations);

            return [
                'before' => $order,
                'after' => DB::table('tbSiparisSatir')->where('No', $siparisNo)->first(),
                'special_production_no' => $ozelUretimNo,
                'allocations' => $allocations,
                'should_log' => true,
                'message' => count($allocations) > 1
                    ? 'Sipariş özel üretimlere paylaştırılarak bağlandı!'
                    : 'Sipariş özel üretime bağlandı!',
            ];
        });
    }

    private function logWipLinkedEvent(array $eventPayload, int $ozelUretimNo): void
    {
        $this->logCenterEvent([
            'event_type' => 'wip_linked',
            'aggregate_type' => 'order_item',
            'aggregate_id' => intval($eventPayload['after']->No ?? $eventPayload['before']->No ?? 0),
            'order_item_no' => intval($eventPayload['after']->No ?? $eventPayload['before']->No ?? 0),
            'order_no' => (string) (($eventPayload['after']->SiparisNo ?? '') ?: ($eventPayload['before']->SiparisNo ?? '')),
            'work_order_no' => intval($eventPayload['after']->GorevNo ?? $eventPayload['before']->GorevNo ?? 0) > 0
                ? intval($eventPayload['after']->GorevNo ?? $eventPayload['before']->GorevNo ?? 0)
                : null,
            'special_production_no' => $ozelUretimNo,
            'status_before' => $eventPayload['before']->Durum ?? null,
            'status_after' => $eventPayload['after']->Durum ?? null,
            'next_step_human' => 'Bagli ozel uretimin tamamlanmasi bekleniyor.',
            'payload_before' => $this->serializeRecord($eventPayload['before'] ?? null),
            'payload_after' => $this->serializeRecord($eventPayload['after'] ?? null),
            'context' => [
                'special_production_no' => $ozelUretimNo,
                'allocated_quantity' => collect($eventPayload['allocations'] ?? [])
                    ->firstWhere('special_production_no', $ozelUretimNo)['quantity'] ?? null,
                'gied_allocations' => $eventPayload['allocations'] ?? [],
            ],
        ]);
    }

    // ================================================================
    //   CANCEL WIP ALLOCATION
    // ================================================================
    private function cancelWipAllocation(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisNo = intval($data['siparisNo'] ?? 0);

            $payload = DB::transaction(function () use ($siparisNo) {
                $order = DB::table('tbSiparisSatir')
                    ->where('No', $siparisNo)
                    ->lockForUpdate()
                    ->first();
                if (!$order) throw new \Exception('Sipariş bulunamadı.');
                $activeAllocations = $this->activeGiedAllocationsForOrder($siparisNo);
                if (empty($order->BagliOlduguOzelUretimNo) && empty($activeAllocations)) {
                    throw new \Exception('Bu siparişin aktif bir GİED rezervasyonu yok.');
                }

                $currentStatus = (string) ($order->Durum ?? '');
                $nextStatus = $currentStatus === 'StokKarsilandi' ? 'StokKarsilandi' : 'UretimBekliyor';

                DB::table('tbSiparisSatir')->where('No', $siparisNo)->update([
                    'BagliOlduguOzelUretimNo' => null,
                    'Durum' => $nextStatus,
                    'GuncellemeTarihi' => now(),
                ]);
                $this->clearGiedAllocations($siparisNo);

                return [
                    'before' => $order,
                    'after' => DB::table('tbSiparisSatir')->where('No', $siparisNo)->first(),
                    'allocations' => $activeAllocations,
                    'message' => $nextStatus === 'StokKarsilandi'
                        ? 'Rezervasyon temizlendi, sipariş stoktan karşılanmış durumda bırakıldı.'
                        : 'Rezervasyon iptal edildi.',
                ];
            });

            $this->logCenterEvent([
                'event_type' => 'wip_unlinked',
                'aggregate_type' => 'order_item',
                'aggregate_id' => $siparisNo,
                'order_item_no' => $siparisNo,
                'order_no' => (string) (($payload['after']->SiparisNo ?? '') ?: ($payload['before']->SiparisNo ?? '')),
                'work_order_no' => intval($payload['after']->GorevNo ?? $payload['before']->GorevNo ?? 0) > 0
                    ? intval($payload['after']->GorevNo ?? $payload['before']->GorevNo ?? 0)
                    : null,
                'special_production_no' => intval($payload['before']->BagliOlduguOzelUretimNo ?? 0) > 0
                    ? intval($payload['before']->BagliOlduguOzelUretimNo)
                    : null,
                'status_before' => $payload['before']->Durum ?? null,
                'status_after' => $payload['after']->Durum ?? null,
                'next_step_human' => ($payload['after']->Durum ?? '') === 'StokKarsilandi'
                    ? 'Kayit stoktan kapanmis durumda kaldı.'
                    : 'Siparis yeniden uretim bekliyor durumuna dondu.',
                'payload_before' => $this->serializeRecord($payload['before']),
                'payload_after' => $this->serializeRecord($payload['after']),
                'context' => [
                    'gied_allocations' => $payload['allocations'] ?? [],
                ],
            ]);

            return response()->json(['success' => true, 'message' => $payload['message']]);
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
        $tur = $payload['tur'] ?? 'Nihai';
        $altBilesenNo = $payload['altBilesenNo'] ?? null;
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));

        $result = $this->orderToWorkOrder->createOrderWorkOrders($satirlar, $surplus, $tur, $altBilesenNo, $stokDurum);
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
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));
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

        if ($gorevNo > 0) {
            $workOrder = DB::table('tbGorevler')->where('No', $gorevNo)->first();
            $this->logCenterEvent([
                'event_type' => 'work_order_created_manual',
                'aggregate_type' => 'work_order',
                'aggregate_id' => $gorevNo,
                'work_order_no' => $gorevNo,
                'order_item_no' => intval($workOrder->SiparisSatirNo ?? 0) > 0 ? intval($workOrder->SiparisSatirNo) : null,
                'order_no' => (string) ($workOrder->SiparisNo ?? ''),
                'status_after' => 'IsEmriVerildi',
                'next_step_human' => 'Havuz ve personel gorevleri takip edilmeli.',
                'payload_after' => $this->serializeRecord($workOrder),
                'context' => [
                    'tur' => $tur,
                    'urun_no' => $urunNo,
                    'adet' => $adet,
                    'stok_durum' => $stokDurum,
                    'aciklama' => $aciklama,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'gorevNo' => $gorevNo,
            'tamponDusumleri' => $result['tamponDusumleri'] ?? [],
            'message' => $message,
        ]);
    }

    private function normalizeWorkOrderStockMode(string $stokDurum): string
    {
        return in_array($stokDurum, ['StokDahil', 'StokHaric'], true) ? $stokDurum : 'StokDahil';
    }

    private function onlyUpdateStatusBulk(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $satirNolar = collect($payload['satirNolar'] ?? [])->map(fn ($no) => intval($no))->filter()->values();
        if ($satirNolar->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Satir numarasi bulunamadi.']);
        }

        $yeniDurum = $payload['yeniDurum'] ?? 'StokKarsilandi';
        $yeniAktif = isset($payload['yeniAktif'])
            ? intval($payload['yeniAktif'])
            : ($yeniDurum === 'StokKarsilandi' ? 0 : 1);

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
                $aktifGorev = $this->countOpenProductionRowsForOrder(
                    intval($kayit->No ?? 0),
                    intval($kayit->EslesenUrunNo ?? 0),
                    (string) ($kayit->EslesenUrunTur ?? ''),
                    intval($kayit->GorevNo ?? 0)
                );

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
                        $stockBefore = DB::table('tbBolumAraStok')
                            ->where('AraUrunAdiNo', $araUrunNo)
                            ->lockForUpdate()
                            ->first();
                        DB::table('tbBolumAraStok')
                            ->where('AraUrunAdiNo', $araUrunNo)
                            ->update([
                                'Adet' => DB::raw('CASE WHEN Adet >= ' . intval($kayit->Adet) . ' THEN Adet - ' . intval($kayit->Adet) . ' ELSE 0 END'),
                            ]);
                        $stockAfter = $stockBefore
                            ? DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first()
                            : null;

                        $this->logStockMovement($stockBefore, $stockAfter, [
                            'movement_type' => 'order_auto_stock_out',
                            'source_type' => 'pasif_devam_eden_auto_close',
                            'source_id' => $kayit->No,
                            'order_item_no' => intval($kayit->No ?? 0),
                            'work_order_no' => intval($kayit->GorevNo ?? 0) > 0 ? intval($kayit->GorevNo) : null,
                            'description' => 'Pasif devam eden sipariş kapanırken stoktan otomatik düşüldü.',
                            'metadata' => [
                                'requested_quantity' => intval($kayit->Adet ?? 0),
                            ],
                        ]);
                    }

                    $autoCompleted++;
                });
            }

            $rows = DB::table('tbSiparisSatir')
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
                ]);

            $groupedRows = $rows->groupBy(function ($row) {
                return (string) ($row->EslesenUrunTur ?? '') . '|' . intval($row->EslesenUrunNo ?? 0);
            });

            $itemsByNo = [];

            foreach ($groupedRows as $groupRows) {
                $sample = $groupRows->first();
                $eslesenUrunNo = intval($sample->EslesenUrunNo ?? 0);
                $eslesenUrunTur = (string) ($sample->EslesenUrunTur ?? '');
                $globalPipelineSteps = $this->buildPipelineSteps($eslesenUrunNo, $eslesenUrunTur);
                $allocationOrders = $this->findActiveOrderRowsForProduct($eslesenUrunNo, $eslesenUrunTur);

                if ($allocationOrders->isEmpty()) {
                    $allocationOrders = $groupRows->map(function ($row) {
                        return (object) [
                            'No' => intval($row->No ?? 0),
                            'Adet' => intval($row->Adet ?? 0),
                            'IsEmriTarihi' => $row->IsEmriTarihi ?? null,
                        ];
                    })->values();
                }

                foreach ($groupRows as $row) {
                    $gorevNo = intval($row->GorevNo ?? 0);
                    $trackedPipelineSteps = $this->buildTrackedPipelineSteps(intval($row->No ?? 0), $eslesenUrunNo, $eslesenUrunTur);
                    $pipelineSteps = $trackedPipelineSteps;
                    $allocationNote = '';

                    if (empty($pipelineSteps)) {
                        $pipelineSteps = $globalPipelineSteps;
                    }

                    if (
                        empty($trackedPipelineSteps)
                        && $eslesenUrunTur === 'Nihai'
                        && $eslesenUrunNo > 0
                        && !empty($globalPipelineSteps)
                        && $allocationOrders->count() > 1
                    ) {
                        $pipelineSteps = $this->buildOrderSpecificPipelineSteps(
                            $globalPipelineSteps,
                            $allocationOrders,
                            intval($row->No)
                        );

                        $toplamPaylasilanAdet = intval($allocationOrders->sum(function ($order) {
                            return intval($order->Adet ?? 0);
                        }));

                        if ($toplamPaylasilanAdet > intval($row->Adet ?? 0)) {
                            $allocationNote = 'Aynı ürünün aktif siparişleri arasında, sipariş adedine göre paylaştırılmış görünüm gösteriliyor.';
                        }
                    }

                    if (!empty($pipelineSteps)) {
                        [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler, $yuzde] = $this->summarizePipelineProgress($pipelineSteps);
                    } else {
                        [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler] = $this->buildPasifDevamEdenProgress($eslesenUrunNo, $eslesenUrunTur, $gorevNo);
                        $yuzde = ($bitenGorevSayisi > 0 || $aktifGorevSayisi > 0)
                            ? (int) round(($bitenGorevSayisi * 100) / ($bitenGorevSayisi + $aktifGorevSayisi))
                            : 0;
                    }

                    $itemsByNo[intval($row->No)] = [
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
                        'pipelineSteps' => $pipelineSteps,
                        'allocationNote' => $allocationNote,
                    ];
                }
            }

            $items = $rows->map(function ($row) use ($itemsByNo) {
                return $itemsByNo[intval($row->No)] ?? null;
            })->filter()->values();

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

    private function getOrderPipeline(Request $request)
    {
        try {
            $satirNo = intval($request->input('satirNo', $request->input('no', 0)));
            if ($satirNo <= 0) {
                return response()->json(['success' => false, 'message' => 'Geçersiz sipariş satırı.']);
            }

            $row = DB::table('tbSiparisSatir')
                ->where('No', $satirNo)
                ->first([
                    'No',
                    'SiparisNo',
                    'Pazaryeri',
                    'Musteri',
                    'UrunAdi',
                    'Adet',
                    'Durum',
                    'EslesenUrunNo',
                    'EslesenUrunTur',
                    'IsEmriTarihi',
                    'GorevNo',
                ]);

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Sipariş bulunamadı.']);
            }

            $eslesenUrunNo = intval($row->EslesenUrunNo ?? 0);
            $eslesenUrunTur = (string) ($row->EslesenUrunTur ?? '');
            $pipelineSteps = $this->buildTrackedPipelineSteps($satirNo, $eslesenUrunNo, $eslesenUrunTur);
            $allocationNote = '';

            if (empty($pipelineSteps)) {
                $globalPipelineSteps = $this->buildPipelineSteps($eslesenUrunNo, $eslesenUrunTur);
                $allocationOrders = $this->findActiveOrderRowsForProduct($eslesenUrunNo, $eslesenUrunTur);

                if (
                    $eslesenUrunTur === 'Nihai'
                    && $eslesenUrunNo > 0
                    && !empty($globalPipelineSteps)
                    && $allocationOrders->count() > 1
                ) {
                    $pipelineSteps = $this->buildOrderSpecificPipelineSteps(
                        $globalPipelineSteps,
                        $allocationOrders,
                        $satirNo
                    );

                    $toplamPaylasilanAdet = intval($allocationOrders->sum(function ($order) {
                        return intval($order->Adet ?? 0);
                    }));

                    if ($toplamPaylasilanAdet > intval($row->Adet ?? 0)) {
                        $allocationNote = 'Aynı üründeki açık iş emirleri arasında sipariş adedine göre paylaştırılmış görünüm.';
                    }
                } else {
                    $pipelineSteps = $globalPipelineSteps;
                }
            }

            if (!empty($pipelineSteps)) {
                [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler, $yuzde] = $this->summarizePipelineProgress($pipelineSteps);
            } else {
                [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler] = $this->buildPasifDevamEdenProgress(
                    $eslesenUrunNo,
                    $eslesenUrunTur,
                    intval($row->GorevNo ?? 0)
                );
                $yuzde = ($bitenGorevSayisi > 0 || $aktifGorevSayisi > 0)
                    ? (int) round(($bitenGorevSayisi * 100) / ($bitenGorevSayisi + $aktifGorevSayisi))
                    : 0;
            }

            return response()->json([
                'success' => true,
                'order' => [
                    'no' => intval($row->No),
                    'siparisNo' => (string) ($row->SiparisNo ?? ''),
                    'pazaryeri' => (string) ($row->Pazaryeri ?? ''),
                    'musteri' => (string) ($row->Musteri ?? ''),
                    'urunAdi' => (string) ($row->UrunAdi ?? ''),
                    'adet' => intval($row->Adet ?? 0),
                    'durum' => (string) ($row->Durum ?? ''),
                    'isEmriTarihi' => $this->formatLegacyDateTime($row->IsEmriTarihi),
                ],
                'progress' => [
                    'aktifGorevSayisi' => $aktifGorevSayisi,
                    'bitenGorevSayisi' => $bitenGorevSayisi,
                    'aktifBolumler' => $aktifBolumler,
                    'yuzde' => $yuzde,
                    'gecenSure' => $this->humanElapsedSince($row->IsEmriTarihi),
                ],
                'pipelineSteps' => $pipelineSteps,
                'allocationNote' => $allocationNote,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
        }
    }

    private function getProductionDetail(Request $request)
    {
        try {
            $satirNo = intval($request->input('satirNo', $request->input('no', 0)));
            if ($satirNo <= 0) {
                return response()->json(['success' => false, 'message' => 'Geçersiz sipariş satırı.']);
            }

            $row = DB::table('tbSiparisSatir')
                ->where('No', $satirNo)
                ->first([
                    'No',
                    'SiparisNo',
                    'Pazaryeri',
                    'Musteri',
                    'UrunAdi',
                    'Adet',
                    'Durum',
                    'EslesenUrunNo',
                    'EslesenUrunTur',
                    'BagliOlduguOzelUretimNo',
                ]);

            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Sipariş bulunamadı.']);
            }

            $eslesenUrunNo = intval($row->EslesenUrunNo ?? 0);
            $eslesenUrunTur = (string) ($row->EslesenUrunTur ?? '');
            $araUrunNo = $this->resolveMatchedAraUrunNo($eslesenUrunNo, $eslesenUrunTur);
            $relatedProductions = $this->buildRelatedSpecialProductionDetails($eslesenUrunNo, $eslesenUrunTur);
            $available = intval(collect($relatedProductions)->sum('available'));
            $reserved = intval(collect($relatedProductions)->sum('reserved'));
            $specialTotal = intval(collect($relatedProductions)->sum('total'));

            if ($this->hasTrackedProductionRows($satirNo)) {
                $trackedSteps = $this->buildTrackedPipelineSteps($satirNo, $eslesenUrunNo, $eslesenUrunTur);
                $stages = $this->buildProductionDetailStagesFromPipeline($trackedSteps);
                $waitingTotal = intval(collect($stages)->sum('waiting'));
                $activeTotal = intval(collect($stages)->sum('active'));
                $readyTotal = intval(collect($stages)->sum('ready'));
                $dateValues = $this->trackedProductionDateValues($satirNo)
                    ->merge(collect($relatedProductions)->pluck('rawDate'))
                    ->filter()
                    ->values()
                    ->all();

                return response()->json([
                    'success' => true,
                    'order' => [
                        'no' => intval($row->No),
                        'siparisNo' => (string) ($row->SiparisNo ?? ''),
                        'pazaryeri' => (string) ($row->Pazaryeri ?? ''),
                        'musteri' => (string) ($row->Musteri ?? ''),
                        'urunAdi' => (string) ($row->UrunAdi ?? ''),
                        'adet' => intval($row->Adet ?? 0),
                        'durum' => (string) ($row->Durum ?? ''),
                        'sistemUrunAdi' => $this->resolveSystemProductName($eslesenUrunNo, $eslesenUrunTur),
                    ],
                    'summary' => [
                        'total' => $waitingTotal + $activeTotal + $readyTotal,
                        'waiting' => $waitingTotal,
                        'active' => $activeTotal,
                        'ready' => $readyTotal,
                        'available' => $available,
                        'reserved' => $reserved,
                        'specialTotal' => $specialTotal,
                        'requested' => intval($row->Adet ?? 0),
                        'lastUpdated' => $this->formatNewestLegacyDateTime($dateValues),
                    ],
                    'stages' => $stages,
                    'relatedProductions' => collect($relatedProductions)->map(function ($item) {
                        unset($item['rawDate']);
                        return $item;
                    })->values()->all(),
                    'source' => 'tracked_order',
                ]);
            }

            if ($araUrunNo <= 0) {
                return response()->json([
                    'success' => true,
                    'order' => [
                        'no' => intval($row->No),
                        'siparisNo' => (string) ($row->SiparisNo ?? ''),
                        'urunAdi' => (string) ($row->UrunAdi ?? ''),
                        'adet' => intval($row->Adet ?? 0),
                        'durum' => (string) ($row->Durum ?? ''),
                        'sistemUrunAdi' => $this->resolveSystemProductName($eslesenUrunNo, $eslesenUrunTur),
                    ],
                    'summary' => [
                        'total' => 0,
                        'waiting' => 0,
                        'active' => 0,
                        'available' => 0,
                        'reserved' => 0,
                        'specialTotal' => 0,
                        'requested' => intval($row->Adet ?? 0),
                        'lastUpdated' => '',
                    ],
                    'stages' => [],
                    'relatedProductions' => [],
                    'message' => 'Bu sipariş için eşleşmiş üretim ürünü bulunamadı.',
                ]);
            }

            $waitingRows = DB::table('tbBolumHavuz as h')
                ->leftJoin('tbBolum as b', 'h.BolumAdiNo', '=', 'b.No')
                ->where('h.AraUrunAdiNo', $araUrunNo)
                ->select(
                    'h.No',
                    'h.BolumAdiNo',
                    'b.BolumAdi',
                    'h.Adet',
                    'h.ToplamAdet',
                    'h.GorevBaslangicTarihi',
                    'h.Aciklama',
                    'h.SiparisSatirNo',
                    'h.SiparisNo'
                )
                ->get();

            $activeRows = DB::table('tbPersonelGorev as pg')
                ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
                ->leftJoin('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
                ->where('pg.AraUrunAdiNo', $araUrunNo)
                ->where(function ($outerQuery) {
                    $outerQuery->where(function ($query) {
                        $query->where('pg.Adet', '>', 0)
                            ->whereRaw($this->pendingApprovalSql('pg.Onay'))
                            ->whereRaw('NOT ' . $this->productionReadyApprovalSql('pg.Onay'));
                    })->orWhere(function ($query) {
                        $query->where('pg.BekleyenAdet', '>', 0)
                            ->whereRaw($this->activeProductionApprovalSql('pg.Onay'));
                    });
                })
                ->select(
                    'pg.No',
                    'pg.BolumAdiNo',
                    'b.BolumAdi',
                    'pg.Adet',
                    'pg.BekleyenAdet',
                    'pg.Onay',
                    'pg.GorevBaslamaTarihi',
                    'pg.PersonelNo',
                    'pg.SiparisSatirNo',
                    'pg.SiparisNo',
                    'p.Ad',
                    'p.Soyad'
                )
                ->get();

            $waitingTotal = intval($waitingRows->sum(function ($item) {
                return intval($item->Adet ?? 0);
            }));
            $activeTotal = intval($activeRows->sum(function ($item) {
                $bekleyen = intval($item->BekleyenAdet ?? 0);
                return $bekleyen > 0 ? $bekleyen : intval($item->Adet ?? 0);
            }));

            $stages = $this->buildProductionDetailStages($waitingRows, $activeRows);
            $dateValues = $waitingRows->pluck('GorevBaslangicTarihi')
                ->merge($activeRows->pluck('GorevBaslamaTarihi'))
                ->merge(collect($relatedProductions)->pluck('rawDate'))
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'order' => [
                    'no' => intval($row->No),
                    'siparisNo' => (string) ($row->SiparisNo ?? ''),
                    'pazaryeri' => (string) ($row->Pazaryeri ?? ''),
                    'musteri' => (string) ($row->Musteri ?? ''),
                    'urunAdi' => (string) ($row->UrunAdi ?? ''),
                    'adet' => intval($row->Adet ?? 0),
                    'durum' => (string) ($row->Durum ?? ''),
                    'sistemUrunAdi' => $this->resolveSystemProductName($eslesenUrunNo, $eslesenUrunTur),
                ],
                'summary' => [
                    'total' => $waitingTotal + $activeTotal,
                    'waiting' => $waitingTotal,
                    'active' => $activeTotal,
                    'available' => $available,
                    'reserved' => $reserved,
                    'specialTotal' => $specialTotal,
                    'requested' => intval($row->Adet ?? 0),
                    'lastUpdated' => $this->formatNewestLegacyDateTime($dateValues),
                ],
                'stages' => $stages,
                'relatedProductions' => collect($relatedProductions)->map(function ($item) {
                    unset($item['rawDate']);
                    return $item;
                })->values()->all(),
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

            $beforeOrder = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
            $mevcut = $beforeOrder->Durum ?? null;
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

            $afterOrder = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
            $this->logCenterEvent([
                'event_type' => 'order_reactivated',
                'aggregate_type' => 'order_item',
                'aggregate_id' => $satirNo,
                'order_item_no' => $satirNo,
                'order_no' => (string) (($afterOrder->SiparisNo ?? '') ?: ($beforeOrder->SiparisNo ?? '')),
                'work_order_no' => intval($afterOrder->GorevNo ?? $beforeOrder->GorevNo ?? 0) > 0
                    ? intval($afterOrder->GorevNo ?? $beforeOrder->GorevNo ?? 0)
                    : null,
                'status_before' => $beforeOrder->Durum ?? null,
                'status_after' => $afterOrder->Durum ?? null,
                'next_step_human' => 'Kayit tekrar aktif. Havuz ve personel gorevleri takibe alinmali.',
                'payload_before' => $this->serializeRecord($beforeOrder),
                'payload_after' => $this->serializeRecord($afterOrder),
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
        app(BomService::class)->restoreTamponFromJson($tamponDusumleriJson);
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

    private function logCenterEvent(array $attributes): void
    {
        try {
            $this->workOrderEventLogger->log($attributes);
        } catch (\Throwable) {
            // Event merkezi ana akisi bozmasin.
        }
    }

    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            $this->stockMovementLogger->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi ana siparis akisini bozmasin.
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

    /**
     * Stoktan düşme ortak iç metot
     */
    private function deductStockForOrder(int $satirNo): string
    {
        try {
            return DB::transaction(function () use ($satirNo) {
                $satir = DB::table('tbSiparisSatir')
                    ->where('No', $satirNo)
                    ->lockForUpdate()
                    ->first();
                if (!$satir) return 'Sipariş bulunamadı.';

                $adet = intval($satir->Adet ?? 0);
                $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
                $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
                $durum = $satir->Durum ?? '';

                if ($durum !== 'UretimBekliyor' && $durum !== 'UretimdenKarsilaniyor') {
                    return 'Sadece \'Üretim Bekliyor\' veya \'Üretime Bağlandı\' (GİED) durumundaki siparişler stoktan düşülebilir.';
                }
                if ($eslesenUrunNo <= 0) return 'Eşleşen ürün bulunamadı.';
                $activeGiedAllocations = $this->activeGiedAllocationsForOrder($satirNo);

                $araUrunNo = 0;
                if ($eslesenUrunTur === 'Nihai') {
                    $urunID = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->value('UrunID');
                    if (empty($urunID)) return 'Ürün adı bulunamadı.';
                    $araUrunNo = DB::table('tbAraUrun')->where('AraUrunAdi', trim($urunID))->value('No') ?? 0;
                } elseif ($eslesenUrunTur === 'Ara') {
                    $araUrunNo = $eslesenUrunNo;
                }

                if ($araUrunNo <= 0) return 'Ara ürün bulunamadı.';

                $stokRows = DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', $araUrunNo)
                    ->where('Adet', '>', 0)
                    ->orderBy('No')
                    ->lockForUpdate()
                    ->get();

                $mevcutStok = intval($stokRows->sum(fn ($row) => intval($row->Adet ?? 0)));
                if ($mevcutStok < $adet) return "Yetersiz stok. Mevcut: {$mevcutStok}, İstenen: {$adet}";

                $remaining = $adet;
                foreach ($stokRows as $stokRow) {
                    if ($remaining <= 0) break;
                    $dusulecek = min($remaining, intval($stokRow->Adet ?? 0));
                    $stockBefore = $stokRow;
                    DB::table('tbBolumAraStok')->where('No', $stokRow->No)->update([
                        'Adet' => DB::raw("Adet - {$dusulecek}"),
                        'TamponMiktar' => DB::raw("CASE WHEN TamponMiktar - {$dusulecek} < 0 THEN 0 ELSE TamponMiktar - {$dusulecek} END")
                    ]);
                    $stockAfter = DB::table('tbBolumAraStok')->where('No', $stokRow->No)->first();
                    $this->logStockMovement($stockBefore, $stockAfter, [
                        'movement_type' => 'order_stock_out',
                        'source_type' => 'order_item',
                        'source_id' => $satirNo,
                        'order_item_no' => $satirNo,
                        'order_no' => (string) ($satir->SiparisNo ?? ''),
                        'work_order_no' => intval($satir->GorevNo ?? 0) > 0 ? intval($satir->GorevNo) : null,
                        'description' => 'Sipariş stoktan karşılandığı için stok çıkışı yapıldı.',
                        'metadata' => [
                            'deducted_quantity' => $dusulecek,
                            'order_quantity' => $adet,
                            'matched_product_no' => $eslesenUrunNo,
                            'matched_product_type' => $eslesenUrunTur,
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
                $this->clearGiedAllocations($satirNo);

                $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                $this->logCenterEvent([
                    'event_type' => 'stock_deducted',
                    'aggregate_type' => 'order_item',
                    'aggregate_id' => $satirNo,
                    'order_item_no' => $satirNo,
                    'order_no' => (string) (($updatedSatir->SiparisNo ?? '') ?: ($satir->SiparisNo ?? '')),
                    'work_order_no' => intval($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0) > 0
                        ? intval($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0)
                        : null,
                    'status_before' => $durum,
                    'status_after' => $updatedSatir->Durum ?? 'StokKarsilandi',
                    'special_production_no' => intval($satir->BagliOlduguOzelUretimNo ?? 0) > 0
                        ? intval($satir->BagliOlduguOzelUretimNo)
                        : null,
                    'next_step_human' => 'Kayit stoktan karsilandi; sevk ve son kontrol asamalari kontrol edilmeli.',
                    'payload_before' => $this->serializeRecord($satir),
                    'payload_after' => $this->serializeRecord($updatedSatir),
                    'context' => [
                        'ara_urun_no' => $araUrunNo,
                        'deducted_amount' => $adet,
                        'gied_allocations' => $activeGiedAllocations,
                    ],
                ]);

                $this->checkStockThreshold($araUrunNo);

                return 'OK';
            });
        } catch (\Exception $e) {
            return 'Hata: ' . $e->getMessage();
        }
    }

    /**
     * Yanlış stoktan düşülen siparişi geri alır.
     * Stok hareket kaydındaki satır ve miktarları kullanır; bu yüzden aynı stok satırına iade eder.
     */
    private function undoStockDeductionForOrder(int $satirNo): array
    {
        if ($satirNo <= 0) {
            return ['success' => false, 'message' => 'Geçersiz sipariş satırı.'];
        }

        try {
            return DB::transaction(function () use ($satirNo) {
                $satir = DB::table('tbSiparisSatir')
                    ->where('No', $satirNo)
                    ->lockForUpdate()
                    ->first();

                if (!$satir) {
                    return ['success' => false, 'message' => 'Sipariş bulunamadı.'];
                }

                if (($satir->Durum ?? '') !== 'StokKarsilandi') {
                    return ['success' => false, 'message' => 'Sadece stoktan karşılanmış siparişler geri alınabilir.'];
                }

                if (!Schema::hasTable('stock_movements')) {
                    return ['success' => false, 'message' => 'Stok hareket kaydı bulunamadığı için otomatik geri alma yapılamıyor.'];
                }

                $lastUndoAt = $this->latestStockUndoTimeForOrder($satirNo);
                $movementsQuery = DB::table('stock_movements')
                    ->where('order_item_no', $satirNo)
                    ->where('movement_type', 'order_stock_out')
                    ->where('quantity_delta', '<', 0)
                    ->orderBy('id');

                if ($lastUndoAt) {
                    $movementsQuery->where('happened_at', '>', $lastUndoAt);
                }

                $movements = $movementsQuery->get();
                if ($movements->isEmpty()) {
                    return ['success' => false, 'message' => 'Bu sipariş için geri alınabilir stok çıkış kaydı bulunamadı.'];
                }

                $stockDeductEvent = $this->latestStockDeductedEventForOrder($satirNo, $lastUndoAt);
                $payloadBefore = $this->decodeJsonPayload(data_get($stockDeductEvent, 'payload_before'));
                $eventContext = $this->decodeJsonPayload(data_get($stockDeductEvent, 'context'));
                $previousAllocations = collect($eventContext['gied_allocations'] ?? [])
                    ->map(function ($allocation) {
                        return [
                            'special_production_no' => intval($allocation['special_production_no'] ?? $allocation['ozelUretimNo'] ?? 0),
                            'quantity' => intval($allocation['quantity'] ?? $allocation['adet'] ?? 0),
                        ];
                    })
                    ->filter(fn ($allocation) => $allocation['special_production_no'] > 0 && $allocation['quantity'] > 0)
                    ->values()
                    ->all();
                $previousStatus = (string) (data_get($stockDeductEvent, 'status_before') ?: ($payloadBefore['Durum'] ?? 'UretimBekliyor'));
                if (!in_array($previousStatus, ['UretimBekliyor', 'UretimdenKarsilaniyor'], true)) {
                    $previousStatus = 'UretimBekliyor';
                }

                $previousSpecialNo = intval($payloadBefore['BagliOlduguOzelUretimNo'] ?? data_get($stockDeductEvent, 'special_production_no') ?? 0);
                if (!empty($previousAllocations)) {
                    $previousSpecialNo = intval($previousAllocations[0]['special_production_no'] ?? $previousSpecialNo);
                }
                $restoredTotal = 0;

                foreach ($movements as $movement) {
                    $stockRowNo = intval($movement->stock_row_no ?? 0);
                    $restoreQuantity = abs(intval($movement->quantity_delta ?? 0));
                    $restoreBuffer = max(0, -intval($movement->buffer_delta ?? 0));

                    if ($stockRowNo <= 0 || $restoreQuantity <= 0) {
                        continue;
                    }

                    $stockBefore = DB::table('tbBolumAraStok')
                        ->where('No', $stockRowNo)
                        ->lockForUpdate()
                        ->first();

                    if (!$stockBefore) {
                        throw new \Exception("Stok satırı bulunamadı: {$stockRowNo}");
                    }

                    DB::table('tbBolumAraStok')->where('No', $stockRowNo)->update([
                        'Adet' => DB::raw("Adet + {$restoreQuantity}"),
                        'TamponMiktar' => DB::raw("TamponMiktar + {$restoreBuffer}"),
                    ]);

                    $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first();
                    $this->logStockMovement($stockBefore, $stockAfter, [
                        'movement_type' => 'order_stock_out_reversed',
                        'source_type' => 'order_stock_undo',
                        'source_id' => $satirNo,
                        'order_item_no' => $satirNo,
                        'order_no' => (string) ($satir->SiparisNo ?? ''),
                        'work_order_no' => intval($satir->GorevNo ?? 0) > 0 ? intval($satir->GorevNo) : null,
                        'description' => 'Yanlış stoktan düşme işlemi geri alındığı için stok iade edildi.',
                        'metadata' => [
                            'original_movement_id' => intval($movement->id ?? 0),
                            'restored_quantity' => $restoreQuantity,
                            'restored_buffer' => $restoreBuffer,
                        ],
                    ]);

                    $restoredTotal += $restoreQuantity;
                }

                if ($restoredTotal <= 0) {
                    return ['success' => false, 'message' => 'Geri alınacak stok miktarı hesaplanamadı.'];
                }

                DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                    'Durum' => $previousStatus,
                    'Aktif' => 1,
                    'BagliOlduguOzelUretimNo' => $previousSpecialNo > 0 ? $previousSpecialNo : null,
                    'GuncellemeTarihi' => now(),
                ]);
                if (!empty($previousAllocations)) {
                    $this->replaceGiedAllocations($satirNo, $previousAllocations);
                }

                $updatedSatir = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();
                $this->logCenterEvent([
                    'event_type' => 'stock_deduction_reversed',
                    'event_group' => 'stock',
                    'aggregate_type' => 'order_item',
                    'aggregate_id' => $satirNo,
                    'order_item_no' => $satirNo,
                    'order_no' => (string) (($updatedSatir->SiparisNo ?? '') ?: ($satir->SiparisNo ?? '')),
                    'work_order_no' => intval($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0) > 0
                        ? intval($updatedSatir->GorevNo ?? $satir->GorevNo ?? 0)
                        : null,
                    'special_production_no' => $previousSpecialNo > 0 ? $previousSpecialNo : null,
                    'status_before' => $satir->Durum ?? 'StokKarsilandi',
                    'status_after' => $updatedSatir->Durum ?? $previousStatus,
                    'title_human' => 'Stoktan dusme geri alindi',
                    'summary_human' => 'Yanlış stoktan düşme işlemi geri alındı ve stok siparişe iade edildi.',
                    'next_step_human' => $previousStatus === 'UretimdenKarsilaniyor'
                        ? 'Siparis tekrar bagli ozel uretimin sonucunu bekliyor.'
                        : 'Siparis tekrar is emri veya stoktan karsilama icin bekliyor.',
                    'payload_before' => $this->serializeRecord($satir),
                    'payload_after' => $this->serializeRecord($updatedSatir),
                    'context' => [
                        'restored_amount' => $restoredTotal,
                        'reversed_movement_ids' => $movements->pluck('id')->map(fn ($id) => intval($id))->values()->all(),
                        'gied_allocations' => $previousAllocations,
                    ],
                ]);

                return [
                    'success' => true,
                    'restored' => $restoredTotal,
                    'status' => $updatedSatir->Durum ?? $previousStatus,
                    'message' => "Stoktan düşme geri alındı. {$restoredTotal} adet stok iade edildi; sipariş durumu {$previousStatus} yapıldı.",
                ];
            });
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Hata: ' . $e->getMessage()];
        }
    }

    private function latestStockUndoTimeForOrder(int $satirNo): ?string
    {
        if (!Schema::hasTable('work_order_events')) {
            return null;
        }

        $value = DB::table('work_order_events')
            ->where('order_item_no', $satirNo)
            ->where('event_type', 'stock_deduction_reversed')
            ->max('happened_at');

        return $value ? (string) $value : null;
    }

    private function latestStockDeductedEventForOrder(int $satirNo, ?string $after = null): ?object
    {
        if (!Schema::hasTable('work_order_events')) {
            return null;
        }

        $query = DB::table('work_order_events')
            ->where('order_item_no', $satirNo)
            ->where('event_type', 'stock_deducted');

        if ($after) {
            $query->where('happened_at', '>', $after);
        }

        return $query->orderByDesc('id')->first();
    }

    private function decodeJsonPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return json_decode(json_encode($payload, JSON_UNESCAPED_UNICODE), true) ?: [];
        }

        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode((string) $payload, true);

        return is_array($decoded) ? $decoded : [];
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

            $order = DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->first();
            if (!$order) {
                throw new \Exception('Sipariş satırı bulunamadı.');
            }

            if (intval($order->Aktif ?? 0) !== 1 || ($order->Durum ?? '') !== 'UretimBekliyor') {
                throw new \Exception('Eşleşme sadece üretim bekleyen aktif siparişlerde değiştirilebilir.');
            }

            if (!empty($order->BagliOlduguOzelUretimNo)) {
                throw new \Exception('GİED ile rezerve edilmiş siparişin eşleşmesi değiştirilemez.');
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
            $urunAdi = $order->UrunAdi ?? '';

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
                    ->where('Aktif', 1)
                    ->where('Durum', 'UretimBekliyor')
                    ->whereNull('BagliOlduguOzelUretimNo')
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

    private function clearOrderMatch(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? $data['no'] ?? 0);
            $deleteCache = !array_key_exists('deleteCache', $data) || (bool) $data['deleteCache'];

            if ($siparisSatirNo <= 0) {
                throw new \Exception('Geçersiz sipariş satırı.');
            }

            $result = $this->orderSync->clearOrderMatch($siparisSatirNo, $deleteCache);

            return response()->json([
                'success' => true,
                'message' => 'Eşleşme iptal edildi.',
                'deletedChildren' => (int) ($result['deletedChildren'] ?? 0),
                'deletedCache' => (int) ($result['deletedCache'] ?? 0),
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

    private function buildProductionDetailStages($waitingRows, $activeRows): array
    {
        $stages = [];
        $ensureStage = function ($bolumNo, $bolumAdi) use (&$stages) {
            $key = intval($bolumNo ?? 0);
            if (!isset($stages[$key])) {
                $stages[$key] = [
                    'bolumNo' => $key,
                    'bolumAdi' => trim((string) ($bolumAdi ?: 'Bölüm belirtilmemiş')),
                    'waiting' => 0,
                    'active' => 0,
                    'total' => 0,
                    'personnel' => [],
                    'status' => 'waiting',
                ];
            }

            return $key;
        };

        foreach ($waitingRows as $row) {
            $key = $ensureStage($row->BolumAdiNo ?? 0, $row->BolumAdi ?? '');
            $adet = intval($row->Adet ?? 0);
            $stages[$key]['waiting'] += $adet;
            $stages[$key]['total'] += $adet;
        }

        foreach ($activeRows as $row) {
            $key = $ensureStage($row->BolumAdiNo ?? 0, $row->BolumAdi ?? '');
            $bekleyen = intval($row->BekleyenAdet ?? 0);
            $adet = $bekleyen > 0 ? $bekleyen : intval($row->Adet ?? 0);
            $personelAd = trim((string) (($row->Ad ?? '') . ' ' . ($row->Soyad ?? '')));

            $stages[$key]['active'] += $adet;
            $stages[$key]['total'] += $adet;
            $stages[$key]['status'] = 'active';

            if ($personelAd !== '' && !in_array($personelAd, $stages[$key]['personnel'], true)) {
                $stages[$key]['personnel'][] = $personelAd;
            }
        }

        usort($stages, function ($left, $right) {
            return intval($left['bolumNo'] ?? 0) <=> intval($right['bolumNo'] ?? 0);
        });

        return array_values($stages);
    }

    private function buildProductionDetailStagesFromPipeline(array $pipelineSteps): array
    {
        return collect($pipelineSteps)
            ->filter(fn ($step) => in_array((string) ($step['status'] ?? ''), ['uretimde', 'hazir', 'bekliyor'], true))
            ->map(function ($step) {
                $status = (string) ($step['status'] ?? '');
                $adet = max(0, intval($step['adet'] ?? 0));
                $toplamAdet = max(0, intval($step['toplamAdet'] ?? 0));
                $personelAd = trim((string) ($step['personelAd'] ?? ''));

                $active = $status === 'uretimde' ? $adet : 0;
                $ready = $status === 'hazir' ? $adet : 0;
                $waiting = $status === 'bekliyor' ? $adet : 0;
                $total = $active + $ready + $waiting;

                if ($total <= 0 && $toplamAdet > 0) {
                    $total = $toplamAdet;
                }

                return [
                    'bolumNo' => intval($step['bolumNo'] ?? 0),
                    'bolumAdi' => trim((string) ($step['bolumAdi'] ?? 'Bölüm belirtilmemiş')),
                    'waiting' => $waiting,
                    'ready' => $ready,
                    'active' => $active,
                    'total' => $total,
                    'personnel' => $personelAd !== '' ? [$personelAd] : [],
                    'status' => match ($status) {
                        'uretimde' => 'active',
                        'hazir' => 'ready',
                        default => 'waiting',
                    },
                    'detail' => (string) ($step['detay'] ?? ''),
                ];
            })
            ->sortBy(fn ($stage) => intval($stage['bolumNo'] ?? 0))
            ->values()
            ->all();
    }

    private function trackedProductionDateValues(int $siparisSatirNo)
    {
        if ($siparisSatirNo <= 0) {
            return collect();
        }

        return collect()
            ->merge(DB::table('tbBolumHavuz')
                ->where('SiparisSatirNo', $siparisSatirNo)
                ->pluck('GorevBaslangicTarihi'))
            ->merge(DB::table('tbPersonelGorev')
                ->where('SiparisSatirNo', $siparisSatirNo)
                ->pluck('GorevBaslamaTarihi'))
            ->merge(DB::table('tbGorevler')
                ->where('SiparisSatirNo', $siparisSatirNo)
                ->pluck('GorevBitisTarihi'));
    }

    private function buildSpecialProductionCapacityMap(array $orders): array
    {
        $pairs = collect($orders)
            ->map(function ($order) {
                $productNo = intval($order['eslesenUrunNo'] ?? 0);
                $productType = trim((string) ($order['eslesenUrunTur'] ?? ''));

                return $productNo > 0 && $productType !== ''
                    ? ['no' => $productNo, 'type' => $productType, 'key' => $this->specialProductionCapacityKey($productNo, $productType)]
                    : null;
            })
            ->filter()
            ->unique('key')
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $productionRows = DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where(function ($query) {
                $query->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
                    ->orWhere('sp.Musteri', 'like', 'OZEL URETIM%');
            })
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->where(function ($query) {
                $query->whereRaw('IFNULL(sp.GorevNo, 0) > 0')
                    ->orWhereIn('sp.Durum', ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor']);
            })
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($pairQuery) use ($pair) {
                        $pairQuery->where('sp.EslesenUrunNo', $pair['no'])
                            ->where('sp.EslesenUrunTur', $pair['type']);
                    });
                }
            })
            ->get([
                'sp.No',
                'sp.EslesenUrunNo',
                'sp.EslesenUrunTur',
                'sp.Adet',
            ]);

        if ($productionRows->isEmpty()) {
            return [];
        }

        $productionNos = $productionRows
            ->pluck('No')
            ->map(fn ($no) => intval($no))
            ->filter(fn ($no) => $no > 0)
            ->values()
            ->all();

        $reservedMap = $this->specialProductionReservedQuantities($productionNos);

        $capacity = [];
        foreach ($productionRows as $row) {
            $key = $this->specialProductionCapacityKey(
                intval($row->EslesenUrunNo ?? 0),
                (string) ($row->EslesenUrunTur ?? '')
            );
            if ($key === '') {
                continue;
            }

            $total = intval($row->Adet ?? 0);
            $reserved = intval($reservedMap[intval($row->No ?? 0)] ?? 0);
            if (!isset($capacity[$key])) {
                $capacity[$key] = [
                    'total' => 0,
                    'reserved' => 0,
                    'available' => 0,
                ];
            }

            $capacity[$key]['total'] += $total;
            $capacity[$key]['reserved'] += $reserved;
            $capacity[$key]['available'] += max(0, $total - $reserved);
        }

        return $capacity;
    }

    private function specialProductionCapacityKey(int $productNo, string $productType): string
    {
        $productType = trim($productType);
        if ($productNo <= 0 || $productType === '') {
            return '';
        }

        return $productType . ':' . $productNo;
    }

    private function buildRelatedSpecialProductionDetails(int $eslesenUrunNo, string $eslesenUrunTur): array
    {
        if ($eslesenUrunNo <= 0 || trim($eslesenUrunTur) === '') {
            return [];
        }

        $rows = DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
            ->where('sp.EslesenUrunNo', $eslesenUrunNo)
            ->where('sp.EslesenUrunTur', $eslesenUrunTur)
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->orderByRaw('CASE WHEN sp.IsEmriTarihi IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sp.IsEmriTarihi')
            ->orderBy('sp.No')
            ->get([
                'sp.No',
                'sp.SiparisNo',
                'sp.UrunAdi',
                'sp.Adet',
                'sp.Durum',
                'sp.IsEmriTarihi',
                'sp.GuncellemeTarihi',
                'sp.YuklemeTarihi',
            ]);

        $reservedMap = $this->specialProductionReservedQuantities(
            $rows->pluck('No')->map(fn ($no) => intval($no))->filter()->values()->all()
        );

        return $rows->map(function ($row) use ($reservedMap) {
            $reserved = intval($reservedMap[intval($row->No ?? 0)] ?? 0);
            $total = intval($row->Adet ?? 0);
            $rawDate = $row->IsEmriTarihi ?: ($row->GuncellemeTarihi ?: $row->YuklemeTarihi);

            return [
                'no' => intval($row->No ?? 0),
                'siparisNo' => (string) ($row->SiparisNo ?? ''),
                'urunAdi' => (string) ($row->UrunAdi ?? ''),
                'total' => $total,
                'reserved' => $reserved,
                'available' => max(0, $total - $reserved),
                'durum' => (string) ($row->Durum ?? ''),
                'isEmriTarihi' => $this->formatLegacyDateTime($row->IsEmriTarihi),
                'rawDate' => $rawDate,
            ];
        })->values()->all();
    }

    private function buildSpecialProductionAllocationPlan(
        int $eslesenUrunNo,
        string $eslesenUrunTur,
        int $requestedQuantity,
        ?int $preferredSpecialProductionNo = null,
        bool $requirePreferred = false,
        bool $lockForUpdate = false
    ): array {
        if ($eslesenUrunNo <= 0 || trim($eslesenUrunTur) === '' || $requestedQuantity <= 0) {
            return ['rows' => collect(), 'allocations' => [], 'total_available' => 0];
        }

        $query = DB::table('tbSiparisSatir as sp')
            ->where('sp.Aktif', 1)
            ->where(function ($query) {
                $query->where('sp.Musteri', 'like', 'ÖZEL ÜRETİM%')
                    ->orWhere('sp.Musteri', 'like', 'OZEL URETIM%');
            })
            ->where('sp.EslesenUrunNo', $eslesenUrunNo)
            ->where('sp.EslesenUrunTur', $eslesenUrunTur)
            ->whereNotIn('sp.Durum', ['Pasif', 'StokKarsilandi'])
            ->where(function ($query) {
                $query->whereRaw('IFNULL(sp.GorevNo, 0) > 0')
                    ->orWhereIn('sp.Durum', ['IsEmriVerildi', 'PasifDevamEden', 'UretimdenKarsilaniyor']);
            })
            ->orderByRaw('COALESCE(sp.IsEmriTarihi, sp.GuncellemeTarihi, sp.YuklemeTarihi) ASC')
            ->orderBy('sp.No');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $rows = $query->get([
            'sp.No',
            'sp.SiparisNo',
            'sp.UrunAdi',
            'sp.Adet as ToplamAdet',
            'sp.Durum',
            'sp.IsEmriTarihi',
            'sp.GorevNo',
        ]);

        if ($rows->isEmpty()) {
            return ['rows' => collect(), 'allocations' => [], 'total_available' => 0];
        }

        $productionNos = $rows->pluck('No')->map(fn ($no) => intval($no))->filter()->values()->all();
        $reservedMap = $this->specialProductionReservedQuantities($productionNos);

        $rows = $rows->map(function ($row) use ($reservedMap) {
            $total = intval($row->ToplamAdet ?? 0);
            $reserved = intval($reservedMap[intval($row->No ?? 0)] ?? 0);
            $row->RezerveAdet = $reserved;
            $row->BostaAdet = max(0, $total - $reserved);

            return $row;
        });

        $preferredSpecialProductionNo = intval($preferredSpecialProductionNo ?? 0);
        if ($preferredSpecialProductionNo > 0) {
            $preferredRow = $rows->firstWhere('No', $preferredSpecialProductionNo);
            if (!$preferredRow && $requirePreferred) {
                throw new \Exception('Seçilen özel üretim sipariş ile aynı ürüne ait değil veya rezervasyona uygun değil.');
            }
            if ($requirePreferred && intval($preferredRow->BostaAdet ?? 0) <= 0) {
                throw new \Exception('Seçilen özel üretimin boş kapasitesi yok.');
            }

            $rows = $rows
                ->filter(fn ($row) => intval($row->No ?? 0) === $preferredSpecialProductionNo)
                ->concat($rows->reject(fn ($row) => intval($row->No ?? 0) === $preferredSpecialProductionNo))
                ->values();
        }

        $totalAvailable = intval($rows->sum(fn ($row) => intval($row->BostaAdet ?? 0)));
        $remaining = $requestedQuantity;
        $allocations = [];

        foreach ($rows as $row) {
            if ($remaining <= 0) {
                break;
            }

            $available = intval($row->BostaAdet ?? 0);
            if ($available <= 0) {
                continue;
            }

            $quantity = min($remaining, $available);
            $allocations[] = [
                'special_production_no' => intval($row->No ?? 0),
                'quantity' => $quantity,
                'available_before' => $available,
            ];
            $remaining -= $quantity;
        }

        return [
            'rows' => $rows,
            'allocations' => $remaining <= 0 ? $allocations : [],
            'total_available' => $totalAvailable,
        ];
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

    private function specialProductionLinkedOrderRows(int $specialProductionNo)
    {
        if ($specialProductionNo <= 0) {
            return collect();
        }

        $rows = collect();
        $ordersWithAllocationRows = [];

        if ($this->supportsSplitGiedAllocations()) {
            $allocationRows = DB::table(self::GIED_ALLOCATION_TABLE . ' as r')
                ->join('tbSiparisSatir as b', 'b.No', '=', 'r.SiparisSatirNo')
                ->where('r.OzelUretimSatirNo', $specialProductionNo)
                ->where('r.Aktif', 1)
                ->where('b.Aktif', 1)
                ->whereNotIn('b.Durum', ['Pasif', 'StokKarsilandi'])
                ->select(
                    'b.No',
                    'b.SiparisNo',
                    'b.Pazaryeri',
                    'b.Magaza',
                    'b.Musteri',
                    'b.UrunAdi',
                    'b.Adet',
                    DB::raw('b.Adet AS SiparisAdet'),
                    DB::raw('r.Adet AS RezervasyonAdet'),
                    'b.Durum',
                    'b.SiparisTarihi',
                    'b.KargoSonTeslim',
                    'b.GuncellemeTarihi',
                    'b.GorevNo'
                )
                ->get();

            $rows = $rows->merge($allocationRows);
            $ordersWithAllocationRows = $allocationRows
                ->pluck('No')
                ->map(fn ($no) => intval($no))
                ->unique()
                ->values()
                ->all();
        }

        $legacyQuery = DB::table('tbSiparisSatir')
            ->where('BagliOlduguOzelUretimNo', $specialProductionNo)
            ->where('Aktif', 1)
            ->whereNotIn('Durum', ['Pasif', 'StokKarsilandi']);

        if (!empty($ordersWithAllocationRows)) {
            $legacyQuery->whereNotIn('No', $ordersWithAllocationRows);
        }

        $legacyRows = $legacyQuery
            ->select(
                'No',
                'SiparisNo',
                'Pazaryeri',
                'Magaza',
                'Musteri',
                'UrunAdi',
                'Adet',
                DB::raw('Adet AS SiparisAdet'),
                DB::raw('Adet AS RezervasyonAdet'),
                'Durum',
                'SiparisTarihi',
                'KargoSonTeslim',
                'GuncellemeTarihi',
                'GorevNo'
            )
            ->get();

        return $rows
            ->merge($legacyRows)
            ->sortBy(fn ($row) => (string) ($row->SiparisTarihi ?? '') . '-' . str_pad((string) intval($row->No ?? 0), 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function activeGiedAllocationsForOrder(int $siparisNo): array
    {
        if ($siparisNo <= 0 || !$this->supportsSplitGiedAllocations()) {
            return [];
        }

        return DB::table(self::GIED_ALLOCATION_TABLE)
            ->where('SiparisSatirNo', $siparisNo)
            ->where('Aktif', 1)
            ->orderBy('No')
            ->get()
            ->map(function ($row) {
                return [
                    'special_production_no' => intval($row->OzelUretimSatirNo ?? 0),
                    'quantity' => intval($row->Adet ?? 0),
                ];
            })
            ->filter(fn ($allocation) => $allocation['special_production_no'] > 0 && $allocation['quantity'] > 0)
            ->values()
            ->all();
    }

    private function replaceGiedAllocations(int $siparisNo, array $allocations): void
    {
        $allocations = collect($allocations)
            ->map(function ($allocation) {
                return [
                    'special_production_no' => intval($allocation['special_production_no'] ?? $allocation['ozelUretimNo'] ?? 0),
                    'quantity' => intval($allocation['quantity'] ?? $allocation['adet'] ?? 0),
                ];
            })
            ->filter(fn ($allocation) => $allocation['special_production_no'] > 0 && $allocation['quantity'] > 0)
            ->values()
            ->all();

        if ($siparisNo <= 0 || empty($allocations)) {
            return;
        }

        if (!$this->supportsSplitGiedAllocations()) {
            if (count($allocations) > 1) {
                throw new \Exception('GİED paylaştırma tablosu bulunamadığı için çoklu rezervasyon yazılamıyor.');
            }

            return;
        }

        $this->clearGiedAllocations($siparisNo);
        $now = now();
        $rows = array_map(function ($allocation) use ($siparisNo, $now) {
            return [
                'SiparisSatirNo' => $siparisNo,
                'OzelUretimSatirNo' => $allocation['special_production_no'],
                'Adet' => $allocation['quantity'],
                'Aktif' => 1,
                'OlusturmaTarihi' => $now,
                'GuncellemeTarihi' => $now,
            ];
        }, $allocations);

        DB::table(self::GIED_ALLOCATION_TABLE)->insert($rows);
    }

    private function clearGiedAllocations(int $siparisNo): void
    {
        if ($siparisNo <= 0 || !$this->supportsSplitGiedAllocations()) {
            return;
        }

        DB::table(self::GIED_ALLOCATION_TABLE)
            ->where('SiparisSatirNo', $siparisNo)
            ->where('Aktif', 1)
            ->update([
                'Aktif' => 0,
                'GuncellemeTarihi' => now(),
            ]);
    }

    private function formatNewestLegacyDateTime(array $values): string
    {
        $newest = null;

        foreach ($values as $value) {
            $parsed = $this->parseLegacyDateTimeForComparison($value);
            if ($parsed && (!$newest || $parsed->greaterThan($newest))) {
                $newest = $parsed;
            }
        }

        return $newest ? $newest->format('d.m.Y H:i') : '';
    }

    private function parseLegacyDateTimeForComparison($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd.m.Y H:i', 'd/m/Y H:i', 'd.m.Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $text);
            } catch (\Exception $e) {
                // Try the next legacy format.
            }
        }

        try {
            return Carbon::parse($text);
        } catch (\Exception $e) {
            return null;
        }
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

            return intval(DB::table('tbAraUrun')->where('AraUrunAdi', trim((string) $urunID))->value('No') ?? 0);
        }

        return 0;
    }

    private function resolveMatchedAraUrunNoFromMaps(int $eslesenUrunNo, string $eslesenUrunTur, $urunlerMap, $araUrunMap): int
    {
        if ($eslesenUrunNo <= 0) {
            return 0;
        }

        if (in_array($eslesenUrunTur, ['Ara', 'HamMadde'], true)) {
            return $eslesenUrunNo;
        }

        if ($eslesenUrunTur !== 'Nihai') {
            return 0;
        }

        $urunID = $urunlerMap[$eslesenUrunNo] ?? '';
        if (trim((string) $urunID) === '') {
            return 0;
        }

        return intval($araUrunMap[trim((string) $urunID)] ?? 0);
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
            $aktifGorevSayisi = (int) DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->count()
                + (int) DB::table('tbPersonelGorev')
                    ->where('UrunIDNo', $eslesenUrunNo)
                    ->where(function ($query) {
                        $query->where('Adet', '>', 0)
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->where(function ($query) {
                        $query->where('BekleyenAdet', '>', 0)
                            ->orWhereRaw($this->pendingApprovalSql());
                    })
                    ->count();

            $bitenGorevSayisi = (int) DB::table('tbGorevler')
                ->where('UrunIDNo', $eslesenUrunNo)
                ->where('ToplamAdet', '>', 0)
                ->whereNotNull('PersonelNo')
                ->where('PersonelNo', '>', 0)
                ->count();

            if ($aktifGorevSayisi > 0) {
                $bekleyenBolumler = DB::table('tbBolumHavuz as h')
                    ->join('tbBolum as b', 'h.BolumAdiNo', '=', 'b.No')
                    ->where('h.UrunIDNo', $eslesenUrunNo)
                    ->distinct()
                    ->pluck(DB::raw("CONCAT(b.BolumAdi, ' (Bekliyor)') as label"))
                    ->toArray();

                $uretimdePersonel = DB::table('tbPersonelGorev as pg')
                    ->join('tbPersonel as p', 'pg.PersonelNo', '=', 'p.PersonelNo')
                    ->where('pg.UrunIDNo', $eslesenUrunNo)
                    ->where(function ($query) {
                        $query->where('pg.Adet', '>', 0)
                            ->orWhere('pg.BekleyenAdet', '>', 0);
                    })
                    ->where(function ($query) {
                        $query->where('pg.BekleyenAdet', '>', 0)
                            ->orWhereRaw($this->pendingApprovalSql('pg.Onay'));
                    })
                    ->distinct()
                    ->pluck(DB::raw("CONCAT(p.Ad, ' (Üretimde)') as label"))
                    ->toArray();

                $aktifBolumler = implode(', ', array_merge($bekleyenBolumler, $uretimdePersonel));
            }
        } elseif ($gorevNo > 0) {
            $aktifGorevSayisi = DB::table('tbBolumHavuz')->where('No', $gorevNo)->count();
            $bitenGorevSayisi = DB::table('tbGorevler')
                ->where('No', $gorevNo)
                ->where('ToplamAdet', '>', 0)
                ->whereNotNull('PersonelNo')
                ->where('PersonelNo', '>', 0)
                ->count();
        }

        return [$aktifGorevSayisi, $bitenGorevSayisi, $aktifBolumler];
    }

    /**
     * Görsel üretim pipeline'ı oluşturur.
     * Her bölüm (departman) için durumu hesaplar: tamamlandı / üretimde / bekliyor / başlanmadı
     * Tooltip bilgisi olarak detaylı görev kayıtlarını döndürür.
     */
    private function buildPipelineSteps(int $eslesenUrunNo, string $eslesenUrunTur): array
    {
        if ($eslesenUrunTur !== 'Nihai' || $eslesenUrunNo <= 0) {
            return [];
        }

        $bolumler = DB::table('tbBolum')->orderBy('No')->get();
        if ($bolumler->isEmpty()) {
            return [];
        }

        // Havuzda bekleyen görevler (detaylı)
        $havuzKayitlari = DB::table('tbBolumHavuz')
            ->where('UrunIDNo', $eslesenUrunNo)
            ->select('No', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'Adet', 'ToplamAdet', 'GorevBaslangicTarihi', 'Aciklama')
            ->get();

        $havuz = $havuzKayitlari->groupBy('BolumAdiNo');

        // Aktif üretimdeki görevler (detaylı)
        $personelGorevler = DB::table('tbPersonelGorev')
            ->where('UrunIDNo', $eslesenUrunNo)
            ->where(function ($outerQuery) {
                $outerQuery->where(function ($query) {
                    $query->where('Adet', '>', 0)
                        ->whereRaw($this->pendingApprovalSql())
                        ->whereRaw('NOT ' . $this->productionReadyApprovalSql());
                })->orWhere(function ($query) {
                    $query->where('BekleyenAdet', '>', 0)
                        ->whereRaw($this->activeProductionApprovalSql());
                });
            })
            ->select('No', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'PersonelNo', 'Adet', 'BekleyenAdet', 'Onay', 'GorevBaslamaTarihi')
            ->get();

        $uretimde = $personelGorevler->groupBy('BolumAdiNo');

        // Personel adlarını al
        $personelNolar = $personelGorevler->pluck('PersonelNo')->unique()->values();
        $personelIsimleri = [];
        if ($personelNolar->isNotEmpty()) {
            DB::table('tbPersonel')->whereIn('PersonelNo', $personelNolar)->get()->each(function ($p) use (&$personelIsimleri) {
                $personelIsimleri[$p->PersonelNo] = trim(($p->Ad ?? '') . ' ' . ($p->Soyad ?? ''));
            });
        }

        // Biten görevler (detaylı)
        $bitenKayitlari = DB::table('tbGorevler')
            ->where('UrunIDNo', $eslesenUrunNo)
            ->where('ToplamAdet', '>', 0)
            ->whereNotNull('PersonelNo')
            ->where('PersonelNo', '>', 0)
            ->select('No', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'ToplamAdet', 'GorevBaslamaTarihi', 'GorevBitisTarihi', 'PersonelNo')
            ->get();

        $bitenler = $bitenKayitlari->groupBy('BolumAdiNo');

        // Personel adları (biten görevlerdeki)
        $bitenPersonelNolar = $bitenKayitlari->pluck('PersonelNo')->unique()->filter()->values();
        if ($bitenPersonelNolar->isNotEmpty()) {
            DB::table('tbPersonel')->whereIn('PersonelNo', $bitenPersonelNolar)->get()->each(function ($p) use (&$personelIsimleri) {
                $personelIsimleri[$p->PersonelNo] = trim(($p->Ad ?? '') . ' ' . ($p->Soyad ?? ''));
            });
        }

        $araUrunNolar = $havuzKayitlari->pluck('AraUrunAdiNo')
            ->merge($personelGorevler->pluck('AraUrunAdiNo'))
            ->merge($bitenKayitlari->pluck('AraUrunAdiNo'))
            ->filter()
            ->unique()
            ->values();

        $urunNolar = $havuzKayitlari->pluck('UrunIDNo')
            ->merge($personelGorevler->pluck('UrunIDNo'))
            ->merge($bitenKayitlari->pluck('UrunIDNo'))
            ->filter()
            ->unique()
            ->values();

        $araUrunIsimleri = [];
        if ($araUrunNolar->isNotEmpty()) {
            DB::table('tbAraUrun')
                ->whereIn('No', $araUrunNolar)
                ->select('No', 'AraUrunAdi')
                ->get()
                ->each(function ($row) use (&$araUrunIsimleri) {
                    $araUrunIsimleri[$row->No] = trim((string) ($row->AraUrunAdi ?? ''));
                });
        }

        $urunIsimleri = [];
        if ($urunNolar->isNotEmpty()) {
            DB::table('tbUrunler')
                ->whereIn('No', $urunNolar)
                ->select('No', 'SistemAdi', 'UrunID')
                ->get()
                ->each(function ($row) use (&$urunIsimleri) {
                    $urunIsimleri[$row->No] = trim((string) (($row->SistemAdi ?: $row->UrunID) ?? ''));
                });
        }

        $resolveTaskName = function ($row) use ($araUrunIsimleri, $urunIsimleri): string {
            $araUrunNo = intval($row->AraUrunAdiNo ?? 0);
            if ($araUrunNo > 0 && !empty($araUrunIsimleri[$araUrunNo])) {
                return $araUrunIsimleri[$araUrunNo];
            }

            $urunNo = intval($row->UrunIDNo ?? 0);
            if ($urunNo > 0 && !empty($urunIsimleri[$urunNo])) {
                return $urunIsimleri[$urunNo];
            }

            return 'Görev adı bulunamadı';
        };

        $steps = [];
        foreach ($bolumler as $bolum) {
            $bNo = $bolum->No;
            $bAdi = $bolum->BolumAdi;

            $havuzda = $havuz->get($bNo);
            $aktifUretimde = $uretimde->get($bNo);
            $bitmis = $bitenler->get($bNo);

            $status = 'baslanmadi';
            $statusLabel = 'Başlanmadı';
            $detay = '';
            $adet = 0;
            $toplamAdet = 0;
            $personelAd = '';
            $tooltipLines = [];
            $activeRecords = [];
            $waitingRecords = [];
            $completedRecords = [];

            if ($aktifUretimde && $aktifUretimde->isNotEmpty()) {
                $status = 'uretimde';
                $statusLabel = 'Üretimde';
                $adet = intval($aktifUretimde->sum(fn ($row) => $this->activePersonnelTaskQuantity($row)));
                $ilkPersonel = $aktifUretimde->first();
                $personelAd = $personelIsimleri[$ilkPersonel->PersonelNo] ?? '';
                $detay = $adet . ' adet üretiliyor';

                foreach ($aktifUretimde as $pg) {
                    $pAdi = $personelIsimleri[$pg->PersonelNo] ?? 'Bilinmeyen';
                    $gorevAdi = $resolveTaskName($pg);
                    $tarih = '';
                    try {
                        $tarih = $pg->GorevBaslamaTarihi ? Carbon::parse($pg->GorevBaslamaTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $tarih = (string) ($pg->GorevBaslamaTarihi ?? '');
                    }
                    $activeRecords[] = [
                        'tarih' => $tarih,
                        'personelAd' => $pAdi,
                        'gorevAdi' => $gorevAdi,
                        'adet' => $this->activePersonnelTaskQuantity($pg),
                    ];
                    $tooltipLines[] = ($tarih ? $tarih . ' - ' : '') . $pAdi . ': ' . $gorevAdi . ' / ' . $this->activePersonnelTaskQuantity($pg) . ' adet';
                }
            } elseif ($havuzda && $havuzda->isNotEmpty()) {
                $status = 'bekliyor';
                $statusLabel = 'Bekliyor';
                $adet = $havuzda->sum('Adet');
                $toplamAdet = $havuzda->sum('ToplamAdet');
                $detay = $adet . '/' . $toplamAdet . ' adet havuzda';

                foreach ($havuzda as $h) {
                    $gorevAdi = $resolveTaskName($h);
                    $tarih = '';
                    try {
                        $tarih = $h->GorevBaslangicTarihi ? Carbon::parse($h->GorevBaslangicTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $tarih = (string) ($h->GorevBaslangicTarihi ?? '');
                    }
                    $waitingRecords[] = [
                        'tarih' => $tarih,
                        'gorevAdi' => $gorevAdi,
                        'adet' => intval($h->Adet ?? 0),
                        'toplamAdet' => intval($h->ToplamAdet ?? 0),
                        'aciklama' => (string) ($h->Aciklama ?? ''),
                    ];
                    $tooltipLines[] = ($tarih ? $tarih . ' - ' : '') . $gorevAdi . ': ' . $h->Adet . '/' . $h->ToplamAdet . ' adet bekliyor' . ($h->Aciklama ? ' (' . $h->Aciklama . ')' : '');
                }
            } elseif ($bitmis && $bitmis->isNotEmpty()) {
                $status = 'tamamlandi';
                $statusLabel = 'Tamamlandı';
                $adet = $bitmis->sum('ToplamAdet');
                $detay = $adet . ' adet tamamlandı';

                foreach ($bitmis as $g) {
                    $pAdi = isset($g->PersonelNo) ? ($personelIsimleri[$g->PersonelNo] ?? '') : '';
                    $gorevAdi = $resolveTaskName($g);
                    $baslangic = '';
                    $bitis = '';
                    try {
                        $baslangic = $g->GorevBaslamaTarihi ? Carbon::parse($g->GorevBaslamaTarihi)->format('d.m.Y') : '';
                        $bitis = $g->GorevBitisTarihi ? Carbon::parse($g->GorevBitisTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $baslangic = (string) ($g->GorevBaslamaTarihi ?? '');
                        $bitis = (string) ($g->GorevBitisTarihi ?? '');
                    }
                    $satir = '';
                    if ($baslangic && $bitis) {
                        $satir = $baslangic . ' → ' . $bitis;
                    } elseif ($bitis) {
                        $satir = $bitis;
                    }
                    $satir .= ': ' . $gorevAdi . ' / ' . $g->ToplamAdet . ' adet';
                    if ($pAdi) {
                        $satir .= ' (' . $pAdi . ')';
                    }
                    $completedRecords[] = [
                        'baslangic' => $baslangic,
                        'bitis' => $bitis,
                        'personelAd' => $pAdi,
                        'gorevAdi' => $gorevAdi,
                        'adet' => intval($g->ToplamAdet ?? 0),
                    ];
                    $tooltipLines[] = $satir;
                }
            }

            if (empty($activeRecords) && $aktifUretimde && $aktifUretimde->isNotEmpty()) {
                foreach ($aktifUretimde as $pg) {
                    $pAdi = $personelIsimleri[$pg->PersonelNo] ?? 'Bilinmeyen';
                    $gorevAdi = $resolveTaskName($pg);
                    $tarih = '';
                    try {
                        $tarih = $pg->GorevBaslamaTarihi ? Carbon::parse($pg->GorevBaslamaTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $tarih = (string) ($pg->GorevBaslamaTarihi ?? '');
                    }

                    $activeRecords[] = [
                        'tarih' => $tarih,
                        'personelAd' => $pAdi,
                        'gorevAdi' => $gorevAdi,
                        'adet' => $this->activePersonnelTaskQuantity($pg),
                    ];
                }
            }

            if (empty($waitingRecords) && $havuzda && $havuzda->isNotEmpty()) {
                foreach ($havuzda as $h) {
                    $gorevAdi = $resolveTaskName($h);
                    $tarih = '';
                    try {
                        $tarih = $h->GorevBaslangicTarihi ? Carbon::parse($h->GorevBaslangicTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $tarih = (string) ($h->GorevBaslangicTarihi ?? '');
                    }

                    $waitingRecords[] = [
                        'tarih' => $tarih,
                        'gorevAdi' => $gorevAdi,
                        'adet' => intval($h->Adet ?? 0),
                        'toplamAdet' => intval($h->ToplamAdet ?? 0),
                        'aciklama' => (string) ($h->Aciklama ?? ''),
                    ];
                }
            }

            if (empty($completedRecords) && $bitmis && $bitmis->isNotEmpty()) {
                foreach ($bitmis as $g) {
                    $pAdi = isset($g->PersonelNo) ? ($personelIsimleri[$g->PersonelNo] ?? '') : '';
                    $gorevAdi = $resolveTaskName($g);
                    $baslangic = '';
                    $bitis = '';
                    try {
                        $baslangic = $g->GorevBaslamaTarihi ? Carbon::parse($g->GorevBaslamaTarihi)->format('d.m.Y') : '';
                        $bitis = $g->GorevBitisTarihi ? Carbon::parse($g->GorevBitisTarihi)->format('d.m.Y') : '';
                    } catch (\Exception $e) {
                        $baslangic = (string) ($g->GorevBaslamaTarihi ?? '');
                        $bitis = (string) ($g->GorevBitisTarihi ?? '');
                    }

                    $completedRecords[] = [
                        'baslangic' => $baslangic,
                        'bitis' => $bitis,
                        'personelAd' => $pAdi,
                        'gorevAdi' => $gorevAdi,
                        'adet' => intval($g->ToplamAdet ?? 0),
                    ];
                }
            }

            $componentNos = collect();
            if ($aktifUretimde && $aktifUretimde->isNotEmpty()) {
                $componentNos = $componentNos->merge($aktifUretimde->pluck('AraUrunAdiNo'));
            }
            if ($havuzda && $havuzda->isNotEmpty()) {
                $componentNos = $componentNos->merge($havuzda->pluck('AraUrunAdiNo'));
            }
            if ($bitmis && $bitmis->isNotEmpty()) {
                $componentNos = $componentNos->merge($bitmis->pluck('AraUrunAdiNo'));
            }

            $steps[] = [
                'bolumNo' => $bNo,
                'bolumAdi' => $bAdi,
                'status' => $status,
                'statusLabel' => $statusLabel,
                'detay' => $detay,
                'adet' => $adet,
                'toplamAdet' => $toplamAdet,
                'personelAd' => $personelAd,
                'tooltipLines' => $tooltipLines,
                'activeAdet' => $aktifUretimde && $aktifUretimde->isNotEmpty() ? intval($aktifUretimde->sum(fn ($row) => $this->activePersonnelTaskQuantity($row))) : 0,
                'waitingAdet' => $havuzda && $havuzda->isNotEmpty() ? intval($havuzda->sum('Adet')) : 0,
                'waitingToplamAdet' => $havuzda && $havuzda->isNotEmpty() ? intval($havuzda->sum('ToplamAdet')) : 0,
                'completedAdet' => $bitmis && $bitmis->isNotEmpty() ? intval($bitmis->sum('ToplamAdet')) : 0,
                'activeRecords' => $activeRecords,
                'waitingRecords' => $waitingRecords,
                'completedRecords' => $completedRecords,
                'componentNos' => $componentNos
                    ->filter()
                    ->map(fn ($value) => intval($value))
                    ->unique()
                    ->values()
                    ->all(),
            ];
        }

        return $this->sortPipelineStepsByProductionSequence($steps, $eslesenUrunNo, $eslesenUrunTur);
    }

    private function sortPipelineStepsByProductionSequence(array $steps, int $eslesenUrunNo, string $eslesenUrunTur): array
    {
        $steps = array_values(array_filter($steps, function ($step) {
            $status = (string) ($step['status'] ?? 'baslanmadi');
            $componentNos = collect($step['componentNos'] ?? [])
                ->filter()
                ->map(fn ($value) => intval($value))
                ->filter(fn ($value) => $value > 0);

            return $status !== 'baslanmadi' || $componentNos->isNotEmpty();
        }));

        if (empty($steps)) {
            return [];
        }

        $sequenceMap = $this->buildPipelineComponentSequenceMap($eslesenUrunNo, $eslesenUrunTur);
        $indexedSteps = [];

        foreach (array_values($steps) as $index => $step) {
            $indexedSteps[] = [
                'index' => $index,
                'step' => $step,
                'sequence' => $this->pipelineStepSequenceIndex($step, $sequenceMap),
                'department' => intval($step['bolumNo'] ?? 0),
            ];
        }

        usort($indexedSteps, function ($left, $right) {
            if ($left['sequence'] !== $right['sequence']) {
                return $left['sequence'] <=> $right['sequence'];
            }

            if ($left['department'] !== $right['department']) {
                return $left['department'] <=> $right['department'];
            }

            return $left['index'] <=> $right['index'];
        });

        return array_map(fn ($item) => $item['step'], $indexedSteps);
    }

    private function pipelineStepSequenceIndex(array $step, array $sequenceMap): int
    {
        $componentNos = collect($step['componentNos'] ?? [])
            ->filter()
            ->map(fn ($value) => intval($value))
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->values();

        if ($componentNos->isEmpty()) {
            return 1000000 + intval($step['bolumNo'] ?? 0);
        }

        $knownIndexes = $componentNos
            ->map(fn ($componentNo) => $sequenceMap[$componentNo] ?? null)
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($knownIndexes->isNotEmpty()) {
            return intval($knownIndexes->min());
        }

        return 900000 + intval($componentNos->min());
    }

    private function buildPipelineComponentSequenceMap(int $eslesenUrunNo, string $eslesenUrunTur): array
    {
        $rootComponentNo = $this->resolveMatchedAraUrunNo($eslesenUrunNo, $eslesenUrunTur);
        if ($rootComponentNo <= 0) {
            return [];
        }

        $visited = [];
        $visiting = [];
        $ordered = [];

        $visit = function (int $componentNo) use (&$visit, &$visited, &$visiting, &$ordered) {
            if ($componentNo <= 0 || isset($visited[$componentNo]) || isset($visiting[$componentNo])) {
                return;
            }

            $visiting[$componentNo] = true;

            foreach ($this->directBomChildComponentNos($componentNo) as $childComponentNo) {
                $visit($childComponentNo);
            }

            unset($visiting[$componentNo]);
            $visited[$componentNo] = true;
            $ordered[] = $componentNo;
        };

        $visit($rootComponentNo);

        $sequenceMap = [];
        foreach ($ordered as $index => $componentNo) {
            $sequenceMap[$componentNo] = $index;
        }

        return $sequenceMap;
    }

    private function directBomChildComponentNos(int $componentNo): array
    {
        if ($componentNo <= 0) {
            return [];
        }

        $yol = trim((string) (DB::table('tbAraUrun')->where('No', $componentNo)->value('Yol') ?? ''));
        if ($yol === '') {
            return [];
        }

        $children = [];
        foreach (explode(':', $yol) as $segment) {
            $parts = array_values(array_filter(array_map('trim', explode('-', $segment)), fn ($part) => $part !== ''));
            $childNo = intval($parts[0] ?? 0);
            if ($childNo > 0) {
                $children[$childNo] = true;
            }
        }

        return array_keys($children);
    }

    private function pendingApprovalSql(string $column = 'Onay'): string
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

    private function isLegacyApprovedValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'evet', 'yes'], true);
    }

    private function isLegacyProductionReadyValue($value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['hazir', 'ready'], true);
    }

    private function isLegacyInProductionValue($value): bool
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['0', 'false', 'hayir', 'hayır', 'no'], true);
    }

    private function isActivePersonnelTaskRecord(object $row): bool
    {
        $adet = max(0, intval($row->Adet ?? 0));
        $bekleyen = max(0, intval($row->BekleyenAdet ?? 0));
        $onay = $row->Onay ?? null;

        if ($adet > 0 && !$this->isLegacyApprovedValue($onay) && !$this->isLegacyProductionReadyValue($onay)) {
            return true;
        }

        return $bekleyen > 0 && $this->isLegacyInProductionValue($onay);
    }

    private function activePersonnelTaskQuantity(object $row): int
    {
        $bekleyen = max(0, intval($row->BekleyenAdet ?? 0));

        return $bekleyen > 0 ? $bekleyen : max(0, intval($row->Adet ?? 0));
    }

    private function isReadyPersonnelTaskRecord(object $row): bool
    {
        if (!empty($row->readiness_blocked)) {
            return false;
        }

        $readyQuantity = max(0, intval($row->Adet ?? 0));

        return $readyQuantity > 0 && $this->isLegacyProductionReadyValue($row->Onay ?? null);
    }

    private function isAssignedWaitingPersonnelTaskRecord(object $row): bool
    {
        if (!empty($row->readiness_blocked)) {
            return true;
        }

        $bekleyen = max(0, intval($row->BekleyenAdet ?? 0));
        $onay = $row->Onay ?? null;

        return $bekleyen > 0
            && !$this->isLegacyApprovedValue($onay)
            && !$this->isLegacyInProductionValue($onay);
    }

    private function isSpecialProductionOrder(?string $musteri): bool
    {
        return str_starts_with(trim((string) $musteri), 'ÖZEL ÜRETİM');
    }

    private function canCancelWorkOrderStatus(string $durum, bool $isOzelUretim): bool
    {
        return $durum === 'IsEmriVerildi'
            || ($isOzelUretim && $durum === 'UretimBekliyor');
    }

    private function productionTraceEnabled(): bool
    {
        return app(BomService::class)->supportsProductionOrderTrace();
    }

    private function hasTrackedProductionRows(int $siparisSatirNo): bool
    {
        if ($siparisSatirNo <= 0 || !$this->productionTraceEnabled()) {
            return false;
        }

        return DB::table('tbBolumHavuz')->where('SiparisSatirNo', $siparisSatirNo)->exists()
            || DB::table('tbPersonelGorev')->where('SiparisSatirNo', $siparisSatirNo)->exists()
            || DB::table('tbGorevler')->where('SiparisSatirNo', $siparisSatirNo)->exists();
    }

    private function countOpenProductionRowsForOrder(int $siparisSatirNo, int $eslesenUrunNo, string $eslesenUrunTur, int $gorevNo): int
    {
        if ($this->hasTrackedProductionRows($siparisSatirNo)) {
            $havuzCount = intval(DB::table('tbBolumHavuz')->where('SiparisSatirNo', $siparisSatirNo)->count());
            $personelCount = intval(
                DB::table('tbPersonelGorev')
                    ->where('SiparisSatirNo', $siparisSatirNo)
                    ->where(function ($query) {
                        $query->where('Adet', '>', 0)
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->where(function ($query) {
                        $query->whereRaw($this->pendingApprovalSql())
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->count()
            );

            return $havuzCount + $personelCount;
        }

        if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
            return intval(DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->count())
                + intval(DB::table('tbPersonelGorev')
                    ->where('UrunIDNo', $eslesenUrunNo)
                    ->where(function ($query) {
                        $query->where('Adet', '>', 0)
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->where(function ($query) {
                        $query->whereRaw($this->pendingApprovalSql())
                            ->orWhere('BekleyenAdet', '>', 0);
                    })
                    ->count());
        }

        if ($gorevNo > 0) {
            return intval(DB::table('tbBolumHavuz')->where('No', $gorevNo)->count());
        }

        return 0;
    }

    private function reverseStockEffectsForWorkOrderCancellation(object $satir): array
    {
        $summary = [
            'logged_movements_reversed' => 0,
            'unlogged_production_reversed' => 0,
            'stock_quantity_restored' => 0,
            'stock_quantity_removed' => 0,
            'buffer_quantity_restored' => 0,
            'buffer_quantity_removed' => 0,
            'movement_ids' => [],
        ];

        foreach ([
            $this->reverseLoggedStockMovementsForCancellation($satir),
            $this->reverseUnloggedCompletedProductionForCancellation($satir),
        ] as $partial) {
            foreach ($summary as $key => $value) {
                if ($key === 'movement_ids') {
                    $summary[$key] = array_values(array_unique(array_merge($summary[$key], $partial[$key] ?? [])));
                    continue;
                }

                $summary[$key] += intval($partial[$key] ?? 0);
            }
        }

        return $summary;
    }

    private function reverseLoggedStockMovementsForCancellation(object $satir): array
    {
        $summary = [
            'logged_movements_reversed' => 0,
            'unlogged_production_reversed' => 0,
            'stock_quantity_restored' => 0,
            'stock_quantity_removed' => 0,
            'buffer_quantity_restored' => 0,
            'buffer_quantity_removed' => 0,
            'movement_ids' => [],
        ];

        if (!Schema::hasTable('stock_movements') || !Schema::hasTable('tbBolumAraStok')) {
            return $summary;
        }

        $satirNo = intval($satir->No ?? 0);
        if ($satirNo <= 0) {
            return $summary;
        }

        $alreadyReversed = $this->reversedStockMovementIdsForOrder($satirNo);
        $movements = DB::table('stock_movements')
            ->where('order_item_no', $satirNo)
            ->whereIn('movement_type', ['production_stock_in', 'order_auto_stock_out', 'order_stock_out'])
            ->where(function ($query) {
                $query->where('quantity_delta', '!=', 0)
                    ->orWhere('buffer_delta', '!=', 0);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($movements as $movement) {
            $movementId = intval($movement->id ?? 0);
            if ($movementId <= 0 || in_array($movementId, $alreadyReversed, true)) {
                continue;
            }

            $stockRowNo = intval($movement->stock_row_no ?? 0);
            if ($stockRowNo <= 0) {
                continue;
            }

            $reverseQuantityDelta = -intval($movement->quantity_delta ?? 0);
            $reverseBufferDelta = -intval($movement->buffer_delta ?? 0);
            if ($reverseQuantityDelta === 0 && $reverseBufferDelta === 0) {
                continue;
            }

            $stockBefore = DB::table('tbBolumAraStok')
                ->where('No', $stockRowNo)
                ->lockForUpdate()
                ->first();

            if (!$stockBefore) {
                continue;
            }

            $newQuantity = max(0, intval($stockBefore->Adet ?? 0) + $reverseQuantityDelta);
            $newBuffer = max(0, intval($stockBefore->TamponMiktar ?? 0) + $reverseBufferDelta);
            if ($newBuffer > $newQuantity) {
                $newBuffer = $newQuantity;
            }

            DB::table('tbBolumAraStok')->where('No', $stockRowNo)->update([
                'Adet' => $newQuantity,
                'TamponMiktar' => $newBuffer,
            ]);

            $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockRowNo)->first();
            $this->logStockMovement($stockBefore, $stockAfter, [
                'movement_type' => $this->reversalMovementTypeFor((string) ($movement->movement_type ?? 'stock_adjusted')),
                'source_type' => 'work_order_cancel',
                'source_id' => $satirNo,
                'order_item_no' => $satirNo,
                'order_no' => trim((string) ($satir->SiparisNo ?? '')) ?: null,
                'work_order_no' => intval($satir->GorevNo ?? 0) > 0 ? intval($satir->GorevNo) : null,
                'personnel_task_no' => intval($movement->personnel_task_no ?? 0) > 0 ? intval($movement->personnel_task_no) : null,
                'description' => 'İş emri iptal edildiği için ilgili stok hareketi geri alındı.',
                'metadata' => [
                    'original_movement_id' => $movementId,
                    'original_movement_type' => (string) ($movement->movement_type ?? ''),
                    'reverse_reason' => 'work_order_cancel',
                ],
            ]);

            $summary['logged_movements_reversed']++;
            $summary['movement_ids'][] = $movementId;
            $this->addReverseDeltaToSummary($summary, $reverseQuantityDelta, $reverseBufferDelta);
        }

        return $summary;
    }

    private function reverseUnloggedCompletedProductionForCancellation(object $satir): array
    {
        $summary = [
            'logged_movements_reversed' => 0,
            'unlogged_production_reversed' => 0,
            'stock_quantity_restored' => 0,
            'stock_quantity_removed' => 0,
            'buffer_quantity_restored' => 0,
            'buffer_quantity_removed' => 0,
            'movement_ids' => [],
        ];

        if (!Schema::hasTable('tbGorevler') || !Schema::hasTable('tbBolumAraStok')) {
            return $summary;
        }

        $satirNo = intval($satir->No ?? 0);
        if ($satirNo <= 0 || !Schema::hasColumn('tbGorevler', 'SiparisSatirNo')) {
            return $summary;
        }

        $completedRows = DB::table('tbGorevler')
            ->where('SiparisSatirNo', $satirNo)
            ->whereNotNull('PersonelNo')
            ->where('PersonelNo', '>', 0)
            ->where('ToplamAdet', '>', 0)
            ->get();

        if ($completedRows->isEmpty()) {
            return $summary;
        }

        $loggedProductionByKey = [];
        if (Schema::hasTable('stock_movements')) {
            DB::table('stock_movements')
                ->where('order_item_no', $satirNo)
                ->where('movement_type', 'production_stock_in')
                ->where('quantity_delta', '>', 0)
                ->selectRaw('component_no, department_no, SUM(quantity_delta) as quantity')
                ->groupBy('component_no', 'department_no')
                ->get()
                ->each(function ($row) use (&$loggedProductionByKey) {
                    $loggedProductionByKey[$this->stockEffectKey(intval($row->component_no ?? 0), intval($row->department_no ?? 0))] = intval($row->quantity ?? 0);
                });
        }

        $completedByKey = [];
        foreach ($completedRows as $row) {
            $componentNo = intval($row->AraUrunAdiNo ?? 0);
            $departmentNo = intval($row->BolumAdiNo ?? 0);
            $quantity = intval($row->ToplamAdet ?? 0);
            if ($componentNo <= 0 || $quantity <= 0) {
                continue;
            }

            $key = $this->stockEffectKey($componentNo, $departmentNo);
            if (!isset($completedByKey[$key])) {
                $completedByKey[$key] = [
                    'component_no' => $componentNo,
                    'department_no' => $departmentNo,
                    'quantity' => 0,
                    'work_order_ids' => [],
                ];
            }

            $completedByKey[$key]['quantity'] += $quantity;
            $completedByKey[$key]['work_order_ids'][] = intval($row->No ?? 0);
        }

        foreach ($completedByKey as $key => $group) {
            $unloggedQuantity = intval($group['quantity']) - intval($loggedProductionByKey[$key] ?? 0);
            if ($unloggedQuantity <= 0) {
                continue;
            }

            $stockQuery = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', intval($group['component_no']));

            if (intval($group['department_no']) > 0) {
                $stockQuery->where('BolumAdiNo', intval($group['department_no']));
            }

            $stockBefore = $stockQuery->orderBy('No')->lockForUpdate()->first();
            if (!$stockBefore) {
                continue;
            }

            $reverseQuantityDelta = -min($unloggedQuantity, intval($stockBefore->Adet ?? 0));
            if ($reverseQuantityDelta === 0) {
                continue;
            }

            $newQuantity = max(0, intval($stockBefore->Adet ?? 0) + $reverseQuantityDelta);
            $newBuffer = min($newQuantity, max(0, intval($stockBefore->TamponMiktar ?? 0)));

            DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->update([
                'Adet' => $newQuantity,
                'TamponMiktar' => $newBuffer,
            ]);

            $stockAfter = DB::table('tbBolumAraStok')->where('No', $stockBefore->No)->first();
            $this->logStockMovement($stockBefore, $stockAfter, [
                'movement_type' => 'production_stock_in_reversed',
                'source_type' => 'work_order_cancel',
                'source_id' => $satirNo,
                'order_item_no' => $satirNo,
                'order_no' => trim((string) ($satir->SiparisNo ?? '')) ?: null,
                'work_order_no' => intval($satir->GorevNo ?? 0) > 0 ? intval($satir->GorevNo) : null,
                'description' => 'İş emri iptalinde hareket kaydı olmayan tamamlanmış üretim stoğu geri alındı.',
                'metadata' => [
                    'completed_work_order_ids' => array_values(array_filter($group['work_order_ids'])),
                    'reverse_reason' => 'work_order_cancel',
                    'unlogged_quantity' => $unloggedQuantity,
                ],
            ]);

            $summary['unlogged_production_reversed']++;
            $this->addReverseDeltaToSummary($summary, $reverseQuantityDelta, 0);
        }

        return $summary;
    }

    private function addReverseDeltaToSummary(array &$summary, int $quantityDelta, int $bufferDelta): void
    {
        if ($quantityDelta > 0) {
            $summary['stock_quantity_restored'] += $quantityDelta;
        } elseif ($quantityDelta < 0) {
            $summary['stock_quantity_removed'] += abs($quantityDelta);
        }

        if ($bufferDelta > 0) {
            $summary['buffer_quantity_restored'] += $bufferDelta;
        } elseif ($bufferDelta < 0) {
            $summary['buffer_quantity_removed'] += abs($bufferDelta);
        }
    }

    private function reversedStockMovementIdsForOrder(int $satirNo): array
    {
        if (!Schema::hasTable('stock_movements')) {
            return [];
        }

        $ids = [];
        DB::table('stock_movements')
            ->where('order_item_no', $satirNo)
            ->whereIn('movement_type', [
                'production_stock_in_reversed',
                'order_auto_stock_out_reversed',
                'order_stock_out_reversed',
                'work_order_stock_movement_reversed',
            ])
            ->get(['metadata'])
            ->each(function ($row) use (&$ids) {
                $metadata = $this->decodeJsonPayload($row->metadata ?? null);
                $singleId = intval(data_get($metadata, 'context.original_movement_id', 0));
                if ($singleId > 0) {
                    $ids[] = $singleId;
                }

                $manyIds = data_get($metadata, 'context.original_movement_ids', []);
                if (is_array($manyIds)) {
                    foreach ($manyIds as $id) {
                        $id = intval($id);
                        if ($id > 0) {
                            $ids[] = $id;
                        }
                    }
                }
            });

        return array_values(array_unique($ids));
    }

    private function reversalMovementTypeFor(string $movementType): string
    {
        return match ($movementType) {
            'production_stock_in' => 'production_stock_in_reversed',
            'order_auto_stock_out' => 'order_auto_stock_out_reversed',
            'order_stock_out' => 'order_stock_out_reversed',
            default => 'work_order_stock_movement_reversed',
        };
    }

    private function stockEffectKey(int $componentNo, int $departmentNo): string
    {
        return $componentNo . ':' . $departmentNo;
    }

    private function deleteProductionRowsForOrder(object $satir): int
    {
        $satirNo = intval($satir->No ?? 0);

        if ($this->hasTrackedProductionRows($satirNo)) {
            return intval(DB::table('tbBolumHavuz')->where('SiparisSatirNo', $satirNo)->delete())
                + intval(DB::table('tbPersonelGorev')->where('SiparisSatirNo', $satirNo)->delete())
                + intval(DB::table('tbGorevler')->where('SiparisSatirNo', $satirNo)->delete());
        }

        $gorevNo = intval($satir->GorevNo ?? 0);
        $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
        $eslesenUrunTur = (string) ($satir->EslesenUrunTur ?? '');

        if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0) {
            return intval(DB::table('tbBolumHavuz')->where('UrunIDNo', $eslesenUrunNo)->delete());
        }

        if ($gorevNo > 0) {
            return intval(DB::table('tbBolumHavuz')->where('No', $gorevNo)->delete());
        }

        return 0;
    }

    private function buildTrackedPipelineSteps(int $siparisSatirNo, int $eslesenUrunNo, string $eslesenUrunTur): array
    {
        if ($siparisSatirNo <= 0 || !$this->hasTrackedProductionRows($siparisSatirNo)) {
            return [];
        }

        $bolumler = DB::table('tbBolum')->orderBy('No')->get();
        if ($bolumler->isEmpty()) {
            return [];
        }

        $havuzKayitlari = DB::table('tbBolumHavuz')
            ->where('SiparisSatirNo', $siparisSatirNo)
            ->select('No', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'Adet', 'ToplamAdet', 'GorevBaslangicTarihi', 'Aciklama')
            ->get();

        $personelKayitlari = DB::table('tbPersonelGorev')
            ->where('SiparisSatirNo', $siparisSatirNo)
            ->select('No', 'SiparisSatirNo', 'SiparisNo', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'PersonelNo', 'Adet', 'BekleyenAdet', 'Onay', 'GorevBaslamaTarihi')
            ->get();

        $bomService = app(BomService::class);
        $personelKayitlari = $personelKayitlari->map(function ($row) use ($bomService) {
            if (!$this->isLegacyProductionReadyValue($row->Onay ?? null)) {
                return $row;
            }

            $total = max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
            if ($total <= 0) {
                return $row;
            }

            try {
                $split = $bomService->personnelTaskReadySplit($row, $total);
                $row->Adet = intval($split['ready'] ?? 0);
                $row->BekleyenAdet = intval($split['waiting'] ?? 0);
            } catch (\Throwable) {
                // İş emri özeti, anlık BOM hesabı hata alsa da mevcut kayıtla gösterilebilsin.
            }

            try {
                $readinessIssue = $bomService->taskReadinessIssue($row, $total);
                if ($readinessIssue !== null) {
                    $row->availability_issue = $readinessIssue;
                    $row->readiness_blocked = 1;
                    $row->Adet = 0;
                    $row->BekleyenAdet = max(
                        max(0, intval($row->BekleyenAdet ?? 0)),
                        $total
                    );
                }
            } catch (\Throwable) {
                // Canlı stok kontrolü geçici hata alırsa eski özet akışı bozulmasın.
            }

            return $row;
        });

        $uretimdeKayitlari = $personelKayitlari->filter(function ($row) {
            return $this->isActivePersonnelTaskRecord($row);
        })->values();

        $uretimeHazirKayitlari = $personelKayitlari->filter(function ($row) {
            return $this->isReadyPersonnelTaskRecord($row);
        })->values();

        $personeldeBekleyenKayitlari = $personelKayitlari->filter(function ($row) {
            return $this->isAssignedWaitingPersonnelTaskRecord($row);
        })->values();

        $tamamlananKayitlari = DB::table('tbGorevler')
            ->where('SiparisSatirNo', $siparisSatirNo)
            ->where('ToplamAdet', '>', 0)
            ->whereNotNull('PersonelNo')
            ->where('PersonelNo', '>', 0)
            ->select('No', 'BolumAdiNo', 'UrunIDNo', 'AraUrunAdiNo', 'PersonelNo', 'ToplamAdet', 'GorevBaslamaTarihi', 'GorevBitisTarihi')
            ->get();

        $havuz = $havuzKayitlari->groupBy('BolumAdiNo');
        $uretimde = $uretimdeKayitlari->groupBy('BolumAdiNo');
        $uretimeHazir = $uretimeHazirKayitlari->groupBy('BolumAdiNo');
        $personeldeBekleyen = $personeldeBekleyenKayitlari->groupBy('BolumAdiNo');
        $tamamlanan = $tamamlananKayitlari->groupBy('BolumAdiNo');

        $personelIsimleri = [];
        $personelNolar = $personelKayitlari->pluck('PersonelNo')
            ->merge($tamamlananKayitlari->pluck('PersonelNo'))
            ->filter()
            ->unique()
            ->values();
        if ($personelNolar->isNotEmpty()) {
            DB::table('tbPersonel')
                ->whereIn('PersonelNo', $personelNolar)
                ->get()
                ->each(function ($personel) use (&$personelIsimleri) {
                    $personelIsimleri[$personel->PersonelNo] = trim(($personel->Ad ?? '') . ' ' . ($personel->Soyad ?? ''));
                });
        }

        $araUrunNolar = $havuzKayitlari->pluck('AraUrunAdiNo')
            ->merge($personelKayitlari->pluck('AraUrunAdiNo'))
            ->merge($tamamlananKayitlari->pluck('AraUrunAdiNo'))
            ->filter()
            ->unique()
            ->values();

        $urunNolar = collect([$eslesenUrunNo])
            ->merge($havuzKayitlari->pluck('UrunIDNo'))
            ->merge($personelKayitlari->pluck('UrunIDNo'))
            ->merge($tamamlananKayitlari->pluck('UrunIDNo'))
            ->filter()
            ->unique()
            ->values();

        $araUrunIsimleri = [];
        if ($araUrunNolar->isNotEmpty()) {
            DB::table('tbAraUrun')
                ->whereIn('No', $araUrunNolar)
                ->select('No', 'AraUrunAdi')
                ->get()
                ->each(function ($row) use (&$araUrunIsimleri) {
                    $araUrunIsimleri[$row->No] = trim((string) ($row->AraUrunAdi ?? ''));
                });
        }

        $urunIsimleri = [];
        if ($urunNolar->isNotEmpty()) {
            DB::table('tbUrunler')
                ->whereIn('No', $urunNolar)
                ->select('No', 'SistemAdi', 'UrunID')
                ->get()
                ->each(function ($row) use (&$urunIsimleri) {
                    $urunIsimleri[$row->No] = trim((string) (($row->SistemAdi ?: $row->UrunID) ?? ''));
                });
        }

        $resolveTaskName = function ($row) use ($araUrunIsimleri, $urunIsimleri, $eslesenUrunNo, $eslesenUrunTur) {
            $araUrunNo = intval($row->AraUrunAdiNo ?? 0);
            if ($araUrunNo > 0 && !empty($araUrunIsimleri[$araUrunNo])) {
                return $araUrunIsimleri[$araUrunNo];
            }

            $urunNo = intval($row->UrunIDNo ?? 0);
            if ($urunNo > 0 && !empty($urunIsimleri[$urunNo])) {
                return $urunIsimleri[$urunNo];
            }

            if ($eslesenUrunTur === 'Nihai' && $eslesenUrunNo > 0 && !empty($urunIsimleri[$eslesenUrunNo])) {
                return $urunIsimleri[$eslesenUrunNo];
            }

            return 'Görev adı bulunamadı';
        };

        $steps = [];

        foreach ($bolumler as $bolum) {
            $bolumNo = intval($bolum->No ?? 0);
            $waitingRows = $havuz->get($bolumNo) ?? collect();
            $activeRows = $uretimde->get($bolumNo) ?? collect();
            $readyRows = $uretimeHazir->get($bolumNo) ?? collect();
            $assignedWaitingRows = $personeldeBekleyen->get($bolumNo) ?? collect();
            $completedRows = $tamamlanan->get($bolumNo) ?? collect();

            $status = 'baslanmadi';
            $statusLabel = 'Başlanmadı';
            $detay = '';
            $personelAd = '';
            $adet = 0;
            $toplamAdet = 0;
            $tooltipLines = [];

            if ($activeRows->isNotEmpty()) {
                $status = 'uretimde';
                $statusLabel = 'Üretimde';
                $adet = intval($activeRows->sum(function ($row) {
                    $bekleyen = intval($row->BekleyenAdet ?? 0);
                    return $bekleyen > 0 ? $bekleyen : intval($row->Adet ?? 0);
                }));
                $detay = $adet . ' adet üretiliyor';

                foreach ($activeRows as $record) {
                    $tarih = trim((string) ($record->GorevBaslamaTarihi ?? ''));
                    $gorevAdi = $resolveTaskName($record);
                    $kisi = trim((string) ($personelIsimleri[$record->PersonelNo] ?? ''));
                    $bekleyen = intval($record->BekleyenAdet ?? 0);
                    $kayitAdedi = $bekleyen > 0 ? $bekleyen : intval($record->Adet ?? 0);

                    if ($personelAd === '' && $kisi !== '') {
                        $personelAd = $kisi;
                    }

                    $tooltipLines[] = ($tarih !== '' ? $tarih . ' - ' : '')
                        . ($kisi !== '' ? $kisi : 'Bilinmeyen')
                        . ': '
                        . $gorevAdi
                        . ' / '
                        . $kayitAdedi
                        . ' adet işleniyor';
                }
            } elseif ($readyRows->isNotEmpty()) {
                $status = 'hazir';
                $statusLabel = 'Üretime Hazır';
                $adet = intval($readyRows->sum(function ($row) {
                    return max(0, intval($row->Adet ?? 0));
                }));
                $toplamAdet = intval($readyRows->sum(function ($row) {
                    return max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
                }));
                $detay = $adet . ' adet üretime hazır';

                foreach ($readyRows as $record) {
                    $tarih = trim((string) ($record->GorevBaslamaTarihi ?? ''));
                    $gorevAdi = $resolveTaskName($record);
                    $kisi = trim((string) ($personelIsimleri[$record->PersonelNo] ?? ''));
                    $kayitAdedi = max(0, intval($record->Adet ?? 0));

                    if ($personelAd === '' && $kisi !== '') {
                        $personelAd = $kisi;
                    }

                    $tooltipLines[] = ($tarih !== '' ? $tarih . ' - ' : '')
                        . ($kisi !== '' ? $kisi : 'Bilinmeyen')
                        . ': '
                        . $gorevAdi
                        . ' / '
                        . $kayitAdedi
                        . ' adet üretime hazır';
                }
            } elseif ($assignedWaitingRows->isNotEmpty()) {
                $status = 'bekliyor';
                $statusLabel = 'Bekliyor';
                $adet = intval($assignedWaitingRows->sum(fn ($row) => max(0, intval($row->BekleyenAdet ?? 0))));
                $toplamAdet = intval($assignedWaitingRows->sum(function ($row) {
                    return max(0, intval($row->Adet ?? 0)) + max(0, intval($row->BekleyenAdet ?? 0));
                }));
                $detay = $adet . '/' . $toplamAdet . ' adet personelde bekliyor';
                $firstAvailabilityIssue = $assignedWaitingRows
                    ->map(fn ($row) => trim((string) ($row->availability_issue ?? '')))
                    ->first(fn ($issue) => $issue !== '');
                if ($firstAvailabilityIssue) {
                    $detay .= ' - ' . $firstAvailabilityIssue;
                }

                foreach ($assignedWaitingRows as $record) {
                    $tarih = trim((string) ($record->GorevBaslamaTarihi ?? ''));
                    $gorevAdi = $resolveTaskName($record);
                    $kisi = trim((string) ($personelIsimleri[$record->PersonelNo] ?? ''));
                    $bekleyen = max(0, intval($record->BekleyenAdet ?? 0));
                    $availabilityIssue = trim((string) ($record->availability_issue ?? ''));

                    if ($personelAd === '' && $kisi !== '') {
                        $personelAd = $kisi;
                    }

                    $satir = ($tarih !== '' ? $tarih . ' - ' : '')
                        . ($kisi !== '' ? $kisi : 'Bilinmeyen')
                        . ': '
                        . $gorevAdi
                        . ' / '
                        . $bekleyen
                        . ' adet bekliyor';

                    if ($availabilityIssue !== '') {
                        $satir .= ' - ' . $availabilityIssue;
                    }

                    $tooltipLines[] = $satir;
                }
            } elseif ($waitingRows->isNotEmpty()) {
                $status = 'bekliyor';
                $statusLabel = 'Bekliyor';
                $adet = intval($waitingRows->sum('Adet'));
                $toplamAdet = intval($waitingRows->sum('ToplamAdet'));
                $detay = $adet . '/' . $toplamAdet . ' adet havuzda';

                foreach ($waitingRows as $record) {
                    $tarih = trim((string) ($record->GorevBaslangicTarihi ?? ''));
                    $gorevAdi = $resolveTaskName($record);
                    $satir = ($tarih !== '' ? $tarih . ' - ' : '')
                        . $gorevAdi
                        . ': '
                        . intval($record->Adet ?? 0)
                        . '/'
                        . intval($record->ToplamAdet ?? 0)
                        . ' adet bekliyor';

                    if (!empty($record->Aciklama)) {
                        $satir .= ' (' . trim((string) $record->Aciklama) . ')';
                    }

                    $tooltipLines[] = $satir;
                }
            } elseif ($completedRows->isNotEmpty()) {
                $status = 'tamamlandi';
                $statusLabel = 'Tamamlandı';
                $adet = intval($completedRows->sum(fn ($row) => intval($row->ToplamAdet ?? 0)));
                $detay = $adet . ' adet tamamlandı';

                foreach ($completedRows as $record) {
                    $tarih = trim((string) (($record->GorevBitisTarihi ?? '') ?: ($record->GorevBaslamaTarihi ?? '')));
                    $gorevAdi = $resolveTaskName($record);
                    $kisi = trim((string) ($personelIsimleri[$record->PersonelNo] ?? ''));
                    $kayitAdedi = intval($record->ToplamAdet ?? 0);

                    if ($personelAd === '' && $kisi !== '') {
                        $personelAd = $kisi;
                    }

                    $satir = ($tarih !== '' ? $tarih . ': ' : '')
                        . $gorevAdi
                        . ' / '
                        . $kayitAdedi
                        . ' adet';

                    if ($kisi !== '') {
                        $satir .= ' (' . $kisi . ')';
                    }

                    $tooltipLines[] = $satir;
                }
            }

            $componentNos = $waitingRows->pluck('AraUrunAdiNo')
                ->merge($activeRows->pluck('AraUrunAdiNo'))
                ->merge($readyRows->pluck('AraUrunAdiNo'))
                ->merge($assignedWaitingRows->pluck('AraUrunAdiNo'))
                ->merge($completedRows->pluck('AraUrunAdiNo'))
                ->filter()
                ->map(fn ($value) => intval($value))
                ->unique()
                ->values()
                ->all();

            $steps[] = [
                'bolumNo' => $bolumNo,
                'bolumAdi' => (string) ($bolum->BolumAdi ?? ''),
                'status' => $status,
                'statusLabel' => $statusLabel,
                'detay' => $detay,
                'adet' => $adet,
                'toplamAdet' => $toplamAdet,
                'personelAd' => $personelAd,
                'tooltipLines' => $tooltipLines,
                'componentNos' => $componentNos,
            ];
        }

        return $this->sortPipelineStepsByProductionSequence($steps, $eslesenUrunNo, $eslesenUrunTur);
    }

    private function findActiveOrderRowsForProduct(int $eslesenUrunNo, string $eslesenUrunTur)
    {
        if ($eslesenUrunNo <= 0 || trim($eslesenUrunTur) === '') {
            return collect();
        }

        return DB::table('tbSiparisSatir')
            ->where('EslesenUrunNo', $eslesenUrunNo)
            ->where('EslesenUrunTur', $eslesenUrunTur)
            ->whereIn('Durum', ['IsEmriVerildi', 'PasifDevamEden'])
            ->orderByRaw('CASE WHEN IsEmriTarihi IS NULL THEN 1 ELSE 0 END')
            ->orderBy('IsEmriTarihi')
            ->orderBy('No')
            ->get([
                'No',
                DB::raw('IFNULL(Adet, 0) as Adet'),
                'IsEmriTarihi',
            ]);
    }

    private function allocateIntegerTotalByOrders($orders, int $total): array
    {
        $normalizedOrders = collect($orders)->values()->map(function ($order, $index) {
            return [
                'no' => intval(is_array($order) ? ($order['No'] ?? $order['no'] ?? 0) : ($order->No ?? $order->no ?? 0)),
                'adet' => max(0, intval(is_array($order) ? ($order['Adet'] ?? $order['adet'] ?? 0) : ($order->Adet ?? $order->adet ?? 0))),
                'sortIndex' => $index,
            ];
        })->filter(function ($order) {
            return $order['no'] > 0;
        })->values();

        $allocation = $normalizedOrders->mapWithKeys(function ($order) {
            return [$order['no'] => 0];
        })->all();

        if ($total <= 0 || $normalizedOrders->isEmpty()) {
            return $allocation;
        }

        $weightTotal = intval($normalizedOrders->sum('adet'));
        if ($weightTotal <= 0) {
            $weightTotal = $normalizedOrders->count();
            $normalizedOrders = $normalizedOrders->map(function ($order) {
                $order['adet'] = 1;
                return $order;
            })->values();
        }

        $distributed = 0;
        $remainders = [];

        foreach ($normalizedOrders as $order) {
            $exact = ($total * $order['adet']) / $weightTotal;
            $base = (int) floor($exact);
            $allocation[$order['no']] = $base;
            $distributed += $base;
            $remainders[] = [
                'no' => $order['no'],
                'adet' => $order['adet'],
                'remainder' => $exact - $base,
                'sortIndex' => $order['sortIndex'],
            ];
        }

        $remaining = $total - $distributed;
        usort($remainders, function ($left, $right) {
            if (abs($left['remainder'] - $right['remainder']) > 0.000001) {
                return $left['remainder'] < $right['remainder'] ? 1 : -1;
            }

            if ($left['adet'] !== $right['adet']) {
                return $left['adet'] < $right['adet'] ? 1 : -1;
            }

            return $left['sortIndex'] <=> $right['sortIndex'];
        });

        for ($i = 0; $i < $remaining; $i++) {
            $target = $remainders[$i % count($remainders)]['no'] ?? 0;
            if ($target > 0) {
                $allocation[$target] = intval($allocation[$target] ?? 0) + 1;
            }
        }

        return $allocation;
    }

    private function summarizePipelineProgress(array $pipelineSteps): array
    {
        $steps = collect($pipelineSteps)->filter(function ($step) {
            return ($step['status'] ?? 'baslanmadi') !== 'baslanmadi';
        })->values();

        $biten = $steps->where('status', 'tamamlandi')->count();
        $aktif = $steps->whereIn('status', ['uretimde', 'hazir', 'bekliyor'])->count();
        $yuzde = ($biten + $aktif) > 0
            ? (int) round(($biten * 100) / ($biten + $aktif))
            : 0;

        $aktifBolumler = $steps->map(function ($step) {
            $status = (string) ($step['status'] ?? '');
            if ($status === 'uretimde') {
                $personelAd = trim((string) ($step['personelAd'] ?? ''));
                return ($personelAd !== '' ? $personelAd : (string) ($step['bolumAdi'] ?? '')) . ' (Uretimde)';
            }

            if ($status === 'hazir') {
                $personelAd = trim((string) ($step['personelAd'] ?? ''));
                return ($personelAd !== '' ? $personelAd : (string) ($step['bolumAdi'] ?? '')) . ' (Uretime hazir)';
            }

            if ($status === 'bekliyor') {
                return (string) ($step['bolumAdi'] ?? '') . ' (Bekliyor)';
            }

            return null;
        })->filter()->implode(', ');

        return [$aktif, $biten, $aktifBolumler, $yuzde];
    }

    private function buildOrderSpecificPipelineSteps(array $globalSteps, $allocationOrders, int $siparisSatirNo): array
    {
        $orders = collect($allocationOrders)->values();

        return collect($globalSteps)->map(function ($step) use ($orders, $siparisSatirNo) {
            $activeAllocation = $this->allocateIntegerTotalByOrders($orders, intval($step['activeAdet'] ?? 0));
            $waitingAllocation = $this->allocateIntegerTotalByOrders($orders, intval($step['waitingAdet'] ?? 0));
            $waitingTotalAllocation = $this->allocateIntegerTotalByOrders($orders, intval($step['waitingToplamAdet'] ?? 0));
            $completedAllocation = $this->allocateIntegerTotalByOrders($orders, intval($step['completedAdet'] ?? 0));

            $allocatedActive = intval($activeAllocation[$siparisSatirNo] ?? 0);
            $allocatedWaiting = intval($waitingAllocation[$siparisSatirNo] ?? 0);
            $allocatedWaitingTotal = intval($waitingTotalAllocation[$siparisSatirNo] ?? 0);
            $allocatedCompleted = intval($completedAllocation[$siparisSatirNo] ?? 0);

            if ($allocatedWaiting > $allocatedWaitingTotal) {
                $allocatedWaiting = $allocatedWaitingTotal;
            }

            $status = 'baslanmadi';
            $statusLabel = 'Başlanmadı';
            $detay = '';
            $personelAd = '';
            $tooltipLines = [];

            if ($allocatedActive > 0) {
                $status = 'uretimde';
                $statusLabel = 'Üretimde';
                $detay = $allocatedActive . ' adet üretiliyor';
                [$tooltipLines, $personelAd] = $this->buildAllocatedActiveTooltipLines(
                    $step['activeRecords'] ?? [],
                    $orders,
                    $siparisSatirNo
                );
            } elseif ($allocatedWaitingTotal > 0) {
                $status = 'bekliyor';
                $statusLabel = 'Bekliyor';
                $detay = $allocatedWaiting . '/' . $allocatedWaitingTotal . ' adet havuzda';
                $tooltipLines = $this->buildAllocatedWaitingTooltipLines(
                    $step['waitingRecords'] ?? [],
                    $orders,
                    $siparisSatirNo
                );
            } elseif ($allocatedCompleted > 0) {
                $status = 'tamamlandi';
                $statusLabel = 'Tamamlandı';
                $detay = $allocatedCompleted . ' adet tamamlandı';
                [$tooltipLines, $personelAd] = $this->buildAllocatedCompletedTooltipLines(
                    $step['completedRecords'] ?? [],
                    $orders,
                    $siparisSatirNo
                );
            }

            return [
                'bolumNo' => intval($step['bolumNo'] ?? 0),
                'bolumAdi' => (string) ($step['bolumAdi'] ?? ''),
                'status' => $status,
                'statusLabel' => $statusLabel,
                'detay' => $detay,
                'adet' => $status === 'bekliyor' ? $allocatedWaiting : ($status === 'tamamlandi' ? $allocatedCompleted : $allocatedActive),
                'toplamAdet' => $status === 'bekliyor' ? $allocatedWaitingTotal : 0,
                'personelAd' => $personelAd,
                'tooltipLines' => $tooltipLines,
            ];
        })->all();
    }

    private function buildAllocatedActiveTooltipLines(array $records, $orders, int $siparisSatirNo): array
    {
        $tooltipLines = [];
        $personelAd = '';

        foreach ($records as $record) {
            $allocation = $this->allocateIntegerTotalByOrders($orders, intval($record['adet'] ?? 0));
            $adet = intval($allocation[$siparisSatirNo] ?? 0);
            if ($adet <= 0) {
                continue;
            }

            $tarih = trim((string) ($record['tarih'] ?? ''));
            $gorevAdi = trim((string) ($record['gorevAdi'] ?? ''));
            $kisi = trim((string) ($record['personelAd'] ?? ''));

            if ($personelAd === '' && $kisi !== '') {
                $personelAd = $kisi;
            }

            $tooltipLines[] = ($tarih !== '' ? $tarih . ' - ' : '')
                . ($kisi !== '' ? $kisi : 'Bilinmeyen')
                . ': '
                . ($gorevAdi !== '' ? $gorevAdi : 'Gorev')
                . ' / '
                . $adet
                . ' adet';
        }

        return [$tooltipLines, $personelAd];
    }

    private function buildAllocatedWaitingTooltipLines(array $records, $orders, int $siparisSatirNo): array
    {
        $tooltipLines = [];

        foreach ($records as $record) {
            $adetAllocation = $this->allocateIntegerTotalByOrders($orders, intval($record['adet'] ?? 0));
            $toplamAllocation = $this->allocateIntegerTotalByOrders($orders, intval($record['toplamAdet'] ?? 0));
            $adet = intval($adetAllocation[$siparisSatirNo] ?? 0);
            $toplamAdet = intval($toplamAllocation[$siparisSatirNo] ?? 0);

            if ($toplamAdet <= 0) {
                continue;
            }

            if ($adet > $toplamAdet) {
                $adet = $toplamAdet;
            }

            $tarih = trim((string) ($record['tarih'] ?? ''));
            $gorevAdi = trim((string) ($record['gorevAdi'] ?? ''));
            $aciklama = trim((string) ($record['aciklama'] ?? ''));

            $satir = ($tarih !== '' ? $tarih . ' - ' : '')
                . ($gorevAdi !== '' ? $gorevAdi : 'Gorev')
                . ': '
                . $adet
                . '/'
                . $toplamAdet
                . ' adet bekliyor';

            if ($aciklama !== '') {
                $satir .= ' (' . $aciklama . ')';
            }

            $tooltipLines[] = $satir;
        }

        return $tooltipLines;
    }

    private function buildAllocatedCompletedTooltipLines(array $records, $orders, int $siparisSatirNo): array
    {
        $tooltipLines = [];
        $personelAd = '';

        foreach ($records as $record) {
            $allocation = $this->allocateIntegerTotalByOrders($orders, intval($record['adet'] ?? 0));
            $adet = intval($allocation[$siparisSatirNo] ?? 0);
            if ($adet <= 0) {
                continue;
            }

            $baslangic = trim((string) ($record['baslangic'] ?? ''));
            $bitis = trim((string) ($record['bitis'] ?? ''));
            $gorevAdi = trim((string) ($record['gorevAdi'] ?? ''));
            $kisi = trim((string) ($record['personelAd'] ?? ''));

            if ($personelAd === '' && $kisi !== '') {
                $personelAd = $kisi;
            }

            $satir = '';
            if ($baslangic !== '' && $bitis !== '') {
                $satir = $baslangic . ' → ' . $bitis;
            } elseif ($bitis !== '') {
                $satir = $bitis;
            } elseif ($baslangic !== '') {
                $satir = $baslangic;
            }

            $satir .= ($satir !== '' ? ': ' : '')
                . ($gorevAdi !== '' ? $gorevAdi : 'Gorev')
                . ' / '
                . $adet
                . ' adet';

            if ($kisi !== '') {
                $satir .= ' (' . $kisi . ')';
            }

            $tooltipLines[] = $satir;
        }

        return [$tooltipLines, $personelAd];
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

    private function getProductBomComponents(Request $request)
    {
        $urunNo = intval($request->query('urunNo') ?? 0);
        $tur = $request->query('tur') ?? 'Ara'; // Ara or HamMadde

        if ($urunNo <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz ürün numarası.']);
        }

        // Check if tbUrunler -> get its UrunID -> find tbAraUrun No
        // If it's already an AraUrun (for Ara Mamül / Serbest Stok), it might not have an UrunID in tbUrunler.
        $araUrunAdiNo = null;
        $uID = DB::table('tbUrunler')->where('No', $urunNo)->value('UrunID');
        if ($uID) {
            $araUrunAdiNo = DB::table('tbAraUrun')->where('AraUrunAdi', $uID)->value('No');
        }

        if (!$araUrunAdiNo) {
            $araUrunAdiNo = $urunNo; // Already a tbAraUrun No (or fallback)
        }

        $components = [];
        $visited = [];

        $this->fetchBomComponentsRecursive($araUrunAdiNo, 1, $components, $visited, $tur);

        // Deduplicate or group
        $uniqueComps = [];
        $uniqueSet = [];
        foreach ($components as $c) {
            if (!isset($uniqueSet[$c['id']])) {
                $uniqueSet[$c['id']] = true;
                $uniqueComps[] = $c;
            }
        }

        return response()->json([
            'success' => true,
            'components' => array_values($uniqueComps)
        ]);
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

        // Skip root component, fetch subcomponents
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
                $isValid = true; // Fallback, send all and let frontend decide if it needs to
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

    private function addIndependentStockOrder(Request $request)
    {
        $payload = json_decode($request->getContent(), true) ?: [];
        $parentNo = intval($payload['parentNo'] ?? $payload['satirNo'] ?? 0);
        $adet = intval($payload['adet'] ?? 0);
        $aciklama = trim((string) ($payload['aciklama'] ?? ''));
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));

        if ($parentNo <= 0 || $adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz stok üretimi veya ilave adet.']);
        }

        DB::beginTransaction();
        try {
            $parent = DB::table('tbSiparisSatir')->where('No', $parentNo)->lockForUpdate()->first();
            if (!$parent) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Ana stok üretimi bulunamadı.']);
            }

            if (!$this->isFreeStockProductionRow($parent)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'İlave sadece serbest stok üretimi iş emirlerinde kullanılabilir.']);
            }

            if ((int) ($parent->Aktif ?? 0) !== 1 || (string) ($parent->Durum ?? '') === 'Pasif') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Pasif stok üretimine ilave açılamaz.']);
            }

            if ((int) ($parent->GorevNo ?? 0) <= 0) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'İlave için önce ana stok üretimi iş emrine dönüştürülmüş olmalı.']);
            }

            $rootNo = $this->stockProductionRootNo($parent);
            $root = $rootNo !== (int) $parent->No
                ? DB::table('tbSiparisSatir')->where('No', $rootNo)->lockForUpdate()->first()
                : $parent;

            if (!$root || !$this->isFreeStockProductionRow($root)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Ana stok üretim bağlantısı doğrulanamadı.']);
            }

            $tur = $this->normalizeIndependentStockType((string) ($parent->EslesenUrunTur ?: $root->EslesenUrunTur ?: 'Nihai'));
            $eslesenUrunNo = (int) ($parent->EslesenUrunNo ?: $root->EslesenUrunNo ?: 0);
            $urunAdi = (string) ($parent->UrunAdi ?: $root->UrunAdi ?: '');

            if ($eslesenUrunNo <= 0 || $urunAdi === '') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Stok üretiminin ürün eşleşmesi eksik.']);
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
                return response()->json($result);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '+' . $adet . ' ilave stok üretimi için yeni iş emri oluşturuldu.',
                'created' => $result['created'] ?? 1,
                'additionNo' => $satirNo,
                'parentNo' => $parentNo,
                'rootNo' => $rootNo,
                'sequence' => $sequence,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function createIndependentStockOrder(Request $request)
    {
        $payload = json_decode($request->getContent(), true);
        $urunNo = intval($payload['urunNo'] ?? 0);
        $adet = intval($payload['adet'] ?? 1);
        $tur = $this->normalizeIndependentStockType($payload['tur'] ?? 'Nihai'); // Nihai, Ara, HamMadde
        $aciklama = $payload['aciklama'] ?? '';
        $stokDurum = $this->normalizeWorkOrderStockMode((string) ($payload['stokDurum'] ?? 'StokDahil'));

        if ($urunNo <= 0 || $adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz ürün veya adet.']);
        }

        $ara = DB::table('tbAraUrun')->where('No', $urunNo)->first();
        if (!$ara) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.']);
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
                 return response()->json(['success' => false, 'message' => 'Bu ürün "Nihai Ürün" olarak tanımlı değil. Lütfen Ara Mamül veya Ham Madde seçin veya ürünü tbUrunler tablosuna ekleyin.']);
            }
        } else {
            $eslesenUrunTur = $tur; // Ara veya HamMadde
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
                return response()->json($result);
            }

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Serbest stok üretimi iş emri başarıyla oluşturuldu.',
                'created' => $result['created'] ?? 1
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
