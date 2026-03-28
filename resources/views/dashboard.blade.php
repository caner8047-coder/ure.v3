@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h4 class="card-title mb-4">Sistem Özeti</h4>
                
                @if(Auth::user()->isAdmin())
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Yönetici paneline hoşgeldiniz. Tüm üretim ve stok fonksiyonlarına erişiminiz bulunmaktadır.
                    </div>
                @else
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle me-2"></i>
                        Personel paneline hoşgeldiniz. Size atanan görevleri buradan takip edebilirsiniz.
                    </div>
                @endif
                
                <div class="row mt-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Aktif İş Emirleri</h5>
                                <p class="card-text fs-2">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Tamamlanan Görevler</h5>
                                <p class="card-text fs-2">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-dark mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Kritik Stok Uyarıları</h5>
                                <p class="card-text fs-2">0</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
