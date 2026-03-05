<?php
/**
 * DOMOGLASS PRO — Page de suivi énergétique
 */
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

$pageTitle  = 'Énergie';
$activePage = 'energy';

$devices      = db()->fetchAll('SELECT d.*, r.name as room_name FROM devices d LEFT JOIN rooms r ON r.id = d.room_id WHERE d.enabled = 1 ORDER BY d.name ASC');
$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

// Récupérer les données énergétiques des dernières 24h
$energyData = db()->fetchAll("
    SELECT 
        s.device_id,
        d.name as device_name,
        d.room_id,
        r.name as room_name,
        s.metric,
        s.value,
        s.unit,
        s.recorded_at
    FROM sensor_history s
    JOIN devices d ON d.id = s.device_id
    LEFT JOIN rooms r ON r.id = d.room_id
    WHERE s.metric IN ('power', 'energy') 
      AND s.recorded_at >= datetime('now', '-24 hours')
    ORDER BY s.recorded_at DESC
");

// Calculer les statistiques
$currentPower = 0;
$totalEnergy = 0;
$deviceStats = [];

foreach ($energyData as $data) {
    if ($data['metric'] === 'power') {
        $currentPower += $data['value'];
        if (!isset($deviceStats[$data['device_id']])) {
            $deviceStats[$data['device_id']] = [
                'name' => $data['device_name'],
                'room' => $data['room_name'],
                'current_power' => 0,
                'total_energy' => 0
            ];
        }
        $deviceStats[$data['device_id']]['current_power'] = $data['value'];
    } elseif ($data['metric'] === 'energy') {
        $totalEnergy += $data['value'];
        if (!isset($deviceStats[$data['device_id']])) {
            $deviceStats[$data['device_id']] = [
                'name' => $data['device_name'],
                'room' => $data['room_name'],
                'current_power' => 0,
                'total_energy' => 0
            ];
        }
        $deviceStats[$data['device_id']]['total_energy'] = $data['value'];
    }
}

// Coût estimé
$dailyCost = ($totalEnergy / 1000) * ENERGY_PRICE_KWH;
$monthlyEstimate = $dailyCost * 30;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-bolt mr-3" style="color: var(--accent-warning)"></i>
                Suivi Énergétique
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">
                Consommation et coûts en temps réel
            </p>
        </div>
        <div class="flex items-center space-x-3">
            <select id="period-filter" onchange="updatePeriod()" class="px-4 py-2 glass rounded-xl text-sm">
                <option value="24h">24 heures</option>
                <option value="7d">7 jours</option>
                <option value="30d">30 jours</option>
                <option value="1y">1 an</option>
            </select>
            <button onclick="refreshEnergyData()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80 transition-all">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- Statistiques principales -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-warning)" id="current-power-display">
                <?= number_format($currentPower, 1) ?> W
            </div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Puissance actuelle</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-info)">
                <?= number_format($totalEnergy, 2) ?> kWh
            </div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Consommation totale</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-success)">
                <?= number_format($dailyCost, 2) ?> €
            </div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Coût aujourd'hui</div>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: var(--accent-primary)">
                <?= number_format($monthlyEstimate, 0) ?> €
            </div>
            <div class="text-sm mt-1" style="color: var(--text-muted)">Estimation mensuelle</div>
        </div>
    </div>

    <!-- Graphique de consommation -->
    <div class="glass-card rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-chart-line mr-2"></i>Évolution de la consommation
        </h2>
        <div class="relative" style="height: 300px;">
            <canvas id="energy-chart"></canvas>
        </div>
    </div>

    <!-- Répartition par pièce -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-home mr-2"></i>Consommation par pièce
            </h2>
            <div class="relative" style="height: 250px;">
                <canvas id="room-chart"></canvas>
            </div>
        </div>
        
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-plug mr-2"></i>Top appareils énergivores
            </h2>
            <div class="space-y-3">
                <?php
                // Trier les appareils par consommation
                uasort($deviceStats, function($a, $b) {
                    return $b['current_power'] <=> $a['current_power'];
                });
                $topDevices = array_slice($deviceStats, 0, 5, true);
                ?>
                <?php foreach ($topDevices as $deviceId => $stats): ?>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-xs">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div>
                            <div class="text-sm font-medium" style="color: var(--text-primary)"><?= htmlspecialchars($stats['name']) ?></div>
                            <div class="text-xs" style="color: var(--text-muted)"><?= htmlspecialchars($stats['room'] ?? 'Non assigné') ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-semibold" style="color: var(--accent-warning)">
                            <?= number_format($stats['current_power'], 1) ?> W
                        </div>
                        <div class="text-xs" style="color: var(--text-muted)">
                            <?= number_format($stats['total_energy'], 2) ?> kWh
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tableau détaillé des appareils -->
    <div class="glass-card rounded-2xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold" style="color: var(--text-primary)">
                <i class="fas fa-list mr-2"></i>Détail par appareil
            </h2>
            <div class="flex items-center space-x-2">
                <input type="text" id="device-search" placeholder="Rechercher..." 
                       onkeyup="filterDevicesTable()"
                       class="px-3 py-1 rounded-lg border border-border-color bg-transparent text-sm">
                <select id="room-filter" onchange="filterDevicesTable()" class="px-3 py-1 rounded-lg border border-border-color bg-transparent text-sm">
                    <option value="">Toutes les pièces&nbsp;&nbsp;</option>
                    <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="color: var(--text-muted)">
                        <th class="text-left p-2">Appareil</th>
                        <th class="text-left p-2">Pièce</th>
                        <th class="text-right p-2">Puissance actuelle</th>
                        <th class="text-right p-2">Consommation totale</th>
                        <th class="text-right p-2">Coût estimé</th>
                        <th class="text-center p-2">État</th>
                    </tr>
                </thead>
                <tbody id="devices-table-body">
                    <?php foreach ($deviceStats as $deviceId => $stats): ?>
                    <tr class="border-t border-border-color device-row" 
                        data-device-name="<?= htmlspecialchars($stats['name']) ?>"
                        data-room-id="<?= $stats['room'] ?? '' ?>">
                        <td class="p-2">
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 rounded bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs">
                                    <i class="fas fa-plug"></i>
                                </div>
                                <span style="color: var(--text-primary)"><?= htmlspecialchars($stats['name']) ?></span>
                            </div>
                        </td>
                        <td class="p-2" style="color: var(--text-muted)"><?= htmlspecialchars($stats['room'] ?? 'Non assigné') ?></td>
                        <td class="p-2 text-right" style="color: var(--text-primary)">
                            <?= number_format($stats['current_power'], 1) ?> W
                        </td>
                        <td class="p-2 text-right" style="color: var(--text-primary)">
                            <?= number_format($stats['total_energy'], 2) ?> kWh
                        </td>
                        <td class="p-2 text-right" style="color: var(--text-primary)">
                            <?= number_format(($stats['total_energy'] / 1000) * ENERGY_PRICE_KWH, 2) ?> €
                        </td>
                        <td class="p-2 text-center">
                            <span class="px-2 py-1 rounded-full text-xs" 
                                  style="background: <?= $stats['current_power'] > 0 ? 'var(--accent-success)' : 'var(--text-muted)' ?>20; 
                                         color: <?= $stats['current_power'] > 0 ? 'var(--accent-success)' : 'var(--text-muted)' ?>">
                                <?= $stats['current_power'] > 0 ? 'Actif' : 'Inactif' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paramètres et alertes -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-cog mr-2"></i>Paramètres
            </h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Prix du kWh</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" id="kwh-price" value="<?= ENERGY_PRICE_KWH ?>" step="0.01" min="0"
                               class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <span style="color: var(--text-muted)">€/kWh</span>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Objectif de consommation mensuelle</label>
                    <div class="flex items-center space-x-2">
                        <input type="number" id="monthly-goal" value="500" step="10" min="0"
                               class="flex-1 px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                        <span style="color: var(--text-muted)">kWh</span>
                    </div>
                </div>
                <button onclick="saveSettings()" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
            </div>
        </div>
        
        <div class="glass-card rounded-2xl p-6">
            <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
                <i class="fas fa-bell mr-2"></i>Alertes énergétiques
            </h2>
            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Seuil de puissance dépassé</div>
                        <div class="text-xs" style="color: var(--text-muted)">Alerte si > 3000W</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Consommation anormale</div>
                        <div class="text-xs" style="color: var(--text-muted)">Détection de pics de consommation</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between p-3 rounded-lg" style="background: var(--bg-tertiary)">
                    <div>
                        <div class="text-sm font-medium" style="color: var(--text-primary)">Rapport quotidien</div>
                        <div class="text-xs" style="color: var(--text-muted)">Résumé à 20h</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
