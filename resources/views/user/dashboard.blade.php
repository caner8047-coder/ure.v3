@extends('layouts.user')

@section('title', 'Personel Paneli')

@section('page-actions')
    <a href="{{ route('user.tasks') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-list-check me-1"></i>Gorevlerim
    </a>
    <a href="{{ route('user.assigned') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-check2-square me-1"></i>Uretime Hazir
    </a>
    <a href="{{ route('user.available') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-hand-index-thumb me-1"></i>Alinabilir Isler
    </a>
@endsection

@section('content')
    <div class="panel-surface">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="section-title" style="margin: 0;">Hos geldin, {{ Auth::user()->name ?? 'Personel' }}</h3>
                <p class="section-copy">Gunluk gorev ozeti ve performans takibi</p>
            </div>
            <span class="soft-badge success" id="dashboardStatusPill">Hazir</span>
        </div>
        <div class="info-list">
            <div class="info-row">
                <span>Ad Soyad</span>
                <strong>{{ trim((Auth::user()->name ?? '') . ' ' . (Auth::user()->surname ?? '')) ?: '-' }}</strong>
            </div>
            <div class="info-row">
                <span>E-posta</span>
                <strong>{{ Auth::user()->email ?? '-' }}</strong>
            </div>
            <div class="info-row">
                <span>Sicil No</span>
                <strong>{{ Auth::user()->personnel_no ?? '-' }}</strong>
            </div>
            <div class="info-row">
                <span>Son veri</span>
                <strong id="dashboardRefreshLabel">Bekleniyor</strong>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <article class="metric-card">
            <p class="metric-label">Aktif Gorev</p>
            <h3 class="metric-value" id="statAktifGorev">0</h3>
            <div class="metric-foot">
                <span class="soft-badge warning">Anlik</span>
                <a href="{{ route('user.tasks') }}" class="btn btn-outline-secondary btn-sm">Listele</a>
            </div>
        </article>
        <article class="metric-card">
            <p class="metric-label">Tamamlanan</p>
            <h3 class="metric-value" id="statTamamlanan">0</h3>
            <div class="metric-foot">
                <span class="soft-badge success">Biten</span>
                <a href="{{ route('user.completed') }}" class="btn btn-outline-secondary btn-sm">Gor</a>
            </div>
        </article>
        <article class="metric-card">
            <p class="metric-label">Uretime Hazir</p>
            <h3 class="metric-value" id="statUretimeHazir">0</h3>
            <div class="metric-foot">
                <span class="soft-badge">Onay</span>
                <a href="{{ route('user.assigned') }}" class="btn btn-outline-secondary btn-sm">Incele</a>
            </div>
        </article>
        <article class="metric-card">
            <p class="metric-label">Bekleyen Adet</p>
            <h3 class="metric-value" id="statBekleyen">0</h3>
            <div class="metric-foot">
                <span class="soft-badge dark" id="statPulseLabel">Hazir</span>
                <a href="{{ route('user.report') }}" class="btn btn-outline-secondary btn-sm">Detay</a>
            </div>
        </article>
    </div>

    <div class="content-grid">
        <section class="panel-surface table-panel">
            <div class="panel-toolbar">
                <div class="panel-toolbar-copy"><h3>Son gorevlerim</h3></div>
                <div class="panel-toolbar-meta"><span class="soft-badge">Canli</span></div>
            </div>
            <div class="table-shell">
                <table class="table-modern table-sm">
                    <thead>
                        <tr>
                            <th>Gorev</th>
                            <th>Bolum</th>
                            <th>Adet</th>
                            <th>Durum</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody id="sonGorevler">
                        <tr><td colspan="5" class="text-center text-muted py-3">Henuz gorev yok.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="stack-list">
            <section class="panel-surface">
                <h3 class="section-title" style="margin-bottom: 12px;">Bugun nasil ilerleyelim?</h3>
                <div class="info-list">
                    <div class="info-row"><span>1. Uretime hazir</span><strong>Onayla</strong></div>
                    <div class="info-row"><span>2. Aktif gorevler</span><strong>Uretimi kaydet</strong></div>
                    <div class="info-row"><span>3. Rapor ekranin</span><strong>Performansini kontrol et</strong></div>
                </div>
            </section>
        </div>
    </div>

    <span id="dashboardStatusLabel" style="display:none;">Hazir</span>
    <span id="announcementBox" style="display:none;"></span>
