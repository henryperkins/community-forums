# Slice 15 account Profile & Security design QA

Status: complete for the Slice 15 Profile and Security boundary.

References:

- `docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html` (758 lines);
  Profile pane `:88-105`, Security pane `:108-169`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-account-settings.md` — **the corrected
  authority for this screen.** It supersedes `D-account-settings.md` (whose design line anchors are
  stale) and folds in `V-account-settings.md`. Unlike the admin screens, this one has a full
  `D-`/`V-`/`R-` triple.
- `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` §1.1 (`C-17`, `C-18`, `C-19`,
  `C-36`, `C-37`, and the new `C-50`), §1.2 (`FA-03`–`FA-05`, `FA-28`), §1.3 (`FC-04`), §1.4
  (`FR-02`–`FR-05`)

Captured 2026-08-08 against the real PHP application and a freshly seeded browser database
(`retroboards_console_e2e`).

## Surfaces

| Template | Route | Rail item |
|---|---|---|
| `account/settings.php` | `/settings/account` | Profile |
| `account/security.php` | `/settings/security` | Security |

## What this slice found first: the substrate was already adopted

Slice 4 landed the account rail and shell, and the panes already sit on the shipped design-system
substrate — `.scribe-panel`, `.scribe-panel-head`, `.input-engraved`, `.textarea-engraved`,
`.field-row`, `.row-input`, `.gem-check`, `.field-grid`. Those are `imladris.css` components, not
local classes, and `.field-grid` is already the design's `1fr 1fr` / 14px pair.

So the Profile and Security **bodies** needed far less than the admin slices did, and most of what
the design adds beyond production is on the do-not-build list.

## Reviewed against the references

- **Chrome.** `R-account-settings` §0.1 is explicit that the AdminNav chrome refactor **does not
  apply to this screen**: it is a member surface and keeps its own eyebrow and 2.4rem `<h1>`. No row
  from the admin slices is carried across. The page head and rail are untouched here.
- **Profile.** The design's single card, gold eyebrow head and engraved field stack are already the
  shipped register. Production's card is a superset — Avatar, Email (disabled), Display name,
  Pronouns, Location, Website, Bio, Signature, custom fields — and every extra is `feature-added`.
- **Security.** The design pairs New and Confirm password on one row; production stacked all three.
  Now paired on the pinned `.field-grid`, with Current password on its own row as the design has it.
- **Two-factor.** The design's `Enabled` chip is adopted (`:154`), with the "each works once" clause
  it carries. The three-across recovery-code chip grid is adopted for the one moment production can
  show codes.
- **Panel heads are now real headings.** `/settings/account` was the last page still emitting
  `<span class="scribe-panel-head">`; Security and Notifications already used `<h2>`. Its panels were
  outside the heading outline for anyone navigating by heading. `C-17` forbids the **reverse**
  conversion (never `<h2>` → `<span>`); this is the safe direction, and it is now pinned.

## Deviations recorded by this slice

- **`FR-02` — no password strength meter.** The design's five-tier meter (`:117-125`) ends in a
  fiction string and sits over a scorer production does not have. Build nothing.
- **`FR-03` / `FC-04` — no mid-enrollment Cancel and no QR square.** No cancel route exists, and
  production renders the readonly secret and URI rather than an empty 88×88 placeholder.
- **`FR-04` — no persistent recovery-code grid.** Production HMAC-hashes recovery codes and can
  never re-display them. The grid renders exactly once, immediately after generation, and now says
  so: *"Copy these now — they are shown only once, and each works once."*
- **`FR-05` — no operator-defined profile-field schema.** Neither *"Fields defined by the wardens"*
  (`:94`) nor *"The wardens choose which fields exist; you choose what goes in them."* (`:96`)
  ships. The per-field type chips (`text` / `select` / `url`, `:98-101`) are **not shipped either**:
  they exist only to describe a schema production does not have, and on production's fixed field set
  they would label nothing. Recorded here rather than left as a silent omission.
- **The `https://` affix on the Homepage field is not adopted** (`:101`). Production stores and
  validates a complete URL in `website`; a visual prefix over a field that still expects the scheme
  would misdescribe what the operator must type.
