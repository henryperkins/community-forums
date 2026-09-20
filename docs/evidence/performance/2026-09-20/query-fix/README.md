# Request query reuse and batched unread positions

Verified locally on 2026-09-20 against the working tree based on
`2d925557de4f78753e443caecb7eecb428250171`. This evidence uses disposable local
fixtures. At capture time, the optimization was local; production response-time
improvements have not been measured.

## Result

| Request | Before | After | Reads removed |
| --- | ---: | ---: | ---: |
| Member home, fresh session/presence | 19 | 17 | 2 |
| Member thread, first visit | 57 | 49 | 8 |
| Member thread, repeat visit | 58 | 50 | 8 |
| Member thread, one unread reply | 62 | 53 | 9 |
| Member thread, six unread replies | 72 | 53 | 19 |
| Guest thread | 27 | 26 | 1 |
| Notification bell, fresh session/presence | 10 | 9 | 1 |

The same [28-scenario harness](../server-attribution.php), run with `--plain`,
produced [before.json](before.json) and
[after.json](after.json). [comparison.json](comparison.json) includes every
scenario, including stale presence/session, wiki, references, polls, and controls.
HTTP statuses remain unchanged. Database connections are injected in these
fixtures; the counts exclude connection setup and transaction-control exchanges.
Transaction boundaries and cursor locking remain unchanged.

Five metadata reads no longer repeat after ordinary member cursor persistence
(four for the admin fixture). Notification scope reuses the membership and
moderator lists, and Living Brief eligibility/progress shares its eligible-post
count. Six unread links now resolve their page positions in one database query
instead of twelve; a single unread link needs one query instead of two.

At the earlier diagnosis's 33–45 ms per database exchange, five removed metadata
reads model 165–225 ms saved. All eight removed reads on an ordinary thread model
264–360 ms; nineteen on the six-unread fixture model 627–855 ms. These are
transport estimates, not observed production response-time improvements.

## Correctness and query plans

Only cursor and since-last-read persistence opt into table-scoped cache
invalidation. All other writes retain full invalidation by default. Reads without
declared dependencies are always evicted on a mutation. Nested transactions
accumulate every affected table, including unknown writes, so rollback cannot
leave a value loaded from an uncommitted write in the cache. Explicitly fresh
notification checks also bypass shared repository snapshots.

Regression coverage verifies cursor visibility/monotonicity, metadata reuse,
permission revocation, commit/rollback and nested invalidation, fresh CLI reads,
Living Brief counts after post mutations, and constant query counts for one
versus six unread items. Post positions preserve `(created_at, id)` ordering,
timestamp ties, moved posts, pending/deleted visibility, foreign-thread targets,
and page-one fallback under both native and emulated prepares.

[explain-batch.php](explain-batch.php) captures the actual repository query on a
1,001-post local fixture; [explain-batch.json](explain-batch.json) records EXPLAIN
and MariaDB ANALYZE output. Both member and staff rank subqueries use the existing
`idx_posts_thread_read` index. Member ranking uses its thread/deleted/pending
prefix; staff ranking uses its thread prefix to include deleted stubs. The rank
still scans eligible rows within the thread for each visible target; this change
reduces exchanges, not the number of rank computations. No schema/index change
is required. The probe rolls back all fixture posts.

## Verification

- [phpunit.txt](phpunit.txt): 2,968 tests, 22,425 assertions, zero failures;
  six existing deprecations and one existing skip. Command:
  `MAIL_DRIVER=sendmail MAIL_FROM='' COMPOSER_PROCESS_TIMEOUT=0 composer test`.
- [green-focused.txt](green-focused.txt): 85 tests / 492 assertions covering
  cache, cursor, performance, eligibility, context, and reading preferences.
- [red.txt](red.txt) and [red-nested-rollback.txt](red-nested-rollback.txt):
  regression failures observed before their fixes.
- The first full suite caught a SQL-attribution test that recognized only direct
  notification-service queries. Its instrumentation now includes delegated
  repository reads; all six notification shell tests pass, and the full rerun
  above passes. [phpunit-initial.txt](phpunit-initial.txt) preserves that result.
- [browser.txt](browser.txt): three Playwright tests pass for Living Brief
  provenance, native Catch me up disclosure with JavaScript disabled, and the
  unread boundary. Fresh screenshots are in [browser/](browser/).
- `git diff --check` passes. No template, asset, migration, or build output changed.

The browser run used `retroboards_queries_e2e_20260920` and port 8043 with separate
rate-limit and package stores. [cleanup.json](cleanup.json) verifies that the
database, temporary grants, server, and stores are gone.
[browser-evidence-restored.json](browser-evidence-restored.json) verifies that
all four pre-existing screenshot files were restored byte-for-byte. Attribution
cleanup is recorded in [before-cleanup.txt](before-cleanup.txt) and
[after-cleanup.txt](after-cleanup.txt). No production credentials or fixtures were
used for this optimization.
