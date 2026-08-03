# Design QA: Imladris production forum surfaces

**Source visual truth**

`docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/`

**Rendered implementation**

`docs/evidence/imladris-forum-surfaces-production/`

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

**Findings**

No actionable P0/P1/P2 visual finding remains in the inspected evidence. Intentional fixture-data and shared-shell differences are documented in the full evidence report.

**Verification summary**

- Full PHPUnit: 2,416 tests, 17,159 assertions, 2 skipped.
- Imladris runtime: 11 tests, 66 assertions.
- Combined native-Chrome forum/composer/Thread Study gate: 48 passed, 20 intentionally skipped.
- Console warnings/errors, page errors, and HTTP responses with status 400 or higher: none in the focused surface run.
- Final application-surface digest: `9c35a46cdb8381644c043e50eacefc8fd83d49242b942ede80a4edc8a49dd43f`.

**Final result**

final result: passed
