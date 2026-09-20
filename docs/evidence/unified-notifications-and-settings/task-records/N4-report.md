# N4 shared notification presentation

Committed dc1f3749 (22 owned runtime/test/build paths only), based on parent 95db930a. Runtime and browser ownership released. No N5 bell/tour or A5 settings-navigation behavior was changed.

Implemented one NotificationPresenter event vocabulary/icon map with the exact ten-key item contract, one shared list partial, and one shared row partial for /notifications and /?pane=notices. Both controllers map N1 authorized rows through the presenter. Existing eligibility, masking, action ownership, history and return-path services remain authoritative. Both screens now say Notifications and have identical disabled/read/empty states, All/Unread/history controls, settings links, real POST actions, unread accessible text, and ISO UTC time attributes. Prose links remain underlined. Long names/titles wrap at 320px; unread dots stay aligned with their first message line. Settings links back to Notifications.

The named three PHP collateral contracts were deliberately updated; an additional AppForumIndexViewingTest guest-copy contract followed the visible label rename. The existing anonymous actor assertions remain intact. Added presenter and integration equality/escaping tests, and an isolated browser fixture/spec using real repository/service APIs. Fixture rejects APP_ENV=production and unrelated schemas; both refusal checks were executed successfully. Mail was array/dummy for browser work; PHP ran MAIL_DRIVER=sendmail MAIL_FROM='' under the required flock.

## Verification

- Initial required red PHP group: 50 tests, 242 assertions, four failures. Three were the intentionally missing presenter; the fourth was the existing N3 history return contract now emitting filter=all. Log: docs/evidence/unified-notifications-and-settings/n4/phpunit-red.log.
- Final expanded PHP group: 80 tests, 738 assertions, all pass (1.525s). Includes NotificationPresenterTest, AppNotificationTest, AppImladrisFidelityHighImpactTest, AppAnonymousPostingTest, AppForumIndexRemediationTest, AppForumIndexViewingTest, ImladrisRuntimeAssetTest, AppImladrisRuntimeTest.
- Interim full suite: 2,892 tests, 21,764 assertions, two failures, six deprecations, one skip, 1m29.879s. Failures were AppForumIndexViewingTest guest notification copy (fixed here and focused green) and AppModerationDraftLossTest::test_requeue_of_a_non_failed_delivery_reports_the_noop (parent fixed in dbf6bceb; parent reports 6 tests/27 assertions green). No full green claim: parent N6 owns final full-suite run after N5/A5.
- Baseline browser red: archived d6b11924 served independently at 8035 failed the new shared-row test because data-notification-list did not exist. Archive used its own App classes/templates, current dependency vendor, and the same isolated fixture. Initial fixture title exceeded the 160-character business limit; corrected before the recorded expected baseline failure. Baseline server stopped.
- Geometry red: new assertion caught a dot 16.59375px away from its first message line for unbroken actor names. Shared CSS position/padding fix passes; red log retained.
- Final notifications-unified.spec.ts: desktop 5/5 (20.3s), mobile 5/5 (21.8s). Both entry points, every event type, anonymous and missing actors, targetless appeals, HTML escaping, old unread event below 35 read rows, UTC timestamps, next/latest/unread history, inaccessible/empty states, disabled zero actions, POST actions with JS disabled, keyboard focus, 44px buttons, no horizontal clipping, 40/64-character names, long unbroken titles, 1280/1440/390/320 widths, Parchment/Twilight, axe link/name/contrast rules. 200% reflow is emulated with a 640x500 CSS viewport and 2x output pixels, corresponding to a 1280x1000 viewport at 200% browser zoom; no native browser-zoom setting was manipulated.
- Original board-index-remediation.spec.ts account-adjacent check: desktop 1/1 and mobile 1/1. Clean standard seed, admin onboarding completed through UserRepository via the strict fixture board-index-ready command; no original spec edits. Parent will replace this preparation with actual Skip-button behavior in N6.
- composer build:imladris and php bin/build-imladris-assets.php --check passed. Shared CSS source/mirror transfer sections remain identical. Runtime baseline application digest includes current parent USER.md and preserves literal reconciled_through_commit. Final N6 digest refresh remains parent-owned.
- git diff --check passed.

Commands:

    flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'NotificationPresenterTest|AppNotificationTest|AppImladrisFidelityHighImpactTest|AppAnonymousPostingTest|AppForumIndexRemediationTest|AppForumIndexViewingTest|ImladrisRuntimeAssetTest|AppImladrisRuntimeTest'
    flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit
    APP_ENV=test DB_DATABASE=retroboards_unified_e2e MAIL_DRIVER=array MAIL_FROM=evidence@example.test E2E_BASE_URL=http://localhost:8034 E2E_PORT=8034 RB_EVIDENCE_DIR=docs/evidence/unified-notifications-and-settings/n4/notifications npx playwright test notifications-unified.spec.ts --project=desktop

Browser commands run from tests/browser; repeat last command with --project=mobile. Original board-index check uses its own n4/board-index/{project} output and --grep account-adjacent. Standard prepare.sh was run before original board-index captures. Final fixture/schema is the clean standard seed with the administrator onboarded. No N4 notification fixture users/content remain after that cleanup.

## Captures and handoff

All logs and captures are under docs/evidence/unified-notifications-and-settings/n4/. Final PNGs were opened and inspected for populated, unread-only, empty, inaccessible, phone/stress, Twilight and zoom states on both surfaces. Captures use viewport frames to avoid misleading full-page images of the shell's independently scrolling pane. Failed-run artifacts were removed after retaining red logs.

The reviewed matching desktop capture for later canonical promotion is:

    docs/evidence/unified-notifications-and-settings/n4/board-index/desktop/05-pane-notices.png

It is the 1280x1100 standard admin/one-unread fixture with no Welcome overlay. Do not promote until parent N5/A5/N6 final verification; no historical PNG was overwritten by N4. Parent owns README/ADR final evidence references and final canonical promotion. N4 evidence remains available for that final documentation commit.

N5 can extend tests/browser/notifications-unified.spec.ts and its fixture for initial bell count/tour. Current fixture reset emits user_id, unread and old_id. Ownership of all runtime source and shared browser port8034/schema is released. No server remains running from this task.
