# Slice 4 account shell design QA

Status: complete for the Slice 4 account-shell boundary.

Reference: `docs/design-system/imladris/templates/account-settings/AccountSettings.dc.html`

Captured 2026-08-04 by `tests/browser/account-console.spec.ts`: 6 passed and 6 expected cross-project skips. The run used the real PHP application, `retroboards_account_s4_e2e`, desktop 1280×800, and mobile 390×844.

Reviewed against the reference:

- The shared eyebrow, `Account settings` h1, and exact introduction render once on every owned route.
- The desktop rail is 232px wide with a 30px pane gap, keeps the exact three group memberships and destination order, and remains at `var(--topbar-h) + 22px` after scrolling.
- Active state is singular and route-owned. Each gated destination disappears only with its own dark feature; no disabled placeholders remain.
- The 390px rail becomes a static, two-column wrapped list. Every control is at least 44px high and the document has no horizontal overflow.
- A JavaScript-disabled mobile context followed all 14 real destinations and preserved the explicit active destination on the 13 Slice 4-owned panes.
- Profile, Security, and Connections were scanned in light, twilight, and system-dark registers with no serious or critical axe findings.

Representative captures:

- `desktop/profile-light.png`
- `desktop/profile-twilight.png`
- `mobile/profile-light.png`
- `mobile/profile-twilight.png`
- `mobile/account-no-js.png`
- `comparisons/{profile,security,connections}-{light,twilight,system-dark}.png`

Adjudicated deviations in scope:

- `C-36` — flag-dark account destinations are silently omitted.
- `C-37` — the unmodelled mobile state remains server-rendered, static, wrapped, touch-safe, and overflow-safe.
- `FA-28` — Replay tour remains a subordinate progressive-enhancement button after the navigation groups.

This evidence certifies only the shared account head and navigation shell. Pane bodies remain later work for Slices 15–17. The `/appeals` body remains Slice 18 work; Slice 4 exposes only its gated rail destination and deliberately leaves it without a current account-rail item.
