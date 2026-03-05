<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Sauvegarde';
$activePage = 'settings';
$settingsSection = 'backup';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Sauvegarde & restauration</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

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
                <button type="button" onclick="exportConfig()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-download mr-2"></i>Exporter
                </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div>
                    <div class="font-medium" style="color: var(--text-primary)">Importer une configuration</div>
                    <div class="text-sm" style="color: var(--text-muted)">Restaurer depuis un fichier</div>
                </div>
                <button type="button" onclick="document.getElementById('import-file').click()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-upload mr-2"></i>Importer
                </button>
                <input type="file" id="import-file" accept=".json" style="display:none" onchange="importConfig(event)">
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
function exportConfig(){ alert('Export en cours de développement'); }
function importConfig(){ alert('Import en cours de développement'); }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
