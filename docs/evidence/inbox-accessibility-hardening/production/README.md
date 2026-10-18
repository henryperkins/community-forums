# Inbox production release

Application commit `c392ea8444c0d6080762fe9413062479e0eba3ac` was pushed to
`main` and verified on 2026-10-18. It combines the reviewed Inbox fixes with
the newer Boards work; [integration evidence](../integration/README.md) records
the composed PHP, Chromium, WebKit and asset checks.

| Surface | Verified result |
| --- | --- |
| Cloudflare Workers Builds | Build `9c2e359f-3b9b-423c-a9e5-a12c97b3e5df` succeeded for the exact application SHA on `main` |
| Active Worker | Version `c3087830-ba4a-4e73-975c-2b53180490f3` receives 100% traffic; its build maps to the application SHA |
| Cloud Build | Build `8d374fb6-3649-4bc9-b718-ba15fef94e69` succeeded, including service and job updates |
| Cloud Run | Revision `retroboards-00025-z7q` is Ready at 100% traffic; its image digest matches the application SHA's registry tag |
| Scheduled job definitions | Four cron jobs and the monitor job reference the same application image |
| Public endpoints | Canonical health returns 200 with `status=ok` and `database=ok`; login returns 200 with the current stylesheet; guest Inbox redirects to login |
| Private origin | Direct unauthenticated access returns the expected 403 |
| Live assets | All 56 manifest URLs return 200 and match SHA-256; the current stylesheet also passes HEAD and conditional GET (304) |

The legacy URL `https://boards.hperkins.blog/healthz` returns 404, where the
deployment runbook expects a 301 to the canonical health URL. This release does
not change routing code or configuration. The alias was not checked before the
push, so the finding's introduction date is unknown. Canonical application
health and asset verification pass independently of this finding.

After live verification, asset version `2371e926206bda24` was recorded under
ADR 0041. The new window retains it with `326ae04cd673029a` and
`be1d396948b3adcc`, removing the oldest release's exclusive stylesheet
`app-style-YO-75cSC.css`. The current CSS/JavaScript bytes are unchanged.
The allowlist therefore contains 55 URLs after recording. The Imladris
application digest was refreshed because the expired delivery file is part
of its measured surface; the design-system bridge is unchanged.

These sanitized receipts describe the application release before the asset
bookkeeping commit. That later commit requires its own automatic deployment
and public checks before release closeout. No authenticated production member
actions or manual scheduled-job executions were performed. Local browser
evidence does not establish physical-device or assistive-technology acceptance.

- [Release summary](summary.json)
- [Worker build and active version](worker.json)
- [Cloud Build](cloud-build.json)
- [Cloud Run revision, digest, traffic and job definitions](cloud-run.json)
- [Public endpoints and legacy-host finding](public-endpoints.json)
- [Every live asset hash](live-assets.json)
- [Asset-retention record and source parity](asset-retention.json)
- [Checks after the retention record](bookkeeping-checks.json)
