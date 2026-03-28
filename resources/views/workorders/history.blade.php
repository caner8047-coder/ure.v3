@extends('layouts.app')
@section('title', 'İş Emri Geçmişi - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>İş Emri Geçmişi</h4>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Tarih Filtresi</label>
                <select id="tarihFiltre" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <option value="bugun">Bugün</option>
                    <option value="hafta">Son 7 Gün</option>
                    <option value="ay">Son 1 Ay</option>
                    <option value="3ay">Son 3 Ay</option>
                    <option value="ozel">Özel Aralık</option>
                </select>
            </div>
            <div class="col-md-2 d-none" id="ozelTarihler">
                <label class="form-label small">Başlangıç</label>
                <input type="date" id="baslangic" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-none" id="ozelTarihler2">
                <label class="form-label small">Bitiş</label>
                <input type="date" id="bitis" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small">İşlem Tipi</label>
                <select id="islemTipi" class="form-select form-select-sm">
                    <option value="Hepsi">Hepsi</option>
                    <option value="IsEmriVerildi">İş Emri Verildi</option>
                    <option value="IptalEdildi">İptal Edildi</option>
                    <option value="StokKarsilandi">Stok Karşılandı</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Arama</label>
                <input type="text" id="aramaInput" class="form-control form-control-sm" placeholder="Ürün, müşteri, sipariş no...">
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary btn-sm w-100" onclick="loadHistory()"><i class="bi bi-search"></i> Ara</button>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0" id="historyTable">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Sipariş No</th>
                        <th>Müşteri</th>
                        <th>Ürün Adı</th>
                        <th>Sistem Ürün</th>
                        <th>Adet</th>
                        <th>Kategori</th>
                        <th>İşlem Tipi</th>
                        <th>İşlem Tarihi</th>
                        <th>İş Emri Tarihi</th>
                        <th>Kargo Son Teslim</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <tr><td colspan="11" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted" id="historyFooter">Toplam: 0 kayıt</div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('tarihFiltre').addEventListener('change', function() {
    let show = this.value === 'ozel';
    document.getElementById('ozelTarihler').classList.toggle('d-none', !show);
    document.getElementById('ozelTarihler2').classList.toggle('d-none', !show);
});

function loadHistory() {
    let params = new URLSearchParams();
    params.set('tarih', document.getElementById('tarihFiltre').value);
    params.set('baslangic', document.getElementById('baslangic').value);
    params.set('bitis', document.getElementById('bitis').value);
    params.set('arama', document.getElementById('aramaInput').value);
    params.set('islemTipi', document.getElementById('islemTipi').value);

    fetch('/SiparisApi.ashx?action=getWorkOrderHistory&' + params.toString())
        .then(r => r.json())
        .then(data => {
            if (!data.success) { alert(data.message); return; }
            let tbody = document.getElementById('historyBody');
            if (!data.items || data.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center py-4 text-muted">Kayıt bulunamadı.</td></tr>';
                document.getElementById('historyFooter').textContent = 'Toplam: 0 kayıt';
                return;
            }
            let html = '';
            data.items.forEach((item, i) => {
                let badge = item.IslemTipi === 'IptalEdildi' ? 'bg-danger' : item.IslemTipi === 'StokKarsilandi' ? 'bg-success' : 'bg-primary';
                html += `<tr>
                    <td>${i+1}</td>
                    <td>${item.SiparisNo || '-'}</td>
                    <td>${item.Musteri || '-'}</td>
                    <td>${item.UrunAdi || '-'}</td>
                    <td>${item.SistemUrunAdi || '-'}</td>
                    <td>${item.Adet || 0}</td>
                    <td>${item.Kategori || '-'}</td>
                    <td><span class="badge ${badge}">${item.IslemTipi}</span></td>
                    <td>${item.IslemTarihi || '-'}</td>
                    <td>${item.IsEmriTarihi || '-'}</td>
                    <td>${item.KargoSonTeslim || '-'}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
            document.getElementById('historyFooter').textContent = `Toplam: ${data.count} kayıt`;
        }).catch(e => { alert('Hata: ' + e.message); });
}

loadHistory();
</script>
@endpush
