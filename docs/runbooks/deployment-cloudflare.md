# RetroBoards — Cloudflare Containers deployment runbook

Operating procedure for the Cloudflare deployment defined by `wrangler.jsonc`,
`worker/index.js`, `Dockerfile`, and `deploy/`, with `wrangler` authenticated
against the account that owns the zone.

> **Run every `wrangler` command from the deployment worktree, not from the
> main checkout.** The deployment lives in a git worktree —
> `.worktrees/cloudflare-production-20260804`, branch
> `deploy/cloudflare-production-20260804` — and the main checkout carries its
> own older, uncommitted `wrangler.jsonc` and `worker/`. `wrangler deploy` reads
> whichever it is standing in, and deploying from the wrong one silently ships
> a stale Worker **and** rebuilds the container image from stale source. That
> has already caused one outage. `git worktree list` shows the path; prefer
> `npx wrangler deploy --cwd <path>` if you are unsure where you are.

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

## 2. Outbound MySQL — settled, it works

Cloudflare never affirmatively documents arbitrary TCP egress from containers
(`enableInternet` is described purely in terms of HTTP), so this was the open
question that could have invalidated the whole approach. It has now been
measured from inside the running production container:

```
DNS  gcp.connect.psdb.cloud -> provider-managed GCP address (rotates)
TCP  gcp.connect.psdb.cloud:3306            OPEN in 0.02s
PDO  connect + SELECT VERSION()             0.33s, server 8.4.9-Vitess
```

Arbitrary TCP egress on 3306 works with the default `enableInternet = true`.
No tunnel, no proxy, no Hyperdrive. If you ever need to re-prove it on another
account, this is the probe:

```sh
php -r '$c = @fsockopen("your-db-host.example.com", 3306, $e, $s, 5);
        echo $c ? "OPEN\n" : "BLOCKED: $s\n";'
```

**Latency is the real cost, not reachability.** The same probe measures ~45ms
per round trip from `ENAM` to this PlanetScale endpoint, and a forum page issues
dozens of queries, so pages render in ~3s. See §14.

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
npx wrangler secret put CLOUDFLARE_EMAIL_API_TOKEN
```

`DB_SSL_CA_PEM` is written to `/run/db-ca.pem` by the entrypoint and picked up
via `DB_SSL_CA`. Without a CA the connection is encrypted but unauthenticated —
`Database::tlsOptions()` will not assert certificate verification it cannot
perform. The email token needs **Email Sending: Edit** on the account that owns
the already-onboarded `candidary.online` sending domain.

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

### The readiness probe must stay a static file

`ForumContainer.pingEndpoint` is `ping/ping.txt`, and it must keep pointing at
something Apache serves without entering PHP. This is not a preference — it is
the failure that took the first working deployment down completely, and it is
worth understanding before changing anything about it.

The SDK probes `http://${pingEndpoint}`. The default value, `ping`, resolves to
**`GET /`**. This app answers `/` with a `302` to `/setup` until an admin exists,
and the Workers `fetch` **follows redirects**, so a single probe cost two full
PHP+MySQL renders. Measured in situ those are ~2.7s each, against a
`PING_TIMEOUT_MS` the SDK **hardcodes at 5000**. Every probe aborted just over
the line and was retried every 300ms, the container was never marked ready, and
every real request blocked in `startAndWaitForPorts()` without ever reaching
`containerFetch`.

The symptom is nasty precisely because nothing looks broken:

- `curl https://forum.example.com/` hangs indefinitely, no status, no body.
- `wrangler tail` shows the request arriving and `outcome: canceled` when the
  client gives up, with **no exception and no log line**.
- The container is `running`, Apache is up, s3fs is mounted, and `/healthz`
  answers `200` normally *from inside the container*.
- The container access log fills with `GET /` → `GET /setup` pairs at a few
  requests a second that nobody sent.

That last one is the tell: a flood of `/`+`/setup` with no matching Worker
requests means the readiness probe is looping. Confirm by measuring the growth
of `/var/log/apache2/access.log` over a fixed interval while sending no traffic.

Two rules follow:

