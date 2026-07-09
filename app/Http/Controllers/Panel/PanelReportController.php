<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApprovalHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelReportController extends Controller
{
    use ApprovalHelpers;

    private function personelNo(Request $request): int
    {
        return intval($request->user()->PersonelNo ?? $request->user()->id ?? 0);
    }

    private function legacyDateOrderSql(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "datetime(COALESCE({$column}, '01/01/2000 00:00'), ' localtime')";
        }
        return "STR_TO_DATE(IFNULL({$column}, '01/01/2000'), '%d/%m/%Y %H:%i')";
    }

    public function completedTasks(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $tasks = DB::select("
            SELECT gr.No, gr.ToplamAdet, gr.ToplamAdet AS Adet,
                   IFNULL(gr.GorevBaslamaTarihi, '') AS GorevBaslamaTarihi,
                   IFNULL(gr.GorevBitisTarihi, '') AS GorevBitisTarihi,
                   IFNULL(au.AraUrunAdi,'') AS AraUrunAdi,
                   IFNULL(b.BolumAdi,'') AS BolumAdi,
                   IFNULL(u.UrunID,'') AS UrunAdi
            FROM tbGorevler gr
            LEFT JOIN tbAraUrun au ON gr.AraUrunAdiNo = au.No
            LEFT JOIN tbBolum b ON gr.BolumAdiNo = b.No
            LEFT JOIN tbUrunler u ON gr.UrunIDNo = u.No
            WHERE gr.PersonelNo = ? AND COALESCE(gr.ToplamAdet, 0) > 0
            ORDER BY " . $this->legacyDateOrderSql('gr.GorevBitisTarihi') . " DESC, gr.No DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }

    public function taskReport(Request $request)
    {
        $personelNo = $this->personelNo($request);

        $report = DB::select("
            SELECT IFNULL(au.AraUrunAdi,'Bilinmiyor') AS UrunAdi,
                   SUM(gr.ToplamAdet) AS ToplamUretim, COUNT(*) AS GorevSayisi,
                   DATE_FORMAT(MIN(" . $this->legacyDateOrderSql('gr.GorevBaslamaTarihi') . "), '%d/%m/%Y %H:%i') AS IlkGorev,
                   DATE_FORMAT(MAX(" . $this->legacyDateOrderSql('gr.GorevBitisTarihi') . "), '%d/%m/%Y %H:%i') AS SonGorev
            FROM tbGorevler gr
            LEFT JOIN tbAraUrun au ON gr.AraUrunAdiNo = au.No
            WHERE gr.PersonelNo = ? AND COALESCE(gr.ToplamAdet, 0) > 0
            GROUP BY au.AraUrunAdi ORDER BY ToplamUretim DESC
        ", [$personelNo]);

        return response()->json(['success' => true, 'report' => $report]);
    }

    public function assignedToMe(Request $request)
    {
        $personelNo = $this->personelNo($request);
        $componentImageSql = "IFNULL((SELECT TOP 1 au2.Resim FROM tbAraUrun au2 WHERE au2.No = pg.AraUrunAdiNo), '')";
        $productImageSql = "IFNULL((SELECT TOP 1 u2.Resim FROM tbUrunler u2 WHERE u2.No = pg.UrunIDNo), '')";
        $dueTaskSql = "(TRIM(IFNULL(pg.GorevBaslamaTarihi, '')) <> '' AND STR_TO_DATE(pg.GorevBaslamaTarihi, '%d/%m/%Y %H:%i') <= NOW())";

        $readyTasks = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->where('pg.PersonelNo', $personelNo)
            ->where('pg.Adet', '>', 0)
            ->whereRaw($this->productionReadyApprovalSql('pg.Onay'))
            ->selectRaw("pg.No, pg.PersonelNo, pg.UrunIDNo, pg.AraUrunAdiNo, pg.BolumAdiNo, pg.SiparisSatirNo,
                IFNULL(pg.SiparisNo, '') AS SiparisNo, pg.Adet, pg.BekleyenAdet,
                (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                IFNULL(pg.GorevBaslamaTarihi, '') AS GorevTarihi,
                IFNULL(au.AraUrunAdi, '') AS AraUrunAdi, IFNULL(u.UrunID, '') AS UrunAdi,
                COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                'Ara Mamül' AS UrunCesidi, IFNULL(b.BolumAdi, '') AS BolumAdi,
                '' AS Aciklama, 'Admin' AS Veren,
                CASE WHEN {$dueTaskSql} THEN 'Üretime Hazır' ELSE 'Planlandı' END AS Durum,
                CASE WHEN {$dueTaskSql} THEN 1 ELSE 0 END AS Baslatilabilir,
                'personel_gorev' AS Kaynak")
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
            ->orderByDesc('pg.No')->get();

        $waitingTasks = DB::table('tbPersonelGorev as pg')
            ->leftJoin('tbAraUrun as au', 'pg.AraUrunAdiNo', '=', 'au.No')
            ->leftJoin('tbBolum as b', 'pg.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'pg.UrunIDNo', '=', 'u.No')
            ->where('pg.PersonelNo', $personelNo)
            ->whereRaw('COALESCE(pg.Adet, 0) <= 0')
            ->where('pg.BekleyenAdet', '>', 0)
            ->whereRaw($this->assignedWaitingApprovalSql('pg.Onay'))
            ->selectRaw("pg.No, pg.PersonelNo, pg.UrunIDNo, pg.AraUrunAdiNo, pg.BolumAdiNo, pg.SiparisSatirNo,
                IFNULL(pg.SiparisNo, '') AS SiparisNo, pg.Adet, pg.BekleyenAdet,
                (COALESCE(pg.Adet, 0) + COALESCE(pg.BekleyenAdet, 0)) AS ToplamAdet,
                IFNULL(pg.GorevBaslamaTarihi, '') AS GorevTarihi,
                IFNULL(au.AraUrunAdi, '') AS AraUrunAdi, IFNULL(u.UrunID, '') AS UrunAdi,
                COALESCE({$componentImageSql}, {$productImageSql}, '') AS Resim,
                'Ara Mamül' AS UrunCesidi, IFNULL(b.BolumAdi, '') AS BolumAdi,
                '' AS Aciklama, 'Admin' AS Veren,
                'Bekliyor' AS Durum, 0 AS Baslatilabilir, 'personel_gorev' AS Kaynak")
            ->orderByRaw($this->legacyDateOrderSql('pg.GorevBaslamaTarihi') . ' ASC')
            ->orderByDesc('pg.No')->get();

        $tasks = $readyTasks->concat($waitingTasks)->values();

        return response()->json(['success' => true, 'tasks' => $tasks]);
    }
}
