@extends('layouts.app')

@section('title', 'İş Emri Havuzu')

@section('page-actions')
    <a href="{{ route('workorders.create') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>İş Emri Ver
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadHavuz()">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .pool-control-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    .pool-table .badge { white-space: nowrap; }
    .group-header td { background: var(--z-bg-soft) !important; font-weight: 700; border-bottom: 1px dashed var(--z-border); }
    .action-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
    .sortable { cursor: pointer; }
    .sortable.sort-asc::after { content: ' ↑'; }
    .sortable.sort-desc::after { content: ' ↓'; }
    .pool-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 20px; flex-wrap: wrap; }
</style>
@endpush

@section('content')
    {{-- Inline Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Görünen Kayıt</p>
            <h3 class="metric-value" id="summaryVisible">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Üretilebilir Adet</p>
            <h3 class="metric-value" id="summaryAdet">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Plan</p>
            <h3 class="metric-value" id="summaryToplam">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Görünüm</p>
            <h3 class="metric-value" id="summaryViewModeCard" style="font-size: 1rem;">Detaylı</h3>
        </article>
    </div>

    {{-- Filters --}}
    <div class="panel-surface">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Arama</label>
                <input type="text" id="searchInput" class="form-control" placeholder="Ürün, bölüm veya açıklama..." onkeyup="debounceLoad()">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label">Görünüm</label>
                <select id="viewMode" class="form-select" onchange="onViewModeChange()">
                    <option value="detayli" selected>Detaylı</option>
                    <option value="detaysiz">Detaysız</option>
                    <option value="ozet">Özet</option>
                    <option value="tamozet">Tam Özet</option>
                    <option value="bolum">Bölüme Göre</option>
                    <option value="urunID">Ürün ID Göre</option>
                    <option value="araUrun">Ara Ürüne Göre</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6" id="deptFilterWrap" style="display:none;">
                <label class="form-label">Bölüm</label>
                <select id="deptFilter" class="form-select" onchange="loadHavuz()">
                    <option value="">Tüm Bölümler</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6" id="araUrunFilterWrap" style="display:none;">
                <label class="form-label">Ara Ürün</label>
                <select id="araUrunFilter" class="form-select" onchange="loadHavuz()">
                    <option value="">Tüm Ara Ürünler</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6" id="urunIDFilterWrap" style="display:none;">
                <label class="form-label">Ürün ID</label>
                <select id="urunIDFilter" class="form-select" onchange="loadHavuz()">
                    <option value="">Tüm Ürünler</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label">Sayfa Boyutu</label>
                <select id="pageSizeSelect" class="form-select" onchange="changePageSize()">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="pool-control-actions">
            <button class="btn btn-outline-danger btn-sm" type="button" onclick="tumunuSil()">
                <i class="bi bi-trash me-1"></i>Temizle
            </button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="loadHavuz()">
                <i class="bi bi-arrow-repeat me-1"></i>Tekrar Yükle
            </button>
            <span class="soft-badge" id="totalBadge">0 kayıt</span>
            <span class="soft-badge" style="margin-left: auto;">Son: <strong id="summaryLastRefresh">—</strong></span>
        </div>
    </div>

    {{-- Table --}}
    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy">
                <h3>İş emri akışı</h3>
            </div>
            <div class="panel-toolbar-meta">
                <span class="soft-badge" id="pagerInfo">0-0 / 0</span>
            </div>
        </div>

        <div class="table-shell">
            <table class="table-modern pool-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="id" onclick="sortColumn('id')">No</th>
                        <th class="sortable" data-sort="product_name" onclick="sortColumn('product_name')">Ürün ID</th>
                        <th class="sortable" data-sort="component_name" onclick="sortColumn('component_name')">Ara Ürün</th>
                        <th class="sortable" data-sort="gorev_tarihi" onclick="sortColumn('gorev_tarihi')">Tarih</th>
                        <th class="sortable" data-sort="gorev_saati" onclick="sortColumn('gorev_saati')">Saat</th>
                        <th class="sortable" data-sort="adet" onclick="sortColumn('adet')">Üretilebilir</th>
                        <th class="sortable" data-sort="toplam_adet" onclick="sortColumn('toplam_adet')">Toplam</th>
                        <th>Açıklama</th>
                        <th class="sortable" data-sort="department_name" onclick="sortColumn('department_name')">Bölüm</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="10" class="text-muted py-4 text-center">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pool-pager">
            <div class="text-muted small">Sayfalar filtre sonucuna göre güncellenir.</div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrev" onclick="prevPage()" disabled>
                    <i class="bi bi-chevron-left"></i>
                </button>
                <span id="pageNumbers"></span>
                <button class="btn btn-outline-secondary btn-sm" id="btnNext" onclick="nextPage()">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    {{-- Hidden elements referenced by JS --}}
    <span id="summaryVisibleInline" style="display:none;">0</span>
    <span id="summaryAdetInline" style="display:none;">0</span>
    <span id="summaryToplamInline" style="display:none;">0</span>
    <span id="summaryViewMode" style="display:none;">Detaylı</span>
    <span id="summaryFocus" style="display:none;">Tüm havuz</span>
    <span id="summaryFocusInline" style="display:none;">Tüm havuz</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let debounceTimer;
let allRows = [];
let filteredRows = [];
let currentPage = 1;
let pageSize = 20;
let currentSortField = '';
let currentSortDir = 'asc';
let lookupData = { departments: [], araUrunler: [], urunIDler: [] };

const viewModeLabels = {
    detayli: 'Detaylı',
    detaysiz: 'Detaysız',
    ozet: 'Özet',
    tamozet: 'Tam Özet',
    bolum: 'Bölüm',
    urunID: 'Ürün ID',
    araUrun: 'Ara Ürün',
};

function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadHavuz, 300);
}