- **Probe a static file.** `public/ping.txt` is served directly, because the
  vhost only rewrites to `index.php` when the target does not exist. App and
  database latency then cannot drag readiness over the limit. This deliberately
  checks "Apache is serving", not "the app is well" — a broken app returning
  5xx is diagnosable, a flapping readiness probe is a total outage. `/healthz`
  remains the application health check.
- **Keep `portReadyTimeoutMS` short** (currently `30_000`). A long window does
  not rescue a broken boot; the SDK just re-fires the probe every 300ms for the
  whole duration. The earlier `120_000` is what turned one bad rollout into a
  sustained flood against the container.

`tests/Unit/Core/CloudflareDeploymentContractTest.php` pins both.

### Getting a shell when something is wrong

`wrangler containers ssh <instance>` currently fails with
`Web socket error: Unexpected server response: 400`, so the practical way in is
`exec()` from the Durable Object. Add a token-gated route to `worker/index.js`
temporarily, deploy, diagnose, then remove it and delete the secret:

```js
async runShell(commands) {
	if (!this.ctx.container.running) await this.start();   // NOT ensureStarted --
	const decoder = new TextDecoder();                     // a broken boot never
	const results = [];                                    // becomes ready
	for (const command of commands) {
		const proc = await this.ctx.container.exec(["sh", "-c", command], { stderr: "combined" });
		const out = await proc.output();
		results.push({ command, exitCode: out.exitCode, output: decoder.decode(out.stdout) });
	}
	return results;
}
```

```js
// in the default fetch(), before forwarding:
if (new URL(request.url).pathname === "/__diag") {
	if (!env.DIAG_TOKEN || request.headers.get("X-Diag-Token") !== env.DIAG_TOKEN) {
		return new Response("Not Found", { status: 404 });
	}
	return Response.json(await getContainer(env.FORUM, CONTAINER_ID)
		.runShell(new URL(request.url).searchParams.getAll("c")));
}
```

```sh
curl -sS -G --data-urlencode 'c=ps aux' -H "X-Diag-Token: $TOKEN" \
     https://forum.example.com/__diag | jq -r '.[].output'
```

Two things to know about `exec()`: it starts with an almost empty environment
(`HOME`, `PATH`, `PWD`) rather than inheriting the container's, so read
`/proc/1/environ` when you need the real `DB_*` values; and `output()` returns
**ArrayBuffers**, which need a `TextDecoder`.

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
documented stable range, so it was read from a live instance rather than
guessed — the first field of `/var/log/apache2/access.log`:

```
10.1.0.0 - - [04/Aug/2026:08:47:01 +0000] "GET / HTTP/1.1" 302 788 "-" "-"
```

`TRUSTED_PROXIES` is therefore set to `10.0.0.0/8` — the private range
containing that peer, since the exact address is not contractual. Trusting a
range this wide is safe **only** because the container has no public address
(`network.mode` is `private`, `assign_ipv4` and `assign_ipv6` both `none`): the
Worker is the only thing that can reach it, and it overwrites `X-Forwarded-For`
with `CF-Connecting-IP` before forwarding. `ClientIdentifier::matches()` accepts
CIDRs, comma-separated.

Re-read the peer with the `/__diag` recipe in §6 if it ever changes; the
`wrangler containers ssh` command that used to be documented here does not
currently work.

## 8. Configure the edge

Zone settings, all of which matter to this app specifically:

- **Cache** is source-controlled in `worker/index.js`, not a zone Cache Rule.
  Successful versioned `GET /assets/*?v=...` and `GET /brand.css` responses are
  stored through `caches.default` for one hour (`s-maxage=3600`) with a
  five-minute browser TTL. Unversioned sub-assets such as fonts bypass this
  cache. `Set-Cookie` is stripped before storage, cache keys retain the query
  string, and responses expose `X-RetroBoards-Cache: MISS|HIT`. Core CSS/JS URLs
  carry a content-derived `?v=` value so a deployment cannot pair new HTML with
  stale JS/CSS. Leave everything else uncached. Do **not** create a "Cache
  Everything" rule: HTML carries session state, and `/media/{id}` already emits
  the correct headers per object — `public, max-age=31536000, immutable` when
  the attachment is public, `private, no-store` otherwise
  (`MediaController.php:126`). Overriding that serves private-board attachments
  from the edge.
