"""
Configuration du logging structuré avec structlog
"""

import logging
import logging.handlers
import sys
from pathlib import Path
from typing import Any, Dict

import structlog
from structlog.stdlib import LoggerFactory

from .config import settings


def setup_logging() -> None:
    """Configuration du logging structuré"""
    
    # Création du répertoire de logs si nécessaire
    log_path = Path(settings.logging.file_path)
    log_path.parent.mkdir(parents=True, exist_ok=True)
    
    # Configuration du formater
    if settings.logging.format.lower() == "json":
        formatter = structlog.stdlib.ProcessorFormatter(
            processor=structlog.processors.JSONRenderer(),
        )
    else:
        formatter = structlog.stdlib.ProcessorFormatter(
            processor=structlog.dev.ConsoleRenderer(colors=True),
        )
    
    # Configuration du handler de fichier avec rotation
    file_handler = logging.handlers.RotatingFileHandler(
        filename=settings.logging.file_path,
        maxBytes=_parse_size(settings.logging.max_size),
        backupCount=settings.logging.backup_count,
        encoding="utf-8",
    )
    file_handler.setFormatter(formatter)
    
    # Configuration du handler de console
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(formatter)
    
    # Configuration du logger racine
    logging.basicConfig(
        level=getattr(logging, settings.logging.level),
        handlers=[file_handler, console_handler],
    )
    
    # Configuration de structlog
    structlog.configure(
        processors=[
            structlog.stdlib.filter_by_level,
            structlog.stdlib.add_logger_name,
            structlog.stdlib.add_log_level,
            structlog.stdlib.PositionalArgumentsFormatter(),
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.StackInfoRenderer(),
            structlog.processors.format_exc_info,
            structlog.processors.UnicodeDecoder(),
            structlog.stdlib.ProcessorFormatter.wrap_for_formatter,
        ],
        context_class=dict,
        logger_factory=LoggerFactory(),
        wrapper_class=structlog.stdlib.BoundLogger,
        cache_logger_on_first_use=True,
    )


def _parse_size(size_str: str) -> int:
    """Convertit une chaîne de taille en octets"""
    size_str = size_str.upper()
    if size_str.endswith("KB"):
        return int(size_str[:-2]) * 1024
    elif size_str.endswith("MB"):
        return int(size_str[:-2]) * 1024 * 1024
    elif size_str.endswith("GB"):
        return int(size_str[:-2]) * 1024 * 1024 * 1024
    else:
        return int(size_str)


def get_logger(name: str = None) -> structlog.stdlib.BoundLogger:
    """Récupère un logger structuré"""
    return structlog.get_logger(name)


class LoggerMixin:
    """Mixin pour ajouter des capacités de logging aux classes"""
    
    @property
    def logger(self) -> structlog.stdlib.BoundLogger:
        """Logger pour cette classe"""
        return get_logger(self.__class__.__name__)
    
    def log_event(self, event: str, **kwargs: Any) -> None:
        """Log un événement structuré"""
        self.logger.info(event, **kwargs)
    
    def log_error(self, error: Exception, **kwargs: Any) -> None:
        """Log une erreur structurée"""
        self.logger.error(
            "error_occurred",
            error=str(error),
            error_type=type(error).__name__,
            **kwargs
        )
    
    def log_debug(self, message: str, **kwargs: Any) -> None:
        """Log un message de debug"""
        self.logger.debug(message, **kwargs)


# Logger global pour l'application
logger = get_logger("domohub")
