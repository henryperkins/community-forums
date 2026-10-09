# RetroBoards — Cloud Run deployment runbook

Operating procedure for the production origin: the PHP app on **Google Cloud
Run**, created 2026-10-09 and decided in ADR 0047. Everything on the Google side
lives in project `rising-woods-449718-v6` (number `616731728350`), region
**us-east4**, next to the Cloud SQL instance.

The Cloudflare **Worker** is still the front door: canonical host, static
assets, the visitor's IP, and the domains. Its current procedure and the retired
container's history are in [`deployment-cloudflare.md`](deployment-cloudflare.md).

> **Merging to `main` deploys both halves.** Workers Builds deploys the Worker
> and assets; the `retroboards-main` Cloud Build trigger builds the image from
> the merge commit and rolls it out to the service and the cron jobs (§3).
> Neither waits for the other. Both finish within minutes, and during that
> window old templates can meet new assets or the reverse, as when the
> container image lagged the Worker.
>
> **Before merging**, check for migrations the same way as before
> (`git diff --name-only origin/main...HEAD -- database/migrations/`, three
> dots, from the PR branch). The new revision runs them on boot. If that fails,
> the revision never passes its startup probe and **the previous revision keeps
> serving**, unlike the container, whose failed boot took the site down. A DDL
> statement that half-applied before the failure still stays applied.

## 1. What runs where

| Piece | Resource | Notes |
| --- | --- | --- |
| Front door | Cloudflare Worker `retroboards` on `forum.candidary.online` | Forwards dynamic requests to `ORIGIN_URL` with an ID token (`worker/origin.mjs`) |
| Origin | Cloud Run service `retroboards` | Image from `Dockerfile`, Apache on 8080, gen2, 1 vCPU / 1 GiB, concurrency 16, **0–1 instances**, startup CPU boost, IAM-only |
| URL | `https://retroboards-616731728350.us-east4.run.app` | Also answers as `retroboards-3h5icvhrwq-uk.a.run.app`; neither is reachable without a token |
| Database | Cloud SQL `imladris-boards` (MySQL 8.4, `db-f1-micro`) | Through Cloud Run's built-in connector: Unix socket `DB_SOCKET=/cloudsql/rising-woods-449718-v6:us-east4:imladris-boards`. **No authorized networks**: nothing reaches it by IP |
| `/data` | Bucket `rising-woods-449718-v6-retroboards-data` | Cloud Storage FUSE volume, `uid=33;gid=33` (www-data); holds `media/` and `packages/` |
| Cron | Cloud Run jobs `retroboards-cron-{5m,6h,0310,0700}` | Started by Cloud Scheduler entries of the same names (§4) |
| Images | `us-east4-docker.pkg.dev/rising-woods-449718-v6/retroboards/app` | Tagged with the commit SHA and `latest`; cleanup keeps the newest 10, deletes the rest after 30 days |
| Deploys | Cloud Build trigger `retroboards-main` (us-east4) | GitHub push webhook → `deploy/cloudrun/cloudbuild.yaml` (§3, §5) |

Secrets (Secret Manager), read only by the accounts that need each value:

| Secret | Env var | Notes |
| --- | --- | --- |
| `retroboards-app-key` | `APP_KEY` | Generated 2026-10-09 for the move; the container's key was unreadable (Cloudflare secrets are write-only) |
| `imladris-boards-app-password` | `DB_PASSWORD` | `retroboards_app`; app runtime and the aggregate monitoring collector read it |
| `retroboards-cloudflare-email-token` | `MAIL_CLOUDFLARE_API_TOKEN` | Version 1 stored 2026-10-09; email-only account permission (§7) |
| `retroboards-deploy-webhook` | — | The deploy webhook's shared secret (§5) |

Service accounts, each holding only what it uses:

| Account | Can |
| --- | --- |
| `retroboards-app` | Runs the service and jobs: `cloudsql.client`, read its secrets, `storage.objectUser` on the data bucket |
| `retroboards-edge` | Invoke the service (`run.invoker` on it). Its key is the Worker's `GCP_INVOKER_KEY` (§6) |
| `retroboards-scheduler` | Run the cron jobs (`run.invoker` on each job) |
| `retroboards-deploy` | Cloud Build: push images, `run.developer`, act as `retroboards-app` and `retroboards-monitor`, write build logs |
| `retroboards-monitor` | Read database aggregates through the connector with the existing app DB credential; view cron executions and Cloud SQL backup metadata (§8). No app key, mail token or data bucket access |
| `retroboards-browser-ci` | Write browser build logs and create synthetic artifacts in its private CI bucket (§5a). No production secrets, database, deployment or invocation rights |

