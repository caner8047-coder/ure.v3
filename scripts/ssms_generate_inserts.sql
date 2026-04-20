-- SSMS'te bu scripti çalıştır (dbZem veritabanında)
-- Sonuç olarak INSERT INTO satırları üretir
-- Sonucu kopyala ve Desktop/zemuretim-v3/zem_data.sql olarak kaydet

-- AYARLAR: Results to Grid yerine Results to Text seç (Ctrl+T)
-- veya Query → Results To → Results to Text

-- Her tablo için INSERT üret

-- 1. tbBolum
SELECT 'INSERT INTO `tbBolum` (`No`, `BolumAdi`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(ISNULL(BolumAdi,''), '''', '''') + ''');'
FROM dbo.tbBolum;

PRINT '-- Table: tbBolum done';

-- 2. tbAraUrun
SELECT 'INSERT INTO `tbAraUrun` (`No`, `AraUrunAdi`, `Performans`, `BolumAdiNo`, `MinAdet`, `UrunCesidi`, `Yol`, `Resim`, `SistemAdi`, `SistemKodu`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(ISNULL(AraUrunAdi,''), '''', '''') + ''', ' +
    ISNULL(CAST(Performans AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(BolumAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(MinAdet AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(UrunCesidi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Yol,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Resim,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SistemAdi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SistemKodu,''), '''', '''') + '''', 'NULL') + ');'
FROM dbo.tbAraUrun;

PRINT '-- Table: tbAraUrun done';

-- 3. tbPersonel
SELECT 'INSERT INTO `tbPersonel` (`PersonelNo`, `Ad`, `Soyad`, `Adres`, `Telefon`, `Mail`, `Sifre`, `BolumAdiNo`) VALUES (' +
    CAST(PersonelNo AS VARCHAR) + ', ' +
    ISNULL('''' + REPLACE(RTRIM(Ad), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(RTRIM(Soyad), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Adres,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Telefon,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Mail,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Sifre,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(BolumAdiNo AS VARCHAR), 'NULL') + ');'
FROM dbo.tbPersonel;

PRINT '-- Table: tbPersonel done';

-- 4. tbGorevler
SELECT 'INSERT INTO `tbGorevler` (`No`, `UrunIDNo`, `GorevBaslamaTarihi`, `GorevBitisTarihi`, `ToplamAdet`, `BolumAdiNo`, `PersonelNo`, `Performans`, `AraUrunAdiNo`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(UrunIDNo AS VARCHAR) + ', ' +
    '''' + REPLACE(GorevBaslamaTarihi, '''', '''') + ''', ' +
    '''' + REPLACE(GorevBitisTarihi, '''', '''') + ''', ' +
    CAST(ToplamAdet AS VARCHAR) + ', ' +
    ISNULL(CAST(BolumAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(PersonelNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(Performans AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(AraUrunAdiNo AS VARCHAR), 'NULL') + ');'
FROM dbo.tbGorevler;

PRINT '-- Table: tbGorevler done';

-- 5. tbBolumAraStok
SELECT 'INSERT INTO `tbBolumAraStok` (`No`, `BolumAdiNo`, `Adet`, `AraUrunAdiNo`, `UrunIDNo`, `TamponMiktar`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    ISNULL(CAST(BolumAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(Adet AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(AraUrunAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(UrunIDNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(TamponMiktar AS VARCHAR), 'NULL') + ');'
FROM dbo.tbBolumAraStok;

PRINT '-- Table: tbBolumAraStok done';

-- 6. tbBolumHavuz
SELECT 'INSERT INTO `tbBolumHavuz` (`No`, `UrunIDNo`, `GorevBaslangicTarihi`, `BolumAdiNo`, `Aciklama`, `GorevBaslangicSaati`, `AraUrunAdiNo`, `Adet`, `ToplamAdet`, `AdimSirasi`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    ISNULL(CAST(UrunIDNo AS VARCHAR), 'NULL') + ', ' +
    '''' + REPLACE(GorevBaslangicTarihi, '''', '''') + ''', ' +
    CAST(BolumAdiNo AS VARCHAR) + ', ' +
    ISNULL('''' + REPLACE(ISNULL(CAST(Aciklama AS NVARCHAR(MAX)),''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(GorevBaslangicSaati,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(AraUrunAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(Adet AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(ToplamAdet AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(AdimSirasi AS VARCHAR), 'NULL') + ');'
FROM dbo.tbBolumHavuz;

PRINT '-- Table: tbBolumHavuz done';

-- 7. tbPersonelGorev
SELECT 'INSERT INTO `tbPersonelGorev` (`No`, `UrunIDNo`, `PersonelNo`, `GorevBaslamaTarihi`, `Adet`, `BolumAdiNo`, `AraUrunAdiNo`, `Onay`, `BekleyenAdet`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(UrunIDNo AS VARCHAR) + ', ' +
    CAST(PersonelNo AS VARCHAR) + ', ' +
    '''' + REPLACE(GorevBaslamaTarihi, '''', '''') + ''', ' +
    CAST(Adet AS VARCHAR) + ', ' +
    CAST(BolumAdiNo AS VARCHAR) + ', ' +
    ISNULL(CAST(AraUrunAdiNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Onay,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(BekleyenAdet AS VARCHAR), 'NULL') + ');'
FROM dbo.tbPersonelGorev;

PRINT '-- Table: tbPersonelGorev done';

-- 8. tbUrunler
SELECT 'INSERT INTO `tbUrunler` (`No`, `UrunID`, `AraAdlarYol`, `Resim`, `SistemAdi`, `SistemKodu`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(UrunID, '''', '''') + ''', ' +
    ISNULL('''' + REPLACE(ISNULL(CAST(AraAdlarYol AS NVARCHAR(MAX)),''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(CAST(Resim AS NVARCHAR(MAX)),''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SistemAdi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SistemKodu,''), '''', '''') + '''', 'NULL') + ');'
FROM dbo.tbUrunler;

PRINT '-- Table: tbUrunler done';

-- 9. tbIletisim
SELECT 'INSERT INTO `tbIletisim` (`PersonelNo`, `Mesaj`, `Tarih`, `MesajNo`, `Saat`, `AdSoyad`, `Mail`, `BolumAdiNo`) VALUES (' +
    ISNULL(CAST(PersonelNo AS VARCHAR), 'NULL') + ', ' +
    '''' + REPLACE(ISNULL(CAST(Mesaj AS NVARCHAR(MAX)),''), '''', '''') + ''', ' +
    '''' + REPLACE(Tarih, '''', '''') + ''', ' +
    CAST(MesajNo AS VARCHAR) + ', ' +
    '''' + REPLACE(Saat, '''', '''') + ''', ' +
    ISNULL('''' + REPLACE(ISNULL(AdSoyad,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Mail,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(BolumAdiNo AS VARCHAR), 'NULL') + ');'
FROM dbo.tbIletisim;

PRINT '-- Table: tbIletisim done';

-- 10. tbIsEmriGecmisi (zemureti_admindb)
SELECT 'INSERT INTO `tbIsEmriGecmisi` (`No`, `SiparisSatirNo`, `SiparisNo`, `Musteri`, `UrunAdi`, `SistemUrunAdi`, `Adet`, `Kategori`, `IsEmriTarihi`, `IslemTipi`, `IslemTarihi`, `GorevNo`, `EslesenUrunNo`, `EslesenUrunTur`, `KargoSonTeslim`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(SiparisSatirNo AS VARCHAR) + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SiparisNo,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Musteri,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(UrunAdi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(SistemUrunAdi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(Adet AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Kategori,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, IsEmriTarihi, 120) + '''', 'NULL') + ', ' +
    '''' + REPLACE(IslemTipi, '''', '''') + ''', ' +
    '''' + CONVERT(VARCHAR, IslemTarihi, 120) + ''', ' +
    ISNULL(CAST(GorevNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(EslesenUrunNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(EslesenUrunTur,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, KargoSonTeslim, 120) + '''', 'NULL') + ');'
FROM zemureti_admindb.tbIsEmriGecmisi;

PRINT '-- Table: tbIsEmriGecmisi done';

-- 11. tbSiparisSatir (zemureti_admindb)
SELECT 'INSERT INTO `tbSiparisSatir` (`No`, `SiparisNo`, `Pazaryeri`, `Magaza`, `SiparisTarihi`, `Musteri`, `UrunAdi`, `Adet`, `MusteriNotu`, `KargoSonTeslim`, `Kategori`, `Durum`, `Aktif`, `EslesenUrunNo`, `EslesenUrunTur`, `EslesmePuani`, `EslesmeYontemi`, `IsEmriTarihi`, `GorevNo`, `YuklemeTarihi`, `GuncellemeTarihi`, `StokKodu`, `SetMi`, `SetNo`, `AnaSetSatirNo`, `TamponDusumleri`, `BagliOlduguOzelUretimNo`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(SiparisNo, '''', '''') + ''', ' +
    ISNULL('''' + REPLACE(ISNULL(Pazaryeri,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Magaza,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, SiparisTarihi, 120) + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Musteri,''), '''', '''') + '''', 'NULL') + ', ' +
    '''' + REPLACE(UrunAdi, '''', '''') + ''', ' +
    CAST(Adet AS VARCHAR) + ', ' +
    ISNULL('''' + REPLACE(ISNULL(MusteriNotu,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, KargoSonTeslim, 120) + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Kategori,''), '''', '''') + '''', 'NULL') + ', ' +
    '''' + REPLACE(Durum, '''', '''') + ''', ' +
    CAST(Aktif AS VARCHAR) + ', ' +
    ISNULL(CAST(EslesenUrunNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(EslesenUrunTur,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(EslesmePuani AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(EslesmeYontemi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, IsEmriTarihi, 120) + '''', 'NULL') + ', ' +
    ISNULL(CAST(GorevNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, YuklemeTarihi, 120) + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, GuncellemeTarihi, 120) + '''', 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(StokKodu,''), '''', '''') + '''', 'NULL') + ', ' +
    CAST(SetMi AS VARCHAR) + ', ' +
    ISNULL(CAST(SetNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(AnaSetSatirNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(CAST(TamponDusumleri AS NVARCHAR(MAX)),''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(BagliOlduguOzelUretimNo AS VARCHAR), 'NULL') + ');'
FROM zemureti_admindb.tbSiparisSatir;

PRINT '-- Table: tbSiparisSatir done';

-- 12. tbKritikStokEsik
SELECT 'INSERT INTO `tbKritikStokEsik` (`No`, `AraUrunAdiNo`, `EsikMiktar`, `OtomatikIsEmri`, `IsEmriAdet`, `UrunIDNo`, `Aktif`, `SonKontrolTarihi`, `SonUyariTarihi`, `OlusturmaTarihi`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(AraUrunAdiNo AS VARCHAR) + ', ' +
    CAST(EsikMiktar AS VARCHAR) + ', ' +
    CAST(OtomatikIsEmri AS VARCHAR) + ', ' +
    ISNULL(CAST(IsEmriAdet AS VARCHAR), 'NULL') + ', ' +
    ISNULL(CAST(UrunIDNo AS VARCHAR), 'NULL') + ', ' +
    CAST(Aktif AS VARCHAR) + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, SonKontrolTarihi, 120) + '''', 'NULL') + ', ' +
    ISNULL('''' + CONVERT(VARCHAR, SonUyariTarihi, 120) + '''', 'NULL') + ', ' +
    '''' + CONVERT(VARCHAR, OlusturmaTarihi, 120) + ''');'
FROM zemureti_admindb.tbKritikStokEsik;

PRINT '-- Table: tbKritikStokEsik done';

-- 13. tbKritikStokUyari
SELECT 'INSERT INTO `tbKritikStokUyari` (`No`, `AraUrunAdiNo`, `EsikMiktar`, `MevcutStok`, `UyariTipi`, `OtomatikIsEmriVerildi`, `Okundu`, `OlusturmaTarihi`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(AraUrunAdiNo AS VARCHAR) + ', ' +
    CAST(EsikMiktar AS VARCHAR) + ', ' +
    CAST(MevcutStok AS VARCHAR) + ', ' +
    '''' + REPLACE(UyariTipi, '''', '''') + ''', ' +
    CAST(OtomatikIsEmriVerildi AS VARCHAR) + ', ' +
    CAST(Okundu AS VARCHAR) + ', ' +
    '''' + CONVERT(VARCHAR, OlusturmaTarihi, 120) + ''');'
FROM zemureti_admindb.tbKritikStokUyari;

PRINT '-- Table: tbKritikStokUyari done';

-- 14. tbSetTanimlari
SELECT 'INSERT INTO `tbSetTanimlari` (`No`, `ExcelSetAdi`, `SetAdi`, `OlusturmaTarihi`, `Aktif`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(ExcelSetAdi, '''', '''') + ''', ' +
    ISNULL('''' + REPLACE(ISNULL(SetAdi,''), '''', '''') + '''', 'NULL') + ', ' +
    '''' + CONVERT(VARCHAR, OlusturmaTarihi, 120) + ''', ' +
    CAST(Aktif AS VARCHAR) + ');'
FROM zemureti_admindb.tbSetTanimlari;

PRINT '-- Table: tbSetTanimlari done';

-- 15. tbSetIcerikleri
SELECT 'INSERT INTO `tbSetIcerikleri` (`No`, `SetNo`, `UrunNo`, `Adet`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    CAST(SetNo AS VARCHAR) + ', ' +
    CAST(UrunNo AS VARCHAR) + ', ' +
    CAST(Adet AS VARCHAR) + ');'
FROM zemureti_admindb.tbSetIcerikleri;

PRINT '-- Table: tbSetIcerikleri done';

-- 16. tbUrunEslestirmeOnbellek
SELECT 'INSERT INTO `tbUrunEslestirmeOnbellek` (`No`, `ExcelUrunAdi`, `EslesenUrunNo`, `EslesenUrunTur`, `OlusturmaTarihi`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    '''' + REPLACE(ExcelUrunAdi, '''', '''') + ''', ' +
    CAST(EslesenUrunNo AS VARCHAR) + ', ' +
    '''' + REPLACE(EslesenUrunTur, '''', '''') + ''', ' +
    '''' + CONVERT(VARCHAR, OlusturmaTarihi, 120) + ''');'
FROM zemureti_admindb.tbUrunEslestirmeOnbellek;

PRINT '-- Table: tbUrunEslestirmeOnbellek done';

-- 17. tbVerilenGorevler
SELECT 'INSERT INTO `tbVerilenGorevler` (`No`, `UrunIDNo`, `GorevTarihi`, `ToplamAdet`, `Aciklama`) VALUES (' +
    CAST(No AS VARCHAR) + ', ' +
    ISNULL(CAST(UrunIDNo AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(GorevTarihi,''), '''', '''') + '''', 'NULL') + ', ' +
    ISNULL(CAST(ToplamAdet AS VARCHAR), 'NULL') + ', ' +
    ISNULL('''' + REPLACE(ISNULL(Aciklama,''), '''', '''') + '''', 'NULL') + ');'
FROM zemureti_admindb.tbVerilenGorevler;

PRINT '-- Table: tbVerilenGorevler done';

PRINT '=== ALL DONE ===';
