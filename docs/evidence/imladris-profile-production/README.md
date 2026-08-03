# Imladris profile production evidence

This directory records the 2026-08-03 source-to-production review of the member profile surface. The browser suite renders the checked-in Imladris `UserProfile.dc.html` source and the real application at the same viewport, writes paired comparison sheets, and exercises the application against the dedicated `retroboards_e2e` database.

## Visual target and approved exceptions

- Source template: `docs/design-system/imladris/templates/user-profile/UserProfile.dc.html`.
- Written visual contract: `docs/design-system/imladris/imladris-spec.md`, especially the profile's twilight cover and supplied Commend Star.
- The source HTML's light-theme cover uses parchment tokens. That conflicts with the written contract and the approved production direction, so production deliberately retains the twilight cover in both themes. All other reviewed anatomy, spacing, typography, tabs, rows, plinths, cards, empty states, and responsive behavior follow the source.
- Application vocabulary remains authoritative: Regard, moderator/moderation, and member record replace source-only council/warden language.
- Profile-level Report, gated-profile Message, and Request access are omitted because those flows do not exist for this application state. No replacement behavior was invented.
- Fixture titles, counts, badges, dates, and shared application-shell navigation differ from the standalone source by design.

## Coverage ledger

| Surface/state | Desktop 1160x900 | Mobile 390x844 | Light/dark | Axe serious/critical | Overflow/console |
| --- | --- | --- | --- | --- | --- |
| Guest, populated overview | Captured | Captured | Both | Pass | Pass |
| Guest, private profile | Captured | Captured | Both | Pass | Pass |
| Guest, empty profile | Captured | Captured | Both | Pass | Pass |
| Signed-in member | Captured | Captured | Both | Pass | Pass |
| Profile owner, Connections | Captured | Captured | Both | Pass | Pass |
| Moderator context | Captured | Captured | Both | Pass | Pass |

The interaction journey additionally covers all five tabs, topic search, newest/most-commended sorting, paging, Connections search, stable `/followers` and `/following` routes, Remove follower visibility, keyboard focus, the action disclosure, Clipboard-enhanced Copy link, and forms/navigation with JavaScript disabled.

## Artifacts

- `comparisons/`: paired Imladris-source and production images for desktop/mobile and light/dark.
- `reference/`: browser-rendered source-template captures.
- `desktop/` and `mobile/`: full-page production captures for every state in both themes.

## Verification result

`E2E_PORT=8013 npx playwright test profile-surface.spec.ts` completed with 6 passed. The related existing profile-media and custom-field coverage also completed with 4 Gate A cases and 4 Axe cases passing across desktop/mobile. The generated comparison sheets were inspected together at original resolution. The final small-screen pass tightened tab padding so the full Connections label remains visible at 390px; the regenerated mobile light and dark sheets were inspected after that correction.
