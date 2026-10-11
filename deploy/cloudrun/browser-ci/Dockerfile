# Synthetic browser fixtures only; no production configuration enters this image.
FROM docker:28-cli AS dockercli
FROM composer:2 AS composer
FROM node:24-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml php8.2-gd php8.2-curl \
    mariadb-client python3 ca-certificates git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=dockercli /usr/local/bin/docker /usr/local/bin/docker
COPY --from=dockercli /usr/local/libexec/docker/cli-plugins /usr/local/libexec/docker/cli-plugins
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright
WORKDIR /opt/browser-ci
COPY package.json package-lock.json ./
RUN npm ci && npx playwright install --with-deps chromium webkit
WORKDIR /workspace/source
