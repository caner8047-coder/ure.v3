@extends('layouts.user')
@section('title', 'Panel - Zem Mobilya')

@section('content')
<div class="row mb-3">
    <div class="col-12">
        <h4><i class="bi bi-speedometer2 me-2"></i>Hoş Geldiniz, {{ Auth::user()->name ?? 'Personel' }}!</h4>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white text-center p-3 mb-3">
            <h2 id="statAktifGorev">0</h2>
            <small>Aktif Görevlerim</small>
            <a href="{{ route('user.tasks') }}" class="btn btn-outline-light btn-sm mt-2">Görüntüle</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white text-center p-3 mb-3">
            <h2 id="statTamamlanan">0</h2>
            <small>Tamamlanan</small>
            <a href="{{ route('user.completed') }}" class="btn btn-outline-light btn-sm mt-2">Görüntüle</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white text-center p-3 mb-3">
            <h2 id="statAlinabilir">0</h2>
            <small>Alınabilir Görev</small>
            <a href="{{ route('user.available') }}" class="btn btn-outline-light btn-sm mt-2">Görüntüle</a>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark text-center p-3 mb-3">
            <h2 id="statBekleyen">0</h2>
            <small>Bekleyen</small>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><i class="bi bi-clock-history me-1"></i>Son Görevlerim</div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped mb-0">
                    <thead class="table-dark"><tr><th>Görev</th><th>Bölüm</th><th>Adet</th><th>Durum</th><th>Tarih</th></tr></thead>
                    <tbody id="sonGorevler">
                        <tr><td colspan="5" class="text-center text-muted py-3">Henüz görev yok.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header"><i class="bi bi-info-circle me-1"></i>Bilgilerim</div>
            <div class="card-body small">
                <p><strong>Ad:</strong> {{ Auth::user()->name ?? '-' }}</p>
                <p><strong>Soyad:</strong> {{ Auth::user()->surname ?? '-' }}</p>
                <p><strong>E-posta:</strong> {{ Auth::user()->email ?? '-' }}</p>
                <p><strong>Sicil No:</strong> {{ Auth::user()->personnel_no ?? '-' }}</p>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header"><i class="bi bi-megaphone me-1"></i>Duyurular</div>
            <div class="card-body small text-muted">Henüz duyuru yok.</div>
        </div>
    </div>
</div>
@endsection
