@extends('layouts.app')

@section('title', 'Üretim Planlama')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadCurrentPlanningTab()">
        <i class="bi bi-arrow-repeat me-1"></i>Yenile
    </button>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadPersonelList()">
        <i class="bi bi-people me-1"></i>Personel
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .planning-legacy-shell {
        display: grid;
        gap: 26px;
    }

    .planning-workbench {
        padding: 28px 0 0;
    }

    .planning-headline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 14px;
    }

    .planning-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
        color: #4a3424;
        font-size: 1.8rem;
        font-weight: 700;
        letter-spacing: 0;
    }

    .planning-title i {
        color: #6f5436;
        font-size: 1.35rem;
    }

    .planning-status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 6px 12px;
        border: 1px solid var(--z-border);
        border-radius: 6px;
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .planning-status-badge.success {
        border-color: rgba(5, 150, 105, 0.18);
        background: var(--z-success-soft);
        color: var(--z-success);
    }

    .planning-status-badge.warning {
        border-color: rgba(217, 119, 6, 0.18);
        background: var(--z-warning-soft);
        color: var(--z-warning);
    }

    .planning-status-badge.danger {
        border-color: rgba(220, 38, 38, 0.18);
        background: var(--z-danger-soft);
        color: var(--z-danger);
    }

    .planning-controls {
        display: grid;
        grid-template-columns: minmax(220px, 300px) minmax(160px, 200px) minmax(150px, 220px) auto;
        gap: 18px;
        align-items: center;
        margin-bottom: 28px;
    }

    .planning-controls .form-select,
    .planning-controls .form-control,
    .planning-controls .btn {
        min-height: 46px;
        font-size: 1rem;
    }

    /* ───── Özellik #2: Aranabilir Personel Dropdown ───── */
    .pss-wrap { position: relative; min-width: 0; }
    .pss-display {
        display: flex;
        align-items: center;
        gap: 8px;
        min-height: 46px;
        padding: 8px 14px;
        border: 1.5px solid var(--z-border-light);
        border-radius: 8px;
        background: var(--z-bg);
        cursor: pointer;
        transition: border-color 0.2s, box-shadow 0.2s;
        font-size: 0.95rem;
    }
    .pss-display:hover, .pss-display:focus { border-color: var(--z-accent); }
    .pss-display.is-open { border-color: var(--z-accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); border-radius: 8px 8px 0 0; }
    .pss-icon { color: var(--z-text-secondary); font-size: 1.1rem; flex-shrink: 0; }
    .pss-text { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--z-text); font-weight: 600; }
    .pss-text.is-placeholder { color: var(--z-text-secondary); font-weight: 500; }
    .pss-badge { font-size: 0.65rem; padding: 2px 7px; border-radius: 10px; background: var(--z-accent); color: #fff; font-weight: 700; flex-shrink: 0; }
    .pss-arrow { color: var(--z-text-secondary); font-size: 0.8rem; flex-shrink: 0; transition: transform 0.2s; }
    .pss-display.is-open .pss-arrow { transform: rotate(180deg); }
    .pss-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0; right: 0;
        z-index: 100;
        background: var(--z-bg);
        border: 1.5px solid var(--z-accent);
        border-top: none;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 12px 32px rgba(0,0,0,0.12);
        max-height: 320px;
        overflow: hidden;
        display: none;
        flex-direction: column;
    }
    .pss-dropdown.is-open { display: flex; }
    .pss-search-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--z-border-light);
    }
    .pss-search-wrap i { color: var(--z-text-secondary); font-size: 0.9rem; }
    .pss-search {
        flex: 1;
        border: none;
        background: transparent;
        font-size: 0.88rem;
        color: var(--z-text);
        outline: none;
    }
    .pss-list { overflow-y: auto; max-height: 260px; }
    .pss-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 14px;
        cursor: pointer;
        transition: background 0.1s;
        border-bottom: 1px solid rgba(0,0,0,0.03);
    }
    .pss-item:hover, .pss-item.is-focused { background: rgba(99,102,241,0.06); }
    .pss-item.is-selected { background: rgba(99,102,241,0.1); font-weight: 700; }
    .pss-item-name { flex: 1; font-size: 0.88rem; color: var(--z-text); font-weight: 600; }
    .pss-item-dept { font-size: 0.7rem; color: var(--z-text-secondary); background: var(--z-bg-soft); padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
    .pss-item-tasks { font-size: 0.68rem; color: var(--z-accent); font-weight: 700; white-space: nowrap; }
    .pss-empty { padding: 16px; text-align: center; color: var(--z-text-secondary); font-size: 0.85rem; }

    /* ───── Özellik #1: Inline Adet Düzenleme ───── */
    .planning-qty-editable {
        cursor: pointer;
        padding: 1px 4px;
        border-radius: 4px;
        transition: background 0.15s;
        display: inline;
    }
    .planning-qty-editable:hover {
        background: rgba(99,102,241,0.1);
        text-decoration: underline dotted;
    }
    .planning-qty-input {
        width: 60px;
        padding: 2px 6px;
        border: 1.5px solid var(--z-accent);
        border-radius: 4px;
        font-size: 0.95rem;
        font-weight: 800;
        text-align: center;
        background: #fff;
        color: var(--z-text);
        outline: none;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }

    /* ───── Özellik #7: Görev Geçmişi Timeline ───── */
    .planning-timeline { padding: 4px 0; text-align: left; }
    .planning-tl-item {
        display: flex;
        gap: 12px;
        position: relative;
        padding-bottom: 16px;
    }
    .planning-tl-item:last-child { padding-bottom: 0; }
    .planning-tl-item::before {
        content: '';
        position: absolute;
        left: 13px;
        top: 28px;
        bottom: 0;
        width: 2px;
        background: var(--z-border-light);
    }
    .planning-tl-item:last-child::before { display: none; }
    .planning-tl-dot {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 0.78rem;
        color: #fff;
        z-index: 1;
    }
    .planning-tl-body { flex: 1; min-width: 0; }
    .planning-tl-title { font-size: 0.82rem; font-weight: 700; color: var(--z-text); line-height: 1.3; }
    .planning-tl-detail { font-size: 0.75rem; color: var(--z-accent); font-weight: 700; margin-top: 1px; }
    .planning-tl-meta { font-size: 0.7rem; color: var(--z-text-secondary); margin-top: 2px; }
    .planning-tl-empty { text-align: center; padding: 20px; color: var(--z-text-secondary); font-size: 0.85rem; }

    /* ───── Özellik #4: Havuz Kenar Çubuğu ───── */
    .pool-sidebar {
        position: fixed;
        top: 0; right: -400px;
        width: 380px; height: 100vh;
        background: var(--z-bg);
        box-shadow: -4px 0 24px rgba(0,0,0,0.1);
        z-index: 1050;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
    }
    .pool-sidebar.is-open { right: 0; }
    .pool-sidebar-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--z-border-light);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(249,250,251,0.5);
    }
    .pool-sidebar-title { font-size: 1.1rem; font-weight: 800; color: var(--z-text); display: flex; align-items: center; gap: 8px; }
    .pool-sidebar-close {
        background: transparent; border: none; font-size: 1.2rem;
        color: var(--z-text-secondary); cursor: pointer; padding: 4px; border-radius: 6px;
    }
    .pool-sidebar-close:hover { background: var(--z-bg-soft); color: var(--z-text); }
    .pool-sidebar-body {
        flex: 1; overflow-y: auto; padding: 16px; background: var(--z-bg-soft);
    }
    .pool-card {
        background: #fff;
        border: 1px solid var(--z-border-light);
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 12px;
        cursor: grab;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    }
    .pool-card:active { cursor: grabbing; }
    .pool-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: var(--z-accent-soft); }
    .pool-card.is-dragging { opacity: 0.5; border-style: dashed; }
    .pool-card-title { font-size: 0.9rem; font-weight: 700; color: var(--z-text); margin-bottom: 6px; line-height: 1.2; }
    .pool-card-meta { display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--z-text-secondary); }
    .pool-card-qty { font-weight: 800; color: var(--z-accent); }
    .pool-card-actions { display: flex; justify-content: flex-end; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--z-border-light); }
    .pool-assign-btn { font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; }

    .pool-toggle-btn {
        position: fixed;
        bottom: 30px; right: 30px;
        width: 56px; height: 56px;
        border-radius: 50%;
        background: var(--z-accent);
        color: #fff;
        border: none;
        box-shadow: 0 4px 16px rgba(99,102,241,0.4);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        z-index: 1040;
        transition: transform 0.2s, background 0.2s;
    }
    .pool-toggle-btn:hover { transform: scale(1.05); background: #4f46e5; }
    .pool-toggle-badge {
        position: absolute; top: -2px; right: -2px;
        background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 800;
        padding: 2px 6px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .pool-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.4); z-index: 1045;
        opacity: 0; pointer-events: none; transition: opacity 0.3s;
    }
    .pool-overlay.is-open { opacity: 1; pointer-events: auto; }

    .planning-view-switch {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 22px;
    }

    .planning-view-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        min-height: 38px;
        padding: 7px 13px;
        border: 1px solid var(--z-border);
        border-radius: 6px;
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        font-weight: 700;
        font-size: 0.86rem;
    }

    .planning-view-btn.active {
        border-color: var(--z-accent);
        background: var(--z-accent);
        color: #fff;
    }

    .planning-section-title {
        margin: 0 0 16px;
        color: #4a3424;
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: 0;
    }

    .planning-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
        color: var(--z-text-secondary);
        font-size: 0.84rem;
    }

    /* ───── Özellik #9: Personel İş Yükü Özet Çubuğu ───── */
    .planning-workload-bar {
        display: none;
        align-items: stretch;
        gap: 0;
        margin-bottom: 18px;
        border: 1px solid var(--z-border-light);
        border-radius: 10px;
        background: var(--z-bg-soft);
        overflow: hidden;
    }
    .planning-workload-bar.is-visible { display: flex; }

    .planning-workload-stat {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 10px 8px;
        border-right: 1px solid var(--z-border-light);
        gap: 2px;
    }
    .planning-workload-stat:last-child { border-right: none; }
    .planning-workload-stat .wl-value {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--z-text);
        line-height: 1.2;
    }
    .planning-workload-stat .wl-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--z-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .planning-workload-stat .wl-value.is-ready { color: #059669; }
    .planning-workload-stat .wl-value.is-waiting { color: #dc2626; }
    .planning-workload-stat .wl-value.is-overdue { color: #b91c1c; }

    .planning-workload-progress {
        width: 100%;
        padding: 0 14px 10px;
    }
    .planning-workload-track {
        width: 100%;
        height: 7px;
        border-radius: 4px;
        background: #e5e7eb;
        overflow: hidden;
    }
    .planning-workload-fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, #059669, #10b981);
        transition: width 0.5s ease;
    }

    .planning-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 28px;
        padding: 4px 9px;
        border-radius: 6px;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        font-size: 0.76rem;
        font-weight: 700;
    }

    .planning-board-wrapper {
        overflow-x: auto;
        padding-bottom: 12px;
        margin-right: -20px; /* Kenar boşluklarını telafi et */
        padding-right: 20px;
    }

    .planning-board {
        display: grid;
        grid-template-columns: repeat(4, minmax(230px, 1fr));
        gap: 26px 34px;
        align-items: start;
        min-width: min-content; /* Grid'in scroll içinde sıkışmasını engeller */
    }

    /* ───── Özellik #6: Haftalık Takvim Görünümü ───── */
    .planning-board.is-weekly-view {
        grid-template-columns: repeat(7, minmax(240px, 1fr));
        gap: 16px 20px;
    }
    .planning-board.is-weekly-view .planning-date-column {
        min-height: 340px; /* Haftalıkta biraz daha uzun olabilir */
    }

    /* ───── Özellik #3: Çoklu Personel Planlama Board'u ───── */
    .multi-board-wrapper {
        overflow-x: auto;
        padding-bottom: 20px;
        margin-right: -20px; padding-right: 20px;
    }
    .multi-board {
        display: flex;
        gap: 20px;
        align-items: flex-start;
        min-width: min-content;
    }
    .multi-col {
        width: 320px; /* Sütun genişliği */
        flex-shrink: 0;
        background: rgba(249,250,251,0.5);
        border: 1px solid var(--z-border-light);
        border-radius: 8px;
        padding: 16px;
        transition: border-color 0.2s, background 0.2s;
    }
    .multi-col.is-drop-target {
        border-color: var(--z-accent);
        background: rgba(99,102,241,0.05);
    }
    .multi-col-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 16px; padding-bottom: 12px;
        border-bottom: 2px solid var(--z-border-light);
    }
    .multi-col-title { font-weight: 800; color: var(--z-text); font-size: 1rem; }
    .multi-col-badge {
        background: var(--z-accent-soft); color: var(--z-accent);
        padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 800;
    }
    .multi-task-list {
        display: flex; flex-direction: column; gap: 12px; min-height: 150px;
    }

    .planning-date-column {
        min-height: 264px;
        padding: 18px 20px 20px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        background: #fff;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
    }

    .planning-date-column.is-drop-target {
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    /* ───── Özellik #5: Bugün / Gecikme Vurgusu ───── */
    .planning-date-column.is-today {
        border: 2px solid #3b82f6;
        background: rgba(59, 130, 246, 0.03);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.08);
    }
    .planning-date-column.is-today .planning-date-label {
        color: #1d4ed8;
        border-bottom-color: #93c5fd;
    }
    .planning-date-column.is-overdue {
        border-color: #fca5a5;
        background: rgba(239, 68, 68, 0.02);
    }
    .planning-date-column.is-overdue .planning-date-label {
        color: #b91c1c;
    }
    .planning-date-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        text-transform: uppercase;
    }
    .planning-date-badge.is-today {
        background: #dbeafe;
        color: #1d4ed8;
    }
    .planning-date-badge.is-overdue {
        background: #fee2e2;
        color: #b91c1c;
        animation: pulse-soft 2s ease-in-out infinite;
    }
    .planning-date-badge.is-future {
        background: #f0fdf4;
        color: #059669;
    }
    @keyframes pulse-soft {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    .planning-date-header {
        display: grid;
        gap: 8px;
        margin-bottom: 14px;
        text-align: center;
    }

    .planning-date-label {
        padding-bottom: 9px;
        border-bottom: 1px solid var(--z-border);
        color: #2d2f33;
        font-size: 1.22rem;
        font-weight: 800;
        line-height: 1.15;
    }

    .planning-date-stats {
        display: flex;
        justify-content: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .planning-task-list {
        display: grid;
        gap: 12px;
    }

    .planning-task-card {
        display: grid;
        gap: 10px;
        min-height: 104px;
        padding: 13px 14px;
        border: 1px solid rgba(17, 24, 39, 0.08);
        border-radius: 5px;
        background: #f8fafc;
        color: #111827;
        cursor: grab;
        transition: transform 0.15s ease, box-shadow 0.15s ease, opacity 0.15s ease, border-color 0.15s ease;
    }

    .planning-task-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(17, 24, 39, 0.12);
    }

    .planning-task-card.is-available,
    .planning-task-card.is-ready {
        border-color: rgba(5, 150, 105, 0.42);
        background: #d1fae5;
    }

    .planning-task-card.is-blocked,
    .planning-task-card.is-waiting {
        border-color: rgba(220, 38, 38, 0.42);
        background: #fee2e2;
    }

    .planning-task-card.is-active {
        border-color: rgba(217, 119, 6, 0.42);
        background: #fef3c7;
    }

    .planning-task-card.is-neutral {
        border-color: rgba(107, 114, 128, 0.24);
        background: #f3f4f6;
    }

    .planning-task-card.is-dragging {
        opacity: 0.55;
    }

    .planning-task-copy {
        min-width: 0;
        font-size: 0.98rem;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .planning-task-copy strong {
        font-weight: 800;
    }

    .planning-task-status {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        gap: 6px;
        min-height: 26px;
        padding: 4px 8px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.72);
        color: #111827;
        font-size: 0.74rem;
        font-weight: 800;
    }

    .planning-task-card.is-available .planning-task-status,
    .planning-task-card.is-ready .planning-task-status {
        color: #047857;
    }

    .planning-task-card.is-blocked .planning-task-status,
    .planning-task-card.is-waiting .planning-task-status {
        color: #b91c1c;
    }

    .planning-task-card.is-active .planning-task-status {
        color: #b45309;
    }

    .planning-task-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 6px;
    }

    .planning-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border: 1px solid rgba(17, 24, 39, 0.14);
        border-radius: 5px;
        background: rgba(255, 255, 255, 0.8);
        color: #111827;
        transition: transform 0.12s ease, background 0.12s ease;
    }

    .planning-icon-btn:hover {
        transform: translateY(-1px);
        background: #fff;
    }

    .planning-icon-btn:disabled {
        cursor: not-allowed;
        opacity: 0.42;
        transform: none;
    }

    .planning-icon-btn.is-info {
        color: var(--z-accent);
    }

    .planning-icon-btn.is-transfer {
        color: #d97706;
    }
    .planning-icon-btn.is-transfer:hover {
        background: #fef3c7;
        color: #b45309;
    }

    .planning-icon-btn.is-pool-return {
        color: #6366f1;
    }
    .planning-icon-btn.is-pool-return:hover {
        background: #eef2ff;
        color: #4338ca;
    }

    /* Aktarma popup stilleri */
    .transfer-modal { text-align: left; color: var(--z-text); }
    .transfer-task-info {
        background: var(--z-bg-soft);
        border: 1px solid var(--z-border-light);
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 16px;
    }
    .transfer-task-info .transfer-info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 0.85rem;
    }
    .transfer-task-info .transfer-info-row .transfer-label {
        color: var(--z-text-secondary);
        font-weight: 500;
    }
    .transfer-task-info .transfer-info-row .transfer-value {
        font-weight: 700;
        color: var(--z-text);
    }
    .transfer-personnel-select {
        width: 100%;
        padding: 10px 12px;
        border: 1.5px solid var(--z-border-light);
        border-radius: 8px;
        font-size: 0.9rem;
        background: var(--z-bg);
        color: var(--z-text);
        cursor: pointer;
        transition: border-color 0.2s;
    }
    .transfer-personnel-select:focus {
        outline: none;
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
    }
    .transfer-section-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--z-text-secondary);
        margin-bottom: 6px;
    }
    .transfer-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 14px 0;
        font-size: 0.78rem;
        color: var(--z-text-secondary);
        font-weight: 600;
        text-transform: uppercase;
    }
    .transfer-divider::before,
    .transfer-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--z-border-light);
    }
    .transfer-pool-btn {
        width: 100%;
        padding: 10px;
        border: 1.5px solid var(--z-border-light);
        border-radius: 8px;
        background: var(--z-bg-soft);
        color: var(--z-text);
        font-size: 0.88rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .transfer-pool-btn:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #dc2626;
    }
    .transfer-pool-btn i {
        font-size: 1rem;
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    .planning-dependency-modal {
        color: var(--z-text);
        text-align: left;
    }

    .planning-dependency-modal h4 {
        font-size: 0.95rem;
        font-weight: 800;
        margin: 0 0 8px;
    }

    .planning-dependency-summary {
        display: grid;
        gap: 8px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin: 10px 0 12px;
    }

    .planning-dependency-summary span {
        background: var(--z-bg-soft);
        border: 1px solid var(--z-border-light);
        border-radius: 6px;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        padding: 8px;
    }

    .planning-dependency-summary strong {
        color: var(--z-text);
        display: block;
        font-size: 0.95rem;
        margin-top: 2px;
    }

    .planning-dependency-block {
        border: 1px solid var(--z-border);
        border-radius: 8px;
        margin-top: 12px;
        padding: 12px;
    }

    .planning-dependency-person {
        background: #fff;
        border: 1px solid var(--z-border-light);
        border-radius: 6px;
        margin-top: 8px;
        padding: 10px;
    }

    .planning-dependency-person-head {
        align-items: flex-start;
        display: flex;
        gap: 8px;
        justify-content: space-between;
    }

    .planning-dependency-person strong {
        display: block;
        font-size: 0.9rem;
        line-height: 1.2;
    }

    .planning-dependency-person small,
    .planning-dependency-person p {
        color: var(--z-text-secondary);
        font-size: 0.78rem;
    }

    .planning-dependency-person p {
        margin: 7px 0 0;
    }

    .planning-dependency-empty {
        background: var(--z-bg-soft);
        border: 1px dashed var(--z-border);
        border-radius: 6px;
        color: var(--z-text-secondary);
        font-size: 0.85rem;
        margin-top: 8px;
        padding: 10px;
        text-align: center;
    }

    .planning-date-empty {
        display: grid;
        place-items: center;
        min-height: 96px;
        border: 1px dashed var(--z-border);
        border-radius: 6px;
        color: var(--z-text-muted);
        font-size: 0.86rem;
        font-weight: 700;
    }

    .planning-empty-state {
        display: grid;
        place-items: center;
        min-height: 260px;
        padding: 30px;
        border: 1px dashed var(--z-border);
        border-radius: 8px;
        background: #fff;
        color: var(--z-text-muted);
        text-align: center;
        gap: 8px;
    }

    .planning-empty-state i {
        color: var(--z-accent);
        font-size: 2rem;
        opacity: 0.45;
    }

    .planning-summary-panel {
        padding: 20px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-card);
    }

    .planning-summary-toolbar {
        display: grid;
        grid-template-columns: minmax(200px, 280px) minmax(160px, 200px) minmax(160px, 220px);
        gap: 14px;
        align-items: end;
        margin-bottom: 18px;
    }

    .planning-summary-table th,
    .planning-summary-table td {
        white-space: nowrap;
    }

    .planning-summary-table td:first-child {
        white-space: normal;
        min-width: 240px;
    }

    [hidden] {
        display: none !important;
    }

    @media (max-width: 1320px) {
        .planning-board {
            grid-template-columns: repeat(3, minmax(230px, 1fr));
        }
    }

    @media (max-width: 1040px) {
        .planning-controls,
        .planning-summary-toolbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .planning-board {
            grid-template-columns: repeat(2, minmax(230px, 1fr));
            gap: 20px;
        }
    }

    @media (max-width: 720px) {
        .planning-headline {
            align-items: flex-start;
            flex-direction: column;
        }

        .planning-title {
            font-size: 1.45rem;
        }

        .planning-controls,
        .planning-summary-toolbar,
        .planning-board {
            grid-template-columns: 1fr;
        }

        .planning-workbench {
            padding-top: 12px;
        }

        .planning-date-column {
            min-height: 220px;
            padding: 16px;
        }
    }