## 2. The request path and the visitor's IP

`browser → Worker → Google Front End → container`. Measured 2026-10-09:

- The Worker replaces any client `X-Forwarded-For` with `CF-Connecting-IP`.
  Google's front end **appends** the caller, so the app sees
  `X-Forwarded-For: <visitor>,<worker egress>` from peer `169.254.169.126`
  (Google's link-local proxy, the address Apache logs for every request).
- `TRUSTED_PROXIES` (`deploy/cloudrun/env.yaml`) trusts `169.254.0.0/16` and
  Cloudflare's published ranges, so `ClientIdentifier` skips both hops and lands
  on the visitor. `CloudRunDeploymentContractTest` replays those headers against
  the deployed list.
- That trust is safe only because the service requires an ID token that only
  the Worker can mint. **Never** grant `allUsers` the invoker role: anyone could
  then put a forged `X-Forwarded-For` in front of the app.
- The token travels in `X-Serverless-Authorization`, which Cloud Run checks and
  strips. The visitor's own `Authorization` header (the forum API's bearer
  tokens) reaches the app untouched.
- **`/healthz` is reserved by Cloud Run's front end** and never reaches the
  container. The Worker sends it upstream as `/healthz/`, which the app routes
  identically. Other paths ending in `z` (`/t/1-quiz`, `/readyz`) are not
  affected; tested 2026-10-09.

## 3. Deploys

Normal path: merge to `main`. GitHub's push webhook calls the
`retroboards-main` trigger, whose filter only accepts `refs/heads/main`. The
build (`deploy/cloudrun/cloudbuild.yaml`) then runs these steps:

1. Fetches exactly the pushed commit from GitHub. The repository is public, so
   no credentials are involved.
2. Builds the image with BuildKit, reusing `latest`'s layers through the inline
   cache. The PHP extension build is the slow, cached part.
3. Pushes the image as `<sha>` and `latest`.
4. Runs `gcloud run deploy retroboards --image=…:<sha>`. The new revision
   migrates on boot and takes traffic only once it is serving.
5. Updates every job labelled `app=retroboards,role=cron` to the same image.
   Finding none fails the build.

Watch it:

```sh
gcloud builds list --region=us-east4 --limit=5
gcloud builds log <BUILD_ID> --region=us-east4
gcloud run revisions list --service=retroboards --region=us-east4 --limit=3
```

Deploy any pushed commit by hand (same steps):

```sh
gcloud builds submit --no-source --region=us-east4 \
  --config=deploy/cloudrun/cloudbuild.yaml \
  --substitutions=_SHA="$(git rev-parse HEAD)"
```

Roll the service back without a build. Every revision keeps its image, unless
the image is old enough for the registry cleanup:

```sh
gcloud run revisions list --service=retroboards --region=us-east4
gcloud run services update-traffic retroboards --region=us-east4 --to-revisions=<REVISION>=100
# then pin the jobs to that revision's image:
gcloud run jobs update retroboards-cron-5m --region=us-east4 --image=<IMAGE>   # and the other three
```

`update-traffic` pins the service until the next deploy, which sends 100% of
traffic to the new revision again.

## 4. Configuration: `configure.sh` and `env.yaml`

Deploys change only images. Everything else lives in two files:

- `deploy/cloudrun/env.yaml` holds the non-secret environment, the counterpart
  of `wrangler.jsonc` `vars`.
- `deploy/cloudrun/configure.sh` applies the following:
  - service settings: scaling, resources, the Cloud SQL connector and the
    `/data` volume;
  - the secret wiring;
  - the four jobs and their Cloud Scheduler entries;
  - the deploy trigger's copy of `cloudbuild.yaml`.

To change any of it, edit and run:

```sh
deploy/cloudrun/configure.sh
```

It keeps the image the service already runs, so it never rolls code back. It
converges, so running it twice is harmless. The very first run, before the
service existed, took `IMAGE=<image>`.

The jobs get a copy of `env.yaml` with `RUN_MIGRATIONS=false`. Only the service
migrates, and a deploy updates the jobs after the service, so a cron tick never
runs new code against an old schema.

Cron schedules (UTC), unchanged from the Worker's former triggers. Each tick
runs its commands in order through `retroboards-console-batch`, which carries
on past a failure but still fails the execution:

