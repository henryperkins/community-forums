#!/bin/sh
set -eu

mkdir -p /data/media /data/packages /data/ratelimit
chown -R www-data:www-data /data

exec docker-php-entrypoint "$@"
