@extends('layouts.user')
@section('title', 'Uretime Hazir Gorevler')

@section('page-actions')
    <button class="btn btn-outline-secondary btn-sm" onclick="loadAssignedTasks()"><i class="bi bi-arrow-clockwise me-1"></i>Yenile</button>
@endsection

@section('content')
<section class="panel-surface table-panel">
    <div class="panel-toolbar">
        <div class="panel-toolbar-copy"><h3>Uretime Hazir Gorevler</h3></div>
        <div class="panel-toolbar-meta"><span class="soft-badge" id="assignedFooter">Toplam: 0 gorev</span></div>
    </div>
    <div class="table-shell">
        <table class="table-modern table-sm">
            <thead>
                <tr><th>#</th><th>Urun</th><th>Bolum</th><th>Adet</th><th>Aciklama</th><th>Veren</th><th>Tarih</th><th>Durum</th><th>Islem</th></tr>
            </thead>
            <tbody id="assignedBody">
                <tr><td colspan="9" class="text-center py-4 text-muted">Henuz uretime hazir gorev yok.</td></tr>
            </tbody>
        </table>
    </div>
</section>
<div id="assignedNotice" class="alert d-none mt-3 mb-0" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
const assignedCsrfToken = document.querySelector('meta[name="csrf-token"]').content;
const assignedNotice = document.getElementById('assignedNotice');
const assignedBody = document.getElementById('assignedBody');
const assignedFooter = document.getElementById('assignedFooter');

function showAssignedNotice(type, message) {
    assignedNotice.className = `alert alert-${type} mt-3 mb-0`;
    assignedNotice.textContent = message;
}

function clearAssignedNotice() {
    assignedNotice.className = 'alert d-none mt-3 mb-0';
    assignedNotice.textContent = '';
}

function loadAssignedTasks() {
    assignedBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Gorevler yukleniyor...</td></tr>';
    fetch('/api/panel/assigned-to-me')
        .then(r => r.json())
        .then(data => {
            const tasks = data.tasks || [];
            assignedFooter.textContent = `Toplam: ${tasks.length} gorev`;
            if (!tasks.length) {
                assignedBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">Henuz uretime hazir gorev yok.</td></tr>';
                return;
            }

            assignedBody.innerHTML = tasks.map(task => {
                const canStart = toInt(task.Baslatilabilir) === 1;
                const readyAmount = toInt(task.Adet);
                const totalAmount = toInt(task.ToplamAdet ?? task.Adet);
                const waitReason = task.BeklemeNedeni || '';
                const buttonText = task.ButonMetni || 'Bekliyor';
                const dependencyAction = !canStart
                    ? dependencyInfoButtonHtml(task.No, 'btn btn-sm btn-outline-secondary')
                    : '';
                const action = canStart
                    ? `<button class="btn btn-sm btn-success" onclick="startAssignedTask(${toInt(task.No)}, ${Math.max(1, readyAmount)})"><i class="bi bi-check2-square me-1"></i>Uretime Gec</button>`
                    : `<div class="d-inline-flex gap-2 align-items-center"><button class="btn btn-sm btn-secondary" disabled title="${escapeHtml(waitReason || buttonText)}">${escapeHtml(buttonText)}</button>${dependencyAction}</div>`;
                const badgeClass = canStart ? 'success' : 'warning';
                const amountText = readyAmount !== totalAmount
                    ? `${readyAmount} hazır / ${totalAmount} toplam`
                    : String(totalAmount);

                return `
            <tr>
                <td>${escapeHtml(task.No)}</td>
                <td>${escapeHtml(task.AraUrunAdi || task.UrunAdi || '-')}</td>
                <td>${escapeHtml(task.BolumAdi || '-')}</td>
                <td>${escapeHtml(amountText)}</td>
                <td>${escapeHtml(waitReason || task.Aciklama || '-')}</td>
                <td>${escapeHtml(task.Veren || 'Sistem')}</td>
                <td>${escapeHtml(task.GorevTarihi || task.GorevBaslangicTarihi || '-')}</td>
                <td><span class="soft-badge ${badgeClass}">${escapeHtml(task.Durum || 'Bekliyor')}</span></td>
                <td>${action}</td>
            </tr>
                `;
            }).join('');
        })
        .catch(() => {
            assignedBody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Gorevler yuklenemedi.</td></tr>';
        });
}

// Canlı senkronizasyon: Layout'taki polling bu fonksiyonu çağıracak
window.refreshPersonnelTasks = loadAssignedTasks;

function startAssignedTask(taskNo, maxAdet) {
    const safeMax = Math.max(1, toInt(maxAdet));
    clearAssignedNotice();
    Swal.fire({
        title: 'Kaç adet üretime alınacak?',
        input: 'number',
        inputLabel: `Hazır adet: ${safeMax}`,
        inputValue: safeMax,
        inputAttributes: { min: 1, max: safeMax },
        showCancelButton: true,
        confirmButtonText: 'Uretime Gec',
        cancelButtonText: 'Vazgec',
        confirmButtonColor: '#059669',
        inputValidator: (value) => {
            const parsed = parseInt(value, 10);
            if (!parsed || parsed < 1) return 'Lutfen gecerli bir adet girin.';
            if (parsed > safeMax) return `En fazla ${safeMax} adet uretime alinabilir.`;
            return null;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;

        fetch(`/api/panel/task/${taskNo}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': assignedCsrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ adet: parseInt(result.value, 10) })
        })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    showAssignedNotice('danger', data.message || 'Gorev uretime alinamadi.');
                    return;
                }

                showAssignedNotice('success', data.message || 'Gorev uretime alindi.');
                loadAssignedTasks();
            })
            .catch(() => showAssignedNotice('danger', 'Gorev uretime alinirken hata olustu.'));
    });
}

loadAssignedTasks();
</script>
@endpush
