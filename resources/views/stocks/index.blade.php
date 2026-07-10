@extends('layouts.app')

@section('title', 'Stok Yönetimi')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetBuffer()">
        <i class="bi bi-layers me-1"></i>Tamponu Eşitle
    </button>
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportData()">
        <i class="bi bi-download me-1"></i>Dışa Aktar
    </button>
    <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer;">
        <i class="bi bi-upload me-1"></i>İçe Aktar
        <input type="file" id="importFile" accept=".xlsx,.xls,.csv,.txt" style="display:none;" onchange="importStockFile(this)">
    </label>
    <button type="button" class="btn btn-primary btn-sm" onclick="loadStocks(1)">
        <i class="bi bi-arrow-clockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    /* ─── Metrics ─── */
    .sk-metrics {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }
    .sk-metric {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 16px 18px;
        text-align: center;
    }
    .sk-metric-num {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--z-text);
        line-height: 1.2;
    }
    .sk-metric-num.accent { color: var(--z-accent); }
    .sk-metric-num.success { color: var(--z-success); }
    .sk-metric-num.warning { color: var(--z-warning); }
    .sk-metric-num.danger { color: var(--z-danger); }
    .sk-metric-label {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--z-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 4px;
    }

    /* ─── Quick Filter Chips ─── */
    .sk-chips {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .sk-chip {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        border: 1.5px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        transition: all 0.15s ease;
        user-select: none;
    }
    .sk-chip:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
    }
    .sk-chip.active {
        background: var(--z-accent);
        color: white;
        border-color: var(--z-accent);
    }
    .sk-chip .chip-count {
        display: inline-block;
        background: rgba(255,255,255,0.25);
        border-radius: 10px;
        padding: 1px 7px;
        font-size: 0.72rem;
        margin-left: 4px;
    }
    .sk-chip:not(.active) .chip-count {
        background: var(--z-bg-soft);
    }

    /* ─── Filter Bar ─── */
    .sk-filter-row {
        display: grid;
        grid-template-columns: 1fr 180px 180px 100px;
        gap: 12px;
        align-items: end;
    }

    /* ─── Stock Cards ─── */
    .sk-cards {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .sk-card {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 16px 20px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 16px;
        align-items: center;
        transition: all 0.15s ease;
    }
    .sk-card:hover {
        border-color: #d1d5db;
        box-shadow: var(--z-shadow-hover);
    }
    .sk-card.critical {
        border-left: 3px solid var(--z-danger);
    }
    .sk-card.low {
        border-left: 3px solid var(--z-warning);
    }

    .sk-card-info {
        min-width: 0;
    }
    .sk-card-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--z-text);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .sk-card-meta {
        font-size: 0.78rem;
        color: var(--z-text-muted);
        margin-top: 3px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }
    .sk-card-meta span {
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .sk-card-meta i { font-size: 0.72rem; opacity: 0.5; }

    /* Stock bar */
    .sk-bar-wrap {
        margin-top: 10px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
    }
    .sk-bar-track {
        height: 8px;
        background: var(--z-border-light, #e5e7eb);
        border-radius: 4px;
        overflow: hidden;
        position: relative;
    }
    .sk-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s ease;
    }
    .sk-bar-fill.green { background: var(--z-success); }
    .sk-bar-fill.yellow { background: var(--z-warning); }
    .sk-bar-fill.red { background: var(--z-danger); }

    .sk-bar-legend {
        display: flex;
        gap: 16px;
        font-size: 0.75rem;
        color: var(--z-text-secondary);
        margin-top: 2px;
    }
    .sk-bar-legend strong { color: var(--z-text); }

    /* Right side numbers */
    .sk-card-right {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-shrink: 0;
    }
    .sk-avail-pill {
        padding: 6px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.88rem;
        white-space: nowrap;
    }
    .sk-avail-pill.ok {
        background: var(--z-success-soft, #dcfce7);
        color: var(--z-success);
    }
    .sk-avail-pill.low {
        background: var(--z-warning-soft, #fef3c7);
        color: var(--z-warning);
    }
    .sk-avail-pill.critical {
        background: var(--z-danger-soft, #ffe4e6);
        color: var(--z-danger);
    }

    /* ─── Inline Edit ─── */
    .sk-edit-row {
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        padding-top: 12px;
        margin-top: 12px;
        border-top: 1px solid var(--z-border-light);
    }
    .sk-edit-row .btn { height: 38px; }

    /* ─── Pager ─── */
    .sk-pager {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        padding: 12px 0;
        flex-wrap: wrap;
    }
    .sk-pager-pages {
        display: flex;
        gap: 4px;
        align-items: center;
    }
    .sk-page-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-sm);
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .sk-page-btn:hover { border-color: var(--z-accent); color: var(--z-accent); }
    .sk-page-btn.active { background: var(--z-accent); color: white; border-color: var(--z-accent); }
    .sk-page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ─── View Toggle ─── */
    .sk-view-toggle {
        display: inline-flex;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-sm);
        overflow: hidden;
    }
    .sk-view-btn {
        padding: 6px 12px;
        border: none;
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        cursor: pointer;
        font-size: 0.82rem;
        transition: all 0.15s ease;
    }
    .sk-view-btn.active {
        background: var(--z-accent);
        color: white;
    }

    /* ─── Table Mode ─── */
    .sk-table-wrap { display: none; }
    .sk-table-wrap.active { display: block; }
    .sk-cards-wrap { display: block; }
    .sk-cards-wrap.hidden { display: none; }

    .sk-table .sortable { cursor: pointer; user-select: none; }
    .sk-table .sortable:hover { color: var(--z-accent); }
    .sk-table .sortable.sort-asc::after { content: ' ↑'; color: var(--z-accent); }
    .sk-table .sortable.sort-desc::after { content: ' ↓'; color: var(--z-accent); }

    .sk-action-group {
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }

    .sk-production-cell {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 86px;
    }
    .sk-production-total,
    .sk-production-free {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 8px;
        padding: 3px 8px;
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.15;
        white-space: nowrap;
    }
    .sk-production-total {
        background: #fff7ed;
        color: #c2410c;
    }
    .sk-production-free {
        background: #ecfdf5;
        color: #047857;
    }
    .sk-production-free.empty {
        background: #f3f4f6;
        color: var(--z-text-muted);
    }

    .sk-icon-btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    /* ─── Stock Ledger ─── */
    .ledger-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.34);
        z-index: 1080;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.16s ease;
    }
    .ledger-backdrop.open {
        opacity: 1;
        pointer-events: auto;
    }
    .ledger-panel {
        position: absolute;
        top: 0;
        right: 0;
        width: min(760px, 100vw);
        height: 100%;
        background: var(--z-bg-card);
        border-left: 1px solid var(--z-border);
        box-shadow: -18px 0 44px rgba(15, 23, 42, 0.18);
        display: flex;
        flex-direction: column;
        transform: translateX(100%);
        transition: transform 0.18s ease;
    }
    .ledger-backdrop.open .ledger-panel {
        transform: translateX(0);
    }
    .ledger-head {
        padding: 18px 20px;
        border-bottom: 1px solid var(--z-border);
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: start;
    }
    .ledger-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--z-text);
        line-height: 1.3;
    }
    .ledger-meta {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 8px;
    }
    .ledger-tools {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .ledger-body {
        padding: 16px 20px 20px;
        overflow: auto;
        flex: 1;
        background: var(--z-bg-soft, #f8fafc);
    }
    .ledger-summary {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 14px;
    }
    .ledger-summary-item {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 10px 12px;
    }
    .ledger-summary-value {
        font-weight: 800;
        color: var(--z-text);
    }
    .ledger-summary-label {
        font-size: 0.72rem;
        color: var(--z-text-muted);
        margin-top: 2px;
    }
    .ledger-explain {
        display: grid;
        gap: 10px;
        margin-bottom: 14px;
    }
    .ledger-equation {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 12px;
    }
    .ledger-equation-line {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        color: var(--z-text-secondary);
        font-size: 0.84rem;
    }
    .ledger-equation-value {
        display: inline-flex;
        align-items: baseline;
        gap: 4px;
        font-weight: 800;
        color: var(--z-text);
    }
    .ledger-equation-result {
        color: var(--z-warning);
    }
    .ledger-net-line {
        margin-top: 8px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .ledger-net-chip {
        display: inline-flex;
        gap: 5px;
        align-items: center;
        border: 1px solid var(--z-border);
        border-radius: 999px;
        padding: 3px 8px;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        font-size: 0.73rem;
        font-weight: 700;
    }
    .ledger-net-chip strong { color: var(--z-text); }
    .ledger-reading {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 12px;
        display: grid;
        gap: 10px;
    }
    .ledger-reading-title {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--z-text);
    }
    .ledger-reading-list {
        display: grid;
        gap: 7px;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .ledger-reading-list li {
        display: grid;
        grid-template-columns: 22px 1fr;
        gap: 8px;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        line-height: 1.45;
    }
    .ledger-reading-list i {
        color: var(--z-primary);
        margin-top: 2px;
    }
    .ledger-reading-alert {
        border: 1px solid var(--z-warning-soft);
        background: var(--z-warning-soft);
        color: #92400e;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 0.76rem;
        font-weight: 700;
    }
    .ledger-breakdown {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 12px;
    }
    .ledger-breakdown-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: center;
        margin-bottom: 8px;
    }
    .ledger-breakdown-title {
        margin: 0;
        font-size: 0.82rem;
        font-weight: 800;
        color: var(--z-text);
    }
    .ledger-source-list {
        display: grid;
        gap: 6px;
    }
    .ledger-source-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        border-top: 1px solid var(--z-border-light);
        padding-top: 7px;
        font-size: 0.78rem;
    }
    .ledger-source-row:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .ledger-source-label {
        min-width: 0;
        color: var(--z-text);
        font-weight: 700;
    }
    .ledger-source-meta {
        margin-top: 2px;
        color: var(--z-text-muted);
        font-weight: 500;
    }
    .ledger-source-note {
        font-size: 0.72rem;
        color: var(--z-text-secondary);
        margin-top: 3px;
    }
    .ledger-source-delta {
        font-weight: 900;
        white-space: nowrap;
        color: var(--z-warning);
    }
    .ledger-context {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 12px;
    }
    .ledger-context-grid {
        display: grid;
        gap: 8px;
    }
    .ledger-context-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: start;
        border-top: 1px solid var(--z-border-light);
        padding-top: 8px;
    }
    .ledger-context-row:first-child {
        border-top: 0;
        padding-top: 0;
    }
    .ledger-context-title {
        font-size: 0.78rem;
        font-weight: 800;
        color: var(--z-text);
    }
    .ledger-context-meta {
        font-size: 0.72rem;
        color: var(--z-text-muted);
        margin-top: 2px;
    }
    .ledger-context-qty {
        font-size: 0.78rem;
        font-weight: 800;
        color: var(--z-text);
        white-space: nowrap;
    }
    .ledger-filters {
        display: grid;
        grid-template-columns: 1fr 150px 132px 132px auto;
        gap: 10px;
        align-items: end;
        margin-bottom: 14px;
    }
    .ledger-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .ledger-row {
        display: grid;
        grid-template-columns: 36px 1fr auto;
        gap: 12px;
        align-items: start;
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        padding: 12px;
    }
    .ledger-icon {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
    }
    .ledger-icon.in { background: var(--z-success-soft); color: var(--z-success); }
    .ledger-icon.out { background: var(--z-danger-soft); color: var(--z-danger); }
    .ledger-icon.reserve { background: var(--z-warning-soft); color: var(--z-warning); }
    .ledger-icon.release { background: #dbeafe; color: #2563eb; }
    .ledger-row-title {
        font-weight: 700;
        color: var(--z-text);
        margin-bottom: 4px;
    }
    .ledger-row-meta {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        color: var(--z-text-muted);
        font-size: 0.78rem;
    }
    .ledger-balance-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 6px;
        margin-top: 10px;
    }
    .ledger-balance-pill {
        border: 1px solid var(--z-border-light);
        border-radius: var(--z-radius-sm);
        background: var(--z-bg-soft);
        padding: 7px 8px;
        min-width: 0;
    }
    .ledger-balance-label {
        color: var(--z-text-muted);
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .ledger-balance-values {
        margin-top: 2px;
        color: var(--z-text);
        font-size: 0.76rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .ledger-balance-delta {
        color: var(--z-text-muted);
        margin-left: 4px;
    }
    .ledger-balance-delta.pos { color: var(--z-success); }
    .ledger-balance-delta.neg { color: var(--z-danger); }
    .ledger-reason {
        margin-top: 8px;
        color: var(--z-text-secondary);
        font-size: 0.78rem;
        line-height: 1.45;
    }
    .ledger-delta {
        text-align: right;
        font-weight: 800;
        white-space: nowrap;
    }
    .ledger-delta.in { color: var(--z-success); }
    .ledger-delta.out { color: var(--z-danger); }
    .ledger-delta.reserve { color: var(--z-warning); }
    .ledger-delta.release { color: #2563eb; }

    /* ─── Empty ─── */
    .sk-empty {
        text-align: center;
        padding: 48px 24px;
        color: var(--z-text-muted);
    }
    .sk-empty i { font-size: 2.2rem; display: block; margin-bottom: 12px; opacity: 0.4; }

    /* ─── Responsive ─── */
    @media (max-width: 900px) {
        .sk-metrics { grid-template-columns: repeat(3, 1fr); }
        .sk-filter-row { grid-template-columns: 1fr 1fr; }
        .sk-card { grid-template-columns: 1fr; }
        .sk-edit-row { grid-template-columns: 1fr; }
        .ledger-summary { grid-template-columns: repeat(2, 1fr); }
        .ledger-filters { grid-template-columns: 1fr 1fr; }
        .ledger-balance-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .sk-metrics { grid-template-columns: repeat(2, 1fr); }
        .sk-filter-row { grid-template-columns: 1fr; }
        .sk-chips { gap: 6px; }
        .ledger-panel { width: 100vw; }
        .ledger-head { grid-template-columns: 1fr; }
        .ledger-filters { grid-template-columns: 1fr; }
        .ledger-row { grid-template-columns: 32px 1fr; }
        .ledger-delta { grid-column: 2; text-align: left; }
    }
</style>
@endpush

@section('content')
    {{-- ── Metrics ── --}}
    <div class="sk-metrics">
        <div class="sk-metric">
            <div class="sk-metric-num" id="metricTotal">0</div>
            <div class="sk-metric-label">Toplam Kayıt</div>
        </div>
        <div class="sk-metric">
            <div class="sk-metric-num accent" id="metricStock">0</div>
            <div class="sk-metric-label">Depodaki Toplam</div>
        </div>
        <div class="sk-metric">
            <div class="sk-metric-num warning" id="metricBuffer">0</div>
            <div class="sk-metric-label">Görevdeki (Ayrılmış)</div>
        </div>
        <div class="sk-metric">
            <div class="sk-metric-num success" id="metricAvail">0</div>
            <div class="sk-metric-label">Boşta Kalan</div>
        </div>
        <div class="sk-metric">
            <div class="sk-metric-num danger" id="metricCritical">0</div>
            <div class="sk-metric-label">Kritik Satır</div>
        </div>
    </div>

    {{-- ── Quick Type Chips ── --}}
    <div class="sk-chips" id="typeChips">
        <div class="sk-chip active" data-type="" onclick="selectTypeChip(this)">Tümü</div>
        {{-- Filled dynamically --}}
    </div>

    {{-- ── Filter Bar ── --}}
    <section class="panel-surface" style="margin-bottom: 16px;">
        <div class="sk-filter-row">
            <div>
                <label class="form-label"><i class="bi bi-search me-1"></i>Ara</label>
                <input id="searchInput" class="form-control" placeholder="Ürün adı, bölüm veya No yazın...">
            </div>
            <div>
                <label class="form-label">Bölüm</label>
                <select id="deptFilter" class="form-select">
                    <option value="">Tümü</option>
                </select>
            </div>
            <div>
                <label class="form-label">Durum</label>
                <select id="statusFilter" class="form-select">
                    <option value="">Tümü</option>
                    <option value="zero">Stok 0 olanlar</option>
                    <option value="nonzero">Stok 0 olmayanlar</option>
                </select>
            </div>
            <div>
                <label class="form-label">Sayfa</label>
                <select id="pageSizeSelect" class="form-select">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 align-items-center">
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" id="onlyCritical">
                <label class="form-check-label" for="onlyCritical">Sadece Kritik</label>
            </div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" id="onlyZeroBuffer">
                <label class="form-check-label" for="onlyZeroBuffer">Boşta/Tampon = 0</label>
            </div>
            <div style="margin-left:auto;" class="d-flex gap-2 align-items-center">
                <div class="sk-view-toggle">
                    <button class="sk-view-btn" onclick="setView('card', this)" title="Kart"><i class="bi bi-grid-3x2-gap"></i></button>
                    <button class="sk-view-btn active" onclick="setView('table', this)" title="Tablo"><i class="bi bi-list-ul"></i></button>
                </div>
                <span class="soft-badge">Son: <strong id="lastRefresh">—</strong></span>
            </div>
        </div>
    </section>

    {{-- ── Stock Feed ── --}}
    <section class="panel-surface">
        <div class="section-header compact">
            <div>
                <div class="section-overline">Depodaki Stoklar</div>
                <h3 class="section-title" id="feedTitle">Departman Stok Havuzu</h3>
                <p class="section-copy" id="feedSub">Tüm bölümlerin stok durumu gösterilir.</p>
            </div>
            <span class="soft-badge" id="pageInfo">0-0 / 0</span>
        </div>

        {{-- Card View --}}
        <div id="cardView" class="sk-cards-wrap hidden">
            <div class="sk-cards" id="stockCards">
                <div class="sk-empty"><i class="bi bi-hourglass-split"></i><p>Yükleniyor...</p></div>
            </div>
        </div>

        {{-- Table View --}}
        <div id="tableView" class="sk-table-wrap active">
            <div class="table-shell">
                <table class="table-modern sk-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="No" onclick="sortBy('No')">No</th>
                            <th class="sortable" data-sort="BolumAdi" onclick="sortBy('BolumAdi')">Bölüm</th>
                            <th class="sortable" data-sort="AraUrunAdi" onclick="sortBy('AraUrunAdi')">Ara Ürün</th>
                            <th>Çeşit</th>
                            <th class="sortable" data-sort="Adet" onclick="sortBy('Adet')">Depodaki</th>
                            <th>Görevdeki</th>
                            <th class="sortable" data-sort="TamponMiktar" onclick="sortBy('TamponMiktar')">Boşta</th>
                            <th title="Üretimdeki toplam miktar ve GİED için müsait kalan adet">Üretimde / Müsait</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        <tr><td colspan="9" class="text-center py-4 text-muted">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pager --}}
        <div class="sk-pager">
            <div class="text-muted small" id="pagerInfo">Aktif filtrelere göre güncellenir.</div>
            <div class="sk-pager-pages" id="pagerControls"></div>
        </div>
    </section>

    <div class="ledger-backdrop" id="stockLedgerBackdrop" aria-hidden="true" onclick="closeLedger()">
        <aside class="ledger-panel" role="dialog" aria-modal="true" aria-labelledby="ledgerTitle" onclick="event.stopPropagation()">
            <div class="ledger-head">
                <div>
                    <div class="section-overline">Stok Ekstresi</div>
                    <h3 class="ledger-title" id="ledgerTitle">-</h3>
                    <div class="ledger-meta" id="ledgerMeta"></div>
                </div>
                <div class="ledger-tools">
                    <button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="exportLedger()" title="Dışa aktar"><i class="bi bi-download"></i></button>
                    <button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="closeLedger()" title="Kapat"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="ledger-body">
                <div class="ledger-summary" id="ledgerSummary"></div>
                <div class="ledger-explain" id="ledgerExplain"></div>
                <div class="ledger-filters">
                    <div>
                        <label class="form-label">Ara</label>
                        <input id="ledgerSearch" class="form-control" placeholder="Kaynak, kullanıcı veya açıklama">
                    </div>
                    <div>
                        <label class="form-label">Yön</label>
                        <select id="ledgerDirection" class="form-select">
                            <option value="">Tümü</option>
                            <option value="in">Giriş</option>
                            <option value="out">Çıkış</option>
                            <option value="reserve">Ayırma</option>
                            <option value="release">İade</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Başlangıç</label>
                        <input id="ledgerDateFrom" type="date" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Bitiş</label>
                        <input id="ledgerDateTo" type="date" class="form-control">
                    </div>
                    <button class="btn btn-primary" onclick="loadLedger()"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button>
                </div>
                <div class="ledger-list" id="ledgerList">
                    <div class="sk-empty"><i class="bi bi-hourglass-split"></i><p>Yükleniyor...</p></div>
                </div>
            </div>
        </aside>
    </div>

    {{-- Hidden compat --}}
    <span id="summaryRecords" style="display:none;">0</span>
    <span id="summaryAvailable" style="display:none;">0</span>
    <span id="summaryBuffer" style="display:none;">0</span>
    <span id="summaryCritical" style="display:none;">0</span>
    <span id="summaryRecordsInline" style="display:none;">0</span>
    <span id="summaryAvailableInline" style="display:none;">0</span>
    <span id="summaryCriticalInline" style="display:none;">0</span>
    <span id="summaryRecordsMeta" style="display:none;">0</span>
    <span id="summaryAvailableMeta" style="display:none;">0</span>
    <span id="summaryBufferMeta" style="display:none;">0</span>
    <span id="summaryCriticalMeta" style="display:none;">0</span>
    <span id="summaryFocus" style="display:none;">Tüm stoklar</span>
    <span id="summaryUpdatedInline" style="display:none;">—</span>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ───── State ───── */
let currentPage = 1;
let pageSize = 20;
let totalRecords = 0;
let currentSort = 'No';
let currentDir = 'asc';
let currentRows = [];
let allTypeCounts = {};
let editingId = null;
let currentView = 'table';
let currentLedgerStock = null;
let editedBufferInputs = new Set();
let csrfToken = document.querySelector('meta[name="csrf-token"]').content;

/* ───── Helpers ───── */
function esc(v) { return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function fmt(n) { return Number(n || 0).toLocaleString('tr-TR'); }
function signed(n) { const value = Number(n || 0); return `${value > 0 ? '+' : ''}${fmt(value)}`; }
function signedClass(n) {
    const value = Number(n || 0);
    return value > 0 ? 'pos' : (value < 0 ? 'neg' : '');
}
function hasValue(v) { return v !== null && v !== undefined && v !== ''; }
function fmtDate(v) {
    if (!v) return '-';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return esc(v);
    return d.toLocaleString('tr-TR', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}
function ledgerIcon(direction) {
    if (direction === 'in') return 'bi-arrow-down-left';
    if (direction === 'out') return 'bi-arrow-up-right';
    if (direction === 'reserve') return 'bi-lock';
    if (direction === 'release') return 'bi-unlock';
    return 'bi-clock-history';
}
function movementSource(row) {
    if (row.source_label) return row.source_label;
    const parts = [];
    if (row.order_no) parts.push(`Sipariş ${row.order_no}`);
    if (row.order_item_no) parts.push(`Satır #${row.order_item_no}`);
    if (row.work_order_no) parts.push(`İş emri #${row.work_order_no}`);
    if (row.personnel_task_no) parts.push(`Görev #${row.personnel_task_no}`);
    if (!parts.length && row.source_type) {
        const legacyComponentRef = ['work_order_buffer_reserved', 'work_order_buffer_released', 'pool_buffer_released'].includes(row.movement_type)
            && row.source_id && Number(row.source_id) === Number(row.component_no || 0);
        parts.push(legacyComponentRef ? `Ara ürün #${row.source_id}` : `${row.source_type}${row.source_id ? ' #' + row.source_id : ''}`);
    }
    return parts.join(' · ');
}
function balancePill(label, before, after, delta) {
    const beforeLabel = hasValue(before) ? fmt(before) : '-';
    const afterLabel = hasValue(after) ? fmt(after) : '-';
    return `
        <div class="ledger-balance-pill">
            <div class="ledger-balance-label">${esc(label)}</div>
            <div class="ledger-balance-values">
                ${beforeLabel} → ${afterLabel}
                <span class="ledger-balance-delta ${signedClass(delta)}">${signed(delta)}</span>
            </div>
        </div>`;
}

/* ───── Init ───── */
document.addEventListener('DOMContentLoaded', async () => {
    await loadLookups();
    await loadStocks(1);

    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') loadStocks(1); });
    document.getElementById('searchInput').addEventListener('input', debounce(() => loadStocks(1), 400));
    document.getElementById('deptFilter').addEventListener('change', () => loadStocks(1));
    document.getElementById('statusFilter').addEventListener('change', () => loadStocks(1));
    document.getElementById('pageSizeSelect').addEventListener('change', () => { pageSize = parseInt(document.getElementById('pageSizeSelect').value, 10); loadStocks(1); });
    document.getElementById('onlyCritical').addEventListener('change', () => loadStocks(1));
    document.getElementById('onlyZeroBuffer').addEventListener('change', () => loadStocks(1));
    document.getElementById('ledgerSearch').addEventListener('input', debounce(() => loadLedger(), 350));
    document.getElementById('ledgerDirection').addEventListener('change', () => loadLedger());
    document.getElementById('ledgerDateFrom').addEventListener('change', () => loadLedger());
    document.getElementById('ledgerDateTo').addEventListener('change', () => loadLedger());
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLedger(); });
});

function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }

