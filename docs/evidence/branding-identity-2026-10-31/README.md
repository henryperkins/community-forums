# Branding identity increment — local qualification

Increment 1 of the expanded branding contract ([ADR 0054](../../adr/0054-expanded-branding-contract.md)):
the community logo on every sign-in screen, System-aware logo variants, editable
community text, and member appearance thumbnails that picture the resolved theme.
Runtime behaviour is described in [operator branding](../../runbooks/branding.md).
This record qualifies the local candidate; production deployment is verified
separately and is not claimed here.

## Captures

`branding-identity.spec.ts` drives the real application against a disposable
seeded database in Chromium and WebKit, at desktop (1280×800) and mobile
(390×844, touch) sizes. Captures are in `chromium/` and `webkit/`, each with
`desktop/` and `mobile/`.

| Capture | Shows |
|---|---|
| `login-system-light`, `login-system-dark`, `register-system-*`, `home-system-*` | An uploaded light/dark/base logo set: the light variant on a parchment plate by day, the dark variant on a twilight plate by night, the tagline under it, the same logo in the topbar |
| `login-no-js-light`, `login-no-js-dark`, `home-no-js-*` | JavaScript disabled: the first paint already uses the device's mode |
| `member-dark-on-light-device-no-js` | A member's explicit Dark mode wins over a light device; no `<picture>` is rendered |
| `login-dark-logo-only-light-device`, `login-dark-logo-only-dark-device` | Only a dark variant: the house mark by day, the logo by night |
| `login-removed-logo-dark` | After removing the only logo, the house mark returns |
| `login-long-name`, `home-long-name`, `login-wide-logo` | A 76-character name and tagline, and a 720×56 wordmark, with no horizontal overflow |
| `directory-welcome`, `directory-welcome-dark`, `directory-defaults` | Saved tagline, heading and two-paragraph introduction, then the restored defaults |
| `admin-community-text` | The Community text section of the branding form; the sticky preview column is bounded to the viewport and scrolls on its own |
| `admin-reset-reachable` (desktop) | The reset confirmation scrolled into view inside that column and focused |
| `invalid-text-keeps-draft-no-js` | An 81-character heading refused without JavaScript; the typed draft remains and nothing is saved |
| `login-logo-keyboard-focus` | The identity link is reached with Shift+Tab from the autofocused email field and shows the stage focus ring (desktop) |
| `appearance-thumbnails-light`, `appearance-keyboard-choice`, `appearance-thumbnails-dark-large-text` | Light/Dark/System thumbnails for a Retro preset with operator colours, arrow-key selection, and the dark register at the Large text size |

## Verification

| Check | Result |
|---|---|
| Identity spec, Chromium | 15 passed, 1 desktop-only case skipped on mobile |
| Identity spec, WebKit | 15 passed on two consecutive runs, 1 skipped on mobile |
| Thumbnail parity | Each thumbnail's page and action colours equal the computed `--surface-page` and `--accent` of a real page in that mode (Retro with operator colours) |
| Unbranded installs unchanged | Pixel comparison against `120b799f` (HEAD) on the same database: log in, register, forgot password and the directory, light/dark, desktop/mobile, Chromium and WebKit — 32/32 identical |
| Base-logo installs unchanged | Same comparison for the directory, a board and the privacy page with only a base logo — 24/24 identical |
| Focused PHP | `AppBrandIdentityTest` (9 tests) plus the branding, package-theme, Imladris fidelity, preferences and auth-gate suites |
| Full PHP suite (final candidate) | 3,447 tests / 31,334 assertions, zero failures, one intentional migration-rehearsal skip |
| Generated assets and Worker | `check:assets`: generated assets and manifest current; `test:assets`: 35 passed |
| Existing journeys on this candidate (Chromium) | `theming-remediation` 20, `admin-appearance-followup` 22, `branding-public-theme` 22, `auth-polish` 19, `header-hardening` 6, and the two `gate-a` branding/theme-package journeys at both sizes — all passed, each group on a freshly prepared database |

Physical-device qualification is outside this record.

## Production (recorded after publication)

`4107d65d` was pushed to `main` at 20:19:21 UTC on 2026-10-31. Sanitized receipts
are in `production/`:

| Receipt | Result |
|---|---|
| `cloud-run-application-build.json` | Cloud Build `df06b3ce` SUCCESS for `4107d65d` at 20:22:18 UTC |
| `cloud-run-application-service.json` | Revision `retroboards-00065-p5f` Ready, 100% traffic, image `app:4107d65d…` |
| `cron-application-images.json` | All four cron jobs on the same image |
| `worker-application-deployment.json` | Worker version `892e06da-155a-475b-aeb9-8f47eb2c88dc` at 100% (20:20:35 UTC) |
| `live-assets-application.json` | 59/59 manifest files served with matching SHA-256 (current `46a2224ad48df63e` plus three retained releases); `/healthz` ok |
| `live-identity-verification.json` | Anonymous checks: `/login` and `/register` render the identity block with the house mark (no logo configured), the directory keeps its defaults, `/brand.css` carries the thumbnail rules, `/settings/appearance` requires sign-in |

The deployed asset release was recorded as pinned in the following increment's
commit, without a proof-only publication.
