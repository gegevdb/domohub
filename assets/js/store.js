/**
 * DomoGlass Pro - Store d'état centralisé
 * Pattern Observer : les composants s'abonnent aux changements d'état
 */

class Store {
    #state = {};
    #subscribers = new Map();

    constructor(initialState = {}) {
        this.#state = structuredClone(initialState);
    }

    // ─── Lecture ──────────────────────────────────────────────────────────────

    get(path) {
        if (!path) return structuredClone(this.#state);
        return path.split('.').reduce((obj, key) =>
            obj !== undefined ? obj[key] : undefined,
            this.#state
        );
    }

    // ─── Écriture ─────────────────────────────────────────────────────────────

    set(path, value) {
        const keys  = path.split('.');
        const last  = keys.pop();
        const oldVal = this.get(path);

        // Navigation jusqu'au parent
        const target = keys.reduce((obj, key) => {
            if (obj[key] === undefined || typeof obj[key] !== 'object') {
                obj[key] = {};
            }
            return obj[key];
        }, this.#state);

        target[last] = value;

        // Notifie les abonnés si la valeur a changé
        if (JSON.stringify(oldVal) !== JSON.stringify(value)) {
            this.#notify(path, value, oldVal);
            this.#notify('*', { path, value, oldVal });
        }
    }

    update(path, updater) {
        const current = this.get(path);
        this.set(path, updater(structuredClone(current)));
    }

    // ─── Subscriptions ────────────────────────────────────────────────────────

    subscribe(path, callback) {
        if (!this.#subscribers.has(path)) {
            this.#subscribers.set(path, new Set());
        }
        this.#subscribers.get(path).add(callback);

        // Retourne la fonction de désabonnement
        return () => this.#subscribers.get(path)?.delete(callback);
    }

    #notify(path, newVal, oldVal) {
        this.#subscribers.get(path)?.forEach(cb => {
            try { cb(newVal, oldVal); }
            catch (e) { console.error(`[Store] Erreur subscriber "${path}":`, e); }
        });
    }

    // ─── Devices ──────────────────────────────────────────────────────────────

    getDevice(id) {
        return this.get('devices')?.find(d => d.id === id);
    }

    updateDevice(id, updates) {
        this.update('devices', devices =>
            devices.map(d => d.id === id ? { ...d, ...updates } : d)
        );
    }

    setDeviceState(id, state, attributes = {}) {
        this.updateDevice(id, {
            state,
            attributes: {
                ...(this.getDevice(id)?.attributes ?? {}),
                ...attributes,
            },
            last_seen: new Date().toISOString(),
        });
    }

    getDevicesByRoom(roomId) {
        const all = this.get('devices') ?? [];
        return roomId === null ? all : all.filter(d => d.room_id === roomId);
    }

    // ─── Stats ────────────────────────────────────────────────────────────────

    computeStats() {
        const devices = this.get('devices') ?? [];
        return {
            total:   devices.length,
            online:  devices.filter(d => d.is_online).length,
            active:  devices.filter(d => d.state === 'on').length,
            offline: devices.filter(d => !d.is_online).length,
        };
    }

    // ─── Persistance légère (localStorage pour les préférences UI) ────────────

    savePrefs() {
        const prefs = {
            palette:      this.get('ui.palette'),
            activeRoom:   this.get('ui.activeRoom'),
            activeSection:this.get('ui.activeSection'),
        };
        try {
            localStorage.setItem('domoglass_prefs', JSON.stringify(prefs));
        } catch (_) {}
    }

    loadPrefs() {
        try {
            const saved = localStorage.getItem('domoglass_prefs');
            if (saved) {
                const prefs = JSON.parse(saved);
                this.set('ui.palette',       prefs.palette       ?? 'midnight');
                this.set('ui.activeRoom',    prefs.activeRoom    ?? null);
                this.set('ui.activeSection', prefs.activeSection ?? 'dashboard');
            }
        } catch (_) {}
    }

    // ─── Debug ────────────────────────────────────────────────────────────────

    dump() {
        console.table(this.computeStats());
        console.log('[Store] État complet:', structuredClone(this.#state));
    }
}

// Instance singleton exportée
const cfg = window.DomoGlassConfig ?? {};

export const store = new Store({
    devices:   cfg.devices ?? [],
    rooms:     cfg.rooms   ?? [],
    stats:     cfg.stats   ?? { total: 0, online: 0, active: 0 },
    ui: {
        palette:        'midnight',
        activeSection:  'dashboard',
        activeRoom:     null,
        mqttConnected:  false,
        sseConnected:   false,
        pairingActive:  false,
        pairingSeconds: 0,
    },
    notifications: [],
    logs: [],
    energy: {
        current_kw: 0,
        history: [],
    },
    zigbee: {
        devices:  [],
        stats:    { total: 0, online: 0, offline: 0, avg_lqi: 0 },
        topology: null,
    },
});
