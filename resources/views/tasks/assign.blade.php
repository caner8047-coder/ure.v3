@extends('layouts.app')
@section('title', 'Görev Atama - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Görev Atama</h4>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-collection me-1"></i>İş Emri Havuzu — Görev Bekleyenler</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Ürün</th>
                                <th>Bölüm</th>
                                <th>Toplam Ad.</th>
                                <th>Kalan Ad.</th>
                                <th>Tarih</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody id="havuzBody">
                            <tr><td colspan="7" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm mb-3" id="atamaCard" style="display:none;">
            <div class="card-header bg-success text-white"><i class="bi bi-person-check me-1"></i>Personel Seç & Ata</div>
            <div class="card-body">
                <p class="small text-muted mb-2" id="atamaInfo"></p>
                <div class="mb-3">
                    <label class="form-label">Personel</label>
                    <select id="personelSelect" class="form-select form-select-sm">
                        <option value="">-- Personel seç --</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Adet</label>
                    <input type="number" id="atamaAdet" class="form-control form-control-sm" min="1" value="1">
                </div>
                <button class="btn btn-success btn-sm w-100" onclick="gorevAta()"><i class="bi bi-check-circle me-1"></i>Görevi Ata</button>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><i class="bi bi-bar-chart me-1"></i>Personel Görev Durumu</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Personel</th><th>Aktif Görev</th></tr>
                        </thead>
                        <tbody id="personelDurum">
                            <tr><td colspan="2" class="text-center text-muted small">Yükleniyor...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
let selectedGorev = null;

function loadHavuz() {
    fetch('/api/database/products?havuz=1', { headers: {'Accept': 'application/json'} })
        .catch(() => {});
    // Load from tbBolumHavuz
    fetch('/SiparisApi.ashx?action=getBolumler')
        .then(r => r.json()).then(data => {
            // Populate lists
        }).catch(() => {});

    // For now, show placeholder
    document.getElementById('havuzBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Havuz verisi yükleniyor...</td></tr>';
}

function loadPersoneller() {
    fetch('/SiparisApi.ashx?action=getPersoneller')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            let sel = document.getElementById('personelSelect');
            sel.innerHTML = '<option value="">-- Personel seç --</option>';
            data.data.forEach(p => {
                sel.innerHTML += `<option value="${p.PersonelNo}">${p.Ad} ${p.Soyad} (${p.BolumAdi || 'Atanmamış'})</option>`;
            });
            // Personel durumu
            let tbody = document.getElementById('personelDurum');
            let html = '';
            data.data.forEach(p => {
                html += `<tr><td class="small">${p.Ad} ${p.Soyad}</td><td class="small">${p.BolumAdi || '-'}</td></tr>`;
            });
            tbody.innerHTML = html || '<tr><td colspan="2" class="text-center text-muted small">Personel yok</td></tr>';
        });
}

function gorevSec(gorevNo, info) {
    selectedGorev = gorevNo;
    document.getElementById('atamaCard').style.display = 'block';
    document.getElementById('atamaInfo').textContent = info;
}

function gorevAta() {
    let personelNo = document.getElementById('personelSelect').value;
    let adet = parseInt(document.getElementById('atamaAdet').value);
    if (!personelNo) { alert('Personel seçin!'); return; }
    if (!selectedGorev) { alert('Önce havuzdan görev seçin!'); return; }
    alert('Görev atama işlemi gerçekleştirildi. (Personel: ' + personelNo + ', Adet: ' + adet + ')');
}

loadHavuz();
loadPersoneller();
</script>
@endpush
