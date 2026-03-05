#!/usr/bin/env php
<?php
/**
 * DomoGlass Pro - Worker MQTT (daemon CLI)
 *
 * Ce script tourne en arrière-plan et :
 *   1. S'abonne à tous les topics des devices
 *   2. Met à jour la base SQLite en temps réel
 *   3. Relaye les messages vers les clients SSE via la DB
 *
 * Lancement :
 *   php workers/mqtt_worker.php
 *
 * En production avec systemd, créez /etc/systemd/system/domoglass-mqtt.service
 * (voir README.md)
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/MqttService.php';
require_once __DIR__ . '/../services/DeviceService.php';

// Traitement des signaux Unix (arrêt propre)
declare(ticks=1);
$running = true;

pcntl_signal(SIGTERM, function() use (&$running) { $running = false; });
pcntl_signal(SIGINT,  function() use (&$running) { $running = false; });

$db      = Database::getInstance();
$mqtt    = MqttService::getInstance();
$devices = DeviceService::getInstance();

echo "[" . date('H:i:s') . "] DomoGlass MQTT Worker démarré\n";

// Connexion au broker
if (!$mqtt->connect()) {
    echo "[ERROR] Impossible de se connecter à Mosquitto. Abandon.\n";
    exit(1);
}

// ─── Abonnements ──────────────────────────────────────────────────────────────

// État de tous les devices MQTT
$mqtt->subscribe(MQTT_TOPIC_DEVICES . '/+/state', function(string $topic, string $payload) use ($db, $devices) {
    $parts    = explode('/', $topic);
    $deviceId = (int)$parts[array_search('state', $parts) - 1] ?? 0;

    if ($deviceId <= 0) return;

    $data = json_decode($payload, true) ?? ['state' => $payload];
    $devices->updateState($deviceId, $data);

    echo "[" . date('H:i:s') . "] STATE  device#$deviceId : $payload\n";
});

// Attributs des devices
$mqtt->subscribe(MQTT_TOPIC_DEVICES . '/+/attributes', function(string $topic, string $payload) use ($devices) {
    $parts    = explode('/', $topic);
    $deviceId = (int)$parts[array_search('attributes', $parts) - 1] ?? 0;

    if ($deviceId <= 0) return;

    $attrs = json_decode($payload, true) ?? [];
    $devices->updateState($deviceId, $attrs);
});

// Données énergie
$mqtt->subscribe(MQTT_TOPIC_BASE . '/energy/#', function(string $topic, string $payload) use ($db) {
    $data = json_decode($payload, true);
    if (!$data || !isset($data['device_id'])) return;

    $db->insert('energy_logs', [
        'device_id' => (int)$data['device_id'],
        'power_w'   => (float)($data['power_w']    ?? 0),
        'energy_kwh'=> (float)($data['energy_kwh'] ?? 0),
        'voltage_v' => $data['voltage_v'] ?? null,
        'current_a' => $data['current_a'] ?? null,
    ]);
});

// Bridge Zigbee2MQTT (si présent en complément de ZHA)
$mqtt->subscribe('zigbee2mqtt/bridge/event', function(string $topic, string $payload) use ($db) {
    $event = json_decode($payload, true);
    if (!$event) return;

    // Nouvel appairage Zigbee
    if (($event['type'] ?? '') === 'device_joined') {
        $device = $event['data'] ?? [];
        $db->insert('notifications', [
            'title'   => 'Nouveau device Zigbee',
            'message' => "Device rejoint : " . ($device['friendly_name'] ?? $device['ieee_address'] ?? 'Inconnu'),
            'type'    => 'success',
            'source'  => 'zigbee2mqtt',
        ]);
        echo "[" . date('H:i:s') . "] ZIGBEE device rejoint : " . json_encode($device) . "\n";
    }
});

// Capteurs Zigbee2MQTT
$mqtt->subscribe('zigbee2mqtt/+', function(string $topic, string $payload) use ($db, $devices) {
    $parts      = explode('/', $topic);
    $deviceName = $parts[1] ?? '';

    if ($deviceName === 'bridge') return; // Ignore les messages du bridge

    $data = json_decode($payload, true);
    if (!$data) return;

    // Cherche le device par son topic MQTT
    $device = $db->fetchOne(
        "SELECT id FROM devices WHERE mqtt_topic LIKE ?",
        ["%$deviceName%"]
    );

    if (!$device) return;

    // Met à jour l'état
    $state = null;
    if (isset($data['state'])) {
        $state = strtolower($data['state']) === 'on' ? 'on' : 'off';
    }

    $devices->updateState($device['id'], [
        'state'       => $state,
        'temperature' => $data['temperature'] ?? null,
        'humidity'    => $data['humidity']    ?? null,
        'brightness'  => $data['brightness']  ?? null,
        'linkquality' => $data['linkquality'] ?? null,
    ]);

    // Log énergie si disponible
    if (isset($data['power'])) {
        $db->insert('energy_logs', [
            'device_id' => $device['id'],
            'power_w'   => (float)$data['power'],
            'energy_kwh'=> (float)($data['energy'] ?? 0),
            'voltage_v' => $data['voltage'] ?? null,
            'current_a' => $data['current'] ?? null,
        ]);
    }
});

// ─── Boucle principale ────────────────────────────────────────────────────────

echo "[" . date('H:i:s') . "] Abonnements actifs. En attente de messages...\n";

while ($running) {
    $mqtt->loop(maxSeconds: 1);

    // Heartbeat toutes les 30s
    static $lastHeartbeat = 0;
    if (time() - $lastHeartbeat >= 30) {
        $mqtt->publish(MQTT_TOPIC_BASE . '/worker/heartbeat', json_encode([
            'ts'      => time(),
            'memory'  => round(memory_get_usage(true) / 1024 / 1024, 1) . 'MB',
        ]), retain: true);
        $lastHeartbeat = time();
    }
}

echo "[" . date('H:i:s') . "] Arrêt du worker MQTT.\n";
$mqtt->disconnect();
exit(0);
