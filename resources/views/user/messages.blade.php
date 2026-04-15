@extends('layouts.user')
@section('title', 'Mesajlar')

@section('content')
<div class="panel-surface">
    <div class="section-header compact">
        <div><h3 class="section-title">Mesajlar</h3></div>
    </div>
    <div class="d-flex gap-2 mb-3">
        <input type="text" class="form-control" id="mesajInput" placeholder="Mesajinizi yazin...">
        <button class="btn btn-primary" onclick="sendMsg()"><i class="bi bi-send me-1"></i>Gonder</button>
    </div>
</div>

<div class="stack-list" id="messageList">
    <p class="text-muted">Yukleniyor...</p>
</div>
@endsection

@push('scripts')
<script>
function loadMessages() {
    fetch('/api/panel/messages').then(r=>r.json()).then(data => {
        if (!data.success) return;
        let html = '';
        (data.messages || []).forEach(m => {
            html += '<div class="panel-surface" style="padding: 14px;">'
                + '<div class="d-flex justify-content-between align-items-center mb-2">'
                + '<strong style="font-size: 0.84rem;">' + m.GonderenAd + ' ' + m.GonderenSoyad + '</strong>'
                + '<span class="text-muted small">' + (m.Tarih || '') + '</span>'
                + '</div>'
                + '<p class="muted-copy mb-0" style="font-size: 0.84rem;">' + m.Mesaj + '</p></div>';
        });
        document.getElementById('messageList').innerHTML = html || '<p class="text-muted">Henuz mesaj yok.</p>';
    });
}
function sendMsg() {
    let msg = document.getElementById('mesajInput').value.trim();
    if (!msg) return;
    fetch('/api/panel/messages', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify({mesaj:msg})})
        .then(r=>r.json()).then(d => { document.getElementById('mesajInput').value=''; loadMessages(); });
}
loadMessages();
</script>
@endpush
