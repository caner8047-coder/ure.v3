@extends('layouts.app')

@section('title', 'Sipariş Yönetimi')
@section('subtitle', 'Excel yükleme, sipariş akış takibi, eşleştirme ve toplu üretim operasyonları tek ekranda.')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
        <i class="bi bi-arrow-counterclockwise me-2"></i>Sıfırla
    </button>
    <button type="button" class="btn btn-primary" onclick="loadOrders()">
        <i class="bi bi-arrow-clockwise me-2"></i>Yenile
    </button>
@endsection

@section('content')        <!-- Kütüphaneler -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        <style>
            /* Upload Card */
            .upload-card {
                background: var(--z-bg-card);
                border-radius: var(--z-radius);
                border: 1px solid var(--z-border);
                padding: 20px 24px;
                margin-bottom: 16px;
            }

            .upload-zone {
                border: 2px dashed var(--z-border);
                border-radius: var(--z-radius);
                padding: 24px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s;
                background: var(--z-bg-soft);
            }
            .upload-zone:hover, .upload-zone.dragover {
                border-color: var(--z-accent);
                background: var(--z-accent-soft);
            }
            .upload-zone i { font-size: 2rem; color: var(--z-accent); }
            .upload-zone p { color: var(--z-text-secondary); margin: 8px 0 0; }

            #uploadInfo {
                padding: 12px 16px;
                border: 1px solid rgba(5,150,105,0.18);
                border-radius: var(--z-radius-sm);
                background: var(--z-success-soft);
                color: var(--z-success) !important;
            }

            .orders-panel-heading {
                display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px;
            }
            .orders-panel-heading p { margin: 0 0 4px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--z-text-muted); }
            .orders-panel-heading h3 { margin: 0; font-size: 1rem; font-weight: 700; color: var(--z-text); }
            .orders-panel-heading span {
                display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
                border-radius: 4px; background: var(--z-bg-soft); color: var(--z-text-secondary);
                font-size: 0.72rem; font-weight: 600;
            }

            /* Stat Cards */
            .stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
            .stat-card {
                flex: 1; min-width: 120px;
                background: var(--z-bg-card); border-radius: var(--z-radius);
                border: 1px solid var(--z-border);
                padding: 12px 14px; text-align: left;
            }
            .stat-card .stat-icon { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--z-text-muted); margin-bottom: 6px; }
            .stat-card .stat-value { font-size: 1.4rem; font-weight: 700; color: var(--z-text); }
            .stat-card .stat-label { font-size: 0.72rem; color: var(--z-text-secondary); margin-top: 2px; }

            /* Filter Bar */
            .filter-bar {
                background: var(--z-bg-card); border-radius: var(--z-radius);
                border: 1px solid var(--z-border);
                padding: 16px 20px; margin-bottom: 16px;
                display: none; gap: 12px; flex-wrap: wrap; align-items: flex-end;
            }
            .filter-group { display: flex; flex-direction: column; gap: 4px; flex: 1 1 140px; }
            .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--z-text-secondary); }
            .filter-group select, .filter-group input {
                padding: 8px 12px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm);
                font-size: 0.85rem; background: var(--z-bg-input);
            }
            .filter-group select:focus, .filter-group input:focus {
                border-color: var(--z-accent); outline: none;
                box-shadow: 0 0 0 3px rgba(13,148,136,0.12);
            }

            .btn-filter {
                background: var(--z-accent); color: #fff; border: none;
                padding: 8px 18px; border-radius: var(--z-radius-sm);
                font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: all 0.15s;
            }
            .btn-filter:hover { background: var(--z-accent-hover); }

            .btn-reset {
                background: var(--z-text); color: #fff; border: none;
                padding: 8px 14px; border-radius: var(--z-radius-sm);
                font-weight: 500; cursor: pointer; font-size: 0.85rem;
            }

            .orders-toolbar-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; width: 100%; margin-top: 8px; }

            /* Grid Toolbar */
            .grid-toolbar {
                display: none; padding: 14px 20px; margin-bottom: 12px;
                justify-content: space-between; align-items: center;
                background: var(--z-bg-card); border-radius: var(--z-radius); border: 1px solid var(--z-border);
            }
            .grid-toolbar .left { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
            .grid-toolbar .selected-info { font-size: 0.85rem; color: var(--z-text-secondary); font-weight: 600; }

            .btn-action {
                background: var(--z-success); color: #fff; border: none;
                padding: 9px 18px; border-radius: var(--z-radius-sm);
                font-weight: 600; cursor: pointer; font-size: 0.85rem;
            }
            .btn-action:hover { opacity: 0.9; }
            .btn-action:disabled { opacity: 0.5; cursor: not-allowed; }

            /* Grid/Table */
            .grid-container {
                display: none; overflow-x: hidden;
                background: var(--z-bg-card); border-radius: var(--z-radius); border: 1px solid var(--z-border);
            }
            .grid-container table {
                width: 100%;
                min-width: 0;
                border-collapse: collapse;
                font-size: 0.76rem;
                table-layout: fixed;
            }
            .grid-container thead th {
                background: var(--z-bg-soft); color: var(--z-text-secondary);
                padding: 7px 8px; text-align: left;
                font-weight: 800; font-size: 0.66rem; letter-spacing: 0.06em; text-transform: uppercase;
                position: sticky; top: 0; z-index: 10; white-space: nowrap;
                border-bottom: 1px solid var(--z-border);
                vertical-align: middle;
            }
            .grid-container tbody tr { border-bottom: 1px solid var(--z-border-light); transition: background 0.15s; }
            .grid-container tbody tr:hover { background: var(--z-bg-soft); }
            .grid-container tbody tr.selected-row { background: var(--z-accent-soft) !important; }
            .grid-container tbody tr.is-emri-verildi { background: var(--z-success-soft); opacity: 0.8; }
            .grid-container tbody tr.pasif { background: var(--z-bg-soft); opacity: 0.5; }
            .grid-container tbody tr.urgency-critical { background: var(--z-danger-soft) !important; }
            .grid-container tbody tr.urgency-warning { background: var(--z-warning-soft) !important; }
            .grid-container tbody tr.stock-ready { box-shadow: inset 3px 0 0 #2563eb; }
            .grid-container tbody tr.set-parent-row { background: var(--z-warning-soft) !important; border-left: 3px solid var(--z-warning); }
            .grid-container tbody tr.set-child-row { background: rgba(139,92,246,0.05) !important; border-left: 3px solid #8b5cf6; display: none; }
            .grid-container tbody tr.set-child-row.show { display: table-row; }
            .grid-container tbody tr.set-child-row td:first-child { padding-left: 20px; }
            .grid-container td { padding: 7px 8px; vertical-align: middle; color: var(--z-text); white-space: normal; }
            .grid-container .col-select { width: 38px; text-align: center; }
            .grid-container .col-order { width: 132px; }
            .grid-container .col-customer { width: 138px; }
            .grid-container .col-product { width: auto; }
            .grid-container .col-adet { width: 50px; text-align: right; }
            .grid-container .col-stock { width: 58px; text-align: right; }
            .grid-container .col-production { width: 84px; text-align: right; }
            .grid-container .col-order-date,
            .grid-container .col-cargo-date { width: 94px; }
            .grid-container .col-state { width: 112px; }
            .grid-container .col-operation { width: 168px; }
            .cell-stack {
                display: flex;
                flex-direction: column;
                gap: 2px;
                min-width: 0;
            }
            .cell-stack.compact { gap: 4px; align-items: flex-start; }
            .cell-main {
                font-weight: 700;
                color: var(--z-text);
                white-space: normal;
                line-height: 1.22;
                overflow-wrap: anywhere;
            }
            .cell-muted {
                color: var(--z-text-muted);
                font-size: 0.68rem;
                font-weight: 600;
                line-height: 1.25;
                overflow-wrap: anywhere;
            }
            .cell-meta {
                display: flex;
                flex-wrap: wrap;
                gap: 4px 8px;
                align-items: center;
                color: var(--z-text-muted);
                font-size: 0.72rem;
                line-height: 1.3;
            }
            .cell-meta strong { color: var(--z-text-secondary); font-weight: 700; }
            .metric-value {
                display: inline-flex;
                align-items: center;
                justify-content: flex-end;
                width: 100%;
                min-height: 22px;
                font-size: 0.82rem;
                font-weight: 700;
            }
            .metric-muted { color: var(--z-text-muted); font-weight: 700; }
            .production-cell {
                display: inline-flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: center;
                gap: 3px;
                width: 100%;
                min-height: 34px;
            }
            .production-available {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 46px;
                padding: 2px 7px;
                border-radius: 999px;
                background: #ecfdf5;
                color: #047857;
                font-size: 0.62rem;
                font-weight: 800;
                line-height: 1.2;
                white-space: nowrap;
            }
            .production-available.empty {
                background: #f1f5f9;
                color: #64748b;
            }
            .date-cell {
                display: flex;
                flex-direction: column;
                gap: 1px;
                line-height: 1.1;
                color: var(--z-text-secondary);
                font-weight: 700;
            }
            .date-cell .date-main { font-size: 0.75rem; white-space: nowrap; }
            .date-cell .date-time { font-size: 0.7rem; color: var(--z-text-muted); white-space: nowrap; }
            .date-cell.empty { color: var(--z-text-muted); font-size: 0.8rem; }
            .operation-stack {
                display: grid;
                grid-template-columns: 34px minmax(78px, 1fr);
                gap: 6px;
                align-items: center;
            }
            .operation-match {
                min-width: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .row-action-stack {
                display: grid;
                grid-template-columns: 1fr;
                gap: 4px;
                align-items: center;
            }
            .row-action-stack.has-stock { grid-template-columns: 1fr; }
            .row-action-stack.single-action { grid-template-columns: 1fr; }
            .row-action-status {
                color: var(--z-text-secondary);
                font-size: 0.64rem;
                line-height: 1.2;
                font-weight: 800;
                text-align: center;
                white-space: normal;
            }
            .btn-row-action.stock-primary { background: #2563eb; }
            .btn-row-action.production-secondary { background: #d97706; }
            .stock-choice-hint {
                color: var(--z-text-muted);
                font-size: 0.62rem;
                line-height: 1.25;
                font-weight: 600;
                text-align: center;
            }

            /* Badges */
            .badge-bekliyor { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
            .badge-verildi { background: var(--z-success-soft); color: var(--z-success); padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
            .badge-pasif { background: var(--z-bg-soft); color: var(--z-text-muted); padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-block; }
            .badge-uretimde { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; line-height: 1.2; white-space: nowrap; }
            .badge-set-parent { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
            .badge-set-child { background: rgba(139,92,246,0.1); color: #7c3aed; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 600; }
            .bilesen-adet-badge { background: var(--z-warning); color: #fff; border-radius: 50%; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; margin-right: 4px; }
            .badge-stok { background: rgba(37,99,235,0.08); color: #2563eb; padding: 3px 8px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; }

            /* Match */
            .match-ok { color: var(--z-success); font-weight: 700; font-size: 0.72rem; }
            .match-warn { color: var(--z-warning); font-weight: 700; font-size: 0.72rem; }
            .match-fail { color: var(--z-danger); font-weight: 700; font-size: 0.72rem; }
            .match-status-icon {
                width: 30px;
                height: 30px;
                border: 1px solid var(--z-border);
                border-radius: 7px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0;
                background: var(--z-bg-card);
                color: var(--z-text-muted);
                cursor: pointer;
                transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
            }
            .match-status-icon:hover,
            .match-status-icon:focus {
                transform: translateY(-1px);
                box-shadow: 0 0 0 3px rgba(13,148,136,0.12);
                outline: none;
            }
            .match-status-icon.is-matched {
                color: var(--z-success);
                background: var(--z-success-soft);
                border-color: rgba(22,163,74,0.24);
            }
            .match-status-icon.is-unmatched {
                color: var(--z-accent);
                background: var(--z-accent-soft);
                border-color: rgba(13,148,136,0.24);
            }
            .match-status-icon.is-set,
            .match-status-icon.is-disabled {
                cursor: help;
                opacity: 0.58;
                background: var(--z-bg-soft);
                box-shadow: none;
                transform: none;
            }
            .match-dialog-order {
                text-align: left;
                border: 1px solid var(--z-border);
                background: var(--z-bg-soft);
                border-radius: 8px;
                padding: 10px 12px;
                margin-bottom: 10px;
            }
            .match-dialog-order span {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.68rem;
                font-weight: 800;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                margin-bottom: 4px;
            }
            .match-dialog-order strong {
                display: block;
                color: var(--z-text);
                font-size: 0.9rem;
                line-height: 1.3;
                max-height: 44px;
                overflow: hidden;
            }
            .match-dialog-search {
                position: relative;
                margin-bottom: 10px;
            }
            .match-dialog-search i {
                position: absolute;
                left: 11px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--z-text-muted);
                pointer-events: none;
            }
            .match-dialog-search input {
                width: 100%;
                border: 1px solid var(--z-border);
                border-radius: 8px;
                padding: 9px 12px 9px 34px;
                font-size: 0.9rem;
                outline: none;
            }
            .match-dialog-search input:focus {
                border-color: var(--z-accent);
                box-shadow: 0 0 0 3px rgba(13,148,136,0.12);
            }
            .match-dialog-results {
                max-height: 270px;
                overflow-y: auto;
                border: 1px solid var(--z-border);
                border-radius: 8px;
                background: #fff;
            }
            .match-product-option {
                width: 100%;
                border: 0;
                border-bottom: 1px solid var(--z-border-light);
                background: #fff;
                padding: 9px 11px;
                text-align: left;
                display: grid;
                gap: 2px;
                cursor: pointer;
            }
            .match-product-option:last-child { border-bottom: 0; }
            .match-product-option:hover,
            .match-product-option.is-selected {
                background: var(--z-accent-soft);
            }
            .match-product-option strong {
                color: var(--z-text);
                font-size: 0.86rem;
                line-height: 1.25;
            }
            .match-product-option small {
                color: var(--z-text-muted);
                font-size: 0.72rem;
                line-height: 1.25;
            }
            .match-dialog-empty {
                padding: 18px 12px;
                color: var(--z-text-muted);
                font-size: 0.86rem;
                text-align: center;
            }

            .note-icon { cursor: help; color: var(--z-accent); font-size: 0.9rem; margin-left: 4px; }

            /* Stok */
            .stok-yeterli { color: var(--z-success); font-weight: 800; font-size: 0.82rem; }
            .stok-kismi { color: var(--z-warning); font-weight: 800; font-size: 0.82rem; }
            .stok-yok { color: var(--z-danger); font-weight: 700; font-size: 0.82rem; }
            .stock-inline {
                display: inline-flex;
                align-items: center;
                justify-content: flex-end;
                gap: 6px;
            }
            .btn-stok {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: auto;
                min-height: 22px;
                background: var(--z-accent);
                color: #fff;
                border: none;
                padding: 2px 7px;
                border-radius: 5px;
                font-size: 0.66rem;
                line-height: 1;
                cursor: pointer;
                font-weight: 700;
                white-space: nowrap;
            }
            .btn-stok:hover { opacity: 0.85; }
            .work-order-trigger,
            .production-trigger {
                cursor: help;
                transition: box-shadow 0.12s ease, transform 0.12s ease;
            }
            .production-trigger { cursor: pointer; }
            .work-order-trigger:hover,
            .work-order-trigger:focus,
            .production-trigger:hover,
            .production-trigger:focus {
                outline: none;
                box-shadow: 0 0 0 2px rgba(47, 158, 133, 0.16);
                transform: translateY(-1px);
            }
            .work-order-popover {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 9998;
                width: 282px;
                max-width: calc(100vw - 24px);
                background: #fff;
                border: 1px solid rgba(30, 41, 59, 0.12);
                border-radius: 12px;
                box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
                padding: 12px;
                pointer-events: auto;
                opacity: 0;
                visibility: hidden;
                transform: translateY(4px) scale(0.98);
                transition: opacity 0.12s ease, transform 0.12s ease, visibility 0.12s ease;
            }
            .work-order-popover.visible {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }
            .wo-popover-head {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                align-items: flex-start;
                margin-bottom: 8px;
            }
            .wo-eyebrow {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.62rem;
                font-weight: 800;
                letter-spacing: 0.05em;
                text-transform: uppercase;
                margin-bottom: 2px;
            }
            .wo-title {
                color: var(--z-text);
                font-size: 0.86rem;
                font-weight: 800;
                line-height: 1.25;
            }
            .wo-percent {
                flex: 0 0 auto;
                min-width: 42px;
                border-radius: 999px;
                background: rgba(37, 99, 235, 0.09);
                color: #2563eb;
                font-size: 0.74rem;
                font-weight: 800;
                padding: 4px 7px;
                text-align: center;
            }
            .wo-progress {
                height: 5px;
                overflow: hidden;
                background: var(--z-border-light);
                border-radius: 999px;
                margin-bottom: 8px;
            }
            .wo-progress span {
                display: block;
                height: 100%;
                border-radius: inherit;
                background: linear-gradient(90deg, #2f9e85, #4f8df5);
            }
            .wo-summary {
                margin: 0 0 8px;
                color: var(--z-text-secondary);
                font-size: 0.72rem;
                line-height: 1.4;
            }
            .wo-next {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                align-items: center;
                background: var(--z-bg-soft);
                border-radius: 9px;
                padding: 7px 9px;
                color: var(--z-text-secondary);
                font-size: 0.7rem;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .wo-next strong { color: var(--z-text); }
            .wo-step-list {
                display: grid;
                gap: 3px;
                margin-top: 2px;
            }
            .wo-step {
                display: grid;
                grid-template-columns: 10px 1fr auto;
                gap: 7px;
                align-items: center;
                border: 0;
                border-radius: 8px;
                padding: 5px 7px;
                background: transparent;
            }
            .wo-step-dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: var(--z-text-muted);
            }
            .wo-step-main strong {
                display: flex;
                align-items: center;
                gap: 5px;
                min-width: 0;
                color: var(--z-text);
                font-size: 0.72rem;
                line-height: 1.25;
            }
            .wo-step-main { min-width: 0; }
            .wo-step-dept {
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .wo-step-personnel {
                flex: 0 1 auto;
                max-width: 92px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                border-radius: 999px;
                padding: 1px 5px;
                background: rgba(37, 99, 235, 0.09);
                color: #2563eb;
                font-size: 0.58rem;
                font-weight: 800;
                line-height: 1.35;
            }
            .wo-step-personnel.muted {
                background: rgba(100, 116, 139, 0.1);
                color: #64748b;
            }
            .wo-step-tag {
                border-radius: 999px;
                padding: 2px 6px;
                font-size: 0.6rem;
                font-weight: 800;
                white-space: nowrap;
            }
            .wo-step.done { background: rgba(34, 197, 94, 0.05); border-color: rgba(34, 197, 94, 0.18); }
            .wo-step.now { background: rgba(234, 179, 8, 0.08); border-color: rgba(234, 179, 8, 0.24); }
            .wo-step.waiting { background: rgba(59, 130, 246, 0.06); border-color: rgba(59, 130, 246, 0.18); }
            .wo-step.next { background: rgba(148, 163, 184, 0.06); }
            .wo-step.done .wo-step-dot, .wo-step.done .wo-step-tag { background: #16a34a; color: #fff; }
            .wo-step.now .wo-step-dot, .wo-step.now .wo-step-tag { background: #eab308; color: #fff; }
            .wo-step.waiting .wo-step-dot, .wo-step.waiting .wo-step-tag { background: #3b82f6; color: #fff; }
            .wo-step.next .wo-step-dot, .wo-step.next .wo-step-tag { background: #94a3b8; color: #fff; }
            .wo-empty {
                color: var(--z-text-secondary);
                font-size: 0.72rem;
                line-height: 1.45;
                margin: 0;
                padding: 9px;
                background: var(--z-bg-soft);
                border-radius: 9px;
            }
            .wo-note {
                color: var(--z-text-muted);
                font-size: 0.62rem;
                line-height: 1.35;
                margin-top: 7px;
            }
            .wo-more {
                color: var(--z-text-muted);
                font-size: 0.66rem;
                font-weight: 700;
                padding: 3px 7px 0;
            }
            .production-metrics {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 5px;
                margin: 8px 0;
            }
            .production-metric {
                background: var(--z-bg-soft);
                border-radius: 8px;
                padding: 6px 5px;
                min-width: 0;
            }
            .production-metric span {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.56rem;
                font-weight: 800;
                line-height: 1.1;
                text-transform: uppercase;
            }
            .production-metric strong {
                display: block;
                color: var(--z-text);
                font-size: 0.86rem;
                line-height: 1.2;
                margin-top: 2px;
            }
            .production-stage-list {
                display: grid;
                gap: 4px;
                margin-top: 8px;
            }
            .production-stage {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px;
                align-items: center;
                padding: 6px 7px;
                border-radius: 8px;
                background: rgba(148, 163, 184, 0.07);
            }
            .production-stage.active { background: rgba(234, 179, 8, 0.09); }
            .production-stage.waiting { background: rgba(59, 130, 246, 0.06); }
            .production-stage strong {
                display: block;
                color: var(--z-text);
                font-size: 0.7rem;
                line-height: 1.2;
            }
            .production-stage small {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.62rem;
                line-height: 1.25;
                margin-top: 1px;
            }
            .production-stage-count {
                border-radius: 999px;
                padding: 3px 7px;
                background: #fff;
                color: var(--z-text);
                font-size: 0.66rem;
                font-weight: 800;
                white-space: nowrap;
            }
            .production-related {
                border-top: 1px solid var(--z-border-light);
                margin-top: 9px;
                padding-top: 7px;
                display: grid;
                gap: 5px;
            }
            .production-related-row {
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px;
                align-items: center;
                color: var(--z-text-secondary);
                font-size: 0.66rem;
            }
            .production-related-row strong {
                display: block;
                color: var(--z-text);
                font-size: 0.68rem;
                line-height: 1.25;
            }
            .production-related-row span:last-child {
                color: var(--z-success);
                font-weight: 800;
                white-space: nowrap;
            }

            .btn-row-action {
                background: var(--z-success);
                color: #fff;
                border: none;
                padding: 5px 7px;
                border-radius: 5px;
                font-size: 0.66rem;
                line-height: 1.05;
                cursor: pointer;
                font-weight: 700;
                white-space: nowrap;
                min-height: 25px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 3px;
            }
            .btn-row-action:hover { opacity: 0.85; }
            .btn-row-action:disabled { background: var(--z-border); cursor: not-allowed; }
            .operation-stack .btn-row-action { width: 100% !important; }

            /* Sort */
            th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 18px !important; }
            th.sortable:hover { background: rgba(0,0,0,0.03); }
            th.sortable::after { content: '⇅'; position: absolute; right: 3px; top: 50%; transform: translateY(-50%); font-size: 0.65rem; opacity: 0.4; }
            th.sortable.sort-asc::after { content: '▲'; opacity: 1; color: var(--z-accent); }
            th.sortable.sort-desc::after { content: '▼'; opacity: 1; color: var(--z-accent); }

            /* Pagination */
            .orders-total-footer {
                display: none;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 14px 20px;
                border-top: 1px solid var(--z-border);
                background: var(--z-bg-soft);
            }
            .orders-total-copy {
                min-width: 170px;
            }
            .orders-total-copy span {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.66rem;
                font-weight: 800;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                margin-bottom: 3px;
            }
            .orders-total-copy strong {
                display: block;
                color: var(--z-text);
                font-size: 0.92rem;
                line-height: 1.25;
            }
            .orders-total-metrics {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 18px;
                flex: 1;
                flex-wrap: wrap;
            }
            .orders-total-metric {
                display: grid;
                gap: 2px;
                min-width: 82px;
                padding-left: 14px;
                border-left: 1px solid var(--z-border);
            }
            .orders-total-metric span {
                color: var(--z-text-muted);
                font-size: 0.66rem;
                font-weight: 800;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                white-space: nowrap;
            }
            .orders-total-metric strong {
                color: var(--z-text);
                font-size: 1rem;
                line-height: 1.2;
            }
            .orders-total-metric.primary strong {
                color: var(--z-accent);
                font-size: 1.25rem;
            }
            .pagination-container { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; border-top: 1px solid var(--z-border); font-size: 0.85rem; }
            .pagination-info { color: var(--z-text-secondary); font-weight: 600; }
            .pagination-controls { display: flex; align-items: center; gap: 8px; }
            .page-size-select { padding: 6px 10px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm); background: var(--z-bg-input); font-size: 0.85rem; cursor: pointer; }
            .btn-page { background: var(--z-bg-card); border: 1px solid var(--z-border); color: var(--z-text); padding: 6px 12px; border-radius: var(--z-radius-sm); cursor: pointer; font-weight: 500; }
            .btn-page:hover:not(:disabled) { background: var(--z-accent); color: #fff; border-color: var(--z-accent); }
            .btn-page:disabled { opacity: 0.5; cursor: not-allowed; }
            .page-number { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: var(--z-radius-sm); cursor: pointer; border: 1px solid transparent; font-weight: 600; }
            .page-number:hover:not(.active) { background: var(--z-bg-soft); }
            .page-number.active { background: var(--z-accent); color: #fff; }

            /* Empty state */
            .empty-state { text-align: center; padding: 50px 20px; color: var(--z-text-muted); }
            .empty-state i { font-size: 3rem; margin-bottom: 12px; opacity: 0.4; }
            .empty-state h3 { font-weight: 600; margin-bottom: 8px; }

            /* Loading */
            .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(244,245,247,0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: center; align-items: center; }

            .orders-hero { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }

            @media (max-width: 1080px) { .orders-hero { grid-template-columns: 1fr; } }
            @media (max-width: 768px) {
                /* Filter bar: compact layout */
                .filter-bar {
                    flex-direction: row;
                    flex-wrap: wrap;
                    gap: 8px;
                    padding: 12px;
                    text-align: left;
                }
                .filter-bar .orders-panel-heading {
                    flex-direction: column;
                    gap: 4px;
                    margin-bottom: 4px !important;
                }
                .filter-bar .orders-panel-heading p { font-size: 0.65rem; }
                .filter-bar .orders-panel-heading h3 { font-size: 0.88rem; }
                .filter-group {
                    flex: 1 1 calc(50% - 4px) !important;
                    min-width: 0 !important;
                }
                .filter-group label { text-align: left; font-size: 0.7rem; }
                .filter-group select,
                .filter-group input {
                    width: 100%;
                    padding: 6px 8px;
                    font-size: 0.8rem;
                    min-width: 0 !important;
                }
                /* Toolbar actions: 2-column grid */
                .orders-toolbar-actions {
                    display: grid !important;
                    grid-template-columns: 1fr 1fr;
                    gap: 6px;
                    width: 100%;
                }
                .orders-toolbar-actions > button {
                    font-size: 0.72rem !important;
                    padding: 8px 6px !important;
                    white-space: nowrap !important;
                    min-width: 0;
                    margin-left: 0 !important;
                }
                /* Stats row: horizontal scroll */
                .stats-row { flex-direction: row; overflow-x: auto; flex-wrap: nowrap; gap: 8px; }
                .stat-card { flex: 0 0 auto; min-width: 130px; }
                /* Grid toolbar */
                .grid-toolbar { flex-direction: column; gap: 8px; padding: 10px 12px; }
                .grid-toolbar .left { display: flex; flex-direction: row; flex-wrap: wrap; gap: 6px; }
                .grid-toolbar .left .btn-action { font-size: 0.72rem; padding: 6px 10px; flex: 1 1 calc(50% - 3px); min-width: 0; }
                .btn-stok { display: inline !important; width: auto !important; padding: 2px 8px !important; font-size: 0.7rem !important; margin-top: 0 !important; }
                .orders-total-footer {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 10px;
                    padding: 12px;
                }
                .orders-total-copy { min-width: 0; }
                .orders-total-metrics {
                    width: 100%;
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 10px 8px;
                }
                .orders-total-metric {
                    min-width: 0;
                    padding-left: 10px;
                }
                .pagination-container { flex-direction: column; gap: 8px; padding: 10px 12px; }
                .pagination-controls { flex-wrap: wrap; justify-content: center; }
                .grid-container table,
                .grid-container tbody {
                    display: block !important;
                    width: 100% !important;
                    min-width: 0 !important;
                }
                .grid-container thead { display: none !important; }
                .grid-container tbody tr {
                    display: grid !important;
                    grid-template-columns: 26px repeat(6, minmax(0, 1fr));
                    gap: 6px 8px;
                    padding: 10px 12px;
                    align-items: start;
                }
                .grid-container tbody tr.set-child-row { display: none !important; }
                .grid-container tbody tr.set-child-row.show { display: grid !important; }
                .grid-container td {
                    display: block !important;
                    width: auto !important;
                    padding: 0 !important;
                    border: none !important;
                }
                .grid-container td::before { content: none; }
                .grid-container td.col-select { display: block !important; grid-column: 1 !important; grid-row: 1 / 3 !important; }
                .grid-container td.col-order { display: block !important; grid-column: 2 / 8 !important; grid-row: 1 !important; font-size: 0.8rem !important; }
                .grid-container td.col-customer { display: block !important; grid-column: 2 / 8 !important; grid-row: 2 !important; }
                .grid-container td.col-product {
                    display: block !important;
                    grid-column: 2 / 8 !important;
                    grid-row: 3 !important;
                    text-align: left !important;
                }
                .grid-container td.col-adet { display: block !important; grid-column: 2 / 4 !important; grid-row: 4 !important; text-align: left !important; }
                .grid-container td.col-stock { display: block !important; grid-column: 4 / 6 !important; grid-row: 4 !important; text-align: left !important; }
                .grid-container td.col-production { display: block !important; grid-column: 6 / 8 !important; grid-row: 4 !important; text-align: left !important; }
                .grid-container td.col-order-date { display: block !important; grid-column: 2 / 5 !important; grid-row: 5 !important; text-align: left !important; }
                .grid-container td.col-cargo-date { display: block !important; grid-column: 5 / 8 !important; grid-row: 5 !important; text-align: left !important; }
                .grid-container td.col-state { display: block !important; grid-column: 2 / 8 !important; grid-row: 6 !important; }
                .grid-container td.col-operation { display: block !important; grid-column: 2 / 8 !important; grid-row: 7 !important; }
                .grid-container td.col-product .cell-main {
                    display: block;
                    font-size: 0.74rem;
                    line-height: 1.25;
                    text-align: left !important;
                }
                .grid-container td.col-customer .cell-main {
                    font-size: 0.78rem;
                }
                .grid-container td.col-adet::before,
                .grid-container td.col-stock::before,
                .grid-container td.col-production::before,
                .grid-container td.col-order-date::before,
                .grid-container td.col-cargo-date::before {
                    content: attr(data-label) !important;
                    display: block;
                    color: var(--z-text-muted);
                    font-size: 0.62rem;
                    font-weight: 800;
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                    margin-bottom: 3px;
                }
                .grid-container td.col-adet,
                .grid-container td.col-stock,
                .grid-container td.col-production,
                .grid-container td.col-order-date,
                .grid-container td.col-cargo-date {
                    background: var(--z-bg-soft);
                    border-radius: 6px;
                    padding: 6px !important;
                }
                .metric-value {
                    justify-content: flex-start;
                    min-height: 0;
                    font-size: 0.78rem;
                }
                .date-cell {
                    align-items: flex-start;
                }
                .date-cell .date-main { font-size: 0.72rem; }
                .date-cell .date-time { font-size: 0.66rem; }
                .badge-bekliyor,
                .badge-verildi,
                .badge-pasif,
                .badge-stok {
                    font-size: 0.68rem;
                }
                .col-state .cell-stack {
                    grid-template-columns: auto auto;
                    display: grid;
                    justify-content: start;
                    gap: 6px;
                }
                .operation-stack {
                    grid-template-columns: 34px minmax(0, 1fr);
                    gap: 5px;
                }
                .operation-match {
                    min-width: 0;
                }
                .row-action-stack {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 5px;
                }
                .row-action-stack.has-stock {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
                .row-action-stack.single-action {
                    grid-template-columns: 1fr;
                }
                .btn-row-action {
                    min-height: 27px;
                    font-size: 0.66rem;
                    padding: 5px 6px;
                }
            }
        </style>

        <!-- Upload Card -->
        <section class="upload-card">
            <div class="orders-panel-heading">
                <div>
                    <p>Veri girişi</p>
                    <h3>Günlük sipariş dosyasını yükle</h3>
                </div>
                <span>Canlı aktarım</span>
            </div>

            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                <i class="bi bi-cloud-arrow-up"></i>
                <p><strong>Excel dosyasını sürükleyip bırakın</strong> veya tıklayarak seçin</p>
                <p style="font-size: 0.8rem; color: var(--z-text-muted);">Günlük sipariş Excel dosyası (.xlsx)</p>
            </div>
            <input type="file" id="fileInput" accept=".xlsx,.xls" style="display:none"
                onchange="handleFileSelect(this)" />
            <div id="uploadInfo" style="display:none; margin-top: 12px; font-size: 0.9rem;">
                <i class="bi bi-check-circle" style="color:var(--z-success);"></i>
                <span id="uploadInfoText"></span>
            </div>
        </section>

        <!-- Stats -->
        <div class="stats-row" id="statsRow" style="display:none;">
            <div class="stat-card">
                <div class="stat-icon">Toplam Sipariş</div>
                <div class="stat-value" id="statToplam">0</div>
                <div class="stat-label">Aktif sipariş havuzundaki satırlar</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">Üretim Bekliyor</div>
                <div class="stat-value" id="statBekliyor">0</div>
                <div class="stat-label">Henüz iş emrine dönüşmemiş siparişler</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">İş Emri Verildi</div>
                <div class="stat-value" id="statVerildi">0</div>
                <div class="stat-label">Operasyona aktarılan satırlar</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">Stoktan Karşılandı</div>
                <div class="stat-value" id="statStokKarsilandi">0</div>
                <div class="stat-label">Direkt stok kullanılarak kapatılanlar</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">Eşleşmeyenler</div>
                <div class="stat-value" id="statEslesmeyenler">0</div>
                <div class="stat-label">Manuel karar bekleyen ürün adları</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">Son Yükleme</div>
                <div class="stat-value" id="statSonYukleme" style="font-size:0.9rem;">—</div>
                <div class="stat-label">Sisteme son veri giriş zamanı</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar" id="filterBar" style="display:none;">
            <div class="orders-panel-heading" style="width:100%; margin-bottom:0;">
                <div>
                    <p>Filtre Merkezi</p>
                    <h3>Sipariş havuzunu daralt</h3>
                </div>
                <span>Canlı listeleme</span>
            </div>
            <div class="filter-group">
                <label>Durum</label>
                <select id="filterDurum">
                    <option value="">Tümü (Aktif)</option>
                    <option value="UretimBekliyor">🟡 Üretim Bekliyor</option>
                    <option value="GiedYapilanlar">🔗 GİED Yapılanlar</option>
                    <option value="IsEmriVerildi">🟢 İş Emri Verildi</option>
                    <option value="StokKarsilandi">📦 Stoktan Karşılanan</option>
                    <option value="Eslesmeyenler">❌ Eşleşmeyenler</option>
                    <option value="Pasif">⚪ Pasif / Kapanmış</option>
                    <option value="PasifDevamEden">🔸 Pasif / Devam Eden</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Pazaryeri</label>
                <select id="filterPazaryeri">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Mağaza</label>
                <select id="filterMagaza">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Kategori</label>
                <select id="filterKategori">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Arama</label>
                <input type="text" id="filterArama" placeholder="Sipariş No / Müşteri / Ürün"
                    style="min-width: 200px;" />
            </div>
            <div class="orders-toolbar-actions">
                <button type="button" class="btn-filter" onclick="loadOrders()"><i class="bi bi-search"></i>
                    Filtrele</button>
                <button type="button" class="btn-reset" onclick="resetFilters()"><i class="bi bi-arrow-counterclockwise"></i>
                    Sıfırla</button>
                <button type="button" id="btnOtoEslestir"
                    style="background: linear-gradient(135deg, #8e44ad, #6c3483); color: #fff; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; white-space: nowrap;"
                    onclick="rematchOrders()" onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'"><i class="bi bi-magic"></i>
                    Oto Eşleştir</button>
                <button type="button"
                    style="background: linear-gradient(135deg, #c0392b, #96281b); color: #fff; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; margin-left: auto; white-space: nowrap;"
                    onclick="clearAllOrders()" onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'"><i class="bi bi-trash3"></i>
                    Her Şeyi Sıfırla</button>
            </div>
        </div>

        <!-- Grid Toolbar -->
        <div class="grid-toolbar" id="gridToolbar" style="display:none;">
            <div class="left">
                <span class="selected-info"><span id="selectedCount">0</span> satır seçili</span>
                <button type="button" class="btn-action" id="btnTopluIsEmri" onclick="submitBulkWorkOrders()" disabled>
                    <i class="bi bi-send"></i> Seçilenleri Üretime Al
                </button>
                <button type="button" class="btn-action" id="btnTopluIptal" onclick="cancelBulkWorkOrders()" disabled
                    style="background:#c0392b; color:#fff; border-color:#a93226;">
                    <i class="bi bi-x-lg"></i> Seçilen İş Emirlerini İptal Et
                </button>
                <button type="button" class="btn-action" id="btnTopluStok" onclick="deductStockBulk()" disabled
                    style="background: linear-gradient(135deg, #2196F3, #1976D2);">
                    <i class="bi bi-box2-open"></i> Seçilenleri Stoktan Karşıla
                </button>
                <button type="button" class="btn-action" id="btnTopluGied" onclick="linkBulkWipAllocations()" disabled
                    style="background: linear-gradient(135deg, #8e44ad, #6c3483);">
                    <i class="bi bi-link-45deg"></i> Seçilenleri GİED Yap
                </button>
            </div>
        </div>

        <!-- Grid -->
        <div class="grid-container" id="gridContainer" style="display:none;">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th class="col-select"><input type="checkbox" id="masterCheckbox"
                                onchange="toggleAllRows(this)" /></th>
                        <th class="sortable col-order" data-sort="siparisNo"
                            onclick="sortColumn('siparisNo')">Sipariş</th>
                        <th class="sortable col-customer" data-sort="musteri" onclick="sortColumn('musteri')">
                            Müşteri</th>
                        <th class="sortable col-product" data-sort="urunAdi" onclick="sortColumn('urunAdi')">Ürün
                        </th>
                        <th class="sortable col-adet" data-sort="adet" onclick="sortColumn('adet')">Adet</th>
                        <th class="sortable col-stock" data-sort="stokAdet" onclick="sortColumn('stokAdet')">Stok</th>
                        <th class="sortable col-production" data-sort="uretimMusaitAdet" onclick="sortColumn('uretimMusaitAdet')" title="GİED üretim toplamı ve müsait miktar">Üretim / Müsait</th>
                        <th class="sortable col-order-date" data-sort="siparisTarihi" onclick="sortColumn('siparisTarihi')">Sip.</th>
                        <th class="sortable col-cargo-date" data-sort="kargoSonTeslim"
                            onclick="sortColumn('kargoSonTeslim')">Kargo</th>
                        <th class="sortable col-state" data-sort="durum" onclick="sortColumn('durum')">Durum
                        </th>
                        <th class="col-operation">DB Ürün / Karar</th>
                    </tr>
                </thead>
                <tbody id="ordersBody"></tbody>
            </table>

            <!-- Filtered totals -->
            <div class="orders-total-footer" id="ordersTotalFooter" style="display:none;">
                <div class="orders-total-copy">
                    <span id="ordersTotalScope">Filtrelenmiş liste</span>
                    <strong id="ordersTotalTitle">0 sipariş satırı</strong>
                </div>
                <div class="orders-total-metrics">
                    <div class="orders-total-metric primary">
                        <span>Adet Toplamı</span>
                        <strong id="ordersTotalAdet">0</strong>
                    </div>
                    <div class="orders-total-metric">
                        <span>Bu Sayfa</span>
                        <strong id="ordersPageAdet">0</strong>
                    </div>
                    <div class="orders-total-metric">
                        <span>Bekleyen</span>
                        <strong id="ordersPendingAdet">0</strong>
                    </div>
                    <div class="orders-total-metric">
                        <span>İş Emri</span>
                        <strong id="ordersWorkOrderAdet">0</strong>
                    </div>
                    <div class="orders-total-metric">
                        <span>Stoktan</span>
                        <strong id="ordersStockAdet">0</strong>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="pagination-container" id="paginationContainer" style="display:none;">
                <div class="pagination-info" id="pageInfo">0-0 / 0 sipariş</div>
                <div class="pagination-controls">
                    <select class="page-size-select" id="pageSizeSelect" onchange="changePageSize()">
                        <option value="50">50 / sayfa</option>
                        <option value="100">100 / sayfa</option>
                        <option value="200">200 / sayfa</option>
                        <option value="999999">Tümü</option>
                    </select>

                    <button class="btn-page" id="btnPrevPage" onclick="prevPage()"><i
                            class="bi bi-chevron-left"></i> Önceki</button>
                    <div class="page-numbers" id="pageNumbers"></div>
                    <button class="btn-page" id="btnNextPage" onclick="nextPage()">Sonraki <i
                            class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div id="workOrderPopover" class="work-order-popover" aria-hidden="true"></div>

        @include('components.work-order-bom-preview')

        <!-- Empty state -->
        <div class="empty-state" id="emptyState">
            <i class="bi bi-inbox"></i>
            <h3>Henüz sipariş yüklenmedi</h3>
            <p>Yukarı alandan günlük sipariş Excel dosyasını yükleyerek tabloyu doldur.</p>
        </div>

        <script>
            // ============================================================
            //   GLOBAL
            // ============================================================
            var apiUrl = '/SiparisApi.ashx';
            var dbProducts = [];
            var allOrders = [];
            var selectedNos = new Set();
            var currentSortField = '';
            var currentSortDir = '';  // 'asc' or 'desc'
            var workOrderPipelineCache = {};
            var productionDetailCache = {};
            var workOrderPopoverTimer = null;
            var activeWorkOrderRow = null;

            // Sayfa yüklendiğinde
            $(document).ready(function () {
                loadProducts(function () {
                    loadOrders();
                });
                setupDragDrop();
                setupWorkOrderPopover();
            });

            // ============================================================
            //   DRAG & DROP
            // ============================================================
            function setupDragDrop() {
                var zone = document.getElementById('uploadZone');
                zone.addEventListener('dragover', function (e) {
                    e.preventDefault(); zone.classList.add('dragover');
                });
                zone.addEventListener('dragleave', function () {
                    zone.classList.remove('dragover');
                });
                zone.addEventListener('drop', function (e) {
                    e.preventDefault(); zone.classList.remove('dragover');
                    if (e.dataTransfer.files.length > 0) {
                        processExcelFile(e.dataTransfer.files[0]);
                    }
                });
            }

            // ============================================================
            //   ÜRÜN LİSTESİ YÜKLE
            // ============================================================
            function loadProducts(callback) {
                $.ajax({
                    url: apiUrl + '?action=getProducts',
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            dbProducts = res.products || [];
                        }
                        if (callback) callback();
                    },
                    error: function () {
                        if (callback) callback();
                    }
                });
            }

            // ============================================================
            //   EXCEL YÜKLEME
            // ============================================================
            function handleFileSelect(input) {
                if (input.files.length > 0) {
                    var file = input.files[0];
                    input.value = '';
                    processExcelFile(file);
                }
            }

            function processExcelFile(file) {
                if (!file.name.match(/\.xlsx?$/i)) {
                    Swal.fire({ icon: 'warning', title: 'Geçersiz Dosya', text: 'Lütfen .xlsx uzantılı Excel dosyası seçin.', confirmButtonColor: '#d4af37' });
                    return;
                }

                if (typeof XLSX === 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Excel Okuma Hatası', text: 'Excel okuma kütüphanesi yüklenemedi. Sayfayı yenileyip tekrar deneyin.', confirmButtonColor: '#d4af37' });
                    return;
                }

                Swal.fire({
                    title: 'Excel Yükleniyor...',
                    html: 'Siparişler okunuyor ve sunucuya aktarılıyor...',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                var reader = new FileReader();
                reader.onload = function (e) {
                    try {
                        var data = new Uint8Array(e.target.result);
                        var workbook = XLSX.read(data, { type: 'array' });

                        var allRows = [];
                        var sheetCount = 0;

                        // --- 1. Adım: TÜM sheet'lerden stok kodu haritası oluştur ---
                        var stokKoduMap = {}; // ürün adı (lower) → stok kodu
                        workbook.SheetNames.forEach(function (sheetName) {
                            var ws = workbook.Sheets[sheetName];
                            var json = XLSX.utils.sheet_to_json(ws, { defval: '' });
                            json.forEach(function (row) {
                                var sk = findVal(row, ['STOK_GOSTER', 'StokKodu', 'Stok Kodu', 'STOK KODU']);
                                var urun = findVal(row, ['Ürün', 'Urun', 'UrunAdi']);
                                if (sk && urun) {
                                    stokKoduMap[urun.toString().trim().toLowerCase()] = sk.toString().trim();
                                }
                            });
                        });

                        // --- 2. Adım: SİPARİŞ TAKİP sheet'lerini işle ---
                        workbook.SheetNames.forEach(function (sheetName) {
                            // Sadece "SİPARİŞ TAKİP" içeren sheet'leri al
                            if (sheetName.toUpperCase().indexOf('SİPARİŞ TAKİP') === -1 &&
                                sheetName.toUpperCase().indexOf('SIPARIS TAKIP') === -1 &&
                                sheetName.toUpperCase().indexOf('SIPARIŞLER') === -1) return;

                            sheetCount++;
                            var ws = workbook.Sheets[sheetName];
                            var json = XLSX.utils.sheet_to_json(ws, { defval: '' });

                            // Kategori: sheet adından çıkar (PUF, BERJER, KÖŞE VE KANEPE vb.)
                            var kategori = sheetName.replace(/SİPARİŞ TAKİP/gi, '').replace(/SIPARIS TAKIP/gi, '').trim();
                            if (kategori.endsWith(' ')) kategori = kategori.trim();

                            json.forEach(function (row) {
                                var parsed = parseExcelRow(row, kategori, stokKoduMap);
                                if (parsed) allRows.push(parsed);
                            });
                        });

                        if (allRows.length === 0) {
                            Swal.fire({ icon: 'warning', title: 'Veri Bulunamadı', text: '"SİPARİŞ TAKİP" içeren sheet bulunamadı veya satır yok.', confirmButtonColor: '#d4af37' });
                            return;
                        }

                        // Sunucuya gönder
                        uploadToServer(allRows, file.name, sheetCount);
                    } catch (ex) {
                        Swal.fire({ icon: 'error', title: 'Excel Okuma Hatası', text: ex.message, confirmButtonColor: '#d4af37' });
                    }
                };
                reader.readAsArrayBuffer(file);
            }

            function parseExcelRow(row, kategori, stokKoduMap) {
                // Kolon isimlerini bul (esnek)
                var siparisNo = findVal(row, ['Sipariş No', 'Siparis No', 'SiparisNo']);
                var urunAdi = findVal(row, ['Ürün', 'Urun', 'UrunAdi']);

                if (!siparisNo || !urunAdi) return null;

                // Stok kodunu önce satırdan, sonra TOPLAM sheet haritasından al
                var stokKodu = (findVal(row, ['STOK_GOSTER', 'StokKodu', 'Stok Kodu', 'STOK KODU']) || '').toString().trim();
                if (!stokKodu && stokKoduMap) {
                    var key = urunAdi.toString().trim().toLowerCase();
                    // 1) Tam eşleşme
                    if (stokKoduMap[key]) {
                        stokKodu = stokKoduMap[key];
                    } else {
                        // 2) Ürün adı haritadaki bir ismi içeriyorsa veya tersi
                        var mapKeys = Object.keys(stokKoduMap);
                        for (var m = 0; m < mapKeys.length; m++) {
                            if (key.indexOf(mapKeys[m]) !== -1 || mapKeys[m].indexOf(key) !== -1) {
                                stokKodu = stokKoduMap[mapKeys[m]];
                                break;
                            }
                        }
                    }
                }

                return {
                    siparisNo: siparisNo.toString().trim(),
                    pazaryeri: findVal(row, ['Pazaryeri', 'Pazar Yeri']) || '',
                    magaza: findVal(row, ['Mağaza', 'Magaza']) || '',
                    siparisTarihi: findVal(row, ['Sip. Tarihi', 'Sipariş Tarihi', 'SiparisTarihi']) || '',
                    musteri: findVal(row, ['Sevk - Müşteri', 'Sevk-Müşteri', 'Müşteri', 'Musteri']) || '',
                    urunAdi: urunAdi.toString().trim(),
                    adet: parseInt(findVal(row, ['Adet']) || '1', 10) || 1,
                    musteriNotu: findVal(row, ['Müşteri Notu', 'MusteriNotu', 'Not']) || '',
                    kargoSonTeslim: findVal(row, ['Kargoya Son Teslim Tarihi', 'Kargo Son Teslim', 'KargoSonTeslim']) || '',
                    stokKodu: stokKodu,
                    kategori: kategori
                };
            }

            function findVal(row, keys) {
                for (var i = 0; i < keys.length; i++) {
                    if (row.hasOwnProperty(keys[i]) && row[keys[i]] !== '' && row[keys[i]] !== null && row[keys[i]] !== undefined) {
                        return row[keys[i]];
                    }
                }
                // Fuzzy: tüm key'leri kontrol et
                var rowKeys = Object.keys(row);
                for (var i = 0; i < keys.length; i++) {
                    var searchKey = keys[i].toLowerCase();
                    for (var j = 0; j < rowKeys.length; j++) {
                        if (rowKeys[j].toLowerCase().indexOf(searchKey) !== -1 || searchKey.indexOf(rowKeys[j].toLowerCase()) !== -1) {
                            if (row[rowKeys[j]] !== '' && row[rowKeys[j]] !== null) return row[rowKeys[j]];
                        }
                    }
                }
                return null;
            }

            function uploadToServer(rows, filename, sheetCount) {
                // Tarihleri string'e çevir
                rows.forEach(function (r) {
                    if (r.siparisTarihi && typeof r.siparisTarihi === 'number') {
                        r.siparisTarihi = excelDateToString(r.siparisTarihi);
                    } else if (r.siparisTarihi) {
                        r.siparisTarihi = r.siparisTarihi.toString();
                    }
                    if (r.kargoSonTeslim && typeof r.kargoSonTeslim === 'number') {
                        r.kargoSonTeslim = excelDateToString(r.kargoSonTeslim);
                    } else if (r.kargoSonTeslim) {
                        r.kargoSonTeslim = r.kargoSonTeslim.toString();
                    }
                });

                $.ajax({
                    url: apiUrl + '?action=uploadOrders',
                    type: 'POST',
                    contentType: 'application/json; charset=utf-8',
                    data: JSON.stringify({ rows: rows }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var pendingWO = res.pendingWorkOrders || [];
                            var stokKoduKaydedilecekler = res.stokKoduKaydedilecekler || [];
                            var giedAutoClosed = (res.giedAutoStockDeducted || 0) + (res.giedAutoStockAlreadyClosed || 0);
                            var giedAutoErrors = res.giedAutoStockErrors || [];

                            // Sonuç zinciri: önce sonuç mesajı → pendingWO dialog → stok kodu dialog → loadOrders
                            function showStokKoduDialog() {
                                if (stokKoduKaydedilecekler.length > 0) {
                                    // Tekrar eden urunNo'ları filtrele (unique)
                                    var uniqueItems = [];
                                    var seenNos = {};
                                    stokKoduKaydedilecekler.forEach(function (sk) {
                                        if (!seenNos[sk.urunNo]) {
                                            seenNos[sk.urunNo] = true;
                                            uniqueItems.push(sk);
                                        }
                                    });

                                    var listHtml = '<div style="text-align:left;max-height:200px;overflow-y:auto;margin-top:10px;">';
                                    uniqueItems.forEach(function (sk) {
                                        listHtml += '<div style="padding:4px 0;border-bottom:1px solid rgba(255,255,255,0.1);">' +
                                            '<b>' + escapeHtml(sk.stokKodu) + '</b> → ' + escapeHtml(sk.urunAdi) +
                                            '</div>';
                                    });
                                    listHtml += '</div>';

                                    Swal.fire({
                                        icon: 'question',
                                        title: 'Stok Kodu Kaydedilsin mi?',
                                        html: '<b>' + uniqueItems.length + '</b> ürün isimle eşleşti ancak stok kodu sistemde kayıtlı değil.<br>' +
                                            'Bu stok kodlarını ürünlere kaydetmek ister misiniz?' + listHtml,
                                        showCancelButton: true,
                                        confirmButtonText: '✅ Evet, Kaydet',
                                        cancelButtonText: '❌ Hayır',
                                        confirmButtonColor: '#4b9460',
                                        cancelButtonColor: '#888',
                                        reverseButtons: true
                                    }).then(function (result) {
                                        if (result.isConfirmed) {
                                            $.ajax({
                                                url: apiUrl + '?action=saveStockCodes',
                                                type: 'POST',
                                                contentType: 'application/json',
                                                data: JSON.stringify({ items: uniqueItems }),
                                                dataType: 'json',
                                                success: function (saveRes) {
                                                    Swal.fire({
                                                        icon: saveRes.success ? 'success' : 'error',
                                                        title: saveRes.success ? 'Kaydedildi!' : 'Hata',
                                                        text: saveRes.message,
                                                        confirmButtonColor: '#4b9460'
                                                    });
                                                    loadOrders();
                                                },
                                                error: function () {
                                                    Swal.fire({ icon: 'error', title: 'Sunucu Hatası', confirmButtonColor: '#d4af37' });
                                                    loadOrders();
                                                }
                                            });
                                        } else {
                                            loadOrders();
                                        }
                                    });
                                } else {
                                    loadOrders();
                                }
                            }

                            var uploadResultHtml = '<b>' + res.inserted + '</b> yeni eklendi<br>' +
                                '<b>' + res.updated + '</b> güncellendi<br>' +
                                '<b>' + res.passivated + '</b> pasifleştirildi<br>' +
                                '<b>' + res.matched + '</b> ürün eşleşti, <b>' + res.unmatched + '</b> eşleşmedi';

                            if (giedAutoClosed > 0) {
                                uploadResultHtml += '<br><b>' + giedAutoClosed + '</b> GİED siparişi stoktan kapatıldı';
                            }
                            if (giedAutoErrors.length > 0) {
                                var giedErrorHtml = '<div style="text-align:left;margin-top:8px;max-height:120px;overflow:auto;">';
                                giedAutoErrors.slice(0, 5).forEach(function (err) {
                                    giedErrorHtml += '<div style="font-size:0.8rem;">' +
                                        '<b>' + escapeHtml(err.siparisNo || ('#' + err.no)) + '</b> — ' +
                                        escapeHtml(err.message || 'Stoktan kapanamadı') +
                                        '</div>';
                                });
                                if (giedAutoErrors.length > 5) {
                                    giedErrorHtml += '<div style="font-size:0.8rem;">+' + (giedAutoErrors.length - 5) + ' kayıt daha</div>';
                                }
                                giedErrorHtml += '</div>';
                                uploadResultHtml += '<br><span style="color:#b45309;"><b>' + giedAutoErrors.length + '</b> GİED siparişi stoktan kapanamadı</span>' + giedErrorHtml;
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'Siparişler Yüklendi!',
                                html: uploadResultHtml,
                                confirmButtonColor: '#4b9460'
                            }).then(function () {
	                                // Listede olmayan ama iş emri verilmiş siparişler var mı?
	                                if (pendingWO.length > 0) {
	                                    var pendingWorkOrderNos = pendingWO.map(function (pw) { return pw.no; });
	                                    var listHtml = '<div style="text-align:left;max-height:200px;overflow-y:auto;margin-top:10px;">';
                                    pendingWO.forEach(function (pw) {
                                        listHtml += '<div style="padding:4px 0;border-bottom:1px solid rgba(255,255,255,0.1);">' +
                                            '<b>' + escapeHtml(pw.siparisNo) + '</b> — ' + escapeHtml(pw.urunAdi) +
                                            '</div>';
                                    });
                                    listHtml += '</div>';

                                    Swal.fire({
                                        icon: 'warning',
                                        title: 'İş Emri Mevcut!',
                                        html: '<b>' + pendingWO.length + '</b> sipariş yeni listede yok ve <b>aktif iş emri</b> mevcut.<br>' +
                                            'Bu siparişleri pasife alıp iş emirlerini iptal etmek ister misiniz?' + listHtml,
                                        showCancelButton: true,
                                        confirmButtonText: '✅ Evet, İptal Et',
                                        cancelButtonText: '❌ Hayır, Koru',
                                        confirmButtonColor: '#c0392b',
                                        cancelButtonColor: '#4b9460',
                                        reverseButtons: true
	                                    }).then(function (result) {
	                                        if (result.isConfirmed) {
	                                            Swal.fire({ title: 'İptal ediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

	                                            $.ajax({
	                                                url: apiUrl + '?action=passivateWithWorkOrderCancel',
	                                                type: 'POST',
	                                                contentType: 'application/json',
	                                                data: JSON.stringify({ satirNolar: pendingWorkOrderNos }),
                                                dataType: 'json',
                                                success: function (cancelRes) {
                                                    Swal.fire({
                                                        icon: cancelRes.success ? 'success' : 'error',
                                                        title: cancelRes.success ? 'İptal Edildi!' : 'Hata',
                                                        text: cancelRes.message,
                                                        confirmButtonColor: '#4b9460'
                                                    }).then(function () {
                                                        showStokKoduDialog();
                                                    });
                                                },
                                                error: function () {
                                                    Swal.fire({ icon: 'error', title: 'Sunucu Hatası', confirmButtonColor: '#d4af37' });
                                                    showStokKoduDialog();
                                                }
                                            });
                                        } else {
	                                            $.ajax({
	                                                url: apiUrl + '?action=onlyUpdateStatusBulk',
	                                                type: 'POST',
	                                                contentType: 'application/json',
	                                                data: JSON.stringify({ satirNolar: pendingWorkOrderNos, yeniDurum: 'PasifDevamEden', yeniAktif: 0 }),
                                                dataType: 'json',
                                                success: function () { showStokKoduDialog(); },
                                                error: function () { showStokKoduDialog(); }
                                            });
                                        }
                                    });
                                } else {
                                    showStokKoduDialog();
                                }
                            });

                            document.getElementById('uploadInfo').style.display = 'block';
                            document.getElementById('uploadInfoText').textContent =
                                filename + ' — ' + rows.length + ' satır · ' + sheetCount + ' sheet · ' + new Date().toLocaleString('tr-TR');
                        } else {
                            Swal.fire({ icon: 'error', title: 'Yükleme Hatası', text: res.message, confirmButtonColor: '#d4af37' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                    }
                });
            }

            function excelDateToString(serial) {
                var utc_days = Math.floor(serial - 25569);
                var utc_value = utc_days * 86400;
                var date_info = new Date(utc_value * 1000);
                var fractional_day = serial - Math.floor(serial) + 0.0000001;
                var total_seconds = Math.floor(86400 * fractional_day);
                var hours = Math.floor(total_seconds / 3600);
                var minutes = Math.floor((total_seconds % 3600) / 60);
                var day = ('0' + date_info.getUTCDate()).slice(-2);
                var month = ('0' + (date_info.getUTCMonth() + 1)).slice(-2);
                var year = date_info.getUTCFullYear();
                return day + '.' + month + '.' + year + ' ' + ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2);
            }

            // ============================================================
            //   SİPARİŞ LİSTESİ YÜKLE
            // ============================================================
            function loadOrders() {
                var params = {
                    durum: $('#filterDurum').val() || '',
                    pazaryeri: $('#filterPazaryeri').val() || '',
                    magaza: $('#filterMagaza').val() || '',
                    kategori: $('#filterKategori').val() || '',
                    arama: $('#filterArama').val() || ''
                };

                $.ajax({
                    url: apiUrl + '?action=getOrders&' + $.param(params),
                    type: 'GET',
                    dataType: 'json',
		                    success: function (res) {
		                        if (res.success) {
		                            workOrderPipelineCache = {};
		                            productionDetailCache = {};
		                            allOrders = res.orders || [];
		                            updateStats(res.stats);
		                            updateFilterDropdowns(res.filters);
		                            syncEmptyStateForOrders(allOrders, res.stats, params);
		                            sortColumn('siparisTarihi', 'desc'); // Sorting ensures stable pagination
		                            showGridUI(allOrders.length > 0, hasOrderPool(res.stats, params));
		                        } else {
		                            showOrdersLoadError(res.message || 'Sipariş listesi yüklenemedi.');
		                        }
		                    },
	                    error: function (xhr) {
	                        var message = (xhr.responseJSON && xhr.responseJSON.message)
	                            ? xhr.responseJSON.message
	                            : (xhr.statusText || 'Sunucuya bağlanılamadı.');
	                        showOrdersLoadError(message);
	                    }
	                });
	            }

		            function resetEmptyState() {
		                setEmptyState('Henüz sipariş yüklenmedi', 'Yukarı alandan günlük sipariş Excel dosyasını yükleyerek tabloyu doldur.');
		            }

		            function syncEmptyStateForOrders(orders, stats, params) {
		                if ((orders || []).length > 0) {
		                    resetEmptyState();
		                    return;
		                }

		                if (hasOrderPool(stats, params)) {
		                    setEmptyState('Filtreye uygun sipariş bulunamadı', 'Seçili kriterlerle eşleşen satır yok. Filtreyi değiştirerek listeyi tekrar daraltabilirsiniz.');
		                    return;
		                }

		                resetEmptyState();
		            }

		            function hasOrderPool(stats, params) {
		                var total = stats && !isNaN(parseInt(stats.toplam, 10)) ? parseInt(stats.toplam, 10) : 0;
		                return total > 0 || hasActiveFilters(params || {});
		            }

		            function hasActiveFilters(params) {
		                return Object.keys(params).some(function (key) {
		                    return String(params[key] || '').trim() !== '';
		                });
		            }

	            function showOrdersLoadError(message) {
	                allOrders = [];
	                selectedNos.clear();
	                var master = document.getElementById('masterCheckbox');
	                if (master) {
	                    master.checked = false;
	                    master.indeterminate = false;
	                }
	                updateSelectedCount();
	                setEmptyState('Siparişler yüklenemedi', message || 'Bağlantı hatası oluştu. Yenile ile tekrar deneyin.');
	                showGridUI(false);
	            }

	            function setEmptyState(title, message) {
	                var emptyState = document.getElementById('emptyState');
	                if (!emptyState) return;
	                var heading = emptyState.querySelector('h3');
	                var paragraph = emptyState.querySelector('p');
	                if (heading) heading.textContent = title || '';
	                if (paragraph) paragraph.textContent = message || '';
	            }

            function updateStats(stats) {
                if (!stats) return;
                document.getElementById('statsRow').style.display = 'flex';
                document.getElementById('statToplam').textContent = stats.toplam || 0;
                document.getElementById('statBekliyor').textContent = stats.uretimBekliyor || 0;
                document.getElementById('statVerildi').textContent = stats.isEmriVerildi || 0;
                document.getElementById('statStokKarsilandi').textContent = stats.stokKarsilandi || 0;
                document.getElementById('statEslesmeyenler').textContent = stats.eslesmeyenler || 0;
                document.getElementById('statSonYukleme').textContent = stats.sonYukleme || '—';
            }

            function updateFilterDropdowns(filters) {
                if (!filters) return;

                fillFilterSelect('filterPazaryeri', filters.pazaryeriList);
                fillFilterSelect('filterMagaza', filters.magazaList);
                fillFilterSelect('filterKategori', filters.kategoriList);
            }

            function fillFilterSelect(id, items) {
                var el = document.getElementById(id);
                var current = el.value;
                var html = '<option value="">Tümü</option>';
                if (items) {
                    items.forEach(function (item) {
                        html += '<option value="' + escapeHtml(item) + '"' + (item === current ? ' selected' : '') + '>' + escapeHtml(item) + '</option>';
                    });
                }
                el.innerHTML = html;
            }

            function showGridUI(hasData, hasOrderPool) {
                var showFilters = hasData || !!hasOrderPool;
                document.getElementById('filterBar').style.display = showFilters ? 'flex' : 'none';
                document.getElementById('gridToolbar').style.display = hasData ? 'flex' : 'none';
                document.getElementById('gridContainer').style.display = hasData ? 'block' : 'none';
                document.getElementById('emptyState').style.display = hasData ? 'none' : 'block';
                document.getElementById('statsRow').style.display = 'flex';
            }

            function resetFilters() {
                $('#filterDurum').val('');
                $('#filterPazaryeri').val('');
                $('#filterMagaza').val('');
                $('#filterKategori').val('');
                $('#filterArama').val('');
                loadOrders();
            }

            // ============================================================
            //   COLUMN SORT & PAGINATION VARIABLES
            // ============================================================
            var currentSortField = 'siparisTarihi';
            var currentSortDir = 'desc';

            var currentPage = 1;
            var pageSize = 50;

            function sortColumn(field, forceDir) {
                // Toggle direction or use forced direction
                if (forceDir) {
                    currentSortField = field;
                    currentSortDir = forceDir;
                } else {
                    if (currentSortField === field) {
                        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSortField = field;
                        currentSortDir = 'asc';
                    }
                }

                // Update header CSS
                var ths = document.querySelectorAll('th.sortable');
                for (var i = 0; i < ths.length; i++) {
                    ths[i].classList.remove('sort-asc', 'sort-desc');
                    if (ths[i].getAttribute('data-sort') === field) {
                        ths[i].classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                    }
                }

                // Determine field type for comparison
                var numericFields = ['adet', 'stokAdet', 'uretimdeAdet', 'uretimMusaitAdet', 'eslesmePuani', 'no'];
                var dateFields = ['siparisTarihi', 'kargoSonTeslim'];
                var isNumeric = numericFields.indexOf(field) >= 0;
                var isDate = dateFields.indexOf(field) >= 0;

                allOrders.sort(function (a, b) {
                    var va = a[field] || '';
                    var vb = b[field] || '';

                    if (isNumeric) {
                        va = parseFloat(va) || 0;
                        vb = parseFloat(vb) || 0;
                    } else if (isDate) {
                        // Parse "dd.MM.yyyy HH:mm" format
                        va = parseTurkishDate(va);
                        vb = parseTurkishDate(vb);
                    } else {
                        va = ('' + va).toLocaleLowerCase('tr');
                        vb = ('' + vb).toLocaleLowerCase('tr');
                    }

                    var cmp = 0;
                    if (va < vb) cmp = -1;
                    else if (va > vb) cmp = 1;
                    return currentSortDir === 'asc' ? cmp : -cmp;
                });

                currentPage = 1; // Sıralama değişince ilk sayfaya dön
                renderGrid();
            }

            function parseTurkishDate(str) {
                if (!str) return 0;
                // "dd.MM.yyyy HH:mm" → sortable number
                var parts = str.split(' ');
                var d = parts[0] ? parts[0].split('.') : [];
                var t = parts[1] ? parts[1].split(':') : ['00', '00'];
                if (d.length < 3) return 0;
                return new Date(parseInt(d[2]), parseInt(d[1]) - 1, parseInt(d[0]),
                    parseInt(t[0] || 0), parseInt(t[1] || 0)).getTime() || 0;
            }

            // ============================================================
            //   PAGINATION ACTIONS
            // ============================================================
            function changePageSize() {
                var newSize = parseInt(document.getElementById('pageSizeSelect').value, 10);
                if (!isNaN(newSize)) {
                    pageSize = newSize;
                    currentPage = 1;
                    renderGrid();
                }
            }

            function prevPage() {
                if (currentPage > 1) {
                    currentPage--;
                    renderGrid();
                }
            }

            function nextPage() {
                var mainOrders = allOrders.filter(function (o) { return o.anaSetSatirNo === 0; });
                var totalPages = Math.ceil(mainOrders.length / pageSize);
                if (currentPage < totalPages) {
                    currentPage++;
                    renderGrid();
                }
            }

            function goToPage(page) {
                currentPage = page;
                renderGrid();
            }

            function renderPagination(totalItems) {
                var container = document.getElementById('paginationContainer');
                if (totalItems === 0) {
                    container.style.display = 'none';
                    return;
                }

                container.style.display = 'flex';
                var totalPages = Math.ceil(totalItems / pageSize);

                // Adjust currentPage if needed (e.g. after filter)
                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);

                var startItem = (currentPage - 1) * pageSize + 1;
                var endItem = Math.min(currentPage * pageSize, totalItems);
                document.getElementById('pageInfo').textContent = startItem + '-' + endItem + ' / ' + totalItems + ' sipariş';

                document.getElementById('btnPrevPage').disabled = currentPage === 1;
                document.getElementById('btnNextPage').disabled = currentPage === totalPages;

                // Page numbers logic (show window of 5 pages)
                var pageNumHtml = '';
                var startPage = Math.max(1, currentPage - 2);
                var endPage = Math.min(totalPages, startPage + 4);
                if (endPage - startPage < 4) {
                    startPage = Math.max(1, endPage - 4);
                }

                if (startPage > 1) {
                    pageNumHtml += '<div class="page-number" onclick="goToPage(1)">1</div>';
                    if (startPage > 2) pageNumHtml += '<div style="padding:0 5px; color:#b0a080;">...</div>';
                }

                for (var i = startPage; i <= endPage; i++) {
                    var act = i === currentPage ? ' active' : '';
                    pageNumHtml += '<div class="page-number' + act + '" onclick="goToPage(' + i + ')">' + i + '</div>';
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) pageNumHtml += '<div style="padding:0 5px; color:#b0a080;">...</div>';
                    pageNumHtml += '<div class="page-number" onclick="goToPage(' + totalPages + ')">' + totalPages + '</div>';
                }

                document.getElementById('pageNumbers').innerHTML = pageNumHtml;
            }

            // ============================================================
            //   GRID RENDER
            // ============================================================
	            function renderGrid() {
	                var tbody = document.getElementById('ordersBody');
	                tbody.innerHTML = '';
	                pruneSelectionToKnownOrders();
	                var masterCheckbox = document.getElementById('masterCheckbox');
	                masterCheckbox.checked = false;
	                masterCheckbox.indeterminate = false;

                // 1) Sadece parent satırları ve normal satırları al (childs hariç)
                var mainOrders = allOrders.filter(function (o) {
                    return o.anaSetSatirNo === 0;
                });

                // 2) Pagination uygula
                renderPagination(mainOrders.length);
                var displayOrders = mainOrders.slice((currentPage - 1) * pageSize, currentPage * pageSize);
                renderOrdersTotalFooter(mainOrders, displayOrders);

                displayOrders.forEach(function (order, idx) {
                    var i = (currentPage - 1) * pageSize + idx;

                    // Alt bileşen satırlarını ana döngüde atla (parent'ın altına eklenecek)
                    if (order.anaSetSatirNo > 0) return;

                    var tr = document.createElement('tr');
                    tr.setAttribute('data-no', order.no);

                    // Row class — durum + kargo aciliyet + set
                    if (order.setMi) {
                        tr.className = 'set-parent-row';
                    } else if (order.durum === 'IsEmriVerildi') {
                        tr.className = 'is-emri-verildi';
                    } else if (order.durum === 'Pasif') {
                        tr.className = 'pasif';
                    } else if (order.durum === 'UretimBekliyor' && order.kargoSonTeslim) {
                        var urgency = getUrgencyClass(order.kargoSonTeslim);
                        if (urgency) tr.className = urgency;
                    }
                    if (hasEnoughStockForOrder(order)) {
                        tr.classList.add('stock-ready');
                    }

                    // Durum badge
                    var durumHtml = '';
                    if (order.setMi) {
                        // Set parent — bileşen sayısını göster
                        var childCount = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; }).length;
                        durumHtml = '<span class="badge-set-parent" onclick="toggleSetChildren(' + order.no + ')" title="' + childCount + ' bileşen - tıkla genişlet">' +
                            '📦 SET (' + childCount + ') ▼</span>';
                    } else if (order.durum === 'UretimBekliyor') durumHtml = '<span class="badge-bekliyor">🟡 Bekliyor</span>';
                    else if (order.durum === 'IsEmriVerildi') durumHtml = '<span class="badge-verildi work-order-trigger" data-no="' + order.no + '" tabindex="0" aria-label="İş emri takibini göster">🟢 Verildi</span>';
                    else if (order.durum === 'PasifDevamEden') durumHtml = '<span class="badge-bekliyor" style="color: #d97706; background: rgba(217,119,6,0.1);">🔸 Devam Eden</span>';
                    else if (order.durum === 'UretimdenKarsilaniyor') durumHtml = '<span style="background: #e8daef; color: #8e44ad; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block;">🔗 Rezerve</span>';
                    else if (order.durum === 'StokKarsilandi') durumHtml = '<span class="badge-stok">📦 Stoktan</span>';
                    else durumHtml = '<span class="badge-pasif">⚪ Pasif</span>';

                    // Eşleşme
                    var matchHtml = '';
                    if (order.setMi) {
                        matchHtml = '<span style="color:#e67e22; font-weight:600;">Set</span>';
                    } else if (Number(order.eslesenUrunNo || 0) > 0) matchHtml = '<span class="match-ok">✅ ' + (order.eslesmeYontemi === 'Manuel' ? 'Manuel' : '%' + (order.eslesmePuani || 100)) + '</span>';
                    else if (order.eslesmePuani >= 40) matchHtml = '<span class="match-warn">⚠️ %' + order.eslesmePuani + '</span>';
                    else matchHtml = '<span class="match-fail">❌ Yok</span>';

                    // Müşteri notu
                    var musteriNoteHtml = '';
                    if (order.musteriNotu) {
                        musteriNoteHtml = ' <i class="bi bi-sticky note-icon" title="' + escapeHtmlAttr(order.musteriNotu) + '"></i>';
                    }

                    // Stok kolonu
                    var stokAdet = order.stokAdet || 0;
                    var stokClass = stokAdet >= order.adet ? 'stok-yeterli' : (stokAdet > 0 ? 'stok-kismi' : 'stok-yok');
                    var stokBtnHtml = '';
                    var stokHtml = order.setMi ? '<span style="color:#888">—</span>' : '<span class="stock-inline"><span class="' + stokClass + '">' + stokAdet + '</span>' + stokBtnHtml + '</span>';

                    // Üretimde / Müsait kolonu
                    var uretimdeAdet = safeNumber(order.uretimdeAdet);
                    var uretimMusaitAdet = safeNumber(order.uretimMusaitAdet);
                    var uretimKapasiteToplamAdet = safeNumber(order.uretimOzelToplamAdet);
                    var uretimdeHtml = renderProductionCell(order, uretimdeAdet, uretimMusaitAdet, uretimKapasiteToplamAdet);

                    // Toplu aksiyon ve eşleştirme sadece üretim bekleyen aktif siparişlerde açık kalır
                    var cbDisabled = order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.durum === 'PasifDevamEden' || order.setMi;
                    var btnDisabled = order.durum === 'IsEmriVerildi' || order.durum === 'PasifDevamEden' || order.durum === 'UretimdenKarsilaniyor' || order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.eslesenUrunNo <= 0 || order.setMi;
                    var matchDisabled = order.durum !== 'UretimBekliyor' || order.setMi;

                    // Ürün Adı — set ise özel göster
                    var urunAdiDisplay = order.setMi
                        ? '<span style="cursor:pointer;" onclick="toggleSetChildren(' + order.no + ')">' + escapeHtml(order.urunAdi) + '</span>'
                        : escapeHtml(order.urunAdi);

                    // Manuel eşleştirme satır içinde ikonla gösterilir; seçim minimal pop-up içinde yapılır.
                    var matchControlHtml = renderMatchControl(order, matchDisabled);

                    var actionHtml = '';
                    if (order.setMi) {
                        actionHtml = '<div class="row-action-stack single-action">' +
                                     '<button type="button" class="btn-row-action" onclick="toggleSetChildren(' + order.no + ')" style="background:#e67e22; width:100%;"><i class="bi bi-chevron-down"></i> Detay</button>' +
                                     (order.durum === 'UretimBekliyor'
                                        ? '<button type="button" class="btn-row-action" onclick="clearOrderMatch(' + order.no + ')" style="background:#64748b; width:100%;"><i class="bi bi-x-lg"></i> Seti İptal</button>'
                                        : '') +
                                     '</div>';
                    } else if (order.durum === 'IsEmriVerildi') {
                        actionHtml = '<button type="button" class="btn-row-action btn-cancel" data-no="' + order.no + '" onclick="cancelWorkOrder(' + order.no + ')" style="background:#c0392b;"><i class="bi bi-x-lg"></i> İptal Et</button>';
                    } else if (order.durum === 'UretimdenKarsilaniyor') {
                        actionHtml = '<div class="row-action-stack single-action">' +
                                     '<span class="row-action-status" style="color:#8e44ad;"><i class="bi bi-link-45deg"></i> Üretime Bağlandı</span>' +
                                     '<button type="button" class="btn-row-action" style="background:#e74c3c; width:100%; border:1px solid #c0392b;" onclick="cancelWipAllocation(' + order.no + ')"><i class="bi bi-x-lg"></i> GİED İptal</button>' +
                                     '</div>';
                    } else if (order.durum === 'StokKarsilandi') {
                        actionHtml = '<div class="row-action-stack single-action">' +
                                     '<span class="badge-stok">📦 Tamamlandı</span>' +
                                     '<button type="button" class="btn-row-action" style="background:#64748b; width:100%;" onclick="undoDeductStock(event, ' + order.no + ')"><i class="bi bi-arrow-counterclockwise"></i> Geri Al</button>' +
                                     '</div>';
                    } else if (order.durum === 'Pasif') {
                        actionHtml = '<span class="badge-pasif">⚪ Kapalı</span>';
                    } else if (order.durum === 'PasifDevamEden') {
                        actionHtml = '<span class="badge-pasif" style="color:#d97706; background:rgba(217,119,6,0.1);">🔸 Üretimde (İptal)</span>';
                    } else {
                        var baglaBtnDisabled = order.durum !== 'UretimBekliyor' || order.eslesenUrunNo <= 0;
                        var giedStyle = baglaBtnDisabled ? 'background:#ccc; color:#777; cursor:not-allowed;' : 'background:#8e44ad;';
                        actionHtml = renderOrderDecisionActions(order, btnDisabled, baglaBtnDisabled, giedStyle);
                    }

                    var orderMetaHtml = '<span class="cell-muted">#' + (i + 1) + (order.pazaryeri ? ' · ' + escapeHtml(order.pazaryeri) : '') + '</span>';
                    var adetHtml = '<span class="metric-value">' + order.adet + '</span>';
                    var stockCellHtml = '<span class="metric-value">' + stokHtml + '</span>';
                    var productionCellHtml = uretimdeHtml;
                    var stateHtml = '<div class="cell-stack compact">' + durumHtml + '<span class="match-slot">' + matchHtml + '</span></div>';
                    var operationHtml = '<div class="operation-stack"><div class="operation-match">' + matchControlHtml + '</div>' + actionHtml + '</div>';

                    tr.innerHTML =
                        '<td class="col-select"><input type="checkbox" class="row-cb" data-no="' + order.no + '" onchange="onRowCheckChange(this)" ' + (cbDisabled ? 'disabled' : '') + ' /></td>' +
                        '<td class="col-order" title="' + escapeHtmlAttr(order.siparisNo) + '"><div class="cell-stack"><strong class="cell-main">' + escapeHtml(order.siparisNo) + '</strong>' + orderMetaHtml + '</div></td>' +
                        '<td class="col-customer" title="' + escapeHtmlAttr(order.musteri) + '"><div class="cell-stack"><span class="cell-main">' + escapeHtml(order.musteri) + musteriNoteHtml + '</span></div></td>' +
                        '<td class="col-product" title="' + escapeHtmlAttr(order.urunAdi) + '"><span class="cell-main">' + urunAdiDisplay + '</span></td>' +
                        '<td class="col-adet" data-label="Adet">' + adetHtml + '</td>' +
                        '<td class="col-stock" data-label="Stok">' + stockCellHtml + '</td>' +
                        '<td class="col-production" data-label="Üretim">' + productionCellHtml + '</td>' +
                        '<td class="col-order-date" data-label="Sip.">' + renderDateCellHtml(order.siparisTarihi) + '</td>' +
                        '<td class="col-cargo-date" data-label="Kargo">' + renderDateCellHtml(order.kargoSonTeslim) + '</td>' +
                        '<td class="col-state">' + stateHtml + '</td>' +
                        '<td class="col-operation">' + operationHtml + '</td>';

                    tbody.appendChild(tr);

                    // Set parent ise — hemen altına child satırları ekle
                    if (order.setMi) {
                        var children = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; });
                        children.forEach(function (child) {
                            var ctr = document.createElement('tr');
                            ctr.className = 'set-child-row';
                            ctr.setAttribute('data-no', child.no);
                            ctr.setAttribute('data-parent', order.no);
                            if (child.durum === 'IsEmriVerildi') {
                                ctr.classList.add('is-emri-verildi');
                            }
                            if (hasEnoughStockForOrder(child)) {
                                ctr.classList.add('stock-ready');
                            }

                            var childDurumHtml = '';
                            if (child.durum === 'UretimBekliyor') childDurumHtml = '<span class="badge-bekliyor">🟡 Bekliyor</span>';
                            else if (child.durum === 'IsEmriVerildi') childDurumHtml = '<span class="badge-verildi work-order-trigger" data-no="' + child.no + '" tabindex="0" aria-label="İş emri takibini göster">🟢 Verildi</span>';
                            else if (child.durum === 'PasifDevamEden') childDurumHtml = '<span class="badge-bekliyor" style="color: #d97706; background: rgba(217,119,6,0.1);">🔸 Devam Eden</span>';
                            else if (child.durum === 'UretimdenKarsilaniyor') childDurumHtml = '<span style="background: #e8daef; color: #8e44ad; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block;">🔗 Rezerve</span>';
                            else if (child.durum === 'StokKarsilandi') childDurumHtml = '<span class="badge-stok">📦 Stoktan</span>';
                            else childDurumHtml = '<span class="badge-pasif">⚪ Pasif</span>';

                            var childMatchHtml = Number(child.eslesenUrunNo || 0) > 0
                                ? '<span class="match-ok">✅ ' + (child.eslesmeYontemi === 'Manuel' ? 'Manuel' : '%' + (child.eslesmePuani || 100)) + '</span>'
                                : '<span class="match-fail">❌ Yok</span>';

                            var childStokAdet = child.stokAdet || 0;
                            var childStokClass = childStokAdet >= child.adet ? 'stok-yeterli' : (childStokAdet > 0 ? 'stok-kismi' : 'stok-yok');
                            var childStokBtnHtml = '';

                            var childUretimde = safeNumber(child.uretimdeAdet);
                            var childUretimMusait = safeNumber(child.uretimMusaitAdet);
                            var childUretimKapasiteToplam = safeNumber(child.uretimOzelToplamAdet);
                            var childBtnDisabled = child.durum === 'IsEmriVerildi' || child.durum === 'PasifDevamEden' || child.durum === 'UretimdenKarsilaniyor' || child.durum === 'Pasif' || child.durum === 'StokKarsilandi' || child.eslesenUrunNo <= 0;
                            var childMatchDisabled = child.durum !== 'UretimBekliyor';

                            var childActionHtml = '';
                            if (child.durum === 'IsEmriVerildi') {
                                childActionHtml = '<button type="button" class="btn-row-action btn-cancel" onclick="cancelWorkOrder(' + child.no + ')" style="background:#c0392b;"><i class="bi bi-x-lg"></i> İptal</button>';
                            } else if (child.durum === 'UretimdenKarsilaniyor') {
                                childActionHtml = '<div class="row-action-stack single-action">' +
                                                  '<span class="row-action-status" style="color:#8e44ad;"><i class="bi bi-link-45deg"></i> Üretime Bağlandı</span>' +
                                                  '<button type="button" class="btn-row-action" style="background:#e74c3c; width:100%; border:1px solid #c0392b;" onclick="cancelWipAllocation(' + child.no + ')"><i class="bi bi-x-lg"></i> GİED İptal</button>' +
                                                  '</div>';
                            } else if (child.durum === 'StokKarsilandi') {
                                childActionHtml = '<div class="row-action-stack single-action">' +
                                                  '<span class="badge-stok">📦 OK</span>' +
                                                  '<button type="button" class="btn-row-action" style="background:#64748b; width:100%;" onclick="undoDeductStock(event, ' + child.no + ')"><i class="bi bi-arrow-counterclockwise"></i> Geri Al</button>' +
                                                  '</div>';
                            } else {
                                var baglaChildBtnDisabled = child.durum !== 'UretimBekliyor' || child.eslesenUrunNo <= 0;
                                var childGiedStyle = baglaChildBtnDisabled ? 'background:#ccc; color:#777; cursor:not-allowed;' : 'background:#8e44ad;';
                                childActionHtml = renderOrderDecisionActions(child, childBtnDisabled, baglaChildBtnDisabled, childGiedStyle);
                            }

                            var childCbDisabled = child.durum === 'Pasif' || child.durum === 'StokKarsilandi' || child.durum === 'PasifDevamEden';
                            var childAdetHtml = '<span class="metric-value">' + child.adet + '</span>';
                            var childStockHtml = '<span class="metric-value"><span class="stock-inline"><span class="' + childStokClass + '">' + childStokAdet + '</span>' + childStokBtnHtml + '</span></span>';
                            var childProductionHtml = renderProductionCell(child, childUretimde, childUretimMusait, childUretimKapasiteToplam);
                            var childStateHtml = '<div class="cell-stack compact">' + childDurumHtml + '<span class="match-slot">' + childMatchHtml + '</span></div>';
                            var childOperationHtml = '<div class="operation-stack"><div class="operation-match">' + renderMatchControl(child, childMatchDisabled) + '</div>' + childActionHtml + '</div>';

                            ctr.innerHTML =
                                '<td class="col-select"><input type="checkbox" class="row-cb" data-no="' + child.no + '" onchange="onRowCheckChange(this)" ' + (childCbDisabled ? 'disabled' : '') + ' /></td>' +
                                '<td class="col-order" title="' + escapeHtmlAttr(child.siparisNo) + '"><div class="cell-stack"><strong class="cell-main">' + escapeHtml(child.siparisNo) + '</strong><span class="cell-muted">↳ Bileşen' + (child.pazaryeri ? ' · ' + escapeHtml(child.pazaryeri) : '') + '</span></div></td>' +
                                '<td class="col-customer" title="' + escapeHtmlAttr(child.musteri) + '"><span class="cell-main">' + escapeHtml(child.musteri) + '</span></td>' +
                                '<td class="col-product" title="' + escapeHtmlAttr(child.urunAdi) + '"><span class="badge-set-child">Bileşen</span> <span class="cell-main">' + escapeHtml(child.urunAdi) + '</span></td>' +
                                '<td class="col-adet" data-label="Adet">' + childAdetHtml + '</td>' +
                                '<td class="col-stock" data-label="Stok">' + childStockHtml + '</td>' +
                                '<td class="col-production" data-label="Üretim">' + childProductionHtml + '</td>' +
                                '<td class="col-order-date" data-label="Sip.">' + renderDateCellHtml(child.siparisTarihi) + '</td>' +
                                '<td class="col-cargo-date" data-label="Kargo">' + renderDateCellHtml(child.kargoSonTeslim) + '</td>' +
                                '<td class="col-state">' + childStateHtml + '</td>' +
                                '<td class="col-operation">' + childOperationHtml + '</td>';

                            tbody.appendChild(ctr);
                        });
                    }

	                });

	                syncRenderedSelection();
	                syncMasterCheckbox();
	                updateSelectedCount();
            }

            function renderMatchControl(order, matchDisabled) {
                if (order.setMi) {
                    return '<span class="match-status-icon is-set" title="Set üründe eşleştirme bileşen satırlarından yapılır" aria-label="Set bileşenlerinde eşleşir">' +
                        '<i class="bi bi-box-seam"></i>' +
                    '</span>';
                }

                var matched = Number(order.eslesenUrunNo || 0) > 0;
                var productName = matched ? displayProductNameForOrder(order) : '';
                var title = matched
                    ? productName
                    : (matchDisabled ? 'Eşleştirme bu durumda değiştirilemez' : 'Ürün eşleştir');
                var className = 'match-status-icon ' + (matched ? 'is-matched' : 'is-unmatched') + (matchDisabled ? ' is-disabled' : '');
                var clickAttr = matchDisabled ? '' : ' onclick="openMatchDialog(' + order.no + ')"';
                var aria = matched
                    ? 'Eşleşen ürün: ' + productName
                    : 'Ürün eşleştir';
                var icon = matched ? 'bi-check-lg' : 'bi-link-45deg';

                return '<button type="button" class="' + className + '"' + clickAttr +
                    ' title="' + escapeHtmlAttr(title) + '"' +
                    ' aria-label="' + escapeHtmlAttr(aria) + '"' +
                    (matchDisabled ? ' aria-disabled="true"' : '') + '>' +
                        '<i class="bi ' + icon + '"></i>' +
                    '</button>';
            }

            function openMatchDialog(satirNo) {
                var order = findOrderByNo(satirNo);
                if (!order || order.setMi || order.durum !== 'UretimBekliyor') {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Eşleştirme kapalı',
                        text: 'Bu satırın eşleştirmesi mevcut durumda değiştirilemez.',
                        confirmButtonColor: '#d4af37'
                    });
                    return;
                }

                if (!dbProducts.length) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Ürün listesi yok',
                        text: 'Sistem ürünleri yüklenemedi. Sayfayı yenileyip tekrar deneyin.',
                        confirmButtonColor: '#d4af37'
                    });
                    return;
                }

                var selectedKey = orderProductKey(order);
                var initialQuery = order.urunAdi || '';
                var currentlyMatched = Number(order.eslesenUrunNo || 0) > 0;

                Swal.fire({
                    title: 'Ürün Eşleştir',
                    html: matchDialogHtml(order, initialQuery),
                    width: 520,
                    showCancelButton: true,
                    showDenyButton: currentlyMatched,
                    confirmButtonText: 'Eşleştir',
                    denyButtonText: 'Eşleşmeyi İptal Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#0d9488',
                    denyButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    focusConfirm: false,
                    showLoaderOnConfirm: true,
                    didOpen: function () {
                        var popup = Swal.getPopup();
                        var input = popup.querySelector('#matchProductSearch');
                        var results = popup.querySelector('#matchProductResults');
                        var confirmButton = Swal.getConfirmButton();

                        function selectProduct(key) {
                            selectedKey = key;
                            renderDialogResults(results, input.value, selectedKey, selectProduct);
                            confirmButton.disabled = !selectedKey;
                        }

                        input.addEventListener('input', function () {
                            renderDialogResults(results, input.value, selectedKey, selectProduct);
                        });

                        renderDialogResults(results, initialQuery, selectedKey, selectProduct);
                        confirmButton.disabled = !selectedKey;
                        setTimeout(function () {
                            input.focus();
                            input.select();
                        }, 50);
                    },
                    preConfirm: function () {
                        if (!selectedKey) {
                            Swal.showValidationMessage('Bir ürün seçin.');
                            return false;
                        }

                        return submitProductMatch(satirNo, selectedKey).catch(function (message) {
                            Swal.showValidationMessage(message || 'Eşleştirme kaydedilemedi.');
                            return false;
                        });
                    },
                    allowOutsideClick: function () {
                        return !Swal.isLoading();
                    }
                }).then(function (result) {
                    if (result.isDenied) {
                        clearOrderMatch(satirNo);
                        return;
                    }
                    if (!result.isConfirmed || !result.value) return;
                    showMatchSavedToast(result.value);
                    loadOrders();
                });
            }

            function matchDialogHtml(order, initialQuery) {
                return '<div class="match-dialog">' +
                    '<div class="match-dialog-order">' +
                        '<span>Sipariş ürünü</span>' +
                        '<strong title="' + escapeHtmlAttr(order.urunAdi || '') + '">' + escapeHtml(order.urunAdi || '-') + '</strong>' +
                    '</div>' +
                    '<div class="match-dialog-search">' +
                        '<i class="bi bi-search"></i>' +
                        '<input id="matchProductSearch" type="search" value="' + escapeHtmlAttr(initialQuery || '') + '" placeholder="Ürün ara">' +
                    '</div>' +
                    '<div id="matchProductResults" class="match-dialog-results"></div>' +
                '</div>';
            }

            function renderDialogResults(container, query, selectedKey, onSelect) {
                var products = filterMatchProducts(query, selectedKey);

                if (!products.length) {
                    container.innerHTML = '<div class="match-dialog-empty">Ürün bulunamadı</div>';
                    return;
                }

                container.innerHTML = products.map(function (product) {
                    var key = productCompositeKey(product);
                    var selected = key === selectedKey ? ' is-selected' : '';
                    var label = productLabel(product);
                    var meta = productMeta(product);

                    return '<button type="button" class="match-product-option' + selected + '" data-key="' + escapeHtmlAttr(key) + '">' +
                        '<strong>' + escapeHtml(label) + '</strong>' +
                        (meta ? '<small>' + escapeHtml(meta) + '</small>' : '') +
                    '</button>';
                }).join('');

                container.querySelectorAll('.match-product-option').forEach(function (button) {
                    button.addEventListener('click', function () {
                        onSelect(button.getAttribute('data-key'));
                    });
                });
            }

            function filterMatchProducts(query, selectedKey) {
                var q = normalizeProductSearch(query);
                var selectedProduct = selectedKey ? dbProducts.find(function (product) {
                    return productCompositeKey(product) === selectedKey;
                }) : null;

                var matches = [];
                if (!q) {
                    matches = dbProducts.slice(0, 40);
                } else {
                    var tokens = q.split(' ').filter(function (token) {
                        return token.length > 2;
                    });

                    matches = dbProducts.map(function (product) {
                        var text = productSearchText(product);
                        var score = text.indexOf(q) !== -1 ? 30 : 0;
                        tokens.forEach(function (token) {
                            if (text.indexOf(token) !== -1) score += 1;
                        });
                        return { product: product, score: score };
                    }).filter(function (item) {
                        return item.score > 0;
                    }).sort(function (a, b) {
                        if (b.score !== a.score) return b.score - a.score;
                        return productLabel(a.product).localeCompare(productLabel(b.product), 'tr');
                    }).slice(0, 40).map(function (item) {
                        return item.product;
                    });
                }

                if (selectedProduct && !matches.some(function (product) {
                    return productCompositeKey(product) === selectedKey;
                })) {
                    matches.unshift(selectedProduct);
                }

                return matches;
            }

            function normalizeProductSearch(value) {
                return String(value || '')
                    .toLocaleLowerCase('tr')
                    .replace(/[.,/\\()_\-]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function productSearchText(product) {
                return normalizeProductSearch([
                    productLabel(product),
                    product.urunId || '',
                    product.sistemKodu || '',
                    product.araAdlarYol || '',
                    product.tur || ''
                ].join(' '));
            }

            function productLabel(product) {
                return (product && (product.sistemAdi || product.urunId)) || (product ? 'Ürün #' + product.no : 'Ürün');
            }

            function productMeta(product) {
                if (!product) return '';
                return [
                    product.sistemKodu ? 'Kod: ' + product.sistemKodu : '',
                    product.urunId && product.urunId !== productLabel(product) ? product.urunId : '',
                    product.tur || 'Nihai'
                ].filter(Boolean).join(' · ');
            }

            function productCompositeKey(product) {
                return (product.tur || 'Nihai') + '_' + String(product.no);
            }

            function submitProductMatch(satirNo, compositeVal) {
                return new Promise(function (resolve, reject) {
                    var parts = String(compositeVal || '').split('_');
                    var tur = parts[0] || 'Nihai';
                    var urunNo = parseInt(parts[1], 10);

                    if (!satirNo || !urunNo) {
                        reject('Geçersiz ürün seçimi.');
                        return;
                    }

                    $.ajax({
                        url: apiUrl + '?action=matchProduct',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            siparisSatirNo: satirNo,
                            eslesenUrunNo: urunNo,
                            eslesenUrunTur: tur
                        }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                resolve(res);
                                return;
                            }
                            reject(res.message || 'Eşleştirme kaydedilemedi.');
                        },
                        error: function (xhr) {
                            reject((xhr.responseJSON && xhr.responseJSON.message) || xhr.statusText || 'Sunucuya bağlanılamadı.');
                        }
                    });
                });
            }

            function showMatchSavedToast(res) {
                var message = 'Eşleştirme kaydedildi.';
                if (res && Number(res.updatedCount || 0) > 0) {
                    message += ' ' + res.updatedCount + ' aynı ürün satırı da güncellendi.';
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: message,
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true
                });
            }

            function clearOrderMatch(satirNo) {
                var order = findOrderByNo(satirNo);
                if (!order) return;

                Swal.fire({
                    icon: 'warning',
                    title: order.setMi ? 'Set eşleşmesi iptal edilsin mi?' : 'Ürün eşleşmesi iptal edilsin mi?',
                    html: '<b>' + escapeHtml(order.urunAdi || '-') + '</b><br><small>Yanlış eşleşme ve varsa bu ürün adı için kayıtlı eşleştirme önbelleği kaldırılacak.</small>',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, İptal Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    reverseButtons: true
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'Eşleşme iptal ediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    $.ajax({
                        url: apiUrl + '?action=clearOrderMatch',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ siparisSatirNo: satirNo, deleteCache: true }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                var details = [];
                                if (Number(res.deletedChildren || 0) > 0) details.push(res.deletedChildren + ' set bileşeni kaldırıldı');
                                if (Number(res.deletedCache || 0) > 0) details.push('eşleştirme önbelleği temizlendi');
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eşleşme İptal Edildi',
                                    text: details.length ? details.join(', ') + '.' : (res.message || 'Satır eşleşmesiz hale getirildi.'),
                                    confirmButtonColor: '#0d9488'
                                });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message || 'Eşleşme iptal edilemedi.', confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            function renderOrdersTotalFooter(filteredOrders, pageOrders) {
                var footer = document.getElementById('ordersTotalFooter');
                if (!footer) return;

                var totals = calculateOrderAdetTotals(filteredOrders || []);
                var pageTotals = calculateOrderAdetTotals(pageOrders || []);
                footer.style.display = totals.rows > 0 ? 'flex' : 'none';
                if (totals.rows === 0) return;

                var searchText = ($('#filterArama').val() || '').trim();
                var scope = searchText ? 'Arama: "' + searchText + '"' : 'Filtrelenmiş liste';

                setText('ordersTotalScope', scope);
                setText('ordersTotalTitle', formatTrNumber(totals.rows) + ' sipariş satırı');
                setText('ordersTotalAdet', formatTrNumber(totals.adet));
                setText('ordersPageAdet', formatTrNumber(pageTotals.adet));
                setText('ordersPendingAdet', formatTrNumber(totals.pending));
                setText('ordersWorkOrderAdet', formatTrNumber(totals.workOrder));
                setText('ordersStockAdet', formatTrNumber(totals.stock));
            }

            function calculateOrderAdetTotals(orders) {
                return orders.reduce(function (totals, order) {
                    var adet = parseInt(order.adet, 10) || 0;
                    totals.rows += 1;
                    totals.adet += adet;

                    if (order.durum === 'UretimBekliyor') {
                        totals.pending += adet;
                    } else if (order.durum === 'IsEmriVerildi') {
                        totals.workOrder += adet;
                    } else if (order.durum === 'StokKarsilandi') {
                        totals.stock += adet;
                    }

                    return totals;
                }, { rows: 0, adet: 0, pending: 0, workOrder: 0, stock: 0 });
            }

            function setText(id, value) {
                var element = document.getElementById(id);
                if (element) element.textContent = value;
            }

            function formatTrNumber(value) {
                return (parseFloat(value) || 0).toLocaleString('tr-TR');
            }

	            // ============================================================
	            //   SEÇİM YÖNETİMİ
	            // ============================================================
	            function pruneSelectionToKnownOrders() {
	                var validSelection = {};
	                allOrders.forEach(function (order) {
	                    if ((order.anaSetSatirNo || 0) === 0) {
	                        validSelection[order.no] = true;
	                    }
	                });
	                Array.from(selectedNos).forEach(function (no) {
	                    if (!validSelection[no]) selectedNos.delete(no);
	                });
	            }

	            function syncRenderedSelection() {
	                document.querySelectorAll('#ordersBody .row-cb').forEach(function (cb) {
	                    applyRowSelectionState(cb, selectedNos.has(parseInt(cb.getAttribute('data-no'))));
	                });
	            }

	            function applyRowSelectionState(cb, selected) {
	                cb.checked = selected;
	                var row = cb.closest('tr');
	                if (row) row.classList.toggle('selected-row', selected);
	            }

	            function isSelectableRowVisible(row) {
	                if (!row) return false;
	                if (row.classList.contains('set-child-row') && !row.classList.contains('show')) {
	                    return false;
	                }
	                return row.offsetParent !== null;
	            }

	            function getVisibleSelectableCheckboxes() {
	                return Array.from(document.querySelectorAll('#ordersBody .row-cb:not(:disabled)')).filter(function (cb) {
	                    return isSelectableRowVisible(cb.closest('tr'));
	                });
	            }

	            function syncMasterCheckbox() {
	                var master = document.getElementById('masterCheckbox');
	                if (!master) return;
	                var checkboxes = getVisibleSelectableCheckboxes();
	                var checkedCount = checkboxes.filter(function (cb) { return cb.checked; }).length;
	                master.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
	                master.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
	            }

	            function onRowCheckChange(cb) {
	                var no = parseInt(cb.getAttribute('data-no'));
	                if (cb.checked) {
	                    selectedNos.add(no);
	                } else {
	                    selectedNos.delete(no);
	                }
	                applyRowSelectionState(cb, cb.checked);
	                syncMasterCheckbox();
	                updateSelectedCount();
	            }

	            function toggleAllRows(masterCb) {
	                var checkboxes = getVisibleSelectableCheckboxes();
	                checkboxes.forEach(function (cb) {
	                    var no = parseInt(cb.getAttribute('data-no'));
	                    if (masterCb.checked) {
	                        selectedNos.add(no);
	                    } else {
	                        selectedNos.delete(no);
	                    }
	                    applyRowSelectionState(cb, masterCb.checked);
	                });
	                syncMasterCheckbox();
	                updateSelectedCount();
	            }

            function updateSelectedCount() {
                var workOrderCount = selectedEligibleOrders(canBulkCreateWorkOrder).length;
                var cancelCount = selectedEligibleOrders(canBulkCancelWorkOrder).length;
                var stockCount = selectedEligibleOrders(canBulkDeductStock).length;
                var giedCount = selectedEligibleOrders(canBulkLinkWip).length;

                document.getElementById('selectedCount').textContent = selectedNos.size;
                document.getElementById('btnTopluIsEmri').disabled = workOrderCount === 0;
                document.getElementById('btnTopluIptal').disabled = cancelCount === 0;
                document.getElementById('btnTopluStok').disabled = stockCount === 0;
                document.getElementById('btnTopluGied').disabled = giedCount === 0;
                document.getElementById('btnTopluIsEmri').title = workOrderCount > 0 ? workOrderCount + ' uygun satır' : 'İş emri verilebilir satır seçilmedi';
                document.getElementById('btnTopluIptal').title = cancelCount > 0 ? cancelCount + ' uygun satır' : 'İptal edilebilir iş emri seçilmedi';
                document.getElementById('btnTopluStok').title = stockCount > 0 ? stockCount + ' uygun satır' : 'Stoktan karşılanabilir satır seçilmedi';
                document.getElementById('btnTopluGied').title = giedCount > 0 ? giedCount + ' uygun satır' : 'GİED yapılabilir satır seçilmedi';
            }

            function getSelectedOrders() {
                var orders = [];
                selectedNos.forEach(function (no) {
                    var order = allOrders.find(function (o) { return o.no === no; });
                    if (order) orders.push(order);
                });
                return orders;
            }

            function selectedEligibleOrders(predicate) {
                return getSelectedOrders().filter(predicate);
            }

            function canBulkCreateWorkOrder(order) {
                return order
                    && !order.setMi
                    && order.durum === 'UretimBekliyor'
                    && order.eslesenUrunNo > 0;
            }

            function canBulkCancelWorkOrder(order) {
                return order
                    && !order.setMi
                    && order.durum === 'IsEmriVerildi';
            }

            function canBulkDeductStock(order) {
                var stokAdet = order ? (order.stokAdet || 0) : 0;
                var adet = order ? (order.adet || 0) : 0;
                return order
                    && !order.setMi
                    && (order.durum === 'UretimBekliyor' || order.durum === 'UretimdenKarsilaniyor')
                    && order.eslesenUrunNo > 0
                    && stokAdet >= adet;
            }

            function canBulkLinkWip(order) {
                return order
                    && !order.setMi
                    && order.durum === 'UretimBekliyor'
                    && order.eslesenUrunNo > 0;
            }

            function findOrderByNo(no) {
                return allOrders.find(function (order) { return Number(order.no) === Number(no); }) || null;
            }

            function hasEnoughStockForOrder(order) {
                if (!order) return false;
                return Number(order.stokAdet || 0) >= Number(order.adet || 0)
                    && Number(order.adet || 0) > 0
                    && order.durum === 'UretimBekliyor'
                    && Number(order.eslesenUrunNo || 0) > 0;
            }

            function renderOrderDecisionActions(order, workOrderDisabled, giedDisabled, giedStyle) {
                var stockAvailable = hasEnoughStockForOrder(order);
                var workOrderLabel = stockAvailable ? 'Üret' : 'İş Emri';
                var stackClass = 'row-action-stack' + (stockAvailable ? ' has-stock' : '');
                var stockButton = stockAvailable
                    ? '<button type="button" class="btn-row-action stock-primary" onclick="deductStock(event, ' + order.no + ')" title="Stoktan karşıla" aria-label="Stoktan karşıla"><i class="bi bi-box2-open"></i> Stok</button>'
                    : '';

                return '<div class="' + stackClass + '">' +
                    stockButton +
	                    '<button type="button" class="btn-row-action ' + (stockAvailable ? 'production-secondary' : '') + '" data-no="' + order.no + '" onclick="submitSingleWorkOrder(' + order.no + ')" ' + (workOrderDisabled ? 'disabled' : '') + ' title="' + (stockAvailable ? 'Üretime al' : 'İş emri ver') + '" aria-label="' + (stockAvailable ? 'Üretime al' : 'İş emri ver') + '"><i class="bi bi-hammer"></i> ' + workOrderLabel + '</button>' +
                    '<button type="button" class="btn-row-action" style="' + giedStyle + '" onclick="showWipAllocationDialog(' + order.no + ')" ' + (giedDisabled ? 'disabled' : '') + ' title="GİED ile üretime bağla" aria-label="GİED ile üretime bağla"><i class="bi bi-link-45deg"></i> GİED</button>' +
                    '</div>';
            }

            function stockChoiceNoticeHtml(order) {
                if (!hasEnoughStockForOrder(order)) return '';
                return '<div style="text-align:left; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; padding:10px 12px; border-radius:8px; margin-top:10px; font-size:0.9rem;">' +
                    '<strong>Bu sipariş için stok yeterli.</strong><br>' +
                    'Stoktan karşılarsanız üretim/havuz/görev açılmaz. Üretime alırken stok kullanımı kararını ayrıca seçebilirsiniz.' +
                    '</div>';
            }

            function stockChoiceBulkNoticeHtml(orders) {
                var stockReadyCount = orders.filter(hasEnoughStockForOrder).length;
                if (stockReadyCount === 0) return '';
                return '<div style="text-align:left; background:#eff6ff; border:1px solid #bfdbfe; color:#1e3a8a; padding:10px 12px; border-radius:8px; margin-top:10px; font-size:0.9rem;">' +
                    '<strong>' + stockReadyCount + ' seçili satır stoktan karşılanabilecek durumda.</strong><br>' +
                    'Bu onay üretime alma işlemidir; stoktan karşılama için ayrı mavi Stok işlemini kullanın.' +
                    '</div>';
            }

            function workOrderStockModeSelectorHtml(defaultMode) {
                defaultMode = defaultMode === 'StokHaric' ? 'StokHaric' : 'StokDahil';
                var checkedDahil = defaultMode === 'StokDahil' ? ' checked' : '';
                var checkedHaric = defaultMode === 'StokHaric' ? ' checked' : '';
                return '<div style="text-align:left; margin-top:12px;">' +
                    '<div style="font-size:0.78rem; font-weight:800; color:#64748b; margin-bottom:8px;">Stok kullanım kararı</div>' +
                    '<label style="display:block; cursor:pointer; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; margin-bottom:8px;">' +
                        '<input type="radio" name="swalStokDurum" value="StokDahil"' + checkedDahil + '> ' +
	                        '<strong>Stok Dahil</strong><br><span style="color:#64748b; font-size:0.78rem;">Her BOM satırı kendi boş stoğuyla değerlendirilir; üst parça stoğu alt parçayı kapatmaz.</span>' +
                    '</label>' +
                    '<label style="display:block; cursor:pointer; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px;">' +
                        '<input type="radio" name="swalStokDurum" value="StokHaric"' + checkedHaric + '> ' +
	                        '<strong>Stok Hariç</strong><br><span style="color:#64748b; font-size:0.78rem;">Stoka dokunmadan BOM ihtiyacı kadar üretim açılır.</span>' +
                    '</label>' +
                '</div>';
            }

            function readSelectedWorkOrderStockMode() {
                var selected = document.querySelector('input[name="swalStokDurum"]:checked');
                return selected && selected.value === 'StokHaric' ? 'StokHaric' : 'StokDahil';
            }

            async function chooseWorkOrderStockMode(defaultMode, title) {
                var result = await Swal.fire({
                    title: title || 'Stok Kullanımı',
                    html: workOrderStockModeSelectorHtml(defaultMode),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Devam Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#4b9460',
                    cancelButtonColor: '#80694d',
                    width: 520,
                    preConfirm: function () {
                        return readSelectedWorkOrderStockMode();
                    }
                });
                return result.isConfirmed ? (result.value || defaultMode || 'StokDahil') : null;
            }

			            function mapOrderNos(orders) {
			                return orders.map(function (order) { return order.no; });
			            }

	            function orderProductKey(order) {
	                if (!order || !(parseInt(order.eslesenUrunNo) > 0)) return '';
	                return (order.eslesenUrunTur || 'Nihai') + '_' + String(order.eslesenUrunNo);
	            }

	            function findDbProductForOrder(order) {
	                var key = orderProductKey(order);
	                if (!key) return null;
	                return dbProducts.find(function (product) {
	                    return ((product.tur || 'Nihai') + '_' + String(product.no)) === key;
	                }) || null;
	            }

	            function displayProductNameForOrder(order) {
	                var product = findDbProductForOrder(order);
	                return (product && (product.sistemAdi || product.urunId)) || (order && order.urunAdi) || 'Bu Ürün';
	            }

	            function skippedSelectionHtml(totalCount, eligibleCount) {
                var skipped = totalCount - eligibleCount;
                if (skipped <= 0) return '';
                return '<br><small class="text-muted">' + skipped + ' seçili satır bu işlem için uygun olmadığı için gönderilmeyecek.</small>';
            }

            function warnNoEligibleSelection(title, text) {
                Swal.fire({
                    icon: 'warning',
                    title: title,
                    text: text,
                    confirmButtonColor: '#d4af37'
                });
            }

            // ============================================================
            //   TEKLİ İŞ EMRİ VER
            // ============================================================
	            async function submitSingleWorkOrder(no) {
	                var order = findOrderByNo(no);
	                var stockAvailable = hasEnoughStockForOrder(order);
                    var stokDurum = await chooseWorkOrderStockMode(stockAvailable ? 'StokHaric' : 'StokDahil', stockAvailable ? 'Üretim Stok Kararı' : 'İş Emri Stok Kararı');
                    if (!stokDurum) return;

	                var confirmed = await window.workOrderBomPreviewConfirm({
	                    mode: 'orders',
	                    satirNolar: [no],
                        stokDurum: stokDurum
	                }, {
                    title: stockAvailable ? 'Üretime Al' : 'İş Emri Ver',
                    html: (stockAvailable
                        ? 'Bu sipariş stoktan karşılanabilecek durumda; yine de üretim akışı başlatılsın mı?'
                        : 'Bu sipariş için iş emri oluşturulsun mu?') + stockChoiceNoticeHtml(order),
                    confirmButtonText: stockAvailable ? 'Üretime Al' : 'Evet, Oluştur',
                    cancelButtonText: 'Vazgeç',
                    previewConfirmText: stockAvailable ? 'Onayla ve üretime al' : 'Onayla ve oluştur',
                    confirmButtonColor: '#4b9460',
                    cancelButtonColor: '#80694d'
                });
                if (!confirmed) return;

                Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                $.ajax({
	                    url: apiUrl + '?action=createOrderWorkOrders',
	                    type: 'POST',
	                    contentType: 'application/json',
	                    data: JSON.stringify({ satirNolar: [no], stokDurum: stokDurum }),
	                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var errHtml = '';
                            if (res.errors && res.errors.length > 0) {
                                    errHtml = '<br><small class="text-danger">' + res.errors.map(function (e) { return escapeHtml(e); }).join('<br>') + '</small>';
                            }
                            Swal.fire({
                                icon: res.created > 0 ? 'success' : 'warning',
                                title: res.created > 0 ? 'İş Emri Oluşturuldu!' : 'İşlem Tamamlandı',
                                    html: escapeHtml(res.message || '') + errHtml,
                                confirmButtonColor: '#4b9460'
                            });
                            loadOrders();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                    }
                });
            }

            // ============================================================
            //   İŞ EMRİ İPTAL ET
            // ============================================================
            function cancelWorkOrder(no) {
                Swal.fire({
                    title: 'İş Emrini İptal Et',
                    text: 'Bu siparişin iş emri iptal edilsin mi? Görev Atama sayfasından da silinecektir.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, İptal Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#c0392b',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'İptal ediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    $.ajax({
                        url: apiUrl + '?action=cancelWorkOrder',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ satirNo: no }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'İş Emri İptal Edildi',
	                                    html: escapeHtml(res.message || ''),
                                    confirmButtonColor: '#4b9460'
                                });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ============================================================
            //   TOPLU İŞ EMRİ İPTAL ET
            // ============================================================
            function cancelBulkWorkOrders() {
                if (selectedNos.size === 0) return;
                var selectedOrders = getSelectedOrders();
                var eligibleOrders = selectedOrders.filter(canBulkCancelWorkOrder);
                if (eligibleOrders.length === 0) {
                    warnNoEligibleSelection('Uygun İş Emri Yok', 'Toplu iptal için sadece iş emri verilmiş satırları seçin.');
                    return;
                }
                var nolar = mapOrderNos(eligibleOrders);

                Swal.fire({
                    title: 'Toplu İş Emri İptal',
                    html: '<b>' + nolar.length + '</b> seçili siparişin iş emirleri iptal edilsin mi?<br><small class="text-muted">Görev Atama sayfasından da silinecektir.</small>' + skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, İptal Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#c0392b',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'İptal ediliyor...', html: 'Lütfen bekleyin...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    $.ajax({
                        url: apiUrl + '?action=cancelBulkWorkOrders',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ satirNolar: nolar }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Toplu İptal Tamamlandı',
	                                    html: escapeHtml(res.message || ''),
                                    confirmButtonColor: '#4b9460'
                                });
                                selectedNos.clear();
                                updateSelectedCount();
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ============================================================
            //   TOPLU İŞ EMRİ VER
            // ============================================================
            async function submitBulkWorkOrders() {
                if (selectedNos.size === 0) return;

                var selectedOrders = getSelectedOrders();
                var eligibleOrders = selectedOrders.filter(canBulkCreateWorkOrder);
                if (eligibleOrders.length === 0) {
                    warnNoEligibleSelection('Uygun Sipariş Yok', 'Toplu iş emri için üretim bekleyen ve eşleşmiş satırları seçin.');
                    return;
                }

	                var nolar = mapOrderNos(eligibleOrders);
	                var firstProductKey = orderProductKey(eligibleOrders[0]);
	                var isSameProduct = firstProductKey !== '' && eligibleOrders.every(function (order) {
	                    return orderProductKey(order) === firstProductKey;
	                });

		                if (isSameProduct) {
		                    var matchedType = eligibleOrders[0].eslesenUrunTur || 'Nihai';
		                    var uName = escapeHtml(displayProductNameForOrder(eligibleOrders[0]));
                            var stokDurum = await chooseWorkOrderStockMode('StokDahil', 'Toplu İş Emri Stok Kararı');
                            if (!stokDurum) return;

			                    Swal.fire({
                        title: 'Seri Üretime Ekleme Yap',
                        html: '<p><b>' + nolar.length + '</b> adet <strong>' + uName + '</strong> siparişi için iş emri oluşturulacak.</p>' +
                              '<p style="font-size:0.9rem; color:#6b5a42;">Üretim bandını bozmamak için stok amacıyla (Özel Üretim) fazladan üretilecek sayı girmek ister misiniz?</p>' +
                              stockChoiceBulkNoticeHtml(eligibleOrders) +
                              skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                        icon: 'question',
                        input: 'number',
                        inputAttributes: {
                            min: 0,
                            step: 1
                        },
                        inputValue: 0,
                        showCancelButton: true,
                        confirmButtonText: 'Üretime Al',
                        cancelButtonText: 'İptal',
                        confirmButtonColor: '#4b9460',
                        cancelButtonColor: '#80694d'
	                    }).then(async function (result) {
		                        if (result.isConfirmed) {
		                            var surplusCount = parseInt(result.value) || 0;
	                                var confirmed = await window.workOrderBomPreviewConfirm({
	                                    mode: 'orders',
	                                    satirNolar: nolar,
	                                    surplus: surplusCount,
	                                    tur: matchedType || 'Nihai',
                                        stokDurum: stokDurum
	                                }, {
                                    title: 'Seri Üretime Ekleme Yap',
                                    html: '<p><b>' + nolar.length + '</b> adet <strong>' + uName + '</strong> siparişi için iş emri oluşturulacak.</p>' +
                                        (surplusCount > 0 ? '<p><b>' + surplusCount + '</b> adet özel stok üretimi de eklenecek.</p>' : '') +
                                        stockChoiceBulkNoticeHtml(eligibleOrders) +
                                        skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                                    confirmButtonText: 'Üretime Al',
                                    previewConfirmText: 'Onayla ve üretime al',
                                    cancelButtonText: 'Vazgeç',
                                    confirmButtonColor: '#4b9460',
                                    cancelButtonColor: '#80694d'
	                                });
	                                if (confirmed) {
		                                sendBulkWorkOrderRequest(nolar, surplusCount, matchedType, stokDurum);
	                                }
	                        }
	                    });
	                } else {
                    var stokDurum = await chooseWorkOrderStockMode('StokDahil', 'Toplu İş Emri Stok Kararı');
                    if (!stokDurum) return;
                    var confirmed = await window.workOrderBomPreviewConfirm({
                        mode: 'orders',
                        satirNolar: nolar,
                        surplus: 0,
                        tur: 'Nihai',
                        stokDurum: stokDurum
                    }, {
                        title: 'Toplu Üretime Al',
                        html: '<b>' + nolar.length + '</b> sipariş satırı için iş emri oluşturulacak.<br><br><small style="color:#e67e22;"><i class="bi bi-exclamation-triangle"></i> Farklı ürünler seçtiğiniz için stok ilavesi sorulmuyor.</small>' + stockChoiceBulkNoticeHtml(eligibleOrders) + skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                        confirmButtonText: 'Üretime Al',
                        previewConfirmText: 'Onayla ve üretime al',
                        cancelButtonText: 'Vazgeç',
                        confirmButtonColor: '#4b9460',
                        cancelButtonColor: '#80694d'
                    });
                    if (confirmed) {
                        sendBulkWorkOrderRequest(nolar, 0, 'Nihai', stokDurum);
                    }
		                }
		            }

		            function sendBulkWorkOrderRequest(nolar, surplusAmount, matchedType, stokDurum) {
                        stokDurum = stokDurum === 'StokHaric' ? 'StokHaric' : 'StokDahil';
		                var loadingText = surplusAmount > 0
	                    ? nolar.length + ' sipariş ve ' + surplusAmount + ' özel stok üretimi işleniyor...'
	                    : nolar.length + ' satır işleniyor...';

	                Swal.fire({
	                    title: 'İş Emirleri Oluşturuluyor...',
	                    html: loadingText,
	                    allowOutsideClick: false,
	                    didOpen: function () { Swal.showLoading(); }
	                });

                $.ajax({
	                    url: apiUrl + '?action=createOrderWorkOrders',
	                    type: 'POST',
	                    contentType: 'application/json',
		                    data: JSON.stringify({ satirNolar: nolar, surplus: surplusAmount, tur: matchedType || 'Nihai', stokDurum: stokDurum }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var errHtml = '';
                            if (res.errors && res.errors.length > 0) {
                                errHtml = '<br><br><details><summary>Hatalar (' + res.errors.length + ')</summary><ul>' +
                                    res.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('') + '</ul></details>';
                            }
                            var createdAny = (res.created || 0) > 0 || (res.surplusCreated || 0) > 0;

                            Swal.fire({
                                icon: createdAny ? 'success' : 'warning',
                                title: createdAny ? 'İş Emirleri Oluşturuldu!' : 'İş Emri Oluşturulmadı',
                                html: '<b>' + res.created + '</b> iş emri oluşturuldu.' +
                                    (res.surplusCreated > 0 ? '<br><span style="color:#8e44ad; font-weight:bold;">+' + res.surplusCreated + ' adet Özel Üretim (Stok) eklendi!</span>' : '') +
                                    (res.failed > 0 ? '<br><span class="text-danger">' + res.failed + ' hata</span>' : '') +
                                    (res.skipped > 0 ? '<br><span class="text-muted">' + res.skipped + ' atlandı (zaten verilmiş)</span>' : '') +
                                    errHtml,
                                confirmButtonColor: '#4b9460'
                            });

                            selectedNos.clear();
                            loadOrders();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                    }
                });
            }

            // ============================================================
            //   YARDIMCI
            // ============================================================
            function getUrgencyClass(kargoDateStr) {
                if (!kargoDateStr) return '';
                var parts = kargoDateStr.split(' ');
                var dateParts = parts[0].split('.');
                if (dateParts.length < 3) return '';
                var kargoDate = new Date(
                    parseInt(dateParts[2]),
                    parseInt(dateParts[1]) - 1,
                    parseInt(dateParts[0])
                );
                var now = new Date();
                var diffDays = Math.ceil((kargoDate - now) / (1000 * 60 * 60 * 24));
                if (diffDays <= 1) return 'urgency-critical';
                if (diffDays <= 3) return 'urgency-warning';
                return '';
            }

            // ============================================================
            //   SET ACCORDION TOGGLE
            // ============================================================
            function toggleSetChildren(parentNo) {
                var childRows = document.querySelectorAll('tr.set-child-row[data-parent="' + parentNo + '"]');
                var isOpen = false;
                childRows.forEach(function (row) {
                    row.classList.toggle('show');
                    if (row.classList.contains('show')) isOpen = true;
                });

	                if (!isOpen) {
	                    childRows.forEach(function (row) {
	                        var cb = row.querySelector('.row-cb');
	                        if (!cb) return;
	                        selectedNos.delete(parseInt(cb.getAttribute('data-no')));
	                        applyRowSelectionState(cb, false);
	                    });
	                }
	                syncMasterCheckbox();
	                updateSelectedCount();
	            }

            // ============================================================
            //   İŞ EMRİ HOVER TAKİBİ
            // ============================================================
            function setupWorkOrderPopover() {
                var $body = $('#ordersBody');
                var $popover = $('#workOrderPopover');
                var triggerSelector = '.work-order-trigger, .production-trigger';

                $body.on('mouseenter focusin', triggerSelector, function (e) {
                    showContextPopover(this, e);
                });

                $body.on('mouseover', triggerSelector, function (e) {
                    if (activeWorkOrderRow !== this) {
                        showContextPopover(this, e);
                    } else {
                        positionWorkOrderPopover(e, this);
                    }
                });

                $body.on('mousemove', triggerSelector, function (e) {
                    if (activeWorkOrderRow === this) {
                        positionWorkOrderPopover(e, this);
                    }
                });

                $body.on('click', triggerSelector, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showContextPopover(this, e);
                });

                $body.on('mouseleave focusout', triggerSelector, function () {
                    scheduleHideWorkOrderPopover();
                });

                $popover.on('mouseenter', function () {
                    if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);
                });

                $popover.on('mouseleave', function () {
                    scheduleHideWorkOrderPopover();
                });
            }

            function showContextPopover(trigger, event) {
                if (trigger.classList.contains('production-trigger')) {
                    showProductionPopover(trigger, event);
                } else {
                    showWorkOrderPopover(trigger, event);
                }
            }

            function showWorkOrderPopover(trigger, event) {
                var satirNo = parseInt(trigger.getAttribute('data-no'), 10);
                if (!satirNo) return;

                activeWorkOrderRow = trigger;
                if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);

                var popover = document.getElementById('workOrderPopover');
                popover.innerHTML = renderWorkOrderLoading();
                popover.classList.add('visible');
                popover.setAttribute('aria-hidden', 'false');
                positionWorkOrderPopover(event, trigger);

                if (workOrderPipelineCache[satirNo]) {
                    popover.innerHTML = renderWorkOrderPopover(workOrderPipelineCache[satirNo]);
                    positionWorkOrderPopover(event, trigger);
                    return;
                }

                $.ajax({
                    url: apiUrl + '?action=getOrderPipeline&satirNo=' + satirNo,
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        workOrderPipelineCache[satirNo] = res;
                        if (!activeWorkOrderRow || parseInt(activeWorkOrderRow.getAttribute('data-no'), 10) !== satirNo) return;
                        popover.innerHTML = renderWorkOrderPopover(res);
                        positionWorkOrderPopover(event, trigger);
                    },
                    error: function () {
                        if (!activeWorkOrderRow || parseInt(activeWorkOrderRow.getAttribute('data-no'), 10) !== satirNo) return;
                        popover.innerHTML = '<p class="wo-empty">İş emri detayını şu an okuyamadım. Biraz sonra tekrar deneyin.</p>';
                        positionWorkOrderPopover(event, trigger);
                    }
                });
            }

            function showProductionPopover(trigger, event) {
                var satirNo = parseInt(trigger.getAttribute('data-no'), 10);
                if (!satirNo) return;

                activeWorkOrderRow = trigger;
                if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);

                var popover = document.getElementById('workOrderPopover');
                popover.innerHTML = renderProductionLoading();
                popover.classList.add('visible');
                popover.setAttribute('aria-hidden', 'false');
                positionWorkOrderPopover(event, trigger);

                if (productionDetailCache[satirNo]) {
                    popover.innerHTML = renderProductionPopover(productionDetailCache[satirNo]);
                    positionWorkOrderPopover(event, trigger);
                    return;
                }

                $.ajax({
                    url: apiUrl + '?action=getProductionDetail&satirNo=' + satirNo,
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        productionDetailCache[satirNo] = res;
                        if (!activeWorkOrderRow || activeWorkOrderRow !== trigger) return;
                        popover.innerHTML = renderProductionPopover(res);
                        positionWorkOrderPopover(event, trigger);
                    },
                    error: function () {
                        if (!activeWorkOrderRow || activeWorkOrderRow !== trigger) return;
                        popover.innerHTML = '<p class="wo-empty">Üretim detayını şu an okuyamadım. Biraz sonra tekrar deneyin.</p>';
                        positionWorkOrderPopover(event, trigger);
                    }
                });
            }

            function scheduleHideWorkOrderPopover() {
                if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);
                workOrderPopoverTimer = setTimeout(hideWorkOrderPopover, 140);
            }

            function hideWorkOrderPopover() {
                activeWorkOrderRow = null;
                var popover = document.getElementById('workOrderPopover');
                popover.classList.remove('visible');
                popover.setAttribute('aria-hidden', 'true');
            }

            function positionWorkOrderPopover(event, anchor) {
                var popover = document.getElementById('workOrderPopover');
                if (!popover) return;

                var rect = anchor ? anchor.getBoundingClientRect() : null;
                var left = event && event.clientX ? event.clientX + 12 : (rect ? rect.left : 24);
                var top = event && event.clientY ? event.clientY + 14 : (rect ? rect.top + 8 : 24);

                requestAnimationFrame(function () {
                    var width = popover.offsetWidth || 282;
                    var height = popover.offsetHeight || 260;
                    var margin = 12;

                    if (left + width + margin > window.innerWidth) {
                        left = Math.max(margin, (event && event.clientX ? event.clientX - width - 12 : window.innerWidth - width - margin));
                    }
                    if (top + height + margin > window.innerHeight) {
                        top = Math.max(margin, window.innerHeight - height - margin);
                    }

                    popover.style.left = Math.max(margin, left) + 'px';
                    popover.style.top = Math.max(margin, top) + 'px';
                });
            }

            function renderWorkOrderLoading() {
                return '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">İş emri takibi</span><div class="wo-title">Aşamalar okunuyor...</div></div>' +
                    '<span class="wo-percent">...</span>' +
                    '</div>' +
                    '<div class="wo-progress"><span style="width:35%;"></span></div>' +
                    '<p class="wo-empty">Siparişin üretimde nerede olduğunu kontrol ediyorum.</p>';
            }

            function renderProductionLoading() {
                return '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">Üretim detayı</span><div class="wo-title">Üretim kayıtları okunuyor...</div></div>' +
                    '<span class="wo-percent">...</span>' +
                    '</div>' +
                    '<div class="wo-progress"><span style="width:35%;"></span></div>' +
                    '<p class="wo-empty">Ürün için açık havuz ve personel görevleri kontrol ediliyor.</p>';
            }

            function renderProductionPopover(res) {
                if (!res || !res.success) {
                    return '<p class="wo-empty">' + escapeHtml((res && res.message) || 'Üretim detayı bulunamadı.') + '</p>';
                }

                var order = res.order || {};
                var summary = res.summary || {};
                var stages = res.stages || [];
                var related = res.relatedProductions || [];
                var stageTotal = formatProductionNumber(summary.total);
                var capacityTotal = formatProductionNumber(summary.specialTotal);
                var total = capacityTotal > 0 ? capacityTotal : stageTotal;
                var title = order.sistemUrunAdi || order.urunAdi || 'Ürün';
                var subtitle = (order.siparisNo || '') + (summary.requested ? ' · Bu sipariş ' + formatProductionNumber(summary.requested) + ' adet' : '');

                var html = '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">Üretim detayı</span>' +
                    '<div class="wo-title">' + escapeHtml(title) + '</div>' +
                    '<span class="cell-muted">' + escapeHtml(subtitle) + '</span></div>' +
                    '<span class="wo-percent">🔨 ' + total + '</span>' +
                    '</div>' +
                    '<div class="production-metrics">' +
                    '<div class="production-metric"><span>Toplam</span><strong>' + total + '</strong></div>' +
                    '<div class="production-metric"><span>Havuz</span><strong>' + formatProductionNumber(summary.waiting) + '</strong></div>' +
                    '<div class="production-metric"><span>Aktif</span><strong>' + formatProductionNumber(summary.active) + '</strong></div>' +
                    '<div class="production-metric"><span>Müsait</span><strong>' + formatProductionNumber(summary.available) + '</strong></div>' +
                    '</div>';

                if (stages.length) {
                    var visibleStages = stages.slice(0, 5);
                    html += '<div class="production-stage-list">';
                    visibleStages.forEach(function (stage) {
                        var parts = [];
                        var active = formatProductionNumber(stage.active);
                        var ready = formatProductionNumber(stage.ready);
                        var waiting = formatProductionNumber(stage.waiting);
                        var personnel = stage.personnel || [];
                        if (active > 0) parts.push(active + ' üretimde');
                        if (ready > 0) parts.push(ready + ' hazır');
                        if (waiting > 0) parts.push(stage.detail || (waiting + ' havuzda'));
                        if (personnel.length) parts.push(personnel.slice(0, 2).join(', '));
                        var stageClass = stage.status === 'active' ? 'active' : 'waiting';

                        html += '<div class="production-stage ' + stageClass + '">' +
                            '<span><strong>' + escapeHtml(stage.bolumAdi || '-') + '</strong>' +
                            '<small>' + escapeHtml(parts.join(' · ') || 'Açık kayıt var') + '</small></span>' +
                            '<span class="production-stage-count">' + formatProductionNumber(stage.total) + ' adet</span>' +
                            '</div>';
                    });
                    if (stages.length > visibleStages.length) {
                        html += '<div class="wo-more">+' + (stages.length - visibleStages.length) + ' aşama daha</div>';
                    }
                    html += '</div>';
                } else {
                    html += '<p class="wo-empty">Bu ürün için açık üretim aşaması görünmüyor.</p>';
                }

                if (related.length) {
                    html += '<div class="production-related"><span class="wo-eyebrow">İlgili üretimler</span>';
                    related.slice(0, 3).forEach(function (item) {
                        html += '<div class="production-related-row">' +
                            '<span><strong>' + escapeHtml(item.siparisNo || ('#' + item.no)) + '</strong>' +
                            escapeHtml((item.durum || '-') + (item.isEmriTarihi ? ' · ' + item.isEmriTarihi : '')) + '</span>' +
                            '<span>' + formatProductionNumber(item.available) + '/' + formatProductionNumber(item.total) + '</span>' +
                            '</div>';
                    });
                    if (related.length > 3) {
                        html += '<div class="wo-more">+' + (related.length - 3) + ' kayıt daha</div>';
                    }
                    html += '</div>';
                }

                if (summary.lastUpdated) {
                    html += '<div class="wo-note">Son güncelleme: ' + escapeHtml(summary.lastUpdated) + '</div>';
                }

                return html;
            }

            function formatProductionNumber(value) {
                var number = parseInt(value || 0, 10);
                return isNaN(number) ? 0 : number;
            }

            function safeNumber(value) {
                return formatProductionNumber(value);
            }

            function renderProductionCell(order, openProduction, availableProduction, capacityTotal) {
                if (!order || order.setMi) {
                    return '<span class="metric-value"><span class="metric-muted">—</span></span>';
                }

                openProduction = safeNumber(openProduction);
                availableProduction = safeNumber(availableProduction);
                capacityTotal = safeNumber(capacityTotal);
                var reservedProduction = safeNumber(order.uretimRezerveAdet);
                var totalProduction = capacityTotal > 0 ? capacityTotal : openProduction;

                if (totalProduction <= 0 && availableProduction <= 0) {
                    return '<span class="metric-value"><span class="metric-muted">—</span></span>';
                }

                var orderNo = safeNumber(order.no);
                var triggerAttrs = orderNo > 0
                    ? ' production-trigger" data-no="' + orderNo + '" tabindex="0" role="button"'
                    : '"';
                var totalTitle = capacityTotal > 0
                    ? 'GİED üretim toplam miktarı: ' + capacityTotal + ' adet'
                    : 'Açık üretimde görünen miktar: ' + openProduction + ' adet';
                var availableTitle = capacityTotal > 0
                    ? 'GİED için müsait miktar: ' + availableProduction + ' adet (Toplam ' + capacityTotal + ' - rezerve ' + reservedProduction + ')'
                    : 'GİED için müsait miktar: ' + availableProduction + ' adet';
                var totalHtml = totalProduction > 0
                    ? '<span class="badge-uretimde' + triggerAttrs + ' aria-label="Üretim detayını göster" title="' + escapeHtmlAttr(totalTitle) + '">🔨 ' + totalProduction + '</span>'
                    : '<span class="metric-muted">—</span>';
                var availableClass = availableProduction > 0 ? 'production-available' : 'production-available empty';
                var availableHtml = '<span class="' + availableClass + (orderNo > 0 ? ' production-trigger' : '') + '" data-no="' + orderNo + '" tabindex="0" role="button" aria-label="Üretim müsait detayını göster" title="' + escapeHtmlAttr(availableTitle) + '">Müsait ' + availableProduction + '</span>';

                return '<span class="metric-value production-cell">' + totalHtml + availableHtml + '</span>';
            }

            function renderWorkOrderPopover(res) {
                if (!res || !res.success) {
                    return '<p class="wo-empty">' + escapeHtml((res && res.message) || 'İş emri detayı bulunamadı.') + '</p>';
                }

                var order = res.order || {};
                var progress = res.progress || {};
                var steps = res.pipelineSteps || [];
                var yuzde = Math.max(0, Math.min(100, parseInt(progress.yuzde || 0, 10)));
                var summary = buildWorkOrderSummary(steps, progress);
                var allVisibleSteps = getVisibleWorkOrderSteps(steps);
                var visibleSteps = allVisibleSteps.slice(0, 4);
                var hiddenStepCount = Math.max(0, allVisibleSteps.length - visibleSteps.length);

                var html = '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">İş emri takibi</span>' +
                    '<div class="wo-title">' + escapeHtml(summary.title) + '</div>' +
                    '<span class="cell-muted">' + escapeHtml(order.siparisNo || '') + '</span></div>' +
                    '<span class="wo-percent">%' + yuzde + '</span>' +
                    '</div>' +
                    '<div class="wo-progress"><span style="width:' + yuzde + '%;"></span></div>' +
                    '<p class="wo-summary">' + escapeHtml(summary.text) + '</p>' +
                    '<div class="wo-next"><span>Sonraki durak</span><strong>' + escapeHtml(summary.nextText) + '</strong></div>';

                if (!visibleSteps.length) {
                    html += '<p class="wo-empty">Bu iş emri için henüz ayrıntılı aşama kaydı görünmüyor. İş emri açık, ama sistemde bölüm izi başlamamış olabilir.</p>';
                } else {
                    html += '<div class="wo-step-list">';
                    visibleSteps.forEach(function (item) {
                        var step = item.step;
                        var isNext = item.isNext;
                        var stepClass = getWorkOrderStepClass(step.status, isNext);
                        var tag = getWorkOrderStepTag(step.status, isNext);

                        html += '<div class="wo-step ' + stepClass + '">' +
                            '<span class="wo-step-dot"></span>' +
                            renderWorkOrderStepMain(step) +
                            '<span class="wo-step-tag">' + tag + '</span>' +
                            '</div>';
                    });
                    if (hiddenStepCount > 0) {
                        html += '<div class="wo-more">+' + hiddenStepCount + ' adım daha</div>';
                    }
                    html += '</div>';
                }

                if (progress.gecenSure) {
                    html += '<div class="wo-note">İş emri verileli: ' + escapeHtml(progress.gecenSure) + ' geçti.</div>';
                }
                if (res.allocationNote) {
                    html += '<div class="wo-note">' + escapeHtml(res.allocationNote) + '</div>';
                }

                return html;
            }

            function renderWorkOrderStepMain(step) {
                step = step || {};
                var tooltip = (step.tooltipLines && step.tooltipLines.length)
                    ? ' title="' + escapeHtmlAttr(step.tooltipLines.join('\n')) + '"'
                    : '';

                return '<span class="wo-step-main"' + tooltip + '><strong>' +
                    '<span class="wo-step-dept">' + escapeHtml(step.bolumAdi || '-') + '</span>' +
                    renderWorkOrderStepPersonnel(step) +
                    '</strong></span>';
            }

            function renderWorkOrderStepPersonnel(step) {
                step = step || {};
                var personel = String((step && step.personelAd) || '').trim();
                if (personel !== '') {
                    return '<small class="wo-step-personnel" title="' + escapeHtmlAttr(personel) + '">' + escapeHtml(personel) + '</small>';
                }

                var status = String((step && step.status) || '');
                if (status === 'bekliyor') {
                    return '<small class="wo-step-personnel muted" title="Henüz personele atanmadı">Havuzda</small>';
                }

                return '';
            }

            function buildWorkOrderSummary(steps, progress) {
                var allSteps = steps || [];
                var meaningfulSteps = allSteps.filter(function (step) {
                    return step && step.status && step.status !== 'baslanmadi';
                });
                var completedCount = meaningfulSteps.filter(function (step) { return step.status === 'tamamlandi'; }).length;
                var totalCount = meaningfulSteps.length || ((progress.bitenGorevSayisi || 0) + (progress.aktifGorevSayisi || 0));
                var currentIndex = findCurrentWorkOrderStepIndex(allSteps);
                var currentStep = currentIndex >= 0 ? allSteps[currentIndex] : null;
                var currentStatus = currentStep ? currentStep.status : '';
                var nextStep = findNextWorkOrderStep(allSteps, currentIndex);
                var title = 'İş emri açık';
                var text = 'Aşama kaydı henüz başlamamış görünüyor.';

                if (currentStep) {
                    title = currentStatus === 'uretimde'
                        ? 'Şu an ' + (currentStep.bolumAdi || 'üretim') + ' aşamasında'
                        : (currentStatus === 'hazir'
                            ? (currentStep.bolumAdi || 'Sıradaki bölüm') + ' üretime hazır'
                            : (currentStep.bolumAdi || 'Sıradaki bölüm') + ' sırasını bekliyor');
                    text = (totalCount ? completedCount + '/' + totalCount + ' adım bitti. ' : '') +
                        (currentStatus === 'uretimde'
                            ? 'Şu anda üretimde.'
                            : (currentStatus === 'hazir'
                                ? 'Personelin üretime alması bekleniyor.'
                                : 'Üretime alınmayı bekliyor.'));
                } else if (totalCount > 0 && completedCount >= totalCount) {
                    title = 'Üretim yolu tamamlanmış görünüyor';
                    text = totalCount + '/' + totalCount + ' adım bitti. Sistem birazdan kapatabilir.';
                } else if (progress.aktifBolumler) {
                    text = 'Açık bölüm: ' + progress.aktifBolumler + '.';
                }

                return {
                    title: title,
                    text: text,
                    nextText: nextStep ? (nextStep.bolumAdi || 'Bir sonraki bölüm') : 'Sonraki adım yok'
                };
            }

            function isOpenWorkOrderStep(step) {
                return step && (step.status === 'uretimde' || step.status === 'hazir' || step.status === 'bekliyor');
            }

            function findCurrentWorkOrderStepIndex(steps) {
                return (steps || []).findIndex(isOpenWorkOrderStep);
            }

            function findNextWorkOrderStep(steps, currentIndex) {
                if (!steps || !steps.length) return null;

                if (currentIndex >= 0) {
                    for (var i = currentIndex + 1; i < steps.length; i++) {
                        if (steps[i].status === 'uretimde' || steps[i].status === 'hazir' || steps[i].status === 'bekliyor') return steps[i];
                    }

                    for (var j = currentIndex + 1; j < steps.length; j++) {
                        if (steps[j].status === 'baslanmadi') return steps[j];
                    }

                    return null;
                }

                return steps.find(function (step) {
                    return step.status === 'hazir' || step.status === 'bekliyor' || step.status === 'baslanmadi';
                }) || null;
            }

            function getVisibleWorkOrderSteps(steps) {
                var allSteps = steps || [];
                var currentIndex = findCurrentWorkOrderStepIndex(allSteps);

                var nextStep = findNextWorkOrderStep(allSteps, currentIndex);
                var nextIndex = nextStep ? allSteps.indexOf(nextStep) : -1;

                return allSteps.map(function (step, index) {
                    return { step: step, isNext: index === nextIndex };
                }).filter(function (item) {
                    return item.step && item.step.bolumAdi && (item.step.status !== 'baslanmadi' || item.isNext);
                });
            }

            function getWorkOrderStepClass(status, isNext) {
                if (status === 'tamamlandi') return 'done';
                if (status === 'uretimde') return 'now';
                if (status === 'hazir') return 'waiting';
                if (status === 'bekliyor') return 'waiting';
                return isNext ? 'next' : '';
            }

            function getWorkOrderStepTag(status, isNext) {
                if (status === 'tamamlandi') return 'Bitti';
                if (status === 'uretimde') return 'Şimdi';
                if (status === 'hazir') return 'Hazır';
                if (status === 'bekliyor') return isNext ? 'Sırada' : 'Bekliyor';
                return isNext ? 'Sonra' : 'Bekliyor';
            }

            function renderDateCellHtml(value) {
                if (!value) return '<span class="date-cell empty">—</span>';
                var text = String(value);
                var parts = text.split(' ');
                var datePart = parts[0] || text;
                var timePart = parts.slice(1).join(' ');

                return '<span class="date-cell" title="' + escapeHtmlAttr(text) + '">' +
                    '<span class="date-main">' + escapeHtml(datePart) + '</span>' +
                    (timePart ? '<span class="date-time">' + escapeHtml(timePart) + '</span>' : '') +
                    '</span>';
            }

	            function escapeHtml(text) {
	                if (text === null || typeof text === 'undefined') return '';
	                var div = document.createElement('div');
	                div.textContent = String(text);
	                return div.innerHTML;
	            }

	            function escapeHtmlAttr(text) {
	                if (text === null || typeof text === 'undefined') return '';
	                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;');
            }

            // ============================================================
            //   HERŞEYİ SIFIRLA
            // ============================================================
            function clearAllOrders() {
                Swal.fire({
                    title: 'Tüm Siparişleri Sil?',
                    html: '<p style="color:#856404; font-size:0.95rem;">Bu işlem <strong>tüm sipariş kayıtlarını kalıcı olarak silecektir</strong>.</p>' +
                        '<p style="color:#dc3545; font-weight:600;">Bu işlem geri alınamaz!</p>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#c0392b',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Evet, Herşeyi Sil',
                    cancelButtonText: 'Vazgeç'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        // İkinci onay - kullanıcıdan SIFIRLA yazmasını iste
                        Swal.fire({
                            title: 'Son Onay',
                            html: '<p>Devam etmek için aşağıya <strong>SIFIRLA</strong> yazın:</p>',
                            input: 'text',
                            inputPlaceholder: 'SIFIRLA',
                            icon: 'error',
                            showCancelButton: true,
                            confirmButtonColor: '#c0392b',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sil ve Sıfırla',
                            cancelButtonText: 'Vazgeç',
                            inputValidator: function (value) {
                                if (value !== 'SIFIRLA') {
                                    return 'Lütfen SIFIRLA yazın!';
                                }
                            }
                        }).then(function (result2) {
                            if (result2.isConfirmed) {
                                executeClearAll();
                            }
                        });
                    }
                });
            }

            function executeClearAll() {
                Swal.fire({
                    title: 'Siliniyor...',
                    text: 'Tüm sipariş kayıtları temizleniyor...',
                    allowOutsideClick: false,
                    didOpen: function () { Swal.showLoading(); }
                });

                fetch(apiUrl + '?action=clearAllOrders', { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Sıfırlandı!',
                                text: res.message,
                                confirmButtonColor: '#4b9460'
                            }).then(function () {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Hata', res.message, 'error');
                        }
                    })
                    .catch(function (err) {
                        console.error(err);
                        Swal.fire('Hata', 'Sunucu ile iletişim hatası.', 'error');
                    });
            }

            // ============================================================
            //   STOKTAN KARŞILA — TEKLİ
            // ============================================================
            function deductStock(e, no) {
                if (e && e.preventDefault) e.preventDefault();
                Swal.fire({
                    title: 'Stoktan Karşıla',
                    text: 'Bu işlem siparişi stoktan kapatır; üretim havuzu veya personel görevi oluşturmaz.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Stoktan Karşıla',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#2196F3',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Stoktan karşılanıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=deductStock',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ no: no }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Stoktan Karşılandı!', text: res.message, confirmButtonColor: '#2196F3' });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ============================================================
            //   STOKTAN DÜŞ — GERİ AL
            // ============================================================
            function undoDeductStock(e, no) {
                if (e && e.preventDefault) e.preventDefault();
                Swal.fire({
                    title: 'Stoktan Karşılamayı Geri Al',
                    text: 'Bu siparişin stok çıkışı geri alınsın mı? Stok iade edilecek ve sipariş tekrar bekleyen duruma dönecek.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Geri Al',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#64748b',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Geri alınıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=undoDeductStock',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ no: no }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Geri Alındı', text: res.message, confirmButtonColor: '#4b9460' });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ============================================================
            //   STOKTAN DÜŞ — TOPLU
            // ============================================================
            function deductStockBulk(e) {
                if (e && e.preventDefault) e.preventDefault();
                if (selectedNos.size === 0) return;
                var selectedOrders = getSelectedOrders();
                var eligibleOrders = selectedOrders.filter(canBulkDeductStock);
                if (eligibleOrders.length === 0) {
                    warnNoEligibleSelection('Uygun Stok Yok', 'Stoktan karşılamak için yeterli stoğu olan üretim bekleyen veya üretime bağlı satırları seçin.');
                    return;
                }
                var nosArr = mapOrderNos(eligibleOrders);
                Swal.fire({
                    title: 'Toplu Stoktan Karşıla',
                    html: '<b>' + nosArr.length + '</b> sipariş stoktan kapatılacak; üretim havuzu veya personel görevi oluşturulmayacak.' + skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Stoktan Karşıla',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#2196F3',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Stoktan karşılanıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=deductStockBulk',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ nos: nosArr }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
	                                var msg = escapeHtml(res.message || '');
	                                if (res.hatalar && res.hatalar.length > 0) {
	                                    msg += '<br><br><small style="color:#856404;">' + res.hatalar.map(function (h) { return escapeHtml(h); }).join('<br>') + '</small>';
	                                }
                                Swal.fire({ icon: 'success', title: 'Stoktan Karşılandı!', html: msg, confirmButtonColor: '#2196F3' });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ============================================================
            //   GİED — TOPLU BAĞLA
            // ============================================================
            function linkBulkWipAllocations(e) {
                if (e && e.preventDefault) e.preventDefault();
                if (selectedNos.size === 0) return;

                var selectedOrders = getSelectedOrders();
                var eligibleOrders = selectedOrders.filter(canBulkLinkWip);
                if (eligibleOrders.length === 0) {
                    warnNoEligibleSelection('Uygun GİED Satırı Yok', 'Toplu GİED için üretim bekleyen ve eşleşmiş satırları seçin.');
                    return;
                }

                var nosArr = mapOrderNos(eligibleOrders);
                Swal.fire({
                    title: 'Toplu GİED Yap',
                    html: '<b>' + nosArr.length + '</b> sipariş uygun boş kapasitesi olan özel/stok üretim kayıtlarına bağlanacak.' +
                        '<br><small class="text-muted">Yeterli üretim kapasitesi bulunamayan satırlar atlanır ve sonuçta gösterilir.</small>' +
                        skippedSelectionHtml(selectedOrders.length, eligibleOrders.length),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'GİED Yap',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#8e44ad',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'GİED yapılıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=linkOrdersToSpecialProductionBulk',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ satirNolar: nosArr }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                var msg = escapeHtml(res.message || '');
                                if (res.hatalar && res.hatalar.length > 0) {
                                    msg += '<br><br><small style="color:#856404;">' + res.hatalar.map(function (h) { return escapeHtml(h); }).join('<br>') + '</small>';
                                }
                                Swal.fire({ icon: 'success', title: 'GİED Tamamlandı!', html: msg, confirmButtonColor: '#8e44ad' });
                                selectedNos.clear();
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                        }
                    });
                });
            }

            // ==========================================
            // Oto Eşleştir — Eşleşmemiş siparişleri tekrar eşleştir
            // ==========================================
            function rematchOrders() {
                Swal.fire({
                    icon: 'question',
                    title: 'Oto Eşleştir',
                    html: 'Bekleyen siparişlerde önce set tanımları, sonra ürün veritabanı kontrol edilecek.<br><br>Devam etmek istiyor musunuz?',
                    showCancelButton: true,
                    confirmButtonText: '✅ Evet, Eşleştir',
                    cancelButtonText: '❌ İptal',
                    confirmButtonColor: '#8e44ad',
                    cancelButtonColor: '#888',
                    reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Eşleştiriliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                        $.ajax({
                            url: apiUrl + '?action=rematchOrders',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({}),
                            dataType: 'json',
                            success: function (res) {
                                if (res.success) {
                                    var lines = [
                                        '<b>' + res.matched + '</b> sipariş yeni eşleştirildi.',
                                        '<b>' + res.total + '</b> bekleyen sipariş kontrol edildi.'
                                    ];
                                    if (Number(res.repairedSets || 0) > 0) {
                                        lines.push('<b>' + res.repairedSets + '</b> SET satırının bileşenleri listelendi.');
                                    }
                                    if (Number(res.clearedSetLikeMatches || 0) > 0) {
                                        lines.push('<b>' + res.clearedSetLikeMatches + '</b> hatalı set/ürün eşleşmesi iptal edildi.');
                                    }
                                    if (Number(res.skippedSetLike || 0) > 0) {
                                        lines.push('<small>Set gibi görünen bazı satırlar tek ürüne otomatik bağlanmadı.</small>');
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Eşleştirme Tamamlandı!',
                                        html: lines.join('<br>'),
                                        confirmButtonColor: '#8e44ad'
                                    });
                                    loadOrders();
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                                }
                            },
                            error: function (xhr) {
                                Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d4af37' });
                            }
                        });
                    }
                });
            }
            // ==========================================
            // İş Emrinden Düş (WIP / Üretim Rezervasyon)
            // ==========================================
            function showWipAllocationDialog(no) {
                var order = allOrders.find(function (o) { return o.no == no; });
                if (!order) return;

                Swal.fire({ title: 'Uygun Üretimler Aranıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                $.ajax({
                    url: apiUrl + '?action=getAvailableSpecialProductions',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ 
                        eslesenUrunNo: order.eslesenUrunNo, 
                        eslesenUrunTur: order.eslesenUrunTur || 'Urun', 
                        adet: order.adet 
                    }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            if (!res.data || res.data.length === 0) {
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Uygun Üretim Bulunamadı',
                                    text: 'Bu ürün için halihazırda üretim bandında (Özel/Stok) yeterli kotası olan bir kayıt bulunmuyor.',
                                    confirmButtonColor: '#8e44ad'
                                });
                                return;
                            }

                            var splitRequired = !!res.splitRequired;
                            var requested = parseInt(res.requested || order.adet || 0, 10) || 0;
                            var totalAvailable = parseInt(res.totalAvailable || 0, 10) || 0;

                            // Tablo HTML oluştur
                            var html = '<div style="text-align:left; font-size:0.9rem; margin-top:10px;">';
                            html += '<p><strong>Sipariş No:</strong> ' + escapeHtml(order.siparisNo) + ' | <strong>Adet:</strong> ' + order.adet + '</p>';
                            if (splitRequired) {
                                html += '<p style="margin:6px 0 10px; color:#6d28d9; font-weight:700;">Bu sipariş tek üretimde kapanmıyor; toplam ' + totalAvailable + ' müsait adet içinden ' + requested + ' adet paylaştırılacak.</p>';
                            }
                            html += '<table class="wip-table" style="width:100%; border-collapse:collapse; margin-top:10px;">';
                            html += '<thead style="background:#f1f1f1;"><tr>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Seç</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">İş Emri Tarihi</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Toplam</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Boşta Kalan</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Ayrılacak</th>' +
                                    '</tr></thead><tbody>';

                            res.data.forEach(function (w, idx) {
                                var wNo = w.no || w.No || 0;
                                var wDate = w.isEmriTarihi || w.IsEmriTarihi || '-';
                                var wTotal = (w.toplamAdet !== undefined) ? w.toplamAdet : ((w.ToplamAdet !== undefined) ? w.ToplamAdet : 0);
                                var wBosta = (w.bostaAdet !== undefined) ? w.bostaAdet : ((w.BostaAdet !== undefined) ? w.BostaAdet : 0);
                                var wPlanned = parseInt(w.ayrilacakAdet || 0, 10) || 0;
                                var checked = idx === 0 ? 'checked' : '';
                                var disabled = splitRequired ? 'disabled' : '';
                                html += '<tr>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center;"><input type="radio" name="wipSelected" value="' + wNo + '" id="wip_' + wNo + '" ' + checked + ' ' + disabled + '></td>' +
                                    '<td style="padding:8px; border:1px solid #ccc;"><label style="cursor:pointer;" for="wip_' + wNo + '">' + escapeHtml(wDate) + '</label></td>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center;">' + wTotal + '</td>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center; color:#27ae60; font-weight:bold;">' + wBosta + '</td>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center; color:#6d28d9; font-weight:bold;">' + (wPlanned > 0 ? wPlanned : '-') + '</td>' +
                                    '</tr>';
                            });
                            html += '</tbody></table></div>';

                            Swal.fire({
                                title: 'GİED (Bağla)',
                                html: html,
                                showCancelButton: true,
                                confirmButtonText: 'GİED Uygula',
                                cancelButtonText: 'İptal',
                                confirmButtonColor: '#8e44ad',
                                cancelButtonColor: '#888',
                                preConfirm: function () {
                                    var selWipId = document.querySelector('input[name="wipSelected"]:checked');
                                    if (!selWipId) {
                                        Swal.showValidationMessage('Lütfen bir üretim kaydı seçin.');
                                        return false;
                                    }
                                    return selWipId.value;
                                }
                            }).then(function (result) {
                                if (result.isConfirmed) {
                                    var ozelUretimNo = result.value;
                                    Swal.fire({ title: 'GİED Bağlanıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                                    $.ajax({
                                        url: apiUrl + '?action=linkOrderToSpecialProduction',
                                        type: 'POST',
                                        contentType: 'application/json',
                                        data: JSON.stringify({
                                            siparisNo: order.no,
                                            ozelUretimNo: parseInt(ozelUretimNo)
                                        }),
                                        dataType: 'json',
                                        success: function (res2) {
                                            if (res2.success) {
                                                Swal.fire({ icon: 'success', title: 'Başarılı!', text: res2.message, confirmButtonColor: '#8e44ad' });
                                                loadOrders();
                                            } else {
                                                Swal.fire({ icon: 'error', title: 'Hata', text: res2.message, confirmButtonColor: '#d4af37' });
                                            }
                                        },
                                        error: function (xhr) {
                                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                                        }
                                    });
                                }
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                    }
                });
            }

            // ==========================================
            // GİED (WIP Reservation) İptali
            // ==========================================
            function cancelWipAllocation(no) {
                Swal.fire({
                    title: 'GİED İptal Edilsin mi?',
                    text: 'Siparişi bağladığınız üretim rezervasyonundan (GİED) iptal ederek, durumu tekrar "Üretim Bekliyor"a dönecektir.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#888',
                    confirmButtonText: 'Evet, İptal Et',
                    cancelButtonText: 'Vazgeç'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'İptal İşlemi Sürüyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=cancelWipAllocation',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ siparisNo: no }),
                            dataType: 'json',
                            success: function (res) {
                                if (res.success) {
                                    Swal.fire({ icon: 'success', title: 'İptal Edildi!', text: res.message, confirmButtonColor: '#27ae60' });
                                    loadOrders();
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d4af37' });
                                }
                            },
                            error: function (xhr) {
                                Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d4af37' });
                            }
                        });
                    }
                });
            }
        </script>

@endsection
