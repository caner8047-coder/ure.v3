@extends('layouts.app')

@section('title', 'Pasif Devam Eden Siparisler')

@section('page-actions')
    <span class="badge" style="background:var(--z-accent-soft);color:var(--z-accent);font-size:0.8rem;padding:6px 12px;" id="toplamBadge">0 kayit</span>
@endsection

@section('content')
    <div class="panel-surface" style="padding:12px 16px;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-info-circle" style="color:var(--z-accent);"></i>
            <span class="small text-muted">Bu sayfada, Excel yuklemesi sirasinda pasife alinan ancak aktif is emirleri devam eden siparisler listelenir. Uretim tamamlandiginda otomatik olarak kapanirlar. Detay icin satira tiklayin.</span>
        </div>
    </div>

    <section class="panel-surface table-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 pt-2">
            
            <!-- Arama -->
            <div class="d-flex align-items-center" style="flex: 1; max-width: 320px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Sip. No, Müşteri veya Ürün Ara..." onkeyup="applyFiltersAndRender()">
                </div>
            </div>

            <!-- Sağ Grup (Filtre + Sıralama) -->
            <div class="d-flex justify-content-end align-items-center flex-wrap gap-4">
                <!-- Filtreler -->
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 500;"><i class="bi bi-filter"></i> İlerleme Durumu:</span>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="smartFilter" id="sfAll" value="all" autocomplete="off" checked onchange="applyFiltersAndRender()">
                        <label class="btn btn-outline-secondary btn-sm px-3" for="sfAll">Tümü</label>

                        <input type="radio" class="btn-check" name="smartFilter" id="sfDone" value="done" autocomplete="off" onchange="applyFiltersAndRender()">
                        <label class="btn btn-outline-secondary btn-sm px-3" for="sfDone">%100</label>

                        <input type="radio" class="btn-check" name="smartFilter" id="sfProgress" value="progress" autocomplete="off" onchange="applyFiltersAndRender()">
                        <label class="btn btn-outline-secondary btn-sm px-3" for="sfProgress">Sürüyor</label>

                        <input type="radio" class="btn-check" name="smartFilter" id="sfZero" value="zero" autocomplete="off" onchange="applyFiltersAndRender()">
                        <label class="btn btn-outline-secondary btn-sm px-3" for="sfZero">Başlamadı</label>
                    </div>
                </div>

                <!-- Sıralama -->
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size: 0.8rem; font-weight: 500;"><i class="bi bi-sort-down"></i> Sırala:</span>
                    <select id="sortMode" class="form-select form-select-sm" style="width: auto; min-width: 160px; font-size: 0.82rem;" onchange="applyFiltersAndRender()">
                        <option value="newest" selected>Tarih (Yeniden Eskiye)</option>
                        <option value="oldest">Tarih (Eskiden Yeniye)</option>
                        <option value="high_progress">İlerleme (Yüksekten)</option>
                        <option value="low_progress">İlerleme (Düşükten)</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-shell">
            <table class="table-modern table-sm">
                <thead>
                    <tr>
                        <th style="width:30px;"></th>
                        <th>Sip. No</th>
                        <th>Musteri</th>
                        <th>Urun Adi</th>
                        <th>Adet</th>
                        <th>Durum</th>
                        <th style="width:140px;">Ilerleme</th>
                        <th>Is Emri Tarihi</th>
                        <th>Islem</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Yukleniyor...</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;

/* ---------- Küçük ilerleme çubuğu (satır içi) ---------- */
function renderMiniBar(row) {
    const yuzde = row.yuzde || 0;
    const color = yuzde >= 100 ? '#16a34a' : yuzde > 50 ? '#3b82f6' : '#eab308';

    return `<div style="display:flex;align-items:center;gap:6px;">
        <div style="flex:1;height:6px;background:var(--z-border-light);border-radius:3px;overflow:hidden;">
            <div style="height:100%;width:${yuzde}%;background:${color};border-radius:3px;"></div>
        </div>
        <span style="font-size:0.75rem;font-weight:700;color:${color};min-width:32px;">%${yuzde}</span>
    </div>`;
}

