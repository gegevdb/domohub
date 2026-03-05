<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Home Assistant';
$activePage = 'settings';
$settingsSection = 'homeassistant';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Intégration Home Assistant</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-home mr-2"></i>Home Assistant
        </h2>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">URL Home Assistant</label>
                    <input type="url" id="ha-url" value="<?= htmlspecialchars($configValues['ha_url'] ?? '') ?>" placeholder="http://homeassistant.local:8123" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Token d'accès longue durée</label>
                    <input type="password" id="ha-token" value="<?= htmlspecialchars($configValues['ha_token'] ?? '') ?>" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">URL WebSocket</label>
                <input type="text" id="ha-ws-url" value="<?= htmlspecialchars($configValues['ha_ws_url'] ?? '') ?>" placeholder="ws://homeassistant.local:8123/api/websocket" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>

            <div class="flex items-center space-x-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="ha-ssl" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
                <span class="text-sm" style="color: var(--text-primary)">Utiliser HTTPS/SSL</span>
            </div>

            <div class="flex items-center space-x-3">
                <button type="button" onclick="testHaConnection()" class="px-4 py-2 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-xl">
                    <i class="fas fa-check-circle mr-2"></i>Vérifier la connexion
                </button>
                <span id="ha-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
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
function testHaConnection() {
    var status = document.getElementById('ha-status');
    if (!status) return;
    status.textContent = 'Test...';
    setTimeout(function(){ status.textContent = 'Non configuré'; }, 800);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
