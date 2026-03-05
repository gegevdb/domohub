<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/api_helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

authRequireAdmin();

$pageTitle = 'Paramètres — Maison & Carte';
$activePage = 'settings';
$settingsSection = 'home';

$rooms = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

$config = db()->fetchAll('SELECT key, value FROM config');
$configValues = [];
foreach ($config as $c) {
    $configValues[$c['key']] = $c['value'];
}

$homeLat = $configValues['home_lat'] ?? '';
$homeLng = $configValues['home_lng'] ?? '';
$homeAddress = $configValues['home_address'] ?? '';

$defaultFloors = [
    ['id' => 'b2', 'level' => -2, 'label' => 'Box (Sous-sol -2)'],
    ['id' => 'f2', 'level' => 2, 'label' => 'Appartement (Étage +2)'],
];

$floorsJson = $configValues['home_floors_json'] ?? '';
if ($floorsJson !== '') {
    $decoded = json_decode($floorsJson, true);
    $floors = is_array($decoded) ? $decoded : $defaultFloors;
} else {
    $floors = $defaultFloors;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-4xl">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary)">
                <i class="fas fa-map-marked-alt mr-3" style="color: var(--accent-primary)"></i>
                Paramètres
            </h1>
            <p class="text-sm mt-1" style="color: var(--text-muted)">Maison & Carte</p>
        </div>
    </div>

    <?php require __DIR__ . '/_nav.php'; ?>

    <div class="glass-card rounded-2xl p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-map-pin mr-2"></i>Localisation
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Adresse (optionnel)</label>
                <input id="home-address" type="text" value="<?= htmlspecialchars($homeAddress) ?>"
                       class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Latitude</label>
                    <input id="home-lat" type="text" inputmode="decimal" value="<?= htmlspecialchars($homeLat) ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-primary)">Longitude</label>
                    <input id="home-lng" type="text" inputmode="decimal" value="<?= htmlspecialchars($homeLng) ?>"
                           class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary">
                </div>
            </div>
        </div>

        <div class="rounded-2xl overflow-hidden border" style="border-color: var(--border-color)">
            <div id="home-map" style="height: 360px; background: rgba(255,255,255,.03);"></div>
        </div>

        <p class="text-xs mt-3" style="color: var(--text-muted)">
            Clique sur la carte pour positionner le marqueur. Tu peux aussi déplacer le marqueur.
        </p>

        <div class="flex justify-end mt-5">
            <button type="button" onclick="saveHomeLocation()"
                    class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>
    </div>

    <div class="glass-card rounded-2xl p-6">
        <h2 class="text-lg font-semibold mb-4" style="color: var(--text-primary)">
            <i class="fas fa-layer-group mr-2"></i>Niveaux (étages / sous-sols)
        </h2>

        <div id="floors-list" class="space-y-3"></div>

        <div class="flex justify-between items-center mt-5">
            <button type="button" onclick="addFloorRow()" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80">
                <i class="fas fa-plus mr-2"></i>Ajouter un niveau
            </button>
            <button type="button" onclick="saveFloors()"
                    class="px-6 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl hover:from-blue-600 hover:to-purple-700">
                <i class="fas fa-save mr-2"></i>Enregistrer
            </button>
        </div>

        <p class="text-xs mt-3" style="color: var(--text-muted)">
            Exemple: Appartement au +2, Box au -2. Ces niveaux serviront ensuite pour associer pièces, plans et surveillance.
        </p>
    </div>

</main>

<!-- Leaflet (OSM) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
const initialFloors = <?= json_encode($floors) ?>;

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500'}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check' : type === 'error' ? 'fa-exclamation-triangle' : 'fa-info'} mr-2"></i>${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function numOrEmpty(v) {
    const n = Number(String(v).replace(',', '.'));
    return Number.isFinite(n) ? n : '';
}

// ============================================================
//  Map
// ============================================================
let map;
let marker;

