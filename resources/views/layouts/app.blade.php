<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Zem Uretim V3')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('admin.index') }}"><i class="bi bi-gear-wide-connected me-1"></i>Zem Mobilya</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                {{-- İş Emri Havuzu --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.index') ? 'active' : '' }}" href="{{ route('admin.index') }}">
                        <i class="bi bi-collection me-1"></i>Havuz
                    </a>
                </li>

                @if(Auth::check() && Auth::user()->isAdmin())
                {{-- İş Emri Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('workorders.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-clipboard-check me-1"></i>İş Emirleri
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('workorders.create') }}"><i class="bi bi-plus-circle me-2"></i>İş Emri Ver</a></li>
                        <li><a class="dropdown-item" href="{{ route('workorders.bulk') }}"><i class="bi bi-stack me-2"></i>Toplu İş Emri</a></li>
                        <li><a class="dropdown-item" href="{{ route('workorders.history') }}"><i class="bi bi-clock-history me-2"></i>İş Emri Geçmişi</a></li>
                    </ul>
                </li>

                {{-- Sipariş Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-cart3 me-1"></i>Siparişler
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('orders.index') }}">Sipariş Yönetimi</a></li>
                        <li><a class="dropdown-item" href="{{ route('orders.special') }}">Özel Üretim Takip</a></li>
                        <li><a class="dropdown-item" href="{{ route('production.pending') }}">Üretim Bekleyen Özet</a></li>
                    </ul>
                </li>

                {{-- Görev Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-lines-fill me-1"></i>Görevler
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('tasks.assign') }}"><i class="bi bi-person-plus me-2"></i>Görev Atama</a></li>
                        <li><a class="dropdown-item" href="{{ route('reports.personnel') }}"><i class="bi bi-file-earmark-person me-2"></i>Personel Rapor</a></li>
                    </ul>
                </li>

                {{-- Stok Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('stocks.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-box-seam me-1"></i>Stoklar
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('stocks.index') }}">Stok Yönetimi</a></li>
                        <li><a class="dropdown-item" href="{{ route('stocks.critical') }}">Kritik Stok Eşik</a></li>
                    </ul>
                </li>

                {{-- Ürün Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('products.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-box me-1"></i>Ürünler
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('products.create') }}">Yeni Ürün Ekle</a></li>
                        <li><a class="dropdown-item" href="{{ route('products.tree') }}">Ürün Ağacı</a></li>
                        <li><a class="dropdown-item" href="{{ route('products.match') }}">Ürün Eşleştirme</a></li>
                        <li><a class="dropdown-item" href="{{ route('products.settings') }}">Ürün Özellikleri</a></li>
                    </ul>
                </li>

                {{-- Ayarlar Dropdown --}}
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-gear me-1"></i>Yönetim
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item" href="{{ route('admin.database') }}"><i class="bi bi-database me-2"></i>Veritabanı</a></li>
                        <li><a class="dropdown-item" href="{{ route('reports.statistics') }}"><i class="bi bi-bar-chart me-2"></i>İstatistikler</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.settings') }}"><i class="bi bi-sliders me-2"></i>Ayarlar</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.password') }}"><i class="bi bi-key me-2"></i>Şifre Değiştir</a></li>
                    </ul>
                </li>
                @endif
            </ul>
            @auth
            <span class="navbar-text me-3 text-light">
                <i class="bi bi-person-circle me-1"></i> {{ Auth::user()->name }}
                @if(Auth::user()->isAdmin())
                    <span class="badge bg-danger ms-1">Yönetici</span>
                @else
                    <span class="badge bg-secondary ms-1">Personel</span>
                @endif
            </span>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button class="btn btn-outline-light btn-sm" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Çıkış</button>
            </form>
            @endauth
        </div>
    </div>
</nav>

<div class="container-fluid mt-3 px-4">
    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
