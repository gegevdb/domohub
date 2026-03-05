<?php
/**
 * DomoGlass Pro - Initialisation et migrations SQLite
 */

require_once __DIR__ . '/config.php';

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');
        $this->pdo->exec('PRAGMA synchronous=NORMAL');

        $this->migrate();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // ─── Migrations ───────────────────────────────────────────────────────────

    private function migrate(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_versions (
                version     INTEGER PRIMARY KEY,
                applied_at  TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $currentVersion = (int)$this->pdo
            ->query('SELECT COALESCE(MAX(version), 0) FROM schema_versions')
            ->fetchColumn();

        $migrations = $this->getMigrations();

        foreach ($migrations as $version => $sql) {
            if ($version > $currentVersion) {
                $this->pdo->beginTransaction();
                try {
                    $this->pdo->exec($sql);
                    $this->pdo->prepare('INSERT INTO schema_versions (version) VALUES (?)')
                              ->execute([$version]);
                    $this->pdo->commit();
                } catch (Exception $e) {
                    $this->pdo->rollBack();
                    throw new RuntimeException("Migration $version échouée : " . $e->getMessage());
                }
            }
        }
    }

    private function getMigrations(): array
    {
        return [

            // ── v1 : Tables de base ──────────────────────────────────────────
            1 => "
                -- Pièces / zones
                CREATE TABLE IF NOT EXISTS rooms (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    name        TEXT NOT NULL,
                    icon        TEXT NOT NULL DEFAULT 'fa-home',
                    floor       INTEGER NOT NULL DEFAULT 0,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
                );

                INSERT OR IGNORE INTO rooms (id, name, icon, floor) VALUES
                    (1, 'Salon',    'fa-couch',       0),
                    (2, 'Cuisine',  'fa-utensils',    0),
                    (3, 'Chambre',  'fa-bed',         1),
                    (4, 'Salle de bain', 'fa-bath',   1),
                    (5, 'Extérieur','fa-tree',        0);

                -- Devices (appareils)
                CREATE TABLE IF NOT EXISTS devices (
                    id              INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id         INTEGER REFERENCES rooms(id) ON DELETE SET NULL,
                    name            TEXT NOT NULL,
                    type            TEXT NOT NULL,         -- light|switch|thermostat|sensor|camera|cover|media
                    protocol        TEXT NOT NULL DEFAULT 'mqtt', -- mqtt|zigbee|wifi|virtual
                    ieee_address    TEXT UNIQUE,           -- Adresse IEEE Zigbee (ZHA)
                    entity_id       TEXT UNIQUE,           -- entity_id Home Assistant
                    mqtt_topic      TEXT,                  -- Topic MQTT d'état
                    mqtt_cmd_topic  TEXT,                  -- Topic MQTT de commande
                    icon            TEXT DEFAULT 'fa-microchip',
                    state           TEXT NOT NULL DEFAULT 'off',  -- JSON ou valeur simple
                    attributes      TEXT NOT NULL DEFAULT '{}',   -- JSON (brightness, color_temp…)
                    is_online       INTEGER NOT NULL DEFAULT 0,
                    is_favourite    INTEGER NOT NULL DEFAULT 0,
                    last_seen       TEXT,
                    created_at      TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at      TEXT NOT NULL DEFAULT (datetime('now'))
                );

                -- Index utiles
                CREATE INDEX IF NOT EXISTS idx_devices_room    ON devices(room_id);
                CREATE INDEX IF NOT EXISTS idx_devices_type    ON devices(type);
                CREATE INDEX IF NOT EXISTS idx_devices_protocol ON devices(protocol);

                -- Historique des actions
                CREATE TABLE IF NOT EXISTS actions (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_id   INTEGER REFERENCES devices(id) ON DELETE CASCADE,
                    user        TEXT NOT NULL DEFAULT 'system',
                    action      TEXT NOT NULL,             -- turn_on|turn_off|set_brightness…
                    payload     TEXT NOT NULL DEFAULT '{}',-- JSON du payload envoyé
                    source      TEXT NOT NULL DEFAULT 'ui',-- ui|automation|mqtt|api
                    status      TEXT NOT NULL DEFAULT 'success', -- success|error|pending
                    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
                );

                CREATE INDEX IF NOT EXISTS idx_actions_device ON actions(device_id);
                CREATE INDEX IF NOT EXISTS idx_actions_date   ON actions(created_at);
            ",

            // ── v2 : Automations ─────────────────────────────────────────────
            2 => "
                CREATE TABLE IF NOT EXISTS automations (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    name        TEXT NOT NULL,
                    description TEXT,
                    trigger_type TEXT NOT NULL,            -- time|state|mqtt|sunrise|sunset
                    trigger_data TEXT NOT NULL DEFAULT '{}',
                    conditions  TEXT NOT NULL DEFAULT '[]',
                    actions_data TEXT NOT NULL DEFAULT '[]',
                    is_enabled  INTEGER NOT NULL DEFAULT 1,
                    last_run    TEXT,
                    run_count   INTEGER NOT NULL DEFAULT 0,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
                );

                -- Exemples d'automations
                INSERT OR IGNORE INTO automations (name, trigger_type, trigger_data, actions_data) VALUES
                (
                    'Extinction nocturne',
                    'time',
                    '{\"time\": \"23:00\"}',
                    '[{\"action\": \"turn_off\", \"target\": \"all_lights\"}]'
                ),
                (
                    'Réveil progressif',
                    'time',
                    '{\"time\": \"07:00\", \"days\": [1,2,3,4,5]}',
                    '[{\"action\": \"set_brightness\", \"target\": \"chambre_light\", \"value\": 10},
                      {\"action\": \"set_brightness\", \"target\": \"chambre_light\", \"value\": 100, \"delay\": 1800}]'
                );
            ",

            // ── v3 : Énergie et capteurs ─────────────────────────────────────
            3 => "
                CREATE TABLE IF NOT EXISTS energy_logs (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_id   INTEGER REFERENCES devices(id) ON DELETE CASCADE,
                    power_w     REAL NOT NULL DEFAULT 0,
                    energy_kwh  REAL NOT NULL DEFAULT 0,
                    voltage_v   REAL,
                    current_a   REAL,
                    logged_at   TEXT NOT NULL DEFAULT (datetime('now'))
                );

                CREATE INDEX IF NOT EXISTS idx_energy_device ON energy_logs(device_id);
                CREATE INDEX IF NOT EXISTS idx_energy_date   ON energy_logs(logged_at);

                CREATE TABLE IF NOT EXISTS sensor_logs (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    device_id   INTEGER REFERENCES devices(id) ON DELETE CASCADE,
                    metric      TEXT NOT NULL,             -- temperature|humidity|co2|motion…
                    value       REAL NOT NULL,
                    unit        TEXT NOT NULL DEFAULT '',
                    logged_at   TEXT NOT NULL DEFAULT (datetime('now'))
                );

                CREATE INDEX IF NOT EXISTS idx_sensor_device ON sensor_logs(device_id);
                CREATE INDEX IF NOT EXISTS idx_sensor_metric ON sensor_logs(metric);
            ",

            // ── v4 : Scènes ──────────────────────────────────────────────────
            4 => "
                CREATE TABLE IF NOT EXISTS scenes (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    name        TEXT NOT NULL,
                    icon        TEXT DEFAULT 'fa-magic',
                    color       TEXT DEFAULT '#6366f1',
                    actions_data TEXT NOT NULL DEFAULT '[]',
                    is_favourite INTEGER NOT NULL DEFAULT 0,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
                );

                INSERT OR IGNORE INTO scenes (name, icon, color, actions_data) VALUES
                ('Cinéma',   'fa-film',        '#7c3aed', '[{\"action\":\"dim\",\"target\":\"salon_lights\",\"value\":20}]'),
                ('Soirée',   'fa-glass-cheers','#db2777', '[{\"action\":\"color\",\"target\":\"salon_lights\",\"color\":\"#ff6b6b\"}]'),
                ('Nuit',     'fa-moon',        '#1e40af', '[{\"action\":\"turn_off\",\"target\":\"all_lights\"}]'),
                ('Matin',    'fa-sun',         '#d97706', '[{\"action\":\"turn_on\",\"target\":\"all_lights\",\"brightness\":80}]');
            ",

            // ── v5 : Système de notifications ────────────────────────────────
            5 => "
                CREATE TABLE IF NOT EXISTS notifications (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    title       TEXT NOT NULL,
                    message     TEXT NOT NULL,
                    type        TEXT NOT NULL DEFAULT 'info',  -- info|success|warning|error
                    source      TEXT NOT NULL DEFAULT 'system',
                    is_read     INTEGER NOT NULL DEFAULT 0,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
                );

                CREATE INDEX IF NOT EXISTS idx_notif_read ON notifications(is_read);
            ",
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO $table ($cols) VALUES ($placeholders)", array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $stmt = $this->query(
            "UPDATE $table SET $set, updated_at = datetime('now') WHERE $where",
            [...array_values($data), ...$whereParams]
        );
        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query("DELETE FROM $table WHERE $where", $params)->rowCount();
    }
}
