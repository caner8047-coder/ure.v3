@extends('layouts.app')

@section('title', 'Admin Stok Yönetimi')

@section('content')
<div class="panel-surface mt-3">
    <div class="orders-panel-heading mb-3">
        <div>
            <p>Stok Yonetimi</p>
            <h3><i class="bi bi-boxes text-muted me-2"></i>Ara Ürün Stokları</h3>
            <p class="text-muted small mt-1 mb-0">Tampon stok hesaplamaları ve genel envanter yönetimi</p>
        </div>
    </div>

    <div class="table-responsive" style="border: 1px solid var(--z-border); border-radius: var(--z-radius); overflow:hidden;">
        <table class="table-modern" id="stockTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Bölüm</th>
                    <th>Ara Ürün</th>
                    <th>Fiziki Stok</th>
                    <th>Görevdeki (Ayrılmış)</th>
                    <th>Boşta (Tampon)</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody id="stockBody">
                <tr><td colspan="7" class="text-center text-muted py-4">Yükleniyor...</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
function loadAdminStocks() {
    fetch('/api/stocks')
        .then(r => r.json())
        .then(data => {
            let rows = data.data || [];
            let html = '';
            rows.forEach(s => {
                let adet = Number(s.Adet || 0);
                let bosta = Math.max(0, Math.min(adet, Number(s.TamponMiktar || 0)));
                let gorevdeki = Math.max(0, adet - bosta);
                let cls = bosta > 0 ? 'text-success' : 'text-danger';
                html += '<tr>'
                    + '<td>' + s.No + '</td>'
                    + '<td>' + (s.BolumAdi || '-') + '</td>'
                    + '<td>' + (s.AraUrunAdi || '-') + '</td>'
                    + '<td><strong>' + adet + '</strong></td>'
                    + '<td>' + gorevdeki + '</td>'
                    + '<td class="' + cls + '"><strong>' + bosta + '</strong></td>'
                    + '<td><button class="btn btn-sm btn-outline-primary" onclick="editAdminStock(' + s.No + ',' + (s.Adet||0) + ',' + (s.TamponMiktar||0) + ')"><i class="bi bi-pencil"></i></button></td>'
                    + '</tr>';
            });
            document.getElementById('stockBody').innerHTML = html || '<tr><td colspan="7" class="text-center text-muted py-4">Stok verisi bulunamadı</td></tr>';
        });
}

function editAdminStock(no, adet, tampon) {
    let newAdet = prompt('Yeni Adet:', adet);
    if (newAdet === null) return;
    const reserved = Math.max(0, parseInt(adet || 0, 10) - Math.max(0, Math.min(parseInt(adet || 0, 10), parseInt(tampon || 0, 10))));
    const suggestedTampon = Math.max(0, parseInt(newAdet || 0, 10) - reserved);
    let newTampon = prompt('Yeni Boşta/Tampon:', suggestedTampon);
    if (newTampon === null) return;
    const preserveReserved = parseInt(newTampon || 0, 10) === suggestedTampon;
    fetch('/api/stocks/' + no, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
        body: JSON.stringify({Adet: parseInt(newAdet), TamponMiktar: parseInt(newTampon), preserve_reserved: preserveReserved})
    }).then(r => r.json()).then(d => { if (d.success) loadAdminStocks(); else alert('Hata!'); });
}

loadAdminStocks();
</script>
@endpush
