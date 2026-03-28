@extends('layouts.user')
@section('title', 'Tamamlanan Görevler - Zem Mobilya')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-check-circle me-2"></i>Tamamlanan Görevler</h4>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr><th>#</th><th>Ürün</th><th>Bölüm</th><th>Adet</th><th>Tamamlanma Tarihi</th></tr>
                </thead>
                <tbody id="completedBody">
                    <tr><td colspan="5" class="text-center py-4 text-muted">Henüz tamamlanan görev yok.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
