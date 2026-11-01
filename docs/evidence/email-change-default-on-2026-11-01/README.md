# Verified email changes default-on — 2026-11-01

The owner approved moving `email_change` to default-on after the initial
implementation release. Fresh installations and existing sites without an
explicit override now expose Settings → Account → Change email address. Existing
explicit-off overrides remain effective; removing that override restores the
enabled default. No settings rows are rewritten, and authentication, tokens,
recovery serialization, delivery limits and suppression rules are unchanged.

Current authority: [ADR 0055](../../adr/0055-verified-email-changes.md) and the
[runbook](../../runbooks/email_changes.md). The
[implementation qualification](../email-change-2026-11-01/README.md) remains the
historical record of the initial default-off release.

## Qualification

| Check | Result |
| --- | --- |
| Final full PHP suite | [3,564 tests, 33,902 assertions, zero failures, one intentional migration-rehearsal skip](checks/php-full.log) |
| Focused availability, rollback, admin and generated-contract checks | [112 tests / 1,170 assertions passed](checks/default-green.log) |
| Chromium desktop/mobile | [10 passed](checks/browser-chromium.log) |
| WebKit desktop/mobile | [10 passed](checks/browser-webkit.log) |
| Generated assets | [Freshness passed](checks/assets-check.log); asset version remains `67ba8434286ff7e6` |
| Asset / Worker tests | [35 passed](checks/assets-tests.log) |
| Worker dry run | [Passed without upload](checks/wrangler-dry-run.log) |
| Imladris flag inventory | [Classification manifest regenerated](checks/imladris-build.log); CSS and font bytes unchanged |

The default-on assertions were first run against the unchanged default-off
application: 67 tests / 529 assertions with five expected failures, recorded in
`default-red.log` in the private release state. The code default and inventories
were then updated. There are now 52 default-on and six default-dark flags.

Browser fixtures remove the `email_change` override rather than forcing it on.
The 20 passing configurations cover the account entry and dedicated form,
validation/pending/cancellation, read-only GET then owner confirmation POST,
session refresh/revocation, native no-JavaScript forms, explicit-off rollback and
the guarded admin turn-off control. There are 30 PNGs under `chromium/` and
`webkit/`, at 1280×800 and 390×844 plus a 320px no-JavaScript confirmation view.
Overflow and serious/critical axe checks pass, and representative form/control
captures were visually inspected.

This is emulated browser evidence using private disposable accounts, captured
mail and databases. It does not establish physical-device behavior, a real
provider round trip, SMTP delivery or inbox receipt. The security implementation
is unchanged; its broader passkey/provider/race evidence remains in the linked
implementation release, with all PHP cases rerun in the final full suite.

## Source, retention and publication

[Qualified file hashes](qualified-files.json) identify the changed inputs relative
to local bookkeeping base `ad4d7ae6`, which descends from published `a187ae4f`.
This release carries that bookkeeping commit: the verified current asset version
is pinned alongside `0abd713b0ebf7bca` and `545bd84edf0dc37c`. The manifest has
55 retained/public files after retiring two files outside that window. No new
asset version, dependency or migration is introduced.

The [passive pre-publication baseline](checks/live-before.json) records healthy
production with the email-change routes still returning 404. After one
publication, verify the exact automatic Cloud Build / Cloud Run and Workers
Builds versions, all four cron images/readiness, health, the retained asset bytes
and the active guest sign-in gate. Signed-out requests should now redirect to
login instead of returning 404. Local authenticated no-override proof is recorded
above; production checks do not change a member's contact or feature settings.

Post-publication receipts are release artifacts under ADR 0051 at
`/home/ubuntu/.local/state/community-forums/email-change-default-on-20261101T012948Z/production/`.
Recording deployment proof does not require another bookkeeping publication.
