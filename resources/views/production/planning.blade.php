@extends('layouts.app')

@section('title', 'Uretim Planlama')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadCurrentPlanningTab()">
        <i class="bi bi-arrow-repeat me-1"></i>Aktif Sekmeyi Yenile
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadPersonelList()">
        <i class="bi bi-people me-1"></i>Personelleri Guncelle
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .planning-control-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .planning-task-groups {
        display: grid;
        gap: 12px;
    }

    .planning-date-group {
        padding: 16px;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        background: var(--z-bg-card);
    }

    .planning-date-header {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
        font-weight: 600;
    }

    .planning-date-label {
        font-size: 0.88rem;
    }

    .planning-task-card {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 12px 14px;
        border: 1px solid var(--z-border-light);
        border-radius: var(--z-radius-sm);
        background: var(--z-bg-soft);
        margin-bottom: 6px;
    }

    .planning-task-card:last-child { margin-bottom: 0; }

    .planning-task-title {
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--z-text);
    }

    .planning-task-meta {
        margin-top: 4px;
        color: var(--z-text-secondary);
        font-size: 0.8rem;
        line-height: 1.5;
    }

    .planning-task-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 6px;
    }

    .planning-task-actions .btn { min-width: 36px; }

    .planning-empty {
        display: grid;
        place-items: center;
        min-height: 180px;
        text-align: center;
        color: var(--z-text-muted);
        gap: 8px;
    }

    .planning-empty i {
        font-size: 2rem;
        color: var(--z-accent);
        opacity: 0.4;
    }

    .planning-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 4px;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        font-weight: 600;
    }

    .planning-pill.success { background: var(--z-success-soft); color: var(--z-success); }
    .planning-pill.warning { background: var(--z-warning-soft); color: var(--z-warning); }
    .planning-pill.danger { background: var(--z-danger-soft); color: var(--z-danger); }

    .planning-summary-table th,
    .planning-summary-table td { white-space: nowrap; }
    .planning-summary-table td:first-child { white-space: normal; }

    @media (max-width: 1080px) {
        .planning-control-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .planning-task-card { flex-direction: column; align-items: flex-start; }
        .planning-task-actions { justify-content: flex-start; }
    }

    @media (max-width: 720px) {
        .planning-control-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    {{-- Inline Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Personel</p>
            <h3 class="metric-value" id="planningPersonnelInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Gorev</p>
            <h3 class="metric-value" id="planningTaskInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Ozet Satiri</p>
            <h3 class="metric-value" id="planningSummaryInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Aktif Gorunum</p>
            <h3 class="metric-value" id="planningActiveInline" style="font-size: 1rem;">Personel</h3>
        </article>
    </div>

    {{-- Status Bar --}}
    <div class="panel-surface">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-wrap gap-3">
                <span class="small"><span class="text-muted">Sekme:</span> <strong id="planningActiveMeta">Personel Planlama</strong></span>
                <span class="small"><span class="text-muted">Personel:</span> <strong id="planningSelectedPersonnel">Henuz secilmedi</strong></span>
                <span class="small"><span class="text-muted">Gorev:</span> <strong id="planningTaskMeta">0</strong></span>
                <span class="small"><span class="text-muted">Son:</span> <strong id="planningUpdatedMeta">Bekleniyor</strong></span>
            </div>
            <span class="planning-pill" id="planningStatusPill">Hazir</span>
        </div>
    </div>

    {{-- Tabs --}}
    <section class="panel-surface">
        <ul class="nav planning-tabs" id="planningTabs" role="tablist" style="display:flex;gap:4px;margin-bottom:16px;border:none;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPersonelPlanlama" type="button" role="tab" aria-selected="true">
                    <i class="bi bi-people me-1"></i>Personel Planlama
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSiparisOzeti" type="button" role="tab" aria-selected="false">
                    <i class="bi bi-list-check me-1"></i>Siparis Ozeti
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tabPersonelPlanlama" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0">Tarih bazli gorev dagilimi</h3>
                    <span class="planning-pill success" id="taskCount">0 gorev</span>
                </div>

                <div class="planning-control-grid">
                    <div>
                        <label class="form-label">Personel secin</label>
                        <select id="personelSelect" class="form-select" onchange="loadPersonelTasks()">
                            <option value="">-- Personel seciniz --</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Yeni tarih</label>
                        <input type="date" id="yeniTarih" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>

                    <div>
                        <label class="form-label">Bos tarih kutusu</label>
                        <button class="btn btn-outline-secondary w-100" type="button" onclick="tarihKutusuEkle()">
                            <i class="bi bi-calendar-plus me-1"></i>Tarih Ekle
                        </button>
                    </div>

                    <div>
                        <label class="form-label">Veri yenile</label>
                        <button class="btn btn-primary w-100" type="button" onclick="loadPersonelTasks()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Gorevleri Getir
                        </button>
                    </div>
                </div>

                <div id="personelTasksArea" class="planning-task-groups">
                    <div class="planning-empty">
                        <i class="bi bi-arrow-up-circle"></i>
                        <strong>Yukaridan bir personel sec.</strong>
                        <span>Secimden sonra gorevler tarih gruplari halinde burada gorunecek.</span>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tabSiparisOzeti" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title mb-0">Toplu uretim ozet tablosu</h3>
                    <span class="planning-pill warning" id="planningSummaryBadge">0 satir</span>
                </div>

                <div class="planning-control-grid">
                    <div>
                        <label class="form-label">Kategori</label>
                        <select id="kategoriFilter" class="form-select">
                            <option value="">Tum Kategoriler</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Referans tarih</label>
                        <input type="date" id="tarihFilter" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>

                    <div style="grid-column: span 2;">
                        <label class="form-label">Ozeti yenile</label>
                        <button class="btn btn-primary w-100" type="button" onclick="loadSummary()">
                            <i class="bi bi-search me-1"></i>Ozeti Getir
                        </button>
                    </div>
                </div>

                <div class="table-shell">
                    <table class="table-modern planning-summary-table" id="planningTable">
                        <thead>
                            <tr>
                                <th>Urun</th>
                                <th>Toplam Siparis</th>
                                <th>Uretilebilir</th>
                                <th>Eksik Miktar</th>
                                <th>Kargo Son Teslim</th>
                                <th>Durum</th>
                                <th>Islem</th>
                            </tr>
                        </thead>
                        <tbody id="planningBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Siparis ozeti yukleniyor...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="dateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-calendar-event me-1"></i>Tarih Degistir</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="date" id="modalNewDate" class="form-control">
                    <input type="hidden" id="modalTaskId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Iptal</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="submitDateChange()">
                        <i class="bi bi-check-lg me-1"></i>Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPersonelNo = null;

function setPlanningStamp(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('planningUpdatedMeta').textContent = label;
}

function setPlanningActive(label) {
    document.getElementById('planningActiveInline').textContent = label;
    document.getElementById('planningActiveMeta').textContent = label;
}

function loadCurrentPlanningTab() {
    const activeTab = document.querySelector('#planningTabs .nav-link.active');
    const target = activeTab ? activeTab.getAttribute('data-bs-target') : '#tabPersonelPlanlama';

    if (target === '#tabSiparisOzeti') {
        loadSummary();
        return;
    }

    loadPersonelTasks();
}

function loadPersonelList() {
    fetch('/api/planning/personnel')
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) return;

            const select = document.getElementById('personelSelect');
            const current = select.value;
            select.innerHTML = '<option value="">-- Personel seciniz --</option>';

            (data.data || []).forEach((personnel) => {
                select.innerHTML += `<option value="${personnel.PersonelNo}">${escapeHtml(personnel.PersonelAdi)}</option>`;
            });

            if (current) {
                select.value = current;
            }

            document.getElementById('planningPersonnelInline').textContent = formatNumber((data.data || []).length);
            setPlanningStamp();
        })
        .catch(() => {
            Swal.fire('Hata', 'Personel listesi yuklenemedi.', 'error');
        });
}

