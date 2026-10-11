# Cloudflare origin retirement and Cloud Run email configuration

This records the approved retirement of the unused Cloudflare container origin
and pre-move R2 uploads, plus email configuration and the PHP 8.2 SMTP repair.
PR [#86](https://github.com/henryperkins/community-forums/pull/86) merged at
**2026-10-09 05:25:14 UTC** as
`e2b7506353fcb928377a6461cc4c41ac123122ad`. Its tree is identical to the tested
PR head `4ec1d2fd`: both resolve to
`6ee314a1a2ac5744b902cb5163880d36e0cfcaee`.

**Hosted retirement completed after both main deployments succeeded.** The
old container application, six legacy Worker secrets and pre-move R2 bucket
were deleted and their absence checked. Google storage retention, post-cleanup
live asset/route checks, guest browser comparison, and manual plus scheduled
5-minute job executions passed, with the evidence boundaries recorded below.

## Scope and recovery boundary

The change removes `ForumContainer`, its Worker binding, the container
dependency and container-only vars. It retains migration `v1` and adds the
`v2` class deletion, while preserving Cloud Run forwarding, canonical domains,
static assets and the empty Worker cron list. The old container and pre-move
R2 bucket `retroboards-data` are the approved retirement targets; the Google
bucket `rising-woods-449718-v6-retroboards-data` holds current uploads and stays.

The class deletion is irreversible. Recovery uses Cloud Run revisions and
matching cron images, preserving the current database schema, keys and Google
storage. Pre-retirement Worker versions that need the deleted class, secrets
or old storage are unsupported rollback targets. The procedures are in
[Cloud Run runbook §9–§10](../../runbooks/deployment-cloud-run.md).

## Local release checks

| Evidence | Recorded result | Boundary |
| --- | --- | --- |
| Full PHPUnit suite on final code | 3,208 tests, 25,405 assertions; one existing dedicated-schema migration guard skip | Local real test database; the guarded migration rehearsal was not run by this suite |
| Worker/asset Node suite | 35 passed | Includes signed origin routing and parsed Wrangler migration/config contracts |
| Fresh dependency install | `npm ci` passed | Reconciled lockfile without `@cloudflare/containers` |
| Asset build and reproducibility | `npm run build` and `npm run check:assets` passed | Local generated assets, not a production delivery receipt |
| Wrangler deployment dry run | Passed | Packaging/config validation without applying the class deletion |
| Diff validation | `git diff --check` passed | Tested PR source, before the evidence-only follow-up |
| Independent cleanup review | No issues found | Active origin/auth/assets/canonical modules and Cloud Run deployment files were unchanged |

The SMTP repair replaces PHP 8.4-only `CURLOPT_DEBUGFUNCTION` with incoming
server-response capture through `CURLOPT_HEADERFUNCTION`, available in the
production PHP 8.2 runtime. TLS verification and provider Message-ID extraction
remain enforced; verbose authentication traces are no longer collected.

A fake loopback SMTPS exchange on **PHP 8.2.34** exercised the repaired mailer
through TLS, AUTH and DATA, returned its synthetic provider ID and emitted no
credential trace. The real-cURL failure test checks a typed `MailException`
without exposing the token. These checks verify the transport against a local
synthetic server, without sending mail through the real provider.

## Live email evidence before the retirement deploy

An email-only Cloudflare account token was stored as version 1 of Google
Secret Manager `retroboards-cloudflare-email-token`. Runtime read access was
granted and `configure.sh` wired it into the web service and all four jobs.
No token values are included in this evidence.

| Cloud Run execution | Observed result | What it establishes |
| --- | --- | --- |
| `retroboards-cron-5m-xv7lq` | Succeeded; `blocked_reason=none`; `sent`, `suppressed`, `retrying`, `failed`, `skipped` all zero | Configured sender and an empty outbox; no message transport was exercised |
| `retroboards-cron-5m-2j22c` | PHP 8.2.34; AUTH `235`, NOOP `250`, `curl_errno=0`, `authenticated=true`, `message_submitted=false` | The new token authenticated to the actual SMTP relay from the Cloud Run runtime; no message was submitted |

Actual-provider message acceptance and recipient receipt remain **untested**.
An empty normal cron tick and a successful no-message authentication probe do
not establish either.

## Production browser comparison

The guest browser baseline in [baseline-summary.json](baseline-summary.json)
was captured at **2026-10-09 05:17:42.452 UTC**, before retirement deployed.
[post-retirement-summary.json](post-retirement-summary.json) records the same
read-only headless Chromium smoke at **05:33:07.549 UTC**, after deletion.

| Scope | Baseline and post-retirement result |
| --- | --- |
| Viewports | 1280×900 and 375×812 |
| Guest home and login | HTTP 200 in all four direct cases |
| `boards.hperkins.blog/login?next=%2Fmessages` | HTTP 301 to the canonical login, then 200; `/messages` destination preserved at both widths |
| Login form | POST method; identifier/email, password, CSRF and submit controls present |
| Layout and runtime | No horizontal overflow or page JavaScript exceptions |
| Self-hosted CSS, JS and fonts | All 12 per-page resources HTTP 200; resource sets unchanged across all six cases |
| Sanitized screenshots | All six baseline/post-retirement byte hashes identical |
| Existing Cloudflare Analytics CSP condition | One blocked beacon, console error and request failure per navigation before and after; unchanged |

There were no application HTTP failures. The existing Analytics beacon from
`static.cloudflareinsights.com` remains blocked by strict CSP; this is a
baseline condition, not a retirement regression.

Both copied JSON reports contain only route/resource URLs, status codes, DOM
geometry, form-presence booleans, error counts and comparison results. They
were inspected for raw HTML, member text, request headers and token values
before inclusion; none are present. Member content and screenshots are not
copied into this record. The comparison uses guest navigation and does not
submit the login form.

## Main deployment and hosted cleanup

| Deployment | Identifier | Verified result |
| --- | --- | --- |
| Workers Builds main release | `18cb9e37-9023-4322-a36a-935723de3e7a` | SUCCESS; deployed version `783c386c-6529-4476-a62e-c382e94f28c8` at 05:26:44.269 UTC |
| Google Cloud Build | `38c888ab-c288-450f-a337-472b197875ac` | SUCCESS; finished 05:30:38.707 UTC |
| Cloud Run web revision | `retroboards-00007-gfn` | 100% traffic; service and all four cron jobs use the PR #86 merge image |
| Worker secret cleanup deployment | `e764a9a8-2600-473d-8579-77d78cd4cbd0` | Active version `5a035f03-e0cd-48fd-a2a0-09608410c110` at 100%; deployed 05:32:22.980964 UTC |

Resource deletion was scoped to the retired origin:

- Container application `a033c1b0-0a55-4af4-a272-6694aa8bfc22`
  (`retroboards-forumcontainer`) was deleted and absent from the application
  list. Four unrelated Candidary container applications were retained. A full
  Durable Object namespace list returned 14 remaining namespaces and no
  old namespace `da69326860374a049a2369af40c89cc1` or `ForumContainer` class.
- The six container-only Worker secrets were removed by a bulk binding patch.
  The resulting Worker has only `APP_URL`, `ASSETS`, `GCP_INVOKER_KEY` and
  `ORIGIN_URL` bindings. The secret change generated the final active version
  shown above after the successful main release.
- Old R2 bucket `retroboards-data` held 38 objects totalling 4,923,029 bytes.
  The objects were deleted, emptiness was verified, bucket deletion returned
  HTTP 200, and a filtered bucket list confirmed absence. Object names and
  contents are not included in this evidence.

Current infrastructure was checked after cleanup:

- Google bucket `rising-woods-449718-v6-retroboards-data` exists in
  `US-EAST4`, with `public_access_prevention=enforced`.
- Cloud SQL remains `db-f1-micro` with `sslMode=ENCRYPTED_ONLY` and no
  authorized networks. The authenticated connector remains the database path.

[live-summary.json](live-summary.json), captured at
**05:32:56.490230 UTC**, records post-cleanup checks:

- `/healthz` returned HTTP 200 with `status=ok`, `database=ok`.
- Home and login returned 200; login retained CSRF/email/password fields.
- Unauthenticated direct Cloud Run access returned 403.
- The alias returned 301 preserving `/login?next=%2Fmessages`.
- All 64 published assets returned 200 and matched their expected SHA-256.
  The 37 content-hashed assets retained the expected immutable cache policy.
  Manifest version was `85b4bc099e7bdac1`.

Manual execution `retroboards-cron-5m-c87d7` ran the normal job commands
successfully on the new merge image, completing at **05:33:11.170 UTC**. Its
email result at 05:33:05.801 UTC reported all five counters zero and
`blocked_reason=none`.

The subsequent **scheduled** execution `retroboards-cron-5m-qppsb` was created
at 05:35:04.672470 UTC by
`retroboards-scheduler@rising-woods-449718-v6.iam.gserviceaccount.com`
(`run.googleapis.com/creator`). Cloud Scheduler's last attempt was
05:35:04.199975 UTC with a successful empty status object. The execution
completed successfully at **05:35:37.257720 UTC**; its email result at
05:35:33.918506 UTC again had all five counters zero and
`blocked_reason=none`. This proves a post-cleanup scheduled tick on the new
image, separately from the manual execution.

All four Cloud Scheduler entries were enabled on their unchanged UTC
schedules. The other three, longer-interval schedules had not fired during
this verification window; their current image/configuration was checked,
without claiming post-retirement scheduled execution evidence for them.

## Repaired SMTP transport on the deployed image

[smtp-runtime-summary.json](smtp-runtime-summary.json) records execution
`retroboards-cron-5m-l8k29`, which completed successfully at
**05:35:09.255331 UTC**. Its result event at 05:35:07.101259 UTC confirms
PHP **8.2.34**, a typed `MailException` from real-cURL connection refusal on a
bound, non-listening loopback port, and no synthetic token in the error. The
probe submitted no external email; it read only aggregate outbox counts,
which were zero.

The runtime mailer hash matches the PR #86 merge, and the execution image
digest is
`sha256:a3a6455d3a0586db07481eb94a1f5bda49924a67b0e8a32a80a3f2b010c81c0a`.
The diagnostic used an execution-only argument override; persistent job
generation, image and normal command arguments were identical afterward.
This checks the repaired transport failure path in the deployed runtime. It
adds no real-provider message acceptance or recipient receipt evidence.

## Automation and coverage limits

- GitHub browser jobs were blocked before startup by the existing billing
  lock; CircleCI was also unavailable. Neither is a passing hosted test lane.
- The PR preview reported Cloudflare error `10211`: Durable Object migrations
  require the full `wrangler deploy` used by the main release. A preview upload
  does not establish migration execution or production deployment.
- The browser baseline uses guest GET requests in headless Chromium on Linux.
  It does not test an account login POST, signed-in writes, physical-device
  behavior or assistive technology.
- No real message was submitted or delivered. Live SMTP AUTH/NOOP and the
  synthetic SMTPS transport are separate evidence lanes.
