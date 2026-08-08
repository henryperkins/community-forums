# Slice 11 admin members design QA

Status: complete for the Slice 11 Members & invitations boundary.

References:

- `docs/design-system/imladris/templates/admin-members/AdminMembers.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-members.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-members.md`
- `docs/superpowers/plans/2026-08-03-imladris-admin-account-ledger.md` §1.1 (`C-05`, `C-09`, `C-11`,
  `C-13`, `C-14`, `C-26`, `C-27`, `C-28`, and the new `C-44`–`C-46`), §1.2 (`FA-13`, `FA-14`),
  §1.3 (the new `FC-25`)

Captured 2026-08-06 against the real PHP application and a freshly seeded browser database
(`retroboards_e2e_imladris_slice11`), with `prepare.sh` re-seeding between spec groups exactly as
`npm run evidence` does.

## What the code commit left unfinished

`565aa10` restructured all four members templates onto new `member-directory-*`, `member-bulk-*`,
`member-record-*` and `member-invitations-*` class names, but authored `app.css` rules for only the
first two families. The member record (40 class names) and the invitations screen (13) shipped with
**no styling at all**, and the branch's suite was red in two places the commit never ran. This slice
closes both; the captures below are the first of either surface.

## Reviewed against the references

- **Chrome.** All four surfaces render the one area heading `Members & invitations` and the one
  `Member sections` tab strip (`Directory` · `Invitations`), with both drill-ins keeping `Directory`
  lit — the design's own drill-in behaviour. `AdminUserBulkTest` pins that exactly one strip is
  emitted. Document titles name the tab — or, on a drill-in, the action — rather than repeating the
  area heading, matching every other adopted console page and the `structure_confirm` precedent.
- **Directory.** Filter grid, sortable header links, monogram + username + display-name cell, role
  pill, board-mod chip, state chip, right-aligned mono numerics with grouped thousands, the bulk bar
  and the unconditional pager all follow `AdminMembers.dc.html:60-199`. The design's `Regard` column
  header ships as `Reputation`: design fiction never reaches production strings (`C-15`).
- **Selection count** is server-rendered and only mirrored by external JS (`C-09`/`C-10`). With
  JavaScript off the count still renders, the ticked rows survive a 422, and the missing-action error
  is the design's copy.
- **Bulk confirmation.** Subject list, shared-reason field and actionable count copy the design. The
  pre-flight `skipped — administrator` marker is scoped to **suspend only**, predicate
  `role === 'admin' || id === actor`: `warn()` has no governability guard, so a warn-path marker
  would have lied about a moderation action (`V-admin-members` R1/N10).
- **Record.** Identity row, six-term Status list in the design's order, Contact & signals, Account
  restrictions, the four-cell action grid and the four-column History all copy the design. Role and
  State render as **plain terms**, not pills — the pill and chip belong to the directory row
  (`V-admin-members` N7). `AppImladrisFidelityHighImpactTest` now pins that contract and negatively
  pins the pills.
- **Safety affordances survive the restyle.** The audited PII reveal is an unconditional POST writing
  one `view_pii` row; the design's `piiGate` editor prop is never exposed (`C-26`). The typed ban
  confirmation stays server-enforced (`C-28`). Every mutating control the design draws as a bare
  `<button onClick>` — reveal, lift, badge revoke, invitation revoke — is a `<form method="post">`
  carrying `csrfField()`, and navigation is `<a href>` (`C-11`). Re-auth holds: `current_password`
  and `confirm_username` render inline and always, never behind a disclosure (`C-14`).
- **The one-time invitation link** renders directly in the POST response inside
  `.member-invitations-once`, never through the cookie-backed Flash (`C-27`).
- **Scroll regions.** `role="region"` + `tabindex="0"` + the accessible name survive on both tables
  (`User directory`, `Issued invitations`); the design's bare `overflow-x` section is not a
  substitute (`C-05`). The `h2` above the invitations table is dropped without introducing a second
  accessible name.
- **Per-field errors.** `field_attrs()` / `field_error()` wiring is kept on the bulk-confirm and
  invitation forms rather than collapsed into the design's single form-level alert (ADR 0023 item 5).
