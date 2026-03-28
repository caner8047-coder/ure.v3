#!/bin/bash
cd "$(dirname "$0")"

# MacOS path fix (ensure /usr/local/bin, /opt/homebrew/bin, and Docker paths are in PATH for Docker)
export PATH="/Applications/Docker.app/Contents/Resources/bin:$HOME/.docker/bin:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:$PATH"

echo "Zem Uretim Fix Uygulanıyor..."
echo "--------------------------"

# Check if docker command is available
if ! command -v docker &> /dev/null
then
    echo "HATA: Docker komutu bulunamadı. Lütfen Docker Desktop'ın kurulu ve çalışıyor olduğundan emin olun."
    read -p "Kapatmak için Enter'a basın..."
    exit 1
fi

echo "Veritabanı sıfırlanıyor..."
./vendor/bin/sail artisan migrate:fresh

echo "--------------------------"
echo "SQL yedeği içe aktarılıyor (UTF-8)..."
./vendor/bin/sail mysql < zemureti_dbZem_utf8.sql

echo "--------------------------"
echo "Örnek veriler ve admin hesabı yükleniyor (Seeder)..."
./vendor/bin/sail artisan db:seed

echo "--------------------------"
echo "İşlem başarıyla tamamlandı!"
echo "Şimdi admin@zemmobilya.com / 123456 ile giriş yapmayı deneyebilirsiniz."
echo "--------------------------"
read -p "Kapatmak için Enter'a basın..."