function setRefreshStamp(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('summaryLastRefresh').textContent = label;
}

function initLookups() {
    fetch('/api/database/departments', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            lookupData.departments = data.data || [];
            document.getElementById('deptFilter').innerHTML = '<option value="">Tüm Bölümler</option>' + 
                lookupData.departments.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
        });

    fetch('/api/database/components?limit=9999', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            lookupData.araUrunler = data.data || [];
            document.getElementById('araUrunFilter').innerHTML = '<option value="">Tüm Ara Ürünler</option>' + 
                lookupData.araUrunler.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        });

    fetch('/api/database/products?limit=9999', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            lookupData.urunIDler = data.data || [];
            document.getElementById('urunIDFilter').innerHTML = '<option value="">Tüm Ürünler</option>' + 
                lookupData.urunIDler.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        });
}

function onViewModeChange() {
    const mode = document.getElementById('viewMode').value;
    document.getElementById('deptFilterWrap').style.display = mode === 'bolum' ? '' : 'none';
    document.getElementById('araUrunFilterWrap').style.display = mode === 'araUrun' ? '' : 'none';
    document.getElementById('urunIDFilterWrap').style.display = mode === 'urunID' ? '' : 'none';
    loadHavuz();
}

function loadHavuz() {
    fetch('/api/database/pool-tasks', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            allRows = (data.data || []).map((row) => {
                row.gorev_tarihi = row.gorev_tarihi || '';
                row.gorev_saati = row.gorev_saati || '';
                return row;
            });
            setRefreshStamp();
            applyFiltersAndRender();
        })
        .catch(() => {
            setRefreshStamp('Veri alınamadı');
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="10" class="text-center py-3 text-danger">Veri yüklenemedi.</td></tr>';
        });
}

