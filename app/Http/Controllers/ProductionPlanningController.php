<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\BomService;

class ProductionPlanningController extends Controller
{
    /**
     * Seçilen personelin görevlerini tarih bazlı getirir (Drag-Drop UI için).
     */
    public function getPersonnelTasks(Request $request, $personelNo)
    {
        $query = DB::table('tbPersonelGorev as pg')
            ->join('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->join('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->where('pg.PersonelNo', $personelNo)
            ->select(
                'pg.No',
                'pg.GorevBaslamaTarihi',
                'au.AraUrunAdi',
                'au.No as AraUrunAdiNo',
                'b.BolumAdi',
                'pg.Adet',
                'pg.BekleyenAdet',
                'pg.Onay',
                'au.Yol'
            )
            ->orderByRaw("STR_TO_DATE(SUBSTRING(pg.GorevBaslamaTarihi, 1, 10), '%d/%m/%Y') ASC")
            ->get();

        return response()->json(['success' => true, 'data' => $query]);
    }

    /**
     * Personel listesini getirir.
     */
    public function getPersonnelList()
    {
        $personnel = DB::table('tbPersonel')
            ->where('BolumAdiNo', '!=', 0)
            ->select('PersonelNo', DB::raw("CONCAT(Ad, ' ', Soyad) as PersonelAdi"))
            ->orderBy('Ad')
            ->get();

        return response()->json(['success' => true, 'data' => $personnel]);
    }

    /**
     * Görev adetini +1 artırır.
     */
    public function incrementTask($taskId)
    {
        DB::table('tbPersonelGorev')
            ->where('No', $taskId)
            ->increment('Adet', 1);

        return response()->json(['success' => true]);
    }

    /**
     * Görev adetini -1 azaltır (minimum 0 olabilir).
     */
    public function decrementTask($taskId)
    {
        DB::table('tbPersonelGorev')
            ->where('No', $taskId)
            ->where('Adet', '>', 0)
            ->decrement('Adet', 1);

        return response()->json(['success' => true]);
    }

    /**
     * Görevi siler, adetleri havuza geri yükler ve stok/tampon güncellemelerini tetikler.
     */
    public function deleteTask($taskId)
    {
        return DB::transaction(function () use ($taskId) {
            $task = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            if (!$task) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            $araUrunAdiNo = $task->AraUrunAdiNo;
            $adet = $task->Adet;
            $bekleyenAdet = $task->BekleyenAdet ?? 0;
            $toplam = $adet + $bekleyenAdet;

            // Görevi sil
            DB::table('tbPersonelGorev')->where('No', $taskId)->delete();

            // Havuza geri aktarma (minAraUrunUretimiDenetle)
            $bomService = app(BomService::class);
            $bomService->minAraUrunUretimiDenetle(
                'Ara Mamül', 
                '', 
                strval($araUrunAdiNo), 
                $toplam, 
                'Personel üzerinden alınan görev', 
                'StokHaric'
            );

            // Geriye dönük veya sonrasındaki görevleri güncelle
            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            return response()->json(['success' => true]);
        });
    }

    /**
     * Görevin tarihini değiştirir (Drag-Drop update).
     */
    public function updateTaskDate(Request $request, $taskId)
    {
        $newDate = $request->input('date'); // Beklenen format YYYY-MM-DD
        if (!$newDate) {
            return response()->json(['success' => false, 'message' => 'Tarih gerekli.']);
        }

        // Formatı DD/MM/YYYY hh:mm yap
        $formattedDate = \Carbon\Carbon::parse($newDate)->format('d/m/Y');

        return DB::transaction(function () use ($taskId, $formattedDate) {
            $mevcutGorev = DB::table('tbPersonelGorev')->where('No', $taskId)->first();
            if (!$mevcutGorev) {
                return response()->json(['success' => false, 'message' => 'Görev bulunamadı.']);
            }

            $araUrunAdiNo = $mevcutGorev->AraUrunAdiNo;
            $adet = $mevcutGorev->Adet + ($mevcutGorev->BekleyenAdet ?? 0);
            
            // Aynı tarihte aynı parça için başka görev var mı?
            $mevcutKayit = DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->where('PersonelNo', $mevcutGorev->PersonelNo)
                ->whereRaw("SUBSTRING(GorevBaslamaTarihi, 1, 10) = ?", [$formattedDate])
                ->where('No', '!=', $taskId)
                ->first();

            if ($mevcutKayit) {
                // Diğer kayıtla birleştir
                $bomService = app(BomService::class);
                $uretilebilecekMax = $bomService->adetBelirle(strval($araUrunAdiNo));
                
                $mevcutAdet = $mevcutKayit->Adet;
                $mevcutBekleyen = $mevcutKayit->BekleyenAdet ?? 0;
                
                $toplamHedef = $mevcutAdet + $mevcutBekleyen + $adet;
                
                if ($uretilebilecekMax < 0 || $uretilebilecekMax >= $toplamHedef) {
                    $newAdet = $toplamHedef;
                    $newBekleyen = 0;
                } else {
                    $newAdet = $uretilebilecekMax;
                    $newBekleyen = $toplamHedef - $uretilebilecekMax;
                }
                
                DB::table('tbPersonelGorev')->where('No', $mevcutKayit->No)->update([
                    'Adet' => $newAdet,
                    'BekleyenAdet' => $newBekleyen
                ]);
                
                // Eski görevi sil
                DB::table('tbPersonelGorev')->where('No', $taskId)->delete();
            } else {
                // Sadece tarihi güncelle
                $yeniTarih = $formattedDate . ' ' . now()->format('H:i');
                DB::table('tbPersonelGorev')->where('No', $taskId)->update([
                    'GorevBaslamaTarihi' => $yeniTarih
                ]);
            }

            return response()->json(['success' => true]);
        });
    }
}
