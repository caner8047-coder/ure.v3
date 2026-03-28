<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Personnel;

/**
 * Personel Paneli API Controller
 * Görev listeleme, alma, tamamlama, dashboard istatistikleri
 */
class PersonnelPanelController extends Controller
{
    /** Dashboard istatistikleri */
    public function dashboardStats(Request $request)
    {
        $user = $request->user();
        // kullanıcının personnel_no'su ile tbPersonel eşleştirebiliriz
        $personelNo = intval($user->personnel_no ?? 0);

        $aktifGorevler = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where('Onay', 0)
            ->count();

        $tamamlanan = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where('Onay', 1)
            ->count();

        $bekleyenAdet = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->where('Onay', 0)
            ->sum('BekleyenAdet');

        return response()->json([
            'success' => true,
            'aktifGorevler' => $aktifGorevler,
            'tamamlanan' => $tamamlanan,
            'bekleyenAdet' => intval($bekleyenAdet),
        ]);
    }

    /** Aktif görevlerim */
    public function myTasks(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);

        $tasks = DB::select("
            SELECT pg.No, pg.Adet, pg.BekleyenAdet, pg.GorevTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.PersonelNo = ? AND pg.Onay = 0
            ORDER BY pg.GorevTarihi DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev detay */
    public function taskDetail(Request $request, $id)
    {
        $task = DB::selectOne("
            SELECT pg.*, IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.No = ?
        ", [$id]);

        return response()->json(['success' => true, 'task' => $task]);
    }

    /** Üretim girişi yap (adet tamamla) */
    public function completeProduction(Request $request, $id)
    {
        $adet = intval($request->input('adet', 0));
        if ($adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz adet']);
        }

        $gorev = DB::table('tbPersonelGorev')->where('No', $id)->first();
        if (!$gorev) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı']);
        }

        $yeniBekleyen = max(0, $gorev->BekleyenAdet - $adet);
        $updateData = ['BekleyenAdet' => $yeniBekleyen];

        if ($yeniBekleyen <= 0) {
            $updateData['Onay'] = 1;
        }

        DB::table('tbPersonelGorev')->where('No', $id)->update($updateData);

        // Stok güncelle
        DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $gorev->AraUrunAdiNo)
            ->increment('Adet', $adet);

        return response()->json([
            'success' => true,
            'message' => $adet . ' adet üretim kaydedildi.',
            'kalanAdet' => $yeniBekleyen,
        ]);
    }

    /** Alınabilir görevler (havuzdan) */
    public function availableTasks(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        $tasks = DB::select("
            SELECT bh.No, bh.Adet, bh.ToplamAdet, bh.GorevBaslangicTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbBolumHavuz bh
            LEFT JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON bh.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON bh.UrunIDNo = u.No
            WHERE bh.Adet > 0 AND bh.BolumAdiNo = ?
            ORDER BY bh.GorevBaslangicTarihi ASC
        ", [$bolumAdiNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev al (havuzdan personele aktar) */
    public function takeTask(Request $request, $id)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);
        $adet = intval($request->input('adet', 0));

        $havuz = DB::table('tbBolumHavuz')->where('No', $id)->first();
        if (!$havuz) {
            return response()->json(['success' => false, 'message' => 'Havuz kaydı bulunamadı']);
        }

        if ($adet <= 0 || $adet > $havuz->Adet) {
            $adet = $havuz->Adet;
        }

        // Havuzdan düş
        $kalanAdet = $havuz->Adet - $adet;
        DB::table('tbBolumHavuz')->where('No', $id)->update(['Adet' => $kalanAdet]);

        // Personele görev ata
        DB::table('tbPersonelGorev')->insert([
            'UrunIDNo' => $havuz->UrunIDNo,
            'PersonelNo' => $personelNo,
            'Adet' => $adet,
            'BekleyenAdet' => $adet,
            'Onay' => 0,
            'AraUrunAdiNo' => $havuz->AraUrunAdiNo,
            'BolumAdiNo' => $havuz->BolumAdiNo,
            'GorevTarihi' => now(),
        ]);

        return response()->json(['success' => true, 'message' => $adet . ' adet görev alındı.']);
    }

    /** Tamamlanan görevlerim */
    public function completedTasks(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);

        $tasks = DB::select("
            SELECT pg.No, pg.Adet, pg.GorevTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.PersonelNo = ? AND pg.Onay = 1
            ORDER BY pg.GorevTarihi DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev raporlarım */
    public function taskReport(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);

        $report = DB::select("
            SELECT IFNULL(au.AraUrunAdi,'Bilinmiyor') AS UrunAdi,
                   SUM(pg.Adet) AS ToplamUretim,
                   COUNT(*) AS GorevSayisi,
                   MIN(pg.GorevTarihi) AS IlkGorev,
                   MAX(pg.GorevTarihi) AS SonGorev
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            WHERE pg.PersonelNo = ? AND pg.Onay = 1
            GROUP BY au.AraUrunAdi
            ORDER BY ToplamUretim DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'report' => $report]);
    }

    /** Bana verilen görevler (tbVerilenGorevler) */
    public function assignedToMe(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);

        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        $tasks = DB::select("
            SELECT bh.No, bh.Adet, bh.ToplamAdet, bh.GorevBaslangicTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbBolumHavuz bh
            LEFT JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
            LEFT JOIN tbUrunler u ON bh.UrunIDNo = u.No
            WHERE bh.BolumAdiNo = ?
            ORDER BY bh.GorevBaslangicTarihi DESC
        ", [$bolumAdiNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Mesajlar (tbIletisim) */
    public function messages(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);

        $messages = DB::select("
            SELECT m.MesajNo, m.Mesaj, m.Tarih, m.Okundu,
                   IFNULL(p.Ad,'Sistem') AS GonderenAd,
                   IFNULL(p.Soyad,'') AS GonderenSoyad
            FROM tbIletisim m
            LEFT JOIN tbPersonel p ON m.PersonelNo = p.PersonelNo
            WHERE m.BolumAdiNo IN (SELECT BolumAdiNo FROM tbPersonel WHERE PersonelNo = ?)
               OR m.PersonelNo = ?
            ORDER BY m.Tarih DESC
            LIMIT 50
        ", [$personelNo, $personelNo]);

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    /** Mesaj gönder */
    public function sendMessage(Request $request)
    {
        $personelNo = intval($request->user()->personnel_no ?? 0);
        $mesaj = $request->input('mesaj', '');
        $bolumAdiNo = intval($request->input('bolumAdiNo', 0));

        if (empty($mesaj)) {
            return response()->json(['success' => false, 'message' => 'Mesaj boş olamaz.']);
        }

        DB::table('tbIletisim')->insert([
            'PersonelNo' => $personelNo,
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
            'Mesaj' => $mesaj,
            'Tarih' => now(),
            'Okundu' => 0,
        ]);

        return response()->json(['success' => true, 'message' => 'Mesaj gönderildi.']);
    }
}
