@extends('layouts.app')
@section('title', 'Şifremi Unuttum')
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark"><h5 class="mb-0">Şifremi Unuttum</h5></div>
                <div class="card-body">
                    @if(session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif
                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">E-posta Adresiniz</label>
                            <input type="email" class="form-control" name="email" required autofocus placeholder="ornek@zemmobilya.com">
                            @error('email') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Şifre Sıfırlama Linki Gönder</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="{{ route('login') }}">Giriş sayfasına dön</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
