<?php
/**
 * DOMOGLASS PRO — API Zigbee
 * GET  ?action=devices   → liste des devices ZHA (cache local)
 * GET  ?action=sync      → synchronise depuis HA
 * GET  ?action=status    → état du réseau
 * POST ?action=permit    → ouvre l'appairage
 * POST ?action=deny      → ferme l'appairage
 * POST ?action=pair      → intègre un device dans DomoGlass
 * POST ?action=remove    → supprime un device du réseau
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/zigbee.php';

header('Content-Type: application/json; charset=utf-8');
checkRateLimit();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'devices';

if ($method === 'GET') {
    if ($action === 'devices') {
        $devices = zigbee()->getCachedDevices(isset($_GET['paired']));
        foreach ($devices as &$d) {
            if ($d['raw_data']) $d['raw_data'] = json_decode($d['raw_data'], true);
        }
        apiSuccess($devices);
    }

    if ($action === 'sync') {
        apiSuccess(zigbee()->syncDevices());
    }

    if ($action === 'status') {
        $permitJoin    = (bool)db()->getConfig('zigbee_permit_join', 0);
        $totalDevices  = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM zigbee_devices')['c'] ?? 0);
        $pairedDevices = (int)(db()->fetchOne('SELECT COUNT(*) as c FROM zigbee_devices WHERE paired = 1')['c'] ?? 0);
        $haReachable   = false;

        if (HA_TOKEN !== '') {
            $ch = curl_init(HA_BASE_URL . '/api/');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . HA_TOKEN],
                CURLOPT_TIMEOUT    => 3]);
            curl_exec($ch);
            $haReachable = (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200);
            curl_close($ch);
        }

        apiSuccess(compact('permit_join', 'ha_reachable', 'total_devices', 'paired_devices'));
    }

    apiError('Action inconnue', 400);
}

if ($method === 'POST') {
    requireCsrf();
    $body = getJsonBody();

    if ($action === 'permit') {
        $duration = (int)($body['duration'] ?? ZHA_PERMIT_JOIN_DURATION);
        $result   = zigbee()->permitJoin($duration);
        $result['success'] ? apiSuccess(['duration' => $duration], 'Appairage ouvert')
                           : apiError($result['error'] ?? 'Échec', 500);
    }

    if ($action === 'deny') {
        zigbee()->denyJoin();
        apiSuccess(null, 'Appairage fermé');
    }

    if ($action === 'pair') {
        requireParam($body, 'ieee');
        $result = zigbee()->pairDevice($body['ieee'], $body);
        $result['success'] ? apiSuccess($result, 'Device appairé')
                           : apiError($result['error'] ?? 'Échec', 500);
    }

    if ($action === 'remove') {
        requireParam($body, 'ieee');
        $result = zigbee()->removeDevice($body['ieee']);
        $result['success'] ? apiSuccess(null, 'Device supprimé')
                           : apiError($result['error'] ?? 'Échec', 500);
    }

    apiError('Action inconnue', 400);
}

apiError('Méthode non supportée', 405);
