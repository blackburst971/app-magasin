#!/bin/bash

################################################################################
# Script de nettoyage après installation échouée
# Supprime les dépôts PHP ajoutés incorrectement
################################################################################

set -e

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

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

# Vérification root
if [ "$EUID" -ne 0 ]; then
    print_error "Ce script doit être exécuté avec sudo"
    exit 1
fi

print_step "NETTOYAGE APRÈS INSTALLATION ÉCHOUÉE"

echo "Ce script va supprimer les dépôts PHP incorrectement configurés"
echo ""
read -p "Voulez-vous continuer ? (o/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Oo]$ ]]; then
    exit 1
fi

################################################################################
# 1. Supprimer le PPA Ondrej s'il existe
################################################################################
print_step "1. Suppression du PPA Ondrej PHP"

# Supprimer le fichier de configuration du PPA
if [ -f /etc/apt/sources.list.d/ondrej-ubuntu-php-resolute.list ]; then
    rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list
    print_success "Fichiers PPA Ondrej supprimés"
else
    print_warning "Aucun fichier PPA Ondrej trouvé"
fi

# Utiliser add-apt-repository pour supprimer proprement
if grep -r "ondrej/php" /etc/apt/sources.list.d/ 2>/dev/null; then
    print_warning "Tentative de suppression via add-apt-repository..."
    add-apt-repository --remove -y ppa:ondrej/php 2>/dev/null || true
fi

################################################################################
# 2. Supprimer les clés GPG orphelines
################################################################################
print_step "2. Nettoyage des clés GPG"

# Supprimer les clés Ondrej
apt-key list 2>/dev/null | grep -i ondrej && {
    print_warning "Clés Ondrej détectées, suppression recommandée manuellement"
} || {
    print_success "Aucune clé Ondrej trouvée"
}

################################################################################
# 3. Nettoyer le cache APT
################################################################################
print_step "3. Nettoyage du cache APT"

apt clean
apt autoclean
print_success "Cache APT nettoyé"

################################################################################
# 4. Mettre à jour les sources
################################################################################
print_step "4. Mise à jour des sources"

apt update 2>&1 | tee /tmp/apt-update.log

if grep -q "404" /tmp/apt-update.log || grep -q "does not have a Release file" /tmp/apt-update.log; then
    print_error "Erreurs détectées lors de la mise à jour"
    echo ""
    echo "Vérifiez les fichiers suivants :"
    ls -la /etc/apt/sources.list.d/
    echo ""
    print_warning "Vous devrez peut-être supprimer manuellement les fichiers problématiques dans /etc/apt/sources.list.d/"
else
    print_success "Sources mises à jour avec succès"
fi

################################################################################
# RÉSUMÉ
################################################################################
print_step "NETTOYAGE TERMINÉ"

echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              NETTOYAGE TERMINÉ AVEC SUCCÈS                   ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "Prochaines étapes :"
echo ""
echo "1. Vérifier qu'il n'y a plus d'erreurs :"
echo "   ${GREEN}sudo apt update${NC}"
echo ""
echo "2. Relancer l'installation :"
echo "   ${GREEN}cd /var/www/app-magasin/deploy${NC}"
echo "   ${GREEN}sudo ./install-ubuntu-backup.sh${NC}"
echo ""
print_success "Vous pouvez maintenant relancer l'installation !"
echo ""
