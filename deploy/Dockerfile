# syntax=docker/dockerfile:1

# OyaFind production image.
#
# Four stages, because the runtime image should not carry Node or Composer:
#   assets  builds the Vite bundle
#   vendor  installs production PHP dependencies
#   app     PHP-FPM runtime (arm64 and amd64)
#   web     Caddy in front, with the built public directory
#
# Build from the repository root:
#   docker build -f deploy/Dockerfile --target app -t oyafind-app .

###############################################################################
# 1. Front-end assets
###############################################################################
FROM node:22-bookworm-slim AS assets

WORKDIR /app

# Copy the manifests first so dependency layers cache independently of source.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

###############################################################################
# 2. Production PHP dependencies
###############################################################################
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .
RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --no-scripts \
        --no-interaction

###############################################################################
# 3. Application runtime
###############################################################################
FROM php:8.2-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        mbstring \
        opcache \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-oyafind.ini
COPY deploy/docker-entrypoint.sh /usr/local/bin/oyafind-entrypoint
RUN chmod 0755 /usr/local/bin/oyafind-entrypoint

WORKDIR /var/www/html

# The host vendor directory is excluded by .dockerignore, so the layer below
# is never shadowed by a stale local checkout.
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# The master process stays as root so it can write its PID file and hand the
# writable directories to www-data; workers drop privileges on their own.
EXPOSE 9000

ENTRYPOINT ["oyafind-entrypoint"]
CMD ["php-fpm"]

###############################################################################
# 4. Edge web server
###############################################################################
FROM caddy:2-alpine AS web

COPY deploy/Caddyfile /etc/caddy/Caddyfile

# Same absolute path as the app container. PHP-FPM resolves the script path
# against its own filesystem, so the two must agree.
COPY --from=app --chown=www-data:www-data /var/www/html/public /var/www/html/public
