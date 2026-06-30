# Phase 3 closeout evidence

**Date:** 2026-06-30

This note records the evidence produced in the final Phase 3 engineering
closeout pass. It is not a product-owner acceptance record.

## Commands

| Command | Result |
|---|---|
| `vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php` | 8 tests / 35 assertions passed |
| `cd tests/browser && npx playwright test -g "phase 3 composer"` | 2 tests passed |
| `cd tests/browser && npm run evidence` | 27 passed / 1 skipped; regenerates desktop/mobile screenshots |
| `cd tests/browser && npm run evidence:prodlike` | 27 passed / 1 skipped against `http://127.0.0.1:8021`; regenerated desktop/mobile screenshots |
| `cd tests/browser && npm run evidence:dark:prodlike` | 6 passed against `http://127.0.0.1:8021`, including server-draft conflict evidence |
| `cd tests/browser && npm run a11y:prodlike` | 4 passed against admin/member dark surfaces |
| `RB_TEST_FRESH=1 composer test` | 803 tests / 3236 assertions passed |
| `APP_ENV=local DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force` | 17/17 upgrade checks passed |
| `./vendor/bin/phpunit tests/Integration/Worker/NotificationEmailWorkerTest.php tests/Integration/Admin/AppAdminEmailTest.php tests/Integration/Core/AppAdminArchiveTest.php tests/Integration/Core/AppServerDraftsTest.php tests/Integration/Core/AppModerationAppealsTest.php tests/Integration/Core/AppThreadSplitMergeTest.php tests/Integration/Core/AppAccountLifecycleTest.php tests/Integration/Worker/ServerExtensionWorkerTest.php tests/Integration/Admin/AppAdminExtensionsTest.php` | 71 tests / 347 assertions passed |
| `tests/backup/rehearse.sh` with production-like DB env | Restore rehearsal passed; latest saved closeout log is `docs/evidence/backup-restore/prodlike-rehearsal-2026-06-30.log`; source snapshot had 105 tables / 116 rows, restored checksum matched, migration status was all applied |
| Prodlike worker smoke: `docker compose -f tests/prodlike/compose.yml exec -T app php bin/console ...` for `worker:email 100`, `worker:digest`, `worker:drafts`, `worker:attachments`, `worker:attachment-scans 60`, `worker:webhooks 100`, `worker:extensions 100` | All commands exited 0 against `retroboards_prodlike`; queues reported no pending work after reset |
| `docker compose -f tests/prodlike/compose.yml run --rm k6 run /scripts/phase3-load.js` | Full 20-VU / 15-minute gate was skipped at operator direction on 2026-06-30. Current `docs/evidence/phase3-load/phase3-load-summary.json` is an interrupted 301-second diagnostic run, not final load/soak acceptance evidence. |

Browser plugin tooling was not available in this Codex tool context, so the
repository's Playwright harness was used directly.

## Browser Evidence

`tests/browser/gate-a.spec.ts` now captures 19 named surfaces for both desktop and
mobile. The Phase 3-specific paths are:

- `15-reading-preferences`
- `16-drafts-view`
- `17-composer-upload`
- `18-branding-preview`
- `19-tour-replay`

The composer journey asserts:

- reading preferences default to 20/20;
- toolbar state and preview render bold Markdown and `:smile:` as an emoji;
- local drafts survive reload, appear in Drafts, and discard cleanly;
- image drop/upload creates a media Markdown reference and alt text can be edited;
- successful topic submission clears the submitted local draft.

The branding/tour journey asserts:

- admin branding preview updates name and colors client-side;
- replay-tour entry point is visible when the feature is enabled;
- the tour dialog exposes `aria-modal`, responds to Escape, and restores focus.

## Remaining Non-Code Sign-Offs

The following must be accepted by the appropriate owner before a production Phase
3 release is formally closed:

- product-owner Phase 3 acceptance;
- production-like 15-minute load/soak evidence on the target deployment profile;
- formal accessibility/assistive-technology audit;
- formal security/privacy review.

## Boundary Decisions

- Server-side draft sync is implemented deploy-dark under ADR 0010. Closeout
  evidence treats it as a dark surface, not an always-on Phase 3 launch default.
- Public/untrusted plugin runtime is not a Phase 3 deliverable. ADR 0011 keeps it
  behind the Phase 5 Gate B sandbox/security boundary.