| Job | Schedule | Commands |
| --- | --- | --- |
| `retroboards-cron-5m` | `*/5 * * * *` | `worker:email`, `worker:webhooks` |
| `retroboards-cron-6h` | `0 */6 * * *` | `worker:registry-refresh` |
| `retroboards-cron-0310` | `10 3 * * *` | `worker:purge-ips`, `worker:attachments`, `worker:packages` |
| `retroboards-cron-0700` | `0 7 * * *` | `worker:digest` |

```sh
gcloud run jobs execute retroboards-cron-5m --region=us-east4 --wait    # run a tick now
gcloud run jobs executions list --job=retroboards-cron-5m --region=us-east4 --limit=5
gcloud scheduler jobs pause|resume retroboards-cron-5m --location=us-east4
```

`configure.sh` does not change whether a schedule is paused. A schedule it
creates starts enabled.

## 5. The deploy trigger (one-time setup, done 2026-10-09)

| Part | Value |
| --- | --- |
| Trigger | `retroboards-main`, us-east4, webhook; substitutions `_SHA=$(body.after)`, `_REF=$(body.ref)`; filter `_REF == "refs/heads/main"`; runs as `retroboards-deploy` |
| Shared secret | Secret Manager `retroboards-deploy-webhook` v1; the Cloud Build service agent can read it |
| API key | "retroboards deploy webhook", restricted to the Cloud Build API |
| GitHub | Repository webhook `694510717`, `push` events, JSON, whose URL carries the key and the secret |

A webhook trigger cannot read the repository without a GitHub App connection,
so the trigger holds an **inline copy** of `cloudbuild.yaml`. After editing that
file, run `configure.sh`, which refreshes the copy. GitHub's delivery log for
the hook (repository Settings → Webhooks) shows each push and Cloud Build's
reply.

## 5a. Hosted browser evidence while GitHub Actions is locked

GitHub Actions currently cannot start jobs because the account is locked for
billing. The latest checked main run (`37888488447`) failed with zero job
steps; there is no CircleCI configuration. Keep
`.github/workflows/browser-evidence.yml` available for billing recovery.
The independent Cloud Build lane uses
`deploy/cloudrun/browser-ci/{configure.py,cloudbuild.json,Dockerfile,run.sh,package.py,upload.py,enforce.py}`.
It is the hosted browser evidence source while Actions cannot run; inspect its
actual build result before calling a commit verified.

The lane fetches an exact source SHA and a separately pinned reviewed control
SHA, runs the existing capture, unified notification/settings and production
Docker upload suites, and uploads sanitized synthetic PNG/JSON evidence to a
private bucket with 14-day expiry. Capture and unified settings use a disposable
MariaDB; uploads use their own Compose project and ports. The suites run
sequentially in one build, and separate builds have separate VMs. Chromium and
WebKit are installed in the runner. Dependency initialization fails immediately.
After a suite fails, the other independent groups still run to collect evidence;
the first nonzero status and failed stage remain the final result.
The outer build step records the actual Docker exit after the runner returns.
The packager publishes its candidate success JSON atomically after the complete
archive; failure artifacts publish before a final validator enforces success.
That validator requires outer Docker exit 0, matching source/control IDs and a
readable archive matching the required manifest; a complete run also requires outputs from all three
suite groups, including every upload browser project.

The dedicated `retroboards-browser-ci` service account can write build logs
and create objects in the evidence bucket. It cannot read production secrets,
connect to Cloud SQL, deploy services or invoke production jobs. Pinning the
reviewed recipe records its provenance; branch test scripts have Docker daemon
access within the build VM. Restricted IAM is the production access boundary.
Neither GitHub credentials nor a production `.env` enter the runner. Published
artifacts omit environment variables, cookies, headers, traces and raw logs.
The uploader uses authenticated HTTPS object creation with `ifGenerationMatch=0`
and checks the creation response's size/checksum. It needs no object read/list/delete
permission and refuses overwriting an existing artifact.

Setup or refresh after pushing a reviewed control commit:

```sh
python3 deploy/cloudrun/browser-ci/configure.py --control-sha=<REVIEWED_CONTROL_SHA>
python3 deploy/cloudrun/browser-ci/configure.py --control-sha=<REVIEWED_CONTROL_SHA> --apply
```

The first command previews the exact plan without API calls. The apply command
converges the restricted account, private expiring bucket, inline webhook
trigger and owner-repository GitHub push hook. It uses the existing protected
Cloud Build webhook configuration; private callback URLs remain out of its
output. Owner-repository branch pushes include PR branches and `main`. Fork
PRs require a deliberate manual run; there is no public unauthenticated
arbitrary-build endpoint.

