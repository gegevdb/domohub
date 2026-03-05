<?php
/**
 * DOMOGLASS PRO — Couche base de données SQLite
 * Singleton PDO avec helpers CRUD et initialisation du schéma.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0750, true);
        }

        $this->pdo = new PDO('sqlite:' . DB_PATH, options: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');

        $this->initSchema();
        $this->runMigrations();
    }

    // --------------------------------------------------------
    //  Singleton
    // --------------------------------------------------------
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // --------------------------------------------------------
    //  Initialisation du schéma (idempotent)
    // --------------------------------------------------------
    private function initSchema(): void
    {
        $schemaPath = BASE_PATH . '/sql/schema.sql';
        if (!file_exists($schemaPath)) {
            throw new RuntimeException("Schéma SQL introuvable : $schemaPath");
        }

        // Exécuter seulement si les tables n'existent pas encore
        $exists = $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='devices'")->fetchColumn();
        if (!$exists) {
            $sql = file_get_contents($schemaPath);
            $this->pdo->exec($sql);
        }
    }

    // --------------------------------------------------------
    //  Migrations simples (pour DB existantes)
    // --------------------------------------------------------
    private function runMigrations(): void
    {
        // Users table (auth)
        $exists = (int)$this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if (!$exists) {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS users (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    username      TEXT    NOT NULL UNIQUE,
                    password_hash TEXT    NOT NULL,
                    role          TEXT    NOT NULL DEFAULT 'user',
                    enabled       INTEGER NOT NULL DEFAULT 1,
                    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
                );"
            );

            $this->pdo->exec(
                "CREATE TRIGGER IF NOT EXISTS users_updated
                    AFTER UPDATE ON users
                    FOR EACH ROW
                    BEGIN
                        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
                    END;"
            );
        }
    }

    // --------------------------------------------------------
    //  Helpers génériques
    // --------------------------------------------------------

    /**
     * Retourne toutes les lignes d'une requête SELECT.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retourne une seule ligne.
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Exécute INSERT/UPDATE/DELETE, retourne le nombre de lignes affectées.
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * INSERT et retourne le dernier ID inséré.
     */
    public function insert(string $sql, array $params = []): int|string
    {
        $this->execute($sql, $params);
        return $this->pdo->lastInsertId();
    }

    // --------------------------------------------------------
    //  Helpers métier
    // --------------------------------------------------------

    /**
     * Lire une valeur de config.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $row = $this->fetchOne('SELECT value FROM config WHERE key = ?', [$key]);
        return $row ? $row['value'] : $default;
    }

    /**
     * Écrire une valeur de config.
     */
    public function setConfig(string $key, mixed $value): void
    {
        $this->execute(
            'INSERT INTO config (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP',
            [$key, (string)$value]
        );
    }

    /**
     * Enregistrer une action dans l'historique.
     */
    public function logAction(
        ?int   $deviceId,
        string $deviceName,
        string $actionType,
        mixed  $payload = null,
        string $source = 'ui',
        string $status = 'sent'
    ): int|string {
        return $this->insert(
            'INSERT INTO actions (device_id, device_name, action_type, payload, source, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $deviceId,
                $deviceName,
                $actionType,
                $payload ? json_encode($payload) : null,
                $source,
                $status,
            ]
        );
    }

    /**
     * Enregistrer une valeur capteur.
     */
    public function logSensor(int $deviceId, string $metric, float $value, string $unit = ''): void
    {
        $this->execute(
            'INSERT INTO sensor_history (device_id, metric, value, unit) VALUES (?, ?, ?, ?)',
            [$deviceId, $metric, $value, $unit]
        );
    }

    /**
     * Mettre à jour l'état courant d'un device.
     */
    public function updateDeviceState(int $deviceId, string $state, mixed $stateData = null): void
    {
        $this->execute(
            'UPDATE devices SET state = ?, state_data = ?, last_seen = CURRENT_TIMESTAMP WHERE id = ?',
            [$state, $stateData ? json_encode($stateData) : null, $deviceId]
        );
    }

    /**
     * Ajouter une notification.
     */
    public function addNotification(string $type, string $title, string $message = '', ?string $source = null): void
    {
        $this->execute(
            'INSERT INTO notifications (type, title, message, source) VALUES (?, ?, ?, ?)',
            [$type, $title, $message, $source]
        );
    }

    /**
     * Récupérer l'historique énergie pour les N dernières heures.
     */
    public function getEnergyHistory(int $deviceId, int $hours = 24): array
    {
        return $this->fetchAll(
            "SELECT metric, value, unit, recorded_at
             FROM sensor_history
             WHERE device_id = ? AND metric IN ('power','energy')
               AND recorded_at >= DATETIME('now', ? || ' hours')
             ORDER BY recorded_at ASC",
            [$deviceId, "-$hours"]
        );
    }
}

// Alias global pour simplicité dans les includes
function db(): Database
{
    return Database::getInstance();
}
