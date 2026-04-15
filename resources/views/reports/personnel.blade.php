@extends('layouts.app')

@section('title', 'Personel Raporlari')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetReportFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Sifirla</button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportExcel()"><i class="bi bi-file-earmark-excel me-1"></i>Excel</button>
    <button type="button" class="btn btn-primary btn-sm" onclick="resetAndLoad()"><i class="bi bi-search me-1"></i>Yenile</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .personnel-date-range { display: none; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 12px; }
    .personnel-date-range.is-visible { display: grid; }
    .sort-head { cursor: pointer; user-select: none; white-space: nowrap; }
    .sort-head::after { content: ''; margin-left: 6px; font-size: 0.7rem; }
    .sort-head.sort-asc::after { content: '▲'; }
    .sort-head.sort-desc::after { content: '▼'; }
    .report-pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 20px; flex-wrap: wrap; }
    .report-page-numbers { display: flex; gap: 4px; flex-wrap: wrap; }
    @media (max-width: 720px) { .personnel-date-range { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam Kayit</p>
            <h3 class="metric-value" id="summaryRecords">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Adet</p>
            <h3 class="metric-value" id="summaryAmount">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Ort. Performans</p>
            <h3 class="metric-value" id="summaryPerformance">-</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Filtre Modu</p>
            <h3 class="metric-value" id="summaryFilterMode" style="font-size: 1rem;">Tum</h3>
        </article>
    </div>

    <div class="panel-surface">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label for="personnelFilter" class="form-label">Personel</label>
                <select id="personnelFilter" class="form-select" onchange="resetAndLoad()">
                    <option value="">Tum Personeller</option>
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label for="dateOptionFilter" class="form-label">Tarih Araligi</label>
                <select id="dateOptionFilter" class="form-select" onchange="onDateOptionChange()">
                    <option value="hepsi">Tum Kayitlar</option>
                    <option value="gun">Son Gun</option>
                    <option value="hafta">Son Hafta</option>
                    <option value="ay">Son Ay</option>
                    <option value="6ay">Son 6 Ay</option>
                    <option value="yil">Son Yil</option>
                    <option value="tarih">Tarih Sec</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label for="perPage" class="form-label">Sayfa Boyutu</label>
                <select id="perPage" class="form-select" onchange="resetAndLoad()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-xl-4 col-md-6 d-flex align-items-end gap-2">
                <button type="button" class="btn btn-primary btn-sm" onclick="resetAndLoad()"><i class="bi bi-search me-1"></i>Filtrele</button>
                <span class="soft-badge" id="summaryStatusChip">Hazir</span>
                <span class="soft-badge" style="margin-left: auto;">Son: <strong id="summaryUpdatedInline">—</strong></span>
            </div>
        </div>
        <div class="personnel-date-range" id="customDateRange">
            <div><label for="startDate" class="form-label">Baslangic</label><input type="date" id="startDate" class="form-control" onchange="resetAndLoad()"></div>
            <div><label for="endDate" class="form-label">Bitis</label><input type="date" id="endDate" class="form-control" onchange="resetAndLoad()"></div>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy"><h3>Personel gorev ciktisi</h3></div>
            <div class="panel-toolbar-meta"><span class="soft-badge" id="summaryQueryState">Bekleniyor</span></div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th class="sort-head sort-desc" data-sort="gr.No" onclick="sortData('gr.No')">No</th>
                        <th class="sort-head" data-sort="p.Ad" onclick="sortData('p.Ad')">Personel</th>
                        <th class="sort-head" data-sort="u.UrunID" onclick="sortData('u.UrunID')">Urun ID</th>
                        <th class="sort-head" data-sort="au.AraUrunAdi" onclick="sortData('au.AraUrunAdi')">Ara Urun</th>
                        <th class="sort-head" data-sort="gr.GorevBaslamaTarihi" onclick="sortData('gr.GorevBaslamaTarihi')">Baslama</th>
                        <th class="sort-head" data-sort="gr.GorevBitisTarihi" onclick="sortData('gr.GorevBitisTarihi')">Bitis</th>
                        <th class="sort-head" data-sort="gr.Performans" onclick="sortData('gr.Performans')">Performans</th>
                        <th class="sort-head" data-sort="gr.ToplamAdet" onclick="sortData('gr.ToplamAdet')">Adet</th>
                        <th class="sort-head" data-sort="b.BolumAdi" onclick="sortData('b.BolumAdi')">Bolum</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="report-pager">
            <div id="pageInfo" class="text-muted small">Hazir</div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <button id="btnPrev" class="report-page-box" onclick="prevPage()">&#9664;</button>
                <span id="pageNumbers" class="report-page-numbers"></span>
                <button id="btnNext" class="report-page-box" onclick="nextPage()">&#9654;</button>
            </div>
        </div>
    </section>

    {{-- Hidden JS compat elements --}}
    <span id="summaryRecordsInline" style="display:none;">0</span>
    <span id="summaryAmountInline" style="display:none;">0</span>
    <span id="summaryPerformanceInline" style="display:none;">-</span>
    <span id="summaryPersonnelMeta" style="display:none;">Tum personeller</span>
    <span id="summaryDateMeta" style="display:none;">Tum kayitlar</span>
    <span id="summaryPageMeta" style="display:none;">Hazir</span>
    <span id="exportStateMeta" style="display:none;">Hazir</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let currentSortCol = 'gr.No';
let currentSortDir = 'desc';
let totalPages = 1;

function initLookups() {
    return fetch('/api/reports/lookups').then((r) => r.json()).then((data) => {
        const select = document.getElementById('personnelFilter');
        (data.personnel || []).forEach((person) => {
            select.innerHTML += `<option value="${person.id}">${escapeHtml(`${person.name || ''} ${person.surname || ''}`.trim())}</option>`;
        });
        updateFilterSummary();
    }).catch((error) => console.error('Personnel lookup error', error));
}

function onDateOptionChange() {
    const isCustom = document.getElementById('dateOptionFilter').value === 'tarih';
    document.getElementById('customDateRange').classList.toggle('is-visible', isCustom);
    updateFilterSummary();
    resetAndLoad();
}

function updateSortIndicators() {
    document.querySelectorAll('.sort-head').forEach((el) => el.classList.remove('sort-asc', 'sort-desc'));
    const active = document.querySelector(`.sort-head[data-sort="${currentSortCol}"]`);
    if (active) active.classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
}

function sortData(col) {
    if (currentSortCol === col) currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    else { currentSortCol = col; currentSortDir = 'asc'; }
    updateSortIndicators();
    resetAndLoad();
}

function resetReportFilters() {
    document.getElementById('personnelFilter').value = '';
    document.getElementById('dateOptionFilter').value = 'hepsi';
    document.getElementById('perPage').value = '20';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('customDateRange').classList.remove('is-visible');
    currentPage = 1; currentSortCol = 'gr.No'; currentSortDir = 'desc';
    updateSortIndicators(); updateFilterSummary();
    document.getElementById('exportStateMeta').textContent = 'Hazir';
    loadData();
}

function resetAndLoad() { currentPage = 1; loadData(); }

function loadData() {
    document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Yukleniyor...</td></tr>';
    document.getElementById('summaryQueryState').textContent = 'Sorgulaniyor';
    document.getElementById('summaryQueryState').className = 'soft-badge warning';
    document.getElementById('summaryStatusChip').textContent = 'Yukleniyor';
    document.getElementById('summaryStatusChip').className = 'soft-badge warning';

    const params = new URLSearchParams();
    params.append('page', currentPage);
    params.append('per_page', document.getElementById('perPage').value);
    params.append('sort_by', currentSortCol);
    params.append('sort_dir', currentSortDir);
    const personnel = document.getElementById('personnelFilter').value;
    if (personnel) params.append('personnel_id', personnel);
    const dateOption = document.getElementById('dateOptionFilter').value;
    if (dateOption !== 'hepsi') {
        params.append('date_filter', dateOption);
        if (dateOption === 'tarih') {
            params.append('start_date', document.getElementById('startDate').value);
            params.append('end_date', document.getElementById('endDate').value);
        }
    }

    fetch('/api/reports/tasks?' + params.toString()).then((r) => r.json()).then((data) => {
        const rows = data.data || [];
        renderTable(rows);
        renderPager(data);
        updatePersonnelSummary(rows, data);
        updateFilterSummary(data);
        document.getElementById('summaryQueryState').textContent = 'Hazir';
        document.getElementById('summaryQueryState').className = 'soft-badge success';
        document.getElementById('summaryStatusChip').textContent = 'Guncel';
        document.getElementById('summaryStatusChip').className = 'soft-badge success';
        document.getElementById('summaryUpdatedInline').textContent = formatClock(new Date());
    }).catch(() => {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Veri yuklenemedi.</td></tr>';
        document.getElementById('summaryQueryState').textContent = 'Hata';
        document.getElementById('summaryStatusChip').textContent = 'Hata';
        document.getElementById('summaryQueryState').className = 'soft-badge danger';
        document.getElementById('summaryStatusChip').className = 'soft-badge danger';
    });
}

function renderTable(rows) {
    if (!rows.length) { document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Kayit yok.</td></tr>'; return; }
    document.getElementById('tableBody').innerHTML = rows.map((row) => `
        <tr>
            <td class="small">${escapeHtml(row.No || '-')}</td>
            <td>${escapeHtml(row.TamAd || '-')}</td>
            <td class="small">${escapeHtml(row.UrunID || '-')}</td>
            <td>${escapeHtml(row.AraUrunAdi || '-')}</td>
            <td class="small">${escapeHtml(row.GorevBaslamaTarihi || '-')}</td>
            <td class="small">${escapeHtml(row.GorevBitisTarihi || '-')}</td>
            <td><span class="soft-badge">${formatPerformance(row.Performans)}</span></td>
            <td><span class="soft-badge success">${formatNumber(row.ToplamAdet || 0)}</span></td>
            <td>${escapeHtml(row.BolumAdi || '-')}</td>
        </tr>
    `).join('');
}

function renderPager(response) {
    totalPages = response.last_page || 1;
    const current = response.current_page || currentPage;
    const total = response.total || 0;
    document.getElementById('pageInfo').textContent = `Toplam ${formatNumber(total)} kayit, sayfa ${current} / ${totalPages}`;
    document.getElementById('summaryPageMeta').textContent = `${current} / ${totalPages}`;
    document.getElementById('btnPrev').disabled = current <= 1;
    document.getElementById('btnNext').disabled = current >= totalPages;
    const start = Math.max(1, current - 2);
    const end = Math.min(totalPages, start + 4);
    let html = '';
    for (let i = start; i <= end; i++) html += `<button class="report-page-box ${i === current ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
    document.getElementById('pageNumbers').innerHTML = html;
}

function prevPage() { if (currentPage > 1) { currentPage--; loadData(); } }
function nextPage() { if (currentPage < totalPages) { currentPage++; loadData(); } }
function goToPage(page) { currentPage = page; loadData(); }

function exportExcel() {
    Swal.fire({ title: 'Excel aktarilsin mi?', text: 'Aktif filtrelere gore tum kayitlar indirilecek.', icon: 'question', showCancelButton: true, confirmButtonText: 'Evet', cancelButtonText: 'Vazgec', confirmButtonColor: '#0d9488' }).then((result) => {
        if (!result.isConfirmed) return;
        const params = new URLSearchParams(); params.append('export', 'excel');
        const personnel = document.getElementById('personnelFilter').value;
        if (personnel) params.append('personnel_id', personnel);
        const dateOption = document.getElementById('dateOptionFilter').value;
        if (dateOption !== 'hepsi') { params.append('date_filter', dateOption); if (dateOption === 'tarih') { params.append('start_date', document.getElementById('startDate').value); params.append('end_date', document.getElementById('endDate').value); } }
        document.getElementById('exportStateMeta').textContent = 'Excel baslatildi';
        window.location.href = '/api/reports/tasks-export?' + params.toString();
    });
}

function updatePersonnelSummary(rows, response = {}) {
    const totalRecords = Number(response.total || rows.length || 0);
    const totalAmount = rows.reduce((sum, row) => sum + Number(row.ToplamAdet || 0), 0);
    const performanceRows = rows.map((row) => Number(String(row.Performans ?? '').replace(',', '.'))).filter((v) => !Number.isNaN(v) && v > 0);
    const avg = performanceRows.length ? performanceRows.reduce((s, v) => s + v, 0) / performanceRows.length : null;
    document.getElementById('summaryRecordsInline').textContent = formatNumber(rows.length);
    document.getElementById('summaryAmountInline').textContent = formatNumber(totalAmount);
    document.getElementById('summaryPerformanceInline').textContent = avg === null ? '-' : formatPerformance(avg);
    document.getElementById('summaryRecords').textContent = formatNumber(totalRecords);
    document.getElementById('summaryAmount').textContent = formatNumber(totalAmount);
    document.getElementById('summaryPerformance').textContent = avg === null ? '-' : formatPerformance(avg);
}

function updateFilterSummary(response = {}) {
    const pSel = document.getElementById('personnelFilter');
    const pLabel = pSel.options[pSel.selectedIndex]?.text || 'Tum Personeller';
    const dateValue = document.getElementById('dateOptionFilter').value;
    const perPage = document.getElementById('perPage').value;
    document.getElementById('summaryPersonnelMeta').textContent = pSel.value ? pLabel : 'Tum personeller';
    document.getElementById('summaryDateMeta').textContent = formatDateMode(dateValue);
    document.getElementById('summaryFilterMode').textContent = `${pSel.value ? 'Kisi' : 'Tum'} / ${perPage}`;
    if (!response.current_page) document.getElementById('summaryPageMeta').textContent = 'Hazir';
}

function formatDateMode(value) {
    const map = { hepsi: 'Tum', gun: 'Gun', hafta: 'Hafta', ay: 'Ay', '6ay': '6Ay', yil: 'Yil', tarih: 'Ozel' };
    return map[value] || 'Tum';
}

function formatPerformance(value) { const n = Number(String(value ?? '').replace(',', '.')); if (Number.isNaN(n)) return '-'; return n.toLocaleString('tr-TR', { maximumFractionDigits: 2 }); }
function formatNumber(value) { return Number(value || 0).toLocaleString('tr-TR'); }
function formatClock(date) { return date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }); }
function escapeHtml(value) { if (value === null || value === undefined) return ''; return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }

setInterval(() => { if (!document.hidden && currentPage === 1) loadData(); }, 20000);

document.addEventListener('DOMContentLoaded', () => { updateSortIndicators(); updateFilterSummary(); initLookups().finally(loadData); });
</script>
@endpush
