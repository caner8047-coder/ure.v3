@extends('layouts.app')
@section('title', 'Personel Rapor - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-person me-2"></i>Personel Rapor</h4>
</div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white text-center p-3"><h3 id="statToplam">0</h3><small>Toplam Personel</small></div></div>
    <div class="col-md-3"><div class="card bg-success text-white text-center p-3"><h3 id="statBolum">0</h3><small>Bölüm Sayısı</small></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between">
        <span>Personel Listesi</span>
        <input type="text" id="aramaInput" class="form-control form-control-sm" style="width:250px" placeholder="Personel ara..." oninput="filterData()">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark"><tr><th>Personel No</th><th>Ad</th><th>Soyad</th><th>E-posta</th><th>Bölüm</th></tr></thead>
                <tbody id="personelBody"><tr><td colspan="5" class="text-center py-3 text-muted">Yükleniyor...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let allData = [];

function loadData() {
    fetch('/SiparisApi.ashx?action=getPersoneller').then(r => r.json()).then(data => {
        if (!data.success) return;
        allData = data.data || [];
        document.getElementById('statToplam').textContent = allData.length;
        let bolumler = new Set(allData.map(p => p.BolumAdi).filter(b => b));
        document.getElementById('statBolum').textContent = bolumler.size;
        renderData(allData);
    });
}

function renderData(list) {
    let html = '';
    list.forEach(p => {
        html += `<tr><td>${p.PersonelNo}</td><td>${p.Ad||'-'}</td><td>${p.Soyad||'-'}</td><td>${p.Mail||'-'}</td><td>${p.BolumAdi||'-'}</td></tr>`;
    });
    document.getElementById('personelBody').innerHTML = html || '<tr><td colspan="5" class="text-center text-muted">Veri yok</td></tr>';
}

function filterData() {
    let q = document.getElementById('aramaInput').value.toLowerCase();
    renderData(allData.filter(p => (p.Ad||'').toLowerCase().includes(q)||(p.Soyad||'').toLowerCase().includes(q)||(p.Mail||'').toLowerCase().includes(q)));
}

loadData();
</script>
@endpush
