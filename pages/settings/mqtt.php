<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — MQTT';
$activePage = 'settings';
$settingsSection = 'mqtt';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Configuration MQTT</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                <i class="fas fa-broadcast-tower mr-2"></i>MQTT
            </h2>
            <a href="/pages/reseaux.php" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80 text-sm" style="text-decoration:none;">
                <i class="fas fa-network-wired mr-2"></i>Réseaux
            </a>
        </div>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Hôte MQTT</label>
                    <input type="text" id="mqtt-host" value="<?= htmlspecialchars($configValues['mqtt_host'] ?? MQTT_HOST) ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port MQTT</label>
                    <input type="number" id="mqtt-port" value="<?= htmlspecialchars($configValues['mqtt_port'] ?? MQTT_PORT) ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port WebSocket</label>
                    <input type="number" id="mqtt-ws-port" value="<?= htmlspecialchars($configValues['mqtt_port_ws'] ?? MQTT_PORT_WS) ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Client ID</label>
                    <input type="text" id="mqtt-client-id" value="domoglass-<?= substr(md5(gethostname()), 0, 8) ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Nom d'utilisateur</label>
                    <input type="text" id="mqtt-username" value="<?= htmlspecialchars($configValues['mqtt_user'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Mot de passe</label>
                    <input type="password" id="mqtt-password" value="<?= htmlspecialchars($configValues['mqtt_password'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Topic de base</label>
                <input type="text" id="mqtt-topic-base" value="domoglass" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>

            <div class="flex items-center space-x-3">
                <button type="button" onclick="testMqttConnection()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-plug mr-2"></i>Tester la connexion
                </button>
                <span id="mqtt-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
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
function testMqttConnection() {
    var status = document.getElementById('mqtt-status');
    if (!status) return;
    status.textContent = 'Test...';
    setTimeout(function(){ status.textContent = 'Non configuré'; }, 800);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
