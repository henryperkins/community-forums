# Inbox hardening evidence — 2026-10-08

This records the original evidence pass and its build boundary. Later review fixes and final release checks are recorded in [Inbox preview recovery](../inbox-preview-recovery-2026-10-08/README.md).

The final correction build is **`e26e139ee9899a8f`**. One bounded confirmation passed **164/164 browser checks across 38 scenario executions** in Chromium 155.0.8059.12 and WebKit 26.6. These are focused browser assertions, not a complete browser regression-suite run. Application changes remain in the working tree.

## Final browser qualification

| Lane | Checks | Failures | Evidence |
|---|---:|---:|---|
| Chromium recovery, forms, rendering | 77 | 0 | `chromium-confirmation.json` |
| WebKit recovery, forms, rendering | 77 | 0 | `webkit-confirmation.json` |
| Chromium current-page drain | 5 | 0 | `chromium-page-drain-confirmation.json` |
| WebKit current-page drain | 5 | 0 | `webkit-page-drain-confirmation.json` |

`browser-results.json` records the aggregate and versions. Each browser fetched the delivered CSS and JavaScript and checked the actual SHA-256 before exercising the UI:

- JavaScript `/assets/dist/app-3bc29160425933ec.js`: `3bc29160425933ecf16bfc4ca8c791e0af8ad153dc96875bad9858026a078e88`
- CSS `/assets/dist/app-style-DRpY5gJ7.css`: `b4cc42697751c97248b49185d8a7d249249f1c18c1f3604b8b1de28d0efaa320`

The correction checks cover HTTP 500, 400 and 429; a rejected network request; browser offline mode; malformed HTML and a mismatched topic ID. All retain the queue, clear busy state, show specific recovery guidance where applicable, and retain a native full-topic link. Failed retry restores its own focus; a later retry loads the real server preview. Deliberate Open full topic navigation works. A previously typed reply remains in the same DOM node after another topic's preview fails.

The actual 15-second deadline was exercised with `AbortController` unavailable in a fresh context. Recovery appeared after 15.39 seconds in Chromium and 15.45 seconds in WebKit (including browser interaction overhead). A later successful response from the expired request was ignored, recovery remained intact, and retry succeeded. A delayed first request also lost to a newer preview; native snooze submission owned navigation and persisted the expected hiding state.

Reading the sole Unread topic shows the server-equivalent caught-up state, hides obsolete selection/list/footer controls and focuses the explanation on Back. A separate 21-topic fixture drains page 2's sole row while 20 unread topics remain elsewhere: it reports that the **page** was read, keeps the count at 20, focuses its explanation and provides Load remaining topics. That native link renders the remaining 20 topics without claiming the whole Inbox is caught up.

Real native POST validation returns 422 and keeps available checked rows. With enhancement enabled, its server-owned queue URL is normalized before preview history is added; preview stays on `/inbox` after that 422. With JavaScript disabled, the native POST response retains checked rows and usable recovery controls. Repeated bulk Star requests both return 303 and leave the topic starred.

The creation panel stays inside a 320×180 viewport with 200% root text sizing and scrolls. The mixed Arabic/Hebrew/CJK/emoji title stays within its row in the existing English shell. Forced-colors panels become immediately opaque and the switch thumb has a visible contrasting fill and border.

## Useful final captures

- `chromium-final-preview-recovery.png` / `webkit-final-preview-recovery.png`
- `chromium-final-caught-up.png` / `webkit-final-caught-up.png`
- `chromium-final-page-drained.png` / `webkit-final-page-drained.png`
- `chromium-final-bulk-validation.png` / `webkit-final-bulk-validation.png`
- `chromium-final-forced-colors.png` / `webkit-final-forced-colors.png`
- `chromium-final-short-creation.png` / `webkit-final-short-creation.png`
- `chromium-final-unicode.png` / `webkit-final-unicode.png`

## Reproduction and initial-pass provenance

The server ran on owned port **8484** using only `retroboards_e2e_inbox_harden_20261008`, isolated `/tmp/retroboards_e2e_harden_rate_20261008` and `/tmp/retroboards_e2e_harden_packages_20261008` stores, non-secure local session cookies and the deterministic browser fixture key. The shared developer server on port 8000 and its tunnel were left running. The guard in `fixture-reset.php` rejects other database names; `php fixture-reset.php drain` creates the current-page-drain variant. These resets require `DB_DATABASE=retroboards_e2e_inbox_harden_20261008`.

