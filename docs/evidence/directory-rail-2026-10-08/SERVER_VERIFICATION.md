# Server, assets and developer delivery verification

Date: 2026-10-08. Base revision: `573f905d71f3c146072cffd5fecb2d2ffaca995b`.
The directory-navigation change and earlier Inbox work remain uncommitted.

## Server behavior

- Stable final full suite: **3,175 tests, 25,179 assertions, zero failures, one skip** (`phpunit-repair-final.log`). The preceding stable full run is retained in `phpunit-full.log`.
- Focused directory/member-shell/create-menu tests: **30 tests, 786 assertions,
  zero failures** in each of emulated and native PDO prepare modes
  (`phpunit-focused-default.log`, `phpunit-focused-native.log`). These cover
  normalized panes, independent Tags/Community gates, guest behavior, rail
  current states, legacy Notifications entry, profile Connections independence,
  heading ownership and the composer board picker.
- Imladris verification: **24 tests, 305 assertions**, passing
  (`imladris-verify.log`, `imladris-verify-repair.log`). The final rebuilt baseline
  also passes `imladris-check-repair-final.log`; the final full suite covers the
  runtime and fidelity tests. Asset build/check, PHP/JS syntax and `git diff --check`
  also pass.
- The first full-suite invocation overlapped the source-baseline rebuild. Its
  sole failure was the stale Imladris runtime baseline, preserved in
  `phpunit-initial-overlap.log`; the full suite above ran after the build settled.
- The extreme-text repair initially placed Connections wrapping inside the
  protected generated compatibility bridge. The full suite caught that source
  ownership failure (`phpunit-repair-bridge-failure.log`). The declaration now
  lives after the bridge in application overrides, and the final full suite
  above passes. This relocation preserves the browser-confirmed behavior.
- The three updated browser specs collected **76 tests** successfully. This is
  collection evidence only; it does not claim their complete execution. The
  first collection attempt omitted required `RB_EVIDENCE_DIR`; both attempts
  are retained in `spec-collection-initial.log` and `spec-collection-final.log`.
- The initial and final mechanical layout scans returned an empty findings
  array. The rendered browser checks provide the separate interaction and
  responsive evidence; an empty detector result alone is not a browser pass.

## Built delivery

- Final asset build: `a8b813712a571dff`. Earlier build receipts remain historical.
- CSS: `/assets/dist/app-style-DcXze5GH.css`, SHA-256
  `afec5a84f50297f8b8d296996b3f4a9480a8f9e0ecd1b3bcaee21b3055c8a115`.
- JS: `/assets/dist/app-ade982dee46260d0.js`, SHA-256
  `ade982dee46260d0006e71a6f6275c4f3016374c61eef8801a7392e82e9ead91`.
- Application baseline digest:
  `13646909b596deaf763ec7530669ca609e36fb39d5d61f16e6240a4d61375e55`.

At 2026-10-08T02:29:48.238636+00:00 on the final build, both the existing local server on port 8000 and the
[developer Connections URL](https://obituaries-lucky-accordance-king.trycloudflare.com/?pane=connections)
returned HTTP 200. `/healthz` reported application/database `ok`. Their served
CSS/JS hashes matched the current delivery, all three guest Explore links were
present, and the old horizontal pane strip was absent. The anonymous
Connections page retained its sign-in state; this network check does not claim
authenticated member-list verification. Exact results are in
`developer-delivery-repair.json`; the earlier delivery receipt is retained in
`developer-delivery.json`. This is developer-preview evidence, not a production
deployment.

## Root visual confirmation

The root reviewer inspected final WebKit 393px Connections closed/open-drawer
captures and Chromium 1440px Connections. The three directory links lead the
rail, Connections has the current marker, the old strip is absent, and the
heading/linked identity share the creation row without overlap. The independent
browser report records the broader interaction matrix and its limits.

Current-build root inspection also covers WebKit 393px member Connections
closed/open and the 320px/200%-Large Connections capture: ordinary geometry is
retained, enlarged primary links wrap within the header, and the heading and
identity preserve the separate creation allocation.

The independent source re-review found no concrete regression in the new header
measurement. The minimum and measured offset are independent, the observer
tracks content growth/shrink, and desktop resizing clears the phone override.
CSSOM writes follow the existing external-script CSP enhancement pattern.
