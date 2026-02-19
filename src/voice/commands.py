"""
Voice Command Processor
"""

import re
from typing import Any, Dict, List, Optional, Tuple

from src.core.logging import get_logger

logger = get_logger(__name__)


class CommandProcessor:
    """Processeur de commandes vocales"""
    
    def __init__(self):
        self.commands = {}
        self.aliases = {}
        self._register_builtin_commands()
    
    def _register_builtin_commands(self):
        """Enregistre les commandes intégrées"""
        
        # Commandes d'éclairage
        self.register_command(
            patterns=[
                r"allume(?: la)?\s*(.+)",
                r"allumer(?: la)?\s*(.+)",
                r"active(?: la)?\s*(.+)"
            ],
            action="turn_on",
            device_types=["light"],
            description="Allume un dispositif"
        )
        
        self.register_command(
            patterns=[
                r"éteins(?: la)?\s*(.+)",
                r"éteindre(?: la)?\s*(.+)",
                r"désactive(?: la)?\s*(.+)"
            ],
            action="turn_off",
            device_types=["light"],
            description="Éteint un dispositif"
        )
        
        # Commandes de luminosité
        self.register_command(
            patterns=[
                r"met(?: la)?\s*(.+)\s*à\s*(\d+)%?",
                r"règle(?: la)?\s*(.+)\s*à\s*(\d+)%?"
            ],
            action="set_brightness",
            device_types=["light"],
            description="Règle la luminosité"
        )
        
        # Commandes de couleur
        self.register_command(
            patterns=[
                r"met(?: la)?\s*(.+)\s*en\s*(\w+)",
                r"change(?: la)?\s*(.+)\s*en\s*(\w+)"
            ],
            action="set_color",
            device_types=["light"],
            description="Change la couleur"
        )
        
        # Commandes de température
        self.register_command(
            patterns=[
                r"met(?: le)?\s*(.+)\s*à\s*(\d+)\s*degrés?",
                r"règle(?: le)?\s*(.+)\s*à\s*(\d+)\s*degrés?"
            ],
            action="set_temperature",
            device_types=["climate"],
            description="Règle la température"
        )
        
        # Commandes de statut
        self.register_command(
            patterns=[
                r"quel(?:le)?\s*est\s*le\s*statut\s*de\s*(.+)",
                r"quel(?:le)?\s*est\s*l'état\s*de\s*(.+)",
                r"donne(?: moi)?\s*le\s*statut\s*de\s*(.+)"
            ],
            action="get_status",
            device_types=["all"],
            description="Demande le statut d'un dispositif"
        )
        
        # Commandes système
        self.register_command(
            patterns=[
                r"quel(?:le)?\s*est\s*la\s*température",
                r"quel(?:le)?\s*est\s*l'humidité",
                r"quel(le)?\s*temps\s*fait-il"
            ],
            action="get_weather",
            device_types=["sensor"],
            description="Demande les informations météo"
        )
        
        # Alias courants
        self.register_alias("lumière", "light")
        self.register_alias("lampe", "light")
        self.register_alias("chauffage", "climate")
        self.register_alias("thermostat", "climate")
        self.register_alias("capteur", "sensor")
    
    def register_command(
        self, 
        patterns: List[str], 
        action: str, 
        device_types: List[str],
        description: str = ""
    ):
        """Enregistre une nouvelle commande"""
        command_id = f"{action}_{len(self.commands)}"
        
        self.commands[command_id] = {
            "patterns": [re.compile(pattern, re.IGNORECASE) for pattern in patterns],
            "action": action,
            "device_types": device_types,
            "description": description
        }
        
        logger.info("command_registered", command_id=command_id, action=action)
    
    def register_alias(self, alias: str, device_type: str):
        """Enregistre un alias de type de dispositif"""
        self.aliases[alias.lower()] = device_type
        logger.info("alias_registered", alias=alias, device_type=device_type)
    
    async def process_command(self, text: str, devices: Dict[str, Any] = None) -> Optional[Dict[str, Any]]:
        """Traite une commande vocale"""
        text = text.strip().lower()
        
        logger.info("processing_command", text=text)
        
        # Recherche d'une commande correspondante
        for command_id, command in self.commands.items():
            for pattern in command["patterns"]:
                match = pattern.match(text)
                if match:
                    return await self._execute_command(
                        command_id, 
                        command, 
                        match, 
                        devices or {}
                    )
        
        # Aucune commande trouvée
        logger.warning("command_not_found", text=text)
        return {
            "success": False,
            "error": "Commande non reconnue",
            "text": text,
            "suggestions": await self._get_suggestions(text)
        }
    
    async def _execute_command(
        self, 
        command_id: str, 
        command: Dict[str, Any], 
        match: re.Match,
        devices: Dict[str, Any]
    ) -> Dict[str, Any]:
        """Exécute une commande reconnue"""
        
        action = command["action"]
        device_types = command["device_types"]
        
        # Extraction des paramètres
        groups = match.groups()
        
        if action in ["turn_on", "turn_off", "get_status"]:
            # Commandes avec nom de dispositif
            device_name = groups[0] if groups else None
            target_devices = await self._find_devices(device_name, device_types, devices)
            
            return {
                "success": True,
                "action": action,
                "devices": target_devices,
                "command_id": command_id,
                "parameters": {}
            }
        
        elif action == "set_brightness":
            device_name = groups[0] if groups else None
            brightness = int(groups[1]) if len(groups) > 1 else 50
            target_devices = await self._find_devices(device_name, device_types, devices)
            
            return {
                "success": True,
                "action": action,
                "devices": target_devices,
                "command_id": command_id,
                "parameters": {"brightness": min(100, max(0, brightness))}
            }
        
        elif action == "set_color":
            device_name = groups[0] if groups else None
            color = groups[1] if len(groups) > 1 else "blanc"
            target_devices = await self._find_devices(device_name, device_types, devices)
            
            return {
                "success": True,
                "action": action,
                "devices": target_devices,
                "command_id": command_id,
                "parameters": {"color": await self._normalize_color(color)}
            }
        
        elif action == "set_temperature":
            device_name = groups[0] if groups else None
            temperature = int(groups[1]) if len(groups) > 1 else 20
            target_devices = await self._find_devices(device_name, device_types, devices)
            
            return {
                "success": True,
                "action": action,
                "devices": target_devices,
                "command_id": command_id,
                "parameters": {"temperature": temperature}
            }
        
        elif action == "get_weather":
            # Commande météo
            return {
                "success": True,
                "action": action,
                "command_id": command_id,
                "parameters": {}
            }
        
        else:
            return {
                "success": False,
                "error": f"Action {action} non implémentée",
                "command_id": command_id
            }
    
    async def _find_devices(
        self, 
        device_name: Optional[str], 
        device_types: List[str],
        devices: Dict[str, Any]
    ) -> List[str]:
        """Trouve les dispositifs correspondants"""
        if not device_name:
            # Si pas de nom spécifié, retourne tous les dispositifs du type approprié
            return [
                device_id for device_id, device in devices.items()
                if device.get("device_type") in device_types or "all" in device_types
            ]
        
        # Recherche par nom
        target_devices = []
        device_name_lower = device_name.lower()
        
        for device_id, device in devices.items():
            device_type = device.get("device_type", "")
            device_name_from_db = device.get("name", "").lower()
            
            # Vérification du type et du nom
            if (device_type in device_types or "all" in device_types) and \
               (device_name_lower in device_name_from_db or 
                device_name_from_db in device_name_lower):
                target_devices.append(device_id)
        
        return target_devices
    
    async def _normalize_color(self, color: str) -> str:
        """Normalise le nom de couleur en code hexadécimal"""
        color_map = {
            "rouge": "#FF0000",
            "vert": "#00FF00",
            "bleu": "#0000FF",
            "jaune": "#FFFF00",
            "blanc": "#FFFFFF",
            "noir": "#000000",
            "orange": "#FFA500",
            "violet": "#800080",
            "rose": "#FFC0CB"
        }
        
        return color_map.get(color.lower(), "#FFFFFF")
    
    async def _get_suggestions(self, text: str) -> List[str]:
        """Génère des suggestions de commandes similaires"""
        suggestions = []
        
        # TODO: Implémenter un système de suggestions plus intelligent
        # Pour l'instant, suggestions basiques
        
        if "allume" in text or "allumer" in text:
            suggestions.extend([
                "allume la lumière du salon",
                "allume toutes les lumières",
                "allume la lampe de la chambre"
            ])
        
        elif "éteins" in text or "éteindre" in text:
            suggestions.extend([
                "éteins la lumière du salon",
                "éteins toutes les lumières",
                "éteins la lampe de la chambre"
            ])
        
        elif "température" in text:
            suggestions.extend([
                "quelle est la température",
                "met le chauffage à 21 degrés",
                "règle le thermostat à 20 degrés"
            ])
        
        return suggestions[:3]  # Limite à 3 suggestions
    
    def get_available_commands(self) -> List[Dict[str, Any]]:
        """Retourne la liste des commandes disponibles"""
        return [
            {
                "id": command_id,
                "action": command["action"],
                "device_types": command["device_types"],
                "description": command["description"],
                "patterns": [pattern.pattern for pattern in command["patterns"]]
            }
            for command_id, command in self.commands.items()
        ]
    
    def get_aliases(self) -> Dict[str, str]:
        """Retourne la liste des alias"""
        return self.aliases.copy()
