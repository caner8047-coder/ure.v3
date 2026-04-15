# P0 Cutover Plan

Bu belge, `6 Nisan 2026` itibariyla Laravel portunu fabrikanin kullandigi ASP.NET referans sistemine yaklastirmak icin acil canliya gecis planidir.

## Hedef

- Canli veri kaynagini tek bir legacy model etrafinda toplamak
- Auth, personel, is emri ve gorev akislarini ASP.NET ile ayni davranisa yaklastirmak
- Go-live oncesi kaldirilmasi gereken P0 riskleri somut gorevlere cevirmek

## P0 Is Akislari

1. Auth ve personel kaynagini `tbPersonel` uzerine tekillestir
- `users` tablosunu prod auth akisinin disina cikar
- `BolumAdiNo = 0` admin kuralini geri getir
- Sifre degisimi ve sifremi unuttum akislarini legacy SHA256 uyumlu tut

2. Legacy semayi birebir uyumlu hale getir
- `tbBolumHavuz.Aciklama`
- `tbBolumHavuz.GorevBaslangicSaati`
- `tbGorevler.Performans`
- `tbPersonelGorev.GorevBaslamaTarihi`
- `tbGorevler.No` icin manuel sira uretimi

3. Is emri uretimini tek cekirdek servise bagla
- Tekli manuel is emri
- Siparisten is emri
- Toplu is emri
- Hepsi ayni BOM ve legacy gorev yazma mantigini kullansin

4. Gorev atama ve personel panelini canliya hazirla
- Havuzdan personele gorev atama
- Personelin havuzdan gorev alma akisi
- Uretim tamamlama
- Tamamlanan gorev ve rapor ekranlari
- `Onay` alaninda hem numeric hem string legacy veriyi tolere et

5. Go-live oncesi dogrulama matrisi
- Admin login
- Personel login
- Siparis yukleme
- Eslestirme
- Tekli is emri
- Toplu is emri
- Havuzdan gorev atama
- Personelin gorev almasi
- Uretim tamamlama
- Tampon dusumu / geri yukleme

## Bu Turda Tamamlanan Isler

- [x] Repo icine P0 planinin alinmasi
- [x] `tbPersonel` tabanli auth ve legacy cookie uyumu
- [x] Legacy `.aspx` URL aliaslari
- [x] Legacy uyumluluk migration'i ve gorev yazma katmani
- [x] Work order servislerinin legacy recursion ile tekillestirilmesi
- [x] `Nihai`, `Ara Mamül`, `Ham Madde` manuel is emri destegi
- [x] Docker icinden `cutover_smoke.php` dogrulamasi
- [x] Docker icinden `order_parity_smoke.php` dogrulamasi
- [x] Uygulama timezone varsayilaninin `Europe/Istanbul` yapilmasi

## Bu Gece Kapanmasi Gerekenler

- [ ] Production `.env` degerlerinin uygulanmasi
- [ ] `APP_ENV=production` ve `APP_DEBUG=false` ile yeniden dogrulama
- [ ] Canli veri orphan kayitlarinin nedeninin netlestirilmesi
- [ ] En az 1 admin ve 1 personel ile UAT onayi
- [ ] ASP.NET rollback adimlarinin yazili ve denenmis hale getirilmesi

## Go / No-Go Kuralı

Asagidaki 4 kosul saglanmadan tam cutover yapilmayacak:

- P0 blokajlar kapali olacak
- Test matrisi satir satir gececek
- En az bir admin ve bir personel UAT onayi verecek
- ASP.NET rollback plani calisiyor olacak
- `legacy_data_audit.php` ciktisindaki veri farklari anlasilmis ve kabul edilmis olacak
