# Evidence — the chamfered frames are removed (ADR 0033)

Refreshed 2026-09-20 by `tests/browser/chamfer-removal.spec.ts` against the real
server-rendered app (`DB_DATABASE=retroboards_chamfer_review_e2e`,
`E2E_PORT=8031`), at desktop (1280×800) and mobile (390×844 @2x), in both
light and twilight. Browser plugin unavailable; validation uses the repository's
Chromium Playwright harness.

**Correction to the original evidence:** the 2026-09-13 `twilight/desktop`
images were byte-identical to the light captures. `RB_BROWSER_DARK_SURFACES=1`
enables seed feature fixtures; it does not select a theme. The spec now runs
both themes by default, stamps `data-theme` after each navigation, and verifies
both the attribute and the painted page ground (the card on the intentionally
dark auth stage) before reading styles. Dark screenshots go to `twilight/`.

PRODUCT_DESIGN §13: a stylesheet grep proves a string is present. These are the
three facts only a browser can settle, and each is asserted, not just pictured.

## 1. No frame clips any more

`expectPlainFrame()` reads computed style on every repainted selector and
asserts `clipPath === 'none'`, no `linear-gradient` in `backgroundImage`, a
`borderTopLeftRadius` greater than 0, and — per DESIGN.md's "don't round
anything holding content past 12px" — no more than 12px.

A final sweep walks every element **and its `::before`/`::after`** on
`/settings/account`, `/settings/account/lifecycle`, `/settings/appearance`,
`/settings/notifications`, `/search` and `/compose` and asserts
**zero** `clip-path: polygon(...)` remain. The pseudo-element half matters: two of
the removed octagons were pseudo-elements (the doubled gold rule on `.auth-card`
and `.scribe-panel`), and a sweep over elements alone would not see them return.

One frame is deliberately not bordered. `.search-query-well` and its two compose
siblings draw their edge as `inset 0 0 0 1.5px var(--gold-200)` over `border: 0`,
and always did; only the clip became a radius. The test asserts exactly that —
`borderTopWidth` is 0 and the inset ring is present — rather than pretending it
took a border.

### Register parity — the specificity trap this repaint walked into

While the edge was `clip-path` + `background-image`, a bare `.textarea-engraved`
always won, because nothing else in `app.css` set those properties. A real
`border` competes: `.composer-input, textarea.input` reaches **(0,1,1)** and
outranks a bare class at **(0,1,0)**. `/appeals` renders
`<textarea class="input textarea-engraved">`, so its appeal reasons silently went
`--border-soft` on `--surface-raised` while the engraved inputs beside them stayed
`--gold-200` on `--surface-page` — two registers in one form.

Caught by measurement, not by review: a majority of adversarial reviewers had
refuted this one. Fixed by type-qualifying the production selector
(`textarea.textarea-engraved`, which ties at (0,1,1) and wins on source order);
the design system needs no such qualifier because it has no `textarea.input` rule.
The test "an engraved textarea is the same register as an engraved input"
synthesises both markup shapes on `/settings/account` and `/appeals` and asserts
their border and ground match the engraved input's. Reverting the one-line fix
makes it fail with `Expected "rgb(234, 217, 168)" / Received "rgb(220, 227, 221)"`.

| capture | frame |
|---|---|
| `auth-card.png` | `.variant-auth .auth-card`, and `::before` proven free of gradient layers |
| `account-settings.png` | `.scribe-panel` (hairline, `box-shadow: none`), `.input-engraved`, `.field-row` |
| `appearance-choice-cards.png` | `.choice-card` |
| `search-well.png` | `.search-query-well`, inset ring intact on the new radius |
| `compose-fields.png` | `.compose-title-input`, `.compose-board-select` |
| `account-lifecycle.png` | `.scribe-panel.danger-zone`: three gold sides and a 3px red leading rule on rounded corners; the rule now replaces the left border instead of sitting inside an octagon |
| `notification-select-focus.png` | member select: raised ground and inset retained under the focus halo, including the twilight-only ground change |
| `admin-{content,roles,features,audit}-focus.png` | flat operator fields at rest and after blur, with an outer halo on focus |

## 2. The outer focus ring renders — it never did before

`clip-path` clips everything outside the octagon, `outline` and outer
`box-shadow` included. `.input-engraved`, `.choice-card` and
`.search-query-well` each *declared* `0 0 0 3px var(--focus-ring)` that had
never painted; three separate source comments recorded the workaround instead
of the cause.

`engraved-field-focus.png` and the choice-card and search-well tests assert the
resting frame differs from the focused one, that `outlineStyle` is not `none`,
and that the halo is present. Both `/login` and `/search` autofocus their first
field, so the resting read is taken after an explicit `blur()` — a naive first
read *is* the focused state and silently passes.