- **`C-18` — no global dirty buffer.** The design holds every change in a client buffer and saves the
  whole page from one sticky bar. Production keeps one server-owned form per section; adopting the
  buffer would break anti-draft-loss and PE.
- **`C-19` — custom profile fields stay flag-gated** behind `custom_profile_fields`; the flag-off
  render stays clean.

## The finding this slice is really about — `C-50`

Doing the substrate pass turned up something much larger than the slice: **`app.css` shadows 150
shipped design-system components.** `imladris.css` ships the DS inside `@layer imladris.*` and
`app.css` is unlayered, so it beats every one of them at any specificity. A property-level comparison
of bare top-level class selectors in both files finds 150 design-owned classes redeclared in
`app.css` — **124 setting only properties the DS also sets** (a stale local copy that silently wins)
and 26 adding extras. `.scribe-panel` is byte-equivalent to `imladris.css:600-605` apart from
whitespace; the list also includes `.btn`, `.input`, `.pill`, `.monogram`, `.chip`, `.field-row`,
`.gem-check` and 40+ `.composer-*` classes.

**It was not fixed here, deliberately.** The comparison is *name*-level: it proves the same
properties are declared twice, not that the values agree. Wherever a value has drifted, deleting the
`app.css` copy changes rendering — on the composer, thread list and post surfaces as much as on
account. Closing it needs a value-level diff, a per-class ruling, and browser evidence across every
affected surface. ADR 0024 obligation 3 asserts this invariant for exactly eleven
`.admin-bar*`/`.admin-tier*` names, and the gate added in `e3eada0` enforces it for those;
generalising that gate to all 150 is the real fix. Recorded as `C-50` and in `PHASE_5_STATUS.md`.

## Known branch state, carried not hidden

- `ImladrisRuntimeAssetTest` is red on this branch **by design** — the `application_surface.sha256`
  baseline is the merger's job (ADR 0024 obligation 4).
- **`totp.spec.ts` and `passkeys.spec.ts` each fail one mobile test.** Verified pre-existing: both
  were re-run with this slice's `templates/account/` and `app.css` changes stashed back to `HEAD` and
  failed identically. Desktop passes for both. Carried for Slice 19.
- The `thread-view-study` geometry failure recorded by slices 13 and 14 is unchanged.

## Verification

**Browser.** Against a freshly seeded `retroboards_console_e2e`, at 1280px and 390px:

| Group | Specs | Result |
|---|---|---|
| 1 | `account-console.spec.ts` · `passkeys` · `totp` | **12 passed**, 6 skipped |
| 2 | `npm run evidence:passkeys` | **3 passed**, 2 failed — both pre-existing mobile failures, isolated above |
| 3 | `npm run a11y` | **35 passed**, 3 skipped, then **6 passed**, 0 failed |

**Backend.** Full suite on private `retroboards_test_s14`: **2,573 tests / 18,654 assertions /
2 skipped / 1 failure** (06:49), that one failure being the application-surface digest above. The
pre-slice baseline on this branch was 2,572 / 18,652 with the same single failure, so this slice adds
1 test and 2 assertions and introduces no new red. The focused account files
(`AppImladrisFidelityTest`, `AppUserSettingsTest`, `AppFieldErrorA11yTest`) pass **43 tests / 340
assertions** on their own.

**Static gates.** CSP scan clean; `php -l` clean on both templates; no generated asset, mirror
document or baseline file modified.

## Captures

14 PNGs under `desktop/`, `mobile/` and `comparisons/` — Profile, Security and Connections in the
light, twilight and `data-theme="system"`-under-dark registers, from `account-console.spec.ts`. The
behavioural passkey and TOTP captures stay under `docs/evidence/browser/{desktop,mobile}/`
(`passkeys-01…06`, `totp-01…03`).

This evidence certifies only the Profile and Security pane bodies. The account rail and shell were
certified by Slice 4; the remaining eight panes are Slice 16 and Slice 17.
