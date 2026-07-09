@extends('layouts.app')

@section('title', 'Ürün Ağacı')


@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="resetTreeSelection()">
        <i class="bi bi-arrow-counterclockwise me-2"></i>Temizle
    </button>
    <button type="button" class="btn btn-primary" onclick="loadUrunler()">
        <i class="bi bi-arrow-repeat me-2"></i>Yenile
    </button>
@endsection

@push('styles')
<style>
    /* ═══════════════════════════════════════
       Ürün Ağacı — Yeni Basit Tasarım
       ═══════════════════════════════════════ */

    .bom-page {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* ── Arama Bölümü ── */
    .bom-search-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
    }

    .bom-search-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .bom-search-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        flex-shrink: 0;
    }

    .bom-search-header h2 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 0;
    }

    .bom-search-header p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 2px 0 0;
    }

    .bom-search-bar {
        position: relative;
    }

    .bom-search-bar input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid var(--z-border);
        border-radius: 12px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.95rem;
        color: var(--z-text);
        transition: all 0.2s ease;
    }

    .bom-search-bar input:focus {
        outline: none;
        border-color: var(--z-accent);
        background: var(--z-bg-card);
        box-shadow: 0 0 0 4px var(--z-accent-soft);
    }

    .bom-search-bar i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.1rem;
        color: var(--z-text-muted);
    }

    .bom-search-count {
        margin-top: 10px;
        font-size: 0.8rem;
        color: var(--z-text-muted);
    }

    .bom-search-count strong {
        color: var(--z-accent);
        font-weight: 700;
    }

    .bom-filter-panel {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 14px;
    }

    .bom-filter-control {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .bom-filter-control span {
        font-size: 0.72rem;
        color: var(--z-text-muted);
        font-weight: 700;
    }

    .bom-filter-control select {
        width: 100%;
        min-height: 38px;
        border: 1.5px solid var(--z-border);
        border-radius: 9px;
        background: var(--z-bg-input);
        color: var(--z-text);
        font: inherit;
        font-size: 0.82rem;
        padding: 7px 10px;
    }

    .bom-filter-control select:focus {
        outline: none;
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .bom-filter-stats {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 12px;
    }

    .bom-stat-chip {
        border: 1px solid var(--z-border-light);
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        border-radius: 999px;
        min-height: 30px;
        padding: 5px 10px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .bom-stat-chip:hover,
    .bom-stat-chip.active {
        border-color: var(--z-accent);
        background: var(--z-accent-soft);
        color: var(--z-accent);
    }

    .bom-stat-chip strong {
        color: inherit;
        font-weight: 800;
    }

    /* ── Ürün Kartları Grid ── */
    .bom-product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 10px;
        max-height: 420px;
        overflow-y: auto;
        margin-top: 14px;
        padding-right: 4px;
    }

    .bom-product-grid::-webkit-scrollbar { width: 5px; }
    .bom-product-grid::-webkit-scrollbar-thumb { border-radius: 99px; background: rgba(148,163,184,0.25); }

    .bom-product-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        border: 2px solid var(--z-border-light);
        border-radius: 10px;
        background: var(--z-bg-card);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .bom-product-item:hover {
        border-color: var(--z-accent);
        background: var(--z-accent-soft);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(13,148,136,0.1);
    }

    .bom-product-item.active {
        border-color: var(--z-accent);
        background: var(--z-accent-soft);
        box-shadow: 0 0 0 3px rgba(13,148,136,0.15);
    }

    .bom-item-emoji {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }

    .bom-item-emoji.nihai { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
    .bom-item-emoji.ara { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .bom-item-emoji.ham { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .bom-item-emoji.blank { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); }

    .bom-item-info {
        flex: 1;
        min-width: 0;
    }

    .bom-item-info h4 {
        font-size: 0.86rem;
        font-weight: 600;
        line-height: 1.35;
        margin: 0 0 4px;
        white-space: normal;
        overflow: visible;
        overflow-wrap: anywhere;
    }

    .bom-item-info span {
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .bom-item-info small {
        display: block;
        margin-top: 2px;
        font-size: 0.68rem;
        color: var(--z-text-muted);
        line-height: 1.3;
        white-space: normal;
        overflow-wrap: anywhere;
    }

    .bom-item-type {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        flex-shrink: 0;
        align-self: flex-start;
    }

    .bom-item-type.nihai { background: #dbeafe; color: #1d4ed8; }
    .bom-item-type.ara { background: #d1fae5; color: #047857; }
    .bom-item-type.ham { background: #fef3c7; color: #b45309; }
    .bom-item-type.blank { background: #f1f5f9; color: #64748b; }

    /* ── Ağaç Görünümü ── */
    .bom-tree-section {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius-lg);
        padding: 24px;
        min-height: 300px;
    }

    .bom-tree-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        text-align: center;
        gap: 12px;
    }

    .bom-tree-empty .empty-icon {
        width: 80px;
        height: 80px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--z-accent-soft), rgba(6,182,212,0.08));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        animation: emptyPulse 2s ease-in-out infinite;
    }

    @keyframes emptyPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .bom-tree-empty h3 {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
    }

    .bom-tree-empty p {
        font-size: 0.85rem;
        color: var(--z-text-muted);
        max-width: 380px;
        margin: 0;
    }

    /* Ana Ürün Kartı */
    .bom-root {
        background: linear-gradient(135deg, #f0fdfa, #e0f2fe);
        border: 2px solid #99f6e4;
        border-radius: 14px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 8px;
        position: relative;
    }

    .bom-root-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(13,148,136,0.25);
    }

    .bom-root-info h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0 0 4px;
        color: var(--z-text);
    }

    .bom-root-info p {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
        margin: 0;
    }

    .bom-root-info p i {
        margin-right: 4px;
    }

    .bom-root-badge {
        margin-left: auto;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
    }

    .bom-root-badge span {
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .bom-root-badge .parts-count {
        background: rgba(13,148,136,0.12);
        color: #0d9488;
    }

    .bom-root-badge .total-count {
        background: rgba(217,119,6,0.1);
        color: #b45309;
    }

    /* ── Parça Ağaç Çizgileri ve Düzenler ── */
    .bom-children {
        position: relative;
        padding-left: 32px;
        margin-top: 4px;
    }

    .bom-children::before {
        content: '';
        position: absolute;
        left: 24px;
        top: 0;
        bottom: 20px;
        width: 2px;
        background: linear-gradient(180deg, #99f6e4, #d1d5db);
        border-radius: 2px;
    }

    .bom-child-wrapper {
        position: relative;
        padding: 6px 0;
    }

    .bom-child-wrapper::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 28px;
        width: 16px;
        height: 2px;
        background: #d1d5db;
    }

    /* Parça Kartı */
    .bom-node {
        border: 1.5px solid var(--z-border);
        border-radius: 12px;
        background: var(--z-bg-card);
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.03);
    }

    .bom-node:hover {
        border-color: var(--z-accent);
        box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        transform: translateX(3px);
    }

    .bom-node-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .bom-node-icon.ara-mamul { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
    .bom-node-icon.ham-madde { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .bom-node-icon.nihai-urun { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
    .bom-node-icon.parca { background: linear-gradient(135deg, #f3e8ff, #e9d5ff); }

    .bom-node-info {
        flex: 1;
        min-width: 0;
    }

    .bom-node-info h5 {
        font-size: 0.88rem;
        font-weight: 600;
        margin: 0;
        line-height: 1.3;
    }

    .bom-node-info span {
        font-size: 0.74rem;
        color: var(--z-text-muted);
    }

    .bom-node-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    .bom-qty {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 0.78rem;
        font-weight: 700;
        background: var(--z-bg-soft);
        color: var(--z-text);
        border: 1px solid var(--z-border-light);
    }

    .bom-type-tag {
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.66rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .bom-type-tag.ara { background: #d1fae5; color: #047857; }
    .bom-type-tag.ham { background: #fef3c7; color: #b45309; }
    .bom-type-tag.nihai { background: #dbeafe; color: #1d4ed8; }
    .bom-type-tag.parca { background: #f3e8ff; color: #7c3aed; }

    /* Alt grup animasyonu */
    .bom-node-expand {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1.5px solid var(--z-border);
        background: var(--z-bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        color: var(--z-text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .bom-node-expand:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }

    .bom-node-expand.open {
        background: var(--z-accent);
        border-color: var(--z-accent);
        color: white;
        transform: rotate(90deg);
    }

    /* İç içe alt ağaç */
    .bom-sub-tree {
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .bom-sub-tree.collapsed {
        max-height: 0 !important;
    }

    /* Seviye renklendir */
    .bom-children.level-1::before { background: linear-gradient(180deg, #0d9488, #a7f3d0); }
    .bom-children.level-2::before { background: linear-gradient(180deg, #06b6d4, #bae6fd); }
    .bom-children.level-3::before { background: linear-gradient(180deg, #8b5cf6, #c4b5fd); }
    .bom-children.level-4::before { background: linear-gradient(180deg, #d97706, #fde68a); }

    /* Özet Bilgi Bandı */
    .bom-summary-strip {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 12px 18px;
        background: var(--z-bg-soft);
        border-radius: 10px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    .bom-summary-strip .strip-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--z-text-secondary);
    }

    .bom-summary-strip .strip-item i {
        font-size: 1rem;
    }

    .bom-summary-strip .strip-item strong {
        font-weight: 700;
        color: var(--z-text);
    }

    .bom-legend {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-left: auto;
    }

    .bom-legend-item {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        color: var(--z-text-muted);
    }

    .bom-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 4px;
    }

    .bom-legend-dot.nihai { background: #93c5fd; }
    .bom-legend-dot.ara { background: #6ee7b7; }
    .bom-legend-dot.ham { background: #fcd34d; }

    .bom-action-group {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .bom-action-btn {
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-text);
        border-radius: 8px;
        min-height: 34px;
        padding: 6px 11px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .bom-action-btn:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }

    .bom-action-btn.primary {
        background: var(--z-accent);
        border-color: var(--z-accent);
        color: #fff;
    }

    .bom-action-btn.danger:hover {
        border-color: var(--z-danger);
        color: var(--z-danger);
        background: var(--z-danger-soft, #fee2e2);
    }

    .bom-node-edit {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        border: 1.5px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-text-muted);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .bom-node-edit:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }

    .bom-editor-modal {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(4px);
    }

    .bom-editor-modal.open {
        display: flex;
    }

    .bom-editor-dialog {
        width: min(1180px, 100%);
        max-height: min(860px, calc(100vh - 48px));
        background: var(--z-bg-card);
        border-radius: 14px;
        border: 1px solid var(--z-border);
        box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .bom-editor-head,
    .bom-editor-foot {
        padding: 16px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: var(--z-bg-soft);
        border-bottom: 1px solid var(--z-border);
    }

    .bom-editor-foot {
        border-top: 1px solid var(--z-border);
        border-bottom: 0;
        background: var(--z-bg-card);
        flex-wrap: wrap;
    }

    .bom-editor-title {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .bom-editor-title h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 800;
    }

    .bom-editor-title span {
        display: block;
        font-size: 0.78rem;
        color: var(--z-text-muted);
        overflow-wrap: anywhere;
        white-space: normal;
        max-width: 620px;
    }

    .bom-editor-close {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .bom-editor-body {
        padding: 18px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 16px;
        flex: 1 1 auto;
        min-height: 0;
    }

    .bom-editor-panels {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 16px;
        align-items: stretch;
    }

    .bom-editor-summary {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 12px;
        align-items: center;
        border: 1px solid var(--z-border-light);
        border-radius: 12px;
        background: var(--z-bg-soft);
        padding: 14px 16px;
    }

    .bom-editor-summary h4 {
        margin: 0;
        font-size: 0.96rem;
        font-weight: 800;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .bom-editor-summary span {
        display: block;
        margin-top: 4px;
        color: var(--z-text-muted);
        font-size: 0.76rem;
    }

    .bom-editor-count {
        min-width: 92px;
        border-radius: 10px;
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        padding: 8px 10px;
        text-align: center;
        font-weight: 800;
        color: var(--z-accent);
    }

    .bom-editor-count small {
        display: block;
        margin-top: 2px;
        color: var(--z-text-muted);
        font-size: 0.68rem;
        font-weight: 700;
    }

    .bom-editor-add-panel,
    .bom-editor-selected-panel {
        border: 1px solid var(--z-border-light);
        border-radius: 12px;
        background: var(--z-bg-card);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .bom-editor-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--z-border-light);
        background: var(--z-bg-soft);
        font-weight: 800;
        font-size: 0.84rem;
    }

    .bom-editor-panel-head span {
        color: var(--z-text-muted);
        font-size: 0.74rem;
        font-weight: 700;
    }

    .bom-editor-picker {
        display: grid;
        grid-template-columns: minmax(130px, 1.5fr) minmax(110px, 1fr) minmax(110px, 1fr) 70px;
        gap: 10px;
        padding: 12px 14px;
        border-bottom: 1px solid var(--z-border-light);
    }

    .bom-editor-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
        min-width: 0;
    }

    .bom-editor-field span {
        color: var(--z-text-muted);
        font-size: 0.7rem;
        font-weight: 800;
    }

    .bom-editor-field input,
    .bom-editor-field select {
        width: 100%;
        min-height: 38px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-input);
        padding: 8px 10px;
        color: var(--z-text);
        font: inherit;
    }

    .bom-editor-field input:focus,
    .bom-editor-field select:focus {
        outline: none;
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .bom-editor-candidates {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 10px;
        min-height: 200px;
        max-height: min(48vh, 460px);
        overflow-y: auto;
        overscroll-behavior: auto;
        padding: 12px 14px;
        flex: 1 1 auto;
    }

    .bom-editor-candidate {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 38px;
        gap: 10px;
        align-items: center;
        border: 1px solid var(--z-border-light);
        border-radius: 10px;
        background: var(--z-bg-card);
        padding: 10px 12px;
    }

    .bom-editor-candidate h5,
    .bom-editor-row-title {
        margin: 0;
        font-size: 0.84rem;
        font-weight: 800;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .bom-editor-candidate-meta,
    .bom-editor-row-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 6px;
        color: var(--z-text-muted);
        font-size: 0.72rem;
    }

    .bom-editor-mini-badge {
        border-radius: 999px;
        background: var(--z-bg-soft);
        border: 1px solid var(--z-border-light);
        padding: 2px 7px;
        font-weight: 700;
    }

    .bom-editor-add-btn {
        width: 38px;
        height: 38px;
        border-radius: 9px;
        border: 1px solid var(--z-accent);
        background: var(--z-accent);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .bom-editor-row {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr) 110px 38px;
        gap: 12px;
        align-items: start;
        padding: 12px 14px;
        border-bottom: 1px solid var(--z-border-light);
        background: var(--z-bg-card);
    }

    #bomEditorRows {
        min-height: 200px;
        max-height: min(48vh, 460px);
        overflow-y: auto;
        overscroll-behavior: auto;
        scrollbar-gutter: stable;
        flex: 1 1 auto;
    }

    #bomEditorRows::-webkit-scrollbar {
        width: 8px;
    }

    #bomEditorRows::-webkit-scrollbar-track {
        background: var(--z-bg-soft);
        border-radius: 999px;
    }

    #bomEditorRows::-webkit-scrollbar-thumb {
        background: rgba(13, 148, 136, 0.35);
        border-radius: 999px;
        border: 2px solid var(--z-bg-soft);
    }

    #bomEditorRows::-webkit-scrollbar-thumb:hover {
        background: rgba(13, 148, 136, 0.55);
    }

    .bom-editor-row:last-child {
        border-bottom: 0;
    }

    .bom-editor-row-no {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: var(--z-bg-soft);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--z-text-muted);
        font-size: 0.72rem;
        font-weight: 800;
    }

    .bom-editor-row input {
        width: 100%;
        min-height: 38px;
        border: 1px solid var(--z-border);
        border-radius: 8px;
        background: var(--z-bg-input);
        padding: 8px 10px;
        color: var(--z-text);
        font: inherit;
    }

    .bom-editor-remove {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-danger);
        cursor: pointer;
    }

    .bom-editor-empty {
        padding: 22px;
        color: var(--z-text-muted);
        text-align: center;
        background: var(--z-bg-card);
    }

    .bom-editor-empty.inline {
        border-top: 1px solid var(--z-border-light);
        padding: 22px;
        color: var(--z-text-muted);
        text-align: center;
        background: var(--z-bg-card);
    }

    .bom-editor-status {
        min-height: 20px;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--z-text-muted);
    }

    .bom-editor-status.error { color: var(--z-danger); }
    .bom-editor-status.success { color: var(--z-success); }

    /* ── No BOM Uyarı ── */
    .bom-no-data {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 50px 20px;
        text-align: center;
        gap: 10px;
    }

    .bom-no-data .no-data-icon {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: var(--z-warning-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
    }

    .bom-no-data h3 {
        font-size: 0.95rem;
        margin: 0;
    }

    .bom-no-data p {
        font-size: 0.82rem;
        color: var(--z-text-muted);
        margin: 0;
    }

    /* ── Responsive ── */
    @media (max-width: 991px) {
        .bom-editor-panels {
            grid-template-columns: 1fr;
        }
        .bom-editor-picker {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        .bom-product-grid {
            grid-template-columns: 1fr;
            max-height: 250px;
        }

        .bom-root {
            flex-direction: column;
            text-align: center;
            gap: 10px;
        }

        .bom-root-badge {
            margin-left: 0;
            flex-direction: row;
        }

        .bom-children {
            padding-left: 20px;
        }

        .bom-node {
            flex-wrap: wrap;
        }

        .bom-editor-row {
            grid-template-columns: 1fr;
        }

        #bomEditorRows {
            max-height: min(30vh, 240px);
        }

        .bom-editor-summary,
        .bom-editor-picker {
            grid-template-columns: 1fr;
        }

        .bom-editor-candidates {
            grid-template-columns: 1fr;
        }

        .bom-editor-title span {
            max-width: 220px;
        }

        .bom-summary-strip {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }

        .bom-filter-panel {
            grid-template-columns: 1fr;
        }

        .bom-legend {
            margin-left: 0;
        }
    }

    /* ── Giriş Animasyonu ── */
    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .bom-animated {
        animation: fadeSlideUp 0.3s ease forwards;
    }

    .bom-child-wrapper:nth-child(1) { animation-delay: 0.05s; }
    .bom-child-wrapper:nth-child(2) { animation-delay: 0.10s; }
    .bom-child-wrapper:nth-child(3) { animation-delay: 0.15s; }
    .bom-child-wrapper:nth-child(4) { animation-delay: 0.20s; }
    .bom-child-wrapper:nth-child(5) { animation-delay: 0.25s; }

    /* ── Diyagram CSS (Modal İçin) ── */
    #diagramModal { backdrop-filter: blur(4px); }
    .diagram-modal-card { background:#fff; width:95%; max-width:1100px; border-radius:12px; padding:20px; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
    .diagram-modal-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:15px; }
    .diagram-modal-header h3 { margin:0; font-size:1.2rem; color:var(--z-text); display:flex; align-items:center; gap:8px; }
    .diagram-header-actions { display:flex; align-items:center; gap:8px; }
    .diagram-action-btn { background:var(--z-bg-card); border:1px solid var(--z-border); border-radius:8px; padding:8px 12px; display:inline-flex; align-items:center; gap:6px; color:var(--z-text); font-weight:700; cursor:pointer; }
    .diagram-action-btn.primary { background:var(--z-accent); border-color:var(--z-accent); color:#fff; }
    .diagram-close { background:var(--z-bg-light); border:1px solid var(--z-border); border-radius:50%; width:36px; height:36px; font-size:1.2rem; cursor:pointer; display:flex; justify-content:center; align-items:center; color:var(--z-text); }
    .diagram-panel { overflow: auto; position: relative; }
    .diagram-node { position: absolute; width: 50px; height: 50px; background-color: var(--z-bg-card); border: 3px solid #d4af37; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 10px; font-weight: bold; color: var(--z-text); cursor: grab; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: center; z-index: 10; user-select: none; }
    .diagram-node.editable:hover { border-color: var(--z-accent); box-shadow: 0 6px 14px rgba(67,160,153,0.25); }
    .diagram-node:active { cursor: grabbing; box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
    .diagram-node::after { content: attr(data-fullname); position: absolute; bottom: -24px; left: 50%; transform: translateX(-50%); background: #4b3621; color: #fff; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: normal; white-space: nowrap; opacity: 0; visibility: hidden; transition: opacity 0.2s ease, bottom 0.2s ease; z-index: 20; pointer-events: none; box-shadow: 0 2px 6px rgba(0,0,0,0.2); }
    .diagram-node:hover::after { opacity: 1; visibility: visible; bottom: -32px; }
    .diagram-line { position: absolute; height: 2px; background-color: #ef4444; transform-origin: top left; z-index: 1; }
    .diagram-qty { position: absolute; background-color: #fef08a; border: 1px solid #ca8a04; border-radius: 4px; padding: 2px 6px; font-size: 10px; font-weight: bold; color: #854d0e; transform: translate(-50%, -50%); z-index: 2; pointer-events: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .diagram-endpoint { position: absolute; width: 10px; height: 10px; background-color: #ef4444; border-radius: 50%; z-index: 3; }

    @media (max-width: 640px) {
        .diagram-modal-header { align-items:flex-start; flex-direction:column; }
        .diagram-header-actions { justify-content:flex-end; width:100%; }
        .diagram-action-btn { flex:1; justify-content:center; }
    }

    @keyframes spin { 100% { -webkit-transform: rotate(360deg); transform:rotate(360deg); } }
</style>
@endpush

@section('content')
    {{-- Hidden compat elements --}}
    <span id="treeBadge" style="display:none;">Secim bekleniyor</span>
    <span id="treeTitleMeta" style="display:none;">Henuz secilmedi</span>
    <span id="treeNodeCountMeta" style="display:none;">0</span>
    <span id="summaryFilteredMeta" style="display:none;">0</span>
    <span id="summaryTreeUpdatedMeta" style="display:none;">Bekleniyor</span>
    <span id="summaryProductsInline" style="display:none;">0</span>
    <span id="summaryFilteredInline" style="display:none;">0</span>
    <span id="summarySelectedInline" style="display:none;">Yok</span>
    <span id="summaryNodesInline" style="display:none;">0</span>

    <div class="bom-page">
        {{-- 1) Arama Bölümü --}}
        <section class="bom-search-section">
            <div class="bom-search-header">
                <div class="bom-search-icon">
                    <i class="bi bi-search"></i>
                </div>
                <div>
                    <h2>Ürün Bul</h2>
                    <p>Ağacını görmek istediğin ürünü ara ve seç</p>
                </div>
            </div>

            <div class="bom-search-bar">
                <i class="bi bi-search"></i>
                <input type="text" id="urunArama" placeholder="Ürün adını yaz..." oninput="filterUrunler()">
            </div>

            <div class="bom-filter-panel">
                <label class="bom-filter-control">
                    <span>Eşleşme</span>
                    <select id="filterMatchMode" onchange="filterUrunler()">
                        <option value="contains">İçerir</option>
                        <option value="starts">Başlar</option>
                        <option value="exact">Tam ad</option>
                        <option value="id">No</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Tür</span>
                    <select id="filterType" onchange="filterUrunler()">
                        <option value="all">Tümü</option>
                        <option value="nihai">Nihai Ürün</option>
                        <option value="ara">Ara Mamül</option>
                        <option value="ham">Ham Madde</option>
                        <option value="blank">Türsüz</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Bölüm</span>
                    <select id="filterDepartment" onchange="filterUrunler()">
                        <option value="all">Tümü</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Ağaç</span>
                    <select id="filterTree" onchange="filterUrunler()">
                        <option value="all">Tümü</option>
                        <option value="has">Ağacı var</option>
                        <option value="none">Ağacı yok</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Kullanım</span>
                    <select id="filterRole" onchange="filterUrunler()">
                        <option value="all">Tümü</option>
                        <option value="root">Kök ağaç</option>
                        <option value="parent">Altı olan</option>
                        <option value="child">Alt parça</option>
                        <option value="leaf">Yaprak</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Parça</span>
                    <select id="filterPartCount" onchange="filterUrunler()">
                        <option value="all">Tümü</option>
                        <option value="none">0</option>
                        <option value="one">1</option>
                        <option value="few">2-3</option>
                        <option value="many">4+</option>
                    </select>
                </label>
                <label class="bom-filter-control">
                    <span>Sırala</span>
                    <select id="filterSort" onchange="filterUrunler()">
                        <option value="id_desc">No yeni</option>
                        <option value="id_asc">No eski</option>
                        <option value="name_asc">Ad A-Z</option>
                        <option value="type_asc">Tür</option>
                        <option value="department_asc">Bölüm</option>
                        <option value="parts_desc">Parça çok</option>
                        <option value="parts_asc">Parça az</option>
                    </select>
                </label>
            </div>

            <div class="bom-filter-stats" id="filterStats"></div>

            <div class="bom-search-count" id="searchCount">
                Yükleniyor...
            </div>

            <div class="bom-product-grid" id="urunListesi">
                <div style="grid-column: 1/-1; text-align:center; padding:30px; color:var(--z-text-muted);">
                    <i class="bi bi-hourglass-split" style="font-size:1.3rem;"></i>
                    <p style="margin-top:6px;">Ürünler yükleniyor...</p>
                </div>
            </div>
        </section>

        {{-- 2) Ağaç Görünümü --}}
        <section class="bom-tree-section" id="treeSection">
            <div id="treeContainer">
                <div class="bom-tree-empty">
                    <div class="empty-icon">🌳</div>
                    <h3>Henüz bir ürün seçilmedi</h3>
                    <p>Yukarıdan bir ürün seçtiğinde, o ürünün hangi parçalardan oluştuğunu burada göreceksin.</p>
                </div>
            </div>
        </section>

        <!-- Diyagram Modalı -->
        <div id="diagramModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
            <div class="diagram-modal-card">
                <div class="diagram-modal-header">
                    <h3><i class="bi bi-diagram-3" style="color:#d4af37;"></i> BOM Ağacı Önizlemesi</h3>
                    <div class="diagram-header-actions">
                        <button type="button" class="diagram-action-btn primary" onclick="openSelectedBomEditorFromDiagram()" title="Seçili ürünün alt bağlantılarını düzenle">
                            <i class="bi bi-pencil-square"></i> Düzenle
                        </button>
                        <button type="button" class="diagram-close" onclick="kapatDiyagramModal()" aria-label="Kapat">&times;</button>
                    </div>
                </div>
                <div id="modalDiagramPanel" class="diagram-panel" style="width:100%; height:600px; background:#fffdf0; border:2px dashed #d4af37; border-radius:12px; display:block;">
                </div>
            </div>
        </div>

        <div id="bomEditorModal" class="bom-editor-modal" aria-hidden="true">
            <div class="bom-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="bomEditorTitle">
                <div class="bom-editor-head">
                    <div class="bom-editor-title">
                        <i class="bi bi-pencil-square" style="color:var(--z-accent); font-size:1.15rem;"></i>
                        <div>
                            <h3 id="bomEditorTitle">Ürün Ağacı Düzenle</h3>
                            <span id="bomEditorSubtitle">Seçim yok</span>
                        </div>
                    </div>
                    <button type="button" class="bom-editor-close" onclick="closeBomEditor()" aria-label="Kapat">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="bom-editor-body">
                    <div id="bomEditorSummary"></div>
                    <div class="bom-editor-panels">
                        <div class="bom-editor-add-panel">
                            <div class="bom-editor-panel-head">
                                <div><i class="bi bi-plus-square"></i> Alt Bileşen Ekle</div>
                                <span id="bomEditorCandidateCount">0 uygun kayıt</span>
                            </div>
                            <div class="bom-editor-picker">
                                <label class="bom-editor-field">
                                    <span>Ara</span>
                                    <input type="text" id="bomEditorSearch" placeholder="Ad veya no" oninput="renderBomEditorCandidates()">
                                </label>
                                <label class="bom-editor-field">
                                    <span>Tür</span>
                                    <select id="bomEditorTypeFilter" onchange="renderBomEditorCandidates()">
                                        <option value="all">Tümü</option>
                                        <option value="nihai">Nihai Ürün</option>
                                        <option value="ara">Ara Mamül</option>
                                        <option value="ham">Ham Madde</option>
                                        <option value="blank">Türsüz</option>
                                    </select>
                                </label>
                                <label class="bom-editor-field">
                                    <span>Bölüm</span>
                                    <select id="bomEditorDepartmentFilter" onchange="renderBomEditorCandidates()">
                                        <option value="all">Tümü</option>
                                    </select>
                                </label>
                                <label class="bom-editor-field">
                                    <span>Adet</span>
                                    <input type="number" id="bomEditorAddQuantity" min="1" step="1" value="1">
                                </label>
                            </div>
                            <div id="bomEditorCandidates" class="bom-editor-candidates"></div>
                        </div>
                        <div class="bom-editor-selected-panel">
                            <div class="bom-editor-panel-head">
                                <div><i class="bi bi-diagram-3"></i> Mevcut Alt Parçalar</div>
                                <span id="bomEditorSelectedCount">0 parça</span>
                            </div>
                            <div id="bomEditorRows"></div>
                        </div>
                    </div>
                </div>
                <div class="bom-editor-foot">
                    <div class="bom-editor-status" id="bomEditorStatus"></div>
                    <div class="bom-action-group">
                        <button type="button" class="bom-action-btn danger" onclick="clearBomEditorRows()">
                            <i class="bi bi-trash3"></i> Tümünü Sil
                        </button>
                        <button type="button" class="bom-action-btn" onclick="closeBomEditor()">Vazgeç</button>
                        <button type="button" class="bom-action-btn primary" onclick="saveBomEditor()">
                            <i class="bi bi-check2"></i> Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
<script>
let allUrunler = [];
let filteredUrunler = [];
let urunMap = {};
let referencedUrunIds = new Set();
let selectedProductId = null;
let editingProductId = null;
let editorReturnProductId = null;
let editingRows = [];
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

// ── Ürünleri Yükle ──
function loadUrunler() {
    fetch('/api/database/components?limit=9999', { headers: { Accept: 'application/json' } })
        .then((r) => r.json())
        .then((data) => {
            allUrunler = data.data || [];
            urunMap = {};
            allUrunler.forEach((urun) => {
                urunMap[String(urun.id)] = urun;
            });
            referencedUrunIds = collectReferencedProductIds(allUrunler);

            populateProductFilters();
            filterUrunler();
            setTreeStamp();
        });
}

// ── Ürün Listesi Render ──
function renderUrunListe(list) {
    const html = list.map((urun) => {
        const typeText = getDisplayType(urun.type);
        const isActive = Number(selectedProductId) === Number(urun.id);
        const subCount = getChildCount(urun);
        const typeClass = getTypeClass(urun.type);
        const emoji = getTypeEmoji(urun.type);
        const departmentName = getDepartmentName(urun);
        const roleText = getProductRoleText(urun);

        return `
            <div class="bom-product-item ${isActive ? 'active' : ''}"
                 data-product-id="${escapeHtml(String(urun.id))}"
                 onclick="showTree(${urun.id}, '${escapeJsString(urun.name || '')}', '${escapeJsString(urun.path || '')}', this)">
                <div class="bom-item-emoji ${typeClass}">${emoji}</div>
                <div class="bom-item-info">
                    <h4>${escapeHtml(urun.name || 'Bilinmiyor')}</h4>
                    <span>${subCount > 0 ? subCount + ' parça' : 'Parçası yok'}</span>
                    <small>#${escapeHtml(String(urun.id))} · ${escapeHtml(departmentName)} · ${escapeHtml(roleText)}</small>
                </div>
                <span class="bom-item-type ${typeClass}">${escapeHtml(typeText)}</span>
            </div>
        `;
    }).join('');

    document.getElementById('urunListesi').innerHTML = html || `
        <div style="grid-column:1/-1; text-align:center; padding:30px; color:var(--z-text-muted);">
            <p>Sonuç bulunamadı.</p>
        </div>`;
}

// ── Arama Filtreleme ──
function filterUrunler() {
    filteredUrunler = applyProductFilters(allUrunler);
    renderUrunListe(filteredUrunler);
    updateSearchCount();
    renderFilterStats();
}

function applyProductFilters(list) {
    const query = normalizeSearch(document.getElementById('urunArama')?.value || '');
    const matchMode = getFilterValue('filterMatchMode', 'contains');
    const typeFilter = getFilterValue('filterType', 'all');
    const departmentFilter = getFilterValue('filterDepartment', 'all');
    const treeFilter = getFilterValue('filterTree', 'all');
    const roleFilter = getFilterValue('filterRole', 'all');
    const partCountFilter = getFilterValue('filterPartCount', 'all');
    const sortMode = getFilterValue('filterSort', 'id_desc');

    const filtered = list.filter((urun) => {
        const typeKey = normalizeTypeKey(urun.type);
        const rawDepartment = getRawDepartmentName(urun);

        if (!matchesSearchMode(urun, query, matchMode)) return false;
        if (typeFilter !== 'all' && typeKey !== typeFilter) return false;
        if (departmentFilter !== 'all') {
            if (departmentFilter === '__blank') {
                if (rawDepartment !== '') return false;
            } else if (rawDepartment !== departmentFilter) {
                return false;
            }
        }
        if (!matchesTreeFilter(urun, treeFilter)) return false;
        if (!matchesRoleFilter(urun, roleFilter)) return false;
        if (!matchesPartCountFilter(urun, partCountFilter)) return false;

        return true;
    });

    return sortProducts(filtered, sortMode);
}

function matchesSearchMode(urun, query, matchMode) {
    if (!query) return true;

    if (matchMode === 'id') {
        return String(urun.id || '').startsWith(query);
    }

    const name = normalizeSearch(urun.name || '');
    if (matchMode === 'starts') return name.startsWith(query);
    if (matchMode === 'exact') return name === query;
    return name.includes(query);
}

function matchesTreeFilter(urun, filter) {
    const hasTree = getChildCount(urun) > 0;
    if (filter === 'has') return hasTree;
    if (filter === 'none') return !hasTree;
    return true;
}

function matchesRoleFilter(urun, filter) {
    const id = String(urun.id);
    const hasTree = getChildCount(urun) > 0;
    const isReferenced = referencedUrunIds.has(id);

    if (filter === 'root') return hasTree && !isReferenced;
    if (filter === 'parent') return hasTree;
    if (filter === 'child') return isReferenced;
    if (filter === 'leaf') return !hasTree;
    return true;
}

function matchesPartCountFilter(urun, filter) {
    const count = getChildCount(urun);
    if (filter === 'none') return count === 0;
    if (filter === 'one') return count === 1;
    if (filter === 'few') return count >= 2 && count <= 3;
    if (filter === 'many') return count >= 4;
    return true;
}

function sortProducts(list, sortMode) {
    const sorted = [...list];
    const byText = (a, b) => normalizeSearch(a).localeCompare(normalizeSearch(b), 'tr');
    const byNumber = (a, b) => Number(a || 0) - Number(b || 0);

    sorted.sort((a, b) => {
        if (sortMode === 'id_asc') return byNumber(a.id, b.id);
        if (sortMode === 'name_asc') return byText(a.name, b.name);
        if (sortMode === 'type_asc') return byText(getDisplayType(a.type), getDisplayType(b.type)) || byText(a.name, b.name);
        if (sortMode === 'department_asc') return byText(getDepartmentName(a), getDepartmentName(b)) || byText(a.name, b.name);
        if (sortMode === 'parts_desc') return byNumber(getChildCount(b), getChildCount(a)) || byText(a.name, b.name);
        if (sortMode === 'parts_asc') return byNumber(getChildCount(a), getChildCount(b)) || byText(a.name, b.name);
        return byNumber(b.id, a.id);
    });

    return sorted;
}

function populateProductFilters() {
    const departmentSelect = document.getElementById('filterDepartment');
    if (!departmentSelect) return;

    const currentValue = departmentSelect.value || 'all';
    const departments = [...new Set(allUrunler.map(getRawDepartmentName).filter(Boolean))]
        .sort((a, b) => a.localeCompare(b, 'tr'));
    const hasBlank = allUrunler.some((urun) => getRawDepartmentName(urun) === '');

    departmentSelect.innerHTML = [
        '<option value="all">Tümü</option>',
        hasBlank ? '<option value="__blank">Bölümsüz</option>' : '',
        ...departments.map((department) => `<option value="${escapeHtml(department)}">${escapeHtml(department)}</option>`)
    ].join('');

    departmentSelect.value = [...departmentSelect.options].some((option) => option.value === currentValue)
        ? currentValue
        : 'all';
}

function renderFilterStats() {
    const host = document.getElementById('filterStats');
    if (!host) return;

    const stats = getFilteredStats(filteredUrunler);
    const chips = [
        { label: 'Nihai', count: stats.nihai, control: 'filterType', value: 'nihai' },
        { label: 'Ara', count: stats.ara, control: 'filterType', value: 'ara' },
        { label: 'Ham', count: stats.ham, control: 'filterType', value: 'ham' },
        { label: 'Türsüz', count: stats.blank, control: 'filterType', value: 'blank' },
        { label: 'Ağacı var', count: stats.hasTree, control: 'filterTree', value: 'has' },
        { label: 'Ağacı yok', count: stats.noTree, control: 'filterTree', value: 'none' }
    ];

    host.innerHTML = chips.map((chip) => {
        const control = document.getElementById(chip.control);
        const active = control && control.value === chip.value ? ' active' : '';
        return `<button type="button" class="bom-stat-chip${active}" onclick="setQuickFilter('${chip.control}', '${chip.value}')">${escapeHtml(chip.label)} <strong>${formatNumber(chip.count)}</strong></button>`;
    }).join('');
}

function setQuickFilter(controlId, value) {
    const control = document.getElementById(controlId);
    if (!control) return;
    control.value = control.value === value ? 'all' : value;
    filterUrunler();
}

function getFilteredStats(list) {
    const stats = { nihai: 0, ara: 0, ham: 0, blank: 0, other: 0, hasTree: 0, noTree: 0 };

    list.forEach((urun) => {
        const typeKey = normalizeTypeKey(urun.type);
        stats[typeKey] = (stats[typeKey] || 0) + 1;
        if (getChildCount(urun) > 0) {
            stats.hasTree += 1;
        } else {
            stats.noTree += 1;
        }
    });

    return stats;
}

function collectReferencedProductIds(list) {
    const ids = new Set();
    list.forEach((urun) => {
        getPathItems(urun.path).forEach((item) => {
            if (item.component_id) ids.add(String(item.component_id));
        });
    });
    return ids;
}

// ── Ağaç Göster ──
function showTree(no, adi, yol, element) {
    selectedProductId = no;

    // Aktif kartı işaretle
    document.querySelectorAll('#urunListesi .bom-product-item').forEach((card) => card.classList.remove('active'));
    if (element) element.classList.add('active');

    // Compat güncelle
    document.getElementById('treeTitleMeta').textContent = adi;
    document.getElementById('summarySelectedInline').textContent = truncateValue(adi, 14);

    // Ağaç olup olmadığını kontrol et
    if (!yol || !yol.trim()) {
        document.getElementById('treeContainer').innerHTML = `
            <div class="bom-no-data">
                <div class="no-data-icon">📋</div>
                <h3>Bu ürün için ağaç tanımlı değil</h3>
                <p>"${escapeHtml(adi)}" ürününün alt parça tanımı bulunmuyor.</p>
                <button type="button" class="bom-action-btn primary" onclick="openBomEditor(${Number(no)})">
                    <i class="bi bi-plus-lg"></i> Ağaç Oluştur
                </button>
            </div>`;
        updateTreeSummary(0, 'BOM yok');
        markSelectedProductCard();
        return;
    }

    const pathParts = getImmediatePathParts(yol);
    const totalNodeCount = countAllNodes(yol, 1);
    const piece = urunMap[String(no)];
    const typeText = piece ? getDisplayType(piece.type) : 'Ürün';

    // Sayfa oluştur
    const html = `
        <!-- Özet Bandı -->
        <div class="bom-summary-strip bom-animated">
            <div class="strip-item">
                <i class="bi bi-box-seam" style="color: var(--z-accent);"></i>
                <span>Ana ürün: <strong>${escapeHtml(truncateValue(adi, 30))}</strong></span>
            </div>
            <div class="strip-item">
                <i class="bi bi-diagram-3" style="color: var(--z-warning);"></i>
                <span>Toplam <strong>${formatNumber(totalNodeCount)}</strong> parça</span>
            </div>
            <div class="strip-item">
                <i class="bi bi-layers" style="color: #8b5cf6;"></i>
                <span><strong>${formatNumber(pathParts.length)}</strong> ana bileşen</span>
            </div>
            <div class="strip-item">
                <button type="button" onclick="acDiyagramModal()" style="background: var(--z-bg-card); border: 1px solid #d4af37; border-radius: 6px; padding: 4px 10px; font-size: 0.82rem; color: var(--z-text); cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s ease;">
                    <i class="bi bi-diagram-3" style="color: #d4af37; font-size: 1rem;"></i> BOM Görseli
                </button>
            </div>
            <div class="bom-action-group">
                <button type="button" class="bom-action-btn primary" onclick="openBomEditor(${Number(no)})">
                    <i class="bi bi-pencil-square"></i> Düzenle
                </button>
                <button type="button" class="bom-action-btn danger" onclick="clearSelectedBomPath()">
                    <i class="bi bi-trash3"></i> Ağacı Sil
                </button>
            </div>
            <div class="bom-legend">
                <div class="bom-legend-item"><div class="bom-legend-dot ara"></div> Ara Mamül</div>
                <div class="bom-legend-item"><div class="bom-legend-dot ham"></div> Ham Madde</div>
                <div class="bom-legend-item"><div class="bom-legend-dot nihai"></div> Nihai Ürün</div>
            </div>
        </div>

        <!-- Ana Ürün Kartı -->
        <div class="bom-root bom-animated">
            <div class="bom-root-icon">🏭</div>
            <div class="bom-root-info">
                <h3>${escapeHtml(adi)}</h3>
                <p><i class="bi bi-tag"></i>${escapeHtml(typeText)} &nbsp;·&nbsp; <i class="bi bi-hash"></i>${escapeHtml(String(no))}</p>
            </div>
            <div class="bom-root-badge">
                <span class="parts-count">${formatNumber(pathParts.length)} parça</span>
                <span class="total-count">${formatNumber(totalNodeCount)} toplam</span>
            </div>
        </div>

        <!-- Alt Parçalar -->
        ${buildTreeHtml(yol, 1)}
    `;

    document.getElementById('treeContainer').innerHTML = html;
    updateTreeSummary(totalNodeCount, `${formatNumber(pathParts.length)} alt bilesen`);
    markSelectedProductCard();

    // Ağaç bölümüne scroll
    document.getElementById('treeSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── Ağaç HTML Oluştur ──
function buildTreeHtml(path, level) {
    if (!path || level > 4) return '';

    const parts = getImmediatePathParts(path);
    if (!parts.length) return '';

    const cards = parts.map((part, idx) => {
        const [pieceNo, quantity] = String(part).split('-');
        const piece = urunMap[String(pieceNo)];
        const pieceName = piece ? piece.name : `Parça #${pieceNo}`;
        const pieceType = piece ? getDisplayType(piece.type) : 'Parça';
        const nestedPath = piece ? piece.path : '';
        const nestedCount = getImmediatePathParts(nestedPath).length;
        const typeClass = getNodeTypeClass(piece ? piece.type : pieceType);
        const emoji = getTypeEmoji(piece ? piece.type : pieceType);
        const tagClass = getTagClass(piece ? piece.type : pieceType);
        const hasChildren = nestedPath && nestedCount > 0;
        const nodeId = `node-${level}-${idx}`;

        let childHtml = '';
        if (hasChildren) {
            childHtml = `
                <div class="bom-sub-tree" id="sub-${nodeId}" style="max-height: 9999px;">
                    ${buildTreeHtml(nestedPath, level + 1)}
                </div>`;
        }

        return `
            <div class="bom-child-wrapper bom-animated">
                <div class="bom-node">
                    <div class="bom-node-icon ${typeClass}">${emoji}</div>
                    <div class="bom-node-info">
                        <h5>${escapeHtml(pieceName)}</h5>
                        <span>${escapeHtml(pieceType)}${nestedCount > 0 ? ' · ' + nestedCount + ' alt parça' : ''}</span>
                    </div>
                    <div class="bom-node-meta">
                        <span class="bom-qty">${formatNumber(quantity || 1)}x</span>
                        <span class="bom-type-tag ${tagClass}">${escapeHtml(pieceType)}</span>
                        ${piece ? `<button type="button" class="bom-node-edit" onclick="event.stopPropagation(); openBomEditor(${Number(pieceNo)}, selectedProductId)" title="Alt parçaları düzenle" aria-label="Alt parçaları düzenle"><i class="bi bi-pencil"></i></button>` : ''}
                        ${hasChildren ? `<button class="bom-node-expand open" onclick="toggleSubTree('${nodeId}', this)"><i class="bi bi-chevron-right"></i></button>` : ''}
                    </div>
                </div>
                ${childHtml}
            </div>
        `;
    }).join('');

    return `<div class="bom-children level-${level}">${cards}</div>`;
}

// ── Alt ağaç aç/kapa ──
function toggleSubTree(nodeId, btn) {
    const sub = document.getElementById('sub-' + nodeId);
    if (!sub) return;

    if (sub.classList.contains('collapsed')) {
        sub.classList.remove('collapsed');
        btn.classList.add('open');
    } else {
        sub.classList.add('collapsed');
        btn.classList.remove('open');
    }
}

function openBomEditor(componentId, returnProductId = null) {
    const piece = urunMap[String(componentId)];
    if (!piece) return;

    editingProductId = Number(componentId);
    editorReturnProductId = returnProductId ? Number(returnProductId) : null;
	    editingRows = getPathItems(piece.path).map((item) => ({
	        component_id: Number(item.component_id),
	        quantity: Number(item.quantity || 1)
	    }));

	    document.getElementById('bomEditorSubtitle').textContent = `${piece.name || 'Ürün'} · #${piece.id}`;
	    resetBomEditorFilters();
	    populateBomEditorDepartmentFilter();
	    setBomEditorStatus('');
	    renderBomEditor();

	    const modal = document.getElementById('bomEditorModal');
	    modal.classList.add('open');
	    modal.setAttribute('aria-hidden', 'false');
}

function closeBomEditor() {
    const modal = document.getElementById('bomEditorModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    editingProductId = null;
    editorReturnProductId = null;
	    editingRows = [];
	}

	function renderBomEditor() {
	    renderBomEditorSummary();
	    renderBomEditorRows();
	    renderBomEditorCandidates();
	}

	function renderBomEditorSummary() {
	    const host = document.getElementById('bomEditorSummary');
	    const piece = urunMap[String(editingProductId)];
	    if (!host || !piece) return;

	    host.innerHTML = `
	        <div class="bom-editor-summary">
	            <div>
	                <h4>${escapeHtml(piece.name || 'Bilinmiyor')}</h4>
	                <span>#${escapeHtml(String(piece.id))} · ${escapeHtml(getDisplayType(piece.type))} · ${escapeHtml(getDepartmentName(piece))}</span>
	            </div>
	            <div class="bom-editor-count">
	                ${formatNumber(editingRows.length)}
	                <small>alt parça</small>
	            </div>
	        </div>`;
	}

	function resetBomEditorFilters() {
	    setFilterDefault('bomEditorSearch', '');
	    setFilterDefault('bomEditorTypeFilter', 'all');
	    setFilterDefault('bomEditorDepartmentFilter', 'all');
	    setFilterDefault('bomEditorAddQuantity', '1');
	}

	function populateBomEditorDepartmentFilter() {
	    const departmentSelect = document.getElementById('bomEditorDepartmentFilter');
	    if (!departmentSelect) return;

	    const currentValue = departmentSelect.value || 'all';
	    const departments = [...new Set(allUrunler.map(getRawDepartmentName).filter(Boolean))]
	        .sort((a, b) => a.localeCompare(b, 'tr'));
	    const hasBlank = allUrunler.some((urun) => getRawDepartmentName(urun) === '');

	    departmentSelect.innerHTML = [
	        '<option value="all">Tümü</option>',
	        hasBlank ? '<option value="__blank">Bölümsüz</option>' : '',
	        ...departments.map((department) => `<option value="${escapeHtml(department)}">${escapeHtml(department)}</option>`)
	    ].join('');

	    departmentSelect.value = [...departmentSelect.options].some((option) => option.value === currentValue)
	        ? currentValue
	        : 'all';
	}

	function renderBomEditorRows() {
	    const host = document.getElementById('bomEditorRows');
	    const countHost = document.getElementById('bomEditorSelectedCount');
	    if (countHost) countHost.textContent = `${formatNumber(editingRows.length)} parça`;

	    if (!editingRows.length) {
	        host.innerHTML = '<div class="bom-editor-empty">Alt parça yok</div>';
	        return;
	    }

	    host.innerHTML = editingRows.map((row, index) => {
	        const piece = urunMap[String(row.component_id)];
	        const name = piece ? (piece.name || 'Bilinmiyor') : `Parça #${row.component_id}`;
	        const type = piece ? getDisplayType(piece.type) : 'Parça';
	        const department = piece ? getDepartmentName(piece) : 'Bölümsüz';
	        const childCount = piece ? getChildCount(piece) : 0;

	        return `
	            <div class="bom-editor-row">
	                <div class="bom-editor-row-no">${index + 1}</div>
	                <div>
	                    <div class="bom-editor-row-title">${escapeHtml(name)}</div>
	                    <div class="bom-editor-row-meta">
	                        <span class="bom-editor-mini-badge">#${escapeHtml(String(row.component_id))}</span>
	                        <span class="bom-editor-mini-badge">${escapeHtml(type)}</span>
	                        <span>${escapeHtml(department)}</span>
	                        <span>${childCount > 0 ? formatNumber(childCount) + ' alt' : 'alt yok'}</span>
	                    </div>
	                </div>
	                <label class="bom-editor-field">
	                    <span>Adet</span>
	                    <input type="number" min="1" step="1" value="${escapeHtml(String(row.quantity || 1))}" onchange="updateBomEditorRow(${index}, 'quantity', this.value)" oninput="updateBomEditorRow(${index}, 'quantity', this.value)">
	                </label>
	                <button type="button" class="bom-editor-remove" onclick="removeBomEditorRow(${index})" aria-label="Kaldır" title="Kaldır">
	                    <i class="bi bi-trash3"></i>
	                </button>
	            </div>
	        `;
	    }).join('');
	}

	function renderBomEditorCandidates() {
	    const host = document.getElementById('bomEditorCandidates');
	    const countHost = document.getElementById('bomEditorCandidateCount');
	    if (!host) return;

	    const candidates = getBomEditorCandidates();
	    const visible = candidates.slice(0, 36);
	    if (countHost) countHost.textContent = `${formatNumber(candidates.length)} uygun kayıt`;

	    if (!visible.length) {
	        host.innerHTML = '<div class="bom-editor-empty inline">Uygun kayıt yok</div>';
	        return;
	    }

	    host.innerHTML = visible.map((urun) => {
	        const childCount = getChildCount(urun);
	        return `
	            <div class="bom-editor-candidate">
	                <div>
	                    <h5>${escapeHtml(urun.name || 'Bilinmiyor')}</h5>
	                    <div class="bom-editor-candidate-meta">
	                        <span class="bom-editor-mini-badge">#${escapeHtml(String(urun.id))}</span>
	                        <span class="bom-editor-mini-badge">${escapeHtml(getDisplayType(urun.type))}</span>
	                        <span>${escapeHtml(getDepartmentName(urun))}</span>
	                        <span>${childCount > 0 ? formatNumber(childCount) + ' alt' : 'alt yok'}</span>
	                    </div>
	                </div>
	                <button type="button" class="bom-editor-add-btn" onclick="addBomEditorRow(${Number(urun.id)})" title="Ekle" aria-label="Ekle">
	                    <i class="bi bi-plus-lg"></i>
	                </button>
	            </div>
	        `;
	    }).join('');
	}

	function getBomEditorCandidates() {
	    const currentId = Number(editingProductId);
	    const used = new Set(editingRows.map((row) => Number(row.component_id)).filter(Boolean));
	    const query = normalizeSearch(document.getElementById('bomEditorSearch')?.value || '');
	    const typeFilter = getFilterValue('bomEditorTypeFilter', 'all');
	    const departmentFilter = getFilterValue('bomEditorDepartmentFilter', 'all');

	    return allUrunler
	        .filter((urun) => {
	            const id = Number(urun.id);
	            if (!id || id === currentId || used.has(id)) return false;
	            if (wouldCreateLocalBomCycle(currentId, id)) return false;

	            if (query) {
	                const name = normalizeSearch(urun.name || '');
	                const no = String(urun.id || '');
	                if (!name.includes(query) && !no.startsWith(query)) return false;
	            }

	            if (typeFilter !== 'all' && normalizeTypeKey(urun.type) !== typeFilter) return false;

	            const department = getRawDepartmentName(urun);
	            if (departmentFilter === '__blank') return department === '';
	            if (departmentFilter !== 'all' && department !== departmentFilter) return false;

	            return true;
	        })
	        .sort((a, b) => normalizeSearch(a.name).localeCompare(normalizeSearch(b.name), 'tr') || Number(a.id) - Number(b.id));
	}

	function addBomEditorRow(componentId = null) {
	    const used = new Set(editingRows.map((row) => Number(row.component_id)).filter(Boolean));
	    const chosenId = Number(componentId || 0);
	    const firstAvailable = chosenId
	        ? allUrunler.find((urun) => Number(urun.id) === chosenId)
	        : getBomEditorCandidates()[0];

	    if (!firstAvailable) {
	        setBomEditorStatus('Eklenecek kayıt bulunamadı.', 'error');
	        return;
	    }
	    if (used.has(Number(firstAvailable.id))) {
	        setBomEditorStatus('Bu parça zaten listede.', 'error');
	        return;
	    }

	    editingRows.push({
	        component_id: Number(firstAvailable.id),
	        quantity: getBomEditorAddQuantity()
	    });
	    setBomEditorStatus(`${firstAvailable.name || 'Parça'} eklendi.`, 'success');
	    renderBomEditor();
	}

	function getBomEditorAddQuantity() {
	    const value = Number(document.getElementById('bomEditorAddQuantity')?.value || 1);
	    return Math.max(1, Math.round(value || 1));
	}

function updateBomEditorRow(index, field, value) {
    if (!editingRows[index]) return;
    editingRows[index][field] = field === 'quantity' ? Math.max(1, Number(value || 1)) : Number(value || 0);
}

	function removeBomEditorRow(index) {
	    editingRows.splice(index, 1);
	    renderBomEditor();
	}

	function clearBomEditorRows() {
	    editingRows = [];
	    renderBomEditor();
	    setBomEditorStatus('Kaydettiğinde tüm alt bağlantılar silinecek.');
	}

async function saveBomEditor() {
    if (!editingProductId) return;

    const targetId = Number(editingProductId);
    const payloadItems = [];
    const seen = new Set();

    for (const row of editingRows) {
        const componentId = Number(row.component_id || 0);
        const quantity = Number(row.quantity || 0);
        if (!componentId) continue;

        if (componentId === targetId) {
            setBomEditorStatus('Bir ürün kendi alt parçası olamaz.', 'error');
            return;
        }
        if (seen.has(componentId)) {
            setBomEditorStatus('Aynı alt parça birden fazla eklenemez. Adedi tek satırdan artırın.', 'error');
            return;
        }
        if (quantity <= 0) {
            setBomEditorStatus('Adet sıfırdan büyük olmalı.', 'error');
            return;
        }
        if (wouldCreateLocalBomCycle(targetId, componentId)) {
            setBomEditorStatus('Bu seçim ürün ağacında döngü oluşturur.', 'error');
            return;
        }

        seen.add(componentId);
        payloadItems.push({ component_id: componentId, quantity: Math.round(quantity) });
    }

    setBomEditorStatus('Kaydediliyor...');

    try {
        const response = await fetch(`/api/database/components/${targetId}/bom-path`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ items: payloadItems })
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Ürün ağacı kaydedilemedi.');
        }

        setComponentPath(targetId, data.path || '');
        const updated = urunMap[String(targetId)];
        if (!updated) {
            throw new Error('Güncellenen ürün listede bulunamadı.');
        }
        const displayId = editorReturnProductId && urunMap[String(editorReturnProductId)]
            ? Number(editorReturnProductId)
            : Number(updated.id);
        const displayProduct = urunMap[String(displayId)] || updated;

        closeBomEditor();
        selectedProductId = Number(displayProduct.id);
        filterUrunler();
        showTree(displayProduct.id, displayProduct.name || '', displayProduct.path || '', null);
    } catch (error) {
        setBomEditorStatus(error.message || 'Ürün ağacı kaydedilemedi.', 'error');
    }
}

async function clearSelectedBomPath() {
    if (!selectedProductId) return;

    const piece = urunMap[String(selectedProductId)];
    if (!piece) return;

    if (!window.confirm(`${piece.name || 'Seçili ürün'} ağacındaki tüm alt bağlantılar silinsin mi?`)) {
        return;
    }

    try {
        const response = await fetch(`/api/database/components/${selectedProductId}/bom-path`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Ürün ağacı silinemedi.');
        }

        setComponentPath(selectedProductId, '');
        const updated = urunMap[String(selectedProductId)];
        filterUrunler();
        showTree(updated.id, updated.name || '', '', null);
    } catch (error) {
        window.alert(error.message || 'Ürün ağacı silinemedi.');
    }
}

function getPathItems(path) {
    return getImmediatePathParts(path)
        .map((part) => {
            const pieces = String(part).split('-');
            return {
                component_id: Number(pieces[0] || 0),
                quantity: Math.max(1, Number(pieces[1] || 1))
            };
        })
        .filter((item) => item.component_id > 0);
}

function setComponentPath(componentId, path) {
    const key = String(componentId);
    if (urunMap[key]) urunMap[key].path = path;
    allUrunler.forEach((urun) => {
        if (Number(urun.id) === Number(componentId)) urun.path = path;
    });
	    filteredUrunler.forEach((urun) => {
	        if (Number(urun.id) === Number(componentId)) urun.path = path;
	    });
	    referencedUrunIds = collectReferencedProductIds(allUrunler);
	}

function setBomEditorStatus(message, type = '') {
    const el = document.getElementById('bomEditorStatus');
    el.textContent = message || '';
    el.className = `bom-editor-status ${type || ''}`.trim();
}

function wouldCreateLocalBomCycle(ownerId, candidateChildId) {
    if (Number(ownerId) === Number(candidateChildId)) return true;
    return localBomDescendantContains(candidateChildId, ownerId, new Set());
}

function localBomDescendantContains(componentId, targetId, visited) {
    const key = String(componentId);
    if (visited.has(key)) return false;
    visited.add(key);

    const piece = urunMap[key];
    if (!piece) return false;

    return getPathItems(piece.path).some((item) => {
        if (Number(item.component_id) === Number(targetId)) return true;
        return localBomDescendantContains(item.component_id, targetId, visited);
    });
}

function markSelectedProductCard() {
    document.querySelectorAll('#urunListesi .bom-product-item').forEach((card) => {
        card.classList.toggle('active', Number(card.dataset.productId) === Number(selectedProductId));
    });
}

// ── Tip Sınıfları ──
function getTypeClass(typeText) {
    const key = normalizeTypeKey(typeText);
    if (key === 'nihai') return 'nihai';
    if (key === 'ara') return 'ara';
    if (key === 'ham') return 'ham';
    if (key === 'blank') return 'blank';
    return '';
}

function getNodeTypeClass(typeText) {
    const key = normalizeTypeKey(typeText);
    if (key === 'ara') return 'ara-mamul';
    if (key === 'ham') return 'ham-madde';
    if (key === 'nihai') return 'nihai-urun';
    return 'parca';
}

function getTagClass(typeText) {
    const key = normalizeTypeKey(typeText);
    if (key === 'ara') return 'ara';
    if (key === 'ham') return 'ham';
    if (key === 'nihai') return 'nihai';
    return 'parca';
}

function getTypeEmoji(typeText) {
    const key = normalizeTypeKey(typeText);
    if (key === 'nihai') return '📦';
    if (key === 'ara') return '🔧';
    if (key === 'ham') return '🪵';
    return '⚙️';
}

function getDisplayType(typeText) {
    const key = normalizeTypeKey(typeText);
    if (key === 'nihai') return 'Nihai Ürün';
    if (key === 'ara') return 'Ara Mamül';
    if (key === 'ham') return 'Ham Madde';
    if (key === 'blank') return 'Türsüz';
    return String(typeText || 'Belirtilmedi');
}

function normalizeTypeKey(typeText) {
    const text = normalizeSearch(typeText);
    if (!text) return 'blank';
    if (text.includes('nihai') || text.includes('nihayi')) return 'nihai';
    if (text.includes('ara')) return 'ara';
    if (text.includes('ham')) return 'ham';
    return 'other';
}

// ── Yardımcı Fonksiyonlar ──
function getFilterValue(id, fallback = 'all') {
    return document.getElementById(id)?.value || fallback;
}

function getChildCount(urun) {
    return getImmediatePathParts(urun?.path).length;
}

function getRawDepartmentName(urun) {
    return String(urun?.department_name || '').trim();
}

function getDepartmentName(urun) {
    return getRawDepartmentName(urun) || 'Bölümsüz';
}

function getProductRoleText(urun) {
    const id = String(urun?.id || '');
    const hasTree = getChildCount(urun) > 0;
    const isReferenced = referencedUrunIds.has(id);

    if (hasTree && !isReferenced) return 'Kök ağaç';
    if (hasTree && isReferenced) return 'Altı var';
    if (!hasTree && isReferenced) return 'Alt parça';
    return 'Yaprak';
}

function getImmediatePathParts(path) {
    return String(path || '').split(':').filter((part) => part.trim() !== '');
}

function countAllNodes(path, level) {
    if (!path || level > 4) return 0;
    const parts = getImmediatePathParts(path);
    let count = parts.length;
    parts.forEach((part) => {
        const [pieceNo] = String(part).split('-');
        const piece = urunMap[String(pieceNo)];
        if (piece && piece.path) {
            count += countAllNodes(piece.path, level + 1);
        }
    });
    return count;
}

function updateSearchCount() {
    const el = document.getElementById('searchCount');
    const activeFilters = getActiveFilterCount();
    const filterText = activeFilters > 0 ? ` · Aktif filtre: <strong>${formatNumber(activeFilters)}</strong>` : '';
    el.innerHTML = `Toplam <strong>${formatNumber(allUrunler.length)}</strong> ürün · Gösterilen: <strong>${formatNumber(filteredUrunler.length)}</strong>${filterText}`;
    document.getElementById('summaryProductsInline').textContent = formatNumber(allUrunler.length);
    document.getElementById('summaryFilteredInline').textContent = formatNumber(filteredUrunler.length);
    document.getElementById('summaryFilteredMeta').textContent = formatNumber(filteredUrunler.length);
}

function getActiveFilterCount() {
    let count = 0;
    if (normalizeSearch(document.getElementById('urunArama')?.value || '')) count += 1;
    if (getFilterValue('filterMatchMode', 'contains') !== 'contains') count += 1;
    if (getFilterValue('filterType', 'all') !== 'all') count += 1;
    if (getFilterValue('filterDepartment', 'all') !== 'all') count += 1;
    if (getFilterValue('filterTree', 'all') !== 'all') count += 1;
    if (getFilterValue('filterRole', 'all') !== 'all') count += 1;
    if (getFilterValue('filterPartCount', 'all') !== 'all') count += 1;
    return count;
}

function updateTreeSummary(nodeCount, badgeText) {
    document.getElementById('summaryNodesInline').textContent = formatNumber(nodeCount);
    document.getElementById('treeNodeCountMeta').textContent = formatNumber(nodeCount);
    document.getElementById('treeBadge').textContent = badgeText || 'Hazir';
}

function resetTreeSelection() {
    selectedProductId = null;
    document.getElementById('urunArama').value = '';
    setFilterDefault('filterMatchMode', 'contains');
    setFilterDefault('filterType', 'all');
    setFilterDefault('filterDepartment', 'all');
    setFilterDefault('filterTree', 'all');
    setFilterDefault('filterRole', 'all');
    setFilterDefault('filterPartCount', 'all');
    setFilterDefault('filterSort', 'id_desc');
    filterUrunler();
    document.getElementById('treeTitleMeta').textContent = 'Henuz secilmedi';
    document.getElementById('summarySelectedInline').textContent = 'Yok';
    document.getElementById('treeContainer').innerHTML = `
        <div class="bom-tree-empty">
            <div class="empty-icon">🌳</div>
            <h3>Henüz bir ürün seçilmedi</h3>
            <p>Yukarıdan bir ürün seçtiğinde, o ürünün hangi parçalardan oluştuğunu burada göreceksin.</p>
        </div>`;
    updateTreeSummary(0, 'Secim bekleniyor');
}

function setFilterDefault(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value;
}

function setTreeStamp() {
    document.getElementById('summaryTreeUpdatedMeta').textContent = new Date().toLocaleTimeString('tr-TR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function truncateValue(value, maxLength) {
    const text = String(value || '');
    if (text.length <= maxLength) return text || 'Yok';
    return `${text.slice(0, maxLength)}...`;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function normalizeSearch(value) {
    return String(value ?? '')
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/ı/g, 'i')
        .replace(/\s+/g, ' ')
        .trim();
}

function escapeHtml(value) {
    return String(value ?? '')
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

document.addEventListener('DOMContentLoaded', () => {
    loadUrunler();
    updateTreeSummary(0, 'Secim bekleniyor');
});

// ════════════════════════════════════════════
// BOM AĞACI MODAL VE ÇİZİM KODLARI
// ════════════════════════════════════════════
let diagPoints = {};
let diagLines = [];
let diagNodeIds = {};
const nodeRadius = 25;

async function acDiyagramModal() {
    if (!selectedProductId) return;

    const modal = document.getElementById('diagramModal');
    modal.style.display = 'flex';
    const panel = document.getElementById('modalDiagramPanel');
    panel.innerHTML = '<div style="text-align:center; margin-top:100px; color:#666;"><i class="bi bi-arrow-repeat spin" style="font-size:2rem; display:inline-block; animation: spin 1s infinite linear;"></i><br>Ağaç üretiliyor...</div>';

    try {
        const resp = await fetch(`/api/database/components/${selectedProductId}/bom-path-names`, { headers: { Accept: 'application/json' } });
        const data = await resp.json();

        if (data.success && data.namePath) {
            cizDiyagram(panel, data.namePath, data.edges || []);
        } else {
            panel.innerHTML = '<div style="text-align:center; margin-top:100px; color:var(--z-danger);">BOM ağacı oluşturulamadı (BOM yolu düz metin olarak eksik).</div>';
        }
    } catch (e) {
        panel.innerHTML = '<div style="text-align:center; margin-top:100px; color:red;">Hata: ' + e.message + '</div>';
    }
}

function kapatDiyagramModal() {
    document.getElementById('diagramModal').style.display = 'none';
}

function openSelectedBomEditorFromDiagram() {
    if (!selectedProductId) return;

    kapatDiyagramModal();
    openBomEditor(selectedProductId);
}

function getDiagramNodeId(label) {
    const directId = diagNodeIds[String(label || '')];
    if (directId) return directId;

    const match = allUrunler.find((urun) => String(urun.name || '') === String(label || ''));
    return match ? Number(match.id) : null;
}

function openDiagramNodeEditor(label) {
    const componentId = getDiagramNodeId(label);
    if (!componentId) {
        window.alert('Bu düğüm ürün listesinde bulunamadı.');
        return;
    }

    const returnProductId = selectedProductId;
    kapatDiyagramModal();
    openBomEditor(componentId, returnProductId);
}

function cizDiyagram(panel, pattern, edges = []) {
    if (!pattern) return;
    panel.innerHTML = '';
    diagPoints = {};
    diagLines = [];
    diagNodeIds = {};
    edges.forEach((edge) => {
        if (edge.source_name && edge.source_id) {
            diagNodeIds[String(edge.source_name)] = Number(edge.source_id);
        }
        if (edge.target_name && edge.target_id) {
            diagNodeIds[String(edge.target_name)] = Number(edge.target_id);
        }
    });

    const segments = pattern.split(':');

    const nodes = {};
    const edgesList = [];

    segments.forEach(function (segment) {
        const pointsStr = segment.split('-');
        if (pointsStr.length >= 2) {
            const start = pointsStr[0];
            const end = pointsStr.length >= 3 ? pointsStr[1] : (pointsStr[0] + '_girdi');
            const adet = pointsStr.length >= 3 ? pointsStr[2] : (pointsStr.length === 2 ? pointsStr[1] : 1);

            if (pointsStr.length === 2) {
                if (!nodes[start]) nodes[start] = { in: [], out: [] };
                return;
            }

            if (!nodes[start]) nodes[start] = { in: [], out: [] };
            if (!nodes[end]) nodes[end] = { in: [], out: [] };

            nodes[start].out.push(end);
            nodes[end].in.push(start);
            edgesList.push({ start, end, adet });
        }
    });

    let roots = Object.keys(nodes).filter(n => nodes[n].out.length === 0);
    if(roots.length === 0) roots = [Object.keys(nodes)[0]];

    let levelQueue = roots.map(r => ({id: r, level: 0}));
    let visited = new Set();
    while (levelQueue.length > 0) {
        let curr = levelQueue.shift();
        if(visited.has(curr.id)) continue;
        visited.add(curr.id);
        nodes[curr.id].level = curr.level;

        nodes[curr.id].in.forEach(child => {
            levelQueue.push({id: child, level: curr.level + 1});
        });
    }

    let levelGroups = {};
    Object.keys(nodes).forEach(n => {
        let lvl = nodes[n].level !== undefined ? nodes[n].level : 0;
        if(!levelGroups[lvl]) levelGroups[lvl] = [];
        if(!levelGroups[lvl].includes(n)) levelGroups[lvl].push(n);
    });

    let maxLevel = Math.max(...Object.keys(levelGroups).map(Number));
    let ySpacing = 120;

    let panelWidth = panel.clientWidth || 900;
    let nodeOuterPadding = 80;
    let xSpacing = 160;
    let startX = nodeOuterPadding;

    let requiredWidth = startX * 2 + (maxLevel * xSpacing);
    if (requiredWidth > panelWidth) {
        xSpacing = (panelWidth - (nodeOuterPadding * 2)) / (maxLevel || 1);
        xSpacing = Math.max(xSpacing, 90);
    } else {
        startX = (panelWidth - (maxLevel * xSpacing)) / 2;
    }

    Object.keys(levelGroups).forEach(lvl => {
        let items = levelGroups[lvl];
        let startY = 100 + ((maxLevel + 1 - items.length) * ySpacing / 2);
        items.forEach((n, idx) => {
            let x = startX + (maxLevel - Number(lvl)) * xSpacing;
            let y = Math.max(80, startY + (idx * ySpacing));
            diagPoints[n] = { x: x, y: y, element: null };
            cizNode(panel, x, y, n);
        });
    });

    edgesList.forEach(edge => {
        cizLine(panel, edge.start, edge.end, edge.adet);
    });
}

function cizNode(panel, x, y, label) {
    if (diagPoints[label].element) return;

    const node = document.createElement('div');
    node.className = 'diagram-node';
    node.style.left = (x - nodeRadius) + 'px';
    node.style.top = (y - nodeRadius) + 'px';

    node.setAttribute('data-fullname', label);
    node.innerText = label.length > 6 ? label.substring(0,5) + '..' : label;
    node.draggable = true;

    const componentId = getDiagramNodeId(label);
    if (componentId) {
        node.classList.add('editable');
        node.dataset.componentId = String(componentId);
        node.title = 'Alt bağlantıları düzenlemek için çift tıkla';
        node.ondblclick = function (event) {
            event.preventDefault();
            event.stopPropagation();
            openDiagramNodeEditor(label);
        };
    }

    node.ondragstart = function (event) { event.dataTransfer.setData("text/plain", label); };

    node.ondrag = function (event) {
        const panelRect = panel.getBoundingClientRect();
        if (event.clientX > 0 && event.clientY > 0) {
            node.style.left = (event.clientX - panelRect.left - nodeRadius + panel.scrollLeft) + 'px';
            node.style.top = (event.clientY - panelRect.top - nodeRadius + panel.scrollTop) + 'px';
            diagPoints[label].x = event.clientX - panelRect.left + panel.scrollLeft;
            diagPoints[label].y = event.clientY - panelRect.top + panel.scrollTop;
            guncelleLines();
        }
    };

    panel.appendChild(node);
    diagPoints[label].element = node;
}

function cizLine(panel, start, end, adet) {
    const line = document.createElement('div');
    line.className = 'diagram-line';
    const endpoint = document.createElement('div');
    endpoint.className = 'diagram-endpoint';

    let qtyBox = null;
    if (adet) {
        qtyBox = document.createElement('div');
        qtyBox.className = 'diagram-qty';
        qtyBox.innerText = adet;
        panel.appendChild(qtyBox);
    }

    diagLines.push({ start: start, end: end, line: line, endpoint: endpoint, qtyBox: qtyBox });

    panel.appendChild(line);
    panel.appendChild(endpoint);
    guncelleLine(start, end, line, endpoint, qtyBox);
}

function guncelleLine(start, end, line, endpoint, qtyBox) {
    if (!diagPoints[start] || !diagPoints[end]) return;

    let startX = diagPoints[start].x;
    let startY = diagPoints[start].y;
    let endX = diagPoints[end].x;
    let endY = diagPoints[end].y;

    let angle = Math.atan2(endY - startY, endX - startX);
    let length = Math.sqrt((endX - startX) ** 2 + (endY - startY) ** 2);

    startX += nodeRadius * Math.cos(angle);
    startY += nodeRadius * Math.sin(angle);
    endX -= nodeRadius * Math.cos(angle);
    endY -= nodeRadius * Math.sin(angle);

    let angleDeg = angle * 180.0 / Math.PI;
    line.style.left = startX + 'px';
    line.style.top = startY + 'px';
    line.style.width = length + 'px';
    line.style.transform = 'rotate(' + angleDeg + 'deg)';

    endpoint.style.left = (endX - 5) + 'px';
    endpoint.style.top = (endY - 5) + 'px';

    if (qtyBox) {
        const midX = (startX + endX) / 2;
        const midY = (startY + endY) / 2;
        qtyBox.style.left = midX + 'px';
        qtyBox.style.top = midY + 'px';
    }
}

function guncelleLines() {
    diagLines.forEach(function (lineData) {
        guncelleLine(lineData.start, lineData.end, lineData.line, lineData.endpoint, lineData.qtyBox);
    });
}
</script>
@endpush
