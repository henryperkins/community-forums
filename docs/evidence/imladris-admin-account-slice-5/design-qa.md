# Slice 5 admin overview design QA

Status: complete for the Slice 5 admin-overview boundary.

Reference: `docs/design-system/imladris/templates/admin-overview/AdminOverview.dc.html`

Captured 2026-08-04 by `tests/browser/admin-dashboard.spec.ts` and `tests/browser/admin-remediation.spec.ts`: 20 passed and 16 expected cross-project skips after excluding only the pre-existing `board delete previews the authoritative count including hidden content` case. The final run used the real PHP application, a freshly seeded `retroboards_admin_s5_browser_final2` database, desktop 1280×800, and mobile 390×844.

Reviewed against the reference:

- The Dashboard and Audit log preserve the design's area-owned `Admin console` heading, exact tab order, content hierarchy, spacing, card treatment, table treatment, and one-based pager presentation.
- Queue health exposes the five real production signals with the approved `attention`, `clear`, and `unavailable` states. The design's unimplemented waiting tier, SLA ages, and uncomputed community metrics remain absent as adjudicated.
- Community today retains exactly the two computed production metrics. Recent activity keeps six append-only records and the production overflow cue while adopting the design's card and table presentation.
- Audit filters remain server-rendered GET controls. External pages are one-based, out-of-range pages clamp to the final page, disabled ends have no link, target links exist only for users, and raw before/after JSON stays available in disclosures.
- At 390px, queue cards collapse to one full-width column, filter controls and pager controls remain at least 44px high, the area tier scrolls independently, and the document itself has no horizontal overflow. A red/green browser assertion caught and fixed the scoped-grid specificity regression before the final evidence run. For full-page mobile Audit captures only, the sticky bar is temporarily rendered statically to prevent Chromium screenshot stitching from duplicating it; live sticky positioning remains asserted separately.
- Dashboard and Audit log were scanned in light, twilight, and system-dark registers with no serious or critical axe findings.

Representative captures:

- `desktop/07-admin-dashboard-light.png`
- `desktop/07-admin-dashboard-twilight.png`
- `desktop/05-admin-audit-light.png`
- `desktop/05-admin-audit-twilight.png`
- `mobile/07-admin-dashboard-light.png`
- `mobile/07-admin-dashboard-twilight.png`
- `mobile/05-admin-audit-light.png`
- `mobile/05-admin-audit-twilight.png`
- `mobile/05-admin-audit-no-js-page-2.png`
- `mobile/07-admin-dashboard-no-js.png`

Adjudicated deviations in scope remain governed by the central ledger, notably:

- `C-31` — the client-switched design panes remain real, crawlable Dashboard and Audit routes.
- `C-38` — small audit target links use the existing `--on-info` foreground because the design's `--artifact-link` resolves to 2.29:1 in system dark.
- `FA-17` / `FA-18` — the dashboard empty-audit state and audit-row hover remain.
- `FC-20` / `FC-21` — the production 50-row audit page size and raw before/after disclosure remain while their presentation follows the design.

This evidence certifies only the Overview area Dashboard and Audit log bodies. The shared admin chrome was certified by Slice 2; later admin areas remain separate slice work.
