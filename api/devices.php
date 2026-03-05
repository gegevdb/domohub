<?php
/**
 * DOMOGLASS PRO — API Devices
 * GET    /api/devices.php          → liste tous les devices
 * GET    /api/devices.php?id=N     → détail d'un device
 * POST   /api/devices.php          → créer un device
 * PUT    /api/devices.php?id=N     → modifier un device
 * DELETE /api/devices.php?id=N     → supprimer un device
 * POST   /api/devices.php?action=command&id=N → envoyer une commande
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/mqtt.php';
require_once __DIR__ . '/../includes/zigbee.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

checkRateLimit();

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if ($id) {
        $device = db()->fetchOne(
            'SELECT d.*, r.name as room_name FROM devices d
             LEFT JOIN rooms r ON r.id = d.room_id
             WHERE d.id = ? AND d.enabled = 1', [$id]
        );
        if (!$device) apiError('Device introuvable', 404);
        if ($device['state_data']) $device['state_data'] = json_decode($device['state_data'], true);
        apiSuccess($device);
    }

    $sql = 'SELECT d.*, r.name as room_name, r.slug as room_slug
            FROM devices d LEFT JOIN rooms r ON r.id = d.room_id
            WHERE d.enabled = 1';
    $params = [];
    if (!empty($_GET['room']))     { $sql .= ' AND r.slug = ?';      $params[] = $_GET['room']; }
    if (!empty($_GET['type']))     { $sql .= ' AND d.type = ?';      $params[] = $_GET['type']; }
    if (!empty($_GET['protocol'])) { $sql .= ' AND d.protocol = ?';  $params[] = $_GET['protocol']; }
    $sql .= ' ORDER BY d.sort_order ASC, d.name ASC';

    $devices = db()->fetchAll($sql, $params);
    foreach ($devices as &$d) {
        if ($d['state_data']) $d['state_data'] = json_decode($d['state_data'], true);
    }
    apiSuccess($devices);
}

if ($method === 'POST') {
    requireCsrf();
    $body = getJsonBody();

    if ($action === 'command' && $id) {
        $device = db()->fetchOne('SELECT * FROM devices WHERE id = ? AND enabled = 1', [$id]);
        if (!$device) apiError('Device introuvable', 404);
        requireParam($body, 'payload');

        $payload = $body['payload'];
        $source  = $body['source'] ?? 'ui';
        $ok      = false;

        if ($device['protocol'] === 'zigbee' && $device['ha_entity_id']) {
            $service = $body['service'] ?? 'toggle';
            $result  = zigbee()->callEntityService($device['ha_entity_id'], $service, is_array($payload) ? $payload : []);
            $ok      = $result['success'];
            db()->logAction((int)$device['id'], $device['name'], 'command', $payload, $source, $ok ? 'sent' : 'error');
        } elseif ($device['mqtt_topic_set']) {
            $ok = mqtt()->sendDeviceCommand($device, $payload, $source);
        }

        if ($ok && isset($payload['state'])) {
            db()->updateDeviceState((int)$device['id'], strtolower($payload['state']), $payload);
        }
        apiSuccess(['sent' => $ok, 'device_id' => $id]);
    }

    requireParam($body, 'name', 'type', 'protocol');
    $slug     = preg_replace('/[^a-z0-9]+/', '-', strtolower($body['name']));
    $deviceId = db()->insert(
        'INSERT INTO devices (name,slug,type,protocol,room_id,mqtt_topic_state,mqtt_topic_set,
            ha_entity_id,zigbee_ieee,zigbee_model,icon,color,sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $body['name'], $body['slug'] ?? $slug, $body['type'], $body['protocol'],
            $body['room_id'] ?? null, $body['mqtt_topic_state'] ?? null,
            $body['mqtt_topic_set'] ?? null, $body['ha_entity_id'] ?? null,
            $body['zigbee_ieee'] ?? null, $body['zigbee_model'] ?? null,
            $body['icon'] ?? 'fa-plug', $body['color'] ?? 'from-indigo-500 to-purple-600',
            $body['sort_order'] ?? 0,
        ]
    );
    db()->logAction((int)$deviceId, $body['name'], 'created', null, 'ui');
    apiSuccess(['id' => $deviceId], 'Device créé', 201);
}

if ($method === 'PUT') {
    requireCsrf();
    if (!$id) apiError('ID requis', 400);
    $body   = getJsonBody();
    $device = db()->fetchOne('SELECT * FROM devices WHERE id = ?', [$id]);
    if (!$device) apiError('Device introuvable', 404);

    $allowed = ['name','type','protocol','room_id','mqtt_topic_state','mqtt_topic_set',
                'ha_entity_id','zigbee_ieee','icon','color','sort_order','enabled'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) { $sets[] = "$f = ?"; $params[] = $body[$f]; }
    }
    if (empty($sets)) apiError('Aucun champ à modifier', 422);
    $params[] = $id;
    db()->execute('UPDATE devices SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    apiSuccess(['id' => $id], 'Device mis à jour');
}

if ($method === 'DELETE') {
    requireCsrf();
    if (!$id) apiError('ID requis', 400);
    $device = db()->fetchOne('SELECT * FROM devices WHERE id = ?', [$id]);
    if (!$device) apiError('Device introuvable', 404);
    db()->execute('UPDATE devices SET enabled = 0 WHERE id = ?', [$id]);
    db()->logAction((int)$id, $device['name'], 'deleted', null, 'ui');
    apiSuccess(null, 'Device supprimé');
}

apiError('Méthode non supportée', 405);
