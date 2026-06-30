#!/usr/bin/env bash
# Reset the prodlike browser database and the live app container's limiter store.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
COMPOSE_FILE="$REPO_ROOT/tests/prodlike/compose.yml"

bash "$SCRIPT_DIR/prodlike-env.sh" bash "$SCRIPT_DIR/prepare.sh"

app_container="$(docker compose -f "$COMPOSE_FILE" ps -q app 2>/dev/null || true)"
if [[ -n "$app_container" ]]; then
  docker exec "$app_container" sh -lc \
    'rm -rf /var/www/html/storage/ratelimit-prodlike/* && mkdir -p /var/www/html/storage/ratelimit-prodlike && chown -R www-data:www-data /var/www/html/storage/ratelimit-prodlike'
  echo "==> Reset prodlike app rate-limit store."
else
  echo "==> Prodlike app container not running; skipped app rate-limit reset."
fi