- **Leave Bot Fight Mode off.** It force-enables JavaScript Detections, which
  injects an *inline* script. `SecurityHeaders::csp()` sends
  `script-src 'self'` with no nonce (`src/Security/SecurityHeaders.php:41`), so
  the browser refuses it. Cloudflare's supported fix is CSP nonces, which the app
  does not currently emit.
- **Leave Rocket Loader off** for the same class of reason. Email Address
  Obfuscation is fine — it loads from `/cdn-cgi/`, which is same-origin and
  allowed by `script-src 'self'`.
- **Login edge rate limiting is deferred until a plan upgrade.** The zone is on
  Free, where rate-limiting expressions cannot use the HTTP method and the only
  counting period is 10 seconds. A path-only `/login` rule would also count GETs
  and cannot reproduce the requested `POST /login` guard. The application-level
  limiter remains 10 attempts per 15 minutes. After upgrading, add a
  method-aware WAF rule as the second layer; response-code counting still
  requires Business or above.
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

`runConsole()` passes `env: this.envVars` explicitly. It has to: an `exec()`
process does **not** inherit the variables handed to the container — it starts
with `HOME`, `PATH`, `PWD` and nothing else. Without that, every worker would
run with no `DB_*` configuration at all. Same reason `output()` is decoded
through a `TextDecoder`: it hands back ArrayBuffers, not strings, so worker
output is otherwise unreadable. Both are pinned by
`tests/Unit/Core/CloudflareDeploymentContractTest.php`.

**These have not yet been observed succeeding against real work** — the crons
only started firing correctly once readiness was fixed, and the site has no
content to process. Check **Cron Events** after the first `*/5` tick.

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

## 14. Measured latency — the open performance problem

Every page render is dominated by database round trips, not by PHP:

| Measurement (from inside the container) | Value |
| --- | --- |
| PDO connect to PlanetScale | 0.20s |
| 20 trivial `SELECT 1` round trips | 0.90s (**~45ms each**) |
| `SELECT COUNT(*) FROM users` | 0.06s |
| `GET /ping.txt` (static, no PHP) | ~0.00s |
| `GET /healthz` before query-path work | ~2.8–4.8s |
| `GET /` before query-path work | ~3.0–5.6s |
| `GET /healthz` after `d6ab5b5` | ~0.52–0.83s |
| `GET /` after `d6ab5b5` | ~1.42s observed |
| Versioned `app.css` edge hit | ~0.15s |
| Versioned `brand.css` edge hit | ~0.15s |

The container currently runs in `ENAM` (observed node `ewr14`, Newark), but
placement is unconstrained (`placement: {}` in the deployed Worker settings).
The PlanetScale hostname uses rotating GCP addresses, so an address lookup is
not a reliable region inventory. The Hyperdrive configuration created during
the deployment investigation does expose the origin hostname:
`gcp-us-central1.connect.psdb.cloud`, placing the database in GCP `us-central1`
(Iowa). At ~45ms per query, a page issuing dozens of queries spends seconds
waiting on the network, which is exactly what the public and container timings
show.

The dashboard-added `HYPERDRIVE_VARIABLE` binding did not change this PHP
topology. It was removed by the `d6ab5b5` source deployment: `worker/index.js`
never used it, and the container continues to receive the original `DB_*`
variables and connect with PDO. Hyperdrive's documented connection object is a
Workers-runtime binding for compatible Worker database drivers; it is not a TCP
endpoint that PHP PDO inside the container can consume.

This is the cost the `wrangler.jsonc` placement comment warns about, and it is
**not** fixed by container sizing — the CPU is idle. The levers, in rough order
of effect:

1. **Move the database next to the container.** Pinning `ENAM` alone does not
   improve the current `ewr14` → `us-central1` path because the container is
   already in ENAM. Migrate the database to an eastern region near the
   container, or move to a managed MySQL provider there. After that, constrain
   the container so restarts cannot move it away. Current Wrangler syntax nests
   placement under the container entry:
   `"constraints": {"regions": ["ENAM"]}`. Do not add this until the database
   migration target is confirmed. A same-region managed MySQL should put round
   trips in the low single-digit milliseconds.
