@extends('layouts.app')
@section('title', 'Hakkimizda')

@section('content')
<div style="max-width: 640px; margin: 0 auto;">
    <div class="panel-surface" style="text-align: center;">
        <div style="width: 56px; height: 56px; background: var(--z-accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: white; font-weight: 700; font-size: 1.2rem;">ZT</div>
        <h2 class="section-title" style="font-size: 1.15rem; margin-bottom: 8px;">ZemMobilya Uretim Takip Sistemi</h2>
        <p class="muted-copy" style="font-size: 0.88rem; margin-bottom: 16px;">Akilli uretim planlama, siparis yonetimi ve personel gorev takip platformu.</p>
        <div style="height: 1px; background: var(--z-border); margin: 16px 0;"></div>
        <p class="muted-copy" style="font-size: 0.84rem;">Bu sistem, mobilya uretim sureclerinin ekspertiz seviyesinde yonetilmesi, siparis takibi, stok kontrolu ve personel performans analizi icin gelistirilmistir.</p>
        <p class="muted-copy" style="font-size: 0.78rem; margin-top: 12px; color: var(--z-text-muted);">&copy; {{ date('Y') }} ZemMobilya — Tum haklari saklidir.</p>
    </div>
</div>
@endsection
