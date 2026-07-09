@extends('layouts.app')

@section('title', 'Ürün Yönetimi')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    /* ═══════════════════════════════════════
       Ürün Yönetimi — Sekme ve Form Tasarımı
       ═══════════════════════════════════════ */

    .product-page { display: flex; flex-direction: column; gap: 0; }

    /* ── Sekme Navigasyonu ── */
    .tab-nav {
        display: flex;
        gap: 4px;
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-bottom: none;
        border-radius: var(--z-radius-lg) var(--z-radius-lg) 0 0;
        padding: 6px 6px 0;
    }

    .tab-btn {
        flex: 1;
        padding: 14px 20px;
        border: none;
        background: transparent;
        color: var(--z-text-muted);
        font-family: inherit;
        font-size: 0.9rem;
        font-weight: 600;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .tab-btn:hover { color: var(--z-text); background: var(--z-bg-soft); }

    .tab-btn.active {
        color: var(--z-accent);
        background: var(--z-bg-soft);
        border-bottom: 3px solid var(--z-accent);
    }

    .tab-btn i { font-size: 1.1rem; }

    /* ── Sekme İçerikleri ── */
    .tab-content {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: 0 0 var(--z-radius-lg) var(--z-radius-lg);
        padding: 28px;
    }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; animation: fadeIn 0.25s ease; }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* ── Section Başlıkları ── */
    .section-label {
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--z-text-muted);
        margin-bottom: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .section-label i { font-size: 1rem; color: var(--z-accent); }

    .section-divider {
        border: none;
        border-top: 1px solid var(--z-border-light);
        margin: 24px 0;
    }

    /* ── Form Grid ── */
    .form-row {
        display: grid;
        gap: 16px;
        margin-bottom: 16px;
    }
    .form-row.cols-5 { grid-template-columns: repeat(5, 1fr); }
    .form-row.cols-4 { grid-template-columns: repeat(4, 1fr); }
    .form-row.cols-3 { grid-template-columns: repeat(3, 1fr); }
    .form-row.cols-2 { grid-template-columns: 1fr 1fr; }
    .form-row.cols-2-1 { grid-template-columns: 2fr 1fr; }
    .form-row.cols-3-1 { grid-template-columns: 3fr 1fr; }

    .form-group label {
        display: block;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--z-text-secondary);
        margin-bottom: 6px;
    }

    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.88rem;
        color: var(--z-text);
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: var(--z-accent);
        background: var(--z-bg-card);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
        font-family: 'JetBrains Mono', 'Fira Code', monospace;
        font-size: 0.82rem;
        line-height: 1.6;
    }

    /* ── Searchable Select ── */
    .searchable-select-wrapper { position: relative; }

    .searchable-select-wrapper input.ss-search {
        width: 100%;
        padding: 10px 14px 10px 36px;
        border: 2px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-input);
        font-family: inherit;
        font-size: 0.88rem;
        color: var(--z-text);
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    .searchable-select-wrapper input.ss-search:focus {
        outline: none;
        border-color: var(--z-accent);
        box-shadow: 0 0 0 3px var(--z-accent-soft);
    }

    .searchable-select-wrapper .ss-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.9rem;
        color: var(--z-text-muted);
        pointer-events: none;
        z-index: 1;
    }

    .ss-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        max-height: 220px;
        overflow-y: auto;
        background: var(--z-bg-card);
        border: 2px solid var(--z-accent);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        z-index: 100;
    }

    .ss-dropdown.open { display: block; }

    .ss-dropdown .ss-option {
        padding: 9px 14px;
        font-size: 0.84rem;
        cursor: pointer;
        transition: background 0.15s;
        border-bottom: 1px solid var(--z-border-light);
    }

    .ss-dropdown .ss-option:last-child { border-bottom: none; }
    .ss-dropdown .ss-option:hover { background: var(--z-accent-soft); }
    .ss-dropdown .ss-option.highlighted { background: var(--z-accent-soft); color: var(--z-accent); }

    .ss-dropdown .ss-no-result {
        padding: 14px;
        text-align: center;
        color: var(--z-text-muted);
        font-size: 0.82rem;
    }

    /* ── Aksiyon Butonları ── */
    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 16px;
    }

    .btn-action {
        padding: 10px 20px;
        border: none;
        border-radius: 10px;
        font-family: inherit;
        font-size: 0.84rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-action:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    .btn-action.green { background: #059669; color: white; }
    .btn-action.gray { background: var(--z-bg-soft); color: var(--z-text-secondary); border: 1px solid var(--z-border); }
    .btn-action.blue { background: #2563eb; color: white; }
    .btn-action.teal { background: var(--z-accent); color: white; }
    .btn-action.amber { background: #d97706; color: white; }
    .btn-action.red { background: #dc2626; color: white; }
    .btn-action.save {
        padding: 14px 32px;
        font-size: 0.92rem;
        background: linear-gradient(135deg, #0d9488, #06b6d4);
        color: white;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(13,148,136,0.25);
    }

    .btn-action.save:hover { box-shadow: 0 6px 20px rgba(13,148,136,0.35); }

    .btn-action:disabled {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-action:disabled:hover {
        transform: none;
        box-shadow: none;
    }

    /* ── Ürün Birleştirme ── */
    .merge-info {
        padding: 12px 16px;
        background: var(--z-bg-soft);
        border: 1px solid var(--z-border);
        border-radius: 10px;
        color: var(--z-text-secondary);
        font-size: 0.86rem;
    }

    .merge-preview-panel {
        display: none;
        margin-top: 16px;
    }

    .merge-preview-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border: 1px solid var(--z-border);
        border-radius: 10px;
        background: var(--z-bg-soft);
        margin-bottom: 14px;
    }

    .merge-preview-title {
        font-weight: 700;
        color: var(--z-text);
        line-height: 1.35;
    }

    .merge-preview-total {
        min-width: 110px;
        text-align: right;
        color: var(--z-accent);
        font-weight: 800;
    }

    .merge-count-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .merge-count-card {
        border: 1px solid var(--z-border);
        background: var(--z-bg-card);
        border-radius: 8px;
        padding: 12px;
        min-height: 74px;
    }

    .merge-count-label {
        color: var(--z-text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .merge-count-value {
        color: var(--z-text);
        font-size: 1.2rem;
        font-weight: 800;
        margin-top: 6px;
    }

    .merge-count-detail {
        color: var(--z-text-muted);
        font-size: 0.72rem;
        line-height: 1.45;
        margin-top: 6px;
    }

    .merge-warning-list {
        display: none;
        margin: 12px 0 0;
        padding: 10px 12px;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        background: #fffbeb;
        color: #92400e;
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .merge-linked-option {
        display: none;
        margin: 12px 0 0;
        padding: 12px 14px;
        border: 1px solid rgba(13,148,136,0.28);
        border-radius: 10px;
        background: rgba(13,148,136,0.06);
    }

    .merge-linked-check {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin: 0;
        cursor: pointer;
    }

    .merge-linked-check input {
        width: 18px;
        height: 18px;
        margin-top: 2px;
        accent-color: var(--z-accent);
        flex: 0 0 auto;
    }

    .merge-linked-check strong {
        display: block;
        color: var(--z-text);
        font-size: 0.88rem;
        line-height: 1.3;
    }

    .merge-linked-check span {
        display: block;
        color: var(--z-text-muted);
        font-size: 0.78rem;
        line-height: 1.45;
        margin-top: 3px;
    }

    /* ── Yol Gösterge Kartları ── */
    .path-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
        min-height: 32px;
    }

    .path-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 5px 12px;
        background: var(--z-accent-soft);
        color: var(--z-accent);
        border-radius: 8px;
        font-size: 0.76rem;
        font-weight: 700;
        border: 1px solid rgba(13,148,136,0.2);
    }

    .path-chip .chip-sep {
        color: var(--z-text-muted);
        font-weight: 400;
        margin: 0 2px;
    }

    /* ── İki Sütun Layout ── */
    .split-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-top: 16px;
    }

    /* ── Resim Yükleme ── */
    .image-area {
        background: var(--z-bg-soft);
        border: 2px dashed var(--z-border);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }

    .image-area img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: none;
        margin: 12px auto 0;
    }

    .image-area .img-label {
        font-size: 0.78rem;
        color: var(--z-text-muted);
        margin-top: 8px;
    }

    /* ── BOM Ağacı (Çizim) ── */
    .diagram-panel {
        width: 100%;
        height: 600px;
        background: #fffdf0;
        border: 2px dashed #d4af37;
        border-radius: 12px;
        position: relative;
        overflow: auto;
        margin-top: 0;
        display: block;
    }

    .diagram-node {
        position: absolute;
        width: 50px;
        height: 50px;
        background-color: var(--z-bg-card);
        border: 3px solid #d4af37;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 10px;
        font-weight: bold;
        color: var(--z-text);
        cursor: grab;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        text-align: center;
        z-index: 10;
        user-select: none;
    }

    .diagram-node:active { cursor: grabbing; box-shadow: 0 6px 12px rgba(0,0,0,0.15); }

    .diagram-node::after {
        content: attr(data-fullname);
        position: absolute;
        bottom: -24px;
        left: 50%;
        transform: translateX(-50%);
        background: #4b3621;
        color: #fff;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: normal;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, bottom 0.2s ease;
        z-index: 20;
        pointer-events: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .diagram-node:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: -32px;
    }

    .diagram-line {
        position: absolute;
        height: 2px;
        background-color: #dc2626; /* red lines */
        transform-origin: 0 0;
        z-index: 5;
    }

    .diagram-endpoint {
        position: absolute;
        width: 10px;
        height: 10px;
        background-color: #dc2626;
        border-radius: 50%;
        z-index: 6;
    }

    .diagram-qty {
        position: absolute;
        background-color: #fff;
        border: 1px solid #d4af37;
        border-radius: 4px;
        padding: 0 4px;
        font-size: 10px;
        font-weight: bold;
        color: #4b3621;
        z-index: 15;
        transform: translate(-50%, -50%); /* Centered on coord */
        pointer-events: none;
    }

    /* ── Responsive ── */
    @media (max-width: 992px) {
        .form-row.cols-5 { grid-template-columns: repeat(3, 1fr); }
        .split-layout { grid-template-columns: 1fr; }
        .merge-count-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 768px) {
        .tab-nav { flex-direction: column; gap: 2px; }
        .form-row.cols-5, .form-row.cols-4, .form-row.cols-3 { grid-template-columns: 1fr 1fr; }
        .form-row.cols-2, .form-row.cols-2-1, .form-row.cols-3-1 { grid-template-columns: 1fr; }
        .tab-content { padding: 18px; }
        .action-row { gap: 6px; }
        .btn-action { padding: 8px 14px; font-size: 0.8rem; }
    }

    @media (max-width: 480px) {
        .form-row.cols-5, .form-row.cols-4, .form-row.cols-3, .form-row.cols-2 { grid-template-columns: 1fr; }
        .merge-preview-head { align-items: flex-start; flex-direction: column; }
        .merge-preview-total { text-align: left; }
        .merge-count-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="product-page">
    <!-- ═══ Sekme Navigasyonu ═══ -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('add')" id="tabBtnAdd">
            <i class="bi bi-plus-circle"></i> Yeni Ürün Ekleme
        </button>
        <button class="tab-btn" onclick="switchTab('derive')" id="tabBtnDerive">
            <i class="bi bi-diagram-3"></i> Ürün Türetme
        </button>
        <button class="tab-btn" onclick="switchTab('merge')" id="tabBtnMerge">
            <i class="bi bi-link-45deg"></i> Ürün Birleştirme
        </button>
    </div>

    <div class="tab-content">
        <!-- ════════════════════════════════════════
             SEKME 1: YENİ ÜRÜN EKLEME (YeniUrunEkle.aspx)
             ════════════════════════════════════════ -->
        <div class="tab-panel active" id="tabAdd">
            <!-- Temel Bilgiler -->
            <div class="section-label"><i class="bi bi-box-seam"></i> Temel Ürün Bilgileri</div>
            <div class="form-row cols-5">
                <div class="form-group">
                    <label>Ürün Adı</label>
                    <input type="text" id="addUrunAdi" placeholder="Ürün adını giriniz">
                </div>
                <div class="form-group">
                    <label>Bölüm Adı</label>
                    <select id="addBolum">
                        <option value="">Seçiniz...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ürün Türü</label>
                    <select id="addUrunTuru">
                        <option value="" disabled selected>Seçiniz...</option>
                        <option value="Ham Madde">Ham Madde</option>
                        <option value="Ara Mamül">Ara Mamül</option>
                        <option value="Nihayi Ürün">Nihai Ürün</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Performans</label>
                    <input type="number" id="addPerformans" placeholder="Performans" value="0">
                </div>
                <div class="form-group">
                    <label>Min. Stok Adedi</label>
                    <input type="number" id="addMinStok" placeholder="Min. Stok" value="0">
                </div>
            </div>

            <hr class="section-divider">

            <!-- Üretim Yolu Oluşturucu -->
            <div class="section-label"><i class="bi bi-signpost-2"></i> BOM Yolu Oluştur (Öncül Ürünler)</div>
            <div class="form-row cols-3-1">
                <div class="form-group">
                    <label>Öncül Ürün Adı</label>
                    <div class="searchable-select-wrapper" id="ssAddOncul">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Ara ürün seçiniz veya arayınız..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="addOncul_value">
                        <input type="hidden" id="addOncul_text">
                    </div>
                </div>
                <div class="form-group">
                    <label>Adet</label>
                    <input type="number" id="addAdet" value="1" min="1">
                </div>
            </div>

            <div class="action-row">
                <button class="btn-action green" onclick="addPathEkle()"><i class="bi bi-plus-lg"></i> Ekle</button>
                <button class="btn-action gray" onclick="addPathGeri()"><i class="bi bi-arrow-left"></i> Geri</button>
                <button class="btn-action blue" onclick="addPathDuzenle()"><i class="bi bi-pencil-square"></i> Düzenle</button>
                <button class="btn-action red" onclick="addPathTemizle()"><i class="bi bi-trash3"></i> Temizle</button>
                <button class="btn-action amber" onclick="cizDiyagram('addYol', 'addDiagramPanel')"><i class="bi bi-diagram-3"></i> BOM Çiz</button>
            </div>

            <hr class="section-divider">

            <!-- Sol: Yol + Resim / Sağ: Diyagram -->
            <div class="split-layout">
                <div>
                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span>BOM Yolu (Üretim Reçetesi)</span>
                            <button class="btn btn-sm" style="color: #0ea5e9; background: none; border: none; padding: 0;" onclick="showInfo('add')" type="button">
                                <i class="bi bi-info-circle-fill"></i> Nasıl Kullanılır?
                            </button>
                        </label>
                        <textarea id="addYol" placeholder="Öncül ürünleri seçip 'Ekle' butonuna tıklayarak üretim yolunu oluşturun..."></textarea>
                    </div>

                    <div class="path-preview" id="addPathPreview"></div>
                </div>

                <div>
                    <!-- Diyagram Alanı -->
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--z-text-secondary); margin-bottom: 6px;">BOM Ağacı Önizlemesi</label>
                    <div id="addDiagramPanel" class="diagram-panel">
                        <div style="text-align: center; margin-top: 40px; color: #a1a1aa;">
                            <i class="bi bi-bezier2" style="font-size: 2rem;"></i><br>
                            <span style="font-size: 0.8rem;">BOM ağacını çizmek için "BOM Çiz" butonuna tıklayınız.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resim (Tam Genişlik) -->
            <div class="image-area" style="margin-top: 24px; text-align: left;">
                <h4 style="margin-top:0; font-size:1rem; color: var(--z-text);"><i class="bi bi-image" style="color:#d4af37; margin-right:6px;"></i> Ürün Resmi</h4>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label>Mevcut Listeden Seç</label>
                        <select id="addResim" onchange="previewImageMode('add')">
                            <option value="">Resim seçiniz...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Web Bağlantısı (URL) ile Ekle</label>
                        <input type="text" id="addUrl" placeholder="https://..." oninput="previewImageMode('add')">
                    </div>
                    <div class="form-group">
                        <label>Bilgisayardan Dosya Yükle</label>
                        <input type="file" id="addDosya" accept=".jpg,.jpeg,.png" onchange="previewImageMode('add')">
                    </div>
                </div>
                <div class="text-center mt-3">
                    <img id="addImgPreview" alt="Ürün önizleme" style="max-width: 200px; max-height: 200px; border-radius: 8px; display:none; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="img-label" id="addImgLabel" style="font-size: 0.8rem; margin-top:8px; color:var(--z-text-secondary);"></div>
                </div>
            </div>


            <div style="text-align: right; margin-top: 24px;">
                <button class="btn-action save" onclick="kaydetYeniUrun()">
                    <i class="bi bi-floppy"></i> Yeni Ürünü Kaydet
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             SEKME 2: ÜRÜN TÜRETME (YeniUrunDuzenle.aspx)
             ════════════════════════════════════════ -->
        <div class="tab-panel" id="tabDerive">
            <!-- Nihai Ürün Seçimi -->
            <div class="section-label"><i class="bi bi-layers"></i> Türetilecek Nihai Ürün</div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label>Nihai Ürün Seçin</label>
                    <div class="searchable-select-wrapper" id="ssDeriveUrun">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Nihai ürün arayın..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="deriveUrun_value">
                        <input type="hidden" id="deriveUrun_text">
                    </div>
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <div id="deriveSelectedInfo" style="padding: 10px 16px; background: var(--z-bg-soft); border-radius: 10px; font-size: 0.84rem; color: var(--z-text-muted); width: 100%;">
                        Henüz bir ürün seçilmedi
                    </div>
                </div>
            </div>

            <hr class="section-divider">

            <!-- Türetme Araçları -->
            <div class="section-label"><i class="bi bi-arrow-repeat"></i> Bileşen Değiştirme (Türetme)</div>
            <div class="form-row cols-3">
                <div class="form-group">
                    <label>Değiştirilecek Ara Ürün</label>
                    <select id="deriveOzellik">
                        <option value="">Önce nihayi ürün seçin...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Silinecek Metin</label>
                    <input type="text" id="deriveSil" placeholder="Ürün adından silinecek kısım">
                </div>
                <div class="form-group">
                    <label>Eklenecek Metin</label>
                    <input type="text" id="deriveEkle" placeholder="Ürün adına eklenecek kısım">
                </div>
            </div>

            <div class="action-row">
                <button class="btn-action teal" onclick="turet()"><i class="bi bi-arrow-repeat"></i> Türet</button>
            </div>

            <hr class="section-divider">

            <!-- Manuel Yol Düzenleme -->
            <div class="section-label"><i class="bi bi-pencil-square"></i> Manuel Yol Düzenleme</div>
            <div class="form-row cols-3">
                <div class="form-group">
                    <label>Giriş Ara Ürün</label>
                    <div class="searchable-select-wrapper" id="ssDeriveGiris">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Giriş ara ürün seçin..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="deriveGiris_value">
                        <input type="hidden" id="deriveGiris_text">
                    </div>
                </div>
                <div class="form-group">
                    <label>Hedef Ara Ürün</label>
                    <div class="searchable-select-wrapper" id="ssDeriveHedef">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Hedef ara ürün seçin..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="deriveHedef_value">
                        <input type="hidden" id="deriveHedef_text">
                    </div>
                </div>
                <div class="form-group">
                    <label>Adet</label>
                    <input type="number" id="deriveAdet" value="1" min="1">
                </div>
            </div>

            <div class="action-row">
                <button class="btn-action green" onclick="derivePathEkle()"><i class="bi bi-plus-lg"></i> Ekle</button>
                <button class="btn-action gray" onclick="derivePathGeri()"><i class="bi bi-arrow-left"></i> Geri</button>
                <button class="btn-action red" onclick="derivePathTemizle()"><i class="bi bi-trash3"></i> Temizle</button>
                <button class="btn-action amber" onclick="cizDiyagram('deriveYol', 'deriveDiagramPanel')"><i class="bi bi-diagram-3"></i> BOM Çiz</button>
            </div>

            <hr class="section-divider">

            <!-- Yol + Resim + Diyagram -->
            <div class="split-layout">
                <div>
                    <div class="form-group">
                        <label style="display: flex; justify-content: space-between; align-items: center;">
                            <span>BOM Yolu (Türetilmiş)</span>
                            <button class="btn btn-sm" style="color: #d97706; background: none; border: none; padding: 0;" onclick="showInfo('derive')" type="button">
                                <i class="bi bi-info-circle-fill"></i> Türetme Nasıl Çalışır?
                            </button>
                        </label>
                        <textarea id="deriveYol" placeholder="Türetme işlemi sonrası yol burada görünecek..."></textarea>
                    </div>
                    <div class="path-preview" id="derivePathPreview"></div>
                </div>

                <div>
                    <!-- Diyagram Alanı -->
                    <label style="display: block; font-size: 0.8rem; font-weight: 600; color: var(--z-text-secondary); margin-bottom: 6px;">BOM Ağacı Önizlemesi</label>
                    <div id="deriveDiagramPanel" class="diagram-panel">
                        <div style="text-align: center; margin-top: 40px; color: #a1a1aa;">
                            <i class="bi bi-bezier2" style="font-size: 2rem;"></i><br>
                            <span style="font-size: 0.8rem;">BOM ağacını çizmek için "BOM Çiz" butonuna tıklayınız.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resim (Tam Genişlik) -->
            <div class="image-area" style="margin-top: 24px; text-align: left;">
                <h4 style="margin-top:0; font-size:1rem; color: var(--z-text);"><i class="bi bi-image" style="color:#d4af37; margin-right:6px;"></i> Ürün Resmi</h4>
                <div class="form-row cols-3">
                    <div class="form-group">
                        <label>Mevcut Listeden Seç</label>
                        <select id="deriveResim" onchange="previewImageMode('derive')">
                            <option value="">Resim seçiniz...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Web Bağlantısı (URL) ile Ekle</label>
                        <input type="text" id="deriveUrl" placeholder="https://..." oninput="previewImageMode('derive')">
                    </div>
                    <div class="form-group">
                        <label>Bilgisayardan Dosya Yükle</label>
                        <input type="file" id="deriveDosya" accept=".jpg,.jpeg,.png" onchange="previewImageMode('derive')">
                    </div>
                </div>
                <div class="text-center mt-3">
                    <img id="deriveImgPreview" alt="Ürün önizleme" style="max-width: 200px; max-height: 200px; border-radius: 8px; display:none; margin: 0 auto; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <div class="img-label" id="deriveImgLabel" style="font-size: 0.8rem; margin-top:8px; color:var(--z-text-secondary);"></div>
                </div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button class="btn-action save" onclick="kaydetTuretme()">
                    <i class="bi bi-floppy"></i> Düzenlenmiş Ürünü Kaydet
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             SEKME 3: ÜRÜN BİRLEŞTİRME
             ════════════════════════════════════════ -->
        <div class="tab-panel" id="tabMerge">
            <div class="section-label"><i class="bi bi-link-45deg"></i> Ürün Birleştirme</div>
            <div class="form-row cols-3">
                <div class="form-group">
                    <label>Birleştirme Türü</label>
                    <select id="mergeType" onchange="onMergeTypeChanged()">
                        <option value="component">Ara Ürün / Parça</option>
                        <option value="product">Nihai Ürün</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kaynak Ürün</label>
                    <div class="searchable-select-wrapper" id="ssMergeSource">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Birleştirilecek eski ürünü arayın..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="mergeSource_value">
                        <input type="hidden" id="mergeSource_text">
                    </div>
                </div>
                <div class="form-group">
                    <label>Hedef Ürün</label>
                    <div class="searchable-select-wrapper" id="ssMergeTarget">
                        <i class="bi bi-search ss-icon"></i>
                        <input type="text" class="ss-search" placeholder="Kalacak doğru ürünü arayın..." autocomplete="off">
                        <div class="ss-dropdown"></div>
                        <input type="hidden" id="mergeTarget_value">
                        <input type="hidden" id="mergeTarget_text">
                    </div>
                </div>
            </div>

            <div class="merge-linked-option" id="mergeLinkedOption">
                <label class="merge-linked-check">
                    <input type="checkbox" id="mergeLinkedComponent" onchange="onMergeLinkedOptionChanged()">
                    <span>
                        <strong>Kök ara ürün/parça ve stok kartlarını da birleştir</strong>
                        <span>Nihai ürünlerin üretim reçetesindeki ana ara ürün kayıtları farklıysa, kaynak kök kayıt ve stokları da hedef kök karta taşınır.</span>
                    </span>
                </label>
            </div>

            <div class="action-row">
                <button class="btn-action blue" onclick="birlesmeOnizle()"><i class="bi bi-eye"></i> Önizle</button>
                <button class="btn-action gray" onclick="resetMergeForm()"><i class="bi bi-arrow-counterclockwise"></i> Temizle</button>
            </div>

            <div class="merge-info" id="mergeSelectedInfo">
                Kaynak ve hedef ürün seçilmedi.
            </div>

            <hr class="section-divider">

            <div id="mergePreviewPanel" class="merge-preview-panel">
                <div class="merge-preview-head">
                    <div>
                        <div class="merge-preview-title" id="mergePreviewTitle"></div>
                        <div style="font-size:0.8rem; color:var(--z-text-muted); margin-top:4px;" id="mergePreviewSubtitle"></div>
                    </div>
                    <div class="merge-preview-total" id="mergePreviewTotal"></div>
                </div>
                <div class="merge-count-grid" id="mergePreviewSections"></div>
                <div class="merge-warning-list" id="mergePreviewWarnings"></div>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button class="btn-action save" id="mergeExecuteBtn" onclick="birlesmeyiUygula()" disabled>
                    <i class="bi bi-check2-circle"></i> Birleştir
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ════════════════════════════════════════════
// GLOBAL VERİLER
// ════════════════════════════════════════════
let allComponents = [];   // tbAraUrun listesi
let allDepartments = [];  // tbBolum listesi
let allProducts = [];     // tbUrunler listesi
let allImages = [];       // Resim listesi
let deriveSourceId = null; // Türetme: seçilen ürün No
let deriveOriginalPath = ''; // Türetme: orijinal yol (kıyaslama için)
let mergePreviewData = null; // Birleştirme: son önizleme sonucu

if (!window.Swal) {
    window.Swal = {
        fire: (...args) => {
            const options = typeof args[0] === 'object'
                ? args[0]
                : { title: args[0], text: args[1], icon: args[2] };

            const message = [options.title, options.text].filter(Boolean).join('\n');
            if (options.showCancelButton) {
                return Promise.resolve({ isConfirmed: window.confirm(message || 'Devam edilsin mi?') });
            }

            if (!options.timer || options.showConfirmButton !== false) {
                window.alert(message || 'İşlem tamamlandı.');
            }

            return Promise.resolve({ isConfirmed: true });
        },
        showLoading: () => {},
        close: () => {},
    };
}

// ════════════════════════════════════════════
// SEKME GEÇİŞİ
// ════════════════════════════════════════════
function switchTab(tab, updateUrl = true) {
    const tabs = {
        add: { btn: 'tabBtnAdd', panel: 'tabAdd' },
        derive: { btn: 'tabBtnDerive', panel: 'tabDerive' },
        merge: { btn: 'tabBtnMerge', panel: 'tabMerge' }
    };
    if (!tabs[tab]) tab = 'add';

    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById(tabs[tab].btn).classList.add('active');
    document.getElementById(tabs[tab].panel).classList.add('active');

    if (updateUrl) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
}

// ════════════════════════════════════════════
// VERİLERİ YÜKLE
// ════════════════════════════════════════════
async function loadInitialData() {
    try {
        const [compResp, deptResp, productResp] = await Promise.all([
            fetch('/api/database/components?limit=9999', { headers: { Accept: 'application/json' } }),
            fetch('/api/database/departments', { headers: { Accept: 'application/json' } }),
            fetch('/api/database/products?limit=9999', { headers: { Accept: 'application/json' } })
        ]);

        const compData = await compResp.json();
        const deptData = await deptResp.json();
        const productData = await productResp.json();

        allComponents = compData.data || [];
        allDepartments = deptData.data || [];
        allProducts = productData.data || [];
        const selectableComponents = allComponents.filter(c => Number(c.id) !== 1373);

        // Bölüm dropdown doldur
        const bolumSelect = document.getElementById('addBolum');
        bolumSelect.innerHTML = '<option value="">Seçiniz...</option>';
        allDepartments.forEach(d => {
            bolumSelect.innerHTML += `<option value="${d.id}">${escHtml(d.name)}</option>`;
        });

        // Resim listesi
        allImages = allProducts
            .map(p => p.image || '')
            .filter(r => r && r.trim() !== '')
            .filter((v, i, a) => a.indexOf(v) === i)
            .sort();

        fillImageSelect('addResim', allImages);
        fillImageSelect('deriveResim', allImages);

        // Searchable select'leri başlat
        initSearchableSelect('ssAddOncul', selectableComponents.map(c => ({ value: c.id, text: c.name || '' })));
        initSearchableSelect('ssDeriveUrun', allComponents.filter(c => c.type === 'Nihayi Ürün' || c.type === 'Nihai Ürün').map(c => ({ value: c.id, text: c.name || '' })));
        initSearchableSelect('ssDeriveGiris', selectableComponents.map(c => ({ value: c.id, text: c.name || '' })));
        initSearchableSelect('ssDeriveHedef', selectableComponents.map(c => ({ value: c.id, text: c.name || '' })));
        initSearchableSelect('ssMergeSource', mergeItemsForType());
        initSearchableSelect('ssMergeTarget', mergeItemsForType());
        await preselectDeriveProductFromQuery();

    } catch (e) {
        console.error('Veri yükleme hatası:', e);
        Swal.fire({ icon: 'error', title: 'Veri Yüklenemedi', text: e.message || 'Ürün listeleri alınamadı.' });
    }
}

function fillImageSelect(id, images) {
    const select = document.getElementById(id);
    select.innerHTML = '<option value="">Resim seçiniz...</option>';
    images.forEach(img => {
        select.innerHTML += `<option value="${escHtml(img)}">${escHtml(img)}</option>`;
    });
}

function previewImageMode(type) {
    const select = document.getElementById(type + 'Resim');
    const url = document.getElementById(type + 'Url');
    const file = document.getElementById(type + 'Dosya');
    const preview = document.getElementById(type + 'ImgPreview');
    const label = document.getElementById(type + 'ImgLabel');

    if (file.files && file.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
            label.textContent = "Yüklenecek Dosya: " + file.files[0].name;
            select.value = '';
            url.value = '';
        }
        reader.readAsDataURL(file.files[0]);
    } else if (url.value.trim() !== '') {
        preview.src = url.value;
        preview.style.display = 'block';
        label.textContent = "Bağlantıdan Resim Gösteriliyor";
        select.value = '';
    } else if (select.value) {
        preview.src = '/Resimler/' + select.value;
        preview.style.display = 'block';
        label.textContent = 'Seçilen Resim: ' + select.value;
    } else {
        preview.style.display = 'none';
        label.textContent = '';
    }
}

// ════════════════════════════════════════════
// SEARCHABLE SELECT BİLEŞENİ
// ════════════════════════════════════════════
function initSearchableSelect(wrapperId, items) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    if (wrapper._searchableSelectReady && typeof wrapper._updateItems === 'function') {
        wrapper._updateItems(items);
        return;
    }

    const searchInput = wrapper.querySelector('.ss-search');
    const dropdown = wrapper.querySelector('.ss-dropdown');
    const hiddenValue = wrapper.querySelector('input[type=hidden]:first-of-type') || wrapper.querySelector('input[id$="_value"]');
    const hiddenText = wrapper.querySelector('input[id$="_text"]');

    let filteredItems = items;
    let selectedIndex = -1;

    function renderDropdown(list) {
        if (list.length === 0) {
            dropdown.innerHTML = '<div class="ss-no-result">Sonuç bulunamadı</div>';
        } else {
            dropdown.innerHTML = list.slice(0, 100).map((item, i) =>
                `<div class="ss-option${i === selectedIndex ? ' highlighted' : ''}" data-value="${item.value}" data-text="${escHtml(item.text)}">${escHtml(item.text)}</div>`
            ).join('');
        }
    }

    searchInput.addEventListener('focus', () => {
        filteredItems = items.filter(it => String(it.text || '').toLowerCase().includes(searchInput.value.toLowerCase()));
        selectedIndex = -1;
        renderDropdown(filteredItems);
        dropdown.classList.add('open');
    });

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        filteredItems = items.filter(it => String(it.text || '').toLowerCase().includes(query));
        selectedIndex = -1;
        if (hiddenValue) hiddenValue.value = '';
        if (hiddenText) hiddenText.value = '';
        if (wrapperId === 'ssDeriveUrun') {
            resetDeriveSelection();
        }
        renderDropdown(filteredItems);
        dropdown.classList.add('open');
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (selectedIndex < filteredItems.length - 1) selectedIndex++;
            renderDropdown(filteredItems);
            scrollToSelected(dropdown);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (selectedIndex > 0) selectedIndex--;
            renderDropdown(filteredItems);
            scrollToSelected(dropdown);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (selectedIndex >= 0 && selectedIndex < filteredItems.length) {
                selectItem(filteredItems[selectedIndex]);
            }
        } else if (e.key === 'Escape') {
            dropdown.classList.remove('open');
        }
    });

    dropdown.addEventListener('click', (e) => {
        const option = e.target.closest('.ss-option');
        if (option) {
            const val = option.dataset.value;
            const txt = option.dataset.text;
            selectItem({ value: val, text: txt });
        }
    });

    function selectItem(item) {
        searchInput.value = item.text;
        if (hiddenValue) hiddenValue.value = item.value;
        if (hiddenText) hiddenText.value = item.text;
        dropdown.classList.remove('open');

        // Nihai ürün seçildiğinde otomatik yükleme
        if (wrapperId === 'ssDeriveUrun') {
            onDeriveUrunSelected(item.value, item.text);
        }
        if (wrapperId === 'ssMergeSource' || wrapperId === 'ssMergeTarget') {
            resetMergePreview();
            updateMergeSelectedInfo();
        }
    }

    function scrollToSelected(dd) {
        const highlighted = dd.querySelector('.ss-option.highlighted');
        if (highlighted) highlighted.scrollIntoView({ block: 'nearest' });
    }

    // Dışa tıklayınca kapat
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });

    // Listeyi güncellemek için method
    wrapper._updateItems = function(newItems) {
        items = newItems;
        filteredItems = newItems;
    };
    wrapper._searchableSelectReady = true;
}