Run a pushed commit manually with the same lane:

```sh
gcloud builds submit --no-source --project=rising-woods-449718-v6 --region=us-east4 \
  --config=deploy/cloudrun/browser-ci/cloudbuild.json \
  --substitutions=_SHA=<EXACT_SOURCE_SHA>,_CONTROL_SHA=<REVIEWED_CONTROL_SHA>
gcloud builds describe <BUILD_ID> --region=us-east4
# For an authorized operator, download the private result and synthetic archive:
gcloud storage cp gs://rising-woods-449718-v6-retroboards-browser-evidence/<SOURCE_SHA>/<BUILD_ID>/result.json /private/result.json
gcloud storage cp gs://rising-woods-449718-v6-retroboards-browser-evidence/<SOURCE_SHA>/<BUILD_ID>/runner-exit.json /private/runner-exit.json
gcloud storage cp gs://rising-woods-449718-v6-retroboards-browser-evidence/<SOURCE_SHA>/<BUILD_ID>/evidence.tar.gz /private/evidence.tar.gz
python3 deploy/cloudrun/browser-ci/enforce.py --results=/private \
  --outer-result=/private/runner-exit.json --source-sha=<EXACT_SOURCE_SHA> \
  --control-sha=<REVIEWED_CONTROL_SHA>
```

Both SHAs must be full lowercase 40-character commit IDs. The final Cloud Build result, independent `runner-exit.json` and complete
private archive are authoritative for this lane. `result.json` is a candidate
receipt and must not be treated as a pass by itself. GitHub check/status
publication is unavailable with the current webhook integration; a Cloud Build
pass is not a recovered GitHub Actions check. PHPUnit remains a separate local
verification lane. Current setup receipts and coverage limits are recorded in
[the operations evidence](../evidence/cloud-run-operations-2026-10-09/README.md).

## 6. The Worker's key (`GCP_INVOKER_KEY`)

It is a JSON key for `retroboards-edge`, stored only as a Worker secret. Local
copies were shredded. `worker/origin.mjs` signs a JWT with it and exchanges that
at Google for an ID token. The token is cached per isolate until five minutes
before it expires. A failed exchange answers **502** with `Retry-After`, never a
1101.

Rotate it:

```sh
gcloud iam service-accounts keys create /path/new-key.json \
  --iam-account=retroboards-edge@rising-woods-449718-v6.iam.gserviceaccount.com
npx wrangler secret put GCP_INVOKER_KEY < /path/new-key.json && shred -u /path/new-key.json
curl -sS https://forum.candidary.online/healthz    # {"status":"ok","database":"ok"}
gcloud iam service-accounts keys list --iam-account=retroboards-edge@rising-woods-449718-v6.iam.gserviceaccount.com --managed-by=user
gcloud iam service-accounts keys delete <OLD_KEY_ID> --iam-account=retroboards-edge@rising-woods-449718-v6.iam.gserviceaccount.com
```

`wrangler secret put` refuses when the newest Worker version is not the
deployed one. A branch push makes Workers Builds upload a preview version, which
creates exactly that state. Use `npx wrangler versions secret put
GCP_INVOKER_KEY` instead. It adds the secret to a new, undeployed version, and
the next `wrangler deploy` inherits it. That is how the key was installed
before the move.

## 7. Email

Mail uses Cloudflare Email Sending's REST API (`MAIL_DRIVER=cloudflare`),
through the existing `CloudflareMailer` and native `EmailOpsService` path.
`MAIL_CLOUDFLARE_ACCOUNT_ID=a77e479f6736120eadd99973dbeb705e` is ordinary
account configuration in `env.yaml`; it is not a credential. The email-only
API token requires **Email Sending: Edit** on that account and is mapped from
Google Secret Manager to `MAIL_CLOUDFLARE_API_TOKEN`. Version 1 of
`retroboards-cloudflare-email-token` was stored on 2026-10-09 and the
`retroboards-app` account has runtime read access. `configure.sh` applies this
configuration to the web service and all four cron jobs.

