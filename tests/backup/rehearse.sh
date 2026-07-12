#!/usr/bin/env bash
# Backup → restore rehearsal (operations.md §7).
#
# Proves a mariadb-dump backup of a populated RetroBoards database restores into a
# fresh database with NO data loss and a fully intact schema:
#   1. build + seed a source DB              (retroboards_backup_src)
#   2. snapshot it                           (per-table row count + CHECKSUM TABLE)
#   3. back it up                            (mariadb-dump → .sql)
#   4. restore into a fresh DB               (retroboards_backup_dst)
#   5. snapshot the restore + diff vs source (must be identical)
#   6. assert restored Thread Intelligence lifecycle rows are populated
#   7. assert the restored schema is complete (`migrate` is a no-op)
#   8. reconcile counters/reputation         (`repair`, runbook step 4)
#   9. boot the app on the restored DB        (home page serves seeded content)
#
# Uses the local rb-mariadb dev container by default (no host MySQL client needed)
# and the dedicated retroboards_backup_{src,dst} databases — never dev/test/prod
# data. Override DB_CONTAINER / DB_ROOT_PASSWORD / DB_MYSQL_CLIENT /
# DB_MYSQLDUMP_CLIENT / BACKUP_REHEARSAL_PORT when a checkout uses a differently
# named local DB container or port 8021 is already occupied. Set DB_CONTAINER=host
# to use host MariaDB clients against existing throwaway databases; in host mode
# DB_BACKUP_SRC and DB_BACKUP_DST may name already-granted databases.
#
#   tests/backup/rehearse.sh                 # human run
#   tests/backup/rehearse.sh | tee docs/evidence/backup-restore/rehearsal.log
set -euo pipefail

cd "$(dirname "$0")/../.."   # repo root

CONTAINER="${DB_CONTAINER:-rb-mariadb}"
ROOT_USER="${DB_ROOT_USER:-root}"
ROOT_PASSWORD="${DB_ROOT_PASSWORD:-rootpw}"
DB_USER="${DB_USERNAME:-retro}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
MYSQL_CLIENT="${DB_MYSQL_CLIENT:-mariadb}"
MYSQLDUMP_CLIENT="${DB_MYSQLDUMP_CLIENT:-mariadb-dump}"
if [ -n "${DOCKER_BINARY:-}" ]; then
  DOCKER_BIN="$DOCKER_BINARY"
elif command -v docker.exe >/dev/null 2>&1; then
  DOCKER_BIN="docker.exe"
else
  DOCKER_BIN="docker"
fi
SRC="${DB_BACKUP_SRC:-retroboards_backup_src}"
DST="${DB_BACKUP_DST:-retroboards_backup_dst}"
PORT="${BACKUP_REHEARSAL_PORT:-8021}"
DUMP="$(mktemp -d)/retroboards-backup.sql"
if [ -n "${PHP_BINARY:-}" ]; then
  PHP_BIN="$PHP_BINARY"
elif command -v php.exe >/dev/null 2>&1; then
  PHP_BIN="php.exe"
else
  PHP_BIN="php"
fi
if [[ "$PHP_BIN" == *.exe ]] && command -v curl.exe >/dev/null 2>&1; then
  CURL_BIN="curl.exe"
  CURL_NULL="NUL"
else
  CURL_BIN="curl"
  CURL_NULL="/dev/null"
fi
export APP_KEY="${APP_KEY:-0000000000000000000000000000000000000000000000000000000000000000}"
export OPENAI_API_KEY="${OPENAI_API_KEY:-backup-rehearsal-dummy-credential}"
if [[ "$PHP_BIN" == *.exe ]]; then
  export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-$(wslpath -w "$PWD/storage/cache")}"
  export OPENSSL_CONF="${OPENSSL_CONF:-C:\Program Files\Git\usr\ssl\openssl.cnf}"
  export WSLENV="${WSLENV:+$WSLENV:}PHP_INI_SCAN_DIR/w:OPENSSL_CONF/w:DB_DATABASE/w:APP_KEY/w:OPENAI_API_KEY/w"
fi

