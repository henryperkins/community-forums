# RetroBoards — Cloudflare Containers deployment runbook

Operating procedure for the Cloudflare deployment defined by `wrangler.jsonc`,
`worker/index.js`, `Dockerfile`, and `deploy/`. Commands run from the project
root with `wrangler` authenticated against the account that owns the zone.

> **Cloudflare cannot host the whole stack.** Workers runs JavaScript, Python and
> WebAssembly — not PHP — so the application runs as a *container* fronted by a
> Worker. Cloudflare has no managed MySQL: D1 is SQLite, and Hyperdrive is a
> Workers binding that PDO cannot use. The schema carries **213 `FOREIGN KEY`
> constraints** and **2 InnoDB `FULLTEXT` indexes**, so it needs a real MySQL 8 or
> MariaDB from another provider. **The database is the one piece that is not on
> Cloudflare, and there is no way around that.**

## 1. What the deployment looks like

- **Worker** (`worker/index.js`) on a Custom Domain, so all paths route to it.
  It rewrites the client-IP header and forwards every request to the container.
- **Container**: the existing `Dockerfile`, Apache listening on **8080**
  (`deploy/apache-vhost.conf`), addressed as a Durable Object with the fixed id
  `main` — the app is stateful, so every request must land on one instance
  (`max_instances: 1`).
- **`/data` is an R2 mount.** Container filesystems are ephemeral: on restart,
  local disk is discarded. `deploy/entrypoint.sh` mounts the R2 bucket with s3fs
  before Apache starts, backing `UPLOADS_PATH`, `PACKAGES_STORAGE_PATH` and
  `RATELIMIT_PATH`.
- **Migrations run in the entrypoint** (`RUN_MIGRATIONS=true`), replacing Fly's
  `release_command`. A failure aborts the boot rather than serving traffic
  against a half-migrated schema.
- **Cron workers** are driven by Worker Cron Triggers, which call an RPC method
  that `exec()`s `bin/console` inside the running container.
- **Database**: managed MySQL/MariaDB elsewhere, reached over the public
  internet **with TLS** (`DB_SSL=true`).

## 2. Verify outbound MySQL before anything else

**Do this first — it can invalidate the whole approach.** Cloudflare's docs
describe container egress in terms of HTTP: the ports-`80`/`443`-only
restriction is documented for `enableInternet = false`, and the separate
"non-HTTP traffic" note says only that traffic on other ports is never routed
through `outbound`/`outboundByHost` handlers — i.e. it bypasses interception,
not that it is blocked. Read plainly, a TCP connection to port 3306 should work
with the default `enableInternet = true`, but Cloudflare never affirmatively
documents arbitrary TCP egress.

Prove it before migrating data. Deploy a throwaway container that runs:

```sh
php -r '$c = @fsockopen("your-db-host.example.com", 3306, $e, $s, 5);
        echo $c ? "OPEN\n" : "BLOCKED: $s\n";'
```

If that reports `BLOCKED`, stop: this deployment shape is not viable and the
alternative is a VPS with Cloudflare Tunnel in front.

## 3. Provision the database

Any managed MySQL 8 / MariaDB with a public TLS endpoint. Two requirements the
schema imposes:

- **Foreign keys.** 198 constraints once migrated.
- **InnoDB `FULLTEXT`.** `ft_threads_title` and `ft_posts_body` back search.

### PlanetScale (current deployment)

Verified working on `gcp.connect.psdb.cloud:3306`, server `8.4.9-Vitess`, with
all 78 migrations applied (116 tables, 198 FK constraints, 2 FULLTEXT indexes).
Confirmed by direct probe: FK constraints are **actually enforced** (orphan
inserts rejected with error 1452), `ON DELETE CASCADE` fires, and `FULLTEXT`
index creation succeeds. Requires FK support enabled on an **unsharded**
keyspace; note PlanetScale's own guidance discourages foreign keys.

TLS: PlanetScale serves a publicly-trusted certificate, so no custom CA is
needed — point `DB_SSL_CA` at the image's distro bundle
(`/etc/ssl/certs/ca-certificates.crt`) and leave `DB_SSL_CA_PEM` unset.

