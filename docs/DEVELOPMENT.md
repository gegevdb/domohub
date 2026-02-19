# Guide de Développement DomoHub

Ce guide explique comment contribuer au développement de DomoHub.

## Architecture du Projet

```
domohub/
├── src/                    # Code source principal
│   ├── core/             # Cœur du système
│   │   ├── config.py     # Configuration
│   │   ├── database.py   # Base de données
│   │   └── logging.py    # Logging
│   ├── api/              # API REST
│   │   ├── auth.py       # Authentification
│   │   ├── devices.py    # Gestion des dispositifs
│   │   └── system.py     # API système
│   ├── devices/          # Gestion des dispositifs
│   ├── plugins/          # Système de plugins
│   │   ├── base.py       # Classes de base
│   │   ├── manager.py    # Gestionnaire de plugins
│   │   └── examples/     # Plugins exemples
│   ├── voice/            # Contrôle vocal
│   │   ├── recognizer.py # Reconnaissance vocale
│   │   ├── synthesizer.py # Synthèse vocale
│   │   ├── commands.py   # Traitement des commandes
│   │   └── manager.py    # Gestionnaire vocal
│   └── security/         # Sécurité
│       ├── auth.py       # Authentification avancée
│       └── middleware.py # Middlewares de sécurité
├── web/                  # Interface web
│   ├── index.html       # Page principale
│   └── app.js          # JavaScript frontend
├── tests/               # Tests
├── scripts/            # Scripts de déploiement
└── docs/              # Documentation
```

## Configuration de l'Environnement de Développement

### 1. Prérequis

- Python 3.11+
- Node.js 16+ (pour le développement frontend)
- Git
- VS Code (recommandé)

### 2. Installation

```bash
# Clone du projet
git clone https://github.com/votre-repo/domohub.git
cd domohub

# Création de l'environnement virtuel
python3 -m venv venv
source venv/bin/activate  # Linux/macOS
# ou
.\venv\Scripts\Activate.ps1  # Windows

# Installation des dépendances
pip install --upgrade pip
pip install -r requirements.txt

# Installation des dépendances de développement
pip install -r requirements-dev.txt
```

### 3. Configuration

```bash
# Configuration de développement
cp .env.example .env.dev

# Édition du fichier
nano .env.dev
```

**Configuration de développement:**
```env
ENVIRONMENT=development
SERVER_DEBUG=true
LOG_LEVEL=DEBUG
DATABASE_URL=sqlite:///./dev_domohub.db
SECRET_KEY=dev_secret_key_not_for_production
```

### 4. Base de Données

```bash
# Initialisation de la base de données de développement
python -c "from src.core.database import init_db; import asyncio; asyncio.run(init_db())"
```

### 5. Démarrage

```bash
# Démarrage en mode développement
./scripts/start.sh dev
# ou
.\scripts\start.ps1 dev
```

L'API sera disponible sur http://localhost:8000

## Tests

### Structure des Tests

```
tests/
├── unit/                 # Tests unitaires
│   ├── test_config.py
│   ├── test_auth.py
│   └── test_plugins.py
├── integration/          # Tests d'intégration
│   ├── test_api.py
│   └── test_devices.py
└── e2e/                 # Tests end-to-end
    └── test_scenarios.py
```

### Exécution des Tests

```bash
# Tous les tests
pytest

# Tests unitaires uniquement
pytest tests/unit/

# Tests avec couverture
pytest --cov=src --cov-report=html

# Tests spécifiques
pytest tests/unit/test_auth.py -v

# Tests avec markers
pytest -m "not slow"
```

### Écriture de Tests

```python
# tests/unit/test_auth.py
import pytest
from src.security.auth import SecurityManager

class TestSecurityManager:
    def setup_method(self):
        self.security = SecurityManager()
    
    def test_password_hashing(self):
        password = "test_password"
        hashed = self.security.hash_password(password)
        
        assert self.security.verify_password(password, hashed)
        assert not self.security.verify_password("wrong_password", hashed)
    
    def test_token_creation(self):
        data = {"sub": "test_user"}
        token = self.security.create_access_token(data)
        
        payload = self.security.verify_token(token)
        assert payload["sub"] == "test_user"
```

