<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * BOM (Bill of Materials) Engine — AnaSayfa.aspx.cs + SiparisApi.ashx'den birebir çeviri.
 *
 * Recursive fonksiyonlar:
 *  - birAdimOncesiUrunAdlari: Alt bileşen No'larını döndürür
 *  - oncekiUrunAdlariBul: Recursive tüm alt bileşenleri bulur
 *  - tumYolHazirla: BOM path string oluşturur
 *  - uretimAdetBelirle: Çarpan uygular
 *  - kacParca: Bileşen çarpanı
 *  - adetBelirle: Stok bazlı üretilebilir adet (darboğaz)
 *  - araStokTamponAzalt: Stok tamponu düşer
 *  - minAraUrunUretimiDenetle: Orchestrator
 *  - isEmriVerRecursive: Recursive görev oluşturma
 *  - restoreTamponFromJson: İptal sırasında tampon geri yükleme
 */
class BomService
{
    public function resolveLegacyProductNo(int|string $urunIDNo): int
    {
        if (is_int($urunIDNo)) {
            return $urunIDNo;
        }

        $raw = trim((string) $urunIDNo);
        if ($raw === '') {
            return 0;
        }

        if (ctype_digit($raw)) {
            return intval($raw);
        }

        $match = DB::table('tbUrunler')->where('UrunID', $raw)->value('No');

        return $match !== null ? intval($match) : 502;
    }

    /**
     * Alt bileşen No'ları — tbAraUrun.Yol'dan parse eder.
     * Yol formatı: "3-4:4-8" → bileşen 3 (4 adet) ve bileşen 4 (8 adet)
     * Döndürür: "3:4" (sadece No'lar)
     */
    public function birAdimOncesiUrunAdlari(string $refUrunAdiNo): string
    {
        $araNo = intval($refUrunAdiNo);
        $yol = DB::table('tbAraUrun')->where('No', $araNo)->value('Yol') ?? '';
        $yol = trim($yol);
        if (empty($yol)) return '';

        $result = '';
        if (str_contains($yol, ':')) {
            foreach (explode(':', $yol) as $seg) {
                $result .= explode('-', $seg)[0] . ':';
            }
        } else {
            $result .= trim(explode('-', $yol)[0]) . ':';
        }

        return rtrim($result, ':');
    }

    /**
     * Recursive tüm alt bileşenleri bulur.
     */
    public function oncekiUrunAdlariBul(string $refUrunAdiNo, int $depth = 0): string
    {
        if ($depth > 20) return $refUrunAdiNo;
        $str = $refUrunAdiNo;
        $source = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if ($source === '') return $refUrunAdiNo;
        $arr = str_contains($source, ':') ? explode(':', $source) : [$source];
        foreach ($arr as $item) {
            $str .= ':' . $this->oncekiUrunAdlariBul($item, $depth + 1);
        }
        return $str;
    }

    /**
     * BOM path string oluşturur. Format: "sourceNo-parentNo-multiplier:..."
     */
    public function tumYolHazirla(string $refUrunAdiNo): string
    {
        $allNodesStr = $this->oncekiUrunAdlariBul($refUrunAdiNo, 0);
        $allNodes = array_reverse(explode(':', $allNodesStr));
        $result = '';
        foreach ($allNodes as $nodeStr) {
            if (empty($nodeStr)) continue;
            $nodeNo = intval($nodeStr);
            $yol = trim(DB::table('tbAraUrun')->where('No', $nodeNo)->value('Yol') ?? '');
            if (empty($yol)) continue;
            if (str_contains($yol, ':')) {
                foreach (explode(':', $yol) as $seg) {
                    $parts = explode('-', $seg);
                    $result .= $parts[0] . '-' . $nodeStr . '-' . ($parts[1] ?? '1') . ':';
                }
            } else {
                $parts = explode('-', $yol);
                $result .= $parts[0] . '-' . $nodeStr . '-' . ($parts[1] ?? '1') . ':';
            }
        }
        return rtrim($result, ':');
    }

