@extends('layouts.app')

@section('content')
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
<style>
    /* Database Admin - Minimal UI */
    .nav-tabs { border-bottom: 2px solid var(--z-border); margin-bottom: 20px; }
    .nav-tabs .nav-link { color: var(--z-text-secondary) !important; font-weight: 600; cursor: pointer; border: none; background: transparent; padding: 10px 20px; transition: 0.2s; border-radius: 0; }
    .nav-tabs .nav-link:hover { color: var(--z-text) !important; }
    .nav-tabs .nav-link.active { color: var(--z-accent) !important; border-bottom: 2px solid var(--z-accent); background: transparent !important; }
    
    .filter-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
    .filter-area h4 { font-size: 1.1rem; font-weight: 700; color: var(--z-text); margin: 0; }
    
    .table-responsive { border-radius: var(--z-radius); border: 1px solid var(--z-border); overflow-x: auto; }
</style>

<div class="panel-surface">
   <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 16px; color: var(--z-text);">Veritabanı Yönetimi</h2>
   
   <ul class="nav nav-tabs" id="veritabaniTabs">
       <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPersonel">👷 Personeller</button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBolum">🏢 Bölümler</button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAraUrun">🧩 Ara Ürünler</button></li>
       <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabUrun">📦 Ürünler</button></li>
   </ul>

   <div class="tab-content">
       <!-- PERSONEL -->
       <div class="tab-pane fade show active" id="tabPersonel">
             <div class="filter-area">
                 <h4 class="m-0">👷 Personel Listesi</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('personnel')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="document.getElementById('filePersonel').click()"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="filePersonel" class="d-none" accept=".xlsx" onchange="importTable(event, 'personnel')">
                     <input type="text" id="searchPersonnel" class="form-control" placeholder="Ara (Ad, Soyad, Mail)..." oninput="loadData('personnel')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr><th>No</th><th>Ad</th><th>Soyad</th><th>Adres</th><th>Telefon</th><th>Mail</th><th>Şifre</th><th>Bölüm</th><th>İşlemler</th></tr></thead>
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
                     <button class="btn btn-outline-primary btn-sm" onclick="document.getElementById('fileBolum').click()"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileBolum" class="d-none" accept=".xlsx" onchange="importTable(event, 'departments')">
                     <input type="text" id="searchDepartments" class="form-control" placeholder="Ara..." oninput="loadData('departments')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr><th>No</th><th>Bölüm Adı</th><th>İşlemler</th></tr></thead>
                     <tbody id="tbody-departments"></tbody>
                     <tfoot>
                         <tr class="bg-light">
                             <td>#</td>
                             <td><input type="text" id="new-d-name" class="form-control form-control-sm"></td>
                             <td><button class="btn btn-primary btn-sm" onclick="saveData('departments', null)"><i class="bi bi-plus"></i> Ekle</button></td>
                         </tr>
                     </tfoot>
                 </table>
             </div>
       </div>

       <!-- ARA URUN -->
       <div class="tab-pane fade" id="tabAraUrun">
             <div class="filter-area">
                 <h4 class="m-0">🧩 Ara Ürünler</h4>
                 <div class="d-flex gap-2">
                     <button class="btn btn-outline-success btn-sm" onclick="exportTable('components')"><i class="bi bi-file-earmark-excel"></i> Dışarı Aktar</button>
                     <button class="btn btn-outline-primary btn-sm" onclick="document.getElementById('fileAra').click()"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileAra" class="d-none" accept=".xlsx" onchange="importTable(event, 'components')">
                     <input type="text" id="searchComponents" class="form-control" placeholder="Ara..." oninput="loadData('components')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr><th>No</th><th>Ara Ürün Adı</th><th>Performans</th><th>Min Adet</th><th>Tür</th><th>Yol (BOM)</th><th>Bölüm</th><th>İşlemler</th></tr></thead>
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
                                     <option value="Nihayi Ürün">Nihayi Ürün</option>
                                     <option value="Yarım Mamul">Yarım Mamul</option>
                                 </select>
                             </td>
                             <td><input type="text" id="new-c-path" class="form-control form-control-sm" placeholder="ID1-Adet:ID2-Adet"></td>
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
                     <button class="btn btn-outline-primary btn-sm" onclick="document.getElementById('fileUrun').click()"><i class="bi bi-box-arrow-in-down"></i> İçeri Aktar</button>
                     <input type="file" id="fileUrun" class="d-none" accept=".xlsx" onchange="importTable(event, 'products')">
                     <input type="text" id="searchProducts" class="form-control" placeholder="Ara..." oninput="loadData('products')">
                 </div>
             </div>
             <div class="table-responsive">
                 <table class="table table-modern table-hover align-middle">
                     <thead><tr><th>No</th><th>Ürün Adı</th><th>Yol (BOM)</th><th>Sistem Adı</th><th>Sistem Kodu</th><th>İşlemler</th></tr></thead>
                     <tbody id="tbody-products"></tbody>
                     <tfoot>
                         <tr class="bg-light">
                             <td>#</td>
                             <td><input type="text" id="new-u-name" class="form-control form-control-sm"></td>
                             <td><input type="text" id="new-u-path" class="form-control form-control-sm"></td>
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

