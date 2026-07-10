@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="stats-grid">
    <article class="metric-card">
        <p class="metric-label">Aktif İş Emirleri</p>
        <h3 class="metric-value" id="active-work-orders-val">0</h3>
        <p class="metric-copy">Havuzda bekleyen iş emri sayısı.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Tamamlanan Görevler</p>
        <h3 class="metric-value" id="completed-tasks-val">0</h3>
        <p class="metric-copy">Kapatılan görev toplamı.</p>
    </article>

    <article class="metric-card">
        <p class="metric-label">Kritik Stok Uyarıları</p>
        <h3 class="metric-value" id="critical-stock-warnings-val">0</h3>
        <p class="metric-copy">Eşik altındaki stok sayısı.</p>
    </article>
</div>

@if(Auth::user()->isAdmin())
    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <h3 class="section-title">Yonetici Paneli</h3>
                <p class="section-copy">Tum uretim ve stok fonksiyonlarina erisiminiz bulunmaktadir.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.index') }}" class="btn btn-primary"><i class="bi bi-grid-1x2 me-1"></i>Is Emri Havuzu</a>
            <a href="{{ route('stocks.index') }}" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Stoklar</a>
            <a href="{{ route('reports.tasks') }}" class="btn btn-outline-secondary"><i class="bi bi-graph-up-arrow me-1"></i>Raporlar</a>
        </div>
    </div>
@else
    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <h3 class="section-title">Personel Paneli</h3>
                <p class="section-copy">Size atanan gorevleri buradan takip edebilirsiniz.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('user.dashboard') }}" class="btn btn-primary"><i class="bi bi-list-check me-1"></i>Gorevlerim</a>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    async function loadDashboardStats() {
        try {
            const response = await fetch('/api/dashboard-stats');
            const data = await response.json();
            
            document.getElementById('active-work-orders-val').textContent = data.poolTasks || 0;
            document.getElementById('completed-tasks-val').textContent = data.completedTasks || 0;
            document.getElementById('critical-stock-warnings-val').textContent = data.criticalStocks || 0;
        } catch (e) {
            console.error('Failed to load dashboard stats:', e);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadDashboardStats();

        // WebSocket Live Updates
        document.addEventListener('work-order-updated', () => loadDashboardStats());
        document.addEventListener('stock-alert-received', () => loadDashboardStats());
        document.addEventListener('task-assigned-to-me', () => loadDashboardStats());
    });
</script>
@endpush
