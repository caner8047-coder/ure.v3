const API_BASE = '/api';

export const api = {
  getToken() {
    return localStorage.getItem('zemu_token');
  },

  setToken(token) {
    localStorage.setItem('zemu_token', token);
  },

  clearToken() {
    localStorage.removeItem('zemu_token');
  },

  isOnline() {
    return navigator.onLine;
  },

  async request(endpoint, options = {}) {
    const token = this.getToken();
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(options.headers || {})
    };

    const config = {
      ...options,
      headers
    };

    if (!this.isOnline() && options.method && options.method !== 'GET') {
      // Offline queue logic
      this.enqueueOfflineRequest(endpoint, options);
      throw new Error('OFFLINE_SAVED');
    }

    try {
      const response = await fetch(`${API_BASE}${endpoint}`, config);
      if (response.status === 401) {
        this.clearToken();
        window.dispatchEvent(new CustomEvent('pwa-unauthorized'));
      }
      return response;
    } catch (e) {
      if (!this.isOnline() && options.method && options.method !== 'GET') {
        this.enqueueOfflineRequest(endpoint, options);
        throw new Error('OFFLINE_SAVED');
      }
      throw e;
    }
  },

  enqueueOfflineRequest(endpoint, options) {
    const queue = JSON.parse(localStorage.getItem('zemu_offline_queue') || '[]');
    queue.push({
      id: Date.now(),
      endpoint,
      method: options.method,
      body: options.body,
      timestamp: new Date().toISOString()
    });
    localStorage.setItem('zemu_offline_queue', JSON.stringify(queue));
    window.dispatchEvent(new CustomEvent('pwa-offline-enqueued'));
  },

  async syncOfflineQueue() {
    if (!this.isOnline()) return;

    const queue = JSON.parse(localStorage.getItem('zemu_offline_queue') || '[]');
    if (queue.length === 0) return;

    console.log(`Syncing ${queue.length} offline operations...`);
    const failed = [];

    for (const req of queue) {
      try {
        await fetch(`${API_BASE}${req.endpoint}`, {
          method: req.method,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${this.getToken()}`
          },
          body: req.body
        });
      } catch (e) {
        failed.push(req);
      }
    }

    localStorage.setItem('zemu_offline_queue', JSON.stringify(failed));
    window.dispatchEvent(new CustomEvent('pwa-sync-completed', { detail: { synced: queue.length - failed.length } }));
  }
};
