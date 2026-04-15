@extends('layouts.app')

@section('title', 'Isci Performans')

@section('page-actions')
    <button type="button" class="btn btn-primary btn-sm" onclick="loadPerformance()">
        <i class="bi bi-arrow-clockwise me-1"></i>Listeyi Yenile
    </button>
@endsection

@push('styles')
<style>
    .perf-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 10px;
        border-radius: 4px;
        background: var(--z-bg-soft);
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        font-weight: 600;
    }
    .perf-chip.success { background: var(--z-success-soft); color: var(--z-success); }
    .perf-chip.warning { background: var(--z-warning-soft); color: var(--z-warning); }
</style>
@endpush

@section('content')
    {{-- Inline Stats --}}
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Siralamadaki Kisi</p>
            <h3 class="metric-value" id="summaryPeopleInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">En Yuksek Puan</p>
            <h3 class="metric-value" id="summaryTopScoreInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Ortalama Puan</p>
            <h3 class="metric-value" id="summaryAverageInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Son Yenileme</p>
            <h3 class="metric-value" id="summaryUpdatedInline" style="font-size: 1rem;">Bekleniyor</h3>
        </article>
    </div>

    {{-- Status bar --}}
    <div class="panel-surface">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-wrap gap-3">
                <span class="small"><span class="text-muted">Lider:</span> <strong id="leaderNameMeta">Bekleniyor</strong></span>
                <span class="small"><span class="text-muted">Toplam kisi:</span> <strong id="summaryPeopleMeta">0</strong></span>
                <span class="small"><span class="text-muted">En yuksek:</span> <strong id="summaryTopScoreMeta">0</strong></span>
                <span class="small"><span class="text-muted">Ortalama:</span> <strong id="summaryAverageMeta">0</strong></span>
            </div>
            <span class="perf-chip success" id="leaderBadge">Veri bekleniyor</span>
        </div>
    </div>

    {{-- Performance Table --}}
    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="section-title mb-0">Performans siralamasi</h3>
            <span class="perf-chip" id="performanceStatus">Yukleniyor</span>
        </div>

        <div class="table-shell">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th class="text-center">Sira</th>
                        <th>Personel Adi</th>
                        <th class="text-center">Bitirilen Gorev</th>
                        <th class="text-center">Ortalama Performans</th>
                        <th class="text-center">Toplam Puan</th>
                    </tr>
                </thead>
                <tbody id="performanceBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
function loadPerformance() {
    document.getElementById('performanceStatus').textContent = 'Yukleniyor';
    document.getElementById('performanceStatus').className = 'perf-chip';

    fetch('/api/reports/performance')
        .then((r) => r.json())
        .then((data) => {
            const rows = data.data || [];
            updatePerformanceSummary(rows);

            if (!rows.length) {
                document.getElementById('performanceBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Kayit bulunamadi.</td></tr>';
                document.getElementById('performanceStatus').textContent = 'Bos sonuc';
                document.getElementById('performanceStatus').className = 'perf-chip';
                setPerformanceStamp();
                return;
            }

            document.getElementById('performanceBody').innerHTML = rows.map((row, idx) => {
                const rankBadge = idx === 0 ? '👑' : idx === 1 ? '🥈' : idx === 2 ? '🥉' : '';
                const avg = Math.round(Number(row.OrtalamaPerformans || 0) * 10) / 10;

                return `
                    <tr>
                        <td class="text-center fw-bold">${idx + 1} ${rankBadge}</td>
                        <td class="fw-bold">${escapeHtml(row.PersonelAdi || '-')}</td>
                        <td class="text-center">${formatNumber(row.ToplamGorevSayisi || 0)}</td>
                        <td class="text-center">${avg ? `%${avg}` : '-'}</td>
                        <td class="text-center fw-bold text-success">${formatNumber(row.ToplamPerformansScore || 0)}</td>
                    </tr>
                `;
            }).join('');

            document.getElementById('performanceStatus').textContent = 'Hazir';
            document.getElementById('performanceStatus').className = 'perf-chip success';
            setPerformanceStamp();
        })
        .catch(() => {
            document.getElementById('performanceBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Hata olustu.</td></tr>';
            document.getElementById('performanceStatus').textContent = 'Hata';
            document.getElementById('performanceStatus').className = 'perf-chip warning';
        });
}

function updatePerformanceSummary(rows) {
    const totalPeople = rows.length;
    const topScore = rows.length ? Number(rows[0].ToplamPerformansScore || 0) : 0;
    const averageScore = rows.length
        ? Math.round(rows.reduce((sum, row) => sum + Number(row.ToplamPerformansScore || 0), 0) / rows.length)
        : 0;
    const leader = rows.length ? rows[0].PersonelAdi || 'Bekleniyor' : 'Bekleniyor';

    document.getElementById('summaryPeopleInline').textContent = formatNumber(totalPeople);
    document.getElementById('summaryPeopleMeta').textContent = formatNumber(totalPeople);
    document.getElementById('summaryTopScoreInline').textContent = formatNumber(topScore);
    document.getElementById('summaryTopScoreMeta').textContent = formatNumber(topScore);
    document.getElementById('summaryAverageInline').textContent = formatNumber(averageScore);
    document.getElementById('summaryAverageMeta').textContent = formatNumber(averageScore);
    document.getElementById('leaderNameMeta').textContent = leader;
    document.getElementById('leaderBadge').textContent = rows.length ? `${leader} lider` : 'Veri bekleniyor';
}

function setPerformanceStamp() {
    document.getElementById('summaryUpdatedInline').textContent = new Date().toLocaleTimeString('tr-TR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('tr-TR');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', loadPerformance);
</script>
@endpush
