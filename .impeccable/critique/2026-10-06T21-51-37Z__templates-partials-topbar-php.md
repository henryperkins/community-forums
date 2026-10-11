---
target: the header (templates/partials/topbar.php)
total_score: 27
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 2
target_identity: "file:/home/ubuntu/community-forums/templates/partials/topbar.php"
target_fingerprint: "sha256:dc5a8e630a5d446d575b723b2f1b642223f0fa3a44f3cdca7cd8d387e8bc6058"
target_path: /home/ubuntu/community-forums/templates/partials/topbar.php
timestamp: 2026-10-06T21-51-37Z
slug: templates-partials-topbar-php
closed: true
---
Method: dual-agent (A: isolated design-review agent · B: isolated detector + browser agent). B finished first, so its results reached synthesis before A's; A never saw them. Load-bearing claims were re-verified in the browser before writing.

Target: the member header, templates/partials/topbar.php (header.forum-bar), on a private seeded copy at 320–1440px, both registers, guest/member/moderator/admin. The admin console header was used only for comparison.

## Design Health Score

| # | Heuristic | Score | Key issue |
|---|---|---|---|
| 1 | Visibility of System Status | 3 | Counts cap at 99+ with full accessible names; current surface is a 1.04:1 wash; Inbox count isn't polled; at 861–1279px the reading-pane toggle reports a state with no pane on screen |
| 2 | Match System / Real World | 3 | Plain route labels; first words are "Search the council…"; ⌘ hints shown on Windows/Linux |
| 3 | User Control and Freedom | 3 | Escape/Tab-out close menu and drawer with focus returned; enhanced drawer has no close control inside its Tab loop |
| 4 | Consistency and Standards | 2 | Two unrelated count badges; two "New topic" buttons with different outcomes on a board page; focus squares off pills; twilight bell hover dims while seat brightens; no long-name truncation (admin header has it) |
| 5 | Error Prevention | 2 | New topic preselects Announcements from anywhere; phone targets 40px with 2px gaps; page widgets take taps meant for the open menu's Log out |
| 6 | Recognition Rather Than Recall | 3 | Routes keep labels at every width; pane toggles identical when both off; shortcuts only in hover titles |
| 7 | Flexibility and Efficiency | 3 | Ctrl/⌘+K/B/J work and persist; no chords for Inbox/Messages/New topic; ⌘K is a page load |
| 8 | Aesthetic and Minimalist Design | 3 | Desktop day bar restrained; 108px phone band mostly empty, orphan divider, duplicate Notifications, 99+ over the bell |
| 9 | Error Recovery | 3 | 404 keeps the full bar; failed toggle save falls back to a real POST |
| 10 | Help and Documentation | 2 | Hover-only titles; tour says "Click the name" where no name shows below 900px (tour.js:22) |
| **Total** | | **27/40** | **Acceptable** |

## Design Specificity Verdict

LLM assessment: the skin is authored, the skeleton is not. Star mark on --accent, one family per job (Cormorant/Marcellus/JetBrains Mono), the panel glyph whose band names the open pane, the presence leaf, and "the council" belong to this product; strip fonts and palette and the structure is the GitHub/Linear bar. Missed thesis: in a Community Inbox the Inbox is the quietest mark, and the thread row's gold-dot-and-halo unread signature never reaches it.

Deterministic scan: detect on topbar.php + layout.php = 0 findings (with and without config). In-page: ≤5 header findings per page; one holds — the ⌘K hint at 10.24px (0.64rem) is under DESIGN.md's 0.7rem chip floor (count chips share the size, unflagged). False positives: gpt-thin-border-wide-shadow on the account menu (DESIGN.md assigns --shadow-lg to menus); text-occlusion on menu items (raised while the <details> was closed — the real overlap in P1 sits one row lower, below the centre-point probe); cream-palette (binding parchment ground). axe scoped to the header: 0 violations both registers; the 390px menu-open target-size hit is the menu correctly covering the route pills.

Visual overlays: none user-visible; detect.js ran in headless, CSP-bypassed Chromium and findings were read from its console.

## Overall Impression

A carefully built, well-tested take on a standard app bar whose failures all sit at the edges: stacking against page content, operator names and logos, phone geometry, and state marks too faint to read. Biggest opportunity: keep the chrome on top and make "what changed" its loudest mark.

## What's Working

1. One contract, honestly progressive: identical on 11 routes × 4 audiences × both registers; active pill keeps its href; 404 keeps identity and routes; pane toggles are POST forms that persist without JS and apply instantly with it.
2. Brand through legibility: every header text ≥4.62:1 in both registers; one family per job; ornament stops at the star.
3. Keyboard fundamentals: skip link first; visible ring on every stop; menu and drawer close on Escape and return focus; drawer traps focus and inerts the page; reduced motion collapses transitions.

## Priority Issues

[P1] Page content paints over the sticky header and its account menu. .forum-bar is sticky at z-index 20 (imladris.css:879); the menu's 45 counts only inside the header's stacking context; .directory-viewbar-mobile (app.css:16092, home ≤760px) and .post-toolbar (app.css:11195, topics) at 30 win. Verified at 390px on /: with a moderator's menu open, tapping Log out hits the page's "Viewing · By category" disclosure; scrolled 250px, that bar covers the header's bottom 19px and takes taps meant for the route pills. Fix: chrome above in-page layers, below scrim (55)/drawer (60)/dialogs; z-index token scale. Command: /impeccable harden

