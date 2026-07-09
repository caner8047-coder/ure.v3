@extends('layouts.user')
@section('title', 'Tamamlanan Gorevler')

@section('content')
<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Tamamlanan Gorevler</h3></div>
        <div class="panel-toolbar-meta"><span class="soft-badge success">Biten</span></div>
    </div>
    <div class="table-shell">
        <table class="table-modern table-sm">
            <thead>
                <tr><th>#</th><th>Urun</th><th>Bolum</th><th>Adet</th><th>Tamamlanma Tarihi</th></tr>
            </thead>
            <tbody id="completedBody">
                <tr><td colspan="5" class="text-center py-4 text-muted">Henuz tamamlanan gorev yok.</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
fetch('/api/panel/completed-tasks')
    .then(r => r.json())
    .then(data => {
        const tasks = data.tasks || [];
        const body = document.getElementById('completedBody');
        if (!tasks.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Henuz tamamlanan gorev yok.</td></tr>';
            return;
        }

        body.innerHTML = tasks.map(task => `
            <tr>
                <td>${escapeHtml(task.No)}</td>
                <td>${escapeHtml(task.AraUrunAdi || task.UrunAdi || '-')}</td>
                <td>${escapeHtml(task.BolumAdi || '-')}</td>
                <td>${toInt(task.ToplamAdet ?? task.Adet)}</td>
                <td>${escapeHtml(task.GorevBitisTarihi || task.GorevBaslamaTarihi || '-')}</td>
            </tr>
        `).join('');
    })
    .catch(() => {
        document.getElementById('completedBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Tamamlanan gorevler yuklenemedi.</td></tr>';
    });
</script>
@endpush