function applyFiltersAndRender() {
    const search = (document.getElementById('searchInput').value || '').toLowerCase();
    const viewMode = document.getElementById('viewMode').value;
    const deptFilter = document.getElementById('deptFilter').value;
    const araUrunFilter = document.getElementById('araUrunFilter').value;
    const urunIDFilter = document.getElementById('urunIDFilter').value;

    let rows = [...allRows];

    if (search) {
        rows = rows.filter((row) =>
            (row.component_name || '').toLowerCase().includes(search) ||
            (row.product_name || '').toLowerCase().includes(search) ||
            (row.department_name || '').toLowerCase().includes(search) ||
            (row.aciklama || '').toLowerCase().includes(search)
        );
    }

    if (viewMode === 'detaysiz') {
        rows = rows.filter((row) => (parseFloat(row.adet) || 0) > 0);
    } else if (viewMode === 'ozet') {
        rows = rows.filter((row) => row.product_name && row.component_name && row.product_name === row.component_name);
    } else if (viewMode === 'bolum' && deptFilter) {
        rows = rows.filter((row) => String(row.department_id) === String(deptFilter));
    } else if (viewMode === 'araUrun' && araUrunFilter) {
        rows = rows.filter((row) => String(row.ara_urun_no) === String(araUrunFilter));
    } else if (viewMode === 'urunID' && urunIDFilter) {
        rows = rows.filter((row) => String(row.urun_id_no) === String(urunIDFilter));
    }

    filteredRows = rows;

    if (currentSortField) {
        sortData();
    }

    updateSummaryCards(viewMode);
    renderCurrentView();
}

function updateSummaryCards(viewMode) {
    const totalAdet = filteredRows.reduce((sum, row) => sum + (parseFloat(row.adet) || 0), 0);
    const totalToplam = filteredRows.reduce((sum, row) => sum + (parseFloat(row.toplam_adet) || 0), 0);
    const focusLabel = resolveFocusLabel(viewMode);
    const modeLabel = viewModeLabels[viewMode] || 'Detaylı';

    document.getElementById('summaryVisible').textContent = filteredRows.length.toLocaleString('tr-TR');
    document.getElementById('summaryVisibleInline').textContent = filteredRows.length.toLocaleString('tr-TR');
    document.getElementById('summaryAdet').textContent = totalAdet.toLocaleString('tr-TR');
    document.getElementById('summaryAdetInline').textContent = totalAdet.toLocaleString('tr-TR');
    document.getElementById('summaryToplam').textContent = totalToplam.toLocaleString('tr-TR');
    document.getElementById('summaryToplamInline').textContent = totalToplam.toLocaleString('tr-TR');
    document.getElementById('summaryViewMode').textContent = modeLabel;
    document.getElementById('summaryViewModeCard').textContent = modeLabel;
    document.getElementById('summaryFocus').textContent = focusLabel;
    document.getElementById('summaryFocusInline').textContent = focusLabel;
    document.getElementById('totalBadge').textContent = `${filteredRows.length.toLocaleString('tr-TR')} kayıt`;
}

function resolveFocusLabel(viewMode) {
    if (viewMode === 'bolum') return getSelectedLabel('deptFilter', 'Tüm Bölümler');
    if (viewMode === 'araUrun') return getSelectedLabel('araUrunFilter', 'Tüm Ara Ürünler');
    if (viewMode === 'urunID') return getSelectedLabel('urunIDFilter', 'Tüm Ürünler');
    return 'Tüm havuz';
}

function getSelectedLabel(id, fallback) {
    const select = document.getElementById(id);
    if (!select || !select.value) return fallback;
    const option = select.options[select.selectedIndex];
    return option ? option.text : fallback;
}

function renderCurrentView() {
    const viewMode = document.getElementById('viewMode').value;
    const deptFilter = document.getElementById('deptFilter').value;
    const araUrunFilter = document.getElementById('araUrunFilter').value;
    const urunIDFilter = document.getElementById('urunIDFilter').value;

    if (viewMode === 'tamozet') { renderTamOzet(); return; }
    if (['bolum', 'araUrun', 'urunID'].includes(viewMode) && !deptFilter && !araUrunFilter && !urunIDFilter) {
        const groupKey = viewMode === 'bolum' ? 'department_name' : viewMode === 'urunID' ? 'product_name' : 'component_name';
        renderGrouped(groupKey);
        return;
    }
    renderFlat();
}

