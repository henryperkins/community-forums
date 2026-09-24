# Login and logout polish — browser evidence

Status: complete for the polish pass on `harden/auth-login-logout` (ADR 0039).

Captured 2026-09-24 against the real PHP application and a freshly seeded browser
database (`retroboards_e2e_polish`, `prepare.sh` exactly as `npm run evidence` runs
it). Chromium, desktop 1280×800 and mobile 390×844, in the OS light and dark schemes.
Guests always get `data-theme="system"`, so the OS scheme is the register they see.
`tests/Unit/Core/AuthGateStylesContractTest.php` pins the stylesheet;
`tests/browser/auth-polish.spec.ts` pins what it renders.

## The rules, as stated before the edit

| Change | Rule | By day | By night |
|---|---|---|---|
| Engraved field edge | `border: 1.5px solid var(--field-rule)` | 1.30:1 → **3.92:1** on the card, 3.69:1 on its fill | near-white 10.8:1 → **4.71:1** gilt, 5.30:1 on its fill |
| Focus on the stage | `outline-color: var(--stage-accent)` on the home link and skip link | 1.75:1 → **8.20:1** | 8.20:1, unchanged |
| The stage itself | `--stage-ground / -raised / -ink / -accent`, `--gold` for the star | pixel-identical | pixel-identical |
| Auth links | `.auth-links a { color: var(--brand) }` deleted | 9.02:1, unchanged | 2.86:1 → **7.30:1** |
| Guest-note link | joins the shared underline rule | underlined | underlined |
| Button hover | `color-mix(in srgb, var(--accent) 82%, #000)` | #24402F → #263D30, 0 changed pixels; text 10.91:1 | green #6F9479 → deeper gold #AC9050; text 5.57:1 |

## Before and after

`compare/` holds each pair as **before | after | changed pixels**, with changed
pixels in red. A pixel counts as changed when any channel moved by more than 6. The
before captures were taken from this branch with the pre-polish stylesheet, which
the harden pass left untouched; the after captures come from the finished branch.
Both runs used the same seed.

| Pair | Changed px | Share | Changed region |
|---|---|---|---|
| `appeals-field-desktop-light.png` | 1322 | 1.39% | x24 y34, 549×102 |
| `brand-focus-desktop-light.png` | 892 | 8.50% | x12 y6, 187×38 |
| `brand-focus-mobile-light.png` | 3513 | 8.36% | x24 y13, 372×76 |
| `btn-hover-desktop-dark.png` | 14028 | 61.39% | x12 y10, 370×38 |
| `btn-hover-desktop-light.png` | 0 | 0.00% | — |
| `compose-field-desktop-light.png` | 0 | 0.00% | — |
| `dm-field-desktop-dark.png` | 1254 | 1.94% | x24 y34, 569×48 |
| `dm-field-desktop-light.png` | 1254 | 1.94% | x24 y34, 569×48 |
| `guest-note-desktop-dark.png` | 32 | 0.09% | x284 y32, 42×1 |
| `guest-note-desktop-light.png` | 32 | 0.09% | x284 y32, 42×1 |
| `login-card-desktop-dark.png` | 3058 | 1.58% | x32 y118, 370×291 |
| `login-card-desktop-light.png` | 1708 | 0.88% | x32 y118, 370×132 |
| `login-card-mobile-dark.png` | 10105 | 1.54% | x46 y228, 632×606 |
| `login-card-mobile-light.png` | 5815 | 0.89% | x46 y228, 632×264 |
| `settings-field-desktop-dark.png` | 756 | 2.19% | x24 y34, 305×48 |
| `settings-field-desktop-light.png` | 756 | 2.19% | x24 y34, 305×48 |
| `skip-focus-desktop-light.png` | 760 | 8.90% | x4 y4, 132×53 |

What the table shows:

- **Login card by day.** Only the two field edges changed. By night the edges
  and the two auth links changed, which is why that region runs down past the
  fields.
- **The stage.** It did not move. No full-page capture changed a pixel outside
  the card. By day the only stage change is the focus ring; by night the focus
  pairs changed 0 pixels.
- **`/compose`.** 0 changed pixels. Its title field already overrides the
  engraved edge with its own ink frame.
- **Button hover by day.** 0 changed pixels: the new recipe lands within 3
  levels per channel of green-800.
- **Guest note.** A single 1px line under "Log in".
- The full set of 38 pairs, at both widths, diffed the same way.

## Captures (after)

`desktop/` and `mobile/` hold one capture per scheme for each of these:

