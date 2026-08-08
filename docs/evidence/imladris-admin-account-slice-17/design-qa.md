# Slice 17 account C design QA — Boards, Drafts and Account lifecycle

Status: complete for the Slice 17 boundary.

References:

- `docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html` — Boards `:313-332`,
  Drafts `:215-255`, Account lifecycle `:449-467`. **The worktree copy (758 lines) is the authority**;
  the main checkout's is 759 and pre-refresh.
- `docs/design-system/imladris/components/forms/Switch.jsx` — read in Slice 16, unchanged here.
- Ledger §1.1 (`C-18`, `C-50`, and the new `C-53`, `C-54`), §1.2 (`FA-31`), §1.3 (the new `FC-29`,
  `FC-30`, `FC-31`), §3.2 (the `Vilya · Expose` row).

Every anchor was re-verified. Two ledger citations were stale: the `Vilya · Expose` placeholder is at
`boards.php:85`, not `:84`, and the slice plan's row 17 is at `2026-08-03-…-adoption.md:221`.

Captured 2026-08-08 against the real PHP application and a freshly seeded `retroboards_console_e2e`.

## Surfaces

| Template | Route | Rail item | Design pane |
|---|---|---|---|
| `account/boards.php` | `/settings/boards` | Boards | `:313-332` |
| `account/drafts.php` | `/drafts` | Drafts | `:215-245` |
| `account/lifecycle.php` | `/settings/account/lifecycle` | Account | `:449-467` |
| `public/assets/composer.js` | — | — | the same Drafts row, client-side |

## Why this slice touches `composer.js`

The plan lists `composer.js` (2,516 lines) alongside the three templates without saying why, and ADR
0024's do-not-build list names *"drafts autosave composer"* — which reads like a contradiction. It is
not, and the reason matters:

`renderDraftsPage()` (`composer.js:971-1063`) **client-renders rows into the Drafts pane itself**. It
resolves `[data-local-drafts-list]` (`drafts.php`) and fills the *"Saved in this browser"* block with
markup that hard-coded `.report-list` / `.report-row` / `.report-head` / `.badge` / `.report-excerpt`.
So restyling the server rows alone would have left the local rows in the old treatment inside the same
list. The composer.js edit is **presentational only**, scoped to the row/list DOM construction; the
autosave, server-sync, conflict-resolution and discard wiring (`:595-952`) is byte-identical, because
that is the composer shell and `server-drafts.spec.ts:109-143`, `:234-258` pin its contract.

**The design's second Drafts section — the `Autosave` textarea, `Draft saved · on your account` /
`Saving…` status and `Post reply` button (`:246-255`) — is the do-not-build item and is not built.**

## Reviewed against the references

- **Boards** takes the design's row anatomy (`:321-326`): a gold `#`, the star toggle, and the mute
  control as a **pill that fills when on** rather than a link — it reads as a state, which is what it
  is. The category eyebrow becomes the design's `.62rem`/`.18em` faint label. The intro takes the
  design's fuller wording (`:315`).
- **US spelling is kept.** The design writes `Favourited` / `Favourite`; production, its column
  (`is_favorite`) and `AppImladrisFidelityTest:250` all use `Favorited` / `Favorite`. Locale register,
  not aesthetics — changing it would fork the codebase against its own schema.
- **Drafts** takes the flush card, the head row with its eyebrow, the green sync line and a count, then
  the design's row: mono destination, display-face title, two-line clamped snippet, mono `saved` stamp
  and a source chip. The empty state takes the design's copy (`:243`).
- **Lifecycle** puts all three sections on `.scribe-panel` and marks the delete card the way the design
  does (`:461-462`) — a `3px` danger rule down the leading edge and a danger-register eyebrow.
  `.danger-zone` and `.board-cat` had **no CSS rule at all** before this slice; both now have one.
- **`Vilya · Expose` is gone.** Ledger §3.2 lists that hard-coded invented folder name as free to
  change; it was the `placeholder` on the new-board-folder input and now reads `Morning read`, matching
  the register of its siblings (`Unanswered in evaluations`, `Read later`).
- **The typed `DELETE` confirmation is not adopted.** The design gates its danger button on a client
  `onInput` handler (`:464-465`). ADR 0024's constraint 6 is explicit that safety affordances are
  server-enforced and never the design's client switch; production's server-checked `current_password`
  re-auth is the stronger control and stays.
- **The unsaved-changes bar and `Saved to your seat.` toast (`:476-491`) are not built** — `C-18`, the
  global dirty buffer, which breaks PE and anti-draft-loss.
- **No inline script/style/handlers.** The CSP scan over all three templates returns nothing.

## Deviations recorded by this slice

