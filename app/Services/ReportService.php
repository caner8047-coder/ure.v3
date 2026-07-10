<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportService
{
    /**
     * Generate Stock Status CSV data.
     */
    public function generateStockCsv(): string
    {
        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM for Turkish character support in Excel
        $csv .= "Bölüm;Ara Ürün No;Ara Ürün Adı;Sistem Kodu;Toplam Adet;Tampon Miktarı;Boşta Adet;Durum\n";

        $rows = DB::table('tbBolumAraStok as s')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'a.AraUrunAdi', '=', 'u.UrunID')
            ->select(
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                's.AraUrunAdiNo',
                DB::raw("IFNULL(a.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(u.SistemKodu, '') as SistemKodu"),
                DB::raw("IFNULL(s.Adet, 0) as ToplamAdet"),
                DB::raw("IFNULL(s.TamponMiktar, 0) as TamponMiktar")
            )
            ->orderBy('b.BolumAdi')
            ->orderBy('a.AraUrunAdi')
            ->get();

        foreach ($rows as $row) {
            $total = intval($row->ToplamAdet);
            $buffer = intval($row->TamponMiktar);
            $free = max(0, min($total, $buffer));
            $status = $total < $buffer ? 'CRITICAL STOK UYARISI' : 'Normal';

            $csv .= sprintf(
                '"%s";%d;"%s";"%s";%d;%d;%d;"%s"' . "\n",
                str_replace('"', '""', $row->BolumAdi),
                $row->AraUrunAdiNo,
                str_replace('"', '""', $row->AraUrunAdi),
                str_replace('"', '""', $row->SistemKodu),
                $total,
                $buffer,
                $free,
                $status
            );
        }

        return $csv;
    }

    /**
     * Generate Stock Status printable HTML.
     */
    public function generateStockHtml(): string
    {
        $rows = DB::table('tbBolumAraStok as s')
            ->leftJoin('tbAraUrun as a', 's.AraUrunAdiNo', '=', 'a.No')
            ->leftJoin('tbBolum as b', 's.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbUrunler as u', 'a.AraUrunAdi', '=', 'u.UrunID')
            ->select(
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                's.AraUrunAdiNo',
                DB::raw("IFNULL(a.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(u.SistemKodu, '') as SistemKodu"),
                DB::raw("IFNULL(s.Adet, 0) as ToplamAdet"),
                DB::raw("IFNULL(s.TamponMiktar, 0) as TamponMiktar")
            )
            ->orderBy('b.BolumAdi')
            ->orderBy('a.AraUrunAdi')
            ->get();

        $tableRows = '';
        foreach ($rows as $row) {
            $total = intval($row->ToplamAdet);
            $buffer = intval($row->TamponMiktar);
            $free = max(0, min($total, $buffer));
            $isCritical = $total < $buffer;
            $statusBadge = $isCritical 
                ? '<span class="badge badge-danger">Kritik</span>' 
                : '<span class="badge badge-success">Normal</span>';

            $tableRows .= sprintf(
                '<tr class="%s">
                    <td>%s</td>
                    <td>%d</td>
                    <td class="font-semibold">%s</td>
                    <td>%s</td>
                    <td class="text-right">%d</td>
                    <td class="text-right">%d</td>
                    <td class="text-right">%d</td>
                    <td class="text-center">%s</td>
                </tr>',
                $isCritical ? 'bg-red-50' : '',
                e($row->BolumAdi),
                $row->AraUrunAdiNo,
                e($row->AraUrunAdi),
                e($row->SistemKodu),
                $total,
                $buffer,
                $free,
                $statusBadge
            );
        }

        return $this->getPrintTemplate('Mevcut Stok Durumu Raporu', $tableRows, [
            'Bölüm', 'Ara Ürün No', 'Ara Ürün Adı', 'Sistem Kodu', 'Toplam Adet', 'Tampon Miktarı', 'Boşta Adet', 'Durum'
        ]);
    }

    /**
     * Generate Production Status CSV data.
     */
    public function generateProductionCsv(): string
    {
        $csv = "\xEF\xBB\xBF";
        $csv .= "Görev No;Tarih;Personel Adı;Bölüm;Ara Ürün Adı;Hazır Adet;Bekleyen Adet;Durum\n";

        $nameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, ''))"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')))";

        $rows = DB::table('tbPersonelGorev as g')
            ->leftJoin('tbPersonel as p', 'g.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'g.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as a', 'g.AraUrunAdiNo', '=', 'a.No')
            ->select(
                'g.No as GorevNo',
                'g.GorevBaslamaTarihi',
                DB::raw("{$nameSql} as PersonelAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(g.Adet, 0) as HazirAdet"),
                DB::raw("IFNULL(g.BekleyenAdet, 0) as BekleyenAdet"),
                'g.Onay'
            )
            ->orderBy('g.GorevBaslamaTarihi', 'desc')
            ->get();

        foreach ($rows as $row) {
            $csv .= sprintf(
                '%d;"%s";"%s";"%s";"%s";%d;%d;"%s"' . "\n",
                $row->GorevNo,
                $row->GorevBaslamaTarihi,
                str_replace('"', '""', $row->PersonelAdi),
                str_replace('"', '""', $row->BolumAdi),
                str_replace('"', '""', $row->AraUrunAdi),
                intval($row->HazirAdet),
                intval($row->BekleyenAdet),
                $row->Onay
            );
        }

        return $csv;
    }

    /**
     * Generate Production Status printable HTML.
     */
    public function generateProductionHtml(): string
    {
        $nameSql = DB::connection()->getDriverName() === 'sqlite'
            ? "TRIM(IFNULL(p.Ad, '') || ' ' || IFNULL(p.Soyad, ''))"
            : "TRIM(CONCAT(IFNULL(p.Ad, ''), ' ', IFNULL(p.Soyad, '')))";

        $rows = DB::table('tbPersonelGorev as g')
            ->leftJoin('tbPersonel as p', 'g.PersonelNo', '=', 'p.PersonelNo')
            ->leftJoin('tbBolum as b', 'g.BolumAdiNo', '=', 'b.No')
            ->leftJoin('tbAraUrun as a', 'g.AraUrunAdiNo', '=', 'a.No')
            ->select(
                'g.No as GorevNo',
                'g.GorevBaslamaTarihi',
                DB::raw("{$nameSql} as PersonelAdi"),
                DB::raw("IFNULL(b.BolumAdi, '') as BolumAdi"),
                DB::raw("IFNULL(a.AraUrunAdi, '') as AraUrunAdi"),
                DB::raw("IFNULL(g.Adet, 0) as HazirAdet"),
                DB::raw("IFNULL(g.BekleyenAdet, 0) as BekleyenAdet"),
                'g.Onay'
            )
            ->orderBy('g.GorevBaslamaTarihi', 'desc')
            ->get();

        $tableRows = '';
        foreach ($rows as $row) {
            $tableRows .= sprintf(
                '<tr>
                    <td>%d</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="font-semibold">%s</td>
                    <td class="text-right">%d</td>
                    <td class="text-right">%d</td>
                    <td class="text-center"><span class="badge">%s</span></td>
                </tr>',
                $row->GorevNo,
                e($row->GorevBaslamaTarihi),
                e($row->PersonelAdi),
                e($row->BolumAdi),
                e($row->AraUrunAdi),
                intval($row->HazirAdet),
                intval($row->BekleyenAdet),
                e($row->Onay)
            );
        }

        return $this->getPrintTemplate('Aktif Üretim Planlama & Görev Raporu', $tableRows, [
            'Görev No', 'Tarih', 'Personel Adı', 'Bölüm', 'Ara Ürün Adı', 'Hazır Adet', 'Bekleyen Adet', 'Onay'
        ]);
    }

    /**
     * Shared print layout HTML wrapper.
     */
    protected function getPrintTemplate(string $title, string $rows, array $headers): string
    {
        $headerHtml = '';
        foreach ($headers as $h) {
            $headerHtml .= "<th>{$h}</th>";
        }

        $date = now()->format('d.m.Y H:i:s');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: 'Outfit', 'Inter', -apple-system, sans-serif; background-color: #ffffff; color: #1e293b; margin: 0; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0; }
        .header .meta { font-size: 13px; color: #64748b; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #f8fafc; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; text-align: left; }
        td { padding: 12px 16px; font-size: 14px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        tr:hover { background-color: #f8fafc; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-semibold { font-weight: 600; color: #0f172a; }
        .bg-red-50 { background-color: #fef2f2 !important; }
        .badge { display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 9999px; text-transform: uppercase; }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        @media print {
            body { padding: 0; }
            button { display: none; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{$title}</h1>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #64748b;">ZemuRetim MES Raporlama Servisi</p>
        </div>
        <div class="meta">
            <div><strong>Tarih:</strong> {$date}</div>
            <div><strong>Sistem:</strong> ZemuRetim v3</div>
            <button onclick="window.print()" style="margin-top: 10px; padding: 8px 16px; background-color: #0f172a; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">Yazdır / PDF Olarak Kaydet</button>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                {$headerHtml}
            </tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</body>
</html>
HTML;
    }
}
