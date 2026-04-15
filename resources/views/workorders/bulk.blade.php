@extends('layouts.app')

@section('title', 'Toplu Is Emri')

@section('page-actions')
    <a href="{{ route('workorders.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Tekli Emir</a>
    <a href="{{ route('workorders.history') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>Gecmis</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadSummary()"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card"><p class="metric-label">Toplam Urun</p><h3 class="metric-value" id="summaryProductsCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Toplam Adet</p><h3 class="metric-value" id="summaryAmountCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Eslesmis Kayit</p><h3 class="metric-value" id="summaryMatchedCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Secili Kayit</p><h3 class="metric-value" id="summarySelectedCard">0</h3></article>
    </div>

    <div class="panel-surface">
        <div class="d-flex flex-wrap gap-3 align-items-end">
            <div style="min-width: 200px; flex: 1;">
                <label class="form-label" for="kategoriSelect">Kategori</label>
                <select id="kategoriSelect" class="form-select" onchange="loadSummary()"><option value="">Tum Kategoriler</option></select>
            </div>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="clearBulkSelection()"><i class="bi bi-eraser me-1"></i>Secimi Temizle</button>
            <button class="btn btn-primary btn-sm" type="button" onclick="topluIsEmriVer()" id="btnTopluVer" disabled><i class="bi bi-send me-1"></i>Is Emri Ver (<span id="selectedCount">0</span>)</button>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy"><h3>Toplu emir ozet tablosu</h3></div>
            <div class="panel-toolbar-meta"><span class="soft-badge" id="summaryTableState">Yukleniyor</span></div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead><tr>
                    <th style="width: 44px;"><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                    <th>Urun Adi</th><th>Sistem Adi</th><th>Toplam Adet</th><th>Siparis Sayisi</th><th>En Yakin Kargo</th><th>Eslesme</th>
                </tr></thead>
                <tbody id="summaryBody"><tr><td colspan="7" class="text-center py-4 text-muted">Yukleniyor...</td></tr></tbody>
            </table>
        </div>
        <div class="px-4 pb-4 small text-muted" id="summaryFooter">Toplam: 0 urun</div>
    </section>

    <span id="summaryProductsInline" style="display:none;">0</span>
    <span id="summaryAmountInline" style="display:none;">0</span>
    <span id="summaryMatchedInline" style="display:none;">0</span>
    <span id="summarySelectedInline" style="display:none;">0</span>
    <span id="summaryStateChip" style="display:none;">Hazir</span>
    <span id="summaryCategoryMeta" style="display:none;">Tum kategoriler</span>
    <span id="summarySelectedMeta" style="display:none;">0 kayit</span>
    <span id="summaryMatchedMeta" style="display:none;">0 kayit</span>
    <span id="summaryActionMeta" style="display:none;">Bekleniyor</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let summaryData = [];

function loadSummary() {
    const kategori = document.getElementById('kategoriSelect').value;
    document.getElementById('summaryTableState').textContent = 'Yukleniyor'; document.getElementById('summaryTableState').className = 'soft-badge warning';
    fetch('/SiparisApi.ashx?action=getSummary&kategori=' + encodeURIComponent(kategori)).then((r) => r.json()).then((data) => {
        if (!data.success) { Swal.fire({ icon: 'error', title: 'Veri alinamadi', text: data.message || 'Ozet yuklenemedi.' }); return; }
        summaryData = data.summary || [];
        const select = document.getElementById('kategoriSelect');
        if (data.kategoriList && select.options.length <= 1) { data.kategoriList.forEach((k) => { select.innerHTML += `<option value="${escapeHtml(k)}">${escapeHtml(k)}</option>`; }); }
        renderSummary(); updateBulkSummary();
        document.getElementById('summaryTableState').textContent = 'Guncel'; document.getElementById('summaryTableState').className = 'soft-badge success';
    }).catch((e) => Swal.fire({ icon: 'error', title: 'Sunucu hatasi', text: e.message }));
}

