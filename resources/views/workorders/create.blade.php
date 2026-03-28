@extends('layouts.app')
@section('title', 'İş Emri Ver - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clipboard-plus me-2"></i>İş Emri Ver</h4>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-search me-1"></i>Ürün Seç ve Emir Ver</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Ürün Seçimi</label>
                        <select id="urunSelect" class="form-select">
                            <option value="">-- Ürün yükleniyor... --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Adet</label>
                        <input type="number" id="adetInput" class="form-control" min="1" value="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Açıklama (Opsiyonel)</label>
                        <input type="text" id="aciklamaInput" class="form-control" placeholder="Sipariş notu veya özel talimat...">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Stok Durumu</label>
                        <select id="stokDurum" class="form-select">
                            <option value="StokDahil">Stoktan düş (Tampon azalt)</option>
                            <option value="StokHaric">Stoka dokunma</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-success btn-lg" onclick="isEmriVer()">
                            <i class="bi bi-send me-1"></i>İş Emri Ver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white"><i class="bi bi-info-circle me-1"></i>Bilgi</div>
            <div class="card-body small">
                <p><strong>İş Emri Verme:</strong></p>
                <ul>
                    <li>Ürün seçin ve adet belirleyin</li>
                    <li>Sistem, ürünün alt parçalarını otomatik hesaplar</li>
                    <li>Her bölüme otomatik görev oluşturulur</li>
                    <li>"Stoktan düş" seçilirse mevcut stoktan tampon düşülür</li>
                </ul>
                <div class="alert alert-info small mb-0">
                    <i class="bi bi-lightbulb me-1"></i>Toplu iş emri vermek için <a href="{{ route('workorders.bulk') }}">buraya tıklayın</a>.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3" id="resultCard" style="display:none;">
    <div class="card-header bg-success text-white"><i class="bi bi-check-circle me-1"></i>İş Emri Sonucu</div>
    <div class="card-body" id="resultBody"></div>
</div>
@endsection

@push('scripts')
<script>
// Ürün listesini yükle
fetch('/SiparisApi.ashx?action=getProducts')
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        let sel = document.getElementById('urunSelect');
        sel.innerHTML = '<option value="">-- Ürün seçin --</option>';
        data.products.forEach(p => {
            sel.innerHTML += `<option value="${p.no}">${p.urunId} ${p.sistemAdi ? '('+p.sistemAdi+')' : ''}</option>`;
        });
    });

function isEmriVer() {
    let urunNo = document.getElementById('urunSelect').value;
    let adet = parseInt(document.getElementById('adetInput').value);
    let aciklama = document.getElementById('aciklamaInput').value;
    let stokDurum = document.getElementById('stokDurum').value;

    if (!urunNo) { alert('Lütfen ürün seçin!'); return; }
    if (adet < 1) { alert('Adet 0\'dan büyük olmalı!'); return; }

    if (!confirm(`${adet} adet iş emri verilecek. Onaylıyor musunuz?`)) return;

    fetch('/SiparisApi.ashx?action=createOrderWorkOrders', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ satirNolar: [parseInt(urunNo)], surplus: adet, aciklama: aciklama, stokDurum: stokDurum })
    }).then(r => r.json()).then(data => {
        let card = document.getElementById('resultCard');
        let body = document.getElementById('resultBody');
        card.style.display = 'block';
        if (data.success) {
            body.innerHTML = `<div class="alert alert-success">${data.message || 'İş emri başarıyla verildi!'}</div>`;
        } else {
            body.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
        }
    }).catch(e => alert('Hata: ' + e.message));
}
</script>
@endpush
