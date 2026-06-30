#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

mkdir -p storage/media storage/ratelimit-prodlike
chown -R www-data:www-data storage

php bin/console migrate

exec "$@"
