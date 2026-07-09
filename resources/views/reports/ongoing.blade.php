@extends('layouts.app')

@section('title', 'Devam Eden Görevler')

@section('page-actions')
    <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" id="refreshOngoingBtn" onclick="loadOngoingTasks(true)">
        <i class="bi bi-arrow-clockwise"></i>
        <span>Yenile</span>
    </button>
@endsection

@push('styles')
<style>
    .ongoing-page {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .ongoing-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
    }

    .ongoing-stat {
        min-height: 104px;
        padding: 16px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-card);
        display: grid;
        grid-template-columns: 44px 1fr;
        gap: 12px;
        align-items: center;
    }

    .ongoing-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        font-size: 1.22rem;
    }

    .ongoing-stat-icon.production { background: #e8f5ee; color: #159461; }
    .ongoing-stat-icon.waiting { background: #fff4df; color: #b26a00; }
    .ongoing-stat-icon.ready { background: #e6f2fb; color: #2179b8; }
    .ongoing-stat-icon.people { background: #eef0f5; color: #596273; }

    .ongoing-stat-value {
        font-size: 1.65rem;
        line-height: 1;
        font-weight: 760;
        color: var(--z-text-primary);
        font-variant-numeric: tabular-nums;
    }

    .ongoing-stat-label {
        margin-top: 6px;
        color: var(--z-text-secondary);
        font-size: 0.82rem;
    }

    .ongoing-toolbar {
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-card);
        padding: 12px;
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 220px auto;
        gap: 10px;
        align-items: center;
    }

    .ongoing-search,
    .ongoing-select {
        height: 40px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: #fff;
        color: var(--z-text-primary);
        font-size: 0.9rem;
    }

    .ongoing-search {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 12px;
    }

    .ongoing-search input {
        border: 0;
        outline: 0;
        width: 100%;
        min-width: 0;
        background: transparent;
        color: inherit;
    }

    .ongoing-segment {
        display: inline-grid;
        grid-auto-flow: column;
        gap: 4px;
        padding: 4px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: #f7f8fa;
    }

    .ongoing-segment button {
        min-width: 86px;
        height: 30px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: var(--z-text-secondary);
        font-weight: 650;
        font-size: 0.82rem;
    }

    .ongoing-segment button.active {
        background: #15998f;
        color: #fff;
    }

    .ongoing-board {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 16px;
        align-items: start;
    }

    .ongoing-panel {
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-card);
        overflow: hidden;
    }

    .ongoing-panel-header {
        min-height: 56px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--z-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .ongoing-panel-title {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 760;
        color: var(--z-text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ongoing-panel-meta {
        color: var(--z-text-secondary);
        font-size: 0.8rem;
        white-space: nowrap;
    }

    .personnel-list,
    .idle-list {
        display: flex;
        flex-direction: column;
    }

    .personnel-card {
        border-bottom: 1px solid var(--z-border);
    }

    .personnel-card:last-child {
        border-bottom: 0;
    }

    .personnel-head {
        padding: 14px;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        background: #fbfcfd;
    }

    .personnel-name {
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 760;
        color: var(--z-text-primary);
    }

    .personnel-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: #eaf3f2;
        color: #15998f;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
    }

    .personnel-subtitle {
        margin-top: 3px;
        color: var(--z-text-secondary);
        font-size: 0.8rem;
    }

    .personnel-chips {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        flex-wrap: wrap;
    }

    .metric-chip,
    .status-chip {
        min-height: 28px;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 0.76rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .metric-chip {
        border: 1px solid var(--z-border);
        color: var(--z-text-primary);
        background: #fff;
    }

    .status-chip.production { background: #e8f5ee; color: #137a51; }
    .status-chip.waiting { background: #fff4df; color: #9c5c00; }
    .status-chip.ready { background: #e6f2fb; color: #1d679d; }

    /* Üretimde olan görevler için sarı vurgu */
    .task-row.in-production {
        background: linear-gradient(90deg, #fffbe6 0%, #fff9e0 100%);
        border-left: 4px solid #f0a500;
    }
    .task-row.in-production:hover {
        background: linear-gradient(90deg, #fff6cc 0%, #fff3b3 100%);
    }
    .in-production-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #f0a500;
        color: #fff;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    .personnel-head.has-production {
        background: linear-gradient(135deg, #fffbe6, #f8f4e8);
        border-left: 3px solid #f0a500;
    }

    .task-list {
        display: flex;
        flex-direction: column;
    }

    .task-row {
        padding: 12px 14px;
        display: grid;
        grid-template-columns: minmax(220px, 1.15fr) minmax(190px, 0.9fr) 150px 120px;
        gap: 14px;
        align-items: center;
        border-top: 1px solid #eef0f3;
    }

    .task-title {
        color: var(--z-text-primary);
        font-weight: 720;
        font-size: 0.9rem;
        overflow-wrap: anywhere;
    }

    .task-subtitle {
        margin-top: 3px;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        overflow-wrap: anywhere;
    }

    .task-meta {
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
    }

    .task-progress {
        min-width: 0;
    }

    .task-progress-line {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        color: var(--z-text-secondary);
        font-size: 0.76rem;
        margin-bottom: 6px;
    }

    .progress-track {
        height: 8px;
        border-radius: 999px;
        background: #edf0f3;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #15998f, #3ea0d9);
        transition: width 0.25s ease;
    }

    .task-qty {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px;
    }

    .qty-box {
        min-height: 48px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 6px 8px;
        background: #fff;
        text-align: center;
    }

    .qty-box strong {
        font-size: 1rem;
        line-height: 1;
        font-variant-numeric: tabular-nums;
        color: var(--z-text-primary);
    }

    .qty-box span {
        margin-top: 4px;
        color: var(--z-text-secondary);
        font-size: 0.68rem;
    }

    .idle-item {
        padding: 12px 14px;
        border-top: 1px solid #eef0f3;
        display: grid;
        gap: 8px;
    }

    .idle-person {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .idle-name {
        min-width: 0;
        font-weight: 720;
        color: var(--z-text-primary);
        overflow-wrap: anywhere;
    }

    .idle-dept {
        margin-top: 2px;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
    }

    .idle-note {
        padding: 8px 10px;
        border-radius: 8px;
        background: #f7f8fa;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
    }

    .state-message {
        min-height: 180px;
        display: grid;
        place-items: center;
        text-align: center;
        color: var(--z-text-secondary);
        padding: 28px;
    }

    .state-message i {
        display: block;
        font-size: 1.8rem;
        margin-bottom: 8px;
        color: #15998f;
    }

    .state-message.error i {
        color: #c0392b;
    }

    @media (max-width: 1180px) {
        .ongoing-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .ongoing-board {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 820px) {
        .ongoing-toolbar {
            grid-template-columns: 1fr;
        }

        .ongoing-segment {
            width: 100%;
            grid-auto-flow: row;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ongoing-segment button {
            width: 100%;
        }

        .task-row {
            grid-template-columns: 1fr;
        }

        .personnel-head {
            grid-template-columns: 1fr;
        }

        .personnel-chips {
            justify-content: flex-start;
        }
    }

    @media (max-width: 560px) {
        .ongoing-stats {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="container-fluid ongoing-page">
    <div class="ongoing-stats" id="summaryCards" aria-live="polite">
        <div class="ongoing-stat">
            <div class="ongoing-stat-icon production"><i class="bi bi-play-circle"></i></div>
            <div>
                <div class="ongoing-stat-value" id="statActive">-</div>
                <div class="ongoing-stat-label">Açık görev</div>
            </div>
        </div>
        <div class="ongoing-stat">
            <div class="ongoing-stat-icon" style="background:#fff4df;color:#f0a500;"><i class="bi bi-lightning-charge"></i></div>
            <div>
                <div class="ongoing-stat-value" id="statInProduction">-</div>
                <div class="ongoing-stat-label">Şu an üretimde</div>
            </div>
        </div>
        <div class="ongoing-stat">
            <div class="ongoing-stat-icon ready"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="ongoing-stat-value" id="statReady">-</div>
                <div class="ongoing-stat-label">Hazır adet</div>
            </div>
        </div>
        <div class="ongoing-stat">
            <div class="ongoing-stat-icon waiting"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="ongoing-stat-value" id="statWaiting">-</div>
                <div class="ongoing-stat-label">Bekleyen adet</div>
            </div>
        </div>
        <div class="ongoing-stat">
            <div class="ongoing-stat-icon people"><i class="bi bi-people"></i></div>
            <div>
                <div class="ongoing-stat-value" id="statPersonnel">-</div>
                <div class="ongoing-stat-label">Aktif personel</div>
            </div>
        </div>
    </div>

    <div class="ongoing-toolbar">
        <label class="ongoing-search" for="ongoingSearch">
            <i class="bi bi-search text-muted"></i>
            <input type="search" id="ongoingSearch" placeholder="Personel, ürün, ara ürün veya sipariş ara" autocomplete="off">
        </label>
        <select class="form-select ongoing-select" id="departmentFilter" aria-label="Bölüm filtresi">
            <option value="">Tüm bölümler</option>
        </select>
        <div class="ongoing-segment" role="tablist" aria-label="Görev görünümü">
            <button type="button" class="active" data-view="active">Aktif</button>
            <button type="button" data-view="working">Çalışıyor</button>
            <button type="button" data-view="idle">Boşta</button>
            <button type="button" data-view="all">Tümü</button>
        </div>
    </div>

    <div class="ongoing-board">
        <section class="ongoing-panel">
            <div class="ongoing-panel-header">
                <h3 class="ongoing-panel-title"><i class="bi bi-kanban"></i>Üretim Akışı</h3>
                <span class="ongoing-panel-meta" id="activeMeta">-</span>
            </div>
            <div class="personnel-list" id="activeList">
                <div class="state-message">
                    <div><i class="bi bi-arrow-clockwise"></i>Yükleniyor...</div>
                </div>
            </div>
        </section>

        <aside class="ongoing-panel">
            <div class="ongoing-panel-header">
                <h3 class="ongoing-panel-title"><i class="bi bi-person-check"></i>Boştaki Personel</h3>
                <span class="ongoing-panel-meta" id="idleMeta">-</span>
            </div>
            <div class="idle-list" id="idleList">
                <div class="state-message">
                    <div><i class="bi bi-arrow-clockwise"></i>Yükleniyor...</div>
                </div>
            </div>
        </aside>
    </div>

    <div class="text-muted text-center" style="font-size:0.78rem;" id="lastUpdate">
        Son güncelleme: -
    </div>
</div>
@endsection

@push('scripts')
<script>
const ongoingState = {
    data: { active_personnel: [], idle_personnel: [], summary: {} },
    view: 'active',
    search: '',
    department: '',
};

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('ongoingSearch').addEventListener('input', (event) => {
        ongoingState.search = event.target.value.trim().toLocaleLowerCase('tr-TR');
        renderOngoingTasks();
    });

    document.getElementById('departmentFilter').addEventListener('change', (event) => {
        ongoingState.department = event.target.value;
        renderOngoingTasks();
    });

    document.querySelectorAll('.ongoing-segment button').forEach((button) => {
        button.addEventListener('click', () => {
            ongoingState.view = button.dataset.view || 'active';
            document.querySelectorAll('.ongoing-segment button').forEach((item) => {
                item.classList.toggle('active', item === button);
            });
            renderOngoingTasks();
        });
    });

    loadOngoingTasks();
    setInterval(() => loadOngoingTasks(false), 30000);
});

async function loadOngoingTasks(manual = false) {
    const button = document.getElementById('refreshOngoingBtn');
    if (button) button.disabled = true;

    try {
        const response = await fetch('/api/database/personnel/production-overview', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Veri alınamadı.');
        }

        ongoingState.data = {
            active_personnel: Array.isArray(data.active_personnel) ? data.active_personnel : [],
            idle_personnel: Array.isArray(data.idle_personnel) ? data.idle_personnel : [],
            summary: data.summary || {},
            generated_at: data.generated_at || '',
        };
        populateDepartmentFilter();
        renderOngoingTasks();
    } catch (error) {
        renderError(error.message || 'Veri alınamadı.');
    } finally {
        if (button) button.disabled = false;
        if (manual) {
            document.getElementById('lastUpdate').textContent = `Son güncelleme: ${new Date().toLocaleTimeString('tr-TR')}`;
        }
    }
}

function populateDepartmentFilter() {
    const select = document.getElementById('departmentFilter');
    const current = select.value;
    const departments = new Map();

    [...ongoingState.data.active_personnel, ...ongoingState.data.idle_personnel].forEach((person) => {
        const id = String(person.department_id || '');
        const name = person.department_name || 'Bölüm tanımsız';
        if (id !== '') departments.set(id, name);
    });

    const options = ['<option value="">Tüm bölümler</option>'];
    [...departments.entries()]
        .sort((a, b) => a[1].localeCompare(b[1], 'tr'))
        .forEach(([id, name]) => {
            options.push(`<option value="${escAttr(id)}">${escHtml(name)}</option>`);
        });

    select.innerHTML = options.join('');
    select.value = departments.has(current) ? current : '';
    ongoingState.department = select.value;
}

function renderOngoingTasks() {
    const summary = ongoingState.data.summary || {};
    const activePeople = filterPeople(ongoingState.data.active_personnel, true);
    const idlePeople = filterPeople(ongoingState.data.idle_personnel, false);

    // Uretimde olan gorev sayisini hesapla
    let inProductionCount = 0;
    ongoingState.data.active_personnel.forEach(person => {
        (person.active_tasks || []).forEach(task => {
            if (task.is_in_production === true) inProductionCount++;
        });
    });

    document.getElementById('statActive').textContent = fmt(summary.active_task_count || 0);
    document.getElementById('statInProduction').textContent = fmt(inProductionCount);
    document.getElementById('statReady').textContent = fmt(summary.ready_quantity || 0);
    document.getElementById('statWaiting').textContent = fmt(summary.waiting_quantity || 0);
    document.getElementById('statPersonnel').textContent = fmt(summary.active_personnel || 0);

    // "Çalışıyor" filtresi: sadece uretimde olanlari goster
    const displayPeople = ongoingState.view === 'working'
        ? activePeople.map(person => ({
            ...person,
            active_tasks: (person.active_tasks || []).filter(t => t.is_in_production === true)
        })).filter(person => person.active_tasks.length > 0)
        : activePeople;

    document.getElementById('activeMeta').textContent = `${fmt(displayPeople.length)} personel`;
    document.getElementById('idleMeta').textContent = `${fmt(idlePeople.length)} personel`;

    const viewLabels = { active: 'Üretim Akışı', working: 'Şu An Çalışanlar', idle: 'Boşta', all: 'Tümü' };
    document.querySelector('.ongoing-panel-title').innerHTML = `<i class="bi bi-kanban"></i>${viewLabels[ongoingState.view] || 'Üretim Akışı'}`;

    const showActive = ongoingState.view === 'active' || ongoingState.view === 'all' || ongoingState.view === 'working';
    const showIdle = ongoingState.view === 'idle' || ongoingState.view === 'all';

    document.querySelector('.ongoing-board').style.gridTemplateColumns = showActive && showIdle
        ? ''
        : '1fr';
    document.querySelector('.ongoing-board aside').style.display = showIdle ? '' : 'none';
    document.querySelector('.ongoing-board section').style.display = showActive ? '' : 'none';

    renderActiveList(displayPeople);
    renderIdleList(idlePeople);

    const time = ongoingState.data.generated_at || new Date().toLocaleTimeString('tr-TR');
    document.getElementById('lastUpdate').textContent = `Son güncelleme: ${time}`;
}

function renderActiveList(people) {
    const container = document.getElementById('activeList');
    if (people.length === 0) {
        const emptyMsg = ongoingState.view === 'working'
            ? 'Şu anda üretimde olan görev yok'
            : 'Aktif görev bulunamadı';
        const emptyIcon = ongoingState.view === 'working' ? 'bi-pause-circle' : 'bi-check-circle';
        container.innerHTML = `<div class="state-message"><div><i class="bi ${emptyIcon}"></i>${emptyMsg}</div></div>`;
        return;
    }

    container.innerHTML = people.map((person) => {
        const summary = person.summary || {};
        const tasks = filteredTasks(person);
        const taskRows = tasks.map(renderTaskRow).join('');
        const latest = person.latest_completed
            ? `Son biten: ${taskName(person.latest_completed)} (${fmt(person.latest_completed.quantity || 0)} adet)`
            : 'Tamamlanan kayıt yok';

        // Bu personelde uretimde olan gorev var mi?
        const hasProduction = tasks.some(t => t.is_in_production);
        const headClass = hasProduction ? 'personnel-head has-production' : 'personnel-head';

        // Uretimde sayisi
        const inProdCount = tasks.filter(t => t.is_in_production).length;
        const inProdBadge = inProdCount > 0
            ? `<span class="in-production-badge"><i class="bi bi-play-circle"></i>${inProdCount} üretiyor</span>`
            : '';

        return `
            <article class="personnel-card">
                <div class="${headClass}">
                    <div>
                        <div class="personnel-name">
                            <span class="personnel-avatar"><i class="bi bi-person"></i></span>
                            <span>${escHtml(person.full_name || 'Personel')}</span>
                            ${inProdBadge}
                        </div>
                        <div class="personnel-subtitle">${escHtml(person.department_name || 'Bölüm tanımsız')} · ${escHtml(latest)}</div>
                    </div>
                    <div class="personnel-chips">
                        <span class="metric-chip">${fmt(summary.active_task_count || tasks.length)} görev</span>
                        <span class="status-chip ready">${fmt(summary.ready_quantity || 0)} hazır</span>
                        <span class="status-chip waiting">${fmt(summary.waiting_quantity || 0)} bekleyen</span>
                    </div>
                </div>
                <div class="task-list">${taskRows}</div>
            </article>
        `;
    }).join('');
}

function renderTaskRow(task) {
    const ready = Number(task.ready_quantity || 0);
    const waiting = Number(task.waiting_quantity || 0);
    const open = Number(task.open_quantity || ready + waiting);
    const percent = Math.max(0, Math.min(100, Number(task.readiness_percent || (open > 0 ? Math.round((ready / open) * 100) : 0))));
    const status = taskStatus(task);
    const orderNo = task.order?.order_no || '';
    const customer = task.order?.customer || '';
    const startedAt = task.started_at || '-';
    const isInProduction = task.is_in_production === true;
    const rowClass = isInProduction ? 'task-row in-production' : 'task-row';

    const prodBadge = isInProduction
        ? `<span class="in-production-badge"><i class="bi bi-play-fill"></i>Çalışıyor</span>`
        : '';

    return `
        <div class="${rowClass}">
            <div>
                <div class="task-title">${escHtml(taskName(task))} ${prodBadge}</div>
                <div class="task-subtitle">${escHtml(task.component_name || task.department_name || '-')}</div>
            </div>
            <div class="task-meta">
                <span><i class="bi bi-calendar2-week me-1"></i>${escHtml(startedAt)}</span>
                ${orderNo ? `<span><i class="bi bi-link-45deg me-1"></i>${escHtml(orderNo)}${customer ? ` · ${escHtml(customer)}` : ''}</span>` : ''}
            </div>
            <div class="task-progress">
                <div class="task-progress-line">
                    <span>${escHtml(task.status_label || status.label)}</span>
                    <strong>${percent}%</strong>
                </div>
                <div class="progress-track"><div class="progress-fill" style="width:${percent}%"></div></div>
            </div>
            <div class="task-qty">
                <div class="qty-box"><strong>${fmt(ready)}</strong><span>hazır</span></div>
                <div class="qty-box"><strong>${fmt(waiting)}</strong><span>bekleyen</span></div>
            </div>
        </div>
    `;
}

function renderIdleList(people) {
    const container = document.getElementById('idleList');
    if (people.length === 0) {
        container.innerHTML = `<div class="state-message"><div><i class="bi bi-people"></i>Boşta personel bulunamadı</div></div>`;
        return;
    }

    container.innerHTML = people.map((person) => {
        const poolCount = Number(person.department_pool_task_count || 0);
        const poolQuantity = Number(person.department_pool_quantity || 0);
        const badge = poolCount > 0
            ? `<span class="status-chip ready">${fmt(poolQuantity)} havuzda</span>`
            : `<span class="metric-chip">havuz yok</span>`;

        return `
            <div class="idle-item">
                <div class="idle-person">
                    <div>
                        <div class="idle-name">${escHtml(person.full_name || 'Personel')}</div>
                        <div class="idle-dept">${escHtml(person.department_name || 'Bölüm tanımsız')}</div>
                    </div>
                    ${badge}
                </div>
                <div class="idle-note">${escHtml(person.recommendation || 'Yeni görev bekliyor.')}</div>
            </div>
        `;
    }).join('');
}

function filterPeople(people, active) {
    return (people || [])
        .map((person) => active ? { ...person, active_tasks: filteredTasks(person) } : person)
        .filter((person) => {
            if (ongoingState.department && String(person.department_id || '') !== ongoingState.department) {
                return false;
            }

            if (!ongoingState.search) {
                return !active || (person.active_tasks || []).length > 0;
            }

            const haystack = [
                person.full_name,
                person.department_name,
                person.recommendation,
                ...(person.active_tasks || []).flatMap((task) => [
                    task.product_name,
                    task.component_name,
                    task.status_label,
                    task.order?.order_no,
                    task.order?.customer,
                ]),
            ].filter(Boolean).join(' ').toLocaleLowerCase('tr-TR');

            return haystack.includes(ongoingState.search) && (!active || (person.active_tasks || []).length > 0);
        });
}

function filteredTasks(person) {
    const tasks = Array.isArray(person.active_tasks) ? person.active_tasks : [];
    if (!ongoingState.search) {
        return tasks;
    }

    return tasks.filter((task) => {
        const haystack = [
            task.product_name,
            task.component_name,
            task.department_name,
            task.status_label,
            task.order?.order_no,
            task.order?.customer,
            person.full_name,
            person.department_name,
        ].filter(Boolean).join(' ').toLocaleLowerCase('tr-TR');

        return haystack.includes(ongoingState.search);
    });
}

function taskStatus(task) {
    const ready = Number(task.ready_quantity || 0);
    const waiting = Number(task.waiting_quantity || 0);
    const label = (task.status_label || '').toLocaleLowerCase('tr-TR');
    const isInProduction = task.is_in_production === true;

    if (isInProduction) {
        return { key: 'production', label: 'Üretimde' };
    }
    if (waiting > 0 && ready <= 0) {
        return { key: 'waiting', label: 'Bekliyor' };
    }
    if (label.includes('onay') || label.includes('hazır')) {
        return { key: 'ready', label: 'Hazır' };
    }
    return { key: 'production', label: 'Üretimde' };
}

function taskName(task) {
    return task.product_name || task.component_name || task.line_product_name || 'Görev';
}

function renderError(message) {
    document.getElementById('activeList').innerHTML = `<div class="state-message error"><div><i class="bi bi-exclamation-triangle"></i>${escHtml(message)}</div></div>`;
    document.getElementById('idleList').innerHTML = `<div class="state-message error"><div><i class="bi bi-exclamation-triangle"></i>${escHtml(message)}</div></div>`;
}

function fmt(value) {
    return new Intl.NumberFormat('tr-TR').format(Number(value || 0));
}

function escHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
}

function escAttr(value) {
    return escHtml(value).replace(/"/g, '&quot;');
}
</script>
@endpush
