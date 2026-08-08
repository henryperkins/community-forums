# ADR 0024 — Imladris adoption for the admin console and account surface

- **Status:** Accepted, 2026-08-03
- **Supersedes, in part:** ADR 0023 §"Console IA per ADMIN §9.2" (the grouped-rail clause only)
- **Amends:** `ADMIN.md` §9.2, §9.4
- **Plan:** `docs/superpowers/plans/2026-08-03-imladris-admin-account-adoption.md`
- **Ledger:** `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md`

This is the **single** ADR for this adoption. Every deferral, gap and constraint arising from it is
recorded here as a section rather than in a separate file, so DESIGN §13's "deferrals are never
silently dropped" holds by construction.

---

## Context

The Imladris design system carries eleven finished screens governing `/admin/*` and `/settings/*`:
ten `templates/admin-*` screens unified by `components/admin/AdminNav`, plus
`templates/account-settings`. Production's equivalents predate them and have drifted. The adoption
rule is *copy the design verbatim*, with exactly four sanctioned deviation classes
(`feature-added`, `feature-removed`, `feature-changed`, `constraint`).

Stage 1 diffed all eleven screens against 39 admin pages and 13 account pages, adversarially
verified every diff, and produced the ledger. Two design-vs-spec conflicts could not be resolved
without an owner decision. Both were put to the operator on 2026-08-03 and are recorded below.

The local design mirror was found to be one sync behind the live design project; it was refreshed
from it (design documents only) as part of Stage 1. Three items were deliberately **not** taken
because the mirror is ahead of upstream or the change is build-coupled — see §6.

---

## Decision 1 — Console information architecture: adopt the tier, amend the spec

`AdminNav.jsx` models a flat horizontal area tier, not a grouped left-nav. `ADMIN.md` §9.2 said
*"Left-nav, grouped"*; §9.4 said *"reuse the app shell"* and *"the section nav in a drawer"*.
ADMIN.md outranks the design system in the precedence chain, so the design could not simply win.

**Decided: adopt the design's two-level horizontal chrome and amend the spec to match.**

`ADMIN.md` §9.2 and §9.4 are amended in this commit. ADR 0023's console-IA clause is superseded *in
part*. Its three other findings stand unchanged and remain binding:

- real Moderation entries in the nav,
- the Appeals dashboard card,
- inbound links for the two orphan consoles (`/admin/roles/simulator`, `/admin/packages/security`).

### Decision 2 — the eleventh area

`ADMIN_AREAS` has ten entries and **no Moderation area**, but `/mod/{reports,approvals,appeals}`,
`/mod/u/{id}` and `/admin/moderation` are live, flag-gated, tested functionality. Under the rules
that is `feature-added`: keep it, style it in the design's idiom, record it.

**Decided:** an eleventh area, `Moderation`, inserted at **tier index 1**. That position preserves
`ADMIN_AREAS`' relative order for the other ten *and* matches ADMIN.md §9.2's and the previous
rail's "Moderation second", so ADR 0023's ordering intent survives the change.

Tier order: Overview · **Moderation** · Content · People · Members · Appearance · Notifications ·
Integrations · Packages · Features · Settings.

`Audit log` moves from the Moderation group to **Overview**, per the design.

### Decision 3 — the identity row keeps the operator cluster

The design's `.admin-bar-id` holds only mark, wordmark, exit link and mode pill, dropping the search
form, notification bell, user monogram and sign-out that `partials/topbar.php` renders today.

**Decided: keep them, styled in the design's idiom** — using the right-cluster pattern the design
itself uses on the member screen (`AccountSettings.dc.html:30-34`: monogram · name · Log out).
Recorded as `feature-added`. Rationale: an operator losing one-click sign-out and notification
visibility is a functional regression, and the design demonstrates the idiom for exactly this
cluster one screen over.

### Consequences

Tests and evidence that assert the superseded IA must be rewritten **in the same commit** as the
chrome change, not after:

