@extends('layouts.user')
@section('title', 'Sifre Degistir')

@section('content')
<div style="max-width: 440px;">
    <div class="panel-surface">
        <div class="section-header compact">
            <div><h3 class="section-title"><i class="bi bi-shield-lock me-2"></i>Sifre Degistir</h3></div>
        </div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
        @endif
        <form method="POST" action="{{ route('user.password.update') }}">
            @csrf
            <div class="stack-list">
                <div>
                    <label class="form-label">Mevcut Sifre</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Yeni Sifre</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div>
                    <label class="form-label">Yeni Sifre (Tekrar)</label>
                    <input type="password" name="new_password_confirmation" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-circle me-1"></i>Sifreyi Guncelle</button>
            </div>
        </form>
    </div>
</div>
@endsection
