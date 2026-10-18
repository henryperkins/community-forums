# Members and People remediation plan

**Goal:** Resolve the verified Members/People review findings, prove the behavior locally, publish to main, and verify both authenticated live pages.

**Architecture:** Keep the PHP service/repository write boundaries and server-rendered forms. Native named target selectors work without JavaScript; JavaScript only enhances selection counts and overflow cues. Preserve Imladris tokens, capability gates, reauthentication, audit events, and shadow/enforce authority semantics.

**Specs:** ADMIN §§1.4, 2.3, 5.1, 9.4; PRODUCT_DESIGN §§3, 11, 13; ADRs 0016, 0023, 0024. The review in this chat supplies the accepted bounded remediation scope.

## Implementation and verification

- [x] Members: regression-first coverage for warning eligibility/order and whole-day join filtering; validate shared inputs before bulk writes; preserve 422 drafts and filters.
- [x] People: regression-first invalid-calendar simulator coverage; validate strict UTC input without normalization.
- [x] Forms: replace ambiguous numeric scope suggestions with a native named scope/target selector, retain legacy POST compatibility, add named simulator boards, and wire capability errors/focus.
- [x] Clarity: explain live permission effect near save/assign, lead capability choices with human descriptions, distinguish unavailable capabilities, clarify explicit assignment counts, and humanize member state labels.
- [x] Responsive/progressive enhancement: shrink record grids and wrap identifiers; surface mobile role actions and overflow cues; provide 44px member-selection labels; hide the JavaScript-only select-all control until enhanced.
- [x] Validate: run focused PHP regressions, complete composer test, generated asset/Worker checks, targeted existing browser suites and new desktop/mobile/320px/enlarged-text/light/dark/keyboard/no-JS regressions; inspect captured screenshots.
- [ ] Review the complete diff independently, resolve findings, commit only this scope, and push main.
- [ ] Verify exact Cloud Run/cron and Workers Builds releases, live asset bytes, and authenticated read-only Members/People page behavior. Revoke the temporary audit session and remove private material.

## Review focus

- Self first/last and admin targets must produce the same warning outcome and accurate eligible counts.
- Empty/invalid shared inputs must fail before any target mutation; original filters/selection remain available.
- Board/category identifiers may collide; named selectors must preserve the intended kind without JavaScript and through 422 responses.
- Invalid targets and dates must never broaden a grant or silently become another time.
- Capability error focus, narrow layouts, long identifiers and enlarged text must retain usable controls and complete content.

Work runs in the clean main checkout requested for publication, with disjoint agent file ownership and private test/browser databases. No migrations or production permission changes are required.
