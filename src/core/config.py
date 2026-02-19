"""
Configuration centralisée avec Pydantic Settings
"""

import os
from pathlib import Path
from typing import List, Optional

from pydantic import Field, validator
from pydantic_settings import BaseSettings


class ServerConfig(BaseSettings):
    host: str = "0.0.0.0"
    port: int = 8000
    debug: bool = False
    ssl_enabled: bool = True
    ssl_cert_path: Optional[str] = None
    ssl_key_path: Optional[str] = None


class DatabaseConfig(BaseSettings):
    url: str = "sqlite:///./domohub.db"
    echo: bool = False
    pool_size: int = 10
    max_overflow: int = 20


class SecurityConfig(BaseSettings):
    secret_key: str = Field(..., min_length=32)
    algorithm: str = "HS256"
    access_token_expire_minutes: int = 30
    refresh_token_expire_days: int = 7
    bcrypt_rounds: int = 12


class MQTTConfig(BaseSettings):
    broker_host: str = "localhost"
    broker_port: int = 1883
    username: str = ""
    password: str = ""
    keepalive: int = 60
    qos: int = 1


class VoiceConfig(BaseSettings):
    enabled: bool = True
    recognition_engine: str = "google"
    synthesis_engine: str = "pyttsx3"
    language: str = "fr-FR"
    wake_word: str = "domohub"
    audio_device: str = "default"


class LoggingConfig(BaseSettings):
    level: str = "INFO"
    format: str = "json"
    file_path: str = "./logs/domohub.log"
    max_size: str = "10MB"
    backup_count: int = 5

    @validator("level")
    def validate_log_level(cls, v):
        valid_levels = ["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"]
        if v.upper() not in valid_levels:
            raise ValueError(f"Log level must be one of {valid_levels}")
        return v.upper()


class MonitoringConfig(BaseSettings):
    prometheus_enabled: bool = True
    prometheus_port: int = 9090
    health_check_interval: int = 30


class DevicesConfig(BaseSettings):
    discovery_enabled: bool = True
    discovery_interval: int = 300
    auto_add_devices: bool = False


class PluginsConfig(BaseSettings):
    enabled: bool = True
    auto_load: bool = True
    directories: List[str] = ["./src/plugins", "./plugins"]


class NotificationsConfig(BaseSettings):
    email_enabled: bool = False
    email_smtp_server: str = ""
    email_smtp_port: int = 587
    email_username: str = ""
    email_password: str = ""


class PerformanceConfig(BaseSettings):
    max_concurrent_requests: int = 100
    request_timeout: int = 30
    websocket_ping_interval: int = 20


class Settings(BaseSettings):
    """Configuration principale de l'application"""
    
    # Environment
    environment: str = "development"
    
    # Sub-configurations
    server: ServerConfig = ServerConfig()
    database: DatabaseConfig = DatabaseConfig()
    security: SecurityConfig = SecurityConfig(secret_key=os.urandom(32).hex())
    mqtt: MQTTConfig = MQTTConfig()
    voice: VoiceConfig = VoiceConfig()
    logging: LoggingConfig = LoggingConfig()
    monitoring: MonitoringConfig = MonitoringConfig()
    devices: DevicesConfig = DevicesConfig()
    plugins: PluginsConfig = PluginsConfig()
    notifications: NotificationsConfig = NotificationsConfig()
    performance: PerformanceConfig = PerformanceConfig()

    class Config:
        env_file = ".env"
        env_nested_delimiter = "__"
        case_sensitive = False

    @property
    def is_production(self) -> bool:
        return self.environment.lower() == "production"

    @property
    def is_development(self) -> bool:
        return self.environment.lower() == "development"


# Instance globale de configuration
settings = Settings()
