@extends('layouts.app')
@section('title', 'Toplu İş Emri Verme - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-stack me-2"></i>Toplu İş Emri Verme</h4>
    <div>
        <button class="btn btn-success btn-sm" onclick="topluIsEmriVer()" id="btnTopluVer" disabled>
            <i class="bi bi-send me-1"></i>Seçilenlere İş Emri Ver (<span id="selectedCount">0</span>)
        </button>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-funnel me-1"></i>Üretim Bekleyen Siparişler Özeti</span>
        <div>
            <select id="kategoriSelect" class="form-select form-select-sm d-inline-block" style="width:200px;" onchange="loadSummary()">
                <option value="">Tüm Kategoriler</option>
            </select>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr>
                        <th><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                        <th>Ürün Adı</th>
                        <th>Sistem Adı</th>
                        <th>Toplam Adet</th>
                        <th>Sipariş Sayısı</th>
                        <th>En Yakın Kargo</th>
                        <th>Eşleşme</th>
                    </tr>
                </thead>
                <tbody id="summaryBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted" id="summaryFooter">Toplam: 0 ürün</div>
</div>
@endsection

@push('scripts')
<script>
let summaryData = [];

function loadSummary() {
    let kategori = document.getElementById('kategoriSelect').value;
    fetch('/SiparisApi.ashx?action=getSummary&kategori=' + encodeURIComponent(kategori))
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.message); return; }
            summaryData = data.summary || [];
            // Populate kategori dropdown
            let sel = document.getElementById('kategoriSelect');
            if (data.kategoriList && sel.options.length <= 1) {
                data.kategoriList.forEach(k => { sel.innerHTML += `<option value="${k}">${k}</option>`; });
            }
            renderSummary();
        });
}

function renderSummary() {
    let tbody = document.getElementById('summaryBody');
    if (summaryData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Üretim bekleyen sipariş yok.</td></tr>';
        document.getElementById('summaryFooter').textContent = 'Toplam: 0 ürün';
        return;
    }
    let html = '';
    summaryData.forEach((item, i) => {
        let eslesme = item.EslesenUrunNo > 0 ? `<span class="badge bg-success">Eşleşmiş</span>` : `<span class="badge bg-warning text-dark">Eşleşmemiş</span>`;
        html += `<tr>
            <td><input type="checkbox" class="rowCheck" data-index="${i}" onchange="updateSelection()" ${item.EslesenUrunNo > 0 ? '' : 'disabled'}></td>
            <td>${item.UrunAdi || '-'}</td>
            <td>${item.sistemAdi || '-'}</td>
            <td><strong>${item.ToplamAdet}</strong></td>
            <td>${item.SiparisSayisi}</td>
            <td>${item.EnYakinKargo || '-'}</td>
            <td>${eslesme}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
    document.getElementById('summaryFooter').textContent = `Toplam: ${summaryData.length} ürün, ${summaryData.reduce((s,i) => s + i.ToplamAdet, 0)} adet`;
}

function toggleAll() {
    let checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.rowCheck:not(:disabled)').forEach(cb => cb.checked = checked);
    updateSelection();
}

function updateSelection() {
    let count = document.querySelectorAll('.rowCheck:checked').length;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('btnTopluVer').disabled = count === 0;
}

function topluIsEmriVer() {
    let selected = [];
    document.querySelectorAll('.rowCheck:checked').forEach(cb => {
        let idx = parseInt(cb.dataset.index);
        selected.push(summaryData[idx]);
    });
    if (selected.length === 0) { alert('Sipariş seçin!'); return; }
    if (!confirm(`${selected.length} ürün için toplu iş emri verilecek. Onaylıyor musunuz?`)) return;
    alert('Toplu iş emri verme işlemi başlatıldı. Sonuçlar gelecektir.');
}

loadSummary();
</script>
@endpush
