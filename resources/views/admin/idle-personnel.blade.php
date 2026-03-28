@extends('layouts.app')
@section('title', 'Görevsiz Personeller')
@section('content')
<div class="container-fluid mt-3">
    <h4><i class="bi bi-person-x"></i> Görevsiz Personeller</h4>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="idleTable">
                    <thead class="table-dark">
                        <tr><th>Personel No</th><th>Ad</th><th>Soyad</th><th>Bölüm</th><th>İşlem</th></tr>
                    </thead>
                    <tbody id="idleBody"><tr><td colspan="5" class="text-center">Yükleniyor...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
fetch('/api/database/personnel').then(r=>r.json()).then(data => {
    // tbPersonelGorev'de aktif görevi olmayan personelleri filtrele
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
