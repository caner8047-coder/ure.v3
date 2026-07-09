# ZemuRetim v3 — Kapsamlı Geliştirme Raporu & Plan

**Hazırlanma Tarihi:** 9 Temmuz 2026
**Durum:** Aktif Geliştirme
**Versiyon:** v3 (ASP.NET → Laravel geçiş projesi)

---

## 1. PROJE ÖZETİ

ZemuRetim v3, mobilya üretim tesisinin (ZemMobilya — bohem tarzı mobilya üreticisi) tüm üretim yaşam döngüsünü yöneten bir **MES/ERP** sistemidir. Proje, ASP.NET WebForms / MSSQL tabanlı eski sistemden Laravel 13 / PHP 8.5 / MySQL'e taşınma sürecindedir.

**Yönetilen İş Akışları:**
- E-ticaret platformlarından (Trendyol, Hepsiburada, N11) sipariş çekme ve senkronizasyon
- BOM (Ürün Ağacı / Malzeme Listesi) yönetimi — çok katmanlı ağaç yapısı
- İş emri oluşturma (tekli, toplu, manuel, siparişten)
- Havuz bazlı görev atama — departmanlar arası iş dağılımı
- Stok/tampon yönetimi
- Özel üretim (GIED) takibi
- Event sourcing ile iş emri merkezi (timeline, snapshot, anomali tespiti)
- Telegram bildirimleri
- AI entegrasyonu (LLM destekli karar destek)

**Operasyonel Ölçek (canlı veri, Nisan 2026):**
- 1.287 sipariş satırı (~45 ürün tipi)
- 846 iş emri, 9 departman
- 2.698 event-sourced aksiyon
- 35 personel
- Üretim departmanları: Kesimhane, Marangozhane, Boyahane, Terzihane, Dosymehane, YM Depo, Urun Depo, Paketleme, Demirhane

---

## 2. TEKNİK ALTYAPI

### 2.1 Teknoloji Yığını

| Katman | Teknoloji | Versiyon |
|---|---|---|
| Backend | Laravel | 13.x |
| PHP | PHP | 8.5 (bleeding edge) |
| Frontend Build | Vite | 8.x |
| CSS Framework | Tailwind CSS | 4.x (Vite-native plugin) |
| Custom UI | minimal-ui.css | 2.939 satır, hand-written design system |
| Veritabanı | MySQL (prod) / SQLite (test) | MySQL 8.4 |
| Queue/Cache | Database driver (Redis tanımlı ama devre dışı) | — |
| Test | PHPUnit | 12.5 |
| Bildirim | Telegram Bot API | — |
| AI | OpenRouter (AIBrainService) | — |
| Deploy | Docker Compose + Manuel cPanel | — |
| Arama | Meilisearch (Docker, prod durumu belirsiz) | — |

### 2.2 Bağımlılık Sorunları

- `package.json` `dependencies` içinde ~50 paket var — bunlar Vite'in kendi bağımlılıkları, uygulama paketleri değil. `devDependencies`'a taşınmalı.
- `tailwind.config.js` yok (Tailwind v4 gerektirmez, doğru).

---

## 3. MİMARİ ANALİZ

### 3.1 Controller Katmanı (14 Controller)

| Controller | Satır | Sorumluluk |
|---|---|---|
| `SiparisApiController` | **6.834** | Sipariş API entegrasyonu — Trendyol/Hepsiburada/N11 |
| `PersonnelPanelController` | **3.200** | Personel self-service paneli |
| `AdminDatabaseController` | **2.618** | Admin veritabanı CRUD işlemleri |
| `ProductionPlanningController` | **1.793** | Üretim planlama ve görev atama |
| `StocksController` | ~1.200 | Stok yönetimi, import/export |
| `ReportsController` | ~900 | Raporlama, dashboard istatistikleri |
| `WorkOrderCenterController` | ~600 | İş emri merkezi |
| `WorkOrderPreviewController` | ~300 | İş emri önizleme |
| `TelegramSettingsController` | ~250 | Telegram ayarları |
| `TelegramWebhookController` | ~150 | Telegram webhook alıcı |
| `AIChatController` | ~100 | AI sohbet arayüzü |
| `AdminController` | ~500 | Admin sayfaları, siparişler, mesajlar |
| `AuthController` | ~200 | Giriş/çıkış, şifre yönetimi |
| `TopluIsEmriApiController` | ~400 | Toplu iş emri API'si |

