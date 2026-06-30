# RetroBoards — Phase 2 Operations Runbook

Documented operating procedures required by `PHASE_2_PLAN.md` §10 (observability
and operating requirements) and §12 (staged release and rollback). All commands
run from the project root on the VPS. `bin/console help` lists every command.

> **Golden rule (PHASE_2_PLAN §12):** for a logic defect, **disable the feature
> flag first**, then investigate. Restore from backup only for proven data
> corruption. Keep migrations additive; never drop a Phase 1 column in the same
> release that replaces it.

## 1. Health & observability

- **Web/DB health:** `GET /healthz` returns `200` with DB status, `503` when the
  database is unreachable (it is dispatched before the DB-querying setup gate).
- **Worker heartbeat / last success:** the cron wrappers for `worker:email` and
  `worker:digest` print `sent/suppressed/failed/skipped` counts; capture stdout to
  a log and alert if the instant worker has not drained in N minutes.
- **Queue depth:** `EmailDeliveryRepository::statusCounts()` returns
  `queued/sent/failed/suppressed` totals (surface via a small status script or
  admin view). A growing `queued` with `sent=0` means the transport is down.
- **Logs:** fan-out, worker claims/sends, suppression, search errors, DM/report
  actions, and OAuth callbacks log with request context and **never** log tokens
  or private message bodies.

## 2. Feature flags — deploy dark, enable, roll back

Flags live in the `features` setting (a JSON object of `flag => bool`); code
defaults are ON. Per-flag overrides take a subsystem offline **without a data
change** (its routes 404; the core forum keeps serving). Flags:
`engagement, notifications, email, mentions, search, dms, moderation_queue,
community, oauth, presence`.

```bash
# Inspect current overrides
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
echo (new App\Repository\SettingRepository(new Database($c->get("db"))))
  ->getString("features","{}"),"\n";'

# Disable a feature (example: pause community layer) — set the full object
php -r 'require "vendor/autoload.php"; use App\Core\{Config,Database,Env};
Env::load(".env"); $c=Config::fromFile("config/config.php");
(new App\Repository\SettingRepository(new Database($c->get("db"))))
  ->set("features", ["community"=>false]);'
```

Re-enable by setting the flag back to `true` (or removing it from the object).
Rolling a feature back is the **first response** to a logic defect; it is
covered by `AppFeatureFlagTest`.

## 3. Email operations

- **Pause all email:** disable the `email` flag (in-app notifications continue),
  or stop the `worker:email` / `worker:digest` cron. Queued rows are preserved.
- **Drain the instant queue:** `php bin/console worker:email [limit]` (oldest
  first, bounded; safe to run repeatedly — at-most-once per `post:user`).
- **Send due digests:** `php bin/console worker:digest` (timezone-aware,
  watermarked; never sends twice or empty).
- **Replay failed sends:** failed rows are marked `failed` and are **not**
  auto-retried. After fixing the transport, use `/admin/email` to requeue
  individual failed rows, or requeue them in bulk:
  ```sql
  UPDATE email_deliveries SET status='queued', error=NULL WHERE status='failed';
  ```
  then run `worker:email`. The `post:user` idempotency key prevents duplicates.
- **Suppression:** bounced/complained addresses are suppressed and skipped.
  Members self-recover via the signed unsubscribe/re-subscribe flow; an operator
  can clear a row from `email_suppressions` once the inbox is healthy.
- **Sender not configured:** with `MAIL_FROM` empty the worker fails closed and
  leaves rows `queued` — configure the sender, then drain.
- **Verified-domain blocking:** when `mail.require_verified_domain` or
  `settings.email_require_verified_domain` is true, `/admin/email` must show SPF
  and DKIM as `pass` for the configured From domain before test sends or workers
  send mail. Use **Refresh SPF/DKIM status** after DNS changes. Blocked workers
  leave rows `queued` and stamp the blocked reason in `email_deliveries.error`.

## 3a. Account deletion grace (ADR 0006)

Self-serve account deletion is a 30-day reversible grace: a request flips the
account to `pending_deletion` and schedules a purge; cancelling reactivates it.
A cron worker completes due deletions by anonymising PII (email, profile,
sessions, DMs). Nothing purges until the grace window elapses, and the purge
never touches an account whose status is no longer `pending_deletion`.

- **Run the purge on cron** (same cadence as `worker:purge-ips`):
  ```bash
  php bin/console worker:purge-accounts [limit]   # default 100; idempotent + audited
  ```
- The `account_lifecycle` flag gates the member-facing export/deactivate/delete
  routes. It ships **deploy-dark**; enable it per operator once acceptance
  evidence lands. With the worker unscheduled, deletion requests never complete.

## 4. Counter & reputation reconciliation

Denormalised counters are maintained transactionally, but a repair command exists
for drift (e.g. after a restore):

```bash
php bin/console repair             # all counters + reputation (reactions + solved bonus)
php bin/console repair:counters    # post/thread/board counters only
php bin/console repair:reputation  # reputation = Σ reactions received + solved bonuses
php bin/console community:backfill-badges   # idempotent auto-badge award (cron-safe; Anniversary)
```

All are idempotent and safe to run on a live database.

## 5. Search index maintenance

Search uses MySQL/MariaDB FULLTEXT (`ft_threads_title`, `ft_posts_body`) behind
`App\Search\SearchService`. The indexes are created by migrations `0013`/`0014`.
To rebuild after a bulk import or corruption, drop and re-add on the affected
table during a maintenance window, then re-verify with `EXPLAIN`, and keep the
`search` flag off until it completes:

```sql
ALTER TABLE posts DROP INDEX ft_posts_body, ADD FULLTEXT KEY ft_posts_body (body);
```

## 6. Migrations & upgrade rehearsal

```bash
php bin/console migrate:status     # show applied/pending
php bin/console migrate            # apply pending (additive)
php bin/console verify:upgrade     # DESTRUCTIVE rehearsal on a SCRATCH db:
                                   # Phase 1 → seed → Phase 2, assert no data loss
```

Run `verify:upgrade` against a copy of production data before a real upgrade.
Deploy schema before the application code that uses it.

## 7. Restore from backup

Take backups with a transaction-consistent dump, e.g.:

```bash
mariadb-dump --single-transaction --routines --triggers retroboards > backup.sql
# restore: mariadb retroboards < backup.sql
```

1. Disable the affected feature flag(s) and pause the email workers (preserve
   queued rows for inspection — do not delete them).
2. Restore the database from the most recent good backup
   (`mariadb retroboards < backup.sql`).
3. Roll application code back only to a version that tolerates the current
   tables/columns (migrations stay additive, so the newer code usually tolerates
   an older row shape).
4. Run `php bin/console repair` to reconcile counters/reputation.
5. Run the permission smoke checks (the integration suite, or a targeted subset)
   before re-enabling flags.

This cycle is **rehearsed** by `tests/backup/rehearse.sh` (back up a seeded DB,
restore into a fresh one, and assert per-table row count + `CHECKSUM TABLE` match,
the schema is complete, and the app boots). Evidence:
`docs/evidence/backup-restore/`.

## 8. Staged enablement order (new install / first Phase 2 rollout)

1. Deploy additive migrations + dark backend code with Phase 2 flags off.
2. Enable read/star + reactions; validate reputation/counter reconciliation.
3. Enable in-app subscriptions/notifications + short-polling (email still paused).
4. Start the worker with test recipients, then enable instant email, then digests.
5. Enable mentions, search, DMs, and reports/moderation in separate flag changes.
6. Accept Gate A, then enable follows/feed, badges/solved, OAuth, and presence
   incrementally for Gate B.
