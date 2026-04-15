@extends('layouts.app')

@section('title', 'Ürün Yönetimi')
@section('subtitle', 'Nihai ürün kaydı, BOM yolu ve görsel bilgisini daha düzenli bir editör deneyimiyle yönet.')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="temizle()">
        <i class="bi bi-eraser me-2"></i>Formu Temizle
    </button>
    <button type="button" class="btn btn-primary" onclick="kaydet()">
        <i class="bi bi-save me-2"></i>Kaydet
    </button>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<style>
    .product-editor-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(360px, 0.95fr);
        gap: 18px;
    }

    .product-list-shell {
        max-height: 620px;
        overflow-y: auto;
    }

    .product-table thead th {
        white-space: nowrap;
    }

    .product-image-panel {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .product-image-preview {
        width: 88px;
        height: 88px;
        border-radius: 18px;
        border: 1px solid var(--z-border);
        object-fit: cover;
        background: rgba(248, 250, 252, 0.92);
        display: none;
    }

    .product-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(15, 23, 42, 0.06);
        color: #1e293b;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .product-chip.success {
        background: rgba(15, 157, 119, 0.12);
        color: var(--z-success);
    }

    .product-row-active td {
        background: rgba(219, 234, 254, 0.95) !important;
    }

    @media (max-width: 1080px) {
        .product-editor-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
    <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
        <article class="metric-card">
            <p class="metric-label">Toplam ürün</p>
            <h3 class="metric-value" id="summaryProductsInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Filtre sonucu</p>
            <h3 class="metric-value" id="summaryFilteredInline">0</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Düzenlenen kayıt</p>
            <h3 class="metric-value" id="summaryEditingInline">Yeni</h3>
        </article>
        <article class="metric-card">
            <p class="metric-label">Son yenileme</p>
            <h3 class="metric-value" id="summaryUpdatedInline">Bekleniyor</h3>
        </article>
    </div>

    <div class="panel-surface">
        <div class="section-header compact">
            <div>
                <p class="section-overline">Editör özeti</p>
                <h3 class="section-title">Aktif form durumu</h3>
            </div>
            <span class="product-chip" id="editorModeBadge">Yeni Kayıt</span>
        </div>

        <div class="info-list">
            <div class="info-row">
                <span>Seçili ürün</span>
                <strong id="editorSelectedMeta">Henüz seçilmedi</strong>
            </div>
            <div class="info-row">
                <span>Liste boyutu</span>
                <strong id="summaryProductsMeta">0</strong>
            </div>
            <div class="info-row">
                <span>Arama sonucu</span>
                <strong id="summaryFilteredMeta">0</strong>
            </div>
            <div class="info-row">
                <span>Görsel durumu</span>
                <strong id="imageStateMeta">Kapalı</strong>
            </div>
        </div>
    </div>

    <div class="product-editor-grid">
        <section class="panel-surface">
            <div class="section-header">
                <div>
                    <p class="section-overline">Ürün editörü</p>
                    <h3 class="section-title">Nihai ürün bilgileri</h3>
                    <p class="section-copy">Kayıt veya düzenleme için ana ürün alanlarını burada yönet.</p>
                </div>
                <span class="product-chip success" id="formStateChip">Hazır</span>
            </div>

            <input type="hidden" id="editNo" value="">

            <div class="stack-list">
                <div>
                    <label class="form-label">Ürün ID (Nihai Ürün Adı)</label>
                    <input type="text" id="urunID" class="form-control" placeholder="or: Koltuk Takımı XL">
                </div>

                <div>
                    <label class="form-label">Sistem Adı</label>
                    <input type="text" id="sistemAdi" class="form-control" placeholder="or: KOLTUK-XL-001">
                </div>

                <div>
                    <label class="form-label">Sistem Kodu</label>
                    <input type="text" id="sistemKodu" class="form-control" placeholder="or: PRD-001">
                </div>

                <div>
                    <label class="form-label">Ara Ürün Yolu (BOM)</label>
                    <textarea id="araAdlarYol" class="form-control" rows="4" placeholder="5-3:8-2:12-1 formatında"></textarea>
                    <p class="muted-copy mb-0">Format: `parcaNo-adet:parcaNo-adet` örneğin `5-3:8-2:12-1`.</p>
                </div>

                <div id="imageUploadArea" style="display:none;">
                    <label class="form-label">Ürün Görseli</label>
                    <div class="product-image-panel">
                        <img id="productImagePreview" src="" alt="Resim Yok" class="product-image-preview">
                        <input type="file" id="productImage" class="form-control" accept="image/*">
                        <button class="btn btn-outline-secondary" type="button" onclick="uploadImage()">
                            <i class="bi bi-upload me-2"></i>Görsel Yükle
                        </button>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <button class="btn btn-outline-secondary" type="button" onclick="temizle()">Temizle</button>
                    <button class="btn btn-primary" type="button" onclick="kaydet()">Kaydet</button>
                </div>
            </div>
        </section>

        <section class="panel-surface table-panel">
            <div class="panel-toolbar">
                <div class="panel-toolbar-copy">
                    <p>Ürün listesi</p>
                    <h3>Mevcut kayıtlar</h3>
                </div>
                <div class="panel-toolbar-meta">
                    <span class="product-chip" id="listCountChip">0 kayıt</span>
                </div>
            </div>

            <div class="panel-surface" style="margin: 18px; padding: 18px;">
                <label class="form-label">Ürün ara</label>
                <input type="text" id="urunArama" class="form-control" placeholder="Ürün ID veya sistem adında ara..." oninput="filterUrunler()">
            </div>

            <div class="table-shell product-list-shell">
                <table class="table-modern table-sm product-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Ürün ID</th>
                            <th>Sistem Adı</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody id="urunlerBody">
                        <tr><td colspan="4" class="text-center text-muted py-4">Yükleniyor...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let allUrunler = [];
let filteredUrunler = [];

function loadUrunler() {
    fetch('/SiparisApi.ashx?action=getProducts', { cache: 'no-store' })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) return;
            allUrunler = data.products || [];
            filteredUrunler = [...allUrunler];
            renderUrunler(filteredUrunler);
            updateProductSummary();
            setProductStamp();
        });
}

