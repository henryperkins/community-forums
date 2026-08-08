# Slice 13 admin features design QA

Status: complete for the Slice 13 Features & badges boundary.

References:

- `docs/design-system/imladris/templates/admin-features/AdminFeatures.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-features.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-features.md`
  — there is **no** `R-admin-features.md`. Like `admin-integrations`, `admin-members` and
  `admin-packages`, this screen has only the `D-`/`V-` pair; `V-` confirms `D-`'s citations
  rather than inverting them (the mid-pass mirror refresh recorded in the plan §8 did not
  hit this screen), and every production anchor was re-verified against the current files
  before this slice was written. Recorded here rather than left implicit.
- `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` §1.1 (`C-03`, `C-05`,
  `C-06`, `C-09`, `C-11`, `C-13`, `C-23`, `C-24`, `C-45`, and the new `C-48`), §1.2 (`FA-10`),
  §1.3 (`FC-12`–`FC-14`), §1.4 (`FR-26`–`FR-28`, and the new `FR-31`)

Captured 2026-08-08 against the real PHP application and a freshly seeded browser database
(`retroboards_console_e2e`), with `prepare.sh` re-seeding between spec groups exactly as
`npm run evidence` does.

## Surfaces

Four production templates under the one Features area heading and the three-tab strip
(`Feature flags` · `Badge rules` · `Custom emoji`):

| Template | Route | Tab lit |
|---|---|---|
| `features.php` | `/admin/features` | Feature flags |
| `badge_rules.php` | `/admin/badge-rules` | Badge rules |
| `badge_rule_preview.php` | `/admin/badge-rules/{id}/preview` | Badge rules |
| `custom_emoji.php` | `/admin/custom-emoji` | Custom emoji |

## Reviewed against the references

- **Chrome.** All four surfaces render the one area heading `Features & badges` and the one
  `Capability sections` tab strip. The preview drill-in keeps `Badge rules` lit and demotes its
  own name to `h2.admin-record-title` (`FC-12`). Document titles name the tab (or the drill-in's
  own record), matching every other adopted console page. No leaf re-emits chrome.
- **Feature flags.** The design's three-sentence intro, the corrupt-overrides alert, four summary
  tiles, per-group tables, the `Readiness / next step` column, and the Unknown-overrides card with
  its `No undeclared keys…` empty state (`FA-10`). Production's six phase groups, 57 flag rows and
  live readiness assignments are unchanged (`FR-27`) — the design enumerates 24 flags, two of which
  (`federation`, `analytics_export`) do not exist, and its reassignments would downgrade a live
  safety finding.
- **Stat tiles forked from `.queue-card`.** The shared class was already tuned to the *overview*
  design (`min-height: 168px`, a 3px `::before` accent rule, `.68rem`/`.06em` head, `2.1rem` count)
  and disagrees with this screen on every metric. Forking to `.features-stat*` copies the design
  verbatim without repainting the dashboard.
- **The readiness legend moved, it was not dropped.** The design has no legend and no
  `src/Core/FeatureFlags.php` / `docs/runbooks/operations.md` pointers. Production keeps all three
  and relocates them to one paragraph below the last group table, so the intro reads as the
  design's three sentences. It must stay outside every `<table>` — `admin-features.spec.ts:117`
  counts `table .state` matching `Reserved (ADR 0018)` and requires exactly four — and inside
  `.admin-pane`, which is the axe scope. Pinned by a new PHPUnit ordering test.
- **Badge rules.** Two-up grid, create-card intro, uppercase micro-labels, the `1fr 1.4fr`
  threshold/board pair, `✦` rule rows, and the design's action order
  `Preview · Backfill · {toggle} · Revoke awards` with the enable/disable branch collapsed so both
  variants land in the same slot. The previous markup emitted Enable *before* Backfill; nothing
  guarded that order, so a new PHPUnit test now does.
- **Badge rule preview.** Chevron back affordance, monogram roster, thousands-separated metric, and
  the `members` empty state. Hand-written 13px/stroke-2 chevron rather than `partials/back_link`,
  which renders 16px/stroke-1.8 through `partials/icon`; the design specifies 13/2 and every landed
  `.admin-back` matches.
