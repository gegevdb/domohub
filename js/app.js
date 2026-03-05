/**
 * DOMOGLASS PRO — Bootstrap principal
 * Point d'entrée ES module chargé par footer.php
 */

import { setDevices, setRooms, setPalette, subscribe } from './store.js';
import { mqttConnect, subscribeToDevices }             from './mqtt.js';
import { fetchZigbeeStatus }                           from './zigbee.js';
import { showToast, initNotifications }                from './ui/notifications.js';

// ============================================================
//  Initialisation au chargement DOM
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {

    console.info(`[DomoGlass] v${window.DOMOGLASS.version} — démarrage`);

    // 1. Appliquer la palette sauvegardée
    const savedPalette = localStorage.getItem('dg_palette') ?? window.DOMOGLASS.palette;
    setPalette(savedPalette);

    // 2. Charger les données initiales (rooms + devices)
    await Promise.all([loadRooms(), loadDevices()]);

    // 3. Connecter MQTT
    mqttConnect();

    // 4. Vérifier état ZHA
    fetchZigbeeStatus();

    // 5. Initialiser les notifications
    initNotifications();

    // 6. Démarrer SSE (push serveur)
    initSSE();

    // 7. Initialiser les UI globaux
    initGlobalUI();

    // 8. Sélecteur de palette
    initPaletteSelector();

    // 9. Mode sombre automatique
    initAutoDarkMode();

    console.info('[DomoGlass] Initialisation terminée');
});

// ============================================================
//  Chargement données
// ============================================================

async function loadRooms() {
    try {
        const res  = await fetch('/api/rooms.php');
        const json = await res.json();
        if (json.success) setRooms(json.data);
    } catch (e) {
        console.warn('[App] loadRooms:', e);
    }
}

async function loadDevices() {
    try {
        const res     = await fetch('/api/devices.php');
        const json    = await res.json();
        if (json.success) {
            setDevices(json.data);
            subscribeToDevices(json.data);
        }
    } catch (e) {
        console.warn('[App] loadDevices:', e);
    }
}

// ============================================================
//  Server-Sent Events
// ============================================================

function initSSE() {
    if (!window.EventSource) {
        console.warn('[SSE] Non supporté par ce navigateur');
        return;
    }

    let sse;
    let retryCount = 0;

    function connect() {
        sse = new EventSource('/api/sse.php');

        sse.addEventListener('connected', () => {
            retryCount = 0;
            console.info('[SSE] Connecté');
        });

        sse.addEventListener('notification', e => {
            try {
                const notif = JSON.parse(e.data);
                // Dispatch vers le store
                import('./store.js').then(({ addNotification }) => addNotification(notif));
            } catch {}
        });

        sse.addEventListener('device_states', e => {
            try {
                const devices = JSON.parse(e.data);
                import('./store.js').then(({ updateDevice }) => {
                    devices.forEach(d => updateDevice(d.id, { state: d.state, last_seen: d.last_seen }));
                });
            } catch {}
        });

        sse.addEventListener('energy', e => {
            try {
                const data = JSON.parse(e.data);
                import('./store.js').then(({ setEnergyData }) => setEnergyData({ live: data }));
            } catch {}
        });

        sse.addEventListener('reconnect', () => {
            sse.close();
            setTimeout(connect, 1000);
        });

        sse.onerror = () => {
            sse.close();
            retryCount++;
            const delay = Math.min(retryCount * 2000, 30000);
            setTimeout(connect, delay);
        };
    }

    connect();
}

// ============================================================
//  UI globale
// ============================================================

