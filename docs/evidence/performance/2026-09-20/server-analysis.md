# Server attribution — 2026-09-20

> Historical evidence from 2026-09-20, organized on 2026-09-23. Production state, timings, build identifiers and source line references describe that snapshot; they were not remeasured during cleanup. See the [provenance and archive inventory](../../workspace-cleanup/2026-09-23/README.md).

The current repository reproduces the shipped budgets exactly. The expensive remaining unit is a sequential database exchange: guest home uses 6 queries, guest thread 27, member home 19, and a member's first visit to a previously unread thread 57. A returning reader with one new reply uses 62; six new replies use 72. The six-item since-last-read panel alone issues 12 position queries. This session changed no product code, settings, deployment, or schema.

`server-request-probe.json` is the existing probe's fresh run. `server-phpunit.txt` records **7 tests / 56 assertions passed** on PHP 8.5.4. The probe and PHPUnit were serialized after checking for other PHPUnit processes. The instrumentation ran against the dedicated `retroboards_test` database. All fixture changes were rolled back; cleanup checked that the fixture users no longer existed and no transaction remained open. No production connection was opened by the server-attribution probe.

## Measurements and reproducibility

`server-attribution.php` loads an instrumented copy of `Database` into memory and records SQL, hashed parameter identity, original call sites, cache hits, and populated cache invalidations. It does not rewrite the application class. `--plain` runs the same scenarios against the actual uninstrumented class. Run it separately from PHPUnit because both use the test bootstrap. `server-attribution-raw.json` contains complete traces; `server-attribution-summary.json` groups **100% of measured queries** using each individual call stack and records their local SQL time. Each group's `query_sequence` links its assignments to the unchanged raw trace; a shared SQL origin does not imply a shared caller. `server-uninstrumented.json` contains five independent runs and their medians. Timings exclude connection establishment because TestCase injects an existing PDO. Local MariaDB timings are not production TTFB forecasts.

| Scenario | Queries | Median local SQL ms | Median local total ms |
| --- | ---: | ---: | ---: |
| Guest home | 6 | 1.507 | 8.704 |
| Guest thread, one opening post | 27 | 3.456 | 11.738 |
| Member home, activity fresh | 19 | 3.296 | 7.147 |
| Member thread, first visit | 57 | 6.454 | 12.636 |
| Member thread, repeat, already read | 58 | 7.053 | 11.022 |
| Member thread, one new unread reply | 62 | 7.348 | 12.080 |
| Member thread, six new unread replies | 72 | 8.239 | 16.005 |
| Presence poll, activity fresh | 4 | 0.450 | 0.925 |
| Bell poll, activity fresh | 10 | 1.573 | 2.677 |
| Member home, stale session and presence | 22 | 3.528 | 5.549 |
| Member thread repeat, stale activity | 61 | 7.328 | 11.302 |
| Presence poll, stale activity | 7 | 0.787 | 0.934 |
| Bell poll, stale activity | 13 | 1.956 | 2.203 |
| Guest `/healthz` | 1 | 0.071 | 0.195 |
| Guest `/login` | 5 | 0.460 | 1.968 |
| Guest missing route, HTTP 404 | 4 | 0.346 | 2.217 |

All other responses were HTTP 200. Scenario order affects PHP class loading: the first guest thread includes cold class loading while later requests reuse loaded classes. This explains why stale-activity samples can take less local wall time despite doing more SQL. The counts, rather than those cross-scenario local timing differences, are the reliable comparison. Median non-SQL time is 6.221 ms for the first member thread and 7.605 ms for the six-unread case. This run did not profile those milliseconds into individual PHP methods.

The guest thread stayed at 27 queries with one plain reply or six plain replies. A separate staff-authored opening post containing a cached Markdown image at `/media/1` also used 27 queries, 2.878 ms SQL, and 5.293 ms total (`server-attribution-staff-image-raw.json`). An image in the stored post HTML adds no SQL during the HTML render; its later media request is separate.

## Where the queries go

Guest home is settings/setup 1, the directory's categories/boards/topic signals 3, theme state 1, and presence roster 1. The sources are `src/Repository/SettingRepository.php:85`, `src/Service/NavigationService.php:100`, `src/Service/NavigationService.php:206`, `src/Repository/PackageThemeRepository.php:110`, and `src/Repository/UserRepository.php:491`.

Guest thread is settings 1; canonical redirect/read gate 2; post count, list, participants and participant count 4; reactions, emoji palette, content references and link previews 4; workflow history, assignment and tags 3; Living Brief/refresh eligibility 8; poll existence 1; navigation 2; theme 1; presence 1. These sum to 27. `ThreadController.php:47`, `:98`, `:139`, `:159`, `:193`, `:204`, `:338`, `:371`, `:415` and `:460` identify the entry points. The eight Living Brief reads are detailed in the JSON trace, including two identical eligible-post counts. The member first-render trace has **nine** Living Brief/eligibility reads: those eight plus the membership refill after cursor invalidation. Its earlier membership lookup belongs to `ThreadReadService`, so the canonical read gate has two reads. Individual-stack regrouping also keeps the heartbeat's settings read separate from the later settings refill. All 27 scenario totals and raw timings are conserved.

