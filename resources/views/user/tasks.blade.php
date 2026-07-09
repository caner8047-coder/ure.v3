@extends('layouts.user')
@section('title', 'Görevlerim')

@section('content')
<div id="loadingOverlay" class="legacy-loading-overlay">
    <div>
        <div class="legacy-spinner mx-auto"></div>
        <p class="text-center mt-3 fw-bold text-dark">İşlem yapılıyor, lütfen bekleyin...</p>
    </div>
</div>

<div class="container mt-4 mb-5">
    <h2 class="legacy-page-heading">Görevlerim</h2>
    <div class="card-container" id="gorevCards">
        <div class="legacy-empty-state">Görevler yükleniyor...</div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .task-card.task-card-grouped {
        border-color: #c4b5fd;
        border-top-color: #7c3aed;
        box-shadow: 0 12px 28px rgba(124, 58, 237, 0.12);
        cursor: pointer;
    }

    .task-card.task-card-grouped .adet-badge {
        background: #6d28d9;
    }

    .task-card.task-card-grouped .img-box {
        border-color: rgba(124, 58, 237, 0.25);
        background: #f5f3ff;
    }

    .task-group-info-btn {
        align-items: center;
        background: #ede9fe;
        border: 1px solid #c4b5fd;
        border-radius: 999px;
        color: #6d28d9;
        display: inline-flex;
        font-size: 0.76rem;
        font-weight: 800;
        gap: 5px;
        height: 30px;
        justify-content: center;
        padding: 0 9px;
        position: absolute;
        right: 10px;
        top: 10px;
        z-index: 3;
    }

    .task-group-info-btn:hover,
    .task-group-info-btn:focus {
        background: #7c3aed;
        color: #fff;
    }

    .task-group-info-btn i {
        transition: transform 0.18s ease;
    }

    .task-group-info-btn.is-open i {
        transform: rotate(180deg);
    }

    .task-group-drawer {
        background: #f5f3ff;
        border: 1px solid #ddd6fe;
        border-radius: 10px;
        display: grid;
        gap: 7px;
        margin-top: 10px;
        padding: 10px;
    }

    .task-group-drawer-head {
        align-items: center;
        color: #5b21b6;
        display: flex;
        font-size: 0.76rem;
        font-weight: 800;
        justify-content: space-between;
        line-height: 1.25;
    }

    .task-group-drawer-head span {
        color: #7c3aed;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .task-group-breakdown-row {
        background: #fff;
        border: 1px solid #ede9fe;
        border-radius: 8px;
        cursor: pointer;
        padding: 8px 9px;
        transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .task-group-breakdown-row:hover {
        border-color: #c4b5fd;
        box-shadow: 0 8px 18px rgba(124, 58, 237, 0.12);
        transform: translateY(-1px);
    }

    .task-group-breakdown-row-main {
        align-items: center;
        display: flex;
        gap: 8px;
        justify-content: space-between;
    }

    .task-group-breakdown-row strong {
        color: #312e81;
        font-size: 0.8rem;
        line-height: 1.25;
    }

    .task-group-breakdown-row .task-group-qty {
        background: #ede9fe;
        border-radius: 999px;
        color: #6d28d9;
        flex: 0 0 auto;
        font-size: 0.74rem;
        font-weight: 800;
        padding: 3px 8px;
    }

    .task-group-row-action {
        align-items: center;
        background: #7c3aed;
        border: 0;
        border-radius: 999px;
        color: #fff;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 0.7rem;
        font-weight: 800;
        gap: 4px;
        min-height: 24px;
        padding: 3px 8px;
    }

    .task-group-row-action:hover,
    .task-group-row-action:focus {
        background: #5b21b6;
        color: #fff;
    }

    .task-group-breakdown-row span,
    .task-group-breakdown-row small {
        display: block;
        line-height: 1.25;
    }

    .task-group-breakdown-row small {
        color: var(--z-text-secondary);
        font-size: 0.72rem;
        margin-top: 3px;
    }
</style>
@endpush

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const gorevCards = document.getElementById('gorevCards');
const loadingOverlay = document.getElementById('loadingOverlay');
const expandedTaskGroups = new Set();

function showLoading() {
    loadingOverlay.classList.add('active');
}

function hideLoading() {
    loadingOverlay.classList.remove('active');
}

function taskAmount(task, fallback = 0) {
    return toInt(task.UretilebilirAdet ?? task.Adet ?? task.ToplamAdet ?? fallback);
}

function taskTotal(task) {
    return toInt(task.ToplamAdet ?? (toInt(task.Adet) + toInt(task.BekleyenAdet)));
}

function taskDate(task) {
    return task.GorevBaslamaTarihi || task.GorevTarihi || '-';
}

function taskWaitReason(task) {
    return task.BeklemeNedeni || task.WaitReason || '';
}

function isGroupedTask(task) {
    return task.GrupMu === true || toInt(task.GrupKayitSayisi) > 1;
}

function groupTaskIds(task) {
    return Array.isArray(task.GrupGorevNoListesi)
        ? task.GrupGorevNoListesi.map((id) => toInt(id)).filter((id) => id > 0)
        : [];
}

function taskGroupBreakdownHtml(task) {
    const details = Array.isArray(task.GrupDetaylari) ? task.GrupDetaylari : [];
    const rows = details.length ? details : groupTaskIds(task).map((id) => ({ No: id, Adet: 0 }));

    return `
        <div class="task-group-drawer">
            <div class="task-group-drawer-head">
                <strong>Parçalı görev dökümü</strong>
                <span>${rows.length} kayıt</span>
            </div>
            ${rows.map((row) => {
                const order = row.SiparisNo ? `Sipariş: ${row.SiparisNo}` : 'Sipariş izi yok';
                const line = row.SiparisSatirNo ? `${order} / Satır: ${row.SiparisSatirNo}` : order;
                const rowNo = toInt(row.No);
                const quantity = Math.max(1, toInt(row.Adet || row.ToplamAdet));

                return `
                    <div class="task-group-breakdown-row" role="button" tabindex="0"
                        onclick="gorevDetayindanKabulEt(${rowNo}, ${quantity}, event)"
                        onkeydown="handleTaskGroupRowKey(event, ${rowNo}, ${quantity})"
                        aria-label="Görev #${rowNo} için ${quantity} adet kabul et">
                        <div class="task-group-breakdown-row-main">
                            <strong>Görev #${rowNo}</strong>
                            <span class="task-group-qty">${quantity} adet</span>
                            <button type="button" class="task-group-row-action"
                                onclick="gorevDetayindanKabulEt(${rowNo}, ${quantity}, event)">
                                <i class="bi bi-check2"></i> Kabul Et
                            </button>
                        </div>
                        <small>${escapeHtml(row.GorevTarihi || task.GorevTarihi || '-')}</small>
                        <small>${escapeHtml(line)}</small>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function taskGroupInfoButton(task) {
    if (!isGroupedTask(task)) return '';

    const count = toInt(task.GrupKayitSayisi);
    const groupKey = task.GrupAnahtari || '';
    const isOpen = expandedTaskGroups.has(groupKey);

    return `
        <button type="button"
            class="task-group-info-btn ${isOpen ? 'is-open' : ''}"
            onclick="toggleTaskGroupDrawer('${escapeHtml(groupKey)}', event)"
            aria-expanded="${isOpen ? 'true' : 'false'}"
            aria-label="Birleştirilen görev dökümü">
            ${count} görev <i class="bi bi-chevron-down"></i>
        </button>
    `;
}

function taskGroupDrawerHtml(task) {
    if (!isGroupedTask(task)) return '';
    const groupKey = task.GrupAnahtari || '';
    if (!expandedTaskGroups.has(groupKey)) return '';

    return taskGroupBreakdownHtml(task);
}

function toggleTaskGroupDrawer(groupKey, event = null) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (!groupKey) return;

    if (expandedTaskGroups.has(groupKey)) {
        expandedTaskGroups.delete(groupKey);
    } else {
        expandedTaskGroups.add(groupKey);
    }
    renderCurrentTasks();
}

function gorevDetayindanKabulEt(no, maxAdet, event = null) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    goreviKabulEt(toInt(no), Math.max(1, toInt(maxAdet)));
}

