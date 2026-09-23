# Local staff-account attribution — 2026-09-20

> Historical evidence from 2026-09-20, organized on 2026-09-23. Production state, timings, build identifiers and source line references describe that snapshot; they were not remeasured during cleanup. See the [provenance and archive inventory](../../workspace-cleanup/2026-09-23/README.md).

The authenticated production account exposes admin controls, so this follow-up measures a local `admin` alongside the original ordinary `user` fixture. It does not access production or any production authentication files. The local admin's home costs one extra query, while its plain thread and poll counts equal the ordinary member's counts for the measured states. Equal counts do not mean identical SQL or permissions.

## Counts and local timing

Both accounts use the same public board and text-only opening post, default feature settings, and fresh session/presence timestamps. Only the current viewer is present in the roster, avoiding an unrelated block-list read from a second active fixture account. All responses below are HTTP 200. Timings are medians of three uninstrumented local runs against an injected MariaDB connection; they omit connection establishment and do not forecast production latency.

| Request/state | Ordinary user queries | Admin queries | Admin local SQL / total ms | Additional production BEGIN/COMMIT exchanges |
| --- | ---: | ---: | ---: | ---: |
| Home `/`, fresh activity | 19 | 20 | 3.026 / 5.288 | 0 |
| Thread direct first visit, no saved cursor | 57 | 57 | 5.903 / 10.093 | 2 |
| Thread direct repeat, already read | 58 | 58 | 6.343 / 10.391 | 2 |
| Presence, fresh activity | 4 | 4 | 0.347 / 0.476 | 0 |
| Bell, fresh activity | 10 | 10 | 1.440 / 1.657 | 0 |
| Home, session and presence stale | 22 | 23 | 3.417 / 5.411 | 0 |
| Thread repeat, session and presence stale | 61 | 61 | 6.865 / 11.020 | 2 |
| Presence, session and presence stale | 7 | 7 | 0.761 / 0.899 | 0 |
| Bell, session and presence stale | 13 | 13 | 1.861 / 2.091 | 0 |

Stale means both activity timestamps were set to 180 seconds ago immediately before that request. It adds the session touch, presence heartbeat write, and the settings read repeated after heartbeat invalidation: three SQL statements for either role. These scenarios are isolated; when real polls run sequentially the earlier poll can refresh activity for the later one.

An admin viewing its **own image-only topic** used 57 queries on the first direct visit and 58 on repeat, exactly matching both the ordinary reader of that same image topic and the admin reading another author's plain topic. The stored body was Markdown rendered as an image at `/media/1`. The later media fetch is separate and was not measured by this PHP kernel probe.

## Named differences from an ordinary member

Admin HTML shell rendering adds `SELECT COUNT(*) FROM reports WHERE status IN ('open','triaged')` through `src/Core/App.php:856` and `src/Repository/ReportRepository.php:184`. It accounts for home 19→20, and remains present on the thread page.

The admin thread avoids the ordinary reader's second memoized board-moderator-list read after cursor persistence. `src/Security/BoardAuthority.php:39` short-circuits legacy moderation authority on `isAdmin()`, whereas the ordinary user must query the board assignment again after the cursor transaction clears the memo. The initial admin list read still occurs through `src/Service/LegacyAuthorityProjection.php:47` during capability evaluation. The later notification scope still reads moderator IDs directly at `src/Service/NotificationVisibilityService.php:43`. Consequently the thread has one extra report-count query and one fewer moderator-list refill: net zero, retaining 57/58 queries.

Both roles invalidate five populated memo keys at cursor persistence. The ordinary member reloads all five through `Database::remember`; the admin reloads four: settings, preferences, membership, and the role-capability map. Thus the original five-refill optimization estimate becomes **four refills** for this admin fixture. Reusing notification repositories can remove **one** duplicated admin-thread membership read; its moderator memo is empty at that point, so the ordinary member's two-query saving does not directly transfer to staff. Home still offers the two-query notification-scope reuse opportunity, and the standalone bell one.

