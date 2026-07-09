# Stok Tampon Coklu Satir Fix

## Amaç

`MAR/ayak ahsap torna 25cm tosca` gibi aynı ara ürünün birden fazla stok satırı olduğunda sistem ilk satırı `0/0` görüp stok varken gereksiz iş emri açabiliyordu.

Bu paket:

- İş emri açarken tamponu ilk satırdan değil, uygun stok satırlarından sırayla düşer.
- Yeni `TamponDusumleri` kayıtlarına `stokNo` ekler.
- İş emri iptalinde tamponu mümkünse aynı stok satırına geri koyar.
- `STOK-20260704-3045` satır `#97` için oluşmuş hatalı canlı veriyi onaracak SQL dosyasını içerir.

## Canlıya Uygulama Sırası

1. Canlı dosyaları ve veritabanını yedekleyin.
2. Zip içindeki dosyaları aynı klasör yollarına yükleyin:
   - `app/Services/BomService.php`
   - `app/Http/Controllers/SiparisApiController.php`
3. Sunucuda Laravel cache temizleyin:

```bash
php artisan optimize:clear
```

4. phpMyAdmin veya MySQL konsolundan şu dosyayı çalıştırın:

```text
deployment/stok_tampon_coklu_satir/repair_stok_1411_siparis_97.sql
```

## Beklenen Veri Onarım Sonucu

- `tbPersonelGorev.No=52` silinir.
- `tbBolumAraStok.No=14066` için `TamponMiktar` `150` değerinden `139` değerine iner.
- `tbPersonelGorev.No=53` için `Adet=11`, `BekleyenAdet=0` olur.
- `tbSiparisSatir.No=97` `TamponDusumleri` içine `{"araNo":1411,"adet":11,"stokNo":14066,"bolumNo":4}` eklenir.

SQL tekrar çalıştırılırsa koşullar tutmayacağı için ikinci kez stok düşmez.

## Localde Yapılan Kontroller

```text
php -l app/Services/BomService.php
php -l app/Http/Controllers/SiparisApiController.php
php artisan test tests/Unit/BomServiceBufferReservationTest.php tests/Unit/OrderToWorkOrderServiceTest.php
```

Sonuç: `3 passed`.
