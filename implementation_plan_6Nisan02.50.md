# Zemuretim ASP.NET → Laravel V3 Tam Migrasyon Planı

Fabrikada aktif olarak kullanılan ASP.NET üretim yazılımının Laravel'e birebir taşınması için detaylı karşılaştırma ve eksik tamamlama planı.

---

## Mevcut Durum Karşılaştırması

### ASP.NET Proje Yapısı (Kaynak)
- **SiparisApi.ashx** — 5.493 satır (42 action endpoint)
- **TopluIsEmriApi.ashx** — ~280 satır
- **AnaSayfa.aspx.cs** — 1.247 satır (BOM, stok, iş emri çekirdek fonksiyonları)
- **V1 Code-behind (.cs)** — ~15 dosya (AdminAnaSayfa, GorevAtama, IsEmriVer, Stoklar, UrunCiz, vb.)
- **V2 Frontend** — Inline JS ile AJAX çağrıları (Siparisler, OzelUretimTakip, KritikStok, vb.)

### Laravel V3 Mevcut Durum
- **SiparisApiController** — 2.013 satır (35 action endpoint ✅)
- **TopluIsEmriApiController** — 120 satır ✅
- **Services** — BomService, WorkOrderService, OrderSyncService, OrderToWorkOrderService (993 satır toplam)
- **Views** — 30+ Blade şablonu

---

## Sayfa Bazında Karşılaştırma Tablosu

### Admin Sayfaları

