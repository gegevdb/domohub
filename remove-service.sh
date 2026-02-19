#!/bin/bash

# Script de désinstallation du service DomoHub

set -e

echo "🗑️ Désinstallation du service DomoHub"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then
    echo "❌ Ce script doit être exécuté en tant que root"
    exit 1
fi

# Arrêter le service
echo "⏹️ Arrêt du service DomoHub..."
systemctl stop domohub.service 2>/dev/null || true

# Désactiver le service
echo "❌ Désactivation du service..."
systemctl disable domohub.service 2>/dev/null || true

# Supprimer le fichier de service
echo "🗑️ Suppression du fichier de service..."
rm -f /etc/systemd/system/domohub.service

# Recharger systemd
echo "🔄 Rechargement de systemd..."
systemctl daemon-reload

echo "✅ Service DomoHub désinstallé avec succès"
