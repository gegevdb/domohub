#!/bin/bash

# =============================================================================
# INSTALLATION ZIGBEE-HERDSMAN POUR SKYCONNECT (Alternative légère)
# =============================================================================

set -e

echo "🚀 Installation Zigbee-Herdsman pour SkyConnect"
echo "================================================"

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
echo "✅ Port série détecté: $SERIAL_PORT"

# Installer Node.js (si pas déjà fait)
echo "📦 Installation Node.js..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

NODE_VERSION=$(node --version)
echo "✅ Node.js: $NODE_VERSION"

# Créer le dossier pour notre service
echo "📁 Création du service Zigbee-Herdsman..."
mkdir -p /opt/zigbee-herdsman
cd /opt/zigbee-herdsman

# Créer package.json
cat > package.json << 'EOF'
{
  "name": "domoglass-zigbee-herdsman",
  "version": "1.0.0",
  "description": "Zigbee coordinator for DomoGlass using zigbee-herdsman",
  "main": "index.js",
  "dependencies": {
    "zigbee-herdsman": "^0.25.0",
    "zigbee-herdsman-converters": "^15.0.0",
    "mqtt": "^5.0.0"
  },
  "scripts": {
    "start": "node index.js"
  }
}
EOF

# Installer les dépendances
echo "📚 Installation des dépendances..."
npm install

# Créer le service principal
cat > index.js << 'EOF'
const { Controller } = require('zigbee-herdsman');
const mqtt = require('mqtt');

// Configuration
const config = {
    serial: {
        path: process.env.SERIAL_PORT || '/dev/ttyUSB0',
        baudrate: 115200,
        rtscts: false,
    },
    network: {
        networkKey: [1, 3, 5, 7, 9, 11, 13, 15, 0, 2, 4, 6, 8, 10, 12, 13],
        panID: 0x1a62,
        channelList: [11],
    },
};

// MQTT Client
const mqttClient = mqtt.connect('mqtt://localhost', {
    username: 'zigbee2mqtt',
    password: 'zigbee2mqtt_password'
});

// Zigbee Controller
const controller = new Controller(config);

async function start() {
    try {
        console.log('🚀 Démarrage Zigbee-Herdsman...');
        
        // Démarrer le contrôleur
        await controller.start();
        console.log('✅ Zigbee-Herdsman démarré');
        
        // Connecter MQTT
        await new Promise((resolve) => {
            mqttClient.on('connect', resolve);
        });
        console.log('✅ MQTT connecté');
        
        // Publier le statut
        mqttClient.publish('zigbee2mqtt/bridge/state', 'online');
        
        // Écouter les appareils
        controller.on('deviceJoined', (device) => {
            console.log('📱 Appareil rejoint:', device.ieeeAddr);
            mqttClient.publish('zigbee2mqtt/bridge/event', JSON.stringify({
                type: 'deviceJoined',
                data: {
                    ieeeAddr: device.ieeeAddr,
                    networkAddress: device.networkAddress
                }
            }));
        });
        
        controller.on('deviceLeft', (device) => {
            console.log('📱 Appareil parti:', device.ieeeAddr);
            mqttClient.publish('zigbee2mqtt/bridge/event', JSON.stringify({
                type: 'deviceLeft',
                data: {
                    ieeeAddr: device.ieeeAddr
                }
            }));
        });
        
        controller.on('message', (data) => {
            console.log('📨 Message reçu:', data.device.ieeeAddr, data.endpoint.ID, data.data);
            mqttClient.publish(`zigbee2mqtt/${data.device.ieeeAddr}`, JSON.stringify(data.data));
        });
        
        // Permettre l'ajout d'appareils
        controller.setPermitJoin(true);
        console.log('🔓 Pairage activé - Vous pouvez ajouter des appareils maintenant');
        
        // Envoyer le statut périodiquement
        setInterval(() => {
            mqttClient.publish('zigbee2mqtt/bridge/state', 'online');
        }, 60000); // chaque minute
        
        console.log('✅ Service Zigbee-Herdsman prêt !');
        
    } catch (error) {
        console.error('❌ Erreur:', error);
        process.exit(1);
    }
}

// Gestion de l'arrêt propre
process.on('SIGINT', async () => {
    console.log('🛑 Arrêt du service...');
    mqttClient.publish('zigbee2mqtt/bridge/state', 'offline');
    await controller.stop();
    mqttClient.end();
    process.exit(0);
});

// Démarrer
start();
EOF

# Créer le service systemd
echo "🔧 Création du service systemd..."
cat > /etc/systemd/system/zigbee-herdsman.service << EOF
[Unit]
Description=Zigbee-Herdsman for DomoGlass
After=network.target mosquitto.service
Wants=mosquitto.service

[Service]
Type=simple
ExecStart=/usr/bin/node index.js
WorkingDirectory=/opt/zigbee-herdsman
Environment=SERIAL_PORT=$SERIAL_PORT
Restart=always
RestartSec=10
User=root
Group=root

[Install]
WantedBy=multi-user.target
EOF

# Démarrer le service
echo "🚀 Démarrage du service..."
systemctl daemon-reload
systemctl enable zigbee-herdsman
systemctl start zigbee-herdsman

# Vérification
echo "🔍 Vérification..."
sleep 3
if systemctl is-active --quiet zigbee-herdsman; then
    echo "✅ Zigbee-Herdsman: ACTIF"
    echo ""
    echo "📊 Statut:"
    systemctl status zigbee-herdsman --no-pager -l
    echo ""
    echo "📋 Logs:"
    journalctl -u zigbee-herdsman --no-pager -n 10
else
    echo "❌ Zigbee-Herdsman: INACTIF"
    echo "   Logs: journalctl -u zigbee-herdsman -f"
fi

# Configuration dans DomoGlass
echo "💾 Configuration dans DomoGlass..."
CONFIG_FILE="/var/www/dom-03/config.php"
if [ -f "$CONFIG_FILE" ]; then
    php -r "
    require_once '$CONFIG_FILE';
    require_once '/var/www/dom-03/includes/db.php';
    \$config = [
        'enabled' => true,
        'serial_port' => '$SERIAL_PORT',
        'mode' => 'zigbee',
        'integration' => 'zigbee-herdsman',
        'mqtt_user' => 'zigbee2mqtt',
        'mqtt_password' => 'zigbee2mqtt_password'
    ];
    db()->setConfig('skyconnect_config', json_encode(\$config));
    echo '✅ Configuration SkyConnect enregistrée dans DomoGlass\n';
    "
fi

echo ""
echo "🎉 Zigbee-Herdsman installé !"
echo "================================"
echo "📊 Service: systemctl status zigbee-herdsman"
echo "📋 Logs: journalctl -u zigbee-herdsman -f"
echo "🔗 MQTT: zigbee2mqtt/bridge/state (online/offline)"
echo "📱 Appareils: zigbee2mqtt/{ieeeAddr}"
echo ""
echo "✅ SkyConnect est prêt avec Zigbee-Herdsman !"
