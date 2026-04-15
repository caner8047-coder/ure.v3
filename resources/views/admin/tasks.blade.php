@extends('layouts.app')

@section('title', 'Görev Atama')

@section('content')
<div class="panel-surface mt-3">
    <div class="orders-panel-heading mb-3">
        <div>
            <p>Personel Yonetimi</p>
            <h3><i class="bi bi-people-fill text-muted me-2"></i>Personele Görev Atama</h3>
            <p class="text-muted small mt-1 mb-0">İş emirlerinden personellere parça atama ekranı.</p>
        </div>
    </div>

    <div class="row mb-4 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Bölüm</label>
            <select id="taskDept" class="form-select form-select-sm" onchange="loadPoolItems()">
                <option value="">Tüm Bölümler</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Personel</label>
            <select id="taskPersonel" class="form-select form-select-sm">
                <option value="">Personel Seçin...</option>
            </select>
        </div>
    </div>

    <div class="table-responsive" style="border: 1px solid var(--z-border); border-radius: var(--z-radius); overflow:hidden;">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Ara Ürün</th>
                    <th>Bölüm</th>
                    <th>Havuzdaki Adet</th>
                    <th>Toplam Adet</th>
                    <th>Atanacak Adet</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody id="poolBody">
                <tr><td colspan="7" class="text-center text-muted py-4">Yükleniyor...</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
const adminTaskCsrf = document.querySelector('meta[name="csrf-token"]').content;

function loadLookups() {
    fetch('/api/stocks/lookups')
        .then(r => r.json())
        .then(data => {
            let deptSel = document.getElementById('taskDept');
            (data.departments || []).forEach(d => {
                deptSel.innerHTML += '<option value="' + d.id + '">' + d.name + '</option>';
            });
        });
    
    fetch('/api/database/personnel')
        .then(r => r.json())
        .then(data => {
            let perSel = document.getElementById('taskPersonel');
            (data.data || []).forEach(p => {
                perSel.innerHTML += '<option value="' + p.id + '">' + p.name + ' ' + p.surname + '</option>';
            });
        });
}

function loadPoolItems() {
    let dept = document.getElementById('taskDept').value;
    let url = '/api/database/pool-tasks';
    if (dept) {
        url += '?department_id=' + encodeURIComponent(dept);
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            let rows = data.data || [];
            let html = '';
            rows.forEach(s => {
                html += '<tr>'
                    + '<td>' + s.id + '</td>'
                    + '<td>' + (s.component_name || s.product_name || '-') + '</td>'
                    + '<td>' + (s.department_name || '-') + '</td>'
                    + '<td><strong>' + (s.adet || 0) + '</strong></td>'
                    + '<td>' + (s.toplam_adet || 0) + '</td>'
                    + '<td><input type="number" class="form-control form-control-sm" style="width:80px" value="1" min="1" max="' + (s.adet || 1) + '" id="adet_' + s.id + '"></td>'
                    + '<td><button class="btn btn-sm btn-success" onclick="assignTask(' + s.id + ')"><i class="bi bi-person-plus"></i> Ata</button></td>'
                    + '</tr>';
            });
            document.getElementById('poolBody').innerHTML = html || '<tr><td colspan="7" class="text-center text-muted py-4">Atanabilecek görev bulunamadı</td></tr>';
        });
}

function assignTask(stockNo) {
    let personelId = document.getElementById('taskPersonel').value;
    if (!personelId) { alert('Lütfen personel seçin!'); return; }
    let adet = document.getElementById('adet_' + stockNo)?.value || 1;
    fetch(`/api/database/pool-tasks/${stockNo}/assign`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': adminTaskCsrf,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ personel_no: parseInt(personelId), adet: parseInt(adet) })
    })
        .then(r => r.json())
        .then(data => {
            alert(data.message || 'Görev atandı.');
            loadPoolItems();
        })
        .catch(() => alert('Görev atama işlemi başarısız oldu.'));
}

loadLookups();
loadPoolItems();
</script>
@endpush
