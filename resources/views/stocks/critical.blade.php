@extends('layouts.app')

@section('content')

        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
            rel="stylesheet" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>

        <style>
            .page-header {
                text-align: center;
                margin-bottom: 25px;
            }

            .page-header h2 {
                color: #39261e;
                font-weight: 700;
                margin-bottom: 4px;
            }

            .page-header .subtitle {
                color: #80694d;
                font-size: 0.9rem;
            }

            /* Stats row */
            .stats-row {
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
                justify-content: center;
                margin-bottom: 25px;
            }

            .stat-card-cs {
                background: #fff;
                border-radius: 14px;
                padding: 18px 24px;
                text-align: center;
                min-width: 150px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
                transition: all 0.3s;
            }

            .stat-card-cs:hover {
                transform: translateY(-3px);
                box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
            }

            .stat-card-cs .sc-icon {
                font-size: 1.6rem;
                margin-bottom: 6px;
            }

            .stat-card-cs .sc-val {
                font-size: 1.8rem;
                font-weight: 700;
                color: #39261e;
            }

            .stat-card-cs .sc-lbl {
                font-size: 0.78rem;
                color: #80694d;
                font-weight: 500;
            }

            /* Add form */
            .add-card {
                background: #fff;
                border-radius: 14px;
                padding: 22px 28px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
                margin-bottom: 25px;
            }

            .add-card h5 {
                color: #39261e;
                font-weight: 600;
                margin-bottom: 16px;
            }

            .form-row {
                display: flex;
                gap: 14px;
                flex-wrap: wrap;
                align-items: flex-end;
            }

            .form-group {
                display: flex;
                flex-direction: column;
            }

            .form-group label {
                font-size: 0.78rem;
                font-weight: 600;
                color: #80694d;
                margin-bottom: 4px;
            }

            .form-group select,
            .form-group input {
                padding: 8px 12px;
                border: 1.5px solid #e0d5c7;
                border-radius: 8px;
                font-size: 0.85rem;
                outline: none;
                transition: all 0.2s;
                background: #fffdf8;
            }

            .form-group select:focus,
            .form-group input:focus {
                border-color: #D4AF37;
                box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
            }

            .form-group select {
                min-width: 280px;
            }

            .form-group input[type="number"] {
                width: 90px;
            }

            /* Toggle switch */
            .toggle-wrap {
                display: flex;
                align-items: center;
                gap: 8px;
                height: 36px;
            }

            .toggle-switch {
                position: relative;
                width: 42px;
                height: 22px;
                cursor: pointer;
            }

            .toggle-switch input {
                display: none;
            }

            .toggle-slider {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #ccc;
                border-radius: 22px;
                transition: 0.3s;
            }

            .toggle-slider::before {
                content: '';
                position: absolute;
                left: 3px;
                top: 3px;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                background: #fff;
                transition: 0.3s;
            }

            .toggle-switch input:checked+.toggle-slider {
                background: #2196F3;
            }

            .toggle-switch input:checked+.toggle-slider::before {
                transform: translateX(20px);
            }

            .toggle-label {
                font-size: 0.78rem;
                color: #80694d;
                font-weight: 500;
            }

            .btn-ekle {
                background: linear-gradient(135deg, #D4AF37, #b9922c);
                color: #fff;
                border: none;
                padding: 8px 22px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
                height: 36px;
            }

            .btn-ekle:hover {
                opacity: 0.9;
                transform: translateY(-1px);
            }

            /* Table */
            .table-card {
                background: #fff;
                border-radius: 14px;
                padding: 20px 24px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
                margin-bottom: 25px;
            }

            .table-card h5 {
                color: #39261e;
                font-weight: 600;
                margin-bottom: 14px;
            }

            .esik-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 0.82rem;
            }

            .esik-table thead th {
                background: #39261e;
                color: #f4e9d8;
                padding: 10px 12px;
                font-weight: 600;
                text-align: left;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .esik-table thead th:first-child {
                border-radius: 10px 0 0 0;
            }

            .esik-table thead th:last-child {
                border-radius: 0 10px 0 0;
            }

            .esik-table tbody tr {
                transition: background 0.15s;
            }

            .esik-table tbody tr:hover {
                background: #fdf6ea;
            }

            .esik-table tbody td {
                padding: 10px 12px;
                border-bottom: 1px solid #f0e8da;
                vertical-align: middle;
            }

            .esik-table .badge-kritik {
                background: #fde8e8;
                color: #c0392b;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 600;
            }

            .esik-table .badge-uyari {
                background: #fff3cd;
                color: #856404;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 600;
            }

            .esik-table .badge-normal {
                background: #d4edda;
                color: #155724;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 0.72rem;
                font-weight: 600;
            }

            .progress-bar-wrap {
                background: #f0e8da;
                border-radius: 6px;
                height: 8px;
                overflow: hidden;
                min-width: 80px;
            }

            .progress-bar-fill {
                height: 100%;
                border-radius: 6px;
                transition: width 0.5s;
            }

            .btn-sil {
                background: #e74c3c;
                color: #fff;
                border: none;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 0.72rem;
                cursor: pointer;
                font-weight: 600;
            }

            .btn-sil:hover {
                opacity: 0.85;
            }

            .btn-duzenle {
                background: #D4AF37;
                color: #fff;
                border: none;
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 0.72rem;
                cursor: pointer;
                font-weight: 600;
                margin-right: 4px;
            }

            .btn-duzenle:hover {
                opacity: 0.85;
            }

            /* Log panel */
            .log-card {
                background: #fff;
                border-radius: 14px;
                padding: 20px 24px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            }

            .log-card h5 {
                color: #39261e;
                font-weight: 600;
                margin-bottom: 14px;
            }

            .log-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                font-size: 0.8rem;
            }

            .log-table thead th {
                background: #80694d;
                color: #f4e9d8;
                padding: 8px 10px;
                font-weight: 600;
                font-size: 0.72rem;
                text-transform: uppercase;
            }

            .log-table thead th:first-child {
                border-radius: 8px 0 0 0;
            }

            .log-table thead th:last-child {
                border-radius: 0 8px 0 0;
            }

            .log-table tbody td {
                padding: 8px 10px;
                border-bottom: 1px solid #f0e8da;
            }

            .log-unread {
                background: #fffef5;
                font-weight: 500;
            }

            .log-type-kritik {
                color: #c0392b;
                font-weight: 700;
            }

            .log-type-uyari {
                color: #e67e22;
                font-weight: 600;
            }

            .empty-state {
                text-align: center;
                padding: 40px;
                color: #b0a28e;
            }

            .empty-state i {
                font-size: 3rem;
                margin-bottom: 14px;
                display: block;
            }

            /* Search Inputs */
            .search-input-wrap {
                margin-bottom: 12px;
                position: relative;
            }

            .search-input {
                width: 100%;
                padding: 8px 12px 8px 36px;
                border: 1.5px solid #e0d5c7;
                border-radius: 8px;
                font-size: 0.82rem;
                outline: none;
                background: #fffdf8;
                transition: all 0.2s;
            }

            .search-input:focus {
                border-color: #D4AF37;
                box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
            }

            .search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #b0a28e;
                font-size: 0.85rem;
            }

            .table-search-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }

            .bulk-actions {
                display: flex;
                gap: 10px;
            }

            .btn-bulk {
                background: #80694d;
                color: #fff;
                border: none;
                padding: 6px 14px;
                border-radius: 8px;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-bulk:hover {
                background: #39261e;
            }

            .btn-reset {
                background: #c0392b;
                color: #fff;
                border: none;
                padding: 6px 14px;
                border-radius: 8px;
                font-size: 0.75rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-reset:hover {
                background: #962d22;
                box-shadow: 0 4px 12px rgba(192, 57, 43, 0.2);
            }

            @media (max-width: 768px) {
                .form-row {
                    flex-direction: column;
                }

                .form-group select {
                    min-width: 100%;
                }

                .table-search-row {
                    flex-direction: column;
                    gap: 10px;
                    align-items: stretch;
                }
            }
        </style>

        <!-- Page Header -->
        <div class="page-header">
            <h2><i class="fa-solid fa-triangle-exclamation" style="color:#D4AF37;"></i> Kritik Stok Eşiği</h2>
            <span class="subtitle">Ürünler için minimum stok eşiklerini tanımlayın — stok düşünce uyarı alın</span>
        </div>

        <!-- Stats -->
        <div class="stats-row" id="statsRow">
            <div class="stat-card-cs">
                <div class="sc-icon">📋</div>
                <div class="sc-val" id="statToplam">0</div>
                <div class="sc-lbl">Tanımlı Eşik</div>
            </div>
            <div class="stat-card-cs">
                <div class="sc-icon">🔴</div>
                <div class="sc-val" id="statKritik">0</div>
                <div class="sc-lbl">Kritik</div>
            </div>
            <div class="stat-card-cs">
                <div class="sc-icon">🟡</div>
                <div class="sc-val" id="statUyari">0</div>
                <div class="sc-lbl">Uyarı</div>
            </div>
            <div class="stat-card-cs">
                <div class="sc-icon">🟢</div>
                <div class="sc-val" id="statNormal">0</div>
                <div class="sc-lbl">Normal</div>
            </div>
        </div>

        <!-- Add Form -->
        <div class="add-card">
            <h5><i class="fa-solid fa-plus-circle" style="color:#D4AF37;"></i> Yeni Eşik Tanımla</h5>
            <div class="form-row">
                <div class="form-group">
                    <label>Ürün Seçin</label>
                    <div class="search-input-wrap" style="margin-bottom:6px;">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="txtSearchUrun" class="search-input"
                            placeholder="Ürün adı veya kod yazın..." onkeyup="filterDropdown()" />
                    </div>
                    <select id="ddUrun">
                        <option value="0">Yükleniyor...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Eşik Miktarı</label>
                    <input type="number" id="txtEsik" value="5" min="1" />
                </div>
                <div class="form-group">
                    <label>Oto. İş Emri Adedi</label>
                    <input type="number" id="txtIsEmriAdet" value="10" min="1" />
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="toggle-wrap">
                        <label class="toggle-switch">
                            <input type="checkbox" id="chkOto" />
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Oto. İş Emri</span>
                    </div>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="button" class="btn-ekle" onclick="saveThreshold()">
                        <i class="fa-solid fa-plus"></i> Ekle
                    </button>
                </div>
            </div>
        </div>

        <!-- Thresholds Table -->
        <div class="table-card">
            <div class="table-search-row">
                <h5><i class="fa-solid fa-list-check" style="color:#D4AF37;"></i> Tanımlı Eşikler</h5>
                <div class="bulk-actions">
                    <div class="search-input-wrap" style="margin-bottom:0; min-width:250px;">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="txtSearchTable" class="search-input" placeholder="Tabloda ara..."
                            onkeyup="filterTable()" />
                    </div>
                    <button type="button" class="btn-bulk" onclick="bulkSetThreshold()">
                        <i class="fa-solid fa-layer-group"></i> Toplu Eşik Tanımla
                    </button>
                    <button type="button" class="btn-reset" onclick="resetThresholds()">
                        <i class="fa-solid fa-trash-can"></i> Tümünü Sıfırla
                    </button>
                </div>
            </div>
            <div style="overflow-x:auto;">
                <table class="esik-table">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th>Kategori</th>
                            <th style="width:80px;">Eşik</th>
                            <th style="width:80px;">Mevcut</th>
                            <th style="width:120px;">Doluluk</th>
                            <th style="width:70px;">Durum</th>
                            <th style="width:80px;">Oto. İş Emri</th>
                            <th style="width:90px;">Son Uyarı</th>
                            <th style="width:100px;">Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyEsikler"></tbody>
                </table>
                <div id="emptyEsik" class="empty-state" style="display:none;">
                    <i class="fa-solid fa-box-open"></i>
                    <div>Henüz eşik tanımı yok. Yukarıdan ürün seçerek başlayın.</div>
                </div>
            </div>
        </div>

        <!-- Alert Log -->
        <div class="log-card">
            <h5><i class="fa-solid fa-bell" style="color:#e67e22;"></i> Son Uyarı Logları</h5>
            <div style="overflow-x:auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Ürün</th>
                            <th>Eşik</th>
                            <th>Mevcut</th>
                            <th>Tip</th>
                            <th>Oto. İş Emri</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyLogs"></tbody>
                </table>
                <div id="emptyLogs" class="empty-state" style="display:none;">
                    <i class="fa-solid fa-check-circle"></i>
                    <div>Henüz uyarı kaydı yok.</div>
                </div>
            </div>
        </div>

        <script>
            var apiUrl = 'SiparisApi.ashx';
            var thresholdData = [];
            var availableProducts = [];

            // ========================= LOAD =========================
            function loadAll() {
                $.ajax({
                    url: apiUrl + '?action=getThresholds',
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        if (!res.success) { Swal.fire('Hata', res.message, 'error'); return; }
                        thresholdData = res.thresholds || [];
                        availableProducts = res.available || [];
                        renderStats();
                        renderTable();
                        renderDropdown();
                    },
                    error: function () {
                        Swal.fire('Hata', 'Sunucu ile bağlantı kurulamadı.', 'error');
                    }
                });

                // Load logs too
                $.ajax({
                    url: apiUrl + '?action=getCriticalStockAlerts',
                    type: 'GET',
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) renderLogs(res.logs || []);
                    }
                });
            }

            // ========================= RENDER =========================
            function renderStats() {
                var kritik = 0, uyari = 0, normal = 0;
                thresholdData.forEach(function (t) {
                    if (t.durum === 'Kritik') kritik++;
                    else if (t.durum === 'Uyari') uyari++;
                    else normal++;
                });
                document.getElementById('statToplam').textContent = thresholdData.length;
                document.getElementById('statKritik').textContent = kritik;
                document.getElementById('statUyari').textContent = uyari;
                document.getElementById('statNormal').textContent = normal;
            }

            function renderTable() {
                var tbody = document.getElementById('tbodyEsikler');
                var empty = document.getElementById('emptyEsik');
                tbody.innerHTML = '';

                if (thresholdData.length === 0) {
                    empty.style.display = 'block';
                    return;
                }
                empty.style.display = 'none';

                thresholdData.forEach(function (t) {
                    var tr = document.createElement('tr');
                    var pct = t.esikMiktar > 0 ? Math.min(Math.round((t.mevcutStok / t.esikMiktar) * 100), 100) : 0;
                    var barColor = t.durum === 'Kritik' ? '#e74c3c' : (t.durum === 'Uyari' ? '#f39c12' : '#27ae60');
                    var badgeClass = t.durum === 'Kritik' ? 'badge-kritik' : (t.durum === 'Uyari' ? 'badge-uyari' : 'badge-normal');
                    var badgeIcon = t.durum === 'Kritik' ? '🔴' : (t.durum === 'Uyari' ? '🟡' : '🟢');

                    tr.innerHTML =
                        '<td><strong>' + escapeHtml(t.araUrunAdi) + '</strong></td>' +
                        '<td style="color:#80694d;">' + escapeHtml(t.urunCesidi || '—') + '</td>' +
                        '<td style="text-align:center; font-weight:600;">' + t.esikMiktar + '</td>' +
                        '<td style="text-align:center; font-weight:700; color:' + barColor + ';">' + t.mevcutStok + '</td>' +
                        '<td><div class="progress-bar-wrap"><div class="progress-bar-fill" style="width:' + pct + '%; background:' + barColor + ';"></div></div><div style="font-size:0.7rem; color:#999; margin-top:2px;">%' + pct + '</div></td>' +
                        '<td><span class="' + badgeClass + '">' + badgeIcon + ' ' + (t.durum === 'Uyari' ? 'Uyarı' : t.durum) + '</span></td>' +
                        '<td style="text-align:center;">' + (t.otomatikIsEmri ? '<span style="color:#2196F3; font-weight:600;">✓ ' + t.isEmriAdet + ' ad.</span>' : '<span style="color:#ccc;">—</span>') + '</td>' +
                        '<td style="font-size:0.72rem; color:#999;">' + (t.sonUyariTarihi || '—') + '</td>' +
                        '<td>' +
                        '<button class="btn-duzenle" data-no="' + t.araUrunAdiNo + '" data-esik="' + t.esikMiktar + '" data-oto="' + (t.otomatikIsEmri ? '1' : '0') + '" data-adet="' + t.isEmriAdet + '" onclick="editThresholdFromBtn(this)"><i class="fa-solid fa-pen"></i></button>' +
                        '<button class="btn-sil" data-no="' + t.no + '" data-adi="' + escapeHtml(t.araUrunAdi) + '" onclick="deleteThresholdFromBtn(this)"><i class="fa-solid fa-trash"></i></button>' +
                        '</td>';

                    tbody.appendChild(tr);
                });
            }

            function renderDropdown() {
                var dd = document.getElementById('ddUrun');
                var search = document.getElementById('txtSearchUrun').value.toLocaleLowerCase('tr-TR');

                dd.innerHTML = '<option value="0">-- Ürün Seçin (' + availableProducts.length + ' adet) --</option>';

                var filtered = availableProducts;
                if (search) {
                    filtered = availableProducts.filter(function (p) {
                        return p.araUrunAdi.toLocaleLowerCase('tr-TR').indexOf(search) > -1 ||
                            (p.urunCesidi && p.urunCesidi.toLocaleLowerCase('tr-TR').indexOf(search) > -1) ||
                            p.araUrunAdiNo.toString().indexOf(search) > -1;
                    });
                }

                filtered.forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = p.araUrunAdiNo;
                    opt.textContent = p.araUrunAdi + (p.urunCesidi ? ' (' + p.urunCesidi + ')' : '') + ' — Stok: ' + p.mevcutStok;
                    dd.appendChild(opt);
                });

                if (filtered.length === 0 && search) {
                    var opt = document.createElement('option');
                    opt.value = "0";
                    opt.textContent = "Sonuç bulunamadı.";
                    dd.appendChild(opt);
                }
            }

            function filterDropdown() {
                renderDropdown();
            }

            function filterTable() {
                var search = document.getElementById('txtSearchTable').value.toLocaleLowerCase('tr-TR');
                var rows = document.querySelectorAll('#tbodyEsikler tr');

                rows.forEach(function (row) {
                    var text = row.innerText.toLocaleLowerCase('tr-TR');
                    if (text.indexOf(search) > -1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            function renderLogs(logs) {
                var tbody = document.getElementById('tbodyLogs');
                var empty = document.getElementById('emptyLogs');
                tbody.innerHTML = '';

                if (!logs || logs.length === 0) {
                    empty.style.display = 'block';
                    return;
                }
                empty.style.display = 'none';

                logs.forEach(function (l) {
                    var tr = document.createElement('tr');
                    if (!l.okundu) tr.className = 'log-unread';
                    var tipClass = l.uyariTipi === 'Kritik' ? 'log-type-kritik' : 'log-type-uyari';
                    tr.innerHTML =
                        '<td>' + l.tarih + '</td>' +
                        '<td>' + escapeHtml(l.araUrunAdi) + '</td>' +
                        '<td style="text-align:center;">' + l.esikMiktar + '</td>' +
                        '<td style="text-align:center; font-weight:600;">' + l.mevcutStok + '</td>' +
                        '<td><span class="' + tipClass + '">' + (l.uyariTipi === 'Kritik' ? '🔴 Kritik' : '🟡 Uyarı') + '</span></td>' +
                        '<td style="text-align:center;">' + (l.otomatikIsEmriVerildi ? '✅ Verildi' : '—') + '</td>';
                    tbody.appendChild(tr);
                });
            }

            // ========================= ACTIONS =========================
            function saveThreshold(araUrunAdiNoOverride, esikOverride, otoOverride, adetOverride) {
                var araUrunAdiNo = (araUrunAdiNoOverride !== undefined && araUrunAdiNoOverride !== null) ? araUrunAdiNoOverride : parseInt(document.getElementById('ddUrun').value);
                var esikMiktar = (esikOverride !== undefined && esikOverride !== null) ? esikOverride : parseInt(document.getElementById('txtEsik').value);
                var otomatikIsEmri = (otoOverride !== undefined && otoOverride !== null) ? otoOverride : document.getElementById('chkOto').checked;
                var isEmriAdet = (adetOverride !== undefined && adetOverride !== null) ? adetOverride : parseInt(document.getElementById('txtIsEmriAdet').value);

                if (!araUrunAdiNo || araUrunAdiNo <= 0) {
                    Swal.fire('Uyarı', 'Lütfen bir ürün seçin.', 'warning');
                    return;
                }
                if (isNaN(esikMiktar) || esikMiktar < 1) {
                    Swal.fire('Uyarı', 'Eşik miktarı en az 1 olmalıdır.', 'warning');
                    return;
                }

                Swal.fire({ title: 'Kaydediliyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                $.ajax({
                    url: apiUrl + '?action=saveThreshold',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        araUrunAdiNo: araUrunAdiNo,
                        esikMiktar: esikMiktar,
                        otomatikIsEmri: otomatikIsEmri,
                        isEmriAdet: isEmriAdet,
                        aktif: true
                    }),
                    dataType: 'json',
                    success: function (res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Kaydedildi!', text: res.message, confirmButtonColor: '#D4AF37', timer: 1500 });
                            loadAll();
                        } else {
                            Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                        }
                    },
                    error: function () { Swal.fire('Hata', 'Sunucu hatası.', 'error'); }
                });
            }

            // data-attribute tabanlı düzenleme (XSS-safe)
            function editThresholdFromBtn(btn) {
                var araUrunAdiNo = parseInt(btn.getAttribute('data-no'));
                var currentEsik = parseInt(btn.getAttribute('data-esik'));
                var currentOto = btn.getAttribute('data-oto') === '1';
                var currentAdet = parseInt(btn.getAttribute('data-adet'));
                editThreshold(araUrunAdiNo, currentEsik, currentOto, currentAdet);
            }

            function editThreshold(araUrunAdiNo, currentEsik, currentOto, currentAdet) {
                Swal.fire({
                    title: 'Eşik Düzenle',
                    html:
                        '<div style="text-align:left; font-size:0.9rem;">' +
                        '<label style="font-weight:600;">Eşik Miktarı:</label>' +
                        '<input id="swalEsik" type="number" class="swal2-input" value="' + currentEsik + '" min="1" style="width:100%;" />' +
                        '<label style="font-weight:600; margin-top:8px; display:block;">Oto. İş Emri Adedi:</label>' +
                        '<input id="swalAdet" type="number" class="swal2-input" value="' + currentAdet + '" min="1" style="width:100%;" />' +
                        '<label style="margin-top:8px; display:flex; align-items:center; gap:8px;">' +
                        '<input id="swalOto" type="checkbox" ' + (currentOto ? 'checked' : '') + ' /> Otomatik İş Emri' +
                        '</label></div>',
                    confirmButtonText: 'Güncelle',
                    confirmButtonColor: '#D4AF37',
                    showCancelButton: true,
                    cancelButtonText: 'Vazgeç',
                    preConfirm: function () {
                        return {
                            esik: parseInt(document.getElementById('swalEsik').value),
                            oto: document.getElementById('swalOto').checked,
                            adet: parseInt(document.getElementById('swalAdet').value)
                        };
                    }
                }).then(function (result) {
                    if (result.isConfirmed) {
                        saveThreshold(araUrunAdiNo, result.value.esik, result.value.oto, result.value.adet);
                    }
                });
            }

            // data-attribute tabanlı silme (XSS-safe)
            function deleteThresholdFromBtn(btn) {
                var no = parseInt(btn.getAttribute('data-no'));
                var urunAdi = btn.getAttribute('data-adi');
                deleteThreshold(no, urunAdi);
            }

            function deleteThreshold(no, urunAdi) {
                Swal.fire({
                    title: 'Eşik Tanımını Sil',
                    html: '<b>' + escapeHtml(urunAdi) + '</b> için kritik stok eşiği silinsin mi?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Sil',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#80694d'
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    $.ajax({
                        url: apiUrl + '?action=deleteThreshold',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ no: no }),
                        dataType: 'json',
                        success: function (res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, timer: 1200, confirmButtonColor: '#D4AF37' });
                                loadAll();
                            } else {
                                Swal.fire('Hata', res.message, 'error');
                            }
                        },
                        error: function () { Swal.fire('Hata', 'Sunucu hatası.', 'error'); }
                    });
                });
            }

            // ========================= TOPLU EŞİK AYARLAMA =========================
            function bulkSetThreshold() {
                if (availableProducts.length === 0) {
                    Swal.fire('Bilgi', 'Eşik tanımlanmamış ürün kalmadı.', 'info');
                    return;
                }
                Swal.fire({
                    title: 'Toplu Eşik Tanımla',
                    html:
                        '<div style="text-align:left; font-size:0.9rem;">' +
                        '<p style="color:#80694d; margin-bottom:10px;">Henüz eşik tanımı olmayan <b>' + availableProducts.length + '</b> ürüne aynı eşik değeri atanacak.</p>' +
                        '<label style="font-weight:600;">Eşik Miktarı:</label>' +
                        '<input id="swalBulkEsik" type="number" class="swal2-input" value="5" min="1" style="width:100%;" />' +
                        '</div>',
                    confirmButtonText: 'Tümüne Uygula',
                    confirmButtonColor: '#D4AF37',
                    showCancelButton: true,
                    cancelButtonText: 'Vazgeç',
                    preConfirm: function () {
                        var val = parseInt(document.getElementById('swalBulkEsik').value);
                        if (isNaN(val) || val < 1) {
                            Swal.showValidationMessage('Eşik miktarı en az 1 olmalı');
                            return false;
                        }
                        return val;
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    var esikVal = result.value;
                    var total = availableProducts.length;
                    var done = 0, failed = 0;

                    Swal.fire({ title: 'Toplu kayıt yapılıyor...', html: '0/' + total, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

                    var queue = availableProducts.slice();
                    function next() {
                        if (queue.length === 0) {
                            Swal.fire({
                                icon: failed > 0 ? 'warning' : 'success',
                                title: 'Tamamlandı',
                                html: '<b>' + done + '/' + total + '</b> ürün eşiği tanımlandı.' + (failed > 0 ? '<br><span style="color:red;">' + failed + ' hata</span>' : ''),
                                confirmButtonColor: '#D4AF37'
                            });
                            loadAll();
                            return;
                        }
                        var p = queue.shift();
                        $.ajax({
                            url: apiUrl + '?action=saveThreshold',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({ araUrunAdiNo: p.araUrunAdiNo, esikMiktar: esikVal, otomatikIsEmri: false, isEmriAdet: 10, aktif: true }),
                            dataType: 'json',
                            success: function (r) { if (r.success) done++; else failed++; },
                            error: function () { failed++; },
                            complete: function () {
                                Swal.getHtmlContainer().querySelector('.swal2-html-container').textContent = (done + failed) + '/' + total;
                                next();
                            }
                        });
                    }
                    next();
                });
            }

            function resetThresholds() {
                Swal.fire({
                    title: 'Emin misiniz?',
                    text: "Tüm kritik stok eşik tanımları ve uyarı logları kalıcı olarak silinecektir!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Evet, Her Şeyi Sil!',
                    cancelButtonText: 'Vazgeç'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Son Onay',
                            text: 'Bu işlem geri alınamaz. Devam etmek istiyor musunuz?',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Evet, Eminim!',
                            cancelButtonText: 'Hayır',
                            confirmButtonColor: '#d33'
                        }).then(function (secondResult) {
                            if (secondResult.isConfirmed) {
                                Swal.fire({ title: 'Sıfırlanıyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                                $.ajax({
                                    url: apiUrl + '?action=resetThresholds',
                                    type: 'POST',
                                    dataType: 'json',
                                    success: function (res) {
                                        if (res.success) {
                                            Swal.fire({ icon: 'success', title: 'Sıfırlandı!', text: res.message, confirmButtonColor: '#D4AF37' });
                                            loadAll();
                                        } else {
                                            Swal.fire('Hata', res.message, 'error');
                                        }
                                    },
                                    error: function () {
                                        Swal.fire('Hata', 'Sunucu ile iletişim kurulamadı.', 'error');
                                    }
                                });
                            }
                        });
                    }
                });
            }

            function escapeHtml(text) {
                if (!text) return '';
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // ========================= INIT =========================
            $(function () { loadAll(); });
        </script>

@endsection