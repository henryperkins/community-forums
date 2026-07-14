# Tech debt audit — frontend JS & templates

**Date:** 2026-07-11 · **Scope:** `public/assets/` (JS + CSS), `templates/`, their build/CI wiring · **Method:** full read of app.js / composer.js / tour.js / passkeys.js / layout + key templates; grep sweeps for CSP, escaping, duplication; CSS token/dead-selector sampling; CI workflow inspection.

**Scoring:** Priority = (Impact + Risk) × (6 − Effort), each 1–5. Higher = do sooner.

## What is healthy (don't spend time here)

The strict-CSP discipline holds: zero inline `style=` attributes and zero inline `<script>` across all 90 templates; GIPHY is correctly allowlisted in `SecurityHeaders` (`connect-src`/`img-src` only when the flag+key are on). Progressive enhancement is real, not aspirational — every JS feature has a server-rendered fallback and comments document the fallback path. `app.css` is fully tokenized (no hardcoded colors outside `:root`, only 7 `!important`). The idempotency key is server-rendered in `partials/composer.php` (JS stamping is just a fallback). `package-lock.json` exists for the wysiwyg build. Anti-draft-loss is enforced server-side.

## Prioritized findings

| # | Item | Category | I | R | E | Priority |
|---|------|----------|---|---|---|----------|
| 1 | No cache-busting on core assets | Infrastructure | 3 | 4 | 1 | **35** |
| 2 | CI runs 3 of 8 Playwright specs; bundle-drift check unwired | Test | 3 | 4 | 1 | **35** |
| 3 | Body-limit constants inconsistent across JS/templates/server | Code | 3 | 3 | 1 | **30** |
| 4 | 466 KB wysiwyg bundle ships on every page, to everyone | Architecture | 4 | 3 | 2 | **28** |
| 5 | Inbox reading-pane composer is never enhanced | Code | 3 | 4 | 2 | **28** |
| 6 | Dark-theme tokens maintained twice in CSS | Code | 2 | 3 | 2 | **20** |
| 7 | Wysiwyg upgrade failure is silently swallowed | Code | 2 | 2 | 1 | **20** |
| 8 | composer.js: duplicated combobox implementations sharing one textarea | Code | 3 | 3 | 3 | **18** |
| 9 | ~160 raw `<?=` echoes erode the escape-everything invariant | Code | 2 | 2 | 2 | **16** |
| 10 | Ad-hoc z-index ladder (14 distinct values, 1–1000) | Code | 1 | 2 | 1 | **15** |
| 11 | Dead CSS families in app.css | Code | 2 | 1 | 2 | **12** |
| 12 | app.js grab-bag: 15 features, one IIFE, cross-file ordering contracts | Code | 2 | 2 | 3 | **12** |

## Detail

### 1. No cache-busting on core assets — priority 35
`templates/layout.php:40,79–83` emit bare `/assets/app.css`, `/assets/app.js`, `/assets/composer.js`, `/assets/wysiwyg-composer.js`. The repo already solves this correctly for packaged themes (`/theme/{css_digest}.css` with `public, max-age=31536000, immutable`, per `docs/phase5/registry-protocol.md`), so core assets are the inconsistent holdout. Consequence for operators today: either no long-lived caching (repeat downloads of ~196 KB gz per cold page), or — if they add nginx cache headers, which the theme pipeline encourages — stale JS/CSS after an upgrade. Under strict CSP there is no inline fallback, so a stale `composer.js` against new server HTML degrades silently.
**Fix:** stamp `?v=` from the app version (or `filemtime`) in one place in `layout.php`; document a long-cache header in the ops runbook. One-file change.
**Why now:** cheapest item on the list and it protects every future frontend deploy, including the fixes below.

### 2. CI evidence gaps — priority 35
`.github/workflows/browser-evidence.yml` runs `npm run evidence` = `gate-a`, `server-drafts`, `appeals` only. Not run in CI: `wysiwyg-composer.spec.ts` (12 tests), `a11y.spec.ts` (10), `dm-reimagine.spec.ts`, `passkeys.spec.ts`, `totp.spec.ts`. Meanwhile `wysiwyg_composer` graduated default-ON on 2026-07-02 — its spec exists but never gates anything. Separately, `npm run check:wysiwyg` (rebuild + `git diff --exit-code` on the committed 466 KB bundle) appears only in the runbook, so a hand-edited or stale bundle would ship unnoticed; for a committed minified artifact that is a supply-chain-shaped hole, not just a test gap. This sits awkwardly with DESIGN §13's own completion-evidence policy.
**Fix:** extend the `evidence` script (or add a matrix job) to run all specs; add a `check:wysiwyg` step to the workflow. CI-only change, no product code.

