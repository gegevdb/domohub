<?php
/**
 * DomoGlass Pro - Service de gestion des devices
 * Abstraction unifiée MQTT + Zigbee
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/MqttService.php';
require_once __DIR__ . '/ZigbeeService.php';

class DeviceService
{
    private static ?DeviceService $instance = null;
    private Database $db;

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

    // ─── Lecture ──────────────────────────────────────────────────────────────

    public function getAllDevices(?int $roomId = null): array
    {
        $sql = '
            SELECT d.*, r.name AS room_name, r.icon AS room_icon
            FROM devices d
            LEFT JOIN rooms r ON r.id = d.room_id
        ';
        $params = [];

        if ($roomId !== null) {
            $sql .= ' WHERE d.room_id = ?';
            $params[] = $roomId;
        }

        $sql .= ' ORDER BY r.floor, r.name, d.name';

        $devices = $this->db->fetchAll($sql, $params);

        return array_map(function ($d) {
            $d['attributes'] = json_decode($d['attributes'] ?? '{}', true);
            return $d;
        }, $devices);
    }

    public function getDevice(int $id): array|false
    {
        $d = $this->db->fetchOne('
            SELECT d.*, r.name AS room_name
            FROM devices d
            LEFT JOIN rooms r ON r.id = d.room_id
            WHERE d.id = ?
        ', [$id]);

        if ($d) {
            $d['attributes'] = json_decode($d['attributes'] ?? '{}', true);
        }
        return $d;
    }

    public function getDevicesByType(string $type): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM devices WHERE type = ? ORDER BY name',
            [$type]
        );
    }

    public function getRooms(): array
    {
        return $this->db->fetchAll('SELECT * FROM rooms ORDER BY floor, name');
    }

    // ─── Commandes ────────────────────────────────────────────────────────────

    /**
     * Commande unifiée : route automatiquement vers MQTT ou Zigbee/HA
     */
    public function command(int $deviceId, string $action, array $params = []): array
    {
        $device = $this->getDevice($deviceId);
        if (!$device) {
            return ['success' => false, 'error' => 'Device introuvable'];
        }

        $success = match($device['protocol']) {
            'zigbee' => $this->commandZigbee($device, $action, $params),
            'mqtt'   => $this->commandMqtt($device, $action, $params),
            'wifi'   => $this->commandHaRest($device, $action, $params),
            default  => false,
        };

        // Met à jour l'état local en base
        if ($success) {
            $this->updateLocalState($deviceId, $action, $params);
        }

        return ['success' => $success, 'device' => $device['name'], 'action' => $action];
    }

    private function commandMqtt(array $device, string $action, array $params): bool
    {
        if (!$device['mqtt_cmd_topic']) {
            return false;
        }

        $payload = $this->buildMqttPayload($action, $params);
        return MqttService::getInstance()->publish(
            $device['mqtt_cmd_topic'],
            json_encode($payload),
            qos: 1
        );
    }

    private function commandZigbee(array $device, string $action, array $params): bool
    {
        if (!$device['entity_id']) {
            return false;
        }

        [$domain] = explode('.', $device['entity_id']);
        $haAction = $this->mapActionToHaService($action, $domain);
        $serviceData = ['entity_id' => $device['entity_id'], ...$params];

        return ZigbeeService::getInstance()->callService($domain, $haAction, $serviceData);
    }

    private function commandHaRest(array $device, string $action, array $params): bool
    {
        return $this->commandZigbee($device, $action, $params);
    }

    // ─── Mise à jour d'état ───────────────────────────────────────────────────

    /**
     * Met à jour l'état d'un device depuis une réception MQTT ou HA
     */
    public function updateState(int $deviceId, array $stateData): bool
    {
        $state      = $stateData['state']  ?? $stateData['value'] ?? null;
        $attributes = $stateData;
        unset($attributes['state'], $attributes['value']);

        $updateData = ['last_seen' => date('Y-m-d H:i:s')];

        if ($state !== null) {
            $updateData['state'] = is_array($state) ? json_encode($state) : (string)$state;
        }

        if (!empty($attributes)) {
            $existing = $this->db->fetchOne('SELECT attributes FROM devices WHERE id = ?', [$deviceId]);
            $current  = json_decode($existing['attributes'] ?? '{}', true);
            $merged   = array_merge($current, $attributes);
            $updateData['attributes'] = json_encode($merged);
        }

        $updateData['is_online'] = 1;

        return $this->db->update('devices', $updateData, 'id = ?', [$deviceId]) > 0;
    }

    private function updateLocalState(int $deviceId, string $action, array $params): void
    {
        $stateMap = [
            'turn_on'  => 'on',
            'turn_off' => 'off',
            'toggle'   => null, // calculé ci-dessous
        ];

        if (array_key_exists($action, $stateMap)) {
            $newState = $stateMap[$action];

            if ($newState === null) {
                $current  = $this->db->fetchColumn('SELECT state FROM devices WHERE id = ?', [$deviceId]);
                $newState = ($current === 'on') ? 'off' : 'on';
            }

            $data = ['state' => $newState];
            if (isset($params['brightness'])) {
                $device = $this->db->fetchOne('SELECT attributes FROM devices WHERE id = ?', [$deviceId]);
                $attrs  = json_decode($device['attributes'] ?? '{}', true);
                $attrs['brightness'] = $params['brightness'];
                $data['attributes']  = json_encode($attrs);
            }

            $this->db->update('devices', $data, 'id = ?', [$deviceId]);
        }
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function createDevice(array $data): int
    {
        return $this->db->insert('devices', [
            'room_id'        => $data['room_id']        ?? null,
            'name'           => $data['name'],
            'type'           => $data['type'],
            'protocol'       => $data['protocol']       ?? 'mqtt',
            'ieee_address'   => $data['ieee_address']   ?? null,
            'entity_id'      => $data['entity_id']      ?? null,
            'mqtt_topic'     => $data['mqtt_topic']      ?? null,
            'mqtt_cmd_topic' => $data['mqtt_cmd_topic']  ?? null,
            'icon'           => $data['icon']            ?? 'fa-microchip',
            'attributes'     => json_encode($data['attributes'] ?? []),
        ]);
    }

    public function updateDevice(int $id, array $data): bool
    {
        if (isset($data['attributes']) && is_array($data['attributes'])) {
            $data['attributes'] = json_encode($data['attributes']);
        }
        return $this->db->update('devices', $data, 'id = ?', [$id]) > 0;
    }

    public function deleteDevice(int $id): bool
    {
        return $this->db->delete('devices', 'id = ?', [$id]) > 0;
    }

    // ─── Historique ───────────────────────────────────────────────────────────

    public function getActionHistory(int $deviceId, int $limit = 50): array
    {
        return $this->db->fetchAll('
            SELECT * FROM actions
            WHERE device_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ', [$deviceId, $limit]);
    }

    public function getRecentActions(int $limit = 100): array
    {
        return $this->db->fetchAll('
            SELECT a.*, d.name AS device_name, d.type AS device_type
            FROM actions a
            LEFT JOIN devices d ON d.id = a.device_id
            ORDER BY a.created_at DESC
            LIMIT ?
        ', [$limit]);
    }

    // ─── Helpers privés ───────────────────────────────────────────────────────

    private function buildMqttPayload(string $action, array $params): array
    {
        $payload = match($action) {
            'turn_on'  => ['state' => 'ON', ...$params],
            'turn_off' => ['state' => 'OFF'],
            'toggle'   => ['state' => 'TOGGLE'],
            default    => [$action => $params['value'] ?? true, ...$params],
        };

        if (isset($params['brightness'])) {
            $payload['brightness'] = (int)$params['brightness'];
        }
        if (isset($params['color_temp'])) {
            $payload['color_temp'] = (int)$params['color_temp'];
        }
        if (isset($params['color'])) {
            $payload['color'] = $params['color'];
        }

        return $payload;
    }

    private function mapActionToHaService(string $action, string $domain): string
    {
        return match($action) {
            'turn_on'  => 'turn_on',
            'turn_off' => 'turn_off',
            'toggle'   => 'toggle',
            'set_brightness' => 'turn_on',
            'set_temperature' => 'set_temperature',
            'open'  => 'open_cover',
            'close' => 'close_cover',
            'stop'  => 'stop_cover',
            default => $action,
        };
    }
}
