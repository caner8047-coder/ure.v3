@extends('layouts.app')

@section('title', 'Urun Eslestirme')


@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="loadProducts()">
        <i class="bi bi-arrow-repeat me-2"></i>Secicileri Yenile
    </button>
    <button type="button" class="btn btn-primary" onclick="refreshWorkspace()">
        <i class="bi bi-link-45deg me-2"></i>Tum Veriyi Yenile
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .match-tab-switcher { display: flex; gap: 4px; flex-wrap: wrap; }
    .match-tab-button { border: 1px solid var(--z-border); border-radius: var(--z-radius-sm); background: var(--z-bg-card); color: var(--z-text-secondary); font-weight: 500; font-size: 0.82rem; padding: 8px 14px; cursor: pointer; transition: all var(--z-transition); }
    .match-tab-button:hover { background: var(--z-bg-soft); color: var(--z-text); }
    .match-tab-button.active { background: var(--z-accent); color: white; border-color: var(--z-accent); }
    .match-tab-content { display: none; gap: 16px; }
    .match-tab-content.active { display: grid; }
    .match-filter-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) auto auto auto; gap: 12px; align-items: end; }
    .match-set-filter-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) auto auto; gap: 12px; align-items: end; }
    .match-form-panel { display: none; margin-top: 16px; padding: 20px; border: 1px solid var(--z-border); border-radius: var(--z-radius); background: var(--z-bg-card); }
    .match-form-panel.is-open { display: block; }
    .match-form-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(260px, 0.85fr) auto auto; gap: 12px; align-items: end; }
    .match-set-grid { display: grid; gap: 14px; }
    .match-set-top-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(240px, 0.7fr); gap: 12px; }
    .match-component-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) 100px auto; gap: 12px; align-items: end; }
    .match-empty { display: grid; place-items: center; gap: 10px; min-height: 180px; text-align: center; color: var(--z-text-muted); }
    .match-empty i { font-size: 2rem; color: var(--z-accent); opacity: 0.4; }
    .match-empty h3 { margin: 0; font-size: 0.95rem; font-weight: 600; }
    .match-empty p { margin: 0; max-width: 42ch; font-size: 0.82rem; }
    .match-table tbody tr.selected-row td { background: var(--z-accent-soft) !important; }
    .match-table thead th { white-space: nowrap; }
    .match-set-list { display: grid; gap: 12px; }
    .match-set-card { border: 1px solid var(--z-border); border-radius: var(--z-radius); background: var(--z-bg-card); overflow: hidden; }
    .match-set-header { display: flex; align-items: center; gap: 14px; padding: 16px; cursor: pointer; transition: background var(--z-transition); }
    .match-set-header:hover { background: var(--z-bg-soft); }
    .match-set-icon { width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: var(--z-warning-soft); color: var(--z-warning); font-size: 1rem; flex-shrink: 0; }
    .match-set-copy { min-width: 0; flex: 1; }
    .match-set-copy h4 { margin: 0; font-size: 0.92rem; font-weight: 600; color: var(--z-text); }
    .match-set-copy p { margin: 4px 0 0; color: var(--z-text-muted); font-size: 0.8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .match-set-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
    .match-set-body { display: none; padding: 0 16px 16px; border-top: 1px solid var(--z-border-light); }
    .match-set-body.open { display: block; }
    .match-set-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--z-border-light); }
    .match-set-item:last-child { border-bottom: 0; }
    .match-component-list { display: grid; gap: 8px; }
    .match-component-item { display: grid; grid-template-columns: auto minmax(0, 1fr) 92px auto; gap: 12px; align-items: center; padding: 10px 14px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm); background: var(--z-bg-card); }
    .match-component-item input { text-align: center; font-weight: 600; }
    .match-component-title { font-weight: 600; color: var(--z-text); font-size: 0.84rem; }
    .match-form-note { margin: 4px 0 0; color: var(--z-text-muted); font-size: 0.78rem; }
    .select2-container--default .select2-selection--single { height: 38px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm); background: var(--z-bg-input); }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px; padding-left: 12px; color: var(--z-text); font-size: 0.84rem; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px; right: 6px; }
    .select2-dropdown { border-color: var(--z-border); border-radius: var(--z-radius-sm); overflow: hidden; box-shadow: var(--z-shadow-hover); }
    @media (max-width: 1080px) { .match-filter-grid, .match-set-filter-grid, .match-form-grid, .match-set-top-grid, .match-component-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam Eslesme</p>
            <h3 class="metric-value" id="statTotal">0</h3>
            <span id="summaryMatchInline" style="display:none;">0</span>
        </article>
        <article class="metric-card">
            <p class="metric-label">Son 7 Gun</p>
            <h3 class="metric-value" id="statRecent">0</h3>
            <span id="summaryRecentInline" style="display:none;">0</span>
        </article>
        <article class="metric-card">
            <p class="metric-label">Farkli Sistem Urunu</p>
            <h3 class="metric-value" id="statProducts">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Set Merkezi</p>
            <h3 class="metric-value" id="statSetTotal">0</h3>
            <div class="metric-foot">
                <span class="soft-badge warning">Toplam bilesen</span>
                <strong id="statSetBilesen">0</strong>
            </div>
            <span id="summarySetsInline" style="display:none;">0</span>
            <span id="summaryComponentsInline" style="display:none;">0</span>
        </article>
    </div>

    {{-- Hidden compat elements --}}
    <span id="activeTabBadge" style="display:none;">Urun eslestirme</span>
    <span id="activeTabMeta" style="display:none;">Urun eslestirme</span>
    <span id="selectedBulkMeta" style="display:none;">0 kayit</span>
    <span id="pendingComponentsMeta" style="display:none;">0 oge</span>
    <span id="summaryUpdatedMeta" style="display:none;">Bekleniyor</span>



    <div class="match-tab-switcher" style="margin-bottom: 16px;">
        <button type="button" class="match-tab-button active" data-tab-button="cache" onclick="switchTab('cache')">
            <i class="bi bi-link-45deg me-1"></i>Urun Eslestirme
        </button>
        <button type="button" class="match-tab-button" data-tab-button="sets" onclick="switchTab('sets')">
            <i class="bi bi-boxes me-1"></i>Set Yonetimi
        </button>
    </div>

    <div id="tabCache" class="match-tab-content active">
        <section class="panel-surface">
            <div class="section-header">
                <div>
                    <h3 class="section-title">Tekli urun eslestirmeleri</h3>
                </div>
                <span class="soft-badge warning" id="cacheQueryState">Hazir</span>
            </div>

            <div class="match-filter-grid">
                <div>
                    <label class="form-label" for="searchInput">Ara</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Pazaryeri veya sistem urunu ara..." oninput="filterTable()">
                </div>

                <button type="button" class="btn btn-outline-secondary" onclick="toggleAddForm()">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Eslestirme
                </button>
                <button type="button" class="btn btn-outline-danger" id="btnBulkDelete" onclick="bulkDelete()" style="display:none;">
                    <i class="bi bi-trash me-2"></i>Secilenleri Sil (<span id="selectedCountLabel">0</span>)
                </button>
                <button type="button" class="btn btn-primary" onclick="loadCache()">
                    <i class="bi bi-arrow-repeat me-2"></i>Listeyi Yenile
                </button>
            </div>

            <div class="match-form-panel" id="addFormContainer">
                <div class="section-header compact">
                    <div>
                        <h3 class="section-title">Yeni eslestirme ekle</h3>
                    </div>
                </div>

                <div class="match-form-grid">
                    <div>
                        <label class="form-label" for="addExcelName">Pazaryeri urun adi</label>
                        <input type="text" id="addExcelName" class="form-control" placeholder="Orn: Pavia Berjer Ceviz Ahsap, Kirik Beyaz">
                    </div>

                    <div>
                        <label class="form-label" for="addProductSelect">Sistem urunu</label>
                        <select id="addProductSelect" style="width: 100%;">
                            <option value="">Urun Secin...</option>
                        </select>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="addCacheEntry()">
                        <i class="bi bi-check2-circle me-2"></i>Kaydet
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="toggleAddForm()">
                        <i class="bi bi-x-circle me-2"></i>Iptal
                    </button>
                </div>
            </div>
        </section>

        <section class="panel-surface table-panel">
            <div class="panel-toolbar">
                <div class="panel-toolbar-copy"><h3>Mevcut eslestirmeler</h3></div>
                <div class="panel-toolbar-meta">
                    <span class="soft-badge" id="cacheTableMeta">0 kayit</span>
                </div>
            </div>

            <div class="table-shell">
                <table class="table-modern table-sm match-table" id="cacheTable">
                    <thead>
                        <tr>
                            <th style="width: 44px;"><input type="checkbox" id="masterCheckbox" onchange="toggleAllRows(this)"></th>
                            <th style="width: 44px;">#</th>
                            <th>Pazaryeri Urun Adi</th>
                            <th style="width: 56px;"></th>
                            <th>Sistem Urunu</th>
                            <th style="width: 120px;">Tur</th>
                            <th style="width: 160px;">Eklenme</th>
                            <th style="width: 100px;">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody id="cacheTableBody">
                        <tr><td colspan="8" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="tabSets" class="match-tab-content">
        <section class="panel-surface">
            <div class="section-header">
                <div>
                    <h3 class="section-title">Set tanimlari</h3>
                </div>
                <span class="soft-badge warning" id="setQueryState">Hazir</span>
            </div>

            <div class="match-set-filter-grid">
                <div>
                    <label class="form-label" for="searchSetInput">Set ara</label>
                    <input type="text" id="searchSetInput" class="form-control" placeholder="Set adi ara..." oninput="filterSets()">
                </div>

                <button type="button" class="btn btn-outline-secondary" onclick="toggleSetForm()">
                    <i class="bi bi-plus-circle me-2"></i>Yeni Set Tanimi
                </button>
                <button type="button" class="btn btn-primary" onclick="loadSets()">
                    <i class="bi bi-arrow-repeat me-2"></i>Setleri Yenile
                </button>
            </div>

            <div class="match-form-panel" id="setFormContainer">
                <div class="section-header compact">
                    <div>
                        <h3 class="section-title">Yeni set tanimla</h3>
                    </div>
                </div>

                <div class="match-set-grid">
                    <div class="match-set-top-grid">
                        <div>
                            <label class="form-label" for="setExcelName">Pazaryeri set adi</label>
                            <input type="text" id="setExcelName" class="form-control" placeholder="Orn: Pavia Cay Seti Naturel Ahsap, Sutlu Kahve">
                            <p class="match-form-note">Excel'deki ad ile bire bir eslesmesi gerekir.</p>
                        </div>

                        <div>
                            <label class="form-label" for="setShortName">Kisa adi</label>
                            <input type="text" id="setShortName" class="form-control" placeholder="Opsiyonel kisa ad">
                        </div>
                    </div>

                    <div class="match-component-grid">
                        <div>
                            <label class="form-label" for="setBilesenSelect">Bilesen urunu</label>
                            <select id="setBilesenSelect" style="width: 100%;">
                                <option value="">Bilesen urunu secin...</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="setBilesenAdet">Adet</label>
                            <input type="number" id="setBilesenAdet" class="form-control" value="1" min="1" max="99">
                        </div>

                        <button type="button" class="btn btn-outline-secondary" onclick="addBilesen()">
                            <i class="bi bi-plus-circle me-2"></i>Ekle
                        </button>
                    </div>

                    <div id="bilesenList" class="match-component-list"></div>

                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="toggleSetForm()">
                            <i class="bi bi-x-circle me-2"></i>Iptal
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveSetDefinition()">
                            <i class="bi bi-check2-circle me-2"></i>Seti Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel-surface table-panel">
            <div class="panel-toolbar">
                <div class="panel-toolbar-copy"><h3>Kayitli set tanimlari</h3></div>
                <div class="panel-toolbar-meta">
                    <span class="soft-badge" id="setListMeta">0 set</span>
                </div>
            </div>

            <div id="setListContainer" class="match-set-list"></div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
var apiUrl = '/SiparisApi.ashx';
var allItems = [];
var selectedNos = new Set();
var products = [];
var allSets = [];
var pendingBilesenler = [];
var activeTab = 'cache';

$(document).ready(function () {
    refreshWorkspace();
    switchTab('cache');
});

function refreshWorkspace() {
    loadProducts();
    loadCache();
    loadSets();
}

function switchTab(tab) {
    activeTab = tab;

    document.querySelectorAll('[data-tab-button]').forEach(function (button) {
        button.classList.toggle('active', button.getAttribute('data-tab-button') === tab);
    });

    document.querySelectorAll('.match-tab-content').forEach(function (panel) {
        panel.classList.toggle('active', panel.id === 'tab' + capitalize(tab));
    });

    updateWorkspaceMeta();
}

function capitalize(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function loadProducts() {
    $.getJSON(apiUrl + '?action=getProducts', function (res) {
        if (!res.success) return;

        products = res.products || [];

        var options = '<option value="">Urun Secin...</option>';
        products.forEach(function (product) {
            var label = product.sistemAdi || product.urunID || ('Urun #' + product.no);
            options += '<option value="Nihai_' + product.no + '">' + escapeHtml(label) + '</option>';
        });
        $('#addProductSelect').html(options);
        initSelect2('#addProductSelect', 'Urun arayin...');

        var setOptions = '<option value="">Bilesen urunu secin...</option>';
        products.forEach(function (product) {
            var label = product.sistemAdi || product.urunID || ('Urun #' + product.no);
            setOptions += '<option value="' + product.no + '" data-label="' + escapeHtml(label) + '">' + escapeHtml(label) + '</option>';
        });
        $('#setBilesenSelect').html(setOptions);
        initSelect2('#setBilesenSelect', 'Bilesen urunu arayin...');

        setRefreshStamp();
    });
}

function initSelect2(selector, placeholder) {
    var element = $(selector);
    if (element.data('select2')) {
        element.select2('destroy');
    }

    element.select2({
        placeholder: placeholder,
        allowClear: true,
        width: '100%'
    });
}

function loadCache() {
    document.getElementById('cacheQueryState').textContent = 'Yukleniyor';
    document.getElementById('cacheQueryState').className = 'match-pill warning';

    $.getJSON(apiUrl + '?action=getMatchCache', function (res) {
        if (!res.success) return;

        allItems = res.items || [];
        selectedNos.clear();
        updateBulkButton();

        if (document.getElementById('searchInput').value.trim()) {
            filterTable();
        } else {
            renderTable(allItems);
        }

        updateStats(allItems);
        document.getElementById('cacheQueryState').textContent = 'Guncel';
        document.getElementById('cacheQueryState').className = 'match-pill success';
        setRefreshStamp();
    });
}

function renderTable(items) {
    var tbody = document.getElementById('cacheTableBody');
    document.getElementById('cacheTableMeta').textContent = items.length + ' kayit';

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="match-empty"><i class="bi bi-link-45deg"></i><h3>Henuz eslestirme yok</h3><p>Yeni eslestirme paneli ile pazaryeri urunlerini sisteme baglayabilirsin.</p></div></td></tr>';
        return;
    }

    var html = '';
    items.forEach(function (item, index) {
        var isSelected = selectedNos.has(item.no);
        html += '<tr data-no="' + item.no + '" class="' + (isSelected ? 'selected-row' : '') + '">' +
            '<td><input type="checkbox" class="row-cb" data-no="' + item.no + '" ' + (isSelected ? 'checked' : '') + ' onchange="onRowCheckChange(this)"></td>' +
            '<td class="small text-muted">' + (index + 1) + '</td>' +
            '<td><strong>' + escapeHtml(item.excelUrunAdi) + '</strong></td>' +
            '<td class="text-center"><i class="bi bi-arrow-left-right text-primary"></i></td>' +
            '<td><strong class="text-success">' + escapeHtml(item.sistemUrunAdi || ('Urun #' + item.eslesenUrunNo)) + '</strong></td>' +
            '<td><span class="soft-badge">' + escapeHtml(item.eslesenUrunTur || '-') + '</span></td>' +
            '<td class="small">' + escapeHtml(item.olusturmaTarihi || '-') + '</td>' +
            '<td><button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteEntry(' + item.no + ')"><i class="bi bi-trash"></i></button></td>' +
        '</tr>';
    });

    tbody.innerHTML = html;
}

function updateStats(items) {
    document.getElementById('statTotal').textContent = items.length;
    document.getElementById('summaryMatchInline').textContent = items.length;

    var recent = 0;
    var unique = new Set();
    var now = new Date();

    items.forEach(function (item) {
        unique.add(item.eslesenUrunNo);
        if (!item.olusturmaTarihi) return;

        var rawDate = item.olusturmaTarihi.split(' ')[0] || '';
        var parts = rawDate.split('.');
        if (parts.length !== 3) return;

        var parsed = new Date(parts[2], parts[1] - 1, parts[0]);
        if (!Number.isNaN(parsed.getTime()) && ((now - parsed) / (1000 * 60 * 60 * 24)) <= 7) {
            recent += 1;
        }
    });

    document.getElementById('statRecent').textContent = recent;
    document.getElementById('summaryRecentInline').textContent = recent;
    document.getElementById('statProducts').textContent = unique.size;
}

function filterTable() {
    var query = document.getElementById('searchInput').value.toLowerCase().trim();
    if (!query) {
        renderTable(allItems);
        return;
    }

    renderTable(allItems.filter(function (item) {
        return item.excelUrunAdi.toLowerCase().indexOf(query) >= 0 ||
            ((item.sistemUrunAdi || '').toLowerCase().indexOf(query) >= 0);
    }));
}

function toggleAddForm(forceState) {
    var form = document.getElementById('addFormContainer');
    var shouldOpen = typeof forceState === 'boolean' ? forceState : !form.classList.contains('is-open');
    form.classList.toggle('is-open', shouldOpen);

    if (shouldOpen) {
        document.getElementById('addExcelName').focus();
    }
}

function addCacheEntry() {
    var excelName = document.getElementById('addExcelName').value.trim();
    var compositeVal = $('#addProductSelect').val();

    if (!excelName) {
        Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Pazaryeri urun adini girin.' });
        return;
    }

    if (!compositeVal) {
        Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Sistem urunu secin.' });
        return;
    }

    var parts = compositeVal.split('_');
    $.ajax({
        url: apiUrl + '?action=addMatchCache',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            excelUrunAdi: excelName,
            eslesenUrunNo: parseInt(parts[1], 10),
            eslesenUrunTur: parts[0]
        }),
        dataType: 'json',
        success: function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Kaydedildi', text: res.message, confirmButtonColor: '#2563eb' });
            document.getElementById('addExcelName').value = '';
            $('#addProductSelect').val('').trigger('change');
            toggleAddForm(false);
            loadCache();
        }
    });
}

