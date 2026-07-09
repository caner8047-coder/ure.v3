@extends('layouts.user')
@section('title', 'Alınabilecek Görevler')

@section('content')
<div id="loadingOverlay" class="legacy-loading-overlay">
    <div>
        <div class="legacy-spinner mx-auto"></div>
        <p class="text-center mt-3 fw-bold text-dark">İşlem yapılıyor, lütfen bekleyin...</p>
    </div>
</div>

<div class="container mt-4 mb-5">
    <h2 class="legacy-page-heading">Alınabilecek Görevler</h2>
    <div class="card-container" id="alinabilirCards">
        <div class="legacy-empty-state">Görevler yükleniyor...</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const alinabilirCards = document.getElementById('alinabilirCards');
const loadingOverlay = document.getElementById('loadingOverlay');

function showLoading() {
    loadingOverlay.classList.add('active');
}

function hideLoading() {
    loadingOverlay.classList.remove('active');
}

function taskDate(task) {
    const date = task.GorevBaslangicTarihi || '-';
    const time = task.GorevBaslangicSaati || '';
    return time && !String(date).includes(time) ? `${date} ${time}` : date;
}

function renderAvailableCard(task) {
    const no = toInt(task.No);
    const amount = toInt(task.Adet || task.ToplamAdet);
    const waiting = toInt(task.ToplamAdet || task.Adet);
    const imageUrl = legacyTaskImage(task.Resim);
    const title = task.AraUrunAdi || task.UrunAdi || '-';
    const subtitle = task.UrunCesidi || (task.UrunAdi ? task.UrunAdi : 'Ara Mamül');

    return `
        <div class="task-card card-true card-available">
            <div class="adet-badge">${amount} Adet</div>
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
            <div class="task-meta bekleyen">
                <i class="bi bi-box-seam me-1"></i>Bekleyen: <strong>${waiting}</strong> ürün
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="goreviAl(${no}, ${Math.max(1, amount)})">Görevi Al</button>
            </div>
        </div>
    `;
}

function loadAlinabilir() {
    alinabilirCards.innerHTML = '<div class="legacy-empty-state">Görevler yükleniyor...</div>';
    fetch('/api/panel/available-tasks')
        .then((r) => r.json())
        .then((data) => {
            const tasks = data.tasks || [];
            if (!tasks.length) {
                alinabilirCards.innerHTML = '<div class="legacy-empty-state">Alınabilecek görev yok.</div>';
                return;
            }
            alinabilirCards.innerHTML = tasks.map(renderAvailableCard).join('');
        })
        .catch(() => {
            alinabilirCards.innerHTML = '<div class="legacy-empty-state text-danger">Alınabilecek görevler yüklenemedi.</div>';
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

function goreviAl(no, maxAdet) {
    Swal.fire({
        title: 'Kaç adet görev alacaksınız?',
        input: 'number',
        inputLabel: `Maksimum: ${maxAdet}`,
        inputValue: maxAdet,
        inputAttributes: { min: 1, max: maxAdet },
        showCancelButton: true,
        confirmButtonText: 'Görevi Al',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#059669',
        background: '#f4f5f7',
        inputValidator: (value) => {
            const parsed = parseInt(value, 10);
            if (!parsed || parsed < 1) return 'Lütfen geçerli bir adet giriniz.';
            if (parsed > maxAdet) return `En fazla ${maxAdet} adet alınabilir.`;
            return null;
        }
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        fetch(`/api/panel/take-task/${no}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ adet: parseInt(result.value, 10) })
        })
            .then((response) => response.json().catch(() => ({})).then((data) => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    swalError(data.message);
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title: data.message || 'Görev alındı.',
                    showConfirmButton: false,
                    timer: 1400,
                    background: '#f4f5f7'
                });
                loadAlinabilir();
            })
            .catch(() => swalError('Görev alınırken hata oluştu.'))
            .finally(hideLoading);
    });
}

loadAlinabilir();
</script>
@endpush
