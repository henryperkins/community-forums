# Unified notifications and account-settings evidence

**Status: implemented and locally verified, 2026-09-20.** The ten notification
findings and nine account findings are closed for the approved repair scope.
Private subscription labels/opt-out are one overlapping repair. Work is on
`codex/unified-notifications-settings`; no deployment, push or merge is claimed.

Scope and expected behavior are recorded in the
[combined design](../../superpowers/specs/2026-09-20-unified-notifications-and-account-settings-design.md),
[notification plan](../../superpowers/plans/2026-09-20-unified-notifications.md),
and [account plan](../../superpowers/plans/2026-09-20-account-settings-repairs.md).
The [runbook](../../runbooks/unified-notifications.md) describes local evidence
commands and release handling. No deployment or external mail delivery has
been performed as part of these local checks.

## Audit coverage map

Every row below has its implementation and passing combined regression evidence.
Audit IDs differ from implementation task IDs; N2/A2 is one shared audit repair.
The full [PHPUnit results](phpunit.xml) name every executed test. Browser case
results, project totals and every skip reason are in [browser-summary.json](browser-summary.json).

| Audit finding | Regression gate | Browser or worker evidence |
|---|---|---|
| N1 digest privacy | AppNotificationPrivacyTest, DailyDigestWorkerTest, NotificationEmailWorkerTest | In-process captured bodies after access revocation; [actual worker rehearsal](worker-cli.json) |
| N2 / A2 private labels and inaccessible unsubscribe | AppNotificationPrivacyTest, AppNotificationPreferencesRepairTest | Both inaccessible-content surfaces; four restricted-state opt-out cases per project in account-settings-repairs |
| N3 DM notification targets | AppNotificationTest and owned-action tests | Both no-JS owned-form cases in notifications-unified open the message destination |
| N4 NULL timezone | AppNotificationPreferencesRepairTest, DailyDigestWorkerTest | [UTC settings](account-settings-repairs/mobile/mobile/08-mobile-notifications.png); worker `null-zone_one_sent` |
| N5 replayable/honest digest status | Digest/outbox/operator tests | Worker exit/status records; account-settings-repairs terminal/requeue journey |
| N6 mobile notification rows | NotificationPresenterTest and notification integration tests | [Both surfaces at 320px](reviewed-captures.md), plus 390px and desktop behavior/geometry assertions |
| N7 stranded unread history | AppNotificationTest keyset and owned-ID tests | notifications-unified old unread history beyond the initial page |
| N8 visible initial bell count | AppNotificationShellTest | [No-JS admin 105 count](notifications-unified/desktop/desktop/bell-nojs-admin-high-count.png), member count and polling tests |
| N9 unread semantics and bulk actions | NotificationPresenterTest, AppNotificationTest | notifications-unified axe, keyboard and no-JS zero-count bulk actions |
| N10 link differentiation | Shared component browser checks | Both themes' computed text decoration and axe in notifications-unified |
| A1 lifecycle bypass | AppAccountLifecycleTest, AppUserModerationTest, owner tests; [five two-connection races](a1-lifecycle-races.log) | No-JS restricted writes, deletion/cancellation and expiry precedence in account-settings-repairs |
| A3 pending TOTP form | AppMfaTest and account security tests | Invalid confirmation/reload/restart plus the original totp ceremony |
| A4 saved feeds/folders/digests | AppSavedFeedReadTest, organization/digest tests | Open/manage/rail/composer routes; malformed/cleared selections; worker `saved-feed_one_sent` |
| A5 avatar draft loss | AppProfileMediaTest | [Rejected upload preserves drafts](account-settings-repairs/desktop/desktop/05-avatar-error-retains-draft.png), successful upload/removal and native Enter-to-save |
| A6 organization validation | AppBoardFoldersSavedFeedsTest, AppSavedFeedReadTest | Selected board/digest retained at 422; cleared legacy multi-selection retained and saved |
| A7 passwordless prompts | Password-setting/lifecycle/security tests | Real NULL-password personas in Security and Connections, retained 422 and successful password setup |
| A8 mobile settings displacement | AppAccountConsoleTest | [Three initial viewports](reviewed-captures.md), native chooser, keyboard/all routes and no-JS matrix |
| A9 raw session labels | UserAgentLabelTest, AppSessionManagementTest | [Readable/escaped details](account-console/mobile/mobile/a5-session-raw-details.png), current-device marker and actual revocation |

## Final combined gates

Application source tested: `eeb72a9c78b81dd875d6d6bfd89ad52ffca14466`.
The final application review's `265f9f73` adds only twelve ADR/reconciliation
lines; later task-evidence commits also leave runtime/test/build files identical.
The subsequent runner-only environment-redaction correction has its separate
review and orchestration proof below; it does not change application behavior.

