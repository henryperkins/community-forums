# Backup → restore evidence (PHASE_2_RUNBOOK §7)

Evidence that a `mariadb-dump` backup of a populated RetroBoards database restores
into a fresh database with **no data loss** and a **fully intact schema** — the
Gate A "backup-restore rehearsal" item.

## How it was produced

`tests/backup/rehearse.sh` (reproducible) performs the full cycle against the local
`rb-mariadb` dev container by default, using dedicated `retroboards_backup_{src,dst}`
databases (never dev/test/prod data):

1. Build + seed a source DB (reuses the deterministic `tests/browser/seed.php`).
2. Snapshot it — per-table **row count** and **`CHECKSUM TABLE`**.
3. Back it up with `mariadb-dump --single-transaction --routines --triggers`.
4. Restore the dump into a fresh DB.
5. Snapshot the restore and **diff vs the source** — every table's count *and*
   checksum must match.
6. Assert the restored schema is complete (`php bin/console migrate` is a no-op).
7. Reconcile counters/reputation (`php bin/console repair`, runbook step 4).
8. Boot the app on the restored DB and confirm the home page serves restored content.

Re-run any time:

```bash
tests/backup/rehearse.sh | tee docs/evidence/backup-restore/rehearsal.log
```

If `tests/prodlike/compose.yml` is already running and owns `127.0.0.1:8021`, give
the temporary restore server a different port first:

```bash
BACKUP_REHEARSAL_PORT=8031 tests/backup/rehearse.sh | tee docs/evidence/backup-restore/rehearsal.log
```

For a differently named local DB container/client, keep the same app DB env and
override the container/client settings, for example:

```bash
DB_CONTAINER=forum-software-db-1 DB_ROOT_PASSWORD=root \
DB_MYSQL_CLIENT=mysql DB_MYSQLDUMP_CLIENT=mysqldump \
DB_PORT=3307 DB_PASSWORD=retro \
tests/backup/rehearse.sh | tee docs/evidence/backup-restore/rehearsal.log
```

## Current result

Latest saved closeout log: `prodlike-rehearsal-2026-06-30.log`

```
== REHEARSAL PASSED ==
Backup: 164996 bytes · 105 tables · 116 rows · restore verified byte-for-byte by
row count + CHECKSUM TABLE, schema intact, app boots.
```

- **105 tables / 116 rows** backed up and restored.
- **Row counts and `CHECKSUM TABLE` identical** for every base table (source vs restore).
- Restored schema complete — `migrate` reports *Nothing to migrate*.
- `repair` runs clean on the restore; the app returns **200** and renders the
  seeded content.

This rehearses the documented restore procedure; **production backups themselves**
(scheduling, off-host retention) remain an operator responsibility.