## 3. `/compose` no longer floods gold on focus

`.compose-title-input` and `.compose-board-select` set `background:` (the
shorthand), which resets `background-position` and `background-size`.
`.input-engraved:focus` matched them at the same `(0,2,0)` and re-supplied
`background-image` — but nothing at that specificity restored the geometry, so
the eight layers repainted at `auto` size across the whole control: solid
`--gold-500`, with no focus ring. The test asserts `backgroundImage` is
unchanged by focus and contains no `linear-gradient`, and that the focus ring
does change. `compose-fields.png`.

## 4. `:user-invalid` is an honest border

`engraved-field-invalid.png`. The test resolves `var(--danger)` through a probe
element and asserts the computed `borderTopColor` equals it, that the border has
non-zero width (a colour is only a frame if the edge exists), and that the
`box-shadow` also changes — so the state is never carried by hue alone.

## Rewritten specs

- `tests/browser/thread-view-study.spec.ts` — "server-invalid engraved controls
  receive the effective danger frame" counted `linear-gradient` occurrences and
  required `>= 8`. It now reads `borderColor` and asserts the edge has width.
- `tests/browser/field-error-a11y.spec.ts` — ":user-invalid paints an engraved
  field before any round-trip" compared `boxShadow` alone. **It was already
  failing before this change**: the invalid rule restated the identical
  `var(--shadow-inset)`, so pristine and invalid were byte-equal, and the
  2026-08-09 rewrite that moved the signal to `background-image` never updated
  the spec. `docs/adr/0027-imladris-board-page-adoption.md:154` records it as a
  pre-existing failure and defers it. It now reads border, shadow and outline
  together — not one property, which is what let it drift red unnoticed — and
  passes. The spec was also in **no npm script**, so CI had never run it;
  `evidence:chamfer` now does.

## Original recorded runs (2026-09-13)

| run | result |
|---|---|
| `composer check:imladris` | `Imladris runtime assets are current.` |
| `vendor/bin/phpunit --testsuite unit` | 658 tests, 7427 assertions, OK |
| targeted integration subset (14 files) | 144 tests, 993 assertions, OK |
| full integration suite, sharded 4× on private DBs | 2122 tests, 12911 assertions — 1 failure, pre-existing (below) |
| `npm run evidence:chamfer` (desktop + mobile) | 24 passed |
| `chamfer-removal.spec.ts` desktop / mobile / attempted twilight | 8 / 8 / 8 passed; the last run was light and does not prove twilight |
| `field-error-a11y.spec.ts` | 3 passed (was 1 failing before this change) |
| `thread-view-study.spec.ts --grep "danger frame"` | 1 passed |
| `member-surfaces.spec.ts`, `composer-shell`, `composer-expansion`, `account-console` | pass on a freshly prepared DB |

Two `account-console.spec.ts` failures (`422 responses preserve explicit active
state`, `full account shell is axe-clean`) reproduce identically on a clean
worktree at `5b516751` and are **pre-existing**; the axe one is
`aria-controls="sidebar-nav"` on `.forum-bar-railtoggle`, a member-chrome ARIA
defect unrelated to borders. `AppAdminEmailTest::test_unconfigured_transport_
blocks_test_send_and_shows_blocked_banner` likewise fails on the clean worktree —
it depends on the local `.env` having no mail transport configured.

---

# The border pass (ADR 0034)

Same surfaces, second half of the audit. The original light-only `fields/`
captures are retained as history. The refreshed `compose-fields.png` and
`notification-select-focus.png` are generated by the spec at both viewports
and in both registers against a **freshly prepared** database.

## Read the DB state before believing a capture

The first attempt at these captures came back with the whole page **bright red**.
It was not a regression: `package-security.spec.ts` and friends install **theme
packages**, which persist in the e2e database and are served from
`/theme/{digest}.css` — unlayered, after `app.css`. One of the fixtures overrides
`--surface` and `--accent`. `brand.css` was empty; `installed_packages` had two
enabled theme rows. A `prepare.sh` cleared it.

So: `prepare.sh` between spec groups is not only about seeded *content*. Theme
packages, branding and custom CSS all survive in that database and repaint
everything. A capture taken after an unrelated admin spec is not evidence.

## What the fields actually resolve to now

`/settings/notifications` — `select.input`:
`1.5px var(--border-soft)` · `--radius-md` · `--surface-raised` · `--shadow-inset`
— i.e. exactly what DESIGN.md "Inputs and fields" specifies, which is what
`<textarea>` already had and `<input>`/`<select>` did not.

`/compose` — the title input and board select stay `border: 0` with their inset
gold ring on `--radius-md`: the deliberate exception recorded in ADR 0033. The
composer textarea stays frameless because `.composer-box` owns the frame.

