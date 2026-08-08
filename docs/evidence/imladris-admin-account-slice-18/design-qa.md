# Slice 18 design QA — the `/mod/*` chrome

Status: complete for the Slice 18 boundary.

References:

- `docs/adr/0024-imladris-admin-account-adoption.md` — **decision 2** (the eleventh area),
  **constraint 4** (`/mod/*` is moderator-reachable while `/admin/*` needs `requireAdmin()`), and the
  `feature-added` list, which names "the `mod-count` badge on the Reports tab".
- Ledger §3.2 — the four `Warden's table` eyebrows and the `Council record` pair.
- `templates/admin/_console.php` — the shared chrome Slice 2 landed.

**There is no design source for this surface.** `ADMIN_AREAS` has ten entries and no Moderation area,
so the whole `/mod/*` surface is `feature-added` under decision 2: *keep it, style it in the design's
idiom, record it*. `templates/admin/moderation.php` (Anti-abuse) is separately recorded in the ADR as
the one admin page with **zero** design content anywhere in the system; it already renders the shared
chrome and receives no body adoption here.

Captured 2026-08-08 against the real PHP application and a freshly seeded `retroboards_console_e2e`.

## Surfaces

| Template | Route | Tab lit |
|---|---|---|
| `mod/reports.php` | `/mod/reports` | Reports |
| `mod/approvals.php` | `/mod/approvals` | Approvals |
| `mod/appeals.php` | `/mod/appeals` | Appeals |
| `mod/user.php` | `/mod/u/{id}` | Reports (drill-in) |
| `admin/moderation.php` | `/admin/moderation` | Anti-abuse — already adopted, unchanged |
| `appeals/index.php` | `/appeals` | member surface — de-fiction only |
| `admin/_console.php` | shared partial | optional tab counts |

## What this slice found first: the chrome was already built and waiting

`admin/_console.php` **already declared the Moderation area** — `h1` `Queues & anti-abuse`, tabs
pointing at `/mod/reports`, `/mod/approvals`, `/mod/appeals` and `/admin/moderation`, each flag-gated —
and `:108-111` **already reduced the tier to that single area for a non-admin viewer**, which is
ADR constraint 4 discharged. Slice 2 built all of it.

The four `/mod/*` templates simply never rendered it. Each carried its own `.mod` / `.mod-head` /
`.mod-pill` / `.mod-subnav` / `.mod-pane` shell, a hand-rolled three-item subnav duplicating those very
tabs, a redundant `Moderation` pill, and a `Warden's table` eyebrow. So the slice is a chrome swap, not
a design translation.

## Reviewed against the references

- **All four templates now render `admin/_console` with `area=moderation`** and `variant=admin`, closing
  with `admin/_console_end`.
- **The four `Warden's table` eyebrows are gone in one move.** Ledger §3.2 predicted exactly this:
  *"Deleting the eyebrow discharges all four fiction strings at once — record it so the de-fiction slice
  does not double-count them."* Recorded here; Slice 19 should not count them again.
- **`/mod/u/{id}` is a drill-in.** It keeps the Reports tab lit and carries an
  `h2.admin-record-title` for the member identity, because the area owns the single `<h1>` — the
  precedent Slice 14 set for the five packages drill-ins ("no leaf emits an `<h1>`").
- **The duplicate subnav and the `Moderation` pill are dropped** — the tier already names the area, and
  the tab strip already lists the queues.
- **`appeals/index.php` is de-fictioned, not restyled.** It is a *member* surface on the account rail,
  so it stays in the account register: the `Council record` eyebrow becomes `Moderation` and *"The
  council record keeps the original action and your reason together."* becomes *"The moderation record
  keeps…"* (ledger §3.2). It also stopped borrowing the staff `.mod-pane` class.
- **The retired CSS is deleted, not orphaned** — ~1.2KB of `.mod`/`.mod-head`/`.mod-pill`/
  `.mod-subnav`/`.mod-pane` rules. Verified first that nothing in `templates/`, `public/assets/*.js` or
  any spec still referenced them. Unlike Slice 16's `.gem-*` case these are **app-local** names, not
  design-owned ones, so `C-50` does not apply and there is no reason to keep them.
  **CORRECTED 2026-08-08 (slice 16-19 review): this claim was not true when written.** The sweep
  caught the top-level rules but missed `.mod { padding: 20px 14px 56px }` inside the `≤860px` media
  block, which survived with no consumer — and `mod-console.spec.ts`'s negative locator listed
  `.mod-head, .mod-subnav, .mod-pane, .mod-pill` but not `.mod`, so nothing enforced the claim
  either. Both are fixed: the rule is deleted and `.mod` is now in the locator.
- **No inline script/style/handlers.** The CSP scan over all seven touched templates returns nothing.
  The console nav is entirely JavaScript-free, and the no-JS walk pins that.

## Deviations recorded by this slice

- **`C-55` — the queues carry the console chrome and their own chrome is deleted.** Full detail in the
  ledger.
- **`FA-32` — the queue-count badge moves onto the console tab.** `admin/_console.php` gained an
  **optional** `counts` (and `urgent_tabs`) parameter: absent or zero renders nothing, so the other ten
  areas are byte-identical. `.mod-subnav .active .mod-count` was re-scoped to
  `.admin-tab.is-active .mod-count`. This is the one shared-partial change in the slice, and it is
  additive by construction.

## Test collisions — what changed and why