function deleteEntry(no) {
    Swal.fire({
        icon: 'warning',
        title: 'Eslestirmeyi sil',
        text: 'Bu kaydi silmek istediginize emin misiniz?',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Iptal',
        confirmButtonColor: '#dc2626',
        reverseButtons: true
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: apiUrl + '?action=deleteMatchCache',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ no: no }),
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, confirmButtonColor: '#2563eb', timer: 1500 });
                loadCache();
            }
        });
    });
}

function onRowCheckChange(checkbox) {
    var no = parseInt(checkbox.getAttribute('data-no'), 10);
    if (checkbox.checked) {
        selectedNos.add(no);
        checkbox.closest('tr').classList.add('selected-row');
    } else {
        selectedNos.delete(no);
        checkbox.closest('tr').classList.remove('selected-row');
    }

    updateBulkButton();
}

function toggleAllRows(masterCheckbox) {
    document.querySelectorAll('.row-cb').forEach(function (checkbox) {
        checkbox.checked = masterCheckbox.checked;
        var no = parseInt(checkbox.getAttribute('data-no'), 10);

        if (masterCheckbox.checked) {
            selectedNos.add(no);
            checkbox.closest('tr').classList.add('selected-row');
        } else {
            selectedNos.delete(no);
            checkbox.closest('tr').classList.remove('selected-row');
        }
    });

    updateBulkButton();
}

