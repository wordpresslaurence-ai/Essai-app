#!/bin/sh
# Démarrage de l'application en production (Railway/Render/…)
set -e

# --- Configuration par défaut (surchageable par les variables d'environnement) ---
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export APP_LOCALE="${APP_LOCALE:-fr}"
export APP_FALLBACK_LOCALE="${APP_FALLBACK_LOCALE:-fr}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

# Clé d'application : générée à la volée si non fournie
if [ -z "${APP_KEY}" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
    echo "APP_KEY générée automatiquement (pensez à la définir comme variable pour la garder stable)."
fi

# Base SQLite (par défaut) : créer le fichier si nécessaire
if [ "${DB_CONNECTION}" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
fi

# Lien de stockage public (images, etc.)
php artisan storage:link 2>/dev/null || true

# Migrations
php artisan migrate --force

# Jeu de démonstration si la base est vide (pratique pour un test)
if [ "${SEED_DEMO:-true}" = "true" ]; then
    php artisan db:seed --class=DemoSeeder --force || true
fi

# Optimisations (routes/vues)
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Démarrage du serveur sur le port fourni par l'hébergeur
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
