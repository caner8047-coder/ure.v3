@extends('layouts.user')
@section('title', 'Görev Raporlarım - Zem Mobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Görev Raporlarım</h4>
</div>

<div class="row mb-3">
    <div class="col-md-4"><div class="card bg-primary text-white text-center p-3"><h2 id="totalGorev">0</h2><small>Toplam Görev</small></div></div>
    <div class="col-md-4"><div class="card bg-success text-white text-center p-3"><h2 id="totalTamamlanan">0</h2><small>Tamamlanan</small></div></div>
    <div class="col-md-4"><div class="card bg-info text-white text-center p-3"><h2 id="totalUretim">0</h2><small>Toplam Üretim</small></div></div>
</div>

<div class="card shadow-sm">
    <div class="card-header">Aylık Performans</div>
    <div class="card-body">
        <p class="text-muted text-center">Performans verileri yükleniyor...</p>
    </div>
</div>
@endsection
