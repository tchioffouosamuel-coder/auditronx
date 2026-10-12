#!/usr/bin/env bash
#
# Mise a jour complete du SaaS Auditron X.
#
# Ce fichier est prevu pour etre lance par cron. Il met a jour le code,
# installe les dependances, applique les migrations centrales et locataires,
# optimise Laravel, puis reconstruit les interfaces Vite.
#
# Exemple cron quotidien a 03:15 :
#   15 3 * * * /bin/bash /chemin/vers/auditron-qr/saas/scripts/mise-a-jour-saas.sh
#
# Variables optionnelles :
#   AUDITRON_DEPLOY_PULL=0       ne fait pas de git pull
#   AUDITRON_DEPLOY_SHOWCASE=0   ne reconstruit pas le site vitrine
#   AUDITRON_DEPLOY_API_ASSETS=0 ne reconstruit pas les assets Vite de l'API
#   RAYON_NUM_THREADS=1          limite le build Vite/Rayon pour cron

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SAAS_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
REPO_ROOT="$(cd "$SAAS_ROOT/.." && pwd)"
API_DIR="$SAAS_ROOT/api"
WEB_DIR="$SAAS_ROOT/web"
SHOWCASE_DIR="$WEB_DIR/showcase"
LOG_DIR="$API_DIR/storage/logs"
LOG_FILE="$LOG_DIR/mise-a-jour-saas.log"
LOCK_FILE="/tmp/auditron-saas-update.lock"

mkdir -p "$LOG_DIR"
exec > >(tee -a "$LOG_FILE") 2>&1

echo
echo "=== Mise a jour SaaS - $(date '+%Y-%m-%d %H:%M:%S') ==="

if command -v flock >/dev/null 2>&1; then
    exec 9>"$LOCK_FILE"
    if ! flock -n 9; then
        echo "Une mise a jour est deja en cours. Sortie."
        exit 0
    fi
fi

run() {
    echo
    echo "-> $*"
    "$@"
}

require_file() {
    local file="$1"
    local message="$2"

    if [ ! -f "$file" ]; then
        echo "$message" >&2
        exit 1
    fi
}

install_node_dependencies() {
    local directory="$1"

    cd "$directory"
    if [ -f package-lock.json ]; then
        run npm ci --no-audit --no-fund
    else
        run npm install --no-audit --no-fund --no-package-lock
    fi
}

build_node_project() {
    local directory="$1"

    install_node_dependencies "$directory"
    run env RAYON_NUM_THREADS="${RAYON_NUM_THREADS:-1}" npm run build
}

require_file "$API_DIR/artisan" "Fichier artisan introuvable dans $API_DIR."
require_file "$API_DIR/.env" "Fichier .env introuvable dans $API_DIR."
require_file "$WEB_DIR/package.json" "Fichier package.json introuvable dans $WEB_DIR."

if [ "${AUDITRON_DEPLOY_PULL:-1}" != "0" ] && [ -d "$REPO_ROOT/.git" ]; then
    cd "$REPO_ROOT"
    run git pull --ff-only
fi

cd "$API_DIR"
run composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
run php artisan down --retry=60

cleanup() {
    cd "$API_DIR" || exit 1
    php artisan up >/dev/null 2>&1 || true
}
trap cleanup EXIT

run php artisan optimize:clear
run php artisan central:migrate --force
run php artisan tenants:migrate
run php artisan config:cache
run php artisan route:clear
run php artisan view:cache

if [ "${AUDITRON_DEPLOY_API_ASSETS:-1}" != "0" ] && [ -f "$API_DIR/package.json" ]; then
    build_node_project "$API_DIR"
fi

build_node_project "$WEB_DIR"

if [ "${AUDITRON_DEPLOY_SHOWCASE:-1}" != "0" ] && [ -f "$SHOWCASE_DIR/package.json" ]; then
    build_node_project "$SHOWCASE_DIR"
fi

cd "$API_DIR"
run php artisan up
trap - EXIT

echo
echo "Mise a jour SaaS terminee - $(date '+%Y-%m-%d %H:%M:%S')"
