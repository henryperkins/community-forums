# Slice 9 admin notifications design QA

Status: complete for the Slice 9 Email & announcements boundary.

References:

- `docs/design-system/imladris/templates/admin-notifications/AdminNotifications.dc.html`
- `docs/superpowers/plans/imladris-admin-account-stage1/D-admin-notifications.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/R-admin-notifications.md`
- `docs/superpowers/plans/imladris-admin-account-stage1/V-admin-notifications.md`

Captured 2026-08-04 against the real PHP application and freshly seeded private browser databases. The final standard `gate-a.spec.ts` run passed 55 tests with 3 expected project-specific skips across desktop and mobile. The dark-fixture `a11y.spec.ts` run passed 28/28; a second targeted pass forced the real twilight theme on the touched Email and Announcements routes and passed 2/2. The focused twilight `gate-a.spec.ts` journeys passed 5 tests with the expected mobile no-JS skip. Light and twilight desktop, 390px no-JS, and mobile captures were visually inspected.

Reviewed against the references:

- Email and Announcements retain the area-owned `Email & announcements` heading and exact two-tab hierarchy. Their separate crawlable routes, server-rendered forms, CSRF fields, authorization, PRG redirects, and no-JS operation remain authoritative.
- The ADR-locked F24 transport, From address, and sending-domain facts remain three independent facts above the domain card (`C-29`). The page does not collapse them into a false configured state, and verified-domain blocking remains production-owned.
- The domain/test two-card grid, six queue facts, raised delivery/suppression cards, filter geometry, result count, table presentation, empty state, and responsive collapse copy the design. Filter values remain real production vocabulary and canonical order; `verify` and `reset` kinds were not invented (`FR-22`).
- The delivery table retains production's `Detail` column (`FA-15`), `n / max` attempts plus absolute next-retry line (`FA-16`), CSV export, focusable overflow region, and failed-only requeue action. The accessible Actions header remains screen-reader text rather than the design's empty header.
- Both operator tables retain the labelled inner `.table-scroll` region and the console card's independent horizontal containment required by `C-05`; the design's visible-overflow card rule is deliberately not copied because it breaks mobile Chromium actionability.
- Test-send confirmation adopts the design's inline placement while retaining server-owned POST-redirect-flash state (`FC-19`). Success uses `role="status"`, known failures use `role="alert"`, unrelated flash remains in the general slot, and the false design claim that a synchronous send was merely queued does not ship.
- Suppression count chrome was removed because production exposes no count contract. Stored reason keys are rendered as operator-facing copy, and `Release` matches the design while preserving the same audited server action.
- No automatic bounce/complaint ingestion, thirty-day retention promise, or unreachable verification/reset delivery kinds were added (`FR-20`, `FR-21`, `FR-22`). Existing bounced/complained storage statuses remain honest status vocabulary without fictional fixtures or ingestion claims.
- Announcements copy the design's current/publish/history anatomy, default the untouched Dismissible control on, and preserve explicit unchecked state plus every submitted choice on 422 and 429 responses. The textarea count uses matching PHP and progressive-enhancement Unicode code-point semantics.
- The email-broadcast warning is server-rendered, works without JavaScript through CSS `:has()`, and reports the exact active-member recipient population used by the enqueue SQL, excluding the acting administrator. The warning makes no recall promise beyond the real queue boundary.
- Announcement history remains audit-derived; no fictional announcements table or client state machine was introduced. Banner, in-app, and email channel facts retain the production audit payload.
- No inline script, inline style, event handler, fictional delivery record, or design-only client state was imported.

Representative light captures:

- `desktop/20-announcement-banner.png`
- `desktop/21-announcement-dismissed.png`
- `desktop/22-admin-email-dashboard.png`
- `desktop/23-admin-email-suppressed.png`
- `desktop/24-admin-email-test-sent.png`
- `desktop/24-admin-notifications-no-js.png`
- `desktop/remediation-announcement-429.png`
- `desktop/remediation-announcement-history.png`
- `mobile/20-announcement-banner.png`
- `mobile/22-admin-email-dashboard.png`
- `mobile/24-admin-email-test-sent.png`

Representative twilight captures mirror the same `20` through `24` journeys under `twilight/desktop/` and `twilight/mobile/`; the explicit no-JS capture is `twilight/desktop/24-admin-notifications-no-js.png` at 390px.

The full unfiltered `admin-remediation.spec.ts` run retained the operator-verified pre-existing board-delete/composer failure. That run also saw one transient PHP-server connection refusal in the PII/ban journey; the journey passed immediately in isolation and again in the final full remediation run with only the known board-delete case excluded. That final run passed 15 tests with 13 expected project skips. The baseline defect was not changed or hidden.

Focused backend verification passed 65 tests / 374 assertions. The final full PHPUnit run passed 2,542 tests / 18,333 assertions / 2 skips, and `composer verify:imladris` passed 18 tests / 254 assertions under the required temporary application-digest allowance. The allowance was then removed from both baseline files because runtime-baseline refreshes land only once per merge on `main`. PHP syntax, JavaScript syntax, `git diff --check`, Playwright discovery (116 tests across the named specs), and the CSP template scan passed; the CSP scan returned only `layout.php`'s permitted external script tags.

This evidence certifies only the Notifications area Email and Announcements bodies. Shared admin chrome was certified by Slice 2; later admin and account areas remain separate slice work.
