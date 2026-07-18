# RetroBoards Admin Dashboard UI audit

Date: 2026-07-18  
Environment: seeded `retroboards_e2e` database, authenticated test administrator, Chrome  
Viewports: 1280 x 800 desktop and 390 x 844 mobile  
Scope: `/admin` landing dashboard only; downstream admin pages were not audited.

## Overall verdict

The dashboard has a solid operational foundation: it establishes Admin mode clearly, exposes linked queue and health summaries, handles the empty attention state cleanly, and uses a consistent Imladris visual system. Its main problem is information architecture. A landing dashboard, global admin navigation, several settings forms, custom-emoji management, and the recent audit feed all occupy one very long page. The result is harder to scan than the control-room brief calls for, and the problem becomes acute on mobile.

## Captured steps

1. Desktop landing — mixed. The operator identity, linked status cards, and clear empty state are strong, but the ungrouped admin navigation is dense and the required attention/audit information is pushed down by settings forms.
2. Mobile landing — needs work. The responsive cards reflow correctly, targets remain usable, and the page does not overflow horizontally; however, all 19 enabled admin destinations plus one disabled destination remain expanded before the operational summary.
3. Keyboard entry — healthy. The skip link receives a visible 2px focus outline. Activating it changes the target to `#main`, and the next Tab reaches the Dashboard link inside the admin content. The mobile activity table is a labelled, keyboard-focusable horizontal-scroll region.

## Confirmed strengths

- `Operator desk`, `Admin console`, and the `Admin mode` badge make the mode change unmistakable.
- Summary cards are links with a count, short explanation, and direct destination.
- The empty `Needs attention` state is plain and reassuring.
- Sampled text contrast is strong: inactive admin navigation 8.35:1, disabled note 6.25:1, and summary cards 10.02:1.
- Mobile top-bar and admin-navigation targets measured at 44px high.
- No browser console errors or warnings were observed during the run.

## Prioritized findings

### High — The landing dashboard is doing too many jobs

The page is approximately 2,989px tall on desktop and 4,047px on mobile. `Site name`, `Trust & safety`, and `Custom emoji` management sit between `Needs attention` and `Recent activity`, burying the audit feed near the bottom. The dashboard spec describes a landing surface made of metrics, an attention list, and recent activity; these settings belong behind focused destinations or progressive disclosure.

Recommendation: keep `/admin` action-first. Retain the summary cards, attention queue, system warnings, and a compact recent-activity feed. Move the three configuration forms to grouped Settings, Moderation, and Content destinations, with small dashboard links when useful.

### High — Admin navigation does not adapt to the mobile task

At 390px, the page renders 19 enabled admin links and the disabled Extensions entry in a wrapping text grid. The first operational card begins around 624px down the page. The global hamburger controls the member navigation, not these admin sections. This conflicts with the admin spec's grouped information architecture and mobile section drawer.

Recommendation: group destinations into the specified sections on desktop and expose those groups through an `Admin sections` drawer or compact menu on mobile. Keep the current page and disabled-state semantics, but move them out of the always-expanded first viewport.

### Medium — The operational summary needs sharper labels and priority

`Users 5` is actually the count of new users today, while its supporting line reports active users in the last 15 minutes. The card reads like a total-user count. `Audit 109` is a total log count rather than an urgent signal, and healthy zero-value cards receive the same weight as actionable states.

Recommendation: rename the card `New users today`; keep active users as secondary text. Sort or style cards by operator urgency, demote healthy zeros, and surface nonzero attention items before general totals.

### Medium — Desktop navigation lacks the documented grouping

Desktop navigation is a three-row sequence of links without the documented Moderation, Content, People, Appearance, Notifications, Integrations, and Settings groups. It is visually tidy but slower to scan as the console grows.

Recommendation: use visible group labels and a persistent grouped rail, or a small number of top-level grouped menus. Preserve the clear active underline and 44px targets.

### Low — The mobile audit table needs a visible overflow cue

The activity table correctly lives in a labelled, focusable horizontal-scroll region (`281px` viewport over a `760px` table), but the screenshot offers no clear hint that Target and Reason continue offscreen.

Recommendation: add a short `Scroll for Target and Reason` hint, edge fade, or mobile stacked-row presentation.

## Evidence limits

This was a combined visual, responsive, keyboard-entry, target-size, sampled-contrast, and console-error review. It does not establish full WCAG conformance. Screen-reader announcements, zoom/reflow above the tested widths, high-contrast/forced-colors behavior, reduced motion, and all downstream admin workflows still need dedicated testing.

## Screenshots

- `01-dashboard-desktop.png` — desktop entry view
- `02-dashboard-desktop-full.png` — full desktop page
- `03-dashboard-mobile.png` — mobile entry view
- `04-dashboard-mobile-full.png` — full mobile page
- `05-dashboard-keyboard-focus.png` — visible skip-link focus
