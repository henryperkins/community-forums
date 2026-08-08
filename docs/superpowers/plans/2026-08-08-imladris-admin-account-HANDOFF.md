# HANDOFF — finish `feat/imladris-admin-account` (slices 16–19) and merge

Written 2026-08-08. **Supersedes `2026-08-06-imladris-admin-account-HANDOFF.md`**, which is now
stale (it lists slice 13 as not started). Branch **`feat/imladris-admin-account`**, worktree
**`.worktrees/imladris-admin-account-session`**, HEAD **`ae4de7a`**, pushed, **24 commits ahead of
`main`** (merge-base `c476874`).

## The task

Finish slices 16 → 17 → 18 → 19 of the Imladris admin/account migration (Stage 2 of ADR 0024), then
merge into `main`. Governing rule, do not relitigate:

> **Copy the design verbatim.** Structure, section order, component anatomy, class names, token
> usage, spacing, empty/loading/error states, microcopy register. The *only* sanctioned deviations
> are `feature-added`, `feature-removed`, `feature-changed`, `constraint`. Aesthetic preference is
> not one of them.

## Read these first, in this order

1. `docs/adr/0024-imladris-admin-account-adoption.md` — operator decisions, 34 constraints, the five
   delivery obligations.
2. `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` — the deviation ledger.
   **§6 standing rules is what a slice runs under**; rules 3 and 7 were amended 2026-08-08.
3. `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md` §6 — the 19-slice sequence.
4. `docs/evidence/imladris-admin-account-slice-14/design-qa.md` — **the best model for a slice's
   evidence doc.** Slice 13's is the second best.
5. The `D-`/`V-`(/`R-`) triple for the screen you are on, under
   `docs/superpowers/plans/imladris-admin-account-stage1/`. **Their production line numbers are
   stale throughout — re-verify every anchor against the current file.** Where an `R-` exists
   (account-settings has one), it is the corrected authority and supersedes `D-`.

`CLAUDE.md` at repo root governs everything (spec precedence, CSP, PE, flags, anti-draft-loss,
"done requires evidence").

## Where the branch stands

| Slice | Area | Status |
|---|---|---|
| 0–12 | adjudication → admin-integrations | Done, with evidence |
| 13 | admin-features | **Done** — `46cafde`, `docs/evidence/imladris-admin-account-slice-13/` |
| — | design-surface + prose-contract gates | **Done** — `e3eada0` (see "New gates" below) |
| 14 | admin-packages | **Done** — `9a35f72`, slice-14 evidence (8 templates + 2 partials) |
| 15 | account A — Profile, Security | **Done** — `ae4de7a`, slice-15 evidence |
| 16 | account B — 8 panes | **Done** — slice-16 evidence (8 templates, 2 controllers, 3 PHPUnit files, the spec extension) |
| 17 | account C — Boards, Drafts, Lifecycle (+ `composer.js`) | **Done** — slice-17 evidence (3 templates, `composer.js` drafts rows, 2 controllers, 2 PHPUnit files, 2 browser specs) |
| 18 | `/mod/*` chrome | **Done** — slice-18 evidence (4 mod templates onto the console chrome, `_console` tab counts, `/appeals` de-fiction, new `mod-console.spec.ts`) |
| 19 | closeout — de-fiction, evidence sweep, merge prep | **Done except the owner decisions** — unpinned de-fiction landed, ledger §3.3 accounts for every string, ADR 0024 has a "Closeout status" section. The merge stays blocked (baseline digest + the fiction decision). |

### Remaining file sets (verified 2026-08-08)

- **Slice 16** — `templates/account/{privacy,appearance,preferences,composing,notifications,connections,sessions,blocks}.php`, **426 lines total**. Evidence: `account-console`, `gate-a`, `a11y`.
- **Slice 17** — `templates/account/{boards,drafts,lifecycle}.php` (**339 lines**) + `public/assets/composer.js` (2,516 lines). Evidence: `server-drafts`, `a11y`, `account-console`.
- **Slice 18** — `templates/mod/{reports,approvals,appeals,user}.php`, `templates/admin/moderation.php` (45), `templates/appeals/index.php` (100). Evidence: `appeals`, `admin-remediation`, `a11y`.

