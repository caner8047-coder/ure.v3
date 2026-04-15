@extends('layouts.app')

@section('title', 'Stok Yönetimi')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetBuffer()">
        <i class="bi bi-layers me-1"></i>Tamponu Eşitle
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportData()">
        <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadStocks(1)">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .stocks-table .sortable { cursor: pointer; }
    .stocks-table .sortable.sort-asc::after { content: ' ↑'; }
    .stocks-table .sortable.sort-desc::after { content: ' ↓'; }
    .stocks-chip { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 4px; background: var(--z-bg-soft); font-size: 0.72rem; font-weight: 600; }
    .stocks-chip.success { background: var(--z-success-soft); color: var(--z-success); }
    .stocks-chip.warning { background: var(--z-warning-soft); color: var(--z-warning); }
    .stocks-chip.danger { background: var(--z-danger-soft); color: var(--z-danger); }
    .stocks-pager { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 10px 20px; flex-wrap: wrap; }
    .stocks-action-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam Kayıt</p>
            <h3 class="metric-value" id="summaryRecords">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Kullanılabilir</p>
            <h3 class="metric-value" id="summaryAvailable">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Tampon Toplam</p>
            <h3 class="metric-value" id="summaryBuffer">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Kritik Satır</p>
            <h3 class="metric-value" id="summaryCritical">0</h3>
        </article>
    </div>

    <div class="panel-surface">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Bölüm</label>
                <select id="filterDept" class="form-select" onchange="loadStocks(1)">
                    <option value="">Tüm Bölümler</option>
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Ürün Çeşidi</label>
                <select id="filterType" class="form-select" onchange="loadStocks(1)">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="col-xl-4 col-md-6">
                <label class="form-label">Arama</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="Bölüm veya ara ürün ara..." onkeydown="handleSearchKeydown(event)">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label">Sayfa</label>
                <select id="pageSizeSelect" class="form-select" onchange="changePageSize()">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <button class="btn btn-primary btn-sm" type="button" onclick="loadStocks(1)"><i class="bi bi-search me-1"></i>Filtrele</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Sıfırla</button>
            <span class="soft-badge" style="margin-left: auto;">Son: <strong id="summaryUpdatedInline">—</strong></span>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy"><h3>Departman stok havuzu</h3></div>
            <div class="panel-toolbar-meta"><span class="soft-badge" id="pageInfo">0-0 / 0</span></div>
        </div>
        <div class="table-shell">
            <table class="table-modern stocks-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="No" onclick="sortBy('No')">No</th>
                        <th class="sortable" data-sort="BolumAdi" onclick="sortBy('BolumAdi')">Bölüm</th>
                        <th class="sortable" data-sort="AraUrunAdi" onclick="sortBy('AraUrunAdi')">Ara Ürün</th>
                        <th>Ürün Çeşidi</th>
                        <th class="sortable" data-sort="Adet" onclick="sortBy('Adet')">Adet</th>
                        <th class="sortable" data-sort="TamponMiktar" onclick="sortBy('TamponMiktar')">Tampon</th>
                        <th>Kullanılabilir</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody id="stockBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="stocks-pager">
            <div class="text-muted small">Aktif filtrelere göre güncellenir.</div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrevPage" onclick="prevPage()" disabled><i class="bi bi-chevron-left"></i></button>
                <span id="pagination"></span>
                <button class="btn btn-outline-secondary btn-sm" id="btnNextPage" onclick="nextPage()" disabled><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </section>

    {{-- Hidden elements for JS compat --}}
    <span id="summaryRecordsInline" style="display:none;">0</span>
    <span id="summaryAvailableInline" style="display:none;">0</span>
    <span id="summaryCriticalInline" style="display:none;">0</span>
    <span id="summaryRecordsMeta" style="display:none;">0</span>
    <span id="summaryAvailableMeta" style="display:none;">0</span>
    <span id="summaryBufferMeta" style="display:none;">0</span>
    <span id="summaryCriticalMeta" style="display:none;">0</span>
    <span id="summaryFocus" style="display:none;">Tüm stoklar</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentSort = 'No';
let currentDir = 'asc';
let currentPage = 1;
let pageSize = 20;
let totalRecords = 0;
let currentRows = [];

function loadLookups() {
    fetch('/api/stocks/lookups', { cache: 'no-store' })
        .then((r) => r.json())
        .then((data) => {
            const deptSel = document.getElementById('filterDept');
            (data.departments || []).forEach((department) => {
                deptSel.innerHTML += `<option value="${department.id}">${department.name}</option>`;
            });
            const typeSel = document.getElementById('filterType');
            (data.componentTypes || []).forEach((type) => {
                typeSel.innerHTML += `<option value="${type}">${type}</option>`;
            });
        });
}

