@extends('layouts.app')

@section('title', 'İş Emri Geçmişi')

@section('page-actions')
    <a href="{{ route('workorders.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Yeni Emir</a>
    <a href="{{ route('workorders.bulk') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Toplu İş Emri</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadCenterFeed(true)"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
@endsection

@push('styles')
<style>
    /* ─── Filter Bar ─── */
    .woh-filter-bar {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        gap: 12px;
        align-items: end;
    }
    .woh-filter-bar .form-label {
        margin-bottom: 4px;
    }
    .woh-filter-check {
        display: flex;
        align-items: center;
        gap: 6px;
        padding-bottom: 8px;
        font-size: 0.82rem;
        color: var(--z-text-secondary);
        cursor: pointer;
        user-select: none;
    }
    .woh-filter-check input { accent-color: var(--z-accent); }

    .woh-view-switch {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px;
        border: 1px solid var(--z-border);
        border-radius: 6px;
        background: var(--z-bg-soft);
        margin-right: 8px;
    }
    .woh-view-switch button {
        border: 0;
        background: transparent;
        color: var(--z-text-secondary);
        border-radius: 4px;
        padding: 6px 10px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .woh-view-switch button.active {
        background: var(--z-bg-card);
        color: var(--z-text);
        box-shadow: var(--z-shadow-sm);
    }

    /* ─── Summary Metrics ─── */
    .woh-metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    .woh-metric-box {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 16px 18px;
        text-align: center;
    }
    .woh-metric-box .metric-num {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--z-text);
        line-height: 1.2;
    }
    .woh-metric-box .metric-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 4px;
    }
    .woh-metric-box.accent .metric-num { color: var(--z-accent); }
    .woh-metric-box.warning .metric-num { color: var(--z-warning); }
    .woh-metric-box.danger .metric-num { color: var(--z-danger); }

    /* ─── Timeline Feed ─── */
    .woh-feed {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .woh-event {
        display: grid;
        grid-template-columns: 56px 1fr;
        gap: 0;
        position: relative;
    }

    /* Timeline line */
    .woh-event-rail {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }

    .woh-event-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--z-accent);
        border: 2px solid var(--z-bg-card);
        outline: 2px solid var(--z-accent-soft);
        flex-shrink: 0;
        margin-top: 20px;
        z-index: 2;
        transition: all 0.2s ease;
    }
    .woh-event:hover .woh-event-dot {
        transform: scale(1.3);
        outline-width: 3px;
    }
    .woh-event-dot.cancelled {
        background: var(--z-danger);
        outline-color: var(--z-danger-soft);
    }
    .woh-event-dot.stock {
        background: var(--z-success);
        outline-color: var(--z-success-soft);
    }
    .woh-event-dot.warning {
        background: var(--z-warning);
        outline-color: var(--z-warning-soft);
    }

    .woh-event-line {
        width: 2px;
        flex: 1;
        background: var(--z-border);
    }
    .woh-event:last-child .woh-event-line {
        background: transparent;
    }

    /* Event Card */
    .woh-event-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 16px 18px;
        margin: 8px 0;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .woh-event-card:hover {
        border-color: #d1d5db;
        box-shadow: var(--z-shadow-hover);
        transform: translateX(2px);
    }
    .woh-event-card.expanded {
        border-color: var(--z-accent);
        box-shadow: 0 0 0 2px var(--z-accent-soft);
    }

    .woh-event-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }

    .woh-event-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--z-text);
        margin: 0;
    }
    .woh-event-subtitle {
        font-size: 0.82rem;
        color: var(--z-text-secondary);
        margin-top: 3px;
        line-height: 1.5;
    }

    .woh-event-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .woh-event-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.72rem;
        font-weight: 500;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
    }
    .woh-event-tag i { font-size: 0.7rem; opacity: 0.7; }

    /* ─── Expanded Detail Drawer ─── */
    .woh-detail {
        display: none;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--z-border-light);
    }
    .woh-detail.open { display: block; }

    .woh-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 24px;
    }
    .woh-detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 0.82rem;
    }
    .woh-detail-row .label { color: var(--z-text-secondary); }
    .woh-detail-row .value { font-weight: 600; color: var(--z-text); text-align: right; }

    .woh-detail-section-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--z-accent);
        margin: 14px 0 6px;
        padding-bottom: 4px;
        border-bottom: 1px solid var(--z-border-light);
    }
    .woh-detail-section-title:first-child { margin-top: 0; }

    /* History steps inside detail */
    .woh-history-steps {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 8px;
    }
    .woh-history-step {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 12px;
        background: var(--z-bg-soft);
        border-radius: var(--z-radius-sm);
        font-size: 0.82rem;
    }
    .woh-history-step-icon {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--z-accent);
        margin-top: 6px;
        flex-shrink: 0;
    }
    .woh-history-step-text strong {
        display: block;
        font-size: 0.82rem;
        color: var(--z-text);
    }
    .woh-history-step-text span {
        font-size: 0.76rem;
        color: var(--z-text-muted);
    }

    /* Diagnostics alerts inside detail */
    .woh-diag-alert {
        padding: 10px 14px;
        border-radius: var(--z-radius-sm);
        font-size: 0.82rem;
        margin-top: 6px;
    }
    .woh-diag-alert.info { background: #eff6ff; color: #1e40af; }
    .woh-diag-alert.warn { background: #fffbeb; color: #92400e; }
    .woh-diag-alert.error { background: #fef2f2; color: #991b1b; }
    .woh-diag-alert.ok { background: #ecfdf5; color: #065f46; }
    .woh-diag-alert strong { display: block; margin-bottom: 2px; }

    .woh-order-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 16px 18px;
        margin: 8px 0;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .woh-order-card:hover {
        border-color: #d1d5db;
        box-shadow: var(--z-shadow-hover);
        transform: translateX(2px);
    }
    .woh-order-card.expanded {
        border-color: var(--z-accent);
        box-shadow: 0 0 0 2px var(--z-accent-soft);
    }
    .woh-order-main {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: start;
    }
    .woh-order-title-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .woh-order-title {
        margin: 0;
        color: var(--z-text);
        font-size: 0.94rem;
        font-weight: 700;
    }
    .woh-order-copy {
        margin: 5px 0 0;
        color: var(--z-text-secondary);
        font-size: 0.82rem;
        line-height: 1.5;
    }
    .woh-order-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .woh-order-task-list {
        display: grid;
        gap: 8px;
        margin-top: 8px;
    }
    .woh-order-task {
        display: grid;
        grid-template-columns: minmax(150px, 1fr) auto;
        gap: 10px;
        align-items: center;
        padding: 9px 12px;
        background: var(--z-bg-soft);
        border-radius: var(--z-radius-sm);
        font-size: 0.82rem;
    }
    .woh-order-task strong {
        display: block;
        color: var(--z-text);
    }
    .woh-order-task span {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.76rem;
        margin-top: 2px;
    }

    /* ─── Pagination ─── */
    .woh-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0 0;
        border-top: 1px solid var(--z-border-light);
        margin-top: 8px;
    }
    .woh-pagination-info {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
    }
    .woh-pagination-btns {
        display: flex;
        gap: 8px;
    }

    /* ─── Empty State ─── */
    .woh-empty {
        text-align: center;
        padding: 48px 24px;
        color: var(--z-text-muted);
    }
    .woh-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 12px;
        opacity: 0.4;
    }
    .woh-empty p { font-size: 0.88rem; margin: 0; }
    .woh-empty small { font-size: 0.78rem; display: block; margin-top: 4px; }

    /* ─── Responsiveness ─── */
    @media (max-width: 900px) {
        .woh-filter-bar {
            grid-template-columns: 1fr 1fr;
        }
        .woh-metrics {
            grid-template-columns: repeat(2, 1fr);
        }
        .woh-detail-grid {
            grid-template-columns: 1fr;
        }
        .woh-order-main {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 600px) {
        .woh-filter-bar {
            grid-template-columns: 1fr;
        }
        .woh-metrics {
            grid-template-columns: 1fr 1fr;
        }
        .woh-event {
            grid-template-columns: 40px 1fr;
        }
        .woh-event-top {
            flex-direction: column;
        }
        .woh-view-switch {
            width: 100%;
            margin: 0 0 8px;
        }
        .woh-view-switch button {
            flex: 1;
            justify-content: center;
        }
    }
</style>
@endpush

@section('content')
    {{-- ── Metrics ── --}}
    <div class="woh-metrics">
        <div class="woh-metric-box">
            <div class="metric-num" id="metricTotal">0</div>
            <div class="metric-label" id="metricTotalLabel">Toplam Sipariş</div>
        </div>
        <div class="woh-metric-box accent">
            <div class="metric-num" id="metricPage">-</div>
            <div class="metric-label">Sayfa</div>
        </div>
        <div class="woh-metric-box warning">
            <div class="metric-num" id="metricAlerts">0</div>
            <div class="metric-label">Sorunlu</div>
        </div>
        <div class="woh-metric-box">
            <div class="metric-num" id="metricTime">-</div>
            <div class="metric-label">Son Yenileme</div>
        </div>
    </div>

    {{-- ── Filter ── --}}
    <section class="panel-surface" style="margin-bottom: 16px;">
        <div class="woh-filter-bar">
            <div>
                <label class="form-label" for="searchInput"><i class="bi bi-search me-1"></i>Ara</label>
                <input id="searchInput" class="form-control" placeholder="Sipariş no, ürün adı veya görev no yazın...">
            </div>
            <div>
                <label class="form-label" for="statusFilter">Durum</label>
                <select id="statusFilter" class="form-select">
                    <option value="">Tümü</option>
                </select>
            </div>
            <label class="woh-filter-check">
                <input id="alertsOnly" type="checkbox">
                Sadece sorunlu
            </label>
            <label class="woh-filter-check">
                <input id="includeOld" type="checkbox">
                Eski kayıtlar
            </label>
        </div>
    </section>

    {{-- ── Timeline Feed ── --}}
    <section class="panel-surface">
        <div class="section-header compact">
            <div>
                <div class="section-overline" id="feedOverline">Sipariş Akışı</div>
                <h3 class="section-title" id="feedTitle">Siparişe Göre İş Emri Takibi</h3>
                <p class="section-copy" id="feedHint">Her siparişin altındaki iş emri ve görev hareketleri birlikte gösterilir.</p>
            </div>
            <div>
                <div class="woh-view-switch" role="group" aria-label="Görünüm">
                    <button type="button" id="viewOrdersBtn" class="active" onclick="setCenterView('orders')"><i class="bi bi-bag-check"></i>Siparişler</button>
                    <button type="button" id="viewTimelineBtn" onclick="setCenterView('timeline')"><i class="bi bi-clock-history"></i>Hareketler</button>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportCenterFeed()"><i class="bi bi-download me-1"></i>CSV</button>
            </div>
        </div>

        <div id="feedContainer" class="woh-feed">
            <div class="woh-empty">
                <i class="bi bi-hourglass-split"></i>
                <p>Veriler yükleniyor...</p>
            </div>
        </div>

        <div class="woh-pagination">
            <div class="woh-pagination-info" id="paginationInfo">Hazırlanıyor...</div>
            <div class="woh-pagination-btns">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrev" disabled><i class="bi bi-chevron-left me-1"></i>Önceki</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNext" disabled>Sonraki<i class="bi bi-chevron-right ms-1"></i></button>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
/* ───────── State ───────── */
let feedData = [];
let centerView = localStorage.getItem('workOrderCenterView') || 'orders';
let pageMeta = { current_page: 1, last_page: 1, per_page: 12, total: 0 };
let expandedId = null;

/* ───────── Helpers ───────── */
function esc(v) {
    if (v == null) return '';
    return String(v).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function humanStatus(s) {
    const map = {
        'UretimBekliyor': 'Üretim Bekliyor',
        'IsEmriVerildi': 'İş Emri Verildi',
        'UretimdenKarsilaniyor': 'Üretimden Karşılanıyor',
        'StokKarsilandi': 'Stoktan Karşılandı',
        'PasifDevamEden': 'Pasif (Devam Ediyor)',
        'Pasif': 'Pasif',
        'IptalEdildi': 'İptal Edildi',
    };
    return map[String(s || '')] || s || 'Bilinmiyor';
}

function statusBadgeClass(s) {
    switch (String(s || '')) {
        case 'IptalEdildi': return 'danger';
        case 'StokKarsilandi': return 'success';
        case 'IsEmriVerildi': return 'dark';
        case 'UretimBekliyor': return 'warning';
        case 'PasifDevamEden': return '';
        default: return 'primary';
    }
}

function dotClass(s) {
    switch (String(s || '')) {
        case 'IptalEdildi': return 'cancelled';
        case 'StokKarsilandi': return 'stock';
        case 'UretimBekliyor': return 'warning';
        default: return '';
    }
}

function setCenterView(view) {
    centerView = view === 'timeline' ? 'timeline' : 'orders';
    localStorage.setItem('workOrderCenterView', centerView);
    pageMeta.current_page = 1;
    pageMeta.per_page = centerView === 'orders' ? 12 : 20;
    expandedId = null;
    applyCenterViewLabels();
    loadCenterFeed(true);
}

function applyCenterViewLabels() {
    const isOrders = centerView === 'orders';
    document.getElementById('viewOrdersBtn').classList.toggle('active', isOrders);
    document.getElementById('viewTimelineBtn').classList.toggle('active', !isOrders);
    document.getElementById('metricTotalLabel').textContent = isOrders ? 'Toplam Sipariş' : 'Toplam Kayıt';
    document.getElementById('feedOverline').textContent = isOrders ? 'Sipariş Akışı' : 'Zaman Çizelgesi';
    document.getElementById('feedTitle').textContent = isOrders ? 'Siparişe Göre İş Emri Takibi' : 'İş Emri Hareketleri';
}

function buildTitle(item) {
    const orderNo = item?.order_no ? String(item.order_no) : '';
    const woNo = item?.work_order_no ? `Görev #${item.work_order_no}` : '';
    if (orderNo && woNo) return `${orderNo} · ${woNo}`;
    if (orderNo) return orderNo;
    if (woNo) return woNo;
    return `Kayıt #${item?.aggregate_id || item?.id || '-'}`;
}

function formatSource(ev) {
    const screen = ev?.source_screen || '';
    const action = ev?.source_action || '';
    if (screen && action) return `${screen} / ${action}`;
    return screen || action || '';
}

function formatJson(v) {
    if (!v) return 'Veri yok';
    try { return JSON.stringify(v, null, 2); } catch { return String(v); }
}

/* ───────── Build Query ───────── */
function buildParams() {
    const p = new URLSearchParams();
    const q = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const alerts = document.getElementById('alertsOnly').checked;
    const old = document.getElementById('includeOld').checked;
    if (q) p.set('q', q);
    if (status) p.set('status', status);
    if (alerts) p.set('has_alerts', '1');
    if (old) p.set('include_seeded', '1');
    p.set('per_page', String(pageMeta.per_page || 20));
    return p;
}

function exportCenterFeed() {
    const params = buildParams();
    window.location.href = '/api/work-order-center/export?' + params.toString();
}

/* ───────── Load Lookups ───────── */
async function loadLookups() {
    try {
        const r = await fetch('/api/work-order-center/lookups');
        const d = await r.json();
        if (!d.success) return;
        const sel = document.getElementById('statusFilter');
        for (const s of (d.data.statuses || [])) {
            sel.insertAdjacentHTML('beforeend', `<option value="${esc(s)}">${esc(humanStatus(s))}</option>`);
        }
    } catch {}
}

/* ───────── Load Feed ───────── */
async function loadCenterFeed(reset = false) {
    const params = buildParams();
    if (reset) pageMeta.current_page = 1;
    params.set('page', String(pageMeta.current_page || 1));

    document.getElementById('feedHint').textContent = 'Yükleniyor...';

    try {
        const endpoint = centerView === 'orders' ? '/api/work-order-center/orders' : '/api/work-order-center/feed';
        const r = await fetch(endpoint + '?' + params.toString());
        const d = await r.json();

        if (!d.success) {
            document.getElementById('feedContainer').innerHTML = `<div class="woh-empty"><i class="bi bi-exclamation-triangle"></i><p>${esc(d.message || 'Veriler alınamadı.')}</p></div>`;
            return;
        }

        feedData = d.data || [];
        pageMeta = { ...pageMeta, ...(d.meta || {}) };

        // Update metrics
        document.getElementById('metricTotal').textContent = Number(pageMeta.total || 0).toLocaleString('tr-TR');
        document.getElementById('metricPage').textContent = `${pageMeta.current_page}/${pageMeta.last_page}`;
        document.getElementById('metricTime').textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });

        const alertCount = feedData.filter(i => Number(centerView === 'orders' ? (i.alert_count || 0) : (i.snapshot?.alert_count || 0)) > 0).length;
        document.getElementById('metricAlerts').textContent = String(alertCount);

        document.getElementById('feedHint').textContent = document.getElementById('alertsOnly').checked
            ? 'Sadece sorunlu kayıtlar gösteriliyor.'
            : (centerView === 'orders'
                ? 'Her siparişin altındaki iş emri ve görev hareketleri birlikte gösterilir.'
                : 'En son yapılan işlemler kronolojik sırayla gösterilir.');

        const expandedStillVisible = expandedId
            ? feedData.some(i => i.id === expandedId)
            : false;

        if (!expandedStillVisible) {
            expandedId = feedData.length ? feedData[0].id : null;
        }

        renderFeed();
        renderPagination();

        if (expandedId) {
            if (centerView === 'orders') {
                renderOrderDetail(expandedId);
            } else {
                await loadDetailForItem(expandedId);
            }
        }
    } catch (e) {
        document.getElementById('feedContainer').innerHTML = `<div class="woh-empty"><i class="bi bi-wifi-off"></i><p>Sunucu hatası</p><small>${esc(e.message)}</small></div>`;
    }
}

/* ───────── Render Feed ───────── */
function renderOrderFeed(container) {
    container.innerHTML = feedData.map(order => {
        const status = order.current_status || '';
        const summary = order.task_summary || {};
        const counts = order.counts || {};
        const hasAlerts = Number(order.alert_count || 0) > 0;
        const isExpanded = expandedId === order.id;
        const activeCount = Number(summary.active ?? counts.active_tasks ?? 0);
        const readyCount = Number(summary.ready ?? counts.ready_tasks ?? 0);
        const waitingCount = Number(summary.waiting ?? counts.assigned_waiting_tasks ?? 0);
        const completedCount = Number(summary.completed ?? counts.completed_tasks ?? 0);
        const poolCount = Number(summary.pool ?? counts.pool ?? 0);
        const orderTitle = order.order_no ? `Sipariş ${order.order_no}` : `Sipariş satırı #${order.order_item_no || '-'}`;
        const latest = order.latest_event_title
            ? `Son hareket: ${order.latest_event_title}`
            : (order.next_expected_action || 'Bu sipariş için güncel hareket bekleniyor.');

        return `
        <div class="woh-event" data-id="${order.id}">
            <div class="woh-event-rail">
                <div class="woh-event-dot ${dotClass(status)}"></div>
                <div class="woh-event-line"></div>
            </div>
            <div class="woh-order-card ${isExpanded ? 'expanded' : ''}" onclick="toggleDetail(${order.id})">
                <div class="woh-order-main">
                    <div>
                        <div class="woh-order-title-row">
                            <h4 class="woh-order-title">${esc(orderTitle)}</h4>
                            ${order.work_order_no ? `<span class="woh-event-tag"><i class="bi bi-clipboard-check"></i>İş emri #${esc(order.work_order_no)}</span>` : ''}
                            ${order.order_item_no ? `<span class="woh-event-tag">Satır #${esc(order.order_item_no)}</span>` : ''}
                        </div>
                        <p class="woh-order-copy">${esc(latest)}</p>
                        <div class="woh-order-stats">
                            ${order.current_holder_name ? `<span class="woh-event-tag"><i class="bi bi-person-workspace"></i>${esc(order.current_holder_name)}</span>` : ''}
                            <span class="woh-event-tag"><i class="bi bi-diagram-3"></i>${poolCount} havuz</span>
                            <span class="woh-event-tag"><i class="bi bi-list-task"></i>${activeCount} aktif</span>
                            ${readyCount ? `<span class="woh-event-tag"><i class="bi bi-play-circle"></i>${readyCount} hazır</span>` : ''}
                            ${waitingCount ? `<span class="woh-event-tag"><i class="bi bi-hourglass-split"></i>${waitingCount} bekleyen</span>` : ''}
                            <span class="woh-event-tag"><i class="bi bi-check2-circle"></i>${completedCount} biten</span>
                            ${hasAlerts ? `<span class="woh-event-tag" style="background:var(--z-warning-soft);color:var(--z-warning);"><i class="bi bi-exclamation-triangle"></i>${order.alert_count} sorun</span>` : ''}
                        </div>
                    </div>
                    <span class="soft-badge ${statusBadgeClass(status)}">${esc(humanStatus(status))}</span>
                </div>
                <div class="woh-detail ${isExpanded ? 'open' : ''}" id="detail-${order.id}">
                    <div id="detail-content-${order.id}">${isExpanded ? '' : ''}</div>
                </div>
            </div>
        </div>`;
    }).join('');
}

function renderFeed() {
    const container = document.getElementById('feedContainer');

    if (!feedData.length) {
        container.innerHTML = `<div class="woh-empty"><i class="bi bi-inbox"></i><p>Gösterilecek kayıt bulunamadı.</p><small>Filtre ayarlarınızı değiştirmeyi deneyin.</small></div>`;
        return;
    }

    if (centerView === 'orders') {
        renderOrderFeed(container);
        return;
    }

    container.innerHTML = feedData.map(item => {
        const snap = item.snapshot || {};
        const status = snap.current_status || item.status_after || item.status_before || '';
        const nextStep = snap.next_expected_action || item.next_step_human || '';
        const hasAlerts = Number(snap.alert_count || 0) > 0;
        const isExpanded = expandedId === item.id;

        return `
        <div class="woh-event" data-id="${item.id}">
            <div class="woh-event-rail">
                <div class="woh-event-dot ${dotClass(status)}"></div>
                <div class="woh-event-line"></div>
            </div>
            <div class="woh-event-card ${isExpanded ? 'expanded' : ''}" onclick="toggleDetail(${item.id})">
                <div class="woh-event-top">
                    <div>
                        <h4 class="woh-event-title">${esc(buildTitle(item))}</h4>
                        <p class="woh-event-subtitle">${esc(item.title_human || 'Kayıt güncellendi')}</p>
                    </div>
                    <span class="soft-badge ${statusBadgeClass(status)}">${esc(humanStatus(status))}</span>
                </div>
                <div class="woh-event-tags">
                    ${item.happened_at ? `<span class="woh-event-tag"><i class="bi bi-clock"></i>${esc(item.happened_at)}</span>` : ''}
                    ${item.actor_name ? `<span class="woh-event-tag"><i class="bi bi-person"></i>${esc(item.actor_name)}</span>` : ''}
                    ${hasAlerts ? `<span class="woh-event-tag" style="background:var(--z-warning-soft);color:var(--z-warning);"><i class="bi bi-exclamation-triangle"></i>${snap.alert_count} Sorun</span>` : ''}
                    ${nextStep ? `<span class="woh-event-tag"><i class="bi bi-arrow-right-circle"></i>${esc(nextStep)}</span>` : ''}
                </div>
                <div class="woh-detail ${isExpanded ? 'open' : ''}" id="detail-${item.id}">
                    <div id="detail-content-${item.id}">
                        <div style="padding:12px 0;text-align:center;color:var(--z-text-muted);font-size:0.82rem;">
                            <i class="bi bi-arrow-repeat" style="animation:spin 1s linear infinite;display:inline-block;"></i> Detaylar yükleniyor...
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ───────── Toggle Detail ───────── */
async function toggleDetail(id) {
    if (expandedId === id) {
        expandedId = null;
        renderFeed();
        return;
    }

    expandedId = id;
    renderFeed();

    if (centerView === 'orders') {
        renderOrderDetail(id);
        return;
    }

    await loadDetailForItem(id);
}

function renderOrderDetail(id) {
    const order = feedData.find(e => e.id === id);
    if (!order) return;

    const tasks = order.tasks || [];
    const timeline = order.timeline || [];
    const status = order.current_status || '';
    const detail = document.getElementById(`detail-content-${id}`);
    if (!detail) return;

    let html = '';

    html += `<div class="woh-diag-alert ${Number(order.alert_count || 0) > 0 ? 'warn' : 'ok'}">
        <strong>${Number(order.alert_count || 0) > 0 ? 'Dikkat gerekiyor' : 'Durum net'}</strong>
        ${esc(order.next_expected_action || 'Bu siparişin güncel iş emri ve görev bağlantıları aşağıda birlikte görünüyor.')}
    </div>`;

    html += '<div class="woh-detail-section-title">Sipariş Özeti</div>';
    html += '<div class="woh-detail-grid">';
    html += detailRow('Durum', humanStatus(status));
    html += detailRow('Şu anda kimde?', order.current_holder_name || '-');
    html += detailRow('Sipariş / Satır', `${order.order_no || '-'} / #${order.order_item_no || '-'}`);
    html += detailRow('İş emri', order.work_order_no ? `#${order.work_order_no}` : '-');
    html += detailRow('İlk hareket', order.first_event_at || '-');
    html += detailRow('Son hareket', order.latest_event_at || order.last_changed_at || '-');
    html += '</div>';

    html += '<div class="woh-detail-section-title">Görevler</div>';
    if (tasks.length) {
        html += '<div class="woh-order-task-list">';
        tasks.forEach(task => {
            const meta = [
                task.holder_name,
                task.department_name,
                task.quantity != null ? `Adet ${task.quantity}` : '',
                task.remaining_quantity != null ? `Bekleyen ${task.remaining_quantity}` : '',
                task.wait_reason,
                task.started_at,
                task.completed_at ? `Bitiş ${task.completed_at}` : '',
            ].filter(Boolean).join(' · ');
            html += `<div class="woh-order-task">
                <div>
                    <strong>${esc(task.title || 'Görev')}</strong>
                    <span>${esc(meta || task.latest_event || 'Görev bağlantısı olay kayıtlarından bulundu.')}</span>
                </div>
                <span class="soft-badge ${esc(task.status_class || '')}">${esc(task.status_label || 'Güncel')}</span>
            </div>`;
        });
        html += '</div>';
    } else {
        html += '<div class="woh-diag-alert info">Bu sipariş için bağlı görev satırı bulunamadı; olay akışından takip edilebilir.</div>';
    }

    html += '<div class="woh-detail-section-title">Sipariş Akışı</div>';
    if (timeline.length) {
        html += '<div class="woh-history-steps">';
        timeline.slice(0, 12).forEach(step => {
            const ref = taskRef(step);
            html += `<div class="woh-history-step">
                <div class="woh-history-step-icon"></div>
                <div class="woh-history-step-text">
                    <strong>${esc(step.title_human || '-')}</strong>
                    <span>${esc([step.happened_at, step.actor_name, ref].filter(Boolean).join(' · '))}</span>
                </div>
            </div>`;
        });
        html += '</div>';
    } else {
        html += '<div class="woh-diag-alert info">Bu sipariş için olay geçmişi henüz oluşmamış.</div>';
    }

    detail.innerHTML = html;
}

function taskRef(step) {
    if (step?.personnel_task_no) return `Personel görevi #${step.personnel_task_no}`;
    if (step?.pool_no) return `Havuz #${step.pool_no}`;
    if (step?.work_order_no) return `İş emri #${step.work_order_no}`;
    if (step?.special_production_no) return `Özel üretim #${step.special_production_no}`;
    return '';
}

async function loadDetailForItem(id) {
    const item = feedData.find(e => e.id === id);
    if (!item) return;

    const type = item.order_item_no
        ? 'order_item'
        : (item.work_order_no ? 'work_order' : (item.aggregate_type || 'event'));
    const entityId = item.order_item_no || item.work_order_no || item.aggregate_id;
    if (!entityId) {
        document.getElementById(`detail-content-${id}`).innerHTML = '<div class="woh-diag-alert info">Bu kayıt için detay bilgisi bulunamadı.</div>';
        return;
    }

    try {
        const r = await fetch(`/api/work-order-center/entity/${type}/${entityId}`);
        const d = await r.json();
        if (d.success && d.data) {
            renderDetailContent(id, d.data);
        } else {
            document.getElementById(`detail-content-${id}`).innerHTML = '<div class="woh-diag-alert info">Detay verisi alınamadı.</div>';
        }
    } catch (e) {
        document.getElementById(`detail-content-${id}`).innerHTML = `<div class="woh-diag-alert error"><strong>Hata</strong>${esc(e.message)}</div>`;
    }
}

/* ───────── Render Detail Content ───────── */
function renderDetailContent(id, payload) {
    const entity = payload.entity || {};
    const narration = payload.narration || {};
    const diagnostics = payload.diagnostics || [];
    const timeline = payload.timeline || [];
    const latestEvent = timeline[0] || {};

    let html = '';

    // ── Summary
    if (narration.short) {
        html += `<div class="woh-diag-alert ${Number(entity.alert_count || 0) > 0 ? 'warn' : 'ok'}"><strong>${Number(entity.alert_count || 0) > 0 ? '⚠ Dikkat gerekiyor' : '✓ Durum net'}</strong>${esc(narration.short)}</div>`;
    }

    // ── Key Info
    html += '<div class="woh-detail-section-title">Bilgiler</div>';
    html += '<div class="woh-detail-grid">';
    html += detailRow('Durum', humanStatus(entity.current_status));
    html += detailRow('Şu anda kimde?', entity.current_holder_name || '-');
    html += detailRow('En son ne oldu?', latestEvent.title_human || '-');
    html += detailRow('Ne zaman?', latestEvent.happened_at || entity.last_changed_at || '-');
    html += detailRow('Kim yaptı?', latestEvent.actor_name || 'Sistem');
    html += detailRow('Nereden?', formatSource(latestEvent) || '-');
    html += detailRow('Sipariş / Görev', `${entity.order_no || '-'} / ${entity.work_order_no ? '#' + entity.work_order_no : '-'}`);
    const nextStep = entity.next_expected_action || latestEvent.next_step_human || '-';
    html += detailRow('Sıradaki iş', nextStep);
    html += '</div>';

    // ── Diagnostics
    if (diagnostics.length) {
        html += '<div class="woh-detail-section-title">Sorunlar</div>';
        diagnostics.forEach(a => {
            const cls = a.severity === 'high' ? 'error' : (a.severity === 'medium' ? 'warn' : 'info');
            html += `<div class="woh-diag-alert ${cls}"><strong>${esc(a.message || 'Uyarı')}</strong>${esc(a.suggested_fix || 'İnceleme önerilir.')}</div>`;
        });
    }

    // ── Timeline
    if (timeline.length) {
        html += '<div class="woh-detail-section-title">Son Adımlar</div>';
        html += '<div class="woh-history-steps">';
        timeline.slice(0, 5).forEach(step => {
            html += `<div class="woh-history-step">
                <div class="woh-history-step-icon"></div>
                <div class="woh-history-step-text">
                    <strong>${esc(step.title_human || '-')}</strong>
                    <span>${esc([step.happened_at, step.actor_name].filter(Boolean).join(' · '))}</span>
                </div>
            </div>`;
        });
        html += '</div>';
    }

    document.getElementById(`detail-content-${id}`).innerHTML = html;
}

function detailRow(label, value) {
    return `<div class="woh-detail-row"><span class="label">${esc(label)}</span><span class="value">${esc(value)}</span></div>`;
}

/* ───────── Pagination ───────── */
function renderPagination() {
    const { total, current_page, last_page, per_page } = pageMeta;
    const from = total === 0 ? 0 : ((current_page - 1) * per_page) + 1;
    const to = total === 0 ? 0 : Math.min(total, current_page * per_page);

    document.getElementById('paginationInfo').textContent = total === 0
        ? 'Kayıt bulunamadı'
        : `${from}–${to} arası / toplam ${Number(total).toLocaleString('tr-TR')} kayıt`;

    document.getElementById('btnPrev').disabled = current_page <= 1;
    document.getElementById('btnNext').disabled = current_page >= last_page;
}

/* ───────── Init ───────── */
document.addEventListener('DOMContentLoaded', async () => {
    pageMeta.per_page = centerView === 'orders' ? 12 : 20;
    applyCenterViewLabels();
    await loadLookups();
    await loadCenterFeed(true);

    document.getElementById('searchInput').addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') await loadCenterFeed(true);
    });

    ['statusFilter', 'alertsOnly', 'includeOld'].forEach(id => {
        document.getElementById(id).addEventListener('change', async () => {
            await loadCenterFeed(true);
        });
    });

    document.getElementById('btnPrev').addEventListener('click', async () => {
        if (pageMeta.current_page <= 1) return;
        pageMeta.current_page--;
        expandedId = null;
        await loadCenterFeed(false);
    });

    document.getElementById('btnNext').addEventListener('click', async () => {
        if (pageMeta.current_page >= pageMeta.last_page) return;
        pageMeta.current_page++;
        expandedId = null;
        await loadCenterFeed(false);
    });
});

/* Spin animation for loading */
const styleTag = document.createElement('style');
styleTag.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
document.head.appendChild(styleTag);
</script>
@endpush
