@extends('layouts.user')
@section('title', 'Görev Detay - Zem Mobilya')

@section('content')
<div class="mb-3">
    <a href="{{ route('user.tasks') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Görevlere Dön</a>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-eye me-1"></i>Görev Detayı</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 mb-3"><label class="form-label text-muted small">Ürün</label><p class="fw-bold" id="urunAdi">-</p></div>
                    <div class="col-6 mb-3"><label class="form-label text-muted small">Bölüm</label><p class="fw-bold" id="bolumAdi">-</p></div>
                    <div class="col-4 mb-3"><label class="form-label text-muted small">Toplam Adet</label><p class="fw-bold" id="toplamAdet">0</p></div>
                    <div class="col-4 mb-3"><label class="form-label text-muted small">Kalan Adet</label><p class="fw-bold text-danger" id="kalanAdet">0</p></div>
                    <div class="col-4 mb-3"><label class="form-label text-muted small">Tamamlanan</label><p class="fw-bold text-success" id="tamamlananAdet">0</p></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-success text-white"><i class="bi bi-check2-square me-1"></i>Üretim Girişi</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Üretilen Adet</label>
                    <input type="number" id="uretimAdet" class="form-control" min="1" value="1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Not (Opsiyonel)</label>
                    <textarea id="uretimNotu" class="form-control" rows="2"></textarea>
                </div>
                <button class="btn btn-success w-100" onclick="uretimKaydet()"><i class="bi bi-save me-1"></i>Üretim Kaydet</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function uretimKaydet() {
    let adet = parseInt(document.getElementById('uretimAdet').value);
    if (adet < 1) { alert('Adet 0\'dan büyük olmalı!'); return; }
    alert('Üretim kaydedildi: ' + adet + ' adet');
}
</script>
@endpush
