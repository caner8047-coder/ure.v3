@extends('layouts.user')
@section('title', 'Aktif Gorevlerim')

@section('page-actions')
    <button class="btn btn-outline-secondary btn-sm" onclick="loadGorevler()"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button>
@endsection

@section('content')
<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Aktif Gorevlerim</h3></div>
        <div class="panel-toolbar-meta"><span class="soft-badge" id="gorevFooter">Toplam: 0 gorev</span></div>
    </div>
    <div class="table-shell">
        <table class="table-modern table-sm">
            <thead>
                <tr><th>#</th><th>Urun</th><th>Bolum</th><th>Toplam Adet</th><th>Kalan Adet</th><th>Baslangic</th><th>Islem</th></tr>
            </thead>
            <tbody id="gorevBody">
                <tr><td colspan="7" class="text-center py-4 text-muted">Gorevler yukleniyor...</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
function loadGorevler() {
    document.getElementById('gorevBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Gorevler yukleniyor...</td></tr>';
    fetch('/api/panel/my-tasks')
        .then(r => r.json())
        .then(data => {
            const tasks = data.tasks || [];
            if (!tasks.length) {
                document.getElementById('gorevBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">Henuz atanmis goreviniz yok.</td></tr>';
                document.getElementById('gorevFooter').textContent = 'Toplam: 0 gorev';
                return;
            }

            document.getElementById('gorevBody').innerHTML = tasks.map(task => `
                <tr>
                    <td>${task.No}</td>
                    <td>${task.AraUrunAdi || task.UrunAdi || '-'}</td>
                    <td>${task.BolumAdi || '-'}</td>
                    <td>${task.Adet ?? 0}</td>
                    <td>${task.BekleyenAdet ?? 0}</td>
                    <td>${task.GorevBaslamaTarihi || '-'}</td>
                    <td><a class="btn btn-sm btn-primary" href="/panel/gorev/${task.No}">Detay</a></td>
                </tr>
            `).join('');
            document.getElementById('gorevFooter').textContent = `Toplam: ${tasks.length} gorev`;
        })
        .catch(() => {
            document.getElementById('gorevBody').innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">Gorevler yuklenemedi.</td></tr>';
        });
}
loadGorevler();
</script>
@endpush