**Toplam Controller:** ~19.000+ satır

### 3.2 Service Katmanı (18 Service)

| Service | Satır | Bağımlılık | Rol |
|---|---|---|---|
| `BomService` | **1.382** | — | Çekirdek BOM motoru, stok matematiği, tampon yönetimi |
| `WorkOrderCenterQueryService` | **1.042** | WorkOrderNarrationService, BomService | Event-sourced read model |
| `OrderSyncService` | **993** | StockMovementLogger, WorkOrderEventLogger | Sipariş yükleme, eşleştirme, set mantığı |
| `WorkOrderBomPreviewService` | **790** | BomService | Read-only BOM simülasyonu |
| `ProductMergeService` | **724** | — | Ürün/bileşen birleştirme |
| `AIBrainService` | **546** | AppSettingService | OpenRouter AI chatbot |
| `WorkOrderSnapshotProjector` | **521** | WorkOrderAnomalyDetector, BomService | CQRS snapshot projeksiyonu |
| `OperationalDataResetService` | **479** | — | Test verisi sıfırlama |
| `WorkOrderService` | **377** | BomService, LegacyWorkOrderWriter | Yüksek seviye iş emri oluşturma |
| `WorkOrderEventLogger` | **320** | WorkOrderSnapshotProjector | Event store yazıcı |
| `OrderToWorkOrderService` | **308** | BomService, WorkOrderService | Sipariş → iş emri köprüsü |
| `StockMovementLogger` | **300** | — | Stok denetim kaydı |
| `PersonnelTaskMerger` | **249** | — | Mükerrer görev birleştirme |
| `TelegramNotificationService` | **226** | AppSettingService | Telegram Bot API |
| `WorkOrderAnomalyDetector` | **185** | — | Veri kalitesi kontrolü |
| `WorkOrderNarrationService` | **150** | — | Türkçe anlatım üretimi |
| `AppSettingService` | **90** | — | Key-value ayar deposu |
| `LegacyWorkOrderWriter` | **69** | — | Eski tbGorevler yazıcı |

**Toplam Service:** ~7.481 satır

### 3.3 Model Katmanı (20 Model)

| Model | Tablo | İlişkiler |
|---|---|---|
| `User` | users | — |
| `Personnel` | personnel | department, tasks |
| `Department` | departments | personnel, stocks |
| `DepartmentStock` | department_stocks | department, component |
| `Product` | products | components (BOM) |
| `Component` | components | — |
| `Order` | orders | items |
| `OrderItem` | order_items | order |
| `WorkOrder` | work_orders | events, snapshots |
| `WorkOrderEvent` | work_order_events | workOrder |
| `WorkOrderSnapshot` | work_order_snapshots | workOrder |
| `Task` | tasks | — |
| `StockMovement` | stock_movements | — |
| `ProductionPool` | production_pool | — |
| `SetDefinition` | set_definitions | contents |
| `SetContent` | set_contents | definition |
| `CriticalStockThreshold` | critical_stock_thresholds | — |
| `ProductMatchCache` | product_match_cache | — |
| `TelegramNotificationLog` | telegram_notification_logs | — |
| `Message` | messages | — |

### 3.4 Veritabanı Yapısı

**23 migration dosyası**, en son: `2026-07-04` (telegram_notification_logs)

**Legacy tablolar** (`tbSiparisSatir`, `tbAraUrun`, `tbGorevler`, `tbPersonel`, `tbBolumHavuz` vb.) hâlâ aktif kullanımda — ASP.NET köprüsü olarak.

### 3.5 Route Yapısı

`routes/api.php` **yok** — tüm API endpoint'leri `routes/web.php` içinde `api/*` prefix'i ile tanımlı. Bu, token-based auth yerine session/cookie tabanlı auth kullanıldığı anlamına gelir.

