"""
Module d'authentification et de sécurité avancée
"""

import secrets
from datetime import datetime, timedelta
from typing import Any, Dict, Optional, Union

import bcrypt
from jose import JWTError, jwt
from passlib.context import CryptContext

from src.core.config import settings
from src.core.logging import get_logger

logger = get_logger(__name__)

# Contexte de hachage avec configuration robuste
pwd_context = CryptContext(
    schemes=["bcrypt"],
    deprecated="auto",
    bcrypt__rounds=settings.security.bcrypt_rounds
)


class SecurityManager:
    """Gestionnaire de sécurité centralisé"""
    
    def __init__(self):
        self.secret_key = settings.security.secret_key
        self.algorithm = settings.security.algorithm
        self.access_token_expire_minutes = settings.security.access_token_expire_minutes
        self.refresh_token_expire_days = settings.security.refresh_token_expire_days
        
        # Blacklist de tokens (en production, utiliser Redis)
        self.token_blacklist = set()
        
        # Rate limiting simple (en production, utiliser Redis)
        self.failed_attempts = {}
        self.lockout_time = {}
    
    def hash_password(self, password: str) -> str:
        """Génère un hash sécurisé du mot de passe"""
        salt = bcrypt.gensalt(rounds=settings.security.bcrypt_rounds)
        hashed = bcrypt.hashpw(password.encode('utf-8'), salt)
        return hashed.decode('utf-8')
    
    def verify_password(self, plain_password: str, hashed_password: str) -> bool:
        """Vérifie un mot de passe contre son hash"""
        try:
            return bcrypt.checkpw(
                plain_password.encode('utf-8'),
                hashed_password.encode('utf-8')
            )
        except Exception as e:
            logger.error("password_verification_error", error=str(e))
            return False
    
    def is_account_locked(self, identifier: str) -> bool:
        """Vérifie si un compte est verrouillé"""
        if identifier in self.lockout_time:
            lockout_until = self.lockout_time[identifier]
            if datetime.now() < lockout_until:
                return True
            else:
                # Nettoyage du verrouillage expiré
                del self.lockout_time[identifier]
                if identifier in self.failed_attempts:
                    del self.failed_attempts[identifier]
        return False
    
    def record_failed_attempt(self, identifier: str) -> bool:
        """Enregistre une tentative de connexion échouée"""
        if identifier not in self.failed_attempts:
            self.failed_attempts[identifier] = 0
        
        self.failed_attempts[identifier] += 1
        
        # Verrouillage après 5 tentatives échouées
        if self.failed_attempts[identifier] >= 5:
            self.lockout_time[identifier] = datetime.now() + timedelta(minutes=15)
            logger.warning("account_locked", identifier=identifier, attempts=self.failed_attempts[identifier])
            return True
        
        return False
    
    def clear_failed_attempts(self, identifier: str) -> None:
        """Efface les tentatives échouées après connexion réussie"""
        if identifier in self.failed_attempts:
            del self.failed_attempts[identifier]
        if identifier in self.lockout_time:
            del self.lockout_time[identifier]
    
    def create_access_token(
        self, 
        data: Dict[str, Any], 
        expires_delta: Optional[timedelta] = None
    ) -> str:
        """Crée un token d'accès JWT"""
        to_encode = data.copy()
        
        if expires_delta:
            expire = datetime.utcnow() + expires_delta
        else:
            expire = datetime.utcnow() + timedelta(minutes=self.access_token_expire_minutes)
        
        to_encode.update({
            "exp": expire,
            "iat": datetime.utcnow(),
            "type": "access",
            "jti": secrets.token_urlsafe(32)  # JWT ID pour la révocation
        })
        
        encoded_jwt = jwt.encode(to_encode, self.secret_key, algorithm=self.algorithm)
        
        logger.info("access_token_created", subject=data.get("sub"), jti=to_encode["jti"])
        
        return encoded_jwt
    
    def create_refresh_token(self, data: Dict[str, Any]) -> str:
        """Crée un token de rafraîchissement JWT"""
        to_encode = data.copy()
        expire = datetime.utcnow() + timedelta(days=self.refresh_token_expire_days)
        
        to_encode.update({
            "exp": expire,
            "iat": datetime.utcnow(),
            "type": "refresh",
            "jti": secrets.token_urlsafe(32)
        })
        
        encoded_jwt = jwt.encode(to_encode, self.secret_key, algorithm=self.algorithm)
        
        logger.info("refresh_token_created", subject=data.get("sub"), jti=to_encode["jti"])
        
        return encoded_jwt
    
    def verify_token(self, token: str, token_type: str = "access") -> Optional[Dict[str, Any]]:
        """Vérifie et décode un token JWT"""
        try:
            payload = jwt.decode(token, self.secret_key, algorithms=[self.algorithm])
            
            # Vérification du type de token
            if payload.get("type") != token_type:
                logger.warning("invalid_token_type", expected=token_type, actual=payload.get("type"))
                return None
            
            # Vérification si le token est révoqué
            jti = payload.get("jti")
            if jti and jti in self.token_blacklist:
                logger.warning("token_blacklisted", jti=jti)
                return None
            
            # Vérification de l'expiration
            exp = payload.get("exp")
            if exp and datetime.utcnow() > datetime.fromtimestamp(exp):
                logger.warning("token_expired", jti=jti)
                return None
            
            return payload
            
        except JWTError as e:
            logger.warning("token_verification_failed", error=str(e))
            return None
    
    def revoke_token(self, token: str) -> bool:
        """Révoque un token (l'ajoute à la blacklist)"""
        try:
            payload = jwt.decode(token, self.secret_key, algorithms=[self.algorithm])
            jti = payload.get("jti")
            
            if jti:
                self.token_blacklist.add(jti)
                logger.info("token_revoked", jti=jti)
                return True
            
        except JWTError as e:
            logger.error("token_revocation_failed", error=str(e))
        
        return False
    
    def generate_secure_random_string(self, length: int = 32) -> str:
        """Génère une chaîne aléatoire sécurisée"""
        return secrets.token_urlsafe(length)
    
    def validate_api_key(self, api_key: str) -> bool:
        """Valide une clé API (à implémenter avec une vraie base de données)"""
        # TODO: Implémenter la validation des clés API
        # Pour l'instant, on accepte une clé de démonstration
        return api_key == "demo_api_key_12345"


# Instance globale du gestionnaire de sécurité
security_manager = SecurityManager()


# Fonctions de compatibilité avec l'API existante
def get_password_hash(password: str) -> str:
    """Génère un hash de mot de passe"""
    return security_manager.hash_password(password)


def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Vérifie un mot de passe"""
    return security_manager.verify_password(plain_password, hashed_password)


def create_access_token(data: Dict[str, Any], expires_delta: Optional[timedelta] = None) -> str:
    """Crée un token d'accès"""
    return security_manager.create_access_token(data, expires_delta)


def verify_token(token: str, token_type: str = "access") -> Optional[Dict[str, Any]]:
    """Vérifie un token"""
    return security_manager.verify_token(token, token_type)
