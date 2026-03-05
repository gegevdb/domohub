<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Énergie';
$activePage = 'settings';
$settingsSection = 'energy';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Énergie</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-bolt mr-2"></i>Configuration énergétique
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Prix du kWh (€)</label>
                <input type="number" id="energy-price" value="<?= htmlspecialchars($configValues['energy_price_kwh'] ?? ENERGY_PRICE_KWH) ?>" step="0.01" min="0"
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Objectif de consommation mensuelle (kWh)</label>
                <input type="number" id="monthly-goal" value="<?= htmlspecialchars($configValues['monthly_goal'] ?? 500) ?>" min="0"
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Seuil d'alerte de puissance (W)</label>
                <input type="number" id="power-threshold" value="3000" min="0"
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