function updateBulkButton() {
    var bulkButton = document.getElementById('btnBulkDelete');
    bulkButton.style.display = selectedNos.size > 0 ? 'inline-flex' : 'none';
    document.getElementById('selectedCountLabel').textContent = selectedNos.size;
    document.getElementById('selectedBulkMeta').textContent = selectedNos.size + ' kayit';

    var masterCheckbox = document.getElementById('masterCheckbox');
    if (masterCheckbox) {
        masterCheckbox.checked = false;
    }

    updateWorkspaceMeta();
}

function bulkDelete() {
    if (selectedNos.size === 0) return;

    Swal.fire({
        icon: 'warning',
        title: 'Toplu silme',
        html: '<strong>' + selectedNos.size + '</strong> eslestirme silinecek. Devam edilsin mi?',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Iptal',
        confirmButtonColor: '#dc2626',
        reverseButtons: true
    }).then(function (result) {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'Siliniyor...',
            allowOutsideClick: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: apiUrl + '?action=deleteMatchCache',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ noList: Array.from(selectedNos) }),
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, confirmButtonColor: '#2563eb' });
                loadCache();
            }
        });
    });
}

function loadSets() {
    document.getElementById('setQueryState').textContent = 'Yukleniyor';
    document.getElementById('setQueryState').className = 'match-pill warning';

    $.getJSON(apiUrl + '?action=getSetDefinitions', function (res) {
        if (!res.success) return;

        allSets = res.sets || [];

        if (document.getElementById('searchSetInput').value.trim()) {
            filterSets();
        } else {
            renderSets(allSets);
        }

        updateSetStats(allSets);
        document.getElementById('setQueryState').textContent = 'Guncel';
        document.getElementById('setQueryState').className = 'match-pill success';
        setRefreshStamp();
    });
}

