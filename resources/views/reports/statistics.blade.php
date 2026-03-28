@extends('layouts.app')

@section('content')
    <!-- 🎨 Tema Stili -->
    <style>
        body {
            background-color: #faf8f3;
        }

        .istatistik-container {
            background: #fffdf9;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-family: 'Segoe UI', sans-serif;
        }

        h2 {
            color: #4b3621;
            font-weight: 700;
            margin-bottom: 25px;
        }

        .list_of_members {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .list_of_members .card {
            background: linear-gradient(180deg, #f6f1e7, #ffffff);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
            text-align: center;
            transition: 0.3s;
        }

        .list_of_members .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.1);
        }

        .card h3 {
            font-size: 26px;
            color: #2b2b2b;
            margin-bottom: 8px;
        }

        .card p {
            font-size: 15px;
            color: #6d5c48;
            margin: 0;
        }

        /* 🎛️ Seçim Alanları */
        label {
            font-weight: 600;
            color: #4b3621;
            margin-bottom: 5px;
        }

        .styled-dropdown, .date-input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            background-color: #fffaf3;
            transition: 0.3s;
        }

        .styled-dropdown:hover, .date-input:hover {
            border-color: #d4af37;
            background-color: #fff;
            box-shadow: 0 0 4px rgba(212,175,55,0.4);
        }

        .dropdown-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 15px;
        }

        .modern-dropdown {
            display: flex;
            flex-direction: column;
        }

        .button-container {
            text-align: left;
            margin-top: 20px;
        }

        .btn-generate {
            background-color: #4b9460;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 28px;
            font-weight: 600;
            transition: 0.3s;
            cursor: pointer;
        }

        .btn-generate:hover {
            background-color: #3a7a4d;
            transform: translateY(-2px);
        }

        /* 📊 Grafik Alanı */
        #myChart {
            margin-top: 40px;
            width: 100% !important;
            height: 450px !important;
            background: #fffefb;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            padding: 10px;
        }
    </style>

    <!-- 📦 JS Kütüphaneleri -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

    <div class="istatistik-container">
        <h2>📈 Genel İstatistikler</h2>

        <!-- 🧮 Sayısal Kartlar -->
        <div class="list_of_members">
            <div class="card">
                <h3 id="lGorev">0</h3>
                <p>Toplam İhtiyacımız Olan Görev (Malzeme/Parça) Sayısı</p>
            </div>
            <div class="card">
                <h3 id="lPersonel">0</h3>
                <p>Toplam Personel Sayısı</p>
            </div>
            <div class="card">
                <h3 id="lMesaj">0</h3>
                <p>Toplam Mesaj Sayısı</p>
            </div>
            <div class="card">
                <h3 id="lTamamlanan">0</h3>
                <p>Tamamlanan Görev Sayısı</p>
            </div>
        </div>

        <!-- 🎛️ Filtre Alanı -->
        <div class="dropdown-row">
            <div class="modern-dropdown">
                <label for="ddlStatisticType">İstatistik Türü:</label>
                <select id="ddlStatisticType" class="styled-dropdown">
                    <option value="1">Genel Üretim İstatistiği</option>
                    <option value="2">Personel Bazlı İstatistik</option>
                    <option value="3">Bölüm Bazlı İstatistik</option>
                    <option value="4">Ürün Bazlı İstatistik</option>
                    <option value="5">Ara Ürün Bazlı İstatistik</option>
                </select>
            </div>

            <div class="modern-dropdown">
                <label for="ddlPeriod">Periyot:</label>
                <select id="ddlPeriod" class="styled-dropdown">
                    <option value="Daily">Günlük</option>
                    <option value="Weekly">Haftalık</option>
                    <option value="Monthly">Aylık</option>
                    <option value="Yearly">Yıllık</option>
                    <option value="Custom">Tarih Aralığı</option>
                </select>
            </div>

            <div class="modern-dropdown">
                <label for="ddlSecondary">Adlar:</label>
                <select id="ddlSecondary" class="styled-dropdown">
                    <option value="0">Hepsi</option>
                </select>
            </div>

            <div class="modern-dropdown">
                <label for="ddlDataType">Veri Türü:</label>
                <select id="ddlDataType" class="styled-dropdown">
                    <option value="performans">Performansa Göre İstatistik</option>
                    <option value="adet">Üretim Adedine Göre İstatistik</option>
                </select>
            </div>
        </div>

        <!-- 📅 Tarih Aralığı -->
        <div id="pnlCustomDateRange" style="display: none;">
            <div class="dropdown-row">
                <div class="modern-dropdown">
                    <label for="txtStartDate">Başlangıç Tarihi:</label>
                    <input type="date" id="txtStartDate" class="date-input">
                </div>
                <div class="modern-dropdown">
                    <label for="txtEndDate">Bitiş Tarihi:</label>
                    <input type="date" id="txtEndDate" class="date-input">
                </div>
            </div>
        </div>

        <!-- 📊 Rapor Butonu -->
        <div class="button-container">
            <button id="btnGenerateReport" class="btn-generate">Rapor Oluştur</button>
        </div>

        <!-- 📈 Grafik Alanı -->
        <canvas id="myChart"></canvas>
    </div>

    <!-- 📊 Chart.js Scriptleri -->
    <script>
        let currentChart = null;

        async function fetchDashboardStats() {
            try {
                const res = await fetch('/api/reports/dashboard-stats');
                const data = await res.json();
                document.getElementById('lGorev').innerText = data.tasks_count || 0;
                document.getElementById('lPersonel').innerText = data.personnel_count || 0;
                document.getElementById('lMesaj').innerText = data.messages_count || 0;
                document.getElementById('lTamamlanan').innerText = data.completed_tasks || 0;
            } catch(e) { console.error("Dashboard fetch error", e); }
        }

        async function updateLookups() {
            const type = document.getElementById('ddlStatisticType').value;
            try {
                const res = await fetch('/api/reports/lookups?type=' + type);
                const data = await res.json();
                const dsl = document.getElementById('ddlSecondary');
                dsl.innerHTML = '';
                data.forEach(x => {
                    dsl.innerHTML += `<option value="${x.id}">${x.text}</option>`;
                });
            } catch(e) { console.log("Lookup error", e); }
        }

        document.getElementById('ddlStatisticType').addEventListener('change', updateLookups);
        
        document.getElementById('ddlPeriod').addEventListener('change', function() {
            document.getElementById('pnlCustomDateRange').style.display = this.value === 'Custom' ? 'block' : 'none';
        });

        document.getElementById('btnGenerateReport').addEventListener('click', async () => {
             const payload = {
                  statistic_type: document.getElementById('ddlStatisticType').value,
                  period: document.getElementById('ddlPeriod').value,
                  secondary: document.getElementById('ddlSecondary').value,
                  data_type: document.getElementById('ddlDataType').value,
                  start_date: document.getElementById('txtStartDate').value,
                  end_date: document.getElementById('txtEndDate').value
             };
             
             try {
                  const res = await fetch('/api/reports/chart-data', {
                       method: 'POST',
                       headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                       body: JSON.stringify(payload)
                  });
                  const json = await res.json();
                  
                  if(payload.data_type === 'performans') {
                      generateChart(json.labels, json.data, json.refData);
                  } else {
                      generateChartAdet(json.labels, json.data, 'Üretim Miktarı (adet)');
                  }
             } catch(e) { console.error("Chart fetch error", e); }
        });

        function generateChart(labels, data, miktar) {
            if(currentChart) currentChart.destroy();
            const ctx = document.getElementById('myChart').getContext('2d');
            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Performans Puanı / Miktar (Tahmini Çarpan)',
                            data: data,
                            backgroundColor: 'rgba(75, 192, 192, 0.7)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Hedef / Ortalama (Yaklaşık)',
                            data: miktar,
                            backgroundColor: 'rgba(255, 99, 132, 0.7)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        datalabels: { display: true, color: 'black', anchor: 'end', align: 'top' },
                        legend: { display: true }
                    },
                    scales: { y: { beginAtZero: true } }
                },
                plugins: [ChartDataLabels]
            });
        }

        function generateChartAdet(labels, data, etiket) {
            if(currentChart) currentChart.destroy();
            const ctx = document.getElementById('myChart').getContext('2d');
            currentChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: etiket,
                        data: data,
                        backgroundColor: 'rgba(75, 192, 192, 0.3)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        datalabels: { display: true, color: 'black', anchor: 'end', align: 'top' },
                        legend: { display: true }
                    },
                    scales: { y: { beginAtZero: true } }
                },
                plugins: [ChartDataLabels]
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchDashboardStats();
            document.getElementById('btnGenerateReport').click();
        });
    </script>
</asp:Content>