### 3. Body-limit constants inconsistent — priority 30
The post-body limit is *configurable* server-side (`limits.post_body_max`, default 20000, `ComposerController.php:33`) yet hardcoded as `20000` in `composer.js:9` (`BODY_MAX`), `ServerDraftRepository.php:40`, and `maxlength` in `compose.php:27`, `partials/composer.php:7`, `partials/new_thread_form.php:12`, `partials/post.php:160,195`, `thread.php:267`. Two live consequences: (a) an operator who raises `post_body_max` gets a UI that still truncates at 20000 and a draft-sync endpoint that rejects what the post endpoint accepts; (b) DM composers correctly render `maxlength="5000"` (matching `DirectMessageService::BODY_MAX`) but composer.js's counter displays "n / 20000" on those same textareas today — the counter lies on every DM page.
**Fix:** have `buildCounter` read the textarea's own `maxlength` (falls back to a `data-body-max` on `<body>`); source template `maxlength` from a view global fed by config; make `ServerDraftRepository` read the same config key.

### 4. Wysiwyg bundle ships on every page — priority 28
`layout.php:81` loads `wysiwyg-composer.js` (466 KB raw / 140 KB gz — 5× the rest of the frontend combined) whenever the flag is on. Not gated on a composer being present on the page, nor on being signed in: an anonymous visitor reading a public thread downloads the full Milkdown editor. For a product that sells "runs on a single VPS", page weight is a feature.
**Fix:** emit the module tag only when the page rendered a `form.composer` (a `View` shared flag set by the composer partial), or have `composer.js` dynamic-`import()` it on first focus. The adapter registration API (`RetroBoardsComposer.registerWysiwygAdapter`) already tolerates late arrival — that seam exists and is the right one.
**Evidence per DESIGN §13:** needs a Playwright assertion that guest thread views make no request for the bundle.

### 5. Inbox reading-pane composer never enhanced — priority 28
`composer.js:1607` enhances `form.composer` once at `DOMContentLoaded`. The Community Inbox (`app.js:183–250`) swaps thread HTML into the reading pane via `innerHTML` — including the reply composer from `thread.php:484`. Delegated handlers (autosize, reactions) survive; per-form enhancement does not. In the flagship three-pane view, replies silently lose: toolbar, preview, character counter, local+server draft autosave, paste/drop upload, slash menu, @/# reference pickers, enter-to-send, and the pending-submit draft cleanup. Draft autosave not running in the primary reading surface cuts against the anti-draft-loss principle the backend works hard to uphold.
**Fix:** export the enhancer (e.g. `RetroBoardsComposer.enhanceWithin(root)`) and call it from the inbox `loadThread` success path. Both files already coordinate via `window.RetroBoardsComposer`.

### 6. Dark tokens maintained twice — priority 20
`[data-theme="dark"]` and `@media (prefers-color-scheme: dark) [data-theme="system"]` (app.css ~line 784) each restate ~45 token overrides. Vanilla CSS can't merge a selector and a media query, so drift is structural: one edit forgotten in the twin block yields a bug visible only in explicit-dark xor system-dark — the kind that survives review.
**Fix options** (pick one): a tiny check script comparing the two blocks (cheap, keeps vanilla CSS, fits `composer test` culture); or generate both from one source via the existing vite build; or `light-dark()` once minimum browser support is acceptable. The check script is the 80/20.

### 7. Silent wysiwyg fallback — priority 20
`composer.js:98` — if the wysiwyg factory throws, `catch (e) {}` discards the error and the plain textarea is used. Correct behavior, wrong observability: operators will field "the editor looks different for some users" reports with nothing in the console and no way to distinguish a broken bundle from an intentional `data-no-wysiwyg`. Same pattern across ~20 empty catch blocks; most are legitimately fail-quiet PE, but the factory failure is a deploy-health signal.
**Fix:** `console.warn` (or a `data-` breadcrumb on the form) in the factory catch specifically. One line.

