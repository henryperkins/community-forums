# Phase 4 Carryover Partial Stopping Point

Date: 2026-06-29
Branch: `phase3-4-closeout-completion`
Base: `main`

This branch is a partial implementation of ADR 0003 carryovers. It does not
replace the existing Phase 4 "accepted with deferrals" status, and it does not
claim full Phase 4 carryover acceptance.

## Landed Deploy-Dark Slices

- Added independent dark feature flags for Phase 4 carryovers: link previews,
  expanded files, polls, custom emoji, slash/GIPHY, split/merge, profile media,
  board folders, saved feeds, automated context, and content references.
- Added additive foundation migration `0058_phase4_carryover_foundation.php` for
  link previews, expanded-file scan/quarantine state, polls/votes, custom emoji,
  board folders, saved feed filters, since-last-read context, profile moderation
  markers, and wider custom-emoji reaction storage.
- Implemented badge-rule admin/service coverage for preview, enable, disable,
  backfill, and revoke using the constrained vocabulary `post_count`,
  `thread_count`, `reputation`, and `solved_count`.
- Implemented persisted post reference extraction and read-gated reference cards
  for board/thread/post references.
- Implemented link-preview queueing/fetch metadata service with operator host
  allowlist, egress validation, metadata sanitization, kill-switch behavior, and
  admin purge/refresh routes. Private-board posts and DMs are not fetched.
- Implemented expanded non-image file upload/download gates for PDF and
  text-family files, with content sniffing, scan-pending default, quarantine
  helpers, stale-scan cleanup, download-only delivery, and `nosniff` headers.
- Implemented one-poll-per-thread creation, no-JS vote/close flows, and aggregate
  result visibility after vote or close.
- Implemented operator-managed custom emoji records, shortcode rendering through
  the Markdown sanitizer, and optional custom-emoji reactions.
- Added GIPHY picker configuration endpoint for client-side Search/Trending use
  with public key, rating, attribution, direct-media expectation, and no server
  proxy/cache.
- Implemented the progressive-enhancement slash menu for approved insert
  snippets and direct GIPHY media insertion, with CSP opened only when the
  `slash_giphy` flag and public key are configured.
- Implemented private board folders and saved feed filters for personal
  organization only.
- Implemented DM-message and summary reference capture/rendering using the same
  read-gated reference-card path as post references.
- Implemented deterministic since-last-read context from local post/read-state
  data only, generated before the current page view advances the read marker.
- Added `worker:related-topics` to refresh approved tag-related public-thread
  links without reading private-board content.
- Implemented profile-media upload/removal for local avatars, three-line
  signature height enforcement, and moderator signature removal audit behind the
  `profile_media` flag.
- Implemented moderation appeals with member submission, staff queue, reverse
  restoration, notification, and audit coverage.
- Implemented moderator split/merge operations behind `split_merge`, using the
  existing thread operation/redirect schema plus counter repair.
- Implemented account deactivation/reactivation, JSON export, deletion
  request/cancel, and purge/anonymization under ADR 0006.
- Implemented advanced local theming: retro preset, light/dark logo variants,
  and guarded custom CSS behind `custom_css`.
- Implemented email announcement broadcast, `system` email worker rendering,
  cached SPF/DKIM domain status, and opt-in verified-domain send blocking.
- Implemented private bookmark folders for starred threads and bounded custom
  public profile fields.

## Unfinished Or Partial Items

- Additional per-slice browser evidence, a11y evidence, crawler evidence, load
  probes, worker smoke, backup/restore, upgrade rehearsal, and operator runbook
  rehearsal are not complete for every new carryover slice.
- Full Phase 4 closeout docs (`SCHEMA.md`, ADR 0003 full acceptance wording, and
  final evidence bundle) were intentionally not converted to acceptance wording
  because the carryover scope is still incomplete.

## Verification Boundary

Focused regression for this increment:

- `./vendor/bin/phpunit tests/Integration/Core/AppContentReferenceTest.php tests/Integration/Core/AppAutomatedContextTest.php tests/Integration/Core/AppProfileMediaTest.php tests/Integration/Worker/RelatedTopicRefreshWorkerTest.php`
  -> 13 tests / 72 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppCustomEmojiGiphyTest.php`
  -> 5 tests / 26 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppAccountLifecycleTest.php`
  -> 5 tests / 48 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppModerationAppealsTest.php`
  -> 3 tests / 21 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppThreadSplitMergeTest.php`
  -> 2 tests / 19 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppBrandingThemeTest.php`
  -> 10 tests / 39 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Admin/AppAdminEmailTest.php`
  -> 12 tests / 75 assertions, green.
- `./vendor/bin/phpunit tests/Integration/Core/AppBoardFoldersSavedFeedsTest.php`
  -> 7 tests / 46 assertions, green.
- `cd tests/browser && npm run prepare-db && npx playwright test --project=desktop -g "slash menu"`
  and the same command with `--project=mobile` -> both green; screenshots:
  `docs/evidence/browser/{desktop,mobile}/26-slash-menu.png` and
  `docs/evidence/browser/{desktop,mobile}/27-giphy-inserted.png`.

Full regression for this branch:

- `composer test` -> 744 tests / 2908 assertions, green.
- `cd tests/browser && npm run evidence` -> 27 passed / 1 skipped across 28
  Playwright tests, green.

Worker smoke for this increment:

- `APP_ENV=testing DB_DATABASE=retroboards_test php bin/console worker:related-topics 10`
  -> `Related-topic refresh: linked=0 skipped=1` with the feature dark.

This branch is still not final Phase 4 carryover acceptance because broader
browser/a11y/upgrade/worker evidence and release runbook rehearsal remain
incomplete for the newer carryover slices.