| Gate | Result | Record |
|---|---|---|
| `composer build:imladris` | Exit 0 | [build.log](build.log) |
| `composer check:imladris` | Exit 0 | [check.log](check.log) |
| `composer verify:imladris` | Exit 0; 24 tests / 296 assertions | [verify.log](verify.log) |
| Full `composer test` | Exit 0; 2,926 tests / 22,080 assertions; zero failures | [phpunit.log](phpunit.log), [command/exit metadata](php-gates.json) |
| Combined browser runner | Exit 0; 224 passed / 18 intentional skips; zero failures or flaky tests | [browser-results.json](browser-results.json), [browser.log](browser.log) |
| Actual purge/digest/email CLI rehearsal | Every command exit 0; 10/10 outcome checks pass | [worker-cli.json](worker-cli.json) |
| Independent task and final branch reviews | PASS after recorded corrections; no unresolved application findings | [task records](task-records/README.md), [final review](task-records/final-review.md) |

PHP ran on 8.5.4 and retained six deprecations in unchanged Database TLS and
ThemeAssetScanner paths. Its one skip is the unrelated fixture-free
thread-intelligence migration reversal rehearsal requiring a separately named
schema. No migration was introduced by this repair. The Imladris reconciliation
pin remains `6d81da590a12bd09bb8d0e282c042aa03d755a94`.

The browser runner completed all twenty suite/project combinations and all
forty prepare/test child commands successfully:

| Suite | Desktop passed / skipped | Mobile passed / skipped |
|---|---|---|
| notifications-unified | 10 / 0 | 10 / 0 |
| account-settings-repairs | 32 / 0 | 32 / 0 |
| account-console | 11 / 4 | 7 / 8 |
| totp | 1 / 0 | 1 / 0 |
| profile-surface | 3 / 0 | 3 / 0 |
| chamfer-removal | 30 / 0 | 30 / 0 |
| unified-chrome | 11 / 4 | 14 / 1 |
| board-index-remediation | 8 / 0 | 8 / 0 |
| gate-a-account-notifications | 5 / 0 | 4 / 1 |
| profile-media-accessibility | 2 / 0 | 2 / 0 |

The eighteen skips are deliberate project exclusions: twelve account-console
cases run on their specified viewport or capture shared validation/axe behavior
once; five unified-chrome cases target either the phone drawer or desktop
persisted rail; one duplicate Gate A no-JS operator journey runs on desktop.
There is no missing passwordless-persona skip. Every exact reason is retained in
the browser summary. The initial A5 timeout is superseded by this clean combined
run, including its complete stress test.

## Delivery, cost and visual evidence

The CLI rehearsal includes NULL timezone, queued retry, saved-feed-only,
paused, address-suppressed, banned, actually purged/NULL recipient, legacy invalid
digest and explicit operator-test rows. Repeated drains send zero. In-process
tests use the same ArrayMailer instance to assert body privacy; separate CLI
processes establish counters/row transitions, not retained message bodies.
Scheduling precedes transport readiness, while every attempt rechecks current
eligibility. SMTP exactly-once delivery is not promised.

[Notification query profiling](notification-query-plan.json) used 10,000 notices
and 100 subscriptions: scope/page/count/settings required six queries, repeated
scope/count zero; observed elapsed time was 16.013ms, not a production threshold.
[Saved-feed profiling](saved-feed-query-profile.json) held rail queries at four
for either one or 100 feeds and used two queries for twenty overlapping digest
sources with 200 topics. AppNotificationShellTest also pins lazy/failure/zero
caching, one count per relevant shell and no notification SQL on unrelated paths.

[Reviewed captures](reviewed-captures.md) record actual visual inspection and
the corresponding behavior/geometry assertions. The final desktop Notifications
pane was deliberately promoted to the existing canonical
`docs/evidence/imladris-board-index-remediation/05-pane-notices.png`; the real
onboarding Skip action removes its prior overlay. The seven
[implementation decisions](implementation-decisions.md) and ADR 0032 record the
saved-feed read scope, account rail and narrow-header adaptations.

## Report integrity and operational limits

During closeout, Playwright's JSON serialization was found to include inherited
server environment values. No raw report was committed. Every final report was
sanitized before promotion. The hardened runner pins deterministic application
and provider test keys, writes raw JSON only to disposable scratch, strips the
server environment before publication, and cleans scratch even on failure.
[Three orchestration checks](report-redaction-check.json) cover successful,
failed-test and malformed-report paths; these are stubbed-child harness checks,
not additional browser tests. The final reviewer inspected this correction.

External OAuth authorization, actual email delivery and production deployment
were not exercised. Legacy inconsistent lifecycle rows require the targeted
operator reconciliation described in the runbook. Member security history,
email discovery, username editing, the last-20 dropdown and the full notification
control matrix remain explicit carryovers; this repair does not claim them as
shipped. Native zoom and operating-system tab switching were not manipulated;
the browser tests document their reflow/visibility simulations.

## Evidence isolation

The combined browser runner resets the dedicated browser database separately
for all eight core suites and both projects, plus the affected existing Gate A
and profile-media accessibility journeys. Every capture honors `RB_EVIDENCE_DIR`.
Capture names are partitioned; historical evidence is promoted only after an
explicit visual review. Runtime upload/package/rate-limit directories are
unique and removed in `finally`. PHPUnit, browser, worker, and concurrency
schemas are distinct, and schema-mutating PHPUnit processes are serialized.

The [closeout record](closeout.json) records final report/link integrity,
disposable-state cleanup, unchanged main checkout and canonical image equality.
Screenshot existence alone is not a geometry, accessibility or behavior assertion.
