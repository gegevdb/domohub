/**
 * DomoGlass Pro - Point d'entrée principal
 * Orchestre l'initialisation de tous les modules
 */

import { store }      from './store.js';
import { UI, Nav }    from './ui.js';
import { MqttClient } from './mqtt.js';
import { Zigbee }     from './zigbee.js';
import { Charts }     from './charts.js';
import { Devices }    from './devices.js';

// ─── Initialisation au chargement du DOM ──────────────────────────────────────

document.addEventListener('DOMContentLoaded', async () => {

    console.log(`[DomoGlass] Démarrage v${window.DomoGlassConfig?.version ?? '?'}`);

    // 1. UI de base (thème, horloge, navigation)
    UI.init();
    UI.addLog('DomoGlass Pro démarré', 'info');

    // 2. Devices (rendu des cartes depuis les données PHP injectées)
    Devices.init();

    // 3. Graphiques énergie
    Charts.init();

    // 4. Connexion MQTT WebSocket
    try {
        MqttClient.connect();
        UI.addLog('Connexion MQTT en cours...', 'info');
    } catch (e) {
        UI.addLog('MQTT indisponible : ' + e.message, 'warning');
    }

    // 5. SSE pour les mises à jour serveur en temps réel
    initSSE();

    // 6. Zigbee (chargé à la demande, seulement si section visible)
    document.addEventListener('section:changed', async (e) => {
        if (e.detail?.section === 'zigbee') {
            await Zigbee.init();
        }
    });

    // 7. Simulation de la météo (à remplacer par une vraie API)
    fetchWeather();

    // 8. Simulation des capteurs MQTT (à désactiver en production)
    if (window.DomoGlassConfig?.appEnv === 'development') {
        startDevSimulation();
    }

    UI.addLog('Interface prête', 'success');
});

// ─── Server-Sent Events ───────────────────────────────────────────────────────

function initSSE() {
    const url = `${window.DomoGlassConfig?.apiBase ?? '/api'}/sse.php`;
    let evtSource;

    const connect = () => {
        evtSource = new EventSource(url);

        evtSource.addEventListener('connected', (e) => {
            const data = JSON.parse(e.data);
            UI.addLog(`SSE connecté - ${data.devices?.length ?? 0} devices`, 'success');
            store.set('ui.sseConnected', true);
        });

        evtSource.addEventListener('device_action', (e) => {
            const data = JSON.parse(e.data);

            // Met à jour le store
            store.updateDevice(data.device_id, {
                state:      data.state,
                attributes: data.attributes,
                last_seen:  data.ts,
            });

            // Re-render la carte du device
            Devices.refreshCard(data.device_id);

            // Log
            UI.addLog(`${data.device_name}: ${data.action}`, 'info');

            // Propage l'événement pour d'autres modules (Zigbee, etc.)
            document.dispatchEvent(new CustomEvent('sse:device_action', { detail: data }));
        });

        evtSource.addEventListener('notification', (e) => {
            const notif = JSON.parse(e.data);
            UI.pushNotification(notif);
            UI.toast(notif.message, notif.type);
        });

        evtSource.addEventListener('energy_update', (e) => {
            const data = JSON.parse(e.data);
            Charts.addEnergyDataPoint(data.power_w, data.logged_at);
            store.set('energy.current_kw', (data.power_w / 1000));
        });

        evtSource.addEventListener('devices_snapshot', (e) => {
            const data = JSON.parse(e.data);
            data.devices?.forEach(d => {
                store.updateDevice(d.id, {
                    state:     d.state,
                    is_online: d.is_online,
                    last_seen: d.last_seen,
                });
            });
            UI.updateStats();
        });

        evtSource.onerror = () => {
            store.set('ui.sseConnected', false);
            UI.addLog('SSE déconnecté, reconnexion...', 'warning');
        };
    };

    connect();

    // Reconnexion manuelle si l'EventSource échoue complètement
    setInterval(() => {
        if (evtSource.readyState === EventSource.CLOSED) {
            UI.addLog('Reconnexion SSE...', 'info');
            connect();
        }
    }, 10_000);
}

// ─── Météo (OpenMeteo - sans clé API) ────────────────────────────────────────

async function fetchWeather() {
    // Coordonnées par défaut : Grasse, France (à rendre configurable)
    const lat = 43.66, lon = 6.92;

    try {
        const res  = await fetch(
            `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m&wind_speed_unit=kmh`
        );
        const data = await res.json();
        const temp = data?.current?.temperature_2m;

        if (temp !== undefined) {
            const el = document.getElementById('weather-temp');
            if (el) el.textContent = Math.round(temp) + '°C';
        }
    } catch (_) {
        // Silencieux : la météo est optionnelle
    }
}

// ─── Simulation développement ─────────────────────────────────────────────────

function startDevSimulation() {
    console.log('[DomoGlass] Mode dev : simulation capteurs active');

    setInterval(() => {
        // Simule une fluctuation de puissance
        const kw = (1 + Math.random() * 0.8).toFixed(2);
        store.set('energy.current_kw', parseFloat(kw));

        const el = document.getElementById('current-power');
        if (el) el.textContent = kw;
    }, 3000);

    setInterval(() => {
        // Simule humidité
        const humidity = 40 + Math.floor(Math.random() * 15);
        const el  = document.getElementById('humidity-display');
        const bar = document.getElementById('humidity-bar');
        if (el)  el.textContent   = humidity + '%';
        if (bar) bar.style.width  = humidity + '%';
    }, 5000);
}

// ─── Expositions globales (compatibilité onclick HTML) ────────────────────────

window.UI      = UI;
window.Nav     = Nav;
window.Zigbee  = Zigbee;
window.Devices = Devices;
window.Charts  = Charts;