- **`FC-29` — `/drafts` stops borrowing the moderation queue's vocabulary.** Adopted as
  `.account-draft-*` in the template **and** in `composer.js` in the same commit. `.report-*` is left
  untouched for Slice 18, which owns `templates/mod/reports.php`. The design's `Resume` on a server row
  is **not built**: only `GET /drafts` and `POST /drafts/{id}/discard` exist, and reversing
  `serverDraftKey`'s encoding is lossy for its `ctx-<hex>` branch. composer.js keeps its own `Resume`
  for browser-local drafts, where a href can be reconstructed — so the button the design draws does
  ship, on exactly the rows that can honour it.
- **`FC-30` — the lifecycle 422 bag is scoped to the form that failed.** A live defect:
  `/deactivate` and `/delete/request` both post `current_password` and both re-render through
  `lifecycleView`, so a refused **deletion** lit the **deactivate** form's inline error while the delete
  form showed nothing — on the page's destructive section. The controller now passes `error_form` and
  the pane scopes the replay with context-unique ids.
- **`FC-31` — the Drafts source chip carries the revision, not a device.** The design shows
  `{{ d.device }}` (`:232`) and a gold `{{ d.board }}` (`:227`). `server_drafts` records neither —
  `metadata` is only `{context, path}`. The chip's slot is kept and filled with something true
  (`r{revision}`, or `Local`); no device badge or board name is fabricated.
- **`C-53` — the JS hook moved to an inner wrapper.** With `server_drafts` off,
  `renderDraftsPage()` falls back to `[data-drafts-list]` and does `host.innerHTML = ''`
  (`composer.js:993`). That was harmless while the pane was a bare `.card` with one `<p>`; it would have
  erased the design's new head row. The hook now sits on an inner `<div>` and the head renders outside
  it. **This was a bug this slice would have introduced, caught before it shipped**, and it is pinned.
- **`C-54` — the flag-off `/drafts` body is not adopted.** With the flag dark there is no
  server-rendered list at all; `renderDraftsPage()`'s non-embedded branch builds `article.card` +
  `pre.draft-preview`, and `.draft-preview` has no rule in `app.css`. Adopting that branch would restyle
  the composer drawer's own draft list, a surface this slice does not certify. Carried, with the
  unstyled `.draft-preview` recorded as a pre-existing gap.
- **`FA-31` — the three Personal Organization cards are kept behind their guards.** The design's Boards
  pane models only the favourite/mute list. `AppBoardFoldersSavedFeedsTest:57-60,66-69` requires the four
  grid class names when the flags are on and **none** of them when off, so a wrapper that always emits
  the grid fails the flags-off half.
- **The 30-day deletion grace period is true, and was verified rather than assumed.** Unlike the
  Sessions footnote Slice 16 had to rewrite, this number is real: `AccountLifecycleService:166` computes
  `time() + 30 * 86400`, hard-coded, with no operator setting. (`config.php:184`'s
  `deleted_grace_days` is the *uploads* sweep and is unrelated.) The copy ships as the design has it.

## The finding the evidence run produced

`account-console.spec.ts`'s slice-17 axe pass failed the Drafts pane: the sync line measured
**4.44:1** — `#6e9479` on `#1e2730` at `11.68px` normal weight, against a 4.5:1 requirement.

This one **was** a real defect, not a measurement artifact. It is the same fixed-ramp failure Slice 14
recorded as `C-49` ("`--success` at `.72rem`: 4.91:1 / 4.45:1"): the design paints this line
`var(--success)` (`:219`), and `--success` is `--green-400` in the twilight register. Recomputing the
pair by hand reproduced axe's number to two decimals, and `--on-done` — the pair Slice 14 adopted for
exactly this — measures **9.30:1** on the same panel and stays dark-on-parchment in the light register.
The line now takes `--on-done`.

A second failure in the same run was **not** a defect: the existing
`account-console.spec.ts:244` lifecycle 422 test pins the message inside `getByRole('alert')`, and
scoping the errors inline had dropped the assertive announcement. `field_error()` takes a fourth
argument for precisely this; both lifecycle re-auth failures now render with `role="alert"` **and**
scoped to their own form, so the a11y contract and the scoping fix hold together.

## Test collisions — what changed and why

