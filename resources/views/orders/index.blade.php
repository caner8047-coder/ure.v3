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
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
                display: none; overflow-x: auto;
                background: var(--z-bg-card); border-radius: var(--z-radius); border: 1px solid var(--z-border);
            }
            .grid-container table { width: 100%; min-width: 1200px; border-collapse: collapse; font-size: 0.82rem; table-layout: fixed; }
            .grid-container thead th {
                background: var(--z-bg-soft); color: var(--z-text-secondary);
                padding: 8px 8px; text-align: left;
                font-weight: 700; font-size: 0.7rem; letter-spacing: 0.06em; text-transform: uppercase;
                position: sticky; top: 0; z-index: 10; white-space: nowrap;
                border-bottom: 1px solid var(--z-border);
                overflow: hidden; text-overflow: ellipsis;
            }
            .grid-container tbody tr { border-bottom: 1px solid var(--z-border-light); transition: background 0.15s; }
            .grid-container tbody tr:hover { background: var(--z-bg-soft); }
            .grid-container tbody tr.selected-row { background: var(--z-accent-soft) !important; }
            .grid-container tbody tr.is-emri-verildi { background: var(--z-success-soft); opacity: 0.8; }
            .grid-container tbody tr.pasif { background: var(--z-bg-soft); opacity: 0.5; }
            .grid-container tbody tr.urgency-critical { background: var(--z-danger-soft) !important; }
            .grid-container tbody tr.urgency-warning { background: var(--z-warning-soft) !important; }
            .grid-container tbody tr.set-parent-row { background: var(--z-warning-soft) !important; border-left: 3px solid var(--z-warning); }
            .grid-container tbody tr.set-child-row { background: rgba(139,92,246,0.05) !important; border-left: 3px solid #8b5cf6; display: none; }
            .grid-container tbody tr.set-child-row.show { display: table-row; }
            .grid-container tbody tr.set-child-row td:first-child { padding-left: 20px; }
            .grid-container td { padding: 7px 8px; vertical-align: middle; color: var(--z-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 0; }

            /* Badges */
            .badge-bekliyor { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
            .badge-verildi { background: var(--z-success-soft); color: var(--z-success); padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
            .badge-pasif { background: var(--z-bg-soft); color: var(--z-text-muted); padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
            .badge-uretimde { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; display: inline-block; white-space: nowrap; }
            .badge-set-parent { background: var(--z-warning-soft); color: var(--z-warning); padding: 3px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
            .badge-set-child { background: rgba(139,92,246,0.1); color: #7c3aed; padding: 2px 8px; border-radius: 4px; font-size: 0.65rem; font-weight: 600; }
            .bilesen-adet-badge { background: var(--z-warning); color: #fff; border-radius: 50%; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 700; margin-right: 4px; }
            .badge-stok { background: rgba(37,99,235,0.08); color: #2563eb; padding: 3px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }

            /* Match */
            .match-ok { color: var(--z-success); font-weight: 600; font-size: 0.78rem; }
            .match-warn { color: var(--z-warning); font-weight: 600; font-size: 0.78rem; }
            .match-fail { color: var(--z-danger); font-weight: 600; font-size: 0.78rem; }
            .match-select { width: 100%; font-size: 0.8rem; padding: 4px 8px; border: 1px solid var(--z-border); border-radius: var(--z-radius-sm); }

            .note-icon { cursor: help; color: var(--z-accent); font-size: 0.9rem; margin-left: 4px; }

            /* Stok */
            .stok-yeterli { color: var(--z-success); font-weight: 700; font-size: 0.85rem; }
            .stok-kismi { color: var(--z-warning); font-weight: 700; font-size: 0.85rem; }
            .stok-yok { color: var(--z-danger); font-weight: 600; font-size: 0.85rem; }
            .btn-stok { background: var(--z-accent); color: #fff; border: none; padding: 3px 8px; border-radius: var(--z-radius-sm); font-size: 0.72rem; cursor: pointer; font-weight: 600; margin-top: 3px; display: block; width: 100%; }
            .btn-stok:hover { opacity: 0.85; }

            .btn-row-action { background: var(--z-success); color: #fff; border: none; padding: 5px 12px; border-radius: var(--z-radius-sm); font-size: 0.75rem; cursor: pointer; font-weight: 600; white-space: nowrap; }
            .btn-row-action:hover { opacity: 0.85; }
            .btn-row-action:disabled { background: var(--z-border); cursor: not-allowed; }

            /* Sort */
            th.sortable { cursor: pointer; user-select: none; position: relative; padding-right: 18px !important; }
            th.sortable:hover { background: rgba(0,0,0,0.03); }
            th.sortable::after { content: '⇅'; position: absolute; right: 3px; top: 50%; transform: translateY(-50%); font-size: 0.65rem; opacity: 0.4; }
            th.sortable.sort-asc::after { content: '▲'; opacity: 1; color: var(--z-accent); }
            th.sortable.sort-desc::after { content: '▼'; opacity: 1; color: var(--z-accent); }

            /* Select2 */
            .select2-container { max-width: 100% !important; width: 100% !important; }
            .select2-container--default .select2-selection--single { border: 1px solid var(--z-border) !important; border-radius: var(--z-radius-sm) !important; height: 32px !important; font-size: 0.8rem !important; }
            .select2-container--default .select2-selection--single .select2-selection__rendered { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-right: 20px; line-height: 30px !important; }
            .select2-container--default .select2-selection--single .select2-selection__arrow { height: 30px !important; }

            /* Pagination */
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
                .pagination-container { flex-direction: column; gap: 8px; padding: 10px 12px; }
                .pagination-controls { flex-wrap: wrap; justify-content: center; }
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
                <div class="stat-label">Yüklenen toplam sipariş satırı</div>
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
                <button type="button" class="btn-filter" onclick="loadOrders()"><i class="fa-solid fa-search"></i>
                    Filtrele</button>
                <button type="button" class="btn-reset" onclick="resetFilters()"><i class="fa-solid fa-rotate-left"></i>
                    Sıfırla</button>
                <button type="button" id="btnOtoEslestir"
                    style="background: linear-gradient(135deg, #8e44ad, #6c3483); color: #fff; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; white-space: nowrap;"
                    onclick="rematchOrders()" onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'"><i class="fa-solid fa-wand-magic-sparkles"></i>
                    Oto Eşleştir</button>
                <button type="button"
                    style="background: linear-gradient(135deg, #c0392b, #96281b); color: #fff; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.2s; margin-left: auto; white-space: nowrap;"
                    onclick="clearAllOrders()" onmouseover="this.style.opacity='0.85'"
                    onmouseout="this.style.opacity='1'"><i class="fa-solid fa-trash-can"></i>
                    Her Şeyi Sıfırla</button>
            </div>
        </div>

        <!-- Grid Toolbar -->
        <div class="grid-toolbar" id="gridToolbar" style="display:none;">
            <div class="left">
                <span class="selected-info"><span id="selectedCount">0</span> satır seçili</span>
                <button type="button" class="btn-action" id="btnTopluIsEmri" onclick="submitBulkWorkOrders()" disabled>
                    <i class="fa-solid fa-paper-plane"></i> Seçilenlere Toplu İş Emri Ver
                </button>
                <button type="button" class="btn-action" id="btnTopluIptal" onclick="cancelBulkWorkOrders()" disabled
                    style="background:#c0392b; color:#fff; border-color:#a93226;">
                    <i class="fa-solid fa-xmark"></i> Seçilen İş Emirlerini İptal Et
                </button>
                <button type="button" class="btn-action" id="btnTopluStok" onclick="deductStockBulk()" disabled
                    style="background: linear-gradient(135deg, #2196F3, #1976D2);">
                    <i class="fa-solid fa-box-open"></i> Seçilenleri Stoktan Düş
                </button>
            </div>
        </div>

        <!-- Grid -->
        <div class="grid-container" id="gridContainer" style="display:none;">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th style="width:2.5%"><input type="checkbox" id="masterCheckbox"
                                onchange="toggleAllRows(this)" /></th>
                        <th style="width:2.5%">#</th>
                        <th style="width:7%" class="sortable" data-sort="siparisNo"
                            onclick="sortColumn('siparisNo')">Sip. No</th>
                        <th style="width:5%" class="sortable" data-sort="pazaryeri"
                            onclick="sortColumn('pazaryeri')">Pazar</th>
                        <th style="width:7%" class="sortable" data-sort="musteri" onclick="sortColumn('musteri')">
                            Müşteri</th>
                        <th style="width:15%" class="sortable" data-sort="urunAdi" onclick="sortColumn('urunAdi')">Ürün
                        </th>
                        <th style="width:3.5%" class="sortable" data-sort="adet" onclick="sortColumn('adet')">Adet</th>
                        <th style="width:4.5%" class="sortable" data-sort="stokAdet" onclick="sortColumn('stokAdet')">
                            Stok</th>
                        <th style="width:5%" class="sortable" data-sort="uretimdeAdet"
                            onclick="sortColumn('uretimdeAdet')">Üretim</th>
                        <th style="width:7.5%" class="sortable" data-sort="siparisTarihi"
                            onclick="sortColumn('siparisTarihi')">Sip. Tarihi</th>
                        <th style="width:7.5%" class="sortable" data-sort="kargoSonTeslim"
                            onclick="sortColumn('kargoSonTeslim')">Kargo Son</th>
                        <th style="width:5.5%" class="sortable" data-sort="durum" onclick="sortColumn('durum')">Durum
                        </th>
                        <th style="width:5%" class="sortable" data-sort="eslesmePuani"
                            onclick="sortColumn('eslesmePuani')">Eşleş</th>
                        <th style="width:16%">DB Ürün</th>
                        <th style="width:6.5%">Aksiyon</th>
                    </tr>
                </thead>
                <tbody id="ordersBody"></tbody>
            </table>

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
                            class="fa-solid fa-chevron-left"></i> Önceki</button>
                    <div class="page-numbers" id="pageNumbers"></div>
                    <button class="btn-page" id="btnNextPage" onclick="nextPage()">Sonraki <i
                            class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div class="empty-state" id="emptyState">
            <i class="fa-solid fa-inbox"></i>
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

            // Sayfa yüklendiğinde
            $(document).ready(function () {
                loadProducts(function () {
                    loadOrders();
                });
                setupDragDrop();
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
                    processExcelFile(input.files[0]);
                }
            }

            function processExcelFile(file) {
                if (!file.name.match(/\.xlsx?$/i)) {
                    Swal.fire({ icon: 'warning', title: 'Geçersiz Dosya', text: 'Lütfen .xlsx uzantılı Excel dosyası seçin.', confirmButtonColor: '#d4af37' });
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

                            Swal.fire({
                                icon: 'success',
                                title: 'Siparişler Yüklendi!',
                                html: '<b>' + res.inserted + '</b> yeni eklendi<br>' +
                                    '<b>' + res.updated + '</b> güncellendi<br>' +
                                    '<b>' + res.passivated + '</b> pasifleştirildi<br>' +
                                    '<b>' + res.matched + '</b> ürün eşleşti, <b>' + res.unmatched + '</b> eşleşmedi',
                                confirmButtonColor: '#4b9460'
                            }).then(function () {
                                // Listede olmayan ama iş emri verilmiş siparişler var mı?
                                if (pendingWO.length > 0) {
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
                                            var nolar = pendingWO.map(function (pw) { return pw.no; });
                                            Swal.fire({ title: 'İptal ediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                                            $.ajax({
                                                url: apiUrl + '?action=passivateWithWorkOrderCancel',
                                                type: 'POST',
                                                contentType: 'application/json',
                                                data: JSON.stringify({ satirNolar: nolar }),
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
                                                data: JSON.stringify({ satirNolar: nolar, yeniDurum: 'PasifDevamEden', yeniAktif: 0 }),
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
                            allOrders = res.orders || [];
                            updateStats(res.stats);
                            updateFilterDropdowns(res.filters);
                            sortColumn('siparisTarihi', 'desc'); // Sorting ensures stable pagination
                            showGridUI(allOrders.length > 0);
                        }
                    },
                    error: function () {
                        // İlk yükleme — tablo henüz yoksa sessizce devam
                    }
                });
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

            function showGridUI(hasData) {
                document.getElementById('filterBar').style.display = hasData ? 'flex' : 'none';
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
                var numericFields = ['adet', 'stokAdet', 'uretimdeAdet', 'eslesmePuani', 'no'];
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
                selectedNos.clear();
                document.getElementById('masterCheckbox').checked = false;
                updateSelectedCount();

                // 1) Sadece parent satırları ve normal satırları al (childs hariç)
                var mainOrders = allOrders.filter(function (o) {
                    return o.anaSetSatirNo === 0;
                });

                // 2) Pagination uygula
                renderPagination(mainOrders.length);
                var displayOrders = mainOrders.slice((currentPage - 1) * pageSize, currentPage * pageSize);

                // DB ürünlerinden seçenek listesi (composite key: Tur_No)
                var optionsHtml = '<option value="">-- Manuel Seç --</option>';
                dbProducts.forEach(function (p) {
                    var label = p.sistemAdi || p.urunId || ('Ürün #' + p.no);
                    var compositeVal = p.tur + '_' + p.no;
                    optionsHtml += '<option value="' + compositeVal + '">' + escapeHtml(label) + '</option>';
                });

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

                    // Durum badge
                    var durumHtml = '';
                    if (order.setMi) {
                        // Set parent — bileşen sayısını göster
                        var childCount = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; }).length;
                        durumHtml = '<span class="badge-set-parent" onclick="toggleSetChildren(' + order.no + ')" title="' + childCount + ' bileşen - tıkla genişlet">' +
                            '📦 SET (' + childCount + ') ▼</span>';
                    } else if (order.durum === 'UretimBekliyor') durumHtml = '<span class="badge-bekliyor">🟡 Bekliyor</span>';
                    else if (order.durum === 'IsEmriVerildi') durumHtml = '<span class="badge-verildi">🟢 Verildi</span>';
                    else if (order.durum === 'PasifDevamEden') durumHtml = '<span class="badge-bekliyor" style="color: #d97706; background: rgba(217,119,6,0.1);">🔸 Devam Eden</span>';
                    else if (order.durum === 'UretimdenKarsilaniyor') durumHtml = '<span style="background: #e8daef; color: #8e44ad; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block;">🔗 Rezerve</span>';
                    else if (order.durum === 'StokKarsilandi') durumHtml = '<span class="badge-stok">📦 Stoktan</span>';
                    else durumHtml = '<span class="badge-pasif">⚪ Pasif</span>';

                    // Eşleşme
                    var matchHtml = '';
                    if (order.setMi) {
                        matchHtml = '<span style="color:#e67e22; font-weight:600;">Set</span>';
                    } else if (order.eslesmePuani >= 80) matchHtml = '<span class="match-ok">✅ %' + order.eslesmePuani + '</span>';
                    else if (order.eslesmePuani >= 40) matchHtml = '<span class="match-warn">⚠️ %' + order.eslesmePuani + '</span>';
                    else matchHtml = '<span class="match-fail">❌ Yok</span>';

                    // Müşteri notu
                    var musteriNoteHtml = '';
                    if (order.musteriNotu) {
                        musteriNoteHtml = ' <i class="fa-solid fa-sticky-note note-icon" title="' + escapeHtml(order.musteriNotu) + '"></i>';
                    }

                    // Stok kolonu
                    var stokAdet = order.stokAdet || 0;
                    var stokClass = stokAdet >= order.adet ? 'stok-yeterli' : (stokAdet > 0 ? 'stok-kismi' : 'stok-yok');
                    var stokBtnHtml = '';
                    if (stokAdet >= order.adet && order.durum === 'UretimBekliyor' && order.eslesenUrunNo > 0) {
                        stokBtnHtml = '<button type="button" class="btn-stok" onclick="deductStock(event, ' + order.no + ')">Stoktan Düş</button>';
                    }
                    var stokHtml = order.setMi ? '<span style="color:#888">—</span>' : '<span class="' + stokClass + '">' + stokAdet + '</span>' + stokBtnHtml;

                    // Üretimde kolonu
                    var uretimdeAdet = order.uretimdeAdet || 0;
                    var uretimdeHtml = order.setMi ? '<span style="color:#888">—</span>'
                        : (uretimdeAdet > 0
                            ? '<span class="badge-uretimde">🔨 ' + uretimdeAdet + '</span>'
                            : '<span style="color:#aaa;">—</span>');

                    // Toplu aksiyon ve eşleştirme sadece üretim bekleyen aktif siparişlerde açık kalır
                    var cbDisabled = order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.durum === 'PasifDevamEden' || order.setMi;
                    var btnDisabled = order.durum === 'IsEmriVerildi' || order.durum === 'PasifDevamEden' || order.durum === 'UretimdenKarsilaniyor' || order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.eslesenUrunNo <= 0 || order.setMi;
                    var matchDisabled = order.durum !== 'UretimBekliyor' || order.setMi;

                    // Ürün Adı — set ise özel göster
                    var urunAdiDisplay = order.setMi
                        ? '<span style="cursor:pointer;" onclick="toggleSetChildren(' + order.no + ')">' + escapeHtml(order.urunAdi) + '</span>'
                        : escapeHtml(order.urunAdi);

                    // Manuel select ve action — set parent'ta devre dışı
                    var selectHtml = order.setMi
                        ? '<span style="color:#888; font-size:0.78rem;">Bileşenlerde</span>'
                        : '<select class="match-select" data-no="' + order.no + '" ' + (matchDisabled ? 'disabled' : '') + '>' + optionsHtml + '</select>';

                    var actionHtml = '';
                    if (order.setMi) {
                        actionHtml = '<button type="button" class="btn-row-action" onclick="toggleSetChildren(' + order.no + ')" style="background:#e67e22;"><i class="fa-solid fa-chevron-down"></i> Detay</button>';
                    } else if (order.durum === 'IsEmriVerildi') {
                        actionHtml = '<button type="button" class="btn-row-action btn-cancel" data-no="' + order.no + '" onclick="cancelWorkOrder(' + order.no + ')" style="background:#c0392b;"><i class="fa-solid fa-xmark"></i> İptal Et</button>';
                    } else if (order.durum === 'UretimdenKarsilaniyor') {
                        actionHtml = '<div style="display:flex; flex-direction:column; gap:4px; align-items:center;">' +
                                     '<span style="color:#8e44ad; font-weight:bold; font-size:0.75rem;"><i class="fa-solid fa-link"></i> Üretime Bağlandı</span>' +
                                     '<button type="button" class="btn-row-action" style="background:#e74c3c; width:100%; border:1px solid #c0392b;" onclick="cancelWipAllocation(' + order.no + ')"><i class="fa-solid fa-xmark"></i> GİED İptal</button>' +
                                     '</div>';
                    } else if (order.durum === 'StokKarsilandi') {
                        actionHtml = '<span class="badge-stok">📦 Tamamlandı</span>';
                    } else if (order.durum === 'Pasif') {
                        actionHtml = '<span class="badge-pasif">⚪ Kapalı</span>';
                    } else if (order.durum === 'PasifDevamEden') {
                        actionHtml = '<span class="badge-pasif" style="color:#d97706; background:rgba(217,119,6,0.1);">🔸 Üretimde (İptal)</span>';
                    } else {
                        var baglaBtnDisabled = order.durum !== 'UretimBekliyor' || order.eslesenUrunNo <= 0;
                        var giedStyle = baglaBtnDisabled ? 'background:#ccc; color:#777; cursor:not-allowed;' : 'background:#8e44ad;';
                        actionHtml = '<div style="display:flex; flex-direction:column; gap:4px; align-items:center;">' +
                                     '<button type="button" class="btn-row-action" data-no="' + order.no + '" onclick="submitSingleWorkOrder(' + order.no + ')" ' + (btnDisabled ? 'disabled' : '') + ' style="width:100%;"><i class="fa-solid fa-hammer"></i> İş Emri</button>' +
                                     '<button type="button" class="btn-row-action" style="width:100%; ' + giedStyle + '" onclick="showWipAllocationDialog(' + order.no + ')" ' + (baglaBtnDisabled ? 'disabled' : '') + '><i class="fa-solid fa-link"></i> GİED</button>' +
                                     '</div>';
                    }

                    tr.innerHTML =
                        '<td><input type="checkbox" class="row-cb" data-no="' + order.no + '" onchange="onRowCheckChange(this)" ' + (cbDisabled ? 'disabled' : '') + ' /></td>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td><strong>' + escapeHtml(order.siparisNo) + '</strong></td>' +
                        '<td>' + escapeHtml(order.pazaryeri) + '</td>' +
                        '<td>' + escapeHtml(order.musteri) + musteriNoteHtml + '</td>' +
                        '<td style="max-width:250px;">' + urunAdiDisplay + '</td>' +
                        '<td style="text-align:center;"><strong>' + order.adet + '</strong></td>' +
                        '<td style="text-align:center;">' + stokHtml + '</td>' +
                        '<td style="text-align:center;">' + uretimdeHtml + '</td>' +
                        '<td>' + escapeHtml(order.siparisTarihi) + '</td>' +
                        '<td>' + escapeHtml(order.kargoSonTeslim) + '</td>' +
                        '<td>' + durumHtml + '</td>' +
                        '<td>' + matchHtml + '</td>' +
                        '<td>' + selectHtml + '</td>' +
                        '<td>' + actionHtml + '</td>';

                    tbody.appendChild(tr);

                    // Set parent ise — hemen altına child satırları ekle
                    if (order.setMi) {
                        var children = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; });
                        children.forEach(function (child) {
                            var ctr = document.createElement('tr');
                            ctr.className = 'set-child-row';
                            ctr.setAttribute('data-no', child.no);
                            ctr.setAttribute('data-parent', order.no);

                            var childDurumHtml = '';
                            if (child.durum === 'UretimBekliyor') childDurumHtml = '<span class="badge-bekliyor">🟡 Bekliyor</span>';
                            else if (child.durum === 'IsEmriVerildi') childDurumHtml = '<span class="badge-verildi">🟢 Verildi</span>';
                            else if (child.durum === 'PasifDevamEden') childDurumHtml = '<span class="badge-bekliyor" style="color: #d97706; background: rgba(217,119,6,0.1);">🔸 Devam Eden</span>';
                            else if (child.durum === 'UretimdenKarsilaniyor') childDurumHtml = '<span style="background: #e8daef; color: #8e44ad; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block;">🔗 Rezerve</span>';
                            else if (child.durum === 'StokKarsilandi') childDurumHtml = '<span class="badge-stok">📦 Stoktan</span>';
                            else childDurumHtml = '<span class="badge-pasif">⚪ Pasif</span>';

                            var childMatchHtml = child.eslesmePuani >= 80 ? '<span class="match-ok">✅ %' + child.eslesmePuani + '</span>' : '<span class="match-fail">❌ Yok</span>';

                            var childStokAdet = child.stokAdet || 0;
                            var childStokClass = childStokAdet >= child.adet ? 'stok-yeterli' : (childStokAdet > 0 ? 'stok-kismi' : 'stok-yok');
                            var childStokBtnHtml = '';
                            if (childStokAdet >= child.adet && child.durum === 'UretimBekliyor' && child.eslesenUrunNo > 0) {
                                childStokBtnHtml = '<button type="button" class="btn-stok" onclick="deductStock(event, ' + child.no + ')">Stoktan Düş</button>';
                            }

                            var childUretimde = child.uretimdeAdet || 0;
                            var childBtnDisabled = child.durum === 'IsEmriVerildi' || child.durum === 'PasifDevamEden' || child.durum === 'UretimdenKarsilaniyor' || child.durum === 'Pasif' || child.durum === 'StokKarsilandi' || child.eslesenUrunNo <= 0;
                            var childMatchDisabled = child.durum !== 'UretimBekliyor';

                            var childActionHtml = '';
                            if (child.durum === 'IsEmriVerildi') {
                                childActionHtml = '<button type="button" class="btn-row-action btn-cancel" onclick="cancelWorkOrder(' + child.no + ')" style="background:#c0392b;"><i class="fa-solid fa-xmark"></i> İptal</button>';
                            } else if (child.durum === 'UretimdenKarsilaniyor') {
                                childActionHtml = '<div style="display:flex; flex-direction:column; gap:4px; align-items:center;">' +
                                                  '<span style="color:#8e44ad; font-weight:bold; font-size:0.75rem;"><i class="fa-solid fa-link"></i> Üretime Bağlandı</span>' +
                                                  '<button type="button" class="btn-row-action" style="background:#e74c3c; width:100%; border:1px solid #c0392b;" onclick="cancelWipAllocation(' + child.no + ')"><i class="fa-solid fa-xmark"></i> GİED İptal</button>' +
                                                  '</div>';
                            } else if (child.durum === 'StokKarsilandi') {
                                childActionHtml = '<span class="badge-stok">📦 OK</span>';
                            } else {
                                var baglaChildBtnDisabled = child.durum !== 'UretimBekliyor' || child.eslesenUrunNo <= 0;
                                var childGiedStyle = baglaChildBtnDisabled ? 'background:#ccc; color:#777; cursor:not-allowed;' : 'background:#8e44ad;';
                                childActionHtml = '<div style="display:flex; flex-direction:column; gap:4px; align-items:center;">' +
                                                  '<button type="button" class="btn-row-action" onclick="submitSingleWorkOrder(' + child.no + ')" ' + (childBtnDisabled ? 'disabled' : '') + ' style="width:100%;"><i class="fa-solid fa-hammer"></i> İş Emri</button>' +
                                                  '<button type="button" class="btn-row-action" style="width:100%; ' + childGiedStyle + '" onclick="showWipAllocationDialog(' + child.no + ')" ' + (baglaChildBtnDisabled ? 'disabled' : '') + '><i class="fa-solid fa-link"></i> GİED</button>' +
                                                  '</div>';
                            }

                            var childCbDisabled = child.durum === 'Pasif' || child.durum === 'StokKarsilandi' || child.durum === 'PasifDevamEden';

                            ctr.innerHTML =
                                '<td><input type="checkbox" class="row-cb" data-no="' + child.no + '" onchange="onRowCheckChange(this)" ' + (childCbDisabled ? 'disabled' : '') + ' /></td>' +
                                '<td style="color:#8e44ad;">↳</td>' +
                                '<td><strong>' + escapeHtml(child.siparisNo) + '</strong></td>' +
                                '<td>' + escapeHtml(child.pazaryeri) + '</td>' +
                                '<td>' + escapeHtml(child.musteri) + '</td>' +
                                '<td style="max-width:250px;"><span class="badge-set-child">Bileşen</span> ' + escapeHtml(child.urunAdi) + '</td>' +
                                '<td style="text-align:center;"><strong>' + child.adet + '</strong></td>' +
                                '<td style="text-align:center;"><span class="' + childStokClass + '">' + childStokAdet + '</span>' + childStokBtnHtml + '</td>' +
                                '<td style="text-align:center;">' + (childUretimde > 0 ? '<span class="badge-uretimde">🔨 ' + childUretimde + '</span>' : '<span style="color:#aaa;">—</span>') + '</td>' +
                                '<td></td>' +
                                '<td></td>' +
                                '<td>' + childDurumHtml + '</td>' +
                                '<td>' + childMatchHtml + '</td>' +
                                '<td><select class="match-select" data-no="' + child.no + '" ' + (childMatchDisabled ? 'disabled' : '') + '>' + optionsHtml + '</select></td>' +
                                '<td>' + childActionHtml + '</td>';

                            tbody.appendChild(ctr);
                        });
                    }

                    // Select'te eşleşeni seç (Tur_No composite key)
                    if (order.eslesenUrunNo > 0 && order.eslesenUrunTur) {
                        var sel = tr.querySelector('.match-select');
                        if (sel) sel.value = order.eslesenUrunTur + '_' + order.eslesenUrunNo;
                    }
                });

                // Select2 aktifle
                setTimeout(function () {
                    $('.match-select').select2({
                        placeholder: '🔍 Ürün ara...',
                        allowClear: true,
                        width: '100%',
                        minimumInputLength: 0,
                        language: {
                            noResults: function () { return 'Ürün bulunamadı'; },
                            searching: function () { return 'Aranıyor...'; },
                            inputTooShort: function () { return 'Yazın...'; }
                        }
                    });

                    // Select2 başlatıldıktan SONRA eşleşen değerleri ayarla
                    $('.match-select').each(function () {
                        var selNo = parseInt($(this).attr('data-no'));
                        var ord = allOrders.find(function (o) { return o.no === selNo; });
                        if (ord && ord.eslesenUrunNo > 0 && ord.eslesenUrunTur) {
                            var compositeVal = ord.eslesenUrunTur + '_' + ord.eslesenUrunNo;
                            if ($(this).find('option[value="' + compositeVal + '"]').length > 0) {
                                $(this).val(compositeVal).trigger('change.select2');
                            }
                        }
                    });

                    // Manuel eşleştirme event handler — select2:select sadece kullanıcı seçiminde tetiklenir
                    $('.match-select').on('select2:select', function (e) {
                        var satirNo = parseInt($(this).attr('data-no'));
                        var compositeVal = $(this).val();
                        if (!compositeVal || !satirNo) return;

                        // Composite key parse: "Nihai_5" → tur=Nihai, no=5
                        var parts = compositeVal.split('_');
                        var tur = parts[0];
                        var urunNo = parseInt(parts[1]);

                        // Manuel eşleştirme kaydet
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
                                    // Satırdaki eşleşme badge'ini güncelle
                                    var tr = $('tr[data-no="' + satirNo + '"]');
                                    tr.find('td:eq(10)').html('<span class="match-ok">✅ Manuel</span>');
                                    tr.find('.btn-row-action').prop('disabled', false);

                                    // Önbellek bilgilendirme popup'ı
                                    var infoHtml = '<div style="text-align:left; font-size:0.9rem;">' +
                                        '<div style="background:#f0faf0; border-radius:10px; padding:12px; margin-bottom:10px;">' +
                                        '<i class="fa-solid fa-database" style="color:#27ae60;"></i> <strong>Önbelleğe Kaydedildi!</strong></div>' +
                                        '<p><strong>Pazaryeri Adı:</strong><br><span style="color:#8e44ad;">' + (res.cachedExcelName || '-') + '</span></p>' +
                                        '<p><strong>Sistem Ürünü:</strong><br><span style="color:#27ae60;">' + (res.cachedProductName || '-') + '</span></p>';

                                    if (res.updatedCount > 0) {
                                        infoHtml += '<div style="background:#fff3cd; border-radius:8px; padding:8px; margin-top:8px;">' +
                                            '<i class="fa-solid fa-bolt" style="color:#e67e22;"></i> <strong>' + res.updatedCount + '</strong> aynı ürün adına sahip sipariş daha otomatik eşleştirildi.</div>';
                                    }

                                    infoHtml += '<p style="margin-top:10px; font-size:0.8rem; color:#888;">Bu eşleştirme kalıcıdır. Siparişler silinip yeniden yüklense bile bu ürün otomatik eşleşecektir.</p></div>';

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Eşleştirme Kaydedildi!',
                                        html: infoHtml,
                                        confirmButtonColor: '#8e44ad',
                                        confirmButtonText: 'Tamam'
                                    });

                                    // Grid'i yenile
                                    setTimeout(function () { loadOrders(); }, 800);
                                }
                            }
                        });
                    });
                }, 150);
            }

            // ============================================================
            //   SEÇİM YÖNETİMİ
            // ============================================================
            function onRowCheckChange(cb) {
                var no = parseInt(cb.getAttribute('data-no'));
                if (cb.checked) {
                    selectedNos.add(no);
                    cb.closest('tr').classList.add('selected-row');
                } else {
                    selectedNos.delete(no);
                    cb.closest('tr').classList.remove('selected-row');
                }
                updateSelectedCount();
            }

            function toggleAllRows(masterCb) {
                var checkboxes = document.querySelectorAll('.row-cb:not(:disabled)');
                checkboxes.forEach(function (cb) {
                    cb.checked = masterCb.checked;
                    var no = parseInt(cb.getAttribute('data-no'));
                    if (masterCb.checked) {
                        selectedNos.add(no);
                        cb.closest('tr').classList.add('selected-row');
                    } else {
                        selectedNos.delete(no);
                        cb.closest('tr').classList.remove('selected-row');
                    }
                });
                updateSelectedCount();
            }

            function updateSelectedCount() {
                document.getElementById('selectedCount').textContent = selectedNos.size;
                document.getElementById('btnTopluIsEmri').disabled = selectedNos.size === 0;
                document.getElementById('btnTopluIptal').disabled = selectedNos.size === 0;
                document.getElementById('btnTopluStok').disabled = selectedNos.size === 0;
            }

            // ============================================================
            //   TEKLİ İŞ EMRİ VER
            // ============================================================
            function submitSingleWorkOrder(no) {
                Swal.fire({
                    title: 'İş Emri Ver',
                    text: 'Bu sipariş için iş emri oluşturulsun mu?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Oluştur',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#4b9460',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    $.ajax({
                        url: apiUrl + '?action=createOrderWorkOrders',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ satirNolar: [no] }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                var errHtml = '';
                                if (res.errors && res.errors.length > 0) {
                                    errHtml = '<br><small class="text-danger">' + res.errors.join('<br>') + '</small>';
                                }
                                Swal.fire({
                                    icon: res.created > 0 ? 'success' : 'warning',
                                    title: res.created > 0 ? 'İş Emri Oluşturuldu!' : 'İşlem Tamamlandı',
                                    html: res.message + errHtml,
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
                                    html: res.message,
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
                var nolar = Array.from(selectedNos);

                Swal.fire({
                    title: 'Toplu İş Emri İptal',
                    html: '<b>' + nolar.length + '</b> seçili siparişin iş emirleri iptal edilsin mi?<br><small class="text-muted">Görev Atama sayfasından da silinecektir.</small>',
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
                                    html: res.message,
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
            function submitBulkWorkOrders() {
                if (selectedNos.size === 0) return;

                var nolar = Array.from(selectedNos);
                var selectedOrders = allOrders.filter(function(o) { return selectedNos.has(o.no); });
                
                // Aynı ürün kontrolü
                var firstUrunNo = selectedOrders[0].eslesenUrunNo;
                var isSameProduct = false;
                
                if (firstUrunNo > 0) {
                    isSameProduct = selectedOrders.every(function(o) { return o.eslesenUrunNo === firstUrunNo; });
                }

                if (isSameProduct) {
                    var urunAdi = dbProducts.find(function(p) { return p.UrunNo == firstUrunNo; });
                    var uName = urunAdi ? escapeHtml(urunAdi.UrunAdi) : 'Bu Ürün';

                    Swal.fire({
                        title: 'Seri Üretime Ekleme Yap',
                        html: '<p><b>' + nolar.length + '</b> adet <strong>' + uName + '</strong> siparişi için iş emri oluşturulacak.</p>' +
                              '<p style="font-size:0.9rem; color:#6b5a42;">Üretim bandını bozmamak için stok amacıyla (Özel Üretim) fazladan üretilecek sayı girmek ister misiniz?</p>',
                        icon: 'question',
                        input: 'number',
                        inputAttributes: {
                            min: 0,
                            step: 1
                        },
                        inputValue: 0,
                        showCancelButton: true,
                        confirmButtonText: 'İş Emri Ver',
                        cancelButtonText: 'İptal',
                        confirmButtonColor: '#4b9460',
                        cancelButtonColor: '#80694d'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            var surplusCount = parseInt(result.value) || 0;
                            sendBulkWorkOrderRequest(nolar, surplusCount);
                        }
                    });
                } else {
                    // Farklı ürünler seçilmiş, standart uyarı
                    Swal.fire({
                        title: 'Toplu İş Emri Ver',
                        html: '<b>' + nolar.length + '</b> sipariş satırı için iş emri oluşturulacak.<br><br><small style="color:#e67e22;"><i class="fa-solid fa-triangle-exclamation"></i> Farklı ürünler seçtiğiniz için stok ilavesi sorulmuyor.</small>',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Oluştur',
                        cancelButtonText: 'Vazgeç',
                        confirmButtonColor: '#4b9460',
                        cancelButtonColor: '#80694d'
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            sendBulkWorkOrderRequest(nolar, 0);
                        }
                    });
                }
            }

            function sendBulkWorkOrderRequest(nolar, surplusAmount) {
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
                    data: JSON.stringify({ satirNolar: nolar, surplus: surplusAmount }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            var errHtml = '';
                            if (res.errors && res.errors.length > 0) {
                                errHtml = '<br><br><details><summary>Hatalar (' + res.errors.length + ')</summary><ul>' +
                                    res.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('') + '</ul></details>';
                            }

                            Swal.fire({
                                icon: 'success',
                                title: 'İş Emirleri Oluşturuldu!',
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

                // Alt satırların Select2 değerlerini ayarla
                if (isOpen) {
                    childRows.forEach(function (row) {
                        var childNo = parseInt(row.getAttribute('data-no'));
                        var childOrder = allOrders.find(function (o) { return o.no === childNo; });
                        if (childOrder && childOrder.eslesenUrunNo > 0) {
                            var sel = row.querySelector('.match-select');
                            if (sel) {
                                var compositeVal = childOrder.eslesenUrunTur + '_' + childOrder.eslesenUrunNo;
                                $(sel).val(compositeVal).trigger('change.select2');
                            }
                        }
                    });
                }
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
            //   STOKTAN DÜŞ — TEKLİ
            // ============================================================
            function deductStock(e, no) {
                if (e && e.preventDefault) e.preventDefault();
                Swal.fire({
                    title: 'Stoktan Düş',
                    text: 'Bu sipariş stoktan karşılansın mı?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Düş',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#2196F3',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Stoktan düşülüyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=deductStock',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ no: no }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Stoktan Düşüldü!', text: res.message, confirmButtonColor: '#2196F3' });
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
                var nosArr = Array.from(selectedNos);
                Swal.fire({
                    title: 'Toplu Stoktan Düş',
                    html: '<b>' + nosArr.length + '</b> sipariş stoktan karşılansın mı?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Düş',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#2196F3',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    Swal.fire({ title: 'Stoktan düşülüyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=deductStockBulk',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ nos: nosArr }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                var msg = res.message;
                                if (res.hatalar && res.hatalar.length > 0) {
                                    msg += '<br><br><small style="color:#856404;">' + res.hatalar.join('<br>') + '</small>';
                                }
                                Swal.fire({ icon: 'success', title: 'Stoktan Düşüldü!', html: msg, confirmButtonColor: '#2196F3' });
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
                    html: 'Eşleşmemiş tüm siparişler güncel ürün veritabanına karşı tekrar eşleştirilecek.<br><br>Devam etmek istiyor musunuz?',
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
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Eşleştirme Tamamlandı!',
                                        html: '<b>' + res.matched + '</b> sipariş yeni eşleştirildi.<br>' +
                                            '<b>' + res.total + '</b> eşleşmemiş sipariş kontrol edildi.',
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

                            // Tablo HTML oluştur
                            var html = '<div style="text-align:left; font-size:0.9rem; margin-top:10px;">';
                            html += '<p><strong>Sipariş No:</strong> ' + escapeHtml(order.siparisNo) + ' | <strong>Adet:</strong> ' + order.adet + '</p>';
                            html += '<table class="wip-table" style="width:100%; border-collapse:collapse; margin-top:10px;">';
                            html += '<thead style="background:#f1f1f1;"><tr>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Seç</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">İş Emri Tarihi</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Toplam</th>' +
                                    '<th style="padding:8px; border:1px solid #ccc;">Boşta Kalan</th>' +
                                    '</tr></thead><tbody>';

                            res.data.forEach(function (w, idx) {
                                html += '<tr>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center;"><input type="radio" name="wipSelected" value="' + w.no + '" id="wip_' + w.no + '" ' + (idx===0 ? 'checked' : '') + '></td>' +
                                    '<td style="padding:8px; border:1px solid #ccc;"><label style="cursor:pointer;" for="wip_' + w.no + '">' + escapeHtml(w.isEmriTarihi) + '</label></td>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center;">' + w.toplamAdet + '</td>' +
                                    '<td style="padding:8px; border:1px solid #ccc; text-align:center; color:#27ae60; font-weight:bold;">' + w.bostaAdet + '</td>' +
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