/* ---------- Geniş detay paneli (expand) ---------- */
function renderDetailPanel(row) {
    const steps = row.pipelineSteps || [];
    const yuzde = row.yuzde || 0;
    const gecenSure = row.gecenSure || '';

    if (!steps.length) {
        return `<div style="padding:16px 20px;">
            <div style="text-align:center;color:var(--z-text-secondary);padding:20px;">
                <i class="bi bi-info-circle" style="font-size:1.5rem;"></i>
                <div style="margin-top:8px;">Pipeline bilgisi bulunamadi</div>
            </div>
        </div>`;
    }

    /* Sadece aktif adımları göster (başlanmamış olanları gizle) */
    const activeSteps = steps.filter(s => s.status !== 'baslanmadi');
    const showSteps = activeSteps.length > 0 ? activeSteps : steps;

    let html = '<div style="padding:16px 24px;">';

    /* Üst bilgi satırı */
    html += `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="font-size:1.5rem;font-weight:800;color:${yuzde >= 100 ? '#16a34a' : '#3b82f6'};">%${yuzde}</span>
            <div>
                <div style="font-weight:600;font-size:0.85rem;">Genel Ilerleme</div>
                <div style="font-size:0.75rem;color:var(--z-text-secondary);">${row.bitenGorevSayisi || 0} tamamlanan / ${(row.aktifGorevSayisi || 0) + (row.bitenGorevSayisi || 0)} toplam adim</div>
            </div>
        </div>
        ${gecenSure ? '<div style="font-size:0.8rem;color:var(--z-text-secondary);"><i class="bi bi-clock"></i> ' + gecenSure + ' gecmis</div>' : ''}
    </div>`;

    if (row.allocationNote) {
        html += `<div style="margin:-4px 0 14px 0;font-size:0.74rem;color:var(--z-text-secondary);display:flex;align-items:flex-start;gap:6px;">
            <i class="bi bi-diagram-3" style="margin-top:2px;"></i>
            <span>${row.allocationNote}</span>
        </div>`;
    }

    /* Geniş progress bar */
    const barColor = yuzde >= 100 ? '#16a34a' : yuzde > 50 ? '#3b82f6' : '#eab308';
    html += `<div style="height:10px;background:var(--z-border-light);border-radius:5px;overflow:hidden;margin-bottom:20px;">
        <div style="height:100%;width:${yuzde}%;background:${barColor};border-radius:5px;transition:width 0.5s;"></div>
    </div>`;

    /* Adım adim pipeline */
    html += '<div style="display:flex;align-items:flex-start;gap:0;overflow-x:auto;padding-bottom:4px;">';

    for (let i = 0; i < showSteps.length; i++) {
        const step = showSteps[i];
        const isLast = i === showSteps.length - 1;

        let iconBg, iconColor, icon, borderColor, textColor, cardBg;

        switch (step.status) {
            case 'tamamlandi':
                iconBg = '#16a34a'; iconColor = '#fff';
                icon = '<i class="bi bi-check-lg"></i>';
                borderColor = '#16a34a'; textColor = '#16a34a';
                cardBg = 'rgba(34,197,94,0.06)';
                break;
            case 'uretimde':
                iconBg = '#eab308'; iconColor = '#fff';
                icon = '<i class="bi bi-gear-fill" style="animation:spin 2s linear infinite;"></i>';
                borderColor = '#eab308'; textColor = '#ca8a04';
                cardBg = 'rgba(234,179,8,0.08)';
                break;
            case 'bekliyor':
                iconBg = '#3b82f6'; iconColor = '#fff';
                icon = '<i class="bi bi-hourglass-split"></i>';
                borderColor = '#3b82f6'; textColor = '#2563eb';
                cardBg = 'rgba(59,130,246,0.06)';
                break;
            default:
                iconBg = '#94a3b8'; iconColor = '#fff';
                icon = '<i class="bi bi-circle"></i>';
                borderColor = '#cbd5e1'; textColor = '#94a3b8';
                cardBg = 'rgba(148,163,184,0.04)';
        }

        const isPulse = step.status === 'uretimde' ? 'animation:pulse-shadow 2s ease-in-out infinite;' : '';

        /* Daire + Bağlantı çizgisi */
        html += `<div style="display:flex;flex-direction:column;align-items:center;min-width:100px;flex:1;position:relative;">`;
        html += `<div style="width:40px;height:40px;border-radius:50%;background:${iconBg};color:${iconColor};display:flex;align-items:center;justify-content:center;font-size:1.1rem;box-shadow:0 2px 8px ${iconBg}44;${isPulse}z-index:2;position:relative;">${icon}</div>`;

        /* Bağlantı çizgisi (sonraki adıma) */
        if (!isLast) {
            const lineColor = step.status === 'tamamlandi' ? '#16a34a' : 'var(--z-border-light)';
            html += `<div style="position:absolute;top:20px;left:calc(50% + 20px);right:calc(-50% + 20px);height:3px;background:${lineColor};z-index:1;"></div>`;
        }

        /* Tooltip içeriği oluştur */
        const hasTooltip = step.tooltipLines && step.tooltipLines.length > 0;
        const tooltipId = 'tip-' + Math.random().toString(36).substr(2, 9);
        let tooltipHtml = '';
        if (hasTooltip) {
            tooltipHtml = `<div id="${tooltipId}" class="pipeline-tooltip" style="display:none;position:absolute;bottom:calc(100% + 12px);left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:10px 14px;border-radius:8px;font-size:0.72rem;line-height:1.6;white-space:nowrap;z-index:100;box-shadow:0 4px 16px rgba(0,0,0,0.25);min-width:180px;">
                <div style="font-weight:700;margin-bottom:6px;font-size:0.78rem;border-bottom:1px solid rgba(255,255,255,0.15);padding-bottom:4px;">
                    ${step.status === 'tamamlandi' ? '✅' : step.status === 'uretimde' ? '⚙️' : step.status === 'bekliyor' ? '⏳' : '•'} ${step.bolumAdi}
                </div>
                ${step.tooltipLines.map(l => '<div style="padding:2px 0;">• ' + l + '</div>').join('')}
                <div style="position:absolute;bottom:-6px;left:50%;transform:translateX(-50%);width:0;height:0;border-left:6px solid transparent;border-right:6px solid transparent;border-top:6px solid #1e293b;"></div>
            </div>`;
        }

        /* Bilgi kartı */
        html += `<div style="margin-top:10px;padding:10px 8px;background:${cardBg};border:1px solid ${borderColor}33;border-radius:8px;text-align:center;width:100%;position:relative;"
            ${hasTooltip ? 'onmouseenter="showTooltip(\'' + tooltipId + '\')" onmouseleave="hideTooltip(\'' + tooltipId + '\')"' : ''}>
            <div style="font-size:0.8rem;font-weight:700;color:${textColor};margin-bottom:4px;">${step.bolumAdi}</div>
            <div style="font-size:0.7rem;color:${textColor};font-weight:600;">${step.statusLabel}</div>
            ${step.detay ? '<div style="font-size:0.65rem;color:var(--z-text-secondary);margin-top:4px;' + (hasTooltip ? 'border-bottom:1px dashed ' + textColor + '44;padding-bottom:2px;cursor:help;' : '') + '">' + step.detay + (hasTooltip ? ' <i class="bi bi-info-circle" style="font-size:0.6rem;"></i>' : '') + '</div>' : ''}
            ${step.personelAd ? '<div style="font-size:0.7rem;color:' + textColor + ';margin-top:4px;font-weight:600;"><i class="bi bi-person-fill"></i> ' + step.personelAd + '</div>' : ''}
            ${tooltipHtml}
        </div>`;

        html += '</div>';
    }

    html += '</div>';

    /* Alt özet */
    const tamamlanan = steps.filter(s => s.status === 'tamamlandi').length;
    const uretimde = steps.filter(s => s.status === 'uretimde').length;
    const bekleyen = steps.filter(s => s.status === 'bekliyor').length;

    html += `<div style="display:flex;gap:16px;justify-content:center;margin-top:16px;padding-top:12px;border-top:1px solid var(--z-border-light);flex-wrap:wrap;">`;
    if (tamamlanan > 0) html += `<div style="display:flex;align-items:center;gap:4px;font-size:0.8rem;"><div style="width:10px;height:10px;border-radius:50%;background:#16a34a;"></div><span style="font-weight:600;color:#16a34a;">${tamamlanan} Tamamlandı</span></div>`;
    if (uretimde > 0) html += `<div style="display:flex;align-items:center;gap:4px;font-size:0.8rem;"><div style="width:10px;height:10px;border-radius:50%;background:#eab308;animation:pulse-shadow 2s ease-in-out infinite;"></div><span style="font-weight:600;color:#ca8a04;">${uretimde} Uretimde</span></div>`;
    if (bekleyen > 0) html += `<div style="display:flex;align-items:center;gap:4px;font-size:0.8rem;"><div style="width:10px;height:10px;border-radius:50%;background:#3b82f6;"></div><span style="font-weight:600;color:#2563eb;">${bekleyen} Bekliyor</span></div>`;
    html += '</div>';

    html += '</div>';
    return html;
}