| # | ASP.NET Sayfası | Laravel Karşılığı | Durum | Notlar |
|---|---|---|---|---|
| 1 | `AdminAnaSayfa.aspx` (İş Emri Havuzu) | `admin/index.blade.php` → `admin.index` (7838 bytes) | ⚠️ Kısmi | V1'de 7 görünüm modu var. GridView'da güncelleme/silme/düzenleme inline yapılıyor. Laravel'de sadece listeleme var, **inline düzenleme yok** |
| 2 | `GorevAtama.aspx` (Görev Atama) | `tasks/assign.blade.php` → `tasks.assign` (6949 bytes) | ⚠️ Kısmi | V1'de AdetBelirle(), PersonelGorevTablosunaEkle(), AraStokAzalt() işlemleri var. Laravel backend'de **görev atama API'si eksik** |
| 3 | `IsEmriVer.aspx` (İş Emri Ver) | `workorders/create.blade.php` → `workorders.create` (5138 bytes) | ✅ Çalışıyor | BomService + WorkOrderService var |
| 4 | `TopluIsEmriVerme.aspx` (Toplu İş Emri) | `workorders/bulk.blade.php` → `workorders.bulk` (4861 bytes) | ✅ Çalışıyor | TopluIsEmriApiController var |
| 5 | `IsEmriGecmisi.aspx` (İş Emri Geçmişi) | `workorders/history.blade.php` → `workorders.history` (5930 bytes) | ✅ Çalışıyor | API endpoint var |
| 6 | `Stoklar.aspx` (Stok Yönetimi) | `stocks/index.blade.php` → `stocks.index` (6368 bytes) | ⚠️ Kısmi | CRUD API var ama **Excel export**, **tampon sıfırlama** ve **SonrakiUrunAdetleriniGuncelle2** zincirleme stok güncellemesi eksik olabilir |
| 7 | `Siparisler.aspx` (Sipariş Yönetimi) | `orders/index.blade.php` → `orders.index` (122KB) | ✅ Çalışıyor | Büyük dosya, tüm API'lar bağlı |
| 8 | `OzelUretimTakip.aspx` (Özel Üretim Takibi) | `orders/special.blade.php` → `orders.special` (111KB) | ✅ Çalışıyor | GİED, linkOrder, cancelWip mevcut |
| 9 | `KritikStokEsik.aspx` (Kritik Stok Eşik) | `stocks/critical.blade.php` → `stocks.critical` (41KB) | ✅ Çalışıyor | Threshold CRUD var |
| 10 | `UrunEslestirme.aspx` (Ürün Eşleştirme) | `products/match.blade.php` → `products.match` (45KB) | ✅ Çalışıyor | MatchCache API var |
| 11 | `VeritabaniDuzenle.aspx` (DB Yönetim) | `admin/database.blade.php` → `admin.database` (24KB) | ✅ Çalışıyor | AdminDatabaseController var |
| 12 | `UrunCiz.aspx` (Ürün Ağacı) | `products/tree.blade.php` → `products.tree` (4637 bytes) | ⚠️ Kısmi | BomService var ama **görsel ağaç çizimi ve interaktif grafik** eksik olabilir |
| 13 | `YeniUrunEkle.aspx` (Yeni Ürün Ekle) | `products/create.blade.php` → `products.create` (5628 bytes) | ⚠️ Kısmi | Sayfa var ama **backend CRUD boş** olabilir |
| 14 | `YeniUrunDuzenle.aspx` (Ürün Düzenle) | **YOK** | ❌ Eksik | ASP.NET'te ayrı düzenleme sayfası var |
| 15 | `UrunOzellikleriAyarlari.aspx` (Ürün Özellikleri) | `products/settings.blade.php` → `products.settings` (22KB) | ✅ Çalışıyor | AdminDatabaseController endpoints var |
| 16 | `Istatistikler.aspx` (İstatistikler) | `reports/statistics.blade.php` → `reports.statistics` (12KB) | ✅ Çalışıyor | ReportsController var |
| 17 | `PersonelRapor.aspx` (Personel Rapor) | `reports/personnel.blade.php` → `reports.personnel` (2506 bytes) | ⚠️ Kısmi | Sayfa var ama backend **veri API'si zayıf** olabilir |
| 18 | `GorevRapor.aspx` (Görev Rapor) | **YOK** | ❌ Eksik | V1'de görev bazlı rapor sayfası |
| 19 | `UretimBekleyenOzet.aspx` (Üretim Bekleyen Özet) | `production/pending.blade.php` → `production.pending` (3684 bytes) | ✅ Çalışıyor | getSummary API ile bağlı |
| 20 | `UretimPlanlama.aspx` (Üretim Planlama) | `production/planning.blade.php` → `production.planning` (3633 bytes) | ⚠️ Kısmi | View var ama **backend planlama mantığı eksik** |
| 21 | `PasifDevamEdenler.aspx` (Pasif Devam Eden) | **YOK (menüde yok, API var)** | ❌ Eksik | API endpoint var (`getPasifDevamEden`) ama **ayrı menü sayfası yok** |
| 22 | `SifreDegistirAdmin.aspx` (Şifre Değiştir) | `admin/password.blade.php` → `admin.password` | ✅ Çalışıyor | AuthController var |
| 23 | `SifremiUnuttum.aspx` (Şifremi Unuttum) | `auth/forgot-password.blade.php` → `password.request` | ✅ Çalışıyor | |
| 24 | `Ayarlar.aspx` (Ayarlar) | `admin/settings.blade.php` | ⚠️ Kısmi | Placeholder olabilir |

### Kullanıcı (Personel) Sayfaları

