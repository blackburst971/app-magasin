#!/bin/bash

################################################################################
# Script d'installation automatique - App Magasin BTS MAV
# Serveur Backup Ubuntu (Raspberry Pi 5 / Ubuntu Server)
################################################################################

set -e  # Arrêter le script en cas d'erreur

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
GITHUB_REPO="https://github.com/VOTRE_UTILISATEUR/app-magasin.git"  # À MODIFIER
GITHUB_BRANCH="master"  # ou main
INSTALL_DIR="/var/www/app-magasin"
WEB_USER="www-data"
PHP_VERSION="8.2"
NODE_VERSION="20"

# Fonction d'affichage
print_step() {
    echo -e "\n${BLUE}═══════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}▶ $1${NC}"
    echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

# Vérification des privilèges root
if [ "$EUID" -ne 0 ]; then
    print_error "Ce script doit être exécuté avec sudo"
    exit 1
fi

print_step "INSTALLATION SERVEUR BACKUP - APP MAGASIN BTS MAV"
echo "Ce script va installer l'application complète sur Ubuntu"
echo "Raspberry Pi 5 détecté : $(uname -m)"
echo ""
read -p "Voulez-vous continuer ? (o/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    exit 1
fi

################################################################################
# ÉTAPE 1 : Mise à jour système
################################################################################
print_step "1/10 - Mise à jour du système"
apt update
apt upgrade -y
print_success "Système mis à jour"

################################################################################
# ÉTAPE 2 : Installation des dépendances système
################################################################################
print_step "2/10 - Installation des paquets système"
apt install -y \
    curl \
    git \
    unzip \
    sqlite3 \
    nginx \
    supervisor \
    ufw \
    htop \
    net-tools \
    rsync \
    wget
print_success "Paquets système installés"

################################################################################
# ÉTAPE 3 : Installation PHP 8.2
################################################################################
print_step "3/10 - Installation PHP ${PHP_VERSION}"

# Ajouter le PPA Ondrej pour PHP (si nécessaire)
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update

# Installation PHP et extensions requises
apt install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-sqlite3 \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-soap \
    php${PHP_VERSION}-imagick

# Vérifier l'installation
php -v
print_success "PHP ${PHP_VERSION} installé : $(php -r 'echo PHP_VERSION;')"

################################################################################
# ÉTAPE 4 : Installation Composer
################################################################################
print_step "4/10 - Installation de Composer"

if [ -f "/usr/local/bin/composer" ]; then
    print_warning "Composer déjà installé, mise à jour..."
    composer self-update
else
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        print_error "Checksum invalide pour Composer"
        rm composer-setup.php
        exit 1
    fi

    php composer-setup.php --quiet
    rm composer-setup.php
    mv composer.phar /usr/local/bin/composer
    chmod +x /usr/local/bin/composer
fi

composer --version
print_success "Composer installé : $(composer --version | head -n1)"

################################################################################
# ÉTAPE 5 : Installation Node.js
################################################################################
print_step "5/10 - Installation de Node.js ${NODE_VERSION}"

if command -v node &> /dev/null; then
    print_warning "Node.js déjà installé : $(node --version)"
else
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
    apt install -y nodejs
fi

node --version
npm --version
print_success "Node.js installé : $(node --version), npm : $(npm --version)"

################################################################################
# ÉTAPE 6 : Clonage du dépôt GitHub
################################################################################
print_step "6/10 - Clonage du code source depuis GitHub"

# Vérifier si le répertoire existe
if [ -d "$INSTALL_DIR" ]; then
    print_warning "Le répertoire $INSTALL_DIR existe déjà"
    read -p "Voulez-vous le supprimer et recommencer ? (o/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Oo]$ ]]; then
        rm -rf "$INSTALL_DIR"
    else
        print_error "Installation annulée"
        exit 1
    fi
fi

# Cloner le dépôt
print_warning "Clonage depuis : $GITHUB_REPO (branche: $GITHUB_BRANCH)"
git clone --branch "$GITHUB_BRANCH" "$GITHUB_REPO" "$INSTALL_DIR"

cd "$INSTALL_DIR"
print_success "Code source cloné dans $INSTALL_DIR"

################################################################################
# ÉTAPE 7 : Configuration des permissions
################################################################################
print_step "7/10 - Configuration des permissions"

chown -R $WEB_USER:$WEB_USER "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/storage"
chmod -R 775 "$INSTALL_DIR/bootstrap/cache"

# Créer le fichier SQLite s'il n'existe pas
mkdir -p "$INSTALL_DIR/database"
if [ ! -f "$INSTALL_DIR/database/database.sqlite" ]; then
    touch "$INSTALL_DIR/database/database.sqlite"
    chown $WEB_USER:$WEB_USER "$INSTALL_DIR/database/database.sqlite"
    chmod 664 "$INSTALL_DIR/database/database.sqlite"
fi

print_success "Permissions configurées"

################################################################################
# ÉTAPE 8 : Installation des dépendances PHP
################################################################################
print_step "8/10 - Installation des dépendances PHP (Composer)"

cd "$INSTALL_DIR"
sudo -u $WEB_USER composer install --no-dev --optimize-autoloader --no-interaction

print_success "Dépendances PHP installées"

################################################################################
# ÉTAPE 9 : Installation des dépendances Node.js et build frontend
################################################################################
print_step "9/10 - Installation des dépendances Node.js et compilation assets"

cd "$INSTALL_DIR"
sudo -u $WEB_USER npm install
sudo -u $WEB_USER npm run build

print_success "Assets frontend compilés"

################################################################################
# ÉTAPE 10 : Configuration Laravel
################################################################################
print_step "10/10 - Configuration de l'application Laravel"

# Copier le fichier .env.example
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    chown $WEB_USER:$WEB_USER "$INSTALL_DIR/.env"
    print_success "Fichier .env créé"
fi

# Configurer la base de données SQLite dans .env
sudo -u $WEB_USER sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' "$INSTALL_DIR/.env"
sudo -u $WEB_USER sed -i "s|APP_URL=.*|APP_URL=http://$(hostname -I | awk '{print $1}')|" "$INSTALL_DIR/.env"
sudo -u $WEB_USER sed -i 's/APP_ENV=local/APP_ENV=production/' "$INSTALL_DIR/.env"
sudo -u $WEB_USER sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' "$INSTALL_DIR/.env"

# Générer la clé d'application
sudo -u $WEB_USER php artisan key:generate --force

# Créer le lien de stockage
sudo -u $WEB_USER php artisan storage:link

# Optimisations Laravel
sudo -u $WEB_USER php artisan config:cache
sudo -u $WEB_USER php artisan route:cache
sudo -u $WEB_USER php artisan view:cache

print_success "Application Laravel configurée"

################################################################################
# Configuration Nginx
################################################################################
print_step "Configuration du serveur web Nginx"

# Créer la configuration Nginx
cat > /etc/nginx/sites-available/app-magasin <<'NGINX_CONFIG'
server {
    listen 80;
    listen [::]:80;

    server_name _;
    root /var/www/app-magasin/public;

    index index.php index.html;

    charset utf-8;

    # Logs
    access_log /var/log/nginx/app-magasin-access.log;
    error_log /var/log/nginx/app-magasin-error.log;

    # Max upload size
    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX_CONFIG

# Activer le site
ln -sf /etc/nginx/sites-available/app-magasin /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default

# Tester la configuration
nginx -t

# Redémarrer Nginx
systemctl restart nginx
systemctl enable nginx

print_success "Nginx configuré et démarré"

################################################################################
# Configuration PHP-FPM
################################################################################
print_step "Optimisation PHP-FPM"

# Ajuster les paramètres PHP-FPM pour Raspberry Pi
sed -i 's/pm.max_children = .*/pm.max_children = 10/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/pm.start_servers = .*/pm.start_servers = 2/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/pm.min_spare_servers = .*/pm.min_spare_servers = 1/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf
sed -i 's/pm.max_spare_servers = .*/pm.max_spare_servers = 3/' /etc/php/${PHP_VERSION}/fpm/pool.d/www.conf

# Augmenter les limites PHP
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/${PHP_VERSION}/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 50M/' /etc/php/${PHP_VERSION}/fpm/php.ini
sed -i 's/memory_limit = .*/memory_limit = 256M/' /etc/php/${PHP_VERSION}/fpm/php.ini

systemctl restart php${PHP_VERSION}-fpm
systemctl enable php${PHP_VERSION}-fpm

print_success "PHP-FPM optimisé"

################################################################################
# Configuration Firewall (UFW)
################################################################################
print_step "Configuration du pare-feu"

ufw --force enable
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow http
ufw allow https

print_success "Pare-feu configuré (SSH, HTTP, HTTPS autorisés)"

################################################################################
# RÉSUMÉ FINAL
################################################################################
print_step "INSTALLATION TERMINÉE AVEC SUCCÈS !"

SERVER_IP=$(hostname -I | awk '{print $1}')

echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          SERVEUR BACKUP INSTALLÉ ET OPÉRATIONNEL            ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "${BLUE}📍 Accès à l'application :${NC}"
echo -e "   ${YELLOW}http://${SERVER_IP}${NC}"
echo ""
echo -e "${BLUE}📂 Emplacement de l'application :${NC}"
echo -e "   ${YELLOW}${INSTALL_DIR}${NC}"
echo ""
echo -e "${BLUE}📊 Base de données SQLite :${NC}"
echo -e "   ${YELLOW}${INSTALL_DIR}/database/database.sqlite${NC}"
echo ""
echo -e "${BLUE}⚙️  Fichier de configuration :${NC}"
echo -e "   ${YELLOW}${INSTALL_DIR}/.env${NC}"
echo ""
echo -e "${BLUE}📝 Logs Nginx :${NC}"
echo -e "   ${YELLOW}/var/log/nginx/app-magasin-*.log${NC}"
echo ""
echo -e "${BLUE}🔧 Services actifs :${NC}"
echo -e "   ${GREEN}✓${NC} Nginx  : $(systemctl is-active nginx)"
echo -e "   ${GREEN}✓${NC} PHP-FPM: $(systemctl is-active php${PHP_VERSION}-fpm)"
echo ""
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}PROCHAINES ÉTAPES :${NC}"
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo "1. Modifier le fichier .env pour configurer :"
echo "   - Les informations de mail (SMTP Gmail)"
echo "   - L'URL de l'application"
echo "   - Le nom de l'application"
echo ""
echo "2. Lancer les migrations pour créer les tables :"
echo "   ${GREEN}cd ${INSTALL_DIR} && sudo -u ${WEB_USER} php artisan migrate --force${NC}"
echo ""
echo "3. Créer le compte administrateur :"
echo "   ${GREEN}cd ${INSTALL_DIR} && sudo -u ${WEB_USER} php artisan db:seed --class=AdminSeeder${NC}"
echo ""
echo "4. Synchroniser la base de données depuis le serveur principal"
echo "   (voir script de synchronisation)"
echo ""
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo ""
print_success "Installation terminée ! 🎉"
echo ""