The Playwright CLI uses named sessions `harden-chromium` and `harden-webkit`, with `capture-confirmation.js` followed by `capture-page-drain-confirmation.js`. It creates temporary contexts for the no-JS and unavailable-AbortController lanes. Transport errors are deliberately injected; native validation, preview retry, canonical navigation and snooze persistence run through the real local PHP application. Raw CLI outputs remain under `/tmp/inbox-harden-cli-20261008`; the committed candidates here contain sanitized result summaries and screenshots.

`INITIAL_REVIEW.md` and the `*-initial*.json` reports retain the findings from initial build **`9cd60f8b2665c07a`**. The initial Chromium pass contains three harness failures, repaired only in `chromium-initial-affected.json`: a heading selector also matched the nested composer emoji heading, the initial mixed-script fixture was paginated off-screen, and a short-view wait assumed the combined disclosure stayed open. The corrected WebKit initial pass and the final confirmation remain distinct. Initial screenshots are old-build evidence and are not claims about the corrected UI.

## Limits

This run uses Linux Chromium/WebKit with desktop and phone dimensions, plus a 320px short viewport. It does not qualify physical iPhone/Safari, Firefox, assistive technology, or Windows high-contrast mode. Chromium applies a forced palette; WebKit advertises the query in this runner but keeps its normal palette, so its result qualifies the media-query rules only. Root font scaling exercises text expansion and is not a physical browser zoom test. Whole-document RTL was a generic exploratory probe that exposed broader shell overflow; it is not a shipped localization contract and this task verifies mixed-direction member content in the existing English shell. No whole-shell RTL implementation is claimed.

The initial inspection and one correction confirmation are complete. No additional cosmetic iteration followed the passing confirmation.

## Server, build and developer delivery qualification

| Check | Result | Evidence |
|---|---|---|
| Full PHPUnit suite, private `retroboards_ui_polish_full_20261008` | 3,175 tests / 24,995 assertions / 1 skip / no failures | `phpunit-full.log` |
| Focused hardening, snooze, member and Request checks, emulated prepares | 64 tests / 1,021 assertions / no failures | `phpunit-focused-default.log` |
| Same focused checks, native prepares | 64 tests / 1,021 assertions / no failures | `phpunit-focused-native.log` |
| Final hardening/member/Request confirmation | 20 tests / 410 assertions / no failures | `phpunit-final-focused.log` |
| Imladris generated/runtime contract | Passed; 24 tests / 305 assertions | `imladris-verify.log` |
| Delivery asset drift | Passed | `assets-check.log` |

The backend checks cover missing/malformed hiding and read intent, held-topic and
revoked-private-board privacy, suspended read eligibility, explicit repeated star
intent, retained legacy toggle clients, malformed JSON-format negotiation and
bulk recovery excluding stale, private and off-page selections. The full suite
started after the main correction batch; the final canonical-history adjustment
landed while it ran. The final focused and runtime checks plus the final browser
confirmation above qualify that adjustment. This is reconciled final evidence,
not a claim that every check ran uninterrupted after the last source edit.

The initial asset/runtime checks caught the expected stale output immediately
after that last adjustment. Assets were rebuilt, the reviewed presentation digest
was updated, and both checks then passed. `node --check public/assets/app.js`, PHP
syntax checks of changed backend paths and `git diff --check` also passed. The root
visually inspected the final recovery, bulk, forced-colors and page-drain captures.

`developer-delivery.json` records a live read-only check of local port 8000 and
`https://obituaries-lucky-accordance-king.trycloudflare.com`: both `/healthz`
responses were HTTP 200 with database `ok`, and both delivered the exact final
CSS/JavaScript hashes above. Anonymous `/inbox` follows the expected login
redirect and its shell references those assets; the authenticated Inbox behavior
is qualified on the isolated browser fixture, not an authenticated tunnel session.
The Git base remains `573f905d71f3c146072cffd5fecb2d2ffaca995b` with uncommitted
working-tree changes. This verifies the developer preview, not a production
deployment, commit or push.

Both owned hardening browser sessions and the private port 8484 server were closed
after confirmation. The existing port 8000 developer server, tunnel and previous
polish/motion evidence were preserved.