**Recipient verification, 2026-10-09:** a native operator test using the
permanent production REST configuration recorded delivery `3` as `sent` with
a transport message identifier on PHP 8.2.34. The intended mailbox received
the exact test subject in its inbox at **07:07:47 UTC**, from the expected
domain; SPF, DKIM and DMARC passed. This verifies one operator test message,
provider acceptance and recipient receipt. It does not test every notification
kind, a backlog drain or future delivery. Sanitized receipts are in
[the operations evidence](../evidence/cloud-run-operations-2026-10-09/README.md).
The REST API returns accepted recipient outcomes rather than a provider message
ID; `CloudflareMailer` records its own `cf-…` transport identifier.

**Why REST:** earlier SMTP probes authenticated (`235`) and completed `NOOP`
(`250`), but the actual sender envelope was rejected (`550`) in execution
`retroboards-cron-5m-m9hgt`, despite the sending domain being onboarded. No
message was submitted by that probe. Successful SMTP authentication and an
empty outbox had masked this backend mismatch; production now uses the REST
backend that accepted and delivered the native test. The alternate SMTP
transport remains PHP 8.2-compatible: server response capture uses
`CURLOPT_HEADERFUNCTION`, with TLS verification and no verbose traces. Its
deployed refused-loopback failure path was verified earlier; see
[the retirement evidence](../evidence/cloudflare-origin-retirement-2026-10-09/README.md).

If the mailer is unconfigured, `worker:email` returns `sender_unconfigured` and
the outbox **holds** messages. After configuration, eligible queued messages
are retried by scheduled ticks. A successful job with zero sends proves neither
provider acceptance nor receipt; inspect the worker result and delivery rows,
then verify the intended mailbox. Do not expose recipients, bodies or token
values in logs or evidence.

To rotate the token:

1. Cloudflare dashboard → My Profile → API Tokens → Create Token → Custom. Add
   only the account permission **Email Sending: Edit** for the account that owns
   `candidary.online`.
2. Add a version to the existing Google secret without echoing the token. Run
   this Bash block; the subshell and exit trap clear the variable even if the
   upload fails:

   ```bash
   (
     set +x
     set -o pipefail
     trap 'unset retroboards_email_token' EXIT
     IFS= read -rs -p 'Cloudflare Email Sending token: ' retroboards_email_token || exit 1
     printf '\n'
     [ -n "$retroboards_email_token" ] || exit 1
     printf '%s' "$retroboards_email_token" | gcloud secrets versions add retroboards-cloudflare-email-token \
       --project=rising-woods-449718-v6 --data-file=-
   )
   ```

   For a new installation where the secret does not exist, replace the final
   pipeline inside that same prompt block with:

   ```bash
   printf '%s' "$retroboards_email_token" | gcloud secrets create retroboards-cloudflare-email-token \
     --project=rising-woods-449718-v6 --replication-policy=automatic --data-file=-
   ```

   Then grant runtime read access once:

   ```sh
   gcloud secrets add-iam-policy-binding retroboards-cloudflare-email-token \
     --project=rising-woods-449718-v6 \
     --member=serviceAccount:retroboards-app@rising-woods-449718-v6.iam.gserviceaccount.com \
     --role=roles/secretmanager.secretAccessor
   ```

3. Run `deploy/cloudrun/configure.sh` to update the web service and every job.
   Run or wait for a 5-minute tick (§4), then verify the worker result and mail
   delivery before revoking the previous Cloudflare token. Keep token values
   out of command arguments, logs and documentation.

## 8. Operations

**Health**:

```sh
curl -sS https://forum.candidary.online/healthz
curl -sS -H "Authorization: Bearer $(gcloud auth print-identity-token)" \
  https://retroboards-616731728350.us-east4.run.app/healthz/    # direct; note the slash
```

**Logs**: Apache's access and error logs reach Cloud Logging through the
entrypoint's `tail`. Its startup lines `tail: … has become inaccessible: Invalid
argument` are benign on Cloud Run. Lines keep flowing; verified with an
`AH01276` error-log entry.

```sh
gcloud logging read 'resource.type="cloud_run_revision" AND resource.labels.service_name="retroboards"' --freshness=1h --limit=50
gcloud logging read 'resource.type="cloud_run_job" AND resource.labels.job_name="retroboards-cron-5m"' --freshness=1h --limit=50
```

**Cold starts**: with `--min-instances=0` the instance shuts down after
roughly 15 idle minutes. The first request afterwards pays the following, then
the request itself:
- platform scheduling and image start;
- the bucket mount;
- the entrypoint's migration check;
- Apache start.

Measured on the first boot: about 1.5 s from instance start to a passing
startup probe. One warm instance (`--min-instances=1`) removes this. At the
idle rates it costs about $13/month for 1 vCPU / 1 GiB.

**Database access** from a workstation: run the Cloud SQL Auth Proxy (v2) with
your gcloud credentials. That is the only way in: the instance has had no
authorized networks since 2026-10-09, so a direct connection by IP times out.
A Unix socket path
must stay under 108 bytes, so bind it in a short directory, or use TCP:

```sh
cloud-sql-proxy --token "$(gcloud auth print-access-token)" --port 13306 \
  rising-woods-449718-v6:us-east4:imladris-boards