// ════════════════════════════════════════════
// SEKME 1: YENİ ÜRÜN EKLEME
// ════════════════════════════════════════════

// Ekle butonu — yola yeni segment ekler
function addPathEkle() {
    const text = document.getElementById('addOncul_text').value;
    const adet = document.getElementById('addAdet').value || '1';

    if (!text) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Öncül ürün seçiniz.' });
        return;
    }

    const yolEl = document.getElementById('addYol');
    let yol = yolEl.value.trim();
    if (yol) yol += ':';
    yol += text + '-' + adet;
    yolEl.value = yol;

    updatePathPreview('addYol', 'addPathPreview');
}

// Geri butonu — son segmenti siler
function addPathGeri() {
    const yolEl = document.getElementById('addYol');
    let yol = yolEl.value.trim();
    if (yol.includes(':')) {
        yol = yol.substring(0, yol.lastIndexOf(':'));
    } else {
        yol = '';
    }
    yolEl.value = yol;
    updatePathPreview('addYol', 'addPathPreview');
}

// ASP.NET'teki Düzenle modalı: seçili metni başka ara ürün adıyla değiştirir
async function addPathDuzenle() {
    const textarea = document.getElementById('addYol');
    const selectedText = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd).trim();

    if (!selectedText) {
        Swal.fire({ icon: 'warning', title: 'Seçim Yok', text: 'BOM yolunda değiştirmek istediğiniz ürün adını seçiniz.' });
        return;
    }

    const options = {};
    allComponents
        .filter(c => Number(c.id) !== 1373 && c.name)
        .sort((a, b) => String(a.name).localeCompare(String(b.name), 'tr'))
        .forEach(c => {
            options[c.name] = c.name;
        });

    const result = await Swal.fire({
        title: 'Ürün Değiştir',
        input: 'select',
        inputOptions: options,
        inputPlaceholder: 'Yeni ürün seçiniz',
        showCancelButton: true,
        confirmButtonText: 'Güncelle',
        cancelButtonText: 'İptal',
        inputValidator: value => !value ? 'Yeni ürün seçiniz.' : undefined
    });

    if (!result.isConfirmed || !result.value) return;

    textarea.value = textarea.value.split(selectedText).join(result.value);
    updatePathPreview('addYol', 'addPathPreview');
}

