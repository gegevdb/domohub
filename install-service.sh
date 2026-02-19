#!/bin/bash

# Script d'installation du service DomoHub pour démarrage automatique

set -e

echo "🚀 Installation du service DomoHub pour démarrage automatique"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Ce script doit être exécuté en tant que root"
    exit 1
fi

# Créer les répertoires de logs
mkdir -p /srv/domohub/logs
mkdir -p /srv/domohub/data

# Copier le fichier de service
echo "📋 Installation du fichier de service..."
cp domohub.service /etc/systemd/system/

# Recharger systemd
echo "🔄 Rechargement de systemd..."
systemctl daemon-reload

# Activer le service
echo "✅ Activation du service DomoHub..."
systemctl enable domohub.service

# Démarrer le service
echo "🚀 Démarrage du service DomoHub..."
systemctl start domohub.service

# Vérifier le statut
echo "📊 Vérification du statut du service..."
systemctl status domohub.service

echo ""
echo "🎉 Installation terminée !"
echo ""
echo "📋 Commandes utiles :"
echo "  Vérifier le statut: systemctl status domohub"
echo "  Voir les logs: journalctl -u domohub -f"
echo "  Arrêter: systemctl stop domohub"
echo "  Démarrer: systemctl start domohub"
echo "  Redémarrer: systemctl restart domohub"
echo ""
echo "🌐 DomoHub accessible sur: http://$(hostname -I | awk '{print $1}'):8000"
echo "📖 Documentation: http://$(hostname -I | awk '{print $1}'):8000/docs"
