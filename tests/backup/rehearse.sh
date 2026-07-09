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
#   6. assert the restored schema is complete (`migrate` is a no-op)
#   7. reconcile counters/reputation         (`repair`, runbook step 4)
#   8. boot the app on the restored DB        (home page serves seeded content)
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
SRC="${DB_BACKUP_SRC:-retroboards_backup_src}"
DST="${DB_BACKUP_DST:-retroboards_backup_dst}"
PORT="${BACKUP_REHEARSAL_PORT:-8021}"
DUMP="$(mktemp -d)/retroboards-backup.sql"

# rootpw is the fixed local rb-mariadb dev-container password (see project README);
# this script only touches throwaway rehearsal databases — never a production credential.
if [ "$CONTAINER" = "host" ]; then
  myq()   { "$MYSQL_CLIENT"     "-h$DB_HOST" "-P$DB_PORT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
  mydump(){ "$MYSQLDUMP_CLIENT" "-h$DB_HOST" "-P$DB_PORT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
else
  myq()   { docker exec -i "$CONTAINER" "$MYSQL_CLIENT"     "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
  mydump(){ docker exec    "$CONTAINER" "$MYSQLDUMP_CLIENT" "-u$ROOT_USER" "-p$ROOT_PASSWORD" "$@" 2>/dev/null; }
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

echo "== Backup → restore rehearsal (operations.md §7) =="

echo "-- 1. Build + seed source DB ($SRC)"
reset_db "$SRC"
DB_DATABASE="$SRC" php bin/console migrate >/dev/null
DB_DATABASE="$SRC" php tests/browser/seed.php >/dev/null
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

echo "-- 6. Restored schema is complete (migrate is a no-op)"
MIG=$(DB_DATABASE="$DST" php bin/console migrate)
echo "   $MIG"
case "$MIG" in *"Nothing to migrate"*) echo "   PASS: no pending migrations.";;
  *) echo "   FAIL: restored schema was incomplete." >&2; exit 1;; esac
echo "   migrate:status:"
DB_DATABASE="$DST" php bin/console migrate:status | sed 's/^/   /'

echo "-- 7. Reconcile counters/reputation on the restore (runbook step 4)"
DB_DATABASE="$DST" php bin/console repair >/dev/null && echo "   PASS: repair ran clean."

echo "-- 8. Boot the app on the restored DB and serve the home page"
SRV_LOG="$(mktemp)"
DB_DATABASE="$DST" SESSION_SECURE=false MAIL_DRIVER=array APP_URL="http://127.0.0.1:$PORT" \
  php -S 127.0.0.1:"$PORT" -t public public/index.php >"$SRV_LOG" 2>&1 &
SRV=$!; trap 'kill "$SRV" 2>/dev/null || true; rm -f "$SRV_LOG"' EXIT
sleep 2
if ! kill -0 "$SRV" 2>/dev/null; then
  echo "   FAIL: php -S did not stay up on port $PORT (set BACKUP_REHEARSAL_PORT or stop anything already listening there)." >&2
  sed 's/^/   /' "$SRV_LOG" >&2 || true
  exit 1
fi
CODE=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/")
BODY_OK=$(curl -s "http://127.0.0.1:$PORT/" | grep -c 'Announcements' || true)
if [ "$CODE" = "200" ] && [ "$BODY_OK" -ge 1 ]; then
  echo "   PASS: home returned 200 and rendered restored content."
else
  echo "   FAIL: app did not serve restored data (status $CODE)." >&2; exit 1
fi

echo
echo "== REHEARSAL PASSED =="
echo "Backup: $DUMP_BYTES bytes · $SRC_TABLES tables · $SRC_ROWS rows · restore verified byte-for-byte by row count + CHECKSUM TABLE, schema intact, app boots."
