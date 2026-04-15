@extends('layouts.app')
@section('title', 'Iletisim')

@section('content')
<div style="max-width: 520px; margin: 0 auto;">
    <div class="panel-surface">
        <div class="section-header compact">
            <div><h3 class="section-title"><i class="bi bi-chat-dots me-2"></i>Iletisim</h3></div>
        </div>
        <form method="POST" action="/iletisim">
            @csrf
            <div class="stack-list">
                <div>
                    <label class="form-label">Adiniz</label>
                    <input type="text" class="form-control" name="ad" required>
                </div>
                <div>
                    <label class="form-label">E-posta</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div>
                    <label class="form-label">Mesajiniz</label>
                    <textarea class="form-control" name="mesaj" rows="4" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary w-100">Gonder</button>
            </div>
        </form>
    </div>
</div>
@endsection
