# Post content rendering evidence

**Date:** 2026-07-14
**Scope:** canonical Markdown rendering for posts, direct messages, composer
preview, living briefs, and derived-cache recovery.

## Automated PHP evidence

The full PHPUnit inventory was run against a freshly migrated, dedicated
`retroboards_test_post_render` database. It was split into bounded invocations
so the Windows runner could retain an exit status for every group:

| Scope | Tests | Assertions | Result |
|---|---:|---:|---|
| Unit | 577 | 6,916 | Pass |
| Admin, API, Controller, Repository, Security integration | 224 | 1,011 | Pass |
| Core integration | 876 | 5,394 | Pass; 1 expected skip |
| Service integration | 321 | 1,386 | Pass |
| Thread Intelligence and Worker integration | 230 | 1,484 | Pass |
| **Total** | **2,228** | **16,191** | **Pass; 1 expected skip** |

The rendering-focused slice also passed independently: 97 tests and 368
assertions covering sanitizer fidelity, post/DM/living-brief read fallback,
shared presentation hooks, cache repair, custom emoji, and the CLI contract.

## Repair-command rehearsal

On a freshly migrated and seeded `retroboards_e2e` database:

```text
php bin/console repair:render-cache --dry-run --batch=1
php bin/console repair:render-cache --batch=1
php bin/console repair:render-cache --dry-run --batch=1

posts              scanned=120 changed=0
dm_messages        scanned=0   changed=0
thread_summaries   scanned=14  changed=0
post_revisions     scanned=0   changed=0
```

All three commands exited zero. The second dry run remained at zero changes,
confirming byte-comparison idempotence on the seeded dataset. Integration tests
separately prove stale-cache writes, dry-run no-write behavior, batch size one,
all four cache tables, mention-context differences, and contextual failure
reporting without a partial render batch.

## Browser and accessibility evidence

`npx playwright test thread-view-study.spec.ts rich-content.spec.ts` passed 21
tests with 7 intentional project-specific skips. A fresh-fixture rerun of
`rich-content.spec.ts` passed 3 tests with the one intentional duplicate mobile
no-JavaScript skip. The checks cover:

- desktop and 390px mobile geometry with no document-level horizontal overflow;
- server-rendered headings, ordered-list starts, task lists, fenced-code language,
  table alignment, spoilers, mentions, custom emoji, and ordinary images;
- a labelled, focusable wide-table region that scrolls with the keyboard;
- bounded ordinary images and inline custom-emoji geometry;
- a narrow JavaScript-disabled thread render; and
- zero serious or critical axe violations in the rich post body.

`npx playwright test wysiwyg-composer.spec.ts --grep "server preview matches final rendered post"`
passed on desktop and mobile. It asserts the preview and final post use the same
`formatted-content` contract and have byte-identical rendered subtrees.

Visual artifacts:

- [Desktop rich-content top](browser/desktop/83-rich-content.png)
- [Desktop table and media](browser/desktop/84-rich-content-table.png)
- [Mobile rich-content top](browser/mobile/83-rich-content.png)
- [Mobile table and media](browser/mobile/84-rich-content-table.png)

The artifacts were inspected after capture; the onboarding tour is dismissed
before screenshots so it cannot obscure the rendering evidence.
