@extends('layouts.app')
@section('title', 'Ayarlar - ZemMobilya')

@section('content')
<div class="orders-panel-heading mb-4">
    <div>
        <p>Yonetim</p>
        <h3><i class="bi bi-sliders text-muted me-2"></i>Sistem Ayarları</h3>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="panel-surface mb-3">
            <h6 class="mb-3 fw-bold text-primary">Genel Ayarlar</h6>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Firma Adı</label>
                    <input type="text" class="form-control" value="Zem Mobilya" disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sistem Versiyonu</label>
                    <input type="text" class="form-control" value="V3.0 (Laravel)" disabled>
                </div>
            </div>
        </div>
        <div class="panel-surface mb-3">
            <h6 class="mb-3 fw-bold text-primary">İş Emri Eğitim Modu</h6>
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <label class="form-label mb-1" for="workOrderBomPreviewSwitch">BOM ve stok önizleme popup'ı</label>
                        <div class="small text-muted">İş emri verilmeden önce ürün ağacı, depodaki/görevdeki/boşta stok ve açılacak görevler gösterilir.</div>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="workOrderBomPreviewSwitch" onchange="saveWorkOrderPreviewSetting()">
                    </div>
                </div>
                <div id="workOrderBomPreviewStatus" class="small text-muted mt-2" aria-live="polite">Yükleniyor...</div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="panel-surface mb-3">
            <h6 class="mb-3 fw-bold text-primary"><i class="bi bi-telegram me-1"></i>Telegram Bildirimleri</h6>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <label class="form-label mb-0">Bildirimleri Aktif Et</label>
                        <div class="small text-muted">Görev tamamlamalarında Telegram'a bildirim gönderilir.</div>
                    </div>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="telegramEnabled" onchange="saveTelegramSettings()">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Bot Token</label>
                    <div class="input-group input-group-sm">
                        <input type="password" class="form-control" id="telegramBotToken"
                               placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                               autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" onclick="toggleTokenVisibility()">
                            <i class="bi bi-eye" id="tokenEyeIcon"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        Mevcut: <code id="telegramBotTokenMasked">••••••••</code>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Chat ID</label>
                    <input type="text" class="form-control form-control-sm" id="telegramChatId"
                           placeholder="-1001234567890 veya 123456789">
                    <div class="form-text">Grup: -100 ile başlayan ID. Özel sohbet: Kullanıcı ID.</div>
                </div>

                <hr>

                <h6 class="mb-2 fw-bold text-secondary">Bildirim Türleri</h6>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="notifyTaskStarted" checked onchange="saveTelegramSettings()">
                    <label class="form-check-label">Görev kabul (üretime başlama)</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="notifyTaskCompleted" checked onchange="saveTelegramSettings()">
                    <label class="form-check-label">Görev tamamlama</label>
                </div>
                <div class="form-check mb-2 text-muted">
                    <input class="form-check-input" type="checkbox" disabled>
                    <label class="form-check-label">Sipariş tamamlama <span class="badge bg-secondary ms-1">Yakında</span></label>
                </div>
                <div class="form-check mb-3 text-muted">
                    <input class="form-check-input" type="checkbox" disabled>
                    <label class="form-check-label">Kritik stok uyarısı <span class="badge bg-secondary ms-1">Yakında</span></label>
                </div>

                <div class="d-flex align-items-center gap-2 mb-3">
                    <button class="btn btn-outline-primary btn-sm" onclick="testTelegramConnection()" id="telegramTestBtn">
                        <i class="bi bi-send me-1"></i>Test Gönder
                    </button>
                    <span id="telegramStatus" class="small text-muted"></span>
                </div>

                <button class="btn btn-primary btn-sm w-100" onclick="saveTelegramSettings()" id="telegramSaveBtn">
                    <i class="bi bi-check-lg me-1"></i>Kaydet
                </button>
                <div id="telegramSaveStatus" class="small text-muted mt-2" aria-live="polite"></div>
            </div>
        </div>

        <div class="panel-surface mb-3">
            <h6 class="mb-3 fw-bold text-primary"><i class="bi bi-robot me-1"></i>AI Brain (OpenRouter)</h6>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="aiEnabled" onchange="saveAISettings()">
                    <label class="form-check-label">AI Brain Aktif</label>
                    <div class="small text-muted">Telegram ve web panelinden soru-cevap ozelligi</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">OpenRouter API Key</label>
                    <input type="password" class="form-control form-control-sm" id="aiApiKey" placeholder="sk-or-v1-...">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Model</label>
                    <input type="text" class="form-control form-control-sm" id="aiModel" value="nvidia/nemotron-3-ultra-550b-a55b:free">
                </div>
                <button class="btn btn-primary btn-sm w-100" onclick="saveAISettings()" id="aiSaveBtn">
                    <i class="bi bi-check-lg me-1"></i>Kaydet
                </button>
                <div id="aiSaveStatus" class="small text-muted mt-2" aria-live="polite"></div>
                <div class="mt-2">
                    <a href="{{ route('admin.ai-chat') }}" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-chat-dots me-1"></i>AI Asistan'a Git
                    </a>
                </div>
            </div>
        </div>

        <div class="panel-surface">
            <h6 class="mb-3 fw-bold text-secondary">Bakım İşlemleri</h6>
            <div class="card-body">
                <button class="btn btn-outline-warning btn-sm mb-2 w-100" onclick="clearCache()"><i class="bi bi-trash me-1"></i>Önbellek Temizle</button>
                <button class="btn btn-outline-info btn-sm mb-2 w-100" onclick="alert('Veritabanı yedekleme yakında aktif olacak.')"><i class="bi bi-database-down me-1"></i>Veritabanı Yedekle</button>
                <button id="resetTestDataBtn" class="btn btn-outline-danger btn-sm w-100" onclick="resetTestData()"><i class="bi bi-arrow-counterclockwise me-1"></i>Test Verilerini Sıfırla</button>
                <div id="resetTestDataStatus" class="small text-muted mt-2" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="panel-surface">
            <h6 class="mb-3 fw-bold text-secondary"><i class="bi bi-telegram me-1"></i>Bildirim Geçmişi</h6>
            <div class="card-body">
                <div id="telegramLogsStatus" class="small text-muted mb-2">Yükleniyor...</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="telegramLogsTable" style="display:none;">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Görev No</th>
                                <th>Durum</th>
                                <th>Deneme</th>
                                <th>Hata</th>
                            </tr>
                        </thead>
                        <tbody id="telegramLogsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function toggleTokenVisibility() {
    const input = document.getElementById('telegramBotToken');
    const icon = document.getElementById('tokenEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

async function loadTelegramSettings() {
    try {
        const response = await fetch('/api/admin/telegram/settings', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error('Ayarlar yüklenemedi.');

        const d = data.data;
        document.getElementById('telegramEnabled').checked = d.enabled;
        document.getElementById('telegramBotTokenMasked').textContent = d.bot_token_masked || '••••••••';
        document.getElementById('telegramChatId').value = d.chat_id || '';
        document.getElementById('notifyTaskCompleted').checked = d.notify_task_completed;
        document.getElementById('notifyTaskStarted').checked = d.notify_task_started !== false;
    } catch (e) {
        console.error('Telegram settings load error:', e);
    }
}

async function saveTelegramSettings() {
    const status = document.getElementById('telegramSaveStatus');
    const btn = document.getElementById('telegramSaveBtn');
    btn.disabled = true;
    status.className = 'small text-muted mt-2';
    status.textContent = 'Kaydediliyor...';

    const body = {
        enabled: document.getElementById('telegramEnabled').checked,
        chat_id: document.getElementById('telegramChatId').value.trim(),
        notify_task_completed: document.getElementById('notifyTaskCompleted').checked,
        notify_task_started: document.getElementById('notifyTaskStarted').checked,
    };

    const tokenVal = document.getElementById('telegramBotToken').value.trim();
    if (tokenVal !== '') {
        body.bot_token = tokenVal;
    }

    try {
        const response = await fetch('/api/admin/telegram/settings', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Kaydedilemedi.');

        status.className = 'small text-success mt-2';
        status.textContent = data.message || 'Kaydedildi.';
        document.getElementById('telegramBotToken').value = '';
        loadTelegramSettings();
    } catch (e) {
        status.className = 'small text-danger mt-2';
        status.textContent = e.message || 'Kaydedilemedi.';
    } finally {
        btn.disabled = false;
    }
}

async function testTelegramConnection() {
    const btn = document.getElementById('telegramTestBtn');
    const status = document.getElementById('telegramStatus');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Gönderiliyor...';
    status.className = 'small text-muted';

    try {
        const response = await fetch('/api/admin/telegram/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            status.className = 'small text-danger';
            status.textContent = data.message || 'Test başarısız.';
        } else {
            status.className = 'small text-success';
            status.textContent = data.message || 'Test başarılı.';
        }
    } catch (e) {
        status.className = 'small text-danger';
        status.textContent = 'Bağlantı hatası.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i>Test Gönder';
    }
}

async function loadTelegramLogs() {
    const statusEl = document.getElementById('telegramLogsStatus');
    const tableEl = document.getElementById('telegramLogsTable');
    const bodyEl = document.getElementById('telegramLogsBody');

    try {
        const response = await fetch('/api/admin/telegram/logs', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error('Geçmiş yüklenemedi.');

        const logs = data.data;
        if (!logs || logs.length === 0) {
            statusEl.textContent = 'Henüz bildirim gönderilmemiş.';
            return;
        }

        statusEl.textContent = '';
        tableEl.style.display = '';

        const statusMap = {
            'sent': '<span class="badge bg-success">Gönderildi</span>',
            'pending': '<span class="badge bg-warning text-dark">Bekliyor</span>',
            'sending': '<span class="badge bg-info">Gönderiliyor</span>',
            'failed': '<span class="badge bg-danger">Başarısız</span>',
        };

        bodyEl.innerHTML = logs.map(log => `
            <tr>
                <td class="small">${new Date(log.created_at).toLocaleString('tr-TR')}</td>
                <td>${log.task_no || '-'}</td>
                <td>${statusMap[log.status] || log.status}</td>
                <td>${log.attempts}</td>
                <td class="small text-danger" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${(log.last_error || '').replace(/"/g, '&quot;')}">${log.last_error || '-'}</td>
            </tr>
        `).join('');
    } catch (e) {
        statusEl.textContent = 'Geçmiş yüklenemedi.';
    }
}

function clearCache() {
    if (confirm('Önbelleği temizlemek istediğinize emin misiniz?')) {
        fetch('/api/admin/clear-cache', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            }
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message || 'Önbellek temizlendi!');
        })
        .catch(e => alert('Hata oluştu!'));
    }
}

async function loadWorkOrderPreviewSetting() {
    const checkbox = document.getElementById('workOrderBomPreviewSwitch');
    const status = document.getElementById('workOrderBomPreviewStatus');

    try {
        const response = await fetch('/api/work-order-preview/settings', {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Ayar okunamadı.');
        }

        checkbox.checked = !!data.enabled;
        status.className = 'small text-muted mt-2';
        status.textContent = data.enabled
            ? 'Aktif: iş emri öncesi eğitim popupı gösterilecek.'
            : 'Kapalı: iş emri akışı klasik onayla devam edecek.';
    } catch (error) {
        status.className = 'small text-danger mt-2';
        status.textContent = error.message || 'Ayar yüklenemedi.';
    }
}

async function saveWorkOrderPreviewSetting() {
    const checkbox = document.getElementById('workOrderBomPreviewSwitch');
    const status = document.getElementById('workOrderBomPreviewStatus');
    checkbox.disabled = true;
    status.className = 'small text-muted mt-2';
    status.textContent = 'Kaydediliyor...';

    try {
        const response = await fetch('/api/work-order-preview/settings', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ enabled: checkbox.checked })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Ayar kaydedilemedi.');
        }

        status.className = 'small text-success mt-2';
        status.textContent = data.message || 'Ayar kaydedildi.';
    } catch (error) {
        checkbox.checked = !checkbox.checked;
        status.className = 'small text-danger mt-2';
        status.textContent = error.message || 'Ayar kaydedilemedi.';
    } finally {
        checkbox.disabled = false;
    }
}

