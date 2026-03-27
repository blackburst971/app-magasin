#!/usr/bin/env bash
# ============================================================
#  App Magasin Lycée — Installation locale (Ubuntu + Nginx)
#  Serveur : http://192.168.1.14
#
#  Usage (depuis la racine du projet) :
#    sudo bash deploy/install.sh
# ============================================================
set -e

APP_DIR="/var/www/app-magasin"
NGINX_CONF="/etc/nginx/sites-available/app-magasin"
PHP_VERSION="8.2"

# Couleurs
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
step() { echo -e "\n${GREEN}[$1/$TOTAL]${NC} $2"; }
warn() { echo -e "${YELLOW}⚠  $1${NC}"; }

TOTAL=9

echo ""
echo "========================================"
echo "  App Magasin Lycée — Installation"
echo "  Serveur : http://192.168.1.14"
echo "========================================"

# ── 1. Paquets système ────────────────────────────────────────
step 1 "Mise à jour apt + paquets de base..."
apt-get update -q
apt-get install -y -q curl gnupg2 ca-certificates lsb-release unzip supervisor

# ── PHP 8.2 via PPA (ubuntu noble/jammy) ─────────────────────
step 2 "Installation PHP ${PHP_VERSION}-FPM..."
if ! php -v 2>/dev/null | grep -q "PHP ${PHP_VERSION}"; then
    add-apt-repository -y ppa:ondrej/php > /dev/null 2>&1
    apt-get update -q
fi
apt-get install -y -q \
    nginx \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-tokenizer

# ── Composer (officiel) ───────────────────────────────────────
if ! command -v composer &>/dev/null; then
    step 3 "Installation Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
else
    step 3 "Composer déjà présent ($(composer --version --no-ansi | head -1))"
fi

# ── Node.js 20 via NodeSource ─────────────────────────────────
if ! node -v 2>/dev/null | grep -q "v2"; then
    step 4 "Installation Node.js 20..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - > /dev/null 2>&1
    apt-get install -y -q nodejs
else
    step 4 "Node.js déjà présent ($(node -v))"
fi

# ── Copie des fichiers ────────────────────────────────────────
step 5 "Déploiement des fichiers dans $APP_DIR..."
mkdir -p "$APP_DIR"
rsync -a --exclude='.env' --exclude='node_modules' --exclude='vendor' \
      --exclude='.git' . "$APP_DIR/"
cd "$APP_DIR"

# ── Fichier .env ──────────────────────────────────────────────
if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/deploy/.env.production" "$APP_DIR/.env"
    echo "  → .env créé depuis deploy/.env.production"
fi

# ── Dépendances PHP ───────────────────────────────────────────
step 6 "Dépendances PHP + assets Vite..."
composer install --no-dev --optimize-autoloader --no-interaction --quiet
npm ci --silent
npm run build

# ── Laravel : clé, DB, cache ──────────────────────────────────
step 7 "Clé app + migrations + cache..."
php artisan key:generate --force
touch "$APP_DIR/database/database.sqlite" 2>/dev/null || true
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── Permissions ───────────────────────────────────────────────
step 8 "Permissions www-data..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod 664 "$APP_DIR/database/database.sqlite"
chown www-data:www-data "$APP_DIR/database/database.sqlite"

# ── Nginx + Supervisor + Cron ─────────────────────────────────
step 9 "Nginx / Supervisor / Cron..."

# Nginx
cp "$APP_DIR/deploy/nginx.conf" "$NGINX_CONF"
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/app-magasin
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx
systemctl restart nginx

# PHP-FPM
systemctl enable php${PHP_VERSION}-fpm
systemctl restart php${PHP_VERSION}-fpm

# Cron Laravel (schedule:run)
cp "$APP_DIR/deploy/cron.conf" /etc/cron.d/app-magasin
chmod 644 /etc/cron.d/app-magasin
systemctl restart cron

# Supervisor (queue worker)
cp "$APP_DIR/deploy/supervisor.conf" /etc/supervisor/conf.d/app-magasin.conf
systemctl enable supervisor
systemctl restart supervisor

# Attendre que le socket Supervisor soit prêt (max 15 s)
for i in $(seq 1 15); do
    supervisorctl status &>/dev/null && break
    sleep 1
done

supervisorctl reread
supervisorctl update
supervisorctl start app-magasin-worker 2>/dev/null || supervisorctl restart app-magasin-worker

echo ""
echo "========================================"
echo -e "  ${GREEN}✅ Installation terminée !${NC}"
echo "========================================"
echo ""
echo "  → Application : http://192.168.1.14"
echo ""
warn "Si vous configurez le mail, éditez /var/www/app-magasin/.env"
warn "puis relancez : php artisan config:cache"
echo ""
