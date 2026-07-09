# Canlı Üretim ve İş Emri Akış Raporu

Oluşturma zamanı: 2026-04-25 23:49:00 (container içi saat).
Veri kaynağı: Docker `zemuretim-v3-mysql-1` / MySQL `laravel` canlı veritabanı.

## Kapsam ve Okuma Notu
Bu rapor üretimde sayılan üç durumu birlikte inceler: `IsEmriVerildi`, `PasifDevamEden`, `UretimdenKarsilaniyor`. `UretimBekliyor` henüz üretim hattına girmemiş, `StokKarsilandi` stoktan kapanmış, `Pasif` kapalı kabul edilmiştir.

Sistemde iki farklı adet dili var: sipariş/nihai ürün adedi ve operasyon/bileşen adedi. Örneğin 1 berjer siparişi, kesimhane/terzihane/marangozhane/boyahane/paketleme gibi birçok ara iş kalemine bölündüğü için operasyon adedi nihai ürün adedinden çok daha büyüktür.

## Canlı Tablo Durumu
| Tablo | Satır |
|---|---|
| tbSiparisSatir | 1.287 |
| tbBolumHavuz | 45 |
| tbPersonelGorev | 305 |
| tbGorevler | 846 |
| tbIsEmriGecmisi | 960 |
| work_order_events | 2.698 |
| work_order_snapshots | 2.132 |


## Sipariş Durum Özeti
| Durum | Satır | Adet |
|---|---|---|
| UretimBekliyor | 167 | 294 |
| IsEmriVerildi | 47 | 225 |
| UretimdenKarsilaniyor | 44 | 49 |
| PasifDevamEden | 153 | 185 |
| StokKarsilandi | 3 | 4 |
| Pasif | 873 | 995 |


## Üretimdeki Toplam
Canlı DB’ye göre üretim kapsamında **244 sipariş satırı / 459 nihai adet** var. Bunun 216 adedi özel üretim/stok ilavesi satırı, 49 adedi GİED bağlantılı müşteri siparişi. İş emri tarih aralığı: 2026-03-29 00:27:04 - 2026-04-25 02:07:10.

| Açık durum | Satır | Adet | İlk iş emri | Son iş emri |
|---|---|---|---|---|
| IsEmriVerildi | 47 | 225 | 2026-03-31 00:08:42 | 2026-04-25 02:07:10 |
| PasifDevamEden | 153 | 185 | 2026-03-29 00:27:04 | 2026-04-17 00:33:59 |
| UretimdenKarsilaniyor | 44 | 49 | - | - |


## Üretimde Olan Tüm Nihai Ürünler
Aşağıdaki tablo ürün bazında açık üretim toplamını ve hangi statüden geldiğini gösterir. `GİED` müşteri siparişinin başka bir özel üretime bağlandığı adettir; `Özel` ise stok ilavesi/özel üretim satırıdır.
| Ürün | Tür/No | Toplam adet | Satır | İş emri | Pasif devam | GİED | Özel | Son iş emri | Kök ara ürün | Operasyonel ilk alt birimler |
|---|---|---|---|---|---|---|---|---|---|---|
| Pavia Berjer Natürel Ahşap, Kırık Beyaz | Nihai/3900 | 43 | 24 | 15 | 22 | 6 | 15 | 2026-04-03 21:37:58 | berjer zem pavia wolf 01 beyaz | Kesimhane, YM Depo, Marangozhane |
| Marin Puf, Beyaz Welsoft Kumaş | Nihai/3978 | 43 | 7 | 40 | 0 | 3 | 37 | 2026-04-15 21:14:24 | bench zem marin welsoft beyaz | Marangozhane, YM Depo, Kesimhane |
| Pavia Berjer Natürel Ahşap, Sütlü Kahve | Nihai/3914 | 35 | 9 | 24 | 9 | 2 | 24 | 2026-04-06 07:03:34 | berjer zem pavia wolf 05 sütlü kahve | YM Depo, Marangozhane, Kesimhane |
| Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | Nihai/3955 | 22 | 9 | 18 | 4 | 0 | 16 | 2026-04-12 21:47:28 | köşe takımı zem legna zeugma keten krem sağ | Kesimhane, Marangozhane, YM Depo |
| Rock Bohem Sallanır Berjer, Sütlü Kahve | Nihai/3928 | 20 | 10 | 10 | 10 | 0 | 10 | 2026-04-06 22:37:14 | berjer zem rock sallanır wolf 05 sütlü kahve | Kesimhane, YM Depo, Marangozhane |
| Favela Bohem Ahşap Ayaklı Gri Teddy Kumaş Berjer | Nihai/3942 | 20 | 7 | 9 | 11 | 0 | 9 | 2026-04-01 23:27:16 | berjer zem favela(DEMONTE) wolf 19 gri | Kesimhane, YM Depo, Marangozhane |
| Favela Bohem Ahşap Ayaklı Beyaz Teddy Kumaş Berjer V01 | Nihai/3941 | 16 | 10 | 0 | 16 | 0 | 0 | 2026-04-01 23:37:54 | berjer zem favela(DEMONTE) wolf 01 beyaz | Kesimhane, YM Depo, Marangozhane |
| Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | Nihai/3946 | 15 | 15 | 1 | 8 | 6 | 0 | 2026-04-17 00:33:59 | berjer zem favela 3lü wolf 05 sütlü kahve | Kesimhane, Marangozhane, YM Depo |
| Rock Bohem Sallanır Berjer, Kırık Beyaz | Nihai/3927 | 15 | 13 | 2 | 13 | 0 | 2 | 2026-04-06 22:39:52 | berjer zem rock sallanır wolf 01 beyaz | Kesimhane, YM Depo, Marangozhane |
| Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | Nihai/3921 | 14 | 8 | 6 | 5 | 3 | 6 | 2026-04-02 00:09:17 | berjer zem alaves 2li wolf 05 sütlü kahve(kahve pinotex) | Kesimhane, Marangozhane, YM Depo |
| Favela Bohem İkili Berjer Natürel Ahşap, Gri | Nihai/1849 | 13 | 7 | 7 | 6 | 0 | 7 | 2026-04-01 23:31:25 | berjer zem favela 2li wolf 19 gri | Kesimhane, Marangozhane, YM Depo |
| Scala Sandıklı Bohem Ahşap Ayaklı Puf/Bench, Sütlü Kahve | Nihai/4036 | 13 | 6 | 8 | 2 | 3 | 8 | 2026-04-03 21:46:56 | bench zem scala sandıklı wolf 05 sütlü kahve | Kesimhane, YM Depo, Marangozhane |
| Pavia Berjer Natürel Ahşap, Gri | Nihai/3903 | 13 | 4 | 8 | 5 | 0 | 8 | 2026-04-03 21:35:01 | berjer zem pavia wolf 19 gri | YM Depo, Marangozhane, Kesimhane |
| Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve | Nihai/2900 | 12 | 9 | 0 | 12 | 0 | 0 | 2026-04-01 23:14:43 | berjer zem favela 2li wolf 05 sütlü kahve | Kesimhane, Marangozhane, YM Depo |
| bench zem louca zeugma v106 keten gri gümüş ayak | Nihai/5082 | 11 | 3 | 9 | 1 | 1 | 9 | 2026-04-01 22:11:06 | bench zem louca zeugma v106 keten gri gümüş ayak | YM Depo, Marangozhane, Kesimhane, Demirhane |
| Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve | Nihai/3920 | 10 | 7 | 0 | 10 | 0 | 0 | 2026-04-02 00:06:14 | berjer zem alaves wolf 05 sütlü kahve | Kesimhane, Marangozhane, YM Depo |
| Favela Bohem Berjer Natürel Ahşap, Hardal | Nihai/3948 | 10 | 6 | 4 | 6 | 0 | 4 | 2026-04-01 22:37:28 | berjer zem favela(DEMONTE) wolf hardal | Kesimhane, YM Depo, Marangozhane |
| Doris Bohem Berjer, Kırık Beyaz | Nihai/3915 | 10 | 5 | 4 | 2 | 4 | 4 | 2026-04-02 00:26:49 | berjer zem doris wolf 01 beyaz | YM Depo, Marangozhane, Kesimhane |
| bench zem marin sandıklı punch altıgen welsoft | Nihai/652 | 8 | 3 | 8 | 0 | 0 | 7 | 2026-04-20 00:48:35 | bench zem marin sandıklı punch altıgen welsoft | Kesimhane, YM Depo, Marangozhane |
| Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | Nihai/2901 | 7 | 7 | 0 | 4 | 3 | 0 | 2026-03-30 01:02:53 | berjer zem favela 2li wolf 01 beyaz | Kesimhane, Marangozhane, YM Depo |
| Rock Bohem Sallanır Berjer, Tarçın/Hardal | Nihai/3930 | 7 | 5 | 4 | 3 | 0 | 3 | 2026-04-25 02:07:10 | berjer zem rock sallanır hardal | Marangozhane, Kesimhane, YM Depo |
| Favela Bohem Sehpa, Natürel Ahşap | Nihai/3950 | 7 | 5 | 3 | 2 | 2 | 3 | 2026-04-01 23:17:51 | sehpa zem favela | Marangozhane |
| Favela Bohem Üçlü Berjer Natürel Ahşap, Gri | Nihai/3947 | 7 | 4 | 4 | 3 | 0 | 4 | 2026-04-01 23:00:57 | berjer zem favela 3lü wolf 19 gri | Kesimhane, Marangozhane, YM Depo |
| Leones Bohem Ahşap Ayaklı Kanepe, Kırık Beyaz | Nihai/1869 | 7 | 3 | 6 | 1 | 0 | 6 | 2026-04-06 06:38:42 | kanepe zem leones wolf 01 beyaz | Kesimhane, Marangozhane, YM Depo |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Kırık Beyaz | Nihai/4064 | 6 | 3 | 4 | 1 | 1 | 4 | 2026-04-06 06:31:44 | akıllı sehpa zem irina wolf 01 beyaz | Kesimhane, Marangozhane, YM Depo |
| bench zem bueno hanedan gri | Nihai/4077 | 6 | 3 | 4 | 2 | 0 | 4 | 2026-04-06 22:53:00 | bench zem bueno hanedan gri | Kesimhane, YM Depo, Marangozhane |
| Rock Bohem Sallanır Berjer, Gri | Nihai/3929 | 5 | 3 | 3 | 2 | 0 | 3 | 2026-04-06 22:38:37 | berjer zem rock sallanır wolf 19 gri | Kesimhane, YM Depo, Marangozhane |
| Alaves Hazeran Jüt Sehpa Ceviz Ahşap | Nihai/3922 | 5 | 2 | 4 | 1 | 0 | 4 | 2026-04-02 00:15:29 | sehpa zem alaves(kahve pinotex) | Marangozhane |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | Nihai/4065 | 5 | 2 | 4 | 1 | 0 | 4 | 2026-04-06 23:16:21 | akıllı sehpa zem irina wolf 05 sütlü kahve | Kesimhane, Marangozhane, YM Depo |
| Alto Bohem Sallanır Berjer, Açık Krem | Nihai/3916 | 5 | 2 | 4 | 1 | 0 | 4 | 2026-04-02 00:23:15 | berjer zem alto zeugma keten krem | Kesimhane, Marangozhane |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Taba | Nihai/4067 | 4 | 4 | 1 | 2 | 1 | 1 | 2026-04-01 21:57:21 | akıllı sehpa zem irina taba deri | Kesimhane, Marangozhane, YM Depo |
| Marcel Bohem Berjer, Kırık Punch / Taba Deri | Nihai/3925 | 4 | 3 | 0 | 0 | 4 | 0 | - | berjer zem marcel teddy kahve(summer).punch kahve(pasto) | Marangozhane, Kesimhane |
| Legna Bohem Köşe Takımı, Açık Krem SOL KÖŞE | Nihai/3956 | 4 | 3 | 2 | 2 | 0 | 2 | 2026-04-02 22:15:38 | köşe takımı zem legna zeugma keten krem sol | Kesimhane, YM Depo, Marangozhane |
| Pavia Berjer Ceviz Ahşap, Sütlü Kahve | Nihai/4069 | 4 | 3 | 0 | 4 | 0 | 0 | 2026-04-06 22:17:07 | berjer zem pavia (ceviz) wolf 05 sütlü kahve | YM Depo, Marangozhane, Kesimhane |
| Tosca Bohem Berjer Natürel Ahşap, Sütlü Kahve | Nihai/3933 | 3 | 3 | 0 | 3 | 0 | 0 | 2026-04-06 22:00:10 | berjer zem tosca wolf 05 sütlü kahve | Marangozhane, Kesimhane, YM Depo |
| Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Açık Krem | Nihai/3898 | 3 | 2 | 3 | 0 | 0 | 2 | 2026-04-09 06:46:47 | berjer zem meyra sallanır interno krem | Kesimhane, Marangozhane |
| Favela Bohem İkili Berjer Natürel Ahşap, Hardal | Nihai/2902 | 3 | 2 | 2 | 1 | 0 | 2 | 2026-04-01 23:34:02 | berjer zem favela 2li wolf hardal | Kesimhane, Marangozhane, YM Depo |
| bench zem louca zeugma v143 keten krem altın ayak | Nihai/5084 | 2 | 2 | 0 | 0 | 2 | 0 | - | bench zem louca zeugma v143 keten krem altın ayak | YM Depo, Marangozhane, Kesimhane, Demirhane |
| puf zem gigi peluş beyaz altın ayak | Nihai/5077 | 2 | 2 | 0 | 2 | 0 | 0 | 2026-04-09 06:26:47 | puf zem gigi peluş beyaz altın ayak | Kesimhane, Marangozhane, YM Depo, Demirhane |
| berjer zem favela 3lü wolf hardal | Nihai/5098 | 2 | 2 | 0 | 2 | 0 | 0 | 2026-04-01 22:55:21 | berjer zem favela 3lü wolf hardal | Kesimhane, Marangozhane, YM Depo |
| Contes Bohem Sandıklı Orta Sehpa Puf/Bench , Gri | Nihai/3958 | 2 | 2 | 0 | 0 | 2 | 0 | - | bench zem contes wolf 19 gri | Kesimhane, YM Depo, Marangozhane |
| Tosca Bohem Berjer Natürel Ahşap, Gri | Nihai/3932 | 2 | 2 | 0 | 2 | 0 | 0 | 2026-04-06 21:57:41 | berjer zem tosca wolf 19 gri | Marangozhane, Kesimhane, YM Depo |
| Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Gri | Nihai/3897 | 2 | 2 | 1 | 1 | 0 | 1 | 2026-04-09 06:46:56 | berjer zem meyra sallanır interno gri | Kesimhane, Marangozhane |
| MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | Ara/6372 | 2 | 1 | 2 | 0 | 0 | 2 | 2026-04-20 10:54:44 | MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | Marangozhane |
| Jarvis Bohem Sandıklı Orta Sehpa Puf, Kırık Beyaz | Nihai/4033 | 1 | 1 | 0 | 0 | 1 | 0 | - | puf zem jarvis wolf 01 beyaz | Kesimhane, YM Depo, Marangozhane |
| Royal Bohem Berjer, Açık Krem | Nihai/4059 | 1 | 1 | 0 | 1 | 0 | 0 | 2026-04-13 22:35:31 | berjer zem royal interno keten krem | Kesimhane, Marangozhane |
| Chester Puf, Gri Gold | Nihai/3993 | 1 | 1 | 0 | 1 | 0 | 0 | 2026-04-09 06:34:44 | puf zem chester babyface bf610 gri altın elkamet | Kesimhane, Marangozhane, YM Depo |
| Contes Bohem Sandıklı Orta Sehpa Puf/Bench , Sütlü Kahve | Nihai/3959 | 1 | 1 | 0 | 0 | 1 | 0 | - | bench zem contes wolf 05 sütlü kahve | Kesimhane, YM Depo, Marangozhane |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Gri | Nihai/4066 | 1 | 1 | 0 | 0 | 1 | 0 | - | akıllı sehpa zem irina wolf 19 gri | Kesimhane, Marangozhane, YM Depo |
| Contes Bohem Sandıklı Orta Sehpa Puf/bench, Kırık Beyaz | Nihai/3957 | 1 | 1 | 0 | 0 | 1 | 0 | - | bench zem contes wolf 01 beyaz | Kesimhane, YM Depo, Marangozhane |
| Orso Bohem Sandıklı Puf, Kırık Beyaz | Nihai/4073 | 1 | 1 | 0 | 0 | 1 | 0 | - | puf zem orso wolf 01 beyaz | YM Depo, Marangozhane, Kesimhane |
| Tosca Bohem Berjer Ceviz Ahşap , Gri | Nihai/3940 | 1 | 1 | 0 | 1 | 0 | 0 | 2026-04-06 22:05:23 | berjer zem tosca wolf 19 gri(kahve pinotex) | Kesimhane, YM Depo, Marangozhane |
| bench zem şila peluş beyaz altın ayak | Nihai/5079 | 1 | 1 | 0 | 0 | 1 | 0 | - | bench zem şila peluş beyaz altın ayak | YM Depo, Marangozhane, Demirhane |
| MAR/rock.kalina berjer gürgen sallanma ayağı | Ara/1091 | 1 | 1 | 1 | 0 | 0 | 1 | 2026-04-20 15:34:46 | MAR/rock.kalina berjer gürgen sallanma ayağı | Marangozhane |