// Temizle
function addPathTemizle() {
    document.getElementById('addYol').value = '';
    updatePathPreview('addYol', 'addPathPreview');
}

// Kaydet (YeniUrunEkle Button1_Click karşılığı)
async function kaydetYeniUrun() {
    const urunAdi = document.getElementById('addUrunAdi').value.trim();
    const bolumId = document.getElementById('addBolum').value;
    const urunTuru = document.getElementById('addUrunTuru').value;
    const performans = document.getElementById('addPerformans').value || '0';
    const minStok = document.getElementById('addMinStok').value || '0';
    const namePath = document.getElementById('addYol').value.trim();

    let resim = document.getElementById('addResim').value;
    if (document.getElementById('addUrl').value.trim()) resim = document.getElementById('addUrl').value.trim();
    const fileInput = document.getElementById('addDosya');

    if (!urunAdi) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Ürün adı giriniz.' });
        return;
    }

    try {
        Swal.fire({ title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const payload = {
            name: urunAdi,
            department_id: bolumId || '',
            type: urunTuru,
            performance_score: parseInt(performans),
            min_quantity: parseInt(minStok),
            name_path: namePath,
            image: resim
        };
        const requestOptions = {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }
        };

        if (fileInput.files.length > 0) {
            const fd = new FormData();
            Object.entries(payload).forEach(([key, value]) => fd.append(key, value ?? ''));
            fd.append('image_file', fileInput.files[0]);
            requestOptions.body = fd;
        } else {
            requestOptions.headers['Content-Type'] = 'application/json';
            requestOptions.body = JSON.stringify(payload);
        }

        const resp = await fetch('/api/database/components', requestOptions);

        const data = await resp.json();
        Swal.close();

        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message || 'Ürün kaydedildi.' });
            // Formu temizle
            document.getElementById('addUrunAdi').value = '';
            document.getElementById('addBolum').value = '';
            document.getElementById('addUrunTuru').value = '';
            document.getElementById('addPerformans').value = '0';
            document.getElementById('addMinStok').value = '0';
            document.getElementById('addYol').value = '';
            document.getElementById('addUrl').value = '';
            document.getElementById('addDosya').value = '';
            updatePathPreview('addYol', 'addPathPreview');
            previewImageMode('add');
            // Listeleri yenile
            loadInitialData();
        } else {
            Swal.fire({ icon: 'error', title: 'Hata', text: data.message || 'Kayıt başarısız.' });
        }
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Hata', text: e.message });
    }
}

