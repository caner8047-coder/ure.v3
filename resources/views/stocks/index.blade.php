@extends('layouts.app')
@section('title', 'Stoklar')

@push('styles')
<style>
    body { background: #f8f6f2; font-family: 'Inter', sans-serif; }
    .stock-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 20px; }
    .filter-row { background: #fff; padding: 15px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 15px; }
    .EU_DataTable { border-collapse: collapse; width: 100%; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); font-size: 14px; }
    .EU_DataTable th { background-color: #4b3621; color: #f8f6f2; padding: 10px 12px; text-align: center; font-weight: 600; }
    .EU_DataTable td { padding: 8px; text-align: center; color: #3b2c1a; }
    .EU_DataTable tr:nth-child(even) { background-color: #f8f4ec; }
    .EU_DataTable tr:hover { background-color: #f0ebe4; }
    .badge-stock { font-size: 11px; }
</style>
@endpush

@section('content')
<div class="container-fluid mt-3">
    <h4 class="mb-3"><i class="bi bi-boxes"></i> Stok Yönetimi</h4>

    <div class="filter-row">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Bölüm</label>
                <select id="filterDept" class="form-select form-select-sm" onchange="loadStocks()">
                    <option value="">Tüm Bölümler</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Ürün Çeşidi</label>
                <select id="filterType" class="form-select form-select-sm" onchange="loadStocks()">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Arama</label>
                <input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Ara..." onkeyup="if(event.key==='Enter') loadStocks()">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100" onclick="loadStocks()"><i class="bi bi-search"></i> Filtrele</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="EU_DataTable">
            <thead>
                <tr>
                    <th onclick="sortBy('No')">No ↕</th>
                    <th onclick="sortBy('BolumAdi')">Bölüm ↕</th>
                    <th onclick="sortBy('AraUrunAdi')">Ara Ürün ↕</th>
                    <th>Ürün Çeşidi</th>
                    <th onclick="sortBy('Adet')">Adet ↕</th>
                    <th onclick="sortBy('TamponMiktar')">Tampon ↕</th>
                    <th>Kullanılabilir</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody id="stockBody">
                <tr><td colspan="8" class="text-center py-4">Yükleniyor...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-3" id="pagination"></div>
</div>

@endsection

@push('scripts')
<script>
let currentSort = 'No';
let currentDir = 'asc';

function loadLookups() {
    fetch('/api/stocks/lookups').then(r => r.json()).then(data => {
        let deptSel = document.getElementById('filterDept');
        (data.departments || []).forEach(d => {
            deptSel.innerHTML += '<option value="' + d.id + '">' + d.name + '</option>';
        });
        let typeSel = document.getElementById('filterType');
        (data.componentTypes || []).forEach(t => {
            typeSel.innerHTML += '<option>' + t + '</option>';
        });
    });
}

function sortBy(field) {
    if (currentSort === field) currentDir = currentDir === 'asc' ? 'desc' : 'asc';
    else { currentSort = field; currentDir = 'asc'; }
    loadStocks();
}

function loadStocks(page) {
    page = page || 1;
    let params = new URLSearchParams({
        page: page,
        per_page: 20,
        sort_by: currentSort,
        sort_dir: currentDir,
    });
    let dept = document.getElementById('filterDept').value;
    let type = document.getElementById('filterType').value;
    let search = document.getElementById('filterSearch').value;
    if (dept) params.set('department_id', dept);
    if (type) params.set('component_type', type);
    if (search) params.set('search', search);

    fetch('/api/stocks?' + params.toString())
        .then(r => r.json())
        .then(data => {
            let rows = data.data || [];
            let html = '';
            rows.forEach(s => {
                let kullanilabilir = Math.max(0, (s.Adet || 0) - (s.TamponMiktar || 0));
                let badgeClass = kullanilabilir > 0 ? 'bg-success' : 'bg-danger';
                html += '<tr>'
                    + '<td>' + s.No + '</td>'
                    + '<td>' + (s.BolumAdi || '-') + '</td>'
                    + '<td>' + (s.AraUrunAdi || '-') + '</td>'
                    + '<td><span class="badge badge-stock bg-info">' + (s.UrunCesidi || '-') + '</span></td>'
                    + '<td><strong>' + (s.Adet || 0) + '</strong></td>'
                    + '<td>' + (s.TamponMiktar || 0) + '</td>'
                    + '<td><span class="badge ' + badgeClass + '">' + kullanilabilir + '</span></td>'
                    + '<td><button class="btn btn-sm btn-outline-primary" onclick="editStock(' + s.No + ',' + (s.Adet||0) + ',' + (s.TamponMiktar||0) + ')"><i class="bi bi-pencil"></i></button></td>'
                    + '</tr>';
            });
            document.getElementById('stockBody').innerHTML = html || '<tr><td colspan="8" class="text-center text-muted py-4">Stok kaydı bulunamadı</td></tr>';
        });
}

function editStock(no, adet, tampon) {
    let newAdet = prompt('Yeni Adet:', adet);
    if (newAdet === null) return;
    let newTampon = prompt('Yeni Tampon:', tampon);
    if (newTampon === null) return;
    fetch('/api/stocks/' + no, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
        body: JSON.stringify({Adet: parseInt(newAdet), TamponMiktar: parseInt(newTampon)})
    }).then(r => r.json()).then(d => { if (d.success) loadStocks(); else alert('Hata!'); });
}

loadLookups();
loadStocks();
</script>
@endpush