# rootpw is the fixed local rb-mariadb dev-container password (see project README);
# this script only touches throwaway rehearsal databases — never a production credential.
if [ "$CONTAINER" = "host" ]; then
  myq()   { "$MYSQL_CLIENT"     "-h$DB_HOST" "-P$DB_PORT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
  mydump(){ "$MYSQLDUMP_CLIENT" "-h$DB_HOST" "-P$DB_PORT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
else
  myq()   { "$DOCKER_BIN" exec -i "$CONTAINER" "$MYSQL_CLIENT"     "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
  mydump(){ "$DOCKER_BIN" exec    "$CONTAINER" "$MYSQLDUMP_CLIENT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
fi

reset_db() {
  local db="$1" exists tables drop_list
  if [ "$CONTAINER" != "host" ]; then
    myq -e "DROP DATABASE IF EXISTS \`$db\`;
            CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            GRANT ALL PRIVILEGES ON \`$db\`.* TO '$DB_USER'@'%'; FLUSH PRIVILEGES;"
    return
  fi

  exists=$(myq -N -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$db'")
  if [ -z "$exists" ]; then
    echo "   FAIL: host-mode database '$db' does not exist or is not visible to $ROOT_USER." >&2
    exit 1
  fi
  mapfile -t tables < <(myq -N -e "SELECT CONCAT('\`', table_schema, '\`.\`', table_name, '\`')
                                 FROM information_schema.tables
                                 WHERE table_schema='$db' AND table_type='BASE TABLE'
                                 ORDER BY table_name")
  if [ "${#tables[@]}" -gt 0 ]; then
    printf -v drop_list '%s,' "${tables[@]}"
    drop_list="${drop_list%,}"
    myq -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE $drop_list; SET FOREIGN_KEY_CHECKS=1;"
  fi
}

snapshot() {  # snapshot <db> <outfile>  →  "table count checksum" per base table, sorted
  local db="$1" out="$2" tables union cklist
  tables=$(myq -N -e "SELECT table_name FROM information_schema.tables
                      WHERE table_schema='$db' AND table_type='BASE TABLE' ORDER BY table_name")
  union=""; cklist=""
  for t in $tables; do
    [ -n "$union" ]  && union="$union UNION ALL "
    union="${union}SELECT '$t', COUNT(*) FROM \`$db\`.\`$t\`"
    [ -n "$cklist" ] && cklist="$cklist, "
    cklist="$cklist\`$db\`.\`$t\`"
  done
  declare -A CNT CKS
  while read -r t n;   do CNT[$t]=$n;        done < <(myq -N -e "$union")
  while read -r tb ck; do CKS[${tb#"$db".}]=$ck; done < <(myq -N -e "CHECKSUM TABLE $cklist")
  for t in $tables; do printf '%s %s %s\n' "$t" "${CNT[$t]}" "${CKS[$t]}"; done | sort > "$out"
}

assert_nonzero() {
  local label="$1" count="$2"
  if [[ ! "$count" =~ ^[0-9]+$ ]] || [ "$count" -le 0 ]; then
    echo "   FAIL: restored $label must be nonzero (got '${count:-empty}')." >&2
    exit 1
  fi
  echo "   PASS: $label = $count."
}

assert_same() {
  local label="$1" source_count="$2" restored_count="$3"
  if [ "$source_count" -ne "$restored_count" ]; then
    echo "   FAIL: $label source/restored counts differ ($source_count != $restored_count)." >&2
    exit 1
  fi
  echo "   PASS: $label source/restored counts match ($source_count)."
}

echo "== Backup → restore rehearsal (operations.md §7) =="

echo "-- 1. Build + seed source DB ($SRC)"
reset_db "$SRC"
DB_DATABASE="$SRC" "$PHP_BIN" bin/console migrate >/dev/null
DB_DATABASE="$SRC" "$PHP_BIN" tests/browser/seed.php >/dev/null
echo "   seeded."

echo "-- 2. Snapshot source"
SRC_SNAP="$(mktemp)"; snapshot "$SRC" "$SRC_SNAP"
SRC_TABLES=$(wc -l < "$SRC_SNAP")
SRC_ROWS=$(awk '{s+=$2} END{print s+0}' "$SRC_SNAP")
if [ "$SRC_TABLES" -eq 0 ]; then
  echo "   FAIL: source snapshot is empty; check DB_* env and DB_CONTAINER alignment." >&2
  exit 1
fi
echo "   $SRC_TABLES tables, $SRC_ROWS rows."

echo "-- 3. Back up with mariadb-dump"
mydump --single-transaction --routines --triggers "$SRC" > "$DUMP"
DUMP_BYTES=$(wc -c < "$DUMP")
echo "   wrote $DUMP ($DUMP_BYTES bytes)."

echo "-- 4. Restore into a fresh DB ($DST)"
reset_db "$DST"
myq "$DST" < "$DUMP"
echo "   restored."

echo "-- 5. Snapshot restore + diff vs source"
DST_SNAP="$(mktemp)"; snapshot "$DST" "$DST_SNAP"
if diff -u "$SRC_SNAP" "$DST_SNAP"; then
  echo "   PASS: every table's row count AND checksum match the source."
else
  echo "   FAIL: restored data differs from the source." >&2; exit 1
fi

echo "-- 6. Source/restored Thread Intelligence lifecycle is populated"
SRC_TI_JOBS=$(myq -N -e "SELECT COUNT(*) FROM \`$SRC\`.thread_intelligence_jobs")
SRC_TI_GENERATIONS=$(myq -N -e "SELECT COUNT(*) FROM \`$SRC\`.thread_intelligence_generations")
SRC_TI_AI_SUMMARIES=$(myq -N -e "SELECT COUNT(*) FROM \`$SRC\`.thread_summaries WHERE kind = 'ai'")
SRC_TI_CITATIONS=$(myq -N -e "SELECT COUNT(*)
  FROM \`$SRC\`.thread_summary_sources ss
  JOIN \`$SRC\`.thread_summaries s ON s.id = ss.summary_id
  WHERE s.kind = 'ai'")
SRC_TI_AI_OVERLAYS=$(myq -N -e "SELECT COUNT(*) FROM \`$SRC\`.related_threads
  WHERE ai_generation_id IS NOT NULL AND ai_selected = 1")
TI_JOBS=$(myq -N -e "SELECT COUNT(*) FROM \`$DST\`.thread_intelligence_jobs")
TI_GENERATIONS=$(myq -N -e "SELECT COUNT(*) FROM \`$DST\`.thread_intelligence_generations")
TI_AI_SUMMARIES=$(myq -N -e "SELECT COUNT(*) FROM \`$DST\`.thread_summaries WHERE kind = 'ai'")
TI_CITATIONS=$(myq -N -e "SELECT COUNT(*)
  FROM \`$DST\`.thread_summary_sources ss
  JOIN \`$DST\`.thread_summaries s ON s.id = ss.summary_id
  WHERE s.kind = 'ai'")
TI_AI_OVERLAYS=$(myq -N -e "SELECT COUNT(*) FROM \`$DST\`.related_threads
  WHERE ai_generation_id IS NOT NULL AND ai_selected = 1")
assert_nonzero "source thread_intelligence_jobs rows" "$SRC_TI_JOBS"
assert_nonzero "source thread_intelligence_generations rows" "$SRC_TI_GENERATIONS"
assert_nonzero "source kind='ai' thread_summaries rows" "$SRC_TI_AI_SUMMARIES"
assert_nonzero "source AI summary citations" "$SRC_TI_CITATIONS"
assert_nonzero "source selected AI relationship overlays" "$SRC_TI_AI_OVERLAYS"
assert_nonzero "restored thread_intelligence_jobs rows" "$TI_JOBS"
assert_nonzero "restored thread_intelligence_generations rows" "$TI_GENERATIONS"
assert_nonzero "restored kind='ai' thread_summaries rows" "$TI_AI_SUMMARIES"
assert_nonzero "restored AI summary citations" "$TI_CITATIONS"
assert_nonzero "restored selected AI relationship overlays" "$TI_AI_OVERLAYS"
assert_same "thread_intelligence_jobs" "$SRC_TI_JOBS" "$TI_JOBS"
assert_same "thread_intelligence_generations" "$SRC_TI_GENERATIONS" "$TI_GENERATIONS"
assert_same "kind='ai' thread_summaries" "$SRC_TI_AI_SUMMARIES" "$TI_AI_SUMMARIES"
assert_same "AI summary citations" "$SRC_TI_CITATIONS" "$TI_CITATIONS"
assert_same "selected AI relationship overlays" "$SRC_TI_AI_OVERLAYS" "$TI_AI_OVERLAYS"

echo "-- 7. Restored schema is complete (migrate is a no-op)"
MIG=$(DB_DATABASE="$DST" "$PHP_BIN" bin/console migrate)
echo "   $MIG"
case "$MIG" in *"Nothing to migrate"*) echo "   PASS: no pending migrations.";;
  *) echo "   FAIL: restored schema was incomplete." >&2; exit 1;; esac
echo "   migrate:status:"
DB_DATABASE="$DST" "$PHP_BIN" bin/console migrate:status | sed 's/^/   /'

echo "-- 8. Reconcile counters/reputation on the restore (runbook step 4)"
DB_DATABASE="$DST" "$PHP_BIN" bin/console repair >/dev/null && echo "   PASS: repair ran clean."

echo "-- 9. Boot the app on the restored DB and serve the home page"
SRV_LOG="$(mktemp)"
DB_DATABASE="$DST" SESSION_SECURE=false MAIL_DRIVER=array APP_URL="http://127.0.0.1:$PORT" \
  "$PHP_BIN" -S 127.0.0.1:"$PORT" -t public public/index.php >"$SRV_LOG" 2>&1 &
SRV=$!; trap 'kill "$SRV" 2>/dev/null || true; rm -f "$SRV_LOG"' EXIT
sleep 2
if ! kill -0 "$SRV" 2>/dev/null; then
  echo "   FAIL: php -S did not stay up on port $PORT (set BACKUP_REHEARSAL_PORT or stop anything already listening there)." >&2
  sed 's/^/   /' "$SRV_LOG" >&2 || true
  exit 1
fi
CODE=$("$CURL_BIN" -s -o "$CURL_NULL" -w '%{http_code}' "http://127.0.0.1:$PORT/")
BODY_OK=$("$CURL_BIN" -s "http://127.0.0.1:$PORT/" | grep -c 'Announcements' || true)
if [ "$CODE" = "200" ] && [ "$BODY_OK" -ge 1 ]; then
  echo "   PASS: home returned 200 and rendered restored content."
else
  echo "   FAIL: app did not serve restored data (status $CODE)." >&2; exit 1
fi

echo
echo "== REHEARSAL PASSED =="
echo "Backup: $DUMP_BYTES bytes · $SRC_TABLES tables · $SRC_ROWS rows · restore verified byte-for-byte by row count + CHECKSUM TABLE, schema intact, app boots."