// ════════════════════════════════════════════
// SEKME 2: ÜRÜN TÜRETME
// ════════════════════════════════════════════

// Nihai ürün seçildiğinde
async function onDeriveUrunSelected(id, name) {
    deriveSourceId = parseInt(id);
    document.getElementById('deriveSelectedInfo').innerHTML =
        `<i class="bi bi-check-circle" style="color: #059669;"></i> Seçilen: <strong>${escHtml(name)}</strong> (No: ${id})`;

    try {
        const resp = await fetch(`/api/database/components/${id}/bom-path-names`, { headers: { Accept: 'application/json' } });
        const data = await resp.json();

        if (data.success) {
            applyDeriveBomData(data);
        } else {
            document.getElementById('deriveYol').value = '';
            deriveOriginalPath = '';
            Swal.fire({ icon: 'warning', title: 'Uyarı', text: data.message || 'BOM yolu bulunamadı.' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Hata', text: e.message });
    }
}

async function preselectDeriveProductFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const productNo = params.get('No') || params.get('no') || params.get('product_no');
    if (!productNo) return;

    switchTab('derive', false);

    try {
        const resp = await fetch(`/api/database/products/${encodeURIComponent(productNo)}/bom-path-names`, { headers: { Accept: 'application/json' } });
        const data = await resp.json();

        if (!data.success) {
            throw new Error(data.message || 'Ürün bilgisi alınamadı.');
        }

        const componentId = parseInt(data.component_id || 0);
        const productName = data.product_name || '';
        if (componentId <= 0 || !productName) {
            throw new Error('Bu nihai ürün için Ara Ürün kaydı bulunamadı.');
        }

        deriveSourceId = componentId;
        const wrapper = document.getElementById('ssDeriveUrun');
        wrapper.querySelector('.ss-search').value = productName;
        document.getElementById('deriveUrun_value').value = componentId;
        document.getElementById('deriveUrun_text').value = productName;
        document.getElementById('deriveSelectedInfo').innerHTML =
            `<i class="bi bi-check-circle" style="color: #059669;"></i> Seçilen: <strong>${escHtml(productName)}</strong> (No: ${componentId})`;

        applyDeriveBomData(data);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Ürün Açılmadı', text: e.message || 'Türetilecek ürün yüklenemedi.' });
    }
}

function applyDeriveBomData(data) {
    document.getElementById('deriveYol').value = data.namePath || '';
    deriveOriginalPath = data.namePath || '';
    updatePathPreview('deriveYol', 'derivePathPreview');

    // ASP.NET yukle(): sadece yaprak bileşenler değiştirilebilir listesine girer.
    const ddOzellik = document.getElementById('deriveOzellik');
    ddOzellik.innerHTML = '<option value="">Seçiniz...</option>';
    (data.replaceables || []).forEach(name => {
        ddOzellik.innerHTML += `<option value="${escHtml(name)}">${escHtml(name)}</option>`;
    });
}

function resetDeriveSelection() {
    deriveSourceId = null;
    deriveOriginalPath = '';
    document.getElementById('deriveYol').value = '';
    document.getElementById('deriveOzellik').innerHTML = '<option value="">Önce nihayi ürün seçin...</option>';
    document.getElementById('deriveSelectedInfo').innerHTML = 'Henüz bir ürün seçilmedi';
    updatePathPreview('deriveYol', 'derivePathPreview');
}

function finalProductItems() {
    return allComponents.filter(c => c.type === 'Nihayi Ürün' || c.type === 'Nihai Ürün');
}

async function ensureDeriveSourceSelected() {
    if (deriveSourceId) return true;

    const wrapper = document.getElementById('ssDeriveUrun');
    const typedName = (wrapper?.querySelector('.ss-search')?.value || '').trim();
    if (!typedName) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Önce türetilecek nihai ürünü listeden seçiniz.' });
        return false;
    }

    const typedLower = typedName.toLocaleLowerCase('tr-TR');
    const match = finalProductItems().find(item => String(item.name || '').trim().toLocaleLowerCase('tr-TR') === typedLower);
    if (!match) {
        Swal.fire({ icon: 'warning', title: 'Ürün Seçilmedi', text: 'Yazdığınız ürün listede tam eşleşmedi. Açılan listeden ürünü seçiniz.' });
        return false;
    }

    wrapper.querySelector('.ss-search').value = match.name || typedName;
    document.getElementById('deriveUrun_value').value = match.id;
    document.getElementById('deriveUrun_text').value = match.name || typedName;
    await onDeriveUrunSelected(match.id, match.name || typedName);
    return Boolean(deriveSourceId);
}

// Türet butonu — ASP.NET sonrakiUrunAdlariBul karşılığı
async function turet() {
    if (!(await ensureDeriveSourceSelected())) return;

    const ozellik = document.getElementById('deriveOzellik').value;
    const sil = document.getElementById('deriveSil').value;
    const ekle = document.getElementById('deriveEkle').value;

    let yol = deriveOriginalPath.trim();
    if (!yol) {
        Swal.fire({ icon: 'warning', title: 'BOM Yolu Yok', text: 'Seçilen ürünün üretim yolu boş. Türetme için önce bu ürüne BOM yolu tanımlı olmalı.' });
        return;
    }
    if (!ozellik) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Değiştirilecek ara ürünü seçiniz.' });
        return;
    }
    if (!sil) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Silinecek metin giriniz.' });
        return;
    }
    if (!ekle) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Eklenecek metin giriniz.' });
        return;
    }

    const beforeYol = yol;
    yol = sonrakiUrunAdlariBul(ozellik, yol, sil, ekle);

    // Yolun başında ':' varsa kaldır
    if (yol.startsWith(':')) yol = yol.substring(1);
    if (yol === beforeYol) {
        Swal.fire({ icon: 'warning', title: 'Değişiklik Yok', text: 'Seçilen ara ürün bu BOM yolunda değiştirilemedi. Başka bir ara ürün seçiniz.' });
        return;
    }

    document.getElementById('deriveYol').value = yol;
    updatePathPreview('deriveYol', 'derivePathPreview');

    Swal.fire({ icon: 'info', title: 'Türetme Uygulandı', text: 'Yol güncellendi. Kontrol edip "Kaydet" butonuna tıklayınız.', timer: 2500, showConfirmButton: false });
}

