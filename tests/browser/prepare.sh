#!/usr/bin/env bash
# Prepare a fresh, seeded database for the browser-evidence run.
#
# Local dev resets the dedicated DB via the rb-mariadb container; CI relies on its
# MariaDB service having already created the database (the migrate + seed steps below
# use the app's normal DB_* configuration either way).
set -euo pipefail

cd "$(dirname "$0")/../.."   # repo root

DB="${DB_DATABASE:-retroboards_e2e}"

if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'rb-mariadb'; then
  # rootpw is the fixed local rb-mariadb dev-container password (see project README);
  # it only resets a throwaway local database and is never a production credential.
  echo "==> Resetting database '$DB' (rb-mariadb container)"
  docker exec rb-mariadb mariadb -uroot -prootpw -e \
    "DROP DATABASE IF EXISTS \`$DB\`;
     CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     GRANT ALL PRIVILEGES ON \`$DB\`.* TO 'retro'@'%';
     FLUSH PRIVILEGES;"
else
  echo "==> No rb-mariadb container; assuming database '$DB' already exists (CI service)."
fi

echo "==> Migrating"
DB_DATABASE="$DB" php bin/console migrate

echo "==> Seeding"
DB_DATABASE="$DB" php tests/browser/seed.php

echo "==> Database '$DB' ready."
