#!/bin/bash

# Script de déploiement DomoHub pour Lubuntu/Proxmox

set -e

echo "🚀 Déploiement DomoHub Linux"

# Vérification Python
if ! command -v python3 &> /dev/null; then
    echo "❌ Python3 non installé"
    exit 1
fi

# Mise à jour système
echo "📦 Mise à jour du système..."
sudo apt update && sudo apt upgrade -y

# Installation dépendances système
echo "🔧 Installation dépendances système..."
sudo apt install -y python3 python3-pip python3-venv
sudo apt install -y python3-dev build-essential
sudo apt install -y portaudio19-dev python3-pyaudio
sudo apt install -y libasound2-dev
sudo apt install -y bluetooth bluez libbluetooth-dev
sudo apt install -y git curl wget

# Installation dépendances Python
echo "🐍 Installation dépendances Python..."
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements-deploy.txt

# Configuration
echo "⚙️ Configuration initiale..."
if [ ! -f config/config.yaml ]; then
    cp config/config.example.yaml config/config.yaml
    echo "✅ config/config.yaml créé"
fi

if [ ! -f .env ]; then
    cp .env.example .env
    echo "✅ .env créé"
fi

# Création répertoires
mkdir -p logs
mkdir -p data

# Permissions
echo "🔐 Configuration permissions..."
chmod +x deploy.sh
chmod 755 src/

# Test installation
echo "🧪 Test installation..."
python -c "import fastapi, uvicorn, sqlalchemy; print('✅ Modules importés avec succès')"

echo ""
echo "🎉 Déploiement terminé !"
echo ""
echo "📋 Prochaines étapes :"
echo "1. Activer l'environnement: source venv/bin/activate"
echo "2. Configurer: nano config/config.yaml"
echo "3. Lancer: python -m src.main"
echo "4. Documentation: http://$(hostname -I | awk '{print $1}'):8000/docs"
echo ""
echo "🔧 Pour le mode production:"
echo "nohup python -m src.main > logs/domohub.log 2>&1 &"