// ASP.NET sonrakiUrunAdlariBul - birebir çeviri
function sonrakiUrunAdlariBul(refUrunAdi, yol, sil, ekle) {
    yol = ':' + yol;
    let ek = '';
    const sourcePlaceholder = '__ZEM_DERIVE_SOURCE__';
    const targetPlaceholder = '__ZEM_DERIVE_TARGET__';

    if (yol.includes(':' + refUrunAdi + '-')) {
        // Replace kontrolü: sil kısmı yoksa direkt ekle
        if (deriveNextName(refUrunAdi, sil, ekle) === refUrunAdi) {
            ek = ekle;
        } else {
            ek = '';
        }

        // Kaynak değiştirme (ASP.NET $$& placeholder trick)
        yol = yol.replaceAll(':' + refUrunAdi + '-', ':' + sourcePlaceholder + '-');
        yol = yol.replaceAll(':' + sourcePlaceholder + '-', ':' + deriveNextName(refUrunAdi, sil, ekle) + ek + '-');

        // Başındaki ':' kaldır
        if (yol.startsWith(':')) yol = yol.substring(1);

        // Aynı segmentteki çıkış (hedef) ürünü bul
        const altyollar = yol.split(':');
        let cikis = '';
        for (const altyol of altyollar) {
            const giris = altyol.split('-')[0];
            cikis = altyol.split('-')[1];
            if (giris === deriveNextName(refUrunAdi, sil, ekle) + ek) break;
        }

        if (cikis) {
            if (deriveNextName(cikis, sil, ekle) === cikis) {
                ek = ekle;
            } else {
                ek = '';
            }
            yol = yol.replaceAll('-' + cikis + '-', '-' + targetPlaceholder + '-');
            yol = yol.replaceAll('-' + targetPlaceholder + '-', '-' + deriveNextName(cikis, sil, ekle) + ek + '-');

            return sonrakiUrunAdlariBul(cikis, yol, sil, ekle);
        }
    }

    if (yol.startsWith(':')) yol = yol.substring(1);
    return yol;
}

