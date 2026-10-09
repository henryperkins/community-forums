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
The later [pre-merge scheduled check](premerge-scheduled-execution-summary.json)
verified actual Scheduler-created successes for the six-hour job `84g8m` at
06:00:31, five-minute job `dqxbp` at 11:40:30 and monitor `6rrgq` at 11:30:33,
with matching image, identity and command. The collector reported no due
outbox rows, recent failures or missed schedules, and a healthy automated
backup. These executions use baseline `c10faca`; they do not prove the pending
M/R rollout.

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
branch-reported. The [live setup inventory](browser-ci-isolated-setup-summary.json)
verifies separate accounts and buckets: the child retains log writing and
artifact creation only; the parent has the exact custom create/get role,
ActAs on the child alone, artifact viewing and verification-receipt creation.
Both buckets enforce private uniform access, 14-day expiry and soft delete 0.
The [native push receipt](browser-ci-isolated-native-push-summary.json) records
parent build `3fc84a4c-5b21-4b28-8ba6-0acba3a65f7e`, launched automatically for
exact source/control `27864474591724bfdf2ba65578ebf647dfab7bfa` at 08:51:21.447460.
That parent and its child were canceled after launch proof when review required
provider-step verification; no complete suite pass is claimed for them.
The revised controller also requires every expected provider step to succeed,
with ordered IDs and no nonzero exit codes, before accepting child artifacts.
The [new native push](browser-ci-final-native-push-summary.json) launched parent
`92503be8-e45c-44e7-966d-b9bcf4a69b28` at 09:10:26.946947 and child
`a09e3080-2884-4445-8d1c-1eb25b0ea9ea` for exact source/control
`03690ff92e26b5c3413c2d4d799b402599179676`, including the accessibility case's
bounded time budget. Its launch receipt is not a complete suite pass.
That run finished **FAILURE**: child at 10:28:19.750924 and parent at
10:28:32.555764. The [hosted failure receipt](browser-ci-036-hosted-failure-summary.json)
records capture 334 passed / 74 existing exclusions and unified settings
248 passed / 18 existing exclusions, both with zero failures. All 40 unified
database/test commands succeeded. Uploads passed 138 and failed three
WebKit-mobile cases: interrupted-image removal left send disabled, a no-JS
case timed out reading its CSRF input, and a finalized DM image was absent.
Desktop and mobile upload projects each passed all 47 cases. No cause or
product regression is inferred from that failed run alone.
Failure publication succeeded; candidate and Docker exit remained failed,
and the parent rejected the provider failure. The complete archive had
809 artifacts / 810 members, 112,555,840 compressed bytes and 125,255,680
inflated bytes including headers, within every verification bound. This failed
historical run and the earlier single-VM runs remain diagnostic evidence;
the later accepted B run is recorded separately below.
An independent audit matched all four cached objects to provider size/MD5 and
local SHA-256, confirmed all 20 unified project reports and the corrected
accessibility case, and reproduced the fixed validator's rejection.

The [targeted hosted WebKit diagnostic](browser-upload-webkit-hosted-diagnostic-summary.json)
reran all 47 upload cases against the same `03690ff` application and PHP 8.2.34;
all passed, including the three failures above. Its four provider steps and
three diagnostic objects were independently verified. This uploads-only run
used passive instrumentation and a separate artifact prefix; it did not run
the parent acceptance lane. A passing replay does not establish a cause for
the earlier failures or replace the failed full run.

The [native pointer comparison](browser-dm-pointer-scroll-fix-summary.json)
subsequently isolated an existing DM submission bug: focusing Send could
scroll its dock between pointer press and release, moving the button 34 pixels
in the measured WebKit case. Release then hit the composer, and the valid,
ready form never submitted. The held-press regression failed on unchanged
`c10faca` in all three projects and passed with the focused fix on PHP 8.2.34,
including native reply 303, the saved image and finalized attachment. The fix
defers dock reveal until active pointers release and preserves keyboard and
initial reveal paths; it changes no CSS and does not force submission. This
is isolated baseline/fix proof. The pending-preview and no-JS causes remain
unconfirmed; their later passing outcomes do not establish a cause.
The fix is committed as `d9b2295e19b251577cafab8c1d5868d516ade80e`, with
unchanged trusted controls pinned to `03690ff`.
Its [native push linkage](browser-ci-d9b-native-push-summary.json) verifies
the original GitHub push delivery, parent `48afc71c-cb4f-468c-9294-58f510c89bd1`
and child `4b4d18e8-0c22-4457-b386-a3244ff6a563`, with their respective trusted
and restricted identities and exact source/control bindings. This receipt
records launch rather than full acceptance.
The [complete B audit](browser-ci-d9b-hosted-acceptance-summary.json) subsequently
verified **SUCCESS** for both builds: child at 12:12:11.718041 and parent at
12:12:22.652193 UTC. The full lane passed 334 capture cases, 248 unified
notification/settings cases and all 144 production-Docker upload cases
(48 in each project): **726 passed, zero failed and 92 existing exclusions**.
All three new held-pointer cases passed, as did the
[three earlier WebKit failures](browser-ci-d9b-previous-failure-cases-summary.json).
The latter outcome is not proof that the pointer fix explains the unrelated
pending-preview or no-JS failure.