function handleTaskGroupRowKey(event, no, maxAdet) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    gorevDetayindanKabulEt(no, maxAdet, event);
}

let currentActiveTasks = [];
let currentAssignedTasks = [];

function renderTaskCard(task) {
    const no = toInt(task.No);
    const state = task._state || 'active';
    const isActive = state === 'active';
    const isReady = state === 'ready';
    const amount = isActive ? taskAmount(task) : taskTotal(task) || taskAmount(task);
    const readyAmount = isReady ? toInt(task.Adet ?? task.UretilebilirAdet ?? amount) : amount;
    const waiting = toInt(task.BekleyenAdet);
    const imageUrl = legacyTaskImage(task.Resim);
    const title = task.AraUrunAdi || task.UrunAdi || '-';
    const subtitle = task.UrunCesidi || (task.UrunAdi ? task.UrunAdi : 'Ara Mamül');
    const cardClass = isActive ? 'card-false card-active' : 'card-true card-ready';
    const waitReason = taskWaitReason(task);
    const blockedLabel = task.ButonMetni || 'Bekliyor';
    const grouped = isGroupedTask(task);
    const groupKey = task.GrupAnahtari || '';
    const groupIds = groupTaskIds(task);
    const groupedClass = grouped ? 'task-card-grouped' : '';
    const dependencyButton = waitReason || (!isReady && !isActive)
        ? dependencyInfoButtonHtml(no)
        : '';
    const readyMeta = isReady && readyAmount > 0 && readyAmount !== amount
        ? `<div class="task-meta"><i class="bi bi-check2-square me-1"></i>Hazır: <strong>${readyAmount}</strong> ürün</div>`
        : '';

    let actions = '';
    if (isReady) {
        actions = readyAmount > 0
            ? (grouped
                ? `<button type="button" class="btn btn-success" onclick="gorevGrubunuKabulEt('${escapeHtml(groupKey)}', ${JSON.stringify(groupIds)}, ${readyAmount})">Görevi Kabul Et</button>`
                : `<button type="button" class="btn btn-success" onclick="goreviKabulEt(${no}, ${readyAmount})">Görevi Kabul Et</button>`)
            : `<button type="button" class="btn btn-secondary" disabled>${escapeHtml(blockedLabel)}</button>`;
    } else if (isActive) {
        actions = `
            <button type="button" class="btn btn-warning" onclick="goreviBitir(${no}, ${Math.max(1, amount)})">Görevi Bitir</button>
            <button type="button" class="btn btn-danger" onclick="goreviSil(${no})">Sil</button>
        `;
    } else {
        actions = `
            <div class="dependency-action-row">
                <button type="button" class="btn btn-secondary dependency-main-btn" disabled title="${escapeHtml(waitReason || blockedLabel)}">${escapeHtml(blockedLabel)}</button>
                ${dependencyButton}
            </div>
        `;
    }

    return `
        <div class="task-card ${cardClass} ${groupedClass}" ${grouped ? `onclick="handleGroupedTaskCardClick('${escapeHtml(groupKey)}', event)"` : ''}>
            <div class="adet-badge">${amount} Adet</div>
            ${taskGroupInfoButton(task)}
            <div class="img-box">
                <img class="task-img" src="${escapeHtml(imageUrl)}" alt="Ürün Resmi"
                    onclick="showLegacyImage(this.src)"
                    onerror="this.onerror=null; this.src='/Resimler/resimYok.png';">
                <div class="overlay-text">
                    <div class="araurun-adi">${escapeHtml(title)}</div>
                    <div class="urun-adi">${escapeHtml(subtitle)}</div>
                </div>
            </div>
            <div class="task-meta"><i class="bi bi-calendar3 me-1"></i>${escapeHtml(taskDate(task))}</div>
            ${readyMeta}
            <div class="task-meta bekleyen">
                <i class="bi bi-box-seam me-1"></i>Bekleyen: <strong>${waiting}</strong> ürün
            </div>
            ${waitReason ? `<div class="task-meta bekleyen">${escapeHtml(waitReason)}</div>` : ''}
            ${taskGroupDrawerHtml(task)}
            <div class="btn-group">${actions}</div>
        </div>
    `;
}

