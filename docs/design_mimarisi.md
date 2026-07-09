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

## 7. Operasyon Takip Panelleri Ana Referansı: Devam Eden Görevler
`resources/views/reports/ongoing.blade.php` dosyasındaki **Devam Eden Görevler** ekranı, ZemMobilya'nın canlı operasyon takip panelleri için ana tasarım referansıdır. Bu ekranın düzeni, üretim planlama, görev atama, stok/operasyon takip ve personel performans ekranlarında aynı mantıkla korunmalıdır. Bu standart eksiksiz uygulanmalı; yeni agentlar ve geliştiriciler bu sayfayı "canlı üretim paneli" tasarım şablonu kabul etmelidir.

### 7.1. Sayfanın Ana Amacı
- Kullanıcı tek ekranda **kim aktif, kim çalışıyor, kim boşta, hangi görev hangi aşamada** sorularının cevabını görmelidir.
- Ekran bir rapor sayfası gibi pasif değil, canlı operasyon panosu gibi davranmalıdır.
- Görsel yoğunluk kontrollü olmalı: bilgi bol, ama taraması kolay olmalıdır.
- İlk bakışta açık görev, üretimdeki görev, hazır adet, bekleyen adet ve aktif personel net görünmelidir.

### 7.2. Üst Özet Kartları
Üst bölümde kompakt, ikonlu ve okunaklı özet kartları bulunur. Kartlar şu sırayı korumalıdır:
- **Açık görev:** Sistemde açık takip edilen toplam görev.
- **Şu an üretimde:** Personelin kabul edip fiilen çalıştığı görev sayısı. Bu metrik mutlaka görünür kalmalıdır.
- **Hazır adet:** Üretime hazır toplam adet.
- **Bekleyen adet:** Bekleyen toplam adet.
- **Aktif personel:** Üretim akışında izlenen personel sayısı.

Kartlar gereksiz açıklama metniyle şişirilmemeli; ikon, büyük sayı ve kısa etiket düzeni korunmalıdır. Büyük sayıların hizası ve kart yüksekliği sabit kalmalı, veri değişince layout zıplamamalıdır.

### 7.3. Canlı "Çalışıyor / Üretimde" Durumu
Üretim planlama sayfasındaki sarı işaret mantığı burada da aynen geçerlidir:
- Personel görevi kabul etmiş ve çalışıyorsa durum **Çalışıyor** kabul edilir.
- Çalışan görev satırı **sarı/amber vurgu** ile ayrılmalıdır.
- Çalışan personel başlığı da hafif sarı zemin veya sol sarı çizgiyle işaretlenmelidir.
- Satır üzerinde `Çalışıyor` veya `x üretiyor` rozeti görünmelidir.
- Sarı renk yalnızca "personel şu anda bu görev üzerinde çalışıyor" anlamına gelmelidir; bekleyen, hazır veya normal aktif görevle karıştırılmamalıdır.

Bu vurgu kritik bir operasyon anlamı taşıdığı için kaldırılmamalı, başka bir renge rastgele çevrilmemeli ve sadece dekoratif amaçla kullanılmamalıdır.

### 7.4. Filtre ve Görünüm Mantığı
Filtre çubuğu şu yapıyı korumalıdır:
- Arama: personel, ürün, ara ürün veya sipariş numarası arar.
- Bölüm filtresi: tüm bölümler veya seçili bölüm.
- Segment kontrolü: **Aktif**, **Çalışıyor**, **Boşta**, **Tümü**.

Segment davranışı:
- **Aktif:** Görevi olan personeli ve görevlerini gösterir.
- **Çalışıyor:** Sadece üretimde olan görevleri ve bu görevlerde çalışan personeli gösterir.
- **Boşta:** Sadece görev bekleyen personeli gösterir.
- **Tümü:** Aktif akışı ve boşta personeli aynı ekranda gösterir.

Segmentler buton gibi değil, seçim durumu belli olan kompakt kontrol gibi tasarlanmalıdır. Aktif seçim teal/marka rengiyle vurgulanır.

