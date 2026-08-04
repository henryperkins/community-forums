#!/bin/sh
set -eu

# Container entrypoint. Three jobs before Apache takes over:
#   1. give the app a writable /data (an R2 bucket when one is configured,
#      otherwise plain local disk / a mounted volume),
#   2. materialise the database TLS trust anchor from the environment,
#   3. bring the schema up to date.
# Every step is a no-op unless its environment variable is set, so the same
# image runs unchanged on Cloudflare Containers, a VPS, or docker compose.

DATA_DIR="${DATA_DIR:-/data}"

# --- 1. R2-backed /data ------------------------------------------------------
# Cloudflare Containers have an ephemeral filesystem. Uploads, installed
# packages and the rate-limit ledger live under /data, so on that platform the
# directory must be an R2 mount or every restart silently discards member
# uploads. s3fs backgrounds itself, hence the readiness poll.
if [ -n "${R2_BUCKET:-}" ]; then
    : "${R2_ACCOUNT_ID:?R2_BUCKET is set but R2_ACCOUNT_ID is missing}"
    : "${R2_ACCESS_KEY_ID:?R2_BUCKET is set but R2_ACCESS_KEY_ID is missing}"
    : "${R2_SECRET_ACCESS_KEY:?R2_BUCKET is set but R2_SECRET_ACCESS_KEY is missing}"

    printf '%s:%s' "$R2_ACCESS_KEY_ID" "$R2_SECRET_ACCESS_KEY" > /run/passwd-s3fs
    chmod 600 /run/passwd-s3fs

    mkdir -p "$DATA_DIR"
    s3fs "$R2_BUCKET" "$DATA_DIR" \
        -o passwd_file=/run/passwd-s3fs \
        -o url="https://${R2_ACCOUNT_ID}.r2.cloudflarestorage.com" \
        -o use_path_request_style \
        -o endpoint=auto \
        -o allow_other \
        -o uid=33 -o gid=33 -o umask=0022

    waited=0
    while ! grep -qs " ${DATA_DIR} " /proc/mounts; do
        waited=$((waited + 1))
        if [ "$waited" -gt 30 ]; then
            echo "entrypoint: R2 bucket ${R2_BUCKET} failed to mount at ${DATA_DIR}" >&2
            exit 1
        fi
        sleep 1
    done
    echo "entrypoint: mounted R2 bucket ${R2_BUCKET} at ${DATA_DIR}"
fi

mkdir -p "$DATA_DIR/media" "$DATA_DIR/packages" "$DATA_DIR/ratelimit"

# chown across a FUSE mount is pointless (ownership is fixed by the uid/gid
# mount options) and slow, so only touch permissions on a real filesystem.
if ! grep -qs " ${DATA_DIR} " /proc/mounts; then
    chown -R www-data:www-data "$DATA_DIR"
fi

# --- 2. database TLS trust anchor -------------------------------------------
# Managed MySQL providers hand out a CA bundle. Carrying it as an environment
# variable keeps provider certificates out of the image, so rotating the CA is
# a secret update rather than an image rebuild.
if [ -n "${DB_SSL_CA_PEM:-}" ]; then
    printf '%s\n' "$DB_SSL_CA_PEM" > /run/db-ca.pem
    chmod 644 /run/db-ca.pem
    DB_SSL_CA="${DB_SSL_CA:-/run/db-ca.pem}"
    export DB_SSL_CA
fi

# --- 3. schema ---------------------------------------------------------------
# Stands in for Fly's release_command. Safe to run on every boot: migrations are
# tracked in schema_migrations and the deployment is a single instance, so there
# is no concurrent-migrator race. A failure here aborts the boot on purpose --
# serving traffic against a half-migrated schema is worse than not serving.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "entrypoint: running migrations"
    php /var/www/html/bin/console migrate
fi

exec docker-php-entrypoint "$@"
