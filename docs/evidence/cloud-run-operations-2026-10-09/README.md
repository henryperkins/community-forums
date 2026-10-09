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

The restricted Cloud Build child runs the existing capture, unified
notification/settings and production-Docker upload suites, with synthetic
local data and no production credentials. Review found that its Docker daemon
access could rewrite later controls on the same VM. The revised source places
the trusted controller and artifact validator on a separate parent VM that
executes only the pinned reviewed controls, hardcodes the child identity and
publishes a receipt to a separate private bucket. Child test semantics remain
branch-reported. Provisioning and hosted proof of this revised parent lane
remain pending; earlier single-VM runs are functional diagnostic evidence.

[Local runner receipt](browser-ci-local-summary.json) records PHP 8.2.34,
Node 24.21.0, Compose 2.40.3, successful nested Docker execution and actual
Chromium/WebKit launches. Ten command/packaging contract tests passed with
81 assertions on PHP 8.4.26 and in the actual PHP 8.2.34 runner image: exact-SHA rejection, boolean final success enforcement,
redaction and symlink exclusion, required capture/unified/all-upload artifacts,
restricted setup dry-run behavior, installed-gh paginated webhook parsing, and strict named/UUID Cloud Build
callback validation. The runner also proves that capture failure and unified
database preparation failure still allow the other independent suites to run,
while preserving the first nonzero exit/status and failed stage. Dependency
initialization remains fail-fast. Seven uploader behavior contracts and a
two-test PHP wrapper passed; the uploader creates objects over verified HTTPS
with `ifGenerationMatch=0` and validates the creation response's size/checksum,
without reading, listing or replacing existing objects. The
[restricted uploader runtime proof](browser-ci-uploader-runtime-summary.json)
used the actual child account in build `9c42d086-d3a8-4dfd-a8c1-001e8f92bfc5`:
three synthetic artifacts were created, overwrite was refused with 412 and
object GET was refused with 403. Operator size/MD5/SHA-256 checks confirmed
the original bytes; exactly those three objects were deleted and then returned
404. No IAM was widened. This proves object publication under restricted IAM;
it does not establish browser suite execution. These local checks do not establish
hosted suite execution. The [setup receipt](browser-ci-setup-summary.json) verifies dedicated CI IAM,
private uniform access/public access prevention, the 14-day object lifecycle
and the owner-branch push hook. Trigger `ba3fce53-93e6-42bb-a4eb-cd3839574a60`
pinned reviewed control `661876a8abf0b23ef3fe9e017e53925dfe866721` at that historical
capture. The initial
manual build against `9460451` was canceled after review found incomplete
success gates; it is not a suite pass. Hardened manual build
`f215672e-72c9-4a27-8dc2-96d80561fc6e` finished **FAILURE** at 07:57:44.325222.
Its thread-view groups passed 34 cases; broad capture passed 162, failed 14,
skipped 38 existing project-specific cases and left 14 unexecuted. The old
fail-fast runner did not reach the independent notifications/settings or
production-Docker upload suites. Artifact publication also failed with 403:
`gcloud storage cp` requested object read permission that the restricted
account does not have. The replacement uploader preserves create-only IAM.
The [failed-build receipt](browser-ci-first-hosted-failure.json) records these
limits. A complete corrected hosted result remains pending.
Automatic owner-push replay `6ce5b5a1-896a-4481-b1ba-b058cd4aa95b` used exact
source/control `661876a`. The automatic replay was canceled
after source/ref/repository launch proof to avoid duplicate suite cost; it is
not a hosted suite pass.

The named webhook endpoint/query is required in this setup. The initial UUID
route returned HTTP 200 without starting a build, despite valid payload/auth.
A replay with the trigger name created a build. Repository equality and the
original compound owner-branch filter then each created builds with correctly
resolved source, ref and repository bindings. Intermediate diagnostic builds
were canceled after launch proof to avoid duplicate suites. The intended
combined filter was restored; no unfiltered replay or extra CI IAM privilege
was needed. A [native branch push](browser-ci-native-push-summary.json) at
07:46:42 created build `1ebef3c0-ba89-42f7-9699-344f31e6f5a6` for exact source
`2c6503667a731cadbafb393753675dbf4ce7b7b7` with reviewed controls `661876a`.
It was canceled after successful source/binding proof to avoid duplicate suites;
this is automatic launch evidence, not a hosted test pass.

The [Gate A baseline receipt](browser-gate-a-baseline-summary.json) reproduces
hidden mobile rail selections, desktop-only admin navigation selection,
ambiguous no-JS controls and stale custom-emoji accessible names against both
the initial CI source and unchanged baseline `c10faca`. Corrections scope board
identity to visible main content, follow the console's native mobile disclosure,
check both no-JS controls and use the humanized reaction label while proving
the exact stored custom token and active state. The affected desktop/mobile
batch passed 15 cases with one existing desktop exclusion. A separate fresh
no-JS run passed after the login helper stopped requesting a JavaScript-only
tour dismissal in that context; native authentication, roles and simulator
assertions remain. The [unread baseline receipt](browser-unread-baseline-summary.json)
confirms both responsive counters already contained `101`; the stale single-
element assertion now checks both copies, with desktop/mobile targeted passes.
These are isolated browser checks; complete hosted execution remains pending.

The [framing and animation receipt](browser-framing-and-animation-summary.json)
also reproduces the curator capture and mobile pointer failures on the baseline.
The corrected helper measures the scroll-padding budget and preserves full
containment; the formatting test waits for the actual entrance animation and
retains its hover, click and keyboard assertions. All five affected cases pass.
A wider three-file run passed 54 cases with 11 existing skips and one intermittent
failure in an untouched shortcut test. That test passed three isolated retries
on both sources; the complete composer-file retry passed 12 cases with six
existing skips and no failures, using unchanged source.

Success publication now requires the outer Docker command to return zero,
complete suite outputs and a readable archive matching the manifest and
embedded result. The packager writes candidate success JSON atomically only
after archive creation. Actual tar creation failure and nonzero/missing outer
Docker receipts are regression-tested. Credential aliases such as `apiKey`
and `Proxy-Authorization` are removed; malformed JSON is omitted with a
bounded count, preserving the actual suite stage and preventing a pass.
`result.json` alone is a candidate receipt, never sufficient success evidence.

The fallback publishes only synthetic PNG and redacted JSON into a private
bucket with a 14-day lifecycle. It publishes failure evidence before enforcing
success and requires all suite groups' expected outputs. GitHub status/check
publication is unavailable for the current webhook integration; actual Cloud
Build results and private artifacts must be inspected instead.

## Source verification

The root agent reran the full suite against the reviewed follow-up working tree:
3,227 tests, 25,557 assertions and one existing dedicated-schema guard skip on
PHP 8.4.26 in 101.952 seconds, as recorded in the
[source verification receipt](local-source-verification.json).
The unchanged asset source passed 35 Node tests and generated-asset checks.
The CI controls additionally passed ten scoped contract tests with
81 assertions on PHP 8.4.26 and 8.2.34, plus the uploader contracts above.
Source verification, hosted browser results and production rollout remain
separate receipts.