Member home adds two authentication reads, preferences, membership, unread navigation counts, moderator scope, five notification-scope/count reads, and personal folders/saved feeds. Presence is two identity reads, one settings read and one roster query when activity is fresh. The fresh bell is two identity reads, one settings read and seven notification model/scope queries. The JSON summary gives every group, SQL origin, count, and measured local duration.

`src/Security/Session.php:62` and `:67` perform the session and user reads on every authenticated request. At `:80` an activity age of at least 60 seconds adds the session touch. `src/Core/App.php:522` separately checks presence freshness and writes at `:539`. In the stale fixture those two writes add two queries, and the presence write invalidates the settings read made to check the presence flag, adding a third query. The session write happens before populated request caches; it does not itself cause the observed settings refill. Thus 4/10 are fresh-activity poll budgets, and 7/13 are measured stale-activity budgets. The first request may refresh activity for the next; simultaneously started polls can both observe stale timestamps.

`ThreadController.php:243` calls `ThreadUserRepository::markRead` even on an already-read visit. `src/Repository/ThreadUserRepository.php:46` does three SQL statements for first/repeat visits (candidate, insert/no-op, locked current cursor) or four when advancing an existing cursor. Its transaction empties five populated cache keys: settings, preferences, board membership, board moderators, and the role-capability map. All five are later reloaded. NotificationVisibilityService additionally reads membership and moderator rows with raw SQL at `src/Service/NotificationVisibilityService.php:39` and `:43`, duplicating reads already available through the memoized repositories.

**Transaction exchanges are absent from the query counter.** `src/Core/Database.php:310` and `:313` call PDO begin/commit directly. The outer TestCase transaction means the probe takes the nested fast path at `:306` and does neither. A real successful member thread GET normally adds BEGIN and COMMIT exchanges beyond the reported 57/58/62/72 SQL counts. Those exchanges must be included in any production member forecast; their exact deployed protocol latency was not measured.

## Content-dependent findings

Six new unread replies raised the returning-member count from 58 to 72: one context upsert, one cursor UPDATE, and twelve position queries. `SinceLastReadContextService.php:81` writes the generated context; `ThreadController.php:250` loops over six items; `PostRepository.php:214` and `:224` fetch each target and rank it. The count increase is `1 + 1 + 6 × 2 = 14`. The original first-visit fixture has no cursor, so the context service returns after its first lookup and misses this case.

Six references to the same target thread raised guest 27→33 and member repeat 58→64. Each target is looked up again at `ContentReferenceService.php:164`; the membership set is already memoized, so six more membership queries do **not** occur. Batched/deduplicated target resolution must retain deletion, pending-state, and per-board read checks.

Six wiki posts added no revision queries for an ordinary member. For an admin on a wiki-enabled board they raised an otherwise identical repeat visit 59→65. The gate is `ThreadController.php:445`; the per-post load is `:449` / `CommunityMemoryService.php:373`. Revision batching benefits curators, rather than every reader.

A poll raised guest 27→28 and member repeat 58→62. An existing poll costs two reads for a guest and **five** for a member: poll, options/counts, vote existence, selected option IDs, and another joined thread load. See `PollService.php:137`, `:141`, `:142`, `:148` and `:166`. A non-existent poll costs one read. The repeated moderation checks already share a board-moderator list and capability map; eight calls do not mean eight independent moderator queries. The observed duplication comes from the cursor invalidation and the separate notification scope.

## Production corroboration and limits

`server-production-query-reconciliation.json` analyzes the read-only Insights snapshot collected by the production track, `production-window-insights.json`, without further production access. The observed 12:42–12:47:30 UTC frequencies match five home loads, three login loads, six guest thread loads, and three health checks. Twenty thread-only normalized patterns ran six times each, and the eligible-post count ran twelve times: `20 + 12/6 = 22` thread-specific reads. The five shared shell reads give `22 + 5 = 27` queries per guest thread. The shared settings/categories/boards/theme reads each ran fourteen times (`5 + 3 + 6`), presence eleven (`5 + 6`), and directory signals five. These frequencies strongly corroborate the current 6/5/27/1 route counts. Aggregated Insights is still not a request-correlated SQL span log.

Summing each route's per-pattern mean server execution time gives **15.360 ms home, 59.820 ms guest thread, 10.679 ms login, and 0.160 ms health**. The SQL executes quickly inside the database. Those sums exclude transport, connection establishment, application work, and time outside Insights' execution measure. They do not justify a schema or index recommendation for this small production dataset.

