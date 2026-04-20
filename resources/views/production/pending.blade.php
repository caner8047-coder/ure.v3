@extends('layouts.app')

@section('title', 'Uretim Bekleyen Ozet')

@section('page-actions')
    <button class="btn btn-primary btn-sm" onclick="loadSummary()">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<style>
    /* ═══════════════════════════════════════
       Üretim Bekleyen Özet — Yeni Tasarım
       ═══════════════════════════════════════ */

    .ub-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Bilgi Bandı ── */
    .ub-info-banner {
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        border: 1.5px solid #93c5fd;
        border-radius: 14px;
        padding: 18px 22px;
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .ub-info-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #3b82f6, #6366f1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(59,130,246,0.2);
    }

    .ub-info-text h2 {
        font-size: 0.92rem;
        font-weight: 700;
        margin: 0 0 3px;
    }

    .ub-info-text p {
        font-size: 0.78rem;
        color: var(--z-text-secondary);
        margin: 0;
        line-height: 1.5;
    }

    /* ── Özet Kartları ── */
    .ub-stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 14px;
    }

    .ub-stat-card {
        background: var(--z-bg-card);
        border: 1.5px solid var(--z-border-light);
        border-radius: 14px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: all 0.2s ease;
    }

    .ub-stat-card:hover {
        border-color: var(--z-accent);
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    }

    .ub-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .ub-stat-icon.products { background: linear-gradient(135deg, #e0f2fe, #bae6fd); }
    .ub-stat-icon.total { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); }
    .ub-stat-icon.matched { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .ub-stat-icon.unmatched { background: linear-gradient(135deg, #fee2e2, #fecaca); }

    .ub-stat-info {
        min-width: 0;
    }

    .ub-stat-label {
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin: 0 0 2px;
    }

    .ub-stat-value {
        font-size: 1.5rem;
        font-weight: 800;
        margin: 0;
        line-height: 1;
    }

    .ub-stat-value.matched { color: var(--z-success); }
    .ub-stat-value.unmatched { color: var(--z-danger); }

    /* ── Arama / Filtre ── */
    .ub-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ub-search-box {
        flex: 1;
        min-width: 200px;
        padding: 10px 14px 10px 38px;
        border: 1.5px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.85rem;
        color: var(--z-text);
        transition: all 0.2s ease;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%239ca3af' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: 12px center;
    }

    .ub-search-box:focus {
        outline: none;
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .ub-filter-select {
        padding: 10px 14px;
        border: 1.5px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.82rem;
        color: var(--z-text);
        cursor: pointer;
        min-width: 160px;
    }

    .ub-tab-btns {
        display: flex;
        gap: 4px;
        background: var(--z-bg-soft);
        padding: 3px;
        border-radius: 10px;
    }

    .ub-tab-btn {
        padding: 7px 14px;
        border: none;
        border-radius: 8px;
        background: transparent;
        font-family: inherit;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--z-text-muted);
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .ub-tab-btn.active {
        background: var(--z-bg-card);
        color: var(--z-text);
        box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }

    .ub-tab-btn:hover:not(.active) {
        color: var(--z-text);
    }

    /* ── Ürün Listesi ── */
    .ub-list-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        overflow: hidden;
    }

    .ub-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--z-border-light);
    }

    .ub-list-header h3 {
        font-size: 0.9rem;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ub-list-count {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 6px;
        background: var(--z-bg-soft);
        color: var(--z-text-muted);
    }

    .ub-list-grid {
        display: flex;
        flex-direction: column;
    }

    /* ── Ürün Kartı ── */
    .ub-product-card {
        display: grid;
        grid-template-columns: 40px 1fr auto auto auto auto;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--z-border-light);
        transition: all 0.15s ease;
    }

    .ub-product-card:last-child {
        border-bottom: none;
    }

    .ub-product-card:hover {
        background: var(--z-bg-soft);
    }

    .ub-product-card.unmatched {
        border-left: 4px solid var(--z-warning);
    }

    .ub-product-card.matched {
        border-left: 4px solid var(--z-success);
    }

    .ub-product-num {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--z-bg-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--z-text-muted);
        flex-shrink: 0;
    }

    .ub-product-info {
        min-width: 0;
    }

    .ub-product-name {
        font-size: 0.85rem;
        font-weight: 600;
        margin: 0;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ub-product-system {
        font-size: 0.72rem;
        color: var(--z-text-muted);
        margin-top: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Veri Alanları */
    .ub-data-cell {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        padding: 0 8px;
        min-width: 70px;
    }

    .ub-data-label {
        font-size: 0.6rem;
        font-weight: 700;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }

    .ub-data-value {
        font-size: 1.05rem;
        font-weight: 800;
    }

    .ub-data-value.qty {
        color: var(--z-accent);
    }

    /* Kargo Tarihi */
    .ub-kargo-badge {
        padding: 5px 10px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .ub-kargo-badge.urgent {
        background: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }

    .ub-kargo-badge.normal {
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
    }

    .ub-kargo-badge.none {
        background: transparent;
        color: var(--z-text-muted);
        font-size: 0.72rem;
    }

    /* Eşleşme Badge'ı */
    .ub-match-badge {
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .ub-match-badge.yes {
        background: #d1fae5;
        color: #047857;
    }

    .ub-match-badge.no {
        background: #fef3c7;
        color: #b45309;
    }

    /* ── Sıralama Çubuğu ── */
    .ub-sort-bar {
        display: grid;
        grid-template-columns: 40px 1fr auto auto auto auto;
        align-items: center;
        gap: 14px;
        padding: 10px 20px;
        background: var(--z-bg-soft);
        border-bottom: 1px solid var(--z-border-light);
    }

    .ub-sort-btn {
        display: flex;
        align-items: center;
        gap: 4px;
        border: none;
        background: none;
        font-family: inherit;
        font-size: 0.68rem;
        font-weight: 700;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: all 0.15s;
        white-space: nowrap;
        justify-content: center;
    }

    .ub-sort-btn:hover {
        background: var(--z-bg-card);
        color: var(--z-text);
    }

    .ub-sort-btn.active {
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }

    .ub-sort-btn .sort-arrow {
        font-size: 0.72rem;
        opacity: 0.4;
    }

    .ub-sort-btn.active .sort-arrow {
        opacity: 1;
    }

    .ub-sort-spacer {
        min-width: 70px;
        text-align: center;
    }

    /* ── Boş Durum ── */
    .ub-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 50px 20px;
        text-align: center;
        gap: 10px;
    }

    .ub-empty-icon {
        font-size: 2.5rem;
        margin-bottom: 6px;
    }

    .ub-empty h3 {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 0;
    }

    .ub-empty p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 0;
        max-width: 380px;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .ub-stats-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .ub-product-card {
            grid-template-columns: 1fr;
            gap: 10px;
            padding: 14px 16px;
        }

        .ub-product-num {
            display: none;
        }

        .ub-data-cell {
            flex-direction: row;
            justify-content: space-between;
            min-width: auto;
            padding: 0;
        }

        .ub-info-banner {
            flex-direction: column;
            text-align: center;
        }

        .ub-toolbar {
            flex-direction: column;
        }

        .ub-search-box {
            width: 100%;
        }
    }
</style>
@endpush

@section('content')
    <div class="ub-page">
        {{-- 1) Bilgi Bandı --}}
        <div class="ub-info-banner">
            <div class="ub-info-icon">📋</div>
            <div class="ub-info-text">
                <h2>Üretimi bekleyen siparişler burada</h2>
                <p>
                    Müşterilerden gelen siparişler ürün bazında gruplandı.
                    Aynı üründen kaç adet üretmeniz gerektiğini, kaç farklı siparişten geldiğini
                    ve en yakın kargo tarihini bir bakışta görebilirsiniz.
                </p>
            </div>
        </div>

        {{-- 2) Özet Kartları --}}
        <div class="ub-stats-row">
            <div class="ub-stat-card">
                <div class="ub-stat-icon products">📦</div>
                <div class="ub-stat-info">
                    <p class="ub-stat-label">Farklı Ürün</p>
                    <h3 class="ub-stat-value" id="statUrun">0</h3>
                </div>
            </div>
            <div class="ub-stat-card">
                <div class="ub-stat-icon total">🔢</div>
                <div class="ub-stat-info">
                    <p class="ub-stat-label">Üretilecek Toplam</p>
                    <h3 class="ub-stat-value" id="statAdet">0</h3>
                </div>
            </div>
            <div class="ub-stat-card">
                <div class="ub-stat-icon matched">✅</div>
                <div class="ub-stat-info">
                    <p class="ub-stat-label">Tanımlı Ürün</p>
                    <h3 class="ub-stat-value matched" id="statEslesme">0</h3>
                </div>
            </div>
            <div class="ub-stat-card">
                <div class="ub-stat-icon unmatched">⚠️</div>
                <div class="ub-stat-info">
                    <p class="ub-stat-label">Tanımsız Ürün</p>
                    <h3 class="ub-stat-value unmatched" id="statEstiksiz">0</h3>
                </div>
            </div>
        </div>

        {{-- 3) Arama + Filtre --}}
        <div class="ub-toolbar">
            <input type="text" class="ub-search-box" id="searchBox" placeholder="Ürün adı ara..." oninput="filterList()">
            <select id="kategoriSelect" class="ub-filter-select" onchange="loadSummary()">
                <option value="">Tüm Kategoriler</option>
            </select>
            <div class="ub-tab-btns">
                <button class="ub-tab-btn active" data-filter="all" onclick="setTab(this)">Tümü</button>
                <button class="ub-tab-btn" data-filter="matched" onclick="setTab(this)">✅ Tanımlı</button>
                <button class="ub-tab-btn" data-filter="unmatched" onclick="setTab(this)">⚠️ Tanımsız</button>
            </div>
        </div>

        {{-- 4) Ürün Listesi --}}
        <div class="ub-list-section">
            <div class="ub-list-header">
                <h3><i class="bi bi-list-task"></i> Bekleyen Ürünler</h3>
                <span class="ub-list-count" id="listCount">0 ürün</span>
            </div>
            <div class="ub-sort-bar">
                <div></div>
                <button class="ub-sort-btn active" onclick="sortBy('UrunAdi', this)">
                    Ürün Adı <span class="sort-arrow">↕</span>
                </button>
                <button class="ub-sort-btn ub-sort-spacer" onclick="sortBy('ToplamAdet', this)">
                    Adet <span class="sort-arrow">↕</span>
                </button>
                <button class="ub-sort-btn ub-sort-spacer" onclick="sortBy('SiparisSayisi', this)">
                    Sipariş <span class="sort-arrow">↕</span>
                </button>
                <button class="ub-sort-btn ub-sort-spacer" onclick="sortBy('EnYakinKargo', this)">
                    Kargo <span class="sort-arrow">↕</span>
                </button>
                <button class="ub-sort-btn" onclick="sortBy('EslesenUrunNo', this)">
                    Eşleşme <span class="sort-arrow">↕</span>
                </button>
            </div>
            <div class="ub-list-grid" id="summaryGrid">
                <div class="ub-empty" id="loadingState">
                    <div class="ub-empty-icon">⏳</div>
                    <h3>Yükleniyor...</h3>
                    <p>Sipariş verileri getiriliyor, lütfen bekleyin.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    let allItems = [];
    let currentFilter = 'all';
    let currentSort = { field: null, dir: 'asc' };

    function loadSummary() {
        let kategori = document.getElementById('kategoriSelect').value;
        const grid = document.getElementById('summaryGrid');
        grid.innerHTML = `
            <div class="ub-empty">
                <div class="ub-empty-icon">⏳</div>
                <h3>Yükleniyor...</h3>
                <p>Sipariş verileri getiriliyor, lütfen bekleyin.</p>
            </div>`;

        fetch('/SiparisApi.ashx?action=getSummary&kategori=' + encodeURIComponent(kategori))
            .then(r => r.json())
            .then(data => {
                if (!data.success) { alert(data.message); return; }

                allItems = data.summary || [];

                // Kategori listesini doldur
                let sel = document.getElementById('kategoriSelect');
                if (data.kategoriList && sel.options.length <= 1) {
                    data.kategoriList.forEach(k => {
                        sel.innerHTML += `<option value="${escapeHtml(k)}">${escapeHtml(k)}</option>`;
                    });
                }

                // İstatistikler
                let eslesmis = allItems.filter(i => i.EslesenUrunNo > 0).length;
                document.getElementById('statUrun').textContent = allItems.length;
                document.getElementById('statAdet').textContent = data.toplamAdet || 0;
                document.getElementById('statEslesme').textContent = eslesmis;
                document.getElementById('statEstiksiz').textContent = allItems.length - eslesmis;

                renderList();
            })
            .catch(err => {
                console.error(err);
                grid.innerHTML = `
                    <div class="ub-empty">
                        <div class="ub-empty-icon">❌</div>
                        <h3>Yüklenemedi</h3>
                        <p>Veriler alınırken bir hata oluştu. Lütfen sayfayı yenileyin.</p>
                    </div>`;
            });
    }

    function renderList() {
        const grid = document.getElementById('summaryGrid');
        const search = document.getElementById('searchBox').value.toLowerCase().trim();

        let filtered = allItems.filter(item => {
            // Tab filtresi
            if (currentFilter === 'matched' && !(item.EslesenUrunNo > 0)) return false;
            if (currentFilter === 'unmatched' && item.EslesenUrunNo > 0) return false;
            // Arama filtresi
            if (search) {
                const name = (item.UrunAdi || '').toLowerCase();
                const sys = (item.sistemAdi || '').toLowerCase();
                if (!name.includes(search) && !sys.includes(search)) return false;
            }
            return true;
        });

        // Sıralama
        if (currentSort.field) {
            filtered.sort((a, b) => {
                let valA = a[currentSort.field];
                let valB = b[currentSort.field];

                // Kargo tarihi özel sıralama
                if (currentSort.field === 'EnYakinKargo') {
                    valA = parseTurkishDate(valA);
                    valB = parseTurkishDate(valB);
                    if (!valA) return 1;
                    if (!valB) return -1;
                    return currentSort.dir === 'asc' ? valA - valB : valB - valA;
                }

                // Sayısal alanlar
                if (typeof valA === 'number' || currentSort.field === 'ToplamAdet' || currentSort.field === 'SiparisSayisi' || currentSort.field === 'EslesenUrunNo') {
                    valA = parseInt(valA) || 0;
                    valB = parseInt(valB) || 0;
                    return currentSort.dir === 'asc' ? valA - valB : valB - valA;
                }

                // Metin alanları
                valA = (valA || '').toString().toLowerCase();
                valB = (valB || '').toString().toLowerCase();
                return currentSort.dir === 'asc'
                    ? valA.localeCompare(valB, 'tr')
                    : valB.localeCompare(valA, 'tr');
            });
        }

        document.getElementById('listCount').textContent = filtered.length + ' ürün';

        if (filtered.length === 0) {
            grid.innerHTML = `
                <div class="ub-empty">
                    <div class="ub-empty-icon">📭</div>
                    <h3>Sonuç bulunamadı</h3>
                    <p>${search ? 'Arama kriterlerinize uygun ürün yok. Aramayı değiştirin.' : 'Bu filtrede bekleyen sipariş bulunmuyor.'}</p>
                </div>`;
            return;
        }

        let html = '';
        filtered.forEach((item, i) => {
            const isMatched = item.EslesenUrunNo > 0;
            const matchClass = isMatched ? 'matched' : 'unmatched';
            const kargoBadge = getKargoBadge(item.EnYakinKargo);
            const matchBadge = isMatched
                ? '<span class="ub-match-badge yes">✅ Tanımlı</span>'
                : '<span class="ub-match-badge no">⚠️ Tanımsız</span>';

            html += `
                <div class="ub-product-card ${matchClass}">
                    <div class="ub-product-num">${i + 1}</div>
                    <div class="ub-product-info">
                        <h4 class="ub-product-name" title="${escapeHtml(item.UrunAdi || '-')}">${escapeHtml(item.UrunAdi || '-')}</h4>
                        <div class="ub-product-system">${escapeHtml(item.sistemAdi || 'Henüz eşleştirilmedi')}</div>
                    </div>
                    <div class="ub-data-cell">
                        <span class="ub-data-label">Üretilecek</span>
                        <span class="ub-data-value qty">${item.ToplamAdet}</span>
                    </div>
                    <div class="ub-data-cell">
                        <span class="ub-data-label">Sipariş</span>
                        <span class="ub-data-value">${item.SiparisSayisi}</span>
                    </div>
                    <div class="ub-data-cell">
                        <span class="ub-data-label">Kargo</span>
                        ${kargoBadge}
                    </div>
                    <div>
                        ${matchBadge}
                    </div>
                </div>
            `;
        });

        grid.innerHTML = html;
    }

    function getKargoBadge(kargoStr) {
        if (!kargoStr || kargoStr === '-') {
            return '<span class="ub-kargo-badge none">Tarih yok</span>';
        }

        // Tarihi parse et ve bugünden kaç gün uzakta kontrol et
        try {
            const parts = kargoStr.split(' ')[0].split('.');
            if (parts.length === 3) {
                const kargoDate = new Date(parts[2], parts[1] - 1, parts[0]);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const diffDays = Math.ceil((kargoDate - today) / (1000 * 60 * 60 * 24));

                if (diffDays <= 3) {
                    return `<span class="ub-kargo-badge urgent">🔥 ${kargoStr.split(' ')[0]}</span>`;
                }
            }
        } catch (e) {}

        return `<span class="ub-kargo-badge normal">📅 ${kargoStr.split(' ')[0]}</span>`;
    }

    function setTab(btn) {
        document.querySelectorAll('.ub-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        renderList();
    }

    function filterList() {
        renderList();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function sortBy(field, btn) {
        // Aynı alana tekrar tıklandıysa yön değiştir
        if (currentSort.field === field) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.field = field;
            currentSort.dir = 'asc';
        }

        // Buton stillerini güncelle
        document.querySelectorAll('.ub-sort-btn').forEach(b => {
            b.classList.remove('active');
            b.querySelector('.sort-arrow').textContent = '↕';
        });
        btn.classList.add('active');
        btn.querySelector('.sort-arrow').textContent = currentSort.dir === 'asc' ? '↑' : '↓';

        renderList();
    }

    function parseTurkishDate(str) {
        if (!str || str === '-') return null;
        try {
            const parts = str.split(' ')[0].split('.');
            if (parts.length === 3) {
                return new Date(parts[2], parts[1] - 1, parts[0]);
            }
        } catch (e) {}
        return null;
    }

    loadSummary();
</script>
@endpush
