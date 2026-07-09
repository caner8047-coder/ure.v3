<script>
(() => {
    const phraseReplacements = [
        ['Ürün Ağaçı', 'Ürün Ağacı'],
        ['ürün ağaçı', 'ürün ağacı'],
        ['Ağaçı', 'Ağacı'],
        ['ağaçı', 'ağacı'],
        ['Urun Agaci', 'Ürün Ağacı'],
        ['Urun agaci', 'Ürün ağacı'],
        ['Zem Uretim', 'Zem Üretim'],
        ['Siparis Yonetimi', 'Sipariş Yönetimi'],
        ['Stok Yonetimi', 'Stok Yönetimi'],
        ['Urun Yonetimi', 'Ürün Yönetimi'],
        ['Gorev Dagitimi', 'Görev Dağıtımı'],
        ['Gorev Raporlari', 'Görev Raporları'],
        ['Siparis ve Uretim', 'Sipariş ve Üretim'],
        ['Is Emri Havuzu', 'İş Emri Havuzu'],
        ['Is Emirleri', 'İş Emirleri'],
        ['Urun Yapisi', 'Ürün Yapısı'],
        ['Veritabani', 'Veritabanı'],
        ['Calisma Alani', 'Çalışma Alanı'],
        ['Alinabilir Isler', 'Alınabilir İşler'],
        ['Gorevlerim', 'Görevlerim'],
        ['Guvenlik', 'Güvenlik'],
        ['Aktif Gorevlerim', 'Aktif Görevlerim'],
        ['Tamamlanan Gorevler', 'Tamamlanan Görevler'],
        ['Gunluk siparis dosyasini yukle', 'Günlük sipariş dosyasını yükle'],
        ['Excel dosyasini surukleyip birakin', 'Excel dosyasını sürükleyip bırakın'],
        ['veya tiklayarak secin', 'veya tıklayarak seçin'],
        ['Gunluk siparis Excel dosyasi (.xlsx)', 'Günlük sipariş Excel dosyası (.xlsx)'],
        ['Toplam Siparis', 'Toplam Sipariş'],
        ['Uretim Bekliyor', 'Üretim Bekliyor'],
        ['Is Emri Verildi', 'İş Emri Verildi'],
        ['Stoktan Karsilandi', 'Stoktan Karşılandı'],
        ['Eslesmeyenler', 'Eşleşmeyenler'],
        ['Son Yukleme', 'Son Yükleme'],
        ['Son Guncelleme', 'Son Güncelleme'],
        ['Gorunen Kayit', 'Görünen Kayıt'],
        ['Uretilebilir Adet', 'Üretilebilir Adet'],
        ['Gorunum', 'Görünüm'],
        ['Detayli', 'Detaylı'],
        ['Detaysiz', 'Detaysız'],
        ['Tam Ozet', 'Tam Özet'],
        ['Ozet', 'Özet'],
        ['Bolume Gore', 'Bölüme Göre'],
        ['Urun ID Gore', 'Ürün ID Göre'],
        ['Ara Urune Gore', 'Ara Ürüne Göre'],
        ['Is emri akisi', 'İş emri akışı'],
        ['Veri girisi', 'Veri girişi'],
        ['Canli aktarim', 'Canlı aktarım'],
        ['Canli listeleme', 'Canlı listeleme'],
        ['Filtre Merkezi', 'Filtre Merkezi'],
        ['Siparis havuzunu daralt', 'Sipariş havuzunu daralt'],
        ['Henuz is emrine donusmemis siparisler', 'Henüz iş emrine dönüşmemiş siparişler'],
        ['Operasyona aktarilan satirlar', 'Operasyona aktarılan satırlar'],
        ['Direkt stok kullanilarak kapatilanlar', 'Direkt stok kullanılarak kapatılanlar'],
        ['Manuel karar bekleyen urun adlari', 'Manuel karar bekleyen ürün adları'],
        ['Sisteme son veri giris zamani.', 'Sisteme son veri giriş zamanı.'],
        ['Sisteme son veri giris zamani', 'Sisteme son veri giriş zamanı'],
        ['Nihai Urun', 'Nihai Ürün'],
        ['Ara Urun', 'Ara Ürün'],
        ['Urun ID', 'Ürün ID'],
        ['Urun ara', 'Ürün ara'],
        ['Urun listesi', 'Ürün listesi'],
        ['Mevcut kayitlar', 'Mevcut kayıtlar'],
        ['Toplam Kayit', 'Toplam Kayıt'],
        ['Kullanilabilir', 'Kullanılabilir'],
        ['Kritik Satir', 'Kritik Satır'],
        ['Tamponu Esitle', 'Tamponu Eşitle'],
        ['Tum Bolumler', 'Tüm Bölümler'],
        ['Tum stoklar', 'Tüm stoklar'],
        ['Tumu', 'Tümü'],
        ['Tum Urunler', 'Tüm Ürünler'],
        ['Tum urunler', 'Tüm ürünler'],
        ['Kayit bulunamadi.', 'Kayıt bulunamadı.'],
        ['Kayit bulunamadi', 'Kayıt bulunamadı'],
        ['Yukleniyor...', 'Yükleniyor...'],
        ['Yuklenemedi.', 'Yüklenemedi.'],
        ['Yuklenemedi', 'Yüklenemedi'],
        ['Kayit secin', 'Kayıt seçin'],
        ['Vazgec', 'Vazgeç'],
        ['Herseyi Sifirla', 'Her Şeyi Sıfırla'],
        ['Oto Eslestir', 'Oto Eşleştir'],
        ['Secimi Temizle', 'Seçimi Temizle'],
        ['Secilenlere Toplu Is Emri Ver', 'Seçilenlere Toplu İş Emri Ver'],
        ['Secilen Is Emirlerini Iptal Et', 'Seçilen İş Emirlerini İptal Et'],
        ['Secilenleri Stoktan Dus', 'Seçilenleri Stoktan Düş'],
        ['Urun secimi', 'Ürün seçimi'],
        ['Secim bekleniyor', 'Seçim bekleniyor'],
        ['Devam etmek icin once urun secimi yapin.', 'Devam etmek için önce ürün seçimi yapın.'],
        ['Is emri olusturulsun mu?', 'İş emri oluşturulsun mu?'],
        ['Evet, olustur', 'Evet, oluştur'],
        ['Menuyu ac', 'Menüyü aç'],
        ['Zem Personel', 'Zem Personel'],
    ].sort((a, b) => b[0].length - a[0].length);

    const regexReplacements = [
        [/\bGorevlerim\b/g, 'Görevlerim'],
        [/\bGorevler\b/g, 'Görevler'],
        [/\bGorev\b/g, 'Görev'],
        [/\bGorunen\b/g, 'Görünen'],
        [/\bGorunum\b/g, 'Görünüm'],
        [/\bDagitimi\b/g, 'Dağıtımı'],
        [/\bRaporlari\b/g, 'Raporları'],
        [/\bSiparisler\b/g, 'Siparişler'],
        [/\bSiparis\b/g, 'Sipariş'],
        [/\bsiparis\b/g, 'sipariş'],
        [/\bUretimde\b/g, 'Üretimde'],
        [/\bUretim\b/g, 'Üretim'],
        [/\bUretilebilir\b/g, 'Üretilebilir'],
        [/\buretim\b/g, 'üretim'],
        [/\bUrunler\b/g, 'Ürünler'],
        [/\bUrun\b/g, 'Ürün'],
        [/\burun\b/g, 'ürün'],
        [/\bBolumler\b/g, 'Bölümler'],
        [/\bBolum\b/g, 'Bölüm'],
        [/\bbolum\b/g, 'bölüm'],
        [/\bYonetimi\b/g, 'Yönetimi'],
        [/\bYonetim\b/g, 'Yönetim'],
        [/\bCesidi\b/g, 'Çeşidi'],
        [/\bcesidi\b/g, 'çeşidi'],
        [/\bGuncelleme\b/g, 'Güncelleme'],
        [/\bGuncelle\b/g, 'Güncelle'],
        [/\bGuncel\b/g, 'Güncel'],
        [/\bDuzenlenen\b/g, 'Düzenlenen'],
        [/\bDuzenleme\b/g, 'Düzenleme'],
        [/\bDuzenle\b/g, 'Düzenle'],
        [/\bduzenli\b/g, 'düzenli'],
        [/\bKayitlar\b/g, 'Kayıtlar'],
        [/\bKayit\b/g, 'Kayıt'],
        [/\bkayitlar\b/g, 'kayıtlar'],
        [/\bkayit\b/g, 'kayıt'],
        [/\bKaydi\b/g, 'Kaydı'],
        [/\bkaydi\b/g, 'kaydı'],
        [/\bYukleniyor\b/g, 'Yükleniyor'],
        [/\bYuklenemedi\b/g, 'Yüklenemedi'],
        [/\bYukleme\b/g, 'Yükleme'],
        [/\bYuklenen\b/g, 'Yüklenen'],
        [/\bYukle\b/g, 'Yükle'],
        [/\bYukari\b/g, 'Yukarı'],
        [/\bSifirlaniyor\b/g, 'Sıfırlanıyor'],
        [/\bSifirla\b/g, 'Sıfırla'],
        [/\bSifir\b/g, 'Sıfır'],
        [/\bSecilenlere\b/g, 'Seçilenlere'],
        [/\bSecilenleri\b/g, 'Seçilenleri'],
        [/\bSecilen\b/g, 'Seçilen'],
        [/\bSecili\b/g, 'Seçili'],
        [/\bSecimi\b/g, 'Seçimi'],
        [/\bSecim\b/g, 'Seçim'],
        [/\bSecin\b/g, 'Seçin'],
        [/\bsecin\b/g, 'seçin'],
        [/\bSec\b/g, 'Seç'],
        [/\bEslesmeyenler\b/g, 'Eşleşmeyenler'],
        [/\bEslesmemis\b/g, 'Eşleşmemiş'],
        [/\bEslesmis\b/g, 'Eşleşmiş'],
        [/\bEslesme\b/g, 'Eşleşme'],
        [/\bEslestir\b/g, 'Eşleştir'],
        [/\bEslestirme\b/g, 'Eşleştirme'],
        [/\beslestirme\b/g, 'eşleştirme'],
        [/\bKarsilanan\b/g, 'Karşılanan'],
        [/\bKarsilandi\b/g, 'Karşılandı'],
        [/\bKarsila\b/g, 'Karşıla'],
        [/\bHenuz\b/g, 'Henüz'],
        [/\bdonusmemis\b/g, 'dönüşmemiş'],
        [/\bdonusum\b/g, 'dönüşüm'],
        [/\bdonus\b/g, 'dönüş'],
        [/\bCanli\b/g, 'Canlı'],
        [/\bGirisi\b/g, 'Girişi'],
        [/\bgirisi\b/g, 'girişi'],
        [/\bGiris\b/g, 'Giriş'],
        [/\bDosyasi\b/g, 'Dosyası'],
        [/\bdosyasi\b/g, 'dosyası'],
        [/\bdosyasini\b/g, 'dosyasını'],
        [/\bGorsel\b/g, 'Görsel'],
        [/\bgorsel\b/g, 'görsel'],
        [/\bKapali\b/g, 'Kapalı'],
        [/\bkapali\b/g, 'kapalı'],
        [/\bHazir\b/g, 'Hazır'],
        [/\bhazir\b/g, 'hazır'],
        [/\bsurukleyip\b/g, 'sürükleyip'],
        [/\bbirakin\b/g, 'bırakın'],
        [/\btiklayarak\b/g, 'tıklayarak'],
        [/\bIptal\b/g, 'İptal'],
        [/\bIstatistik\b/g, 'İstatistik'],
        [/\bAlinabilir\b/g, 'Alınabilir'],
        [/\bGuvenlik\b/g, 'Güvenlik'],
        [/\bCalisma\b/g, 'Çalışma'],
        [/\bAktif Is Emirleri\b/g, 'Aktif İş Emirleri'],
        [/\bIs Emirlerini\b/g, 'İş Emirlerini'],
        [/\bIs Emirleri\b/g, 'İş Emirleri'],
        [/\bIs Emri\b/g, 'İş Emri'],
        [/\bis emrine\b/g, 'iş emrine'],
        [/\bis emri\b/g, 'iş emri'],
        [/\bIslem\b/g, 'İşlem'],
        [/\bislem\b/g, 'işlem'],
        [/\bBaslangic\b/g, 'Başlangıç'],
        [/\bBitis\b/g, 'Bitiş'],
        [/\baktarilan\b/g, 'aktarılan'],
        [/\bkapatilanlar\b/g, 'kapatılanlar'],
        [/\badlari\b/g, 'adları'],
        [/\bsatiri\b/g, 'satırı'],
        [/\bsatirlar\b/g, 'satırlar'],
        [/\bsatir\b/g, 'satır'],
        [/\bolustur\b/g, 'oluştur'],
        [/\bOlustur\b/g, 'Oluştur'],
        [/\bOzel\b/g, 'Özel'],
        [/\bozel\b/g, 'özel'],
        [/\bAciklama\b/g, 'Açıklama'],
        [/\baciklama\b/g, 'açıklama'],
        [/\bzamani\b/g, 'zamanı'],
        [/\bMenuyu\b/g, 'Menüyü'],
        [/(?<![a-zA-Z0-9_\p{L}])ac(?![a-zA-Z0-9_\p{L}])/gu, 'aç'],
    ];

    const attributeNames = ['placeholder', 'title', 'aria-label'];
    const uiTags = new Set(['A', 'BUTTON', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LABEL', 'LI', 'OPTION', 'P', 'SMALL', 'SPAN', 'STRONG', 'TH']);
    const blockedSelector = 'script, style, code, pre, textarea, [data-ui-no-normalize]';

    const normalizeUiText = (value) => {
        if (!value || typeof value !== 'string') return value;

        let normalized = value;

        for (const [from, to] of phraseReplacements) {
            if (normalized.includes(from)) {
                normalized = normalized.split(from).join(to);
            }
        }

        for (const [pattern, replacement] of regexReplacements) {
            normalized = normalized.replace(pattern, replacement);
        }

        return normalized;
    };

    const isEligibleElement = (element) => {
        if (!element || element.closest(blockedSelector)) return false;

        if (element.tagName === 'INPUT') {
            const type = (element.getAttribute('type') || 'text').toLowerCase();
            return ['button', 'submit', 'reset'].includes(type);
        }

        if (element.tagName === 'TD') {
            return element.colSpan > 1 || element.classList.contains('text-muted') || element.classList.contains('text-center');
        }

        return uiTags.has(element.tagName);
    };

    const normalizeTextNode = (node) => {
        const parent = node.parentElement;
        if (!parent || !isEligibleElement(parent)) return;

        const original = node.nodeValue;
        if (!original || !original.trim()) return;

        const normalized = normalizeUiText(original);
        if (normalized !== original) {
            node.nodeValue = normalized;
        }
    };

    const normalizeAttributes = (element) => {
        if (!element || !element.getAttribute || element.closest(blockedSelector)) return;

        for (const attribute of attributeNames) {
            const original = element.getAttribute(attribute);
            if (!original) continue;

            const normalized = normalizeUiText(original);
            if (normalized !== original) {
                element.setAttribute(attribute, normalized);
            }
        }

        if (element.tagName === 'INPUT') {
            const type = (element.getAttribute('type') || 'text').toLowerCase();
            if (['button', 'submit', 'reset'].includes(type)) {
                const original = element.value;
                const normalized = normalizeUiText(original);
                if (normalized !== original) {
                    element.value = normalized;
                }
            }
        }
    };

    const normalizeTree = (root) => {
        if (!root) return;

        if (root.nodeType === Node.TEXT_NODE) {
            normalizeTextNode(root);
            return;
        }

        if (root.nodeType !== Node.ELEMENT_NODE) return;

        normalizeAttributes(root);
        root.querySelectorAll('*').forEach(normalizeAttributes);

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let currentNode = walker.nextNode();
        while (currentNode) {
            normalizeTextNode(currentNode);
            currentNode = walker.nextNode();
        }
    };

    const bootstrap = () => {
        document.title = normalizeUiText(document.title);
        normalizeTree(document.body);

        const observer = new MutationObserver((mutations) => {
            document.title = normalizeUiText(document.title);

            for (const mutation of mutations) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach((node) => normalizeTree(node));
                    continue;
                }

                if (mutation.type === 'attributes') {
                    normalizeAttributes(mutation.target);
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [...attributeNames, 'value'],
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
    } else {
        bootstrap();
    }
})();
</script>
