# Déploiement DomoHub sur Lubuntu (Proxmox)

## 🚀 Étapes de déploiement

### 1. Préparation de la VM Lubuntu
```bash
# Mise à jour du système
sudo apt update && sudo apt upgrade -y

# Installation Python et dépendances
sudo apt install -y python3 python3-pip python3-venv
sudo apt install -y python3-dev build-essential
sudo apt install -y portaudio19-dev python3-pyaudio
sudo apt install -y libasound2-dev
sudo apt install -y bluetooth bluez libbluetooth-dev
sudo apt install -y git curl wget
```

### 2. Transfert des fichiers
```bash
# Depuis Windows vers VM (scp ou partage réseau)
scp -r domohub-linux/ user@vm-ip:/home/user/
# OU via partage Proxmox
```

### 3. Installation DomoHub
```bash
cd domohub-linux
python3 -m venv venv
source venv/bin/activate
pip install -r requirements-deploy.txt
```

### 4. Configuration
```bash
# Copie configuration
cp config/config.example.yaml config/config.yaml
cp .env.example .env

# Édition configuration
nano config/config.yaml
nano .env
```

### 5. Lancement
```bash
# Mode développement
python -m src.main

# Mode production (background)
nohup python -m src.main > logs/domohub.log 2>&1 &
```

### 6. Vérification
```bash
# Test API
curl http://localhost:8000/api/v1/devices/
curl http://localhost:8000/api/v1/system/status

# Documentation
# Ouvrir http://vm-ip:8000/docs
```

## 🔧 Configuration recommandée

### config/config.yaml
```yaml
server:
  host: "0.0.0.0"
  port: 8000
  debug: false
  ssl_enabled: false

database:
  url: "sqlite:///./domohub.db"
  echo: false

mqtt:
  broker_host: "localhost"
  broker_port: 1883

voice:
  enabled: true
  recognition_engine: "google"
  synthesis_engine: "pyttsx3"
  language: "fr-FR"

devices:
  discovery_enabled: true
  discovery_interval: 300
```

## 🎯 Tests post-déploiement

1. **API REST** : `http://vm-ip:8000/docs`
2. **Devices** : Test découverte Bluetooth
3. **Voice** : Test reconnaissance vocale
4. **MQTT** : Test communication
5. **Monitoring** : Vérification métriques

## 📝 Notes

- **Port 8000** : À ouvrir dans firewall Proxmox
- **Bluetooth** : Nécessite droits ou `sudo`
- **Audio** : Vérifier permissions PulseAudio
- **Logs** : `/logs/domohub.log`

## 🔄 Migration vers RaspberryPi/OrangePi

Une fois validé sur VM :
1. Exporter configuration
2. Adapter requirements (ARM)
3. Déployer sur hardware cible
