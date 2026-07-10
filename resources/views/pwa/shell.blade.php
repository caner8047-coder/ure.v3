<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ZemuMobil — Personel Paneli</title>
  
  <!-- CSS assets -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="/css/pwa-custom.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
</head>
<body>

  <!-- Offline Warning Banner -->
  <div id="offline-banner" class="offline-banner">
    <i class="bi bi-wifi-off me-1"></i> İnternet Bağlantısı Yok — Çevrimdışı Çalışıyorsunuz
  </div>

  <!-- ==================== LOGIN PAGE ==================== -->
  <div id="page-login" class="container d-flex flex-column align-items-center justify-content-center min-vh-100 p-4">
    <div class="text-center mb-4">
      <i class="bi bi-cpu" style="font-size: 3rem; color: var(--pwa-accent);"></i>
      <h2 class="fw-bold mt-2" style="font-size: 1.5rem; letter-spacing: -0.5px;">ZemuRetim</h2>
      <p style="color: var(--pwa-text-muted); font-size: 0.85rem;">Mobil Personel PWA Girişi</p>
    </div>

    <div class="w-100" style="max-width: 320px;">
      <form id="login-form" onsubmit="handleLogin(event)">
        <div class="mb-3">
          <label class="form-label small text-muted">E-Posta Adresi</label>
          <input type="email" id="login-email" class="pwa-input" placeholder="personel@zemuretim.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted">Şifre</label>
          <input type="password" id="login-password" class="pwa-input" placeholder="••••••••" required>
        </div>
        <button type="submit" class="pwa-btn mt-2">Giriş Yap</button>
      </form>
    </div>
  </div>

  <!-- ==================== MAIN APP CONTEXT (HIDDEN BY DEFAULT) ==================== -->
  <div id="app-shell" style="display: none;">
    <!-- Top Sticky Header -->
    <div class="pwa-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-cpu text-info" style="font-size: 1.4rem;"></i>
        <h1 class="h6 mb-0 fw-bold" id="user-display-name">Personel Paneli</h1>
      </div>
      <div>
        <button class="btn btn-sm btn-outline-danger" onclick="handleLogout()">
          <i class="bi bi-box-arrow-right"></i>
        </button>
      </div>
    </div>

    <!-- ==================== 1. DASHBOARD PAGE ==================== -->
    <div id="page-dashboard" class="container p-3 container-page">
      <div class="row g-3 mb-4">
        <div class="col-6">
          <div class="pwa-card text-center mb-0" onclick="switchPage('tasks')">
            <i class="bi bi-check2-circle text-info d-block mb-1" style="font-size: 1.8rem;"></i>
            <span class="d-block small text-muted">Aktif Görevlerim</span>
            <strong id="dash-active-tasks-count" style="font-size: 1.25rem;">0</strong>
          </div>
        </div>
        <div class="col-6">
          <div class="pwa-card text-center mb-0" onclick="switchPage('alerts')">
            <i class="bi bi-exclamation-triangle text-warning d-block mb-1" style="font-size: 1.8rem;"></i>
            <span class="d-block small text-muted">Kritik Stoklar</span>
            <strong id="dash-critical-stocks-count" style="font-size: 1.25rem;">0</strong>
          </div>
        </div>
      </div>

      <h5 class="fw-bold mb-3" style="font-size: 0.95rem;">Son Sistem Bildirimleri</h5>
      <div id="notifications-list" class="d-flex flex-column gap-2">
        <p class="text-center text-muted small py-4">Bildirim bulunmuyor.</p>
      </div>
    </div>

    <!-- ==================== 2. TASKS LIST PAGE ==================== -->
    <div id="page-tasks" class="container p-3 container-page" style="display: none;">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0" style="font-size: 0.95rem;">Aktif Üretim Görevlerim</h5>
        <span class="badge bg-secondary-subtle text-muted" id="tasks-count-badge">0 Görev</span>
      </div>

      <div id="tasks-list" class="d-flex flex-column gap-3">
        <p class="text-center text-muted small py-5">Atanmış üretim göreviniz bulunmuyor.</p>
      </div>
    </div>

    <!-- ==================== 3. STOCK ALERTS PAGE ==================== -->
    <div id="page-alerts" class="container p-3 container-page" style="display: none;">
      <h5 class="fw-bold mb-3" style="font-size: 0.95rem;">Kritik Stok Seviyeleri</h5>
      <div id="stock-alerts-list" class="d-flex flex-column gap-2">
        <p class="text-center text-muted small py-5">Kritik stok seviyesinde bileşen bulunmamaktadır.</p>
      </div>
    </div>

    <!-- ==================== BOTTOM TAB BAR NAVIGATION ==================== -->
    <div class="pwa-nav">
      <a href="#" class="pwa-nav-item active" id="nav-dashboard" onclick="switchPage('dashboard')">
        <i class="bi bi-grid-fill"></i>Panel
      </a>
      <a href="#" class="pwa-nav-item" id="nav-tasks" onclick="switchPage('tasks')">
        <i class="bi bi-clipboard2-data-fill"></i>Görevler
      </a>
      <a href="#" class="pwa-nav-item" id="nav-alerts" onclick="switchPage('alerts')">
        <i class="bi bi-exclamation-octagon-fill"></i>Stoklar
      </a>
    </div>
  </div>

  <!-- Custom routing and API library scripts -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script type="module">
    import { api } from '/js/pwa/api.js';

    window.api = api; // Expose globally for inline event handlers

    // Initialize SPA Shell
    document.addEventListener('DOMContentLoaded', () => {
      // Register PWA Service Worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
          .then(reg => console.log('SW: Service Worker Registered', reg))
          .catch(err => console.error('SW: Registration failed', err));
      }

      // Check current auth status
      if (api.getToken()) {
        showAppShell();
      } else {
        showLoginPage();
      }

      // Network State Listeners
      window.addEventListener('online', handleOnline);
      window.addEventListener('offline', handleOffline);

      // Handle unauthorized event
      window.addEventListener('pwa-unauthorized', () => {
        showLoginPage();
        Swal.fire('Oturum Kapandı', 'Giriş süreniz doldu, lütfen tekrar giriş yapın.', 'warning');
      });

      // Synchronize offline queue when returning online
      if (navigator.onLine) {
        api.syncOfflineQueue();
      }
    });

    // Handle Network State Changes
    function handleOnline() {
      document.getElementById('offline-banner').style.display = 'none';
      Swal.fire({
        icon: 'success',
        title: 'Bağlantı Sağlandı',
        text: 'İnternet bağlantınız geri geldi, veriler senkronize ediliyor...',
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
      });
      api.syncOfflineQueue();
    }

    function handleOffline() {
      document.getElementById('offline-banner').style.display = 'block';
      Swal.fire({
        icon: 'warning',
        title: 'Çevrimdışı Mod',
        text: 'İnternet bağlantısı kesildi. Yaptığınız değişiklikler çevrimdışı kaydedilecek.',
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
      });
    }

    // App Navigation Pages
    window.switchPage = function(pageId) {
      document.querySelectorAll('.container-page').forEach(el => el.style.display = 'none');
      document.querySelectorAll('.pwa-nav-item').forEach(el => el.classList.remove('active'));

      document.getElementById(`page-${pageId}`).style.display = 'block';
      document.getElementById(`nav-${pageId}`).classList.add('active');

      if (pageId === 'dashboard') loadDashboardData();
      if (pageId === 'tasks') loadTasksData();
      if (pageId === 'alerts') loadStockAlertsData();
    }

    // Login action
    window.handleLogin = async function(event) {
      event.preventDefault();
      const email = document.getElementById('login-email').value;
      const password = document.getElementById('login-password').value;

      try {
        const response = await fetch('/api/login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify({ email, password })
        });
        const data = await response.json();

        if (data.success) {
          api.setToken(data.token);
          showAppShell();
          Swal.fire('Giriş Başarılı', `Hoş geldiniz, ${data.user.name}`, 'success');
        } else {
          Swal.fire('Hata', data.message || 'Giriş yapılamadı.', 'error');
        }
      } catch (e) {
        Swal.fire('Hata', 'Sunucu bağlantı hatası.', 'error');
      }
    }

    // Logout action
    window.handleLogout = async function() {
      try {
        await api.request('/logout', { method: 'POST' });
      } catch(e) {}
      api.clearToken();
      showLoginPage();
    }

    function showLoginPage() {
      document.getElementById('page-login').style.display = 'flex';
      document.getElementById('app-shell').style.display = 'none';
    }

    async function showAppShell() {
      document.getElementById('page-login').style.display = 'none';
      document.getElementById('app-shell').style.display = 'block';
      
      // Load user profile
      try {
        const r = await api.request('/me');
        const d = await r.json();
        if (d.success) {
          document.getElementById('user-display-name').textContent = `${d.user.name} ${d.user.surname}`;
        }
      } catch (e) {}

      switchPage('dashboard');
    }

    // Load Dashboard Statistics & Notifications
    async function loadDashboardData() {
      try {
        // Load notifications
        const r = await api.request('/notifications');
        const notifications = await r.json();
        const listNode = document.getElementById('notifications-list');

        if (notifications.data && notifications.data.length > 0) {
          listNode.innerHTML = '';
          notifications.data.slice(0, 5).forEach(n => {
            const card = document.createElement('div');
            card.className = 'pwa-card p-3 d-flex align-items-center justify-content-between';
            card.innerHTML = `
              <div>
                <strong class="d-block small" style="color: #fff;">${n.title}</strong>
                <span class="small text-muted">${n.message}</span>
              </div>
              ${!n.read_at ? `<button class="btn btn-xs btn-outline-info" onclick="markNotificationRead(${n.id}, this)"><i class="bi bi-check"></i></button>` : ''}
            `;
            listNode.appendChild(card);
          });
        }

        // Fetch tasks and alerts counts
        const rTasks = await api.request('/tasks');
        const tasks = await rTasks.json();
        document.getElementById('dash-active-tasks-count').textContent = tasks.data ? tasks.data.length : 0;

        const rAlerts = await api.request('/stock-alerts');
        const alerts = await rAlerts.json();
        document.getElementById('dash-critical-stocks-count').textContent = alerts.data ? alerts.data.length : 0;

      } catch (e) {}
    }

    window.markNotificationRead = async function(id, btn) {
      try {
        const r = await api.request(`/notifications/${id}/read`, { method: 'POST' });
        const d = await r.json();
        if (d.success) {
          btn.remove();
          loadDashboardData();
        }
      } catch (e) {}
    }

    // Load tasks lists
    async function loadTasksData() {
      const listNode = document.getElementById('tasks-list');
      try {
        const r = await api.request('/tasks');
        const tasks = await r.json();

        document.getElementById('tasks-count-badge').textContent = `${tasks.data ? tasks.data.length : 0} Görev`;

        if (!tasks.data || tasks.data.length === 0) {
          listNode.innerHTML = `<p class="text-center text-muted small py-5">Atanmış üretim göreviniz bulunmuyor.</p>`;
          return;
        }

        listNode.innerHTML = '';
        tasks.data.forEach(t => {
          const card = document.createElement('div');
          card.className = 'pwa-card';
          
          const progressPercent = Math.round(((t.quantity - t.pending_quantity) / t.quantity) * 100);

          card.innerHTML = `
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <strong class="d-block" style="font-size: 0.95rem;">${t.product_name || t.component_name}</strong>
                <span class="small text-muted d-block" style="font-size: 0.75rem;">Başlama: ${t.start_date}</span>
              </div>
              <span class="pwa-badge ${t.status === 'completed' ? 'success' : (t.status === 'in_progress' ? 'info' : 'warning')}">${t.status}</span>
            </div>

            <div class="mb-3">
              <div class="d-flex justify-content-between text-muted small mb-1" style="font-size: 0.75rem;">
                <span>Üretilen: ${t.quantity - t.pending_quantity} / ${t.quantity}</span>
                <span>%${progressPercent}</span>
              </div>
              <div class="pwa-progress">
                <div class="pwa-progress-bar" style="width: ${progressPercent}%;"></div>
              </div>
            </div>

            <div class="d-flex gap-2">
              <input type="number" class="pwa-input p-2 text-center" style="width: 80px;" id="prod-qty-${t.id}" min="1" max="${t.pending_quantity}" value="${t.pending_quantity}">
              <button class="pwa-btn p-2" onclick="reportProgress(${t.id})">Adet Üret</button>
            </div>
          `;
          listNode.appendChild(card);
        });
      } catch (e) {}
    }

    // Report production progress
    window.reportProgress = async function(id) {
      const qtyInput = document.getElementById(`prod-qty-${id}`);
      const qty = parseInt(qtyInput.value);

      if (isNaN(qty) || qty <= 0) {
        Swal.fire('Geçersiz Değer', 'Lütfen geçerli bir üretim adeti girin.', 'warning');
        return;
      }

      try {
        const r = await api.request(`/tasks/${id}/progress`, {
          method: 'POST',
          body: JSON.stringify({ completed_qty: qty })
        });
        const d = await r.json();

        if (d.success) {
          Swal.fire('Başarılı', d.message, 'success');
          loadTasksData();
        }
      } catch (e) {
        if (e.message === 'OFFLINE_SAVED') {
          Swal.fire('Çevrimdışı Kaydedildi', 'Bağlantı koptuğu için işleminiz cihaz belleğinde kuyruğa alındı. İnternet geri geldiğinde senkronize edilecektir.', 'info');
          loadTasksData();
        }
      }
    }

    // Load Stock Alerts
    async function loadStockAlertsData() {
      const listNode = document.getElementById('stock-alerts-list');
      try {
        const r = await api.request('/stock-alerts');
        const alerts = await r.json();

        if (!alerts.data || alerts.data.length === 0) {
          listNode.innerHTML = `<p class="text-center text-muted small py-5">Kritik stok seviyesinde bileşen bulunmamaktadır.</p>`;
          return;
        }

        listNode.innerHTML = '';
        alerts.data.forEach(a => {
          const card = document.createElement('div');
          card.className = 'pwa-card d-flex justify-content-between align-items-center';
          card.innerHTML = `
            <div>
              <strong class="d-block" style="font-size: 0.9rem;">${a.component_name}</strong>
              <span class="small text-muted" style="font-size: 0.75rem;">Minimum Eşik: ${a.min_threshold} adet</span>
            </div>
            <div class="text-end">
              <span class="badge bg-danger-subtle text-danger" style="font-size: 0.8rem; font-weight: 700;">${a.current_quantity} Adet</span>
            </div>
          `;
          listNode.appendChild(card);
        });
      } catch (e) {}
    }
  </script>
</body>
</html>
