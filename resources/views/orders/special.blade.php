@extends('layouts.app')

@section('content')

        <!-- Kütüphaneler -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <style>
            /* ═══════════════════════════════════════
               Özel Üretim — Yeni Modern Tasarım
               ═══════════════════════════════════════ */

            .oz-page { display: flex; flex-direction: column; gap: 16px; }

            /* ── Bilgi Bandı ── */
            .oz-info-banner {
                background: linear-gradient(135deg, #eff6ff, #dbeafe);
                border: 1.5px solid #93c5fd;
                border-radius: 14px;
                padding: 16px 20px;
                display: flex; align-items: center; gap: 14px;
            }
            .oz-info-icon {
                width: 44px; height: 44px; border-radius: 12px;
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                display: flex; align-items: center; justify-content: center;
                font-size: 1.3rem; color: white; flex-shrink: 0;
                box-shadow: 0 4px 12px rgba(99,102,241,0.2);
            }
            .oz-info-text h2 { font-size: 0.92rem; font-weight: 700; margin: 0 0 3px; }
            .oz-info-text p { font-size: 0.78rem; color: var(--z-text-secondary); margin: 0; line-height: 1.5; }

            .oz-primary-actions {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                flex-wrap: wrap;
            }
            .oz-btn-action.oz-btn-secondary {
                background: var(--z-bg-card);
                color: var(--z-text);
                border: 1px solid var(--z-border);
                text-decoration: none;
            }
            .oz-btn-action.oz-btn-secondary:hover {
                border-color: var(--z-accent);
                color: var(--z-accent);
                opacity: 1;
            }

            /* ── Özet Kartları ── */
            .oz-stats-row { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
            .oz-stat-card {
                background: var(--z-bg-card); border: 1.5px solid var(--z-border-light);
                border-radius: 12px; padding: 14px 16px;
                display: flex; align-items: center; gap: 12px;
                transition: all 0.2s ease;
            }
            .oz-stat-card:hover { border-color: var(--z-accent); box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
            .oz-stat-emoji { font-size: 1.3rem; flex-shrink: 0; }
            .oz-stat-info { min-width: 0; }
            .oz-stat-label {
                font-size: 0.62rem; font-weight: 700; color: var(--z-text-muted);
                text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 2px;
            }
            .oz-stat-value { font-size: 1.3rem; font-weight: 800; margin: 0; line-height: 1; }
            .oz-stat-value.bekliyor { color: #d97706; }
            .oz-stat-value.verildi { color: var(--z-success); }
            .oz-stat-value.stok { color: #2563eb; }
            .oz-stat-value.hata { color: var(--z-danger); }

            /* ── Filtre Çubuğu ── */
            .oz-filter-bar {
                background: var(--z-bg-card); border: 1px solid var(--z-border);
                border-radius: 12px; padding: 14px 18px;
                display: none; gap: 10px; flex-wrap: wrap; align-items: flex-end;
            }
            .oz-filter-group { display: flex; flex-direction: column; gap: 3px; flex: 1 1 130px; }
            .oz-filter-group label { font-size: 0.68rem; font-weight: 700; color: var(--z-text-muted); text-transform: uppercase; letter-spacing: 0.04em; }
            .oz-filter-group select, .oz-filter-group input {
                padding: 8px 12px; border: 1.5px solid var(--z-border); border-radius: 8px;
                font-size: 0.82rem; background: var(--z-bg-input); font-family: inherit;
                color: var(--z-text); transition: border-color 0.2s;
            }
            .oz-filter-group select:focus, .oz-filter-group input:focus {
                border-color: var(--z-accent); outline: none; box-shadow: 0 0 0 3px var(--z-accent-soft);
            }
            .oz-filter-actions { display: flex; gap: 6px; align-items: center; }
            .oz-btn-filter {
                background: var(--z-accent); color: #fff; border: none;
                padding: 8px 16px; border-radius: 8px; font-weight: 600;
                cursor: pointer; font-size: 0.82rem; font-family: inherit;
                transition: all 0.15s;
            }
            .oz-btn-filter:hover { opacity: 0.85; }
            .oz-btn-reset {
                background: var(--z-bg-soft); color: var(--z-text-muted); border: 1px solid var(--z-border);
                padding: 8px 14px; border-radius: 8px; font-weight: 600;
                cursor: pointer; font-size: 0.82rem; font-family: inherit;
            }

            /* ── Toplu İşlem Çubuğu ── */
            .oz-toolbar {
                background: var(--z-bg-card); border: 1px solid var(--z-border);
                border-radius: 12px; padding: 12px 18px;
                display: none; align-items: center; gap: 10px; flex-wrap: wrap;
            }
            .oz-selected-info { font-size: 0.82rem; color: var(--z-text-secondary); font-weight: 700; }
            .oz-btn-action {
                padding: 8px 14px; border-radius: 8px; border: none;
                font-weight: 600; cursor: pointer; font-size: 0.78rem;
                font-family: inherit; color: #fff; transition: all 0.15s;
                display: inline-flex; align-items: center; gap: 5px;
                white-space: nowrap;
            }
            .oz-btn-action:hover { opacity: 0.85; }
            .oz-btn-action:disabled { opacity: 0.4; cursor: not-allowed; }
            .oz-btn-action.green { background: var(--z-success); }
            .oz-btn-action.red { background: #dc2626; }
            .oz-btn-action.blue { background: #2563eb; }
            .oz-btn-action.purple {
                background: #7c3aed; font-size: 0.85rem; font-weight: 700;
                animation: pulse-purple 2s infinite;
            }
            .oz-btn-action.purple:hover { opacity: 1; transform: scale(1.02); }

            @keyframes pulse-purple {
                0% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.4); }
                70% { box-shadow: 0 0 0 8px rgba(124, 58, 237, 0); }
                100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0); }
            }

            /* ── Tablo ── */
            .oz-table-wrap {
                display: none; overflow-x: auto;
                background: var(--z-bg-card); border-radius: 12px; border: 1px solid var(--z-border);
            }
            .oz-table-wrap table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
            .oz-table-wrap thead th {
                background: var(--z-bg-soft); color: var(--z-text-muted);
                padding: 10px 8px; text-align: left;
                font-weight: 700; font-size: 0.68rem; letter-spacing: 0.04em; text-transform: uppercase;
                position: sticky; top: 0; z-index: 10; white-space: nowrap;
                border-bottom: 2px solid var(--z-border);
            }
            .oz-table-wrap tbody tr { border-bottom: 1px solid var(--z-border-light); transition: background 0.12s; }
            .oz-table-wrap tbody tr:hover { background: var(--z-bg-soft); }
            .oz-table-wrap tbody tr:last-child { border-bottom: none; }
            .oz-table-wrap tbody tr.selected-row { background: var(--z-accent-soft) !important; }
            .oz-table-wrap tbody tr.is-emri-verildi { background: rgba(16,185,129,0.06); }
            .oz-table-wrap tbody tr.pasif { opacity: 0.45; }
            .oz-table-wrap tbody tr.urgency-critical { background: rgba(220,38,38,0.06) !important; }
            .oz-table-wrap tbody tr.urgency-warning { background: rgba(245,158,11,0.06) !important; }
            .oz-table-wrap tbody tr.set-parent-row { background: rgba(245,158,11,0.06) !important; border-left: 3px solid #f59e0b; }
            .oz-table-wrap tbody tr.set-child-row { background: rgba(139,92,246,0.04) !important; border-left: 3px solid #8b5cf6; display: none; }
            .oz-table-wrap tbody tr.set-child-row.show { display: table-row; }
            .oz-table-wrap td { padding: 10px 8px; vertical-align: middle; color: var(--z-text); }

            /* Badges */
            .oz-badge {
                padding: 4px 10px; border-radius: 6px;
                font-size: 0.7rem; font-weight: 700; display: inline-flex;
                align-items: center; gap: 4px; white-space: nowrap;
            }
            .oz-badge.bekliyor { background: #fef3c7; color: #b45309; }
            .oz-badge.verildi { background: #d1fae5; color: #047857; }
            .oz-badge.pasif { background: var(--z-bg-soft); color: var(--z-text-muted); }
            .oz-badge.stok { background: #dbeafe; color: #1d4ed8; }
            .oz-badge.status-detail-trigger,
            .oz-badge.work-order-trigger {
                cursor: help;
                transition: transform 0.12s ease, box-shadow 0.12s ease;
            }
            .oz-badge.status-detail-trigger:hover,
            .oz-badge.status-detail-trigger:focus,
            .oz-badge.work-order-trigger:hover,
            .oz-badge.work-order-trigger:focus {
                outline: none;
                transform: translateY(-1px);
                box-shadow: 0 0 0 2px rgba(47, 158, 133, 0.16);
            }
            .oz-badge.set-parent {
                background: #fef3c7; color: #b45309;
                cursor: pointer;
            }
            .oz-badge.set-child { background: rgba(139,92,246,0.1); color: #7c3aed; font-size: 0.65rem; }
            .oz-badge.uretimde { background: #fef3c7; color: #b45309; }

            .work-order-popover {
                position: fixed;
                left: 0;
                top: 0;
                z-index: 11020;
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
            .wo-muted {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.66rem;
                font-weight: 700;
                line-height: 1.25;
                margin-top: 2px;
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
                max-width: 118px;
                line-height: 1.15;
                white-space: normal;
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
            .wo-step.done { background: rgba(34, 197, 94, 0.05); }
            .wo-step.now { background: rgba(234, 179, 8, 0.08); }
            .wo-step.waiting { background: rgba(59, 130, 246, 0.06); }
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
            .wo-status-metrics {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 5px;
                margin: 8px 0;
            }
            .wo-status-metric {
                background: var(--z-bg-soft);
                border-radius: 8px;
                padding: 6px 5px;
                min-width: 0;
            }
            .wo-status-metric span {
                display: block;
                color: var(--z-text-muted);
                font-size: 0.56rem;
                font-weight: 800;
                line-height: 1.1;
                text-transform: uppercase;
            }
            .wo-status-metric strong {
                display: block;
                color: var(--z-text);
                font-size: 0.82rem;
                line-height: 1.2;
                margin-top: 2px;
                overflow-wrap: anywhere;
            }

            /* ── Progress Bar (Görevlendirme) ── */
            .oz-progress-wrap { min-width: 90px; cursor: help; outline: none; }
            .oz-progress-wrap:focus-visible {
                box-shadow: 0 0 0 3px rgba(59,130,246,0.16);
                border-radius: 6px;
            }
            .oz-progress-bar { width: 100%; height: 7px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 3px; }
            .oz-progress-bar-inner { height: 100%; display: flex; transition: width 0.4s ease; }
            .oz-progress-seg-personel { background: linear-gradient(90deg, #3b82f6, #6366f1); }
            .oz-progress-seg-havuz { background: linear-gradient(90deg, #f59e0b, #eab308); }
            .oz-progress-label { font-size: 0.62rem; color: var(--z-text-muted); font-weight: 600; line-height: 1.2; }
            .oz-progress-label .pct { font-weight: 800; }
            .oz-progress-label .pct.full { color: var(--z-success); }
            .oz-progress-label .pct.partial { color: #d97706; }
            .oz-progress-label .pct.zero { color: var(--z-text-muted); }
            .oz-progress-chip { font-weight: 800; white-space: nowrap; }
            .oz-progress-chip.personel { color: #6366f1; }
            .oz-progress-chip.havuz { color: #d97706; }
            .oz-progress-na { font-size: 0.68rem; color: var(--z-text-muted); }

            .oz-assignment-popover {
                position: fixed;
                z-index: 11000;
                width: min(290px, calc(100vw - 24px));
                background: #fff;
                border: 1px solid #dbe3ea;
                border-radius: 8px;
                box-shadow: 0 18px 42px rgba(15,23,42,0.16), 0 2px 8px rgba(15,23,42,0.08);
                padding: 12px;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(4px);
                transition: opacity 0.12s ease, transform 0.12s ease, visibility 0.12s ease;
                color: #1f2937;
            }
            .oz-assignment-popover.visible {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0);
            }
            .oz-assignment-head {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 10px;
                padding-bottom: 8px;
                border-bottom: 1px solid #eef2f6;
            }
            .oz-assignment-title {
                margin: 0;
                font-size: 0.78rem;
                font-weight: 800;
                color: #111827;
            }
            .oz-assignment-sub {
                margin-top: 2px;
                font-size: 0.66rem;
                font-weight: 700;
                color: #8a94a3;
            }
            .oz-assignment-status {
                flex: 0 0 auto;
                border-radius: 999px;
                background: #eef6ff;
                color: #2563eb;
                font-size: 0.64rem;
                font-weight: 800;
                padding: 4px 8px;
                white-space: nowrap;
            }
            .oz-assignment-status.warn {
                background: #fff7ed;
                color: #c2410c;
            }
            .oz-assignment-metrics {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 6px;
                margin: 10px 0;
            }
            .oz-assignment-metric {
                border: 1px solid #edf1f5;
                border-radius: 7px;
                padding: 7px 8px;
                background: #f8fafc;
            }
            .oz-assignment-metric span {
                display: block;
                margin-bottom: 2px;
                font-size: 0.61rem;
                font-weight: 800;
                color: #8a94a3;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .oz-assignment-metric strong {
                display: block;
                font-size: 0.9rem;
                line-height: 1;
                color: #111827;
            }
            .oz-assignment-mini-bar {
                display: flex;
                width: 100%;
                height: 7px;
                overflow: hidden;
                border-radius: 999px;
                background: #e5e7eb;
                margin-bottom: 9px;
            }
            .oz-assignment-mini-bar .personel { background: linear-gradient(90deg, #3b82f6, #6366f1); }
            .oz-assignment-mini-bar .havuz { background: linear-gradient(90deg, #f59e0b, #eab308); }
            .oz-assignment-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                padding: 5px 0;
                font-size: 0.72rem;
                font-weight: 700;
                color: #4b5563;
            }
            .oz-assignment-row strong {
                color: #111827;
                font-size: 0.76rem;
            }
            .oz-assignment-dot {
                display: inline-block;
                width: 7px;
                height: 7px;
                border-radius: 999px;
                margin-right: 6px;
                vertical-align: middle;
            }
            .oz-assignment-dot.personel { background: #6366f1; }
            .oz-assignment-dot.havuz { background: #eab308; }
            .oz-assignment-dot.kalan { background: #94a3b8; }
            .oz-assignment-note {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #eef2f6;
                font-size: 0.68rem;
                line-height: 1.45;
                color: #64748b;
            }

            /* ── Filter Pills ── */
            .oz-filter-pills { display: flex; gap: 4px; flex-wrap: wrap; }
            .oz-pill {
                padding: 5px 12px; border-radius: 20px; border: 1.5px solid var(--z-border);
                background: var(--z-bg-input); font-size: 0.72rem; font-weight: 600;
                cursor: pointer; font-family: inherit; color: var(--z-text-muted);
                transition: all 0.15s; white-space: nowrap;
            }
            .oz-pill:hover { border-color: var(--z-accent); color: var(--z-text); }
            .oz-pill.active { background: var(--z-accent); color: #fff; border-color: var(--z-accent); }
            .oz-pill.active:hover { opacity: 0.9; }

            /* Eşleşme */
            .oz-match-ok { color: var(--z-success); font-weight: 700; font-size: 0.76rem; }
            .oz-match-warn { color: #d97706; font-weight: 700; font-size: 0.76rem; }
            .oz-match-fail { color: var(--z-danger); font-weight: 700; font-size: 0.76rem; }

            /* Stok */
            .oz-stok-ok { color: var(--z-success); font-weight: 700; }
            .oz-stok-kismi { color: #d97706; font-weight: 700; }
            .oz-stok-yok { color: var(--z-danger); font-weight: 600; }
            .oz-musait-value {
                display: inline-flex;
                justify-content: center;
                align-items: center;
                min-width: 28px;
                padding: 3px 8px;
                border-radius: 999px;
                font-size: 0.72rem;
                font-weight: 800;
                line-height: 1.2;
                white-space: nowrap;
            }
            .oz-musait-ok { background: #ecfdf5; color: #047857; }
            .oz-musait-empty { background: #f1f5f9; color: #64748b; }
            .oz-btn-stok {
                background: var(--z-accent); color: #fff; border: none;
                padding: 3px 8px; border-radius: 6px; font-size: 0.68rem;
                cursor: pointer; font-weight: 600; margin-top: 3px; display: block; width: 100%;
                font-family: inherit;
            }
            .oz-btn-stok:hover { opacity: 0.85; }

            /* Satır Aksiyonları */
            .oz-btn-row {
                padding: 5px 10px; border-radius: 6px; border: none;
                font-weight: 600; cursor: pointer; font-size: 0.72rem;
                font-family: inherit; color: #fff;
                display: inline-flex; align-items: center; gap: 4px;
                white-space: nowrap; transition: opacity 0.15s;
            }
            .oz-btn-row:hover { opacity: 0.85; }
            .oz-btn-row:disabled { opacity: 0.4; cursor: not-allowed; }
            .oz-btn-row.green { background: var(--z-success); }
            .oz-btn-row.red { background: #dc2626; }
            .oz-btn-row.purple { background: #7c3aed; }
            .oz-btn-row.orange { background: #ea580c; }
            .oz-row-actions { display: inline-flex; flex-direction: column; align-items: stretch; gap: 5px; }
            .oz-row-actions .oz-btn-row { justify-content: center; }
            .oz-stock-addition-badge {
                display: inline-flex; align-items: center; gap: 3px; margin-top: 4px;
                color: #7c3aed; font-size: 0.72rem; font-weight: 700;
            }
            .swal2-popup.oz-stock-modal {
                width: min(720px, calc(100vw - 32px)) !important;
                max-height: calc(100vh - 48px);
                padding: 22px 28px 18px;
                overflow: hidden;
                display: flex !important;
                flex-direction: column;
            }
            .oz-stock-modal .swal2-title {
                flex: 0 0 auto;
                margin: 0 0 14px;
                font-size: clamp(1.5rem, 2.2vw, 2.3rem);
                line-height: 1.12;
            }
            .oz-stock-modal .swal2-html-container {
                flex: 1 1 auto;
                min-height: 0;
                max-height: calc(100vh - 220px);
                overflow-y: auto;
                margin: 0;
                padding: 0 8px 4px;
            }
            .oz-stock-modal .swal2-actions {
                flex: 0 0 auto;
                margin: 14px 0 0;
            }
            .oz-stock-modal .wo-type-card,
            .oz-stock-modal .wo-stock-card {
                padding: 8px 12px !important;
                margin-bottom: 6px !important;
                border-radius: 8px !important;
            }
            .oz-stock-modal .wo-type-card strong,
            .oz-stock-modal .wo-stock-card strong {
                font-size: 0.94rem !important;
            }
            .oz-stock-modal .wo-type-card span,
            .oz-stock-modal .wo-stock-card span {
                font-size: 0.74rem !important;
                line-height: 1.35;
            }
            @media (max-height: 760px) {
                .swal2-popup.oz-stock-modal { padding: 16px 24px 14px; }
                .oz-stock-modal .swal2-title { margin-bottom: 10px; }
                .oz-stock-modal .swal2-html-container { max-height: calc(100vh - 180px); }
                .oz-stock-modal .wo-type-card,
                .oz-stock-modal .wo-stock-card { padding: 7px 10px !important; }
            }

            /* Sort */
            th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 16px !important; }
            th.sortable:hover { background: rgba(0,0,0,0.04); }
            th.sortable::after { content: '⇅'; position: absolute; right: 2px; top: 50%; transform: translateY(-50%); font-size: 0.6rem; opacity: 0.35; }
            th.sortable.sort-asc::after { content: '▲'; opacity: 1; color: var(--z-accent); }
            th.sortable.sort-desc::after { content: '▼'; opacity: 1; color: var(--z-accent); }

            /* Select2 */
            .match-select { width: 100%; font-size: 0.78rem; padding: 4px 6px; border: 1px solid var(--z-border); border-radius: 6px; }
            .select2-container { max-width: 100% !important; width: 100% !important; }
            .select2-container--default .select2-selection--single { border: 1px solid var(--z-border) !important; border-radius: 6px !important; height: 30px !important; font-size: 0.78rem !important; }
            .select2-container--default .select2-selection--single .select2-selection__rendered { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 20px; line-height: 28px !important; }
            .select2-container--default .select2-selection--single .select2-selection__arrow { height: 28px !important; }

            /* Pagination */
            .oz-pagination {
                display: none; justify-content: space-between; align-items: center;
                padding: 12px 18px; border-top: 1px solid var(--z-border-light); font-size: 0.82rem;
            }
            .oz-page-info { color: var(--z-text-muted); font-weight: 600; }
            .oz-page-controls { display: flex; align-items: center; gap: 6px; }
            .oz-page-select {
                padding: 6px 10px; border: 1px solid var(--z-border); border-radius: 6px;
                background: var(--z-bg-input); font-size: 0.82rem; cursor: pointer; font-family: inherit;
            }
            .oz-page-btn {
                background: var(--z-bg-card); border: 1px solid var(--z-border);
                color: var(--z-text); padding: 5px 10px; border-radius: 6px;
                cursor: pointer; font-weight: 500; font-family: inherit; font-size: 0.8rem;
            }
            .oz-page-btn:hover:not(:disabled) { background: var(--z-accent); color: #fff; border-color: var(--z-accent); }
            .oz-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
            .page-number {
                width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
                border-radius: 6px; cursor: pointer; border: 1px solid transparent; font-weight: 600; font-size: 0.78rem;
            }
            .page-number:hover:not(.active) { background: var(--z-bg-soft); }
            .page-number.active { background: var(--z-accent); color: #fff; }

            /* Boş Durum */
            .oz-empty { text-align: center; padding: 50px 20px; }
            .oz-empty-icon { font-size: 2.5rem; margin-bottom: 10px; }
            .oz-empty h3 { font-weight: 700; margin: 0 0 6px; font-size: 0.95rem; }
            .oz-empty p { color: var(--z-text-muted); font-size: 0.82rem; margin: 0; }
            .oz-empty-actions {
                display: flex;
                justify-content: center;
                gap: 8px;
                flex-wrap: wrap;
                margin-top: 16px;
            }

            /* WIP yapısı */
            .bilesen-adet-badge { background: #d97706; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; margin-right: 3px; }
            .note-icon { cursor: help; color: var(--z-accent); font-size: 0.85rem; margin-left: 3px; }

            /* Loading */
            .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(244,245,247,0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: center; align-items: center; }

            @media (max-width: 768px) {
                .oz-stats-row { grid-template-columns: repeat(2, 1fr); }
                .oz-primary-actions { justify-content: stretch; }
                .oz-primary-actions .oz-btn-action { flex: 1 1 100%; justify-content: center; }
                .oz-filter-bar, .oz-toolbar { flex-direction: column; }
                .oz-info-banner { flex-direction: column; text-align: center; }
            }
        </style>

        <div class="oz-page">

            {{-- 1) Bilgi Bandı --}}
            <div class="oz-info-banner">
                <div class="oz-info-icon">🏭</div>
                <div class="oz-info-text">
                    <h2>Özel Üretim (Stok) Takibi</h2>
                    <p>
                        İnternet siparişlerinden ayrı tutulan özel üretim emirleri.
                        Stoğa çalışma, kurumsal proje ve numune üretimleri bu ekrandan yönetilir.
                        Normal kargo siparişleriyle karışmasını önler.
                    </p>
                </div>
            </div>

            <div class="oz-primary-actions">
                <a href="{{ route('orders.index') }}" class="oz-btn-action oz-btn-secondary">
                    📥 Operasyon Merkezi
                </a>
                <button type="button" class="oz-btn-action purple" onclick="openIndependentStockModal()">
                    ➕ Yeni Stok Üretimi
                </button>
            </div>

            {{-- 2) Özet Kartları (KALDIRILDI) --}}

            {{-- 3) Filtre Çubuğu --}}
            <div class="oz-filter-bar" id="filterBar" style="display:none;">
                <div style="flex: 1 1 100%; display: flex; align-items: center; gap: 12px; margin-bottom: 2px;">
                    <label style="font-size: 0.68rem; font-weight: 700; color: var(--z-text-muted); text-transform: uppercase;">Üretim Türü:</label>
                    <div class="oz-filter-pills" id="filterUretimTuru">
                        <button type="button" class="oz-pill active" data-value="" onclick="setUretimTuruFilter(this)">Tümü</button>
                        <button type="button" class="oz-pill" data-value="Nihai" onclick="setUretimTuruFilter(this)">🏭 Nihai</button>
                        <button type="button" class="oz-pill" data-value="Ara" onclick="setUretimTuruFilter(this)">🔧 Ara Mamül</button>
                        <button type="button" class="oz-pill" data-value="HamMadde" onclick="setUretimTuruFilter(this)">🪵 Hammadde</button>
                        <button type="button" class="oz-pill" data-value="Eslesmemis" onclick="setUretimTuruFilter(this)">⚠️ Eşleşmemiş</button>
                    </div>
                </div>

                <div class="oz-filter-group">
                    <label>Durum</label>
                    <select id="filterDurum">
                        <option value="">Tümü (Aktif)</option>
                        <option value="UretimBekliyor">⏳ Üretim Bekliyor</option>
                        <option value="IsEmriVerildi">✅ İş Emri Verildi</option>
                        <option value="StokKarsilandi">📦 Stoktan Karşılanan</option>
                        <option value="Eslesmeyenler">⚠️ Eşleşmeyenler</option>
                        <option value="Pasif">⚪ Pasif / Kapanmış</option>
                    </select>
                </div>
                <div class="oz-filter-group">
                    <label>Pazaryeri</label>
                    <select id="filterPazaryeri"><option value="">Tümü</option></select>
                </div>
                <div class="oz-filter-group">
                    <label>Mağaza</label>
                    <select id="filterMagaza"><option value="">Tümü</option></select>
                </div>
                <div class="oz-filter-group">
                    <label>Kategori</label>
                    <select id="filterKategori"><option value="">Tümü</option></select>
                </div>
                <div class="oz-filter-group">
                    <label>Arama</label>
                    <input type="text" id="filterArama" placeholder="Sipariş No / Müşteri / Ürün" />
                </div>
                <div class="oz-filter-actions" style="margin-left:auto;">
                    <button type="button" class="oz-btn-filter" onclick="loadOrders()">🔍 Filtrele</button>
                    <button type="button" class="oz-btn-reset" onclick="resetFilters()">↺ Sıfırla</button>
                </div>
            </div>

            {{-- 4) Toplu İşlem Çubuğu --}}
            <div class="oz-toolbar" id="gridToolbar" style="display:none;">
                <span class="oz-selected-info"><span id="selectedCount">0</span> satır seçili</span>
                <button type="button" class="oz-btn-action green" id="btnTopluIsEmri" onclick="submitBulkWorkOrders()" disabled>
                    🔨 Toplu İş Emri Ver
                </button>
                <button type="button" class="oz-btn-action red" id="btnTopluIptal" onclick="cancelBulkWorkOrders()" disabled>
                    ✕ İş Emirlerini İptal Et
                </button>
            </div>

            {{-- 5) Tablo --}}
            <div class="oz-table-wrap" id="gridContainer" style="display:none;">
                <table id="ordersTable">
                    <thead>
                        <tr>
                            <th style="width:24px;"><input type="checkbox" id="masterCheckbox" onchange="toggleAllRows(this)" /></th>
                            <th style="width:24px;">#</th>
                            <th class="sortable" data-sort="siparisNo" onclick="sortColumn('siparisNo')" style="width:80px;">Sipariş No</th>
                            <th class="sortable" data-sort="siparisTarihi" onclick="sortColumn('siparisTarihi')" style="width:90px;">Tarih</th>
                            <th class="sortable" data-sort="urunAdi" onclick="sortColumn('urunAdi')">Ürün</th>
                            <th class="sortable" data-sort="adet" onclick="sortColumn('adet')" style="width:40px;">Adet</th>
                            <th class="sortable" data-sort="musaitAdet" onclick="sortColumn('musaitAdet')" style="width:58px;" title="GİED ile yeni sipariş bağlanabilecek kalan adet">Müsait</th>
                            <th class="sortable" data-sort="stokAdet" onclick="sortColumn('stokAdet')" style="width:44px;">Stok</th>
                            <th class="sortable" data-sort="uretimdeAdet" onclick="sortColumn('uretimdeAdet')" style="width:110px;">Görevlendirme</th>
                            <th class="sortable" data-sort="durum" onclick="sortColumn('durum')" style="width:105px;">Durum</th>
                            <th class="sortable" data-sort="eslesmePuani" onclick="sortColumn('eslesmePuani')" style="width:50px;">Eşl.</th>
                            <th style="width:40px;">DB</th>
                            <th style="width:70px;">İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="ordersBody"></tbody>
                </table>

                {{-- Pagination --}}
                <div class="oz-pagination" id="paginationContainer" style="display:none;">
                    <div class="oz-page-info" id="pageInfo">0-0 / 0 sipariş</div>
                    <div class="oz-page-controls">
                        <select class="oz-page-select" id="pageSizeSelect" onchange="changePageSize()">
                            <option value="50">50 / sayfa</option>
                            <option value="100">100 / sayfa</option>
                            <option value="200">200 / sayfa</option>
                            <option value="999999">Tümü</option>
                        </select>
                        <button class="oz-page-btn" id="btnPrevPage" onclick="prevPage()">← Önceki</button>
                        <div class="page-numbers" id="pageNumbers"></div>
                        <button class="oz-page-btn" id="btnNextPage" onclick="nextPage()">Sonraki →</button>
                    </div>
                </div>
            </div>

            {{-- Boş Durum --}}
            <div class="oz-empty" id="emptyState">
                <div class="oz-empty-icon">📭</div>
                <h3>Henüz özel üretim siparişi yüklenmedi</h3>
                <p>Operasyon Merkezi'nden Excel yükleyebilir veya buradan serbest stok üretimi oluşturabilirsiniz.</p>
                <div class="oz-empty-actions">
                    <button type="button" class="oz-btn-action purple" onclick="openIndependentStockModal()">
                        ➕ Yeni Stok Üretimi
                    </button>
                    <a href="{{ route('orders.index') }}" class="oz-btn-action oz-btn-secondary">
                        📥 Operasyon Merkezi
                    </a>
                </div>
            </div>

        </div>

        <div id="assignmentPopover" class="oz-assignment-popover" aria-hidden="true"></div>
        <div id="workOrderPopover" class="work-order-popover" aria-hidden="true"></div>

        @include('components.work-order-bom-preview')

        <script>
            // ============================================================
            //   GLOBAL
            // ============================================================
            var apiUrl = '/SiparisApi.ashx';
            var dbProducts = [];
            var allOrders = [];
            var selectedNos = new Set();
            var activeUretimTuru = '';  // '', 'Nihai', 'Ara', 'HamMadde', 'Eslesmemis'
            var currentSortField = '';
            var currentSortDir = '';  // 'asc' or 'desc'
            var workOrderPipelineCache = {};
            var workOrderPopoverTimer = null;
            var activeWorkOrderRow = null;

            // Sayfa yüklendiğinde
            $(document).ready(function () {
                initAssignmentPopover();
                setupWorkOrderPopover();
                loadProducts(function () {
                    loadOrders();
                });
            });

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

            function loadOrders() {
                var params = {
                    durum: $('#filterDurum').val() || '',
                    pazaryeri: $('#filterPazaryeri').val() || '',
                    magaza: $('#filterMagaza').val() || '',
                    kategori: $('#filterKategori').val() || '',
                    arama: $('#filterArama').val() || '',
                    ozelUretim: 1
                };

                $.ajax({
                    url: apiUrl + '?action=getOrders&' + $.param(params),
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            workOrderPipelineCache = {};
                            allOrders = res.orders || [];
                            updateStats(res.stats);
                            updateFilterDropdowns(res.filters);
                            updatePillCounts();
                            sortColumn('siparisTarihi', 'desc');
                            showGridUI(allOrders.length > 0);
                        }
                    },
                    error: function () { }
                });
            }

            function updateStats(stats) {
                // UI'dan kaldırıldı
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

            function showGridUI(hasData) {
                document.getElementById('filterBar').style.display = hasData ? 'flex' : 'none';
                document.getElementById('gridToolbar').style.display = hasData ? 'flex' : 'none';
                document.getElementById('gridContainer').style.display = hasData ? 'block' : 'none';
                document.getElementById('emptyState').style.display = hasData ? 'none' : 'block';
                try { document.getElementById('statsRow').style.display = 'grid'; } catch(e) {}
            }

            function resetFilters() {
                $('#filterDurum').val('');
                $('#filterPazaryeri').val('');
                $('#filterMagaza').val('');
                $('#filterKategori').val('');
                $('#filterArama').val('');
                activeUretimTuru = '';
                var pills = document.querySelectorAll('#filterUretimTuru .oz-pill');
                pills.forEach(function(p) { p.classList.remove('active'); });
                var firstPill = document.querySelector('#filterUretimTuru .oz-pill[data-value=""]');
                if (firstPill) firstPill.classList.add('active');
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

                var ths = document.querySelectorAll('th.sortable');
                for (var i = 0; i < ths.length; i++) {
                    ths[i].classList.remove('sort-asc', 'sort-desc');
                    if (ths[i].getAttribute('data-sort') === field) {
                        ths[i].classList.add(currentSortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                    }
                }

                var numericFields = ['adet', 'musaitAdet', 'stokAdet', 'uretimdeAdet', 'eslesmePuani', 'no'];
                var dateFields = ['siparisTarihi', 'kargoSonTeslim'];
                var isNumeric = numericFields.indexOf(field) >= 0;
                var isDate = dateFields.indexOf(field) >= 0;

                allOrders.sort(function (a, b) {
                    var va = a[field] || '';
                    var vb = b[field] || '';
                    if (field === 'musaitAdet') {
                        va = getReserveAvailable(a);
                        vb = getReserveAvailable(b);
                    }
                    else if (isNumeric) { va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; }
                    else if (isDate) { va = parseTurkishDate(va); vb = parseTurkishDate(vb); }
                    else { va = ('' + va).toLocaleLowerCase('tr'); vb = ('' + vb).toLocaleLowerCase('tr'); }
                    var cmp = 0;
                    if (va < vb) cmp = -1;
                    else if (va > vb) cmp = 1;
                    return currentSortDir === 'asc' ? cmp : -cmp;
                });

                currentPage = 1;
                renderGrid();
            }

            function parseTurkishDate(str) {
                if (!str) return 0;
                var parts = str.split(' ');
                var d = parts[0] ? parts[0].split('.') : [];
                var t = parts[1] ? parts[1].split(':') : ['00', '00'];
                if (d.length < 3) return 0;
                return new Date(parseInt(d[2]), parseInt(d[1]) - 1, parseInt(d[0]), parseInt(t[0] || 0), parseInt(t[1] || 0)).getTime() || 0;
            }

            // ============================================================
            //   PAGINATION
            // ============================================================
            function changePageSize() {
                var newSize = parseInt(document.getElementById('pageSizeSelect').value, 10);
                if (!isNaN(newSize)) { pageSize = newSize; currentPage = 1; renderGrid(); }
            }
            function prevPage() { if (currentPage > 1) { currentPage--; renderGrid(); } }
            function nextPage() {
                var mainOrders = allOrders.filter(function (o) { return o.anaSetSatirNo === 0; });
                var totalPages = Math.ceil(mainOrders.length / pageSize);
                if (currentPage < totalPages) { currentPage++; renderGrid(); }
            }
            function goToPage(page) { currentPage = page; renderGrid(); }

            function renderPagination(totalItems) {
                var container = document.getElementById('paginationContainer');
                if (totalItems === 0) { container.style.display = 'none'; return; }
                container.style.display = 'flex';
                var totalPages = Math.ceil(totalItems / pageSize);
                if (currentPage > totalPages) currentPage = Math.max(1, totalPages);
                var startItem = (currentPage - 1) * pageSize + 1;
                var endItem = Math.min(currentPage * pageSize, totalItems);
                document.getElementById('pageInfo').textContent = startItem + '-' + endItem + ' / ' + totalItems + ' sipariş';
                document.getElementById('btnPrevPage').disabled = currentPage === 1;
                document.getElementById('btnNextPage').disabled = currentPage === totalPages;
                var pageNumHtml = '';
                var startPage = Math.max(1, currentPage - 2);
                var endPage = Math.min(totalPages, startPage + 4);
                if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);
                if (startPage > 1) {
                    pageNumHtml += '<div class="page-number" onclick="goToPage(1)">1</div>';
                    if (startPage > 2) pageNumHtml += '<div style="padding:0 5px; color:var(--z-text-muted);">...</div>';
                }
                for (var i = startPage; i <= endPage; i++) {
                    var act = i === currentPage ? ' active' : '';
                    pageNumHtml += '<div class="page-number' + act + '" onclick="goToPage(' + i + ')">' + i + '</div>';
                }
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) pageNumHtml += '<div style="padding:0 5px; color:var(--z-text-muted);">...</div>';
                    pageNumHtml += '<div class="page-number" onclick="goToPage(' + totalPages + ')">' + totalPages + '</div>';
                }
                document.getElementById('pageNumbers').innerHTML = pageNumHtml;
            }

            // ============================================================
            //   PROGRESS BAR BUILDER
            // ============================================================
            function buildProgressHtml(order) {
                if (order.setMi) return '<span class="oz-progress-na">—</span>';

                var havuz = order.havuzAdet || 0;
                var personel = order.personelAdet || 0;
                var toplam = havuz + personel;
                var siparisAdet = order.adet || 0;

                if (order.durum !== 'IsEmriVerildi' && toplam <= 0) {
                    return '<span class="oz-progress-na">—</span>';
                }

                var hedef = Math.max(siparisAdet || 1, toplam);
                var pctPersonel = hedef > 0 ? Math.min(100, Math.round((personel / hedef) * 100)) : 0;
                var pctHavuz = hedef > 0 ? Math.min(100 - pctPersonel, Math.round((havuz / hedef) * 100)) : 0;
                var pctTotal = Math.min(100, pctPersonel + pctHavuz);

                var pctClass = pctTotal >= 100 ? 'full' : (pctTotal > 0 ? 'partial' : 'zero');
                var ghostWarn = (order.durum === 'IsEmriVerildi' && toplam <= 0)
                    ? ' <span style="color:#ef4444;" aria-label="İş emri verilmiş ama atölyede kayıt yok">⚠️</span>' : '';

                var popoverAttrs = [
                    'class="oz-progress-wrap"',
                    'tabindex="0"',
                    'role="button"',
                    'aria-label="Görevlendirme detayını göster"',
                    'data-siparis-no="' + escapeAttr(order.siparisNo || '') + '"',
                    'data-durum="' + escapeAttr(order.durum || '') + '"',
                    'data-siparis-adet="' + siparisAdet + '"',
                    'data-havuz="' + havuz + '"',
                    'data-personel="' + personel + '"',
                    'data-toplam="' + toplam + '"',
                    'data-ozel="' + (order.uretimdeOzel || 0) + '"',
                    'data-ghost="' + (order.durum === 'IsEmriVerildi' && toplam <= 0 ? '1' : '0') + '"'
                ].join(' ');

                var barHtml = '<div ' + popoverAttrs + '>' +
                    '<div class="oz-progress-bar"><div class="oz-progress-bar-inner" style="width:' + pctTotal + '%">' +
                    (pctPersonel > 0 ? '<div class="oz-progress-seg-personel" style="flex:' + pctPersonel + '"></div>' : '') +
                    (pctHavuz > 0 ? '<div class="oz-progress-seg-havuz" style="flex:' + pctHavuz + '"></div>' : '') +
                    '</div></div>' +
                    '<div class="oz-progress-label">' +
                    '<span class="pct ' + pctClass + '">' + toplam + '/' + siparisAdet + '</span>' +
                    (personel > 0 ? ' <span class="oz-progress-chip personel">👷' + personel + '</span>' : '') +
                    (havuz > 0 ? ' <span class="oz-progress-chip havuz">📦' + havuz + '</span>' : '') +
                    ghostWarn +
                    '</div></div>';

                return barHtml;
            }

            function isFreeStockProduction(order) {
                return !!(order && order.isSerbestStokUretimi && !order.setMi);
            }

            function canAddStockProduction(order) {
                return isFreeStockProduction(order)
                    && !!order.aktif
                    && order.durum !== 'Pasif'
                    && (parseInt(order.gorevNo, 10) || 0) > 0
                    && (parseInt(order.eslesenUrunNo, 10) || 0) > 0;
            }

            function stockProductionPreviewType(order) {
                var tur = (order && order.eslesenUrunTur) ? order.eslesenUrunTur : 'Nihai';
                if (tur === 'Ham Madde') return 'HamMadde';
                if (tur === 'Ara Mamül') return 'Ara';
                return tur || 'Nihai';
            }

            function buildStatusBadge(order, compact) {
                if (!order) return '<span class="oz-badge pasif">⚪ Durum yok</span>';

                if (order.setMi) {
                    var childCount = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; }).length;
                    return '<span class="oz-badge set-parent" onclick="toggleSetChildren(' + order.no + ')" title="' + childCount + ' bileşen">📦 SET (' + childCount + ') ▼</span>';
                }

                var status = order.durum || '';
                var statusMap = {
                    'UretimBekliyor': { cls: 'bekliyor', icon: '⏳', label: compact ? 'İE Bekliyor' : 'İş Emri Bekliyor', trigger: 'status-detail-trigger' },
                    'IsEmriVerildi': { cls: 'verildi', icon: '✅', label: compact ? 'Verildi' : 'İş Emri Verildi', trigger: 'work-order-trigger' },
                    'StokKarsilandi': { cls: 'stok', icon: '📦', label: 'Stoktan', trigger: 'status-detail-trigger' },
                    'UretimdenKarsilaniyor': { cls: 'stok', icon: '🔗', label: compact ? 'Rezerve' : 'Üretime Bağlı', trigger: 'status-detail-trigger' },
                    'PasifDevamEden': { cls: 'bekliyor', icon: '🔸', label: compact ? 'Devam' : 'Devam Eden', trigger: 'status-detail-trigger' },
                    'Pasif': { cls: 'pasif', icon: '⚪', label: 'Pasif', trigger: 'status-detail-trigger' }
                };
                var meta = statusMap[status] || { cls: 'pasif', icon: '⚪', label: status || 'Pasif', trigger: 'status-detail-trigger' };
                var attrs = [
                    'class="oz-badge ' + meta.cls + ' ' + meta.trigger + '"',
                    'data-no="' + (parseInt(order.no, 10) || 0) + '"',
                    'data-status="' + escapeAttr(status) + '"',
                    'tabindex="0"',
                    'role="button"',
                    'aria-label="' + escapeAttr(meta.label + ' durum detayını göster') + '"'
                ].join(' ');

                return '<span ' + attrs + '>' + meta.icon + ' ' + escapeHtml(meta.label) + '</span>';
            }

            function wrapRowActions(html) {
                return '<div class="oz-row-actions">' + html + '</div>';
            }

            // ============================================================
            //   ÜRETİM TÜRÜ FİLTRE
            // ============================================================
            function setUretimTuruFilter(btn) {
                if (btn.classList.contains('pill-disabled')) return;
                var pills = document.querySelectorAll('#filterUretimTuru .oz-pill');
                pills.forEach(function(p) { p.classList.remove('active'); });
                btn.classList.add('active');
                activeUretimTuru = btn.getAttribute('data-value') || '';
                currentPage = 1;
                renderGrid();
            }

            function updatePillCounts() {
                var mainOrders = allOrders.filter(function(o) { return o.anaSetSatirNo === 0; });
                var counts = {
                    '': mainOrders.length,
                    'Nihai': 0,
                    'Ara': 0,
                    'HamMadde': 0,
                    'Eslesmemis': 0
                };
                mainOrders.forEach(function(o) {
                    if (o.eslesenUrunNo <= 0 && !o.setMi) counts['Eslesmemis']++;
                    if (o.eslesenUrunTur === 'Nihai') counts['Nihai']++;
                    else if (o.eslesenUrunTur === 'Ara') counts['Ara']++;
                    else if (o.eslesenUrunTur === 'HamMadde') counts['HamMadde']++;
                });
                var pills = document.querySelectorAll('#filterUretimTuru .oz-pill');
                pills.forEach(function(p) {
                    var val = p.getAttribute('data-value') || '';
                    var count = counts[val] || 0;
                    var baseText = p.getAttribute('data-label');
                    if (!baseText) {
                        baseText = p.textContent.replace(/\s*\(\d+\)\s*$/, '').trim();
                        p.setAttribute('data-label', baseText);
                    }
                    p.textContent = baseText + (val !== '' ? ' (' + count + ')' : '');
                    if (val !== '' && count === 0) {
                        p.classList.add('pill-disabled');
                        p.style.opacity = '0.4';
                        p.style.cursor = 'not-allowed';
                    } else {
                        p.classList.remove('pill-disabled');
                        p.style.opacity = '';
                        p.style.cursor = '';
                    }
                });
            }

            // ============================================================
            //   GRID RENDER
            // ============================================================
            function renderGrid() {
                hideAssignmentPopover();
                hideWorkOrderPopover();
                var tbody = document.getElementById('ordersBody');
                tbody.innerHTML = '';
                selectedNos.clear();
                document.getElementById('masterCheckbox').checked = false;
                updateSelectedCount();

                var mainOrders = allOrders.filter(function (o) { return o.anaSetSatirNo === 0; });

                // Üretim Türü filtresi
                if (activeUretimTuru) {
                    mainOrders = mainOrders.filter(function(o) {
                        if (activeUretimTuru === 'Eslesmemis') return o.eslesenUrunNo <= 0 && !o.setMi;
                        return o.eslesenUrunTur === activeUretimTuru;
                    });
                }

                renderPagination(mainOrders.length);
                var displayOrders = mainOrders.slice((currentPage - 1) * pageSize, currentPage * pageSize);

                var optionsHtml = '<option value="">-- Manuel Seç --</option>';
                dbProducts.forEach(function (p) {
                    var label = p.sistemAdi || p.urunId || ('Ürün #' + p.no);
                    var compositeVal = p.tur + '_' + p.no;
                    optionsHtml += '<option value="' + compositeVal + '">' + escapeHtml(label) + '</option>';
                });

                displayOrders.forEach(function (order, idx) {
                    var i = (currentPage - 1) * pageSize + idx;
                    if (order.anaSetSatirNo > 0) return;

                    var tr = document.createElement('tr');
                    tr.setAttribute('data-no', order.no);

                    // Row class
                    if (order.setMi) tr.className = 'set-parent-row';
                    else if (order.durum === 'IsEmriVerildi') tr.className = 'is-emri-verildi';
                    else if (order.durum === 'Pasif') tr.className = 'pasif';
                    else if (order.durum === 'UretimBekliyor' && order.kargoSonTeslim) {
                        var urgency = getUrgencyClass(order.kargoSonTeslim);
                        if (urgency) tr.className = urgency;
                    }

                    // Durum badge
                    var durumHtml = buildStatusBadge(order, false);

                    // Eşleşme
                    var matchHtml = '';
                    if (order.setMi) matchHtml = '<span style="color:#ea580c; font-weight:600;">Set</span>';
                    else if (order.eslesmePuani >= 80) matchHtml = '<span class="oz-match-ok">✅ %' + order.eslesmePuani + '</span>';
                    else if (order.eslesmePuani >= 40) matchHtml = '<span class="oz-match-warn">⚠️ %' + order.eslesmePuani + '</span>';
                    else matchHtml = '<span class="oz-match-fail">❌ Yok</span>';

                    // Müşteri notu
                    var musteriNoteHtml = '';
                    if (order.musteriNotu) {
                        musteriNoteHtml = ' <i class="fa-solid fa-sticky-note note-icon" title="' + escapeHtml(order.musteriNotu) + '"></i>';
                    }

                    // Stok
                    var stokAdet = order.stokAdet || 0;
                    var stokClass = stokAdet >= order.adet ? 'oz-stok-ok' : (stokAdet > 0 ? 'oz-stok-kismi' : 'oz-stok-yok');
                    var stokHtml = order.setMi ? '<span style="color:var(--z-text-muted)">—</span>' : '<span class="' + stokClass + '">' + stokAdet + '</span>';
                    var musaitHtml = order.setMi ? '<span style="color:var(--z-text-muted)">—</span>' : buildReserveAvailableHtml(order);

                    // Görevlendirme Progress Bar
                    var progressHtml = buildProgressHtml(order);

                    var cbDisabled = order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.setMi;
                    var btnDisabled = order.durum === 'IsEmriVerildi' || order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.eslesenUrunNo <= 0 || order.setMi;

                    var urunAdiDisplay = order.setMi
                        ? '<span style="cursor:pointer;" onclick="toggleSetChildren(' + order.no + ')">' + escapeHtml(order.urunAdi) + '</span>'
                        : escapeHtml(order.urunAdi);

                    // WIP stats (Eksik/Rezerve)
                    var wipStatsHtml = '';
                    if (isFreeStockProduction(order)) {
                        var planlanan = order.stokUretimToplamAdet || order.adet || 0;
                        var ilaveToplam = order.stokUretimIlaveToplam || 0;
                        var ilaveSayisi = order.stokUretimIlaveSayisi || 0;
                        var rootNo = order.stokUretimRootNo || order.no;
                        wipStatsHtml = '<div style="font-size:0.72rem; margin-top:3px;">' +
                            '<span style="color:#475569; font-weight:600;" title="Aynı ana stok üretimine bağlı toplam plan">Plan: ' + planlanan + '</span>' +
                            (ilaveToplam > 0 ? ' <span class="oz-stock-addition-badge" title="Bu stok üretimine sonradan açılan ilaveler">+ ' + ilaveToplam + ' ilave / ' + ilaveSayisi + ' kayıt</span>' : '') +
                            (order.stokUretimIlaveMi ? ' <span style="color:#7c3aed; font-weight:700;" title="Ana stok üretimi #' + rootNo + '">İlave</span>' : '') +
                            '</div>';
                    } else if (order.bagliSiparisler) {
                        var bosta = order.bostaKalan || 0;
                        var rezerve = order.rezerveEdilen || 0;
                        wipStatsHtml = '<div style="font-size:0.72rem; margin-top:3px;"><span style="color:#ea580c; font-weight:600;" title="Siparişin kapanması için hala atanması gereken miktar">Eksik/Bekleyen: ' + bosta + '</span> | <span style="color:#7c3aed; font-weight:600;">Rezerve: ' + rezerve + '</span></div>';
                    }

                    var selectHtml = order.setMi
                        ? '<span style="color:var(--z-text-muted); font-size:0.72rem;">Set</span>'
                        : '<button class="oz-btn-row gray" onclick="openMatchModal(' + order.no + ')" ' + (order.durum === 'IsEmriVerildi' || order.durum === 'StokKarsilandi' ? 'disabled' : '') + ' style="padding:4px 8px;" title="Veritabanı ürünü ara/seç">🔍</button>';

                    // Aksiyon
                    var actionHtml = '';
                    var additionActionHtml = canAddStockProduction(order)
                        ? '<button type="button" class="oz-btn-row purple" data-no="' + order.no + '" onclick="openStockProductionAdditionModal(' + order.no + ')">+ İlave Et</button>'
                        : '';
                    if (order.bagliSiparisler) {
                        if (order.bagliSiparisler.length > 0) {
                            actionHtml = '<button type="button" class="oz-btn-row purple" onclick="toggleWipChildren(' + order.no + ')">🔍 Rzv. Yönet</button>';
                        } else {
                            actionHtml = '<span style="color:var(--z-text-muted); font-size:0.75rem; font-weight:600;">📦 Stoka Üretim</span>';
                        }
                    } else if (order.setMi) {
                        actionHtml = '<button type="button" class="oz-btn-row orange" onclick="toggleSetChildren(' + order.no + ')">▼ Detay</button>';
                    } else if (order.durum === 'IsEmriVerildi') {
                        actionHtml = '<button type="button" class="oz-btn-row red" data-no="' + order.no + '" onclick="cancelWorkOrder(' + order.no + ')">✕ İptal</button>';
                    } else {
                        actionHtml = '<button type="button" class="oz-btn-row green" data-no="' + order.no + '" onclick="submitSingleWorkOrder(' + order.no + ')" ' + (btnDisabled ? 'disabled' : '') + '>🔨 İş Emri</button>';
                    }
                    if (additionActionHtml) {
                        actionHtml = wrapRowActions(actionHtml + additionActionHtml);
                    }

                    tr.innerHTML =
                        '<td><input type="checkbox" class="row-cb" data-no="' + order.no + '" onchange="onRowCheckChange(this)" ' + (cbDisabled ? 'disabled' : '') + ' /></td>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td><strong>' + escapeHtml(order.siparisNo) + '</strong></td>' +
                        '<td style="white-space:nowrap; font-size:0.75rem; color:var(--z-text-muted);">' + escapeHtml(order.siparisTarihi) + '</td>' +
                        '<td style="max-width:280px;">' + urunAdiDisplay + wipStatsHtml + (order.musteriNotu ? musteriNoteHtml : '') + '</td>' +
                        '<td style="text-align:center;"><strong>' + order.adet + '</strong></td>' +
                        '<td style="text-align:center;">' + musaitHtml + '</td>' +
                        '<td style="text-align:center;">' + stokHtml + '</td>' +
                        '<td>' + progressHtml + '</td>' +
                        '<td>' + durumHtml + '</td>' +
                        '<td>' + matchHtml + '</td>' +
                        '<td>' + selectHtml + '</td>' +
                        '<td>' + actionHtml + '</td>';

                    tbody.appendChild(tr);

                    // Rezerve siparişler alt tablo
                    if (order.bagliSiparisler && order.bagliSiparisler.length > 0) {
                        var tdColSpan = 13;
                        var wipTr = document.createElement('tr');
                        wipTr.className = 'wip-child-container';
                        wipTr.id = 'wip_child_' + order.no;
                        wipTr.style.display = 'none';
                        wipTr.style.backgroundColor = 'rgba(139,92,246,0.03)';

                        var reserveToplam = order.bagliSiparisler.reduce(function(total, b) {
                            return total + (parseInt(b.adet, 10) || 0);
                        }, 0);
                        var musaitAdet = getReserveAvailable(order);
                        var innerHtml = '<td colspan="' + tdColSpan + '" style="padding:12px 36px 16px; border-left:4px solid #8b5cf6; background:linear-gradient(90deg, rgba(139,92,246,0.07), rgba(255,255,255,0.92));">' +
                            '<div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:10px;">' +
                            '<div>' +
                            '<div style="color:#6d28d9; font-size:0.78rem; font-weight:800; letter-spacing:0.02em; text-transform:uppercase;">🔗 Rezerve Sipariş Detayları</div>' +
                            '<div style="color:#64748b; font-size:0.74rem; margin-top:2px;">GİED ile bu özel üretime bağlanan siparişler ve zaman bilgileri</div>' +
                            '</div>' +
                            '<div style="display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end;">' +
                            '<span style="background:#ede9fe; color:#6d28d9; padding:5px 10px; border-radius:999px; font-size:0.72rem; font-weight:800;">' + order.bagliSiparisler.length + ' Sipariş</span>' +
                            '<span style="background:#f3e8ff; color:#7c3aed; padding:5px 10px; border-radius:999px; font-size:0.72rem; font-weight:800;">' + reserveToplam + ' Adet Rezerve</span>' +
                            '<span style="background:#ecfdf5; color:#047857; padding:5px 10px; border-radius:999px; font-size:0.72rem; font-weight:800;">' + musaitAdet + ' Adet Boşta</span>' +
                            '</div>' +
                            '</div>' +
                            '<div style="overflow-x:auto;">' +
                            '<table style="width:100%; min-width:1060px; border-collapse:collapse; font-size:0.78rem; background:#fff; border:1px solid var(--z-border-light); border-radius:8px; overflow:hidden;">' +
                            '<thead><tr style="background:#f8fafc; border-bottom:1px solid var(--z-border-light);">' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">SİPARİŞ</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">MÜŞTERİ / PAZAR</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">ÜRÜN</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">SİPARİŞ / KARGO</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">GİED</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:center;">MİKTAR</th>' +
                            '<th style="padding:9px 10px; color:#7c3aed; font-weight:800; font-size:0.68rem; text-align:left;">DURUM</th>' +
                            '</tr></thead><tbody>';

                        order.bagliSiparisler.forEach(function(b) {
                            var pazaryeri = [b.pazaryeri, b.magaza].filter(Boolean).join(' / ');
                            var musteri = b.musteri ? escapeHtml(b.musteri) : '<span style="color:#94a3b8;">Müşteri yok</span>';
                            var urunAdi = b.urunAdi ? escapeHtml(b.urunAdi) : '<span style="color:#94a3b8;">Ürün adı yok</span>';
                            var siparisTarihi = b.siparisTarihi ? escapeHtml(b.siparisTarihi) : '<span style="color:#94a3b8;">Tarih yok</span>';
                            var kargoTarihi = b.kargoSonTeslim ? escapeHtml(b.kargoSonTeslim) : '<span style="color:#94a3b8;">Kargo yok</span>';
                            var giedTarihi = b.giedTarihi ? escapeHtml(b.giedTarihi) : '<span style="color:#94a3b8;">Kayıt yok</span>';
                            var giedSure = b.giedGecenSure ? '<div style="color:#64748b; font-size:0.68rem; margin-top:2px;">' + escapeHtml(b.giedGecenSure) + ' önce</div>' : '';
                            var giedKaynak = b.giedKaynak ? '<span style="display:inline-block; background:#eef2ff; color:#4f46e5; padding:1px 6px; border-radius:999px; font-size:0.62rem; font-weight:800; margin-top:3px;">' + escapeHtml(b.giedKaynak) + '</span>' : '';
                            var gorevNo = b.gorevNo > 0 ? '<div style="color:#64748b; font-size:0.68rem; margin-top:4px;">Görev #' + b.gorevNo + '</div>' : '';
                            var durum = b.durum ? escapeHtml(b.durum) : 'Aktif';
                            innerHtml += '<tr style="border-bottom:1px solid var(--z-border-light);">' +
                                '<td style="padding:10px; vertical-align:top;"><strong style="display:block; color:#111827;">' + escapeHtml(b.siparisNo) + '</strong><span style="color:#94a3b8; font-size:0.68rem;">Satır #' + b.no + '</span></td>' +
                                '<td style="padding:10px; vertical-align:top;"><strong style="display:block; color:#334155;">' + musteri + '</strong>' + (pazaryeri ? '<div style="color:#64748b; font-size:0.68rem; margin-top:3px;">' + escapeHtml(pazaryeri) + '</div>' : '') + '</td>' +
                                '<td style="padding:10px; vertical-align:top; max-width:320px;"><div style="color:#334155; font-weight:600; line-height:1.35;">' + urunAdi + '</div></td>' +
                                '<td style="padding:10px; vertical-align:top;"><div><span style="color:#94a3b8;">Sipariş:</span> <strong>' + siparisTarihi + '</strong></div><div style="margin-top:3px;"><span style="color:#94a3b8;">Kargo:</span> <strong>' + kargoTarihi + '</strong></div></td>' +
                                '<td style="padding:10px; vertical-align:top;"><strong style="color:#334155;">' + giedTarihi + '</strong>' + giedSure + giedKaynak + '</td>' +
                                '<td style="padding:10px; text-align:center; vertical-align:top;"><span style="background:#7c3aed; color:#fff; padding:3px 9px; border-radius:7px; font-weight:800; font-size:0.72rem;">' + (parseInt(b.adet, 10) || 0) + ' Adet</span></td>' +
                                '<td style="padding:10px; vertical-align:top;"><span style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:7px; font-weight:700; font-size:0.7rem;">' + durum + '</span>' + gorevNo + '</td>' +
                                '</tr>';
                        });
                        innerHtml += '</tbody></table></div></td>';
                        wipTr.innerHTML = innerHtml;
                        tbody.appendChild(wipTr);
                    }

                    // Set child satırları
                    if (order.setMi) {
                        var children = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; });
                        children.forEach(function (child) {
                            var ctr = document.createElement('tr');
                            ctr.className = 'set-child-row';
                            ctr.setAttribute('data-no', child.no);
                            ctr.setAttribute('data-parent', order.no);

                            var childDurumHtml = buildStatusBadge(child, true);

                            var childMatchHtml = child.eslesmePuani >= 80 ? '<span class="oz-match-ok">✅ %' + child.eslesmePuani + '</span>' : '<span class="oz-match-fail">❌ Yok</span>';

                            var childStokAdet = child.stokAdet || 0;
                            var childStokClass = childStokAdet >= child.adet ? 'oz-stok-ok' : (childStokAdet > 0 ? 'oz-stok-kismi' : 'oz-stok-yok');


                            var childBtnDisabled = child.durum === 'IsEmriVerildi' || child.durum === 'Pasif' || child.durum === 'StokKarsilandi' || child.eslesenUrunNo <= 0;

                            var childActionHtml = '';
                            if (child.durum === 'IsEmriVerildi') childActionHtml = '<button type="button" class="oz-btn-row red" onclick="cancelWorkOrder(' + child.no + ')">✕ İptal</button>';
                            else if (child.durum === 'StokKarsilandi') childActionHtml = '<span class="oz-badge stok">📦 OK</span>';
                            else childActionHtml = '<button type="button" class="oz-btn-row green" onclick="submitSingleWorkOrder(' + child.no + ')" ' + (childBtnDisabled ? 'disabled' : '') + '>🔨 İş Emri</button>';

                            ctr.innerHTML =
                                '<td><input type="checkbox" class="row-cb" data-no="' + child.no + '" onchange="onRowCheckChange(this)" /></td>' +
                                '<td style="color:#8b5cf6;">↳</td>' +
                                '<td><strong>' + escapeHtml(child.siparisNo) + '</strong></td>' +
                                '<td style="white-space:nowrap; font-size:0.75rem; color:var(--z-text-muted);">' + escapeHtml(child.siparisTarihi) + '</td>' +
                                '<td style="max-width:280px;"><span class="oz-badge set-child">Bileşen</span> ' + escapeHtml(child.urunAdi) + '</td>' +
                                '<td style="text-align:center;"><strong>' + child.adet + '</strong></td>' +
                                '<td style="text-align:center;"><span style="color:var(--z-text-muted)">—</span></td>' +
                                '<td style="text-align:center;"><span class="' + childStokClass + '">' + childStokAdet + '</span></td>' +
                                '<td>' + buildProgressHtml(child) + '</td>' +
                                '<td>' + childDurumHtml + '</td>' +
                                '<td>' + childMatchHtml + '</td>' +
                                '<td><button class="oz-btn-row gray" onclick="openMatchModal(' + child.no + ')" ' + (child.durum === 'IsEmriVerildi' || child.durum === 'StokKarsilandi' ? 'disabled' : '') + ' style="padding:4px 8px;" title="Veritabanı ürünü ara/seç">🔍</button></td>' +
                                '<td>' + childActionHtml + '</td>';

                            tbody.appendChild(ctr);
                        });
                    }

                    // Node selection was handled by buttons, no inline select anymore.
                });

                // Select2 ve modal işlemleri tamamlandı.
            }

            // ============================================================
            //   SEÇİM YÖNETİMİ
            // ============================================================
            function onRowCheckChange(cb) {
                var no = parseInt(cb.getAttribute('data-no'));
                if (cb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
                else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
                updateSelectedCount();
            }

            function toggleAllRows(masterCb) {
                var checkboxes = document.querySelectorAll('.row-cb:not(:disabled)');
                checkboxes.forEach(function (cb) {
                    cb.checked = masterCb.checked;
                    var no = parseInt(cb.getAttribute('data-no'));
                    if (masterCb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
                    else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
                });
                updateSelectedCount();
            }

            function updateSelectedCount() {
                document.getElementById('selectedCount').textContent = selectedNos.size;
                document.getElementById('btnTopluIsEmri').disabled = selectedNos.size === 0;
                document.getElementById('btnTopluIptal').disabled = selectedNos.size === 0;
            }

            // ============================================================
            //   İŞ EMRİ SEVİYE SEÇİMİ MODALI (Ortak)
            // ============================================================
            function onWoTypeChange(label, radio, urunNo) {
                document.querySelectorAll('.wo-type-label').forEach(function(l){l.style.borderColor='#e5e7eb';l.style.background='transparent'});
                label.style.borderColor = (radio.value === 'Nihai') ? '#16a34a' : (radio.value === 'Ara' ? '#7c3aed' : '#ea580c');
                label.style.background = (radio.value === 'Nihai') ? '#f0fdf4' : (radio.value === 'Ara' ? '#f5f3ff' : '#fff7ed');
                radio.checked = true;

                var compContainer = document.getElementById('bomComponentSelectorContainer');
                var compSelect = document.getElementById('swalBomComponent');
                if (!compContainer || !compSelect || !urunNo) return;

                if (radio.value === 'Ara' || radio.value === 'HamMadde') {
                    compContainer.style.display = 'block';
                    compSelect.innerHTML = '<option value="">Yükleniyor...</option>';
                    compSelect.disabled = true;

                    $.ajax({
                        url: apiUrl + '?action=getProductBomComponents&urunNo=' + urunNo + '&tur=' + radio.value,
                        type: 'GET',
                        success: function(res) {
                            if (res.success) {
                                var html = '<option value="">(Ürünün Kendisi - Ağaç Seçilmedi)</option>';
                                res.components.forEach(function(c) {
                                    var optText = c.name + ' (' + c.type + (c.department ? ' - ' + c.department : '') + ')';
                                    html += '<option value="' + c.id + '">' + escapeHtml(optText) + '</option>';
                                });
                                compSelect.innerHTML = html;
                                compSelect.disabled = false;
                            } else {
                                compSelect.innerHTML = '<option value="">Ürün Eşleşmesi Bulunmuyor!</option>';
                            }
                        },
                        error: function() {
                            compSelect.innerHTML = '<option value="">Ağaç Yüklenemedi!</option>';
                        }
                    });
                } else {
                    compContainer.style.display = 'none';
                    compSelect.value = '';
                }
            }

            function buildWorkOrderTypeSelector(urunNo) {
                // If urunNo is provided (single or same type bulk), we enable BOM components selector
                var uNoStr = urunNo || 0;

                // Using divs (NOT <label>) to avoid double-click / event-bubbling conflicts
                var html = '<div style="text-align:left; margin: 12px 0;">' +
                    '<div style="display:flex; align-items:center; gap:10px; padding:12px 16px; border:2px solid #16a34a; background:#f0fdf4; border-radius:10px; margin-bottom:8px; cursor:pointer; transition: all 0.2s;" class="wo-type-card" data-value="Nihai" data-urunno="' + uNoStr + '">' +
                        '<input type="radio" name="woType" value="Nihai" checked style="accent-color:#16a34a; width:18px; height:18px; pointer-events:none;">' +
                        '<div><strong style="font-size:1rem;">🏭 Nihai Ürün</strong><br><span style="font-size:0.8rem; color:#6b7280;">Tam üretim — BOM ağacındaki tüm alt parçalar otomatik oluşur</span></div>' +
                    '</div>' +
                    '<div style="display:flex; align-items:center; gap:10px; padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; margin-bottom:8px; cursor:pointer; transition: all 0.2s;" class="wo-type-card" data-value="Ara" data-urunno="' + uNoStr + '">' +
                        '<input type="radio" name="woType" value="Ara" style="accent-color:#7c3aed; width:18px; height:18px; pointer-events:none;">' +
                        '<div><strong style="font-size:1rem;">🔧 Ara Mamül</strong><br><span style="font-size:0.8rem; color:#6b7280;">Seçili parça + alt dalları — Terzihane, Montaj vb. bölüm işleri</span></div>' +
                    '</div>' +
                    '<div style="display:flex; align-items:center; gap:10px; padding:12px 16px; border:2px solid #e5e7eb; border-radius:10px; cursor:pointer; transition: all 0.2s;" class="wo-type-card" data-value="HamMadde" data-urunno="' + uNoStr + '">' +
                        '<input type="radio" name="woType" value="HamMadde" style="accent-color:#ea580c; width:18px; height:18px; pointer-events:none;">' +
                        '<div><strong style="font-size:1rem;">🪵 Ham Madde</strong><br><span style="font-size:0.8rem; color:#6b7280;">Sadece tek parça/malzeme — Alt dal açılmaz</span></div>' +
                    '</div>' +
                '</div>';

                if (urunNo > 0) {
                    html += '<div id="bomComponentSelectorContainer" style="display:none; text-align:left; margin-top:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">' +
                        '<label style="font-size:0.85rem; color:#475569; font-weight:600;">Belirli Bir Alt Bileşeni Seçin (Opsiyonel)</label>' +
                        '<select id="swalBomComponent" style="width:100%; margin-top:6px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' +
                            '<option value="">(Seçmek için bekleyin...)</option>' +
                        '</select>' +
                        '<p style="font-size:0.75rem; color:#94a3b8; margin-top:4px;">Seçmezseniz tıklanan ürünün kendisi işleme girer.</p>' +
                    '</div>';
                }

	                return html;
	            }

            function buildWorkOrderStockSelector(defaultMode) {
                defaultMode = defaultMode === 'StokHaric' ? 'StokHaric' : 'StokDahil';
                return '<div style="text-align:left; margin: 12px 0;">' +
                    '<div style="font-size:0.82rem; color:#475569; font-weight:700; margin-bottom:6px;">Stok Kullanımı</div>' +
                    '<div style="display:flex; align-items:center; gap:10px; padding:10px 12px; border:2px solid ' + (defaultMode === 'StokDahil' ? '#16a34a' : '#e5e7eb') + '; background:' + (defaultMode === 'StokDahil' ? '#f0fdf4' : 'transparent') + '; border-radius:10px; margin-bottom:8px; cursor:pointer;" class="wo-stock-card" data-value="StokDahil">' +
                        '<input type="radio" name="swalStokDurum" value="StokDahil" ' + (defaultMode === 'StokDahil' ? 'checked' : '') + ' style="accent-color:#16a34a; width:18px; height:18px; pointer-events:none;">' +
	                        '<div><strong>Stok Dahil</strong><br><span style="font-size:0.78rem; color:#6b7280;">Her BOM satırı kendi boş stoğuyla değerlendirilir; üst parça stoğu alt parçayı kapatmaz</span></div>' +
                    '</div>' +
                    '<div style="display:flex; align-items:center; gap:10px; padding:10px 12px; border:2px solid ' + (defaultMode === 'StokHaric' ? '#7c3aed' : '#e5e7eb') + '; background:' + (defaultMode === 'StokHaric' ? '#f5f3ff' : 'transparent') + '; border-radius:10px; cursor:pointer;" class="wo-stock-card" data-value="StokHaric">' +
                        '<input type="radio" name="swalStokDurum" value="StokHaric" ' + (defaultMode === 'StokHaric' ? 'checked' : '') + ' style="accent-color:#7c3aed; width:18px; height:18px; pointer-events:none;">' +
	                        '<div><strong>Stok Hariç</strong><br><span style="font-size:0.78rem; color:#6b7280;">Stoka dokunmadan BOM ihtiyacı kadar üretim açılır</span></div>' +
                    '</div>' +
                '</div>';
            }

            // Attach click handlers to wo-type-card using event delegation (works even inside SweetAlert)
	            $(document).on('click', '.wo-type-card', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var card = $(this)[0];
                var value = card.getAttribute('data-value');
                var urunNo = parseInt(card.getAttribute('data-urunno')) || 0;

                // Check radio
                var radio = card.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Update all card styles
                var allCards = document.querySelectorAll('.wo-type-card');
                allCards.forEach(function(c) {
                    c.style.borderColor = '#e5e7eb';
                    c.style.background = 'transparent';
	            });

	                // Highlight selected
	                var colors = { 'Nihai': ['#16a34a', '#f0fdf4'], 'Ara': ['#7c3aed', '#f5f3ff'], 'HamMadde': ['#ea580c', '#fff7ed'] };
	                card.style.borderColor = colors[value][0];
                card.style.background = colors[value][1];

                var independentSelect = $('#swalIndStockProduct');
                if (independentSelect.length) {
                    var previousType = independentSelect.data('selected-type') || 'Nihai';
                    var placeholder = getIndependentStockProductPlaceholder(value);
                    independentSelect.attr('data-placeholder', placeholder);
                    if (previousType !== value) {
                        independentSelect.data('selected-type', value);
                        if (independentSelect.data('select2')) {
                            independentSelect.val(null).trigger('change');
                            independentSelect.select2('close');
                            var renderedPlaceholder = document.querySelector('.swal2-container .select2-selection__placeholder');
                            if (renderedPlaceholder) renderedPlaceholder.textContent = placeholder;
                        } else {
                            independentSelect.val('');
                        }
                        allCards.forEach(function(c) {
                            c.setAttribute('data-urunno', '0');
                        });
                        urunNo = 0;
                    }
                }

                // BOM component loading
                var compContainer = document.getElementById('bomComponentSelectorContainer');
                var compSelect = document.getElementById('swalBomComponent');

                if ((value === 'Ara' || value === 'HamMadde') && compContainer && compSelect && urunNo > 0) {
                    compContainer.style.display = 'block';
                    compSelect.innerHTML = '<option value="">Yükleniyor...</option>';
                    compSelect.disabled = true;

                    $.ajax({
                        url: apiUrl + '?action=getProductBomComponents&urunNo=' + urunNo + '&tur=' + value,
                        type: 'GET',
                        success: function(res) {
                            if (res.success && res.components && res.components.length > 0) {
                                var html = '<option value="">(Ürünün Kendisi - Ağaç Seçilmedi)</option>';
                                res.components.forEach(function(c) {
                                    var optText = c.name + ' (' + c.type + (c.department ? ' - ' + c.department : '') + ')';
                                    html += '<option value="' + c.id + '">' + escapeHtml(optText) + '</option>';
                                });
                                compSelect.innerHTML = html;
                                compSelect.disabled = false;
                            } else if (res.success && (!res.components || res.components.length === 0)) {
                                compSelect.innerHTML = '<option value="">Bu ürünün alt bileşeni bulunamadı</option>';
                            } else {
                                compSelect.innerHTML = '<option value="">Ürün Eşleşmesi Bulunmuyor!</option>';
                            }
                        },
                        error: function() {
                            compSelect.innerHTML = '<option value="">Ağaç Yüklenemedi!</option>';
	                }
	            });

	                } else if (compContainer) {
	                    compContainer.style.display = 'none';
	                    if (compSelect) compSelect.value = '';
	                }
	            });

	            $(document).on('click', '.wo-stock-card', function(e) {
	                e.preventDefault();
	                e.stopPropagation();
	                var card = $(this)[0];
	                var value = card.getAttribute('data-value');
	                var radio = card.querySelector('input[type="radio"]');
	                if (radio) radio.checked = true;
	                document.querySelectorAll('.wo-stock-card').forEach(function(c) {
	                    c.style.borderColor = '#e5e7eb';
	                    c.style.background = 'transparent';
	                });
	                card.style.borderColor = value === 'StokHaric' ? '#7c3aed' : '#16a34a';
	                card.style.background = value === 'StokHaric' ? '#f5f3ff' : '#f0fdf4';
	            });

            function getSelectedWorkOrderType() {
                var checked = document.querySelector('input[name="woType"]:checked');
                return checked ? checked.value : 'Nihai';
            }

            function getIndependentStockProductPlaceholder(tur) {
                if (tur === 'Ara') return 'Ara Mamül ara...';
                if (tur === 'HamMadde') return 'Ham Madde ara...';
                return 'Nihai Ürün ara...';
            }

	            function getSelectedBomComponent() {
	                var compSelect = document.getElementById('swalBomComponent');
	                return compSelect ? compSelect.value : null;
	            }

            function getSelectedWorkOrderStockMode() {
                var checked = document.querySelector('input[name="swalStokDurum"]:checked');
                return checked && checked.value === 'StokHaric' ? 'StokHaric' : 'StokDahil';
            }

            // ============================================================
            //   TEKLİ İŞ EMRİ VER
            // ============================================================
            async function submitSingleWorkOrder(no) {
                var order = allOrders.find(function(o) { return o.no === no; });
                var target = order;
                if (!order) {
                    // Might be a set child
                    allOrders.forEach(function(o) {
                        var setChildren = allOrders.filter(function(c) { return c.anaSetSatirNo === o.no; });
                        var child = setChildren.find(function(c) { return c.no === no; });
                        if (child) { target = child; order = o; }
                    });
                }
                var urunNo = target ? target.eslesenUrunNo : 0;

	                Swal.fire({
	                    title: 'İş Emri Seviyesi Seçin',
	                    html: buildWorkOrderTypeSelector(urunNo) + buildWorkOrderStockSelector('StokDahil'),
                    icon: 'question',
                    showCancelButton: true,
	                    confirmButtonText: '🔨 İş Emri Ver',
	                    cancelButtonText: 'Vazgeç',
	                    confirmButtonColor: '#16a34a',
	                    cancelButtonColor: '#6b7280',
	                    width: 520,
                        preConfirm: function () {
                            return {
                                tur: getSelectedWorkOrderType(),
                                altBilesenNo: getSelectedBomComponent(),
                                stokDurum: getSelectedWorkOrderStockMode()
                            };
                        }
	                }).then(async function (result) {
	                    if (!result.isConfirmed) return;
                        var selection = result.value || {};
	                    var tur = selection.tur || 'Nihai';
	                    var altBilesenNo = selection.altBilesenNo || null;
                        var stokDurum = selection.stokDurum || 'StokDahil';

                        var previewConfirmed = await window.workOrderBomPreviewConfirm({
                            mode: 'orders',
                            satirNolar: [no],
                            tur: tur,
                            altBilesenNo: altBilesenNo,
                            stokDurum: stokDurum
                        }, {
                            title: 'İş Emri Ver',
                            html: '<div style="text-align:left;">' +
                                '<p><b>' + escapeHtml((target && target.urunAdi) || 'Seçilen ürün') + '</b> için iş emri oluşturulacak.</p>' +
                                '<p style="margin:0;color:#64748b;">Stok kullanımı: <b>' + (stokDurum === 'StokHaric' ? 'Stok Hariç' : 'Stok Dahil') + '</b></p>' +
                                '</div>',
                            confirmButtonText: 'Evet, oluştur',
                            cancelButtonText: 'Vazgeç',
                            previewTitle: 'İş Emri BOM Önizlemesi',
                            previewConfirmText: 'Onayla ve oluştur',
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#6b7280'
                        });

                        if (!previewConfirmed) return;

	                    Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
	                    $.ajax({
	                        url: apiUrl + '?action=createOrderWorkOrders', type: 'POST', contentType: 'application/json',
	                        data: JSON.stringify({ satirNolar: [no], tur: tur, altBilesenNo: altBilesenNo, stokDurum: stokDurum }), dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                var errHtml = '';
                                if (res.errors && res.errors.length > 0) errHtml = '<br><small style="color:var(--z-danger);">' + res.errors.join('<br>') + '</small>';
                                Swal.fire({ icon: res.created > 0 ? 'success' : 'warning', title: res.created > 0 ? 'İş Emri Oluşturuldu!' : 'İşlem Tamamlandı', html: res.message + errHtml, confirmButtonColor: '#16a34a' });
                                loadOrders();
                            } else Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                        },
                        error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d97706' }); }
                    });
                });
            }

            // ============================================================
            //   İŞ EMRİ İPTAL ET
            // ============================================================
            function cancelWorkOrder(no) {
                Swal.fire({
                    title: 'İş Emrini İptal Et', text: 'Bu siparişin iş emri iptal edilsin mi? Görev Atama sayfasından da silinecektir.', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Evet, İptal Et', cancelButtonText: 'Vazgeç', confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'İptal ediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=cancelWorkOrder', type: 'POST', contentType: 'application/json',
                        data: JSON.stringify({ satirNo: no }), dataType: 'json',
                        success: function (res) {
                            if (res.success) { Swal.fire({ icon: 'success', title: 'İş Emri İptal Edildi', html: res.message, confirmButtonColor: '#16a34a' }); loadOrders(); }
                            else Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                        },
                        error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d97706' }); }
                    });
                });
            }

            // ============================================================
            //   TOPLU İŞ EMRİ İPTAL ET
            // ============================================================
            function cancelBulkWorkOrders() {
                if (selectedNos.size === 0) return;
                var nolar = Array.from(selectedNos);
                Swal.fire({
                    title: 'Toplu İş Emri İptal', html: '<b>' + nolar.length + '</b> seçili siparişin iş emirleri iptal edilsin mi?', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Evet, İptal Et', cancelButtonText: 'Vazgeç', confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'İptal ediliyor...', html: 'Lütfen bekleyin...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=cancelBulkWorkOrders', type: 'POST', contentType: 'application/json',
                        data: JSON.stringify({ satirNolar: nolar }), dataType: 'json',
                        success: function (res) {
                            if (res.success) { Swal.fire({ icon: 'success', title: 'Toplu İptal Tamamlandı', html: res.message, confirmButtonColor: '#16a34a' }); selectedNos.clear(); updateSelectedCount(); loadOrders(); }
                            else Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                        },
                        error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d97706' }); }
                    });
                });
            }

            // ============================================================
            //   TOPLU İŞ EMRİ VER
            // ============================================================
            function submitBulkWorkOrders() {
                if (selectedNos.size === 0) return;
                var nolar = Array.from(selectedNos);
                var selectedOrders = allOrders.filter(function(o) { return selectedNos.has(o.no); });
                var firstUrunNo = selectedOrders[0].eslesenUrunNo;
                var isSameProduct = false;
                if (firstUrunNo > 0) isSameProduct = selectedOrders.every(function(o) { return o.eslesenUrunNo === firstUrunNo; });

                var urunAdi = isSameProduct ? dbProducts.find(function(p) { return p.UrunNo == firstUrunNo; }) : null;
                var uName = urunAdi ? escapeHtml(urunAdi.UrunAdi) : '';

                var topHtml = '<p style="margin-bottom:12px;"><b>' + nolar.length + '</b> sipariş satırı' + (uName ? ' (<strong>' + uName + '</strong>)' : '') + ' için iş emri oluşturulacak.</p>';

                var surplusHtml = '';
                if (isSameProduct) {
                    surplusHtml = '<div style="margin-top:12px; padding:12px; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">' +
                        '<label style="font-size:0.85rem; color:#475569; font-weight:600;">📦 Stok İlavesi (Opsiyonel)</label>' +
                        '<input type="number" id="swalSurplus" min="0" step="1" value="0" style="width:100%; margin-top:6px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:1rem;" placeholder="Fazladan üretilecek adet">' +
                        '<p style="font-size:0.75rem; color:#94a3b8; margin-top:4px;">Üretim bandını bozmamak için stok amacıyla fazladan üretilecek adet</p>' +
                    '</div>';
                }

	                Swal.fire({
	                    title: 'İş Emri Seviyesi Seçin',
	                    html: topHtml + buildWorkOrderTypeSelector(isSameProduct ? firstUrunNo : 0) + buildWorkOrderStockSelector('StokDahil') + surplusHtml,
                    icon: 'question',
                    showCancelButton: true,
	                    confirmButtonText: '🔨 Toplu İş Emri Ver',
	                    cancelButtonText: 'Vazgeç',
	                    confirmButtonColor: '#16a34a',
	                    cancelButtonColor: '#6b7280',
	                    width: 560,
                        preConfirm: function () {
                            return {
                                tur: getSelectedWorkOrderType(),
                                altBilesenNo: getSelectedBomComponent(),
                                stokDurum: getSelectedWorkOrderStockMode(),
                                surplus: isSameProduct ? (parseInt(document.getElementById('swalSurplus').value) || 0) : 0
                            };
                        }
	                }).then(async function (result) {
	                    if (!result.isConfirmed) return;
                        var selection = result.value || {};
	                    var tur = selection.tur || 'Nihai';
	                    var altBilesenNo = selection.altBilesenNo || null;
                        var stokDurum = selection.stokDurum || 'StokDahil';
	                    var surplus = selection.surplus || 0;

                        var previewConfirmed = await window.workOrderBomPreviewConfirm({
                            mode: 'orders',
                            satirNolar: nolar,
                            surplus: surplus,
                            tur: tur,
                            altBilesenNo: altBilesenNo,
                            stokDurum: stokDurum
                        }, {
                            title: 'Toplu İş Emri Ver',
                            html: '<div style="text-align:left;">' +
                                '<p><b>' + nolar.length + '</b> seçili satır için iş emri oluşturulacak.</p>' +
                                (surplus > 0 ? '<p><b>+' + escapeHtml(surplus) + '</b> adet özel stok üretimi de eklenecek.</p>' : '') +
                                '<p style="margin:0;color:#64748b;">Stok kullanımı: <b>' + (stokDurum === 'StokHaric' ? 'Stok Hariç' : 'Stok Dahil') + '</b></p>' +
                                '</div>',
                            confirmButtonText: 'Evet, oluştur',
                            cancelButtonText: 'Vazgeç',
                            previewTitle: 'İş Emri BOM Önizlemesi',
                            previewConfirmText: 'Onayla ve oluştur',
                            confirmButtonColor: '#16a34a',
                            cancelButtonColor: '#6b7280'
                        });

                        if (!previewConfirmed) return;

	                    sendBulkWorkOrderRequest(nolar, surplus, tur, altBilesenNo, stokDurum);
	                });
	            }

	            function sendBulkWorkOrderRequest(nolar, surplusAmount, tur, altBilesenNo, stokDurum) {
                    stokDurum = stokDurum === 'StokHaric' ? 'StokHaric' : 'StokDahil';
	                var loadingText = surplusAmount > 0
                    ? nolar.length + ' sipariş ve ' + surplusAmount + ' özel stok üretimi işleniyor...'
                    : nolar.length + ' satır işleniyor...';
                Swal.fire({ title: 'İş Emirleri Oluşturuluyor...', html: loadingText, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
	                $.ajax({
	                    url: apiUrl + '?action=createOrderWorkOrders', type: 'POST', contentType: 'application/json',
	                    data: JSON.stringify({ satirNolar: nolar, surplus: surplusAmount, tur: tur || 'Nihai', altBilesenNo: altBilesenNo, stokDurum: stokDurum }), dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var errHtml = '';
                            if (res.errors && res.errors.length > 0) {
                                errHtml = '<br><br><details><summary>Hatalar (' + res.errors.length + ')</summary><ul>' +
                                    res.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('') + '</ul></details>';
                            }
                            Swal.fire({
                                icon: 'success', title: 'İş Emirleri Oluşturuldu!',
                                html: '<b>' + res.created + '</b> iş emri oluşturuldu.' +
                                    (res.surplusCreated > 0 ? '<br><span style="color:#7c3aed; font-weight:bold;">+' + res.surplusCreated + ' adet Özel Üretim (Stok) eklendi!</span>' : '') +
                                    (res.failed > 0 ? '<br><span style="color:var(--z-danger);">' + res.failed + ' hata</span>' : '') +
                                    (res.skipped > 0 ? '<br><span style="color:var(--z-text-muted);">' + res.skipped + ' atlandı (zaten verilmiş)</span>' : '') + errHtml,
                                confirmButtonColor: '#16a34a'
                            });
                            selectedNos.clear(); loadOrders();
                        } else Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                    },
                    error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d97706' }); }
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
                var kargoDate = new Date(parseInt(dateParts[2]), parseInt(dateParts[1]) - 1, parseInt(dateParts[0]));
                var now = new Date();
                var diffDays = Math.ceil((kargoDate - now) / (1000 * 60 * 60 * 24));
                if (diffDays <= 1) return 'urgency-critical';
                if (diffDays <= 3) return 'urgency-warning';
                return '';
            }

            function toggleSetChildren(parentNo) {
                var childRows = document.querySelectorAll('tr.set-child-row[data-parent="' + parentNo + '"]');
                var isOpen = false;
                childRows.forEach(function (row) {
                    row.classList.toggle('show');
                    if (row.classList.contains('show')) isOpen = true;
                });
                if (isOpen) {
                    childRows.forEach(function (row) {
                        var childNo = parseInt(row.getAttribute('data-no'));
                        var childOrder = allOrders.find(function (o) { return o.no === childNo; });
                        if (childOrder && childOrder.eslesenUrunNo > 0) {
                            var sel = row.querySelector('.match-select');
                            if (sel) { $(sel).val(childOrder.eslesenUrunTur + '_' + childOrder.eslesenUrunNo).trigger('change.select2'); }
                        }
                    });
                }
            }

            function toggleWipChildren(no) {
                var childRow = document.getElementById('wip_child_' + no);
                if (childRow) {
                    childRow.style.display = childRow.style.display === 'none' ? 'table-row' : 'none';
                }
            }

            function setupWorkOrderPopover() {
                var $body = $('#ordersBody');
                var $popover = $('#workOrderPopover');
                var triggerSelector = '.work-order-trigger, .status-detail-trigger';

                $body.on('mouseenter focusin', triggerSelector, function (event) {
                    showContextPopover(this, event);
                });

                $body.on('mouseover', triggerSelector, function (event) {
                    if (activeWorkOrderRow !== this) {
                        showContextPopover(this, event);
                    } else {
                        positionWorkOrderPopover(event, this);
                    }
                });

                $body.on('mousemove', triggerSelector, function (event) {
                    if (activeWorkOrderRow === this) {
                        positionWorkOrderPopover(event, this);
                    }
                });

                $body.on('click', triggerSelector, function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    showContextPopover(this, event);
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

                window.addEventListener('resize', hideWorkOrderPopover);
                window.addEventListener('scroll', hideWorkOrderPopover, true);
            }

            function showContextPopover(trigger, event) {
                hideAssignmentPopover();
                if (trigger.classList.contains('work-order-trigger')) {
                    showWorkOrderPopover(trigger, event);
                    return;
                }
                showStatusDetailPopover(trigger, event);
            }

            function showStatusDetailPopover(trigger, event) {
                var satirNo = parseInt(trigger.getAttribute('data-no'), 10);
                var order = findOrderByNo(satirNo);
                if (!order) return;

                activeWorkOrderRow = trigger;
                if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);

                var popover = document.getElementById('workOrderPopover');
                popover.innerHTML = renderStatusDetailPopover(order);
                popover.classList.add('visible');
                popover.setAttribute('aria-hidden', 'false');
                positionWorkOrderPopover(event, trigger);
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

            function scheduleHideWorkOrderPopover() {
                if (workOrderPopoverTimer) clearTimeout(workOrderPopoverTimer);
                workOrderPopoverTimer = setTimeout(hideWorkOrderPopover, 140);
            }

            function hideWorkOrderPopover() {
                activeWorkOrderRow = null;
                var popover = document.getElementById('workOrderPopover');
                if (!popover) return;
                popover.classList.remove('visible');
                popover.setAttribute('aria-hidden', 'true');
            }

            function positionWorkOrderPopover(event, anchor) {
                var popover = document.getElementById('workOrderPopover');
                if (!popover) return;

                var rect = anchor ? anchor.getBoundingClientRect() : null;
                var left = event && event.clientX ? event.clientX + 12 : (rect ? rect.left : 24);
                var top = event && event.clientY ? event.clientY + 14 : (rect ? rect.bottom + 8 : 24);

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

            function renderStatusDetailPopover(order) {
                var status = order.durum || '';
                var label = formatDurumLabel(status);
                var stokAdet = safeNumber(order.stokAdet);
                var adet = safeNumber(order.adet);
                var plan = isFreeStockProduction(order) ? safeNumber(order.stokUretimToplamAdet || order.adet) : adet;
                var matchText = order.eslesenUrunNo > 0
                    ? ((order.eslesenUrunTur || 'Ürün') + (order.eslesmePuani ? ' · %' + order.eslesmePuani : ''))
                    : 'Eşleşme yok';
                var title = statusDetailTitle(order);
                var summary = statusDetailSummary(order);

                return '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">Durum detayı</span>' +
                    '<div class="wo-title">' + escapeHtml(title) + '</div>' +
                    '<span class="wo-muted">' + escapeHtml(order.siparisNo || '') + '</span></div>' +
                    '<span class="wo-percent">' + escapeHtml(label) + '</span>' +
                    '</div>' +
                    '<div class="wo-status-metrics">' +
                    '<div class="wo-status-metric"><span>Adet</span><strong>' + adet + '</strong></div>' +
                    '<div class="wo-status-metric"><span>Stok</span><strong>' + stokAdet + '</strong></div>' +
                    '<div class="wo-status-metric"><span>Plan</span><strong>' + plan + '</strong></div>' +
                    '</div>' +
                    '<p class="wo-summary">' + escapeHtml(summary) + '</p>' +
                    '<div class="wo-next"><span>Eşleşme</span><strong>' + escapeHtml(matchText) + '</strong></div>' +
                    statusDetailExtraNote(order);
            }

            function renderWorkOrderLoading() {
                return '<div class="wo-popover-head">' +
                    '<div><span class="wo-eyebrow">İş emri takibi</span><div class="wo-title">Aşamalar okunuyor...</div></div>' +
                    '<span class="wo-percent">...</span>' +
                    '</div>' +
                    '<div class="wo-progress"><span style="width:35%;"></span></div>' +
                    '<p class="wo-empty">Siparişin üretimde nerede olduğunu kontrol ediyorum.</p>';
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
                    '<span class="wo-muted">' + escapeHtml(order.siparisNo || '') + '</span></div>' +
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
                        var stepClass = getWorkOrderStepClass(step.status, item.isNext);
                        var tag = getWorkOrderStepTag(step.status, item.isNext);

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
                    ? ' title="' + escapeAttr(step.tooltipLines.join('\n')) + '"'
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
                    return '<small class="wo-step-personnel" title="' + escapeAttr(personel) + '">' + escapeHtml(personel) + '</small>';
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

            function statusDetailTitle(order) {
                if (!order) return 'Durum yok';
                if (order.durum === 'UretimBekliyor') return 'İş emri bekliyor';
                if (order.durum === 'StokKarsilandi') return 'Stoktan karşılandı';
                if (order.durum === 'UretimdenKarsilaniyor') return 'Özel üretime bağlı';
                if (order.durum === 'PasifDevamEden') return 'Pasif ama üretim devam ediyor';
                if (order.durum === 'Pasif') return 'Satır pasif';
                return formatDurumLabel(order.durum);
            }

            function statusDetailSummary(order) {
                if (!order) return 'Bu satır için durum bilgisi bulunamadı.';
                if (order.durum === 'UretimBekliyor') {
                    return 'Bu satır henüz iş emrine dönmemiş. Ürün eşleşmesi ve stok bilgisi kontrol edilerek iş emri açılabilir.';
                }
                if (order.durum === 'StokKarsilandi') {
                    return 'Bu satır mevcut stoktan karşılandı olarak kapanmış görünüyor. Üretim açılmadan stok tarafında tamamlanan kayıtları gösterir.';
                }
                if (order.durum === 'UretimdenKarsilaniyor') {
                    return 'Bu satır GİED akışıyla özel üretime bağlanmış. Rezerve üretim tamamlandığında sipariş tarafı kapanır.';
                }
                if (order.durum === 'PasifDevamEden') {
                    return 'Sipariş aktif listeden çıkmış, fakat bağlı üretim kaydı devam ediyor. Üretim tamamlanana kadar takipte kalır.';
                }
                if (order.durum === 'Pasif') {
                    return 'Bu satır kapalı veya pasif durumda. Toplu iş emri işlemlerine dahil edilmez.';
                }
                return 'Bu durum için temel takip bilgileri gösteriliyor.';
            }

            function statusDetailExtraNote(order) {
                if (!order) return '';
                if (isFreeStockProduction(order)) {
                    var rootNo = order.stokUretimRootNo || order.no;
                    var ilave = safeNumber(order.stokUretimIlaveToplam);
                    return '<div class="wo-note">Ana stok üretimi #' + rootNo + (ilave > 0 ? ' · +' + ilave + ' ilave kayıt' : '') + '</div>';
                }
                if (order.musteriNotu) {
                    return '<div class="wo-note">Müşteri notu: ' + escapeHtml(order.musteriNotu) + '</div>';
                }
                return '';
            }

            function findOrderByNo(no) {
                no = parseInt(no, 10) || 0;
                return allOrders.find(function (order) {
                    return parseInt(order.no, 10) === no;
                }) || null;
            }

            function safeNumber(value) {
                var number = parseInt(value || 0, 10);
                return isNaN(number) ? 0 : number;
            }

            function getReserveAvailable(order) {
                if (!order || !Array.isArray(order.bagliSiparisler)) return 0;
                if (order.musaitAdet !== undefined && order.musaitAdet !== null) {
                    return Math.max(0, safeNumber(order.musaitAdet));
                }
                var reserved = safeNumber(order.rezerveEdilen);
                if (!reserved) {
                    reserved = order.bagliSiparisler.reduce(function(total, item) {
                        return total + safeNumber(item.adet);
                    }, 0);
                }
                return Math.max(0, safeNumber(order.adet) - reserved);
            }

            function buildReserveAvailableHtml(order) {
                var available = getReserveAvailable(order);
                var cls = available > 0 ? 'oz-musait-ok' : 'oz-musait-empty';
                var title = 'Rezerve edilebilir miktar: ' + available + ' adet';
                return '<span class="oz-musait-value ' + cls + '" title="' + escapeHtml(title) + '">' + available + '</span>';
            }

            var assignmentPopoverTimer = null;
            var assignmentPopoverActiveTrigger = null;

            function initAssignmentPopover() {
                var popover = document.getElementById('assignmentPopover');
                if (!popover || popover.getAttribute('data-ready') === '1') return;
                popover.setAttribute('data-ready', '1');

                document.addEventListener('mouseover', function (event) {
                    var trigger = event.target.closest ? event.target.closest('.oz-progress-wrap') : null;
                    if (!trigger || (event.relatedTarget && trigger.contains(event.relatedTarget))) return;
                    showAssignmentPopover(trigger);
                });

                document.addEventListener('mouseout', function (event) {
                    var trigger = event.target.closest ? event.target.closest('.oz-progress-wrap') : null;
                    if (!trigger || (event.relatedTarget && trigger.contains(event.relatedTarget))) return;
                    scheduleHideAssignmentPopover();
                });

                document.addEventListener('focusin', function (event) {
                    var trigger = event.target.closest ? event.target.closest('.oz-progress-wrap') : null;
                    if (trigger) showAssignmentPopover(trigger);
                });

                document.addEventListener('focusout', function (event) {
                    var trigger = event.target.closest ? event.target.closest('.oz-progress-wrap') : null;
                    if (trigger) scheduleHideAssignmentPopover();
                });

                popover.addEventListener('mouseenter', cancelHideAssignmentPopover);
                popover.addEventListener('mouseleave', scheduleHideAssignmentPopover);
                window.addEventListener('resize', hideAssignmentPopover);
                window.addEventListener('scroll', hideAssignmentPopover, true);
            }

            function showAssignmentPopover(trigger) {
                hideWorkOrderPopover();
                cancelHideAssignmentPopover();
                assignmentPopoverActiveTrigger = trigger;

                var popover = document.getElementById('assignmentPopover');
                if (!popover) return;

                popover.innerHTML = buildAssignmentPopoverHtml(trigger);
                popover.classList.add('visible');
                popover.setAttribute('aria-hidden', 'false');
                positionAssignmentPopover(trigger, popover);
            }

            function scheduleHideAssignmentPopover() {
                cancelHideAssignmentPopover();
                assignmentPopoverTimer = setTimeout(hideAssignmentPopover, 120);
            }

            function cancelHideAssignmentPopover() {
                if (assignmentPopoverTimer) clearTimeout(assignmentPopoverTimer);
                assignmentPopoverTimer = null;
            }

            function hideAssignmentPopover() {
                cancelHideAssignmentPopover();
                var popover = document.getElementById('assignmentPopover');
                if (!popover) return;
                popover.classList.remove('visible');
                popover.setAttribute('aria-hidden', 'true');
                assignmentPopoverActiveTrigger = null;
            }

            function positionAssignmentPopover(trigger, popover) {
                if (!trigger || !popover) return;

                var rect = trigger.getBoundingClientRect();
                var margin = 10;
                var gap = 9;
                var width = popover.offsetWidth || 290;
                var height = popover.offsetHeight || 210;
                var left = rect.left + (rect.width / 2) - (width / 2);
                var top = rect.bottom + gap;

                if (top + height > window.innerHeight - margin) {
                    top = rect.top - height - gap;
                }

                left = Math.min(window.innerWidth - width - margin, Math.max(margin, left));
                top = Math.min(window.innerHeight - height - margin, Math.max(margin, top));

                popover.style.left = left + 'px';
                popover.style.top = top + 'px';
            }

            function buildAssignmentPopoverHtml(trigger) {
                var siparisNo = trigger.getAttribute('data-siparis-no') || '';
                var durum = trigger.getAttribute('data-durum') || '';
                var siparisAdet = readNumberData(trigger, 'siparis-adet');
                var havuz = readNumberData(trigger, 'havuz');
                var personel = readNumberData(trigger, 'personel');
                var toplam = readNumberData(trigger, 'toplam');
                var ozel = readNumberData(trigger, 'ozel');
                var ghost = trigger.getAttribute('data-ghost') === '1';
                var barHedef = Math.max(siparisAdet, toplam, 1);
                var personelPct = Math.min(100, Math.round((personel / barHedef) * 100));
                var havuzPct = Math.min(100 - personelPct, Math.round((havuz / barHedef) * 100));
                var kalan = Math.max(siparisAdet - toplam, 0);
                var fazla = Math.max(toplam - siparisAdet, 0);
                var coverage = siparisAdet > 0 ? Math.round((toplam / siparisAdet) * 100) : 0;
                var coverageText = coverage > 100 ? '%100+' : '%' + coverage;
                var balanceLabel = fazla > 0 ? 'Fazla açık' : 'Kalan';
                var balanceValue = fazla > 0 ? '+' + fazla : kalan;
                var statusText = ghost ? 'Kontrol gerekli' : (fazla > 0 ? 'Fazla açık' : (toplam === siparisAdet && siparisAdet > 0 ? 'Hedef görevde' : (toplam > 0 ? 'Kısmi açık' : formatDurumLabel(durum))));
                var statusClass = ghost || fazla > 0 || (toplam > 0 && toplam < siparisAdet) ? ' warn' : '';
                var note = buildAssignmentNote(ghost, toplam, siparisAdet, havuz, personel, ozel);

                return '<div class="oz-assignment-head">' +
                        '<div><p class="oz-assignment-title">Görevlendirme</p>' +
                        '<div class="oz-assignment-sub">' + escapeHtml(siparisNo || formatDurumLabel(durum)) + '</div></div>' +
                        '<span class="oz-assignment-status' + statusClass + '">' + escapeHtml(statusText) + '</span>' +
                    '</div>' +
                    '<div class="oz-assignment-metrics">' +
                        '<div class="oz-assignment-metric"><span>Sipariş</span><strong>' + siparisAdet + '</strong></div>' +
                        '<div class="oz-assignment-metric"><span>Açık</span><strong>' + toplam + '</strong></div>' +
                        '<div class="oz-assignment-metric"><span>Oran</span><strong>' + coverageText + '</strong></div>' +
                    '</div>' +
                    '<div class="oz-assignment-mini-bar">' +
                        (personelPct > 0 ? '<span class="personel" style="width:' + personelPct + '%"></span>' : '') +
                        (havuzPct > 0 ? '<span class="havuz" style="width:' + havuzPct + '%"></span>' : '') +
                    '</div>' +
                    '<div class="oz-assignment-row"><span><i class="oz-assignment-dot personel"></i>Personelde</span><strong>' + personel + '</strong></div>' +
                    '<div class="oz-assignment-row"><span><i class="oz-assignment-dot havuz"></i>Havuzda</span><strong>' + havuz + '</strong></div>' +
                    '<div class="oz-assignment-row"><span><i class="oz-assignment-dot kalan"></i>' + balanceLabel + '</span><strong>' + balanceValue + '</strong></div>' +
                    '<div class="oz-assignment-note">' + escapeHtml(note) + '</div>';
            }

            function readNumberData(el, key) {
                return parseInt(el.getAttribute('data-' + key), 10) || 0;
            }

            function formatDurumLabel(durum) {
                var labels = {
                    'IsEmriVerildi': 'İş Emri Verildi',
                    'UretimBekliyor': 'Bekliyor',
                    'StokKarsilandi': 'Stoktan',
                    'UretimdenKarsilaniyor': 'Üretime Bağlı',
                    'PasifDevamEden': 'Devam Eden',
                    'Pasif': 'Pasif'
                };
                return labels[durum] || durum || 'Durum yok';
            }

            function buildAssignmentNote(ghost, toplam, siparisAdet, havuz, personel, ozel) {
                if (ghost) {
                    return 'İş emri verilmiş görünüyor; ancak havuz veya personel kaydı bulunmuyor.';
                }
                if (toplam <= 0) {
                    return 'Bu satır için henüz açık havuz/personel görevi görünmüyor.';
                }
                if (toplam > siparisAdet && siparisAdet > 0) {
                    return 'Açık görev sipariş adedinden fazla; stok ya da rezerv üretimi de dahil olabilir.';
                }
                if (toplam === siparisAdet) {
                    return 'Sipariş adedi kadar görev açılmış. Havuz atanmamış işi, personel ise alınmış işi gösterir.';
                }
                if (ozel > 0 && ozel < toplam) {
                    return 'Açık üretimin bir kısmı özel üretim/stok tarafına bağlı görünüyor.';
                }
                return 'Havuz atanmamış işi, personel ise alınmış veya onay bekleyen işi gösterir.';
            }

            function escapeAttr(text) {
                return escapeHtml(text).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            }

            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // ============================================================
            //   HERŞEYİ SIFIRLA
            // ============================================================
            function clearAllOrders() {
                Swal.fire({
                    title: 'Tüm Siparişleri Sil?',
                    html: '<p style="color:#d97706;">Bu işlem <strong>tüm sipariş kayıtlarını kalıcı olarak silecektir</strong>.</p><p style="color:#dc2626; font-weight:600;">Bu işlem geri alınamaz!</p>',
                    icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280', confirmButtonText: 'Evet, Herşeyi Sil', cancelButtonText: 'Vazgeç'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Son Onay', html: '<p>Devam etmek için aşağıya <strong>SIFIRLA</strong> yazın:</p>',
                            input: 'text', inputPlaceholder: 'SIFIRLA', icon: 'error',
                            showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280', confirmButtonText: 'Sil ve Sıfırla', cancelButtonText: 'Vazgeç',
                            inputValidator: function (value) { if (value !== 'SIFIRLA') return 'Lütfen SIFIRLA yazın!'; }
                        }).then(function (result2) { if (result2.isConfirmed) executeClearAll(); });
                    }
                });
            }

            function executeClearAll() {
                Swal.fire({ title: 'Siliniyor...', text: 'Tüm sipariş kayıtları temizleniyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                fetch(apiUrl + '?action=clearAllOrders', { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res.success) { Swal.fire({ icon: 'success', title: 'Sıfırlandı!', text: res.message, confirmButtonColor: '#16a34a' }).then(function () { location.reload(); }); }
                        else Swal.fire('Hata', res.message, 'error');
                    })
                    .catch(function (err) { console.error(err); Swal.fire('Hata', 'Sunucu ile iletişim hatası.', 'error'); });
            }

            // ============================================================
            //   Ürün Eşleştirme Modalı
            // ============================================================
            window.openMatchModal = function(satirNo) {
                var order = allOrders.find(function(o) { return o.no === satirNo; });
                if (!order) {
                    order = []; // Fallback for child rows
                    allOrders.forEach(function(o) {
                        if (o.setMi) {
                            var children = allOrders.filter(function (child) { return child.anaSetSatirNo === o.no; });
                            children.forEach(function(c) { if(c.no === satirNo) order = c; });
                        }
                    });
                }
                if (!order || order.length === 0) return;

                var optionsHtml = '<option value="">Eşleşme yok (Manuel Seçim)</option>';
                globalProductList.forEach(function (p) {
                    optionsHtml += '<option value="Nihai_' + p.no + '">🏭 Nihai: ' + escapeHtml(p.urunID || p.sistemAdi) + '</option>';
                });
                globalAraMamuls.forEach(function (a) {
                    optionsHtml += '<option value="Ara_' + a.no + '">🔧 Ara: ' + escapeHtml(a.ad) + '</option>';
                });
                globalHammadde.forEach(function (h) {
                    optionsHtml += '<option value="HamMadde_' + h.no + '">🪵 Ham: ' + escapeHtml(h.ad) + '</option>';
                });

                var html = '<div style="text-align:left; margin-top:10px;">' +
                           '<label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:4px;">Veritabanında Ürün Ara</label>' +
                           '<select id="swalAppMatchSelect" style="width:100%;">' + optionsHtml + '</select>' +
                           '<div style="font-size:0.75rem; color:#64748b; margin-top:8px;">Aramak için yazın...</div></div>';

                Swal.fire({
                    title: 'Ürün Eşleştir',
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: 'Kaydet',
                    cancelButtonText: 'İptal',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#64748b',
                    didOpen: function() {
                        $('#swalAppMatchSelect').select2({
                            dropdownParent: $('.swal2-container'),
                            placeholder: 'Ürün arayın...'
                        });
                        if (order.eslesenUrunNo > 0 && order.eslesenUrunTur) {
                            $('#swalAppMatchSelect').val(order.eslesenUrunTur + '_' + order.eslesenUrunNo).trigger('change');
                        }
                    }
                }).then(function(result) {
                    if (result.isConfirmed) {
                        var val = $('#swalAppMatchSelect').val();
                        if (!val) return;
                        var parts = val.split('_');
                        Swal.fire({ title: 'Eşleştiriliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=matchProduct',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ siparisSatirNo: satirNo, eslesenUrunNo: parseInt(parts[1]), eslesenUrunTur: parts[0] }),
                            dataType: 'json',
                            success: function (res) {
                                if(res.success) {
                                    var infoHtml = '<div style="text-align:left; font-size:0.9rem;">' +
                                        '<div style="background:rgba(16,185,129,0.08); border-radius:10px; padding:12px; margin-bottom:10px;">' +
                                        '<strong>📋 Önbelleğe Kaydedildi!</strong></div>' +
                                        '<p><strong>Pazaryeri Adı:</strong><br><span style="color:#7c3aed;">' + (res.cachedExcelName || '-') + '</span></p>' +
                                        '<p><strong>Sistem Ürünü:</strong><br><span style="color:#16a34a;">' + (res.cachedProductName || '-') + '</span></p>';

                                    if (res.updatedCount > 0) {
                                        infoHtml += '<div style="background:rgba(245,158,11,0.08); border-radius:8px; padding:8px; margin-top:8px;">' +
                                            '⚡ <strong>' + res.updatedCount + '</strong> sipariş daha otomatik eşleştirildi.</div>';
                                    }
                                    infoHtml += '<p style="margin-top:10px; font-size:0.8rem; color:var(--z-text-muted);">Bu eşleştirme kalıcıdır.</p></div>';

                                    Swal.fire({ icon: 'success', title: 'Eşleştirme Kaydedildi!', html: infoHtml, confirmButtonColor: '#7c3aed', confirmButtonText: 'Tamam' });
                                    setTimeout(function () { loadOrders(); }, 800);
                                } else {
                                    Swal.fire('Hata', res.message, 'error');
                                }
                            }
                        });
                    }
                });
            }

            // ============================================================
            //   SERBEST STOK ÜRETİMİ MODALI
            // ============================================================
            function openStockProductionAdditionModal(no) {
                var order = allOrders.find(function (o) { return parseInt(o.no, 10) === parseInt(no, 10); });
                if (!order || !canAddStockProduction(order)) {
                    Swal.fire('Uyarı', 'Bu satır için ilave stok üretimi açılamaz.', 'warning');
                    return;
                }

                var planlanan = order.stokUretimToplamAdet || order.adet || 0;
                var ilaveToplam = order.stokUretimIlaveToplam || 0;
                var rootNo = order.stokUretimRootNo || order.no;
                var html = '<div style="text-align:left; margin-top:12px;">' +
                    '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; margin-bottom:14px;">' +
                        '<div style="font-size:0.78rem; color:#64748b; font-weight:700; text-transform:uppercase;">Ana Stok Üretimi #' + rootNo + '</div>' +
                        '<div style="font-size:1rem; color:#111827; font-weight:800; margin-top:4px;">' + escapeHtml(order.urunAdi || '') + '</div>' +
                        '<div style="font-size:0.82rem; color:#64748b; margin-top:5px;">Mevcut plan: <b>' + planlanan + '</b> adet' +
                            (ilaveToplam > 0 ? ' · Önceki ilave: <b>+' + ilaveToplam + '</b> adet' : '') +
                        '</div>' +
                    '</div>' +

                    '<label style="display:block; font-size:0.85rem; font-weight:700; color:#475569; margin-bottom:4px;">İlave Edilecek Adet</label>' +
                    '<input type="number" id="swalStockAdditionCount" min="1" step="1" value="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:1rem;">' +

                    '<div style="margin-top:16px;">' + buildWorkOrderStockSelector('StokDahil') + '</div>' +

                    '<div style="margin-top:16px;">' +
                        '<label style="display:block; font-size:0.85rem; font-weight:700; color:#475569; margin-bottom:4px;">Not / Açıklama (Opsiyonel)</label>' +
                        '<textarea id="swalStockAdditionNote" rows="2" placeholder="Örn: Ek proje talebi, showroom stoğu..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem; resize:vertical;"></textarea>' +
                    '</div>' +
                '</div>';

                Swal.fire({
                    title: '+ İlave Stok Üretimi',
                    html: html,
                    width: 560,
                    customClass: { popup: 'oz-stock-modal' },
                    showCancelButton: true,
                    confirmButtonText: 'Devam Et',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#7c3aed',
                    cancelButtonColor: '#6b7280',
                    preConfirm: function () {
                        var adet = parseInt(document.getElementById('swalStockAdditionCount').value, 10) || 0;
                        if (adet <= 0) {
                            Swal.showValidationMessage('Lütfen 1 veya daha büyük bir ilave adet girin.');
                            return false;
                        }

                        return {
                            adet: adet,
                            stokDurum: getSelectedWorkOrderStockMode(),
                            note: document.getElementById('swalStockAdditionNote').value || ''
                        };
                    }
                }).then(async function (result) {
                    if (!result.isConfirmed) return;

                    var selection = result.value || {};
                    var adet = parseInt(selection.adet, 10) || 0;
                    var stokDurum = selection.stokDurum || 'StokDahil';
                    var tur = stockProductionPreviewType(order);

                    var previewConfirmed = await window.workOrderBomPreviewConfirm({
                        mode: 'manual',
                        urunNo: parseInt(order.eslesenUrunNo, 10),
                        adet: adet,
                        tur: tur,
                        stokDurum: stokDurum
                    }, {
                        title: '+ İlave Stok Üretimi',
                        html: '<div style="text-align:left;">' +
                            '<p><b>' + escapeHtml(order.urunAdi || 'Seçilen ürün') + '</b> için <b>+' + adet + '</b> adet ilave iş emri açılacak.</p>' +
                            '<p style="margin:0;color:#64748b;">Ana stok üretimi: <b>#' + rootNo + '</b> · Stok kullanımı: <b>' + (stokDurum === 'StokHaric' ? 'Stok Hariç' : 'Stok Dahil') + '</b></p>' +
                            '</div>',
                        confirmButtonText: 'Evet, ilave et',
                        cancelButtonText: 'Vazgeç',
                        previewTitle: 'İş Emri BOM Önizlemesi',
                        previewConfirmText: 'Onayla ve oluştur',
                        confirmButtonColor: '#7c3aed',
                        cancelButtonColor: '#6b7280'
                    });

                    if (!previewConfirmed) return;

                    Swal.fire({ title: 'İlave İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=addIndependentStockOrder',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            parentNo: order.no,
                            adet: adet,
                            stokDurum: stokDurum,
                            aciklama: selection.note || ''
                        }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'İlave Oluşturuldu!', text: res.message, confirmButtonColor: '#16a34a' });
                                loadOrders();
                            } else {
                                Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                            }
                        },
                        error: function (xhr) {
                            Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d97706' });
                        }
                    });
                });
            }

            function openIndependentStockModal() {
                var html = '<div style="text-align:left; margin-top:12px;">' +
                    '<label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:4px;">Üretilecek Ürün / Ara Mamül</label>' +
                    '<select id="swalIndStockProduct" style="width:100%;"><option value="">Seçiniz...</option></select>' +

	                    '<div style="margin-top:16px;"></div>' + buildWorkOrderTypeSelector(0) + buildWorkOrderStockSelector('StokDahil') +

                    '<div style="margin-top:16px;">' +
                        '<label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:4px;">Adet</label>' +
                        '<input type="number" id="swalIndStockCount" min="1" step="1" value="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:1rem;">' +
                    '</div>' +

                    '<div style="margin-top:16px;">' +
                        '<label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:4px;">Not / Açıklama (Opsiyonel)</label>' +
                        '<input type="text" id="swalIndStockNote" placeholder="Örn: Fabrika İçi Stok İçin..." style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' +
                    '</div>' +
                '</div>';

                Swal.fire({
                    title: 'Yeni Serbest Stok Üretimi',
                    html: html,
                    width: 560,
                    customClass: { popup: 'oz-stock-modal' },
	                    showCancelButton: true,
	                    confirmButtonText: '🔨 İş Emri Ver',
	                    cancelButtonText: 'İptal',
	                    confirmButtonColor: '#7c3aed',
                        preConfirm: function () {
                            var productData = $('#swalIndStockProduct').select2('data') || [];
                            return {
                                urunNo: $('#swalIndStockProduct').val(),
                                tur: getSelectedWorkOrderType(),
                                adet: document.getElementById('swalIndStockCount').value,
                                note: document.getElementById('swalIndStockNote').value,
                                altBilesenNo: getSelectedBomComponent(),
                                stokDurum: getSelectedWorkOrderStockMode(),
                                productText: productData[0] ? productData[0].text : ''
                            };
                        },
	                    didOpen: function() {
                        var select = $('#swalIndStockProduct');
                        select.data('selected-type', getSelectedWorkOrderType());
                        select.select2({
                            dropdownParent: $('.swal2-container'),
                            ajax: {
                                url: apiUrl + '?action=getAraUrunler',
                                dataType: 'json',
                                delay: 250,
                                data: function (params) { return { search: params.term, tur: getSelectedWorkOrderType() }; },
                                processResults: function (data) {
                                    return {
                                        results: (data.data || []).map(function(item) {
                                            // The backend returns item.No and item.AraUrunAdi
                                            return { id: item.No, text: item.AraUrunAdi };
                                        })
                                    };
                                }
                            },
                            placeholder: getIndependentStockProductPlaceholder(getSelectedWorkOrderType()),
                            minimumInputLength: 2,
                            language: { inputTooShort: function() { return "En az 2 harf girin..."; }, searching: function() { return "Aranıyor..."; }, noResults: function() { return "Kayıt bulunamadı"; } }
                        });

                        // SweetAlert2 steals focus from Select2 search box. Need to remove tabindex.
                        var swalPopup = document.querySelector('.swal2-popup');
                        if (swalPopup) {
                            swalPopup.removeAttribute('tabindex');
                        }

                        select.on('change', function() {
                            var val = $(this).val();
                            if (val) {
                                // Update all card data-urunno attributes so BOM loading works
                                document.querySelectorAll('.wo-type-card').forEach(function(card) {
                                    card.setAttribute('data-urunno', val);
                                });

                                // Ensure bomComponentSelectorContainer exists
                                var compWrapper = document.getElementById('bomComponentSelectorContainer');
                                if (!compWrapper) {
                                    var lastCard = document.querySelectorAll('.wo-type-card');
                                    if (lastCard.length > 0) {
                                        $(lastCard[lastCard.length - 1].parentNode).after(
                                            '<div id="bomComponentSelectorContainer" style="display:none; text-align:left; margin-top:12px; padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">' +
                                            '<label style="font-size:0.85rem; color:#475569; font-weight:600;">Belirli Bir Alt Bileşeni Seçin (Opsiyonel)</label>' +
                                            '<select id="swalBomComponent" style="width:100%; margin-top:6px; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:0.9rem;">' +
                                            '</select></div>'
                                        );
                                    }
                                }

                                // Re-trigger current selection's BOM load if Ara or HamMadde is selected
                                var checkedRadio = document.querySelector('input[name="woType"]:checked');
                                if (checkedRadio && (checkedRadio.value === 'Ara' || checkedRadio.value === 'HamMadde')) {
                                    var card = checkedRadio.closest('.wo-type-card');
                                    if (card) $(card).trigger('click');
                                }
                            }
                        });
                    }
	                }).then(async function(result) {
	                    if(result.isConfirmed) {
                            var selection = result.value || {};
	                        var urunNo = selection.urunNo;
	                        if(!urunNo) {
	                            Swal.fire('Hata', 'Lütfen bir ürün seçin!', 'warning');
	                            return;
	                        }
	                        var tur = selection.tur || 'Nihai';
	                        var adet = selection.adet || 1;
	                        var note = selection.note || '';
	                        var altBilesenNo = selection.altBilesenNo || null;
                            var stokDurum = selection.stokDurum || 'StokDahil';

                        if(altBilesenNo && parseInt(altBilesenNo) > 0) {
                            urunNo = altBilesenNo;
                            tur = (tur === 'Ara' ? 'Ara' : tur);
                        }

                        var previewConfirmed = await window.workOrderBomPreviewConfirm({
                            mode: 'manual',
                            urunNo: parseInt(urunNo, 10),
                            adet: parseInt(adet, 10) || 1,
                            tur: tur,
                            stokDurum: stokDurum
                        }, {
                            title: 'Serbest Stok Üretimi',
                            html: '<div style="text-align:left;">' +
                                '<p><b>' + escapeHtml(selection.productText || 'Seçilen ürün') + '</b> için ' + escapeHtml(adet) + ' adet stok üretimi açılacak.</p>' +
                                '<p style="margin:0;color:#64748b;">Stok kullanımı: <b>' + (stokDurum === 'StokHaric' ? 'Stok Hariç' : 'Stok Dahil') + '</b></p>' +
                                '</div>',
                            confirmButtonText: 'Evet, oluştur',
                            cancelButtonText: 'Vazgeç',
                            previewTitle: 'İş Emri BOM Önizlemesi',
                            previewConfirmText: 'Onayla ve oluştur',
                            confirmButtonColor: '#7c3aed',
                            cancelButtonColor: '#6b7280'
                        });
                        if (!previewConfirmed) return;

                        Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
	                        $.ajax({
	                            url: apiUrl + '?action=createIndependentStockOrder', type: 'POST', contentType: 'application/json',
	                            data: JSON.stringify({ urunNo: urunNo, adet: adet, tur: tur, aciklama: note, stokDurum: stokDurum }), dataType: 'json',
                            success: function(res) {
                                if(res.success) {
                                    Swal.fire({ icon: 'success', title: 'Oluşturuldu!', text: res.message, confirmButtonColor: '#16a34a' });
                                    loadOrders();
                                } else {
                                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                                }
                            },
                            error: function(xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText, confirmButtonColor: '#d97706' }); }
                        });
                    }
                });
            }

            // ============================================================
            //   OTO EŞLEŞTİR
            // ============================================================
            function rematchOrders() {
                Swal.fire({
                    icon: 'question', title: 'Oto Eşleştir',
                    html: 'Eşleşmemiş tüm siparişler güncel ürün veritabanına karşı tekrar eşleştirilecek.<br><br>Devam etmek istiyor musunuz?',
                    showCancelButton: true, confirmButtonText: '✅ Evet, Eşleştir', cancelButtonText: '❌ İptal', confirmButtonColor: '#7c3aed', cancelButtonColor: '#6b7280', reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Eşleştiriliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=rematchOrders', type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({}), dataType: 'json',
                            success: function (res) {
                                if (res.success) {
                                    Swal.fire({ icon: 'success', title: 'Eşleştirme Tamamlandı!', html: '<b>' + res.matched + '</b> sipariş yeni eşleştirildi.<br><b>' + res.total + '</b> eşleşmemiş sipariş kontrol edildi.', confirmButtonColor: '#7c3aed' });
                                    loadOrders();
                                } else Swal.fire({ icon: 'error', title: 'Hata', text: res.message, confirmButtonColor: '#d97706' });
                            },
                            error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText || 'Bağlantı hatası', confirmButtonColor: '#d97706' }); }
                        });
                    }
                });
            }
        </script>
@endsection
