#!/usr/bin/env bash
# Backup → restore rehearsal (PHASE_2_RUNBOOK §7).
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
# Uses the local rb-mariadb dev container (no host MySQL client needed) and the
# dedicated retroboards_backup_{src,dst} databases — never dev/test/prod data.
#
#   tests/backup/rehearse.sh                 # human run
#   tests/backup/rehearse.sh | tee docs/evidence/backup-restore/rehearsal.log
set -euo pipefail

cd "$(dirname "$0")/../.."   # repo root

CONTAINER=rb-mariadb
SRC=retroboards_backup_src
DST=retroboards_backup_dst
PORT=8021
DUMP="$(mktemp -d)/retroboards-backup.sql"

# rootpw is the fixed local rb-mariadb dev-container password (see project README);
# this script only touches throwaway rehearsal databases — never a production credential.
myq()   { docker exec -i "$CONTAINER" mariadb     -uroot -prootpw "$@" 2>/dev/null; }
mydump(){ docker exec    "$CONTAINER" mariadb-dump -uroot -prootpw "$@" 2>/dev/null; }

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

echo "== Backup → restore rehearsal (PHASE_2_RUNBOOK §7) =="

echo "-- 1. Build + seed source DB ($SRC)"
myq -e "DROP DATABASE IF EXISTS \`$SRC\`;
        CREATE DATABASE \`$SRC\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        GRANT ALL PRIVILEGES ON \`$SRC\`.* TO 'retro'@'%'; FLUSH PRIVILEGES;"
DB_DATABASE="$SRC" php bin/console migrate >/dev/null
DB_DATABASE="$SRC" php tests/browser/seed.php >/dev/null
echo "   seeded."

echo "-- 2. Snapshot source"
SRC_SNAP="$(mktemp)"; snapshot "$SRC" "$SRC_SNAP"
SRC_TABLES=$(wc -l < "$SRC_SNAP")
SRC_ROWS=$(awk '{s+=$2} END{print s}' "$SRC_SNAP")
echo "   $SRC_TABLES tables, $SRC_ROWS rows."

echo "-- 3. Back up with mariadb-dump"
mydump --single-transaction --routines --triggers "$SRC" > "$DUMP"
DUMP_BYTES=$(wc -c < "$DUMP")
echo "   wrote $DUMP ($DUMP_BYTES bytes)."

echo "-- 4. Restore into a fresh DB ($DST)"
myq -e "DROP DATABASE IF EXISTS \`$DST\`;
        CREATE DATABASE \`$DST\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        GRANT ALL PRIVILEGES ON \`$DST\`.* TO 'retro'@'%'; FLUSH PRIVILEGES;"
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

echo "-- 7. Reconcile counters/reputation on the restore (runbook step 4)"
DB_DATABASE="$DST" php bin/console repair >/dev/null && echo "   PASS: repair ran clean."

echo "-- 8. Boot the app on the restored DB and serve the home page"
DB_DATABASE="$DST" SESSION_SECURE=false MAIL_DRIVER=array APP_URL="http://127.0.0.1:$PORT" \
  php -S 127.0.0.1:"$PORT" -t public public/index.php >/dev/null 2>&1 &
SRV=$!; trap 'kill "$SRV" 2>/dev/null || true' EXIT
sleep 2
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