**Route Dağılımı:**
- Stocks API: 11 route
- Reports API: 8 route
- Database Admin API: ~35 route
- Personnel Panel API: ~18 route
- Production Planning API: ~15 route
- Work Order Preview/Center: 9 route
- Telegram API: 7 route
- AI Chat API: 2 route
- Admin sayfaları: ~25 route
- User sayfaları: ~10 route
- Legacy `.aspx/.ashx` alias'ları: ~30 route

### 3.6 Frontend / UI

**44 Blade template** — 12 dizinde organize.

**Dual layout sistemi:**
- `layouts/app.blade.php` — Admin sidebar layout (5 navigasyon grubu, 22 öğe)
- `layouts/user.blade.php` — Personel paneli (top navbar)

**CSS stratejisi:** Vite-built Tailwind CSS (44KB) + statik `minimal-ui.css` (2.939 satır) birlikte yükleniyor. minimal-ui.css Vite pipeline'ından geçmiyor.

**JS mimarisi:** Vanilla JS + Axios AJAX. SPA benzeri sayfa yenileme yok, tüm veriler client-side'da fetch ediliyor. Blade template'ler içinde inline JS mevcut.

**Türkçe karakter normalizasyonu:** `ui-text-normalizer.blade.php` — DOM'daki ASCII Türkçe karakterleri runtime'da Unicode'a dönüştürüyor (örn: "Urun" → "Ürün"). Bu, backend/DB'de Unicode'un düzgün kullanılmadığını gösteriyor.

---

## 4. KRİTİK SORUNLAR

### 4.1 🔴 GOD OBJECT CONTROLLER'LAR

| Dosya | Satır | Risk |
|---|---|---|
| `SiparisApiController.php` | **6.834** | Single Responsibility ihlali, test edilemez |
| `PersonnelPanelController.php` | **3.200** | Aşırı şişkin, bakım imkansız |
| `AdminDatabaseController.php` | **2.618** | CRUD ile iş mantığı karışmış |

### 4.2 🔴 GÜVENLİK AÇIKLARI

1. **SQL dump dosyaları repoda:**
   - `zemureti_mysql_data_full.sql` (2.3 MB) — muhtemelen gerçek üretim verisi
   - `zem_utf8.sql` (2.5 MB)
   - `zemureti_dbZem_mysql_fixed_utf8.sql` (918 KB)
   - `zemureti_dbZem_mysql_fixed.sql` (900 KB)
   - `zemureti_dbZem_utf8.sql` (878 KB)
   - `zemureti_dbZem_fixed.sql` (878 KB)
   - `zemureti_dbZem_mysql.sql` (830 KB)
   - `fix_encoding.sql` (19 KB)
   - `storage/database_backups/` içinde 4 tam dump (toplam ~17 MB)
   - **Toplam: 17 SQL dosyası, ~28 MB**

2. **Telegram bot tokenı hardcoded:**
   `deployment/webhook-kur.command` içinde `8954600072:AAFsC9MAmzgC2trOjldOjlFuZlDhb_M61z8`

3. **Secret scanning yapılmamış.**

### 4.3 🟡 TEST KAPASİTESİ EKSİKLİĞİ

- `phpunit.xml` mevcut, 18 test dosyası var
- Controller'lar için test yazmak şu haliyle imkansız (6.834 satırlık controller test edilemez)
- Service katmanı için test coverage belirsiz

### 4.4 🟡 REDIS DEVRE DIŞI

- Queue ve Cache için `database` driver kullanılıyor
- `QUEUE_CONNECTION=redis` ve `CACHE_STORE=redis` tanımlı ama aktif değil
- Yüksek yük altında darboğaz oluşacak

### 4.5 🟡 DUAL CSS STRATEJİSİ

- Tailwind CSS 4.x (Vite pipeline) + minimal-ui.css (statik) birlikte yükleniyor
- minimal-ui.css Vite'dan geçmiyor, optimizasyon eksik
- Tailwind ve custom CSS çakışması riski

### 4.6 🟡 ENCODING SORUNLARI

- `fix_encoding.sql`, `fix_sql_dump.sh` dosyaları var
- Birden fazla `_utf8`, `_fixed` suffix'li SQL dosyası
- `ui-text-normalizer` — runtime'da ASCII→Unicode düzeltmesi
- Bu sorunlar ciddi vakit kaybına neden olmuş

