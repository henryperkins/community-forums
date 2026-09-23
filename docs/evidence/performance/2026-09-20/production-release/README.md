# Production release verification — 2026-09-20

> Historical evidence from 2026-09-20, organized on 2026-09-23. Production state, timings, build identifiers and source line references describe that snapshot; they were not remeasured during cleanup. See the [provenance and archive inventory](../../../workspace-cleanup/2026-09-23/README.md).

Commit [`2d925557de4f78753e443caecb7eecb428250171`](https://github.com/henryperkins/community-forums/commit/2d925557de4f78753e443caecb7eecb428250171) was pushed to `main` and deployed by the existing Workers Builds pipeline. The exact commit's Cloudflare build completed successfully at 14:54:14 UTC. Worker version `5a7040fe-fbbe-488b-bfd2-bec025a39327` serves 100% of traffic. No infrastructure or database migrations changed.

The production homepage and `/healthz` returned 200. The homepage renders the new native rail control and references the new asset filenames. Six deployed assets, including the lazy rich-editor adapter, match the committed files byte for byte and retain immutable caching. See [deployment receipt](checks.json) and [live asset verification](live-verification.json).

## Signed-in production measurements

An authorized temporary staff session measured the same production thread as the original diagnosis. These are browser laboratory measurements on production, not field p75 results or a claim about every member route. The harness allowed GET/HEAD requests, sent no input, and observed no non-read requests during capture.

Chromium 149.0.7827.55; 390×844 mobile viewport, DPR 2, 4× CPU slowdown, 150 ms latency, 1.6 Mbps down / 750 Kbps up; 15 seconds of observation after each page load, 30 seconds between navigation starts. All four pages returned 200 and retained verified signed-in controls. Each warm run served 16 responses from browser cache.

| Sample | CLS | Unfiltered shift sum |
| --- | ---: | ---: |
| Original production warm | 1.036996 | 1.036996 |
| Deployed cold | 0.072978 | 0.116950 |
| Deployed warm 1 | 0.043972 | 0.043972 |
| Deployed warm 2 | 0.043972 | 0.043972 |
| Deployed warm 3 | 0.043972 | 0.043972 |

**The warm target below 0.1 is met in all three runs.** Chromium marked one cold shift as recent input despite no input being sent; the unfiltered sum above retains that distinction. No warm shifts were excluded. The existing production image-dimension issue remains outside this release.

[Measurement summary](measurements.json), [redacted raw traces](../../../workspace-cleanup/2026-09-23/README.md#archived-scratch) (`production-member-thread.json`, archived), and [capture harness](../browser-member-measure.cjs). No DOM text, account names, cookies, credentials, screenshots, or response bodies were recorded in these production artifacts. Topic slugs are redacted.

Two console errors match the pre-release production trace: the Cloudflare analytics beacon is blocked by the existing CSP, and `/composer/giphy-config` returns 404. These are pre-existing findings, not new release failures.

## Behavior and release checks

The live phone layout passed keyboard opening, Escape dismissal and focus return for both the board rail and Topic tools. With JavaScript disabled, the rail and full reply textarea remain visible, and keyboard activation of a board link navigates successfully. No non-read requests were sent. See [live accessibility checks](accessibility.json).

Before push, the full local PHPUnit suite completed with 2,956 tests, 22,331 assertions and zero failures (six existing deprecations, one skip). Imladris verification passed 24 tests / 296 assertions, and all eight asset tests passed. The committed [implementation evidence](../cls-fix/README.md) records the seven new browser regressions and existing affected browser checks.

Hosted checks have two unrelated limitations: GitHub's browser-evidence job could not start because the account is locked due to a billing issue; CircleCI reports missing configuration, as it also did on the preceding main commit. The Cloudflare production build succeeded independently.

The temporary production session was logged out, its old cookie was verified to no longer authenticate, and its local cookie file and authentication helper were removed. Other member sessions were left intact. See [cleanup verification](session-cleanup.json).

These post-deployment receipts are retained locally. The published release commit contains the implementation and its pre-release evidence; earlier raw diagnostic artifacts remain uncommitted.
