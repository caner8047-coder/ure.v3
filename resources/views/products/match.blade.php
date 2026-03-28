@extends('layouts.app')

@section('content')

        <!-- Kütüphaneler -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
        <!-- Select2 -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <style>
            .page-header {
                max-width: 1200px;
                margin: 30px auto 20px auto;
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .page-header h2 {
                color: #3b2c1a;
                font-weight: 700;
                margin: 0;
                font-size: 1.6rem;
            }

            .page-header .subtitle {
                color: #80694d;
                font-size: 0.85rem;
                margin-top: 2px;
            }

            .page-header .icon {
                width: 52px;
                height: 52px;
                background: linear-gradient(135deg, #8e44ad, #6c3483);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 1.5rem;
                box-shadow: 0 4px 12px rgba(142, 68, 173, 0.3);
            }

            /* Tabs */
            .tabs-container {
                max-width: 1200px;
                margin: 0 auto 20px auto;
            }

            .tab-buttons {
                display: flex;
                gap: 0;
                border-bottom: 2px solid #ede4d4;
            }

            .tab-btn {
                padding: 12px 28px;
                border: none;
                background: transparent;
                font-size: 0.9rem;
                font-weight: 600;
                color: #80694d;
                cursor: pointer;
                position: relative;
                transition: all 0.2s;
            }

            .tab-btn:hover {
                color: #3b2c1a;
            }

            .tab-btn.active {
                color: #8e44ad;
            }

            .tab-btn.active::after {
                content: '';
                position: absolute;
                bottom: -2px;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(135deg, #8e44ad, #6c3483);
                border-radius: 3px 3px 0 0;
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }

            /* Stats */
            .stats-row {
                max-width: 1200px;
                margin: 0 auto 20px auto;
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .stat-card {
                background: #fffaf3;
                border-radius: 12px;
                padding: 18px 24px;
                flex: 1;
                min-width: 160px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
                text-align: center;
                border: 1px solid #ede4d4;
            }

            .stat-card .number {
                font-size: 2rem;
                font-weight: 700;
                color: #3b2c1a;
            }

            .stat-card .label {
                font-size: 0.8rem;
                color: #80694d;
                font-weight: 500;
            }

            /* Toolbar */
            .toolbar {
                max-width: 1200px;
                margin: 0 auto 16px auto;
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }

            .toolbar input[type=text] {
                padding: 8px 14px;
                border: 1px solid #d4c5a9;
                border-radius: 8px;
                font-size: 0.85rem;
                min-width: 250px;
                background: #fff;
            }

            .toolbar input[type=text]:focus {
                border-color: #8e44ad;
                outline: none;
                box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.15);
            }

            .btn-primary-custom {
                background: linear-gradient(135deg, #8e44ad, #6c3483);
                color: #fff;
                border: none;
                padding: 8px 18px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                font-size: 0.85rem;
                transition: all 0.2s;
                white-space: nowrap;
            }

            .btn-primary-custom:hover {
                opacity: 0.85;
                transform: translateY(-1px);
            }

            .btn-danger-custom {
                background: linear-gradient(135deg, #c0392b, #96281b);
                color: #fff;
                border: none;
                padding: 8px 18px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                font-size: 0.85rem;
                transition: all 0.2s;
                white-space: nowrap;
            }

            .btn-danger-custom:hover {
                opacity: 0.85;
            }

            .btn-secondary-custom {
                background: #80694d;
                color: #fff;
                border: none;
                padding: 8px 14px;
                border-radius: 8px;
                font-weight: 500;
                cursor: pointer;
                font-size: 0.85rem;
            }

            /* Table */
            .grid-container {
                max-width: 1200px;
                margin: 0 auto 40px auto;
                background: #fffaf3;
                border-radius: 14px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
                overflow-x: auto;
            }

            .grid-container table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.85rem;
            }

            .grid-container thead th {
                background: linear-gradient(90deg, #4b3621, #6b5230);
                color: #f4e9d8;
                padding: 12px 14px;
                text-align: left;
                font-weight: 600;
                font-size: 0.8rem;
                position: sticky;
                top: 0;
                z-index: 10;
                white-space: nowrap;
            }

            .grid-container tbody tr {
                border-bottom: 1px solid #ede4d4;
                transition: background 0.2s;
            }

            .grid-container tbody tr:hover {
                background: #fef5e6;
            }

            .grid-container tbody tr.selected-row {
                background: #f3e8ff !important;
            }

            .grid-container td {
                padding: 10px 14px;
                vertical-align: middle;
                color: #3b2c1a;
            }

            .badge-tur {
                background: linear-gradient(135deg, #8e44ad, #6c3483);
                color: #fff;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 600;
            }

            .badge-set {
                background: linear-gradient(135deg, #e67e22, #d35400);
                color: #fff;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 600;
            }

            .btn-delete-row {
                background: #c0392b;
                color: #fff;
                border: none;
                padding: 5px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.78rem;
                transition: all 0.2s;
            }

            .btn-delete-row:hover {
                background: #96281b;
            }

            .arrow-icon {
                color: #8e44ad;
                font-size: 1.1rem;
                margin: 0 4px;
            }

            .empty-state {
                text-align: center;
                padding: 60px 20px;
                color: #80694d;
            }

            .empty-state i {
                font-size: 3rem;
                margin-bottom: 12px;
                opacity: 0.4;
            }

            .empty-state h3 {
                font-size: 1.1rem;
                margin-bottom: 6px;
                color: #3b2c1a;
            }

            /* Add form */
            .add-form-container {
                max-width: 1200px;
                margin: 0 auto 20px auto;
                background: #fffaf3;
                border-radius: 14px;
                padding: 20px 24px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
                border: 1px solid #ede4d4;
                display: none;
            }

            .add-form-container h4 {
                margin: 0 0 16px 0;
                color: #3b2c1a;
                font-size: 1rem;
            }

            .add-form-row {
                display: flex;
                gap: 12px;
                align-items: flex-end;
                flex-wrap: wrap;
            }

            .add-form-row .form-group {
                flex: 1;
                min-width: 200px;
            }

            .add-form-row .form-group label {
                display: block;
                font-size: 0.75rem;
                font-weight: 600;
                color: #6b5a42;
                margin-bottom: 4px;
            }

            .add-form-row .form-group input,
            .add-form-row .form-group select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #d4c5a9;
                border-radius: 8px;
                font-size: 0.85rem;
                background: #fff;
            }

            /* Select2 style */
            .select2-container--default .select2-selection--single {
                height: 36px;
                border: 1px solid #d4c5a9;
                border-radius: 8px;
            }

            .select2-container--default .select2-selection--single .select2-selection__rendered {
                line-height: 36px;
                font-size: 0.85rem;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
            }

            /* Set form */
            .set-form-container {
                max-width: 1200px;
                margin: 0 auto 20px auto;
                background: #fffaf3;
                border-radius: 14px;
                padding: 20px 24px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
                border: 1px solid #ede4d4;
                display: none;
            }

            .set-form-container h4 {
                margin: 0 0 16px 0;
                color: #3b2c1a;
                font-size: 1rem;
            }

            .bilesen-list {
                margin: 12px 0;
            }

            .bilesen-item {
                display: flex;
                gap: 10px;
                align-items: center;
                padding: 8px 12px;
                background: #fff;
                border: 1px solid #ede4d4;
                border-radius: 8px;
                margin-bottom: 6px;
            }

            .bilesen-item .bilesen-urun {
                flex: 3;
                font-weight: 600;
                color: #27ae60;
            }

            .bilesen-item .bilesen-adet {
                flex: 0 0 80px;
            }

            .bilesen-item .bilesen-adet input {
                width: 60px;
                padding: 4px 8px;
                border: 1px solid #d4c5a9;
                border-radius: 6px;
                text-align: center;
                font-weight: 700;
                font-size: 0.9rem;
            }

            .bilesen-item .bilesen-remove {
                cursor: pointer;
                color: #c0392b;
                font-size: 1rem;
            }

            /* Set card */
            .set-card {
                background: #fffaf3;
                border: 1px solid #ede4d4;
                border-radius: 12px;
                margin-bottom: 12px;
                overflow: hidden;
            }

            .set-card-header {
                padding: 14px 18px;
                display: flex;
                align-items: center;
                gap: 12px;
                cursor: pointer;
                transition: background 0.2s;
            }

            .set-card-header:hover {
                background: #fef5e6;
            }

            .set-card-header .set-icon {
                width: 38px;
                height: 38px;
                background: linear-gradient(135deg, #e67e22, #d35400);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                font-size: 1rem;
                flex-shrink: 0;
            }

            .set-card-header .set-info {
                flex: 1;
                min-width: 0;
            }

            .set-card-header .set-info .set-name {
                font-weight: 700;
                color: #3b2c1a;
                font-size: 0.9rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .set-card-header .set-info .set-excel {
                font-size: 0.75rem;
                color: #8e44ad;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .set-card-header .set-meta {
                display: flex;
                gap: 8px;
                align-items: center;
                flex-shrink: 0;
            }

            .set-card-body {
                display: none;
                padding: 0 18px 14px 18px;
                border-top: 1px solid #ede4d4;
            }

            .set-card-body.open {
                display: block;
            }

            .set-bilesen-row {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 0;
                border-bottom: 1px solid #f5efe5;
            }

            .set-bilesen-row:last-child {
                border-bottom: none;
            }

            .set-bilesen-row .adet-badge {
                background: #e67e22;
                color: #fff;
                border-radius: 50%;
                width: 26px;
                height: 26px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.75rem;
                font-weight: 700;
                flex-shrink: 0;
            }

            .set-bilesen-row .urun-adi {
                font-weight: 600;
                color: #27ae60;
                flex: 1;
            }
        </style>

        <!-- Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <div class="icon">
                <i class="fa-solid fa-link"></i>
            </div>
            <div>
                <h2>Ürün Eşleştirme Yönetimi</h2>
                <div class="subtitle">Tekli ürün eşleştirmelerini ve set tanımlarını yönetin</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs-container animate__animated animate__fadeInUp">
            <div class="tab-buttons">
                <button type="button" class="tab-btn active" onclick="switchTab('cache')">
                    <i class="fa-solid fa-link"></i> Ürün Eşleştirme
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('sets')">
                    <i class="fa-solid fa-boxes-stacked"></i> Set Yönetimi
                </button>
            </div>
        </div>

        <!-- ====== TAB 1: ÜRÜN EŞLEŞTİRME ====== -->
        <div id="tabCache" class="tab-content active">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="number" id="statTotal">0</div>
                    <div class="label">Toplam Eşleşme</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="statRecent">0</div>
                    <div class="label">Son 7 Gün</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="statProducts">0</div>
                    <div class="label">Farklı Sistem Ürünü</div>
                </div>
            </div>

            <div class="toolbar">
                <input type="text" id="searchInput" placeholder="Ürün adı veya sistem ürünü ara..."
                    oninput="filterTable()" />
                <button type="button" class="btn-primary-custom" onclick="toggleAddForm()">
                    <i class="fa-solid fa-plus"></i> Yeni Eşleştirme
                </button>
                <button type="button" class="btn-danger-custom" id="btnBulkDelete" onclick="bulkDelete()"
                    style="display:none;">
                    <i class="fa-solid fa-trash-can"></i> Seçilenleri Sil (<span id="selectedCountLabel">0</span>)
                </button>
                <button type="button" class="btn-secondary-custom" onclick="loadCache()" style="margin-left:auto;">
                    <i class="fa-solid fa-rotate"></i> Yenile
                </button>
            </div>

            <!-- Add Form -->
            <div class="add-form-container" id="addFormContainer">
                <h4><i class="fa-solid fa-plus-circle" style="color:#8e44ad;"></i> Yeni Eşleştirme Ekle</h4>
                <div class="add-form-row">
                    <div class="form-group">
                        <label>Pazaryeri Ürün Adı (Excel'deki hali)</label>
                        <input type="text" id="addExcelName" placeholder="Örn: Pavia Berjer Ceviz Ahşap, Kırık Beyaz" />
                    </div>
                    <div class="form-group">
                        <label>Sistem Ürünü</label>
                        <select id="addProductSelect" style="width:100%;">
                            <option value="">Ürün Seçin...</option>
                        </select>
                    </div>
                    <button type="button" class="btn-primary-custom" onclick="addCacheEntry()"
                        style="height:36px; align-self:flex-end;">
                        <i class="fa-solid fa-check"></i> Kaydet
                    </button>
                    <button type="button" class="btn-secondary-custom" onclick="toggleAddForm()"
                        style="height:36px; align-self:flex-end;">
                        <i class="fa-solid fa-xmark"></i> İptal
                    </button>
                </div>
            </div>

            <!-- Grid -->
            <div class="grid-container" id="gridContainer">
                <table id="cacheTable">
                    <thead>
                        <tr>
                            <th style="width:30px;"><input type="checkbox" id="masterCheckbox"
                                    onchange="toggleAllRows(this)" /></th>
                            <th style="width:30px;">#</th>
                            <th>Pazaryeri Ürün Adı</th>
                            <th style="width:30px;"></th>
                            <th>Sistem Ürünü</th>
                            <th style="width:80px;">Tür</th>
                            <th style="width:130px;">Eklenme Tarihi</th>
                            <th style="width:80px;">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody id="cacheTableBody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ====== TAB 2: SET YÖNETİMİ ====== -->
        <div id="tabSets" class="tab-content">
            <div class="stats-row">
                <div class="stat-card">
                    <div class="number" id="statSetTotal">0</div>
                    <div class="label">Toplam Set</div>
                </div>
                <div class="stat-card">
                    <div class="number" id="statSetBilesen">0</div>
                    <div class="label">Toplam Bileşen</div>
                </div>
            </div>

            <div class="toolbar">
                <input type="text" id="searchSetInput" placeholder="Set adı ara..." oninput="filterSets()" />
                <button type="button" class="btn-primary-custom" onclick="toggleSetForm()">
                    <i class="fa-solid fa-plus"></i> Yeni Set Tanımla
                </button>
                <button type="button" class="btn-secondary-custom" onclick="loadSets()" style="margin-left:auto;">
                    <i class="fa-solid fa-rotate"></i> Yenile
                </button>
            </div>

            <!-- Set Add Form -->
            <div class="set-form-container" id="setFormContainer">
                <h4><i class="fa-solid fa-boxes-stacked" style="color:#e67e22;"></i> Yeni Set Tanımla</h4>

                <div class="add-form-row" style="margin-bottom:12px;">
                    <div class="form-group" style="flex:2;">
                        <label>Pazaryeri Set Adı (Excel'deki hali — tam olarak yazılmalı)</label>
                        <input type="text" id="setExcelName"
                            placeholder="Örn: Pavia Çay Seti Natürel Ahşap, Sütlü Kahve (1 Adet Tekli Berjer 1 Adet Puf)" />
                    </div>
                    <div class="form-group">
                        <label>Kısa Adı (isteğe bağlı)</label>
                        <input type="text" id="setShortName" placeholder="Örn: Pavia Çay Seti Sütlü Kahve" />
                    </div>
                </div>

                <h5 style="margin:0 0 8px 0; color:#6b5a42; font-size:0.85rem;">Bileşenler</h5>

                <div class="add-form-row" style="margin-bottom:8px;">
                    <div class="form-group" style="flex:2;">
                        <select id="setBilesenSelect" style="width:100%;">
                            <option value="">Bileşen ürünü seçin...</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 80px;">
                        <input type="number" id="setBilesenAdet" value="1" min="1" max="99"
                            style="text-align:center; font-weight:700;" />
                    </div>
                    <button type="button" class="btn-secondary-custom" onclick="addBilesen()"
                        style="height:36px; align-self:flex-end;">
                        <i class="fa-solid fa-plus"></i> Ekle
                    </button>
                </div>

                <div class="bilesen-list" id="bilesenList"></div>

                <div style="display:flex; gap:10px; margin-top:12px;">
                    <button type="button" class="btn-primary-custom" onclick="saveSetDefinition()">
                        <i class="fa-solid fa-check"></i> Seti Kaydet
                    </button>
                    <button type="button" class="btn-secondary-custom" onclick="toggleSetForm()">
                        <i class="fa-solid fa-xmark"></i> İptal
                    </button>
                </div>
            </div>

            <!-- Set List -->
            <div id="setListContainer" style="max-width:1200px; margin:0 auto 40px auto;"></div>
        </div>

        <script>
            var apiUrl = '/SiparisApi.ashx';
            var allItems = [];
            var selectedNos = new Set();
            var products = [];
            var allSets = [];
            var pendingBilesenler = [];

            // Sayfa yüklendiğinde
            $(document).ready(function () {
                loadCache();
                loadProducts();
                loadSets();
            });

            // ===== TAB SWITCHING =====
            function switchTab(tab) {
                $('.tab-btn').removeClass('active');
                $('.tab-content').removeClass('active');
                if (tab === 'cache') {
                    $('.tab-btn:first').addClass('active');
                    $('#tabCache').addClass('active');
                } else {
                    $('.tab-btn:last').addClass('active');
                    $('#tabSets').addClass('active');
                }
            }

            // ===== ÜRÜN LİSTESİ =====
            function loadProducts() {
                $.getJSON(apiUrl + '?action=getProducts', function (res) {
                    if (res.success) {
                        products = res.products || [];

                        var options = '<option value="">Ürün Seçin...</option>';
                        products.forEach(function (p) {
                            var label = p.sistemAdi || p.urunID || ('Ürün #' + p.no);
                            options += '<option value="Nihai_' + p.no + '">' + label + '</option>';
                        });
                        $('#addProductSelect').html(options);
                        $('#addProductSelect').select2({ placeholder: 'Ürün arayın...', allowClear: true, width: '100%' });

                        // Set bileşen selector
                        var setOpts = '<option value="">Bileşen ürünü seçin...</option>';
                        products.forEach(function (p) {
                            var label = p.sistemAdi || p.urunID || ('Ürün #' + p.no);
                            setOpts += '<option value="' + p.no + '" data-label="' + escapeHtml(label) + '">' + label + '</option>';
                        });
                        $('#setBilesenSelect').html(setOpts);
                        $('#setBilesenSelect').select2({ placeholder: 'Bileşen ürünü arayın...', allowClear: true, width: '100%' });
                    }
                });
            }

            // ===== TAB 1: ÜRÜN EŞLEŞTİRME =====
            function loadCache() {
                $.getJSON(apiUrl + '?action=getMatchCache', function (res) {
                    if (res.success) {
                        allItems = res.items || [];
                        selectedNos.clear();
                        updateBulkButton();
                        renderTable(allItems);
                        updateStats(allItems);
                    }
                });
            }

            function renderTable(items) {
                var tbody = document.getElementById('cacheTableBody');
                if (items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-link-slash"></i><h3>Henüz eşleştirme yok</h3><p>Manuel eşleştirmeler veya yukarıdaki form ile ekleyebilirsiniz.</p></div></td></tr>';
                    return;
                }
                var html = '';
                items.forEach(function (item, i) {
                    var isSelected = selectedNos.has(item.no);
                    html += '<tr data-no="' + item.no + '" class="' + (isSelected ? 'selected-row' : '') + '">' +
                        '<td><input type="checkbox" class="row-cb" data-no="' + item.no + '" ' + (isSelected ? 'checked' : '') + ' onchange="onRowCheckChange(this)" /></td>' +
                        '<td style="color:#999; font-size:0.78rem;">' + (i + 1) + '</td>' +
                        '<td style="font-weight:600; color:#8e44ad;">' + escapeHtml(item.excelUrunAdi) + '</td>' +
                        '<td><i class="fa-solid fa-arrow-right arrow-icon"></i></td>' +
                        '<td style="font-weight:600; color:#27ae60;">' + escapeHtml(item.sistemUrunAdi || 'Ürün #' + item.eslesenUrunNo) + '</td>' +
                        '<td><span class="badge-tur">' + item.eslesenUrunTur + '</span></td>' +
                        '<td style="font-size:0.78rem; color:#888;">' + item.olusturmaTarihi + '</td>' +
                        '<td><button type="button" class="btn-delete-row" onclick="deleteEntry(' + item.no + ')"><i class="fa-solid fa-trash"></i></button></td></tr>';
                });
                tbody.innerHTML = html;
            }

            function updateStats(items) {
                document.getElementById('statTotal').textContent = items.length;
                var recent = 0, now = new Date();
                items.forEach(function (item) {
                    var parts = item.olusturmaTarihi.split(' ')[0].split('.');
                    var d = new Date(parts[2], parts[1] - 1, parts[0]);
                    if ((now - d) / (1000 * 60 * 60 * 24) <= 7) recent++;
                });
                document.getElementById('statRecent').textContent = recent;
                var unique = new Set();
                items.forEach(function (item) { unique.add(item.eslesenUrunNo); });
                document.getElementById('statProducts').textContent = unique.size;
            }

            function filterTable() {
                var q = document.getElementById('searchInput').value.toLowerCase().trim();
                if (!q) { renderTable(allItems); return; }
                renderTable(allItems.filter(function (item) {
                    return item.excelUrunAdi.toLowerCase().indexOf(q) >= 0 ||
                        (item.sistemUrunAdi && item.sistemUrunAdi.toLowerCase().indexOf(q) >= 0);
                }));
            }

            function toggleAddForm() {
                var form = document.getElementById('addFormContainer');
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            }

            function addCacheEntry() {
                var excelName = document.getElementById('addExcelName').value.trim();
                var compositeVal = $('#addProductSelect').val();
                if (!excelName) { Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Pazaryeri ürün adını girin.' }); return; }
                if (!compositeVal) { Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Sistem ürünü seçin.' }); return; }
                var parts = compositeVal.split('_');
                $.ajax({
                    url: apiUrl + '?action=addMatchCache', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ excelUrunAdi: excelName, eslesenUrunNo: parseInt(parts[1]), eslesenUrunTur: parts[0] }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Kaydedildi!', text: res.message, confirmButtonColor: '#8e44ad' });
                            document.getElementById('addExcelName').value = '';
                            $('#addProductSelect').val('').trigger('change');
                            toggleAddForm(); loadCache();
                        } else { Swal.fire({ icon: 'error', title: 'Hata', text: res.message }); }
                    }
                });
            }

            function deleteEntry(no) {
                Swal.fire({
                    icon: 'warning', title: 'Eşleştirmeyi Sil',
                    text: 'Bu eşleştirmeyi silmek istediğinize emin misiniz?',
                    showCancelButton: true, confirmButtonText: 'Evet, Sil', cancelButtonText: 'İptal',
                    confirmButtonColor: '#c0392b', reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: apiUrl + '?action=deleteMatchCache', type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({ no: no }), dataType: 'json',
                            success: function (res) {
                                if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, confirmButtonColor: '#8e44ad', timer: 1500 }); loadCache(); }
                            }
                        });
                    }
                });
            }

            function onRowCheckChange(cb) {
                var no = parseInt(cb.getAttribute('data-no'));
                if (cb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
                else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
                updateBulkButton();
            }

            function toggleAllRows(masterCb) {
                document.querySelectorAll('.row-cb').forEach(function (cb) {
                    cb.checked = masterCb.checked;
                    var no = parseInt(cb.getAttribute('data-no'));
                    if (masterCb.checked) { selectedNos.add(no); cb.closest('tr').classList.add('selected-row'); }
                    else { selectedNos.delete(no); cb.closest('tr').classList.remove('selected-row'); }
                });
                updateBulkButton();
            }

            function updateBulkButton() {
                var btn = document.getElementById('btnBulkDelete');
                if (selectedNos.size > 0) { btn.style.display = 'inline-flex'; document.getElementById('selectedCountLabel').textContent = selectedNos.size; }
                else { btn.style.display = 'none'; }
                document.getElementById('masterCheckbox').checked = false;
            }

            function bulkDelete() {
                if (selectedNos.size === 0) return;
                Swal.fire({
                    icon: 'warning', title: 'Toplu Silme',
                    html: '<strong>' + selectedNos.size + '</strong> eşleştirmeyi silmek istediğinize emin misiniz?',
                    showCancelButton: true, confirmButtonText: 'Evet, Hepsini Sil', cancelButtonText: 'İptal',
                    confirmButtonColor: '#c0392b', reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'Siliniyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=deleteMatchCache', type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({ noList: Array.from(selectedNos) }), dataType: 'json',
                            success: function (res) {
                                if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, confirmButtonColor: '#8e44ad' }); loadCache(); }
                            }
                        });
                    }
                });
            }

            // ===== TAB 2: SET YÖNETİMİ =====
            function loadSets() {
                $.getJSON(apiUrl + '?action=getSetDefinitions', function (res) {
                    if (res.success) {
                        allSets = res.sets || [];
                        renderSets(allSets);
                        updateSetStats(allSets);
                    }
                });
            }

            function renderSets(sets) {
                var container = document.getElementById('setListContainer');
                if (sets.length === 0) {
                    container.innerHTML = '<div class="grid-container"><div class="empty-state"><i class="fa-solid fa-boxes-stacked"></i><h3>Henüz set tanımı yok</h3><p>Yukarıdaki butonla yeni set tanımlayabilirsiniz.</p></div></div>';
                    return;
                }
                var html = '';
                sets.forEach(function (set) {
                    var bilesenCount = set.icerikler ? set.icerikler.length : 0;
                    var name = set.setAdi || set.excelSetAdi;

                    html += '<div class="set-card" data-setno="' + set.no + '">';
                    html += '<div class="set-card-header" onclick="toggleSetCard(' + set.no + ')">';
                    html += '<div class="set-icon"><i class="fa-solid fa-boxes-stacked"></i></div>';
                    html += '<div class="set-info">';
                    html += '<div class="set-name">' + escapeHtml(name) + '</div>';
                    if (set.setAdi && set.excelSetAdi !== set.setAdi) {
                        html += '<div class="set-excel">' + escapeHtml(set.excelSetAdi) + '</div>';
                    }
                    html += '</div>';
                    html += '<div class="set-meta">';
                    html += '<span class="badge-set">' + bilesenCount + ' bileşen</span>';
                    html += '<span style="font-size:0.75rem; color:#888;">' + set.olusturmaTarihi + '</span>';
                    html += '<button type="button" class="btn-delete-row" onclick="event.stopPropagation(); deleteSet(' + set.no + ')"><i class="fa-solid fa-trash"></i></button>';
                    html += '</div></div>';

                    // Body — bileşenler
                    html += '<div class="set-card-body" id="setBody_' + set.no + '">';
                    if (set.icerikler && set.icerikler.length > 0) {
                        set.icerikler.forEach(function (ic) {
                            html += '<div class="set-bilesen-row">';
                            html += '<div class="adet-badge">' + ic.adet + '</div>';
                            html += '<div class="urun-adi">' + escapeHtml(ic.urunAdi || 'Ürün #' + ic.urunNo) + '</div>';
                            html += '</div>';
                        });
                    }
                    html += '</div></div>';
                });
                container.innerHTML = html;
            }

            function updateSetStats(sets) {
                document.getElementById('statSetTotal').textContent = sets.length;
                var totalBilesen = 0;
                sets.forEach(function (s) { totalBilesen += (s.icerikler ? s.icerikler.length : 0); });
                document.getElementById('statSetBilesen').textContent = totalBilesen;
            }

            function filterSets() {
                var q = document.getElementById('searchSetInput').value.toLowerCase().trim();
                if (!q) { renderSets(allSets); return; }
                renderSets(allSets.filter(function (s) {
                    return s.excelSetAdi.toLowerCase().indexOf(q) >= 0 ||
                        (s.setAdi && s.setAdi.toLowerCase().indexOf(q) >= 0);
                }));
            }

            function toggleSetCard(setNo) {
                var body = document.getElementById('setBody_' + setNo);
                body.classList.toggle('open');
            }

            function toggleSetForm() {
                var form = document.getElementById('setFormContainer');
                if (form.style.display === 'none') {
                    form.style.display = 'block';
                    pendingBilesenler = [];
                    renderBilesenList();
                    document.getElementById('setExcelName').value = '';
                    document.getElementById('setShortName').value = '';
                } else {
                    form.style.display = 'none';
                }
            }

            function addBilesen() {
                var sel = document.getElementById('setBilesenSelect');
                var urunNo = parseInt(sel.value);
                if (!urunNo) { Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Bileşen ürünü seçin.' }); return; }
                var adet = parseInt(document.getElementById('setBilesenAdet').value) || 1;
                var label = sel.options[sel.selectedIndex].text;

                // Zaten ekliyse adetin güncelle
                var found = false;
                pendingBilesenler.forEach(function (b) {
                    if (b.urunNo === urunNo) { b.adet += adet; found = true; }
                });
                if (!found) {
                    pendingBilesenler.push({ urunNo: urunNo, adet: adet, label: label });
                }
                renderBilesenList();
                $('#setBilesenSelect').val('').trigger('change');
                document.getElementById('setBilesenAdet').value = '1';
            }

            function removeBilesen(idx) {
                pendingBilesenler.splice(idx, 1);
                renderBilesenList();
            }

            function renderBilesenList() {
                var html = '';
                if (pendingBilesenler.length === 0) {
                    html = '<div style="color:#888; font-size:0.8rem; padding:8px;">Henüz bileşen eklenmedi. Yukarıdan ürün seçip "Ekle" butonuna basın.</div>';
                } else {
                    pendingBilesenler.forEach(function (b, i) {
                        html += '<div class="bilesen-item">';
                        html += '<div class="adet-badge" style="background:#e67e22; color:#fff; border-radius:50%; width:26px; height:26px; display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700;">' + b.adet + '</div>';
                        html += '<div class="bilesen-urun">' + escapeHtml(b.label) + '</div>';
                        html += '<div class="bilesen-adet"><input type="number" value="' + b.adet + '" min="1" max="99" onchange="updateBilesenAdet(' + i + ', this.value)" /></div>';
                        html += '<span class="bilesen-remove" onclick="removeBilesen(' + i + ')"><i class="fa-solid fa-circle-xmark"></i></span>';
                        html += '</div>';
                    });
                }
                document.getElementById('bilesenList').innerHTML = html;
            }

            function updateBilesenAdet(idx, val) {
                pendingBilesenler[idx].adet = parseInt(val) || 1;
                renderBilesenList();
            }

            function saveSetDefinition() {
                var excelName = document.getElementById('setExcelName').value.trim();
                var shortName = document.getElementById('setShortName').value.trim();

                if (!excelName) { Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'Pazaryeri set adını girin.' }); return; }
                if (pendingBilesenler.length === 0) { Swal.fire({ icon: 'warning', title: 'Uyarı', text: 'En az bir bileşen eklemelisiniz.' }); return; }

                Swal.fire({ title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                var payload = {
                    excelSetAdi: excelName,
                    setAdi: shortName,
                    icerikler: pendingBilesenler.map(function (b) { return { urunNo: b.urunNo, adet: b.adet }; })
                };

                $.ajax({
                    url: apiUrl + '?action=addSetDefinition', type: 'POST', contentType: 'application/json',
                    data: JSON.stringify(payload), dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Set Kaydedildi!', text: res.message, confirmButtonColor: '#e67e22' });
                            toggleSetForm();
                            loadSets();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                        }
                    },
                    error: function (xhr) { Swal.fire({ icon: 'error', title: 'Sunucu Hatası', text: xhr.statusText }); }
                });
            }

            function deleteSet(no) {
                Swal.fire({
                    icon: 'warning', title: 'Seti Sil',
                    text: 'Bu set tanımını ve tüm bileşenlerini silmek istediğinize emin misiniz?',
                    showCancelButton: true, confirmButtonText: 'Evet, Sil', cancelButtonText: 'İptal',
                    confirmButtonColor: '#c0392b', reverseButtons: true
                }).then(function (result) {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: apiUrl + '?action=deleteSetDefinition', type: 'POST', contentType: 'application/json',
                            data: JSON.stringify({ no: no }), dataType: 'json',
                            success: function (res) {
                                if (res.success) { Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, confirmButtonColor: '#e67e22', timer: 1500 }); loadSets(); }
                            }
                        });
                    }
                });
            }

            // ===== UTILS =====
            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(text));
                return div.innerHTML;
            }
        </script>

@endsection