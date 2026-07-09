@extends('layouts.app')

@section('content')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<style>
    .nav-tabs { border-bottom: 2px solid var(--z-border); margin-bottom: 20px; }
    .nav-tabs .nav-link { color: var(--z-text-secondary) !important; font-weight: 600; cursor: pointer; border: none; background: transparent; padding: 10px 20px; transition: 0.2s; border-radius: 0; }
    .nav-tabs .nav-link:hover { color: var(--z-text) !important; }
    .nav-tabs .nav-link.active { color: var(--z-accent) !important; border-bottom: 2px solid var(--z-accent); background: transparent !important; }
    .nav-tabs .tab-count { font-size: .7rem; background: var(--z-accent); color: #fff; border-radius: 10px; padding: 1px 6px; margin-left: 4px; vertical-align: middle; }

    .filter-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
    .filter-area h4 { font-size: 1.1rem; font-weight: 700; color: var(--z-text); margin: 0; }
    .table-responsive { border-radius: var(--z-radius); border: 1px solid var(--z-border); overflow-x: auto; }

    /* Loading / empty / error */
    .db-empty-icon { font-size: 2rem; opacity: .25; display: block; margin-bottom: 6px; }

    /* Performance bar */
    .perf-bar-wrap { width: 56px; height: 6px; background: #e9ecef; border-radius: 3px; display:inline-block; vertical-align:middle; margin-right:4px; }
    .perf-bar { height: 100%; border-radius: 3px; }

    /* Task badge */
    .badge-task { font-size: .68rem; padding: 2px 7px; border-radius: 10px; }

    /* Readonly BOM path */
    input[readonly].bom-path { background: var(--z-surface, #f8f9fa); cursor: not-allowed; font-size: .78rem; color: #6c757d; }
    .bom-cell { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .bom-summary { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--z-border); border-radius: 999px; background: var(--z-surface, #f8f9fa); color: var(--z-text-secondary); padding: 3px 8px; font-size: .76rem; font-weight: 600; white-space: nowrap; }
    .bom-summary.empty { color: #94a3b8; font-weight: 500; }
    .bom-content { background: var(--z-surface); border: 1px solid var(--z-border); border-radius: 8px; padding: 10px 12px; max-height: 65vh; overflow: auto; }
    .bom-tree-list, .bom-tree-list ul { list-style: none; margin: 0; padding-left: 0; }
    .bom-tree-list ul { margin-left: 18px; padding-left: 16px; border-left: 1px dashed var(--z-border); }
    .bom-tree-list li { margin: 3px 0; }
    .bom-node-line { display: flex; align-items: flex-start; gap: 8px; padding: 4px 0; line-height: 1.35; }
    .bom-node-name { color: var(--z-text); font-weight: 600; overflow-wrap: anywhere; }
    .bom-node-line.root .bom-node-name { color: var(--z-accent); }
    .bom-qty-badge { flex: 0 0 auto; border: 1px solid #d4af37; border-radius: 6px; background: #fff7cc; color: #7c5a00; padding: 1px 6px; font-size: .72rem; font-weight: 700; }
    .bom-raw-path { margin-top: 10px; color: var(--z-text-secondary); font-family: monospace; font-size: .74rem; white-space: pre-wrap; overflow-wrap: anywhere; }

    .sortable-th { cursor: pointer; user-select: none; white-space: nowrap; }
    .sortable-th:hover { color: var(--z-accent); background: rgba(13, 148, 136, .06); }
    .sortable-th .sort-indicator { display: inline-block; min-width: 12px; margin-left: 4px; color: #94a3b8; font-size: .72rem; }
    .sortable-th.active .sort-indicator { color: var(--z-accent); }
</style>

<div class="panel-surface">
   <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 16px; color: var(--z-text);">Veritabanı Yönetimi</h2>

   <ul class="nav nav-tabs" id="veritabaniTabs">
       <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPersonel">👷 Personeller <span class="tab-count" id="badge-personel">…</span></button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBolum">🏢 Bölümler <span class="tab-count" id="badge-bolum">…</span></button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAraUrun">🧩 BOM Bileşenleri <span class="tab-count" id="badge-ara">…</span></button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUrun">📦 Nihai Ürünler <span class="tab-count" id="badge-urun">…</span></button></li>
   </ul>

   <div class="tab-content">
       <!-- PERSONEL -->
       <div class="tab-pane fade show active" id="tabPersonel">
             <div class="filter-area">
                 <h4 class="m-0">👷 Personel Listesi</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('personnel')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="openImportFile('filePersonel')" title="Excel veya CSV içeri aktar"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="filePersonel" class="d-none" accept=".xlsx,.xls,.csv" onchange="importTable(event, 'personnel')">
                     <input type="text" id="searchPersonnel" class="form-control" placeholder="Ara (Ad, Soyad, Mail)..." oninput="debouncedLoadData('personnel')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="id" onclick="sortTable('personnel', 'id')">No<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="name" onclick="sortTable('personnel', 'name')">Ad<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="surname" onclick="sortTable('personnel', 'surname')">Soyad<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="address" onclick="sortTable('personnel', 'address')">Adres<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="phone" onclick="sortTable('personnel', 'phone')">Telefon<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="email" onclick="sortTable('personnel', 'email')">Mail<span class="sort-indicator">↕</span></th>
                         <th>Şifre</th>
                         <th class="sortable-th" data-sort-module="personnel" data-sort-key="department_name" onclick="sortTable('personnel', 'department_name')">Bölüm<span class="sort-indicator">↕</span></th>
                         <th>İşlemler</th>
                     </tr></thead>
                     <tbody id="tbody-personnel"></tbody>
                     <tfoot>
                         <tr class="bg-light">
                             <td>#</td>
                             <td><input type="text" id="new-p-name" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-p-surname" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-p-address" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-p-phone" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-p-email" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-p-password" class="form-control form-control-sm" placeholder="Varsayılan: 123"></td>
                             <td><select id="new-p-dept" class="form-select form-select-sm dd-dept"></select></td>
                             <td><button class="btn btn-primary btn-sm" onclick="saveData('personnel', null)"><i class="bi bi-plus"></i> Ekle</button></td>
                         </tr>
                     </tfoot>
                 </table>
             </div>
       </div>

       <!-- BOLUM -->
       <div class="tab-pane fade" id="tabBolum">
             <div class="filter-area">
                 <h4 class="m-0">🏢 Bölüm Listesi</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('departments')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="openImportFile('fileBolum')" title="Excel veya CSV içeri aktar"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileBolum" class="d-none" accept=".xlsx,.xls,.csv" onchange="importTable(event, 'departments')">
                     <input type="text" id="searchDepartments" class="form-control" placeholder="Ara..." oninput="debouncedLoadData('departments')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr>
                         <th class="sortable-th" data-sort-module="departments" data-sort-key="id" onclick="sortTable('departments', 'id')">No<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="departments" data-sort-key="name" onclick="sortTable('departments', 'name')">Bölüm Adı<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th text-center" data-sort-module="departments" data-sort-key="personnel_count" onclick="sortTable('departments', 'personnel_count')">👥 Personel<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th text-center" data-sort-module="departments" data-sort-key="component_count" onclick="sortTable('departments', 'component_count')">🧩 Ara Ürün<span class="sort-indicator">↕</span></th>
                         <th>İşlemler</th>
                     </tr></thead>
                      <tbody id="tbody-departments"></tbody>
                      <tfoot>
                          <tr class="bg-light">
                              <td>#</td>
                              <td><input type="text" id="new-d-name" class="form-control form-control-sm" placeholder="Bölüm adı..."></td>
                              <td class="text-center text-muted small">—</td>
                              <td class="text-center text-muted small">—</td>
                              <td><button class="btn btn-primary btn-sm" onclick="saveData('departments', null)"><i class="bi bi-plus"></i> Ekle</button></td>
                          </tr>
                      </tfoot>
                 </table>
             </div>
       </div>

       <!-- ARA URUN -->
       <div class="tab-pane fade" id="tabAraUrun">
             <div class="filter-area">
                 <h4 class="m-0">🧩 BOM Bileşenleri</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('components')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="openImportFile('fileAra')" title="Excel veya CSV içeri aktar"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileAra" class="d-none" accept=".xlsx,.xls,.csv" onchange="importTable(event, 'components')">
                     <input type="text" id="searchComponents" class="form-control" placeholder="Ara ürün veya bileşen ara..." oninput="debouncedLoadData('components')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="id" onclick="sortTable('components', 'id')">No<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="name" onclick="sortTable('components', 'name')">Bileşen / Ara Ürün Adı<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="performance_score" onclick="sortTable('components', 'performance_score')">Performans<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="min_quantity" onclick="sortTable('components', 'min_quantity')">Min Adet<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="type" onclick="sortTable('components', 'type')">Tür<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="path" onclick="sortTable('components', 'path')">BOM<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="components" data-sort-key="department_name" onclick="sortTable('components', 'department_name')">Bölüm<span class="sort-indicator">↕</span></th>
                         <th>İşlemler</th>
                     </tr></thead>
                     <tbody id="tbody-components"></tbody>
                     <tfoot>
                         <tr class="bg-light">
                             <td>#</td>
                             <td><input type="text" id="new-c-name" class="form-control form-control-sm"></td>
                             <td><input type="number" id="new-c-perf" class="form-control form-control-sm" value="0"></td>
                             <td><input type="number" id="new-c-min" class="form-control form-control-sm" value="0"></td>
                             <td>
	                                 <select id="new-c-type" class="form-select form-select-sm">
	                                     <option value="">Seçiniz</option>
	                                     <option value="Nihayi Ürün">Nihai Ürün</option>
	                                     <option value="Ara Mamül">Ara Mamül</option>
	                                     <option value="Ham Madde">Ham Madde</option>
	                                 </select>
                             </td>
                             <td><input type="text" id="new-c-path" class="form-control form-control-sm bom-path" readonly placeholder="Türetme sayfasından yönetilir" title="BOM yolu Ürün Türetme sayfasından yönetilmelidir"></td>
                             <td><select id="new-c-dept" class="form-select form-select-sm dd-dept"></select></td>
                             <td><button class="btn btn-primary btn-sm" onclick="saveData('components', null)"><i class="bi bi-plus"></i> Ekle</button></td>
                         </tr>
                     </tfoot>
                 </table>
             </div>
       </div>

       <!-- URUN -->
       <div class="tab-pane fade" id="tabUrun">
             <div class="filter-area">
                 <h4 class="m-0">📦 Nihai Ürünler</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('products')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="openImportFile('fileUrun')" title="Excel veya CSV içeri aktar"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileUrun" class="d-none" accept=".xlsx,.xls,.csv" onchange="importTable(event, 'products')">
                     <input type="text" id="searchProducts" class="form-control" placeholder="Nihai ürün ara..." oninput="debouncedLoadData('products')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr>
                         <th class="sortable-th" data-sort-module="products" data-sort-key="id" onclick="sortTable('products', 'id')">No<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="products" data-sort-key="name" onclick="sortTable('products', 'name')">Nihai Ürün Adı<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="products" data-sort-key="path" onclick="sortTable('products', 'path')">BOM<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="products" data-sort-key="system_name" onclick="sortTable('products', 'system_name')">Sistem Adı<span class="sort-indicator">↕</span></th>
                         <th class="sortable-th" data-sort-module="products" data-sort-key="system_code" onclick="sortTable('products', 'system_code')">Sistem Kodu<span class="sort-indicator">↕</span></th>
                         <th>İşlemler</th>
                     </tr></thead>
                     <tbody id="tbody-products"></tbody>
                     <tfoot>
                         <tr class="bg-light">
                             <td>#</td>
                             <td><input type="text" id="new-u-name" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-u-path" class="form-control form-control-sm bom-path" readonly placeholder="Türetme sayfasından yönetilir" title="BOM yolu Ürün Türetme sayfasından yönetilmelidir"></td>
                             <td><input type="text" id="new-u-sysname" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-u-syscode" class="form-control form-control-sm"></td>
                             <td><button class="btn btn-primary btn-sm" onclick="saveData('products', null)"><i class="bi bi-plus"></i> Ekle</button></td>
                         </tr>
                     </tfoot>
                 </table>
             </div>
       </div>
   </div>
</div>

<!-- BOM Modal -->
<div class="modal fade" id="bomModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-diagram-3"></i> BOM Ağacı</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="bomLoading" class="text-center d-none py-3"><div class="spinner-border text-primary"></div><div class="mt-2 text-muted small">Ağaç Yükleniyor...</div></div>
        <div id="bomContent" class="bom-content small"></div>
      </div>
    </div>
  </div>
</div>

<script>
    const state = { personnel: [], departments: [], components: [], products: [] };
    let departmentOptions = [];
    const editingId = { personnel: null, departments: null, components: null, products: null };
    const pageState = { personnel: 1, departments: 1, components: 1, products: 1 };
    const sortState = {
        personnel: { key: null, direction: 'asc' },
        departments: { key: null, direction: 'asc' },
        components: { key: null, direction: 'asc' },
        products: { key: null, direction: 'asc' },
    };
    const numericSortKeys = new Set(['id', 'active_tasks', 'personnel_count', 'component_count', 'performance_score', 'min_quantity']);
    const tabSelectorByModule = {
        personnel: '#tabPersonel',
        departments: '#tabBolum',
        components: '#tabAraUrun',
        products: '#tabUrun',
    };
    const ITEMS_PER_PAGE = 25;

    if (!window.Swal) {
        window.Swal = {
            fire: (...args) => {
                const options = typeof args[0] === 'object'
                    ? args[0]
                    : { title: args[0], text: args[1], icon: args[2] };

                const message = [options.title, options.text].filter(Boolean).join('\n');
                if (options.showCancelButton) {
                    return Promise.resolve({ isConfirmed: window.confirm(message || 'Devam edilsin mi?') });
                }

                if (!options.timer || options.showConfirmButton !== false) {
                    window.alert(message || 'İşlem tamamlandı.');
                }

                return Promise.resolve({ isConfirmed: true });
            },
            showLoading: () => {},
            close: () => {},
        };
    }

    function debounce(func, timeout = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => { func.apply(this, args); }, timeout);
        };
    }
    const debouncedLoadData = debounce((module) => {
        pageState[module] = 1;
        loadData(module);
    }, 300);

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    function normalizeDepartmentOptions(rows) {
        return (Array.isArray(rows) ? rows : [])
            .map((department) => ({
                id: department.id ?? department.bolum_no ?? department.No ?? '',
                name: department.name ?? department.department_name ?? department.BolumAdi ?? '',
            }))
            .filter((department) => String(department.id).trim() !== '' && String(department.name).trim() !== '');
    }

    function departmentOptionRows() {
        return departmentOptions.length > 0 ? departmentOptions : normalizeDepartmentOptions(state.departments);
    }

    function renderDepartmentOptions(selectedValue = '', selectedLabel = '') {
        const selected = selectedValue === null || selectedValue === undefined ? '' : String(selectedValue);
        const rows = departmentOptionRows();
        const hasSelected = rows.some((department) => String(department.id ?? '') === selected);
        const selectedFallback = selected && !hasSelected
            ? `<option value="${escapeAttr(selected)}" selected>${escapeHtml(selectedLabel || ('Bölüm #' + selected))}</option>`
            : '';

        return '<option value="">Bölüm Seç</option>' + selectedFallback + rows.map((department) => {
            const value = department.id === null || department.id === undefined ? '' : String(department.id);
            const isSelected = value === selected ? ' selected' : '';
            return `<option value="${escapeAttr(value)}"${isSelected}>${escapeHtml(department.name)}</option>`;
        }).join('');
    }

    function fillDepartmentSelect(select, selectedValue = '', selectedLabel = '') {
        if (!select) return;
        select.innerHTML = renderDepartmentOptions(selectedValue, selectedLabel);
    }

    function refreshDepartmentSelects() {
        document.querySelectorAll('.dd-dept').forEach((select) => {
            const oldValue = select.value;
            fillDepartmentSelect(select, oldValue);
        });
    }

    async function loadDepartmentOptions(force = false) {
        if (!force && departmentOptions.length > 0) {
            return departmentOptions;
        }

        try {
            const res = await fetch('/api/database/departments', { headers: { 'Accept': 'application/json' } });
            const json = await res.json();
            departmentOptions = normalizeDepartmentOptions(json.data);
            refreshDepartmentSelects();
        } catch (error) {
            console.error('Department options could not be loaded', error);
        }

        return departmentOptions;
    }

    function getSortValue(item, key) {
        const rawValue = item?.[key];
        if (numericSortKeys.has(key)) {
            const normalized = String(rawValue ?? '').replace(',', '.').trim();
            const numberValue = Number(normalized);
            return Number.isFinite(numberValue) ? numberValue : Number.NEGATIVE_INFINITY;
        }

        return String(rawValue ?? '').trim().toLocaleLowerCase('tr-TR');
    }

    function sortedRows(module) {
        const rows = [...(state[module] || [])];
        const currentSort = sortState[module];
        if (!currentSort?.key) return rows;

        return rows.sort((a, b) => {
            const aValue = getSortValue(a, currentSort.key);
            const bValue = getSortValue(b, currentSort.key);
            let result;

            if (numericSortKeys.has(currentSort.key)) {
                result = aValue - bValue;
            } else {
                result = aValue.localeCompare(bValue, 'tr', { sensitivity: 'base', numeric: true });
            }

            if (result === 0) {
                result = (Number(a.id) || 0) - (Number(b.id) || 0);
            }

            return currentSort.direction === 'desc' ? -result : result;
        });
    }

    function sortTable(module, key) {
        if (!sortState[module]) return;

        const current = sortState[module];
        sortState[module] = {
            key,
            direction: current.key === key && current.direction === 'asc' ? 'desc' : 'asc',
        };

        pageState[module] = 1;
        editingId[module] = null;
        renderData(module);
    }

    function updateSortIndicators(module) {
        const current = sortState[module];
        document.querySelectorAll(`[data-sort-module="${module}"]`).forEach((header) => {
            const isActive = current && header.dataset.sortKey === current.key;
            const indicator = header.querySelector('.sort-indicator');

            header.classList.toggle('active', isActive);
            if (indicator) {
                indicator.textContent = isActive
                    ? (current.direction === 'asc' ? '↑' : '↓')
                    : '↕';
            }
        });
    }

    function countBomSegments(path) {
        return String(path || '').split(':').filter(part => part.trim() !== '').length;
    }

    function renderBomCell(id, module, path) {
        const count = countBomSegments(path);
        if (count === 0) {
            return '<span class="bom-summary empty"><i class="bi bi-diagram-3"></i> BOM yok</span>';
        }

        const label = count === 1 ? '1 bağlantı' : `${count} bağlantı`;
        return `<span class="bom-cell">
            <span class="bom-summary" title="${escapeAttr(path)}"><i class="bi bi-diagram-3"></i> ${label}</span>
            <button class="btn btn-sm btn-outline-info py-0 px-1" onclick="viewBomTree(${id}, '${module}')" title="BOM Ağacını Gör"><i class="bi bi-eye"></i></button>
        </span>`;
    }

    function renderBomTree(edges, rawNamePath = '') {
        if (!Array.isArray(edges) || edges.length === 0) {
            return rawNamePath
                ? `<div class="bom-raw-path">${escapeHtml(rawNamePath)}</div>`
                : '<div class="text-muted text-center py-2">BOM yolu bulunamadı veya boş.</div>';
        }

        const childrenByTarget = new Map();
        const sourceIds = new Set();
        const targetIds = new Set();
        const namesById = new Map();

        edges.forEach(edge => {
            const sourceId = String(edge.source_id || edge.source_name || '');
            const targetId = String(edge.target_id || edge.target_name || '');
            if (!sourceId || !targetId) return;

            sourceIds.add(sourceId);
            targetIds.add(targetId);
            namesById.set(sourceId, edge.source_name || sourceId);
            namesById.set(targetId, edge.target_name || targetId);
            if (!childrenByTarget.has(targetId)) childrenByTarget.set(targetId, []);
            childrenByTarget.get(targetId).push({ id: sourceId, quantity: edge.quantity || 1 });
        });

        const roots = [...targetIds].filter(id => !sourceIds.has(id));
        const treeRoots = roots.length > 0 ? roots : [...targetIds].slice(-1);
        const html = treeRoots.map(root => renderBomBranch(root, childrenByTarget, namesById, true, new Set())).join('');
        const raw = rawNamePath ? `<details class="bom-raw-path"><summary>Teknik yol</summary>${escapeHtml(rawNamePath)}</details>` : '';

        return `<ul class="bom-tree-list">${html}</ul>${raw}`;
    }

    function renderBomBranch(id, childrenByTarget, namesById, isRoot = false, seen = new Set(), quantity = null) {
        const children = childrenByTarget.get(id) || [];
        const name = namesById.get(id) || id;
        const lineClass = isRoot ? 'bom-node-line root' : 'bom-node-line';
        const qty = quantity === null ? '' : `<span class="bom-qty-badge">x${escapeHtml(quantity)}</span>`;
        const nodeLine = `<div class="${lineClass}">${qty}<i class="bi ${isRoot ? 'bi-box-seam' : 'bi-arrow-return-right'}"></i><span class="bom-node-name">${escapeHtml(name)}</span></div>`;

        if (seen.has(id) || children.length === 0) {
            return `<li>${nodeLine}</li>`;
        }

        const nextSeen = new Set(seen);
        nextSeen.add(id);
        const childItems = children
            .map(child => renderBomBranch(child.id, childrenByTarget, namesById, false, nextSeen, child.quantity))
            .join('');

        return `<li>${nodeLine}<ul>${childItems}</ul></li>`;
    }

    async function loadData(module) {
        let q = document.getElementById('search'+module.charAt(0).toUpperCase() + module.slice(1))?.value || '';
        try {
            let res = await fetch(`/api/database/${module}?search=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json' }
            });
            let json = await res.json();
            state[module] = Array.isArray(json.data) ? json.data : [];
            if (module === 'departments' && q.trim() === '') {
                departmentOptions = normalizeDepartmentOptions(state.departments);
            }
            if ((module === 'personnel' || module === 'components') && departmentOptions.length === 0) {
                await loadDepartmentOptions();
            }

            // Güncel sayıları badge'lere yaz
            const badgeEl = document.getElementById('badge-' + module.replace('components', 'ara').replace('products', 'urun').replace('departments', 'bolum'));
            if(badgeEl) badgeEl.textContent = state[module].length;

            if(module === 'departments') {
                refreshDepartmentSelects();
            }
            renderData(module);
        } catch(e) {
            console.error("Error loading " + module, e);
            Swal.fire('Hata', 'Veriler yüklenemedi. Sayfayı yenileyip tekrar deneyin.', 'error');
        }
    }

    function renderData(module) {
        const tbody = document.getElementById(`tbody-${module}`);
        tbody.innerHTML = '';
        updateSortIndicators(module);

        const rows = sortedRows(module);

        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted"><i class="bi bi-inbox db-empty-icon"></i>Kayıt bulunamadı.</td></tr>`;
            return;
        }

        let totalItems = rows.length;
        let totalPages = Math.ceil(totalItems / ITEMS_PER_PAGE);
        if (pageState[module] > totalPages) pageState[module] = totalPages;
        if (pageState[module] < 1) pageState[module] = 1;

        let startIdx = (pageState[module] - 1) * ITEMS_PER_PAGE;
        let paginatedData = rows.slice(startIdx, startIdx + ITEMS_PER_PAGE);

        paginatedData.forEach(item => {
            const tr = document.createElement('tr');
            if(String(editingId[module]) === String(item.id)) {
                // EDIT MODE
                if(module === 'personnel') {
                    tr.innerHTML = `
                        <td>${escapeHtml(item.id)}</td>
                        <td><input type="text" id="edit-p-name-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.name)}"></td>
                        <td><input type="text" id="edit-p-surname-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.surname)}"></td>
                        <td><input type="text" id="edit-p-address-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.address)}"></td>
                        <td><input type="text" id="edit-p-phone-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.phone)}"></td>
                        <td><input type="text" id="edit-p-email-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.email)}"></td>
                        <td><input type="text" id="edit-p-password-${item.id}" class="form-control form-control-sm" placeholder="Yeni (Boş=Değişmez)"></td>
                        <td><select id="edit-p-dept-${item.id}" class="form-select form-select-sm dd-dept"></select></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                } else if(module === 'departments') {
                    tr.innerHTML = `
                        <td>${escapeHtml(item.id)}</td>
                        <td><input type="text" id="edit-d-name-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.name)}"></td>
                        <td class="text-center text-muted small">—</td>
                        <td class="text-center text-muted small">—</td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                    } else if(module === 'components') {
                    tr.innerHTML = `
                        <td>${escapeHtml(item.id)}</td>
                        <td><input type="text" id="edit-c-name-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.name)}"></td>
                        <td><input type="number" id="edit-c-perf-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.performance_score)}"></td>
                        <td><input type="number" id="edit-c-min-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.min_quantity)}"></td>
                        <td>
	                            <select id="edit-c-type-${item.id}" class="form-select form-select-sm">
	                                <option value="">Seçiniz</option>
	                                <option value="Nihayi Ürün" ${item.type === 'Nihayi Ürün' || item.type === 'Nihai Ürün' ? 'selected' : ''}>Nihai Ürün</option>
	                                <option value="Ara Mamül" ${item.type === 'Ara Mamül' ? 'selected' : ''}>Ara Mamül</option>
	                                <option value="Ham Madde" ${item.type === 'Ham Madde' ? 'selected' : ''}>Ham Madde</option>
	                            </select>
                        </td>
                        <td><input type="text" id="edit-c-path-${item.id}" class="form-control form-control-sm bom-path" value="${escapeAttr(item.path)}" readonly title="BOM yolu Ürün Türetme sayfasından yönetilmelidir"></td>
                        <td><select id="edit-c-dept-${item.id}" class="form-select form-select-sm dd-dept"></select></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                } else if(module === 'products') {
                    tr.innerHTML = `
                        <td>${escapeHtml(item.id)}</td>
                        <td><input type="text" id="edit-u-name-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.name)}"></td>
                        <td><input type="text" id="edit-u-path-${item.id}" class="form-control form-control-sm bom-path" value="${escapeAttr(item.path)}" readonly title="BOM yolu Ürün Türetme sayfasından yönetilmelidir"></td>
                        <td><input type="text" id="edit-u-sysname-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.system_name)}"></td>
                        <td><input type="text" id="edit-u-syscode-${item.id}" class="form-control form-control-sm" value="${escapeAttr(item.system_code)}"></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                }
            } else {
                // VIEW MODE
                if(module === 'personnel') {
                    let taskBadge = item.active_tasks > 0 ? `<span class="badge bg-danger badge-task ms-1" title="Aktif Görev"><i class="bi bi-exclamation-circle"></i> ${item.active_tasks}</span>` : '';
                    tr.innerHTML = `<td>${escapeHtml(item.id)}</td><td>${escapeHtml(item.name)}</td><td>${escapeHtml(item.surname || '')}</td><td>${escapeHtml(item.address || '')}</td><td>${escapeHtml(item.phone || '')}</td><td>${escapeHtml(item.email)}</td><td>***</td><td>${escapeHtml(item.department_name)} ${taskBadge}</td>`;
                } else if(module === 'departments') {
                    tr.innerHTML = `<td>${escapeHtml(item.id)}</td><td>${escapeHtml(item.name)}</td><td class="text-center"><span class="badge bg-light text-dark border"><i class="bi bi-people"></i> ${escapeHtml(item.personnel_count)}</span></td><td class="text-center"><span class="badge bg-light text-dark border"><i class="bi bi-puzzle"></i> ${escapeHtml(item.component_count)}</span></td>`;
                } else if(module === 'components') {
                    let perf = Math.min(100, Math.max(0, item.performance_score || 0));
                    let pColor = perf < 50 ? 'bg-danger' : (perf < 80 ? 'bg-warning' : 'bg-success');
                    let pBar = `<div class="perf-bar-wrap"><div class="perf-bar ${pColor}" style="width:${perf}%"></div></div> ${escapeHtml(perf)}`;
	                    tr.innerHTML = `<td>${escapeHtml(item.id)}</td><td>${escapeHtml(item.name)}</td><td>${pBar}</td><td>${escapeHtml(item.min_quantity)}</td><td>${escapeHtml(item.type || '-')}</td><td>${renderBomCell(item.id, 'components', item.path)}</td><td>${escapeHtml(item.department_name)}</td>`;
                } else if(module === 'products') {
                    tr.innerHTML = `<td>${escapeHtml(item.id)}</td><td>${escapeHtml(item.name)}</td><td>${renderBomCell(item.id, 'products', item.path)}</td><td>${escapeHtml(item.system_name)}</td><td>${escapeHtml(item.system_code)}</td>`;
                }

                tr.innerHTML += `
                    <td>
                        <button class="btn btn-warning btn-sm btn-icon" onclick="setEdit('${module}', ${item.id})" title="Düzenle"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteData('${module}', ${item.id})" title="Sil"><i class="bi bi-trash3"></i></button>
                    </td>
                `;
            }
            tbody.appendChild(tr);
        });

        // Pagination row
        if (totalPages > 1) {
            const trPage = document.createElement('tr');
            trPage.innerHTML = `<td colspan="10" class="text-center py-2 bg-light">
                <div class="d-flex justify-content-between align-items-center px-2">
                    <small class="text-muted">Toplam ${totalItems} kayıt. (Sayfa ${pageState[module]} / ${totalPages})</small>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary" onclick="changePage('${module}', -1)" ${pageState[module] === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i> Önceki</button>
                        <button class="btn btn-outline-secondary" onclick="changePage('${module}', 1)" ${pageState[module] === totalPages ? 'disabled' : ''}>Sonraki <i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </td>`;
            tbody.appendChild(trPage);
        }

        // Set select values in Edit Mode
        if(editingId[module]) {
            let el = state[module].find(x => String(x.id) === String(editingId[module]));
            if (!el) return;

            let s = document.getElementById(`edit-${module.charAt(0)}-dept-${el.id}`);
            if(s) {
                fillDepartmentSelect(s, el.department_id, el.department_name);
            }
        }
    }

    async function setEdit(module, id) {
        editingId[module] = id;
        if ((module === 'personnel' || module === 'components') && departmentOptions.length === 0) {
            await loadDepartmentOptions();
        }
        renderData(module);
    }
    function cancelEdit(module) {
        editingId[module] = null;
        renderData(module);
    }

    function changePage(module, dir) {
        pageState[module] += dir;
        renderData(module);
    }

    async function viewBomTree(id, module = 'components') {
        const modal = new bootstrap.Modal(document.getElementById('bomModal'));
        modal.show();

        const loader = document.getElementById('bomLoading');
        const content = document.getElementById('bomContent');

        loader.classList.remove('d-none');
        content.innerHTML = '';

        try {
            let endpoint = module === 'products'
                ? `/api/database/products/${id}/bom-path-names`
                : `/api/database/components/${id}/bom-path-names`;

            let res = await fetch(endpoint);
            let json = await res.json();

            loader.classList.add('d-none');

            if (json.namePath || (Array.isArray(json.edges) && json.edges.length > 0)) {
                content.innerHTML = renderBomTree(json.edges, json.namePath || '');
            } else {
                content.innerHTML = '<div class="text-muted text-center py-2">BOM yolu bulunamadı veya boş.</div>';
            }
        } catch(e) {
            loader.classList.add('d-none');
            content.innerHTML = '<div class="text-danger text-center">BOM bilgisi alınırken bir hata oluştu.</div>';
        }
    }

    async function saveData(module, id) {
        let url = id ? `/api/database/${module}/${id}` : `/api/database/${module}`;
        let method = id ? 'PUT' : 'POST';
        let payload = {};

        if(module === 'personnel') {
            payload = {
                name: document.getElementById(id ? `edit-p-name-${id}` : 'new-p-name').value,
                surname: document.getElementById(id ? `edit-p-surname-${id}` : 'new-p-surname').value,
                address: document.getElementById(id ? `edit-p-address-${id}` : 'new-p-address').value,
                phone: document.getElementById(id ? `edit-p-phone-${id}` : 'new-p-phone').value,
                email: document.getElementById(id ? `edit-p-email-${id}` : 'new-p-email').value,
                new_password: document.getElementById(id ? `edit-p-password-${id}` : 'new-p-password').value,
                department_id: document.getElementById(id ? `edit-p-dept-${id}` : 'new-p-dept').value
            };
        } else if(module === 'departments') {
            payload = { name: document.getElementById(id ? `edit-d-name-${id}` : 'new-d-name').value };
        } else if(module === 'components') {
            payload = {
                name: document.getElementById(id ? `edit-c-name-${id}` : 'new-c-name').value,
                performance_score: document.getElementById(id ? `edit-c-perf-${id}` : 'new-c-perf').value,
                min_quantity: document.getElementById(id ? `edit-c-min-${id}` : 'new-c-min').value,
                type: document.getElementById(id ? `edit-c-type-${id}` : 'new-c-type').value,
                path: document.getElementById(id ? `edit-c-path-${id}` : 'new-c-path').value,
                department_id: document.getElementById(id ? `edit-c-dept-${id}` : 'new-c-dept').value
            };
        } else if(module === 'products') {
            payload = {
                name: document.getElementById(id ? `edit-u-name-${id}` : 'new-u-name').value,
                path: document.getElementById(id ? `edit-u-path-${id}` : 'new-u-path').value,
                system_name: document.getElementById(id ? `edit-u-sysname-${id}` : 'new-u-sysname').value,
                system_code: document.getElementById(id ? `edit-u-syscode-${id}` : 'new-u-syscode').value
            };
        }

        try {
            let res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            });
            if(res.ok) {
                Swal.fire({icon: 'success', title: id ? 'Güncellendi' : 'Eklendi', timer: 1500, showConfirmButton: false});
                editingId[module] = null;
                // clear inputs if new
                if(!id) {
                    const tabSelector = tabSelectorByModule[module] || '';
                    const inputs = document.querySelectorAll(`${tabSelector} tfoot input, ${tabSelector} tfoot select`);
                    inputs.forEach(i => i.value = '');
                }
                loadData(module);
            } else {
                let err = await res.json().catch(() => ({}));
                let validationMessage = err.errors ? Object.values(err.errors).flat()[0] : null;
                Swal.fire('Hata', validationMessage || err.message || 'Veri gönderilemedi', 'error');
            }
        } catch(e) {
            console.error(e);
            Swal.fire('Hata', 'İşlem sırasında bağlantı hatası oluştu.', 'error');
        }
    }

    async function deleteData(module, id) {
        Swal.fire({
            title: 'Emin misiniz?',
            text: 'Bu kaydı silmek geri alınamaz!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'İptal'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    let res = await fetch(`/api/database/${module}/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    if(res.ok) {
                        Swal.fire({icon: 'success', title: 'Silindi', timer: 1500, showConfirmButton: false});
                        loadData(module);
                    } else {
                        let err = await res.json();
                        Swal.fire('Hata', err.message, 'error');
                    }
                } catch(e) {
                    console.error(e);
                    Swal.fire('Hata', 'Silme işlemi sırasında bağlantı hatası oluştu.', 'error');
                }
            }
        });
    }

    // EXPORT
    function exportTable(module) {
        if (typeof XLSX === 'undefined') {
            Swal.fire('Hata', 'Excel dışa aktarma kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.', 'error');
            return;
        }

        const data = state[module].map(item => {
            const row = {...item};
            delete row.password;
            delete row.created_at;
            delete row.updated_at;
            return row;
        });
        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Data");
        XLSX.writeFile(wb, `${module}_export.xlsx`);
    }

    const importLabels = {
        personnel: 'Personeller',
        departments: 'Bölümler',
        components: 'BOM Bileşenleri',
        products: 'Nihai Ürünler'
    };

    function openImportFile(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        input.value = '';
        input.click();
    }

    function readWorkbookRows(file) {
        return new Promise((resolve, reject) => {
            if (typeof XLSX === 'undefined') {
                reject(new Error('Excel okuma kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.'));
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const rows = XLSX.utils.sheet_to_json(firstSheet, { defval: '', raw: false })
                        .filter(row => Object.values(row).some(value => String(value ?? '').trim() !== ''));
                    resolve(rows);
                } catch (error) {
                    reject(error);
                }
            };
            reader.onerror = () => reject(new Error('Dosya okunamadı.'));
            reader.readAsArrayBuffer(file);
        });
    }

    async function importTable(event, module) {
        const file = event.target.files[0];
        if (!file) return;

        try {
            const rows = await readWorkbookRows(file);
            if (rows.length === 0) {
                Swal.fire('Boş Dosya', 'İçeri aktarılacak satır bulunamadı.', 'warning');
                return;
            }

            const confirm = await Swal.fire({
                icon: 'question',
                title: 'İçeri Aktarılsın mı?',
                html: `<b>${escapeHtml(importLabels[module] || module)}</b> için <b>${rows.length}</b> satır okunacak.<br>` +
                      `<small class="text-muted">Aynı No/id varsa kayıt güncellenir, yoksa yeni kayıt eklenir.</small>`,
                showCancelButton: true,
                confirmButtonText: 'İçeri Aktar',
                cancelButtonText: 'İptal'
            });

            if (!confirm.isConfirmed) return;

            Swal.fire({
                title: 'İçeri aktarılıyor...',
                text: 'Kayıtlar veritabanına yazılıyor.',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const res = await fetch(`/api/database/${module}/import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ rows })
            });

            const json = await res.json().catch(() => ({}));
            if (!res.ok || json.success === false) {
                throw new Error(json.message || 'İçeri aktarma tamamlanamadı.');
            }

            const errorHtml = Array.isArray(json.errors) && json.errors.length > 0
                ? `<hr><small class="text-muted">${json.errors.map(escapeHtml).join('<br>')}</small>`
                : '';

            await Swal.fire({
                icon: 'success',
                title: 'İçeri Aktarıldı',
                html: `<b>${json.inserted || 0}</b> eklendi, <b>${json.updated || 0}</b> güncellendi, <b>${json.skipped || 0}</b> atlandı.${errorHtml}`,
                confirmButtonText: 'Tamam'
            });

            pageState[module] = 1;
            await loadData(module);
            if (module !== 'departments') {
                await loadData('departments');
            }
        } catch (error) {
            Swal.fire('Hata', error.message || 'Dosya içeri aktarılamadı.', 'error');
        } finally {
            event.target.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Lazy loading: sadece aktif sekmenin verisini yükle
        const tabModuleMap = {
            'tabPersonel': 'personnel',
            'tabBolum':    'departments',
            'tabAraUrun':  'components',
            'tabUrun':     'products'
        };

        // İlk açılışta departments ve aktif sekme (personnel) yükle
        loadData('departments').then(() => {
            loadData('personnel');
        });

        // Sekme değişince sadece o sekmenin verisini yükle
        document.querySelectorAll('#veritabaniTabs [data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', e => {
                const targetId = e.target.getAttribute('data-bs-target')?.replace('#', '');
                const module   = tabModuleMap[targetId];
                if (module && module !== 'departments') {
                    // departments zaten yüklendi, diğerlerini her sekme açılışında tazele
                    loadData(module);
                }
            });
        });
    });
</script>
@endsection
