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
            .oz-badge.set-parent {
                background: #fef3c7; color: #b45309;
                cursor: pointer;
            }
            .oz-badge.set-child { background: rgba(139,92,246,0.1); color: #7c3aed; font-size: 0.65rem; }
            .oz-badge.uretimde { background: #fef3c7; color: #b45309; }

            /* ── Progress Bar (Görevlendirme) ── */
            .oz-progress-wrap { min-width: 90px; }
            .oz-progress-bar { width: 100%; height: 7px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 3px; }
            .oz-progress-bar-inner { height: 100%; display: flex; transition: width 0.4s ease; }
            .oz-progress-seg-personel { background: linear-gradient(90deg, #3b82f6, #6366f1); }
            .oz-progress-seg-havuz { background: linear-gradient(90deg, #f59e0b, #eab308); }
            .oz-progress-label { font-size: 0.62rem; color: var(--z-text-muted); font-weight: 600; line-height: 1.2; }
            .oz-progress-label .pct { font-weight: 800; }
            .oz-progress-label .pct.full { color: var(--z-success); }
            .oz-progress-label .pct.partial { color: #d97706; }
            .oz-progress-label .pct.zero { color: var(--z-text-muted); }
            .oz-progress-na { font-size: 0.68rem; color: var(--z-text-muted); }

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

            /* WIP yapısı */
            .bilesen-adet-badge { background: #d97706; color: #fff; border-radius: 50%; width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; margin-right: 3px; }
            .note-icon { cursor: help; color: var(--z-accent); font-size: 0.85rem; margin-left: 3px; }

            /* Loading */
            .loading-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(244,245,247,0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: center; align-items: center; }

            @media (max-width: 768px) {
                .oz-stats-row { grid-template-columns: repeat(2, 1fr); }
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
                <div style="flex-grow: 1;"></div>
                <button type="button" class="oz-btn-action purple" onclick="openIndependentStockModal()" style="margin-left: auto;">
                    ➕ Yeni Stok Üretimi
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
                <p>Operasyon Merkezi'nden sipariş Excel dosyanızı yükleyin.</p>
            </div>

        </div>

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

            // Sayfa yüklendiğinde
            $(document).ready(function () {
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

                var numericFields = ['adet', 'stokAdet', 'uretimdeAdet', 'eslesmePuani', 'no'];
                var dateFields = ['siparisTarihi', 'kargoSonTeslim'];
                var isNumeric = numericFields.indexOf(field) >= 0;
                var isDate = dateFields.indexOf(field) >= 0;

                allOrders.sort(function (a, b) {
                    var va = a[field] || '';
                    var vb = b[field] || '';
                    if (isNumeric) { va = parseFloat(va) || 0; vb = parseFloat(vb) || 0; }
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

                if (order.durum !== 'IsEmriVerildi' && toplam <= 0) {
                    return '<span class="oz-progress-na">—</span>';
                }

                var hedef = Math.max(order.adet || 1, toplam);
                var pctPersonel = hedef > 0 ? Math.min(100, Math.round((personel / hedef) * 100)) : 0;
                var pctHavuz = hedef > 0 ? Math.min(100 - pctPersonel, Math.round((havuz / hedef) * 100)) : 0;
                var pctTotal = Math.min(100, pctPersonel + pctHavuz);

                var pctClass = pctTotal >= 100 ? 'full' : (pctTotal > 0 ? 'partial' : 'zero');
                var ghostWarn = (order.durum === 'IsEmriVerildi' && toplam <= 0)
                    ? ' <span style="color:#ef4444;" title="İş emri verilmiş ama atölyede kayıt yok!">⚠️</span>' : '';

                var barHtml = '<div class="oz-progress-wrap" title="Havuz (Atanmamış): ' + havuz + ' | Personelde: ' + personel + ' / Sipariş: ' + (order.adet || 0) + '">' +
                    '<div class="oz-progress-bar"><div class="oz-progress-bar-inner" style="width:' + pctTotal + '%">' +
                    (pctPersonel > 0 ? '<div class="oz-progress-seg-personel" style="flex:' + pctPersonel + '"></div>' : '') +
                    (pctHavuz > 0 ? '<div class="oz-progress-seg-havuz" style="flex:' + pctHavuz + '"></div>' : '') +
                    '</div></div>' +
                    '<div class="oz-progress-label">' +
                    '<span class="pct ' + pctClass + '">' + toplam + '/' + (order.adet || 0) + '</span>' +
                    (personel > 0 ? ' <span style="color:#6366f1;" title="Personele atanmış">👷' + personel + '</span>' : '') +
                    (havuz > 0 ? ' <span style="color:#eab308;" title="Havuzda bekliyor">📦' + havuz + '</span>' : '') +
                    ghostWarn +
                    '</div></div>';

                return barHtml;
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
                    var durumHtml = '';
                    if (order.setMi) {
                        var childCount = allOrders.filter(function (o) { return o.anaSetSatirNo === order.no; }).length;
                        durumHtml = '<span class="oz-badge set-parent" onclick="toggleSetChildren(' + order.no + ')" title="' + childCount + ' bileşen">📦 SET (' + childCount + ') ▼</span>';
                    } else if (order.durum === 'UretimBekliyor') durumHtml = '<span class="oz-badge bekliyor">⏳ İş Emri Bekliyor</span>';
                    else if (order.durum === 'IsEmriVerildi') durumHtml = '<span class="oz-badge verildi">✅ İş Emri Verildi</span>';
                    else if (order.durum === 'StokKarsilandi') durumHtml = '<span class="oz-badge stok">📦 Stoktan</span>';
                    else durumHtml = '<span class="oz-badge pasif">⚪ Pasif</span>';

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

                    // Görevlendirme Progress Bar
                    var progressHtml = buildProgressHtml(order);

                    var cbDisabled = order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.setMi;
                    var btnDisabled = order.durum === 'IsEmriVerildi' || order.durum === 'Pasif' || order.durum === 'StokKarsilandi' || order.eslesenUrunNo <= 0 || order.setMi;

                    var urunAdiDisplay = order.setMi
                        ? '<span style="cursor:pointer;" onclick="toggleSetChildren(' + order.no + ')">' + escapeHtml(order.urunAdi) + '</span>'
                        : escapeHtml(order.urunAdi);

                    // WIP stats (Eksik/Rezerve)
                    var wipStatsHtml = '';
                    if (order.bagliSiparisler) {
                        var bosta = order.bostaKalan || 0;
                        var rezerve = order.rezerveEdilen || 0;
                        wipStatsHtml = '<div style="font-size:0.72rem; margin-top:3px;"><span style="color:#ea580c; font-weight:600;" title="Siparişin kapanması için hala atanması gereken miktar">Eksik/Bekleyen: ' + bosta + '</span> | <span style="color:#7c3aed; font-weight:600;">Rezerve: ' + rezerve + '</span></div>';
                    }

                    var selectHtml = order.setMi
                        ? '<span style="color:var(--z-text-muted); font-size:0.72rem;">Set</span>'
                        : '<button class="oz-btn-row gray" onclick="openMatchModal(' + order.no + ')" ' + (order.durum === 'IsEmriVerildi' || order.durum === 'StokKarsilandi' ? 'disabled' : '') + ' style="padding:4px 8px;" title="Veritabanı ürünü ara/seç">🔍</button>';

                    // Aksiyon
                    var actionHtml = '';
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

                    tr.innerHTML =
                        '<td><input type="checkbox" class="row-cb" data-no="' + order.no + '" onchange="onRowCheckChange(this)" ' + (cbDisabled ? 'disabled' : '') + ' /></td>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td><strong>' + escapeHtml(order.siparisNo) + '</strong></td>' +
                        '<td style="white-space:nowrap; font-size:0.75rem; color:var(--z-text-muted);">' + escapeHtml(order.siparisTarihi) + '</td>' +
                        '<td style="max-width:280px;">' + urunAdiDisplay + wipStatsHtml + (order.musteriNotu ? musteriNoteHtml : '') + '</td>' +
                        '<td style="text-align:center;"><strong>' + order.adet + '</strong></td>' +
                        '<td style="text-align:center;">' + stokHtml + '</td>' +
                        '<td>' + progressHtml + '</td>' +
                        '<td>' + durumHtml + '</td>' +
                        '<td>' + matchHtml + '</td>' +
                        '<td>' + selectHtml + '</td>' +
                        '<td>' + actionHtml + '</td>';

                    tbody.appendChild(tr);

                    // Rezerve siparişler alt tablo
                    if (order.bagliSiparisler && order.bagliSiparisler.length > 0) {
                        var tdColSpan = 11;
                        var wipTr = document.createElement('tr');
                        wipTr.className = 'wip-child-container';
                        wipTr.id = 'wip_child_' + order.no;
                        wipTr.style.display = 'none';
                        wipTr.style.backgroundColor = 'rgba(139,92,246,0.03)';

                        var innerHtml = '<td colspan="' + tdColSpan + '" style="padding:10px 40px; border-left:4px solid #8b5cf6;">' +
                            '<table style="width:100%; border-collapse:collapse; font-size:0.8rem;">' +
                            '<thead><tr style="border-bottom:2px solid var(--z-border-light);">' +
                            '<th style="padding:8px; color:#7c3aed; font-weight:700; font-size:0.72rem;">🔗 Rezerve Eden Sipariş No</th><th style="padding:8px; color:#7c3aed; font-weight:700; font-size:0.72rem;">Çekilen Miktar</th>' +
                            '</tr></thead><tbody>';

                        order.bagliSiparisler.forEach(function(b) {
                            innerHtml += '<tr style="border-bottom:1px solid var(--z-border-light);">' +
                                '<td style="padding:8px;"><strong>' + escapeHtml(b.siparisNo) + '</strong></td>' +
                                '<td style="padding:8px;"><span style="background:#7c3aed; color:#fff; padding:2px 8px; border-radius:6px; font-weight:700; font-size:0.72rem;">' + b.adet + ' Adet</span></td>' +
                                '</tr>';
                        });
                        innerHtml += '</tbody></table></td>';
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

                            var childDurumHtml = '';
                            if (child.durum === 'UretimBekliyor') childDurumHtml = '<span class="oz-badge bekliyor">⏳ İE Bekliyor</span>';
                            else if (child.durum === 'IsEmriVerildi') childDurumHtml = '<span class="oz-badge verildi">✅ Verildi</span>';
                            else if (child.durum === 'StokKarsilandi') childDurumHtml = '<span class="oz-badge stok">📦 Stoktan</span>';
                            else childDurumHtml = '<span class="oz-badge pasif">⚪ Pasif</span>';

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

            function getSelectedWorkOrderType() {
                var checked = document.querySelector('input[name="woType"]:checked');
                return checked ? checked.value : 'Nihai';
            }

            function getSelectedBomComponent() {
                var compSelect = document.getElementById('swalBomComponent');
                return compSelect ? compSelect.value : null;
            }

            // ============================================================
            //   TEKLİ İŞ EMRİ VER
            // ============================================================
            function submitSingleWorkOrder(no) {
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
                    html: buildWorkOrderTypeSelector(urunNo),
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '🔨 İş Emri Ver',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                    width: 520
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var tur = getSelectedWorkOrderType();
                    var altBilesenNo = getSelectedBomComponent();

                    Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                    $.ajax({
                        url: apiUrl + '?action=createOrderWorkOrders', type: 'POST', contentType: 'application/json',
                        data: JSON.stringify({ satirNolar: [no], tur: tur, altBilesenNo: altBilesenNo }), dataType: 'json',
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
                    html: topHtml + buildWorkOrderTypeSelector(isSameProduct ? firstUrunNo : 0) + surplusHtml,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: '🔨 Toplu İş Emri Ver',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#16a34a',
                    cancelButtonColor: '#6b7280',
                    width: 560
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var tur = getSelectedWorkOrderType();
                    var altBilesenNo = getSelectedBomComponent();
                    var surplus = isSameProduct ? (parseInt(document.getElementById('swalSurplus').value) || 0) : 0;
                    sendBulkWorkOrderRequest(nolar, surplus, tur, altBilesenNo);
                });
            }

            function sendBulkWorkOrderRequest(nolar, surplusAmount, tur, altBilesenNo) {
                var loadingText = surplusAmount > 0
                    ? nolar.length + ' sipariş ve ' + surplusAmount + ' özel stok üretimi işleniyor...'
                    : nolar.length + ' satır işleniyor...';
                Swal.fire({ title: 'İş Emirleri Oluşturuluyor...', html: loadingText, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                $.ajax({
                    url: apiUrl + '?action=createOrderWorkOrders', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ satirNolar: nolar, surplus: surplusAmount, tur: tur || 'Nihai', altBilesenNo: altBilesenNo }), dataType: 'json',
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
            function openIndependentStockModal() {
                var html = '<div style="text-align:left; margin-top:12px;">' +
                    '<label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:4px;">Üretilecek Ürün / Ara Mamül</label>' +
                    '<select id="swalIndStockProduct" style="width:100%;"><option value="">Seçiniz...</option></select>' +

                    '<div style="margin-top:16px;"></div>' + buildWorkOrderTypeSelector(0) +

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
                    showCancelButton: true,
                    confirmButtonText: '🔨 İş Emri Ver',
                    cancelButtonText: 'İptal',
                    confirmButtonColor: '#7c3aed',
                    didOpen: function() {
                        var select = $('#swalIndStockProduct');
                        select.select2({
                            dropdownParent: $('.swal2-container'),
                            ajax: {
                                url: apiUrl + '?action=getAraUrunler',
                                dataType: 'json',
                                delay: 250,
                                data: function (params) { return { search: params.term }; },
                                processResults: function (data) {
                                    return {
                                        results: data.data.map(function(item) {
                                            // The backend returns item.No and item.AraUrunAdi
                                            return { id: item.No, text: item.AraUrunAdi };
                                        })
                                    };
                                }
                            },
                            placeholder: 'Ürün veya Ara Mamül ara...',
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
                }).then(function(result) {
                    if(result.isConfirmed) {
                        var urunNo = $('#swalIndStockProduct').val();
                        if(!urunNo) {
                            Swal.fire('Hata', 'Lütfen bir ürün seçin!', 'warning');
                            return;
                        }
                        var tur = getSelectedWorkOrderType();
                        var adet = document.getElementById('swalIndStockCount').value;
                        var note = document.getElementById('swalIndStockNote').value;
                        var altBilesenNo = getSelectedBomComponent();

                        if(altBilesenNo && parseInt(altBilesenNo) > 0) {
                            urunNo = altBilesenNo;
                            tur = (tur === 'Ara' ? 'Ara' : tur);
                        }

                        Swal.fire({ title: 'İş Emri Oluşturuluyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=createIndependentStockOrder', type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({ urunNo: urunNo, adet: adet, tur: tur, aciklama: note }), dataType: 'json',
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