function loadPersonelTasks() {
    const select = document.getElementById('personelSelect');
    currentPersonelNo = select.value;
    const area = document.getElementById('personelTasksArea');

    if (!currentPersonelNo) {
        area.innerHTML = `
            <div class="planning-empty">
                <i class="bi bi-arrow-up-circle"></i>
                <strong>Yukaridan bir personel sec.</strong>
                <span>Secimden sonra gorevler tarih gruplari halinde burada gorunecek.</span>
            </div>`;
        document.getElementById('taskCount').textContent = '0 gorev';
        document.getElementById('planningTaskInline').textContent = '0';
        document.getElementById('planningTaskMeta').textContent = '0';
        document.getElementById('planningSelectedPersonnel').textContent = 'Henuz secilmedi';
        document.getElementById('planningStatusPill').textContent = 'Secim bekleniyor';
        document.getElementById('planningStatusPill').className = 'planning-pill';
        return;
    }

    document.getElementById('planningSelectedPersonnel').textContent = select.options[select.selectedIndex].text;
    document.getElementById('planningStatusPill').textContent = 'Yukleniyor';
    document.getElementById('planningStatusPill').className = 'planning-pill warning';

    area.innerHTML = '<div class="planning-empty"><i class="bi bi-arrow-repeat"></i><strong>Gorevler yukleniyor...</strong></div>';

    fetch(`/api/planning/personnel/${currentPersonelNo}/tasks`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                area.innerHTML = '<div class="alert alert-danger">Gorevler yuklenemedi.</div>';
                return;
            }

            const tasks = data.data || [];
            document.getElementById('taskCount').textContent = `${formatNumber(tasks.length)} gorev`;
            document.getElementById('planningTaskInline').textContent = formatNumber(tasks.length);
            document.getElementById('planningTaskMeta').textContent = formatNumber(tasks.length);
            document.getElementById('planningStatusPill').textContent = tasks.length ? 'Akis aktif' : 'Bos plan';
            document.getElementById('planningStatusPill').className = tasks.length ? 'planning-pill success' : 'planning-pill';

            if (!tasks.length) {
                area.innerHTML = `
                    <div class="planning-empty">
                        <i class="bi bi-inbox"></i>
                        <strong>Bu personele atanmis gorev yok.</strong>
                        <span>Yeni bir tarih kutusu ekleyebilir veya baska personel secebilirsin.</span>
                    </div>`;
                setPlanningStamp();
                return;
            }

            const groups = {};
            tasks.forEach((task) => {
                const rawDate = (task.GorevBaslamaTarihi || '').substring(0, 10) || 'Tarihsiz';
                if (!groups[rawDate]) groups[rawDate] = [];
                groups[rawDate].push(task);
            });

            const html = Object.keys(groups).sort().map((rawDate) => {
                const taskList = groups[rawDate];
                const totalAmount = taskList.reduce((sum, task) => sum + (parseInt(task.Adet, 10) || 0) + (parseInt(task.BekleyenAdet, 10) || 0), 0);

                const cards = taskList.map((task) => {
                    const amount = parseInt(task.Adet, 10) || 0;
                    const pending = parseInt(task.BekleyenAdet, 10) || 0;
                    const approved = task.Onay === 'true' || task.Onay === '1' || task.Onay === 1;
                    const approvalBadge = approved
                        ? '<span class="planning-pill success">Tamamlandi</span>'
                        : '<span class="planning-pill warning">Bekliyor</span>';

                    return `
                        <div class="planning-task-card" id="task-${task.No}">
                            <div>
                                <div class="planning-task-title">${escapeHtml(task.AraUrunAdi || 'Bilinmiyor')}</div>
                                <div class="planning-task-meta">
                                    <i class="bi bi-diagram-3 me-1"></i>${escapeHtml(task.BolumAdi || '-')}
                                    &nbsp;|&nbsp;
                                    Adet: <strong>${formatNumber(amount)}</strong>
                                    &nbsp;|&nbsp;
                                    Bekleyen: <strong>${formatNumber(pending)}</strong>
                                    &nbsp;|&nbsp;
                                    ${approvalBadge}
                                </div>
                            </div>
                            <div class="planning-task-actions">
                                <button class="btn btn-outline-success btn-sm" onclick="incrementTask(${task.No})" title="Adet +1">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="decrementTask(${task.No})" title="Adet -1">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="openDateModal(${task.No})" title="Tarih degistir">
                                    <i class="bi bi-calendar-event"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteTask(${task.No})" title="Gorevi sil">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');

                return `
                    <div class="planning-date-group" data-date="${rawDate}">
                        <div class="planning-date-header">
                            <span class="planning-date-label"><i class="bi bi-calendar-date me-1"></i>${formatDateLabel(rawDate)}</span>
                            <span class="planning-pill">${formatNumber(taskList.length)} gorev</span>
                            <span class="planning-pill success">${formatNumber(totalAmount)} adet</span>
                        </div>
                        <div class="planning-task-groups">${cards}</div>
                    </div>
                `;
            }).join('');

            area.innerHTML = html;
            setPlanningStamp();
        })
        .catch(() => {
            area.innerHTML = '<div class="alert alert-danger">Gorevler yuklenirken hata olustu.</div>';
            document.getElementById('planningStatusPill').textContent = 'Hata';
            document.getElementById('planningStatusPill').className = 'planning-pill danger';
        });
}

function incrementTask(taskId) {
    fetch(`/api/planning/increment/${taskId}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Islem tamamlanamadi.', 'error');
                return;
            }

            loadPersonelTasks();
        })
        .catch((error) => Swal.fire('Hata', String(error), 'error'));
}

function decrementTask(taskId) {
    fetch(`/api/planning/decrement/${taskId}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Islem tamamlanamadi.', 'error');
                return;
            }

            loadPersonelTasks();
        })
        .catch((error) => Swal.fire('Hata', String(error), 'error'));
}

function deleteTask(taskId) {
    Swal.fire({
        title: 'Gorev silinsin mi?',
        text: 'Bu gorev havuza geri aktarilacak.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Vazgec',
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch(`/api/planning/task/${taskId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) {
                    Swal.fire('Hata', data.message || 'Gorev silinemedi.', 'error');
                    return;
                }

                loadPersonelTasks();
            })
            .catch((error) => Swal.fire('Hata', String(error), 'error'));
    });
}