## Développement de Plugins

### Structure d'un Plugin

```python
# src/plugins/my_plugin.py
from ..base import BasePlugin, PluginInfo, PluginType, DeviceCapability

class MyPlugin(BasePlugin):
    @property
    def info(self) -> PluginInfo:
        return PluginInfo(
            name="my_plugin",
            version="1.0.0",
            description="Mon plugin personnalisé",
            author="Your Name",
            plugin_type=PluginType.CUSTOM,
            supported_devices=["MyDevice"],
            capabilities=[DeviceCapability.ON_OFF],
            config_schema={
                "api_key": {"type": "string", "required": True}
            }
        )
    
    async def initialize(self) -> bool:
        # Initialisation du plugin
        self.api_key = self.config.get("api_key")
        return True
    
    async def start(self) -> bool:
        # Démarrage du plugin
        return True
    
    async def stop(self) -> bool:
        # Arrêt du plugin
        return True
    
    async def discover_devices(self) -> List[DeviceInfo]:
        # Découverte des dispositifs
        return []
    
    async def _execute_device_action(self, device_id: str, action: str, parameters: Dict[str, Any]) -> bool:
        # Exécution des actions
        return True
```

### Test d'un Plugin

```python
# tests/unit/test_my_plugin.py
import pytest
from src.plugins.my_plugin import MyPlugin

@pytest.mark.asyncio
async def test_my_plugin_initialization():
    plugin = MyPlugin({"api_key": "test_key"})
    
    assert await plugin.initialize()
    assert plugin.api_key == "test_key"
```

## Développement Frontend

### Structure

```
web/
├── index.html          # Page principale
├── app.js             # Application principale
├── components/        # Composants réutilisables
├── styles/           # Styles CSS
└── assets/           # Images, icônes
```

### Développement Local

```bash
# Installation des dépendances frontend (si utilisé)
npm install

# Serveur de développement
npm run dev
```

### Intégration avec l'API

```javascript
// web/api.js
class DomoHubAPI {
    constructor(baseURL = 'http://localhost:8000/api/v1') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('domohub_token');
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...(this.token && { 'Authorization': `Bearer ${this.token}` }),
            ...options.headers
        };
        
        const response = await fetch(url, { ...options, headers });
        
        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }
        
        return response.json();
    }
    
    async getDevices() {
        return this.request('/devices');
    }
    
    async executeDeviceAction(deviceId, action, parameters = {}) {
        return this.request(`/devices/${deviceId}/actions`, {
            method: 'POST',
            body: JSON.stringify({ action, parameters })
        });
    }
}
```

## Conventions de Code

### Python

- **Style**: PEP 8 avec Black
- **Type hints**: Obligatoires pour toutes les fonctions publiques
- **Docstrings**: Format Google Style

```python
def process_device_data(device_id: str, data: Dict[str, Any]) -> bool:
    """Traite les données d'un dispositif.
    
    Args:
        device_id: Identifiant du dispositif.
        data: Données à traiter.
        
    Returns:
        True si le traitement a réussi, False sinon.
        
    Raises:
        DeviceNotFoundError: Si le dispositif n'existe pas.
    """
    pass
```

### JavaScript

- **Style**: ESLint avec configuration Prettier
- **ES6+**: Utilisation des fonctionnalités modernes
- **Async/Await**: Préférez async/await aux Promises

```javascript
/**
 * Traite les données d'un dispositif
 * @param {string} deviceId - Identifiant du dispositif
 * @param {Object} data - Données à traiter
 * @returns {Promise<boolean>} - True si succès
 */
async function processDeviceData(deviceId, data) {
    try {
        const response = await api.post(`/devices/${deviceId}/data`, data);
        return response.success;
    } catch (error) {
        console.error('Error processing device data:', error);
        return false;
    }
}
```

