@extends('layouts.app')

@section('title', 'Urun Ozellikleri Ayarlari')

@section('page-actions')
    <button id="updateButton" type="button" class="btn btn-primary">
        <i class="bi bi-save me-1"></i>Kaydet
    </button>
    <button id="btnExport" type="button" class="btn btn-outline-secondary" onclick="exportToExcel()">
        <i class="bi bi-file-earmark-excel me-1"></i>Excel Aktar
    </button>
    <button id="btnImport" type="button" class="btn btn-outline-secondary" onclick="document.getElementById('fileImport').click()">
        <i class="bi bi-upload me-1"></i>Iceri Aktar
    </button>
    <input type="file" id="fileImport" accept=".xlsx,.xls,.csv" style="display:none;" onchange="importFromExcel(this)" />
@endsection

@push('styles')
<style>
    .settings-form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        align-items: end;
    }
    .handsontable-wrapper {
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        background: var(--z-bg-card);
        padding: 8px;
        margin-top: 16px;
    }
    #handsontable {
        width: 100%;
        height: 460px;
        border-radius: var(--z-radius-sm);
        overflow: hidden;
    }
    @media (max-width: 768px) {
        .settings-form-grid { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <h3 class="section-title">Urun ve performans ayarlari</h3>
                <p class="section-copy">Urun secin, sistem adi/kodu duzenleyin ve performans degerlerini guncelleyin.</p>
            </div>
        </div>

        <div class="settings-form-grid">
            <div>
                <label class="form-label"><i class="bi bi-box me-1"></i>Urun Sec</label>
                <select id="ddUrunID" class="form-select" onchange="loadProductDetails()">
                    <option value="">Yukleniyor...</option>
                </select>
            </div>
            <div>
                <label class="form-label">Sistem Adi</label>
                <input type="text" id="tbSistemAdi" class="form-control" placeholder="Sistem adini giriniz...">
            </div>
            <div>
                <label class="form-label">Sistem Kodu</label>
                <input type="text" id="tbSistemKodu" class="form-control" placeholder="Sistem kodunu giriniz...">
            </div>
        </div>

        <div class="handsontable-wrapper">
            <div id="handsontable"></div>
        </div>
    </div>
@endsection

@push('scripts')
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
            document.getElementById('ddUrunID').innerHTML = '<option value="">Yuklenemedi</option>';
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
            colHeaders: ['Urun No', 'Urun Adi', 'Sistem Adi', 'Sistem Kodu', 'Ara Urun No', 'Ara Urun Adi', 'Adet', 'Performans'],
            rowHeaders: true,
            stretchH: 'all',
            height: 400,
            licenseKey: 'non-commercial-and-evaluation'
        });
    }

    function exportToExcel() {
        if (!hot) {
            Swal.fire('Uyari', 'Tablo henuz yuklenmedi.', 'warning');
            return;
        }

        var headers = ['Urun No', 'Urun Adi', 'Sistem Adi', 'Sistem Kodu', 'Ara Urun No', 'Ara Urun Adi', 'Adet', 'Performans'];
        var rawData = hot.getData();
        var exportData = [headers];

        for (var i = 0; i < rawData.length; i++) {
            exportData.push(rawData[i]);
        }

        var ws = XLSX.utils.aoa_to_sheet(exportData);
        ws['!cols'] = [{ wch: 10 }, { wch: 35 }, { wch: 30 }, { wch: 18 }, { wch: 12 }, { wch: 45 }, { wch: 8 }, { wch: 12 }];

        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Urun Ozellikleri');

        var urunSelect = document.getElementById('ddUrunID');
        var urunAdi = urunSelect.options[urunSelect.selectedIndex].text || 'Urun';
        var safeFileName = urunAdi.replace(/[^a-zA-Z0-9ğüşıöçĞÜŞİÖÇ\s]/g, '').replace(/\s+/g, '_').substring(0, 50);
        var tarih = new Date().toISOString().slice(0, 10);

        XLSX.writeFile(wb, safeFileName + '_' + tarih + '.xlsx');
        Swal.fire({ icon: 'success', title: 'Aktarildi', html: '<b>' + rawData.length + '</b> satir Excel dosyasina aktarildi.', timer: 2500, showConfirmButton: false });
    }

    function importFromExcel(input) {
        if (!input.files || !input.files[0]) return;
        if (!hot) {
            Swal.fire('Uyari', 'Tablo henuz yuklenmedi. Once bir urun secin.', 'warning');
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
                    Swal.fire('Uyari', 'Excel dosyasi bos veya sadece baslik iceriyor.', 'warning');
                    input.value = '';
                    return;
                }

                var headerRow = rows[0];
                var dataRows = rows.slice(1);

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

                var sistemAdiUpdated = false;
                var sistemKoduUpdated = false;
                if (dataRows.length > 0 && dataRows[0]) {
                    var firstRow = dataRows[0];
                    if (firstRow[sistemAdiIndex] !== undefined && firstRow[sistemAdiIndex] !== null) {
                        document.getElementById('tbSistemAdi').value = firstRow[sistemAdiIndex].toString().trim();
                        sistemAdiUpdated = true;
                    }
                    if (firstRow[sistemKoduIndex] !== undefined && firstRow[sistemKoduIndex] !== null) {
                        document.getElementById('tbSistemKodu').value = firstRow[sistemKoduIndex].toString().trim();
                        sistemKoduUpdated = true;
                    }
                }

                var currentData = hot.getSourceData();
                var updatedCount = 0;
                var notFoundRows = [];

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

                for (var i = 0; i < currentData.length; i++) {
                    var araUrunNo = (currentData[i].AraUrunNo || '').toString().trim();
                    if (perfMap.hasOwnProperty(araUrunNo)) {
                        hot.setDataAtRowProp(i, 'Performans', perfMap[araUrunNo]);
                        updatedCount++;
                    }
                }

                var currentAraNos = {};
                for (var i = 0; i < currentData.length; i++) {
                    currentAraNos[(currentData[i].AraUrunNo || '').toString().trim()] = true;
                }
                for (var key in perfMap) {
                    if (!currentAraNos.hasOwnProperty(key)) {
                        notFoundRows.push(key);
                    }
                }

                var resultHtml = '<b>' + updatedCount + '</b> satirin Performans degeri guncellendi.';
                if (sistemAdiUpdated) resultHtml += '<br>Sistem Adi guncellendi.';
                if (sistemKoduUpdated) resultHtml += '<br>Sistem Kodu guncellendi.';
                if (notFoundRows.length > 0) resultHtml += '<br><br><small class="text-muted">' + notFoundRows.length + ' satir tabloda bulunamadi</small>';
                resultHtml += '<br><br><small>Degisiklikleri kalici yapmak icin "Kaydet" butonuna basin.</small>';

                Swal.fire({ icon: 'success', title: 'Iceri Aktarildi', html: resultHtml, confirmButtonColor: '#0d9488' });
            } catch (err) {
                Swal.fire('Hata', 'Dosya okunurken hata olustu: ' + err.message, 'error');
                console.error('Import hatasi:', err);
            }

            input.value = '';
        };

        reader.readAsArrayBuffer(file);
    }

    async function handleUpdateButtonClick() {
        if (!hot) {
            Swal.fire('Uyari', 'Tablo henuz yuklenmedi.', 'warning');
            return;
        }

        const updatedData = hot.getData().map(row => ({
            AraUrunNo: row[4],
            Performans: row[7]
        }));

        const urunNo = document.getElementById('ddUrunID').value;
        const sistemAdi = document.getElementById('tbSistemAdi').value;
        const sistemKodu = document.getElementById('tbSistemKodu').value;

        const btn = document.getElementById('updateButton');
        btn.disabled = true;
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Kaydediliyor...';

        Swal.fire({ title: 'Kaydediliyor...', text: 'Lutfen bekleyin', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

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
                Swal.fire('Basarili', 'Performans ve sistem bilgileri guncellendi!', 'success').then(() => location.reload());
            } else {
                Swal.fire('Uyari', result.message || 'Beklenmedik bir hata olustu.', 'warning');
            }
        } catch (error) {
            Swal.close();
            Swal.fire('Hata', 'Sunucuyla baglanti kurulamadi.', 'error');
            console.error('Hata:', error);
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    }
</script>
@endpush