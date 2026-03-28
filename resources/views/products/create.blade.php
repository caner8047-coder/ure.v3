@extends('layouts.app')
@section('title', 'Yeni Ürün Ekle - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-plus-square me-2"></i>Yeni Ürün Ekle / Düzenle</h4>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">Ürün Bilgileri</div>
            <div class="card-body">
                <input type="hidden" id="editNo" value="">
                <div class="mb-3">
                    <label class="form-label">Ürün ID (Nihai Ürün Adı)</label>
                    <input type="text" id="urunID" class="form-control" placeholder="ör: Koltuk Takımı XL">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sistem Adı</label>
                    <input type="text" id="sistemAdi" class="form-control" placeholder="ör: KOLTUK-XL-001">
                </div>
                <div class="mb-3">
                    <label class="form-label">Sistem Kodu</label>
                    <input type="text" id="sistemKodu" class="form-control" placeholder="ör: PRD-001">
                </div>
                <div class="mb-3">
                    <label class="form-label">Ara Ürün Yolu (BOM)</label>
                    <textarea id="araAdlarYol" class="form-control" rows="3" placeholder="5-3:8-2:12-1 formatında"></textarea>
                    <small class="text-muted">Format: parçaNo-adet:parçaNo-adet (ör: 5-3:8-2:12-1)</small>
                </div>
                <div class="text-end">
                    <button class="btn btn-secondary me-2" onclick="temizle()">Temizle</button>
                    <button class="btn btn-success" onclick="kaydet()"><i class="bi bi-save me-1"></i>Kaydet</button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <span>Mevcut Ürünler</span>
                <input type="text" id="urunArama" class="form-control form-control-sm" style="width:200px" placeholder="Ürün ara..." oninput="filterUrunler()">
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-secondary sticky-top"><tr><th>No</th><th>Ürün ID</th><th>Sistem Adı</th><th></th></tr></thead>
                    <tbody id="urunlerBody"><tr><td colspan="4" class="text-center text-muted">Yükleniyor...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let allUrunler = [];

function loadUrunler() {
    fetch('/SiparisApi.ashx?action=getProducts').then(r => r.json()).then(data => {
        if (!data.success) return;
        allUrunler = data.products || [];
        renderUrunler(allUrunler);
    });
}

function renderUrunler(list) {
    let html = '';
    list.forEach(p => {
        html += `<tr>
            <td class="small">${p.no}</td>
            <td class="small">${p.urunId}</td>
            <td class="small">${p.sistemAdi || '-'}</td>
            <td><button class="btn btn-outline-primary btn-sm py-0" onclick="duzenle('${p.no}','${(p.urunId||'').replace(/'/g,"\\'")}','${(p.sistemAdi||'').replace(/'/g,"\\'")}','${(p.sistemKodu||'').replace(/'/g,"\\'")}','${(p.araAdlarYol||'').replace(/'/g,"\\'")}')"><i class="bi bi-pencil"></i></button></td>
        </tr>`;
    });
    document.getElementById('urunlerBody').innerHTML = html || '<tr><td colspan="4" class="text-center text-muted">Ürün yok</td></tr>';
}

function filterUrunler() {
    let q = document.getElementById('urunArama').value.toLowerCase();
    renderUrunler(allUrunler.filter(p => (p.urunId||'').toLowerCase().includes(q) || (p.sistemAdi||'').toLowerCase().includes(q)));
}

function duzenle(no, urunId, sistemAdi, sistemKodu, araAdlarYol) {
    document.getElementById('editNo').value = no;
    document.getElementById('urunID').value = urunId;
    document.getElementById('sistemAdi').value = sistemAdi;
    document.getElementById('sistemKodu').value = sistemKodu;
    document.getElementById('araAdlarYol').value = araAdlarYol;
}

function temizle() {
    document.getElementById('editNo').value = '';
    document.getElementById('urunID').value = '';
    document.getElementById('sistemAdi').value = '';
    document.getElementById('sistemKodu').value = '';
    document.getElementById('araAdlarYol').value = '';
}

function kaydet() {
    let no = document.getElementById('editNo').value;
    let payload = {
        UrunID: document.getElementById('urunID').value,
        SistemAdi: document.getElementById('sistemAdi').value,
        SistemKodu: document.getElementById('sistemKodu').value,
        AraAdlarYol: document.getElementById('araAdlarYol').value
    };
    let url = no ? `/api/database/products/${no}` : '/api/database/products';
    let method = no ? 'PUT' : 'POST';
    fetch(url, {
        method: method,
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify(payload)
    }).then(r => r.json()).then(data => {
        alert(data.message || (data.success ? 'Kaydedildi!' : 'Hata!'));
        if (data.success) { temizle(); loadUrunler(); }
    }).catch(e => alert('Hata: ' + e.message));
}

loadUrunler();
</script>
@endpush
