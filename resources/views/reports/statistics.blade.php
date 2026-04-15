@extends('layouts.app')

@section('title', 'Genel Istatistikler')

@section('page-actions')
    <button type="button" class="btn btn-primary btn-sm" id="btnGenerateReportTop">
        <i class="bi bi-bar-chart-line me-1"></i>Rapor Olustur
    </button>
@endsection

@push('styles')
<style>
    .stats-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }
    .stats-filter-grid.compact {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .stats-chart-shell {
        min-height: 480px;
        padding: 12px;
        border: 1px solid var(--z-border);
        border-radius: var(--z-radius);
        background: var(--z-bg-card);
    }
    #myChart {
        width: 100% !important;
        height: 430px !important;
    }
    .stats-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 4px;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        font-weight: 600;
    }
    .stats-chip.success { background: var(--z-success-soft); color: var(--z-success); }
    .stats-chip.warning { background: var(--z-warning-soft); color: var(--z-warning); }

    @media (max-width: 1080px) {
        .stats-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 720px) {
        .stats-filter-grid, .stats-filter-grid.compact { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
    {{-- Inline Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam Gorev</p>
            <h3 class="metric-value" id="summaryTasksCard">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Personel</p>
            <h3 class="metric-value" id="summaryPersonnelCard">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Toplam Mesaj</p>
            <h3 class="metric-value" id="summaryMessageCard">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Tamamlanan</p>
            <h3 class="metric-value" id="summaryCompletedCard">0</h3>
        </article>
    </div>

    {{-- Status bar --}}
    <div class="panel-surface">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-wrap gap-3">
                <span class="small"><span class="text-muted">Istatistik:</span> <strong id="summaryTypeMeta">Genel Uretim</strong></span>
                <span class="small"><span class="text-muted">Periyot:</span> <strong id="summaryPeriodMeta">Gunluk</strong></span>
                <span class="small"><span class="text-muted">Veri:</span> <strong id="summaryDataTypeMeta">Performans</strong></span>
                <span class="small"><span class="text-muted">Son:</span> <strong id="summaryUpdatedMeta">Bekleniyor</strong></span>
            </div>
            <span class="stats-chip" id="statsStatusChip">Hazir</span>
        </div>
    </div>

    {{-- Filters --}}
    <section class="panel-surface">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Grafik parametreleri</h3>
            <span class="stats-chip success">Chart kontrolu</span>
        </div>

        <div class="stats-filter-grid">
            <div>
                <label for="ddlStatisticType" class="form-label">Istatistik Turu</label>
                <select id="ddlStatisticType" class="form-select">
                    <option value="1">Genel Uretim Istatistigi</option>
                    <option value="2">Personel Bazli Istatistik</option>
                    <option value="3">Bolum Bazli Istatistik</option>
                    <option value="4">Urun Bazli Istatistik</option>
                    <option value="5">Ara Urun Bazli Istatistik</option>
                </select>
            </div>

            <div>
                <label for="ddlPeriod" class="form-label">Periyot</label>
                <select id="ddlPeriod" class="form-select">
                    <option value="Daily">Gunluk</option>
                    <option value="Weekly">Haftalik</option>
                    <option value="Monthly">Aylik</option>
                    <option value="Yearly">Yillik</option>
                    <option value="Custom">Tarih Araligi</option>
                </select>
            </div>

            <div>
                <label for="ddlSecondary" class="form-label">Adlar</label>
                <select id="ddlSecondary" class="form-select">
                    <option value="0">Hepsi</option>
                </select>
            </div>

            <div>
                <label for="ddlDataType" class="form-label">Veri Turu</label>
                <select id="ddlDataType" class="form-select">
                    <option value="performans">Performansa Gore Istatistik</option>
                    <option value="adet">Uretim Adedine Gore Istatistik</option>
                </select>
            </div>
        </div>

        <div id="pnlCustomDateRange" style="display:none; margin-top: 16px;">
            <div class="stats-filter-grid compact">
                <div>
                    <label for="txtStartDate" class="form-label">Baslangic Tarihi</label>
                    <input type="date" id="txtStartDate" class="form-control">
                </div>
                <div>
                    <label for="txtEndDate" class="form-label">Bitis Tarihi</label>
                    <input type="date" id="txtEndDate" class="form-control">
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button id="btnGenerateReport" class="btn btn-primary btn-sm">
                <i class="bi bi-bar-chart me-1"></i>Rapor Olustur
            </button>
        </div>
    </section>

    {{-- Chart --}}
    <section class="panel-surface">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Donemsel chart</h3>
            <span class="stats-chip warning" id="chartModeChip">Chart hazirlaniyor</span>
        </div>

        <div class="stats-chart-shell">
            <canvas id="myChart"></canvas>
        </div>
    </section>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
let currentChart = null;

async function fetchDashboardStats() {
    try {
        const response = await fetch('/api/reports/dashboard-stats');
        const data = await response.json();

        const tasks = data.tasks_count || 0;
        const personnel = data.personnel_count || 0;
        const messages = data.messages_count || 0;
        const completed = data.completed_tasks || 0;

        document.getElementById('summaryTasksCard').textContent = formatNumber(tasks);
        document.getElementById('summaryPersonnelCard').textContent = formatNumber(personnel);
        document.getElementById('summaryMessageCard').textContent = formatNumber(messages);
        document.getElementById('summaryCompletedCard').textContent = formatNumber(completed);
    } catch (error) {
        console.error('Dashboard fetch error', error);
    }
}

async function updateLookups() {
    const type = document.getElementById('ddlStatisticType').value;

    try {
        const response = await fetch(`/api/reports/lookups?type=${type}`);
        const data = await response.json();
        const select = document.getElementById('ddlSecondary');
        select.innerHTML = '';
        data.forEach((item) => {
            select.innerHTML += `<option value="${item.id}">${item.text}</option>`;
        });
    } catch (error) {
        console.log('Lookup error', error);
    }
}

function updateStatisticsSummary() {
    document.getElementById('summaryTypeMeta').textContent = document.getElementById('ddlStatisticType').options[document.getElementById('ddlStatisticType').selectedIndex].text;
    document.getElementById('summaryPeriodMeta').textContent = document.getElementById('ddlPeriod').options[document.getElementById('ddlPeriod').selectedIndex].text;
    document.getElementById('summaryDataTypeMeta').textContent = document.getElementById('ddlDataType').options[document.getElementById('ddlDataType').selectedIndex].text;
}

async function generateReport() {
    const payload = {
        statistic_type: document.getElementById('ddlStatisticType').value,
        period: document.getElementById('ddlPeriod').value,
        secondary: document.getElementById('ddlSecondary').value,
        data_type: document.getElementById('ddlDataType').value,
        start_date: document.getElementById('txtStartDate').value,
        end_date: document.getElementById('txtEndDate').value
    };

    updateStatisticsSummary();
    document.getElementById('statsStatusChip').textContent = 'Sorgulaniyor';
    document.getElementById('statsStatusChip').className = 'stats-chip warning';
    document.getElementById('chartModeChip').textContent = 'Chart olusuyor';
    document.getElementById('chartModeChip').className = 'stats-chip warning';

    try {
        const response = await fetch('/api/reports/chart-data', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });

        const json = await response.json();
        if (payload.data_type === 'performans') {
            generateChart(json.labels, json.data, json.refData);
        } else {
            generateChartAdet(json.labels, json.data, 'Uretim Miktari (adet)');
        }

        document.getElementById('statsStatusChip').textContent = 'Rapor hazir';
        document.getElementById('statsStatusChip').className = 'stats-chip success';
        document.getElementById('chartModeChip').textContent = payload.data_type === 'performans' ? 'Performans chart' : 'Adet chart';
        document.getElementById('chartModeChip').className = 'stats-chip success';
        document.getElementById('summaryUpdatedMeta').textContent = new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    } catch (error) {
        console.error('Chart fetch error', error);
        document.getElementById('statsStatusChip').textContent = 'Hata';
        document.getElementById('chartModeChip').textContent = 'Chart hatasi';
        document.getElementById('statsStatusChip').className = 'stats-chip warning';
        document.getElementById('chartModeChip').className = 'stats-chip warning';
    }
}

function generateChart(labels, data, referenceData) {
    if (currentChart) currentChart.destroy();
    const ctx = document.getElementById('myChart').getContext('2d');
    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Performans Puani / Miktar',
                    data,
                    backgroundColor: 'rgba(13, 148, 136, 0.72)',
                    borderColor: 'rgba(13, 148, 136, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'Hedef / Ortalama',
                    data: referenceData,
                    backgroundColor: 'rgba(5, 150, 105, 0.28)',
                    borderColor: 'rgba(5, 150, 105, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                datalabels: { display: true, color: '#1a1d23', anchor: 'end', align: 'top' },
                legend: { display: true }
            },
            scales: { y: { beginAtZero: true } }
        },
        plugins: [ChartDataLabels]
    });
}