</style>
@endpush

@section('content')
    <div class="planning-legacy-shell">
        <section class="planning-workbench">
            <div class="planning-headline">
                <h2 class="planning-title">
                    <i class="bi bi-stack"></i>
                    Görev Planlaması
                </h2>
                <span class="planning-status-badge" id="planningStatusPill">Hazır</span>
            </div>

            <div class="planning-controls">
                <select id="personelSelect" class="form-select" onchange="handlePersonnelChange()" style="display:none">
                    <option value="">Personel Seçin</option>
                </select>
                <div class="pss-wrap" id="pssWrap">
                    <div class="pss-display" id="pssDisplay" tabindex="0">
                        <i class="bi bi-person pss-icon"></i>
                        <span class="pss-text" id="pssText">Personel Seçin</span>
                        <span class="pss-badge" id="pssBadge" style="display:none"></span>
                        <i class="bi bi-chevron-down pss-arrow"></i>
                    </div>
                    <div class="pss-dropdown" id="pssDropdown">
                        <div class="pss-search-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" class="pss-search" id="pssSearch" placeholder="Personel ara..." autocomplete="off">
                        </div>
                        <div class="pss-list" id="pssList"></div>
                    </div>
                </div>

                <label class="visually-hidden" for="yeniTarih">Yeni tarih</label>
                <input type="date" id="yeniTarih" class="form-control" value="{{ date('Y-m-d') }}">

                <button class="btn btn-outline-secondary" type="button" onclick="tarihKutusuEkle()">
                    <i class="bi bi-calendar-plus me-1"></i>Tarih Ekle
                </button>

                <button class="btn btn-primary" type="button" onclick="loadPersonelTasks()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Görevleri Getir
                </button>
            </div>

            <div class="planning-view-switch" role="tablist" aria-label="Planlama görünümü">
                <button class="planning-view-btn active" id="personnelViewButton" type="button" onclick="showPlanningView('personnel')">
                    <i class="bi bi-person"></i>Personel Planlama
                </button>
                <button class="planning-view-btn" id="multiViewButton" type="button" onclick="showPlanningView('multi')">
                    <i class="bi bi-people"></i>Bölüm Planlama (Çoklu)
                </button>
                <button class="planning-view-btn" id="summaryViewButton" type="button" onclick="showPlanningView('summary')">
                    <i class="bi bi-list-check"></i>Sipariş Özeti
                </button>
            </div>


            <div id="personnelPlanningView">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="planning-section-title mb-0" id="departmentTitle">Görev Planlaması</h3>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="boardMode" id="modeDynamic" value="dynamic" autocomplete="off" checked onchange="handleBoardModeChange()">
                        <label class="btn btn-outline-secondary" for="modeDynamic" title="Sadece dolu olan veya eklenen günleri gösterir"><i class="bi bi-kanban"></i> Standart</label>
                        
                        <input type="radio" class="btn-check" name="boardMode" id="modeWeekly" value="weekly" autocomplete="off" onchange="handleBoardModeChange()">
                        <label class="btn btn-outline-secondary" for="modeWeekly" title="Pazartesi'den Pazar'a 7 günlük takvim görünümü"><i class="bi bi-calendar-week"></i> Haftalık Takvim</label>
                    </div>
                </div>
                <div class="planning-meta-row">
                    <span class="planning-chip"><i class="bi bi-person"></i><span id="planningSelectedPersonnel">Personel seçilmedi</span></span>
                    <span class="planning-chip"><i class="bi bi-list-task"></i><span id="taskCount">0 görev</span></span>
                    <span class="planning-chip"><i class="bi bi-clock"></i><span id="planningUpdatedMeta">Bekleniyor</span></span>
                    <span class="planning-chip" id="autoRefreshChip" style="display:none" title="Otomatik yenileme aktif"><i class="bi bi-arrow-repeat"></i><span id="autoRefreshLabel">Otomatik</span></span>
                </div>

                <div class="planning-workload-bar" id="workloadBar">
                    <div class="planning-workload-stat">
                        <span class="wl-value" id="wlTaskCount">0</span>
                        <span class="wl-label">Görev</span>
                    </div>
                    <div class="planning-workload-stat">
                        <span class="wl-value" id="wlTotalQty">0</span>
                        <span class="wl-label">Toplam Adet</span>
                    </div>
                    <div class="planning-workload-stat">
                        <span class="wl-value is-ready" id="wlReadyQty">0</span>
                        <span class="wl-label">✅ Hazır</span>
                    </div>
                    <div class="planning-workload-stat">
                        <span class="wl-value is-waiting" id="wlWaitingQty">0</span>
                        <span class="wl-label">⏳ Bekleyen</span>
                    </div>
                    <div class="planning-workload-stat">
                        <span class="wl-value is-overdue" id="wlOverdueCount">0</span>
                        <span class="wl-label">⚠️ Gecikmiş</span>
                    </div>
                </div>
                <div class="planning-workload-progress" id="workloadProgressWrap" style="display:none">
                    <div class="planning-workload-track">
                        <div class="planning-workload-fill" id="workloadFill" style="width:0%"></div>
                    </div>
                </div>

                <div class="planning-board-wrapper">
                    <div id="personelTasksArea" class="planning-board">
                        <div class="planning-empty-state" style="grid-column: 1 / -1;">
                            <i class="bi bi-arrow-up-circle"></i>
                            <strong>Personel Seçin</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Özellik #3: Çoklu Personel Planlama Board'u -->
            <div id="multiPersonnelPlanningView" hidden>
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <h3 class="planning-section-title mb-0">Bölüm Planlaması (Çoklu Personel)</h3>
                    <div class="d-flex align-items-center gap-2">
                        <select id="multiDeptSelect" class="form-select form-select-sm" style="width: 250px" onchange="loadMultiPersonnelTasks()">
                            <option value="">Bölüm Seçin</option>
                        </select>
                        <button class="btn btn-primary btn-sm" type="button" onclick="loadMultiPersonnelTasks()">
                            <i class="bi bi-arrow-clockwise"></i> Yenile
                        </button>
                    </div>
                </div>
                
                <div class="multi-board-wrapper" id="multiBoardWrapper">
                    <div class="planning-empty-state" style="margin-top:40px">
                        <i class="bi bi-diagram-3"></i>
                        <strong>Bölüm Seçin</strong>
                    </div>
                </div>
            </div>

            <div id="summaryPlanningView" hidden>
                <div class="planning-summary-panel">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="planning-section-title mb-0">Sipariş Özeti</h3>
                        <span class="planning-chip" id="planningSummaryBadge">0 satır</span>
                    </div>

                    <div class="planning-summary-toolbar">
                        <div>
                            <label class="form-label" for="kategoriFilter">Kategori</label>
                            <select id="kategoriFilter" class="form-select">
                                <option value="">Tüm Kategoriler</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="tarihFilter">Referans tarih</label>
                            <input type="date" id="tarihFilter" class="form-control" value="{{ date('Y-m-d') }}">
                        </div>

                        <button class="btn btn-primary" type="button" onclick="loadSummary()">
                            <i class="bi bi-search me-1"></i>Özeti Getir
                        </button>
                    </div>

                    <div class="table-shell">
                        <table class="table-modern planning-summary-table" id="planningTable">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th>Toplam Sipariş</th>
                                    <th>Üretilebilir</th>
                                    <th>Eksik Miktar</th>
                                    <th>Kargo Son Teslim</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody id="planningBody">
                                <tr><td colspan="7" class="text-center text-muted py-4">Sipariş özeti yükleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Özellik #4: Havuz Kenar Çubuğu -->
    <button class="pool-toggle-btn" onclick="togglePoolSidebar()" title="Havuzdaki Görevler" style="display:none" id="poolToggleBtn">
        <i class="bi bi-inbox-fill"></i>
        <span class="pool-toggle-badge" id="poolToggleBadge" style="display:none">0</span>
    </button>
    <div class="pool-overlay" id="poolOverlay" onclick="togglePoolSidebar()"></div>
    <aside class="pool-sidebar" id="poolSidebar">
        <div class="pool-sidebar-header">
            <div class="pool-sidebar-title">
                <i class="bi bi-inbox text-primary"></i> Havuzdaki Görevler
            </div>
            <button class="pool-sidebar-close" onclick="togglePoolSidebar()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="pool-sidebar-body" id="poolSidebarBody">
            <div class="text-center text-muted mt-4">
                <i class="bi bi-arrow-repeat" style="font-size:1.5rem;animation:spin 1s linear infinite;display:block;margin-bottom:8px"></i>
                Yükleniyor...
            </div>
        </div>
    </aside>

    <div class="modal fade" id="dateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="bi bi-calendar-event me-1"></i>Tarih Değiştir</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="date" id="modalNewDate" class="form-control">
                    <input type="hidden" id="modalTaskId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="submitDateChange()">
                        <i class="bi bi-check-lg me-1"></i>Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPersonelNo = null;
