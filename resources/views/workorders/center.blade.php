@extends('layouts.app')

@section('title', 'Is Emri Merkezi')

@section('page-actions')
    <a href="{{ route('workorders.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Yeni Emir</a>
    <a href="{{ route('workorders.bulk') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Toplu Is Emri</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadCenterFeed(true)"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
@endsection

@push('styles')
<style>
    .center-toolbar-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(180px, 1fr) auto auto;
        gap: 12px;
        align-items: end;
    }
    .center-feed-list,
    .center-history-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .center-feed-item {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 14px;
        cursor: pointer;
        transition: all var(--z-transition);
    }
    .center-feed-item:hover {
        background: var(--z-bg-soft);
        border-color: #d1d5db;
    }
    .center-feed-item.active {
        background: var(--z-accent-soft);
        border-color: var(--z-accent);
    }
    .center-feed-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .center-feed-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--z-text);
    }
    .center-feed-status {
        font-size: 0.82rem;
        color: var(--z-text-secondary);
        margin-top: 4px;
    }
    .center-feed-copy {
        font-size: 0.83rem;
        color: var(--z-text-secondary);
        margin-top: 10px;
        line-height: 1.55;
    }
    .center-feed-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
        font-size: 0.76rem;
        color: var(--z-text-muted);
    }
    .center-answer {
        padding: 16px;
        border-radius: var(--z-radius);
        border: 1px solid var(--z-border);
        background: var(--z-bg-soft);
    }
    .center-answer.problem {
        background: var(--z-warning-soft);
        border-color: rgba(217, 119, 6, 0.18);
    }
    .center-answer h3 {
        font-size: 0.92rem;
        margin: 0 0 6px;
    }
    .center-answer p {
        margin: 0;
        color: var(--z-text-secondary);
        line-height: 1.6;
        font-size: 0.84rem;
    }
    .center-history-item {
        padding: 12px 14px;
        border-radius: var(--z-radius);
        border: 1px solid var(--z-border);
        background: var(--z-bg-soft);
    }
    .center-history-item strong {
        display: block;
        margin-bottom: 4px;
        font-size: 0.84rem;
    }
    .center-history-item p {
        margin: 0;
        font-size: 0.82rem;
        color: var(--z-text-secondary);
        line-height: 1.55;
    }
    .center-history-meta {
        margin-top: 8px;
        font-size: 0.75rem;
        color: var(--z-text-muted);
    }
    .center-empty {
        padding: 24px;
        border: 1px dashed var(--z-border);
        border-radius: var(--z-radius);
        background: var(--z-bg-soft);
        text-align: center;
        color: var(--z-text-muted);
        font-size: 0.84rem;
    }
    .center-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: 14px;
        flex-wrap: wrap;
    }
    .center-pagination-copy {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
    }
    .center-json {
        white-space: pre-wrap;
        word-break: break-word;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: 0.78rem;
        background: #111827;
        color: #e5eefc;
        border-radius: var(--z-radius);
        padding: 14px;
        margin-top: 12px;
        max-height: 280px;
        overflow: auto;
    }
    @media (max-width: 1024px) {
        .center-toolbar-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 640px) {
        .center-toolbar-grid {
            grid-template-columns: 1fr;
        }
        .center-feed-head {
            flex-direction: column;
        }
    }
</style>
@endpush

