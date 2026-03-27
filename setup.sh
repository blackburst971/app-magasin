#!/bin/bash
# ============================================================
# App Magasin Lycée — Script d'installation macOS / Linux
# Compatible: macOS (Homebrew), Ubuntu/Debian, CentOS/RHEL
# ============================================================

set -e
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
ok() { echo -e "${GREEN}  ✓ $1${NC}"; }
info() { echo -e "${CYAN}==> $1${NC}"; }
warn() { echo -e "${YELLOW}  ⚠ $1${NC}"; }
err() { echo -e "${RED}  ✗ $1${NC}"; exit 1; }

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║   App Magasin Lycée — Setup macOS/Linux  ║"
echo "╚══════════════════════════════════════════╝"
echo ""

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

# ── Détection OS ──────────────────────────────────────────────
OS="unknown"
if [[ "$OSTYPE" == "darwin"* ]]; then
    OS="macos"
elif [[ -f /etc/debian_version ]]; then
    OS="debian"
elif [[ -f /etc/redhat-release ]]; then
    OS="rhel"
fi
info "OS détecté: $OS"

# ── Homebrew (macOS) ──────────────────────────────────────────
if [[ "$OS" == "macos" ]]; then
    if ! command -v brew &>/dev/null; then
        info "Installation de Homebrew..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
        ok "Homebrew installé"
    fi
fi

# ── PHP 8.2 ───────────────────────────────────────────────────
if ! command -v php &>/dev/null; then
    info "Installation de PHP 8.2..."
    if [[ "$OS" == "macos" ]]; then
        brew install php@8.2
        echo 'export PATH="/opt/homebrew/opt/php@8.2/bin:$PATH"' >> ~/.zshrc
        export PATH="/opt/homebrew/opt/php@8.2/bin:$PATH"
    elif [[ "$OS" == "debian" ]]; then
        sudo apt-get update -q
        sudo apt-get install -y software-properties-common
        sudo add-apt-repository ppa:ondrej/php -y
        sudo apt-get install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-zip php8.2-gd php8.2-curl php8.2-intl php8.2-bcmath
    elif [[ "$OS" == "rhel" ]]; then
        sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
        sudo dnf module install -y php:remi-8.2
    fi
    ok "PHP 8.2 installé: $(php --version | head -1)"
else
    ok "PHP déjà installé: $(php --version | head -1)"
fi

# ── Composer ──────────────────────────────────────────────────
if ! command -v composer &>/dev/null; then
    info "Installation de Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer 2>/dev/null || \
    php composer-setup.php --install-dir="$HOME/.local/bin" --filename=composer
    php -r "unlink('composer-setup.php');"
    export PATH="$HOME/.local/bin:$PATH"
    ok "Composer installé: $(composer --version | head -1)"
else
    ok "Composer déjà installé"
fi

# ── Node.js ───────────────────────────────────────────────────
if ! command -v node &>/dev/null; then
    info "Installation de Node.js 20..."
    if [[ "$OS" == "macos" ]]; then
        brew install node@20
        export PATH="/opt/homebrew/opt/node@20/bin:$PATH"
    elif [[ "$OS" == "debian" ]]; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
        sudo apt-get install -y nodejs
    elif [[ "$OS" == "rhel" ]]; then
        curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
        sudo dnf install -y nodejs
    fi
    ok "Node.js installé: $(node --version)"
else
    ok "Node.js déjà installé: $(node --version)"
fi

# ── MySQL ─────────────────────────────────────────────────────
if ! command -v mysql &>/dev/null; then
    info "Installation de MySQL 8..."
    if [[ "$OS" == "macos" ]]; then
        brew install mysql@8.0
        brew services start mysql@8.0
        export PATH="/opt/homebrew/opt/mysql@8.0/bin:$PATH"
    elif [[ "$OS" == "debian" ]]; then
        sudo apt-get install -y mysql-server
        sudo systemctl start mysql
        sudo systemctl enable mysql
    fi
    ok "MySQL installé"
fi

# ── Configuration .env ────────────────────────────────────────
if [[ ! -f .env ]]; then
    info "Création du fichier .env..."
    cp .env.example .env
    DB_PASS="AppMagasin$(date +%Y)!"
    sed -i.bak "s/DB_DATABASE=.*/DB_DATABASE=app_magasin/" .env
    sed -i.bak "s/DB_USERNAME=.*/DB_USERNAME=root/" .env
    sed -i.bak "s/DB_PASSWORD=.*/DB_PASSWORD=/" .env
    rm -f .env.bak
    ok ".env créé"
fi

# ── Création base de données ──────────────────────────────────
info "Création de la base de données app_magasin..."
mysql -u root --connect-expired-password -e "
    CREATE DATABASE IF NOT EXISTS app_magasin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    SELECT 'Base créée.' AS Status;
" 2>/dev/null || warn "Impossible de créer la DB automatiquement. Créez manuellement: mysql -u root -p < windows-deploy/mysql-setup.sql"

# ── Dépendances PHP ───────────────────────────────────────────
info "Installation des dépendances PHP (composer install)..."
composer install --no-interaction 2>&1 | tail -5
ok "Dépendances PHP installées"

# ── Dépendances JS ────────────────────────────────────────────
info "Installation des dépendances JS (npm install)..."
npm install --silent
ok "Dépendances JS installées"

# ── Clé d'application ────────────────────────────────────────
info "Génération de la clé Laravel..."
php artisan key:generate --ansi
ok "Clé générée"

# ── Migrations et seeders ─────────────────────────────────────
info "Migration de la base de données..."
php artisan migrate --seed --force
ok "Base de données initialisée"

# ── Lien symbolique storage ───────────────────────────────────
info "Création du lien symbolique storage..."
php artisan storage:link 2>/dev/null || true
ok "Lien storage créé"

# ── Build des assets ──────────────────────────────────────────
echo ""
read -p "Voulez-vous compiler les assets maintenant ? (o/n) [o]: " build_assets
build_assets=${build_assets:-o}
if [[ "$build_assets" =~ ^[oOyY] ]]; then
    info "Compilation des assets (npm run build)..."
    npm run build
    ok "Assets compilés"
fi

# ── Résumé ────────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  ✅ INSTALLATION TERMINÉE                               ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║  Démarrer: php artisan serve                            ║"
echo "║  URL:      http://localhost:8000                        ║"
echo "║  Admin:    admin@lycee.fr / Admin@1234                  ║"
echo "║  Démo:     user@lycee.fr / password                     ║"
echo "╠══════════════════════════════════════════════════════════╣"
echo "║  ⚠  Pensez à configurer Gmail dans .env:               ║"
echo "║  MAIL_USERNAME + MAIL_PASSWORD                          ║"
echo "╚══════════════════════════════════════════════════════════╝"
echo ""