function isOverviewGroupedMode() {
    const viewMode = document.getElementById('viewMode').value;
    const deptFilter = document.getElementById('deptFilter').value;
    const araUrunFilter = document.getElementById('araUrunFilter').value;
    const urunIDFilter = document.getElementById('urunIDFilter').value;
    return ['bolum', 'araUrun', 'urunID'].includes(viewMode) && !deptFilter && !araUrunFilter && !urunIDFilter;
}

function sortColumn(field) {
    if (currentSortField === field) {
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortField = field;
        currentSortDir = 'asc';
    }
    document.querySelectorAll('th.sortable').forEach((th) => th.classList.remove('sort-asc', 'sort-desc'));
    const activeTh = document.querySelector(`th[data-sort="${field}"]`);
    if (activeTh) activeTh.classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
    sortData();
    currentPage = 1;
    renderCurrentView();
}

function sortData() {
    const numericFields = ['id', 'adet', 'toplam_adet'];
    const isNumeric = numericFields.includes(currentSortField);
    filteredRows.sort((a, b) => {
        let left = a[currentSortField] || '';
        let right = b[currentSortField] || '';
        if (isNumeric) { left = parseFloat(left) || 0; right = parseFloat(right) || 0; }
        else { left = String(left).toLowerCase(); right = String(right).toLowerCase(); }
        const comparison = left < right ? -1 : left > right ? 1 : 0;
        return currentSortDir === 'asc' ? comparison : -comparison;
    });
}

function renderFlat() {
    const start = (currentPage - 1) * pageSize;
    const display = filteredRows.slice(start, start + pageSize);
    if (!display.length) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="10" class="text-muted py-4 text-center">Gösterilecek iş emri bulunamadı.</td></tr>';
        renderPager(0);
        return;
    }
    document.getElementById('tableBody').innerHTML = display.map(renderRow).join('');
    renderPager(filteredRows.length);
}

function renderGrouped(groupKey) {
    const groups = {};
    filteredRows.forEach((row) => {
        const key = row[groupKey] || 'Tanımsız';
        (groups[key] = groups[key] || []).push(row);
    });
    let html = '';
    Object.keys(groups).sort().forEach((key) => {
        const group = groups[key];
        const totalAdet = group.reduce((sum, row) => sum + (parseFloat(row.adet) || 0), 0);
        const totalToplam = group.reduce((sum, row) => sum + (parseFloat(row.toplam_adet) || 0), 0);
        html += `<tr class="group-header">
            <td colspan="5">${escHtml(key)}</td>
            <td><strong>${totalAdet.toLocaleString('tr-TR')}</strong></td>
            <td><strong>${totalToplam.toLocaleString('tr-TR')}</strong></td>
            <td colspan="3">${group.length} satır</td>
        </tr>`;
        html += group.map(renderRow).join('');
    });
    document.getElementById('tableBody').innerHTML = html || '<tr><td colspan="10" class="text-muted py-4 text-center">Kayıt yok.</td></tr>';
    renderPager(0);
}

