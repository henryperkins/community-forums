# Design QA: Imladris forum surfaces prototype

## Scope

This review covers the three approved prototype surfaces:

- Forum Index: `#/`
- Individual Board Thread List: `#/c/the-archive`
- Thread View: `#/t/184-where-should-ratified-decisions-live`

The prototype is a review artifact. It does not change the production PHP templates, routes, permissions, feature flags, or persistence behavior.

## Visual sources and evidence

| Surface | Imladris visual truth | Implementation evidence | Combined comparison |
| --- | --- | --- | --- |
| Forum Index | `docs/design-system/imladris/templates/reading-rooms/.thumbnail` | `evidence/forum-index-qa-1266x854-v2.png`, `evidence/forum-index-mobile-v2.png` | `evidence/forum-index-comparison-v2.png`, `evidence/forum-index-focus-comparison.png` |
| Board | `evidence/board-identity-direction-a-reference.png` | `evidence/board-identity-desktop.png`, `evidence/board-identity-mobile.png` | `evidence/board-identity-comparison.png` |
| Thread | `docs/design-system/imladris/templates/thread-view/.thumbnail` | `evidence/thread-qa-1266x854.png`, `evidence/thread-mobile.png` | `evidence/thread-comparison.png`, `evidence/thread-focus-comparison.png` |

Desktop implementation captures use a 1266 × 854 viewport. Forum and board captures were downsampled to 633 × 427 for direct comparison with their source thumbnails. The 640 × 432 thread source and implementation were normalized to the same comparison frame. Focused crops compare the primary header, ruled-list, poll, and Living Brief details at matched scale.

## Approved structural deltas

These differences are intentional product decisions, not visual defects:

- The Reading Rooms preview pane is omitted from the Forum Index because it invents behavior and makes `/` resemble `/inbox`. The result is a calm directory-only page.
- The Board Index reading pane, Hall/Watch controls, sort tabs, and inbox filters are omitted from `/c/{slug}`. A board remains a canonical topic list ordered by pinned status and last post.
- The production application rail remains present on the focused thread because removing existing global navigation would remove a feature. The thread content itself follows the Imladris single-reading-column treatment.

## Visual findings and iteration

### Iteration 1

P2: Forum Index rows initially used circular hash markers and 86px rows, which drifted from the compact ruled-directory source.

Resolution: the gold hash was integrated into each board name, row height was reduced to 68px, and board names, descriptions, and counts were aligned into the source's restrained columns. Post-fix evidence is in `forum-index-qa-1266x854-v2.png` and `forum-index-comparison-v2.png`.

### Iteration 2

The interaction review found hash-route collisions, off-canvas focus leakage, misleading prototype links, mismatched mobile action order, and repeated reply IDs.

Resolution:

- Skip and post-fragment links now preserve the active hash route and move focus to their in-page targets.
- The closed mobile rail and Topic Tools drawer are inert; the drawer supports initial focus, Escape, focus containment, and focus return.
- Only the demonstrated board and exemplar topic are interactive.
- Mobile board actions use the same DOM and visual order.
- Prototype replies receive unique keys and fragment IDs.
- The CSS and JavaScript mobile navigation breakpoint is aligned at 840px.

An independent post-fix review found no remaining P0, P1, or P2 issue in the approved scope.

### Iteration 3: board-only identity band

The prior board capture at `evidence/board-qa-1266x854.png` showed a transparent board header with a hairline divider. The approved Direction A reference was restored as `evidence/board-identity-direction-a-reference.html` and captured as `evidence/board-identity-direction-a-reference.png`.

The same `#/c/the-archive` parchment-theme route and mock data were captured at 1266 × 854 CSS px (`evidence/board-identity-desktop.png`) and 390 × 844 CSS px (`evidence/board-identity-mobile.png`) at device scale factor 1. The Direction A source element is 440 × 143 px; the rendered board-header element is 836 × 188 px. `evidence/board-identity-comparison.png` places the source at its original 440 px width next to the rendered header normalized to the same 440 px displayed width (440 × 99 px), so browser chrome and differing page frames do not affect the judgment.

