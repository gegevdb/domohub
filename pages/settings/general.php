<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Général';
$activePage = 'settings';
$settingsSection = 'general';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Configuration de DomoGlass Pro
            </p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

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

        <div class="flex justify-end space-x-3 mt-6">
            <button onclick="location.reload()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                <i class="fas fa-undo mr-2"></i>Réinitialiser
            </button>
            <button onclick="alert('Fonctionnalité en cours de développement')" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
