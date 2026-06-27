# Backup → restore evidence (PHASE_2_RUNBOOK §7)

Evidence that a `mariadb-dump` backup of a populated RetroBoards database restores
into a fresh database with **no data loss** and a **fully intact schema** — the
Gate A "backup-restore rehearsal" item.

## How it was produced

`tests/backup/rehearse.sh` (reproducible) performs the full cycle against the local
`rb-mariadb` dev container, using dedicated `retroboards_backup_{src,dst}` databases
(never dev/test/prod data):

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

## Result (see `rehearsal.log`)

```
== REHEARSAL PASSED ==
Backup: 49197 bytes · 34 tables · 76 rows · restore verified byte-for-byte by
row count + CHECKSUM TABLE, schema intact, app boots.
```

- **34 tables / 76 rows** backed up and restored.
- **Row counts and `CHECKSUM TABLE` identical** for every base table (source vs restore).
- Restored schema complete — `migrate` reports *Nothing to migrate*.
- `repair` runs clean on the restore; the app returns **200** and renders the
  seeded content.

This rehearses the documented restore procedure; **production backups themselves**
(scheduling, off-host retention) remain an operator responsibility.
