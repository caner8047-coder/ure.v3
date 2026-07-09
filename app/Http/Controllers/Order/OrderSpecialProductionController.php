<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\LogsStockMovement;
use App\Services\BomService;
use App\Services\WorkOrderEventLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderSpecialProductionController extends Controller
{
    use LogsStockMovement;

    public function __construct(
        protected WorkOrderEventLogger $workOrderEventLogger,
    ) {}

    public function getAvailableSpecialProductions(Request $request)
    {
        try {
            $siparisSatirNo = intval($request->input('siparisSatirNo', 0));
            $query = DB::table('tbOzelUretim')
                ->where('Aktif', 1)
                ->where(function ($q) {
                    $q->whereNull('Durum')->orWhere('Durum', '')->orWhere('Durum', 'DevamEden');
                });

            if ($siparisSatirNo > 0) {
                $satir = DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->first();
                if ($satir && !empty($satir->EslesenUrunNo)) {
                    $query->where('UrunIDNo', $satir->EslesenUrunNo);
                }
            }

            $ozelUretimler = $query->select('No', 'UrunIDNo', 'Aciklama', 'BaslangicTarihi')
                ->orderByDesc('No')->limit(50)->get();

            return response()->json(['success' => true, 'specialProductions' => $ozelUretimler]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function linkOrderToSpecialProduction(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
            $ozelUretimNo = intval($data['ozelUretimNo'] ?? 0);

            if ($siparisSatirNo <= 0 || $ozelUretimNo <= 0) {
                throw new \Exception('Geçersiz parametreler.');
            }

            $satir = DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->lockForUpdate()->first();
            if (!$satir) throw new \Exception('Sipariş satırı bulunamadı.');
            if (!empty($satir->BagliOlduguOzelUretimNo)) {
                throw new \Exception('Bu sipariş zaten bir özel üretime bağlı.');
            }

            $ozelUretim = DB::table('tbOzelUretim')->where('No', $ozelUretimNo)->first();
            if (!$ozelUretim) throw new \Exception('Özel üretim bulunamadı.');

            DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->update([
                'BagliOlduguOzelUretimNo' => $ozelUretimNo, 'GuncellemeTarihi' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Sipariş özel üretime bağlandı.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function linkOrdersToSpecialProductionBulk(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNolari = $data['siparisSatirNolari'] ?? [];
            $ozelUretimNo = intval($data['ozelUretimNo'] ?? 0);

            if (empty($siparisSatirNolari) || $ozelUretimNo <= 0) {
                throw new \Exception('Geçersiz parametreler.');
            }

            $ozelUretim = DB::table('tbOzelUretim')->where('No', $ozelUretimNo)->first();
            if (!$ozelUretim) throw new \Exception('Özel üretim bulunamadı.');

            $linked = 0;
            foreach ($siparisSatirNolari as $no) {
                $satir = DB::table('tbSiparisSatir')->where('No', intval($no))->lockForUpdate()->first();
                if ($satir && empty($satir->BagliOlduguOzelUretimNo)) {
                    DB::table('tbSiparisSatir')->where('No', intval($no))->update([
                        'BagliOlduguOzelUretimNo' => $ozelUretimNo, 'GuncellemeTarihi' => now(),
                    ]);
                    $linked++;
                }
            }

            return response()->json(['success' => true, 'message' => $linked . ' sipariş bağlandı.', 'linked' => $linked]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function cancelWipAllocation(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? 0);
            if ($siparisSatirNo <= 0) throw new \Exception('Geçersiz sipariş.');

            $satir = DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->lockForUpdate()->first();
            if (!$satir) throw new \Exception('Sipariş bulunamadı.');
            if (empty($satir->BagliOlduguOzelUretimNo)) {
                return response()->json(['success' => true, 'message' => 'Zaten bağlı değil.']);
            }

            DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->update([
                'BagliOlduguOzelUretimNo' => null, 'GuncellemeTarihi' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Özel üretim bağlantısı kaldırıldı.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