function initMap() {
    const latInput = document.getElementById('home-lat');
    const lngInput = document.getElementById('home-lng');

    const lat = numOrEmpty(latInput.value);
    const lng = numOrEmpty(lngInput.value);

    const startLat = (lat !== '') ? lat : 48.8566;
    const startLng = (lng !== '') ? lng : 2.3522;
    const startZoom = (lat !== '' && lng !== '') ? 14 : 5;

    map = L.map('home-map').setView([startLat, startLng], startZoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

    function setInputsFromLatLng(latlng) {
        latInput.value = latlng.lat.toFixed(6);
        lngInput.value = latlng.lng.toFixed(6);
    }

    marker.on('dragend', function(e) {
        setInputsFromLatLng(e.target.getLatLng());
    });

    map.on('click', function(e) {
        marker.setLatLng(e.latlng);
        setInputsFromLatLng(e.latlng);
    });
}

async function saveHomeLocation() {
    const payload = {
        home_address: document.getElementById('home-address').value,
        home_lat: document.getElementById('home-lat').value,
        home_lng: document.getElementById('home-lng').value,
    };

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
            showToast('Erreur: ' + msg, 'error');
            return;
        }

        showToast('Localisation enregistrée.', 'success');
    } catch (e) {
        showToast('Erreur réseau.', 'error');
    }
}

// ============================================================
//  Floors
// ============================================================
function floorRowTemplate(f) {
    const id = f.id || ('f_' + Math.random().toString(16).slice(2));
    const level = (typeof f.level === 'number') ? f.level : Number(f.level || 0);
    const label = f.label || '';

    return `
        <div class="glass rounded-2xl p-4" data-floor-row data-id="${id}">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-muted)">Identifiant</label>
                    <input type="text" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary" data-floor-id value="${id}">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-muted)">Niveau (ex: -2, 0, 2)</label>
                    <input type="number" step="1" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary" data-floor-level value="${level}">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-muted)">Libellé</label>
                    <input type="text" class="w-full px-3 py-2 rounded-xl border border-border-color bg-transparent text-primary" data-floor-label value="${label}">
                </div>
            </div>
            <div class="flex justify-end mt-3">
                <button type="button" class="px-4 py-2 glass rounded-xl hover:bg-opacity-80" onclick="removeFloorRow('${id}')">
                    <i class="fas fa-trash mr-2"></i>Supprimer
                </button>
            </div>
        </div>
    `;
}

function renderFloors() {
    const list = document.getElementById('floors-list');
    list.innerHTML = (initialFloors || []).map(floorRowTemplate).join('');
}

function addFloorRow() {
    const list = document.getElementById('floors-list');
    const newFloor = { id: '', level: 0, label: '' };
    list.insertAdjacentHTML('beforeend', floorRowTemplate(newFloor));
}

function removeFloorRow(id) {
    document.querySelector(`[data-floor-row][data-id="${id}"]`)?.remove();
}

function collectFloors() {
    const rows = document.querySelectorAll('[data-floor-row]');
    const floors = [];
    rows.forEach(r => {
        const id = r.querySelector('[data-floor-id]')?.value?.trim() || '';
        const level = Number(r.querySelector('[data-floor-level]')?.value ?? 0);
        const label = r.querySelector('[data-floor-label]')?.value?.trim() || '';
        if (!id || !label || !Number.isFinite(level)) return;
        floors.push({ id, level, label });
    });
    floors.sort((a,b) => a.level - b.level);
    return floors;
}

async function saveFloors() {
    const floors = collectFloors();
    if (!floors.length) {
        showToast('Ajoute au moins un niveau valide.', 'error');
        return;
    }

    try {
        const resp = await fetch('/api/settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': (window.DOMOGLASS && window.DOMOGLASS.csrfToken) ? window.DOMOGLASS.csrfToken : ''
            },
            body: JSON.stringify({ home_floors_json: JSON.stringify(floors) })
        });

        const data = await resp.json().catch(() => ({}));
        if (!resp.ok || !data.success) {
            const msg = data.error || ('Erreur HTTP ' + resp.status);
            showToast('Erreur: ' + msg, 'error');
            return;
        }

        showToast('Niveaux enregistrés.', 'success');
    } catch (e) {
        showToast('Erreur réseau.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    renderFloors();
    initMap();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