function generateChartAdet(labels, data, label) {
    if (currentChart) currentChart.destroy();
    const ctx = document.getElementById('myChart').getContext('2d');
    currentChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label,
                data,
                backgroundColor: 'rgba(5, 150, 105, 0.45)',
                borderColor: 'rgba(5, 150, 105, 1)',
                borderWidth: 1,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                datalabels: { display: true, color: '#1a1d23', anchor: 'end', align: 'top' },
                legend: { display: true }
            },
            scales: { y: { beginAtZero: true } }
        },
        plugins: [ChartDataLabels]
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

document.getElementById('ddlStatisticType').addEventListener('change', () => {
    updateLookups();
    updateStatisticsSummary();
});

document.getElementById('ddlPeriod').addEventListener('change', function () {
    document.getElementById('pnlCustomDateRange').style.display = this.value === 'Custom' ? 'block' : 'none';
    updateStatisticsSummary();
});

document.getElementById('ddlDataType').addEventListener('change', updateStatisticsSummary);
document.getElementById('btnGenerateReport').addEventListener('click', generateReport);
document.getElementById('btnGenerateReportTop').addEventListener('click', generateReport);

document.addEventListener('DOMContentLoaded', async () => {
    updateStatisticsSummary();
    await fetchDashboardStats();
    await updateLookups();
    await generateReport();
});
</script>
@endpush
