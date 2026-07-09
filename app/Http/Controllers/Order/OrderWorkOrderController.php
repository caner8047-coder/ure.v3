<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsStockMovement;
use App\Http\Controllers\Concerns\LogsWorkOrderEvents;
use App\Http\Controllers\Concerns\SerializesRecord;
use App\Services\BomService;
use App\Services\OrderToWorkOrderService;
use App\Services\WorkOrderService;
use App\Services\WorkOrderEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class OrderWorkOrderController extends Controller
{
    use LogsStockMovement, LogsWorkOrderEvents, SerializesRecord;

    public function __construct(
        protected OrderToWorkOrderService $orderToWorkOrder,
        protected WorkOrderService $workOrderService,
        protected WorkOrderEventLogger $workOrderEventLogger,
    ) {}

    public function createOrderWorkOrders(Request $request)
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

    public function createManualWorkOrder(Request $request)
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

    public function cancelWorkOrder(Request $request)
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

    public function cancelBulkWorkOrders(Request $request)
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

    public function passivateWithWorkOrderCancel(Request $request)
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

    public function getWorkOrderHistory(Request $request)
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

    public function reactivateOrder(Request $request)
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

    public function onlyUpdateStatusBulk(Request $request)
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

    public function fixKayipOzelUretim(Request $request)
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

    public function getPasifDevamEden(Request $request)
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

    public function getOrderPipeline(Request $request)
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

    public function getProductionDetail(Request $request)
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

    private function normalizeWorkOrderStockMode(string $stokDurum): string
        {
            return in_array($stokDurum, ['StokDahil', 'StokHaric'], true) ? $stokDurum : 'StokDahil';
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

    private function pendingApprovalSql(string $column = 'Onay'): string
        {
            $normalized = "LOWER(TRIM(CAST({$column} AS CHAR)))";

            return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) = '' OR {$normalized} NOT IN ('1', 'true', 'evet', 'yes'))";
        }

    private function isLegacyApprovedValue($value): bool
        {
            if (is_bool($value)) {
                return $value;
            }

            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['1', 'true', 'evet', 'yes'], true);
        }

    private function isLegacyInProductionValue($value): bool
        {
            $normalized = strtolower(trim((string) $value));

            return in_array($normalized, ['0', 'false', 'hayir', 'hayır', 'no'], true);
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

    private function restoreTamponFromJson(?string $tamponDusumleriJson): void
        {
            app(BomService::class)->restoreTamponFromJson($tamponDusumleriJson);
        }

}