- **Custom emoji.** Two-up grid, single-column field stack, the switch-styled reaction checkbox, the
  gold chip framing the real asset, status pills, the outlined row toggle and the centred italic
  empty state. The catalogue heading is removed and the section left unnamed, as the design has it;
  the inner scroll region keeps `aria-label="Custom emoji catalogue"` and stays the one named region.
- **Full override strings kept (`C-23`).** `Effective on` / `Override on` / `Override off` are not
  shortened to the design's bare `on`/`off`. The fail-dark normalisation proof
  (`AppAdminFeaturesTest:44-57`) asserts `Override off` is present **and** `Override on` is absent,
  which a bare `off` cannot express. Only the chrome — the 6px dot, the gold pill, the mono default
  — is the design's.
- **`Ready for acceptance` stays absent (`C-24`)**, and the flag key stays a classless `<code>`
  styled by descendant selector so the three byte-exact PHPUnit assertions still match.
- **Three flag asymmetries survive (`C-03`, ADR 0024 constraint 3).** `/admin/features` is
  admin-only but not feature-gated (200 with all 57 flags dark); `/admin/badge-rules` gates the flag
  before auth (404 for a guest); `/admin/custom-emoji` gates auth first (302 to `/login`). Not
  "fixed" here.
- **No inline script/style/handlers (`C-09`).** Anti-draft-loss 422 paths keep `->errors` + `->old`
  (`C-13`) under both naming conventions — `$errors`/`$old` on badge rules,
  `$emoji_errors`/`$emoji_old` on custom emoji — and they are deliberately not unified in this
  slice. Every mutating control is a `<form method="post">` carrying `csrfField()`; navigation is
  `<a href>` (`C-11`). Scroll regions and the sr-only `Action` header survive (`C-05`, `C-06`), the
  header staying inside the `position: relative` `.table-scroll` that `app.css:3768-3779` records as
  the mobile-Chrome viewport-zoom fix.

## Deviations recorded by this slice

- **`C-48` — the gold pill pairing.** New constraint row. The design inks the override pill and the
  emoji chip `--gold-700` on `--gold-100`; that pair is fixed-ramp, does not flip for twilight, and
  measures **3.55:1** even in the light register — below AA for the `.66rem` label. Production takes
  the gilt semantic pair `--surface-staff`/`--on-staff` (**6.25:1** light, flips correctly). This is
  `C-45` recurring on a pill-heavy screen.
- **`Effective on` ink.** The design inks the on-word `--success`; on the twilight card that
  resolves to `--green-400` on `--twilight-800` and axe measured **4.44:1** at `.72rem` — a genuine
  AA miss the first evidence run caught. Production inks it `--on-done` and leaves the dot on the
  design's `--leaf`/`--green-400` hue, so the state colour still reads.
- **`FC-12`** page identity across three routes; **`FC-13`** the create flash is rejected — rules are
  created inert (`is_enabled = 0`), so the design's *"Rule created — {badge} awards at {rule} ≥ {n}."*
  would claim a live award path; **`FC-14`** no preview lead count — `preview()['total']` counts a
  LIMIT-100 page while `backfill()` runs at 1000.
- **`FR-26`/`FR-27`/`FR-28`** — the recovery drill (design-only prototype scaffolding, explicitly out
  of scope rather than a deferral to build later), the design's flag dataset and readiness
  assignments, and *"Assets are served from the media root"* (no `/emoji/*` route exists). Built:
  nothing. Shipped: no dead chrome.
- **`FR-31` — the duplicate badge-rule guard, deferred not dismissed.** The design refuses a create
  whose badge, metric and scope all match an existing rule; `BadgeRuleService::create` has no such
  check. Closing it needs a service check *and* a unique index on `(badge_id, rule_type, board_id)`
  — a migration with a backfill decision — so the copy is not adopted here: a message promising an
  invariant nothing enforces is worse than silence. Recorded in `PHASE_5_STATUS.md`.
