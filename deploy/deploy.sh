#!/usr/bin/env bash
# =============================================================================
#  App Magasin — Script de déploiement (Mac → GitHub)
#
#  Usage :
#    ./deploy/deploy.sh                      # message automatique
#    ./deploy/deploy.sh "Fix bug retour"     # message personnalisé
#
#  Ce script :
#    1. Build les assets Vite (public/build/)
#    2. Commit TOUT (app, migrations, vues, assets compilés…)
#    3. Push sur GitHub (branche main)
#
#  Après le push, connectez-vous sur le serveur et lancez :
#    PowerShell -ExecutionPolicy Bypass -File deploy\windows\update.ps1
#  Ou si SSH est configuré, ce script peut le faire automatiquement
#  (décommenter la section SSH en bas).
# =============================================================================
set -euo pipefail

# ── Couleurs ──────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; CYAN='\033[0;36m'; YELLOW='\033[1;33m'; NC='\033[0m'
step() { echo -e "\n${CYAN}▶ $1${NC}"; }
ok()   { echo -e "  ${GREEN}✓ $1${NC}"; }
warn() { echo -e "  ${YELLOW}⚠ $1${NC}"; }
fail() { echo -e "  ${RED}✗ $1${NC}"; exit 1; }

# ── Message de commit ─────────────────────────────────────────────────────────
COMMIT_MSG="${1:-Deploy $(date '+%Y-%m-%d %H:%M')}"

# ── Vérifications préalables ──────────────────────────────────────────────────
step "Vérification de l'environnement"
[ -f "artisan" ] || fail "Lancez ce script depuis la racine du projet."
command -v git  &>/dev/null || fail "git n'est pas installé."
node node_modules/.bin/vite --version &>/dev/null 2>&1 || fail "Vite introuvable (node_modules manquant ?)."

BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "main")
REMOTE=$(git remote get-url origin 2>/dev/null || echo "")
[ -n "$REMOTE" ] || fail "Aucun remote 'origin' configuré. Faites d'abord : git remote add origin <URL>"
ok "Remote : $REMOTE"
ok "Branche : $BRANCH"

# ── Build Vite ────────────────────────────────────────────────────────────────
step "Build des assets (Vite + PWA)"
node node_modules/.bin/vite build
ok "public/build/ généré"

# ── Commit ────────────────────────────────────────────────────────────────────
step "Commit Git"
git add -A

# Vérifier s'il y a des changements à commiter
if git diff --cached --quiet; then
    warn "Aucun changement à commiter. Le code est déjà à jour."
    echo -e "\n${GREEN}✓ Rien à déployer.${NC}\n"
    exit 0
fi

git commit -m "$COMMIT_MSG"
ok "Commit : $COMMIT_MSG"

# ── Push ──────────────────────────────────────────────────────────────────────
step "Push sur GitHub"
git push origin "$BRANCH"
ok "Poussé sur $REMOTE ($BRANCH)"

# ── Résumé ────────────────────────────────────────────────────────────────────
echo ""
echo -e "${GREEN}============================================${NC}"
echo -e "${GREEN}  ✓ Code poussé sur GitHub avec succès !${NC}"
echo -e "${GREEN}============================================${NC}"
echo ""
echo -e "Pour déployer sur le serveur Windows :"
echo -e "  ${YELLOW}Option 1 (manuel)${NC} : Connectez-vous au serveur et lancez :"
echo -e "    PowerShell -ExecutionPolicy Bypass -File deploy\\windows\\update.ps1"
echo ""
echo -e "  ${YELLOW}Option 2 (SSH)${NC} : Si SSH est configuré, décommentez le bloc SSH"
echo -e "    dans deploy/deploy.sh et relancez."
echo ""

# ── OPTIONNEL : Déploiement SSH automatique ───────────────────────────────────
# Décommentez et ajustez si SSH est configuré sur le serveur Windows.
# L'utilisateur SSH doit avoir les droits admin (pour les migrations et IIS).
#
# SERVER_USER="administrator"
# SERVER_IP="10.15.9.114"
# SERVER_PATH="C:\\inetpub\\wwwroot\\app-magasin"
#
# step "Déploiement SSH sur le serveur"
# ssh "${SERVER_USER}@${SERVER_IP}" \
#     "cd \"${SERVER_PATH}\" && PowerShell -ExecutionPolicy Bypass -File deploy\\windows\\update.ps1"
# ok "Serveur mis à jour via SSH !"
