#!/usr/bin/env bash
# Shared environment for browser evidence that targets tests/prodlike/compose.yml.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

export DB_HOST="${DB_HOST:-127.0.0.1}"
export DB_PORT="${DB_PORT:-3321}"
export DB_DATABASE="${DB_DATABASE:-retroboards_prodlike}"
export DB_USERNAME="${DB_USERNAME:-retro}"
export DB_PASSWORD="${DB_PASSWORD:-retro}"
export DB_RESET_CONTAINER="${DB_RESET_CONTAINER:-retroboards-prodlike-db}"
export RATELIMIT_PATH="${RATELIMIT_PATH:-storage/ratelimit-prodlike}"
export E2E_BASE_URL="${E2E_BASE_URL:-http://127.0.0.1:8021}"
export E2E_SKIP_WEBSERVER="${E2E_SKIP_WEBSERVER:-1}"
export PATH="$SCRIPT_DIR/node_modules/.bin:$PATH"

exec "$@"