The production track measured median container timers of 347 ms health, 517 ms home, 479 ms login, and 1440 ms guest thread (five matched thread samples). Health→home gives `(517 − 347)/(6 − 1) = 34 ms` per added query, while health→login gives `(479 − 347)/(5 − 1) = 33 ms`. Extending the 34 ms control slope to the guest thread predicts `347 + (27 − 1) × 34 = 1231 ms`; **209 ms of its 1440 ms container time remains unexplained**. Relative to the 1562.192 ms median public thread TTFB it is 13.4%. Staff-author/image SQL did not explain it. A fit from home→thread instead gives `(1440 − 517)/(27 − 6) = 43.95 ms`, so one universal slope would hide a real mismatch. Use a 33–45 ms range for query-reduction estimates and label it an estimate. Direct per-request connection/query spans are needed to separate network/protocol variability, PHP/container scheduling and other remaining time; this diagnosis does not claim that separation is verified.

## Repository recommendations and expected savings

The estimates below use `R = 33–45 ms` per removed sequential query. They are expected remote savings, not locally observed improvements, and must be remeasured after an implementation. They overlap where noted. Region colocation is an operator action owned by the main report; its benefit must not be added to estimates still using the old region's R.

| Recommendation | Expected saving and arithmetic | Risk and verification |
| --- | --- | --- |
| Batch since-last-read item positions; use the already-rendered page's IDs when possible | Six off-page targets: 12→1 query, `11R = 363–495 ms`; the measured six-on-page fixture can avoid all twelve, `12R = 396–540 ms` | Medium. Preserve `(created_at,id)` ordering, moved/imported posts, deleted staff stubs, pending exclusions, and user page size. Verify `ThreadReadCursorRepositoryTest`, `AppAutomatedContextTest`, `AppDeletedPostStubTest`, existing page-location tests, and the 72-query scenario. |
| Reuse notification membership/moderator repositories | Member home/thread: remove 2 duplicate reads, `2R = 66–90 ms`; standalone bell: remove its one duplicated moderator read, `R = 33–45 ms` | Low to medium. Preserve `scope(fresh:true)`, worker freshness, suspended-reader permissions, and capability evaluation. Verify notification visibility tests and fresh/stale poll budgets. Do not cache permissions across requests. |
| Reuse eligible-post counts and already-readable thread metadata in the Living Brief view | Duplicate count alone: `R = 33–45 ms` on the measured thread. Passing current thread metadata into the two repeated thread reads offers another `2R = 66–90 ms`; combined bound 3 queries / `99–135 ms` | Low to medium. Keep generation/worker eligibility checks authoritative, checkpoint-dependent count keys, visibility and pending/deleted checks. Verify ThreadIntelligence surface/eligibility tests and both guest/member budgets. |
| Resolve shared view metadata before cursor persistence and carry it in the view model | Avoid the five measured reloads, `5R = 165–225 ms`, while retaining the cursor write | Medium. Keep invalidation conservative. Do not remove `Database::clearRequestCache` generally, restore an old cache wholesale, or render navigation unread counts before marking read. Reuse only data the cursor write cannot change; verify navigation and permission behavior alongside cache tests. |
| Avoid an already-read cursor transaction when current request data safely proves no advance | Up to 3 SQL reads/writes + 5 refill queries + BEGIN/COMMIT = `10R = 330–450 ms` on a repeat visit; SQL budget 58→50 | Medium to high. This is a bound until a concurrency-safe proof is implemented. Preserve chronological monotonicity, invalid cursor repair, deleted/pending exclusions and concurrent mark-unread semantics. Test actual transaction boundaries on an isolated database. The five-refill saving overlaps the preceding recommendation. |
| Deduplicate/batch reference target reads | Six identical targets: 6→1, `5R = 165–225 ms`; zero saving when no reference cards exist | Medium. Apply every current read gate and unavailable-card rule; verify `AppContentReferenceTest`, anonymous authorship and source types. Existing membership caching already removes the membership N+1. |
| Batch wiki revision lists for curator-visible posts | Six wiki posts: 6→1, `5R = 165–225 ms`; zero for ordinary members | Low to medium. Preserve newest-first revisions, editor attribution, and curator/wiki gates. Verify wiki read/edit/revision surfaces and browser evidence. |
| Simplify existing poll reads | Derive `viewer_voted` from selected option IDs and use the caller's current thread row: 5→3, `2R = 66–90 ms` per member page with a poll | Low to medium. Keep closed/result visibility, multi-select votes and capability-based manage permission unchanged. Verify `AppPollTest` and native/emulated prepare compatibility. |
| Combine participant list/count and subscription fallback reads | One query each, at most `2R = 66–90 ms` for member threads, `R = 33–45 ms` guest thread | Low to medium. Preserve anonymous exclusion, total participant count beyond the visible stack, and thread-over-board subscription precedence. Existing read/view behavior tests plus query budgets should verify. |

Session and user reads could be joined to save another one query on each member request, but expiry, revocation, touch behavior, user hydration and suspended/banned-state checks make that a separate moderate-risk change. The current auth reads are required behavior; removing them or extending their cache across requests is not a recommendation. Stale-activity settings refills offer one further query only if view-model timing can preserve every freshness rule; no global invalidation relaxation is justified.

The server evidence does not verify a production member session, a cold container start, connection reuse, a region move, or member p75. It does not turn the local 7-test result into a full-suite claim. Any eventual change to Database/App/View still requires the repository's full local PHPUnit gate, and visible changes require browser evidence. No commit or push was performed.
