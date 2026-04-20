# Is Emri Merkezi Uygulama Checklist

Tarih: 20 Nisan 2026
Kaynak Belge: [is_emri_merkezi_teknik_spec.md](./is_emri_merkezi_teknik_spec.md)

## Sprint 0 - Hazirlik

- [ ] Teknik spec review toplantisi yap
- [ ] Event sozlugunu urun ve operasyon ekibi ile kilitle
- [ ] Ekranin yeni adini netlestir
- [ ] Event payload formatini kararlastir
- [ ] Route ve yetki modelini onayla

## Sprint 1 - Veri Tabanı ve Cekirdek Event Altyapisi

### Migration

- [ ] `work_order_events` migration yaz
- [ ] `work_order_snapshots` migration yaz
- [ ] Gerekli indexleri ekle

### Servisler

- [ ] `WorkOrderEventLogger` olustur
- [ ] `WorkOrderSnapshotProjector` iskeletini olustur
- [ ] `WorkOrderNarrationService` iskeletini olustur
- [ ] `WorkOrderCenterQueryService` iskeletini olustur

### Testler

- [ ] Event logger unit testleri
- [ ] Snapshot projector unit test iskeleti

## Sprint 2 - Siparis ve Is Emri Eventleri

### Entegrasyon

- [ ] Siparisten is emri verme aksiyonunu event logger'a bagla
- [ ] Toplu is emri verme aksiyonunu event logger'a bagla
- [ ] Manuel is emri verme aksiyonunu event logger'a bagla
- [ ] Tekli is emri iptali event'i ekle
- [ ] Toplu is emri iptali event'i ekle
- [ ] Pasife alma event'i ekle
- [ ] Tekrar aktif etme event'i ekle

### Testler

- [ ] Siparisten is emri verildi event testi
- [ ] Manuel is emri event testi
- [ ] Iptal event testi
- [ ] Snapshot guncelleme testleri

## Sprint 3 - GIED ve Stok Akislari

### Entegrasyon

- [ ] `wip_linked` event'i ekle
- [ ] `wip_unlinked` event'i ekle
- [ ] `stock_deducted` event'i ekle
- [ ] Kritik stok otomatik is emri event'i ekle
- [ ] Event summary metinlerini tamamla

### Testler

- [ ] GIED baglama timeline testi
- [ ] GIED iptal timeline testi
- [ ] Stoktan dusme event testi
- [ ] Snapshot `next_expected_action` testi

## Sprint 4 - Personel ve Planlama Akislari

### Entegrasyon

- [ ] `personnel_task_taken` event'i ekle
- [ ] `production_completed_partial` event'i ekle
- [ ] `production_completed_full` event'i ekle
- [ ] `personnel_task_deleted` event'i ekle
- [ ] `planning_incremented` event'i ekle
- [ ] `planning_decremented` event'i ekle
- [ ] `planning_rescheduled` event'i ekle

### Testler

- [ ] Personel gorev alma event testi
- [ ] Uretim tamamlama event testi
- [ ] Gorev silme event testi
- [ ] Planlama `+/-` event testi
- [ ] Planlama tasima event testi

## Sprint 5 - Query API ve UI

### API

- [ ] `GET /api/work-order-center/feed`
- [ ] `GET /api/work-order-center/entity/{type}/{id}`
- [ ] `GET /api/work-order-center/timeline`
- [ ] `GET /api/work-order-center/lookups`

### UI

- [ ] Yeni `workorders/center.blade.php` ekle
- [ ] Search hero
- [ ] Quick filters
- [ ] Ozet kartlar
- [ ] Timeline list
- [ ] Status panel
- [ ] Narration panel
- [ ] Related entities panel
- [ ] Alerts panel
- [ ] Technical details drawer

### UX

- [ ] Cocuk dostu sade metinler
- [ ] Teknik terimler icin tooltip
- [ ] Empty state tasarimi
- [ ] Loading state tasarimi

## Sprint 6 - Backfill ve Legacy Gecis

### Backfill

- [ ] `work-order-center:backfill` artisan komutu yaz
- [ ] `tbIsEmriGecmisi` -> `work_order_events` donusumu
- [ ] Legacy actor default mantigi
- [ ] Correlation id backfill mantigi

### Gecis

- [ ] Eski `is-emri-gecmisi` sayfasini yeni ekrana yonlendir
- [ ] Nav label guncelle
- [ ] Gerekirse eski tablo icin bilgi banner'i ekle

## Sprint 7 - Anomaly Detection ve Operasyon Kalitesi

- [ ] `IsEmriVerildi ama GorevNo yok` kuralini ekle
- [ ] `StokKarsilandi ama event yok` kuralini ekle
- [ ] `GIED linki var ama durum uyumsuz` kuralini ekle
- [ ] `Uretimde ama havuz/gorev yok` kuralini ekle
- [ ] Alert paneli UI'da goster
- [ ] Suggested fix metinlerini ekle

## Dosya Bazli Muhtemel Dokunus Haritasi

- [ ] `app/Services/OrderToWorkOrderService.php`
- [ ] `app/Http/Controllers/SiparisApiController.php`
- [ ] `app/Http/Controllers/PersonnelPanelController.php`
- [ ] `app/Http/Controllers/ProductionPlanningController.php`
- [ ] `app/Http/Controllers/AdminDatabaseController.php`
- [ ] `routes/web.php`
- [ ] `resources/views/layouts/app.blade.php`
- [ ] `resources/views/workorders/history.blade.php`

## Operasyon Kabul Testleri

- [ ] Bir siparise is emri verildiğinde timeline'a dusuyor
- [ ] Ayni kayit iptal edilince once/sonra durumlar dogru gorunuyor
- [ ] GIED baglaninca kaynak ekran ve actor gorunuyor
- [ ] Stoktan dusulunce son durum ve sonraki adim guncelleniyor
- [ ] Personel uretim girisi yapinca timeline hikayesi tamamlanıyor
- [ ] Planlama uzerinden adet degisikligi timeline'a dusuyor
- [ ] Arama kutusu siparis no ve gorev no ile sonuc buluyor
- [ ] Teknik detay drawer debug ihtiyacini karsiliyor

## Release Oncesi Kontrol

- [ ] Tum migration'lar calisiyor
- [ ] Tum testler yesil
- [ ] Backfill staging'de denendi
- [ ] Buyuk veri setinde feed performansi olculdu
- [ ] Yetkisiz kullanici teknik detay goremez
- [ ] Eski ekran redirect'i calisiyor