## The method that worked for 13/14/15 — reuse it

1. **Build the spec before touching code.** For 13 and 14 this was a parallel read of five things —
   design intent (`D-`/`V-`/`R-`), current production state, the landed-slice precedent, the design
   source, and the gates/evidence inventory — synthesised into one change list with contradictions
   resolved. It repeatedly caught test collisions that no single reader saw. If you have workflow
   tooling, use it; if not, do the five reads yourself and write the spec down first.
2. **Re-verify every anchor.** `D-`/`V-` line numbers are stale. Assume nothing.
3. **Grep the test suites for the strings you are about to change** *before* changing them. Both
   14 and 15 changed markup that existing assertions pinned; catching that first is much cheaper.
4. **When a test fails, find out why — do not adjust the test to match.** Slice 14's
   `AppRegistryCatalogTest` failure was a real product defect (`FC-23`); slice 15's mobile failures
   were pre-existing. Both were established by evidence, not assumption.
5. **Isolate every red before calling it pre-existing:** `git stash push -- <production paths>`,
   re-run, compare, `git stash pop`. Do this every time. It is the difference between "carried" and
   "hand-waved".
6. Land one commit per slice, gates green, evidence committed with it.

## Standing rules that are easy to violate

1. **Never touch `config/imladris-runtime-baseline.json`.** Refreshed once per merge, on `main`, by
   the merger, as the immediately-following commit (ADR 0024 obligation 4, ledger §6 rule 5).
2. **`config/imladris-design-baseline.json` is different** — a slice branch *may and should* carry a
   change to it, but only when the design mirror is re-synced. Refresh with
   `php bin/build-imladris-assets.php --print-design-digest`.
3. **Never write a bare `.admin-bar*` / `.admin-tier*` rule in `app.css`.** Enforced by
   `ImladrisRuntimeAssetTest::test_app_css_never_overrides_a_design_owned_console_class`, which
   compares *property sets* on bare top-level selectors. Qualified selectors (`:hover`, a state
   class, a descendant) and `@media` rules are exempt.
4. **`.pill-admin` is never recoloured** (41 call sites, three meanings). Slice 14 added
   `.pill-danger` as its sibling.
5. **Every slice ends with the gates** (ledger §6 rule 6): CSP scan, `php -l`, class/CSS parity
   sweep, full PHPUnit on a private `DB_TEST_DATABASE`, the named Playwright specs on desktop **and**
   mobile, a `javaScriptEnabled:false` pass, axe under `data-theme="system"` + `prefers-color-scheme:
   dark`, and screenshots + `design-qa.md` under `docs/evidence/<slice>/`.
6. **Deviations go in the ledger, never silently.** One ADR, one plan doc, one ledger.
7. **CSP is strict**; **anti-draft-loss** is the standing test obligation (422 + `->errors` + `->old`).

### New since the 2026-08-06 handoff

- **`config/imladris-design-baseline.json`** (`e3eada0`) digests
  `docs/design-system/imladris/{templates,components}/**`. Re-syncing the mirror with a changed
  screen now turns `composer check:imladris` red. It is a change detector, **not** a fidelity proof.
- **`check()` now reports the application-surface drift instead of throwing**, so a design change is
  never masked by the production-surface drift that is normal on every slice branch.
- **The mirror's `README.md` provenance is pinned** to `manifest.json`'s `inspected_commit`.
- **`_adherence.oxlintrc.json`, `PRODUCTION_PARITY.md`, `RUNTIME_CONTRACT.md`** are recorded in
  `LOCAL_RECONCILIATION.md` as **inert** — prose/upstream artifacts, not gates. Do not treat them as
  enforcement.

## Known reds — all isolated, none blocking

