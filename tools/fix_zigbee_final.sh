#!/bin/bash

# =============================================================================
# CORRECTION FINALE INSTALLATION ZIGBEE2MQTT
# =============================================================================

set -e

echo "🔧 Correction finale installation Zigbee2MQTT..."

# 1. Créer le home directory avec chemins absolus
echo "🏠 Création du home directory..."
mkdir -p /home/zigbee2mqtt
chown zigbee2mqtt:zigbee2mqtt /home/zigbee2mqtt
chmod 755 /home/zigbee2mqtt

# 2. Donner les permissions sur /opt
echo "🔐 Permissions sur /opt/zigbee2mqtt..."
chown -R zigbee2mqtt:zigbee2mqtt /opt/zigbee2mqtt

# 3. Se connecter comme zigbee2mqtt pour installer
echo "📦 Installation des dépendances..."
cd /opt/zigbee2mqtt

# Nettoyer
rm -rf node_modules package-lock.json

# Installer directement en tant que zigbee2mqtt
sudo -u zigbee2mqtt bash -c "cd /opt/zigbee2mqtt && HOME=/home/zigbee2mqtt npm install --production"

# 4. Créer le service systemd corrigé
echo "🔧 Création du service systemd..."
cat > /etc/systemd/system/zigbee2mqtt.service << 'EOF'
[Unit]
Description=zigbee2mqtt
After=network.target mosquitto.service
Wants=mosquitto.service

[Service]
ExecStart=/usr/bin/node index.js
WorkingDirectory=/opt/zigbee2mqtt
Environment=HOME=/home/zigbee2mqtt
Environment=NODE_ENV=production
StandardOutput=journal
StandardError=journal
Restart=always
User=zigbee2mqtt
Group=zigbee2mqtt

[Install]
WantedBy=multi-user.target
EOF

# 5. Redémarrer les services
echo "🚀 Redémarrage des services..."
systemctl daemon-reload
systemctl restart zigbee2mqtt

# 6. Vérification
echo "🔍 Vérification..."
sleep 5

if systemctl is-active --quiet zigbee2mqtt; then
    echo "✅ Zigbee2MQTT: ACTIF"
    echo ""
    echo "📊 Statut détaillé:"
    systemctl status zigbee2mqtt --no-pager -l
    echo ""
    echo "📋 Logs récents:"
    journalctl -u zigbee2mqtt --no-pager -n 10
else
    echo "❌ Zigbee2MQTT: INACTIF"
    echo ""
    echo "🔍 Diagnostic:"
    echo "   - Statut: systemctl status zigbee2mqtt"
    echo "   - Logs: journalctl -u zigbee2mqtt -f"
    echo "   - Vérifier le port: ls -la $SERIAL_PORT"
    echo "   - Permissions: groups zigbee2mqtt"
fi

echo "✅ Correction terminée !"
