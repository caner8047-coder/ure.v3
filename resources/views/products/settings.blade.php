@extends('layouts.app')

@section('title', 'Urun Ozellikleri Ayarlari')

@section('page-actions')
    <button id="updateButton" type="button" class="btn btn-primary" onclick="kaydet()">
        <i class="bi bi-save me-1"></i>Kaydet
    </button>
    <button type="button" class="btn btn-outline-secondary" onclick="exportToExcel()">
        <i class="bi bi-file-earmark-excel me-1"></i>Excel Aktar
    </button>
    <button type="button" class="btn btn-outline-secondary" onclick="openProductSettingsImport()">
        <i class="bi bi-upload me-1"></i>İçeri Aktar
    </button>
    <input type="file" id="fileImport" accept=".xlsx,.xls,.csv" style="display:none;" onchange="importFromExcel(this)" />
@endsection

@push('styles')
<style>
    /* ═══════════════════════════════════════
       Ürün Ayarları — Yeni Basit Tasarım
       ═══════════════════════════════════════ */

    .ps-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Bilgi Bandı ── */
    .ps-info-banner {
        background: linear-gradient(135deg, #f0fdfa, #e0f2fe);
        border: 1.5px solid #99f6e4;
        border-radius: 14px;
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .ps-info-icon {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(13,148,136,0.2);
    }

    .ps-info-text h2 {
        font-size: 1rem;
        font-weight: 700;
        margin: 0 0 4px;
    }

    .ps-info-text p {
        font-size: 0.82rem;
        color: var(--z-text-secondary);
        margin: 0;
        line-height: 1.5;
    }

    .ps-info-text p strong {
        color: var(--z-accent);
    }

    /* ── Ürün Seçici ── */
    .ps-product-picker {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
    }

    .ps-picker-label {
        font-size: 0.82rem;
        font-weight: 700;
        color: var(--z-text);
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ps-picker-label i {
        color: var(--z-accent);
        font-size: 1.1rem;
    }

    .ps-product-select {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid var(--z-border);
        border-radius: 12px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--z-text);
        transition: all 0.2s ease;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 8.5L1 3.5h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 40px;
    }

    .ps-product-select:focus {
        outline: none;
        border-color: var(--z-accent);
        background-color: var(--z-bg-card);
        box-shadow: 0 0 0 4px var(--z-accent-soft);
    }

    /* ── Ürün Kimlik Kartı ── */
    .ps-identity-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
        display: none;
    }

    .ps-identity-card.visible {
        display: block;
    }

    .ps-identity-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    .ps-identity-header h3 {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
    }

    .ps-identity-header i {
        font-size: 1.1rem;
        color: var(--z-accent);
    }

    .ps-identity-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .ps-id-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ps-id-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ps-id-input {
        padding: 10px 14px;
        border: 1.5px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.88rem;
        color: var(--z-text);
        transition: all 0.2s ease;
    }

    .ps-id-input:focus {
        outline: none;
        border-color: var(--z-accent);
        background: var(--z-bg-card);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    /* ── Parça Listesi ── */
    .ps-parts-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
        display: none;
    }

    .ps-parts-section.visible {
        display: block;
    }

    .ps-parts-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 18px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .ps-parts-title {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ps-parts-title h3 {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
    }

    .ps-parts-title i {
        color: var(--z-warning);
        font-size: 1.1rem;
    }

    .ps-parts-stats {
        display: flex;
        gap: 10px;
    }

    .ps-stat-chip {
        padding: 4px 12px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .ps-stat-chip.total {
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
    }

    .ps-stat-chip.missing {
        background: var(--z-danger-soft);
        color: var(--z-danger);
    }

    .ps-stat-chip.filled {
        background: var(--z-success-soft);
        color: var(--z-success);
    }

    /* Parça Kartları */
    .ps-parts-grid {
        display: grid;
        gap: 10px;
    }

    .ps-part-card {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 14px;
        padding: 16px 18px;
        border: 1.5px solid var(--z-border-light);
        border-radius: 12px;
        background: var(--z-bg-card);
        transition: all 0.2s ease;
    }

    .ps-part-card:hover {
        border-color: var(--z-accent);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    .ps-part-card.missing-perf {
        border-left: 4px solid var(--z-warning);
    }

    .ps-part-card.has-perf {
        border-left: 4px solid var(--z-success);
    }

    .ps-part-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        flex-shrink: 0;
    }

    .ps-part-icon.ham { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .ps-part-icon.ara { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .ps-part-icon.nihai { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
    .ps-part-icon.parca { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); }

    .ps-part-info {
        min-width: 0;
    }

    .ps-part-name {
        font-size: 0.88rem;
        font-weight: 600;
        margin: 0;
        line-height: 1.3;
    }

    .ps-part-meta {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
        flex-wrap: wrap;
    }

    .ps-part-meta span {
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .ps-part-meta .type-badge {
        padding: 2px 7px;
        border-radius: 5px;
        font-weight: 700;
        font-size: 0.64rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .ps-part-meta .type-badge.ham { background: #fef3c7; color: #b45309; }
    .ps-part-meta .type-badge.ara { background: #d1fae5; color: #047857; }
    .ps-part-meta .type-badge.nihai { background: #dbeafe; color: #1d4ed8; }
    .ps-part-meta .type-badge.parca { background: #f3e8ff; color: #7c3aed; }

    /* Performans Girişi */
    .ps-perf-area {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }

    .ps-perf-label {
        font-size: 0.64rem;
        font-weight: 700;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ps-perf-input-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ps-perf-input {
        width: 72px;
        padding: 8px 10px;
        border: 2px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 1rem;
        font-weight: 700;
        color: var(--z-text);
        text-align: center;
        transition: all 0.2s ease;
    }

    .ps-perf-input:focus {
        outline: none;
        border-color: var(--z-accent);
        background: var(--z-bg-card);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .ps-perf-input.empty {
        border-color: var(--z-warning);
        background: var(--z-warning-soft);
    }

    .ps-perf-input.filled {
        border-color: var(--z-success);
    }

    .ps-perf-unit {
        font-size: 0.72rem;
        color: var(--z-text-muted);
        font-weight: 600;
        white-space: nowrap;
    }

    .ps-qty-badge {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        margin-right: 6px;
    }

    /* ── Boş Durum ── */
    .ps-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 50px 20px;
        text-align: center;
        gap: 10px;
    }

    .ps-empty-icon {
        font-size: 2.2rem;
        margin-bottom: 4px;
    }

    .ps-empty h3 {
        font-size: 0.95rem;
        margin: 0;
    }

    .ps-empty p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 0;
        max-width: 360px;
    }

    /* ── Kaydet Animasyonu ── */
    @keyframes saveFlash {
        0% { background: var(--z-success-soft); }
        100% { background: var(--z-bg-card); }
    }

    .ps-part-card.saved {
        animation: saveFlash 0.6s ease;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .ps-identity-grid {
            grid-template-columns: 1fr;
        }

        .ps-part-card {
            grid-template-columns: auto 1fr;
            gap: 10px;
        }

        .ps-perf-area {
            grid-column: 1 / -1;
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding-top: 10px;
            border-top: 1px solid var(--z-border-light);
        }

        .ps-info-banner {
            flex-direction: column;
            text-align: center;
        }

        .ps-parts-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
@endpush

@section('content')
    <div class="ps-page">
        {{-- 1) Bilgi Bandı --}}
        <div class="ps-info-banner">
            <div class="ps-info-icon">💡</div>
            <div class="ps-info-text">
                <h2>Bu sayfa ne işe yarıyor?</h2>
                <p>
                    Burada ürünlerinizin alt parçaları için <strong>günlük üretim kapasitesi</strong> belirliyorsunuz.
                    Yani bir işçi 1 günde bu parçadan kaç tane yapabilir? Bu bilgi sayesinde sistem, üretim planlamasını ve personel performansını otomatik olarak hesaplar.
                    <strong>0 olan alanları doldurun, Kaydet'e basın.</strong>
                </p>
            </div>
        </div>

        {{-- 2) Ürün Seçici --}}
        <div class="ps-product-picker">
            <label class="ps-picker-label">
                <i class="bi bi-box-seam"></i>
                Hangi ürünün ayarlarını düzenlemek istiyorsunuz?
            </label>
            <select id="ddUrunID" class="ps-product-select" onchange="loadProductDetails()">
                <option value="">Yükleniyor...</option>
            </select>
        </div>

        {{-- 3) Ürün Kimlik Kartı --}}
        <div class="ps-identity-card" id="identityCard">
            <div class="ps-identity-header">
                <i class="bi bi-tag"></i>
                <h3>Ürün Kimlik Bilgileri</h3>
            </div>
            <div class="ps-identity-grid">
                <div class="ps-id-field">
                    <label class="ps-id-label">
                        <i class="bi bi-card-text"></i> Sistem Adı
                    </label>
                    <input type="text" id="tbSistemAdi" class="ps-id-input" placeholder="Ürünün sistemdeki adı...">
                </div>
                <div class="ps-id-field">
                    <label class="ps-id-label">
                        <i class="bi bi-upc"></i> Sistem Kodu
                    </label>
                    <input type="text" id="tbSistemKodu" class="ps-id-input" placeholder="Ürünün barkod/kodu...">
                </div>
            </div>
        </div>

        {{-- 4) Parça Listesi --}}
        <div class="ps-parts-section" id="partsSection">
            <div class="ps-parts-header">
                <div class="ps-parts-title">
                    <i class="bi bi-puzzle"></i>
                    <h3>Alt Parçalar ve Günlük Kapasiteler</h3>
                </div>
                <div class="ps-parts-stats" id="partsStats"></div>
            </div>

            <div class="ps-parts-grid" id="partsGrid">
                {{-- JS ile doldurulacak --}}
            </div>
        </div>

        {{-- 5) Boş Durum --}}
        <div class="ps-parts-section visible" id="emptyState">
            <div class="ps-empty">
                <div class="ps-empty-icon">📦</div>
                <h3>Ürün seçin</h3>
                <p>Yukarıdaki listeden düzenlemek istediğiniz ürünü seçtiğinizde, alt parçaları burada göreceksiniz.</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<script>
    let currentData = [];
    let currentUrunNo = null;

    document.addEventListener('DOMContentLoaded', function () {
        fetchInitialData();
    });

    function openProductSettingsImport() {
        const input = document.getElementById('fileImport');
        input.value = '';
        input.click();
    }

    // ── Ürün Listesini Yükle ──
    async function fetchInitialData() {
        try {
            const response = await fetch('/api/database/product-settings/lookups');
            const result = await response.json();
            if (result.success && result.urunler) {
                const select = document.getElementById('ddUrunID');
                select.innerHTML = '<option value="">-- Ürün seçin --</option>';
                result.urunler.forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.No;
                    opt.text = item.UrunID;
                    select.appendChild(opt);
                });

                if (result.urunler.length > 0) {
                    select.selectedIndex = 1;
                    loadProductDetails();
                }
            }
        } catch (error) {
            console.error('Yükleme hatası:', error);
            document.getElementById('ddUrunID').innerHTML = '<option value="">Yüklenemedi</option>';
        }
    }

    // ── Seçilen Ürünün Detaylarını Yükle ──
    async function loadProductDetails() {
        const urunNo = document.getElementById('ddUrunID').value;
        if (!urunNo) return;

        currentUrunNo = urunNo;

        try {
            const response = await fetch(`/api/database/product-settings/${urunNo}`);
            const result = await response.json();

            if (result.success) {
                document.getElementById('tbSistemAdi').value = result.sistemAdi || '';
                document.getElementById('tbSistemKodu').value = result.sistemKodu || '';
                document.getElementById('identityCard').classList.add('visible');

                currentData = result.tablo || [];
                renderParts(currentData);
            }
        } catch (error) {
            console.error('Detay yükleme hatası:', error);
        }
    }

    // ── Parça Kartlarını Render Et ──
    function renderParts(data) {
        const grid = document.getElementById('partsGrid');
        const section = document.getElementById('partsSection');
        const emptyState = document.getElementById('emptyState');

        if (!data || data.length === 0) {
            section.classList.remove('visible');
            emptyState.classList.add('visible');
            emptyState.querySelector('.ps-empty').innerHTML = `
                <div class="ps-empty-icon">📋</div>
                <h3>Bu ürünün alt parçası yok</h3>
                <p>Seçilen ürün için BOM (reçete) tanımı yapılmamış.</p>
            `;
            return;
        }

        emptyState.classList.remove('visible');
        section.classList.add('visible');

        // İstatistikler
        const total = data.length;
        const filled = data.filter(d => parseInt(d.Performans) > 0).length;
        const missing = total - filled;

        document.getElementById('partsStats').innerHTML = `
            <span class="ps-stat-chip total">
                <i class="bi bi-puzzle"></i> ${total} parça
            </span>
            ${filled > 0 ? `<span class="ps-stat-chip filled"><i class="bi bi-check-circle"></i> ${filled} tanımlı</span>` : ''}
            ${missing > 0 ? `<span class="ps-stat-chip missing"><i class="bi bi-exclamation-circle"></i> ${missing} eksik</span>` : ''}
        `;

        // Kartları oluştur
        const html = data.map((item, idx) => {
            const perf = parseInt(item.Performans) || 0;
            const isPerfMissing = perf === 0;
            const araUrunAdi = item.AraUrun || 'Bilinmeyen Parça';
            const typeClass = getPartTypeClass(araUrunAdi);
            const emoji = getPartEmoji(araUrunAdi);
            const typeName = getPartTypeName(araUrunAdi);

            return `
                <div class="ps-part-card ${isPerfMissing ? 'missing-perf' : 'has-perf'}" id="part-card-${idx}">
                    <div class="ps-part-icon ${typeClass}">${emoji}</div>
                    <div class="ps-part-info">
                        <h4 class="ps-part-name">${escapeHtml(araUrunAdi)}</h4>
                        <div class="ps-part-meta">
                            <span class="type-badge ${typeClass}">${typeName}</span>
                            <span>#${item.AraUrunNo}</span>
                        </div>
                    </div>
                    <div class="ps-perf-area">
                        <span class="ps-perf-label">Günlük kapasite</span>
                        <div class="ps-perf-input-wrap">
                            <input type="number"
                                   class="ps-perf-input ${isPerfMissing ? 'empty' : 'filled'}"
                                   id="perf-${idx}"
                                   data-ara-urun-no="${item.AraUrunNo}"
                                   data-index="${idx}"
                                   value="${perf}"
                                   min="0"
                                   placeholder="?"
                                   onchange="onPerfChange(this)"
                                   onfocus="this.select()">
                            <span class="ps-perf-unit">adet/gün</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        grid.innerHTML = html;
    }

    // ── Performans Değeri Değiştiğinde ──
    function onPerfChange(input) {
        const idx = parseInt(input.dataset.index);
        const val = parseInt(input.value) || 0;

        // Data'yı güncelle
        if (currentData[idx]) {
            currentData[idx].Performans = val;
        }

        // Görsel güncelle
        const card = document.getElementById(`part-card-${idx}`);
        if (val > 0) {
            input.classList.remove('empty');
            input.classList.add('filled');
            card.classList.remove('missing-perf');
            card.classList.add('has-perf');
        } else {
            input.classList.remove('filled');
            input.classList.add('empty');
            card.classList.remove('has-perf');
            card.classList.add('missing-perf');
        }

        // İstatistikleri güncelle
        updateStats();
    }

    function updateStats() {
        const total = currentData.length;
        const filled = currentData.filter(d => parseInt(d.Performans) > 0).length;
        const missing = total - filled;

        document.getElementById('partsStats').innerHTML = `
            <span class="ps-stat-chip total">
                <i class="bi bi-puzzle"></i> ${total} parça
            </span>
            ${filled > 0 ? `<span class="ps-stat-chip filled"><i class="bi bi-check-circle"></i> ${filled} tanımlı</span>` : ''}
            ${missing > 0 ? `<span class="ps-stat-chip missing"><i class="bi bi-exclamation-circle"></i> ${missing} eksik</span>` : ''}
        `;
    }

    // ── Kaydet ──
    async function kaydet() {
        if (!currentUrunNo || currentData.length === 0) {
            Swal.fire('Uyarı', 'Önce bir ürün seçin.', 'warning');
            return;
        }

        const updates = currentData.map(item => ({
            AraUrunNo: item.AraUrunNo,
            Performans: parseInt(document.querySelector(`[data-ara-urun-no="${item.AraUrunNo}"]`)?.value) || 0
        }));

        const sistemAdi = document.getElementById('tbSistemAdi').value;
        const sistemKodu = document.getElementById('tbSistemKodu').value;

        const btn = document.getElementById('updateButton');
        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Kaydediliyor...';

        Swal.fire({
            title: 'Kaydediliyor...',
            text: 'Lütfen bekleyin',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const response = await fetch('/api/database/product-settings/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    updates: updates,
                    urunNo: parseInt(currentUrunNo),
                    sistemAdi: sistemAdi,
                    sistemKodu: sistemKodu
                })
            });

            const result = await response.json();
            Swal.close();

            if (result && result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Kaydedildi! ✅',
                    text: 'Performans değerleri ve ürün bilgileri güncellendi.',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Kartlara kayıt animasyonu
                document.querySelectorAll('.ps-part-card').forEach(card => {
                    card.classList.add('saved');
                    setTimeout(() => card.classList.remove('saved'), 600);
                });
            } else {
                Swal.fire('Uyarı', result.message || 'Beklenmedik bir hata oluştu.', 'warning');
            }
        } catch (error) {
            Swal.close();
            Swal.fire('Hata', 'Sunucuyla bağlantı kurulamadı.', 'error');
            console.error('Hata:', error);
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }

    // ── Excel Dışa Aktar ──
    function exportToExcel() {
        if (typeof XLSX === 'undefined') {
            Swal.fire('Hata', 'Excel dışa aktarma kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.', 'error');
            return;
        }

        if (!currentData || currentData.length === 0) {
            Swal.fire('Uyarı', 'Tablo henüz yüklenmedi.', 'warning');
            return;
        }

        const headers = ['Ürün No', 'Ürün Adı', 'Sistem Adı', 'Sistem Kodu', 'Ara Ürün No', 'Ara Ürün Adı', 'Adet', 'Performans'];
        const exportData = [headers];

        currentData.forEach((item, idx) => {
            const perf = parseInt(document.querySelector(`[data-ara-urun-no="${item.AraUrunNo}"]`)?.value) || 0;
            exportData.push([
                item.No, item.UrunID, item.SistemAdi, item.SistemKodu,
                item.AraUrunNo, item.AraUrun, item.Adet, perf
            ]);
        });

        const ws = XLSX.utils.aoa_to_sheet(exportData);
        ws['!cols'] = [
            { wch: 10 }, { wch: 35 }, { wch: 30 }, { wch: 18 },
            { wch: 12 }, { wch: 45 }, { wch: 8 }, { wch: 12 }
        ];

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Ürün Özellikleri');

        const select = document.getElementById('ddUrunID');
        const urunAdi = select.options[select.selectedIndex].text || 'Ürün';
        const safeFileName = urunAdi.replace(/[^a-zA-Z0-9ğüşıöçĞÜŞİÖÇ\s]/g, '').replace(/\s+/g, '_').substring(0, 50);
        const tarih = new Date().toISOString().slice(0, 10);

        XLSX.writeFile(wb, safeFileName + '_' + tarih + '.xlsx');
        Swal.fire({
            icon: 'success',
            title: 'Aktarıldı',
            html: '<b>' + currentData.length + '</b> parça Excel dosyasına aktarıldı.',
            timer: 2500,
            showConfirmButton: false
        });
    }

    // ── Excel İçe Aktar ──
    function importFromExcel(input) {
        if (!input.files || !input.files[0]) return;
        if (typeof XLSX === 'undefined') {
            Swal.fire('Hata', 'Excel okuma kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.', 'error');
            input.value = '';
            return;
        }

        if (!currentData || currentData.length === 0) {
            Swal.fire('Uyarı', 'Önce bir ürün seçin.', 'warning');
            input.value = '';
            return;
        }

        const file = input.files[0];
        const reader = new FileReader();

        reader.onload = function (e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                const rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                if (rows.length < 2) {
                    Swal.fire('Uyarı', 'Excel dosyası boş veya sadece başlık içeriyor.', 'warning');
                    input.value = '';
                    return;
                }

                const headerRow = rows[0];
                const dataRows = rows.slice(1);

                let perfIndex = -1, araNoIndex = -1, sistemAdiIndex = -1, sistemKoduIndex = -1;
                for (let i = 0; i < headerRow.length; i++) {
                    const h = (headerRow[i] || '').toString().toLowerCase().trim();
                    if (h === 'performans') perfIndex = i;
                    if (h === 'ara ürün no' || h === 'araurunno') araNoIndex = i;
                    if (h === 'sistem adı' || h === 'sistemadi' || h === 'sistem adi') sistemAdiIndex = i;
                    if (h === 'sistem kodu' || h === 'sistemkodu') sistemKoduIndex = i;
                }

                if (perfIndex === -1) perfIndex = 7;
                if (araNoIndex === -1) araNoIndex = 4;
                if (sistemAdiIndex === -1) sistemAdiIndex = 2;
                if (sistemKoduIndex === -1) sistemKoduIndex = 3;

                let sistemAdiUpdated = false, sistemKoduUpdated = false;
                if (dataRows.length > 0 && dataRows[0]) {
                    const firstRow = dataRows[0];
                    if (firstRow[sistemAdiIndex] !== undefined && firstRow[sistemAdiIndex] !== null) {
                        document.getElementById('tbSistemAdi').value = firstRow[sistemAdiIndex].toString().trim();
                        sistemAdiUpdated = true;
                    }
                    if (firstRow[sistemKoduIndex] !== undefined && firstRow[sistemKoduIndex] !== null) {
                        document.getElementById('tbSistemKodu').value = firstRow[sistemKoduIndex].toString().trim();
                        sistemKoduUpdated = true;
                    }
                }

                // Performans map oluştur
                const perfMap = {};
                for (let i = 0; i < dataRows.length; i++) {
                    const row = dataRows[i];
                    if (row && row.length > perfIndex) {
                        const araNo = (row[araNoIndex] || '').toString().trim();
                        const perf = row[perfIndex];
                        if (araNo !== '' && perf !== undefined && perf !== null && perf !== '') {
                            perfMap[araNo] = parseInt(perf) || 0;
                        }
                    }
                }

                // Kartlardaki inputları güncelle
                let updatedCount = 0;
                currentData.forEach((item, idx) => {
                    const araNo = (item.AraUrunNo || '').toString().trim();
                    if (perfMap.hasOwnProperty(araNo)) {
                        const inp = document.querySelector(`[data-ara-urun-no="${araNo}"]`);
                        if (inp) {
                            inp.value = perfMap[araNo];
                            onPerfChange(inp);
                            updatedCount++;
                        }
                        item.Performans = perfMap[araNo];
                    }
                });

                let resultHtml = '<b>' + updatedCount + '</b> parçanın kapasitesi güncellendi.';
                if (sistemAdiUpdated) resultHtml += '<br>Sistem Adı güncellendi.';
                if (sistemKoduUpdated) resultHtml += '<br>Sistem Kodu güncellendi.';
                resultHtml += '<br><br><small>Değişiklikleri kalıcı yapmak için "Kaydet" butonuna basın.</small>';

                Swal.fire({ icon: 'success', title: 'İçeri Aktarıldı', html: resultHtml, confirmButtonColor: '#0d9488' });
            } catch (err) {
                Swal.fire('Hata', 'Dosya okunurken hata oluştu: ' + err.message, 'error');
                console.error('Import hatası:', err);
            }

            input.value = '';
        };

        reader.readAsArrayBuffer(file);
    }

    // ── Yardımcı Fonksiyonlar ──
    function getPartTypeClass(name) {
        const n = (name || '').toUpperCase();
        if (n.startsWith('KES/') || n.startsWith('HAM/') || n.includes('HAM ')) return 'ham';
        if (n.startsWith('MAR/') || n.startsWith('YM/') || n.startsWith('TER/') || n.startsWith('DOŞ/') || n.startsWith('DÖŞ/') || n.startsWith('PAK/') || n.startsWith('BOY/')) return 'ara';
        if (n.includes('NİHAİ') || n.includes('NIHAI')) return 'nihai';
        return 'parca';
    }

    function getPartEmoji(name) {
        const n = (name || '').toUpperCase();
        if (n.startsWith('KES/') || n.startsWith('HAM/') || n.includes('HAM ')) return '🪵';
        if (n.startsWith('MAR/') || n.startsWith('YM/')) return '🔩';
        if (n.startsWith('TER/') || n.startsWith('DOŞ/') || n.startsWith('DÖŞ/')) return '🧵';
        if (n.startsWith('PAK/')) return '📦';
        if (n.startsWith('BOY/')) return '🎨';
        if (n.includes('NİHAİ') || n.includes('NIHAI')) return '🏭';
        return '🔧';
    }

    function getPartTypeName(name) {
        const n = (name || '').toUpperCase();
        if (n.startsWith('KES/')) return 'Kesim';
        if (n.startsWith('HAM/')) return 'Ham Madde';
        if (n.startsWith('MAR/')) return 'Marangoz';
        if (n.startsWith('YM/')) return 'Yarı Mamül';
        if (n.startsWith('TER/')) return 'Tertip';
        if (n.startsWith('DOŞ/') || n.startsWith('DÖŞ/')) return 'Döşeme';
        if (n.startsWith('PAK/')) return 'Paketleme';
        if (n.startsWith('BOY/')) return 'Boyama';
        if (n.includes('NİHAİ') || n.includes('NIHAI')) return 'Nihai Ürün';
        return 'Parça';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
</script>
@endpush
