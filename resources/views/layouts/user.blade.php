@php
    $pageTitle = trim($__env->yieldContent('title', 'Personel Paneli'));
    $hasPageActions = trim($__env->yieldContent('page-actions')) !== '';

    $navGroups = [
        [
            'label' => 'Çalışma Alanı',
            'items' => [
                ['route' => 'user.dashboard', 'patterns' => ['user.dashboard'], 'icon' => 'bi bi-grid-1x2-fill', 'label' => 'Panelim'],
                ['route' => 'user.tasks', 'patterns' => ['user.tasks', 'user.task-detail'], 'icon' => 'bi bi-list-check', 'label' => 'Görevlerim'],
                ['route' => 'user.available', 'patterns' => ['user.available'], 'icon' => 'bi bi-hand-index-thumb', 'label' => 'Alınabilir İşler'],
            ],
        ],
        [
            'label' => 'Takip',
            'items' => [
                ['route' => 'user.completed', 'patterns' => ['user.completed'], 'icon' => 'bi bi-check2-circle', 'label' => 'Tamamlananlar'],
                ['route' => 'user.report', 'patterns' => ['user.report', 'user.assigned'], 'icon' => 'bi bi-graph-up', 'label' => 'Raporlar'],
                ['route' => 'user.messages', 'patterns' => ['user.messages'], 'icon' => 'bi bi-chat-left-dots', 'label' => 'Mesajlar'],
            ],
        ],
        [
            'label' => 'Profil',
            'items' => [
                ['route' => 'user.password', 'patterns' => ['user.password'], 'icon' => 'bi bi-shield-lock', 'label' => 'Güvenlik'],
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — Zem Personel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="ui-shell">
<div class="crm-shell" data-crm-shell>
    <aside class="crm-sidebar">
        <div class="crm-sidebar-brand">
            <div class="crm-sidebar-logo">ZT</div>
            <div class="crm-sidebar-copy">
                <h2>Zem Personel</h2>
            </div>
        </div>

        @foreach ($navGroups as $group)
            <section class="crm-nav-group">
                <div class="crm-nav-group-label">{{ $group['label'] }}</div>
                <div class="crm-nav-list">
                    @foreach ($group['items'] as $item)
                        @php
                            $isActive = collect($item['patterns'] ?? [$item['route']])
                                ->contains(fn ($pattern) => request()->routeIs($pattern));
                        @endphp
                        <a href="{{ route($item['route']) }}" class="crm-nav-link {{ $isActive ? 'active' : '' }}">
                            <i class="{{ $item['icon'] }}"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endforeach
    </aside>

    <div class="crm-main">
        <header class="crm-topbar">
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="crm-sidebar-toggle" data-sidebar-toggle aria-label="Menüyü aç">
                    <i class="bi bi-list"></i>
                </button>
                <h1 class="crm-page-title">{{ $pageTitle }}</h1>
            </div>

            <div class="crm-topbar-actions">
                @if ($hasPageActions)
                    @yield('page-actions')
                @endif

                @auth
                    <div class="crm-user-pill">
                        <i class="bi bi-person-circle"></i>
                        <span>{{ Auth::user()->name }}</span>
                        <span class="crm-role-badge {{ Auth::user()->isAdmin() ? 'admin' : 'user' }}">
                            {{ Auth::user()->isAdmin() ? 'Admin' : 'Personel' }}
                        </span>
                    </div>

                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-up-right-square me-1"></i>Admin
                        </a>
                    @endif

                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <main class="crm-page">
            @yield('content')
        </main>
    </div>

    <div class="crm-sidebar-backdrop" data-sidebar-close></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@include('layouts.partials.ui-text-normalizer')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const shell = document.querySelector('[data-crm-shell]');
        if (!shell) return;

        const sidebar = shell.querySelector('.crm-sidebar');

        const closeSidebar = () => {
            shell.classList.remove('sidebar-open');
            shell.classList.remove('sidebar-expanded');
        };
        const toggleSidebar = () => {
            if (window.innerWidth > 1024) {
                shell.classList.toggle('sidebar-expanded');
            } else {
                shell.classList.toggle('sidebar-open');
            }
        };

        shell.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.addEventListener('click', toggleSidebar);
        });

        shell.querySelectorAll('[data-sidebar-close]').forEach((target) => {
            target.addEventListener('click', closeSidebar);
        });

        // Desktop click-to-expand logic
        if (sidebar) {
            sidebar.addEventListener('click', () => {
                if (window.innerWidth > 1024 && !shell.classList.contains('sidebar-expanded')) {
                    shell.classList.add('sidebar-expanded');
                }
            });
        }

        // Click outside to collapse on desktop
        document.addEventListener('click', (e) => {
            if (window.innerWidth > 1024) {
                if (shell.classList.contains('sidebar-expanded') && !sidebar.contains(e.target)) {
                    if (!e.target.closest('[data-sidebar-toggle]')) {
                        shell.classList.remove('sidebar-expanded');
                    }
                }
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) closeSidebar();
        });
    });
</script>
@stack('scripts')
<script>
    // Mobil Tablolar için Otomatik Veri Hizalama (Data Label Ataması)
    window.initMobileTables = function() {
        document.querySelectorAll('table.table-modern').forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            table.querySelectorAll('tbody tr').forEach(row => {
                Array.from(row.querySelectorAll('td')).forEach((td, index) => {
                    if (headers[index] && !td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        window.initMobileTables();
        
        // Dinamik (AJAX) Tablo Değişimlerini İzleme
        const observer = new MutationObserver(() => {
            window.initMobileTables();
        });
        
        document.querySelectorAll('.crm-main, .panel-surface').forEach(panel => {
            observer.observe(panel, { childList: true, subtree: true });
        });
    });
</script>
</body>
</html>
