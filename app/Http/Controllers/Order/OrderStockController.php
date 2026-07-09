<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsStockMovement;
use App\Http\Controllers\Concerns\SerializesRecord;
use App\Services\BomService;
use App\Services\WorkOrderEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderStockController extends Controller
{
    use LogsStockMovement, SerializesRecord;

    private const GIED_ALLOCATION_TABLE = 'tbSiparisOzelUretimRezervasyon';

    public function __construct(
        protected WorkOrderEventLogger $workOrderEventLogger,
    ) {}

    public function getThresholds(Request $request)
    {
        $thresholds = DB::table('tbKritikStokEsik as e')
            ->leftJoin('tbAraUrun as a', 'e.AraUrunAdiNo', '=', 'a.No')
            ->select('e.*', 'a.AraUrunAdi')
            ->orderByDesc('e.No')->get();
        return response()->json(['success' => true, 'thresholds' => $thresholds]);
    }

    public function saveThreshold(Request $request)
    {
        try {
            $data = $request->json()->all();
            $araUrunAdiNo = intval($data['araUrunAdiNo'] ?? 0);
            $esikMiktar = intval($data['esikMiktar'] ?? 0);
            $isEmriAdet = intval($data['isEmriAdet'] ?? 0);
            $otomatikIsEmri = intval($data['otomatikIsEmri'] ?? 0);

            if ($araUrunAdiNo <= 0) return response()->json(['success' => false, 'message' => 'Ara ürün seçilmedi.'], 422);

            DB::table('tbKritikStokEsik')->updateOrInsert(
                ['AraUrunAdiNo' => $araUrunAdiNo],
                [
                    'EsikMiktar' => $esikMiktar,
                    'IsEmriAdet' => $isEmriAdet,
                    'OtomatikIsEmri' => $otomatikIsEmri,
                    'Aktif' => 1,
                    'GuncellemeTarihi' => now()
                ]
            );
            return response()->json(['success' => true, 'message' => 'Kritik stok eşiği kaydedildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteThreshold(Request $request)
    {
        try {
            $data = $request->json()->all();
            $no = intval($data['no'] ?? 0);
            if ($no <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz eşik.'], 422);

            DB::table('tbKritikStokEsik')->where('No', $no)->delete();
            return response()->json(['success' => true, 'message' => 'Kritik stok eşiği silindi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function resetThresholds(Request $request)
    {
        try {
            DB::table('tbKritikStokEsik')->delete();
            return response()->json(['success' => true, 'message' => 'Tüm kritik stok eşikleri sıfırlandı.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getCriticalStockAlerts(Request $request)
    {
        try {
            $alerts = DB::table('tbKritikStokUyari as u')
                ->leftJoin('tbAraUrun as a', 'u.AraUrunAdiNo', '=', 'a.No')
                ->select('u.*', 'a.AraUrunAdi')
                ->orderByDesc('u.No')->get();
            return response()->json(['success' => true, 'alerts' => $alerts]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deductStock(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? $data['no'] ?? $data['satirNo'] ?? 0);
            if ($siparisSatirNo <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz sipariş.'], 422);

            $result = $this->deductStockForOrder($siparisSatirNo);
            return response()->json(['success' => true, 'message' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deductStockBulk(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNolari = $data['siparisSatirNolari'] ?? $data['noList'] ?? $data['satirNolar'] ?? [];
            if (empty($siparisSatirNolari)) return response()->json(['success' => false, 'message' => 'Sipariş listesi boş.'], 422);

            $results = [];
            foreach ($siparisSatirNolari as $no) {
                try {
                    $results[intval($no)] = $this->deductStockForOrder(intval($no));
                } catch (\Exception $ex) {
                    $results[intval($no)] = 'Hata: ' . $ex->getMessage();
                }
            }
            return response()->json(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function undoDeductStock(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? $data['no'] ?? $data['satirNo'] ?? 0);
            if ($siparisSatirNo <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz sipariş.'], 422);

            $result = $this->undoStockDeductionForOrder($siparisSatirNo);
            return response()->json(['success' => true, 'message' => $result['message'] ?? 'İptal edildi.', 'restored' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function saveStockCodes(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? $data['no'] ?? $data['satirNo'] ?? 0);
            $stokKodlari = $data['stokKodlari'] ?? [];
            if ($siparisSatirNo <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz sipariş.'], 422);

            DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->update(['StokKodlari' => json_encode($stokKodlari)]);
            return response()->json(['success' => true, 'message' => 'Stok kodları kaydedildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Stock Helpers ──

    private function deductStockForOrder(int $satirNo): string
    {
        return DB::transaction(function () use ($satirNo) {
            $satir = DB::table('tbSiparisSatir')
                ->where('No', $satirNo)
                ->lockForUpdate()
                ->first();
            if (!$satir) throw new \Exception('Sipariş bulunamadı.');

            $adet = intval($satir->Adet ?? 0);
            $eslesenUrunNo = intval($satir->EslesenUrunNo ?? 0);
            $eslesenUrunTur = $satir->EslesenUrunTur ?? '';
            $durum = $satir->Durum ?? '';

            if ($durum !== 'UretimBekliyor' && $durum !== 'UretimdenKarsilaniyor') {
                throw new \Exception('Sadece \'Üretim Bekliyor\' veya \'Üretime Bağlandı\' (GİED) durumundaki siparişler stoktan düşülebilir.');
            }
            if ($eslesenUrunNo <= 0) throw new \Exception('Eşleşen ürün bulunamadı.');
            $activeGiedAllocations = $this->activeGiedAllocationsForOrder($satirNo);

            $araUrunNo = $this->resolveMatchedAraUrunNo($eslesenUrunNo, $eslesenUrunTur);
            if ($araUrunNo <= 0) throw new \Exception('Ara ürün bulunamadı.');

            $stokRows = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $araUrunNo)
                ->where('Adet', '>', 0)
                ->orderBy('No')
                ->lockForUpdate()
                ->get();

            $mevcutStok = intval($stokRows->sum(fn ($row) => intval($row->Adet ?? 0)));
            if ($mevcutStok < $adet) throw new \Exception("Yetersiz stok. Mevcut: {$mevcutStok}, İstenen: {$adet}");

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

            return 'Stok düşüldü.';
        });
    }

    private function undoStockDeductionForOrder(int $satirNo): array
    {
        return DB::transaction(function () use ($satirNo) {
            $satir = DB::table('tbSiparisSatir')
                ->where('No', $satirNo)
                ->lockForUpdate()
                ->first();

            if (!$satir) {
                throw new \Exception('Sipariş bulunamadı.');
            }

            if (($satir->Durum ?? '') !== 'StokKarsilandi') {
                throw new \Exception('Sadece stoktan karşılanmış siparişler geri alınabilir.');
            }

            if (!Schema::hasTable('stock_movements')) {
                throw new \Exception('Stok hareket kaydı bulunamadığı için otomatik geri alma yapılamıyor.');
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
                throw new \Exception('Bu sipariş için geri alınabilir stok çıkış kaydı bulunamadı.');
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
                throw new \Exception('Geri alınacak stok miktarı hesaplanamadı.');
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
                'restored' => $restoredTotal,
                'status' => $updatedSatir->Durum ?? $previousStatus,
                'message' => "Stoktan düşme geri alındı. {$restoredTotal} adet stok iade edildi; sipariş durumu {$previousStatus} yapıldı.",
            ];
        });
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

    private function supportsSplitGiedAllocations(): bool
    {
        return Schema::hasTable(self::GIED_ALLOCATION_TABLE);
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

    private function replaceGiedAllocations(int $siparisNo, array $allocations): void
    {
        if ($siparisNo <= 0 || !$this->supportsSplitGiedAllocations()) {
            return;
        }

        DB::transaction(function () use ($siparisNo, $allocations) {
            DB::table(self::GIED_ALLOCATION_TABLE)
                ->where('SiparisSatirNo', $siparisNo)
                ->update([
                    'Aktif' => 0,
                    'GuncellemeTarihi' => now(),
                ]);

            foreach ($allocations as $alloc) {
                $specNo = intval($alloc['special_production_no'] ?? 0);
                $qty = intval($alloc['quantity'] ?? 0);
                if ($specNo <= 0 || $qty <= 0) {
                    continue;
                }

                DB::table(self::GIED_ALLOCATION_TABLE)->insert([
                    'SiparisSatirNo' => $siparisNo,
                    'OzelUretimSatirNo' => $specNo,
                    'Adet' => $qty,
                    'Aktif' => 1,
                    'OlusturmaTarihi' => now(),
                    'GuncellemeTarihi' => now(),
                ]);
            }
        });
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

        if (is_string($payload)) {
            try {
                $decoded = json_decode($payload, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
            }
        }

        return [];
    }

    private function logCenterEvent(array $attributes): void
    {
        try {
            $this->workOrderEventLogger->log($attributes);
        } catch (\Throwable) {
        }
    }

    private function checkStockThreshold(int $araUrunAdiNo): void
    {
        try {
            $esik = DB::selectOne("SELECT e.No, e.EsikMiktar, e.OtomatikIsEmri, e.IsEmriAdet, IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = ?), 0) AS MevcutStok FROM tbKritikStokEsik e WHERE e.AraUrunAdiNo = ? AND e.Aktif = 1", [$araUrunAdiNo, $araUrunAdiNo]);
            if (!$esik || intval($esik->MevcutStok) >= intval($esik->EsikMiktar)) return;

            $recentAlert = DB::table('tbKritikStokUyari')->where('AraUrunAdiNo', $araUrunAdiNo)->where('OlusturmaTarihi', '>', now()->subHours(24))->exists();
            if ($recentAlert) return;

            DB::table('tbKritikStokUyari')->insert([
                'AraUrunAdiNo' => $araUrunAdiNo, 'EsikMiktar' => $esik->EsikMiktar,
                'MevcutStok' => $esik->MevcutStok, 'Mesaj' => "Kritik stok: {$esik->MevcutStok} kaldı.",
                'OlusturmaTarihi' => now(),
            ]);
        } catch (\Throwable) {}
    }
}
