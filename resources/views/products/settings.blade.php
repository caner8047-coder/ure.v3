@extends('layouts.app')

@section('content')

        <div class="page-wrapper">
            <div class="card-modern">
                <h2 class="page-title"><i class="fa fa-cogs me-2"></i>Ürün Özellikleri Ayarları</h2>

                <!-- Üst Form -->
                <div class="row g-3 align-items-end mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fa fa-cube me-1"></i>Ürün Seç</label>
                        <select id="ddUrunID" class="form-select form-select-lg modern-select" onchange="loadProductDetails()">
                            <option value="">Yükleniyor...</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fa fa-sliders me-1"></i>Sistem Adı</label>
                        <input type="text" id="tbSistemAdi" class="form-control form-control-lg" placeholder="Sistem adını giriniz...">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="fa fa-sliders me-1"></i>Sistem Kodu</label>
                        <input type="text" id="tbSistemKodu" class="form-control form-control-lg" placeholder="Sistem kodunu giriniz...">
                    </div>

                    <div class="col-md-3 text-md-end text-center">
                        <div class="d-flex flex-column gap-2">
                            <button id="updateButton" type="button" class="btn btn-lg btn-gradient w-100">
                                <i class="fa fa-save me-2"></i>Düzenlemeleri Kaydet
                            </button>
                            <div class="d-flex gap-2">
                                <button id="btnExport" type="button" class="btn btn-md btn-export w-50"
                                    onclick="exportToExcel()">
                                    <i class="fa fa-file-excel me-1"></i>Dışarı Aktar
                                </button>
                                <button id="btnImport" type="button" class="btn btn-md btn-import w-50"
                                    onclick="document.getElementById('fileImport').click()">
                                    <i class="fa fa-upload me-1"></i>İçeri Aktar
                                </button>
                                <input type="file" id="fileImport" accept=".xlsx,.xls,.csv" style="display:none;"
                                    onchange="importFromExcel(this)" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tablo Alanı -->
                <div class="handsontable-wrapper mt-4">
                    <div id="handsontable"></div>
                </div>
            </div>
        </div>

        <!-- ====== STYLES ====== -->
        <style>
            body {
                background-color: #f8f6f2;
                font-family: 'Inter', sans-serif;
            }

            .page-wrapper {
                max-width: 1200px;
                margin: 40px auto;
                padding: 0 20px;
            }

            .card-modern {
                background: #fff;
                border-radius: 20px;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.08);
                padding: 30px;
                transition: all .3s ease;
            }

            .card-modern:hover {
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            }

            .page-title {
                color: #4b3621;
                font-weight: 600;
                margin-bottom: 25px;
                border-bottom: 2px solid #d4af37;
                padding-bottom: 10px;
            }

            .btn-gradient {
                background: linear-gradient(90deg, #d4af37, #80694d);
                color: #fff;
                border: none;
                border-radius: 10px;
                transition: all .3s;
            }

            .btn-gradient:hover {
                background: linear-gradient(90deg, #c19e2d, #6e5d40);
                transform: translateY(-2px);
            }

            .btn-gradient:disabled {
                opacity: .7;
                cursor: not-allowed;
                transform: none;
            }

            .btn-export {
                background: linear-gradient(90deg, #217346, #2d9254);
                color: #fff;
                border: none;
                border-radius: 8px;
                transition: all .3s;
                font-weight: 600;
            }

            .btn-export:hover {
                background: linear-gradient(90deg, #1a5c38, #217346);
                transform: translateY(-1px);
                color: #fff;
            }

            .btn-import {
                background: linear-gradient(90deg, #2563eb, #3b82f6);
                color: #fff;
                border: none;
                border-radius: 8px;
                transition: all .3s;
                font-weight: 600;
            }

            .btn-import:hover {
                background: linear-gradient(90deg, #1d4ed8, #2563eb);
                transform: translateY(-1px);
                color: #fff;
            }

            .modern-select {
                border-radius: 10px;
                border: 1px solid #ccc;
            }

            #handsontable {
                width: 100%;
                height: 500px;
                border-radius: 10px;
                overflow: hidden;
            }

            .handsontable-wrapper {
                border: 1px solid #e0e0e0;
                border-radius: 10px;
                background: #fffefb;
                padding: 10px;
            }

            @media (max-width: 768px) {
                .row.g-3 .col-md-3 {
                    margin-bottom: 1rem;
                }
            }
        </style>

        <!-- ====== SCRIPTS ====== -->
        <script src="/Scripts/handsontable/handsontable.full.min.js"></script>
        <link rel="stylesheet" href="/Content/handsontable/handsontable.full.min.css" />
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

        <script>
            var hot;

            document.addEventListener('DOMContentLoaded', function () {
                fetchInitialData();
                document.getElementById('updateButton').addEventListener('click', handleUpdateButtonClick);
            });

            async function fetchInitialData() {
                try {
                    const response = await fetch('/api/database/product-settings/lookups');
                    const result = await response.json();
                    if (result.success && result.urunler) {
                        const select = document.getElementById('ddUrunID');
                        select.innerHTML = '';
                        result.urunler.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.No;
                            opt.text = item.UrunID;
                            select.appendChild(opt);
                        });
                        
                        if (result.urunler.length > 0) {
                            loadProductDetails();
                        }
                    }
                } catch (error) {
                    console.error('Error fetching initial lookups:', error);
                    document.getElementById('ddUrunID').innerHTML = '<option value="">Yüklenemedi</option>';
                }
            }

            async function loadProductDetails() {
                const urunNo = document.getElementById('ddUrunID').value;
                if (!urunNo) return;
                
                try {
                    const response = await fetch(`/api/database/product-settings/${urunNo}`);
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById('tbSistemAdi').value = result.sistemAdi || '';
                        document.getElementById('tbSistemKodu').value = result.sistemKodu || '';
                        
                        if (hot) {
                            hot.destroy();
                        }
                        initHandsontable(result.tablo || []);
                    }
                } catch (error) {
                    console.error('Error loading product details:', error);
                }
            }

            function initHandsontable(data) {
                var container = document.getElementById('handsontable');
                hot = new Handsontable(container, {
                    data: data,
                    columns: [
                        { data: 'No', readOnly: true },
                        { data: 'UrunID', readOnly: true },
                        { data: 'SistemAdi', readOnly: true },
                        { data: 'SistemKodu', readOnly: true },
                        { data: 'AraUrunNo', readOnly: true },
                        { data: 'AraUrun', readOnly: true },
                        { data: 'Adet', readOnly: true },
                        { data: 'Performans' }
                    ],
                    colHeaders: ['Ürün No', 'Ürün Adı', 'Sistem Adı', 'Sistem Kodu', 'Ara Ürün No', 'Ara Ürün Adı', 'Adet', 'Performans'],
                    rowHeaders: true,
                    stretchH: 'all',
                    height: 400,
                    licenseKey: 'non-commercial-and-evaluation'
                });
            }

            // ============================================================
            //   EXCEL DIŞARI AKTAR (EXPORT)
            // ============================================================
            function exportToExcel() {
                if (!hot) {
                    Swal.fire('❗ Uyarı', 'Tablo henüz yüklenmedi.', 'warning');
                    return;
                }

                var headers = ['Ürün No', 'Ürün Adı', 'Sistem Adı', 'Sistem Kodu', 'Ara Ürün No', 'Ara Ürün Adı', 'Adet', 'Performans'];
                var rawData = hot.getData();
                var exportData = [headers];

                for (var i = 0; i < rawData.length; i++) {
                    exportData.push(rawData[i]);
                }

                var ws = XLSX.utils.aoa_to_sheet(exportData);

                // Sütun genişlikleri
                ws['!cols'] = [
                    { wch: 10 }, // Ürün No
                    { wch: 35 }, // Ürün Adı
                    { wch: 30 }, // Sistem Adı
                    { wch: 18 }, // Sistem Kodu
                    { wch: 12 }, // Ara Ürün No
                    { wch: 45 }, // Ara Ürün Adı
                    { wch: 8 },  // Adet
                    { wch: 12 }  // Performans
                ];

                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Ürün Özellikleri');

                // Dosya adını ürün adından oluştur
                var urunSelect = document.getElementById('ddUrunID');
                var urunAdi = urunSelect.options[urunSelect.selectedIndex].text || 'Urun';
                var safeFileName = urunAdi.replace(/[^a-zA-Z0-9ğüşıöçĞÜŞİÖÇ\s]/g, '').replace(/\s+/g, '_').substring(0, 50);
                var tarih = new Date().toISOString().slice(0, 10);

                XLSX.writeFile(wb, safeFileName + '_' + tarih + '.xlsx');

                Swal.fire({
                    icon: 'success',
                    title: 'Dışarı Aktarıldı',
                    html: '<b>' + rawData.length + '</b> satır Excel dosyasına aktarıldı.',
                    confirmButtonColor: '#217346',
                    timer: 2500,
                    showConfirmButton: false
                });
            }

            // ============================================================
            //   EXCEL İÇERİ AKTAR (IMPORT)
            // ============================================================
            function importFromExcel(input) {
                if (!input.files || !input.files[0]) return;
                if (!hot) {
                    Swal.fire('❗ Uyarı', 'Tablo henüz yüklenmedi. Önce bir ürün seçin.', 'warning');
                    input.value = '';
                    return;
                }

                var file = input.files[0];
                var reader = new FileReader();

                reader.onload = function (e) {
                    try {
                        var data = new Uint8Array(e.target.result);
                        var workbook = XLSX.read(data, { type: 'array' });
                        var firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                        var rows = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

                        if (rows.length < 2) {
                            Swal.fire('❗ Uyarı', 'Excel dosyası boş veya sadece başlık içeriyor.', 'warning');
                            input.value = '';
                            return;
                        }

                        // İlk satır başlık, kalanlar veri
                        var headerRow = rows[0];
                        var dataRows = rows.slice(1);

                        // Performans ve diğer sütunları bul (başlıktan)
                        var perfIndex = -1;
                        var araNoIndex = -1;
                        var sistemAdiIndex = -1;
                        var sistemKoduIndex = -1;
                        for (var i = 0; i < headerRow.length; i++) {
                            var h = (headerRow[i] || '').toString().toLowerCase().trim();
                            if (h === 'performans') perfIndex = i;
                            if (h === 'ara ürün no' || h === 'araurunno') araNoIndex = i;
                            if (h === 'sistem adı' || h === 'sistemadi' || h === 'sistem adi') sistemAdiIndex = i;
                            if (h === 'sistem kodu' || h === 'sistemkodu') sistemKoduIndex = i;
                        }

                        if (perfIndex === -1) perfIndex = 7;
                        if (araNoIndex === -1) araNoIndex = 4;
                        if (sistemAdiIndex === -1) sistemAdiIndex = 2;
                        if (sistemKoduIndex === -1) sistemKoduIndex = 3;

                        // Sistem Adı ve Sistem Kodu'nu ilk veri satırından oku ve TextBox'lara yaz
                        var sistemAdiUpdated = false;
                        var sistemKoduUpdated = false;
                        if (dataRows.length > 0 && dataRows[0]) {
                            var firstRow = dataRows[0];
                            if (firstRow[sistemAdiIndex] !== undefined && firstRow[sistemAdiIndex] !== null) {
                                var newSistemAdi = firstRow[sistemAdiIndex].toString().trim();
                                document.getElementById('tbSistemAdi').value = newSistemAdi;
                                sistemAdiUpdated = true;
                            }
                            if (firstRow[sistemKoduIndex] !== undefined && firstRow[sistemKoduIndex] !== null) {
                                var newSistemKodu = firstRow[sistemKoduIndex].toString().trim();
                                document.getElementById('tbSistemKodu').value = newSistemKodu;
                                sistemKoduUpdated = true;
                            }
                        }

                        // Mevcut tablodaki verileri güncelle
                        var currentData = hot.getSourceData();
                        var updatedCount = 0;
                        var notFoundRows = [];

                        // Excel'deki AraUrunNo → Performans haritası oluştur
                        var perfMap = {};
                        for (var i = 0; i < dataRows.length; i++) {
                            var row = dataRows[i];
                            if (row && row.length > perfIndex) {
                                var araNo = (row[araNoIndex] || '').toString().trim();
                                var perf = row[perfIndex];
                                if (araNo !== '' && perf !== undefined && perf !== null && perf !== '') {
                                    perfMap[araNo] = parseInt(perf) || 0;
                                }
                            }
                        }

                        // Handsontable'daki verileri güncelle
                        for (var i = 0; i < currentData.length; i++) {
                            var araUrunNo = (currentData[i].AraUrunNo || '').toString().trim();
                            if (perfMap.hasOwnProperty(araUrunNo)) {
                                hot.setDataAtRowProp(i, 'Performans', perfMap[araUrunNo]);
                                updatedCount++;
                            }
                        }

                        // Eşleşmeyen Excel satırlarını bul
                        var currentAraNos = {};
                        for (var i = 0; i < currentData.length; i++) {
                            currentAraNos[(currentData[i].AraUrunNo || '').toString().trim()] = true;
                        }
                        for (var key in perfMap) {
                            if (!currentAraNos.hasOwnProperty(key)) {
                                notFoundRows.push(key);
                            }
                        }

                        var resultHtml = '<b>' + updatedCount + '</b> satırın Performans değeri güncellendi.';
                        if (sistemAdiUpdated) {
                            resultHtml += '<br>📋 <b>Sistem Adı</b> güncellendi.';
                        }
                        if (sistemKoduUpdated) {
                            resultHtml += '<br>🏷️ <b>Sistem Kodu</b> güncellendi.';
                        }
                        if (notFoundRows.length > 0) {
                            resultHtml += '<br><br><small class="text-muted">⚠️ ' + notFoundRows.length + ' satır tabloda bulunamadı (Ara Ürün No: ' + notFoundRows.join(', ') + ')</small>';
                        }
                        resultHtml += '<br><br><small>💡 Değişiklikleri kalıcı yapmak için <b>"Düzenlemeleri Kaydet"</b> butonuna basın.</small>';

                        Swal.fire({
                            icon: 'success',
                            title: 'İçeri Aktarıldı',
                            html: resultHtml,
                            confirmButtonColor: '#2563eb'
                        });

                    } catch (err) {
                        Swal.fire('❌ Hata', 'Dosya okunurken hata oluştu: ' + err.message, 'error');
                        console.error('Import hatası:', err);
                    }

                    input.value = '';
                };

                reader.readAsArrayBuffer(file);
            }

            // ============================================================
            //   DÜZENLE & KAYDET
            // ============================================================
            async function handleUpdateButtonClick() {
                if (!hot) {
                    Swal.fire('❗ Uyarı', 'Tablo henüz yüklenmedi.', 'warning');
                    return;
                }

                // DOĞRU sütun eşlemesi (4: AraUrunNo, 7: Performans)
                const updatedData = hot.getData().map(row => ({
                    AraUrunNo: row[4],
                    Performans: row[7]
                }));

                // Form verileri (static kontrol yok; direkt istemciden gönderiyoruz)
                const urunNo = document.getElementById('ddUrunID').value;
                const sistemAdi = document.getElementById('tbSistemAdi').value;
                const sistemKodu = document.getElementById('tbSistemKodu').value;

                const btn = document.getElementById('updateButton');
                btn.disabled = true;
                const oldHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Kaydediliyor...';

                Swal.fire({
                    title: 'Kaydediliyor...',
                    text: 'Lütfen bekleyin',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const response = await fetch('/api/database/product-settings/update', {
                        method: 'POST',
                        headers: { 
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ 
                            updates: updatedData, 
                            urunNo: parseInt(urunNo), 
                            sistemAdi: sistemAdi, 
                            sistemKodu: sistemKodu 
                        })
                    });

                    const result = await response.json();
                    Swal.close();

                    if (result && result.success) {
                        Swal.fire('✅ Başarılı', 'Performans ve sistem bilgileri güncellendi!', 'success')
                            .then(() => location.reload());
                    } else {
                        const msg = result.message || 'Beklenmedik bir hata oluştu.';
                        Swal.fire('⚠️ Uyarı', msg, 'warning');
                    }
                } catch (error) {
                    Swal.close();
                    Swal.fire('❌ Hata', 'Sunucuyla bağlantı kurulamadı.', 'error');
                    console.error('Hata:', error);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = oldHtml;
                }
            }
        </script>

@endsection