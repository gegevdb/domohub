# DomoGlass Pro + Windfront - Installation Complète

## 🎯 Vue d'ensemble

DomoGlass Pro est une interface domotique moderne avec intégration Zigbee complète via Windfront.

### Architecture
- **DomoGlass** : Interface principale (port 18080)
- **Windfront** : Interface Zigbee (port 8081) 
- **Zigbee2MQTT** : Backend Zigbee (port 8082)
- **Mosquitto** : Broker MQTT (port 1883)
- **Caddy** : Serveur web (port 18080)

## 🚀 Installation Automatique

### Prérequis
- Debian/Ubuntu 20.04+
- Accès root/sudo
- PHP 8.5+ (avec FPM)
- SkyConnect dongle (optionnel)

### Installation
```bash
# 1. Copier les fichiers DomoGlass
cp -r dom-03 /srv/dom-03

# 2. Lancer l'installation
chmod +x /srv/dom-03/scripts/setup_complete.sh
sudo /srv/dom-03/scripts/setup_complete.sh
```

## 📋 Configuration

### SkyConnect Zigbee
- Port : `/dev/ttyUSB0`
- Détection automatique
- Configuration via Windfront

### MQTT
- Hôte : `localhost:1883`
- Utilisateur : `gegevdb`
- Mot de passe : `3yn4coYd`

### Réseau
- DomoGlass : `http://IP:18080`
- Windfront : `http://IP:8081`
- Zigbee2MQTT : `http://IP:8082`

## 🔧 Utilisation

### 1. DomoGlass
1. Accéder à `http://IP:18080`
2. Créer un compte administrateur
3. Configurer les pièces et appareils

### 2. Windfront (Zigbee)
1. Accéder à `http://IP:8081`
2. Activer "Permit Join"
3. Mettre les appareils Zigbee en mode pairing
4. Les appareils apparaissent automatiquement

### 3. Appareils Zigbee
- **Ampoules** : Philips Hue, IKEA TRÅDFRI
- **Capteurs** : Température, mouvement, porte
- **Interrupteurs** : Prises, switches
- **Détecteurs** : Fumée, fuite

## 🛠️ Services

### Démarrer/Arrêter
```bash
# DomoGlass/Caddy
sudo systemctl restart caddy

# Windfront
sudo systemctl restart zigbee-windfront

# Zigbee2MQTT
sudo systemctl restart zigbee2mqtt

# Mosquitto
sudo systemctl restart mosquitto
```

### Logs
```bash
# Windfront
sudo journalctl -u zigbee-windfront -f

# Zigbee2MQTT
sudo journalctl -u zigbee2mqtt -f

# Mosquitto
sudo journalctl -u mosquitto -f
```

## 🔍 Diagnostic

### Vérifier les ports
```bash
netstat -tlnp | grep -E ":(18080|8081|8082|1883)"
```

### Vérifier les services
```bash
sudo systemctl status caddy zigbee-windfront zigbee2mqtt mosquitto
```

### Tester MQTT
```bash
mosquitto_pub -h localhost -u gegevdb -P 3yn4coYd -t test/topic -m "hello"
mosquitto_sub -h localhost -u gegevdb -P 3yn4coYd -t test/topic
```

## 📁 Fichiers importants

### Configuration
- `/srv/dom-03/config.php` : Config DomoGlass
- `/opt/zigbee2mqtt-windfront/config.json` : Config Windfront
- `/opt/zigbee2mqtt/configuration.yaml` : Config Zigbee2MQTT
- `/etc/mosquitto/mosquitto.conf` : Config MQTT

### Base de données
- `/srv/dom-03/domoglass.db` : SQLite DomoGlass

### Services
- `/etc/systemd/system/zigbee-windfront.service`
- `/etc/systemd/system/zigbee2mqtt.service`
- `/etc/caddy/Caddyfile`

## 🐛 Dépannage

### Windfront inaccessible
```bash
# Vérifier le port
netstat -tlnp | grep :8081

# Redémarrer
sudo systemctl restart zigbee-windfront
```

### Appareils Zigbee non détectés
```bash
# Vérifier SkyConnect
lsusb | grep 10c4:ea60
ls -la /dev/ttyUSB0

# Vérifier Zigbee2MQTT
sudo systemctl status zigbee2mqtt
```

### Configuration non sauvegardée
```bash
# Recréer la base de données
cd /srv/dom-03
sqlite3 domoglass.db < sql/schema.sql
```

## 🔄 Mises à jour

### DomoGlass
```bash
cd /srv/dom-03
git pull origin main
```

### Windfront
```bash
cd /opt/zigbee2mqtt-windfront
git pull origin main
npm install
npm run build
sudo systemctl restart zigbee-windfront
```

### Zigbee2MQTT
```bash
cd /opt/zigbee2mqtt
git pull origin master
npm install
sudo systemctl restart zigbee2mqtt
```

## 📚 Support

### Documentation
- [Windfront Documentation](https://github.com/nerivec/zigbee2mqtt-windfront)
- [Zigbee2MQTT Documentation](https://www.zigbee2mqtt.io/)
- [DomoGlass Repository](https://github.com/your-repo/domoglass)

### Communauté
- Issues GitHub pour les bugs
- Wiki pour les guides détaillés

---

**DomoGlass Pro v2.0.0** - Interface domotique moderne avec Zigbee intégré