| Pin | Disposition |
|---|---|
| `gate-a.spec.ts:675` — `.report-row:not([data-local-draft-row])` | **Rewritten** to `.account-draft-row:not(…)`. The class it named belongs to the moderation queue and is deliberately gone from this pane. |
| `gate-a.spec.ts:685` and `:707` — `No server drafts yet.` (**two** occurrences) | **Rewritten** to the design's empty-state copy. The second occurrence was missed on the first pass and caught by the gate — the run is why both are now correct. |
| `account-console.spec.ts:244` — the lifecycle 422 inside `getByRole('alert')` | **Untouched; the product was fixed to keep the contract** (see above). |
| `AppImladrisFidelityTest:249` — exact `class="linkbtn btn-on"` on `/settings/boards` | **Survives.** The favourite toggle keeps that exact class list; only the *mute* control changed idiom. |
| `AppImladrisFidelityTest:250-251` — `Favorited` / `Muted` | **Survive** — both on-state labels are unchanged. |
| `AppImladrisFidelityTest:248` — negative pin on `toggle-link` | **Survives.** |
| `AppAccountConsoleTest:53-59` — exact `<span class="eyebrow">Account</span>` ×1, bare `<h1>Account settings</h1>` ×1, the intro ×1, and **no `<h1>Drafts</h1>`** on `/drafts` | **All survive** — the shared head is byte-identical and no pane adds an `<h1>`. |
| `AppAccountConsoleTest:240-284` — four lifecycle 422s must show their message | **Survive.** Two of the four (`reactivate`, `delete/cancel`) carry no field key and still render through the flat alert card, which is why that card was kept rather than replaced. |
| `AppAccountLifecycleTest:130,134` — `Add another active admin` on both 422s | **Survive** — the guard is keyed `current_password` (`AccountLifecycleService:253`), so it lands in the scoped inline slot. |
| `server-drafts.spec.ts:178-185` — both data hooks, the `Saved in this browser` heading, the local `Resume` link and the `Remove local copy` button | **All survive.** The heading moved from `<h2>` to `<h3>` for the outline; `getByRole('heading')` is level-agnostic. |
| `a11y.spec.ts:333` — `Saved reply draft` visible on `/drafts` | **Survives** — the title still renders as visible text. |

New: `AppAccountLifecycleTest::test_a_refused_lifecycle_action_scopes_its_error_to_its_own_form` and
`AppImladrisFidelityTest::test_slice17_account_panes_carry_the_console_register`.

## Verification

**Browser.** Freshly seeded `retroboards_console_e2e`, at 1280×800 and 390×844:

| Group | Specs | Result |
|---|---|---|
| 1 | `account-console.spec.ts` · `server-drafts.spec.ts` | **23 passed**, 11 skipped, 0 failed (2.0m) |
| 2 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed |
| 3 | `gate-a.spec.ts` | **54 passed**, 1 failed — isolated as pre-existing below |

**Backend.** Full suite on private `retroboards_test_s16`: **2,576 tests / 18,725 assertions /
2 skipped / 1 failure**. The slice-16 baseline was **2,574 / 18,696 / 2 skipped / 1 failure**, so this
slice adds **2 tests and 29 assertions and introduces no new red**; the one failure is the
application-surface digest that is red by design on any slice branch. The focused set
(`AppBoardFoldersSavedFeedsTest`, `AppAccountLifecycleTest`, `AppServerDraftsTest`,
`AppImladrisFidelityTest`, `AppAccountConsoleTest`, `AppModerationDraftLossTest`) passes on its own.

**Static gates.** CSP scan clean on all three templates. `php -l` clean on the three templates and both
controllers; `node --check` clean on `composer.js`. The class/CSS parity sweep reports no unstyled class
introduced by this slice — five pre-existing unstyled modifier names remain (`board-folder-card`,
`saved-feed-card`, `bookmark-folder-card`, `bookmark-folder-list`, `local-drafts`), all present at HEAD
and all sitting on already-styled base classes. No baseline, mirror or generated file is modified.

## Known branch state, carried not hidden

- `ImladrisRuntimeAssetTest` is red **by design** on this branch; the digest is the merger's job.
- **`gate-a.spec.ts:1429` (site announcement banner) fails on mobile — pre-existing, isolated not
  assumed.** Re-run with `public/assets/app.css`, `public/assets/composer.js`, `templates/account/` and
  `src/Controller/` stashed back to `HEAD`, it failed **identically** on `[data-announcement]` at
  `:1452`. It passed during Slice 16's gate-a run on the same machine, so it is state-dependent rather
  than deterministically broken — consistent with the stateful shared-database behaviour Slice 14
  recorded for the gate-a group. Carried for Slice 19.
- Two `admin-remediation` board-composer tests remain pre-existing exclusions owned by Slice 19.
- The flag-off `/drafts` body remains un-adopted by deliberate decision (`C-54`).

## Captures

22 PNGs. `comparisons/` holds the nine register triples for Boards, Drafts and Lifecycle;
`desktop/` and `mobile/` hold the six light/twilight pairs each, plus `s17-lifecycle-422` showing a
refused deletion whose error lands on the delete form and **not** on the deactivate form above it.

Re-shots of Slice 4/15/16 surfaces that those specs produce under this slice's `RB_EVIDENCE_DIR` were
pruned rather than committed — they are already certified in their own slice directories, and 30
duplicate binaries would obscure this slice's diff. The shared Gate A set under
`docs/evidence/browser/` is likewise left uncommitted.

**Reading the captures:** the Boards pane shows the seeded categories with the three Personal
Organization cards live; Drafts shows one seeded server draft plus the browser-local block; Lifecycle
shows the active-account state, so the Deactivate form (not the Reactivate one) is visible.

This evidence certifies only the Boards, Drafts and Lifecycle pane bodies and the drafts-row half of
`composer.js`. The account rail and shell were certified by Slices 2 and 4, Profile and Security by
Slice 15, and the other eight panes by Slice 16.
