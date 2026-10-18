# Features console remediation evidence

Local verification of `/admin/features`, `/admin/badge-rules`,
`/admin/custom-emoji`, and `/admin/link-previews`, preserving the Imladris design.
The review and implementation use ADMIN §§3.6 and 9.4 and the subsystem runbooks.
Local test databases contain isolated, synthetic fixtures. The subsequent
production verification used read-only page requests; no production settings or
content were changed.

## Resolved requirements

| Requirement | Implementation and evidence |
| --- | --- |
| Confirm bulk badge changes | Read-only impact pages precede Backfill/Revoke. Typed confirmation and a current batch fingerprint are required by POST; missing/wrong/stale confirmation returns 422 before effects. [Backfill validation](mobile/remediation-backfill-confirmation-422.png), [Revoke impact](desktop/remediation-revoke-impact.png). |
| Correct automation guidance | The rule form explains manual Backfill and that Enable does not schedule awards. Preview discloses its 100-member cap; confirmation reviews the actual batch of up to 1,000. [Rule list](desktop/features-badge-rules-light.png). |
| Audit custom emoji changes | Create, replace, enable and disable record actor, entry ID and before/after state in the same transaction as the configuration write. Browser fixtures assert the four audit actions in order; integration tests assert snapshots. |
| Reach older previews | Filtered history has counts and previous/next pages; settings, board and row actions retain safe status/page return state. Browser fixtures create 51 additional records and reach the oldest entry on page 2. [History page 2](desktop/remediation-preview-history-page-2.png). |
| Keep board controls visible | The three-column board table fits its card at 1440, 901, 390 and 320 CSS pixels, including an opted-in private board. Every button stays visible and at least 44px high; state labels do not overlap actions. [Mobile detail](mobile/remediation-board-controls-390-detail.png). |
| Reject built-in shortcode collisions | `smile`, `heart`, `thumbsup`, `thumbs_up` and `tada` return 422 with form values preserved. Legacy entries remain disableable; re-enabling explains recovery under a new shortcode. [Reserved-name validation](mobile/remediation-reserved-emoji-422.png). |
| Enlarge Operations links | Browser geometry asserts that inventory Operations links have targets at least 44px wide and high. |
| Add flag/readiness filters | Flag-name search, effective state and readiness combine in a normal GET form. The no-JS journey verifies selected values, intersections, empty results and Clear filters. Totals and undeclared overrides stay inventory-wide. [Filtered inventory](desktop/remediation-filtered-flags.png). |

## Verification

Exact command results, source identity, asset identity and screenshot hashes are
recorded in `verification.json`. Successful command logs are under `validation/`.

- Full PHP suite runs through `composer test` with a fresh private test database.
- The four affected integration test files also run with native PDO prepares.
- Chromium runs `admin-features.spec.ts`, `features-console.spec.ts`,
  `features-remediation.spec.ts` and `link-previews.spec.ts` at desktop/mobile
  viewports, plus the existing Gate A badge lifecycle scenario.
- WebKit runs the four new remediation scenarios at desktop/mobile viewports.
- Existing console evidence checks light, twilight and system-dark registers;
  scoped axe checks cover the inventory, rule/preview forms, emoji form and link
  preview surfaces. The no-JS action journeys are separate from axe injection.
- Asset build/check, all asset tests and the Imladris generated-file guard are
  verified. The application presentation baseline and generated resource
  manifest are refreshed for the reviewed templates/CSS; design-system source
  tokens and `public/assets/imladris.css` are unchanged.
- Both CLI emoji consumers are exercised through render-cache dry run and
  Thread Intelligence status. Neither invokes a model or fetches preview URLs.
- Independent behavior and visual reviewers checked the final changes. Their
  constructor-compatibility and private-board label findings were corrected.

The mechanical design scan also reports existing patterns across `app.css` and
one advisory about the new filter's deliberate 1rem input size. It is retained
for readable native controls; this task preserves the existing visual system.

## Evidence limits

Viewport emulation is not a physical device or assistive-technology acceptance
test. Outbound preview fetching and production email delivery were not exercised.
No new provider calls were made.

## Production release

Application commit `17d0fef5805d8d18dec07c0f04fc54c826d3ee9a` was pushed to
`main` and independently deployed by Workers Builds and Cloud Build. The Worker
build succeeded and its version serves 100% traffic. Cloud Run revision
`retroboards-00034-t26` serves 100% traffic; all four cron jobs use the same
application image. `/healthz` reports the application and database healthy.
Sanitized deployment receipts are in [production/](production/).

All 57 application-release asset URLs returned HTTP 200 with matching SHA-256
hashes. After recording deployed version `50a62f5be30f6b09`, all 55 retained
manifest URLs also matched. Retention removed only two obsolete bundles. The
Imladris baseline and generated metadata were refreshed for those removals;
current application assets and design-system CSS are unchanged. The asset suite
again passed all 35 tests, with asset and Imladris checks passing.

Authenticated Chromium verification passed 37 checks across desktop, 390px,
320px and no-JavaScript contexts. All four Features routes returned HTTP 200;
combined filters, empty/reset behavior, inventory totals, 44px Operations targets,
and board-control visibility passed. There were no runtime errors or attempted
mutable/cross-origin requests. Production has no badge rules or preview records,
so eight populated-data checks were skipped; confirmation POSTs and multi-page
history remain covered by the local fixtures and tests above. Production forms
were not submitted. The temporary verification session was revoked afterward.

The [browser receipt](production/live-features-verification.json) includes
aggregate results and hashes for 32 private captures. Production screenshots and
session credentials are excluded from the repository. The earlier
`verification.json` remains the original local pre-commit snapshot; production
receipts identify the published application commit separately.
