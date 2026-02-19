"""
API de gestion des dispositifs domotiques
"""

from typing import Any, List, Optional

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel

from .auth import get_current_active_user, User
from ..core.logging import get_logger

logger = get_logger(__name__)
router = APIRouter()


class DeviceBase(BaseModel):
    name: str
    device_type: str
    room: Optional[str] = None
    manufacturer: Optional[str] = None
    model: Optional[str] = None


class DeviceCreate(DeviceBase):
    pass


class DeviceUpdate(BaseModel):
    name: Optional[str] = None
    room: Optional[str] = None
    enabled: Optional[bool] = None


class Device(DeviceBase):
    id: str
    enabled: bool = True
    online: bool = False
    last_seen: Optional[str] = None
    properties: dict = {}
    
    class Config:
        from_attributes = True


class DeviceAction(BaseModel):
    action: str
    parameters: dict = {}


# Base de données devices en mémoire (à remplacer par une vraie DB)
fake_devices_db = {
    "light_001": {
        "id": "light_001",
        "name": "Lumière Salon",
        "device_type": "light",
        "room": "salon",
        "manufacturer": "Philips",
        "model": "Hue White",
        "enabled": True,
        "online": True,
        "last_seen": "2024-01-01T12:00:00Z",
        "properties": {"brightness": 80, "color": "#FFFFFF", "power": True}
    },
    "sensor_001": {
        "id": "sensor_001",
        "name": "Capteur Température",
        "device_type": "sensor",
        "room": "salon",
        "manufacturer": "Xiaomi",
        "model": "Mi Temperature",
        "enabled": True,
        "online": True,
        "last_seen": "2024-01-01T12:00:00Z",
        "properties": {"temperature": 22.5, "humidity": 45, "battery": 85}
    }
}


@router.get("/", response_model=List[Device])
async def get_devices(
    skip: int = 0,
    limit: int = 100,
    room: Optional[str] = None,
    device_type: Optional[str] = None,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Récupère la liste des dispositifs"""
    logger.info("get_devices", user=current_user.username, room=room, device_type=device_type)
    
    devices = list(fake_devices_db.values())
    
    # Filtrage
    if room:
        devices = [d for d in devices if d.get("room") == room]
    if device_type:
        devices = [d for d in devices if d.get("device_type") == device_type]
    
    # Pagination
    return devices[skip:skip + limit]


@router.get("/{device_id}", response_model=Device)
async def get_device(
    device_id: str,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Récupère un dispositif spécifique"""
    logger.info("get_device", user=current_user.username, device_id=device_id)
    
    device = fake_devices_db.get(device_id)
    if not device:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Device not found"
        )
    
    return device


@router.post("/", response_model=Device)
async def create_device(
    device: DeviceCreate,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Crée un nouveau dispositif"""
    logger.info("create_device", user=current_user.username, device_name=device.name)
    
    device_id = f"{device.device_type}_{len(fake_devices_db) + 1:03d}"
    
    device_data = {
        "id": device_id,
        **device.dict(),
        "enabled": True,
        "online": False,
        "last_seen": None,
        "properties": {}
    }
    
    fake_devices_db[device_id] = device_data
    
    return device_data


@router.put("/{device_id}", response_model=Device)
async def update_device(
    device_id: str,
    device_update: DeviceUpdate,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Met à jour un dispositif"""
    logger.info("update_device", user=current_user.username, device_id=device_id)
    
    device = fake_devices_db.get(device_id)
    if not device:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Device not found"
        )
    
    # Mise à jour des champs
    update_data = device_update.dict(exclude_unset=True)
    device.update(update_data)
    
    return device


@router.delete("/{device_id}")
async def delete_device(
    device_id: str,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Supprime un dispositif"""
    logger.info("delete_device", user=current_user.username, device_id=device_id)
    
    if device_id not in fake_devices_db:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Device not found"
        )
    
    del fake_devices_db[device_id]
    
    return {"message": "Device deleted successfully"}


@router.post("/{device_id}/actions")
async def execute_device_action(
    device_id: str,
    action: DeviceAction,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Exécute une action sur un dispositif"""
    logger.info("execute_action", user=current_user.username, device_id=device_id, action=action.action)
    
    device = fake_devices_db.get(device_id)
    if not device:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Device not found"
        )
    
    if not device.get("enabled"):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Device is disabled"
        )
    
    # TODO: Implémenter la logique d'exécution d'actions
    # Pour l'instant, on simule une réponse
    
    return {
        "message": f"Action '{action.action}' executed successfully",
        "device_id": device_id,
        "action": action.action,
        "parameters": action.parameters,
        "result": "success"
    }


@router.get("/{device_id}/status")
async def get_device_status(
    device_id: str,
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Récupère le statut détaillé d'un dispositif"""
    logger.info("get_device_status", user=current_user.username, device_id=device_id)
    
    device = fake_devices_db.get(device_id)
    if not device:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Device not found"
        )
    
    return {
        "device_id": device_id,
        "online": device.get("online", False),
        "last_seen": device.get("last_seen"),
        "properties": device.get("properties", {}),
        "battery_level": device.get("properties", {}).get("battery"),
        "signal_strength": device.get("properties", {}).get("signal_strength", -50)
    }
