@extends('layouts.app')
@section('title', 'Şifre Değiştir - ZemMobilya')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-primary text-white"><i class="bi bi-key me-1"></i>Şifre Değiştir</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.password.update') }}" id="passwordForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Mevcut Şifre</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" name="new_password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i>Şifreyi Güncelle</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