2. **Reduce queries per render.** `d6ab5b5` landed request-level query metrics,
   a bulk settings read for the global shell, a request-memoized navigation
   snapshot shared by `shareViewGlobals()` and `HomeController`, and a true
   infrastructure-path bypass for `/healthz`. Guest home is contract-bounded to
   eight database queries. There is still no page/fragment render cache;
   `docs/runbooks/render_cache.md` only rebuilds derived Markdown `body_html`.
3. Persistent connections would save the 0.20s connect, but not the per-query
   cost, which is the dominant term.

### PlanetScale east-region migration

The selected target is `gcp-us-east4` (Ashburn, Virginia), near the current
ENAM container placement. PlanetScale does not move an existing production
branch in place. Its documented region-change procedure is:

1. Install/authenticate the PlanetScale CLI and create a new branch in the
   target region:
   `pscale branch create imladris-db production-east --region gcp-us-east4`.
2. Schedule a maintenance window and block application writes before the final
   dump. The documented dump/restore path requires downtime to avoid lost
   writes.
3. Dump the current production branch:
   `pscale database dump imladris-db main`.
4. Restore the dump to `production-east`:
   `pscale database restore-dump imladris-db production-east`.
5. Validate migration status, authoritative row counts/checksums, foreign-key
   enforcement, FULLTEXT indexes, and a health check using temporary credentials
   for the new branch.
6. Promote `production-east`:
   `pscale branch promote imladris-db production-east`.
7. Issue branch credentials, update `DB_HOST`/`DB_USERNAME` and the
   `DB_PASSWORD` Worker secret, then deploy from this worktree. Add
   `"constraints": {"regions": ["ENAM"]}` only after the target is live.
8. Verify `/healthz`, representative reads/writes, cron workers, and latency
   before reopening writes.

Keep the old branch and credentials available for the rollback window. A
rollback restores the prior `DB_*` values and known-good Worker version; do not
delete the old branch until parity and backup evidence are complete.

## 15. Current state

As of 2026-08-04 the deployment serves traffic:

- `https://forum.candidary.online/healthz` → `200 {"status":"ok","database":"ok"}`
- Worker version `b266e6bd`, source commit `d6ab5b5`; container application
  version 8 runs image digest `sha256:f4ebd113…` at observed node `ewr14`
- PlanetScale `8.4.9-Vitess`, all **78** migrations applied
- R2 bucket `retroboards-data` mounted at `/data` via s3fs
- Secrets set: `APP_KEY`, `DB_PASSWORD`, `R2_ACCESS_KEY_ID`,
  `R2_SECRET_ACCESS_KEY`, `CLOUDFLARE_EMAIL_API_TOKEN`
- `MAIL_DRIVER=cloudflare_smtp` with `noreply@candidary.online`; the sending
  domain is onboarded and the token secret is present
- Four Cron Triggers are registered. A post-deploy `*/5` tick completed with
  both `runConsole` RPC calls reporting `outcome: ok` and no error logs
- First-run setup is complete: `/` returns `200` and `/setup` redirects to `/`.
- Worker Cache API verification observed `MISS` then `HIT` for the same
  versioned `app.css` and `brand.css` URLs; hits return in ~0.15s
- The deployed code is the published `deploy/cloudflare-production-20260804`
  branch at `d6ab5b5`. It deliberately does **not** include the eight
  feature-only Imladris admin/account commits on the separately published
  `feat/imladris-admin-account` branch.
- The throwaway Workers `rb-nginx-test` and `rb-egress-probe`, and their
  corresponding container apps, were deleted after confirming they had no
  custom domains, routes, or Cron Triggers. Only
  `retroboards-forumcontainer` remains provisioned.

Not yet done, tracked here so it is not lost:

- Execute the `gcp-us-east4` PlanetScale migration above during a write-free
  maintenance window.
- Upgrade the Cloudflare zone before adding a method-aware `POST /login` WAF
  rate rule; the Free plan cannot express it.
- Perform a recipient-level SMTP test send from `/admin/email`; configuration
  and cron execution are proven, but mailbox delivery has not yet been observed.
