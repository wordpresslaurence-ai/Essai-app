# Image de déploiement pour l'application Laravel « Laurence B. »
# Convient à Railway, Render, Fly.io, ou tout hébergeur qui construit un Dockerfile.
FROM php:8.4-cli-bookworm

# Dépendances système + extensions PHP nécessaires à Laravel
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip curl ca-certificates \
        libzip-dev libicu-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip intl gd bcmath \
    && rm -rf /var/lib/apt/lists/*

# Node.js 22 (pour compiler les assets Vite/Tailwind)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copie du code et installation des dépendances
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && npm ci \
    && npm run build \
    && npm cache clean --force \
    && rm -rf node_modules

# Permissions d'écriture pour Laravel
RUN chmod -R 775 storage bootstrap/cache database \
    && chmod +x docker/entrypoint.sh

ENV APP_ENV=production
EXPOSE 8080

CMD ["sh", "docker/entrypoint.sh"]
