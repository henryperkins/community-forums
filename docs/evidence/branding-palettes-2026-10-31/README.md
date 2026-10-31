# Independent light and dark palettes — local qualification

Increment 3 of the expanded branding contract ([ADR 0054](../../adr/0054-expanded-branding-contract.md)):
one shared colour-role definition for packages and branding, optional light
and dark primary/accent colours, per-mode contrast review with suggestions,
and both palettes previewed with their interaction states. Runtime behaviour is
in [operator branding](../../runbooks/branding.md). This record qualifies the
local candidate; production verification is recorded after publication.

## Captures

`branding-palettes.spec.ts` runs in Chromium and WebKit at desktop (1280×800)
and mobile (390×844, touch) against a disposable seeded database. Every
colour check reads rendered pixels, so it holds whatever colour syntax the
engine reports. The two story captures are full pages; the rest show only the
per-mode fields or the state strips.

| Capture | Shows |
|---|---|
| `dark-suggestion-offered` | Evergreen (`#2e4a3a`) as the shared primary: the review warns only on dark surfaces, and the per-mode colours open with a suggested dark tone |
| `dark-suggestion-published`, `dark-suggestion-states` | After **Use suggestion** and publishing without any acknowledgement: light keeps evergreen, dark uses the suggestion. Both strips match fresh public pages in their mode |
| `classic-…`, `retro-…`, `package-extreme-fields` and `-extreme-states` | Black/white primaries and magenta/green accents per mode on Classic, Retro and an active package; each strip's action, ink, hover, selected navigation and link match a fresh public page in that mode |
| `classic-…`, `retro-…`, `package-cleared-states` | Every colour cleared: each mode returns to the package or preset roles and still matches the public pages |
| `no-js-suggestion-typed` | Without JavaScript the warning opens the per-mode colours and shows the suggestion as text, with no inert button; typing it in publishes cleanly |
| `keyboard-suggestion-applied`, `keyboard-suggestion-states` | Keyboard only (desktop): the disclosure closes and reopens from its summary, Tab reaches **Use suggestion**, Enter fills the dark field and returns focus to it; the dark strip follows at once while light keeps the shared colour |
| `large-text-modes`, `large-text-states` | The administrator's Large text preference: the mode fields and both strips wrap inside the page with no horizontal scroll |

In the package captures the band between the strips is the evidence package's
own page texture.

## Verification

| Check | Result |
|---|---|
| Focused PHP | `AppBrandingPaletteTest` (8 tests), `BrandPaletteTest` (11, including a property test that accepting any suggestion clears that mode's review for its role, with or without the other role set), `AppThemePackageTest` (25, including package semantic surfaces, the new gate pairs and suggestions computed against the active package during a private preview), `ThemeBaselineFidelityTest`, and the branding, identity, revision and draft suites |
| Palettes spec | Chromium 13 passed, WebKit 13 passed (desktop and mobile; the keyboard test is desktop-only) |
| Existing journeys (Chromium), each on a freshly prepared database | `branding-drafts` 6, `branding-revisions` 9, `branding-identity` 15, `theming-remediation` 20, `admin-appearance-followup` 22, `branding-public-theme` 22 and the `gate-a` branding/theme-package journeys 4 — all passed |
| Compatibility | For shared colours only — 32 configurations: Classic and Retro, with and without a package, eight colour pairs — the public stylesheet's declarations (global, sample and thumbnail rules) are identical to the previous release (`72b5a45c`) |
| Full PHP suite (final candidate) | 3,484 tests / 32,168 assertions, zero failures, one intentional migration-rehearsal skip |
| Generated assets and Worker | `check:assets`: current; `test:assets`: 35 passed |

Two existing journeys were updated for intended changes: the colours reset
now says it also clears the per-mode colours (the revisions journey checks a
dark override is cleared), and the appearance follow-up finds the main sample
by its data hook, since the state strips share its `.brand-preview` scope.

Found and fixed during qualification:

- Without JavaScript the "Use suggestion" button showed although it could not
  work (`.btn`'s display beat the `hidden` attribute); it now stays hidden until
  JavaScript enables it.
- Suggestions were computed against an administrator's private package preview
  and checked link contrast only, while publishing reviews the active package
  across every surface and derived state. They now run publishing's own review
  for the role, so accepting one always clears its warning.
- WebKit applies `:hover` a frame after the pointer moves; the comparison waits
  for the settled fill rather than reading the first frame.

## Production (recorded after publication)

`19bad808` was pushed to `main` at 21:52:38 UTC on 2026-10-31. Sanitized
receipts are in `production/`:

| Receipt | Result |
|---|---|
| `cloud-run-application-build.json` | Cloud Build `84681b37` SUCCESS for `19bad808` at 21:57:01 UTC |
| `cloud-run-application-service.json` | Revision `retroboards-00068-w58` Ready, 100% traffic, image `app:19bad808…`; no migration in this release |
| `cron-application-images.json` | All four cron jobs on the same image |
| `worker-application-deployment.json` | Worker version `d1be4d50-ad9d-476a-a62a-db7bdb478301` at 100% (21:54:09 UTC), the first deployment after the push |
| `live-assets-application.json` | 56/56 manifest files with matching SHA-256 (current `545bd84edf0dc37c` plus retained releases); `/healthz` ok with the database |
| `live-palettes-verification.json` | `/admin/branding` requires sign-in; the sample-scoped stylesheet carries the dark sample and thumbnail rules; `/` uses the new stylesheet, which carries the state strips and keeps the suggestion button hidden without JavaScript; the new script carries the suggestion and per-mode hooks |

The deployed asset release was pinned in the following increment's commit.
