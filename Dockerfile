# syntax=docker/dockerfile:1
FROM php:8.4-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" curl dom gd mbstring opcache pdo_mysql \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN --mount=type=secret,id=composer_auth,required=false \
    if [ -f /run/secrets/composer_auth ]; then export COMPOSER_AUTH="$(cat /run/secrets/composer_auth)"; fi \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY --chown=www-data:www-data . .
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY deploy/entrypoint.sh /usr/local/bin/retroboards-entrypoint

RUN chmod +x /usr/local/bin/retroboards-entrypoint \
    && mkdir -p storage/cache \
    && chown -R www-data:www-data storage

ENTRYPOINT ["retroboards-entrypoint"]
CMD ["apache2-foreground"]
