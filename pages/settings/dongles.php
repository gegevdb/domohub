<?php
/**
 * DOMOGLASS PRO — Configuration des dongles USB
 */
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Vérification admin
authRequireAdmin();

$pageTitle = 'Dongles USB';
$activePage = 'settings';

// Récupération des configurations existantes
$skyconnectConfig = json_decode(db()->getConfig('skyconnect_config', '{}'), true) ?: [];
$wifiDongleConfig = json_decode(db()->getConfig('wifi_dongle_config', '{}'), true) ?: [];

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- En-tête -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
            <i class="fas fa-usb mr-3" style="color: var(--accent-primary)"></i>
            Dongles USB
        </h1>
        <p class="text-sm mt-1" style="color: var(--text-muted)">
            Configuration des dongles HomeAssistant SkyConnect et WiFi
        </p>
    </div>

    <!-- HomeAssistant SkyConnect -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white">
                    <i class="fas fa-satellite-dish"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold" style="color: var(--text-primary)">HomeAssistant SkyConnect</h2>
                    <p class="text-sm" style="color: var(--text-muted)">Dongle Zigbee/Thread/Matter</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full <?= $skyconnectConfig['enabled'] ?? false ? 'bg-green-500' : 'bg-gray-400' ?>"></div>
                <span class="text-sm" style="color: var(--text-muted)">
                    <?= $skyconnectConfig['enabled'] ?? false ? 'Actif' : 'Inactif' ?>
                </span>
            </div>
        </div>

        <form id="skyconnect-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Activer SkyConnect</label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="enabled" value="1" 
                               class="w-5 h-5 rounded border-border-color bg-transparent text-primary"
                               <?= ($skyconnectConfig['enabled'] ?? false) ? 'checked' : '' ?>>
                        <span class="text-sm" style="color: var(--text-secondary)">Utiliser ce dongle</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mode de fonctionnement</label>
                    <select name="mode" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="zigbee" <?= ($skyconnectConfig['mode'] ?? 'zigbee') === 'zigbee' ? 'selected' : '' ?>>Zigbee</option>
                        <option value="thread" <?= ($skyconnectConfig['mode'] ?? '') === 'thread' ? 'selected' : '' ?>>Thread</option>
                        <option value="matter" <?= ($skyconnectConfig['mode'] ?? '') === 'matter' ? 'selected' : '' ?>>Matter</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port série</label>
                    <input type="text" name="serial_port" placeholder="ex: /dev/ttyUSB0" 
                           value="<?= htmlspecialchars($skyconnectConfig['serial_port'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Intégration Zigbee2MQTT</label>
                    <select name="zigbee2mqtt_integration" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="none" <?= ($skyconnectConfig['zigbee2mqtt_integration'] ?? 'none') === 'none' ? 'selected' : '' ?>>Aucune</option>
                        <option value="local" <?= ($skyconnectConfig['zigbee2mqtt_integration'] ?? '') === 'local' ? 'selected' : '' ?>>Local</option>
                        <option value="docker" <?= ($skyconnectConfig['zigbee2mqtt_integration'] ?? '') === 'docker' ? 'selected' : '' ?>>Docker</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Réseau Zigbee</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="network_pan_id" placeholder="PAN ID" 
                           value="<?= htmlspecialchars($skyconnectConfig['network_pan_id'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <input type="text" name="network_ext_pan_id" placeholder="Extended PAN ID" 
                           value="<?= htmlspecialchars($skyconnectConfig['network_ext_pan_id'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <input type="text" name="network_key" placeholder="Clé réseau" 
                           value="<?= htmlspecialchars($skyconnectConfig['network_key'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div class="flex items-center space-x-3 pt-4">
                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer SkyConnect
                </button>
                <button type="button" onclick="detectSkyConnect()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-search mr-2"></i>Détecter
                </button>
            </div>
        </form>
    </div>

    <!-- Dongle WiFi -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white">
                    <i class="fas fa-wifi"></i>
                </div>
                <div>
                    <h2 class="text-lg font-semibold" style="color: var(--text-primary)">Dongle WiFi</h2>
                    <p class="text-sm" style="color: var(--text-muted)">Adaptateur réseau sans fil</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full <?= $wifiDongleConfig['enabled'] ?? false ? 'bg-green-500' : 'bg-gray-400' ?>"></div>
                <span class="text-sm" style="color: var(--text-muted)">
                    <?= $wifiDongleConfig['enabled'] ?? false ? 'Actif' : 'Inactif' ?>
                </span>
            </div>
        </div>

        <form id="wifi-form" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Activer dongle WiFi</label>
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="enabled" value="1" 
                               class="w-5 h-5 rounded border-border-color bg-transparent text-primary"
                               <?= ($wifiDongleConfig['enabled'] ?? false) ? 'checked' : '' ?>>
                        <span class="text-sm" style="color: var(--text-secondary)">Utiliser ce dongle</span>
                    </label>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mode de connexion</label>
                    <select name="connection_mode" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="client" <?= ($wifiDongleConfig['connection_mode'] ?? 'client') === 'client' ? 'selected' : '' ?>>Client WiFi</option>
                        <option value="ap" <?= ($wifiDongleConfig['connection_mode'] ?? '') === 'ap' ? 'selected' : '' ?>>Point d'accès</option>
                        <option value="mesh" <?= ($wifiDongleConfig['connection_mode'] ?? '') === 'mesh' ? 'selected' : '' ?>>Mesh</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">SSID</label>
                    <input type="text" name="ssid" placeholder="Nom du réseau WiFi" 
                           value="<?= htmlspecialchars($wifiDongleConfig['ssid'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mot de passe</label>
                    <input type="password" name="password" placeholder="Mot de passe WiFi" 
                           value="<?= htmlspecialchars($wifiDongleConfig['password'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Sécurité</label>
                    <select name="security" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="wpa2" <?= ($wifiDongleConfig['security'] ?? 'wpa2') === 'wpa2' ? 'selected' : '' ?>>WPA2</option>
                        <option value="wpa3" <?= ($wifiDongleConfig['security'] ?? '') === 'wpa3' ? 'selected' : '' ?>>WPA3</option>
                        <option value="wep" <?= ($wifiDongleConfig['security'] ?? '') === 'wep' ? 'selected' : '' ?>>WEP</option>
                        <option value="open" <?= ($wifiDongleConfig['security'] ?? '') === 'open' ? 'selected' : '' ?>>Ouvert</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Canal</label>
                    <select name="channel" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <?php for ($i = 1; $i <= 13; $i++): ?>
                        <option value="<?= $i ?>" <?= ($wifiDongleConfig['channel'] ?? '') == $i ? 'selected' : '' ?>>Canal <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Interface</label>
                    <input type="text" name="interface" placeholder="ex: wlan1" 
                           value="<?= htmlspecialchars($wifiDongleConfig['interface'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div class="flex items-center space-x-3 pt-4">
                <button type="submit" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer WiFi
                </button>
                <button type="button" onclick="detectWifiDongle()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-search mr-2"></i>Détecter
                </button>
                <button type="button" onclick="scanWifiNetworks()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-radar mr-2"></i>Scanner réseaux
                </button>
            </div>
        </form>
    </div>

    <!-- Détection automatique -->
    <div class="glass-card rounded-2xl p-6">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-microchip mr-2"></i>Détection automatique
        </h3>
        <div class="space-y-3">
            <button onclick="detectAllUsbDevices()" class="w-full px-4 py-3 glass rounded-xl hover:bg-opacity-80 text-left">
                <i class="fas fa-usb mr-3"></i>
                <span style="color: var(--text-primary)">Scanner tous les périphériques USB</span>
            </button>
            <div id="detection-results" class="hidden space-y-2">
                <!-- Résultats de détection -->
            </div>
        </div>
    </div>