function renderSets(sets) {
    var container = document.getElementById('setListContainer');
    document.getElementById('setListMeta').textContent = sets.length + ' set';

    if (sets.length === 0) {
        container.innerHTML = '<div class="match-empty"><i class="bi bi-boxes"></i><h3>Henuz set tanimi yok</h3><p>Yeni set tanimi ile birden fazla urunu tek pazaryeri adina baglayabilirsin.</p></div>';
        return;
    }

    var html = '';
    sets.forEach(function (set) {
        var bilesenCount = set.icerikler ? set.icerikler.length : 0;
        var name = set.setAdi || set.excelSetAdi;

        html += '<div class="match-set-card" data-setno="' + set.no + '">';
        html += '<div class="match-set-header" onclick="toggleSetCard(' + set.no + ')">';
        html += '<div class="match-set-icon"><i class="bi bi-boxes"></i></div>';
        html += '<div class="match-set-copy">';
        html += '<h4>' + escapeHtml(name) + '</h4>';
        html += '<p>' + escapeHtml(set.excelSetAdi || '-') + '</p>';
        html += '</div>';
        html += '<div class="match-set-meta">';
        html += '<span class="soft-badge warning">' + bilesenCount + ' bilesen</span>';
        html += '<span class="small text-muted">' + escapeHtml(set.olusturmaTarihi || '-') + '</span>';
        html += '<button type="button" class="btn btn-outline-danger btn-sm" onclick="event.stopPropagation(); deleteSet(' + set.no + ')"><i class="bi bi-trash"></i></button>';
        html += '</div>';
        html += '</div>';

        html += '<div class="match-set-body" id="setBody_' + set.no + '">';
        if (set.icerikler && set.icerikler.length) {
            set.icerikler.forEach(function (icerik) {
                html += '<div class="match-set-item">';
                html += '<span class="soft-badge warning">' + escapeHtml(icerik.adet) + 'x</span>';
                html += '<strong>' + escapeHtml(icerik.urunAdi || ('Urun #' + icerik.urunNo)) + '</strong>';
                html += '</div>';
            });
        } else {
            html += '<div class="match-empty" style="min-height: 140px;"><i class="bi bi-boxes"></i><p>Bu set icin kayitli bilesen bulunmuyor.</p></div>';
        }
        html += '</div></div>';
    });

    container.innerHTML = html;
}

