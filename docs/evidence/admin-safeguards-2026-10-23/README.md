# Admin safeguards and readable audit — local evidence, 2026-10-23

The two deferred P2 findings in ADR 0024 are implemented on
`codex/admin-safeguards-20261023`, based on `d9d56fba67f1f40dd56dc197a04c4cf4cc384325`.
This record qualifies the local implementation. It does not establish publication,
automatic deployment, authenticated production behavior or physical-device behavior.

| Evidence | Result |
|---|---|
| Focused PHPUnit | **137 passed, 869 assertions**; see [phpunit.txt](phpunit.txt). |
| Focused Chromium Playwright | **10 passed**, desktop 1280×800 and mobile emulation 390×844; see [browser.txt](browser.txt). |
| Audit content axe | No serious/critical WCAG 2/2.1 A/AA violations in the changed audit-table region, both viewport projects. |
| Layout | No document overflow in the tested confirmation, suspension, bulk and audit views. Screenshots inspected. Existing audit table retains its horizontal scroll region. |
| Independent code review | Parent reviewer reported no P1/P2 findings, including additional strict-UTC parser cases. |

PHPUnit ran against the private `retroboards_admin_safeguards_20261023` database and covers
review/edit/cancel versus confirmed mutation, admin/account-state/feature/CSRF guards,
real-calendar UTC validation and legacy compatibility, draft and bulk-selection preservation,
preset batch consistency, escaped identities, missing/deleted identity handling, bounded values,
and existing moderation/audit/filter/pagination regressions.

Playwright ran against private `retroboards_admin_safeguards_browser_20261023` on port 8095:

```bash
DB_DATABASE=retroboards_admin_safeguards_browser_20261023 E2E_PORT=8095 \
  RB_EVIDENCE_DIR=docs/evidence/admin-safeguards-2026-10-23 \
  npx --prefix tests/browser playwright test \
  --config=tests/browser/playwright.config.ts admin-safeguards.spec.ts
```

The five journeys per viewport are Block review/edit/cancel/confirm (including a leading-newline
phrase draft), native UTC plus preset expiry, readable audit with unknown escaped raw data,
no-JavaScript registration/Block confirmations, and no-JavaScript bulk suspension with an actual
422 navigation preserving an impossible date and its reason/selection. The invalid-date test
changes the input type to model a crafted/stale form bypassing browser validity; production
server validation is what rejects it. No model calls or external services are used.

Screenshots are in [desktop](desktop/) and [mobile](mobile/): `block-review`,
`registration-no-js`, `utc-restriction`, `bulk-utc-no-js`, `invalid-date-no-js`, and
`readable-audit`. `screenshots.sha256` records their file identity. Only synthetic fixture data
appears in them. The initial browser attempt had two test-locator typos; corrected complete runs
passed all 10 journeys.

Full integrated PHPUnit, assets/runtime-baseline checks, existing browser regression lanes, and
release verification belong to the parent integration workflow. No CSS, application JavaScript,
schema, feature defaults or generated assets changed in this implementation. Native date-picker
presentation, screen-reader behavior and touch-keyboard behavior remain device/browser-specific;
these Chromium emulations do not claim physical-device coverage.