function renderUrunler(list) {
    const activeNo = document.getElementById('editNo').value;
    const html = list.map((product) => `
        <tr class="${String(activeNo) === String(product.no) ? 'product-row-active' : ''}">
            <td class="small">${escapeHtml(product.no)}</td>
            <td class="small">${escapeHtml(product.urunId || '-')}</td>
            <td class="small">${escapeHtml(product.sistemAdi || '-')}</td>
            <td>
                <button class="btn btn-outline-secondary btn-sm" onclick="duzenle('${escapeJsString(product.no)}','${escapeJsString(product.urunId || '')}','${escapeJsString(product.sistemAdi || '')}','${escapeJsString(product.sistemKodu || '')}','${escapeJsString(product.araAdlarYol || '')}','${escapeJsString(product.image || '')}')">
                    <i class="bi bi-pencil"></i>
                </button>
            </td>
        </tr>
    `).join('');

    document.getElementById('urunlerBody').innerHTML = html || '<tr><td colspan="4" class="text-center text-muted py-4">Ürün yok.</td></tr>';
    document.getElementById('listCountChip').textContent = `${formatNumber(list.length)} kayıt`;
}

function filterUrunler() {
    const query = document.getElementById('urunArama').value.toLowerCase();
    filteredUrunler = allUrunler.filter((product) =>
        (product.urunId || '').toLowerCase().includes(query) ||
        (product.sistemAdi || '').toLowerCase().includes(query)
    );
    renderUrunler(filteredUrunler);
    updateProductSummary();
}

function duzenle(no, urunId, sistemAdi, sistemKodu, araAdlarYol, imageUrl) {
    document.getElementById('editNo').value = no;
    document.getElementById('urunID').value = urunId;
    document.getElementById('sistemAdi').value = sistemAdi;
    document.getElementById('sistemKodu').value = sistemKodu;
    document.getElementById('araAdlarYol').value = araAdlarYol;
    document.getElementById('imageUploadArea').style.display = 'block';
    document.getElementById('editorModeBadge').textContent = 'Düzenleme';
    document.getElementById('editorSelectedMeta').textContent = urunId || sistemAdi || `Kayıt #${no}`;
    document.getElementById('summaryEditingInline').textContent = `#${no}`;
    document.getElementById('formStateChip').textContent = 'Düzenleniyor';
    document.getElementById('imageStateMeta').textContent = imageUrl ? 'Görsel var' : 'Görsel yok';

    const preview = document.getElementById('productImagePreview');
    if (imageUrl && imageUrl !== 'undefined') {
        preview.src = imageUrl;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
        preview.src = '';
    }

    renderUrunler(filteredUrunler.length ? filteredUrunler : allUrunler);
}

