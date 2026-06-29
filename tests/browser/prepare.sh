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
RATE_LIMIT_PATH="${RATELIMIT_PATH:-$PWD/storage/ratelimit-e2e}"

if [[ "$RATE_LIMIT_PATH" != /* ]]; then
  RATE_LIMIT_PATH="$PWD/$RATE_LIMIT_PATH"
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

if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx "$RESET_CONTAINER"; then
  # rootpw is the fixed local rb-mariadb dev-container password (see project README);
  # it only resets a throwaway local database and is never a production credential.
  echo "==> Resetting database '$DB' ($RESET_CONTAINER container)"
  docker exec "$RESET_CONTAINER" "$MYSQL_CLIENT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" -e \
    "DROP DATABASE IF EXISTS \`$DB\`;
     CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB_USER'@'%';
     FLUSH PRIVILEGES;"
else
  echo "==> No $RESET_CONTAINER container; assuming database '$DB' already exists (CI service)."
fi

echo "==> Migrating"
DB_DATABASE="$DB" php bin/console migrate

echo "==> Seeding"
DB_DATABASE="$DB" php tests/browser/seed.php

echo "==> Database '$DB' ready."
