"""
Voice Manager - Coordination of voice components
"""

import asyncio
from typing import Any, Dict, Optional

from .commands import CommandProcessor
from .recognizer import SpeechRecognizer
from .synthesizer import SpeechSynthesizer
from src.core.config import settings
from src.core.logging import get_logger

logger = get_logger(__name__)


class VoiceManager:
    """Gestionnaire principal du contrôle vocal"""
    
    def __init__(self):
        self.recognizer = SpeechRecognizer()
        self.synthesizer = SpeechSynthesizer()
        self.command_processor = CommandProcessor()
        self.is_initialized = False
        self.is_running = False
        self._devices = {}  # Cache des dispositifs disponibles
        
        # Configuration des callbacks
        self.recognizer.add_callback(self._on_speech_recognized)
    
    async def initialize(self) -> bool:
        """Initialise tous les composants vocaux"""
        try:
            logger.info("initializing_voice_manager")
            
            # Initialisation du recognizer
            if not await self.recognizer.initialize():
                logger.error("recognizer_initialization_failed")
                return False
            
            # Initialisation du synthesizer
            if not await self.synthesizer.initialize():
                logger.error("synthesizer_initialization_failed")
                return False
            
            self.is_initialized = True
            logger.info("voice_manager_initialized")
            
            # Message de bienvenue
            if settings.voice.enabled:
                await self.speak("DomoHub est prêt. Je vous écoute.")
            
            return True
            
        except Exception as e:
            logger.error("voice_manager_init_error", error=str(e))
            return False
    
    async def start(self) -> bool:
        """Démarre le gestionnaire vocal"""
        if not self.is_initialized:
            logger.error("voice_manager_not_initialized")
            return False
        
        if not settings.voice.enabled:
            logger.info("voice_control_disabled")
            return True
        
        try:
            # Démarrage de l'écoute
            await self.recognizer.start_listening()
            self.is_running = True
            
            logger.info("voice_manager_started")
            return True
            
        except Exception as e:
            logger.error("voice_manager_start_error", error=str(e))
            return False
    
    async def stop(self) -> bool:
        """Arrête le gestionnaire vocal"""
        try:
            await self.recognizer.stop_listening()
            self.is_running = False
            
            logger.info("voice_manager_stopped")
            return True
            
        except Exception as e:
            logger.error("voice_manager_stop_error", error=str(e))
            return False
    
    async def _on_speech_recognized(self, result: Dict[str, Any]):
        """Callback appelé quand la parole est reconnue"""
        try:
            text = result.get("text", "")
            confidence = result.get("confidence", 0)
            
            logger.info("speech_recognized", text=text, confidence=confidence)
            
            # Traitement de la commande
            command_result = await self.command_processor.process_command(text, self._devices)
            
            if command_result.get("success"):
                await self._execute_command(command_result)
            else:
                # Message d'erreur
                error_msg = command_result.get("error", "Je n'ai pas compris")
                await self.speak(error_msg)
                
                # Suggestions si disponibles
                suggestions = command_result.get("suggestions", [])
                if suggestions:
                    suggestion_text = "Essayez: " + ", ".join(suggestions[:2])
                    await self.speak(suggestion_text)
        
        except Exception as e:
            logger.error("speech_processing_error", error=str(e))
            await self.speak("Une erreur est survenue")
    
    async def _execute_command(self, command_result: Dict[str, Any]):
        """Exécute une commande vocale"""
        try:
            action = command_result.get("action")
            devices = command_result.get("devices", [])
            parameters = command_result.get("parameters", {})
            
            logger.info("executing_command", action=action, devices=devices, parameters=parameters)
            
            # TODO: Intégration avec le gestionnaire de dispositifs
            # Pour l'instant, simulation des réponses
            
            if action == "turn_on":
                if devices:
                    await self.speak(f"J'allume {len(devices)} dispositif(s)")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "turn_off":
                if devices:
                    await self.speak(f"J'éteins {len(devices)} dispositif(s)")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "set_brightness":
                brightness = parameters.get("brightness", 50)
                if devices:
                    await self.speak(f"Je règle la luminosité à {brightness}%")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "set_color":
                color = parameters.get("color", "#FFFFFF")
                if devices:
                    await self.speak(f"Je change la couleur")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "set_temperature":
                temperature = parameters.get("temperature", 20)
                if devices:
                    await self.speak(f"Je règle la température à {temperature} degrés")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "get_status":
                if devices:
                    await self.speak(f"Statut de {len(devices)} dispositif(s) demandé")
                else:
                    await self.speak("Aucun dispositif trouvé")
            
            elif action == "get_weather":
                await self.speak("La température actuelle est de 22 degrés avec 45% d'humidité")
            
            else:
                await self.speak(f"Commande {action} exécutée")
        
        except Exception as e:
            logger.error("command_execution_error", error=str(e))
            await self.speak("Erreur lors de l'exécution de la commande")
    
    async def speak(self, text: str, priority: bool = False) -> bool:
        """Fait parler l'assistant"""
        if not self.is_initialized:
            return False
        
        return await self.synthesizer.speak(text, priority)
    
    async def speak_immediately(self, text: str) -> bool:
        """Fait parler l'assistant immédiatement"""
        if not self.is_initialized:
            return False
        
        return await self.synthesizer.speak_immediately(text)
    
    def update_devices(self, devices: Dict[str, Any]):
        """Met à jour la liste des dispositifs disponibles"""
        self._devices = devices
        logger.info("devices_updated", count=len(devices))
    
    async def process_text_command(self, text: str) -> Dict[str, Any]:
        """Traite une commande textuelle (pour l'interface web)"""
        return await self.command_processor.process_command(text, self._devices)
    
    async def get_status(self) -> Dict[str, Any]:
        """Retourne le statut du gestionnaire vocal"""
        recognizer_status = await self.recognizer.get_status()
        synthesizer_status = await self.synthesizer.get_status()
        
        return {
            "initialized": self.is_initialized,
            "running": self.is_running,
            "enabled": settings.voice.enabled,
            "recognizer": recognizer_status,
            "synthesizer": synthesizer_status,
            "devices_count": len(self._devices),
            "available_commands": len(self.command_processor.get_available_commands())
        }
    
    async def get_commands_help(self) -> Dict[str, Any]:
        """Retourne l'aide sur les commandes disponibles"""
        return {
            "commands": self.command_processor.get_available_commands(),
            "aliases": self.command_processor.get_aliases(),
            "wake_word": settings.voice.wake_word,
            "language": settings.voice.language
        }
    
    async def set_wake_word(self, wake_word: str):
        """Change le wake word"""
        self.recognizer.set_wake_word(wake_word)
        logger.info("wake_word_changed", wake_word=wake_word)
    
    async def enable_wake_word(self, enabled: bool):
        """Active/désactive le wake word"""
        self.recognizer.enable_wake_word(enabled)
        logger.info("wake_word_toggled", enabled=enabled)
    
    async def test_recognition(self, text: str) -> Dict[str, Any]:
        """Teste la reconnaissance avec un texte donné"""
        result = await self.command_processor.process_command(text, self._devices)
        
        logger.info("recognition_test", text=text, result=result)
        return result
    
    async def shutdown(self):
        """Arrêt propre du gestionnaire vocal"""
        try:
            await self.stop()
            await self.synthesizer.shutdown()
            
            logger.info("voice_manager_shutdown")
            
        except Exception as e:
            logger.error("voice_manager_shutdown_error", error=str(e))
