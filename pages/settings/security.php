<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Sécurité';
$activePage = 'settings';
$settingsSection = 'security';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Sécurité</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

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
                    <button type="button" onclick="generateApiKey()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
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
                    <button type="button" onclick="generateAppSecret()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                        <i class="fas fa-sync-alt mr-2"></i>Générer
                    </button>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">Pour la signature des tokens CSRF</p>
            </div>

            <h3 class="text-md font-semibold mb-2 mt-6" style="color: var(--text-primary)">
                <i class="fas fa-user-shield mr-2"></i>Sessions
            </h3>

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
function generateApiKey() {
    var apiKey = document.getElementById('api-key');
    if (apiKey) apiKey.value = 'key_' + Math.random().toString(36).substring(2, 15);
}
function generateAppSecret() {
    var s = document.getElementById('app-secret');
    if (s) s.value = 'secret_' + Math.random().toString(36).substring(2, 15);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
