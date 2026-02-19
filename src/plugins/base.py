"""
Base classes for plugins system
"""

import asyncio
from abc import ABC, abstractmethod
from enum import Enum
from typing import Any, Dict, List, Optional, Union

from pydantic import BaseModel


class PluginType(str, Enum):
    """Types de plugins disponibles"""
    LIGHT = "light"
    SENSOR = "sensor"
    SWITCH = "switch"
    CLIMATE = "climate"
    SECURITY = "security"
    MULTIMEDIA = "multimedia"
    GATEWAY = "gateway"
    CUSTOM = "custom"


class PluginStatus(str, Enum):
    """Statuts possibles d'un plugin"""
    LOADED = "loaded"
    RUNNING = "running"
    STOPPED = "stopped"
    ERROR = "error"
    DISABLED = "disabled"


class DeviceCapability(str, Enum):
    """Capacités des dispositifs"""
    ON_OFF = "on_off"
    BRIGHTNESS = "brightness"
    COLOR = "color"
    TEMPERATURE = "temperature"
    HUMIDITY = "humidity"
    MOTION = "motion"
    DOOR_WINDOW = "door_window"
    VOLUME = "volume"
    CHANNEL = "channel"


class PluginInfo(BaseModel):
    """Informations sur un plugin"""
    name: str
    version: str
    description: str
    author: str
    plugin_type: PluginType
    supported_devices: List[str]
    capabilities: List[DeviceCapability]
    dependencies: List[str] = []
    config_schema: Dict[str, Any] = {}


class DeviceInfo(BaseModel):
    """Informations sur un dispositif"""
    id: str
    name: str
    manufacturer: Optional[str] = None
    model: Optional[str] = None
    firmware_version: Optional[str] = None
    capabilities: List[DeviceCapability]
    properties: Dict[str, Any] = {}
    room: Optional[str] = None


class DeviceState(BaseModel):
    """État d'un dispositif"""
    device_id: str
    properties: Dict[str, Any]
    last_updated: str
    online: bool = True


class BasePlugin(ABC):
    """Classe de base pour tous les plugins"""
    
    def __init__(self, config: Dict[str, Any] = None):
        self.config = config or {}
        self.status = PluginStatus.LOADED
        self.devices: Dict[str, DeviceInfo] = {}
        self.device_states: Dict[str, DeviceState] = {}
        self._event_handlers = []
        self._running = False
    
    @property
    @abstractmethod
    def info(self) -> PluginInfo:
        """Retourne les informations du plugin"""
        pass
    
    @abstractmethod
    async def initialize(self) -> bool:
        """Initialise le plugin"""
        pass
    
    @abstractmethod
    async def start(self) -> bool:
        """Démarre le plugin"""
        pass
    
    @abstractmethod
    async def stop(self) -> bool:
        """Arrête le plugin"""
        pass
    
    @abstractmethod
    async def discover_devices(self) -> List[DeviceInfo]:
        """Découvre les dispositifs compatibles"""
        pass
    
    async def add_device(self, device: DeviceInfo) -> bool:
        """Ajoute un dispositif au plugin"""
        try:
            self.devices[device.id] = device
            await self._emit_event("device_added", {"device": device.dict()})
            return True
        except Exception as e:
            await self._emit_event("error", {"error": str(e)})
            return False
    
    async def remove_device(self, device_id: str) -> bool:
        """Supprime un dispositif du plugin"""
        try:
            if device_id in self.devices:
                del self.devices[device_id]
                if device_id in self.device_states:
                    del self.device_states[device_id]
                await self._emit_event("device_removed", {"device_id": device_id})
                return True
            return False
        except Exception as e:
            await self._emit_event("error", {"error": str(e)})
            return False
    
    async def get_device_state(self, device_id: str) -> Optional[DeviceState]:
        """Récupère l'état d'un dispositif"""
        return self.device_states.get(device_id)
    
    async def update_device_state(self, device_id: str, properties: Dict[str, Any]) -> bool:
        """Met à jour l'état d'un dispositif"""
        try:
            if device_id not in self.devices:
                return False
            
            from datetime import datetime
            
            state = DeviceState(
                device_id=device_id,
                properties=properties,
                last_updated=datetime.utcnow().isoformat(),
                online=True
            )
            
            self.device_states[device_id] = state
            await self._emit_event("state_changed", {"state": state.dict()})
            return True
            
        except Exception as e:
            await self._emit_event("error", {"error": str(e)})
            return False
    
    async def execute_action(self, device_id: str, action: str, parameters: Dict[str, Any] = None) -> bool:
        """Exécute une action sur un dispositif"""
        if device_id not in self.devices:
            return False
        
        parameters = parameters or {}
        
        try:
            result = await self._execute_device_action(device_id, action, parameters)
            await self._emit_event("action_executed", {
                "device_id": device_id,
                "action": action,
                "parameters": parameters,
                "result": result
            })
            return result
        except Exception as e:
            await self._emit_event("error", {"error": str(e)})
            return False
    
    @abstractmethod
    async def _execute_device_action(self, device_id: str, action: str, parameters: Dict[str, Any]) -> bool:
        """Implémentation spécifique de l'exécution d'action"""
        pass
    
    def add_event_handler(self, handler):
        """Ajoute un handler d'événements"""
        self._event_handlers.append(handler)
    
    def remove_event_handler(self, handler):
        """Supprime un handler d'événements"""
        if handler in self._event_handlers:
            self._event_handlers.remove(handler)
    
    async def _emit_event(self, event_type: str, data: Dict[str, Any]):
        """Émet un événement à tous les handlers"""
        event = {
            "plugin": self.info.name,
            "type": event_type,
            "data": data,
            "timestamp": "2024-01-01T00:00:00Z"  # TODO: utiliser datetime.utcnow()
        }
        
        for handler in self._event_handlers:
            try:
                if asyncio.iscoroutinefunction(handler):
                    await handler(event)
                else:
                    handler(event)
            except Exception as e:
                # Log l'erreur mais continue avec les autres handlers
                pass
    
    async def health_check(self) -> Dict[str, Any]:
        """Vérification de santé du plugin"""
        return {
            "status": self.status,
            "devices_count": len(self.devices),
            "online_devices": len([d for d in self.device_states.values() if d.online]),
            "last_check": "2024-01-01T00:00:00Z"  # TODO: utiliser datetime.utcnow()
        }
    
    def get_config_schema(self) -> Dict[str, Any]:
        """Retourne le schéma de configuration du plugin"""
        return self.info.config_schema
    
    def validate_config(self, config: Dict[str, Any]) -> bool:
        """Valide la configuration du plugin"""
        # TODO: Implémenter la validation avec pydantic
        return True
