@extends('layouts.user')
@section('title', 'Alınabilir Görevler - Zem Mobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-hand-index me-2"></i>Alınabilir Görevler</h4>
    <button class="btn btn-outline-primary btn-sm" onclick="loadAlinabilir()"><i class="bi bi-arrow-clockwise"></i> Yenile</button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr><th>#</th><th>Ürün</th><th>Bölüm</th><th>Adet</th><th>Tarih</th><th>İşlem</th></tr>
                </thead>
                <tbody id="alinabilirBody">
                    <tr><td colspan="6" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function loadAlinabilir() {
    document.getElementById('alinabilirBody').innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Henüz alınabilir görev yok.</td></tr>';
}

function gorevAl(gorevNo) {
    if (!confirm('Bu görevi almak istediğinize emin misiniz?')) return;
    alert('Görev alındı: #' + gorevNo);
    loadAlinabilir();
}
loadAlinabilir();
</script>
@endpush