function renderSummary() {
    const tbody = document.getElementById('summaryBody');
    if (!summaryData.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Uretim bekleyen siparis yok.</td></tr>'; document.getElementById('summaryFooter').textContent = 'Toplam: 0 urun'; document.getElementById('selectAll').checked = false; updateSelection(); return; }
    tbody.innerHTML = summaryData.map((item, i) => {
        const eslesme = item.EslesenUrunNo > 0 ? '<span class="soft-badge success">Eslesmis</span>' : '<span class="soft-badge warning">Eslesmemis</span>';
        return `<tr><td><input type="checkbox" class="rowCheck" data-index="${i}" onchange="updateSelection()" ${item.EslesenUrunNo > 0 ? '' : 'disabled'}></td><td>${escapeHtml(item.UrunAdi || '-')}</td><td>${escapeHtml(item.sistemAdi || '-')}</td><td><strong>${Number(item.ToplamAdet || 0).toLocaleString('tr-TR')}</strong></td><td>${Number(item.SiparisSayisi || 0).toLocaleString('tr-TR')}</td><td>${escapeHtml(item.EnYakinKargo || '-')}</td><td>${eslesme}</td></tr>`;
    }).join('');
    document.getElementById('summaryFooter').textContent = `Toplam: ${summaryData.length} urun, ${summaryData.reduce((s, i) => s + Number(i.ToplamAdet || 0), 0).toLocaleString('tr-TR')} adet`;
    updateSelection();
}

function updateBulkSummary() {
    const totalAmount = summaryData.reduce((s, i) => s + Number(i.ToplamAdet || 0), 0);
    const matched = summaryData.filter((i) => Number(i.EslesenUrunNo || 0) > 0).length;
    const selected = document.querySelectorAll('.rowCheck:checked').length;
    document.getElementById('summaryProductsInline').textContent = summaryData.length;
    document.getElementById('summaryAmountInline').textContent = totalAmount.toLocaleString('tr-TR');
    document.getElementById('summaryMatchedInline').textContent = matched;
    document.getElementById('summarySelectedInline').textContent = selected;
    document.getElementById('summaryProductsCard').textContent = summaryData.length;
    document.getElementById('summaryAmountCard').textContent = totalAmount.toLocaleString('tr-TR');
    document.getElementById('summaryMatchedCard').textContent = matched;
    document.getElementById('summarySelectedCard').textContent = selected;
}

function toggleAll() { const c = document.getElementById('selectAll').checked; document.querySelectorAll('.rowCheck:not(:disabled)').forEach((cb) => cb.checked = c); updateSelection(); }
function updateSelection() { const count = document.querySelectorAll('.rowCheck:checked').length; document.getElementById('selectedCount').textContent = count; document.getElementById('btnTopluVer').disabled = count === 0; updateBulkSummary(); }
function clearBulkSelection() { document.getElementById('selectAll').checked = false; document.querySelectorAll('.rowCheck').forEach((cb) => cb.checked = false); updateSelection(); }

function topluIsEmriVer() {
    const selected = []; document.querySelectorAll('.rowCheck:checked').forEach((cb) => { selected.push(summaryData[parseInt(cb.dataset.index, 10)]); });
    if (!selected.length) { Swal.fire({ icon: 'warning', title: 'Kayit secin', text: 'Toplu islem icin once en az bir urun secin.' }); return; }
    Swal.fire({ icon: 'question', title: 'Toplu akisi baslat', text: `${selected.length} urun icin toplu is emri akisi baslatilacak.`, showCancelButton: true, confirmButtonText: 'Evet, baslat', cancelButtonText: 'Iptal', confirmButtonColor: '#0d9488' }).then((result) => {
        if (!result.isConfirmed) return;
        document.getElementById('summaryActionMeta').textContent = `${selected.length} kayit icin islem baslatildi`;
        Swal.fire({ icon: 'info', title: 'Akis baslatildi', text: 'Toplu is emri verme islemi baslatildi.', confirmButtonColor: '#0d9488' });
    });
}

function escapeHtml(v) { if (v === null || v === undefined) return ''; return String(v).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
document.addEventListener('DOMContentLoaded', loadSummary);
</script>
@endpush
