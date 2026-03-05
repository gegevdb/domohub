#!/bin/bash

# =============================================================================
# INSTALLATION ZIGBEE2MQTT-WINDFRONT (Interface web complète)
# =============================================================================

set -e

echo "🚀 Installation Zigbee2MQTT-Windfront pour SkyConnect"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

# Vérifier SkyConnect
echo "🔍 Vérification du SkyConnect..."
if ! lsusb | grep -q "10c4:ea60"; then
    echo "❌ SkyConnect non détecté (ID 10c4:ea60)"
    exit 1
else
    echo "✅ SkyConnect détecté"
fi

# Détecter le port série
SERIAL_PORT=$(find /dev -name "ttyUSB*" -o -name "ttyACM*" | head -1)
if [ -z "$SERIAL_PORT" ]; then
    echo "❌ Aucun port série détecté"
    exit 1
else
    echo "✅ Port série détecté: $SERIAL_PORT"
fi

# Arrêter notre ancien service zigbee-herdsman
echo "🛑 Arrêt de l'ancien service..."
systemctl stop zigbee-herdsman 2>/dev/null || true
systemctl disable zigbee-herdsman 2>/dev/null || true

# Installer Node.js si nécessaire
echo "📦 Vérification Node.js..."
if ! command -v node &> /dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
    apt-get install -y nodejs
fi

NODE_VERSION=$(node --version)
echo "✅ Node.js: $NODE_VERSION"

# Installer Windfront
echo "🌐 Installation de Zigbee2MQTT-Windfront..."
cd /opt
rm -rf zigbee2mqtt-windfront
git clone https://github.com/nerivec/zigbee2mqtt-windfront.git
cd zigbee2mqtt-windfront

# Installer les dépendances
echo "📚 Installation des dépendances..."
npm install

# Créer la configuration directement (pas de config.example.json)
echo "⚙️ Configuration de Windfront..."
cat > config.json << 'EOF'
{
  "mqtt": {
    "url": "mqtt://localhost:1883",
    "username": "zigbee2mqtt",
    "password": "zigbee2mqtt_password"
  },
  "serial": {
    "port": "$SERIAL_PORT",
    "baudrate": 115200,
    "rtscts": false
  },
  "network": {
    "panID": "0x1a62",
    "channel": 11,
    "extendedPanID": "dddddddddddddddddd"
  },
  "frontend": {
    "port": 8080,
    "auth": false
  }
}
EOF

# Donner les permissions
chown -R root:root /opt/zigbee2mqtt-windfront

# Créer le service systemd
echo "🔧 Création du service systemd..."
cat > /etc/systemd/system/zigbee-windfront.service << 'EOF'
[Unit]
Description=Zigbee2MQTT Windfront
After=network.target mosquitto.service
Wants=mosquitto.service

[Service]
Type=simple
WorkingDirectory=/opt/zigbee2mqtt-windfront
ExecStart=/usr/bin/npm start
Environment=NODE_ENV=production
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal
User=root
Group=root

[Install]
WantedBy=multi-user.target
EOF

# Démarrer Windfront
echo "🚀 Démarrage de Windfront..."
systemctl daemon-reload
systemctl enable zigbee-windfront
systemctl start zigbee-windfront

# Vérification
echo "🔍 Vérification de l'installation..."
sleep 5

if systemctl is-active --quiet zigbee-windfront; then
    echo "✅ Windfront: ACTIF"
    echo ""
    echo "🌐 Interface web: http://$(hostname -I | awk '{print $1}'):8080"
    echo "📊 Statut: systemctl status zigbee-windfront"
    echo "📋 Logs: journalctl -u zigbee-windfront -f"
else
    echo "❌ Windfront: INACTIF"
    echo "   Logs: journalctl -u zigbee-windfront -f"
fi

# Configuration dans DomoGlass
echo "💾 Mise à jour de la configuration DomoGlass..."
CONFIG_FILE="/var/www/dom-03/config.php"
if [ -f "$CONFIG_FILE" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
    php -r "
    require_once '$CONFIG_FILE';
    require_once '/var/www/dom-03/includes/db.php';
    \$config = [
        'enabled' => true,
        'serial_port' => '$SERIAL_PORT',
        'mode' => 'zigbee',
        'integration' => 'zigbee2mqtt-windfront',
        'web_url' => 'http://$SERVER_IP:8080',
        'mqtt_user' => 'zigbee2mqtt',
        'mqtt_password' => 'zigbee2mqtt_password'
    ];
    db()->setConfig('skyconnect_config', json_encode(\$config));
    echo '✅ Configuration SkyConnect mise à jour dans DomoGlass\n';
    "
else
    echo "⚠️  Fichier de configuration DomoGlass non trouvé: $CONFIG_FILE"
fi

echo ""
echo "🎉 Windfront installé !"
echo "================================================================"
echo "🌐 Interface web complète:"
echo "   - URL: http://$(hostname -I | awk '{print $1}'):8080"
echo "   - Gestion des appareils Zigbee complète"
echo "   - Logs en temps réel"
echo "   - Configuration avancée"
echo ""
echo "📊 Services:"
echo "   - Windfront: systemctl status zigbee-windfront"
echo "   - Mosquitto: systemctl status mosquitto"
echo ""
echo "🔗 Intégration DomoGlass:"
echo "   - Paramètres > Dongles USB > SkyConnect"
echo ""
echo "✅ SkyConnect avec Windfront est prêt !"
