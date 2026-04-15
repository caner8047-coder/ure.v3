@extends('layouts.app')

@section('title', 'Pasif Devam Eden Siparisler')

@section('page-actions')
    <span class="badge" style="background:var(--z-accent-soft);color:var(--z-accent);font-size:0.8rem;padding:6px 12px;" id="toplamBadge">0 kayit</span>
@endsection

@section('content')
    <div class="panel-surface" style="padding:12px 16px;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle" style="color:var(--z-accent);"></i>
            <span class="small text-muted">Bu sayfada, Excel yuklemesi sirasinda pasife alinan ancak aktif is emirleri devam eden siparisler listelenir. Uretim tamamlandiginda otomatik olarak kapanirlar.</span>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Pasif devam eden siparisler</h3>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th>Sip. No</th>
                        <th>Musteri</th>
                        <th>Urun Adi</th>
                        <th>Adet</th>
                        <th>Durum</th>
                        <th>Uretim Ilerlemesi</th>
                        <th>Is Emri Tarihi</th>
                        <th>Islem</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

function loadData() {
    fetch('/SiparisApi.ashx?action=getPasifDevamEden')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('tableBody').innerHTML =
                    `<tr><td colspan="8" class="text-center py-3 text-danger">${data.message || 'Hata'}</td></tr>`;
                return;
            }

            const rows = data.data || [];
            document.getElementById('toplamBadge').textContent = rows.length + ' kayit';

            if (!rows.length) {
                document.getElementById('tableBody').innerHTML =
                    '<tr><td colspan="8" class="text-center py-3 text-muted">Pasif devam eden siparis yok.</td></tr>';
                return;
            }

            document.getElementById('tableBody').innerHTML = rows.map(row => {
                let progressHtml = '';
                if (row.progress && row.progress.length) {
                    progressHtml = row.progress.map(p => {
                        const pct = p.toplam > 0 ? Math.round((p.tamamlanan / p.toplam) * 100) : 0;
                        const color = pct >= 100 ? 'var(--z-success)' : pct > 50 ? 'var(--z-accent)' : 'var(--z-warning)';
                        return `<div class="mb-1">
                            <small class="d-block">${p.bolum || '-'}: ${p.tamamlanan}/${p.toplam}</small>
                            <div style="height:5px;background:var(--z-border-light);border-radius:3px;overflow:hidden;">
                                <div style="height:100%;width:${pct}%;background:${color};border-radius:3px;transition:width 0.3s;"></div>
                            </div>
                        </div>`;
                    }).join('');
                } else {
                    progressHtml = '<span class="text-muted small">Bilgi yok</span>';
                }

                let durumBadge = '';
                if (row.durum === 'PasifDevamEden') {
                    durumBadge = '<span class="badge" style="background:var(--z-warning-soft);color:var(--z-warning);">Pasif Devam Eden</span>';
                } else {
                    durumBadge = `<span class="badge" style="background:var(--z-bg-soft);color:var(--z-text-secondary);">${row.durum || '-'}</span>`;
                }

                return `<tr>
                    <td>${row.siparisNo || '-'}</td>
                    <td>${row.musteri || '-'}</td>
                    <td>${row.urunAdi || '-'}</td>
                    <td>${row.adet || '-'}</td>
                    <td>${durumBadge}</td>
                    <td style="min-width:200px">${progressHtml}</td>
                    <td><small>${row.isEmriTarihi || '-'}</small></td>
                    <td>
                        <button class="btn btn-sm btn-outline-success" title="Yeniden Aktif Et"
                            onclick="reactivate(${row.no})">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        })
        .catch(() => {
            document.getElementById('tableBody').innerHTML =
                '<tr><td colspan="8" class="text-center py-3 text-danger">Veri yuklenemedi.</td></tr>';
        });
}

function reactivate(no) {
    if (!confirm('Bu siparisi yeniden aktif etmek istediginize emin misiniz?')) return;

    fetch('/SiparisApi.ashx?action=reactivateOrder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ no })
    })
    .then(r => r.json())
    .then(data => {
        alert(data.message || 'Islem tamamlandi.');
        loadData();
    })
    .catch(() => alert('Hata olustu.'));
}

loadData();
</script>
@endpush
