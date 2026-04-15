@extends('layouts.app')

@section('title', 'Is Emri Gecmisi')

@section('page-actions')
    <a href="{{ route('workorders.create') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-plus me-1"></i>Yeni Emir</a>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetHistoryFilters()"><i class="bi bi-arrow-counterclockwise me-1"></i>Sifirla</button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadHistory()"><i class="bi bi-search me-1"></i>Yenile</button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .history-date-range { display: none; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 12px; }
    .history-date-range.is-visible { display: grid; }
    @media (max-width: 720px) { .history-date-range { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card"><p class="metric-label">Toplam Kayit</p><h3 class="metric-value" id="summaryRecordsCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Benzersiz Siparis</p><h3 class="metric-value" id="summaryOrdersCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Iptal Edilen</p><h3 class="metric-value" id="summaryCancelledCard">0</h3></article>
        <article class="metric-card"><p class="metric-label">Stok Karsilanan</p><h3 class="metric-value" id="summaryStockCard">0</h3></article>
    </div>

    <div class="panel-surface">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="form-label" for="tarihFiltre">Tarih filtresi</label>
                <select id="tarihFiltre" class="form-select">
                    <option value="">Tumu</option><option value="bugun">Bugun</option><option value="hafta">Son 7 Gun</option><option value="ay">Son 1 Ay</option><option value="3ay">Son 3 Ay</option><option value="ozel">Ozel Aralik</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="form-label" for="islemTipi">Islem tipi</label>
                <select id="islemTipi" class="form-select">
                    <option value="Hepsi">Hepsi</option><option value="IsEmriVerildi">Is Emri Verildi</option><option value="IptalEdildi">Iptal Edildi</option><option value="StokKarsilandi">Stok Karsilandi</option>
                </select>
            </div>
            <div class="col-xl-4 col-md-6">
                <label class="form-label" for="aramaInput">Arama</label>
                <input type="text" id="aramaInput" class="form-control" placeholder="Urun, musteri, siparis no...">
            </div>
            <div class="col-xl-3 col-md-6">
                <button class="btn btn-primary btn-sm w-100" type="button" onclick="loadHistory()"><i class="bi bi-search me-1"></i>Ara</button>
            </div>
        </div>
        <div class="history-date-range" id="customDateRange">
            <div><label class="form-label" for="baslangic">Baslangic</label><input type="date" id="baslangic" class="form-control"></div>
            <div><label class="form-label" for="bitis">Bitis</label><input type="date" id="bitis" class="form-control"></div>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="panel-toolbar">
            <div class="panel-toolbar-copy"><h3>Is emri hareketleri</h3></div>
            <div class="panel-toolbar-meta"><span class="soft-badge" id="summaryTableState">Yukleniyor</span></div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm" id="historyTable">
                <thead><tr><th>#</th><th>Siparis No</th><th>Musteri</th><th>Urun Adi</th><th>Sistem Urun</th><th>Adet</th><th>Kategori</th><th>Islem Tipi</th><th>Islem Tarihi</th><th>Is Emri Tarihi</th><th>Kargo Son Teslim</th></tr></thead>
                <tbody id="historyBody"><tr><td colspan="11" class="text-center py-4 text-muted">Yukleniyor...</td></tr></tbody>
            </table>
        </div>
        <div class="px-4 pb-4 small text-muted" id="historyFooter">Toplam: 0 kayit</div>
    </section>

    <span id="summaryRecordsInline" style="display:none;">0</span>
    <span id="summaryOrdersInline" style="display:none;">0</span>
    <span id="summaryCancelledInline" style="display:none;">0</span>
    <span id="summaryUpdatedInline" style="display:none;">Bekleniyor</span>
    <span id="summaryStateChip" style="display:none;">Hazir</span>
    <span id="summaryDateMeta" style="display:none;">Tumu</span>
    <span id="summaryTypeMeta" style="display:none;">Hepsi</span>
    <span id="summarySearchMeta" style="display:none;">Bos</span>
    <span id="summaryFooterMeta" style="display:none;">0</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleDateRange() { document.getElementById('customDateRange').classList.toggle('is-visible', document.getElementById('tarihFiltre').value === 'ozel'); }
function resetHistoryFilters() { document.getElementById('tarihFiltre').value = ''; document.getElementById('baslangic').value = ''; document.getElementById('bitis').value = ''; document.getElementById('aramaInput').value = ''; document.getElementById('islemTipi').value = 'Hepsi'; toggleDateRange(); loadHistory(); }

function loadHistory() {
    const params = new URLSearchParams(); params.set('tarih', document.getElementById('tarihFiltre').value); params.set('baslangic', document.getElementById('baslangic').value); params.set('bitis', document.getElementById('bitis').value); params.set('arama', document.getElementById('aramaInput').value); params.set('islemTipi', document.getElementById('islemTipi').value);
    document.getElementById('summaryTableState').textContent = 'Yukleniyor'; document.getElementById('summaryTableState').className = 'soft-badge warning';
    fetch('/SiparisApi.ashx?action=getWorkOrderHistory&' + params.toString()).then((r) => r.json()).then((data) => {
        if (!data.success) { Swal.fire({ icon: 'error', title: 'Veri alinamadi', text: data.message }); return; }
        const items = data.items || []; const tbody = document.getElementById('historyBody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted">Kayit bulunamadi.</td></tr>'; document.getElementById('historyFooter').textContent = 'Toplam: 0 kayit'; updateHistorySummary([]); return; }
        tbody.innerHTML = items.map((item, i) => `<tr><td>${i + 1}</td><td>${escapeHtml(item.SiparisNo || '-')}</td><td>${escapeHtml(item.Musteri || '-')}</td><td>${escapeHtml(item.UrunAdi || '-')}</td><td>${escapeHtml(item.SistemUrunAdi || '-')}</td><td>${Number(item.Adet || 0).toLocaleString('tr-TR')}</td><td>${escapeHtml(item.Kategori || '-')}</td><td>${renderActionBadge(item.IslemTipi)}</td><td>${escapeHtml(item.IslemTarihi || '-')}</td><td>${escapeHtml(item.IsEmriTarihi || '-')}</td><td>${escapeHtml(item.KargoSonTeslim || '-')}</td></tr>`).join('');
        document.getElementById('historyFooter').textContent = `Toplam: ${(data.count || items.length).toLocaleString('tr-TR')} kayit`;
        updateHistorySummary(items);
        document.getElementById('summaryTableState').textContent = 'Guncel'; document.getElementById('summaryTableState').className = 'soft-badge success';
        document.getElementById('summaryUpdatedInline').textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    }).catch((e) => Swal.fire({ icon: 'error', title: 'Sunucu hatasi', text: e.message }));
}

function updateHistorySummary(items) {
    const unique = new Set(items.map((i) => i.SiparisNo).filter(Boolean)).size;
    const cancelled = items.filter((i) => i.IslemTipi === 'IptalEdildi').length;
    const stock = items.filter((i) => i.IslemTipi === 'StokKarsilandi').length;
    document.getElementById('summaryRecordsInline').textContent = items.length;
    document.getElementById('summaryOrdersInline').textContent = unique;
    document.getElementById('summaryCancelledInline').textContent = cancelled;
    document.getElementById('summaryRecordsCard').textContent = items.length;
    document.getElementById('summaryOrdersCard').textContent = unique;
    document.getElementById('summaryCancelledCard').textContent = cancelled;
    document.getElementById('summaryStockCard').textContent = stock;
    document.getElementById('summaryFooterMeta').textContent = items.length;
}

function renderActionBadge(type) { const c = type === 'IptalEdildi' ? 'danger' : type === 'StokKarsilandi' ? 'success' : 'dark'; return `<span class="soft-badge ${c}">${escapeHtml(type || '-')}</span>`; }
function escapeHtml(v) { if (v == null) return ''; return String(v).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tarihFiltre').addEventListener('change', toggleDateRange);
    loadHistory();
});
</script>
@endpush
