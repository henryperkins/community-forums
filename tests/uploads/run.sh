#!/usr/bin/env bash
# Production-Dockerfile multipart/browser proof; owns only its dedicated Compose project.
set -euo pipefail
cd "$(dirname "$0")/../.."
baseline=0
keep=0
export RB_UPLOAD_IMAGE=retroboards:upload-reliability
while (($#)); do
  case "$1" in
    --baseline-image) export RB_UPLOAD_IMAGE="${2:?baseline image required}"; baseline=1; shift 2 ;;
    --keep) keep=1; shift ;;
    *) echo "usage: tests/uploads/run.sh [--baseline-image IMAGE] [--keep]" >&2; exit 2 ;;
  esac
done
compose=(docker compose -p retroboards-upload-check -f tests/uploads/compose.yml)
if [[ -n "$(docker ps -aq --filter label=com.docker.compose.project=retroboards-upload-check)" ]]; then
  echo 'The upload-check project already exists; inspect or clean that dedicated project before retrying.' >&2
  exit 2
fi
evidence="$PWD/docs/evidence/image-upload-reliability"
if ((baseline)); then evidence="$evidence/baseline"; fi
mkdir -p "$evidence"
scratch=$(mktemp -d -t rb-upload-check.XXXXXXXX)
cleanup() {
  local status=$?
  if ((status)); then
    for log in seed start; do
      if [[ -f "$scratch/$log.log" ]]; then tail -20 "$scratch/$log.log" >&2; fi
    done
  fi
  if ((keep)); then
    echo "Diagnostic mode: retained only retroboards-upload-check and $scratch" >&2
  else
    "${compose[@]}" down --volumes --remove-orphans > "$scratch/cleanup.log" 2>&1 || status=1
    rm -rf "$scratch"
  fi
  exit "$status"
}
trap cleanup EXIT
if ((!baseline)); then
  docker build -t "$RB_UPLOAD_IMAGE" . > "$scratch/build.log" 2>&1 || { tail -40 "$scratch/build.log"; exit 1; }
fi
"${compose[@]}" up -d --wait > "$scratch/start.log" 2>&1 || { tail -40 "$scratch/start.log"; exit 1; }
if ((baseline)); then
  # The old entrypoint mistakes a Docker volume for R2 and skips ownership.
  # Prepare this local baseline fixture so that defect does not mask the PHP cap.
  "${compose[@]}" exec -T app chown -R www-data:www-data /data
fi
# Only explicitly listed, synthetic configuration goes into the application.
# Host fixture commands connect to the same disposable database, never .env.
export APP_ENV=test APP_KEY=0000000000000000000000000000000000000000000000000000000000000000
export DB_HOST=127.0.0.1 DB_PORT=3334 DB_DATABASE=retroboards_upload_http DB_USERNAME=upload_http DB_PASSWORD=upload-http-local
export E2E_SKIP_WEBSERVER=1 E2E_BASE_URL=http://127.0.0.1:8024 RB_UPLOAD_HTTP=1 RB_UPLOAD_BASELINE="$baseline"
export RB_EVIDENCE_DIR="${evidence#$PWD/}"
export RB_UPLOAD_APP_CONTAINER="$("${compose[@]}" ps -q app)"
"${compose[@]}" exec -T app php -r '
require "vendor/autoload.php";
$config = App\Core\Config::fromFile("config/config.php");
$f = ini_parse_quantity(ini_get("upload_max_filesize"));
$p = ini_parse_quantity(ini_get("post_max_size"));
$a = (int) $config->get("uploads.max_bytes");
echo json_encode(["php" => PHP_VERSION, "file_bytes" => $f, "post_bytes" => $p, "application_bytes" => $a]), PHP_EOL;
exit($f >= $a && $p > $f ? 0 : 1);
' > "$evidence/runtime-limits.json" || { if ((!baseline)); then cat "$evidence/runtime-limits.json"; exit 1; fi; }
docker image inspect --format '{{.Id}}' "$RB_UPLOAD_IMAGE" > "$evidence/image-id.txt"
projects=(desktop mobile webkit-mobile)
if ((baseline)); then projects=(desktop); fi
for project in "${projects[@]}"; do
  # This explicit database check precedes every destructive fixture reset.
  "${compose[@]}" exec -T app php -r 'exit(getenv("DB_DATABASE") === "retroboards_upload_http" ? 0 : 1);'
  "${compose[@]}" exec -T app php bin/console migrate:fresh > "$scratch/seed.log" 2>&1
  "${compose[@]}" exec -T app php tests/browser/upload-fixture.php seed >> "$scratch/seed.log" 2>&1
  "${compose[@]}" exec -T app sh -c 'find /data/ratelimit -type f -delete'
  args=(test --config tests/browser/playwright.uploads.config.ts --project="$project")
  if ((baseline)); then args+=(upload-http.spec.ts --grep 'multipart size boundaries'); fi
  tests/browser/node_modules/.bin/playwright "${args[@]}" 2>&1 | tee "$evidence/$project-results.txt"
done
if ((baseline)); then echo 'Baseline reproduced; this is not a repaired-suite pass.'; fi
