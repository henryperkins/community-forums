# Next-step release qualification — 2026-10-23

This candidate combines the explicit hosted-browser coordinator, qualified PR92
Vite updates, and the two deferred admin UX findings. Application Vite is 8.3.5;
the runnable archived Imladris prototype uses 6.4.5. Missing JavaScript outputs
were regenerated with the deployed-release pins preserved. The new asset
version is `3aa554af4ef3f85a`.

## Local qualification

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

## Live CI configuration

The browser trigger and its GitHub push hook are disabled; the production
trigger and hook remain enabled. The trusted verifier now has only
`cloudbuild.builds.get` in its custom role, and its child-account ActAs grant
is removed. Sanitized applied and independently-read configuration receipts
are adjacent. CI control commit: `adab5335680c5a8599a6a4a4ecec83500d19e1fd`.
No browser builds appeared after publishing the control and PR92 branches.

## Publication boundary

PR92 merged as `4ef324bfebe16d1901e6fecaeeceefef7294d416`.
This record establishes local qualification and live CI configuration. The
complete hosted matrix and final automatic production deployments require
separate exact-commit receipts after execution.

The root npm audit's Zod 4.6.5 advisory through `@neon/config`/`@neon/env`
exists on the starting main and is unchanged by PR92; it remains separate
follow-up work. No production setting or member state is changed by these tests.
