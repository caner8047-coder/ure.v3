# Agent Sohbet Hafızası - 23 Mart 2026

## Proje ve Hedef
ZemMobilya web tabanlı V1 uygulamasının (ASP.NET Web Forms, C#, Entity Framework 6, MSSQL), en son güncel kopyasından alınarak modern bir **Laravel 11, PHP 8.5** ve **MySQL** altyapısına %100 "Pixel-Perfect" kopyalanması ve taşınması. 

## Konuşmalardaki Önemli Uyarılar (Kullanıcı Talepleri)
- "Proje çok kapsamlı, hiçbir şeyi unutmamamız, atlamamamız lazım, kusursuz bir şekilde olmalı."
- "Benden V1'i Laravel'e çevirmeni istedim, projenin son hali `zemuretim` klasörü ve güncel veritabanı `zemureti_dbZem_2026-03-22_23-01-19.zip`."
- "Projenin tamamını en ufak hata ve eksik olmadan geçiş yapmamız lazım. Hiçbir eksik istemiyorum detaylı ve profesyonelce yap."

## Uygulanan Çözüm Stratejisi
*   **Veritabanı Göçü:** MSSQL yedeğinin doğrudan MySQL'e yüklenememesi sebebiyle tüm MSSQL şemaları okundu, tablolar ve ilişkiler CSV formatında (`Siparisler`, `İsEmirleri`, `Kullanicilar` vb.) dışarı aktarılarak Laravel Controller'ları aracılığıyla MySQL'e kayıpsız aktarıldı.
*   **Arayüzlerin (Frontend) Göçü:** C# `.aspx` dosyalarının Blade tabanlı ve Bootstrap ile revize edilmiş hallerine çevrildi. CSS, class yapıları ve tüm estetik, orijinal yapıya %100 uygun kurgulandı.
*   **Backend Servislerin Ayrılması:** Devasa boyuttaki `SiparisApi.ashx` (38 farklı komut içeren handler), Laravel çatısı altında daha temiz bir "Service Repository Pattern" ile ayrıştırıldı:
    - `OrderSyncService`
    - `BomService` (Ürün Ağacı hesaplamaları)
    - `WorkOrderService` (İş emirleri oluşturma)
    - `OrderToWorkOrderService` (Siparişten iş emrine dönüştürme mantığı)
*   **Web Forms (asp:) Kontrolleri İçin Plan:** `<asp:GridView>`, `<asp:DropDownList>` gibi sunucu tabanlı kontroller, Blade ve Native JS tabanlı REST API isteklerine dönüştürüldü ve dönüştürülmeye (Faz 4 itibariyle) devam ediliyor.
