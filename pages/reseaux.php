<?php
/**
 * DOMOGLASS PRO — Page Réseaux
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Réseaux';
$activePage = 'network';

$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

// Récupérer la configuration réseau actuelle
$config = db()->fetchAll('SELECT key, value FROM config WHERE key LIKE "network_%"');
$configValues = [];
foreach ($config as $c) {
    $configValues[$c['key']] = $c['value'];
}

// Informations système réseau
$networkInfo = [
    'hostname' => gethostname(),
    'interfaces' => [],
    'gateway' => '',
    'dns' => []
];

// Détecter les interfaces réseau disponibles
if (function_exists('shell_exec')) {
    $interfaces = shell_exec('ip link show | grep -E "^[0-9]+:" | cut -d: -f2 | tr -d " "');
    if ($interfaces) {
        $networkInfo['interfaces'] = array_filter(explode("\n", trim($interfaces)));
    }
    
    // Gateway par défaut
    $gateway = shell_exec('ip route | grep default | awk \'{print $3}\' | head -1');
    if ($gateway) {
        $networkInfo['gateway'] = trim($gateway);
    }
    
    // DNS
    $dns = shell_exec('cat /etc/resolv.conf | grep nameserver | awk \'{print $2}\'');
    if ($dns) {
        $networkInfo['dns'] = array_filter(explode("\n", trim($dns)));
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-6xl">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-network-wired mr-3" style="color: var(--accent-primary)"></i>
                Réseaux
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Configuration et surveillance réseau
            </p>
        </div>
        <div class="flex space-x-3">
            <button onclick="scanNetwork()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                <i class="fas fa-search mr-2"></i>Scanner réseau
            </button>
            <button onclick="testConnectivity()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                <i class="fas fa-plug mr-2"></i>Tester connectivité
            </button>
        </div>
    </div>

    <!-- Navigation des sections réseau -->
    <div class="glass-card rounded-2xl p-2 mb-6">
        <div class="flex flex-wrap gap-2">
            <button onclick="showSection('overview')" data-section="overview" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-chart-line mr-2"></i>Vue d'ensemble
            </button>
            <button onclick="showSection('interfaces')" data-section="interfaces" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-ethernet mr-2"></i>Interfaces
            </button>
            <button onclick="showSection('wifi')" data-section="wifi" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-wifi mr-2"></i>WiFi
            </button>
            <button onclick="showSection('dhcp')" data-section="dhcp" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-server mr-2"></i>DHCP
            </button>
            <button onclick="showSection('firewall')" data-section="firewall" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-shield-alt mr-2"></i>Firewall
            </button>
            <button onclick="showSection('vpn')" data-section="vpn" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-lock mr-2"></i>VPN
            </button>
            <button onclick="showSection('monitoring')" data-section="monitoring" 
                    class="network-section px-4 py-2 text-sm font-medium rounded-lg transition-all hover:bg-opacity-10"
                    style="color: var(--text-muted)">
                <i class="fas fa-chart-area mr-2"></i>Monitoring
            </button>
        </div>
    </div>

    <!-- Contenu des sections -->
    <div class="space-y-6">
        
        <!-- Section Vue d'ensemble -->
        <div id="section-overview" class="network-section-content">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Statistiques réseau -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                        <i class="fas fa-chart-line mr-2"></i>Statistiques réseau
                    </h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="text-2xl font-bold" style="color: var(--accent-success)">12.5 MB/s</div>
                            <div class="text-sm" style="color: var(--text-muted)">Download</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="text-2xl font-bold" style="color: var(--accent-info)">8.3 MB/s</div>
                            <div class="text-sm" style="color: var(--text-muted)">Upload</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="text-2xl font-bold" style="color: var(--accent-warning)">3 ms</div>
                            <div class="text-sm" style="color: var(--text-muted)">Latence</div>
                        </div>
                        <div class="p-4 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="text-2xl font-bold" style="color: var(--text-primary)">0.01%</div>
                            <div class="text-sm" style="color: var(--text-muted)">Perte</div>
                        </div>
                    </div>
                </div>

                <!-- État des services -->
                <div class="glass-card rounded-2xl p-6">
                    <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                        <i class="fas fa-server mr-2"></i>Services réseau
                    </h2>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" style="background: var(--accent-success)"></div>
                                <span style="color: var(--text-primary)">MQTT Broker</span>
                            </div>
                            <span class="text-sm" style="color: var(--accent-success)">Actif</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" style="background: var(--accent-success)"></div>
                                <span style="color: var(--text-primary)">Web Server</span>
                            </div>
                            <span class="text-sm" style="color: var(--accent-success)">Actif</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" style="background: var(--accent-warning)"></div>
                                <span style="color: var(--text-primary)">VPN</span>
                            </div>
                            <span class="text-sm" style="color: var(--accent-warning)">Inactif</span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" style="background: var(--accent-success)"></div>
                                <span style="color: var(--text-primary)">DNS</span>
                            </div>
                            <span class="text-sm" style="color: var(--accent-success)">Actif</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphique de trafic -->
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-chart-area mr-2"></i>Trafic réseau (24h)
                </h2>
                <div class="h-64 flex items-center justify-center rounded-lg" style="background: var(--bg-tertiary)">
                    <canvas id="trafficChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Section Interfaces -->
        <div id="section-interfaces" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-ethernet mr-2"></i>Interfaces réseau
                </h2>
                <div class="space-y-4">
                    <?php foreach ($networkInfo['interfaces'] as $interface): ?>
                    <div class="p-4 rounded-lg border border-border-color" style="background: var(--bg-secondary)">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-3 h-3 rounded-full" style="background: var(--accent-success)"></div>
                                <h3 class="font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($interface) ?></h3>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="toggleInterface('<?= htmlspecialchars($interface) ?>')" class="px-3 py-1 text-sm rounded-lg glass hover:bg-opacity-80">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button onclick="configureInterface('<?= htmlspecialchars($interface) ?>')" class="px-3 py-1 text-sm rounded-lg glass hover:bg-opacity-80">
                                    <i class="fas fa-cog"></i>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="text-muted">État:</span>
                                <span class="ml-2" style="color: var(--accent-success)">Actif</span>
                            </div>
                            <div>
                                <span class="text-muted">IP:</span>
                                <span class="ml-2">192.168.1.100/24</span>
                            </div>
                            <div>
                                <span class="text-muted">MAC:</span>
                                <span class="ml-2">aa:bb:cc:dd:ee:ff</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 flex justify-center">
                    <button onclick="addInterface()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                        <i class="fas fa-plus mr-2"></i>Ajouter une interface
                    </button>
                </div>
            </div>
        </div>

        <!-- Section WiFi -->
        <div id="section-wifi" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-wifi mr-2"></i>Configuration WiFi
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- WiFi actuel -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Connexion actuelle</h3>
                        <div class="p-4 rounded-lg" style="background: var(--bg-tertiary)">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <i class="fas fa-wifi text-2xl" style="color: var(--accent-success)"></i>
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary)">HomeNetwork_5G</div>
                                        <div class="text-sm" style="color: var(--text-muted)">WPA2-AES</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm" style="color: var(--accent-success)">-45 dBm</div>
                                    <div class="text-xs" style="color: var(--text-muted)">Excellent</div>
                                </div>
                            </div>
                            <div class="flex space-x-3">
                                <button onclick="disconnectWifi()" class="px-3 py-1 text-sm rounded-lg glass hover:bg-opacity-80">
                                    <i class="fas fa-unlink mr-1"></i>Déconnecter
                                </button>
                                <button onclick="forgetWifi()" class="px-3 py-1 text-sm rounded-lg glass hover:bg-opacity-80">
                                    <i class="fas fa-trash mr-1"></i>Oublier
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Réseaux disponibles -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Réseaux disponibles</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <div class="p-3 rounded-lg border border-border-color hover:bg-opacity-10 cursor-pointer" style="background: var(--bg-secondary)" onclick="connectToWifi('HomeNetwork_5G')">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-wifi" style="color: var(--accent-success)"></i>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary)">HomeNetwork_5G</div>
                                            <div class="text-xs" style="color: var(--text-muted)">WPA2-AES</div>
                                        </div>
                                    </div>
                                    <div class="text-xs" style="color: var(--accent-success)">-45 dBm</div>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg border border-border-color hover:bg-opacity-10 cursor-pointer" style="background: var(--bg-secondary)" onclick="connectToWifi('Guest_Network')">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-wifi" style="color: var(--accent-warning)"></i>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary)">Guest_Network</div>
                                            <div class="text-xs" style="color: var(--text-muted)">WPA2</div>
                                        </div>
                                    </div>
                                    <div class="text-xs" style="color: var(--accent-warning)">-62 dBm</div>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg border border-border-color hover:bg-opacity-10 cursor-pointer" style="background: var(--bg-secondary)" onclick="connectToWifi('IoT_Network')">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-wifi" style="color: var(--accent-error)"></i>
                                        <div>
                                            <div class="text-sm font-medium" style="color: var(--text-primary)">IoT_Network</div>
                                            <div class="text-xs" style="color: var(--text-muted)">Open</div>
                                        </div>
                                    </div>
                                    <div class="text-xs" style="color: var(--accent-error)">-78 dBm</div>
                                </div>
                            </div>
                        </div>
                        <button onclick="scanWifi()" class="w-full mt-3 px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-search mr-2"></i>Scanner les réseaux
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section DHCP -->
        <div id="section-dhcp" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-server mr-2"></i>Serveur DHCP
                </h2>
                <div class="space-y-6">
                    <!-- Configuration DHCP -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Configuration</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Plage d'adresses</label>
                                <div class="flex space-x-2">
                                    <input type="text" value="192.168.1.100" placeholder="Début" 
                                           class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                    <input type="text" value="192.168.1.200" placeholder="Fin" 
                                           class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Masque réseau</label>
                                <input type="text" value="255.255.255.0" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Passerelle</label>
                                <input type="text" value="192.168.1.1" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Durée du bail (heures)</label>
                                <input type="number" value="24" min="1" max="168" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 mt-4">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="dhcp-enabled" checked class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                            <span class="text-sm" style="color: var(--text-primary)">Activer le serveur DHCP</span>
                        </div>
                    </div>

                    <!-- Baux DHCP actifs -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Baux actifs</h3>
                        <div class="space-y-2">
                            <div class="p-3 rounded-lg" style="background: var(--bg-tertiary)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary)">192.168.1.101</div>
                                        <div class="text-sm" style="color: var(--text-muted)">aa:bb:cc:dd:ee:ff - Smartphone</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm" style="color: var(--text-primary)">18h 24m</div>
                                        <div class="text-xs" style="color: var(--text-muted)">restant</div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg" style="background: var(--bg-tertiary)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium" style="color: var(--text-primary)">192.168.1.102</div>
                                        <div class="text-sm" style="color: var(--text-muted)">11:22:33:44:55:66 - Laptop</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm" style="color: var(--text-primary)">6h 12m</div>
                                        <div class="text-xs" style="color: var(--text-muted)">restant</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Firewall -->
        <div id="section-firewall" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-shield-alt mr-2"></i>Firewall
                </h2>
                <div class="space-y-6">
                    <!-- État du firewall -->
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Firewall</div>
                            <div class="text-sm" style="color: var(--text-muted)">Protection réseau active</div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="firewall-enabled" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- Règles du firewall -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Règles actives</h3>
                        <div class="space-y-2">
                            <div class="p-3 rounded-lg border border-green-500" style="background: rgba(34, 197, 94, 0.1)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-check-circle" style="color: var(--accent-success)"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary)">HTTP/HTTPS</div>
                                            <div class="text-sm" style="color: var(--text-muted)">Ports 80, 443 - Serveur web</div>
                                        </div>
                                    </div>
                                    <span class="text-sm" style="color: var(--accent-success)">Autorisé</span>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg border border-green-500" style="background: rgba(34, 197, 94, 0.1)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-check-circle" style="color: var(--accent-success)"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary)">MQTT</div>
                                            <div class="text-sm" style="color: var(--text-muted)">Port 1883 - Broker MQTT</div>
                                        </div>
                                    </div>
                                    <span class="text-sm" style="color: var(--accent-success)">Autorisé</span>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg border border-red-500" style="background: rgba(239, 68, 68, 0.1)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-times-circle" style="color: var(--accent-error)"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary)">SSH</div>
                                            <div class="text-sm" style="color: var(--text-muted)">Port 22 - Accès distant</div>
                                        </div>
                                    </div>
                                    <span class="text-sm" style="color: var(--accent-error)">Bloqué</span>
                                </div>
                            </div>
                        </div>
                        <button onclick="addFirewallRule()" class="w-full mt-3 px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-plus mr-2"></i>Ajouter une règle
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section VPN -->
        <div id="section-vpn" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-lock mr-2"></i>VPN
                </h2>
                <div class="space-y-6">
                    <!-- État VPN -->
                    <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                        <div>
                            <div class="font-medium" style="color: var(--text-primary)">Connexion VPN</div>
                            <div class="text-sm" style="color: var(--text-muted)">Non connecté</div>
                        </div>
                        <button onclick="toggleVpn()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                            <i class="fas fa-power-off mr-2"></i>Connecter
                        </button>
                    </div>

                    <!-- Configuration VPN -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Configuration</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Type de VPN</label>
                                <select class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                    <option value="openvpn">OpenVPN</option>
                                    <option value="wireguard">WireGuard</option>
                                    <option value="ipsec">IPSec</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Serveur VPN</label>
                                <input type="text" placeholder="vpn.example.com" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port</label>
                                <input type="number" value="1194" 
                                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Certificat</label>
                                <div class="flex space-x-2">
                                    <input type="file" accept=".crt,.pem" class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                    <button onclick="uploadCertificate()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                                        <i class="fas fa-upload"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Monitoring -->
        <div id="section-monitoring" class="network-section-content hidden">
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                    <i class="fas fa-chart-area mr-2"></i>Monitoring réseau
                </h2>
                <div class="space-y-6">
                    <!-- Alertes réseau -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Alertes actives</h3>
                        <div class="space-y-2">
                            <div class="p-3 rounded-lg border border-yellow-500" style="background: rgba(234, 179, 8, 0.1)">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-exclamation-triangle" style="color: var(--accent-warning)"></i>
                                        <div>
                                            <div class="font-medium" style="color: var(--text-primary)">Latence élevée</div>
                                            <div class="text-sm" style="color: var(--text-muted)">Ping > 100ms vers gateway</div>
                                        </div>
                                    </div>
                                    <span class="text-sm" style="color: var(--accent-warning)">Il y a 5 min</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tests de connectivité -->
                    <div>
                        <h3 class="text-md font-medium mb-3" style="color: var(--text-primary)">Tests de connectivité</h3>
                        <div class="space-y-3">
                            <div class="p-3 rounded-lg" style="background: var(--bg-tertiary)">
                                <div class="flex items-center justify-between">
                                    <span style="color: var(--text-primary)">Google DNS (8.8.8.8)</span>
                                    <span class="text-sm" style="color: var(--accent-success)">12 ms</span>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg" style="background: var(--bg-tertiary)">
                                <div class="flex items-center justify-between">
                                    <span style="color: var(--text-primary)">Cloudflare DNS (1.1.1.1)</span>
                                    <span class="text-sm" style="color: var(--accent-success)">8 ms</span>
                                </div>
                            </div>
                            <div class="p-3 rounded-lg" style="background: var(--bg-tertiary)">
                                <div class="flex items-center justify-between">
                                    <span style="color: var(--text-primary)">Gateway (192.168.1.1)</span>
                                    <span class="text-sm" style="color: var(--accent-success)">2 ms</span>
                                </div>
                            </div>
                        </div>
                        <button onclick="runConnectivityTest()" class="w-full mt-3 px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                            <i class="fas fa-sync-alt mr-2"></i>Actualiser les tests
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
// Variables globales
let currentSection = 'overview';
let trafficChart = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    showSection('overview');
    initTrafficChart();
});

// Gestion des sections
function showSection(sectionName) {
    // Masquer toutes les sections
    document.querySelectorAll('.network-section-content').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Réinitialiser les styles des onglets
    document.querySelectorAll('.network-section').forEach(tab => {
        tab.style.background = 'transparent';
        tab.style.color = 'var(--text-muted)';
    });
    
    // Afficher la section sélectionnée
    document.getElementById('section-' + sectionName).classList.remove('hidden');
    
    // Mettre en évidence l'onglet actif
    const activeTab = document.querySelector(`[data-section="${sectionName}"]`);
    activeTab.style.background = 'var(--accent-primary)20';
    activeTab.style.color = 'var(--accent-primary)';
    
    currentSection = sectionName;
}

// Initialiser le graphique de trafic
function initTrafficChart() {
    const ctx = document.getElementById('trafficChart');
    if (!ctx) return;
    
    // Graphique simulé
    const canvas = ctx.getContext('2d');
    canvas.fillStyle = 'var(--text-muted)';
    canvas.font = '14px sans-serif';
    canvas.textAlign = 'center';
    canvas.fillText('Graphique de trafic réseau', ctx.width/2, ctx.height/2);
}

// Fonctions réseau
function scanNetwork() {
    showToast('Scan réseau en cours...', 'info');
    setTimeout(() => {
        showToast('15 appareils détectés', 'success');
    }, 2000);
}

function testConnectivity() {
    showToast('Test de connectivité en cours...', 'info');
    setTimeout(() => {
        showToast('Connectivité OK', 'success');
    }, 1500);
}

function toggleInterface(interfaceName) {
    if (confirm(`Activer/désactiver l'interface ${interfaceName} ?`)) {
        showToast(`Interface ${interfaceName} basculée`, 'success');
    }
}

function configureInterface(interfaceName) {
    showToast(`Configuration de ${interfaceName}`, 'info');
}

function addInterface() {
    showToast('Ajout d\'interface réseau', 'info');
}

function connectToWifi(ssid) {
    const password = prompt(`Mot de passe pour ${ssid}:`);
    if (password) {
        showToast(`Connexion à ${ssid}...`, 'info');
        setTimeout(() => {
            showToast(`Connecté à ${ssid}`, 'success');
        }, 2000);
    }
}

function disconnectWifi() {
    if (confirm('Se déconnecter du WiFi ?')) {
        showToast('Déconnexion WiFi', 'info');
    }
}

function forgetWifi() {
    if (confirm('Oublier ce réseau WiFi ?')) {
        showToast('Réseau oublié', 'success');
    }
}

function scanWifi() {
    showToast('Scan WiFi en cours...', 'info');
    setTimeout(() => {
        showToast('8 réseaux détectés', 'success');
    }, 3000);
}

function toggleVpn() {
    const button = event.target;
    if (button.textContent.includes('Connecter')) {
        showToast('Connexion VPN en cours...', 'info');
        setTimeout(() => {
            button.innerHTML = '<i class="fas fa-power-off mr-2"></i>Déconnecter';
            showToast('VPN connecté', 'success');
        }, 3000);
    } else {
        if (confirm('Déconnecter le VPN ?')) {
            showToast('Déconnexion VPN...', 'info');
            setTimeout(() => {
                button.innerHTML = '<i class="fas fa-power-off mr-2"></i>Connecter';
                showToast('VPN déconnecté', 'success');
            }, 1000);
        }
    }
}

function uploadCertificate() {
    showToast('Upload du certificat...', 'info');
}

function addFirewallRule() {
    showToast('Ajout de règle firewall', 'info');
}

function runConnectivityTest() {
    showToast('Tests de connectivité en cours...', 'info');
    setTimeout(() => {
        showToast('Tests terminés', 'success');
    }, 2000);
}

// Toast helper
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 px-4 py-2 rounded-xl text-white z-50';
    toast.style.background = type === 'success' ? 'var(--accent-success)' : 
                              type === 'error' ? 'var(--accent-error)' : 
                              type === 'warning' ? 'var(--accent-warning)' : 'var(--accent-info)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