- **Production-only facts are kept and styled** (`FA-13`, `FA-14`): the `required` attributes, the
  invitations `By` column, the 429 state, and the named skip list in the bulk flashes.

## Deviations recorded by this slice

- **`FC-25` — invitation status vocabulary.** Production computes four states where the design pills
  three; `expired` and `exhausted` take the design's spent register.
- **`C-44` — the active-state label.** The design inks it `--success`, which resolves to
  `--green-400` in twilight and measured **4.44:1**. The green family and the `--leaf` dot are kept;
  the text takes `--on-done`.
- **`C-45` — role pills against the numbered ramp.** The first pass paired flipping washes with fixed
  ramp inks (`--green-800`, `--gold-700`), measuring **1.1:1** in twilight — the regression the
  mirror already fixed for `.badge-staff` and patched for `.presence-staff`. Both pills now use the
  semantic pairs, which resolve to the same values in the light register.
- **`C-46` — danger-button fill ink.** `--danger` flips to a light rust tint for dark surfaces, so
  the design system's own white fill-ink measured **3.2:1**. The dark registers now pin the fill to
  `--rust`, so the button looks the same in both and white ink sits at 6.2:1. The rule is global
  because the defective rule is global.
- **The show-once panel's gold ink.** The design's `--gold-700` on the gold wash is the 3.55:1
  pairing `LOCAL_RECONCILIATION.md` corrects; it now takes `--on-staff`.
- **Cosmetic title control pair.** The design draws one control group; production splits Save and
  Clear into two forms so a no-JS submit is unambiguous (ledger `M2`, `feature-changed`).
  `display: contents` on the save form restores the design's single field-then-button-pair
  composition without nesting forms.
- **Invitations table wrapping.** The design's six fixture-short columns become seven with real UTC
  datetimes, so the atomic cells are `white-space: nowrap` and the shipped scroll region carries the
  overflow — which is what the design's own `overflow-x: auto` section anticipated. The grid items
  take `min-width: 0` so the card can shrink; without it the 560px-min table stretched the document
  to 610px at the phone viewport.
- **`amRise` is not adopted.** The keyframe ships nowhere in production and no other adopted console
  surface imports it.
- **`--gold-050` does not exist**; the show-once panel uses `--gold-soft` (plan §7 rule 4).

## Evidence the commit's own changes had invalidated

Five browser assertions asserted markup and copy `565aa10` had already replaced, and were never
updated with it. All are corrected here, per ADR 0024's rule that tests asserting a superseded
structure are rewritten in the same commit as the change:

| Assertion | Was | Now |
|---|---|---|
| `gate-a.spec.ts` record heading | `heading level 2 /bob/` (the handle used to sit inside the `h2`) | display-name heading + `.member-record-handle` |
| `gate-a.spec.ts` held badges | `ul.link-list` | `ul.member-record-badge-list` |
| `gate-a.spec.ts` profile media | `.profile-media-card` | `.member-record-profile-media` |
| `a11y.spec.ts` profile media | `.profile-media-card` | `.member-record-profile-media` |
| `admin-remediation.spec.ts` ban confirm | `Type the member` | `The username does not match` |

A sixth was inherited rather than caused here: the Imladris appearance slice reworded the theme
safe-mode status sentence, and `invitations`, `api-tokens`, `providers` and `thread-intelligence`
still matched the old one. Their shared `enterThemeSafeMode` helper therefore went blind whenever
safe mode was already on and hung for 30s clicking a button the page does not render in that state.
All four now read the state structurally, from the mutually exclusive enter/exit forms.

## New spec

`tests/browser/members-console.spec.ts` — the per-area spec this migration already uses for content
and account (`content-console`, `account-console`). It owns the copied body metrics and register
parity for all four surfaces: axe in light, twilight and `data-theme="system"` under a dark OS;
light and twilight captures at 1280px and 390px; a `javaScriptEnabled: false` walk of every route;
and a document-overflow check at each width. Slices 7–10 captured their register evidence with no
committed harness, so this is the first members-area evidence that reproduces from the tree.