/* ---------- Tablo yükleme ---------- */
let allOrders = [];

function loadData() {
    fetch('/SiparisApi.ashx?action=getPasifDevamEden')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('tableBody').innerHTML =
                    `<tr><td colspan="9" class="text-center py-3 text-danger">${data.message || 'Hata'}</td></tr>`;
                return;
            }

            allOrders = data.items || data.data || [];
            document.getElementById('toplamBadge').textContent = allOrders.length + ' kayit';
            applyFiltersAndRender();
        })
        .catch(() => {
            document.getElementById('tableBody').innerHTML =
                '<tr><td colspan="9" class="text-center py-3 text-danger">Veri yuklenemedi.</td></tr>';
        });
}

function applyFiltersAndRender() {
    const searchVal = (document.getElementById('searchInput').value || '').toLowerCase();
    const smartFilterNode = document.querySelector('input[name="smartFilter"]:checked');
    const smartFilter = smartFilterNode ? smartFilterNode.value : 'all';
    const sortMode = document.getElementById('sortMode').value;

    let displayRows = [...allOrders];

    /* Arama */
    if (searchVal) {
        displayRows = displayRows.filter(row => {
            return (row.siparisNo && String(row.siparisNo).toLowerCase().includes(searchVal)) ||
                   (row.musteri && String(row.musteri).toLowerCase().includes(searchVal)) ||
                   (row.urunAdi && String(row.urunAdi).toLowerCase().includes(searchVal));
        });
    }

    /* Filtreleme */
    if (smartFilter !== 'all') {
        displayRows = displayRows.filter(row => {
            const yuzde = parseFloat(row.yuzde) || 0;
            if (smartFilter === 'done') return yuzde >= 100;
            if (smartFilter === 'progress') return yuzde > 0 && yuzde < 100;
            if (smartFilter === 'zero') return yuzde === 0;
            return true;
        });
    }

    /* Sıralama */
    displayRows.sort((a, b) => {
        if (sortMode === 'newest' || sortMode === 'oldest') {
            const parseDate = (str) => {
                if (!str) return 0;
                // Örnek format: "17.04.2026 00:33"
                const parts = str.trim().split(' ');
                if (parts.length < 2) return 0;
                const dParts = parts[0].split('.');
                const tParts = parts[1].split(':');
                if (dParts.length !== 3 || tParts.length !== 2) return 0;
                return new Date(dParts[2], dParts[1] - 1, dParts[0], tParts[0], tParts[1]).getTime();
            };
            const dA = parseDate(a.isEmriTarihi);
            const dB = parseDate(b.isEmriTarihi);
            return sortMode === 'newest' ? dB - dA : dA - dB;
        } else if (sortMode === 'high_progress' || sortMode === 'low_progress') {
            const pA = parseFloat(a.yuzde) || 0;
            const pB = parseFloat(b.yuzde) || 0;
            return sortMode === 'high_progress' ? pB - pA : pA - pB;
        }
        return 0;
    });

    if (!displayRows.length) {
        document.getElementById('tableBody').innerHTML =
            '<tr><td colspan="9" class="text-center py-3 text-muted">Filtreye uygun pasif devam eden siparis yok.</td></tr>';
        return;
    }

    let html = '';
    displayRows.forEach((row, idx) => {
        let durumBadge = '';
        if (row.durum === 'PasifDevamEden') {
            durumBadge = '<span class="badge" style="background:var(--z-warning-soft);color:var(--z-warning);">Pasif Devam Eden</span>';
        } else {
            durumBadge = `<span class="badge" style="background:var(--z-bg-soft);color:var(--z-text-secondary);">${row.durum || '-'}</span>`;
        }

        /* Ana satır */
        html += `<tr class="clickable-row" data-idx="${idx}" onclick="toggleDetail(${idx})" style="cursor:pointer;transition:background 0.15s;">
            <td style="text-align:center;padding:8px 4px;">
                <i class="bi bi-chevron-right detail-arrow" id="arrow-${idx}" style="transition:transform 0.2s;color:var(--z-text-secondary);font-size:0.8rem;"></i>
            </td>
            <td>${row.siparisNo || '-'}</td>
            <td>${row.musteri || '-'}</td>
            <td>${row.urunAdi || '-'}</td>
            <td>${row.adet || '-'}</td>
            <td>${durumBadge}</td>
            <td>${renderMiniBar(row)}</td>
            <td><small>${row.isEmriTarihi || '-'}</small></td>
            <td>
                <button class="btn btn-sm btn-outline-success" title="Yeniden Aktif Et"
                    onclick="event.stopPropagation(); reactivate(${row.no})">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </td>
        </tr>`;

        /* Detay satırı (başlangıçta gizli) */
        html += `<tr class="detail-row" id="detail-${idx}" style="display:none;">
            <td colspan="9" style="padding:0;border-top:none;background:var(--z-bg-subtle,rgba(0,0,0,0.02));">
                ${renderDetailPanel(row)}
            </td>
        </tr>`;
    });

    document.getElementById('tableBody').innerHTML = html;
}

