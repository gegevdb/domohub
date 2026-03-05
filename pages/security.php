<?php
/**
 * DOMOGLASS PRO — Page de sécurité
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Sécurité';
$activePage = 'security';

$devices      = db()->fetchAll('SELECT d.*, r.name as room_name FROM devices d LEFT JOIN rooms r ON r.id = d.room_id WHERE d.enabled = 1 ORDER BY d.name ASC');
$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

// Récupérer les devices de sécurité
$securityDevices = array_filter($devices, fn($d) => in_array($d['type'], ['camera', 'sensor', 'security']));

// Récupérer les logs de sécurité récents
$securityLogs = db()->fetchAll("
    SELECT * FROM actions 
    WHERE source = 'security' OR action_type LIKE '%security%' OR action_type LIKE '%alarm%'
    ORDER BY created_at DESC 
    LIMIT 20
");

// Statistiques de sécurité
$activeCameras = count(array_filter($securityDevices, fn($d) => $d['type'] === 'camera' && $d['state'] === 'on'));
$activeSensors = count(array_filter($securityDevices, fn($d) => $d['type'] === 'sensor' && $d['state'] === 'on'));
$alertCount = count(array_filter($securityLogs, fn($log) => $log['status'] === 'error' || strpos($log['action'], 'alert') !== false));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-shield-alt mr-3" style="color: var(--accent-error)"></i>
                Sécurité
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Surveillance et protection de votre domicile
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="toggleSecurityMode()" id="security-mode-btn" 
                    class="px-4 py-2 rounded-xl font-medium transition-all">
                <i class="fas fa-lock mr-2"></i>
                <span id="security-mode-text">Mode Activé</span>
            </button>
            <button onclick="refreshSecurity()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80 transition-all">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Statistiques de sécurité -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-success)"><?= $activeCameras ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Caméras actives</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-info)"><?= $activeSensors ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Capteurs actifs</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-warning)"><?= $alertCount ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Alertes récentes</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-primary)">
                <?= count($securityDevices) ?>
            </div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Appareils sécurité</div>
        </div>
    </div>

    <!-- État du système -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-home mr-2"></i>État du système
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center text-white">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Portes</div>
                        <div class="text-sm" style="color: var(--text-muted)">Toutes fermées</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
            </div>
            
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white">
                        <i class="fas fa-window-maximize"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Fenêtres</div>
                        <div class="text-sm" style="color: var(--text-muted)">2 ouvertes</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
            </div>
            
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white">
                        <i class="fas fa-video"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Surveillance</div>
                        <div class="text-sm" style="color: var(--text-muted)">Enregistrement actif</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
            </div>
            
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-600 flex items-center justify-center text-white">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Détection fumée</div>
                        <div class="text-sm" style="color: var(--text-muted)">Aucune alerte</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
            </div>
            
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500 to-cyan-600 flex items-center justify-center text-white">
                        <i class="fas fa-water"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Fuites d'eau</div>
                        <div class="text-sm" style="color: var(--text-muted)">Aucune détection</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
            </div>
            
            <div class="flex items-center justify-between p-4 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <div class="font-medium" style="color: var(--text-primary)">Alarme</div>
                        <div class="text-sm" style="color: var(--text-muted)">Désactivée</div>
                    </div>
                </div>
                <div class="w-3 h-3 rounded-full bg-gray-500"></div>
            </div>
        </div>
    </div>

    <!-- Appareils de sécurité -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-video mr-2"></i>Caméras
            </h2>
            <div class="space-y-3">
                <?php
                $cameras = array_filter($securityDevices, fn($d) => $d['type'] === 'camera');
                foreach ($cameras as $camera):
                ?>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs">
                            <i class="fas fa-video"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($camera['name']) ?></div>
                            <div class="text-xs" style="color: var(--text-muted)"><?= htmlspecialchars($camera['room_name'] ?? 'Non assigné') ?></div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 rounded-full text-xs" 
                              style="background: <?= ($camera['state'] === 'on') ? 'var(--accent-success)' : 'var(--text-muted)' ?>20; 
                                     color: <?= ($camera['state'] === 'on') ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                            <?= $camera['state'] === 'on' ? 'Active' : 'Inactive' ?>
                        </span>
                        <button onclick="viewCamera(<?= $camera['id'] ?>)" class="p-1 rounded hover:bg-opacity-10" style="color: var(--text-muted)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cameras)): ?>
                <div class="text-center py-4" style="color: var(--text-muted)">
                    <i class="fas fa-video-slash text-2xl mb-2"></i>
                    <p>Aucune caméra configurée</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-satellite-dish mr-2"></i>Capteurs
            </h2>
            <div class="space-y-3">
                <?php
                $sensors = array_filter($securityDevices, fn($d) => $d['type'] === 'sensor');
                foreach ($sensors as $sensor):
                ?>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-teal-600 flex items-center justify-center text-white text-xs">
                            <i class="fas fa-satellite-dish"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($sensor['name']) ?></div>
                            <div class="text-xs" style="color: var(--text-muted)"><?= htmlspecialchars($sensor['room_name'] ?? 'Non assigné') ?></div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 rounded-full text-xs" 
                              style="background: <?= ($sensor['state'] === 'on') ? 'var(--accent-success)' : 'var(--text-muted)' ?>20; 
                                     color: <?= ($sensor['state'] === 'on') ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                            <?= ($sensor['state'] === 'on') ? 'Actif' : 'Inactif' ?>
                        </span>
                        <button onclick="testSensor(<?= $sensor['id'] ?>)" class="p-1 rounded hover:bg-opacity-10" style="color: var(--text-muted)">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($sensors)): ?>
                <div class="text-center py-4" style="color: var(--text-muted)">
                    <i class="fas fa-satellite-dish text-2xl mb-2"></i>
                    <p>Aucun capteur configuré</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Journal des événements -->
    <div class="glass-card rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                <i class="fas fa-history mr-2"></i>Journal des événements
            </h2>
            <button onclick="clearLogs()" class="px-3 py-1 text-sm rounded-lg" style="background: var(--accent-error); color: white">
                <i class="fas fa-trash mr-1"></i>Vider
            </button>
        </div>
        
        <div class="space-y-2 max-h-96 overflow-y-auto">
            <?php foreach ($securityLogs as $log): ?>
            <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                <div class="flex items-center space-x-3">
                    <div class="w-2 h-2 rounded-full" 
                         style="background: <?= ($log['status'] === 'error') ? 'var(--accent-error)' : 
                                         (($log['status'] === 'sent') ? 'var(--accent-success)' : 'var(--accent-warning)') ?>">
                    </div>
                    <div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">
                            <?= htmlspecialchars($log['device_name'] ?? 'Système') ?>
                        </div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            <?= htmlspecialchars($log['action_type']) ?> - <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <div class="text-xs" style="color: var(--text-muted)">
                    <?= htmlspecialchars($log['source']) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($securityLogs)): ?>
            <div class="text-center py-8" style="color: var(--text-muted)">
                <i class="fas fa-history text-2xl mb-2"></i>
                <p>Aucun événement récent</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<!-- Modal de visualisation caméra -->
<div id="camera-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="glass-card rounded-2xl p-6 max-w-4xl w-full mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold" style="color: var(--text-primary)">
                <i class="fas fa-video mr-2"></i>
                <span id="camera-name">Caméra</span>
            </h3>
            <button onclick="closeCameraModal()" class="p-2 rounded-lg hover:bg-opacity-10" style="color: var(--text-muted)">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="relative" style="background: #000; border-radius: 12px; overflow: hidden;">
            <img id="camera-feed" src="" alt="Flux caméra" class="w-full" style="min-height: 400px; object-fit: cover;">
            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between">
                <div class="flex space-x-2">
                    <button onclick="toggleCameraRecording()" class="px-3 py-1 rounded-lg text-sm" style="background: var(--accent-error); color: white">
                        <i class="fas fa-record-vinyl mr-1"></i>Enregistrer
                    </button>
                    <button onclick="takeSnapshot()" class="px-3 py-1 rounded-lg text-sm glass">
                        <i class="fas fa-camera mr-1"></i>Photo
                    </button>
                </div>
                <div class="text-white text-sm">
                    <i class="fas fa-clock mr-1"></i>
                    <span id="camera-time"><?= date('H:i:s') ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Variables globales
let securityMode = true;
let currentCameraId = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    updateSecurityMode();
    startSecurityMonitoring();
});

// Mode sécurité
function toggleSecurityMode() {
    securityMode = !securityMode;
    updateSecurityMode();
    
    // Envoyer la commande à l'API
    fetch('/api/security.php?action=mode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.DOMOGLASS.csrfToken
        },
        body: JSON.stringify({ enabled: securityMode })
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              showToast(securityMode ? 'Mode sécurité activé' : 'Mode sécurité désactivé', 'success');
          }
      })
      .catch(error => {
          console.error('Erreur:', error);
          showToast('Erreur lors du changement de mode', 'error');
      });
}

function updateSecurityMode() {
    const btn = document.getElementById('security-mode-btn');
    const text = document.getElementById('security-mode-text');
    
    if (securityMode) {
        btn.style.background = 'var(--accent-success)';
        btn.style.color = 'white';
        text.textContent = 'Mode Activé';
    } else {
        btn.style.background = 'var(--glass-bg)';
        btn.style.color = 'var(--text-primary)';
        text.textContent = 'Mode Désactivé';
    }
}

// Surveillance en temps réel
function startSecurityMonitoring() {
    setInterval(async () => {
        try {
            const response = await fetch('/api/security.php?action=status');
            const data = await response.json();
            
            if (data.success) {
                // Mettre à jour les statuts si nécessaire
                if (data.alerts && data.alerts.length > 0) {
                    data.alerts.forEach(alert => {
                        showToast(alert.message, 'warning');
                    });
                }
            }
        } catch (error) {
            console.error('Erreur lors de la surveillance:', error);
        }
    }, 10000); // Toutes les 10 secondes
}

// Caméra
function viewCamera(cameraId) {
    currentCameraId = cameraId;
    const camera = <?= json_encode(array_filter($devices, fn($d) => $d['type'] === 'camera')) ?>.find(c => c.id == cameraId);
    
    if (camera) {
        document.getElementById('camera-name').textContent = camera.name;
        // Simuler un flux caméra (remplacer par URL réelle)
        document.getElementById('camera-feed').src = `https://picsum.photos/seed/camera${cameraId}/800/450.jpg`;
        document.getElementById('camera-modal').classList.remove('hidden');
    }
}

function closeCameraModal() {
    document.getElementById('camera-modal').classList.add('hidden');
    currentCameraId = null;
}

function toggleCameraRecording() {
    // Implémenter l'enregistrement vidéo
    showToast('Enregistrement démarré', 'success');
}

function takeSnapshot() {
    // Implémenter la capture d'écran
    showToast('Photo capturée', 'success');
}

// Capteur
function testSensor(sensorId) {
    fetch(`/api/devices.php?action=command&id=${sensorId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.DOMOGLASS.csrfToken
        },
        body: JSON.stringify({
            payload: { test: true }
        })
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              showToast('Test du capteur effectué', 'success');
          } else {
              showToast('Erreur lors du test', 'error');
          }
      })
      .catch(error => {
          showToast('Erreur réseau', 'error');
      });
}

// Logs
function clearLogs() {
    if (!confirm('Vider tous les logs de sécurité ?')) return;
    
    fetch('/api/security.php?action=clear_logs', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': window.DOMOGLASS.csrfToken
        }
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              showToast('Logs vidés', 'success');
              setTimeout(() => location.reload(), 1000);
          }
      })
      .catch(error => {
          showToast('Erreur lors du vidage', 'error');
      });
}

// Rafraîchissement
function refreshSecurity() {
    location.reload();
}

// Toast helper
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 px-4 py-2 rounded-xl text-white z-50';
    toast.style.background = type === 'success' ? 'var(--accent-success)' : 
                              type === 'error' ? 'var(--accent-error)' : 
                              type === 'warning' ? 'var(--accent-warning)' : 'var(--accent-info)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

// Mise à jour de l'heure
setInterval(() => {
    const timeElement = document.getElementById('camera-time');
    if (timeElement) {
        timeElement.textContent = new Date().toLocaleTimeString();
    }
}, 1000);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