Focused comparison found no actionable P0, P1, or P2 drift: the implementation uses the Direction A evergreen fill, parchment text, mallorn-gold bottom rule, pale supporting copy, and gold primary action while retaining the approved full board information and action layout. The desktop and mobile captures preserve the target’s typography roles, measured spacing rhythm, palette, supplied iconography, and board copy. The mobile capture has no horizontal overflow and keeps the DOM/visual action order as Following then New topic. `evidence/thread-identity-desktop.png` confirms the canonical thread remains parchment with its existing hairline divider rather than inheriting the board band.

### Iteration 4: exact-head parchment-heading recapture

After the hash-mark repair and exact route-boundary repairs, the board evidence was regenerated from the current source at 1266 × 854 (`evidence/board-identity-desktop.png`) and 390 × 844 (`evidence/board-identity-mobile.png`) CSS px at device scale factor 1. The fresh `.board-hero` element was captured as `evidence/board-identity-board-header.png`, and `evidence/board-identity-comparison.png` was rebuilt from that fresh header and the unchanged Direction A source reference. The reference remains 440 × 143 px; the fresh implementation header is 836 × 188 px and is normalized to 440 × 99 px in the comparison.

The regenerated desktop, mobile, focused-header, and comparison artifacts were opened alongside `evidence/board-identity-direction-a-reference.png` and `evidence/thread-identity-desktop.png`. The entire `#the-archive` heading is visibly parchment in each fresh board artifact. The exact browser computed values for both the heading and its hash child are `rgb(250, 246, 236)`; the board background remains `rgb(46, 74, 58)`. No actionable P0, P1, or P2 difference remains. The canonical thread capture still shows the intended parchment reading surface and is unchanged by this board-only treatment.

The approved board and thread hashes, including exact trailing-slash forms, render their respective prototype screens. Unknown `/c/*` and `/t/*` hashes now return the existing Forum Index fallback rather than displaying hard-coded content. At mobile width, action order remains Following then New topic, no horizontal overflow is present, the Follow control toggles and announces its discovery-feed notice, and focus remains visibly outlined. The fresh browser console reported 0 errors and 0 warnings. No favicon change was made.

## Design-system fidelity

- Typography: self-hosted Cormorant Garamond, Marcellus, EB Garamond, and JetBrains Mono are used in their source roles.
- Palette: parchment page/raised/sunken surfaces, hairline borders, evergreen actions, and restrained gold indicators use the Imladris tokens.
- Shape and density: list rows remain ruled rather than card-heavy; cards use the source's small radii and warm, low-contrast shadows.
- Assets: the supplied Elven star and commend star are used; interface icons come from one consistent line-icon library.
- Responsive behavior: the rail becomes an inert off-canvas drawer at 840px and below, content reflows without horizontal clipping, and thread tools become a mobile sheet.
- Product distinction: navigation explicitly labels Forum Index as “Browse boards,” Forum Inbox as “Your personal queue,” and Messages as “Private conversations.”

## Interaction and verification evidence

Browser checks covered the Forum Index → Board → Thread path; Follow, New Topic, Topic Tools, poll, reply, post-source, skip-link, mobile navigation, and focus-loop states. Two consecutive replies produced unique `post-453` and `post-454` IDs.

For the board-identity pass, the desktop computed style was `rgb(46, 74, 58)` background, `rgb(250, 246, 236)` color, and `3px solid rgb(194, 154, 68)` border bottom. Follow toggled with its discovery-feed notice; New topic opened and closed; partial submission produced the invalid-submit notice; a complete submission produced the prototype-success notice; and the exemplar topic link opened the canonical thread. At mobile width, both actions remained in order and the focused Following action remained visible with a 3 px outline. The exact-head recapture is recorded in Iteration 4.

- `npm run build`: passed; 4,575 modules transformed and the Sites bundle prepared.
- `npm run test:sites`: passed; 4 tests, 0 failures.
- `git diff --check`: passed. Its CRLF notices refer only to pre-existing dirty Imladris handoff files outside this prototype.

final result: passed
