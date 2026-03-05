<?php
/**
 * DOMOGLASS PRO — Configuration globale
 * Ce fichier est le seul à modifier pour adapter l'installation.
 * Ne jamais versionner avec des secrets réels (utilisez .env ou config.local.php).
 */

declare(strict_types=1);

// ============================================================
//  ENVIRONNEMENT
// ============================================================
define('DOMOGLASS_VERSION', '2.0.0');
define('DOMOGLASS_ENV', getenv('APP_ENV') ?: 'production'); // development|production
define('BASE_PATH', __DIR__);
define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));

// ============================================================
//  BASE DE DONNÉES SQLite
// ============================================================
define('DB_PATH', BASE_PATH . '/database/domoglass.sqlite');

// ============================================================
//  MOSQUITTO — MQTT (communication côté serveur PHP)
//  Le client WebSocket JS se connecte directement au broker.
// ============================================================
define('MQTT_HOST',     getenv('MQTT_HOST')     ?: 'localhost');
define('MQTT_PORT',     (int)(getenv('MQTT_PORT')     ?: 1883));   // Port TCP pour PHP
define('MQTT_PORT_WS',  (int)(getenv('MQTT_PORT_WS')  ?: 9001));   // Port WebSocket pour JS
define('MQTT_USER',     getenv('MQTT_USER')     ?: '');
define('MQTT_PASSWORD', getenv('MQTT_PASSWORD') ?: '');
define('MQTT_CLIENT_ID','domoglass-php-' . gethostname());
define('MQTT_TIMEOUT',  5);   // secondes

// Topics MQTT
define('MQTT_TOPIC_BASE',    'domoglass');
define('MQTT_TOPIC_DEVICES', MQTT_TOPIC_BASE . '/devices');
define('MQTT_TOPIC_CMD',     MQTT_TOPIC_BASE . '/cmd');

// ============================================================
//  HOME ASSISTANT — API REST + WebSocket
//  Générer un token : Profil HA → Sécurité → Tokens longue durée
// ============================================================
$haUrl = parse_url(getenv('HA_URL') ?: 'http://localhost:8123');
define('HA_HOST',     $haUrl['host'] ?: 'localhost');
define('HA_PORT',     (int)($haUrl['port'] ?: 8123));
define('HA_SSL',      ($haUrl['scheme'] ?? 'http') === 'https');
define('HA_TOKEN',    getenv('HA_TOKEN') ?: '');   // LONG_LIVED_ACCESS_TOKEN
define('HA_BASE_URL', (HA_SSL ? 'https' : 'http') . '://' . HA_HOST . ':' . HA_PORT);
define('HA_WS_URL',   (HA_SSL ? 'wss'  : 'ws')   . '://' . HA_HOST . ':' . HA_PORT . '/api/websocket');

// ============================================================
//  ZIGBEE (via ZHA + Home Assistant)
// ============================================================
define('ZHA_PERMIT_JOIN_DURATION', 60);  // secondes d'appairage ouvert
define('ZIGBEE2MQTT_BASE_TOPIC',   'zigbee2mqtt');  // si Zigbee2MQTT en parallèle

// ============================================================
//  SÉCURITÉ APPLICATIVE
// ============================================================
define('SESSION_LIFETIME',  3600);           // 1h
define('CSRF_TOKEN_LENGTH', 32);
define('API_RATE_LIMIT',    120);            // requêtes/minute par IP
define('LOG_LEVEL',         DOMOGLASS_ENV === 'development' ? 'debug' : 'warning');

// Logs
define('LOG_PATH',          BASE_PATH . '/logs/app.log');

// ============================================================
//  ÉNERGIE
// ============================================================
define('ENERGY_PRICE_KWH', 0.2516);  // €/kWh tarif EDF base 2025
define('ENERGY_HISTORY_DAYS', 30);   // jours de rétention

// ============================================================
//  CHARGEMENT CONFIG LOCALE (surcharge sans toucher ce fichier)
// ============================================================
$localConfig = BASE_PATH . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// ============================================================
//  INITIALISATION SESSIONS
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (HA_SSL) {
        ini_set('session.cookie_secure', '1');
    }
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
//  TIMEZONE
// ============================================================
date_default_timezone_set('Europe/Paris');