### 8. Duplicated combobox implementations — priority 18
`wireSlashMenu` (composer.js:1035–1264) and `wireReferencePickers` (1333–1508) are ~400 lines implementing the same APG combobox pattern twice: parallel `openMenu/hide/highlight/setExpanded`, parallel keydown switches, parallel outside-click closers. Both stamp `role="combobox"` on the *same textarea* and swap `aria-controls` between their two menu ids at runtime (`ownsCombobox()`/`comboboxReady()` guards). It works, but every keyboard/a11y fix must be made twice, and the shared-attribute handoff is exactly where a regression will hide. The wysiwyg adapter's optional methods (`referenceTargets`, `referenceState`, `replaceReferenceSelection`) are also an undocumented duck-typed contract — document it when touching this.
**Fix:** extract one listbox/combobox helper parameterized by trigger-state + item-renderer; single owner for the textarea's combobox attributes. Do it with the a11y spec running in CI first (#2), since that spec is the safety net.

### 9. Raw-echo convention erosion — priority 16
~160 `<?= $var ?>` echoes bypass `$e()`. Nearly all are safe constants (` checked`/` selected` ternaries, `$sel()`/`$opt()` closures), but `account/notifications.php:59–64` builds `$action`/`$link` URLs by concatenation and echoes them raw (safe today — int casts and, oddly, `$e()` *inside* the concat for `$link`). The cost is auditability: "the only sanctioned raw echo is pre-sanitized `body_html`" is no longer checkable by grep, and the pattern invites copy-paste with a user-controlled string.
**Fix:** wrap the URL echoes in `$e()`; optionally add a conformance test that greps templates for raw echoes outside an allowlist (mirrors the existing feature-flag regression-test culture).

### 10. z-index ladder — priority 15
14 distinct values (1, 6, 20, 40, 45, 46, 50, 54, 55, 60, 80, 81, 100, 1000) with no token scale, in a UI whose overlay stack is already subtle (nav drawer, DM rail, compose modal, tour popover — see the hand-ordered Escape-key layering in app.js:376–424).
**Fix:** `--z-*` tokens in `:root`, mechanical substitution.

### 11. Dead CSS — priority 12
Confirmed dead in `app.css` (no reference in templates/JS/src): the `feature-area-*`/`feature-activation-*`/`feature-status-*`/`feature-flag-list` family (an earlier `admin/features.php` design), `btn-accent`, `mod-bar`, `badge-title`, `conn-tabs`, `input-narrow`. Sampling suggests more in the 546-class inventory. Caution — two families this audit initially misflagged prove the trap: `mono-0..9` is built dynamically (`helpers.php:31`) and `chip-archived`/`chip-decision` come from `chip-<?= $statusSlug ?>` (`thread_row.php:26`). A purge needs a dynamic-prefix allowlist (`mono-`, `chip-`, theme/brand tokens), not a bare grep.

### 12. app.js grab-bag — priority 12
15 unrelated features in one 531-line IIFE; DM-only code (rail, menus, compose, search, copy, flash — ~260 lines) parses on every page. More important than size: implicit cross-file ordering contracts documented only in comments (composer.js must own Enter before app.js could; slash-menu keydown must register before `wireKeys`; Escape layering between rail/menus/compose is hand-sequenced across files). Cheap mitigation now, split later if it grows: keep the comments (they're good), add a `docs/` note naming the contracts, and prefer delegated handlers for any new DM feature so pane-swap patterns stay safe.

## Phased remediation (alongside feature work)

**Phase A — this week, no product-code risk (items 1, 2, 3, 7, 10; ~1–2 days).** Asset versioning, CI spec + `check:wysiwyg` wiring, body-limit single-sourcing, fallback warning, z-index tokens. All are one-file or CI-only changes; #2 must land first since it is the safety net for everything else.

**Phase B — next release train, Gate-A-style with browser evidence (items 4, 5; ~3–5 days).** Conditional wysiwyg loading and inbox-pane re-enhancement. Both are UI-visible, so per DESIGN §13 they need Playwright evidence (bundle-absence assertion for #4; toolbar/draft-presence-in-pane assertion for #5). Doing them after Phase A means the new specs actually run in CI.

**Phase C — opportunistic, behind the a11y net (items 6, 8, 9, 11, 12).** Combobox extraction only after `a11y.spec.ts` gates CI; dark-token check script; escaping conformance test; dead-CSS pass with dynamic-class allowlist. None block features; schedule as carryover-ledger entries so they aren't silently dropped (per the ADR deferral convention).
