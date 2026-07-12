# Thread Intelligence Graduation Evidence Index

**Posture:** both production defaults changed to `true` on 2026-07-12 with
independent explicit-false rollback pins; every graduation gate is complete.

default_on: complete

- [x] live_eval: gate PASS — 46/46 runs, 149/149 supported claims, selected effort low, ceiling 16000, run 2026-07-12 02:59 UTC — `docs/evidence/phase4-closeout/thread-intelligence-live-eval.md`
- [x] human_rubric: interactive reviewer grades, every fixture/effort quality_pass — `docs/evidence/phase4-closeout/thread-intelligence-live-rubric.json`
- [x] browser_desktop: thread-intelligence.spec.ts 12 passed (6 desktop), run 2026-07-12 10:06 UTC, captures 75-79 refreshed — `docs/evidence/browser/desktop/75-thread-intelligence-fallback.png`
- [x] browser_mobile: thread-intelligence.spec.ts 12 passed (6 mobile), same 2026-07-12 10:06 UTC run — `docs/evidence/browser/mobile/75-thread-intelligence-fallback.png`
- [x] no_js: no-JS|axe subset 4 passed, 2026-07-12 10:07 UTC, commands recorded in — `docs/evidence/phase4-closeout/thread-intelligence-operations.md`
- [x] a11y: scoped axe scans, zero serious/critical findings (same 4-passed subset) — `docs/evidence/phase4-closeout/thread-intelligence-operations.md`
- [x] security_privacy: ThreadIntelligenceAdversarialTest OK, 4 tests / 94 assertions, 2026-07-12 09:51 UTC — `docs/evidence/phase4-closeout/thread-intelligence-security-privacy.md`
- [x] worker_concurrency: ThreadIntelligenceConcurrencyTest OK, 6 tests / 25 assertions, 2026-07-12 09:51 UTC — `docs/evidence/phase4-closeout/thread-intelligence-security-privacy.md`
- [x] migration_upgrade: fresh 77-migration install + status, dedicated 0077 down/up rehearsal OK (1 test / 26 assertions), verify:upgrade PASS 17/17, 2026-07-12 09:51 UTC — `docs/evidence/phase4-closeout/thread-intelligence-operations.md`
- [x] backup_restore: rehearse.sh REHEARSAL PASSED — 116 tables, 744 rows, checksums matched, TI lifecycle rows 12/18/12/96/8, 2026-07-12 09:55 UTC — `docs/evidence/phase4-closeout/thread-intelligence-operations.md`
- [x] runtime_rollback: data-preserving sequence OK, 1 test / 30 assertions, 2026-07-12 09:51 UTC — `docs/evidence/phase4-closeout/thread-intelligence-rollback.md`
- [x] runbook: operator runbook current (environment, worker schedule, recovery, budgets, retention, data-preserving rollback order) — `docs/runbooks/thread_intelligence.md`
- [x] post_flip_double_suite: fresh + reused complete suites both OK at 2177 tests / 12564 assertions / 1 skipped, 2026-07-12 13:05–13:17 UTC — `docs/evidence/phase4-closeout/thread-intelligence-operations.md`

Entries become checked only after the named command has passed and its UTC run,
count, and artifact path have been recorded.
