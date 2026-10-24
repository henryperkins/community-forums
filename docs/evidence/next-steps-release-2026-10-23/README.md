# Next-step release qualification — 2026-10-23

This release combines the simpler release contract and optional single hosted build, qualified PR92
Vite updates, and the two deferred admin UX findings. Application Vite is 8.3.5;
the runnable archived Imladris prototype uses 6.4.5. Missing JavaScript outputs
were regenerated with the deployed-release pins preserved. The new asset
version is `3aa554af4ef3f85a`.

## Initial local qualification (before ADR 0051)

- Full `composer test`: 3,364 tests, 29,805 assertions, one skip; exit 0.
- Asset reproducibility and all 35 asset/Worker tests pass.
- Imladris freshness passes after reconciling the reviewed ADMIN/template surface.
- Focused Chromium composer journeys: nine passed, five declared mobile skips,
  zero retries; lazy rich loading, Markdown submission, caret marks and images.
- Integrated admin journeys: ten passed, desktop and 390px Chromium, including
  explicit no-JavaScript confirmation/bulk flows, UTC date validation, draft
  preservation and escaped readable audit details. No serious/critical axe
  violations in the changed audit region. Screenshots are under `browser/`.
- Prototype qualification: clean install/build, four packaging tests, desktop
  and narrow index/board/thread navigation; no prototype audit advisories.

Logs are adjacent. PHPUnit used private `retroboards_pr91_unit_20261023`;
browser evidence used private `retroboards_pr91_browser_20261023`, port 8093,
and isolated rate-limit/package stores. Browser results are emulation, not
physical-device evidence.

Independent reviewers found no remaining P1/P2 findings in the final CI or
admin implementations. The CI review's two findings were fixed before live
configuration. The admin reviewer additionally passed 19 UTC parser cases
under a non-UTC process timezone.

## Initial CI configuration (historical; superseded below)

The browser trigger and its GitHub push hook are disabled; the production
trigger and hook remain enabled. The trusted verifier now has only
`cloudbuild.builds.get` in its custom role, and its child-account ActAs grant
is removed. Sanitized applied and independently-read configuration receipts
are adjacent. CI control commit: `adab5335680c5a8599a6a4a4ecec83500d19e1fd`.
No browser builds appeared after publishing the control and PR92 branches.

## Publication boundary

PR92 merged as `4ef324bfebe16d1901e6fecaeeceefef7294d416`.
ADR 0051 replaces the intermediate two-build acceptance protocol. Final local
qualification is below. The earlier hosted run failed and has no acceptance
claim. Exact final production receipts are stored as linked release artifacts
after publication, without another proof-only main commit.

The root npm audit's Zod 4.6.5 advisory through `@neon/config`/`@neon/env`
exists on the starting main and is unchanged by PR92; it remains separate
follow-up work. No production setting or member state is changed by these tests.

## PR92 production proof and retention

Both automatic production lanes completed on the exact PR92 merge SHA.
Cloud Build `d41ae944-51f7-4992-b07f-1a68e2e1f9a5` succeeded; Cloud Run revision
`retroboards-00057-qpx` is Ready with 100% traffic, and all four cron jobs plus
the monitoring job use that merge image. The Workers Build succeeded on the
same SHA; active Worker version `557c444d-6c41-4b81-8b32-468eccdf50c4` serves 100%.
Public health/login return 200, health reports application/database `ok`, and
all 60 manifest assets matched their SHA-256 hashes. Sanitized receipts are in
`pr92-production/`.

Only after that live proof, asset release `3aa554af4ef3f85a` was recorded. The
retention window is now `3aa554af4ef3f85a`, `432ce95c7cdbe032`, and
`d558418fe08d412c`. Two files exclusive to the retired oldest release were
removed; all 58 remaining files were hash-verified. `retention.json` records
the exact removals. No current asset bytes or application behavior changed.
Post-retention asset reproducibility and all 35 asset tests pass, as does
`composer verify:imladris` (24 tests, 305 assertions). Application digest:
`31df86217edb7484ae88a56009fd123127b170f577b43f8c8006f21e3253268a`.

The new admin journeys are included in the hosted capture command, alongside
existing admin-remediation and dashboard coverage.

## Final simpler-contract qualification

Owner-approved ADR 0051 removes the application-wide Imladris digest, its baseline
file and follow-up-commit rule. Generated CSS/font reproducibility and concrete
behavioral checks remain. Optional broad CI is one restricted build; the separate
verifier/coordinator/enforcer and their obsolete contracts are removed. Test exit
status is independent of diagnostic packaging and upload.

- Final full PHP suite: **3,361 tests, 29,761 assertions, one skip**, exit 0,
  1m35.605s. A final diagnostic-fallback metadata adjustment was additionally
  verified by the focused CI contracts; member/admin application code is unchanged.
- CI contracts: **13 tests, 110 assertions**, including real Docker-status
  propagation and successful tests with missing diagnostics.
- Asset reproducibility and **all 35 asset/Worker tests pass**. All 58 manifest
  files match locally; version `3aa554af4ef3f85a` and deployed pins are preserved.
- Playwright discovery proves **708 → 608 scheduled capture/account cases**, with
  **all 608 unique cases preserved** and no duplicates. The secondary matrix
  drops **20 → 14 project batches**. This is selection proof, not a broad run.
- The old hosted test run failed on `datetime-local` fill normalization of zero
  seconds in an existing account test. Both provider builds were already terminal
  FAILURE when cancellation was requested; the local coordinator was stopped.
  The test uses native minute format now; **six focused account/suspension
  journeys pass** on desktop/mobile. No production validation changed for this fix.
- One final independent read-only review found no P1/P2 or meaningful P3.

Sanitized logs and receipts are in `release-contract/`. The old verifier account
is disabled, its custom role deleted and project/artifact-bucket grants removed.
Historical receipt objects retain their existing lifecycle. Independent reads
confirm the browser trigger disabled with `filter=false`, its webhook inactive,
and production trigger/webhook active. The new build uses only the restricted
test identity. No new hosted run was launched for this release.

The earlier focused admin/composer render evidence remains applicable: their
controllers, services, templates, Worker and public asset bytes have no changes
from qualified candidate `7c0305c5`. Final publication receipts are stored at
`/home/ubuntu/.local/state/community-forums/next-steps-20261023T225540Z/final/`
and indexed by the external `FINAL.md`; this directory is outside the repository.
