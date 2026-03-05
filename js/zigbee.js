/**
 * DOMOGLASS PRO — Module Zigbee ZHA (côté client)
 * Gère le flux d'appairage, la liste des devices découverts,
 * et les statuts en temps réel via SSE.
 */

import { setZhaState, subscribe } from './store.js';
import { showToast } from './ui/notifications.js';

const API = '/api/zigbee_herdsman.php';
const CSRF = () => window.DOMOGLASS.csrfToken;

// ============================================================
//  État d'appairage
// ============================================================

let permitJoinTimer   = null;
let permitJoinSeconds = 0;

// ============================================================
//  API calls
// ============================================================

export async function fetchZigbeeStatus() {
    try {
        const res  = await fetch(`${API}?action=status`);
        const json = await res.json();
        if (json.success) {
            setZhaState({
                connected:   json.data.ha_reachable,
                permitJoin:  json.data.permit_join,
                totalDevices: json.data.total_devices,
                pairedDevices: json.data.paired_devices,
            });
            updateZhaIndicator(json.data.ha_reachable);
        }
        return json.data;
    } catch (e) {
        console.error('[Zigbee] fetchStatus:', e);
        setZhaState({ connected: false });
        updateZhaIndicator(false);
        return null;
    }
}

export async function fetchDiscoveredDevices() {
    const res  = await fetch(`${API}?action=devices`);
    const json = await res.json();
    return json.success ? json.data : [];
}

export async function syncDevices() {
    showToast('Synchronisation ZHA en cours…', 'info');
    try {
        const res  = await fetch(`${API}?action=sync`);
        const json = await res.json();
        if (json.success) {
            showToast(`${json.data.count} appareil(s) Zigbee synchronisé(s)`, 'success');
            return json.data.devices;
        }
        throw new Error(json.error);
    } catch (e) {
        showToast(`Erreur sync ZHA: ${e.message}`, 'error');
        return [];
    }
}

// ============================================================
//  Appairage
// ============================================================

export async function startPermitJoin(duration = 60, onTick = null) {
    try {
        const res  = await fetch(`${API}?action=permit_join&enable=true&seconds=${duration}`);
        const json = await res.json();

        if (!json.success) throw new Error(json.error ?? 'Échec');

        setZhaState({ permitJoin: true });
        showToast(`Appairage Zigbee ouvert (${duration}s)`, 'success');

        // Compte à rebours
        permitJoinSeconds = duration;
        clearInterval(permitJoinTimer);
        permitJoinTimer = setInterval(() => {
            permitJoinSeconds--;
            if (onTick) onTick(permitJoinSeconds);
            if (permitJoinSeconds <= 0) {
                clearInterval(permitJoinTimer);
                setZhaState({ permitJoin: false });
                showToast('Appairage Zigbee terminé', 'info');
                if (onTick) onTick(0);
            }
        }, 1000);

        return true;
    } catch (e) {
        showToast(`Erreur appairage: ${e.message}`, 'error');
        return false;
    }
}

export async function stopPermitJoin() {
    clearInterval(permitJoinTimer);
    permitJoinSeconds = 0;

    await fetch(`${API}?action=permit_join&enable=false`);

    setZhaState({ permitJoin: false });
    showToast('Appairage Zigbee fermé', 'info');
}

export async function pairDevice(ieee, options = {}) {
    try {
        const res  = await fetch(`${API}?action=pair`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF() },
            body:    JSON.stringify({ ieee, ...options }),
        });
        const json = await res.json();

        if (!json.success) throw new Error(json.error ?? 'Échec');

        showToast(`Appareil appairé avec succès !`, 'success');
        return json.data;
    } catch (e) {
        showToast(`Erreur appairage: ${e.message}`, 'error');
        return null;
    }
}

export async function removeDevice(ieee) {
    if (!confirm('Supprimer cet appareil du réseau Zigbee ?')) return false;

    try {
        const res  = await fetch(`${API}?action=remove`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF() },
            body:    JSON.stringify({ ieee }),
        });
        const json = await res.json();

        if (!json.success) throw new Error(json.error ?? 'Échec');
        showToast('Appareil supprimé du réseau Zigbee', 'success');
        return true;
    } catch (e) {
        showToast(`Erreur suppression: ${e.message}`, 'error');
        return false;
    }
}

// ============================================================
//  UI helpers
// ============================================================

function updateZhaIndicator(connected) {
    const dot = document.getElementById('zha-status-dot');
    if (dot) {
        dot.style.background = connected ? 'var(--status-online)' : 'var(--status-offline)';
    }
}

/**
 * Rendu de la carte d'un device Zigbee découvert.
 */
export function renderZigbeeDeviceCard(device) {
    const isPaired  = device.paired === 1;
    const lastSeen  = device.last_seen ? new Date(device.last_seen).toLocaleString('fr-FR') : 'Jamais';
    const typeIcon  = {
        light: 'fa-lightbulb', switch: 'fa-toggle-on', sensor: 'fa-chart-line',
        climate: 'fa-thermometer-half', coordinator: 'fa-broadcast-tower',
    }[device.device_type] ?? 'fa-plug';

    return `
    <div class="glass-card rounded-2xl p-4 flex flex-col gap-3" data-ieee="${device.ieee}">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center
                            ${isPaired ? 'bg-gradient-to-br from-green-400 to-teal-500' : 'bg-white/10'}">
                    <i class="fas ${typeIcon} text-sm ${isPaired ? 'text-white' : ''}"
                       style="${isPaired ? '' : 'color: var(--text-muted)'}"></i>
                </div>
                <div>
                    <div class="font-semibold text-sm" style="color: var(--text-primary)">
                        ${escHtml(device.name || device.ieee)}
                    </div>
                    <div class="text-xs" style="color: var(--text-muted)">
                        ${escHtml(device.manufacturer ?? '')} ${escHtml(device.model ?? '')}
                    </div>
                </div>
            </div>
            <span class="text-xs px-2 py-1 rounded-lg font-medium
                         ${isPaired ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400'}">
                ${isPaired ? '● Appairé' : '○ Découvert'}
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs" style="color: var(--text-muted)">
            <div><span class="font-mono">${device.ieee}</span></div>
            <div class="text-right">Vu: ${lastSeen}</div>
        </div>

        ${!isPaired ? `
        <button onclick="window.zigbeeUI.pairDevice('${device.ieee}')"
                class="w-full py-2 rounded-xl text-sm font-semibold transition
                       bg-gradient-to-r from-indigo-500 to-purple-600 text-white hover:opacity-90">
            <i class="fas fa-link mr-2"></i>Intégrer dans DomoGlass
        </button>` : `
        <button onclick="window.zigbeeUI.removeDevice('${device.ieee}')"
                class="w-full py-2 rounded-xl text-sm font-medium transition hover:opacity-80"
                style="background: rgba(239,68,68,0.1); color: var(--accent-danger)">
            <i class="fas fa-unlink mr-2"></i>Retirer du réseau
        </button>`}
    </div>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, c =>
        ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
