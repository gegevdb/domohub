<?php
/**
 * DomoGlass Pro - Service MQTT (Mosquitto)
 *
 * Dépendance : php-mqtt/client (via Composer)
 * Installation : composer require php-mqtt/client
 *
 * Usage :
 *   $mqtt = MqttService::getInstance();
 *   $mqtt->publish('domoglass/cmd/light1', json_encode(['state' => 'ON']));
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;

class MqttService
{
    private static ?MqttService $instance = null;
    private ?MqttClient $client = null;
    private bool $connected = false;
    private array $subscriptions = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ─── Connexion ────────────────────────────────────────────────────────────

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        try {
            $settings = (new ConnectionSettings())
                ->setKeepAliveInterval(60)
                ->setConnectTimeout(10)
                ->setUseTls(false)
                ->setReconnectAutomatically(true)
                ->setMaxReconnectAttempts(5)
                ->setDelayBetweenReconnectAttempts(2000);

            if (MQTT_USERNAME !== '') {
                $settings = $settings
                    ->setUsername(MQTT_USERNAME)
                    ->setPassword(MQTT_PASSWORD);
            }

            // Last Will & Testament : signale la déconnexion du serveur
            $settings = $settings->setLastWillTopic('domoglass/status')
                                  ->setLastWillMessage(json_encode(['status' => 'offline', 'ts' => time()]))
                                  ->setLastWillQualityOfService(1)
                                  ->setRetainLastWill(true);

            $this->client = new MqttClient(MQTT_HOST, MQTT_PORT, MQTT_CLIENT_ID);
            $this->client->connect($settings, true);
            $this->connected = true;

            // Annonce la présence du serveur
            $this->publish('domoglass/status', json_encode([
                'status' => 'online',
                'version' => DOMOGLASS_VERSION,
                'ts' => time()
            ]), retain: true);

            $this->log('info', 'Connecté au broker MQTT ' . MQTT_HOST . ':' . MQTT_PORT);
            return true;

        } catch (MqttClientException $e) {
            $this->log('error', 'Connexion MQTT échouée : ' . $e->getMessage());
            return false;
        }
    }

    public function disconnect(): void
    {
        if ($this->connected && $this->client) {
            $this->client->disconnect();
            $this->connected = false;
        }
    }

    // ─── Publish ──────────────────────────────────────────────────────────────

    /**
     * Envoie un message sur un topic MQTT
     *
     * @param string $topic    Topic cible
     * @param string $payload  Message (généralement JSON)
     * @param int    $qos      Quality of Service (0, 1 ou 2)
     * @param bool   $retain   Message retained par le broker
     */
    public function publish(
        string $topic,
        string $payload,
        int $qos = 0,
        bool $retain = false
    ): bool {
        try {
            if (!$this->connected) {
                $this->connect();
            }

            $this->client->publish($topic, $payload, $qos, $retain);

            $this->log('debug', "PUBLISH → $topic : $payload");
            return true;

        } catch (MqttClientException $e) {
            $this->log('error', "PUBLISH échoué sur $topic : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Commande un device via son topic de commande
     */
    public function commandDevice(int $deviceId, array $payload): bool
    {
        $db = Database::getInstance();
        $device = $db->fetchOne(
            'SELECT mqtt_topic_set, name FROM devices WHERE id = ?',
            [$deviceId]
        );

        if (!$device || !$device['mqtt_topic_set']) {
            $this->log('warning', "Device $deviceId sans topic de commande MQTT");
            return false;
        }

        $jsonPayload = json_encode($payload);
        $success = $this->publish($device['mqtt_topic_set'], $jsonPayload, qos: 1);

        // Log en base
        $db->insert('actions', [
            'device_id' => $deviceId,
            'action'    => $payload['action'] ?? 'command',
            'payload'   => $jsonPayload,
            'source'    => 'mqtt',
            'status'    => $success ? 'success' : 'error',
        ]);

        return $success;
    }

    // ─── Subscribe ────────────────────────────────────────────────────────────

    /**
     * S'abonne à un topic et exécute le callback à la réception
     * Callback signature : function(string $topic, string $payload): void
     */
    public function subscribe(string $topicPattern, callable $callback, int $qos = 0): void
    {
        if (!$this->connected) {
            $this->connect();
        }

        $this->client->subscribe($topicPattern, function (string $topic, string $payload) use ($callback) {
            $this->log('debug', "RECEIVE ← $topic : $payload");
            try {
                $callback($topic, $payload);
            } catch (Throwable $e) {
                $this->log('error', "Erreur callback subscribe $topic : " . $e->getMessage());
            }
        }, $qos);

        $this->subscriptions[] = $topicPattern;
    }

    /**
     * Démarre la boucle d'écoute MQTT (pour les workers/daemons)
     * À appeler dans un script CLI séparé (workers/mqtt_worker.php)
     */
    public function loop(int $maxSeconds = 0): void
    {
        if (!$this->connected) {
            $this->connect();
        }

        $this->log('info', 'Démarrage de la boucle MQTT...');
        $this->client->loop(true, $maxSeconds > 0, $maxSeconds);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Lit le dernier message retenu sur un topic (PUBLISH + subscribe immédiat)
     * Utile pour récupérer l'état initial d'un device
     */
    public function getRetained(string $topic, float $timeoutSeconds = 2.0): ?string
    {
        $result = null;

        if (!$this->connected) {
            $this->connect();
        }

        $this->client->subscribe($topic, function (string $t, string $payload) use (&$result) {
            $result = $payload;
        }, 0);

        $start = microtime(true);
        while ($result === null && (microtime(true) - $start) < $timeoutSeconds) {
            $this->client->loop(true, true, 100);
        }

        $this->client->unsubscribe($topic);
        return $result;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    // ─── Logging ──────────────────────────────────────────────────────────────

    private function log(string $level, string $message): void
    {
        $logPath = __DIR__ . '/../logs/app.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }

        $line = sprintf("[%s] [MQTT] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);

        if (DOMOGLASS_ENV === 'development') {
            echo $line;
        }
    }
}
