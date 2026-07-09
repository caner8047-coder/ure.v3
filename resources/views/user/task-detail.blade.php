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
            <span class="soft-badge warning" id="taskStatusBadge">Aktif</span>
        </div>
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <article class="metric-card">
                <p class="metric-label">Toplam Adet</p>
                <h3 class="metric-value" id="toplamAdet">0</h3>
            </article>
            <article class="metric-card">
                <p class="metric-label">Uretilebilir</p>
                <h3 class="metric-value" id="uretilebilirAdet" style="color: var(--z-success);">0</h3>
            </article>
            <article class="metric-card">
                <p class="metric-label">Bekleyen</p>
                <h3 class="metric-value" id="bekleyenAdet" style="color: var(--z-danger);">0</h3>
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
                <button class="btn btn-success w-100" id="uretimKaydetBtn" onclick="uretimKaydet()"><i class="bi bi-save me-1"></i>Uretim Kaydet</button>
                <button class="btn btn-outline-secondary w-100 d-none" id="dependencyInfoBtn" type="button" onclick="showTaskDependencyInfo(taskId)">
                    <i class="bi bi-info-circle me-1"></i>Bekleme detayını gör
                </button>
                <div id="taskNotice" class="alert d-none mb-0" role="status" aria-live="polite"></div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
const taskId = {{ (int) $id }};
const taskCsrfToken = document.querySelector('meta[name="csrf-token"]').content;
const taskNotice = document.getElementById('taskNotice');
const saveButton = document.getElementById('uretimKaydetBtn');
let taskReadyForStart = false;
let taskReadyMax = 0;

function showTaskNotice(type, message) {
    taskNotice.className = `alert alert-${type} mb-0`;
    taskNotice.textContent = message;
}

function hideTaskNotice() {
    taskNotice.className = 'alert d-none mb-0';
    taskNotice.textContent = '';
}

function setSaving(isSaving) {
    saveButton.disabled = isSaving;
    saveButton.innerHTML = isSaving
        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Kaydediliyor'
        : '<i class="bi bi-save me-1"></i>Uretim Kaydet';
}

function syncProductionControls(uretilebilirAdet, bekleyenAdet, onayBekliyor = false) {
    const input = document.getElementById('uretimAdet');
    const dependencyButton = document.getElementById('dependencyInfoBtn');
    input.max = Math.max(1, uretilebilirAdet);
    taskReadyForStart = onayBekliyor && uretilebilirAdet > 0;
    taskReadyMax = taskReadyForStart ? uretilebilirAdet : 0;

    if (dependencyButton) {
        dependencyButton.classList.toggle('d-none', !(bekleyenAdet > 0 && (onayBekliyor || uretilebilirAdet <= 0)));
    }

    if (onayBekliyor) {
        input.disabled = !taskReadyForStart;
        saveButton.disabled = !taskReadyForStart;
        saveButton.innerHTML = taskReadyForStart
            ? '<i class="bi bi-check2-square me-1"></i>Gorevi Kabul Et'
            : '<i class="bi bi-hourglass-split me-1"></i>Parca Bekliyor';
        if (taskReadyForStart && (parseInt(input.value || 0, 10) < 1 || parseInt(input.value || 0, 10) > uretilebilirAdet)) {
            input.value = uretilebilirAdet;
        }
        return;
    }

    if (uretilebilirAdet <= 0) {
        input.value = 0;
        input.disabled = true;
        saveButton.disabled = true;
        saveButton.innerHTML = bekleyenAdet > 0
            ? '<i class="bi bi-hourglass-split me-1"></i>Parca Bekliyor'
            : '<i class="bi bi-check2-circle me-1"></i>Uretim Tamamlandi';
        return;
    }

    input.disabled = false;
    saveButton.disabled = false;
    saveButton.innerHTML = '<i class="bi bi-save me-1"></i>Uretim Kaydet';
    if (parseInt(input.value || 0, 10) > uretilebilirAdet) {
        input.value = uretilebilirAdet;
    }
}

