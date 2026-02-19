#!/bin/bash

# Script d'installation pour DomoHub
# Support: Ubuntu/Debian, Raspberry Pi OS

set -e

echo "🏠 Installation de DomoHub - Système Domotique Intelligent"

# Vérification des privilèges
if [[ $EUID -ne 0 ]]; then
   echo "Ce script doit être exécuté en tant que root" 
   exit 1
fi

# Détection du système
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$NAME
    VER=$VERSION_ID
else
    echo "Impossible de détecter le système d'exploitation"
    exit 1
fi

echo "📋 Système détecté: $OS $VER"

# Installation des dépendances système
echo "📦 Installation des dépendances système..."

if [[ "$OS" == *"Ubuntu"* ]] || [[ "$OS" == *"Debian"* ]]; then
    apt update
    apt install -y python3 python3-pip python3-venv git portaudio19-dev python3-dev gcc
    apt install -y sqlite3 nginx supervisor
elif [[ "$OS" == *"Raspberry Pi"* ]]; then
    apt update
    apt install -y python3 python3-pip python3-venv git portaudio19-dev python3-dev gcc
    apt install -y sqlite3 nginx supervisor
else
    echo "❌ Système non supporté"
    exit 1
fi

# Création de l'utilisateur domohub
echo "👤 Création de l'utilisateur domohub..."
if ! id "domohub" &>/dev/null; then
    useradd -m -s /bin/bash domohub
    usermod -aG audio domohub  # Pour l'accès au micro
fi

# Répertoire d'installation
INSTALL_DIR="/srv/domohub"
echo "📁 Installation dans $INSTALL_DIR..."

# Création du répertoire
mkdir -p $INSTALL_DIR
chown domohub:domohub $INSTALL_DIR

# Copie des fichiers (en supposant qu'on est dans le répertoire du projet)
echo "📋 Copie des fichiers..."
cp -r . $INSTALL_DIR/
chown -R domohub:domohub $INSTALL_DIR

# Installation Python
echo "🐍 Installation des dépendances Python..."
sudo -u domohub bash -c "cd $INSTALL_DIR && python3 -m venv venv"
sudo -u domohub bash -c "cd $INSTALL_DIR && source venv/bin/activate && pip install --upgrade pip"
sudo -u domohub bash -c "cd $INSTALL_DIR && source venv/bin/activate && pip install -r requirements.txt"

# Configuration
echo "⚙️ Configuration..."
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp $INSTALL_DIR/.env.example $INSTALL_DIR/.env
    chown domohub:domohub $INSTALL_DIR/.env
    echo "✅ Fichier .env créé. Veuillez le configurer."
fi

# Base de données
echo "🗄️ Initialisation de la base de données..."
sudo -u domohub bash -c "cd $INSTALL_DIR && source venv/bin/activate && python -c 'from src.core.database import init_db; import asyncio; asyncio.run(init_db())'"

# Service systemd
echo "🔧 Création du service systemd..."
cat > /etc/systemd/system/domohub.service << EOF
[Unit]
Description=DomoHub - Système Domotique Intelligent
After=network.target

[Service]
Type=simple
User=domohub
Group=domohub
WorkingDirectory=/srv/domohub
Environment=PATH=/srv/domohub/venv/bin
ExecStart=/srv/domohub/venv/bin/python -m src.main
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

# Configuration Nginx
echo "🌐 Configuration Nginx..."
cat > /etc/nginx/sites-available/domohub << EOF
server {
    listen 80;
    server_name _;
    
    # API
    location /api/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
    
    # Interface web
    location / {
        root /srv/domohub/web;
        index index.html;
        try_files \$uri \$uri/ /index.html;
    }
    
    # WebSocket
    location /ws/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

# Activation du site Nginx
ln -sf /etc/nginx/sites-available/domohub /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Configuration Supervisor pour les tâches de fond
echo "👥 Configuration Supervisor..."
cat > /etc/supervisor/conf.d/domohub-workers.conf << EOF
[program:domohub-device-monitor]
command=/srv/domohub/venv/bin/python -m src.workers.device_monitor
directory=/srv/domohub
user=domohub
autostart=true
autorestart=true
stderr_logfile=/var/log/domohub-device-monitor.err.log
stdout_logfile=/var/log/domohub-device-monitor.out.log

[program:domohub-voice-worker]
command=/srv/domohub/venv/bin/python -m src.workers.voice_worker
directory=/srv/domohub
user=domohub
autostart=true
autorestart=true
stderr_logfile=/var/log/domohub-voice-worker.err.log
stdout_logfile=/var/log/domohub-voice-worker.out.log
EOF

# Permissions pour les logs
mkdir -p /var/log/domohub
chown domohub:domohub /var/log/domohub

# Démarrage des services
echo "🚀 Démarrage des services..."
systemctl daemon-reload
systemctl enable domohub
systemctl start domohub

systemctl reload nginx
supervisorctl reread
supervisorctl update

# Vérification
echo "⏳ Vérification du démarrage..."
sleep 5

if systemctl is-active --quiet domohub; then
    echo "✅ DomoHub est démarré avec succès!"
else
    echo "❌ Erreur lors du démarrage de DomoHub"
    systemctl status domohub
    exit 1
fi

# Informations finales
echo ""
echo "🎉 Installation terminée!"
echo ""
echo "📍 Informations importantes:"
echo "   - Interface web: http://$(hostname -I | awk '{print $1}')"
echo "   - API: http://$(hostname -I | awk '{print $1}')/api/v1"
echo "   - Logs: journalctl -u domohub -f"
echo "   - Configuration: /srv/domohub/.env"
echo ""
echo "🔧 Commandes utiles:"
echo "   - Démarrer: systemctl start domohub"
echo "   - Arrêter: systemctl stop domohub"
echo "   - Redémarrer: systemctl restart domohub"
echo "   - Status: systemctl status domohub"
echo ""
echo "⚠️  N'oubliez pas de configurer le fichier .env avec vos paramètres!"
