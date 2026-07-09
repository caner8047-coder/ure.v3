@extends('layouts.app')

@section('title', $reportTitle ?? 'Gorev Raporlari')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetReportFilters()">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Sifirla
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadReport()">
        <i class="bi bi-search me-1"></i>Raporu Yenile
    </button>
@endsection

@push('styles')
<style>
    .report-chip {
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
    .report-chip.success { background: var(--z-success-soft); color: var(--z-success); }
    .report-chip.warning { background: var(--z-warning-soft); color: var(--z-warning); }
    .report-chip.danger { background: var(--z-danger-soft); color: var(--z-danger); }

    .report-pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        padding: 12px 0 0;
        flex-wrap: wrap;
    }

    .sort-head {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }
    .sort-head::after {
        content: '';
        margin-left: 6px;
        font-size: 0.7rem;
    }
    .sort-head.sort-asc::after { content: '▲'; }
    .sort-head.sort-desc::after { content: '▼'; }
</style>
@endpush

@section('content')
    {{-- Inline Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Kayit Sayisi</p>
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
            <p class="metric-label">Filtre Durumu</p>
            <h3 class="metric-value" id="summaryFilterMode" style="font-size: 1rem;">Tum</h3>
        </article>
    </div>

    {{-- Status bar --}}
    <div class="panel-surface">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-wrap gap-3">
                <span class="small"><span class="text-muted">Tarih:</span> <strong id="summaryRangeChip">Tum zamanlar</strong></span>
                <span class="small"><span class="text-muted">Urun:</span> <strong id="summaryProductMeta">Tum urunler</strong></span>
                <span class="small"><span class="text-muted">Sayfa:</span> <strong id="pageInfo">Hazir</strong></span>
                <span class="small"><span class="text-muted">Son:</span> <strong id="summaryUpdatedInline">Bekleniyor</strong></span>
            </div>
            <span class="report-chip" id="summaryQueryState">Araniyor...</span>
        </div>
    </div>

    {{-- Filter --}}
    <section class="panel-surface">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Filtre</h3>
            <span class="report-chip success">Canli rapor</span>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label">Tarih araligi</label>
                <select id="dateFilter" class="form-select" onchange="handleDateFilterChange()">
                    <option value="">Tumu</option>
                    <option value="gun">Bu Gun</option>
                    <option value="hafta">Bu Hafta</option>
                    <option value="ay">Bu Ay</option>
                    <option value="6ay">Son 6 Ay</option>
                    <option value="tarih">Ozel Tarih Araligi</option>
                </select>
            </div>

            <div class="col-xl-5 col-md-6" id="customDateRange" style="display: none;">
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Baslangic</label>
                        <input type="date" id="startDate" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Bitis</label>
                        <input type="date" id="endDate" class="form-control">
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-4">
                <label class="form-label">Urun (Nihai)</label>
                <select id="productSelect" class="form-select">
                    <option value="">Tum Urunler</option>
                </select>
            </div>

            <div class="col-xl-3 col-md-4">
                <label class="form-label">Personel</label>
                <select id="personnelSelect" class="form-select">
                    <option value="">Tümü</option>
                </select>
            </div>

            <div class="col-xl-1 col-md-2">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" onclick="loadReport()">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </section>

    {{-- Table --}}
    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">{{ $reportTableTitle ?? 'Gorev rapor tablosu' }}</h3>
            <span class="report-chip warning" id="summaryQueryStateBadge">Araniyor...</span>
        </div>

        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th class="sort-head sort-desc" data-sort="gr.No" onclick="sortReport('gr.No')">Gorev No</th>
                        <th class="sort-head" data-sort="UrunID" onclick="sortReport('UrunID')">Nihai Urun</th>
                        <th class="sort-head" data-sort="AraUrunAdi" onclick="sortReport('AraUrunAdi')">Ara Urun</th>
                        <th class="sort-head" data-sort="BolumAdi" onclick="sortReport('BolumAdi')">Bolum</th>
                        <th class="sort-head" data-sort="Personel" onclick="sortReport('Personel')">Personel</th>
                        <th class="sort-head" data-sort="GorevBaslamaTarihi" onclick="sortReport('GorevBaslamaTarihi')">Baslama</th>
                        <th class="sort-head" data-sort="GorevBitisTarihi" onclick="sortReport('GorevBitisTarihi')">Bitis</th>
                        <th class="sort-head" data-sort="ToplamAdet" onclick="sortReport('ToplamAdet')">Adet</th>
                        <th class="sort-head" data-sort="Performans" onclick="sortReport('Performans')">Performans</th>
                    </tr>
                </thead>
                <tbody id="reportBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Arama yapin...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="report-pager">
            <div class="text-muted small">Sayfalama rapor sonucuna gore otomatik guncellenir.</div>
            <div class="btn-group">
                <button class="btn btn-outline-secondary btn-sm" id="btnPrev" onclick="changePage(-1)">Onceki</button>
                <button class="btn btn-outline-secondary btn-sm" id="btnNext" onclick="changePage(1)">Sonraki</button>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
let currentPage = 1;
let currentLastPage = 1;
let currentSortCol = 'gr.No';
let currentSortDir = 'desc';

function handleDateFilterChange() {
    const value = document.getElementById('dateFilter').value;
    document.getElementById('customDateRange').style.display = value === 'tarih' ? 'block' : 'none';
    updateFilterSummary();
}

function loadProducts() {
    fetch('/api/database/products')
        .then((r) => r.json())
        .then((data) => {
            const select = document.getElementById('productSelect');
            select.innerHTML = '<option value="">Tum Urunler</option>';
            (data.data || []).forEach((product) => {
                select.innerHTML += `<option value="${product.id}">${escapeHtml(product.name)}</option>`;
            });
        });
}

function loadPersonnel() {
    fetch('/api/database/personnel')
        .then((r) => r.json())
        .then((data) => {
            const select = document.getElementById('personnelSelect');
            select.innerHTML = '<option value="">Tümü</option>';
            (data.data || [])
                .map((p) => {
                    const id = p.PersonelNo ?? p.id;
                    const name = [p.Ad ?? p.name, p.Soyad ?? p.surname]
                        .filter(Boolean)
                        .join(' ')
                        .trim();

                    return { id, name };
                })
                .filter((person) => person.id && person.name)
                .sort((a, b) => a.name.localeCompare(b.name, 'tr', { sensitivity: 'base' }))
                .forEach((person) => {
                    select.innerHTML += `<option value="${escapeHtml(person.id)}">${escapeHtml(person.name)}</option>`;
                });
        });
}

function loadReport(page = 1) {
    currentPage = page;
    let url = `/api/reports/tasks?page=${page}&per_page=20`;
    url += `&sort_by=${encodeURIComponent(currentSortCol)}&sort_dir=${encodeURIComponent(currentSortDir)}`;

    const dateFilter = document.getElementById('dateFilter').value;
    if (dateFilter) url += `&date_filter=${dateFilter}`;

    if (dateFilter === 'tarih') {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
    }

    const productId = document.getElementById('productSelect').value;
    if (productId) url += `&product_id=${productId}`;

    const personnelId = document.getElementById('personnelSelect').value;
    if (personnelId) url += `&personnel_id=${personnelId}`;

    document.getElementById('summaryQueryState').textContent = 'Sorgulaniyor';
    document.getElementById('summaryQueryState').className = 'report-chip warning';
    document.getElementById('summaryQueryStateBadge').textContent = 'Sorgulaniyor';
    document.getElementById('summaryQueryStateBadge').className = 'report-chip warning';
    document.getElementById('reportBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Yukleniyor...</td></tr>';

    fetch(url)
        .then((r) => r.json())
        .then((data) => {
            const rows = data.data || [];
            currentLastPage = Number(data.last_page || 1);

            if (!rows.length) {
                document.getElementById('reportBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Kayit bulunamadi.</td></tr>';
                document.getElementById('pageInfo').textContent = '';
                document.getElementById('btnPrev').disabled = true;
                document.getElementById('btnNext').disabled = true;
                updateSummary([], data);
                document.getElementById('summaryQueryState').textContent = 'Bos sonuc';
                document.getElementById('summaryQueryState').className = 'report-chip';
                document.getElementById('summaryQueryStateBadge').textContent = 'Bos sonuc';
                document.getElementById('summaryQueryStateBadge').className = 'report-chip';
                setReportStamp();
                return;
            }

            document.getElementById('reportBody').innerHTML = rows.map((row) => {
                const performance = parsePerformance(row.Performans);
                const perfHtml = performance === null
                    ? '-'
                    : `<span class="${performance >= 80 ? 'text-success' : performance >= 50 ? 'text-warning' : 'text-danger'} fw-bold">%${performance}</span>`;

                return `
                    <tr>
                        <td>${escapeHtml(row.No)}</td>
                        <td>${escapeHtml(row.UrunID || '-')}</td>
                        <td>${escapeHtml(row.AraUrunAdi || '-')}</td>
                        <td>${escapeHtml(row.BolumAdi || '-')}</td>
                        <td>${escapeHtml(row.TamAd || '-')}</td>
                        <td>${escapeHtml(row.GorevBaslamaTarihi || '-')}</td>
                        <td>${escapeHtml(row.GorevBitisTarihi || '-')}</td>
                        <td><strong class="text-success">${formatNumber(row.ToplamAdet || 0)}</strong></td>
                        <td>${perfHtml}</td>
                    </tr>
                `;
            }).join('');

            document.getElementById('pageInfo').textContent = `Sayfa ${data.current_page} / ${data.last_page} (Toplam: ${formatNumber(data.total || 0)})`;
            document.getElementById('btnPrev').disabled = data.current_page <= 1;
            document.getElementById('btnNext').disabled = data.current_page >= data.last_page;
            updateSummary(rows, data);
            document.getElementById('summaryQueryState').textContent = 'Rapor hazir';
            document.getElementById('summaryQueryState').className = 'report-chip success';
            document.getElementById('summaryQueryStateBadge').textContent = 'Rapor hazir';
            document.getElementById('summaryQueryStateBadge').className = 'report-chip success';
            setReportStamp();
        })
        .catch(() => {
            document.getElementById('reportBody').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Hata olustu.</td></tr>';
            document.getElementById('summaryQueryState').textContent = 'Hata';
            document.getElementById('summaryQueryState').className = 'report-chip danger';
            document.getElementById('summaryQueryStateBadge').textContent = 'Hata';
            document.getElementById('summaryQueryStateBadge').className = 'report-chip danger';
        });
}

function updateSummary(rows, data) {
    const totalAmount = rows.reduce((sum, row) => sum + Number(row.ToplamAdet || 0), 0);
    const performanceRows = rows
        .map((row) => parsePerformance(row.Performans))
        .filter((value) => value !== null);
    const averagePerformance = performanceRows.length
        ? Math.round(performanceRows.reduce((sum, value) => sum + value, 0) / performanceRows.length)
        : null;

    document.getElementById('summaryRecords').textContent = formatNumber(rows.length);
    document.getElementById('summaryAmount').textContent = formatNumber(totalAmount);
    document.getElementById('summaryPerformance').textContent = averagePerformance === null ? '-' : `%${averagePerformance}`;
    document.getElementById('summaryFilterMode').textContent = getFilterModeLabel();
    updateFilterSummary();

    if (data && data.current_page) {
        currentPage = Number(data.current_page);
    }
}

function updateFilterSummary() {
    const dateFilter = document.getElementById('dateFilter');
    const productSelect = document.getElementById('productSelect');
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    const dateValue = dateFilter.value;
    let rangeLabel = dateFilter.options[dateFilter.selectedIndex]?.text || 'Tumu';
    if (dateValue === 'tarih') {
        rangeLabel = [startDate || 'Baslangic', endDate || 'Bitis'].join(' - ');
    }

    document.getElementById('summaryRangeChip').textContent = rangeLabel || 'Tum zamanlar';
    document.getElementById('summaryProductMeta').textContent = productSelect.value
        ? productSelect.options[productSelect.selectedIndex].text
        : 'Tum urunler';
}

function getFilterModeLabel() {
    const dateFilter = document.getElementById('dateFilter').value;
    if (!dateFilter) return 'Tum';
    if (dateFilter === 'tarih') return 'Ozel';
    return (document.getElementById('dateFilter').options[document.getElementById('dateFilter').selectedIndex]?.text || 'Filtreli');
}

function changePage(delta) {
    const nextPage = currentPage + delta;
    if (nextPage < 1 || nextPage > currentLastPage) return;
    loadReport(nextPage);
}

function updateSortIndicators() {
    document.querySelectorAll('.sort-head').forEach((el) => el.classList.remove('sort-asc', 'sort-desc'));
    const active = document.querySelector(`.sort-head[data-sort="${currentSortCol}"]`);
    if (active) active.classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
}

function sortReport(column) {
    if (currentSortCol === column) {
        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSortCol = column;
        currentSortDir = 'asc';
    }

    updateSortIndicators();
    loadReport(1);
}

function resetReportFilters() {
    document.getElementById('dateFilter').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('productSelect').value = '';
    document.getElementById('personnelSelect').value = '';
    currentSortCol = 'gr.No';
    currentSortDir = 'desc';
    updateSortIndicators();
    handleDateFilterChange();
    loadReport(1);
}

function setReportStamp() {
    document.getElementById('summaryUpdatedInline').textContent = new Date().toLocaleTimeString('tr-TR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function parsePerformance(value) {
    if (value === null || value === undefined || value === '') return null;
    const parsed = parseInt(String(value).replace('%', ''), 10);
    return Number.isNaN(parsed) ? null : parsed;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadProducts();
    loadPersonnel();
    handleDateFilterChange();
    updateSortIndicators();
    loadReport();
});
</script>
@endpush
