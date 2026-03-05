<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — APIs';
$activePage = 'settings';
$settingsSection = 'apis';

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
            <p class="text-sm mt-1" style="color: var(--text-muted)">Intégrations APIs (box internet)</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="space-y-6">

        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                    <i class="fas fa-box mr-2"></i>Freebox — API Free
                </h2>
                <span class="text-xs px-2 py-1 rounded-full" style="background: var(--accent-info)20; color: var(--accent-info)">Réseau local</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Hôte / IP</label>
                    <input type="text" id="freebox-host" value="<?= htmlspecialchars($configValues['freebox_host'] ?? 'mafreebox.freebox.fr') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <p class="text-xs mt-1" style="color: var(--text-muted)">Par défaut: mafreebox.freebox.fr</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Port</label>
                    <input type="number" id="freebox-port" value="<?= htmlspecialchars($configValues['freebox_port'] ?? '80') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">App Token</label>
                    <input type="password" id="freebox-app-token" value="<?= htmlspecialchars($configValues['freebox_app_token'] ?? '') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <p class="text-xs mt-1" style="color: var(--text-muted)">Token obtenu après association de l'application dans Freebox OS.</p>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 mt-6">
                <button type="button" onclick="testFreebox()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-plug mr-2"></i>Tester
                </button>
                <button type="button" onclick="saveApis('freebox')" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
            <div class="mt-2 text-sm" id="freebox-status" style="color: var(--text-muted)"></div>
        </div>

        <div class="glass-card rounded-2xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                    <i class="fas fa-router mr-2"></i>Bbox — Bouygues
                </h2>
                <div class="text-xs" style="color: var(--text-muted)">
                    Modèle: <span class="font-medium" style="color: var(--text-primary)">F@st5696b</span>
                    <span class="mx-2">•</span>
                    Fabricant: <span class="font-medium" style="color: var(--text-primary)">Sagemcom</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Hôte / IP</label>
                    <input type="text" id="bbox-host" value="<?= htmlspecialchars($configValues['bbox_host'] ?? '192.168.1.254') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <p class="text-xs mt-1" style="color: var(--text-muted)">Souvent: 192.168.1.254 ou 192.168.0.1</p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Protocole</label>
                    <select id="bbox-proto" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <?php $proto = $configValues['bbox_proto'] ?? 'http'; ?>
                        <option value="http" <?= $proto === 'http' ? 'selected' : '' ?>>HTTP</option>
                        <option value="https" <?= $proto === 'https' ? 'selected' : '' ?>>HTTPS</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Endpoint / Notes</label>
                    <input type="text" id="bbox-endpoint" value="<?= htmlspecialchars($configValues['bbox_endpoint'] ?? '/api') ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <p class="text-xs mt-1" style="color: var(--text-muted)">Varie selon firmware/modèle. À ajuster selon la doc/inspection réseau.</p>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 mt-6">
                <button type="button" onclick="testBbox()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    <i class="fas fa-plug mr-2"></i>Tester
                </button>
                <button type="button" onclick="saveApis('bbox')" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
            <div class="mt-2 text-sm" id="bbox-status" style="color: var(--text-muted)"></div>
        </div>

    </div>

</main>

<script>
function testFreebox() {
    var status = document.getElementById('freebox-status');
    if (!status) return;
    status.textContent = 'Test en cours...';
    setTimeout(function(){
        status.textContent = 'Test non implémenté (API backend à créer).';
    }, 800);
}

function testBbox() {
    var status = document.getElementById('bbox-status');
    if (!status) return;
    status.textContent = 'Test en cours...';
    setTimeout(function(){
        status.textContent = 'Test non implémenté (API backend à créer).';
    }, 800);
}

async function saveApis(which) {
    const isFreebox = which === 'freebox';
    const statusEl = document.getElementById(isFreebox ? 'freebox-status' : 'bbox-status');
    if (statusEl) statusEl.textContent = 'Enregistrement...';

    const payload = {};
    if (isFreebox) {
        var fbHost = document.getElementById('freebox-host');
        var fbPort = document.getElementById('freebox-port');
        var fbToken = document.getElementById('freebox-app-token');
        payload.freebox_host = fbHost ? fbHost.value : '';
        payload.freebox_port = fbPort ? fbPort.value : '';
        payload.freebox_app_token = fbToken ? fbToken.value : '';
    } else {
        var bbHost = document.getElementById('bbox-host');
        var bbProto = document.getElementById('bbox-proto');
        var bbEndpoint = document.getElementById('bbox-endpoint');
        payload.bbox_host = bbHost ? bbHost.value : '';
        payload.bbox_proto = bbProto ? bbProto.value : 'http';
        payload.bbox_endpoint = bbEndpoint ? bbEndpoint.value : '';
    }

    try {
        const resp = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': (window.DOMOGLASS && window.DOMOGLASS.csrfToken) ? window.DOMOGLASS.csrfToken : ''
            },
            body: JSON.stringify(payload)
        });

        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.success) {
            const msg = data.error || ('Erreur HTTP ' + resp.status);
            if (statusEl) statusEl.textContent = 'Erreur: ' + msg;
            return;
        }

        if (statusEl) statusEl.textContent = 'Enregistré.';
    } catch (e) {
        if (statusEl) statusEl.textContent = 'Erreur réseau.';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