function renderTamOzet() {
    const ozetRows = filteredRows.filter((row) => row.product_name && row.component_name && row.product_name === row.component_name);
    const groups = {};
    ozetRows.forEach((row) => {
        const key = `${row.product_name}||${row.component_name}||${row.department_name}`;
        if (!groups[key]) groups[key] = { ...row, toplam_adet: 0 };
        groups[key].toplam_adet += parseFloat(row.toplam_adet) || 0;
    });
    const summarized = Object.values(groups);
    if (currentSortField) {
        const numericFields = ['id', 'adet', 'toplam_adet'];
        const isNumeric = numericFields.includes(currentSortField);
        summarized.sort((a, b) => {
            let left = a[currentSortField] || '';
            let right = b[currentSortField] || '';
            if (isNumeric) { left = parseFloat(left) || 0; right = parseFloat(right) || 0; }
            else { left = String(left).toLowerCase(); right = String(right).toLowerCase(); }
            const comparison = left < right ? -1 : left > right ? 1 : 0;
            return currentSortDir === 'asc' ? comparison : -comparison;
        });
    }
    const start = (currentPage - 1) * pageSize;
    const display = summarized.slice(start, start + pageSize);
    if (!display.length) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="10" class="text-muted py-4 text-center">Özet kayıt bulunamadı.</td></tr>';
        renderPager(0);
        return;
    }
    document.getElementById('tableBody').innerHTML = display.map((row) => `
        <tr>
            <td>${row.id}</td>
            <td>${escHtml(row.product_name || '-')}</td>
            <td><span class="badge bg-secondary">${escHtml(row.component_name || '-')}</span></td>
            <td colspan="2"><em class="text-muted">Özet</em></td>
            <td>-</td>
            <td><strong>${(parseFloat(row.toplam_adet) || 0).toLocaleString('tr-TR')}</strong></td>
            <td>-</td>
            <td>${escHtml(row.department_name || 'Tanımsız')}</td>
            <td>
                <button class="btn btn-outline-danger action-btn btn-sm" title="Sil" onclick="deleteByProduct(${row.urun_id_no})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
    renderPager(summarized.length);
}

function renderRow(row) {
    return `<tr data-id="${row.id}">
        <td>${row.id}</td>
        <td>${escHtml(row.product_name || '-')}</td>
        <td><span class="badge bg-secondary">${escHtml(row.component_name || 'Bilinmiyor')}</span></td>
        <td>${escHtml(row.gorev_tarihi || '-')}</td>
        <td>${escHtml(row.gorev_saati || '-')}</td>
        <td><strong>${(parseFloat(row.adet) || 0).toLocaleString('tr-TR')}</strong></td>
        <td>${(parseFloat(row.toplam_adet) || 0).toLocaleString('tr-TR')}</td>
        <td>${escHtml(row.aciklama || '')}</td>
        <td>${escHtml(row.department_name || 'Tanımsız')}</td>
        <td>
            <button class="btn btn-outline-secondary action-btn btn-sm" title="Düzenle" onclick="editRow(${row.id}, ${row.adet}, ${row.toplam_adet})">
                <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-outline-danger action-btn btn-sm" title="Sil" onclick="deleteRow(${row.id})">
                <i class="bi bi-trash"></i>
            </button>
        </td>
    </tr>`;
}

function renderPager(total) {
    const container = document.getElementById('pagerInfo');
    const numsEl = document.getElementById('pageNumbers');
    if (total <= 0) {
        container.textContent = isOverviewGroupedMode() ? 'Gruplanmış görünüm' : '0 kayıt';
        numsEl.innerHTML = '';
        document.getElementById('btnPrev').disabled = true;
        document.getElementById('btnNext').disabled = true;
        return;
    }
    const totalPages = Math.ceil(total / pageSize);
    if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
    const startItem = (currentPage - 1) * pageSize + 1;
    const endItem = Math.min(currentPage * pageSize, total);
    container.textContent = `${startItem}-${endItem} / ${total}`;
    document.getElementById('btnPrev').disabled = currentPage === 1;
    document.getElementById('btnNext').disabled = currentPage === totalPages;
    let numHtml = '';
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, startPage + 4);
    for (let i = startPage; i <= endPage; i++) {
        numHtml += `<span class="page-number${i === currentPage ? ' active' : ''}" onclick="goToPage(${i})">${i}</span>`;
    }
    numsEl.innerHTML = numHtml;
}

function changePageSize() {
    pageSize = parseInt(document.getElementById('pageSizeSelect').value, 10) || 20;
    currentPage = 1;
    applyFiltersAndRender();
}

function prevPage() { if (currentPage > 1) { currentPage--; applyFiltersAndRender(); } }
function nextPage() { currentPage++; applyFiltersAndRender(); }
function goToPage(page) { currentPage = page; applyFiltersAndRender(); }

function deleteRow(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: 'Bu havuz kaydını silmek üzeresiniz!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch(`/api/database/pool-tasks/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }
        })
            .then((r) => r.json())
            .then((data) => {
                Swal.fire('Silindi!', data.message || 'Kayıt silindi.', 'success');
                loadHavuz();
            })
            .catch(() => Swal.fire('Hata', 'Silme işleminde hata.', 'error'));
    });
}

