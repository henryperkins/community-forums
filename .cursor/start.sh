#!/usr/bin/env bash
# Per-boot startup for RetroBoards Cloud Agent environments.
# Starts the database daemon and waits until it is ready, then returns.
set -euo pipefail

echo "==> starting MariaDB"
sudo mkdir -p /var/run/mysqld
sudo chown -R mysql:mysql /var/run/mysqld
sudo service mariadb start >/dev/null 2>&1 || true

for _ in $(seq 1 30); do
  if sudo mysqladmin ping >/dev/null 2>&1; then
    echo "==> MariaDB is ready"
    exit 0
  fi
  sleep 1
done

echo "!! MariaDB did not become ready in time" >&2
exit 1
