/**
 * DomoGlass Pro - Module Devices
 * Rendu et interaction avec les cartes d'appareils
 */

import { store }      from './store.js';
import { MqttClient } from './mqtt.js';
import { UI }         from './ui.js';

const API_BASE = window.DomoGlassConfig?.apiBase ?? '/api';
const API_KEY  = window.DomoGlassConfig?.apiKey  ?? '';

export const Devices = {

    // ─── Initialisation ───────────────────────────────────────────────────────

    init() {
        this.#bindDeviceEvents();

        // Écoute les mises à jour de devices depuis MQTT/SSE
        document.addEventListener('device:updated', (e) => {
            this.refreshCard(e.detail.id);
        });

        // Met à jour le compteur de devices actifs
        store.subscribe('devices', () => {
            this.updateActiveCount();
        });
    },

    // ─── Commandes ────────────────────────────────────────────────────────────

    async toggle(deviceId, element) {
        const device = store.getDevice(deviceId);
        if (!device) return;

        const newState = device.state === 'on' ? 'off' : 'on';
        const action   = newState === 'on' ? 'turn_on' : 'turn_off';

        // Optimistic update (UI immédiate)
        store.setDeviceState(deviceId, newState);
        this.#updateToggleUI(element, newState === 'on');

        // Envoi commande
        const success = await this.#sendCommand(deviceId, action);

        if (!success) {
            // Rollback si erreur
            store.setDeviceState(deviceId, device.state);
            this.#updateToggleUI(element, device.state === 'on');
            UI.toast(`Erreur commande sur ${device.name}`, 'error');
        } else {
            UI.addLog(`${device.name}: ${action.replace('_', ' ')}`, 'info');
        }
    },

    async setBrightness(deviceId, value) {
        const device = store.getDevice(deviceId);
        if (!device) return;

        store.setDeviceState(deviceId, 'on', { brightness: parseInt(value) });

        await this.#sendCommand(deviceId, 'turn_on', {
            brightness: parseInt(value),
        });
    },

    async setColorTemp(deviceId, value) {
        await this.#sendCommand(deviceId, 'turn_on', {
            color_temp: parseInt(value),
        });
        store.setDeviceState(deviceId, 'on', { color_temp: parseInt(value) });
    },

    async quickAction(deviceId, action, params = {}) {
        const device = store.getDevice(deviceId);
        if (!device) return;

        const success = await this.#sendCommand(deviceId, action, params);
        if (success) {
            UI.addLog(`${device.name}: ${action}`, 'info');
        }
    },

    // ─── Rendu / mise à jour des cartes ──────────────────────────────────────

    refreshCard(deviceId) {
        const device  = store.getDevice(deviceId);
        if (!device) return;

        // Toggle switch
        const toggle = document.querySelector(`[data-device-toggle="${deviceId}"]`);
        if (toggle) {
            this.#updateToggleUI(toggle, device.state === 'on');
        }

        // État texte
        const stateEl = document.querySelector(`[data-device-state="${deviceId}"]`);
        if (stateEl) {
            stateEl.textContent = device.state === 'on' ? 'Allumé' : 'Éteint';
            stateEl.style.color = device.state === 'on'
                ? 'var(--accent-success)'
                : 'var(--text-muted)';
        }

        // Indicateur online
        const onlineEl = document.querySelector(`[data-device-online="${deviceId}"]`);
        if (onlineEl) {
            onlineEl.style.background = device.is_online
                ? 'var(--status-online)'
                : 'var(--status-offline)';
        }

        // Luminosité
        const brightnessEl = document.querySelector(`[data-device-brightness="${deviceId}"]`);
        if (brightnessEl && device.attributes?.brightness !== undefined) {
            brightnessEl.value = device.attributes.brightness;
        }

        // Icône device ON/OFF
        const icon = document.querySelector(`.device-card[data-device-id="${deviceId}"] .device-icon`);
        if (icon) {
            icon.style.color      = device.state === 'on' ? 'var(--accent-warning)' : '';
            icon.style.textShadow = device.state === 'on'
                ? '0 0 20px var(--accent-warning)'
                : '';
        }

        this.updateActiveCount();
    },

    updateActiveCount() {
        const count = store.computeStats().active;
        const el    = document.getElementById('active-devices');
        if (el) el.textContent = count;
    },

    // ─── Binding des événements sur les cartes ────────────────────────────────

    #bindDeviceEvents() {
        // Délégation d'événements sur les toggles
        document.addEventListener('click', (e) => {
            const toggle = e.target.closest('[data-device-toggle]');
            if (toggle) {
                const id = parseInt(toggle.dataset.deviceToggle);
                this.toggle(id, toggle);
            }
        });

        // Délégation sur les sliders
        document.addEventListener('input', (e) => {
            const slider = e.target.closest('[data-device-brightness]');
            if (slider) {
                const id = parseInt(slider.dataset.deviceBrightness);
                this.setBrightness(id, e.target.value);
            }
        });
    },

    // ─── Envoi de commande ────────────────────────────────────────────────────

    async #sendCommand(deviceId, action, params = {}) {
        const device = store.getDevice(deviceId);
        if (!device) return false;

        // 1. Essai via MQTT si le device a un topic de commande et MQTT est connecté
        if (device.mqtt_cmd_topic && MqttClient.isConnected()) {
            return MqttClient.commandDevice(deviceId, action, params);
        }

        // 2. Fallback HTTP API
        try {
            const res = await fetch(`${API_BASE}/devices.php?action=command&id=${deviceId}`, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key':    API_KEY,
                },
                body: JSON.stringify({ action, params }),
            });

            const data = await res.json();
            return data.success ?? false;

        } catch (e) {
            console.error('[Devices] Erreur commande:', e);
            return false;
        }
    },

    #updateToggleUI(element, isActive) {
        if (!element) return;
        element.classList.toggle('active', isActive);

        // Met à jour aria-checked pour l'accessibilité
        element.setAttribute('aria-checked', isActive ? 'true' : 'false');
    },
};
