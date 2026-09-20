# Repository performance verification — 2026-09-20

This record covers repository changes based on `a90a1012`, measured against the
local MariaDB test fixture. No production database move, deployment, or
Cloudflare zone setting change was performed.

## Database requests

`php tests/performance/request-queries.php` uses the dedicated PHPUnit database
through `tests/bootstrap.php`, creates a rollback-only fixture, and
measures `App::handle()` directly. Do not run it concurrently with PHPUnit on
the same database; bootstrap may rebuild its schema. The fixture has an
installed marker, one public board and thread, and a member whose
presence/session timestamps are fresh. The member
thread request is that member's first unread visit, including its read-state
write. This differs from the production report's content and visit history.

| Request | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| Guest home | 9 | 6 | 33% |
| Guest thread | 32 | 27 | 16% |
| Member home | 32 | 19 | 41% |
| Member thread, first unread visit | 100 | 57 | 43% |
| Presence poll | 18 | 4 | 78% |
| Notification bell poll | 24 | 10 | 58% |
| Composer preview | 18 | 3 | 83% |

Every measured response returned HTTP 200. Raw evidence is in
[`queries-before.json`](queries-before.json) and
[`queries-after.json`](queries-after.json). An injected local connection means
connection counts are zero; the timing fields do not estimate remote TLS,
network latency, or production TTFB.

Request caching covers settings, capability maps, preferences, board
membership/moderator lists, ordered boards, and thread-intelligence jobs.
Writes and transaction boundaries clear cached reads. Locking reads bypass
memoization, and request boundaries prevent stale permissions across requests
or CLI jobs. HTML globals initialize on the first render, leaving successful
JSON/redirect responses free of shell queries. Tests preserve HTML errors,
feature gates, CSRF, session revocation, and session expiry.

PDO uses emulated prepares by default; parameter round-trip tests exercise
native and emulated modes with quoted text, Unicode, binary values, nulls,
booleans, numbers, and named/positional placeholders. Both modes reject a
second SQL statement. `DB_EMULATE_PREPARES=false` is the compatibility fallback.

## Asset delivery

[`asset-sizes.json`](asset-sizes.json) records raw and gzip level-9 sizes. The
comparison uses the prior checked-in source/editor assets and the generated
delivery files, not a production transfer trace.

| Payload | Before, gzip bytes | After, gzip bytes |
| --- | ---: | ---: |
| Two core stylesheets | 139,573 | 74,101 |
| app.js and composer.js | 48,673 | 26,535 |
| All six core CSS/JS assets | 193,100 | 103,832 |
| Editor entry, requested only when needed | 141,683 | 794 |
| Editor adapter, requested only when needed | Included above | 137,562 |

The PHP layout, public preload headers, and Worker allowlist share
`config/assets.json`. Hashed CSS, JavaScript, chunks, and fonts use immutable
one-year cache headers. Mutable source URLs revalidate unless their version
matches the current build. Only reviewed public assets are staged for the
Workers Assets binding; `/brand.css`, `/theme/*`, and application routes remain
dynamic. Runtime tests exercise real binding GET, conditional 304, HEAD, and
404 responses, as well as range/header preservation and container bypass.

The build minifies classic scripts separately from the module graph, checks
their syntax as classic scripts, and preserves strict mode. The core composer
loads the editor only when a real input exists, including a later inbox
insertion. It owns the import promise for both entry and adapter, so failure
of either download leaves a working enhanced textarea and server submission.

## Validation

- Full `composer test`: **2,956 tests, 22,331 assertions, zero failures or
  errors**, exit 0. One down/up migration rehearsal intentionally requires a
  separate empty database and was skipped. Six existing PHP 8.5 deprecations
  come from the unchanged TLS constant/reflection and GD cleanup code. See
  [`phpunit.txt`](phpunit.txt).
- Deployment-runtime check: **37 tests, 271 assertions passed on PHP 8.2.33**,
  covering both prepare modes, cache invalidation, HTTP budgets, guest CSRF,
  session expiry/revocation, setup locking, TLS options, and manifest fallback.
  See [`php82.txt`](php82.txt).
- `npm run check:assets`, all **8** `npm run test:assets` cases, and
  `composer check:imladris` passed. Wrangler's deploy dry-run also passed;
  it did not deploy anything.
- The complete Docker build passed. A check inside image `98a17dd31cc6`
  verified all **50** published asset digests and resolved all 23 manifest
  URLs. Its manifest version is `6ba4ffef9eadf890`, matching the checkout.
- Final browser runs passed **63 checks**: 37 desktop and 26 mobile, with 17
  viewport-specific skips. They cover entry/chunk download failures, rich
  editing, uploads, later inbox insertion, and JavaScript-disabled submission.
  An initial mobile toolbar hover timed out while the button moved; three
  isolated rechecks and the clean mobile suite passed without changes. The
  failure and reruns remain recorded in
  [`asset-validation.json`](asset-validation.json) and the browser logs.
- Source and minified CSS produced identical screenshots for login and a board
  at desktop and mobile sizes; see
  [`css-visual-parity.json`](css-visual-parity.json). Entry-failure screenshots
  are available for [desktop](desktop-failed-entry-textarea.png) and
  [mobile](mobile-failed-entry-textarea.png).

The full PHP command was:

```sh
COMPOSER_PROCESS_TIMEOUT=0 RB_TEST_FRESH=1 MAIL_DRIVER=sendmail MAIL_FROM= composer test -- --display-deprecations --display-skipped
```

The mail environment overrides provide the unconfigured default transport
expected by the suite; they do not modify `.env`. Database test processes
were serialized. Machine-readable PHP/container results are in
[`backend-validation.json`](backend-validation.json).
Browser fixtures were restored and temporary browser servers were stopped.

## Deployment follow-up

The production branch is still configured in `gcp-us-central1`. Region
colocation, Browser Cache TTL respecting origin headers, and zone Early Hints
enablement remain operational work described in the
[Cloudflare runbook](../../../runbooks/deployment-cloudflare.md#14-measured-latency--the-open-performance-problem).
Repository preload headers alone do not enable HTTP 103 at Cloudflare.

Persistent connections and cross-request APCu/page caches remain deferred.
Rate-limit files now use local storage in the single container; replacement of
that container resets the ledger. Public HTML caching is not enabled.
