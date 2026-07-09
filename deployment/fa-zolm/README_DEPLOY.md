# fa.zolm.com.tr Yayina Alma

Bu paket cPanel'de domain kok dizini `/home/zolmcomtr/fa.zolm.com.tr` olarak kalacak sekilde hazirlandi.

## 1. Gerekenler

- cPanel PHP surumu: PHP 8.4 veya uzeri.
- MySQL veritabani ve kullanicisi: cPanel > MySQL Databases bolumunden olustur.
- Veritabani kullanicisina ilgili veritabani icin tum yetkileri ver.

## 2. Dosyalari Yerlestirme

`fa_zolm_full_deploy_*.zip` dosyasini cPanel File Manager'da `/home/zolmcomtr` icine yukle ve extract et.

Extract sonrasi bu iki klasor olusmali:

- `/home/zolmcomtr/fa_app`
- `/home/zolmcomtr/fa.zolm.com.tr`

`fa.zolm.com.tr` klasoru cPanel'in web kokudur. `fa_app` klasoru Laravel uygulamasidir.

## 3. .env Ayari

`/home/zolmcomtr/fa_app/.env.fa.zolm.com.tr.example` dosyasini ayni klasorde `.env` olarak kopyala.

Su alanlari cPanel'de olusturdugun veritabanina gore guncelle:

- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

## 4. Veritabani Iceri Aktarma

phpMyAdmin'de olusturdugun veritabanini sec ve su dosyayi import et:

`/home/zolmcomtr/fa_app/database/fa_zolm_database.sql`

## 5. Terminal Komutlari

cPanel Terminal'de calistir:

```bash
cd /home/zolmcomtr/fa_app
/opt/cpanel/ea-php84/root/usr/bin/php artisan key:generate --force
/opt/cpanel/ea-php84/root/usr/bin/php artisan config:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan cache:clear
/opt/cpanel/ea-php84/root/usr/bin/php artisan view:clear
ln -sfn /home/zolmcomtr/fa_app/storage/app/public /home/zolmcomtr/fa.zolm.com.tr/storage
chmod -R 775 storage bootstrap/cache
```

Sunucuda PHP yolu farkliysa cPanel MultiPHP Manager veya Terminal'de `which php` ile aktif PHP yolunu kontrol et.

## 6. Kontrol

Tarayicida ac:

`https://fa.zolm.com.tr/login`

Bir sorun olursa ilk bakilacak dosya:

`/home/zolmcomtr/fa_app/storage/logs/laravel.log`
