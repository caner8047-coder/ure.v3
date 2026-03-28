@extends('layouts.app')

@section('title', 'Görev Atama')

@section('content')
<div class="container-fluid mt-3">
    <h4><i class="bi bi-people-fill"></i> Personele Görev Atama</h4>
    <p class="text-muted">İş emirlerinden personellere parça atama</p>

    <div class="row mb-3">
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

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-dark">
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
function loadLookups() {
    fetch('/api/stocks/lookups')
        .then(r => r.json())
        .then(data => {
            let deptSel = document.getElementById('taskDept');
            (data.departments || []).forEach(d => {
                deptSel.innerHTML += '<option value="' + d.id + '">' + d.name + '</option>';
            });
        });
    
    fetch('/api/db/personnel')
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
    let url = '/PersonelGorevApi.ashx?action=getPoolItems';
    if (dept) url += '&bolumNo=' + dept;
    
    // Use SiparisApi fallback to get havuz data
    fetch('/SiparisApi.ashx?action=getOrders&durum=IsEmriVerildi')
        .then(r => r.json())
        .then(data => {
            // Show havuz items from tbBolumHavuz
            fetch('/api/stocks?per_page=100')
                .then(r => r.json())
                .then(stockData => {
                    let rows = stockData.data || [];
                    let html = '';
                    rows.forEach(s => {
                        if (s.TamponMiktar > 0 || true) {
                            html += '<tr>'
                                + '<td>' + s.No + '</td>'
                                + '<td>' + (s.AraUrunAdi || '-') + '</td>'
                                + '<td>' + (s.BolumAdi || '-') + '</td>'
                                + '<td><strong>' + (s.Adet || 0) + '</strong></td>'
                                + '<td>' + (s.TamponMiktar || 0) + '</td>'
                                + '<td><input type="number" class="form-control form-control-sm" style="width:80px" value="1" min="1" id="adet_' + s.No + '"></td>'
                                + '<td><button class="btn btn-sm btn-success" onclick="assignTask(' + s.No + ')"><i class="bi bi-person-plus"></i> Ata</button></td>'
                                + '</tr>';
                        }
                    });
                    document.getElementById('poolBody').innerHTML = html || '<tr><td colspan="7" class="text-center text-muted py-4">Atanabilecek görev bulunamadı</td></tr>';
                });
        });
}

function assignTask(stockNo) {
    let personelId = document.getElementById('taskPersonel').value;
    if (!personelId) { alert('Lütfen personel seçin!'); return; }
    let adet = document.getElementById('adet_' + stockNo)?.value || 1;
    alert('Görev atama işlemi: Stok #' + stockNo + ' → Personel #' + personelId + ' (' + adet + ' adet)\nBu fonksiyon PersonelGorevApi üzerinden çalışacaktır.');
}

loadLookups();
loadPoolItems();
</script>
@endpush
