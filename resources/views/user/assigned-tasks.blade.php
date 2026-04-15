@extends('layouts.user')
@section('title', 'Verilen Gorevler')

@section('content')
<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Bana Verilen Gorevler</h3></div>
        <div class="panel-toolbar-meta"><span class="soft-badge">Canli</span></div>
    </div>
    <div class="table-shell">
        <table class="table-modern table-sm">
            <thead>
                <tr><th>#</th><th>Urun</th><th>Bolum</th><th>Adet</th><th>Veren</th><th>Tarih</th><th>Durum</th></tr>
            </thead>
            <tbody id="assignedBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">Henuz atanan gorev yok.</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
fetch('/api/panel/assigned-to-me')
    .then(r => r.json())
    .then(data => {
        const tasks = data.tasks || [];
        const body = document.getElementById('assignedBody');
        if (!tasks.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Henuz atanan gorev yok.</td></tr>';
            return;
        }

        body.innerHTML = tasks.map(task => `
            <tr>
                <td>${task.No}</td>
                <td>${task.AraUrunAdi || task.UrunAdi || '-'}</td>
                <td>-</td>
                <td>${task.Adet ?? 0}</td>
                <td>Sistem</td>
                <td>${task.GorevBaslangicTarihi || '-'}</td>
                <td><span class="soft-badge warning">Bekliyor</span></td>
            </tr>
        `).join('');
    })
    .catch(() => {
        document.getElementById('assignedBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Gorevler yuklenemedi.</td></tr>';
    });
</script>
@endpush