function loadTaskDetail() {
    fetch(`/api/panel/task/${taskId}`)
        .then(r => r.json().then(data => ({ ok: r.ok, status: r.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok || !data.success || !data.task) {
                if (status === 404) {
                    showTaskNotice('success', 'Gorev tamamlandi.');
                    setTimeout(() => { window.location.href = '{{ route('user.tasks') }}'; }, 700);
                    return;
                }
                showTaskNotice('danger', data.message || 'Gorev detayi yuklenemedi.');
                return;
            }

            renderTaskDetail(data.task);
        })
        .catch(() => showTaskNotice('danger', 'Gorev detayi yuklenirken hata olustu.'));
}

function renderTaskDetail(task) {
    const uretilebilirAdet = toInt(task.UretilebilirAdet ?? task.Adet);
    const bekleyenAdet = toInt(task.BekleyenAdet);
    const toplamAdet = toInt(task.ToplamAdet || (uretilebilirAdet + bekleyenAdet));
    const onayDurumu = String(task.Onay || '').trim().toLowerCase();
    const onayBekliyor = ['hazir', 'ready'].includes(onayDurumu);

    document.getElementById('urunAdi').textContent = task.AraUrunAdi || task.UrunAdi || '-';
    document.getElementById('bolumAdi').textContent = task.BolumAdi || '-';
    document.getElementById('toplamAdet').textContent = toplamAdet;
    document.getElementById('uretilebilirAdet').textContent = uretilebilirAdet;
    document.getElementById('bekleyenAdet').textContent = bekleyenAdet;
    document.getElementById('taskStatusBadge').textContent = onayBekliyor ? 'Uretime Hazir' : 'Aktif';
    document.getElementById('taskStatusBadge').className = onayBekliyor ? 'soft-badge success' : 'soft-badge warning';
    syncProductionControls(uretilebilirAdet, bekleyenAdet, onayBekliyor);
    if (onayBekliyor) {
        showTaskNotice('warning', taskReadyForStart
            ? 'Bu gorev uretime hazir. Uretime almak istediginiz adedi girip kabul edin.'
            : 'Bu gorev su an parca/stok bekliyor.');
    } else if (uretilebilirAdet <= 0 && bekleyenAdet > 0) {
        showTaskNotice('warning', 'Bu gorev su an parca/stok bekliyor.');
    }
}

function completeTaskView(message) {
    document.getElementById('taskStatusBadge').textContent = 'Tamamlandi';
    document.getElementById('taskStatusBadge').className = 'soft-badge success';
    document.getElementById('uretilebilirAdet').textContent = '0';
    document.getElementById('bekleyenAdet').textContent = '0';
    document.getElementById('uretimAdet').value = 0;
    syncProductionControls(0, 0, false);
    showTaskNotice('success', message || 'Gorev tamamlandi.');
    setTimeout(() => { window.location.href = '{{ route('user.tasks') }}'; }, 700);
}

function parseJsonResponse(response) {
    return response.json()
        .catch(() => ({}))
        .then(data => ({ ok: response.ok, status: response.status, data }));
}

function gorevKabulEt(adet) {
    hideTaskNotice();
    setSaving(true);
    fetch(`/api/panel/task/${taskId}/start`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': taskCsrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ adet })
    })
        .then(parseJsonResponse)
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showTaskNotice('danger', data.message || 'Gorev kabul edilemedi.');
                return;
            }

            showTaskNotice('success', data.message || 'Gorev uretime alindi.');
            loadTaskDetail();
        })
        .catch(() => showTaskNotice('danger', 'Gorev kabul edilirken hata olustu.'))
        .finally(() => setSaving(false));
}

function uretimKaydet() {
    let adet = parseInt(document.getElementById('uretimAdet').value);
    if (adet < 1) {
        showTaskNotice('warning', 'Adet 0\'dan buyuk olmali.');
        return;
    }
    if (taskReadyForStart) {
        if (adet > taskReadyMax) {
            showTaskNotice('warning', `En fazla ${taskReadyMax} adet kabul edilebilir.`);
            return;
        }

        gorevKabulEt(adet);
        return;
    }

    hideTaskNotice();
    setSaving(true);
    fetch(`/api/panel/task/${taskId}/complete`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': taskCsrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ adet })
    })
        .then(parseJsonResponse)
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                showTaskNotice('danger', data.message || 'Uretim kaydi basarisiz.');
                return;
            }

            document.getElementById('uretimNotu').value = '';
            if (data.completed || toInt(data.toplamAdet) <= 0) {
                completeTaskView(data.message || 'Uretim kaydedildi.');
                return;
            }

            showTaskNotice('success', data.message || 'Uretim kaydedildi.');
            loadTaskDetail();
        })
        .catch(() => showTaskNotice('danger', 'Uretim kaydi sirasinda hata olustu.'))
        .finally(() => {
            const uretilebilirAdet = toInt(document.getElementById('uretilebilirAdet').textContent);
            if (uretilebilirAdet > 0) {
                setSaving(false);
            }
        });
}

loadTaskDetail();
</script>
@endpush
