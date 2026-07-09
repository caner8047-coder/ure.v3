@extends('layouts.app')

@section('title', 'Personel Üretim Takibi')

@section('page-actions')
    <a href="{{ route('production.planning') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-calendar-check me-1"></i>Planlama
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadOverview()">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<style>
    .personnel-command-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .personnel-toolbar {
        display: grid;
        grid-template-columns: minmax(240px, 1fr) auto;
        gap: 12px;
        align-items: center;
    }

    .production-section {
        margin-bottom: 18px;
    }

    .production-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }

    .production-section-head h3 {
        font-size: 0.98rem;
        margin: 0;
    }

    .production-section-head p {
        color: var(--z-text-secondary);
        font-size: 0.82rem;
        margin-top: 3px;
    }

    .production-personnel-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 12px;
    }

    .production-person-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: 8px;
        padding: 16px;
        min-width: 0;
    }

    .production-person-head {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        margin-bottom: 14px;
    }

    .person-avatar {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #e0f2fe;
        color: #0369a1;
        font-weight: 700;
        font-size: 0.82rem;
        flex: 0 0 auto;
    }

    .person-avatar.idle {
        background: #f3f4f6;
        color: #4b5563;
    }

    .person-name {
        font-weight: 700;
        color: var(--z-text);
        overflow-wrap: anywhere;
    }

    .person-subline {
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        margin-top: 2px;
    }

    .production-mini-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 14px;
    }

    .production-mini-stat {
        border: 1px solid var(--z-border-light);
        border-radius: 6px;
        padding: 8px;
        background: #fafafa;
        min-width: 0;
    }

    .production-mini-stat span {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.68rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .production-mini-stat strong {
        display: block;
        margin-top: 2px;
        font-size: 0.95rem;
        color: var(--z-text);
    }

    .production-task-list {
        display: grid;
        gap: 8px;
    }

    .production-task-row {
        border: 1px solid var(--z-border-light);
        border-radius: 8px;
        padding: 10px;
        background: #ffffff;
    }

    .production-task-main {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: flex-start;
    }

    .production-task-title {
        font-weight: 700;
        color: var(--z-text);
        font-size: 0.86rem;
        overflow-wrap: anywhere;
    }

    .production-task-copy {
        color: var(--z-text-secondary);
        font-size: 0.76rem;
        margin-top: 2px;
        overflow-wrap: anywhere;
    }

    .production-task-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 9px;
    }

    .task-quantity-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
        margin-top: 10px;
    }

    .task-quantity {
        border-radius: 6px;
        background: var(--z-bg-soft);
        padding: 7px 8px;
    }

    .task-quantity span {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.66rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .task-quantity strong {
        display: block;
        font-size: 0.88rem;
        margin-top: 1px;
    }

    .readiness-bar {
        height: 6px;
        border-radius: 99px;
        background: #e5e7eb;
        overflow: hidden;
        margin-top: 10px;
    }

    .readiness-bar span {
        display: block;
        height: 100%;
        background: #0d9488;
        border-radius: inherit;
    }

    .idle-person-cell {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 220px;
    }

    .idle-person-cell .person-avatar {
        width: 36px;
        height: 36px;
    }

    .empty-state {
        border: 1px dashed var(--z-border);
        border-radius: 8px;
        background: #ffffff;
        padding: 28px;
        text-align: center;
        color: var(--z-text-secondary);
    }

    @media (max-width: 992px) {
        .personnel-command-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .production-mini-stats,
        .task-quantity-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .personnel-command-grid,
        .personnel-toolbar,
        .production-personnel-grid,
        .production-section-head {
            grid-template-columns: 1fr;
        }

        .production-section-head,
        .production-task-main,
        .production-person-head {
            display: grid;
            grid-template-columns: 1fr;
        }

        .production-person-head {
            gap: 8px;
        }
    }
</style>
@endpush

@section('content')
    <div class="personnel-command-grid">
        <article class="metric-card">
            <p class="metric-label">Üretimdeki Personel</p>
            <h3 class="metric-value" id="activePersonnelMetric">0</h3>
            <span class="soft-badge primary mt-2" id="activeTaskMetric">0 aktif kayıt</span>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Açık Adet</p>
            <h3 class="metric-value" id="openQuantityMetric">0</h3>
            <span class="soft-badge success mt-2" id="readyQuantityMetric">0 üretilebilir</span>
        </article>
        <article class="metric-card">
            <p class="metric-label">Bekleyen Adet</p>
            <h3 class="metric-value" id="waitingQuantityMetric">0</h3>
            <span class="soft-badge warning mt-2">Stok/ön adım bekleyen</span>
        </article>
        <article class="metric-card">
            <p class="metric-label">Boştaki Personel</p>
            <h3 class="metric-value" id="idlePersonnelMetric">0</h3>
            <span class="soft-badge mt-2" id="poolMetric">0 havuz işi</span>
        </article>
    </div>

    <div class="panel-surface">
        <div class="personnel-toolbar">
            <div>
                <label for="personnelSearch" class="form-label">Personel, bölüm, ürün veya sipariş ara</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="personnelSearch" class="form-control" placeholder="Örn. Kesim, Ahmet, sandalye, SP-2026">
                </div>
            </div>
            <div class="d-flex align-items-end gap-2 flex-wrap">
                <span class="soft-badge" id="overviewUpdatedAt">Yükleniyor</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSearch()">
                    <i class="bi bi-x-lg me-1"></i>Temizle
                </button>
            </div>
        </div>
    </div>

    <section class="production-section">
        <div class="production-section-head">
            <div>
                <h3>Üretimdeki personeller</h3>
                <p>Personelin üzerindeki aktif üretim kayıtları, açık adetleri ve sipariş bağlantıları.</p>
            </div>
            <span class="soft-badge primary" id="activeSectionCount">0 kayıt</span>
        </div>
        <div class="production-personnel-grid" id="activePersonnelList">
            <div class="empty-state">Üretim verileri yükleniyor...</div>
        </div>
    </section>

    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy">
                <p>Kapasite</p>
                <h3>Boştaki personeller</h3>
            </div>
            <div class="panel-toolbar-meta">
                <span class="soft-badge" id="idleSectionCount">0 kişi</span>
            </div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th>Personel</th>
                        <th>Bölüm</th>
                        <th>Bölüm Havuzu</th>
                        <th>Geçmiş Üretim</th>
                        <th>Son Kayıt</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody id="idlePersonnelBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
let personnelOverview = {
    active_personnel: [],
    idle_personnel: [],
    summary: {}
};

const formatter = new Intl.NumberFormat('tr-TR');

document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('personnelSearch');
    if (search) {
        search.addEventListener('input', () => renderOverview());
    }

    loadOverview();
});

