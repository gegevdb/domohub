"""
Plugin Manager for DomoHub
"""

import asyncio
import importlib
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional, Type

from .base import BasePlugin, PluginInfo, PluginStatus, DeviceInfo

# Import des plugins exemples
from .examples.philips_hue import PhilipsHuePlugin
from .examples.xiaomi_sensors import XiaomiSensorsPlugin


class PluginManager:
    """Gestionnaire de plugins pour DomoHub"""
    
    def __init__(self, plugin_directories: List[str] = None):
        self.plugin_directories = plugin_directories or ["./src/plugins"]
        self.plugins: Dict[str, BasePlugin] = {}
        self.plugin_classes: Dict[str, Type[BasePlugin]] = {}
        self._event_handlers = []
        self._running = False
        
        # Enregistrement des plugins intégrés
        self._register_builtin_plugins()
    
    def _register_builtin_plugins(self):
        """Enregistre les plugins intégrés"""
        self.plugin_classes.update({
            "philips_hue": PhilipsHuePlugin,
            "xiaomi_sensors": XiaomiSensorsPlugin,
        })
    
    async def initialize(self) -> bool:
        """Initialise le gestionnaire de plugins"""
        try:
            # Découverte des plugins dans les répertoires
            await self._discover_plugins()
            
            # Chargement des plugins configurés
            await self._load_configured_plugins()
            
            self._running = True
            return True
            
        except Exception as e:
            print(f"Plugin manager initialization failed: {e}")
            return False
    
    async def _discover_plugins(self):
        """Découvre les plugins dans les répertoires configurés"""
        for directory in self.plugin_directories:
            plugin_path = Path(directory)
            if not plugin_path.exists():
                continue
            
            # Parcours des fichiers Python dans le répertoire
            for file_path in plugin_path.glob("**/*.py"):
                if file_path.name.startswith("__"):
                    continue
                
                try:
                    await self._load_plugin_from_file(file_path)
                except Exception as e:
                    print(f"Failed to load plugin from {file_path}: {e}")
    
    async def _load_plugin_from_file(self, file_path: Path):
        """Charge un plugin depuis un fichier"""
        module_name = file_path.stem
        
        # Ajout du répertoire au path Python
        if str(file_path.parent) not in sys.path:
            sys.path.insert(0, str(file_path.parent))
        
        try:
            # Import du module
            module = importlib.import_module(module_name)
            
            # Recherche des classes de plugins
            for attr_name in dir(module):
                attr = getattr(module, attr_name)
                
                if (isinstance(attr, type) and 
                    issubclass(attr, BasePlugin) and 
                    attr != BasePlugin):
                    
                    # Création d'une instance pour obtenir les infos
                    temp_instance = attr()
                    plugin_info = temp_instance.info
                    
                    self.plugin_classes[plugin_info.name] = attr
                    
        finally:
            # Nettoyage du path
            if str(file_path.parent) in sys.path:
                sys.path.remove(str(file_path.parent))
    
    async def _load_configured_plugins(self):
        """Charge les plugins configurés"""
        # TODO: Charger depuis la configuration
        # Pour l'instant, on charge tous les plugins disponibles
        
        for plugin_name, plugin_class in self.plugin_classes.items():
            try:
                await self.load_plugin(plugin_name)
            except Exception as e:
                print(f"Failed to load plugin {plugin_name}: {e}")
    
    async def load_plugin(self, plugin_name: str, config: Dict[str, Any] = None) -> bool:
        """Charge un plugin spécifique"""
        if plugin_name in self.plugins:
            print(f"Plugin {plugin_name} already loaded")
            return True
        
        if plugin_name not in self.plugin_classes:
            print(f"Plugin {plugin_name} not found")
            return False
        
        try:
            # Création de l'instance du plugin
            plugin_class = self.plugin_classes[plugin_name]
            plugin = plugin_class(config or {})
            
            # Ajout des handlers d'événements
            for handler in self._event_handlers:
                plugin.add_event_handler(handler)
            
            # Initialisation du plugin
            if await plugin.initialize():
                self.plugins[plugin_name] = plugin
                print(f"Plugin {plugin_name} loaded successfully")
                return True
            else:
                print(f"Plugin {plugin_name} initialization failed")
                return False
                
        except Exception as e:
            print(f"Failed to load plugin {plugin_name}: {e}")
            return False
    
    async def unload_plugin(self, plugin_name: str) -> bool:
        """Décharge un plugin"""
        if plugin_name not in self.plugins:
            return False
        
        try:
            plugin = self.plugins[plugin_name]
            
            # Arrêt du plugin s'il est en cours d'exécution
            if plugin.status == PluginStatus.RUNNING:
                await plugin.stop()
            
            # Suppression du plugin
            del self.plugins[plugin_name]
            print(f"Plugin {plugin_name} unloaded successfully")
            return True
            
        except Exception as e:
            print(f"Failed to unload plugin {plugin_name}: {e}")
            return False
    
    async def start_plugin(self, plugin_name: str) -> bool:
        """Démarre un plugin"""
        if plugin_name not in self.plugins:
            return False
        
        try:
            plugin = self.plugins[plugin_name]
            if await plugin.start():
                print(f"Plugin {plugin_name} started successfully")
                return True
            else:
                print(f"Plugin {plugin_name} failed to start")
                return False
                
        except Exception as e:
            print(f"Failed to start plugin {plugin_name}: {e}")
            return False
    
    async def stop_plugin(self, plugin_name: str) -> bool:
        """Arrête un plugin"""
        if plugin_name not in self.plugins:
            return False
        
        try:
            plugin = self.plugins[plugin_name]
            if await plugin.stop():
                print(f"Plugin {plugin_name} stopped successfully")
                return True
            else:
                print(f"Plugin {plugin_name} failed to stop")
                return False
                
        except Exception as e:
            print(f"Failed to stop plugin {plugin_name}: {e}")
            return False
    
    async def start_all_plugins(self) -> Dict[str, bool]:
        """Démarre tous les plugins"""
        results = {}
        for plugin_name in self.plugins:
            results[plugin_name] = await self.start_plugin(plugin_name)
        return results
    
    async def stop_all_plugins(self) -> Dict[str, bool]:
        """Arrête tous les plugins"""
        results = {}
        for plugin_name in self.plugins:
            results[plugin_name] = await self.stop_plugin(plugin_name)
        return results
    
    def get_plugin(self, plugin_name: str) -> Optional[BasePlugin]:
        """Récupère un plugin par son nom"""
        return self.plugins.get(plugin_name)
    
    def get_all_plugins(self) -> Dict[str, BasePlugin]:
        """Récupère tous les plugins chargés"""
        return self.plugins.copy()
    
    def get_plugin_info(self, plugin_name: str) -> Optional[PluginInfo]:
        """Récupère les informations d'un plugin"""
        plugin = self.plugins.get(plugin_name)
        return plugin.info if plugin else None
    
    def get_all_plugins_info(self) -> Dict[str, PluginInfo]:
        """Récupère les informations de tous les plugins"""
        return {
            name: plugin.info 
            for name, plugin in self.plugins.items()
        }
    
    async def discover_all_devices(self) -> Dict[str, List[DeviceInfo]]:
        """Découvre tous les dispositifs de tous les plugins"""
        all_devices = {}
        
        for plugin_name, plugin in self.plugins.items():
            try:
                devices = await plugin.discover_devices()
                all_devices[plugin_name] = devices
            except Exception as e:
                print(f"Failed to discover devices for plugin {plugin_name}: {e}")
                all_devices[plugin_name] = []
        
        return all_devices
    
    def add_event_handler(self, handler):
        """Ajoute un handler d'événements global"""
        self._event_handlers.append(handler)
        
        # Ajout aux plugins existants
        for plugin in self.plugins.values():
            plugin.add_event_handler(handler)
    
    def remove_event_handler(self, handler):
        """Supprime un handler d'événements global"""
        if handler in self._event_handlers:
            self._event_handlers.remove(handler)
        
        # Suppression des plugins existants
        for plugin in self.plugins.values():
            plugin.remove_event_handler(handler)
    
    async def get_system_status(self) -> Dict[str, Any]:
        """Récupère le statut du système de plugins"""
        status = {
            "total_plugins": len(self.plugins),
            "running_plugins": len([
                p for p in self.plugins.values() 
                if p.status == PluginStatus.RUNNING
            ]),
            "total_devices": sum(len(p.devices) for p in self.plugins.values()),
            "plugins": {}
        }
        
        for name, plugin in self.plugins.items():
            status["plugins"][name] = await plugin.health_check()
        
        return status
    
    async def shutdown(self):
        """Arrêt propre du gestionnaire de plugins"""
        await self.stop_all_plugins()
        self._running = False
