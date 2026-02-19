# Script d'installation pour DomoHub sur Windows
# PowerShell 5.1+

param(
    [string]$InstallPath = "C:\DomoHub",
    [switch]$SkipPython = $false,
    [switch]$SkipNginx = $false
)

Write-Host "🏠 Installation de DomoHub - Système Domotique Intelligent" -ForegroundColor Green

# Vérification des privilèges administrateur
if (-NOT ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "❌ Ce script doit être exécuté en tant qu'administrateur" -ForegroundColor Red
    exit 1
}

# Création du répertoire d'installation
Write-Host "📁 Création du répertoire d'installation: $InstallPath" -ForegroundColor Blue
New-Item -ItemType Directory -Path $InstallPath -Force | Out-Null

# Installation de Python si nécessaire
if (-not $SkipPython) {
    Write-Host "🐍 Vérification de Python..." -ForegroundColor Blue
    
    try {
        $pythonVersion = python --version 2>&1
        Write-Host "✅ Python trouvé: $pythonVersion" -ForegroundColor Green
    }
    catch {
        Write-Host "❌ Python non trouvé. Installation..." -ForegroundColor Yellow
        
        # Téléchargement de Python
        $pythonUrl = "https://www.python.org/ftp/python/3.11.5/python-3.11.5-amd64.exe"
        $pythonInstaller = "$env:TEMP\python-installer.exe"
        
        Write-Host "📥 Téléchargement de Python..." -ForegroundColor Blue
        Invoke-WebRequest -Uri $pythonUrl -OutFile $pythonInstaller
        
        Write-Host "⚙️ Installation de Python..." -ForegroundColor Blue
        Start-Process -FilePath $pythonInstaller -ArgumentList "/quiet InstallAllUsers=1 PrependPath=1 Include_test=0" -Wait
        
        Remove-Item $pythonInstaller -Force
        
        # Vérification après installation
        try {
            $pythonVersion = python --version 2>&1
            Write-Host "✅ Python installé: $pythonVersion" -ForegroundColor Green
        }
        catch {
            Write-Host "❌ Erreur lors de l'installation de Python" -ForegroundColor Red
            exit 1
        }
    }
}

# Copie des fichiers
Write-Host "📋 Copie des fichiers..." -ForegroundColor Blue
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectPath = Split-Path -Parent $scriptPath

Copy-Item -Path "$projectPath\*" -Destination $InstallPath -Recurse -Force

# Installation des dépendances Python
Write-Host "📦 Installation des dépendances Python..." -ForegroundColor Blue
Set-Location $InstallPath

# Création de l'environnement virtuel
& python -m venv venv

# Activation de l'environnement virtuel et installation
& "$InstallPath\venv\Scripts\Activate.ps1"
& pip install --upgrade pip
& pip install -r requirements.txt

# Configuration
Write-Host "⚙️ Configuration..." -ForegroundColor Blue
if (-not (Test-Path "$InstallPath\.env")) {
    Copy-Item "$InstallPath\.env.example" "$InstallPath\.env"
    Write-Host "✅ Fichier .env créé. Veuillez le configurer." -ForegroundColor Green
}

# Initialisation de la base de données
Write-Host "🗄️ Initialisation de la base de données..." -ForegroundColor Blue
& python -c "from src.core.database import init_db; import asyncio; asyncio.run(init_db())"

# Création du service Windows
Write-Host "🔧 Création du service Windows..." -ForegroundColor Blue

# Script de service PowerShell
$serviceScript = @"
# DomoHub Service Script
`$InstallPath = "$InstallPath"

while (`$true) {
    try {
        & "`$InstallPath\venv\Scripts\python.exe" -m src.main
    }
    catch {
        Write-Host "Service crashed: `$_`" -ForegroundColor Red
        Start-Sleep -Seconds 10
    }
}
"@

$serviceScript | Out-File -FilePath "$InstallPath\service.ps1" -Encoding UTF8

