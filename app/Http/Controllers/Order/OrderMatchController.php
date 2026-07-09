<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Services\OrderSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderMatchController extends Controller
{
    public function __construct(
        protected OrderSyncService $orderSync,
    ) {}

    public function matchProduct(Request $request)
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
            if (!$order) throw new \Exception('Sipariş satırı bulunamadı.');
            if (intval($order->Aktif ?? 0) !== 1 || ($order->Durum ?? '') !== 'UretimBekliyor') {
                throw new \Exception('Eşleşme sadece üretim bekleyen aktif siparişlerde değiştirilebilir.');
            }
            if (!empty($order->BagliOlduguOzelUretimNo)) {
                throw new \Exception('GİED ile rezerve edilmiş siparişin eşleşmesi değiştirilemez.');
            }

            DB::table('tbSiparisSatir')->where('No', $siparisSatirNo)->update([
                'EslesenUrunNo' => $eslesenUrunNo, 'EslesenUrunTur' => $eslesenUrunTur,
                'EslesmePuani' => 100, 'EslesmeYontemi' => 'Manuel', 'GuncellemeTarihi' => now()
            ]);

            $urunAdi = $order->UrunAdi ?? '';
            $cachedProductName = '';
            $urun = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->first();
            if ($urun) $cachedProductName = !empty($urun->SistemAdi) ? $urun->SistemAdi : ($urun->UrunID ?? '');

            $updatedCount = 0;
            if (!empty($urunAdi)) {
                DB::statement("INSERT INTO tbUrunEslestirmeOnbellek (ExcelUrunAdi, EslesenUrunNo, EslesenUrunTur) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE EslesenUrunNo = VALUES(EslesenUrunNo), EslesenUrunTur = VALUES(EslesenUrunTur), OlusturmaTarihi = CURRENT_TIMESTAMP", [$urunAdi, $eslesenUrunNo, $eslesenUrunTur]);
                $updatedCount = DB::table('tbSiparisSatir')->where('UrunAdi', $urunAdi)->where('EslesmeYontemi', '!=', 'Manuel')->where('Aktif', 1)->where('Durum', 'UretimBekliyor')->whereNull('BagliOlduguOzelUretimNo')->where('No', '!=', $siparisSatirNo)->update([
                    'EslesenUrunNo' => $eslesenUrunNo, 'EslesenUrunTur' => $eslesenUrunTur,
                    'EslesmePuani' => 100, 'EslesmeYontemi' => 'Onbellek', 'GuncellemeTarihi' => now()
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Eşleştirme kaydedildi.', 'updatedCount' => $updatedCount, 'cachedExcelName' => $urunAdi, 'cachedProductName' => $cachedProductName]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function clearOrderMatch(Request $request)
    {
        try {
            $data = $request->json()->all();
            $siparisSatirNo = intval($data['siparisSatirNo'] ?? $data['no'] ?? 0);
            $deleteCache = !array_key_exists('deleteCache', $data) || (bool) $data['deleteCache'];
            if ($siparisSatirNo <= 0) throw new \Exception('Geçersiz sipariş satırı.');

            $result = $this->orderSync->clearOrderMatch($siparisSatirNo, $deleteCache);
            return response()->json(['success' => true, 'message' => 'Eşleşme iptal edildi.', 'deletedChildren' => (int) ($result['deletedChildren'] ?? 0), 'deletedCache' => (int) ($result['deletedCache'] ?? 0)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function rematchOrders(Request $request)
    {
        try {
            $dbProducts = DB::table('tbUrunler')->select('No', 'UrunID', 'SistemAdi')->get();
            $pendingOrders = DB::table('tbSiparisSatir')
                ->where('Aktif', 1)->where('Durum', 'UretimBekliyor')
                ->where(function ($q) { $q->whereNull('EslesenUrunNo')->orWhere('EslesenUrunNo', 0); })
                ->whereNull('BagliOlduguOzelUretimNo')
                ->get();

            $matched = 0;
            foreach ($pendingOrders as $order) {
                $excelName = trim((string) ($order->UrunAdi ?? ''));
                if (empty($excelName)) continue;

                $cached = DB::table('tbUrunEslestirmeOnbellek')->where('ExcelUrunAdi', $excelName)->first();
                if ($cached) {
                    DB::table('tbSiparisSatir')->where('No', $order->No)->update([
                        'EslesenUrunNo' => $cached->EslesenUrunNo, 'EslesenUrunTur' => $cached->EslesenUrunTur,
                        'EslesmePuani' => 100, 'EslesmeYontemi' => 'Onbellek', 'GuncellemeTarihi' => now()
                    ]);
                    $matched++;
                    continue;
                }

                $match = $this->findBestMatch($excelName, $dbProducts);
                if ($match) {
                    DB::table('tbSiparisSatir')->where('No', $order->No)->update([
                        'EslesenUrunNo' => $match['no'], 'EslesenUrunTur' => 'Nihai',
                        'EslesmePuani' => $match['score'], 'EslesmeYontemi' => 'Otomatik', 'GuncellemeTarihi' => now()
                    ]);
                    $matched++;
                }
            }

            return response()->json(['success' => true, 'message' => $matched . ' sipariş yeniden eşleştirildi.', 'matched' => $matched, 'total' => $pendingOrders->count()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getMatchCache(Request $request)
    {
        $cache = DB::table('tbUrunEslestirmeOnbellek')
            ->leftJoin('tbUrunler as u', 'tbUrunEslestirmeOnbellek.EslesenUrunNo', '=', 'u.No')
            ->select('tbUrunEslestirmeOnbellek.*', 'u.UrunID as EslesenUrunAdi')
            ->orderByDesc('tbUrunEslestirmeOnbellek.No')->get();
        return response()->json(['success' => true, 'cache' => $cache]);
    }

    public function addMatchCache(Request $request)
    {
        $data = $request->json()->all();
        $excelName = trim((string) ($data['excelName'] ?? ''));
        $urunNo = intval($data['urunNo'] ?? 0);
        if (empty($excelName) || $urunNo <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz parametreler.'], 422);

        DB::statement("INSERT INTO tbUrunEslestirmeOnbellek (ExcelUrunAdi, EslesenUrunNo, EslesenUrunTur) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE EslesenUrunNo = VALUES(EslesenUrunNo), EslesenUrunTur = VALUES(EslesenUrunTur), OlusturmaTarihi = CURRENT_TIMESTAMP", [$excelName, $urunNo, 'Nihai']);
        return response()->json(['success' => true, 'message' => 'Önbelleğe eklendi.']);
    }

    public function deleteMatchCache(Request $request)
    {
        $data = $request->json()->all();
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) return response()->json(['success' => false, 'message' => 'Geçersiz ID.'], 422);
        DB::table('tbUrunEslestirmeOnbellek')->where('No', $id)->delete();
        return response()->json(['success' => true, 'message' => 'Önbellek kaydı silindi.']);
    }

    // ── Matching Helpers ──

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
        if ($excelName === $dbName) return 100;
        if (str_contains($excelName, $dbName) || str_contains($dbName, $excelName)) return 85;

        $excelWords = array_filter(explode(' ', $excelName), fn($w) => mb_strlen($w) > 2);
        $dbWords = array_filter(explode(' ', $dbName), fn($w) => mb_strlen($w) > 2);
        if (empty($excelWords) || empty($dbWords)) return 0;

        $matchCount = 0;
        foreach ($dbWords as $dw) {
            foreach ($excelWords as $ew) {
                if (str_contains($ew, $dw) || str_contains($dw, $ew)) { $matchCount++; break; }
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
            foreach (['UrunID', 'SistemAdi'] as $field) {
                $dbName = $this->normalizeText($dbProd->$field ?? '');
                if (empty($dbName)) continue;
                $score = $this->calculateMatchScore($normalized, $dbName);
                if ($score > $bestScore && $score >= 40) {
                    $bestScore = $score;
                    $bestMatch = ['no' => $dbProd->No, 'name' => $dbProd->UrunID, 'sistemAdi' => $dbProd->SistemAdi ?? '', 'score' => $score];
                }
            }
        }
        return $bestMatch;
    }
}
