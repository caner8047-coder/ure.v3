@extends('layouts.app')

@section('title', 'Uretim Bekleyen Ozet')

@section('page-actions')
    <select id="kategoriSelect" class="form-select form-select-sm d-inline-block" style="width:200px;" onchange="loadSummary()">
        <option value="">Tum Kategoriler</option>
    </select>
    <button class="btn btn-primary btn-sm" onclick="loadSummary()">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Farkli Urun</p>
            <h3 class="metric-value" id="statUrun">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Adet</p>
            <h3 class="metric-value" id="statAdet">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Eslesmis</p>
            <h3 class="metric-value" id="statEslesme">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Eslesmemis</p>
            <h3 class="metric-value" id="statEstiksiz">0</h3>
        </article>
    </div>

    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Bekleyen siparis ozeti</h3>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Urun Adi</th>
                        <th>Sistem Adi</th>
                        <th>Toplam Adet</th>
                        <th>Siparis Sayisi</th>
                        <th>En Yakin Kargo</th>
                        <th>Eslesme</th>
                    </tr>
                </thead>
                <tbody id="summaryBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
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
                let badge = item.EslesenUrunNo > 0
                    ? '<span class="badge" style="background:var(--z-success-soft);color:var(--z-success);">✓</span>'
                    : '<span class="badge" style="background:var(--z-warning-soft);color:var(--z-warning);">✗</span>';
                html += `<tr><td>${i+1}</td><td>${item.UrunAdi||'-'}</td><td>${item.sistemAdi||'-'}</td><td><strong>${item.ToplamAdet}</strong></td><td>${item.SiparisSayisi}</td><td>${item.EnYakinKargo||'-'}</td><td>${badge}</td></tr>`;
            });
            tbody.innerHTML = html;
        });
}
loadSummary();
</script>
@endpush
