# Graduate `wysiwyg_composer` to default-ON — design

**Date:** 2026-07-02
**Owner request:** "I don't want Milkdown to be optional." Scope confirmed with Henry: **graduate the flag to default-true; the flag itself stays** (operator rollback preserved). Deeper variants (deleting the flag, removing source mode) were offered and not chosen.
**Pattern:** the established graduation ritual (`polls` 2026-06-30, `topic_workflow` 2026-07-01; see `docs/evidence/deploy-dark-features.md` "graduation pattern").

## Context

PR #33 shipped the Milkdown WYSIWYG layer deploy-dark behind `wysiwyg_composer` (ADR 0013), *with its acceptance evidence already landed*: `wysiwyg-composer.spec.ts` (CSP, submit, source-mode round trip, no-op edit, preview parity, chips, URL paste, mobile smoke, fallback), a11y scans, `AppComposerTest`/`AppComposerSuggestTest`/`MarkdownRoundTripTest`, and `npm run check:wysiwyg`. The "deploy-dark until evidence lands" condition is therefore already satisfied; graduation is a posture flip plus the documented ritual.

`rich_composer` is already default-ON, so flipping `wysiwyg_composer` alone makes Milkdown the out-of-the-box editor.

## What changes

### 1. Flag default — `src/Core/FeatureFlags.php`

`'wysiwyg_composer' => true` with the GA comment form: `// Milkdown WYSIWYG layer over canonical Markdown textarea — GA default-on (2026-07-02; reversible via features override)`.

No other code changes. `templates/layout.php` gating, the adapter, and the composer bridge are already flag-driven.

### 2. PHPUnit — flip the two tests that assume dark

- `tests/Integration/Core/AppFeatureFlagTest.php`: replace `test_wysiwyg_composer_defaults_dark_and_is_independently_reversible` with `test_wysiwyg_composer_is_available_by_default_and_can_be_disabled`, mirroring the `topic_workflow` graduated test. The flag is an asset/attribute layer (not route-gated), so the observable assertions are HTTP-body ones: a board page contains `data-wysiwyg-composer="1"` and the bundle tags by default; neighbour isolation (`group_dms` stays false); `wysiwyg_composer=false` override removes them; the `rich_composer=false` kill-switch interplay assertion is retained.
- `tests/Integration/Core/AppComposerTest.php` (`test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled`): the default-state expectation inverts — default page now contains `/assets/wysiwyg-composer.js`/`.css`; explicit `wysiwyg_composer=false` omits them; `rich_composer=false` still omits them.

TDD order: rewrite tests first, observe red, flip the default, observe green.

### 3. Browser evidence

- `tests/browser/seed.php` `$evidenceFeatures`: add **`'wysiwyg_composer' => false`** with a comment. Rationale: `gate-a.spec.ts` (and the drafts journey) drive `textarea.composer-input` directly — six interactions including a `toBeVisible()` — which a mounted Milkdown hides (`is-wysiwyg-source-hidden`). Gate-a therefore continues to capture the progressive-enhancement textarea baseline; the rich surface's browser evidence lives in the dedicated `wysiwyg-composer.spec.ts` + a11y scans, which set the flag explicitly per test (`workers: 1`, no races). This deliberately deviates from the ritual's "seed `=> true`" step; the deviation is the documentation.
- `tests/browser/wysiwyg-composer.spec.ts`: extend the flag helper with an *unset* mode (remove the key from the features override) and use it in one mount test, proving in a real browser that Milkdown mounts under the **true default**, not a forced override.

### 4. Docs

- `docs/runbooks/wysiwyg_composer.md`: graduated banner ("Default-ON as of 2026-07-02"; golden rule: for any editor defect, disable `wysiwyg_composer` first — non-destructive, textarea composer keeps serving; `rich_composer=false` stays the broad emergency switch), mirroring `topic_workflow.md`.
- `docs/evidence/deploy-dark-features.md`: bump date; rewrite the `wysiwyg_composer` row to the "**Graduated 2026-07-02 — now default-ON**" form; add the Notes bullet.
- `PHASE_5_STATUS.md`: record the graduation (the WYSIWYG stream landed via PR #33 alongside Inc 4).
- `CLAUDE.md` flags paragraph: note the `wysiwyg_composer` graduation with runbook pointer.
- `COMPOSER.md`: the §"priority vs phase" note ("deploy-dark behind `wysiwyg_composer` per ADR 0013") becomes "default-ON as of 2026-07-02"; changelog gains v0.6.
- `DESIGN.md`/`DECISIONS.md`: audit for stale "deploy-dark wysiwyg" posture claims; update only if they assert the default.
- ADR 0013 is **not** edited: its consequences ("operators can roll back by setting `wysiwyg_composer=false`") remain true.

## Non-goals / deferred (recorded, not dropped)

- **Full evidence-set regeneration.** ~100 evidence PNGs are already dirty in the working tree from a concurrent admin/anti-abuse workstream; regenerating now would interleave churn. Also gate-a composer flows would need wysiwyg-aware interaction patterns before capturing Milkdown-on screenshots. Follow-up: after the admin workstream lands, decide whether gate-a should capture Milkdown-by-default (rewriting its composer interactions) or keep the textarea-baseline pin.
- Removing the flag, the source-mode toggle, or the textarea (the textarea is the submit source and no-JS baseline; removing it is a DECISIONS-level change nobody asked for).
- The four theme-related fail-dark follow-ups from the PR #33 review (unrelated).

## Risks

- Tests that silently assumed the flag dark → caught by the full-suite gate (the ritual's step 6).
- Future full evidence runs will show Milkdown in composer screenshots unless the gate-a pin is kept — intentional, documented in the seed comment.
- No schema, migrations, counters, CSP, or route changes.

## Verification gates

1. `composer test` full suite green.
2. `npm run check:wysiwyg` (deterministic bundle unchanged).
3. `cd tests/browser && npm run prepare-db && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"` green, including the new unset-mode default proof and the existing `wysiwyg_composer=false` fallback test.

## Process note

Henry was away when the sequencing question timed out; proceeding with the recommended "alongside" option: this branch stages **only graduation files**, the admin workstream's uncommitted edits are never touched, and full PNG regeneration is deferred. Gates auto-approved in his absence are listed here for review.