@section('content')
    <div class="dashboard-hero">
        <section class="panel-surface hero-banner">
            <span class="hero-kicker">Is Emri</span>
            <h2 class="hero-title">Is Emri Merkezi</h2>
            <p class="hero-description">Bir kaydi secince su anki durumu, en son ne oldugunu, kim yaptigini ve sirada ne oldugunu kolayca gor.</p>
            <div class="hero-stats">
                <div class="mini-metric">
                    <span>Gorunen Kayit</span>
                    <strong id="centerMetricTotal">0</strong>
                </div>
                <div class="mini-metric">
                    <span>Secili Durum</span>
                    <strong id="centerMetricStatus">-</strong>
                </div>
                <div class="mini-metric">
                    <span>Acik Sorun</span>
                    <strong id="centerMetricAlerts">0</strong>
                </div>
            </div>
        </section>
    </div>

    <section class="panel-surface">
        <div class="section-header compact">
            <div>
                <div class="section-overline">Filtre</div>
                <h3 class="section-title">Kaydi bul</h3>
                <p class="section-copy">Arama yap, duruma bak veya sadece sorunlu kayitlari goster.</p>
            </div>
        </div>

        <div class="center-toolbar-grid">
            <div>
                <label class="form-label" for="centerSearchInput">Arama</label>
                <input id="centerSearchInput" class="form-control" placeholder="Siparis no, gorev no veya urun yaz">
            </div>
            <div>
                <label class="form-label" for="centerStatus">Durum</label>
                <select id="centerStatus" class="form-select">
                    <option value="">Hepsi</option>
                </select>
            </div>
            <label class="form-check" style="padding-bottom: 8px;">
                <input id="centerOnlyAlerts" type="checkbox" class="form-check-input">
                <span class="form-check-label">Sadece sorunlu</span>
            </label>
            <label class="form-check" style="padding-bottom: 8px;">
                <input id="centerIncludeSeeded" type="checkbox" class="form-check-input">
                <span class="form-check-label">Eski aktarim kayitlari</span>
            </label>
        </div>
    </section>

    <div class="content-grid">
        <section class="panel-surface">
            <div class="section-header compact">
                <div>
                    <div class="section-overline">Liste</div>
                    <h3 class="section-title">Kayitlar</h3>
                    <p class="section-copy" id="centerFeedHint">En yeni anlamli hareketler listelenir.</p>
                </div>
            </div>

            <div id="centerFeedList" class="center-feed-list">
                <div class="center-empty">Veriler yukleniyor...</div>
            </div>

            <div class="center-pagination">
                <div class="center-pagination-copy" id="centerPaginationCopy">Liste hazirlaniyor...</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="centerPrevPage">Onceki</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="centerNextPage">Sonraki</button>
                </div>
            </div>
        </section>

        <div id="centerDetailStack" class="stack-list">
            <section class="panel-surface">
                <div class="section-header compact">
                    <div>
                        <div class="section-overline">Detay</div>
                        <h3 class="section-title">Secili Kayit</h3>
                        <p class="section-copy">Soldan bir kayit secince sade ozet burada gorunur.</p>
                    </div>
                </div>
                <div class="center-empty">Soldan bir kayit sec.</div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script>
let centerFeed = [];
let centerSelected = null;
let centerMeta = { current_page: 1, last_page: 1, per_page: 20, total: 0 };

