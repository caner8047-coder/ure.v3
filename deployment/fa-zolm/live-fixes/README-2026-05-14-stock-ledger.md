# 2026-05-14 Stok Ekstresi ve STOK-20260514-5820 Canli Duzeltmesi

## Dosya guncellemesi

Canlida `/home/zolmcomtr/fa_app` altinda su dosyalari ayni yollarla degistir:

- `app/Http/Controllers/PersonnelPanelController.php`
- `app/Http/Controllers/StocksController.php`
- `app/Http/Controllers/SiparisApiController.php`
- `resources/views/orders/index.blade.php`
- `resources/views/orders/special.blade.php`
- `resources/views/production/planning.blade.php`
- `resources/views/stocks/index.blade.php`

Ardindan cPanel Terminal:

```bash
cd /home/zolmcomtr/fa_app
/opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan cache:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan view:clear
```

## Veri duzeltmesi

phpMyAdmin'de canli veritabanini secip su dosyayi SQL olarak calistir:

`deployment/fa-zolm/live-fixes/2026-05-14-stock-6368-ledger-fix.sql`

Beklenen son kontrol:

- `Depodaki`: 128
- `Bosta`: 89
- `Gorevdeki`: 39

Personel ekranindaki `STOK-20260513-4564` / `DÖŞ/bench zem contes wolf 05 sütlü kahve`
bekleme durumunu acmak icin ikinci SQL:

`deployment/fa-zolm/live-fixes/2026-05-14-stock-1766-personnel-wait-fix.sql`

Beklenen son kontrol:

- `tbBolumAraStok.No = 11617`: `Depodaki 23`, `Bosta 23`, `Gorevdeki 0`
- `tbPersonelGorev.No = 24`: `Hazir 11`, `Bekleyen 0`
- `tbPersonelGorev.No = 26`: `Hazir 0`, `Bekleyen 11`

## Ne duzelir?

- Stok uretimi tamamlanan ana urun, normal musteri siparisi gibi gorevde kalmaz; bosta stoğa eklenir.
- Eski ekstre hareketlerinde siparis baglantisi eksikse, sistem hareket zamani ve `TamponDusumleri` alanindan bagli siparisi ekranda turetir.
- Stok ekstresi daha okunur bir aciklama, kaynak bazli etki ve canli baglanti bolumleri gosterir.
- Operasyon popover'i `Adet=0` olan personel gorevini artik "uretime hazir" saymaz; ilk gercek bekleyen asamayi gosterir.
- Uretim planlama ekraninda personelin kabul edebilecegi gorevler yesil, alt parca bekleyenler kirmizi gosterilir.