function initGlobalUI() {
    // Menu mobile
    window.toggleMobileMenu = function () {
        const menu = document.getElementById('mobile-menu');
        const btn  = document.getElementById('mobile-menu-btn');
        if (!menu) return;
        menu.classList.toggle('hidden');
        btn?.setAttribute('aria-expanded', (!menu.classList.contains('hidden')).toString());
    };

    // Fermer panels au clic extérieur
    document.addEventListener('click', e => {
        // Notifications
        const panel = document.getElementById('notification-panel');
        const bell  = e.target.closest('[aria-label="Notifications"]');
        if (panel && !panel.contains(e.target) && !bell) {
            panel.classList.add('hidden');
        }
    });

    // Écouter les mises à jour de store pour l'UI
    subscribe('mqtt:status', ({ connected }) => {
        const dot  = document.getElementById('mqtt-status-dot');
        const text = document.getElementById('mqtt-status-text');
        if (dot)  dot.style.background  = connected ? 'var(--status-online)' : 'var(--status-offline)';
        if (text) text.textContent = connected ? 'MQTT ✓' : 'MQTT ✗';
    });
}

// ============================================================
//  Sélecteur de palette
// ============================================================

function initPaletteSelector() {
    document.querySelectorAll('.palette-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const palette = btn.dataset.palette;
            if (!palette) return;
            setPalette(palette);
            document.querySelectorAll('.palette-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Persister côté serveur
            fetch('/api/settings.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.DOMOGLASS.csrfToken },
                body:    JSON.stringify({ key: 'theme_palette', value: palette }),
            }).then(response => {
                if (!response.ok) {
                    console.error('Failed to save theme to server:', response.status);
                    showToast('Erreur lors de la sauvegarde du thème', 'error');
                }
            }).catch(error => {
                console.error('Error saving theme:', error);
                showToast('Erreur réseau lors de la sauvegarde du thème', 'error');
            });
        });
    });

    // Marquer le bouton actif au chargement
    const current = document.body.dataset.palette || window.DOMOGLASS.palette;
    document.querySelector(`.palette-btn[data-palette="${current}"]`)?.classList.add('active');
}

// ============================================================
//  Mode sombre automatique
// ============================================================

async function initAutoDarkMode() {
    try {
        // Load auto dark mode settings
        const [autoModeRes, timeRes] = await Promise.all([
            fetch('/api/settings.php?key=auto_dark_mode'),
            fetch('/api/settings.php?key=dark_mode_time')
        ]);

        const autoMode = await autoModeRes.json();
        const timeSetting = await timeRes.json();

        const autoModeValue = autoMode?.data?.value ?? null;
        const timeValue = timeSetting?.data?.value ?? null;

        if (!autoMode.success || autoModeValue === 'disabled' || autoModeValue === null || autoModeValue === '') {
            return; // Auto dark mode disabled
        }

        const switchTime = timeSetting.success && timeValue ? timeValue : '20:00';
        const [hours, minutes] = switchTime.split(':').map(Number);

        // Check if we should switch to dark mode
        const shouldBeDark = shouldSwitchToDark(autoModeValue, hours, minutes);

        if (shouldBeDark) {
            const currentPalette = document.body.dataset.palette;
            // Switch to dark theme if not already dark
            if (!isDarkTheme(currentPalette)) {
                // Use the darkest theme available (midnight)
                setPalette('midnight');
            }
        }

        // Set up daily check
        scheduleNextCheck(hours, minutes);

    } catch (e) {
        console.warn('[AutoDarkMode] Failed to initialize:', e);
    }
}

function shouldSwitchToDark(mode, switchHour, switchMinute) {
    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();

    if (mode === 'schedule') {
        // Switch at specified time
        const currentTime = currentHour * 60 + currentMinute;
        const switchTime = switchHour * 60 + switchMinute;
        return currentTime >= switchTime;
    } else if (mode === 'sunset') {
        // Simple sunset approximation (6 PM)
        return currentHour >= 18;
    }

    return false;
}

function isDarkTheme(palette) {
    // Consider midnight, ocean, purple, rose, emerald as dark themes
    // Light is the only light theme
    return palette !== 'light';
}

function scheduleNextCheck(switchHour, switchMinute) {
    // Calculate time until next check (every hour)
    const now = new Date();
    const nextCheck = new Date(now);
    nextCheck.setHours(nextCheck.getHours() + 1, 0, 0, 0);

    const timeUntilNext = nextCheck.getTime() - now.getTime();

    setTimeout(() => {
        initAutoDarkMode(); // Re-check settings and switch if needed
    }, timeUntilNext);
}
