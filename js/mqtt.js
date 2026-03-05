/**
 * DOMOGLASS PRO — Client MQTT WebSocket (Paho)
 * Connexion au broker Mosquitto via WebSocket (port 9001).
 * Paho est chargé via CDN dans header.php.
 */

import { setMqttState, updateDevice, subscribe } from './store.js';
import { showToast } from './ui/notifications.js';

const RECONNECT_DELAY_MS  = 5000;
const MAX_RECONNECT_TRIES = 10;

let client           = null;
let reconnectTimer   = null;
let reconnectTries   = 0;
let subscriptions    = new Map(); // topic → Set<callback>
let isIntentionalDisconnect = false;

// ============================================================
//  Connexion
// ============================================================

export async function mqttConnect() {
    // Attendre que Paho soit chargé
    if (typeof Paho === 'undefined') {
        await loadPaho();
    }

    const cfg = window.DOMOGLASS;
    const clientId = `domoglass-ui-${Math.random().toString(36).substr(2, 8)}`;

    client = new Paho.MQTT.Client(cfg.mqttHost, Number(cfg.mqttPortWs), '/', clientId);

    client.onConnectionLost = onConnectionLost;
    client.onMessageArrived = onMessageArrived;

    const connectOptions = {
        timeout:        10,
        keepAliveInterval: 30,
        cleanSession:   true,
        useSSL:         cfg.haSSL ?? false,
        onSuccess:      onConnectSuccess,
        onFailure:      onConnectFailure,
    };

    if (cfg.mqttUser) {
        connectOptions.userName = cfg.mqttUser;
        connectOptions.password = cfg.mqttPass ?? '';
    }

    try {
        client.connect(connectOptions);
    } catch (e) {
        console.error('[MQTT] Erreur connexion:', e);
        scheduleReconnect();
    }
}

export function mqttDisconnect() {
    isIntentionalDisconnect = true;
    clearTimeout(reconnectTimer);
    if (client?.isConnected()) {
        client.disconnect();
    }
    setMqttState({ connected: false });
}

// ============================================================
//  Publication
// ============================================================

export function mqttPublish(topic, payload, qos = 0, retain = false) {
    if (!client?.isConnected()) {
        console.warn('[MQTT] Non connecté, message ignoré:', topic);
        return false;
    }

    const message = new Paho.MQTT.Message(
        typeof payload === 'object' ? JSON.stringify(payload) : String(payload)
    );
    message.destinationName = topic;
    message.qos             = qos;
    message.retained        = retain;

    try {
        client.send(message);
        return true;
    } catch (e) {
        console.error('[MQTT] Erreur publish:', e);
        return false;
    }
}

// ============================================================
//  Souscriptions
// ============================================================

export function mqttSubscribe(topic, callback) {
    if (!subscriptions.has(topic)) {
        subscriptions.set(topic, new Set());
        if (client?.isConnected()) {
            client.subscribe(topic, { qos: 0 });
        }
    }
    subscriptions.get(topic).add(callback);

    return () => {
        subscriptions.get(topic)?.delete(callback);
        if (subscriptions.get(topic)?.size === 0) {
            subscriptions.delete(topic);
            if (client?.isConnected()) {
                client.unsubscribe(topic);
            }
        }
    };
}

/**
 * S'abonner automatiquement à tous les topics des devices.
 */
export function subscribeToDevices(devices) {
    devices.forEach(device => {
        if (device.mqtt_topic_state) {
            mqttSubscribe(device.mqtt_topic_state, (topic, payload) => {
                handleDeviceMessage(device.id, payload);
            });
        }
    });
}

// ============================================================
//  Commande device (helper haut niveau)
// ============================================================

export function sendDeviceCommand(device, payload) {
    // Priorité MQTT direct
    if (device.mqtt_topic_set) {
        return mqttPublish(device.mqtt_topic_set, payload);
    }
    // Fallback API PHP (Zigbee ZHA)
    return sendCommandViaApi(device.id, payload);
}

