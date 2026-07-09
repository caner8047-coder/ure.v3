@extends('layouts.app')

@section('title', 'AI Asistan')

@push('styles')
<style>
    .chat-container {
        background: var(--z-bg-card);
        border: 1px solid var(--z-border);
        border-radius: 12px;
        overflow: hidden;
        max-width: 800px;
        margin: 0 auto;
    }
    .chat-header {
        background: linear-gradient(135deg, #4b3621, #80694d);
        color: #f4e9d8;
        padding: 14px 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chat-header small { opacity: 0.7; font-weight: 400; }
    .chat-messages {
        height: 450px;
        overflow-y: auto;
        padding: 20px;
        background: var(--z-bg-page, #faf8f5);
    }
    .chat-msg {
        margin-bottom: 14px;
        display: flex;
    }
    .chat-msg.user { justify-content: flex-end; }
    .chat-msg.assistant { justify-content: flex-start; }
    .chat-bubble {
        max-width: 75%;
        padding: 10px 15px;
        border-radius: 12px;
        font-size: 0.93rem;
        line-height: 1.5;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .chat-msg.user .chat-bubble {
        background: #d4af37;
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    .chat-msg.assistant .chat-bubble {
        background: var(--z-bg-card, #fff);
        color: var(--z-text-primary, #3b2c1a);
        border: 1px solid var(--z-border, #ede4d4);
        border-bottom-left-radius: 4px;
    }
    .chat-time {
        font-size: 0.7rem;
        color: var(--z-text-secondary, #999);
        margin-top: 3px;
    }
    .chat-msg.user .chat-time { text-align: right; }
    .suggestions {
        padding: 10px 20px;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        border-top: 1px solid var(--z-border, #ede4d4);
    }
    .suggestion-btn {
        background: var(--z-bg-page, #f4e9d8);
        border: 1px solid var(--z-border, #d4af37);
        border-radius: 15px;
        padding: 5px 12px;
        font-size: 0.8rem;
        color: var(--z-text-primary, #4b3621);
        cursor: pointer;
        transition: all 0.15s;
    }
    .suggestion-btn:hover {
        background: #d4af37;
        color: #fff;
        border-color: #d4af37;
    }
    .chat-input {
        display: flex;
        gap: 10px;
        padding: 15px;
        border-top: 1px solid var(--z-border, #ede4d4);
        background: var(--z-bg-card, #fff);
    }
    .chat-input input {
        flex: 1;
        border: 2px solid var(--z-border, #d4af37);
        border-radius: 8px;
        padding: 10px 15px;
        font-size: 0.95rem;
        background: var(--z-bg-card, #fff);
        color: var(--z-text-primary, #3b2c1a);
    }
    .chat-input input:focus {
        outline: none;
        border-color: #b9922c;
    }
    .chat-input button {
        background: #d4af37;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .chat-input button:hover { background: #b9922c; }
    .chat-input button:disabled { opacity: 0.5; cursor: not-allowed; }
    .typing-indicator {
        display: none;
        padding: 10px 15px;
        color: var(--z-text-secondary, #999);
        font-style: italic;
        font-size: 0.85rem;
    }
    .typing-indicator.show { display: block; }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="chat-container">
        <div class="chat-header">
            <div>
                <i class="bi bi-robot me-2"></i> ZemMobilya AI
                <small>Veritabanini tarayarak sorulara cevap verir</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="clearChat()" title="Chati temizle">
                <i class="bi bi-trash"></i>
            </button>
        </div>

        <div class="chat-messages" id="chatMessages">
            <div class="chat-msg assistant">
                <div>
                    <div class="chat-bubble">Merhaba! Ben ZemMobilya AI asistaniyim. Uretim, stok, personel veya gorevler hakkinda sorularinizi sorabilirsiniz.</div>
                    <div class="chat-time">Baslangic</div>
                </div>
            </div>

            @foreach($messages as $msg)
                <div class="chat-msg {{ $msg['role'] }}">
                    <div>
                        <div class="chat-bubble">{{ $msg['text'] }}</div>
                        <div class="chat-time">{{ $msg['time'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="suggestions">
            <span class="suggestion-btn" onclick="sendSuggestion('/devam')">📋 /devam</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/stok')">⚠️ /stok</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/personel')">👥 /personel</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/uretim')">🏭 /uretim</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/verim')">📈 /verim</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/havuz')">🏊 /havuz</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/siparis')">📦 /siparis</span>
            <span class="suggestion-btn" onclick="sendSuggestion('/help')">❓ /help</span>
        </div>

        <div class="typing-indicator" id="typingIndicator">
            <i class="bi bi-three-dots"></i> AI dusunuyor...
        </div>

        <div class="chat-input">
            <input type="text" id="chatInput" placeholder="Sorunuzu yazin..."
                onkeypress="if(event.key==='Enter') sendMessage()" />
            <button type="button" id="sendBtn" onclick="sendMessage()">Gonder</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendBtn = document.getElementById('sendBtn');
const typingIndicator = document.getElementById('typingIndicator');

// Sayfa yuklendiginde en alta kaydir
scrollToBottom();

function sendMessage() {
    const text = chatInput.value.trim();
    if (!text) return;

    // Kullanici mesajini ekle
    appendMessage('user', text);
    chatInput.value = '';
    sendBtn.disabled = true;
    typingIndicator.classList.add('show');
    scrollToBottom();

    // API'ye gonder
    fetch('/api/ai/send', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
        body: JSON.stringify({ message: text }),
    })
    .then(r => r.json())
    .then(data => {
        typingIndicator.classList.remove('show');
        sendBtn.disabled = false;
        if (data.success) {
            appendMessage('assistant', data.reply, data.time);
        } else {
            appendMessage('assistant', 'Hata: ' + (data.message || 'Bilinmeyen hata'));
        }
        scrollToBottom();
    })
    .catch(err => {
        typingIndicator.classList.remove('show');
        sendBtn.disabled = false;
        appendMessage('assistant', 'Baglanti hatasi: ' + err.message);
        scrollToBottom();
    });
}

function sendSuggestion(text) {
    chatInput.value = text;
    sendMessage();
}

function appendMessage(role, text, time) {
    const now = time || new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    const div = document.createElement('div');
    div.className = `chat-msg ${role}`;
    div.innerHTML = `
        <div>
            <div class="chat-bubble">${escapeHtml(text)}</div>
            <div class="chat-time">${now}</div>
        </div>
    `;
    chatMessages.appendChild(div);
}

function clearChat() {
    if (!confirm('Chati temizlemek istediginize emin misiniz?')) return;

    fetch('/api/ai/clear', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
    })
    .then(() => {
        // Tüm mesajlari temizle (ilk hoşgeldin mesaji haric)
        const msgs = chatMessages.querySelectorAll('.chat-msg');
        msgs.forEach((m, i) => { if (i > 0) m.remove(); });
    });
}

function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
</script>
@endpush