| Red | Where | Status |
|---|---|---|
| `ImladrisRuntimeAssetTest::test_checked_in_runtime_asset_matches…` | full suite | **Red by design** on any slice branch — the merger refreshes the baseline on `main`. |
| `thread-view-study.spec.ts:328` geometry (expected ≤2, got 15) | `npm run evidence` group 1 | Pre-existing; verified by stashing in slices 13 **and** 14. Because the script chains groups with `&&`, this aborts the rest of the sweep. |
| one mobile `totp.spec.ts:75`, one mobile `passkeys.spec.ts` | `npm run evidence:passkeys` | Pre-existing; verified by stashing in slice 15. Desktop passes both. |
| two `admin-remediation` board-composer tests | — | Pre-existing exclusions owned by Slice 19. |

Baseline to compare against: full suite at `ae4de7a` is **2,573 tests / 18,654 assertions / 2 skipped
/ 1 failure**.

## Working environment recipes (Windows, verified on this machine)

**PHPUnit** — use `php vendor/phpunit/phpunit/phpunit`, *not* `composer test` (Composer's 300s
timeout kills the ~7-minute run). Use a private DB:

```bash
docker start rb-mariadb
DB_TEST_DATABASE=retroboards_test_s16 php vendor/phpunit/phpunit/phpunit
# RB_TEST_FRESH=1 on first use of a new DB, or to recover an interrupted run
```

**Browser evidence** — from Git Bash. `prepare.sh` calls `wslpath`, which does not exist here, so
`PHP_INI_SCAN_DIR` must be pre-set (and **not** empty — `:-` substitutes on empty too):

```bash
cd /c/Users/htper/community-forums/.worktrees/imladris-admin-account-session
export PHP_INI_SCAN_DIR="$(cygpath -w "$PWD/storage/cache")"
export DB_DATABASE=retroboards_console_e2e
export DB_ROOT_PASSWORD=$(docker inspect rb-mariadb --format '{{range .Config.Env}}{{println .}}{{end}}' | grep '^MARIADB_ROOT_PASSWORD=' | cut -d= -f2-)
export RATELIMIT_PATH="$(cygpath -m "$PWD/storage/ratelimit-console-e2e")"
export PACKAGES_STORAGE_PATH="$(cygpath -m "$PWD/storage/packages-console-e2e")"
export E2E_PORT=8013            # a private port: playwright reuses a stale server on the default
cd tests/browser && bash prepare.sh && npx playwright test <spec>
```

Traps that cost time in this session:

- **`prepare.sh` must run in the *same command* as the test**, and you must confirm it seeded.
  Several gate-a package tests are **stateful and share one database**; two "failures" were seed
  pollution, not regressions.
- Some specs self-skip or hard-fail without **`RB_BROWSER_DARK_SURFACES=1`** (`package-security`,
  `package-review`, anything touching `/admin/extensions`).
- Set **`RB_EVIDENCE_DIR=docs/evidence/imladris-admin-account-slice-N`** when running a per-area
  console spec so its captures land in the slice directory.
- After a form POST, `await page.waitForLoadState('load')` before measuring geometry — the flash
  lands in the DOM before `app.css` finishes loading, and layout assertions will read an unstyled
  page. This caused a phantom "overflow" defect in slice 13.

## Open decisions the closeout needs from the owner

1. **The four test-pinned fiction strings** — `Removed by a warden`, `Commends`, `Private counsel`,
   `sort=commends` — plus the `Regard` chrome that `profile/show.php` renders. ADR 0024 obligation 5.
   **Fix both surfaces or neither.** This blocks the merge.
2. **`C-50` — `app.css` shadows 150 shipped design-system components** (124 setting only properties
   the DS also sets). Recorded in the ledger and `PHASE_5_STATUS.md`, deliberately not fixed: the
   comparison is *name*-level, so it does not prove the values agree, and deleting a copy where a
   value has drifted changes rendering on the composer and thread surfaces. Needs a value-level diff,
   a per-class ruling, and evidence. Generalising the obligation-3 gate to all 150 is the real fix.
3. **Do the six `*-console` specs join `npm run evidence`?** None of `account-`, `content-`,
   `members-`, `integrations-`, `features-`, `packages-console` runs in any CI today, so their pins
   rot silently. Slices 12–15 each deferred this to closeout.
4. **`registries.php` field-error wiring** — ADR 0023 deferral item 4 is still open; closing it needs
   `AdminRegistryController::consoleView()` to stop broadcasting one flat `$errors`/`$old` bag to
   every registry card. Behaviour change, not a restyle.
