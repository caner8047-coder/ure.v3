@extends('layouts.app')
@section('title', 'İletişim')
@section('content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white"><h5 class="mb-0">İletişim</h5></div>
                <div class="card-body">
                    <form method="POST" action="/iletisim">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Adınız</label>
                            <input type="text" class="form-control" name="ad" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mesajınız</label>
                            <textarea class="form-control" name="mesaj" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Gönder</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