The [operator download](browser-ci-d9b-operator-download-summary.json) read
the four generation-pinned objects once. Independent provider size/MD5 and
parent SHA-256/size checks matched the same cached bytes. Every real provider
step succeeded; the parent receipt's `metadata_verified`,
`provider_steps_verified`, `enforcement_passed` and `passed` are true.
The pinned data-only validator also passed locally. The archive contains
809 artifacts / 810 regular members, 112,487,605 compressed bytes and
125,265,920 inflated bytes including headers, within every bound. Its 20
unified project reports and 40 preparation/test commands are complete and
successful. This accepts exact source B with control `03690ff`; it does not
claim C/M/R fully reran these browser suites or establish production delivery.
The separate [lifecycle probe](browser-dm-pointer-lifecycle-summary.json)
passed its complete Chromium touch case, covering matched cancellation,
outside release, native Tab focus and short-screen reveal. WebKit measured
matched cancellation and immediate deferred reveal, but a later whole-form
poll failed after rich-editor growth; its keyboard section was not reached.
No complete WebKit lifecycle pass is claimed. The private delayed-login probe
completed the original no-JS assertions before a context-close budget timeout;
it reproduced no authentication race and led to no shared login change.
The [owned isolation resources](browser-dm-pointer-isolation-cleanup-summary.json)
were removed after verification, leaving no owned database volumes.

The [pre-merge asset baseline](asset-delivery-premerge-baseline-summary.json)
verified all 64 assets against deployed manifest `85b4bc099e7bdac1`, including
the active `app-ead709125428cd4d.js`, plus health, guest routes and origin
protection. That verified release was pinned before generating replacement
`d8529d5d0f5e0219`, which retains the active bundle under the existing three-
deploy retention policy. The new `app-5839ddd55290c1d1.js` carries the pointer
fix; CSS is unchanged. Generated-asset checks and 35 Node tests passed after
the retention correction. The [source retention receipt](asset-retention-source-summary.json)
confirms all 25 files in the previous current release retain identical bytes
in committed source `d9b2295e`. This is baseline delivery and new source proof;
post-merge delivery of the replacement remains unverified.

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
limits. The [second diagnostic](browser-ci-d6-functional-failure-summary.json)
on exact source/control `d6cc5aca` also finished **FAILURE**, at 09:05:00.871891:
capture passed 334 with 74 existing exclusions; unified notifications/settings
passed 96, failed one and skipped four, then stopped that group; production
Docker uploads passed all 141 cases across desktop, mobile and WebKit-mobile.
The top-level runner continued uploads after the unified failure. Its
published candidate and Docker receipt remained failed, and the local trusted
validator rejected them. The archive had 521 artifacts / 522 members,
71,225,261 compressed bytes and 79,196,160 inflated bytes including headers;
it fits the new resource bounds but is a lower bound because unified was
incomplete. This single-VM run is functional diagnostic evidence.
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
These are historical isolated checks; complete hosted acceptance is recorded
for B above.

The [account-console budget comparison](browser-account-console-budget-summary.json)
keeps its accessibility checks intact. The hosted diagnostic exceeded the
default 30-second whole-test budget while scanning and capturing eight panes
in three registers. Unchanged current and baseline specs both passed fresh
isolation at two CPUs (22 seconds) and one CPU (26.5–26.6 seconds); both
timed out under an explicit 0.5-CPU stress condition. A case-local 90-second
budget passed that same stress condition on both sources (51.4–51.8 seconds),
with all 24 axe scans, 24 captures and original assertions retained. This is
constrained-runner proof, not a baseline Cloud Build run. The
[remaining unified preflight](browser-unified-preflight-summary.json) passed
144 cases with six existing viewport exclusions and no failures or flakes;
all 28 database/test commands succeeded. Its private local DB/container and
scratch were cleaned. It is targeted preflight, not a complete hosted pass.