Staff post-count, list, and unread-position SQL includes soft-deleted stubs because `ThreadController.php:93` and `:95` grant delete/restore capabilities. Staff link-preview SQL includes removed cards through the manageable-post selection at `ThreadController.php:209`; ordinary readers see fetched cards unless they own the post. Unread navigation and notification predicates also use admin read scope. These alter predicates, not query counts in this fixture. No extra wiki revision queries occur because this board has no enabled wiki-curator surface; the earlier wiki-specific measurements remain separate.

## Explicit unread links versus direct renders

`src/Controller/ThreadController.php:116` resolves unread location before cursor persistence. A redirect occurs only for a GET with `unread=1`, no explicit `page`, and a non-null unread result (`:117`). The helper at `src/Repository/PostRepository.php:248` requires a valid existing cursor and a later live post. Missing, invalid, or already-current cursors yield no unread destination.

| Admin scenario | Status | Queries | Cursor effect | Extra production transaction controls |
| --- | ---: | ---: | --- | ---: |
| `?unread=1`, no saved cursor | 200 | 57 | Creates cursor at the visible opening post | 2 |
| `?unread=1`, already read | 200 | 58 | Cursor remains unchanged | 2 |
| `?unread=1`, valid older cursor and one new reply | **303** | **13** | Cursor remains unchanged | 0 |
| Follow redirect to `?page=1#pID` | 200 | **62** | Advances cursor from opening post to reply | 2 |
| Same one-unread state, direct canonical URL | 200 | **62** | Advances cursor to reply | 2 |
| `?unread=1` after reading that reply | 200 | 58 | Cursor remains unchanged | 2 |

The unread redirect uses the controller helper's default **303**, defined at `src/Controller/Controller.php:72`; it does not use App's separate 302 helper. It ends before post loading, markRead, or HTML shell initialization. Following this link costs two HTTP/PHP requests and normally two separate database connections: **13 + 62 = 75 counted queries**, versus 62 for the direct rendered request in the same one-unread state. Its extra 13 sequential queries correspond to `13 × 33–45 ms = 429–585 ms` under the earlier diagnosis's estimated range, plus another connection/request overhead. This is explanatory arithmetic, not a recommendation to remove unread navigation.

Every rendered thread in this probe calls `ThreadUserRepository::markRead` at `src/Controller/ThreadController.php:243`. First/repeat visits execute three statements inside it; advancing an existing cursor executes four. `src/Core/Database.php:310` and `:313` also issue PDO begin/commit outside the query counter in a real request. The test fixture's outer transaction means zero actual begin/commit exchanges occur inside markRead locally. The table's extra two exchanges are a code-derived production expectation, not a measured production value.

The original label “first unread visit” described a first render with no saved cursor. It should not be equated with the two-request explicit unread jump from an older valid cursor. A production browser's observed URL, status chain, and existing cursor history determine which local case is comparable.

## Evidence and cleanup

`server-staff-attribution.php` reuses only the diagnostic instrumentation loader from the earlier evidence script and adds this rollback-only fixture. `server-staff-raw.json` contains 28 scenarios with SQL, original call sites, cache invalidations, response status/location, and before/after cursor values. `server-staff-summary.json` attributes every counted query to a method and records transaction SQL/control expectations. `server-staff-uninstrumented.json` contains the three uninstrumented runs and medians. `server-staff-cleanup.jsonl` verifies zero remaining fixture users, threads, and boards and no open transaction; each plain run contains the same cleanup result.

The existing default PHPUnit database was used only after checking for competing PHPUnit/probe processes. Runs were serialized. No product source, persistent application configuration, production state, or authentication files were touched. No additional PHPUnit suite was run: this follow-up changes evidence only. The previous focused 7-test result remains the previously recorded result, not a new test claim.
