#!/bin/bash
# Upgrades Laravel dependencies inside the Docker container.
# Handles: composer update, code migrations, cache rebuild.
# Safe to run in production: puts app in maintenance mode and rolls back on error.
#
# Usage: bash scripts/upgrade_laravel.sh
# Run from repo root, OUTSIDE the container (uses docker exec).

set -euo pipefail

CONTAINER="php81_orchestrator"
APP_DIR="/var/www/html/orchestrator"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()     { echo -e "${GREEN}[upgrade]${NC} $*"; }
warn()    { echo -e "${YELLOW}[upgrade]${NC} $*"; }
error()   { echo -e "${RED}[upgrade]${NC} $*" >&2; }

# ── helpers ──────────────────────────────────────────────────────────────────

artisan() {
    docker exec -it "$CONTAINER" php "$APP_DIR/artisan" "$@"
}

composer_cmd() {
    docker exec -u root -it "$CONTAINER" composer --working-dir="$APP_DIR" "$@"
}

# ── rollback ─────────────────────────────────────────────────────────────────

rollback() {
    error "Something went wrong — rolling back..."

    # Restore composer.lock from backup
    if [ -f composer.lock.pre-upgrade ]; then
        cp composer.lock.pre-upgrade composer.lock
        warn "composer.lock restored from backup"
    fi

    # Re-install the original locked dependencies
    if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
        warn "Re-installing original dependencies..."
        composer_cmd install --no-interaction --prefer-dist 2>&1 | tail -5 || true
    fi

    # Bring app back up if it was taken down
    artisan up 2>/dev/null || true

    error "Rollback complete. The app should be back to its previous state."
    error "Check the output above for the root cause."
    exit 1
}

trap rollback ERR

# ── preflight ─────────────────────────────────────────────────────────────────

log "Checking NOVA_LICENSE_KEY in .env..."
if ! grep -q "NOVA_LICENSE_KEY=" .env; then
    error "NOVA_LICENSE_KEY is missing from .env."
    error "Add: NOVA_LICENSE_KEY=<your-token-from-auth.json>"
    exit 1
fi

# ── rebuild container ─────────────────────────────────────────────────────────

log "Rebuilding PHP container (PHP version upgrade)..."
docker compose build phpfpm
docker compose up -d phpfpm

log "Waiting for container to be ready..."
sleep 5

log "Checking container is running..."
if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
    error "Container '$CONTAINER' failed to start after rebuild."
    exit 1
fi

log "Checking PHP version in container..."
PHP_VERSION=$(docker exec "$CONTAINER" php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
log "PHP $PHP_VERSION detected"

# ── backup ────────────────────────────────────────────────────────────────────

log "Backing up composer.lock..."
cp composer.lock composer.lock.pre-upgrade

# ── maintenance mode ──────────────────────────────────────────────────────────

log "Putting app in maintenance mode..."
artisan down --retry=30

# ── submodules ────────────────────────────────────────────────────────────────

log "Updating git submodules..."
git submodule update --init --recursive

# ── composer update ───────────────────────────────────────────────────────────

log "Running composer update (this may take a few minutes)..."
composer_cmd update --no-interaction --prefer-dist --with-all-dependencies

# ── cache rebuild ─────────────────────────────────────────────────────────────

log "Rebuilding caches..."
artisan optimize:clear
artisan optimize

# ── publish vendor assets ─────────────────────────────────────────────────────

log "Publishing vendor migrations..."
artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force

# ── migrations ────────────────────────────────────────────────────────────────

log "Running migrations..."
artisan migrate --force

# ── horizon restart ───────────────────────────────────────────────────────────

log "Restarting Horizon..."
artisan horizon:terminate 2>/dev/null || warn "Horizon not running, skipping"

# ── bring app up ─────────────────────────────────────────────────────────────

log "Bringing app back online..."
artisan up

# ── verify ────────────────────────────────────────────────────────────────────

log "Verifying app responds..."
artisan about --only=environment 2>&1 | grep -E "Laravel Version|PHP Version" || true

# ── cleanup ──────────────────────────────────────────────────────────────────

rm -f composer.lock.pre-upgrade
log "Backup removed."

echo ""
log "✓ Upgrade complete!"