> **Vitess schema-propagation race — read before adding migrations.**
> Vitess applies `ALTER TABLE` and then propagates the new schema to vtgate
> asynchronously (measured at **~2 seconds**). Any migration that alters an
> existing, already-queried table and *then* writes to the new column in the
> same `up()` can fail with:
>
> ```
> SQLSTATE[HY000]: General error: 1105 column 'x' not found in table 'y'
> ```
>
> `0048_phase4_gate_a` does exactly this (`ALTER TABLE threads ADD COLUMN
> status_changed_at …` followed immediately by `UPDATE threads SET
> status_changed_at = …`). It failed on one run and succeeded on a retry — it is
> a **race, not a deterministic failure**, so a migration run that passes once is
> not proof the next one will.
>
> `CREATE TABLE` is unaffected; so are `ALTER`s on tables created within the same
> run. Only pre-existing, schema-cached tables are exposed.
>
> If this starts biting, the fix is a schema-convergence wait in
> `src/Core/Migrator.php`: after each DDL statement, poll `information_schema`
> until the change is visible before running dependent DML. On a non-Vitess
> MySQL the race does not exist and no such change is needed.

Restrict source addresses if the provider supports it, and keep the database in
a region close to where the container will run.

## 4. Create the R2 bucket

```sh
npx wrangler r2 bucket create retroboards-data
```

Then create an **R2 API token** (Object Read & Write, scoped to that bucket) in
the dashboard. s3fs uses the S3-compatible credentials, not a Worker binding.

Keep this bucket **private**. Attachments are authorization-gated in PHP
(`src/Controller/MediaController.php`) — exposing the bucket publicly would
bypass `BoardPolicy` entirely and serve private-board attachments to anyone.

## 5. Configure vars and secrets

Edit the `vars` block in `wrangler.jsonc`: `APP_URL`, the `DB_*` host/port/name/
user, `R2_BUCKET`, `R2_ACCOUNT_ID`, and the `routes` pattern.

Secrets never go in `wrangler.jsonc`:

```sh
# APP_KEY derives CSRF/guest tokens and encrypts stored secrets — 32+ bytes.
php bin/console key:generate            # prints APP_KEY=<hex>; pass just the value
npx wrangler secret put APP_KEY

npx wrangler secret put DB_PASSWORD
npx wrangler secret put DB_SSL_CA_PEM   # paste the provider's CA bundle (PEM)
npx wrangler secret put R2_ACCESS_KEY_ID
npx wrangler secret put R2_SECRET_ACCESS_KEY
```

`DB_SSL_CA_PEM` is written to `/run/db-ca.pem` by the entrypoint and picked up
via `DB_SSL_CA`. Without a CA the connection is encrypted but unauthenticated —
`Database::tlsOptions()` will not assert certificate verification it cannot
perform.

## 6. Deploy

```sh
npm install
npx wrangler deploy
```

Wrangler builds the image with Docker, pushes it to the Cloudflare registry, and
deploys the Worker. **After the first deploy, wait several minutes**: containers
are provisioned asynchronously, and calls to the container error until they are
ready. Check with:

```sh
npx wrangler containers list
npx wrangler tail            # live Worker logs
```

Then confirm the app answers: `curl -sS https://forum.example.com/healthz`.
`/healthz` is dispatched before the setup gate and the CSRF check, so it answers
even pre-setup or with the database down.

## 7. Fix the client IP (do not skip)

`RateLimitService` keys per-IP, and `App\Security\ClientIdentifier` only honours
`X-Forwarded-For` when the immediate peer is listed in `TRUSTED_PROXIES`. With
`TRUSTED_PROXIES` empty, **every request is attributed to one Cloudflare address
and per-IP rate limiting collapses into a single global bucket.**

The Worker already overwrites `X-Forwarded-For` with `CF-Connecting-IP` and
discards whatever the client sent. That is the security boundary, and it holds
because the container is not addressable from the internet — only the Worker can
reach it. What remains is telling the app to trust that hop.

The peer address the container observes is Cloudflare-internal and not a
documented stable range, so read it from a live instance rather than guessing:

```sh
npx wrangler containers ssh main
# inside the container:
tail -n 20 /var/log/apache2/access.log     # first field is the peer address
```

Put the containing CIDR in `TRUSTED_PROXIES` (comma-separated, CIDRs allowed) and
redeploy. Verify by making a request and confirming the app attributes it to your
real address — a rate limit that never triggers from two different networks means
it is still wrong.

## 8. Configure the edge

Zone settings, all of which matter to this app specifically:

- **Cache** `/assets/*` and `/brand.css` aggressively. Leave everything else on
  **respect origin**. Do **not** create a "Cache Everything" rule: HTML carries
  session state, and `/media/{id}` already emits the correct headers per object —
  `public, max-age=31536000, immutable` when the attachment is public,
  `private, no-store` otherwise (`MediaController.php:126`). Overriding that
  serves private-board attachments from the edge.
- **Leave Bot Fight Mode off.** It force-enables JavaScript Detections, which
  injects an *inline* script. `SecurityHeaders::csp()` sends
  `script-src 'self'` with no nonce (`src/Security/SecurityHeaders.php:41`), so
  the browser refuses it. Cloudflare's supported fix is CSP nonces, which the app
  does not currently emit.
- **Leave Rocket Loader off** for the same class of reason. Email Address
  Obfuscation is fine — it loads from `/cdn-cgi/`, which is same-origin and
  allowed by `script-src 'self'`.
- **WAF rate limiting on `POST /login`** as a second layer. This matters more
  here than on a VPS: `RATELIMIT_PATH` lives on the R2 mount, and the file-based
  limiter is far weaker than a local disk one. Note that the tiered pattern which
  counts only `401`/`403` responses requires a **Business plan**; Free and Pro get
  IP-based counting.
- **Smart Tiered Cache** — free on all plans, worth enabling with a
  single-region origin.
- Optionally **Access** in front of `/admin` for an identity-provider gate ahead
  of `requireAdmin()`.

## 9. Cron workers

`worker/index.js` maps cron expressions to `bin/console` commands:

| Cron | Commands |
| --- | --- |
| `*/5 * * * *` | `worker:email`, `worker:webhooks` |
| `0 */6 * * *` | `worker:registry-refresh` |
| `10 3 * * *` | `worker:purge-ips`, `worker:attachments`, `worker:packages` |
| `0 7 * * *` | `worker:digest` |

Commands within a tick run sequentially — they share the database and the
denormalized counters it maintains. A tick that lands after `sleepAfter` starts
the container first (`exec()` does not start a stopped container). Cron trigger
changes take up to 15 minutes to propagate. Past runs are visible under **Cron
Events** in the dashboard.

## 10. Known caveats

- **Cold starts.** `sleepAfter` is `1h` and the cron ticks keep the instance
  warm, but after a genuine idle period the first request pays image start,
  Apache boot, and migrations.
- **The rate-limit ledger is weak.** It sits on a FUSE mount and is lost with the
  instance. The WAF rules in §8 are the real protection, not a nice-to-have.
- **s3fs is not a POSIX filesystem.** It has no atomic rename and higher latency
  than local disk. Attachment writes and package installs are correspondingly
  slower; watch `worker:attachments` runtimes.
- **Single instance by design.** `max_instances: 1`. Horizontal scaling would
  require moving the rate limiter and any local state out of the filesystem
  first.
- **Email.** `SendmailMailer` has no deliverability story from a container.
  Cloudflare Email Service offers authenticated SMTP
  (`smtp.mx.cloudflare.net:465`, username the literal `api_token`, password an
  API token with *Email Sending: Edit*). `Mailer` is a replaceable seam
  (DECISIONS §2), so an `SmtpMailer` bound in `App::buildContainer()` is the
  clean fix. Until then email **fails closed** — in-app notifications still
  deliver.

## 11. Cost

Rough monthly figures from published rates, always-on:

| Component | Cost |
| --- | --- |
| Workers Paid plan | $5 |
| Container, `basic` (1/4 vCPU, 1 GiB) | ≈ $8 |
| Container, `standard-1` (1/2 vCPU, 4 GiB) | ≈ $28 |
| Managed MySQL (external) | ≈ $15 |
| R2 | free under 10 GB |
| Container egress | $0.025/GB after 1 TB (NA/EU) |

## 12. Rolling back

`wrangler deployments list` / `wrangler rollback` revert the Worker. **A rollback
does not undo migrations** — they are additive and forward-only, and
`migrate:rollback` is greenfield-only. Do not delete container images that older
Worker versions still reference (`wrangler containers images delete` breaks those
versions).

## 13. Backups

R2 holds uploads; the database is the provider's responsibility. `tests/backup/
rehearse.sh` rehearses restore against a throwaway database. Point off-site dumps
at a second R2 bucket over the S3 API — egress is free.