@endsection

@push('scripts')
<script>
const dashboardRecentBody = document.getElementById('sonGorevler');

function setDashboardRefresh(text) {
    const label = text || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    document.getElementById('dashboardRefreshLabel').textContent = label;
}

function renderRecentTasks(tasks, assignedTasks = []) {
    const activeRows = (tasks || []).map((task) => ({ ...task, dashboardStatus: 'Uretimde', dashboardBadge: 'warning' }));
    const readyRows = (assignedTasks || [])
        .filter((task) => toInt(task.Baslatilabilir) === 1)
        .map((task) => ({
            ...task,
            UretilebilirAdet: task.Adet,
            GorevBaslamaTarihi: task.GorevTarihi,
            dashboardStatus: 'Uretime Hazir',
            dashboardBadge: 'success',
        }));
    const rows = readyRows.concat(activeRows);

    if (!rows.length) {
        dashboardRecentBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Henuz gorev yok.</td></tr>';
        return;
    }
    dashboardRecentBody.innerHTML = rows.slice(0, 6).map((task) => `
        <tr>
            <td>${escapeHtml(task.AraUrunAdi || task.UrunAdi || '-')}</td>
            <td>${escapeHtml(task.BolumAdi || '-')}</td>
            <td>${toInt(task.UretilebilirAdet ?? task.Adet)}</td>
            <td><span class="soft-badge ${task.dashboardBadge || 'warning'}">${escapeHtml(task.dashboardStatus || 'Uretimde')}</span></td>
            <td>${escapeHtml(task.GorevBaslamaTarihi || '-')}</td>
        </tr>
    `).join('');
}

function updateDashboardMood(stats) {
    const activeTasks = Number(stats.aktifGorevler ?? 0);
    const availableTasks = Number(stats.alinabilir ?? 0);
    const readyTasks = Number(stats.uretimeHazir ?? 0);
    const pendingAmount = Number(stats.bekleyenAdet ?? 0);
    const statusPill = document.getElementById('dashboardStatusPill');
    const statusLabel = document.getElementById('dashboardStatusLabel');
    const pulseLabel = document.getElementById('statPulseLabel');

    if (readyTasks > 0) {
        statusPill.textContent = 'Onay bekliyor'; statusPill.className = 'soft-badge'; statusLabel.textContent = 'Uretime hazir';
    } else if (activeTasks > 0) {
        statusPill.textContent = 'Odak aktif'; statusPill.className = 'soft-badge warning'; statusLabel.textContent = 'Aktif tempo';
    } else if (availableTasks > 0) {
        statusPill.textContent = 'Yeni is hazir'; statusPill.className = 'soft-badge'; statusLabel.textContent = 'Uygun gorev var';
    } else {
        statusPill.textContent = 'Akis sakin'; statusPill.className = 'soft-badge success'; statusLabel.textContent = 'Hazir';
    }
    pulseLabel.textContent = pendingAmount > 0 ? 'Bekleyen var' : 'Bekleme yok';
}

function refreshDashboard() {
    Promise.all([
        fetch('/api/panel/dashboard-stats').then((r) => r.json()),
        fetch('/api/panel/my-tasks').then((r) => r.json()),
        fetch('/api/panel/assigned-to-me').then((r) => r.json()),
    ]).then(([stats, tasks, assigned]) => {
        setDashboardRefresh();
        if (stats.success) {
            document.getElementById('statAktifGorev').textContent = stats.aktifGorevler ?? 0;
            document.getElementById('statTamamlanan').textContent = stats.tamamlanan ?? 0;
            document.getElementById('statUretimeHazir').textContent = stats.uretimeHazir ?? 0;
            document.getElementById('statBekleyen').textContent = stats.bekleyenAdet ?? 0;
            updateDashboardMood(stats);
        }
        renderRecentTasks(tasks.tasks || [], assigned.tasks || []);
    }).catch(() => {
        setDashboardRefresh('Veri alinamadi');
        dashboardRecentBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Gorev verileri yuklenemedi.</td></tr>';
    });
}

// Canlı senkronizasyon: Layout'taki polling bu fonksiyonu çağıracak
window.refreshPersonnelTasks = refreshDashboard;

// İlk yükleme
refreshDashboard();
</script>
@endpush
