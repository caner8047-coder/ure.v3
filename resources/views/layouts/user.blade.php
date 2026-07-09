@php
    $pageTitle = trim($__env->yieldContent('title', 'Görevlerim'));
    $userName = trim((string) (Auth::user()->name ?? 'Personel'));
    $isTasksPage = request()->routeIs('user.tasks') || request()->routeIs('user.dashboard') || request()->routeIs('legacy.user.dashboard');
    $isAvailablePage = request()->routeIs('user.available');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} — zolfa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
    <style>
        :root {
            --personnel-brand: var(--z-sidebar);
            --personnel-nav-active: rgba(13, 148, 136, 0.14);
            --personnel-nav-active-border: rgba(13, 148, 136, 0.22);
            --personnel-ready-line: var(--z-warning);
            --personnel-active-line: var(--z-success);
        }

        body.legacy-user-body {
            background: var(--z-bg);
            color: var(--z-text);
            margin: 0;
            padding-top: 64px;
        }

        .legacy-navbar {
            background: var(--personnel-brand);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            min-height: 64px;
        }

        .legacy-navbar .navbar-brand {
            align-items: center;
            color: #f3f4f6 !important;
            display: inline-flex;
            font-size: 0.95rem;
            font-weight: 600;
            gap: 10px;
            letter-spacing: 0;
        }

        .legacy-brand-logo {
            align-items: center;
            background: var(--z-accent);
            border-radius: 8px;
            color: #fff;
            display: inline-flex;
            flex: 0 0 36px;
            font-size: 0.85rem;
            font-weight: 700;
            height: 36px;
            justify-content: center;
            width: 36px;
        }

        .legacy-brand-name {
            line-height: 1;
        }

        .legacy-brand-version {
            color: #9ca3af;
            font-size: 0.62rem;
            font-weight: 700;
            vertical-align: super;
        }

        .legacy-navbar .nav-link {
            border-radius: var(--z-radius-sm);
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 8px 10px !important;
        }

        .legacy-navbar .nav-link:hover,
        .legacy-navbar .nav-link.active {
            background: var(--personnel-nav-active);
            box-shadow: inset 0 0 0 1px var(--personnel-nav-active-border);
            color: #fff !important;
        }

        .legacy-navbar .navbar-toggler {
            border-color: rgba(255, 255, 255, 0.2);
            border-radius: var(--z-radius-sm);
            padding: 6px 8px;
        }

        .legacy-user-section {
            align-items: center;
            color: #fff;
            display: flex;
            gap: 10px;
        }

        .legacy-avatar {
            align-items: center;
            background: var(--z-accent-soft);
            border: 1px solid rgba(13, 148, 136, 0.45);
            border-radius: 50%;
            display: inline-flex;
            height: 35px;
            justify-content: center;
            min-width: 35px;
            width: 35px;
        }

        .legacy-avatar-wrap {
            display: inline-flex;
            position: relative;
        }

        .legacy-notification-badge {
            align-items: center;
            background: var(--z-danger);
            border: 2px solid var(--personnel-brand);
            border-radius: 999px;
            color: #fff;
            display: none;
            font-size: 0.62rem;
            font-weight: 800;
            height: 19px;
            justify-content: center;
            line-height: 1;
            min-width: 19px;
            padding: 0 5px;
            position: absolute;
            right: -6px;
            top: -6px;
        }

        .legacy-avatar-wrap.has-unread .legacy-avatar {
            animation: personnelNotifyPulse 1.05s ease-in-out infinite;
            background: rgba(220, 38, 38, 0.18);
            border-color: rgba(248, 113, 113, 0.9);
            color: #fecaca;
        }

        .legacy-avatar-wrap.has-unread .legacy-notification-badge {
            display: inline-flex;
        }

        .legacy-menu-message-badge {
            margin-left: auto;
        }

        @keyframes personnelNotifyPulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.7);
                transform: scale(1);
            }

            50% {
                box-shadow: 0 0 0 8px rgba(248, 113, 113, 0);
                transform: scale(1.08);
            }
        }

        .legacy-logout-button {
            background: transparent;
            border: 0;
            border-radius: var(--z-radius-sm);
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.95rem;
            font-weight: 600;
            padding: 8px 0;
        }

        .legacy-logout-button:hover {
            color: #fecaca;
        }

        .legacy-main {
            padding: 24px 28px 40px;
        }

        h2.legacy-page-heading {
            color: var(--z-text);
            font-size: 1.35rem;
            font-weight: 600;
            margin-bottom: 22px;
            text-align: center;
        }

        .card-container {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            justify-items: center;
        }

        .task-card {
            background: var(--z-bg-card);
            border: 1px solid var(--z-border);
            border-radius: var(--z-radius-lg);
            box-shadow: var(--z-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            max-width: 292px;
            overflow: visible;
            padding: 18px 14px 14px;
            position: relative;
            width: 100%;
        }

        .task-card:hover {
            box-shadow: var(--z-shadow);
        }

        .card-true,
        .card-ready,
        .card-available {
            border-top: 4px solid var(--personnel-ready-line);
        }

        .card-false,
        .card-active {
            border-top: 4px solid var(--personnel-active-line);
        }

        .adet-badge {
            background: var(--z-text);
            border-radius: 999px;
            box-shadow: var(--z-shadow);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            left: 50%;
            padding: 6px 14px;
            position: absolute;
            top: -16px;
            transform: translateX(-50%);
            white-space: nowrap;
            z-index: 2;
        }

        .img-box {
            align-items: center;
            background: var(--z-bg-soft);
            border: 1px solid var(--z-border-light);
            border-radius: var(--z-radius);
            display: flex;
            height: 174px;
            justify-content: center;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
            width: 100%;
        }

        .task-img {
            border-radius: var(--z-radius);
            cursor: pointer;
            height: 100%;
            object-fit: cover;
            width: 100%;
        }

        .img-box:hover .task-img {
            transform: none;
        }

        .overlay-text {
            background: rgba(255, 255, 255, 0.84);
            border-top: 1px solid var(--z-border-light);
            bottom: 0;
            left: 0;
            padding: 7px 8px;
            position: absolute;
            right: 0;
            text-align: center;
        }

        .overlay-text .araurun-adi {
            color: var(--z-text);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.18;
        }

        .overlay-text .urun-adi {
            color: var(--z-text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.1;
        }

        .task-meta {
            color: var(--z-text-secondary);
            font-size: 0.9rem;
            margin-top: 8px;
            text-align: center;
        }

        .bekleyen {
            background: var(--z-bg-soft);
            border: 1px solid var(--z-border-light);
            border-radius: var(--z-radius-sm);
            color: var(--z-text);
            display: block;
            font-size: 0.85rem;
            margin-top: 8px;
            padding: 6px 10px;
        }

        .task-card .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 14px;
            width: 100%;
        }

        .task-card .btn {
            border-radius: var(--z-radius-sm) !important;
            flex: 1;
            font-size: 0.95rem;
            font-weight: 700;
            min-height: 46px;
            padding: 10px 0;
        }

        .task-card .btn-group .dependency-action-row {
            display: flex;
            gap: 10px;
            width: 100%;
        }

        .task-card .btn-group .dependency-main-btn {
            flex: 1 1 auto;
        }

        .task-card .btn-group .dependency-info-btn {
            align-items: center;
            display: inline-flex;
            flex: 0 0 46px;
            justify-content: center;
            padding: 0;
            width: 46px;
        }

        .dependency-modal {
            color: var(--z-text);
            text-align: left;
        }

        .dependency-modal h4 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .dependency-summary-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin: 10px 0 12px;
        }

        .dependency-summary-grid span {
            background: var(--z-bg-soft);
            border: 1px solid var(--z-border-light);
            border-radius: var(--z-radius-sm);
            color: var(--z-text-secondary);
            font-size: 0.78rem;
            padding: 7px 8px;
        }

        .dependency-summary-grid strong {
            color: var(--z-text);
            display: block;
            font-size: 0.95rem;
            margin-top: 2px;
        }

        .dependency-block {
            border: 1px solid var(--z-border);
            border-radius: var(--z-radius);
            margin-top: 12px;
            padding: 12px;
        }

        .dependency-person {
            background: #fff;
            border: 1px solid var(--z-border-light);
            border-radius: var(--z-radius-sm);
            margin-top: 8px;
            padding: 10px;
        }

        .dependency-person-title {
            align-items: flex-start;
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }

        .dependency-person-title strong {
            display: block;
            font-size: 0.9rem;
            line-height: 1.2;
        }

        .dependency-person-title small,
        .dependency-person p {
            color: var(--z-text-secondary);
            font-size: 0.78rem;
        }

        .dependency-person p {
            margin: 7px 0 0;
        }

        .dependency-person .btn {
            font-size: 0.78rem;
            font-weight: 700;
            margin-top: 8px;
        }

        .dependency-empty {
            background: var(--z-bg-soft);
            border: 1px dashed var(--z-border);
            border-radius: var(--z-radius-sm);
            color: var(--z-text-secondary);
            font-size: 0.85rem;
            margin-top: 8px;
            padding: 10px;
            text-align: center;
        }

        .legacy-empty-state {
            color: var(--z-text-secondary);
            font-size: 0.95rem;
            font-weight: 600;
            padding: 24px 10px;
            text-align: center;
        }

        .legacy-loading-overlay {
            align-items: center;
            background: rgba(244, 245, 247, 0.86);
            display: none;
            inset: 0;
            justify-content: center;
            position: fixed;
            z-index: 9999;
        }

        .legacy-loading-overlay.active {
            display: flex;
        }

        .legacy-spinner {
            animation: legacySpin 1s linear infinite;
            border: 6px solid var(--z-border);
            border-radius: 50%;
            border-top-color: var(--z-accent);
            height: 58px;
            width: 58px;
        }

        @keyframes legacySpin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 991.98px) {
            body.legacy-user-body {
                padding-top: 59px;
            }

            .legacy-navbar {
                min-height: 59px;
            }

            .legacy-navbar .navbar-brand {
                font-size: 0.95rem;
            }

            .legacy-navbar .navbar-collapse {
                padding: 12px 0 6px;
            }

            .legacy-main {
                padding: 16px 12px 28px;
            }

            h2.legacy-page-heading {
                font-size: 1.25rem;
                margin-bottom: 22px;
            }

            .card-container {
                grid-template-columns: 1fr;
            }

            .task-card {
                max-width: 352px;
            }

            .task-card .btn-group {
                flex-direction: column;
            }

            .task-card .btn {
                width: 100%;
            }

            .task-card .btn-group .dependency-action-row {
                flex-direction: row;
            }

            .dependency-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="legacy-user-body">
<nav class="navbar navbar-expand-lg navbar-dark fixed-top legacy-navbar">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="{{ route('user.tasks') }}">
            <span class="legacy-brand-logo">zo</span>
            <span class="legacy-brand-name">zolfa <span class="legacy-brand-version">v0.3</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#legacyUserNavbar" aria-controls="legacyUserNavbar" aria-expanded="false" aria-label="Menüyü aç">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="legacyUserNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link {{ $isTasksPage ? 'active' : '' }}" href="{{ route('user.tasks') }}">
                        <i class="bi bi-clipboard-check me-1"></i>Devam Eden Görevlerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $isAvailablePage ? 'active' : '' }}" href="{{ route('user.available') }}">
                        <i class="bi bi-hand-index-thumb me-1"></i>Alınabilecek Görevler
                    </a>
                </li>
            </ul>

            @auth
                <div class="legacy-user-section">
                    <span class="legacy-avatar-wrap" id="personnelNotificationIcon">
                        <span class="legacy-avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
                        <span class="legacy-notification-badge" id="personnelNotificationBadge" aria-label="Okunmamış mesaj">0</span>
                    </span>
                    <div class="dropdown">
                        <a class="dropdown-toggle text-white text-decoration-none fw-semibold" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Personel Menüsü
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><a class="dropdown-item" href="{{ route('user.completed') }}"><i class="bi bi-check2-circle me-2 text-warning"></i>Tamamlanan Görevler</a></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('user.messages') }}">
                                    <i class="bi bi-envelope text-warning"></i>
                                    <span>Mesajlar</span>
                                    <span class="soft-badge danger legacy-menu-message-badge d-none" id="personnelMenuMessageBadge">0</span>
                                </a>
                            </li>
                            <li><a class="dropdown-item" href="{{ route('user.password') }}"><i class="bi bi-key me-2 text-warning"></i>Şifre Değiştir</a></li>
                        </ul>
                    </div>

                    <form id="legacyLogoutForm" method="POST" action="{{ route('logout') }}" class="ms-lg-3">
                        @csrf
                        <button class="legacy-logout-button" type="button" onclick="confirmLegacyLogout()">
                            <i class="bi bi-box-arrow-right me-1"></i>Çıkış Yap
                        </button>
                    </form>
                </div>
            @endauth
        </div>
    </div>
