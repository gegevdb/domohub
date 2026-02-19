"""
Speech Synthesis Module
"""

import asyncio
from typing import Any, Dict, Optional

import pyttsx3

from src.core.config import settings
from src.core.logging import get_logger

logger = get_logger(__name__)


class SpeechSynthesizer:
    """Gestionnaire de synthèse vocale"""
    
    def __init__(self):
        self.engine = None
        self.is_initialized = False
        self.is_speaking = False
        self._speech_queue = asyncio.Queue()
        self._stop_event = asyncio.Event()
        self._voice_properties = {
            "rate": 200,  # mots par minute
            "volume": 0.9,  # 0.0 à 1.0
            "voice_id": None  # ID de la voix
        }
    
    async def initialize(self) -> bool:
        """Initialise le module de synthèse vocale"""
        try:
            # Initialisation dans un thread séparé car pyttsx3 est synchrone
            loop = asyncio.get_event_loop()
            self.engine = await loop.run_in_executor(None, pyttsx3.init)
            
            # Configuration des propriétés vocales
            await self._configure_voice()
            
            self.is_initialized = True
            logger.info("speech_synthesizer_initialized")
            
            # Démarrage du worker de parole
            asyncio.create_task(self._speech_worker())
            
            return True
            
        except Exception as e:
            logger.error("speech_synthesizer_init_error", error=str(e))
            return False
    
    async def _configure_voice(self):
        """Configure les propriétés vocales"""
        try:
            # Configuration du débit
            self.engine.setProperty('rate', self._voice_properties["rate"])
            
            # Configuration du volume
            self.engine.setProperty('volume', self._voice_properties["volume"])
            
            # Sélection de la voix appropriée pour la langue
            voices = self.engine.getProperty('voices')
            
            # Recherche d'une voix française
            french_voice = None
            for voice in voices:
                if 'french' in voice.name.lower() or 'fr' in voice.id.lower():
                    french_voice = voice
                    break
            
            # Utilisation de la voix française si trouvée, sinon la première
            if french_voice:
                self.engine.setProperty('voice', french_voice.id)
                self._voice_properties["voice_id"] = french_voice.id
                logger.info("french_voice_selected", voice=french_voice.name)
            elif voices:
                self.engine.setProperty('voice', voices[0].id)
                self._voice_properties["voice_id"] = voices[0].id
                logger.info("default_voice_selected", voice=voices[0].name)
            
        except Exception as e:
            logger.error("voice_configuration_error", error=str(e))
    
    async def speak(self, text: str, priority: bool = False) -> bool:
        """Ajoute un texte à la file d'attente de parole"""
        if not self.is_initialized:
            logger.warning("synthesizer_not_initialized")
            return False
        
        try:
            speech_item = {
                "text": text,
                "priority": priority,
                "timestamp": "2024-01-01T12:00:00Z"  # TODO: datetime réel
            }
            
            if priority:
                # Insertion en priorité dans la queue
                await self._speech_queue.put(speech_item)
            else:
                await self._speech_queue.put(speech_item)
            
            logger.info("speech_queued", text=text[:50], priority=priority)
            return True
            
        except Exception as e:
            logger.error("speech_queue_error", error=str(e))
            return False
    
    async def speak_immediately(self, text: str) -> bool:
        """Parle immédiatement le texte (interrompt la parole en cours)"""
        if not self.is_initialized:
            return False
        
        try:
            # Arrêt de la parole en cours
            if self.is_speaking:
                await self.stop()
            
            # Parole immédiate
            loop = asyncio.get_event_loop()
            await loop.run_in_executor(None, self.engine.say, text)
            await loop.run_in_executor(None, self.engine.runAndWait)
            
            logger.info("speech_immediate", text=text[:50])
            return True
            
        except Exception as e:
            logger.error("immediate_speech_error", error=str(e))
            return False
    
    async def _speech_worker(self):
        """Worker qui traite la file d'attente de parole"""
        while not self._stop_event.is_set():
            try:
                # Attente d'un élément à parler
                speech_item = await asyncio.wait_for(
                    self._speech_queue.get(), 
                    timeout=1.0
                )
                
                self.is_speaking = True
                logger.info("speaking", text=speech_item["text"][:50])
                
                # Parole dans un thread séparé
                loop = asyncio.get_event_loop()
                await loop.run_in_executor(
                    None, 
                    self._speak_sync, 
                    speech_item["text"]
                )
                
                self.is_speaking = False
                self._speech_queue.task_done()
                
            except asyncio.TimeoutError:
                # Timeout normal, continue
                continue
            except Exception as e:
                logger.error("speech_worker_error", error=str(e))
                self.is_speaking = False
    
    def _speak_sync(self, text: str):
        """Méthode synchrone de parole (exécutée dans un thread)"""
        try:
            self.engine.say(text)
            self.engine.runAndWait()
        except Exception as e:
            logger.error("sync_speech_error", error=str(e))
    
    async def stop(self):
        """Arrête la parole en cours et vide la file d'attente"""
        try:
            # Arrêt du moteur
            if self.engine:
                loop = asyncio.get_event_loop()
                await loop.run_in_executor(None, self.engine.stop)
            
            # Vidage de la file d'attente
            while not self._speech_queue.empty():
                self._speech_queue.get_nowait()
            
            self.is_speaking = False
            logger.info("speech_stopped")
            
        except Exception as e:
            logger.error("speech_stop_error", error=str(e))
    
    async def set_rate(self, rate: int):
        """Définit le débit de parole"""
        if 50 <= rate <= 400:
            self._voice_properties["rate"] = rate
            if self.engine:
                loop = asyncio.get_event_loop()
                await loop.run_in_executor(None, self.engine.setProperty, 'rate', rate)
            logger.info("speech_rate_changed", rate=rate)
    
    async def set_volume(self, volume: float):
        """Définit le volume (0.0 à 1.0)"""
        if 0.0 <= volume <= 1.0:
            self._voice_properties["volume"] = volume
            if self.engine:
                loop = asyncio.get_event_loop()
                await loop.run_in_executor(None, self.engine.setProperty, 'volume', volume)
            logger.info("speech_volume_changed", volume=volume)
    
    async def get_available_voices(self) -> list:
        """Retourne la liste des voix disponibles"""
        if not self.engine:
            return []
        
        try:
            loop = asyncio.get_event_loop()
            voices = await loop.run_in_executor(None, self.engine.getProperty, 'voices')
            
            return [
                {
                    "id": voice.id,
                    "name": voice.name,
                    "languages": voice.languages,
                    "gender": voice.gender
                }
                for voice in voices
            ]
            
        except Exception as e:
            logger.error("get_voices_error", error=str(e))
            return []
    
    async def set_voice(self, voice_id: str) -> bool:
        """Définit la voix à utiliser"""
        if not self.engine:
            return False
        
        try:
            loop = asyncio.get_event_loop()
            await loop.run_in_executor(None, self.engine.setProperty, 'voice', voice_id)
            self._voice_properties["voice_id"] = voice_id
            logger.info("voice_changed", voice_id=voice_id)
            return True
            
        except Exception as e:
            logger.error("set_voice_error", error=str(e))
            return False
    
    async def get_status(self) -> Dict[str, Any]:
        """Retourne le statut du module de synthèse"""
        return {
            "initialized": self.is_initialized,
            "speaking": self.is_speaking,
            "queue_size": self._speech_queue.qsize(),
            "voice_properties": self._voice_properties.copy(),
            "available_voices": len(await self.get_available_voices())
        }
    
    async def shutdown(self):
        """Arrêt propre du module"""
        self._stop_event.set()
        await self.stop()
        
        if self.engine:
            loop = asyncio.get_event_loop()
            await loop.run_in_executor(None, self.engine.stop)
        
        logger.info("speech_synthesizer_shutdown")