</main>

<script>
// Sauvegarde SkyConnect
document.getElementById('skyconnect-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const config = Object.fromEntries(formData.entries());
    config.enabled = config.enabled === '1';
    
    try {
        const res = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({
                key: 'skyconnect_config',
                value: JSON.stringify(config)
            })
        });
        
        const json = await res.json();
        if (json.success) {
            showToast('Configuration SkyConnect enregistrée', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
});

// Sauvegarde WiFi
document.getElementById('wifi-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const config = Object.fromEntries(formData.entries());
    config.enabled = config.enabled === '1';
    
    try {
        const res = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({
                key: 'wifi_dongle_config',
                value: JSON.stringify(config)
            })
        });
        
        const json = await res.json();
        if (json.success) {
            showToast('Configuration WiFi enregistrée', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
});

// Détection SkyConnect
async function detectSkyConnect() {
    showToast('Détection SkyConnect en cours...', 'info');
    
    try {
        const res = await fetch('/api/usb.php?action=detect', {
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            const skyconnect = json.data.detected_skyconnect;
            if (skyconnect) {
                // Remplir automatiquement le formulaire
                document.querySelector('input[name="serial_port"]').value = json.data.serial_ports[0]?.port || '';
                showToast('SkyConnect détecté: ' + skyconnect.description, 'success');
            } else {
                showToast('SkyConnect non détecté. Vérifiez le branchement USB.', 'error');
            }
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Détection dongle WiFi
async function detectWifiDongle() {
    showToast('Détection dongle WiFi en cours...', 'info');
    
    try {
        const res = await fetch('/api/usb.php?action=detect', {
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            const wifiDevices = json.data.usb_devices.filter(d => d.description.toLowerCase().includes('wireless') || d.description.toLowerCase().includes('wifi'));
            if (wifiDevices.length > 0) {
                showToast(`${wifiDevices.length} dongle(s) WiFi détecté(s)`, 'success');
            } else {
                showToast('Aucun dongle WiFi détecté', 'error');
            }
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Scanner réseaux WiFi
async function scanWifiNetworks() {
    showToast('Scan des réseaux WiFi en cours...', 'info');
    // Implémentation à faire avec appel API
}

// Détection USB
async function detectAllUsbDevices() {
    const resultsDiv = document.getElementById('detection-results');
    resultsDiv.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Détection en cours...</div>';
    resultsDiv.classList.remove('hidden');
    
    try {
        const res = await fetch('/api/usb.php?action=detect', {
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            const devices = json.data.usb_devices;
            const ports = json.data.serial_ports;
            
            let html = '';
            
            // Afficher les périphériques USB
            devices.forEach(device => {
                const icon = device.type === 'skyconnect' ? 'fa-satellite-dish text-blue-500' : 
                           device.description.toLowerCase().includes('wireless') ? 'fa-wifi text-green-500' : 
                           'fa-usb text-gray-500';
                
                html += `
                    <div class="p-3 rounded-xl mb-2" style="background: var(--bg-secondary)">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i class="fas ${icon}"></i>
                                <div>
                                    <div class="font-medium" style="color: var(--text-primary)">${device.description}</div>
                                    <div class="text-sm" style="color: var(--text-muted)">ID: ${device.vendor_id}:${device.product_id}</div>
                                </div>
                            </div>
                            ${device.type === 'skyconnect' ? '<button onclick="configureSkyConnect()" class="px-3 py-1 bg-blue-500 text-white rounded-lg text-sm">Configurer</button>' : ''}
                        </div>
                    </div>
                `;
            });
            
            // Afficher les ports série
            if (ports.length > 0) {
                html += '<div class="mt-4 mb-2 text-sm font-medium" style="color: var(--text-muted)">Ports série disponibles:</div>';
                ports.forEach(port => {
                    html += `
                        <div class="p-2 rounded-lg mb-1 text-sm" style="background: var(--bg-tertiary)">
                            <code style="color: var(--text-primary)">${port.port}</code> - ${port.description}
                        </div>
                    `;
                });
            }
            
            resultsDiv.innerHTML = html || '<div class="text-center py-4" style="color: var(--text-muted)">Aucun périphérique USB détecté</div>';
        } else {
            resultsDiv.innerHTML = '<div class="text-center py-4" style="color: var(--text-error)">Erreur de détection: ' + (json.error || 'Inconnue') + '</div>';
        }
    } catch (error) {
        resultsDiv.innerHTML = '<div class="text-center py-4" style="color: var(--text-error)">Erreur réseau</div>';
    }
}

function configureSkyConnect() {
    // Remplir le formulaire SkyConnect avec les valeurs détectées
    document.querySelector('input[name="serial_port"]').value = '/dev/ttyUSB0';
    document.querySelector('select[name="mode"]').value = 'zigbee';
    document.querySelector('input[name="enabled"]').checked = true;
    
    // Scroller vers la section SkyConnect
    document.getElementById('skyconnect-form').scrollIntoView({ behavior: 'smooth' });
    showToast('SkyConnect configuré avec les valeurs par défaut', 'success');
}

// Toast helper
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 px-4 py-2 rounded-xl text-white z-50';
    toast.style.background = type === 'success' ? 'var(--accent-success)' : 
                              type === 'error' ? 'var(--accent-error)' : 'var(--accent-info)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
