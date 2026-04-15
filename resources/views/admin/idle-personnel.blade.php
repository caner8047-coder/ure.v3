@extends('layouts.app')
@section('title', 'Görevsiz Personeller')
@section('content')
<div class="panel-surface mt-3">
    <div class="orders-panel-heading mb-3">
        <div>
            <p>Personel Durumu</p>
            <h3><i class="bi bi-clock-history text-muted me-2"></i>Boştaki Personeller</h3>
            <p class="text-muted small mt-1 mb-0">Şu anda üzerinde aktif bir iş olmayan personeller.</p>
        </div>
    </div>

    <div class="table-responsive" style="border: 1px solid var(--z-border); border-radius: var(--z-radius); overflow:hidden;">
        <table class="table-modern">
            <thead>
                <tr><th>Personel No</th><th>Ad</th><th>Soyad</th><th>Bölüm</th><th>İşlem</th></tr>
            </thead>
            <tbody id="idleBody"><tr><td colspan="5" class="text-center">Yükleniyor...</td></tr></tbody>
        </table>
    </div>
</div>
<script>
fetch('/api/database/personnel/idle').then(r=>r.json()).then(data => {
    // Sadece üzerinde aktif görev olmayan personeller dönüyor
    let html = '';
    (data.personnel || data || []).forEach(p => {
        html += '<tr><td>' + (p.PersonelNo || p.id || '') + '</td><td>' + (p.Ad || p.name || '') + '</td>'
            + '<td>' + (p.Soyad || p.surname || '') + '</td><td>' + (p.BolumAdi || p.department || '') + '</td>'
            + '<td><button class="btn btn-sm btn-primary">Görev Ata</button></td></tr>';
    });
    document.getElementById('idleBody').innerHTML = html || '<tr><td colspan="5" class="text-center">Tüm personeller görevli</td></tr>';
});
</script>
@endsection