let currentPlanningView = 'personnel';
let planningSummaryRows = [];
const pendingEmptyDateKeys = new Set();

function loadCurrentPlanningTab() {
    if (currentPlanningView === 'summary') {
        loadSummary();
        return;
    }

    loadPersonelTasks();
}

function csrfHeaders(extra = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    return token ? { ...extra, 'X-CSRF-TOKEN': token } : extra;
}

function setPlanningStamp(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('planningUpdatedMeta').textContent = label;
    // Özellik #8: Auto-refresh sayacını sıfırla
    if (typeof markRefreshTimestamp === 'function') markRefreshTimestamp();
}

function setPlanningStatus(label, tone = '') {
    const pill = document.getElementById('planningStatusPill');
    pill.textContent = label;
    pill.className = `planning-status-badge ${tone}`.trim();
}

function showPlanningView(view) {
    currentPlanningView = view;
    
    const isPersonel = view === 'personnel';
    const isMulti = view === 'multi';
    const isSummary = view === 'summary';

    document.getElementById('personnelPlanningView').hidden = !isPersonel;
    document.getElementById('multiPersonnelPlanningView').hidden = !isMulti;
    document.getElementById('summaryPlanningView').hidden = !isSummary;

    document.getElementById('personnelViewButton').classList.toggle('active', isPersonel);
    document.getElementById('multiViewButton').classList.toggle('active', isMulti);
    document.getElementById('summaryViewButton').classList.toggle('active', isSummary);

    if (isSummary) {
        loadSummary();
    } else if (isMulti) {
        if (!document.getElementById('multiDeptSelect').value) {
            // İlk açılışta eğer bir departman varsa onu seç
            const opt = document.querySelector('#multiDeptSelect option:nth-child(2)');
            if (opt) {
                document.getElementById('multiDeptSelect').value = opt.value;
                loadMultiPersonnelTasks();
            }
        } else {
            loadMultiPersonnelTasks();
        }
    }
}

let personnelData = []; // Özellik #2: tam personel verisi

function loadPersonelList() {
    setPlanningStatus('Yükleniyor', 'warning');

    fetch('/api/planning/personnel')
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                setPlanningStatus('Hata', 'danger');
                return;
            }

            personnelData = data.data || [];
            const select = document.getElementById('personelSelect');
            const fromQuery = new URLSearchParams(window.location.search).get('personel_no');
            const current = select.value || fromQuery || '';

            // Hidden select güncelle (uyumluluk)
            select.innerHTML = '<option value="">Personel Seçin</option>';
            
            // Özellik #3: Departman Select Güncelle
            const deptSelect = document.getElementById('multiDeptSelect');
            const depts = new Map();
            
            personnelData.forEach((p) => {
                select.innerHTML += `<option value="${escapeHtml(p.PersonelNo)}">${escapeHtml(p.PersonelAdi)}</option>`;
                if (p.BolumAdiNo && p.BolumAdi) {
                    depts.set(p.BolumAdiNo, p.BolumAdi);
                }
            });

            deptSelect.innerHTML = '<option value="">Bölüm Seçin</option>';
            Array.from(depts.entries()).forEach(([id, name]) => {
                deptSelect.innerHTML += `<option value="${escapeHtml(id)}">${escapeHtml(name)}</option>`;
            });

            // Custom dropdown güncelle
            pssRenderList(personnelData);

            if (current) {
                select.value = current;
                pssSetSelected(current);
            }

            if (select.value) {
                currentPersonelNo = select.value;
                loadPersonelTasks();
            } else {
                renderEmptyPersonnelState();
                setPlanningStatus('Hazır');
            }
        })
        .catch(() => {
            setPlanningStatus('Hata', 'danger');
            Swal.fire('Hata', 'Personel listesi yüklenemedi.', 'error');
        });
}

/* ───── Özellik #2: Aranabilir Dropdown Motoru ───── */
function pssInit() {
    const display = document.getElementById('pssDisplay');
    const dropdown = document.getElementById('pssDropdown');
    const search = document.getElementById('pssSearch');

    display.addEventListener('click', () => {
        const isOpen = dropdown.classList.contains('is-open');
        if (isOpen) {
            pssClose();
        } else {
            pssOpen();
        }
    });

    search.addEventListener('input', () => {
        const q = search.value.toLowerCase().trim();
        pssFilterList(q);
    });

    search.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { pssClose(); return; }
        if (e.key === 'Enter') {
            const focused = document.querySelector('.pss-item.is-focused');
            if (focused) focused.click();
            return;
        }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            pssMoveFocus(e.key === 'ArrowDown' ? 1 : -1);
        }
    });

    // Dışına tıklayınca kapat
    document.addEventListener('click', (e) => {
        if (!document.getElementById('pssWrap')?.contains(e.target)) {
            pssClose();
        }
    });
}