function deleteByProduct(urunIDNo) {
    Swal.fire({
        title: 'Bu ürünün tüm havuz kayıtlarını silmek istediğinize emin misiniz?',
        text: 'Bu işlem geri alınamaz!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Evet, hepsini sil!',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('/api/database/pool-tasks/delete-by-product', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify({ urun_id_no: urunIDNo })
        })
            .then((r) => r.json())
            .then((data) => {
                Swal.fire('Silindi!', data.message || 'Kayıtlar silindi.', 'success');
                loadHavuz();
            })
            .catch(() => Swal.fire('Hata', 'Silme işleminde hata.', 'error'));
    });
}

function editRow(id, mevcutAdet, mevcutToplam) {
    Swal.fire({
        title: 'Havuz Kaydını Düzenle',
        html: `
            <div class="text-start">
                <label class="form-label">Üretilebilir Adet</label>
                <input id="swal-adet" type="number" class="swal2-input" value="${mevcutAdet}" min="0">
                <label class="form-label mt-2">Toplam Adet</label>
                <input id="swal-toplam" type="number" class="swal2-input" value="${mevcutToplam}" min="0">
            </div>`,
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: () => ({
            adet: parseInt(document.getElementById('swal-adet').value, 10),
            toplam_adet: parseInt(document.getElementById('swal-toplam').value, 10)
        })
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch(`/api/database/pool-tasks/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify(result.value)
        })
            .then((r) => r.json())
            .then((data) => {
                Swal.fire('Güncellendi!', data.message || 'Kayıt güncellendi.', 'success');
                loadHavuz();
            })
            .catch(() => Swal.fire('Hata', 'Güncelleme işleminde hata.', 'error'));
    });
}

function tumunuSil() {
    const viewMode = document.getElementById('viewMode').value;
    let mesaj = 'Bu işlem tüm havuz kayıtlarını silecektir!';
    let body = {};
    if (viewMode === 'bolum' && document.getElementById('deptFilter').value) {
        mesaj = 'Seçili bölümdeki tüm havuz kayıtları silinecek!';
        body = { department_id: document.getElementById('deptFilter').value };
    } else if (viewMode === 'araUrun' && document.getElementById('araUrunFilter').value) {
        mesaj = 'Seçili ara ürüne ait tüm havuz kayıtları silinecek!';
        body = { ara_urun_no: document.getElementById('araUrunFilter').value };
    } else if (viewMode === 'urunID' && document.getElementById('urunIDFilter').value) {
        mesaj = 'Seçili ürün ID’ye ait tüm havuz kayıtları silinecek!';
        body = { urun_id_no: document.getElementById('urunIDFilter').value };
    }
    Swal.fire({
        title: 'Tüm Havuzu Temizle?',
        text: `${mesaj} Bu işlem geri alınamaz.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Evet, hepsini sil!',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('/api/database/pool-tasks/delete-all', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify(body)
        })
            .then((r) => r.json())
            .then((data) => {
                Swal.fire('Silindi!', data.message || 'Kayıtlar silindi.', 'success');
                loadHavuz();
            })
            .catch(() => Swal.fire('Hata', 'Toplu silme işleminde hata.', 'error'));
    });
}

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

setInterval(loadHavuz, 20000);
initLookups();
loadHavuz();
</script>
@endpush