- `*-login-card`
- `*-home-link-focus`
- `*-button-hover` (desktop only; hover is a pointer state)
- `*-guest-note`
- `*-settings-field`
- `*-appeals-field`
- `*-messages-field`

Also `desktop/branded-button-hover.png`: an operator primary of `#7c3aed`, hovered.
The spec writes the colour and restores it.

## axe

WCAG 2.0 and 2.1 A/AA, run before and after:

| Page | Before | After |
|---|---|---|
| `/login`, light | none | none |
| `/login`, dark | `color-contrast` (the two auth links) | none |
| `/`, light | `link-in-text-block` (guest note) | none |
| `/`, dark | `link-in-text-block` (guest note) | none |

## The spec against `main`

`auth-polish.spec.ts` ran unchanged against `main`, in a detached worktree with its
own database and port. On desktop 7 of 11 cases failed, each at its defect:

- the focus ring measured 1.75:1;
- the field edge measured 1.30:1;
- the guest-note link had no underline, in either scheme;
- the auth links measured 2.86:1 by night;
- the hover hue drifted 94°, from gold to green;
- a branded button's hover drifted 115°: an operator primary of `#7c3aed`
  hovered to evergreen `rgb(37, 64, 49)`.

The other 4 were never broken on `main`: the day links, the day hover, focus by
night, and field contrast by night. The night edge had been glaring, not failing.

On the branch: 19 passed and 3 skipped. The skips are the hover cases on the phone
project, which emulates touch.

## Regressions run

Each group ran on its own fresh `prepare.sh`. Every failure was re-run on `main`, in
a detached worktree with its own database:

| Group | Branch | `main` |
|---|---|---|
| `auth-polish` (new) | 19 passed, 3 skipped | 7 of 11 desktop cases fail (above) |
| `chamfer-removal` + `field-error-a11y` | 64 passed, 2 failed | the same `field-error-a11y.spec.ts:49` fails |
| `account-console` | 18 passed, 12 skipped | — |
| `composer-shell` | 22 passed, 12 skipped | — |
| `composer-expansion` | 20 passed, 10 skipped, after the fix below | 20 passed |
| `content-console` | 6 passed, 3 failed | the same 3 fail |
| `forum-inbox-remediation` | 24 passed | — |
| `board-index-remediation` | 16 passed | — |
| `imladris-forum-surfaces` | 11 passed, 3 skipped | — |
| `messages-audit-regressions` | 16 passed, 16 skipped | — |
| `messages-refinement` | 5 passed, 5 skipped | — |
| `dm-reimagine` | 2 passed, 2 skipped | — |
| `thread-content-presentation` | 2 passed | — |
| `thread-view-remediation` | 13 passed, 13 skipped | — |
| `thread-view-study` | 27 passed, 13 skipped | — |
| `unified-chrome` | 23 passed, 2 failed | the same 2 fail (`:358`) |
| `profile-surface` | 14 passed | — |
| `users-online-remediation` | 32 passed | — |
| `passkeys` + `totp` | 6 passed | — |
| `auth-hardening` | 16 passed | — |

**One regression found and fixed.** The first run failed four `composer-expansion`
cases, and `main` passed them. The cause was not this pass's CSS: it was the harden
pass's `no-store` default, which then covered every signed-in response.

- The composer discards a server draft with a `fetch` whose body it never reads.
- Under `no-store`, Chromium receives the response (`200 {"discarded":true}` in
  6 ms) but never fires `Network.loadingFinished`. A cacheable response is drained
  by the HTTP cache instead.
- So Playwright's `networkidle` never arrived.

The default now covers HTML pages only, the only thing history can re-show (ADR 0038).
`AppAuthHardeningTest` pins that signed-in JSON answers keep their default. On the
re-run: `composer-expansion` 20 passed, `auth-hardening` 16 passed.

**Failures that are not this branch's.** Both `content-console` failures reproduce
on `main` for reasons outside this pass:

- The admin board-edit page has a `link-in-text-block` violation: the "Link
  previews" link in `.content-field-help` is colour alone. It is the same
  muted-prose class as the guest note, but a selector this pass does not own.
- The no-JS journey's companion context aborts `**/assets/*.js`, which never
  matches the hashed `/assets/dist/*.js` bundles, so JavaScript runs anyway.

## Not covered

- **Firefox and WebKit.** Chromium only. `color-mix()` is Baseline 2023 (Chrome
  111, Safari 16.2, Firefox 113), and the codebase already relies on it.
- **Windows forced-colours mode.** A frame's edge is replaced by the system
  colour there, so none of this applies.
