# Slice 7 admin people design QA

Status: complete for the Slice 7 Roles & capabilities boundary.

References:

- `docs/design-system/imladris/templates/admin-people/AdminPeople.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-people.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-admin-people.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-people.md`

Captured 2026-08-04 against the real PHP application and freshly seeded private browser databases. `tests/browser/role-assignments.spec.ts` passed 6/6 across desktop and mobile under `CAPABILITIES_MODE=enforce`. The role-focused `gate-a.spec.ts` and `a11y.spec.ts` run passed 5 with 1 expected desktop skip for the mobile-only no-JS check. `admin-features.spec.ts` passed 2/2 on its separate default-posture database; it cannot share the dark-surface fixture because it intentionally proves that Extensions remains unlinked while its flag is dark.

Reviewed against the references:

- Roles and Permission simulator preserve the area-owned `Roles & capabilities` heading and the exact two-tab hierarchy. Role records use the role name as an h2 beneath that shared accessible page name.
- The role list keeps the resolver-posture explanation and simulator inbound link, static count, labelled table scroll region, protected/custom chips, mono numeric columns, and outlined row actions. No non-functional search, segmented filter, or impossible empty state was added.
- Role creation keeps the design's two-column name/description row, grouped capability fieldsets, consent-first descriptions, high-risk indicators, inline reauthentication, and exact primary-action recipe. The selected-capability counter is created only by progressive enhancement and is absent from the no-JS document.
- Role records retain production capability editing as the ledgered `FA-23` extrapolation. System anchors remain read-only and cannot receive assignments. Clone and assignment mutations remain real POST forms with CSRF and inline reauthentication.
- Assignment presentation covers the four production states (`active`, `scheduled`, `expired`, and `revoked`) under `FA-24`. The production-only Reason field remains seventh in the design's three-column grid; scope options remain Site-wide, Category, Board.
- Definition, clone, assignment, and per-row renewal failures now use context-unique input/error ids while preserving every typed value on a 422 response. This closes only the `role_edit.php` half of ADR 0023 deferral #4; the registries half remains open.
- The simulator remains a server-rendered GET form. An explicitly submitted empty capability renders `Pick a capability to test.` in the separate error card, and Allowed/Denied results retain decisive rule, reason, and conditional role provenance.
- A genuine JavaScript-disabled browser context completed create → assign → renew → revoke on both desktop and mobile. At 390px, capability groups collapse to one column, table regions remain independently scrollable, and primary controls remain usable without an alternate client-only path.
- The captured roles, simulator, active/revoked assignment, deputy-control, approvals-queue, feature-inventory, and no-JS surfaces were visually inspected. The broad dark-surface axe journey and the role-assignment scans reported no serious or critical findings.

Representative captures:

- `desktop/30-admin-role-created.png`
- `desktop/31-admin-role-simulator.png`
- `desktop/62-admin-role-assigned.png`
- `desktop/63-admin-role-assignment-revoked.png`
- `desktop/64-deputy-sees-lock-control.png`
- `desktop/65-deputy-approvals-queue.png`
- `desktop/66-admin-role-no-js-lifecycle.png`
- `mobile/30-admin-role-created.png`
- `mobile/31-admin-role-simulator.png`
- `mobile/62-admin-role-assigned.png`
- `mobile/66-admin-role-no-js-lifecycle.png`

Adjudicated deviations in scope remain governed by the central ledger, notably:

- `FA-23` — production custom-role capability editing remains, styled as a faithful extrapolation from the design's creation catalogue.
- `FA-24` — scheduled and expired assignment states receive explicit production-state chips in addition to the design's active and revoked examples.
- `FR-17` and `FR-18` — no inert role search/filter/empty chrome and no unusable system-role assignment form were added.
- `FC-24` — Permission simulator is now a first-class tab while the shipped inbound explanatory link remains.
- Production identities, role keys, capabilities, scopes, timestamps, permissions, and mutation semantics remain authoritative; the design's fictional data and client state machine were not imported.

Focused backend verification passed 34 tests / 223 assertions. The final full PHPUnit run passed 2,527 tests / 18,189 assertions / 2 skips, and `composer verify:imladris` passed 18 tests / 254 assertions under the required temporary application-digest allowance. The allowance was then removed from both baseline files because runtime-baseline refreshes land only once per merge on `main`. PHP syntax, JavaScript syntax, `git diff --check`, and the CSP template scan passed; the CSP scan returned only `layout.php`'s permitted external script tags.

This evidence certifies only the People area Roles and Permission simulator bodies. Shared admin chrome was certified by Slice 2; Members and later admin/account areas remain separate slice work.