function handleGroupedTaskCardClick(groupKey, event) {
    if (event.target.closest('button, a, input, .btn-group, .task-img')) return;
    toggleTaskGroupDrawer(groupKey, event);
}

function renderCurrentTasks() {
    const readyTasks = (currentAssignedTasks || [])
        .filter((task) => toInt(task.Baslatilabilir) === 1)
        .map((task) => ({ ...task, _state: 'ready' }));
    const waitingAssigned = (currentAssignedTasks || [])
        .filter((task) => toInt(task.Baslatilabilir) !== 1)
        .map((task) => ({ ...task, _state: 'waiting' }));
    const activeRows = (currentActiveTasks || []).map((task) => ({ ...task, _state: 'active' }));
    const tasks = readyTasks.concat(activeRows, waitingAssigned);

    if (!tasks.length) {
        gorevCards.innerHTML = '<div class="legacy-empty-state">Şu anda görev yok.</div>';
        return;
    }

    gorevCards.innerHTML = tasks.map(renderTaskCard).join('');
}

function renderTasks(activeTasks, assignedTasks) {
    currentActiveTasks = Array.isArray(activeTasks) ? activeTasks : [];
    currentAssignedTasks = Array.isArray(assignedTasks) ? assignedTasks : [];
    renderCurrentTasks();
}

function loadGorevler() {
    gorevCards.innerHTML = '<div class="legacy-empty-state">Görevler yükleniyor...</div>';
    Promise.all([
        fetch('/api/panel/my-tasks').then((r) => r.json()),
        fetch('/api/panel/assigned-to-me').then((r) => r.json())
    ]).then(([active, assigned]) => {
        renderTasks(active.tasks || [], assigned.tasks || []);
    }).catch(() => {
        gorevCards.innerHTML = '<div class="legacy-empty-state text-danger">Görevler yüklenemedi.</div>';
    });
}