/* ───── Lookups ───── */
async function loadLookups() {
    try {
        const r = await fetch('/api/stocks/lookups', { cache: 'no-store' });
        const d = await r.json();

        // Department filter
        const deptSel = document.getElementById('deptFilter');
        (d.departments || []).forEach(dept => {
            deptSel.insertAdjacentHTML('beforeend', `<option value="${dept.id}">${esc(dept.name)}</option>`);
        });

        // Type chips
        const types = d.componentTypes || [];
        buildTypeChips(types);
    } catch {}
}

function buildTypeChips(types) {
    // We'll update counts after data loads
    const container = document.getElementById('typeChips');
    types.forEach(t => {
        container.insertAdjacentHTML('beforeend', `<div class="sk-chip" data-type="${esc(t)}" onclick="selectTypeChip(this)">${esc(t)}<span class="chip-count" id="chip-count-${t.replace(/\s/g,'_')}">0</span></div>`);
    });
}

function selectTypeChip(el) {
    document.querySelectorAll('.sk-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    loadStocks(1);
}

function getActiveType() {
    return document.querySelector('.sk-chip.active')?.dataset.type || '';
}

/* ───── Load Stocks ───── */
async function loadStocks(page) {
    currentPage = page || 1;
    const params = new URLSearchParams({
        page: currentPage,
        per_page: pageSize,
        sort_by: currentSort,
        sort_dir: currentDir
    });

    const dept = document.getElementById('deptFilter').value;
    const search = document.getElementById('searchInput').value.trim();
    const type = getActiveType();
    const status = document.getElementById('statusFilter').value;

    if (dept) params.set('department_id', dept);
    if (search) params.set('search', search);
    if (type) params.set('component_type', type);
    if (status) params.set('stock_status', status);

    try {
        const r = await fetch(`/api/stocks?${params.toString()}`, { cache: 'no-store' });
        const d = await r.json();
        currentRows = d.data || [];
        totalRecords = Number(d.total || 0);
        currentPage = Number(d.current_page || 1);
        pageSize = Number(d.per_page || pageSize);

        // Client-side status filtering
        let filtered = applyClientFilters(currentRows);

        renderCards(filtered);
        renderTable(filtered);
        updateMetrics(filtered, totalRecords);
        renderPager(totalRecords, currentPage, pageSize);
        updateChipCounts(currentRows);
        setTimestamp();
    } catch {
        document.getElementById('stockCards').innerHTML = '<div class="sk-empty"><i class="bi bi-exclamation-triangle"></i><p>Stok verisi yüklenemedi.</p></div>';
    }
}

function applyClientFilters(rows) {
    const status = document.getElementById('statusFilter').value;
    const onlyCritical = document.getElementById('onlyCritical').checked;
    const onlyZeroBuffer = document.getElementById('onlyZeroBuffer').checked;

    return rows.filter(s => {
        const avail = freeStock(s);
        if (onlyCritical && avail > 0) return false;
        if (onlyZeroBuffer && freeStock(s) > 0) return false;
        if (status === 'zero' && stockTotal(s) !== 0) return false;
        if (status === 'nonzero' && stockTotal(s) === 0) return false;
        return true;
    });
}

function stockTotal(row) {
    return Number(row?.Adet || 0);
}

function freeStock(row) {
    const total = stockTotal(row);
    const tampon = Number(row?.TamponMiktar || 0);
    return Math.max(0, Math.min(total, tampon));
}

function reservedStock(row) {
    return Math.max(0, stockTotal(row) - freeStock(row));
}
function stockNo(row) {
    return Number(row?.No || row?.StockNo || 0);
}
function hasStockRow(row) {
    return stockNo(row) > 0;
}
function stockKey(row) {
    const no = stockNo(row);
    if (no > 0) return `stock-${no}`;
    return `component-${Number(row?.AraUrunAdiNo || 0)}-${Number(row?.BolumAdiNo || 0)}`;
}
function findStockRow(key) {
    return currentRows.find(row => stockKey(row) === key) || null;
}
function displayStockNo(row) {
    const no = stockNo(row);
    return no > 0 ? no : '-';
}
function productionTotal(row) {
    return Number(row?.UretimToplamAdet || 0);
}
function productionAvailable(row) {
    return Number(row?.UretimMusaitAdet || 0);
}
function renderProductionAvailability(row) {
    const total = productionTotal(row);
    const available = productionAvailable(row);
    if (total <= 0 && available <= 0) {
        return '<span class="text-muted">—</span>';
    }

    const availableClass = available > 0 ? 'sk-production-free' : 'sk-production-free empty';
    return `
        <span class="sk-production-cell">
            <span class="sk-production-total" title="Üretimdeki toplam miktar"><i class="bi bi-hammer"></i>${fmt(total)}</span>
            <span class="${availableClass}" title="GİED için müsait miktar">Müsait ${fmt(available)}</span>
        </span>`;
}

function updateChipCounts(rows) {
    const counts = {};
    rows.forEach(s => {
        const t = s.UrunCesidi || '';
        counts[t] = (counts[t] || 0) + 1;
    });
    allTypeCounts = counts;
    document.querySelectorAll('.sk-chip[data-type]').forEach(chip => {
        const t = chip.dataset.type;
        const countEl = chip.querySelector('.chip-count');
        if (countEl) {
            countEl.textContent = t === '' ? totalRecords : (counts[t] || 0);
        }
    });
    // Update "Tümü" chip
    const allChip = document.querySelector('.sk-chip[data-type=""]');
    if (allChip) {
        const countEl = allChip.querySelector('.chip-count');
        if (!countEl) {
            allChip.insertAdjacentHTML('beforeend', `<span class="chip-count">${totalRecords}</span>`);
        } else {
            countEl.textContent = totalRecords;
        }
    }
}

/* ───── Metrics ───── */
function updateMetrics(rows, total) {
    const stockTotalValue = rows.reduce((s, r) => s + stockTotal(r), 0);
    const bufferTotal = rows.reduce((s, r) => s + reservedStock(r), 0);
    const availTotal = rows.reduce((s, r) => s + freeStock(r), 0);
    const criticalCount = rows.filter(r => freeStock(r) <= 0).length;

    document.getElementById('metricTotal').textContent = fmt(total);
    document.getElementById('metricStock').textContent = fmt(stockTotalValue);
    document.getElementById('metricBuffer').textContent = fmt(bufferTotal);
    document.getElementById('metricAvail').textContent = fmt(availTotal);
    document.getElementById('metricCritical').textContent = fmt(criticalCount);

    // Compat
    ['summaryRecords','summaryRecordsInline','summaryRecordsMeta'].forEach(id => { const e = document.getElementById(id); if (e) e.textContent = fmt(total); });
    ['summaryAvailable','summaryAvailableInline','summaryAvailableMeta'].forEach(id => { const e = document.getElementById(id); if (e) e.textContent = fmt(availTotal); });
    ['summaryBuffer','summaryBufferMeta'].forEach(id => { const e = document.getElementById(id); if (e) e.textContent = fmt(bufferTotal); });
    ['summaryCritical','summaryCriticalInline','summaryCriticalMeta'].forEach(id => { const e = document.getElementById(id); if (e) e.textContent = fmt(criticalCount); });
}

/* ───── Render Cards ───── */
function renderCards(rows) {
    const container = document.getElementById('stockCards');

    if (!rows.length) {
        container.innerHTML = '<div class="sk-empty"><i class="bi bi-inbox"></i><p>Stok kaydı bulunamadı.</p></div>';
        return;
    }

    container.innerHTML = rows.map(s => {
        const key = stockKey(s);
        const realStockRow = hasStockRow(s);
        const adet = stockTotal(s);
        const tampon = freeStock(s);
        const gorevdeki = reservedStock(s);
        const avail = tampon;
        const pct = adet > 0 ? Math.round((avail / adet) * 100) : 0;

        // Status
        let barColor = 'green', pillClass = 'ok', cardClass = '';
        if (avail <= 0) {
            barColor = 'red'; pillClass = 'critical'; cardClass = 'critical';
        } else if (pct < 30) {
            barColor = 'yellow'; pillClass = 'low'; cardClass = 'low';
        }

        const isEditing = editingId === key;

        return `
        <div class="sk-card ${cardClass}">
            <div class="sk-card-info">
                <h4 class="sk-card-title">
                    ${esc(s.AraUrunAdi || '-')}
                    <span class="soft-badge">${esc(s.UrunCesidi || '-')}</span>
                    ${avail <= 0 ? '<span class="soft-badge danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Kritik</span>' : ''}
                </h4>
                <div class="sk-card-meta">
                    <span><i class="bi bi-building"></i>${esc(s.BolumAdi || '-')}</span>
                    <span><i class="bi bi-hash"></i>Stok No: ${displayStockNo(s)}</span>
                    <span><i class="bi bi-box-seam"></i>Ürün No: ${esc(s.AraUrunAdiNo || '-')}</span>
                </div>
                <div class="sk-bar-wrap">
                    <div class="sk-bar-track">
                        <div class="sk-bar-fill ${barColor}" style="width:${pct}%"></div>
                    </div>
                    <div class="sk-bar-legend">
                        <span>Depodaki: <strong>${fmt(adet)}</strong></span>
                        <span>Görevdeki: <strong>${fmt(gorevdeki)}</strong></span>
                        <span>Boşta: <strong>${fmt(tampon)}</strong></span>
                    </div>
                </div>
            </div>
            <div class="sk-card-right">
                <div class="sk-avail-pill ${pillClass}">${fmt(avail)} boşta</div>
                <div class="sk-action-group">
                    ${realStockRow
                        ? `<button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="openLedger(${stockNo(s)})" title="Ekstre"><i class="bi bi-clock-history"></i></button>`
                        : `<button class="btn btn-outline-secondary btn-sm sk-icon-btn" disabled title="Henüz stok hareketi yok"><i class="bi bi-clock-history"></i></button>`}
                    <button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="toggleEdit('${key}')" title="Düzenle"><i class="bi bi-pencil"></i></button>
                </div>
            </div>
            ${isEditing ? `
            <div class="sk-edit-row">
                <div>
                    <label class="form-label">Depodaki (Toplam Adet)</label>
                    <input type="number" class="form-control" id="edit-adet-${key}" value="${adet}" min="0" oninput="syncBufferForReserved('${key}')">
                </div>
                <div>
                    <label class="form-label">Boşta (Tampon)</label>
                    <input type="number" class="form-control" id="edit-tampon-${key}" value="${tampon}" min="0" oninput="markBufferEdited('${key}')">
                </div>
                <div style="display:flex;gap:6px;align-items:end;">
                    <button class="btn btn-primary" onclick="saveEdit('${key}')"><i class="bi bi-check-lg me-1"></i>Kaydet</button>
                    <button class="btn btn-outline-secondary" onclick="cancelEdit()">İptal</button>
                </div>
            </div>` : ''}
        </div>`;
    }).join('');
}

/* ───── Render Table ───── */
function renderTable(rows) {
    const tbody = document.getElementById('stockTableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Stok kaydı bulunamadı.</td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(s => {
        const key = stockKey(s);
        const realStockRow = hasStockRow(s);
        const avail = freeStock(s);
        const gorevdeki = reservedStock(s);
        let badgeClass = avail > 0 ? 'success' : 'danger';
        if (avail > 0 && avail < 10) badgeClass = 'warning';
        return `<tr>
            <td>${displayStockNo(s)}</td>
            <td>${esc(s.BolumAdi || '-')}</td>
            <td>${esc(s.AraUrunAdi || '-')}</td>
            <td><span class="soft-badge">${esc(s.UrunCesidi || '-')}</span></td>
            <td><strong>${fmt(s.Adet || 0)}</strong></td>
            <td>${fmt(gorevdeki)}</td>
            <td><span class="soft-badge ${badgeClass}">${fmt(avail)}</span></td>
            <td>${renderProductionAvailability(s)}</td>
            <td>
                <div class="sk-action-group">
                    ${realStockRow
                        ? `<button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="openLedger(${stockNo(s)})" title="Ekstre"><i class="bi bi-clock-history"></i></button>`
                        : `<button class="btn btn-outline-secondary btn-sm sk-icon-btn" disabled title="Henüz stok hareketi yok"><i class="bi bi-clock-history"></i></button>`}
                    <button class="btn btn-outline-secondary btn-sm sk-icon-btn" onclick="editStock('${key}')" title="Düzenle"><i class="bi bi-pencil"></i></button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

/* ───── Pager ───── */
function renderPager(total, page, perPage) {
    const totalPages = Math.max(1, Math.ceil(total / perPage));
    const startItem = total > 0 ? (page - 1) * perPage + 1 : 0;
    const endItem = Math.min(page * perPage, total);

    document.getElementById('pageInfo').textContent = `${startItem}-${endItem} / ${total}`;
    document.getElementById('pagerInfo').textContent = `${fmt(total)} kayıt arasından ${fmt(startItem)}-${fmt(endItem)} gösteriliyor.`;

    const controls = document.getElementById('pagerControls');
    let html = `<button class="sk-page-btn" onclick="loadStocks(${page - 1})" ${page <= 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`;

    let start = Math.max(1, page - 2);
    let end = Math.min(totalPages, start + 4);
    if (end - start < 4) start = Math.max(1, end - 4);

    for (let i = start; i <= end; i++) {
        html += `<button class="sk-page-btn ${i === page ? 'active' : ''}" onclick="loadStocks(${i})">${i}</button>`;
    }
    html += `<button class="sk-page-btn" onclick="loadStocks(${page + 1})" ${page >= totalPages ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`;
    controls.innerHTML = html;
}

/* ───── Sorting ───── */
function sortBy(field) {
    if (currentSort === field) { currentDir = currentDir === 'asc' ? 'desc' : 'asc'; }
    else { currentSort = field; currentDir = 'asc'; }
    document.querySelectorAll('.sk-table .sortable').forEach(th => th.classList.remove('sort-asc','sort-desc'));
    const active = document.querySelector(`.sk-table .sortable[data-sort="${field}"]`);
    if (active) active.classList.add(currentDir === 'asc' ? 'sort-asc' : 'sort-desc');
    loadStocks(1);
}

/* ───── View Toggle ───── */
function setView(mode, btn) {
    currentView = mode;
    document.querySelectorAll('.sk-view-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.getElementById('cardView').classList.toggle('hidden', mode === 'table');
    document.getElementById('tableView').classList.toggle('active', mode === 'table');
}

/* ───── Inline Edit ───── */
function toggleEdit(key) {
    editedBufferInputs.delete(key);
    editingId = editingId === key ? null : key;
    renderCards(applyClientFilters(currentRows));
}
function cancelEdit() {
    if (editingId) editedBufferInputs.delete(editingId);
    editingId = null;
    renderCards(applyClientFilters(currentRows));
}

function markBufferEdited(key) {
    editedBufferInputs.add(key);
}

function syncBufferForReserved(key) {
    if (editedBufferInputs.has(key)) return;
    const row = findStockRow(key);
    const adetInput = document.getElementById(`edit-adet-${key}`);
    const tamponInput = document.getElementById(`edit-tampon-${key}`);
    if (!row || !adetInput || !tamponInput) return;

    const nextAdet = Math.max(0, parseInt(adetInput.value || 0, 10));
    tamponInput.value = Math.max(0, nextAdet - reservedStock(row));
}

async function saveEdit(key) {
    const row = findStockRow(key);
    if (!row) return;
    const adet = parseInt(document.getElementById(`edit-adet-${key}`)?.value || 0, 10);
    const tampon = parseInt(document.getElementById(`edit-tampon-${key}`)?.value || 0, 10);
    const preserveReserved = !editedBufferInputs.has(key);

    try {
        const d = await persistStockEdit(row, adet, tampon, preserveReserved);
        if (!d.success) { Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'); return; }
        Swal.fire({ icon: 'success', title: 'Güncellendi', text: 'Stok kaydı kaydedildi.', timer: 1500, showConfirmButton: false });
        editedBufferInputs.delete(key);
        editingId = null;
        await loadStocks(currentPage);
    } catch { Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'); }
}

/* ───── Table Edit (SweetAlert fallback) ───── */
function editStock(key) {
    const row = findStockRow(key);
    if (!row) return;
    const adet = stockTotal(row);
    const tampon = freeStock(row);
    let tamponTouched = false;

    Swal.fire({
        title: 'Stok Kaydını Düzenle',
        html: `<div class="text-start"><label class="form-label">Depodaki (Adet)</label><input id="swal-adet" type="number" class="swal2-input" value="${adet}" min="0"><label class="form-label mt-2">Boşta (Tampon)</label><input id="swal-tampon" type="number" class="swal2-input" value="${tampon}" min="0"></div>`,
        showCancelButton: true, confirmButtonText: 'Kaydet', cancelButtonText: 'İptal',
        didOpen: () => {
            const adetInput = document.getElementById('swal-adet');
            const tamponInput = document.getElementById('swal-tampon');
            adetInput?.addEventListener('input', () => {
                if (tamponTouched || !tamponInput) return;
                const nextAdet = Math.max(0, parseInt(adetInput.value || 0, 10));
                tamponInput.value = Math.max(0, nextAdet - reservedStock(row));
            });
            tamponInput?.addEventListener('input', () => { tamponTouched = true; });
        },
        preConfirm: () => ({
            Adet: parseInt(document.getElementById('swal-adet').value, 10),
            TamponMiktar: parseInt(document.getElementById('swal-tampon').value, 10),
            preserveReserved: !tamponTouched
        })
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const d = await persistStockEdit(row, result.value.Adet, result.value.TamponMiktar, result.value.preserveReserved);
            if (!d.success) { Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'); return; }
            Swal.fire({ icon: 'success', title: 'Güncellendi', timer: 1500, showConfirmButton: false });
            loadStocks(currentPage);
        } catch { Swal.fire('Hata', 'Kayıt güncellenemedi.', 'error'); }
    });
}

async function persistStockEdit(row, adet, tampon, preserveReserved = false) {
    const realStockRow = hasStockRow(row);
    const payload = realStockRow
        ? { Adet: adet, TamponMiktar: tampon, preserve_reserved: Boolean(preserveReserved) }
        : {
            component_id: Number(row.AraUrunAdiNo || 0),
            department_id: Number(row.BolumAdiNo || 0),
            quantity: adet,
            buffer_quantity: tampon,
            preserve_reserved: Boolean(preserveReserved)
        };

    const r = await fetch(realStockRow ? `/api/stocks/${stockNo(row)}` : '/api/stocks', {
        method: realStockRow ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    });

    return r.json();
}

/* ───── Stock Ledger ───── */
function openLedger(no) {
    currentLedgerStock = currentRows.find(r => Number(r.No) === Number(no)) || { No: no };
    document.getElementById('stockLedgerBackdrop').classList.add('open');
    document.getElementById('stockLedgerBackdrop').setAttribute('aria-hidden', 'false');
    document.getElementById('ledgerTitle').textContent = currentLedgerStock.AraUrunAdi || `Stok #${no}`;
    renderLedgerMeta(currentLedgerStock);
    renderLedgerSummary(null, currentLedgerStock);
    renderLedgerExplain(null, currentLedgerStock);
    document.getElementById('ledgerList').innerHTML = '<div class="sk-empty"><i class="bi bi-hourglass-split"></i><p>Yükleniyor...</p></div>';
    loadLedger();
}

function closeLedger() {
    const backdrop = document.getElementById('stockLedgerBackdrop');
    if (!backdrop.classList.contains('open')) return;
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
}

function renderLedgerMeta(stock) {
    const avail = freeStock(stock);
    document.getElementById('ledgerMeta').innerHTML = `
        <span class="soft-badge">No: ${esc(stock.No || '-')}</span>
        <span class="soft-badge">${esc(stock.BolumAdi || '-')}</span>
        <span class="soft-badge">${esc(stock.UrunCesidi || '-')}</span>
        <span class="soft-badge success">${fmt(avail)} boşta</span>
    `;
}

async function loadLedger() {
    if (!currentLedgerStock?.No) return;

    const params = new URLSearchParams({ limit: 120 });
    const q = document.getElementById('ledgerSearch').value.trim();
    const direction = document.getElementById('ledgerDirection').value;
    const dateFrom = document.getElementById('ledgerDateFrom').value;
    const dateTo = document.getElementById('ledgerDateTo').value;
    if (q) params.set('q', q);
    if (direction) params.set('direction', direction);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);

    try {
        const r = await fetch(`/api/stocks/${currentLedgerStock.No}/movements?${params.toString()}`, { cache: 'no-store' });
        const d = await r.json();
        if (!d.success) throw new Error(d.message || 'Ekstre yüklenemedi.');
        if (d.stock) {
            currentLedgerStock = d.stock;
            document.getElementById('ledgerTitle').textContent = currentLedgerStock.AraUrunAdi || `Stok #${currentLedgerStock.No}`;
            renderLedgerMeta(currentLedgerStock);
        }
        renderLedgerSummary(d.summary || {}, currentLedgerStock);
        renderLedgerExplain(d.analysis || {}, currentLedgerStock);
        renderLedgerRows(d.movements || []);
    } catch (e) {
        document.getElementById('ledgerList').innerHTML = '<div class="sk-empty"><i class="bi bi-exclamation-triangle"></i><p>Ekstre verisi yüklenemedi.</p></div>';
    }
}

function renderLedgerSummary(summary, stock) {
    const adet = stockTotal(stock);
    const tampon = freeStock(stock);
    const gorevdeki = reservedStock(stock);
    document.getElementById('ledgerSummary').innerHTML = `
        <div class="ledger-summary-item"><div class="ledger-summary-value">${fmt(adet)}</div><div class="ledger-summary-label">Depodaki</div></div>
        <div class="ledger-summary-item"><div class="ledger-summary-value">${fmt(gorevdeki)}</div><div class="ledger-summary-label">Görevdeki</div></div>
        <div class="ledger-summary-item"><div class="ledger-summary-value">${fmt(tampon)}</div><div class="ledger-summary-label">Boşta</div></div>
        <div class="ledger-summary-item"><div class="ledger-summary-value">${fmt(summary?.movement_count || 0)}</div><div class="ledger-summary-label">Hareket</div></div>
    `;
}

function renderLedgerExplain(analysis, stock) {
    const target = document.getElementById('ledgerExplain');
    if (!analysis) {
        target.innerHTML = '';
        return;
    }

    const current = analysis.current || {
        quantity: stockTotal(stock),
        free: freeStock(stock),
        reserved: reservedStock(stock)
    };
    const opening = analysis.opening || { quantity: 0, free: 0, reserved: 0 };
    const net = analysis.net || { quantity_delta: 0, free_delta: 0, reserved_delta: 0 };
    const sources = Array.isArray(analysis.reserved_sources) ? analysis.reserved_sources : [];
    const sourceTotals = Array.isArray(analysis.source_totals) ? analysis.source_totals : sources;
    const live = analysis.live_context || {};
    const pool = live.pool || {};
    const tasks = live.personnel_tasks || {};
    const orders = live.orders || {};
    const topSources = sourceTotals.slice(0, 8);
    const liveChips = [
        Number(pool.count || 0) > 0 ? `<span class="ledger-net-chip">Havuz <strong>${fmt(pool.total_quantity || 0)} toplam / ${fmt(pool.ready_quantity || 0)} hazır</strong></span>` : '',
        Number(tasks.count || 0) > 0 ? `<span class="ledger-net-chip">Personel görev <strong>${fmt(tasks.ready_quantity || 0)} hazır / ${fmt(tasks.waiting_quantity || 0)} bekleyen</strong></span>` : '',
        Number(orders.count || 0) > 0 ? `<span class="ledger-net-chip">Bağlı sipariş <strong>${fmt(orders.count || 0)}</strong></span>` : ''
    ].filter(Boolean).join('');
    const reconciled = analysis.reconciled || {};
    const hasMismatch = reconciled.quantity === false || reconciled.free === false || reconciled.reserved === false;

    const sourceHtml = topSources.length
        ? topSources.map(source => `
            <div class="ledger-source-row">
                <div>
                    <div class="ledger-source-label">${esc(source.label || source.source_label || 'Kaynak')}</div>
                    <div class="ledger-source-meta">
                        ${fmt(source.movement_count || 0)} hareket · Depo ${signed(source.quantity_delta || 0)} · Boşta ${signed(source.free_delta || 0)}
                    </div>
                    <div class="ledger-source-note">${esc(ledgerSourceNote(source))}</div>
                </div>
                <div class="ledger-source-delta">${signed(source.reserved_delta || 0)}</div>
            </div>
        `).join('')
        : '<div class="text-muted small">Bu stok için kaynak bazlı hareket etkisi bulunamadı.</div>';

    target.innerHTML = `
        <div class="ledger-reading">
            <h4 class="ledger-reading-title">Bu ekstre ne söylüyor?</h4>
            ${hasMismatch ? '<div class="ledger-reading-alert">Hareket toplamı ile canlı stok değeri tam örtüşmüyor; manuel düzeltme, eski kayıt veya eksik hareket olabilir.</div>' : ''}
            <ul class="ledger-reading-list">
                <li><i class="bi bi-box-seam"></i><span>Depodaki <strong>${fmt(current.quantity)}</strong> adet fiziksel stok var.</span></li>
                <li><i class="bi bi-check2-circle"></i><span>Boşta <strong>${fmt(current.free)}</strong> adet yeni iş emrinde kullanılabilir.</span></li>
                <li><i class="bi bi-lock"></i><span>Görevdeki <strong>${fmt(current.reserved)}</strong> adet, daha önce açılmış işlere ayrılmış görünüyor. Bu sayı tek bir görev numarası değil; <strong>Depodaki - Boşta</strong> hesabıdır.</span></li>
                <li><i class="bi bi-clock-history"></i><span>Bu ekstre açılışta <strong>${fmt(opening.quantity)} depoda / ${fmt(opening.free)} boşta / ${fmt(opening.reserved)} görevde</strong> başlamış; hareketlerin net etkisi <strong>depo ${signed(net.quantity_delta || 0)}, boşta ${signed(net.free_delta || 0)}, görevde ${signed(net.reserved_delta || 0)}</strong>.</span></li>
            </ul>
        </div>
        <div class="ledger-equation">
            <div class="ledger-equation-line">
                <span class="ledger-equation-value">${fmt(current.quantity)} <small>depodaki</small></span>
                <span>-</span>
                <span class="ledger-equation-value">${fmt(current.free)} <small>boşta</small></span>
                <span>=</span>
                <span class="ledger-equation-value ledger-equation-result">${fmt(current.reserved)} <small>görevdeki</small></span>
            </div>
            <div class="ledger-net-line">
                <span class="ledger-net-chip">Açılış <strong>${fmt(opening.quantity)} / ${fmt(opening.free)} / ${fmt(opening.reserved)}</strong></span>
                <span class="ledger-net-chip">Depo net <strong>${signed(net.quantity_delta || 0)}</strong></span>
                <span class="ledger-net-chip">Boşta net <strong>${signed(net.free_delta || 0)}</strong></span>
                <span class="ledger-net-chip">Görevdeki net <strong>${signed(net.reserved_delta || 0)}</strong></span>
                ${liveChips}
            </div>
        </div>
        <div class="ledger-breakdown">
            <div class="ledger-breakdown-head">
                <h4 class="ledger-breakdown-title">Kaynak Bazlı Etki</h4>
                <span class="soft-badge">${fmt(sourceTotals.length)} kaynak · ${fmt(sources.length)} görevdeki kaynak</span>
            </div>
            <div class="ledger-source-list">${sourceHtml}</div>
        </div>
        ${ledgerLiveContextHtml(live)}
    `;
}

function ledgerSourceNote(source) {
    const qty = Number(source.quantity_delta || 0);
    const free = Number(source.free_delta || 0);
    const reserved = Number(source.reserved_delta || 0);
    if (reserved > 0 && free < 0 && qty === 0) {
        return 'Boşta stok bu iş emrine ayrılmış; depodaki değişmedi, görevdeki arttı.';
    }
    if (reserved > 0 && qty > 0 && free === 0) {
        return 'Depoya giriş olmuş ama boşta artmamış; stok üretimi ise veri/kod düzeltmesi gerekir.';
    }
    if (reserved === 0 && qty > 0 && free > 0) {
        return 'Üretim boşta stoğa eklenmiş; yeni işlerde kullanılabilir.';
    }
    if (reserved < 0) {
        return 'Ayrılmış stok çözülmüş veya tüketilmiş; görevdeki azalmış.';
    }
    return 'Bu kaynak stok dengesini hareket kayıtları üzerinden etkiliyor.';
}

function ledgerLiveContextHtml(live) {
    const rows = [];
    const orders = live?.orders?.rows || [];
    const tasks = live?.personnel_tasks?.rows || [];
    const pool = live?.pool?.rows || [];

    orders.slice(0, 6).forEach(order => {
        rows.push(ledgerContextRow(
            `Sipariş ${order.SiparisNo || '-'}`,
            `Satır #${order.No || '-'} · ${order.Durum || '-'}${order.GorevNo ? ' · İş emri #' + order.GorevNo : ''}`,
            `${fmt(order.Adet || 0)} adet`
        ));
    });

    tasks.slice(0, 6).forEach(task => {
        rows.push(ledgerContextRow(
            `Personel görev #${task.No || '-'}`,
            `Sipariş ${task.SiparisNo || '-'} · Personel ${task.PersonelNo || '-'} · ${task.Onay || '-'}`,
            `${fmt(Number(task.Adet || 0) + Number(task.BekleyenAdet || 0))} adet`
        ));
    });

    pool.slice(0, 6).forEach(item => {
        rows.push(ledgerContextRow(
            `Havuz #${item.No || '-'}`,
            `Sipariş ${item.SiparisNo || '-'} · Hazır ${fmt(item.Adet || 0)} · ${item.Aciklama || ''}`,
            `${fmt(item.ToplamAdet || 0)} adet`
        ));
    });

    if (!rows.length) return '';

    return `
        <div class="ledger-context">
            <div class="ledger-breakdown-head">
                <h4 class="ledger-breakdown-title">Canlı Bağlantılar</h4>
                <span class="soft-badge">${fmt(rows.length)} kayıt</span>
            </div>
            <div class="ledger-context-grid">${rows.join('')}</div>
        </div>
    `;
}

function ledgerContextRow(title, meta, quantity) {
    return `
        <div class="ledger-context-row">
            <div>
                <div class="ledger-context-title">${esc(title)}</div>
                <div class="ledger-context-meta">${esc(meta)}</div>
            </div>
            <div class="ledger-context-qty">${esc(quantity)}</div>
        </div>
    `;
}

function renderLedgerRows(rows) {
    const list = document.getElementById('ledgerList');
    if (!rows.length) {
        list.innerHTML = '<div class="sk-empty"><i class="bi bi-inbox"></i><p>Ekstre kaydı bulunamadı.</p></div>';
        return;
    }

    list.innerHTML = rows.map(row => {
        const direction = row.direction || 'neutral';
        const qty = Number(row.quantity_delta || 0);
        const free = Number(row.free_delta ?? row.buffer_delta ?? 0);
        const reserved = Number(row.reserved_delta || 0);
        const mainDelta = qty !== 0 ? signed(qty) : (free !== 0 ? signed(free) : signed(reserved));
        const deltaLabel = qty !== 0 ? 'depodaki' : (free !== 0 ? 'boşta' : 'görevdeki');
        const source = movementSource(row);
        const actor = row.actor_name || 'Sistem';

        return `
            <div class="ledger-row">
                <div class="ledger-icon ${esc(direction)}"><i class="bi ${ledgerIcon(direction)}"></i></div>
                <div>
                    <div class="ledger-row-title">${esc(row.title_human || row.movement_type || 'Stok hareketi')}</div>
                    <div class="ledger-row-meta">
                        <span><i class="bi bi-calendar3 me-1"></i>${fmtDate(row.happened_at)}</span>
                        <span><i class="bi bi-person me-1"></i>${esc(actor)}</span>
                        ${source ? `<span><i class="bi bi-link-45deg me-1"></i>${esc(source)}</span>` : ''}
                    </div>
                    <div class="ledger-balance-grid">
                        ${balancePill('Depodaki', row.quantity_before, row.quantity_after, row.quantity_delta || 0)}
                        ${balancePill('Boşta', row.free_before ?? row.buffer_before, row.free_after ?? row.buffer_after, free)}
                        ${balancePill('Görevdeki', row.reserved_before, row.reserved_after, reserved)}
                    </div>
                    ${row.reason_human ? `<div class="ledger-reason">${esc(row.reason_human)}</div>` : ''}
                    ${row.description ? `<div class="text-muted small mt-1">${esc(row.description)}</div>` : ''}
                </div>
                <div class="ledger-delta ${esc(direction)}">
                    ${esc(mainDelta)}
                    <div class="text-muted small">${deltaLabel}</div>
                    <div class="text-muted small">görevdeki ${signed(reserved)}</div>
                </div>
            </div>
        `;
    }).join('');
}

function exportLedger() {
    if (!currentLedgerStock?.No) return;
    const params = new URLSearchParams();
    const q = document.getElementById('ledgerSearch').value.trim();
    const direction = document.getElementById('ledgerDirection').value;
    const dateFrom = document.getElementById('ledgerDateFrom').value;
    const dateTo = document.getElementById('ledgerDateTo').value;
    if (q) params.set('q', q);
    if (direction) params.set('direction', direction);
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo) params.set('date_to', dateTo);
    window.location.href = `/api/stocks/${currentLedgerStock.No}/movements/export?${params.toString()}`;
}

/* ───── Export ───── */
function exportData() {
    const params = new URLSearchParams({ sort_by: currentSort, sort_dir: currentDir });
    const dept = document.getElementById('deptFilter').value;
    const type = getActiveType();
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value.trim();
    if (dept) params.set('department_id', dept);
    if (type) params.set('component_type', type);
    if (status) params.set('stock_status', status);
    if (search) params.set('search', search);
    window.location.href = `/api/stocks/export?${params.toString()}`;
}

/* ───── Import ───── */
async function importStockFile(input) {
    const file = input.files[0];
    if (!file) return;

    const result = await Swal.fire({
        title: 'Excel Dosyası Yükle',
        html: `<p class="text-start"><strong>${esc(file.name)}</strong> dosyasındaki "Adet" ve "Boşta/Tampon" değerleri mevcut kayıtlara uygulanacak.</p><p class="text-start text-muted small">Not: Önce "Dışa Aktar" ile indirdiğiniz çalışma kitabını Excel'de düzenleyip tekrar yükleyebilirsiniz.</p>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yükle ve Güncelle',
        cancelButtonText: 'Vazgeç'
    });

    if (!result.isConfirmed) { input.value = ''; return; }

    const formData = new FormData();
    formData.append('file', file);

    try {
        const r = await fetch('/api/stocks/import', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        });
        const d = await r.json();
        if (d.success) {
            Swal.fire({ icon: 'success', title: 'Aktarım Tamamlandı', text: d.message, timer: 3000 });
            await loadStocks(1);
        } else {
            Swal.fire('Hata', d.message || 'Aktarım başarısız.', 'error');
        }
    } catch (e) {
        Swal.fire('Hata', 'Dosya yüklenemedi: ' + e.message, 'error');
    }
    input.value = '';
}

/* ───── Reset Buffer ───── */
function resetBuffer() {
    Swal.fire({
        title: 'Tamponlar eşitlensin mi?',
        text: 'Tüm kayıtlarda boşta/tampon miktarı, depodaki toplam ile eşitlenecek.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, eşitle',
        cancelButtonText: 'Vazgeç'
    }).then(async (result) => {
        if (!result.isConfirmed) return;
        try {
            const r = await fetch('/api/stocks/reset-buffer', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            const d = await r.json();
            if (d.success) {
                Swal.fire({ icon: 'success', title: 'Tamamlandı', text: 'Tampon miktarları eşitlendi.', timer: 2000, showConfirmButton: false });
                loadStocks(1);
            } else {
                Swal.fire('Hata', 'Tamponlar güncellenemedi.', 'error');
            }
        } catch { Swal.fire('Hata', 'Tamponlar güncellenemedi.', 'error'); }
    });
}

/* ───── Timestamp ───── */
function setTimestamp() {
    const t = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('lastRefresh').textContent = t;
    const el = document.getElementById('summaryUpdatedInline');
    if (el) el.textContent = t;
}

// WebSocket Live Updates
document.addEventListener('stock-alert-received', (e) => {
    if (typeof loadStocks === 'function') {
        loadStocks(currentPage);
    }
});

document.addEventListener('work-order-updated', (e) => {
    if (typeof loadStocks === 'function') {
        loadStocks(currentPage);
    }
});
</script>
@endpush
