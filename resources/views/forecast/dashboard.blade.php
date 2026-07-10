@extends('layouts.app')

@section('title', 'AI Talep Tahminleri')

@section('content')
<div class="panel-surface mb-4">
    <div class="section-header compact">
        <div>
            <h3 class="section-title">Yapay Zeka Talep Tahmin Paneli</h3>
            <p class="section-copy">Prophet AI zaman serisi modeliyle sipariş geçmişi analiz edilerek gelecek dönem talep öngörüleri üretilir.</p>
        </div>
        <div>
            <button id="run-forecast-btn" class="btn btn-primary" onclick="triggerForecast()">
                <i class="bi bi-play-circle me-1"></i>Tahmini Yeniden Hesapla
            </button>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Sol Liste: Ürünler ve Son Durum -->
    <div class="col-lg-5">
        <div class="panel-surface h-100">
            <h4 class="section-title mb-3">Ürün Tahmin Listesi</h4>
            
            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-sm btn-outline-secondary active" onclick="setPeriod('monthly', this)">Aylık</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="setPeriod('weekly', this)">Haftalık</button>
            </div>

            <div class="table-responsive">
                <table class="table table-modern">
                    <thead>
                        <tr>
                            <th>Ürün Adı</th>
                            <th>Tarih</th>
                            <th>Tahmin</th>
                            <th>Durum</th>
                            <th>Aksiyon</th>
                        </tr>
                    </thead>
                    <tbody id="forecast-tbody">
                        <tr>
                            <td colspan="5" class="text-center text-muted">Yükleniyor...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Sağ Grafik ve AI Analizi -->
    <div class="col-lg-7">
        <div class="panel-surface h-100" id="detail-panel" style="display: none;">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="section-title" id="selected-product-title">Seçili Ürün Analizi</h4>
                    <p class="section-copy" id="selected-product-sub">Geçmiş satış trendi ve gelecek projeksiyonu</p>
                </div>
                <div class="d-flex gap-2" id="action-buttons-group">
                    <!-- Dinamik Onay/Override Butonları -->
                </div>
            </div>

            <!-- Grafik Alanı -->
            <div style="height: 300px; position: relative;" class="mb-4">
                <canvas id="forecastChart"></canvas>
            </div>

            <!-- AI Öneri Özeti -->
            <div class="alert alert-info d-flex gap-3 align-items-start p-3" style="border-radius: 12px; background: rgba(14, 116, 144, 0.1); border: 1px solid rgba(14, 116, 144, 0.2);">
                <i class="bi bi-robot" style="font-size: 1.5rem; color: var(--z-accent);"></i>
                <div>
                    <h5 style="margin: 0 0 6px 0; font-size: 0.9rem; font-weight: 600; color: #e2e8f0;">AI Asistan Önerisi</h5>
                    <p id="ai-summary-text" style="margin: 0; font-size: 0.8rem; color: #94a3b8; line-height: 1.5;">
                        Analiz hazırlanıyor...
                    </p>
                </div>
            </div>
        </div>

        <div class="panel-surface h-100 d-flex flex-column align-items-center justify-content-center text-center p-5" id="empty-detail-panel">
            <i class="bi bi-graph-up" style="font-size: 3rem; color: #475569;" class="mb-3"></i>
            <h5 class="mt-3" style="font-size: 1rem; color: #94a3b8;">Detayları İnceleyin</h5>
            <p style="font-size: 0.8rem; color: #64748b; max-width: 280px;">Grafiği, AI yorumlarını ve onay/override aksiyonlarını görüntülemek için soldan bir ürün seçin.</p>
        </div>
    </div>
