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
    authApiRequireAdmin();
    
    $action = $_GET['action'] ?? '';
    
    if ($action === 'detect') {
        // Détection des périphériques USB
        $usbDevices = [];
        
        // Lister les périphériques USB
        $usbCmd = 'lsusb 2>/dev/null';
        $usbOutput = shell_exec($usbCmd);
        
        if ($usbOutput) {
            $lines = explode("\n", trim($usbOutput));
            foreach ($lines as $line) {
                if (preg_match('/Bus (\d+) Device (\d+): ID ([a-f0-9]{4}):([a-f0-9]{4}) (.+)/', $line, $matches)) {
                    $usbDevices[] = [
                        'bus' => $matches[1],
                        'device' => $matches[2],
                        'vendor_id' => $matches[3],
                        'product_id' => $matches[4],
                        'description' => trim($matches[5]),
                        'type' => detectDeviceType($matches[3], $matches[4])
                    ];
                }
            }
        }
        
        // Détecter les ports série disponibles
        $serialPorts = [];
        $serialCmd = 'find /dev -name "ttyUSB*" -o -name "ttyACM*" 2>/dev/null';
        $serialOutput = shell_exec($serialCmd);
        
        if ($serialOutput) {
            $ports = explode("\n", trim($serialOutput));
            foreach ($ports as $port) {
                $port = trim($port);
                if ($port) {
                    $serialPorts[] = [
                        'port' => $port,
                        'description' => getSerialPortDescription($port)
                    ];
                }
            }
        }
        
        apiSuccess([
            'usb_devices' => $usbDevices,
            'serial_ports' => $serialPorts,
            'detected_skyconnect' => detectSkyConnect($usbDevices)
        ]);
    }
    
    if ($action === 'skyconnect_status') {
        // Vérifier le statut spécifique du SkyConnect
        $skyconnectConfig = json_decode(db()->getConfig('skyconnect_config', '{}'), true) ?: [];
        $serialPort = $skyconnectConfig['serial_port'] ?? '';
        
        $status = [
            'configured' => !empty($skyconnectConfig),
            'enabled' => $skyconnectConfig['enabled'] ?? false,
            'serial_port' => $serialPort,
            'port_exists' => $serialPort && file_exists($serialPort),
            'port_accessible' => false,
            'zigbee2mqtt_running' => false
        ];
        
        if ($status['port_exists']) {
            // Vérifier si le port est accessible
            $status['port_accessible'] = is_readable($serialPort) && is_writable($serialPort);
            
            // Vérifier si Zigbee2MQTT est en cours d'exécution
            $z2mCmd = 'systemctl is-active zigbee2mqtt 2>/dev/null || echo "inactive"';
            $z2mStatus = trim(shell_exec($z2mCmd));
            $status['zigbee2mqtt_running'] = $z2mStatus === 'active';
        }
        
        apiSuccess($status);
    }
    
    apiError('Action non valide', 400);
}

function detectDeviceType(string $vendorId, string $productId): string {
    $skyConnectIds = [
        '10c4:ea60' => 'skyconnect',  // Silicon Labs CP210x (SkyConnect)
        '1cf1:0030' => 'conbee',      // Dresden Elektronik ConBee II
        '0403:6015' => 'conbee',      // ConBee III
    ];
    
    $id = $vendorId . ':' . $productId;
    return $skyConnectIds[$id] ?? 'unknown';
}

function detectSkyConnect(array $usbDevices): array {
    foreach ($usbDevices as $device) {
        if ($device['type'] === 'skyconnect') {
            return $device;
        }
    }
    return [];
}

function getSerialPortDescription(string $port): string {
    $udevCmd = "udevadm info --name=$port 2>/dev/null | grep 'ID_SERIAL_SHORT=' | cut -d'=' -f2";
    $serial = trim(shell_exec($udevCmd));
    
    if ($serial) {
        return "Serial: $serial";
    }
    
    // Essayer de déterminer le type par le nom
    if (strpos($port, 'ttyUSB') !== false) {
        return 'USB Serial Converter';
    } elseif (strpos($port, 'ttyACM') !== false) {
        return 'USB CDC ACM';
    }
    
    return 'Serial Port';
}