## Üretim Yolu Mantığı
İş emri verildiğinde sistem önce sipariş satırını `IsEmriVerildi` yapar ve `GorevNo`/`IsEmriTarihi` yazar. Ardından BOM ağacını `tbAraUrun.Yol` alanından açar. Kod tarafındaki ana akış `OrderToWorkOrderService::createOrderWorkOrders`, `WorkOrderService`, `BomService::minAraUrunUretimiDenetle` ve `BomService::isEmriVerRecursive` üzerinde çalışır.

Önemli nüans: sistem veritabanına kök ürünü önce yazar, sonra alt bileşenleri recursive açar. Bu yüzden havuzda ilk satır bazen `Ürün Depo` veya `Paketleme` görünür ama `Adet=0` olabilir. Operasyonel olarak ilk yapılabilir iş, `Adet > 0` olan en alt/uygun bileşen satırıdır. `ToplamAdet` hedef ihtiyacı, `Adet` ise o anda atanabilir/üretilebilir miktarı gösterir.

## Ürün Bazında Üretim Rotası Özeti
| Ürün | Açık adet | Kök ara ürün | Departman katmanları | BOM düğüm sayısı |
|---|---|---|---|---|
| Pavia Berjer Natürel Ahşap, Kırık Beyaz | 43 | berjer zem pavia wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 14 |
| Marin Puf, Beyaz Welsoft Kumaş | 43 | bench zem marin welsoft beyaz | L0: Ürün Depo -> L1: Paketleme, YM Depo -> L2: Döşemehane -> L3: Marangozhane, Terzihane -> L4: Marangozhane, YM Depo, Kesimhane | 11 |
| Pavia Berjer Natürel Ahşap, Sütlü Kahve | 35 | berjer zem pavia wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Marangozhane, Terzihane -> L4: YM Depo, Marangozhane, Kesimhane | 14 |
| Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 22 | köşe takımı zem legna zeugma keten krem sağ | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, YM Depo -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 15 |
| Rock Bohem Sallanır Berjer, Sütlü Kahve | 20 | berjer zem rock sallanır wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 18 |
| Favela Bohem Ahşap Ayaklı Gri Teddy Kumaş Berjer | 20 | berjer zem favela(DEMONTE) wolf 19 gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 16 |
| Favela Bohem Ahşap Ayaklı Beyaz Teddy Kumaş Berjer V01 | 16 | berjer zem favela(DEMONTE) wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 16 |
| Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 15 | berjer zem favela 3lü wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 17 |
| Rock Bohem Sallanır Berjer, Kırık Beyaz | 15 | berjer zem rock sallanır wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 18 |
| Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | 14 | berjer zem alaves 2li wolf 05 sütlü kahve(kahve pinotex) | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 16 |
| Favela Bohem İkili Berjer Natürel Ahşap, Gri | 13 | berjer zem favela 2li wolf 19 gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 17 |
| Scala Sandıklı Bohem Ahşap Ayaklı Puf/Bench, Sütlü Kahve | 13 | bench zem scala sandıklı wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme, Boyahane -> L2: Döşemehane, YM Depo -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 14 |
| Pavia Berjer Natürel Ahşap, Gri | 13 | berjer zem pavia wolf 19 gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Marangozhane, Terzihane -> L4: YM Depo, Marangozhane, Kesimhane | 14 |
| Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve | 12 | berjer zem favela 2li wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 17 |
| bench zem louca zeugma v106 keten gri gümüş ayak | 11 | bench zem louca zeugma v106 keten gri gümüş ayak | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Marangozhane, Terzihane, Demirhane -> L4: YM Depo, Marangozhane, Kesimhane, Demirhane | 12 |
| Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve | 10 | berjer zem alaves wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 15 |
| Favela Bohem Berjer Natürel Ahşap, Hardal | 10 | berjer zem favela(DEMONTE) wolf hardal | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 16 |
| Doris Bohem Berjer, Kırık Beyaz | 10 | berjer zem doris wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane, YM Depo -> L3: Marangozhane, Terzihane -> L4: YM Depo, Marangozhane, Kesimhane | 12 |
| bench zem marin sandıklı punch altıgen welsoft | 8 | bench zem marin sandıklı punch altıgen welsoft | L0: YM Depo -> L1: Paketleme, YM Depo -> L2: Döşemehane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 13 |
| Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | 7 | berjer zem favela 2li wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 17 |
| Rock Bohem Sallanır Berjer, Tarçın/Hardal | 7 | berjer zem rock sallanır hardal | L0: Ürün Depo -> L1: Paketleme -> L2: Boyahane, Döşemehane -> L3: Marangozhane, Terzihane -> L4: Marangozhane, Kesimhane, YM Depo | 18 |
| Favela Bohem Sehpa, Natürel Ahşap | 7 | sehpa zem favela | L0: Ürün Depo -> L1: Paketleme -> L2: Boyahane -> L3: Marangozhane -> L4: Marangozhane | 5 |
| Favela Bohem Üçlü Berjer Natürel Ahşap, Gri | 7 | berjer zem favela 3lü wolf 19 gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane, YM Depo | 17 |
| Leones Bohem Ahşap Ayaklı Kanepe, Kırık Beyaz | 7 | kanepe zem leones wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane, YM Depo -> L4: Kesimhane, Marangozhane, YM Depo | 16 |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Kırık Beyaz | 6 | akıllı sehpa zem irina wolf 01 beyaz | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane, YM Depo -> L4: Kesimhane, Marangozhane, YM Depo | 15 |
| bench zem bueno hanedan gri | 6 | bench zem bueno hanedan gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, YM Depo -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 11 |
| Rock Bohem Sallanır Berjer, Gri | 5 | berjer zem rock sallanır wolf 19 gri | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane -> L4: Kesimhane, YM Depo, Marangozhane | 18 |
| Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 5 | sehpa zem alaves(kahve pinotex) | L0: Ürün Depo -> L1: Paketleme -> L2: Boyahane -> L3: Marangozhane -> L4: Marangozhane | 5 |
| İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | 5 | akıllı sehpa zem irina wolf 05 sütlü kahve | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: Terzihane, Marangozhane, YM Depo -> L4: Kesimhane, Marangozhane, YM Depo | 15 |
| Alto Bohem Sallanır Berjer, Açık Krem | 5 | berjer zem alto zeugma keten krem | L0: Ürün Depo -> L1: Paketleme -> L2: Döşemehane, Boyahane -> L3: YM Depo, Terzihane, Marangozhane -> L4: Kesimhane, Marangozhane | 13 |


## Bölüm Bazında Açık İş Yükü
| Bölüm | Havuz satırı | Havuz hedef | Havuz atanabilir | Personel aktif satır | Personel bekleyen | Toplam operasyon yükü |
|---|---|---|---|---|---|---|
| Marangozhane | 18 | 80 | 68 | 100 | 3.001 | 3.081 |
| YM Depo | 6 | 55 | 52 | 26 | 1.360 | 1.415 |
| Boyahane | 6 | 30 | 20 | 41 | 1.021 | 1.051 |
| Paketleme | 3 | 9 | 0 | 53 | 411 | 420 |
| Ürün Depo | 2 | 6 | 0 | 48 | 373 | 379 |
| Döşemehane | 2 | 4 | 0 | 25 | 226 | 230 |
| Terzihane | 4 | 14 | 0 | 7 | 139 | 153 |
| Kesimhane | 4 | 14 | 14 | 4 | 49 | 63 |
| Demirhane | 0 | 0 | 0 | 1 | 8 | 8 |