| # | ASP.NET Sayfası | Laravel Karşılığı | Durum | Notlar |
|---|---|---|---|---|
| 1 | `KullaniciAnaSayfa.aspx` (Personel Dashboard) | `user/dashboard.blade.php` → `user.dashboard` (4420 bytes) | ✅ Çalışıyor | PersonnelPanelController var |
| 2 | `PersonelGorev.aspx` (Görevlerim) | `user/tasks.blade.php` → `user.tasks` (2679 bytes) | ✅ Çalışıyor | |
| 3 | `AlinabilecekGorevler.aspx` (Alınabilir Görevler) | `user/available-tasks.blade.php` → `user.available` | ✅ Çalışıyor | |
| 4 | `TamamlananGorevler.aspx` (Tamamlanan Görevler) | `user/completed-tasks.blade.php` → `user.completed` | ✅ Çalışıyor | |
| 5 | `GorevGoruntuleme.aspx` (Görev Detay) | `user/task-detail.blade.php` → `user.task-detail` | ✅ Çalışıyor | |
| 6 | `VerilenGorevler.aspx` (Verilen Görevler) | `user/assigned-tasks.blade.php` → `user.assigned` | ✅ Çalışıyor | |
| 7 | `GorevRapor.aspx` (Görev Rapor) | `user/task-report.blade.php` → `user.report` | ✅ Çalışıyor | |
| 8 | `Mesajlar.aspx` (Mesajlar) | `user/messages.blade.php` → `user.messages` | ✅ Çalışıyor | |
| 9 | `PersonelGorevsiz.aspx` (Görevsiz Personel) | `admin/idle-personnel.blade.php` → `admin.idle` | ⚠️ Kısmi | Sadece admin görmeli |
| 10 | `Iletisim.aspx` (İletişim) | `pages/contact.blade.php` → `pages.contact` | ✅ Var | |
| 11 | `Hakkimizda.aspx` (Hakkımızda) | `pages/about.blade.php` → `pages.about` | ✅ Var | |

---

## Kritik Eksikler (Fabrika Geçişi İçin ZORUNLU)

### 🔴 Öncelik 1 — İş Akışı Kırıklıkları

> [!CAUTION]
> Bu eksikler fabrikada günlük üretim akışını doğrudan etkileyecek kritik öğelerdir.

#### 1. Görev Atama (GorevAtama) Backend API'si
- ASP.NET'te `GorevAtama.aspx.cs` — Personele görev atama, stok düşme, havuz güncelleme
- Laravel'de `tasks/assign.blade.php` view var ama **AdminDatabaseController.assignPoolTask** çok basit olabilir
- **Eksik işlevler:**
  - `PersonelGorevTablosunaEkle()` — tbPersonelGorev'e INSERT
  - `AraStokAzalt()` — Stoktan fiili düşme
  - `AdetBelirle()` — Maksimum üretilebilir adet kontrolü  
  - Havuz adetini güncellemek (ToplamAdet -= atanan)

#### 2. Havuz (AdminAnaSayfa) Inline İşlemleri
- ASP.NET'te GridView üzerinden satır düzenleme, silme, tampon stok kontrolü yapılıyor
- Laravel'de `admin/index.blade.php` sadece listeleme yapıyor
- **Eksik işlevler:**
  - Satır düzenleme (adet değiştirme)
  - Satır silme + stok iade
  - `tamponStokKontrol()` tetiklemesi
  - 7 farklı görünüm modu (bölüme göre filtre, tüm havuz, vb.)

#### 3. Pasif Devam Eden Sayfası
- ASP.NET'te `PasifDevamEdenler.aspx` ayrı bir menü sayfası
- Laravel'de API endpoint (`getPasifDevamEden`) var ama **view ve route yok**

#### 4. Stoklar — Zincirleme Güncelleme
- `SonrakiUrunAdetleriniGuncelle2()` — Stok eklendiğinde/değiştiğinde **upstream (üst)** ürünlerin havuz adetlerini otomatik günceller
- Bu çok kritik bir BOM zincirleme güncellemesi. Laravel'de `StocksController` basit CRUD yapıyor

---

### 🟠 Öncelik 2 — Fonksiyonel Eksikler

#### 5. Ürün Düzenleme Sayfası
- `YeniUrunDuzenle.aspx` — Mevcut ürünleri düzenleme
- Laravel'de yok, sadece ekleme sayfası var (`products/create.blade.php`)

#### 6. Görev Rapor Sayfası (Admin)
- `GorevRapor.aspx` — Admin'in tüm görevleri raporlaması
- Laravel'de admin tarafında bu sayfa yok

