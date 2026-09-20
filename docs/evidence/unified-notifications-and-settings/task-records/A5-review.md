# A5 independent review

Reviewed on 2026-09-20. Scope is the frozen A5 package `429b8f91..e8e41c9c` (17 owned paths), `A5-brief.md`, `A5-handoff.md`, `A5-report.md`, the responsive-settings/session-label contracts in the combined design, and implementation-decisions.md ruling 7. No source, index, HEAD, database, browser-server, or generated-asset changes were made. Only this review document was written.

## Spec-compliance verdict

**PASS for the A5 implementation and focused evidence; combined N6 release proof remains pending.** No A5 requirement violation requiring correction was found.

- The existing three-group model remains authoritative. Both responsive renderings call one links partial and retain identical feature filtering, route order, active state, and Replay hook. The mobile chooser is a native, initially closed details element with the active section in its summary; the desktop rail remains the visible sticky navigation above the existing breakpoint. There are no new shared element IDs. Thirteen account destinations plus the intentional Appeals exit remain accounted for.
- Native keyboard disclosure/navigation and no-JavaScript ordinary links are covered. The initial 390×844 Security, Profile, and Notifications captures show their first editable control inside the viewport. The logged first-control bottoms (approximately 614, 714, and 693 CSS pixels) are consistent with the opened images.
- The account-only no-JavaScript rail cap is the explicit adaptation authorized by ruling 7. Its body marker comes from rendering the settings partial before the outer layout; it therefore covers the account surfaces without altering unrelated page rails. Existing links remain in the native scrollable navigation, which gains keyboard focusability. The no-JavaScript <=860px selector excludes the enhanced drawer; the desktop geometry is preserved. Separate rail scrolling is a documented tradeoff, not an undeclared omission.
- UserAgentLabel returns bounded, deterministic fixed-literal labels. Edge/Opera/Samsung/Firefox variants precede generic Chrome/Safari; OS overlap prioritizes iOS and Android appropriately. Null, malformed, oversized and unrecognized input has readable fallbacks. The implementation neither infers a physical model nor uses UA recognition for authorization.
- Session primary text is the concise label. Raw stored strings are escaped in secondary native details, while current-device status, IP/last-active information, lifetime copy, owned session IDs, CSRF-bearing revoke forms, and current-device omission of the per-row revoke button are retained.

## Code-quality verdict

**PASS. No actionable correctness, security, or maintainability regression was found in the frozen A5 diff.**

The template extraction avoids parallel navigation models. Responsive visibility is mutually exclusive; closed native details keeps hidden mobile links out of normal tab order. The display-only label enrichment is local to SettingsController::sessions and does not change session persistence or revocation policy. Fixed regular expressions operate on at most 1024 bytes, and the template still escapes the original raw value using View::e, which substitutes invalid UTF-8 safely.

Unchanged collaborators were inspected only to resolve concrete risks: View rendering order and shared partial globals for the account marker/feature model; tour.js delegated Replay handling for duplicated responsive buttons; app.js disclosure selectors for unintended chooser interference; SessionRepository's selected columns and ownership-scoped revoke queries. Those checks found no integration defect. Application CSS and its Imladris source/mirror/generated additions are consistent in the frozen package, with the pinned reconciliation commit preserved.

## Evidence inspected and limits

Read the committed evidence logs rather than treating the implementation report as proof by itself:

- `a5-final-php.log`: 91 tests, 773 assertions, pass. This is supplied focused evidence, not a reviewer rerun or a full-suite claim.
- `a5-final-browser.log`: 17 passed, 12 viewport-specific skips, one stress-test timeout/cleanup failure. This broad run is **not** an entirely passing run.
- `a5-stress-final.log`: the isolated six-context stress test passes with its increased 90-second budget (38.7-second test). `a5-sessions-final.log`: desktop/mobile raw-UA and no-JavaScript revoke workflows pass 2/2.
- `a5-build.log`: Imladris build and currency check pass. `a5-cleanup.log`: the dedicated browser schema was reset and seeded after evidence capture.

Opened these selected artifacts under `docs/evidence/unified-notifications-and-settings/a5/`: mobile initial Security light, Profile dark, Notifications light; mobile 320px dark no-JavaScript long-label chooser; desktop and mobile expanded raw-session details. The forms are initially accessible, the long label wraps, the current-device chip remains readable, and HTML-looking raw strings render as text without overlap.

The 200% case is CSS reflow emulation at 640×500 with deviceScaleFactor 2; it is not evidence of manipulating native browser zoom. The broad account-console rerun, account-settings-repairs/Gate A/board-index collateral, final full PHPUnit and Imladris checks, and final evidence promotion remain the parent's N6 responsibility. External OAuth ceremony and real email delivery were not exercised.

The known shared-header clipping and notifications-unified Replay selector collateral belong to the separately assigned N5 follow-up. They were not re-reviewed against moving source here and must not be considered closed by this A5 verdict. No new A5-specific finding was discovered.
