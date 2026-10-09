# ADR 0047: Run the PHP origin on Cloud Run behind the Cloudflare Worker

**Date:** 2026-10-09
**Status:** Accepted
**Supersedes:** The Cloudflare Containers origin in
`docs/runbooks/deployment-cloudflare.md` (§1, §6, §9). The Worker, the domains
and the canonical-host policy stay as they were.

## Context

On 2026-10-09 the database moved from PlanetScale, asleep since 2026-09-30 for
want of a payment method, to Cloud SQL in us-east4. That left the app split
across two clouds. A `standard-1` container on Cloudflare (about $63–73/month,
kept awake by five-minute cron ticks) reached the database over the public
internet. That link was encrypted but unauthenticated: Cloudflare mounts
`/etc/hosts` read-only, so the connection could not pin the certificate's host
name.

The owner asked for a cheaper Google option. A VM was weighed and rejected: the
existing image already meets Cloud Run's contract (Apache on 8080, migrations
in the entrypoint, logs on stdout), while a VM adds an OS, patching, backups
and a deploy path to maintain.

## Decision

- The image runs as the Cloud Run service `retroboards` (us-east4, gen2,
  0–1 instances, IAM-only ingress). The single-instance cap stays because the
  rate-limit ledger is a local file.
- The Worker stays the front door. It forwards dynamic requests with a
  Google-signed ID token minted from a dedicated service account key, sent in
  `X-Serverless-Authorization` so the forum API's `Authorization` header passes
  through. A Google load balancer (about $18/month) would buy nothing the
  Worker does not already do.
- The database is reached through Cloud Run's built-in Cloud SQL connector, a
  Unix socket (`DB_SOCKET`): encrypted, IAM-authenticated, and with no
  certificate host name to pin.
- `/data` moves from an s3fs mount of R2 to a Cloud Storage FUSE volume. No
  credentials are needed in the image or the environment. The R2 keys could not
  be read back from Cloudflare's write-only secrets, and the owner asked for no
  data migration.
- Cron moves from Worker triggers that `exec()` in the container to four Cloud
  Run jobs on Cloud Scheduler. Schedules and commands are unchanged. A batch
  runner keeps "in order, carry on past a failure".
- Merge to `main` deploys through a Cloud Build webhook trigger that builds the
  pushed commit. Workers Builds still deploys the Worker. GitHub Actions stays
  unused (billing-locked).
- Configuration is code. `deploy/cloudrun/env.yaml` holds the non-secret
  environment, and `configure.sh` converges the service, jobs, schedules and
  trigger. Secrets live in Secret Manager.
- The container was initially retained unused for a rollback window. The owner
  approved retirement on 2026-10-09: remove its Worker class, binding, dependency
  and configuration, apply the Durable Object deletion migration, and delete
  the old container resources and pre-move R2 uploads (runbook §9).

## Consequences

- Cost falls to about $15–20/month from about $63–73. The database moved to
  `db-f1-micro` the same day, with no measurable latency change (runbook §11).
- A failed boot migration no longer takes the site down: the new revision
  never takes traffic, and the old one keeps serving.
- Scale to zero brings cold starts after about 15 idle minutes (≈1.5 s of
  boot, measured). One warm instance would cost about $13/month.
- `TRUSTED_PROXIES` must trust Google's link-local proxy and Cloudflare's
  published ranges. It is safe only while the service stays IAM-only, and the
  runbook forbids `allUsers`.
- Cloud Run reserves `/healthz`, so the Worker sends it upstream as `/healthz/`.
- New risks: a Google service account key lives in Cloudflare (it can only
  invoke this one service; rotation is in the runbook), and the deploy trigger
  holds an inline copy of `cloudbuild.yaml` that `configure.sh` must refresh.
- `APP_KEY` was regenerated. Rows encrypted under the old key (MFA, OAuth or
  package secrets set up after the 2026-10-09 setup) no longer decrypt; the
  owner accepted starting fresh.
- Retirement removes the old container rollback path irreversibly. Recovery
  uses a Cloud Run revision and its corresponding cron images, retaining the
  current schema, keys and Google storage. Worker rollbacks must use versions
  without the retired class binding (runbook §10). Hosted resource deletion is
  pending verification in the runbook execution record.
- An email-only Cloudflare Email Sending token was stored in Secret Manager
  on 2026-10-09 with runtime read access. Configuration rollout and SMTP
  acceptance are verified separately (runbook §7); an unconfigured sender
  retains queued mail.
- The `0.0.0.0/0` authorized network was removed right after the cutover, so
  only connector and Auth Proxy traffic reaches the database.