    /**
     * Çarpan uygula → Üretilmesi gereken toplam adet.
     */
    public function uretimAdetBelirle(string $refUrunAdiNo, string $yol, int $uretimAdet): int
    {
        if (str_contains($yol, ':')) {
            foreach (explode(':', $yol) as $seg) {
                if (str_contains(':' . $seg, ':' . $refUrunAdiNo . '-')) {
                    return intval(explode('-', $seg)[2] ?? 1) * $uretimAdet;
                }
            }
            return $uretimAdet;
        }
        if ($yol === '' || !str_contains($yol, '-')) return $uretimAdet;
        return intval(explode('-', $yol)[2] ?? 1) * $uretimAdet;
    }

    /**
     * Bileşen çarpanı.
     */
    public function kacParca(string $urunID, string $refUrunAdiNo): int
    {
        $source = $this->tumYolHazirla($urunID);
        if (str_contains($source, ':')) {
            foreach (explode(':', $source) as $seg) {
                if (str_contains(':' . $seg, ':' . $refUrunAdiNo . '-')) {
                    return intval(explode('-', $seg)[2] ?? 1);
                }
            }
            return 1;
        }
        if ($source === '' || !str_contains($source, '-')) return 1;
        return intval(explode('-', $source)[2] ?? 1);
    }

