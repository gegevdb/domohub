<?php
/**
 * DOMOGLASS PRO — Client MQTT PHP (Mosquitto TCP)
 *
 * Utilisé côté serveur pour publier des commandes.
 * La souscription temps réel est gérée côté JS via WebSocket (Paho).
 *
 * Protocole implémenté : MQTT 3.1.1 minimal (CONNECT + PUBLISH + DISCONNECT)
 * sans dépendance externe — pure socket PHP.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

class MqttClient
{
    private ?Socket $socket = null;
    private bool    $connected = false;
    private string  $clientId;

    public function __construct(
        private readonly string $host     = MQTT_HOST,
        private readonly int    $port     = MQTT_PORT,
        private readonly string $username = MQTT_USER,
        private readonly string $password = MQTT_PASSWORD,
        private readonly int    $timeout  = MQTT_TIMEOUT,
    ) {
        $this->clientId = MQTT_CLIENT_ID . '-' . substr(uniqid(), -6);
    }

    // --------------------------------------------------------
    //  Connexion au broker
    // --------------------------------------------------------
    public function connect(): bool
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            $this->logError('Impossible de créer le socket TCP');
            return false;
        }

        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        if (!socket_connect($this->socket, $this->host, $this->port)) {
            $this->logError("Connexion refusée sur {$this->host}:{$this->port}");
            return false;
        }

        $packet = $this->buildConnectPacket();
        if (socket_write($this->socket, $packet, strlen($packet)) === false) {
            $this->logError('Échec envoi CONNECT');
            return false;
        }

        // Lire CONNACK (4 octets)
        $connack = socket_read($this->socket, 4);
        if ($connack === false || strlen($connack) < 4) {
            $this->logError('Pas de CONNACK reçu');
            return false;
        }

        $returnCode = ord($connack[3]);
        if ($returnCode !== 0x00) {
            $codes = [
                0x01 => 'Version de protocole refusée',
                0x02 => 'Identifiant client refusé',
                0x03 => 'Serveur indisponible',
                0x04 => 'Identifiants incorrects',
                0x05 => 'Non autorisé',
            ];
            $this->logError('CONNACK erreur: ' . ($codes[$returnCode] ?? "Code $returnCode"));
            return false;
        }

        $this->connected = true;
        return true;
    }

    // --------------------------------------------------------
    //  Publication d'un message
    // --------------------------------------------------------
    public function publish(string $topic, string|array $payload, int $qos = 0, bool $retain = false): bool
    {
        if (!$this->connected) {
            if (!$this->connect()) {
                return false;
            }
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $packet = $this->buildPublishPacket($topic, $payload, $qos, $retain);
        $result = socket_write($this->socket, $packet, strlen($packet));

        return $result !== false;
    }

    // --------------------------------------------------------
    //  Déconnexion propre
    // --------------------------------------------------------
    public function disconnect(): void
    {
        if ($this->connected && $this->socket) {
            $packet = "\xe0\x00"; // DISCONNECT
            socket_write($this->socket, $packet, 2);
        }
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
    }

    // --------------------------------------------------------
    //  Envoi d'une commande device (helper haut niveau)
    // --------------------------------------------------------
    public function sendDeviceCommand(array $device, mixed $payload, string $source = 'api'): bool
    {
        $topic = $device['mqtt_topic_set'] ?? null;
        if (!$topic) {
            $this->logError("Device #{$device['id']} n'a pas de mqtt_topic_set");
            return false;
        }

        $published = $this->publish($topic, $payload);

        // Log en base
        db()->logAction(
            deviceId:   (int)$device['id'],
            deviceName: $device['name'],
            actionType: 'command',
            payload:    is_array($payload) ? $payload : ['raw' => $payload],
            source:     $source,
            status:     $published ? 'sent' : 'error'
        );

        return $published;
    }

    // --------------------------------------------------------
    //  Construction des packets MQTT 3.1.1
    // --------------------------------------------------------
    private function buildConnectPacket(): string
    {
        $protocolName    = "\x00\x04MQTT";
        $protocolLevel   = "\x04";                   // MQTT 3.1.1
        $connectFlags    = 0b00000010;               // Clean Session

        $payload = $this->encodeString($this->clientId);

        if ($this->username !== '') {
            $connectFlags |= 0b10000000;
            $payload .= $this->encodeString($this->username);
        }
        if ($this->password !== '') {
            $connectFlags |= 0b01000000;
            $payload .= $this->encodeString($this->password);
        }

        $keepAlive = "\x00\x3c"; // 60 secondes

        $variableHeader = $protocolName . $protocolLevel . chr($connectFlags) . $keepAlive;
        $remainingLength = strlen($variableHeader) + strlen($payload);

        return "\x10" . $this->encodeRemainingLength($remainingLength) . $variableHeader . $payload;
    }

    private function buildPublishPacket(string $topic, string $payload, int $qos, bool $retain): string
    {
        $fixedHeaderByte1 = 0x30;
        if ($retain) $fixedHeaderByte1 |= 0x01;
        if ($qos > 0) $fixedHeaderByte1 |= ($qos << 1);

        $variableHeader = $this->encodeString($topic);
        if ($qos > 0) {
            $variableHeader .= "\x00\x01"; // Packet ID
        }

        $remainingLength = strlen($variableHeader) + strlen($payload);

        return chr($fixedHeaderByte1) . $this->encodeRemainingLength($remainingLength) . $variableHeader . $payload;
    }

    private function encodeString(string $str): string
    {
        $len = strlen($str);
        return chr(($len >> 8) & 0xFF) . chr($len & 0xFF) . $str;
    }

    private function encodeRemainingLength(int $length): string
    {
        $output = '';
        do {
            $byte = $length % 128;
            $length = intdiv($length, 128);
            if ($length > 0) $byte |= 0x80;
            $output .= chr($byte);
        } while ($length > 0);
        return $output;
    }

    private function logError(string $message): void
    {
        error_log("[DomoGlass MQTT] $message");
        if (DOMOGLASS_ENV === 'development') {
            db()->addNotification('error', 'MQTT Erreur', $message, 'mqtt');
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}

// --------------------------------------------------------
//  Factory singleton léger pour réutilisation dans une requête
// --------------------------------------------------------
function mqtt(): MqttClient
{
    static $client = null;
    if ($client === null) {
        $client = new MqttClient();
    }
    return $client;
}
