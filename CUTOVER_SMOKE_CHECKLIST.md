# Cutover Smoke Checklist

Bu kontrol listesi, Laravel uygulamasini fabrikada devreye almadan hemen once ve hemen sonra uygulanmak uzere hazirlandi.

## 1. Gecis Oncesi Hazirlik

- ASP.NET prod kodu ve veritabani yedegi alinmis olacak
- Laravel `.env` degerleri prod veritabaniyla dogrulanacak
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` gercek alan adi / IP ile ayni olacak
- `APP_TIMEZONE=Europe/Istanbul`
- `tbPersonel`, `tbSiparisSatir`, `tbBolumHavuz`, `tbPersonelGorev`, `tbGorevler`, `tbBolumAraStok` tablolarinda veri varligi kontrol edilecek
- Laravel migration durumu not alinacak
- Tek komut smoke icin `docker compose exec -T laravel.test php scripts/cutover_smoke.php` calistirilacak
- Siparis parity smoke icin `docker compose exec -T laravel.test php scripts/order_parity_smoke.php` calistirilacak
- Veri butunlugu denetimi icin `docker compose exec -T laravel.test php scripts/legacy_data_audit.php` calistirilacak
- Rollback karari verilirse hangi saatte ASP.NET’e donulecegi net olacak

## 2. Login Smoke

- Admin kullanici ile giris
- Personel kullanici ile giris
- Admin `admin.index` aciliyor mu
- Personel `user.dashboard` aciliyor mu
- Admin olmayan kullanici admin rotalarina giremiyor mu

## 3. Siparis Smoke

- Aktif siparis listesi aciliyor mu
- Filtreler calisiyor mu
- Manuel eslestirme kaydi yaziliyor mu
- Ozel uretim rezervasyonu baglanabiliyor mu
- Rezervasyon iptali dogru durumu geri yapiyor mu

## 4. Is Emri Smoke

- Tekli manuel is emri veriliyor mu
- `Nihai` manuel is emri veriliyor mu
- `Ara Mamül` manuel is emri veriliyor mu
- `Ham Madde` manuel is emri veriliyor mu
- Siparisten is emri veriliyor mu
- Toplu is emri veriliyor mu
- Is emri iptalinde `tbBolumHavuz` ve `TamponDusumleri` geri geliyor mu
- `tbGorevler.No` cakismadan artiyor mu

## 5. Gorev Smoke

- Admin havuz ekraninda bekleyen gorevler gorunuyor mu
- Admin personel atamasi yapabiliyor mu
- Personel alinabilir gorevleri goruyor mu
- Personel gorev alip `tbPersonelGorev` kaydi olusuyor mu
- Personel uretim girisi yapinca `BekleyenAdet` dusuyor mu
- Gorev bitince `Onay` tamamlandi durumuna geciyor mu

## 6. Veri Dogrulama

Her kritik islemden sonra ASP.NET ve Laravel sonucunu asagidaki tablolar uzerinden karsilastir:

- `tbSiparisSatir`
- `tbBolumHavuz`
- `tbPersonelGorev`
- `tbGorevler`
- `tbBolumAraStok`
- `tbIsEmriGecmisi`

## 7. Go / No-Go

Tum maddeler saglanmadiysa tam gecis yapma.

- Login smoke gecti
- Siparis smoke gecti
- Is emri smoke gecti
- Gorev smoke gecti
- Veri karsilastirmada kritik fark yok
- `legacy_data_audit.php` ciktisi gozden gecirildi
- Rollback sorumlusu hazir

## 9. 6 Nisan 2026 Durum Notu

- `cutover_smoke.php` docker icinden basariyla gecti
- `order_parity_smoke.php` docker icinden basariyla gecti
- `legacy_data_audit.php` su an temiz degil
- Tespit edilen farklar:
- `tbPersonelGorev_missing_personnel = 6`
- `tbPersonelGorev_missing_component = 6`
- `tbSiparisSatir_missing_product = 4`
- Uygulama timezone'i `Europe/Istanbul` olarak dogrulandi
- Uygulama ortamı halen `local` ve debug acik; canliya cikmadan once production moda alinmali

## 8. Rollback

Asagidaki durumlardan biri varsa Laravel kullanimi durdurulacak ve ASP.NET’e geri donulecek:

- Login veya yetki problemi
- Is emri yazma hatasi
- Havuz / personel gorevi verisi kaybi
- Siparis durumu beklenmedik sekilde degisiyorsa
- Stok dusumu veya tampon geri yukleme yanlis calisiyorsa
