@extends('layouts.app')

@section('title', 'Görev Dağıtımı')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshAssignPage()"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadHavuz()"><i class="bi bi-diagram-3 me-1"></i>Havuzu Güncelle</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .assign-selected-row td { background: rgba(13,148,136,0.06) !important; }
    .assignment-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .assignment-placeholder { display: grid; gap: 8px; place-items: center; min-height: 160px; text-align: center; color: var(--z-text-secondary); }
    .assignment-placeholder i { font-size: 2rem; color: var(--z-accent); opacity: 0.4; }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Bekleyen Satır</p>
            <h3 class="metric-value" id="summaryPoolTasksInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Kalan Adet</p>
            <h3 class="metric-value" id="summaryPoolRemainingInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Personel</p>
            <h3 class="metric-value" id="summaryPersonnelInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Son Güncelleme</p>
            <h3 class="metric-value" id="summaryAssignUpdatedInline" style="font-size: 1rem;">—</h3>
        </article>
    </div>

    <div class="content-grid">
        <section class="panel-surface table-panel">
            <div class="panel-toolbar">
                <div class="panel-toolbar-copy"><h3>Görev bekleyen satırlar</h3></div>
                <div class="panel-toolbar-meta"><span class="soft-badge success">Seç ve ata</span></div>
            </div>
            <div class="table-shell">
                <table class="table-modern table-sm">
                    <thead>
                        <tr>
                            <th>#</th><th>Ürün</th><th>Bölüm</th><th>Toplam</th><th>Kalan</th><th>Tarih</th><th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="havuzBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="stack-list">
            <section class="panel-surface" id="atamaCard" style="display:none;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title" style="margin:0;">Personel seç ve dağıt</h3>
                    <span class="soft-badge warning" id="assignmentTaskBadge">Hazır</span>
                </div>
                <p class="muted-copy" id="atamaInfo"></p>
                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                    <div>
                        <label class="form-label">Personel</label>
                        <select id="personelSelect" class="form-select"><option value="">-- Personel seç --</option></select>
                    </div>
                    <div>
                        <label class="form-label">Adet</label>
                        <input type="number" id="atamaAdet" class="form-control" min="1" value="1">
                    </div>
                </div>
                <div class="assignment-actions">
                    <button class="btn btn-primary w-100" onclick="gorevAta()"><i class="bi bi-check2-circle me-1"></i>Görevi Ata</button>
                </div>
            </section>

            <section class="panel-surface" id="atamaPlaceholder">
                <div class="assignment-placeholder">
                    <i class="bi bi-hand-index-thumb"></i>
                    <strong>Atama için soldan bir görev seç.</strong>
                </div>
            </section>

            <section class="panel-surface table-panel">
                <div class="panel-toolbar">
                    <div class="panel-toolbar-copy"><h3>Personel görev durumu</h3></div>
                    <div class="panel-toolbar-meta"><span class="soft-badge" id="personnelStatusBadge">0 personel</span></div>
                </div>
                <div class="table-shell">
                    <table class="table-modern table-sm">
                        <thead><tr><th>Personel</th><th>Aktif Görev</th></tr></thead>
                        <tbody id="personelDurum">
                            <tr><td colspan="2" class="text-center text-muted py-3">Yükleniyor...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <span id="selectedTaskLabel" style="display:none;">Henüz seçilmedi</span>
    <span id="selectedTaskStatus" style="display:none;">Seçim bekleniyor</span>
    <span id="summaryPoolTasksMeta" style="display:none;">0</span>
    <span id="summaryPoolRemainingMeta" style="display:none;">0</span>
    <span id="summaryPersonnelMeta" style="display:none;">0</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let selectedGorev = null;
let selectedGorevLabel = '';
const assignCsrfToken = document.querySelector('meta[name="csrf-token"]').content;

function setAssignStamp(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('summaryAssignUpdatedInline').textContent = label;
}

function refreshAssignPage() { loadHavuz(); loadPersoneller(); }

