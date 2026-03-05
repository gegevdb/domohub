<?php
/**
 * DOMOGLASS PRO — Page Appairage Zigbee (Redirection vers Windfront)
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Réseau Zigbee';
$activePage = 'zigbee';

// Récupérer la configuration SkyConnect
$skyconnectConfig = json_decode(db()->getConfig('skyconnect_config', '{}'), true) ?: [];
$windfrontUrl = $skyconnectConfig['web_url'] ?? 'http://192.168.1.210:8080';

// Variables pour l'affichage (simulées pour Windfront)
$permitJoin = false; // Windfront gère ça côté front-end
$totalDiscovered = 0;
$totalPaired = 0;

// Forcer l'affichage du lien Windfront
$showWindfrontLink = true;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-6xl">

    <!-- Titre -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-network-wired mr-3" style="color: var(--accent-primary)"></i>
                Réseau Zigbee
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Gestion via Zigbee2MQTT Windfront — Interface complète
            </p>
        </div>
    </div>

    <!-- Carte principale -->
    <div class="glass-card rounded-2xl p-8 text-center">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-green-400 to-teal-500 flex items-center justify-center text-white text-3xl mx-auto mb-6">
            <i class="fas fa-satellite-dish"></i>
        </div>
        
        <h2 class="text-xl font-bold mb-4" style="color: var(--text-primary)">
            Zigbee2MQTT Windfront
        </h2>
        
        <p class="text-sm mb-6 max-w-md mx-auto" style="color: var(--text-muted)">
            Interface web complète pour la gestion de votre réseau Zigbee via SkyConnect. 
            Accédez au dashboard pour ajouter, configurer et surveiller vos appareils.
        </p>

        <?php if (!empty($windfrontUrl)): ?>
        <!-- Bouton d'accès à Windfront -->
        <div class="space-y-4">
            <a href="<?= htmlspecialchars($windfrontUrl) ?>" 
               target="_blank"
               class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-green-500 to-teal-600 text-white rounded-xl font-semibold hover:opacity-90 transition">
                <i class="fas fa-external-link-alt"></i>
                Ouvrir Windfront
            </a>
            
            <div class="text-xs" style="color: var(--text-muted)">
                <i class="fas fa-info-circle mr-1"></i>
                Ouvre dans un nouvel onglet
            </div>
        </div>
        <?php else: ?>
        <!-- Configuration requise -->
        <div class="space-y-4">
            <div class="p-4 rounded-xl" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.3);">
                <div class="flex items-center gap-3 mb-2" style="color: var(--accent-warning)">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="font-medium">Configuration requise</span>
                </div>
                <p class="text-sm" style="color: var(--text-muted)">
                    Windfront n'est pas encore configuré. 
                    <a href="/pages/settings/dongles.php" class="underline">Configurez votre SkyConnect</a> 
                    pour activer l'interface Zigbee.
                </p>
            </div>
            
            <a href="/pages/settings/dongles.php" 
               class="inline-flex items-center gap-3 px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl font-semibold hover:opacity-90 transition">
                <i class="fas fa-cog"></i>
                Configurer SkyConnect
            </a>
        </div>
        <?php endif; ?>

        <!-- Informations -->
        <div class="mt-8 pt-6 border-t" style="border-color: var(--border-color)">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div>
                    <div class="font-medium mb-1" style="color: var(--text-primary)">SkyConnect</div>
                    <div style="color: var(--text-muted)">
                        <?= !empty($skyconnectConfig['serial_port']) ? htmlspecialchars($skyconnectConfig['serial_port']) : 'Non configuré' ?>
                    </div>
                </div>
                <div>
                    <div class="font-medium mb-1" style="color: var(--text-primary)">MQTT</div>
                    <div style="color: var(--text-muted)">localhost:1883</div>
                </div>
                <div>
                    <div class="font-medium mb-1" style="color: var(--text-primary)">Service</div>
                    <div style="color: var(--text-muted)">
                        <?= !empty($skyconnectConfig['integration']) ? htmlspecialchars($skyconnectConfig['integration']) : 'Non configuré' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Guide rapide -->
    <div class="mt-6 glass-card rounded-2xl p-6">
        <h3 class="font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-question-circle mr-2"></i>
            Guide rapide Windfront
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-medium mb-2" style="color: var(--text-primary)">Ajouter un appareil</h4>
                <ol class="space-y-1" style="color: var(--text-muted)">
                    <li>1. Allez dans Windfront</li>
                    <li>2. Cliquez sur "Permit Join"</li>
                    <li>3. Mettez l'appareil en mode pairing</li>
                    <li>4. L'appareil apparaîtra automatiquement</li>
                </ol>
            </div>
            
            <div>
                <h4 class="font-medium mb-2" style="color: var(--text-primary)">Fonctionnalités</h4>
                <ul class="space-y-1" style="color: var(--text-muted)">
                    <li>• Dashboard des appareils</li>
                    <li>• Logs en temps réel</li>
                    <li>• Configuration réseau</li>
                    <li>• Gestion des groupes</li>
                </ul>
            </div>
        </div>
    </div>

</main>
            </div>
            <div>
                <p class="text-sm font-medium" style="color: var(--text-muted)">Home Assistant</p>
                <div class="flex items-center gap-2 mt-1">
                    <div class="w-2.5 h-2.5 rounded-full" id="ha-status-dot"
                         style="background: var(--status-offline)"></div>
                    <span class="font-semibold text-sm" id="ha-status-text"
                          style="color: var(--text-primary)">Vérification...</span>
                </div>
                <p class="text-xs mt-1" style="color: var(--text-muted)">
                    <?= htmlspecialchars(HA_HOST) ?>:<?= HA_PORT ?>
                </p>
            </div>
        </div>

        <!-- Mode appairage -->
        <div class="glass-card rounded-2xl p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl transition-all
                        <?= $permitJoin ? 'bg-gradient-to-br from-green-400 to-teal-500 animate-pulse' : 'bg-gradient-to-br from-gray-500 to-gray-600' ?>"
                 id="pairing-icon-bg">
                <i class="fas fa-broadcast-tower"></i>
            </div>
            <div class="flex-1">
                <p class="text-sm font-medium" style="color: var(--text-muted)">Mode appairage</p>
                <p class="font-bold text-sm" id="pairing-status-text"
                   style="color: <?= $permitJoin ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                    <?= $permitJoin ? 'OUVERT' : 'Fermé' ?>
                </p>
                <p class="text-xs" id="pairing-countdown" style="color: var(--text-muted)"></p>
            </div>
        </div>

        <!-- Compteurs -->
        <div class="glass-card rounded-2xl p-5 grid grid-cols-2 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold" style="color: var(--accent-primary)" id="count-discovered">
                    <?= $totalDiscovered ?>
                </div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">Découverts</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold" style="color: var(--accent-success)" id="count-paired">
                    <?= $totalPaired ?>
                </div>
                <div class="text-xs mt-1" style="color: var(--text-muted)">Intégrés</div>
            </div>
        </div>
    </div>

    <!-- Panneau de contrôle appairage -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <h2 class="font-bold text-base mb-4 flex items-center gap-2" style="color: var(--text-primary)">
            <i class="fas fa-plus-circle" style="color: var(--accent-success)"></i>
            Ajouter un appareil
        </h2>

        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div class="flex-1">
                <p class="text-sm" style="color: var(--text-secondary)">
                    Appuyez sur le bouton d'appairage de votre appareil Zigbee, puis ouvrez le réseau ci-dessous.
                    L'appareil sera détecté automatiquement par ZHA.
                </p>
                <p class="text-xs mt-2" style="color: var(--text-muted)">
                    <i class="fas fa-info-circle mr-1"></i>
                    Durée d'ouverture : 60 secondes. L'appareil doit être à portée du coordinateur Zigbee.
                </p>
            </div>

            <div class="flex gap-3 flex-shrink-0">
                <button onclick="startPairing()"
                        id="btn-permit"
                        class="px-5 py-3 rounded-xl text-sm font-bold text-white transition
                               bg-gradient-to-r from-green-500 to-teal-600 hover:opacity-90 shadow-lg"
                        <?= $permitJoin ? 'disabled' : '' ?>>
                    <i class="fas fa-broadcast-tower mr-2"></i>
                    Ouvrir le réseau
                </button>
                <button onclick="stopPairing()"
                        id="btn-deny"
                        class="px-5 py-3 rounded-xl text-sm font-bold transition hover:opacity-80"
                        style="background: rgba(239,68,68,.15); color: var(--accent-danger)"
                        <?= !$permitJoin ? 'disabled style="opacity:.4"' : '' ?>>
                    <i class="fas fa-times-circle mr-2"></i>
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Appareils découverts -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-base" style="color: var(--text-primary)">
                Appareils sur le réseau
            </h2>
            <div class="flex gap-2">
                <button onclick="filterDevices('all')" data-filter="all"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg glass font-medium active"
                        style="color: var(--text-primary)">Tous</button>
                <button onclick="filterDevices('new')" data-filter="new"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg glass font-medium"
                        style="color: var(--text-secondary)">Non intégrés</button>
                <button onclick="filterDevices('paired')" data-filter="paired"
                        class="filter-btn text-xs px-3 py-1.5 rounded-lg glass font-medium"
                        style="color: var(--text-secondary)">Intégrés</button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" id="zigbee-devices-grid">
            <?php if (empty($discoveredDevices)): ?>
            <div class="col-span-full glass-card rounded-2xl p-10 text-center" id="empty-state">
                <i class="fas fa-satellite-dish text-5xl mb-4 opacity-20" style="color: var(--text-muted)"></i>
                <p class="font-medium" style="color: var(--text-primary)">Aucun appareil découvert</p>
                <p class="text-sm mt-2" style="color: var(--text-muted)">
                    Cliquez sur "Synchroniser HA" ou ouvrez le réseau pour détecter de nouveaux appareils.
                </p>
            </div>
            <?php else: ?>
            <?php foreach ($discoveredDevices as $dev): ?>
            <?php
                $isPaired  = (bool)$dev['paired'];
                $lastSeen  = $dev['last_seen'] ? date('d/m/Y H:i', strtotime($dev['last_seen'])) : 'Jamais';
                $typeIcons = ['light'=>'fa-lightbulb','switch'=>'fa-toggle-on','sensor'=>'fa-chart-line',
                              'coordinator'=>'fa-broadcast-tower','climate'=>'fa-thermometer-half'];
                $icon = $typeIcons[$dev['device_type'] ?? ''] ?? 'fa-plug';
            ?>
            <div class="glass-card rounded-2xl p-4 flex flex-col gap-3 zigbee-device-card"
                 data-paired="<?= $isPaired ? '1' : '0' ?>"
                 data-ieee="<?= htmlspecialchars($dev['ieee']) ?>">

                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center
                                    <?= $isPaired ? 'bg-gradient-to-br from-green-400 to-teal-500' : 'glass' ?>">
                            <i class="fas <?= $icon ?> text-sm"
                               style="color: <?= $isPaired ? 'white' : 'var(--text-muted)' ?>"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-sm" style="color: var(--text-primary)">
                                <?= htmlspecialchars($dev['name'] ?: substr($dev['ieee'], -8)) ?>
                            </p>
                            <p class="text-xs" style="color: var(--text-muted)">
                                <?= htmlspecialchars(trim(($dev['manufacturer'] ?? '') . ' ' . ($dev['model'] ?? ''))) ?: 'Inconnu' ?>
                            </p>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-lg font-semibold flex-shrink-0
                                 <?= $isPaired ? 'bg-green-500/20 text-green-400' : 'bg-yellow-500/20 text-yellow-400' ?>">
                        <?= $isPaired ? '● Intégré' : '○ Nouveau' ?>
                    </span>
                </div>

                <div class="text-xs font-mono p-2 rounded-lg" style="background:rgba(0,0,0,.2);color:var(--text-muted)">
                    <?= htmlspecialchars($dev['ieee']) ?>
                </div>

                <div class="flex items-center justify-between text-xs" style="color: var(--text-muted)">
                    <span><i class="fas fa-clock mr-1"></i><?= $lastSeen ?></span>
                    <span><?= htmlspecialchars(ucfirst($dev['device_type'] ?? 'inconnu')) ?></span>
                </div>

                <?php if (!$isPaired): ?>
                <button onclick="pairDevice('<?= htmlspecialchars($dev['ieee']) ?>', '<?= htmlspecialchars(addslashes($dev['name'] ?: '')) ?>')"
                        class="w-full py-2.5 rounded-xl text-sm font-bold text-white transition hover:opacity-90
                               bg-gradient-to-r from-indigo-500 to-purple-600">
                    <i class="fas fa-link mr-2"></i>Intégrer dans DomoGlass
                </button>
                <?php else: ?>
                <div class="flex gap-2">
                    <a href="/index.php?device=<?= $dev['device_id'] ?>"
                       class="flex-1 py-2 rounded-xl text-sm font-medium text-center transition hover:opacity-80 glass"
                       style="color: var(--text-primary)">
                        <i class="fas fa-external-link-alt mr-1"></i>Voir
                    </a>
                    <button onclick="removeZigbeeDevice('<?= htmlspecialchars($dev['ieee']) ?>')"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition hover:opacity-80"
                            style="background:rgba(239,68,68,.1);color:var(--accent-danger)">
                        <i class="fas fa-unlink"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<script type="module">
import { startPermitJoin, stopPermitJoin, pairDevice as apiPair,
         removeDevice, syncDevices as apiSync, fetchZigbeeStatus } from '/js/zigbee.js';
import { showToast } from '/js/ui/notifications.js';

// Vérifier le statut HA au chargement
fetchZigbeeStatus().then(status => {
    const dot  = document.getElementById('ha-status-dot');
    const text = document.getElementById('ha-status-text');
    if (!status) return;
    dot.style.background  = status.ha_reachable ? 'var(--status-online)' : 'var(--status-offline)';
    text.textContent      = status.ha_reachable ? 'Connecté' : 'Hors ligne';
    document.getElementById('count-discovered').textContent = status.total_devices;
    document.getElementById('count-paired').textContent     = status.paired_devices;
});

window.startPairing = async () => {
    const ok = await startPermitJoin(60, (sec) => {
        const el = document.getElementById('pairing-countdown');
        if (el) el.textContent = sec > 0 ? `Fermeture dans ${sec}s` : '';
        if (sec === 0) {
            document.getElementById('pairing-status-text').textContent = 'Fermé';
            document.getElementById('pairing-status-text').style.color = 'var(--text-muted)';
            document.getElementById('btn-permit').disabled = false;
            document.getElementById('btn-deny').disabled   = true;
        }
    });
    if (ok) {
        document.getElementById('pairing-status-text').textContent = 'OUVERT';
        document.getElementById('pairing-status-text').style.color = 'var(--accent-success)';
        document.getElementById('btn-permit').disabled = true;
        document.getElementById('btn-deny').disabled   = false;
    }
};

window.stopPairing = async () => {
    await stopPermitJoin();
    document.getElementById('pairing-status-text').textContent = 'Fermé';
    document.getElementById('pairing-status-text').style.color = 'var(--text-muted)';
    document.getElementById('pairing-countdown').textContent   = '';
    document.getElementById('btn-permit').disabled = false;
    document.getElementById('btn-deny').disabled   = true;
};

window.pairDevice = async (ieee, name) => {
    const roomId = null; // TODO: modal de sélection de pièce
    const result = await apiPair(ieee, { name: name || undefined, room_id: roomId });
    if (result) {
        const card = document.querySelector(`[data-ieee="${ieee}"]`);
        if (card) {
            card.dataset.paired = '1';
            card.querySelector('button')?.remove();
            // Marquer comme intégré visuellement
            card.querySelector('.text-yellow-400')?.classList.replace('text-yellow-400','text-green-400');
            card.querySelector('.text-yellow-400 ~ span')?.remove();
        }
        document.getElementById('count-paired').textContent =
            String(parseInt(document.getElementById('count-paired').textContent) + 1);
    }
};

window.removeZigbeeDevice = async (ieee) => {
    const ok = await removeDevice(ieee);
    if (ok) {
        document.querySelector(`[data-ieee="${ieee}"]`)?.remove();
    }
};

window.syncDevices = async () => {
    const icon = document.getElementById('sync-icon');
    icon.classList.add('fa-spin');
    const devices = await apiSync();
    icon.classList.remove('fa-spin');
    if (devices.length > 0) location.reload();
};

window.filterDevices = (filter) => {
    document.querySelectorAll('.zigbee-device-card').forEach(c => {
        const paired = c.dataset.paired === '1';
        c.style.display = (
            filter === 'all' ||
            (filter === 'paired' && paired) ||
            (filter === 'new' && !paired)
        ) ? '' : 'none';
    });
    document.querySelectorAll('.filter-btn').forEach(b => {
        const active = b.dataset.filter === filter;
        b.style.background = active ? 'var(--accent-primary)' : '';
        b.style.color      = active ? 'white' : 'var(--text-secondary)';
    });
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
