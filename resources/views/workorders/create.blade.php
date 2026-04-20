@extends('layouts.app')

@section('title', 'Is Emri Ver')

@section('page-actions')
    <a href="{{ route('workorders.bulk') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-stack me-1"></i>Toplu Ver</a>
    <a href="{{ route('workorders.center') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>Merkez</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadManualLookup()"><i class="bi bi-arrow-repeat me-1"></i>Yenile</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .workorder-form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .workorder-form-grid .span-2 { grid-column: span 2; }
    .workorder-result-panel { display: none; }
    .workorder-result-panel.is-visible { display: block; }
    @media (max-width: 720px) { .workorder-form-grid { grid-template-columns: 1fr; } .workorder-form-grid .span-2 { grid-column: span 1; } }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card"><p class="metric-label">Nihai Urun</p><h3 class="metric-value" id="summaryNihaiCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Ara Mamul</p><h3 class="metric-value" id="summaryAraCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Ham Madde</p><h3 class="metric-value" id="summaryHamCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Son Sonuc</p><h3 class="metric-value" id="summaryResultInline" style="font-size: 1rem;">Bekleniyor</h3></article>
    </div>

    <section class="panel-surface">
        <h3 class="section-title" style="margin-bottom: 14px;">Manuel is emri formu</h3>
        <div class="workorder-form-grid">
            <div>
                <label class="form-label" for="turSelect">Is emri turu</label>
                <select id="turSelect" class="form-select" onchange="renderUrunOptions()">
                    <option value="Nihai">Nihai Urun</option>
                    <option value="Ara Mamül">Ara Mamul</option>
                    <option value="Ham Madde">Ham Madde</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="urunSelect">Urun secimi</label>
                <select id="urunSelect" class="form-select" onchange="updateWorkOrderSummary()">
                    <option value="">-- Urun yukleniyor... --</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="adetInput">Adet</label>
                <input type="number" id="adetInput" class="form-control" min="1" value="1">
            </div>
            <div>
                <label class="form-label" for="stokDurum">Stok durumu</label>
                <select id="stokDurum" class="form-select">
                    <option value="StokDahil">Stoktan dus</option>
                    <option value="StokHaric">Stoka dokunma</option>
                </select>
            </div>
            <div class="span-2">
                <label class="form-label" for="aciklamaInput">Aciklama</label>
                <input type="text" id="aciklamaInput" class="form-control" placeholder="Siparis notu veya ozel talimat...">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-4">
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="resetWorkOrderForm()"><i class="bi bi-eraser me-1"></i>Temizle</button>
            <button class="btn btn-primary btn-sm" type="button" onclick="isEmriVer()"><i class="bi bi-send me-1"></i>Is Emri Ver</button>
        </div>
    </section>

    <section class="panel-surface workorder-result-panel" id="resultCard">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title" style="margin:0;">Son islem sonucu</h3>
            <span class="soft-badge" id="resultStateChip">Bekleniyor</span>
        </div>
        <div id="resultBody"></div>
    </section>

    <span id="summaryNihaiInline" style="display:none;">0</span>
    <span id="summaryAraInline" style="display:none;">0</span>
    <span id="summaryHamInline" style="display:none;">0</span>
    <span id="summaryStatusChip" style="display:none;">Hazir</span>
    <span id="summaryTypeMeta" style="display:none;">Nihai Urun</span>
    <span id="summaryProductMeta" style="display:none;">Secim bekleniyor</span>
    <span id="summaryStockMeta" style="display:none;">Stoktan dus</span>
    <span id="summaryNoteMeta" style="display:none;">Bos</span>
    <span id="summaryAdetCard" style="display:none;">1</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let manualLookup = { nihai: [], araMamul: [], hamMadde: [] };

function loadManualLookup() {
    fetch('/SiparisApi.ashx?action=getAraUrunler').then((r) => r.json()).then((data) => {
        if (!data.success) return;
        manualLookup = { nihai: [], araMamul: [], hamMadde: [] };
        (data.data || []).forEach((item) => {
            const tur = String(item.UrunCesidi || '').toLocaleLowerCase('tr-TR');
            if (tur.includes('nihay')) { const kod = String(item.SistemKodu || '').trim(); manualLookup.nihai.push({ no: item.No, label: kod ? `${kod} - ${item.AraUrunAdi}` : item.AraUrunAdi }); }
            else if (tur.includes('ara') && tur.includes('mam')) { manualLookup.araMamul.push({ no: item.No, label: item.AraUrunAdi }); }
            else if (tur.includes('ham madde')) { manualLookup.hamMadde.push({ no: item.No, label: item.AraUrunAdi }); }
        });
        manualLookup.nihai.sort((a, b) => a.label.localeCompare(b.label, 'tr'));
        manualLookup.araMamul.sort((a, b) => a.label.localeCompare(b.label, 'tr'));
        manualLookup.hamMadde.sort((a, b) => a.label.localeCompare(b.label, 'tr'));
        renderUrunOptions(); updateLookupSummary();
    });
}

