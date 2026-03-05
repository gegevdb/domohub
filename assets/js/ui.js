/**
 * DomoGlass Pro - Module UI
 * Toasts, modals, navigation, logs, thème, horloge
 */

import { store } from './store.js';

export const UI = {

    // ─── Initialisation ───────────────────────────────────────────────────────

    init() {
        this.#startClock();
        this.#initTheme();
        this.#bindGlobalEvents();
        store.loadPrefs();

        // Affiche la section initiale
        const section = store.get('ui.activeSection') ?? 'dashboard';
        Nav.showSection(section);
    },

    // ─── Toasts ───────────────────────────────────────────────────────────────

    toast(message, type = 'info', duration = 5000) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const icons = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error:   'fa-times-circle',
            info:    'fa-info-circle',
        };

        const colors = {
            success: 'var(--accent-success)',
            warning: 'var(--accent-warning)',
            error:   'var(--accent-danger)',
            info:    'var(--accent-info)',
        };

        const toast = document.createElement('div');
        toast.className = 'notification glass border-l-4 p-4 rounded-xl shadow-2xl flex items-center space-x-3 min-w-64 max-w-sm';
        toast.style.borderLeftColor = colors[type] ?? colors.info;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        toast.innerHTML = `
            <i class="fas ${icons[type] ?? icons.info} text-xl flex-shrink-0"
               style="color: ${colors[type] ?? colors.info}"></i>
            <div class="flex-1 font-medium text-sm">${this.#escapeHtml(message)}</div>
            <button onclick="this.parentElement.remove()" class="opacity-50 hover:opacity-100 flex-shrink-0">
                <i class="fas fa-times text-xs"></i>
            </button>`;

        container.appendChild(toast);

        // Auto-suppression
        const timer = setTimeout(() => {
            toast.style.transition = 'opacity 0.3s, transform 0.3s';
            toast.style.opacity    = '0';
            toast.style.transform  = 'translateX(110%)';
            setTimeout(() => toast.remove(), 300);
        }, duration);

        // Pause au survol
        toast.addEventListener('mouseenter', () => clearTimeout(timer));

        return toast;
    },

    // ─── Logs système ─────────────────────────────────────────────────────────

    addLog(message, type = 'info') {
        const container = document.getElementById('system-logs');
        if (!container) return;

        const time = new Date().toLocaleTimeString('fr-FR', { hour12: false });
        const entry = document.createElement('div');
        entry.className = `log-entry ${type}`;
        entry.innerHTML = `
            <span class="font-bold">[${time}]</span>
            <span class="font-bold uppercase text-xs px-1">${type}</span>
            ${this.#escapeHtml(message)}`;

        container.insertBefore(entry, container.firstChild);

        // Limite à 100 entrées
        while (container.children.length > 100) {
            container.removeChild(container.lastChild);
        }

        // Sauvegarde dans le store
        store.update('logs', logs => [
            { time, type, message, ts: Date.now() },
            ...logs.slice(0, 99),
        ]);
    },

    clearLogs() {
        const container = document.getElementById('system-logs');
        if (container) container.innerHTML = '';
        store.set('logs', []);
        this.toast('Journaux effacés', 'info');
    },

    exportLogs() {
        const logs = store.get('logs') ?? [];
        const text = logs.map(l => `[${l.time}] [${l.type.toUpperCase()}] ${l.message}`).join('\n');
        const blob = new Blob([text], { type: 'text/plain' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = `domoglass_logs_${new Date().toISOString().slice(0,10)}.txt`;
        a.click();
        URL.revokeObjectURL(url);
        this.toast('Logs exportés', 'success');
    },

    // ─── Notifications ────────────────────────────────────────────────────────

    toggleNotifications() {
        document.getElementById('notification-panel')?.classList.toggle('hidden');
    },

    markAllRead() {
        document.getElementById('notif-count')?.remove();
        const list = document.getElementById('notification-list');
        if (list) {
            list.innerHTML = '<p class="text-center text-xs py-4" style="color:var(--text-muted)">Aucune notification</p>';
        }
        store.set('ui.unreadNotifs', 0);
    },

    pushNotification(notif) {
        const list = document.getElementById('notification-list');
        const info = document.querySelector('#notification-list > p');
        if (info) info.remove();

        const item = document.createElement('div');
        item.className = 'p-2 rounded-xl glass mb-1 text-sm';
        item.innerHTML = `
            <div class="font-semibold">${this.#escapeHtml(notif.title ?? '')}</div>
            <div class="text-xs opacity-70 mt-0.5">${this.#escapeHtml(notif.message ?? '')}</div>`;

        list?.insertBefore(item, list.firstChild);

        // Badge
        const badge = document.getElementById('notif-count');
        const count = parseInt(badge?.textContent ?? 0) + 1;
        if (badge) {
            badge.textContent = count > 9 ? '9+' : count;
        } else {
            const btn = document.querySelector('[aria-label="Notifications"]');
            if (btn) {
                const b = document.createElement('span');
                b.id = 'notif-count';
                b.className = 'absolute -top-1 -right-1 text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold text-white';
                b.style.background = 'var(--accent-danger)';
                b.textContent = '1';
                btn.appendChild(b);
            }
        }
    },

    // ─── Connexion status ─────────────────────────────────────────────────────

    updateConnectionStatus(connected, label = null) {
        const dot  = document.getElementById('status-dot');
        const text = document.getElementById('status-text');

        if (dot) {
            dot.style.background = connected
                ? 'var(--status-online)'
                : 'var(--status-offline)';
            dot.classList.toggle('animate-pulse', connected);
        }

        if (text) {
            text.textContent = label ?? (connected ? 'Connecté' : 'Déconnecté');
        }
    },

    // ─── Stats header ─────────────────────────────────────────────────────────

    updateStats() {
        const stats = store.computeStats();
        const set   = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        set('stat-total',  stats.total);
        set('stat-online', stats.online);
        set('stat-active', stats.active);
    },

    // ─── Modals ───────────────────────────────────────────────────────────────

    openModal(id) {
        document.getElementById(id)?.classList.add('active');
    },

    closeModal(id) {
        document.getElementById(id)?.classList.remove('active');
    },

    openSettings() {
        this.openModal('settings-modal');
    },

    // ─── Thème ────────────────────────────────────────────────────────────────

    #initTheme() {
        const saved = store.get('ui.palette') ?? 'midnight';
        this.setPalette(saved);
    },

    setPalette(name) {
        document.documentElement.setAttribute('data-palette', name);
        document.body.setAttribute('data-palette', name);
        store.set('ui.palette', name);
        store.savePrefs();

        // Met à jour les boutons de palette
        document.querySelectorAll('.palette-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.palette === name);
        });
    },

    // ─── Horloge ──────────────────────────────────────────────────────────────

    #startClock() {
        const update = () => {
            const now  = new Date();
            const time = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
            const el   = document.getElementById('header-clock');
            if (el) el.textContent = time;
        };
        update();
        setInterval(update, 30_000);
    },

    // ─── Mobile menu ──────────────────────────────────────────────────────────

    toggleMobileMenu() {
        document.getElementById('main-nav')?.classList.toggle('hidden');
    },

    // ─── Events globaux ───────────────────────────────────────────────────────

    #bindGlobalEvents() {
        // Ferme les modals en cliquant sur le fond
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', e => {
                if (e.target === modal) modal.classList.remove('active');
            });
        });

        // Ferme le panneau notifs en cliquant ailleurs
        document.addEventListener('click', e => {
            const panel = document.getElementById('notification-panel');
            const btn   = e.target.closest('[aria-label="Notifications"]');
            if (panel && !panel.contains(e.target) && !btn) {
                panel.classList.add('hidden');
            }
        });

        // Écoute les changements d'état des devices
        store.subscribe('devices', () => this.updateStats());
    },

    // ─── Helpers ──────────────────────────────────────────────────────────────

    #escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    },
};


// ─── Navigation ───────────────────────────────────────────────────────────────

export const Nav = {
    showSection(id) {
        // Cache toutes les sections
        document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));

        // Affiche la section cible
        const target = document.getElementById('section-' + id);
        if (target) target.classList.remove('hidden');

        // Met à jour les boutons de nav
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.section === id);
        });

        store.set('ui.activeSection', id);
        store.savePrefs();
    },

    filterRoom(roomId) {
        store.set('ui.activeRoom', roomId);

        // Met à jour les onglets de pièces
        document.querySelectorAll('.room-tab').forEach(tab => {
            const tabRoom = tab.dataset.room === 'all' ? null : parseInt(tab.dataset.room);
            tab.classList.toggle('active', tabRoom === roomId);
        });

        // Filtre les device-cards
        document.querySelectorAll('.device-card[data-room-id]').forEach(card => {
            const cardRoom = parseInt(card.dataset.roomId) || null;
            card.style.display = (roomId === null || cardRoom === roomId) ? '' : 'none';
        });
    },
};

// Expose pour les onclick HTML
window.UI  = UI;
window.Nav = Nav;
