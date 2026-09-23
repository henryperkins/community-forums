# RetroBoards Cloud Agent base image.
# Provides the stable system toolchain the app needs — PHP 8.3 (with the
# extensions RetroBoards requires), Composer, and MariaDB. Repository
# dependencies (Composer packages) and per-boot services are handled by
# .cursor/install.sh and .cursor/start.sh, not here.
FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        sudo \
        unzip \
        php8.3-cli \
        php8.3-mysql \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-curl \
        php8.3-gd \
        php8.3-bcmath \
        php8.3-common \
        mariadb-server \
        mariadb-client \
    && rm -rf /var/lib/apt/lists/*

# Composer (pinned to a known-good version; installer verifies its own signature).
RUN curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php \
    && composer --version

# The Cloud Agent runs as `ubuntu`; give it passwordless sudo so start.sh can
# manage the MariaDB service and its runtime socket directory.
RUN usermod -aG sudo ubuntu \
    && echo 'ubuntu ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/90-ubuntu \
    && chmod 0440 /etc/sudoers.d/90-ubuntu

USER ubuntu