function centerEscape(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function centerFormatJson(value) {
    if (!value) return 'Veri yok';
    try { return JSON.stringify(value, null, 2); } catch { return String(value); }
}

function centerHumanizeStatus(status) {
    switch (String(status || '')) {
        case 'UretimBekliyor': return 'Uretim bekliyor';
        case 'IsEmriVerildi': return 'Is emri verildi';
        case 'UretimdenKarsilaniyor': return 'Uretimden karsilaniyor';
        case 'StokKarsilandi': return 'Stoktan karsilandi';
        case 'PasifDevamEden': return 'Pasif ama uretimi suruyor';
        case 'Pasif': return 'Pasif';
        default: return status || 'Bilinmiyor';
    }
}

function centerHumanizeSource(event) {
    const screen = event?.source_screen || '';
    const action = event?.source_action || '';
    if (screen && action) return `${screen} / ${action}`;
    return screen || action || 'Bilinmiyor';
}

function centerBuildRecordTitle(record) {
    const orderNo = record?.order_no ? String(record.order_no) : '';
    const workOrderNo = record?.work_order_no ? `Gorev #${record.work_order_no}` : '';

    if (orderNo && workOrderNo) return `${orderNo} · ${workOrderNo}`;
    if (orderNo) return orderNo;
    if (workOrderNo) return workOrderNo;

    const aggregateType = record?.aggregate_type ? String(record.aggregate_type).replaceAll('_', ' ') : 'Kayit';
    const aggregateId = record?.aggregate_id || record?.id || '-';
    return `${aggregateType} #${aggregateId}`;
}

function centerCurrentParams() {
    const params = new URLSearchParams();
    const q = document.getElementById('centerSearchInput').value.trim();
    const status = document.getElementById('centerStatus').value;
    const onlyAlerts = document.getElementById('centerOnlyAlerts').checked;
    const includeSeeded = document.getElementById('centerIncludeSeeded').checked;

    if (q) params.set('q', q);
    if (status) params.set('status', status);
    if (onlyAlerts) params.set('has_alerts', '1');
    if (includeSeeded) params.set('include_seeded', '1');
    params.set('per_page', String(centerMeta.per_page || 20));

    return params;
}

async function loadCenterLookups() {
    const response = await fetch('/api/work-order-center/lookups');
    const data = await response.json();
    if (!data.success) return;

    const status = document.getElementById('centerStatus');
    for (const item of (data.data.statuses || [])) {
        status.insertAdjacentHTML('beforeend', `<option value="${centerEscape(item)}">${centerEscape(centerHumanizeStatus(item))}</option>`);
    }
}

async function loadCenterFeed(force = false) {
    const params = centerCurrentParams();
    if (force) {
        centerMeta.current_page = 1;
    }
    params.set('page', String(centerMeta.current_page || 1));

    document.getElementById('centerFeedHint').textContent = 'Yukleniyor...';

    try {
        const response = await fetch('/api/work-order-center/feed?' + params.toString());
        const data = await response.json();

        if (!data.success) {
            document.getElementById('centerFeedList').innerHTML = `<div class="center-empty">${centerEscape(data.message || 'Veri alinamadi.')}</div>`;
            document.getElementById('centerMetricTotal').textContent = '0';
            document.getElementById('centerPaginationCopy').textContent = 'Kayit yok';
            return;
        }

        centerFeed = data.data || [];
        centerMeta = { ...centerMeta, ...(data.meta || {}) };

        document.getElementById('centerMetricTotal').textContent = String(centerMeta.total || 0);
        document.getElementById('centerFeedHint').textContent = document.getElementById('centerOnlyAlerts').checked
            ? 'Sadece sorunlu kayitlar gosteriliyor.'
            : 'En yeni ve en anlamli hareketler listeleniyor.';

        renderCenterFeed();
        renderCenterPagination();

        const selectedStillVisible = centerSelected
            ? centerFeed.some((entry) => String(entry.id) === String(centerSelected.id))
            : false;

        if ((force || !centerSelected || !selectedStillVisible) && centerFeed.length) {
            await selectCenterEvent(centerFeed[0]);
        } else if (!centerFeed.length) {
            centerSelected = null;
            renderCenterDetail(null);
        }
    } catch (error) {
        document.getElementById('centerFeedList').innerHTML = `<div class="center-empty">Sunucu hatasi: ${centerEscape(error.message)}</div>`;
        document.getElementById('centerPaginationCopy').textContent = 'Kayit yok';
    }
}

function renderCenterPagination() {
    const total = Number(centerMeta.total || 0);
    const perPage = Number(centerMeta.per_page || 20);
    const currentPage = Number(centerMeta.current_page || 1);
    const lastPage = Number(centerMeta.last_page || 1);
    const from = total === 0 ? 0 : ((currentPage - 1) * perPage) + 1;
    const to = total === 0 ? 0 : Math.min(total, currentPage * perPage);

    document.getElementById('centerPaginationCopy').textContent = total === 0
        ? 'Kayit yok'
        : `${from}-${to} / ${total} kayit · Sayfa ${currentPage}/${lastPage}`;

    document.getElementById('centerPrevPage').disabled = currentPage <= 1;
    document.getElementById('centerNextPage').disabled = currentPage >= lastPage;
}

function renderCenterFeed() {
    const feedList = document.getElementById('centerFeedList');
    const includeSeeded = document.getElementById('centerIncludeSeeded').checked;

    if (!centerFeed.length) {
        feedList.innerHTML = includeSeeded
            ? '<div class="center-empty">Gosterilecek kayit bulunamadi.</div>'
            : '<div class="center-empty">Gosterilecek hareket bulunamadi. Istersen eski aktarim kayitlarini da acabilirsin.</div>';
        return;
    }

    feedList.innerHTML = centerFeed.map((item) => {
        const snapshot = item.snapshot || {};
        const isActive = centerSelected && centerSelected.id === item.id;
        const statusText = centerHumanizeStatus(snapshot.current_status || item.status_after || item.status_before);
        const nextStep = snapshot.next_expected_action || item.next_step_human || 'Sonraki adim bilgisi yok.';
        const meta = [item.happened_at, item.actor_name, centerHumanizeSource(item)].filter(Boolean).join(' · ');
        const hasAlerts = Number(snapshot.alert_count || 0) > 0;

        return `
            <article class="center-feed-item ${isActive ? 'active' : ''}" data-event-id="${item.id}">
                <div class="center-feed-head">
                    <div>
                        <div class="center-feed-title">${centerEscape(centerBuildRecordTitle(item))}</div>
                        <div class="center-feed-status">Su an: <strong>${centerEscape(statusText)}</strong></div>
                    </div>
                    <span class="soft-badge ${hasAlerts ? 'warning' : 'success'}">${hasAlerts ? `${snapshot.alert_count} sorun` : 'Normal'}</span>
                </div>
                <div class="center-feed-copy">
                    <strong>En son:</strong> ${centerEscape(item.title_human || 'Kayit guncellendi')}<br>
                    <strong>Siradaki is:</strong> ${centerEscape(nextStep)}
                </div>
                <div class="center-feed-meta">${centerEscape(meta)}</div>
            </article>
        `;
    }).join('');

    feedList.querySelectorAll('.center-feed-item').forEach((card) => {
        card.addEventListener('click', async () => {
            const item = centerFeed.find((entry) => String(entry.id) === String(card.dataset.eventId));
            if (item) {
                await selectCenterEvent(item);
            }
        });
    });
}

async function selectCenterEvent(item) {
    centerSelected = item;
    renderCenterFeed();

    const type = item.order_item_no
        ? 'order_item'
        : (item.work_order_no ? 'work_order' : (item.aggregate_type || 'event'));
    const id = item.order_item_no || item.work_order_no || item.aggregate_id;
    if (!id) {
        renderCenterDetail(null);
        return;
    }

    try {
        const response = await fetch(`/api/work-order-center/entity/${type}/${id}`);
        const data = await response.json();
        renderCenterDetail(data.success ? data.data : null);
    } catch (error) {
        renderCenterDetail(null, error.message);
    }
}

function renderCenterDetail(payload, error = '') {
    const stack = document.getElementById('centerDetailStack');

    if (!payload) {
        document.getElementById('centerMetricStatus').textContent = '-';
        document.getElementById('centerMetricAlerts').textContent = '0';
        stack.innerHTML = `
            <section class="panel-surface">
                <div class="section-header compact">
                    <div>
                        <div class="section-overline">Detay</div>
                        <h3 class="section-title">Secili Kayit</h3>
                        <p class="section-copy">Soldan bir kayit secince sade ozet burada gorunur.</p>
                    </div>
                </div>
                <div class="center-empty">${centerEscape(error || 'Soldan bir kayit sec.')}</div>
            </section>
        `;
        return;
    }

    const entity = payload.entity || {};
    const narration = payload.narration || {};
    const diagnostics = payload.diagnostics || [];
    const timeline = payload.timeline || [];
    const technical = payload.technical || {};
    const latestEvent = timeline[0] || {};
    const recordTitle = centerBuildRecordTitle(entity);
    const currentStatus = centerHumanizeStatus(entity.current_status);
    const issueCount = Number(entity.alert_count || 0);
    const nextStep = entity.next_expected_action || latestEvent.next_step_human || 'Sonraki adim bilgisi yok.';

    document.getElementById('centerMetricStatus').textContent = currentStatus;
    document.getElementById('centerMetricAlerts').textContent = String(issueCount);

    stack.innerHTML = `
        <section class="panel-surface">
            <div class="section-header compact">
                <div>
                    <div class="section-overline">Ozet</div>
                    <h3 class="section-title">${centerEscape(recordTitle)}</h3>
                    <p class="section-copy">Kisa ve anlasilir ozet</p>
                </div>
            </div>
            <div class="center-answer ${issueCount > 0 ? 'problem' : ''}">
                <h3>${issueCount > 0 ? 'Dikkat gerekiyor' : 'Durum net'}</h3>
                <p>${centerEscape(narration.short || 'Bu kayit icin ozet bulunamadi.')}</p>
            </div>
        </section>

        <section class="panel-surface">
            <div class="section-header compact">
                <div>
                    <div class="section-overline">Hizli Cevap</div>
                    <h3 class="section-title">Bilmek istedigin seyler</h3>
                </div>
            </div>
            <div class="info-list">
                <div class="info-row"><span>Su an ne durumda?</span><strong>${centerEscape(currentStatus || '-')}</strong></div>
                <div class="info-row"><span>Su anda kimde?</span><strong>${centerEscape(entity.current_holder_name || '-')}</strong></div>
                <div class="info-row"><span>En son ne oldu?</span><strong>${centerEscape(latestEvent.title_human || '-')}</strong></div>
                <div class="info-row"><span>Ne zaman oldu?</span><strong>${centerEscape(latestEvent.happened_at || entity.last_changed_at || '-')}</strong></div>
                <div class="info-row"><span>Kim yapti?</span><strong>${centerEscape(latestEvent.actor_name || 'Sistem')}</strong></div>
                <div class="info-row"><span>Nereden yapildi?</span><strong>${centerEscape(centerHumanizeSource(latestEvent))}</strong></div>
                <div class="info-row"><span>Siradaki is</span><strong>${centerEscape(nextStep)}</strong></div>
                <div class="info-row"><span>Siparis / Gorev</span><strong>${centerEscape((entity.order_no || '-') + ' / ' + (entity.work_order_no ? ('#' + entity.work_order_no) : '-'))}</strong></div>
            </div>
        </section>

        <section class="panel-surface">
            <div class="section-header compact">
                <div>
                    <div class="section-overline">Kontrol</div>
                    <h3 class="section-title">Sorun var mi?</h3>
                </div>
            </div>
            ${diagnostics.length ? diagnostics.map((alert) => `
                <div class="alert ${alert.severity === 'high' ? 'alert-danger' : (alert.severity === 'medium' ? 'alert-warning' : 'alert-info')} mb-3">
                    <div>
                        <strong>${centerEscape(alert.message || 'Uyari')}</strong>
                        <div class="small mt-2">${centerEscape(alert.suggested_fix || 'Inceleme onerilir.')}</div>
                    </div>
                </div>
            `).join('') : '<div class="alert alert-success mb-0"><div>Su an acik bir sorun gorunmuyor.</div></div>'}
        </section>

        <section class="panel-surface">
            <div class="section-header compact">
                <div>
                    <div class="section-overline">Gecmis</div>
                    <h3 class="section-title">Kisa hikaye</h3>
                </div>
            </div>
            ${timeline.length ? `
                <div class="center-history-list">
                    ${timeline.slice(0, 6).map((entry) => `
                        <article class="center-history-item">
                            <strong>${centerEscape(entry.title_human || '-')}</strong>
                            <p>${centerEscape(entry.summary_human || '-')}</p>
                            <div class="center-history-meta">${centerEscape([entry.happened_at, entry.actor_name, centerHumanizeSource(entry)].filter(Boolean).join(' · '))}</div>
                        </article>
                    `).join('')}
                </div>
            ` : '<div class="center-empty">Bu kayit icin gecmis adim bulunamadi.</div>'}
        </section>

        <section class="panel-surface">
            <details>
                <summary class="small text-muted" style="cursor:pointer;">Teknik detay</summary>
                <div class="center-json">${centerEscape(centerFormatJson(technical))}</div>
            </details>
        </section>
    `;
}

document.addEventListener('DOMContentLoaded', async () => {
    await loadCenterLookups();
    await loadCenterFeed(true);

    document.getElementById('centerSearchInput').addEventListener('keydown', async (event) => {
        if (event.key !== 'Enter') return;
        await loadCenterFeed(true);
    });

    ['centerStatus', 'centerOnlyAlerts', 'centerIncludeSeeded'].forEach((id) => {
        document.getElementById(id).addEventListener('change', async () => {
            await loadCenterFeed(true);
        });
    });

    document.getElementById('centerPrevPage').addEventListener('click', async () => {
        if ((centerMeta.current_page || 1) <= 1) return;
        centerMeta.current_page = Math.max(1, (centerMeta.current_page || 1) - 1);
        await loadCenterFeed(false);
    });

    document.getElementById('centerNextPage').addEventListener('click', async () => {
        if ((centerMeta.current_page || 1) >= (centerMeta.last_page || 1)) return;
        centerMeta.current_page = Math.min(centerMeta.last_page || 1, (centerMeta.current_page || 1) + 1);
        await loadCenterFeed(false);
    });
});
</script>
@endpush
