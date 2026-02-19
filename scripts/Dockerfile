# Dockerfile pour DomoHub
FROM python:3.11-slim

# Configuration de l'environnement
ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    DEBIAN_FRONTEND=noninteractive

# Installation des dépendances système
RUN apt-get update && apt-get install -y \
    gcc \
    g++ \
    sqlite3 \
    portaudio19-dev \
    python3-dev \
    libffi-dev \
    libssl-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Création de l'utilisateur domohub
RUN useradd -m -u 1000 domohub

# Répertoire de travail
WORKDIR /app

# Copie des fichiers requirements et installation des dépendances
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

# Copie du code source
COPY . .

# Création des répertoires nécessaires
RUN mkdir -p /app/data /app/logs /app/config && \
    chown -R domohub:domohub /app

# Configuration par défaut
COPY .env.example .env

# Changement de propriétaire
RUN chown domohub:domohub .env

# Utilisateur par défaut
USER domohub

# Exposition du port
EXPOSE 8000

# Commande de démarrage
CMD ["python", "-m", "src.main"]

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1