The admin content, roles, features and audit fields explicitly reset their
resting shadow to `none`. Their more specific focus rules already supplied only
the outer halo, so inheriting the new generic inset had made their depth vanish
on focus and return on blur. The regression tests failed on all four surfaces
before the fix and now exercise every visible field through rest → focus → blur.
This preserves the operator treatment already used on general settings forms.

The mobile navigation scrim follows `--scrim`: day alpha `.5` → `.42`, twilight
`.58`. This intentionally aligns it with the other overlays.

(Computed `borderTopWidth` reads `1px` for a `1.5px` border: Chromium snaps used
border widths to device pixels at DPR 1. That is true of every 1.5px border in
this design system and is not specific to this change.)

## Original border-pass runs (2026-09-13)

| run | result |
|---|---|
| `composer check:imladris` | `Imladris runtime assets are current.` |
| bridge byte-identity | `bridge OK` |
| `vendor/bin/phpunit --testsuite unit` | 658 tests, OK |
| `admin-remediation.spec.ts` + `admin-dashboard.spec.ts` | 22 passed — the admin surfaces carry ~50 of the repainted selects, and this set includes the computed-style assertion that the rejected chevron edit would have broken |
| `npm run evidence:chamfer` | 24 passed |

`ImladrisRuntimeAssetTest::test_both_application_dark_registers_declare_the_same_tokens`
**caught a real defect mid-change** and is worth the mention: a `--scrim` fix had
been applied to the system-dark register only, which would have left the two
dark registers declaring different tokens. Restated in both.

## Review verification (2026-09-20)

| check | result |
|---|---|
| `npm run evidence:chamfer` | 66 passed: 60 frame/theme tests plus 6 member field-error tests |
| `admin-remediation.spec.ts` + `admin-dashboard.spec.ts` | 26 passed, 22 intentional viewport skips |
| `MAIL_DRIVER=sendmail MAIL_FROM= composer test` | 2780 tests, 20348 assertions; no failures, 6 deprecations, 1 skip |
| `composer verify:imladris` | runtime assets current; 24 tests, 296 assertions, OK |
| Light/twilight image comparison | 13 pairs per viewport; no byte-identical pairs |
| `git diff --check` | clean |

The frame and field-error captures use Chromium at `http://localhost:8031`.
Every frame test verifies a successful page response, a visible main region,
the requested theme and its computed ground. Focus tests exercise the real
controls; the account-error tests preserve server validation, autofocus and
message linkage. Screenshots reset scrolling without clearing focus so sticky
chrome stays at the top of full-page evidence. Representative desktop/mobile,
light/twilight, lifecycle, notification and error captures were visually inspected.

Console checking reports no JavaScript exceptions or new console errors. One
specific pre-existing error is allowed: the malformed `eye` SVG path in
`templates/partials/icon.php` (`Expected number` at `7-3 7-10 7-10-7z`), also
present in HEAD before this work. Other SVG errors remain failures. The custom
select-chevron follow-up remains as recorded in ADR 0034 §3; this pass does not
claim that its twilight contrast is fixed.

PHPUnit used `DB_TEST_DATABASE=retroboards_chamfer_review_test`. The explicit
empty `MAIL_FROM` and `sendmail` driver match the suite's unconfigured-mail
premise and avoid inheriting the local outbound transport; `.env` was not edited.
PHP 8.5.4 emits deprecation notices in the existing suite; a focused rerun
confirmed `ThemeAssetScanner.php:68` calls deprecated `imagedestroy()`.

Browser runs used `DB_DATABASE=retroboards_chamfer_review_e2e`,
`RATELIMIT_PATH=storage/ratelimit-e2e-chamfer-review`, and
`PACKAGES_STORAGE_PATH=storage/packages-e2e-chamfer-review`. The final frame run
started from `prepare.sh` after the mutating admin regressions. Both scratch
DBs and their isolated stores were removed afterward, and the temporary server
was stopped. The existing browser/development database and server were preserved.

## Superseded in part, 2026-09-24 (ADR 0039)

The engraved frames in these captures (`auth-card`, `account-settings`,
`compose-fields`, `engraved-field-*`) show the edge in `--gold-200`. Production now
draws it in `--field-rule`: `gold-700` by day and `gold-600` in twilight, because
`--gold-200` measured 1.30:1 against the card, below the 3:1 WCAG 1.4.11 asks of
a field's boundary. The geometry this slice proves is unchanged: a real border,
no clip-path, the outer focus ring. So these captures are left as the dated
record. For the new edge, see `docs/evidence/auth-login-logout-polish/`, whose
`compare/` holds before and after pairs. `/compose`'s title field paints its own
ink frame and did not change.
