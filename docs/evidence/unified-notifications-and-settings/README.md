# Unified notifications and account-settings evidence

**Status: implementation and verification in progress.** Baseline audit results
do not close the regressions. This index will record the final combined results,
reviewed captures, and remaining external checks before completion.

Scope and expected behavior are recorded in the
[combined design](../../superpowers/specs/2026-09-20-unified-notifications-and-account-settings-design.md),
[notification plan](../../superpowers/plans/2026-09-20-unified-notifications.md),
and [account plan](../../superpowers/plans/2026-09-20-account-settings-repairs.md).
The [runbook](../../runbooks/unified-notifications.md) describes local evidence
commands and release handling. No deployment or external mail delivery has
been performed as part of these local checks.

## Audit coverage map

Each row remains open until its implementation and the corresponding final
regression gates pass. N2/A2 is one shared privacy/opt-out repair.

| Audit finding | Regression gate | Browser or worker evidence |
|---|---|---|
| N1 digest privacy | AppNotificationPrivacyTest, DailyDigestWorkerTest, NotificationEmailWorkerTest | Captured worker messages; access revoked between queue and retry |
| N2 / A2 private labels and inaccessible unsubscribe | AppNotificationPrivacyTest, subscription HTTP tests | Notification/settings inaccessible-content and opt-out cases |
| N3 DM notification targets | AppNotificationTest and owned-action tests | No-JS DM notification open |
| N4 NULL timezone | Notification settings and digest worker tests | UTC settings; NULL-zone recipient rehearsal |
| N5 replayable/honest digest status | Digest/outbox/operator tests | CLI exit/status records and captured retry messages |
| N6 mobile notification rows | Presenter and notification integration tests | Both surfaces at 390/320 widths |
| N7 stranded unread history | Notification keyset and owned-ID tests | Read/unread history beyond the initial page |
| N8 visible initial bell count | Notification/pre-setup query-budget tests | No-JS member/admin bell and 100+ counts |
| N9 unread semantics and bulk actions | Presenter and notification HTTP tests | Axe, keyboard, zero-count bulk action |
| N10 link differentiation | Shared component browser checks | Light/dark computed decoration and axe |
| A1 lifecycle bypass | AppAccountLifecycleTest, AppUserModerationTest, owner tests, real two-connection races | No-JS restricted writes; deletion and expiry precedence |
| A3 pending TOTP form | AppMfaTest and account security tests | Invalid confirmation, reload, existing TOTP ceremony |
| A4 saved feeds/folders/digests | Saved-feed read/organization/digest tests | Open/manage/rail, overlapping digest sources |
| A5 avatar draft loss | AppProfileMediaTest and profile draft tests | Invalid upload, successful upload/remove, explicit Save profile |
| A6 organization validation | AppBoardFoldersSavedFeedsTest | Selected board/digest retained at 422 |
| A7 passwordless prompts | Password-setting/lifecycle/security tests | Actual NULL-password persona in Security and Connections |
| A8 mobile settings displacement | AppAccountConsoleTest | Native section chooser, first-control geometry, keyboard/no-JS |
| A9 raw session labels | UserAgentLabelTest, AppSessionManagementTest | Escaped raw details, current-device marker, revocation |

## Evidence isolation

The combined browser runner resets the dedicated browser database separately
for all eight core suites and both projects, plus the affected existing Gate A
and profile-media accessibility journeys. Every capture honors `RB_EVIDENCE_DIR`.
Capture names are partitioned; historical evidence is promoted only after an
explicit visual review. Runtime upload/package/rate-limit directories are
unique and removed in `finally`. PHPUnit, browser, worker, and concurrency
schemas are distinct, and schema-mutating PHPUnit processes are serialized.

The final record must include command exit codes, full-suite totals, the
meaning of each browser skip, reviewed image paths, query-plan measurements,
worker row outcomes, and cleanup verification. Screenshot existence alone is
not a geometry, accessibility, or behavior assertion.
