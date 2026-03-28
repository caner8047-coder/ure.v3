@extends('layouts.user')
@section('title', 'Görevlerim - Zem Mobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Aktif Görevlerim</h4>
    <button class="btn btn-outline-primary btn-sm" onclick="loadGorevler()"><i class="bi bi-arrow-clockwise"></i> Yenile</button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr><th>#</th><th>Ürün</th><th>Bölüm</th><th>Toplam Adet</th><th>Kalan Adet</th><th>Başlangıç</th><th>İşlem</th></tr>
                </thead>
                <tbody id="gorevBody">
                    <tr><td colspan="7" class="text-center py-4 text-muted">Görevler yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted" id="gorevFooter">Toplam: 0 görev</div>
</div>
@endsection

@push('scripts')
<script>
function loadGorevler() {
    document.getElementById('gorevBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Görevler yükleniyor...</td></tr>';
    // Kullanıcının görevlerini getir
    // API çağrısı placeholder
    document.getElementById('gorevBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Henüz atanmış göreviniz yok.</td></tr>';
}
loadGorevler();
</script>
@endpush