function handleSearchKeydown(event) { if (event.key === 'Enter') loadStocks(1); }
function changePageSize() { pageSize = parseInt(document.getElementById('pageSizeSelect').value, 10) || 20; loadStocks(1); }

function sortBy(field) {
    if (currentSort === field) { currentDir = currentDir === 'asc' ? 'desc' : 'asc'; }
    else { currentSort = field; currentDir = 'asc'; }
    document.querySelectorAll('.stocks-table .sortable').forEach((th) => th.classList.remove('sort-asc', 'sort-desc'));
    const active = document.querySelector(`.stocks-table .sortable[data-sort="${field}"]`);
    if (active) active.classList.add(currentDir === 'asc' ? 'sort-asc' : 'sort-desc');
    loadStocks(1);
}

function loadStocks(page) {
    currentPage = page || 1;
    const params = new URLSearchParams({ page: currentPage, per_page: pageSize, sort_by: currentSort, sort_dir: currentDir });
    const dept = document.getElementById('filterDept').value;
    const type = document.getElementById('filterType').value;
    const search = document.getElementById('filterSearch').value.trim();
    if (dept) params.set('department_id', dept);
    if (type) params.set('component_type', type);
    if (search) params.set('search', search);

    fetch(`/api/stocks?${params.toString()}`, { cache: 'no-store' })
        .then((r) => r.json())
        .then((data) => {
            currentRows = data.data || [];
            totalRecords = Number(data.total || 0);
            currentPage = Number(data.current_page || 1);
            pageSize = Number(data.per_page || pageSize);
            renderRows(currentRows);
            updateSummary(currentRows, totalRecords);
            renderPagination(totalRecords, currentPage, pageSize);
            setRefreshStamp();
        })
        .catch(() => {
            document.getElementById('stockBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Stok verisi yüklenemedi.</td></tr>';
        });
}

function renderRows(rows) {
    const tbody = document.getElementById('stockBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Stok kaydı bulunamadı.</td></tr>'; return; }
    tbody.innerHTML = rows.map((stock) => {
        const available = Math.max(0, (stock.Adet || 0) - (stock.TamponMiktar || 0));
        const badgeClass = available > 0 ? 'stocks-chip success' : 'stocks-chip danger';
        return `<tr>
            <td>${stock.No}</td>
            <td>${escapeHtml(stock.BolumAdi || '-')}</td>
            <td>${escapeHtml(stock.AraUrunAdi || '-')}</td>
            <td><span class="stocks-chip">${escapeHtml(stock.UrunCesidi || '-')}</span></td>
            <td><strong>${formatNumber(stock.Adet || 0)}</strong></td>
            <td>${formatNumber(stock.TamponMiktar || 0)}</td>
            <td><span class="${badgeClass}">${formatNumber(available)}</span></td>
            <td><button class="btn btn-outline-secondary stocks-action-btn btn-sm" onclick="editStock(${stock.No}, ${stock.Adet || 0}, ${stock.TamponMiktar || 0})" title="Düzenle"><i class="bi bi-pencil"></i></button></td>
        </tr>`;
    }).join('');
}

function updateSummary(rows, total) {
    const availableTotal = rows.reduce((sum, stock) => sum + Math.max(0, (stock.Adet || 0) - (stock.TamponMiktar || 0)), 0);
    const bufferTotal = rows.reduce((sum, stock) => sum + Number(stock.TamponMiktar || 0), 0);
    const criticalCount = rows.filter((stock) => Math.max(0, (stock.Adet || 0) - (stock.TamponMiktar || 0)) <= 0).length;
    const focusLabel = resolveFocusLabel();
    document.getElementById('summaryFocus').textContent = focusLabel;
    document.getElementById('summaryRecords').textContent = formatNumber(total);
    document.getElementById('summaryRecordsInline').textContent = formatNumber(total);
    document.getElementById('summaryRecordsMeta').textContent = formatNumber(total);
    document.getElementById('summaryAvailable').textContent = formatNumber(availableTotal);
    document.getElementById('summaryAvailableInline').textContent = formatNumber(availableTotal);
    document.getElementById('summaryAvailableMeta').textContent = formatNumber(availableTotal);
    document.getElementById('summaryBuffer').textContent = formatNumber(bufferTotal);
    document.getElementById('summaryBufferMeta').textContent = formatNumber(bufferTotal);
    document.getElementById('summaryCritical').textContent = formatNumber(criticalCount);
    document.getElementById('summaryCriticalInline').textContent = formatNumber(criticalCount);
    document.getElementById('summaryCriticalMeta').textContent = formatNumber(criticalCount);
}

function resolveFocusLabel() {
    const dept = document.getElementById('filterDept');
    const type = document.getElementById('filterType');
    if (dept.value) return dept.options[dept.selectedIndex].text;
    if (type.value) return type.options[type.selectedIndex].text;
    return 'Tüm stoklar';
}

function renderPagination(total, page, perPage) {
    const info = document.getElementById('pageInfo');
    const pagination = document.getElementById('pagination');
    const prev = document.getElementById('btnPrevPage');
    const next = document.getElementById('btnNextPage');
    if (!total) { info.textContent = '0 kayıt'; pagination.innerHTML = ''; prev.disabled = true; next.disabled = true; return; }
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const startItem = (page - 1) * perPage + 1;
    const endItem = Math.min(page * perPage, total);
    info.textContent = `${startItem}-${endItem} / ${total}`;
    prev.disabled = page === 1;
    next.disabled = page === totalPages;
    let html = '';
    let startPage = Math.max(1, page - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
    for (let i = startPage; i <= endPage; i++) {
        html += `<span class="page-number${i === page ? ' active' : ''}" onclick="goToPage(${i})">${i}</span>`;
    }
    pagination.innerHTML = html;
}

function prevPage() { if (currentPage > 1) loadStocks(currentPage - 1); }
function nextPage() { const totalPages = Math.max(1, Math.ceil(totalRecords / pageSize)); if (currentPage < totalPages) loadStocks(currentPage + 1); }
function goToPage(page) { loadStocks(page); }

function resetFilters() {
    document.getElementById('filterDept').value = '';
    document.getElementById('filterType').value = '';
    document.getElementById('filterSearch').value = '';
    document.getElementById('pageSizeSelect').value = '20';
    pageSize = 20;
    loadStocks(1);
}

function exportData() {
    const params = new URLSearchParams({ sort_by: currentSort, sort_dir: currentDir });
    const dept = document.getElementById('filterDept').value;
    const type = document.getElementById('filterType').value;
    const search = document.getElementById('filterSearch').value.trim();
    if (dept) params.set('department_id', dept);
    if (type) params.set('component_type', type);
    if (search) params.set('search', search);
    window.location.href = `/api/stocks/export?${params.toString()}`;
}

function editStock(no, adet, tampon) {
    Swal.fire({
        title: 'Stok Kaydını Düzenle',
        html: `<div class="text-start"><label class="form-label">Adet</label><input id="swal-adet" type="number" class="swal2-input" value="${adet}" min="0"><label class="form-label mt-2">Tampon</label><input id="swal-tampon" type="number" class="swal2-input" value="${tampon}" min="0"></div>`,
        showCancelButton: true, confirmButtonText: 'Kaydet', cancelButtonText: 'İptal',
        preConfirm: () => ({ Adet: parseInt(document.getElementById('swal-adet').value, 10), TamponMiktar: parseInt(document.getElementById('swal-tampon').value, 10) })
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch(`/api/stocks/${no}`, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify(result.value) })
            .then((r) => r.json())
            .then((data) => { if (!data.success) { Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'); return; } Swal.fire('Güncellendi!', 'Stok kaydı kaydedildi.', 'success'); loadStocks(currentPage); })
            .catch(() => Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'));
    });
}

function resetBuffer() {
    Swal.fire({ title: 'Tamponlar eşitlensin mi?', text: 'Tüm kayıtlarda tampon miktarı mevcut adet ile eşitlenecek.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Evet, eşitle', cancelButtonText: 'Vazgeç' }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('/api/stocks/reset-buffer', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => { if (!data.success) { Swal.fire('Hata', 'Tamponlar güncellenemedi.', 'error'); return; } Swal.fire('Tamamlandı', 'Tampon miktarları eşitlendi.', 'success'); loadStocks(1); })
            .catch(() => Swal.fire('Hata', 'Tamponlar güncellenemedi.', 'error'));
    });
}

function setRefreshStamp() { document.getElementById('summaryUpdatedInline').textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }); }
function formatNumber(value) { return Number(value || 0).toLocaleString('tr-TR'); }
function escapeHtml(value) { return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

document.addEventListener('DOMContentLoaded', () => {
    const initialSort = document.querySelector('.stocks-table .sortable[data-sort="No"]');
    if (initialSort) initialSort.classList.add('sort-asc');
    loadLookups();
    loadStocks(1);
});
</script>
@endpush
