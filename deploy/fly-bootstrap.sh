#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

APP_NAME=${FLY_APP_NAME:-community-forums}
DB_APP_NAME=${FLY_DB_APP_NAME:-"${APP_NAME}-db"}
REGION=${FLY_REGION:-iad}
DB_VOLUME=${FLY_DB_VOLUME:-mariadb_data}
DB_VOLUME_SIZE=${FLY_DB_VOLUME_SIZE:-10}

require_command() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "Required command not found: $1" >&2
        exit 1
    }
}

require_secret() {
    eval "value=\${$1:-}"
    if [ -z "$value" ]; then
        echo "Set $1 in the environment before running this command." >&2
        exit 1
    fi
}

create_app() {
    name=$1
    if flyctl status --app "$name" >/dev/null 2>&1; then
        return
    fi

    if [ -n "${FLY_ORG:-}" ]; then
        flyctl apps create "$name" --org "$FLY_ORG"
    else
        flyctl apps create "$name"
    fi
}

require_command flyctl
require_secret APP_KEY
require_secret DB_PASSWORD
require_secret DB_ROOT_PASSWORD

create_app "$DB_APP_NAME"

if ! flyctl volumes list --app "$DB_APP_NAME" | grep -Fq "$DB_VOLUME"; then
    flyctl volumes create "$DB_VOLUME" \
        --app "$DB_APP_NAME" \
        --region "$REGION" \
        --size "$DB_VOLUME_SIZE" \
        --yes
fi

flyctl secrets set --app "$DB_APP_NAME" \
    MARIADB_PASSWORD="$DB_PASSWORD" \
    MARIADB_ROOT_PASSWORD="$DB_ROOT_PASSWORD"

flyctl deploy \
    --app "$DB_APP_NAME" \
    --config "$ROOT/fly.database.toml"

attempt=0
until flyctl ssh console --app "$DB_APP_NAME" \
    --command "healthcheck.sh --connect --innodb_initialized" >/dev/null 2>&1
do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "MariaDB did not become ready; inspect it with: flyctl logs --app $DB_APP_NAME" >&2
        exit 1
    fi
    sleep 2
done

create_app "$APP_NAME"

flyctl secrets set --app "$APP_NAME" \
    APP_KEY="$APP_KEY" \
    DB_HOST="${DB_APP_NAME}.internal" \
    DB_PORT="3306" \
    DB_DATABASE="retroboards" \
    DB_USERNAME="retro" \
    DB_PASSWORD="$DB_PASSWORD"

flyctl deploy \
    --app "$APP_NAME" \
    --config "$ROOT/fly.toml"

echo "Deployment complete: https://${APP_NAME}.fly.dev"
