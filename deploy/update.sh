#!/usr/bin/env bash
# ============================================================
#  App Magasin Lycée — Script de mise à jour
#  Usage : sudo bash deploy/update.sh
# ============================================================
set -e

APP_DIR="/var/www/app-magasin"

echo "[1/6] Mode maintenance ON..."
cd "$APP_DIR"
php artisan down --render="errors.503" --retry=60 2>/dev/null \
  || php artisan down --retry=60

echo "[2/6] Mise à jour des dépendances PHP..."
php artisan clear-compiled 2>/dev/null || true
composer install --no-dev --optimize-autoloader --no-interaction --quiet
php artisan package:discover --ansi --quiet 2>/dev/null || true

echo "[3/6] Build des assets..."
npm ci --silent
npm run build

echo "[4/6] Migrations..."
php artisan migrate --force

echo "[5/6] Vidage des caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "[6/6] Redémarrage des workers..."
supervisorctl restart app-magasin-worker
supervisorctl restart app-magasin-scheduler 2>/dev/null || true

php artisan up

echo ""
echo "✅ Mise à jour terminée !"
