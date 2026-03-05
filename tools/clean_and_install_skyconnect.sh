#!/bin/bash

# =============================================================================
# SCRIPT DE NETTOYAGE + INSTALLATION SKYCONNECT PROPRE
# Pour DomoGlass Pro
# =============================================================================

set -e

echo "🧹 Nettoyage et réinstallation SkyConnect + MQTT + Zigbee2MQTT"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

# 1. Nettoyer les installations précédentes
echo "🧹 Nettoyage des installations précédentes..."

# Arrêter les services
systemctl stop mosquitto 2>/dev/null || true
systemctl stop zigbee2mqtt 2>/dev/null || true
systemctl disable mosquitto 2>/dev/null || true
systemctl disable zigbee2mqtt 2>/dev/null || true

# Supprimer les containers Docker MQTT
echo "🐳 Arrêt et suppression des containers Docker MQTT..."
docker stop $(docker ps -q --filter "publish=1883") 2>/dev/null || true
docker rm $(docker ps -aq --filter "publish=1883") 2>/dev/null || true

# Supprimer les paquets
apt purge -y mosquitto mosquitto-clients nodejs npm 2>/dev/null || true
apt autoremove -y
apt autoclean

# Supprimer les dossiers
rm -rf /opt/zigbee2mqtt
rm -rf /etc/zigbee2mqtt
rm -rf /etc/mosquitto
rm -rf /var/lib/mosquitto
rm -rf /var/log/mosquitto
rm -f /etc/systemd/system/zigbee2mqtt.service
rm -f /etc/systemd/system/multi-user.target.wants/mosquitto.service

# Recharger systemd
systemctl daemon-reload

echo "✅ Nettoyage terminé"

# 2. Vérifier SkyConnect
echo "🔍 Vérification du SkyConnect..."
if ! lsusb | grep -q "10c4:ea60"; then
    echo "❌ SkyConnect non détecté (ID 10c4:ea60)"
    echo "   Vérifiez le branchement USB et le pass-through Proxmox"
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

# 3. Mettre à jour le système
echo "📦 Mise à jour du système..."
apt update && apt upgrade -y

# 4. Installer Mosquitto (MQTT)
echo "📡 Installation de Mosquitto (MQTT)..."
apt install -y mosquitto mosquitto-clients

# Configuration Mosquitto propre
echo "🔧 Configuration de Mosquitto..."
mkdir -p /etc/mosquitto/conf.d
mkdir -p /var/lib/mosquitto
mkdir -p /var/log/mosquitto

cat > /etc/mosquitto/mosquitto.conf << 'EOF'
# Configuration Mosquitto pour DomoGlass Pro
listener 1883
allow_anonymous true
persistence true
persistence_location /var/lib/mosquitto/
log_dest file /var/log/mosquitto/mosquitto.log
log_type error
log_type warning
log_type notice
log_type information
max_connections 1000
EOF

# Permissions
chown -R mosquitto:mosquitto /var/lib/mosquitto
chown -R mosquitto:mosquitto /var/log/mosquitto
chmod 755 /var/lib/mosquitto
chmod 755 /var/log/mosquitto

# Démarrer Mosquitto
systemctl enable mosquitto
systemctl restart mosquitto
echo "✅ Mosquitto installé et démarré"

# 5. Downgrade Node.js vers v18 (requis pour Zigbee2MQTT)
echo "📦 Installation de Node.js v18..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

NODE_VERSION=$(node --version)
echo "✅ Node.js installé: $NODE_VERSION"

# 6. Créer utilisateur zigbee2mqtt
echo "👤 Création de l'utilisateur zigbee2mqtt..."
id zigbee2mqtt &>/dev/null || useradd -r -s /bin/false zigbee2mqtt

# 7. Installer Zigbee2MQTT
echo "📡 Installation de Zigbee2MQTT..."
rm -rf /opt/zigbee2mqtt
mkdir -p /opt/zigbee2mqtt
chown zigbee2mqtt:zigbee2mqtt /opt/zigbee2mqtt

# Cloner la version stable
cd /opt/zigbee2mqtt
sudo -u zigbee2mqtt git clone --depth 1 https://github.com/Koenkk/zigbee2mqtt.git .

# Installer les dépendances
echo "📚 Installation des dépendances..."
sudo -u zigbee2mqtt npm install --production

# 8. Configuration Zigbee2MQTT
echo "⚙️ Configuration de Zigbee2MQTT..."
mkdir -p /etc/zigbee2mqtt

cat > /etc/zigbee2mqtt/configuration.yaml << EOF
# Configuration Zigbee2MQTT pour DomoGlass Pro
homeassistant: true
permit_join: false
mqtt:
  base_topic: zigbee2mqtt
  server: mqtt://localhost
  user: zigbee2mqtt
  password: zigbee2mqtt_password