function deriveNextName(name, sil, ekle) {
    if (!sil) return name + ekle;

    const replaced = name.split(sil).join(ekle);
    return replaced === name ? name + ekle : replaced;
}

// Manuel ekle (Giriş-Hedef-Adet)
function derivePathEkle() {
    const girisText = document.getElementById('deriveGiris_text').value;
    const hedefText = document.getElementById('deriveHedef_text').value;
    const adet = document.getElementById('deriveAdet').value || '1';

    if (!girisText || !hedefText) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Giriş ve hedef ara ürün seçiniz.' });
        return;
    }

    const yolEl = document.getElementById('deriveYol');
    let yol = yolEl.value.trim();
    if (yol) yol += ':';
    yol += girisText + '-' + hedefText + '-' + adet;
    yolEl.value = yol;
    updatePathPreview('deriveYol', 'derivePathPreview');
}

function derivePathGeri() {
    const yolEl = document.getElementById('deriveYol');
    let yol = yolEl.value.trim();
    if (yol.includes(':')) {
        yol = yol.substring(0, yol.lastIndexOf(':'));
    } else {
        yol = '';
    }
    yolEl.value = yol;
    updatePathPreview('deriveYol', 'derivePathPreview');
}

function derivePathTemizle() {
    document.getElementById('deriveYol').value = '';
    updatePathPreview('deriveYol', 'derivePathPreview');
}

// Kaydet (YeniUrunDuzenle Button1_Click karşılığı)
async function kaydetTuretme() {
    if (!(await ensureDeriveSourceSelected())) return;

    const newNamePath = document.getElementById('deriveYol').value.trim();
    if (!newNamePath) {
        Swal.fire({ icon: 'warning', title: 'Eksik', text: 'Üretim yolu boş olamaz.' });
        return;
    }
    if (newNamePath === deriveOriginalPath) {
        Swal.fire({ icon: 'warning', title: 'Değişiklik Yok', text: 'Önce Türet butonuyla BOM yolunu değiştiriniz.' });
        return;
    }

    let resim = document.getElementById('deriveResim').value;
    if (document.getElementById('deriveUrl').value.trim()) resim = document.getElementById('deriveUrl').value.trim();
    const fileInput = document.getElementById('deriveDosya');

    try {
        Swal.fire({ title: 'Türetiliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
        const payload = {
            source_id: deriveSourceId,
            new_name_path: newNamePath,
            image: resim
        };
        const requestOptions = {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }
        };

        if (fileInput.files.length > 0) {
            const fd = new FormData();
            Object.entries(payload).forEach(([key, value]) => fd.append(key, value ?? ''));
            fd.append('image_file', fileInput.files[0]);
            requestOptions.body = fd;
        } else {
            requestOptions.headers['Content-Type'] = 'application/json';
            requestOptions.body = JSON.stringify(payload);
        }

        const resp = await fetch('/api/database/derive-product', requestOptions);

        const data = await resp.json();
        Swal.close();

        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Başarılı', text: data.message || 'Ürün türetildi.' });
            // Listeleri yenile
            document.getElementById('deriveUrl').value = '';
            document.getElementById('deriveDosya').value = '';
            previewImageMode('derive');
            loadInitialData();
        } else {
            Swal.fire({ icon: 'error', title: 'Hata', text: data.message || 'Türetme başarısız.' });
        }
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Hata', text: e.message });
    }
}

// ════════════════════════════════════════════
// SEKME 3: ÜRÜN BİRLEŞTİRME
// ════════════════════════════════════════════

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function mergeItemsForType() {
    const type = document.getElementById('mergeType')?.value || 'component';
    const sourceItems = type === 'product'
        ? allProducts
        : allComponents.filter(c => Number(c.id) !== 1373);

    return sourceItems
        .filter(item => Number(item.id) > 0)
        .sort((a, b) => String(a.name || a.system_name || '').localeCompare(String(b.name || b.system_name || ''), 'tr'))
        .map(item => {
            const name = item.name || item.system_name || '';
            return {
                value: item.id,
                text: `${name || 'Ürün'} (#${item.id})`
            };
        });
}

function onMergeTypeChanged() {
    const items = mergeItemsForType();
    ['ssMergeSource', 'ssMergeTarget'].forEach(wrapperId => {
        const wrapper = document.getElementById(wrapperId);
        if (wrapper && typeof wrapper._updateItems === 'function') {
            wrapper._updateItems(items);
        }
        clearSearchableSelect(wrapperId);
    });
    updateMergeLinkedOption();
    resetMergePreview();
    updateMergeSelectedInfo();
}

function updateMergeLinkedOption() {
    const type = document.getElementById('mergeType')?.value || 'component';
    const option = document.getElementById('mergeLinkedOption');
    const checkbox = document.getElementById('mergeLinkedComponent');
    if (!option || !checkbox) return;

    const isProduct = type === 'product';
    option.style.display = isProduct ? 'block' : 'none';
    if (!isProduct) checkbox.checked = false;
}