- **Emoji toggle flashes name their row** (`:{code}: enabled.` / `:{code}: disabled — it will render
  as plain text.`), matching the design's object-naming register. No test pinned the old strings.
- **Badge-rule enable/disable flashes keep production's wording.** Adopting the design's
  object-naming needs the badge name in the controller, and `BadgeRuleService::enable/disable`
  return `void`; a service signature change exceeds a restyle slice.
- **`Custom emoji saved.` and the honest replace copy are unchanged.** The replace branch is Shipped
  bullet 4 of `docs/adr/0023-admin-console-audit-round-2.md`; reverting it to the design's
  `:{code}: replaced.` would be a silent ADR revert.
- **Threshold/board grid cells wrap label + error.** `field_error()` emits a `<p>`, which cannot
  legally nest inside `<label>`, so each cell of the `1fr 1.4fr` pair wraps the label and its error
  line rather than moving the error inside the label. The `aria-invalid`/`aria-describedby` wiring
  is untouched.
- **`overflow-wrap: anywhere` on the asset path.** An emoji asset path has no spaces, so its full
  length would otherwise become the column's min-content and push the table past the 560px floor the
  scroll region is sized for. The design's fixture paths are short enough never to show this.
- **`Revoke awards` remains a single unconfirmed POST.** Pre-existing posture, not changed here, and
  recorded so it is not mistaken for an adoption decision.

## New spec

`tests/browser/features-console.spec.ts` — the per-area harness this migration already uses for
content, members, integrations and account. It owns copied body metrics and register parity for all
four surfaces: axe in light, twilight and `data-theme="system"` under a dark OS; light and twilight
captures at 1280px and 390px; a `javaScriptEnabled: false` walk of every route; document-overflow at
each width; and the two anti-draft-loss 422 captures. Behavioural contracts (readiness
classification, flag rollback, award backfill/revoke, shortcode rendering) remain in
`admin-features`, `gate-a` and `a11y`.

Its axe helper pins the sticky console bar to `position: static` for the duration of the scan — the
same compositor caveat as `members-console` / `integrations-console`. Its overflow helper names the
offending elements in the failure message, which is how the two layout defects above were found.

Two states in the plan's capture list are **not** in this spec because no UI can produce them: a
corrupt `settings.features` blob and the all-flags-dark tier both require a direct settings write,
and the ledger is deliberately toggle-free. Both are proved in PHPUnit instead
(`test_corrupt_overrides_banner_is_an_alert`, `test_corrupt_overrides_tiles_report_code_defaults`,
`test_feature_flag_inventory_is_admin_only_but_not_feature_gated`). Said here rather than left as a
silent gap in the capture list.

Kept **out** of `npm run evidence`, as slice 12 did; whether the per-area consoles join the
aggregate script is a Slice 19 closeout decision.

## Known branch state, carried not hidden

- `bin/build-imladris-assets.php --check` reports the `application_surface.sha256` drift, so
  `ImladrisRuntimeAssetTest` is red on this branch **by design**: the runtime baseline is refreshed
  exactly once per merge, on `main`, by the merger, as the immediately-following commit (ADR 0024
  obligation 4, ledger §6 rule 5). No slice branch may carry that file. No generated asset, mirror
  document or baseline file is touched by this commit.
- Two `admin-remediation` board-composer tests remain pre-existing exclusions owned by Slice 19
  closeout (see slice-11 `design-qa.md`).
- **`npm run evidence` has three red tests in its first group, and they are not this slice's.**
  `rich-content.spec.ts:67` and `:180` both fail in their `login()` helper on a product-tour `Skip`
  button that is expected hidden and is visible; `thread-view-study.spec.ts:328` fails a geometry
  tolerance (expected ≤ 2, received 15). All three are thread surfaces. This was **verified, not
  assumed**: the whole group was re-run with this slice's production changes (`app.css`,
  `templates/admin/`, `AdminCustomEmojiController`) stashed back to `HEAD`, and the same three tests
  failed with the same values — 3 failed / 7 skipped / 24 passed both times. Every `features-*` class
  this slice adds is either scoped `.admin-console` or prefixed `features-`, and none of those names
  occurs outside the four admin templates. Carried here for Slice 19 closeout rather than folded into
  this slice's numbers.

