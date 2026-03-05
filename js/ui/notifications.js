/**
 * DOMOGLASS PRO — Notifications & Toasts
 */

import { addNotification, subscribe } from '../store.js';

// ============================================================
//  Toast (messages éphémères)
// ============================================================

const TOAST_COLORS = {
    success: { border: 'var(--accent-success)', icon: 'fa-check-circle',  iconColor: 'var(--accent-success)' },
    warning: { border: 'var(--accent-warning)', icon: 'fa-exclamation-triangle', iconColor: 'var(--accent-warning)' },
    error:   { border: 'var(--accent-danger)',  icon: 'fa-times-circle',  iconColor: 'var(--accent-danger)' },
    info:    { border: 'var(--accent-info)',     icon: 'fa-info-circle',   iconColor: 'var(--accent-info)' },
};

export function showToast(message, type = 'info', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const c     = TOAST_COLORS[type] ?? TOAST_COLORS.info;
    const toast = document.createElement('div');

    toast.className = 'notification glass rounded-xl shadow-2xl flex items-center space-x-3 min-w-64 max-w-sm';
    toast.style.cssText = `
        border-left: 4px solid ${c.border};
        padding: 14px 16px;
        animation: slideIn 0.35s cubic-bezier(.4,0,.2,1);
    `;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <i class="fas ${c.icon} text-lg flex-shrink-0" style="color: ${c.iconColor}"></i>
        <p class="text-sm font-medium flex-1" style="color: var(--text-primary)">${escHtml(message)}</p>
        <button onclick="this.closest('[role=alert]').remove()"
                class="ml-2 opacity-50 hover:opacity-100 transition text-lg leading-none"
                aria-label="Fermer">×</button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(120%)';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// ============================================================
//  Panneau de notifications
// ============================================================

export function initNotifications() {
    // Écouter les nouvelles notifications du store
    subscribe('notification:new', renderNotification);

    // Charger les notifications existantes depuis l'API
    loadNotifications();
}

async function loadNotifications() {
    try {
        const res  = await fetch('/api/actions.php?limit=10');
        const json = await res.json();
        // (optionnel: charger notif depuis table notifications)
    } catch (e) {
        console.warn('[Notif] loadNotifications:', e);
    }
}

function renderNotification(notif) {
    const list = document.getElementById('notif-list');
    if (!list) return;

    // Retirer le message "aucune notification"
    const empty = list.querySelector('.empty-notif');
    if (empty) empty.remove();

    const icons = { info: 'fa-info-circle text-blue-400', success: 'fa-check-circle text-green-400',
                    warning: 'fa-exclamation-triangle text-yellow-400', error: 'fa-times-circle text-red-400' };

    const item = document.createElement('div');
    item.className = 'flex items-start space-x-3 p-2 rounded-xl hover:opacity-80 transition cursor-default';
    item.innerHTML = `
        <i class="fas ${icons[notif.type] ?? icons.info} mt-0.5 flex-shrink-0"></i>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold truncate" style="color: var(--text-primary)">${escHtml(notif.title)}</p>
            ${notif.message ? `<p class="text-xs opacity-60 truncate" style="color: var(--text-muted)">${escHtml(notif.message)}</p>` : ''}
        </div>
    `;
    list.prepend(item);

    // Mettre à jour le compteur
    const badge = document.getElementById('notif-count');
    if (badge) {
        const current = parseInt(badge.textContent ?? '0', 10) + 1;
        badge.textContent = Math.min(current, 9).toString();
        badge.style.display = '';
    }

    // Afficher aussi en toast
    showToast(notif.title, notif.type);
}

// ============================================================
//  Fonctions globales (appelées depuis le HTML)
// ============================================================

window.toggleNotifications = function () {
    const panel = document.getElementById('notification-panel');
    if (panel) panel.classList.toggle('hidden');
};

window.markAllRead = function () {
    const badge = document.getElementById('notif-count');
    if (badge) badge.style.display = 'none';

    fetch('/api/actions.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.DOMOGLASS.csrfToken },
        body:    JSON.stringify({ action: 'mark_read' }),
    }).catch(() => {});
};

// ============================================================
//  Util
// ============================================================

function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
