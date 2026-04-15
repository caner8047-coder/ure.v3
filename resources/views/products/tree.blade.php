@extends('layouts.app')

@section('title', 'Urun Agaci')


@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="resetTreeSelection()">
        <i class="bi bi-arrow-counterclockwise me-2"></i>Secimi Temizle
    </button>
    <button type="button" class="btn btn-primary" onclick="loadUrunler()">
        <i class="bi bi-diagram-3 me-2"></i>Listeyi Yenile
    </button>
@endsection

@push('styles')
<style>
    .tree-layout { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: 16px; }
    .tree-search-results { max-height: 620px; overflow-y: auto; display: grid; gap: 8px; padding-right: 6px; }
    .tree-product-card { padding: 14px; border: 1px solid var(--z-border); border-radius: var(--z-radius); background: var(--z-bg-card); cursor: pointer; transition: all var(--z-transition); }
    .tree-product-card:hover { border-color: var(--z-accent); box-shadow: var(--z-shadow); }
    .tree-product-card.active { border-color: var(--z-accent); background: var(--z-accent-soft); }
    .tree-product-card h4 { margin: 0 0 6px; font-size: 0.88rem; font-weight: 600; color: var(--z-text); }
    .tree-product-card p { margin: 0; color: var(--z-text-muted); font-size: 0.8rem; }
    .tree-stage { display: grid; gap: 14px; min-height: 500px; }
    .tree-empty { display: grid; place-items: center; text-align: center; color: var(--z-text-muted); min-height: 400px; gap: 10px; }
    .tree-empty i { font-size: 2rem; color: var(--z-accent); opacity: 0.35; }
    .tree-root-card, .tree-node-card { position: relative; padding: 16px; border: 1px solid var(--z-border); border-radius: var(--z-radius); background: var(--z-bg-card); }
    .tree-root-card { background: var(--z-bg-soft); }
    .tree-root-card h3, .tree-node-card h4 { margin: 0; font-weight: 600; }
    .tree-root-card p, .tree-node-card p { margin: 6px 0 0; color: var(--z-text-muted); font-size: 0.82rem; }
    .tree-level { display: grid; gap: 10px; padding-left: 24px; border-left: 2px dashed var(--z-border-light); }
    .tree-level-header { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 2px; }
    .tree-level-title { font-size: 0.72rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--z-text-muted); }
    .tree-node-row { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; }
    .tree-node-card { overflow: hidden; }
    .tree-node-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .tree-branch { display: grid; gap: 12px; margin-top: 10px; padding-top: 12px; border-top: 1px solid var(--z-border-light); }
    .tree-search-results::-webkit-scrollbar { width: 6px; }
    .tree-search-results::-webkit-scrollbar-thumb { border-radius: 999px; background: rgba(148, 163, 184, 0.3); }
    @media (max-width: 1080px) { .tree-layout { grid-template-columns: 1fr; } .tree-search-results { max-height: 300px; } }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam urun</p>
            <h3 class="metric-value" id="summaryProductsInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Filtre sonucu</p>
            <h3 class="metric-value" id="summaryFilteredInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Secili urun</p>
            <h3 class="metric-value" id="summarySelectedInline">Yok</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Dugum sayisi</p>
            <h3 class="metric-value" id="summaryNodesInline">0</h3>
        </article>
    </div>

    {{-- Hidden compat elements --}}
    <span id="treeBadge" style="display:none;">Secim bekleniyor</span>
    <span id="treeTitleMeta" style="display:none;">Henuz secilmedi</span>
    <span id="treeNodeCountMeta" style="display:none;">0</span>
    <span id="summaryFilteredMeta" style="display:none;">0</span>
    <span id="summaryTreeUpdatedMeta" style="display:none;">Bekleniyor</span>

    <div class="tree-layout">
        <section class="panel-surface">
            <div class="section-header">
                <div>
                    <h3 class="section-title">Urun listesi</h3>
                </div>
                <span class="soft-badge success" id="listCountChip">0 urun</span>
            </div>

            <div class="stack-list">
                <div>
                    <label class="form-label">Urun ara</label>
                    <input type="text" id="urunArama" class="form-control" placeholder="Urun adina gore ara..." oninput="filterUrunler()">
                </div>

                <div class="tree-search-results" id="urunListesi">
                    <div class="text-center text-muted py-4">Yukleniyor...</div>
                </div>
            </div>
        </section>

        <section class="panel-surface">
            <div class="section-header">
                <div>
                    <h3 class="section-title" id="treeTitle">Urun secin...</h3>
                </div>
                <span class="soft-badge warning" id="treeFocusChip">Hazir</span>
            </div>

            <div class="tree-stage" id="treeContainer">
                <div class="tree-empty">
                    <i class="bi bi-arrow-left-circle"></i>
                    <strong>Soldan bir urun secerek agaci goruntule.</strong>
                    <span>Kok urun ve tum alt bilesenler secimden sonra burada acilacak.</span>
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

            updateListSummary();
            renderUrunListe(filteredUrunler);
            setTreeStamp();
        });
}

