"""
Middlewares de sécurité pour FastAPI
"""

import time
from typing import Callable

from fastapi import Request, Response
from fastapi.middleware.base import BaseHTTPMiddleware
from starlette.middleware.base import RequestResponseEndpoint

from src.core.logging import get_logger

logger = get_logger(__name__)


class SecurityHeadersMiddleware(BaseHTTPMiddleware):
    """Ajoute les headers de sécurité HTTP"""
    
    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        response = await call_next(request)
        
        # Headers de sécurité
        response.headers["X-Content-Type-Options"] = "nosniff"
        response.headers["X-Frame-Options"] = "DENY"
        response.headers["X-XSS-Protection"] = "1; mode=block"
        response.headers["Strict-Transport-Security"] = "max-age=31536000; includeSubDomains"
        response.headers["Content-Security-Policy"] = "default-src 'self'"
        response.headers["Referrer-Policy"] = "strict-origin-when-cross-origin"
        
        return response


class RateLimitMiddleware(BaseHTTPMiddleware):
    """Middleware de rate limiting simple"""
    
    def __init__(self, app, calls: int = 100, period: int = 60):
        super().__init__(app)
        self.calls = calls
        self.period = period
        self.clients = {}
    
    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        client_ip = request.client.host
        current_time = time.time()
        
        # Nettoyage des anciennes entrées
        if client_ip in self.clients:
            self.clients[client_ip] = [
                timestamp for timestamp in self.clients[client_ip]
                if current_time - timestamp < self.period
            ]
        else:
            self.clients[client_ip] = []
        
        # Vérification du rate limiting
        if len(self.clients[client_ip]) >= self.calls:
            logger.warning("rate_limit_exceeded", client_ip=client_ip)
            return Response(
                content="Rate limit exceeded",
                status_code=429,
                headers={"Retry-After": str(self.period)}
            )
        
        # Ajout de la requête actuelle
        self.clients[client_ip].append(current_time)
        
        response = await call_next(request)
        
        # Ajout des headers de rate limiting
        remaining = max(0, self.calls - len(self.clients[client_ip]))
        response.headers["X-RateLimit-Limit"] = str(self.calls)
        response.headers["X-RateLimit-Remaining"] = str(remaining)
        response.headers["X-RateLimit-Reset"] = str(int(current_time + self.period))
        
        return response


class AuditLogMiddleware(BaseHTTPMiddleware):
    """Middleware pour l'audit logging"""
    
    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        start_time = time.time()
        
        # Log de la requête
        logger.info(
            "request_started",
            method=request.method,
            url=str(request.url),
            client_ip=request.client.host,
            user_agent=request.headers.get("user-agent", "Unknown")
        )
        
        response = await call_next(request)
        
        # Calcul du temps de traitement
        process_time = time.time() - start_time
        
        # Log de la réponse
        logger.info(
            "request_completed",
            method=request.method,
            url=str(request.url),
            status_code=response.status_code,
            process_time=round(process_time, 4),
            client_ip=request.client.host
        )
        
        # Ajout du header de temps de traitement
        response.headers["X-Process-Time"] = str(round(process_time, 4))
        
        return response


class IPWhitelistMiddleware(BaseHTTPMiddleware):
    """Middleware pour whitelist d'IPs"""
    
    def __init__(self, app, allowed_ips: list = None):
        super().__init__(app)
        self.allowed_ips = allowed_ips or ["127.0.0.1", "::1"]
    
    async def dispatch(self, request: Request, call_next: RequestResponseEndpoint) -> Response:
        client_ip = request.client.host
        
        if client_ip not in self.allowed_ips:
            logger.warning("unauthorized_ip_access", client_ip=client_ip)
            return Response(
                content="Access denied",
                status_code=403
            )
        
        return await call_next(request)