## Havuzda Bekleyen İş Emirleri
Havuz, personele geçmemiş bölüm işlerini tutar. `Hedef` toplam ihtiyaçtır, `Atanabilir` stok/darboğaz hesabına göre hemen personele verilebilecek adettir.
| Havuz No | Tarih | Bölüm | Ara ürün | Ana ürün | Hedef | Atanabilir | Sipariş satırı | Sipariş no |
|---|---|---|---|---|---|---|---|---|
| 29620 | 17.04.2026 00:33 | Ürün Depo | berjer zem favela 3lü wolf 05 sütlü kahve | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 0 | - | - |
| 29621 | 17.04.2026 00:33 | Paketleme | PAK/berjer zem favela 3lü wolf 05 sütlü kahve | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 0 | - | - |
| 29623 | 17.04.2026 00:33 | Marangozhane | MAR/favela 3lü berjer kasa(SIRT) | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 0 | - | - |
| 29624 | 17.04.2026 00:33 | Marangozhane | MAR/favela 3lü berjer kasa (SIRT) kavak kesim | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 5 | - | - |
| 29625 | 17.04.2026 00:33 | YM Depo | YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 10 | 10 | - | - |
| 29626 | 17.04.2026 00:33 | Marangozhane | MAR/favela 3lü berjer kasa(OTURUM) | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 0 | - | - |
| 29627 | 17.04.2026 00:33 | Marangozhane | MAR/favela 3lü berjer kasa (OTURUM) kavak kesim | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 5 | - | - |
| 29628 | 17.04.2026 00:33 | Boyahane | BOY/kol favela(DEMONTE) berjer | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 10 | 10 | - | - |
| 29629 | 17.04.2026 00:33 | Boyahane | BOY/favela.pavia.alaves destek ayak | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 10 | 0 | - | - |
| 29630 | 17.04.2026 00:33 | Marangozhane | MAR/favela.pavia.alaves destek ayak | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 10 | 10 | - | - |
| 29631 | 17.04.2026 00:33 | Boyahane | BOY/favela 3lü berjer kol bağlantı GÜRGEN | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 5 | 5 | - | - |
| 29635 | 17.04.2026 00:43 | Terzihane | TER/berjer zem favela.pavia wolf hardal | Ara Mamül | 5 | 0 | - | - |
| 29636 | 17.04.2026 00:43 | Kesimhane | KES/berjer zem favela.pavia wolf hardal | Ara Mamül | 5 | 5 | - | - |
| 29637 | 17.04.2026 00:43 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) | Ara Mamül | 5 | 5 | - | - |
| 29638 | 17.04.2026 00:43 | YM Depo | YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | Ara Mamül | 10 | 10 | - | - |
| 29639 | 17.04.2026 00:43 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim | Ara Mamül | 5 | 5 | - | - |
| 29641 | 17.04.2026 00:44 | Terzihane | TER/berjer zem favela.pavia wolf hardal | Ara Mamül | 5 | 0 | - | - |
| 29642 | 17.04.2026 00:44 | Kesimhane | KES/berjer zem favela.pavia wolf hardal | Ara Mamül | 5 | 5 | - | - |
| 29643 | 17.04.2026 00:44 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) | Ara Mamül | 5 | 5 | - | - |
| 29644 | 17.04.2026 00:44 | YM Depo | YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | Ara Mamül | 10 | 10 | - | - |
| 29645 | 17.04.2026 00:44 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim | Ara Mamül | 5 | 5 | - | - |
| 29678 | 18.04.2026 00:02 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) | Ara Mamül | 10 | 10 | - | - |
| 29679 | 18.04.2026 00:02 | YM Depo | YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | Ara Mamül | 20 | 20 | - | - |
| 29680 | 18.04.2026 00:02 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim | Ara Mamül | 10 | 10 | - | - |
| 29692 | 20/04/2026 00:48 | YM Depo | bench zem marin sandıklı punch altıgen welsoft | bench zem marin sandıklı punch altıgen welsoft | 3 | 0 | 5977 | STOK-20260420-2934 |
| 29693 | 20/04/2026 00:48 | Paketleme | PAK/bench zem marin sandıklı punch altıgen welsoft | bench zem marin sandıklı punch altıgen welsoft | 3 | 0 | 5977 | STOK-20260420-2934 |
| 29694 | 20/04/2026 00:48 | Döşemehane | DÖŞ/bench zem marin sandıklı punch altıgen welsoft | bench zem marin sandıklı punch altıgen welsoft | 3 | 0 | 5977 | STOK-20260420-2934 |
| 29695 | 20/04/2026 00:48 | Terzihane | TER/bench zem marin sandıklı punch altıgen welsoft | bench zem marin sandıklı punch altıgen welsoft | 3 | 0 | 5977 | STOK-20260420-2934 |
| 29696 | 20/04/2026 00:48 | Kesimhane | KES/bench zem marin sandıklı punch altıgen welsoft | bench zem marin sandıklı punch altıgen welsoft | 3 | 3 | 5977 | STOK-20260420-2934 |
| 29697 | 20/04/2026 10:54 | Marangozhane | MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | Ara Mamül | 2 | 0 | 5978 | STOK-20260420-3341 |
| 29698 | 20/04/2026 10:54 | Marangozhane | MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) kavak kesim | Ara Mamül | 2 | 2 | 5978 | STOK-20260420-3341 |
| 29700 | 20/04/2026 15:34 | Marangozhane | MAR/rock.kalina berjer gürgen sallanma ayağı | Ara Mamül | 1 | 1 | 5980 | STOK-20260420-8041 |
| 29701 | 25/04/2026 02:07 | Ürün Depo | berjer zem rock sallanır hardal | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 0 | 5955 | 1091784954-A |
| 29702 | 25/04/2026 02:07 | Paketleme | PAK/berjer zem rock sallanır hardal | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 0 | 5955 | 1091784954-A |
| 29703 | 25/04/2026 02:07 | Boyahane | BOY/kol favela(DEMONTE) berjer | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 2 | 2 | 5955 | 1091784954-A |
| 29704 | 25/04/2026 02:07 | Boyahane | BOY/rock.kalina berjer gürgen sallanma ayağı | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 2 | 2 | 5955 | 1091784954-A |
| 29705 | 25/04/2026 02:07 | Marangozhane | MAR/rock.kalina berjer gürgen sallanma ayağı | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 2 | 2 | 5955 | 1091784954-A |
| 29706 | 25/04/2026 02:07 | Döşemehane | DÖŞ/berjer zem favela(DEMONTE) wolf hardal | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 0 | 5955 | 1091784954-A |
| 29707 | 25/04/2026 02:07 | Terzihane | TER/berjer zem favela.pavia wolf hardal | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 0 | 5955 | 1091784954-A |
| 29708 | 25/04/2026 02:07 | Kesimhane | KES/berjer zem favela.pavia wolf hardal | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 1 | 5955 | 1091784954-A |
| 29709 | 25/04/2026 02:07 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 1 | 5955 | 1091784954-A |
| 29710 | 25/04/2026 02:07 | YM Depo | YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 2 | 2 | 5955 | 1091784954-A |
| 29711 | 25/04/2026 02:07 | Marangozhane | MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 1 | 5955 | 1091784954-A |
| 29712 | 25/04/2026 02:07 | Boyahane | BOY/favela berjer kol bağlantı GÜRGEN | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 1 | 5955 | 1091784954-A |
| 29713 | 25/04/2026 02:07 | Marangozhane | MAR/favela berjer kol bağlantı GÜRGEN | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | 1 | 5955 | 1091784954-A |