function pssOpen() {
    document.getElementById('pssDisplay').classList.add('is-open');
    document.getElementById('pssDropdown').classList.add('is-open');
    const search = document.getElementById('pssSearch');
    search.value = '';
    pssFilterList('');
    setTimeout(() => search.focus(), 50);
}

function pssClose() {
    document.getElementById('pssDisplay').classList.remove('is-open');
    document.getElementById('pssDropdown').classList.remove('is-open');
}

function pssRenderList(list) {
    const container = document.getElementById('pssList');
    if (!list.length) {
        container.innerHTML = '<div class="pss-empty">Personel bulunamadı</div>';
        return;
    }

    container.innerHTML = list.map((p) => {
        const no = String(p.PersonelNo || '');
        const name = escapeHtml(p.PersonelAdi || '');
        const dept = escapeHtml(p.BolumAdi || '');
        const tasks = parseInt(p.aktif_gorev_sayisi, 10) || 0;
        const isSelected = no === String(currentPersonelNo || '');
        return `<div class="pss-item ${isSelected ? 'is-selected' : ''}" data-pno="${escapeHtml(no)}" onclick="pssSelect('${escapeHtml(no)}')">
            <span class="pss-item-name">${name}</span>
            ${dept ? `<span class="pss-item-dept">${dept}</span>` : ''}
            ${tasks > 0 ? `<span class="pss-item-tasks">${tasks} görev</span>` : ''}
        </div>`;
    }).join('');
}

function pssFilterList(query) {
    const container = document.getElementById('pssList');
    const items = container.querySelectorAll('.pss-item');
    let visibleCount = 0;

    items.forEach((item) => {
        const text = item.textContent.toLowerCase();
        const match = !query || text.includes(query);
        item.style.display = match ? '' : 'none';
        item.classList.remove('is-focused');
        if (match) visibleCount++;
    });

    // İlk görünene focus ver
    const firstVisible = container.querySelector('.pss-item[style=""], .pss-item:not([style])');
    if (firstVisible && query) firstVisible.classList.add('is-focused');

    const empty = container.querySelector('.pss-empty');
    if (visibleCount === 0 && !empty) {
        container.insertAdjacentHTML('beforeend', '<div class="pss-empty pss-empty-temp">Sonuç bulunamadı</div>');
    } else {
        container.querySelector('.pss-empty-temp')?.remove();
    }
}

function pssMoveFocus(dir) {
    const items = Array.from(document.querySelectorAll('.pss-item')).filter(el => el.style.display !== 'none');
    if (!items.length) return;
    const focusedIdx = items.findIndex(el => el.classList.contains('is-focused'));
    items.forEach(el => el.classList.remove('is-focused'));
    let nextIdx = focusedIdx + dir;
    if (nextIdx < 0) nextIdx = items.length - 1;
    if (nextIdx >= items.length) nextIdx = 0;
    items[nextIdx].classList.add('is-focused');
    items[nextIdx].scrollIntoView({ block: 'nearest' });
}

function pssSelect(personnelNo) {
    const select = document.getElementById('personelSelect');
    select.value = personnelNo;
    pssSetSelected(personnelNo);
    pssClose();
    handlePersonnelChange();
}

function pssSetSelected(personnelNo) {
    const p = personnelData.find(x => String(x.PersonelNo) === String(personnelNo));
    const textEl = document.getElementById('pssText');
    const badgeEl = document.getElementById('pssBadge');

    if (p) {
        textEl.textContent = p.PersonelAdi || 'Personel';
        textEl.classList.remove('is-placeholder');
        const tasks = parseInt(p.aktif_gorev_sayisi, 10) || 0;
        if (tasks > 0) {
            badgeEl.textContent = `${tasks} görev`;
            badgeEl.style.display = '';
        } else {
            badgeEl.style.display = 'none';
        }
    } else {
        textEl.textContent = 'Personel Seçin';
        textEl.classList.add('is-placeholder');
        badgeEl.style.display = 'none';
    }

    // Seçili öğeyi işaretle
    document.querySelectorAll('.pss-item').forEach((el) => {
        el.classList.toggle('is-selected', el.dataset.pno === String(personnelNo));
    });
}

function handlePersonnelChange() {
    const selected = document.getElementById('personelSelect').value || '';
    if (selected !== currentPersonelNo) {
        pendingEmptyDateKeys.clear();
    }

    currentPersonelNo = selected;
    loadPersonelTasks();
}

let currentBoardMode = 'dynamic';

function handleBoardModeChange() {
    const radio = document.querySelector('input[name="boardMode"]:checked');
    if (radio) currentBoardMode = radio.value;
    
    const board = document.getElementById('personelTasksArea');
    if (currentBoardMode === 'weekly') {
        board.classList.add('is-weekly-view');
        // Haftanın 7 gününü pending empty date keys'e ekleyelim
        const now = new Date();
        const day = now.getDay();
        const diff = now.getDate() - day + (day === 0 ? -6 : 1); // Pazartesi'yi bul
        const monday = new Date(now.setDate(diff));
        
        for(let i = 0; i < 7; i++) {
            const d = new Date(monday);
            d.setDate(monday.getDate() + i);
            const iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            pendingEmptyDateKeys.add(iso);
        }
    } else {
        board.classList.remove('is-weekly-view');
        // Standart moda geçince fazladan boş tarihleri temizleyelim (tümünü silmiyorum ki kullanıcı eklediyse dursun)
        // Eğer istenirse pendingEmptyDateKeys.clear() yapılabilir ama kullanıcı eklediği tarihleri kaybeder.
        // Şimdilik sadece haftalık günlerin boş olanlarını ayıklayabiliriz ama temizleyip baştan yüklemek en temizi.
        pendingEmptyDateKeys.clear();
    }
    
    if (currentPersonelNo) {
        loadPersonelTasks();
    }
}

function loadPersonelTasks() {
    const select = document.getElementById('personelSelect');
    const area = document.getElementById('personelTasksArea');
    currentPersonelNo = select.value || '';

    if (!currentPersonelNo) {
        renderEmptyPersonnelState();
        setPlanningStatus('Hazır');
        return;
    }

    const selectedName = select.options[select.selectedIndex]?.text || 'Personel seçilmedi';
    document.getElementById('planningSelectedPersonnel').textContent = selectedName;
    document.getElementById('departmentTitle').textContent = 'Görev Planlaması';
    document.getElementById('taskCount').textContent = '0 görev';
    setPlanningStatus('Yükleniyor', 'warning');

    area.innerHTML = `
        <div class="planning-empty-state" style="grid-column: 1 / -1;">
            <i class="bi bi-arrow-repeat"></i>
            <strong>Görevler yükleniyor...</strong>
        </div>
    `;

    fetch(`/api/planning/personnel/${encodeURIComponent(currentPersonelNo)}/tasks`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                area.innerHTML = '<div class="alert alert-danger" style="grid-column: 1 / -1;">Görevler yüklenemedi.</div>';
                setPlanningStatus('Hata', 'danger');
                return;
            }

            const tasks = data.data || [];
            renderTaskBoard(tasks);
            setPlanningStamp();
            setPlanningStatus(tasks.length ? 'Akış aktif' : 'Boş plan', tasks.length ? 'success' : '');

            // Havuz kontrolü için departman no'yu bul ve butonu göster
            const p = personnelData.find(x => String(x.PersonelNo) === String(currentPersonelNo));
            if (p && parseInt(p.BolumAdiNo || 0) > 0) {
                document.getElementById('poolToggleBtn').style.display = 'flex';
                // Havuz sayısı çek
                fetch(`/api/planning/department/${p.BolumAdiNo}/pool`)
                    .then(r => r.json())
                    .then(pdata => {
                        updatePoolBadge(pdata.success ? (pdata.data || []).length : 0);
                        if (document.getElementById('poolSidebar').classList.contains('is-open')) {
                            loadPoolTasks();
                        }
                    });
            } else {
                document.getElementById('poolToggleBtn').style.display = 'none';
            }
        })
        .catch(() => {
            area.innerHTML = '<div class="alert alert-danger" style="grid-column: 1 / -1;">Görevler yüklenirken hata oluştu.</div>';
            setPlanningStatus('Hata', 'danger');
        });
}

function renderEmptyPersonnelState() {
    document.getElementById('departmentTitle').textContent = 'Görev Planlaması';
    document.getElementById('planningSelectedPersonnel').textContent = 'Personel seçilmedi';
    document.getElementById('taskCount').textContent = '0 görev';
    document.getElementById('personelTasksArea').innerHTML = `
        <div class="planning-empty-state" style="grid-column: 1 / -1;">
            <i class="bi bi-arrow-up-circle"></i>
            <strong>Personel Seçin</strong>
        </div>
    `;
    document.getElementById('poolToggleBtn').style.display = 'none';
    if (typeof updateWorkloadBar === 'function') updateWorkloadBar([], 0);
}

function renderTaskBoard(tasks) {
    const area = document.getElementById('personelTasksArea');
    const groups = new Map();
    const firstDepartment = tasks.find((task) => task.BolumAdi)?.BolumAdi || '';

    tasks.forEach((task) => {
        const info = normalizeDateInfo(task.GorevBaslamaTarihi);
        if (!groups.has(info.key)) {
            groups.set(info.key, { info, tasks: [] });
        }

        groups.get(info.key).tasks.push(task);
    });

    pendingEmptyDateKeys.forEach((isoDate) => {
        const info = normalizeDateInfo(isoDate);
        if (!groups.has(info.key)) {
            groups.set(info.key, { info, tasks: [] });
        }
    });

    document.getElementById('departmentTitle').textContent = firstDepartment ? `${firstDepartment} Bölümü` : 'Görev Planlaması';
    document.getElementById('taskCount').textContent = `${formatNumber(tasks.length)} görev`;

    if (!groups.size) {
        area.innerHTML = `
            <div class="planning-empty-state" style="grid-column: 1 / -1;">
                <i class="bi bi-inbox"></i>
                <strong>Bu personele atanmış görev yok.</strong>
            </div>
        `;
        updateWorkloadBar([], 0);
        return;
    }

    const html = Array.from(groups.values())
        .sort((a, b) => dateSortValue(a.info) - dateSortValue(b.info))
        .map(renderDateColumn)
        .join('');

    area.innerHTML = html;
    bindPlanningDragDrop();

    // Özellik #9: İş yükü bar güncelleme
    const todayISO = todayISODate();
    let overdueCount = 0;
    groups.forEach((group) => {
        if (group.info.iso && group.info.iso < todayISO && group.tasks.length > 0) {
            overdueCount += group.tasks.length;
        }
    });
    updateWorkloadBar(tasks, overdueCount);
}

function todayISODate() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function dateDiffDays(isoDate) {
    if (!isoDate) return null;
    const today = new Date(todayISODate());
    const target = new Date(isoDate);
    return Math.round((target - today) / 86400000);
}

function renderDateColumn(group) {
    const totalAmount = group.tasks.reduce((sum, task) => {
        const ready = parseInt(task.Adet, 10) || 0;
        const waiting = parseInt(task.BekleyenAdet, 10) || 0;
        return sum + ready + waiting;
    }, 0);
    const cards = group.tasks.length
        ? group.tasks.map(renderTaskCard).join('')
        : '<div class="planning-date-empty">Boş</div>';

    // Özellik #5: Bugün/gecikme hesaplama
    const diff = dateDiffDays(group.info.iso);
    let columnClass = '';
    let dateBadge = '';

    if (diff === 0) {
        columnClass = 'is-today';
        dateBadge = '<span class="planning-date-badge is-today"><i class="bi bi-star-fill"></i> BUGÜN</span>';
    } else if (diff !== null && diff < 0) {
        const absDiff = Math.abs(diff);
        columnClass = 'is-overdue';
        dateBadge = `<span class="planning-date-badge is-overdue"><i class="bi bi-exclamation-triangle-fill"></i> ${absDiff} gün gecikmiş</span>`;
    } else if (diff === 1) {
        dateBadge = '<span class="planning-date-badge is-future">Yarın</span>';
    } else if (diff !== null && diff > 1 && diff <= 7) {
        dateBadge = `<span class="planning-date-badge is-future">${diff} gün sonra</span>`;
    }

    return `
        <article class="planning-date-column ${columnClass}" data-date-key="${escapeHtml(group.info.key)}" data-iso-date="${escapeHtml(group.info.iso)}">
            <div class="planning-date-header">
                <div class="planning-date-label">${escapeHtml(group.info.label)}</div>
                ${dateBadge}
                <div class="planning-date-stats">
                    <span class="planning-chip">${formatNumber(group.tasks.length)} görev</span>
                    <span class="planning-chip">${formatNumber(totalAmount)} adet</span>
                </div>
            </div>
            <div class="planning-task-list">${cards}</div>
        </article>
    `;
}

