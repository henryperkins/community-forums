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

Secrets (Secret Manager), read by the runtime account only:

| Secret | Env var | Notes |
| --- | --- | --- |
| `retroboards-app-key` | `APP_KEY` | Generated 2026-10-09 for the move; the container's key was unreadable (Cloudflare secrets are write-only) |
| `imladris-boards-app-password` | `DB_PASSWORD` | `retroboards_app` |
| `retroboards-cloudflare-email-token` | `CLOUDFLARE_EMAIL_API_TOKEN` | Version 1 stored 2026-10-09; email-only account permission (§7) |
| `retroboards-deploy-webhook` | — | The deploy webhook's shared secret (§5) |

Service accounts, each holding only what it uses:

| Account | Can |
| --- | --- |
| `retroboards-app` | Runs the service and jobs: `cloudsql.client`, read its secrets, `storage.objectUser` on the data bucket |
| `retroboards-edge` | Invoke the service (`run.invoker` on it). Its key is the Worker's `GCP_INVOKER_KEY` (§6) |
| `retroboards-scheduler` | Run the cron jobs (`run.invoker` on each job) |
| `retroboards-deploy` | Cloud Build: push images, `run.developer`, act as `retroboards-app`, write build logs |

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

Mail goes through Cloudflare's SMTP relay (`MAIL_DRIVER=cloudflare_smtp`) with
an account API token scoped to **Email Sending: Edit**. The old container token
could not be read back from Cloudflare. A new email-only token was created on
2026-10-09 and stored as version 1 of `retroboards-cloudflare-email-token` in
Google Secret Manager. The `retroboards-app` runtime account has secret read
access. `configure.sh` applied the secret to the web service and all four jobs.

**Live worker check, 2026-10-09:** execution `retroboards-cron-5m-xv7lq`
succeeded. The email result reported `blocked_reason=none`; `sent`,
`suppressed`, `retrying`, `failed`, `skipped` were all zero. The empty queue
verified configuration availability without testing message acceptance or
recipient delivery. A separate no-message probe from the same Cloud Run job
(`retroboards-cron-5m-2j22c`, PHP 8.2.34) authenticated with the new token
(`235`) and completed `NOOP` (`250`) with no cURL error. It submitted no
message; SMTP message acceptance and recipient receipt remain untested.

The Cloud Run image uses PHP 8.2. The SMTP transport must keep its response
capture compatible with that runtime; `CURLOPT_DEBUGFUNCTION` requires PHP
8.4. An empty outbox does not exercise that transport path.

If the mailer is unconfigured, `worker:email` returns `sender_unconfigured` and
the outbox **holds** messages. After configuration, eligible queued messages
are retried by scheduled ticks. A successful job alone does not prove SMTP
acceptance or receipt; inspect the email worker result and delivery records.

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

**Execution record:** source retirement is prepared; deployment and hosted
resource deletion are pending verification. Record the active Worker version,
container application absence, secret-name inventory, old R2 bucket absence,
and post-cleanup production checks when complete.

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

## 11. Cost

Monthly estimates at us-east4 list prices (Cloud Billing Catalog, 2026-10-09):

| Component | Cost |
| --- | --- |
| Cloud Run service, request-based, scale to zero | ≈ $0–5: $0.000024/vCPU-s and $0.0000025/GiB-s while serving; free tier 180k vCPU-s, 360k GiB-s and 2M requests per billing account |
| Cron jobs | ≈ $0–2: the 5-minute job is ~11 s × 8,640 runs ≈ 95k vCPU-s at $0.000018, inside the 240k vCPU-s jobs free tier |
| Cloud Scheduler | $0.10: 4 jobs, 3 free per billing account |
| Cloud SQL `db-f1-micro` ($0.0112/h), 10 GB SSD, backups and binlog | ≈ $10–11. `db-g1-small` ($0.0375/h) was ≈ $30 |
| Cloud Storage, Artifact Registry, Cloud Build, Logging | ≈ $0, within free tiers at this size |
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
