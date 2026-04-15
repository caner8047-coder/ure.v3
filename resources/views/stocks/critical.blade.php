@extends('layouts.app')

@section('title', 'Kritik Stok Esigi')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="bulkSetThreshold()">
        <i class="bi bi-layers me-1"></i>Toplu Esik
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetThresholds()">
        <i class="bi bi-trash me-1"></i>Tumunu Sifirla
    </button>
@endsection

@push('styles')
<style>
    .esik-toggle {
        position: relative;
        width: 38px;
        height: 20px;
        cursor: pointer;
        display: inline-block;
    }
    .esik-toggle input { display: none; }
    .esik-toggle-slider {
        position: absolute; inset: 0;
        background: var(--z-border);
        border-radius: 20px;
        transition: 0.25s;
    }
    .esik-toggle-slider::before {
        content: '';
        position: absolute;
        left: 3px; top: 3px;
        width: 14px; height: 14px;
        border-radius: 50%;
        background: #fff;
        transition: 0.25s;
    }
    .esik-toggle input:checked + .esik-toggle-slider {
        background: var(--z-accent);
    }
    .esik-toggle input:checked + .esik-toggle-slider::before {
        transform: translateX(18px);
    }
    .esik-progress {
        height: 6px;
        background: var(--z-border-light);
        border-radius: 3px;
        overflow: hidden;
    }
    .esik-progress-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.5s;
    }
    .esik-add-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }
    @media (max-width: 900px) {
        .esik-add-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 600px) {
        .esik-add-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    {{-- Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Tanimli Esik</p>
            <h3 class="metric-value" id="statToplam">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Kritik</p>
            <h3 class="metric-value" id="statKritik" style="color:var(--z-danger);">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Uyari</p>
            <h3 class="metric-value" id="statUyari" style="color:var(--z-warning);">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Normal</p>
            <h3 class="metric-value" id="statNormal" style="color:var(--z-success);">0</h3>
        </article>
    </div>

    {{-- Add Form --}}
    <section class="panel-surface">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Yeni esik tanimla</h3>
        </div>
        <div class="esik-add-grid">
            <div>
                <label class="form-label">Urun secin</label>
                <input type="text" id="txtSearchUrun" class="form-control mb-1" placeholder="Urun adi veya kod yazin..." onkeyup="filterDropdown()" />
                <select id="ddUrun" class="form-select">
                    <option value="0">Yukleniyor...</option>
                </select>
            </div>
            <div>
                <label class="form-label">Esik miktari</label>
                <input type="number" id="txtEsik" class="form-control" value="5" min="1" />
            </div>
            <div>
                <label class="form-label">Oto. is emri adedi</label>
                <input type="number" id="txtIsEmriAdet" class="form-control" value="10" min="1" />
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <div class="d-flex align-items-center gap-2" style="height:38px;">
                    <label class="esik-toggle">
                        <input type="checkbox" id="chkOto" />
                        <span class="esik-toggle-slider"></span>
                    </label>
                    <span class="small text-muted">Oto. Is Emri</span>
                </div>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button type="button" class="btn btn-primary" onclick="saveThreshold()">
                    <i class="bi bi-plus-lg me-1"></i>Ekle
                </button>
            </div>
        </div>
    </section>

    {{-- Thresholds Table --}}
    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Tanimli esikler</h3>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="txtSearchTable" class="form-control form-control-sm" placeholder="Tabloda ara..." onkeyup="filterTable()" style="width:200px;" />
            </div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th>Urun</th>
                        <th>Kategori</th>
                        <th style="width:70px;">Esik</th>
                        <th style="width:70px;">Mevcut</th>
                        <th style="width:110px;">Doluluk</th>
                        <th style="width:70px;">Durum</th>
                        <th style="width:80px;">Oto. IE</th>
                        <th style="width:90px;">Son Uyari</th>
                        <th style="width:90px;">Aksiyon</th>
                    </tr>
                </thead>
                <tbody id="tbodyEsikler"></tbody>
            </table>
            <div id="emptyEsik" class="text-center py-5 text-muted" style="display:none;">
                <i class="bi bi-box2 d-block mb-2" style="font-size:2rem;opacity:0.4;"></i>
                <div>Henuz esik tanimi yok. Yukaridan urun secerek baslayin.</div>
            </div>
        </div>
    </section>

    {{-- Alert Log --}}
    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Son uyari loglari</h3>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Urun</th>
                        <th>Esik</th>
                        <th>Mevcut</th>
                        <th>Tip</th>
                        <th>Oto. Is Emri</th>
                    </tr>
                </thead>
                <tbody id="tbodyLogs"></tbody>
            </table>
            <div id="emptyLogs" class="text-center py-5 text-muted" style="display:none;">
                <i class="bi bi-check-circle d-block mb-2" style="font-size:2rem;opacity:0.4;"></i>
                <div>Henuz uyari kaydi yok.</div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
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
                Swal.fire('Hata', 'Sunucu ile baglanti kurulamadi.', 'error');
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
            var barColor = t.durum === 'Kritik' ? 'var(--z-danger)' : (t.durum === 'Uyari' ? 'var(--z-warning)' : 'var(--z-success)');
            var badgeBg = t.durum === 'Kritik' ? 'var(--z-danger-soft)' : (t.durum === 'Uyari' ? 'var(--z-warning-soft)' : 'var(--z-success-soft)');
            var badgeColor = t.durum === 'Kritik' ? 'var(--z-danger)' : (t.durum === 'Uyari' ? 'var(--z-warning)' : 'var(--z-success)');
            var badgeIcon = t.durum === 'Kritik' ? '🔴' : (t.durum === 'Uyari' ? '🟡' : '🟢');

            tr.innerHTML =
                '<td><strong>' + escapeHtml(t.araUrunAdi) + '</strong></td>' +
                '<td class="text-muted">' + escapeHtml(t.urunCesidi || '—') + '</td>' +
                '<td class="text-center fw-bold">' + t.esikMiktar + '</td>' +
                '<td class="text-center fw-bold" style="color:' + barColor + ';">' + t.mevcutStok + '</td>' +
                '<td>' +
                    '<div class="esik-progress"><div class="esik-progress-fill" style="width:' + pct + '%; background:' + barColor + ';"></div></div>' +
                    '<div class="small text-muted mt-1">%' + pct + '</div>' +
                '</td>' +
                '<td><span class="badge" style="background:' + badgeBg + ';color:' + badgeColor + ';">' + badgeIcon + ' ' + (t.durum === 'Uyari' ? 'Uyari' : t.durum) + '</span></td>' +
                '<td class="text-center">' + (t.otomatikIsEmri ? '<span style="color:var(--z-accent);font-weight:600;">✓ ' + t.isEmriAdet + ' ad.</span>' : '<span class="text-muted">—</span>') + '</td>' +
                '<td class="small text-muted">' + (t.sonUyariTarihi || '—') + '</td>' +
                '<td>' +
                '<button class="btn btn-outline-secondary btn-sm me-1" data-no="' + t.araUrunAdiNo + '" data-esik="' + t.esikMiktar + '" data-oto="' + (t.otomatikIsEmri ? '1' : '0') + '" data-adet="' + t.isEmriAdet + '" onclick="editThresholdFromBtn(this)" title="Duzenle"><i class="bi bi-pencil"></i></button>' +
                '<button class="btn btn-outline-danger btn-sm" data-no="' + t.no + '" data-adi="' + escapeHtml(t.araUrunAdi) + '" onclick="deleteThresholdFromBtn(this)" title="Sil"><i class="bi bi-trash"></i></button>' +
                '</td>';

            tbody.appendChild(tr);
        });
    }

    function renderDropdown() {
        var dd = document.getElementById('ddUrun');
        var search = document.getElementById('txtSearchUrun').value.toLocaleLowerCase('tr-TR');

        dd.innerHTML = '<option value="0">-- Urun Secin (' + availableProducts.length + ' adet) --</option>';

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
            opt.textContent = "Sonuc bulunamadi.";
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
            if (!l.okundu) tr.style.fontWeight = '500';
            var tipColor = l.uyariTipi === 'Kritik' ? 'var(--z-danger)' : 'var(--z-warning)';
            tr.innerHTML =
                '<td>' + l.tarih + '</td>' +
                '<td>' + escapeHtml(l.araUrunAdi) + '</td>' +
                '<td class="text-center">' + l.esikMiktar + '</td>' +
                '<td class="text-center fw-bold">' + l.mevcutStok + '</td>' +
                '<td><span style="color:' + tipColor + ';font-weight:700;">' + (l.uyariTipi === 'Kritik' ? '🔴 Kritik' : '🟡 Uyari') + '</span></td>' +
                '<td class="text-center">' + (l.otomatikIsEmriVerildi ? '✅ Verildi' : '—') + '</td>';
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
            Swal.fire('Uyari', 'Lutfen bir urun secin.', 'warning');
            return;
        }
        if (isNaN(esikMiktar) || esikMiktar < 1) {
            Swal.fire('Uyari', 'Esik miktari en az 1 olmalidir.', 'warning');
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
                    Swal.fire({ icon: 'success', title: 'Kaydedildi!', text: res.message, timer: 1500 });
                    loadAll();
                } else {
                    Swal.fire({ icon: 'error', title: 'Hata', text: res.message });
                }
            },
            error: function () { Swal.fire('Hata', 'Sunucu hatasi.', 'error'); }
        });
    }

    function editThresholdFromBtn(btn) {
        var araUrunAdiNo = parseInt(btn.getAttribute('data-no'));
        var currentEsik = parseInt(btn.getAttribute('data-esik'));
        var currentOto = btn.getAttribute('data-oto') === '1';
        var currentAdet = parseInt(btn.getAttribute('data-adet'));
        editThreshold(araUrunAdiNo, currentEsik, currentOto, currentAdet);
    }

    function editThreshold(araUrunAdiNo, currentEsik, currentOto, currentAdet) {
        Swal.fire({
            title: 'Esik Duzenle',
            html:
                '<div style="text-align:left; font-size:0.9rem;">' +
                '<label style="font-weight:600;">Esik Miktari:</label>' +
                '<input id="swalEsik" type="number" class="swal2-input" value="' + currentEsik + '" min="1" style="width:100%;" />' +
                '<label style="font-weight:600; margin-top:8px; display:block;">Oto. Is Emri Adedi:</label>' +
                '<input id="swalAdet" type="number" class="swal2-input" value="' + currentAdet + '" min="1" style="width:100%;" />' +
                '<label style="margin-top:8px; display:flex; align-items:center; gap:8px;">' +
                '<input id="swalOto" type="checkbox" ' + (currentOto ? 'checked' : '') + ' /> Otomatik Is Emri' +
                '</label></div>',
            confirmButtonText: 'Guncelle',
            showCancelButton: true,
            cancelButtonText: 'Vazgec',
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

    function deleteThresholdFromBtn(btn) {
        var no = parseInt(btn.getAttribute('data-no'));
        var urunAdi = btn.getAttribute('data-adi');
        deleteThreshold(no, urunAdi);
    }

    function deleteThreshold(no, urunAdi) {
        Swal.fire({
            title: 'Esik Tanimini Sil',
            html: '<b>' + escapeHtml(urunAdi) + '</b> icin kritik stok esigi silinsin mi?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'Vazgec',
            confirmButtonColor: '#dc2626',
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
                        Swal.fire({ icon: 'success', title: 'Silindi!', text: res.message, timer: 1200 });
                        loadAll();
                    } else {
                        Swal.fire('Hata', res.message, 'error');
                    }
                },
                error: function () { Swal.fire('Hata', 'Sunucu hatasi.', 'error'); }
            });
        });
    }

    // ========================= TOPLU ESIK AYARLAMA =========================
    function bulkSetThreshold() {
        if (availableProducts.length === 0) {
            Swal.fire('Bilgi', 'Esik tanimlanmamis urun kalmadi.', 'info');
            return;
        }
        Swal.fire({
            title: 'Toplu Esik Tanimla',
            html:
                '<div style="text-align:left; font-size:0.9rem;">' +
                '<p style="color:var(--z-text-secondary); margin-bottom:10px;">Henuz esik tanimi olmayan <b>' + availableProducts.length + '</b> urune ayni esik degeri atanacak.</p>' +
                '<label style="font-weight:600;">Esik Miktari:</label>' +
                '<input id="swalBulkEsik" type="number" class="swal2-input" value="5" min="1" style="width:100%;" />' +
                '</div>',
            confirmButtonText: 'Tumune Uygula',
            showCancelButton: true,
            cancelButtonText: 'Vazgec',
            preConfirm: function () {
                var val = parseInt(document.getElementById('swalBulkEsik').value);
                if (isNaN(val) || val < 1) {
                    Swal.showValidationMessage('Esik miktari en az 1 olmali');
                    return false;
                }
                return val;
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var esikVal = result.value;
            var total = availableProducts.length;
            var done = 0, failed = 0;

            Swal.fire({ title: 'Toplu kayit yapiliyor...', html: '0/' + total, allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });

            var queue = availableProducts.slice();
            function next() {
                if (queue.length === 0) {
                    Swal.fire({
                        icon: failed > 0 ? 'warning' : 'success',
                        title: 'Tamamlandi',
                        html: '<b>' + done + '/' + total + '</b> urun esigi tanimlandi.' + (failed > 0 ? '<br><span style="color:var(--z-danger);">' + failed + ' hata</span>' : ''),
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
            text: "Tum kritik stok esik tanimlari ve uyari loglari kalici olarak silinecektir!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Evet, Her Seyi Sil!',
            cancelButtonText: 'Vazgec'
        }).then(function (result) {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Son Onay',
                    text: 'Bu islem geri alinamaz. Devam etmek istiyor musunuz?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Evet, Eminim!',
                    cancelButtonText: 'Hayir',
                    confirmButtonColor: '#dc2626'
                }).then(function (secondResult) {
                    if (secondResult.isConfirmed) {
                        Swal.fire({ title: 'Sifirlaniyor...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
                        $.ajax({
                            url: apiUrl + '?action=resetThresholds',
                            type: 'POST',
                            dataType: 'json',
                            success: function (res) {
                                if (res.success) {
                                    Swal.fire({ icon: 'success', title: 'Sifirlandi!', text: res.message });
                                    loadAll();
                                } else {
                                    Swal.fire('Hata', res.message, 'error');
                                }
                            },
                            error: function () {
                                Swal.fire('Hata', 'Sunucu ile iletisim kurulamadi.', 'error');
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
@endpush