### 7.5. Ana Yerleşim
Sayfa iki ana bölgeden oluşur:
- **Üretim Akışı:** Sol/ana paneldir. Personel başlığı altında görev satırları yer alır.
- **Boştaki Personel:** Sağ paneldir. Havuz veya yeni iş emri bekleyen personeli gösterir.

Masaüstünde iki panel yan yana kullanılabilir. Tek görünüm seçildiğinde ana panel tam genişliğe yayılabilir. Mobilde paneller alt alta düşmeli, hiçbir metin veya buton taşmamalıdır.

### 7.6. Personel ve Görev Satırı Standardı
Personel başlığı şu bilgileri taşımalıdır:
- Personel adı.
- Bölüm adı.
- Son tamamlanan kayıt bilgisi veya tamamlanan kayıt yok durumu.
- Görev, hazır ve bekleyen adet çipleri.
- Üretimde görev varsa sarı `x üretiyor` rozeti.

Görev satırı şu bilgileri taşımalıdır:
- Ürün/ara ürün adı.
- Bölüm veya parça adı.
- Tarih ve sipariş bağlantı bilgisi.
- Durum etiketi ve yüzdelik ilerleme.
- Hazır ve bekleyen adet kutuları.
- Üretimdeyse sarı `Çalışıyor` rozeti ve sarı satır vurgusu.

Görev satırları tablo gibi sıkışık görünmemeli; ama kart içinde gereksiz boşluk da bırakılmamalıdır. Operasyon kullanıcısı tek bakışta adetleri, durumu ve kimin çalıştığını okuyabilmelidir.

### 7.7. Veri Sözleşmesi
Bu ekranın canlı verisi `/api/database/personnel/production-overview` endpointinden gelir. Beklenen ana alanlar:
- `summary.active_task_count`
- `summary.ready_quantity`
- `summary.waiting_quantity`
- `summary.active_personnel`
- `active_personnel[].full_name`
- `active_personnel[].department_name`
- `active_personnel[].active_tasks`
- `active_personnel[].summary`
- `idle_personnel[]`
- `active_tasks[].is_in_production`
- `active_tasks[].ready_quantity`
- `active_tasks[].waiting_quantity`
- `active_tasks[].readiness_percent`

Özellikle `active_tasks` alanı kullanılmalıdır. Personeli sadece üst seviyedeki sayaçlarla göstermek yeterli değildir; görev satırları canlı akışın ana içeriğidir. `is_in_production === true` olan kayıtlar sarı "Çalışıyor" durumu üretir.

### 7.8. Yenileme ve Canlılık
- Sayfa otomatik olarak yaklaşık 30 saniyede bir yenilenmelidir.
- Manuel **Yenile** butonu korunmalıdır.
- Son güncelleme zamanı görünür olmalıdır.
- Veri alınamazsa sayfa sessizce boş kalmamalı; kullanıcıya okunaklı hata/boş durum mesajı gösterilmelidir.

### 7.9. Görsel Stil Kuralları
- Panel ve kartlarda 8px civarı radius yeterlidir; aşırı yuvarlak, oyuncak gibi görünümden kaçınılır.
- İç içe kart hissi minimumda tutulur. Bölümler net, sade ve taranabilir olmalıdır.
- Teal/marka rengi ana aksiyonlarda, amber/sarı sadece üretimde/çalışıyor anlamında, mavi hazır adetlerde, gri ikincil bilgilerde kullanılmalıdır.
- Boş personel panelinde kısa öneri/not alanı bulunabilir; metin kısa tutulmalıdır.
- Sayfa landing/hero gibi tasarlanmamalıdır. Bu ekran operasyon aracıdır; hızlı karar vermeye hizmet eder.

Bu bölümde tarif edilen düzen, ZemMobilya'nın modern operasyon paneli standardıdır ve ileride yapılacak tasarım değişikliklerinde referans alınmalıdır.