## Personeldeki Aktif/Bekleyen Operasyonlar
Bu tablo `BekleyenAdet > 0` veya `Onay=false/null` olan görevleri aktif sayar. Canlı veride bazı `Onay=true` kayıtlarında da bekleyen adet kaldığı için bu alan özellikle önemli.
| Ara ürün | Bölüm | Bekleyen | Görev satırı | Görev adedi | Personeller | Ana ürünler | İlk görev | Son görev |
|---|---|---|---|---|---|---|---|---|
| MAR/kavak çıta 34x3x2(basit kasa) | Marangozhane | 680 | 1 | 680 | orhan | Ara Mamül | 16/04/2026 00:00 | 16/04/2026 00:00 |
| YM/favela.pavia.alaves.bahama berjer kasa(OTURUM)MDF kesim | YM Depo | 329 | 2 | 29 | eren emir güneysu, TESTY | Ara Mamül, Ham Madde | 08/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) | Marangozhane | 329 | 3 | 0 | adem çınar, Ramazan Kömreli, TESTM | Ara Mamül | 08/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/duralit basit kasa | Marangozhane | 282 | 1 | 282 | orhan | Ara Mamül | 16/04/2026 00:00 | 16/04/2026 00:00 |
| BOY/kol favela(DEMONTE) berjer | Boyahane | 262 | 4 | 262 | hüdai | Favela Bohem İkili Berjer Natürel Ahşap, Gri, Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve, Rock Bohem Sallanır Berjer, Kırık Beyaz, Rock Bohem Sallanır Berjer, Sütlü Kahve | 03/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/basit kasa | Marangozhane | 200 | 2 | 90 | adem çınar, rıza | Ara Mamül, Long Line Puf, Kırık Beyaz Gold | 07/04/2026 00:00 | 17/04/2026 00:00 |
| YM/8mm MDF 33.5 çap daire | YM Depo | 171 | 1 | 171 | eren emir güneysu | Ara Mamül | 16/04/2026 00:00 | 16/04/2026 00:00 |
| YM/ayak zade bohem(legna) 19cm | YM Depo | 160 | 3 | 152 | TESTY, Yuşa Garouhi | Legna Bohem Köşe Takımı, Gri SOL KÖŞE, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 13/04/2026 00:00 | 16/04/2026 00:00 |
| YM/18mm sunta 33.5 çap daire | YM Depo | 155 | 1 | 155 | eren emir güneysu | Ara Mamül | 16/04/2026 00:00 | 16/04/2026 00:00 |
| YM/legna kanepe kol 8mm MDF | YM Depo | 152 | 2 | 152 | eren emir güneysu, TESTY | Ara Mamül, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/kol pavia berjer gürgen kesim | Marangozhane | 130 | 2 | 130 | Ramazan Ejder | Pavia Berjer Ceviz Ahşap, Sütlü Kahve, Pavia Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 07/04/2026 07:05 |
| MAR/kol pavia berjer | Marangozhane | 116 | 2 | 22 | Ramazan Ejder | Pavia Berjer Ceviz Ahşap, Sütlü Kahve, Pavia Berjer Natürel Ahşap, Kırık Beyaz | 06/04/2026 00:00 | 07/04/2026 00:00 |
| BOY/kol pavia berjer | Boyahane | 110 | 2 | 52 | hüdai | Pavia Berjer Natürel Ahşap, Kırık Beyaz, Pavia Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 07/04/2026 00:00 |
| BOY/rock.kalina berjer gürgen sallanma ayağı | Boyahane | 92 | 4 | 92 | hüdai | Rock Bohem Sallanır Berjer, Tarçın/Hardal, Rock Bohem Sallanır Berjer, Kırık Beyaz, Rock Bohem Sallanır Berjer, Sütlü Kahve | 02/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/favela.pavia.alaves destek ayak | Marangozhane | 89 | 2 | 89 | Kemal Akbulut | Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve, Favela Bohem İkili Berjer Natürel Ahşap, Gri | 02/04/2026 00:00 | 30/03/2026 00:00 |
| DÖŞ/puf zem longline babyface bf910 kırık beyaz altın elkamet | Döşemehane | 88 | 3 | 88 | ali akyürek, Kemal Akbulut | Ara Mamül, Long Line Puf, Kırık Beyaz Gold | 08.04.2026 09:45 | 18/04/2026 00:00 |
| BOY/favela.pavia.alaves destek ayak | Boyahane | 84 | 4 | 0 | niyazi | Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve, Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve, Favela Bohem İkili Berjer Natürel Ahşap, Gri | 03/04/2026 00:00 | 31/03/2026 00:00 |
| BOY/favela berjer kol bağlantı GÜRGEN | Boyahane | 81 | 2 | 81 | hüdai, niyazi | Favela Bohem Berjer Natürel Ahşap, Hardal, Rock Bohem Sallanır Berjer, Kırık Beyaz | 03/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/duralit marin kasa | Marangozhane | 80 | 1 | 80 | orhan | Marin Puf, Beyaz Welsoft Kumaş | 16/04/2026 00:00 | 16/04/2026 00:00 |
| YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF | YM Depo | 76 | 2 | 76 | eren emir güneysu, TESTY | Ara Mamül | 14/04/2026 00:00 | 15/04/2026 00:00 |
| MAR/legna kanepe kol duralit kesim | Marangozhane | 76 | 2 | 76 | orhan, TESTM | Ara Mamül, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/legna kanepe kol | Marangozhane | 76 | 2 | 0 | adem çınar, TESTM | Ara Mamül, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/alaves berjer kol | Marangozhane | 72 | 3 | 50 | ergül | Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | 03/04/2026 00:00 | 13.04.2026 15:05 |
| BOY/alaves berjer kol(kahve pinotex) | Boyahane | 72 | 1 | 0 | niyazi | Ara Mamül | 06/04/2026 00:00 | 06/04/2026 00:00 |
| YM/8mm MDF 37cm çap daire(fine kafa.rennes kafa taban) | YM Depo | 70 | 1 | 0 | eren emir güneysu | Ara Mamül | 13/04/2026 00:00 | 13/04/2026 00:00 |
| YM/legna kanepe kol 8mm MDF renkli | YM Depo | 66 | 2 | 66 | eren emir güneysu, TESTY | Ara Mamül, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 18/04/2026 00:00 |
| BOY/ayak modena 18cm naturel vernikli | Boyahane | 60 | 3 | 60 | niyazi | İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Taba, İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Kırık Beyaz, İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | 02/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/puf zem lines wolf 19 gri | Paketleme | 59 | 1 | 59 | caner | Lines Puf, Teddy Kumaş Gri | 09/04/2026 00:00 | 09/04/2026 00:00 |
| puf zem lines wolf 19 gri | Ürün Depo | 59 | 1 | 0 | Mustafa Ali Acar | Lines Puf, Teddy Kumaş Gri | 09/04/2026 00:00 | 09/04/2026 00:00 |
| MAR/şila.louca kasa | Marangozhane | 57 | 1 | 57 | adem çınar | Ara Mamül | 07/04/2026 00:00 | 07/04/2026 00:00 |
| BOY/ayak (leones marcel) torna 15cm | Boyahane | 56 | 2 | 56 | niyazi | Leones Bohem Ahşap Ayaklı Kanepe, Kırık Beyaz, Leones Bohem Ahşap Ayaklı Kanepe, Gri | 06/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/favela.pavia.bahama berjer kasa(SIRT) | Marangozhane | 55 | 2 | 55 | adem çınar, TESTM | Ara Mamül | 06/04/2026 00:00 | 14/04/2026 00:00 |
| TER/berjer zem favela.pavia wolf 01 beyaz | Terzihane | 50 | 1 | 50 | fatma | Ara Mamül | 18.04.2026 11:37 | 18.04.2026 11:37 |
| MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | Marangozhane | 50 | 3 | 0 | orhan, rıza, TESTM | Ara Mamül | 07/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/favela.pavia 2li berjer kasa(SIRT) | Marangozhane | 45 | 2 | 45 | rıza, TESTM | Ara Mamül | 07/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/marin kasa | Marangozhane | 40 | 1 | 0 | adem çınar | Marin Puf, Beyaz Welsoft Kumaş | 17/04/2026 00:34 | 17/04/2026 00:34 |
| DÖŞ/bench zem marin welsoft beyaz | Döşemehane | 40 | 1 | 0 | ali akyürek | Marin Puf, Beyaz Welsoft Kumaş | 17/04/2026 00:00 | 17/04/2026 00:00 |
| PAK/bench zem marin welsoft beyaz | Paketleme | 40 | 1 | 0 | caner | Marin Puf, Beyaz Welsoft Kumaş | 17/04/2026 00:00 | 17/04/2026 00:00 |
| bench zem marin welsoft beyaz | Ürün Depo | 40 | 1 | 0 | Mustafa Ali Acar | Marin Puf, Beyaz Welsoft Kumaş | 17/04/2026 00:00 | 17/04/2026 00:00 |
| BOY/ayak gigi gümüş | Boyahane | 40 | 1 | 40 | hüdai | bench zem louca zeugma v106 keten gri gümüş ayak | 04/04/2026 00:00 | 04/04/2026 00:00 |
| YM/18mm sunta 34x86cm(marin alt)(ayak girmesi için içi açılmış) | YM Depo | 40 | 1 | 40 | eren emir güneysu | Marin Puf, Beyaz Welsoft Kumaş | 16/04/2026 00:00 | 16/04/2026 00:00 |
| YM/8mm MDF 34x86cm(marin üst.marin sandıklı kapak) | YM Depo | 40 | 1 | 40 | eren emir güneysu | Marin Puf, Beyaz Welsoft Kumaş | 16/04/2026 00:00 | 16/04/2026 00:00 |
| PAK/puf zem lines wolf 05 sütlü kahve | Paketleme | 40 | 1 | 40 | caner | Lines Puf ,teddy Kumaş Sütlü Kahve | 15.04.2026 13:59 | 15.04.2026 13:59 |
| puf zem lines wolf 05 sütlü kahve | Ürün Depo | 40 | 1 | 0 | Mustafa Ali Acar | Lines Puf ,teddy Kumaş Sütlü Kahve | 14/04/2026 00:00 | 14/04/2026 00:00 |
| TER/berjer zem alaves wolf 05 sütlü kahve | Terzihane | 40 | 2 | 0 | fatma, Selime Yahşi | Ara Mamül | 13/04/2026 00:00 | 18/04/2026 00:00 |
| TER/berjer zem alaves 2li wolf 05 sütlü kahve | Terzihane | 40 | 2 | 0 | fatma, Selime Yahşi | Ara Mamül | 13/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/alaves berjer kol kesim | Marangozhane | 36 | 2 | 36 | ergül | Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve, Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve | 13.04.2026 15:05 | 13.04.2026 15:05 |
| YM/18mm sunta 37cm çap İÇİ BOŞ daire(rennes puf üst) | YM Depo | 35 | 1 | 0 | eren emir güneysu | Ara Mamül | 13/04/2026 00:00 | 13/04/2026 00:00 |
| MAR/rennes kasa | Marangozhane | 35 | 1 | 0 | adem çınar | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/favela 3lü berjer kasa (SIRT) kavak kesim | Marangozhane | 35 | 2 | 5 | orhan | Ara Mamül | 08/04/2026 00:00 | 15/04/2026 00:00 |
| MAR/favela 3lü berjer kasa(SIRT) | Marangozhane | 35 | 2 | 0 | Ramazan Kömreli, rıza | Ara Mamül | 09/04/2026 00:00 | 15/04/2026 00:00 |
| MAR/favela 3lü berjer kasa (OTURUM) kavak kesim | Marangozhane | 35 | 2 | 5 | orhan | Ara Mamül | 08/04/2026 00:00 | 15/04/2026 00:00 |
| MAR/favela 3lü berjer kasa(OTURUM) | Marangozhane | 35 | 2 | 0 | rıza | Ara Mamül | 09/04/2026 00:00 | 15/04/2026 00:00 |
| MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) kavak kesim | Marangozhane | 29 | 1 | 29 | TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 00:00 |
| YM/legna köşe takımı sırt MDF | YM Depo | 29 | 3 | 23 | eren emir güneysu, TESTY | Legna Bohem Köşe Takımı, Gri SOL KÖŞE, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 13/04/2026 00:00 | 16/04/2026 00:00 |
| MAR/legna köşe takımı sırt MDF zımpara tırnaklı somun | Marangozhane | 29 | 4 | 0 | adem çınar, orhan, TESTM | Legna Bohem Köşe Takımı, Gri SAG KÖŞE, Legna Bohem Köşe Takımı, Gri SOL KÖŞE, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 13/04/2026 00:00 | 16/04/2026 00:00 |
| PAK/puf zem longline babyface bf910 kırık beyaz altın elkamet | Paketleme | 28 | 1 | 0 | caner | Long Line Puf, Kırık Beyaz Gold | 08/04/2026 00:00 | 08/04/2026 00:00 |
| puf zem longline babyface bf910 kırık beyaz altın elkamet | Ürün Depo | 28 | 1 | 0 | Mustafa Ali Acar | Long Line Puf, Kırık Beyaz Gold | 08/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/legna köşe takımı kasa sağ | Marangozhane | 28 | 2 | 28 | rıza, TESTM | Ara Mamül, Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 13/04/2026 00:00 | 14/04/2026 00:00 |
| BOY/favela 2li berjer kol bağlantı GÜRGEN | Boyahane | 25 | 1 | 25 | niyazi | Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve | 03/04/2026 00:00 | 03/04/2026 00:00 |
| MAR/legna köşe takımı kasa sol | Marangozhane | 21 | 2 | 21 | Ramazan Kömreli, rıza | Ara Mamül, Legna Bohem Köşe Takımı, Gri SOL KÖŞE | 13/04/2026 00:00 | 16/04/2026 00:00 |
| KES/berjer zem alaves wolf 05 sütlü kahve | Kesimhane | 20 | 1 | 20 | deniz | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| KES/berjer zem alaves 2li wolf 05 sütlü kahve | Kesimhane | 20 | 1 | 20 | deniz | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/alto berjer ayak | Marangozhane | 20 | 1 | 20 | Ramazan Ejder | Alto Bohem Sallanır Berjer, Açık Krem | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/alto berjer ayak | Boyahane | 20 | 1 | 0 | hüdai | Alto Bohem Sallanır Berjer, Açık Krem | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem rock sallanır wolf 05 sütlü kahve | Paketleme | 20 | 2 | 0 | müşteba | Rock Bohem Sallanır Berjer, Sütlü Kahve | 03/04/2026 00:00 | 09/04/2026 00:00 |
| berjer zem rock sallanır wolf 05 sütlü kahve | Ürün Depo | 20 | 2 | 0 | Mustafa Ali Acar | Rock Bohem Sallanır Berjer, Sütlü Kahve | 03/04/2026 00:00 | 09/04/2026 00:00 |
| YM/alaves CNC sırt bağlantı MDF | YM Depo | 20 | 1 | 20 | eren emir güneysu | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) kavak kesim | Marangozhane | 20 | 2 | 20 | orhan, TESTM | Ara Mamül | 14/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/kol tosca berjer gürgen kesim | Marangozhane | 18 | 2 | 18 | adem çınar, ergül | Tosca Bohem Berjer Natürel Ahşap, Sütlü Kahve, Tosca Bohem Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| MAR/kol tosca berjer | Marangozhane | 18 | 2 | 18 | adem çınar, ergül | Tosca Bohem Berjer Natürel Ahşap, Sütlü Kahve, Tosca Bohem Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim | Marangozhane | 18 | 1 | 18 | TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 00:00 |
| BOY/ayak tosca 25cm(kahve pinotex) | Boyahane | 16 | 1 | 16 | niyazi | Tosca Bohem Berjer Ceviz Ahşap , Gri | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/legna kanepe kol kavak kesim | Marangozhane | 16 | 1 | 16 | TESTM | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/favela.pavia 2li berjer kasa(SIRT) kavak kesim | Marangozhane | 15 | 1 | 15 | TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 00:00 |
| DÖŞ/berjer zem pavia 2li wolf 05 sütlü kahve | Döşemehane | 15 | 1 | 0 | abdullah kasimi | Ara Mamül | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/berjer zem favela(DEMONTE) wolf 19 gri | Paketleme | 15 | 1 | 0 | müşteba | Favela Bohem Ahşap Ayaklı Gri Teddy Kumaş Berjer | 06/04/2026 00:00 | 06/04/2026 00:00 |
| PAK/berjer zem rock sallanır wolf 01 beyaz | Paketleme | 15 | 2 | 0 | müşteba | Rock Bohem Sallanır Berjer, Kırık Beyaz | 03/04/2026 00:00 | 09/04/2026 00:00 |
| berjer zem rock sallanır wolf 01 beyaz | Ürün Depo | 15 | 2 | 0 | Mustafa Ali Acar | Rock Bohem Sallanır Berjer, Kırık Beyaz | 03/04/2026 00:00 | 09/04/2026 00:00 |
| MAR/lupa berjer gürgen kesim | Marangozhane | 14 | 2 | 4 | Ramazan Ejder | Ara Mamül, Lupa Bohem Ahşap Berjer , Beyaz | 07/04/2026 00:00 | 09/04/2026 00:00 |
| MAR/lupa berjer kol | Marangozhane | 14 | 2 | 0 | Ramazan Ejder | Ara Mamül, Lupa Bohem Ahşap Berjer , Beyaz | 07/04/2026 00:00 | 09/04/2026 00:00 |
| BOY/lupa berjer kol | Boyahane | 14 | 2 | 0 | hüdai | Ara Mamül, Lupa Bohem Ahşap Berjer , Beyaz | 08/04/2026 00:00 | 10/04/2026 00:00 |
| PAK/berjer zem favela 2li wolf 05 sütlü kahve | Paketleme | 13 | 2 | 0 | müşteba | Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve | 02/04/2026 00:00 | 06/04/2026 00:00 |
| berjer zem favela 2li wolf 05 sütlü kahve | Ürün Depo | 13 | 2 | 0 | Mustafa Ali Acar | Favela Bohem İkili Berjer Natürel Ahşap, Sütlü Kahve | 02/04/2026 00:00 | 06/04/2026 00:00 |
| PAK/berjer zem alaves 2li wolf 05 sütlü kahve | Paketleme | 12 | 1 | 0 | müşteba | Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/doris berjer sırt gürgen | Marangozhane | 12 | 1 | 12 | Ramazan Ejder | Doris Bohem Berjer, Kırık Beyaz | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/doris berjer sırt gürgen | Boyahane | 12 | 1 | 0 | niyazi | Doris Bohem Berjer, Kırık Beyaz | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem favela(DEMONTE) wolf 01 beyaz | Paketleme | 11 | 2 | 0 | müşteba | Favela Bohem Ahşap Ayaklı Beyaz Teddy Kumaş Berjer V01 | 02/04/2026 00:00 | 06/04/2026 00:00 |
| berjer zem alaves 2li wolf 05 sütlü kahve(kahve pinotex) | Ürün Depo | 11 | 1 | 0 | Mustafa Ali Acar | Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| DÖŞ/berjer zem favela(DEMONTE) wolf 19 gri | Döşemehane | 10 | 1 | 10 | abdullah kasimi | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| berjer zem favela(DEMONTE) wolf 19 gri | Ürün Depo | 10 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Ahşap Ayaklı Gri Teddy Kumaş Berjer | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/tosca berjer kol naturel | Boyahane | 10 | 1 | 10 | hüdai | Tosca Bohem Berjer Natürel Ahşap, Gri | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem favela 2li wolf 19 gri | Paketleme | 10 | 1 | 0 | müşteba | Favela Bohem İkili Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| PAK/berjer zem favela 3lü wolf 05 sütlü kahve | Paketleme | 10 | 1 | 0 | müşteba | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 04/04/2026 00:00 | 04/04/2026 00:00 |
| berjer zem favela 3lü wolf 05 sütlü kahve | Ürün Depo | 10 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 04/04/2026 00:00 | 04/04/2026 00:00 |
| MAR/alto berjer sallanır ayak | Marangozhane | 10 | 1 | 10 | Ramazan Ejder | Alto Bohem Sallanır Berjer, Açık Krem | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/alto berjer sallanır ayak | Boyahane | 10 | 1 | 0 | hüdai | Alto Bohem Sallanır Berjer, Açık Krem | 07/04/2026 00:00 | 07/04/2026 00:00 |
| BOY/marcel berjer kasa | Boyahane | 10 | 1 | 10 | hüdai | Ara Mamül | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/berjer zem pavia wolf 01 beyaz | Paketleme | 9 | 1 | 0 | müşteba | Pavia Berjer Natürel Ahşap, Kırık Beyaz | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/garcia köşe takımı sırt duralit MDF sunta kesim | Marangozhane | 9 | 2 | 9 | emin, TESTM | Ara Mamül | 14/04/2026 00:00 | 16.04.2026 08:42 |
| MAR/garcia köşe takımı sırt kavak kesim | Marangozhane | 9 | 2 | 9 | emin, TESTM | Ara Mamül | 14/04/2026 00:00 | 16.04.2026 08:42 |
| MAR/garcia köşe takımı sırt | Marangozhane | 9 | 2 | 18 | Ramazan Kömreli, TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 21:08 |
| DEM/lupa berjer 60cm profil | Demirhane | 8 | 1 | 8 | mehmetB | Lupa Bohem Ahşap Berjer , Beyaz | 07/04/2026 00:00 | 07/04/2026 00:00 |
| BOY/lupa berjer 60cm profil | Boyahane | 8 | 1 | 0 | hüdai | Lupa Bohem Ahşap Berjer , Beyaz | 08/04/2026 00:00 | 08/04/2026 00:00 |
| DÖŞ/berjer zem alaves 2li wolf 05 sütlü kahve | Döşemehane | 8 | 2 | 0 | abdullah kasimi | Ara Mamül, Alaves Hazeran Jüt Bohem İkili Berjer Ceviz Ahşap, Sütlü Kahve | 06/04/2026 00:00 | 18/04/2026 00:00 |
| BOY/tosca berjer kol(kahve pinotex) | Boyahane | 8 | 1 | 8 | hüdai | Tosca Bohem Berjer Ceviz Ahşap , Gri | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/legna köşe takımı kasa kavak kesim | Marangozhane | 8 | 1 | 8 | TESTM | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| DÖŞ/köşe takımı zem legna zeugma keten gri sağ | Döşemehane | 8 | 1 | 0 | ali ihsan yılmaz | Legna Bohem Köşe Takımı, Gri SAG KÖŞE | 17/04/2026 00:30 | 17/04/2026 00:30 |
| PAK/köşe takımı zem legna zeugma keten gri sağ | Paketleme | 8 | 1 | 0 | caner | Legna Bohem Köşe Takımı, Gri SAG KÖŞE | 16/04/2026 00:00 | 16/04/2026 00:00 |
| köşe takımı zem legna zeugma keten gri sağ | Ürün Depo | 8 | 1 | 0 | Mustafa Ali Acar | Legna Bohem Köşe Takımı, Gri SAG KÖŞE | 16/04/2026 00:00 | 16/04/2026 00:00 |
| DÖŞ/köşe takımı zem legna zeugma keten krem sağ | Döşemehane | 8 | 1 | 0 | ali ihsan yılmaz | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 13/04/2026 00:00 | 13/04/2026 00:00 |
| PAK/köşe takımı zem legna zeugma keten krem sağ | Paketleme | 8 | 1 | 0 | TESTP | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| köşe takımı zem legna zeugma keten krem sağ | Ürün Depo | 8 | 1 | 0 | Mustafa Ali Acar | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| BOY/kol pavia (ceviz) berjer | Boyahane | 8 | 1 | 8 | hüdai | Pavia Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem alaves wolf 05 sütlü kahve | Paketleme | 7 | 1 | 0 | müşteba | Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| berjer zem favela 2li wolf 19 gri | Ürün Depo | 7 | 1 | 0 | Mustafa Ali Acar | Favela Bohem İkili Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| YM/garcia köşe takımı kasa MDF | YM Depo | 7 | 2 | 2 | eren emir güneysu, TESTY | Ara Mamül | 13/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/garcia köşe takımı kasa | Marangozhane | 7 | 2 | 0 | rıza, TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 21:09 |
| DÖŞ/köşe takımı zem legna zeugma keten gri sol | Döşemehane | 7 | 1 | 0 | Kemal Akbulut | Legna Bohem Köşe Takımı, Gri SOL KÖŞE | 17/04/2026 00:31 | 17/04/2026 00:31 |
| PAK/köşe takımı zem legna zeugma keten gri sol | Paketleme | 7 | 1 | 0 | caner | Legna Bohem Köşe Takımı, Gri SOL KÖŞE | 16/04/2026 00:00 | 16/04/2026 00:00 |
| köşe takımı zem legna zeugma keten gri sol | Ürün Depo | 7 | 1 | 0 | Mustafa Ali Acar | Legna Bohem Köşe Takımı, Gri SOL KÖŞE | 16/04/2026 00:00 | 16/04/2026 00:00 |
| MAR/kol favela(DEMONTE) berjer kesim | Marangozhane | 6 | 1 | 6 | Ramazan Ejder | Rock Bohem Sallanır Berjer, Kırık Beyaz | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/kol favela(DEMONTE) berjer | Marangozhane | 6 | 1 | 6 | Ramazan Ejder | Rock Bohem Sallanır Berjer, Kırık Beyaz | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/pavia puf kol gürgen kesim | Marangozhane | 6 | 1 | 6 | Ramazan Ejder | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| MAR/pavia puf kol | Marangozhane | 6 | 1 | 0 | Ramazan Ejder | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| BOY/pavia puf kol | Boyahane | 6 | 1 | 0 | hüdai | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| berjer zem alaves wolf 05 sütlü kahve | Ürün Depo | 6 | 1 | 0 | Mustafa Ali Acar | Alaves Hazeranlı Bohem Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/garcia köşe takımı kol duralit MDF sunta kesim | Marangozhane | 6 | 2 | 6 | emin, TESTM | Ara Mamül | 14/04/2026 00:00 | 16.04.2026 08:42 |
| MAR/garcia köşe takımı kol kavak kesim | Marangozhane | 6 | 2 | 6 | emin, TESTM | Ara Mamül | 14/04/2026 00:00 | 16.04.2026 08:43 |
| MAR/garcia köşe takımı kol | Marangozhane | 6 | 2 | 12 | Ramazan Kömreli, TESTM | Ara Mamül | 14/04/2026 00:00 | 14/04/2026 00:00 |
| PAK/berjer zem doris wolf 01 beyaz | Paketleme | 6 | 1 | 0 | müşteba | Doris Bohem Berjer, Kırık Beyaz | 08/04/2026 00:00 | 08/04/2026 00:00 |
| berjer zem doris wolf 01 beyaz | Ürün Depo | 6 | 1 | 0 | Mustafa Ali Acar | Doris Bohem Berjer, Kırık Beyaz | 08/04/2026 00:00 | 08/04/2026 00:00 |
| PAK/berjer zem rock sallanır hardal | Paketleme | 6 | 2 | 0 | müşteba | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 03/04/2026 00:00 | 09/04/2026 00:00 |
| berjer zem rock sallanır hardal | Ürün Depo | 6 | 2 | 0 | Mustafa Ali Acar | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 03/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/bench zem bueno hanedan gri | Paketleme | 6 | 1 | 0 | caner | bench zem bueno hanedan gri | 13.04.2026 22:12 | 13.04.2026 22:12 |
| bench zem bueno hanedan gri | Ürün Depo | 6 | 1 | 0 | Mustafa Ali Acar | bench zem bueno hanedan gri | 07/04/2026 00:00 | 07/04/2026 00:00 |
| KES/bench zem marin sandıklı punch altıgen welsoft | Kesimhane | 5 | 1 | 5 | deniz | bench zem marin sandıklı punch altıgen welsoft | 16/04/2026 00:00 | 16/04/2026 00:00 |
| TER/bench zem marin sandıklı punch altıgen welsoft | Terzihane | 5 | 1 | 0 | fatma | bench zem marin sandıklı punch altıgen welsoft | 16/04/2026 00:00 | 16/04/2026 00:00 |
| DÖŞ/bench zem marin sandıklı punch altıgen welsoft | Döşemehane | 5 | 1 | 0 | ali akyürek | bench zem marin sandıklı punch altıgen welsoft | 17/04/2026 00:00 | 17/04/2026 00:00 |
| PAK/bench zem marin sandıklı punch altıgen welsoft | Paketleme | 5 | 1 | 0 | caner | bench zem marin sandıklı punch altıgen welsoft | 17/04/2026 00:00 | 17/04/2026 00:00 |
| bench zem marin sandıklı punch altıgen welsoft | YM Depo | 5 | 1 | 0 | Yuşa Garouhi | bench zem marin sandıklı punch altıgen welsoft | 17/04/2026 00:00 | 17/04/2026 00:00 |
| DÖŞ/berjer zem alaves wolf 05 sütlü kahve | Döşemehane | 5 | 1 | 0 | abdullah kasimi | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/alaves berjer 2li kasa(SIRT) | Marangozhane | 5 | 1 | 0 | orhan | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| PAK/sehpa zem alaves(kahve pinotex) | Paketleme | 5 | 1 | 0 | müşteba | Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 07/04/2026 00:00 | 07/04/2026 00:00 |
| sehpa zem alaves(kahve pinotex) | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 08/04/2026 00:00 | 08/04/2026 00:00 |
| DÖŞ/berjer zem favela 3lü wolf 05 sütlü kahve | Döşemehane | 5 | 1 | 0 | abdullah kasimi | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 17/04/2026 00:00 | 17/04/2026 00:00 |
| PAK/berjer zem favela 3lü wolf 19 gri | Paketleme | 5 | 1 | 0 | müşteba | Favela Bohem Üçlü Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| PAK/kanepe zem legna zeugma keten krem | Paketleme | 5 | 2 | 2 | caner, Ramazan Kömreli | Legna Bohem Kanepe, Açık Krem | 02/04/2026 00:00 | 13.04.2026 22:12 |
| kanepe zem legna zeugma keten krem | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Legna Bohem Kanepe, Açık Krem | 08/04/2026 00:00 | 08/04/2026 00:00 |
| PAK/berjer zem alto zeugma keten krem | Paketleme | 5 | 1 | 0 | müşteba | Alto Bohem Sallanır Berjer, Açık Krem | 08/04/2026 00:00 | 08/04/2026 00:00 |
| berjer zem alto zeugma keten krem | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Alto Bohem Sallanır Berjer, Açık Krem | 08/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/meyra berjer sallanır kasa | Marangozhane | 5 | 2 | 5 | emin | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Açık Krem | 13/04/2026 00:00 | 16.04.2026 08:41 |
| BOY/meyra berjer sallanır kasa | Boyahane | 5 | 1 | 0 | hüdai | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Gri | 14/04/2026 00:00 | 14/04/2026 00:00 |
| DÖŞ/berjer zem favela(DEMONTE) wolf hardal | Döşemehane | 5 | 1 | 0 | abdullah kasimi | Ara Mamül | 17/04/2026 00:00 | 17/04/2026 00:00 |
| PAK/köşe takımı zem legna zeugma keten krem sol | Paketleme | 5 | 1 | 5 | caner | Legna Bohem Köşe Takımı, Açık Krem SOL KÖŞE | 07/04/2026 00:00 | 07/04/2026 00:00 |
| köşe takımı zem legna zeugma keten krem sol | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Legna Bohem Köşe Takımı, Açık Krem SOL KÖŞE | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem rock sallanır wolf 19 gri | Paketleme | 5 | 1 | 0 | müşteba | Rock Bohem Sallanır Berjer, Gri | 09/04/2026 00:00 | 09/04/2026 00:00 |
| berjer zem rock sallanır wolf 19 gri | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Rock Bohem Sallanır Berjer, Gri | 09/04/2026 00:00 | 09/04/2026 00:00 |
| MAR/favela sehpa gürgen kesim | Marangozhane | 5 | 1 | 5 | Ramazan Ejder | Favela Bohem Sehpa, Natürel Ahşap | 06/04/2026 00:00 | 06/04/2026 00:00 |
| MAR/favela sehpa | Marangozhane | 5 | 1 | 0 | Ramazan Ejder | Favela Bohem Sehpa, Natürel Ahşap | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/favela sehpa | Boyahane | 5 | 1 | 0 | hüdai | Favela Bohem Sehpa, Natürel Ahşap | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/sehpa zem favela | Paketleme | 5 | 1 | 0 | müşteba | Favela Bohem Sehpa, Natürel Ahşap | 08/04/2026 00:00 | 08/04/2026 00:00 |
| sehpa zem favela | Ürün Depo | 5 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Sehpa, Natürel Ahşap | 08/04/2026 00:00 | 08/04/2026 00:00 |
| MAR/alaves berjer tekli sırt kasa | Marangozhane | 5 | 1 | 0 | orhan | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| MAR/alaves berjer 2li kasa (SIRT) duralit | Marangozhane | 5 | 1 | 5 | orhan | Ara Mamül | 18/04/2026 00:00 | 18/04/2026 00:00 |
| DÖŞ/berjer zem pavia wolf hardal | Döşemehane | 5 | 1 | 0 | abdullah kasimi | Ara Mamül | 17/04/2026 00:00 | 17/04/2026 00:00 |
| PAK/berjer zem favela 2li wolf 01 beyaz | Paketleme | 4 | 1 | 0 | müşteba | Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | 01/04/2026 00:00 | 01/04/2026 00:00 |
| KES/köşe takımı zem legna zeugma keten krem | Kesimhane | 4 | 1 | 4 | TESTK | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| TER/köşe takımı zem legna zeugma keten krem | Terzihane | 4 | 1 | 4 | TESTT | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 14/04/2026 00:00 | 14/04/2026 00:00 |
| YM/ayak zade kulaklı bohem(royal) 15cm | YM Depo | 4 | 1 | 4 | Yuşa Garouhi | Royal Bohem Berjer, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| BOY/ayak zade kulaklı bohem(royal kahve pinotex) 15cm | Boyahane | 4 | 1 | 4 | niyazi | Royal Bohem Berjer, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| PAK/berjer zem pavia wolf 05 sütlü kahve | Paketleme | 4 | 1 | 0 | müşteba | Pavia Berjer Natürel Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| berjer zem pavia wolf 05 sütlü kahve | Ürün Depo | 4 | 1 | 0 | Mustafa Ali Acar | Pavia Berjer Natürel Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| MAR/pavia puf kasa MDF.sunta | Marangozhane | 3 | 1 | 3 | Ramazan Ejder | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| MAR/pavia puf kasa | Marangozhane | 3 | 1 | 0 | Ramazan Ejder | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| DÖŞ/puf zem pavia wolf 05 sütlü kahve | Döşemehane | 3 | 1 | 0 | abdullah kasimi | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| PAK/puf zem pavia wolf 05 sütlü kahve | Paketleme | 3 | 1 | 0 | müşteba | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| puf zem pavia wolf 05 sütlü kahve | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | Pavia Puf Natürel Ahşap, Sütlü Kahve Ofis, Balkon, Kafe, Bahçe, Salon Mobilyası | 16/04/2026 00:00 | 16/04/2026 00:00 |
| bench zem scala sandıklı wolf 05 sütlü kahve | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | Scala Sandıklı Bohem Ahşap Ayaklı Puf/Bench, Sütlü Kahve | 06/04/2026 00:00 | 06/04/2026 00:00 |
| DÖŞ/berjer zem favela 2li wolf 19 gri | Döşemehane | 3 | 1 | 0 | ali ihsan yılmaz | Favela Bohem İkili Berjer Natürel Ahşap, Gri | 02/04/2026 00:00 | 02/04/2026 00:00 |
| DÖŞ/berjer zem favela 2li wolf hardal | Döşemehane | 3 | 1 | 0 | ali ihsan yılmaz | Favela Bohem İkili Berjer Natürel Ahşap, Hardal | 03/04/2026 00:00 | 03/04/2026 00:00 |
| PAK/berjer zem favela 2li wolf hardal | Paketleme | 3 | 1 | 0 | müşteba | Favela Bohem İkili Berjer Natürel Ahşap, Hardal | 06/04/2026 00:00 | 06/04/2026 00:00 |
| berjer zem favela 2li wolf hardal | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | Favela Bohem İkili Berjer Natürel Ahşap, Hardal | 06/04/2026 00:00 | 06/04/2026 00:00 |
| berjer zem favela 3lü wolf 19 gri | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Üçlü Berjer Natürel Ahşap, Gri | 06/04/2026 00:00 | 06/04/2026 00:00 |
| MAR/garcia köşe takımı kasa duralit kavak kesim | Marangozhane | 3 | 2 | 3 | emin, TESTM | Ara Mamül | 14/04/2026 00:00 | 16.04.2026 08:42 |
| DÖŞ/berjer zem alto zeugma keten krem | Döşemehane | 3 | 3 | 3 | abdullah kasimi | Alto Bohem Sallanır Berjer, Açık Krem | 06/04/2026 00:08 | 17.04.2026 22:19 |
| PAK/berjer zem meyra sallanır interno krem | Paketleme | 3 | 1 | 0 | caner | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| berjer zem meyra sallanır interno krem | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| MAR/irina akıllı sehpa kasa | Marangozhane | 3 | 1 | 0 | adem çınar | İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/akıllı sehpa zem irina wolf 05 sütlü kahve | Paketleme | 3 | 1 | 0 | caner | İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | 08/04/2026 00:00 | 08/04/2026 00:00 |
| akıllı sehpa zem irina wolf 05 sütlü kahve | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | İrina Sandıklı Mekanizmalı Orta Sehpa/Bench, Sütlü Kahve | 08/04/2026 00:00 | 08/04/2026 00:00 |
| PAK/akıllı sehpa zem ester wolf 19 gri | Paketleme | 3 | 1 | 3 | caner | akıllı sehpa zem ester wolf 19 gri | 15.04.2026 13:59 | 15.04.2026 13:59 |
| akıllı sehpa zem ester wolf 19 gri | Ürün Depo | 3 | 1 | 0 | Mustafa Ali Acar | akıllı sehpa zem ester wolf 19 gri | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/berjer zem lupa zeugma v143 krem | Paketleme | 2 | 1 | 0 | müşteba | Lupa Bohem Ahşap Berjer , Beyaz | 09/04/2026 00:00 | 09/04/2026 00:00 |
| berjer zem lupa zeugma v143 krem | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | Lupa Bohem Ahşap Berjer , Beyaz | 09/04/2026 00:00 | 09/04/2026 00:00 |
| DÖŞ/süngerli kanepe zem meyra interno gri | Döşemehane | 2 | 1 | 2 | Şadi Raslan | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Gri | 10/04/2026 00:00 | 10/04/2026 00:00 |
| MAR/alaves sehpa gürgen kesim | Marangozhane | 2 | 1 | 2 | Ramazan Ejder | Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 06/04/2026 00:00 | 06/04/2026 00:00 |
| MAR/alaves sehpa | Marangozhane | 2 | 1 | 0 | Ramazan Ejder | Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/alaves sehpa(kahve pinotex) | Boyahane | 2 | 1 | 3 | hüdai | Alaves Hazeran Jüt Sehpa Ceviz Ahşap | 08/04/2026 00:00 | 08/04/2026 00:00 |
| DÖŞ/köşe takımı zem garcia wolf 19 gri | Döşemehane | 2 | 1 | 0 | ali ihsan yılmaz | Ara Mamül | 15/04/2026 00:00 | 15/04/2026 00:00 |
| PAK/berjer zem meyra sallanır interno gri | Paketleme | 2 | 1 | 0 | müşteba | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Gri | 14/04/2026 00:00 | 14/04/2026 00:00 |
| berjer zem meyra sallanır interno gri | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | Meira Sallanır Tek Kişilik Katlanır Yataklı Koltuk, Gri | 14/04/2026 00:00 | 14/04/2026 00:00 |
| PAK/berjer zem pavia (ceviz) wolf 05 sütlü kahve | Paketleme | 2 | 1 | 0 | müşteba | Pavia Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| berjer zem pavia (ceviz) wolf 05 sütlü kahve | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | Pavia Berjer Ceviz Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| PAK/berjer zem favela 3lü wolf hardal | Paketleme | 2 | 1 | 0 | müşteba | berjer zem favela 3lü wolf hardal | 04/04/2026 00:00 | 04/04/2026 00:00 |
| berjer zem favela 3lü wolf hardal | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | berjer zem favela 3lü wolf hardal | 04/04/2026 00:00 | 04/04/2026 00:00 |
| PAK/akıllı sehpa zem ester wolf 01 beyaz | Paketleme | 2 | 1 | 2 | caner | akıllı sehpa zem ester wolf 01 beyaz | 15.04.2026 13:59 | 15.04.2026 13:59 |
| akıllı sehpa zem ester wolf 01 beyaz | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | akıllı sehpa zem ester wolf 01 beyaz | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/akıllı sehpa zem ester wolf 05 sütlü kahve | Paketleme | 2 | 1 | 2 | caner | akıllı sehpa zem ester wolf 05 sütlü kahve | 15.04.2026 13:59 | 15.04.2026 13:59 |
| akıllı sehpa zem ester wolf 05 sütlü kahve | Ürün Depo | 2 | 1 | 0 | Mustafa Ali Acar | akıllı sehpa zem ester wolf 05 sütlü kahve | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/puf zem gigi peluş beyaz altın ayak | Paketleme | 1 | 1 | 0 | caner | puf zem gigi peluş beyaz altın ayak | 09/04/2026 00:00 | 09/04/2026 00:00 |
| YM/18mm sunta 33.5cm çap İÇİ BOŞ(ridge.chester.liva) | YM Depo | 1 | 1 | 0 | eren emir güneysu | Chester Puf, Gri Gold | 09/04/2026 00:00 | 09/04/2026 00:00 |
| MAR/chester puf kasa | Marangozhane | 1 | 1 | 0 | adem çınar | Chester Puf, Gri Gold | 09/04/2026 00:00 | 09/04/2026 00:00 |
| DÖŞ/puf zem chester babyface bf610 gri altın elkamet | Döşemehane | 1 | 1 | 0 | Şadi Raslan | Chester Puf, Gri Gold | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/puf zem chester babyface bf610 gri altın elkamet | Paketleme | 1 | 1 | 0 | caner | Chester Puf, Gri Gold | 09/04/2026 00:00 | 09/04/2026 00:00 |
| puf zem chester babyface bf610 gri altın elkamet | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Chester Puf, Gri Gold | 09/04/2026 00:00 | 09/04/2026 00:00 |
| PAK/berjer zem tosca wolf 05 sütlü kahve | Paketleme | 1 | 1 | 0 | caner | Tosca Bohem Berjer Natürel Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| berjer zem tosca wolf 05 sütlü kahve | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Tosca Bohem Berjer Natürel Ahşap, Sütlü Kahve | 07/04/2026 00:00 | 07/04/2026 00:00 |
| berjer zem favela 2li wolf 01 beyaz | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | 01/04/2026 00:00 | 01/04/2026 00:00 |
| PAK/kanepe zem leones wolf 01 beyaz | Paketleme | 1 | 1 | 0 | caner | Leones Bohem Ahşap Ayaklı Kanepe, Kırık Beyaz | 06/04/2026 00:00 | 06/04/2026 00:00 |
| kanepe zem leones wolf 01 beyaz | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Leones Bohem Ahşap Ayaklı Kanepe, Kırık Beyaz | 06/04/2026 00:00 | 06/04/2026 00:00 |
| BOY/royal berjer kasa | Boyahane | 1 | 1 | 1 | hüdai | Royal Bohem Berjer, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| PAK/berjer zem royal interno keten krem | Paketleme | 1 | 1 | 0 | müşteba | Royal Bohem Berjer, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| berjer zem royal interno keten krem | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Royal Bohem Berjer, Açık Krem | 14/04/2026 00:00 | 14/04/2026 00:00 |
| PAK/berjer zem favela(DEMONTE) wolf hardal | Paketleme | 1 | 1 | 0 | müşteba | Favela Bohem Berjer Natürel Ahşap, Hardal | 06/04/2026 00:00 | 06/04/2026 00:00 |
| berjer zem favela(DEMONTE) wolf hardal | Ürün Depo | 1 | 1 | 0 | Mustafa Ali Acar | Favela Bohem Berjer Natürel Ahşap, Hardal | 06/04/2026 00:00 | 06/04/2026 00:00 |


