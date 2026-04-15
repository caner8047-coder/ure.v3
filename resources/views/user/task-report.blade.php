@extends('layouts.user')
@section('title', 'Gorev Raporlarim')

@section('content')
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <article class="metric-card">
        <p class="metric-label">Toplam Gorev</p>
        <h3 class="metric-value" id="totalGorev">0</h3>
    </article>
    <article class="metric-card">
        <p class="metric-label">Tamamlanan</p>
        <h3 class="metric-value" id="totalTamamlanan" style="color: var(--z-success);">0</h3>
    </article>
    <article class="metric-card">
        <p class="metric-label">Toplam Uretim</p>
        <h3 class="metric-value" id="totalUretim">0</h3>
    </article>
</div>

<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Aylik Performans</h3></div>
    </div>
    <div class="table-shell" id="reportBody">
        <p class="text-muted text-center py-4">Performans verileri yukleniyor...</p>
    </div>
</section>
@endsection

@push('scripts')
<script>
fetch('/api/panel/task-report')
    .then(r => r.json())
    .then(data => {
        const report = data.report || [];
        const totalGorev = report.reduce((sum, row) => sum + parseInt(row.GorevSayisi || 0, 10), 0);
        const totalUretim = report.reduce((sum, row) => sum + parseInt(row.ToplamUretim || 0, 10), 0);

        document.getElementById('totalGorev').textContent = totalGorev;
        document.getElementById('totalTamamlanan').textContent = totalGorev;
        document.getElementById('totalUretim').textContent = totalUretim;

        const body = document.getElementById('reportBody');
        if (!report.length) {
            body.innerHTML = '<p class="text-muted text-center py-4 mb-0">Henuz rapor verisi yok.</p>';
            return;
        }

        body.innerHTML = `
            <table class="table-modern table-sm">
                <thead>
                    <tr><th>Urun</th><th>Toplam Uretim</th><th>Gorev Sayisi</th><th>Ilk Gorev</th><th>Son Gorev</th></tr>
                </thead>
                <tbody>
                    ${report.map(row => `
                        <tr>
                            <td>${row.UrunAdi || '-'}</td>
                            <td>${row.ToplamUretim || 0}</td>
                            <td>${row.GorevSayisi || 0}</td>
                            <td>${row.IlkGorev || '-'}</td>
                            <td>${row.SonGorev || '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    })
    .catch(() => {
        document.getElementById('reportBody').innerHTML = '<p class="text-danger text-center py-4 mb-0">Rapor verisi yuklenemedi.</p>';
    });
</script>
@endpush