# Création du service avec NSSM (Non-Sucking Service Manager)
Write-Host "📥 Téléchargement de NSSM..." -ForegroundColor Blue
$nssmUrl = "https://nssm.cc/release/nssm-2.24.zip"
$nssmZip = "$env:TEMP\nssm.zip"
$nssmPath = "$InstallPath\nssm"

Invoke-WebRequest -Uri $nssmUrl -OutFile $nssmZip
Expand-Archive -Path $nssmZip -DestinationPath $nssmPath -Force

# Installation du service
$nssmExe = Get-ChildItem -Path $nssmPath -Name "nssm.exe" -Recurse | Select-Object -First 1
& "$nssmPath\$nssmExe" install DomoHub powershell.exe -ArgumentList "-ExecutionPolicy Bypass -File `"$InstallPath\service.ps1`""
& "$nssmPath\$nssmExe" set DomoHub DisplayName "DomoHub - Système Domotique"
& "$nssmPath\$nssmExe" set DomoHub Description "Système domotique intelligent avec contrôle vocal"
& "$nssmPath\$nssmExe" set DomoHub Start SERVICE_AUTO_START

# Nettoyage
Remove-Item $nssmZip -Force
Remove-Item $nssmPath -Recurse -Force

# Configuration du firewall
Write-Host "🔥 Configuration du firewall..." -ForegroundColor Blue
New-NetFirewallRule -DisplayName "DomoHub API" -Direction Inbound -Port 8000 -Protocol TCP -Action Allow | Out-Null
New-NetFirewallRule -DisplayName "DomoHub Web" -Direction Inbound -Port 80 -Protocol TCP -Action Allow | Out-Null

# Création des raccourcis
Write-Host "🔗 Création des raccourcis..." -ForegroundColor Blue
$desktopPath = [Environment]::GetFolderPath("Desktop")
$shell = New-Object -ComObject WScript.Shell

# Raccourci vers l'interface web
$shortcut = $shell.CreateShortcut("$desktopPath\DomoHub Web.lnk")
$shortcut.TargetPath = "http://localhost"
$shortcut.Save()

# Raccourci vers le dossier d'installation
$shortcut = $shell.CreateShortcut("$desktopPath\DomoHub Folder.lnk")
$shortcut.TargetPath = $InstallPath
$shortcut.Save()

# Démarrage du service
Write-Host "🚀 Démarrage du service..." -ForegroundColor Blue
Start-Service DomoHub

# Vérification
Start-Sleep -Seconds 5
$service = Get-Service DomoHub -ErrorAction SilentlyContinue

if ($service -and $service.Status -eq "Running") {
    Write-Host "✅ DomoHub est démarré avec succès!" -ForegroundColor Green
} else {
    Write-Host "❌ Erreur lors du démarrage de DomoHub" -ForegroundColor Red
    Get-Service DomoHub | Format-Table Name, Status, StartType
    exit 1
}

# Informations finales
Write-Host ""
Write-Host "🎉 Installation terminée!" -ForegroundColor Green
Write-Host ""
Write-Host "📍 Informations importantes:" -ForegroundColor Blue
Write-Host "   - Interface web: http://localhost"
Write-Host "   - API: http://localhost/api/v1"
Write-Host "   - Installation: $InstallPath"
Write-Host "   - Configuration: $InstallPath\.env"
Write-Host ""
Write-Host "🔧 Commandes utiles:" -ForegroundColor Blue
Write-Host "   - Démarrer: Start-Service DomoHub"
Write-Host "   - Arrêter: Stop-Service DomoHub"
Write-Host "   - Status: Get-Service DomoHub"
Write-Host "   - Logs: Get-EventLog -LogName Application -Source DomoHub"
Write-Host ""
Write-Host "⚠️  N'oubliez pas de configurer le fichier .env avec vos paramètres!" -ForegroundColor Yellow