function updateSetStats(sets) {
    var totalBilesen = 0;
    sets.forEach(function (set) {
        totalBilesen += set.icerikler ? set.icerikler.length : 0;
    });

    document.getElementById('statSetTotal').textContent = sets.length;
    document.getElementById('statSetBilesen').textContent = totalBilesen;
    document.getElementById('summarySetsInline').textContent = sets.length;
    document.getElementById('summaryComponentsInline').textContent = totalBilesen;
}

function filterSets() {
    var query = document.getElementById('searchSetInput').value.toLowerCase().trim();
    if (!query) {
        renderSets(allSets);
        return;
    }

    renderSets(allSets.filter(function (set) {
        return (set.excelSetAdi || '').toLowerCase().indexOf(query) >= 0 ||
            ((set.setAdi || '').toLowerCase().indexOf(query) >= 0);
    }));
}

function toggleSetCard(setNo) {
    var body = document.getElementById('setBody_' + setNo);
    if (body) {
        body.classList.toggle('open');
    }
}

function toggleSetForm(forceState) {
    var form = document.getElementById('setFormContainer');
    var shouldOpen = typeof forceState === 'boolean' ? forceState : !form.classList.contains('is-open');
    form.classList.toggle('is-open', shouldOpen);

    if (shouldOpen) {
        pendingBilesenler = [];
        document.getElementById('setExcelName').value = '';
        document.getElementById('setShortName').value = '';
        document.getElementById('setBilesenAdet').value = '1';
        $('#setBilesenSelect').val('').trigger('change');
        renderBilesenList();
        document.getElementById('setExcelName').focus();
    }
}