function loadHavuz() {
    fetch('/api/database/pool-tasks').then((r) => r.json()).then((data) => {
        const rows = data.data || [];
        updatePoolSummary(rows);
        if (!rows.length) { document.getElementById('havuzBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Havuzda bekleyen görev yok.</td></tr>'; setAssignStamp(); return; }
        document.getElementById('havuzBody').innerHTML = rows.map((row) => {
            const taskLabel = row.component_name || row.product_name || 'Görev';
            const escaped = escapeJsString(taskLabel);
            return `<tr data-gorev-id="${row.id}">
                <td>${row.id}</td><td>${escapeHtml(taskLabel)}</td><td>${escapeHtml(row.department_name || '-')}</td>
                <td>${formatNumber(row.toplam_adet || 0)}</td><td><strong>${formatNumber(row.adet || 0)}</strong></td>
                <td>${escapeHtml([row.gorev_tarihi, row.gorev_saati].filter(Boolean).join(' ') || '-')}</td>
                <td><button class="btn btn-outline-secondary btn-sm" onclick="gorevSec(${row.id}, '${escaped}', ${parseInt(row.adet || 0, 10) || 0})">Seç</button></td>
            </tr>`;
        }).join('');
        if (selectedGorev) highlightSelectedRow(selectedGorev);
        setAssignStamp();
    }).catch(() => { document.getElementById('havuzBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Havuz verisi yüklenemedi.</td></tr>'; setAssignStamp('Hata'); });
}

function updatePoolSummary(rows) {
    const totalRows = rows.length;
    const totalRemaining = rows.reduce((sum, row) => sum + Number(row.adet || 0), 0);
    document.getElementById('summaryPoolTasksInline').textContent = formatNumber(totalRows);
    document.getElementById('summaryPoolTasksMeta').textContent = formatNumber(totalRows);
    document.getElementById('summaryPoolRemainingInline').textContent = formatNumber(totalRemaining);
    document.getElementById('summaryPoolRemainingMeta').textContent = formatNumber(totalRemaining);
}

function loadPersoneller() {
    fetch('/api/database/personnel-workload').then((r) => r.json()).then((data) => {
        const rows = data.data || [];
        const select = document.getElementById('personelSelect');
        select.innerHTML = '<option value="">-- Personel seç --</option>';
        rows.forEach((p) => { select.innerHTML += `<option value="${p.PersonelNo}">${escapeHtml(p.Ad)} ${escapeHtml(p.Soyad)} (${escapeHtml(p.BolumAdi || 'Atanmamış')})</option>`; });
        document.getElementById('personelDurum').innerHTML = rows.map((p) => `<tr><td>${escapeHtml(p.Ad)} ${escapeHtml(p.Soyad)}</td><td>${formatNumber(p.aktifGorev || 0)} görev / ${formatNumber(p.bekleyenAdet || 0)} adet</td></tr>`).join('') || '<tr><td colspan="2" class="text-center text-muted py-3">Personel yok.</td></tr>';
        document.getElementById('summaryPersonnelInline').textContent = formatNumber(rows.length);
        document.getElementById('summaryPersonnelMeta').textContent = formatNumber(rows.length);
        document.getElementById('personnelStatusBadge').textContent = `${formatNumber(rows.length)} personel`;
    }).catch(() => { document.getElementById('personelDurum').innerHTML = '<tr><td colspan="2" class="text-center text-danger py-3">Personel verisi yüklenemedi.</td></tr>'; });
}

function gorevSec(gorevNo, info, remaining) {
    selectedGorev = gorevNo; selectedGorevLabel = info;
    document.getElementById('atamaCard').style.display = 'block';
    document.getElementById('atamaPlaceholder').style.display = 'none';
    document.getElementById('atamaInfo').textContent = `${info} için personel seçip dağıtılacak adedi belirle. Kalan: ${formatNumber(remaining)} adet.`;
    document.getElementById('selectedTaskLabel').textContent = info;
    document.getElementById('selectedTaskStatus').textContent = 'Seçildi';
    document.getElementById('assignmentTaskBadge').textContent = `${formatNumber(remaining)} adet`;
    document.getElementById('atamaAdet').value = remaining > 0 ? 1 : 0;
    highlightSelectedRow(gorevNo);
}

function highlightSelectedRow(gorevNo) {
    document.querySelectorAll('#havuzBody tr').forEach((row) => row.classList.remove('assign-selected-row'));
    const target = document.querySelector(`#havuzBody tr[data-gorev-id="${gorevNo}"]`);
    if (target) target.classList.add('assign-selected-row');
}

function gorevAta() {
    const personelNo = document.getElementById('personelSelect').value;
    const adet = parseInt(document.getElementById('atamaAdet').value, 10);
    if (!personelNo) { Swal.fire('Eksik bilgi', 'Lütfen bir personel seçin.', 'warning'); return; }
    if (!selectedGorev) { Swal.fire('Eksik bilgi', 'Önce havuzdan bir görev seçin.', 'warning'); return; }
    if (!adet || adet < 1) { Swal.fire('Geçersiz adet', 'Atama adedi en az 1 olmalı.', 'warning'); return; }

    fetch(`/api/database/pool-tasks/${selectedGorev}/assign`, {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': assignCsrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ personel_no: parseInt(personelNo, 10), adet })
    }).then((r) => r.json()).then((data) => {
        if (data.message && !String(data.message).includes('atandı') && !String(data.message).includes('atandi')) { Swal.fire('Atama başarısız', data.message, 'error'); return; }
        Swal.fire('Görev atandı', data.message || 'Seçilen görev personele aktarıldı.', 'success');
        selectedGorev = null; selectedGorevLabel = '';
        document.getElementById('atamaCard').style.display = 'none';
        document.getElementById('atamaPlaceholder').style.display = 'block';
        document.getElementById('selectedTaskLabel').textContent = 'Henüz seçilmedi';
        document.getElementById('selectedTaskStatus').textContent = 'Seçim bekleniyor';
        document.getElementById('assignmentTaskBadge').textContent = 'Hazır';
        document.querySelectorAll('#havuzBody tr').forEach((row) => row.classList.remove('assign-selected-row'));
        refreshAssignPage();
    }).catch(() => Swal.fire('Hata', 'Görev atanırken hata oluştu.', 'error'));
}

function formatNumber(value) { return Number(value || 0).toLocaleString('tr-TR'); }
function escapeHtml(value) { return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
function escapeJsString(value) { return String(value || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\n/g, ' '); }

document.addEventListener('DOMContentLoaded', () => { loadHavuz(); loadPersoneller(); });
</script>
@endpush
