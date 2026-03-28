@extends('layouts.app')
@section('title', 'Hakkımızda')
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h2 class="mb-4">ZemMobilya Üretim Takip Sistemi</h2>
                    <p class="lead">Akıllı üretim planlama, sipariş yönetimi ve personel görev takip platformu.</p>
                    <hr>
                    <p>Bu sistem, mobilya üretim süreçlerinin ekspertiz seviyesinde yönetilmesi, sipariş takibi, stok kontrolü ve personel performans analizi için geliştirilmiştir.</p>
                    <p class="text-muted">© {{ date('Y') }} ZemMobilya — Tüm hakları saklıdır.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
