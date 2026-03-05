<?php
/**
 * DomoGlass Pro - Server-Sent Events (temps réel)
 *
 * Le client JS maintient une connexion persistante SSE.
 * Le serveur pousse les mises à jour d'état des devices,
 * les notifications et les logs système en temps réel.
 *
 * GET /api/sse.php
 * GET /api/sse.php?rooms=1,2,3  → filtre par pièces
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Headers SSE obligatoires
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');   // Nginx : désactive le buffering
header('Access-Control-Allow-Origin: *');

// Pas de timeout PHP
set_time_limit(0);
ignore_user_abort(false);

$db          = Database::getInstance();
$lastEventId = (int)($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
$roomFilter  = isset($_GET['rooms'])
    ? array_map('intval', explode(',', $_GET['rooms']))
    : [];

$lastCheck = time();
$heartbeat = SSE_HEARTBEAT;

// État de polling : timestamp des dernières données envoyées
$lastActionId  = $db->fetchColumn('SELECT COALESCE(MAX(id), 0) FROM actions');
$lastNotifId   = $db->fetchColumn('SELECT COALESCE(MAX(id), 0) FROM notifications');
$lastEnergyId  = $db->fetchColumn('SELECT COALESCE(MAX(id), 0) FROM energy_logs');

/**
 * Envoie un événement SSE au client
 */
function sseEvent(string $event, mixed $data, ?int $id = null): void
{
    if ($id !== null) {
        echo "id: $id\n";
    }
    echo "event: $event\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    ob_flush();
    flush();
}

/**
 * Envoie un heartbeat (keep-alive)
 */
function sseHeartbeat(): void
{
    echo ": heartbeat " . date('H:i:s') . "\n\n";
    ob_flush();
    flush();
}

// Événement initial : état complet de tous les devices
$devicesQuery = 'SELECT d.*, r.name AS room_name FROM devices d LEFT JOIN rooms r ON r.id = d.room_id';
$devicesParams = [];

if (!empty($roomFilter)) {
    $placeholders = implode(',', array_fill(0, count($roomFilter), '?'));
    $devicesQuery .= " WHERE d.room_id IN ($placeholders)";
    $devicesParams = $roomFilter;
}

$devices = $db->fetchAll($devicesQuery, $devicesParams);
foreach ($devices as &$d) {
    $d['attributes'] = json_decode($d['attributes'] ?? '{}', true);
}

sseEvent('connected', [
    'devices'   => $devices,
    'ts'        => time(),
    'version'   => APP_VERSION,
]);

// ─── Boucle principale SSE ────────────────────────────────────────────────────

while (true) {
    // Vérifie si le client est déconnecté
    if (connection_aborted()) {
        break;
    }

    $now = time();

    // ── Nouvelles actions (changements d'état) ────────────────────────────────
    $newActions = $db->fetchAll('
        SELECT a.*, d.name AS device_name, d.type AS device_type,
               d.state AS device_state, d.attributes AS device_attributes
        FROM actions a
        LEFT JOIN devices d ON d.id = a.device_id
        WHERE a.id > ?
        ORDER BY a.id ASC
        LIMIT 20
    ', [$lastActionId]);

    foreach ($newActions as $action) {
        $lastActionId = $action['id'];
        sseEvent('device_action', [
            'device_id'   => $action['device_id'],
            'device_name' => $action['device_name'],
            'device_type' => $action['device_type'],
            'action'      => $action['action'],
            'payload'     => json_decode($action['payload'], true),
            'state'       => $action['device_state'],
            'attributes'  => json_decode($action['device_attributes'] ?? '{}', true),
            'ts'          => $action['created_at'],
        ], $action['id']);
    }

    // ── Nouvelles notifications ────────────────────────────────────────────────
    $newNotifs = $db->fetchAll('
        SELECT * FROM notifications WHERE id > ? ORDER BY id ASC LIMIT 10
    ', [$lastNotifId]);

    foreach ($newNotifs as $notif) {
        $lastNotifId = $notif['id'];
        sseEvent('notification', $notif, $notif['id']);
    }

    // ── Données énergie ────────────────────────────────────────────────────────
    $newEnergy = $db->fetchAll('
        SELECT e.*, d.name AS device_name
        FROM energy_logs e
        LEFT JOIN devices d ON d.id = e.device_id
        WHERE e.id > ?
        ORDER BY e.id ASC
        LIMIT 10
    ', [$lastEnergyId]);

    foreach ($newEnergy as $energy) {
        $lastEnergyId = $energy['id'];
        sseEvent('energy_update', $energy);
    }

    // ── Snapshot périodique de tous les devices (toutes les 30s) ─────────────
    if ($now - $lastCheck >= 30) {
        $snapshot = $db->fetchAll('
            SELECT id, name, type, state, is_online, last_seen
            FROM devices
        ');
        sseEvent('devices_snapshot', ['devices' => $snapshot, 'ts' => $now]);
        $lastCheck = $now;
    }

    // ── Heartbeat toutes les N secondes ───────────────────────────────────────
    sseHeartbeat();

    // Pause pour ne pas saturer le CPU
    sleep(2);

    // Retry hint pour le client (reconnexion en 3s si coupure)
    echo 'retry: ' . (SSE_RETRY_MS) . "\n\n";
    ob_flush();
    flush();
}