function loadOverview() {
    setLoadingState();

    fetch('/api/database/personnel/production-overview')
        .then((response) => {
            if (!response.ok) throw new Error('Üretim personel verisi alınamadı.');
            return response.json();
        })
        .then((data) => {
            personnelOverview = data || { active_personnel: [], idle_personnel: [], summary: {} };
            renderOverview();
        })
        .catch((error) => {
            document.getElementById('activePersonnelList').innerHTML = renderEmptyState(error.message || 'Veri alınamadı.');
            document.getElementById('idlePersonnelBody').innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Veri alınamadı.</td></tr>`;
            document.getElementById('overviewUpdatedAt').textContent = 'Hata';
        });
}

function setLoadingState() {
    document.getElementById('activePersonnelList').innerHTML = renderEmptyState('Üretim verileri yükleniyor...');
    document.getElementById('idlePersonnelBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>';
    document.getElementById('overviewUpdatedAt').textContent = 'Yükleniyor';
}

function renderOverview() {
    const summary = personnelOverview.summary || {};
    const active = filterActivePersonnel(personnelOverview.active_personnel || []);
    const idle = filterIdlePersonnel(personnelOverview.idle_personnel || []);

    document.getElementById('activePersonnelMetric').textContent = fmt(summary.active_personnel);
    document.getElementById('activeTaskMetric').textContent = `${fmt(summary.active_task_count)} aktif kayıt`;
    document.getElementById('openQuantityMetric').textContent = fmt(summary.open_quantity);
    document.getElementById('readyQuantityMetric').textContent = `${fmt(summary.ready_quantity)} üretilebilir`;
    document.getElementById('waitingQuantityMetric').textContent = fmt(summary.waiting_quantity);
    document.getElementById('idlePersonnelMetric').textContent = fmt(summary.idle_personnel);
    document.getElementById('poolMetric').textContent = `${fmt(summary.pool_task_count)} havuz işi`;
    document.getElementById('overviewUpdatedAt').textContent = `Son güncelleme: ${personnelOverview.generated_at || '—'}`;
    document.getElementById('activeSectionCount').textContent = `${fmt(active.length)} personel`;
    document.getElementById('idleSectionCount').textContent = `${fmt(idle.length)} kişi`;

    renderActivePersonnel(active);
    renderIdlePersonnel(idle);
    bindAssignButtons();
}

function filterActivePersonnel(items) {
    const term = getSearchTerm();
    if (!term) return items;

    return items.filter((person) => {
        const taskText = (person.active_tasks || []).map((task) => [
            task.product_name,
            task.component_name,
            task.order?.order_no,
            task.order?.customer,
            task.order?.line_product_name,
        ].join(' ')).join(' ');

        return normalize([
            person.full_name,
            person.department_name,
            taskText,
        ].join(' ')).includes(term);
    });
}

function filterIdlePersonnel(items) {
    const term = getSearchTerm();
    if (!term) return items;

    return items.filter((person) => normalize([
        person.full_name,
        person.department_name,
        person.recommendation,
        person.latest_completed?.component_name,
        person.latest_completed?.product_name,
    ].join(' ')).includes(term));
}

function renderActivePersonnel(items) {
    const target = document.getElementById('activePersonnelList');

    if (!items.length) {
        target.innerHTML = renderEmptyState('Arama kriterine uygun üretimde personel yok.');
        return;
    }

    target.innerHTML = items.map((person) => {
        const summary = person.summary || {};
        const latest = person.latest_completed;

        return `
            <article class="production-person-card">
                <header class="production-person-head">
                    <div class="person-avatar">${escapeHtml(initials(person.full_name))}</div>
                    <div>
                        <div class="person-name">${escapeHtml(person.full_name || 'İsimsiz personel')}</div>
                        <div class="person-subline">${escapeHtml(person.department_name || 'Bölüm tanımsız')} · Sicil ${escapeHtml(person.personnel_no || '-')}</div>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-assign-personnel data-personnel-no="${escapeAttr(person.personnel_no)}" data-full-name="${escapeAttr(person.full_name || '')}" title="Planlamaya git">
                        <i class="bi bi-calendar-plus"></i>
                    </button>
                </header>

                <div class="production-mini-stats">
                    ${miniStat('Aktif kayıt', summary.active_task_count)}
                    ${miniStat('Açık adet', summary.open_quantity)}
                    ${miniStat('Üretilebilir', summary.ready_quantity)}
                    ${miniStat('Bekleyen', summary.waiting_quantity)}
                </div>

                <div class="production-task-list">
                    ${(person.active_tasks || []).map((task) => renderTaskRow(task)).join('')}
                </div>

                <div class="mt-3 pt-3" style="border-top: 1px solid var(--z-border-light);">
                    <span class="soft-badge success">${fmt(summary.completed_quantity)} adet geçmiş üretim</span>
                    ${latest ? `<span class="soft-badge ms-1">Son: ${escapeHtml(latest.component_name || latest.product_name || 'Kayıt')} · ${escapeHtml(latest.finished_at || '-')}</span>` : ''}
                </div>
            </article>
        `;
    }).join('');
}

function renderTaskRow(task) {
    const order = task.order || {};
    const orderParts = [
        order.order_no ? `Sipariş ${order.order_no}` : '',
        order.customer ? order.customer : '',
        task.started_at ? `Başlangıç ${task.started_at}` : '',
        order.deadline ? `Teslim ${order.deadline}` : '',
    ].filter(Boolean);

    return `
        <div class="production-task-row">
            <div class="production-task-main">
                <div>
                    <div class="production-task-title">${escapeHtml(task.component_name || 'Ara ürün tanımsız')}</div>
                    <div class="production-task-copy">${escapeHtml(task.product_name || order.line_product_name || 'Ürün bilgisi yok')}</div>
                </div>
                <span class="soft-badge ${task.waiting_quantity > 0 ? 'warning' : 'success'}">${escapeHtml(task.status_label || 'Üretimde')}</span>
            </div>

            <div class="production-task-meta">
                <span class="soft-badge dark">Görev #${escapeHtml(task.id || '-')}</span>
                ${orderParts.map((part) => `<span class="soft-badge">${escapeHtml(part)}</span>`).join('')}
            </div>

            <div class="task-quantity-grid">
                ${quantityCell('Toplam', task.open_quantity)}
                ${quantityCell('Üretilebilir', task.ready_quantity)}
                ${quantityCell('Bekleyen', task.waiting_quantity)}
            </div>

            <div class="readiness-bar" title="Üretilebilir oran">
                <span style="width: ${Math.max(0, Math.min(100, Number(task.readiness_percent || 0)))}%"></span>
            </div>
        </div>
    `;
}

function renderIdlePersonnel(items) {
    const target = document.getElementById('idlePersonnelBody');

    if (!items.length) {
        target.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Boşta personel bulunmuyor.</td></tr>';
        return;
    }

    target.innerHTML = items.map((person) => {
        const latest = person.latest_completed;
        const hasPool = Number(person.department_pool_task_count || 0) > 0;

        return `
            <tr>
                <td>
                    <div class="idle-person-cell">
                        <div class="person-avatar idle">${escapeHtml(initials(person.full_name))}</div>
                        <div>
                            <div class="person-name">${escapeHtml(person.full_name || 'İsimsiz personel')}</div>
                            <div class="person-subline">Sicil ${escapeHtml(person.personnel_no || '-')}</div>
                        </div>
                    </div>
                </td>
                <td>${escapeHtml(person.department_name || '-')}</td>
                <td>
                    <strong>${fmt(person.department_pool_task_count)} iş</strong>
                    <div class="text-muted small">${fmt(person.department_pool_quantity)} toplam · ${fmt(person.department_ready_pool_quantity)} hazır</div>
                </td>
                <td>
                    <strong>${fmt(person.completed_quantity)} adet</strong>
                    <div class="text-muted small">${fmt(person.completed_record_count)} kayıt</div>
                </td>
                <td>
                    ${latest
                        ? `<strong>${escapeHtml(latest.component_name || latest.product_name || 'Kayıt')}</strong><div class="text-muted small">${escapeHtml(latest.finished_at || '-')}</div>`
                        : '<span class="text-muted">Kayıt yok</span>'}
                </td>
                <td><span class="soft-badge ${hasPool ? 'primary' : ''}">${escapeHtml(person.recommendation || 'Görev bekliyor')}</span></td>
                <td>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-assign-personnel data-personnel-no="${escapeAttr(person.personnel_no)}" data-full-name="${escapeAttr(person.full_name || '')}">
                        <i class="bi bi-person-plus me-1"></i>Görev Ata
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function bindAssignButtons() {
    document.querySelectorAll('[data-assign-personnel]').forEach((button) => {
        button.addEventListener('click', () => assignTask(button.dataset.personnelNo));
    });
}

function assignTask(personnelNo) {
    window.location.href = `/uretim-planlama?personel_no=${encodeURIComponent(personnelNo || '')}`;
}

function clearSearch() {
    const search = document.getElementById('personnelSearch');
    search.value = '';
    renderOverview();
}

function miniStat(label, value) {
    return `<div class="production-mini-stat"><span>${escapeHtml(label)}</span><strong>${fmt(value)}</strong></div>`;
}

function quantityCell(label, value) {
    return `<div class="task-quantity"><span>${escapeHtml(label)}</span><strong>${fmt(value)}</strong></div>`;
}

function renderEmptyState(message) {
    return `<div class="empty-state">${escapeHtml(message)}</div>`;
}

function getSearchTerm() {
    const input = document.getElementById('personnelSearch');
    return normalize(input ? input.value : '');
}

function normalize(value) {
    return String(value || '').toLocaleLowerCase('tr-TR').trim();
}

function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '—';
    return parts.slice(0, 2).map((part) => part.charAt(0)).join('').toLocaleUpperCase('tr-TR');
}

function fmt(value) {
    return formatter.format(Number(value || 0));
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}
</script>
@endpush