### 4.7 🟢 FORM REQUEST YOK

- Tüm validasyon inline `$request->validate(...)` ile yapılıyor
- En büyük Controller'larda validasyon karmaşık ve dağılmış
- Form Request sınıfları oluşturulmalı

---

## 5. GÜÇLÜ YÖNLER

1. **Service Layer mimarisi** doğru uygulanmış — iş mantığı Controller'lardan izole
2. **Event Sourcing** temelleri atılmış: WorkOrderEvent + WorkOrderSnapshot + WorkOrderSnapshotProjector
3. **Stock audit trail** (StockMovementLogger) — her stok değişikliği loglanıyor
4. **Legacy köprü** mekanizması — eski ASP.NET sistemine yazma destekleniyor
5. **Telegram entegrasyonu** tamamlanmış — bildirim loglama dahil
6. **AI Brain Service** entegre edilmiş — LLM destekli karar destek
7. **Docker ile konteynerize** — compose.yaml mevcut
8. **Anomali tespiti** (WorkOrderAnomalyDetector) gibi ileri özellikler geliştirilmiş
9. **Dual database desteği** — SQLite (dev) ve MySQL (prod) arasında geçiş
10. **CQRS read model** — WorkOrderCenterQueryService ile event sourcing'den okuma

---

## 6. GELİŞTİRME PLANI

### FAZ 1 — TEKNİK BORÇ TEMİZLİĞİ (2-3 Hafta) 🔴 ÖNCELİK

#### 1.1 GÜVENLİK — P0 (Hemen)

| # | Görev | Efor | Detay |
|---|---|---|---|
| 1.1.1 | SQL dump dosyalarını repodan sil | 30 dk | `zemureti_mysql_data_full.sql`, `zem_utf8.sql` vb. tüm root-level SQL dosyaları + `storage/database_backups/` |
| 1.1.2 | `.gitignore` güncelle | 15 dk | `*.sql`, `storage/database_backups/`, `dbZem.bak` ekle |
| 1.1.3 | Git history'den SQL dosyalarını temizle | 1-2 saat | `git filter-repo` ile büyük dosyaları sil + force push |
| 1.1.4 | Telegram token rotasyonu | 15 dk | Yeni token üret, `.env`'a taşı, hardcoded referansları sil |
| 1.1.5 | Secret scanning çalıştır | 30 dk | `gitleaks` veya `trufflehog` ile tarama |
| 1.1.6 | `.env.example` güncelle | 30 dk | Tüm environment değişkenlerini ekle (Telegram, AI API key, DB config) |

#### 1.2 CONTROLLER REFACTORING — P1 (1-2 Hafta)

**SiparisApiController (6.834 → ~5-6 dosya):**

| Yeni Controller | Sorumluluk |
|---|---|
| `OrderImportController` | Sipariş içe aktarma (Trendyol, Hepsiburada, N11 API) |
| `OrderStatusController` | Sipariş durumu güncelleme, senkronizasyon |
| `OrderMatchController` | Sipariş-ürün eşleştirme (ProductMatchCache) |
| `OrderSetController` | Set/montaj grubu yönetimi |
| `OrderBulkController` | Toplu sipariş işlemleri |
| `OrderLegacyBridgeController` | ASP.NET köprü endpoint'leri (.ashx) |

**PersonnelPanelController (3.200 → ~3-4 dosya):**

| Yeni Controller | Sorumluluk |
|---|---|
| `PersonnelTaskController` | Görev listesi, başlat/tamamla |
| `PersonnelAvailableTaskController` | Mevcut görevler, havuz |
| `PersonnelMessageController` | Mesajlaşma |
| `PersonnelDependencyController` | Bağımlılık bildirimleri |

**AdminDatabaseController (2.618 → ~4-5 dosya):**

| Yeni Controller | Sorumluluk |
|---|---|
| `AdminPersonnelController` | Personel CRUD |
| `AdminDepartmentController` | Departman CRUD |
| `AdminComponentController` | Bileşen CRUD, BOM yolları |
| `AdminProductController` | Ürün CRUD, ayarlar |
| `AdminPoolController` | Havuz görev yönetimi |

