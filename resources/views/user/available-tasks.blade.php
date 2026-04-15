@extends('layouts.user')
@section('title', 'Alinabilir Gorevler')

@section('page-actions')
    <button class="btn btn-outline-secondary btn-sm" onclick="loadAlinabilir()"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button>
@endsection

@section('content')
<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Alinabilir Gorevler</h3></div>
        <div class="panel-toolbar-meta"><span class="soft-badge">Firsat</span></div>
    </div>
    <div class="table-shell">
        <table class="table-modern table-sm">
            <thead>
                <tr><th>#</th><th>Urun</th><th>Bolum</th><th>Adet</th><th>Tarih</th><th>Islem</th></tr>
            </thead>
            <tbody id="alinabilirBody">
                <tr><td colspan="6" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
            </tbody>
        </table>
    </div>
</section>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

function loadAlinabilir() {
    fetch('/api/panel/available-tasks')
        .then(r => r.json())
        .then(data => {
            const tasks = data.tasks || [];
            if (!tasks.length) {
                document.getElementById('alinabilirBody').innerHTML = '<tr><td colspan="6" class="text-center py-3 text-muted">Henuz alinabilir gorev yok.</td></tr>';
                return;
            }

            document.getElementById('alinabilirBody').innerHTML = tasks.map(task => `
                <tr>
                    <td>${task.No}</td>
                    <td>${task.AraUrunAdi || task.UrunAdi || '-'}</td>
                    <td>${task.BolumAdi || '-'}</td>
                    <td>${task.Adet ?? 0}</td>
                    <td>${task.GorevBaslangicTarihi || '-'}</td>
                    <td><button class="btn btn-sm btn-success" onclick="gorevAl(${task.No}, ${task.Adet ?? 0})">Gorevi Al</button></td>
                </tr>
            `).join('');
        })
        .catch(() => {
            document.getElementById('alinabilirBody').innerHTML = '<tr><td colspan="6" class="text-center py-3 text-danger">Alinabilir gorevler yuklenemedi.</td></tr>';
        });
}

function gorevAl(gorevNo, varsayilanAdet) {
    if (!confirm('Bu gorevi almak istediginize emin misiniz?')) return;

    fetch(`/api/panel/take-task/${gorevNo}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ adet: varsayilanAdet })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Gorev alinamadi.');
                return;
            }

            alert(data.message || 'Gorev alindi.');
            loadAlinabilir();
        })
        .catch(() => alert('Gorev alinirken hata olustu.'));
}
loadAlinabilir();
</script>
@endpush
