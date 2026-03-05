#!/bin/bash

# =============================================================================
# CHANGER PORT CADDY VERS 18080 et LIBERER 8080 pour Windfront
# =============================================================================

set -e

echo "🔧 Changement port Caddy vers 18080"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

NEW_PORT="18080"
OLD_PORT="8080"

echo "📋 Changement : $OLD_PORT → $NEW_PORT"

# 1. Trouver et modifier la configuration Caddy
echo "🔍 Recherche de la configuration Caddy..."

# Fichiers Caddy possibles
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
        echo "✅ Configuration trouvée: $file"
        break
    fi
done

if [ -z "$CADDYFILE" ]; then
    echo "❌ Configuration Caddy non trouvée. Recherche manuelle..."
    find /etc -name "*Caddyfile*" 2>/dev/null
    find /usr -name "*Caddyfile*" 2>/dev/null
    echo "   Modifiez manuellement le port dans le fichier Caddyfile trouvé"
    exit 1
fi

# 2. Backup de la configuration
echo "💾 Backup de la configuration..."
cp "$CADDYFILE" "$CADDYFILE.backup.$(date +%Y%m%d-%H%M%S)"

# 3. Changer le port
echo "🔧 Modification du port..."
sed -i "s/:$OLD_PORT/:$NEW_PORT/g" "$CADDYFILE"

# 4. Vérifier les changements
echo "🔍 Vérification des changements..."
echo "   Ancienne configuration (backup):"
grep ":$OLD_PORT" "$CADDYFILE.backup.$(date +%Y%m%d-%H%M%S)" || echo "   (aucun port $OLD_PORT trouvé)"
echo ""
echo "   Nouvelle configuration:"
grep ":$NEW_PORT" "$CADDYFILE" || echo "   Port $NEW_PORT non trouvé dans la config"

# 5. Redémarrer Caddy
echo "🚀 Redémarrage de Caddy..."
systemctl reload caddy 2>/dev/null || systemctl restart caddy

# 6. Vérifier que Caddy écoute sur le nouveau port
echo "🔍 Vérification du nouveau port..."
sleep 3
if netstat -tlnp | grep -q ":$NEW_PORT "; then
    echo "✅ Caddy écoute sur le port $NEW_PORT"
else
    echo "⚠️  Caddy ne semble pas écouter sur le port $NEW_PORT"
    echo "   Vérification: netstat -tlnp | grep :$NEW_PORT"
fi

# 7. Vérifier que l'ancien port est libre
echo "🔍 Vérification de la libération du port $OLD_PORT..."
if netstat -tlnp | grep -q ":$OLD_PORT "; then
    echo "❌ Le port $OLD_PORT est encore utilisé:"
    netstat -tlnp | grep ":$OLD_PORT"
else
    echo "✅ Port $OLD_PORT libéré avec succès"
fi

# 8. Mettre à jour Windfront pour utiliser 8080
echo "🌐 Configuration de Windfront sur le port 8080..."
if [ -f "/opt/zigbee2mqtt-windfront/config.json" ]; then
    sed -i "s/\"port\": [0-9]*/\"port\": 8080/g" /opt/zigbee2mqtt-windfront/config.json
    echo "✅ Windfront configuré sur le port 8080"
    
    # Redémarrer Windfront
    systemctl restart zigbee-windfront
    sleep 3
    
    if netstat -tlnp | grep -q ":8080 "; then
        echo "✅ Windfront écoute sur le port 8080"
    else
        echo "⚠️  Windfront ne semble pas écouter sur le port 8080"
    fi
else
    echo "⚠️  Windfront non trouvé, configuration manuelle requise"
fi

# 9. Mettre à jour la configuration DomoGlass
echo "💾 Mise à jour de la configuration DomoGlass..."
CONFIG_FILE="/srv/dom-03/config.php"
if [ -f "$CONFIG_FILE" ]; then
    SERVER_IP=$(hostname -I | awk '{print $1}')
    php -r "
    require_once '$CONFIG_FILE';
    require_once '/srv/dom-03/includes/db.php';
    \$config = [
        'enabled' => true,
        'serial_port' => '/dev/ttyUSB0',
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
echo "🎉 Changement de ports terminé !"
echo "================================================================"
echo "🌐 Accès:"
echo "   - DomoGlass: http://$(hostname -I | awk '{print $1}'):$NEW_PORT"
echo "   - Windfront: http://$(hostname -I | awk '{print $1}'):8080"
echo ""
echo "📊 Services:"
echo "   - Caddy: systemctl status caddy"
echo "   - Windfront: systemctl status zigbee-windfront"
echo ""
echo "✅ Ports modifiés avec succès !"
