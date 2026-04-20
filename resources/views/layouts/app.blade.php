@php
    $pageTitle = trim($__env->yieldContent('title', 'Operasyon Merkezi'));
    $hasPageActions = trim($__env->yieldContent('page-actions')) !== '';

    $navGroups = [
        [
            'label' => 'Zolm API / Ana',
            'items' => [
                ['route' => 'admin.index', 'patterns' => ['admin.index'], 'icon' => 'bi bi-house-door', 'label' => 'Ana Sayfa'],
                ['route' => 'orders.index', 'patterns' => ['orders.index'], 'icon' => 'bi bi-bag-check', 'label' => 'Siparişler'],
                ['route' => 'production.pasif', 'patterns' => ['production.pasif'], 'icon' => 'bi bi-clock-history', 'label' => 'Pasif Devam Edenler'],
                ['route' => 'production.pending', 'patterns' => ['production.pending'], 'icon' => 'bi bi-hourglass-split', 'label' => 'Üretim Bekleyen Özet'],
                ['route' => 'products.match', 'patterns' => ['products.match'], 'icon' => 'bi bi-link-45deg', 'label' => 'Ürün Eşleştirme'],
                ['route' => 'orders.special', 'patterns' => ['orders.special'], 'icon' => 'bi bi-star', 'label' => 'Özel Üretim Takip'],
                ['route' => 'workorders.bulk', 'patterns' => ['workorders.bulk'], 'icon' => 'bi bi-file-earmark-spreadsheet', 'label' => 'Excel\'den Toplu Sipariş'],
                ['route' => 'workorders.center', 'patterns' => ['workorders.center', 'workorders.history'], 'icon' => 'bi bi-clock-history', 'label' => 'İş Emri Merkezi'],
            ],
        ],
        [
            'label' => 'Görüntüle',
            'items' => [
                ['route' => 'admin.messages', 'patterns' => ['admin.messages'], 'icon' => 'bi bi-chat-left-dots', 'label' => 'Mesajları Görüntüle'],
                ['route' => 'admin.idle', 'patterns' => ['admin.idle'], 'icon' => 'bi bi-person-x', 'label' => 'Boşta Olan Personeller'],
            ],
        ],
        [
            'label' => 'İş Emri Verme',
            'items' => [
                ['route' => 'workorders.create', 'patterns' => ['workorders.create'], 'icon' => 'bi bi-clipboard-plus', 'label' => 'Tekli İş Emri Oluştur'],
                ['route' => 'tasks.assign', 'patterns' => ['tasks.assign', 'admin.tasks'], 'icon' => 'bi bi-person-plus', 'label' => 'Personele Görev Ata'],
                ['route' => 'production.planning', 'patterns' => ['production.planning'], 'icon' => 'bi bi-calendar-check', 'label' => 'Üretim Planlama'],
            ],
        ],
        [
            'label' => 'Raporlama',
            'items' => [
                ['route' => 'reports.personnel', 'patterns' => ['reports.personnel'], 'icon' => 'bi bi-person-lines-fill', 'label' => 'Personel Raporları'],
                ['route' => 'reports.tasks', 'patterns' => ['reports.tasks'], 'icon' => 'bi bi-list-check', 'label' => 'Görev Raporları'],
                ['route' => 'reports.statistics', 'patterns' => ['reports.statistics'], 'icon' => 'bi bi-pie-chart', 'label' => 'İstatistikler'],
                ['route' => 'reports.performance', 'patterns' => ['reports.performance'], 'icon' => 'bi bi-graph-up', 'label' => 'İşçi Performans'],
            ],
        ],
        [
            'label' => 'Stoklar & Ürün',
            'items' => [
                ['route' => 'stocks.index', 'patterns' => ['stocks.index', 'admin.stocks'], 'icon' => 'bi bi-box-seam', 'label' => 'Stok Listesi'],
                ['route' => 'stocks.critical', 'patterns' => ['stocks.critical'], 'icon' => 'bi bi-exclamation-triangle', 'label' => 'Kritik Stok Eşiği'],
                ['route' => 'products.create', 'patterns' => ['products.create'], 'icon' => 'bi bi-plus-square', 'label' => 'Yeni Ürün'],
                ['route' => 'products.create', 'patterns' => [], 'icon' => 'bi bi-node-plus', 'label' => 'Yeni Ürün Türet'],
                ['route' => 'products.tree', 'patterns' => ['products.tree'], 'icon' => 'bi bi-diagram-3', 'label' => 'Ürün Ağacı'],
                ['route' => 'products.settings', 'patterns' => ['products.settings'], 'icon' => 'bi bi-gear-wide-connected', 'label' => 'Ürün Ayarları'],
            ],
        ],
        [
            'label' => 'Sistem & Ayarlar',
            'items' => [
                ['route' => 'admin.database', 'patterns' => ['admin.database'], 'icon' => 'bi bi-database', 'label' => 'Veritabanı'],
                ['route' => 'admin.settings', 'patterns' => ['admin.settings'], 'icon' => 'bi bi-sliders', 'label' => 'Ayarlar'],
                ['route' => 'admin.password', 'patterns' => ['admin.password'], 'icon' => 'bi bi-key', 'label' => 'Şifre Yönetimi'],
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
    <title>{{ $pageTitle }} — Zem Üretim</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="ui-shell">
<div class="crm-shell" data-crm-shell>
    <aside class="crm-sidebar">
        <div class="crm-sidebar-brand">
            <div class="crm-sidebar-logo">ZM</div>
            <div class="crm-sidebar-copy">
                <h1>Zem Üretim</h1>
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

        // Click outside to collapse on desktop, or inside main area
        document.addEventListener('click', (e) => {
            if (window.innerWidth > 1024) {
                if (shell.classList.contains('sidebar-expanded') && !sidebar.contains(e.target)) {
                    // Avoid collapsing if they clicked the hamburger toggle
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
