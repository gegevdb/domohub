<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

checkRateLimit();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    switch ($action) {
        case 'status':
            // Vérifier si zigbee-herdsman tourne
            $status = [
                'ha_reachable' => true, // Simuler HA connecté
                'permit_join' => true, // Notre service est toujours en mode pairing
                'total_devices' => (int)db()->fetchOne('SELECT COUNT(*) as c FROM zigbee_devices')['c'] ?? 0,
                'paired_devices' => (int)db()->fetchOne('SELECT COUNT(*) as c FROM zigbee_devices WHERE paired = 1')['c'] ?? 0,
            ];
            apiSuccess($status);
            break;
            
        case 'devices':
            // Récupérer les appareils (simulé pour l'instant)
            $devices = db()->fetchAll('SELECT * FROM zigbee_devices ORDER BY last_seen DESC');
            apiSuccess($devices);
            break;
            
        case 'permit_join':
            // Activer/désactiver le pairing
            $enable = filter_var($_GET['enable'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            $seconds = (int)($_GET['seconds'] ?? 60);
            
            // Simuler l'activation (en vrai, on communiquerait avec le service)
            apiSuccess([
                'enabled' => $enable,
                'seconds' => $seconds,
                'message' => $enable ? 'Pairage activé' : 'Pairage désactivé'
            ]);
            break;
    }
}

if ($method === 'POST') {
    requireCsrf();
    requireMethod('POST');
    
    $body = getJsonBody();
    if (!is_array($body)) {
        apiError('Corps JSON invalide', 400);
    }
    
    switch ($action) {
        case 'pair':
            // Appairer un appareil
            $ieee = $body['ieee'] ?? '';
            $name = $body['name'] ?? '';
            $roomId = $body['room_id'] ?? null;
            
            if (empty($ieee)) {
                apiError('IEEE address requis', 400);
            }
            
            // Insérer ou mettre à jour l'appareil
            db()->query(
                'INSERT OR REPLACE INTO zigbee_devices (ieee, name, device_type, manufacturer, model, paired, room_id, last_seen) 
                 VALUES (?, ?, ?, ?, ?, 1, ?, datetime("now"))',
                [$ieee, $name, 'unknown', 'Unknown', $roomId]
            );
            
            apiSuccess(['paired' => true, 'ieee' => $ieee]);
            break;
            
        case 'remove':
            // Supprimer un appareil
            $ieee = $body['ieee'] ?? '';
            
            if (empty($ieee)) {
                apiError('IEEE address requis', 400);
            }
            
            db()->query('DELETE FROM zigbee_devices WHERE ieee = ?', [$ieee]);
            apiSuccess(['removed' => true]);
            break;
            
        case 'sync':
            // Synchroniser (simulé)
            apiSuccess(['synced' => true, 'devices_found' => 0]);
            break;
    }
}

apiError('Action non valide', 400);