function updateWorkloadBar(tasks, overdueCount) {
    const bar = document.getElementById('workloadBar');
    const progressWrap = document.getElementById('workloadProgressWrap');

    if (!tasks.length) {
        bar.classList.remove('is-visible');
        progressWrap.style.display = 'none';
        return;
    }

    let totalReady = 0, totalWaiting = 0;
    tasks.forEach((t) => {
        totalReady += Math.max(0, parseInt(t.Adet, 10) || 0);
        totalWaiting += Math.max(0, parseInt(t.BekleyenAdet, 10) || 0);
    });
    const total = totalReady + totalWaiting;
    const pct = total > 0 ? Math.round((totalReady / total) * 100) : 0;

    document.getElementById('wlTaskCount').textContent = formatNumber(tasks.length);
    document.getElementById('wlTotalQty').textContent = formatNumber(total);
    document.getElementById('wlReadyQty').textContent = formatNumber(totalReady);
    document.getElementById('wlWaitingQty').textContent = formatNumber(totalWaiting);
    document.getElementById('wlOverdueCount').textContent = formatNumber(overdueCount);
    document.getElementById('workloadFill').style.width = `${pct}%`;

    bar.classList.add('is-visible');
    progressWrap.style.display = '';
}
}



function renderTaskCard(task) {
    const id = Number(task.No || 0);
    const amount = parseInt(task.Adet, 10) || 0;
    const pending = parseInt(task.BekleyenAdet, 10) || 0;
    const approved = isApproved(task.Onay);
    const state = planningTaskState(task, amount, pending);
    const canIncrease = !approved;
    const canDecrease = amount > 0;
    const dependencyButton = pending > 0
        ? `<button class="planning-icon-btn is-info" type="button" onclick="openPlanningDependencyInfo(${id})" title="Bekleme detayını gör">
                <i class="bi bi-info-circle"></i>
            </button>`
        : '';

    const totalQty = amount + pending;

    return `
        <div class="planning-task-card ${state.className}" id="task-${id}" data-task-id="${id}" draggable="true">
            <div class="planning-task-copy">
                <strong>Ara Ürün:</strong> ${escapeHtml(task.AraUrunAdi || 'Bilinmiyor')}<br>
                <strong>Adet:</strong> <span class="planning-qty-editable" onclick="startQtyEdit(${id}, ${totalQty})" title="Tıkla ve yeni adet gir">${formatNumber(amount)}</span>
                <strong>Bekleyen:</strong> ${formatNumber(pending)}
            </div>
            <div class="planning-task-status">
                <i class="bi ${state.icon}"></i>${escapeHtml(state.label)}
            </div>
            <div class="planning-task-actions">
                ${dependencyButton}
                <button class="planning-icon-btn is-transfer" type="button" onclick="openTransferModal(${id})" title="Başka personele aktar">
                    <i class="bi bi-person-up"></i>
                </button>
                <button class="planning-icon-btn" type="button" onclick="startQtyEdit(${id}, ${totalQty})" title="Adet değiştir">
                    <i class="bi bi-hash"></i>
                </button>
                <button class="planning-icon-btn" type="button" onclick="openDateModal(${id})" title="Tarih değiştir">
                    <i class="bi bi-calendar-event"></i>
                </button>
                <button class="planning-icon-btn is-pool-return" type="button" onclick="returnToPool(${id})" title="Havuza iade et">
                    <i class="bi bi-box-arrow-in-left"></i>
                </button>
                <button class="planning-icon-btn" type="button" onclick="showTaskHistory(${id})" title="Görev geçmişi" style="color:#6b7280">
                    <i class="bi bi-clock-history"></i>
                </button>
            </div>
        </div>
    `;
}

function planningTaskState(task, amount, pending) {
    if (isActiveProduction(task.Onay)) {
        return {
            className: 'is-active',
            icon: 'bi-play-circle',
            label: 'Üretimde',
        };
    }

    if (amount > 0) {
        return {
            className: 'is-available',
            icon: 'bi-check-circle',
            label: pending > 0 ? 'Kısmi kabul edilebilir' : 'Kabul edilebilir',
        };
    }

    if (pending > 0) {
        return {
            className: 'is-blocked',
            icon: 'bi-exclamation-triangle',
            label: 'Alt parça bekliyor',
        };
    }

    return {
        className: 'is-neutral',
        icon: 'bi-clock',
        label: 'Plan bekliyor',
    };
}

function buildPlanningDependencyInfoHtml(data) {
    const shortages = Array.isArray(data?.shortages) ? data.shortages : [];
    const task = data?.task || {};

    if (!shortages.length) {
        return `
            <div class="planning-dependency-modal">
                <p class="planning-dependency-empty mb-0">
                    Bu görev için aktif alt parça eksiği bulunamadı. Stok veya planlama bilgisi yenilenmiş olabilir.
                </p>
            </div>
        `;
    }

    const shortageHtml = shortages.map((shortage) => {
        const componentNo = Number(shortage.component_no || 0);
        const suppliers = Array.isArray(shortage.suppliers) ? shortage.suppliers : [];
        const poolRows = Array.isArray(shortage.pool) ? shortage.pool : [];
        const supplierHtml = suppliers.length
            ? suppliers.map((supplier) => {
                const canNotify = supplier.can_notify === true || Number(supplier.can_notify || 0) === 1;
                const expectedQuantity = Number(supplier.expected_quantity || 0);
                const notifyButton = canNotify
                    ? `<button type="button" class="btn btn-outline-primary btn-sm mt-2 planning-dependency-notify-btn" data-component-no="${componentNo}" data-supplier-task-no="${Number(supplier.task_no || 0)}"><i class="bi bi-bell me-1"></i>Bildirim gönder</button>`
                    : '';

                return `
                    <div class="planning-dependency-person">
                        <div class="planning-dependency-person-head">
                            <div>
                                <strong>${escapeHtml(supplier.personnel_name || 'Personel')}</strong>
                                <small>${escapeHtml(supplier.department_name || supplier.status || 'Açık görev')}</small>
                            </div>
                            <span class="soft-badge warning">${formatNumber(expectedQuantity > 0 ? expectedQuantity : Number(supplier.open_quantity || 0))} adet</span>
                        </div>
                        <p>${escapeHtml(supplier.status || 'Açık görev')} · Açık: ${formatNumber(supplier.open_quantity)} · Hazır: ${formatNumber(supplier.ready_quantity)} · Bekleyen: ${formatNumber(supplier.waiting_quantity)}</p>
                        <p>${escapeHtml(supplier.wait_reason || 'Personelin üretim girişi bekleniyor.')}</p>
                        ${notifyButton}
                        <div class="planning-dependency-notice small mt-2" aria-live="polite"></div>
                    </div>
                `;
            }).join('')
            : '<div class="planning-dependency-empty">Bu parça için personelde açık üretim bulunamadı.</div>';

        const poolHtml = poolRows.length
            ? `<div class="planning-dependency-empty">Havuzda atama bekleyen ${formatNumber(poolRows.reduce((sum, row) => sum + Number(row.open_quantity || 0), 0))} adet açık iş var.</div>`
            : '';

        return `
            <div class="planning-dependency-block">
                <h4>${escapeHtml(shortage.component_name || 'Alt parça')}</h4>
                <div class="planning-dependency-summary">
                    <span>Gerekli<strong>${formatNumber(shortage.required_quantity)} adet</strong></span>
                    <span>Eksik<strong>${formatNumber(shortage.missing_quantity)} adet</strong></span>
                    <span>Stok<strong>${formatNumber(shortage.stock_quantity)} adet</strong></span>
                    <span>Kullanılabilir<strong>${formatNumber(shortage.usable_quantity)} adet</strong></span>
                </div>
                <h4>Kimden gelecek?</h4>
                ${supplierHtml}
                ${poolHtml}
            </div>
        `;
    }).join('');

    return `
        <div class="planning-dependency-modal">
            <p class="mb-2">
                <strong>${escapeHtml(task.component_name || 'Görev')}</strong>
                ${task.personnel_name ? `· ${escapeHtml(task.personnel_name)}` : ''}
                ${Number(task.quantity_checked || 0) > 0 ? `· ${formatNumber(task.quantity_checked)} adet kontrol edildi.` : ''}
            </p>
            ${shortageHtml}
        </div>
    `;
}