[P1] The operator's name and logo have no width budget. Brand flex:none, wordmark nowrap without ellipsis (imladris.css:880, :882); .brand-logo no max-width; names may be 80 chars (SetupService.php:74); admin header truncates (imladris.css:843–844). Measured: 32 chars overflows /inbox at 901px; 40 chars overflows /inbox at 901px and 1180px; 48 chars puts the seat 148px off-screen on /inbox at 901px. A: 80 chars breaks every desktop width to 1440px; a 4:1 logo pushes the 320px bar's first row above the viewport. Fix: brand flex 0 1 auto + min-width 0, ellipsis wordmark with clamp() and full accessible name, cap the logo, let search flex. Command: /impeccable harden

[P2] Two header controls ignore the page they're on. New topic is bare /compose (topbar.php:143) → preselects Announcements from any board or topic; a board page shows two "New topic" buttons with different outcomes. The reading-pane toggle renders at 861–1279px though the pane is a column only ≥1280px (app.css:16046–16050); at 1200px it flips Hide/Show + aria-pressed and persists, nothing on screen changes. Fix: ?board_id= on /c/* and authorized topics (active_thread_board_id is already known); render the reading toggle only ≥1280px. Command: /impeccable harden

[P2] The marks that matter most are the faintest. Current surface 1.04:1 wash, 1.62:1 ink difference, indistinguishable under forced colours. Inbox/Messages count 1.10:1 chip, JetBrains Mono 10.24px (imladris.css:892). Bell count solid gold, EB Garamond 700 11px from primitives --accent-2/--ink-900 (app.css:693, :1573) — the loudest unread mark, and off the Tabular Rule. Toggle "on" band 2.65:1; both toggles identical empty boxes when off. Twilight: star, New topic fill and bell badge all gold. Fix: one count component (mono, tabular-nums, semantic tokens) with the Inbox count strongest (reuse the gold dot + halo); non-colour cue for the active pill; legible toggle state. Command: /impeccable colorize

[P2] Phones spend header space badly (deliberate, ADR 0042). 108px two-row header ≤860px (app.css:16375); ~510px empty on row 1 at 860px; a guest's second row is one "Boards" pill; route labels 11.8px; name hidden ≤900px; search label and username collapse ≤1080px with ~300px free; row-1 targets 40×40 with 2px gaps (app.css:16357); focus order row 1 → row 2 → row 1 (order:1, app.css:16377). Fix: wrap only when needed (container query), no single-pill guest row, labels ≥13px, truncate instead of hide, 44px targets with ≥8px gaps. Command: /impeccable adapt

## Persona Red Flags

Alex: ⌘K loads /search, not a palette; no chords for Inbox/Messages/New topic/Notifications; ⌘ glyph on Linux/Windows; Inbox count not polled (app.js:147 polls bell + DMs only); reading toggle dead at 861–1279px.
Sam: pane toggles triple-encode state (label Hide/Show + aria-pressed + aria-expanded, topbar.php:122/133); account control named "Open account menu…" while open; enhanced drawer has no close control in its Tab loop (.nav-close only pre-enhancement, app.css:1762); counts named via aria-label on role-less spans (topbar.php:80/90); shortcuts only in title attributes; current surface colour-only.
Casey: every control in the top 108px; New topic/bell/seat 40px targets 2px apart; page bar slides over the header; 99+ covers the bell glyph; Sign up 43×19.
Jordan: no community name below 900px; first words "Search the council…"; Sign up is the quiet option next to a filled Log in; phone row 2 is one Boards pill above an identical page tab.
Morgan (operator): 32-char name overflows /inbox at 901px, 40–48 up to 1180–1280px; no logo max width; Administration is item 7 of 8 in an undivided menu.
Moderator: no open-reports signal on the bar (count lives in the closed menu, topbar.php:193); admins get none.

## Minor Observations

- Orphan divider at 721–860px and on plain pages (topbar.php:139 vs app.css:16359).
- Global :focus-visible { border-radius: 2px } (app.css:766) squares off the search pill, route pills, toggles on focus.
- Brand, hamburger and New topic lack the gold focus halo (absent from app.css:16303).
- Log in has no hover state (no .forum-bar-signin:hover rule).
- Twilight bell hover dims to 2.89:1 (--brand, app.css:1571) while the seat brightens to gold.
- Server renders "1 unread conversations" (topbar.php:90); app.js fixes the plural only after the first poll.
- Account menu: Profile and Settings share a glyph; no separator before Log out; Notifications duplicates the bell.
- Brand and Boards both go to "/" while members land on /inbox.
- Wordmark at 600 vs DESIGN.md's "medium (500), not bold" (imladris.css:882, design-system value).
- Tour's "the search box" (tour.js:23) doesn't match ≤1080px, where search is an icon.
- The bell isn't marked current on /notifications.

## Questions to Consider

- If the Community Inbox is the product, why is its count the quietest mark in the bar?
- Does a stranger from search know who "the council" is? Should private lexicon be the bar's first words?
- ⌘K promises a palette: become one, become a type-ahead field, or become an honest "Search" link?
- What if the operator's identity slot were designed first, with everything else flexing around it?
- In twilight, should gold be reserved for "unread" alone?
