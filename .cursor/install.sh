#!/usr/bin/env bash
# Idempotent repository bootstrap for RetroBoards Cloud Agent environments.
# Runs after checkout. Safe to run repeatedly.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> composer install"
# composer.lock is intentionally gitignored by this project, so a pristine
# checkout may not have one. Fall back to `composer update` to materialize it.
if [ -f composer.lock ]; then
  composer install --no-interaction --no-progress
else
  composer update --no-interaction --no-progress
fi

echo "==> ensure .env"
if [ ! -f .env ]; then
  cp .env.example .env
  # Local development overrides: plain HTTP, no HSTS, debug on.
  sed -i 's/^APP_ENV=.*/APP_ENV=local/' .env
  sed -i 's/^APP_DEBUG=.*/APP_DEBUG=true/' .env
  sed -i 's/^SESSION_SECURE=.*/SESSION_SECURE=false/' .env
  sed -i 's/^SECURITY_HSTS=.*/SECURITY_HSTS=false/' .env
fi

# Generate an APP_KEY when it is empty (key:generate only prints the value).
if ! grep -q '^APP_KEY=.\+' .env; then
  KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
  sed -i "s/^APP_KEY=.*/APP_KEY=${KEY}/" .env
fi

echo "==> ensure MariaDB is running"
sudo mkdir -p /var/run/mysqld
sudo chown -R mysql:mysql /var/run/mysqld
sudo service mariadb start >/dev/null 2>&1 || true
for _ in $(seq 1 30); do
  if sudo mysqladmin ping >/dev/null 2>&1; then break; fi
  sleep 1
done

echo "==> ensure databases and app user"
sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS retroboards CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS retroboards_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'retro'@'localhost' IDENTIFIED BY 'retropw';
CREATE USER IF NOT EXISTS 'retro'@'127.0.0.1' IDENTIFIED BY 'retropw';
GRANT ALL PRIVILEGES ON retroboards.* TO 'retro'@'localhost';
GRANT ALL PRIVILEGES ON retroboards.* TO 'retro'@'127.0.0.1';
GRANT ALL PRIVILEGES ON retroboards_test.* TO 'retro'@'localhost';
GRANT ALL PRIVILEGES ON retroboards_test.* TO 'retro'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "==> run migrations (additive / idempotent)"
php bin/console migrate

echo "==> install complete"
