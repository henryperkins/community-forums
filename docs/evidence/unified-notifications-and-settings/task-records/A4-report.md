# A4 implementation report

Commit: `d6b11924` (exact owned paths only). Source ownership released.

Status: implemented; focused PHP and parent-run A4 browser evidence green. Full combined browser/full-suite verification remains parent-owned.

## Behavior and contracts

- Added owned saved feed GET `/feeds/saved/{id}` and update/delete POSTs, folder rename/delete/remove POSTs. Legacy create/add routes remain.
- Added narrow SavedFeedRepository/BoardFolderRepository storage, SavedFeedService, SavedFeedController, and shared strict SavedFeedFilter validation. No schema migration.
- Creation, rename/filter changes, enabling digest, folder mutation, and saved-feed deletion use WriteGate. Exact owner-scoped `digest_enabled=0`-only POST (plus CSRF) bypasses WriteGate, including suspended/deactivated/deletion-pending/banned and revoked source access. Mixed edits remain gated. UI has separate `Turn off digest` form.
- Duplicate-name conflicts produce 422 with field-specific errors; saved-feed/folder/bookmark form errors re-render originating settings page with preserved selections and unique linked error IDs. Normal POST forms require no JS.
- Original empty filter follows existing Latest public/private-member discovery. **Explicit selected IDs use canonical current read access**, including hidden direct scope, admin-private and assigned-board read access. Pending/deleted/anonymous posts and blocked actors stay excluded. Flags/ownership precede reads; malformed/unknown filter shapes show neutral unavailable states and never become all boards.
- Lazy shell closure loads only owned folder/saved-feed groups, guarded against missing schema and disabled flags. It excludes bookmark lookup/cleanup. Custom groups precede existing categories; shortcuts carry no duplicate unread counters. Composer ignores custom groups and retains existing board destinations, locked explanations, drawer and presence footer. NavigationService remains unchanged because existing category navigation and unread calculation stay intact.
- Digest v1 snapshots store enabled saved-feed IDs plus original validated filters. Delivery intersects original and current source filter semantics, current enabled/deleted state, and N1 eligibility. Explicit thread settings override board settings; Off/email-disabled/Instant exclude saved-source targets, Daily participates, and an uncontrolled target can get daily activity through a saved feed. Posts count once and each topic renders once. Removed source content is absent while remaining independent sources can deliver. No source leaves a terminal suppressed job. Global scheduling/pause/suppression remains in N3 shared drainer.
- Settings says `Daily digest is off` with a notifications-settings link when a feed is selected but the global schedule is Off.

## Verification

All PHP commands ran under `flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM=''` and the isolated configured PHPUnit database. No real mail or external delivery.

1. Initial red: `vendor/bin/phpunit --filter 'AppBoardFoldersSavedFeedsTest|AppSavedFeedReadTest|DailyDigestWorkerTest'` — 38 tests, 424 assertions, 8 expected missing-behavior failures (`/tmp/a4-red.log`).
2. Main green: same filter — 38 tests, 482 assertions (`/tmp/a4-green3.log`).
3. Expanded regressions exposed malformed nested form input accepted as a name (1 failure + PHP warning); validation fixed, 45 tests/559 assertions green (`/tmp/a4-more-green.log`).
4. Explicit hidden/admin/assigned selected-scope tests first failed cleanly in both page and digest paths, then passed after the bounded canonical scope correction (`/tmp/a4-scope-red.log`). Unsupported stored filter keys also first failed then were rejected.
5. Final focused command: `vendor/bin/phpunit --filter 'AppBoardFoldersSavedFeedsTest|AppSavedFeedReadTest|DailyDigestWorkerTest|AppFollowFeedTest|NavigationServiceTest|AppFeatureFlagTest|AppSetupTest'` — **101 tests, 1,037 assertions, pass**, 2.850 seconds (`/tmp/a4-final3.log`). The feature-flags malformed-object fixture emits its intentional logger line; PHPUnit reports no warnings/errors.
6. `git diff --check` clean. PHP lint checked edited templates and organization service during implementation; all touched runtime paths covered by final focused tests.
7. Parent reported desktop/mobile A4 browser outcomes: earlier restricted digest opt-outs/folder/composer cases 12/12 passed; corrected saved-feed open/manage/rail/composer/revocation cases 2/2 passed (5.9 seconds); linked validation and populated Boards Axe cases 4/4 passed (10.4 seconds). These are parent-observed runs, not independently rerun here; parent owns browser artifacts and final combined rerun.

Tests cover legacy multi-board filters on page and digest, both block directions, malformed/corrupt filters, foreign IDs, duplicate create/rename, selected revoked versus all boards, notification/email-disabled feed availability, overlapping sources, effective Off/Instant/email-disabled precedence, disable/delete/replace/corrupt/private/flag-off between queue/retry, separate original/current discovery semantics, remaining independent sources, terminal suppression, and queued saved-feed-only opt-out in all four restricted states.

## Query profile

Executed `vendor/bin/phpunit /tmp/A4QueryProfileTest.php` under the same flock/mail environment: 1 test, 6 assertions, pass. Temporary reproducible profiler at `/tmp/A4QueryProfileTest.php`; measured artifact `/tmp/a4-query-profile.json`. Transaction-rolled-back fixture: 20 boards, 200 topics, 200 posts, then 20 overlapping enabled saved feeds.

| Operation | Returned | DB queries | DB time |
| --- | ---: | ---: | ---: |
| Explicit selected feed, perPage=1 | 1 item | 4 | 1.509 ms |
| Explicit selected feed, perPage=100 | 100 items | 4 | 1.381 ms |
| Digest, 20 overlapping sources | 200 topics | 2 | 2.832 ms |

The selected page's four queries are one flag snapshot, member IDs, assigned-board IDs, and one bounded feed query. All-board Latest remains its existing two-query path. Digest measures one current enabled-source lookup plus one aggregate activity query after scope/snapshot construction. No per-post membership lookups; measured query count is independent of returned items. Timings are one local sample, not a load benchmark.

## Boundaries and parent follow-up

No full suite run per task scope. Parent owns final combined verification, CLI array-mail rehearsal, all release documentation and evidence publication. Source commit includes only the owned runtime/templates/integration tests, never parent drafts or browser harness. No NavigationService or shared CSS change was needed: guarded custom groups reuse current rail/form classes; existing category rail and composer layout are preserved. No push performed.

Parent should document the all-vs-explicit scope distinction above in ADR 0032/USER and the closeout ledger, and preserve query profile evidence if desired. Parent documentation drafts remain untouched.