</nav>

<main class="container-fluid legacy-main">
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@include('layouts.partials.ui-text-normalizer')
<script>
    const personnelMessagesUrl = @json(route('user.messages'));
    const personnelIsMessagesPage = @json(request()->routeIs('user.messages') || request()->routeIs('legacy.user.messages'));
    let personnelLastUnreadCount = 0;
    let personnelLastToastMessageNo = 0;
    let personnelOriginalTitle = document.title;

    window.escapeHtml = function(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    };

    window.toInt = function(value) {
        const parsed = parseInt(value ?? 0, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    window.legacyTaskImage = function(value) {
        const raw = String(value ?? '').trim();
        if (!raw) return '/Resimler/resimYok.png';
        if (/^https?:\/\//i.test(raw) || raw.startsWith('/storage/') || raw.startsWith('/Resimler/')) return raw;
        if (raw.startsWith('/')) return raw;
        return '/Resimler/' + raw.split('/').map((part) => encodeURIComponent(part)).join('/');
    };

    window.showLegacyImage = function(url) {
        if (!window.Swal) return;
        Swal.fire({
            imageUrl: url,
            imageAlt: 'Ürün resmi',
            showConfirmButton: false,
            background: '#f4f5f7'
        });
    };

    window.dependencyInfoButtonHtml = function(taskNo, classes = 'btn btn-outline-secondary dependency-info-btn') {
        const no = toInt(taskNo);
        if (!no) return '';

        return `<button type="button" class="${classes}" title="Bekleme detayını gör" aria-label="Bekleme detayını gör" onclick="showTaskDependencyInfo(${no})"><i class="bi bi-info-circle"></i></button>`;
    };

    window.buildTaskDependencyInfoHtml = function(data, taskNo) {
        const shortages = Array.isArray(data?.shortages) ? data.shortages : [];
        const task = data?.task || {};

        if (!shortages.length) {
            return `
                <div class="dependency-modal">
                    <p class="dependency-empty mb-0">
                        Bu görev için aktif alt parça eksiği bulunamadı. Stok veya planlama bilgisi yenilenmiş olabilir.
                    </p>
                </div>
            `;
        }

        const shortageHtml = shortages.map((shortage) => {
            const componentNo = toInt(shortage.component_no);
            const suppliers = Array.isArray(shortage.suppliers) ? shortage.suppliers : [];
            const relatedSuppliers = Array.isArray(shortage.related_suppliers) ? shortage.related_suppliers : [];
            const poolRows = Array.isArray(shortage.pool) ? shortage.pool : [];
            const supplierHtml = suppliers.length
                ? suppliers.map((supplier) => {
                    const canNotify = supplier.can_notify === true || toInt(supplier.can_notify) === 1;
                    const expectedQuantity = toInt(supplier.expected_quantity);
                    const notifyButton = canNotify
                        ? `<button type="button" class="btn btn-outline-primary btn-sm dependency-notify-btn" data-component-no="${componentNo}" data-supplier-task-no="${toInt(supplier.task_no)}"><i class="bi bi-bell me-1"></i>Bildirim gönder</button>`
                        : '';

                    return `
                        <div class="dependency-person">
                            <div class="dependency-person-title">
                                <div>
                                    <strong>${escapeHtml(supplier.personnel_name || 'Personel')}</strong>
                                    <small>${escapeHtml(supplier.department_name || supplier.status || 'Açık görev')}</small>
                                </div>
                                <span class="soft-badge warning">${expectedQuantity > 0 ? expectedQuantity : toInt(supplier.open_quantity)} adet</span>
                            </div>
                            <p>
                                ${escapeHtml(supplier.status || 'Açık görev')} · Açık: ${toInt(supplier.open_quantity)} ·
                                Hazır: ${toInt(supplier.ready_quantity)} · Bekleyen: ${toInt(supplier.waiting_quantity)}
                            </p>
                            <p>${escapeHtml(supplier.wait_reason || 'Personelin üretim girişi bekleniyor.')}</p>
                            ${notifyButton}
                            <div class="dependency-notice small mt-2" aria-live="polite"></div>
                        </div>
                    `;
                }).join('')
                : '<div class="dependency-empty">Bu siparişe bağlı personelde açık üretim bulunamadı.</div>';

            const relatedHtml = relatedSuppliers.length
                ? `
                    <h4>Aynı parçadan başka açık üretim</h4>
                    ${relatedSuppliers.map((supplier) => {
                        const expectedQuantity = toInt(supplier.expected_quantity);
                        return `
                            <div class="dependency-person">
                                <div class="dependency-person-title">
                                    <div>
                                        <strong>${escapeHtml(supplier.personnel_name || 'Personel')}</strong>
                                        <small>${escapeHtml(supplier.department_name || supplier.status || 'Açık görev')}</small>
                                    </div>
                                    <span class="soft-badge warning">${expectedQuantity > 0 ? expectedQuantity : toInt(supplier.open_quantity)} adet</span>
                                </div>
                                <p>
                                    ${escapeHtml(supplier.status || 'Açık görev')} · Açık: ${toInt(supplier.open_quantity)} ·
                                    Hazır: ${toInt(supplier.ready_quantity)} · Bekleyen: ${toInt(supplier.waiting_quantity)}
                                </p>
                                <p>${escapeHtml(supplier.relation_label || 'Başka sipariş/iz')}</p>
                            </div>
                        `;
                    }).join('')}
                `
                : '';

            const poolHtml = poolRows.length
                ? `
                    <div class="dependency-empty">
                        Havuzda atama bekleyen ${poolRows.reduce((sum, row) => sum + toInt(row.open_quantity), 0)} adet açık iş var.
                    </div>
                `
                : '';

            return `
                <div class="dependency-block">
                    <h4>${escapeHtml(shortage.component_name || 'Alt parça')}</h4>
                    <div class="dependency-summary-grid">
                        <span>Gerekli<strong>${toInt(shortage.required_quantity)} adet</strong></span>
                        <span>Eksik<strong>${toInt(shortage.missing_quantity)} adet</strong></span>
                        <span>Stok<strong>${toInt(shortage.stock_quantity)} adet</strong></span>
                        <span>Kullanılabilir<strong>${toInt(shortage.usable_quantity)} adet</strong></span>
                    </div>
                    <h4>Kimden gelecek?</h4>
                    ${supplierHtml}
                    ${relatedHtml}
                    ${poolHtml}
                </div>
            `;
        }).join('');

        return `
            <div class="dependency-modal">
                <p class="mb-2">
                    <strong>${escapeHtml(task.component_name || 'Görev')}</strong>
                    ${toInt(task.quantity_checked) > 0 ? `için ${toInt(task.quantity_checked)} adet kontrol edildi.` : ''}
                </p>
                ${shortageHtml}
            </div>
        `;
    };

    window.sendTaskDependencyNotification = function(taskNo, componentNo, supplierTaskNo, button = null) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const notice = button?.closest('.dependency-person')?.querySelector('.dependency-notice') || null;
        const originalHtml = button?.innerHTML || '';

        if (button) {
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Gönderiliyor';
        }
        if (notice) {
            notice.className = 'dependency-notice small mt-2 text-muted';
            notice.textContent = '';
        }

        return fetch(`/api/panel/task/${toInt(taskNo)}/notify-dependency`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                component_no: toInt(componentNo),
                supplier_task_no: toInt(supplierTaskNo)
            })
        })
            .then((response) => response.json().catch(() => ({})).then((payload) => ({ ok: response.ok, payload })))
            .then(({ ok, payload }) => {
                if (!ok || !payload.success) {
                    throw new Error(payload.message || 'Bildirim gönderilemedi.');
                }

                if (button) {
                    button.classList.remove('btn-outline-primary');
                    button.classList.add(payload.already_sent ? 'btn-outline-secondary' : 'btn-success');
                    button.innerHTML = payload.already_sent
                        ? '<i class="bi bi-check2 me-1"></i>Daha önce gönderildi'
                        : '<i class="bi bi-check2 me-1"></i>Gönderildi';
                }
                if (notice) {
                    notice.className = 'dependency-notice small mt-2 text-success';
                    notice.textContent = payload.message || 'Bildirim gönderildi.';
                }
            })
            .catch((error) => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalHtml;
                }
                if (notice) {
                    notice.className = 'dependency-notice small mt-2 text-danger';
                    notice.textContent = error.message || 'Bildirim gönderilemedi.';
                } else if (window.Swal) {
                    Swal.fire({ icon: 'error', title: 'Hata', text: error.message || 'Bildirim gönderilemedi.' });
                }
            });
    };

    window.showTaskDependencyInfo = function(taskNo) {
        const no = toInt(taskNo);
        if (!no || !window.Swal) return;

        Swal.fire({
            title: 'Bekleme detayı',
            html: '<div class="dependency-empty">Bağımlılık bilgisi yükleniyor...</div>',
            showConfirmButton: false,
            allowOutsideClick: false,
            background: '#f4f5f7'
        });

        fetch(`/api/panel/task/${no}/dependency-info`, { headers: { 'Accept': 'application/json' } })
            .then((response) => response.json().catch(() => ({})).then((payload) => ({ ok: response.ok, payload })))
            .then(({ ok, payload }) => {
                if (!ok || !payload.success) {
                    throw new Error(payload.message || 'Bekleme detayı alınamadı.');
                }

                Swal.fire({
                    title: 'Bekleme detayı',
                    html: buildTaskDependencyInfoHtml(payload, no),
                    confirmButtonText: 'Kapat',
                    confirmButtonColor: '#0d9488',
                    background: '#f4f5f7',
                    width: 640,
                    didOpen: (popup) => {
                        popup.querySelectorAll('.dependency-notify-btn').forEach((button) => {
                            button.addEventListener('click', () => {
                                sendTaskDependencyNotification(
                                    no,
                                    button.getAttribute('data-component-no'),
                                    button.getAttribute('data-supplier-task-no'),
                                    button
                                );
                            });
                        });
                    }
                });
            })
            .catch((error) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Bekleme detayı alınamadı',
                    text: error.message || 'Lütfen tekrar deneyin.',
                    confirmButtonColor: '#0d9488',
                    background: '#f4f5f7'
                });
            });
    };

    window.updatePersonnelNotificationState = function(unreadCount) {
        const count = Math.max(0, toInt(unreadCount));
        const icon = document.getElementById('personnelNotificationIcon');
        const badge = document.getElementById('personnelNotificationBadge');
        const menuBadge = document.getElementById('personnelMenuMessageBadge');
        const label = count > 99 ? '99+' : String(count);

        if (icon) {
            icon.classList.toggle('has-unread', count > 0);
            icon.setAttribute('title', count > 0 ? `${label} okunmamış mesaj` : 'Okunmamış mesaj yok');
        }
        if (badge) {
            badge.textContent = label;
        }
        if (menuBadge) {
            menuBadge.textContent = label;
            menuBadge.classList.toggle('d-none', count <= 0);
        }
        document.title = count > 0 && !personnelIsMessagesPage
            ? `(${label}) ${personnelOriginalTitle}`
            : personnelOriginalTitle;
    };

    window.showPersonnelNotificationToast = function(payload) {
        const count = Math.max(0, toInt(payload?.unread_count));
        const latestMessageNo = toInt(payload?.latest_message_no);
        if (count <= 0 || personnelIsMessagesPage || !window.Swal) return;
        if (latestMessageNo > 0 && latestMessageNo === personnelLastToastMessageNo) return;

        const storageKey = 'zolfaPersonnelLastUnreadToast';
        try {
            if (latestMessageNo > 0 && localStorage.getItem(storageKey) === String(latestMessageNo)) {
                return;
            }
            if (latestMessageNo > 0) {
                localStorage.setItem(storageKey, String(latestMessageNo));
            }
        } catch (error) {
            // localStorage kapaliysa uyari yine gosterilebilir.
        }
        personnelLastToastMessageNo = latestMessageNo;

        const preview = String(payload?.latest_preview || '').trim();
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: count > 1 ? `${count} okunmamış mesajınız var` : 'Yeni üretim bildirimi',
            text: preview || 'Personel menüsünden mesajlarınızı kontrol edin.',
            showConfirmButton: true,
            confirmButtonText: 'Mesajlara Git',
            showCancelButton: true,
            cancelButtonText: 'Kapat',
            timer: 9000,
            timerProgressBar: true,
            confirmButtonColor: '#0d9488',
            background: '#f4f5f7'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = personnelMessagesUrl;
            }
        });
    };

    window.refreshPersonnelNotifications = function() {
        const icon = document.getElementById('personnelNotificationIcon');
        if (!icon) return Promise.resolve();

        return fetch('/api/panel/messages/unread-count', { headers: { 'Accept': 'application/json' } })
            .then((response) => response.json().catch(() => ({})))
            .then((payload) => {
                if (payload && payload.success) {
                    const unreadCount = Math.max(0, toInt(payload.unread_count));
                    updatePersonnelNotificationState(unreadCount);
                    if (unreadCount > 0 && unreadCount >= personnelLastUnreadCount) {
                        showPersonnelNotificationToast(payload);
                    }
                    personnelLastUnreadCount = unreadCount;
                }
            })
            .catch(() => {});
    };

    window.markPersonnelMessagesRead = function() {
        const icon = document.getElementById('personnelNotificationIcon');
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        if (!icon) return Promise.resolve();

        return fetch('/api/panel/messages/mark-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify({})
        })
            .then((response) => response.json().catch(() => ({})))
            .then((payload) => {
                if (payload && payload.success) {
                    updatePersonnelNotificationState(0);
                    personnelLastUnreadCount = 0;
                }
            })
            .catch(() => {});
    };

    window.confirmLegacyLogout = function() {
        if (!window.Swal) {
            if (confirm('Çıkış yapmak istiyor musunuz?')) {
                document.getElementById('legacyLogoutForm').submit();
            }
            return;
        }

        Swal.fire({
            title: 'Çıkış yapmak istiyor musunuz?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, çıkış yap',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#0d9488',
            background: '#f4f5f7'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('legacyLogoutForm').submit();
            }
        });
    };

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
        window.refreshPersonnelNotifications();
        window.personnelNotificationInterval = window.setInterval(window.refreshPersonnelNotifications, 10000);
        window.addEventListener('focus', window.refreshPersonnelNotifications);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                window.refreshPersonnelNotifications();
            }
        });

        // ── Görev Listesi Canlı Senkronizasyon (20sn polling) ──
        // Her sayfa kendi window.refreshPersonnelTasks fonksiyonunu tanımlayabilir.
        // Layout otomatik olarak bu fonksiyonu periyodik olarak çağırır.
        let _personnelTaskRefreshInterval = null;
        function _startPersonnelTaskPolling() {
            if (_personnelTaskRefreshInterval) clearInterval(_personnelTaskRefreshInterval);
            _personnelTaskRefreshInterval = setInterval(() => {
                if (document.hidden) return;
                if (typeof window.refreshPersonnelTasks === 'function') {
                    window.refreshPersonnelTasks();
                }
            }, 20000);
        }
        _startPersonnelTaskPolling();

        window.addEventListener('focus', () => {
            if (typeof window.refreshPersonnelTasks === 'function') {
                window.refreshPersonnelTasks();
            }
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && typeof window.refreshPersonnelTasks === 'function') {
                window.refreshPersonnelTasks();
            }
        });
    });
</script>
@stack('scripts')
</body>
</html>
