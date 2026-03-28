@extends('layouts.app')
@section('title', 'Ürün Ağacı - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Ürün Ağacı Görselleştirme</h4>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">Ürün Seçimi</div>
            <div class="card-body">
                <input type="text" id="urunArama" class="form-control form-control-sm mb-2" placeholder="Ürün ara..." oninput="filterUrunler()">
                <div style="max-height:400px;overflow-y:auto;" id="urunListesi">
                    <div class="text-center text-muted py-3">Yükleniyor...</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <span id="treeTitle">Ürün seçin...</span>
                <span id="treeBadge"></span>
            </div>
            <div class="card-body" id="treeContainer" style="min-height:300px;">
                <div class="text-center text-muted py-5"><i class="bi bi-arrow-left-circle" style="font-size:2rem;"></i><p class="mt-2">Sol taraftan bir ürün seçerek ağaç yapısını görüntüleyin.</p></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tree-node { padding: 4px 8px; border-left: 2px solid #dee2e6; margin-left: 20px; }
.tree-node.level-0 { border-left: 3px solid #0d6efd; }
.tree-node.level-1 { border-left: 3px solid #198754; }
.tree-node.level-2 { border-left: 3px solid #ffc107; }
.tree-node .badge { font-size: 0.7em; }
</style>
@endpush

@push('scripts')
<script>
let allUrunler = [];

function loadUrunler() {
    fetch('/SiparisApi.ashx?action=getAraUrunler').then(r => r.json()).then(data => {
        if (!data.success) return;
        allUrunler = data.data || [];
        renderUrunListe(allUrunler);
    });
}

function renderUrunListe(list) {
    let html = '<div class="list-group list-group-flush">';
    list.forEach(u => {
        let badge = u.UrunCesidi === 'Nihayi Ürün' ? 'bg-primary' : u.UrunCesidi === 'Ara Mamül' ? 'bg-success' : 'bg-secondary';
        html += `<a href="#" class="list-group-item list-group-item-action py-1 small" onclick="showTree(${u.No},'${(u.AraUrunAdi||'').replace(/'/g,"\\'")}','${u.Yol||''}')">
            <span class="badge ${badge} me-1">${(u.UrunCesidi||'?').charAt(0)}</span>${u.AraUrunAdi || 'Bilinmiyor'}
        </a>`;
    });
    html += '</div>';
    document.getElementById('urunListesi').innerHTML = html;
}

function filterUrunler() {
    let q = document.getElementById('urunArama').value.toLowerCase();
    renderUrunListe(allUrunler.filter(u => (u.AraUrunAdi||'').toLowerCase().includes(q)));
}

function showTree(no, adi, yol) {
    document.getElementById('treeTitle').textContent = adi;
    let container = document.getElementById('treeContainer');

    if (!yol || yol.trim() === '') {
        container.innerHTML = '<div class="alert alert-info">Bu ürünün alt parçası (BOM) tanımlanmamış.</div>';
        return;
    }

    let parts = yol.split(':').filter(p => p.trim() !== '');
    let html = `<div class="tree-node level-0"><strong><i class="bi bi-box me-1"></i>${adi}</strong></div>`;
    parts.forEach(part => {
        let [parcaNo, adet] = part.split('-');
        let parca = allUrunler.find(u => u.No == parcaNo);
        let parcaAdi = parca ? parca.AraUrunAdi : `Parça #${parcaNo}`;
        let badge = parca ? (parca.UrunCesidi === 'Ara Mamül' ? 'bg-success' : parca.UrunCesidi === 'Ham Madde' ? 'bg-warning text-dark' : 'bg-info') : 'bg-secondary';
        html += `<div class="tree-node level-1">
            <span class="badge ${badge} me-1">${adet || 1}x</span>${parcaAdi}
            <small class="text-muted">(No: ${parcaNo})</small>
        </div>`;
        // Show sub-components if available
        if (parca && parca.Yol) {
            let subParts = parca.Yol.split(':').filter(p => p.trim() !== '');
            subParts.forEach(sp => {
                let [sNo, sAdet] = sp.split('-');
                let sub = allUrunler.find(u => u.No == sNo);
                let subAdi = sub ? sub.AraUrunAdi : `Parça #${sNo}`;
                html += `<div class="tree-node level-2"><span class="badge bg-secondary me-1">${sAdet || 1}x</span>${subAdi}</div>`;
            });
        }
    });
    container.innerHTML = html;
}

loadUrunler();
</script>
@endpush
