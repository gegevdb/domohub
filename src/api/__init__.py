"""
API REST de DomoHub
"""

from fastapi import APIRouter

from .auth import router as auth_router
from .devices import router as devices_router
from .system import router as system_router

# Router principal
api_router = APIRouter()

# Inclusion des sous-routers
api_router.include_router(auth_router, prefix="/auth", tags=["authentication"])
api_router.include_router(devices_router, prefix="/devices", tags=["devices"])
api_router.include_router(system_router, prefix="/system", tags=["system"])
