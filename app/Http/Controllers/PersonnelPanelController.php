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
    private function personelNo(Request $request): int
    {
        return intval($request->user()->personnel_no ?? 0);
    }

    private function pendingApprovalSql(string $column = 'Onay'): string
    {
        return "({$column} IS NULL OR {$column} = 0 OR {$column} = '0' OR LOWER(TRIM(CAST({$column} AS CHAR))) = 'false')";
    }

    private function approvedApprovalSql(string $column = 'Onay'): string
    {
        return "({$column} = 1 OR {$column} = '1' OR LOWER(TRIM(CAST({$column} AS CHAR))) = 'true')";
    }

    /** Dashboard istatistikleri */
    public function dashboardStats(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $aktifGorevler = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->whereRaw($this->pendingApprovalSql())
            ->count();

        $tamamlanan = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->whereRaw($this->approvedApprovalSql())
            ->count();

        $bekleyenAdet = DB::table('tbPersonelGorev')
            ->where('PersonelNo', $personelNo)
            ->whereRaw($this->pendingApprovalSql())
            ->sum('BekleyenAdet');

        $alinabilir = DB::table('tbBolumHavuz as bh')
            ->join('tbPersonel as p', 'bh.BolumAdiNo', '=', 'p.BolumAdiNo')
            ->where('p.PersonelNo', $personelNo)
            ->where('bh.Adet', '>', 0)
            ->count();

        return response()->json([
            'success' => true,
            'aktifGorevler' => $aktifGorevler,
            'tamamlanan' => $tamamlanan,
            'alinabilir' => $alinabilir,
            'bekleyenAdet' => intval($bekleyenAdet),
        ]);
    }

    /** Aktif görevlerim */
    public function myTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $tasks = DB::select("
            SELECT pg.No, pg.Adet, pg.BekleyenAdet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.PersonelNo = ? AND " . $this->pendingApprovalSql('pg.Onay') . "
            ORDER BY STR_TO_DATE(pg.GorevBaslamaTarihi, '%d/%m/%Y %H:%i') DESC, pg.No DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev detay */
    public function taskDetail(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);

        $task = DB::selectOne("
            SELECT pg.*,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihiFormatted,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.No = ? AND pg.PersonelNo = ?
        ", [$id, $personelNo]);

        if (!$task) {
            return response()->json(['success' => false, 'message' => 'Görev bulunamadı.'], 404);
        }

        return response()->json(['success' => true, 'task' => $task]);
    }

    /** Üretim girişi yap (adet tamamla) */
    public function completeProduction(Request $request, $id)
    {
        $adet = intval($request->input('adet', 0));
        if ($adet <= 0) {
            return response()->json(['success' => false, 'message' => 'Geçersiz adet']);
        }

        $personelNo = $this->personelNo($request);
        $sonuc = DB::transaction(function () use ($id, $personelNo, $adet) {
            $gorev = DB::table('tbPersonelGorev')
                ->where('No', $id)
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı'];
            }

            $gerceklesenAdet = min($adet, intval($gorev->BekleyenAdet ?? 0));
            if ($gerceklesenAdet <= 0) {
                return ['success' => false, 'message' => 'Tamamlanacak adet kalmamış.'];
            }

            $yeniBekleyen = max(0, intval($gorev->BekleyenAdet ?? 0) - $gerceklesenAdet);
            $updateData = ['BekleyenAdet' => $yeniBekleyen];

            if ($yeniBekleyen <= 0) {
                $updateData['Onay'] = 'true';
            }

            DB::table('tbPersonelGorev')->where('No', $id)->update($updateData);

            $stockRow = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $gorev->AraUrunAdiNo)
                ->where('BolumAdiNo', $gorev->BolumAdiNo)
                ->lockForUpdate()
                ->first();

            if ($stockRow) {
                DB::table('tbBolumAraStok')
                    ->where('No', $stockRow->No)
                    ->update(['Adet' => intval($stockRow->Adet ?? 0) + $gerceklesenAdet]);
            } else {
                DB::table('tbBolumAraStok')->insert([
                    'BolumAdiNo' => $gorev->BolumAdiNo,
                    'Adet' => $gerceklesenAdet,
                    'AraUrunAdiNo' => $gorev->AraUrunAdiNo,
                    'UrunIDNo' => $gorev->UrunIDNo,
                    'TamponMiktar' => 0,
                ]);
            }

            return [
                'success' => true,
                'message' => $gerceklesenAdet . ' adet üretim kaydedildi.',
                'kalanAdet' => $yeniBekleyen,
            ];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    /** Alınabilir görevler (havuzdan) */
    public function availableTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        $tasks = DB::select("
            SELECT bh.No, bh.Adet, bh.ToplamAdet,
                   CONCAT(IFNULL(bh.GorevBaslangicTarihi, ''), CASE WHEN IFNULL(bh.GorevBaslangicSaati, '') <> '' THEN CONCAT(' ', bh.GorevBaslangicSaati) ELSE '' END) AS GorevBaslangicTarihi,
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
        $personelNo = $this->personelNo($request);
        $adet = intval($request->input('adet', 0));
        $sonuc = DB::transaction(function () use ($personelNo, $adet, $id) {
            $personel = DB::table('tbPersonel')
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$personel) {
                return ['success' => false, 'message' => 'Personel bulunamadı'];
            }

            $havuz = DB::table('tbBolumHavuz')
                ->where('No', $id)
                ->lockForUpdate()
                ->first();

            if (!$havuz) {
                return ['success' => false, 'message' => 'Havuz kaydı bulunamadı'];
            }

            if (intval($personel->BolumAdiNo ?? 0) !== intval($havuz->BolumAdiNo ?? 0)) {
                return ['success' => false, 'message' => 'Farklı bölüm görevini alamazsınız.'];
            }

            $alinacakAdet = $adet;
            if ($alinacakAdet <= 0 || $alinacakAdet > intval($havuz->Adet ?? 0)) {
                $alinacakAdet = intval($havuz->Adet ?? 0);
            }

            if ($alinacakAdet <= 0) {
                return ['success' => false, 'message' => 'Alınacak görev adedi kalmamış.'];
            }

            DB::table('tbBolumHavuz')
                ->where('No', $id)
                ->update(['Adet' => intval($havuz->Adet ?? 0) - $alinacakAdet]);

            DB::table('tbPersonelGorev')->insert([
                'UrunIDNo' => $havuz->UrunIDNo,
                'GorevBaslamaTarihi' => now()->format('d/m/Y H:i'),
                'PersonelNo' => $personelNo,
                'Adet' => $alinacakAdet,
                'BekleyenAdet' => $alinacakAdet,
                'Onay' => 'false',
                'AraUrunAdiNo' => $havuz->AraUrunAdiNo,
                'BolumAdiNo' => $havuz->BolumAdiNo,
            ]);

            return ['success' => true, 'message' => $alinacakAdet . ' adet görev alındı.'];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    /** Tamamlanan görevlerim */
    public function completedTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $tasks = DB::select("
            SELECT pg.No, pg.Adet,
                   IFNULL(pg.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON pg.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON pg.UrunIDNo = u.No
            WHERE pg.PersonelNo = ? AND " . $this->approvedApprovalSql('pg.Onay') . "
            ORDER BY STR_TO_DATE(pg.GorevBaslamaTarihi, '%d/%m/%Y %H:%i') DESC, pg.No DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    /** Görev raporlarım */
    public function taskReport(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $report = DB::select("
            SELECT IFNULL(au.AraUrunAdi,'Bilinmiyor') AS UrunAdi,
                   SUM(pg.Adet) AS ToplamUretim,
                   COUNT(*) AS GorevSayisi,
                   DATE_FORMAT(MIN(STR_TO_DATE(pg.GorevBaslamaTarihi, '%d/%m/%Y %H:%i')), '%d/%m/%Y %H:%i') AS IlkGorev,
                   DATE_FORMAT(MAX(STR_TO_DATE(pg.GorevBaslamaTarihi, '%d/%m/%Y %H:%i')), '%d/%m/%Y %H:%i') AS SonGorev
            FROM tbPersonelGorev pg
            LEFT JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
            WHERE pg.PersonelNo = ? AND " . $this->approvedApprovalSql('pg.Onay') . "
            GROUP BY au.AraUrunAdi
            ORDER BY ToplamUretim DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'report' => $report]);
    }

    /** Bana verilen görevler (tbVerilenGorevler) */
    public function assignedToMe(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $personel = DB::table('tbPersonel')->where('PersonelNo', $personelNo)->first();
        $bolumAdiNo = intval($personel->BolumAdiNo ?? 0);

        $tasks = DB::select("
            SELECT bh.No, bh.Adet, bh.ToplamAdet,
                   CONCAT(IFNULL(bh.GorevBaslangicTarihi, ''), CASE WHEN IFNULL(bh.GorevBaslangicSaati, '') <> '' THEN CONCAT(' ', bh.GorevBaslangicSaati) ELSE '' END) AS GorevBaslangicTarihi,
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
        $personelNo = $this->personelNo($request);

        $messages = DB::select("
            SELECT m.MesajNo, m.Mesaj, m.Tarih, 0 as Okundu,
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
        $personelNo = $this->personelNo($request);
        $mesaj = $request->input('mesaj', '');
        $bolumAdiNo = intval($request->input('bolumAdiNo', 0));

        if (empty($mesaj)) {
            return response()->json(['success' => false, 'message' => 'Mesaj boş olamaz.']);
        }

        DB::table('tbIletisim')->insert([
            'PersonelNo' => $personelNo,
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
            'Mesaj' => $mesaj,
            'Tarih' => now()->format('Y-m-d H:i:s'),
            'Saat' => now()->format('H:i:s'),
        ]);

        return response()->json(['success' => true, 'message' => 'Mesaj gönderildi.']);
    }

    /** Mesaj sil (E7: deleteMesaj.aspx karşılığı) */
    public function deleteMessage(Request $request, $id)
    {
        $deleted = DB::table('tbIletisim')->where('MesajNo', $id)->delete();
        if ($deleted) {
            return response()->json(['success' => true, 'message' => 'Mesaj silindi.']);
        }
        return response()->json(['success' => false, 'message' => 'Mesaj bulunamadı.'], 404);
    }

    /**
     * Personel görevini sil → havuza geri aktarma (E5: PersonelGorev.aspx.cs gridService_RowDeleting)
     * Eğer görev "Üretimde" durumdaysa, üretilen miktarı stoka geri ekler (E6: iptalEdilenStogaEkle)
     */
    public function deleteTask(Request $request, $id)
    {
        $personelNo = $this->personelNo($request);

        $sonuc = DB::transaction(function () use ($id, $personelNo) {
            $gorev = DB::table('tbPersonelGorev')
                ->where('No', $id)
                ->where('PersonelNo', $personelNo)
                ->lockForUpdate()
                ->first();

            if (!$gorev) {
                return ['success' => false, 'message' => 'Görev bulunamadı.'];
            }

            $araUrunAdiNo = intval($gorev->AraUrunAdiNo);
            $adet = intval($gorev->Adet ?? 0);
            $bekleyenAdet = intval($gorev->BekleyenAdet ?? 0);
            $toplam = $adet + $bekleyenAdet;

            // E6: iptalEdilenStogaEkle — Üretimde iken iptal edilen miktar varsa stoktan düş
            // ASP.NET mantığı: Adet-BekleyenAdet = üretilmiş adet, stoğa geri eklenir
            $uretilenAdet = $adet - $bekleyenAdet;
            if ($uretilenAdet > 0) {
                $stok = DB::table('tbBolumAraStok')
                    ->where('AraUrunAdiNo', $araUrunAdiNo)
                    ->first();
                if ($stok) {
                    // Üretimi iptal edilen miktar stoğa geri ekleniyor
                    DB::table('tbBolumAraStok')
                        ->where('AraUrunAdiNo', $araUrunAdiNo)
                        ->update(['Adet' => intval($stok->Adet) + $uretilenAdet]);
                }
            }

            // Görevi sil
            DB::table('tbPersonelGorev')->where('No', $id)->delete();

            // E5: Havuza geri aktarma — minAraUrunUretimiDenetle ile
            $bomService = app(\App\Services\BomService::class);
            $bomService->minAraUrunUretimiDenetle(
                intval($gorev->UrunIDNo ?? 0),
                '',
                strval($araUrunAdiNo),
                $toplam,
                'Personel görev iptal — havuza iade',
                'StokHaric'
            );

            // personelGorevTabloGuncelle çağrısı
            $bomService->personelGorevTabloGuncelle(strval($araUrunAdiNo));

            return ['success' => true, 'message' => 'Görev silindi ve havuza geri aktarıldı.'];
        });

        return response()->json($sonuc, ($sonuc['success'] ?? false) ? 200 : 422);
    }

    /**
     * Genel "üretimde iken iptal edilen stoğa ekle" fonksiyonu (E6).
     * Diğer controller'lardan da çağrılabilir.
     */
    public static function iptalEdilenStogaEkle(int $araUrunAdiNo, int $uretilenAdet): void
    {
        if ($uretilenAdet <= 0) return;

        $stok = DB::table('tbBolumAraStok')
            ->where('AraUrunAdiNo', $araUrunAdiNo)
            ->first();

        if ($stok) {
            DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $araUrunAdiNo)
                ->update(['Adet' => intval($stok->Adet) + $uretilenAdet]);
        }
    }
}
