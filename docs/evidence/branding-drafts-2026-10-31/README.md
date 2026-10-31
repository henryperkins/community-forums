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
