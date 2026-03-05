#!/bin/bash

# =============================================================================
# CORRECTION INSTALLATION ZIGBEE2MQTT
# =============================================================================

set -e

echo "🔧 Correction de l'installation Zigbee2MQTT..."

# 1. Créer le home directory pour zigbee2mqtt
echo "🏠 Création du home directory..."
mkdir -p /home/zigbee2mqtt
chown zigbee2mqtt:zigbee2mqtt /home/zigbee2mqtt
chmod 755 /home/zigbee2mqtt

# 2. Définir le home directory dans le service
echo "🔧 Correction du service systemd..."
cat > /etc/systemd/system/zigbee2mqtt.service << 'EOF'
[Unit]
Description=zigbee2mqtt
After=network.target mosquitto.service
Wants=mosquitto.service

[Service]
ExecStart=/usr/bin/node index.js
WorkingDirectory=/opt/zigbee2mqtt
Environment=HOME=/home/zigbee2mqtt
StandardOutput=journal
StandardError=journal
Restart=always
User=zigbee2mqtt

[Install]
WantedBy=multi-user.target
EOF

# 3. Nettoyer et réinstaller
echo "🧹 Nettoyage et réinstallation..."
cd /opt/zigbee2mqtt
rm -rf node_modules package-lock.json

# 4. Installer avec le bon flag
echo "📦 Installation des dépendances..."
sudo -u zigbee2mqtt -H /home/zigbee2mqtt npm install --production

# 5. Redémarrer les services
echo "🚀 Redémarrage des services..."
systemctl daemon-reload
systemctl restart zigbee2mqtt

# 6. Vérification
echo "🔍 Vérification..."
sleep 3
if systemctl is-active --quiet zigbee2mqtt; then
    echo "✅ Zigbee2MQTT: ACTIF"
else
    echo "❌ Zigbee2MQTT: INACTIF"
    echo "   Logs: journalctl -u zigbee2mqtt -f"
fi

echo "✅ Correction terminée !"
