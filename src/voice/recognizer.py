"""
Speech Recognition Module
"""

import asyncio
import queue
import threading
from typing import Any, Dict, Optional

import speech_recognition as sr
import webrtcvad

from src.core.config import settings
from src.core.logging import get_logger

logger = get_logger(__name__)


class SpeechRecognizer:
    """Gestionnaire de reconnaissance vocale"""
    
    def __init__(self):
        self.recognizer = sr.Recognizer()
        self.microphone = None
        self.vad = webrtcvad.Vad(2)  # Niveau de sensibilité moyen
        self.is_listening = False
        self.wake_word_enabled = True
        self.wake_word = settings.voice.wake_word.lower()
        self._audio_queue = queue.Queue()
        self._stop_event = threading.Event()
        self._callbacks = []
        
        # Configuration du recognizer
        self.recognizer.dynamic_energy_threshold = True
        self.recognizer.energy_threshold = 300
        self.recognizer.pause_threshold = 0.8
        self.recognizer.phrase_threshold = 0.3
        self.recognizer.non_speaking_duration = 0.5
    
    async def initialize(self) -> bool:
        """Initialise le module de reconnaissance vocale"""
        try:
            # Détection des microphones disponibles
            microphones = sr.Microphone.list_microphone_names()
            
            if not microphones:
                logger.error("no_microphone_found")
                return False
            
            # Utilisation du microphone par défaut
            self.microphone = sr.Microphone()
            
            # Calibration du microphone
            with self.microphone as source:
                logger.info("calibrating_microphone")
                self.recognizer.adjust_for_ambient_noise(source, duration=2)
            
            logger.info("speech_recognizer_initialized", microphones_count=len(microphones))
            return True
            
        except Exception as e:
            logger.error("speech_recognizer_init_error", error=str(e))
            return False
    
    def add_callback(self, callback):
        """Ajoute un callback pour les résultats de reconnaissance"""
        self._callbacks.append(callback)
    
    def remove_callback(self, callback):
        """Supprime un callback"""
        if callback in self._callbacks:
            self._callbacks.remove(callback)
    
    async def start_listening(self):
        """Démarre l'écoute en continu"""
        if self.is_listening:
            return
        
        self.is_listening = True
        self._stop_event.clear()
        
        logger.info("start_listening", wake_word=self.wake_word)
        
        # Démarrage du thread d'écoute
        listen_thread = threading.Thread(target=self._listen_loop)
        listen_thread.daemon = True
        listen_thread.start()
    
    async def stop_listening(self):
        """Arrête l'écoute"""
        self.is_listening = False
        self._stop_event.set()
        logger.info("stop_listening")
    
    def _listen_loop(self):
        """Boucle d'écoute dans un thread séparé"""
        with self.microphone as source:
            while not self._stop_event.is_set() and self.is_listening:
                try:
                    # Écoute avec wake word
                    if self.wake_word_enabled:
                        self._listen_for_wake_word(source)
                    else:
                        self._listen_for_command(source)
                        
                except Exception as e:
                    logger.error("listen_loop_error", error=str(e))
                    asyncio.sleep(1)
    
    def _listen_for_wake_word(self, source):
        """Écoute le wake word"""
        # TODO: Implémenter la détection du wake word avec un modèle ML
        # Pour l'instant, on utilise une détection simple basée sur le volume
        
        logger.debug("listening_for_wake_word")
        
        # Écoute en continu
        audio = self.recognizer.listen(source, timeout=1, phrase_time_limit=3)
        
        try:
            # Reconnaissance du wake word
            text = self.recognizer.recognize_google(
                audio, 
                language=settings.voice.language
            ).lower()
            
            if self.wake_word in text:
                logger.info("wake_word_detected", text=text)
                asyncio.create_task(self._on_wake_word_detected())
                
        except sr.UnknownValueError:
            # Pas de parole détectée, normal
            pass
        except sr.RequestError as e:
            logger.error("speech_recognition_error", error=str(e))
    
    def _listen_for_command(self, source):
        """Écoute une commande directement"""
        logger.debug("listening_for_command")
        
        audio = self.recognizer.listen(source, timeout=5, phrase_time_limit=10)
        
        try:
            text = self.recognizer.recognize_google(
                audio,
                language=settings.voice.language
            )
            
            logger.info("command_recognized", text=text)
            asyncio.create_task(self._on_command_recognized(text))
            
        except sr.UnknownValueError:
            logger.debug("speech_not_understood")
        except sr.RequestError as e:
            logger.error("speech_recognition_error", error=str(e))
    
    async def _on_wake_word_detected(self):
        """Appelé quand le wake word est détecté"""
        # Confirmation sonore
        await self._play_acknowledgment()
        
        # Écoute de la commande
        await self._listen_single_command()
    
    async def _listen_single_command(self):
        """Écoute une seule commande après le wake word"""
        try:
            with self.microphone as source:
                audio = self.recognizer.listen(source, timeout=5, phrase_time_limit=10)
                
                text = self.recognizer.recognize_google(
                    audio,
                    language=settings.voice.language
                )
                
                logger.info("command_after_wake_word", text=text)
                await self._on_command_recognized(text)
                
        except sr.UnknownValueError:
            logger.info("no_command_after_wake_word")
            await self._play_error_sound()
        except sr.RequestError as e:
            logger.error("command_recognition_error", error=str(e))
            await self._play_error_sound()
    
    async def _on_command_recognized(self, text: str):
        """Traite le texte reconnu"""
        result = {
            "text": text,
            "confidence": 0.8,  # TODO: Obtenir la vraie confiance
            "timestamp": "2024-01-01T12:00:00Z"  # TODO: datetime réel
        }
        
        # Notification des callbacks
        for callback in self._callbacks:
            try:
                if asyncio.iscoroutinefunction(callback):
                    await callback(result)
                else:
                    callback(result)
            except Exception as e:
                logger.error("callback_error", error=str(e))
    
    async def _play_acknowledgment(self):
        """Joue un son de confirmation"""
        # TODO: Implémenter la lecture d'un son court
        logger.debug("play_acknowledgment")
    
    async def _play_error_sound(self):
        """Joue un son d'erreur"""
        # TODO: Implémenter la lecture d'un son d'erreur
        logger.debug("play_error_sound")
    
    async def recognize_from_file(self, file_path: str) -> Optional[Dict[str, Any]]:
        """Reconnaît la parole depuis un fichier audio"""
        try:
            with sr.AudioFile(file_path) as source:
                audio = self.recognizer.record(source)
                
                text = self.recognizer.recognize_google(
                    audio,
                    language=settings.voice.language
                )
                
                return {
                    "text": text,
                    "confidence": 0.8,
                    "timestamp": "2024-01-01T12:00:00Z"
                }
                
        except Exception as e:
            logger.error("file_recognition_error", error=str(e), file_path=file_path)
            return None
    
    def set_wake_word(self, wake_word: str):
        """Définit le wake word"""
        self.wake_word = wake_word.lower()
        logger.info("wake_word_changed", wake_word=self.wake_word)
    
    def enable_wake_word(self, enabled: bool = True):
        """Active/désactive le wake word"""
        self.wake_word_enabled = enabled
        logger.info("wake_word_toggled", enabled=enabled)
    
    async def get_status(self) -> Dict[str, Any]:
        """Retourne le statut du module de reconnaissance"""
        return {
            "initialized": self.microphone is not None,
            "listening": self.is_listening,
            "wake_word_enabled": self.wake_word_enabled,
            "wake_word": self.wake_word,
            "microphone_count": len(sr.Microphone.list_microphone_names()),
            "energy_threshold": self.recognizer.energy_threshold
        }