function renderUrunListe(list) {
    const html = list.map((urun) => {
        const typeText = urun.type || 'Belirtilmedi';
        const badgeClass = 'soft-badge' + (typeText.includes('Nihai') ? '' : typeText.includes('Ara') ? ' success' : ' warning');
        const isActive = Number(selectedProductId) === Number(urun.id);
        const subCount = getImmediatePathParts(urun.path).length;

        return `
            <article class="tree-product-card ${isActive ? 'active' : ''}" onclick="showTree(${urun.id}, '${escapeJsString(urun.name || '')}', '${escapeJsString(urun.path || '')}', this)">
                <div class="d-flex justify-content-between gap-3 align-items-start">
                    <h4>${escapeHtml(urun.name || 'Bilinmiyor')}</h4>
                    <span class="${badgeClass}">${escapeHtml(typeText)}</span>
                </div>
                <p><i class="bi bi-diagram-2 me-1"></i>Alt bilesen: ${formatNumber(subCount)}</p>
            </article>
        `;
    }).join('');

    document.getElementById('urunListesi').innerHTML = html || '<div class="text-center text-muted py-4">Sonuc bulunamadi.</div>';
}

function filterUrunler() {
    const query = document.getElementById('urunArama').value.toLowerCase();
    filteredUrunler = allUrunler.filter((urun) => (urun.name || '').toLowerCase().includes(query));
    renderUrunListe(filteredUrunler);
    updateListSummary();
}

function showTree(no, adi, yol, element) {
    selectedProductId = no;
    document.querySelectorAll('#urunListesi .tree-product-card').forEach((card) => card.classList.remove('active'));
    if (element) {
        element.classList.add('active');
    }

    document.getElementById('treeTitle').textContent = adi;
    document.getElementById('treeTitleMeta').textContent = adi;
    document.getElementById('summarySelectedInline').textContent = truncateValue(adi, 14);

    if (!yol || !yol.trim()) {
        document.getElementById('treeContainer').innerHTML = `
            <div class="tree-empty">
                <i class="bi bi-exclamation-circle"></i>
                <strong>Bu urun icin BOM tanimli degil.</strong>
                <span>Alt parca yolu bulunmadigi icin agac olusturulamadi.</span>
            </div>`;
        updateTreeSummary(0, 'BOM yok');
        return;
    }

    const pathParts = getImmediatePathParts(yol);
    const totalNodeCount = countAllNodes(yol, 1);

    const html = `
        <section class="tree-root-card">
            <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                <div>
                    <span class="tree-chip">Root</span>
                    <h3 class="mt-3">${escapeHtml(adi)}</h3>
                    <p>ID: ${escapeHtml(no)} • Kok urun</p>
                </div>
                <div class="tree-node-meta">
                    <span class="tree-chip success">${formatNumber(pathParts.length)} ilk seviye</span>
                    <span class="tree-chip warning">${formatNumber(totalNodeCount)} toplam dugum</span>
                </div>
            </div>
        </section>
        ${buildTreeHtml(yol, 1)}
    `;

    document.getElementById('treeContainer').innerHTML = html;
    updateTreeSummary(totalNodeCount, `${formatNumber(pathParts.length)} alt bilesen`);
}

