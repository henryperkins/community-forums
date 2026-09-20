# N2 implementation report

Base: `e4b5c0f9` (N1 actor provenance correction). Branch: `codex/unified-notifications-settings`. Runtime commit: `2f8892d7` (`Fix owned notification actions and delivery preferences`). Source ownership released after this scoped commit. No push or merge.

## Behavior

- `NotificationReadService::open(User, id)` first establishes direct row ownership, then uses a fresh visibility scope and canonical ThreadReadService for topic targets. Foreign/missing IDs return 404. Inaccessible owned targets return neutral feedback and remain unread. Successful targets are marked read only after resolution. Historical group DMs remain openable with group_dms disabled; dms disabled removes their availability. Scoped report authority is required for DM-report links. Real targetless AppealService resolution notifications open `/appeals`; when appeals is disabled they acknowledge without guessing an appeal ID.
- Both HTML entry points accept All/Unread and positive descending-ID `before` cursors. Query and path are passed separately in regression tests. Existing eligible SQL paging supplies the extra-row Next cursor. Added basic All/Unread/Next/Latest navigation and retained originating return values; only `/notifications` or `/?pane=notices` and validated filter/before keys survive mutation returns. N4 owns visual unification.
- New `SubscriptionService` owns input validation, ownership, reduction classification and mutation. The settings route is `POST /settings/notifications/subscriptions/{id}`. Existing thread/board routes delegate to the same service before reading target labels. Existing channel removal and Off remain available for all authenticated account states and revoked targets. Enable/frequency changes and mixed requests require WriteGate and current access, with no partial mutation. Explicit Off and both channels disabled persist an Off override with both channel columns zero.
- Settings lists now include persisted Off rows, safe target links, neutral unavailable labels, editable channels/frequency, and explicit Turn off forms. The shared subscription-controls partial is only form fields, not N4's notification presenter. Thread validation passes draft/error bags through `templates/thread.php` to thread_tools. Restricted authenticated users retain notification watch controls and 422 drafts; snooze remains write-gated. Board and unavailable-thread errors use the settings renderer, retaining target/draft without private labels.
- `NotificationSettingsService` validates timezone/hour/pause before mutation, locks and reloads the current user, and updates digest and pause together in a transaction. NULL/empty zones become UTC; hour accepts Off/empty or integer 0–23. Invalid values/arrays return 422 with ordinary drafts retained. Digest Off and pause are state-independent reductions; unpause/enabling/changing an active schedule requires WriteGate. Bounce suppression remains separate and untouched.
- Explicit App bindings and route were added. No schema or worker changes. Signed unsubscribe remains unchanged.

## Form and page contracts for later tasks

Settings row full form: action `/settings/notifications/subscriptions/{id}`; `frequency=instant|daily|off`; checkbox names `in_app` and `email`, each value `1` (unchecked omitted). Button: `Save subscription`. Separate owner-only POST form: hidden `frequency=off`, button text exactly `Turn off`; no channel fields, both persist zero. Unavailable rows have no target hyperlink. Both forms retain CSRF.

Global form remains `/settings/notifications`, names `timezone`, `digest_hour` (empty value Off), `pause_all_email=1`; button `Save digest settings`. Invalid ordinary selected values get escaped selected options; malformed arrays get field errors without cast warnings.

Both notification controllers now provide `notification_page` (`items`, `unread`, `next_before`, `unread_only`, `before`) and `notification_return`. N4 may consolidate the temporary navigation in home.php/notifications.php using these values and the read-service historyUrl helper. No layout/shell/polling presenter changes were made.

## RED and GREEN evidence

Initial RED (before runtime edits):

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationTest|AppNotificationPreferencesRepairTest'`

**13 tests / 21 assertions / 5 failures**, exit 1. Reproduced missing owned controls, silent malformed settings acceptance, foreign-ID handling and inaccessible acknowledgment, and unread/history failure. The >100 direct-owned-ID test was already green from N1 and remains a regression.

Second RED after extending restricted legacy-draft coverage:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter test_owned_reductions_and_atomic_escalation_for_every_restricted_state`

**1 test / 2 assertions / 1 failure**, exit 1. Restricted member received 422 but the thread watch section was hidden, losing the submitted draft. The subsequent thread/watch forwarding fix restores the form.

Durable RED summary: `docs/evidence/unified-notifications-and-settings/n2-phpunit-red.log`. Transient full rendered HTML logs are `/tmp/n2-red.log` and `/tmp/n2-restricted-draft-red.log`; generated response/token markup intentionally omitted from the durable summary.

Final focused adjacent GREEN:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationTest|AppNotificationPrivacyTest|AppNotificationPreferencesRepairTest|AppUserPreferencesTest|AppAccountConsoleTest|AppModerationAppealsTest|AppUserSettingsTest|AppFeatureFlagTest|NotificationEmailWorkerTest|DailyDigestWorkerTest'`

