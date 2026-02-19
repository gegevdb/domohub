"""
Configuration de la base de données avec SQLAlchemy async
"""

from sqlalchemy import create_engine, MetaData
from sqlalchemy.ext.asyncio import AsyncSession, create_async_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker

from .config import settings

# Configuration du moteur de base de données
if settings.database.url.startswith("sqlite"):
    # Pour SQLite, on utilise le driver aiosqlite
    async_db_url = settings.database.url.replace("sqlite:///", "sqlite+aiosqlite:///")
else:
    async_db_url = settings.database.url

# Moteur asynchrone
async_engine = create_async_engine(
    async_db_url,
    echo=settings.database.echo,
)

# Moteur synchrone (pour les migrations Alembic)
sync_engine = create_engine(
    settings.database.url,
    echo=settings.database.echo,
)

# Session asynchrone (SQLAlchemy 1.4 compatible)
AsyncSessionLocal = sessionmaker(
    async_engine,
    class_=AsyncSession,
    expire_on_commit=False,
)

# Session synchrone
SessionLocal = sessionmaker(
    autocommit=False,
    autoflush=False,
    bind=sync_engine,
)

# Base pour les modèles
Base = declarative_base()

# Metadata pour les migrations
metadata = MetaData()


async def get_async_session() -> AsyncSession:
    """Dependency injection pour les sessions asynchrones"""
    async with AsyncSessionLocal() as session:
        try:
            yield session
        finally:
            await session.close()


def get_sync_session():
    """Dependency injection pour les sessions synchrones"""
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


async def init_db():
    """Initialisation de la base de données"""
    async with async_engine.begin() as conn:
        # Création des tables
        await conn.run_sync(Base.metadata.create_all)


async def close_db():
    """Fermeture des connexions à la base de données"""
    await async_engine.dispose()
