# Imladris production forum surfaces

- Branch: `codex/board-topic-density-remediation`
- Test date: 2026-08-03
- Implementation commit tested before this verification/evidence commit: `4344341` (`fix: restore board topic scan density`)
- Source design: [`docs/superpowers/specs/2026-08-03-board-topic-density-remediation-design.md`](../superpowers/specs/2026-08-03-board-topic-density-remediation-design.md)
- Approved references: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/`

## Measured browser result

The focused suite was prepared with `bash prepare.sh` and run in the approved native Windows Chrome with:

```powershell
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts
```

Latest remediation capture result: **8 passed, 2 intentionally skipped**. The two skips are the desktop-project-owned 800px shell-transition checks repeated under the mobile project. The run recorded no console warnings/errors, page errors, or HTTP responses with status 400 or higher. The exact seeded desktop Board row for “Share your favourite keyboard shortcuts” measured **64 CSS px**, inside the approved 60–72 px density contract.

The final combined native-Chrome gate ran this focused suite together with `composer-shell.spec.ts` and `thread-view-study.spec.ts`: **48 passed, 20 intentionally skipped, 68 total** in 176.2 seconds. The composer suite's JS-enabled board helper now uses the visible promoted opener; the separate no-JavaScript case continues to prove that the native summary works without enhancement.

| Route / surface | Desktop 1266×854 CSS px, DSF 1 | Mobile 390×844 CSS px, DSF 2 | Theme/state checks |
| --- | --- | --- | --- |
| `/inbox` Community Inbox | [light](imladris-forum-surfaces-production/desktop/inbox-light.png), [dark](imladris-forum-surfaces-production/desktop/inbox-dark.png) | [light](imladris-forum-surfaces-production/mobile/inbox-light.png), [dark](imladris-forum-surfaces-production/mobile/inbox-dark.png) | Signed-in queue retains its filters and selected-topic reading-pane contract: the desktop empty reading pane is visible, while the mobile default pane is hidden. Inbox is captured for paired density inspection only. |
| `/` forum index | [light](imladris-forum-surfaces-production/desktop/forum-index-light.png), [dark](imladris-forum-surfaces-production/desktop/forum-index-dark.png) | [light](imladris-forum-surfaces-production/mobile/forum-index-light.png), [dark](imladris-forum-surfaces-production/mobile/forum-index-dark.png) | Forum hero and personal cross-board-queue copy visible; board identity absent; no horizontal overflow; Axe serious/critical count 0. |
| `/c/general` board | [light](imladris-forum-surfaces-production/desktop/board-light.png), [dark](imladris-forum-surfaces-production/desktop/board-dark.png) | [light](imladris-forum-surfaces-production/mobile/board-light.png), [dark](imladris-forum-surfaces-production/mobile/board-dark.png) | Identity eyebrow, `#General`, description, three facts, Follow board, and promoted New topic are visible in both themes; no horizontal overflow; Axe serious/critical count 0. |
| Canonical “Share your favourite keyboard shortcuts” thread | [light](imladris-forum-surfaces-production/desktop/thread-light.png), [dark](imladris-forum-surfaces-production/desktop/thread-dark.png) | [light](imladris-forum-surfaces-production/mobile/thread-light.png), [dark](imladris-forum-surfaces-production/mobile/thread-dark.png) | Thread Study root visible; board identity absent; participant list has valid list semantics; no horizontal overflow; Axe serious/critical count 0. |

Full-page output dimensions are 1266×855 physical px for the desktop captures. Mobile full-page outputs are 780×1710 for the index, 780×1774 for the board, and 780×1688 for the thread. The mobile reference files are 390×844 physical px; the production captures use the configured device scale factor 2 and are normalized to 390 CSS px width in the comparison sheets.

The board identity computed CSS is exact in the browser: background `rgb(46, 74, 58)`, foreground `rgb(250, 246, 236)`, bottom rule `rgb(194, 154, 68)`, and bottom-rule width `3px`.

Keyboard evidence used Tab focus on the promoted New topic button, Enter activation, title-input focus, Escape dismissal, `aria-expanded` true/false, and focus restoration to the actual opener. With JavaScript disabled, the hidden promoted trigger was absent, `details.composer-details > summary` opened the single real `form[action="/threads"]`, and the canonical thread link remained usable. The focused browser checks also cover the 800px board/index shell transition and the 390px mobile viewport without horizontal overflow.

