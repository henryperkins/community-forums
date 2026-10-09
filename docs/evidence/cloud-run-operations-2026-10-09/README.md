# Cloud Run operations follow-up, 2026-10-09

This record separates production email, operational monitoring, recovery and
hosted browser checks from the earlier
[container retirement](../cloudflare-origin-retirement-2026-10-09/README.md).
The repeatable procedures are in the
[Cloud Run runbook](../../runbooks/deployment-cloud-run.md).
All times are UTC. Private recipients, credentials, callback URLs, message
bodies and raw authenticated resource metadata are omitted.

## Production email

| Check | Result |
| --- | --- |
| SMTP sender-envelope diagnostic | Execution `retroboards-cron-5m-m9hgt` completed at 06:58:49.947570. Verified TLS and AUTH `235`; `MAIL FROM` returned `550`. No recipient or message was submitted. Authentication alone had not established acceptance |
| Native REST operator test | Permanent production configuration, PHP 8.2.34, native `EmailOpsService`, delivery `3` recorded `sent` with a transport identifier |
| Recipient receipt | Exact test subject in intended inbox at 07:07:47; expected sender domain, SPF/DKIM/DMARC passed |

[REST result](email-rest-result.json), [mailbox receipt](email-receipt.json) and
[SMTP diagnostic summary](smtp-envelope-summary.json) contain bounded receipts.
Production uses `MAIL_DRIVER=cloudflare` with the account ID as ordinary config
and `MAIL_CLOUDFLARE_API_TOKEN` from Secret Manager. The REST mailer generates its
own `cf-…` transport identifier; the provider returns accepted recipient
outcomes rather than a provider message ID.

This proves one operator test accepted and received through permanent
configuration. It does not establish every notification kind, future delivery,
backlog processing, unsubscribe behavior or recovery of a suppressed row.
The earlier retirement record's no-message SMTP and empty-outbox limits remain
accurate for the checks performed then.

## Monitoring and cron

Nine enabled alert policies and an enabled private owner email channel cover
public health, Cloud Run 5xx, failed jobs, collector errors/absence, missed
scheduled ticks, a stalled due outbox, mail failures and backup failure/age.
All policy filters were validated against current APIs. The public health
check succeeded at five locations: three USA locations, Belgium and Singapore.
Manual aggregate collector execution `retroboards-monitor-qvkl9` completed
successfully at 07:08:32.713375.
Actual scheduler-created collector `retroboards-monitor-qkkvk` succeeded at
07:15:38.481377; its aggregate log showed no due outbox jobs or missed ticks.

The collector reads aggregates within `START TRANSACTION READ ONLY`. It reuses
the existing application database user whose SQL grants permit writes; it is
not a distinct read-only DB identity. Its Google account is separate and has
no APP_KEY, email token, storage bucket or deployment access. The expected earlier SMTP diagnostic left one recent terminal failure. The
actual scheduled aggregate probe detected it and the native mail-failure policy
opened an alert. The owner inbox received the matching Monitoring notification
at 07:16:46, with sender, policy subject and inbox presence verified in the
[notification receipt](monitoring-notification-receipt.json). Availability,
5xx, missed schedules and backup failure were not deliberately induced.

The existing daily digest scheduler execution `retroboards-cron-0700-pzdrr`
succeeded at 07:00:30. The normal 03:10 command batch was tested manually as
`retroboards-cron-0310-tbd64`, successful at 07:07:30; its next actual scheduled
03:10 tick remains distinct evidence. The retired-origin record retains the
observed scheduled 5-minute tick. Do not infer every longer schedule has fired
from enabled configuration or manual executions.

Monitoring source and sanitized current configuration/runtime receipts live
under [`deploy/cloudrun/monitoring`](../../../deploy/cloudrun/monitoring) and
[`cloud-run-monitoring-2026-10-09`](../cloud-run-monitoring-2026-10-09).

## Isolated recovery

An on-demand production SQL backup `1791528734171` succeeded from
06:52:14 to 06:53:55 and is retained. An isolated scratch restore and mounted
storage proof passed at 07:11:32 on PHP 8.2.34/image `c10faca`:
116 tables passed `CHECK TABLE`, 198 foreign-key orphan checks returned zero,
83 migrations and the complete column schema matched, as did selected stable
core totals. Secret Manager APP_KEY version 1 recovered a protected synthetic
encryption challenge. A private scratch health request returned 200 with the
database healthy, while an unauthenticated request returned 403.

Only three current storage objects existed, all zero bytes. Their generation-
pinned copies matched provider checksums. A separate 128-byte synthetic fixture
proved mounted copy/read/SHA-256 behavior. There were no nonempty member
uploads or attachment rows to restore, and this did not rehearse whole-project
loss or off-project key recovery. The SQL deletion operation completed at 07:14:21.984; all four scratch
resource APIs returned 404 (SQL, Run service/job and bucket). The
[recovery receipt](recovery-summary.json) confirms complete cleanup and retained
production backup, storage and keys. No production restore was performed.

## Hosted browser lane

GitHub Actions run `37888488447` was blocked before either job ran any steps:
the check annotation reported an account billing lock. No CircleCI config
exists. The unchanged GitHub workflow remains available when its account can
run again; no payment details or billing changes were made.

The restricted Cloud Build fallback source runs the existing capture, unified
notification/settings and production-Docker upload suites, with synthetic
local data and no production credentials. The trusted inline configuration
pins a separately reviewed control SHA; restricted IAM is the production
boundary. Branch scripts can access the build VM's Docker daemon.

[Local runner receipt](browser-ci-local-summary.json) records PHP 8.2.34,
Node 24.21.0, Compose 2.40.3, successful nested Docker execution and actual
Chromium/WebKit launches. Seven command/packaging contract tests passed with
53 assertions: exact-SHA rejection, boolean final success enforcement,
redaction and symlink exclusion, required capture/unified/all-upload artifacts,
restricted setup dry-run behavior, installed-gh paginated webhook parsing, and strict named/UUID Cloud Build
callback validation. These local checks do not establish
hosted suite execution. The [setup receipt](browser-ci-setup-summary.json) verifies dedicated CI IAM,
private uniform access/public access prevention, the 14-day object lifecycle
and the owner-branch push hook. Trigger `ba3fce53-93e6-42bb-a4eb-cd3839574a60`
pins reviewed control `94604517843eec78fb917aad15d6d7278270b5be`. Manual build
`d4d2a221-a50c-48de-89b5-a31379e8b6f6` started against that same exact source;
its hosted suite result is still pending in this initial snapshot.

The fallback publishes only synthetic PNG and redacted JSON into a private
bucket with a 14-day lifecycle. It publishes failure evidence before enforcing
success and requires all suite groups' expected outputs. GitHub status/check
publication is unavailable for the current webhook integration; actual Cloud
Build results and private artifacts must be inspected instead.

## Source verification

The root agent ran the full suite against the current operations source:
3,220 tests, 25,507 assertions and one existing dedicated-schema guard skip on
PHP 8.4.26, plus 35 Node asset tests and current generated-asset checks. The
subsequent administrator-side gh compatibility fix passed its seven scoped
contract tests with 53 assertions; it does not change the pinned runner files.
Source verification, hosted browser results and production rollout remain
separate receipts.
