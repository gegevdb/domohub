-- ============================================================
--  DOMOGLASS PRO — Schéma SQLite
--  Version : 1.0.0
--  Compatible : SQLite 3.35+
-- ============================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ------------------------------------------------------------
--  ROOMS — Pièces / zones de la maison
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    slug        TEXT    NOT NULL UNIQUE,   -- ex: "salon", "chambre-1"
    icon        TEXT    DEFAULT 'fa-home',
    sort_order  INTEGER DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO rooms (name, slug, icon, sort_order) VALUES
    ('Salon',       'salon',       'fa-couch',         1),
    ('Cuisine',     'cuisine',     'fa-utensils',      2),
    ('Chambre',     'chambre',     'fa-bed',           3),
    ('Salle de bain','sdb',        'fa-bath',          4),
    ('Extérieur',   'exterieur',   'fa-tree',          5),
    ('Général',     'general',     'fa-home',          0);

-- ------------------------------------------------------------
--  DEVICES — Appareils domotiques
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS devices (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    slug            TEXT    NOT NULL UNIQUE,
    type            TEXT    NOT NULL,       -- light|switch|thermostat|sensor|camera|media|security
    protocol        TEXT    NOT NULL DEFAULT 'mqtt',  -- mqtt|zigbee|http|local
    room_id         INTEGER REFERENCES rooms(id) ON DELETE SET NULL,

    -- MQTT
    mqtt_topic_state    TEXT,              -- ex: zigbee2mqtt/salon/lumiere
    mqtt_topic_set      TEXT,              -- ex: zigbee2mqtt/salon/lumiere/set
    mqtt_payload_on     TEXT DEFAULT '{"state":"ON"}',
    mqtt_payload_off    TEXT DEFAULT '{"state":"OFF"}',

    -- Zigbee (ZHA via Home Assistant)
    ha_entity_id        TEXT,              -- ex: light.salon_lumiere
    zigbee_ieee         TEXT,              -- adresse IEEE 64-bit
    zigbee_model        TEXT,              -- modèle fabricant

    -- État courant (cache)
    state           TEXT    DEFAULT 'unknown',   -- on|off|unknown
    state_data      TEXT,                        -- JSON complet du dernier état
    last_seen       DATETIME,

    -- Métadonnées
    icon            TEXT    DEFAULT 'fa-plug',
    color           TEXT    DEFAULT 'from-indigo-500 to-purple-600',
    enabled         INTEGER DEFAULT 1,
    sort_order      INTEGER DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Trigger mise à jour updated_at
CREATE TRIGGER IF NOT EXISTS devices_updated
    AFTER UPDATE ON devices
    FOR EACH ROW
    BEGIN
        UPDATE devices SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
    END;

-- ------------------------------------------------------------
--  ACTIONS — Historique de toutes les commandes envoyées
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS actions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id   INTEGER REFERENCES devices(id) ON DELETE SET NULL,
    device_name TEXT,                   -- snapshot du nom au moment de l'action
    action_type TEXT    NOT NULL,       -- toggle|set_value|set_color|scene|automation
    payload     TEXT,                   -- JSON de la commande
    source      TEXT    DEFAULT 'ui',   -- ui|automation|api|mqtt
    status      TEXT    DEFAULT 'sent', -- sent|ack|error
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_actions_device   ON actions(device_id);
CREATE INDEX IF NOT EXISTS idx_actions_created  ON actions(created_at DESC);

-- ------------------------------------------------------------
--  SENSOR_HISTORY — Historique valeurs capteurs (énergie, temp, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sensor_history (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id   INTEGER REFERENCES devices(id) ON DELETE CASCADE,
    metric      TEXT    NOT NULL,       -- temperature|humidity|power|energy|co2
    value       REAL    NOT NULL,
    unit        TEXT,                   -- °C|%|W|kWh|ppm
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_sensor_device    ON sensor_history(device_id);
CREATE INDEX IF NOT EXISTS idx_sensor_recorded  ON sensor_history(recorded_at DESC);
CREATE INDEX IF NOT EXISTS idx_sensor_metric    ON sensor_history(metric);

-- Purge automatique : garder 30 jours
CREATE TRIGGER IF NOT EXISTS sensor_purge
    AFTER INSERT ON sensor_history
    BEGIN
        DELETE FROM sensor_history
        WHERE recorded_at < DATETIME('now', '-30 days');
    END;

-- ------------------------------------------------------------
--  AUTOMATIONS — Règles d'automatisation
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS automations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    name            TEXT    NOT NULL,
    description     TEXT,
    trigger_type    TEXT    NOT NULL,   -- time|device_state|sunrise|sunset|webhook
    trigger_config  TEXT,              -- JSON: {time:"07:00", days:[1,2,3,4,5]}
    conditions      TEXT,              -- JSON array de conditions
    actions         TEXT    NOT NULL,  -- JSON array d'actions à exécuter
    enabled         INTEGER DEFAULT 1,
    last_run        DATETIME,
    run_count       INTEGER DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
--  SCENES — Scènes (groupes d'actions prédéfinies)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scenes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    icon        TEXT    DEFAULT 'fa-magic',
    color       TEXT    DEFAULT 'from-purple-500 to-indigo-600',
    actions     TEXT    NOT NULL,      -- JSON array: [{device_id, payload}]
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO scenes (name, icon, color, actions) VALUES
    ('Bonne nuit',  'fa-moon',     'from-indigo-600 to-purple-800', '[{"type":"all_off"},{"device_type":"thermostat","payload":{"temperature":19}}]'),
    ('Matin',       'fa-sun',      'from-yellow-400 to-orange-500', '[{"type":"all_on","filter":"light"},{"device_type":"thermostat","payload":{"temperature":21}}]'),
    ('Film',        'fa-film',     'from-red-500 to-pink-600',      '[{"type":"dim","filter":"light","value":20}]'),
    ('Absent',      'fa-door-open','from-teal-500 to-cyan-600',     '[{"type":"all_off"},{"type":"security_arm"}]');

-- ------------------------------------------------------------
--  ZIGBEE_DEVICES — Cache appareils Zigbee découverts via ZHA
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS zigbee_devices (
    ieee            TEXT    PRIMARY KEY,   -- adresse IEEE unique
    nwk             TEXT,                  -- adresse réseau courte
    name            TEXT,
    model           TEXT,
    manufacturer    TEXT,
    device_type     TEXT,                  -- light|switch|sensor|coordinator
    ha_entity_id    TEXT,
    paired          INTEGER DEFAULT 0,     -- 1 = appairé dans DomoGlass
    device_id       INTEGER REFERENCES devices(id) ON DELETE SET NULL,
    last_seen       DATETIME,
    raw_data        TEXT,                  -- JSON complet retourné par ZHA
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
--  CONFIG — Paramètres applicatifs clé/valeur
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS config (
    key         TEXT PRIMARY KEY,
    value       TEXT,
    description TEXT,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO config (key, value, description) VALUES
    ('mqtt_host',           'localhost',    'Hôte du broker Mosquitto'),
    ('mqtt_port_ws',        '9001',         'Port WebSocket Mosquitto'),
    ('mqtt_user',           '',             'Utilisateur MQTT (optionnel)'),
    ('mqtt_password',       '',             'Mot de passe MQTT (optionnel)'),
    ('ha_host',             'localhost',    'Hôte Home Assistant'),
    ('ha_port',             '8123',         'Port Home Assistant'),
    ('ha_token',            '',             'Token Long-Lived Home Assistant'),
    ('ha_ssl',              '0',            'HA en HTTPS ? 0|1'),
    ('zigbee_permit_join',  '0',            'Appairage Zigbee actif ?'),
    ('theme_palette',       'midnight',     'Palette par défaut'),
    ('timezone',            'Europe/Paris', 'Fuseau horaire'),
    ('energy_price_kwh',    '0.2516',       'Prix kWh en euros (tarif EDF base)'),
    ('app_name',            'DomoGlass Pro','Nom affiché dans l''interface'),
    ('app_version',         '2.0.0',        'Version'),
    ('skyconnect_config',   '{"enabled":true,"serial_port":"/dev/ttyUSB0","mode":"zigbee","integration":"zigbee2mqtt-windfront","web_url":"http://192.168.1.237:8081","mqtt_user":"gegevdb","mqtt_password":"3yn4coYd"}', 'Configuration SkyConnect Zigbee');

-- ------------------------------------------------------------
--  NOTIFICATIONS — Journal des alertes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    type        TEXT    NOT NULL DEFAULT 'info',  -- info|success|warning|error
    title       TEXT    NOT NULL,
    message     TEXT,
    read        INTEGER DEFAULT 0,
    source      TEXT,   -- device_id, automation_id, system...
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_notif_read ON notifications(read, created_at DESC);

-- ------------------------------------------------------------
--  USERS — Comptes pour l'interface (admin/user)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL DEFAULT 'user',
    enabled       INTEGER NOT NULL DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TRIGGER IF NOT EXISTS users_updated
    AFTER UPDATE ON users
    FOR EACH ROW
    BEGIN
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = OLD.id;
    END;
