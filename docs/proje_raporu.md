# Proje Gelişim ve Durum Raporu
**(Not: Dosya her yeni modül eklendiğinde güncellenmelidir)**
**Güncelleme Tarihi:** 23 Mart 2026

## 1. Altyapı ve Veritabanı Modülü
- **Çekirdek Sistem (Backend):** Laravel 11.x, PHP 8.5 kuruldu, Sail (Docker) ile çalışır duruma getirildi. `app/` kütüphaneleri MVC normlarına dizildi.
- **Veritabanı (Migration):** Eski `MSSQL` yedeğinde yer alan şema ve veriler incelenerek `MySQL` ortamına uyarlandı ve taşındı (CSV Yöntemi kullanıldı).
- Veritabanı Yapısı: `users` tablosu başta olmak üzere custom tabloların ilişkisel bağları ORM ile yansıtılmaktadır. `C_TblAraAdlar` vs. referansları güvendedir.

## 2. API ve Servisler
- `routes/web.php` üzerinde rotalama yapılmıştır.
- `SiparisApiController.php` başarıyla entegre edilip `OrderSyncService.php` içerisindeki tüm bağımlılıklar kontrol edilmiştir. `UploadOrders`, `GetOrders` gibi kritik uç noktalar işler durumdadır.

## 3. Sayfa Durumları ve Arayüz Akışı (Ne Nerede?)

**Siparişler Yönetimi:**
- `Siparisler.aspx` → `resources/views/orders/index.blade.php` (Frontend tamamlandı, API bağlandı).

**Özel Üretim Takip:**
- `OzelUretimTakip.aspx` → `resources/views/orders/special.blade.php` (Tasarım/Blade geçişi yapıldı, API verisi bekleniyor - Faz 4).

**Ürün Ayarları & Eşleştirme Sistemi:**
- `UrunOzellikleriAyarlari.aspx` → `resources/views/products/settings.blade.php` (Excel benzeri Handsontable yapısı ile kodlandı, Blade ve Frontend entegre edildi).
- `UrunEslestirme.aspx` → `resources/views/products/match.blade.php` (Tasarım aktarıldı, dinamik veri bekleniyor - Faz 4).

**Stok ve İstatistik Sistemi:**
- `Stoklar.aspx` → `resources/views/stocks/index.blade.php` (Frontend ve Backend Tamamlandı. HTML dizilimi aktarıldı, ASP gridleri tamamen API beslemeli JS tablolara dönüştürüldü).
- `KritikStokEsik.aspx` → `resources/views/stocks/critical.blade.php` (Arayüz aktarıldı, veri bağlama sürecinde).
- `Istatistikler.aspx` → `resources/views/reports/statistics.blade.php` (Frontend ve Backend tamamlandı. `ReportsController` ile Eloquent üzerinden Chart.js verileri saf JSON API olarak bağlandı).

**Admin & Veritabanı Yönetim Paneli:**
- `VeritabaniDuzenle.aspx` → `resources/views/admin/database.blade.php` (Frontend ve Backend tamamlandı. Eski 1600 satırlık karmaşık `asp:GridView` yapısı 4 sekmeli (Personel, Bolum, AraUrun, Urun), saf JS tabanlı ve `AdminDatabaseController`'dan tam REST API CRUD beslemeli modern bir yapıya geçirildi. Excel Export entegrasyonu JS üzerinden bağlandı).

## 4. Projenin Yol Haritası (Eksik Kalanlar)
1. **Faz 4:** V3 yapısındaki tüm Frontend Blade sayfalarındaki statik/bozuk (asp tabanlı) gridlerin (DataGrid/GridView) dinamik API-JS tablolarıyla yeniden tasarlanarak sisteme nefes aldırılması.
2. Diğer C# `aspx` sayfalarının klasör yapısına çekilerek sisteme eklenmesi (Örn: GorevAtama, PersonelGorev, UretimPlanlama vb.).
3. Otantikasyon (Auth) sistemi şifre şemalarının optimize edilmesi (MD5 v.s. modern algoritmalar).
4. Performans ve kapsamlı genel testler.
