<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zem Uretim — Giris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/minimal-ui.css') }}?v={{ file_exists(public_path('css/minimal-ui.css')) ? filemtime(public_path('css/minimal-ui.css')) : time() }}" rel="stylesheet">
</head>
<body>
    <div class="auth-wrapper flex-column">
        <h1 style="font-family: 'Inter', sans-serif; font-weight: 800; font-size: 2.8rem; letter-spacing: -0.05em; color: #0f172a; margin-bottom: 2rem;">zolfa</h1>
        <div class="auth-panel">
            <div class="text-center mb-4">
                <h2 style="font-size: 1.25rem; font-weight: 700; color: #1e293b;">Giriş Yap</h2>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger" style="font-size: 0.82rem;">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">E-Posta</label>
                    <input type="email" class="form-control" id="email" name="email"
                        value="{{ old('email', request()->cookie('userid', '')) }}"
                        required autofocus placeholder="ornek@zemmobilya.com">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Sifre</label>
                    <input type="password" class="form-control" id="password" name="password"
                        required placeholder="Sifrenizi girin">
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember" {{ request()->cookie('userid') ? 'checked' : '' }}>
                        <label class="form-check-label text-muted" for="remember" style="font-size: 0.8rem;">Beni hatirla</label>
                    </div>
                    <a href="{{ route('password.request') }}" style="font-size: 0.8rem; font-weight: 600; color: var(--z-accent);">Sifremi unuttum</a>
                </div>

                <button type="submit" class="btn btn-primary w-100" style="padding: 10px;">
                    Giris Yap <i class="bi bi-arrow-right-short ms-1"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
