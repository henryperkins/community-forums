# Messages release verification — 2026-09-23

The reviewed `fix/messages-release` branch consolidates `messages-audit`, `ma-lay` and
`ma-distill` on main `703aeb600a47959555c403dea4022d1d4405f5c6`, and completes the
[poll/focus plan](../../../superpowers/plans/2026-09-21-messages-poll-focus-fixes.md).
Implementation reviewed through `b671083b5b31e4618521021d8f34d2b486995a6c`.

Reading position survives late fonts, dock resizing and incoming backlog pages.
An invalid recipient receives focus in the enhanced combobox without disturbing
body-error focus or closed dialogs. Polling returns 50 rows plus an internal
one-row probe, acknowledges only returned rows, and drains advancing pages with
at most 20 immediate follow-ups. Polls use `dm_poll = [120, 300]`; sending retains
`dm = [20, 600]`. A 429 deadline survives tab changes and an in-flight request.

The consolidated audit also repairs disclosure and modal keyboard behavior,
short-screen composition, touch targets, system-dark contrast, plain-text list
previews, group placeholders, and no-JavaScript reply landing. Empty poll ticks
remain transaction-free and perform no write when the read watermark is current.
There is no migration or hostname-route change.

## Verification

| Check | Result | Evidence |
| --- | --- | --- |
| Full PHPUnit | 3,009 tests, 22,725 assertions; exit 0 | [Log](phpunit.txt) |
| Native prepared statements, Messages integration | 26 tests, 175 assertions | [Log](phpunit-native-prepares.txt) |
| Imladris runtime and source contracts | 24 tests, 296 assertions | [Log](imladris.txt) |
| Worker/asset tests and generated asset check | 15 tests; generated files current | [Log](assets.txt) |
| Poll/focus regressions | 13 passed | [Log](poll-focus.txt) |
| Font/scroll event ordering, repeated four times | 8 passed | [Log](font-order-repeat.txt) |
| Messages audit matrix | 16 passed | [Log](audit-matrix.txt) |
| Messages refinement and real incoming HTTP/poll | 5 passed | [Log](refinement.txt) |
| Shared notification polling lifecycle | 3 passed | [Log](notifications.txt) |
| Group DMs, desktop with no JavaScript | 2 passed | [Log](group-desktop.txt) |
| Group DMs, mobile | 1 passed, 1 viewport skip | [Log](group-mobile.txt) |
| Shared composer and rich content, desktop | 8 passed, 10 viewport skips | [Log](shared-desktop.txt) |
| Shared composer and rich content, mobile | 17 passed, 1 viewport skip | [Log](shared-mobile.txt) |
| Combined composer/group/rich suites after harness repair | 10 desktop passes and 18 mobile passes; 12 viewport skips | [Desktop](combined-desktop.txt), [mobile](combined-mobile.txt) |

The full suite has six existing PHP 8.5 deprecations and one existing skip; the
clean main baseline had the same issues (2,979 tests, 22,482 assertions). These are
not counted as new failures or silently omitted. Browser skips are the specs'
explicit viewport conditions, not failed or unexecuted requirements.

Browser coverage uses Chromium with desktop 1440px and 1280px, touch emulation at
390 × 844, and a 640 × 400 zoom/short-screen viewport. It covers light, explicit
dark, system dark, keyboard Tab/Shift+Tab/Escape, focus restoration, rich and source
editing, axe checks, and JavaScript-disabled forms and navigation. This does not
claim physical-device, Safari, or Firefox coverage.

Each independent browser suite starts with `bash tests/browser/prepare.sh` against
an isolated database. Commands are run from `tests/browser` using
`npx playwright test <spec> --project=<desktop|mobile>`; the polling checks in
`notifications-unified.spec.ts` use `-g 'poll|throttl'` and their own fixture DB.
The other specs are `messages-poll-regressions.spec.ts`,
`messages-audit-regressions.spec.ts`, `messages-refinement.spec.ts`,
`group-dms.spec.ts`, and `composer-expansion.spec.ts rich-content.spec.ts`.
Routine PHP web-server connection lines and ANSI colors are removed from the
published logs; test names, results and diagnostics are preserved. No inherited
environment dump, cookie jar, session token, or private browser trace is included.

## Screenshots