The [isolated boundary rehearsal](browser-ci-boundary-probe-summary.json)
used operator-authored synthetic bundles with reviewed controls `2786447`.
A valid bundle passed; a child that rewrote its own enforcer still could not
make the parent accept the invalid bundle. The parent enforcer hash stayed
unchanged and matched the reviewed snapshot. Child attempts to create verification receipts or builds returned
403, and all eight exact test objects were deleted and verified absent.
This proves bounded acceptance/rejection and the CI-account boundary; valid
branch test-report forgery is outside the claim. No browser suite ran in this
rehearsal, and numeric VM instance IDs were unavailable.
The [current provider-step checker](browser-ci-provider-step-check-summary.json)
was separately exercised against three actual hosted metadata responses using
reviewed controls `03690ff`: both completed fixture step sequences passed,
while the earlier allowed browser-step failure was rejected. A step-gate pass
alone does not accept a tampered artifact; the separate artifact validator
remains required.

The [framing and animation receipt](browser-framing-and-animation-summary.json)
also reproduces the curator capture and mobile pointer failures on the baseline.
The corrected helper measures the scroll-padding budget and preserves full
containment; the formatting test waits for the actual entrance animation and
retains its hover, click and keyboard assertions. All five affected cases pass.
A wider three-file run passed 54 cases with 11 existing skips and one intermittent
failure in an untouched shortcut test. That test passed three isolated retries
on both sources; the complete composer-file retry passed 12 cases with six
existing skips and no failures, using unchanged source.

The parent validator checks the child-reported Docker result, complete suite
outputs and a readable archive matching the manifest and embedded result.
The packager writes candidate success JSON atomically only
after archive creation. Actual tar creation failure and nonzero/missing outer
Docker receipts are regression-tested. These checks run separately on the
parent VM; a child can still fabricate otherwise valid reports about its own
branch-authored tests. Credential aliases such as `apiKey`
and `Proxy-Authorization` are removed; malformed JSON is omitted with a
bounded count, preserving the actual suite stage and preventing a pass.
`result.json` alone is a candidate receipt, never sufficient success evidence.

The child publishes only synthetic PNG and redacted JSON into a private
bucket with a 14-day lifecycle. It publishes failure evidence before enforcing
success and requires all suite groups' expected outputs. The parent publishes
its acceptance receipt into a separate private bucket. GitHub status/check
publication is unavailable for the current webhook integration; actual Cloud
Build results and private artifacts must be inspected instead.

## Source verification

The root agent reran the full suite against the reviewed follow-up working tree:
3,231 tests, 25,601 assertions and one existing dedicated-schema guard skip on
PHP 8.4.26 in 121.49 seconds, as recorded in the
[source verification receipt](local-source-verification.json).
The pointer fix and retained delivery assets passed 35 Node tests,
generated-asset checks and the current Imladris baseline check.
The CI controls additionally passed ten scoped contract tests with
81 assertions on PHP 8.4.26 and 8.2.34, plus the uploader contracts above.
The revised isolated parent/child controls passed 16 targeted tests with 136
assertions in the actual PHP 8.2.34 runner, with no PHP warnings after using a
writable cache path (1.554 seconds); the Python provider contracts exercise
missing/failed/nonzero provider steps as well as artifact enforcement.
These full local results belong to source `d9b2295e` (B). The planned final
documentation commit (C) will retain main's two Imladris checksum metadata
files until merge, following [ADR 0024](../../adr/0024-imladris-admin-account-adoption.md).
Its temporary checksum-guard difference is not described as a passing full
local run. After the merge's automatic deployment and live current hashes are
verified, the immediately following main commit (R) will record that deployed
release using the existing CLI, then refresh its reviewed presentation checksum
under ADR 0024 and [ADR 0041](../../adr/0041-asset-release-retention.md).
Recording the release can prune expired retained files and change the checksum;
R is not promised to share B's entire asset tree or checksum metadata.
Application, browser-test and trusted-control source, plus current entrypoint,
lazy-chunk and font bytes, will be compared to B. Fresh R local checks and its
exact automatic production deployment will be verified separately. Merge/R
and these production results are still pending.

The [exact-B review receipt](browser-ci-d9b-review-summary.json) records
completed CodeRabbit and Vortex reviews, the checksum-sequencing correction,
and provider availability limits. GitHub's browser jobs never started due to
billing. The qlty status reported success while its description listed 16
issues; their authenticated details were unavailable and are not represented
as cleared. Older review summaries are identified as stale.
Source verification, hosted browser results and production rollout remain
separate receipts.
