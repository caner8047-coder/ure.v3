@extends('layouts.app')

@section('title', 'Admin Ana Sayfa (İş Emri Havuzu)')

@push('styles')
<style>
    /* 🌿 Genel Tema (Eski V1 Temasının Birebir Uyarlaması) */
    body {
        background-color: #f8f6f2;
        font-family: 'Inter', sans-serif;
    }

    h1, h2, h5 {
        color: #4b3621;
        font-weight: 600;
    }

    /* 🔍 Filtreleme Alanı */
    .filter-box {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
        padding: 20px;
        margin-bottom: 25px;
    }

    .filter-box input,
    .filter-box select {
        border-radius: 6px;
        border: 1px solid #ccc;
        padding: 8px 10px;
        width: 100%;
    }

    /* 📋 GridView Modern Stili */
    .EU_DataTable {
        border-collapse: collapse;
        width: 100%;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        font-size: 14px;
    }

    .EU_DataTable th {
        background-color: #4b3621; /* Tek renk koyu kahverengi */
        color: #f8f6f2; /* Açık bej yazı rengi */
        padding: 10px 12px;
        text-align: center;
        font-weight: 600;
        border-bottom: 2px solid #3a2918;
        letter-spacing: 0.3px;
    }

    .EU_DataTable td {
        padding: 8px;
        text-align: center;
        color: #3b2c1a;
        vertical-align: middle;
    }

    .EU_DataTable tr:nth-child(even) {
        background-color: #f8f4ec;
    }

    .EU_DataTable tr:hover {
        background-color: #f0ebe4;
    }

    .EU_DataTable th:hover {
        background-color: #d4af37;
        color: #fff;
    }

    /* 🔘 İşlem Butonları */
    .action-btn {
        width: 32px;
        height: 32px;
        margin: 3px;
        border-radius: 6px;
        background-color: #fff;
        border: 1px solid #ddd;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #4b3621;
        text-decoration: none;
    }

    .action-btn:hover {
        transform: scale(1.12);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        background-color: #f8f4ec;
        color: #d4af37;
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="container-fluid mt-3">

    <!-- 🔹 Filtreleme Kutusu -->
    <div class="filter-box">
        <h2>Görev Filtreleme</h2>
        <h6 class="text-muted mb-3">Filtreleme için metin girin ve Enter’a basın:</h6>

        <input type="text" class="form-control mb-3 w-50" placeholder="Aramak istediğiniz metni girin ve Enter’a basın">

        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <select class="form-select">
                    <option value="detayli" selected>Detaylı Gösterim</option>
                    <option value="detaysiz">Detaysız Gösterim</option>
                    <option value="ozet">Özet Gösterim</option>
                    <option value="tamozet">Tam Özet Gösterim</option>
                    <option value="bolum">Bölüme Göre Gösterim</option>
                    <option value="urunID">ÜrünID Göre Gösterim</option>
                    <option value="araUrun">Ara Ürüne Göre Gösterim</option>
                </select>
            </div>
        </div>
    </div>

    <!-- 🔹 GridView Table -->
    <div class="table-responsive mt-4 pb-5">
        <table class="EU_DataTable table-bordered">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Ara Ürün Adı</th>
                    <th>Veriliş Tarihi</th>
                    <th>Üretilebilir Adet</th>
                    <th>Toplam Adet</th>
                    <th>Açıklama</th>
                    <th>Bölüm Adı</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($havuz as $kayit)
                <tr>
                    <td>{{ $kayit->No }}</td>
                    <td><span class="badge bg-secondary">{{ $kayit->component_name ?: 'Bilinmiyor' }}</span></td>
                    <td>{{ $kayit->GorevBaslangicTarihi ?? '-' }}</td>
                    <td><strong class="text-success">{{ $kayit->Adet }}</strong></td>
                    <td>{{ $kayit->ToplamAdet }}</td>
                    <td>{{ $kayit->Aciklama ?? '' }}</td>
                    <td>{{ $kayit->department_name ?: 'Tanımsız' }}</td>
                    <td>
                        <button class="btn action-btn" title="Düzenle" onclick="confirmUpdate(this)"><i class="bi bi-pencil-square"></i></button>
                        <button class="btn action-btn text-danger" title="Sil" onclick="confirmDelete(this)"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-muted py-4">Gösterilecek iş emri bulunamadı.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Sayfalama (Pagination) -->
        <div class="mt-3 d-flex justify-content-between align-items-center">
            <div>
                <label class="small me-2">Sayfa başına kayıt:</label>
                <select class="form-select form-select-sm d-inline-block w-auto">
                    <option value="10">10</option>
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div>
                {{ $havuz->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function showLoading() {
        let loadingDiv = document.createElement("div");
        loadingDiv.id = "loadingDiv";
        loadingDiv.style.cssText = "position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;";
        loadingDiv.innerHTML = "<div style='color:white;font-size:22px;'><i class='bi bi-hourglass-split me-2'></i> İşlem yapılıyor, lütfen bekleyin...</div>";
        document.body.appendChild(loadingDiv);
    }

    function confirmDelete(btn) {
        event.preventDefault();
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu kaydı kalıcı olarak silmek üzeresiniz!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Evet, sil!',
            cancelButtonText: 'Vazgeç'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading();
                // Simulate Delete HTTP Form Submission here in the future
                setTimeout(() => window.location.reload(), 1000); 
            }
        });
    }

    function confirmUpdate(btn) {
        event.preventDefault();
        Swal.fire({
            title: 'İş Emrini Düzenle',
            text: "Bu satırı güncellemek istiyor musunuz?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Evet, kaydet!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading();
                setTimeout(() => window.location.reload(), 1000); 
            }
        });
    }
</script>
@endpush
