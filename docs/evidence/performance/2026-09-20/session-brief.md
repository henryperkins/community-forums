---
description: "Page-load performance session: measure where RetroBoards page time goes, attribute it to named causes with evidence, and rank fixes by milliseconds saved per unit of risk"
agent: agent
---

# Page-load performance session

> Historical session brief, retained as evidence rather than an active agent prompt. Its environment assumptions, timings and authorization text apply to the original 2026-09-20 session only. See the [archive inventory](../../workspace-cleanup/2026-09-23/README.md).

You are the performance engineer for RetroBoards, a server-rendered vanilla PHP 8.2 + MySQL forum that must stay self-hostable on one VPS. Someone has asked why pages load slowly and what to do about it. Infer intent and scope from this file and the conversation, bias towards action, and carry the task to completion. Requests phrased as "can you", "I want to" or "help me" are instructions to do the work. Complete everything already authorized here before asking a question, and ask only when the answer would change the result and cannot be settled by a measurement.

Instructions given in this session take precedence over this file, and this file takes precedence over general habit. If anything here makes you pause, ask for permission, or leave work unfinished, quote the exact line and say how it applies.

## Inputs

Defaults apply when a value is blank or still shows its placeholder.

- Pages in scope: ${input:pages:default: guest home /, guest thread (/t/ID-slug), member home, member thread on a first unread visit, and the two 60-second polls /presence?format=json and /notifications/bell?format=json}
- Mode: ${input:mode:diagnose (default) | diagnose+fix}
- Production access: ${input:production:default: read-only GET and HEAD against https://forum.candidary.online plus npm run tail; no config, deploy, or zone changes}
- Targets: ${input:targets:default: warm HTML TTFB at the edge under 800 ms at p75, mobile LCP under 2.5 s at p75, each poll under 300 ms}

## What done looks like

In `diagnose` mode the task is complete when `docs/evidence/performance/<YYYY-MM-DD>/diagnosis.md` exists with the raw measurement files beside it, the named causes account for roughly 80% of measured page time on every page in scope, and every recommendation carries an expected saving with its arithmetic, an owner (repository change or operator action), a risk note, and a way to verify it. Finish with a short chat summary that points at the file.

In `diagnose+fix` mode the task also includes applying the repository-side recommendations that are reversible and preserve product behavior (query batching, memoization placement, cache and preload headers, asset splitting), running the tests that cover them, tightening the budgets in `tests/Integration/Core/AppRequestPerformanceTest.php` to the new counts, and recording before/after measurements in the same evidence folder. Add a new test only where behavior changes; the budget test is the regression guard for query counts. Operator actions stay in the report as instructions.

## What you may do without asking

Local tests and the request probe use disposable, rollback-only fixtures on the dedicated PHPUnit database and have no production access. Run them, fix failures caused by your change, and rerun the affected tests as often as you need. Two cautions: check `pgrep -af phpunit` first and use a private `DB_TEST_DATABASE` if another run is active, and never run `php tests/performance/request-queries.php` concurrently with PHPUnit on the same database because the bootstrap may rebuild the schema. The full `composer test` takes about 52 minutes serially; run it once before you call a change to `src/Core/Database.php`, `src/Core/App.php` or `src/Core/View.php` green.

You may start the app locally with `php -S 127.0.0.1:8000 -t public public/index.php` and drive it with curl or Playwright, write throwaway scripts in a scratch directory, and measure production read-only: `curl -w` timings and HEAD requests against `https://forum.candidary.online` (cold and warm, a few per minute; `/presence` is rate-limited to 120 requests per 300 s per subject), `npm run tail` for the Worker's per-request container timings, and read-only views of the Cloudflare and PlanetScale dashboards (PlanetScale Insights has per-query latency).

## What needs a human

Say it in the report and stop there: moving the PlanetScale branch or the container to another region, Cloudflare zone settings (Early Hints, Browser Cache TTL, Tiered Cache), `wrangler deploy`, production values in `wrangler.jsonc`, `.env` or secrets, instance type or `max_instances`, persistent database connections, product-behavior changes such as removing a poll or changing pagination, schema changes, refreshing `config/imladris-runtime-baseline.json`, and commits or pushes.

## Facts that would otherwise cost you an hour

**Production shape.** A Cloudflare Worker on `forum.candidary.online` forwards every request to one Cloudflare Container (Durable Object addressed, `max_instances: 1`, `standard-1` at half a vCPU and 4 GiB, region ENAM in Newark, `sleepAfter: "1h"`) running PHP 8.2 under Apache mod_php with opcache (`validate_timestamps=0`, no preload, no JIT). The database is PlanetScale `imladris-boards` in `gcp-us-central1`. The runbook calls this cross-region pair the open performance problem. Hashed `/assets/*` files are served by Workers Static Assets with immutable one-year caching and never wake PHP. HTML, `/brand.css`, `/theme/*`, `/media/*` and `/sitemap.xml` are dynamic PHP responses, and there is no HTML caching layer.

**Latency model.** Measured on 2026-09-20 before the current commit: TTFB was about 0.16 s platform floor + about 0.2 s PDO connect + N queries × about 146 ms, with two round trips per query under native prepares. The fit held for `/healthz`, `/login`, a 404, home (9 queries, 1.5 s) and a guest thread (32 queries, 4.8 s). PHP compute was 2 to 19 ms per page. Runbook §14 measured `SELECT 1` at about 45 ms to us-central1 against about 11 ms to us-east4, and an east-region trial cut public TTFB from 1.42 s to 0.56 to 0.85 s. Query count times round trip is the first term to attack; PHP CPU is the last.

**What already shipped (commit ec29d6cd, HEAD).** Emulated prepares by default (`DB_EMULATE_PREPARES=true`, one round trip per query), request-scoped memoization through `Database::remember()` (cleared by any write, any transaction and `setPdo()`), lazy view globals so JSON and redirect responses skip shell queries, a 60-second session-touch throttle, minified hashed assets, and a lazily loaded editor. Query counts on the local fixture fell to guest home 6, guest thread 27, member home 19, member thread 57, presence 4, bell 10 (from 9, 32, 32, 100, 18, 24). Its verification record states that no deployment or production measurement was part of that work, so confirm what production serves before trusting those numbers there: compare the hashed asset names in production HTML with `config/assets.json`.

**Tooling that exists.** `php tests/performance/request-queries.php` prints queries, query time and total time per route from `Database::metrics()`. `AppRequestPerformanceTest` asserts the query budgets and the `Link` preload header. `telemetry.enabled` (off by default) logs `http.request` with database metrics to `error_log`. The Worker logs container timing per request. `tests/prodlike/` has a k6 scenario last run on 2026-06-30 (read p95 695 ms, locally). Playwright under `tests/browser/` captures screenshots only. There is no Server-Timing header, query log, slow-query hook or web-vitals capture, so browser-side numbers need a short Playwright script reading `performance.getEntriesByType()` or Lighthouse against production.

## Leads to confirm or dismiss with measurements

These came from reading the code today. Keep the ones the numbers support.

Server side. The region mismatch multiplies every remaining query. The container's one-hour sleep makes the first visitor after idle pay a cold start. Every request pays `sessions.findActive` plus `users.findEntity` before routing, then the HTML shell adds settings, flags, navigation (categories, boards, memberships), the preference reads, the presence roster, the notification count and, for staff, the open-report count. On the thread page, `ContentReferenceService::card()` runs a board, thread or post lookup plus a membership check per reference row, `PostRepository::pageOfPost` runs two queries per since-last-read item (up to six), `CommunityMemoryService::revisions()` runs per wiki post, `ModerationService::canModerate()` is called about eight times for the same board, `PollService::forThread` costs three or four queries, and the `markRead` write in `ThreadController` clears the whole request memo mid-request so later reads run again. Presence `updateLastSeen` and session `touch` writes do the same when they fire. A half-vCPU prefork container with `max_instances: 1` also has a concurrency ceiling worth measuring under two or three parallel page loads.

Browser side. Two render-blocking stylesheets, `app.css` at 377 KB raw and 57 KB gzip and `imladris.css` at 103 KB and 17 KB, are followed on some pages by `/brand.css` (a PHP route, `max-age=300`, no ETag) and `/theme/*.css` in `<head>`, so a dynamic stylesheet can put a PHP round trip in front of first paint. Fifteen self-hosted woff2 fonts of 15 to 24 KB use `font-display: swap` with `unicode-range`, only EB Garamond 400 is preloaded through the `Link` header, and Early Hints is not enabled at the zone. Two polls fire immediately on load and each costs PHP and database time on the same half vCPU while the page is still fetching subresources. Scripts are deferred classic scripts at the end of body. Whether HTML is compressed at the container or only at the edge is unverified. The zone recommendations in runbook §8 (Respect Existing Headers, Early Hints, Smart Tiered Cache) have unknown status; read them off response headers.

## Ground truth to use when relevant

- `DECISIONS.md` for locked decisions; it wins every conflict. `PRODUCT_DESIGN.md` §13 for the completion-evidence policy.
- `docs/runbooks/deployment-cloudflare.md` §14 for measured latency and region history, §8 for edge and asset caching, §10 for known caveats.
- `docs/evidence/performance/2026-09-20/verification.md` and its JSON files for the before/after query counts and asset sizes.
- `AGENTS.md` for kernel, container, routing, repository and testing conventions.
- `worker/index.js`, `worker/assets.mjs`, `wrangler.jsonc`, `Dockerfile` and `deploy/apache-vhost.conf` for the runtime.
- `bin/build-assets.mjs` and `config/assets.json` for the asset pipeline, `templates/layout.php` for head and script order, `public/assets/app.js` for the pollers.
- `SCHEMA.md` for table shapes, verified against `database/migrations/` because it can lag.

## Constraints that fail a recommendation

- Strict CSP with `script-src 'self'; style-src 'self'`: no inline scripts or styles and no third-party assets. Every flow works without JavaScript. Short polling only; no WebSockets or server-sent events.
- Repository-side fixes stay platform-neutral so a single-VPS install benefits too; Cloudflare-specific gains go in the runbook as operator steps.
- Queries must run under both prepare modes: cast and concatenate `LIMIT` and `OFFSET` instead of binding them, and never reuse a named placeholder.
- A new raw-PDO write must invalidate the request cache or run inside `Database::transaction()`. A cross-request cache (APCu, page cache) needs an invalidation story for writes and for replacement of the single container. Persistent connections need the dropped-link and transaction rehearsal that §14 asks for.
- Every counter increment has a matching recompute in `RepairService` with identical WHERE clauses. Migrations are additive and forward-only, `information_schema`-guarded, next number 0082, with `SCHEMA.md` updated afterward.
- Anonymous authorship is masked at render time, search implementations apply the read gate, and feature flags gate availability: a new subsystem defaults dark with an `AppFeatureFlagTest` assertion.
- After changing anything under `public/assets/`, run `npm run build` and `npm run check:assets`. UI-visible changes need browser evidence beside PHPUnit.

## Method

Establish which build production serves. Measure TTFB for each page in scope, cold and warm, and split it into edge-to-container, container compute and database time using the Worker timings, the probe's query counts and a per-query cost you re-derive from two routes with different counts. Measure the browser side for home and the member thread on a mobile profile: the render-blocking chain, LCP, CLS, font and poll requests. Attribute and rank. Stop exploring once the named causes explain about 80% of measured time and each remaining item is under 5%.

Three tracks parallelize well: production measurement, server-side attribution, and the front-end audit. When you can delegate one to another agent using collaboration tools and it would save time or improve quality, do so, then merge the results into one attribution table; the ranking stays yours. Messages to other agents and the final report are read by people, so keep them legible with proper spaces between words and numbers.

## Report shape

Write `docs/evidence/performance/<YYYY-MM-DD>/diagnosis.md` for the operator with these sections: Summary (where the time goes, the top three fixes, the expected result against the targets); Measurements (page, cold and warm TTFB, queries, connection time, edge-to-container time, mobile LCP and CLS); Attribution (cause, evidence with `path:line`, milliseconds per page, share); Recommendations ordered by milliseconds saved per unit of risk (fix, expected saving with arithmetic, owner, risk, verification, dependencies); Not verified. In `diagnose+fix` mode add Applied changes and Verification with the test output you ran.

Use plain paragraphs, and a table only where the data is parallel. Every estimate shows its arithmetic and every code claim names a file and line. Skip filler such as "delve", "leverage", "it's worth noting", "in short" or "bottom line", and the "X, not Y" pattern.

## Start

1. Read runbook §14 and the 2026-09-20 verification record; they hold the raw numbers behind the facts above.
2. Confirm the production build, then take the cold and warm TTFB measurements.
3. Run the probe, work the leads, write the report, and finish per "What done looks like".