```

`retroboards_app` is `REQUIRE NONE` since the move. The connector's link is
encrypted end to end, but MySQL does not see it as a TLS session
(`Ssl_version` is empty), so `REQUIRE SSL` refused it with `1045`. The instance's
`sslMode=ENCRYPTED_ONLY` still refuses every plaintext direct connection. That
was verified after the change: a plaintext login is refused, while the
connector and TLS paths succeed.

### Monitoring and operational alerts

The operations-only `retroboards-monitor` job samples aggregate database,
scheduler and backup state every 15 minutes in UTC. Its collector starts a
read-only SQL transaction and logs counts/ages rather than members, recipients
or message content. It currently reuses the app database user, whose underlying
SQL grants allow writes; the collector's read-only transaction enforces this
probe's query behavior. Its separate Google account has bounded secret,
Cloud SQL and cron-viewer access. It receives no APP_KEY, mail token or storage
mount. The deployment pipeline updates optional jobs labelled
`app=retroboards,role=monitor` to the current app image.

Nine enabled policies notify the privately configured owner email channel:

| Signal | Trigger |
| --- | --- |
| Public `/healthz` | At least two checker locations fail for 3 minutes; TLS and JSON status checked |
| Cloud Run HTTP errors | At least five 5xx responses per 5-minute window, sustained 5 minutes |
| Failed cron or collector execution | Any native failed execution in the 5-minute evaluation window |
| Collector failure or absence | Error log immediately, or no successful heartbeat for 45 minutes |
| Missed scheduled cron | No scheduler-created successful tick after its 30-minute grace; daily jobs exempt until their first due tick after creation |
| Stalled outbox | Oldest queued job due for at least 30 minutes, excluding future retry backoff; 15-minute sampling gives roughly 30–45-minute detection |
| Mail failures | Recent retries, terminal failures or transport blocks in worker/aggregate logs; notification rate capped at one per hour |
| Backup failure | Final `FAILED`/`SKIPPED` backup, or newest successful automated backup older than 36 hours |

An execution failure metric does not identify the initiating actor: a diagnostic
override of an actual cron job can alert too. Diagnostic jobs with different
names are excluded. A successful manual cron execution does not reset the
collector's scheduled-tick check.

Preview, apply and verify from the checked-in configuration:

```sh
python3 deploy/cloudrun/monitoring/configure.py plan
python3 deploy/cloudrun/monitoring/configure.py apply --recipient-file /private/owner.json
python3 deploy/cloudrun/monitoring/configure.py verify --evidence /private/monitoring-summary.json
```

The recipient file contains one `address` key, mode `0600`; never commit it.
See [the operations evidence](../evidence/cloud-run-operations-2026-10-09/README.md)
for policy validation, checker locations, actual collector execution and
notification receipt limits. An enabled channel alone does not prove an alert
reached the mailbox.

## 9. Retiring the Cloudflare container and pre-move R2 uploads

The owner approved retirement on 2026-10-09. Production has used Cloud Run
since the move; the old `ForumContainer` and R2 bucket are no longer a recovery
path. Deleting the Durable Object class removes its state, and deleting
`retroboards-data` removes the pre-move uploads that were deliberately not
migrated. Keep the Google Cloud Storage bucket
`rising-woods-449718-v6-retroboards-data`, which holds current uploads.

The retirement change removes the `ForumContainer` class, the
`@cloudflare/containers` dependency, the `containers` / `durable_objects`
configuration, and all container-only Worker vars. `APP_URL`, `ORIGIN_URL`,
static assets, canonical routing, and the empty cron list stay in the Worker.
The migration history keeps `v1` and appends
`{ "tag": "v2", "deleted_classes": ["ForumContainer"] }`. This deletion
migration is irreversible; pre-retirement Worker versions are not supported
rollback targets (§10).

After the retirement commit deploys through Workers Builds:

1. Verify the active Worker version, `/healthz`, pages and login, hashed static
   assets, the `boards.hperkins.blog` redirect, and denial of unauthenticated
   direct Cloud Run requests. Confirm the Cloud Run service and scheduler
   still run normally.
2. List Cloudflare container applications and delete `retroboards-forumcontainer`
   if the deployment has not already removed it. Confirm it is absent.
3. Delete the container-only Worker secrets: `APP_KEY`, `DB_PASSWORD`,
   `DB_SSL_CA_PEM`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, and
   `CLOUDFLARE_EMAIL_API_TOKEN`. Keep `GCP_INVOKER_KEY`; Cloud Run's application
   key and database password stay in Google Secret Manager. The old mail token
   cannot be read back or copied into Google (§7).
4. Delete the objects in the old R2 bucket `retroboards-data`, then delete the
   bucket and confirm it is absent. The current Google bucket is a separate
   resource and must remain.

**Execution record, 2026-10-09:** PR #86 merged as
`e2b7506353fcb928377a6461cc4c41ac123122ad`. Workers Builds and Cloud Build
both succeeded; the web service runs revision `retroboards-00007-gfn`, and
the service plus all four jobs use the merge image. The retired container
application and its Durable Object namespace are absent, the six
container-only Worker secrets were deleted,
and the old R2 bucket was deleted after its 38 objects (4,923,029 bytes) were
removed and emptiness verified. After secret cleanup, active Worker version
`5a035f03-e0cd-48fd-a2a0-09608410c110` serves 100% of traffic with only
`APP_URL`, `ASSETS`, `GCP_INVOKER_KEY` and `ORIGIN_URL` bindings. The current
Google bucket remains in us-east4 with public access prevention enforced; SQL
authorized networks remain empty. Post-cleanup health, guest pages/login,
alias routing, all 64 published asset hashes and cache expectations, direct
Cloud Run denial, and manual plus scheduled 5-minute job executions passed.
The other three schedules were enabled but had not fired during verification.
Deployment identifiers, resource receipts, browser comparison and coverage
limits are in
[the retirement evidence](../evidence/cloudflare-origin-retirement-2026-10-09/README.md).

## 10. Recovery after container retirement

Roll the PHP app back by switching Cloud Run traffic to a known-good revision
and pinning all four cron jobs to its image (§3). Keep the current database,
Secret Manager keys, and Cloud Storage uploads. A revision rollback does not
undo additive migrations; the selected image must support the current schema.
The next deploy sends traffic to its new revision again.

For a Worker regression, inspect `npx wrangler deployments list` and choose a
known-good version that includes the retirement migration and has no
`ForumContainer` binding. A pre-retirement Worker version may depend on the
deleted class, secrets and R2 data, so do not use it as a `wrangler rollback`
target. If the needed code predates retirement, restore that code in a new
commit with the current Cloud Run forwarding and retirement configuration,
then deploy it through the normal `main` path.

There is no one-command return to the old Cloudflare origin. Restoring a
container origin would be a new hosting change requiring a fresh deployment,
authenticated database access, storage and key configuration, and explicit
scheduler handoff. Keep Cloud SQL's authorized networks closed during recovery.

## 10a. Rehearse backup, storage and key recovery in isolated resources

A revision rollback is not a data restore. For a recovery rehearsal, retain a
successful source backup and restore it into a new disposable SQL instance;
never pass the production instance as the restore target. Keep the source's
authorized networks closed and use the authenticated connector for the scratch
runtime too. Do not run `configure.sh` against scratch resources: it owns
production service/jobs/schedules.

Create the backup and select its successful numeric ID:

```sh
gcloud sql backups create --instance=imladris-boards --project=rising-woods-449718-v6
gcloud sql backups list --instance=imladris-boards --project=rising-woods-449718-v6
```

Use a fresh target name and verify the guards before any restore:

```bash
recover_sql="retroboards-recovery-$(date -u +%Y%m%d%H%M%S)"
recover_backup_id='SUCCESSFUL_BACKUP_ID'
[[ "$recover_sql" =~ ^retroboards-recovery-[0-9]+$ && "$recover_sql" != imladris-boards ]] || exit 1
[[ "$recover_backup_id" =~ ^[0-9]+$ ]] || exit 1
gcloud sql instances create "$recover_sql" --project=rising-woods-449718-v6 \
  --region=us-east4 --database-version=MYSQL_8_4 --edition=ENTERPRISE \
  --tier=db-f1-micro --storage-size=10 --ssl-mode=ENCRYPTED_ONLY --no-deletion-protection
