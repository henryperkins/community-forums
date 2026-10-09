# RetroBoards — Cloud Run deployment runbook

Operating procedure for the production origin: the PHP app on **Google Cloud
Run**, created 2026-10-09 and decided in ADR 0047. Everything on the Google side
lives in project `rising-woods-449718-v6` (number `616731728350`), region
**us-east4**, next to the Cloud SQL instance.

The Cloudflare **Worker** is still the front door: canonical host, static
assets, the visitor's IP, and the domains. It and the dormant Cloudflare
container are covered by [`deployment-cloudflare.md`](deployment-cloudflare.md).

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
| `retroboards-cloudflare-email-token` | `CLOUDFLARE_EMAIL_API_TOKEN` | **Not created yet**, see §7 |
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
an API token. The container's token could not be read back from Cloudflare.
Until `retroboards-cloudflare-email-token` exists, the mailer reports itself
unconfigured. `worker:email` then returns `sender_unconfigured` and the outbox
**holds** every message. Nothing is dropped; it drains on the first tick after
the token arrives.

1. Cloudflare dashboard → My Profile → API Tokens → Create Token → Custom. Add
   the account permission **Email Sending: Edit** for the account that owns
   `candidary.online`.
2. Store it without echoing it:

   ```sh
   read -rs TOKEN && printf %s "$TOKEN" | gcloud secrets create retroboards-cloudflare-email-token \
     --replication-policy=automatic --data-file=- && unset TOKEN
   gcloud secrets add-iam-policy-binding retroboards-cloudflare-email-token \
     --member=serviceAccount:retroboards-app@rising-woods-449718-v6.iam.gserviceaccount.com \
     --role=roles/secretmanager.secretAccessor
   deploy/cloudrun/configure.sh        # picks the secret up for the service and jobs
   ```

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

## 9. Removing the dormant Cloudflare container

Nothing calls `ForumContainer` since the move, so it sleeps and costs nothing.
It stays declared so `wrangler rollback` to a pre-move version still finds its
Durable Object class. Remove it once the origin has proved itself, and only
deliberately: **after this, rolling back to the container needs a new
deploy, not `wrangler rollback`.**

1. In one PR:
   - delete the `ForumContainer` class and the `@cloudflare/containers`
     dependency;
   - remove the `containers` and `durable_objects` config, and the vars only
     the container read (`DB_*`, `R2_*`, `UPLOADS_PATH`,
     `PACKAGES_STORAGE_PATH`, `RATELIMIT_PATH`, `RUN_MIGRATIONS`, `MAIL_*`,
     `SESSION_SECURE`, `SECURITY_HSTS`, `TRUSTED_PROXIES`, `APP_ENV`,
     `APP_DEBUG`). Keep `APP_URL` and `ORIGIN_URL`;
   - append the migration
     `{ "tag": "v2", "deleted_classes": ["ForumContainer"] }`;
   - update `CloudflareDeploymentContractTest`.
2. After it deploys:
   - `npx wrangler containers list`, then delete the `retroboards-forumcontainer`
     application if it is still listed;
   - `npx wrangler secret delete` the container-only secrets: `APP_KEY`,
     `DB_PASSWORD`, `DB_SSL_CA_PEM`, `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`
     and `CLOUDFLARE_EMAIL_API_TOKEN`.
3. The R2 bucket `retroboards-data` holds only pre-move uploads, which were
   deliberately not migrated. Delete it when nobody needs them.

## 10. Rolling back to the Cloudflare container

Only while §9 has not happened. `faf20758-ff41-415a-8d99-59f1605a43ef` is the
last container-backed Worker version (PR #83).

```sh
npx wrangler rollback faf20758-ff41-415a-8d99-59f1605a43ef
for j in 5m 6h 0310 0700; do gcloud scheduler jobs pause retroboards-cron-$j --location=us-east4; done
```

Then put back what the move changed:

- **Cron triggers are not part of a version.** Put the pre-move `crons` list
  back into `wrangler.jsonc` (see `git show 16529ede:wrangler.jsonc`) and run
  `npx wrangler triggers deploy` from the repository root.
- **The database network.** The container reaches Cloud SQL by public IP over
  TLS, and `0.0.0.0/0` was removed from the authorized networks on 2026-10-09.
  Re-add it:
  `gcloud sql instances patch imladris-boards --authorized-networks=0.0.0.0/0`.
- **`APP_KEY` differs.** Data encrypted on Cloud Run (MFA secrets, OAuth and
  package secrets) only decrypts under the new key. Give the container the same
  one: `gcloud secrets versions access latest --secret=retroboards-app-key |
  npx wrangler secret put APP_KEY`.
- **Uploads made on Cloud Run** are in the Cloud Storage bucket, not in R2.

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