/* ---------- Satır genişlet/daralt ---------- */
let openIdx = null;

function toggleDetail(idx) {
    const detail = document.getElementById('detail-' + idx);
    const arrow = document.getElementById('arrow-' + idx);

    if (!detail) return;

    /* Açık olan başka bir satır varsa kapat */
    if (openIdx !== null && openIdx !== idx) {
        const prevDetail = document.getElementById('detail-' + openIdx);
        const prevArrow = document.getElementById('arrow-' + openIdx);
        if (prevDetail) prevDetail.style.display = 'none';
        if (prevArrow) { prevArrow.style.transform = 'rotate(0deg)'; }
    }

    if (detail.style.display === 'none') {
        detail.style.display = 'table-row';
        arrow.style.transform = 'rotate(90deg)';
        openIdx = idx;
    } else {
        detail.style.display = 'none';
        arrow.style.transform = 'rotate(0deg)';
        openIdx = null;
    }
}

/* ---------- Reactivate ---------- */
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

/* ---------- Tooltip göster/gizle ---------- */
function showTooltip(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'block';
}
function hideTooltip(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
}

loadData();
</script>

<style>
/* Satır üzerine gelince hafif vurgu */
.clickable-row:hover {
    background: var(--z-accent-soft, rgba(59,130,246,0.06)) !important;
}

/* Detay satırı animasyonu */
.detail-row {
    transition: opacity 0.2s ease;
}

/* Dikey pipeline çizgisi */
.pipeline-step {
    position: relative;
}

/* Gear spin animasyonu */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Üretimde olan adım için nabız efekti */
@keyframes pulse-shadow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(234,179,8,0.4); }
    50% { box-shadow: 0 0 0 6px rgba(234,179,8,0.1); }
}

/* Ok dönüşü */
.detail-arrow {
    display: inline-block;
}
</style>
@endpush
