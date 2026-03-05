<?php
/**
 * DOMOGLASS PRO — Page de gestion des automatisations
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Automatisations';
$activePage = 'automations';

$automations  = db()->fetchAll('SELECT * FROM automations ORDER BY created_at DESC');
$devices      = db()->fetchAll('SELECT d.*, r.name as room_name FROM devices d LEFT JOIN rooms r ON r.id = d.room_id WHERE d.enabled = 1 ORDER BY d.name ASC');
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
                <i class="fas fa-robot mr-3" style="color: var(--accent-primary)"></i>
                Automatisations
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Créez des scénarios intelligents pour votre maison
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <button onclick="openAutomationModal()" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl hover:shadow-lg transition-all">
                <i class="fas fa-plus mr-2"></i>Nouvelle automation
            </button>
            <button onclick="refreshAutomations()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80 transition-all">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--accent-primary)"><?= count($automations) ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Total automatisations</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--accent-success)"><?= count(array_filter($automations, fn($a) => $a['enabled'])) ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Actives</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--accent-warning)"><?= array_sum(array_column($automations, 'run_count')) ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Exécutions aujourd'hui</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--accent-info)"><?= count(array_filter($automations, fn($a) => $a['last_run'])) ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Exécutées récemment</div>
        </div>
    </div>

    <!-- Liste des automatisations -->
    <div class="space-y-4" id="automations-list">
        <?php if (empty($automations)): ?>
        <div class="glass-card rounded-2xl p-12 text-center">
            <i class="fas fa-robot text-5xl mb-4 opacity-30" style="color: var(--text-muted)"></i>
            <h3 class="text-xl font-semibold mb-2" style="color: var(--text-primary)">Aucune automation</h3>
            <p class="text-sm mb-4" style="color: var(--text-muted)">Créez votre première automation pour commencer.</p>
            <button onclick="openAutomationModal()" class="px-6 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl">
                <i class="fas fa-plus mr-2"></i>Créer une automation
            </button>
        </div>
        <?php else: foreach ($automations as $a): ?>
        <div class="glass-card rounded-2xl p-6 automation-card" data-automation-id="<?= $a['id'] ?>">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <h3 class="text-lg font-semibold" style="color: var(--text-primary)"><?= htmlspecialchars($a['name']) ?></h3>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 rounded-lg text-xs font-medium" 
                                  style="background: <?= $a['enabled'] ? 'var(--accent-success)' : 'var(--text-muted)' ?>20; color: <?= $a['enabled'] ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                                <?= $a['enabled'] ? 'Active' : 'Inactive' ?>
                            </span>
                            <span class="px-2 py-1 rounded-lg text-xs font-medium" 
                                  style="background: var(--accent-primary)20; color: var(--accent-primary)">
                                <?= ucfirst(htmlspecialchars($a['trigger_type'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($a['description']): ?>
                    <p class="text-sm mb-3" style="color: var(--text-muted)"><?= htmlspecialchars($a['description']) ?></p>
                    <?php endif; ?>
                    
                    <!-- Détails du trigger -->
                    <div class="mb-3">
                        <div class="text-xs font-medium mb-1" style="color: var(--text-muted)">Déclencheur</div>
                        <div class="text-sm" style="color: var(--text-primary)">
                            <?php
                            $triggerConfig = json_decode($a['trigger_config'], true) ?: [];
                            switch ($a['trigger_type']) {
                                case 'time':
                                    echo '<i class="fas fa-clock mr-2"></i>' . htmlspecialchars($triggerConfig['time'] ?? 'Non défini');
                                    if (!empty($triggerConfig['days'])) {
                                        $days = ['L','M','M','J','V','S','D'];
                                        $selected = array_intersect_key($days, array_flip($triggerConfig['days']));
                                        echo ' (' . implode(',', $selected) . ')';
                                    }
                                    break;
                                case 'device_state':
                                    $device = $devices[array_search($triggerConfig['device_id'] ?? 0, array_column($devices, 'id'))] ?? null;
                                    echo '<i class="fas fa-plug mr-2"></i>' . ($device ? htmlspecialchars($device['name']) : 'Device inconnu');
                                    echo ' → ' . htmlspecialchars($triggerConfig['state'] ?? '???');
                                    break;
                                case 'sunrise':
                                    echo '<i class="fas fa-sun mr-2"></i>Lever du soleil';
                                    if (!empty($triggerConfig['offset'])) {
                                        echo ' (' . ($triggerConfig['offset'] > 0 ? '+' : '') . $triggerConfig['offset'] . ' min)';
                                    }
                                    break;
                                case 'sunset':
                                    echo '<i class="fas fa-moon mr-2"></i>Coucher du soleil';
                                    if (!empty($triggerConfig['offset'])) {
                                        echo ' (' . ($triggerConfig['offset'] > 0 ? '+' : '') . $triggerConfig['offset'] . ' min)';
                                    }
                                    break;
                                default:
                                    echo '<i class="fas fa-question mr-2"></i>' . ucfirst(htmlspecialchars($a['trigger_type']));
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mb-3">
                        <div class="text-xs font-medium mb-1" style="color: var(--text-muted)">Actions</div>
                        <div class="flex flex-wrap gap-2">
                            <?php
                            $actions = json_decode($a['actions'], true) ?: [];
                            foreach ($actions as $action) {
                                $icon = 'fa-cog';
                                $label = 'Action';
                                
                                if ($action['type'] === 'device_command') {
                                    $device = $devices[array_search($action['device_id'] ?? 0, array_column($devices, 'id'))] ?? null;
                                    $icon = 'fa-plug';
                                    $label = $device ? htmlspecialchars($device['name']) : 'Device inconnu';
                                    if (!empty($action['payload']['state'])) {
                                        $label .= ' → ' . ucfirst($action['payload']['state']);
                                    }
                                } elseif ($action['type'] === 'notification') {
                                    $icon = 'fa-bell';
                                    $label = 'Notification: ' . htmlspecialchars($action['message'] ?? '');
                                } elseif ($action['type'] === 'delay') {
                                    $icon = 'fa-clock';
                                    $label = 'Délai: ' . ($action['seconds'] ?? 0) . 's';
                                }
                                
                                echo '<span class="px-2 py-1 rounded-lg text-xs" style="background: var(--bg-tertiary); color: var(--text-secondary)">';
                                echo '<i class="fas ' . $icon . ' mr-1"></i>' . $label;
                                echo '</span>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Statistiques -->
                    <div class="flex items-center space-x-4 text-xs" style="color: var(--text-muted)">
                        <span><i class="fas fa-play mr-1"></i><?= $a['run_count'] ?> exécutions</span>
                        <?php if ($a['last_run']): ?>
                        <span><i class="fas fa-history mr-1"></i>Dernière: <?= date('d/m H:i', strtotime($a['last_run'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contrôles -->
                <div class="flex items-center space-x-2 ml-4">
                    <button onclick="toggleAutomation(<?= $a['id'] ?>, <?= $a['enabled'] ? 'false' : 'true' ?>)" 
                            class="p-2 rounded-lg hover:bg-opacity-10 transition-all"
                            style="color: var(--text-muted)"
                            title="<?= $a['enabled'] ? 'Désactiver' : 'Activer' ?>">
                        <i class="fas <?= $a['enabled'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                    </button>
                    <button onclick="testAutomation(<?= $a['id'] ?>)" 
                            class="p-2 rounded-lg hover:bg-opacity-10 transition-all"
                            style="color: var(--text-muted)"
                            title="Tester">
                        <i class="fas fa-play"></i>
                    </button>
                    <button onclick="editAutomation(<?= $a['id'] ?>)" 
                            class="p-2 rounded-lg hover:bg-opacity-10 transition-all"
                            style="color: var(--text-muted)"
                            title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteAutomation(<?= $a['id'] ?>)" 
                            class="p-2 rounded-lg hover:bg-opacity-10 transition-all"
                            style="color: var(--text-muted)"
                            title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>

<!-- Modal de création/modification d'automation -->
<div id="automation-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="glass-card rounded-2xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-xl font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-robot mr-2"></i>
            <span id="modal-title">Nouvelle automation</span>
        </h2>
        
        <form id="automation-form" class="space-y-6">
            <input type="hidden" id="automation-id">
            
            <!-- Informations générales -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Nom *</label>
                    <input type="text" id="automation-name" required 
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary"
                           placeholder="Ex: Allumage automatique du salon">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Description</label>
                    <input type="text" id="automation-description" 
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary"
                           placeholder="Optionnel">
                </div>
            </div>
            
            <!-- Type de déclencheur -->
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Type de déclencheur *</label>
                <select id="trigger-type" required onchange="updateTriggerFields()" 
                        class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Sélectionner...</option>
                    <option value="time">Horaire</option>
                    <option value="device_state">État d'un appareil</option>
                    <option value="sunrise">Lever du soleil</option>
                    <option value="sunset">Coucher du soleil</option>
                    <option value="webhook">Webhook</option>
                </select>
            </div>
            
            <!-- Configuration du déclencheur -->
            <div id="trigger-config" class="space-y-4">
                <!-- Champs horaires -->
                <div id="time-fields" class="hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Heure</label>
                            <input type="time" id="trigger-time" 
                                   class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Jours</label>
                            <div class="flex space-x-2">
                                <?php foreach(['L','M','M','J','V','S','D'] as $i => $day): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="days[]" value="<?= $i ?>" 
                                           class="mr-1 rounded" style="accent-color: var(--accent-primary)">
                                    <span class="text-sm"><?= $day ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Champs device -->
                <div id="device-fields" class="hidden space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Appareil</label>
                            <select id="trigger-device" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                <option value="">Sélectionner...</option>
                                <?php foreach ($devices as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['room_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">État</label>
                            <select id="trigger-state" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                                <option value="on">Allumé</option>
                                <option value="off">Éteint</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Champs soleil -->
                <div id="sun-fields" class="hidden">
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Décalage (minutes)</label>
                    <input type="number" id="trigger-offset" min="-120" max="120" 
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary"
                           placeholder="0 = au moment du lever/coucher">
                </div>
            </div>
            
            <!-- Actions -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium" style="color: var(--text-primary)">Actions *</label>
                    <button type="button" onclick="addAction()" class="px-3 py-1 text-sm rounded-lg" style="background: var(--accent-primary); color: white">
                        <i class="fas fa-plus mr-1"></i>Ajouter
                    </button>
                </div>
                <div id="actions-list" class="space-y-2">
                    <!-- Les actions seront ajoutées dynamiquement -->
                </div>
            </div>
            
            <div class="flex items-center space-x-3 pt-4">
                <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
                <button type="button" onclick="closeAutomationModal()" 
                        class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'ajout d'action -->
<div id="action-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="glass-card rounded-2xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-plus mr-2"></i>Ajouter une action
        </h3>
        
        <form id="action-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Type d'action</label>
                <select id="action-type" required onchange="updateActionFields()" 
                        class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                    <option value="">Sélectionner...</option>
                    <option value="device_command">Commander un appareil</option>
                    <option value="notification">Envoyer une notification</option>
                    <option value="delay">Délai d'attente</option>
                    <option value="scene">Activer une scène</option>
                </select>
            </div>
            
            <div id="action-config">
                <!-- Champs dynamiques selon le type -->
            </div>
            
            <div class="flex space-x-3">
                <button type="submit" class="flex-1 px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    Ajouter
                </button>
                <button type="button" onclick="closeActionModal()" 
                        class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variables globales
let automations = <?= json_encode($automations) ?>;
let devices = <?= json_encode($devices) ?>;
let currentEditId = null;
let actions = [];

// Trigger fields
function updateTriggerFields() {
    const type = document.getElementById('trigger-type').value;
    const timeFields = document.getElementById('time-fields');
    const deviceFields = document.getElementById('device-fields');
    const sunFields = document.getElementById('sun-fields');
    
    // Cacher tous les champs
    timeFields.classList.add('hidden');
    deviceFields.classList.add('hidden');
    sunFields.classList.add('hidden');
    
    // Afficher les champs pertinents
    switch (type) {
        case 'time':
            timeFields.classList.remove('hidden');
            break;
        case 'device_state':
            deviceFields.classList.remove('hidden');
            break;
        case 'sunrise':
        case 'sunset':
            sunFields.classList.remove('hidden');
            break;
    }
}

// Action fields
function updateActionFields() {
    const type = document.getElementById('action-type').value;
    const config = document.getElementById('action-config');
    
    let html = '';
    
    switch (type) {
        case 'device_command':
            html = `
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Appareil</label>
                    <select id="action-device" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="">Sélectionner...</option>
                        ${devices.map(d => `<option value="${d.id}">${d.name} (${d.room_name})</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Commande</label>
                    <select id="action-command" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <option value="on">Allumer</option>
                        <option value="off">Éteindre</option>
                        <option value="toggle">Inverser</option>
                    </select>
                </div>
            `;
            break;
        case 'notification':
            html = `
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Message</label>
                    <textarea id="action-message" rows="3" 
                              class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary"
                              placeholder="Message de la notification..."></textarea>
                </div>
            `;
            break;
        case 'delay':
            html = `
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Délai (secondes)</label>
                    <input type="number" id="action-delay" min="1" 
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary"
                           placeholder="10">
                </div>
            `;
            break;
    }
    
    config.innerHTML = html;
}

// Actions management
function addAction() {
    document.getElementById('action-modal').classList.remove('hidden');
    document.getElementById('action-form').reset();
    updateActionFields();
}

function closeActionModal() {
    document.getElementById('action-modal').classList.add('hidden');
}

document.getElementById('action-form').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const type = document.getElementById('action-type').value;
    let action = { type };
    
    switch (type) {
        case 'device_command':
            action.device_id = parseInt(document.getElementById('action-device').value);
            action.payload = { state: document.getElementById('action-command').value };
            break;
        case 'notification':
            action.message = document.getElementById('action-message').value;
            break;
        case 'delay':
            action.seconds = parseInt(document.getElementById('action-delay').value);
            break;
    }
    
    actions.push(action);
    renderActions();
    closeActionModal();
});

function renderActions() {
    const list = document.getElementById('actions-list');
    
    if (actions.length === 0) {
        list.innerHTML = '<div class="text-center py-4" style="color: var(--text-muted)">Aucune action ajoutée</div>';
        return;
    }
    
    list.innerHTML = actions.map((action, index) => {
        let label = '';
        
        switch (action.type) {
            case 'device_command':
                const device = devices.find(d => d.id === action.device_id);
                label = `Device: ${device ? device.name : 'Inconnu'} → ${action.payload.state}`;
                break;
            case 'notification':
                label = `Notification: ${action.message}`;
                break;
            case 'delay':
                label = `Délai: ${action.seconds}s`;
                break;
        }
        
        return `
            <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                <span class="text-sm" style="color: var(--text-primary)">${index + 1}. ${label}</span>
                <button type="button" onclick="removeAction(${index})" 
                        class="p-1 rounded hover:bg-opacity-10" style="color: var(--text-muted)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }).join('');
}

function removeAction(index) {
    actions.splice(index, 1);
    renderActions();
}

// Modal automation
function openAutomationModal() {
    currentEditId = null;
    actions = [];
    document.getElementById('modal-title').textContent = 'Nouvelle automation';
    document.getElementById('automation-form').reset();
    renderActions();
    document.getElementById('automation-modal').classList.remove('hidden');
    updateTriggerFields();
}

function editAutomation(id) {
    currentEditId = id;
    const automation = automations.find(a => a.id == id);
    if (!automation) return;
    
    document.getElementById('modal-title').textContent = 'Modifier l\'automation';
    document.getElementById('automation-id').value = id;
    document.getElementById('automation-name').value = automation.name;
    document.getElementById('automation-description').value = automation.description || '';
    document.getElementById('trigger-type').value = automation.trigger_type;
    
    // Charger les actions
    actions = JSON.parse(automation.actions || '[]');
    renderActions();
    
    updateTriggerFields();
    document.getElementById('automation-modal').classList.remove('hidden');
}

function closeAutomationModal() {
    document.getElementById('automation-modal').classList.add('hidden');
}

// Sauvegarde automation
document.getElementById('automation-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    // Construire la configuration du trigger
    let triggerConfig = {};
    const triggerType = document.getElementById('trigger-type').value;
    
    switch (triggerType) {
        case 'time':
            triggerConfig.time = document.getElementById('trigger-time').value;
            triggerConfig.days = Array.from(document.querySelectorAll('input[name="days[]"]:checked')).map(cb => cb.value);
            break;
        case 'device_state':
            triggerConfig.device_id = parseInt(document.getElementById('trigger-device').value);
            triggerConfig.state = document.getElementById('trigger-state').value;
            break;
        case 'sunrise':
        case 'sunset':
            triggerConfig.offset = parseInt(document.getElementById('trigger-offset').value) || 0;
            break;
    }
    
    const formData = {
        name: document.getElementById('automation-name').value,
        description: document.getElementById('automation-description').value,
        trigger_type: triggerType,
        trigger_config: JSON.stringify(triggerConfig),
        actions: JSON.stringify(actions),
        enabled: true
    };
    
    try {
        const url = currentEditId ? `/api/automations.php?id=${currentEditId}` : '/api/automations.php';
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
            showToast(currentEditId ? 'Automation modifiée' : 'Automation créée', 'success');
            closeAutomationModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
});

// Toggle automation
async function toggleAutomation(id, enabled) {
    try {
        const res = await fetch(`/api/automations.php?id=${id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({ enabled })
        });
        
        const json = await res.json();
        if (json.success) {
            showToast(enabled ? 'Automation activée' : 'Automation désactivée', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Test automation
async function testAutomation(id) {
    try {
        const res = await fetch(`/api/automations.php?action=test&id=${id}`, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            showToast('Automation testée avec succès', 'success');
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Suppression automation
async function deleteAutomation(id) {
    if (!confirm('Supprimer cette automation ?')) return;
    
    try {
        const res = await fetch(`/api/automations.php?id=${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            }
        });
        
        const json = await res.json();
        if (json.success) {
            showToast('Automation supprimée', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Erreur: ' + (json.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
}

// Rafraîchissement
function refreshAutomations() {
    location.reload();
}

// Toast helper
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'fixed top-4 right-4 px-4 py-2 rounded-xl text-white z-50';
    toast.style.background = type === 'success' ? 'var(--accent-success)' : 
                              type === 'error' ? 'var(--accent-error)' : 'var(--accent-info)';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), 3000);
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    renderActions();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
