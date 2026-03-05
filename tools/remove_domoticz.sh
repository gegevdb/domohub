#!/bin/bash

# =============================================================================
# SUPPRESSION DOMOTICZ (Libérer le port 8080 pour Windfront)
# =============================================================================

set -e

echo "🗑️ Suppression de Domoticz pour libérer le port 8080"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

# 1. Arrêter Domoticz
echo "🛑 Arrêt de Domoticz..."
systemctl stop domoticz 2>/dev/null || echo "Domoticz n'était pas en cours d'exécution"

# 2. Désactiver Domoticz au démarrage
echo "🔧 Désactivation de Domoticz..."
systemctl disable domoticz 2>/dev/null || echo "Domoticz n'était pas activé"

# 3. Supprimer le service systemd
echo "🗑️ Suppression du service systemd..."
rm -f /etc/systemd/system/domoticz.service
systemctl daemon-reload

# 4. Supprimer les fichiers Domoticz (optionnel)
echo "📁 Suppression des fichiers Domoticz..."
read -p "Voulez-vous supprimer tous les fichiers Domoticz ? (o/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Oo]$ ]]; then
    rm -rf /opt/domoticz
    rm -rf /home/pi/domoticz 2>/dev/null || true
    rm -rf /usr/local/domoticz 2>/dev/null || true
    echo "✅ Fichiers Domoticz supprimés"
else
    echo "⚠️  Fichiers Domoticz conservés"
fi

# 5. Vérifier que le port 8080 est libre
echo "🔍 Vérification du port 8080..."
sleep 2
if netstat -tlnp | grep -q ":8080 "; then
    echo "❌ Le port 8080 est encore utilisé"
    netstat -tlnp | grep ":8080"
else
    echo "✅ Port 8080 libéré avec succès"
fi

# 6. Redémarrer Windfront sur le port 8080
echo "🚀 Redémarrage de Windfront sur le port 8080..."
cd /opt/zigbee2mqtt-windfront

# S'assurer que le port est 8080 dans config.json
sed -i 's/"port": 808[0-9]/"port": 8080/g' config.json

# Redémarrer Windfront
systemctl restart zigbee-windfront
sleep 3

# 7. Vérification finale
echo "🔍 Vérification finale..."
if systemctl is-active --quiet zigbee-windfront; then
    echo "✅ Windfront: ACTIF"
    if netstat -tlnp | grep -q ":8080 "; then
        echo "✅ Windfront écoute sur le port 8080"
        echo ""
        echo "🌐 Accès: http://$(hostname -I | awk '{print $1}'):8080"
    else
        echo "⚠️  Windfront ne semble pas écouter sur le port 8080"
        echo "   Vérification: netstat -tlnp | grep :8080"
    fi
else
    echo "❌ Windfront: INACTIF"
    echo "   Logs: journalctl -u zigbee-windfront -f"
fi

echo ""
echo "🎉 Opération terminée !"
echo "================================================================"
echo "📊 État des services:"
echo "   - Domoticz: Supprimé"
echo "   - Windfront: systemctl status zigbee-windfront"
echo "   - Mosquitto: systemctl status mosquitto"
echo ""
echo "🌐 Accès Windfront:"
echo "   http://$(hostname -I | awk '{print $1}'):8080"
echo ""
echo "✅ Port 8080 libéré pour Windfront !"