5. **`FR-31`** — the duplicate badge-rule guard needs a unique index before the design's copy can be
   honest.

## Merging — read this before you start

**There is a merge blocker that has nothing to do with the remaining slices.** Four commits on this
branch rewrite `config/imladris-runtime-baseline.json`, which ledger §6 rule 5 forbids:

```
8cc3894  fix: repair three admin/account defects found during the Imladris audit
b474e45  feat: replace the admin rail with the Imladris console chrome
a8a6da6  feat: adopt the Imladris People screens for roles and assignments
bdbacd7  fix: complete Imladris admin people evidence
```

The values have also diverged: the branch holds `749c0de1…`, `main` holds `79d99fbb…`. **Merging as
is rewinds `main`'s digest to a value matching neither tree.** Decide deliberately — most likely
take `main`'s side of that file in the merge, then refresh it on `main` as the immediately-following
commit.

**`main` has moved and is still moving.** It is now at `2ac40df` (merge of PR #60), which includes
`44c00d1` *"Give the mobile reply composer a two-way expansion state and one frame"* and
`9a47d99` *"finalize pr-59 browser evidence and composer assets"*.

**CORRECTED 2026-08-08 — the predicted `composer.js` conflict does not happen, and the real conflict
list is longer than this document said.** Measured with `git merge-tree --write-tree --name-only
2ac40df e3fb733`:

- **`public/assets/composer.js` auto-merges.** `main`'s hunks sit at ~106 / 2088 / 2233 / 2469 / 2506;
  slice 17's at ~1001–1080. No textual overlap, and the regions are semantically unrelated (mobile
  reply expansion vs the drafts-pane rows). Do not hand-resolve it.
- **Nine source files DO conflict**, most unlisted here before: `.env.example`, `config/config.php`,
  `config/imladris-runtime-baseline.json`, `resources/imladris/manifest.json`, **`src/Core/App.php`**,
  **`src/Core/Database.php`**, `tests/browser/gate-a.spec.ts`, `tests/browser/package.json`,
  `tests/browser/server-drafts.spec.ts` — plus ~200 binary evidence PNGs under
  `docs/evidence/browser/`.
- **`tests/browser/gate-a.spec.ts` is the one to resolve carefully.** It is the entire content of
  `.github/workflows/browser-evidence.yml`, and `main` rewrote `dismissTour()` **and**
  `openNewTopicComposer()` in a single hunk while the branch carries the *old* `dismissTour` next to a
  byte-identical copy of main's *new* `openNewTopicComposer`. Taking the branch's side — the natural
  move, since half the hunk already looks right — silently reverts main's tour fix in the only spec CI
  runs. **Correct resolution: `main`'s `dismissTour` + either side's `openNewTopicComposer`.**
- After merging, confirm `gate-a.spec.ts`'s drafts assertions moved to the slice-17 markup
  (`.account-draft-row`, the new empty-state copy) — main's `:569/:578/:600` still pin `.report-row`
  and `No server drafts yet.`. Those hunks do not overlap, so they auto-merge to the branch side; the
  check is that nothing re-introduced the old strings.

Then:

```bash
git checkout main && git pull
git merge --no-ff feat/imladris-admin-account     # repo pattern uses explicit merge commits
# immediately-following commit on main: refresh config/imladris-runtime-baseline.json
php bin/build-imladris-assets.php --print-application-digest
```

Expect the branch to carry duplicate commits of already-merged main work (`16fb994`, `90f4080` —
same content as main's `debdf59`/`a7a2636` under different hashes); identical blobs merge cleanly.
Push `main`, re-run the full suite from `main`, and keep the branch until the evidence is confirmed
there.

## Definition of done

- Slices 16–19 each have code **and** evidence (`design-qa.md` + captures at both widths, both
  registers).
- Ledger and ADR statuses updated; the fiction decision recorded.
- `main` contains the migration via a merge commit, with the baseline-digest refresh as the
  immediately-following commit, and the full PHPUnit suite + `npm run evidence` are green apart from
  the reds listed above.
