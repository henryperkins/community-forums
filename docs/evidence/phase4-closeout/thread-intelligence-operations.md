# Thread Intelligence Operations Evidence

**Scope:** deterministic browser/a11y/no-JS capture, clean install and upgrade,
backup/restore semantics, rollback, and pre-flip regression.

**Status:** harness and rehearsals implemented; command results pending.

## Host Adaptations

- This is a Windows-native linked worktree. Status and commit evidence use
  native `git.exe` to avoid WSL CRLF noise.
- Every Windows PHP process uses the worktree `storage/cache` as
  `PHP_INI_SCAN_DIR` and Git for Windows' OpenSSL configuration.
- PHPUnit selects `DB_TEST_DATABASE`, not `DB_DATABASE`. The direct migration
  test therefore uses
  `DB_TEST_DATABASE=retroboards_thread_intelligence_clean`; console migration
  commands continue to use `DB_DATABASE`.
- Browser seed, fixture subprocesses, PHP server, and CI share one deterministic
  dummy `APP_KEY` and dummy `OPENAI_API_KEY`. The fixture injects the fake
  provider and moderator, so no provider network call occurs.
- Desktop and mobile share one evidence database. Their fixture thread titles
  are project-specific and every global latch, pause, and budget mutation is
  restored.

## Browser, No-JS, and Accessibility

The repository Playwright harness is used because the Browser plugin is not
available. The target flow is seeded public topics -> member, curator, and
operator actions -> accessible preserved output at 1280x800 and 390x844.

Commands and counts will be recorded after both required invocations finish.
Expected committed assets are `75-thread-intelligence-fallback.png` through
`79-admin-thread-intelligence.png` under both
`docs/evidence/browser/desktop/` and `docs/evidence/browser/mobile/`.

## Migration and Upgrade

The clean-install status, historical upgrade verification, and direct
fixture-free `0077` down/up command will be recorded with UTC timestamps after
fresh execution. Direct migration DDL restores `0077` in `finally`.

## Backup and Restore

`tests/backup/rehearse.sh` retains its whole-table row-count/checksum comparison
and additionally requires nonzero source and restored counts for:

- `thread_intelligence_jobs`;
- `thread_intelligence_generations`;
- published `thread_summaries.kind='ai'` rows;
- `thread_summary_sources` belonging to AI summaries; and
- selected AI relationship overlays.

The source is seeded through the same deterministic real-worker fixture used by
Playwright. Dump size, table/row counts, semantic counts, schema no-op, repair,
and restored-app boot result will be recorded after the rehearsal.

## Pre-Flip Regression

Fresh and reused-schema full-suite commands will be recorded with UTC start/end
times and exact test/assertion counts. Both runs occur while the code defaults
for `community_memory` and `automated_context` remain `false`; explicit test and
browser overrides exercise the complete product.
