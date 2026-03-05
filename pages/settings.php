<?php
/**
 * DOMOGLASS PRO — Page des paramètres
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Paramètres';
$activePage = 'settings';

$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

// Récupérer la configuration actuelle
$config = db()->fetchAll('SELECT key, value FROM config');
$configValues = [];
foreach ($config as $c) {
    $configValues[$c['key']] = $c['value'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-4xl">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-cog mr-3" style="color: var(--accent-primary)"></i>
                Paramètres
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Configuration de DomoGlass Pro
            </p>
        </div>
    </div>

    <!-- Navigation des paramètres - Utilisation d'ancres HTML simples -->
    <div class="glass-card rounded-2xl p-2 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="#general" data-tab="general" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-home mr-2"></i>Général
            </a>
            <a href="#network" data-tab="network" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-network-wired mr-2"></i>Réseau
            </a>
            <a href="#mqtt" data-tab="mqtt" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-broadcast-tower mr-2"></i>MQTT
            </a>
            <a href="#homeassistant" data-tab="homeassistant" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-home mr-2"></i>Home Assistant
            </a>
            <a href="#security" data-tab="security" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-shield-alt mr-2"></i>Sécurité
            </a>
            <a href="#notifications" data-tab="notifications" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-bell mr-2"></i>Notifications
            </a>
            <a href="#appearance" data-tab="appearance" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-palette mr-2"></i>Apparence
            </a>
            <a href="#energy" data-tab="energy" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-bolt mr-2"></i>Énergie
            </a>
            <a href="#backup" data-tab="backup" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-save mr-2"></i>Sauvegarde
            </a>
            <a href="#advanced" data-tab="advanced" 
                    class="settings-tab px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10 cursor-pointer no-underline"
                    style="color: var(--text-muted); text-decoration: none;">
                <i class="fas fa-tools mr-2"></i>Avancé
            </a>
        </div>
    </div>

    <!-- Contenu des onglets -->
    <div class="space-y-6">
        
        <!-- Onglet Général -->
        <div id="tab-general" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-info-circle mr-2"></i>Informations système
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-muted)">Version</label>
                        <div class="text-lg" style="color: var(--text-primary)">DomoGlass Pro v<?= DOMOGLASS_VERSION ?></div>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-muted)">Environnement</label>
                        <div class="text-lg" style="color: var(--text-primary)"><?= ucfirst(DOMOGLASS_ENV) ?></div>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-muted)">URL de base</label>
                        <div class="text-lg" style="color: var(--text-primary)"><?= BASE_URL ?: 'Non configurée' ?></div>
                    </div>
                    <div>
                        <label class="text-sm font-medium" style="color: var(--text-muted)">Fuseau horaire</label>
                        <div class="text-lg" style="color: var(--text-primary)"><?= date_default_timezone_get() ?></div>
                    </div>
                </div>
                
                <h3 class="text-md font-semibold mb-3" style="color: var(--text-primary)">
                    <i class="fas fa-cog mr-2"></i>Configuration générale
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Nom de l'application</label>
                        <input type="text" id="app-name" value="<?= htmlspecialchars($configValues['app_name'] ?? 'DomoGlass Pro') ?>" 
                               class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Langue</label>
                        <select id="app-language" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <option value="fr" selected>Français</option>
                            <option value="en">English</option>
                            <option value="es">Español</option>
                            <option value="de">Deutsch</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Format de date</label>
                        <select id="date-format" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <option value="d/m/Y" selected>31/12/2024</option>
                            <option value="m/d/Y">12/31/2024</option>
                            <option value="Y-m-d">2024-12-31</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Réseau -->
        <div id="tab-network" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                        <i class="fas fa-wifi mr-2"></i>Configuration réseau
                    </h2>
                    <a href="/pages/reseaux.php" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl text-sm">
                        <i class="fas fa-external-link-alt mr-2"></i>Gestion avancée
                    </a>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Interface réseau</label>
                        <select id="network-interface" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <option value="auto">Automatique</option>
                            <option value="eth0">Ethernet (eth0)</option>
                            <option value="wlan0">WiFi (wlan0)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mode DHCP</label>
                        <div class="flex items-center space-x-3">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="dhcp-enabled" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <span class="text-sm" style="color: var(--text-primary)">Activer DHCP</span>
                        </div>
                    </div>
                    <div id="static-ip-settings" class="space-y-4 opacity-50">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Adresse IP</label>
                                <input type="text" id="static-ip" placeholder="192.168.1.100" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Masque réseau</label>
                                <input type="text" id="netmask" placeholder="255.255.255.0" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Passerelle</label>
                                <input type="text" id="gateway" placeholder="192.168.1.1" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">DNS primaire</label>
                                <input type="text" id="dns-primary" placeholder="8.8.8.8" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet MQTT -->
        <div id="tab-mqtt" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                        <i class="fas fa-broadcast-tower mr-2"></i>Configuration MQTT
                    </h2>
                    <a href="/pages/reseaux.php" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl text-sm">
                        <i class="fas fa-external-link-alt mr-2"></i>Réseaux
                    </a>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Hôte MQTT</label>
                            <input type="text" id="mqtt-host" value="<?= htmlspecialchars($configValues['mqtt_host'] ?? MQTT_HOST) ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port MQTT</label>
                            <input type="number" id="mqtt-port" value="<?= htmlspecialchars($configValues['mqtt_port'] ?? MQTT_PORT) ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port WebSocket</label>
                            <input type="number" id="mqtt-ws-port" value="<?= htmlspecialchars($configValues['mqtt_port_ws'] ?? MQTT_PORT_WS) ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Client ID</label>
                            <input type="text" id="mqtt-client-id" value="domoglass-<?= substr(md5(gethostname()), 0, 8) ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Nom d'utilisateur</label>
                            <input type="text" id="mqtt-username" value="<?= htmlspecialchars($configValues['mqtt_user'] ?? '') ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mot de passe</label>
                            <input type="password" id="mqtt-password" value="<?= htmlspecialchars($configValues['mqtt_password'] ?? '') ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Topic de base</label>
                        <input type="text" id="mqtt-topic-base" value="domoglass" 
                               class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="testMqttConnection()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                            <i class="fas fa-plug mr-2"></i>Tester la connexion
                        </button>
                        <span id="mqtt-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Home Assistant -->
        <div id="tab-homeassistant" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-home mr-2"></i>Home Assistant
                </h2>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">URL Home Assistant</label>
                            <input type="url" id="ha-url" value="<?= htmlspecialchars($configValues['ha_url'] ?? '') ?>" 
                                   placeholder="http://homeassistant.local:8123"
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Token d'accès longue durée</label>
                            <input type="password" id="ha-token" value="<?= htmlspecialchars($configValues['ha_token'] ?? '') ?>" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">URL WebSocket</label>
                        <input type="text" id="ha-ws-url" value="<?= htmlspecialchars($configValues['ha_ws_url'] ?? '') ?>" 
                               placeholder="ws://homeassistant.local:8123/api/websocket"
                               class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div class="flex items-center space-x-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="ha-ssl" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm" style="color: var(--text-primary)">Utiliser HTTPS/SSL</span>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="testHaConnection()" class="px-4 py-2 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-xl">
                            <i class="fas fa-check-circle mr-2"></i>Vérifier la connexion
                        </button>
                        <span id="ha-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Sécurité -->
        <div id="tab-security" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-key mr-2"></i>Clés API et authentification
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Clé API</label>
                        <div class="flex space-x-2">
                            <input type="password" id="api-key" value="<?= htmlspecialchars($configValues['api_key'] ?? '') ?>" 
                                   class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <button onclick="generateApiKey()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                                <i class="fas fa-sync-alt mr-2"></i>Générer
                            </button>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">Utilisée pour l'accès API externe</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Secret application</label>
                        <div class="flex space-x-2">
                            <input type="password" id="app-secret" value="<?= htmlspecialchars($configValues['app_secret'] ?? '') ?>" 
                                   class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <button onclick="generateAppSecret()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                                <i class="fas fa-sync-alt mr-2"></i>Générer
                            </button>
                        </div>
                        <p class="text-xs mt-1" style="color: var(--text-muted)">Pour la signature des tokens CSRF</p>
                    </div>
                </div>
                
                <h3 class="text-md font-semibold mb-3 mt-6" style="color: var(--text-primary)">
                    <i class="fas fa-user-shield mr-2"></i>Sessions
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Durée de session (heures)</label>
                        <input type="number" id="session-lifetime" value="<?= htmlspecialchars($configValues['session_lifetime'] ?? 8) ?>" 
                               min="1" max="168" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div class="flex items-center space-x-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="secure-cookies" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                        <span class="text-sm" style="color: var(--text-primary)">Cookies sécurisés (HTTPS uniquement)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Notifications -->
        <div id="tab-notifications" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-bell mr-2"></i>Configuration des notifications
                </h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Notifications système</div>
                            <div class="text-sm" style="color: var(--text-muted)">Alertes et informations importantes</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="system-notifications" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Notifications appareils</div>
                            <div class="text-sm" style="color: var(--text-muted)">Changements d'état des appareils</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="device-notifications" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Notifications énergie</div>
                            <div class="text-sm" style="color: var(--text-muted)">Alertes de consommation</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="energy-notifications" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Notifications sécurité</div>
                            <div class="text-sm" style="color: var(--text-muted)">Alertes de sécurité et intrusions</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="security-notifications" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
                
                <h3 class="text-md font-semibold mb-3 mt-6" style="color: var(--text-primary)">
                    <i class="fas fa-envelope mr-2"></i>Email
                </h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Serveur SMTP</label>
                            <input type="text" id="smtp-server" placeholder="smtp.gmail.com" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port SMTP</label>
                            <input type="number" id="smtp-port" value="587" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Email expéditeur</label>
                            <input type="email" id="smtp-from" placeholder="domoglass@example.com" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Email destinataire</label>
                            <input type="email" id="smtp-to" placeholder="admin@example.com" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="testEmailNotification()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                            <i class="fas fa-envelope mr-2"></i>Envoyer un test
                        </button>
                        <span id="email-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Apparence -->
        <div id="tab-appearance" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-palette mr-2"></i>Personnalisation
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Thème par défaut</label>
                        <select id="default-theme" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <option value="midnight" <?= ($configValues['theme_palette'] ?? 'midnight') === 'midnight' ? 'selected' : '' ?>>Midnight</option>
                            <option value="ocean" <?= ($configValues['theme_palette'] ?? '') === 'ocean' ? 'selected' : '' ?>>Ocean</option>
                            <option value="purple" <?= ($configValues['theme_palette'] ?? '') === 'purple' ? 'selected' : '' ?>>Purple</option>
                            <option value="rose" <?= ($configValues['theme_palette'] ?? '') === 'rose' ? 'selected' : '' ?>>Rose</option>
                            <option value="emerald" <?= ($configValues['theme_palette'] ?? '') === 'emerald' ? 'selected' : '' ?>>Emerald</option>
                            <option value="light" <?= ($configValues['theme_palette'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mode sombre automatique</label>
                        <select id="auto-dark-mode" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            <option value="disabled">Désactivé</option>
                            <option value="sunset">Au coucher du soleil</option>
                            <option value="schedule">Selon horaire</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Heure de basculement</label>
                        <input type="time" id="dark-mode-time" value="20:00" 
                               class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Énergie -->
        <div id="tab-energy" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-bolt mr-2"></i>Configuration énergétique
                </h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Prix du kWh (€)</label>
                        <input type="number" id="energy-price" value="<?= htmlspecialchars($configValues['energy_price_kwh'] ?? ENERGY_PRICE_KWH) ?>" 
                               step="0.01" min="0" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Objectif de consommation mensuelle (kWh)</label>
                        <input type="number" id="monthly-goal" value="<?= htmlspecialchars($configValues['monthly_goal'] ?? 500) ?>" 
                               min="0" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Seuil d'alerte de puissance (W)</label>
                        <input type="number" id="power-threshold" value="3000" 
                               min="0" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Alertes de consommation</div>
                            <div class="text-sm" style="color: var(--text-muted)">Notifier en cas de dépassement</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="energy-alerts" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Sauvegarde -->
        <div id="tab-backup" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-save mr-2"></i>Sauvegarde et restauration
                </h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Exporter la configuration</div>
                            <div class="text-sm" style="color: var(--text-muted)">Télécharger tous les paramètres</div>
                        </div>
                        <button onclick="exportConfig()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                            <i class="fas fa-download mr-2"></i>Exporter
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Importer une configuration</div>
                            <div class="text-sm" style="color: var(--text-muted)">Restaurer depuis un fichier</div>
                        </div>
                        <button onclick="document.getElementById('import-file').click()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-upload mr-2"></i>Importer
                        </button>
                        <input type="file" id="import-file" accept=".json" style="display: none;" onchange="importConfig(event)">
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Sauvegarde automatique</div>
                            <div class="text-sm" style="color: var(--text-muted)">Quotidienne à 02:00</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="auto-backup" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Rétention des sauvegardes</div>
                            <div class="text-sm" style="color: var(--text-muted)">Nombre de jours à conserver</div>
                        </div>
                        <input type="number" id="backup-retention" value="30" min="1" max="365" 
                               class="w-20 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                </div>
            </div>
        </div>

        <!-- Onglet Avancé -->
        <div id="tab-advanced" class="settings-tab-content">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-tools mr-2"></i>Maintenance
                </h2>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Nettoyer les logs</div>
                            <div class="text-sm" style="color: var(--text-muted)">Supprimer les anciens logs système</div>
                        </div>
                        <button onclick="cleanLogs()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-broom mr-2"></i>Nettoyer
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Vider le cache</div>
                            <div class="text-sm" style="color: var(--text-muted)">Supprimer le cache des templates</div>
                        </div>
                        <button onclick="clearCache()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-trash mr-2"></i>Vider
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Optimiser la base de données</div>
                            <div class="text-sm" style="color: var(--text-muted)">Compacter et optimiser SQLite</div>
                        </div>
                        <button onclick="optimizeDatabase()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-database mr-2"></i>Optimiser
                        </button>
                    </div>
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Redémarrer les services</div>
                            <div class="text-sm" style="color: var(--text-muted)">Redémarrer MQTT et workers</div>
                        </div>
                        <button onclick="restartServices()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-redo mr-2"></i>Redémarrer
                        </button>
                    </div>
                </div>
                
                <h3 class="text-md font-semibold mb-3 mt-6" style="color: var(--text-primary)">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Actions dangereuses
                </h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 rounded-lg border border-red-500" style="background: rgba(239, 68, 68, 0.1)">
                        <div>
                            <div class="font-medium" style="color: var(--accent-error)">Réinitialiser aux paramètres d'usine</div>
                            <div class="text-sm" style="color: var(--text-muted)">Supprime toutes les données et configuration</div>
                        </div>
                        <button onclick="factoryReset()" class="px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Réinitialiser
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons de sauvegarde -->
    <div class="flex justify-end space-x-3 mt-6">
        <button onclick="resetSettings()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
            <i class="fas fa-undo mr-2"></i>Réinitialiser
        </button>
        <button onclick="saveSettings()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
            <i class="fas fa-save mr-2"></i>Enregistrer les paramètres
        </button>
    </div>

</main>

<script>
// Gestion simple des onglets
document.addEventListener('DOMContentLoaded', function() {
    
    function showTab(tabName) {
        // Masquer tous les onglets
        document.querySelectorAll('.settings-tab-content').forEach(function(tab) {
            tab.style.display = 'none';
        });
        
        // Réinitialiser les styles des liens
        document.querySelectorAll('.settings-tab').forEach(function(link) {
            link.style.background = 'transparent';
            link.style.color = 'var(--text-muted)';
        });
        
        // Afficher l'onglet sélectionné
        var selectedTab = document.getElementById('tab-' + tabName);
        if (selectedTab) {
            selectedTab.style.display = 'block';
        } else {
            console.log('showTab: tab introuvable:', 'tab-' + tabName);
        }
        
        // Mettre en évidence le lien actif
        var activeLink = document.querySelector('[data-tab="' + tabName + '"]');
        if (activeLink) {
            activeLink.style.background = 'var(--accent-primary)20';
            activeLink.style.color = 'var(--accent-primary)';
        }
    }
    
    // Intercepter les clics sur les liens
    document.querySelectorAll('.settings-tab').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var tabName = this.getAttribute('data-tab');
            tabName = (tabName || '').trim();
            if (!tabName) return;
            window.location.hash = tabName;
            showTab(tabName);
        });
    });

    function getHashTab() {
        return (window.location.hash || '').replace('#', '').trim();
    }

    window.addEventListener('hashchange', function() {
        var tabName = getHashTab();
        if (tabName && document.getElementById('tab-' + tabName)) {
            showTab(tabName);
        }
    });
    
    // Ouvrir l'onglet initial
    var initialHash = getHashTab();
    var initialTab = initialHash && document.getElementById('tab-' + initialHash) ? initialHash : 'general';
    showTab(initialTab);
});

// Fonctions utilitaires
function saveSettings() {
    alert('Fonctionnalité en cours de développement');
}

function resetSettings() {
    if (confirm('Réinitialiser les paramètres ?')) {
        location.reload();
    }
}

function testMqttConnection() {
    var status = document.getElementById('mqtt-status');
    if (status) {
        status.textContent = 'Test...';
        setTimeout(function() {
            status.textContent = 'Non configuré';
        }, 1000);
    }
}

function testHaConnection() {
    var status = document.getElementById('ha-status');
    if (status) {
        status.textContent = 'Test...';
        setTimeout(function() {
            status.textContent = 'Non configuré';
        }, 1000);
    }
}

function generateApiKey() {
    var apiKey = document.getElementById('api-key');
    if (apiKey) {
        apiKey.value = 'key_' + Math.random().toString(36).substring(2, 15);
    }
}

function generateAppSecret() {
    var appSecret = document.getElementById('app-secret');
    if (appSecret) {
        appSecret.value = 'secret_' + Math.random().toString(36).substring(2, 15);
    }
}

function exportConfig() {
    alert('Export en cours de développement');
}

function importConfig(event) {
    alert('Import en cours de développement');
}

function factoryReset() {
    if (confirm('Cette action est IRRÉVERSIBLE ! Confirmer ?')) {
        alert('Réinitialisation en cours de développement');
    }
}

function cleanLogs() {
    alert('Nettoyage en cours de développement');
}

function clearCache() {
    alert('Vidage du cache en cours de développement');
}

function optimizeDatabase() {
    alert('Optimisation en cours de développement');
}

function restartServices() {
    alert('Redémarrage en cours de développement');
}

function testEmailNotification() {
    var status = document.getElementById('email-status');
    if (status) {
        status.textContent = 'Test email...';
        setTimeout(function() {
            status.textContent = 'Non configuré';
        }, 1000);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
