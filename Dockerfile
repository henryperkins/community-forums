# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1: compile JS/CSS assets (Vite + TypeScript → public/assets/)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.mjs ./
COPY src/client/ src/client/
RUN npm run build:wysiwyg

# ---------------------------------------------------------------------------
# Stage 2: PHP base — OS libraries + PHP extensions shared by the vendor
#           (Composer) stage and the final runtime stage.
# ---------------------------------------------------------------------------
FROM php:8.2-apache-bookworm AS phpbase

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsodium-dev \
        libwebp-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl dom gd mbstring opcache pdo_mysql sodium \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Stage 3: install Composer dependencies (production only, no dev).
#           Derived from phpbase so all required extensions (including ext-gd)
#           are present when Composer verifies platform requirements.
# ---------------------------------------------------------------------------
FROM phpbase AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Stage 4: final PHP + Apache runtime
# ---------------------------------------------------------------------------
FROM phpbase

WORKDIR /var/www/html

# Application source (vendor/ and storage/ excluded via .dockerignore)
COPY --chown=www-data:www-data . .

# Production vendor from the dedicated Composer stage
COPY --chown=www-data:www-data --from=vendor /build/vendor ./vendor

# Freshly compiled JS/CSS assets (authoritative; overwrite any pre-committed versions)
COPY --chown=www-data:www-data --from=assets /build/public/assets/wysiwyg-composer.js ./public/assets/wysiwyg-composer.js
COPY --chown=www-data:www-data --from=assets /build/public/assets/wysiwyg-composer.css ./public/assets/wysiwyg-composer.css

COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/entrypoint.sh /usr/local/bin/retroboards-entrypoint

RUN chmod +x /usr/local/bin/retroboards-entrypoint \
    && mkdir -p storage/cache storage/ratelimit storage/media \
    && chown -R www-data:www-data storage

ENTRYPOINT ["retroboards-entrypoint"]
CMD ["apache2-foreground"]