gcloud sql backups restore "$recover_backup_id" --project=rising-woods-449718-v6 \
  --backup-instance=imladris-boards --restore-instance="$recover_sql"
gcloud sql instances describe "$recover_sql" --project=rising-woods-449718-v6 \
  --format='json(state,settings.ipConfiguration,settings.tier)'
```

Confirm no authorized networks before connecting. Create a private temporary
bucket with uniform access/public access prevention. Snapshot current production
object metadata privately, copy each exact source generation into the scratch
bucket, then compare count, sizes and provider checksums. Never empty or overwrite
the production bucket. If no nonempty uploaded content exists, label that limit
and add a clearly synthetic scratch-only fixture to prove mounted byte reads.

Run a temporary IAM-only Cloud Run service/job using the selected immutable app
image, the scratch SQL connector and scratch bucket mount. Bind needed existing
Secret Manager versions by reference, with no plaintext keys in env files or
arguments. Pin `RUN_MIGRATIONS=false`, disable real email and all schedulers, and
use only the scratch targets. Verify schema/migration equality, table checks,
foreign-key orphan counts and stable aggregates without logging private rows.
Verify private HTTP health plus unauthenticated 403, a nonempty mounted fixture
hash, and a synthetic encryption/decryption challenge with the recovered APP_KEY.
Do not run application repair or cron workers during this read-only rehearsal.

After collecting sanitized results, delete only the explicitly named scratch
service, job, copied objects/bucket and SQL instance. Wait for asynchronous SQL
deletion to reach `DONE`; verify every scratch resource is absent. Retain the
source backup, production uploads and Secret Manager versions. The temporary
runtime can use the existing app account for a bounded rehearsal; that account's
production access is not proof of a separate disaster-recovery identity.

**2026-10-09 rehearsal:** backup `1791528734171` was retained successfully;
the isolated restore passed 116 table checks, 198 zero-orphan FK checks, 83
migrations, full column-schema equality and selected stable totals. Private
HTTP/database health and unauthenticated denial passed. Three live zero-byte
objects copied with matching checksums, and a separate synthetic 128-byte
fixture passed mounted SHA-256 verification. APP_KEY version 1 passed a
synthetic recovery challenge. All scratch resources were absent after SQL
deletion completed at 07:14:21.984 UTC. There were no nonempty member uploads
or attachment rows to restore; whole-project loss and off-project key recovery
were not rehearsed. [The recovery receipt](../evidence/cloud-run-operations-2026-10-09/recovery-summary.json)
records those limits. No reusable recovery script was added: each scratch probe
must be reviewed against its exact temporary targets and retained versions.

## 11. Cost

Monthly estimates at us-east4 list prices (Cloud Billing Catalog, 2026-10-09):

| Component | Cost |
| --- | --- |
| Cloud Run service, request-based, scale to zero | ≈ $0–5: $0.000024/vCPU-s and $0.0000025/GiB-s while serving; free tier 180k vCPU-s, 360k GiB-s and 2M requests per billing account |
| Application cron jobs | ≈ $0–2: the 5-minute job is ~11 s × 8,640 runs ≈ 95k vCPU-s at $0.000018, inside the 240k vCPU-s jobs free tier |
| Cloud Scheduler | $0.20: 5 jobs including monitoring, 3 free per billing account |
| Cloud SQL `db-f1-micro` ($0.0112/h), 10 GB SSD, backups and binlog | ≈ $10–11. `db-g1-small` ($0.0375/h) was ≈ $30 |
| Monitoring collector | ≈ $3.30 gross before shared free-tier credits: 2,880 monthly ticks, 1 vCPU / 512 MiB with the one-minute minimum |
| Cloud Storage, Artifact Registry, Logging | Usage dependent; synthetic CI artifacts expire after 14 days |
| Browser Cloud Build lane | Separate usage-based build cost; each owner-repository push runs the complete browser lane |
| Workers Paid plan | $5, shared with the account's other Workers |

Versus about $63–73 for the Cloudflare `standard-1` container that kept itself
awake on cron ticks.

**Tier change, 2026-10-09:** `db-g1-small` → `db-f1-micro` with
`gcloud sql instances patch imladris-boards --tier=db-f1-micro`. The patch
ran 6.5 minutes. The forum answered 503 for 1 minute 42 seconds of it
(04:34:23–04:36:05 UTC) while the instance restarted. Warm time to first byte
through the Worker was unchanged: 10-request medians of 24 ms for `/` and
21 ms for `/login`, against 24 and 24 ms before. Scaling back up is the same
command with `--tier=db-g1-small` and the same short restart.