function temizle() {
    document.getElementById('editNo').value = '';
    document.getElementById('urunID').value = '';
    document.getElementById('sistemAdi').value = '';
    document.getElementById('sistemKodu').value = '';
    document.getElementById('araAdlarYol').value = '';
    document.getElementById('imageUploadArea').style.display = 'none';
    document.getElementById('productImage').value = '';
    document.getElementById('productImagePreview').style.display = 'none';
    document.getElementById('productImagePreview').src = '';
    document.getElementById('editorModeBadge').textContent = 'Yeni Kayıt';
    document.getElementById('editorSelectedMeta').textContent = 'Henüz seçilmedi';
    document.getElementById('summaryEditingInline').textContent = 'Yeni';
    document.getElementById('formStateChip').textContent = 'Hazır';
    document.getElementById('imageStateMeta').textContent = 'Kapalı';
    renderUrunler(filteredUrunler.length ? filteredUrunler : allUrunler);
}

function kaydet() {
    const no = document.getElementById('editNo').value;
    const payload = {
        UrunID: document.getElementById('urunID').value,
        SistemAdi: document.getElementById('sistemAdi').value,
        SistemKodu: document.getElementById('sistemKodu').value,
        AraAdlarYol: document.getElementById('araAdlarYol').value
    };

    const url = no ? `/api/database/products/${no}` : '/api/database/products';
    const method = no ? 'PUT' : 'POST';

    fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(payload)
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', 'Kayıt yapılamadı.', 'error');
                return;
            }

            const newlyCreatedId = data.id || no;
            Swal.fire('Kaydedildi', 'Ürün bilgisi başarıyla kaydedildi.', 'success');
            loadUrunler();

            if (!no && newlyCreatedId) {
                document.getElementById('editNo').value = newlyCreatedId;
                document.getElementById('imageUploadArea').style.display = 'block';
                document.getElementById('editorModeBadge').textContent = 'Düzenleme';
                document.getElementById('summaryEditingInline').textContent = `#${newlyCreatedId}`;
                document.getElementById('formStateChip').textContent = 'Kayıt açıldı';
                document.getElementById('imageStateMeta').textContent = 'Yüklemeye hazır';
            }
        })
        .catch((error) => Swal.fire('Hata', `Kayıt yapılamadı: ${error.message}`, 'error'));
}

function uploadImage() {
    const no = document.getElementById('editNo').value;
    if (!no) {
        Swal.fire('Eksik kayıt', 'Önce ürünü kaydedin veya seçin.', 'warning');
        return;
    }

    const fileInput = document.getElementById('productImage');
    if (fileInput.files.length === 0) {
        Swal.fire('Eksik dosya', 'Lütfen bir görsel seçin.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('image', fileInput.files[0]);

    fetch(`/api/database/products/${no}/image`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: formData
    })
        .then((r) => r.json())
        .then((data) => {
            if (!data.success) {
                Swal.fire('Hata', data.message || 'Görsel yüklenemedi.', 'error');
                return;
            }

            Swal.fire('Yüklendi', 'Görsel bilgisi ürüne eklendi.', 'success');
            document.getElementById('productImagePreview').src = data.path;
            document.getElementById('productImagePreview').style.display = 'block';
            document.getElementById('imageStateMeta').textContent = 'Görsel var';
            loadUrunler();
        })
        .catch(() => Swal.fire('Hata', 'Görsel yüklenemedi.', 'error'));
}

function updateProductSummary() {
    document.getElementById('summaryProductsInline').textContent = formatNumber(allUrunler.length);
    document.getElementById('summaryProductsMeta').textContent = formatNumber(allUrunler.length);
    document.getElementById('summaryFilteredInline').textContent = formatNumber(filteredUrunler.length);
    document.getElementById('summaryFilteredMeta').textContent = formatNumber(filteredUrunler.length);
}

function setProductStamp() {
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

function escapeJsString(value) {
    return String(value || '')
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\n/g, ' ');
}

document.addEventListener('DOMContentLoaded', loadUrunler);
</script>
@endpush
