#!/usr/bin/env bash
# Prepare a fresh, seeded database for the browser-evidence run.
#
# Local dev resets the dedicated DB via the rb-mariadb container; CI relies on its
# MariaDB service having already created the database (the migrate + seed steps below
# use the app's normal DB_* configuration either way).
set -euo pipefail

cd "$(dirname "$0")/../.."   # repo root

DB="${DB_DATABASE:-retroboards_e2e}"
RESET_CONTAINER="${DB_RESET_CONTAINER:-rb-mariadb}"
ROOT_USER="${DB_ROOT_USER:-root}"
ROOT_PASSWORD="${DB_ROOT_PASSWORD:-rootpw}"
DB_USER="${DB_USERNAME:-retro}"
MYSQL_CLIENT="${DB_MYSQL_CLIENT:-mariadb}"
if [ -n "${DOCKER_BINARY:-}" ]; then
  DOCKER_BIN="$DOCKER_BINARY"
elif command -v docker.exe >/dev/null 2>&1; then
  DOCKER_BIN="docker.exe"
else
  DOCKER_BIN="docker"
fi
RATE_LIMIT_PATH="${RATELIMIT_PATH:-$PWD/storage/ratelimit-e2e}"
PACKAGES_PATH="${PACKAGES_STORAGE_PATH:-$PWD/storage/packages-e2e}"
if [ -n "${PHP_BINARY:-}" ]; then
  PHP_BIN="$PHP_BINARY"
elif command -v php.exe >/dev/null 2>&1; then
  PHP_BIN="php.exe"
else
  PHP_BIN="php"
fi

# Seed, fixture subprocesses, and Playwright's PHP server must share the same
# deterministic non-production keys. Thread Intelligence provider health is
# fingerprinted with both values, so allowing one process to invent its own
# fallback would make the latch/retry evidence unstable.
export APP_KEY="${APP_KEY:-0000000000000000000000000000000000000000000000000000000000000000}"
export OPENAI_API_KEY="${OPENAI_API_KEY:-browser-thread-intelligence-dummy-credential}"
if [[ "$PHP_BIN" == *.exe ]]; then
  export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-$(wslpath -w "$PWD/storage/cache")}"
  export OPENSSL_CONF="${OPENSSL_CONF:-C:\Program Files\Git\usr\ssl\openssl.cnf}"
  export WSLENV="${WSLENV:+$WSLENV:}PHP_INI_SCAN_DIR/w:OPENSSL_CONF/w:DB_DATABASE/w:APP_KEY/w:OPENAI_API_KEY/w:PACKAGES_STORAGE_PATH/p"
fi

if [[ "$RATE_LIMIT_PATH" != /* ]]; then
  RATE_LIMIT_PATH="$PWD/$RATE_LIMIT_PATH"
fi
if [[ "$PACKAGES_PATH" != /* ]]; then
  PACKAGES_PATH="$PWD/$PACKAGES_PATH"
fi

case "$RATE_LIMIT_PATH" in
  "$PWD"/storage/ratelimit-e2e*)
    echo "==> Resetting browser-evidence rate-limit store"
    rm -rf "$RATE_LIMIT_PATH"
    mkdir -p "$RATE_LIMIT_PATH"
    ;;
  *)
    echo "==> Using rate-limit store '$RATE_LIMIT_PATH' (not clearing outside storage/ratelimit-e2e)"
    mkdir -p "$RATE_LIMIT_PATH"
    ;;
esac

# Isolate the content-addressed package artifact store from the developer's real
# storage/packages, mirroring the rate-limit store above.
case "$PACKAGES_PATH" in
  "$PWD"/storage/packages-e2e*)
    echo "==> Resetting browser-evidence package artifact store"
    rm -rf "$PACKAGES_PATH"
    mkdir -p "$PACKAGES_PATH"
    ;;
  *)
    echo "==> Using package artifact store '$PACKAGES_PATH' (not clearing outside storage/packages-e2e)"
    mkdir -p "$PACKAGES_PATH"
    ;;
esac
export PACKAGES_STORAGE_PATH="$PACKAGES_PATH"

if "$DOCKER_BIN" ps --format '{{.Names}}' 2>/dev/null | grep -qx "$RESET_CONTAINER"; then
  # rootpw is the fixed local rb-mariadb dev-container password (see project README);
  # it only resets a throwaway local database and is never a production credential.
  echo "==> Resetting database '$DB' ($RESET_CONTAINER container)"
  "$DOCKER_BIN" exec "$RESET_CONTAINER" "$MYSQL_CLIENT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" -e \
    "DROP DATABASE IF EXISTS \`$DB\`;
     CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_USER'@'%';
     FLUSH PRIVILEGES;"
else
  echo "==> No $RESET_CONTAINER container; assuming database '$DB' already exists (CI service)."
fi

echo "==> Rebuilding schema"
# Browser evidence must start from the same deterministic content every run.
# The spec mutates data (new threads, announcements, email state, etc.), so a
# plain migrate-on-top leaves older evidence artifacts on page 1 and breaks the
# fixture assumptions. Rebuild the dedicated evidence DB each time instead.
DB_DATABASE="$DB" "$PHP_BIN" bin/console migrate:fresh

echo "==> Seeding"
DB_DATABASE="$DB" "$PHP_BIN" tests/browser/seed.php

echo "==> Database '$DB' ready."
