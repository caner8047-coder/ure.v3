@extends('layouts.user')
@section('title', 'Gorev Detay')

@section('page-actions')
    <a href="{{ route('user.tasks') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Gorevlere Don</a>
@endsection

@section('content')
<div class="content-grid">
    <section class="panel-surface">
        <div class="section-header compact">
            <div><h3 class="section-title">Gorev Detayi</h3></div>
            <span class="soft-badge warning">Aktif</span>
        </div>
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <article class="metric-card">
                <p class="metric-label">Toplam Adet</p>
                <h3 class="metric-value" id="toplamAdet">0</h3>
            </article>
            <article class="metric-card">
                <p class="metric-label">Kalan Adet</p>
                <h3 class="metric-value" id="kalanAdet" style="color: var(--z-danger);">0</h3>
            </article>
            <article class="metric-card">
                <p class="metric-label">Tamamlanan</p>
                <h3 class="metric-value" id="tamamlananAdet" style="color: var(--z-success);">0</h3>
            </article>
        </div>
        <div class="info-list">
            <div class="info-row"><span>Urun</span><strong id="urunAdi">-</strong></div>
            <div class="info-row"><span>Bolum</span><strong id="bolumAdi">-</strong></div>
        </div>
    </section>

    <div class="stack-list">
        <section class="panel-surface">
            <div class="section-header compact">
                <div><h3 class="section-title"><i class="bi bi-check2-square me-2"></i>Uretim Girisi</h3></div>
            </div>
            <div class="stack-list">
                <div>
                    <label class="form-label">Uretilen Adet</label>
                    <input type="number" id="uretimAdet" class="form-control" min="1" value="1">
                </div>
                <div>
                    <label class="form-label">Not (Opsiyonel)</label>
                    <textarea id="uretimNotu" class="form-control" rows="2"></textarea>
                </div>
                <button class="btn btn-success w-100" onclick="uretimKaydet()"><i class="bi bi-save me-1"></i>Uretim Kaydet</button>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
const taskId = {{ (int) $id }};
const taskCsrfToken = document.querySelector('meta[name="csrf-token"]').content;

function loadTaskDetail() {
    fetch(`/api/panel/task/${taskId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.task) {
                alert(data.message || 'Gorev detayi yuklenemedi.');
                return;
            }

            const task = data.task;
            const toplamAdet = parseInt(task.Adet || 0, 10);
            const kalanAdet = parseInt(task.BekleyenAdet || 0, 10);

            document.getElementById('urunAdi').textContent = task.AraUrunAdi || task.UrunAdi || '-';
            document.getElementById('bolumAdi').textContent = task.BolumAdi || '-';
            document.getElementById('toplamAdet').textContent = toplamAdet;
            document.getElementById('kalanAdet').textContent = kalanAdet;
            document.getElementById('tamamlananAdet').textContent = Math.max(0, toplamAdet - kalanAdet);
            document.getElementById('uretimAdet').max = Math.max(1, kalanAdet);
        })
        .catch(() => alert('Gorev detayi yuklenirken hata olustu.'));
}

function uretimKaydet() {
    let adet = parseInt(document.getElementById('uretimAdet').value);
    if (adet < 1) { alert('Adet 0\'dan buyuk olmali!'); return; }

    fetch(`/api/panel/task/${taskId}/complete`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': taskCsrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ adet })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Uretim kaydi basarisiz.');
                return;
            }

            alert(data.message || 'Uretim kaydedildi.');
            loadTaskDetail();
        })
        .catch(() => alert('Uretim kaydi sirasinda hata olustu.'));
}

loadTaskDetail();
</script>
@endpush