function addBilesen() {
    var select = document.getElementById('setBilesenSelect');
    var urunNo = parseInt(select.value, 10);
    if (!urunNo) {
        Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Bilesen urunu secin.' });
        return;
    }

    var adet = parseInt(document.getElementById('setBilesenAdet').value, 10) || 1;
    var label = select.options[select.selectedIndex].text;

    var found = false;
    pendingBilesenler.forEach(function (bilesen) {
        if (bilesen.urunNo === urunNo) {
            bilesen.adet += adet;
            found = true;
        }
    });

    if (!found) {
        pendingBilesenler.push({ urunNo: urunNo, adet: adet, label: label });
    }

    renderBilesenList();
    $('#setBilesenSelect').val('').trigger('change');
    document.getElementById('setBilesenAdet').value = '1';
}

function removeBilesen(index) {
    pendingBilesenler.splice(index, 1);
    renderBilesenList();
}

function renderBilesenList() {
    var html = '';

    if (pendingBilesenler.length === 0) {
        html = '<div class="match-empty" style="min-height: 150px;"><i class="bi bi-boxes"></i><p>Henuz bilesen eklenmedi. Yukaridan urun secip listeyi olustur.</p></div>';
    } else {
        pendingBilesenler.forEach(function (bilesen, index) {
            html += '<div class="match-component-item">';
            html += '<span class="soft-badge warning">' + escapeHtml(bilesen.adet) + 'x</span>';
            html += '<div class="match-component-title">' + escapeHtml(bilesen.label) + '</div>';
            html += '<input type="number" class="form-control" value="' + bilesen.adet + '" min="1" max="99" onchange="updateBilesenAdet(' + index + ', this.value)">';
            html += '<button type="button" class="btn btn-outline-danger btn-sm" onclick="removeBilesen(' + index + ')"><i class="bi bi-x-circle"></i></button>';
            html += '</div>';
        });
    }

    document.getElementById('bilesenList').innerHTML = html;
    updateWorkspaceMeta();
}