| Pin | Disposition |
|---|---|
| `AppImladrisFidelityTest:484-486` — `mod-head`, `mod-subnav`, `mod-pane` on `/mod/reports` | **Rewritten** to `admin-tier` / `admin-tabs` / `admin-pane`, plus negative pins on `Warden's table` and `mod-subnav`. The classes it named were this surface's own chrome and are deliberately gone. |
| `appeals.spec.ts:96` — `getByRole('heading', { name: 'Appeals queue' })` | **Rewritten.** The area owns the h1 (`Queues & anti-abuse`) and the lit tab names the queue; the body `<h2>Appeals</h2>` is also pinned so the queue is still identified. |
| `a11y.spec.ts:362` — the same heading gate | **Rewritten the same way.** Its mobile sibling `:325` was failing only as a knock-on: the staff test's self-clean `Resolve appeal` step never ran, so bob's appeal stayed open and the member `/appeals` scan found a different state. One fix cleared both. |
| `AppAdminNavIaTest:66-68` — the three `/mod/*` hrefs appear in the admin tier | **Survive** — those assertions run on `/admin` pages, which this slice does not touch. |
| `AppBoardFoldersSavedFeedsTest`, `AppAccountConsoleTest` | **Untouched** — no account surface in this slice. |

New: `AppImladrisFidelityTest::test_moderator_sees_only_the_moderation_area_on_a_queue`, which pins
ADR constraint 4 on the newly-console-ised queues: a board moderator (role `user`, authority from
`board_moderators`) sees the tier, sees Moderation, sees **no** admin-only destination, and gets exactly
one `<h1>`.

New spec: `tests/browser/mod-console.spec.ts` — the per-area harness slices 12–17 use. It owns axe in
light, twilight and `data-theme="system"` under a dark OS on all three queues, captures at 1280px and
390px, a document-overflow check that names its offenders, negative pins on the retired chrome, and a
`javaScriptEnabled: false` walk asserting every tier destination is an ordinary href. Kept **out** of
`npm run evidence`, as slices 12–17 did; whether the per-area consoles join the aggregate script is a
Slice 19 decision.

## Verification

**Browser.** Freshly seeded `retroboards_console_e2e`, at 1280×800 and 390×844:

| Group | Specs | Result |
|---|---|---|
| 1 | `mod-console.spec.ts` (new) | **4 passed**, 0 failed |
| 2 | `appeals.spec.ts` | **2 passed**, 0 failed |
| 3 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed |
| 4 | `admin-remediation.spec.ts` | **18 passed**, 1 failed — pre-existing, isolated below |

Group 4's passing set includes `:287` *"board moderator staff panel preserves a failed warn"*, which is
the browser evidence for the `/mod/u/{id}` drill-in under the new chrome.

**Backend.** Full suite on private `retroboards_test_s16`: **2,577 tests / 18,735 assertions /
2 skipped / 1 failure**. The slice-17 baseline was **2,576 / 18,725 / 2 skipped / 1 failure**, so this
slice adds **1 test and 10 assertions and introduces no new red**; the one failure is the
application-surface digest, red by design on any slice branch. Nine test files exercise `/mod/u/`, so
the drill-in is covered server-side as well.

**Static gates.** CSP scan clean on all seven touched templates. `php -l` clean on all of them. The
class/CSS parity sweep introduces **no** unstyled class; three pre-existing ones remain
(`mod-action-retry`, `subnav-item-label`, and the PHP-concatenated `role-`/`state-` prefixes the sweep
reports as false positives), all present at HEAD. No baseline, mirror or generated file is modified.

## Known branch state, carried not hidden

- `ImladrisRuntimeAssetTest` is red **by design**; the digest is the merger's job.
- **`admin-remediation.spec.ts:561` (board delete preview) fails — pre-existing, isolated not assumed.**
  Re-run with `public/assets/app.css`, `templates/mod/`, `templates/admin/_console.php` and
  `templates/appeals/` stashed back to `HEAD`, it failed identically. It is one of the two
  board-composer exclusions the handoff already assigns to Slice 19.
- **A second `admin-remediation` failure and four `a11y` failures were environment artifacts, not
  regressions, and the cause is worth recording.** `prepare.sh` resets the database but deliberately
  does **not** clear a private rate-limit store ("not clearing outside `storage/ratelimit-e2e`"). After
  many consecutive suites against the same `RATELIMIT_PATH`, accumulated counters began tripping 429s
  early — breaking announcement publish (`admin-remediation:329`) and content-reference creation
  (`a11y:520`). `rm -rf storage/ratelimit-console-e2e` between runs restored both to green, and every
  result above was measured with a cleared store. **Any future slice running several browser suites in
  one session needs to do the same**, or it will chase phantom regressions.
- `admin-remediation.spec.ts:180` failed once under load and passes in isolation with all changes
  applied — a timing flake, not a regression.

## Captures

22 PNGs. `comparisons/` holds the nine register triples for Reports, Approvals and Appeals;
`desktop/` and `mobile/` hold the light/twilight pairs, plus `s18-reports-no-js` from the
JavaScript-disabled walk. The shared Gate A set under `docs/evidence/browser/` is left uncommitted, as
in slices 16 and 17.

**Reading the captures:** the queues show the seeded fixture — one reported DM on Reports, the seeded
appeal on Appeals — and the Approvals queue is legitimately empty because the seed holds nothing for
approval. The tier shows all eleven areas because the captures are taken as an admin; the moderator's
reduced single-area tier is pinned server-side instead.

This evidence certifies only the moderation-queue chrome and the `/appeals` de-fiction. The console
chrome itself was certified by Slice 2, and `admin/moderation.php`'s body has no design to adopt.
