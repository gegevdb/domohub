<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/api_helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

checkRateLimit();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // Récupération d'une clé spécifique
    $key = $_GET['key'] ?? '';
    if ($key === '') {
        apiError('Clé manquante', 400);
    }

    $db = db();
    $value = $db->getConfig($key, null);

    apiSuccess(['key' => $key, 'value' => $value]);
}

if ($method === 'POST') {
    authApiRequireAdmin();
    requireCsrf();
    requireMethod('POST');

    $body = getJsonBody();
    if (!is_array($body)) {
        apiError('Corps JSON invalide', 400);
    }

    // On n'autorise l'enregistrement que d'une liste blanche de clés.
    $allowedKeys = [
        // UI
        'theme_palette',
        'auto_dark_mode',
        'dark_mode_time',

        // Home / Geo / Floorplans
        'home_lat',
        'home_lng',
        'home_address',
        'home_floors_json',
        'home_floorplans_json',

        // Dongles USB
        'skyconnect_config',
        'wifi_dongle_config',

        // Freebox
        'freebox_host',
        'freebox_port',
        'freebox_app_token',

        // Bbox (sans user/pass)
        'bbox_host',
        'bbox_proto',
        'bbox_endpoint',
    ];

    $toSave = [];

    // Compatibilité: ancien format {key,value}
    if (isset($body['key']) && array_key_exists('value', $body)) {
        $k = is_string($body['key']) ? trim($body['key']) : '';
        if ($k === '') {
            apiError('Clé invalide', 400);
        }
        if (!in_array($k, $allowedKeys, true)) {
            apiError('Clé non autorisée: ' . $k, 403);
        }
        $body = [$k => $body['value']];
    }

    foreach ($allowedKeys as $k) {
        if (!array_key_exists($k, $body)) {
            continue;
        }

        $v = $body[$k];
        if (is_bool($v)) {
            $v = $v ? '1' : '0';
        } elseif (is_int($v) || is_float($v)) {
            $v = (string)$v;
        } elseif (is_string($v)) {
            $v = trim($v);
        } elseif ($v === null) {
            $v = '';
        } else {
            apiError('Type invalide pour la clé: ' . $k, 400);
        }

        // Petites contraintes de sécurité
        if (strlen($v) > 2048) {
            apiError('Valeur trop longue pour la clé: ' . $k, 400);
        }

        $toSave[$k] = $v;
    }

    if (empty($toSave)) {
        apiError('Aucune clé autorisée à enregistrer', 400);
    }

    $db = db();

    // Upsert config
    $sql = "INSERT INTO config(key, value, updated_at) VALUES(?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP";

    foreach ($toSave as $k => $v) {
        $result = $db->execute($sql, [$k, $v]);
        if (!$result) {
            error_log("Failed to save config $k = $v");
            apiError('Erreur sauvegarde base de données', 500);
        }
    }

    apiSuccess(['saved' => array_keys($toSave)]);
}

// Par défaut : refuser.
apiError('Méthode non supportée', 405);
