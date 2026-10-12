# Board index — adaptation, hardening, clarification and optimization

Verified locally on 2026-10-12 against base commit `9e6e45b5` plus the accompanying uncommitted changes. Local delivery asset version: `906231110793ea6f`. [Source identity and measured results](summary.json).

The directory now wraps valid long board names, constrains its grid at enlarged text sizes, and gives order, Peek, board and topic-preview controls a 44px touch floor. The enhanced Viewing sheet contains keyboard focus, makes the background inert, supports Escape and backdrop dismissal, restores focus on closure and viewport changes, and reserves a separate close-header band above its scrolling choices. The native no-JavaScript view remains an inline disclosure with working links/forms.

The Solved explanation now describes settlement-count ordering; the ranking remains count first with recency as its tie-break. Directory panes opt out of composer and passkey assets. Editor, topic, login and security surfaces retain their scripts.

## Completed verification

- `composer test`: 3,232 tests, 25,626 assertions, one reported skip; no failures.
- Playwright: 15 Chromium checks and 7 WebKit checks. Each engine covered 28 responsive cases across 320, 390, 760, 861, 1024 and 1440px, light/twilight themes, a readable 68-character name, an unbroken 80-character name, CJK text, and simulated 200% Large text. Measured horizontal overflow was 0px; measured controls met the 44px floor.
- Sheet regressions cover guest/member keyboard containment, immediate Enter/Tab, Escape, backdrop dismissal, focus handoff across the 760px boundary, and canonical sorting. Native guest/member sorting and remembered member order work with JavaScript disabled. Short landscape and enlarged-text checks confirm focused choices are uncovered.
- Eight settled axe WCAG A/AA scans of the guest/member sheet in both themes across both engines reported zero violations.
- Asset delivery tests: 35 checks passed. `npm run check:assets`, `composer check:imladris`, JavaScript/PHP syntax checks and `git diff --check` passed.
- The authored Imladris compatibility transfer remains byte-for-byte unchanged. Application adaptations sit outside it; runtime baseline/manifest and delivery assets were refreshed.
- Detector review found no findings in changed JS/templates or the appended CSS. The unchanged global stylesheet retains its existing warnings and advisories.

## Measured payload

The guest index JavaScript decreased from 146,015 to 73,528 decoded bytes: **72,487 fewer bytes**. This is an uncompressed payload comparison, with the sheet enhancement included. It establishes a payload reduction rather than a production load-time claim. [Chromium resources](chromium/script-payload.json) · [WebKit resources](webkit/script-payload.json).

## Evidence

- [Long board name, phone/day](chromium/directory-390-light.png)
- [Desktop/twilight](chromium/directory-1440-dark.png)
- [Landscape sheet](chromium/sheet-landscape.png)
- [Enlarged-text sheet](webkit/sheet-large-200.png)
- [Native member view](webkit/native-member-view.png)
- [Chromium geometry](chromium/geometry.json) · [WebKit geometry](webkit/geometry.json)
- [Solved explanation](solved-copy.json)

The browser profile uses the real PHP app, current generated assets, isolated local databases and deterministic seed data. No live provider calls were used. Viewports and text enlargement are emulated; physical iPhone/Android, native browser zoom and live production behavior remain unverified.

## Reproduction

Prepare a fresh dedicated local browser database using the repository's migrations and `tests/browser/seed.php`, then run from `tests/browser/` with `DB_DATABASE`, `E2E_PORT`, `E2E_BASE_URL`, `RATELIMIT_PATH`, `PACKAGES_STORAGE_PATH` and `RB_EVIDENCE_DIR` pointing at that isolated profile:

```sh
npx playwright test board-index-hardening.spec.ts board-index-remediation.spec.ts --project=desktop
E2E_LAYOUT_BROWSER=webkit npx playwright test board-index-hardening.spec.ts --project=desktop
```

The completed run used `retroboards_board_index_fix_e2e_20261012`, ports 18012/18013, and independent rate-limit stores. PHPUnit used `DB_TEST_DATABASE=retroboards_board_index_fix_unit_20261012`. Temporary databases and the inspection server were removed after verification.

The first inspection caught and corrected an enlarged-text grid constraint. The initial WebKit batch also encountered a navigation error; the completed batches above use explicit loopback origins and passed without retries. An earlier full PHP run exposed two directory-as-proxy asset assertions and a broad selector guard. Asset tests now use their actual consuming surfaces; the existing stylesheet guard is unchanged. These partial runs are separate from the final completed evidence.
