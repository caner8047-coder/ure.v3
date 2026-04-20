@extends('layouts.app')

@section('title', 'Urun Agaci')


@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="resetTreeSelection()">
        <i class="bi bi-arrow-counterclockwise me-2"></i>Temizle
    </button>
    <button type="button" class="btn btn-primary" onclick="loadUrunler()">
        <i class="bi bi-arrow-repeat me-2"></i>Yenile
    </button>
@endsection

@push('styles')
<style>
    /* ═══════════════════════════════════════
       Ürün Ağacı — Yeni Basit Tasarım
       ═══════════════════════════════════════ */

    .bom-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Arama Bölümü ── */
    .bom-search-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
    }

    .bom-search-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .bom-search-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        flex-shrink: 0;
    }

    .bom-search-header h2 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
    }

    .bom-search-header p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 2px 0 0;
    }

    .bom-search-bar {
        position: relative;
    }

    .bom-search-bar input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid var(--z-border);
        border-radius: 12px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--z-text);
        transition: all 0.2s ease;
    }

    .bom-search-bar input:focus {
        outline: none;
        border-color: var(--z-accent);
        background: var(--z-bg-card);
        box-shadow: 0 0 0 4px var(--z-accent-soft);
    }

    .bom-search-bar i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.1rem;
        color: var(--z-text-muted);
    }

    .bom-search-count {
        margin-top: 10px;
        font-size: 0.8rem;
        color: var(--z-text-muted);
    }

    .bom-search-count strong {
        color: var(--z-accent);
        font-weight: 700;
    }

    /* ── Ürün Kartları Grid ── */
    .bom-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 10px;
        max-height: 320px;
        overflow-y: auto;
        margin-top: 14px;
        padding-right: 4px;
    }

    .bom-product-grid::-webkit-scrollbar { width: 5px; }
    .bom-product-grid::-webkit-scrollbar-thumb { border-radius: 99px; background: rgba(148,163,184,0.25); }

    .bom-product-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border: 2px solid var(--z-border-light);
        border-radius: 10px;
        background: var(--z-bg-card);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .bom-product-item:hover {
        border-color: var(--z-accent);
        background: var(--z-accent-soft);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13,148,136,0.1);
    }

    .bom-product-item.active {
        border-color: var(--z-accent);
        background: var(--z-accent-soft);
        box-shadow: 0 0 0 3px rgba(13,148,136,0.15);
    }

    .bom-item-emoji {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .bom-item-emoji.nihai { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
    .bom-item-emoji.ara { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .bom-item-emoji.ham { background: linear-gradient(135deg, #fef3c7, #fde68a); }

    .bom-item-info {
        flex: 1;
        min-width: 0;
    }

    .bom-item-info h4 {
        font-size: 0.84rem;
        font-weight: 600;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .bom-item-info span {
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .bom-item-type {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        flex-shrink: 0;
    }

    .bom-item-type.nihai { background: #dbeafe; color: #1d4ed8; }
    .bom-item-type.ara { background: #d1fae5; color: #047857; }
    .bom-item-type.ham { background: #fef3c7; color: #b45309; }

    /* ── Ağaç Görünümü ── */
    .bom-tree-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
        min-height: 300px;
    }

    .bom-tree-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
        gap: 12px;
    }

    .bom-tree-empty .empty-icon {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--z-accent-soft), rgba(6,182,212,0.08));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        animation: emptyPulse 2s ease-in-out infinite;
    }

    @keyframes emptyPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .bom-tree-empty h3 {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
    }

    .bom-tree-empty p {
        font-size: 0.85rem;
        color: var(--z-text-muted);
        max-width: 380px;
        margin: 0;
    }

    /* Ana Ürün Kartı */
    .bom-root {
        background: linear-gradient(135deg, #f0fdfa, #e0f2fe);
        border: 2px solid #99f6e4;
        border-radius: 14px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 8px;
        position: relative;
    }

    .bom-root-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(13,148,136,0.25);
    }

    .bom-root-info h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: var(--z-text);
    }

    .bom-root-info p {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
        margin: 0;
    }

    .bom-root-info p i {
        margin-right: 4px;
    }

    .bom-root-badge {
        margin-left: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }

    .bom-root-badge span {
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .bom-root-badge .parts-count {
        background: rgba(13,148,136,0.12);
        color: #0d9488;
    }

    .bom-root-badge .total-count {
        background: rgba(217,119,6,0.1);
        color: #b45309;
    }

    /* ── Parça Ağaç Çizgileri ve Düzenler ── */
    .bom-children {
        position: relative;
        padding-left: 32px;
        margin-top: 4px;
    }

    .bom-children::before {
        content: '';
        position: absolute;
        left: 24px;
        top: 0;
        bottom: 20px;
        width: 2px;
        background: linear-gradient(180deg, #99f6e4, #d1d5db);
        border-radius: 2px;
    }

    .bom-child-wrapper {
        position: relative;
        padding: 6px 0;
    }

    .bom-child-wrapper::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 28px;
        width: 16px;
        height: 2px;
        background: #d1d5db;
    }

    /* Parça Kartı */
    .bom-node {
        border: 1.5px solid var(--z-border);
        border-radius: 12px;
        background: var(--z-bg-card);
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }

    .bom-node:hover {
        border-color: var(--z-accent);
        box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        transform: translateX(3px);
    }

    .bom-node-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .bom-node-icon.ara-mamul { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .bom-node-icon.ham-madde { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .bom-node-icon.nihai-urun { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
    .bom-node-icon.parca { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); }

    .bom-node-info {
        flex: 1;
        min-width: 0;
    }

    .bom-node-info h5 {
        font-size: 0.88rem;
        font-weight: 600;
        margin: 0;
        line-height: 1.3;
    }

    .bom-node-info span {
        font-size: 0.74rem;
        color: var(--z-text-muted);
    }

    .bom-node-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .bom-qty {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 700;
        background: var(--z-bg-soft);
        color: var(--z-text);
        border: 1px solid var(--z-border-light);
    }

    .bom-type-tag {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .bom-type-tag.ara { background: #d1fae5; color: #047857; }
    .bom-type-tag.ham { background: #fef3c7; color: #b45309; }
    .bom-type-tag.nihai { background: #dbeafe; color: #1d4ed8; }
    .bom-type-tag.parca { background: #f3e8ff; color: #7c3aed; }

    /* Alt grup animasyonu */
    .bom-node-expand {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1.5px solid var(--z-border);
        background: var(--z-bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        color: var(--z-text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .bom-node-expand:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }

    .bom-node-expand.open {
        background: var(--z-accent);
        border-color: var(--z-accent);
        color: white;
        transform: rotate(90deg);
    }

    /* İç içe alt ağaç */
    .bom-sub-tree {
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .bom-sub-tree.collapsed {
        max-height: 0 !important;
    }

    /* Seviye renklendir */
    .bom-children.level-1::before { background: linear-gradient(180deg, #0d9488, #a7f3d0); }
    .bom-children.level-2::before { background: linear-gradient(180deg, #06b6d4, #bae6fd); }
    .bom-children.level-3::before { background: linear-gradient(180deg, #8b5cf6, #c4b5fd); }
    .bom-children.level-4::before { background: linear-gradient(180deg, #d97706, #fde68a); }

    /* Özet Bilgi Bandı */
    .bom-summary-strip {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 18px;
        background: var(--z-bg-soft);
        border-radius: 10px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .bom-summary-strip .strip-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--z-text-secondary);
    }

    .bom-summary-strip .strip-item i {
        font-size: 1rem;
    }

    .bom-summary-strip .strip-item strong {
        font-weight: 700;
        color: var(--z-text);
    }

    .bom-legend {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-left: auto;
    }

    .bom-legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .bom-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 4px;
    }

    .bom-legend-dot.nihai { background: #93c5fd; }
    .bom-legend-dot.ara { background: #6ee7b7; }
    .bom-legend-dot.ham { background: #fcd34d; }

    /* ── No BOM Uyarı ── */
    .bom-no-data {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 50px 20px;
        text-align: center;
        gap: 10px;
    }

    .bom-no-data .no-data-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: var(--z-warning-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .bom-no-data h3 {
        font-size: 0.95rem;
        margin: 0;
    }

    .bom-no-data p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 0;
    }

    /* ── Responsive ── */
    @media (max-width: 768px) {
        .bom-product-grid {
            grid-template-columns: 1fr;
            max-height: 250px;
        }

        .bom-root {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .bom-root-badge {
            margin-left: 0;
            flex-direction: row;
        }

        .bom-children {
            padding-left: 20px;
        }

        .bom-node {
            flex-wrap: wrap;
        }

        .bom-summary-strip {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .bom-legend {
            margin-left: 0;
        }
    }

    /* ── Giriş Animasyonu ── */
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .bom-animated {
        animation: fadeSlideUp 0.3s ease forwards;
    }

    .bom-child-wrapper:nth-child(1) { animation-delay: 0.05s; }
    .bom-child-wrapper:nth-child(2) { animation-delay: 0.10s; }
    .bom-child-wrapper:nth-child(3) { animation-delay: 0.15s; }
    .bom-child-wrapper:nth-child(4) { animation-delay: 0.20s; }
    .bom-child-wrapper:nth-child(5) { animation-delay: 0.25s; }
</style>
@endpush

@section('content')
    {{-- Hidden compat elements --}}
    <span id="treeBadge" style="display:none;">Secim bekleniyor</span>
    <span id="treeTitleMeta" style="display:none;">Henuz secilmedi</span>
    <span id="treeNodeCountMeta" style="display:none;">0</span>
    <span id="summaryFilteredMeta" style="display:none;">0</span>
    <span id="summaryTreeUpdatedMeta" style="display:none;">Bekleniyor</span>
    <span id="summaryProductsInline" style="display:none;">0</span>
    <span id="summaryFilteredInline" style="display:none;">0</span>
    <span id="summarySelectedInline" style="display:none;">Yok</span>
    <span id="summaryNodesInline" style="display:none;">0</span>

    <div class="bom-page">
        {{-- 1) Arama Bölümü --}}
        <section class="bom-search-section">
            <div class="bom-search-header">
                <div class="bom-search-icon">
                    <i class="bi bi-search"></i>
                </div>
                <div>
                    <h2>Ürün Bul</h2>
                    <p>Ağacını görmek istediğin ürünü ara ve seç</p>
                </div>
            </div>

            <div class="bom-search-bar">
                <i class="bi bi-search"></i>
                <input type="text" id="urunArama" placeholder="Ürün adını yaz..." oninput="filterUrunler()">
            </div>

            <div class="bom-search-count" id="searchCount">
                Yükleniyor...
            </div>

            <div class="bom-product-grid" id="urunListesi">
                <div style="grid-column: 1/-1; text-align:center; padding:30px; color:var(--z-text-muted);">
                    <i class="bi bi-hourglass-split" style="font-size:1.3rem;"></i>
                    <p style="margin-top:6px;">Ürünler yükleniyor...</p>
                </div>
            </div>
        </section>

        {{-- 2) Ağaç Görünümü --}}
        <section class="bom-tree-section" id="treeSection">
            <div id="treeContainer">
                <div class="bom-tree-empty">
                    <div class="empty-icon">🌳</div>
                    <h3>Henüz bir ürün seçilmedi</h3>
                    <p>Yukarıdan bir ürün seçtiğinde, o ürünün hangi parçalardan oluştuğunu burada göreceksin.</p>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
let allUrunler = [];
let filteredUrunler = [];
let urunMap = {};
let selectedProductId = null;

// ── Ürünleri Yükle ──
function loadUrunler() {
    fetch('/api/database/components?limit=9999', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            allUrunler = data.data || [];
            filteredUrunler = [...allUrunler];
            urunMap = {};
            allUrunler.forEach((urun) => {
                urunMap[String(urun.id)] = urun;
            });

            updateSearchCount();
            renderUrunListe(filteredUrunler);
            setTreeStamp();
        });
}

// ── Ürün Listesi Render ──
function renderUrunListe(list) {
    const html = list.map((urun) => {
        const typeText = urun.type || 'Belirtilmedi';
        const isActive = Number(selectedProductId) === Number(urun.id);
        const subCount = getImmediatePathParts(urun.path).length;
        const typeClass = getTypeClass(typeText);
        const emoji = getTypeEmoji(typeText);

        return `
            <div class="bom-product-item ${isActive ? 'active' : ''}"
                 onclick="showTree(${urun.id}, '${escapeJsString(urun.name || '')}', '${escapeJsString(urun.path || '')}', this)">
                <div class="bom-item-emoji ${typeClass}">${emoji}</div>
                <div class="bom-item-info">
                    <h4>${escapeHtml(urun.name || 'Bilinmiyor')}</h4>
                    <span>${subCount > 0 ? subCount + ' parça' : 'Parçası yok'}</span>
                </div>
                <span class="bom-item-type ${typeClass}">${escapeHtml(typeText)}</span>
            </div>
        `;
    }).join('');

    document.getElementById('urunListesi').innerHTML = html || `
        <div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--z-text-muted);">
            <p>Sonuç bulunamadı 😔</p>
        </div>`;
}

// ── Arama Filtreleme ──
function filterUrunler() {
    const query = document.getElementById('urunArama').value.toLowerCase();
    filteredUrunler = allUrunler.filter((urun) => (urun.name || '').toLowerCase().includes(query));
    renderUrunListe(filteredUrunler);
    updateSearchCount();
}

// ── Ağaç Göster ──
function showTree(no, adi, yol, element) {
    selectedProductId = no;

    // Aktif kartı işaretle
    document.querySelectorAll('#urunListesi .bom-product-item').forEach((card) => card.classList.remove('active'));
    if (element) element.classList.add('active');

    // Compat güncelle
    document.getElementById('treeTitleMeta').textContent = adi;
    document.getElementById('summarySelectedInline').textContent = truncateValue(adi, 14);

    // Ağaç olup olmadığını kontrol et
    if (!yol || !yol.trim()) {
        document.getElementById('treeContainer').innerHTML = `
            <div class="bom-no-data">
                <div class="no-data-icon">📋</div>
                <h3>Bu ürün için ağaç tanımlı değil</h3>
                <p>"${escapeHtml(adi)}" ürününün alt parça tanımı bulunmuyor.</p>
            </div>`;
        updateTreeSummary(0, 'BOM yok');
        return;
    }

    const pathParts = getImmediatePathParts(yol);
    const totalNodeCount = countAllNodes(yol, 1);
    const piece = urunMap[String(no)];
    const typeText = piece ? (piece.type || 'Ürün') : 'Ürün';

    // Sayfa oluştur
    const html = `
        <!-- Özet Bandı -->
        <div class="bom-summary-strip bom-animated">
            <div class="strip-item">
                <i class="bi bi-box-seam" style="color: var(--z-accent);"></i>
                <span>Ana ürün: <strong>${escapeHtml(truncateValue(adi, 30))}</strong></span>
            </div>
            <div class="strip-item">
                <i class="bi bi-diagram-3" style="color: var(--z-warning);"></i>
                <span>Toplam <strong>${formatNumber(totalNodeCount)}</strong> parça</span>
            </div>
            <div class="strip-item">
                <i class="bi bi-layers" style="color: #8b5cf6;"></i>
                <span><strong>${formatNumber(pathParts.length)}</strong> ana bileşen</span>
            </div>
            <div class="bom-legend">
                <div class="bom-legend-item"><div class="bom-legend-dot ara"></div> Ara Mamül</div>
                <div class="bom-legend-item"><div class="bom-legend-dot ham"></div> Ham Madde</div>
                <div class="bom-legend-item"><div class="bom-legend-dot nihai"></div> Nihai Ürün</div>
            </div>
        </div>

        <!-- Ana Ürün Kartı -->
        <div class="bom-root bom-animated">
            <div class="bom-root-icon">🏭</div>
            <div class="bom-root-info">
                <h3>${escapeHtml(adi)}</h3>
                <p><i class="bi bi-tag"></i>${escapeHtml(typeText)} &nbsp;·&nbsp; <i class="bi bi-hash"></i>${escapeHtml(String(no))}</p>
            </div>
            <div class="bom-root-badge">
                <span class="parts-count">${formatNumber(pathParts.length)} parça</span>
                <span class="total-count">${formatNumber(totalNodeCount)} toplam</span>
            </div>
        </div>

        <!-- Alt Parçalar -->
        ${buildTreeHtml(yol, 1)}
    `;

    document.getElementById('treeContainer').innerHTML = html;
    updateTreeSummary(totalNodeCount, `${formatNumber(pathParts.length)} alt bilesen`);

    // Ağaç bölümüne scroll
    document.getElementById('treeSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Ağaç HTML Oluştur ──
function buildTreeHtml(path, level) {
    if (!path || level > 4) return '';

    const parts = getImmediatePathParts(path);
    if (!parts.length) return '';

    const cards = parts.map((part, idx) => {
        const [pieceNo, quantity] = String(part).split('-');
        const piece = urunMap[String(pieceNo)];
        const pieceName = piece ? piece.name : `Parça #${pieceNo}`;
        const pieceType = piece ? (piece.type || 'Parça') : 'Parça';
        const nestedPath = piece ? piece.path : '';
        const nestedCount = getImmediatePathParts(nestedPath).length;
        const typeClass = getNodeTypeClass(pieceType);
        const emoji = getTypeEmoji(pieceType);
        const tagClass = getTagClass(pieceType);
        const hasChildren = nestedPath && nestedCount > 0;
        const nodeId = `node-${level}-${idx}`;

        let childHtml = '';
        if (hasChildren) {
            childHtml = `
                <div class="bom-sub-tree" id="sub-${nodeId}" style="max-height: 9999px;">
                    ${buildTreeHtml(nestedPath, level + 1)}
                </div>`;
        }

        return `
            <div class="bom-child-wrapper bom-animated">
                <div class="bom-node">
                    <div class="bom-node-icon ${typeClass}">${emoji}</div>
                    <div class="bom-node-info">
                        <h5>${escapeHtml(pieceName)}</h5>
                        <span>${escapeHtml(pieceType)}${nestedCount > 0 ? ' · ' + nestedCount + ' alt parça' : ''}</span>
                    </div>
                    <div class="bom-node-meta">
                        <span class="bom-qty">${formatNumber(quantity || 1)}x</span>
                        <span class="bom-type-tag ${tagClass}">${escapeHtml(pieceType)}</span>
                        ${hasChildren ? `<button class="bom-node-expand open" onclick="toggleSubTree('${nodeId}', this)"><i class="bi bi-chevron-right"></i></button>` : ''}
                    </div>
                </div>
                ${childHtml}
            </div>
        `;
    }).join('');

    return `<div class="bom-children level-${level}">${cards}</div>`;
}

// ── Alt ağaç aç/kapa ──
function toggleSubTree(nodeId, btn) {
    const sub = document.getElementById('sub-' + nodeId);
    if (!sub) return;

    if (sub.classList.contains('collapsed')) {
        sub.classList.remove('collapsed');
        btn.classList.add('open');
    } else {
        sub.classList.add('collapsed');
        btn.classList.remove('open');
    }
}

// ── Tip Sınıfları ──
function getTypeClass(typeText) {
    if (typeText.includes('Nihai')) return 'nihai';
    if (typeText.includes('Ara')) return 'ara';
    if (typeText.includes('Ham')) return 'ham';
    return '';
}

function getNodeTypeClass(typeText) {
    if (typeText.includes('Ara')) return 'ara-mamul';
    if (typeText.includes('Ham')) return 'ham-madde';
    if (typeText.includes('Nihai')) return 'nihai-urun';
    return 'parca';
}

function getTagClass(typeText) {
    if (typeText.includes('Ara')) return 'ara';
    if (typeText.includes('Ham')) return 'ham';
    if (typeText.includes('Nihai')) return 'nihai';
    return 'parca';
}

function getTypeEmoji(typeText) {
    if (typeText.includes('Nihai')) return '📦';
    if (typeText.includes('Ara')) return '🔧';
    if (typeText.includes('Ham')) return '🪵';
    return '⚙️';
}

// ── Yardımcı Fonksiyonlar ──
function getImmediatePathParts(path) {
    return String(path || '').split(':').filter((part) => part.trim() !== '');
}

function countAllNodes(path, level) {
    if (!path || level > 4) return 0;
    const parts = getImmediatePathParts(path);
    let count = parts.length;
    parts.forEach((part) => {
        const [pieceNo] = String(part).split('-');
        const piece = urunMap[String(pieceNo)];
        if (piece && piece.path) {
            count += countAllNodes(piece.path, level + 1);
        }
    });
    return count;
}

function updateSearchCount() {
    const el = document.getElementById('searchCount');
    el.innerHTML = `Toplam <strong>${formatNumber(allUrunler.length)}</strong> ürün · Gösterilen: <strong>${formatNumber(filteredUrunler.length)}</strong>`;
    document.getElementById('summaryProductsInline').textContent = formatNumber(allUrunler.length);
    document.getElementById('summaryFilteredInline').textContent = formatNumber(filteredUrunler.length);
    document.getElementById('summaryFilteredMeta').textContent = formatNumber(filteredUrunler.length);
}

function updateTreeSummary(nodeCount, badgeText) {
    document.getElementById('summaryNodesInline').textContent = formatNumber(nodeCount);
    document.getElementById('treeNodeCountMeta').textContent = formatNumber(nodeCount);
    document.getElementById('treeBadge').textContent = badgeText || 'Hazir';
}

function resetTreeSelection() {
    selectedProductId = null;
    document.getElementById('urunArama').value = '';
    filteredUrunler = [...allUrunler];
    renderUrunListe(filteredUrunler);
    updateSearchCount();
    document.getElementById('treeTitleMeta').textContent = 'Henuz secilmedi';
    document.getElementById('summarySelectedInline').textContent = 'Yok';
    document.getElementById('treeContainer').innerHTML = `
        <div class="bom-tree-empty">
            <div class="empty-icon">🌳</div>
            <h3>Henüz bir ürün seçilmedi</h3>
            <p>Yukarıdan bir ürün seçtiğinde, o ürünün hangi parçalardan oluştuğunu burada göreceksin.</p>
        </div>`;
    updateTreeSummary(0, 'Secim bekleniyor');
}

function setTreeStamp() {
    document.getElementById('summaryTreeUpdatedMeta').textContent = new Date().toLocaleTimeString('tr-TR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function truncateValue(value, maxLength) {
    const text = String(value || '');
    if (text.length <= maxLength) return text || 'Yok';
    return `${text.slice(0, maxLength)}...`;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeJsString(value) {
    return String(value || '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\n/g, ' ');
}

document.addEventListener('DOMContentLoaded', () => {
    loadUrunler();
    updateTreeSummary(0, 'Secim bekleniyor');
});
</script>
@endpush