## Personel Bazında Açık Yük
| Personel | Bölüm | Aktif görev satırı | Bekleyen adet | Görevler |
|---|---|---|---|---|
| orhan | Marangozhane | 14 | 1.205 | MAR/alaves berjer 2li kasa (SIRT) duralit / MAR/alaves berjer 2li kasa(SIRT) / MAR/alaves berjer tekli sırt kasa / MAR/duralit basit kasa / MAR/duralit marin kasa / MAR/favela 3lü berjer kasa (OTURUM) kavak kesim / MAR/favela 3lü berjer kasa (SIRT) kavak kesim / MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) / MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) kavak kesim / MAR/kavak çıta 34x3x2(basit kasa) / MAR/legna kanepe kol duralit kesim / MAR/legna köşe takımı sırt MDF zımpara tırnaklı somun |
| eren emir güneysu | YM Depo | 15 | 1.038 | YM/18mm sunta 33.5 çap daire / YM/18mm sunta 33.5cm çap İÇİ BOŞ(ridge.chester.liva) / YM/18mm sunta 34x86cm(marin alt)(ayak girmesi için içi açılmış) / YM/18mm sunta 37cm çap İÇİ BOŞ daire(rennes puf üst) / YM/8mm MDF 33.5 çap daire / YM/8mm MDF 34x86cm(marin üst.marin sandıklı kapak) / YM/8mm MDF 37cm çap daire(fine kafa.rennes kafa taban) / YM/alaves CNC sırt bağlantı MDF / YM/favela.pavia.alaves.bahama berjer kasa(OTURUM)MDF kesim / YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF / YM/garcia köşe takımı kasa MDF / YM/legna kanepe kol 8mm MDF / YM/legna kanepe kol 8mm MDF renkli / YM/legna köşe takımı sırt MDF |
| hüdai | Boyahane | 26 | 647 | BOY/alaves sehpa(kahve pinotex) / BOY/alto berjer ayak / BOY/alto berjer sallanır ayak / BOY/ayak gigi gümüş / BOY/favela berjer kol bağlantı GÜRGEN / BOY/favela sehpa / BOY/kol favela(DEMONTE) berjer / BOY/kol pavia (ceviz) berjer / BOY/kol pavia berjer / BOY/lupa berjer 60cm profil / BOY/lupa berjer kol / BOY/marcel berjer kasa / BOY/meyra berjer sallanır kasa / BOY/pavia puf kol / BOY/rock.kalina berjer gürgen sallanma ayağı / BOY/royal berjer kasa / BOY/tosca berjer kol naturel / BOY/tosca berjer kol(kahve pinotex) |
| adem çınar | Marangozhane | 13 | 453 | MAR/basit kasa / MAR/chester puf kasa / MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) / MAR/favela.pavia.bahama berjer kasa(SIRT) / MAR/irina akıllı sehpa kasa / MAR/kol tosca berjer / MAR/kol tosca berjer gürgen kesim / MAR/legna kanepe kol / MAR/legna köşe takımı sırt MDF zımpara tırnaklı somun / MAR/marin kasa / MAR/rennes kasa / MAR/şila.louca kasa |
| niyazi | Boyahane | 15 | 374 | BOY/alaves berjer kol(kahve pinotex) / BOY/ayak (leones marcel) torna 15cm / BOY/ayak modena 18cm naturel vernikli / BOY/ayak tosca 25cm(kahve pinotex) / BOY/ayak zade kulaklı bohem(royal kahve pinotex) 15cm / BOY/doris berjer sırt gürgen / BOY/favela 2li berjer kol bağlantı GÜRGEN / BOY/favela berjer kol bağlantı GÜRGEN / BOY/favela.pavia.alaves destek ayak |
| Mustafa Ali Acar | Ürün Depo | 48 | 373 | akıllı sehpa zem ester wolf 01 beyaz / akıllı sehpa zem ester wolf 05 sütlü kahve / akıllı sehpa zem ester wolf 19 gri / akıllı sehpa zem irina wolf 05 sütlü kahve / bench zem bueno hanedan gri / bench zem marin welsoft beyaz / bench zem scala sandıklı wolf 05 sütlü kahve / berjer zem alaves 2li wolf 05 sütlü kahve(kahve pinotex) / berjer zem alaves wolf 05 sütlü kahve / berjer zem alto zeugma keten krem / berjer zem doris wolf 01 beyaz / berjer zem favela 2li wolf 01 beyaz / berjer zem favela 2li wolf 05 sütlü kahve / berjer zem favela 2li wolf 19 gri / berjer zem favela 2li wolf hardal / berjer zem favela 3lü wolf 05 sütlü kahve / berjer zem favela 3lü wolf 19 gri / berjer zem favela 3lü wolf hardal / berjer zem favela(DEMONTE) wolf 19 gri / berjer zem favela(DEMONTE) wolf hardal / berjer zem lupa zeugma v143 krem / berjer zem meyra sallanır interno gri / berjer zem meyra sallanır interno krem / berjer zem pavia (ceviz) wolf 05 sütlü kahve / berjer zem pavia wolf 05 sütlü ka |
| Ramazan Ejder | Marangozhane | 21 | 360 | MAR/alaves sehpa / MAR/alaves sehpa gürgen kesim / MAR/alto berjer ayak / MAR/alto berjer sallanır ayak / MAR/doris berjer sırt gürgen / MAR/favela sehpa / MAR/favela sehpa gürgen kesim / MAR/kol favela(DEMONTE) berjer / MAR/kol favela(DEMONTE) berjer kesim / MAR/kol pavia berjer / MAR/kol pavia berjer gürgen kesim / MAR/lupa berjer gürgen kesim / MAR/lupa berjer kol / MAR/pavia puf kasa / MAR/pavia puf kasa MDF.sunta / MAR/pavia puf kol / MAR/pavia puf kol gürgen kesim |
| rıza | Marangozhane | 9 | 281 | MAR/basit kasa / MAR/favela 3lü berjer kasa(OTURUM) / MAR/favela 3lü berjer kasa(SIRT) / MAR/favela.pavia 2li berjer kasa(SIRT) / MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) / MAR/garcia köşe takımı kasa / MAR/legna köşe takımı kasa sağ / MAR/legna köşe takımı kasa sol |
| TESTM | Marangozhane | 22 | 260 | MAR/favela.pavia 2li berjer kasa(SIRT) / MAR/favela.pavia 2li berjer kasa(SIRT) kavak kesim / MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) / MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) kavak kesim / MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) / MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) kavak kesim / MAR/favela.pavia.bahama berjer kasa(SIRT) / MAR/favela.pavia.bahama berjer kasa(SIRT) kavak kesim / MAR/garcia köşe takımı kasa / MAR/garcia köşe takımı kasa duralit kavak kesim / MAR/garcia köşe takımı kol / MAR/garcia köşe takımı kol duralit MDF sunta kesim / MAR/garcia köşe takımı kol kavak kesim / MAR/garcia köşe takımı sırt / MAR/garcia köşe takımı sırt duralit MDF sunta kesim / MAR/garcia köşe takımı sırt kavak kesim / MAR/legna kanepe kol / MAR/legna kanepe kol duralit kesim / MAR/legna kanepe kol kavak kesim / MAR/legna köşe takımı kasa kavak kesim / MAR/legna köşe takımı kasa sağ / MAR/legna köşe takımı sırt MDF zımpara tırnaklı |
| caner | Paketleme | 19 | 219 | PAK/akıllı sehpa zem ester wolf 01 beyaz / PAK/akıllı sehpa zem ester wolf 05 sütlü kahve / PAK/akıllı sehpa zem ester wolf 19 gri / PAK/akıllı sehpa zem irina wolf 05 sütlü kahve / PAK/bench zem bueno hanedan gri / PAK/bench zem marin sandıklı punch altıgen welsoft / PAK/bench zem marin welsoft beyaz / PAK/berjer zem meyra sallanır interno krem / PAK/berjer zem tosca wolf 05 sütlü kahve / PAK/kanepe zem legna zeugma keten krem / PAK/kanepe zem leones wolf 01 beyaz / PAK/köşe takımı zem legna zeugma keten gri sağ / PAK/köşe takımı zem legna zeugma keten gri sol / PAK/köşe takımı zem legna zeugma keten krem sol / PAK/puf zem chester babyface bf610 gri altın elkamet / PAK/puf zem gigi peluş beyaz altın ayak / PAK/puf zem lines wolf 05 sütlü kahve  / PAK/puf zem lines wolf 19 gri  / PAK/puf zem longline babyface bf910 kırık beyaz altın elkamet |
| Ramazan Kömreli | Marangozhane | 5 | 205 | MAR/favela 3lü berjer kasa(SIRT) / MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) / MAR/garcia köşe takımı kol / MAR/garcia köşe takımı sırt / MAR/legna köşe takımı kasa sol |
| TESTY | YM Depo | 7 | 185 | YM/ayak zade bohem(legna) 19cm / YM/favela.pavia.alaves.bahama berjer kasa(OTURUM)MDF kesim / YM/favela.pavia.bahama kasa (SIRT) CNC kol bağlantı MDF / YM/garcia köşe takımı kasa MDF / YM/legna kanepe kol 8mm MDF / YM/legna kanepe kol 8mm MDF renkli / YM/legna köşe takımı sırt MDF |
| müşteba | Paketleme | 32 | 183 | PAK/berjer zem alaves 2li wolf 05 sütlü kahve / PAK/berjer zem alaves wolf 05 sütlü kahve / PAK/berjer zem alto zeugma keten krem / PAK/berjer zem doris wolf 01 beyaz / PAK/berjer zem favela 2li wolf 01 beyaz / PAK/berjer zem favela 2li wolf 05 sütlü kahve / PAK/berjer zem favela 2li wolf 19 gri / PAK/berjer zem favela 2li wolf hardal / PAK/berjer zem favela 3lü wolf 05 sütlü kahve / PAK/berjer zem favela 3lü wolf 19 gri / PAK/berjer zem favela 3lü wolf hardal / PAK/berjer zem favela(DEMONTE) wolf 01 beyaz / PAK/berjer zem favela(DEMONTE) wolf 19 gri / PAK/berjer zem favela(DEMONTE) wolf hardal / PAK/berjer zem lupa zeugma v143 krem / PAK/berjer zem meyra sallanır interno gri / PAK/berjer zem pavia (ceviz) wolf 05 sütlü kahve / PAK/berjer zem pavia wolf 01 beyaz / PAK/berjer zem pavia wolf 05 sütlü kahve / PAK/berjer zem rock sallanır hardal / PAK/berjer zem rock sallanır wolf 01 beyaz / PAK/berjer zem rock sallanır wolf 05 sütlü kahve / PAK/berjer zem rock sallanır wolf 19 gri / PAK/ber |
| Yuşa Garouhi | YM Depo | 4 | 137 | bench zem marin sandıklı punch altıgen welsoft / YM/ayak zade bohem(legna) 19cm / YM/ayak zade kulaklı bohem(royal) 15cm |
| ergül | Marangozhane | 7 | 132 | MAR/alaves berjer kol / MAR/alaves berjer kol kesim / MAR/kol tosca berjer / MAR/kol tosca berjer gürgen kesim |
| ali akyürek | Döşemehane | 3 | 105 | DÖŞ/bench zem marin sandıklı punch altıgen welsoft / DÖŞ/bench zem marin welsoft beyaz / DÖŞ/puf zem longline babyface bf910 kırık beyaz altın elkamet |
| fatma | Terzihane | 4 | 95 | TER/bench zem marin sandıklı punch altıgen welsoft / TER/berjer zem alaves 2li wolf 05 sütlü kahve / TER/berjer zem alaves wolf 05 sütlü kahve / TER/berjer zem favela.pavia wolf 01 beyaz |
| Kemal Akbulut | Marangozhane | 2 | 89 | MAR/favela.pavia.alaves destek ayak |
| abdullah kasimi | Döşemehane | 12 | 59 | DÖŞ/berjer zem alaves 2li wolf 05 sütlü kahve / DÖŞ/berjer zem alaves wolf 05 sütlü kahve / DÖŞ/berjer zem alto zeugma keten krem / DÖŞ/berjer zem favela 3lü wolf 05 sütlü kahve / DÖŞ/berjer zem favela(DEMONTE) wolf 19 gri / DÖŞ/berjer zem favela(DEMONTE) wolf hardal / DÖŞ/berjer zem pavia 2li wolf 05 sütlü kahve / DÖŞ/berjer zem pavia wolf hardal / DÖŞ/puf zem pavia wolf 05 sütlü kahve |
| deniz | Kesimhane | 3 | 45 | KES/bench zem marin sandıklı punch altıgen welsoft / KES/berjer zem alaves 2li wolf 05 sütlü kahve / KES/berjer zem alaves wolf 05 sütlü kahve |
| Selime Yahşi | Terzihane | 2 | 40 | TER/berjer zem alaves 2li wolf 05 sütlü kahve / TER/berjer zem alaves wolf 05 sütlü kahve |
| Kemal Akbulut | Döşemehane | 3 | 35 | DÖŞ/köşe takımı zem legna zeugma keten gri sol / DÖŞ/puf zem longline babyface bf910 kırık beyaz altın elkamet |
| ali ihsan yılmaz | Döşemehane | 5 | 24 | DÖŞ/berjer zem favela 2li wolf 19 gri / DÖŞ/berjer zem favela 2li wolf hardal / DÖŞ/köşe takımı zem garcia wolf 19 gri / DÖŞ/köşe takımı zem legna zeugma keten gri sağ / DÖŞ/köşe takımı zem legna zeugma keten krem sağ |
| emin | Marangozhane | 7 | 16 | MAR/garcia köşe takımı kasa duralit kavak kesim / MAR/garcia köşe takımı kol duralit MDF sunta kesim / MAR/garcia köşe takımı kol kavak kesim / MAR/garcia köşe takımı sırt duralit MDF sunta kesim / MAR/garcia köşe takımı sırt kavak kesim / MAR/meyra berjer sallanır kasa |
| mehmetB | Demirhane | 1 | 8 | DEM/lupa berjer 60cm profil |
| TESTP | Paketleme | 1 | 8 | PAK/köşe takımı zem legna zeugma keten krem sağ |
| TESTT | Terzihane | 1 | 4 | TER/köşe takımı zem legna zeugma keten krem |
| TESTK | Kesimhane | 1 | 4 | KES/köşe takımı zem legna zeugma keten krem |
| Şadi Raslan | Döşemehane | 2 | 3 | DÖŞ/puf zem chester babyface bf610 gri altın elkamet / DÖŞ/süngerli kanepe zem meyra interno gri |
| Ramazan Kömreli | Paketleme | 1 | 1 | PAK/kanepe zem legna zeugma keten krem |