## Visual comparison

- Paired density inspection: desktop [Inbox light](imladris-forum-surfaces-production/desktop/inbox-light.png), [Inbox dark](imladris-forum-surfaces-production/desktop/inbox-dark.png), [Board light](imladris-forum-surfaces-production/desktop/board-light.png), and [Board dark](imladris-forum-surfaces-production/desktop/board-dark.png); mobile [Inbox light](imladris-forum-surfaces-production/mobile/inbox-light.png), [Inbox dark](imladris-forum-surfaces-production/mobile/inbox-dark.png), [Board light](imladris-forum-surfaces-production/mobile/board-light.png), and [Board dark](imladris-forum-surfaces-production/mobile/board-dark.png).
- Desktop: [forum index](imladris-forum-surfaces-production/comparisons/forum-index.png), [board](imladris-forum-surfaces-production/comparisons/board.png), [thread](imladris-forum-surfaces-production/comparisons/thread.png)
- Mobile: [forum index](imladris-forum-surfaces-production/comparisons/forum-index-mobile.png), [board](imladris-forum-surfaces-production/comparisons/board-mobile.png), [thread](imladris-forum-surfaces-production/comparisons/thread-mobile.png)

All six comparison sheets and all six mobile production screenshots were opened after the latest native-Chrome capture. Typography hierarchy, route structure, spacing rhythm, band containment, responsive wrapping, target sizing, contrast, icons, and image sharpness were inspected. Production differs from the approved prototype in fixture names/counts/content and in shared shell controls already owned by the application; those differences are intentional and do not change the approved surface hierarchy.

The first mobile dark-board capture exposed a Chrome raster artifact that omitted most identity-band content even though the DOM was visible. The focused spec now sets reduced motion before theme changes, performs a disposable animations-disabled settling raster, and reasserts the identity content before the saved capture. The latest full desktop/mobile run and visual reinspection show the complete eyebrow, title, description, facts, Follow board, and New topic controls without the earlier strip/artifact.

The Inbox body remains behaviorally unchanged. This remediation captures `/inbox` beside `/c/general` from the same signed-in fixture to inspect the density relationship directly; it does not compare Inbox to a Board prototype or change Inbox implementation.

The paired desktop/mobile inspection confirms that Board retains the dark-green identity band, compact ruled topic rows, and visible metadata/activity while exposing materially more scan density than the prior tall rows. Inbox retains its distinct queue/list treatment and its desktop empty-pane versus mobile hidden-pane default, so it does not read as a Board.

## Final verification

- Full PHPUnit, with the documented deterministic test-only `APP_KEY` and a fresh test schema: **2,416 tests, 17,159 assertions, 2 skipped**, exit 0.
- Imladris runtime verification: **11 tests, 66 assertions**, exit 0.
- WYSIWYG generated-asset check: current, exit 0.
- The reviewed application-surface baseline now records the approved Board CSS digest `89f210a80401973d214101f5e676d75ba99fe951d1e570e18f65703e90a33192`; the canonical runtime builder regenerated its matching manifest metadata.
- Focused native-Chrome Board/Inbox visual contract: **8 passed, 2 intentionally skipped**; exact desktop seeded Board row: **64 CSS px**.
- Inbox behavior suite (filters, selected-topic reading pane, mobile Back, no-JavaScript fallback, and canonical navigation): **18 passed, 10 intentionally skipped**, exit 0.
- Native Chrome combined forum/composer/Thread Study gate: **48 passed, 20 intentionally skipped**, exit 0.
- Application-surface digest: `89f210a80401973d214101f5e676d75ba99fe951d1e570e18f65703e90a33192`. Only the baseline hash and its matching generated-manifest metadata changed; generated Imladris CSS, fonts, tokens, and licenses retained identical content.
- `git diff --check`: clean.

The Thread Study suite refreshed its existing desktop/mobile evidence images after the Forum index label and participant-list semantics changed; those browser-rendered artifacts were opened and inspected with no remaining visual defect.
