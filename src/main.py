"""
Point d'entrée principal de l'application DomoHub
"""

import asyncio
import signal
import sys
from contextlib import asynccontextmanager
from typing import AsyncGenerator

import uvicorn
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware

from .api import api_router
from .core.config import settings
from .core.database import init_db, close_db
from .core.logging import setup_logging, get_logger

# Configuration du logging
setup_logging()
logger = get_logger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI) -> AsyncGenerator[None, None]:
    """Gestion du cycle de vie de l'application"""
    
    # Démarrage
    logger.info("starting_domohub", version="1.0.0", environment=settings.environment)
    
    try:
        # Initialisation de la base de données
        await init_db()
        logger.info("database_initialized")
        
        # Démarrage des services de fond
        await start_background_services()
        logger.info("background_services_started")
        
        yield
        
    except Exception as e:
        logger.error("startup_error", error=str(e), error_type=type(e).__name__)
        raise
    
    finally:
        # Arrêt
        logger.info("shutting_down_domohub")
        await stop_background_services()
        await close_db()
        logger.info("domohub_shutdown_complete")


# Création de l'application FastAPI
app = FastAPI(
    title="DomoHub API",
    description="API REST pour le système domotique intelligent",
    version="1.0.0",
    docs_url="/docs" if settings.is_development else None,
    redoc_url="/redoc" if settings.is_development else None,
    lifespan=lifespan,
)

# Configuration des middlewares
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"] if settings.is_development else ["http://localhost:3000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.add_middleware(
    TrustedHostMiddleware,
    allowed_hosts=["*"] if settings.is_development else ["localhost", "127.0.0.1"]
)

# Inclusion des routes API
app.include_router(api_router, prefix="/api/v1")


@app.get("/")
async def root():
    """Endpoint racine pour vérifier que l'API fonctionne"""
    return {
        "message": "DomoHub API",
        "version": "1.0.0",
        "status": "running"
    }


@app.get("/health")
async def health_check():
    """Endpoint de health check pour le monitoring"""
    return {
        "status": "healthy",
        "timestamp": "2024-01-01T00:00:00Z",
        "version": "1.0.0"
    }


async def start_background_services():
    """Démarrage des services de fond"""
    # TODO: Implémenter les services de fond
    # - MQTT client
    # - Device discovery
    # - Voice recognition
    # - Monitoring
    pass


async def stop_background_services():
    """Arrêt des services de fond"""
    # TODO: Implémenter l'arrêt propre des services
    pass


def setup_signal_handlers():
    """Configuration des handlers de signaux pour l'arrêt propre"""
    
    def signal_handler(signum, frame):
        logger.info("signal_received", signal=signum)
        sys.exit(0)
    
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)


async def main():
    """Fonction principale de l'application"""
    setup_signal_handlers()
    
    # Configuration du serveur Uvicorn
    config = uvicorn.Config(
        app=app,
        host=settings.server.host,
        port=settings.server.port,
        log_level=settings.logging.level.lower(),
        access_log=True,
        use_colors=settings.is_development,
        reload=settings.is_development,
    )
    
    # Démarrage du serveur
    server = uvicorn.Server(config)
    
    try:
        await server.serve()
    except KeyboardInterrupt:
        logger.info("keyboard_interrupt_received")
    except Exception as e:
        logger.error("server_error", error=str(e), error_type=type(e).__name__)
        raise


if __name__ == "__main__":
    asyncio.run(main())
