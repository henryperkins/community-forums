# Post Content Rendering Evidence

Updated: 2026-07-14

## Scope

This evidence covers the shared server-rendered Markdown contract for posts,
direct messages, composer previews, and living briefs; responsive presentation;
read-time fallback for missing derived HTML; and the bounded render-cache repair
command. Canonical `body` values, write metadata, counters, notifications, and
mention events are not changed by reads or repair.

## PHP verification

- `php vendor/phpunit/phpunit/phpunit --testsuite unit` passed 577 tests and
  6,916 assertions.
- The complete `tests/Integration` suite was run in seven bounded partitions to
  stay below the Windows command-runner limit. The partitions passed 1,651
  tests and 9,275 assertions, with one intentional existing skip.
- Combined result: 2,228 tests and 16,191 assertions, with one skip.
- The focused rendering regression slice passed 97 tests and 368 assertions.
- `git diff --check` and PHP syntax checks were clean before publication.

## Browser verification

The browser database was dropped, migrated through all 77 migrations, and
seeded immediately before these runs.

- `npx playwright test thread-view-study.spec.ts` passed 18 checks with six
  project-specific skips.
- `npx playwright test rich-content.spec.ts` passed desktop and mobile semantic,
  containment, keyboard-scroll, serious/critical axe, and narrow no-JavaScript
  checks: three passed with one duplicate-project skip.
- `npx playwright test wysiwyg-composer.spec.ts --grep "server preview matches final rendered post"`
  passed in desktop and mobile projects (two passed). The preview and final
  formatted-content subtrees matched exactly.

Captured evidence:

- `browser/desktop/83-rich-content.png`
- `browser/desktop/84-rich-content-table.png`
- `browser/mobile/83-rich-content.png`
- `browser/mobile/84-rich-content-table.png`

## Operator rehearsal

On the disposable browser database, `repair:render-cache --dry-run --batch=1`,
the execute command, and a second dry run all completed successfully. Each run
scanned 123 posts and 14 thread summaries (with no DM or revision fixtures) and
reported zero changes, confirming byte-idempotence for already-current caches.