<script>
    const state = { personnel: [], departments: [], components: [], products: [] };
    const editingId = { personnel: null, departments: null, components: null, products: null };

    async function loadData(module) {
        let q = document.getElementById('search'+module.charAt(0).toUpperCase() + module.slice(1))?.value || '';
        try {
            let res = await fetch(`/api/database/${module}?search=${q}`);
            let json = await res.json();
            state[module] = json.data;
            
            if(module === 'departments') {
                document.querySelectorAll('.dd-dept').forEach(dd => {
                    let oldVal = dd.value;
                    dd.innerHTML = '<option value="">Bölüm Seç</option>' + json.data.map(d => `<option value="${d.id}">${d.name}</option>`).join('');
                    dd.value = oldVal;
                });
            }
            renderData(module);
        } catch(e) { console.error("Error loading " + module, e); }
    }

    function renderData(module) {
        const tbody = document.getElementById(`tbody-${module}`);
        tbody.innerHTML = '';
        state[module].forEach(item => {
            const tr = document.createElement('tr');
            if(editingId[module] === item.id) {
                // EDIT MODE
                if(module === 'personnel') {
                    tr.innerHTML = `
                        <td>${item.id}</td>
                        <td><input type="text" id="edit-p-name-${item.id}" class="form-control form-control-sm" value="${item.name}"></td>
                        <td><input type="text" id="edit-p-surname-${item.id}" class="form-control form-control-sm" value="${item.surname}"></td>
                        <td><input type="text" id="edit-p-address-${item.id}" class="form-control form-control-sm" value="${item.address}"></td>
                        <td><input type="text" id="edit-p-phone-${item.id}" class="form-control form-control-sm" value="${item.phone}"></td>
                        <td><input type="text" id="edit-p-email-${item.id}" class="form-control form-control-sm" value="${item.email}"></td>
                        <td><input type="text" id="edit-p-password-${item.id}" class="form-control form-control-sm" placeholder="Yeni (Boş=Değişmez)"></td>
                        <td><select id="edit-p-dept-${item.id}" class="form-select form-select-sm dd-dept"></select></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                } else if(module === 'departments') {
                    tr.innerHTML = `
                        <td>${item.id}</td>
                        <td><input type="text" id="edit-d-name-${item.id}" class="form-control form-control-sm" value="${item.name}"></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                } else if(module === 'components') {
                    tr.innerHTML = `
                        <td>${item.id}</td>
                        <td><input type="text" id="edit-c-name-${item.id}" class="form-control form-control-sm" value="${item.name}"></td>
                        <td><input type="number" id="edit-c-perf-${item.id}" class="form-control form-control-sm" value="${item.performance_score}"></td>
                        <td><input type="number" id="edit-c-min-${item.id}" class="form-control form-control-sm" value="${item.min_quantity}"></td>
                        <td><input type="text" id="edit-c-type-${item.id}" class="form-control form-control-sm" value="${item.type}"></td>
                        <td><input type="text" id="edit-c-path-${item.id}" class="form-control form-control-sm" value="${item.path}"></td>
                        <td><select id="edit-c-dept-${item.id}" class="form-select form-select-sm dd-dept"></select></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                } else if(module === 'products') {
                    tr.innerHTML = `
                        <td>${item.id}</td>
                        <td><input type="text" id="edit-u-name-${item.id}" class="form-control form-control-sm" value="${item.name}"></td>
                        <td><input type="text" id="edit-u-path-${item.id}" class="form-control form-control-sm" value="${item.path}"></td>
                        <td><input type="text" id="edit-u-sysname-${item.id}" class="form-control form-control-sm" value="${item.system_name}"></td>
                        <td><input type="text" id="edit-u-syscode-${item.id}" class="form-control form-control-sm" value="${item.system_code}"></td>
                        <td>
                            <button class="btn btn-success btn-sm btn-icon" onclick="saveData('${module}', ${item.id})"><i class="bi bi-save2"></i></button>
                            <button class="btn btn-secondary btn-sm btn-icon" onclick="cancelEdit('${module}')"><i class="bi bi-x-circle"></i></button>
                        </td>
                    `;
                }
            } else {
                // VIEW MODE
                if(module === 'personnel') {
                    tr.innerHTML = `<td>${item.id}</td><td>${item.name}</td><td>${item.surname || ''}</td><td>${item.address || ''}</td><td>${item.phone || ''}</td><td>${item.email}</td><td>***</td><td>${item.department_name}</td>`;
                } else if(module === 'departments') {
                    tr.innerHTML = `<td>${item.id}</td><td>${item.name}</td>`;
                } else if(module === 'components') {
                    tr.innerHTML = `<td>${item.id}</td><td>${item.name}</td><td>${item.performance_score}</td><td>${item.min_quantity}</td><td>${item.type}</td><td>${item.path}</td><td>${item.department_name}</td>`;
                } else if(module === 'products') {
                    tr.innerHTML = `<td>${item.id}</td><td>${item.name}</td><td>${item.path}</td><td>${item.system_name}</td><td>${item.system_code}</td>`;
                }
                
                tr.innerHTML += `
                    <td>
                        <button class="btn btn-warning btn-sm btn-icon" onclick="setEdit('${module}', ${item.id})"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn btn-danger btn-sm btn-icon" onclick="deleteData('${module}', ${item.id})"><i class="bi bi-trash3"></i></button>
                    </td>
                `;
            }
            tbody.appendChild(tr);
        });

        // Set select values in Edit Mode
        if(editingId[module]) {
            let el = state[module].find(x => x.id === editingId[module]);
            let s = document.getElementById(`edit-${module.charAt(0)}-dept-${el.id}`);
            if(s) {
                s.innerHTML = document.querySelector('.dd-dept').innerHTML;
                s.value = el.department_id;
            }
        }
    }

    function setEdit(module, id) {
        editingId[module] = id;
        renderData(module);
    }
    function cancelEdit(module) {
        editingId[module] = null;
        renderData(module);
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
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            });
            if(res.ok) {
                Swal.fire({icon: 'success', title: id ? 'Güncellendi' : 'Eklendi', timer: 1500, showConfirmButton: false});
                editingId[module] = null;
                // clear inputs if new
                if(!id) {
                    const inputs = document.querySelectorAll(`#tab${module.charAt(0).toUpperCase() + module.slice(1)} tfoot input, #tab${module.charAt(0).toUpperCase() + module.slice(1)} tfoot select`);
                    inputs.forEach(i => i.value = '');
                }
                loadData(module);
            } else {
                let err = await res.json();
                Swal.fire('Hata', err.message || 'Veri gönderilemedi', 'error');
            }
        } catch(e) { console.error(e); }
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
                } catch(e) { console.error(e); }
            }
        });
    }

    // EXPORT
    function exportTable(module) {
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

    // IMPORT
    function importTable(event, module) {
        const file = event.target.files[0];
        if(!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const json = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]]);
            console.log("Imported from Excel:", json);
            Swal.fire('İçeri Aktarıldı', `${json.length} satır okundu.<br><small>Veritabanı senkronizasyonu geliştirilme aşamasındadır.</small>`, 'info');
        };
        reader.readAsArrayBuffer(file);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Start loading the departments first, then the rest
        loadData('departments').then(() => {
            loadData('personnel');
            loadData('components');
            loadData('products');
        });
    });
</script>
@endsection