function buildTreeHtml(path, level) {
    if (!path || level > 4) return '';

    const parts = getImmediatePathParts(path);
    if (!parts.length) return '';

    const levelLabel = `Seviye ${level}`;
    const cards = parts.map((part) => {
        const [pieceNo, quantity] = String(part).split('-');
        const piece = urunMap[String(pieceNo)];
        const pieceName = piece ? piece.name : `Parca #${pieceNo}`;
        const pieceType = piece ? (piece.type || 'Parca') : 'Parca';
        const nestedPath = piece ? piece.path : '';
        const nestedCount = getImmediatePathParts(nestedPath).length;
        const badgeClass = pieceType.includes('Ara') ? 'soft-badge success' : pieceType.includes('Ham') ? 'soft-badge warning' : 'soft-badge';

        return `
            <div class="tree-node-row">
                <article class="tree-node-card">
                    <div class="d-flex justify-content-between gap-3 align-items-start flex-wrap">
                        <div>
                            <span class="soft-badge">${escapeHtml(pieceType)}</span>
                            <h4 class="mt-3">${escapeHtml(pieceName)}</h4>
                            <p>ID: ${escapeHtml(pieceNo)} • Miktar: ${formatNumber(quantity || 1)}</p>
                        </div>
                        <div class="tree-node-meta">
                            <span class="tree-chip">${formatNumber(quantity || 1)}x</span>
                            <span class="tree-chip success">${formatNumber(nestedCount)} alt</span>
                        </div>
                    </div>
                    ${nestedPath ? `<div class="tree-branch">${buildTreeHtml(nestedPath, level + 1)}</div>` : ''}
                </article>
            </div>
        `;
    }).join('');

    return `
        <section class="tree-level">
            <div class="tree-level-header">
                <span class="tree-level-title">${levelLabel}</span>
                <span class="soft-badge">${formatNumber(parts.length)} dugum</span>
            </div>
            ${cards}
        </section>
    `;
}

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

function updateListSummary() {
    document.getElementById('summaryProductsInline').textContent = formatNumber(allUrunler.length);
    document.getElementById('summaryFilteredInline').textContent = formatNumber(filteredUrunler.length);
    document.getElementById('summaryFilteredMeta').textContent = formatNumber(filteredUrunler.length);
    document.getElementById('listCountChip').textContent = `${formatNumber(filteredUrunler.length)} urun`;
}

function updateTreeSummary(nodeCount, badgeText) {
    document.getElementById('summaryNodesInline').textContent = formatNumber(nodeCount);
    document.getElementById('treeNodeCountMeta').textContent = formatNumber(nodeCount);
    document.getElementById('treeBadge').textContent = badgeText || 'Hazir';
    document.getElementById('treeFocusChip').textContent = badgeText || 'Hazir';
}

function resetTreeSelection() {
    selectedProductId = null;
    document.getElementById('urunArama').value = '';
    filteredUrunler = [...allUrunler];
    renderUrunListe(filteredUrunler);
    updateListSummary();
    document.getElementById('treeTitle').textContent = 'Urun secin...';
    document.getElementById('treeTitleMeta').textContent = 'Henuz secilmedi';
    document.getElementById('summarySelectedInline').textContent = 'Yok';
    document.getElementById('treeContainer').innerHTML = `
        <div class="tree-empty">
            <i class="bi bi-arrow-left-circle"></i>
            <strong>Soldan bir urun secerek agaci goruntule.</strong>
            <span>Kok urun ve tum alt bilesenler secimden sonra burada acilacak.</span>
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