| Artifact | Change |
|---|---|
| `AppAdminNavIaTest:31-36` | `.admin-nav-group-title` ceases to exist → assert the tier items; the three `/mod/*` hrefs move to the Moderation area's tab strip |
| `AppAdminNavIaTest:39-46` | survives in spirit — the disabled-span contract carries onto the tier and tabs |
| `AppAdminNavIaTest:71-76` | survives — the ADR 0023 inbound links are kept even though the simulator gains a tab |
| `AppAdminDashboardRemediationTest:77-120` | the 26-destination single-page directory is structurally impossible under scoped tabs → 11 tier destinations in order + the active area's tabs |
| `admin-dashboard.spec.ts:61`, `:93-105` | `expectGroupedDirectory()` rewritten; `[data-admin-nav]` → `[data-admin-tier]`; the axe `include` widens beyond `.admin` because the tier is full-bleed |
| `AppImladrisFidelityTest:81` | `admin-subnav` → `admin-tier` |
| `docs/evidence/browser/**/r2-*.png` | superseded; re-shot under DESIGN §13 |

`public/assets/app.js:766-875` (drawer, scrim, focus trap, 860px `matchMedia`) is **deleted**
together with its no-JS expanded-grid fallback at `app.css:3290-3301`. Leaving either orphaned would
be dead chrome. The console nav becomes entirely JavaScript-free.

---

## Constraints — where verbatim copy is impossible

Full detail in the ledger §1.1 (34 deduplicated rows). The load-bearing ones:

1. **CSP.** ~2,174 inline `style=` attributes, 193 `style-hover=`/`style-focus=` pseudo-attributes
   and a `<helmet><style>` per screen become external classes. A *mechanism* constraint — the
   rendered result must still match exactly. The budget-meter fill must not become an inline width;
   CSSOM writes from external JS remain the sanctioned branding-preview mechanism.
2. **Progressive enhancement.** The `<script type="text/x-dc">` state machine that drives every view
   switch, filter, count and pager becomes real routes and GET forms. `<button onClick>` navigation
   becomes `<a href>`; `<button onClick>` mutation becomes `<form method="post">` with
   `csrfField()`. A GET never mutates.
3. **Feature flags.** The design renders every panel and tab unconditionally. Tabs must never link a
   dark route (six controllers throw `NotFoundException` when dark). Three asymmetries survive
   unchanged: `/admin/features` is admin-only but not flag-gated; `/admin/thread-intelligence`
   answers **200** with both TI flags dark **by design** (ADR 0019); `/admin/badge-rules` gates the
   flag before auth (guest → 404) while `/admin/custom-emoji` gates auth first (guest → 302).
