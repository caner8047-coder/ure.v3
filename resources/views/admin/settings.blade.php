@extends('layouts.app')
@section('title', 'Ayarlar - ZemMobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-sliders me-2"></i>Sistem Ayarları</h4>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white">Genel Ayarlar</div>
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
        <div class="card shadow-sm">
            <div class="card-header bg-secondary text-white">Bakım İşlemleri</div>
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
        alert('Önbellek temizlendi!');
    }
}
</script>
@endpush
