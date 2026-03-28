@extends('layouts.user')
@section('title', 'Mesajlar')
@section('content')
<div class="container mt-3">
    <h4><i class="bi bi-chat-dots"></i> Mesajlar</h4>
    <div class="card mb-3">
        <div class="card-body">
            <div class="input-group">
                <input type="text" class="form-control" id="mesajInput" placeholder="Mesajınızı yazın...">
                <button class="btn btn-primary" onclick="sendMsg()"><i class="bi bi-send"></i> Gönder</button>
            </div>
        </div>
    </div>
    <div id="messageList">Yükleniyor...</div>
</div>
<script>
function loadMessages() {
    fetch('/api/panel/messages').then(r=>r.json()).then(data => {
        if (!data.success) return;
        let html = '';
        (data.messages || []).forEach(m => {
            html += '<div class="card mb-2"><div class="card-body p-2">'
                + '<strong>' + m.GonderenAd + ' ' + m.GonderenSoyad + '</strong>'
                + '<span class="float-end text-muted small">' + (m.Tarih || '') + '</span>'
                + '<p class="mb-0 mt-1">' + m.Mesaj + '</p></div></div>';
        });
        document.getElementById('messageList').innerHTML = html || '<p class="text-muted">Henüz mesaj yok.</p>';
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
@endsection