#### 7. Üretim Planlama Backend
- `UretimPlanlama.aspx` — Planlama mantığı
- Laravel'de view var ama backend API eksik

#### 8. İsçi Performans Raporu
- `IsciPerformans.aspx` — V1'de işçi performans görüntüleme
- Laravel'de yok

---

### 🟡 Öncelik 3 — Fonksiyonel İyileştirmeler

#### 9. Excel Export (Stoklar)
- ASP.NET Stoklar.aspx'te `btnAktar_Click` ile Excel export
- Laravel'de mevcut değil

#### 10. Ürün Resim Desteği
- ASP.NET'te `tbAraUrun.Resim` ve `tbUrunler.Resim` alanları kullanılıyor
- Laravel'de resim upload/gösterim yok

#### 11. Personel Görevsiz Filtresi Backend
- `PersonelGorevsiz.aspx` — Aktif görevi olmayan personel listesi
- Laravel'de `admin/idle-personnel.blade.php` var ama backend API zayıf

---

## User Review Required

> [!IMPORTANT]
> **Veritabanı Bağlantısı:** Fabrikada MSSQL kullanılıyor, Laravel MySQL ile çalışıyor. Fabrikadaki gerçek veritabanı verilerini Laravel'e aktarmak için bir veritabanı migrasyon stratejisi gerekiyor. Bunu daha önce yapmış olabilirsiniz (conversation loglarında `zemureti_dbZem_2026-03-24_09-28-07` backup dosyası görünüyor).

> [!WARNING]
> **Kritik Soru:** Fabrikada veriler halihazırda MySQL'e aktarılmış ve güncel mi? Yoksa MSSQL'den taze bir aktarım mı gerekiyor? Bu, planlama önceliğini değiştirir.

---

## Önerilen Uygulama Sırası

### Faz 1 — Kritik İş Akışları (Bu Gece)
1. **Görev Atama API'si** — Tam backend: AdetBelirle, PersonelGorev INSERT, stok düşme, havuz güncelleme
2. **Havuz (Admin Ana Sayfa) İşlemleri** — Inline edit/delete, 7 görünüm modu, stok iade
3. **Pasif Devam Eden Sayfası** — Route + view + menü linki
4. **Stok Zincirleme Güncelleme** — SonrakiUrunAdetleriniGuncelle2 mantığını StocksController'a ekle

### Faz 2 — Tamamlayıcı Sayfalar
5. Ürün Düzenleme sayfası
6. Görev Rapor sayfası
7. Üretim Planlama backend
8. İşçi Performans sayfası

### Faz 3 — Kullanılabilirlik
9. Excel Export özelliği
10. Ürün resim desteği
11. Görevsiz personel API güçlendirme

---

## Verification Plan

### Automated Tests
1. Her API endpoint'i `curl` ile test
2. Tüm admin sayfaları browser'da gezilerek kontrol
3. İş emri → Görev atama → Üretim tamamlama tam döngü testi

### Manual Verification
- Admin olarak giriş yapıp tüm menüleri gezmek
- İş emri vermek, görev atamak, stok güncellemek
- Personel olarak giriş yapıp görevleri görmek ve tamamlamak

---

## Open Questions

> [!IMPORTANT]
> 1. **Fabrikadaki veriler MySQL'e aktarıldı mı?** Yoksa taze bir MSSQL → MySQL aktarımı mı gerekiyor?
> 2. **Hangi sayfalar en çok kullanılıyor?** Önceliklendirme için kritik (Havuz, Görev Atama, Stoklar öncelikli mi?)
> 3. **Yarın sabah tüm bu eksiklerin tamamlanmasını mı istiyorsunuz, yoksa %80-90 yeterli mi?** Realistik olarak Faz 1'deki 4 kritik öğe bu gece tamamlanabilir, Faz 2 ise ek süre gerektirir.
