#!/bin/bash

# =============================================================================
# DIAGNOSTIC COMPLET DOMOGLASS + WINDFRONT + CADDY + MQTT
# =============================================================================

set -e

echo "🔍 DIAGNOSTIC COMPLET - DOMOGLASS PRO"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

echo ""
echo "📁 1. FICHIERS DOMOGLASS"
echo "================================================================"

# Vérifier les fichiers essentiels
DOMOGLASS_DIR="/srv/dom-03"
echo "📂 Dossier DomoGlass: $DOMOGLASS_DIR"
if [ -d "$DOMOGLASS_DIR" ]; then
    echo "✅ Dossier DomoGlass existe"
else
    echo "❌ Dossier DomoGlass n'existe pas"
fi

# Vérifier les fichiers de configuration
echo ""
echo "📋 Fichiers de configuration:"
for file in "config.php" "includes/db.php" "pages/zigbee.php"; do
    if [ -f "$DOMOGLASS_DIR/$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file manquant"
    fi
done

# Vérifier la base de données
echo ""
echo "🗄️ Base de données:"
if [ -f "$DOMOGLASS_DIR/domoglass.db" ]; then
    echo "✅ Base de données SQLite existe"
    sqlite3 "$DOMOGLASS_DIR/domoglass.db" "SELECT COUNT(*) FROM sqlite_master;" >/dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "✅ Base de données valide"
    else
        echo "❌ Base de données corrompue"
    fi
else
    echo "❌ Base de données manquante"
fi

echo ""
echo "🌐 2. CONFIGURATION CADDY"
echo "================================================================"

# Vérifier Caddy
CADDY_FILES=(
    "/etc/caddy/Caddyfile"
    "/etc/caddy/caddy.conf"
    "/etc/caddy/Caddyfile.conf"
    "/usr/local/etc/caddy/Caddyfile"
)

CADDYFILE=""
for file in "${CADDY_FILES[@]}"; do
    if [ -f "$file" ]; then
        CADDYFILE="$file"
        echo "✅ Configuration Caddy trouvée: $file"
        break
    fi
done

if [ -z "$CADDYFILE" ]; then
    echo "❌ Configuration Caddy non trouvée"
else
    echo "📋 Contenu du Caddyfile:"
    echo "---"
    cat "$CADDYFILE"
    echo "---"
fi

# Vérifier le statut Caddy
echo ""
echo "📊 Statut Caddy:"
if systemctl is-active --quiet caddy; then
    echo "✅ Caddy actif"
    echo "   PID: $(systemctl show -p MainPID caddy | cut -d= -f2)"
else
    echo "❌ Caddy inactif"
fi

echo ""
echo "🌐 3. CONFIGURATION WINDFRONT"
echo "================================================================"

WINDFRONT_DIR="/opt/zigbee2mqtt-windfront"
echo "📂 Dossier Windfront: $WINDFRONT_DIR"

if [ -d "$WINDFRONT_DIR" ]; then
    echo "✅ Dossier Windfront existe"
else
    echo "❌ Dossier Windfront n'existe pas"
fi

# Vérifier les fichiers Windfront
echo ""
echo "📋 Fichiers Windfront:"
for file in "package.json" "config.json" "node_modules"; do
    if [ -e "$WINDFRONT_DIR/$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file manquant"
    fi
done

# Vérifier la configuration Windfront
if [ -f "$WINDFRONT_DIR/config.json" ]; then
    echo ""
    echo "📋 Configuration Windfront:"
    echo "---"
    cat "$WINDFRONT_DIR/config.json"
    echo "---"
fi

# Vérifier le service Windfront
echo ""
echo "📊 Statut Windfront:"
if systemctl is-active --quiet zigbee-windfront; then
    echo "✅ Windfront actif"
    echo "   PID: $(systemctl show -p MainPID zigbee-windfront | cut -d= -f2)"
else
    echo "❌ Windfront inactif"
fi

echo ""
echo "📡 4. CONFIGURATION MQTT"
echo "================================================================"

# Vérifier Mosquitto
echo "📋 Fichiers Mosquitto:"
for file in "/etc/mosquitto/mosquitto.conf" "/etc/mosquitto/passwd"; do
    if [ -f "$file" ]; then
        echo "✅ $(basename $file)"
    else
        echo "❌ $(basename $file) manquant"
    fi
done

# Vérifier la configuration Mosquitto
if [ -f "/etc/mosquitto/mosquitto.conf" ]; then
    echo ""
    echo "📋 Configuration Mosquitto:"
    echo "---"
    cat "/etc/mosquitto/mosquitto.conf"
    echo "---"
fi

# Vérifier le statut Mosquitto
echo ""
echo "📊 Statut Mosquitto:"
if systemctl is-active --quiet mosquitto; then
    echo "✅ Mosquitto actif"
    echo "   PID: $(systemctl show -p MainPID mosquitto | cut -d= -f2)"
else
    echo "❌ Mosquitto inactif"
fi

echo ""
echo "🔌 5. ÉTAT DES PORTS"
echo "================================================================"

echo "📋 Ports utilisés:"
netstat -tlnp | grep -E ":(18080|8080|8081|1883)" | while read line; do
    port=$(echo $line | awk '{print $4}' | cut -d: -f2)
    pid=$(echo $line | awk '{print $7}' | cut -d/ -f1)
    echo "   Port $port: PID $pid"
done

echo ""
echo "🧪 6. TESTS DE CONNEXION"
echo "================================================================"

# Test DomoGlass
echo "🌐 Test DomoGlass (port 18080):"
curl -s -o /dev/null -w "%{http_code}" http://localhost:18080/ 2>/dev/null || echo "❌ Échec"

# Test Windfront
echo "🌐 Test Windfront (port 8080):"
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ 2>/dev/null || echo "❌ Échec"

# Test MQTT
echo "📡 Test MQTT (port 1883):"
timeout 2 bash -c "</dev/tcp/localhost/1883" && echo "✅ MQTT répond" || echo "❌ MQTT ne répond pas"

echo ""
echo "🔧 7. CORRECTIONS AUTOMATIQUES"
echo "================================================================"

# Corriger le port Windfront si nécessaire
if netstat -tlnp | grep -q ":8080.*node"; then
    echo "🔧 Windfront détecté sur 8080, vérification de la configuration..."
    if [ -f "$WINDFRONT_DIR/config.json" ]; then
        if grep -q '"port": 8081' "$WINDFRONT_DIR/config.json"; then
            echo "⚠️  config.json dit 8081 mais Windfront tourne sur 8080"
            echo "🔧 Correction du port dans config.json..."
            sed -i 's/"port": 8081/"port": 8080/g' "$WINDFRONT_DIR/config.json"
        fi
    fi
fi

# Corriger le service Windfront si nécessaire
if [ -f "/etc/systemd/system/zigbee-windfront.service" ]; then
    if grep -q "port 8081" "/etc/systemd/system/zigbee-windfront.service"; then
        echo "⚠️  Service configuré pour 8081 mais Windfront tourne sur 8080"
        echo "🔧 Correction du service..."
        sed -i 's/port 8081/port 8080/g' "/etc/systemd/system/zigbee-windfront.service"
        systemctl daemon-reload
    fi
fi

# Mettre à jour la configuration DomoGlass
if [ -f "$DOMOGLASS_DIR/config.php" ]; then
    echo "🔧 Mise à jour de la configuration SkyConnect..."
    SERVER_IP=$(hostname -I | awk '{print $1}')
    php -r "
    require_once '$DOMOGLASS_DIR/config.php';
    require_once '$DOMOGLASS_DIR/includes/db.php';
    \$config = [
        'enabled' => true,
        'serial_port' => '/dev/ttyUSB0',
        'mode' => 'zigbee',
        'integration' => 'zigbee2mqtt-windfront',
        'web_url' => 'http://$SERVER_IP:8080',
        'mqtt_user' => 'gegevdb',
        'mqtt_password' => '3yn4coYd'
    ];
    db()->setConfig('skyconnect_config', json_encode(\$config));
    echo '✅ Configuration SkyConnect mise à jour';
    "
fi

echo ""
echo "🎯 8. RÉCAPITULATIF"
echo "================================================================"

echo "🌐 URLs finales:"
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "   - DomoGlass: http://$SERVER_IP:18080"
echo "   - Windfront: http://$SERVER_IP:8080"

echo ""
echo "📊 Services:"
echo "   - Caddy: $(systemctl is-active caddy && echo 'ACTIF' || echo 'INACTIF')"
echo "   - Windfront: $(systemctl is-active zigbee-windfront && echo 'ACTIF' || echo 'INACTIF')"
echo "   - Mosquitto: $(systemctl is-active mosquitto && echo 'ACTIF' || echo 'INACTIF')"

echo ""
echo "✅ Diagnostic terminé !"
echo "================================================================"
