# Telegram Bildirim Modülü - Canlı Deployment Rehberi

**Sunucu:** Alastyr cPanel (eolo.alastyr.com)
**Proje Yolu:** /home/zolmcomt/fa_app
**Veritabanı:** zolmcomt_fabrika

---

## ADIM 1: Yeni Dosyaları Yükle

cPanel Dosya Yöneticisi'ne git → `/home/zolmcomt/fa_app/`

### Yüklenecek YENİ dosyalar:

| Kaynak (masaüstü) | Hedef (sunucu) |
|---|---|
| `app/Services/TelegramNotificationService.php` | `app/Services/TelegramNotificationService.php` |
| `app/Http/Controllers/TelegramSettingsController.php` | `app/Http/Controllers/TelegramSettingsController.php` |
| `app/Models/TelegramNotificationLog.php` | `app/Models/TelegramNotificationLog.php` |
| `app/Jobs/SendTelegramNotificationJob.php` | `app/Jobs/SendTelegramNotificationJob.php` |
| `app/Console/Commands/TestTelegramNotification.php` | `app/Console/Commands/TestTelegramNotification.php` |
| `database/migrations/2026_07_04_100000_create_telegram_notification_logs_table.php` | `database/migrations/2026_07_04_100000_create_telegram_notification_logs_table.php` |

### Değiştirilecek DOSYALAR (mevcut olanların üzerine):

| Kaynak | Hedef |
|---|---|
| `routes/web.php` | `routes/web.php` |
| `app/Http/Controllers/PersonnelPanelController.php` | `app/Http/Controllers/PersonnelPanelController.php` |
| `resources/views/admin/settings.blade.php` | `resources/views/admin/settings.blade.php` |
| `.env` | `.env` |

**Not:** Değiştirilecek dosyaları yüklemeden ÖNCE sunucudakilerin yedeğini al!

---

## ADIM 2: Veritabanı Tablosu Oluştur

phpMyAdmin → `zolmcomt_fabrika` → SQL sekmesi → aşağıdaki SQL'i çalıştır:

```sql
CREATE TABLE IF NOT EXISTS `telegram_notification_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(64) NOT NULL,
    `task_no` BIGINT UNSIGNED NULL,
    `order_no` BIGINT UNSIGNED NULL,
    `order_item_no` BIGINT UNSIGNED NULL,
    `message_body` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` TEXT NULL,
    `sent_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_event_task` (`event_type`, `task_no`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## ADIM 3: .env Dosyasını Güncelle

cPanel Dosya Yöneticisi → `.env` → Düzenle

Aşağıdaki satırları ekle (dosyanın sonuna):

```
# Telegram Bildirim
TELEGRAM_BOT_TOKEN=8954600072:AAFsC9MAmzgC2trOjldOjlFuZlDhb_M61z8
TELEGRAM_CHAT_ID=6180022743
```

**ÖNEMLİ:** Sunucuda QUEUE_CONNECTION muhtemelen `database`. Bunu `sync` yap:

```
QUEUE_CONNECTION=sync
```

Neden: cPanel'de queue worker çalıştıramazsın. `sync` ile job hemen çalışır.

---

## ADIM 4: Önbellek Temizle

cPanel → Terminal veya SSH üzerinden:

```bash
cd /home/zolmcomt/fa_app
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

Eğer SSH erişimin yoksa, cPanel'de "Uygulama Yöneticisi" veya "Cron Jobs" bölümünden:

```
cd /home/zolmcomt/fa_app && php artisan config:clear && php artisan cache:clear && php artisan route:clear
```

---

## ADIM 5: Test Et

Tarayıcıdan admin paneline git → Ayarlar → Telegram Bildirimleri bölümüne bak.

Veya SSH/Terminal üzerinden:

```bash
cd /home/zolmcomt/fa_app
php artisan telegram:test
```

Beklenen çıktı:
```
1) Bot token doğrulaniyor (getMe)... Başarili.
2) Chat ID doğrulaniyor (sendMessage)... Başarili.
Telegram baglantisi tamamen basarili.
```

---

## ADIM 6: Gerçek Görev Tamamlama Testi

Personel panelinden bir görevi "üretim girişi" ile tamamla. Telegram'a bildirim gitmeli.

---

## Kontrol Listesi

- [ ] Yeni dosyalar yüklendi (6 dosya)
- [ ] Mevcut dosyalar değiştirildi (4 dosya)
- [ ] Yedek alındı mı?
- [ ] SQL tablosu oluşturuldu
- [ ] .env güncellendi (TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID, QUEUE_CONNECTION=sync)
- [ ] Önbellek temizlendi
- [ ] telegram:test başarılı
- [ ] Gerçek görev tamamlama testi yapıldı

---

## Sorun Giderme

### "Bot token tanımlı değil" hatası
→ .env'de TELEGRAM_BOT_TOKEN doğru mu? Sonunda boşluk var mı?

### "Chat ID bulunamadı" hatası
→ Bot gruba eklendi mi? Gruba /start yazıldı mı?

### Bildirim gitmiyor
→ QUEUE_CONNECTION=sync mı? (database ise worker gereksinir)
→ php artisan config:clear çalıştırıldı mı?

### 403 hatası
→ Meta Cloud API'de numara onaylı mı? (test numarası yeterli)