function openPlanningDependencyInfo(taskId) {
    const id = Number(taskId || 0);
    if (!id) return;

    Swal.fire({
        title: 'Bekleme detayı',
        html: '<div class="planning-dependency-empty">Bağımlılık bilgisi yükleniyor...</div>',
        showConfirmButton: false,
        allowOutsideClick: false,
    });

    fetch(`/api/planning/task/${id}/dependency-info`, { headers: { 'Accept': 'application/json' } })
        .then((response) => response.json().catch(() => ({})).then((payload) => ({ ok: response.ok, payload })))
        .then(({ ok, payload }) => {
            if (!ok || !payload.success) {
                throw new Error(payload.message || 'Bekleme detayı alınamadı.');
            }

            Swal.fire({
                title: 'Bekleme detayı',
                html: buildPlanningDependencyInfoHtml(payload),
                confirmButtonText: 'Kapat',
                confirmButtonColor: '#0d9488',
                width: 660,
                didOpen: (popup) => {
                    popup.querySelectorAll('.planning-dependency-notify-btn').forEach((button) => {
                        button.addEventListener('click', () => {
                            sendPlanningDependencyNotification(
                                id,
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
            Swal.fire('Hata', error.message || 'Bekleme detayı alınamadı.', 'error');
        });
}

function sendPlanningDependencyNotification(taskId, componentNo, supplierTaskNo, button = null) {
    const notice = button?.closest('.planning-dependency-person')?.querySelector('.planning-dependency-notice') || null;
    const originalHtml = button?.innerHTML || '';

    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Gönderiliyor';
    }
    if (notice) {
        notice.className = 'planning-dependency-notice small mt-2 text-muted';
        notice.textContent = '';
    }

    return fetch(`/api/planning/task/${Number(taskId || 0)}/notify-dependency`, {
        method: 'POST',
        headers: csrfHeaders({
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }),
        body: JSON.stringify({
            component_no: Number(componentNo || 0),
            supplier_task_no: Number(supplierTaskNo || 0)
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
                notice.className = 'planning-dependency-notice small mt-2 text-success';
                notice.textContent = payload.message || 'Bildirim gönderildi.';
            }
        })
        .catch((error) => {
            if (button) {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
            if (notice) {
                notice.className = 'planning-dependency-notice small mt-2 text-danger';
                notice.textContent = error.message || 'Bildirim gönderilemedi.';
            } else {
                Swal.fire('Hata', error.message || 'Bildirim gönderilemedi.', 'error');
            }
        });
}

function bindPlanningDragDrop() {
    document.querySelectorAll('.planning-task-card').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            event.dataTransfer.setData('text/plain', card.dataset.taskId);
            card.classList.add('is-dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
        });
    });

    document.querySelectorAll('.planning-date-column').forEach((column) => {
        column.addEventListener('dragover', (event) => {
            if (!column.dataset.isoDate) return;
            event.preventDefault();
            column.classList.add('is-drop-target');
        });

        column.addEventListener('dragleave', () => {
            column.classList.remove('is-drop-target');
        });

        column.addEventListener('drop', (event) => {
            event.preventDefault();
            column.classList.remove('is-drop-target');
            
            const newDate = column.dataset.isoDate;
            if (!newDate) return;

            // Havuzdan gelen sürükle-bırak kontrolü
            const poolId = event.dataTransfer.getData('application/pool-id');
            if (poolId) {
                assignPoolTask(poolId, newDate);
                return;
            }

            // Normal görev tarihi değiştirme
            const taskId = event.dataTransfer.getData('text/plain');
            if (!taskId) return;
            
            const draggedCard = document.querySelector(`.planning-task-card[data-task-id="${escapeCssValue(taskId)}"]`);
            const oldDate = draggedCard?.closest('.planning-date-column')?.dataset.isoDate || '';

            if (oldDate) pendingEmptyDateKeys.add(oldDate);
            updateTaskDate(taskId, newDate, true);
        });
    });
}

function lockTaskCard(taskId) {
    const card = document.getElementById(`task-${taskId}`);
    if (!card) return false;
    if (card.dataset.locked === '1') return true; // already locked
    card.dataset.locked = '1';
    card.style.opacity = '0.6';
    card.querySelectorAll('.planning-icon-btn').forEach((btn) => { btn.disabled = true; });
    return false;
}

function unlockTaskCard(taskId) {
    const card = document.getElementById(`task-${taskId}`);
    if (!card) return;
    delete card.dataset.locked;
    card.style.opacity = '';
    card.querySelectorAll('.planning-icon-btn').forEach((btn) => { btn.disabled = false; });
}

function incrementTask(taskId) {
    if (lockTaskCard(taskId)) return;

    fetch(`/api/planning/increment/${taskId}`, {
        method: 'POST',
        headers: csrfHeaders()
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                unlockTaskCard(taskId);
                Swal.fire('Hata', data.message || 'İşlem tamamlanamadı.', 'error');
                return;
            }

            loadPersonelTasks();
        })
        .catch((error) => {
            unlockTaskCard(taskId);
            Swal.fire('Hata', String(error), 'error');
        });
}

function decrementTask(taskId) {
    if (lockTaskCard(taskId)) return;

    fetch(`/api/planning/decrement/${taskId}`, {
        method: 'POST',
        headers: csrfHeaders()
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                unlockTaskCard(taskId);
                Swal.fire('Hata', data.message || 'İşlem tamamlanamadı.', 'error');
                return;
            }

            loadPersonelTasks();
        })
        .catch((error) => {
            unlockTaskCard(taskId);
            Swal.fire('Hata', String(error), 'error');
        });
}

function deleteTask(taskId) {
    returnToPool(taskId);
}

function returnToPool(taskId) {
    if (lockTaskCard(taskId)) return;

    Swal.fire({
        title: '<i class="bi bi-box-arrow-in-left" style="color:#6366f1;margin-right:6px"></i> Havuza İade',
        html: '<div style="text-align:left;font-size:0.92rem;color:var(--z-text-secondary)">' +
              'Bu görev personelden alınıp <strong>iş emri havuzuna</strong> geri aktarılacak.<br>' +
              '<small style="opacity:0.7">Havuzdan tekrar başka bir personele atanabilir.</small></div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> Evet, havuza iade et',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#6366f1',
        focusCancel: true,
    }).then((result) => {
        if (!result.isConfirmed) {
            unlockTaskCard(taskId);
            return;
        }

        fetch(`/api/planning/task/${taskId}`, {
            method: 'DELETE',
            headers: csrfHeaders()
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) {
                    unlockTaskCard(taskId);
                    Swal.fire('Hata', data.message || 'İade işlemi başarısız.', 'error');
                    return;
                }

                Swal.fire({
                    title: 'Havuza İade Edildi',
                    text: data.message || 'Görev başarıyla havuza aktarıldı.',
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                });
                loadPersonelTasks();
            })
            .catch((error) => {
                unlockTaskCard(taskId);
                Swal.fire('Hata', String(error), 'error');
            });
    });
}

function openTransferModal(taskId) {
    if (lockTaskCard(taskId)) return;

    Swal.fire({
        title: '<i class="bi bi-person-up" style="color:#d97706;margin-right:6px"></i> Görev Aktarma',
        html: '<div style="text-align:center;padding:16px 0"><i class="bi bi-arrow-repeat" style="font-size:1.5rem;color:var(--z-text-secondary);animation:spin 1s linear infinite"></i><br><small style="color:var(--z-text-secondary)">Personel listesi yükleniyor…</small></div>',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Kapat',
        didOpen: () => {
            fetch(`/api/planning/task/${taskId}/transfer-options`, {
                headers: csrfHeaders()
            })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) {
                        Swal.update({
                            html: `<div style="color:#dc2626;padding:12px"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(data.message || 'Bilgiler yüklenemedi.')}</div>`,
                        });
                        return;
                    }

                    const task = data.task;
                    const personnel = data.available_personnel || [];
                    const totalQty = task.total_quantity || 0;
                    const dateStr = task.date ? task.date.substring(0, 10) : '—';

                    let personnelOptions = '<option value="" disabled selected>Personel seçin…</option>';
                    personnel.forEach((p) => {
                        personnelOptions += `<option value="${p.PersonelNo}">${escapeHtml(p.PersonelAdi || 'Personel #' + p.PersonelNo)}</option>`;
                    });

                    const noPersonnelMsg = personnel.length === 0
                        ? '<div style="color:#d97706;font-size:0.82rem;margin-top:6px"><i class="bi bi-exclamation-triangle"></i> Bu bölümde aktarılabilecek başka personel bulunamadı.</div>'
                        : '';

                    const html = `
                        <div class="transfer-modal">
                            <div class="transfer-task-info">
                                <div class="transfer-info-row">
                                    <span class="transfer-label">Ara Ürün</span>
                                    <span class="transfer-value">${escapeHtml(task.component_name || '—')}</span>
                                </div>
                                <div class="transfer-info-row">
                                    <span class="transfer-label">Bölüm</span>
                                    <span class="transfer-value">${escapeHtml(task.department_name || '—')}</span>
                                </div>
                                <div class="transfer-info-row">
                                    <span class="transfer-label">Mevcut Personel</span>
                                    <span class="transfer-value">${escapeHtml(task.personnel_name || '—')}</span>
                                </div>
                                <div class="transfer-info-row">
                                    <span class="transfer-label">Toplam Adet</span>
                                    <span class="transfer-value">${formatNumber(totalQty)}</span>
                                </div>
                                <div class="transfer-info-row">
                                    <span class="transfer-label">Tarih</span>
                                    <span class="transfer-value">${escapeHtml(dateStr)}</span>
                                </div>
                            </div>

                            <div class="transfer-section-label">Hedef Personel</div>
                            <select id="transferTargetSelect" class="transfer-personnel-select" ${personnel.length === 0 ? 'disabled' : ''}>
                                ${personnelOptions}
                            </select>
                            ${noPersonnelMsg}

                            <div class="transfer-divider">veya</div>

                            <button type="button" class="transfer-pool-btn" onclick="executeReturnToPoolFromModal(${taskId})">
                                <i class="bi bi-box-arrow-in-left"></i> Havuza İade Et
                            </button>
                        </div>
                    `;

                    Swal.update({
                        html: html,
                        showConfirmButton: personnel.length > 0,
                        confirmButtonText: '<i class="bi bi-person-check"></i> Aktar',
                        confirmButtonColor: '#d97706',
                    });

                    // Confirm handler for transfer
                    const confirmBtn = Swal.getConfirmButton();
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const select = document.getElementById('transferTargetSelect');
                            if (!select || !select.value) {
                                Swal.showValidationMessage('Lütfen hedef personel seçin.');
                                return;
                            }
                            executeTransfer(taskId, parseInt(select.value, 10));
                        });
                    }
                })
                .catch((error) => {
                    Swal.update({
                        html: `<div style="color:#dc2626;padding:12px"><i class="bi bi-exclamation-triangle"></i> Bağlantı hatası: ${escapeHtml(String(error))}</div>`,
                    });
                });
        },
        willClose: () => {
            unlockTaskCard(taskId);
        },
    });
}

function executeTransfer(taskId, targetPersonnelNo) {
    Swal.fire({
        title: 'Aktarılıyor…',
        html: '<i class="bi bi-arrow-repeat" style="font-size:1.3rem;animation:spin 1s linear infinite"></i>',
        allowOutsideClick: false,
        showConfirmButton: false,
    });

    fetch(`/api/planning/task/${taskId}/transfer`, {
        method: 'POST',
        headers: csrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ target_personnel_no: targetPersonnelNo }),
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Aktarma başarısız.', 'error');
                return;
            }

            Swal.fire({
                title: 'Aktarıldı!',
                text: data.message || 'Görev başarıyla aktarıldı.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
            });
            loadPersonelTasks();
        })
        .catch((error) => Swal.fire('Hata', String(error), 'error'));
}

function executeReturnToPoolFromModal(taskId) {
    Swal.fire({
        title: 'Havuza iade ediliyor…',
        html: '<i class="bi bi-arrow-repeat" style="font-size:1.3rem;animation:spin 1s linear infinite"></i>',
        allowOutsideClick: false,
        showConfirmButton: false,
    });

    fetch(`/api/planning/task/${taskId}`, {
        method: 'DELETE',
        headers: csrfHeaders(),
    })
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', data.message || 'İade başarısız.', 'error');
                return;
            }

            Swal.fire({
                title: 'Havuza İade Edildi',
                text: data.message || 'Görev havuza aktarıldı.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
            loadPersonelTasks();
        })
        .catch((error) => Swal.fire('Hata', String(error), 'error'));
}

/* ───── Özellik #4: Havuz Kenar Çubuğu & Atama ───── */
function togglePoolSidebar() {
    const sidebar = document.getElementById('poolSidebar');
    const overlay = document.getElementById('poolOverlay');
    const isOpen = sidebar.classList.contains('is-open');

    if (isOpen) {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
    } else {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
        loadPoolTasks();
    }
}

function loadPoolTasks() {
    const body = document.getElementById('poolSidebarBody');
    const p = personnelData.find(x => String(x.PersonelNo) === String(currentPersonelNo));
    const deptId = p ? parseInt(p.BolumAdiNo || 0) : 0;

    if (!deptId) {
        body.innerHTML = '<div class="text-center text-muted mt-4">Personel bölümü bulunamadı.</div>';
        return;
    }

    body.innerHTML = '<div class="text-center text-muted mt-4"><i class="bi bi-arrow-repeat" style="font-size:1.5rem;animation:spin 1s linear infinite;display:block;margin-bottom:8px"></i>Yükleniyor...</div>';

    fetch(`/api/planning/department/${deptId}/pool`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                body.innerHTML = `<div class="alert alert-danger">${escapeHtml(data.message || 'Yüklenemedi')}</div>`;
                return;
            }

            const tasks = data.data || [];
            updatePoolBadge(tasks.length);

            if (!tasks.length) {
                body.innerHTML = `
                    <div style="text-align:center;padding:40px 20px;color:var(--z-text-secondary)">
                        <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:12px;opacity:0.5"></i>
                        Bu bölüm için havuzda bekleyen<br>görev bulunmuyor.
                    </div>`;
                return;
            }

            body.innerHTML = tasks.map(t => {
                const id = parseInt(t.No);
                const qty = parseInt(t.Adet);
                const name = escapeHtml(t.AraUrunAdi || 'Bilinmiyor');
                return `
                    <div class="pool-card" draggable="true" data-pool-id="${id}">
                        <div class="pool-card-title">${name}</div>
                        <div class="pool-card-meta">
                            <span>Sipariş: ${escapeHtml(t.SiparisNo || '-')}</span>
                            <span class="pool-card-qty">${formatNumber(qty)} adet</span>
                        </div>
                        <div class="pool-card-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary pool-assign-btn" onclick="assignPoolTask(${id})">
                                <i class="bi bi-plus-lg"></i> Personele Ata
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            // Sürükle bırak bağla
            body.querySelectorAll('.pool-card').forEach(card => {
                card.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('application/pool-id', card.dataset.poolId);
                    card.classList.add('is-dragging');
                });
                card.addEventListener('dragend', () => {
                    card.classList.remove('is-dragging');
                });
            });
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger">Hata: ${escapeHtml(String(err))}</div>`;
        });
}

