@extends('layouts.app')
@section('title', 'Üretim Planlama')
@section('content')
<div class="container-fluid mt-3">
    <h4><i class="bi bi-calendar-week"></i> Üretim Planlama</h4>
    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <select id="kategoriFilter" class="form-select"><option value="">Tüm Kategoriler</option></select>
                </div>
                <div class="col-md-4">
                    <input type="date" id="tarihFilter" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" onclick="loadPlanning()"><i class="bi bi-search"></i> Filtrele</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="planningTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Ürün</th><th>Toplam Sipariş</th><th>Üretilebilir (Stok)</th>
                            <th>Eksik Miktar</th><th>Kargo Son Teslim</th><th>Durum</th><th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="planningBody"><tr><td colspan="7" class="text-center">Yükleniyor...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function loadPlanning() {
    fetch('/SiparisApi.ashx?action=getSummary&kategori=' + document.getElementById('kategoriFilter').value)
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            let html = '';
            if (data.kategoriList) {
                let sel = document.getElementById('kategoriFilter');
                sel.innerHTML = '<option value="">Tüm Kategoriler</option>';
                data.kategoriList.forEach(k => sel.innerHTML += '<option>' + k + '</option>');
            }
            (data.summary || []).forEach(s => {
                let urgency = s.EnYakinKargo ? '<span class="badge bg-warning">' + s.EnYakinKargo + '</span>' : '-';
                let status = s.EslesenUrunNo > 0 ? '<span class="badge bg-success">Eşleşmiş</span>' : '<span class="badge bg-danger">Eşleşmemiş</span>';
                html += '<tr><td>' + s.UrunAdi + (s.sistemAdi ? '<br><small class="text-muted">' + s.sistemAdi + '</small>' : '') + '</td>'
                    + '<td class="text-center">' + s.ToplamAdet + '</td>'
                    + '<td class="text-center">-</td><td class="text-center">-</td>'
                    + '<td class="text-center">' + urgency + '</td><td>' + status + '</td>'
                    + '<td><button class="btn btn-sm btn-primary" onclick="planWorkOrder(' + s.EslesenUrunNo + ',' + s.ToplamAdet + ',\'' + s.EslesenUrunTur + '\')"><i class="bi bi-play"></i> İş Emri</button></td></tr>';
            });
            document.getElementById('planningBody').innerHTML = html || '<tr><td colspan="7" class="text-center">Veri yok</td></tr>';
        });
}
function planWorkOrder(urunNo, adet, tur) {
    if (!confirm(adet + ' adet için iş emri oluşturulsun mu?')) return;
    fetch('/SiparisApi.ashx?action=createOrderWorkOrders', {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({eslesenUrunNo:urunNo, eslesenUrunTur:tur, adet:adet, stokDurum:'StokDahil'})
    }).then(r=>r.json()).then(d => { alert(d.message || 'İşlem tamamlandı.'); loadPlanning(); });
}
loadPlanning();
</script>
@endsection
