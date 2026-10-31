# Curated style presets — local qualification

Increment 4 of the expanded branding contract ([ADR 0054](../../adr/0054-expanded-branding-contract.md)):
complete style presets (surfaces, sidebar treatment, heading type and corners),
individual overrides for the sidebar, type and corners, and a default density
that never overrides a member's own choice. Runtime behaviour is in
[operator branding](../../runbooks/branding.md). This record qualifies the
local candidate; production verification is recorded after publication.

## Captures

`branding-styles.spec.ts` runs in Chromium and WebKit at desktop (1280×800)
and mobile (390×844, touch) against a disposable seeded database. Expected
values are read from the PHP definitions (`BrandPalette::presets()`,
`BrandStyle::PRESETS`), so the pages are checked against the source the
server uses.

| Capture | Shows |
|---|---|
| `ledger-{light,dark}-{navigation,reading,forms,messaging}` | Ledger for a member on the directory with its rail, a topic, the appearance settings and Messages: mist/slate grounds, the rail flush with the page, EB Garamond headings, crisp corners |
| `vellum-{light,dark}-{navigation,reading,forms,messaging}` | Vellum on the same four surfaces: vellum/charcoal grounds, a raised rail, Marcellus headings, soft corners; the appearance thumbnails name Vellum |
| `vellum-overrides`, `package-with-overrides` | Vellum with crisp corners and EB Garamond headings; then an active package owning the base while those two overrides still apply |
| `density-guest-compact`, `density-undecided-member` | A compact default for a visitor and for a member who never chose; a member who chose Comfortable keeps it |
| `no-js-style-controls`, `no-js-style-sample` | Without JavaScript, the Style group publishes Ledger with soft corners and a compact default; the sample shows the saved style while the branding page keeps the built-in style |
| `keyboard-style-controls`, `keyboard-style-sample` | Keyboard only (desktop): the parts follow the preset in reading order, the "Preset — …" labels follow the chosen preset, and the sample follows a corner change |
| `large-text-style-controls` | Large text: the Style group wraps inside the page |

On every page of both new presets, in both modes, the spec also asserts:

- the `<html>` attributes match the preset (none for built-in parts);
- the rail's computed ground is the treatment's surface and the body is the
  preset's page ground;
- the study title uses the pairing's face, body text stays EB Garamond, and no
  visible text is left in Cormorant once another heading face is chosen;
- the root corner scale and a real field's corners match the preset;
- no element is painted with Classic's grounds (constant-ground stages, identity
  tints and status chips are outside this check by design).

## Verification

| Check | Result |
|---|---|
| Focused PHP | `AppBrandingStyleTest` (10), `BrandStyleContractTest` (6), `BrandStyleTest` (4), `BrandPaletteTest` (12, including every text role on every ground and sidebar of every preset in both modes), `AppThemePackageTest` (26, including package precedence and safe mode), and the branding, palette, identity, revision and draft suites |
| Styles spec | Chromium 13 passed, WebKit 13 passed (desktop and mobile; the keyboard test is desktop-only) |
| Unchanged when unstyled | [`unstyled-pixel-comparison.json`](unstyled-pixel-comparison.json): the previous release (`19bad808`) and this candidate on one database, for Classic and Retro, guests and a member, Chromium and WebKit, light and dark, desktop and mobile — 139 of 144 viewport captures pixel-identical. The other five (21–33 pixels each, Chromium only) were re-captured three times per side: the same differences appear between captures of the *same* server, and every case has pixel-identical previous/candidate pairs, so they are render nondeterminism rather than a change |
| Existing journeys (Chromium), each on a freshly prepared database | `branding-palettes` 13, `branding-drafts` 6, `branding-revisions` 9, `branding-identity` 15, `theming-remediation` 20, `admin-appearance-followup` 22, `branding-public-theme` 22, the `gate-a` branding/theme-package journeys 4 — all passed. `member-surfaces` passed 10; its two `compose destination picker` cases fail identically at `19bad808` and at `120b799f`, before any branding increment (the anonymity chip stays visible on a board that disallows it) — a pre-existing composer issue outside this change |
| Full PHP suite (final candidate) | 3,506 tests / 33,425 assertions, zero failures, one intentional migration-rehearsal skip |
| Generated assets and Worker | `check:assets`: current; `test:assets`: 35 passed |

Found and fixed during qualification:

- The full branding reset cleared a fixed key list that predated per-mode
  colours, leaving them in place; it now clears every resettable group.
- The appearance page named Classic for any non-Retro preset; it now names the
  serving preset.
- An 8px literal radius on record lists would not have followed a corner
  choice; it now reads the scale (still 8px with standard corners).

## Production (recorded after publication)

`12fe5c91` was pushed to `main` at 23:07:02 UTC on 2026-10-31. Sanitized
receipts are in `production/`:

| Receipt | Result |
|---|---|
| `cloud-run-application-build.json` | Cloud Build `cd117544` SUCCESS for `12fe5c91` at 23:10:17 UTC |
| `cloud-run-application-service.json` | Revision `retroboards-00069-qmr` Ready, 100% traffic, image `app:12fe5c91…`; no migration in this release |
| `cron-application-images.json` | All four cron jobs on the same image |
| `worker-application-deployment.json` | Worker version `f93b7ef7-68ab-44e7-8492-ce012e1b4019` at 100% (23:08:38 UTC), the first deployment after the push |
| `live-assets-application.json` | 57/57 manifest files with matching SHA-256 (current `0abd713b0ebf7bca` plus retained releases); `/healthz` ok with the database |
| `live-styles-verification.json` | `/admin/branding` requires sign-in; `/` uses the new stylesheet and, with no style configured yet, stamps no style attributes and keeps Comfortable density; the stylesheet carries the rail hook and the sidebar, corner and pairing rules; the sample-scoped stylesheet carries the Ledger and Vellum sample rules; the script carries the style-preview hook |

The deployed asset release (`0abd713b0ebf7bca`) is recorded in the commit that
carries these receipts, which ADR 0051 groups with the next planned change
rather than publishing on its own.