## Git Workflow

### Branches

- `main`: Branche principale de production
- `develop`: Branche de développement
- `feature/*`: Nouvelles fonctionnalités
- `bugfix/*`: Corrections de bugs
- `hotfix/*`: Corrections urgentes en production

### Commit Messages

Format Conventional Commits:

```
type(scope): description

[optional body]

[optional footer]
```

**Types:**
- `feat`: Nouvelle fonctionnalité
- `fix`: Correction de bug
- `docs`: Documentation
- `style`: Style/formatage
- `refactor`: Refactoring
- `test`: Tests
- `chore`: Tâches de maintenance

**Exemples:**
```
feat(voice): add wake word detection

fix(auth): resolve token expiration issue

docs(api): update authentication documentation
```

### Pull Requests

1. Forker le projet
2. Créer une branche de fonctionnalité
3. Faire les changements avec des commits atomiques
4. Pousser la branche
5. Créer une Pull Request avec:
   - Description claire des changements
   - Tests ajoutés si nécessaire
   - Documentation mise à jour

## Debugging

### Logging

Utilisez le logging structuré:

```python
from src.core.logging import get_logger

logger = get_logger(__name__)

async def process_device(device_id: str):
    logger.info("processing_device", device_id=device_id)
    
    try:
        # Traitement
        logger.info("device_processed", device_id=device_id, success=True)
    except Exception as e:
        logger.error("device_processing_failed", device_id=device_id, error=str(e))
        raise
```

### Debugging avec VS Code

Configuration `.vscode/launch.json`:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Debug DomoHub",
            "type": "python",
            "request": "launch",
            "program": "${workspaceFolder}/src/main.py",
            "console": "integratedTerminal",
            "env": {
                "PYTHONPATH": "${workspaceFolder}"
            },
            "args": []
        }
    ]
}
```

### Tests en Mode Debug

```bash
# Debug avec pytest
pytest --pdb tests/unit/test_auth.py

# Debug avec VS Code
# Lancer la configuration "Debug Tests"
```

## Performance

### Monitoring

Utilisez les métriques intégrées:

```python
from prometheus_client import Counter, Histogram

REQUEST_COUNT = Counter('requests_total', 'Total requests', ['method', 'endpoint'])
REQUEST_DURATION = Histogram('request_duration_seconds', 'Request duration')

@app.middleware("http")
async def metrics_middleware(request: Request, call_next):
    start_time = time.time()
    
    response = await call_next(request)
    
    REQUEST_COUNT.labels(method=request.method, endpoint=request.url.path).inc()
    REQUEST_DURATION.observe(time.time() - start_time)
    
    return response
```

### Optimisation

- Utilisez `asyncio` pour les opérations I/O
- Implémentez le caching pour les requêtes fréquentes
- Utilisez des connexions persistantes (database, MQTT)
- Surveillez l'utilisation mémoire avec `memory_profiler`

## Déploiement

### Build de Production

```bash
# Tests complets
pytest --cov=src

# Formatage et linting
black src tests
flake8 src tests
mypy src

# Build Docker
docker build -t domohub:latest -f scripts/Dockerfile .
```

### CI/CD

Exemple de workflow GitHub Actions (`.github/workflows/ci.yml`):

```yaml
name: CI/CD

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        python-version: [3.11]
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Set up Python
      uses: actions/setup-python@v2
      with:
        python-version: ${{ matrix.python-version }}
    
    - name: Install dependencies
      run: |
        pip install --upgrade pip
        pip install -r requirements.txt
        pip install -r requirements-dev.txt
    
    - name: Run tests
      run: |
        pytest --cov=src --cov-report=xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v1
```

## Ressources

- **Documentation FastAPI**: https://fastapi.tiangolo.com/
- **Documentation SQLAlchemy**: https://docs.sqlalchemy.org/
- **Python Asyncio**: https://docs.python.org/3/library/asyncio.html
- **Prometheus Client**: https://github.com/prometheus/client_python