- [Desktop system dark](../../dm-reimagine/phase5/conversation-1440-os-dark.png)
- [Touch composer](../../dm-reimagine/phase5/conversation-390-touch-expanded.png)
- [Keyboard compose dialog](../../dm-reimagine/phase5/compose-dialog-1440-day.png)
- [No-JavaScript phone reply landing](../../dm-reimagine/phase5/no-js-reply-landing-390-touch.png)
- [Incoming message while reading earlier history](refinement/live-new-messages.png)
- [No-JavaScript validation preserves a draft](refinement/phone-validation-no-js.png)

The new audit's phase5 screenshots are refreshed by this run. The 20 fresh
refinement screenshots are retained here; the older phase4 evidence is restored
byte-for-byte. Fresh cross-surface screenshots live under `cross/`, preserving
the previous `docs/evidence/browser/` captures.

## Review and implementation decisions

A fresh independent reviewer examined the complete `703aeb60..b671083b` diff,
specifications, tests and screenshots, and independently checked whitespace and
JavaScript syntax. Verdict: **ready to merge**, with no critical, important or
minor findings. There are no deferred reviewer findings.

- A font promise can resolve before the pending scroll event. The regression
  scrolls and resolves fonts in the same task; the fix checks the current offset
  against the last followed position while allowing layout clamping and growth.
- The burst limit means the initial request plus 20 immediate follow-ups, then
  the normal interval before page 22. This resolves the original plan's prose
  off-by-one in favor of its explicit follow-up count and algorithm.
- The overlong-body test submits the form directly to reach the real server 422:
  clicking the correctly disabled client Send button cannot exercise that path.
- The broader combined browser run exposed incompatible fixture assumptions:
  the composer suite enables rich editing, whereas the older group-DM journey
  drives source textareas. The group-DM test helper now selects Source through
  the real mode control when needed. The original failing combined command
  passes on both viewports after this test-only repair, including no-JavaScript
  behavior. It also passes against the fresh source-mode seed.

Existing product decisions remain recorded in their original sources: mobile
topbar Messages-link visibility ([ADR 0032](../../../adr/0032-unified-member-chrome.md)
and audit P2-7), the 1700px details-column threshold (surface brief §8), and
new-message/toast pill geometry (ADR 0034 addendum). Per-message report forms
remain a markup optimization opportunity. Optimistic sends, group read receipts
and non-polling transports remain explicit deferrals or anti-goals. These were
present on main and were not introduced by this release.

## Integration and cleanup

The original dirty Messages worktrees were snapshotted as patches and tar archives
before consolidation, under the local recovery directory
`/home/ubuntu/community-forums-archives/2026-09-23-messages/`. `ma-lay` is included
in the audit changes; `ma-distill` adds the consolidated CSS. Unique audit tests
and evidence were retained. Current main's dependency updates were preserved.

Per ADR 0024 obligation 4, the final surface digest is prepared and tested here,
then committed **immediately after the merge on main**. The slice itself leaves
main's baseline and manifest unchanged. The verified final digest is
`48aa68e6669922764e09ba14542ccf75f1033178ddb3cc210717a9f1c9c20a72`.

Merged on main as `48c941d4`, followed immediately by baseline refresh
`4b352728`. All 113 merged files matched the tested release tree. The full suite
was rerun on main: [PHPUnit](main-phpunit.txt) again passed 3,009 tests / 22,725
assertions with the same six deprecations and one skip;
[Imladris](main-imladris.txt) passed 24 / 296 and
[generated assets](main-assets.txt) remained current. The later group-test helper
change affects only browser automation and is covered by the combined rerun.

Hostname-route [PR #72](https://github.com/henryperkins/community-forums/pull/72)
remains open and draft. Its Cloudflare prerequisites are unverified by this work.
Its branch and worktree are retained.

Main was pushed through `8ea7734d`; local and remote matched. All four Messages
worktrees and both temporary Messages branches were removed after their source
and local-state snapshots were archived. The three owned databases and their
specific grants were removed; the existing main server and Messages database
were preserved. See [cleanup.json](cleanup.json).

GitHub's [browser workflow](https://github.com/henryperkins/community-forums/actions/runs/35832801398)
did not start any step: its annotation says the account is locked due to a
billing issue. This is an external CI limitation, not a CI pass. The local runs
above supply the verification evidence; the CI receipt is in [ci.json](ci.json).
