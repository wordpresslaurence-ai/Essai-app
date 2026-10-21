#!/usr/bin/env bash
#
# Mise à jour du CRM sur Hostinger (hébergement mutualisé « Cloud Startup »).
#
# Usage, en SSH :
#     bash ~/crm-app/deploy/update-hostinger.sh
#
# Pré-requis (déjà en place après le premier déploiement) :
#   - le code est cloné dans ~/crm-app (clé de déploiement GitHub configurée) ;
#   - Node est installé via nvm (~/.nvm) ;
#   - le .env est configuré (MySQL, APP_KEY, etc.) ;
#   - le sous-domaine mon-crm pointe (lien symbolique) vers ~/crm-app/public.
#
# Particularités Hostinger gérées ci-dessous :
#   - proc_open est désactivé  -> Composer tourne avec --no-scripts ;
#   - route:cache est volontairement évité (une route « closure » existe).

set -euo pipefail

# Se placer à la racine de l'application, quel que soit l'endroit d'appel.
cd "$(dirname "$0")/.."
echo "==> Application : $(pwd)"

echo "==> 1/6  Récupération du code (git pull)"
git pull --ff-only

# Node n'est pas dans le PATH par défaut sur le mutualisé : on charge nvm.
export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
if [ -s "$NVM_DIR/nvm.sh" ]; then
    # shellcheck disable=SC1091
    . "$NVM_DIR/nvm.sh"
fi

echo "==> 2/6  Dépendances PHP (Composer, sans scripts car proc_open est désactivé)"
composer install --no-dev --optimize-autoloader --no-scripts

echo "==> 3/6  Compilation des assets (Vite / Tailwind)"
npm install
npm run build

echo "==> 4/6  Migrations (additives : elles ne suppriment jamais de données)"
php artisan migrate --force

echo "==> 5/6  Mise en cache (config + vues ; pas de route:cache)"
php artisan config:clear
php artisan config:cache
php artisan view:cache

echo "==> 6/6  Droits d'écriture (logs, cache)"
chmod -R 775 storage bootstrap/cache || true

echo ""
echo "==> Terminé ✅  Le CRM est à jour."
