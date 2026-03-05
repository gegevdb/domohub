<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Notifications';
$activePage = 'settings';
$settingsSection = 'notifications';

$rooms = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Notifications</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-bell mr-2"></i>Configuration des notifications
        </h2>

        <div class="space-y-4">
            <?php
            $toggles = [
                ['id' => 'system-notifications', 'label' => 'Notifications système', 'desc' => 'Alertes et informations importantes'],
                ['id' => 'device-notifications', 'label' => 'Notifications appareils', 'desc' => 'Changements d\'état des appareils'],
                ['id' => 'energy-notifications', 'label' => 'Notifications énergie', 'desc' => 'Alertes de consommation'],
                ['id' => 'security-notifications', 'label' => 'Notifications sécurité', 'desc' => 'Alertes sécurité et intrusions'],
            ];
            foreach ($toggles as $t):
            ?>
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div>
                    <div class="font-medium" style="color: var(--text-primary)"><?= $t['label'] ?></div>
                    <div class="text-sm" style="color: var(--text-muted)"><?= $t['desc'] ?></div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="<?= $t['id'] ?>" class="sr-only peer" checked>
                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 class="text-md font-semibold mb-3 mt-6" style="color: var(--text-primary)">
            <i class="fas fa-envelope mr-2"></i>Email (SMTP)
        </h3>

        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Serveur SMTP</label>
                    <input type="text" id="smtp-server" placeholder="smtp.gmail.com" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port SMTP</label>
                    <input type="number" id="smtp-port" value="587" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Email expéditeur</label>
                    <input type="email" id="smtp-from" placeholder="domoglass@example.com" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Email destinataire</label>
                    <input type="email" id="smtp-to" placeholder="admin@example.com" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <button type="button" onclick="testEmail()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-envelope mr-2"></i>Envoyer un test
                </button>
                <span id="email-status" class="text-sm" style="color: var(--text-muted)">Non testé</span>
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
function testEmail() {
    var s = document.getElementById('email-status');
    if (!s) return;
    s.textContent = 'Test...';
    setTimeout(function(){ s.textContent = 'Non configuré'; }, 800);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
