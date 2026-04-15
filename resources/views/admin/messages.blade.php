@extends('layouts.app')

@section('title', 'Sistem Mesajları ve Bildirimler')

@section('page-actions')
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadMessages(1)">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Yenile
    </button>
@endsection

@push('styles')
<style>
    .message-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border-radius: 12px;
        border: 1px solid var(--z-border);
        background: #fff;
    }
    .message-card:hover {
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .message-sender {
        font-weight: 600;
        color: var(--z-text-main);
    }
    .message-date {
        font-size: 0.8rem;
        color: var(--z-text-secondary);
    }
    .message-dept {
        font-size: 0.75rem;
        background: var(--z-bg-soft);
        padding: 3px 8px;
        border-radius: 12px;
        color: var(--z-text-main);
        font-weight: 500;
    }
    .message-body {
        margin-top: 10px;
        color: var(--z-text-muted);
        line-height: 1.5;
        font-size: 0.95rem;
    }
    .btn-delete-msg {
        color: var(--z-danger);
        cursor: pointer;
        padding: 5px;
        border-radius: 6px;
    }
    .btn-delete-msg:hover {
        background: var(--z-danger-soft);
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.min.css" rel="stylesheet">
@endpush

@section('content')
    <section class="panel-surface mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="Mesaj veya personel adı ile ara..." onkeyup="handleSearch(event)">
                    <button class="btn btn-primary" onclick="loadMessages(1)">Ara</button>
                </div>
            </div>
            <div class="col-md-6 text-md-end text-muted small" id="pageInfo">
                Yükleniyor...
            </div>
        </div>
    </section>

    <div id="messagesContainer" class="row g-4">
        <div class="col-12 text-center py-5 text-muted">Mesajlar yükleniyor...</div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="changePage(-1)"><i class="bi bi-chevron-left me-1"></i>Önceki</button>
            <button class="btn btn-outline-secondary" id="btnNext" onclick="changePage(1)">Sonraki<i class="bi bi-chevron-right ms-1"></i></button>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.5/dist/sweetalert2.all.min.js"></script>
<script>
let currentPage = 1;
let currentLastPage = 1;

function loadMessages(page = 1) {
    currentPage = page;
    const search = document.getElementById('searchInput').value;
    let url = `/api/admin/messages?page=${page}&per_page=12`;
    
    if (search) {
        url += `&search=${encodeURIComponent(search)}`;
    }

    document.getElementById('messagesContainer').innerHTML = '<div class="col-12 text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const rows = data.data || [];
            currentLastPage = Number(data.last_page || 1);

            if (!rows.length) {
                document.getElementById('messagesContainer').innerHTML = '<div class="col-12 text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3"></i>Görüntülenecek mesaj bulunamadı.</div>';
                document.getElementById('pageInfo').textContent = 'Kayıt yok';
                document.getElementById('btnPrev').disabled = true;
                document.getElementById('btnNext').disabled = true;
                return;
            }

            document.getElementById('messagesContainer').innerHTML = rows.map(row => {
                const dateParts = (row.Tarih || '').split(' ');
                const dateStr = dateParts[0] ? dateParts[0] : row.Tarih;
                
                return `
                    <div class="col-xl-4 col-md-6">
                        <div class="message-card p-4 h-100 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm" style="width:40px; height:40px; font-size:1.1rem;">
                                        ${row.Gonderen.charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <div class="message-sender">${escapeHtml(row.Gonderen)}</div>
                                        <div class="message-date"><i class="bi bi-clock me-1"></i>${dateStr} ${row.Saat || ''}</div>
                                    </div>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-link text-muted p-0 border-0" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteMessage(${row.MesajNo}); return false;"><i class="bi bi-trash me-2"></i>Sil</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mb-3">
                                <span class="message-dept"><i class="bi bi-tag-fill me-1 text-primary"></i>${escapeHtml(row.BolumAdi)}</span>
                            </div>
                            <div class="message-body flex-grow-1">
                                ${escapeHtml(row.Mesaj)}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('pageInfo').textContent = `Sayfa ${data.current_page} / ${data.last_page} (Toplam ${data.total} mesaj)`;
            document.getElementById('btnPrev').disabled = data.current_page <= 1;
            document.getElementById('btnNext').disabled = data.current_page >= data.last_page;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('messagesContainer').innerHTML = '<div class="col-12 text-center py-5 text-danger">Mesajlar yüklenirken bir hata oluştu.</div>';
        });
}

function handleSearch(e) {
    if (e.key === 'Enter') {
        loadMessages(1);
    }
}

function changePage(delta) {
    const nextPage = currentPage + delta;
    if (nextPage >= 1 && nextPage <= currentLastPage) {
        loadMessages(nextPage);
    }
}

function deleteMessage(id) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: 'Bu mesajı kalıcı olarak silmek istiyor musunuz?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, Sil!',
        cancelButtonText: 'İptal',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/api/admin/messages/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: data.message,
                        showConfirmButton: false,
                        timer: 2500
                    });
                    loadMessages(currentPage);
                } else {
                    Swal.fire('Hata!', data.message || 'Silinemedi.', 'error');
                }
            })
            .catch(err => {
                Swal.fire('Hata!', 'Sunucu hatası oluştu.', 'error');
            });
        }
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', () => loadMessages(1));
</script>
@endpush
