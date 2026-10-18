# Inbox accessibility and polish

Local fixes for the three P1 findings from the `/inbox` audit. Base commit:
`fbaed0e9e040dbed4889f776c9463be28bc261ad`. The changes are uncommitted; this evidence establishes
local behavior. No publication or production verification was requested.

The owner subsequently requested publication. The [release integration](integration/README.md)
records the combined tree after preserving the newer Boards release on main;
the original local measurements below remain tied to their recorded base.

| Finding | Result |
| --- | --- |
| System dark used a low-contrast board-reference link (3.07:1) | Both dark token blocks explicitly use Imladris river ink. Measured System contrast is 10.82:1 in Chromium and 10.82:1 in WebKit; System and explicit Dark also pass the scoped axe checks. |
| Character shortcuts acted while member chrome had focus | Topic links and intentionally focused preview/navigation surfaces own the keys. Other buttons, content links, menus, text fields and chrome retain theirs. Actions resolve the focused topic rather than an older cursor. |
| Enlarged text collided in the toolbar and overflowed the queue | Root-font-relative compact steps, bounded gutters, a reserved heading column and wrapping metadata retain one toolbar row, named choices and 44px controls. |

Polish also hides inactive keyboard instructions without JavaScript and explains
where shortcuts work. Consecutive navigation retains heading focus; loading
respects focus moved to another control. A retained Unread preview whose row
has disappeared cannot submit another topic's actions. It still supports
`j`/`k` navigation.

The focused-component boundary follows the
[W3C character-shortcut guidance](https://www.w3.org/WAI/WCAG22/Understanding/character-key-shortcuts.html).
It does not establish a complete accessibility conformance claim.

| Verification | Result |
| --- | --- |
| Chromium 155.0.8059.12 | 58 main Inbox checks + 4 keyboard compatibility checks passed; 6 intentional mobile skips; no failed or flaky cases. |
| WebKit 26.5 | 58 main Inbox checks + 4 keyboard compatibility checks passed; 6 intentional mobile skips; no failed or flaky cases. |
| Text layout | 320 measured states: both engines, desktop and emulated-mobile contexts, For You/Needs Answer, 320/390/861/1280/1440px, both densities and 16/18/32/36px root text. No horizontal overflow, clipped controls, heading collisions or sub-44px command targets. |
| Full PHP suite | 3,233 tests, 25,651 assertions, one skipped; exit 0. |
| Imladris verification | Generated assets current; 24 tests and 305 assertions passed. |
| Asset build and syntax | `node bin/build-assets.mjs --check`, PHP template lint, JavaScript syntax and `git diff --check` passed. CSS/JS delivered by the local server match the manifest hashes. |
| Source review | Independent review found no remaining actionable source findings after the shortcut/focus corrections. |

The six mobile browser skips per engine cover desktop pane-preference controls
and the generic submit contract exercised on desktop. The PHP suite skips the
dedicated database-only migration down/up rehearsal. The Impeccable scan of the
three shared UI files returned 223 existing findings outside the changed lines;
none were added on changed lines, and no detector ignores were introduced.

The final browser runs used the shared member fixture and fresh private
databases. Initial red checks and the first WebKit layout pass used the rich
forum fixture. Combining those fixture families buried an older test's sample
topic, so the new regression suite now uses `member-surfaces-fixture.php`.
Two older compatibility assertions were aligned with the existing compact menu
and ADR 0045 recovery behavior before their final green runs. The first PHP run
was stopped after a local APP_URL/RP-ID mismatch; the full successful run used
matching localhost values and a fresh private test schema.
The clean render probe followed a reset of the owned temporary rate-limit store;
the accumulated browser-suite polling had produced 429 responses there.

Coverage is local Linux browser execution and mobile emulation. Root text was
enlarged independently of viewport width; physical devices, browser-chrome
zoom behavior and assistive technology were not exercised.

The main browser command selected these specs in each engine, with an external
local PHP server and a temporary configuration overriding both projects'
`browserName`:

```sh
playwright test --config /tmp/retroboards-inbox-fix-bnjcedoy/playwright.config.ts \
  inbox-accessibility-hardening.spec.ts inbox-controls-polish.spec.ts \
  inbox-header.spec.ts inbox-preview-recovery.spec.ts
```

The additional run selected `member-surfaces.spec.ts` and
`auth-hardening.spec.ts` with `--grep 'inbox menu, selection|Enter on a focused link'`.
Chromium used the installed cache executable `chromium-1247`; WebKit used the
runner's installed browser. The permanent Playwright configuration was not
changed. Private DBs and a temporary rate-limit/package store isolated the work.

- [All test outcomes and intentional skip reasons](browser-runs.json)
- [Source hashes, asset identity and verification receipt](verification.json)
- [Measured contrast and clean navigation/preview console checks](render-check.json)
- [PHP and Imladris output summaries](phpunit-summary.txt)
- [Scoped Impeccable disposition](impeccable-summary.json)
- [Chromium desktop](chromium/desktop/for_you-1440-16px.png),
  [System dark](chromium/desktop/system-dark.png),
  [320px with doubled text](chromium/mobile/needs_answer-320-32px.png)
- [WebKit desktop](webkit/desktop/for_you-1440-16px.png),
  [System dark](webkit/desktop/system-dark.png),
  [320px with doubled text](webkit/mobile/needs_answer-320-32px.png)

Per-engine/project folders retain the complete layout measurements and scoped
axe results; each engine also records the CSS/JS hashes served during its run.
The owned preview server is stopped, and both private databases and their
temporary grants were removed after verification.
