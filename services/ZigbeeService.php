<?php
/**
 * DomoGlass Pro - Service Zigbee via ZHA (Home Assistant)
 *
 * Utilise :
 *   - L'API REST de Home Assistant pour les states et commandes
 *   - Le WebSocket HA pour l'écoute temps réel
 *   - L'API ZHA spécifique pour le pairing et la gestion des devices
 *
 * Prérequis : extension PHP cURL + sockets
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

class ZigbeeService
{
    private static ?ZigbeeService $instance = null;
    private Database $db;
    private int $wsMessageId = 1;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── API REST Home Assistant ───────────────────────────────────────────────

    /**
     * Appel générique à l'API REST HA
     */
    private function haRequest(
        string $endpoint,
        string $method = 'GET',
        ?array $body = null
    ): array|false {
        $url = rtrim(HA_BASE_URL, '/') . '/api' . $endpoint;

        $headers = [
            'Authorization: Bearer ' . HA_TOKEN,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log('error', "cURL error on $endpoint : $error");
            return false;
        }

        if ($httpCode >= 400) {
            $this->log('error', "HA API $endpoint returned HTTP $httpCode : $response");
            return false;
        }

        $decoded = json_decode($response, true);
        return $decoded ?? [];
    }

    // ─── Découverte et synchronisation des devices ZHA ────────────────────────

    /**
     * Récupère tous les devices Zigbee depuis ZHA
     * Endpoint HA : GET /api/zha/devices
     */
    public function getZhaDevices(): array
    {
        $devices = $this->haRequest('/zha/devices');
        if ($devices === false) {
            return [];
        }

        return array_map(fn($d) => [
            'ieee'         => $d['ieee'] ?? '',
            'nwk'          => $d['nwk'] ?? '',
            'name'         => $d['name'] ?? $d['ieee'] ?? 'Inconnu',
            'model'        => $d['model_id'] ?? '',
            'manufacturer' => $d['manufacturer'] ?? '',
            'device_type'  => $d['device_type'] ?? '',
            'lqi'          => $d['lqi'] ?? 0,
            'rssi'         => $d['rssi'] ?? null,
            'available'    => $d['available'] ?? false,
            'quirk'        => $d['quirk_applied'] ?? false,
            'entities'     => $d['entities'] ?? [],
        ], $devices);
    }

    /**
     * Récupère un device ZHA spécifique par son adresse IEEE
     */
    public function getZhaDevice(string $ieeeAddress): array|false
    {
        return $this->haRequest('/zha/devices/' . urlencode($ieeeAddress));
    }

    /**
     * Synchronise les devices ZHA dans la base de données locale
     * Crée les entrées manquantes, met à jour les existantes
     */
    public function syncDevicesToDatabase(): array
    {
        $zhaDevices = $this->getZhaDevices();
        $synced = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($zhaDevices as $zha) {
            if (empty($zha['ieee'])) {
                continue;
            }

            $existing = $this->db->fetchOne(
                'SELECT id FROM devices WHERE ieee_address = ?',
                [$zha['ieee']]
            );

            // Détermine le type DomoGlass depuis le type ZHA
            $type = $this->mapZhaDeviceType($zha['device_type'], $zha['entities']);

            // Cherche l'entity_id principal dans les entities
            $mainEntityId = $this->findMainEntityId($zha['entities'], $type);

            // Construit le topic MQTT Zigbee2MQTT équivalent (si bridged)
            $mqttTopic    = 'zigbee2mqtt/' . ($zha['name'] ?? $zha['ieee']);
            $mqttCmdTopic = $mqttTopic . '/set';

            if ($existing) {
                $this->db->update('devices', [
                    'name'       => $zha['name'],
                    'is_online'  => $zha['available'] ? 1 : 0,
                    'entity_id'  => $mainEntityId,
                    'attributes' => json_encode([
                        'model'        => $zha['model'],
                        'manufacturer' => $zha['manufacturer'],
                        'lqi'          => $zha['lqi'],
                        'rssi'         => $zha['rssi'],
                    ]),
                    'last_seen'  => date('Y-m-d H:i:s'),
                ], 'ieee_address = ?', [$zha['ieee']]);
                $synced['updated']++;
            } else {
                $this->db->insert('devices', [
                    'name'           => $zha['name'],
                    'type'           => $type,
                    'protocol'       => 'zigbee',
                    'ieee_address'   => $zha['ieee'],
                    'entity_id'      => $mainEntityId,
                    'mqtt_topic'     => $mqttTopic,
                    'mqtt_cmd_topic' => $mqttCmdTopic,
                    'icon'           => $this->mapTypeToIcon($type),
                    'is_online'      => $zha['available'] ? 1 : 0,
                    'attributes'     => json_encode([
                        'model'        => $zha['model'],
                        'manufacturer' => $zha['manufacturer'],
                        'lqi'          => $zha['lqi'],
                        'rssi'         => $zha['rssi'],
                    ]),
                    'last_seen'      => date('Y-m-d H:i:s'),
                ]);
                $synced['created']++;
            }
        }

        $this->log('info', sprintf(
            'Sync ZHA : %d créés, %d mis à jour, %d ignorés',
            $synced['created'], $synced['updated'], $synced['skipped']
        ));

        return $synced;
    }

    // ─── Pairing / Appairage ──────────────────────────────────────────────────

    /**
     * Active le mode pairing sur le coordinateur ZHA
     *
     * @param int $duration  Durée du pairing en secondes (60 par défaut)
     * @param string|null $ieeeAddress  IEEE du device routeur cible (null = coordinateur)
     */
    public function permitJoin(int $duration = 60, ?string $ieeeAddress = null): bool
    {
        $body = ['duration' => $duration];
        if ($ieeeAddress !== null) {
            $body['ieee'] = $ieeeAddress;
        }

        $result = $this->haRequest('/services/zha/permit', 'POST', $body);
        if ($result !== false) {
            $this->log('info', "Pairing activé pour {$duration}s" . ($ieeeAddress ? " via $ieeeAddress" : ''));
            return true;
        }
        return false;
    }

    /**
     * Désactive le mode pairing
     */
    public function stopJoin(): bool
    {
        $result = $this->haRequest('/services/zha/permit', 'POST', ['duration' => 0]);
        $this->log('info', 'Pairing désactivé');
        return $result !== false;
    }

    /**
     * Supprime un device ZHA
     */
    public function removeDevice(string $ieeeAddress): bool
    {
        $result = $this->haRequest(
            '/services/zha/remove',
            'POST',
            ['ieee': $ieeeAddress]
        );

        if ($result !== false) {
            // Supprime aussi en base locale
            $this->db->delete('devices', 'ieee_address = ?', [$ieeeAddress]);
            $this->log('info', "Device ZHA supprimé : $ieeeAddress");
            return true;
        }
        return false;
    }

    /**
     * Reconfigure un device ZHA (force re-interview)
     */
    public function reconfigureDevice(string $ieeeAddress): bool
    {
        $result = $this->haRequest(
            '/services/zha/reconfigure_device',
            'POST',
            ['ieee' => $ieeeAddress]
        );
        return $result !== false;
    }

    // ─── Contrôle des devices ─────────────────────────────────────────────────

    /**
     * Récupère l'état actuel d'une entité HA
     */
    public function getEntityState(string $entityId): array|false
    {
        return $this->haRequest('/states/' . $entityId);
    }

    /**
     * Appelle un service HA (turn_on, turn_off, etc.)
     */
    public function callService(
        string $domain,
        string $service,
        array $serviceData = []
    ): bool {
        $result = $this->haRequest(
            "/services/$domain/$service",
            'POST',
            $serviceData
        );
        return $result !== false;
    }

    /**
     * Commande un device DomoGlass via son entity_id HA
     */
    public function commandDevice(int $deviceId, string $action, array $params = []): bool
    {
        $device = $this->db->fetchOne(
            'SELECT entity_id, type, name FROM devices WHERE id = ?',
            [$deviceId]
        );

        if (!$device || !$device['entity_id']) {
            $this->log('warning', "Device $deviceId sans entity_id HA");
            return false;
        }

        [$domain] = explode('.', $device['entity_id']);
        $serviceData = ['entity_id' => $device['entity_id'], ...$params];

        $success = $this->callService($domain, $action, $serviceData);

        // Log en base
        $this->db->insert('actions', [
            'device_id' => $deviceId,
            'action'    => $action,
            'payload'   => json_encode($serviceData),
            'source'    => 'zigbee',
            'status'    => $success ? 'success' : 'error',
        ]);

        $this->log($success ? 'info' : 'error',
            "Commande Zigbee $action sur '{$device['name']}' : " . ($success ? 'OK' : 'KO')
        );

        return $success;
    }

    // ─── Réseau Zigbee ────────────────────────────────────────────────────────

    /**
     * Récupère la topologie du réseau Zigbee (pour visualisation)
     * Endpoint : GET /api/zha/topology
     */
    public function getNetworkTopology(): array
    {
        $result = $this->haRequest('/zha/topology');
        return $result ?: [];
    }

    /**
     * Statistiques du réseau Zigbee
     */
    public function getNetworkStats(): array
    {
        $devices = $this->getZhaDevices();

        return [
            'total'     => count($devices),
            'online'    => count(array_filter($devices, fn($d) => $d['available'])),
            'offline'   => count(array_filter($devices, fn($d) => !$d['available'])),
            'avg_lqi'   => count($devices) > 0
                ? round(array_sum(array_column($devices, 'lqi')) / count($devices))
                : 0,
        ];
    }

    // ─── Mappers ──────────────────────────────────────────────────────────────

    private function mapZhaDeviceType(string $zhaType, array $entities): string
    {
        return match(true) {
            str_contains($zhaType, 'light')  => 'light',
            str_contains($zhaType, 'switch') => 'switch',
            str_contains($zhaType, 'sensor') => 'sensor',
            str_contains($zhaType, 'cover')  => 'cover',
            str_contains($zhaType, 'lock')   => 'switch',
            $this->hasEntityDomain($entities, 'climate') => 'thermostat',
            $this->hasEntityDomain($entities, 'media_player') => 'media',
            default => 'switch',
        };
    }

    private function hasEntityDomain(array $entities, string $domain): bool
    {
        foreach ($entities as $entity) {
            if (isset($entity['entity_id']) && str_starts_with($entity['entity_id'], $domain . '.')) {
                return true;
            }
        }
        return false;
    }

    private function findMainEntityId(array $entities, string $type): ?string
    {
        $domainMap = [
            'light'     => 'light',
            'switch'    => 'switch',
            'sensor'    => 'sensor',
            'thermostat'=> 'climate',
            'cover'     => 'cover',
            'media'     => 'media_player',
        ];

        $targetDomain = $domainMap[$type] ?? null;

        foreach ($entities as $entity) {
            if ($targetDomain && isset($entity['entity_id'])
                && str_starts_with($entity['entity_id'], $targetDomain . '.')) {
                return $entity['entity_id'];
            }
        }

        return $entities[0]['entity_id'] ?? null;
    }

    private function mapTypeToIcon(string $type): string
    {
        return match($type) {
            'light'     => 'fa-lightbulb',
            'switch'    => 'fa-toggle-on',
            'sensor'    => 'fa-thermometer-half',
            'thermostat'=> 'fa-temperature-high',
            'cover'     => 'fa-window-maximize',
            'media'     => 'fa-music',
            'camera'    => 'fa-video',
            default     => 'fa-microchip',
        };
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    private function log(string $level, string $message): void
    {
        $logDir = dirname(LOG_PATH);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $line = sprintf("[%s] [ZIGBEE] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        file_put_contents(LOG_PATH, $line, FILE_APPEND | LOCK_EX);
    }
}