async function sendCommandViaApi(deviceId, payload) {
    try {
        const res = await fetch(`/api/devices.php?action=command&id=${deviceId}`, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'X-CSRF-Token':  window.DOMOGLASS.csrfToken,
            },
            body: JSON.stringify({ payload }),
        });
        return (await res.json()).success;
    } catch (e) {
        console.error('[MQTT] sendCommandViaApi:', e);
        return false;
    }
}

// ============================================================
//  Handlers internes
// ============================================================

function onConnectSuccess() {
    reconnectTries = 0;
    isIntentionalDisconnect = false;
    setMqttState({ connected: true, broker: `${window.DOMOGLASS.mqttHost}:${window.DOMOGLASS.mqttPortWs}` });

    // Réabonnement de toutes les souscriptions
    subscriptions.forEach((_, topic) => {
        client.subscribe(topic, { qos: 0 });
    });

    updateStatusIndicator(true);
    console.info('[MQTT] Connecté au broker');
}

function onConnectFailure(err) {
    console.warn('[MQTT] Échec connexion:', err.errorMessage);
    setMqttState({ connected: false });
    updateStatusIndicator(false);
    if (!isIntentionalDisconnect) scheduleReconnect();
}

function onConnectionLost(response) {
    setMqttState({ connected: false });
    updateStatusIndicator(false);
    if (response.errorCode !== 0) {
        console.warn('[MQTT] Connexion perdue:', response.errorMessage);
        if (!isIntentionalDisconnect) scheduleReconnect();
    }
}

function onMessageArrived(message) {
    const topic   = message.destinationName;
    const payload = tryParseJson(message.payloadString);

    // Dispatcher vers les souscriptions correspondantes
    subscriptions.forEach((callbacks, pattern) => {
        if (topicMatches(pattern, topic)) {
            callbacks.forEach(cb => {
                try { cb(topic, payload); } catch (e) { console.error('[MQTT] callback error:', e); }
            });
        }
    });
}

function handleDeviceMessage(deviceId, payload) {
    if (!payload) return;

    const patch = { last_seen: new Date().toISOString() };

    if (typeof payload.state === 'string') {
        patch.state = payload.state.toLowerCase();
    }
    if (payload.brightness !== undefined) {
        patch.brightness = payload.brightness;
    }
    if (payload.temperature !== undefined) {
        patch.temperature = payload.temperature;
    }
    if (payload.linkquality !== undefined) {
        patch.linkquality = payload.linkquality;
    }

    updateDevice(deviceId, { state: patch.state, state_data: payload });
}

function scheduleReconnect() {
    if (reconnectTries >= MAX_RECONNECT_TRIES) {
        console.error('[MQTT] Nombre maximal de tentatives atteint');
        showToast('MQTT: Impossible de se connecter au broker', 'error');
        return;
    }
    reconnectTries++;
    const delay = RECONNECT_DELAY_MS * Math.min(reconnectTries, 5);
    console.info(`[MQTT] Reconnexion dans ${delay / 1000}s (essai ${reconnectTries})`);
    reconnectTimer = setTimeout(mqttConnect, delay);
}

// ============================================================
//  Utils
// ============================================================

function updateStatusIndicator(connected) {
    const dot  = document.getElementById('mqtt-status-dot');
    const text = document.getElementById('mqtt-status-text');
    if (dot) {
        dot.style.background = connected ? 'var(--status-online)' : 'var(--status-offline)';
    }
    if (text) {
        text.textContent = connected ? 'MQTT ✓' : 'MQTT ✗';
    }
}

function topicMatches(pattern, topic) {
    if (pattern === topic) return true;
    const patternParts = pattern.split('/');
    const topicParts   = topic.split('/');

    for (let i = 0; i < patternParts.length; i++) {
        if (patternParts[i] === '#') return true;
        if (patternParts[i] === '+') continue;
        if (patternParts[i] !== topicParts[i]) return false;
    }
    return patternParts.length === topicParts.length;
}

function tryParseJson(str) {
    try { return JSON.parse(str); } catch { return str; }
}

async function loadPaho() {
    return new Promise((resolve, reject) => {
        if (typeof Paho !== 'undefined') { resolve(); return; }
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.1.0/paho-mqtt.min.js';
        script.onload  = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}
