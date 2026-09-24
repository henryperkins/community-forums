# Imladris profile production evidence

This directory records the 2026-08-03 source-to-production review of the member profile surface, refreshed on 2026-09-24 for PR #75 (profile contrast, menu clipping, and connection navigation). The browser suite renders the checked-in Imladris `UserProfile.dc.html` source and the real application at the same viewport, writes paired comparison sheets, and exercises the application against the dedicated `retroboards_e2e` database.

## Visual target and approved exceptions

- Source template: `docs/design-system/imladris/templates/user-profile/UserProfile.dc.html`.
- Written visual contract: `docs/design-system/imladris/imladris-spec.md`, especially the profile's twilight cover and supplied Commend Star.
- The source HTML's light-theme cover uses parchment tokens. That conflicts with the written contract and the approved production direction, so production deliberately retains the twilight cover in both themes. All other reviewed anatomy, spacing, typography, tabs, rows, plinths, cards, empty states, and responsive behavior follow the source.
- Application vocabulary remains authoritative: Regard, moderator/moderation, and member record replace source-only council/warden language.
- Profile-level Report, gated-profile Message, and Request access are omitted because those flows do not exist for this application state. No replacement behavior was invented.
- Fixture titles, counts, badges, dates, and shared application-shell navigation differ from the standalone source by design.
- The Commends tab's Regard card sits on the raised surface; gold is only its hairline, its star, and the Regard label (DESIGN.md One Gold Rule). The source's gold-100 wash does not flip with the theme, so in twilight it put gold-800 ink on a light wash and failed contrast.
- Every profile empty state, the no-results and Connections empties included, uses the shared dashed frame with a title and a sentence. The source leaves the no-results and Connections empties unframed.

## Coverage ledger

| Surface/state | Desktop 1160x900 | Mobile 390x844 | Light/dark | Axe serious/critical | Overflow/console |
| --- | --- | --- | --- | --- | --- |
| Guest, populated overview | Captured | Captured | Both | Pass | Pass |
| Guest, private profile | Captured | Captured | Both | Pass | Pass |
| Guest, empty profile | Captured | Captured | Both | Pass | Pass |
| Signed-in member | Captured | Captured | Both | Pass | Pass |
| Profile owner, Connections | Captured | Captured | Both | Pass | Pass |
| Moderator context | Captured | Captured | Both | Pass | Pass |
| Guest, Commends tab | Captured | Captured | Both | Pass | Pass |
| Guest, 32-character handle | Captured | Captured at 320px | Both | Pass | Pass |
| Signed-in member, ··· menu open | Captured | Captured | Both | Pass | Pass |
| Signed-in member, Block confirmation step | Captured | Captured | Both | Pass | Pass |
| Member who blocked the profile, ··· menu on a short row | Captured | Captured | Both | Pass | Pass |

The interaction journey additionally covers all five tabs, topic search, newest/most-commended sorting, paging, Connections search, stable `/followers` and `/following` routes, Remove follower visibility, keyboard focus, the action disclosure, Clipboard-enhanced Copy link, and forms/navigation with JavaScript disabled.

The 2026-09-24 refresh pins PR #75's claims:

- The header Followers and Following counts link to `?tab=connections` and `?tab=connections&c=following` and land on the Connections tab with the matching list, with and without JavaScript.
- The open ··· popover lies wholly inside the viewport at 1160px, 390px, and 320px, is painted on top at all four corners, opens under the ··· that opened it, and on a phone stays inside the cover's horizontal extent. This holds for a full action row and for a short one: the viewer blocks the member through the real Block step, only ··· remains, and the spec then unblocks through the real Unblock form.
- The ··· trigger on the twilight cover measures at least 3:1 in light and dark, at rest and hovered (a tap leaves hover behind on a touch screen).
- The nested Block step shows its `Block @username` confirmation on screen, and with a 32-character handle its sentence and button wrap inside the menu. The step folds whenever the menu closes, by Escape or by a click outside, so ··· reopens on its first level.
- Copy link reads `Copied` and announces `Link copied.`, goes back to `Copy link` after two seconds, and a second copy clears the status first and announces again.
- On a phone the Posts, Followers, and Following numbers sit the same distance under their labels and share a top on each row. The linked labels and the website take a 44px tap target from a centred `::after`. At 320px the id column beside the avatar is about 176px wide, so Following wraps under Posts. That row break is layout, not a baseline shift.
- A 32-character unbroken handle with no display name, beside an unbroken website, wraps inside the cover with no horizontal scroll at 390px and 320px.
- On the Commends tab the commend counts and the Regard card's value, label, and note measure at least 4.5:1 in light and dark, and the card has no gradient.
- Connections pages at 20: a signed-in viewer sees 20 + 2 followers and a guest 20 + 1, and the guest's Followers count reads 21. The members-only follower is left out for guests on the tab and on `/followers` (ADR 0031 §2). The standalone list keeps `.person-rep`.

The fixture (`tests/browser/profile-surface-fixture.php`) adds `Celebrimbor_of_Eregion_Ringsmith` for the long-handle and short-row states, and `lamplighter` with 21 `hearth_NN` followers plus `private-seat` for paging.

Known gaps, reported for follow-up and not asserted here: the standalone `/u/{username}/followers` and `/following` pages scroll sideways on a touch phone. They overflow by 2px at 390px, because `.read-pad`'s -16px margins exceed `main`'s 14px padding, and by about 200px when the handle has 32 characters, because the `@handle` in the h1 does not wrap. Both predate PR #75.

## Artifacts

- `comparisons/`: paired Imladris-source and production images for desktop/mobile and light/dark.
- `reference/`: browser-rendered source-template captures.
- `desktop/` and `mobile/`: full-page production captures for every state in both themes. `mobile/guest-long-handle-*` is taken at 320px, where wrapping is forced.

## Verification result

On 2026-09-24, from `tests/browser`, `DB_DATABASE=retroboards_e2e_pr75ev E2E_PORT=8081 bash prepare.sh && DB_DATABASE=retroboards_e2e_pr75ev E2E_PORT=8081 npx playwright test profile-surface.spec.ts --reporter=list` completed with 14 passed: seven tests on each of desktop and mobile. `cmp` confirmed that each of the 26 dark captures differs from its light twin. The mobile menu, Block-confirmation, blocked short-row, long-handle, and Commends captures and the desktop Block-confirmation and Commends captures were inspected at original resolution, cropped to the cover, the open menu, and the Commends panel.

The 2026-08-03 review: `E2E_PORT=8013 npx playwright test profile-surface.spec.ts` completed with 6 passed. The related existing profile-media and custom-field coverage also completed with 4 Gate A cases and 4 Axe cases passing across desktop/mobile. The generated comparison sheets were inspected together at original resolution. The final small-screen pass tightened tab padding so the full Connections label remains visible at 390px; the regenerated mobile light and dark sheets were inspected after that correction.
