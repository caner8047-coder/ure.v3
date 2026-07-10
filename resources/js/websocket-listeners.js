// WebSocket Live Listeners & Toast Notification Service for ZemuRetim v3
document.addEventListener('DOMContentLoaded', () => {
    // 1. Create and Inject Toast Container
    let toastContainer = document.getElementById('websocket-toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'websocket-toast-container';
        toastContainer.style.position = 'fixed';
        toastContainer.style.bottom = '24px';
        toastContainer.style.right = '24px';
        toastContainer.style.zIndex = '99999';
        toastContainer.style.display = 'flex';
        toastContainer.style.flexDirection = 'column';
        toastContainer.style.gap = '12px';
        toastContainer.style.maxWidth = '380px';
        toastContainer.style.width = '100%';
        document.body.appendChild(toastContainer);
    }

    // Toast Style Helper
    function showToast(title, message, iconClass = 'bi-info-circle', bgType = 'dark') {
        const toast = document.createElement('div');
        toast.className = `crm-toast toast-bg-${bgType}`;
        toast.style.display = 'flex';
        toast.style.gap = '12px';
        toast.style.alignItems = 'start';
        toast.style.padding = '14px 18px';
        toast.style.borderRadius = '12px';
        toast.style.background = bgType === 'danger' ? '#7f1d1d' : bgType === 'warning' ? '#78350f' : bgType === 'success' ? '#064e3b' : '#1e293b';
        toast.style.color = '#f8fafc';
        toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -4px rgba(0, 0, 0, 0.3)';
        toast.style.border = `1px solid ${bgType === 'danger' ? '#b91c1c' : bgType === 'warning' ? '#d97706' : bgType === 'success' ? '#059669' : '#334155'}`;
        toast.style.animation = 'slideIn 0.3s ease forwards';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';

        toast.innerHTML = `
            <i class="bi ${iconClass}" style="font-size: 1.25rem; color: ${bgType === 'danger' ? '#fca5a5' : bgType === 'warning' ? '#fcd34d' : bgType === 'success' ? '#6ee7b7' : '#94a3b8'}"></i>
            <div style="flex: 1;">
                <h5 style="margin: 0 0 4px 0; font-size: 0.875rem; font-weight: 600; line-height: 1.25;">${title}</h5>
                <p style="margin: 0; font-size: 0.75rem; color: #cbd5e1; line-height: 1.4;">${message}</p>
            </div>
            <button class="toast-close-btn" style="background: none; border: none; color: #94a3b8; font-size: 1rem; cursor: pointer; padding: 0; margin-left: 8px;">&times;</button>
        `;

        // Slide in animation
        const styleSheet = document.createElement("style");
        styleSheet.innerText = `
            @keyframes slideIn {
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes fadeOut {
                to { opacity: 0; transform: translateY(-20px); }
            }
        `;
        document.head.appendChild(styleSheet);

        toast.querySelector('.toast-close-btn').addEventListener('click', () => {
            toast.style.animation = 'fadeOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        });

        toastContainer.appendChild(toast);

        // Auto remove after 6 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'fadeOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 6000);
    }

    // 2. Initialize Echo Subscriptions
    if (window.Echo) {
        // --- 2.1 Public Production Channel ---
        window.Echo.channel('production')
            .listen('.work-order.status-changed', (e) => {
                showToast(
                    e.title || 'İş Emri Güncellendi',
                    `${e.summary || ''} (Durum: ${e.status_after})`,
                    'bi-activity',
                    'info'
                );

                // Dispatch global event for views to react dynamically without reload
                document.dispatchEvent(new CustomEvent('work-order-updated', { detail: e }));
            });

        // --- 2.2 Private Channels depending on user meta ---
        const metaCsrf = document.querySelector('meta[name="csrf-token"]');
        
        // Fetch current user details dynamically to join private channels
        axios.get('/api/panel/dashboard-stats')
            .then(response => {
                if (response.data && response.data.success) {
                    const stats = response.data;
                    const userId = stats.personel_no || stats.userId || 0;
                    const deptId = stats.department_id || 0;

                    if (userId > 0) {
                        // Join personal user channel
                        window.Echo.private(`user.${userId}`)
                            .listen('.notification.new', (e) => {
                                showToast(
                                    e.title,
                                    e.message,
                                    'bi-bell',
                                    e.severity || 'info'
                                );
                                document.dispatchEvent(new CustomEvent('new-notification-received', { detail: e }));
                            })
                            .listen('.task.assigned', (e) => {
                                showToast(
                                    'Yeni Görev Atandı 🚀',
                                    `${e.task_description} - ${e.product_name || ''}`,
                                    'bi-rocket-takeoff',
                                    'success'
                                );
                                document.dispatchEvent(new CustomEvent('task-assigned-to-me', { detail: e }));
                            });
                    }

                    if (deptId > 0) {
                        // Join department channel
                        window.Echo.private(`department.${deptId}`)
                            .listen('.work-order.status-changed', (e) => {
                                // Since we already listen to production channel, we can use this for more targeted department alerts.
                                document.dispatchEvent(new CustomEvent('department-work-order-updated', { detail: e }));
                            });
                    }
                }
            })
            .catch(() => {
                // If dashboard-stats fails (e.g. admin page), try to get user info from layout data attributes if present.
                const userElement = document.querySelector('[data-current-user-id]');
                if (userElement) {
                    const userId = parseInt(userElement.getAttribute('data-current-user-id') || '0');
                    if (userId > 0) {
                        window.Echo.private(`user.${userId}`)
                            .listen('.notification.new', (e) => {
                                showToast(e.title, e.message, 'bi-bell', e.severity);
                            });
                    }
                }
            });

        // --- 2.3 Stock Alerts Private Channel ---
        // Join stock-alerts channel (Echo auto handles authentication request to /broadcasting/auth)
        window.Echo.private('stock-alerts')
            .listen('.stock.critical-alert', (e) => {
                showToast(
                    `Kritik Stok Uyarısı: ${e.component_name}`,
                    `${e.department_name} departmanındaki stok seviyesi kritik sınırda! Mevcut: ${e.current_quantity} (Eşik: ${e.threshold_quantity})`,
                    'bi-exclamation-triangle-fill',
                    'danger'
                );
                document.dispatchEvent(new CustomEvent('stock-alert-received', { detail: e }));
            });
    }
});
