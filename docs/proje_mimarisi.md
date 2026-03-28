# Proje Mimarisi (Geliştirici Rehberi)

Merhaba Junior/Mid Developer! 
ZemMobilya V3 Laravel (PHP 8.5) Uygulamasına Hoşgeldin.
Bu proje, eski yapısı olan "ASP.NET Web Forms" tabanlı bir üretim ve sipariş takip altyapısının modern bir "Laravel MVC" ve "Rest API (JS Tabanlı Web)" mimarisine çevrilmiş halidir. Aşağıda projenin tüm mantıksal ağını (network), dosyalara "noktasına virgülüne kadar" nereden nasıl ulaşacağını bulacaksın. 

## 1. Mimari Yaklaşım: Service Repository / MVC Katmanları
Laravel altyapısına ek olarak projede aşırı derecede "iş katmanı (business logic)" barınır. Kodları sadece Controller'ın (Kontrolcülerin) içine atıp "Spaghetti" yaratmaktan kaçındık.

* **Modeller (Models) - `app/Models/`:** Veritabanı ilişkileri (One-to-Many vb.) için Laravel Eloquent ORM kullanırız. Eski sitemdeki direkt SqlCommand'lar bitti, tüm işlemler `Model::create()` objeleri üzerinden gerçekleşir.
* **Kontrolcüler (Controllers) - `app/Http/Controllers/`:** 
  - `AdminController` türündeki sınıfların tek amacı `resources/views/` (Blade HTML) şablonunu son kullanıcıya dönmektir (return view). Sayfa geçişlerini sağlar. 
  - `*ApiController` (`SiparisApiController` gibi) türündeki sınıflar, sayfanın asenkron JS(AJAX/Fetch/Axios) üzerinden çağırdığı veri alışverişini yapar ve sadece **JSON** döner, veritabanına veri yazar ve alır.
* **Servisler (Services) - `app/Services/`:** Tüm algoritma beyninin çalıştığı yer burasıdır. Bir sipariş geldiğinde üretim/ürün ağacını (BOM) çıkarma, kesim/bantlama iş emirlerini oluşturma ve stok düşme gibi çok ağır algoritmalar `SiparisApiController` yerine `OrderSyncService`, `BomService` gibi Sınıflarda metodlaştırılarak kapsüllenir. İlerde bir modülü değiştireceksen önce buradaki servis metodunun ne iş yaptığına bak!

## 2. Frontend (Ön Uç) İstemci Mimarisi (`resources/views/`)
- Web Forms'daki `<asp:Button>`, `PostBack` kavramları ve her tuşta sayfanın beyazlayıp yenilenmesi rafa kalkmıştır.
- Tüm `Blade` mimarisi `@extends('layouts.app')` ile `resources/views/layouts/app.blade.php` isimli bir ana tasarım kalıbını (MasterPage) takip eder. Menü ve header buradadır. Sayfalara CSS ve JS dosyaları buradan enjekte edilir.
- Tabloları doldurmak için genellikle saf (Native) HTML Table etiketlerine JavaScript üzerinden `forEach` veya `map` fonksiyonu ile `<tr>` basarak doldururuz (Vanilla JS ya da bazen özelleştirilmiş Handsontable paketleriyle). Tabloda aksiyon alındığında (örn Sil butonu), sayfa yenilenmeden ilgili satır koda müdahale edilerek yok edilir (Real-Time hissi).

## 3. Sipariş Sistemi ve BOM Algoritması (Sistemin Kalbi)
* Siparişler dış e-ticaret sitelerinden (`TblSiparisGelen` benzeri mantık) düşünce veya eklense; mutfak dolabının modüler parçaları aranır. Her parça için tanımlanan Cnc, Suntalam, Bantlama, Delik departmanlarındaki istasyonlara "İş Emri" yazılır. (Eski adıyla `SiparisApi.ashx`, YENİ Adıyla -> `app/Services/OrderSyncService.php`).
* Bu yüzden API'daki `UploadOrders` ve servisteki `OrderToWorkOrderService` rotaları sistemin bel kemiğidir. Asla, test yapmadan logiclerine müdahale etmemelisin!

## 4. Güvenlik ve Rotalama (`routes/web.php`)
- Tüm Web bağlantıları ve ekran URL'leri `web.php` üzerinde kategorize edilmiş (Siparişler, Stoklar, İstatistikler) durumdadır. Yetkisiz giriş olmaması için Middleware rotaları projeye daha sonrasında eklenecektir.
- Eloquent ORM tüm DB işlemlerinde koruyucu olduğu için SQL Injection sorunu giderilmiştir. Ayrıca form veya dosya aktarımlarında CSRF Token her zaman form etiketlerinde `@csrf` vasıtasıyla iletilir, bunu Ajax POST/PUT çağrılarında yollamayı unutma.

*Mimarinin en güçlü yanı kurallı, servis bağlantılı ve sayfa yenilemeyen tam API temelli esnekliğidir!*
