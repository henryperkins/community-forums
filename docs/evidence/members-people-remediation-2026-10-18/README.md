# Members and People remediation — 2026-10-18

Scope: `/admin/users`, member records and bulk confirmation; `/admin/roles`, role editing/assignments and permission simulation. Implements the accepted review against baseline `2f02ce1b`, under ADMIN §§1.4, 2.3, 5.1, 9.4; PRODUCT_DESIGN §§3, 11, 13; ADRs 0016, 0023, 0024.

## Local acceptance

| Check | Result |
| --- | --- |
| Complete `composer test` | 3,277 tests, 25,834 assertions, one skip; no failures |
| New browser regressions | 30 passed in shadow mode, plus two enforce-mode cases |
| Existing Members/account/role browser matrix | 36 passed, 14 intentional project-specific skips; no failures or flaky tests |
| New light/dark axe checks | 20 scoped scans, zero violations |
| Worker/asset unit checks | 35 passed |
| Compiled asset and Imladris contract checks | Current; reviewed application digest and generated manifest refreshed |
| PHP/JavaScript syntax and diff checks | Passed |

The final browser build is `244ddf3d50bbd19e`, with `app-style-BS0lqYEG.css` and `app-6d007c03ee2fa15b.js`. Browser submissions used private local MariaDB databases, synthetic users and array mail; zero outbound deliveries. The first existing-browser attempt had an incompatible inherited WebAuthn RP ID for localhost; a fresh run with the correct local value produced the qualified matrix above.

Tests first reproduced warning order, date cutoff/normalization, target parsing, missing capability-error linkage and 320px overflow failures. Recovery coverage additionally parses and re-submits actual selected member IDs from an invalid-filter 422.

## Finding coverage

- Bulk warnings consistently skip self, keep peer administrators warnable, validate shared reason/expiry before per-member writes, and show eligible counts in both heading and action. Zero-eligible confirmation cannot submit.
- Join-date filters include the whole selected UTC end day; invalid/inverted bounds return a preserved-input 422 without broadening results. The simulator rejects calendar/time normalization, retains UTC, and tests exact grant-window boundaries.
- A native named target selector keeps board/category identities distinct even when their numeric IDs overlap. Legacy direct POST fields remain supported. No-JavaScript create, grant, renew and revoke journeys remain functional.
- Capability errors have group and checkbox linkage, an announced error, and focus on an available choice. Available human descriptions lead; unavailable choices remain disabled in a native disclosure. Permission posture is explained beside Save and Assign, and explicit assignment counts are distinguished from built-in membership roles.
- Native member checkboxes have actual 44px clickable labels; select-all appears only after its JavaScript handler is ready. Preserved hidden selections and visible selection counts are handled separately.
- Member records fit 320px; capability identifiers wrap at 200% text size; mobile role actions remain readable, directly reachable and at least 44px. Tables retain named keyboard-scroll regions and visible overflow guidance. The mobile role password field no longer reserves an unintended 240px height above Save.

The screenshots in `screenshots/` use synthetic accounts. The durable regression drivers are `tests/browser/members-people-remediation.spec.ts` and its guarded fixture. Set `RB_EVIDENCE_DIR` to scratch space and use an empty migrated local database named `retroboards_e2e_members_people_<suffix>` with APP_ENV=test, an empty DB socket, and array mail. The normal Playwright configuration supports `E2E_PORT` and `DB_DATABASE`; use a separate database/server for the existing enforce-mode role matrix.

## Evidence limits and release

Chromium viewport/text-resize checks are emulation, not physical-device or screen-reader certification. The full hosted browser matrix was not invoked. Production business mutations are not needed to verify these two pages: after publication, verify exact automatic Cloud Run/cron and Worker releases, public asset hashes, and authenticated GET-only page checks. Sanitized production receipts are added after live verification; private cookies, keys, member screenshots and raw production HTML remain outside the repository.