**159 tests / 1,285 assertions**, exit 0, 3.417 seconds. No failures, warnings, risky tests or skips. FeatureFlags emits its expected malformed-settings diagnostic. Durable full output: `docs/evidence/unified-notifications-and-settings/n2-phpunit-green.log`. Three new/changed services passed PHP syntax checks and final diff whitespace checks passed.

Coverage includes all four restricted states, accessible and revoked-target channel reductions, modern and legacy thread/board Off, mixed atomic refusals, ordinary 422 retention, malformed arrays/hours/timezones, UTC defaults, foreign row IDs, CSRF, explicit thread-Off precedence, retained group and disabled DM targets, report authority, a genuinely resolved appeal and disabled-appeals acknowledgment, and old unread history through both entry points. Existing adjacent instant/digest worker suites remain green, without claiming the new N3 retry contract.

One temporary fatal during development came from attempting to re-add UserRepository::findForUpdate already supplied by A1. Removed that duplicate; UserRepository has no final diff.

The first wider focused run exposed two stale A1 expected-copy assertions in AppAccountConsoleTest. `git show e793088b -- src/Service/AccountLifecycleService.php` confirmed that commit deliberately changed the reactivate-active and cancel-no-pending errors. Parent explicitly authorized updating those two expected strings. Their existing 422 and settings active-owner assertions remain intact, and the full class now passes.

## Browser evidence and remaining gates

Parent reported all **four restricted-state no-JS browser cases passing (8.9 seconds)** against these forms: actual Turn off on unavailable subscription; stale legacy target Off; global digest Off/pause; ordinary profile write remains 403; persisted Off and both channels zero. Parent owns fixture/spec commits, `/tmp/retroboards-unified-n2-browser.log`, and captures under `.superpowers/sdd/2026-09-20-unified-notifications/browser-n2`.

N3 must add combined queued instant and durable digest tests: enqueue/fail first attempt, reduce delivery through these real settings/legacy endpoints for suspended/banned/deactivated/pending_deletion recipients (including lost target access), retry using ArrayMailer, and prove no transport call. Existing worker tests passing does not fulfill that combined acceptance case. Preserve N1's versioned actor provenance and fresh recipient/access checks. No actual mail was sent.

N4/A5 own shared visual presentation/responsive refinements and relevant Imladris synchronization. Parent/N6 owns the final full PHPUnit suite, broader desktop/mobile/keyboard/light-dark browser matrix, complete release report and cleanup. No full-suite or release-completion claim is made by this slice. Parent-owned AGENTS, USER/ADR/runbook/README and browser drafts were excluded from the scoped commit.

## Independent-review correction: board destination authority

Correction commit: **`19c06f39`**, `Align board subscription actions with board read access`; base **`9195fe72`**. The review's assigned-nonmember private-board mismatch was confirmed against `BoardController::show` and `BoardPolicy::canRead`: assignments extend canonical thread read access, but do not grant access to `/c/{slug}`.

The bounded correction injects the existing BoardPolicy and BoardMemberRepository explicitly into SubscriptionService. Board targets now use exactly the board route's policy and actual membership. This gate remains after owned reduction, so Off persists independently of access and then redirects safely to `/settings/notifications` when the board destination is unavailable. Both legacy and owned-row board escalation paths reject inaccessible board targets without mutation. No BoardController or ThreadReadService behavior changed.

SubscriptionRepository's set-based settings projection now distinguishes board-target read scope (admin or member) from thread-target scope (also assigned moderator). Unreadable board rows get neutral labels and null slugs; readable assigned threads retain their labels/links. No per-row membership queries were added. Existing actual membership restores board availability.

Three regressions were written and run before source changes:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'test_assigned_nonmember|test_subscription_labels_distinguish'`

RED: **3 tests / 6 assertions / 3 failures**, exit 1. Wrong board redirect after owned Off; unauthorized board escalation returned 303 instead of 404; settings reported the inaccessible board as available. Durable complete output: `docs/evidence/unified-notifications-and-settings/n2-board-authority-red.log` (trailing whitespace only trimmed).

Focused adjacent GREEN:

`flock /tmp/retroboards-unified-phpunit.lock env MAIL_DRIVER=sendmail MAIL_FROM='' vendor/bin/phpunit --filter 'AppNotificationPreferencesRepairTest|AppNotificationPrivacyTest|AppNotificationTest|AppUserPreferencesTest|AppAccountConsoleTest|AppModerationAppealsTest|AppFeatureFlagTest'`

**124 tests / 1,157 assertions**, exit 0, 3.111 seconds. No failures, warnings, skips or risky tests; expected malformed-feature diagnostic only. Durable output: `docs/evidence/unified-notifications-and-settings/n2-board-authority-green.log`. The new tests follow the Off redirect to settings (200), assert persisted Off/zero channels, deny both board escalation routes atomically, preserve assigned thread GET and subscription escalation, and verify neutral board labels versus available thread labels and explicit-member board recovery. Existing all-state reduction regressions remain green.

Scoped six-file `git commit --only`; staged whitespace check passed. No parent drafts, worker code, full-suite or browser ownership touched. Source ownership released. N3/N4/N6 remaining gates from the original report still apply.
