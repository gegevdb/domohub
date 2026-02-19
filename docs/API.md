# Documentation API DomoHub

L'API REST de DomoHub permet de contrôler tous les aspects du système domotique.

## Base URL

```
http://votre-serveur:8000/api/v1
```

## Authentification

Toutes les requêtes (sauf `/token`) nécessitent un token JWT dans l'en-tête:

```
Authorization: Bearer <votre_token>
```

### Obtention d'un Token

```http
POST /auth/token
Content-Type: application/x-www-form-urlencoded

username=admin&password=admin123
```

**Réponse:**
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer"
}
```

### Rafraîchissement du Token

```http
POST /auth/refresh
Content-Type: application/json

{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

## Endpoints

### Authentification

#### POST /auth/token
Obtenir un token d'accès.

**Body:**
```json
{
  "username": "admin",
  "password": "admin123"
}
```

#### GET /auth/me
Obtenir les informations de l'utilisateur courant.

#### POST /auth/logout
Déconnexion de l'utilisateur.

### Dispositifs

#### GET /devices
Lister tous les dispositifs.

**Paramètres:**
- `skip` (int): Nombre de dispositifs à sauter (défaut: 0)
- `limit` (int): Nombre maximum de dispositifs (défaut: 100)
- `room` (string): Filtrer par pièce
- `device_type` (string): Filtrer par type (light, sensor, etc.)

**Réponse:**
```json
[
  {
    "id": "light_001",
    "name": "Lumière Salon",
    "device_type": "light",
    "room": "salon",
    "manufacturer": "Philips",
    "model": "Hue White",
    "enabled": true,
    "online": true,
    "last_seen": "2024-01-01T12:00:00Z",
    "properties": {
      "power": true,
      "brightness": 80,
      "color": "#FFFFFF"
    }
  }
]
```

#### GET /devices/{device_id}
Obtenir les détails d'un dispositif spécifique.

#### POST /devices
Créer un nouveau dispositif.

**Body:**
```json
{
  "name": "Nouvelle Lumière",
  "device_type": "light",
  "room": "chambre",
  "manufacturer": "IKEA",
  "model": "Trådfri"
}
```

#### PUT /devices/{device_id}
Mettre à jour un dispositif.

**Body:**
```json
{
  "name": "Lumière Salon Renommée",
  "room": "salon_principal"
}
```

#### DELETE /devices/{device_id}
Supprimer un dispositif.

#### POST /devices/{device_id}/actions
Exécuter une action sur un dispositif.

**Body:**
```json
{
  "action": "turn_on",
  "parameters": {
    "brightness": 75,
    "color": "#FF0000"
  }
}
```

**Actions possibles:**
- `turn_on`: Allumer le dispositif
- `turn_off`: Éteindre le dispositif
- `set_brightness`: Régler la luminosité (0-100)
- `set_color`: Changer la couleur (code hexadécimal)
- `set_temperature`: Régler la température (°C)

#### GET /devices/{device_id}/status
Obtenir le statut détaillé d'un dispositif.

**Réponse:**
```json
{
  "device_id": "light_001",
  "online": true,
  "last_seen": "2024-01-01T12:00:00Z",
  "properties": {
    "power": true,
    "brightness": 80,
    "color": "#FFFFFF"
  },
  "battery_level": null,
  "signal_strength": -45
}
```

### Système

#### GET /system/info
Obtenir les informations système.

**Réponse:**
```json
{
  "hostname": "domohub-pi",
  "platform": "linux",
  "architecture": "armv7l",
  "cpu_count": 4,
  "memory_total": 1073741824,
  "disk_total": 32212254720,
  "uptime": 123456
}
```

#### GET /system/status
Obtenir le statut système en temps réel.

**Réponse:**
```json
{
  "cpu_percent": 15.2,
  "memory_percent": 45.8,
  "disk_percent": 32.1,
  "load_average": [0.5, 0.3, 0.2],
  "temperature": 42.5,
  "timestamp": "2024-01-01T12:00:00Z"
}
```

#### GET /system/services
Obtenir le statut des services DomoHub.

**Réponse:**
```json
[
  {
    "name": "api",
    "status": "running",
    "uptime": 3600.0,
    "last_restart": "2024-01-01T11:00:00Z"
  },
  {
    "name": "mqtt",
    "status": "running",
    "uptime": 3600.0,
    "last_restart": "2024-01-01T11:00:00Z"
  }
]
```

#### GET /system/config
Obtenir la configuration système (sans les secrets).

#### POST /system/restart
Redémarrer le système DomoHub.

#### GET /system/logs
Obtenir les logs système.

**Paramètres:**
- `lines` (int): Nombre de lignes (défaut: 100)
- `level` (string): Niveau de log (DEBUG, INFO, WARNING, ERROR)

### Contrôle Vocal

#### GET /voice/status
Obtenir le statut du système vocal.

**Réponse:**
```json
{
  "initialized": true,
  "running": true,
  "enabled": true,
  "recognizer": {
    "listening": true,
    "wake_word_enabled": true,
    "wake_word": "domohub"
  },
  "synthesizer": {
    "speaking": false,
    "queue_size": 0
  }
}
```

#### POST /voice/speak
Faire parler l'assistant.

**Body:**
```json
{
  "text": "Bonjour, comment allez-vous ?",
  "priority": false
}
```

#### POST /voice/command
Traiter une commande textuelle.

**Body:**
```json
{
  "text": "Allume la lumière du salon"
}
```

**Réponse:**
```json
{
  "success": true,
  "action": "turn_on",
  "devices": ["light_001"],
  "parameters": {}
}
```

#### GET /voice/commands
Obtenir la liste des commandes vocales disponibles.

#### POST /voice/test
Tester la reconnaissance vocale.

## WebSocket

### Connexion

```
ws://votre-serveur:8000/ws
```

### Messages

#### Client → Serveur

**Authentification:**
```json
{
  "type": "auth",
  "token": "votre_jwt_token"
}
```

**Souscription aux événements:**
```json
{
  "type": "subscribe",
  "events": ["device_state_changed", "system_status"]
}
```

#### Serveur → Client

**Changement d'état de dispositif:**
```json
{
  "type": "device_state_changed",
  "data": {
    "device_id": "light_001",
    "properties": {
      "power": true,
      "brightness": 80
    },
    "timestamp": "2024-01-01T12:00:00Z"
  }
}
```

**Statut système:**
```json
{
  "type": "system_status",
  "data": {
    "cpu_percent": 15.2,
    "memory_percent": 45.8,
    "timestamp": "2024-01-01T12:00:00Z"
  }
}
```

## Erreurs

### Codes d'Erreur HTTP

- `200`: Succès
- `201`: Créé
- `400`: Requête invalide
- `401`: Non authentifié
- `403`: Accès refusé
- `404`: Non trouvé
- `422`: Erreur de validation
- `500`: Erreur serveur

### Format d'Erreur

```json
{
  "error": {
    "code": "DEVICE_NOT_FOUND",
    "message": "Dispositif non trouvé",
    "details": {
      "device_id": "invalid_id"
    }
  }
}
```

## Limites de Rate Limiting

- **100 requêtes/minute** par IP
- **1000 requêtes/heure** par utilisateur authentifié

Les en-têtes de rate limiting sont inclus dans chaque réponse:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

## Exemples de Code

### Python

```python
import requests

# Authentification
response = requests.post(
    "http://localhost:8000/api/v1/auth/token",
    data={"username": "admin", "password": "admin123"}
)
token = response.json()["access_token"]

headers = {"Authorization": f"Bearer {token}"}

# Lister les dispositifs
response = requests.get(
    "http://localhost:8000/api/v1/devices",
    headers=headers
)
devices = response.json()

# Allumer une lumière
response = requests.post(
    "http://localhost:8000/api/v1/devices/light_001/actions",
    headers=headers,
    json={"action": "turn_on", "parameters": {"brightness": 80}}
)
```

### JavaScript

```javascript
// Authentification
const authResponse = await fetch('/api/v1/auth/token', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ username: 'admin', password: 'admin123' })
});
const { access_token } = await authResponse.json();

// Lister les dispositifs
const devicesResponse = await fetch('/api/v1/devices', {
  headers: { 'Authorization': `Bearer ${access_token}` }
});
const devices = await devicesResponse.json();

// Allumer une lumière
await fetch('/api/v1/devices/light_001/actions', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${access_token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ action: 'turn_on', parameters: { brightness: 80 } })
});
```

### cURL

```bash
# Authentification
TOKEN=$(curl -X POST http://localhost:8000/api/v1/auth/token \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  | jq -r '.access_token')

# Lister les dispositifs
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/v1/devices

# Allumer une lumière
curl -X POST http://localhost:8000/api/v1/devices/light_001/actions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"action":"turn_on","parameters":{"brightness":80}}'
```
