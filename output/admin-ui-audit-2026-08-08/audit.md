# RetroBoards admin UI review

Captured: 2026-08-08 against a freshly seeded local `retroboards_e2e` environment.

## Scope

This is a visual/interaction review of the shared Admin Console chrome and representative high-impact routes: Dashboard, Reports, Members, and Settings. It covers desktop (1280px), an intermediate desktop width (960px), the 861px breakpoint edge, and mobile (390px). It does not claim a complete functional or accessibility certification of every admin route.

## Overall verdict

The console has a strong operating-system feel: the separate Admin mode, two-tier information architecture, clear current-area state, and scoped forms make the product feel intentional rather than like a member UI with extra links. The main concern is the responsive transition just above the 860px breakpoint; it visibly breaks the shell that every admin route shares.

## Findings

### H1 — The shared console header breaks from 861px through the intermediate desktop range

Evidence: `09-dashboard-960px.png`, `10-dashboard-861px.png`.

At 960px, the area tier exposes a bright full-width horizontal scrollbar beneath the header. At 861px, the identity row is visibly crowded (`Log out` wraps into two lines, the user name is truncated), while the area row clips later destinations such as Features and Settings. The routes remain available by horizontal scrolling, but this is a poor transition for portrait tablets and narrow desktop windows because navigation and account controls lose their normal hierarchy on every console page.

Recommendation: introduce the compact/mobile console composition before the header begins to overflow, or add a deliberate intermediate layout that reduces the header cluster and area-tier spacing. Keep the required overflow affordance, but theme it as a thin, intentional dark-surface scrollbar rather than the current pale bar.

### M1 — The mobile member directory makes an urgent lookup start with a long wall of filters

Evidence: `06-members-mobile.png`, `07-members-mobile-results.png`.

On a 390px screen, the first viewport contains only the first six of eight filter controls; members and bulk actions are well below the fold. That is costly when an operator needs to find or act on a known member quickly.

Recommendation: show search plus the two most common filters initially, and place the date/post-count filters behind a native, no-JS-capable “More filters” disclosure. Retain the current GET-based filter contract and make active filters visible as summary chips or a count.

### M2 — Horizontal table overflow is functional but not explained consistently

Evidence: `05-dashboard-mobile.png`, `07-members-mobile-results.png`.

The dashboard's audit table explicitly says “Scroll for Target and Reason,” but the wider member table provides only its scrollbar. On mobile, State, reputation, posts, activity, and join date are initially hidden without an equivalent cue. The scrollbar makes the table operable, but the experience asks users to infer that key data continues off-screen.

Recommendation: use the same short directional cue and edge treatment on the member table, while preserving the keyboard-scrollable region and the visible scrollbar.

## Strengths

- The persistent mode label and “Back to the forum” link prevent context mistakes (`01-dashboard-desktop.png`).
- Dashboard cards use plain-language status, counts, and direct destinations; clear states are not communicated by color alone (`01-dashboard-desktop.png`).
- The reports empty state is calm, clear, and leaves filtering available (`02-reports-desktop.png`, `08-reports-mobile.png`).
- The directory makes sorting, selection, and the follow-up confirmation step understandable (`03-members-desktop.png`, `07-members-mobile-results.png`).
- Settings separates community identity from registration posture, with specific save actions and explanatory copy (`04-settings-desktop.png`).

## Evidence limits

This review did not test real report records/actions, invalid-form recovery, screen-reader output, zoom/reflow beyond the captured viewports, contrast ratios, or the no-JavaScript path. Those need targeted functional and accessibility verification before claiming compliance.