#### 1.3 FORM REQUEST SINIFLARI — P1

Her Controller'ın validasyon mantığı için `FormRequest` sınıfları oluşturulmalı. En kritik olanlar:
- `OrderImportRequest`
- `StockUpdateRequest`
- `PersonnelTaskRequest`
- `ComponentStoreRequest`
- `ProductStoreRequest`

#### 1.4 TEST ALTYAPISI — P1

| # | Görev | Öncelik |
|---|---|---|
| 1.4.1 | `BomService` unit testleri (en kritik service, 10+ bağımlılık) | Yüksek |
| 1.4.2 | `OrderSyncService` unit testleri | Yüksek |
| 1.4.3 | `StockMovementLogger` unit testleri | Orta |
| 1.4.4 | `WorkOrderService` unit testleri | Orta |
| 1.4.5 | Kritik API endpoint'leri için feature testleri | Orta |

---

### FAZ 2 — PERFORMANS & ALTYAPI (2-3 Hafta) 🟡

#### 2.1 REDİS AKTİVASYONU

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

- Sipariş senkronizasyon job'ları Redis'e taşınmalı
- Telegram bildirim job'ları Redis'e taşınmalı
- BOM query caching Redis ile desteklenmeli

#### 2.2 BOM SERVICE OPTİMİZASYONU

BomService (1.382 satır) en çok kullanılan service:
- N+1 sorgu problemi kontrolü ve eager loading
- Recursive BOM traversal için query caching
- Bulk operations için chunked processing

#### 2.3 API RATE LIMITING

- SiparisApiController'daki harici API çağrıları için throttle middleware
- `RateLimiter::for('api', ...)` tanımlaması
- Per-user ve per-endpoint limitler

#### 2.4 TOPLU İŞ EMRİ ASYNC JOB PIPELINE

`TopluIsEmriApiController` — büyük toplu işlemlerde timeout riski:
- Laravel Queue Jobs'a dönüştürülmeli
- Progress tracking (Redis pub/sub veya DB polling)
- Batch chunking ile bellek kullanımı azaltılmalı

#### 2.5 CSS BİRLEŞTİRME

- minimal-ui.css → Tailwind CSS'e veya Vite pipeline'a dahil edilmeli
- Tek CSS stratejisi benimsenmeli
- Unused CSS temizliği

---

### FAZ 3 — YENİ ÖZELLİKLER & UI (3-4 Hafta) 🟢

#### 3.1 UI MODERNİZASYONU (Yarım Kaldı)

`Modernizing Zemuretim UI Design.md` dosyasında planlanmış:
- Tüm Blade view'lar minimal Slate/Teal temaya geçilmeli
- Hero banner'lar kaldırılmalı
- Kompakt sidebar + topbar uygulanmalı
- Kullanıcı deneyimi iyileştirmeleri

#### 3.2 AI CHAT MODÜLÜ GELİŞTİRME

`AIBrainService` + `AIChatController` altyapısı mevcut:
- Üretim anomali tespiti için AI entegrasyonu
- Sipariş öneri sistemi
- Stok optimizasyonu önerileri
- Doğal dil ile veri sorgulama

#### 3.3 REAL-TIME BİLDİRİMLER

- Şu an sadece Telegram bildirimi var
- Laravel Broadcasting + WebSocket (Reverb veya Pusher) eklenmeli
- Panel içi real-time güncellemeler
- Stok değişiklikleri, yeni siparişler, görev atamaları için live updates

#### 3.4 RAPORLAMA GELİŞTİRME

- PDF export (dompdf veya snappy)
- Grafiksel dashboard (Chart.js veya ApexCharts)
- Tarihli karşılaştırma raporları
- Üretim verimlilik raporları
- Export formatları: PDF, Excel, CSV

#### 3.5 API LAYER İYİLEŞTİRME

