<?php
/**
 * DOMOGLASS PRO — Page de gestion des devices
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Appareils';
$activePage = 'devices';

$devices      = db()->fetchAll('SELECT d.*, r.name as room_name, r.slug as room_slug FROM devices d LEFT JOIN rooms r ON r.id = d.room_id WHERE d.enabled = 1 ORDER BY d.sort_order ASC, d.name ASC');
$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-plug mr-3" style="color: var(--accent-primary)"></i>
                Appareils
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Gestion de tous les appareils domotiques
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openAddDeviceModal()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:shadow-lg transition-all">
                <i class="fas fa-plus mr-2"></i>Ajouter un appareil
            </button>
            <button onclick="refreshDevices()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80 transition-all">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Filtres et recherche -->
    <div class="glass-card rounded-2xl p-4 mb-6">
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2" style="color: var(--text-muted)"></i>
                    <input type="text" id="device-search" placeholder="Rechercher un appareil..." 
                           class="w-full pl-10 pr-4 py-2 rounded-xl border border-border-color bg-transparent text-primary placeholder-muted"
                           onkeyup="filterDevices()">
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
                <select id="room-filter" onchange="filterDevices()" class="flex-1 px-4 py-2 pr-8 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Toutes les pièces&nbsp;&nbsp;</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= htmlspecialchars($r['slug']) ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="type-filter" onchange="filterDevices()" class="flex-1 px-4 py-2 pr-8 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Tous les types&nbsp;&nbsp;</option>
                    <option value="light">Lumières</option>
                    <option value="switch">Interrupteurs</option>
                    <option value="thermostat">Thermostats</option>
                    <option value="sensor">Capteurs</option>
                    <option value="camera">Caméras</option>
                    <option value="media">Multimédia</option>
                    <option value="security">Sécurité</option>
                </select>
                <select id="protocol-filter" onchange="filterDevices()" class="flex-1 px-4 py-2 pr-8 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Tous les protocoles&nbsp;&nbsp;</option>
                    <option value="mqtt">MQTT</option>
                    <option value="zigbee">Zigbee</option>
                    <option value="wifi">WiFi</option>
                    <option value="http">HTTP</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Grille des devices -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="devices-grid">
        <?php if (empty($devices)): ?>
        <div class="col-span-full glass-card rounded-2xl p-12 text-center">
            <i class="fas fa-plug text-5xl mb-4 opacity-30" style="color: var(--text-muted)"></i>
            <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary)">Aucun appareil</h3>
            <p class="text-sm mb-4" style="color: var(--text-muted)">Commencez par ajouter votre premier appareil.</p>
            <button onclick="openAddDeviceModal()" class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                <i class="fas fa-plus mr-2"></i>Ajouter un appareil
            </button>
        </div>
        <?php else: foreach ($devices as $d): ?>
        <div class="glass-card rounded-2xl p-4 device-card" 
             data-device-id="<?= $d['id'] ?>"
             data-name="<?= htmlspecialchars($d['name']) ?>"
             data-room="<?= htmlspecialchars($d['room_slug'] ?? '') ?>"
             data-type="<?= htmlspecialchars($d['type']) ?>"
             data-protocol="<?= htmlspecialchars($d['protocol']) ?>"
             data-state="<?= htmlspecialchars($d['state']) ?>">
            
            <!-- En-tête du device -->
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= htmlspecialchars($d['color']) ?> flex items-center justify-center text-white">
                        <i class="fas <?= htmlspecialchars($d['icon']) ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-primary)"><?= htmlspecialchars($d['name']) ?></h3>
                        <p class="text-xs" style="color: var(--text-muted)"><?= htmlspecialchars($d['room_name'] ?? 'Non assigné') ?></p>
                    </div>
                </div>
                <div class="flex items-center space-x-1">
                    <button onclick="editDevice(<?= $d['id'] ?>)" class="p-1 rounded hover:bg-opacity-10" style="color: var(--text-muted)">
                        <i class="fas fa-edit text-xs"></i>
                    </button>
                    <button onclick="deleteDevice(<?= $d['id'] ?>)" class="p-1 rounded hover:bg-opacity-10" style="color: var(--text-muted)">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- État et contrôles -->
            <div class="space-y-2">
                <!-- Toggle pour les devices on/off -->
                <?php if (in_array($d['type'], ['light', 'switch'])): ?>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium" style="color: <?= $d['state'] === 'on' ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                        <?= $d['state'] === 'on' ? '● Allumé' : '○ Éteint' ?>
                    </span>
                    <button class="toggle-switch <?= $d['state'] === 'on' ? 'active' : '' ?>"
                            onclick="toggleDevice(<?= $d['id'] ?>)"
                            role="switch"
                            aria-checked="<?= $d['state'] === 'on' ? 'true' : 'false' ?>">
                    </button>
                </div>
                <?php endif; ?>

                <!-- Informations -->
                <div class="flex items-center justify-between text-xs" style="color: var(--text-muted)">
                    <span class="flex items-center">
                        <i class="fas <?= $d['protocol'] === 'zigbee' ? 'fa-broadcast-tower' : 'fa-wifi' ?> mr-1"></i>
                        <?= ucfirst(htmlspecialchars($d['protocol'])) ?>
                    </span>
                    <span class="flex items-center">
                        <i class="fas fa-tag mr-1"></i>
                        <?= ucfirst(htmlspecialchars($d['type'])) ?>
                    </span>
                </div>

                <!-- État détaillé si disponible -->
                <?php if ($d['state_data']): ?>
                <?php $stateData = json_decode($d['state_data'], true); ?>
                <?php if (isset($stateData['brightness']) && $d['type'] === 'light'): ?>
                <div class="mt-2">
                    <label class="text-xs" style="color: var(--text-muted)">Luminosité</label>
                    <input type="range" min="1" max="254" value="<?= $stateData['brightness'] ?>" 
                           onchange="setBrightness(<?= $d['id'] ?>, this.value)"
                           class="w-full h-1 rounded-lg appearance-none cursor-pointer"
                           style="background: var(--bg-tertiary)">
                    <div class="text-xs text-center mt-1" style="color: var(--text-muted)">
                        <?= round(($stateData['brightness'] / 254) * 100) ?>%
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>

<!-- Modal d'ajout/modification de device -->
<div id="device-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="glass-card rounded-2xl p-6 max-w-md w-full mx-4">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-plug mr-2"></i>
            <span id="modal-title">Ajouter un appareil</span>
        </h2>
        
        <form id="device-form" class="space-y-4">
            <input type="hidden" id="device-id">
            
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Nom</label>
                <input type="text" id="device-name" required 
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Type</label>
                <select id="device-type" required class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="light">Lumière</option>
                    <option value="switch">Interrupteur</option>
                    <option value="thermostat">Thermostat</option>
                    <option value="sensor">Capteur</option>
                    <option value="camera">Caméra</option>
                    <option value="media">Multimédia</option>
                    <option value="security">Sécurité</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Protocole</label>
                <select id="device-protocol" required onchange="updateProtocolFields()" 
                        class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="mqtt">MQTT</option>
                    <option value="zigbee">Zigbee</option>
                    <option value="wifi">WiFi</option>
                    <option value="http">HTTP</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Pièce</label>
                <select id="device-room" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Non assigné</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Champs MQTT -->
            <div id="mqtt-fields" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Topic d'état</label>
                    <input type="text" id="mqtt-topic-state" placeholder="ex: zigbee2mqtt/salon/lumiere"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Topic de commande</label>
                    <input type="text" id="mqtt-topic-set" placeholder="ex: zigbee2mqtt/salon/lumiere/set"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>
            
            <!-- Champs Zigbee -->
            <div id="zigbee-fields" class="space-y-3 hidden">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Entité Home Assistant</label>
                    <input type="text" id="ha-entity-id" placeholder="ex: light.salon_lumiere"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Adresse IEEE</label>
                    <input type="text" id="zigbee-ieee" placeholder="ex: 0x00158d0001234567"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>
            
            <div class="flex items-center space-x-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
                <button type="button" onclick="closeDeviceModal()" 
                        class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables globales
let devices = <?= json_encode($devices) ?>;
let currentEditId = null;

// Filtrage des devices
function filterDevices() {
    const search = document.getElementById('device-search').value.toLowerCase();
    const room = document.getElementById('room-filter').value;
    const type = document.getElementById('type-filter').value;
    const protocol = document.getElementById('protocol-filter').value;
    
    document.querySelectorAll('.device-card').forEach(card => {
        const name = card.dataset.name?.toLowerCase() || '';
        const cardRoom = card.dataset.room || '';
        const cardType = card.dataset.type || '';
        const cardProtocol = card.dataset.protocol || '';
        
        const matches = (!search || name.includes(search)) &&
                       (!room || cardRoom === room) &&
                       (!type || cardType === type) &&
                       (!protocol || cardProtocol === protocol);
        
        card.style.display = matches ? '' : 'none';
    });
}

// Toggle device
async function toggleDevice(id) {
    const card = document.querySelector(`[data-device-id="${id}"]`);
    const btn = card?.querySelector('.toggle-switch');
    if (!card || !btn) return;
    
    const isOn = btn.classList.contains('active');
    const newState = isOn ? 'off' : 'on';
    
    // Animation immédiate
    btn.classList.toggle('active');
    btn.setAttribute('aria-checked', String(!isOn));
    
    try {
        const res = await fetch(`/api/devices.php?action=command&id=${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({
                payload: { state: newState.toUpperCase() }
            })
        });
        
        const json = await res.json();
        if (!json.success) {
            // Revenir en cas d'erreur
            btn.classList.toggle('active');
            btn.setAttribute('aria-checked', String(isOn));
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        } else {
            // Mettre à jour l'état affiché
            card.dataset.state = newState;
            const stateText = card.querySelector('.text-xs.font-medium');
            if (stateText) {
                stateText.textContent = newState === 'on' ? '● Allumé' : '○ Éteint';
                stateText.style.color = newState === 'on' ? 'var(--accent-success)' : 'var(--text-muted)';
            }
        }
    } catch (error) {
        btn.classList.toggle('active');
        btn.setAttribute('aria-checked', String(isOn));
        showToast('Erreur réseau', 'error');
    }
}

// Luminosité
async function setBrightness(id, brightness) {
    try {
        const res = await fetch(`/api/devices.php?action=command&id=${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({
                payload: { brightness: parseInt(brightness) }
            })
        });
        
        const json = await res.json();
        if (!json.success) {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Modal device
function openAddDeviceModal() {
    currentEditId = null;
    document.getElementById('modal-title').textContent = 'Ajouter un appareil';
    document.getElementById('device-form').reset();
    document.getElementById('device-modal').classList.remove('hidden');
    updateProtocolFields();
}

function editDevice(id) {
    currentEditId = id;
    const device = devices.find(d => d.id == id);
    if (!device) return;
    
    document.getElementById('modal-title').textContent = 'Modifier l\'appareil';
    document.getElementById('device-id').value = id;
    document.getElementById('device-name').value = device.name;
    document.getElementById('device-type').value = device.type;
    document.getElementById('device-protocol').value = device.protocol;
    document.getElementById('device-room').value = device.room_id || '';
    
    // MQTT
    document.getElementById('mqtt-topic-state').value = device.mqtt_topic_state || '';
    document.getElementById('mqtt-topic-set').value = device.mqtt_topic_set || '';
    
    // Zigbee
    document.getElementById('ha-entity-id').value = device.ha_entity_id || '';
    document.getElementById('zigbee-ieee').value = device.zigbee_ieee || '';
    
    updateProtocolFields();
    document.getElementById('device-modal').classList.remove('hidden');
}

function closeDeviceModal() {
    document.getElementById('device-modal').classList.add('hidden');
}

function updateProtocolFields() {
    const protocol = document.getElementById('device-protocol').value;
    const mqttFields = document.getElementById('mqtt-fields');
    const zigbeeFields = document.getElementById('zigbee-fields');
    
    if (protocol === 'mqtt') {
        mqttFields.classList.remove('hidden');
        zigbeeFields.classList.add('hidden');
    } else if (protocol === 'zigbee') {
        mqttFields.classList.add('hidden');
        zigbeeFields.classList.remove('hidden');
    } else {
        mqttFields.classList.add('hidden');
        zigbeeFields.classList.add('hidden');
    }
}

// Sauvegarde device
document.getElementById('device-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = {
        name: document.getElementById('device-name').value,
        type: document.getElementById('device-type').value,
        protocol: document.getElementById('device-protocol').value,
        room_id: document.getElementById('device-room').value || null,
        mqtt_topic_state: document.getElementById('mqtt-topic-state').value || null,
        mqtt_topic_set: document.getElementById('mqtt-topic-set').value || null,
        ha_entity_id: document.getElementById('ha-entity-id').value || null,
        zigbee_ieee: document.getElementById('zigbee-ieee').value || null
    };
    
    try {
        const url = currentEditId ? `/api/devices.php?id=${currentEditId}` : '/api/devices.php';
        const method = currentEditId ? 'PUT' : 'POST';
        
        const res = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify(formData)
        });
        
        const json = await res.json();
        if (json.success) {
            showToast(currentEditId ? 'Appareil modifié' : 'Appareil ajouté', 'success');
            closeDeviceModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
});

// Suppression device
async function deleteDevice(id) {
    if (!confirm('Supprimer cet appareil ?')) return;
    
    try {
        const res = await fetch(`/api/devices.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            showToast('Appareil supprimé', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Rafraîchissement
function refreshDevices() {
    location.reload();
}

// Toast helper
function showToast(message, type = 'info') {
    // Implémentation simple du toast
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 px-4 py-2 rounded-xl text-white z-50';
    toast.style.background = type === 'success' ? 'var(--accent-success)' : 
                              type === 'error' ? 'var(--accent-error)' : 'var(--accent-info)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
