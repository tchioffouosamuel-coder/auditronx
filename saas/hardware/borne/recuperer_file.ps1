# Récupère la file de pointages (queue.jsonl) stockée dans la flash interne
# (LittleFS) d'une borne esp32dev_borne, SANS rien modifier sur l'ESP32 :
#   1. lit la partition "spiffs" (0x310000, 0xE0000 — identique dans
#      huge_app.csv et partitions.csv) avec esptool (lecture seule) ;
#   2. extrait les fichiers de l'image avec extraire_littlefs.py.
# Le queue.jsonl obtenu s'importe dans le backoffice :
#   Appareils & points d'accès > Import file borne.
#
# Usage (fermer d'abord tout moniteur série ouvert sur le port) :
#   powershell -ExecutionPolicy Bypass -File .\recuperer_file.ps1 -Port COM6
# Ré-extraire une image déjà lue, sans ESP32 branché :
#   powershell -ExecutionPolicy Bypass -File .\recuperer_file.ps1 -Image .\backup\<date>\littlefs.bin
param(
    [string]$Port = "COM6",
    [string]$Image = ""
)

$ErrorActionPreference = "Stop"
$here = Split-Path -Parent $MyInvocation.MyCommand.Path

# Python et esptool fournis par PlatformIO (selon l'installation : ~/.platformio ou C:\pio-core).
$pioRoots = @("$env:USERPROFILE\.platformio", "C:\pio-core")
$python = $pioRoots | ForEach-Object { "$_\penv\Scripts\python.exe" } | Where-Object { Test-Path $_ } | Select-Object -First 1
$esptool = $pioRoots | ForEach-Object { "$_\packages\tool-esptoolpy\esptool.py" } | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $python -or -not $esptool) {
    throw "Outils PlatformIO introuvables (python/esptool). Compilez une fois le projet avec 'pio run' pour les installer."
}

if ($Image) {
    $image = (Resolve-Path $Image).Path
    $outDir = Split-Path -Parent $image
} else {
    $outDir = Join-Path $here ("backup\" + (Get-Date -Format "yyyyMMdd-HHmmss"))
    New-Item -ItemType Directory -Force $outDir | Out-Null
    $image = Join-Path $outDir "littlefs.bin"

    Write-Host "[1/2] Lecture de la partition LittleFS sur $Port (lecture seule)..."
    & $python $esptool --chip esp32 --port $Port --baud 460800 read_flash 0x310000 0xE0000 $image
    if ($LASTEXITCODE -ne 0) { throw "Lecture de la flash impossible (port occupé par un moniteur série ?)." }
}

# littlefs-python installé à part (dossier temporaire), pour ne pas toucher
# à l'environnement Python de PlatformIO.
$libDir = Join-Path $env:TEMP "auditron_littlefs_py"
if (-not (Test-Path (Join-Path $libDir "littlefs"))) {
    Write-Host "Installation de littlefs-python (une seule fois)..."
    & $python -m pip install --quiet --target $libDir littlefs-python
    if ($LASTEXITCODE -ne 0) { throw "Installation de littlefs-python impossible (connexion internet ?)." }
}

$files = Join-Path $outDir "fichiers"
Write-Host "[2/2] Extraction de l'image LittleFS..."
$env:PYTHONPATH = $libDir
& $python (Join-Path $here "extraire_littlefs.py") $image $files
if ($LASTEXITCODE -ne 0) { throw "Extraction LittleFS impossible." }

$queue = Join-Path $files "queue.jsonl"
if (Test-Path $queue) {
    $count = @(Get-Content $queue | Where-Object { $_.Trim() -ne "" }).Count
    Write-Host ""
    Write-Host "OK : $count paquet(s) récupéré(s) -> $queue"
    Write-Host "Importez ce fichier dans le backoffice : Appareils & points d'accès > Import file borne."
} else {
    Write-Host ""
    Write-Host "Aucun queue.jsonl dans la flash interne."
    Write-Host "Si la borne annonce des paquets en attente, la file est sur la carte micro-SD"
    Write-Host "(au démarrage, une carte détectée récupère la file interne puis l'efface) :"
    Write-Host "retirez la carte, copiez queue.jsonl depuis sa racine sur le PC, puis importez-le."
    Write-Host "Contenu extrait : $files"
}
