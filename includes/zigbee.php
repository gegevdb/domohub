<?php
/**
 * DOMOGLASS PRO — Module Zigbee ZHA (via Home Assistant)
 *
 * Gère :
 *  - L'appairage (permit join)
 *  - La découverte et synchronisation des devices ZHA
 *  - Les commandes directes via l'API REST HA
 *  - La mise en cache dans zigbee_devices
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class ZigbeeManager
{
    private string $baseUrl;
    private array  $headers;

    public function __construct(
        private readonly string $haHost  = HA_HOST,
        private readonly int    $haPort  = HA_PORT,
        private readonly string $token   = HA_TOKEN,
        private readonly bool   $ssl     = HA_SSL,
    ) {
        $scheme        = $this->ssl ? 'https' : 'http';
        $this->baseUrl = "$scheme://{$this->haHost}:{$this->haPort}";
        $this->headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
        ];
    }

    // --------------------------------------------------------
    //  APPAIRAGE — Ouvrir le réseau Zigbee
    // --------------------------------------------------------

    /**
     * Activer le mode appairage pour $duration secondes.
     * Appelle le service zha.permit via l'API HA.
     */
    public function permitJoin(int $duration = ZHA_PERMIT_JOIN_DURATION): array
    {
        $payload = [
            'duration' => $duration,
        ];

        $result = $this->callService('zha', 'permit', $payload);

        if ($result['success']) {
            db()->setConfig('zigbee_permit_join', '1');
            db()->addNotification(
                'info',
                'Appairage Zigbee ouvert',
                "Réseau Zigbee ouvert pendant {$duration} secondes.",
                'zigbee'
            );
            db()->logAction(null, 'Zigbee Network', 'permit_join', ['duration' => $duration], 'system');
        }

        return $result;
    }

    /**
     * Fermer le mode appairage immédiatement.
     */
    public function denyJoin(): array
    {
        $result = $this->callService('zha', 'permit', ['duration' => 0]);
        db()->setConfig('zigbee_permit_join', '0');
        return $result;
    }

    // --------------------------------------------------------
    //  DÉCOUVERTE — Synchroniser les devices depuis ZHA
    // --------------------------------------------------------

    /**
     * Récupère tous les appareils ZHA depuis HA et les synchronise en base.
     * Retourne la liste mise à jour.
     */
    public function syncDevices(): array
    {
        $response = $this->apiGet('/api/states');
        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error'], 'devices' => []];
        }

        $zigbeeDevices = [];

        foreach ($response['data'] as $entity) {
            // Filtrer uniquement les entités ZHA (attribut intégration ou préfixe)
            $attributes = $entity['attributes'] ?? [];

            // ZHA expose "integration" ou des attributs spécifiques
            $isZha = isset($attributes['ieee'])
                || (isset($attributes['platform']) && $attributes['platform'] === 'zha')
                || str_starts_with($entity['entity_id'], 'zha.');

            if (!$isZha && !$this->looksLikeZigbee($entity)) {
                continue;
            }

            $device = $this->normalizeZhaEntity($entity);
            $zigbeeDevices[] = $device;
            $this->upsertZigbeeDevice($device, $entity);
        }

        // Deuxième passe : récupérer via l'endpoint ZHA dédié si disponible
        $zhaResponse = $this->apiGet('/api/zha/devices');
        if ($zhaResponse['success'] && is_array($zhaResponse['data'])) {
            foreach ($zhaResponse['data'] as $zhaDevice) {
                $device = $this->normalizeZhaDevice($zhaDevice);
                $this->upsertZigbeeDevice($device, $zhaDevice);

                // Dédupliquer
                $existing = array_filter($zigbeeDevices, fn($d) => $d['ieee'] === $device['ieee']);
                if (empty($existing)) {
                    $zigbeeDevices[] = $device;
                }
            }
        }

        return [
            'success' => true,
            'count'   => count($zigbeeDevices),
            'devices' => $zigbeeDevices,
        ];
    }

    /**
     * Récupère les devices depuis la table locale (cache).
     */
    public function getCachedDevices(bool $pairedOnly = false): array
    {
        $sql = 'SELECT * FROM zigbee_devices';
        $params = [];

        if ($pairedOnly) {
            $sql .= ' WHERE paired = 1';
        }

        $sql .= ' ORDER BY name ASC';

        return db()->fetchAll($sql, $params);
    }

    /**
     * Appairer un device Zigbee découvert dans DomoGlass (créer l'entrée device).
     */
    public function pairDevice(string $ieee, array $options = []): array
    {
        $zigbeeDevice = db()->fetchOne(
            'SELECT * FROM zigbee_devices WHERE ieee = ?',
            [$ieee]
        );

        if (!$zigbeeDevice) {
            return ['success' => false, 'error' => "Device IEEE $ieee introuvable en base"];
        }

        // Déterminer le type DomoGlass
        $type = $this->mapDeviceType($zigbeeDevice['device_type'], $zigbeeDevice['model'] ?? '');

        // Créer le device dans la table devices
        $slug = $this->makeSlug($options['name'] ?? $zigbeeDevice['name']);

        $deviceId = db()->insert(
            'INSERT INTO devices (name, slug, type, protocol, room_id,
                mqtt_topic_state, mqtt_topic_set,
                ha_entity_id, zigbee_ieee, zigbee_model,
                state, icon, color)
             VALUES (?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?)
             ON CONFLICT(slug) DO UPDATE SET
                ha_entity_id = excluded.ha_entity_id,
                zigbee_ieee  = excluded.zigbee_ieee,
                updated_at   = CURRENT_TIMESTAMP',
            [
                $options['name']    ?? $zigbeeDevice['name'],
                $slug,
                $type,
                'zigbee',
                $options['room_id'] ?? null,
                // Topics MQTT si Zigbee2MQTT est aussi présent
                ZIGBEE2MQTT_BASE_TOPIC . '/' . ($zigbeeDevice['name'] ?? $ieee),
                ZIGBEE2MQTT_BASE_TOPIC . '/' . ($zigbeeDevice['name'] ?? $ieee) . '/set',
                // ZHA entity
                $zigbeeDevice['ha_entity_id'],
                $ieee,
                $zigbeeDevice['model'],
                'unknown',
                $this->getIconForType($type),
                $this->getColorForType($type),
            ]
        );

        // Mettre à jour la table zigbee_devices
        db()->execute(
            'UPDATE zigbee_devices SET paired = 1, device_id = ? WHERE ieee = ?',
            [$deviceId, $ieee]
        );

        db()->logAction(
            (int)$deviceId,
            $options['name'] ?? $zigbeeDevice['name'],
            'paired',
            ['ieee' => $ieee, 'protocol' => 'zigbee'],
            'system'
        );

        return [
            'success'   => true,
            'device_id' => $deviceId,
            'message'   => "Device appairé avec succès",
        ];
    }

    /**
     * Supprimer un device Zigbee du réseau ZHA.
     */
    public function removeDevice(string $ieee): array
    {
        $result = $this->callService('zha', 'remove', ['ieee' => $ieee]);

        if ($result['success']) {
            db()->execute(
                'UPDATE zigbee_devices SET paired = 0, device_id = NULL WHERE ieee = ?',
                [$ieee]
            );
            db()->execute(
                'UPDATE devices SET enabled = 0 WHERE zigbee_ieee = ?',
                [$ieee]
            );
        }

        return $result;
    }

    // --------------------------------------------------------
    //  COMMANDES — Contrôle des entités HA
    // --------------------------------------------------------

    /**
     * Envoyer une commande à une entité HA (light, switch, climate...).
     */
    public function callEntityService(string $entityId, string $service, array $data = []): array
    {
        $domain = explode('.', $entityId)[0]; // light, switch, climate...
        $data['entity_id'] = $entityId;

        return $this->callService($domain, $service, $data);
    }

    /**
     * Lire l'état actuel d'une entité HA.
     */
    public function getEntityState(string $entityId): array
    {
        $response = $this->apiGet('/api/states/' . urlencode($entityId));

        if ($response['success']) {
            $entity = $response['data'];
            // Mise en cache base
            $device = db()->fetchOne('SELECT id FROM devices WHERE ha_entity_id = ?', [$entityId]);
            if ($device) {
                db()->updateDeviceState(
                    (int)$device['id'],
                    $entity['state'],
                    $entity['attributes'] ?? []
                );
            }
        }

        return $response;
    }

    /**
     * Lire tous les états d'un coup (optimisé).
     */
    public function getAllStates(): array
    {
        return $this->apiGet('/api/states');
    }

    // --------------------------------------------------------
    //  Helpers HTTP
    // --------------------------------------------------------

    private function apiGet(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    private function callService(string $domain, string $service, array $data = []): array
    {
        return $this->request('POST', "/api/services/$domain/$service", $data);
    }

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $ch  = curl_init($url);

        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $this->ssl,
        ];

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST]       = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $curlOptions);

        $body  = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL: $error", 'data' => null];
        }

        if ($code < 200 || $code >= 300) {
            return ['success' => false, 'error' => "HTTP $code", 'data' => json_decode($body, true)];
        }

        return ['success' => true, 'data' => json_decode($body, true), 'http_code' => $code];
    }

    // --------------------------------------------------------
    //  Normalisation & helpers internes
    // --------------------------------------------------------

    private function normalizeZhaEntity(array $entity): array
    {
        $attr = $entity['attributes'] ?? [];
        return [
            'ieee'         => $attr['ieee']         ?? '',
            'nwk'          => $attr['nwk']          ?? '',
            'name'         => $attr['friendly_name'] ?? $entity['entity_id'],
            'model'        => $attr['model']         ?? '',
            'manufacturer' => $attr['manufacturer']  ?? '',
            'device_type'  => $this->inferDeviceType($entity['entity_id']),
            'ha_entity_id' => $entity['entity_id'],
            'last_seen'    => $entity['last_changed'] ?? null,
            'raw_data'     => json_encode($entity),
        ];
    }

    private function normalizeZhaDevice(array $device): array
    {
        return [
            'ieee'         => $device['ieee_address'] ?? $device['ieee'] ?? '',
            'nwk'          => $device['nwk_address']  ?? '',
            'name'         => $device['name']          ?? $device['user_given_name'] ?? '',
            'model'        => $device['model_id']      ?? $device['model'] ?? '',
            'manufacturer' => $device['manufacturer']  ?? '',
            'device_type'  => $device['device_type']   ?? 'unknown',
            'ha_entity_id' => $device['entity_id']     ?? '',
            'last_seen'    => $device['last_seen']      ?? null,
            'raw_data'     => json_encode($device),
        ];
    }

    private function upsertZigbeeDevice(array $device, array $raw): void
    {
        if (empty($device['ieee'])) return;

        db()->execute(
            'INSERT INTO zigbee_devices (ieee, nwk, name, model, manufacturer, device_type, ha_entity_id, last_seen, raw_data)
             VALUES (:ieee, :nwk, :name, :model, :manufacturer, :device_type, :ha_entity_id, :last_seen, :raw_data)
             ON CONFLICT(ieee) DO UPDATE SET
                nwk          = excluded.nwk,
                name         = excluded.name,
                model        = excluded.model,
                manufacturer = excluded.manufacturer,
                device_type  = excluded.device_type,
                ha_entity_id = excluded.ha_entity_id,
                last_seen    = excluded.last_seen,
                raw_data     = excluded.raw_data',
            $device
        );
    }

    private function inferDeviceType(string $entityId): string
    {
        return match (true) {
            str_starts_with($entityId, 'light.')   => 'light',
            str_starts_with($entityId, 'switch.')  => 'switch',
            str_starts_with($entityId, 'climate.') => 'climate',
            str_starts_with($entityId, 'sensor.')  => 'sensor',
            str_starts_with($entityId, 'binary_sensor.') => 'binary_sensor',
            str_starts_with($entityId, 'cover.')   => 'cover',
            str_starts_with($entityId, 'lock.')    => 'lock',
            default                                 => 'unknown',
        };
    }

    private function mapDeviceType(string $zhaType, string $model): string
    {
        return match (strtolower($zhaType)) {
            'light', 'color_light', 'dimmable_light' => 'light',
            'switch', 'outlet'                        => 'switch',
            'temperature', 'humidity', 'sensor'       => 'sensor',
            'thermostat', 'climate'                   => 'thermostat',
            'cover', 'shutter'                        => 'switch',
            default                                   => 'switch',
        };
    }

    private function looksLikeZigbee(array $entity): bool
    {
        $attr = $entity['attributes'] ?? [];
        return isset($attr['linkquality'])
            || isset($attr['lqi'])
            || str_contains(($attr['platform'] ?? ''), 'zigbee')
            || str_contains(($entity['entity_id'] ?? ''), 'zigbee');
    }

    private function getIconForType(string $type): string
    {
        return match ($type) {
            'light'      => 'fa-lightbulb',
            'switch'     => 'fa-toggle-on',
            'thermostat' => 'fa-thermometer-half',
            'sensor'     => 'fa-chart-line',
            'camera'     => 'fa-video',
            default      => 'fa-plug',
        };
    }

    private function getColorForType(string $type): string
    {
        return match ($type) {
            'light'      => 'from-yellow-400 to-orange-500',
            'switch'     => 'from-blue-500 to-indigo-600',
            'thermostat' => 'from-orange-400 to-red-500',
            'sensor'     => 'from-green-400 to-teal-500',
            'camera'     => 'from-red-500 to-pink-600',
            default      => 'from-indigo-500 to-purple-600',
        };
    }

    private function makeSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}

// Factory
function zigbee(): ZigbeeManager
{
    static $manager = null;
    if ($manager === null) {
        $manager = new ZigbeeManager();
    }
    return $manager;
}