Its axe helper pins the sticky console bar to `position: static` for the duration of the scan. axe
composites what it believes overlaps a control into that control's background, which turned the
primary button's gold fill into a blended olive and reported a knife-edge 4.48:1 that does not
reproduce when the same page is scanned alone. `shot()` in `admin-dashboard.spec.ts` already treats
the sticky bar as a capture artifact for the same reason.

## Known branch state, carried not hidden

- `bin/build-imladris-assets.php --check` reports the `application_surface.sha256` drift, so
  `ImladrisRuntimeAssetTest` is red on this branch **by design**: the runtime baseline is refreshed
  exactly once per merge, on `main`, by the merger, as the immediately-following commit (ADR 0024
  obligation 4, ledger §6 rule 5). No slice branch may carry that file. No generated asset, mirror
  document or baseline file is touched by this commit.
- Two `admin-remediation` tests are excluded by title from this run and remain **pre-existing**:
  `split failure re-renders the thread with the typed title intact` and `board delete previews the
  authoritative count including hidden content`. Both hang for 30s on
  `details.composer-details > summary` on a `/c/{slug}` board page; the 2026-08-04 session verified
  the first at baseline `b61ca15` in a clean worktree with its own database. `board.php:69` gates
  that disclosure on `$can_post`, so the likely cause is the scratch board the tests create through
  `/admin/structure` not being postable. Owned by the Slice 19 closeout, not by this slice.

## Verification

**Browser.** Three spec groups, each against a freshly seeded database, all at both the 1280px and
390px projects:

| Group | Specs | Result |
|---|---|---|
| 1 | `gate-a.spec.ts`, `invitations.spec.ts` | **57 passed**, 3 skipped, 0 failed (5.1m) |
| 2 | `admin-remediation.spec.ts` | **17 passed**, 15 skipped, 0 failed (1.0m) |
| 3 | `members-console.spec.ts` | **10 passed**, 0 failed (32.6s) |

84 passed, 18 skipped, **zero failures**, with only the two pre-existing board-composer tests
excluded by title. Group 3 carries the axe passes: light, twilight and `data-theme="system"` under
`prefers-color-scheme: dark`, on all four surfaces at both widths, plus a document-overflow check at
each width and the JavaScript-disabled walk.

**Backend.** The full suite, read to completion on the private `retroboards_test_s11_evidence`
database: **2,556 tests / 18,571 assertions / 2 skipped / 1 failure**, and that one failure is the
application-surface digest described below. The four focused members files
(`AdminUserBulkTest`, `AppAdminUserRecordTest`, `AppInvitationsTest`,
`AppImladrisFidelityHighImpactTest`) pass 92 tests / 459 assertions on their own.

**Static gates.** The CSP template scan (`rg -n "<script|<style| on[a-z]+=" templates/ -S`) returns
only `layout.php`'s permitted external `src` tags. `php -l` passes on every touched template.
`bin/build-imladris-assets.php --check` reports only the application-surface digest drift described
above; no generated asset, mirror document or baseline file is modified by this commit.

## Captures

Every surface is captured in both registers at both widths. `desktop/` and `mobile/` hold the light
register plus the JavaScript-disabled walk; `twilight/{desktop,mobile}/` hold the same four surfaces
in the twilight register; `comparisons/` pairs them side by side.

- `members-directory` · `members-bulk-confirm` · `members-record` · `members-invitations` — the
  register set, from `members-console.spec.ts`.
- `members-*-no-js` — the same four routes with `javaScriptEnabled: false`.
- `remediation-member-directory`, `remediation-member-directory-no-js-422`,
  `remediation-users-bulk-confirm`, `remediation-user-record-pii`,
  `remediation-ban-typed-confirmation` — the behavioural flows, from `admin-remediation.spec.ts`.
- `remediation-390-users`, `remediation-390-invitations` — the phone-width containment probes.
- `14-admin-users`, `15-admin-user-record`, `47-profile-media-moderation`,
  `69-admin-invitations-show-once`, `70-admin-invitations-list` — the Gate A set, from
  `gate-a.spec.ts` and `invitations.spec.ts`.

This evidence certifies only the Members & invitations bodies. Shared admin chrome was certified by
Slice 2; later admin and account areas remain separate slice work.
