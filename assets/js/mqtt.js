/**
 * DomoGlass Pro - Client MQTT WebSocket (front-end)
 * Utilise MQTT.js (chargé via CDN)
 *
 * Se connecte au broker Mosquitto via WebSocket (port 9001 par défaut)
 * Écoute les topics de state et propage les changements dans le Store
 */

import { store } from './store.js';
import { UI }    from './ui.js';

const cfg  = window.DomoGlassConfig?.mqtt ?? {};

export const MqttClient = {
    client:     null,
    connected:  false,
    reconnects: 0,

    // ─── Connexion ────────────────────────────────────────────────────────────

    connect() {
        const wsUrl = `ws://${cfg.host}:${cfg.wsPort}/mqtt`;

        const options = {
            clientId:       'domoglass-ui-' + Math.random().toString(16).slice(2, 8),
            username:       cfg.username || undefined,
            password:       cfg.password || undefined,
            keepalive:      60,
            reconnectPeriod:3000,
            connectTimeout: 10_000,
            will: {
                topic:   cfg.topicBase + '/ui/status',
                payload: JSON.stringify({ status: 'offline' }),
                qos:     1,
                retain:  false,
            },
        };

        console.log('[MQTT] Connexion à', wsUrl);
        this.client = mqtt.connect(wsUrl, options);

        this.client.on('connect',     ()          => this.#onConnect());
        this.client.on('reconnect',   ()          => this.#onReconnect());
        this.client.on('disconnect',  ()          => this.#onDisconnect());
        this.client.on('error',       (err)       => this.#onError(err));
        this.client.on('message',     (t, payload)=> this.#onMessage(t, payload));
    },

    // ─── Événements de connexion ──────────────────────────────────────────────

    #onConnect() {
        this.connected = true;
        this.reconnects = 0;
        store.set('ui.mqttConnected', true);

        console.log('[MQTT] Connecté');
        UI.toast('MQTT connecté', 'success');
        UI.updateConnectionStatus(true);

        // Abonnements aux topics
        this.#subscribe();

        // Annonce la présence de l'UI
        this.publish(cfg.topicBase + '/ui/status',
            JSON.stringify({ status: 'online', ts: Date.now() })
        );
    },

    #onReconnect() {
        this.reconnects++;
        store.set('ui.mqttConnected', false);
        UI.updateConnectionStatus(false, `Reconnexion... (${this.reconnects})`);
    },

    #onDisconnect() {
        this.connected = false;
        store.set('ui.mqttConnected', false);
        UI.updateConnectionStatus(false, 'Déconnecté');
    },

    #onError(err) {
        console.error('[MQTT] Erreur:', err.message);
        UI.toast('Erreur MQTT : ' + err.message, 'error');
    },

    // ─── Abonnements ──────────────────────────────────────────────────────────

    #subscribe() {
        const topics = [
            cfg.topicBase + '/devices/+/state',        // État d'un device
            cfg.topicBase + '/devices/+/attributes',   // Attributs d'un device
            cfg.topicBase + '/energy/#',               // Données énergie
            cfg.topicBase + '/notifications',          // Notifications push
            'zigbee2mqtt/#',                           // Bridge Zigbee2MQTT (si présent)
        ];

        topics.forEach(t => {
            this.client.subscribe(t, { qos: 0 }, (err) => {
                if (err) console.warn('[MQTT] Subscribe failed:', t, err);
                else console.log('[MQTT] Abonné:', t);
            });
        });
    },

    // ─── Réception des messages ───────────────────────────────────────────────

    #onMessage(topic, payloadBuffer) {
        let payload;
        try {
            payload = JSON.parse(payloadBuffer.toString());
        } catch {
            payload = payloadBuffer.toString();
        }

        const parts = topic.split('/');

        // domoglass/devices/{id}/state
        if (parts[0] === cfg.topicBase && parts[1] === 'devices' && parts[3] === 'state') {
            const deviceId = parseInt(parts[2]);
            if (!isNaN(deviceId)) {
                store.setDeviceState(deviceId, payload?.state ?? payload);
                this.#refreshDeviceCard(deviceId);
            }
            return;
        }

        // domoglass/devices/{id}/attributes
        if (parts[0] === cfg.topicBase && parts[1] === 'devices' && parts[3] === 'attributes') {
            const deviceId = parseInt(parts[2]);
            if (!isNaN(deviceId)) {
                store.updateDevice(deviceId, { attributes: payload });
                this.#refreshDeviceCard(deviceId);
            }
            return;
        }

        // domoglass/energy/...
        if (parts[0] === cfg.topicBase && parts[1] === 'energy') {
            if (payload?.power_w !== undefined) {
                store.set('energy.current_kw', payload.power_w / 1000);
                document.getElementById('current-power')?.textContent &&
                    (document.getElementById('current-power').textContent =
                        (payload.power_w / 1000).toFixed(2));
            }
            return;
        }

        // domoglass/notifications
        if (topic === cfg.topicBase + '/notifications') {
            UI.toast(payload?.message ?? String(payload), payload?.type ?? 'info');
            return;
        }

        // zigbee2mqtt/{device}/...
        if (parts[0] === 'zigbee2mqtt' && parts.length >= 2) {
            this.#handleZigbee2MqttMessage(parts, payload);
            return;
        }
    },

    #handleZigbee2MqttMessage(parts, payload) {
        // Mise à jour via nom du device (on cherche par mqtt_topic)
        const deviceName  = parts[1];
        const messageType = parts[2]; // undefined = state, 'availability' = dispo

        const devices = store.get('devices') ?? [];
        const device  = devices.find(d =>
            d.mqtt_topic && d.mqtt_topic.includes(deviceName)
        );

        if (!device) return;

        if (messageType === 'availability') {
            store.updateDevice(device.id, { is_online: payload === 'online' ? 1 : 0 });
            return;
        }

        // Payload d'état Zigbee2MQTT
        if (payload?.state !== undefined) {
            store.setDeviceState(device.id,
                payload.state === 'ON' ? 'on' : 'off',
                {
                    brightness:  payload.brightness,
                    color_temp:  payload.color_temp,
                    temperature: payload.temperature,
                    humidity:    payload.humidity,
                }
            );
            this.#refreshDeviceCard(device.id);
        }
    },

    // ─── Publication ──────────────────────────────────────────────────────────

    publish(topic, payload, options = {}) {
        if (!this.connected) {
            console.warn('[MQTT] Non connecté, message en attente:', topic);
            return false;
        }

        const msg = typeof payload === 'string' ? payload : JSON.stringify(payload);
        this.client.publish(topic, msg, { qos: 0, retain: false, ...options });
        return true;
    },

    /**
     * Commande un device directement via MQTT
     */
    commandDevice(deviceId, action, params = {}) {
        const device = store.getDevice(deviceId);
        if (!device?.mqtt_cmd_topic) return false;

        const payload = this.#buildPayload(action, params);
        return this.publish(device.mqtt_cmd_topic, payload, { qos: 1 });
    },

    #buildPayload(action, params) {
        const map = {
            turn_on:  { state: 'ON', ...params },
            turn_off: { state: 'OFF' },
            toggle:   { state: 'TOGGLE' },
        };
        return map[action] ?? { [action]: params.value ?? true, ...params };
    },

    // ─── UI ───────────────────────────────────────────────────────────────────

    #refreshDeviceCard(deviceId) {
        // Déclenche un event DOM pour que les composants se mettent à jour
        document.dispatchEvent(new CustomEvent('device:updated', {
            detail: { id: deviceId, device: store.getDevice(deviceId) }
        }));
    },

    disconnect() {
        this.client?.end();
        this.connected = false;
    },

    isConnected() {
        return this.connected;
    },
};
