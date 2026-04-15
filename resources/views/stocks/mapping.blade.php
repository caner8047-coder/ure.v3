@extends('layouts.app')

@section('title', 'Urun Eslestirme Yonetimi')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRefreshTab" onclick="loadCache()">
        <i class="bi bi-arrow-repeat me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .mapping-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid var(--z-border);
        margin-bottom: 16px;
    }
    .mapping-tab-btn {
        padding: 10px 24px;
        border: none;
        background: transparent;
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--z-text-secondary);
        cursor: pointer;
        position: relative;
        transition: color 0.2s;
    }
    .mapping-tab-btn:hover { color: var(--z-text); }
    .mapping-tab-btn.active { color: var(--z-accent); }
    .mapping-tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -2px; left: 0; right: 0;
        height: 2px;
        background: var(--z-accent);
        border-radius: 2px 2px 0 0;
    }
    .mapping-tab-content { display: none; }
    .mapping-tab-content.active { display: block; }

    .mapping-add-form {
        display: none;
        padding: 16px;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        background: var(--z-bg-soft);
        margin-bottom: 16px;
    }
    .mapping-add-form.open { display: block; }

    .mapping-add-row {
        display: flex;
        gap: 12px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    .mapping-add-row .form-group { flex: 1; min-width: 200px; }
    .mapping-add-row .form-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--z-text-secondary);
        margin-bottom: 4px;
    }

    .selected-row { background: var(--z-accent-soft) !important; }

    .arrow-icon { color: var(--z-accent); font-size: 1rem; }

    .set-card {
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        margin-bottom: 10px;
        overflow: hidden;
        background: var(--z-bg-card);
    }
    .set-card-header {
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .set-card-header:hover { background: var(--z-bg-soft); }
    .set-card-header .set-icon {
        width: 34px; height: 34px;
        background: var(--z-warning-soft);
        color: var(--z-warning);
        border-radius: var(--z-radius-sm);
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem; flex-shrink: 0;
    }
    .set-card-header .set-info { flex: 1; min-width: 0; }
    .set-card-header .set-name {
        font-weight: 600; font-size: 0.88rem;
        color: var(--z-text);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .set-card-header .set-excel {
        font-size: 0.72rem; color: var(--z-accent);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .set-card-header .set-meta {
        display: flex; gap: 8px; align-items: center; flex-shrink: 0;
    }
    .set-card-body {
        display: none;
        padding: 0 16px 14px 16px;
        border-top: 1px solid var(--z-border-light);
    }
    .set-card-body.open { display: block; }
    .set-bilesen-row {
        display: flex; align-items: center; gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid var(--z-border-light);
    }
    .set-bilesen-row:last-child { border-bottom: none; }
    .set-bilesen-row .adet-badge {
        background: var(--z-warning);
        color: #fff; border-radius: 50%;
        width: 24px; height: 24px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.72rem; font-weight: 700; flex-shrink: 0;
    }
    .set-bilesen-row .urun-adi {
        font-weight: 600; color: var(--z-success); flex: 1;
    }

    .bilesen-item {
        display: flex; gap: 10px; align-items: center;
        padding: 8px 12px;
        background: var(--z-bg-card);
        border: 1px solid var(--z-border-light);
        border-radius: var(--z-radius-sm);
        margin-bottom: 6px;
    }
    .bilesen-item .bilesen-urun { flex: 3; font-weight: 600; color: var(--z-success); }
    .bilesen-item .bilesen-adet { flex: 0 0 80px; }
    .bilesen-item .bilesen-adet input {
        width: 60px; padding: 4px 8px;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-sm);
        text-align: center; font-weight: 700; font-size: 0.88rem;
    }
    .bilesen-item .bilesen-remove {
        cursor: pointer; color: var(--z-danger); font-size: 1rem;
    }

    .select2-container--default .select2-selection--single {
        height: 36px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm);
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px; font-size: 0.85rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
@endpush

@section('content')
    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam Eslesme</p>
            <h3 class="metric-value" id="statTotal">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Son 7 Gun</p>
            <h3 class="metric-value" id="statRecent">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Farkli Sistem Urunu</p>
            <h3 class="metric-value" id="statProducts">0</h3>
        </article>
    </div>

    {{-- Tabs --}}
    <section class="panel-surface">
        <div class="mapping-tabs">
            <button type="button" class="mapping-tab-btn active" onclick="switchTab('cache')">
                <i class="bi bi-link-45deg me-1"></i>Urun Eslestirme
            </button>
            <button type="button" class="mapping-tab-btn" onclick="switchTab('sets')">
                <i class="bi bi-boxes me-1"></i>Set Yonetimi
            </button>
        </div>

        {{-- TAB 1: URUN ESLESTIRME --}}
        <div id="tabCache" class="mapping-tab-content active">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Urun adi veya sistem urunu ara..." oninput="filterTable()" style="max-width:300px;" />
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="toggleAddForm()">
                        <i class="bi bi-plus-lg me-1"></i>Yeni Eslestirme
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnBulkDelete" onclick="bulkDelete()" style="display:none;">
                        <i class="bi bi-trash me-1"></i>Secilenleri Sil (<span id="selectedCountLabel">0</span>)
                    </button>
                </div>
            </div>

            {{-- Add Form --}}
            <div class="mapping-add-form" id="addFormContainer">
                <h6 class="mb-3"><i class="bi bi-plus-circle me-1" style="color:var(--z-accent);"></i>Yeni Eslestirme Ekle</h6>
                <div class="mapping-add-row">
                    <div class="form-group">
                        <label>Pazaryeri Urun Adi (Excel'deki hali)</label>
                        <input type="text" id="addExcelName" class="form-control" placeholder="Orn: Pavia Berjer Ceviz Ahsap, Kirik Beyaz" />
                    </div>
                    <div class="form-group">
                        <label>Sistem Urunu</label>
                        <select id="addProductSelect" style="width:100%;">
                            <option value="">Urun Secin...</option>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addCacheEntry()" style="height:36px;">
                        <i class="bi bi-check-lg me-1"></i>Kaydet
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleAddForm()" style="height:36px;">
                        <i class="bi bi-x-lg me-1"></i>Iptal
                    </button>
                </div>
            </div>

            {{-- Grid --}}
            <div class="table-shell">
                <table class="table-modern table-sm" id="cacheTable">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="masterCheckbox" onchange="toggleAllRows(this)" /></th>
                            <th style="width:30px;">#</th>
                            <th>Pazaryeri Urun Adi</th>
                            <th style="width:30px;"></th>
                            <th>Sistem Urunu</th>
                            <th style="width:70px;">Tur</th>
                            <th style="width:120px;">Eklenme Tarihi</th>
                            <th style="width:70px;">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody id="cacheTableBody">
                    </tbody>
                </table>
            </div>
        </div>

        {{-- TAB 2: SET YONETIMI --}}
        <div id="tabSets" class="mapping-tab-content">
            <div class="stats-grid mb-3" style="grid-template-columns: repeat(2, 1fr);">
                <article class="metric-card">
                    <p class="metric-label">Toplam Set</p>
                    <h3 class="metric-value" id="statSetTotal">0</h3>
                </article>
                <article class="metric-card">
                    <p class="metric-label">Toplam Bilesen</p>
                    <h3 class="metric-value" id="statSetBilesen">0</h3>
                </article>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <input type="text" id="searchSetInput" class="form-control form-control-sm" placeholder="Set adi ara..." oninput="filterSets()" style="max-width:300px;" />
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="toggleSetForm()">
                        <i class="bi bi-plus-lg me-1"></i>Yeni Set Tanimla
                    </button>
                </div>
            </div>

            {{-- Set Add Form --}}
            <div class="mapping-add-form" id="setFormContainer">
                <h6 class="mb-3"><i class="bi bi-boxes me-1" style="color:var(--z-warning);"></i>Yeni Set Tanimla</h6>

                <div class="mapping-add-row mb-3">
                    <div class="form-group" style="flex:2;">
                        <label>Pazaryeri Set Adi (Excel'deki hali — tam olarak yazilmali)</label>
                        <input type="text" id="setExcelName" class="form-control"
                            placeholder="Orn: Pavia Cay Seti Naturel Ahsap, Sutlu Kahve (1 Adet Tekli Berjer 1 Adet Puf)" />
                    </div>
                    <div class="form-group">
                        <label>Kisa Adi (istege bagli)</label>
                        <input type="text" id="setShortName" class="form-control" placeholder="Orn: Pavia Cay Seti Sutlu Kahve" />
                    </div>
                </div>

                <h6 class="mb-2 small fw-bold text-muted">Bilesenler</h6>

                <div class="mapping-add-row mb-2">
                    <div class="form-group" style="flex:2;">
                        <select id="setBilesenSelect" style="width:100%;">
                            <option value="">Bilesen urunu secin...</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 80px;">
                        <input type="number" id="setBilesenAdet" class="form-control" value="1" min="1" max="99"
                            style="text-align:center; font-weight:700;" />
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addBilesen()" style="height:36px;">
                        <i class="bi bi-plus me-1"></i>Ekle
                    </button>
                </div>

                <div id="bilesenList" class="mb-3"></div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-sm" onclick="saveSetDefinition()">
                        <i class="bi bi-check-lg me-1"></i>Seti Kaydet
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSetForm()">
                        <i class="bi bi-x-lg me-1"></i>Iptal
                    </button>
                </div>
            </div>

            {{-- Set List --}}
            <div id="setListContainer"></div>
        </div>
    </section>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    var apiUrl = '/api/siparisler';
    var allItems = [];
    var selectedNos = new Set();
    var products = [];
    var allSets = [];
    var pendingBilesenler = [];

    $(document).ready(function () {
        loadCache();
        loadProducts();
        loadSets();
    });

    // ===== TAB SWITCHING =====
    function switchTab(tab) {
        $('.mapping-tab-btn').removeClass('active');
        $('.mapping-tab-content').removeClass('active');
        if (tab === 'cache') {
            $('.mapping-tab-btn:first').addClass('active');
            $('#tabCache').addClass('active');
            document.getElementById('btnRefreshTab').onclick = loadCache;
        } else {
            $('.mapping-tab-btn:last').addClass('active');
            $('#tabSets').addClass('active');
            document.getElementById('btnRefreshTab').onclick = loadSets;
        }
    }

    // ===== URUN LISTESI =====
    function loadProducts() {
        $.getJSON(apiUrl + '?action=getProducts', function (res) {
            if (res.success) {
                products = res.products || [];

                var options = '<option value="">Urun Secin...</option>';
                products.forEach(function (p) {
                    var label = p.sistemAdi || p.urunID || ('Urun #' + p.no);
                    options += '<option value="Nihai_' + p.no + '">' + label + '</option>';
                });
                $('#addProductSelect').html(options);
                $('#addProductSelect').select2({ placeholder: 'Urun arayin...', allowClear: true, width: '100%' });

                var setOpts = '<option value="">Bilesen urunu secin...</option>';
                products.forEach(function (p) {
                    var label = p.sistemAdi || p.urunID || ('Urun #' + p.no);
                    setOpts += '<option value="' + p.no + '" data-label="' + escapeHtml(label) + '">' + label + '</option>';
                });
                $('#setBilesenSelect').html(setOpts);
                $('#setBilesenSelect').select2({ placeholder: 'Bilesen urunu arayin...', allowClear: true, width: '100%' });
            }
        });
    }

    // ===== TAB 1: URUN ESLESTIRME =====
    function loadCache() {
        $.getJSON(apiUrl + '?action=getMatchCache', function (res) {
            if (res.success) {
                allItems = res.items || [];
                selectedNos.clear();
                updateBulkButton();
                renderTable(allItems);
                updateStats(allItems);
            }
        });
    }

    function renderTable(items) {
        var tbody = document.getElementById('cacheTableBody');
        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8"><div class="text-center py-5 text-muted"><i class="bi bi-link-slash d-block mb-2" style="font-size:2rem;opacity:0.3;"></i><strong>Henuz eslestirme yok</strong><br><span class="small">Manuel eslestirmeler veya yukaridaki form ile ekleyebilirsiniz.</span></div></td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (item, i) {
            var isSelected = selectedNos.has(item.no);
            html += '<tr data-no="' + item.no + '" class="' + (isSelected ? 'selected-row' : '') + '">' +
                '<td><input type="checkbox" class="row-cb" data-no="' + item.no + '" ' + (isSelected ? 'checked' : '') + ' onchange="onRowCheckChange(this)" /></td>' +
                '<td class="small text-muted">' + (i + 1) + '</td>' +
                '<td style="font-weight:600; color:var(--z-accent);">' + escapeHtml(item.excelUrunAdi) + '</td>' +
                '<td><i class="bi bi-arrow-right arrow-icon"></i></td>' +
                '<td style="font-weight:600; color:var(--z-success);">' + escapeHtml(item.sistemUrunAdi || 'Urun #' + item.eslesenUrunNo) + '</td>' +
                '<td><span class="badge" style="background:var(--z-accent-soft);color:var(--z-accent);font-size:0.72rem;">' + item.eslesenUrunTur + '</span></td>' +
                '<td class="small text-muted">' + item.olusturmaTarihi + '</td>' +
                '<td><button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteEntry(' + item.no + ')"><i class="bi bi-trash"></i></button></td></tr>';
        });
        tbody.innerHTML = html;
    }

    function updateStats(items) {
        document.getElementById('statTotal').textContent = items.length;
        var recent = 0, now = new Date();
        items.forEach(function (item) {
            var parts = item.olusturmaTarihi.split(' ')[0].split('.');
            var d = new Date(parts[2], parts[1] - 1, parts[0]);
            if ((now - d) / (1000 * 60 * 60 * 24) <= 7) recent++;
        });
        document.getElementById('statRecent').textContent = recent;
        var unique = new Set();
        items.forEach(function (item) { unique.add(item.eslesenUrunNo); });
        document.getElementById('statProducts').textContent = unique.size;
    }

    function filterTable() {
        var q = document.getElementById('searchInput').value.toLowerCase().trim();
        if (!q) { renderTable(allItems); return; }
        renderTable(allItems.filter(function (item) {
            return item.excelUrunAdi.toLowerCase().indexOf(q) >= 0 ||
                (item.sistemUrunAdi && item.sistemUrunAdi.toLowerCase().indexOf(q) >= 0);
        }));
    }

    function toggleAddForm() {
        var form = document.getElementById('addFormContainer');
        form.classList.toggle('open');
    }

    function addCacheEntry() {
        var excelName = document.getElementById('addExcelName').value.trim();
        var compositeVal = $('#addProductSelect').val();
        if (!excelName) { Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Pazaryeri urun adini girin.' }); return; }
        if (!compositeVal) { Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Sistem urunu secin.' }); return; }
        var parts = compositeVal.split('_');
        $.ajax({
            url: apiUrl + '?action=addMatchCache', type: 'POST', contentType: 'application/json',
            data: JSON.stringify({ excelUrunAdi: excelName, eslesenUrunNo: parseInt(parts[1]), eslesenUrunTur: parts[0] }),
            dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Kaydedildi!', text: res.message });
                    document.getElementById('addExcelName').value = '';
                    $('#addProductSelect').val('').trigger('change');
                    toggleAddForm(); loadCache();
                } else { Swal.fire({ icon: 'error', title: 'Hata', text: res.message }); }
            }
        });
    }

    function deleteEntry(no) {
        Swal.fire({
            icon: 'warning', title: 'Eslestirmeyi Sil',
            text: 'Bu eslestirmeyi silmek istediginize emin misiniz?',
            showCancelButton: true, confirmButtonText: 'Evet, Sil', cancelButtonText: 'Iptal',
            confirmButtonColor: '#dc2626', reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: apiUrl + '?action=deleteMatchCache', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ no: no }), dataType: 'json',
                    success: function (res) {
                        if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, timer: 1500 }); loadCache(); }
                    }
                });
            }
        });
    }

    function onRowCheckChange(cb) {
        var no = parseInt(cb.getAttribute('data-no'));
        if (cb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
        else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
        updateBulkButton();
    }

    function toggleAllRows(masterCb) {
        document.querySelectorAll('.row-cb').forEach(function (cb) {
            cb.checked = masterCb.checked;
            var no = parseInt(cb.getAttribute('data-no'));
            if (masterCb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
            else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
        });
        updateBulkButton();
    }

    function updateBulkButton() {
        var btn = document.getElementById('btnBulkDelete');
        if (selectedNos.size > 0) { btn.style.display = 'inline-flex'; document.getElementById('selectedCountLabel').textContent = selectedNos.size; }
        else { btn.style.display = 'none'; }
        document.getElementById('masterCheckbox').checked = false;
    }

    function bulkDelete() {
        if (selectedNos.size === 0) return;
        Swal.fire({
            icon: 'warning', title: 'Toplu Silme',
            html: '<strong>' + selectedNos.size + '</strong> eslestirmeyi silmek istediginize emin misiniz?',
            showCancelButton: true, confirmButtonText: 'Evet, Hepsini Sil', cancelButtonText: 'Iptal',
            confirmButtonColor: '#dc2626', reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Siliniyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                $.ajax({
                    url: apiUrl + '?action=deleteMatchCache', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ noList: Array.from(selectedNos) }), dataType: 'json',
                    success: function (res) {
                        if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message }); loadCache(); }
                    }
                });
            }
        });
    }

    // ===== TAB 2: SET YONETIMI =====
    function loadSets() {
        $.getJSON(apiUrl + '?action=getSetDefinitions', function (res) {
            if (res.success) {
                allSets = res.sets || [];
                renderSets(allSets);
                updateSetStats(allSets);
            }
        });
    }

    function renderSets(sets) {
        var container = document.getElementById('setListContainer');
        if (sets.length === 0) {
            container.innerHTML = '<div class="text-center py-5 text-muted"><i class="bi bi-boxes d-block mb-2" style="font-size:2rem;opacity:0.3;"></i><strong>Henuz set tanimi yok</strong><br><span class="small">Yukaridaki butonla yeni set tanimlayabilirsiniz.</span></div>';
            return;
        }
        var html = '';
        sets.forEach(function (set) {
            var bilesenCount = set.icerikler ? set.icerikler.length : 0;
            var name = set.setAdi || set.excelSetAdi;

            html += '<div class="set-card" data-setno="' + set.no + '">';
            html += '<div class="set-card-header" onclick="toggleSetCard(' + set.no + ')">';
            html += '<div class="set-icon"><i class="bi bi-boxes"></i></div>';
            html += '<div class="set-info">';
            html += '<div class="set-name">' + escapeHtml(name) + '</div>';
            if (set.setAdi && set.excelSetAdi !== set.setAdi) {
                html += '<div class="set-excel">' + escapeHtml(set.excelSetAdi) + '</div>';
            }
            html += '</div>';
            html += '<div class="set-meta">';
            html += '<span class="badge" style="background:var(--z-warning-soft);color:var(--z-warning);font-size:0.72rem;">' + bilesenCount + ' bilesen</span>';
            html += '<span class="small text-muted">' + set.olusturmaTarihi + '</span>';
            html += '<button type="button" class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation(); deleteSet(' + set.no + ')"><i class="bi bi-trash"></i></button>';
            html += '</div></div>';

            html += '<div class="set-card-body" id="setBody_' + set.no + '">';
            if (set.icerikler && set.icerikler.length > 0) {
                set.icerikler.forEach(function (ic) {
                    html += '<div class="set-bilesen-row">';
                    html += '<div class="adet-badge">' + ic.adet + '</div>';
                    html += '<div class="urun-adi">' + escapeHtml(ic.urunAdi || 'Urun #' + ic.urunNo) + '</div>';
                    html += '</div>';
                });
            }
            html += '</div></div>';
        });
        container.innerHTML = html;
    }

    function updateSetStats(sets) {
        document.getElementById('statSetTotal').textContent = sets.length;
        var totalBilesen = 0;
        sets.forEach(function (s) { totalBilesen += (s.icerikler ? s.icerikler.length : 0); });
        document.getElementById('statSetBilesen').textContent = totalBilesen;
    }

    function filterSets() {
        var q = document.getElementById('searchSetInput').value.toLowerCase().trim();
        if (!q) { renderSets(allSets); return; }
        renderSets(allSets.filter(function (s) {
            return s.excelSetAdi.toLowerCase().indexOf(q) >= 0 ||
                (s.setAdi && s.setAdi.toLowerCase().indexOf(q) >= 0);
        }));
    }

    function toggleSetCard(setNo) {
        var body = document.getElementById('setBody_' + setNo);
        body.classList.toggle('open');
    }

    function toggleSetForm() {
        var form = document.getElementById('setFormContainer');
        if (!form.classList.contains('open')) {
            form.classList.add('open');
            pendingBilesenler = [];
            renderBilesenList();
            document.getElementById('setExcelName').value = '';
            document.getElementById('setShortName').value = '';
        } else {
            form.classList.remove('open');
        }
    }

    function addBilesen() {
        var sel = document.getElementById('setBilesenSelect');
        var urunNo = parseInt(sel.value);
        if (!urunNo) { Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Bilesen urunu secin.' }); return; }
        var adet = parseInt(document.getElementById('setBilesenAdet').value) || 1;
        var label = sel.options[sel.selectedIndex].text;

        var found = false;
        pendingBilesenler.forEach(function (b) {
            if (b.urunNo === urunNo) { b.adet += adet; found = true; }
        });
        if (!found) {
            pendingBilesenler.push({ urunNo: urunNo, adet: adet, label: label });
        }
        renderBilesenList();
        $('#setBilesenSelect').val('').trigger('change');
        document.getElementById('setBilesenAdet').value = '1';
    }

    function removeBilesen(idx) {
        pendingBilesenler.splice(idx, 1);
        renderBilesenList();
    }

    function renderBilesenList() {
        var html = '';
        if (pendingBilesenler.length === 0) {
            html = '<div class="small text-muted p-2">Henuz bilesen eklenmedi. Yukaridan urun secip "Ekle" butonuna basin.</div>';
        } else {
            pendingBilesenler.forEach(function (b, i) {
                html += '<div class="bilesen-item">';
                html += '<div class="adet-badge" style="background:var(--z-warning);color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;">' + b.adet + '</div>';
                html += '<div class="bilesen-urun">' + escapeHtml(b.label) + '</div>';
                html += '<div class="bilesen-adet"><input type="number" value="' + b.adet + '" min="1" max="99" onchange="updateBilesenAdet(' + i + ', this.value)" /></div>';
                html += '<span class="bilesen-remove" onclick="removeBilesen(' + i + ')"><i class="bi bi-x-circle"></i></span>';
                html += '</div>';
            });
        }
        document.getElementById('bilesenList').innerHTML = html;
    }

    function updateBilesenAdet(idx, val) {
        pendingBilesenler[idx].adet = parseInt(val) || 1;
        renderBilesenList();
    }

    function saveSetDefinition() {
        var excelName = document.getElementById('setExcelName').value.trim();
        var shortName = document.getElementById('setShortName').value.trim();

        if (!excelName) { Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Pazaryeri set adini girin.' }); return; }
        if (pendingBilesenler.length === 0) { Swal.fire({ icon: 'warning', title: 'Uyari', text: 'En az bir bilesen eklemelisiniz.' }); return; }

        Swal.fire({ title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

        var payload = {
            excelSetAdi: excelName,
            setAdi: shortName,
            icerikler: pendingBilesenler.map(function (b) { return { urunNo: b.urunNo, adet: b.adet }; })
        };

        $.ajax({
            url: apiUrl + '?action=addSetDefinition', type: 'POST', contentType: 'application/json',
            data: JSON.stringify(payload), dataType: 'json',
            success: function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Set Kaydedildi!', text: res.message });
                    toggleSetForm();
                    loadSets();
                } else {
                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                }
            },
            error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatasi', text: xhr.statusText }); }
        });
    }

    function deleteSet(no) {
        Swal.fire({
            icon: 'warning', title: 'Seti Sil',
            text: 'Bu set tanimini ve tum bilesenlerini silmek istediginize emin misiniz?',
            showCancelButton: true, confirmButtonText: 'Evet, Sil', cancelButtonText: 'Iptal',
            confirmButtonColor: '#dc2626', reverseButtons: true
        }).then(function (result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: apiUrl + '?action=deleteSetDefinition', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ no: no }), dataType: 'json',
                    success: function (res) {
                        if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, timer: 1500 }); loadSets(); }
                    }
                });
            }
        });
    }

    // ===== UTILS =====
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
</script>
@endpush
