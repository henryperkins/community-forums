# A4 review fixes

Commit: `2c8a59cd`. Source ownership RELEASED.

Status: both review P2 findings implemented and verified, including the same JSON type-loss issue at raw digest worker/admin replay boundaries. No migrations or payload-version change. Source work began after N4 release at `dc1f3749`; parent commits remained untouched.

## Fixes

1. `SavedFeedFilter::parse` now decodes JSON objects as objects and converts only the filter object after retaining nested field types. `board_ids:{}` and numeric-key objects such as `board_ids:{"0":123}` are rejected. Real `board_ids:[]` remains explicit discovery. Page reads show unavailable; snapshot construction drops corrupt sources; a valid original snapshot cannot use a corrupt current source.
2. `DigestService::parsePayload(string): ?array` validates raw JSON object/list boundaries before conversion into the trusted v1 array model. Subscriptions and saved feeds must be JSON arrays of source objects; nested filter objects retain board-list types until `validPayload` validates them. Worker delivery and `EmailDeliveryRepository::canRequeue` both use this parser. Malformed nested snapshots fail permanently as `invalid_digest_payload`, cannot be replayed by an admin, and do not block later valid rows. Legitimate empty-array v1 snapshots still deliver and can be requeued following an ordinary transport failure.
3. Full saved-feed edit forms submit hidden `board_filter_present=1`. A multiple select with no successful board controls then means an explicitly cleared filter, becoming All boards. A partial update that omits both marker and board controls keeps its original scope. The 422 renderer preserves no selected options and the digest checkbox. The multiple-select label explains that selecting none means all boards. The separate restricted-state digest-disable form remains marker-free, and adding the marker to a restricted reduction is rejected as a mixed edit.

## RED / GREEN

All PHP commands used `flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM=''` and the isolated configured test DB. No real mail, browser execution, or external side effects from this implementation agent.

- Unmodified external reproduction: `vendor/bin/phpunit --do-not-cache-result /tmp/A4ReviewRegressionTest.php` — **2 tests, 4 assertions, 2 failures** (`/tmp/a4-fix-original-red.log`). Original file retained as `/tmp/A4ReviewRegressionTest.original.php`.
- New committed regression filter: `vendor/bin/phpunit --filter 'test_object_shaped|test_cleared_full_multiselect|test_digest_raw_json|test_admin_cannot_requeue_digest_with_object'` — RED **5 tests, 11 assertions, 5 failures** (`/tmp/a4-fix-red.log`), then GREEN **5 tests, 59 assertions** (`/tmp/a4-fix-green.log`).
- External reproduction adaptation was deliberate: its original hardcoded clearing POST omitted every future hidden marker, which now correctly describes a partial update rather than a complete cleared form. The temporary reproduction now collects hidden successful controls from the rendered full form before posting its cleared selection, matching browser behavior. Same command now passes **2 tests, 4 assertions** (`/tmp/a4-fix-review-green.log`). No parent browser test was changed.
- Expanded final command: `vendor/bin/phpunit --filter 'AppBoardFoldersSavedFeedsTest|AppSavedFeedReadTest|DailyDigestWorkerTest|NotificationEmailWorkerTest|AppModerationDraftLossTest|AppFollowFeedTest|NavigationServiceTest|AppFeatureFlagTest|AppSetupTest'` — **146 tests, 1,331 assertions, PASS**, 3.423 seconds (`/tmp/a4-fix-final.log`). Its single intentional malformed-feature-object logger line is not a PHPUnit warning. No full suite run per task scope.
- `git diff --check` clean. Source self-review confirmed all durable digest raw decode/requeue boundaries use the typed parser; remaining worker JSON decode sites are instant/system payloads outside this digest scope.
- External observation artifact `/tmp/a4-review-malformed-result.json` now records HTTP 200, `page_contains_unrelated=false`, `sources.saved_feeds=[]`, and `digest_contains_unrelated=false`.
- Parent-run real no-JS browsers: **12/12 PASS**, desktop/mobile, 19.9 seconds. Covers cleared legacy selection plus 422 round-trip, malformed object filter unavailable, and exact saved-feed digest opt-out in four restricted states. Parent log `/tmp/retroboards-unified-a4-review-browser.log`; captures `.superpowers/sdd/2026-09-20-unified-notifications/browser-a4-review`. These runs were reported by the parent, not independently rerun here.

## Scope / follow-up

Only ten owned runtime/template/integration-test files changed. Parent browser tests, fixtures, docs, and N4 evidence left untouched. No query changes: existing bounded DB query profile remains applicable; this change adds pure JSON structural validation and one full-form marker. Parent owns scoped rereview and final combined/full-suite gates.