function renderUrunOptions() {
    const tur = document.getElementById('turSelect').value;
    const select = document.getElementById('urunSelect');
    const options = tur === 'Nihai' ? manualLookup.nihai : tur === 'Ara Mamül' ? manualLookup.araMamul : manualLookup.hamMadde;
    const ph = tur === 'Nihai' ? '-- Nihai urun secin --' : tur === 'Ara Mamül' ? '-- Ara mamul secin --' : '-- Ham madde secin --';
    select.innerHTML = `<option value="">${ph}</option>`;
    options.forEach((item) => { select.innerHTML += `<option value="${item.no}">${escapeHtml(item.label)}</option>`; });
    updateWorkOrderSummary();
}

function updateLookupSummary() {
    document.getElementById('summaryNihaiInline').textContent = manualLookup.nihai.length;
    document.getElementById('summaryAraInline').textContent = manualLookup.araMamul.length;
    document.getElementById('summaryHamInline').textContent = manualLookup.hamMadde.length;
    document.getElementById('summaryNihaiCard').textContent = manualLookup.nihai.length;
    document.getElementById('summaryAraCard').textContent = manualLookup.araMamul.length;
    document.getElementById('summaryHamCard').textContent = manualLookup.hamMadde.length;
}

function updateWorkOrderSummary() {
    const tur = document.getElementById('turSelect').value;
    const uS = document.getElementById('urunSelect');
    const adet = parseInt(document.getElementById('adetInput').value, 10) || 1;
    document.getElementById('summaryTypeMeta').textContent = tur;
    document.getElementById('summaryProductMeta').textContent = uS.value ? (uS.options[uS.selectedIndex]?.text || 'Secildi') : 'Secim bekleniyor';
    document.getElementById('summaryStockMeta').textContent = document.getElementById('stokDurum').value === 'StokDahil' ? 'Stoktan dus' : 'Stoka dokunma';
    document.getElementById('summaryNoteMeta').textContent = document.getElementById('aciklamaInput').value.trim() || 'Bos';
    document.getElementById('summaryAdetCard').textContent = adet;
}

function resetWorkOrderForm() { document.getElementById('turSelect').value = 'Nihai'; document.getElementById('adetInput').value = '1'; document.getElementById('aciklamaInput').value = ''; document.getElementById('stokDurum').value = 'StokDahil'; renderUrunOptions(); }

function isEmriVer() {
    const tur = document.getElementById('turSelect').value;
    const urunNo = document.getElementById('urunSelect').value;
    const adet = parseInt(document.getElementById('adetInput').value, 10);
    const aciklama = document.getElementById('aciklamaInput').value;
    const stokDurum = document.getElementById('stokDurum').value;
    if (!urunNo) { Swal.fire({ icon: 'warning', title: 'Urun secin', text: 'Devam etmek icin once urun secimi yapin.' }); return; }
    if (adet < 1) { Swal.fire({ icon: 'warning', title: 'Gecersiz adet', text: 'Adet 0\'dan buyuk olmali.' }); return; }
    Swal.fire({ icon: 'question', title: 'Is emri olusturulsun mu?', text: `${adet} adet icin manuel is emri acilacak.`, showCancelButton: true, confirmButtonText: 'Evet, olustur', cancelButtonText: 'Iptal', confirmButtonColor: '#0d9488' }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('/SiparisApi.ashx?action=createManualWorkOrder', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ urunNo: parseInt(urunNo, 10), adet, aciklama, stokDurum, tur }) })
            .then((r) => r.json()).then((data) => {
                const card = document.getElementById('resultCard'); const body = document.getElementById('resultBody'); const chip = document.getElementById('resultStateChip');
                card.classList.add('is-visible');
                if (data.success) { chip.textContent = 'Basarili'; chip.className = 'soft-badge success'; body.innerHTML = `<div class="alert alert-success mb-0">${escapeHtml(data.message || 'Is emri verildi.')}</div>`; document.getElementById('summaryResultInline').textContent = 'Basarili'; Swal.fire({ icon: 'success', title: 'Is emri verildi', text: data.message || 'Kayit tamamlandi.', confirmButtonColor: '#0d9488' }); }
                else { chip.textContent = 'Hata'; chip.className = 'soft-badge warning'; body.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(data.message || 'Islem basarisiz.')}</div>`; document.getElementById('summaryResultInline').textContent = 'Hata'; Swal.fire({ icon: 'error', title: 'Islem basarisiz', text: data.message || 'Beklenmeyen hata.' }); }
            }).catch((e) => Swal.fire({ icon: 'error', title: 'Sunucu hatasi', text: e.message }));
    });
}

function escapeHtml(v) { if (v === null || v === undefined) return ''; return String(v).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }

document.addEventListener('DOMContentLoaded', () => {
    ['adetInput', 'aciklamaInput', 'stokDurum'].forEach((id) => { document.getElementById(id).addEventListener('input', updateWorkOrderSummary); document.getElementById(id).addEventListener('change', updateWorkOrderSummary); });
    loadManualLookup();
});
</script>
@endpush
