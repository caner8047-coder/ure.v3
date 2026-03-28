# Proje Tasarım ve UI/UX Mimarisi

ZemMobilya Laravel (V3) geçişindeki temel UI/UX tasarım hedefleri; karmaşık endüstriyel takip panellerini okunaklı, sade, hızlı ve tamamen **Mobil Uyumlu (Responsive)** kılmaktır. Sistem "Kusursuzluk" üzerine kurulmuştur.

## 1. Tasarım Sistemi ve Renk Uzayı (Color Palette)
- **Ana Zemin (Background):** `#f8f6f2` (Kirli Beyaz/Krem) – Göz yormaz.
- **Kart Zeminleri (Cards):** `#ffffff` – İçeriklerin öne çıkması için pürüzsüz zemin ve gölge (box-shadow) kullanımı.
- **Marka ve Vurgu (Brand / Primary):** Altın rengi tonları (`#d4af37`, `#c29b2d`) – Butonlar ve öne çıkan tablolar başlıkları.
- **Temel Metin (Text):** `#2b2115` ve `#4b3621` (Koyu Kahve/Siyah) – Keskin siyah yerine organik kontrast ile tasarlanmıştır.
- **İkincil Menüler ve Hover Efektleri:** `#f1e3c6` (Bej/Krem). Ortak uyum (bütünlük) bu renklerle sağlanır.

## 2. Tipografi ve İkonografi
- **Yazı Tipi (Font):** Inter (Google Fonts) – Okunabilirliği çok yüksek, modern Sans Serif yapısı. Okunabilirliği artırır ve kullanıcı dostu bir deneyim yaşatır.
- **İkonlar (Icons):** Bootstrap Icons (`<i class="bi bi-X">`) veya FontAwesome 5/6 (`<i class="fa fa-X">`) genel platformda standartlandırılır. Bütünlük için ikon kullanım kütüphanesi tektir.

## 3. Form ve Filtre Kontrolleri
- **Filtreler:** Tamamı modern mobil uyumlu Bootstrap `.form-select` ve `.form-control` sınıflarıyla dizayn edilir. `Select2` kütüphanesi kullanılarak çok seçenekli (dropdown) listelerde "Arama (Search)" özelliği aktif kılınıp deneyim iyileştirilir.
- **Input Alanları (Tarih, Miktar vb.):** Native HTML yapılarına dayanır, `border-radius: 8px` ile daha kavisli estetik yakalanır.

## 4. Tablo (DataGrid) Deneyimi
Projede (Siparişler, Stoklar, İş Emirleri ve Veritabanı yönetiminde) fazlasıyla data mevcut. Her tablonun yapısı ortaktır:
- **Yatay Kaydırma (Horizontal Scroll):** Masaüstünden Mobile geçildiğinde ekranı patlatmamak adına kapsayıcı bir `.grid-wrapper` (`overflow-x: auto`) yapısı kullanılır.
- **Renk Alternatifleri (Striped Rows):** Okumayı kolaylaştırmak için bir açık bir koyu zemin rengi olan `.table-striped` ve seçim odağı için `.table-hover` aktif durumdadır.
- **Aksiyonlar (Sil, Düzenle, Ekle vb.):** Ayrı bir sütun içerisinde ikonlara sahip ufak, kompakt boyutlardadır (`.btn-sm btn-icon`). Silme için kırmızı (danger), Düzenleme için sarı (warning), Ekleme için yeşil (success/primary) ve İptal için gri (secondary) uluslararası renklendirme standartları uygulanır.

## 5. Modal ve İletişim Kutuları (Alerts)
- Çağdışı `alert()` çağrıları atılmış, yerine şık ve platform bağımsız zengin **SweetAlert2** (`Swal.fire`) entegre edilmiştir. Silme onayları dahil her uyarı animasyonlu ve buton dizaynları zengindir.

## 6. Servis Yapısı & Asenkron Mimari
- Tüm modern sayfaların altında (AJAX veya Axios ile kurulan) mimari devreye girer. Sipariş oluşturma, stok güncelleme, ürün eşleştirme sayfalarında işlem yapılırken "Sayfayı Yenileme" (PostBack ekran beyazlama) sorunu komple bitirilmiş, modern SPAs (Single Page Application) hissi verilmiştir. Tüm alt tablolar API endpointlerinden verilerini anlık alır.
