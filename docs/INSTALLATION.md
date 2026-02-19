# Guide d'Installation de DomoHub

DomoHub est un système domotique intelligent conçu pour fonctionner 24/7 sur RaspberryPi, OrangePi ou autres machines Linux/Windows.

## Prérequis

### Systèmes Supportés
- **Linux**: Ubuntu 18.04+, Debian 10+, Raspberry Pi OS
- **Windows**: Windows 10/11 (PowerShell 5.1+)
- **Docker**: Docker Engine 20.10+

### Matériel Recommandé
- **Minimum**: Raspberry Pi 3B+ (1GB RAM)
- **Recommandé**: Raspberry Pi 4B (2GB RAM+) ou Orange Pi 5
- **Stockage**: 16GB+ microSD (Classe 10) ou SSD
- **Réseau**: WiFi ou Ethernet (recommandé pour stabilité)

## Installation Automatisée

### Linux/macOS

```bash
# Clone du projet
git clone https://github.com/votre-repo/domohub.git
cd domohub

# Installation automatique
chmod +x scripts/install.sh
sudo ./scripts/install.sh
```

### Windows

```powershell
# Clone du projet
git clone https://github.com/votre-repo/domohub.git
cd domohub

# Installation automatique (exécuter en tant qu'administrateur)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
.\scripts\install.ps1
```

## Installation Manuelle

### 1. Préparation de l'Environnement

#### Linux
```bash
# Installation des dépendances
sudo apt update
sudo apt install -y python3 python3-pip python3-venv git portaudio19-dev python3-dev gcc sqlite3

# Création de l'utilisateur (optionnel)
sudo useradd -m -s /bin/bash domohub
sudo usermod -aG audio domohub  # Pour l'accès au micro
```

#### Windows
```powershell
# Installation de Python (télécharger depuis python.org)
# Vérifier l'option "Add Python to PATH"

# Installation de Git (télécharger depuis git-scm.com)
```

### 2. Installation des Dépendances Python

```bash
# Clone du projet
git clone https://github.com/votre-repo/domohub.git
cd domohub

# Création de l'environnement virtuel
python3 -m venv venv

# Activation de l'environnement
# Linux/macOS:
source venv/bin/activate
# Windows:
.\venv\Scripts\Activate.ps1

# Installation des dépendances
pip install --upgrade pip
pip install -r requirements.txt
```

### 3. Configuration

```bash
# Copie du fichier de configuration
cp .env.example .env

# Édition du fichier de configuration
nano .env  # ou votre éditeur préféré
```

**Paramètres essentiels à configurer:**
```env
SECRET_KEY=votre_clé_secrète_très_longue_et_sécurisée
ENVIRONMENT=production
SERVER_HOST=0.0.0.0
SERVER_PORT=8000

# Base de données
DATABASE_URL=sqlite:///./domohub.db

# MQTT (si utilisé)
MQTT_BROKER_HOST=votre_ip_mqtt
MQTT_BROKER_PORT=1883

# Contrôle vocal
VOICE_ENABLED=true
VOICE_LANGUAGE=fr-FR
```

### 4. Initialisation de la Base de Données

```bash
# Avec l'environnement virtuel activé
python -c "from src.core.database import init_db; import asyncio; asyncio.run(init_db())"
```

### 5. Démarrage

#### Développement
```bash
# Linux/macOS
./scripts/start.sh dev

# Windows
.\scripts\start.ps1 dev
```

#### Production
```bash
# Linux/macOS
./scripts/start.sh prod

# Windows
.\scripts\start.ps1 prod
```

## Installation Docker

### 1. Préparation

```bash
# Clone du projet
git clone https://github.com/votre-repo/domohub.git
cd domohub/scripts

# Copie de la configuration
cp ../.env.example ../.env
# Éditez .env selon vos besoins
```

### 2. Démarrage

```bash
# Démarrage de tous les services
docker-compose up -d

# Vérification des services
docker-compose ps
```

### Services Inclus

- **domohub**: Application principale (port 8000)
- **nginx**: Reverse proxy (ports 80/443)
- **mosquitto**: Broker MQTT (port 1883)
- **redis**: Cache et sessions (port 6379)
- **prometheus**: Monitoring (port 9090)
- **grafana**: Tableaux de bord (port 3000)

