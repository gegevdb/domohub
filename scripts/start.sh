#!/bin/bash

# Script de démarrage pour DomoHub
# Usage: ./start.sh [dev|prod]

set -e

MODE=${1:-dev}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🏠 Démarrage de DomoHub en mode $MODE"

# Vérification de l'environnement virtuel
if [ ! -d "$PROJECT_DIR/venv" ]; then
    echo "❌ Environnement virtuel non trouvé. Exécutez d'abord le script d'installation."
    exit 1
fi

# Activation de l'environnement virtuel
source "$PROJECT_DIR/venv/bin/activate"

# Configuration des variables d'environnement
export PYTHONPATH="$PROJECT_DIR:$PYTHONPATH"

if [ "$MODE" = "dev" ]; then
    echo "🔧 Mode développement"
    export ENVIRONMENT=development
    export SERVER_DEBUG=true
    export LOG_LEVEL=DEBUG
    
    # Démarrage avec rechargement automatique
    cd "$PROJECT_DIR"
    python -m uvicorn src.main:app --host 0.0.0.0 --port 8000 --reload --log-level debug
    
elif [ "$MODE" = "prod" ]; then
    echo "🚀 Mode production"
    export ENVIRONMENT=production
    export SERVER_DEBUG=false
    export LOG_LEVEL=INFO
    
    # Vérification du fichier .env
    if [ ! -f "$PROJECT_DIR/.env" ]; then
        echo "❌ Fichier .env non trouvé. Copiez .env.example vers .env et configurez-le."
        exit 1
    fi
    
    # Démarrage avec Uvicorn en mode production
    cd "$PROJECT_DIR"
    exec python -m uvicorn src.main:app \
        --host 0.0.0.0 \
        --port 8000 \
        --workers 4 \
        --log-level info \
        --access-log \
        --no-use-colors

else
    echo "❌ Mode invalide. Utilisez 'dev' ou 'prod'"
    exit 1
fi