function onMergeLinkedOptionChanged() {
    resetMergePreview();
    updateMergeSelectedInfo();
}

function clearSearchableSelect(wrapperId) {
    const wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    const search = wrapper.querySelector('.ss-search');
    const dropdown = wrapper.querySelector('.ss-dropdown');
    const hiddenInputs = wrapper.querySelectorAll('input[type=hidden]');

    if (search) search.value = '';
    if (dropdown) dropdown.classList.remove('open');
    hiddenInputs.forEach(input => input.value = '');
}

function resetMergeForm() {
    clearSearchableSelect('ssMergeSource');
    clearSearchableSelect('ssMergeTarget');
    const checkbox = document.getElementById('mergeLinkedComponent');
    if (checkbox) checkbox.checked = false;
    updateMergeLinkedOption();
    resetMergePreview();
    updateMergeSelectedInfo();
}

function resetMergePreview() {
    mergePreviewData = null;
    const panel = document.getElementById('mergePreviewPanel');
    const executeBtn = document.getElementById('mergeExecuteBtn');
    if (panel) panel.style.display = 'none';
    if (executeBtn) executeBtn.disabled = true;
}

function updateMergeSelectedInfo() {
    const sourceText = document.getElementById('mergeSource_text')?.value || '';
    const targetText = document.getElementById('mergeTarget_text')?.value || '';
    const typeLabel = document.getElementById('mergeType')?.selectedOptions?.[0]?.textContent || '';
    const info = document.getElementById('mergeSelectedInfo');
    if (!info) return;

    if (!sourceText && !targetText) {
        info.textContent = 'Kaynak ve hedef ürün seçilmedi.';
        return;
    }

    info.innerHTML = [
        `<strong>${escHtml(typeLabel)}</strong>`,
        `Kaynak: <strong>${escHtml(sourceText || '-')}</strong>`,
        `Hedef: <strong>${escHtml(targetText || '-')}</strong>`,
        mergeLinkedComponentRequested() ? '<strong>Kök ara ürün dahil</strong>' : ''
    ].filter(Boolean).join(' · ');
}

function mergeLinkedComponentRequested() {
    return (document.getElementById('mergeType')?.value || 'component') === 'product'
        && Boolean(document.getElementById('mergeLinkedComponent')?.checked);
}

function mergeLinkedComponentLabel(preview) {
    if (!preview?.include_linked_component || !preview?.linked_component) return '';

    const sourceName = preview.linked_component.source?.name || `#${preview.linked_component.source?.id || ''}`;
    const targetName = preview.linked_component.target?.name || `#${preview.linked_component.target?.id || ''}`;

    return `Kök ara ürün dahil: ${sourceName} → ${targetName}`;
}

function getMergePayload() {
    const mergeType = document.getElementById('mergeType')?.value || 'component';
    const sourceId = parseInt(document.getElementById('mergeSource_value')?.value || '0', 10);
    const targetId = parseInt(document.getElementById('mergeTarget_value')?.value || '0', 10);

    if (sourceId <= 0 || targetId <= 0) {
        Swal.fire({ icon: 'warning', title: 'Eksik Seçim', text: 'Kaynak ve hedef ürün seçiniz.' });
        return null;
    }

    if (sourceId === targetId) {
        Swal.fire({ icon: 'warning', title: 'Geçersiz Seçim', text: 'Kaynak ve hedef aynı kayıt olamaz.' });
        return null;
    }

    return {
        merge_type: mergeType,
        source_id: sourceId,
        target_id: targetId,
        include_linked_component: mergeLinkedComponentRequested()
    };
}

async function birlesmeOnizle() {
    const payload = getMergePayload();
    if (!payload) return;

    try {
        Swal.fire({ title: 'Önizleme hazırlanıyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const resp = await fetch('/api/database/products/merge-preview', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(payload)
        });

        const data = await resp.json();
        Swal.close();

        if (!resp.ok || !data.success) {
            throw new Error(data.message || 'Önizleme alınamadı.');
        }

        mergePreviewData = data.preview;
        renderMergePreview(mergePreviewData);
    } catch (e) {
        Swal.close();
        resetMergePreview();
        Swal.fire({ icon: 'error', title: 'Önizleme Hatası', text: e.message });
    }
}

function renderMergePreview(preview) {
    if (!preview) return;

    const panel = document.getElementById('mergePreviewPanel');
    const title = document.getElementById('mergePreviewTitle');
    const subtitle = document.getElementById('mergePreviewSubtitle');
    const total = document.getElementById('mergePreviewTotal');
    const sections = document.getElementById('mergePreviewSections');
    const warnings = document.getElementById('mergePreviewWarnings');
    const executeBtn = document.getElementById('mergeExecuteBtn');

    const sourceName = preview.source?.name || `#${preview.source?.id || ''}`;
    const targetName = preview.target?.name || `#${preview.target?.id || ''}`;

    title.innerHTML = `${escHtml(sourceName)} <span style="color:var(--z-text-muted);">→</span> ${escHtml(targetName)}`;
    subtitle.innerHTML = [
        escHtml(preview.type_label || ''),
        mergeLinkedComponentLabel(preview) ? escHtml(mergeLinkedComponentLabel(preview)) : ''
    ].filter(Boolean).join(' · ');
    total.textContent = `${Number(preview.total_count || 0).toLocaleString('tr-TR')} kayıt`;

    const previewSections = preview.sections || [];
    sections.innerHTML = previewSections.length > 0
        ? previewSections.map(section => `
            <div class="merge-count-card">
                <div class="merge-count-label">${escHtml(section.label || '')}</div>
                <div class="merge-count-value">${Number(section.count || 0).toLocaleString('tr-TR')}</div>
                ${mergeSectionDetailHtml(section.details || {})}
            </div>
        `).join('')
        : `<div class="merge-count-card">
                <div class="merge-count-label">Etkilenecek kayıt</div>
                <div class="merge-count-value">0</div>
           </div>`;

    const warningItems = preview.warnings || [];
    if (warningItems.length > 0) {
        warnings.style.display = 'block';
        warnings.innerHTML = warningItems.map(item => `<div><i class="bi bi-exclamation-triangle"></i> ${escHtml(item.message || '')}</div>`).join('');
    } else {
        warnings.style.display = 'none';
        warnings.innerHTML = '';
    }

    panel.style.display = 'block';
    executeBtn.disabled = false;
}

function mergeSectionDetailHtml(details) {
    if (!details || Object.keys(details).length === 0) return '';

    const parts = [];
    if (details.merge_rows !== undefined) parts.push(`birleşecek satır: ${Number(details.merge_rows || 0).toLocaleString('tr-TR')}`);
    if (details.move_rows !== undefined) parts.push(`taşınacak satır: ${Number(details.move_rows || 0).toLocaleString('tr-TR')}`);
    if (details.quantity !== undefined) parts.push(`toplam adet: ${Number(details.quantity || 0).toLocaleString('tr-TR')}`);

    return parts.length > 0
        ? `<div class="merge-count-detail">${parts.map(escHtml).join('<br>')}</div>`
        : '';
}

