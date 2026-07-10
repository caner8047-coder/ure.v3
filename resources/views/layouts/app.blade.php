@php
    $pageTitle = trim($__env->yieldContent('title', 'Operasyon Merkezi'));
    $hasPageActions = trim($__env->yieldContent('page-actions')) !== '';

    $navGroups = [
        [
            'label' => 'Operasyon',
            'hint' => 'Sipariş ve günlük takip',
            'icon' => 'bi bi-bag-check',
            'items' => [
                ['route' => 'admin.index', 'patterns' => ['admin.index'], 'icon' => 'bi bi-house-door', 'label' => 'Ana Sayfa'],
                ['route' => 'orders.index', 'patterns' => ['orders.index'], 'icon' => 'bi bi-bag-check', 'label' => 'Siparişler'],
                ['route' => 'production.pasif', 'patterns' => ['production.pasif'], 'icon' => 'bi bi-clock-history', 'label' => 'Pasif Devam Edenler'],
                ['route' => 'production.pending', 'patterns' => ['production.pending'], 'icon' => 'bi bi-hourglass-split', 'label' => 'Üretim Bekleyen Özet'],
                ['route' => 'orders.special', 'patterns' => ['orders.special'], 'icon' => 'bi bi-star', 'label' => 'Özel Üretim Takip'],
            ],
        ],
        [
            'label' => 'İş Emirleri',
            'hint' => 'Oluşturma ve planlama',
            'icon' => 'bi bi-clipboard-check',
            'items' => [
                ['route' => 'workorders.center', 'patterns' => ['workorders.center', 'workorders.history'], 'icon' => 'bi bi-clock-history', 'label' => 'İş Emri Merkezi'],
                ['route' => 'tasks.assign', 'patterns' => ['tasks.assign', 'admin.tasks'], 'icon' => 'bi bi-person-plus', 'label' => 'Personele Görev Ata'],
                ['route' => 'production.planning', 'patterns' => ['production.planning'], 'icon' => 'bi bi-calendar-check', 'label' => 'Üretim Planlama'],
                ['route' => 'forecast.dashboard', 'patterns' => ['forecast.dashboard'], 'icon' => 'bi bi-cpu', 'label' => 'AI Talep Tahminleri'],
            ],
        ],
        [
            'label' => 'Ürün & Stok',
            'hint' => 'Eşleştirme ve stok yönetimi',
            'icon' => 'bi bi-box-seam',
            'items' => [
                ['route' => 'products.match', 'patterns' => ['products.match'], 'icon' => 'bi bi-link-45deg', 'label' => 'Ürün Eşleştirme'],
                ['route' => 'stocks.index', 'patterns' => ['stocks.index', 'admin.stocks'], 'icon' => 'bi bi-box-seam', 'label' => 'Stok Listesi'],
                ['route' => 'stocks.critical', 'patterns' => ['stocks.critical'], 'icon' => 'bi bi-exclamation-triangle', 'label' => 'Kritik Stok Eşiği'],
                ['route' => 'products.create', 'patterns' => ['products.create'], 'params' => ['tab' => 'add'], 'icon' => 'bi bi-plus-square', 'label' => 'Yeni Ürün'],
                ['route' => 'products.create', 'patterns' => ['products.create'], 'params' => ['tab' => 'derive'], 'icon' => 'bi bi-node-plus', 'label' => 'Yeni Ürün Türet'],
                ['route' => 'products.tree', 'patterns' => ['products.tree'], 'icon' => 'bi bi-diagram-3', 'label' => 'Ürün Ağacı'],
                ['route' => 'products.settings', 'patterns' => ['products.settings'], 'icon' => 'bi bi-gear-wide-connected', 'label' => 'Ürün Ayarları'],
            ],
        ],
        [
            'label' => 'Ekip & Raporlar',
            'hint' => 'Personel, mesaj ve analiz',
            'icon' => 'bi bi-people',
            'items' => [
                ['route' => 'admin.messages', 'patterns' => ['admin.messages'], 'icon' => 'bi bi-chat-left-dots', 'label' => 'Mesajları Görüntüle'],
                ['route' => 'reports.ongoing', 'patterns' => ['reports.ongoing'], 'icon' => 'bi bi-play-circle', 'label' => 'Devam Eden Görevler'],
                ['route' => 'admin.idle', 'patterns' => ['admin.idle'], 'icon' => 'bi bi-person-workspace', 'label' => 'Personel Üretim Takibi'],
                ['route' => 'reports.personnel', 'patterns' => ['reports.personnel'], 'icon' => 'bi bi-calendar3', 'label' => 'Tarihe Göre Görevler'],
                ['route' => 'reports.completed', 'patterns' => ['reports.completed'], 'icon' => 'bi bi-check2-circle', 'label' => 'Tamamlanan Görevler'],
                ['route' => 'reports.tasks', 'patterns' => ['reports.tasks'], 'icon' => 'bi bi-list-check', 'label' => 'Görev Raporları'],
                ['route' => 'reports.statistics', 'patterns' => ['reports.statistics'], 'icon' => 'bi bi-pie-chart', 'label' => 'İstatistikler'],
                ['route' => 'reports.performance', 'patterns' => ['reports.performance'], 'icon' => 'bi bi-graph-up', 'label' => 'İşçi Performans'],
            ],
        ],
        [
            'label' => 'Sistem',
            'hint' => 'Veri ve güvenlik ayarları',
            'icon' => 'bi bi-gear',
            'items' => [
                ['route' => 'admin.database', 'patterns' => ['admin.database'], 'icon' => 'bi bi-database', 'label' => 'Veritabanı'],
                ['route' => 'admin.ai-chat', 'patterns' => ['admin.ai-chat'], 'icon' => 'bi bi-robot', 'label' => 'AI Asistan'],
                ['route' => 'admin.settings', 'patterns' => ['admin.settings'], 'icon' => 'bi bi-sliders', 'label' => 'Ayarlar'],
                ['route' => 'admin.password', 'patterns' => ['admin.password'], 'icon' => 'bi bi-key', 'label' => 'Şifre Yönetimi'],
            ],
        ],
    ];

    $activeGroupIndex = collect($navGroups)->search(function ($group) {
        return collect($group['items'])->contains(function ($item) {
            return collect($item['patterns'] ?? [$item['route']])
                ->contains(fn ($pattern) => $pattern && request()->routeIs($pattern));
        });
    });