## İş Emri Geçmişi ve Kaynak Olaylar
| İşlem tipi | Satır | Adet | İlk | Son |
|---|---|---|---|---|
| Verildi | 614 | 1.212 | 2026-03-08 14:50:31 | 2026-04-17 00:33:59 |
| IptalEdildi | 289 | 611 | 2026-03-11 17:00:50 | 2026-04-20 15:44:48 |
| UretimRezerveEdildi | 47 | 53 | 2026-03-14 00:33:49 | 2026-04-12 20:21:50 |
| IsEmriVerildi | 8 | 11 | 2026-04-18 13:51:01 | 2026-04-25 02:07:10 |
| UretimRezerveIptal | 2 | 2 | 2026-04-01 17:20:13 | 2026-04-01 17:20:16 |


### Work Order Center Event Dağılımı
| Event | Grup | Ekran | Aksiyon | Adet | İlk | Son |
|---|---|---|---|---|---|---|
| state_seeded | system | Is Emri Merkezi Backfill | Siparis Durumunu Tohumla | 895 | 2026-03-29 23:13:23 | 2026-04-20 00:46:47 |
| state_seeded | system | Is Emri Merkezi Backfill | Is Emri Durumunu Tohumla | 842 | 2026-04-20 16:45:24 | 2026-04-20 16:45:26 |
| work_order_created_single | create | Legacy Is Emri Gecmisi | Verildi | 614 | 2026-03-08 14:50:31 | 2026-04-17 00:33:59 |
| work_order_cancelled | cancel | Legacy Is Emri Gecmisi | IptalEdildi | 289 | 2026-03-11 17:00:50 | 2026-04-20 15:44:48 |
| legacy_uretimrezerveedildi | system | Legacy Is Emri Gecmisi | UretimRezerveEdildi | 47 | 2026-03-14 00:33:49 | 2026-04-12 20:21:50 |
| work_order_created_single | create | Legacy Is Emri Gecmisi | IsEmriVerildi | 7 | 2026-04-18 13:51:01 | 2026-04-20 15:34:46 |
| legacy_uretimrezerveiptal | system | Legacy Is Emri Gecmisi | UretimRezerveIptal | 2 | 2026-04-01 17:20:13 | 2026-04-01 17:20:16 |
| work_order_created_single | create | Siparis Yonetimi | Is Emri Ver | 1 | 2026-04-25 02:07:10 | 2026-04-25 02:07:10 |
| stock_deducted | stock | Siparis Yonetimi | Stoktan Dus | 1 | 2026-04-23 02:00:43 | 2026-04-23 02:00:43 |


