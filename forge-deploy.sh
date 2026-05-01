#!/usr/bin/env bash
set -euo pipefail

# ─── EldoGas Production Deployment Script ────────────────────────────────────
# Runs on Laravel Forge after every push to main.
# Configure this in Forge → Site → Deployment → Deployment Script.
# ─────────────────────────────────────────────────────────────────────────────

cd $FORGE_SITE_PATH

echo "→ Pulling latest code..."
git pull origin main

echo "→ Installing PHP dependencies (production, optimised)..."
$FORGE_COMPOSER install --no-interaction --no-dev --prefer-dist --optimize-autoloader

echo "→ Installing Node dependencies..."
npm ci --omit=dev

echo "→ Building frontend assets..."
npm run build

echo "→ Caching configuration, routes, and views..."
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache
$FORGE_PHP artisan event:cache

echo "→ Running database migrations..."
$FORGE_PHP artisan migrate --force

echo "→ Linking storage..."
$FORGE_PHP artisan storage:link --quiet || true   # safe to re-run

echo "→ Restarting queue workers..."
$FORGE_PHP artisan queue:restart

echo "→ Restarting Reverb WebSocket server..."
$FORGE_PHP artisan reverb:restart 2>/dev/null || true

echo "→ Clearing OPcache (if available)..."
$FORGE_PHP artisan opcache:clear 2>/dev/null || true

echo "✓ Deployment complete."
