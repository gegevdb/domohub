# Script de démarrage pour DomoHub sur Windows
# Usage: .\start.ps1 [dev|prod]

param(
    [ValidateSet("dev", "prod")]
    [string]$Mode = "dev"
)

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectDir = Split-Path -Parent $ScriptDir

Write-Host "🏠 Démarrage de DomoHub en mode $Mode" -ForegroundColor Green

# Vérification de l'environnement virtuel
$VenvPath = "$ProjectDir\venv"
if (-not (Test-Path $VenvPath)) {
    Write-Host "❌ Environnement virtuel non trouvé. Exécutez d'abord le script d'installation." -ForegroundColor Red
    exit 1
}

# Activation de l'environnement virtuel
try {
    & "$VenvPath\Scripts\Activate.ps1"
}
catch {
    Write-Host "❌ Erreur lors de l'activation de l'environnement virtuel" -ForegroundColor Red
    Write-Host "Assurez-vous que la politique d'exécution PowerShell permet l'exécution de scripts locaux:" -ForegroundColor Yellow
    Write-Host "Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser" -ForegroundColor Yellow
    exit 1
}

# Configuration des variables d'environnement
$env:PYTHONPATH = "$ProjectDir;$env:PYTHONPATH"

if ($Mode -eq "dev") {
    Write-Host "🔧 Mode développement" -ForegroundColor Blue
    $env:ENVIRONMENT = "development"
    $env:SERVER_DEBUG = "true"
    $env:LOG_LEVEL = "DEBUG"
    
    # Démarrage avec rechargement automatique
    Set-Location $ProjectDir
    python -m uvicorn src.main:app --host 0.0.0.0 --port 8000 --reload --log-level debug
    
}
elseif ($Mode -eq "prod") {
    Write-Host "🚀 Mode production" -ForegroundColor Blue
    $env:ENVIRONMENT = "production"
    $env:SERVER_DEBUG = "false"
    $env:LOG_LEVEL = "INFO"
    
    # Vérification du fichier .env
    $EnvFile = "$ProjectDir\.env"
    if (-not (Test-Path $EnvFile)) {
        Write-Host "❌ Fichier .env non trouvé. Copiez .env.example vers .env et configurez-le." -ForegroundColor Red
        exit 1
    }
    
    # Démarrage avec Uvicorn en mode production
    Set-Location $ProjectDir
    python -m uvicorn src.main:app `
        --host 0.0.0.0 `
        --port 8000 `
        --workers 4 `
        --log-level info `
        --access-log `
        --no-use-colors
}