    /**
     * Stok bazlı üretilebilir adet — darboğaz hesaplaması.
     */
    public function adetBelirle(string $refUrunAdiNo): int
    {
        $str = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if (empty($str)) return -1;
        $minVal = 1000000;
        foreach (explode(':', $str) as $subNoStr) {
            $subNo = intval($subNoStr);
            $mult = $this->kacParca($refUrunAdiNo, $subNoStr);
            $stock = intval(DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $subNo)->value('Adet') ?? 0);
            $producible = ($mult > 0) ? intval(floor($stock / $mult)) : 0;
            if ($producible < $minVal) $minVal = $producible;
        }
        return $minVal;
    }

    /**
     * Nihai/Ara ürün için toplam üretilebilir maksimum adeti stok dar bogazina göre hesaplar.
     */
    public function uretilebilirNihaiAdet(int $urunNo, string $tur): int
    {
        try {
            if ($tur === 'Ara Mamül') {
                return max(0, $this->adetBelirle((string)$urunNo));
            } else {
                $yol = DB::table('tbUrunler')->where('No', $urunNo)->value('AraAdlarYol');
                if (empty($yol)) return 0;
                
                $minVal = 1000000;
                $components = explode(':', trim($yol));
                
                foreach ($components as $parca) {
                    $parts = explode('-', trim($parca));
                    if (count($parts) >= 2) {
                        $altNo = intval($parts[0]);
                        $mult = floatval($parts[1]);
                        
                        $stock = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $altNo)->value('Adet');
                        $stock = $stock ? floatval($stock) : 0;
                        
                        $producible = ($mult > 0) ? intval(floor($stock / $mult)) : 0;
                        if ($producible < $minVal) $minVal = $producible;
                    }
                }
                return $minVal === 1000000 ? 0 : $minVal;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Stok tamponu düşer ve düşürülen miktarı döndürür.
     */
    public function araStokTamponAzalt(string $araUrunAdiNo, int $adet): int
    {
        $araNo = intval($araUrunAdiNo);
        $eskiTampon = DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araNo)->value('TamponMiktar');
        if ($eskiTampon === null) return 0;
        $eskiTampon = intval($eskiTampon);
        $yeniTampon = max(0, $eskiTampon - $adet);
        DB::table('tbBolumAraStok')->where('AraUrunAdiNo', $araNo)->update(['TamponMiktar' => $yeniTampon]);
        return $eskiTampon - $yeniTampon;
    }

    /**
     * İptal sırasında tampon stoğu geri yükle.
     */
    public function restoreTamponFromJson(?string $tamponDusumleriJson): void
    {
        if (empty($tamponDusumleriJson)) return;
        try {
            $dusumleri = json_decode($tamponDusumleriJson, true);
            if (!is_array($dusumleri)) return;
            foreach ($dusumleri as $dusum) {
                $araNo = intval($dusum['araNo'] ?? 0);
                $adet = intval($dusum['adet'] ?? 0);
                if ($araNo > 0 && $adet > 0) {
                    DB::statement(
                        "UPDATE tbBolumAraStok SET TamponMiktar = CASE WHEN TamponMiktar + ? > Adet THEN Adet ELSE TamponMiktar + ? END WHERE AraUrunAdiNo = ?",
                        [$adet, $adet, $araNo]
                    );
                }
            }
        } catch (\Exception $e) { /* sessiz */ }
    }

    /**
     * Orchestrator — Tek bir ara ürün için iş emri oluşturur.
     */
    public function minAraUrunUretimiDenetle(
        int|string $urunIDNo, string $yol, string $refUrunAdiNo,
        int $uretimAdet, string $aciklama, string $stokDurum,
        array &$tamponDusumleri = []
    ): int {
        try {
            $araUrunNo = intval($refUrunAdiNo);
            $legacyUrunIDNo = $this->resolveLegacyProductNo($urunIDNo);
            $uretilebilir = $this->adetBelirle($refUrunAdiNo);
            $uretimAdet = $this->uretimAdetBelirle($refUrunAdiNo, $yol, $uretimAdet);

            if ($stokDurum === 'StokDahil') {
                $azaltilan = $this->araStokTamponAzalt($refUrunAdiNo, $uretimAdet);
                $uretimAdet -= $azaltilan;
                if ($azaltilan > 0) {
                    $tamponDusumleri[] = ['araNo' => $araUrunNo, 'adet' => $azaltilan];
                }
            }

            if ($uretilebilir > $uretimAdet || $uretilebilir < 0) $uretilebilir = $uretimAdet;
            if ($uretimAdet <= 0) return 0;

            $bolumAdiNo = intval(DB::table('tbAraUrun')->where('No', $araUrunNo)->value('BolumAdiNo') ?? 0);
            $legacyDate = now()->format('d/m/Y');
            $legacyTime = now()->format('H:i');
            $legacyAciklama = trim($aciklama);
            $existingQuery = DB::table('tbBolumHavuz')->where('AraUrunAdiNo', $araUrunNo);
            $rawUrunIDNo = trim((string) $urunIDNo);

            if ($rawUrunIDNo !== '' && !ctype_digit($rawUrunIDNo)) {
                $existing = (clone $existingQuery)
                    ->whereIn('UrunIDNo', array_values(array_unique([$legacyUrunIDNo, 0])))
                    ->orderByRaw('CASE WHEN UrunIDNo = ? THEN 0 ELSE 1 END', [$legacyUrunIDNo])
                    ->first();
            } else {
                $existing = (clone $existingQuery)
                    ->where('UrunIDNo', $legacyUrunIDNo)
                    ->first();
            }

            if ($existing) {
                $updatePayload = [
                    'ToplamAdet' => DB::raw("ToplamAdet + {$uretimAdet}"),
                    'Adet' => DB::raw("Adet + {$uretilebilir}"),
                ];

                if (intval($existing->UrunIDNo ?? 0) !== $legacyUrunIDNo) {
                    $updatePayload['UrunIDNo'] = $legacyUrunIDNo;
                }

                DB::table('tbBolumHavuz')->where('No', $existing->No)->update($updatePayload);
            } else {
                DB::table('tbBolumHavuz')->insert([
                    'UrunIDNo' => $legacyUrunIDNo,
                    'AraUrunAdiNo' => $araUrunNo,
                    'ToplamAdet' => $uretimAdet,
                    'Adet' => $uretilebilir,
                    'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                    'Aciklama' => $legacyAciklama !== '' ? $legacyAciklama : null,
                    'GorevBaslangicTarihi' => $legacyDate,
                    'GorevBaslangicSaati' => $legacyTime,
                ]);
            }
            return $uretimAdet;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Recursive görev oluşturma — BOM ağacını traverse eder.
     */
    public function isEmriVerRecursive(
        string $refUrunAdiNo, int $uretimAdet, string $aciklama,
        string $yol, int|string $urunIDNo, string $stokDurum,
        array &$tamponDusumleri = []
    ): void {
        $subComponents = $this->birAdimOncesiUrunAdlari($refUrunAdiNo);
        if (empty($subComponents)) return;

        foreach (explode(':', $subComponents) as $sub) {
            if (empty($sub)) continue;
            $subSubs = $this->birAdimOncesiUrunAdlari($sub);
            $result = $this->minAraUrunUretimiDenetle(
                $urunIDNo, $yol, $sub, $uretimAdet, $aciklama, $stokDurum, $tamponDusumleri
            );
            if ($result > 0 && trim($subSubs) !== '') {
                $this->isEmriVerRecursive($sub, $result, $aciklama, $yol, $urunIDNo, $stokDurum, $tamponDusumleri);
            }
        }
    }

    /**
     * İş emri geçmişine kayıt ekle.
     */
    public function logIsEmriGecmisi(
        int $siparisSatirNo, ?string $siparisNo, ?string $musteri,
        ?string $urunAdi, ?string $sistemUrunAdi, ?int $adet,
        ?string $kategori, string $islemTipi, ?int $gorevNo = null,
        ?int $eslesenUrunNo = null, ?string $eslesenUrunTur = null,
        ?string $kargoSonTeslim = null
    ): void {
        DB::table('tbIsEmriGecmisi')->insert([
            'SiparisSatirNo' => $siparisSatirNo,
            'SiparisNo' => $siparisNo,
            'Musteri' => $musteri,
            'UrunAdi' => $urunAdi,
            'SistemUrunAdi' => $sistemUrunAdi,
            'Adet' => $adet,
            'Kategori' => $kategori,
            'IsEmriTarihi' => now(),
            'IslemTipi' => $islemTipi,
            'IslemTarihi' => now(),
            'GorevNo' => $gorevNo,
            'EslesenUrunNo' => $eslesenUrunNo,
            'EslesenUrunTur' => $eslesenUrunTur,
            'KargoSonTeslim' => $kargoSonTeslim,
        ]);
    }

    // ================================================================
    // sonrakiUrunAdlari — Bu parçayı YOL'unda kullanan üst ürünleri bulur
    // ================================================================
    public function sonrakiUrunAdlari(string $refUrunAdiNo): string
    {
        $results = [];

        $allAraUrunler = DB::table('tbAraUrun')
            ->whereNotNull('Yol')
            ->where('Yol', '!=', '')
            ->select('No', 'Yol')
            ->get();

        foreach ($allAraUrunler as $araUrun) {
            $yolParcalari = explode(':', $araUrun->Yol);
            foreach ($yolParcalari as $parca) {
                $parts = explode('-', $parca);
                if (isset($parts[0]) && trim($parts[0]) === $refUrunAdiNo) {
                    $results[] = strval($araUrun->No);
                    break;
                }
            }
        }

        return implode(':', array_unique($results));
    }

    // ================================================================
    // SonrakiUrunAdetleriniGuncelle2
    // Stok değiştiğinde, o parçayı kullanan üst ürünlerin havuzdaki
    // üretilebilir adetlerini (Adet) yeniden hesaplar.
    // ================================================================
    public function sonrakiUrunAdetleriniGuncelle(string $refUrunAdiNo, ?int $eskiAdet = null, ?int $yeniAdet = null): void
    {
        $oncekiUrunleriKontrolEt = false;
        $sonrakiAdlar = $this->sonrakiUrunAdlari($refUrunAdiNo);
        if (empty($sonrakiAdlar)) return;

        $strings = explode(':', $sonrakiAdlar);

        foreach ($strings as $araNo) {
            $araNo = trim($araNo);
            if (empty($araNo)) continue;

            $adetBelirle = $this->adetBelirle($araNo);
            if ($adetBelirle < 0) continue;

            $araGorevler = DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', intval($araNo))
                ->get();

            foreach ($araGorevler as $gorev) {
                $newAdet = ($gorev->ToplamAdet < $adetBelirle) ? $gorev->ToplamAdet : $adetBelirle;
                DB::table('tbBolumHavuz')->where('No', $gorev->No)->update(['Adet' => $newAdet]);
                $oncekiUrunleriKontrolEt = true;
            }
        }

        if ($eskiAdet !== null && $yeniAdet !== null && $yeniAdet < $eskiAdet && $oncekiUrunleriKontrolEt) {
            try {
                $aciklama = 'Stok guncelleme ile verilen görev...';
                $hazirdaVerilenIsSayisi = $this->verilenIsSayisi($refUrunAdiNo);
                $stokMiktar = $this->stokGetir($refUrunAdiNo);
                $uretimAdet = ($eskiAdet - $yeniAdet) + $hazirdaVerilenIsSayisi + $stokMiktar;
                $yol = $this->tumYolHazirla($refUrunAdiNo);

                $this->minAraUrunUretimiDenetle('Ara Mamül', $yol, $refUrunAdiNo, $uretimAdet, $aciklama, 'StokDahil');

                $recursiveUretimAdet = $uretimAdet - $stokMiktar;
                if (
                    $recursiveUretimAdet > 0
                    && trim($this->birAdimOncesiUrunAdlari($refUrunAdiNo)) !== ''
                ) {
                    $this->isEmriVerRecursive(
                        $refUrunAdiNo,
                        $recursiveUretimAdet,
                        $aciklama,
                        $yol,
                        'Ara Mamül',
                        'StokDahil'
                    );
                }
            } catch (\Throwable $e) {
                // Legacy akışta bu dal sessiz çalışıyordu; stok kaydını bozma.
            }
        }
    }

    public function verilenIsSayisi(string $refUrunAdiNo): int
    {
        $araUrunNo = intval($refUrunAdiNo);

        $havuz = intval(
            DB::table('tbBolumHavuz')
                ->where('AraUrunAdiNo', $araUrunNo)
                ->sum('ToplamAdet')
        );

        $personel = intval(
            DB::table('tbPersonelGorev')
                ->where('AraUrunAdiNo', $araUrunNo)
                ->selectRaw('COALESCE(SUM(COALESCE(Adet, 0) + COALESCE(BekleyenAdet, 0)), 0) as toplam')
                ->value('toplam') ?? 0
        );

        return $havuz + $personel;
    }

    public function stokGetir(string $refUrunAdiNo): int
    {
        return intval(
            DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', intval($refUrunAdiNo))
                ->value('Adet') ?? 0
        );
    }

    // ================================================================
    // personelGorevTabloGuncelle
    // Stok/havuz değiştiğinde, onu bekleyen personel görevlerini (Adet ve 
    // BekleyenAdet) üretilebilir limite göre günceller.
    // ================================================================
    public function personelGorevTabloGuncelle(string $araUrunNo): void
    {
        $sonUrunMu = true;
        $kontrolUrunlerStr = $this->sonrakiUrunAdlari($araUrunNo);
        $kontrolUrunler = explode(':', $kontrolUrunlerStr);

        if (count($kontrolUrunler) > 0 && trim($kontrolUrunler[0]) !== '') {
            foreach ($kontrolUrunler as $urunNo) {
                $intUrnNo = intval(trim($urunNo));
                $kayitlar = DB::table('tbPersonelGorev')
                    ->where('AraUrunAdiNo', $intUrnNo)
                    ->get();
                
                if ($kayitlar->isNotEmpty()) {
                    foreach ($kayitlar as $kayit) {
                        $sonUrunMu = false;
                        $uretilebilecekMaksimumAdet = $this->adetBelirle(strval($kayit->AraUrunAdiNo));
                        if ($uretilebilecekMaksimumAdet < 0) {
                            $uretilebilecekMaksimumAdet = intval($kayit->Adet);
                        }
                        
                        $bekleyenAdet = intval($kayit->BekleyenAdet ?? 0);
                        $adet = intval($kayit->Adet ?? 0);
                        
                        if (($adet + $bekleyenAdet) >= $uretilebilecekMaksimumAdet) {
                            $eskiAdet = $adet;
                            $newAdet = $uretilebilecekMaksimumAdet;
                            $newBekleyen = $bekleyenAdet - ($uretilebilecekMaksimumAdet - $eskiAdet);
                            if ($newBekleyen < 0) $newBekleyen = 0;
                            
                            DB::table('tbPersonelGorev')->where('No', $kayit->No)->update([
                                'Adet' => $newAdet,
                                'BekleyenAdet' => $newBekleyen
                            ]);
                        } else if (($adet + $bekleyenAdet) < $uretilebilecekMaksimumAdet) {
                            $newAdet = $adet + $bekleyenAdet;
                            $newBekleyen = 0;
                            
                            DB::table('tbPersonelGorev')->where('No', $kayit->No)->update([
                                'Adet' => $newAdet,
                                'BekleyenAdet' => $newBekleyen
                            ]);
                        }
                    }
                }
            }
        }

        if ($sonUrunMu) {
            $intUrnNo = intval($araUrunNo);
            $kayitlar = DB::table('tbBolumAraStok')
                ->where('AraUrunAdiNo', $intUrnNo)
                ->get();
            
            if ($kayitlar->isNotEmpty()) {
                foreach ($kayitlar as $kayit) {
                    DB::table('tbBolumAraStok')->where('No', $kayit->No)->update([
                        'TamponMiktar' => $kayit->Adet
                    ]);
                }
            }
        }
    }
}
