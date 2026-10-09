#!/usr/bin/env bash
# Runs existing suites against one disposable MariaDB; upload Compose runs last.
set -euo pipefail
cd "${RB_CI_SOURCE:-/workspace/source}"
ci_results="${RB_CI_RESULTS:-/workspace/results}"
ci_control="${RB_CI_CONTROL:-/workspace/control}"
ci_db_name="${RB_CI_CONTAINER:-retroboards-browser-ci-db}"
ci_db_port="${RB_CI_DB_PORT:-3306}"
[[ "$ci_db_name" =~ ^retroboards-browser-ci-[a-z0-9-]+$ ]] || { echo 'Invalid disposable container name' >&2; exit 2; }
[[ "$ci_db_port" =~ ^[0-9]+$ ]] && ((ci_db_port >= 1024 && ci_db_port <= 65535)) || { echo 'Invalid local DB port' >&2; exit 2; }
if [[ "${1:-}" == '--validate-only' ]]; then
  docker compose version >/dev/null
  php -r 'exit(PHP_VERSION_ID >= 80200 && extension_loaded("pdo_mysql") && extension_loaded("gd") && extension_loaded("sodium") ? 0 : 1);'
  exit
fi
mkdir -p "$ci_results"
export CI=true APP_ENV=test DB_HOST=127.0.0.1 DB_PORT="$ci_db_port" DB_DATABASE=retroboards_e2e DB_USERNAME=root
# These fixed credentials protect only disposable synthetic databases.
export DB_PASSWORD=browser-ci-local DB_ROOT_PASSWORD=browser-ci-local DB_ROOT_USER=root DB_RESET_CONTAINER="$ci_db_name"
export APP_KEY=0000000000000000000000000000000000000000000000000000000000000000
export OPENAI_API_KEY=browser-thread-intelligence-dummy-credential
export THREAD_INTELLIGENCE_REASONING_EFFORT=low THREAD_INTELLIGENCE_MAX_OUTPUT_TOKENS=16000
export WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32,::1/128 MAIL_DRIVER=array SESSION_SECURE=false
# Never reuse evidence checked into the source tree as output of this build.
rm -rf docs/evidence/browser docs/evidence/unified-notifications-and-settings docs/evidence/image-upload-reliability
ci_stage=dependencies
ci_db_created=0
cleanup() {
  local status=$?
  trap - EXIT
  if ((ci_db_created)); then docker rm -f "$ci_db_name" >/dev/null 2>&1 || true; fi
  python3 "$ci_control/package.py" --source "$PWD" --output "$ci_results" --exit-code "$status" --stage "$ci_stage" || status=1
  exit "$status"
}
trap cleanup EXIT
if [[ -n "$(docker ps -aq --filter name="^/${ci_db_name}$")" ]]; then
  echo 'Disposable CI database container already exists; refusing reuse.' >&2
  exit 2
fi
composer install --no-interaction --prefer-dist --no-progress
npm ci
npm run check:assets
npm run test:assets
(cd tests/browser && npm ci)
docker compose version
ci_db_created=1
docker run -d --name "$ci_db_name" \
  --publish "127.0.0.1:$ci_db_port:3306" \
  --env MARIADB_ROOT_PASSWORD=browser-ci-local --env MARIADB_ROOT_HOST=% \
  --env MARIADB_DATABASE=retroboards_e2e \
  --health-cmd='healthcheck.sh --connect --innodb_initialized' \
  --health-interval=2s --health-timeout=2s --health-retries=40 mariadb:11.4 >/dev/null
for attempt in {1..50}; do
  [[ "$(docker inspect --format '{{.State.Health.Status}}' "$ci_db_name")" == healthy ]] && break
  ((attempt < 50)) || { echo 'Disposable database did not become healthy' >&2; exit 1; }
  sleep 2
done
ci_stage=capture
(cd tests/browser && npm run evidence)
ci_stage=notifications-settings
mysql --host=127.0.0.1 --port="$ci_db_port" --user=root --password=browser-ci-local \
  --execute='CREATE DATABASE retroboards_unified_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
(cd tests/browser && DB_DATABASE=retroboards_unified_e2e E2E_PORT=8034 npm run evidence:notifications-settings)
ci_stage=uploads
bash tests/uploads/run.sh
ci_stage=complete
