<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sifremi Unuttum — Zemuretim</title>
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-panel">
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="width: 48px; height: 48px; background: var(--z-warning); border-radius: 10px; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: white; font-weight: 700; font-size: 1rem;">ZT</div>
            <h1 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Sifremi Unuttum</h1>
            <p class="muted-copy" style="margin-top: 4px;">E-posta adresinize sifre sifirlama linki gonderilecek.</p>
        </div>

        @if(session('status'))
            <div class="alert alert-success" style="margin-bottom: 16px;">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div style="margin-bottom: 16px;">
                <label class="form-label">E-posta Adresiniz</label>
                <input type="email" class="form-control" name="email" required autofocus placeholder="ornek@zemmobilya.com">
                @error('email') <div style="color: var(--z-danger); font-size: 0.8rem; margin-top: 4px;">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-warning w-100" style="color: white;">Sifre Sifirlama Linki Gonder</button>
        </form>

        <div style="text-align: center; margin-top: 16px;">
            <a href="{{ route('login') }}" style="color: var(--z-accent); font-size: 0.84rem; font-weight: 500;">Giris sayfasina don</a>
        </div>
    </div>
</div>
</body>
</html>
