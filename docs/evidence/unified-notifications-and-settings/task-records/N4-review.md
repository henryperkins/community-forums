# N4 review: shared notification presentation

Reviewed frozen change `95db930a..dc1f3749` against `N4-brief.md`, `N4-handoff.md`, `N4-report.md`, and the combined design's presentation, privacy, accessibility, and release-proof constraints. Review was read-only except for this report. No source, index, HEAD, database, or browser-server state was changed. No reported suite was rerun.

**Spec compliance: PASS for the assigned N4 slice.**

**Quality: PASS. No actionable N4 defects found.**

These verdicts do not claim the complete combined feature is released or the final full suite is green. N5, A5, final integration gates, documentation reconciliation, and canonical evidence promotion remain parent-owned as instructed.

## Contract review

| Requirement | Evidence and conclusion |
| --- | --- |
| Same presenter and partials at both entries | `src/Controller/NotificationController.php:27` and `src/Controller/HomeController.php:115` map already-authorized page items through the same presenter. `templates/notifications.php:4` and `templates/home.php:184` invoke the same list partial. `templates/partials/notification_list.php:20` invokes one shared row partial. No parallel event-copy implementation remains in these templates. |
| Exact presentation contract | `src/Support/NotificationPresenter.php:26` contains the single copy/icon mapping and returns the required ten fields. It projects strings/IDs without querying repositories or rebuilding authorization. Missing actors use Someone; long strings remain intact; current targetless moderation notices have appeal-resolution copy. |
| Privacy and owned action behavior | The only controller changes add presentation mapping. The existing service continues to supply scoped rows and own click-time resolution. A targeted collaborator inspection verified `src/Repository/NotificationRepository.php:181` applies eligibility before the result limit and masks anonymous actor name, username, and ID before returning rows. `src/Service/NotificationReadService.php:40` retains ownership checking and action-time authorization. The row partial renders only projected presentation fields, escapes text/SVG path values/return paths, and posts the numeric notification ID to the existing endpoint with CSRF. |
| Both surfaces, return paths, history, empty states | `templates/partials/notification_list.php:3` supplies identical heading/actions; lines 11–18 supply All/Unread, Latest/Next, and distinct empty-All, empty-Unread, and exhausted-history copy. Controllers keep their existing validated return models. Mark all read uses the page-wide eligible unread count, so an older unread event does not disappear merely because the current page contains read rows. Clear all remains available on empty Unread/history views, where read or newer history may still exist. |
| Accessible names and timestamps | `templates/partials/notification_row.php:8` separates the decorative unread marker from screen-reader Unread text. Lines 9–13 retain escaped actor/topic copy, action text, machine-readable ISO UTC time, relative visible time, and a full timestamp title. Native buttons/forms retain keyboard and no-JS operation. |
| Shared styles and design contracts | The frozen diff adds the same notification CSS block to the app stylesheet, design source, resource mirror, and generated stylesheet. It uses existing color/focus tokens, underlined prose/history links, wrap-capable message columns, and a narrow-width timestamp row. Old selector assertions were deliberately changed to the emitted classes without removing anonymous-masking or stylesheet coverage assertions. The runtime digest refresh preserves `reconciled_through_commit`, consistent with the explicit branch ruling. |

## Evidence assessment

The recorded logs were opened and agree with the report:

- `docs/evidence/unified-notifications-and-settings/n4/phpunit-focused.log`: **80 tests, 738 assertions, pass**. The expanded group includes the required notification, anonymity, board-index, and Imladris collateral checks.
- `browser-desktop.log` and `browser-mobile.log` in the same directory: **5/5 each**, including equality/history, responsive layouts, inaccessible/empty states, and both no-JS action flows.
- `board-index-desktop.log` and `board-index-mobile.log`: the original account-adjacent-pane check passes **1/1 each**.
- `phpunit-full-interim.log`: **2,892 tests, 21,764 assertions, two failures, six deprecations, one skip**. This is explicitly an interim result. The guest-copy failure is addressed by the reviewed contract update and expanded focused group; the other failure belongs to the separately reported parent fix. Final full-suite verification remains outstanding for the parent.

The browser spec includes behavioral and geometry assertions rather than relying on capture existence: matching text at both entries, every stored event vocabulary, anonymous/missing actors, targetless appeals, escaped markup, an unread event older than 35 read rows, ISO parsing, filters/history, inaccessible targets, disabled zero-unread actions, native POSTs without JavaScript, focus round-tripping, a visible focus outline, minimum button height, horizontal bounds, and unread-marker alignment (`tests/browser/notifications-unified.spec.ts:27`, `:43`, `:75`, `:117`, `:136`). Axe is deliberately scoped to the notification panel and the specified link/color/name rules, rather than represented as a whole-application accessibility audit.

The fixture rejects non-test environments and schemas outside the disposable unified-evidence naming convention (`tests/browser/notifications-unified-fixture.php:17`). The report documents cleanup back to the standard seed and avoids claiming that external mail was exercised.

I opened the following existing captures and checked the displayed component rather than inferring appearance from file existence:

- Both `standalone-empty.png` and `pane-empty.png`.
- Both `standalone-inaccessible.png` and `pane-inaccessible.png`.
- Both `standalone-unread-only.png` and `pane-unread-only.png`.
- `pane-light-320.png`, showing wrapping, secondary timestamp placement, and a visible unclipped row focus outline.
- `standalone-dark-zoom200.png`, showing the recorded Twilight/reflow presentation.
- The proposed canonical `n4/board-index/desktop/05-pane-notices.png`, showing the standard desktop fixture without the onboarding overlay.

The inspected captures match the shared presentation, show readable actor/topic content and visibly underlined prose links, and do not reveal a new notification-component layout defect.

## Limits and remaining ownership

The 200% evidence uses a 640×500 CSS viewport and deviceScaleFactor 2 to model the reflow of a 1280×1000 viewport at 200%; it does not exercise a native browser zoom setting. This limitation is accurately disclosed in the implementation report and is adequate evidence of the relevant component reflow, without claiming browser-zoom control coverage.

This review accepts the supplied focused test evidence and inspected assertions; it did not rerun the suites or take over shared port 8034. The report's build/check results are recorded evidence, and final combined runtime-digest/build verification remains a parent gate because later slices change runtime files.

The parent should promote `docs/evidence/unified-notifications-and-settings/n4/board-index/desktop/05-pane-notices.png` only after the final matching integrated capture/gates, and update canonical README/ADR references then. No missing N5 bell or A5 mobile-settings work is charged against N4. No N4 fix is requested.
