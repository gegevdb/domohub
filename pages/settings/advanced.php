<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Avancé';
$activePage = 'settings';
$settingsSection = 'advanced';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Maintenance et options avancées</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

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
                <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-broom mr-2"></i>Nettoyer
                </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div>
                    <div class="font-medium" style="color: var(--text-primary)">Vider le cache</div>
                    <div class="text-sm" style="color: var(--text-muted)">Supprimer le cache des templates</div>
                </div>
                <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-trash mr-2"></i>Vider
                </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div>
                    <div class="font-medium" style="color: var(--text-primary)">Optimiser la base de données</div>
                    <div class="text-sm" style="color: var(--text-muted)">Compacter et optimiser SQLite</div>
                </div>
                <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-database mr-2"></i>Optimiser
                </button>
            </div>

            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div>
                    <div class="font-medium" style="color: var(--text-primary)">Redémarrer les services</div>
                    <div class="text-sm" style="color: var(--text-muted)">Redémarrer MQTT et workers</div>
                </div>
                <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
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
                <button type="button" onclick="alert('Fonctionnalité en cours de développement')" class="px-4 py-2 bg-red-600 text-white rounded-xl hover:bg-red-700">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Réinitialiser
                </button>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
