<?php
/**
 * DomoGlass Pro - Configuration centrale
 * Adaptez ces valeurs à votre environnement
 */

// ─── Environnement ────────────────────────────────────────────────────────────
define('APP_ENV',     getenv('APP_ENV') ?: 'development');  // production | development
define('APP_NAME',    'DomoGlass Pro');
define('APP_VERSION', '2.0.0');
define('APP_ROOT',    dirname(__DIR__));
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost');

// ─── Base de données SQLite ───────────────────────────────────────────────────
define('DB_PATH', APP_ROOT . '/db/domoglass.sqlite');

// ─── Mosquitto MQTT ───────────────────────────────────────────────────────────
// Connexion PHP côté serveur (php-mqtt/client via Composer)
define('MQTT_HOST',     getenv('MQTT_HOST')     ?: '127.0.0.1');
define('MQTT_PORT',     (int)(getenv('MQTT_PORT') ?: 1883));
define('MQTT_WS_PORT',  (int)(getenv('MQTT_WS_PORT') ?: 9001));   // WebSocket pour le front JS
define('MQTT_USERNAME', getenv('MQTT_USERNAME') ?: '');
define('MQTT_PASSWORD', getenv('MQTT_PASSWORD') ?: '');
define('MQTT_CLIENT_ID','domoglass-server-' . gethostname());

// Topics de base
define('MQTT_TOPIC_BASE',    'domoglass');
define('MQTT_TOPIC_DEVICES', MQTT_TOPIC_BASE . '/devices');
define('MQTT_TOPIC_CMD',     MQTT_TOPIC_BASE . '/cmd');

// ─── Home Assistant (ZHA) ─────────────────────────────────────────────────────
define('HA_URL',        getenv('HA_URL')        ?: 'http://homeassistant.local:8123');
define('HA_TOKEN',      getenv('HA_TOKEN')      ?: 'VOTRE_LONG_LIVED_TOKEN_ICI');
define('HA_WS_URL',     getenv('HA_WS_URL')     ?: 'ws://homeassistant.local:8123/api/websocket');

// ─── Sécurité ─────────────────────────────────────────────────────────────────
define('APP_SECRET',    getenv('APP_SECRET')    ?: bin2hex(random_bytes(32)));
define('API_KEY',       getenv('API_KEY')        ?: 'CHANGEZ_CETTE_CLE_API');
define('SESSION_LIFETIME', 3600 * 8);  // 8 heures

// ─── SSE (Server-Sent Events) ─────────────────────────────────────────────────
define('SSE_RETRY_MS',  3000);   // Reconnexion client en ms
define('SSE_HEARTBEAT', 25);     // Heartbeat en secondes

// ─── Logging ──────────────────────────────────────────────────────────────────
define('LOG_PATH',  APP_ROOT . '/logs/app.log');
define('LOG_LEVEL', APP_ENV === 'production' ? 'warning' : 'debug');

// ─── Chargement automatique .env (optionnel) ──────────────────────────────────
$envFile = APP_ROOT . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}
