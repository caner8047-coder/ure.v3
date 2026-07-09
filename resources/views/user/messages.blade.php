@extends('layouts.user')
@section('title', 'Mesajlar')

@section('content')
<div class="panel-surface">
    <div class="section-header compact">
        <div><h3 class="section-title">Mesajlar</h3></div>
    </div>
    <div class="d-flex gap-2 mb-3">
        <input type="text" class="form-control" id="mesajInput" placeholder="Mesajinizi yazin...">
        <button class="btn btn-primary" id="mesajGonderBtn" onclick="sendMsg()"><i class="bi bi-send me-1"></i>Gonder</button>
    </div>
    <div id="messageNotice" class="alert d-none mb-0" role="status" aria-live="polite"></div>
</div>

<div class="stack-list" id="messageList">
    <p class="text-muted">Yukleniyor...</p>
</div>
@endsection

@push('styles')
<style>
    .message-card {
        border-left: 3px solid var(--z-accent);
        padding: 14px 16px;
    }

    .message-card-unread {
        border-left-color: var(--z-warning);
        background: #fff;
    }

    .message-type-badge {
        background: var(--z-accent-soft);
        border-radius: 4px;
        color: var(--z-accent);
        font-size: 0.7rem;
        font-weight: 700;
        padding: 3px 8px;
    }

    .message-summary-title {
        color: var(--z-text);
        font-size: 0.9rem;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .message-body {
        line-height: 1.55;
        white-space: pre-line;
    }
</style>
@endpush

@push('scripts')
<script>
const messageList = document.getElementById('messageList');
const messageNotice = document.getElementById('messageNotice');
const messageButton = document.getElementById('mesajGonderBtn');

function showMessageNotice(type, message) {
    messageNotice.className = `alert alert-${type} mb-0`;
    messageNotice.textContent = message;
}

function normalizeMessageText(value) {
    const raw = String(value || '').trim();
    if (!raw) return '';

    return raw
        .replace(/^([^,\n]+),\s+(.+?) görevi için sizin üreteceğiniz alt parçayı bekliyor\./, 'Üretim Akışı Bildirimi\nBekleyen görev: $2')
        .replace(/\\. Beklenen parça:/g, '\nBeklenen parça:')
        .replace(/\\s+Eksik adet:/g, '\nEksik adet:')
        .replace(/\\s+Sizden beklenen adet:/g, '\nBeklenen adet:')
        .replace(/\\s+Sizde açık görünen adet:/g, '\nAçık adet:')
        .replace(/\\s+Bekleyen görev:/g, '\nBekleyen görev:')
        .replace(/\\s+Sizin göreviniz:/g, '\nSizin göreviniz:')
        .replace(/\\s+Lütfen üretim durumunu kontrol edin\\./g, '\nAksiyon: Üretim durumunu kontrol edin.')
        .replace(/\\s+Takip kodu:/g, '\nTakip:');
}

function renderMessage(m) {
    const item = document.createElement('div');
    item.className = 'panel-surface message-card';
    if (toInt(m.Okundu) === 0) {
        item.classList.add('message-card-unread');
    }

    const header = document.createElement('div');
    header.className = 'd-flex justify-content-between align-items-center mb-2';

    const senderWrap = document.createElement('div');
    senderWrap.className = 'd-flex align-items-center gap-2';

    const sender = document.createElement('strong');
    sender.style.fontSize = '0.84rem';
    sender.textContent = m.GonderenAdSoyad || [m.GonderenAd, m.GonderenSoyad].filter(Boolean).join(' ') || 'Sistem';
    senderWrap.appendChild(sender);

    const normalizedMessage = normalizeMessageText(m.Mesaj);
    const isWorkflowNotice = normalizedMessage.includes('Takip:') || normalizedMessage.includes('Takip kodu:') || normalizedMessage.includes('Üretim Akışı Bildirimi');
    if (isWorkflowNotice) {
        const typeBadge = document.createElement('span');
        typeBadge.className = 'message-type-badge';
        typeBadge.textContent = 'Üretim bildirimi';
        senderWrap.appendChild(typeBadge);
    }

    const date = document.createElement('span');
    date.className = 'text-muted small';
    date.textContent = [m.Tarih, m.Saat].filter(Boolean).join(' ');

    header.append(senderWrap, date);

    const lines = normalizedMessage.split('\n').map(line => line.trim()).filter(Boolean);
    const hasTitle = lines[0] === 'Üretim Akışı Bildirimi';
    if (hasTitle) {
        const title = document.createElement('div');
        title.className = 'message-summary-title';
        title.textContent = lines.shift();
        item.append(header, title);
    } else {
        item.appendChild(header);
    }

    const message = document.createElement('p');
    message.className = 'muted-copy mb-0 message-body';
    message.style.fontSize = '0.84rem';
    message.textContent = lines.join('\n') || normalizedMessage;

    item.appendChild(message);
    return item;
}

function loadMessages() {
    fetch('/api/panel/messages', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                showMessageNotice('danger', data.message || 'Mesajlar yuklenemedi.');
                return;
            }

            messageList.innerHTML = '';
            const messages = data.messages || [];
            if (!messages.length) {
                const empty = document.createElement('p');
                empty.className = 'text-muted';
                empty.textContent = 'Henuz mesaj yok.';
                messageList.appendChild(empty);
                if (window.markPersonnelMessagesRead) {
                    window.markPersonnelMessagesRead();
                }
                return;
            }

            messages.forEach(m => messageList.appendChild(renderMessage(m)));
            if (window.markPersonnelMessagesRead) {
                window.markPersonnelMessagesRead();
            }
        })
        .catch(() => showMessageNotice('danger', 'Mesajlar yuklenemedi.'));
}
function sendMsg() {
    let msg = document.getElementById('mesajInput').value.trim();
    if (!msg) {
        showMessageNotice('warning', 'Mesaj bos olamaz.');
        return;
    }

    messageButton.disabled = true;
    fetch('/api/panel/messages', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ mesaj: msg })
    })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                showMessageNotice('danger', d.message || 'Mesaj gonderilemedi.');
                return;
            }

            document.getElementById('mesajInput').value = '';
            showMessageNotice('success', d.message || 'Mesaj gonderildi.');
            loadMessages();
        })
        .catch(() => showMessageNotice('danger', 'Mesaj gonderilirken hata olustu.'))
        .finally(() => {
            messageButton.disabled = false;
        });
}
loadMessages();
</script>
@endpush
