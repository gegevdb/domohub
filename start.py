#!/usr/bin/env python3
"""
Script de démarrage pour DomoHub
"""

import sys
import os
import subprocess
import time
import webbrowser
from pathlib import Path

def check_port_available(port):
    """Vérifie si un port est disponible"""
    import socket
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
        try:
            s.bind(('localhost', port))
            return True
        except:
            return False

def find_available_port(start_port=8080):
    """Trouve un port disponible"""
    port = start_port
    while port < 9000:
        if check_port_available(port):
            return port
        port += 1
    return None

def main():
    print("🚀 Démarrage de DomoHub...")
    
    # Vérifier Python
    if sys.version_info < (3, 8):
        print("❌ Python 3.8+ requis")
        sys.exit(1)
    
    # Créer l'environnement virtuel s'il n'existe pas
    if not os.path.exists("venv"):
        print("📦 Création de l'environnement virtuel...")
        subprocess.run([sys.executable, "-m", "venv", "venv"], check=True)
        print("✅ Environnement virtuel créé")
    
    # Déterminer les commandes selon l'OS
    if sys.platform == "win32":
        pip_cmd = ["venv\\Scripts\\pip.exe"]
        python_cmd = ["venv\\Scripts\\python.exe"]
    else:
        pip_cmd = ["./venv/bin/pip"]
        python_cmd = ["./venv/bin/python"]
    
    # Vérifier que pip existe
    if not os.path.exists(pip_cmd[0]):
        print(f"❌ {pip_cmd[0]} non trouvé. Réinstallation du venv...")
        import shutil
        shutil.rmtree("venv", ignore_errors=True)
        subprocess.run([sys.executable, "-m", "venv", "venv"], check=True)
        print("✅ Environnement virtuel réinstallé")
    
    # Installer les dépendances
    print("📦 Installation des dépendances...")
    try:
        subprocess.run([*pip_cmd, "install", "-r", "requirements.txt"], check=True)
        print("✅ Dépendances installées")
        
        # Forcer la réinstallation de bcrypt pour éviter les conflits
        print("🔧 Correction de bcrypt...")
        subprocess.run([*pip_cmd, "install", "--force-reinstall", "bcrypt==4.0.1"], check=True)
        print("✅ bcrypt corrigé")
        
    except subprocess.CalledProcessError as e:
        print(f"❌ Erreur lors de l'installation: {e}")
        sys.exit(1)
    
    # Trouver un port disponible
    port = find_available_port(8080)
    if not port:
        print("❌ Aucun port disponible trouvé")
        sys.exit(1)
    
    print(f"🌐 Démarrage sur le port {port}")
    
    # Démarrer le serveur
    try:
        # Ouvrir le navigateur après 2 secondes (uniquement si display disponible)
        import threading
        def open_browser():
            time.sleep(2)
            if os.environ.get('DISPLAY'):
                webbrowser.open(f"http://localhost:{port}")
        
        threading.Thread(target=open_browser, daemon=True).start()
        
        # Démarrer l'application
        env = os.environ.copy()
        env["SERVER__PORT"] = str(port)
        
        subprocess.run([*python_cmd, "-m", "src.main"], env=env)
        
    except KeyboardInterrupt:
        print("\n👋 Arrêt de DomoHub")
    except Exception as e:
        print(f"❌ Erreur: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