- `routes/api.php` oluştur (web.php'den ayır)
- Token-based auth (Sanctum veya Passport)
- API versioning (v1, v2)
- OpenAPI/Swagger dokümantasyonu

#### 3.6 OBSERVABILITY

- Laravel Telescope entegrasyonu
- Structured logging (JSON format)
- Hata takibi (Sentry veya Bugsnag)
- Performans monitoring

---

## 7. ÖNCELİK MATRİSİ

| Görev | Etki | Efor | Öncelik | Faz |
|---|---|---|---|---|
| SQL dosyalarını repodan sil | 🔴 Güvenlik | Düşük | **P0** | 1 |
| Telegram token rotasyonu | 🔴 Güvenlik | Düşük | **P0** | 1 |
| Secret scanning | 🔴 Güvenlik | Düşük | **P0** | 1 |
| God Controller'ları böl | Yüksek | Yüksek | **P1** | 1 |
| Service unit testleri | Yüksek | Orta | **P1** | 1 |
| Form Request sınıfları | Orta | Orta | **P1** | 1 |
| Redis aktivasyonu | Yüksek | Düşük | **P1** | 2 |
| BOM optimizasyonu | Yüksek | Orta | **P2** | 2 |
| API rate limiting | Yüksek | Düşük | **P2** | 2 |
| CSS birleştirme | Orta | Orta | **P2** | 2 |
| UI modernizasyonu | Orta | Yüksek | **P2** | 3 |
| AI modülü genişletme | Yüksek | Yüksek | **P3** | 3 |
| Real-time broadcasting | Orta | Orta | **P3** | 3 |
| PDF raporlama | Orta | Düşük | **P3** | 3 |
| API layer separation | Yüksek | Yüksek | **P3** | 3 |

---

## 8. UYGULAMA SIRASI

### Hafta 1-2: Güvenlik & Temizlik
1. SQL dump dosyalarını sil + Git history temizliği
2. Telegram token rotasyonu
3. Secret scanning
4. `.env.example` güncelleme
5. `.gitignore` güncellemesi

### Hafta 2-4: Controller Refactoring
1. `SiparisApiController` → 6 parçaya böl
2. `PersonnelPanelController` → 4 parçaya böl
3. `AdminDatabaseController` → 5 parçaya böl
4. Form Request sınıfları oluşturma

### Hafta 3-5: Test & Kalite
1. BomService unit testleri
2. OrderSyncService unit testleri
3. Feature testleri (kritik API'ler)
4. CI pipeline iyileştirmesi

### Hafta 5-7: Performans
1. Redis aktivasyonu
2. BOM query optimization
3. API rate limiting
4. Toplu iş emri async job'a dönüştürme
5. CSS birleştirme

### Hafta 7-10: Yeni Özellikler
1. UI modernizasyonu tamamlama
2. AI modülü genişletme
3. Real-time bildirimler
4. PDF raporlama
5. API layer separation

---

## 9. RİSK DEĞERLENDİRMESİ

| Risk | Olasılık | Etki | Mitigasyon |
|---|---|---|---|
| SQL dump'larda hassas veri sızıntısı | Yüksek | Kritik | Hemen sil + Git history temizle |
| Controller bölme sırasında regression | Orta | Yüksek | Feature testleri önceden yaz, checkpoint al |
| Redis geçişinde downtime | Düşük | Orta | Aşamalı geçiş, fallback mekanizması |
| BOM optimizasyonunda veri tutarsızlığı | Düşük | Yüksek | Extensive test coverage, staging ortamı |
| UI değişikliklerinde kullanıcı direnci | Orta | Orta | Kullanıcı testleri, aşamalı geçiş |

---

## 10. SONUÇ

ZemuRetim v3, güçlü bir Service Layer mimarisine ve modern event sourcing temellerine sahip, kapsamlı bir üretim yönetim sistemidir. En kritik sorunlar **güvenlik** (SQL dump'lar, hardcoded token) ve **code smell** (God Object Controller'lardır).

Önerilen yol: **Güvenlik → Refactoring → Test → Performans → Yeni Özellikler** sıralamasıyla ilerlemek. Her fazdan önce checkpoint almak, küçük adımlarla onay alarak çalışmak güvenli geçişi sağlayacaktır.

---

*Bu rapor, 9 Temmuz 2026 itibarıyla projenin durumunu yansıtmaktadır.*
