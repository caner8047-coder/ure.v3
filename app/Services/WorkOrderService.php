<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * İş Emri Servisi — Legacy tablolar kullanır.
 * BomService'i kullanarak iş emirleri oluşturur.
 */
class WorkOrderService
{
    protected BomService $bomService;

    public function __construct(BomService $bomService)
    {
        $this->bomService = $bomService;
    }

    /**
     * Nihai ürün için iş emri oluştur — BOM ağacını traverse eder.
     */
    public function createWorkOrderForProduct(int $urunIDNo, int $adet, string $stokDurum = 'StokDahil', string $aciklama = ''): array
    {
        $product = DB::table('tbUrunler')->where('No', $urunIDNo)->first();
        if (!$product) {
            return ['success' => false, 'message' => 'Ürün bulunamadı'];
        }

        $tamponDusumleri = [];
        $araAdlarYol = trim($product->AraAdlarYol ?? '');

        if (empty($araAdlarYol)) {
            return ['success' => false, 'message' => 'BOM yolu (AraAdlarYol) boş'];
        }

        // Top-level ara ürünü bul  
        $adimlar = preg_split('/[→>]|->/', $araAdlarYol);
        foreach ($adimlar as $adim) {
            $araUrunAdi = trim($adim);
            if (empty($araUrunAdi)) continue;
            $araUrun = DB::table('tbAraUrun')->where('AraUrunAdi', $araUrunAdi)->first();
            if ($araUrun) {
                $yol = $this->bomService->tumYolHazirla(strval($araUrun->No));
                $this->bomService->isEmriVerRecursive(
                    strval($araUrun->No), $adet, $aciklama,
                    $yol, $urunIDNo, $stokDurum, $tamponDusumleri
                );
            }
        }

        // Ana görev kaydı
        $gorevNo = DB::table('tbGorevler')->insertGetId([
            'UrunIDNo' => $urunIDNo,
            'ToplamAdet' => $adet,
            'GorevBaslamaTarihi' => now(),
        ], 'No');

        return [
            'success' => true,
            'gorevNo' => $gorevNo,
            'tamponDusumleri' => $tamponDusumleri,
        ];
    }

    /**
     * Ara ürün (bileşen) için doğrudan iş emri oluştur.
     */
    public function createWorkOrderForComponent(int $araUrunNo, int $adet, string $stokDurum = 'StokDahil'): array
    {
        $araUrun = DB::table('tbAraUrun')->where('No', $araUrunNo)->first();
        if (!$araUrun) {
            return ['success' => false, 'message' => 'Ara ürün bulunamadı'];
        }

        $tamponDusumleri = [];
        $bolumAdiNo = intval($araUrun->BolumAdiNo ?? 0);

        // Havuza ekle
        DB::table('tbBolumHavuz')->insert([
            'UrunIDNo' => 0,
            'AraUrunAdiNo' => $araUrunNo,
            'ToplamAdet' => $adet,
            'Adet' => $adet,
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
            'GorevBaslangicTarihi' => now(),
        ]);

        // Alt bileşenleri traverse et
        $yol = $this->bomService->tumYolHazirla(strval($araUrunNo));
        $this->bomService->isEmriVerRecursive(
            strval($araUrunNo), $adet, '', $yol, 0, $stokDurum, $tamponDusumleri
        );

        $gorevNo = DB::table('tbGorevler')->insertGetId([
            'UrunIDNo' => 0,
            'ToplamAdet' => $adet,
            'GorevBaslamaTarihi' => now(),
            'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
        ], 'No');

        return [
            'success' => true,
            'gorevNo' => $gorevNo,
            'tamponDusumleri' => $tamponDusumleri,
        ];
    }
}