async function birlesmeyiUygula() {
    if (!mergePreviewData) {
        Swal.fire({ icon: 'warning', title: 'Önizleme Gerekli', text: 'Önce birleştirme önizlemesi alın.' });
        return;
    }

    const payload = getMergePayload();
    if (!payload) return;

    const result = await Swal.fire({
        title: 'Birleştirme yapılsın mı?',
        html: mergeConfirmationHtml(mergePreviewData),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, birleştir',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#0d9488'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({ title: 'Birleştiriliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        const resp = await fetch('/api/database/products/merge', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify({ ...payload, confirm: true })
        });

        const data = await resp.json();
        Swal.close();

        if (!resp.ok || !data.success) {
            throw new Error(data.message || 'Birleştirme tamamlanamadı.');
        }

        Swal.fire({ icon: 'success', title: 'Tamamlandı', text: data.message || 'Ürün birleştirildi.' });
        resetMergeForm();
        await loadInitialData();
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Birleştirme Hatası', text: e.message });
    }
}

function mergeConfirmationHtml(preview) {
    const sourceName = preview.source?.name || `#${preview.source?.id || ''}`;
    const targetName = preview.target?.name || `#${preview.target?.id || ''}`;
    const sectionLines = (preview.sections || [])
        .filter(section => Number(section.count || 0) > 0)
        .map(section => `<li>${escHtml(section.label || '')}: <strong>${Number(section.count || 0).toLocaleString('tr-TR')}</strong></li>`)
        .join('');

    return `<div style="text-align:left; line-height:1.55;">
        <div><strong>Kaynak:</strong> ${escHtml(sourceName)}</div>
        <div><strong>Hedef:</strong> ${escHtml(targetName)}</div>
        ${preview.include_linked_component && preview.linked_component ? `<div><strong>Kök ara ürün:</strong> ${escHtml(preview.linked_component.source?.name || '-')} → ${escHtml(preview.linked_component.target?.name || '-')}</div>` : ''}
        <div style="margin-top:10px;"><strong>Etkilenecek kayıt:</strong> ${Number(preview.total_count || 0).toLocaleString('tr-TR')}</div>
        ${sectionLines ? `<ul style="margin:8px 0 0; padding-left:20px;">${sectionLines}</ul>` : ''}
    </div>`;
}

// ════════════════════════════════════════════
// YARDIMCI FONKSİYONLAR
// ════════════════════════════════════════════

// Yol önizleme çipleri
function updatePathPreview(textareaId, previewId) {
    const yol = document.getElementById(textareaId).value.trim();
    const container = document.getElementById(previewId);

    if (!yol) { container.innerHTML = ''; return; }

    const segments = yol.split(':');
    container.innerHTML = segments.map(seg => {
        const parts = seg.split('-');
        if (parts.length >= 3) {
            // "Kaynak-Hedef-Adet" format (türetme)
            return `<div class="path-chip"><span>${escHtml(parts[0])}</span><span class="chip-sep">→</span><span>${escHtml(parts[1])}</span><span class="chip-sep">×</span><span>${parts[2]}</span></div>`;
        } else if (parts.length === 2) {
            // "Ürün-Adet" format (yeni ekleme)
            return `<div class="path-chip"><span>${escHtml(parts[0])}</span><span class="chip-sep">×</span><span>${parts[1]}</span></div>`;
        }
        return `<div class="path-chip">${escHtml(seg)}</div>`;
    }).join('');
}

// Resim dosya önizleme
function previewFileAdd(event) { previewFile(event, 'addImgPreview', 'addImgLabel'); }
function previewFileDerive(event) { previewFile(event, 'deriveImgPreview', 'deriveImgLabel'); }

function previewFile(event, imgId, labelId) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById(imgId);
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById(labelId).textContent = 'Yüklenecek dosya: ' + file.name;
    };
    reader.readAsDataURL(file);
}

// HTML escape
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Info Modal
function showInfo(type) {
    if (type === 'derive') {
        Swal.fire({
            title: 'Türetme Nasıl Çalışır?',
            html: `<div style="text-align: left; font-size: 0.9rem; line-height: 1.6;">
                    <ol>
                        <li><strong>Nihai ürün</strong> seçin (üst bölümde)</li>
                        <li>Sistem ürünün tüm BOM ağacını yükler</li>
                        <li><strong>Değiştirilecek Ara Ürün</strong> seçin</li>
                        <li><strong>Silinecek</strong> ve <strong>Eklenecek</strong> metin girin</li>
                        <li><strong>Türet</strong> butonuna tıklayın — yoldaki isimler otomatik güncellenir</li>
                        <li><strong>Kaydet</strong>'e tıklayarak yeni bileşenleri oluşturun</li>
                    </ol>
                    <div style="margin-top: 10px; padding: 10px; background: #fef3c7; color: #92400e; border-radius: 8px;">
                        <strong>Örnek:</strong> "Koltuk-Mavi" ürünündeki "Mavi" silinip "Kırmızı" eklenerek "Koltuk-Kırmızı" türetilir.
                    </div>
                   </div>`,
            icon: 'info'
        });
    } else {
        Swal.fire({
            title: 'Nasıl Kullanılır?',
            html: `<div style="text-align: left; font-size: 0.9rem; line-height: 1.6;">
                    <ol>
                        <li>Ürün adını ve temel bilgilerini girin</li>
                        <li><strong>Öncül Ürün</strong> listesinden bir bileşen seçin</li>
                        <li><strong>Adet</strong> değerini girin ve <strong>Ekle</strong>'ye tıklayın</li>
                        <li>Bu adımları tüm bileşenler için tekrarlayın</li>
                        <li>Resim seçin veya yükleyin</li>
                        <li><strong>Kaydet</strong> butonuna tıklayarak ürünü kaydedin</li>
                    </ol>
                    <div style="margin-top: 10px; padding: 10px; background: #f0fdfa; color: #0f766e; border-radius: 8px;">
                        <strong>Yol Formatı:</strong> <code style="background: rgba(0,0,0,0.05); padding: 2px 6px; border-radius: 4px;">ÖncülAdı-Adet:ÖncülAdı-Adet</code>
                    </div>
                   </div>`,
            icon: 'info'
        });
    }
}

// ════════════════════════════════════════════
// BOM AĞACI ÇİZİMİ (ASP.NET drawPattern)
// ════════════════════════════════════════════
let diagPoints = {};
let diagLines = [];
const nodeRadius = 25;

function cizDiyagram(textareaId, panelId) {
    const pattern = document.getElementById(textareaId).value.trim();
    const panel = document.getElementById(panelId);

    if (!pattern) {
        Swal.fire({ icon: 'info', title: 'Bilgi', text: 'Çizim yapılacak BOM yolu bulunamadı.' });
        return;
    }

    panel.innerHTML = '';
    diagPoints = {};
    diagLines = [];

    const segments = pattern.split(':');

    // Ağaç yapısını oluşturmak için düğümleri ve kenarları analiz et
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

            nodes[start].out.push(end); // start is input for end
            nodes[end].in.push(start);
            edgesList.push({ start, end, adet });
        }
    });

    // Kök düğümleri (çıkışı olmayan nihai ürünler) bul
    let roots = Object.keys(nodes).filter(n => nodes[n].out.length === 0);
    if(roots.length === 0) roots = [Object.keys(nodes)[0]];

    // BFS ile seviyeleri belirle (Hiyerarşi)
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

    // Seviyelere göre grupla ve koordinat ata
    let levelGroups = {};
    Object.keys(nodes).forEach(n => {
        let lvl = nodes[n].level !== undefined ? nodes[n].level : 0;
        if(!levelGroups[lvl]) levelGroups[lvl] = [];
        if(!levelGroups[lvl].includes(n)) levelGroups[lvl].push(n);
    });

    let maxLevel = Math.max(...Object.keys(levelGroups).map(Number));
    let ySpacing = 120;

    // Panel genişliğine göre X aralığını dinamik belirle (taşmayı önle)
    let panelWidth = panel.clientWidth || 800; // default to 800 if not visible yet
    let nodeOuterPadding = 80; // Sol ve sağ kenardan boşluk payı
    let xSpacing = 160;
    let startX = nodeOuterPadding;

    // Eğer ağaç panel boyutundan büyükse (taşıyorsa), otomatik sıkıştır
    let requiredWidth = startX * 2 + (maxLevel * xSpacing);
    if (requiredWidth > panelWidth) {
        xSpacing = (panelWidth - (nodeOuterPadding * 2)) / (maxLevel || 1);
        xSpacing = Math.max(xSpacing, 90); // Minumum sınır (çok ezilmesin)
    } else {
        // Eğer küçükse yatayda merkeze oturt
        startX = (panelWidth - (maxLevel * xSpacing)) / 2;
    }

    Object.keys(levelGroups).forEach(lvl => {
        let items = levelGroups[lvl];
        // Dikey hizalamayı merkeze al
        let startY = 100 + ((maxLevel + 1 - items.length) * ySpacing / 2);
        items.forEach((n, idx) => {
            // maxLevel sol tarafta (girişler), 0 (kök) sağ tarafta
            let x = startX + (maxLevel - Number(lvl)) * xSpacing;
            let y = Math.max(80, startY + (idx * ySpacing));
            diagPoints[n] = { x: x, y: y, element: null };
            cizNode(panel, x, y, n);
        });
    });

    // Kenarları Çiz
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

    // Minimal display, tamad CSS attr'de saklanıyor
    node.setAttribute('data-fullname', label);
    node.innerText = label.length > 6 ? label.substring(0,5) + '..' : label;
    node.draggable = true;

    node.ondragstart = function (event) {
        event.dataTransfer.setData("text/plain", label);
    };

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

// Pan & Zoom Scroll
document.querySelectorAll('.diagram-panel').forEach(panel => {
    let scale = 1;
    panel.addEventListener('wheel', function (e) {
        if (!e.ctrlKey) return; // Only zoom with Ctrl
        e.preventDefault();
        const zoomStep = 0.1;
        const delta = e.deltaY > 0 ? -zoomStep : zoomStep;
        scale = Math.min(Math.max(scale + delta, 0.4), 2.5);

        // CSS transform uygulandığında sürükleme hesabı bozulabilir, bu yüzden içeriği transform ederiz.
        // Şimdilik sadece bilgilendirme eklendi
    });
});

// ════════════════════════════════════════════
// BAŞLAT
// ════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    const tabParam = new URLSearchParams(window.location.search).get('tab');
    const initialTab = ['derive', 'merge'].includes(tabParam) ? tabParam : 'add';
    switchTab(initialTab, false);
    loadInitialData();
});
</script>
@endpush
