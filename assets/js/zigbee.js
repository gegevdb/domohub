/**
 * DomoGlass Pro - Module Zigbee (ZHA)
 * Gère le pairing, la liste des devices et la topologie réseau
 */

import { store } from './store.js';
import { UI }    from './ui.js';

const API_BASE = window.DomoGlassConfig?.apiBase ?? '/api';
const API_KEY  = window.DomoGlassConfig?.apiKey  ?? '';

export const Zigbee = {
    permitDuration: 60,
    countdownTimer: null,
    pollTimer:      null,

    // ─── Initialisation ───────────────────────────────────────────────────────

    async init() {
        await this.loadStats();
        await this.loadDevices();
        this.#setupSSEListeners();
    },

    // ─── Pairing ──────────────────────────────────────────────────────────────

    setPermitDuration(seconds) {
        this.permitDuration = seconds;

        // Met à jour les boutons de sélection
        document.querySelectorAll('.permit-duration-btn').forEach(btn => {
            const active = parseInt(btn.dataset.duration) === seconds;
            btn.style.background = active ? 'var(--accent-primary)' : '';
            btn.style.color      = active ? 'white' : '';
        });
    },

    async permitJoin() {
        try {
            const res = await this.#request('POST', '/zigbee.php?action=permit_join', {
                duration: this.permitDuration,
            });

            if (res.success) {
                this.#startCountdown(this.permitDuration);
                store.set('ui.pairingActive', true);
                UI.toast(`Pairing actif pour ${this.permitDuration}s`, 'success');

                document.getElementById('btn-permit-join')?.classList.add('hidden');
                document.getElementById('btn-stop-join')?.classList.remove('hidden');

                // Poll pour détecter les nouveaux devices
                this.#startPolling();
            } else {
                UI.toast('Impossible d\'activer le pairing', 'error');
            }
        } catch (e) {
            UI.toast('Erreur : ' + e.message, 'error');
        }
    },

    async stopJoin() {
        try {
            await this.#request('POST', '/zigbee.php?action=stop_join');
            this.#stopCountdown();
            this.#stopPolling();
            store.set('ui.pairingActive', false);

            document.getElementById('btn-permit-join')?.classList.remove('hidden');
            document.getElementById('btn-stop-join')?.classList.add('hidden');

            UI.toast('Mode pairing désactivé', 'info');

            // Sync finale pour récupérer les nouveaux devices
            await this.syncDevices();

        } catch (e) {
            UI.toast('Erreur arrêt pairing : ' + e.message, 'error');
        }
    },

    #startCountdown(seconds) {
        const el  = document.getElementById('countdown-value');
        const bar = document.getElementById('countdown-bar');
        const container = document.getElementById('pairing-countdown');
        const indicator = document.getElementById('pairing-indicator');
        const statusTxt = document.getElementById('pairing-status-text');

        container?.classList.remove('hidden');

        if (indicator) indicator.style.background = 'var(--accent-success)';
        if (indicator) indicator.classList.add('animate-pulse');
        if (statusTxt) statusTxt.textContent = 'Actif';

        const total = seconds;
        let remaining = seconds;

        const update = () => {
            if (el)  el.textContent  = remaining;
            if (bar) bar.style.width = ((remaining / total) * 100) + '%';

            if (remaining <= 10 && bar) {
                bar.style.background = 'var(--accent-danger)';
            }
        };

        update();
        this.countdownTimer = setInterval(() => {
            remaining--;
            update();
            if (remaining <= 0) {
                this.#stopCountdown();
                this.stopJoin();
            }
        }, 1000);
    },

    #stopCountdown() {
        clearInterval(this.countdownTimer);
        this.countdownTimer = null;

        document.getElementById('pairing-countdown')?.classList.add('hidden');
        document.getElementById('new-device-alert')?.classList.add('hidden');

        const indicator = document.getElementById('pairing-indicator');
        const statusTxt = document.getElementById('pairing-status-text');
        if (indicator) {
            indicator.style.background = 'var(--text-muted)';
            indicator.classList.remove('animate-pulse');
        }
        if (statusTxt) statusTxt.textContent = 'Inactif';
    },

    // ─── Polling pendant le pairing ───────────────────────────────────────────

    #startPolling() {
        let knownIeees = new Set(
            (store.get('zigbee.devices') ?? []).map(d => d.ieee)
        );

        this.pollTimer = setInterval(async () => {
            const devices = await this.#fetchDevices();

            devices.forEach(d => {
                if (!knownIeees.has(d.ieee)) {
                    knownIeees.add(d.ieee);
                    this.#showNewDeviceAlert(d);
                    UI.addLog(`Nouveau device Zigbee détecté : ${d.name} (${d.ieee})`, 'success');
                    UI.toast(`Nouveau device : ${d.name}`, 'success');
                }
            });

        }, 3000);
    },

    #stopPolling() {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
    },

    #showNewDeviceAlert(device) {
        const alert = document.getElementById('new-device-alert');
        const name  = document.getElementById('new-device-name');
        const ieee  = document.getElementById('new-device-ieee');

        if (!alert) return;
        if (name) name.textContent = `${device.name} (${device.manufacturer ?? ''} ${device.model ?? ''})`;
        if (ieee) ieee.textContent = device.ieee;

        alert.classList.remove('hidden');
        setTimeout(() => alert.classList.add('hidden'), 8000);
    },

    // ─── Chargement des données ───────────────────────────────────────────────

    async loadStats() {
        try {
            const stats = await this.#request('GET', '/zigbee.php?action=stats');
            store.set('zigbee.stats', stats);
            this.#renderStats(stats);
        } catch (e) {
            console.warn('[Zigbee] Stats non disponibles:', e.message);
        }
    },

    async loadDevices() {
        const devices = await this.#fetchDevices();
        store.set('zigbee.devices', devices);
        this.#renderDeviceList(devices);
    },

    async #fetchDevices() {
        try {
            const res = await this.#request('GET', '/zigbee.php?action=list');
            return res.devices ?? [];
        } catch (e) {
            console.warn('[Zigbee] Liste devices non disponible:', e.message);
            return [];
        }
    },

    async syncDevices() {
        const icon = document.getElementById('sync-icon');
        icon?.classList.add('fa-spin');

        try {
            const res = await this.#request('POST', '/zigbee.php?action=sync');
            UI.toast(res.message, 'success');
            await this.loadDevices();
            await this.loadStats();
        } catch (e) {
            UI.toast('Erreur de synchronisation : ' + e.message, 'error');
        } finally {
            icon?.classList.remove('fa-spin');
        }
    },

    async loadTopology() {
        try {
            const topology = await this.#request('GET', '/zigbee.php?action=topology');
            store.set('zigbee.topology', topology);
            this.#renderTopology(topology);
        } catch (e) {
            UI.toast('Topologie non disponible', 'warning');
        }
    },

    // ─── Actions sur un device ────────────────────────────────────────────────

    async reconfigureDevice(ieee) {
        if (!confirm(`Reconfigurer ${ieee} ?`)) return;
        try {
            const res = await this.#request('POST', '/zigbee.php?action=reconfigure', { ieee });
            UI.toast(res.message, res.success ? 'success' : 'error');
        } catch (e) {
            UI.toast('Erreur : ' + e.message, 'error');
        }
    },

    async removeDevice(ieee, name) {
        if (!confirm(`Supprimer définitivement "${name}" (${ieee}) ?`)) return;
        try {
            const res = await this.#request('DELETE', '/zigbee.php?ieee=' + encodeURIComponent(ieee));
            if (res.success) {
                UI.toast(res.message, 'success');
                await this.loadDevices();
                await this.loadStats();
            } else {
                UI.toast(res.message, 'error');
            }
        } catch (e) {
            UI.toast('Erreur suppression : ' + e.message, 'error');
        }
    },

    // ─── Filtre ───────────────────────────────────────────────────────────────

    filterDevices(query) {
        const rows = document.querySelectorAll('#zigbee-devices-list [data-ieee]');
        const q    = query.toLowerCase();
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    },

    // ─── Rendu ────────────────────────────────────────────────────────────────

    #renderStats(stats) {
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val ?? '–';
        };
        set('zb-total',   stats.total);
        set('zb-online',  stats.online);
        set('zb-offline', stats.offline);
        set('zb-lqi',     stats.avg_lqi);
    },

    #renderDeviceList(devices) {
        const container = document.getElementById('zigbee-devices-list');
        if (!container) return;

        if (devices.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8" style="color: var(--text-muted)">
                    <i class="fas fa-network-wired text-3xl mb-2 opacity-30"></i>
                    <p>Aucun device Zigbee trouvé</p>
                    <p class="text-xs mt-1">Synchronisez depuis Home Assistant ZHA</p>
                </div>`;
            return;
        }

        const tpl = document.getElementById('zigbee-device-row');
        container.innerHTML = '';

        devices.forEach(device => {
            const clone = tpl.content.cloneNode(true);
            const row   = clone.querySelector('[data-ieee]');

            row.dataset.ieee = device.ieee;

            row.querySelector('.device-name').textContent =
                device.name || device.ieee;
            row.querySelector('.device-meta').textContent =
                [device.manufacturer, device.model].filter(Boolean).join(' · ')
                || device.ieee;
            row.querySelector('.device-lqi').textContent =
                `LQI: ${device.lqi ?? '–'}`;

            const statusDot = row.querySelector('.device-status');
            statusDot.style.background = device.available
                ? 'var(--accent-success)'
                : 'var(--accent-danger)';
            statusDot.title = device.available ? 'En ligne' : 'Hors ligne';

            row.querySelector('.device-btn-reconfigure')
               .addEventListener('click', () => this.reconfigureDevice(device.ieee));

            row.querySelector('.device-btn-remove')
               .addEventListener('click', () => this.removeDevice(device.ieee, device.name));

            container.appendChild(clone);
        });
    },

    #renderTopology(topology) {
        const container = document.getElementById('network-topology');
        if (!container || !topology?.devices) {
            container.innerHTML = `<div class="text-center" style="color:var(--text-muted)">
                <p class="text-sm">Données topologie non disponibles depuis ZHA</p>
            </div>`;
            return;
        }

        // Rendu SVG simple des connexions
        const devices = topology.devices ?? [];
        const w = container.offsetWidth || 600;
        const h = 250;

        const svgNS = 'http://www.w3.org/2000/svg';
        const svg   = document.createElementNS(svgNS, 'svg');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', h);
        svg.style.color = 'var(--text-primary)';

        // Positions en cercle
        const cx = w / 2, cy = h / 2;
        const r  = Math.min(cx, cy) * 0.7;

        const positions = devices.map((_, i) => ({
            x: cx + r * Math.cos((i / devices.length) * 2 * Math.PI - Math.PI / 2),
            y: cy + r * Math.sin((i / devices.length) * 2 * Math.PI - Math.PI / 2),
        }));

        // Connexions
        (topology.links ?? []).forEach(link => {
            const src  = devices.findIndex(d => d.ieee === link.source);
            const dest = devices.findIndex(d => d.ieee === link.target);
            if (src < 0 || dest < 0) return;

            const line = document.createElementNS(svgNS, 'line');
            line.setAttribute('x1', positions[src].x);
            line.setAttribute('y1', positions[src].y);
            line.setAttribute('x2', positions[dest].x);
            line.setAttribute('y2', positions[dest].y);
            line.setAttribute('stroke', 'var(--glass-border-strong)');
            line.setAttribute('stroke-width', '1');
            svg.appendChild(line);
        });

        // Noeuds
        devices.forEach((device, i) => {
            const g = document.createElementNS(svgNS, 'g');
            g.style.cursor = 'pointer';

            const circle = document.createElementNS(svgNS, 'circle');
            circle.setAttribute('cx', positions[i].x);
            circle.setAttribute('cy', positions[i].y);
            circle.setAttribute('r', 12);
            circle.setAttribute('fill', device.available ? 'var(--accent-success)' : 'var(--accent-danger)');
            circle.setAttribute('opacity', '0.8');

            const label = document.createElementNS(svgNS, 'text');
            label.setAttribute('x', positions[i].x);
            label.setAttribute('y', positions[i].y + 24);
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('font-size', '9');
            label.setAttribute('fill', 'var(--text-muted)');
            label.textContent = (device.name ?? device.ieee).slice(0, 12);

            g.appendChild(circle);
            g.appendChild(label);
            g.title = `${device.name} (LQI: ${device.lqi ?? '–'})`;
            svg.appendChild(g);
        });

        container.innerHTML = '';
        container.appendChild(svg);
    },

    // ─── SSE ──────────────────────────────────────────────────────────────────

    #setupSSEListeners() {
        document.addEventListener('sse:device_action', (e) => {
            // Re-render si c'est un device zigbee
            const detail = e.detail;
            if (detail?.device_type === 'zigbee') {
                this.#renderStats(store.get('zigbee.stats'));
            }
        });
    },

    // ─── HTTP helper ─────────────────────────────────────────────────────────

    async #request(method, path, body = null) {
        const url = API_BASE + path;
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': API_KEY,
            },
        };
        if (body) opts.body = JSON.stringify(body);

        const res  = await fetch(url, opts);
        const data = await res.json();

        if (!res.ok) throw new Error(data.error ?? `HTTP ${res.status}`);
        return data;
    },
};

// Expose pour les onclick HTML
window.Zigbee = Zigbee;