serial:
  port: $SERIAL_PORT
advanced:
  log_level: info
  pan_id: 0x1A62
  channel: 11
  network_key: GENERATE
  rtscts: false
  baudrate: 115200
EOF

# Donner les permissions sur le port série
echo "🔐 Configuration des permissions..."
usermod -a -G dialout zigbee2mqtt
chown zigbee2mqtt:zigbee2mqtt /etc/zigbee2mqtt
chmod 755 /etc/zigbee2mqtt

# 9. Créer le service systemd
echo "🔧 Création du service systemd..."
cat > /etc/systemd/system/zigbee2mqtt.service << 'EOF'
[Unit]
Description=zigbee2mqtt
After=network.target mosquitto.service
Wants=mosquitto.service

[Service]
ExecStart=/usr/bin/node index.js
WorkingDirectory=/opt/zigbee2mqtt
StandardOutput=journal
StandardError=journal
Restart=always
User=zigbee2mqtt

[Install]
WantedBy=multi-user.target
EOF

# 10. Configurer utilisateur MQTT pour Zigbee2MQTT
echo "🔑 Configuration utilisateur MQTT..."
mosquitto_passwd -c -b /etc/mosquitto/passwd zigbee2mqtt zigbee2mqtt_password

# Ajouter l'authentification dans la config Mosquitto
cat >> /etc/mosquitto/mosquitto.conf << 'EOF'

# Authentification
allow_anonymous false
password_file /etc/mosquitto/passwd
EOF

# Redémarrer Mosquitto avec authentification
systemctl restart mosquitto

# 11. Démarrer Zigbee2MQTT
echo "🚀 Démarrage des services..."
systemctl daemon-reload
systemctl enable zigbee2mqtt
systemctl restart zigbee2mqtt

# 12. Vérification
echo "🔍 Vérification de l'installation..."
sleep 5

# Vérifier Mosquitto
if systemctl is-active --quiet mosquitto; then
    echo "✅ Mosquitto: ACTIF (port 1883)"
else
    echo "❌ Mosquitto: INACTIF"
    echo "   Logs: journalctl -u mosquitto -f"
fi

# Vérifier Zigbee2MQTT
if systemctl is-active --quiet zigbee2mqtt; then
    echo "✅ Zigbee2MQTT: ACTIF"
else
    echo "❌ Zigbee2MQTT: INACTIF"
    echo "   Logs: journalctl -u zigbee2mqtt -f"
fi

# 13. Test MQTT
echo "🧪 Test de connexion MQTT..."
sleep 2
if mosquitto_pub -h localhost -u zigbee2mqtt -P zigbee2mqtt_password -t test/topic -m "test_message" 2>/dev/null; then
    echo "✅ Connexion MQTT: OK"
else
    echo "⚠️  Connexion MQTT: Échec (vérifier logs)"
fi

# 14. Configuration dans DomoGlass
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
        'zigbee2mqtt_integration' => 'local',
        'mqtt_user' => 'zigbee2mqtt',
        'mqtt_password' => 'zigbee2mqtt_password'
    ];
    db()->setConfig('skyconnect_config', json_encode(\$config));
    echo '✅ Configuration SkyConnect enregistrée dans DomoGlass\n';
    "
else
    echo "⚠️  Fichier de configuration DomoGlass non trouvé: $CONFIG_FILE"
fi

echo ""
echo "🎉 Installation propre terminée !"
echo "================================================================"
echo "📊 Services actifs:"
echo "   - Mosquitto (MQTT): systemctl status mosquitto"
echo "   - Zigbee2MQTT: systemctl status zigbee2mqtt"
echo ""
echo "🔧 Logs:"
echo "   - Zigbee2MQTT: journalctl -u zigbee2mqtt -f"
echo "   - Mosquitto: tail -f /var/log/mosquitto/mosquitto.log"
echo ""
echo "🌐 Interface web:"
echo "   - DomoGlass: http://$(hostname -I | awk '{print $1}')/"
echo "   - Configuration: Paramètres > Dongles USB"
echo ""
echo "🔑 Identifiants MQTT:"
echo "   - Utilisateur: zigbee2mqtt"
echo "   - Mot de passe: zigbee2mqtt_password"
echo ""
echo "📱 Pour ajouter des appareils Zigbee:"
echo "   1. Mettez 'permit_join: true' dans /etc/zigbee2mqtt/configuration.yaml"
echo "   2. Redémarrez: systemctl restart zigbee2mqtt"
echo "   3. Appairez vos appareils"
echo "   4. Remettez 'permit_join: false' et redémarrez"
echo ""
echo "✅ SkyConnect est prêt pour DomoGlass Pro !"