</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--z-sidebar); border: 1px solid rgba(255, 255, 255, 0.08); color: #fff;">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                <h5 class="modal-title">Tahmini Manuel Güncelle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" style="font-size: 0.85rem; color: #94a3b8;">Sistem Tahmini</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="modal-system-forecast" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size: 0.85rem; color: #94a3b8;">Manuel Tahmini Miktar (Adet)</label>
                    <input type="number" class="form-control bg-dark text-white" id="modal-override-value" min="0">
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 255, 255, 0.08);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" onclick="submitOverride()">Güncelle</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    let currentPeriod = 'monthly';
    let activeProductId = null;
    let chartInstance = null;
    let overrideModal = null;
    let selectedSnapshotId = null;

    document.addEventListener('DOMContentLoaded', () => {
        overrideModal = new bootstrap.Modal(document.getElementById('overrideModal'));
        loadForecastList();

        // WebSocket Live Listener
        document.addEventListener('forecast-completed', (e) => {
            console.log('AI forecast calculation completed via WebSocket', e.detail);
            Swal.fire({
                icon: 'success',
                title: 'Tahminler Hesaplandı',
                text: 'Yeni tahmin verileri başarıyla yüklendi.',
                toast: true,
                position: 'top-end',
                timer: 3000,
                showConfirmButton: false
            });
            loadForecastList();
            if (activeProductId && parseInt(e.detail.target_id) === activeProductId) {
                showDetail(activeProductId);
            }
        });
    });

    function setPeriod(period, btn) {
        currentPeriod = period;
        document.querySelectorAll('.panel-surface .d-flex .btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        loadForecastList();
    }

    async function loadForecastList() {
        try {
            const r = await fetch(`/api/forecast/products?period_type=${currentPeriod}`);
            const d = await r.json();
            const tbody = document.getElementById('forecast-tbody');
            
            if (!d.success || d.forecasts.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Tahmin kaydı bulunamadı.</td></tr>`;
                return;
            }

            tbody.innerHTML = '';
            d.forecasts.forEach(f => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                if (activeProductId === f.product_id) {
                    tr.style.background = 'rgba(255,255,255,0.04)';
                }
                tr.onclick = () => {
                    document.querySelectorAll('#forecast-tbody tr').forEach(r => r.style.background = '');
                    tr.style.background = 'rgba(255,255,255,0.04)';
                    showDetail(f.product_id);
                };

                const dateStr = new Date(f.period_date).toLocaleDateString('tr-TR', { month: 'short', year: 'numeric' });
                const value = f.status === 'overridden' ? f.override_value : Math.round(f.forecasted_demand);
                const statusBadge = f.status === 'approved' 
                    ? '<span class="badge bg-success-subtle text-success">Onaylandı</span>'
                    : f.status === 'overridden'
                    ? '<span class="badge bg-warning-subtle text-warning">Override</span>'
                    : '<span class="badge bg-secondary-subtle text-muted">Beklemede</span>';

                tr.innerHTML = `
                    <td><strong>${f.product_name}</strong></td>
                    <td>${dateStr}</td>
                    <td>${value} Adet</td>
                    <td>${statusBadge}</td>
                    <td><button class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></button></td>
                `;
                tbody.appendChild(tr);
            });
        } catch (e) {
            console.error(e);
        }
    }

    async function showDetail(productId) {
        activeProductId = productId;
        document.getElementById('empty-detail-panel').style.display = 'none';
        document.getElementById('detail-panel').style.display = 'block';

        try {
            const r = await fetch(`/api/forecast/product/${productId}?period_type=${currentPeriod}`);
            const d = await r.json();

            if (!d.success) return;

            document.getElementById('selected-product-title').textContent = d.product_name;

            // Latest snapshot for actions
            const latestPred = d.predictions[d.predictions.length - 1];
            selectedSnapshotId = latestPred ? latestPred.id : null;

            // Set AI summary
            document.getElementById('ai-summary-text').textContent = latestPred && latestPred.ai_summary 
                ? latestPred.ai_summary 
                : 'Seçili ürün için henüz AI açıklaması oluşturulmamış.';

            // Setup buttons
            const actionGroup = document.getElementById('action-buttons-group');
            actionGroup.innerHTML = '';
            if (latestPred && latestPred.status === 'predicted') {
                actionGroup.innerHTML = `
                    <button class="btn btn-sm btn-success" onclick="approveForecast(${latestPred.id})">Onayla</button>
                    <button class="btn btn-sm btn-outline-warning" onclick="openOverride(${latestPred.id}, ${Math.round(latestPred.forecasted_demand)})">Manuel Düzenle</button>
                `;
            } else if (latestPred) {
                actionGroup.innerHTML = `<span class="text-muted small">Bu tahmin ${latestPred.status === 'approved' ? 'onaylandı' : 'güncellendi'}.</span>`;
            }

            // Draw Chart
            renderChart(d.history, d.predictions);

        } catch (e) {
            console.error(e);
        }
    }

    function renderChart(history, predictions) {
        const ctx = document.getElementById('forecastChart').getContext('2d');
        
        if (chartInstance) {
            chartInstance.destroy();
        }

        const labels = [];
        const actuals = [];
        const forecasts = [];
        const lowerBounds = [];
        const upperBounds = [];

        // Add history points
        history.forEach(h => {
            labels.push(new Date(h.ds).toLocaleDateString('tr-TR', { month: 'short', year: 'numeric' }));
            actuals.push(h.y);
            forecasts.push(null);
            lowerBounds.push(null);
            upperBounds.push(null);
        });

        // Add predictions points
        predictions.forEach(p => {
            labels.push(new Date(p.period_date).toLocaleDateString('tr-TR', { month: 'short', year: 'numeric' }));
            actuals.push(null);
            forecasts.push(p.status === 'overridden' ? p.override_value : parseFloat(p.forecasted_demand));
            lowerBounds.push(parseFloat(p.confidence_lower));
            upperBounds.push(parseFloat(p.confidence_upper));
        });

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Gerçekleşen Talep',
                        data: actuals,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        spanGaps: false
                    },
                    {
                        label: 'Öngörülen Talep',
                        data: forecasts,
                        borderColor: '#10b981',
                        borderDash: [5, 5],
                        borderWidth: 3,
                        spanGaps: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#f8fafc' }
                    }
                }
            }
        });
    }

    async function approveForecast(id) {
        try {
            const r = await fetch(`/api/forecast/approve/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            const d = await r.json();
            if (d.success) {
                Swal.fire('Onaylandı', 'Talep tahmini onaylandı.', 'success');
                loadForecastList();
                showDetail(activeProductId);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function openOverride(id, systemVal) {
        selectedSnapshotId = id;
        document.getElementById('modal-system-forecast').value = systemVal + ' Adet';
        document.getElementById('modal-override-value').value = systemVal;
        overrideModal.show();
    }

    async function submitOverride() {
        const val = document.getElementById('modal-override-value').value;
        try {
            const r = await fetch(`/api/forecast/override/${selectedSnapshotId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ override_value: val })
            });
            const d = await r.json();
            if (d.success) {
                overrideModal.hide();
                Swal.fire('Güncellendi', 'Manuel tahmin kaydedildi.', 'success');
                loadForecastList();
                showDetail(activeProductId);
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function triggerForecast() {
        const btn = document.getElementById('run-forecast-btn');
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Hesaplanıyor...`;

        try {
            const r = await fetch('/api/forecast/run-now', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ period_type: currentPeriod })
            });
            const d = await r.json();
            if (d.success) {
                Swal.fire('Başlatıldı', d.message, 'success');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Hata', 'Tahmin başlatılamadı.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<i class="bi bi-play-circle me-1"></i>Tahmini Yeniden Hesapla`;
        }
    }
</script>
@endpush
