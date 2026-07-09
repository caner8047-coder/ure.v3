@extends('layouts.app')

@section('title', 'Görev Dağıtımı')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshAll()"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    /* ─── Metrics ─── */
    .ga-metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    .ga-metric {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 16px 18px;
        text-align: center;
    }
    .ga-metric-num {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--z-text);
        line-height: 1.2;
    }
    .ga-metric-num.accent { color: var(--z-accent); }
    .ga-metric-num.warning { color: var(--z-warning); }
    .ga-metric-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 4px;
    }

    /* ─── Filter ─── */
    .ga-filter {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: end;
    }

    /* ─── Task Cards ─── */
    .ga-tasks {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .ga-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        overflow: hidden;
        transition: all 0.2s ease;
    }
    .ga-card:hover {
        border-color: #d1d5db;
        box-shadow: var(--z-shadow-hover);
    }

    /* Card Header — always visible */
    .ga-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        cursor: pointer;
        gap: 12px;
        user-select: none;
    }
    .ga-card-header:hover {
        background: var(--z-bg-soft);
    }

    .ga-card-info {
        flex: 1;
        min-width: 0;
    }
    .ga-card-title {
        font-size: 0.92rem;
        font-weight: 600;
        color: var(--z-text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ga-card-sub {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
        margin-top: 3px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .ga-card-sub span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .ga-card-sub i { font-size: 0.75rem; opacity: 0.6; }
    .ga-card-detail {
        margin-top: 6px;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        color: var(--z-text-muted);
        font-size: 0.72rem;
        font-weight: 700;
    }
    .ga-card-detail span {
        background: var(--z-bg-soft);
        border-radius: 6px;
        padding: 3px 7px;
    }

    .ga-card-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }

    .ga-amount-pill {
        background: var(--z-accent-soft);
        color: var(--z-accent);
        font-weight: 700;
        font-size: 0.92rem;
        padding: 6px 14px;
        border-radius: 20px;
        white-space: nowrap;
    }

    .ga-expand-icon {
        color: var(--z-text-muted);
        font-size: 1rem;
        transition: transform 0.2s ease;
    }
    .ga-card.open .ga-expand-icon {
        transform: rotate(180deg);
    }

    /* Card Body — assignment form */
    .ga-card-body {
        display: none;
        padding: 0 20px 20px;
        border-top: 1px solid var(--z-border-light);
    }
    .ga-card.open .ga-card-body {
        display: block;
    }

    .ga-assign-form {
        display: grid;
        grid-template-columns: minmax(220px, 1fr) 150px 120px auto;
        gap: 12px;
        align-items: end;
        margin-top: 16px;
    }

    .ga-assign-form .btn {
        height: 38px;
    }

    /* Personnel workload preview */
    .ga-crew-section {
        margin-top: 16px;
    }
    .ga-crew-title {
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--z-text-muted);
        margin-bottom: 8px;
    }
    .ga-crew-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 8px;
    }
    .ga-crew-chip {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 12px;
        background: var(--z-bg-soft);
        border-radius: var(--z-radius-sm);
        font-size: 0.82rem;
        cursor: pointer;
        transition: all 0.15s ease;
        border: 2px solid transparent;
    }
    .ga-crew-chip:hover {
        background: var(--z-accent-soft);
        border-color: var(--z-accent);
    }
    .ga-crew-chip.selected {
        background: var(--z-accent-soft);
        border-color: var(--z-accent);
    }
    .ga-crew-chip.recommended {
        box-shadow: 0 0 0 1px var(--z-success);
    }

    .ga-crew-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--z-accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.72rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .ga-crew-info { flex: 1; min-width: 0; }
    .ga-crew-name {
        font-weight: 600;
        color: var(--z-text);
        font-size: 0.82rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ga-crew-dept {
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .ga-crew-load {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }
    .ga-crew-bar-wrap {
        width: 48px;
        height: 6px;
        background: var(--z-border);
        border-radius: 3px;
        overflow: hidden;
    }
    .ga-crew-bar {
        height: 100%;
        border-radius: 3px;
        background: var(--z-accent);
        transition: width 0.3s ease;
    }
    .ga-crew-bar.medium { background: var(--z-warning); }
    .ga-crew-bar.heavy { background: var(--z-danger); }
    .ga-crew-count {
        font-size: 0.7rem;
        color: var(--z-text-muted);
        font-weight: 600;
        white-space: nowrap;
    }

    /* Recommended star */
    .ga-star {
        color: var(--z-success);
        font-size: 0.7rem;
    }

    /* ─── Empty ─── */
    .ga-empty {
        text-align: center;
        padding: 48px 24px;
        color: var(--z-text-muted);
    }
    .ga-empty i {
        font-size: 2.2rem;
        display: block;
        margin-bottom: 12px;
        opacity: 0.4;
    }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
        .ga-metrics { grid-template-columns: repeat(2, 1fr); }
        .ga-assign-form { grid-template-columns: 1fr; }
        .ga-crew-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .ga-card-header { flex-direction: column; align-items: flex-start; }
        .ga-card-right { align-self: flex-end; }
        .ga-filter { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    {{-- ── Metrics ── --}}
    <div class="ga-metrics">
        <div class="ga-metric">
            <div class="ga-metric-num" id="metricTasks">0</div>
            <div class="ga-metric-label">Bekleyen Görev</div>
        </div>
        <div class="ga-metric">
            <div class="ga-metric-num accent" id="metricPieces">0</div>
	            <div class="ga-metric-label">Planlanabilir Adet</div>
        </div>
        <div class="ga-metric">
            <div class="ga-metric-num" id="metricPersonnel">0</div>
            <div class="ga-metric-label">Personel</div>
        </div>
        <div class="ga-metric">
            <div class="ga-metric-num warning" id="metricTime">—</div>
            <div class="ga-metric-label">Son Güncelleme</div>
        </div>
    </div>

    {{-- ── Filter ── --}}
    <section class="panel-surface" style="margin-bottom: 16px;">
        <div class="ga-filter">
            <div>
                <label class="form-label" for="searchInput"><i class="bi bi-search me-1"></i>Görev Ara</label>
                <input id="searchInput" class="form-control" placeholder="Ürün adı veya bölüm yazın...">
            </div>
            <div>
                <label class="form-label" for="deptFilter">Bölüm</label>
                <select id="deptFilter" class="form-select">
                    <option value="">Tümü</option>
                </select>
            </div>
        </div>
    </section>

    {{-- ── Task Feed ── --}}
    <section class="panel-surface">
        <div class="section-header compact">
            <div>
                <div class="section-overline">Havuz</div>
                <h3 class="section-title">Görev Bekleyen Satırlar</h3>
                <p class="section-copy">Bir göreve tıklayın, personel seçin ve atayın.</p>
            </div>
        </div>

        <div id="taskFeed" class="ga-tasks">
            <div class="ga-empty">
                <i class="bi bi-hourglass-split"></i>
                <p>Yükleniyor...</p>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ───────── State ───────── */
let poolTasks = [];
let personnel = [];
let openCardId = null;
let csrfToken = document.querySelector('meta[name="csrf-token"]').content;

/* ───────── Helpers ───────── */
function esc(v) {
    if (v == null) return '';
    return String(v).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
}
function fmt(n) { return Number(n || 0).toLocaleString('tr-TR'); }
function todayIso() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
}
function initials(ad, soyad) {
    return ((ad||'')[0] || '') + ((soyad||'')[0] || '');
}

/* ───────── Load Data ───────── */
async function loadPool() {
    try {
        const r = await fetch('/api/database/pool-tasks');
        const d = await r.json();
        poolTasks = (d.data || []).map(task => ({
            ...task,
            id: Number(task.id),
            department_id: Number(task.department_id || 0),
        }));
    } catch {
        poolTasks = [];
    }
}

async function loadPersonnel() {
    try {
        const r = await fetch('/api/database/personnel-workload');
        const d = await r.json();
        personnel = (d.data || []).map(person => ({
            ...person,
            PersonelNo: Number(person.PersonelNo),
            BolumAdiNo: Number(person.BolumAdiNo || 0),
        }));
    } catch {
        personnel = [];
    }
}

async function refreshAll() {
    await Promise.all([loadPool(), loadPersonnel()]);
    updateMetrics();
    buildDeptFilter();
    renderFeed();
    document.getElementById('metricTime').textContent = new Date().toLocaleTimeString('tr-TR', { hour:'2-digit', minute:'2-digit' });
}

/* ───────── Metrics ───────── */
function updateMetrics() {
    const filtered = getFilteredTasks();
    document.getElementById('metricTasks').textContent = fmt(filtered.length);
    document.getElementById('metricPieces').textContent = fmt(filtered.reduce((s, t) => s + (t.toplam_adet || 0), 0));
    document.getElementById('metricPersonnel').textContent = fmt(personnel.length);
}

/* ───────── Department Filter ───────── */
function buildDeptFilter() {
    const sel = document.getElementById('deptFilter');
    const current = sel.value;
    const depts = [...new Set(poolTasks.map(t => t.department_name).filter(Boolean))].sort();
    sel.innerHTML = '<option value="">Tümü</option>';
    depts.forEach(d => {
        sel.insertAdjacentHTML('beforeend', `<option value="${esc(d)}" ${d === current ? 'selected' : ''}>${esc(d)}</option>`);
    });
}

/* ───────── Filter Logic ───────── */
function getFilteredTasks() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const dept = document.getElementById('deptFilter').value;
    return poolTasks.filter(t => {
        if (dept && t.department_name !== dept) return false;
        if (q) {
            const hay = [t.component_name, t.product_name, t.department_name, t.aciklama].join(' ').toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });
}

/* ───────── Render Feed ───────── */
function renderFeed() {
    const container = document.getElementById('taskFeed');
    const filtered = getFilteredTasks();

    if (!filtered.length) {
        container.innerHTML = `<div class="ga-empty"><i class="bi bi-inbox"></i><p>${poolTasks.length ? 'Filtreye uygun görev bulunamadı.' : 'Havuzda bekleyen görev yok.'}</p></div>`;
        return;
    }

    // Find max workload for bar scaling
    const maxLoad = Math.max(1, ...personnel.map(p => Number(p.bekleyenAdet || 0)));

	    container.innerHTML = filtered.map(task => {
	        const isOpen = openCardId === task.id;
	        const name = task.component_name || task.product_name || 'Görev';
		        const readyAdet = Number(task.adet || 0);
		        const totalAdet = Number(task.toplam_adet || 0);
                const plannedAdet = Number(task.planlanabilir_adet || totalAdet);
                const reservedAdet = Number(task.stoktan_ayrilan_adet || 0);
                const childReservedAdet = Number(task.alt_stoktan_ayrilan_adet || 0);
                const requestedAdet = Number(task.bom_ihtiyac_adet || task.requested_adet || totalAdet);
                const stockDetailParts = [];
                if (reservedAdet > 0 || requestedAdet !== totalAdet) {
                    stockDetailParts.push(`BOM ihtiyaç: ${fmt(requestedAdet)}`);
                    stockDetailParts.push(`Boş stoktan düşen: ${fmt(reservedAdet)}`);
                }
                if (task.alt_gorev_bekliyor) {
                    stockDetailParts.push('Alt görevler tamamlanınca personele açılır');
                } else if (childReservedAdet > 0) {
                    stockDetailParts.push(`Alt parça stokta/tamponda: ${fmt(childReservedAdet)}`);
                }
                stockDetailParts.push(`Net üretim: ${fmt(totalAdet)}`);
                const stockDetail = `<div class="ga-card-detail">${stockDetailParts.map(part => `<span>${esc(part)}</span>`).join('')}</div>`;

	        // Find matching department personnel only. Backend enforces the same rule.
        const taskDeptId = Number(task.department_id || 0);
        const deptPersonnel = personnel.filter(p => taskDeptId > 0
            ? Number(p.BolumAdiNo || 0) === taskDeptId
            : p.BolumAdi === task.department_name
        );
        // Sort by workload (least busy first)
        const sortByLoad = (a, b) => Number(a.bekleyenAdet || 0) - Number(b.bekleyenAdet || 0);
        deptPersonnel.sort(sortByLoad);

        return `
        <div class="ga-card ${isOpen ? 'open' : ''}" data-id="${task.id}">
            <div class="ga-card-header" onclick="toggleCard(${task.id})">
                <div class="ga-card-info">
                    <h4 class="ga-card-title">
                        ${esc(name)}
                        <span class="soft-badge">${esc(task.department_name || 'Bölüm yok')}</span>
                    </h4>
	                    <div class="ga-card-sub">
	                        <span><i class="bi bi-box"></i>Hazır: ${fmt(readyAdet)}</span>
	                        <span><i class="bi bi-clipboard-check"></i>Planlanabilir: ${fmt(plannedAdet)}</span>
	                        <span><i class="bi bi-calendar3"></i>${esc([task.gorev_tarihi, task.gorev_saati].filter(Boolean).join(' ') || '-')}</span>
	                        ${task.aciklama ? `<span><i class="bi bi-chat-left-text"></i>${esc(task.aciklama)}</span>` : ''}
	                    </div>
                        ${stockDetail}
		                </div>
		                <div class="ga-card-right">
		                    <div class="ga-amount-pill">${fmt(readyAdet)} hazır / ${fmt(totalAdet)} net</div>
	                    <i class="bi bi-chevron-down ga-expand-icon"></i>
	                </div>
            </div>

            <div class="ga-card-body">
                <!-- Assignment Form -->
                <div class="ga-assign-form">
                    <div>
                        <label class="form-label">Personel</label>
	                        <select class="form-select" id="personnel-${task.id}">
	                            <option value="">— Personel seçin —</option>
	                            ${deptPersonnel.length ? `<optgroup label="${esc(task.department_name || 'Aynı Bölüm')}">
	                                ${deptPersonnel.map(p => `<option value="${p.PersonelNo}">${esc(p.Ad)} ${esc(p.Soyad)} — ${fmt(p.bekleyenAdet)} adet iş</option>`).join('')}
	                            </optgroup>` : '<option value="" disabled>Bu bölümde personel bulunamadı</option>'}
	                        </select>
	                    </div>
                    <div>
                        <label class="form-label">Görev Tarihi</label>
                        <input type="date" class="form-control" id="date-${task.id}" min="${todayIso()}" value="${todayIso()}">
                    </div>
		                    <div>
		                        <label class="form-label">Adet</label>
		                        <input type="number" class="form-control" id="amount-${task.id}" min="1" max="${plannedAdet}" value="${plannedAdet > 0 ? plannedAdet : 0}">
	                    </div>
	                    <div style="display:flex;gap:6px;">
		                        <button class="btn btn-primary" onclick="assignTask(${task.id})" title="Görevi Ata" ${deptPersonnel.length && plannedAdet > 0 ? '' : 'disabled'}>
		                            <i class="bi bi-check2-circle me-1"></i>Ata
		                        </button>
	                        <button class="btn btn-outline-secondary" onclick="assignAll(${task.id})" title="Tüm planlanabilir adedi ata" ${plannedAdet > 0 ? '' : 'disabled'}>
	                            Tümü
	                        </button>
                    </div>
                </div>

                <!-- Personnel Workload Preview -->
                ${personnel.length ? `
                <div class="ga-crew-section">
	                    <div class="ga-crew-title">
	                        ${esc(task.department_name || 'Görev Bölümü')} Personel İş Yükleri
	                    </div>
	                    <div class="ga-crew-grid">
	                        ${deptPersonnel.length ? deptPersonnel.slice(0, 12).map(p => {
	                            const load = Number(p.bekleyenAdet || 0);
	                            const pct = Math.min(100, Math.round((load / maxLoad) * 100));
	                            const barClass = pct > 70 ? 'heavy' : (pct > 40 ? 'medium' : '');
	                            const isDeptMatch = (p.BolumAdi === task.department_name) && task.department_name;
	                            return `
                            <div class="ga-crew-chip ${isDeptMatch ? 'recommended' : ''}" onclick="selectPersonnel(${task.id}, ${p.PersonelNo})">
                                <div class="ga-crew-avatar">${esc(initials(p.Ad, p.Soyad))}</div>
                                <div class="ga-crew-info">
                                    <div class="ga-crew-name">${isDeptMatch ? '<span class="ga-star">★</span> ' : ''}${esc(p.Ad)} ${esc(p.Soyad)}</div>
                                    <div class="ga-crew-dept">${esc(p.BolumAdi || 'Bölüm yok')}</div>
                                </div>
                                <div class="ga-crew-load">
                                    <div class="ga-crew-bar-wrap"><div class="ga-crew-bar ${barClass}" style="width:${pct}%"></div></div>
                                    <span class="ga-crew-count">${fmt(load)}</span>
	                                </div>
	                            </div>`;
	                        }).join('') : '<div class="text-muted small">Bu görev bölümünde personel bulunamadı.</div>'}
	                    </div>
                </div>` : ''}
            </div>
        </div>`;
    }).join('');
}

/* ───────── Toggle Card ───────── */
function toggleCard(id) {
    id = Number(id);
    openCardId = Number(openCardId) === id ? null : id;
    renderFeed();
}

/* ───────── Select Personnel from Chip ───────── */
function selectPersonnel(taskId, personnelNo) {
    const sel = document.getElementById(`personnel-${taskId}`);
    if (sel) {
        sel.value = String(personnelNo);
        // Visual feedback
        const card = document.querySelector(`.ga-card[data-id="${taskId}"]`);
        if (card) {
            card.querySelectorAll('.ga-crew-chip').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
        }
    }
}

/* ───────── Assign Task ───────── */
async function assignTask(taskId) {
    const personnelNo = document.getElementById(`personnel-${taskId}`)?.value;
    const gorevTarihi = document.getElementById(`date-${taskId}`)?.value || todayIso();
    const adet = parseInt(document.getElementById(`amount-${taskId}`)?.value || 0, 10);

    if (!personnelNo) {
        Swal.fire({ icon: 'warning', title: 'Personel Seçin', text: 'Lütfen bir personel seçin.' });
        return;
    }
    if (!gorevTarihi) {
        Swal.fire({ icon: 'warning', title: 'Görev Tarihi Seçin', text: 'Lütfen görevin alınacağı tarihi seçin.' });
        return;
    }
    if (!adet || adet < 1) {
        Swal.fire({ icon: 'warning', title: 'Geçersiz Adet', text: 'Adet en az 1 olmalı.' });
        return;
    }

    try {
        const r = await fetch(`/api/database/pool-tasks/${taskId}/assign`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify({ personel_no: parseInt(personnelNo, 10), adet, gorev_tarihi: gorevTarihi })
        });
        const d = await r.json();

        if (d.success || (d.message && d.message.includes('atandı'))) {
            Swal.fire({ icon: 'success', title: 'Görev Atandı', text: d.message || 'Başarıyla atandı.', timer: 2000, showConfirmButton: false });
            openCardId = null;
            await refreshAll();
        } else {
            Swal.fire({ icon: 'error', title: 'Atama Başarısız', text: d.message || 'Bir hata oluştu.' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: e.message });
    }
}

/* ───────── Assign All Remaining ───────── */
function assignAll(taskId) {
    const task = poolTasks.find(t => Number(t.id) === Number(taskId));
    if (task) {
        document.getElementById(`amount-${taskId}`).value = Number(task.planlanabilir_adet || task.toplam_adet || 0);
    }
}

/* ───────── Init ───────── */
document.addEventListener('DOMContentLoaded', async () => {
    await refreshAll();

    document.getElementById('searchInput').addEventListener('input', () => {
        updateMetrics();
        renderFeed();
    });
    document.getElementById('deptFilter').addEventListener('change', () => {
        updateMetrics();
        renderFeed();
    });
});
</script>
@endpush