### En Son İş Emri Hareketleri
| Tarih | Tip | Satır | Sipariş | Görev | Ürün | Adet | Müşteri |
|---|---|---|---|---|---|---|---|
| 2026-04-25 02:07:10 | IsEmriVerildi | 5955 | 1091784954-A | 8970 | Rock Bohem Sallanır Berjer, Tarçın/Hardal | 1 | Helin Taylan |
| 2026-04-20 15:44:48 | IptalEdildi | 5979 | STOK-20260420-2765 | - | MAR/rock.kalina berjer gürgen sallanma ayağı | 1 | ÖZEL ÜRETİM (SERBEST) |
| 2026-04-20 15:44:26 | IptalEdildi | 5979 | STOK-20260420-2765 | 8968 | MAR/rock.kalina berjer gürgen sallanma ayağı | 1 | ÖZEL ÜRETİM (SERBEST) |
| 2026-04-20 15:34:46 | IsEmriVerildi | 5980 | STOK-20260420-8041 | 8969 | MAR/rock.kalina berjer gürgen sallanma ayağı | 1 | SERBEST STOK ÜRETİMİ |
| 2026-04-20 15:32:23 | IsEmriVerildi | 5979 | STOK-20260420-2765 | 8968 | MAR/rock.kalina berjer gürgen sallanma ayağı | 1 | SERBEST STOK ÜRETİMİ |
| 2026-04-20 10:54:44 | IsEmriVerildi | 5978 | STOK-20260420-3341 | 8967 | MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | 2 | SERBEST STOK ÜRETİMİ |
| 2026-04-20 00:48:35 | IsEmriVerildi | 5977 | STOK-20260420-2934 | 8966 | bench zem marin sandıklı punch altıgen welsoft | 3 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-19 03:36:51 | IptalEdildi | 5963 | STOK-20260418-4562 | - | SMOKE_ORDER_20260418_135101 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-19 03:36:51 | IptalEdildi | 5968 | STOK-20260418-2609 | - | SMOKE_ORDER_20260418_135109 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-19 03:36:51 | IptalEdildi | 5973 | STOK-20260418-1746 | - | SMOKE_ORDER_20260418_135117 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:17 | IptalEdildi | 5973 | STOK-20260418-1746 | 8967 | SMOKE_ORDER_20260418_135117 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:17 | IsEmriVerildi | 5973 | STOK-20260418-1746 | 8967 | SMOKE_ORDER_20260418_135117 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:09 | IptalEdildi | 5968 | STOK-20260418-2609 | 8967 | SMOKE_ORDER_20260418_135109 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:09 | IsEmriVerildi | 5968 | STOK-20260418-2609 | 8967 | SMOKE_ORDER_20260418_135109 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:01 | IptalEdildi | 5963 | STOK-20260418-4562 | 8967 | SMOKE_ORDER_20260418_135101 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-18 13:51:01 | IsEmriVerildi | 5963 | STOK-20260418-4562 | 8967 | SMOKE_ORDER_20260418_135101 | 1 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-17 00:33:59 | Verildi | 5722 | 11131898478 | 29633 | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 1 | zeliha mutlu |
| 2026-04-17 00:33:58 | Verildi | 5742 | 11134572233 | 29633 | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 1 | ilke turgut |
| 2026-04-17 00:33:57 | Verildi | 5732 | 11136864774 | 29633 | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 1 | hanife kilci |
| 2026-04-17 00:33:57 | Verildi | 5733 | 11136976848 | 29631 | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 1 | Mustafa ÖZKAN |
| 2026-04-17 00:33:57 | Verildi | 5836 | 1091137064-A | 29631 | Favela Bohem Üçlü Berjer Natürel Ahşap, Sütlü Kahve | 1 | BEDRİ AYDİN |
| 2026-04-15 21:18:53 | Verildi | 5849 | STOK-20260415-6432 | 29561 | Marin Sandıklı Puf, Beyaz/Gri Punch Desenli | 4 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-15 21:18:53 | Verildi | 5773 | PRA-8142603160 | 29561 | Marin Sandıklı Puf, Beyaz/Gri Punch Desenli | 1 | Vivi Seremetilou STEWE SHOP |
| 2026-04-15 21:14:24 | Verildi | 5848 | STOK-20260415-9789 | 29556 | Marin Puf, Beyaz Welsoft Kumaş | 37 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-15 21:14:24 | Verildi | 5768 | 11137618719 | 29555 | Marin Puf, Beyaz Welsoft Kumaş | 1 | atakan sekreter |
| 2026-04-15 21:14:24 | Verildi | 5771 | 11138183307 | 29555 | Marin Puf, Beyaz Welsoft Kumaş | 1 | Şeyma Kara |
| 2026-04-15 21:14:24 | Verildi | 5774 | 11138744600 | 29555 | Marin Puf, Beyaz Welsoft Kumaş | 1 | Cansu Asena Sarı |
| 2026-04-13 22:35:31 | Verildi | 5601 | 11123798907 | 29484 | Royal Bohem Berjer, Açık Krem | 1 | ümran gündüz |
| 2026-04-12 21:47:28 | Verildi | 5634 | STOK-20260412-4860 | 29398 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 4 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-12 21:47:28 | Verildi | 5614 | 11108864985 | 29395 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Tuba keskin |
| 2026-04-12 21:47:28 | Verildi | 5616 | 11116768599 | 29395 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | İsmail Köroğlu |
| 2026-04-12 21:47:28 | Verildi | 5557 | 11117024073 | 29395 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebubekir kartal |
| 2026-04-12 21:47:28 | Verildi | 5558 | 11117271519 | 29395 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebru Başboğa |
| 2026-04-12 21:47:28 | Verildi | 5617 | 11122709075 | 29384 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ali Osman gumba |
| 2026-04-12 21:47:28 | Verildi | 5619 | 11123246534 | 29384 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ülker Özekinci |
| 2026-04-12 21:47:13 | IptalEdildi | 5614 | 11108864985 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Tuba keskin |
| 2026-04-12 21:47:13 | IptalEdildi | 5616 | 11116768599 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | İsmail Köroğlu |
| 2026-04-12 21:47:13 | IptalEdildi | 5557 | 11117024073 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebubekir kartal |
| 2026-04-12 21:47:13 | IptalEdildi | 5558 | 11117271519 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebru Başboğa |
| 2026-04-12 21:47:13 | IptalEdildi | 5617 | 11122709075 | 29369 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ali Osman gumba |
| 2026-04-12 21:47:13 | IptalEdildi | 5619 | 11123246534 | 29369 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ülker Özekinci |
| 2026-04-12 21:46:20 | Verildi | 5633 | STOK-20260412-7290 | 29383 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 10 | ÖZEL ÜRETİM (Stok İlavesi) |
| 2026-04-12 21:46:20 | Verildi | 5614 | 11108864985 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Tuba keskin |
| 2026-04-12 21:46:20 | Verildi | 5616 | 11116768599 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | İsmail Köroğlu |
| 2026-04-12 21:46:20 | Verildi | 5557 | 11117024073 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebubekir kartal |
| 2026-04-12 21:46:19 | Verildi | 5558 | 11117271519 | 29380 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ebru Başboğa |
| 2026-04-12 21:46:19 | Verildi | 5617 | 11122709075 | 29369 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ali Osman gumba |
| 2026-04-12 21:46:19 | Verildi | 5619 | 11123246534 | 29369 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ülker Özekinci |
| 2026-04-12 21:44:18 | IptalEdildi | 5619 | 11123246534 | 29356 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ülker Özekinci |
| 2026-04-12 21:44:18 | IptalEdildi | 5617 | 11122709075 | 29356 | Legna Bohem Köşe Takımı, Açık Krem SAG KÖŞE | 1 | Ali Osman gumba |