@endphp
<!DOCTYPE html>
<html lang="tr" class="ui-shell-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — zolfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
    @stack('styles')
    @vite(['resources/js/app.js'])
</head>
<body class="ui-shell">
<div class="crm-shell" data-crm-shell>
    <aside class="crm-sidebar">
        <div class="crm-sidebar-brand">
            <div class="crm-sidebar-logo">zo</div>
            <div class="crm-sidebar-copy">
                <h1>zolfa <span class="crm-brand-version">v0.3</span></h1>
            </div>
        </div>

        @foreach ($navGroups as $group)
            @php
                $groupKey = 'admin-nav-' . $loop->index;
                $groupActive = collect($group['items'])->contains(function ($item) {
                    return collect($item['patterns'] ?? [$item['route']])
                        ->contains(fn ($pattern) => $pattern && request()->routeIs($pattern));
                });
                $isGroupOpen = $groupActive || ($activeGroupIndex === false && $loop->first);
            @endphp
            <section class="crm-nav-group has-accordion {{ $isGroupOpen ? 'is-open' : '' }} {{ $groupActive ? 'is-active' : '' }}" data-nav-group="{{ $groupKey }}">
                <button
                    type="button"
                    class="crm-nav-group-toggle"
                    data-nav-group-toggle
                    aria-controls="{{ $groupKey }}-list"
                    aria-expanded="{{ $isGroupOpen ? 'true' : 'false' }}"
                    aria-label="{{ $group['label'] }} menüsünü aç/kapat"
                    title="{{ $group['label'] }}"
                >
                    <span class="crm-nav-group-toggle-main">
                        <span class="crm-nav-group-icon" aria-hidden="true">
                            <i class="{{ $group['icon'] }}"></i>
                        </span>
                        <span class="crm-nav-group-text">
                            <span class="crm-nav-group-title">{{ $group['label'] }}</span>
                            <span class="crm-nav-group-hint">{{ $group['hint'] }}</span>
                        </span>
                    </span>
                    <span class="crm-nav-group-count">{{ count($group['items']) }}</span>
                    <i class="bi bi-chevron-down crm-nav-group-chevron"></i>
                </button>
                <div class="crm-nav-list" id="{{ $groupKey }}-list" aria-hidden="{{ $isGroupOpen ? 'false' : 'true' }}">
                    @foreach ($group['items'] as $item)
                        @php
                            $routeMatches = collect($item['patterns'] ?? [$item['route']])
                                ->contains(fn ($pattern) => $pattern && request()->routeIs($pattern));
                            $queryMatches = collect($item['params'] ?? [])
                                ->every(function ($value, $key) {
                                    $actual = request()->query($key);
                                    if ($key === 'tab' && $actual === null) {
                                        $actual = 'add';
                                    }
                                    return (string) $actual === (string) $value;
                                });
                            $isActive = $routeMatches && $queryMatches;
                        @endphp
                        <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="crm-nav-link {{ $isActive ? 'active' : '' }}">
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
        let openActiveGroup = () => {};

        const closeSidebar = () => {
            shell.classList.remove('sidebar-open');
            shell.classList.remove('sidebar-expanded');
        };
        const toggleSidebar = () => {
            if (window.innerWidth > 1024) {
                const shouldExpand = !shell.classList.contains('sidebar-expanded');
                shell.classList.toggle('sidebar-expanded', shouldExpand);
                if (shouldExpand) openActiveGroup();
            } else {
                shell.classList.toggle('sidebar-open');
                if (shell.classList.contains('sidebar-open')) openActiveGroup();
            }
        };

        shell.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.addEventListener('click', toggleSidebar);
        });

        shell.querySelectorAll('[data-sidebar-close]').forEach((target) => {
            target.addEventListener('click', closeSidebar);
        });

        shell.querySelectorAll('.crm-nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) closeSidebar();
            });
        });

        const navStorageKey = 'zem-admin-sidebar-groups-v1';
        const navGroups = Array.from(shell.querySelectorAll('[data-nav-group]'));
        let savedGroups = {};
        try {
            savedGroups = JSON.parse(localStorage.getItem(navStorageKey) || '{}') || {};
        } catch (e) {
            savedGroups = {};
        }

        const setGroupOpen = (group, isOpen) => {
            group.classList.toggle('is-open', isOpen);
            const toggle = group.querySelector('[data-nav-group-toggle]');
            if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            const list = group.querySelector('.crm-nav-list');
            if (list) list.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        };

        const closeOtherGroups = (currentGroup) => {
            navGroups.forEach((group) => {
                if (group !== currentGroup) setGroupOpen(group, false);
            });
        };

        const persistGroupState = () => {
            const nextState = {};
            navGroups.forEach((group) => {
                nextState[group.dataset.navGroup] = group.classList.contains('is-open');
            });
            localStorage.setItem(navStorageKey, JSON.stringify(nextState));
        };

        navGroups.forEach((group) => {
            const key = group.dataset.navGroup;
            const hasSavedState = Object.prototype.hasOwnProperty.call(savedGroups, key);
            if (hasSavedState && !group.classList.contains('is-active')) {
                setGroupOpen(group, Boolean(savedGroups[key]));
            }

            const toggle = group.querySelector('[data-nav-group-toggle]');
            if (!toggle) return;

            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();

                if (window.innerWidth > 1024 && !shell.classList.contains('sidebar-expanded')) {
                    shell.classList.add('sidebar-expanded');
                    closeOtherGroups(group);
                    setGroupOpen(group, true);
                    persistGroupState();
                    return;
                }

                const shouldOpen = !group.classList.contains('is-open') || group.classList.contains('is-active');
                if (shouldOpen) closeOtherGroups(group);
                setGroupOpen(group, shouldOpen);
                persistGroupState();
            });
        });

        openActiveGroup = () => {
            const activeGroup = navGroups.find((group) => group.classList.contains('is-active'));
            if (activeGroup) {
                closeOtherGroups(activeGroup);
                setGroupOpen(activeGroup, true);
                persistGroupState();
            }
        };
        openActiveGroup();

        // Desktop click-to-expand logic
        if (sidebar) {
            sidebar.addEventListener('click', () => {
                if (window.innerWidth > 1024 && !shell.classList.contains('sidebar-expanded')) {
                    shell.classList.add('sidebar-expanded');
                    openActiveGroup();
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

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSidebar();
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
