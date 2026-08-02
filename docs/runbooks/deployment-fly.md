# RetroBoards — Fly.io deployment runbook

Operating procedure for the Fly.io deployment defined by `fly.toml`, `Dockerfile`,
and `deploy/`. Commands run from the project root with `flyctl` authenticated
against the org that owns the app.

> **RetroBoards requires MySQL 8 or MariaDB.** `App\Core\Database` builds a
> `mysql:` DSN and the image installs only the `pdo_mysql` extension. The schema
> and queries are MySQL-specific throughout (`ENGINE=InnoDB`, `AUTO_INCREMENT`,
> `UTC_TIMESTAMP()`, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE`, `FULLTEXT`
> indexes, `JSON_*` functions). **A PostgreSQL database — including Fly's managed
> Postgres — cannot back this application.** Attaching one does not fail with a
> useful message: the app simply never connects.

## 1. What the deployment looks like

- **Image:** `Dockerfile` builds `php:8.4-apache-bookworm`, installs
  `pdo_mysql` (plus `curl`, `dom`, `gd`, `mbstring`, `opcache`), and runs
  `composer install --no-dev`.
- **Web server:** `deploy/apache-vhost.conf` listens on **8080** with
  `DocumentRoot /var/www/html/public`, matching `http_service.internal_port`.
- **Entrypoint:** `deploy/entrypoint.sh` creates and chowns the volume
  subdirectories, then hands off to `docker-php-entrypoint`.
- **Release command:** `[deploy] release_command = "php bin/console migrate"`
  applies pending (additive) migrations before any app Machine is created or
  updated. A non-zero exit stops the deploy.
- **Volume:** a 3 GB volume mounts at `/data` and backs `UPLOADS_PATH`,
  `PACKAGES_STORAGE_PATH`, and `RATELIMIT_PATH`.

## 2. Provision the database

Fly has no managed MySQL offering, so pick one of:

- **The repository's MariaDB Fly app**, defined by `fly.database.toml` and
  `deploy/mariadb/Dockerfile`, with its own volume for `/var/lib/mysql`. It is
  reached over private networking at `<db-app>.internal:3306`. Its service has no
  public port mapping; do not allocate public IPs for it.
- **An external managed MySQL provider.** Require TLS and restrict source
  addresses where the provider supports it.

Either way, create a dedicated database and a least-privilege application user.
The app needs DML plus DDL on its own schema (the release command runs
migrations); it does not need `SUPER` or rights on other schemas.

Character set must be `utf8mb4` — `config/config.php` hardcodes it in the DSN.

For a new Fly environment, export the three required secrets and run the
bootstrap command. It creates both apps when absent, creates the database
volume, deploys MariaDB, waits for it to become ready, sets the application's
private database hostname, and deploys the application:

```bash
export APP_KEY="$(openssl rand -hex 32)"
export DB_PASSWORD='<store-a-strong-unique-password>'
export DB_ROOT_PASSWORD='<store-a-different-strong-password>'

deploy/fly-bootstrap.sh
```

The defaults are `community-forums`, `community-forums-db`, region `iad`, and a
10 GB database volume. Override them with `FLY_APP_NAME`, `FLY_DB_APP_NAME`,
`FLY_APP_URL`, and `FLY_DB_VOLUME_SIZE`; set `FLY_ORG` when the authenticated
Fly account has access to more than one organization. Both checked-in Fly
configurations use `iad`, so the bootstrap creates the volume there as well.
Store the supplied passwords in the operator's secret manager: Fly secrets
cannot be read back. On a rerun with an initialized database volume, supply the
original database passwords. Changing the `MARIADB_*` initialization secrets
does not rotate credentials in an existing MariaDB data directory; rotate the
database user in MariaDB first, then update the Fly secrets.

## 3. Configure secrets

`[env]` in `fly.toml` is committed plaintext and is for **non-sensitive** values
only; it currently holds `APP_ENV`, `APP_URL`, the storage paths, and the
security toggles. Credentials go in Fly secrets, which take precedence over
same-named `[env]` values. `App\Core\Env` reads real environment variables ahead
of any `.env` file, and `.env` is excluded from the image by `.dockerignore`, so
secrets are the only supported channel.

```bash
# APP_KEY derives CSRF/guest tokens and encrypts stored secrets — 32+ bytes.
# Prints a ready-made `APP_KEY=<hex>` line; pass just the value below.
php bin/console key:generate

fly secrets set \
  APP_KEY='<generated>' \
  DB_HOST='<db-app>.internal' \
  DB_PORT='3306' \
  DB_DATABASE='retroboards' \
  DB_USERNAME='<app-user>' \
  DB_PASSWORD='<password>'
```

**Do not skip any of these.** `config/config.php` has development fallbacks
(`127.0.0.1:3306`, user `retro`, password `retropw`); with the secrets unset the
release Machine tries to reach a database inside its own container, `php
bin/console migrate` exits non-zero, and **the deploy fails before any app
Machine starts**. An empty `APP_KEY` breaks passkey login and secret storage.

Optional, environment-only credentials (never stored in the database or shown in
the UI): `OPENAI_API_KEY` enables Thread Intelligence generation. Leaving it
unset is safe — manual community memory and deterministic return context keep
working. See `docs/runbooks/thread_intelligence.md`.

## 4. Deploy

```bash
fly deploy
```

The release Machine inherits environment, secrets, and network config, but gets
**no volumes**, and its default timeout is 5 minutes. If the migration set grows
past that, raise it with `fly deploy --release-command-timeout=10m`.

Confirm the database is reachable *from the release Machine's network*, not just
from your laptop — a private `.internal` hostname that resolves for you may not
be the one the app uses.

Verify:

```bash
fly logs
fly status
curl -fsS https://<app>.fly.dev/healthz     # {"status":"ok","database":"ok"}
```

`/healthz` is dispatched before the setup gate and the CSRF check, so it answers
pre-setup and when the database is down. It returns **503** whenever
`Database::ping()` fails, which is also what the `http_service.checks` block
probes — so a database outage takes the Machine out of rotation by design.

## 5. First-run setup

On an empty database the kernel's setup gate redirects every route to `/setup`.
Visit `https://<app>.fly.dev/setup` over HTTPS and create the initial admin
account **promptly** — until it is completed the gate is open to anyone who
reaches the app. Once an admin exists, `/setup` stops being served.

Then work through the staged enablement order in
`docs/runbooks/operations.md` §8 rather than turning everything on at once.

## 6. Known caveats

**Background workers are not scheduled.** `fly.toml` defines no `[processes]`
groups and nothing invokes cron, so every `bin/console worker:*` command is
inert in this deployment. That means no notification emails, no daily digests,
no IP-retention anonymisation, no orphaned-attachment sweeping, no webhook
delivery, and no package digest verification. Before relying on any of those
features, schedule them — and note two constraints:

- `min_machines_running = 0` with `auto_stop_machines = "stop"` lets the app
  Machine sleep when idle, so an in-container scheduler silently misses runs.
- Volumes attach to one Machine. `worker:attachments` and `worker:packages`
  operate on `/data`, so they **cannot** run as separate process-group Machines
  against the same volume; they must run where `/data` is mounted, or `/data`
  must move to shared object storage first.

**The volume is per-Machine.** Scaling past one Machine splits uploads, package
storage, and rate-limit state across Machines — uploads become intermittently
missing and rate limits weaken. Stay single-Machine until `/data` is replaced
with shared storage.

**Rate limiting fails open.** A `/data` permission problem degrades limits
silently rather than erroring; check `RATELIMIT_PATH` is writable by `www-data`
after any volume change.

## 7. Backups

The `[[mounts]]` volume does **not** back up the database — the database lives
outside the app Machine. Take transaction-consistent dumps per
`docs/runbooks/operations.md` §7, store them off the host, and rehearse restores
with `tests/backup/rehearse.sh`. Volume snapshots cover only uploads and package
storage.

## 8. Rolling back

Per the golden rule in `docs/runbooks/operations.md`, disable the offending
**feature flag** first and investigate before rolling back code or data.
Migrations are additive and forward-only, so `fly deploy` of an older image
usually tolerates the newer schema. Never run `migrate:rollback` or
`migrate:fresh` against production — both are destructive and greenfield-only.
