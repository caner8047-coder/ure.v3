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
    .pool-qty-primary { font-weight: 800; color: var(--z-text); }
    .pool-qty-meta { margin-top: 3px; color: var(--z-text-muted); font-size: 0.66rem; font-weight: 700; line-height: 1.25; }
	    .group-header td { background: var(--z-bg-soft) !important; font-weight: 700; border-bottom: 1px dashed var(--z-border); }
	    .action-btn { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }
	    .sortable { cursor: pointer; }
	    .sortable.sort-asc::after { content: ' ↑'; }
	    .sortable.sort-desc::after { content: ' ↓'; }
	    .pool-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 20px; flex-wrap: wrap; }
	    .pool-bom-shell { padding: 0 16px 16px; }
	    .bom-flow-intro { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 0 10px; border-top: 1px solid var(--z-border-light); border-bottom: 1px solid var(--z-border-light); }
	    .bom-flow-intro h4 { font-size: 0.9rem; margin-bottom: 2px; }
	    .bom-flow-intro p { color: var(--z-text-secondary); font-size: 0.72rem; max-width: 680px; }
	    .bom-flow-legend { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
	    .bom-legend-chip { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--z-border); border-radius: 999px; padding: 3px 7px; font-size: 0.66rem; font-weight: 700; color: var(--z-text-secondary); background: #fff; white-space: nowrap; }
	    .bom-legend-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--z-text-muted); }
	    .bom-legend-dot.ready { background: #059669; }
	    .bom-legend-dot.partial { background: #0284c7; }
	    .bom-legend-dot.waiting { background: #d97706; }
	    .bom-legend-dot.stock { background: #4f46e5; }
	    .bom-legend-dot.reserved { background: #7c3aed; }
	    .bom-legend-dot.missing { background: #ef4444; }
	    .bom-flow-group { padding: 12px 0 14px; border-bottom: 1px solid var(--z-border-light); }
	    .bom-flow-group:last-child { border-bottom: 0; padding-bottom: 0; }
	    .bom-flow-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
	    .bom-flow-title { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
	    .bom-flow-title h4 { font-size: 0.9rem; overflow-wrap: anywhere; }
	    .bom-flow-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; color: var(--z-text-secondary); font-size: 0.7rem; }
	    .bom-flow-stats { display: flex; align-items: center; justify-content: flex-end; gap: 6px; flex-wrap: wrap; }
	    .bom-flow-stat { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--z-border); border-radius: 7px; padding: 4px 7px; background: #fff; font-size: 0.68rem; font-weight: 700; color: var(--z-text-secondary); white-space: nowrap; }
	    .bom-flow-diagram { display: flex; align-items: stretch; gap: 8px; overflow-x: auto; padding: 3px 0 6px; }
	    .bom-flow-layer { min-width: 170px; max-width: 178px; display: flex; flex-direction: column; gap: 6px; }
	    .bom-flow-layer-label { color: var(--z-text-muted); font-size: 0.62rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
	    .bom-flow-connector { display: flex; align-items: center; justify-content: center; color: var(--z-text-muted); min-width: 14px; padding-top: 22px; opacity: 0.7; }
	    .bom-flow-node { min-height: 82px; border: 1px solid var(--z-border); border-left: 3px solid var(--z-text-muted); border-radius: 7px; background: #fff; padding: 7px 8px; display: flex; flex-direction: column; gap: 4px; box-shadow: none; }
	    .bom-flow-node.status-ready { border-left-color: #059669; background: linear-gradient(90deg, rgba(5,150,105,0.09), #fff 44%); }
	    .bom-flow-node.status-partial { border-left-color: #0284c7; background: linear-gradient(90deg, rgba(2,132,199,0.09), #fff 44%); }
	    .bom-flow-node.status-waiting { border-left-color: #d97706; background: linear-gradient(90deg, rgba(217,119,6,0.09), #fff 44%); }
	    .bom-flow-node.status-stock { border-left-color: #4f46e5; background: linear-gradient(90deg, rgba(79,70,229,0.08), #fff 44%); }
	    .bom-flow-node.status-reserved { border-left-color: #7c3aed; background: linear-gradient(90deg, rgba(124,58,237,0.08), #fff 44%); }
	    .bom-flow-node.status-missing { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.08), #fff 44%); }
	    .bom-flow-node.status-idle { border-left-color: #94a3b8; }
	    .bom-node-top { display: flex; align-items: center; gap: 5px; min-width: 0; }
	    .bom-node-status { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 2px 6px; font-size: 0.6rem; font-weight: 800; white-space: nowrap; }
	    .bom-node-status.status-ready { color: #047857; background: rgba(5,150,105,0.12); }
	    .bom-node-status.status-partial { color: #0369a1; background: rgba(2,132,199,0.12); }
	    .bom-node-status.status-waiting { color: #9a3412; background: rgba(217,119,6,0.14); }
	    .bom-node-status.status-stock { color: #4338ca; background: rgba(79,70,229,0.11); }
	    .bom-node-status.status-reserved { color: #6d28d9; background: rgba(124,58,237,0.11); }
	    .bom-node-status.status-missing { color: #b91c1c; background: rgba(239,68,68,0.1); }
	    .bom-node-status.status-idle { color: #64748b; background: #f1f5f9; }
	    .bom-node-qty { color: var(--z-text-muted); font-size: 0.64rem; font-weight: 800; white-space: nowrap; margin-left: auto; }
	    .bom-node-name { font-size: 0.76rem; font-weight: 800; color: var(--z-text); line-height: 1.18; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow-wrap: anywhere; }
	    .bom-node-sub { color: var(--z-text-secondary); font-size: 0.64rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	    .bom-node-metrics { display: flex; gap: 4px; flex-wrap: wrap; margin-top: auto; }
	    .bom-node-metric { border: 1px solid var(--z-border-light); border-radius: 5px; background: var(--z-bg-input); padding: 2px 5px; font-size: 0.62rem; font-weight: 700; color: var(--z-text-secondary); white-space: nowrap; }
	    .bom-node-action { width: 22px; height: 22px; min-height: 22px; border: 1px solid rgba(13,148,136,0.3); border-radius: 6px; background: var(--z-accent-soft); color: var(--z-accent-hover); font-size: 0.7rem; display: inline-flex; align-items: center; justify-content: center; margin-left: auto; flex: 0 0 22px; }
	    .bom-node-action span { display: none; }
	    .bom-flow-empty { padding: 30px 16px; text-align: center; color: var(--z-text-secondary); }
	    .bom-flow-loading { padding: 28px 16px; display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--z-text-secondary); font-weight: 700; }
	    @media (max-width: 900px) {
	        .bom-flow-intro, .bom-flow-head { flex-direction: column; align-items: stretch; }
	        .bom-flow-legend, .bom-flow-stats { justify-content: flex-start; }
	    }
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
	            <p class="metric-label">Atanabilir Adet</p>
	            <h3 class="metric-value" id="summaryAdet">0</h3>
	        </article>
	        <article class="metric-card">
	            <p class="metric-label">Üretilecek Net</p>
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
	                    <option value="bom">BOM Akışı</option>
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
        <div class="pool-control-actions align-items-center mt-4 pt-2" style="border-top: 1px dashed var(--z-border);">
            <button class="btn btn-outline-danger btn-sm" type="button" onclick="tumunuSil()" title="Temizle">
                <i class="bi bi-trash"></i>
            </button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="loadHavuz()" title="Yenile">
                <i class="bi bi-arrow-repeat"></i>
            </button>
            <span class="soft-badge" id="totalBadge" style="margin-right: 8px;">0 kayıt</span>

            <div class="vr mx-2 d-none d-lg-block" style="opacity: 0.15"></div>

            <span class="text-muted" style="font-size: 0.8rem; font-weight: 500;"><i class="bi bi-filter"></i> Etiket:</span>

            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="smartFilter" id="sfAll" value="all" autocomplete="off" checked onchange="applyFiltersAndRender()">
                <label class="btn btn-outline-secondary btn-sm px-3" for="sfAll">Tümü</label>

                <input type="radio" class="btn-check" name="smartFilter" id="sfReady" value="ready" autocomplete="off" onchange="applyFiltersAndRender()">
                <label class="btn btn-outline-secondary btn-sm px-3" for="sfReady">Hazır</label>

                <input type="radio" class="btn-check" name="smartFilter" id="sfPartial" value="partial" autocomplete="off" onchange="applyFiltersAndRender()">
                <label class="btn btn-outline-secondary btn-sm px-3" for="sfPartial">Kısmi</label>

                <input type="radio" class="btn-check" name="smartFilter" id="sfWaiting" value="waiting" autocomplete="off" onchange="applyFiltersAndRender()">
                <label class="btn btn-outline-secondary btn-sm px-3" for="sfWaiting">Bekleyen</label>

                <input type="radio" class="btn-check" name="smartFilter" id="sfLate" value="late" autocomplete="off" onchange="applyFiltersAndRender()">
                <label class="btn btn-outline-secondary btn-sm px-3" for="sfLate">Gecikmiş</label>
            </div>

            <span class="text-muted small ms-auto" style="font-size: 0.75rem;">Son: <strong id="summaryLastRefresh">—</strong></span>
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

	        <div id="bomFlowShell" class="pool-bom-shell d-none"></div>

	        <div class="table-shell" id="poolTableShell">
            <table class="table-modern pool-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="id" onclick="sortColumn('id')">No</th>
                        <th class="sortable" data-sort="product_name" onclick="sortColumn('product_name')">Ürün ID</th>
                        <th class="sortable" data-sort="component_name" onclick="sortColumn('component_name')">Ara Ürün</th>
                        <th class="sortable" data-sort="gorev_tarihi" onclick="sortColumn('gorev_tarihi')">Tarih</th>
                        <th class="sortable" data-sort="gorev_saati" onclick="sortColumn('gorev_saati')">Saat</th>
	                        <th class="sortable" data-sort="adet" onclick="sortColumn('adet')">Atanabilir</th>
	                        <th class="sortable" data-sort="toplam_adet" onclick="sortColumn('toplam_adet')">Üretilecek Net</th>
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

	        <div class="pool-pager" id="poolPager">
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

    {{-- Hızlı Görev Ata Modalı --}}
    <div class="modal fade" id="assignTaskModal" tabindex="-1" aria-labelledby="assignTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignTaskModalLabel">Hızlı Görev Ata</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
	                <div class="modal-body">
	                    <input type="hidden" id="assignPoolId" value="">

	                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Seçili Görev</label>
	                        <div id="assignTaskInfo" class="fw-bold" style="font-size: 0.95rem;">-</div>
	                    </div>

		                    <div class="mb-3">
	                        <label for="assignPersonnel" class="form-label">Görevlendirilecek Personel</label>
	                        <select class="form-select" id="assignPersonnel">
	                            <option value="">Yükleniyor...</option>
	                        </select>
		                        <div class="form-text" id="assignPersonnelHelp">Bu görev yalnızca kendi bölümündeki personele atanabilir.</div>
		                    </div>

		                    <div class="mb-3">
	                        <label for="assignDate" class="form-label">Görev Tarihi</label>
	                        <input type="date" class="form-control" id="assignDate">
		                    </div>

		                    <div class="mb-3">
	                        <label for="assignAmount" class="form-label">Atanacak Adet</label>
	                        <input type="number" class="form-control" id="assignAmount" min="1" value="1">
                        <div class="form-text" id="assignAmountInfo">Maksimum atanabilir: -</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary" onclick="submitAssignTask()">Görevi Ata</button>
                </div>
            </div>
        </div>
    </div>
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
	let isModalOpen = false;
	let assignPersonnel = [];
	let bomCache = new Map();
	let bomRenderToken = 0;
	let stockByComponent = new Map();
	let stockCacheLoaded = false;

const viewModeLabels = {
    detayli: 'Detaylı',
    detaysiz: 'Detaysız',
    ozet: 'Özet',
	    tamozet: 'Tam Özet',
	    bolum: 'Bölüm',
	    urunID: 'Ürün ID',
	    araUrun: 'Ara Ürün',
	    bom: 'BOM Akışı',
	};

function debounceLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadHavuz, 300);
}

function setRefreshStamp(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('summaryLastRefresh').textContent = label;
}

function todayIso() {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 10);
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
	    currentPage = 1;
	    loadHavuz();
	}

function loadHavuz(silent = false) {
    if (!silent) {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">Yükleniyor...</td></tr>';
    }
    fetch('/api/database/pool-tasks', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
	            allRows = (data.data || []).map((row) => {
	                row.gorev_tarihi = row.gorev_tarihi || '';
	                row.gorev_saati = row.gorev_saati || '';
	                return row;
	            });
	            stockCacheLoaded = false;
	            setRefreshStamp();
	            applyFiltersAndRender();
        })
        .catch(() => {
            setRefreshStamp('Veri alınamadı');
            if (!silent) {
                document.getElementById('tableBody').innerHTML = '<tr><td colspan="10" class="text-center py-3 text-danger">Veri yüklenemedi.</td></tr>';
            }
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

    // Akıllı filtreler
    const smartFilterNode = document.querySelector('input[name="smartFilter"]:checked');
    const smartFilter = smartFilterNode ? smartFilterNode.value : 'all';

    if (smartFilter !== 'all') {
	        rows = rows.filter((row) => {
	            const adet = parseFloat(row.adet) || 0;
	            const toplam = parseFloat(row.toplam_adet) || 0;

	            if (smartFilter === 'ready') return adet > 0 && adet === toplam;
            if (smartFilter === 'partial') return adet > 0 && adet < toplam;
            if (smartFilter === 'waiting') return adet === 0 && toplam > 0;
            if (smartFilter === 'late') {
                if (!row.gorev_tarihi) return false;
                const parts = row.gorev_tarihi.split('/');
                if (parts.length === 3) {
                    const rowDate = new Date(parts[2], parts[1] - 1, parts[0]);
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    return rowDate < today;
                }
                return false;
            }
            return true;
        });
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

		    setBomMode(viewMode === 'bom');
	    if (viewMode === 'bom') { renderBomView(); return; }
	    if (viewMode === 'tamozet') { renderTamOzet(); return; }
	    if (['bolum', 'araUrun', 'urunID'].includes(viewMode) && !deptFilter && !araUrunFilter && !urunIDFilter) {
	        const groupKey = viewMode === 'bolum' ? 'department_name' : viewMode === 'urunID' ? 'product_name' : 'component_name';
        renderGrouped(groupKey);
        return;
    }
	    renderFlat();
	}

	function setBomMode(enabled) {
	    document.getElementById('bomFlowShell').classList.toggle('d-none', !enabled);
	    document.getElementById('poolTableShell').classList.toggle('d-none', enabled);
	    document.getElementById('poolPager').classList.toggle('d-none', enabled);
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
	    const adet = parseFloat(row.adet) || 0;
	    const toplam = parseFloat(row.toplam_adet) || 0;
	    const childReserved = Number(row.alt_stoktan_ayrilan_adet || 0);
	    const waitsForChildren = !!row.alt_gorev_bekliyor;

	    // Status Badge Logic
    let statusHtml = '';
    let rowClass = '';
	    if (adet === 0 && toplam > 0) {
	        statusHtml = '<span class="badge bg-warning text-dark mt-1 d-block" style="font-size:0.7rem;">' + (waitsForChildren ? 'Alt Görev Bekliyor' : 'Parça Bekliyor') + '</span>';
	        rowClass = 'table-warning';
	    } else if (adet > 0 && adet === toplam) {
	        statusHtml = '<span class="badge bg-success mt-1 d-block" style="font-size:0.7rem;">Atamaya Hazır</span>';
		    } else if (adet > 0 && adet < toplam) {
		        statusHtml = '<span class="badge bg-info mt-1 d-block" style="font-size:0.7rem;">Kısmi Atanabilir</span>';
		    }

	    // Date Logic
    let dateHtml = escHtml(row.gorev_tarihi || '-');
    if (row.gorev_tarihi) {
        const parts = row.gorev_tarihi.split('/');
        if (parts.length === 3) {
            const rowDate = new Date(parts[2], parts[1] - 1, parts[0]);
            const today = new Date();
            today.setHours(0,0,0,0);
            if (rowDate < today) {
                dateHtml += ' <i class="bi bi-exclamation-circle text-danger" title="Gecikmiş İş"></i>';
            }
        }
    }

    // Sipariş Link
    let siparisHtml = '';
    if (row.siparis_satir_no && row.siparis_satir_no > 0) {
        siparisHtml = ` <a href="/is-emri-merkezi?siparis=${row.siparis_satir_no}" class="badge bg-primary text-decoration-none" title="Siparişe Git"><i class="bi bi-box-seam"></i> SIP-${row.siparis_satir_no}</a>`;
    }

        const reserved = Number(row.stoktan_ayrilan_adet || 0);
        const requested = Number(row.bom_ihtiyac_adet || row.requested_adet || toplam);
        const netDetails = [];
        if (reserved > 0 || requested !== toplam) {
            netDetails.push(`BOM ${requested.toLocaleString('tr-TR')}`);
            netDetails.push(`Boş stoktan düşen ${reserved.toLocaleString('tr-TR')}`);
        }
        if (waitsForChildren) {
            netDetails.push('Atanır, alt görevleri bekler');
        } else if (childReserved > 0) {
            netDetails.push(`Alt parça stokta/tamponda ${childReserved.toLocaleString('tr-TR')}`);
        }
        const netDetailHtml = netDetails.length ? `<div class="pool-qty-meta">${netDetails.join(' · ')}</div>` : '';

	    return `<tr data-id="${row.id}" class="${rowClass}">
	        <td>${row.id}</td>
	        <td>${escHtml(row.product_name || '-')}${siparisHtml}</td>
	        <td><span class="badge bg-secondary">${escHtml(row.component_name || 'Bilinmiyor')}</span></td>
	        <td>${dateHtml}</td>
	        <td>${escHtml(row.gorev_saati || '-')}</td>
	        <td><span class="pool-qty-primary">${adet.toLocaleString('tr-TR')}</span>${statusHtml}</td>
	        <td><span class="pool-qty-primary">${toplam.toLocaleString('tr-TR')}</span>${netDetailHtml}</td>
        <td>${escHtml(row.aciklama || '')}</td>
        <td>${escHtml(row.department_name || 'Tanımsız')}</td>
        <td>
            <div class="d-flex gap-1">
                <button class="btn btn-outline-primary action-btn btn-sm" title="Görev Ata" onclick="openAssignModal(${Number(row.id)}, ${jsArg(row.component_name || row.product_name || 'Görev')}, ${Math.floor(toplam)}, ${Number(row.department_id || 0)}, ${jsArg(row.department_name || '')})" ${toplam <= 0 ? 'disabled' : ''}>
                    <i class="bi bi-person-plus"></i>
                </button>
                <button class="btn btn-outline-secondary action-btn btn-sm" title="Düzenle" onclick="editRow(${row.id}, ${adet}, ${toplam})">
                    <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-outline-danger action-btn btn-sm" title="Sil" onclick="deleteRow(${row.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
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

	function renderBomPager(groupCount, rowCount) {
	    const label = groupCount > 0
	        ? `${groupCount.toLocaleString('tr-TR')} BOM / ${rowCount.toLocaleString('tr-TR')} kayıt`
	        : 'BOM kaydı yok';
	    document.getElementById('pagerInfo').textContent = label;
	    document.getElementById('pageNumbers').innerHTML = '';
	    document.getElementById('btnPrev').disabled = true;
	    document.getElementById('btnNext').disabled = true;
	}

	async function renderBomView() {
	    const token = ++bomRenderToken;
	    const shell = document.getElementById('bomFlowShell');
	    const groups = buildBomGroups(filteredRows);
	    renderBomPager(groups.length, filteredRows.length);

	    if (!groups.length) {
	        shell.innerHTML = '<div class="bom-flow-empty"><i class="bi bi-diagram-3 me-1"></i>Filtreye uygun BOM akışı bulunamadı.</div>';
	        return;
	    }

	    shell.innerHTML = '<div class="bom-flow-loading"><i class="bi bi-arrow-repeat"></i>BOM akışı hazırlanıyor...</div>';

	    try {
	        await ensureStockCache();
	        const bomResults = await Promise.all(groups.map((group) => loadBomForGroup(group)));
	        if (token !== bomRenderToken) return;

	        shell.innerHTML = `
	            <div class="bom-flow-intro">
	                <div>
	                    <h4><i class="bi bi-diagram-3 me-1"></i>BOM Akışı</h4>
	                    <p>Filtrelenmiş havuz kayıtları ürün ağacına yerleştirildi; renkler üretilebilirlik, stok ve görev verilebilirliği gösterir.</p>
	                </div>
	                <div class="bom-flow-legend">
	                    <span class="bom-legend-chip"><span class="bom-legend-dot ready"></span>Hazır</span>
	                    <span class="bom-legend-chip"><span class="bom-legend-dot partial"></span>Kısmi</span>
	                    <span class="bom-legend-chip"><span class="bom-legend-dot waiting"></span>Parça bekliyor</span>
	                    <span class="bom-legend-chip"><span class="bom-legend-dot stock"></span>Stokta</span>
	                    <span class="bom-legend-chip"><span class="bom-legend-dot reserved"></span>Stok ayrıldı</span>
	                    <span class="bom-legend-chip"><span class="bom-legend-dot missing"></span>Eksik</span>
	                </div>
	            </div>
	            ${groups.map((group, index) => renderBomGroup(group, bomResults[index])).join('')}
	        `;
	    } catch (error) {
	        if (token !== bomRenderToken) return;
	        shell.innerHTML = '<div class="bom-flow-empty text-danger"><i class="bi bi-exclamation-triangle me-1"></i>BOM görünümü hazırlanamadı.</div>';
	    }
	}

	function buildBomGroups(rows) {
	    const groups = new Map();
	    rows.forEach((row) => {
	        const key = bomGroupKey(row);
	        const siparisNo = Number(row.siparis_satir_no || 0);
	        const productNo = Number(row.urun_id_no || 0);
	        if (!groups.has(key)) {
	            groups.set(key, {
	                key,
	                productNo,
	                siparisNo,
	                productName: row.product_name || 'Ürün tanımsız',
	                rows: [],
	            });
	        }
	        groups.get(key).rows.push(row);
	    });

	    const result = Array.from(groups.values());
	    result.forEach((group) => {
	        group.statusRows = allRows.filter((row) => bomGroupKey(row) === group.key);
	        if (!group.statusRows.length) group.statusRows = group.rows;
	    });

	    return result.sort((a, b) => {
	        const byName = String(a.productName).localeCompare(String(b.productName), 'tr');
	        if (byName !== 0) return byName;
	        return Number(a.siparisNo || 0) - Number(b.siparisNo || 0);
	    });
	}

	function bomGroupKey(row) {
	    const siparisNo = Number(row.siparis_satir_no || 0);
	    const productNo = Number(row.urun_id_no || 0);
	    return siparisNo > 0 ? `sip-${siparisNo}` : `urun-${productNo || row.product_name || row.id}`;
	}

	function normalizeBomName(value) {
	    return String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('tr-TR');
	}

	async function ensureStockCache() {
	    if (stockCacheLoaded) return;
	    let data = { data: [] };
	    try {
	        const response = await fetch('/api/stocks?per_page=100000', { headers: { Accept: 'application/json' }, cache: 'no-store' });
	        data = await response.json();
	    } catch (error) {
	        stockByComponent = new Map();
	        stockCacheLoaded = true;
	        return;
	    }
	    const nextMap = new Map();
	    (data.data || []).forEach((row) => {
	        const componentId = Number(row.AraUrunAdiNo || 0);
	        if (!componentId) return;
	        const total = Number(row.Adet || 0);
	        const free = Math.max(0, Math.min(total, Number(row.TamponMiktar || 0)));
	        const existing = nextMap.get(componentId) || {
	            total: 0,
	            free: 0,
	            reserved: 0,
	            departmentName: row.BolumAdi || '',
	            type: row.UrunCesidi || '',
	        };
	        existing.total += total;
	        existing.free += free;
	        existing.reserved += Math.max(0, total - free);
	        if (!existing.departmentName && row.BolumAdi) existing.departmentName = row.BolumAdi;
	        if (!existing.type && row.UrunCesidi) existing.type = row.UrunCesidi;
	        nextMap.set(componentId, existing);
	    });
	    stockByComponent = nextMap;
	    stockCacheLoaded = true;
	}

	async function loadBomForGroup(group) {
	    const candidates = [];
	    const sourceRows = group.statusRows || group.rows;
	    const componentIds = [...new Set(sourceRows.map((row) => Number(row.ara_urun_no || 0)).filter(Boolean))];
	    const rootLike = sourceRows.find((row) => {
	        const productName = normalizeBomName(row.product_name);
	        const componentName = normalizeBomName(row.component_name);
	        return productName && componentName && productName === componentName;
	    });

	    // Havuz satırları tbAraUrun zincirine bağlıdır. Kök ara ürün varsa, ürün
	    // tablosundaki eski AraAdlarYol yerine aynı zinciri kullanmak gerekir.
	    if (rootLike && Number(rootLike.ara_urun_no || 0) > 0) {
	        candidates.push({ key: `component:${Number(rootLike.ara_urun_no)}`, url: `/api/database/components/${Number(rootLike.ara_urun_no)}/bom-path-names` });
	    }
	    componentIds.slice(0, 4).forEach((componentId) => {
	        candidates.push({ key: `component:${componentId}`, url: `/api/database/components/${componentId}/bom-path-names` });
	    });
	    if (group.productNo > 0) {
	        candidates.push({ key: `product:${group.productNo}`, url: `/api/database/products/${group.productNo}/bom-path-names` });
	    }

	    const seen = new Set();
	    const matches = [];
	    for (const candidate of candidates) {
	        if (seen.has(candidate.key)) continue;
	        seen.add(candidate.key);
	        if (bomCache.has(candidate.key)) {
	            const cached = bomCache.get(candidate.key);
	            if (cached && Array.isArray(cached.edges) && cached.edges.length > 0) {
	                matches.push({ data: cached, score: scoreBomDataForGroup(cached, sourceRows) });
	            }
	            continue;
	        }
	        try {
	            const response = await fetch(candidate.url, { headers: { Accept: 'application/json' } });
	            const data = await response.json();
	            bomCache.set(candidate.key, data);
	            if (data.success && Array.isArray(data.edges) && data.edges.length > 0) {
	                matches.push({ data, score: scoreBomDataForGroup(data, sourceRows) });
	            }
	        } catch (error) {
	            bomCache.set(candidate.key, null);
	        }
	    }

	    if (!matches.length) return null;
	    matches.sort((a, b) => b.score - a.score);
	    return matches[0].data;
	}

	function scoreBomDataForGroup(bomData, rows) {
	    const nodeIds = new Set();
	    (bomData.edges || []).forEach((edge) => {
	        const sourceId = Number(edge.source_id || 0);
	        const targetId = Number(edge.target_id || 0);
	        if (sourceId) nodeIds.add(sourceId);
	        if (targetId) nodeIds.add(targetId);
	    });

	    let matchedRows = 0;
	    const rowComponentIds = [...new Set(rows.map((row) => Number(row.ara_urun_no || 0)).filter(Boolean))];
	    rowComponentIds.forEach((componentId) => {
	        if (nodeIds.has(componentId)) matchedRows += 1;
	    });

	    const rootRows = rows.filter((row) => {
	        const productName = normalizeBomName(row.product_name);
	        const componentName = normalizeBomName(row.component_name);
	        return productName && componentName && productName === componentName;
	    });
	    const rootMatched = rootRows.some((row) => nodeIds.has(Number(row.ara_urun_no || 0))) ? 1 : 0;

	    return (matchedRows * 1000) + (rootMatched * 100) + Math.min(Number(bomData.edges?.length || 0), 99);
	}

	function renderBomGroup(group, bomData) {
	    const statusRows = group.statusRows || group.rows;
	    const summary = summarizeBomGroup(statusRows);
	    const model = buildBomModel(group, bomData);
	    const flowHtml = model.levels.length
	        ? model.levels.map((nodes, index) => `
	            ${index > 0 ? '<div class="bom-flow-connector"><i class="bi bi-arrow-right"></i></div>' : ''}
	            <div class="bom-flow-layer">
	                <div class="bom-flow-layer-label">${index === model.levels.length - 1 ? 'Nihai' : `Seviye ${model.levels.length - index - 1}`}</div>
	                ${nodes.map((node) => renderBomNode(node, statusRows, model.edges)).join('')}
	            </div>
	        `).join('')
	        : '<div class="bom-flow-empty">Bu ürün için çizilecek BOM düğümü bulunamadı.</div>';

	    return `
	        <section class="bom-flow-group">
	            <div class="bom-flow-head">
	                <div class="bom-flow-title">
	                    <h4>${escHtml(group.productName)}</h4>
	                    <div class="bom-flow-meta">
	                        ${group.siparisNo > 0 ? `<span><i class="bi bi-box-seam"></i> SIP-${group.siparisNo}</span>` : ''}
	                        <span><i class="bi bi-list-task"></i> ${statusRows.length.toLocaleString('tr-TR')} havuz satırı</span>
	                        ${model.edgeCount > 0 ? `<span><i class="bi bi-diagram-2"></i> ${model.edgeCount.toLocaleString('tr-TR')} bağlantı</span>` : '<span><i class="bi bi-diagram-2"></i> BOM yolu yok</span>'}
	                    </div>
	                </div>
	                <div class="bom-flow-stats">
		                    <span class="bom-flow-stat"><i class="bi bi-check2-circle"></i> Atanabilir ${summary.ready.toLocaleString('tr-TR')}</span>
	                    <span class="bom-flow-stat"><i class="bi bi-hourglass-split"></i> Bekleyen ${summary.waiting.toLocaleString('tr-TR')}</span>
	                    <span class="bom-flow-stat"><i class="bi bi-person-plus"></i> Atanabilir ${summary.assignableRows.toLocaleString('tr-TR')}</span>
	                </div>
	            </div>
	            <div class="bom-flow-diagram">${flowHtml}</div>
	        </section>
	    `;
	}

	function summarizeBomGroup(rows) {
	    const ready = rows.reduce((sum, row) => sum + (Number(row.adet) || 0), 0);
	    const total = rows.reduce((sum, row) => sum + (Number(row.toplam_adet) || 0), 0);
	    return {
	        ready,
	        waiting: Math.max(0, total - ready),
	        assignableRows: rows.filter((row) => (Number(row.toplam_adet) || 0) > 0).length,
	    };
	}

	function buildBomModel(group, bomData) {
	    const edges = Array.isArray(bomData?.edges) ? bomData.edges : [];
	    const nodes = new Map();
	    const ensureNode = (id, name, meta = {}) => {
	        const nodeId = Number(id || 0);
	        if (!nodeId) return null;
	        if (!nodes.has(nodeId)) {
	            nodes.set(nodeId, {
	                id: nodeId,
	                name: name || `#${nodeId}`,
	                departmentName: meta.departmentName || '',
	                type: meta.type || '',
	                sources: [],
	                targets: [],
	                level: undefined,
	            });
	        } else if (name && String(nodes.get(nodeId).name).startsWith('#')) {
	            nodes.get(nodeId).name = name;
	        }
	        if (meta.departmentName && !nodes.get(nodeId).departmentName) nodes.get(nodeId).departmentName = meta.departmentName;
	        if (meta.type && !nodes.get(nodeId).type) nodes.get(nodeId).type = meta.type;
	        return nodes.get(nodeId);
	    };

	    edges.forEach((edge) => {
	        const source = ensureNode(edge.source_id, edge.source_name, {
	            departmentName: edge.source_department_name || '',
	            type: edge.source_type || '',
	        });
	        const target = ensureNode(edge.target_id, edge.target_name, {
	            departmentName: edge.target_department_name || '',
	            type: edge.target_type || '',
	        });
	        if (!source || !target) return;
	        source.targets.push(target.id);
	        target.sources.push(source.id);
	    });

	    if (!nodes.size) {
	        const fallback = new Map();
	        (group.statusRows || group.rows).forEach((row) => {
	            const id = Number(row.ara_urun_no || 0);
	            if (!id || fallback.has(id)) return;
	            fallback.set(id, {
	                id,
	                name: row.component_name || `#${id}`,
	                departmentName: row.department_name || '',
	                type: '',
	                sources: [],
	                targets: [],
	                level: 0,
	            });
	        });
	        return { levels: [Array.from(fallback.values()).sort(sortBomNodes)], edges: [], edgeCount: 0 };
	    }

	    const visibleNodeIds = selectBomFlowNodeIds(nodes, group.statusRows || group.rows);
	    if (visibleNodeIds.size) {
	        Array.from(nodes.keys()).forEach((id) => {
	            if (!visibleNodeIds.has(id)) nodes.delete(id);
	        });
	        nodes.forEach((node) => {
	            node.sources = node.sources.filter((id) => visibleNodeIds.has(id));
	            node.targets = node.targets.filter((id) => visibleNodeIds.has(id));
	        });
	    }

	    const roots = Array.from(nodes.values()).filter((node) => node.targets.length === 0);
	    const queue = (roots.length ? roots : [nodes.values().next().value]).map((node) => ({ id: node.id, level: 0 }));
	    while (queue.length) {
	        const current = queue.shift();
	        const node = nodes.get(current.id);
	        if (!node) continue;
	        if (node.level !== undefined && node.level >= current.level) continue;
	        node.level = current.level;
	        node.sources.forEach((sourceId) => queue.push({ id: sourceId, level: current.level + 1 }));
	    }

	    nodes.forEach((node) => {
	        if (node.level === undefined) node.level = 0;
	    });

	    const maxLevel = Math.max(...Array.from(nodes.values()).map((node) => node.level || 0));
	    const levels = [];
	    for (let level = maxLevel; level >= 0; level--) {
	        levels.push(Array.from(nodes.values()).filter((node) => node.level === level).sort(sortBomNodes));
	    }

	    const visibleEdges = edges.filter((edge) => nodes.has(Number(edge.source_id || 0)) && nodes.has(Number(edge.target_id || 0)));
	    return { levels, edges: visibleEdges, edgeCount: visibleEdges.length };
	}

	function selectBomFlowNodeIds(nodes, rows) {
	    const visible = new Set();

	    const addAncestors = (nodeId) => {
	        const node = nodes.get(Number(nodeId || 0));
	        if (!node || visible.has(node.id)) return;
	        visible.add(node.id);
	        node.targets.forEach(addAncestors);
	    };

	    nodes.forEach((node) => {
	        const status = getBomNodeStatus(node, rows);
	        if (status.total > 0) addAncestors(node.id);
	    });

	    nodes.forEach((node) => {
	        const status = getBomNodeStatus(node, rows);
	        if (!visible.has(node.id) || status.total <= 0) return;
	        if (!['waiting', 'partial'].includes(status.key)) return;
	        node.sources.forEach((sourceId) => visible.add(sourceId));
	    });

	    return visible.size ? visible : new Set(nodes.keys());
	}

	function sortBomNodes(a, b) {
	    const aScore = getNodePoolRows(a.id, filteredRows).length > 0 ? 0 : 1;
	    const bScore = getNodePoolRows(b.id, filteredRows).length > 0 ? 0 : 1;
	    if (aScore !== bScore) return aScore - bScore;
	    return String(a.name).localeCompare(String(b.name), 'tr');
	}

	function renderBomNode(node, rows, edges) {
	    const status = getBomNodeStatus(node, rows);
	    const qty = getNodeQuantityLabel(node.id, edges);
	    const stockText = status.stockTotal > 0
	        ? `Stok ${status.stockFree.toLocaleString('tr-TR')}/${status.stockTotal.toLocaleString('tr-TR')}`
	        : '';
	    const readyText = status.total > 0
	        ? `Ata ${status.ready.toLocaleString('tr-TR')}/${status.total.toLocaleString('tr-TR')}`
	        : '';
	    const actionHtml = status.assignRow
	        ? `<button type="button" class="bom-node-action" title="Görev ver" onclick="openAssignModal(${Number(status.assignRow.id)}, ${jsArg(status.assignRow.component_name || node.name)}, ${Math.floor(Number(status.assignRow.toplam_adet) || 0)}, ${Number(status.assignRow.department_id || 0)}, ${jsArg(status.assignRow.department_name || '')})"><i class="bi bi-person-plus"></i><span>Görev ver</span></button>`
	        : '';
	    const metricHtml = [readyText, stockText]
	        .filter(Boolean)
	        .map((text) => `<span class="bom-node-metric">${escHtml(text)}</span>`)
	        .join('');

	    return `
	        <article class="bom-flow-node status-${status.key}" title="${escHtml(node.name)}">
	            <div class="bom-node-top">
	                <span class="bom-node-status status-${status.key}"><i class="bi ${status.icon}"></i>${escHtml(status.label)}</span>
	                ${actionHtml}
	                <span class="bom-node-qty">${escHtml(qty)}</span>
	            </div>
	            <div class="bom-node-name">${escHtml(node.name)}</div>
	            <div class="bom-node-sub">${escHtml(status.departmentLabel)}</div>
	            ${metricHtml ? `<div class="bom-node-metrics">${metricHtml}</div>` : ''}
	        </article>
	    `;
	}

	function getBomNodeStatus(node, rows) {
	    const poolRows = getNodePoolRows(node.id, rows);
	    const ready = poolRows.reduce((sum, row) => sum + (Number(row.adet) || 0), 0);
	    const total = poolRows.reduce((sum, row) => sum + (Number(row.toplam_adet) || 0), 0);
	    const stock = stockByComponent.get(Number(node.id)) || { total: 0, free: 0, reserved: 0, departmentName: '', type: '' };
	    const assignRow = poolRows
	        .filter((row) => (Number(row.toplam_adet) || 0) > 0)
	        .sort((a, b) => (Number(b.toplam_adet) || 0) - (Number(a.toplam_adet) || 0))[0] || null;
	    const departmentNames = [...new Set(poolRows.map((row) => row.department_name).filter(Boolean))];
	    if (!departmentNames.length && stock.departmentName) departmentNames.push(stock.departmentName);
	    if (!departmentNames.length && node.departmentName) departmentNames.push(node.departmentName);
	    const departmentLabel = departmentNames.length
	        ? departmentNames.join(', ')
	        : (stock.type || node.type || 'Bölüm atanmadı');

	    if (total > 0 && ready <= 0) {
	        return { key: 'waiting', icon: 'bi-hourglass-split', label: 'Bekliyor', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	    }
	    if (total > 0 && ready < total) {
	        return { key: 'partial', icon: 'bi-pie-chart', label: 'Kısmi', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	    }
	    if (total > 0 && ready >= total) {
	        return { key: 'ready', icon: 'bi-check2-circle', label: 'Hazır', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	    }
	    if (stock.free > 0) {
	        return { key: 'stock', icon: 'bi-box-seam', label: 'Stok', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	    }
	    if (stock.total > 0) {
	        return { key: 'reserved', icon: 'bi-bookmark-check', label: 'Ayrıldı', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	    }
	    return { key: 'missing', icon: 'bi-exclamation-circle', label: 'Eksik', ready, total, stockTotal: stock.total, stockFree: stock.free, assignRow, departmentLabel };
	}

	function getNodePoolRows(componentId, rows) {
	    return rows.filter((row) => Number(row.ara_urun_no || 0) === Number(componentId || 0));
	}

	function getNodeQuantityLabel(componentId, edges) {
	    const edge = edges.find((item) => Number(item.source_id || 0) === Number(componentId || 0));
	    if (edge) return `x${edge.quantity || 1}`;
	    const targetEdge = edges.find((item) => Number(item.target_id || 0) === Number(componentId || 0));
	    return targetEdge ? 'Kök' : '';
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

function jsArg(value) {
    return JSON.stringify(String(value ?? ''));
}

// ==========================
// Hızlı Görev Ata Mantığı
// ==========================
let assignModalInstance = null;

function loadIdlePersonnel() {
    fetch('/api/database/personnel-workload', { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            assignPersonnel = data.data || [];
        })
        .catch(err => console.error('Personel yüklenemedi:', err));
}

function openAssignModal(poolId, componentName, maxAdet, departmentId, departmentName) {
    if (!assignModalInstance) {
        assignModalInstance = new bootstrap.Modal(document.getElementById('assignTaskModal'));
        document.getElementById('assignTaskModal').addEventListener('show.bs.modal', () => { isModalOpen = true; });
        document.getElementById('assignTaskModal').addEventListener('hidden.bs.modal', () => { isModalOpen = false; });
    }

    document.getElementById('assignPoolId').value = poolId;
    document.getElementById('assignTaskInfo').textContent = `${componentName}`;
	    document.getElementById('assignAmount').value = maxAdet;
	    document.getElementById('assignAmount').max = maxAdet;
	    document.getElementById('assignAmountInfo').textContent = `Maksimum planlanabilir: ${maxAdet}`;
	    const assignDate = document.getElementById('assignDate');
	    assignDate.min = todayIso();
	    assignDate.value = todayIso();

    const select = document.getElementById('assignPersonnel');
    const sameDepartmentPersonnel = assignPersonnel
        .filter(p => String(p.BolumAdiNo || '') === String(departmentId || ''))
        .sort((a, b) => Number(a.bekleyenAdet || 0) - Number(b.bekleyenAdet || 0));

    document.getElementById('assignPersonnelHelp').textContent = departmentName
        ? `${departmentName} bölümündeki personeller listeleniyor.`
        : 'Bu görev yalnızca kendi bölümündeki personele atanabilir.';

    if (sameDepartmentPersonnel.length === 0) {
        select.innerHTML = '<option value="">Bu bölümde atanabilir personel bulunamadı</option>';
    } else {
        select.innerHTML = '<option value="">Personel Seçin...</option>' +
            sameDepartmentPersonnel.map(p => {
                const name = `${p.Ad || ''} ${p.Soyad || ''}`.trim();
                return `<option value="${p.PersonelNo}">${escHtml(name)} (${escHtml(p.BolumAdi || 'Bölümsüz')}) - ${Number(p.bekleyenAdet || 0).toLocaleString('tr-TR')} adet iş</option>`;
            }).join('');
    }

    assignModalInstance.show();
}

function submitAssignTask() {
	    const poolId = document.getElementById('assignPoolId').value;
	    const personnelNo = document.getElementById('assignPersonnel').value;
	    const amount = document.getElementById('assignAmount').value;
	    const gorevTarihi = document.getElementById('assignDate').value;

	    if (!personnelNo) {
	        Swal.fire('Eksik Bilgi', 'Lütfen personel seçin.', 'warning');
	        return;
	    }
	    if (!gorevTarihi) {
	        Swal.fire('Eksik Bilgi', 'Lütfen görevin alınacağı tarihi seçin.', 'warning');
	        return;
	    }

	    fetch(`/api/database/pool-tasks/${poolId}/assign`, {
	        method: 'POST',
	        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
	        body: JSON.stringify({ personel_no: personnelNo, adet: amount, gorev_tarihi: gorevTarihi })
	    })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            Swal.fire('Başarılı', data.message || 'Görev atandı.', 'success');
            assignModalInstance.hide();
            loadHavuz(true);
            loadIdlePersonnel();
        } else {
            Swal.fire('Hata', data.message || 'Görev atanamadı.', 'error');
        }
    })
    .catch(() => Swal.fire('Hata', 'İşlem sırasında bir hata oluştu.', 'error'));
}

setInterval(() => {
    if (!isModalOpen && !document.querySelector('.swal2-container')) {
        loadHavuz(true);
    }
}, 20000);

initLookups();
loadIdlePersonnel();
loadHavuz();
</script>
@endpush