// Canlı senkronizasyon: Layout'taki polling bu fonksiyonu çağıracak
window.refreshPersonnelTasks = loadGorevler;

function swalSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: message,
        showConfirmButton: false,
        timer: 1400,
        background: '#f4f5f7'
    });
}

function swalError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Hata',
        text: message || 'İşlem tamamlanamadı.',
        confirmButtonColor: '#0d9488',
        background: '#f4f5f7'
    });
}

function postJson(url, body = {}, method = 'POST') {
    return fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: method === 'DELETE' ? null : JSON.stringify(body)
    }).then((response) => response.json().catch(() => ({})).then((data) => ({ ok: response.ok, data })));
}

function goreviKabulEt(no, maxAdet) {
    const safeMax = Math.max(1, toInt(maxAdet));
    Swal.fire({
        title: 'Kaç adet kabul edeceksin?',
        input: 'number',
        inputLabel: `Hazır adet: ${safeMax}`,
        inputValue: safeMax,
        inputAttributes: { min: 1, max: safeMax },
        showCancelButton: true,
        confirmButtonText: 'Görevi Kabul Et',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#059669',
        background: '#f4f5f7',
        inputValidator: (value) => {
            const parsed = parseInt(value, 10);
            if (!parsed || parsed < 1) return 'Lütfen geçerli bir adet giriniz.';
            if (parsed > safeMax) return `En fazla ${safeMax} adet kabul edilebilir.`;
            return null;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        postJson(`/api/panel/task/${no}/start`, { adet: parseInt(result.value, 10) })
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    swalError(data.message);
                    return;
                }
                swalSuccess(data.message || 'Görev kabul edildi.');
                loadGorevler();
            })
            .catch(() => swalError('Görev kabul edilirken hata oluştu.'))
            .finally(hideLoading);
    });
}

function gorevGrubunuKabulEt(groupKey, taskIds, maxAdet) {
    const safeMax = Math.max(1, toInt(maxAdet));
    const ids = Array.isArray(taskIds) ? taskIds.map((id) => toInt(id)).filter((id) => id > 0) : [];

    Swal.fire({
        title: 'Kaç adet kabul edeceksin?',
        input: 'number',
        inputLabel: `Toplam hazır adet: ${safeMax}`,
        inputValue: safeMax,
        inputAttributes: { min: 1, max: safeMax },
        showCancelButton: true,
        confirmButtonText: 'Görevi Kabul Et',
        cancelButtonText: 'Vazgeç',
        confirmButtonColor: '#059669',
        background: '#f4f5f7',
        inputValidator: (value) => {
            const parsed = parseInt(value, 10);
            if (!parsed || parsed < 1) return 'Lütfen geçerli bir adet giriniz.';
            if (parsed > safeMax) return `En fazla ${safeMax} adet kabul edilebilir.`;
            return null;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        postJson('/api/panel/task-group/start', {
            group_key: groupKey,
            task_ids: ids,
            adet: parseInt(result.value, 10)
        })
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    swalError(data.message);
                    return;
                }
                swalSuccess(data.message || 'Görev kabul edildi.');
                loadGorevler();
            })
            .catch(() => swalError('Görev kabul edilirken hata oluştu.'))
            .finally(hideLoading);
    });
}

function goreviBitir(no, maxAdet) {
    Swal.fire({
        title: 'Ürün adedini güncelle',
        input: 'number',
        inputLabel: 'Yeni adet giriniz',
        inputValue: maxAdet,
        inputAttributes: { min: 1, max: maxAdet },
        showCancelButton: true,
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#059669',
        background: '#f4f5f7',
        inputValidator: (value) => {
            const parsed = parseInt(value, 10);
            if (!parsed || parsed < 1) return 'Lütfen geçerli bir adet giriniz.';
            if (parsed > maxAdet) return `En fazla ${maxAdet} adet girilebilir.`;
            return null;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        postJson(`/api/panel/task/${no}/complete`, { adet: parseInt(result.value, 10) })
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    swalError(data.message);
                    return;
                }
                swalSuccess(data.message || 'Üretim kaydedildi.');
                loadGorevler();
            })
            .catch(() => swalError('Üretim kaydı sırasında hata oluştu.'))
            .finally(hideLoading);
    });
}

function goreviSil(no) {
    Swal.fire({
        title: 'Görevi silmek istediğine emin misin?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, sil',
        cancelButtonText: 'Hayır',
        confirmButtonColor: '#dc2626',
        background: '#f4f5f7'
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        postJson(`/api/panel/task/${no}`, {}, 'DELETE')
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    swalError(data.message);
                    return;
                }
                swalSuccess(data.message || 'Görev silindi.');
                loadGorevler();
            })
            .catch(() => swalError('Görev silinirken hata oluştu.'))
            .finally(hideLoading);
    });
}

loadGorevler();
</script>
@endpush