function updateBilesenAdet(index, value) {
    pendingBilesenler[index].adet = parseInt(value, 10) || 1;
    renderBilesenList();
}

function saveSetDefinition() {
    var excelName = document.getElementById('setExcelName').value.trim();
    var shortName = document.getElementById('setShortName').value.trim();

    if (!excelName) {
        Swal.fire({ icon: 'warning', title: 'Uyari', text: 'Pazaryeri set adini girin.' });
        return;
    }

    if (pendingBilesenler.length === 0) {
        Swal.fire({ icon: 'warning', title: 'Uyari', text: 'En az bir bilesen eklemelisiniz.' });
        return;
    }

    Swal.fire({
        title: 'Kaydediliyor...',
        allowOutsideClick: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: apiUrl + '?action=addSetDefinition',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            excelSetAdi: excelName,
            setAdi: shortName,
            icerikler: pendingBilesenler.map(function (bilesen) {
                return { urunNo: bilesen.urunNo, adet: bilesen.adet };
            })
        }),
        dataType: 'json',
        success: function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                return;
            }

            Swal.fire({ icon: 'success', title: 'Set kaydedildi', text: res.message, confirmButtonColor: '#d97706' });
            toggleSetForm(false);
            pendingBilesenler = [];
            renderBilesenList();
            loadSets();
        },
        error: function (xhr) {
            Swal.fire({ icon: 'error', title: 'Sunucu hatasi', text: xhr.statusText });
        }
    });
}

