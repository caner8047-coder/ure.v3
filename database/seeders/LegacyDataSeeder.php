<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Orijinal MSSQL veritabanı yapısına uygun gerçekçi test verisi.
 * ZemMobilya üretim sistemi — Bölümler, Ürünler, Personeller, Siparişler, Stoklar.
 */
class LegacyDataSeeder extends Seeder
{
    public function run(): void
    {
        // ===== 1. BÖLÜMLER (tbBolum) =====
        $bolumler = [
            ['No' => 1, 'BolumAdi' => 'İskelet'],
            ['No' => 2, 'BolumAdi' => 'Döşeme'],
            ['No' => 3, 'BolumAdi' => 'Boyama'],
            ['No' => 4, 'BolumAdi' => 'Montaj'],
            ['No' => 5, 'BolumAdi' => 'Paketleme'],
            ['No' => 6, 'BolumAdi' => 'Kalite Kontrol'],
        ];
        foreach ($bolumler as $b) {
            DB::table('tbBolum')->updateOrInsert(['No' => $b['No']], $b);
            DB::table('departments')->updateOrInsert(['id' => $b['No']], ['name' => $b['BolumAdi'], 'bolum_no' => $b['No']]);
        }

        // ===== 2. ARA ÜRÜNLER (tbAraUrun) — Ham Madde + Ara Mamül + Nihai Ürün =====
        $araUrunler = [
            ['No'=>1,  'AraUrunAdi'=>'Sünger',          'Performans'=>50, 'BolumAdiNo'=>2, 'MinAdet'=>10, 'UrunCesidi'=>'Ham Madde',   'Yol'=>''],
            ['No'=>2,  'AraUrunAdi'=>'Kumaş',            'Performans'=>40, 'BolumAdiNo'=>2, 'MinAdet'=>20, 'UrunCesidi'=>'Ham Madde',   'Yol'=>''],
            ['No'=>3,  'AraUrunAdi'=>'Ahşap Panel',      'Performans'=>30, 'BolumAdiNo'=>1, 'MinAdet'=>15, 'UrunCesidi'=>'Ham Madde',   'Yol'=>''],
            ['No'=>4,  'AraUrunAdi'=>'Vida Seti',        'Performans'=>100,'BolumAdiNo'=>4, 'MinAdet'=>50, 'UrunCesidi'=>'Ham Madde',   'Yol'=>''],
            ['No'=>5,  'AraUrunAdi'=>'Boya',             'Performans'=>60, 'BolumAdiNo'=>3, 'MinAdet'=>10, 'UrunCesidi'=>'Ham Madde',   'Yol'=>''],
            ['No'=>6,  'AraUrunAdi'=>'İskelet Çerçeve',  'Performans'=>15, 'BolumAdiNo'=>1, 'MinAdet'=>5,  'UrunCesidi'=>'Ara Mamül',   'Yol'=>'3-4:4-8'],
            ['No'=>7,  'AraUrunAdi'=>'Döşeme Kılıf',     'Performans'=>20, 'BolumAdiNo'=>2, 'MinAdet'=>5,  'UrunCesidi'=>'Ara Mamül',   'Yol'=>'1-2:2-3'],
            ['No'=>8,  'AraUrunAdi'=>'Boyalı Panel',     'Performans'=>25, 'BolumAdiNo'=>3, 'MinAdet'=>5,  'UrunCesidi'=>'Ara Mamül',   'Yol'=>'3-2:5-1'],
            ['No'=>9,  'AraUrunAdi'=>'Koltuk Takımı',    'Performans'=>5,  'BolumAdiNo'=>4, 'MinAdet'=>2,  'UrunCesidi'=>'Nihayi Ürün', 'Yol'=>'6-1:7-1:8-2:4-12'],
            ['No'=>10, 'AraUrunAdi'=>'Yemek Masası',     'Performans'=>8,  'BolumAdiNo'=>4, 'MinAdet'=>2,  'UrunCesidi'=>'Nihayi Ürün', 'Yol'=>'3-6:5-2:4-16'],
            ['No'=>11, 'AraUrunAdi'=>'Sandalye',         'Performans'=>12, 'BolumAdiNo'=>4, 'MinAdet'=>4,  'UrunCesidi'=>'Nihayi Ürün', 'Yol'=>'3-2:1-1:2-1:4-8'],
            ['No'=>12, 'AraUrunAdi'=>'TV Ünitesi',       'Performans'=>6,  'BolumAdiNo'=>4, 'MinAdet'=>2,  'UrunCesidi'=>'Nihayi Ürün', 'Yol'=>'8-3:4-10'],
            ['No'=>13, 'AraUrunAdi'=>'Yatak Başlığı',    'Performans'=>10, 'BolumAdiNo'=>2, 'MinAdet'=>3,  'UrunCesidi'=>'Ara Mamül',   'Yol'=>'6-1:7-1'],
            ['No'=>14, 'AraUrunAdi'=>'Yatak Odası Takımı','Performans'=>3, 'BolumAdiNo'=>4, 'MinAdet'=>1,  'UrunCesidi'=>'Nihayi Ürün', 'Yol'=>'13-1:8-2:4-20'],
        ];
        foreach ($araUrunler as $a) {
            DB::table('tbAraUrun')->updateOrInsert(['No' => $a['No']], $a);
        }

        // ===== 3. NİHAİ ÜRÜNLER (tbUrunler) =====
        $urunler = [
            ['No'=>1, 'UrunID'=>'Koltuk Takımı XL',     'SistemAdi'=>'KOLTUK-XL-001',    'SistemKodu'=>'PRD-001', 'AraAdlarYol'=>'6-1:7-1:8-2:4-12'],
            ['No'=>2, 'UrunID'=>'Yemek Masası 6 Kişilik','SistemAdi'=>'MASA-6K-002',      'SistemKodu'=>'PRD-002', 'AraAdlarYol'=>'3-6:5-2:4-16'],
            ['No'=>3, 'UrunID'=>'Sandalye Klasik',       'SistemAdi'=>'SANDALYE-KLS-003', 'SistemKodu'=>'PRD-003', 'AraAdlarYol'=>'3-2:1-1:2-1:4-8'],
            ['No'=>4, 'UrunID'=>'TV Ünitesi Modern',     'SistemAdi'=>'TV-MDR-004',       'SistemKodu'=>'PRD-004', 'AraAdlarYol'=>'8-3:4-10'],
            ['No'=>5, 'UrunID'=>'Yatak Odası Takımı Lüx','SistemAdi'=>'YATAK-LUX-005',   'SistemKodu'=>'PRD-005', 'AraAdlarYol'=>'13-1:8-2:4-20'],
            ['No'=>6, 'UrunID'=>'Koltuk Takımı Mini',    'SistemAdi'=>'KOLTUK-MINI-006',  'SistemKodu'=>'PRD-006', 'AraAdlarYol'=>'6-1:7-1:4-8'],
            ['No'=>7, 'UrunID'=>'Sehpa Set',             'SistemAdi'=>'SEHPA-SET-007',    'SistemKodu'=>'PRD-007', 'AraAdlarYol'=>'3-3:5-1:4-6'],
        ];
        foreach ($urunler as $u) {
            DB::table('tbUrunler')->updateOrInsert(['No' => $u['No']], $u);
        }

        // ===== 4. PERSONELLER (tbPersonel) =====
        $personeller = [
            ['PersonelNo'=>1, 'Ad'=>'Ahmet',   'Soyad'=>'Kaya',    'Mail'=>'ahmet@zemmobilya.com',  'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>1],
            ['PersonelNo'=>2, 'Ad'=>'Mehmet',   'Soyad'=>'Demir',   'Mail'=>'mehmet@zemmobilya.com', 'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>2],
            ['PersonelNo'=>3, 'Ad'=>'Ali',      'Soyad'=>'Yılmaz',  'Mail'=>'ali@zemmobilya.com',    'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>3],
            ['PersonelNo'=>4, 'Ad'=>'Fatma',    'Soyad'=>'Çelik',   'Mail'=>'fatma@zemmobilya.com',  'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>4],
            ['PersonelNo'=>5, 'Ad'=>'Ayşe',     'Soyad'=>'Öztürk',  'Mail'=>'ayse@zemmobilya.com',   'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>5],
            ['PersonelNo'=>6, 'Ad'=>'Hasan',    'Soyad'=>'Şahin',   'Mail'=>'hasan@zemmobilya.com',  'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>1],
            ['PersonelNo'=>7, 'Ad'=>'Hüseyin',  'Soyad'=>'Arslan',  'Mail'=>'huseyin@zemmobilya.com','Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>2],
            ['PersonelNo'=>8, 'Ad'=>'Zeynep',   'Soyad'=>'Ak',      'Mail'=>'zeynep@zemmobilya.com', 'Sifre'=>hash('sha256','123456'), 'BolumAdiNo'=>6],
        ];
        foreach ($personeller as $p) {
            // Trim spaces for consistency (legacy DB often has trailing spaces from CHAR/NCHAR)
            $p['Ad'] = trim($p['Ad']);
            $p['Soyad'] = trim($p['Soyad']);
            $p['Mail'] = trim($p['Mail']);

            DB::table('tbPersonel')->updateOrInsert(['PersonelNo' => $p['PersonelNo']], $p);

            // Also mirror to Laravel's users table for Authentication
            DB::table('users')->updateOrInsert(
                ['email' => $p['Mail']],
                [
                    'personnel_no' => $p['PersonelNo'],
                    'name' => $p['Ad'],
                    'surname' => $p['Soyad'],
                    'email' => $p['Mail'],
                    'password' => $p['Sifre'], // AuthController handles SHA256 migration
                    'department_id' => $p['BolumAdiNo'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // ===== 5. STOKLAR (tbBolumAraStok) =====
        $stoklar = [
            ['No'=>1,  'BolumAdiNo'=>2, 'Adet'=>50,  'AraUrunAdiNo'=>1,  'UrunIDNo'=>null, 'TamponMiktar'=>40],  // Sünger
            ['No'=>2,  'BolumAdiNo'=>2, 'Adet'=>80,  'AraUrunAdiNo'=>2,  'UrunIDNo'=>null, 'TamponMiktar'=>65],  // Kumaş
            ['No'=>3,  'BolumAdiNo'=>1, 'Adet'=>120, 'AraUrunAdiNo'=>3,  'UrunIDNo'=>null, 'TamponMiktar'=>100], // Ahşap Panel
            ['No'=>4,  'BolumAdiNo'=>4, 'Adet'=>300, 'AraUrunAdiNo'=>4,  'UrunIDNo'=>null, 'TamponMiktar'=>250], // Vida Seti
            ['No'=>5,  'BolumAdiNo'=>3, 'Adet'=>25,  'AraUrunAdiNo'=>5,  'UrunIDNo'=>null, 'TamponMiktar'=>20],  // Boya
            ['No'=>6,  'BolumAdiNo'=>1, 'Adet'=>15,  'AraUrunAdiNo'=>6,  'UrunIDNo'=>null, 'TamponMiktar'=>10],  // İskelet Çerçeve
            ['No'=>7,  'BolumAdiNo'=>2, 'Adet'=>18,  'AraUrunAdiNo'=>7,  'UrunIDNo'=>null, 'TamponMiktar'=>12],  // Döşeme Kılıf
            ['No'=>8,  'BolumAdiNo'=>3, 'Adet'=>20,  'AraUrunAdiNo'=>8,  'UrunIDNo'=>null, 'TamponMiktar'=>15],  // Boyalı Panel
            ['No'=>9,  'BolumAdiNo'=>4, 'Adet'=>5,   'AraUrunAdiNo'=>9,  'UrunIDNo'=>1,    'TamponMiktar'=>3],   // Koltuk Takımı
            ['No'=>10, 'BolumAdiNo'=>4, 'Adet'=>8,   'AraUrunAdiNo'=>10, 'UrunIDNo'=>2,    'TamponMiktar'=>5],   // Yemek Masası
            ['No'=>11, 'BolumAdiNo'=>4, 'Adet'=>12,  'AraUrunAdiNo'=>11, 'UrunIDNo'=>3,    'TamponMiktar'=>8],   // Sandalye
            ['No'=>12, 'BolumAdiNo'=>4, 'Adet'=>6,   'AraUrunAdiNo'=>12, 'UrunIDNo'=>4,    'TamponMiktar'=>4],   // TV Ünitesi
        ];
        foreach ($stoklar as $s) {
            DB::table('tbBolumAraStok')->updateOrInsert(['No' => $s['No']], $s);
        }

        // ===== 6. SİPARİŞLER (tbSiparisSatir) =====
        $siparisler = [
            ['No'=>1,  'SiparisNo'=>'SP-2026-001', 'Pazaryeri'=>'Trendyol',  'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-20 10:30:00', 'Musteri'=>'Caner R.', 'UrunAdi'=>'Koltuk Takımı XL', 'Adet'=>2, 'KargoSonTeslim'=>'2026-03-27 18:00:00', 'Kategori'=>'Koltuk', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>1, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-20 10:30:00'],
            ['No'=>2,  'SiparisNo'=>'SP-2026-002', 'Pazaryeri'=>'Hepsiburada','Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-20 14:15:00', 'Musteri'=>'Ayşe K.',  'UrunAdi'=>'Yemek Masası 6 Kişilik', 'Adet'=>1, 'KargoSonTeslim'=>'2026-03-28 18:00:00', 'Kategori'=>'Masa', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>2, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>95, 'EslesmeYontemi'=>'Otomatik', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-20 14:15:00'],
            ['No'=>3,  'SiparisNo'=>'SP-2026-003', 'Pazaryeri'=>'N11',       'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-21 09:00:00', 'Musteri'=>'Mehmet D.','UrunAdi'=>'Sandalye Klasik', 'Adet'=>6, 'KargoSonTeslim'=>'2026-03-26 18:00:00', 'Kategori'=>'Sandalye', 'Durum'=>'IsEmriVerildi', 'Aktif'=>1, 'EslesenUrunNo'=>3, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'IsEmriTarihi'=>'2026-03-21 10:00:00', 'GorevNo'=>1, 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-21 09:00:00'],
            ['No'=>4,  'SiparisNo'=>'SP-2026-004', 'Pazaryeri'=>'Trendyol',  'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-21 11:30:00', 'Musteri'=>'Fatma Ç.', 'UrunAdi'=>'TV Ünitesi Modern', 'Adet'=>1, 'KargoSonTeslim'=>'2026-03-29 18:00:00', 'Kategori'=>'TV Ünitesi', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>4, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>90, 'EslesmeYontemi'=>'Otomatik', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-21 11:30:00'],
            ['No'=>5,  'SiparisNo'=>'SP-2026-005', 'Pazaryeri'=>'Hepsiburada','Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-22 08:45:00', 'Musteri'=>'Hasan Ş.', 'UrunAdi'=>'Yatak Odası Takımı Lüx', 'Adet'=>1, 'KargoSonTeslim'=>'2026-04-01 18:00:00', 'Kategori'=>'Yatak Odası', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>5, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>85, 'EslesmeYontemi'=>'Otomatik', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-22 08:45:00'],
            ['No'=>6,  'SiparisNo'=>'SP-2026-006', 'Pazaryeri'=>'Trendyol',  'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-22 13:00:00', 'Musteri'=>'Zeynep A.','UrunAdi'=>'Koltuk Takımı Mini', 'Adet'=>3, 'KargoSonTeslim'=>'2026-03-30 18:00:00', 'Kategori'=>'Koltuk', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>6, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-22 13:00:00'],
            ['No'=>7,  'SiparisNo'=>'SP-2026-007', 'Pazaryeri'=>'N11',       'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-22 16:00:00', 'Musteri'=>'Ali Y.',   'UrunAdi'=>'Sehpa Set', 'Adet'=>2, 'KargoSonTeslim'=>'2026-03-28 18:00:00', 'Kategori'=>'Sehpa', 'Durum'=>'StokKarsilandi', 'Aktif'=>1, 'EslesenUrunNo'=>7, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-22 16:00:00'],
            ['No'=>8,  'SiparisNo'=>'SP-2026-008', 'Pazaryeri'=>'Trendyol',  'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-23 09:30:00', 'Musteri'=>'Büşra T.', 'UrunAdi'=>'Bilinmeyen Ürün XYZ', 'Adet'=>1, 'KargoSonTeslim'=>'2026-03-31 18:00:00', 'Kategori'=>'Diğer', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>null, 'EslesenUrunTur'=>null, 'EslesmePuani'=>null, 'EslesmeYontemi'=>null, 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-23 09:30:00'],
            ['No'=>9,  'SiparisNo'=>'SP-2026-009', 'Pazaryeri'=>'Hepsiburada','Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-23 11:00:00', 'Musteri'=>'Emre B.', 'UrunAdi'=>'Koltuk Takımı XL', 'Adet'=>1, 'KargoSonTeslim'=>'2026-04-02 18:00:00', 'Kategori'=>'Koltuk', 'Durum'=>'Pasif', 'Aktif'=>0, 'EslesenUrunNo'=>1, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-23 11:00:00'],
            ['No'=>10, 'SiparisNo'=>'SP-2026-010', 'Pazaryeri'=>'Trendyol',   'Magaza'=>'ZemMobilya', 'SiparisTarihi'=>'2026-03-23 14:30:00', 'Musteri'=>'Deniz K.','UrunAdi'=>'Sandalye Klasik', 'Adet'=>4, 'KargoSonTeslim'=>'2026-03-30 18:00:00', 'Kategori'=>'Sandalye', 'Durum'=>'UretimBekliyor', 'Aktif'=>1, 'EslesenUrunNo'=>3, 'EslesenUrunTur'=>'Nihai', 'EslesmePuani'=>100, 'EslesmeYontemi'=>'Onbellek', 'SetMi'=>0, 'YuklemeTarihi'=>'2026-03-23 14:30:00'],
        ];
        foreach ($siparisler as $s) {
            DB::table('tbSiparisSatir')->updateOrInsert(['No' => $s['No']], $s);
        }

        // ===== 7. İŞ EMRİ GEÇMİŞİ (tbIsEmriGecmisi) =====
        $isEmriGecmisi = [
            ['SiparisSatirNo'=>3, 'SiparisNo'=>'SP-2026-003', 'Musteri'=>'Mehmet D.', 'UrunAdi'=>'Sandalye Klasik', 'SistemUrunAdi'=>'SANDALYE-KLS-003', 'Adet'=>6, 'Kategori'=>'Sandalye', 'IsEmriTarihi'=>'2026-03-21 10:00:00', 'IslemTipi'=>'Verildi', 'IslemTarihi'=>'2026-03-21 10:00:00', 'GorevNo'=>1, 'EslesenUrunNo'=>3, 'EslesenUrunTur'=>'Nihai', 'KargoSonTeslim'=>'2026-03-26 18:00:00'],
        ];
        foreach ($isEmriGecmisi as $g) {
            DB::table('tbIsEmriGecmisi')->insert($g);
        }

        // ===== 8. KRİTİK STOK EŞİKLERİ (tbKritikStokEsik) =====
        $esikler = [
            ['AraUrunAdiNo'=>1, 'EsikMiktar'=>15, 'OtomatikIsEmri'=>0, 'IsEmriAdet'=>20, 'Aktif'=>1, 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['AraUrunAdiNo'=>3, 'EsikMiktar'=>20, 'OtomatikIsEmri'=>1, 'IsEmriAdet'=>30, 'Aktif'=>1, 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['AraUrunAdiNo'=>4, 'EsikMiktar'=>50, 'OtomatikIsEmri'=>0, 'IsEmriAdet'=>100,'Aktif'=>1, 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
        ];
        foreach ($esikler as $e) {
            DB::table('tbKritikStokEsik')->updateOrInsert(['AraUrunAdiNo' => $e['AraUrunAdiNo']], $e);
        }

        // ===== 9. ÜRÜN EŞLEŞTİRME ÖNBELLEĞİ (tbUrunEslestirmeOnbellek) =====
        $eslestirmeler = [
            ['ExcelUrunAdi'=>'Koltuk Takımı XL',       'EslesenUrunNo'=>1, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'Yemek Masası 6 Kişilik',  'EslesenUrunNo'=>2, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'Sandalye Klasik',          'EslesenUrunNo'=>3, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'TV Ünitesi Modern',        'EslesenUrunNo'=>4, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'Yatak Odası Takımı Lüx',   'EslesenUrunNo'=>5, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'Koltuk Takımı Mini',       'EslesenUrunNo'=>6, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
            ['ExcelUrunAdi'=>'Sehpa Set',                'EslesenUrunNo'=>7, 'EslesenUrunTur'=>'Nihai', 'OlusturmaTarihi'=>'2026-03-20 10:00:00'],
        ];
        foreach ($eslestirmeler as $e) {
            DB::table('tbUrunEslestirmeOnbellek')->updateOrInsert(['ExcelUrunAdi' => $e['ExcelUrunAdi']], $e);
        }

        // ===== 10. BÖLÜM HAVUZ (İş Emri Havuzu) =====
        $havuz = [
            ['UrunIDNo'=>3, 'GorevBaslangicTarihi'=>'2026-03-21 10:00:00', 'BolumAdiNo'=>1, 'AraUrunAdiNo'=>3,  'Adet'=>6, 'ToplamAdet'=>12, 'AdimSirasi'=>1],
            ['UrunIDNo'=>3, 'GorevBaslangicTarihi'=>'2026-03-21 10:00:00', 'BolumAdiNo'=>4, 'AraUrunAdiNo'=>4,  'Adet'=>48,'ToplamAdet'=>48, 'AdimSirasi'=>2],
            ['UrunIDNo'=>3, 'GorevBaslangicTarihi'=>'2026-03-21 10:00:00', 'BolumAdiNo'=>2, 'AraUrunAdiNo'=>1,  'Adet'=>6, 'ToplamAdet'=>6,  'AdimSirasi'=>3],
            ['UrunIDNo'=>3, 'GorevBaslangicTarihi'=>'2026-03-21 10:00:00', 'BolumAdiNo'=>2, 'AraUrunAdiNo'=>2,  'Adet'=>6, 'ToplamAdet'=>6,  'AdimSirasi'=>4],
        ];
        foreach ($havuz as $h) {
            DB::table('tbBolumHavuz')->insert($h);
        }

        // ===== 11. ADMIN USER (tbPersonel ve Laravel users tablosuna) =====
        $adminSifreRaw = '123456';
        $adminSifreHash = hash('sha256', $adminSifreRaw);
        
        DB::table('tbPersonel')->updateOrInsert(
            ['Mail' => 'admin@zemmobilya.com'],
            [
                'PersonelNo' => 9999,
                'Ad' => 'Caner Bey',
                'Soyad' => 'Yönetici',
                'Telefon' => null,
                'Adres' => null,
                'Sifre' => $adminSifreHash,
                'BolumAdiNo' => 0
            ]
        );

        DB::table('users')->updateOrInsert(
            ['email' => 'admin@zemmobilya.com'],
            [
                'name' => 'Caner Bey',
                'surname' => 'Yönetici',
                'personnel_no' => '9999',
                'password' => $adminSifreHash,
                'department_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // ===== 12. DEPARTMENTS (Mevcut departmanlar yukarıda tbBolum ile birlikte oluşturuldu) =====
    }
}