function openDateModal(taskId) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('modalNewDate').value = '';
    new bootstrap.Modal(document.getElementById('dateModal')).show();
}

function submitDateChange() {
    const taskId = document.getElementById('modalTaskId').value;
    const newDate = document.getElementById('modalNewDate').value;

    if (!newDate) {
        Swal.fire('Eksik tarih', 'Lutfen bir tarih secin.', 'warning');
        return;
    }

    fetch(`/api/planning/task/${taskId}/date`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ date: newDate })
    })
        .then((r) => r.json())
        .then((data) => {
            bootstrap.Modal.getInstance(document.getElementById('dateModal')).hide();
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Tarih guncellenemedi.', 'error');
                return;
            }

            loadPersonelTasks();
        })
        .catch((error) => Swal.fire('Hata', String(error), 'error'));
}

function tarihKutusuEkle() {
    const date = document.getElementById('yeniTarih').value;
    if (!date) {
        Swal.fire('Eksik tarih', 'Lutfen bir tarih secin.', 'warning');
        return;
    }

    const area = document.getElementById('personelTasksArea');
    const existing = area.querySelector(`[data-date="${date}"]`);
    if (existing) {
        existing.scrollIntoView({ behavior: 'smooth' });
        return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'planning-date-group';
    wrapper.setAttribute('data-date', date);
    wrapper.innerHTML = `
        <div class="planning-date-header">
            <span class="planning-date-label"><i class="bi bi-calendar-date me-1"></i>${formatDateLabel(date)}</span>
            <span class="planning-pill">0 gorev</span>
        </div>
        <div class="text-muted small">Bu tarihe gorev tasinabilir veya yeni gorevler bu gune planlanabilir.</div>
    `;

    const empty = area.querySelector('.planning-empty');
    if (empty) {
        area.innerHTML = '';
    }

    area.prepend(wrapper);
    wrapper.scrollIntoView({ behavior: 'smooth' });
}

function loadSummary() {
    const category = document.getElementById('kategoriFilter').value;
    const selectedDate = document.getElementById('tarihFilter').value;

    fetch(`/SiparisApi.ashx?action=getSummary&kategori=${encodeURIComponent(category)}`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) return;

            if (data.kategoriList) {
                const select = document.getElementById('kategoriFilter');
                const current = category;
                select.innerHTML = '<option value="">Tum Kategoriler</option>';
                data.kategoriList.forEach((item) => {
                    select.innerHTML += `<option value="${item}"${item === current ? ' selected' : ''}>${item}</option>`;
                });
            }

            const rows = data.summary || [];
            document.getElementById('planningSummaryInline').textContent = formatNumber(rows.length);
            document.getElementById('planningSummaryBadge').textContent = `${formatNumber(rows.length)} satir`;

            if (!rows.length) {
                document.getElementById('planningBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Veri yok.</td></tr>';
                setPlanningStamp();
                return;
            }

            document.getElementById('planningBody').innerHTML = rows.map((summary) => {
                const urgency = summary.EnYakinKargo
                    ? `<span class="planning-pill warning">${escapeHtml(summary.EnYakinKargo)}</span>`
                    : '-';
                const status = summary.EslesenUrunNo > 0
                    ? '<span class="planning-pill success">Eslesmis</span>'
                    : '<span class="planning-pill danger">Eslesmemis</span>';

                const producible = Number(summary.UretilebilirAdet || 0);
                const missing = Math.max(0, Number(summary.ToplamAdet || 0) - producible);
                const producibleHtml = producible >= Number(summary.ToplamAdet || 0)
                    ? `<span class="text-success fw-bold">${formatNumber(producible)}</span>`
                    : formatNumber(producible);
                const missingHtml = missing > 0
                    ? `<span class="text-danger fw-bold">${formatNumber(missing)}</span>`
                    : '<span class="text-success">Yok</span>';

                return `
                    <tr>
                        <td>
                            ${escapeHtml(summary.UrunAdi || '-')}
                            ${summary.sistemAdi ? `<br><small class="text-muted">${escapeHtml(summary.sistemAdi)}</small>` : ''}
                        </td>
                        <td class="text-center fw-bold">${formatNumber(summary.ToplamAdet || 0)}</td>
                        <td class="text-center">${producibleHtml}</td>
                        <td class="text-center">${missingHtml}</td>
                        <td class="text-center">${urgency}</td>
                        <td>${status}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="planWorkOrder(${summary.EslesenUrunNo || 0}, ${summary.ToplamAdet || 0}, '${escapeJsString(summary.EslesenUrunTur || '')}', '${escapeJsString(summary.UrunAdi || '')}', '${escapeJsString(selectedDate || '')}')">
                                <i class="bi bi-play me-1"></i>Is Emri
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            setPlanningStamp();
        });
}

function planWorkOrder(urunNo, adet, tur, urunAdi, selectedDate) {
    if (!urunNo || !tur) {
        Swal.fire('Eksik eslesme', 'Bu urun icin is emri olusturulamiyor; once eslestirme gerekiyor.', 'warning');
        return;
    }

    Swal.fire({
        title: 'Is emri olusturulsun mu?',
        html: `
            <div class="text-start">
                <p><strong>Urun:</strong> ${escapeHtml(urunAdi || '-')}</p>
                <p><strong>Adet:</strong> ${formatNumber(adet)}</p>
                <p><strong>Referans tarih:</strong> ${escapeHtml(selectedDate || '-')}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet, olustur',
        cancelButtonText: 'Vazgec'
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch('/SiparisApi.ashx?action=createOrderWorkOrders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                eslesenUrunNo: urunNo,
                eslesenUrunTur: tur,
                adet: adet,
                stokDurum: 'StokDahil'
            })
        })
            .then((r) => r.json())
            .then((data) => {
                Swal.fire('Islem tamamlandi', data.message || 'Is emri olusturuldu.', 'success');
                loadSummary();
            })
            .catch(() => Swal.fire('Hata', 'Is emri olusturulamadi.', 'error'));
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function formatDateLabel(rawDate) {
    if (!rawDate || rawDate === 'Tarihsiz') return 'Tarihsiz';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) return rawDate;

    const [year, month, day] = rawDate.split('-');
    return `${day}.${month}.${year}`;
}

function escapeHtml(value) {
    return String(value || '')
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
    setPlanningActive('Personel Planlama');
    loadPersonelList();

    document.querySelectorAll('#planningTabs .nav-link').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', (event) => {
            const label = event.target.getAttribute('data-bs-target') === '#tabSiparisOzeti'
                ? 'Siparis Ozeti'
                : 'Personel Planlama';

            setPlanningActive(label);
            if (label === 'Siparis Ozeti') {
                loadSummary();
            }
        });
    });
});
</script>
@endpush