function updatePoolBadge(count) {
    const badge = document.getElementById('poolToggleBadge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }
}

function assignPoolTask(poolId, targetIsoDate = null) {
    if (!currentPersonelNo) return;
    const dateStr = targetIsoDate || todayISODate();

    Swal.fire({
        title: 'Atanıyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('/api/planning/pool/assign', {
        method: 'POST',
        headers: csrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
            pool_id: poolId,
            personnel_no: currentPersonelNo,
            target_date: dateStr
        })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            Swal.fire('Hata', data.message || 'Atama başarısız.', 'error');
            return;
        }
        Swal.fire({ title: 'Atandı', icon: 'success', timer: 1200, showConfirmButton: false });
        loadPersonelTasks(); // Ana tabloyu yenile
        if (document.getElementById('poolSidebar').classList.contains('is-open')) {
            loadPoolTasks(); // Havuzu yenile
        }
    })
    .catch(err => Swal.fire('Hata', String(err), 'error'));
}

/* ───── Özellik #7: Görev Geçmişi Timeline ───── */
function showTaskHistory(taskId) {
    Swal.fire({
        title: '<i class="bi bi-clock-history" style="color:#6366f1;margin-right:6px"></i> Görev Geçmişi',
        html: '<div style="text-align:center;padding:16px 0"><i class="bi bi-arrow-repeat" style="font-size:1.5rem;color:var(--z-text-secondary);animation:spin 1s linear infinite"></i><br><small style="color:var(--z-text-secondary)">Geçmiş yükleniyor…</small></div>',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Kapat',
        width: 480,
        didOpen: () => {
            fetch(`/api/planning/task/${taskId}/history`, {
                headers: csrfHeaders()
            })
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) {
                        Swal.update({
                            html: `<div style="color:#dc2626;padding:12px"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(data.message || 'Geçmiş yüklenemedi.')}</div>`,
                        });
                        return;
                    }

                    const events = data.data || [];
                    const compName = data.component_name || '';

                    if (!events.length) {
                        Swal.update({
                            html: `<div class="planning-tl-empty"><i class="bi bi-inbox" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>Bu görev için henüz kayıtlı geçmiş yok.</div>`,
                        });
                        return;
                    }

                    const header = compName ? `<div style="font-size:0.8rem;color:var(--z-text-secondary);margin-bottom:12px;text-align:left"><strong>Ara Ürün:</strong> ${escapeHtml(compName)}</div>` : '';

                    const timelineHtml = events.map((ev) => `
                        <div class="planning-tl-item">
                            <div class="planning-tl-dot" style="background:${ev.color}">
                                <i class="bi ${ev.icon}"></i>
                            </div>
                            <div class="planning-tl-body">
                                <div class="planning-tl-title">${escapeHtml(ev.title)}</div>
                                ${ev.detail ? `<div class="planning-tl-detail">${escapeHtml(ev.detail)}</div>` : ''}
                                <div class="planning-tl-meta">
                                    ${escapeHtml(ev.date)}${ev.actor ? ' · ' + escapeHtml(ev.actor) : ''}${ev.screen ? ' · ' + escapeHtml(ev.screen) : ''}
                                </div>
                            </div>
                        </div>
                    `).join('');

                    Swal.update({
                        html: `${header}<div class="planning-timeline">${timelineHtml}</div>`,
                    });
                })
                .catch((error) => {
                    Swal.update({
                        html: `<div style="color:#dc2626;padding:12px"><i class="bi bi-exclamation-triangle"></i> Bağlantı hatası: ${escapeHtml(String(error))}</div>`,
                    });
                });
        },
    });
}

/* ───── Özellik #1: Toplu Adet Girişi ───── */
function startQtyEdit(taskId, currentTotal) {
    if (lockTaskCard(taskId)) return;

    Swal.fire({
        title: '<i class="bi bi-hash" style="color:var(--z-accent);margin-right:6px"></i> Adet Değiştir',
        html: `
            <div style="text-align:left;font-size:0.9rem;margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <span style="color:var(--z-text-secondary)">Mevcut adet:</span>
                    <strong>${formatNumber(currentTotal)}</strong>
                </div>
            </div>
            <input type="number" id="swalQtyInput" class="swal2-input" min="0" step="1"
                   value="${currentTotal}" placeholder="Yeni adet girin"
                   style="text-align:center;font-size:1.2rem;font-weight:700">
            <div style="font-size:0.75rem;color:var(--z-text-secondary);margin-top:6px">
                Havuzdan çekilir veya havuza iade edilir.
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-lg"></i> Uygula',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#6366f1',
        focusConfirm: false,
        didOpen: () => {
            const inp = document.getElementById('swalQtyInput');
            if (inp) { inp.focus(); inp.select(); }
        },
        preConfirm: () => {
            const val = parseInt(document.getElementById('swalQtyInput')?.value, 10);
            if (isNaN(val) || val < 0) {
                Swal.showValidationMessage('Geçerli bir sayı girin (0 veya üzeri).');
                return false;
            }
            return val;
        },
    }).then((result) => {
        if (!result.isConfirmed) {
            unlockTaskCard(taskId);
            return;
        }
        submitQtyEdit(taskId, result.value);
    });
}

function submitQtyEdit(taskId, targetQty) {
    Swal.fire({
        title: 'Güncelleniyor…',
        html: '<i class="bi bi-arrow-repeat" style="font-size:1.3rem;animation:spin 1s linear infinite"></i>',
        allowOutsideClick: false,
        showConfirmButton: false,
    });

    fetch(`/api/planning/task/${taskId}/quantity`, {
        method: 'PUT',
        headers: csrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ target_quantity: targetQty }),
    })
        .then((r) => r.json())
        .then((data) => {
            unlockTaskCard(taskId);
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Adet güncellenemedi.', 'error');
                return;
            }
            Swal.fire({
                title: 'Adet Güncellendi',
                text: data.message || 'Başarılı.',
                icon: 'success',
                timer: 1800,
                showConfirmButton: false,
            });
            loadPersonelTasks();
        })
        .catch((error) => {
            unlockTaskCard(taskId);
            Swal.fire('Hata', String(error), 'error');
        });
}

function openDateModal(taskId) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('modalNewDate').value = '';
    new bootstrap.Modal(document.getElementById('dateModal')).show();
}

function submitDateChange() {
    const taskId = document.getElementById('modalTaskId').value;
    const newDate = document.getElementById('modalNewDate').value;

    if (!newDate) {
        Swal.fire('Eksik tarih', 'Lütfen bir tarih seçin.', 'warning');
        return;
    }

    updateTaskDate(taskId, newDate, false);
}

function updateTaskDate(taskId, newDate, fromDrag) {
    setPlanningStatus('Tarih kaydediliyor', 'warning');

    fetch(`/api/planning/task/${taskId}/date`, {
        method: 'PUT',
        headers: csrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ date: newDate })
    })
        .then((r) => r.json())
        .then((data) => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('dateModal'));
            if (modal) modal.hide();

            if (!data.success) {
                setPlanningStatus('Hata', 'danger');
                Swal.fire('Hata', data.message || 'Tarih güncellenemedi.', 'error');
                loadPersonelTasks();
                return;
            }

            if (fromDrag) {
                pendingEmptyDateKeys.add(newDate);
            }

            loadPersonelTasks();
        })
        .catch((error) => {
            setPlanningStatus('Hata', 'danger');
            Swal.fire('Hata', String(error), 'error');
        });
}

function tarihKutusuEkle() {
    const date = document.getElementById('yeniTarih').value;
    if (!date) {
        Swal.fire('Eksik tarih', 'Lütfen bir tarih seçin.', 'warning');
        return;
    }

    pendingEmptyDateKeys.add(date);
    renderTaskBoardFromCurrentDom(date);
}

function renderTaskBoardFromCurrentDom(date) {
    const existing = document.querySelector(`.planning-date-column[data-iso-date="${escapeCssValue(date)}"]`);
    if (existing) {
        existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    if (currentPersonelNo) {
        loadPersonelTasks();
        return;
    }

    const area = document.getElementById('personelTasksArea');
    area.innerHTML = renderDateColumn({ info: normalizeDateInfo(date), tasks: [] });
    bindPlanningDragDrop();
}

function loadSummary() {
    const category = document.getElementById('kategoriFilter').value;
    const selectedDate = document.getElementById('tarihFilter').value;

    fetch(`/SiparisApi.ashx?action=getSummary&kategori=${encodeURIComponent(category)}`)
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) return;

            if (data.kategoriList) {
                const select = document.getElementById('kategoriFilter');
                const current = category;
                select.innerHTML = '<option value="">Tüm Kategoriler</option>';
                data.kategoriList.forEach((item) => {
                    select.innerHTML += `<option value="${escapeHtml(item)}"${item === current ? ' selected' : ''}>${escapeHtml(item)}</option>`;
                });
            }

            const rows = data.summary || [];
            planningSummaryRows = rows;
            document.getElementById('planningSummaryBadge').textContent = `${formatNumber(rows.length)} satır`;

            if (!rows.length) {
                document.getElementById('planningBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Veri yok.</td></tr>';
                setPlanningStamp();
                return;
            }

            document.getElementById('planningBody').innerHTML = rows.map((summary, index) => {
                const urgency = summary.EnYakinKargo
                    ? `<span class="planning-chip">${escapeHtml(summary.EnYakinKargo)}</span>`
                    : '-';
                const status = summary.EslesenUrunNo > 0
                    ? '<span class="planning-chip">Eşleşmiş</span>'
                    : '<span class="planning-chip">Eşleşmemiş</span>';

                const producible = Number(summary.UretilebilirAdet || 0);
                const total = Number(summary.ToplamAdet || 0);
                const missing = Math.max(0, total - producible);
                const producibleHtml = producible >= total
                    ? `<span class="text-success fw-bold">${formatNumber(producible)}</span>`
                    : formatNumber(producible);
                const missingHtml = missing > 0
                    ? `<span class="text-danger fw-bold">${formatNumber(missing)}</span>`
                    : '<span class="text-success">Yok</span>';

                return `
                    <tr>
                        <td>
                            ${escapeHtml(summary.UrunAdi || '-')}
                            ${summary.sistemAdi ? `<br><small class="text-muted">${escapeHtml(summary.sistemAdi)}</small>` : ''}
                        </td>
                        <td class="text-center fw-bold">${formatNumber(total)}</td>
                        <td class="text-center">${producibleHtml}</td>
                        <td class="text-center">${missingHtml}</td>
                        <td class="text-center">${urgency}</td>
                        <td>${status}</td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="planWorkOrderByIndex(${index})">
                                <i class="bi bi-play me-1"></i>İş Emri
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            setPlanningStamp();
        });
}

function planWorkOrderByIndex(index) {
    const selectedDate = document.getElementById('tarihFilter').value;
    const summary = planningSummaryRows[index] || {};
    planWorkOrder(
        Number(summary.EslesenUrunNo || 0),
        Number(summary.ToplamAdet || 0),
        String(summary.EslesenUrunTur || ''),
        String(summary.UrunAdi || ''),
        selectedDate,
        Array.isArray(summary.SatirNolar) ? summary.SatirNolar : []
    );
}

