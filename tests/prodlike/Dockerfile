FROM php:8.4-fpm-bookworm

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
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

COPY --chown=www-data:www-data . .

RUN mkdir -p storage/media storage/ratelimit-prodlike \
    && chown -R www-data:www-data storage

COPY tests/prodlike/php.ini /usr/local/etc/php/conf.d/retroboards-prodlike.ini
COPY tests/prodlike/app-entrypoint.sh /usr/local/bin/retroboards-prodlike-entrypoint
RUN chmod +x /usr/local/bin/retroboards-prodlike-entrypoint

ENTRYPOINT ["retroboards-prodlike-entrypoint"]
CMD ["php-fpm"]
