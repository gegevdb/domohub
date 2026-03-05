<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Réseau';
$activePage = 'settings';
$settingsSection = 'network';

$rooms = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

$config = db()->fetchAll('SELECT key, value FROM config');
$configValues = [];
foreach ($config as $c) {
    $configValues[$c['key']] = $c['value'];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-4xl">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-cog mr-3" style="color: var(--accent-primary)"></i>
                Paramètres
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">Configuration réseau</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                <i class="fas fa-network-wired mr-2"></i>Réseau
            </h2>
            <a href="/pages/reseaux.php" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl text-sm" style="text-decoration:none;">
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
                        <input type="checkbox" id="dhcp-enabled" checked class="sr-only peer" onchange="toggleStaticIp()">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                    <span class="text-sm" style="color: var(--text-primary)">Activer DHCP</span>
                </div>
            </div>

            <div id="static-ip-settings" class="space-y-4 opacity-50" style="pointer-events:none;">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Adresse IP</label>
                        <input type="text" id="static-ip" placeholder="192.168.1.100" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Masque réseau</label>
                        <input type="text" id="netmask" placeholder="255.255.255.0" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Passerelle</label>
                        <input type="text" id="gateway" placeholder="192.168.1.1" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">DNS primaire</label>
                        <input type="text" id="dns-primary" placeholder="8.8.8.8" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="location.reload()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                <i class="fas fa-undo mr-2"></i>Réinitialiser
            </button>
            <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>
    </div>

</main>

<script>
function toggleStaticIp() {
    var dhcp = document.getElementById('dhcp-enabled');
    var box = document.getElementById('static-ip-settings');
    if (!dhcp || !box) return;
    if (dhcp.checked) {
        box.style.opacity = '0.5';
        box.style.pointerEvents = 'none';
    } else {
        box.style.opacity = '1';
        box.style.pointerEvents = 'auto';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
