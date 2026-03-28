@extends('layouts.app')
@section('title', 'Üretim Bekleyen Özet - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Üretim Bekleyen Siparişler Özeti</h4>
    <div>
        <select id="kategoriSelect" class="form-select form-select-sm d-inline-block" style="width:200px;" onchange="loadSummary()">
            <option value="">Tüm Kategoriler</option>
        </select>
        <button class="btn btn-outline-primary btn-sm" onclick="loadSummary()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white text-center p-3"><h3 id="statUrun">0</h3><small>Farklı Ürün</small></div></div>
    <div class="col-md-3"><div class="card bg-success text-white text-center p-3"><h3 id="statAdet">0</h3><small>Toplam Adet</small></div></div>
    <div class="col-md-3"><div class="card bg-warning text-dark text-center p-3"><h3 id="statEslesme">0</h3><small>Eşleşmiş</small></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white text-center p-3"><h3 id="statEstiksiz">0</h3><small>Eşleşmemiş</small></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr><th>#</th><th>Ürün Adı</th><th>Sistem Adı</th><th>Toplam Adet</th><th>Sipariş Sayısı</th><th>En Yakın Kargo</th><th>Eşleşme</th></tr>
                </thead>
                <tbody id="summaryBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function loadSummary() {
    let kategori = document.getElementById('kategoriSelect').value;
    fetch('/SiparisApi.ashx?action=getSummary&kategori=' + encodeURIComponent(kategori))
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.message); return; }
            let items = data.summary || [];
            let sel = document.getElementById('kategoriSelect');
            if (data.kategoriList && sel.options.length <= 1) {
                data.kategoriList.forEach(k => { sel.innerHTML += `<option value="${k}">${k}</option>`; });
            }
            let eslesmis = items.filter(i => i.EslesenUrunNo > 0).length;
            document.getElementById('statUrun').textContent = items.length;
            document.getElementById('statAdet').textContent = data.toplamAdet || 0;
            document.getElementById('statEslesme').textContent = eslesmis;
            document.getElementById('statEstiksiz').textContent = items.length - eslesmis;

            let tbody = document.getElementById('summaryBody');
            if (items.length === 0) { tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Veri yok</td></tr>'; return; }
            let html = '';
            items.forEach((item, i) => {
                let badge = item.EslesenUrunNo > 0 ? '<span class="badge bg-success">✓</span>' : '<span class="badge bg-warning text-dark">✗</span>';
                html += `<tr><td>${i+1}</td><td>${item.UrunAdi||'-'}</td><td>${item.sistemAdi||'-'}</td><td><strong>${item.ToplamAdet}</strong></td><td>${item.SiparisSayisi}</td><td>${item.EnYakinKargo||'-'}</td><td>${badge}</td></tr>`;
            });
            tbody.innerHTML = html;
        });
}
loadSummary();
</script>
@endpush
