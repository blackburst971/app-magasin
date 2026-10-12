#!/bin/bash

################################################################################
# Script d'installation automatique - App Magasin BTS MAV
# Serveur Backup Ubuntu (Raspberry Pi 5 / Ubuntu Server)
#
# PRÉREQUIS : Code source déjà téléchargé dans /var/www/app-magasin
# Ce script installe UNIQUEMENT les dépendances système et configure le serveur
################################################################################

set -e  # Arrêter le script en cas d'erreur

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
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
echo "Ce script va installer toutes les dépendances nécessaires"
echo "Raspberry Pi 5 détecté : $(uname -m)"
echo ""
echo "IMPORTANT : Le code source doit déjà être présent dans $INSTALL_DIR"
echo ""
read -p "Voulez-vous continuer ? (o/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    exit 1
fi

################################################################################
# VÉRIFICATION : Code source présent
################################################################################
print_step "Vérification du code source"

if [ ! -d "$INSTALL_DIR" ]; then
    print_error "Le répertoire $INSTALL_DIR n'existe pas !"
    echo ""
    echo "Veuillez d'abord télécharger le code source depuis GitHub :"
    echo "  1. Créer le répertoire : sudo mkdir -p /var/www"
    echo "  2. Télécharger : cd /var/www && sudo git clone https://github.com/blackburst971/app-magasin.git"
    echo "  3. Relancer ce script"
    exit 1
fi

if [ ! -f "$INSTALL_DIR/composer.json" ]; then
    print_error "Fichier composer.json introuvable dans $INSTALL_DIR"
    echo "Le code source n'est pas complet. Vérifiez le téléchargement."
    exit 1
fi

print_success "Code source trouvé dans $INSTALL_DIR"

################################################################################
# ÉTAPE 1 : Mise à jour système
################################################################################
print_step "1/9 - Mise à jour du système"
apt update
apt upgrade -y
print_success "Système mis à jour"

################################################################################
# ÉTAPE 2 : Installation des dépendances système
################################################################################
print_step "2/9 - Installation des paquets système"
apt install -y \
    curl \
    git \
    unzip \
    zip \
    sqlite3 \
    nginx \
    supervisor \
    ufw \
    htop \
    net-tools \
    rsync \
    wget \
    ca-certificates \
    gnupg \
    lsb-release \
    bc
print_success "Paquets système installés"

################################################################################
# ÉTAPE 3 : Installation PHP 8.2
################################################################################
print_step "3/9 - Installation PHP ${PHP_VERSION}"

# Détection de la version Ubuntu
UBUNTU_VERSION=$(lsb_release -rs)
UBUNTU_CODENAME=$(lsb_release -cs)

print_warning "Détection : Ubuntu ${UBUNTU_VERSION} (${UBUNTU_CODENAME})"

# Pour Ubuntu 24.04 (Noble) et plus récent, utiliser packages.sury.org
if (( $(echo "$UBUNTU_VERSION >= 24.04" | bc -l) )); then
    print_warning "Utilisation du dépôt packages.sury.org pour Ubuntu ${UBUNTU_VERSION}"

    # Installer les dépendances
    apt install -y lsb-release ca-certificates curl

    # Ajouter la clé GPG
    curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
    dpkg -i /tmp/debsuryorg-archive-keyring.deb
    rm /tmp/debsuryorg-archive-keyring.deb

    # Ajouter le dépôt
    sh -c 'echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'

    apt update
else
    # Pour Ubuntu 22.04 (Jammy) et versions antérieures, utiliser le PPA Ondrej
    print_warning "Utilisation du PPA Ondrej pour Ubuntu ${UBUNTU_VERSION}"
    apt install -y software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt update
fi

# Installation PHP et extensions requises
print_warning "Installation de PHP ${PHP_VERSION} et extensions..."
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
print_step "4/9 - Installation de Composer"

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
print_step "5/9 - Installation de Node.js ${NODE_VERSION}"

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
# ÉTAPE 6 : Configuration des permissions
################################################################################
print_step "6/9 - Configuration des permissions"