async function resetTestData() {
    const message = [
        'Siparişler, görevler, iş emirleri, iş emri merkezi kayıtları ve stok hareketleri silinecek.',
        'Stoktan düşülen sipariş miktarları, üretimle stoğa giren test miktarları ve iş emriyle ayrılan tamponlar geri alınacak; diğer tampon değerlerine dokunulmayacak.',
        'Devam edilsin mi?'
    ].join('\n\n');

    if (!confirm(message)) {
        return;
    }

    const button = document.getElementById('resetTestDataBtn');
    const status = document.getElementById('resetTestDataStatus');
    const originalHtml = button.innerHTML;

    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Sıfırlanıyor';
    status.className = 'small text-muted mt-2';
    status.textContent = 'İşlem çalışıyor...';

    try {
        const response = await fetch('/api/admin/reset-test-data', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ confirmation: 'RESET_TEST_DATA' })
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Sıfırlama tamamlanamadı.');
        }

        const deleted = data.data?.deleted || {};
        const stock = data.data?.stock || {};
        const backupName = data.data?.backup_path ? data.data.backup_path.split('/').pop() : '';
        const summary = [
            data.message,
            `Silinen sipariş: ${deleted.tbSiparisSatir || 0}`,
            `Silinen görev: ${(deleted.tbBolumHavuz || 0) + (deleted.tbPersonelGorev || 0) + (deleted.tbGorevler || 0)}`,
            `Geri eklenen stok: ${stock.order_stock_quantity_restored || 0}`,
            `Geri alınan üretim stoğu: ${stock.production_stock_quantity_removed || 0}`,
            `Geri alınan tampon satırı: ${stock.buffer_rows_restored || 0}`,
            backupName ? `Yedek: ${backupName}` : null
        ].filter(Boolean).join('\n');

        status.className = 'small text-success mt-2';
        status.textContent = backupName ? `Tamamlandı. Yedek: ${backupName}` : 'Tamamlandı.';
        alert(summary);
    } catch (error) {
        status.className = 'small text-danger mt-2';
        status.textContent = error.message || 'Sıfırlama sırasında hata oluştu.';
        alert(status.textContent);
    } finally {
        button.disabled = false;
        button.innerHTML = originalHtml;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadWorkOrderPreviewSetting();
    loadTelegramSettings();
    loadTelegramLogs();
    loadAISettings();
});