## Verification

**Browser.** Four groups against a freshly seeded `retroboards_console_e2e` database, all at both
the 1280px and 390px projects:

| Group | Specs | Result |
|---|---|---|
| 1 | `features-console.spec.ts` | **10 passed**, 0 failed (44.0s) |
| 2 | `admin-features.spec.ts` | **2 passed**, 0 failed (11.7s) |
| 3 | `gate-a.spec.ts --grep "custom emoji\|badge rules"` | **4 passed**, 0 failed (21.6s) |
| 4 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed |
| 5 | `npm run evidence` (the aggregate CI sweep) | **24 passed**, 7 skipped, **3 failed** — all three pre-existing thread-surface failures, isolated as above |

Group 1 carries the axe passes: light, twilight and `data-theme="system"` under
`prefers-color-scheme: dark`, on all four surfaces at both widths, plus document-overflow and the
JavaScript-disabled walk.

**Backend.** Full suite on private `retroboards_test_s13`: **2,563 tests / 18,622 assertions /
2 skipped / 1 failure** (06:54), and that one failure is the application-surface digest described
above — `fec49dbd…`, the value the merger writes into the baseline on `main`. The pre-slice baseline
on this branch was 2,558 / 18,577 with the same single failure, so this slice adds 5 tests and 45
assertions and introduces no new red.
The five tests this slice adds pass on their own (`AppAdminFeaturesTest`, `AppAdminBadgeRulesTest`,
`AppCustomEmojiGiphyTest`: 27 tests / 250 assertions), as do the pinned neighbours
(`AppFieldErrorA11yTest`, `AppAdminDashboardRemediationTest`, `AppAdminNavIaTest`,
`AppAdminThreadIntelligenceTest`, `AppFeatureFlagTest`).

**Static gates.** The CSP template scan (`rg -n "<script|<style| on[a-z]+=" templates/ -S`) returns
only `layout.php`'s permitted external `src` tags — zero hits in the four touched templates.
`php -l` passes on every touched template and on `AdminCustomEmojiController`. No generated asset,
mirror document or baseline file is modified by this commit.

## Captures

Every surface is captured in both registers at both widths — 36 PNGs. `desktop/` and `mobile/` hold
the light register plus the JavaScript-disabled walk and the two 422 states;
`twilight/{desktop,mobile}/` hold the same four surfaces in the twilight register; `comparisons/`
holds the eight side-by-side light/twilight pairs.

- `features-flags` · `features-badge-rules` · `features-badge-rule-preview` ·
  `features-custom-emoji` — the register set, from `features-console.spec.ts`.
- `features-*-no-js` — all four routes with `javaScriptEnabled: false`.
- `features-badge-rules-422` — invalid rule type, typed threshold replayed, select wired
  `aria-invalid`/`aria-describedby`.
- `features-custom-emoji-422` — rejected shortcode, `name`/`image_path` replayed, error span linked.
- `comparisons/features-<surface>-<desktop|mobile>-light-twilight.png` — the eight register pairs.

**Reading the emoji captures:** the chip in the Emoji column frames a broken-image glyph. That is the
fixture, not the chrome — the spec creates a catalogue row pointing at `/emoji/<code>.webp`, and no
`/emoji/*` route exists to serve it (the same fact that makes `FR-28`'s media-root sentence
unshippable). Production keeps the real `<img>` rather than the design's initial-letter placeholder,
so on an install whose assets exist the chip frames the asset. The pre-slice template rendered the
same bare `<img>`; only the chip around it is new.
- Gate A / behavioural set stays under `docs/evidence/browser/{desktop,mobile}/`:
  `admin-feature-readiness`, `32-badge-rules`, `33-badge-rule-preview`, `34-badge-rule-backfilled`,
  `48-custom-emoji-admin`, `49-custom-emoji-thread`.

This evidence certifies only the Features & badges bodies. Shared admin chrome was certified by
Slice 2; later admin and account areas remain separate slice work.
