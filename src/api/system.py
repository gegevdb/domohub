"""
API système pour le monitoring et la configuration
"""

import psutil
from datetime import datetime
from typing import Any, Dict

from fastapi import APIRouter, Depends
from pydantic import BaseModel

from .auth import get_current_active_user, User
from ..core.config import settings
from ..core.logging import get_logger

logger = get_logger(__name__)
router = APIRouter()


class SystemInfo(BaseModel):
    hostname: str
    platform: str
    architecture: str
    cpu_count: int
    memory_total: int
    disk_total: int
    uptime: float


class SystemStatus(BaseModel):
    cpu_percent: float
    memory_percent: float
    disk_percent: float
    load_average: list
    temperature: float
    timestamp: datetime


class ServiceStatus(BaseModel):
    name: str
    status: str
    uptime: float
    last_restart: datetime


@router.get("/info", response_model=SystemInfo)
async def get_system_info(current_user: User = Depends(get_current_active_user)) -> Any:
    """Récupère les informations système"""
    logger.info("get_system_info", user=current_user.username)
    
    return SystemInfo(
        hostname=psutil.os.uname().nodename,
        platform=psutil.os.name,
        architecture=psutil.os.uname().machine,
        cpu_count=psutil.cpu_count(),
        memory_total=psutil.virtual_memory().total,
        disk_total=psutil.disk_usage('/').total,
        uptime=psutil.boot_time()
    )


@router.get("/demo/status", response_model=SystemStatus)
async def get_demo_system_status() -> Any:
    """Récupère le statut système (démo sans auth)"""
    
    # Statut simulé pour la démo
    return SystemStatus(
        cpu_percent=25.5,
        memory_percent=45.2,
        disk_percent=32.8,
        load_average=[0.5, 0.3, 0.2],
        temperature=42.5,
        timestamp=datetime.now()
    )


@router.get("/status", response_model=SystemStatus)
async def get_system_status(current_user: User = Depends(get_current_active_user)) -> Any:
    """Récupère le statut système en temps réel"""
    logger.info("get_system_status", user=current_user.username)
    
    # Récupération des métriques système
    cpu_percent = psutil.cpu_percent(interval=1)
    memory = psutil.virtual_memory()
    disk = psutil.disk_usage('/')
    
    # Load average (Linux/macOS seulement)
    try:
        load_avg = list(psutil.getloadavg())
    except AttributeError:
        load_avg = [0.0, 0.0, 0.0]
    
    # Température (si disponible)
    try:
        temps = psutil.sensors_temperatures()
        if temps:
            temp = list(temps.values())[0][0].current
        else:
            temp = 0.0
    except:
        temp = 0.0
    
    return SystemStatus(
        cpu_percent=cpu_percent,
        memory_percent=memory.percent,
        disk_percent=disk.percent,
        load_average=load_avg,
        temperature=temp,
        timestamp=datetime.now()
    )


@router.get("/services", response_model=list[ServiceStatus])
async def get_services_status(current_user: User = Depends(get_current_active_user)) -> Any:
    """Récupère le statut des services DomoHub"""
    logger.info("get_services_status", user=current_user.username)
    
    services = [
        ServiceStatus(
            name="api",
            status="running",
            uptime=3600.0,
            last_restart=datetime.now()
        ),
        ServiceStatus(
            name="mqtt",
            status="running",
            uptime=3600.0,
            last_restart=datetime.now()
        ),
        ServiceStatus(
            name="device_discovery",
            status="running",
            uptime=3600.0,
            last_restart=datetime.now()
        )
    ]
    
    return services


@router.get("/config")
async def get_system_config(current_user: User = Depends(get_current_active_user)) -> Any:
    """Récupère la configuration système (sans les secrets)"""
    logger.info("get_system_config", user=current_user.username)
    
    config = {
        "environment": settings.environment,
        "server": {
            "host": settings.server.host,
            "port": settings.server.port,
            "ssl_enabled": settings.server.ssl_enabled
        },
        "database": {
            "url": settings.database.url.split("://")[0] + "://***",  # Masquer le chemin
            "pool_size": settings.database.pool_size
        },
        "mqtt": {
            "broker_host": settings.mqtt.broker_host,
            "broker_port": settings.mqtt.broker_port,
            "keepalive": settings.mqtt.keepalive
        },
        "voice": {
            "enabled": settings.voice.enabled,
            "recognition_engine": settings.voice.recognition_engine,
            "language": settings.voice.language
        },
        "devices": {
            "discovery_enabled": settings.devices.discovery_enabled,
            "discovery_interval": settings.devices.discovery_interval
        },
        "monitoring": {
            "prometheus_enabled": settings.monitoring.prometheus_enabled,
            "health_check_interval": settings.monitoring.health_check_interval
        }
    }
    
    return config


@router.post("/restart")
async def restart_system(current_user: User = Depends(get_current_active_user)) -> Any:
    """Redémarre le système DomoHub"""
    logger.info("restart_system", user=current_user.username)
    
    # TODO: Implémenter le redémarrage propre du système
    # Pour l'instant, on simule une réponse
    
    return {
        "message": "System restart initiated",
        "timestamp": datetime.now(),
        "estimated_downtime": 30  # secondes
    }


@router.get("/logs")
async def get_system_logs(
    lines: int = 100,
    level: str = "INFO",
    current_user: User = Depends(get_current_active_user)
) -> Any:
    """Récupère les logs système"""
    logger.info("get_system_logs", user=current_user.username, lines=lines, level=level)
    
    # TODO: Implémenter la lecture des logs depuis le fichier
    # Pour l'instant, on simule des logs
    
    logs = [
        {
            "timestamp": "2024-01-01T12:00:00Z",
            "level": "INFO",
            "message": "DomoHub started successfully",
            "module": "main"
        },
        {
            "timestamp": "2024-01-01T12:01:00Z",
            "level": "INFO",
            "message": "MQTT client connected",
            "module": "mqtt"
        },
        {
            "timestamp": "2024-01-01T12:02:00Z",
            "level": "INFO",
            "message": "Device discovery started",
            "module": "devices"
        }
    ]
    
    return logs[-lines:]