cd "$INSTALL_DIR"
chown -R $WEB_USER:$WEB_USER "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod -R 775 "$INSTALL_DIR/storage" 2>/dev/null || mkdir -p "$INSTALL_DIR/storage" && chmod -R 775 "$INSTALL_DIR/storage"
chmod -R 775 "$INSTALL_DIR/bootstrap/cache" 2>/dev/null || mkdir -p "$INSTALL_DIR/bootstrap/cache" && chmod -R 775 "$INSTALL_DIR/bootstrap/cache"

# Créer le fichier SQLite s'il n'existe pas
mkdir -p "$INSTALL_DIR/database"
if [ ! -f "$INSTALL_DIR/database/database.sqlite" ]; then
    touch "$INSTALL_DIR/database/database.sqlite"
    chown $WEB_USER:$WEB_USER "$INSTALL_DIR/database/database.sqlite"
    chmod 664 "$INSTALL_DIR/database/database.sqlite"
fi

print_success "Permissions configurées"

################################################################################
# ÉTAPE 7 : Installation des dépendances PHP
################################################################################
print_step "7/9 - Installation des dépendances PHP (Composer)"

cd "$INSTALL_DIR"

# Vérifier la version PHP actuelle
PHP_CURRENT_VERSION=$(php -r 'echo PHP_VERSION;')
print_warning "Version PHP détectée : ${PHP_CURRENT_VERSION}"

# Si composer.lock existe et nécessite une version PHP différente, le régénérer
if [ -f "composer.lock" ]; then
    print_warning "Vérification de la compatibilité du composer.lock..."

    # Tenter l'installation, si échec, régénérer le lock
    if ! sudo -u $WEB_USER composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs 2>&1 | tee /tmp/composer-install.log; then
        if grep -q "does not satisfy that requirement" /tmp/composer-install.log || grep -q "affected by security advisories" /tmp/composer-install.log; then
            print_warning "composer.lock incompatible avec PHP ${PHP_CURRENT_VERSION}"
            print_warning "Régénération du composer.lock pour ce serveur de backup..."
            print_warning "(Ignore les incompatibilités de plateforme - acceptable pour serveur backup local)"

            # Supprimer le lock file et régénérer avec flags appropriés
            rm -f composer.lock
            sudo -u $WEB_USER composer update --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

            print_success "composer.lock régénéré pour PHP ${PHP_CURRENT_VERSION}"
        else
            print_error "Erreur lors de l'installation des dépendances"
            exit 1
        fi
    else
        print_success "composer.lock compatible"
    fi
else
    # Pas de composer.lock, installation normale
    sudo -u $WEB_USER composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs
fi

print_success "Dépendances PHP installées"

################################################################################
# ÉTAPE 8 : Installation des dépendances Node.js et build frontend
################################################################################
print_step "8/9 - Installation des dépendances Node.js et compilation assets"

cd "$INSTALL_DIR"
sudo -u $WEB_USER npm install
sudo -u $WEB_USER npm run build

print_success "Assets frontend compilés"

################################################################################
# ÉTAPE 9 : Configuration Laravel
################################################################################
print_step "9/9 - Configuration de l'application Laravel"

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
print_step "Optimisation PHP-FPM pour Raspberry Pi"

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
echo "   ${GREEN}sudo nano ${INSTALL_DIR}/.env${NC}"
echo "   - Les informations de mail (SMTP Gmail)"
echo "   - L'URL de l'application"
echo "   - Le nom de l'application"
echo ""
echo "2. Lancer les migrations pour créer les tables :"
echo "   ${GREEN}cd ${INSTALL_DIR} && sudo -u ${WEB_USER} php artisan migrate --force${NC}"
echo ""
echo "3. Créer le compte administrateur (optionnel si base synchronisée) :"
echo "   ${GREEN}cd ${INSTALL_DIR} && sudo -u ${WEB_USER} php artisan db:seed --class=AdminSeeder${NC}"
echo ""
echo "4. Synchroniser la base de données depuis le serveur principal"
echo "   (voir script de synchronisation dans deploy/)"
echo ""
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo ""
print_success "Installation terminée ! 🎉"
echo ""
