<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsStockMovement;
use App\Services\BomService;
use App\Services\WorkOrderEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderStockController extends Controller
{
    use LogsStockMovement;

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
            if ($araUrunAdiNo <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz ürün.'], 422);

            $existing = DB::table('tbKritikStokEsik')->where('AraUrunAdiNo', $araUrunAdiNo)->first();
            if ($existing) {
                DB::table('tbKritikStokEsik')->where('No', $existing->No)->update([
                    'EsikMiktar' => $esikMiktar, 'Aktif' => intval($data['aktif'] ?? 1),
                    'OtomatikIsEmri' => intval($data['otomatikIsEmri'] ?? 0),
                    'IsEmriAdet' => intval($data['isEmriAdet'] ?? 0),
                ]);
            } else {
                DB::table('tbKritikStokEsik')->insert([
                    'AraUrunAdiNo' => $araUrunAdiNo, 'EsikMiktar' => $esikMiktar,
                    'Aktif' => intval($data['aktif'] ?? 1),
                    'OtomatikIsEmri' => intval($data['otomatikIsEmri'] ?? 0),
                    'IsEmriAdet' => intval($data['isEmriAdet'] ?? 0),
                ]);
            }
            return response()->json(['success' => true, 'message' => 'Eşik kaydedildi.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteThreshold(Request $request)
    {
        $data = $request->json()->all();
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz ID.'], 422);
        DB::table('tbKritikStokEsik')->where('No', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Eşik silindi.']);
    }

    public function resetThresholds(Request $request)
    {
        DB::table('tbKritikStokEsik')->delete();
        return response()->json(['success' => true, 'message' => 'Tüm eşikler silindi.']);
    }

    public function getCriticalStockAlerts(Request $request)
    {
        $alerts = DB::select("
            SELECT e.No, e.AraUrunAdiNo, a.AraUrunAdi, e.EsikMiktar, e.OtomatikIsEmri, e.IsEmriAdet,
                   IFNULL((SELECT SUM(s.Adet) FROM tbBolumAraStok s WHERE s.AraUrunAdiNo = e.AraUrunAdiNo), 0) AS MevcutStok
            FROM tbKritikStokEsik e
            LEFT JOIN tbAraUrun a ON e.AraUrunAdiNo = a.No
            WHERE e.Aktif = 1
        ");
        $triggered = collect($alerts)->filter(fn ($a) => $a->MevcutStok < $a->EsikMiktar);
        return response()->json(['success' => true, 'alerts' => $triggered->values(), 'total' => count($alerts)]);
    }

    public function deductStock(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
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
            $siparisSatirNolari = $data['siparisSatirNolari'] ?? [];
            if (empty($siparisSatirNolari)) return response()->json(['success' => false, 'message' => 'Sipariş listesi boş.'], 422);

            $results = [];
            foreach ($siparisSatirNolari as $no) {
                $results[intval($no)] = $this->deductStockForOrder(intval($no));
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
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
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
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
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
            $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->lockForUpdate()->first();
            if (!$satir) throw new \Exception('Sipariş satırı bulunamadı.');
            if (intval($satir->StokDusuldu ?? 0) === 1) return 'Zaten düşülmüş.';

            $urunID = trim((string) ($satir->EslesenUrunNo ?? ''));
            $urunTur = trim((string) ($satir->EslesenUrunTur ?? 'Nihai'));
            $adet = max(1, intval($satir->Adet ?? 1));

            if (empty($urunID) || intval($urunID) <= 0) throw new \Exception('Eşleşen ürün bulunamadı.');

            $bomService = app(BomService::class);
            $yol = $bomService->tumYolHazirla($urunID);
            if (empty($yol)) throw new \Exception('Ürün ağacı bulunamadı.');

            $bomService->stokDus($urunID, $yol, $adet);
            DB::table('tbSiparisSatir')->where('No', $satirNo)->update(['StokDusuldu' => 1, 'GuncellemeTarihi' => now()]);

            $this->checkStockThreshold(intval($urunID));

            return 'Stok düşüldü.';
        });
    }

    private function undoStockDeductionForOrder(int $satirNo): array
    {
        return DB::transaction(function () use ($satirNo) {
            $satir = DB::table('tbSiparisSatir')->where('No', $satirNo)->lockForUpdate()->first();
            if (!$satir) throw new \Exception('Sipariş satırı bulunamadı.');
            if (intval($satir->StokDusuldu ?? 0) !== 1) return ['message' => 'Stok zaten düşülmemiş.'];

            $urunID = trim((string) ($satir->EslesenUrunNo ?? ''));
            $adet = max(1, intval($satir->Adet ?? 1));

            if (empty($urunID) || intval($urunID) <= 0) throw new \Exception('Eşleşen ürün bulunamadı.');

            $bomService = app(BomService::class);
            $yol = $bomService->tumYolHazirla($urunID);
            $bomService->stokGeriAl($urunID, $yol, $adet);
            DB::table('tbSiparisSatir')->where('No', $satirNo)->update(['StokDusuldu' => 0, 'GuncellemeTarihi' => now()]);

            return ['message' => 'Stok iade edildi.'];
        });
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
