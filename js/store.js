/**
 * DOMOGLASS PRO — Store d'état centralisé
 * Pattern pub/sub léger, sans dépendance externe.
 */

const state = {
    devices:      [],
    rooms:        [],
    notifications:[],
    energy:       { current: 0, history: [] },
    mqtt:         { connected: false, broker: '' },
    zha:          { connected: false, permitJoin: false },
    ui: {
        activeRoom:  'all',
        activePage:  'dashboard',
        palette:     window.DOMOGLASS?.palette ?? 'midnight',
        sidebarOpen: false,
    },
};

const palettes = ['midnight', 'ocean', 'purple', 'rose', 'emerald', 'catppuccin', 'light'];

const listeners = new Map(); // event → Set<callback>

/**
 * Souscrire à un événement de store.
 * @returns {Function} unsubscribe
 */
export function subscribe(event, callback) {
    if (!listeners.has(event)) listeners.set(event, new Set());
    listeners.get(event).add(callback);
    return () => listeners.get(event)?.delete(callback);
}

/**
 * Émettre un événement avec données.
 */
function emit(event, data) {
    listeners.get(event)?.forEach(cb => {
        try { cb(data); } catch (e) { console.error(`[Store] ${event}:`, e); }
    });
    listeners.get('*')?.forEach(cb => {
        try { cb({ event, data }); } catch (e) {}
    });
}

// ============================================================
//  Getters
// ============================================================

export const getDevices    = () => [...state.devices];
export const getRooms      = () => [...state.rooms];
export const getNotifs     = () => [...state.notifications];
export const getEnergy     = () => ({ ...state.energy });
export const getMqttState  = () => ({ ...state.mqtt });
export const getZhaState   = () => ({ ...state.zha });
export const getUi         = () => ({ ...state.ui });

export function getDevice(id) {
    return state.devices.find(d => d.id === id) ?? null;
}

export function getDevicesByRoom(room) {
    if (room === 'all') return [...state.devices];
    return state.devices.filter(d => d.room_slug === room);
}

// ============================================================
//  Setters / Mutateurs
// ============================================================

export function setDevices(devices) {
    state.devices = devices;
    emit('devices:updated', state.devices);
}

export function updateDevice(id, patch) {
    const idx = state.devices.findIndex(d => d.id === id);
    if (idx !== -1) {
        state.devices[idx] = { ...state.devices[idx], ...patch };
        emit('device:updated', state.devices[idx]);
    }
}

export function setRooms(rooms) {
    state.rooms = rooms;
    emit('rooms:updated', state.rooms);
}

export function addNotification(notif) {
    state.notifications.unshift(notif);
    if (state.notifications.length > 50) state.notifications.pop();
    emit('notification:new', notif);
}

export function markNotificationsRead() {
    state.notifications.forEach(n => n.read = 1);
    emit('notifications:read', null);
}

export function setEnergyData(data) {
    state.energy = { ...state.energy, ...data };
    emit('energy:updated', state.energy);
}

export function setMqttState(patch) {
    state.mqtt = { ...state.mqtt, ...patch };
    emit('mqtt:status', state.mqtt);
}

export function setZhaState(patch) {
    state.zha = { ...state.zha, ...patch };
    emit('zha:status', state.zha);
}

export function setUi(patch) {
    state.ui = { ...state.ui, ...patch };
    emit('ui:updated', state.ui);
}

export function setActiveRoom(room) {
    state.ui.activeRoom = room;
    emit('ui:room_changed', room);
}

export function setPalette(palette) {
    state.ui.palette = palette;
    document.body.dataset.palette = palette;
    localStorage.setItem('dg_palette', palette);
    document.body.classList.remove('no-transition');
    emit('ui:palette_changed', palette);

    // Dispatch window event for external listeners
    window.dispatchEvent(new CustomEvent('paletteChanged', { detail: palette }));
}

// ============================================================
//  Exposition globale pour compatibilité
// ============================================================

window.setPalette = setPalette;
