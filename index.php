<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/api_helpers.php';

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$devices      = db()->fetchAll('SELECT d.*, r.name as room_name, r.slug as room_slug FROM devices d LEFT JOIN rooms r ON r.id = d.room_id WHERE d.enabled = 1 ORDER BY d.sort_order ASC');
$rooms        = db()->fetchAll('SELECT * FROM rooms ORDER BY sort_order ASC');
$activeCount  = count(array_filter($devices, fn($d) => $d['state'] === 'on'));
$totalDevices = count($devices);
$unreadNotifs = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM notifications WHERE read = 0')['c'] ?? 0);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<main class="container mx-auto px-4 py-6 max-w-7xl">

    <!-- Stats rapides -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php
        $stats = [
            [$activeCount,  'Appareils actifs', 'var(--accent-success)', null],
            [$totalDevices, 'Total appareils',  'var(--text-primary)', null],
            ['—',           'kW en cours',      'var(--accent-warning)', 'id="current-power"'],
            [count($rooms), 'Pièces',           'var(--accent-info)', null],
        ];
        foreach ($stats as [$val, $label, $color, $extra]): ?>
        <div class="glass-card rounded-2xl p-4 text-center">
            <div class="text-3xl font-bold" style="color: <?= $color ?>" <?= $extra ?? '' ?>><?= $val ?></div>
            <div class="text-sm mt-1" style="color: var(--text-muted)"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres pièces -->
    <div class="flex items-center space-x-2 mb-6 overflow-x-auto pb-2">
        <button onclick="filterRoom('all')" data-room="all"
                class="room-tab glass px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap active"
                style="color: var(--text-primary)">
            <i class="fas fa-home mr-2"></i>Tout
        </button>
        <?php foreach ($rooms as $r): ?>
        <button onclick="filterRoom('<?= htmlspecialchars($r['slug']) ?>')"
                data-room="<?= htmlspecialchars($r['slug']) ?>"
                class="room-tab glass px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap"
                style="color: var(--text-secondary)">
            <i class="fas <?= htmlspecialchars($r['icon']) ?> mr-2"></i><?= htmlspecialchars($r['name']) ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Grille devices -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="devices-grid">
        <?php if (empty($devices)): ?>
        <div class="col-span-full glass-card rounded-2xl p-12 text-center">
            <i class="fas fa-plug text-5xl mb-4 opacity-30" style="color: var(--text-muted)"></i>
            <p class="text-lg font-medium mb-2" style="color: var(--text-primary)">Aucun appareil configuré</p>
            <p class="text-sm mb-4" style="color: var(--text-muted)">Commencez par appairer un appareil Zigbee.</p>
            <a href="/pages/zigbee.php"
               class="inline-block px-6 py-2 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-500 to-purple-600">
                <i class="fas fa-network-wired mr-2"></i>Appairer un appareil
            </a>
        </div>
        <?php else: foreach ($devices as $d): ?>
        <div class="glass-card rounded-2xl p-4 device-card"
             data-room="<?= htmlspecialchars($d['room_slug'] ?? 'general') ?>"
             data-device-id="<?= $d['id'] ?>"
             data-state="<?= htmlspecialchars($d['state']) ?>">
            <div class="flex justify-between items-start mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br <?= htmlspecialchars($d['color']) ?> flex items-center justify-center text-white">
                        <i class="fas <?= htmlspecialchars($d['icon']) ?>"></i>
                    </div>
                    <div>
                        <h3 class="font-semibold text-sm" style="color: var(--text-primary)"><?= htmlspecialchars($d['name']) ?></h3>
                        <p class="text-xs" style="color: var(--text-muted)"><?= htmlspecialchars($d['room_name'] ?? '—') ?></p>
                    </div>
                </div>
                <?php if (in_array($d['type'], ['light','switch'])): ?>
                <button class="toggle-switch <?= $d['state'] === 'on' ? 'active' : '' ?>"
                        onclick="toggleDevice(<?= $d['id'] ?>)"
                        role="switch"
                        aria-checked="<?= $d['state'] === 'on' ? 'true' : 'false' ?>"
                        aria-label="<?= htmlspecialchars($d['name']) ?>">
                </button>
                <?php endif; ?>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-xs px-2 py-1 rounded-lg" style="background:rgba(255,255,255,.06);color:var(--text-muted)">
                    <i class="fas <?= $d['protocol'] === 'zigbee' ? 'fa-broadcast-tower' : 'fa-wifi' ?> mr-1"></i>
                    <?= ucfirst(htmlspecialchars($d['protocol'])) ?>
                </span>
                <span class="text-xs font-medium" style="color:<?= $d['state']==='on'?'var(--accent-success)':'var(--text-muted)' ?>">
                    <?= $d['state']==='on' ? '● Actif' : '○ Inactif' ?>
                </span>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>

<!-- Sélecteur palette flottant -->
<div class="palette-selector" id="palette-selector">
    <p class="text-xs font-semibold mb-3 text-center" style="color:var(--text-muted)">THÈME</p>
    <?php foreach ([
        ['midnight','#0f172a'],['ocean','#0c4a6e'],['purple','#4c1d95'],
        ['rose','#881337'],['emerald','#064e3b'],['catppuccin','#cba6f7'],['light','#e2e8f0']
    ] as [$pid,$pbg]): ?>
    <button class="palette-btn <?= $palette===$pid?'active':'' ?>"
            data-palette="<?= $pid ?>" style="background:<?= $pbg ?>"
            title="<?= ucfirst($pid) ?>" aria-label="Thème <?= ucfirst($pid) ?>"></button>
    <?php endforeach; ?>
</div>

<script>
async function toggleDevice(id) {
    const card = document.querySelector(`[data-device-id="${id}"]`);
    const btn  = card?.querySelector('.toggle-switch');
    if (!card || !btn) return;
    const isOn     = btn.classList.contains('active');
    const newState = isOn ? 'off' : 'on';
    btn.classList.toggle('active');
    btn.setAttribute('aria-checked', String(!isOn));
    const res  = await fetch(`/api/devices.php?action=command&id=${id}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content},
        body: JSON.stringify({payload:{state: newState.toUpperCase()}}),
    });
    const json = await res.json();
    if (!json.success) { btn.classList.toggle('active'); btn.setAttribute('aria-checked', String(isOn)); }
}

function filterRoom(room) {
    document.querySelectorAll('.device-card').forEach(c => {
        c.style.display = (room === 'all' || c.dataset.room === room) ? '' : 'none';
    });
    document.querySelectorAll('.room-tab').forEach(t => {
        const active = t.dataset.room === room;
        t.classList.toggle('active', active);
        t.style.background = active ? 'var(--accent-primary)' : '';
        t.style.color      = active ? 'white' : 'var(--text-secondary)';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