## Configuration Post-Installation

### 1. Accès à l'Interface

Ouvrez votre navigateur et accédez à:
- **Interface web**: http://votre-ip-locale
- **API documentation**: http://votre-ip-locale/docs
- **Monitoring**: http://votre-ip-locale:3000 (Grafana)

### 2. Configuration du Micro (Contrôle Vocal)

#### Linux
```bash
# Test du micro
arecord -D plughw:0,0 -d 5 test.wav
aplay test.wav

# Configuration des permissions
sudo usermod -aG audio $USER
# Déconnexion/reconnexion requise
```

#### Windows
- Vérifiez que le micro est activé dans les paramètres Windows
- Autorisez l'accès au micro pour Python/terminal

### 3. Configuration des Plugins

Les plugins se configurent via l'interface web ou directement dans `.env`:

```env
# Plugin Philips Hue
PHILIPS_HUE_BRIDGE_IP=192.168.1.100
PHILIPS_HUE_USERNAME=votre_username_hue

# Plugin Xiaomi
XIAOMI_GATEWAY_IP=192.168.1.50
XIAOMI_GATEWAY_TOKEN=votre_token_gateway
```

## Dépannage

### Problèmes Communs

#### 1. Erreur de Permissions Audio (Linux)
```bash
# Ajout de l'utilisateur au groupe audio
sudo usermod -aG audio $USER
# Redémarrage nécessaire
```

#### 2. Port déjà Utilisé
```bash
# Vérification des ports utilisés
sudo netstat -tlnp | grep :8000

# Changement de port dans .env
SERVER_PORT=8001
```

#### 3. Erreur de Base de Données
```bash
# Suppression et recréation
rm domohub.db
python -c "from src.core.database import init_db; import asyncio; asyncio.run(init_db())"
```

#### 4. Problèmes de Performance (Raspberry Pi)
```bash
# Augmentation de la mémoire GPU
sudo raspi-config
# Advanced Options -> Memory Split -> 16

# Désactivation des services non utilisés
sudo systemctl disable bluetooth
sudo systemctl disable cups
```

### Logs

#### Linux (systemd)
```bash
# Logs du service
journalctl -u domohub -f

# Logs détaillés
tail -f /var/log/domohub/domohub.log
```

#### Windows
```powershell
# Logs du service Windows
Get-EventLog -LogName Application -Source DomoHub -Newest 50

# Logs dans le fichier
Get-Content "C:\DomoHub\logs\domohub.log" -Tail 50 -Wait
```

#### Docker
```bash
# Logs du conteneur
docker-compose logs -f domohub

# Logs de tous les services
docker-compose logs -f
```

## Mise à Jour

### Installation Automatisée

```bash
# Arrêt du service
sudo systemctl stop domohub

# Mise à jour du code
cd /opt/domohub
git pull origin main

# Mise à jour des dépendances
source venv/bin/activate
pip install -r requirements.txt

# Migration de la base de données (si nécessaire)
python -c "from src.core.database import init_db; import asyncio; asyncio.run(init_db())"

# Redémarrage
sudo systemctl start domohub
```

### Docker

```bash
# Mise à jour des images
docker-compose pull

# Recréation des conteneurs
docker-compose up -d --force-recreate
```

## Support

- **Documentation**: https://docs.domohub.local
- **Issues**: https://github.com/votre-repo/domohub/issues
- **Community**: https://community.domohub.local

## Sécurité

### Recommandations

1. **Changez le SECRET_KEY** par défaut
2. **Utilisez HTTPS** en production
3. **Configurez un firewall** approprié
4. **Mettez à jour régulièrement** le système
5. **Sauvegardez** votre base de données

### Backup

```bash
# Script de backup simple
#!/bin/bash
BACKUP_DIR="/backup/domohub"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup base de données
cp /opt/domohub/domohub.db $BACKUP_DIR/domohub_$DATE.db

# Backup configuration
cp /opt/domohub/.env $BACKUP_DIR/.env_$DATE

# Compression
tar -czf $BACKUP_DIR/domohub_backup_$DATE.tar.gz $BACKUP_DIR/*_$DATE.*

# Nettoyage (garde 7 jours)
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```
