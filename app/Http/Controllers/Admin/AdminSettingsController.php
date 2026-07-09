<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSettingsController extends Controller
{
    public function getProductSettingsLookups()
    {
        $urunler = DB::table('tbUrunler')
            ->whereNotIn('No', [10, 49, 50])
            ->select('No', 'UrunID')
            ->orderBy('UrunID')
            ->get();

        return response()->json(['success' => true, 'urunler' => $urunler]);
    }

    public function getProductSettingsDetails($urunNo)
    {
        $urun = DB::table('tbUrunler')->where('No', $urunNo)->first();
        if (!$urun) {
            return response()->json(['success' => false, 'message' => 'Ürün bulunamadı.']);
        }

        $tablo = [];
        if (!empty($urun->AraAdlarYol)) {
            $parcalar = explode(':', $urun->AraAdlarYol);
            $seenAraUrun = [];
            foreach ($parcalar as $p) {
                if (trim($p) == '') continue;
                $parts = explode('-', $p);
                if (count($parts) >= 2) {
                    $araNo = intval($parts[0]);
                    $adet = intval($parts[1]);

                    if (!in_array($araNo, $seenAraUrun)) {
                        $araUrunData = DB::table('tbAraUrun')->where('No', $araNo)->first();
                        if ($araUrunData) {
                            $tablo[] = [
                                'No' => $urunNo,
                                'UrunID' => $urun->UrunID,
                                'SistemAdi' => $urun->SistemAdi,
                                'SistemKodu' => $urun->SistemKodu,
                                'AraUrunNo' => $araNo,
                                'AraUrun' => $araUrunData->AraUrunAdi,
                                'Adet' => $adet,
                                'Performans' => $araUrunData->Performans
                            ];
                            $seenAraUrun[] = $araNo;
                        }
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'sistemAdi' => $urun->SistemAdi,
            'sistemKodu' => $urun->SistemKodu,
            'tablo' => $tablo
        ]);
    }

    public function updateProductSettings(Request $request)
    {
        try {
            $updates = $request->input('updates', []);
            $urunNo = $request->input('urunNo');
            $sistemAdi = $request->input('sistemAdi');
            $sistemKodu = $request->input('sistemKodu');

            DB::transaction(function () use ($updates, $urunNo, $sistemAdi, $sistemKodu) {
                foreach ($updates as $upd) {
                    if (isset($upd['AraUrunNo']) && isset($upd['Performans'])) {
                        DB::table('tbAraUrun')
                            ->where('No', $upd['AraUrunNo'])
                            ->update(['Performans' => $upd['Performans']]);
                    }
                }

                if ($urunNo) {
                    DB::table('tbUrunler')
                        ->where('No', $urunNo)
                        ->update([
                            'SistemAdi' => empty($sistemAdi) ? null : $sistemAdi,
                            'SistemKodu' => empty($sistemKodu) ? null : $sistemKodu
                        ]);
                }
            });

            return response()->json(['success' => true, 'status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