4. **Authorization.** `/mod/*` is moderator-reachable; `/admin/*` requires `requireAdmin()`. The
   tier is role-filtered on `/mod/*` — ADMIN.md §9.1 ("reduced Console") and §9.4 ("hide what a role
   can't do"). The design models a single all-powerful operator and has no reduced state.
5. **Anti-draft-loss.** 32 distinct 422 paths. A restructured form carries `->errors` *and* `->old`.
   A 422 that forgets to pass `area`/`tab` to the console partial renders an unlit tier — the one
   regression this restructure can introduce, and every slice tests against it.
6. **Safety affordances are never presentational.** Typed ban confirmation
   (`AdminUserController:292-296`, server-enforced — never the design's client `banRequiresUsername`
   switch), the audited PII reveal (never the design's `piiGate` prop), the one-time invitation link
   rendered in the POST response and never via the cookie-backed Flash, GET confirmation
   interstitials for destructive structure actions, and ~30 re-auth password fields that must render
   inline and always (a `required` control inside a closed `<details>` silently aborts submit in
   Chromium).
7. **`.pill-admin` is never recoloured.** 41 call sites in three meanings, including the
   execution-disabled emergency brake at `package_security.php:18`. The mode chip uses the design's
   own `.admin-bar-mode`, which shares no class with it.

---

## Admin appearance behavior adjudications

Stage 1 found two places where a verbatim visual copy would otherwise leave server behavior
ambiguous. They are settled for the Appearance slice as follows:

1. **FC-07 — disabled custom CSS is treated as an absent control.** The CSS-only disclosure uses
   `:has()` to hide the textarea, so a browser can still submit its value after the checkbox is
   cleared. When `custom_css_enabled` is off, the controller ignores that posted value: it does not
   validate it and does not overwrite the stored CSS. Only the enabled bit is cleared. This matches
   the design's removed-node semantics and ADR 0009's promise that custom CSS can be disabled
   without deleting it.
2. **FC-08 — safe mode blanks both theme summaries.** While safe mode is on, the Themes view
   exposes neither an Active theme summary nor a session Preview summary. The last-known-good state
   remains available for recovery, and the plain, ungated `/admin/themes/safe-mode` surface keeps
   its existing enter-without-password / password-to-exit contract. This resolves production's
   former mismatch, where Preview blanked but Active remained visible, in favor of the design.

The design has no branding contrast check. Production's existing check is retained as
`feature-added`, but its current behavior is a documented divergence from ADR 0009: it hard-blocks
the save with a 422 and provides neither the specified admin override nor an audit row for an
override. This adoption does **not** silently reinterpret that hard block as ADR-0009-complete; the
override-and-audit workflow remains a separately owned policy gap.

---

## Gaps — recorded, not built

### Design shows it, production does not implement it (`feature-removed` — build nothing, ship no dead chrome)

`Regard` reputation pane · password strength meter · 2FA QR square and Cancel · persistent recovery
codes (production's are HMAC-hashed) · typed profile-field schema · `Hidden — wardens only` ·
`Members I have replied to` · per-event email switches · drafts autosave composer · amber "Waiting"
queue tier · attention-row ages · three uncomputed Community-today metrics · audit error-retry · the
roles filter bar and its empty state · assignments on system roles (`RoleAssignmentService:71`
refuses) · bounce/complaint ingestion · 30-day delivery retention · the invitations-flag checkbox ·
the evidence `Digest` column (`request_fingerprint` is asserted **absent** at
`AppAdminThreadIntelligenceTest:63`) · `Ready for acceptance` (retired by ADR 0022, negatively
pinned twice) · the recovery drill (prototype scaffolding) · a theme `deactivate` button · relative
timestamps.

`AdminPackages.dc.html:414,421` asserts the Extensions page is a live read-only probe *while
`server_extensions` is dark*. `AdminExtensionController:20-22` 404s in exactly that state, so the
copy is false in the only renderable state. The tab ships **disabled** with the standard note and
the copy is rewritten. Reserved-dark features still receive no invented UI.

### Production has it, the design never modelled it (`feature-added` — keep it, style it)

The operator cluster (Decision 3) · the Moderation area (Decision 2) · flag-disabled tier and tab
states · the `mod-count` badge on the Reports tab · `templates/admin/board_edit.php`, adopted **by
extrapolation** and labelled as such (the design draws an `Edit` link to nothing) · the `/appeals`
rail entry · the `/settings/composing` rail entry (its *content* is modelled inside the design's
Reading pane) · the `.table-scroll` region wrappers and sr-only labels that ADR 0021/0023 landed.

### No design representation at all

`templates/admin/moderation.php` (Anti-abuse) is the only admin page with **zero** design content
anywhere in the system. It receives the shared chrome and no body adoption. **The ownership gap is
raised upstream in the design project** rather than invented here.

### Carried forward unchanged from earlier ADRs

ADR 0021 deferral #7 (`link_previews` admin operations) — still deferred; no console is invented.
ADR 0023 deferrals #1 (reports-queue bulk actions), #2 (thread-level restore), #3 (deputy-facing
roster) — still deferred. The People slice **closes the `role_edit.php` half of ADR 0023 deferral
#4**: definition, clone, assignment and per-row renewal errors receive context-unique ids and
programmatic input linkage without weakening their existing scoped 422 round-trips. The
`registries.php` half remains explicitly deferred.

---

## Live defects found during Stage 1

Not design work; fixed before adoption is layered on top.

1. **The staff badge does not flip on the default theme.** `layout.php:4` defaults `theme` to
   `system`. `app.css` has two dark token blocks (`[data-theme="dark"]` `:789`,
   `@media (prefers-color-scheme: dark) { [data-theme="system"] }` `:831`) and **neither** declares
   `--surface-staff`/`--on-staff`; `imladris.css` has no `prefers-color-scheme` block at all. A
   `system`-theme user on a dark OS gets the light-register chip on a twilight surface — the bug
   commit `8ffefce` fixed, landed only on the explicit-`dark` path. `.badge-staff` renders on every
   thread an admin posted in (`partials/post.php:55`). No test catches it; a register-parity test
   lands with the fix.
2. **The branding preview bar shows neither the typed nor the saved colour** — `.brand-preview-*` is
   duplicated (`app.css:876-903` vs `:3515-3565`) and pinned to a static `--brand` that
   `/brand.css` never emits.
3. **The Thread Intelligence status rail always paints success** — all four cards emit bare
   `queue-card is-static`, so `.queue-card::before` renders `--success` even on `Not ready`/`Paused`.

**Settled, not a defect:** `/admin/thread-intelligence` answering 200 with both TI flags dark is
deliberate rollback reachability (ADR 0019, `AppAdminThreadIntelligenceTest:29-71`). An earlier
report called it a missing flag guard; changing it turns the suite red.

---

## Design-mirror divergences (appended to `LOCAL_RECONCILIATION.md`)

Three upstream states the local mirror deliberately does not take:

1. **`tokens/colors.css`** — the mirror's semantic `--surface-staff`/`--on-staff` pair is a
   documented, test-backed WCAG-AA correction. Upstream still paints `.badge-staff` from the
   numbered ramp at 3.55:1, and that pair does not flip in twilight. The mirror stays ahead.
   Upstream's new `.presence-staff` rule reintroduces the same numbered-ramp pairing and is patched
   locally on the same grounds; **raised upstream** rather than patched silently a second time.
2. **`production-contract.json`** — upstream regressed `group_dms` to `implemented_dark` (it
   graduated default-on 2026-07-18, ADR 0022) and dropped `reconciled_through_commit`, which
   `ImladrisRuntimeAssetTest` pins to the literal `6d81da59…`. The mirror stays ahead. Never bump
   that commit value.
3. **`manifest.json`** — upstream re-files the ADR 0021/0023 remediation gaps from the retired
   `ui_kits/admin` against `templates/admin-*`, which is correct in substance, but a non-empty
   `unresolved_gaps` makes `check:imladris` red. Those gaps are what this adoption closes; the
   manifest is updated to the upstream form **at closeout**, once they are closed, not before.

---

## Delivery obligations

1. **Two new Playwright specs** — `content-console.spec.ts` (structure, tags, board edit,
   confirmations, anti-abuse) and `account-console.spec.ts` (the seven uncovered account panes).
   Three admin pages and seven account panes have zero browser coverage today.
2. **`role-assignments.spec.ts` is added to `npm run evidence`** — it is reachable from no named
   script, and `npm run evidence` (15 of 28 specs) is the only CI.
3. **`.admin-bar`/`.admin-tier` CSS ships from `composer build:imladris`**, never hand-written.
   The builder reads `docs/design-system/imladris/`; `resources/imladris/` and
   `public/assets/imladris.css` are outputs.
4. **`config/imladris-runtime-baseline.json` is refreshed once per merge, on `main`, by the merger,
   as the immediately-following commit.** No slice branch contains a change to it.
5. **Fiction never ships.** Four production strings are *already* fiction **and test-pinned** —
   `Removed by a warden`, `Commends`, `Private counsel`, `sort=commends`. They are out of scope here
   and need their own owner decision; note `profile/show.php` also ships `Regard` as user-visible
   chrome, so a partial fix would leave two surfaces disagreeing two clicks apart.

---

## Closeout status (Slice 19, 2026-08-08)

Stage 2 is code-complete: slices 0–18 have landed with per-slice evidence under
`docs/evidence/imladris-admin-account-slice-*/`. This section records what the closeout
discharged and what it deliberately did not, so nothing is silently dropped.

### The five delivery obligations

| # | Obligation | Status |
|---|---|---|
| 1 | Two new Playwright specs (`content-console`, `account-console`) | **Done.** Both exist, and the per-area pattern was extended further than the obligation asked: `members-`, `integrations-`, `features-`, `packages-` and now `mod-console.spec.ts` (Slice 18). |
| 2 | `role-assignments.spec.ts` joins `npm run evidence` | **Done** — it is in the aggregate script's third group. |
| 3 | `.admin-bar`/`.admin-tier` CSS ships from the build, never hand-written | **Done and now gated** by `ImladrisRuntimeAssetTest::test_app_css_never_overrides_a_design_owned_console_class`. Generalising that gate to all 150 shadowed classes remains the real fix for `C-50`. |
| 4 | `config/imladris-runtime-baseline.json` refreshed once per merge, on `main`, by the merger | **Outstanding by design, and it is the merge blocker.** See below. |
| 5 | Fiction never ships | **Partly discharged; the remainder needs an owner decision.** Ledger §3.3 is the full accounting. |

### What still needs the owner

1. **The four test-pinned fiction strings** — `Removed by a warden`, `Commends`, `Private counsel`,
   `sort=commends` — plus the `Regard` chrome `profile/show.php` renders. Obligation 5 says **fix both
   surfaces or neither**, so the account pane's `regard` sentence was deliberately left alone.
   **This blocks the merge.** Slice 19 adds a fifth to the list: `leaderboard.php`'s `The council` is
   also test-pinned (`AppLeaderboardFidelityTest` pins it in the test *name*), which ledger §3.2 had
   mislabelled as free to change — corrected there.
2. **`C-50`** — `app.css` shadows 150 shipped design-system components. Deliberately unfixed: the
   comparison is name-level, so it does not prove the values agree, and deleting a drifted copy changes
   rendering on the composer and thread surfaces. Slice 16 declined the one deletion it could have made
   safely (`.gem-*`, left with zero consumers) to keep the rule intact.
3. **Do the seven `*-console` specs join `npm run evidence`?** `account-`, `content-`, `members-`,
   `integrations-`, `features-`, `packages-` and `mod-console` run in no CI, so their pins rot silently.
   Slices 12–18 each deferred this; it is a CI-shape decision, not a code change.
4. **`registries.php` field-error wiring** (ADR 0023 deferral 4) — still open; closing it needs
   `AdminRegistryController::consoleView()` to stop broadcasting one flat bag to every registry card.
5. **`FR-31`** — the duplicate badge-rule guard needs a unique index before the design's copy is honest.

### The merge blocker, restated with the values

Four commits on this branch rewrite `config/imladris-runtime-baseline.json`, which ledger §6 rule 5
forbids: `8cc3894`, `b474e45`, `a8a6da6`, `bdbacd7`. It is a genuine **three-way** divergence —
merge-base `f8a09441…`, `main` `79d99fbb…`, branch `749c0de1…` — so resolving it by picking either
side is wrong in both directions. Take `main`'s side in the merge, then refresh the digest on `main` as
the immediately-following commit. **Slices 16–18 touch that file, the design mirror, `resources/imladris/`
and `public/assets/imladris.css` not at all** (verified: `git diff --name-only d58ed42..HEAD` over those
paths is empty), so the blocker is exactly those four pre-existing commits.

## Post-review remediation (2026-08-08)

An adversarial review of slices 16–19 confirmed five defects. All are fixed on this branch; each is
recorded where it belongs rather than only here.

| # | Defect | Fix | Recorded in |
|---|---|---|---|
| 1 | Slice 18 filtered the console's **area rail** by role but not its **tab row**, and the one area a moderator keeps contains `antiabuse` → `/admin/moderation`, behind `requireAdmin()`. A board moderator was shown a fourth tab that 403s — the show-and-deny constraint C-04 forbids, reintroduced by the fix for it. | Tabs carry `admin_only`; `$visibleTabs()` applies the tier's predicate to the tab row and to `$firstHref`. | Ledger C-04 |
| 2 | The guard test passed anyway — it listed four `/admin/*` hrefs, not `/admin/moderation`, and its positive assertion was satisfied unconditionally by `aria-label="Moderation sections"`. | Assertion set extended; positive pin tightened to `>Moderation</span>`; `test_admin_still_sees_the_anti_abuse_tab_on_a_moderation_queue` added so the filter cannot over-reach. | Ledger C-04 |
| 3 | `_console.php` told a non-admin moderator they were in **`Admin mode`**. | Renders `Moderation` for a non-admin viewer — the string the retired `.mod-pill` chrome carried on these same four pages. `.admin-bar-mode` unchanged (C-07/FC-02 intact). | Ledger §3.2 copy row 4 |
| 4 | `lifecycle.php`'s scoped 422 replay could land nowhere: each scoped form sits on one branch of its section, and `lifecycleView()` re-reads status/pending, so a state change between GET and POST re-rendered the other branch — a 422 page showing no error at all. | Scope only where the form is actually rendered; anything left over falls back to the alert card. | This table |
| 5 | Slice 18's QA claimed the retired `/mod/*` CSS was "deleted, not orphaned"; one `.mod` rule inside the `≤860px` block survived with no consumer, and the spec's negative locator omitted `.mod` so nothing enforced the claim. | Rule deleted; `.mod` added to the locator; the false claim corrected in place. | Slice-18 `design-qa.md` |

Also closed: `mod-console.spec.ts`'s queue-count assertion was `count() >= 0`, true of any locator — it
stayed green with the badge deleted outright. It now pins the badge's **value** against the rendered
rows. Two header claims in that spec were corrected rather than fixed, because the coverage they named
exists elsewhere: it does not visit `/mod/u/{id}` (`admin-remediation.spec.ts:287` does), and its no-JS
`.admin-tier a` assertion is admin-scoped by construction.

**None of this changes the merge blocker above**, which remains the owner's call. The `app.css` edit in
item 5 moves the application digest again — expected, and it is why obligation 4 refreshes on `main`
after the merge rather than on the branch.
