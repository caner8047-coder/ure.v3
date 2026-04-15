@extends('layouts.app')
@section('title', 'Ayarlar - ZemMobilya')

@section('content')
<div class="orders-panel-heading mb-4">
    <div>
        <p>Yonetim</p>
        <h3><i class="bi bi-sliders text-muted me-2"></i>Sistem Ayarları</h3>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="panel-surface mb-3">
            <h6 class="mb-3 fw-bold text-primary">Genel Ayarlar</h6>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Firma Adı</label>
                    <input type="text" class="form-control" value="Zem Mobilya" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sistem Versiyonu</label>
                    <input type="text" class="form-control" value="V3.0 (Laravel)" disabled>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-surface">
            <h6 class="mb-3 fw-bold text-secondary">Bakım İşlemleri</h6>
            <div class="card-body">
                <button class="btn btn-outline-warning btn-sm mb-2 w-100" onclick="clearCache()"><i class="bi bi-trash me-1"></i>Önbellek Temizle</button>
                <button class="btn btn-outline-info btn-sm w-100" onclick="alert('Veritabanı yedekleme yakında aktif olacak.')"><i class="bi bi-database-down me-1"></i>Veritabanı Yedekle</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function clearCache() {
    if (confirm('Önbelleği temizlemek istediğinize emin misiniz?')) {
        fetch('/api/admin/clear-cache', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message || 'Önbellek temizlendi!');
        })
        .catch(e => alert('Hata oluştu!'));
    }
}
</script>
@endpush
