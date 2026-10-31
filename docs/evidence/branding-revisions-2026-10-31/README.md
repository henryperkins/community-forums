# Versioned branding — local qualification

Increment 2a of the expanded branding contract ([ADR 0054](../../adr/0054-expanded-branding-contract.md)):
every published branding change is a restorable revision, stale editors are
refused with their drafts, group resets leave other groups alone, and the
branding pages keep their own controls readable whatever palette is saved.
Runtime instructions are in [operator branding](../../runbooks/branding.md).
This record qualifies the local candidate; production verification is recorded
separately after publication.

## Captures

`branding-revisions.spec.ts` drives the real application against a disposable
seeded database in Chromium and WebKit at desktop (1280×800) and mobile
(390×844, touch). Captures are in `chromium/` and `webkit/`.

| Capture | Shows |
|---|---|
| `conflict-keeps-draft` | A second administrator published a tagline while the first form was open: saving returns 409 with both typed fields intact, names `@elrond` and the changed setting, and offers the explicit replace confirmation; nothing was written |
| `history` | Three revisions newest first — the live one marked, the changed settings listed, and the configuration before history began |
| `revision-review` | A revision compared with the live branding: name and logo, with the retained logo's thumbnail, and the restore scope |
| `restored-logo-live` | After restoring, the header shows the retained logo again |
| `reset-colours-step-no-js`, `reset-colours-done-no-js` | Without JavaScript: the colours reset's confirmation step names what it clears; afterwards logos, preset, visitor mode and community text are unchanged |
| `reset-step-keyboard` (desktop) | The reset step opens with Enter, its confirm button is the next Tab stop, and Escape returns focus to the summary |
| `unreadable-palette-elsewhere`, `recovery-readable` | An acknowledged near-white palette makes links unreadable on public pages, while every recovery control on the branding page (tier, history link, resets, save) measures at least 4.5:1 and the sample still shows the saved palette |

## Verification

| Check | Result |
|---|---|
| Focused PHP | `AppBrandingRevisionTest` (13 tests) plus the branding, identity, package-theme, settings and unit suites |
| Revisions spec | Chromium 9 passed, WebKit 9 passed (desktop and mobile; the keyboard case is desktop-only) |
| Identity spec on this candidate | Chromium 15 passed, WebKit 15 passed |
| Existing journeys (Chromium), each on a freshly prepared database | `theming-remediation` 20, `admin-appearance-followup` 22, `branding-public-theme` 22, `auth-polish` 19, `header-hardening` 6, and the `gate-a` branding/theme-package journeys 4 — all passed |
| Full PHP suite (final candidate) | 3,460 tests / 31,537 assertions, zero failures, one intentional migration-rehearsal skip |
| Generated assets and Worker | `check:assets`: current; `test:assets`: 35 passed |

The branding page moved its reset card below the form and loads only the
scoped sample stylesheet; the existing journeys above passed against that
structure without changes beyond the reset wording updated in increment 1.

## Production (recorded after publication)

`3478be72` was pushed to `main` at 20:55:03 UTC on 2026-10-31. Sanitized receipts
are in `production/`:

| Receipt | Result |
|---|---|
| `cloud-run-application-build.json` | Cloud Build `1c0107e4` SUCCESS for `3478be72` at 21:01:05 UTC |
| `cloud-run-application-service.json` | Revision `retroboards-00066-dhb` Ready, 100% traffic, image `app:3478be72…`; migration `0084` applied on boot |
| `cron-application-images.json` | All four cron jobs on the same image |
| `worker-application-deployment.json` | Worker version `ffc3e540-0577-41bd-a3ba-68aecc90a798` at 100% (20:56:45 UTC) |
| `live-assets-application.json` | 58/58 manifest files with matching SHA-256 (current `a66a8552cc181031` plus retained releases); `/healthz` ok with the database |
| `live-revisions-verification.json` | `/admin/branding/history` and a revision page require sign-in; the reset route is POST-only; `/brand.css?…&scope=sample` serves only scoped sample and thumbnail rules; `/login` uses the new stylesheet |

The deployed asset release was pinned in the following increment's commit.
