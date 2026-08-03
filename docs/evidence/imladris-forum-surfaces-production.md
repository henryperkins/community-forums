# Imladris production forum surfaces

- Branch: `feature/imladris-forum-surfaces-production`
- Test date: 2026-08-03
- Implementation commit tested before this verification/evidence commit: `7c7bf13` (`fix: repair forum surface evidence defects`)
- Starting Task 5 commit: `91954cecb83f919ff0dbef7e13e254ebd4c17489`
- Source design: [`docs/superpowers/specs/2026-08-02-imladris-forum-surfaces-production-design.md`](../superpowers/specs/2026-08-02-imladris-forum-surfaces-production-design.md)
- Execution plan: [`docs/superpowers/plans/2026-08-02-imladris-forum-surfaces-production.md`](../superpowers/plans/2026-08-02-imladris-forum-surfaces-production.md)
- Approved references: `docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/`

## Measured browser result

The focused suite was prepared with `bash prepare.sh` and run in the approved native Windows Chrome with:

```powershell
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts
```

Latest evidence capture result: **8 passed, 2 intentionally skipped**. The two skips are the desktop-project-owned 800px shell-transition checks repeated under the mobile project. The run recorded no console warnings/errors, page errors, or HTTP responses with status 400 or higher.

The final combined native-Chrome gate ran this focused suite together with `composer-shell.spec.ts` and `thread-view-study.spec.ts`: **48 passed, 20 intentionally skipped, 68 total** in 176.2 seconds. The composer suite's JS-enabled board helper now uses the visible promoted opener; the separate no-JavaScript case continues to prove that the native summary works without enhancement.

| Route / surface | Desktop 1266×854 CSS px, DSF 1 | Mobile 390×844 CSS px, DSF 2 | Theme/state checks |
| --- | --- | --- | --- |
| `/` forum index | [light](imladris-forum-surfaces-production/desktop/forum-index-light.png), [dark](imladris-forum-surfaces-production/desktop/forum-index-dark.png) | [light](imladris-forum-surfaces-production/mobile/forum-index-light.png), [dark](imladris-forum-surfaces-production/mobile/forum-index-dark.png) | Forum hero and personal cross-board-queue copy visible; board identity absent; no horizontal overflow; Axe serious/critical count 0. |
| `/c/general` board | [light](imladris-forum-surfaces-production/desktop/board-light.png), [dark](imladris-forum-surfaces-production/desktop/board-dark.png) | [light](imladris-forum-surfaces-production/mobile/board-light.png), [dark](imladris-forum-surfaces-production/mobile/board-dark.png) | Identity eyebrow, `#General`, description, three facts, Follow board, and promoted New topic are visible in both themes; no horizontal overflow; Axe serious/critical count 0. |
| Canonical “Share your favourite keyboard shortcuts” thread | [light](imladris-forum-surfaces-production/desktop/thread-light.png), [dark](imladris-forum-surfaces-production/desktop/thread-dark.png) | [light](imladris-forum-surfaces-production/mobile/thread-light.png), [dark](imladris-forum-surfaces-production/mobile/thread-dark.png) | Thread Study root visible; board identity absent; participant list has valid list semantics; no horizontal overflow; Axe serious/critical count 0. |

Full-page output dimensions are 1266×855 physical px for the desktop captures. Mobile full-page outputs are 780×1710 for the index, 780×1774 for the board, and 780×1688 for the thread. The mobile reference files are 390×844 physical px; the production captures use the configured device scale factor 2 and are normalized to 390 CSS px width in the comparison sheets.

The board identity computed CSS is exact in the browser: background `rgb(46, 74, 58)`, foreground `rgb(250, 246, 236)`, bottom rule `rgb(194, 154, 68)`, and bottom-rule width `3px`.

Keyboard evidence used Tab focus on the promoted New topic button, Enter activation, title-input focus, Escape dismissal, `aria-expanded` true/false, and focus restoration to the actual opener. With JavaScript disabled, the hidden promoted trigger was absent, `details.composer-details > summary` opened the single real `form[action="/threads"]`, and the canonical thread link remained usable. The focused browser checks also cover the 800px board/index shell transition and the 390px mobile viewport without horizontal overflow.

## Visual comparison

- Desktop: [forum index](imladris-forum-surfaces-production/comparisons/forum-index.png), [board](imladris-forum-surfaces-production/comparisons/board.png), [thread](imladris-forum-surfaces-production/comparisons/thread.png)
- Mobile: [forum index](imladris-forum-surfaces-production/comparisons/forum-index-mobile.png), [board](imladris-forum-surfaces-production/comparisons/board-mobile.png), [thread](imladris-forum-surfaces-production/comparisons/thread-mobile.png)

All six comparison sheets and all six mobile production screenshots were opened after the latest native-Chrome capture. Typography hierarchy, route structure, spacing rhythm, band containment, responsive wrapping, target sizing, contrast, icons, and image sharpness were inspected. Production differs from the approved prototype in fixture names/counts/content and in shared shell controls already owned by the application; those differences are intentional and do not change the approved surface hierarchy.

The first mobile dark-board capture exposed a Chrome raster artifact that omitted most identity-band content even though the DOM was visible. The focused spec now sets reduced motion before theme changes, performs a disposable animations-disabled settling raster, and reasserts the identity content before the saved capture. The latest full desktop/mobile run and visual reinspection show the complete eyebrow, title, description, facts, Follow board, and New topic controls without the earlier strip/artifact.

The `/inbox` body is outside this production-surface slice; only its existing navigation route/markup is preserved here.

## Final verification

- Full PHPUnit, with the documented deterministic test-only `APP_KEY` and a fresh test schema: **2,416 tests, 17,159 assertions, 2 skipped**, exit 0.
- Imladris runtime verification: **11 tests, 66 assertions**, exit 0.
- WYSIWYG generated-asset check: current, exit 0.
- Native Chrome combined forum/composer/Thread Study gate: **48 passed, 20 intentionally skipped**, exit 0.
- Application-surface digest: `9c35a46cdb8381644c043e50eacefc8fd83d49242b942ede80a4edc8a49dd43f`. Only the baseline hash and its matching generated-manifest metadata changed; generated Imladris CSS, fonts, tokens, and licenses retained identical content.
- `git diff --check`: clean.

The Thread Study suite refreshed its existing desktop/mobile evidence images after the Forum index label and participant-list semantics changed; those browser-rendered artifacts were opened and inspected with no remaining visual defect.
