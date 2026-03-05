#!/bin/bash

# =============================================================================
# SCRIPT DE MIGRATION DOMOGLASS PRO vers VM propre
# =============================================================================

set -e

echo "🚀 Migration DomoGlass Pro vers VM propre"
echo "================================================================"

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

# Configuration
SOURCE_DIR="/srv/dom-03"
BACKUP_DIR="/tmp/domoglass-backup-$(date +%Y%m%d-%H%M%S)"
TARGET_VM_IP=""  # À définir

echo "📁 Dossier source: $SOURCE_DIR"
echo "💾 Backup temporaire: $BACKUP_DIR"

# 1. Créer un backup propre
echo "🗜️ Création de l'archive propre..."
mkdir -p "$BACKUP_DIR"

# Copier uniquement les fichiers essentiels (exclure les logs, temp, node_modules)
echo "📋 Copie des fichiers essentiels..."
rsync -av --progress \
    --exclude='node_modules' \
    --exclude='*.log' \
    --exclude='logs/*' \
    --exclude='temp/*' \
    --exclude='cache/*' \
    --exclude='.git' \
    --exclude='tools/*' \
    --exclude='_verif-connexion.txt' \
    --exclude='*.tmp' \
    "$SOURCE_DIR/" "$BACKUP_DIR/dom-03/"

# Créer un script de restauration
echo "📜 Création du script de restauration..."
cat > "$BACKUP_DIR/restore.sh" << 'EOF'
#!/bin/bash

# Script de restauration DomoGlass Pro
set -e

echo "🚀 Restauration DomoGlass Pro"
echo "================================================================"

# Vérifier root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Ce script doit être exécuté en root (sudo)"
    exit 1
fi

# Dépendances de base
echo "📦 Installation des dépendances..."
apt update
apt install -y \
    apache2 \
    php8.1 php8.1-cli php8.1-sqlite3 php8.1-curl php8.1-json \
    sqlite3 \
    curl \
    git \
    nodejs \
    npm \
    mosquitto mosquitto-clients

# Configuration Apache
echo "🌐 Configuration Apache..."
cat > /etc/apache2/sites-available/domoglass.conf << 'APACHE'
<VirtualHost *:80>
    DocumentRoot /srv/dom-03
    ServerName domoglass.local
    
    <Directory /srv/dom-03>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/domoglass_error.log
    CustomLog ${APACHE_LOG_DIR}/domoglass_access.log combined
</VirtualHost>
APACHE

a2dissite 000-default
a2ensite domoglass
a2enmod rewrite
systemctl restart apache2

# Copier les fichiers
echo "📁 Installation des fichiers..."
cp -r dom-03 /srv/
chown -R www-data:www-data /srv/dom-03
chmod -R 755 /srv/dom-03

# Base de données
echo "🗄️ Configuration base de données..."
cd /srv/dom-03
sqlite3 domoglass.db < sql/schema.sql

# Services
echo "🔧 Configuration des services..."
systemctl enable mosquitto
systemctl start mosquitto

echo "✅ Installation terminée !"
echo "🌐 Accès: http://$(hostname -I | awk '{print $1}')/"
echo "👤 Créer un admin: php scripts/create_admin.php"
EOF

chmod +x "$BACKUP_DIR/restore.sh"

# Créer l'archive finale
echo "🗜️ Création de l'archive..."
cd "$BACKUP_DIR"
tar -czf "domoglass-pro-$(date +%Y%m%d).tar.gz" dom-03/ restore.sh

echo ""
echo "✅ Archive créée: $BACKUP_DIR/domoglass-pro-$(date +%Y%m%d).tar.gz"
echo ""
echo "📋 Étapes suivantes:"
echo "1. Transférer l'archive sur la nouvelle VM:"
echo "   scp $BACKUP_DIR/domoglass-pro-$(date +%Y%m%d).tar.gz user@new-vm:/tmp/"
echo ""
echo "2. Sur la nouvelle VM:"
echo "   cd /tmp"
echo "   tar -xzf domoglass-pro-$(date +%Y%m%d).tar.gz"
echo "   sudo ./restore.sh"
echo ""
echo "3. Recréer l'utilisateur admin:"
echo "   php scripts/create_admin.php"
echo ""
echo "🎯 Recommandation système pour la nouvelle VM:"
echo "   - Raspberry Pi OS Lite (64-bit)"
echo "   - 2GB RAM minimum"
echo "   - 16GB SD card"
echo ""
echo "✅ Migration préparée !"
