# Design QA: Imladris production forum surfaces

**Source visual truth**

`docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/`

Profile source: `docs/design-system/imladris/templates/user-profile/UserProfile.dc.html` plus `docs/design-system/imladris/imladris-spec.md`.

**Rendered implementation**

`docs/evidence/imladris-forum-surfaces-production/`

Profile evidence: `docs/evidence/imladris-profile-production/`.

**Viewport and normalization**

- Desktop references: 1266×854 px. Browser viewport: 1266×854 CSS px at device scale factor 1. Full-page production captures: 1266×855 px.
- Mobile references: 390×844 px. Browser viewport: 390×844 CSS px at device scale factor 2. Full-page production captures: 780×1710 index, 780×1774 board, and 780×1688 thread. Comparison sheets render both sides at 390 CSS px width so the production `@2x` captures are density-normalized.
- Compared states: signed-in `/`, `/c/general`, and the canonical “Share your favourite keyboard shortcuts” thread in light and dark themes; comparison sheets use the approved light references.

**Full-view and focused evidence**

- Full-route sheets: [desktop index](docs/evidence/imladris-forum-surfaces-production/comparisons/forum-index.png), [desktop board](docs/evidence/imladris-forum-surfaces-production/comparisons/board.png), [desktop thread](docs/evidence/imladris-forum-surfaces-production/comparisons/thread.png), [mobile index](docs/evidence/imladris-forum-surfaces-production/comparisons/forum-index-mobile.png), [mobile board](docs/evidence/imladris-forum-surfaces-production/comparisons/board-mobile.png), and [mobile thread](docs/evidence/imladris-forum-surfaces-production/comparisons/thread-mobile.png).
- Focused review used the standalone light/dark production captures, especially the board identity band and mobile thread header/poll/composer. No extra crop was needed because these regions are legible at original resolution in the full-page artifacts.
- Fonts/typography: display serif, body serif, label capitals, monospace metadata, hierarchy, weights, wrapping, and line height follow the approved visual language.
- Spacing/layout: route shell, content widths, identity-band containment, vertical rhythm, mobile stacking, target sizes, and absence of horizontal overflow were checked.
- Colors/tokens: browser assertions measured the identity background `rgb(46, 74, 58)`, foreground `rgb(250, 246, 236)`, and 3px `rgb(194, 154, 68)` rule.
- Image/asset fidelity: the existing application icon partial and font assets remain sharp; no reference image was edited and no fabricated visible asset was introduced.
- Copy/content: route labels and surface-purpose copy are preserved. Fixture names/counts differ intentionally from the prototype. `/inbox` body content is out of scope.

**Interaction and accessibility evidence**

Native Windows Chrome covered promoted-composer keyboard open/focus/Escape/focus-return behavior, the no-JavaScript summary/form path, canonical-thread navigation, serious/critical Axe checks, console/page/HTTP error capture, exact computed band CSS, and overflow at 390px and the 800px shell transition.

**Comparison history**

- P2 mobile/index shell overflow: route-scoped 860px margin correction; post-fix 390px and 800px browser checks passed.
- P2 no-JavaScript duplicate promoted trigger: route-scoped `[hidden]` rule; post-fix the trigger is absent and the native summary/form path is usable.
- P2 thread participant semantics: participant container/items changed to `ul`/`li`; post-fix PHP semantics and Axe serious/critical checks passed.
- P2 mobile dark-board evidence artifact: the first full capture omitted the identity copy and New topic control despite visible DOM. Capture synchronization now disables transitions before theme mutation, settles one disposable raster, and reasserts all identity content; the recaptured artifact and subsequent full-suite capture were visually inspected and contain the complete band.

**Profile completion addendum**

- Viewports: desktop 1160×900 and mobile 390×844; full-page captures preserve natural document height. Guest populated, guest gated, guest empty, signed-in member, self Connections, and moderator-context states were captured in light and dark themes.
- Source/production comparisons: `docs/evidence/imladris-profile-production/comparisons/desktop-light.png`, `desktop-dark.png`, `mobile-light.png`, and `mobile-dark.png`. Each sheet was inspected with both images together at original resolution.
- Approved source conflict: the standalone HTML's light cover uses parchment semantic tokens, while the written Imladris contract specifies a twilight profile cover and the approved production direction retained it. Production therefore keeps the twilight cover in both themes; this is documented in the profile evidence ledger.
- Scope vocabulary: Regard and moderator/member-record language remain authoritative. Source-only profile Report, gated Message, and Request access actions were not introduced.
- P2 interaction/accessibility fixes found during browser QA: Copy link was unreachable outside Thread Study because its progressive-enhancement handler followed a thread-only guard; the handler is now page-generic. The presence dot now has valid image semantics. The native action disclosure no longer claims an invalid ARIA menu structure.
- P2 mobile tab clipping: the final Connections label initially extended 5.14px past the 390px viewport. Small-screen padding was tightened, a bounding-box assertion was added, and regenerated light/dark comparisons show all five tabs.
- Profile Playwright result: 6 passed across desktop/mobile. The suite covers keyboard focus, Clipboard enhancement with safe-link fallback, no-JavaScript navigation/forms, search, sorting, paging, stable legacy connection routes, Remove follower, overflow, console/page/HTTP errors, and serious/critical Axe findings.
- Focused profile/privacy PHP result: 55 tests, 331 assertions.

**Findings**

No actionable P0/P1/P2 visual finding remains in the inspected forum or profile evidence. Intentional fixture-data, shared-shell, and approved cover differences are documented in their evidence reports.

**Verification summary**

- Full PHPUnit: 2,431 tests, 17,317 assertions, 2 skipped.
- Imladris runtime: 11 tests, 66 assertions.
- Profile Chromium suite: 6 passed across desktop/mobile, including per-state Axe and no-JavaScript coverage.
- Existing profile-media/custom-field browser compatibility: 4 Gate A cases and 4 Axe cases passed across desktop/mobile.
- Combined native-Chrome forum/composer/Thread Study gate: 48 passed, 20 intentionally skipped.
- Additive upgrade rehearsal through migration 0078: 17/17 checks passed.
- WYSIWYG generated assets: deterministic rebuild current.
- Console warnings/errors, page errors, and HTTP responses with status 400 or higher: none in the focused surface run.
- Final application-surface digest: `70c29b65f98373fb6bcfa7f268d9e9147460c62b5d0b01fd13ed9f770ee9c66a`.

**Final result**

final result: passed
