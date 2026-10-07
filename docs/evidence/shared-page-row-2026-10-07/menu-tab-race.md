# Messages menu Tab race verification

Verified on 2026-10-07 using installed Playwright Chromium and WebKit, authenticated `/messages`, a 1440 × 1000 desktop viewport, and the rebuilt local assets on an isolated private database.

The original race let Enter open the native disclosure before its asynchronous `toggle` handler positioned the panel. The panel remained hidden during a fast Tab, so native focus skipped the search input. The shared keydown handler now positions an open, unpositioned menu synchronously before native Tab navigation; it neither prevents Tab nor forces focus.

| Engine | Normal runs | Forced pending-toggle runs | Total |
| --- | --- | --- | --- |
| Chromium | 10/10 passed | 10/10 passed | 20/20 |
| WebKit | 10/10 passed | 10/10 passed | 20/20 |

Each run navigated to `/messages`, focused `.dm-search-menu > summary`, pressed Enter, asserted the disclosure was open, then immediately pressed Tab. Every run left `.dm-search input` focused and the menu open, positioned, and visible.

In the normal Chromium runs, five entered Tab with the panel still unpositioned. Normal WebKit runs had already received `toggle`. For the controlled pending-toggle runs, an in-memory capture listener intercepted the search menu's native `toggle` event before the application listener, keeping the panel hidden and unpositioned until Tab. All 20 controlled runs exercised the new synchronous path successfully. Production source was unchanged during verification.

Inspected `public/assets/app.js` SHA-256: `c2143dc6dfc3bf7236af11864d3ed35f5a480c11ff2f4284e4cf8ad9431daf92`.

Limits: this was a focused desktop keyboard check, not a full browser suite, mobile/touch check, or assistive-technology test. The controlled event interception tests the timing boundary; it does not reproduce every native scheduler ordering. No credentials, cookies, session state, or raw browser reports are included. The owned private server on port 8027 was stopped after verification.