// Variables globales
let energyData = <?= json_encode($energyData) ?>;
let deviceStats = <?= json_encode($deviceStats) ?>;
let charts = {};

// Initialisation des graphiques
document.addEventListener('DOMContentLoaded', function() {
    initEnergyChart();
    initRoomChart();
    startRealTimeUpdates();
});

// Graphique d'évolution
function initEnergyChart() {
    const ctx = document.getElementById('energy-chart').getContext('2d');
    
    // Préparer les données pour les dernières 24h
    const labels = [];
    const data = [];
    const now = new Date();
    
    for (let i = 23; i >= 0; i--) {
        const hour = new Date(now - i * 60 * 60 * 1000);
        labels.push(hour.getHours() + 'h');
        data.push(Math.random() * 500 + 200); // Simulé pour l'exemple
    }
    
    charts.energy = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Puissance (W)',
                data: data,
                borderColor: 'rgb(251, 146, 60)',
                backgroundColor: 'rgba(251, 146, 60, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: 'var(--text-muted)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: 'var(--text-muted)'
                    }
                }
            }
        }
    });
}

// Graphique par pièce
function initRoomChart() {
    const ctx = document.getElementById('room-chart').getContext('2d');
    
    // Calculer la consommation par pièce
    const roomConsumption = {};
    Object.values(deviceStats).forEach(device => {
        const room = device.room || 'Non assigné';
        roomConsumption[room] = (roomConsumption[room] || 0) + device.current_power;
    });
    
    const labels = Object.keys(roomConsumption);
    const data = Object.values(roomConsumption);
    
    charts.room = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: 'var(--text-muted)',
                        padding: 10,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// Mises à jour en temps réel
function startRealTimeUpdates() {
    setInterval(async () => {
        try {
            const response = await fetch('/api/energy.php?action=current');
            const data = await response.json();
            
            if (data.success) {
                // Mettre à jour la puissance actuelle
                document.getElementById('current-power-display').textContent = 
                    data.current_power.toFixed(1) + ' W';
                
                // Mettre à jour le dashboard principal aussi
                const mainPowerElement = document.getElementById('current-power');
                if (mainPowerElement) {
                    mainPowerElement.textContent = data.current_power.toFixed(1) + ' kW';
                }
            }
        } catch (error) {
            console.error('Erreur lors de la mise à jour des données énergétiques:', error);
        }
    }, 5000); // Toutes les 5 secondes
}

// Filtrage du tableau
function filterDevicesTable() {
    const search = document.getElementById('device-search').value.toLowerCase();
    const roomFilter = document.getElementById('room-filter').value;
    
    document.querySelectorAll('.device-row').forEach(row => {
        const name = row.dataset.deviceName?.toLowerCase() || '';
        const roomId = row.dataset.roomId || '';
        
        const matches = (!search || name.includes(search)) &&
                       (!roomFilter || roomId === roomFilter);
        
        row.style.display = matches ? '' : 'none';
    });
}

// Changement de période
function updatePeriod() {
    const period = document.getElementById('period-filter').value;
    // Recharger les données pour la période sélectionnée
    location.href = `?period=${period}`;
}

// Rafraîchissement manuel
function refreshEnergyData() {
    location.reload();
}

// Sauvegarde des paramètres
async function saveSettings() {
    const kwhPrice = document.getElementById('kwh-price').value;
    const monthlyGoal = document.getElementById('monthly-goal').value;
    
    try {
        const response = await fetch('/api/energy.php?action=settings', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.DOMOGLASS.csrfToken
            },
            body: JSON.stringify({
                kwh_price: parseFloat(kwhPrice),
                monthly_goal: parseInt(monthlyGoal)
            })
        });
        
        const data = await response.json();
        if (data.success) {
            showToast('Paramètres enregistrés', 'success');
        } else {
            showToast('Erreur: ' + (data.error || 'Inconnue'), 'error');
        }
    } catch (error) {
        showToast('Erreur réseau', 'error');
    }
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
</script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
