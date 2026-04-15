@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="stats-grid">
    <article class="metric-card">
        <p class="metric-label">Aktif Is Emirleri</p>
        <h3 class="metric-value">0</h3>
        <p class="metric-copy">Havuzda bekleyen is emri sayisi.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Tamamlanan Gorevler</p>
        <h3 class="metric-value">0</h3>
        <p class="metric-copy">Kapatilan gorev toplami.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Kritik Stok Uyarilari</p>
        <h3 class="metric-value">0</h3>
        <p class="metric-copy">Esik altindaki stok sayisi.</p>
    </article>
</div>

@if(Auth::user()->isAdmin())
    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <h3 class="section-title">Yonetici Paneli</h3>
                <p class="section-copy">Tum uretim ve stok fonksiyonlarina erisiminiz bulunmaktadir.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.index') }}" class="btn btn-primary"><i class="bi bi-grid-1x2 me-1"></i>Is Emri Havuzu</a>
            <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Stoklar</a>
            <a href="{{ route('reports.tasks') }}" class="btn btn-outline-secondary"><i class="bi bi-graph-up-arrow me-1"></i>Raporlar</a>
        </div>
    </div>
@else
    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <h3 class="section-title">Personel Paneli</h3>
                <p class="section-copy">Size atanan gorevleri buradan takip edebilirsiniz.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('user.dashboard') }}" class="btn btn-primary"><i class="bi bi-list-check me-1"></i>Gorevlerim</a>
        </div>
    </div>
@endif
@endsection