## Kırmızı Bayraklar / Veri Tutarsızlıkları
| Bulgu | Satır | Adet/etki |
|---|---|---|
| Onay=true ama BekleyenAdet>0 | 177 | 2.688 |
| Onay=false/0 ama BekleyenAdet<=0 | 19 | 0 |
| GİED bağlı ama özel üretim iş emri almamış | 25 | 26 |
| Açık üretim ama IsEmriTarihi yok | 44 | 49 |
| IsEmriVerildi ama izli havuz/personel satırı yok | 43 | 218 |
| Sipariş izsiz havuz satırı | 24 | 175 |
| Sipariş izsiz aktif personel görevi | 305 | 6.588 |


### Onay=true Ama Bekleyen Kalan Örnekler
| Görev No | Personel | Bölüm | Ara ürün | Adet | Bekleyen | Onay | Tarih |
|---|---|---|---|---|---|---|---|
| 12721 | eren emir güneysu | YM Depo | YM/favela.pavia.alaves.bahama berjer kasa(OTURUM)MDF kesim | 0 | 300 | true | 08/04/2026 00:00 |
| 13253 | rıza | Marangozhane | MAR/basit kasa | 45 | 155 | true | 17/04/2026 00:00 |
| 12637 | adem çınar | Marangozhane | MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) | 0 | 150 | true | 08/04/2026 00:00 |
| 12636 | Ramazan Kömreli | Marangozhane | MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) | 0 | 150 | true | 08/04/2026 00:00 |
| 12365 | Ramazan Ejder | Marangozhane | MAR/kol pavia berjer | 14 | 108 | true | 07/04/2026 00:00 |
| 11552 | niyazi | Boyahane | BOY/alaves berjer kol(kahve pinotex) | 0 | 72 | true | 06/04/2026 00:00 |
| 12832 | eren emir güneysu | YM Depo | YM/8mm MDF 37cm çap daire(fine kafa.rennes kafa taban) | 0 | 70 | true | 13/04/2026 00:00 |
| 13320 | adem çınar | Marangozhane | MAR/legna kanepe kol | 0 | 60 | true | 18/04/2026 00:00 |
| 12768 | Mustafa Ali Acar | Ürün Depo | puf zem lines wolf 19 gri | 0 | 59 | true | 09/04/2026 00:00 |
| 11807 | hüdai | Boyahane | BOY/kol pavia berjer | 26 | 56 | true | 06/04/2026 00:00 |
| 12361 | hüdai | Boyahane | BOY/kol pavia berjer | 26 | 54 | true | 07/04/2026 00:00 |
| 12091 | niyazi | Boyahane | BOY/favela.pavia.alaves destek ayak | 0 | 49 | true | 03/04/2026 00:00 |
| 13201 | adem çınar | Marangozhane | MAR/marin kasa | 0 | 40 | true | 17/04/2026 00:34 |
| 13204 | Mustafa Ali Acar | Ürün Depo | bench zem marin welsoft beyaz | 0 | 40 | true | 17/04/2026 00:00 |
| 13203 | caner | Paketleme | PAK/bench zem marin welsoft beyaz | 0 | 40 | true | 17/04/2026 00:00 |
| 13002 | Mustafa Ali Acar | Ürün Depo | puf zem lines wolf 05 sütlü kahve | 0 | 40 | true | 14/04/2026 00:00 |
| 13202 | ali akyürek | Döşemehane | DÖŞ/bench zem marin welsoft beyaz | 0 | 40 | true | 17/04/2026 00:00 |
| 12833 | adem çınar | Marangozhane | MAR/rennes kasa | 0 | 35 | true | 14/04/2026 00:00 |
| 12831 | eren emir güneysu | YM Depo | YM/18mm sunta 37cm çap İÇİ BOŞ daire(rennes puf üst) | 0 | 35 | true | 13/04/2026 00:00 |
| 12712 | orhan | Marangozhane | MAR/favela 3lü berjer kasa (SIRT) kavak kesim | 0 | 30 | true | 08/04/2026 00:00 |
| 12714 | orhan | Marangozhane | MAR/favela 3lü berjer kasa (OTURUM) kavak kesim | 0 | 30 | true | 08/04/2026 00:00 |
| 12264 | rıza | Marangozhane | MAR/favela.pavia.alaves 2li berjer kasa (OTURUM) | 0 | 30 | true | 07/04/2026 00:00 |
| 12717 | rıza | Marangozhane | MAR/favela 3lü berjer kasa(OTURUM) | 0 | 30 | true | 09/04/2026 00:00 |
| 12716 | Ramazan Kömreli | Marangozhane | MAR/favela 3lü berjer kasa(SIRT) | 0 | 30 | true | 09/04/2026 00:00 |
| 13129 | TESTM | Marangozhane | MAR/favela.pavia.alaves.bahama berjer kasa(OTURUM) | 0 | 29 | true | 14/04/2026 00:00 |
| 12644 | caner | Paketleme | PAK/puf zem longline babyface bf910 kırık beyaz altın elkamet | 0 | 28 | true | 08/04/2026 00:00 |
| 12645 | Mustafa Ali Acar | Ürün Depo | puf zem longline babyface bf910 kırık beyaz altın elkamet | 0 | 28 | true | 08/04/2026 00:00 |
| 11572 | niyazi | Boyahane | BOY/favela.pavia.alaves destek ayak | 0 | 23 | true | 31/03/2026 00:00 |
| 11547 | ergül | Marangozhane | MAR/alaves berjer kol | 0 | 22 | true | 03/04/2026 00:00 |
| 12153 | hüdai | Boyahane | BOY/alto berjer ayak | 0 | 20 | true | 07/04/2026 00:00 |


### GİED Bağlı Ama Özel Üretim İş Emri Almamış Örnekler
| Satır | Sipariş | Ürün | Adet | Özel satır | Özel sipariş | Özel durum | Özel görev |
|---|---|---|---|---|---|---|---|
| 4966 | 11093957084 | Marin Puf, Beyaz Welsoft Kumaş | 1 | 4860 | STOK-20260328-8919 | UretimBekliyor | - |
| 4980 | 11091463154 | Marin Puf, Beyaz Welsoft Kumaş | 1 | 4860 | STOK-20260328-8919 | UretimBekliyor | - |
| 4986 | 11091747233 | Contes Bohem Sandıklı Orta Sehpa Puf/Bench, Sütlü Kahve | 1 | 4854 | STOK-20260328-2257 | UretimBekliyor | - |
| 4995 | 11092579774 | Marin Puf, Beyaz Welsoft Kumaş | 1 | 4860 | STOK-20260328-8919 | UretimBekliyor | - |
| 5033 | 11086747529 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5034 | 11086747392 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5037 | 4175374031 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5046 | 11090690283 | Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | 1 | 4862 | STOK-20260329-2799 | UretimBekliyor | - |
| 5054 | 11094893235 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5085 | 11093999336 | Favela Bohem Ikili Berjer Natürel Ahşap, Kırık Beyaz | 1 | 4862 | STOK-20260329-2799 | UretimBekliyor | - |
| 5133 | 4749017377 | Contes Bohem Sandıklı Orta Sehpa Puf/Bench, Kırık Beyaz | 1 | 5103 | STOK-20260330-4041 | UretimBekliyor | - |
| 5154 | 1090377476-D | Orso Bohem Sandıklı Puf, Kırık Beyaz | 1 | 5101 | STOK-20260330-2932 | UretimBekliyor | - |
| 5158 | 11095248800 | Marcel Bohem Berjer, Sütlü Kahve/Taba Deri Sırt | 2 | 4864 | STOK-20260329-6588 | UretimBekliyor | - |
| 5199 | 11099175391 | Marcel Bohem Berjer, Sütlü Kahve/Taba Deri Sırt | 1 | 4864 | STOK-20260329-6588 | UretimBekliyor | - |
| 5210 | 11101324703 | Şila Bench Beyaz Peluş - Gold Ayaklı 90 Cm | 1 | 5183 | STOK-20260401-899 | UretimBekliyor | - |
| 5216 | PRA-0510100205 | Favela Bohem İkili Berjer Natürel Ahşap, Kırık Beyaz | 1 | 4862 | STOK-20260329-2799 | UretimBekliyor | - |
| 5226 | 11099623524 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5234 | 11101400843 | Sevilla Bohem Üçlü Kanepe Natürel Ahşap, Sütlü Kahve | 1 | 4863 | STOK-20260329-9664 | UretimBekliyor | - |
| 5271 | 11103526631 | Louca Bench, Kırık Beyaz Gold Louca01 | 1 | 5181 | STOK-20260401-6064 | UretimBekliyor | - |
| 5285 | 11101740683 | Marcel Bohem Berjer, Sütlü Kahve/Taba Deri Sırt | 1 | 4864 | STOK-20260329-6588 | UretimBekliyor | - |
| 5344 | 4349987880 | Contes Bohem Sandıklı Orta Sehpa Puf/Bench, Gri | 1 | 5254 | STOK-20260401-3087 | UretimBekliyor | - |
| 5355 | 11109815669 | İriva Sandıklı Mekanizmalı Orta Sehpa/Bench, Gri | 1 | 5253 | STOK-20260401-9214 | UretimBekliyor | - |
| 5483 | 11118703512 | Jarvis Bohem Sandıklı Orta Sehpa Puf, Kırık Beyaz | 1 | 5297 | STOK-20260402-8176 | UretimBekliyor | - |
| 5497 | 4480470539 | Contes Bohem Sandıklı Orta Sehpa Puf/Bench, Gri | 1 | 5254 | STOK-20260401-3087 | UretimBekliyor | - |
| 5574 | 11126727688 | Louca Bench, Kırık Beyaz Gold Louca01 | 1 | 5181 | STOK-20260401-6064 | UretimBekliyor | - |


## Üretim Tamamlanınca Ne Oluyor?
Personel üretim girişi yaptığında `tbPersonelGorev.BekleyenAdet` düşer. Bekleyen sıfıra inerse `Onay=true` yazılır. Aynı anda ilgili `tbBolumAraStok` satırına üretilen ara ürün adedi eklenir. Eğer bağlı sipariş için havuzda ve personelde açık görev kalmadıysa sipariş durumu müşteri siparişinde `UretimdenKarsilaniyor`, özel üretim/stok ilavesi satırında `StokKarsilandi` yönüne gider. Bu son adım sevk/stoktan kapatma politikasına göre operasyon tarafından izlenmelidir.

## Beyin Fırtınası İçin Ana Sorular
1. `PasifDevamEden` 153 satır / 185 adet: Bunlar gerçekten üretimi devam edecek iptaller mi, yoksa temizlenmesi gereken kuyruk mu?
2. `Onay=true` ama bekleyen adet kalan 177 görev: Onay mı yanlış, bekleyen adet mi yanlış, yoksa eski sistemden gelen anlam farklı mı?
3. GİED bağlı 25 satırda özel üretim tarafı henüz iş emri almamış görünüyor. Bu rezervasyonlara izin vermeli miyiz?
4. Ürün bazlı raporda nihai adet 459 iken personel görevlerinde operasyon bekleyeni binler seviyesinde. Yönetim ekranında bu iki metrik ayrı mı gösterilmeli?
5. İz kolonları var ama aktif personel görevlerinin tamamı sipariş izsiz görünüyor. Geriye dönük backfill yapılmadan sipariş bazlı tam hikaye her kayıt için kurulamaz.
