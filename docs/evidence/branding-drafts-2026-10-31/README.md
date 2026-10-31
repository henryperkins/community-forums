# Private branding drafts — local qualification

Increment 2b of the expanded branding contract ([ADR 0054](../../adr/0054-expanded-branding-contract.md)):
private per-administrator drafts, a whole-site preview only the author sees,
and explicit publishing through the versioned transaction. Runtime behaviour is
in [operator branding](../../runbooks/branding.md). This record qualifies the
local candidate; production verification is recorded after publication.

## Captures

`branding-drafts.spec.ts` runs in Chromium and WebKit at desktop (1280×800) and
mobile (390×844, touch) against a disposable seeded database.

| Capture | Shows |
|---|---|
| `draft-saved` | The form reopened on the saved draft — name, tagline, heading and a private logo — under the *Unpublished draft* panel, with **Publish branding** and **Save draft** |
| `guest-sees-published` | A signed-out visitor at the same moment: the published defaults, no preview bar; the draft's logo URL answers 404 and the draft stylesheet is `private, no-store` |
| `draft-preview-home`, `draft-preview-sign-in` | The author's preview: the draft name, logo, tagline and heading on the directory and on the sign-in screen, under the preview bar |
| `published-sign-in` | After publishing: a visitor's sign-in screen shows the logo, now public |
| `discard-step-no-js` | Without JavaScript, the discard confirmation explains what is deleted; afterwards the form shows the published branding |
| `preview-bar-hard-palette` | A draft with near-white colours: the preview bar's text, links and exit button keep at least 4.5:1 on its constant stage ground |

## Verification

| Check | Result |
|---|---|
| Focused PHP | `AppBrandingDraftTest` (9 tests) plus the branding, identity, revision, package-theme and unit suites |
| Drafts spec | Chromium 6 passed, WebKit 6 passed (desktop and mobile) |
| Revisions and identity specs on this candidate (Chromium) | 9 and 15 passed |
| Existing journeys (Chromium), each on a freshly prepared database | `theming-remediation` 20, `admin-appearance-followup` 22, `branding-public-theme` 22 and the `gate-a` branding/theme-package journeys 4 — all passed |
| Full PHP suite (final candidate) | 3,469 tests / 31,637 assertions, zero failures, one intentional migration-rehearsal skip |
| Generated assets and Worker | `check:assets`: current; `test:assets`: 35 passed |

The save action is now **Publish branding**; the existing journeys use that
label and otherwise run unchanged.

## Production (recorded after publication)

`72b5a45c` (committed 21:12:07 UTC on 2026-10-31) was pushed to `main`.
Sanitized receipts are in `production/`:

| Receipt | Result |
|---|---|
| `cloud-run-application-build.json` | Cloud Build `c536f949` SUCCESS for `72b5a45c` at 21:15:24 UTC |
| `cloud-run-application-service.json` | Revision `retroboards-00067-ms5` Ready, 100% traffic, image `app:72b5a45c…`; migration `0085` applied on boot (revision log `migrated: 0085_branding_drafts`, 21:14:54 UTC) |
| `cron-application-images.json` | All four cron jobs on the same image |
| `worker-application-deployment.json` | Worker version `fa345174-ec5e-432b-bf31-cd272ec4621b` at 100% (21:14:20 UTC) |
| `live-assets-application.json` | 56/56 manifest files with matching SHA-256 (current `7524b5996f1124b2` plus retained releases); `/healthz` ok with the database |
| `live-drafts-verification.json` | The sign-in preview requires sign-in; preview and discard are POST-only; `/brand.css?…&draft=1` is `private, no-store` and, for a visitor, identical to the public stylesheet; `/` shows no preview bar and uses the new stylesheet |

The deployed asset release was pinned in the following increment's commit.
