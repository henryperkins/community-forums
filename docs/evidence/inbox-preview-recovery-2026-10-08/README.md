# Inbox preview review fixes and release verification

Date: 2026-10-08 UTC. Base revision: `573f905d71f3c146072cffd5fecb2d2ffaca995b`.
Final local build: **`5de73df06f287ae9`**. This record supersedes the current-build
claims in the earlier Inbox polish, motion, hardening and directory evidence
passes; their artifacts retain their original build boundaries.

## Fixed review findings

1. A failed preview hid the phone reading pane after its original topic had left
   Unread, leaving its retained draft inaccessible. **Return to previous topic**
   now reveals the existing article and focuses its heading. It preserves the
   exact textarea/editor nodes and typed text, makes no preview request, and
   adds no history entry. If a failed Back/Forward load changed the address to
   another topic, recovery replaces that entry while preserving view parameters
   and its fragment. An initial failure offers no previous-topic command.
2. Capture-phase submission cancellation invalidated previews before enhanced
   pane forms prevented navigation. Cancellation now observes the bubble-phase
   event and respects `defaultPrevented`. Both pane preferences preserve loading
   and recovery controls; native row and bulk submissions still invalidate stale
   responses before competing canonical navigation.

The new control uses the existing button vocabulary and 44px targets. Its hidden
state is explicit so application button styles cannot reveal it. The recovery
contract is recorded in ADR 0045, USER.md and PRODUCT_DESIGN.md.

## Final verification

| Check | Result | Record |
| --- | --- | --- |
| Full local PHPUnit suite | 3,175 tests / 25,179 assertions / 1 existing skip / no failures | [Full log](verification/phpunit-full.log) |
| Focused Inbox, intent, snooze, member, directory and Request tests with native PDO prepares | 64 tests / 1,524 assertions / no failures | [Native log](verification/phpunit-native-focused.log) |
| New recovery tests, Chromium desktop and phone | 18 passed / 6 desktop-only cases skipped on phone | [Chromium log](verification/recovery-chromium.log) |
| New recovery tests, WebKit desktop and phone | 18 passed / 6 desktop-only cases skipped on phone | [WebKit log](verification/recovery-webkit.log) |
| Existing Inbox controls/header and shared subheaders, Chromium | 44 passed | [Regression log](verification/regression-chromium.log) |
| Same existing browser specs, WebKit | 44 passed | [Regression log](verification/regression-webkit.log) |
| Asset retention and Worker tests | 24 passed | [Asset tests](verification/assets-tests.log) |
| Asset build/check and Imladris generated/runtime check | Passed | [Build](verification/assets-build.log), [assets](verification/assets-check.log), [Imladris](verification/imladris-check.log) |

These final browser runs total **124 passed and 12 skipped**. Recovery tests use
1440×900 and 393×844; existing regression specs also exercise native/no-JS
controls, constrained layouts, long text and the application's Large text
setting. The full PHP suite covers the changed backend and migrations; its one
skip is the existing migration 0077 down/up rehearsal reserved for its dedicated
fixture-free database. No migration rollback against a live database is claimed.

Recovery coverage includes repeated HTTP failures, a 15-second timeout without
AbortController, ignored late responses, initial-error visibility, failed
history restoration, both pane forms with and without AbortController, a generic
prevented submission, and native row/bulk submissions with a late access error.
For the last case, a delayed preview promise and one-shot window submit listener
keep the document observable after the application's submission handler. The
same form and submitter are then submitted to the real server and must return
303 to Inbox. This avoids relying on transport that browsers cancel during
native navigation. [The spec](../../../tests/browser/inbox-preview-recovery.spec.ts)
is the reproducible harness.

Each engine/project fetches the actual CSS and JS and checks the served bytes
against the manifest; `served-assets-*.json` records the receipts. The new JS is
`app-854791801de6c0fa.js`; CSS is `app-style-DEqCyv6L.css`. Deployed release pins
remain unchanged. Exact hashes and counts are in [VERIFICATION.json](VERIFICATION.json).
The root reviewer inspected both engines' phone recovery and restored-draft
screenshots. Independent source review found no remaining issue in these fixes.

## Red proofs and harness corrections

[Initial red tests](verification/red-initial.log) reproduced the missing return
control but initially expected 200 from the preference POST, which correctly
returns 303 before fetch follows its redirect. [Corrected rail red proof](verification/red-rail-confirmation.log)
then reproduced cleared loading after the pane toggle before application edits.

The first native-submit harness attempts held a navigation response and waited
for a browser fetch that native navigation cancels. A delayed promise alone
still could not make Playwright evaluate a provisionally navigating document.
Those interrupted attempts remain in `*-harness.log`. The corrected window
barrier proved both native paths; a final bulk helper correction reads the form's
`action` attribute because named action buttons shadow its DOM property. The
passing native confirmation is separate from the final complete browser runs.
This is a corrected verification sequence, not an uninterrupted green run.

## Existing baseline and limits

The initial review's create-menu/control/subheader lane completed with 54 passed,
4 failed and 8 skipped. Its four failures were native/no-JS Messages geometry
assertions: desktop subheader reservation and enlarged phone conversation
height. Chromium and WebKit comparisons against untouched HEAD-generated CSS
reproduced the same measurements, with Send remaining visible. The reduced
[HEAD comparison](verification/messages-head-comparison.json) records those
measurements. The final Inbox repair does not change that Messages geometry;
the failing create-menu lane was not rerun or relabeled passing. The final
browser results above cover the specified four specs, not the entire suite.

All databases and rate/package stores used here were private throwaway fixtures.
Browser evidence is Linux Chromium/WebKit emulation, without physical iOS/Safari,
Firefox, assistive technology or physical browser zoom qualification. Git
integration and production deployment are separate; this evidence does not
qualify production health or deployment completion.