// ===== AI Brain Ayarlari =====
async function loadAISettings() {
    try {
        const response = await fetch('/api/admin/telegram/settings', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() }
        });
        const data = await response.json();
        if (data.data) {
            document.getElementById('aiEnabled').checked = data.data.ai_brain_enabled || false;
            document.getElementById('aiModel').value = data.data.openrouter_model || 'nvidia/nemotron-3-ultra-550b-a55b:free';
        }
    } catch(e) { console.error('AI settings load error:', e); }
}

async function saveAISettings() {
    const status = document.getElementById('aiSaveStatus');
    const btn = document.getElementById('aiSaveBtn');
    status.textContent = 'Kaydediliyor...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/admin/telegram/settings', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ai_brain_enabled: document.getElementById('aiEnabled').checked,
                openrouter_api_key: document.getElementById('aiApiKey').value.trim(),
                openrouter_model: document.getElementById('aiModel').value.trim(),
            })
        });
        const data = await response.json();
        status.textContent = data.message || 'Kaydedildi';
        status.className = 'small text-success mt-2';
        document.getElementById('aiApiKey').value = '';
    } catch(e) {
        status.textContent = 'Hata: ' + e.message;
        status.className = 'small text-danger mt-2';
    } finally {
        btn.disabled = false;
    }
}
</script>
@endpush
