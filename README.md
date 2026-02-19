# DomoHub Linux

Système domotique intelligent optimisé pour Linux et RaspberryPi.

## 🚀 Installation

```bash
# Cloner le projet
git clone <repository-url>
cd domohub-linux

# Créer l'environnement virtuel
python3 -m venv venv
source venv/bin/activate

# Installer les dépendances
pip install -r requirements-linux.txt

# Configuration
cp config/config.example.yaml config/config.yaml

# Lancement
python -m src.main
```

## 🏗️ Architecture

- **Backend**: FastAPI (asynchrone)
- **Base de données**: SQLite avec SQLAlchemy
- **Sécurité**: JWT, bcrypt, HTTPS
- **Communication**: MQTT, Bluetooth LE, Serial
- **Interface Web**: Responsive avec WebSocket
- **Contrôle Vocal**: Reconnaissance et synthèse vocale
- **Plugins**: Système extensible pour les dispositifs

## � Configuration

Le système est optimisé pour:
- **Faible consommation**: Optimisé pour ARM/RaspberryPi
- **Haute disponibilité**: Redémarrage automatique, monitoring
- **Sécurité**: Chiffrement de bout en bout
- **Extensibilité**: Plugin system pour nouveaux dispositifs

## 🎤 Contrôle Vocal

- Reconnaissance vocale offline (SpeechRecognition)
- Synthèse vocale multilingue (pyttsx3)
- Commandes personnalisables
- Support multi-langues

## 🔌 Plugins

Système de plugins pour:
- Lumières (Philips Hue, IKEA Trådfri)
- Capteurs (température, humidité, mouvement)
- Sécurité (caméras, alarmes)
- Multimédia (TV, musique)

## 📊 Monitoring

- Métriques Prometheus
- Logs structurés
- Health checks
- Alertes configurables