function deleteSet(no) {
    Swal.fire({
        icon: 'warning',
        title: 'Seti sil',
        text: 'Bu set tanimini ve tum bilesenlerini silmek istediginize emin misiniz?',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Iptal',
        confirmButtonColor: '#dc2626',
        reverseButtons: true
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: apiUrl + '?action=deleteSetDefinition',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ no: no }),
            dataType: 'json',
            success: function (res) {
                if (!res.success) return;
                Swal.fire({ icon: 'success', title: 'Silindi', text: res.message, confirmButtonColor: '#d97706', timer: 1500 });
                loadSets();
            }
        });
    });
}

function updateWorkspaceMeta() {
    var activeLabel = activeTab === 'cache' ? 'Urun eslestirme' : 'Set yonetimi';
    document.getElementById('activeTabBadge').textContent = activeLabel;
    document.getElementById('activeTabMeta').textContent = activeLabel;
    document.getElementById('selectedBulkMeta').textContent = selectedNos.size + ' kayit';
    document.getElementById('pendingComponentsMeta').textContent = pendingBilesenler.length + ' oge';
}

function setRefreshStamp() {
    document.getElementById('summaryUpdatedMeta').textContent = new Date().toLocaleTimeString('tr-TR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text && text !== 0) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(text)));
    return div.innerHTML;
}
</script>
@endpush
