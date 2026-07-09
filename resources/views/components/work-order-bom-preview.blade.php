@once
@php
    $workOrderBomPreviewEnabled = app(\App\Services\AppSettingService::class)
        ->bool('work_order_bom_preview_enabled', true);
@endphp

@push('styles')
<style>
    .wo-bom-popup {
        width: min(1280px, 96vw) !important;
        padding: 0 !important;
        max-height: calc(100vh - 32px);
        display: flex !important;
        flex-direction: column;
        overflow: hidden;
    }
    .wo-bom-popup .swal2-title {
        flex: 0 0 auto;
        padding: 20px 24px 8px;
    }
    .wo-bom-popup .swal2-actions {
        flex: 0 0 auto;
        margin: 12px auto 18px;
    }
    .wo-bom-popup .swal2-html-container {
        margin: 0;
        padding: 0 20px 18px;
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
    }
    .wo-bom-preview {
        text-align: left;
        color: var(--z-text);
        min-height: 0;
    }
    .wo-bom-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        margin-bottom: 12px;
    }
    .wo-bom-title {
        display: grid;
        gap: 3px;
        min-width: 0;
    }
    .wo-bom-title strong {
        font-size: 1rem;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .wo-bom-title span {
        color: var(--z-text-muted);
        font-size: 0.78rem;
        font-weight: 600;
    }
    .wo-bom-header-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 8px;
        align-items: center;
    }
    .wo-bom-export-actions {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .wo-bom-export-btn {
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        border-radius: 6px;
        min-height: 32px;
        padding: 6px 9px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
    }
    .wo-bom-export-btn:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }
    .wo-bom-export-btn:disabled {
        cursor: wait;
        opacity: 0.58;
    }
    .wo-bom-export-message {
        color: var(--z-text-muted);
        font-size: 0.68rem;
        font-weight: 700;
        min-height: 1em;
    }
    .wo-bom-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }
    .wo-bom-tab {
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        color: var(--z-text-secondary);
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
    }
    .wo-bom-tab.active {
        background: var(--z-accent);
        border-color: var(--z-accent);
        color: #fff;
    }
    .wo-bom-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 12px;
    }
    .wo-bom-metric {
        background: var(--z-bg-soft);
        border: 1px solid var(--z-border);
        border-radius: 8px;
        padding: 9px 10px;
        min-width: 0;
    }
    .wo-bom-metric span {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.66rem;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.04em;
        margin-bottom: 3px;
    }
    .wo-bom-metric strong {
        color: var(--z-text);
        font-size: 1.04rem;
        line-height: 1.1;
    }
    .wo-bom-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 380px;
        gap: 12px;
        align-items: stretch;
        height: clamp(420px, calc(100vh - 360px), 640px);
        min-height: 0;
    }
    .wo-bom-panel {
        background: #fffef7;
        border: 1px dashed #d4af37;
        border-radius: 8px;
        min-width: 0;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .wo-bom-panel-head {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid rgba(212, 175, 55, 0.28);
        background: rgba(255, 251, 235, 0.86);
    }
    .wo-bom-panel-head strong {
        font-size: 0.86rem;
    }
    .wo-bom-panel-head span {
        color: var(--z-text-muted);
        font-size: 0.72rem;
        font-weight: 700;
    }
    .wo-bom-panel-head .wo-bom-legend {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .wo-bom-legend-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        display: inline-block;
        margin-right: 3px;
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.9);
    }
    .wo-bom-legend-dot.stock { background: #16a34a; }
    .wo-bom-legend-dot.task { background: #dc2626; }
    .wo-bom-diagram {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        position: relative;
    }
    .wo-bom-scroll {
        height: 100%;
        min-height: 0;
        overflow: auto;
        cursor: grab;
        overscroll-behavior: contain;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        -webkit-overflow-scrolling: touch;
    }
    .wo-bom-scroll.dragging,
    .wo-bom-diagram.magnifier-on .wo-bom-scroll.dragging {
        cursor: grabbing;
        scroll-behavior: auto;
    }
    .wo-bom-scroll.dragging * {
        cursor: grabbing !important;
        user-select: none;
        -webkit-user-select: none;
    }
    .wo-bom-scroll * {
        -webkit-user-drag: none;
    }
    .wo-bom-stage {
        position: relative;
    }
    .wo-bom-canvas {
        position: relative;
        min-width: 760px;
        transform-origin: 0 0;
    }
    .wo-bom-zoom-controls {
        position: absolute;
        right: 12px;
        bottom: 12px;
        z-index: 30;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px;
        border: 1px solid rgba(148, 163, 184, 0.36);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 10px 26px rgba(15, 23, 42, 0.16);
        backdrop-filter: blur(8px);
    }
    .wo-bom-zoom-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--z-border);
        border-radius: 6px;
        background: var(--z-bg-card);
        color: var(--z-text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        font-weight: 800;
        line-height: 1;
        cursor: pointer;
    }
    .wo-bom-zoom-btn:hover {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
    }
    .wo-bom-zoom-btn.active {
        border-color: var(--z-accent);
        color: var(--z-accent);
        background: var(--z-accent-soft);
        box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.12);
    }
    .wo-bom-zoom-btn i {
        font-size: 0.92rem;
        line-height: 1;
    }
    .wo-bom-zoom-label {
        min-width: 48px;
        text-align: center;
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }
    .wo-bom-svg {
        position: absolute;
        inset: 0;
        pointer-events: none;
        overflow: visible;
    }
    .wo-bom-line {
        stroke: #ef4444;
        stroke-width: 3;
        stroke-linecap: round;
        fill: none;
    }
    .wo-bom-edge-dot {
        fill: #ef4444;
    }
    .wo-bom-edge-qty {
        position: absolute;
        transform: translate(-50%, -50%);
        min-width: 24px;
        height: 24px;
        border-radius: 6px;
        background: #fef3c7;
        border: 1px solid #d4af37;
        color: #7a5b00;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.74rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        z-index: 4;
    }
    .wo-bom-map-item {
        position: absolute;
        width: 148px;
        min-height: 142px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        z-index: 5;
        pointer-events: auto;
        user-select: none;
        -webkit-user-select: none;
    }
    .wo-bom-map-circle {
        width: 68px;
        height: 68px;
        border: 3px solid #3f3425;
        border-radius: 999px;
        background: #fde68a;
        color: #2b241c;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 6px;
        font-size: 0.8rem;
        font-weight: 900;
        line-height: 1.04;
        overflow: hidden;
        overflow-wrap: anywhere;
        box-shadow: 0 8px 18px rgba(63, 52, 37, 0.18);
    }
    .wo-bom-map-label {
        width: 100%;
        margin-top: 7px;
        display: grid;
        gap: 2px;
        color: #2f281f;
        font-size: 0.72rem;
        font-weight: 850;
        line-height: 1.1;
        min-height: 38px;
    }
    .wo-bom-map-label span {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .wo-bom-map-need {
        margin-top: 4px;
        color: var(--z-text-muted);
        font-size: 0.58rem;
        font-weight: 800;
        line-height: 1.1;
        white-space: nowrap;
    }
    .wo-bom-map-stock {
        margin-top: 4px;
        display: inline-flex;
        justify-content: center;
        gap: 3px;
        max-width: 142px;
    }
    .wo-bom-map-stock span {
        min-width: 40px;
        border-radius: 5px;
        background: #f1f5f9;
        color: var(--z-text);
        padding: 3px 4px;
        font-size: 0.54rem;
        font-weight: 850;
        line-height: 1.05;
        font-variant-numeric: tabular-nums;
    }
    .wo-bom-map-stock b {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.52rem;
        font-weight: 900;
        text-transform: uppercase;
    }
    .wo-bom-map-item.status-ready .wo-bom-map-circle {
        border-color: #10b981;
    }
    .wo-bom-map-item.status-partial .wo-bom-map-circle {
        border-color: #f59e0b;
    }
    .wo-bom-map-item.status-blocked .wo-bom-map-circle {
        border-color: #ef4444;
    }
    .wo-bom-map-item.status-covered .wo-bom-map-circle {
        border-color: #14b8a6;
        background: #ccfbf1;
    }
    .wo-bom-map-item.type-ham .wo-bom-map-circle {
        background: #fef3c7;
    }
    .wo-bom-map-item.type-nihai .wo-bom-map-circle {
        background: #fde68a;
    }
    .wo-bom-map-item.intent-stock .wo-bom-map-circle {
        border-color: #16a34a;
        background: #dcfce7;
        color: #14532d;
        box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.14), 0 8px 18px rgba(22, 101, 52, 0.18);
    }
    .wo-bom-map-item.intent-task .wo-bom-map-circle {
        border-color: #dc2626;
        background: #fee2e2;
        color: #7f1d1d;
        box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.14), 0 8px 18px rgba(127, 29, 29, 0.18);
    }
    .wo-bom-map-item.intent-neutral .wo-bom-map-circle {
        border-color: #94a3b8;
        background: #f8fafc;
        color: #334155;
    }
    .wo-bom-node {
        position: absolute;
        width: 176px;
        min-height: 92px;
        border: 2px solid #d4af37;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.12);
        z-index: 5;
        padding: 8px;
        display: grid;
        gap: 5px;
    }
    .wo-bom-node.status-ready { border-color: #10b981; }
    .wo-bom-node.status-partial { border-color: #f59e0b; }
    .wo-bom-node.status-blocked { border-color: #ef4444; }
    .wo-bom-node.status-covered { border-color: #14b8a6; background: #f0fdfa; }
    .wo-bom-node-name {
        font-size: 0.78rem;
        font-weight: 800;
        line-height: 1.15;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .wo-bom-node-sub {
        display: flex;
        justify-content: space-between;
        gap: 6px;
        align-items: center;
        color: var(--z-text-muted);
        font-size: 0.66rem;
        font-weight: 700;
    }
    .wo-bom-stock-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px;
    }
    .wo-bom-stock-grid span {
        background: var(--z-bg-soft);
        border-radius: 5px;
        padding: 4px;
        display: grid;
        gap: 1px;
        color: var(--z-text-muted);
        font-size: 0.58rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .wo-bom-stock-grid b {
        color: var(--z-text);
        font-size: 0.74rem;
    }
    .wo-bom-side {
        display: flex;
        flex-direction: column;
        gap: 10px;
        min-width: 0;
        min-height: 0;
        height: 100%;
        overflow: auto;
        padding-right: 4px;
    }
	    .wo-bom-explain {
	        background: #eff6ff;
	        border: 1px solid #bfdbfe;
	        color: #1e3a8a;
	        border-radius: 8px;
	        padding: 10px 12px;
	        font-size: 0.8rem;
	        line-height: 1.45;
            flex: 0 0 auto;
	    }
	    .wo-bom-covered {
	        border: 1px solid #f8d68a;
	        border-radius: 8px;
	        background: #fffbeb;
	        overflow: hidden;
            flex: 0 0 auto;
	    }
	    .wo-bom-covered-head {
	        padding: 9px 12px;
	        border-bottom: 1px solid rgba(217, 119, 6, 0.18);
	        display: flex;
	        justify-content: space-between;
	        gap: 8px;
	        align-items: center;
	    }
	    .wo-bom-covered-head strong {
	        color: #7c4a03;
	        font-size: 0.8rem;
	    }
	    .wo-bom-covered-head span {
	        color: #9a6712;
	        font-size: 0.68rem;
	        font-weight: 800;
	    }
	    .wo-bom-covered-list {
	        overflow: visible;
	    }
	    .wo-bom-covered-item {
	        padding: 8px 12px;
	        border-bottom: 1px solid rgba(217, 119, 6, 0.12);
	        display: grid;
	        gap: 2px;
	    }
	    .wo-bom-covered-item:last-child {
	        border-bottom: 0;
	    }
	    .wo-bom-covered-item strong {
	        color: #2f281f;
	        font-size: 0.72rem;
	        line-height: 1.25;
	        overflow-wrap: anywhere;
	    }
	    .wo-bom-covered-item span {
	        color: #8a5a12;
	        font-size: 0.64rem;
	        font-weight: 700;
	        line-height: 1.25;
	    }
	    .wo-bom-tasks {
	        border: 1px solid var(--z-border);
	        border-radius: 8px;
        overflow: hidden;
        background: var(--z-bg-card);
        min-height: 0;
        flex: 0 0 auto;
    }
    .wo-bom-tasks-head {
        padding: 10px 12px;
        border-bottom: 1px solid var(--z-border);
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }
    .wo-bom-tasks-head strong {
        font-size: 0.86rem;
    }
    .wo-bom-tasks-head span {
        color: var(--z-text-muted);
        font-size: 0.72rem;
        font-weight: 700;
    }
    .wo-bom-task-list {
        max-height: 360px;
        overflow: auto;
    }
    .wo-bom-magnifier {
        position: absolute;
        left: 16px;
        top: 16px;
        width: 270px;
        height: 190px;
        z-index: 35;
        display: none;
        overflow: hidden;
        border: 2px solid rgba(13, 148, 136, 0.82);
        border-radius: 10px;
        background: #fffef7;
        box-shadow: 0 16px 38px rgba(15, 23, 42, 0.24);
        pointer-events: none;
    }
    .wo-bom-magnifier.active {
        display: block;
    }
    .wo-bom-magnifier::after {
        content: '';
        position: absolute;
        inset: 50% auto auto 50%;
        width: 14px;
        height: 14px;
        border-left: 1px solid rgba(13, 148, 136, 0.45);
        border-top: 1px solid rgba(13, 148, 136, 0.45);
        transform: translate(-50%, -50%) rotate(45deg);
        pointer-events: none;
    }
    .wo-bom-magnifier-content {
        position: absolute;
        inset: 0;
        overflow: hidden;
    }
    .wo-bom-diagram.magnifier-on .wo-bom-scroll {
        cursor: zoom-in;
    }
    .wo-bom-diagram.panning .wo-bom-magnifier {
        display: none;
    }
    body.wo-bom-fullscreen-open {
        overflow: hidden;
    }
    .wo-bom-diagram.wo-bom-fullscreen {
        position: fixed !important;
        inset: 18px;
        z-index: 2147483000;
        background: #fffef7;
        border: 1px solid rgba(212, 175, 55, 0.72);
        border-radius: 12px;
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.38);
    }
    .wo-bom-diagram.wo-bom-fullscreen::before {
        content: 'BOM ağacı tam ekran';
        position: absolute;
        left: 18px;
        top: 14px;
        z-index: 31;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.92);
        border: 1px solid rgba(148, 163, 184, 0.36);
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        font-weight: 800;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
        pointer-events: none;
    }
    .wo-bom-diagram.wo-bom-fullscreen .wo-bom-scroll {
        height: 100%;
    }
    .wo-bom-diagram.wo-bom-fullscreen .wo-bom-zoom-controls {
        right: 18px;
        bottom: 18px;
    }
    .wo-bom-diagram.wo-bom-fullscreen .wo-bom-magnifier {
        width: 360px;
        height: 250px;
    }
    .wo-bom-task {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px;
        padding: 9px 12px;
        border-bottom: 1px solid var(--z-border-light);
    }
    .wo-bom-task:last-child {
        border-bottom: 0;
    }
    .wo-bom-task-main {
        min-width: 0;
        display: grid;
        gap: 3px;
    }
    .wo-bom-task-main strong {
        font-size: 0.78rem;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }
    .wo-bom-task-main span {
        color: var(--z-text-muted);
        font-size: 0.68rem;
        font-weight: 650;
    }
    .wo-bom-task-qty {
        text-align: right;
        display: grid;
        gap: 2px;
        align-content: center;
        white-space: nowrap;
        font-size: 0.72rem;
    }
    .wo-bom-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 2px 8px;
        font-size: 0.64rem;
        font-weight: 800;
    }
    .wo-bom-status.ready { background: #d1fae5; color: #047857; }
    .wo-bom-status.partial { background: #fef3c7; color: #b45309; }
    .wo-bom-status.blocked { background: #fee2e2; color: #b91c1c; }
    .wo-bom-errors {
        margin-top: 10px;
        color: #b45309;
        font-size: 0.78rem;
        font-weight: 650;
    }
    .wo-bom-export-host {
        position: fixed;
        left: -20000px;
        top: 0;
        width: 1360px;
        background: #fff;
        color: #1f2937;
        pointer-events: none;
        z-index: 0;
    }
    .wo-bom-export-mode {
        width: 1320px;
        padding: 22px;
        background: #fff;
        color: #1f2937;
    }
    .wo-bom-export-mode .wo-bom-layout {
        grid-template-columns: 1fr;
        height: auto;
        align-items: start;
        gap: 12px;
    }
    .wo-bom-export-mode .wo-bom-panel,
    .wo-bom-export-mode .wo-bom-diagram,
    .wo-bom-export-mode .wo-bom-scroll,
    .wo-bom-export-mode .wo-bom-task-list {
        overflow: visible;
        max-height: none;
    }
    .wo-bom-export-mode .wo-bom-panel {
        min-height: 0;
    }
    .wo-bom-export-mode .wo-bom-side {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 10px;
        height: auto;
        overflow: visible;
        padding-right: 0;
        align-items: start;
    }
    .wo-bom-export-mode .wo-bom-explain {
        grid-column: 1 / -1;
    }
    .wo-bom-export-mode .wo-bom-diagram,
    .wo-bom-export-mode .wo-bom-scroll {
        height: auto;
    }
    .wo-bom-export-mode .wo-bom-canvas {
        transform: none !important;
    }
    .wo-bom-export-mode .wo-bom-task-list {
        height: auto;
    }
    .wo-bom-export-mode.wo-bom-export-pdf {
        padding: 18px;
    }
    .wo-bom-export-mode.wo-bom-export-pdf .wo-bom-header,
    .wo-bom-export-mode.wo-bom-export-pdf .wo-bom-summary {
        margin-bottom: 9px;
    }
    .wo-bom-export-mode.wo-bom-export-pdf .wo-bom-panel-head {
        padding: 8px 10px;
    }
    @media (max-width: 980px) {
        .wo-bom-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .wo-bom-layout {
            grid-template-columns: 1fr;
            height: auto;
        }
        .wo-bom-panel {
            height: min(520px, 58vh);
        }
        .wo-bom-side {
            height: auto;
            max-height: none;
            overflow: visible;
            padding-right: 0;
        }
    }
    @media (max-width: 620px) {
        .wo-bom-popup .swal2-html-container { padding: 0 12px 14px; }
        .wo-bom-summary { grid-template-columns: 1fr; }
        .wo-bom-header { flex-direction: column; }
        .wo-bom-header-actions { justify-content: flex-start; }
        .wo-bom-panel {
            height: min(560px, 62vh);
        }
        .wo-bom-zoom-controls {
            left: 10px;
            right: 10px;
            bottom: 10px;
            justify-content: center;
        }
        .wo-bom-zoom-btn {
            width: 40px;
            height: 40px;
            font-size: 1.18rem;
        }
        .wo-bom-zoom-label {
            min-width: 54px;
        }
        .wo-bom-diagram.wo-bom-fullscreen {
            inset: 8px;
            border-radius: 10px;
        }
        .wo-bom-diagram.wo-bom-fullscreen::before {
            left: 10px;
            top: 10px;
        }
        .wo-bom-diagram.wo-bom-fullscreen .wo-bom-zoom-controls {
            left: 10px;
            right: 10px;
            bottom: 10px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
window.workOrderBomPreviewState = window.workOrderBomPreviewState || {
    enabled: @json($workOrderBomPreviewEnabled),
    endpoint: '/api/work-order-preview/preview'
};

(function () {
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function esc(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function fmt(value) {
        return Number(value || 0).toLocaleString('tr-TR');
    }

    const bomEdgeColors = [
        '#ef4444',
        '#0ea5e9',
        '#22c55e',
        '#f59e0b',
        '#8b5cf6',
        '#ec4899',
        '#14b8a6',
        '#f97316',
        '#6366f1',
        '#84cc16'
    ];
    const exportLibraryPromises = {};
    const exportLibraries = {
        html2canvas: {
            url: 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js',
            ready: function () { return typeof window.html2canvas === 'function'; }
        },
        jspdf: {
            url: 'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
            ready: function () { return !!(window.jspdf && window.jspdf.jsPDF); }
        }
    };

    function bomEdgeColor(index) {
        return bomEdgeColors[index % bomEdgeColors.length];
    }

    function splitBomNodeName(name) {
        const clean = String(name || '-').replace(/\s+/g, ' ').trim();
        const words = clean ? clean.split(' ') : ['-'];
        const badge = words.shift() || '-';
        const details = words.slice(0, 3);
        if (words.length > 3 && details.length) {
            details[details.length - 1] = compactBomWord(details[details.length - 1], 15) + '...';
        }

        return {
            badge: compactBomWord(badge, 13),
            details: details.map(function (word) {
                return compactBomWord(word, 18);
            })
        };
    }

    function compactBomWord(value, maxLength) {
        const text = String(value || '-');
        if (text.length <= maxLength) return text;
        return text.slice(0, Math.max(1, maxLength - 3)) + '...';
    }

    function fallbackHtml(options) {
        return options.html || esc(options.text || 'İş emri oluşturulsun mu?');
    }

    async function fallbackConfirm(options) {
        if (!window.Swal) {
            return window.confirm(options.text || 'İş emri oluşturulsun mu?');
        }

        const result = await Swal.fire({
            icon: options.icon || 'question',
            title: options.title || 'İş Emri Ver',
            html: fallbackHtml(options),
            showCancelButton: true,
            confirmButtonText: options.confirmButtonText || 'Onayla',
            cancelButtonText: options.cancelButtonText || 'Vazgeç',
            confirmButtonColor: options.confirmButtonColor || '#0d9488',
            cancelButtonColor: options.cancelButtonColor || '#80694d'
        });

        return result.isConfirmed;
    }

    async function fetchPreview(payload) {
        const response = await fetch(window.workOrderBomPreviewState.endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'BOM önizlemesi yüklenemedi.');
        }

        return data;
    }

    window.setWorkOrderBomPreviewEnabled = function (enabled) {
        window.workOrderBomPreviewState.enabled = !!enabled;
    };

    window.workOrderBomPreviewConfirm = async function (payload, options) {
        options = options || {};

        if (!window.workOrderBomPreviewState.enabled) {
            return fallbackConfirm(options);
        }

        if (!window.Swal) {
            return fallbackConfirm(options);
        }

        try {
            const data = await fetchPreview(payload);
            if (data.enabled === false) {
                window.setWorkOrderBomPreviewEnabled(false);
                return fallbackConfirm(options);
            }

	            return showPreview(data, options);
	        } catch (error) {
	            return fallbackConfirm(Object.assign({}, options, {
                title: options.fallbackErrorTitle || 'Önizleme Alınamadı',
                html: '<div style="text-align:left;">' +
                    '<p>' + esc(error.message || 'BOM önizlemesi yüklenemedi.') + '</p>' +
                    '<p class="text-muted" style="margin:0;">İsterseniz klasik onay ile devam edebilirsiniz.</p>' +
                    '</div>'
            }));
        }
    };

	    async function showPreview(data, options) {
	        const containerId = 'woBomPreview_' + Date.now();
	        const result = await Swal.fire({
            title: options.previewTitle || 'İş Emri BOM Önizlemesi',
            html: '<div id="' + containerId + '" class="wo-bom-preview"></div>',
            icon: null,
            customClass: { popup: 'wo-bom-popup' },
            width: 'min(1280px, 96vw)',
            showCancelButton: true,
            confirmButtonText: options.previewConfirmText || options.confirmButtonText || 'Onayla ve İş Emri Ver',
            cancelButtonText: options.cancelButtonText || 'Vazgeç',
            confirmButtonColor: options.confirmButtonColor || '#0d9488',
            cancelButtonColor: options.cancelButtonColor || '#80694d',
	            didOpen: function () {
	                const container = document.getElementById(containerId);
	                if (container) renderPreview(container, data, 0);
	            },
                willClose: function () {
                    closeBomFullscreen();
                }
	        });

        return result.isConfirmed;
    }

	    function renderPreview(container, data, activeIndex, renderOptions) {
            renderOptions = renderOptions || {};
        const exportMode = !!renderOptions.exportMode;
        const exportFormat = renderOptions.exportFormat || '';
            if (!exportMode) closeBomFullscreen();
	        const groups = Array.isArray(data.groups) ? data.groups : [];
	        const group = groups[activeIndex] || groups[0];

        if (!group) {
            container.innerHTML = '<div class="alert alert-warning mb-0">BOM önizlemesi için gösterilecek ürün bulunamadı.</div>';
            return;
        }

        const summary = group.summary || {};
        const target = group.target || {};
	        const stockMode = group.stockMode || data.stockMode || 'StokDahil';
	        const stockModeLabel = stockMode === 'StokHaric' ? 'Stok Hariç' : 'Stok Dahil';
	        const stockModeText = stockMode === 'StokHaric'
	            ? 'Stok/tampon düşülmez; BOM ihtiyacı kadar üretim havuzuna net görev açılır.'
	            : 'Her BOM satırı kendi boş stokuyla değerlendirilir; üst görevler alt görevler tamamlanmadan personele açılmaz.';
	        const coveredNodes = stockMode === 'StokDahil' ? coveredNodesForGroup(group) : [];
	        const coveredHint = coveredNodes.length
	            ? '<span style="display:block;margin-top:6px;">' + fmt(coveredNodes.length) + ' BOM satırı kendi boş stoğundan karşılanacak. Alt parçalar yine kendi depo/boşta durumuna göre ayrıca değerlendirilir.</span>'
	            : '';
	        const departments = Array.isArray(summary.departments) && summary.departments.length
	            ? summary.departments.join(', ')
            : 'Görev atama sayfasında bölüm bilgisiyle görünecek';
        const ordersText = Array.isArray(group.orders) && group.orders.length
            ? group.orders.length + ' sipariş satırı'
            : 'Manuel iş emri';
        const surplusText = Number(group.surplus || 0) > 0 ? ' + ' + fmt(group.surplus) + ' özel stok' : '';
        const errors = Array.isArray(data.errors) ? data.errors : [];

        container.innerHTML =
            (exportMode ? '' : renderTabs(groups, activeIndex)) +
            '<div class="wo-bom-header">' +
	                '<div class="wo-bom-title">' +
	                    '<strong>' + esc(target.name || target.rootName || 'Ürün') + '</strong>' +
	                    '<span>' + esc(target.type || 'Ürün') + ' · ' + fmt(group.quantity) + ' adet · ' + esc(ordersText) + esc(surplusText) + ' · ' + esc(stockModeLabel) + '</span>' +
	                '</div>' +
                    '<div class="wo-bom-header-actions">' +
	                    '<span class="wo-bom-status ' + (summary.blockedQuantity > 0 ? 'partial' : 'ready') + '">' + (summary.blockedQuantity > 0 ? 'Kısmi hazır' : 'Hazır akış') + '</span>' +
                        (exportMode ? '' : renderExportActions()) +
                    '</div>' +
	            '</div>' +
	            '<div class="wo-bom-summary">' +
	                metricHtml('Havuz Kaydı', fmt(summary.taskCount), 'bi-list-task') +
	                metricHtml('Üretilecek Net', fmt(summary.totalTaskQuantity), 'bi-inboxes') +
	                metricHtml('Atanabilir', fmt(summary.assignableQuantity), 'bi-person-check') +
		                metricHtml('Boş Stoktan Düşen', fmt(summary.stockReserved), 'bi-box-arrow-up') +
	            '</div>' +
            '<div class="wo-bom-layout">' +
                '<section class="wo-bom-panel">' +
                    '<div class="wo-bom-panel-head"><strong><i class="bi bi-diagram-3 me-1"></i>BOM Ağacı ve Stok</strong><span class="wo-bom-legend"><span><i class="wo-bom-legend-dot stock"></i>Stoktan</span><span><i class="wo-bom-legend-dot task"></i>Görev açılacak</span><span>D · A · B</span></span></div>' +
                    '<div class="wo-bom-diagram" data-wo-bom-diagram></div>' +
                '</section>' +
	                    '<aside class="wo-bom-side">' +
		                    '<div class="wo-bom-explain">' +
		                        '<strong>' + esc(stockModeLabel) + ':</strong> ' + esc(stockModeText) + '<br>' +
		                        '<span style="display:block;margin-top:6px;"><strong>Bölümler:</strong> ' + esc(departments) + '</span>' +
		                        coveredHint +
		                    '</div>' +
	                    renderTasks(group.tasks || []) +
	                    renderCoveredNodes(group, stockMode) +
	                '</aside>' +
            '</div>' +
            (errors.length ? '<div class="wo-bom-errors">' + errors.map(esc).join('<br>') + '</div>' : '');

            if (!exportMode) {
	            container.querySelectorAll('[data-preview-tab]').forEach(function (button) {
	                button.addEventListener('click', function () {
	                    renderPreview(container, data, Number(button.dataset.previewTab || 0));
	                });
	            });

                container.querySelectorAll('[data-wo-bom-export]').forEach(function (button) {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        exportPreview(container, data, activeIndex, button.dataset.woBomExport || 'pdf');
                    });
                });
            }

        drawDiagram(container.querySelector('[data-wo-bom-diagram]'), group, { exportMode: exportMode, exportFormat: exportFormat });
    }

    function renderTabs(groups, activeIndex) {
        if (!Array.isArray(groups) || groups.length <= 1) return '';
        return '<div class="wo-bom-tabs">' + groups.map(function (group, index) {
            const target = group.target || {};
            return '<button type="button" class="wo-bom-tab ' + (index === activeIndex ? 'active' : '') + '" data-preview-tab="' + index + '">' +
                esc(target.name || target.rootName || ('Ürün ' + (index + 1))) +
                '</button>';
        }).join('') + '</div>';
    }

    function renderExportActions() {
        return '<div class="wo-bom-export-actions" aria-label="BOM önizlemesini dışa aktar">' +
            '<button type="button" class="wo-bom-export-btn" data-wo-bom-export="pdf" title="PDF olarak indir" aria-label="BOM önizlemesini PDF olarak indir"><i class="bi bi-filetype-pdf"></i><span>PDF</span></button>' +
            '<button type="button" class="wo-bom-export-btn" data-wo-bom-export="jpg" title="JPG olarak indir" aria-label="BOM önizlemesini JPG olarak indir"><i class="bi bi-filetype-jpg"></i><span>JPG</span></button>' +
            '<span class="wo-bom-export-message" data-wo-export-message aria-live="polite"></span>' +
        '</div>';
    }

    function metricHtml(label, value) {
        return '<div class="wo-bom-metric"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></div>';
    }

    async function exportPreview(container, data, activeIndex, format) {
        const targetFormat = format === 'jpg' ? 'jpg' : 'pdf';
        setExportBusy(container, true, targetFormat);

        try {
            await ensureExportLibraries(targetFormat);
            const canvas = await createExportCanvas(data, activeIndex, targetFormat);
            const group = (Array.isArray(data.groups) ? data.groups : [])[activeIndex] || {};
            const filename = buildExportFileName(group, targetFormat);

            if (targetFormat === 'jpg') {
                downloadCanvasAsJpg(canvas, filename);
            } else {
                downloadCanvasAsPdf(canvas, filename);
            }

            setExportMessage(container, targetFormat.toUpperCase() + ' indirildi.', 'success');
        } catch (error) {
            setExportMessage(container, error.message || 'Dışa aktarım tamamlanamadı.', 'error');
        } finally {
            setExportBusy(container, false, targetFormat);
        }
    }

    function setExportBusy(container, busy, format) {
        container.querySelectorAll('[data-wo-bom-export]').forEach(function (button) {
            button.disabled = busy;
            button.setAttribute('aria-busy', busy ? 'true' : 'false');
        });
        if (busy) {
            setExportMessage(container, format.toUpperCase() + ' hazırlanıyor...', 'muted');
        }
    }

    function setExportMessage(container, message, tone) {
        const messageEl = container.querySelector('[data-wo-export-message]');
        if (!messageEl) return;

        messageEl.textContent = message || '';
        messageEl.style.color = tone === 'error'
            ? '#b91c1c'
            : tone === 'success'
                ? '#047857'
                : '';
    }

    async function ensureExportLibraries(format) {
        await loadExportLibrary('html2canvas');
        if (format === 'pdf') {
            await loadExportLibrary('jspdf');
        }
    }

    function loadExportLibrary(name) {
        const config = exportLibraries[name];
        if (!config) return Promise.reject(new Error('Dışa aktarım kütüphanesi tanımlı değil.'));
        if (config.ready()) return Promise.resolve();
        if (exportLibraryPromises[name]) return exportLibraryPromises[name];

        exportLibraryPromises[name] = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = config.url;
            script.async = true;
            script.dataset.woExportLibrary = name;
            script.onload = function () {
                if (config.ready()) {
                    resolve();
                } else {
                    reject(new Error('Dışa aktarım kütüphanesi yüklenemedi.'));
                }
            };
            script.onerror = function () {
                reject(new Error('Dışa aktarım kütüphanesi yüklenemedi.'));
            };
            document.head.appendChild(script);
        }).catch(function (error) {
            delete exportLibraryPromises[name];
            throw error;
        });

        return exportLibraryPromises[name];
    }

    async function createExportCanvas(data, activeIndex, format) {
        const host = document.createElement('div');
        host.className = 'wo-bom-export-host';

        const exportContainer = document.createElement('div');
        exportContainer.className = 'wo-bom-preview wo-bom-export-mode wo-bom-export-' + (format === 'pdf' ? 'pdf' : 'jpg');
        host.appendChild(exportContainer);
        document.body.appendChild(host);

        try {
            renderPreview(exportContainer, data, activeIndex, { exportMode: true, exportFormat: format });
            await waitForExportLayout();

            const rect = exportContainer.getBoundingClientRect();
            const maxCanvasSide = 28000;
            const desiredScale = Math.min(2, Math.max(1, window.devicePixelRatio || 1));
            const exportScale = Math.max(0.6, Math.min(desiredScale, maxCanvasSide / Math.max(rect.width, rect.height, 1)));
            return window.html2canvas(exportContainer, {
                backgroundColor: '#ffffff',
                scale: exportScale,
                useCORS: true,
                logging: false,
                width: Math.ceil(rect.width),
                height: Math.ceil(rect.height),
                windowWidth: Math.ceil(rect.width),
                windowHeight: Math.ceil(rect.height),
                scrollX: 0,
                scrollY: 0
            });
        } finally {
            host.remove();
        }
    }

    async function waitForExportLayout() {
        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready.catch(function () {});
        }

        await new Promise(function (resolve) {
            requestAnimationFrame(function () {
                requestAnimationFrame(resolve);
            });
        });
    }

    function downloadCanvasAsJpg(canvas, filename) {
        const link = document.createElement('a');
        link.download = filename;
        link.href = canvas.toDataURL('image/jpeg', 0.95);
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    function downloadCanvasAsPdf(canvas, filename) {
        const jsPDF = window.jspdf.jsPDF;
        const pdf = new jsPDF({
            orientation: canvas.width > canvas.height && (canvas.width / Math.max(canvas.height, 1)) > 1.18 ? 'landscape' : 'portrait',
            unit: 'mm',
            format: 'a4'
        });
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 8;
        const usableWidth = pageWidth - (margin * 2);
        const usableHeight = pageHeight - (margin * 2);
        const imageHeight = (canvas.height * usableWidth) / canvas.width;

        if (imageHeight <= usableHeight * 1.35) {
            const fitRatio = Math.min(usableWidth / canvas.width, usableHeight / canvas.height);
            const renderWidth = canvas.width * fitRatio;
            const renderHeight = canvas.height * fitRatio;
            const x = (pageWidth - renderWidth) / 2;
            pdf.addImage(canvas.toDataURL('image/jpeg', 0.95), 'JPEG', x, margin, renderWidth, renderHeight, undefined, 'FAST');
            pdf.save(filename);
            return;
        }

        const sourcePageHeight = Math.max(1, Math.floor((usableHeight * canvas.width) / usableWidth));
        let sourceY = 0;
        let pageIndex = 0;

        while (sourceY < canvas.height - 1) {
            const sliceBottom = findPdfSliceBottom(canvas, sourceY, sourcePageHeight);
            const sliceHeight = Math.max(1, sliceBottom - sourceY);

            if (pageIndex > 0) {
                pdf.addPage();
            }

            addCanvasSliceToPdf(pdf, canvas, sourceY, sliceHeight, margin, usableWidth);
            sourceY = sliceBottom;
            pageIndex += 1;
        }

        pdf.save(filename);
    }

    function addCanvasSliceToPdf(pdf, canvas, sourceY, sliceHeight, margin, usableWidth) {
        const sliceCanvas = document.createElement('canvas');
        sliceCanvas.width = canvas.width;
        sliceCanvas.height = sliceHeight;

        const context = sliceCanvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
        context.drawImage(canvas, 0, sourceY, canvas.width, sliceHeight, 0, 0, canvas.width, sliceHeight);

        const sliceHeightMm = (sliceHeight * usableWidth) / canvas.width;
        pdf.addImage(sliceCanvas.toDataURL('image/jpeg', 0.95), 'JPEG', margin, margin, usableWidth, sliceHeightMm, undefined, 'FAST');
    }

    function findPdfSliceBottom(canvas, startY, maxSliceHeight) {
        const maxBottom = Math.min(canvas.height, Math.floor(startY + maxSliceHeight));
        if (maxBottom >= canvas.height) return canvas.height;

        const minBottom = Math.min(maxBottom - 1, Math.floor(startY + (maxSliceHeight * 0.58)));
        const searchStart = Math.max(minBottom, maxBottom - Math.min(360, Math.floor(maxSliceHeight * 0.34)));
        const searchHeight = Math.max(1, maxBottom - searchStart);

        try {
            const context = canvas.getContext('2d', { willReadFrequently: true });
            const data = context.getImageData(0, searchStart, canvas.width, searchHeight).data;
            const sampleStep = Math.max(6, Math.floor(canvas.width / 340));
            const sampleCount = Math.ceil(canvas.width / sampleStep);
            let bestOffset = searchHeight - 1;
            let bestScore = Number.POSITIVE_INFINITY;

            for (let row = 0; row < searchHeight; row += 2) {
                let score = 0;
                const rowStart = row * canvas.width * 4;

                for (let x = 0; x < canvas.width; x += sampleStep) {
                    const offset = rowStart + (x * 4);
                    const alpha = data[offset + 3];
                    const red = data[offset];
                    const green = data[offset + 1];
                    const blue = data[offset + 2];

                    if (alpha > 18 && (red < 245 || green < 245 || blue < 245)) {
                        score += 1;
                    }
                }

                if (score < bestScore) {
                    bestScore = score;
                    bestOffset = row;
                }
            }

            if (bestScore <= Math.max(4, sampleCount * 0.035)) {
                return Math.max(minBottom, searchStart + bestOffset);
            }
        } catch (error) {
            // If the canvas cannot be inspected, keep the standard page height.
        }

        return maxBottom;
    }

    function buildExportFileName(group, extension) {
        const target = (group && group.target) || {};
        const title = target.name || target.rootName || 'bom-onizleme';
        const date = new Date().toISOString().slice(0, 10);
        return slugifyFileName(title + '-' + date) + '.' + extension;
    }

    function slugifyFileName(value) {
        return String(value || 'bom-onizleme')
            .toLocaleLowerCase('tr-TR')
            .replaceAll('ı', 'i')
            .replaceAll('ğ', 'g')
            .replaceAll('ü', 'u')
            .replaceAll('ş', 's')
            .replaceAll('ö', 'o')
            .replaceAll('ç', 'c')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 90) || 'bom-onizleme';
    }

	    function renderTasks(tasks) {
        if (!Array.isArray(tasks) || tasks.length === 0) {
            return '<div class="wo-bom-tasks"><div class="wo-bom-tasks-head"><strong>Havuzda Açılacak Kayıtlar</strong><span>0 kayıt</span></div><div class="p-3 text-muted small">Bu ürün için havuz görevi hesaplanmadı.</div></div>';
        }

        return '<div class="wo-bom-tasks">' +
            '<div class="wo-bom-tasks-head"><strong>Havuzda Açılacak Kayıtlar</strong><span>' + tasks.length + ' kayıt</span></div>' +
            '<div class="wo-bom-task-list">' +
	                tasks.map(function (task) {
	                    const statusKey = task.statusKey || 'ready';
                        const requested = Number(task.requestedQuantity || task.totalQuantity || 0);
                        const reserved = Number(task.reservedFromStock || 0);
                        const total = Number(task.totalQuantity || 0);
	                    return '<div class="wo-bom-task">' +
	                        '<div class="wo-bom-task-main">' +
	                            '<strong>' + esc(task.componentName || '-') + '</strong>' +
		                            '<span>' + esc(task.departmentName || 'Bölüm yok') + ' · BOM ihtiyaç: ' + fmt(requested) + ' · Boş stoktan düşen: ' + fmt(reserved) + ' · Net üretim: ' + fmt(total) + '</span>' +
	                        '</div>' +
	                        '<div class="wo-bom-task-qty">' +
	                            '<span class="wo-bom-status ' + esc(statusKey) + '">' + esc(task.status || '-') + '</span>' +
                            '<strong>' + fmt(task.assignableQuantity) + ' / ' + fmt(task.totalQuantity) + '</strong>' +
                        '</div>' +
                    '</div>';
                }).join('') +
            '</div>' +
	        '</div>';
	    }

	    function coveredNodesForGroup(group) {
	        const tree = group.tree || {};
	        const nodes = Array.isArray(tree.nodes) ? tree.nodes : [];
	        return nodes.filter(function (node) {
	            const bomRequired = Number(node.bomRequired ?? node.required ?? 0);
	            const netRequired = Number(node.netRequired ?? 0);
	            return bomRequired > 0 && netRequired === 0;
	        });
	    }

	    function renderCoveredNodes(group, stockMode) {
	        if (stockMode !== 'StokDahil') return '';
	        const covered = coveredNodesForGroup(group);
	        if (!covered.length) return '';

	        const maps = buildCoverageMaps(group);
	        const shown = covered.slice(0, 10);
	        const hidden = covered.length - shown.length;

	        return '<div class="wo-bom-covered">' +
		            '<div class="wo-bom-covered-head"><strong>Kendi Boş Stoğundan Karşılanan</strong><span>' + fmt(covered.length) + ' kayıt</span></div>' +
	            '<div class="wo-bom-covered-list">' +
	                shown.map(function (node) {
	                    const bomRequired = Number(node.bomRequired ?? node.required ?? 0);
	                    const reserved = Number(node.stockReserved || 0);
	                    const sourceText = coverageSourceText(node, maps);
	                    return '<div class="wo-bom-covered-item">' +
	                        '<strong>' + esc(node.name || '-') + '</strong>' +
		                        '<span>BOM: ' + fmt(bomRequired) + ' · Boş stoktan düşen: ' + fmt(reserved) + ' · ' + esc(sourceText) + '</span>' +
	                    '</div>';
	                }).join('') +
		                (hidden > 0 ? '<div class="wo-bom-covered-item"><span>+' + fmt(hidden) + ' kayıt daha kendi stoğundan karşılanacak.</span></div>' : '') +
	            '</div>' +
	        '</div>';
	    }

	    function buildCoverageMaps(group) {
	        const tree = group.tree || {};
	        const nodes = Array.isArray(tree.nodes) ? tree.nodes : [];
	        const edges = Array.isArray(tree.edges) ? tree.edges : [];
	        const nodeById = {};
	        const parentsByChild = {};

	        nodes.forEach(function (node) {
	            nodeById[String(node.id)] = node;
	        });

	        edges.forEach(function (edge) {
	            const childId = String(edge.from);
	            const parentId = String(edge.to);
	            if (!nodeById[childId] || !nodeById[parentId]) return;
	            if (!parentsByChild[childId]) parentsByChild[childId] = [];
	            parentsByChild[childId].push(parentId);
	        });

	        return { nodeById: nodeById, parentsByChild: parentsByChild };
	    }

	    function coverageSourceText(node, maps) {
	        const ownReserved = Number(node.stockReserved || 0);
	        if (ownReserved > 0) {
	                return fmt(ownReserved) + ' adet boş stoktan düşecek';
	        }

	        const queue = (maps.parentsByChild[String(node.id)] || []).slice();
	        const seen = {};
	        while (queue.length) {
	            const parentId = queue.shift();
	            if (seen[parentId]) continue;
	            seen[parentId] = true;
	            const parent = maps.nodeById[parentId];
	            if (!parent) continue;

	            const parentReserved = Number(parent.stockReserved || 0);
	            if (parentReserved > 0) {
	                return compactBomWord(parent.name || 'Üst parça', 42) + ' boş stoktan düştüğü için';
	            }

	            (maps.parentsByChild[parentId] || []).forEach(function (nextParentId) {
	                if (!seen[nextParentId]) queue.push(nextParentId);
	            });
	        }

	        return (node.status && node.status.label) || 'Kendi stoğundan karşılandı';
	    }

	    function drawDiagram(shell, group, options) {
        if (!shell) return;
        options = options || {};
        const exportMode = !!options.exportMode;
        const pdfExportMode = exportMode && options.exportFormat === 'pdf';

        const tree = group.tree || {};
        const nodes = Array.isArray(tree.nodes) ? tree.nodes : [];
        const edges = Array.isArray(tree.edges) ? tree.edges : [];

        if (!nodes.length) {
            shell.innerHTML = '<div class="p-4 text-muted">BOM ağacı bulunamadı.</div>';
            return;
        }

        const nodeR = 34;
        const itemW = 148;
        const itemH = 154;
        const visibleWidth = Math.max(shell.clientWidth || (exportMode ? 860 : 880), 760);
        const maxLevel = Math.max.apply(null, nodes.map(function (node) { return Number(node.level || 0); }));
        const nodeById = {};
        const childrenByParent = {};
        const parentsByChild = {};

        nodes.forEach(function (node) {
            nodeById[String(node.id)] = node;
        });

        edges.forEach(function (edge) {
            const childId = String(edge.from);
            const parentId = String(edge.to);
            if (!nodeById[childId] || !nodeById[parentId]) return;
            if (!childrenByParent[parentId]) childrenByParent[parentId] = [];
            if (!parentsByChild[childId]) parentsByChild[childId] = [];
            if (!childrenByParent[parentId].includes(childId)) childrenByParent[parentId].push(childId);
            if (!parentsByChild[childId].includes(parentId)) parentsByChild[childId].push(parentId);
        });

        Object.keys(childrenByParent).forEach(function (parentId) {
            childrenByParent[parentId].sort(function (a, b) {
                return String((nodeById[a] || {}).name || '').localeCompare(String((nodeById[b] || {}).name || ''), 'tr');
            });
        });

        const levelCount = Math.max(1, maxLevel);
        const gapX = Math.max(280, Math.min(380, (visibleWidth - 180) / levelCount));
        const gapY = pdfExportMode ? 178 : 218;
        const leftPad = 98;
        const rightPad = 150;
        const topPad = pdfExportMode ? 74 : 96;
        const chartWidth = Math.max(visibleWidth, leftPad + (maxLevel * gapX) + itemW + rightPad);
        const positions = {};
        let nextY = topPad + nodeR;

        let roots = nodes
            .filter(function (node) { return Number(node.level || 0) === 0; })
            .map(function (node) { return String(node.id); });

        if (!roots.length) {
            roots = nodes
                .filter(function (node) { return !parentsByChild[String(node.id)]; })
                .map(function (node) { return String(node.id); });
        }

        roots.sort(function (a, b) {
            return String((nodeById[a] || {}).name || '').localeCompare(String((nodeById[b] || {}).name || ''), 'tr');
        });

        function placeNode(nodeId, y) {
            const node = nodeById[nodeId];
            const level = Number((node || {}).level || 0);
            const x = leftPad + ((maxLevel - level) * gapX);
            positions[nodeId] = {
                cx: x,
                cy: y,
                x: x - (itemW / 2),
                y: y - nodeR
            };

            return y;
        }

        function layoutNode(nodeId, path) {
            if (positions[nodeId]) return positions[nodeId].cy;
            if (!nodeById[nodeId]) return nextY;
            if (path.includes(nodeId)) {
                const cycleY = nextY;
                nextY += gapY;
                return placeNode(nodeId, cycleY);
            }

            const children = (childrenByParent[nodeId] || []).filter(function (childId) {
                return nodeById[childId];
            });

            if (!children.length) {
                const leafY = nextY;
                nextY += gapY;
                return placeNode(nodeId, leafY);
            }

            const childYs = children.map(function (childId) {
                return layoutNode(childId, path.concat(nodeId));
            });
            const parentY = childYs.reduce(function (total, value) { return total + value; }, 0) / childYs.length;
            return placeNode(nodeId, parentY);
        }

        roots.forEach(function (rootId) {
            layoutNode(rootId, []);
        });

        nodes.forEach(function (node) {
            layoutNode(String(node.id), []);
        });

        const maxY = Math.max.apply(null, Object.values(positions).map(function (position) {
            return position.cy;
        }));
        const chartHeight = Math.max(420, maxY + itemH + (pdfExportMode ? 44 : 72));

        shell.innerHTML =
            '<div class="wo-bom-scroll" data-wo-bom-scroll>' +
                '<div class="wo-bom-stage" data-wo-bom-stage>' +
                    '<div class="wo-bom-canvas" data-wo-bom-canvas style="width:' + chartWidth + 'px;height:' + chartHeight + 'px;">' +
                        '<svg class="wo-bom-svg" width="' + chartWidth + '" height="' + chartHeight + '" viewBox="0 0 ' + chartWidth + ' ' + chartHeight + '"></svg>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            (exportMode ? '' :
                '<div class="wo-bom-magnifier" data-wo-magnifier aria-hidden="true"><div class="wo-bom-magnifier-content" data-wo-magnifier-content></div></div>' +
                '<div class="wo-bom-zoom-controls" aria-label="BOM ağacı yakınlaştırma kontrolleri">' +
                    '<button type="button" class="wo-bom-zoom-btn" data-wo-zoom-out title="Uzaklaş" aria-label="BOM ağacından uzaklaş">-</button>' +
                    '<span class="wo-bom-zoom-label" data-wo-zoom-label>100%</span>' +
                    '<button type="button" class="wo-bom-zoom-btn" data-wo-zoom-in title="Yakınlaş" aria-label="BOM ağacına yakınlaş">+</button>' +
                    '<button type="button" class="wo-bom-zoom-btn" data-wo-zoom-center title="Ortala" aria-label="BOM ağacını ortala"><i class="bi bi-bullseye"></i></button>' +
                    '<button type="button" class="wo-bom-zoom-btn" data-wo-magnifier-toggle title="Mercek" aria-label="BOM ağacı merceğini aç/kapat" aria-pressed="false"><i class="bi bi-search"></i></button>' +
                    '<button type="button" class="wo-bom-zoom-btn" data-wo-fullscreen-toggle title="Tam ekran" aria-label="BOM ağacını tam ekran incele" aria-pressed="false"><i class="bi bi-fullscreen"></i></button>' +
                '</div>');

        const canvas = shell.querySelector('.wo-bom-canvas');
        const svg = shell.querySelector('svg');

        edges.forEach(function (edge) {
            const from = positions[String(edge.from)];
            const to = positions[String(edge.to)];
            if (!from || !to) return;
            const edgeColor = bomNodeIntentColor(nodeById[String(edge.from)]);

            const direction = to.cx >= from.cx ? 1 : -1;
            const x1 = from.cx + (nodeR * direction);
            const y1 = from.cy;
            const x2 = to.cx - (nodeR * direction);
            const y2 = to.cy;
            const midX = x1 + ((x2 - x1) * 0.5);
            const qtyX = midX;
            const qtyY = y1 + ((y2 - y1) * 0.5);

            const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            line.setAttribute('class', 'wo-bom-line');
            line.setAttribute('d', 'M ' + x1 + ' ' + y1 + ' C ' + midX + ' ' + y1 + ', ' + midX + ' ' + y2 + ', ' + x2 + ' ' + y2);
            line.style.stroke = edgeColor;
            svg.appendChild(line);

            [x1, x2].forEach(function (dotX, dotIndex) {
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('class', 'wo-bom-edge-dot');
                dot.setAttribute('cx', dotX);
                dot.setAttribute('cy', dotIndex === 0 ? y1 : y2);
                dot.setAttribute('r', 5);
                dot.style.fill = edgeColor;
                svg.appendChild(dot);
            });

            const qty = document.createElement('div');
            qty.className = 'wo-bom-edge-qty';
            qty.style.left = qtyX + 'px';
            qty.style.top = qtyY + 'px';
            qty.style.borderColor = edgeColor;
            qty.style.color = edgeColor;
            qty.textContent = fmt(edge.quantity || 1);
            canvas.appendChild(qty);
        });

        nodes.forEach(function (node) {
            const pos = positions[String(node.id)];
            if (!pos) return;

            const stock = node.stock || {};
            const status = node.status || {};
            const intent = bomNodeIntent(node);
            const intentLabel = intent === 'task'
                ? 'Görev açılacak'
                : (intent === 'stock' ? 'Stoktan karşılanacak' : 'Bilgi amaçlı');
            const nameParts = splitBomNodeName(node.name || '-');
            const netRequired = Number(node.netRequired ?? node.required ?? 0);
            const bomRequired = Number(node.bomRequired ?? node.required ?? 0);
            const requiredText = bomRequired !== netRequired
                ? 'Net: ' + fmt(netRequired) + ' · BOM: ' + fmt(bomRequired)
                : 'İhtiyaç: ' + fmt(netRequired);
            const div = document.createElement('div');
            div.className = 'wo-bom-map-item type-' + esc(node.typeClass || 'parca') + ' status-' + esc(status.key || 'neutral') + ' intent-' + intent;
            div.style.left = pos.x + 'px';
            div.style.top = pos.y + 'px';
            div.title = (node.name || '') + ' - ' + intentLabel + ' - ' + (status.label || '') +
                ' | Depodaki: ' + fmt(stock.warehouse) +
                ' | Ayrılan: ' + fmt(stock.reserved) +
                ' | Boşta: ' + fmt(stock.free);
            div.innerHTML =
                '<div class="wo-bom-map-circle">' + esc(nameParts.badge) + '</div>' +
                '<div class="wo-bom-map-label">' +
                    (nameParts.details.length
                        ? nameParts.details.map(function (part) { return '<span>' + esc(part) + '</span>'; }).join('')
                        : '<span>' + esc(node.department || node.type || '-') + '</span>') +
                '</div>' +
                '<div class="wo-bom-map-need">' + esc(requiredText) + '</div>' +
                '<div class="wo-bom-map-stock">' +
                    '<span title="Depodaki"><b>D</b>' + fmt(stock.warehouse) + '</span>' +
                    '<span title="Ayrılan/rezerve"><b>A</b>' + fmt(stock.reserved) + '</span>' +
                    '<span title="Boşta"><b>B</b>' + fmt(stock.free) + '</span>' +
                '</div>';
            canvas.appendChild(div);
        });

        if (exportMode) {
            prepareDiagramForExport(shell, chartWidth, chartHeight);
            return;
        }

        installBomZoom(shell, chartWidth, chartHeight);
    }

    function bomNodeIntent(node) {
        node = node || {};
        const netRequired = Number(node.netRequired ?? node.required ?? 0);
        const bomRequired = Number(node.bomRequired ?? node.required ?? 0);
        const reservedFromStock = Number(node.stockReserved || 0);
        const statusKey = (node.status && node.status.key) || '';

        if (netRequired > 0) {
            return 'task';
        }

        if (bomRequired > 0 && (reservedFromStock > 0 || statusKey === 'covered')) {
            return 'stock';
        }

        return 'neutral';
    }

    function bomNodeIntentColor(node) {
        const intent = bomNodeIntent(node);
        if (intent === 'task') return '#dc2626';
        if (intent === 'stock') return '#16a34a';
        return '#94a3b8';
    }

    function prepareDiagramForExport(shell, chartWidth, chartHeight) {
        const scroll = shell.querySelector('[data-wo-bom-scroll]');
        const stage = shell.querySelector('[data-wo-bom-stage]');
        const canvas = shell.querySelector('[data-wo-bom-canvas]');
        if (!scroll || !stage || !canvas) return;

        scroll.style.height = 'auto';
        scroll.style.overflow = 'visible';
        stage.style.width = chartWidth + 'px';
        stage.style.height = chartHeight + 'px';
        canvas.dataset.zoom = '1';
        canvas.style.transform = 'none';
    }

    function installBomZoom(shell, chartWidth, chartHeight) {
        const scroll = shell.querySelector('[data-wo-bom-scroll]');
        const stage = shell.querySelector('[data-wo-bom-stage]');
        const canvas = shell.querySelector('[data-wo-bom-canvas]');
        const label = shell.querySelector('[data-wo-zoom-label]');
        const zoomOut = shell.querySelector('[data-wo-zoom-out]');
        const zoomCenter = shell.querySelector('[data-wo-zoom-center]');
        const zoomIn = shell.querySelector('[data-wo-zoom-in]');
        const magnifierToggle = shell.querySelector('[data-wo-magnifier-toggle]');
        const fullscreenToggle = shell.querySelector('[data-wo-fullscreen-toggle]');
        if (!scroll || !stage || !canvas || !label || !zoomOut || !zoomCenter || !zoomIn || !magnifierToggle || !fullscreenToggle) return;

        const visibleWidth = Math.max(scroll.clientWidth || shell.clientWidth || 760, 320);
        const visibleHeight = Math.max(scroll.clientHeight || shell.clientHeight || 420, 320);
        const fitZoom = Math.min(1, (visibleWidth - 28) / chartWidth, (visibleHeight - 28) / chartHeight);
        let zoom = clampBomZoom(Number.isFinite(fitZoom) ? fitZoom : 1);
        const magnifier = installBomMagnifier(shell, scroll, canvas, magnifierToggle, function () {
            return zoom;
        });
        installBomPan(shell, scroll);
        installBomFullscreen(shell, scroll, stage, canvas, label, fullscreenToggle, chartWidth, chartHeight, function () {
            return zoom;
        }, function (nextZoom, keepCenter) {
            zoom = applyBomZoom(scroll, stage, canvas, label, chartWidth, chartHeight, nextZoom, keepCenter);
            magnifier.refresh();
            return zoom;
        });

        applyBomZoom(scroll, stage, canvas, label, chartWidth, chartHeight, zoom, false);
        centerBomDiagram(scroll, chartWidth, chartHeight, zoom);

        zoomOut.addEventListener('click', function () {
            zoom = applyBomZoom(scroll, stage, canvas, label, chartWidth, chartHeight, zoom - 0.1, true);
            magnifier.refresh();
        });

        zoomCenter.addEventListener('click', function () {
            centerBomDiagram(scroll, chartWidth, chartHeight, zoom);
        });

        zoomIn.addEventListener('click', function () {
            zoom = applyBomZoom(scroll, stage, canvas, label, chartWidth, chartHeight, zoom + 0.1, true);
            magnifier.refresh();
        });
    }

    function installBomPan(shell, scroll) {
        let panState = null;

        function canStartPan(event) {
            if (event.isPrimary === false) return false;
            if (event.pointerType === 'mouse' && event.button !== 0) return false;
            return true;
        }

        function finishPan(event) {
            if (!panState || (event && event.pointerId !== panState.pointerId)) return;

            try {
                if (scroll.hasPointerCapture && scroll.hasPointerCapture(panState.pointerId)) {
                    scroll.releasePointerCapture(panState.pointerId);
                }
            } catch (error) {}

            panState = null;
            shell.classList.remove('panning');
            scroll.classList.remove('dragging');
        }

        scroll.addEventListener('pointerdown', function (event) {
            if (!canStartPan(event)) return;

            panState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                scrollLeft: scroll.scrollLeft,
                scrollTop: scroll.scrollTop,
                dragging: false
            };

            if (scroll.setPointerCapture) {
                scroll.setPointerCapture(event.pointerId);
            }

            if (event.pointerType !== 'mouse') {
                event.preventDefault();
            }
        });

        scroll.addEventListener('pointermove', function (event) {
            if (!panState || event.pointerId !== panState.pointerId) return;

            const deltaX = event.clientX - panState.startX;
            const deltaY = event.clientY - panState.startY;

            if (!panState.dragging && Math.hypot(deltaX, deltaY) > 3) {
                panState.dragging = true;
                shell.classList.add('panning');
                scroll.classList.add('dragging');
            }

            if (!panState.dragging) return;

            scroll.scrollLeft = panState.scrollLeft - deltaX;
            scroll.scrollTop = panState.scrollTop - deltaY;
            event.preventDefault();
        });

        scroll.addEventListener('pointerup', finishPan);
        scroll.addEventListener('pointercancel', finishPan);
        scroll.addEventListener('lostpointercapture', finishPan);
        scroll.addEventListener('dragstart', function (event) {
            event.preventDefault();
        });
        scroll.addEventListener('wheel', function (event) {
            if (!event.shiftKey || Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
            if (scroll.scrollWidth <= scroll.clientWidth) return;

            scroll.scrollLeft += event.deltaY;
            event.preventDefault();
        }, { passive: false });
    }

    function installBomFullscreen(shell, scroll, stage, canvas, label, toggle, chartWidth, chartHeight, getZoom, setZoom) {
        let enabled = false;
        const icon = toggle.querySelector('i');

        function applyState(nextEnabled) {
            enabled = !!nextEnabled;
            shell.classList.toggle('wo-bom-fullscreen', enabled);
            document.body.classList.toggle('wo-bom-fullscreen-open', enabled);
            toggle.classList.toggle('active', enabled);
            toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            toggle.setAttribute('title', enabled ? 'Tam ekrandan çık' : 'Tam ekran');
            toggle.setAttribute('aria-label', enabled ? 'BOM ağacı tam ekranından çık' : 'BOM ağacını tam ekran incele');
            if (icon) {
                icon.className = enabled ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
            }

            if (enabled) {
                document.addEventListener('keydown', handleFullscreenKeydown);
            } else {
                document.removeEventListener('keydown', handleFullscreenKeydown);
            }

            window.requestAnimationFrame(function () {
                const visibleWidth = Math.max(scroll.clientWidth || shell.clientWidth || 760, 320);
                const visibleHeight = Math.max(scroll.clientHeight || shell.clientHeight || 420, 320);
                const fitZoom = clampBomZoom(Math.min(1, (visibleWidth - 40) / chartWidth, (visibleHeight - 40) / chartHeight));
                const nextZoom = enabled ? Math.max(getZoom(), fitZoom) : getZoom();
                setZoom(nextZoom, false);
                centerBomDiagram(scroll, chartWidth, chartHeight, nextZoom);
            });
        }

        function handleFullscreenKeydown(event) {
            if (event.key === 'Escape' && enabled) {
                applyState(false);
            }
        }

        function handleExternalClose() {
            if (!shell.isConnected) {
                document.removeEventListener('woBomCloseFullscreen', handleExternalClose);
                document.removeEventListener('keydown', handleFullscreenKeydown);
                return;
            }

            if (enabled) {
                applyState(false);
            }
        }

        toggle.addEventListener('click', function () {
            applyState(!enabled);
        });
        document.addEventListener('woBomCloseFullscreen', handleExternalClose);
    }

    function closeBomFullscreen() {
        document.dispatchEvent(new Event('woBomCloseFullscreen'));
        document.querySelectorAll('.wo-bom-diagram.wo-bom-fullscreen').forEach(function (shell) {
            shell.classList.remove('wo-bom-fullscreen');
            const toggle = shell.querySelector('[data-wo-fullscreen-toggle]');
            if (toggle) {
                toggle.classList.remove('active');
                toggle.setAttribute('aria-pressed', 'false');
                toggle.setAttribute('title', 'Tam ekran');
                toggle.setAttribute('aria-label', 'BOM ağacını tam ekran incele');
                const icon = toggle.querySelector('i');
                if (icon) icon.className = 'bi bi-fullscreen';
            }
        });
        document.body.classList.remove('wo-bom-fullscreen-open');
    }

    function installBomMagnifier(shell, scroll, canvas, toggle, getZoom) {
        const lens = shell.querySelector('[data-wo-magnifier]');
        const content = shell.querySelector('[data-wo-magnifier-content]');
        let enabled = false;
        let clone = null;

        if (!lens || !content || !toggle) {
            return { refresh: function () {} };
        }

        function refreshClone() {
            if (!enabled) return;

            content.innerHTML = '';
            clone = canvas.cloneNode(true);
            clone.removeAttribute('data-wo-bom-canvas');
            clone.removeAttribute('data-wo-bom-scroll');
            clone.style.position = 'absolute';
            clone.style.left = '0';
            clone.style.top = '0';
            clone.style.minWidth = '0';
            clone.style.pointerEvents = 'none';
            clone.style.transformOrigin = '0 0';
            clone.querySelectorAll('[id]').forEach(function (item) {
                item.removeAttribute('id');
            });
            content.appendChild(clone);
        }

        function setEnabled(nextEnabled) {
            enabled = !!nextEnabled;
            toggle.classList.toggle('active', enabled);
            toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            shell.classList.toggle('magnifier-on', enabled);
            lens.classList.remove('active');
            lens.setAttribute('aria-hidden', enabled ? 'false' : 'true');

            if (enabled) {
                refreshClone();
            } else {
                content.innerHTML = '';
                clone = null;
            }
        }

        function moveLens(event) {
            if (!enabled || !clone) return;

            const scrollRect = scroll.getBoundingClientRect();
            const shellRect = shell.getBoundingClientRect();
            const insideScroll = event.clientX >= scrollRect.left &&
                event.clientX <= scrollRect.right &&
                event.clientY >= scrollRect.top &&
                event.clientY <= scrollRect.bottom;

            if (!insideScroll) {
                lens.classList.remove('active');
                return;
            }

            const lensWidth = lens.offsetWidth || 270;
            const lensHeight = lens.offsetHeight || 190;
            const lensLeft = clampNumber(event.clientX - shellRect.left + 18, 8, shellRect.width - lensWidth - 8);
            const lensTop = clampNumber(event.clientY - shellRect.top + 18, 8, shellRect.height - lensHeight - 8);
            const zoom = getZoom();
            const magnifierScale = Math.max(1.65, Math.min(2.6, 1.15 / Math.max(zoom, 0.28)));
            const scale = zoom * magnifierScale;
            const canvasX = (scroll.scrollLeft + (event.clientX - scrollRect.left)) / Math.max(zoom, 0.01);
            const canvasY = (scroll.scrollTop + (event.clientY - scrollRect.top)) / Math.max(zoom, 0.01);
            const contentX = (lensWidth / 2) - (canvasX * scale);
            const contentY = (lensHeight / 2) - (canvasY * scale);

            lens.style.left = lensLeft + 'px';
            lens.style.top = lensTop + 'px';
            clone.style.transform = 'translate(' + contentX + 'px, ' + contentY + 'px) scale(' + scale + ')';
            lens.classList.add('active');
        }

        toggle.addEventListener('click', function () {
            setEnabled(!enabled);
        });

        scroll.addEventListener('mousemove', moveLens);
        scroll.addEventListener('mouseleave', function () {
            lens.classList.remove('active');
        });
        scroll.addEventListener('scroll', function () {
            lens.classList.remove('active');
        });

        return {
            refresh: function () {
                refreshClone();
            }
        };
    }

    function clampBomZoom(value) {
        return Math.min(1.6, Math.max(0.22, value));
    }

    function clampNumber(value, min, max) {
        if (max < min) return min;
        return Math.min(max, Math.max(min, value));
    }

    function centerBomDiagram(scroll, chartWidth, chartHeight, zoom) {
        scroll.scrollLeft = Math.max(0, ((chartWidth * zoom) - scroll.clientWidth) / 2);
        scroll.scrollTop = Math.max(0, ((chartHeight * zoom) - scroll.clientHeight) / 2);
    }

    function applyBomZoom(scroll, stage, canvas, label, chartWidth, chartHeight, nextZoom, keepCenter) {
        const oldZoom = Number(canvas.dataset.zoom || '1') || 1;
        const zoom = clampBomZoom(nextZoom);
        const centerX = (scroll.scrollLeft + (scroll.clientWidth / 2)) / oldZoom;
        const centerY = (scroll.scrollTop + (scroll.clientHeight / 2)) / oldZoom;

        canvas.dataset.zoom = String(zoom);
        canvas.style.transform = 'scale(' + zoom + ')';
        stage.style.width = Math.ceil(chartWidth * zoom) + 'px';
        stage.style.height = Math.ceil(chartHeight * zoom) + 'px';
        label.textContent = Math.round(zoom * 100) + '%';

        if (keepCenter) {
            scroll.scrollLeft = Math.max(0, (centerX * zoom) - (scroll.clientWidth / 2));
            scroll.scrollTop = Math.max(0, (centerY * zoom) - (scroll.clientHeight / 2));
        }

        return zoom;
    }
})();
</script>
@endpush
@endonce
