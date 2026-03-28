<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Personel Paneli - Zem Mobilya')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #2c3e50;">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('user.dashboard') }}"><i class="bi bi-person-workspace me-1"></i>Zem Personel</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="userNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.dashboard') ? 'active' : '' }}" href="{{ route('user.dashboard') }}">
                        <i class="bi bi-house me-1"></i>Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.tasks') ? 'active' : '' }}" href="{{ route('user.tasks') }}">
                        <i class="bi bi-clipboard-check me-1"></i>Görevlerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.available') ? 'active' : '' }}" href="{{ route('user.available') }}">
                        <i class="bi bi-hand-index me-1"></i>Alınabilir Görevler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.completed') ? 'active' : '' }}" href="{{ route('user.completed') }}">
                        <i class="bi bi-check-circle me-1"></i>Tamamlananlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('user.report') ? 'active' : '' }}" href="{{ route('user.report') }}">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>Rapor
                    </a>
                </li>
            </ul>
            @auth
            <span class="navbar-text me-3 text-light">
                <i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}
                @if(Auth::user()->isAdmin())
                    <a href="{{ route('admin.index') }}" class="badge bg-warning text-dark ms-1 text-decoration-none">Admin Paneli</a>
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