function planWorkOrder(urunNo, adet, tur, urunAdi, selectedDate, satirNolar) {
    if (!urunNo || !tur) {
        Swal.fire('Eksik eşleşme', 'Bu ürün için iş emri oluşturulamıyor; önce eşleştirme gerekiyor.', 'warning');
        return;
    }

    if (!Array.isArray(satirNolar) || !satirNolar.length) {
        Swal.fire('Satır bulunamadı', 'Bu özet satırına bağlı sipariş satırı bulunamadı.', 'warning');
        return;
    }

    Swal.fire({
        title: 'İş emri oluşturulsun mu?',
        html: `
            <div class="text-start">
                <p><strong>Ürün:</strong> ${escapeHtml(urunAdi || '-')}</p>
                <p><strong>Adet:</strong> ${formatNumber(adet)}</p>
                <p><strong>Sipariş satırı:</strong> ${formatNumber(satirNolar.length)}</p>
                <p><strong>Referans tarih:</strong> ${escapeHtml(selectedDate || '-')}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet, oluştur',
        cancelButtonText: 'Vazgeç'
    }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
            title: 'İş emri oluşturuluyor...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        fetch('/SiparisApi.ashx?action=createOrderWorkOrders', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                satirNolar: satirNolar,
                tur: tur,
                stokDurum: 'StokDahil'
            })
        })
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) {
                    Swal.fire('Hata', data.message || 'İş emri oluşturulamadı.', 'error');
                    return;
                }

                const errors = Array.isArray(data.errors) && data.errors.length
                    ? `<br><small class="text-danger">${data.errors.map((error) => escapeHtml(error)).join('<br>')}</small>`
                    : '';
                const created = Number(data.created || 0);
                Swal.fire({
                    icon: created > 0 ? 'success' : 'warning',
                    title: created > 0 ? 'İş Emri Oluşturuldu' : 'İşlem Tamamlandı',
                    html: `${escapeHtml(data.message || 'İşlem tamamlandı.')}${errors}`,
                });
                loadSummary();
            })
            .catch(() => Swal.fire('Hata', 'İş emri oluşturulamadı.', 'error'));
    });
}

function normalizeDateInfo(value) {
    const text = String(value || '').trim().substring(0, 10);
    let match = text.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (match) {
        const [, year, month, day] = match;
        return {
            key: `${year}-${month}-${day}`,
            iso: `${year}-${month}-${day}`,
            label: `${day}/${month}/${year}`,
        };
    }

    match = text.match(/^(\d{2})[./-](\d{2})[./-](\d{4})$/);
    if (match) {
        const [, day, month, year] = match;
        return {
            key: `${year}-${month}-${day}`,
            iso: `${year}-${month}-${day}`,
            label: `${day}/${month}/${year}`,
        };
    }

    return {
        key: text || 'undated',
        iso: '',
        label: text || 'Tarihsiz',
    };
}

function dateSortValue(info) {
    if (!info.iso) return Number.MAX_SAFE_INTEGER;
    return new Date(`${info.iso}T00:00:00`).getTime();
}

function isApproved(value) {
    return ['1', 'true', 'evet', 'yes'].includes(String(value || '').trim().toLowerCase());
}

function isActiveProduction(value) {
    return ['0', 'false', 'hayir', 'hayır', 'no'].includes(String(value || '').trim().toLowerCase());
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function escapeCssValue(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(String(value || ''));
    }

    return String(value || '').replace(/["\\]/g, '\\$&');
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeJsString(value) {
    return String(value || '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\n/g, ' ');
}

/* ───── Özellik #3: Çoklu Personel Board'u ───── */
function loadMultiPersonnelTasks() {
    const deptId = document.getElementById('multiDeptSelect').value;
    const wrapper = document.getElementById('multiBoardWrapper');
    
    if (!deptId) {
        wrapper.innerHTML = `
            <div class="planning-empty-state" style="margin-top:40px">
                <i class="bi bi-diagram-3"></i>
                <strong>Bölüm Seçin</strong>
            </div>
        `;
        return;
    }

    wrapper.innerHTML = `<div class="text-center mt-5"><i class="bi bi-arrow-repeat" style="font-size:2rem;animation:spin 1s linear infinite;"></i><br>Bölüm görevleri yükleniyor...</div>`;

    fetch(`/api/planning/department/${deptId}/tasks`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                wrapper.innerHTML = `<div class="alert alert-danger mx-3 mt-3">${escapeHtml(data.message || 'Yüklenemedi')}</div>`;
                return;
            }

            const tasks = data.data || [];
            if (!tasks.length) {
                wrapper.innerHTML = `
                    <div class="planning-empty-state" style="margin-top:40px">
                        <i class="bi bi-inbox"></i>
                        <strong>Bu bölümde aktif görev bulunamadı.</strong>
                    </div>
                `;
                return;
            }

            // Görevleri personele göre grupla
            const pGroup = new Map();
            // Tüm departman personellerini de boş bile olsa çıkarabiliriz, ama şimdilik sadece görevi olanları veya depts map'den
            personnelData.forEach(p => {
                if (String(p.BolumAdiNo) === String(deptId)) {
                    pGroup.set(String(p.PersonelNo), {
                        name: p.PersonelAdi,
                        no: p.PersonelNo,
                        tasks: []
                    });
                }
            });

            tasks.forEach(t => {
                const pno = String(t.PersonelNo);
                if (!pGroup.has(pno)) {
                    pGroup.set(pno, { name: t.PersonelAdi || 'Bilinmiyor', no: pno, tasks: [] });
                }
                pGroup.get(pno).tasks.push(t);
            });

            renderMultiBoard(Array.from(pGroup.values()));
        })
        .catch(err => {
            wrapper.innerHTML = `<div class="alert alert-danger mx-3 mt-3">Hata: ${escapeHtml(String(err))}</div>`;
        });
}

function renderMultiBoard(personnelGroups) {
    const wrapper = document.getElementById('multiBoardWrapper');
    
    const html = `<div class="multi-board">` + personnelGroups.map(pg => {
        const taskCards = pg.tasks.map(task => {
            const dateInfo = normalizeDateInfo(task.GorevBaslamaTarihi);
            const isLate = dateInfo.iso && dateInfo.iso < todayISODate();
            const totalQty = parseInt(task.Adet, 10);
            const state = planningTaskState(task, totalQty, parseInt(task.BekleyenAdet, 10));
            const id = parseInt(task.No);
            
            return `
                <div class="planning-task-card ${isLate ? 'is-late' : ''}" draggable="true" data-task-id="${id}">
                    <div class="planning-task-header">
                        <span class="planning-task-no">#${id}</span>
                        ${isLate ? '<span class="planning-late-badge">Gecikti</span>' : ''}
                    </div>
                    <div class="planning-task-title">${escapeHtml(task.AraUrunAdi)}</div>
                    
                    <div class="planning-task-meta mt-1">
                        <span><i class="bi bi-calendar3"></i> ${escapeHtml(dateInfo.text)}</span>
                        <span><strong>${formatNumber(totalQty)}</strong> adet</span>
                    </div>

                    <div class="planning-task-status mt-2">
                        <div class="planning-status-badge ${state.className}">
                            <i class="bi ${state.icon}"></i> ${state.label}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        return `
            <div class="multi-col" data-personnel-no="${escapeHtml(pg.no)}">
                <div class="multi-col-header">
                    <span class="multi-col-title">${escapeHtml(pg.name)}</span>
                    <span class="multi-col-badge">${pg.tasks.length} Görev</span>
                </div>
                <div class="multi-task-list">
                    ${taskCards || '<div class="text-center text-muted" style="font-size:0.8rem;padding:20px;border:1px dashed #ccc;border-radius:6px">Görev yok</div>'}
                </div>
            </div>
        `;
    }).join('') + `</div>`;

    wrapper.innerHTML = html;
    bindMultiBoardDragDrop();
}

function bindMultiBoardDragDrop() {
    const wrapper = document.getElementById('multiBoardWrapper');
    
    wrapper.querySelectorAll('.planning-task-card').forEach(card => {
        card.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('is-dragging');
        });
    });

    wrapper.querySelectorAll('.multi-col').forEach(col => {
        col.addEventListener('dragover', (e) => {
            e.preventDefault();
            col.classList.add('is-drop-target');
        });
        col.addEventListener('dragleave', () => {
            col.classList.remove('is-drop-target');
        });
        col.addEventListener('drop', (e) => {
            e.preventDefault();
            col.classList.remove('is-drop-target');
            
            const taskId = e.dataTransfer.getData('text/plain');
            const targetPersonnelNo = col.dataset.personnelNo;
            
            if (!taskId || !targetPersonnelNo) return;
            
            const draggedCard = wrapper.querySelector(`.planning-task-card[data-task-id="${escapeCssValue(taskId)}"]`);
            const sourceCol = draggedCard?.closest('.multi-col');
            const sourcePersonnelNo = sourceCol?.dataset.personnelNo;

            if (sourcePersonnelNo === targetPersonnelNo) return; // Aynı kişiye bırakıldıysa işlem yapma
            
            transferTaskToPersonnel(taskId, targetPersonnelNo);
        });
    });
}

function transferTaskToPersonnel(taskId, newPersonnelNo) {
    Swal.fire({
        title: 'Transfer Ediliyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`/api/planning/task/${taskId}/transfer`, {
        method: 'POST',
        headers: csrfHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ target_personnel_no: newPersonnelNo })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            Swal.fire('Hata', data.message || 'Transfer başarısız.', 'error');
            return;
        }
        Swal.fire({ title: 'Transfer Başarılı', icon: 'success', timer: 1200, showConfirmButton: false });
        loadMultiPersonnelTasks(); // Board'u yenile
    })
    .catch(err => Swal.fire('Hata', String(err), 'error'));
}

/* ───── Özellik #8: Otomatik Yenileme (30sn) ───── */
let autoRefreshInterval = null;
let lastRefreshTimestamp = Date.now();
let refreshCounterInterval = null;

function startAutoRefresh() {
    stopAutoRefresh();

    autoRefreshInterval = setInterval(() => {
        // Duraklatma koşulları
        if (!currentPersonelNo) return;
        if (currentPlanningView !== 'personnel') return;
        if (document.querySelector('.swal2-container')) return;
        if (document.querySelector('.modal.show')) return;
        if (document.activeElement && ['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement.tagName)) return;

        loadPersonelTasks();
    }, 30000);

    refreshCounterInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - lastRefreshTimestamp) / 1000);
        const label = document.getElementById('autoRefreshLabel');
        if (!label) return;

        if (elapsed < 3) label.textContent = 'Az önce';
        else if (elapsed < 60) label.textContent = `${elapsed} sn önce`;
        else label.textContent = `${Math.floor(elapsed / 60)} dk önce`;
    }, 1000);

    const chip = document.getElementById('autoRefreshChip');
    if (chip) chip.style.display = '';
}

function stopAutoRefresh() {
    if (autoRefreshInterval) { clearInterval(autoRefreshInterval); autoRefreshInterval = null; }
    if (refreshCounterInterval) { clearInterval(refreshCounterInterval); refreshCounterInterval = null; }
    const chip = document.getElementById('autoRefreshChip');
    if (chip) chip.style.display = 'none';
}

function markRefreshTimestamp() {
    lastRefreshTimestamp = Date.now();
    const label = document.getElementById('autoRefreshLabel');
    if (label) label.textContent = 'Az önce';
}



/* ───── Özellik #10: Klavye Kısayolları ───── */
function initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Input/select alanında iken kısayolları devre dışı bırak
        const tag = (e.target.tagName || '').toUpperCase();
        if (['INPUT', 'SELECT', 'TEXTAREA'].includes(tag)) return;
        // SweetAlert açıkken devre dışı bırak
        if (document.querySelector('.swal2-container')) return;

        switch (e.key) {
            case 'r':
            case 'R':
                e.preventDefault();
                loadCurrentPlanningTab();
                break;

            case '/':
                e.preventDefault();
                pssOpen();
                break;

            case 'n':
            case 'N':
                e.preventDefault();
                tarihKutusuEkle();
                break;

            case 'ArrowLeft':
                e.preventDefault();
                navigatePersonnel(-1);
                break;

            case 'ArrowRight':
                e.preventDefault();
                navigatePersonnel(1);
                break;
        }
    });
}

function navigatePersonnel(direction) {
    const select = document.getElementById('personelSelect');
    if (!select || select.options.length < 2) return;

    let idx = select.selectedIndex + direction;
    // İlk option "Personel Seçin" placeholder'ı, atla
    if (idx < 1) idx = select.options.length - 1;
    if (idx >= select.options.length) idx = 1;

    select.selectedIndex = idx;
    handlePersonnelChange();
}

document.addEventListener('DOMContentLoaded', () => {
    pssInit();
    loadPersonelList();
    startAutoRefresh();
    initKeyboardShortcuts();
});
</script>
@endpush
