# Webhook Delivery — B2 Sub-project 3

> Archived design record — implementation plan + design spec(s) merged during the webhook-delivery doc consolidation; see the ADR / runbook / PR referenced below for shipped status.

---

## Part I — Design spec (the "what & why")

*(Was `docs/superpowers/specs/2026-06-28-webhook-delivery-design.md`.)*

- **Status:** Approved design, pre-implementation
- **Date:** 2026-06-28
- **Program:** Phase 5 Gate A · ADR 0004 Part B (B2 "trusted hook/webhook/API-token/service-secret foundation")
- **Position:** Sub-project 3 of 4. SP1 (service-secret registry, `0055`) and SP2 (read-only API tokens, `0056`) have landed deploy-dark. SP4 (first-party hook registry) follows.
- **Branch:** `b2-webhook-delivery` (off the SP1+SP2 HEAD; `main` does not yet contain `0055`/`0056`).
- **Precedence:** `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > surface specs. Where this spec and an authoritative doc disagree, the authoritative doc wins.

---

### 0. Summary

Build the webhook **delivery engine + operator admin UI**, deploy-dark behind a new `webhooks` feature flag (default OFF, doubling as a kill switch). The engine: operator-registered HTTPS endpoints, HMAC-SHA256-signed payloads, a durable per-attempt delivery ledger with retry/backoff/dead-letter, idempotent event identity, SSRF/egress control, a 5-second delivery timeout (ADR 0004 D11), and a `GET_LOCK`-drained cron worker.

The producer seam (`WebhookService::dispatch()`) is **built and tested** in this slice, but the wiring that fires real domain events (`topic.created`, `reply.created`, `moderation.*`) from `PostingService`/`ModerationService`/etc. is **deferred to SP4 (the first-party hook registry)**. This keeps SP3 self-contained and out of the hot write paths. End-to-end proof comes from an admin **"send test event"** action that dispatches a `ping` through the full pipeline.

No HTTP-kernel changes: admin routes are ordinary CSRF-protected, admin-gated HTML routes. We are the *sender*, not a receiver, so no CSRF exemption is needed.

#### Decisions locked in this spec

1. **Engine-only scope.** Real domain-event producer wiring is SP4's job; SP3 ships the engine, the `dispatch()` seam, and a `ping` test path. (Approved.)
2. **Egress posture: deny-private-by-default + opt-in allowlist.** HTTPS-only, block loopback/private/link-local/metadata ranges, resolve-then-pin; operators may opt specific hosts/CIDRs back in via env. (Approved.)
3. **Retry policy: moderate.** ~6 attempts, exponential backoff `1m → 5m → 25m → 2h → 6h`, then `dead`; plus a circuit-breaker that auto-pauses an endpoint after sustained consecutive failures. (Approved.)
4. **Signature: GitHub-style two-header + timestamp**, with multiple comma-separated signatures during a secret-rotation grace window (zero-downtime rotation). (Approved.)
5. **Secret storage: SecretVault reference, not plaintext.** The legacy documented `webhooks.secret VARCHAR(128)` plaintext column is replaced by a `secret_ref` (`svcsec_*`). SP3 is SecretVault's first consumer and adds its deferred container binding.
6. **No `delivering` ledger state** (crash-safety over fine-grained observability — see §3).

---

### 1. Surrounding seams (verified against the code, 2026-06-28)

- **SecretVault** (`src/Service/SecretVault.php`, flag `service_secrets`, built but inert): `store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor=null): string` (returns `svcsec_<32hex>`); `reveal(string $ref): string`; `rotate(string $ref, string $newPlaintext, ?User $actor=null, ?int $graceSeconds=null): int`; `usableSecrets(string $ref): array` (current ∪ in-grace-retired, newest first); `revoke(string $ref, ?User $actor=null): void`; `prune(int $limit=100): int`. AES-256-GCM at rest (`SecretBox`, key from `APP_KEY`). `store`/`rotate` throw `SecretsDisabledException` when its flag is dark; `reveal`/`usableSecrets`/`revoke`/`prune` work regardless. **Container binding deliberately deferred to the first consumer (us)**; today only `bin/console worker:secret-prune` constructs it. Reentrant-transaction ownership contract: callers MUST invoke `store()`/`rotate()` inside their own consumer-save transaction (`Database::transaction` nests with no savepoints).
- **API-token vertical slice** (SP2) is the copy-me template: migration `0056_phase5_api_tokens.php`; `ApiTokenService` (ctor `Database` first; order `WriteGate::assertCanWrite` → flag check → password reauth → validation → `$db->transaction(insert + ModerationLogRepository::log)`); `AdminApiTokenController` (`requireAdmin()` + private `gate()`→`NotFoundException` when dark; show-secret-once rendered directly from the POST response, never via Flash); `tests/Integration/Api/*` + `tests/Unit/Security/ApiScopesTest`; Playwright `tests/browser/gate-a.spec.ts` screenshots `20`/`21` (desktop+mobile); flag-dark regression asserted at every layer.
- **Durable-queue/worker pattern** (`email_deliveries` `0023` → `NotificationEmailWorker` → `worker:email`): single-drainer via connection-scoped `GET_LOCK('rb_email_outbox', 0)` (non-blocking; bail if not won), `status='queued'` scan ordered by id, `LIMIT` int-interpolated (never bound; `EMULATE_PREPARES=false`), `INSERT IGNORE` for enqueue idempotency. **It has no retry/backoff/dead-letter — `markFailed` is terminal.** PHASE_3_PLAN §8.2 #6 requires the webhook ledger to add those from day one.
- **Outbound HTTP:** only `src/Service/OAuth/HttpClient.php` (cURL) exists — coupled to OAuth, **zero SSRF defenses** (good defaults: TLS verify on, `FOLLOWLOCATION=false`, 10s timeout; missing: scheme/port/IP validation, `CONNECTTIMEOUT`, protocol pin, size cap). Reference only — do **not** reuse for operator URLs.
- **HMAC primitive to reuse:** `src/Support/SignedToken.php` — `sign(string $purpose, string $value, string $key): string` = `hash_hmac('sha256', $purpose."\0".$value, $key)`; `verify(...)` uses `hash_equals`. Key is `app.key` (`APP_KEY`).
- **CIDR matching:** `src/Security/ClientIdentifier.php::matches(string $ip, string $range): bool` has the `inet_pton` binary-prefix logic we need, but it is **`private` and instance-scoped — not reusable as-is**. SP3 extracts a pure static `App\Support\Cidr::contains(string $ip, string $cidr): bool` (same v4/v6 byte-prefix logic) that `EgressGuard` uses; `ClientIdentifier::matches` MAY later delegate to it (optional, not required this slice). No private-range *classifier* exists anywhere — that is new code in `EgressGuard`.
- **Rate limiting:** `RateLimitService::enforce(policy, request, ?user)` / `enforceSubject(policy, request, subject, ?user)`; named policies in `config/config.php` `rate_limits` as `[max, decaySeconds]`; **fails open** on unknown policy; keys per account/IP (not per destination).
- **Audit:** `ModerationLogRepository::log([...])`, `target_type` ENUM (currently `…,'service_secret','api_token'`); each B2 migration extends it. Before/after JSON carries only non-secret metadata; audit write is in the same transaction as its mutation (D11).
- **Governance:** new flags default OFF (`FeatureFlags::DEFAULTS`, B2 block at `FeatureFlags.php:69-73`). `enabled('typo')` fails dark. Decision #40: every subsystem has an independent disable path that pauses it without losing operator control. No public/untrusted PHP execution in any Gate A sub-project. Next migration number is **`0057`**.

---

### 2. Components

Each unit has one purpose, a defined interface, and is independently testable.

| Unit | Path | Responsibility |
|---|---|---|
| Migration | `database/migrations/0057_phase5_webhooks.php` | Create `webhooks` + `webhook_deliveries`; extend `moderation_log.target_type` enum with `'webhook'` |
| `WebhookEvents` | `src/Security/WebhookEvents.php` | `final` catalogue of event-name strings (like `ApiScopes`): `const EVENTS`, `isValid(string): bool`, `all(): array` |
| `WebhookRepository` | `src/Repository/WebhookRepository.php` | `final`, single-table SQL for endpoints |
| `WebhookDeliveryRepository` | `src/Repository/WebhookDeliveryRepository.php` | `final`, the durable ledger (enqueue/transitions/lock/observability + a backoff-aware `claim` that joins `webhooks` to gate on `is_active=1`) |
| `EgressGuard` | `src/Security/EgressGuard.php` | `final`, pure SSRF policy — validate URL + resolved IPs vs deny-ranges & allowlist |
| `Cidr` | `src/Support/Cidr.php` | `final`, pure static `contains(ip, cidr): bool` (`inet_pton` byte-prefix, v4/v6); extracted so `EgressGuard` can reuse it (`ClientIdentifier::matches` is `private`) |
| `WebhookTransport` | `src/Service/Webhook/WebhookTransport.php` | interface — `deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse` |
| `CurlWebhookTransport` | `src/Service/Webhook/CurlWebhookTransport.php` | real SSRF-hardened cURL impl (uses `EgressGuard`, resolve-then-pin) |
| `FakeWebhookTransport` | `src/Service/Webhook/FakeWebhookTransport.php` | test double — records calls, returns canned responses (no real egress) |
| `WebhookSigner` | `src/Service/Webhook/WebhookSigner.php` | build signed headers from a secret set; multi-signature during rotation |
| `WebhookService` | `src/Service/WebhookService.php` | `final`, flag-gated orchestrator (the public surface) |
| `WebhookDeliveryWorker` | `src/Worker/WebhookDeliveryWorker.php` | drains the ledger; mirrors `NotificationEmailWorker` |
| `AdminWebhookController` | `src/Controller/AdminWebhookController.php` | `final extends Controller`, admin UI |
| Templates | `templates/admin/webhooks.php`, `templates/admin/webhook_detail.php` | list + create; detail (edit/rotate/delivery-log/replay) |
| Exceptions | `src/Core/WebhooksDisabledException.php`, `src/Core/EgressBlockedException.php` | `final extends RuntimeException` |
| Worker case | `bin/console` (`worker:webhooks`) | construct repos + worker, run, print stats line |
| Config | `config/config.php` (`webhooks` block, `rate_limits.webhook_test`) | tunables + opt-in allowlist (env-sourced) |
| Flag | `src/Core/FeatureFlags.php` (`'webhooks' => false`) | deploy-dark + kill switch |

---

### 3. Schema (`0057_phase5_webhooks.php`)

Additive, anonymous-class `up(\PDO)/down(\PDO)`, raw `$pdo->exec(<<<'SQL' … SQL)`, `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

#### `webhooks` (endpoint config — reconciled from the legacy doc shape)

```sql
CREATE TABLE webhooks (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                 VARCHAR(80)     NOT NULL,
  url                  VARCHAR(512)    NOT NULL,
  events               JSON            NOT NULL,            -- subscribed event-name strings
  secret_ref           VARCHAR(64)     NOT NULL,            -- svcsec_* SecretVault reference (NOT plaintext)
  is_active            TINYINT(1)      NOT NULL DEFAULT 1,
  consecutive_failures INT UNSIGNED    NOT NULL DEFAULT 0,  -- circuit-breaker counter
  disabled_at          DATETIME        NULL,                -- set when auto-paused
  disabled_reason      VARCHAR(190)    NULL,
  last_status          INT             NULL,                -- last delivery HTTP status
  last_delivered_at    DATETIME        NULL,
  created_by           BIGINT UNSIGNED NOT NULL,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_webhook_active (is_active),
  CONSTRAINT fk_webhook_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `webhook_deliveries` (durable per-attempt ledger — new; closes the PHASE_3_PLAN §8.2 gap)

```sql
CREATE TABLE webhook_deliveries (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  webhook_id      BIGINT UNSIGNED NOT NULL,
  event_type      VARCHAR(80)     NOT NULL,
  event_id        VARCHAR(64)     NOT NULL,                 -- per-occurrence id; unique WITH event_type (idempotency)
  payload         MEDIUMTEXT      NOT NULL,                 -- JSON body to POST
  status          ENUM('queued','delivered','dead') NOT NULL DEFAULT 'queued',
  attempt_count   INT UNSIGNED    NOT NULL DEFAULT 0,
  max_attempts    INT UNSIGNED    NOT NULL,                 -- policy snapshot at enqueue
  next_attempt_at DATETIME        NULL,                     -- backoff schedule (NULL = immediate)
  last_attempt_at DATETIME        NULL,
  response_status INT             NULL,                     -- last HTTP status observed
  error           VARCHAR(255)    NULL,                     -- truncated, secret-scrubbed
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_delivery_idem (webhook_id, event_type, event_id), -- dedupe per endpoint per (type, occurrence)
  KEY idx_delivery_claim (status, next_attempt_at),         -- backs the backoff-aware claim scan
  CONSTRAINT fk_delivery_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Audit enum extension (mirrors `0055`/`0056`)

```sql
ALTER TABLE moderation_log
  MODIFY target_type ENUM('thread','post','user','board','category','setting',
                          'service_secret','api_token','webhook') NOT NULL;
```

`down()` drops both tables (FK-aware order), reverts the enum, and deletes `moderation_log` rows with `target_type='webhook'`.

**Why no `delivering` state.** With a single-drainer `GET_LOCK` worker processing rows synchronously, an intermediate `delivering` state would be left stranded if the worker crashed mid-POST (no reaper exists in this codebase). Instead a row stays `queued` (with `next_attempt_at`) between attempts — a crash simply leaves it claimable on the next run, exactly as the email pattern behaves. "Retrying" is derivable as `status='queued' AND attempt_count>0`.

---

### 4. Event catalogue (`WebhookEvents`)

Mirrors `ApiScopes`. Defines the contract SP4 will fill. Uses the dotted naming convention already adopted by Phase-4 code (`thread.solved`, `thread.status`).

```php
public const EVENTS = [
    'ping'              => 'Test event (fires from the admin "Send test event" action)',
    'topic.created'     => 'A new topic/thread became publicly visible',
    'reply.created'     => 'A new reply became publicly visible',
    'post.edited'       => 'A post was edited by its author',
    'post.deleted'      => 'A post was deleted (author or moderator)',
    'thread.solved'     => 'A thread was marked solved / answer accepted',
    'report.created'    => 'A post was reported',
    'report.resolved'   => 'A report was resolved or dismissed',
    'member.registered' => 'A new member account was created',
    'member.banned'     => 'A member was banned',
    'moderation.auto_action' => 'Anti-abuse took an automated action (flag/hold/block)',
];
```

`isValid(string $event): bool` (isset); `all(): array` (name ⇒ description).

**Honesty about coverage (no silent cap).** Only `ping` actually fires in this slice; the domain events activate when their producers are wired in SP4. Operators may still subscribe to any catalogued event forward-compatibly, but the admin create/edit form shows a banner: *"Only the `ping` (test) event fires in this release. Domain events activate when event sources land (B2 sub-project 4)."* The catalogue is the single source of truth SP4 hooks into.

---

### 5. `WebhookService` (public surface)

`final`. Constructor (Database first, per the house template):

```php
public function __construct(
    private Database $db,
    private WebhookRepository $webhooks,
    private WebhookDeliveryRepository $deliveries,
    private SecretVault $vault,
    private ModerationLogRepository $log,
    private FeatureFlags $flags,
    private Config $config,
    private PasswordHasher $hasher,
    private WriteGate $writeGate,
) {}
```

Methods (enforcement order matches `ApiTokenService`: **WriteGate → flag → reauth → validate → `db->transaction`(mutation + audit)**):

- `register(User $admin, string $currentPassword, string $name, string $url, array $events): array{id:int, secret:string}` — reauth required (mints a signing secret). **Asserts `service_secrets` is also enabled** (the SecretVault dependency — `store()` calls `assertEnabled()`): if it is dark, raise a `ValidationException` with an operator-facing message ("Enable the service-secret store before creating webhooks") rather than letting SecretVault's `SecretsDisabledException` bubble to the kernel as a 500. Validate: `name` 1–80 chars; `url` parses, scheme in policy, passes a *static* `EgressGuard` pre-check (reject obviously-bad URLs at registration; the live resolve-time check still runs at delivery); `events` non-empty subset of `WebhookEvents`. Generate `secret = bin2hex(random_bytes(32))`. **Inside one transaction:** insert the webhook row (with a placeholder/`''` ref), `$ref = $vault->store('webhook', $webhookId, "Webhook signing secret: {$name}", $secret, $admin)`, update the row's `secret_ref = $ref`, audit `webhook_registered`. Return the plaintext `secret` **once**.
- `rotateSecret(User $admin, string $currentPassword, int $webhookId): string` — reauth required; **also asserts `service_secrets` enabled** (same dependency as `register` — `rotate()` calls `assertEnabled()`; `ValidationException` if dark). `$vault->rotate($ref, $newSecret, $admin, grace)`; audit `webhook_rotated`. Return new plaintext once. (Grace overlap → §6 dual signatures.)
- `update(User $admin, int $webhookId, string $name, string $url, array $events): void` — no reauth; same validation as register (minus secret). Audit `webhook_updated`.
- `setActive(User $admin, int $webhookId, bool $active): void` — manual pause/resume. Re-enabling clears `disabled_at`/`disabled_reason` and resets `consecutive_failures=0`. Audit `webhook_enabled`/`webhook_disabled`.
- `delete(User $admin, int $webhookId): void` — delete endpoint (deliveries cascade), `$vault->revoke($ref, $admin)`, audit `webhook_deleted`.
- `dispatch(string $eventType, array $payload, ?string $eventId=null): int` — **the producer seam.** Returns `0` (no-op) when the flag is dark. `$eventId` is a **per-occurrence identifier, not a source-row id**: `dispatch()` generates a random one (`bin2hex(random_bytes(16))`) when the caller omits it; a caller (SP4) that supplies one MUST make it unique per logical occurrence (e.g. `post:123:edited:<rev>`) and never reuse a bare source id like `post:123` across event types. Find active endpoints subscribed to `$eventType`; `enqueue()` one ledger row per endpoint via `INSERT IGNORE` on the `(webhook_id, event_type, event_id)` unique key — so a genuine double-fire of the *same* occurrence dedupes, while distinct event types for one source row (e.g. `post.edited` then `post.deleted`) never collide. Returns the count enqueued. A plain INSERT — safe inside a caller's transaction (reentrant). Today called only by `sendTestEvent` and tests.
- `sendTestEvent(User $admin, int $webhookId): int` — enqueue a synthetic `ping` delivery to one endpoint (admin button + evidence). Builds an envelope `{event:'ping', id:<event_id>, occurred_at:<utc>, webhook_id, data:{message:'…'}}`, enqueues directly for that endpoint.
- `list(): array` / `get(int $id): ?array` / `deliveriesFor(int $webhookId, int $limit): array` / `replay(User $admin, int $webhookId, int $deliveryId): void` (re-queue a `dead` delivery **scoped to its owning webhook** — the `requeue` UPDATE matches `id AND webhook_id AND status='dead'`, so `/admin/webhooks/A/deliveries/B/replay` cannot touch a delivery of another webhook; resets `status='queued'`, `attempt_count=0`, `next_attempt_at=NULL`, and clears stale `error`/`response_status`; audit `webhook_delivery_replayed`).

**Two distinct off-switches — don't conflate them:**

1. **The `webhooks` flag = the deploy-dark gate.** When dark: the admin UI 404s (`gate()`, §9), `dispatch()` no-ops, the worker is idle, and `register`/`rotateSecret` throw `WebhooksDisabledException`. This is the api-token precedent: "feature not released / globally off → nothing happens and nothing is reachable." Re-enabling resumes the worker drain of any already-queued rows. (For defense-in-depth the read/admin service methods — `list`/`get`/`deliveriesFor`/`setActive`/`delete`/`replay` — do **not** throw when dark, so the console/worker/tests still function, but they are not reachable through the 404'd UI.)
2. **Per-endpoint `is_active` toggle + circuit breaker = the operational pause (decision #40's "independent disable path").** While the flag is ON, an operator pauses a single misbehaving endpoint (or the breaker auto-pauses it) *without* losing the subsystem or its delivery log. This is the "pause without losing control" path — at endpoint granularity — not the global flag.

---

### 6. Signing (`WebhookSigner`) — GitHub-style, rotation-safe

Per delivery the worker emits:

```
X-RetroBoards-Event:     <event_type>
X-RetroBoards-Delivery:  <event_id>            # idempotency id for consumer dedupe
X-RetroBoards-Timestamp: <unix seconds>
X-RetroBoards-Signature: sha256=<hex>[, sha256=<hex>...]
Content-Type:            application/json
User-Agent:              RetroBoards-Webhook/1.0
```

The signed message is `"{timestamp}.{rawBody}"`, MAC'd with `hash_hmac('sha256', …, $secret)` (reusing the `SignedToken` discipline). The worker signs with **every secret returned by `$vault->usableSecrets($ref)`** (newest first), emitting one `sha256=<hex>` per secret, comma-separated. During a rotation grace window that yields two signatures (new + old), so a consumer that hasn't switched yet still validates — zero-downtime rotation, and the operational justification for `usableSecrets`. Consumers should enforce a freshness window on `X-RetroBoards-Timestamp` to resist replay.

---

### 7. Egress / SSRF (`EgressGuard` + `CurlWebhookTransport`)

`EgressGuard` is pure and unit-testable: given a URL and a DNS resolver, it returns allow / block-with-reason. It has two entry points: **`validate(url)`** (delivery-time — resolves, classifies, returns the IP to pin) and **`validateStatic(url)`** (registration-time — structural checks always, plus the tier check for *IP-literal* hosts, but **no DNS for hostnames**). `WebhookService::register`/`update` call `validateStatic` so an obviously-bad endpoint (credentials, non-http(s), or a literal private IP like `https://10.0.0.1/`) is rejected as a `422` field error instead of failing later in the worker; the full resolve-then-pin check still runs at delivery.

- **Two-tier scheme/port policy** (the tier is decided across *all* resolved addresses — see resolve-then-pin). *Public tier*: `https` only, port **443** (or **80** only if `WEBHOOK_ALLOW_HTTP`). *Relaxed (private) tier*: may use `http` **and any port** — internal services run on arbitrary ports (n8n on `:5678`, a local test receiver on a random port). `user:pass@host` credential forms are rejected on **both** tiers. (The relaxed tier is what lets the Playwright local receiver on a random port be delivered to — see §12.)
- **Resolve-then-pin, with the tier decided across the whole address set** (DNS-rebinding defense). Resolve the host to its full A/AAAA set (reject if empty), then:
  - **Relaxed (private) tier** applies **only if every** resolved IP is within `WEBHOOK_ALLOWED_PRIVATE_CIDRS`.
  - Otherwise the **public tier** applies and **every** resolved IP must be public-safe (none in the deny set), with the public scheme/port rules.
  - **Any mix is blocked** — e.g. one allowlisted-private address alongside a public or non-allowlisted-private one. (A host that resolves to both a trusted private IP *and* a public IP is exactly the rebinding shape this guard exists to stop; it must not inherit the relaxed `http`/any-port latitude.)
  Then connect via `CURLOPT_RESOLVE` pinned to one validated address **of the decided tier**, so the socket cannot use a re-resolved address.
- **Deny CIDRs** (matched via the new `App\Support\Cidr::contains()` helper): `127.0.0.0/8`, `::1`, `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`, `fc00::/7`, `169.254.0.0/16` (covers `169.254.169.254`), `fe80::/10`, `0.0.0.0/8`, broadcast/multicast/reserved, and IPv4-mapped IPv6 `::ffff:0:0/96`.
- **Opt-in allowlist** (`WEBHOOK_ALLOWED_PRIVATE_CIDRS`, comma-separated, env→config) re-permits otherwise-denied private ranges **only when every resolved address is within it** (per the tier rule above), enabling localhost/LAN targets for self-hosters.

`CurlWebhookTransport` runs `EgressGuard` first (throwing `EgressBlockedException` on block), then performs the cURL with: `CURLOPT_PROTOCOLS`/`CURLOPT_REDIR_PROTOCOLS` pinned to allowed schemes, `CURLOPT_FOLLOWLOCATION=false`, `CURLOPT_SSL_VERIFYPEER=true`, `CURLOPT_SSL_VERIFYHOST=2`, `CURLOPT_CONNECTTIMEOUT` + `CURLOPT_TIMEOUT=5` (D11), and a response-size cap (write-callback abort). Returns a `WebhookResponse{status:int, error:?string}`; never logs the secret, signature, or auth headers.

`WebhookTransport` is a **replaceable seam** (like `Mailer`): `CurlWebhookTransport` in production, `FakeWebhookTransport` in tests (records `deliver()` calls, returns canned status — so worker tests make no real network calls).

---

### 8. `WebhookDeliveryWorker` + `worker:webhooks`

Mirrors `NotificationEmailWorker`. `run(int $limit): array{delivered,retrying,dead,skipped}`:

1. Flag dark → return zero stats (paused; rows left `queued`).
2. `$deliveries->acquireDrainLock()` (`GET_LOCK('rb_webhook_outbox', 0)`) or return zero stats.
3. `try` `foreach ($deliveries->claim($limit) as $row)`:
   `claim()` selects **only deliveries whose endpoint is active**, so a paused or auto-disabled endpoint never consumes the batch or starves active endpoints — `is_active=0` *is* the pause; there is no `skipped` row state:
   ```sql
   SELECT d.* FROM webhook_deliveries d
     JOIN webhooks w ON w.id = d.webhook_id
    WHERE d.status = 'queued'
      AND w.is_active = 1
      AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= UTC_TIMESTAMP())
    ORDER BY d.next_attempt_at ASC, d.id ASC   -- NULLs (immediate) sort first
    LIMIT <int>
   ```
   A paused endpoint's queued rows are simply not claimed; they resume when it is re-enabled. `idx_delivery_claim (status, next_attempt_at)` backs the scan; the join is on the `webhooks` PK. (This is the one place the delivery repo reads a second table — the active-endpoint gate — a deliberate, documented exception to the single-table rule.)
   - `$secrets = $vault->usableSecrets($row['secret_ref'])`; build signed request via `WebhookSigner`. (If `usableSecrets` unexpectedly throws — e.g. a transient vault error — leave the row `queued` and count it `skipped` for this run; do not dead-letter.)
   - `try { $resp = $transport->deliver($url, $headers, $payload, 5); }`
     - **2xx** → `markDelivered(id, status)`; `webhooks->resetConsecutiveFailures(webhookId)`; `webhooks->touchLastStatus(webhookId, status)`.
     - **non-2xx / timeout / connection error** → compare against the **row's own `max_attempts` snapshot** (NOT live config — a mid-flight `webhooks.max_attempts` change must not re-fate already-queued rows): if `attempt_count+1 >= row.max_attempts` → `recordFailure(..., next:null, dead:true)` else `recordFailure(..., next, dead:false)` with `next = backoffNext(attempt_count)`; `webhooks->incrementConsecutiveFailures(webhookId)`; if it crosses the circuit-breaker threshold → add the endpoint to an **in-run skip set** (so its remaining already-claimed rows this batch are skipped, counted `skipped`, not hammered) and `webhooks->disable(webhookId, 'auto-paused after N consecutive failures')` + audit `webhook_auto_disabled`.
     - **`EgressBlockedException`** → terminal: `recordFailure(id, 0, "egress blocked: {reason}", next:null, dead:true)` (a private/blocked URL is a config error, not transient — do not retry).
4. `finally { $deliveries->releaseDrainLock(); }`

**Backoff** (`backoffNext(int $attempt): string` → UTC datetime): schedule `[60, 300, 1500, 7200, 21600]` seconds for attempts 1..5; `max_attempts = 6` (the 6th failure dead-letters). All times UTC (`UTC_TIMESTAMP()`/`gmdate`).

`bin/console` gets a `worker:webhooks` case constructing:

```php
new WebhookDeliveryWorker(
    new WebhookRepository($db), new WebhookDeliveryRepository($db),
    $vault, $transport,
    new FeatureFlags(new SettingRepository($db)),   // the dark no-op check
    new ModerationLogRepository($db),               // the webhook_auto_disabled audit
    $config,
)
```

The worker needs `FeatureFlags` for the flag-dark no-op (step 1) and `ModerationLogRepository` for the **system-actor** `webhook_auto_disabled` audit row (`actor_id` null, mirroring `AntiAbuseService::audit`); `WebhookRepository::autoDisable()` does the single-table UPDATE but the audit write needs the log repo. `WebhookSigner` is a **stateless static helper** that signs with the per-endpoint secret(s) from the vault (**not** `app.key`), so it is not a constructor dependency. `run((int)($argv[2] ?? 100))` prints `delivered/retrying/dead/skipped` as the cron heartbeat. Cron cadence: minutely (like `worker:email`); documented in the runbook.

---

### 9. Admin UI

Routes registered in `App::buildRouter()` near `/admin/api-tokens`:

```
GET  /admin/webhooks                                   index (list)
POST /admin/webhooks                                   register
GET  /admin/webhooks/{id}                              detail (config + delivery log)
POST /admin/webhooks/{id}                              update
POST /admin/webhooks/{id}/toggle                       setActive
POST /admin/webhooks/{id}/rotate                       rotateSecret
POST /admin/webhooks/{id}/test                         sendTestEvent
POST /admin/webhooks/{id}/delete                       delete
POST /admin/webhooks/{id}/deliveries/{deliveryId}/replay   replay
```

`AdminWebhookController` (`final extends Controller`): every action `requireAdmin()` then private `gate()` (`if (!$flags->enabled('webhooks')) throw new NotFoundException();` → 404 when dark). `register`/`rotate` re-render the page with the new secret in the response body (status 200, **never** Flash — same show-once pattern and rationale as API tokens); on `ValidationException` re-render `422` with `errors`+`old` preserved. `toggle`/`delete`/`test`/`replay`/`update` use POST-redirect-GET with a flash. Authorization is `requireAdmin()` (the `site.webhooks` capability is admin-only and the capability resolver is dark). Rate-limit `sendTestEvent` with a `webhook_test` policy.

Templates: `templates/admin/webhooks.php` (endpoint list + create form with the event-catalogue checkboxes and the coverage banner) and `templates/admin/webhook_detail.php` (edit config, rotate secret, the delivery log table with status/attempts/last-response/error, and per-row replay for dead deliveries). All output `$e()`-escaped; `$this->csrfField()` in every form. Flag-gated discovery link on the admin dashboard.

---

### 10. Error handling

- `WebhooksDisabledException` / `EgressBlockedException` — new `final extends RuntimeException` under `src/Core/`. The kernel does not catch `RuntimeException`; `WebhooksDisabledException` only surfaces from admin actions that already gate on the flag (so it is effectively unreachable from the UI but guards direct service use + tests), and `EgressBlockedException` is caught inside the worker/service.
- `ValidationException` (bad URL, unknown event, name length, failed reauth) — caught by the controller → `422` re-render with typed fields preserved (anti-draft-loss).
- Audit actions (`target_type='webhook'`): `webhook_registered`, `webhook_updated`, `webhook_rotated`, `webhook_enabled`, `webhook_disabled`, `webhook_auto_disabled`, `webhook_deleted`, `webhook_delivery_replayed`. Before/after JSON carries only non-secret metadata (id, name, url, events, status, attempt counts) — never the secret, the `reveal()`ed plaintext, or full headers.

---

### 11. Container & config wiring

`App::buildContainer()` adds (Database-first, per the `PostingService`/`ApiTokenService` template):

```php
$c->bind(ServiceSecretRepository::class, fn (Container $c) => new ServiceSecretRepository($c->get(Database::class)));
$c->bind(SecretVault::class, fn (Container $c) => new SecretVault(
    $c->get(Database::class), $c->get(ServiceSecretRepository::class), $c->get(SecretBox::class),
    $c->get(ModerationLogRepository::class), $c->get(FeatureFlags::class), $config));
$c->bind(WebhookRepository::class, fn (Container $c) => new WebhookRepository($c->get(Database::class)));
$c->bind(WebhookDeliveryRepository::class, fn (Container $c) => new WebhookDeliveryRepository($c->get(Database::class)));
$c->bind(WebhookTransport::class, fn (Container $c) => new CurlWebhookTransport(new EgressGuard($config), $config));
$c->bind(WebhookService::class, fn (Container $c) => new WebhookService(
    $c->get(Database::class), $c->get(WebhookRepository::class), $c->get(WebhookDeliveryRepository::class),
    $c->get(SecretVault::class), $c->get(ModerationLogRepository::class), $c->get(FeatureFlags::class),
    $config, $c->get(PasswordHasher::class), $c->get(WriteGate::class)));
```

(`SecretBox` is already bound at `App.php:510`. This is the deferred SecretVault binding SP1 left for its first consumer.)

`config/config.php`:

```php
'webhooks' => [
    'timeout_seconds'           => 5,                      // ADR 0004 D11
    'max_attempts'              => 6,
    'backoff_seconds'           => [60, 300, 1500, 7200, 21600],
    'circuit_breaker_threshold' => 15,                     // consecutive failures → auto-pause
    'max_response_bytes'        => 65536,
    'allow_http'                => Env::bool('WEBHOOK_ALLOW_HTTP', false),   // NOT (bool) Env::get — "false" is a truthy string
    'allowed_private_cidrs'     => array_values(array_filter(array_map('trim',
        explode(',', (string) Env::get('WEBHOOK_ALLOWED_PRIVATE_CIDRS', ''))))),
],
// rate_limits: add 'webhook_test' => [20, 600],
```

`FeatureFlags::DEFAULTS` (in the B2 block at lines 69–73). **Flag dependency:** webhook *creation/rotation* additionally requires `service_secrets` enabled (SecretVault mints/rotates the signing secret); *delivery and inspection* do not (`reveal`/`usableSecrets` work even if `service_secrets` later goes dark), so an endpoint created while both were on keeps delivering. The admin create/rotate flows assert this and surface a clear error instead of a 500.

```php
'webhooks' => false,          // outbound webhook delivery engine + admin UI (B2 sub-project 3)
```

---

### 12. Testing & evidence (target R2 + R3, plus Playwright for the UI)

Per-test isolation is one rolled-back transaction (no savepoints); assert observable behaviour, not row counts where the code rolls back. `FakeWebhookTransport` keeps the suite offline.

- **Unit**
  - `tests/Unit/Security/WebhookEventsTest.php` — catalogue validity, `ping` present, unknown rejected.
  - `tests/Unit/Security/EgressGuardTest.php` — **the critical security unit:** blocks `127.0.0.1`, `::1`, `10/172.16/192.168` ranges, `169.254.169.254`, `fe80::`, `::ffff:10.0.0.1` (v4-mapped); allows a public IP; opt-in allowlist re-permits a chosen CIDR (relaxed tier → `http` + non-standard port allowed when **all** addresses are allowlisted); **mixed-DNS resolver returning one allowlisted-private + one public IP is BLOCKED** (and a non-allowlisted private mixed with public is blocked); rejects non-https (unless allowed), odd ports on the public tier, and `user:pass@` URLs; resolve-then-pin uses an injectable resolver and pins one validated address of the decided tier.
  - `tests/Unit/Service/WebhookSignerTest.php` — header set + exact `sha256=` HMAC over `"{ts}.{body}"`; two comma-separated signatures when given two secrets.
- **Integration — service** (`tests/Integration/Service/WebhookServiceTest.php`): register returns secret once and stores a `svcsec_*` ref (not plaintext) recoverable via the vault; wrong reauth → `ValidationException`; suspended admin → `ForbiddenException`; flag dark → `register` throws `WebhooksDisabledException` and `dispatch` returns 0; `webhooks` on but `service_secrets` dark → `register`/`rotateSecret` raise a `ValidationException` (not an uncaught `SecretsDisabledException`); `dispatch` enqueues exactly one delivery per subscribed active endpoint, dedupes a repeat of the same `(webhook_id, event_type, event_id)`, and does NOT collapse two different event types for one source id; `update`/`toggle`/`delete`/`rotate`; audit rows contain neither the secret nor the password.
- **Integration — worker** (`tests/Integration/Worker/WebhookDeliveryWorkerTest.php`, the path PHASE_3_PLAN §457 already names): 2xx → `delivered` + signature header present & valid; failure → `attempt_count` incremented, `next_attempt_at` set, still `queued`; reaching `max_attempts` → `dead`; circuit breaker auto-disables after threshold; flag dark → worker delivers nothing; `EgressBlockedException` → immediate `dead`; `replay` re-queues a dead delivery; rotation → two signatures emitted; **a paused (`is_active=0`) endpoint's queued deliveries are NOT claimed — they don't consume the batch (an active endpoint's row queued behind them still delivers in the same run) and they resume after re-enable.**
- **Integration — admin HTTP** (`tests/Integration/Admin/AdminWebhookTest.php`): register shows secret once (200, not redirect; subsequent GET hides it); wrong reauth → 422 with typed `name`/`url` preserved; all routes 404 when flag dark; suspended admin → 403; `toggle`/`delete`/`test`/`replay` PRG; delivery log renders.
- **Schema** (`tests/Integration/Core/AppWebhooksSchemaTest.php`): `webhooks.secret_ref` exists and **no** plaintext `secret` column; `webhook_deliveries` has `uq_delivery_idem` (unique, spanning `webhook_id, event_type, event_id`) and `idx_delivery_claim`; `moderation_log.target_type` enum contains `'webhook'`.
- **Flag-dark regression** added to `tests/Integration/Core/AppFeatureFlagTest.php`: `webhooks` defaults dark; per-flag override isolation.
- **Playwright** (`tests/browser/gate-a.spec.ts`, desktop + mobile): seed (`tests/browser/seed.php`) enables **both** `webhooks` and `service_secrets` (create/rotate needs the vault, §11), and sets `WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32` — which, per §7's two-tier policy, grants `http` + arbitrary-port to that range, so a local receiver on a random port is a valid target. The spec registers an endpoint pointing at a tiny local `http://127.0.0.1:<port>` receiver, captures show-once (`22-admin-webhook-registered`), clicks "Send test event", shells out to `php bin/console worker:webhooks`, reloads the delivery log, and captures a `delivered`/`200` row (`23-admin-webhook-delivery-log`). Fallback if the receiver proves flaky: register + show-once + a `queued` delivery row.

---

### 13. Documentation & status updates

- **SCHEMA.md:** replace the legacy `webhooks` DDL (plaintext `secret`) with the reconciled shape (`secret_ref`); add the `webhook_deliveries` DDL; remove "durable webhook-delivery ledger" from the §8.2 schema-gap list; add a §9 changelog entry + version bump.
- **PHASE_5_STATUS.md:** mark SP3 landed; B2 now 3/4; note the deferral of producer wiring to SP4.
- **ADMIN.md:** note the secret is a vault reference (not the plaintext column the old §10 DDL shows); the §8.6 behaviour description is otherwise accurate.
- **This spec** records the design decisions (engine-only / defer producers to SP4 / deny-private-default + opt-in allowlist / dual-signature rotation / no `delivering` state) so they are not implied work (ADR 0004 carryover discipline).

---

### 14. Out of scope (explicit)

- Real domain-event producer wiring (`PostingService`/`ModerationService`/etc. calling `dispatch()`) — **SP4 (first-party hook registry)**.
- Inbound webhook *receivers* (the email ESP bounce/complaint webhooks in ADMIN §7 are a separate, unrelated concept).
- Per-destination-host rate limiting beyond the worker batch limit + circuit breaker.
- APP_KEY / envelope-key rotation (a SecretVault operational runbook concern, already out of scope there).
- Reaching R4/R5 (acceptance): browser/no-JS/security/perf/operating sign-off + product-owner acceptance — this is a deploy-dark R2+R3 landing.
- Untrusted/sandboxed PHP execution (Gate B, flag `server_extensions`).

---

## Part II — Implementation plan (the "how / task breakdown")

*(Was the body of this plan file.)*

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the deploy-dark webhook *delivery engine* + operator admin UI for B2 sub-project 3 (outbound HTTPS, HMAC-signed, durable retry/backoff/dead-letter ledger, SSRF-controlled, drained by a `GET_LOCK` worker), with the `dispatch()` producer seam and a `ping` test path — deferring real domain-event wiring to SP4.

**Architecture:** Mirrors the landed B2 API-token slice (migration → repository → flag-gated service → admin UI) and the `email_deliveries` worker pattern, adding the retry/backoff/dead-letter columns the email table lacks. The per-endpoint HMAC secret lives in the existing `SecretVault` (we add its deferred container binding). Outbound HTTP goes through a new SSRF-hardened `WebhookTransport` seam.

**Tech Stack:** Vanilla PHP 8.2+, MySQL/MariaDB, PDO (`EMULATE_PREPARES=false`), PHPUnit, Playwright. No framework.

**Spec:** merged into Part I above (was `docs/superpowers/specs/2026-06-28-webhook-delivery-design.md`).

### Global Constraints

Every task implicitly includes these (verbatim from the spec / CLAUDE.md / DECISIONS.md):

- **Migrations are additive / forward-only.** Next number is **`0057`**. Anonymous class returning `up(\PDO)/down(\PDO)`, raw `$pdo->exec(<<<'SQL' … SQL)`. After landing it, hand-update `SCHEMA.md` (shape + §9 changelog + version bump).
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` (cast to int, clamp, concatenate); never reuse a named placeholder twice.
- **UTC everywhere:** `UTC_TIMESTAMP()` in SQL, `gmdate()` in PHP.
- **Repositories are `final`, constructor `(private Database $db)`, single-table SQL, return associative arrays.** The one documented exception in this slice: `WebhookDeliveryRepository::claim()` joins `webhooks` for the active-endpoint gate.
- **Services own business rules + transactions.** Order on write paths: `WriteGate::assertCanWrite` → flag check → password reauth (secret-minting ops only) → validation → `$db->transaction(mutation + audit)`. Audit writes (`ModerationLogRepository::log`) run inside the same transaction. `Database::transaction` is reentrant (no savepoints).
- **Feature flags default dark.** `enabled('typo')` returns false. New subsystem ships behind `webhooks` (default false) with a regression test asserting it is dark.
- **Flag dependency:** webhook *create/rotate* also requires `service_secrets` enabled (SecretVault mints the secret); *delivery/inspection* do not.
- **Strict CSP:** no inline `<script>`/`<style>`. Escape all template output with `$e()` / `$this->e()`; the only raw echo is pre-sanitized HTML (not used here). Emit CSRF with `$this->csrfField()` on every POST form.
- **Secrets never logged/exported.** Audit before/after JSON, error columns, and exceptions must never contain a secret, the revealed plaintext, or auth/signature headers. Store only the opaque `svcsec_*` ref.
- **Delivery timeout 5s** (ADR 0004 D11). **No untrusted/sandboxed PHP execution** (Gate B).
- **Test isolation:** each integration test runs in one transaction rolled back in tearDown (no savepoints). You *can* read rows you wrote within the same test; assert observable behavior, not cross-transaction row counts. Unit tests extend PHPUnit `TestCase` directly. PHPUnit is strict (`failOnWarning`, `failOnRisky`): every test needs ≥1 assertion, no stray output, no PHP warnings.
- **Commands:** one file → `vendor/bin/phpunit <path>`; one method → `vendor/bin/phpunit --filter <name>`; full suite → `composer test`. The test DB is dropped + re-migrated by `tests/bootstrap.php` on every run, so once `0057` lands it applies to all subsequent test runs. The test DB container must be reachable (`docker start forum-software-db-1` if needed, port 3307).
- **Every commit message ends with:** `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- **Branch:** all work lands on `b2-webhook-delivery` (already created off the SP1+SP2 HEAD).

---

### File Structure

**New files**

| Path | Responsibility |
|---|---|
| `database/migrations/0057_phase5_webhooks.php` | `webhooks` config table + `webhook_deliveries` ledger + `moderation_log` enum `'webhook'` |
| `src/Support/Cidr.php` | pure static `contains(ip, cidr)` (extracted `inet_pton` matcher) |
| `src/Security/WebhookEvents.php` | event-name catalogue (`isValid`/`all`) |
| `src/Security/EgressGuard.php` | SSRF policy: validate URL + resolved IPs, return pinned IP |
| `src/Core/EgressBlockedException.php` | `final extends \RuntimeException` |
| `src/Core/WebhooksDisabledException.php` | `final extends \RuntimeException` |
| `src/Service/Webhook/WebhookResponse.php` | `{status:int, error:?string}` DTO |
| `src/Service/Webhook/WebhookTransport.php` | `deliver()` interface (replaceable seam) |
| `src/Service/Webhook/CurlWebhookTransport.php` | SSRF-hardened cURL impl (uses `EgressGuard`, resolve-then-pin) |
| `src/Service/Webhook/FakeWebhookTransport.php` | test double (records calls, canned responses) |
| `src/Service/Webhook/WebhookSigner.php` | stateless GitHub-style header/signature builder (multi-sig) |
| `src/Repository/WebhookRepository.php` | endpoint CRUD + circuit-breaker counters |
| `src/Repository/WebhookDeliveryRepository.php` | durable ledger (enqueue/claim/transitions/lock) |
| `src/Service/WebhookService.php` | flag-gated orchestrator (the public surface) |
| `src/Worker/WebhookDeliveryWorker.php` | drains the ledger |
| `src/Controller/AdminWebhookController.php` | admin UI controller |
| `templates/admin/webhooks.php` | list + create form |
| `templates/admin/webhook_detail.php` | edit / rotate / test / delete / delivery log / replay |
| `tests/Unit/Support/CidrTest.php`, `tests/Unit/Security/WebhookEventsTest.php`, `tests/Unit/Security/EgressGuardTest.php`, `tests/Unit/Service/WebhookSignerTest.php`, `tests/Unit/Service/WebhookTransportTest.php` | unit tests |
| `tests/Integration/Repository/WebhookRepositoryTest.php`, `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`, `tests/Integration/Service/WebhookServiceTest.php`, `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`, `tests/Integration/Admin/AdminWebhookTest.php`, `tests/Integration/Core/AppWebhooksSchemaTest.php` | integration tests |

**Modified files**

| Path | Change |
|---|---|
| `src/Core/FeatureFlags.php` | add `'webhooks' => false` to the B2 block |
| `config/config.php` | add `webhooks` config block + `rate_limits.webhook_test` |
| `src/Core/App.php` | imports + container bindings (incl. deferred `SecretVault`) + routes |
| `bin/console` | `worker:webhooks` case + imports |
| `templates/admin/dashboard.php` | flag-gated discovery link |
| `tests/Integration/Core/AppFeatureFlagTest.php` | add `'webhooks'` to the phase-5 dark list |
| `tests/browser/seed.php`, `tests/browser/playwright.config.ts`, `tests/browser/gate-a.spec.ts` | Playwright evidence |
| `SCHEMA.md`, `PHASE_5_STATUS.md` | doc + status updates |

---

### Task 1: Feature flag `webhooks` (deploy-dark) + regression test

**Files:**
- Modify: `src/Core/FeatureFlags.php` (B2 block, ~lines 69–73)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php:51-57` (add `'webhooks'` to the phase-5 dark list)

**Interfaces:**
- Produces: the flag name string `'webhooks'`, readable via `FeatureFlags::enabled('webhooks')` (false by default).

- [ ] **Step 1: Extend the failing test.** In `tests/Integration/Core/AppFeatureFlagTest.php`, inside `test_phase5_foundation_flags_default_dark`, add `'webhooks'` to the `$phase5` array (after `'api_tokens'`) and an assertion that it is a declared flag:

```php
            // Gate A
            'package_registry', 'package_themes', 'capabilities', 'passkeys',
            'provider_registry', 'invitations', 'service_secrets', 'api_tokens', 'webhooks',
```

Then after the existing `assertArrayHasKey('api_tokens', ...)` line add:

```php
        self::assertArrayHasKey('webhooks', $flags->all(), 'webhooks must be a declared flag');
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark`
Expected: FAIL — `Failed asserting that an array has the key 'webhooks'` (the flag is not declared yet).

- [ ] **Step 3: Add the flag.** In `src/Core/FeatureFlags.php`, in the B2 block, after the `api_tokens` line add:

```php
        'webhooks' => false,          // outbound webhook delivery engine + admin UI (B2 sub-project 3)
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "$(printf 'feat(webhooks): add deploy-dark webhooks flag (B2 SP3)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 2: Migration `0057` (schema) + schema test + SCHEMA.md

**Files:**
- Create: `database/migrations/0057_phase5_webhooks.php`
- Create: `tests/Integration/Core/AppWebhooksSchemaTest.php`
- Modify: `SCHEMA.md` (reconcile `webhooks` shape, add `webhook_deliveries`, §9 changelog, version bump)

**Interfaces:**
- Produces: tables `webhooks` (cols: `id, name, url, events, secret_ref, is_active, consecutive_failures, disabled_at, disabled_reason, last_status, last_delivered_at, created_by, created_at, updated_at`) and `webhook_deliveries` (cols: `id, webhook_id, event_type, event_id, payload, status ENUM('queued','delivered','dead'), attempt_count, max_attempts, next_attempt_at, last_attempt_at, response_status, error, created_at, delivered_at`); unique `uq_delivery_idem (webhook_id, event_type, event_id)`; index `idx_delivery_claim (status, next_attempt_at)`; `moderation_log.target_type` enum gains `'webhook'`.

- [ ] **Step 1: Write the failing schema test.** Create `tests/Integration/Core/AppWebhooksSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/** Schema-shape checks for the B2 webhooks tables (migration 0057). */
final class AppWebhooksSchemaTest extends TestCase
{
    private function dataType(string $table, string $col): ?string
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $col],
        );
        return $row === null ? null : (string) $row['t'];
    }

    public function test_webhooks_table_stores_a_secret_ref_not_plaintext(): void
    {
        self::assertNotNull($this->dataType('webhooks', 'secret_ref'), 'secret_ref column must exist');
        self::assertNull($this->dataType('webhooks', 'secret'), 'no plaintext secret column may exist');
        self::assertSame('json', $this->dataType('webhooks', 'events'));
    }

    public function test_webhook_deliveries_has_idempotency_and_claim_indexes(): void
    {
        $unique = (int) $this->db->fetchValue(
            "SELECT COUNT(DISTINCT COLUMN_NAME) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_deliveries'
               AND INDEX_NAME = 'uq_delivery_idem' AND NON_UNIQUE = 0",
        );
        self::assertSame(3, $unique, 'uq_delivery_idem must span webhook_id, event_type, event_id');

        $claim = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webhook_deliveries'
               AND INDEX_NAME = 'idx_delivery_claim'",
        );
        self::assertGreaterThan(0, $claim, 'idx_delivery_claim must exist');
    }

    public function test_moderation_log_enum_accepts_webhook(): void
    {
        $colType = (string) $this->db->fetchValue(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'moderation_log' AND COLUMN_NAME = 'target_type'",
        );
        self::assertStringContainsString("'webhook'", $colType);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppWebhooksSchemaTest.php`
Expected: FAIL — `secret_ref column must exist` (the tables don't exist yet; `bootstrap.php` re-migrates but there is no `0057`).

- [ ] **Step 3: Write the migration.** Create `database/migrations/0057_phase5_webhooks.php`:

```php
<?php

declare(strict_types=1);

/**
 * 0057 · Phase 5 Gate A prerequisite (B2 sub-project 3) — webhook delivery.
 *
 * ADDITIVE. Two tables: `webhooks` (endpoint config; the HMAC secret is a
 * SecretVault svcsec_* reference, NOT plaintext — reconciling the legacy
 * SCHEMA/ADMIN DDL) and `webhook_deliveries` (the durable per-attempt ledger
 * PHASE_3_PLAN §8.2 #6 requires: retry/backoff/dead-letter + idempotent event
 * identity). Also extends moderation_log.target_type with 'webhook'.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE webhooks (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name                 VARCHAR(80)     NOT NULL,
              url                  VARCHAR(512)    NOT NULL,
              events               JSON            NOT NULL,
              secret_ref           VARCHAR(64)     NOT NULL,
              is_active            TINYINT(1)      NOT NULL DEFAULT 1,
              consecutive_failures INT UNSIGNED    NOT NULL DEFAULT 0,
              disabled_at          DATETIME        NULL,
              disabled_reason      VARCHAR(190)    NULL,
              last_status          INT             NULL,
              last_delivered_at    DATETIME        NULL,
              created_by           BIGINT UNSIGNED NOT NULL,
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_webhook_active (is_active),
              CONSTRAINT fk_webhook_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE webhook_deliveries (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              webhook_id      BIGINT UNSIGNED NOT NULL,
              event_type      VARCHAR(80)     NOT NULL,
              event_id        VARCHAR(64)     NOT NULL,
              payload         MEDIUMTEXT      NOT NULL,
              status          ENUM('queued','delivered','dead') NOT NULL DEFAULT 'queued',
              attempt_count   INT UNSIGNED    NOT NULL DEFAULT 0,
              max_attempts    INT UNSIGNED    NOT NULL,
              next_attempt_at DATETIME        NULL,
              last_attempt_at DATETIME        NULL,
              response_status INT             NULL,
              error           VARCHAR(255)    NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              delivered_at    DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_delivery_idem (webhook_id, event_type, event_id),
              KEY idx_delivery_claim (status, next_attempt_at),
              CONSTRAINT fk_delivery_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'webhook'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS webhook_deliveries');
        $pdo->exec('DROP TABLE IF EXISTS webhooks');
    }
};
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Core/AppWebhooksSchemaTest.php`
Expected: PASS (bootstrap re-migrates, creating `0057`'s tables).

- [ ] **Step 5: Update SCHEMA.md.** Replace the legacy `webhooks` DDL (the one with `secret VARCHAR(128)` plaintext) with the reconciled shape above; add the `webhook_deliveries` DDL; remove "durable webhook-delivery ledger" from the §8.2 schema-gap list; add a §9 changelog entry ("0057: webhooks + webhook_deliveries; secret moved to SecretVault ref; moderation_log enum += 'webhook'") and bump the SCHEMA.md version header.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/0057_phase5_webhooks.php tests/Integration/Core/AppWebhooksSchemaTest.php SCHEMA.md
git commit -m "$(printf 'feat(webhooks): 0057 schema — webhooks + durable delivery ledger\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 3: `Cidr` helper + unit test

**Files:**
- Create: `src/Support/Cidr.php`
- Create: `tests/Unit/Support/CidrTest.php`

**Interfaces:**
- Produces: `App\Support\Cidr::contains(string $ip, string $cidr): bool` — exact match when `$cidr` has no `/`; else `inet_pton` byte-prefix containment (v4/v6); returns false on malformed input or family mismatch.

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Support/CidrTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cidr;
use PHPUnit\Framework\TestCase;

final class CidrTest extends TestCase
{
    public function test_ipv4_containment(): void
    {
        self::assertTrue(Cidr::contains('10.1.2.3', '10.0.0.0/8'));
        self::assertFalse(Cidr::contains('11.0.0.1', '10.0.0.0/8'));
        self::assertTrue(Cidr::contains('192.168.1.5', '192.168.0.0/16'));
        self::assertTrue(Cidr::contains('169.254.169.254', '169.254.0.0/16'));
    }

    public function test_exact_match_without_slash(): void
    {
        self::assertTrue(Cidr::contains('1.2.3.4', '1.2.3.4'));
        self::assertFalse(Cidr::contains('1.2.3.5', '1.2.3.4'));
    }

    public function test_ipv6_and_family_mismatch(): void
    {
        self::assertTrue(Cidr::contains('::1', '::1/128'));
        self::assertTrue(Cidr::contains('fe80::1', 'fe80::/10'));
        self::assertFalse(Cidr::contains('10.0.0.1', '::1/128'), 'v4 vs v6 must not match');
        self::assertFalse(Cidr::contains('garbage', '10.0.0.0/8'));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Support/CidrTest.php`
Expected: FAIL — `Error: Class "App\Support\Cidr" not found`.

- [ ] **Step 3: Implement.** Create `src/Support/Cidr.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pure CIDR containment (IPv4/IPv6), extracted from ClientIdentifier so
 * EgressGuard (and others) can reuse the inet_pton byte-prefix logic without
 * an instance. Exact match when $cidr has no "/".
 */
final class Cidr
{
    public static function contains(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        if ($bits < 0 || $bits > strlen($ipBin) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
            return false;
        }
        if ($rem === 0) {
            return true;
        }
        $mask = chr((0xff << (8 - $rem)) & 0xff);
        return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Support/CidrTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/Cidr.php tests/Unit/Support/CidrTest.php
git commit -m "$(printf 'feat(webhooks): add App\\\\Support\\\\Cidr containment helper\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 4: `WebhookEvents` catalogue + unit test

**Files:**
- Create: `src/Security/WebhookEvents.php`
- Create: `tests/Unit/Security/WebhookEventsTest.php`

**Interfaces:**
- Produces: `App\Security\WebhookEvents::EVENTS` (const map name⇒desc, includes `'ping'`), `::isValid(string): bool`, `::all(): array<string,string>`.

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Security/WebhookEventsTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\WebhookEvents;
use PHPUnit\Framework\TestCase;

final class WebhookEventsTest extends TestCase
{
    public function test_catalogue_includes_ping_and_validates(): void
    {
        self::assertTrue(WebhookEvents::isValid('ping'));
        self::assertTrue(WebhookEvents::isValid('topic.created'));
        self::assertFalse(WebhookEvents::isValid('not.a.real.event'));
        self::assertArrayHasKey('ping', WebhookEvents::all());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Security/WebhookEventsTest.php`
Expected: FAIL — `Error: Class "App\Security\WebhookEvents" not found`.

- [ ] **Step 3: Implement.** Create `src/Security/WebhookEvents.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

/**
 * The webhook event-name catalogue (like ApiScopes). Defines the contract SP4
 * (the first-party hook registry) will fire into. Dotted names match the
 * Phase-4 convention (thread.solved, thread.status). Only `ping` actually fires
 * in this slice; the admin UI surfaces a banner saying so.
 */
final class WebhookEvents
{
    /** @var array<string,string> event name => human description */
    public const EVENTS = [
        'ping' => 'Test event (fires from the admin "Send test event" action)',
        'topic.created' => 'A new topic/thread became publicly visible',
        'reply.created' => 'A new reply became publicly visible',
        'post.edited' => 'A post was edited by its author',
        'post.deleted' => 'A post was deleted (author or moderator)',
        'thread.solved' => 'A thread was marked solved / answer accepted',
        'report.created' => 'A post was reported',
        'report.resolved' => 'A report was resolved or dismissed',
        'member.registered' => 'A new member account was created',
        'member.banned' => 'A member was banned',
        'moderation.auto_action' => 'Anti-abuse took an automated action (flag/hold/block)',
    ];

    public static function isValid(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        return self::EVENTS;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Security/WebhookEventsTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Security/WebhookEvents.php tests/Unit/Security/WebhookEventsTest.php
git commit -m "$(printf 'feat(webhooks): add WebhookEvents catalogue\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 5: `EgressGuard` SSRF policy (+ `EgressBlockedException` + egress config) + unit test

**Files:**
- Create: `src/Core/EgressBlockedException.php`
- Create: `src/Security/EgressGuard.php`
- Create: `tests/Unit/Security/EgressGuardTest.php`
- Modify: `config/config.php` (add the `webhooks` config block — egress keys used here; retry keys used in Task 11)

**Interfaces:**
- Consumes: `App\Support\Cidr::contains` (Task 3).
- Produces: `App\Core\EgressBlockedException` (`final extends \RuntimeException`); `App\Security\EgressGuard::__construct(bool $allowHttp, list<string> $allowedCidrs, ?callable $resolver = null)`, `validate(string $url): string` (full delivery-time check — resolves, classifies, returns the literal IP to pin; throws `EgressBlockedException`), and `validateStatic(string $url): void` (registration-time pre-check — structural always, plus the tier check for IP-literal hosts, **no DNS for hostnames**; throws `EgressBlockedException`). The injected `$resolver` is `callable(string $host): array<int,string>` (host → IPs); the default resolves real DNS.

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Security/EgressGuardTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use PHPUnit\Framework\TestCase;

final class EgressGuardTest extends TestCase
{
    /** @param array<int,string> $ips */
    private function guard(array $ips, bool $allowHttp = false, array $allow = []): EgressGuard
    {
        return new EgressGuard($allowHttp, $allow, static fn (string $host): array => $ips);
    }

    public function test_public_https_target_is_allowed_and_returns_pinned_ip(): void
    {
        $ip = $this->guard(['93.184.216.34'])->validate('https://example.test/hook');
        self::assertSame('93.184.216.34', $ip);
    }

    public function test_blocks_loopback_private_linklocal_metadata_and_v4mapped(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '192.168.1.9', '169.254.169.254', 'fe80::1', '::ffff:10.0.0.1'] as $ip) {
            try {
                $this->guard([$ip])->validate('https://internal.test/hook');
                self::fail("expected block for $ip");
            } catch (EgressBlockedException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_allowlist_relaxes_scheme_and_port_for_all_allowlisted(): void
    {
        $ip = $this->guard(['127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://localhost:8011/hook');
        self::assertSame('127.0.0.1', $ip);
    }

    public function test_mixed_public_and_private_dns_is_blocked(): void
    {
        $this->expectException(EgressBlockedException::class);
        // One allowlisted-private + one public address must NOT inherit the relaxed tier.
        $this->guard(['1.2.3.4', '127.0.0.1'], false, ['127.0.0.1/32'])->validate('http://rebind.test:8011/hook');
    }

    public function test_public_tier_rejects_http_and_odd_ports_and_credentials(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard(['93.184.216.34'])->validate('http://example.test/hook'); // http on public tier, no allow_http
    }

    public function test_rejects_credentials_in_url(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard(['93.184.216.34'])->validate('https://user:pass@example.test/hook');
    }

    public function test_rejects_unresolvable_host(): void
    {
        $this->expectException(EgressBlockedException::class);
        $this->guard([])->validate('https://nope.test/hook');
    }

    public function test_validate_static_rejects_literal_private_ip_but_allows_hostname_without_dns(): void
    {
        // A resolver that would FAIL the test if called — validateStatic must not resolve hostnames.
        $guard = new EgressGuard(false, [], static function (): array {
            throw new \RuntimeException('validateStatic must not perform DNS for hostnames');
        });
        $guard->validateStatic('https://example.test/hook'); // hostname → structural-only, no throw
        self::assertTrue(true);

        $this->expectException(EgressBlockedException::class);
        $guard->validateStatic('https://10.0.0.1/hook'); // IP literal → tier-checked, blocked
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Security/EgressGuardTest.php`
Expected: FAIL — `Error: Class "App\Core\EgressBlockedException" not found`.

- [ ] **Step 3a: Implement the exception.** Create `src/Core/EgressBlockedException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown when a webhook URL/target fails the SSRF egress policy. Terminal (dead-letter). */
final class EgressBlockedException extends \RuntimeException
{
}
```

- [ ] **Step 3b: Implement the guard.** Create `src/Security/EgressGuard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\EgressBlockedException;
use App\Support\Cidr;

/**
 * SSRF egress policy for outbound webhook delivery. Two tiers, decided across
 * ALL resolved addresses (DNS-rebinding defense):
 *   - Relaxed (operator-allowlisted): every resolved IP is in $allowedCidrs →
 *     http + any port permitted.
 *   - Public: otherwise → every resolved IP must be public-safe (not in DENY)
 *     and the scheme/port must be https/443 (or 80 when $allowHttp).
 * Any mix (allowlisted-private + public/non-allowlisted) is blocked. validate()
 * returns one validated IP literal for the caller to pin via CURLOPT_RESOLVE.
 */
final class EgressGuard
{
    private const DENY = [
        '127.0.0.0/8', '::1/128', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        'fc00::/7', '169.254.0.0/16', 'fe80::/10', '0.0.0.0/8',
        '100.64.0.0/10', '224.0.0.0/4', '240.0.0.0/4', '::ffff:0:0/96',
    ];

    /** @var callable(string):array<int,string> */
    private $resolver;

    /**
     * @param list<string> $allowedCidrs operator opt-in private ranges
     * @param null|callable(string):array<int,string> $resolver host => IPs (tests inject a fake)
     */
    public function __construct(
        private bool $allowHttp,
        private array $allowedCidrs,
        ?callable $resolver = null,
    ) {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = [];
            $a = @gethostbynamel($host);
            if (is_array($a)) {
                $ips = $a;
            }
            $recs = @dns_get_record($host, DNS_AAAA);
            if (is_array($recs)) {
                foreach ($recs as $r) {
                    if (isset($r['ipv6'])) {
                        $ips[] = (string) $r['ipv6'];
                    }
                }
            }
            return $ips;
        };
    }

    /**
     * Full delivery-time check: resolve, classify the whole address set, and
     * return one validated IP to pin via CURLOPT_RESOLVE.
     * @throws EgressBlockedException
     */
    public function validate(string $url): string
    {
        [$scheme, $host, $port] = $this->parse($url);
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);
        $ips = array_values(array_filter($ips, static fn ($ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false));
        if ($ips === []) {
            throw new EgressBlockedException('Host did not resolve.');
        }
        return $this->classify($ips, $scheme, $port);
    }

    /**
     * Registration-time pre-check WITHOUT DNS. Always enforces structure
     * (scheme/credentials); for an IP-literal host it also applies the full tier
     * check (so `https://10.0.0.1/` is rejected at registration). A hostname
     * passes structural checks here — its address tier is enforced at delivery.
     * @throws EgressBlockedException
     */
    public function validateStatic(string $url): void
    {
        [$scheme, $host, $port] = $this->parse($url);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->classify([$host], $scheme, $port);
        }
    }

    /**
     * @return array{0:string,1:string,2:int} [scheme, host, port]
     * @throws EgressBlockedException
     */
    private function parse(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new EgressBlockedException('Malformed URL.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new EgressBlockedException('Credentials in URL are not allowed.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new EgressBlockedException('Only http(s) is allowed.');
        }
        $host = trim((string) $parts['host'], '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        return [$scheme, $host, $port];
    }

    /**
     * Decide the tier across ALL addresses and return one to pin.
     * @param array<int,string> $ips
     * @throws EgressBlockedException
     */
    private function classify(array $ips, string $scheme, int $port): string
    {
        $allAllowlisted = true;
        foreach ($ips as $ip) {
            if (!$this->inAllowlist($ip)) {
                $allAllowlisted = false;
                break;
            }
        }

        if ($allAllowlisted) {
            return $ips[0]; // relaxed tier — http + any port permitted
        }

        foreach ($ips as $ip) {
            if ($this->inDeny($ip)) {
                throw new EgressBlockedException('Resolves to a blocked address.');
            }
        }
        if ($scheme !== 'https' && !$this->allowHttp) {
            throw new EgressBlockedException('Only HTTPS is allowed for public targets.');
        }
        $allowedPorts = $this->allowHttp ? [443, 80] : [443];
        if (!in_array($port, $allowedPorts, true)) {
            throw new EgressBlockedException('Port ' . $port . ' is not allowed.');
        }
        return $ips[0];
    }

    private function inAllowlist(string $ip): bool
    {
        foreach ($this->allowedCidrs as $cidr) {
            if (Cidr::contains($ip, (string) $cidr)) {
                return true;
            }
        }
        return false;
    }

    private function inDeny(string $ip): bool
    {
        foreach (self::DENY as $cidr) {
            if (Cidr::contains($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }
}
```

- [ ] **Step 3c: Add the config block.** In `config/config.php`, after the `'secrets' => [ ... ]` block, add:

```php
    'webhooks' => [
        'timeout_seconds' => 5,                         // ADR 0004 D11
        'max_attempts' => 6,
        'backoff_seconds' => [60, 300, 1500, 7200, 21600],
        'circuit_breaker_threshold' => 15,              // consecutive failures => auto-pause
        'max_response_bytes' => 65536,
        'allow_http' => Env::bool('WEBHOOK_ALLOW_HTTP', false),
        'allowed_private_cidrs' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('WEBHOOK_ALLOWED_PRIVATE_CIDRS', ''))))),
    ],
```

And in `'rate_limits' => [ ... ]` add `'webhook_test' => [20, 600],`.

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Security/EgressGuardTest.php`
Expected: PASS (all cases, including mixed-DNS blocked).

- [ ] **Step 5: Commit**

```bash
git add src/Core/EgressBlockedException.php src/Security/EgressGuard.php tests/Unit/Security/EgressGuardTest.php config/config.php
git commit -m "$(printf 'feat(webhooks): SSRF EgressGuard with two-tier allowlist + config\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 6: `WebhookSigner` (GitHub-style, multi-signature) + unit test

**Files:**
- Create: `src/Service/Webhook/WebhookSigner.php`
- Create: `tests/Unit/Service/WebhookSignerTest.php`

**Interfaces:**
- Produces: `App\Service\Webhook\WebhookSigner::headers(string $eventType, string $eventId, int $timestamp, string $body, array $secrets): array<string,string>` — stateless; signs `"{timestamp}.{body}"` with each secret (HMAC-SHA256), emits comma-separated `sha256=<hex>` signatures (newest first) plus `X-RetroBoards-Event/Delivery/Timestamp` and `Content-Type`/`User-Agent`.

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Service/WebhookSignerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Webhook\WebhookSigner;
use PHPUnit\Framework\TestCase;

final class WebhookSignerTest extends TestCase
{
    public function test_headers_contain_signature_over_timestamp_dot_body(): void
    {
        $h = WebhookSigner::headers('ping', 'evt_1', 1782680000, '{"a":1}', ['secretA']);
        $expected = 'sha256=' . hash_hmac('sha256', '1782680000.{"a":1}', 'secretA');
        self::assertSame($expected, $h['X-RetroBoards-Signature']);
        self::assertSame('ping', $h['X-RetroBoards-Event']);
        self::assertSame('evt_1', $h['X-RetroBoards-Delivery']);
        self::assertSame('1782680000', $h['X-RetroBoards-Timestamp']);
        self::assertSame('application/json', $h['Content-Type']);
    }

    public function test_two_secrets_emit_two_comma_separated_signatures(): void
    {
        $h = WebhookSigner::headers('ping', 'evt_2', 1782680000, 'body', ['newS', 'oldS']);
        $new = 'sha256=' . hash_hmac('sha256', '1782680000.body', 'newS');
        $old = 'sha256=' . hash_hmac('sha256', '1782680000.body', 'oldS');
        self::assertSame($new . ', ' . $old, $h['X-RetroBoards-Signature']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/WebhookSignerTest.php`
Expected: FAIL — `Error: Class "App\Service\Webhook\WebhookSigner" not found`.

- [ ] **Step 3: Implement.** Create `src/Service/Webhook/WebhookSigner.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/**
 * Builds the signed request headers for a webhook delivery. Stateless: signs
 * "{timestamp}.{body}" with each provided per-endpoint secret (HMAC-SHA256, the
 * SignedToken discipline) and emits one `sha256=<hex>` per secret, comma-
 * separated, newest first — so a rotation grace window sends both signatures and
 * the consumer can switch without downtime. The secret is the per-endpoint
 * SecretVault value, NEVER app.key.
 */
final class WebhookSigner
{
    /**
     * @param array<int,string> $secrets newest-first (from SecretVault::usableSecrets)
     * @return array<string,string>
     */
    public static function headers(string $eventType, string $eventId, int $timestamp, string $body, array $secrets): array
    {
        $message = $timestamp . '.' . $body;
        $sigs = [];
        foreach ($secrets as $secret) {
            $sigs[] = 'sha256=' . hash_hmac('sha256', $message, $secret);
        }

        return [
            'Content-Type' => 'application/json',
            'User-Agent' => 'RetroBoards-Webhook/1.0',
            'X-RetroBoards-Event' => $eventType,
            'X-RetroBoards-Delivery' => $eventId,
            'X-RetroBoards-Timestamp' => (string) $timestamp,
            'X-RetroBoards-Signature' => implode(', ', $sigs),
        ];
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/WebhookSignerTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Webhook/WebhookSigner.php tests/Unit/Service/WebhookSignerTest.php
git commit -m "$(printf 'feat(webhooks): WebhookSigner (GitHub-style, rotation-safe)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 7: Transport seam — `WebhookResponse`, `WebhookTransport`, `FakeWebhookTransport`, `CurlWebhookTransport` + unit test

**Files:**
- Create: `src/Service/Webhook/WebhookResponse.php`, `src/Service/Webhook/WebhookTransport.php`, `src/Service/Webhook/FakeWebhookTransport.php`, `src/Service/Webhook/CurlWebhookTransport.php`
- Create: `tests/Unit/Service/WebhookTransportTest.php`

**Interfaces:**
- Consumes: `App\Security\EgressGuard` (Task 5), `App\Core\EgressBlockedException`.
- Produces: `WebhookResponse(public int $status, public ?string $error = null)`; `interface WebhookTransport { public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse; }`; `FakeWebhookTransport` (records `->calls`, optional responder); `CurlWebhookTransport::__construct(EgressGuard $guard, int $maxResponseBytes = 65536)` (validates+pins, throws `EgressBlockedException` before any socket).

- [ ] **Step 1: Write the failing test.** Create `tests/Unit/Service/WebhookTransportTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Core\EgressBlockedException;
use App\Security\EgressGuard;
use App\Service\Webhook\CurlWebhookTransport;
use App\Service\Webhook\FakeWebhookTransport;
use App\Service\Webhook\WebhookResponse;
use PHPUnit\Framework\TestCase;

final class WebhookTransportTest extends TestCase
{
    public function test_fake_records_calls_and_returns_canned_response(): void
    {
        $fake = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(202, null));
        $resp = $fake->deliver('https://x.test/h', ['A' => 'b'], '{}', 5);
        self::assertSame(202, $resp->status);
        self::assertCount(1, $fake->calls);
        self::assertSame('https://x.test/h', $fake->calls[0]['url']);
    }

    public function test_curl_transport_blocks_denied_target_before_any_request(): void
    {
        // Resolver returns a private IP; the guard must throw before cURL runs.
        $guard = new EgressGuard(false, [], static fn (): array => ['10.0.0.5']);
        $transport = new CurlWebhookTransport($guard);
        $this->expectException(EgressBlockedException::class);
        $transport->deliver('https://evil.test/hook', [], '{}', 5);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Service/WebhookTransportTest.php`
Expected: FAIL — `Error: Class "App\Service\Webhook\FakeWebhookTransport" not found`.

- [ ] **Step 3a: `WebhookResponse`.**

```php
<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Result of one delivery attempt. status=0 means no HTTP response (connection error/timeout). */
final class WebhookResponse
{
    public function __construct(public int $status, public ?string $error = null)
    {
    }
}
```

- [ ] **Step 3b: `WebhookTransport` interface.**

```php
<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Replaceable outbound-HTTP seam (like Mailer). CurlWebhookTransport in prod, Fake in tests. */
interface WebhookTransport
{
    /** @param array<string,string> $headers @throws \App\Core\EgressBlockedException */
    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse;
}
```

- [ ] **Step 3c: `FakeWebhookTransport`.**

```php
<?php

declare(strict_types=1);

namespace App\Service\Webhook;

/** Test double: records every deliver() call; returns a canned/responder-built response. */
final class FakeWebhookTransport implements WebhookTransport
{
    /** @var array<int,array{url:string,headers:array<string,string>,body:string}> */
    public array $calls = [];

    /** @var null|callable(string,array<string,string>,string):WebhookResponse */
    private $responder;

    /** @param null|callable(string,array<string,string>,string):WebhookResponse $responder */
    public function __construct(?callable $responder = null)
    {
        $this->responder = $responder;
    }

    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];
        if ($this->responder !== null) {
            return ($this->responder)($url, $headers, $body);
        }
        return new WebhookResponse(200, null);
    }
}
```

- [ ] **Step 3d: `CurlWebhookTransport`.**

```php
<?php

declare(strict_types=1);

namespace App\Service\Webhook;

use App\Security\EgressGuard;

/**
 * SSRF-hardened outbound delivery. Runs EgressGuard first (throws
 * EgressBlockedException), then POSTs with TLS verification, no redirects, a
 * protocol allowlist, connect+total timeout caps, a response-size cap, and
 * CURLOPT_RESOLVE pinned to the guard-validated IP (resolve-then-pin). Never
 * logs the body, headers, or signature.
 */
final class CurlWebhookTransport implements WebhookTransport
{
    public function __construct(private EgressGuard $guard, private int $maxResponseBytes = 65536)
    {
    }

    public function deliver(string $url, array $headers, string $body, int $timeoutSeconds): WebhookResponse
    {
        $ip = $this->guard->validate($url); // throws EgressBlockedException on a denied target

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        $hdrs = [];
        foreach ($headers as $k => $v) {
            $hdrs[] = $k . ': ' . $v;
        }

        $bytes = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $hdrs,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => 0,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip],
            CURLOPT_WRITEFUNCTION => function ($c, string $chunk) use (&$bytes): int {
                $bytes += strlen($chunk);
                return $bytes > $this->maxResponseBytes ? -1 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($status === 0) {
            return new WebhookResponse(0, 'curl error ' . $errno);
        }
        return new WebhookResponse($status, ($status >= 200 && $status < 300) ? null : ('HTTP ' . $status));
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Service/WebhookTransportTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Service/Webhook/WebhookResponse.php src/Service/Webhook/WebhookTransport.php src/Service/Webhook/FakeWebhookTransport.php src/Service/Webhook/CurlWebhookTransport.php tests/Unit/Service/WebhookTransportTest.php
git commit -m "$(printf 'feat(webhooks): SSRF-hardened transport seam (cURL + fake)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 8: `WebhookRepository` + integration test

**Files:**
- Create: `src/Repository/WebhookRepository.php`
- Create: `tests/Integration/Repository/WebhookRepositoryTest.php`

**Interfaces:**
- Produces (all on `WebhookRepository`): `insert(name,url,eventsJson,secretRef,createdBy): int`, `setSecretRef(id,ref): void`, `findById(id): ?array`, `list(): array` (excludes `secret_ref`), `activeEndpoints(): array` (includes `secret_ref`), `update(id,name,url,eventsJson): void`, `enable(id): int`, `disable(id,reason): int`, `delete(id): int`, `incrementConsecutiveFailures(id): void`, `resetConsecutiveFailures(id): void`, `setLastStatus(id,?status,bool deliveredNow): void`. `enable`/`disable` are state-guarded (return rowCount for idempotent audit).
- Consumes: a `users` row id (seed via `makeAdmin`) for `created_by`, and the `webhooks` table (Task 2).

- [ ] **Step 1: Write the failing test.** Create `tests/Integration/Repository/WebhookRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

final class WebhookRepositoryTest extends TestCase
{
    private function repo(): WebhookRepository
    {
        return new WebhookRepository($this->db);
    }

    private function makeHook(): int
    {
        $admin = $this->makeAdmin();
        return $this->repo()->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', 'svcsec_x', (int) $admin['id']);
    }

    public function test_insert_list_excludes_secret_ref_but_active_includes_it(): void
    {
        $id = $this->makeHook();
        $listed = $this->repo()->list();
        self::assertArrayNotHasKey('secret_ref', $listed[0]);
        $active = $this->repo()->activeEndpoints();
        self::assertSame('svcsec_x', $active[0]['secret_ref']);
        self::assertSame($id, (int) $active[0]['id']);
    }

    public function test_disable_then_enable_is_state_guarded(): void
    {
        $id = $this->makeHook();
        self::assertSame(1, $this->repo()->disable($id, 'broke'), 'first disable changes a row');
        self::assertSame(0, $this->repo()->disable($id, 'broke again'), 'already-disabled is a no-op');
        self::assertSame([], $this->repo()->activeEndpoints(), 'disabled endpoint is not active');
        self::assertSame(1, $this->repo()->enable($id), 're-enable changes a row');
        self::assertSame(0, (int) $this->repo()->findById($id)['consecutive_failures'], 'enable resets the breaker');
    }

    public function test_failure_counter_and_last_status(): void
    {
        $id = $this->makeHook();
        $this->repo()->incrementConsecutiveFailures($id);
        $this->repo()->incrementConsecutiveFailures($id);
        self::assertSame(2, (int) $this->repo()->findById($id)['consecutive_failures']);
        $this->repo()->setLastStatus($id, 200, true);
        self::assertSame(200, (int) $this->repo()->findById($id)['last_status']);
        $this->repo()->resetConsecutiveFailures($id);
        self::assertSame(0, (int) $this->repo()->findById($id)['consecutive_failures']);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/WebhookRepositoryTest.php`
Expected: FAIL — `Error: Class "App\Repository\WebhookRepository" not found`.

- [ ] **Step 3: Implement.** Create `src/Repository/WebhookRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Single-table SQL for webhook endpoints (the HMAC secret is a SecretVault ref). */
final class WebhookRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insert(string $name, string $url, string $eventsJson, string $secretRef, int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO webhooks (name, url, events, secret_ref, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$name, $url, $eventsJson, $secretRef, $createdBy],
        );
    }

    public function setSecretRef(int $id, string $ref): void
    {
        $this->db->run('UPDATE webhooks SET secret_ref = ? WHERE id = ?', [$ref, $id]);
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM webhooks WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> admin listing; excludes secret_ref */
    public function list(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, url, events, is_active, consecutive_failures, disabled_at, disabled_reason,
                    last_status, last_delivered_at, created_at, updated_at
             FROM webhooks ORDER BY id DESC',
        );
    }

    /** @return array<int,array<string,mixed>> active endpoints (incl. secret_ref) for dispatch */
    public function activeEndpoints(): array
    {
        return $this->db->fetchAll('SELECT * FROM webhooks WHERE is_active = 1 ORDER BY id ASC');
    }

    public function update(int $id, string $name, string $url, string $eventsJson): void
    {
        $this->db->run(
            'UPDATE webhooks SET name = ?, url = ?, events = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$name, $url, $eventsJson, $id],
        );
    }

    /** @return int rows affected — 0 when already active (idempotent audit) */
    public function enable(int $id): int
    {
        return $this->db->run(
            'UPDATE webhooks SET is_active = 1, disabled_at = NULL, disabled_reason = NULL,
                    consecutive_failures = 0, updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND is_active = 0',
            [$id],
        )->rowCount();
    }

    /** @return int rows affected — 0 when already disabled (idempotent audit) */
    public function disable(int $id, string $reason): int
    {
        return $this->db->run(
            'UPDATE webhooks SET is_active = 0, disabled_at = UTC_TIMESTAMP(), disabled_reason = ?,
                    updated_at = UTC_TIMESTAMP()
             WHERE id = ? AND is_active = 1',
            [substr($reason, 0, 190), $id],
        )->rowCount();
    }

    public function delete(int $id): int
    {
        return $this->db->run('DELETE FROM webhooks WHERE id = ?', [$id])->rowCount();
    }

    public function incrementConsecutiveFailures(int $id): void
    {
        $this->db->run('UPDATE webhooks SET consecutive_failures = consecutive_failures + 1 WHERE id = ?', [$id]);
    }

    public function resetConsecutiveFailures(int $id): void
    {
        $this->db->run('UPDATE webhooks SET consecutive_failures = 0 WHERE id = ?', [$id]);
    }

    public function setLastStatus(int $id, ?int $status, bool $deliveredNow): void
    {
        if ($deliveredNow) {
            $this->db->run('UPDATE webhooks SET last_status = ?, last_delivered_at = UTC_TIMESTAMP() WHERE id = ?', [$status, $id]);
            return;
        }
        $this->db->run('UPDATE webhooks SET last_status = ? WHERE id = ?', [$status, $id]);
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Repository/WebhookRepositoryTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Repository/WebhookRepository.php tests/Integration/Repository/WebhookRepositoryTest.php
git commit -m "$(printf 'feat(webhooks): WebhookRepository (endpoint CRUD + breaker)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 9: `WebhookDeliveryRepository` (durable ledger) + integration test

**Files:**
- Create: `src/Repository/WebhookDeliveryRepository.php`
- Create: `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`

**Interfaces:**
- Produces (all on `WebhookDeliveryRepository`): `enqueue(webhookId,eventType,eventId,payloadJson,maxAttempts): int` (`INSERT IGNORE` on the 3-col unique key; 0 on dup), `claim(limit): array` (joins `webhooks`, gates `is_active=1`, backoff-aware; rows include `url`, `secret_ref`, `consecutive_failures`), `markDelivered(id,httpStatus): void`, `recordFailure(id,?httpStatus,error,?nextAttemptAt,bool dead): void`, `requeue(webhookId,deliveryId): int` (scoped to the owning webhook; only `status='dead'`; clears stale last-attempt metadata), `listForWebhook(webhookId,limit): array`, `find(id): ?array`, `acquireDrainLock(): bool`/`releaseDrainLock(): void` (`GET_LOCK('rb_webhook_outbox',0)`), `statusCounts(): array`.
- Consumes: `WebhookRepository` (to create the parent endpoint in tests), the `webhook_deliveries` table (Task 2).

- [ ] **Step 1: Write the failing test.** Create `tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

final class WebhookDeliveryRepositoryTest extends TestCase
{
    private WebhookRepository $hooks;
    private WebhookDeliveryRepository $deliv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = new WebhookRepository($this->db);
        $this->deliv = new WebhookDeliveryRepository($this->db);
    }

    private function hook(): int
    {
        $admin = $this->makeAdmin();
        return $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', 'svcsec_x', (int) $admin['id']);
    }

    public function test_enqueue_dedupes_same_triple_but_allows_distinct_event_type(): void
    {
        $h = $this->hook();
        self::assertGreaterThan(0, $this->deliv->enqueue($h, 'post.edited', 'src1', '{}', 6));
        self::assertSame(0, $this->deliv->enqueue($h, 'post.edited', 'src1', '{}', 6), 'same triple dedupes');
        self::assertGreaterThan(0, $this->deliv->enqueue($h, 'post.deleted', 'src1', '{}', 6), 'different event type does not collide');
    }

    public function test_claim_only_returns_active_endpoint_rows_and_is_backoff_aware(): void
    {
        $h = $this->hook();
        $id = $this->deliv->enqueue($h, 'ping', 'e1', '{}', 6);
        $row = $this->deliv->claim(10)[0];
        self::assertSame($id, (int) $row['id']);
        self::assertSame('https://x.test/h', $row['url'], 'claim joins endpoint url');
        self::assertSame('svcsec_x', $row['secret_ref']);

        // Disable the endpoint: its queued row is no longer claimable.
        $this->hooks->disable($h, 'paused');
        self::assertSame([], $this->deliv->claim(10), 'paused endpoint rows are not claimed');
        $this->hooks->enable($h);

        // A future next_attempt_at is not yet claimable.
        $this->deliv->recordFailure($id, 500, 'x', gmdate('Y-m-d H:i:s', time() + 3600), false);
        self::assertSame([], $this->deliv->claim(10), 'backoff defers the row');
    }

    public function test_transitions_and_requeue(): void
    {
        $h = $this->hook();

        // mark-delivered after a prior failure clears the stale error text.
        $a = $this->deliv->enqueue($h, 'ping', 'a', '{}', 6);
        $this->deliv->recordFailure($a, 500, 'boom', '2030-01-01 00:00:00', false);
        $this->deliv->markDelivered($a, 200);
        self::assertSame('delivered', $this->deliv->find($a)['status']);
        self::assertNull($this->deliv->find($a)['error'], 'markDelivered clears the prior error');

        $b = $this->deliv->enqueue($h, 'ping', 'b', '{}', 6);
        $this->deliv->recordFailure($b, 500, 'boom', null, true);
        self::assertSame('dead', $this->deliv->find($b)['status']);

        // Replay is scoped to the OWNING webhook: a wrong parent id is a no-op.
        self::assertSame(0, $this->deliv->requeue($h + 1, $b), 'cannot replay a delivery via another webhook id');
        self::assertSame('dead', $this->deliv->find($b)['status'], 'wrong-parent replay left it dead');

        self::assertSame(1, $this->deliv->requeue($h, $b), 'the owning webhook replays its dead row');
        $row = $this->deliv->find($b);
        self::assertSame('queued', $row['status']);
        self::assertSame(0, (int) $row['attempt_count'], 'replay resets attempts');
        self::assertNull($row['response_status'], 'replay clears stale response_status');
        self::assertSame(0, $this->deliv->requeue($h, $a), 'a delivered row cannot be requeued');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`
Expected: FAIL — `Error: Class "App\Repository\WebhookDeliveryRepository" not found`.

- [ ] **Step 3: Implement.** Create `src/Repository/WebhookDeliveryRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * The durable webhook-delivery ledger (PHASE_3_PLAN §8.2 #6). Mirrors the email
 * outbox (INSERT IGNORE enqueue + GET_LOCK single-drainer) and ADDS retry/
 * backoff/dead-letter. claim() joins webhooks to gate on is_active — the one
 * documented two-table read in this repo — so a paused/auto-disabled endpoint's
 * rows are not claimed in subsequent batches. (Within a single batch, the worker
 * itself skips an endpoint once its breaker trips — see WebhookDeliveryWorker.)
 */
final class WebhookDeliveryRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return int new row id, or 0 when the (webhook_id,event_type,event_id) triple already exists */
    public function enqueue(int $webhookId, string $eventType, string $eventId, string $payloadJson, int $maxAttempts): int
    {
        $stmt = $this->db->run(
            'INSERT IGNORE INTO webhook_deliveries
                (webhook_id, event_type, event_id, payload, status, attempt_count, max_attempts, created_at)
             VALUES (:wid, :etype, :eid, :payload, :status, 0, :maxa, UTC_TIMESTAMP())',
            ['wid' => $webhookId, 'etype' => $eventType, 'eid' => $eventId, 'payload' => $payloadJson, 'status' => 'queued', 'maxa' => $maxAttempts],
        );
        return $stmt->rowCount() > 0 ? (int) $this->db->pdo()->lastInsertId() : 0;
    }

    /** @return array<int,array<string,mixed>> claimable rows for ACTIVE endpoints, backoff-aware */
    public function claim(int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT d.*, w.url AS url, w.secret_ref AS secret_ref, w.consecutive_failures AS consecutive_failures
             FROM webhook_deliveries d
             JOIN webhooks w ON w.id = d.webhook_id
             WHERE d.status = 'queued'
               AND w.is_active = 1
               AND (d.next_attempt_at IS NULL OR d.next_attempt_at <= UTC_TIMESTAMP())
             ORDER BY d.next_attempt_at ASC, d.id ASC
             LIMIT " . $limit,
        );
    }

    public function markDelivered(int $id, int $httpStatus): void
    {
        // Clear any prior-attempt error so a row that failed-then-succeeded
        // doesn't show stale failure text in the delivery log.
        $this->db->run(
            "UPDATE webhook_deliveries
             SET status = 'delivered', delivered_at = UTC_TIMESTAMP(), last_attempt_at = UTC_TIMESTAMP(),
                 attempt_count = attempt_count + 1, response_status = ?, error = NULL
             WHERE id = ?",
            [$httpStatus, $id],
        );
    }

    public function recordFailure(int $id, ?int $httpStatus, string $error, ?string $nextAttemptAt, bool $dead): void
    {
        $this->db->run(
            "UPDATE webhook_deliveries
             SET status = ?, attempt_count = attempt_count + 1, last_attempt_at = UTC_TIMESTAMP(),
                 response_status = ?, error = ?, next_attempt_at = ?
             WHERE id = ?",
            [$dead ? 'dead' : 'queued', $httpStatus, substr($error, 0, 255), $nextAttemptAt, $id],
        );
    }

    /**
     * Re-queue a dead delivery — scoped to its OWNING webhook so a crafted
     * /admin/webhooks/A/deliveries/B/replay can't requeue B when it belongs to
     * another webhook. Also wipes stale last-attempt metadata for a clean retry.
     * @return int 1 when a dead row of $webhookId was re-queued (idempotent), else 0
     */
    public function requeue(int $webhookId, int $deliveryId): int
    {
        return $this->db->run(
            "UPDATE webhook_deliveries
             SET status = 'queued', attempt_count = 0, next_attempt_at = NULL,
                 error = NULL, response_status = NULL, last_attempt_at = NULL
             WHERE id = ? AND webhook_id = ? AND status = 'dead'",
            [$deliveryId, $webhookId],
        )->rowCount();
    }

    /** @return array<int,array<string,mixed>> recent deliveries for the admin log */
    public function listForWebhook(int $webhookId, int $limit = 50): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            'SELECT * FROM webhook_deliveries WHERE webhook_id = ? ORDER BY id DESC LIMIT ' . $limit,
            [$webhookId],
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM webhook_deliveries WHERE id = ?', [$id]);
    }

    public function acquireDrainLock(): bool
    {
        return (int) $this->db->fetchValue("SELECT GET_LOCK('rb_webhook_outbox', 0)") === 1;
    }

    public function releaseDrainLock(): void
    {
        $this->db->run("SELECT RELEASE_LOCK('rb_webhook_outbox')");
    }

    /** @return array<string,int> status => count */
    public function statusCounts(): array
    {
        $rows = $this->db->fetchAll('SELECT status, COUNT(*) AS n FROM webhook_deliveries GROUP BY status');
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Repository/WebhookDeliveryRepositoryTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Repository/WebhookDeliveryRepository.php tests/Integration/Repository/WebhookDeliveryRepositoryTest.php
git commit -m "$(printf 'feat(webhooks): durable WebhookDeliveryRepository (claim gates active)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 10: `WebhookService` (+ `WebhooksDisabledException`) + integration test

**Files:**
- Create: `src/Core/WebhooksDisabledException.php`
- Create: `src/Service/WebhookService.php`
- Create: `tests/Integration/Service/WebhookServiceTest.php`

**Interfaces:**
- Consumes: `WebhookRepository`, `WebhookDeliveryRepository`, `SecretVault` (`store/rotate/revoke/usableSecrets`), `ModerationLogRepository`, `FeatureFlags`, `Config`, `PasswordHasher`, `WriteGate`, `WebhookEvents`.
- Produces (all on `WebhookService`): `register(User,string $pw,string $name,string $url,array $events): array{id:int,secret:string}`, `rotateSecret(User,string $pw,int $id): string`, `update(User,int $id,string $name,string $url,array $events): void`, `setActive(User,int $id,bool): void`, `delete(User,int $id): void`, `dispatch(string $eventType,array $payload,?string $eventId=null): int`, `sendTestEvent(User,int $id): int`, `replay(User,int $id,int $deliveryId): void`, `list(): array`, `get(int $id): ?array`, `deliveriesFor(int $id,int $limit=50): array`. Dark `webhooks` → `dispatch` returns 0; `register`/`rotateSecret`/`update`/`sendTestEvent` throw `WebhooksDisabledException`; **`setActive`/`delete`/`replay`/`list`/`get`/`deliveriesFor` remain available (retained control, decision #40 — operators must keep cleanup/pause/inspect when the feature is globally off)**. `register`/`rotateSecret` additionally throw `ValidationException` if `service_secrets` is dark.

- [ ] **Step 1: Write the failing test.** Create `tests/Integration/Service/WebhookServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Core\WebhooksDisabledException;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\PasswordHasher;
use App\Security\SecretBox;
use App\Repository\ServiceSecretRepository;
use App\Security\WriteGate;
use App\Service\SecretVault;
use App\Service\WebhookService;
use Tests\Support\TestCase;

final class WebhookServiceTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function service(array $flags = ['webhooks' => true, 'service_secrets' => true]): WebhookService
    {
        (new SettingRepository($this->db))->set('features', $flags);
        $vault = new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
        return new WebhookService(
            $this->db,
            new WebhookRepository($this->db),
            new WebhookDeliveryRepository($this->db),
            $vault,
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new PasswordHasher(),
            new WriteGate(),
            new EgressGuard(false, []),
        );
    }

    private function admin(): \App\Domain\User
    {
        return $this->userEntity($this->makeAdmin(['password' => 'password123']));
    }

    public function test_register_returns_secret_once_and_stores_only_a_vault_ref(): void
    {
        $res = $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        self::assertNotSame('', $res['secret']);
        $ref = (string) $this->db->fetchValue('SELECT secret_ref FROM webhooks WHERE id = ?', [$res['id']]);
        self::assertStringStartsWith('svcsec_', $ref, 'only a vault ref is persisted, not the plaintext');
        self::assertStringNotContainsString($res['secret'], $ref);
    }

    public function test_register_requires_service_secrets_flag(): void
    {
        $this->expectException(ValidationException::class);
        $this->service(['webhooks' => true, 'service_secrets' => false])
            ->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_register_blocked_when_webhooks_dark(): void
    {
        $this->expectException(WebhooksDisabledException::class);
        $this->service(['webhooks' => false, 'service_secrets' => true])
            ->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_register_rejects_wrong_password_and_bad_input(): void
    {
        $svc = $this->service();
        try {
            $svc->register($this->admin(), 'WRONG', 'ci', 'https://x.test/h', ['ping']);
            self::fail('expected ValidationException');
        } catch (ValidationException) {
            self::assertTrue(true);
        }
        $this->expectException(ValidationException::class);
        $svc->register($this->admin(), 'password123', 'ci', 'not-a-url', ['ping']);
    }

    public function test_suspended_admin_cannot_register(): void
    {
        $admin = $this->userEntity($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->expectException(ForbiddenException::class);
        $this->service()->register($admin, 'password123', 'ci', 'https://x.test/h', ['ping']);
    }

    public function test_dispatch_fans_out_to_subscribed_active_endpoints_and_dedupes(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $a = $svc->register($admin, 'password123', 'subA', 'https://a.test/h', ['topic.created']);
        $svc->register($admin, 'password123', 'subB', 'https://b.test/h', ['reply.created']); // not subscribed

        self::assertSame(1, $svc->dispatch('topic.created', ['x' => 1], 'occ1'), 'only the subscribed endpoint enqueues');
        self::assertSame(0, $svc->dispatch('topic.created', ['x' => 1], 'occ1'), 'same occurrence dedupes');
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ?',
            [$a['id']],
        ));
    }

    public function test_dispatch_is_noop_when_dark(): void
    {
        // Register while the flag is on...
        $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        // ...then flip it dark and assert dispatch enqueues nothing. Build the dark
        // service directly (do NOT call service() again — that would re-enable it).
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $dark = new WebhookService(
            $this->db,
            new WebhookRepository($this->db),
            new WebhookDeliveryRepository($this->db),
            $this->vaultStub(),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new PasswordHasher(),
            new WriteGate(),
            new EgressGuard(false, []),
        );
        self::assertSame(0, $dark->dispatch('ping', ['x' => 1], 'occ'));
    }

    public function test_audit_rows_carry_no_secret(): void
    {
        $res = $this->service()->register($this->admin(), 'password123', 'ci', 'https://x.test/h', ['ping']);
        $row = $this->db->fetch(
            "SELECT after_json FROM moderation_log WHERE action = 'webhook_registered' AND target_id = ?",
            [$res['id']],
        );
        self::assertNotNull($row);
        self::assertStringNotContainsString($res['secret'], (string) $row['after_json']);
        self::assertStringNotContainsString('password123', (string) $row['after_json']);
    }

    public function test_register_rejects_a_literal_private_ip_at_registration(): void
    {
        // EgressGuard::validateStatic rejects an IP-literal private target without DNS.
        $this->expectException(ValidationException::class);
        $this->service()->register($this->admin(), 'password123', 'ci', 'https://10.0.0.1/hook', ['ping']);
    }

    private function vaultStub(): SecretVault
    {
        return new SecretVault(
            $this->db, new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db), new FeatureFlags(new SettingRepository($this->db)), $this->config,
        );
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Service/WebhookServiceTest.php`
Expected: FAIL — `Error: Class "App\Core\WebhooksDisabledException" not found`.

- [ ] **Step 3a: Exception.** Create `src/Core/WebhooksDisabledException.php`:

```php
<?php

declare(strict_types=1);

namespace App\Core;

/** Thrown by webhook write paths when the `webhooks` flag is dark. */
final class WebhooksDisabledException extends \RuntimeException
{
}
```

- [ ] **Step 3b: Service.** Create `src/Service/WebhookService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Core\EgressBlockedException;
use App\Core\WebhooksDisabledException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Security\PasswordHasher;
use App\Security\WebhookEvents;
use App\Security\WriteGate;

/**
 * Register / rotate / manage webhook endpoints and enqueue deliveries. The
 * `webhooks` flag is a deploy-dark gate + write kill switch; create/rotate also
 * require `service_secrets` (SecretVault mints the signing secret). The signing
 * secret is shown once and stored only as a svcsec_* vault ref. dispatch() is
 * the producer seam SP4 will call; today only sendTestEvent + tests exercise it.
 */
final class WebhookService
{
    public function __construct(
        private Database $db,
        private WebhookRepository $webhooks,
        private WebhookDeliveryRepository $deliveries,
        private SecretVault $vault,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
        private PasswordHasher $hasher,
        private WriteGate $writeGate,
        private EgressGuard $egress,
    ) {
    }

    /**
     * @param array<int,mixed> $events
     * @return array{id:int,secret:string}
     */
    public function register(User $admin, string $currentPassword, string $name, string $url, array $events): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $this->assertSecretStoreEnabled();
        if (!$this->hasher->verify($currentPassword, $admin->passwordHash())) {
            throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
        }
        $name = trim($name);
        $url = trim($url);
        $this->assertValidName($name);
        $this->assertValidUrl($url);
        $clean = $this->cleanEvents($events);

        $secret = bin2hex(random_bytes(32));
        $id = $this->db->transaction(function () use ($name, $url, $clean, $secret, $admin): int {
            $id = $this->webhooks->insert($name, $url, json_encode($clean) ?: '[]', '', $admin->id());
            $ref = $this->vault->store('webhook', $id, 'Webhook signing secret: ' . $name, $secret, $admin);
            $this->webhooks->setSecretRef($id, $ref);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_registered',
                'target_type' => 'webhook',
                'target_id' => $id,
                'after' => ['name' => $name, 'url' => $url, 'events' => $clean],
            ]);
            return $id;
        });

        return ['id' => $id, 'secret' => $secret];
    }

    public function rotateSecret(User $admin, string $currentPassword, int $webhookId): string
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $this->assertSecretStoreEnabled('current_password'); // rotate form only renders current_password errors
        if (!$this->hasher->verify($currentPassword, $admin->passwordHash())) {
            throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
        }
        $wh = $this->webhooks->findById($webhookId);
        if ($wh === null) {
            throw new ValidationException(['current_password' => 'Unknown webhook.']);
        }
        $ref = (string) $wh['secret_ref'];
        $newSecret = bin2hex(random_bytes(32));
        $this->db->transaction(function () use ($ref, $newSecret, $admin, $webhookId): void {
            $this->vault->rotate($ref, $newSecret, $admin);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_rotated',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
            ]);
        });
        return $newSecret;
    }

    /** @param array<int,mixed> $events */
    public function update(User $admin, int $webhookId, string $name, string $url, array $events): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $name = trim($name);
        $url = trim($url);
        $this->assertValidName($name);
        $this->assertValidUrl($url);
        $clean = $this->cleanEvents($events);
        $this->db->transaction(function () use ($webhookId, $name, $url, $clean, $admin): void {
            $this->webhooks->update($webhookId, $name, $url, json_encode($clean) ?: '[]');
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_updated',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
                'after' => ['name' => $name, 'url' => $url, 'events' => $clean],
            ]);
        });
    }

    public function setActive(User $admin, int $webhookId, bool $active): void
    {
        // Retained-control op (decision #40): NO assertEnabled() — pausing/resuming
        // an endpoint must stay available even when the webhooks flag is dark.
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($webhookId, $active, $admin): void {
            $changed = $active
                ? $this->webhooks->enable($webhookId)
                : $this->webhooks->disable($webhookId, 'Disabled by admin.');
            if ($changed === 1) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => $active ? 'webhook_enabled' : 'webhook_disabled',
                    'target_type' => 'webhook',
                    'target_id' => $webhookId,
                ]);
            }
        });
    }

    public function delete(User $admin, int $webhookId): void
    {
        // Retained-control op: NO assertEnabled() — cleanup stays available when dark.
        $this->writeGate->assertCanWrite($admin);
        $wh = $this->webhooks->findById($webhookId);
        if ($wh === null) {
            return;
        }
        $ref = (string) $wh['secret_ref'];
        $this->db->transaction(function () use ($webhookId, $ref, $admin): void {
            $this->webhooks->delete($webhookId);
            if ($ref !== '') {
                $this->vault->revoke($ref, $admin); // works even if service_secrets is dark
            }
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'webhook_deleted',
                'target_type' => 'webhook',
                'target_id' => $webhookId,
            ]);
        });
    }

    /**
     * Producer seam (SP4 calls this). No-op when dark. Enqueues one delivery per
     * active endpoint subscribed to $eventType. $eventId is a per-OCCURRENCE id.
     *
     * @param array<string,mixed> $payload
     */
    public function dispatch(string $eventType, array $payload, ?string $eventId = null): int
    {
        if (!$this->flags->enabled('webhooks')) {
            return 0;
        }
        $eventId ??= bin2hex(random_bytes(16));
        $maxAttempts = (int) $this->config->get('webhooks.max_attempts', 6);
        $count = 0;
        foreach ($this->webhooks->activeEndpoints() as $wh) {
            $subscribed = json_decode((string) $wh['events'], true);
            if (!is_array($subscribed) || !in_array($eventType, $subscribed, true)) {
                continue;
            }
            $envelope = $this->envelope($eventType, $eventId, (int) $wh['id'], $payload);
            if ($this->deliveries->enqueue((int) $wh['id'], $eventType, $eventId, $envelope, $maxAttempts) > 0) {
                $count++;
            }
        }
        return $count;
    }

    public function sendTestEvent(User $admin, int $webhookId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->assertEnabled();
        $wh = $this->webhooks->findById($webhookId);
        if ($wh === null) {
            throw new ValidationException(['webhook' => 'Unknown webhook.']);
        }
        $eventId = bin2hex(random_bytes(16));
        $envelope = $this->envelope('ping', $eventId, $webhookId, ['message' => 'This is a test event from RetroBoards.']);
        $maxAttempts = (int) $this->config->get('webhooks.max_attempts', 6);
        $n = $this->deliveries->enqueue($webhookId, 'ping', $eventId, $envelope, $maxAttempts);
        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => 'webhook_test_sent',
            'target_type' => 'webhook',
            'target_id' => $webhookId,
            'after' => ['event_id' => $eventId],
        ]);
        return $n;
    }

    public function replay(User $admin, int $webhookId, int $deliveryId): void
    {
        // Retained-control op: NO assertEnabled() — re-queue stays available when dark.
        $this->writeGate->assertCanWrite($admin);
        $this->db->transaction(function () use ($webhookId, $deliveryId, $admin): void {
            // Scope by BOTH ids so a delivery can only be replayed via its owning webhook.
            if ($this->deliveries->requeue($webhookId, $deliveryId) === 1) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'webhook_delivery_replayed',
                    'target_type' => 'webhook',
                    'target_id' => $webhookId,
                    'after' => ['delivery_id' => $deliveryId],
                ]);
            }
        });
    }

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return $this->webhooks->list();
    }

    /** @return array<string,mixed>|null */
    public function get(int $id): ?array
    {
        return $this->webhooks->findById($id);
    }

    /** @return array<int,array<string,mixed>> */
    public function deliveriesFor(int $webhookId, int $limit = 50): array
    {
        return $this->deliveries->listForWebhook($webhookId, $limit);
    }

    // ---- internals --------------------------------------------------------

    /** @param array<string,mixed> $payload */
    private function envelope(string $eventType, string $eventId, int $webhookId, array $payload): string
    {
        return json_encode([
            'event' => $eventType,
            'id' => $eventId,
            'occurred_at' => gmdate('c'),
            'webhook_id' => $webhookId,
            'data' => $payload,
        ]) ?: '{}';
    }

    private function assertEnabled(): void
    {
        if (!$this->flags->enabled('webhooks')) {
            throw new WebhooksDisabledException('Webhooks are disabled.');
        }
    }

    /** @param string $field the form field to surface the error on (register: name; rotate: current_password) */
    private function assertSecretStoreEnabled(string $field = 'name'): void
    {
        if (!$this->flags->enabled('service_secrets')) {
            throw new ValidationException([$field => 'Enable the service-secret store (service_secrets) first.']);
        }
    }

    private function assertValidName(string $name): void
    {
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Name must be 1–80 characters.']);
        }
    }

    private function assertValidUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new ValidationException(['url' => 'Enter a valid URL.']);
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            throw new ValidationException(['url' => 'URL must be http or https.']);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new ValidationException(['url' => 'URL must not contain credentials.']);
        }
        if (strlen($url) > 512) {
            throw new ValidationException(['url' => 'URL is too long (max 512).']);
        }
        // Reject obviously-bad destinations at registration (no DNS for hostnames;
        // the full resolve-then-pin check still runs at delivery). A literal
        // private/blocked IP becomes a field error here, not a worker-time failure.
        try {
            $this->egress->validateStatic($url);
        } catch (EgressBlockedException) {
            throw new ValidationException(['url' => 'That URL is not an allowed destination.']);
        }
    }

    /**
     * @param array<int,mixed> $events
     * @return array<int,string>
     */
    private function cleanEvents(array $events): array
    {
        $clean = [];
        foreach ($events as $e) {
            if (!is_string($e) || !WebhookEvents::isValid($e)) {
                throw new ValidationException(['events' => 'Unknown event type.']);
            }
            if (in_array($e, $clean, true)) {
                throw new ValidationException(['events' => 'Duplicate event type.']);
            }
            $clean[] = $e;
        }
        if ($clean === []) {
            throw new ValidationException(['events' => 'Select at least one event.']);
        }
        return $clean;
    }
}
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Service/WebhookServiceTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/WebhooksDisabledException.php src/Service/WebhookService.php tests/Integration/Service/WebhookServiceTest.php
git commit -m "$(printf 'feat(webhooks): WebhookService (register/rotate/dispatch/manage)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 11: `WebhookDeliveryWorker` + `worker:webhooks` console case + integration test

**Files:**
- Create: `src/Worker/WebhookDeliveryWorker.php`
- Modify: `bin/console` (add `worker:webhooks` case + imports)
- Create: `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`

**Interfaces:**
- Consumes: `WebhookRepository`, `WebhookDeliveryRepository`, `SecretVault` (`usableSecrets`), `WebhookTransport` (`FakeWebhookTransport` in tests), `WebhookSigner` (static), `FeatureFlags`, `ModerationLogRepository`, `Config`. The retry/backoff/threshold/timeout keys come from the `webhooks` config block (added in Task 5).
- Produces: `WebhookDeliveryWorker::run(int $limit = 100): array{delivered:int,retrying:int,dead:int,skipped:int}`.

- [ ] **Step 1: Write the failing test.** Create `tests/Integration/Worker/WebhookDeliveryWorkerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use App\Service\Webhook\FakeWebhookTransport;
use App\Service\Webhook\WebhookResponse;
use App\Service\Webhook\WebhookTransport;
use App\Worker\WebhookDeliveryWorker;
use Tests\Support\TestCase;

final class WebhookDeliveryWorkerTest extends TestCase
{
    private WebhookRepository $hooks;
    private WebhookDeliveryRepository $deliv;

    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
        $this->hooks = new WebhookRepository($this->db);
        $this->deliv = new WebhookDeliveryRepository($this->db);
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db, new ServiceSecretRepository($this->db),
            new SecretBox('0000000000000000000000000000000000000000000000000000000000000000'),
            new ModerationLogRepository($this->db), new FeatureFlags(new SettingRepository($this->db)), $this->config,
        );
    }

    private function worker(WebhookTransport $transport): WebhookDeliveryWorker
    {
        return new WebhookDeliveryWorker(
            $this->hooks, $this->deliv, $this->vault(), $transport,
            new FeatureFlags(new SettingRepository($this->db)),
            new ModerationLogRepository($this->db), $this->config,
        );
    }

    /** Create an endpoint whose secret_ref is a real vault secret, plus one queued delivery. */
    private function hookWithDelivery(string $eventId = 'e1'): array
    {
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $id = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($id, 'ping', $eventId, '{"event":"ping"}', 6);
        return ['webhook_id' => $id, 'delivery_id' => $did];
    }

    public function test_2xx_marks_delivered_and_signs_with_the_secret(): void
    {
        $ids = $this->hookWithDelivery();
        $fake = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(200, null));
        $stats = $this->worker($fake)->run();
        self::assertSame(1, $stats['delivered']);
        self::assertSame('delivered', $this->deliv->find($ids['delivery_id'])['status']);
        self::assertSame(
            'sha256=' . hash_hmac('sha256', $fake->calls[0]['headers']['X-RetroBoards-Timestamp'] . '.{"event":"ping"}', 'topsecret'),
            $fake->calls[0]['headers']['X-RetroBoards-Signature'],
        );
    }

    public function test_failure_retries_with_backoff_then_dead_letters(): void
    {
        $ids = $this->hookWithDelivery();
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        $stats = $this->worker($fail)->run();
        self::assertSame(1, $stats['retrying']);
        $row = $this->deliv->find($ids['delivery_id']);
        self::assertSame('queued', $row['status']);
        self::assertSame(1, (int) $row['attempt_count']);
        self::assertNotNull($row['next_attempt_at'], 'backoff scheduled');

        // Exhaust: force attempt_count to one below max and clear backoff, then run again.
        $this->db->run('UPDATE webhook_deliveries SET attempt_count = 5, next_attempt_at = NULL WHERE id = ?', [$ids['delivery_id']]);
        $stats2 = $this->worker($fail)->run();
        self::assertSame(1, $stats2['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);
    }

    public function test_egress_blocked_dead_letters_immediately(): void
    {
        $ids = $this->hookWithDelivery();
        $blocked = new FakeWebhookTransport(static function (): WebhookResponse {
            throw new EgressBlockedException('blocked');
        });
        $stats = $this->worker($blocked)->run();
        self::assertSame(1, $stats['dead']);
        self::assertSame('dead', $this->deliv->find($ids['delivery_id'])['status']);
    }

    public function test_circuit_breaker_auto_disables_after_threshold(): void
    {
        $ids = $this->hookWithDelivery();
        // Pre-load the endpoint to one below the threshold; one more failure trips it.
        $this->db->run('UPDATE webhooks SET consecutive_failures = 14 WHERE id = ?', [$ids['webhook_id']]);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(503, 'HTTP 503'));
        $this->worker($fail)->run();
        self::assertSame(0, (int) $this->hooks->findById($ids['webhook_id'])['is_active'], 'auto-paused');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'webhook_auto_disabled' AND target_id = ?",
            [$ids['webhook_id']],
        ));
    }

    public function test_paused_endpoint_rows_are_not_delivered(): void
    {
        $ids = $this->hookWithDelivery();
        $this->hooks->disable($ids['webhook_id'], 'paused');
        $fake = new FakeWebhookTransport();
        $stats = $this->worker($fake)->run();
        self::assertSame(0, $stats['delivered']);
        self::assertCount(0, $fake->calls, 'a paused endpoint receives no delivery attempt');
    }

    public function test_dark_flag_delivers_nothing(): void
    {
        $this->hookWithDelivery();
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $fake = new FakeWebhookTransport();
        $stats = $this->worker($fake)->run();
        self::assertSame(['delivered' => 0, 'retrying' => 0, 'dead' => 0, 'skipped' => 0], $stats);
        self::assertCount(0, $fake->calls);
    }

    public function test_dead_letters_at_the_row_max_attempts_snapshot_not_live_config(): void
    {
        // Enqueue a delivery whose snapshot is max_attempts=1, while live config is 6.
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $hid = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $did = $this->deliv->enqueue($hid, 'ping', 'snap', '{"event":"ping"}', 1);
        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(500, 'HTTP 500'));
        $stats = $this->worker($fail)->run();
        self::assertSame(1, $stats['dead'], 'one failed attempt dead-letters when the row snapshot is 1');
        self::assertSame('dead', $this->deliv->find($did)['status']);
    }

    public function test_breaker_skips_same_endpoints_remaining_rows_in_one_run(): void
    {
        // Two queued deliveries for one endpoint already at the breaker edge.
        $admin = $this->userEntity($this->makeAdmin());
        $ref = $this->vault()->store('webhook', 0, 'sig', 'topsecret', $admin);
        $hid = $this->hooks->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', $ref, $admin->id());
        $this->deliv->enqueue($hid, 'ping', 'r1', '{"event":"ping"}', 6);
        $this->deliv->enqueue($hid, 'ping', 'r2', '{"event":"ping"}', 6);
        $this->db->run('UPDATE webhooks SET consecutive_failures = 14 WHERE id = ?', [$hid]);

        $fail = new FakeWebhookTransport(static fn (): WebhookResponse => new WebhookResponse(503, 'HTTP 503'));
        $stats = $this->worker($fail)->run();

        self::assertCount(1, $fail->calls, 'only the first row is attempted; the breaker trips and the second is skipped');
        self::assertSame(1, $stats['skipped']);
        self::assertSame(0, (int) $this->hooks->findById($hid)['is_active'], 'endpoint auto-paused mid-run');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Worker/WebhookDeliveryWorkerTest.php`
Expected: FAIL — `Error: Class "App\Worker\WebhookDeliveryWorker" not found`.

- [ ] **Step 3a: Implement the worker.** Create `src/Worker/WebhookDeliveryWorker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Config;
use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Repository\ModerationLogRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Service\SecretVault;
use App\Service\Webhook\WebhookSigner;
use App\Service\Webhook\WebhookTransport;
use Throwable;

/**
 * Drains the webhook_deliveries ledger (mirrors NotificationEmailWorker). Single
 * drainer via GET_LOCK('rb_webhook_outbox'). No-op when the `webhooks` flag is
 * dark. claim() already excludes paused endpoints. 2xx -> delivered; non-2xx/
 * timeout/connection -> backoff or dead-letter at max_attempts, with a circuit
 * breaker that auto-disables an endpoint after N consecutive failures. An
 * EgressBlockedException is a terminal config error (immediate dead-letter).
 */
final class WebhookDeliveryWorker
{
    public function __construct(
        private WebhookRepository $webhooks,
        private WebhookDeliveryRepository $deliveries,
        private SecretVault $vault,
        private WebhookTransport $transport,
        private FeatureFlags $flags,
        private ModerationLogRepository $log,
        private Config $config,
    ) {
    }

    /** @return array{delivered:int,retrying:int,dead:int,skipped:int} */
    public function run(int $limit = 100): array
    {
        $stats = ['delivered' => 0, 'retrying' => 0, 'dead' => 0, 'skipped' => 0];

        if (!$this->flags->enabled('webhooks')) {
            return $stats; // paused: leave rows queued
        }
        if (!$this->deliveries->acquireDrainLock()) {
            return $stats;
        }

        /** @var list<int> $backoff */
        $backoff = (array) $this->config->get('webhooks.backoff_seconds', [60, 300, 1500, 7200, 21600]);
        $threshold = (int) $this->config->get('webhooks.circuit_breaker_threshold', 15);
        $timeout = (int) $this->config->get('webhooks.timeout_seconds', 5);

        // Endpoints whose breaker trips DURING this batch: skip their remaining
        // already-claimed rows so one broken endpoint can't hog the whole run.
        $disabledThisRun = [];

        try {
            foreach ($this->deliveries->claim($limit) as $row) {
                $id = (int) $row['id'];
                $webhookId = (int) $row['webhook_id'];

                if (isset($disabledThisRun[$webhookId])) {
                    $stats['skipped']++; // endpoint auto-paused earlier this run; leave queued
                    continue;
                }

                try {
                    $secrets = $this->vault->usableSecrets((string) $row['secret_ref']);
                } catch (Throwable) {
                    // Transient vault problem — leave queued, try again next run.
                    $stats['skipped']++;
                    continue;
                }

                $ts = time();
                $headers = WebhookSigner::headers(
                    (string) $row['event_type'],
                    (string) $row['event_id'],
                    $ts,
                    (string) $row['payload'],
                    $secrets,
                );

                try {
                    $resp = $this->transport->deliver((string) $row['url'], $headers, (string) $row['payload'], $timeout);
                } catch (EgressBlockedException $e) {
                    $this->deliveries->recordFailure($id, null, 'egress blocked: ' . $e->getMessage(), null, true);
                    $stats['dead']++;
                    continue;
                }

                if ($resp->status >= 200 && $resp->status < 300) {
                    $this->deliveries->markDelivered($id, $resp->status);
                    $this->webhooks->setLastStatus($webhookId, $resp->status, true);
                    $this->webhooks->resetConsecutiveFailures($webhookId);
                    $stats['delivered']++;
                    continue;
                }

                $attempt = (int) $row['attempt_count'] + 1;
                $dead = $attempt >= (int) $row['max_attempts']; // per-row snapshot, NOT live config
                $next = null;
                if (!$dead) {
                    $idx = min($attempt - 1, count($backoff) - 1);
                    $secs = (int) ($backoff[$idx] ?? 21600);
                    $next = gmdate('Y-m-d H:i:s', time() + $secs);
                }
                $this->deliveries->recordFailure($id, $resp->status ?: null, $resp->error ?? ('HTTP ' . $resp->status), $next, $dead);
                $this->webhooks->setLastStatus($webhookId, $resp->status ?: null, false);

                $newFailures = (int) $row['consecutive_failures'] + 1;
                $this->webhooks->incrementConsecutiveFailures($webhookId);
                if ($newFailures >= $threshold) {
                    $disabledThisRun[$webhookId] = true; // skip this endpoint's remaining batch rows
                    if ($this->webhooks->disable($webhookId, 'Auto-paused after ' . $newFailures . ' consecutive delivery failures.') === 1) {
                        $this->log->log([
                            'actor_id' => null,
                            'action' => 'webhook_auto_disabled',
                            'target_type' => 'webhook',
                            'target_id' => $webhookId,
                            'after' => ['consecutive_failures' => $newFailures],
                        ]);
                    }
                }

                $stats[$dead ? 'dead' : 'retrying']++;
            }
        } finally {
            $this->deliveries->releaseDrainLock();
        }

        return $stats;
    }
}
```

- [ ] **Step 3b: Wire `bin/console`.** Add to the imports block near the top of `bin/console` (after the existing `use App\Worker\...` lines):

```php
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Service\Webhook\CurlWebhookTransport;
use App\Worker\WebhookDeliveryWorker;
```

Then add a new `case` in the command `switch`, right after the `worker:secret-prune` case (before `case 'help':`):

```php
        case 'worker:webhooks':
            // Drain the durable webhook-delivery ledger (B2 SP3). Single drainer
            // via rb_webhook_outbox; retries/backoff/dead-letter + circuit breaker.
            $db = $database();
            $vault = new SecretVault(
                $db,
                new ServiceSecretRepository($db),
                new SecretBox((string) $config->get('app.key', '')),
                new ModerationLogRepository($db),
                new FeatureFlags(new SettingRepository($db)),
                $config,
            );
            $transport = new CurlWebhookTransport(
                new EgressGuard(
                    (bool) $config->get('webhooks.allow_http', false),
                    (array) $config->get('webhooks.allowed_private_cidrs', []),
                ),
                (int) $config->get('webhooks.max_response_bytes', 65536),
            );
            $worker = new WebhookDeliveryWorker(
                new WebhookRepository($db),
                new WebhookDeliveryRepository($db),
                $vault,
                $transport,
                new FeatureFlags(new SettingRepository($db)),
                new ModerationLogRepository($db),
                $config,
            );
            $stats = $worker->run((int) ($argv[2] ?? 100));
            $log(sprintf('Webhook delivery: delivered=%d retrying=%d dead=%d skipped=%d', $stats['delivered'], $stats['retrying'], $stats['dead'], $stats['skipped']));
            break;
```

(`SecretBox`, `ServiceSecretRepository`, `ModerationLogRepository`, `FeatureFlags`, `SettingRepository`, `SecretVault` are already imported in `bin/console`.)

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Worker/WebhookDeliveryWorkerTest.php`
Then sanity-check the console wires up: `php bin/console worker:webhooks` (with the flag dark it prints `delivered=0 retrying=0 dead=0 skipped=0`).
Expected: PASS; console prints the stats line.

- [ ] **Step 5: Commit**

```bash
git add src/Worker/WebhookDeliveryWorker.php bin/console tests/Integration/Worker/WebhookDeliveryWorkerTest.php
git commit -m "$(printf 'feat(webhooks): delivery worker + worker:webhooks console case\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 12: Admin UI — container bindings, routes, controller, templates, dashboard link + HTTP test

**Files:**
- Modify: `src/Core/App.php` (imports + container bindings + routes)
- Create: `src/Controller/AdminWebhookController.php`
- Create: `templates/admin/webhooks.php`, `templates/admin/webhook_detail.php`
- Modify: `templates/admin/dashboard.php` (flag-gated discovery link)
- Create: `tests/Integration/Admin/AdminWebhookTest.php`

**Interfaces:**
- Consumes: `WebhookService` (Task 10), `WebhookEvents` (Task 4), `EgressGuard` (Task 5, injected into `WebhookService`), `RateLimitService` (existing; throttles `test`), `SecretVault`/`ServiceSecretRepository` (existing; we add their deferred container bindings), `FeatureFlags`, controller base helpers (`requireAdmin(): User`, `view($tpl,$data,$status=200)`, `redirectWithFlash($to,$msg)`).
- Produces: routes `GET/POST /admin/webhooks`, `GET/POST /admin/webhooks/{id}`, `POST /admin/webhooks/{id}/{toggle,rotate,test,delete}`, `POST /admin/webhooks/{id}/deliveries/{deliveryId}/replay`; container binding `WebhookService::class`.

- [ ] **Step 1: Write the failing test.** Create `tests/Integration/Admin/AdminWebhookTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminWebhookTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
    }

    private function register(): \App\Core\Response
    {
        return $this->post('/admin/webhooks', [
            'name' => 'CI hook', 'url' => 'https://example.test/hook',
            'events' => ['ping'], 'current_password' => 'password123',
        ]);
    }

    public function test_register_shows_secret_once_then_hidden(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'whadmin', 'password' => 'password123']));

        $res = $this->register();
        $this->assertStatus(200, $res); // not a redirect — secret rendered directly
        self::assertStringContainsString('will not be shown again', $res->body());

        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);
        self::assertStringNotContainsString('will not be shown again', $this->get('/admin/webhooks/' . $id)->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_register_wrong_reauth_is_422_and_preserves_input(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $res = $this->post('/admin/webhooks', [
            'name' => 'KeepMe', 'url' => 'https://example.test/hook', 'events' => ['ping'], 'current_password' => 'WRONG',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('KeepMe', $res->body(), 'the typed name is preserved');
        self::assertStringContainsString('value="ping" checked', $res->body(), 'the chosen event stays checked');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_routes_404_when_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/webhooks'));
    }

    public function test_suspended_admin_cannot_register(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->assertStatus(403, $this->register());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_toggle_and_send_test_and_delete_round_trip(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->register();
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);

        $this->assertRedirectContains($this->post('/admin/webhooks/' . $id . '/test', []), '/admin/webhooks/' . $id);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ? AND event_type = 'ping'", [$id]));

        $this->post('/admin/webhooks/' . $id . '/toggle', ['active' => '0']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_active FROM webhooks WHERE id = ?', [$id]));

        $this->assertRedirectContains($this->post('/admin/webhooks/' . $id . '/delete', []), '/admin/webhooks');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks WHERE id = ?', [$id]));
    }

    public function test_send_test_event_is_rate_limited(): void
    {
        // Policy webhook_test = [20, 600]. The 21st test-send in the window is 429.
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->register();
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);

        for ($i = 0; $i < 20; $i++) {
            $this->assertContains($this->post('/admin/webhooks/' . $id . '/test', [])->status(), [302, 303], 'within-limit test-send redirects');
        }
        $this->assertStatus(429, $this->post('/admin/webhooks/' . $id . '/test', []));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Admin/AdminWebhookTest.php`
Expected: FAIL — 404 on `/admin/webhooks` (route not registered yet) so `test_register_shows_secret_once_then_hidden` fails its 200 assertion.

- [ ] **Step 3a: Container bindings.** In `src/Core/App.php`, add imports near the other `use App\...` lines:

```php
use App\Controller\AdminWebhookController;
use App\Repository\ServiceSecretRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\EgressGuard;
use App\Service\SecretVault;
use App\Service\Webhook\CurlWebhookTransport;
use App\Service\Webhook\WebhookTransport;
use App\Service\WebhookService;
```

In `buildContainer()`, right after the `ApiTokenService` binding (around line 535), add:

```php
        // B2 sub-project 1 (SecretVault) container binding — deferred to its
        // first consumer, which is webhook delivery (SP3).
        $c->bind(ServiceSecretRepository::class, fn (Container $c) => new ServiceSecretRepository($c->get(Database::class)));
        $c->bind(SecretVault::class, fn (Container $c) => new SecretVault(
            $c->get(Database::class),
            $c->get(ServiceSecretRepository::class),
            $c->get(SecretBox::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
        ));
        // B2 sub-project 3 — webhook delivery.
        $c->bind(WebhookRepository::class, fn (Container $c) => new WebhookRepository($c->get(Database::class)));
        $c->bind(WebhookDeliveryRepository::class, fn (Container $c) => new WebhookDeliveryRepository($c->get(Database::class)));
        $c->bind(WebhookTransport::class, fn () => new CurlWebhookTransport(
            new EgressGuard(
                (bool) $config->get('webhooks.allow_http', false),
                (array) $config->get('webhooks.allowed_private_cidrs', []),
            ),
            (int) $config->get('webhooks.max_response_bytes', 65536),
        ));
        $c->bind(WebhookService::class, fn (Container $c) => new WebhookService(
            $c->get(Database::class),
            $c->get(WebhookRepository::class),
            $c->get(WebhookDeliveryRepository::class),
            $c->get(SecretVault::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
            $c->get(PasswordHasher::class),
            $c->get(WriteGate::class),
            new EgressGuard(
                (bool) $config->get('webhooks.allow_http', false),
                (array) $config->get('webhooks.allowed_private_cidrs', []),
            ),
        ));
```

- [ ] **Step 3b: Routes.** In `buildRouter()`, right after the `/admin/api-tokens` routes (around line 1012), add:

```php
        $r->get('/admin/webhooks', [AdminWebhookController::class, 'index']);
        $r->post('/admin/webhooks', [AdminWebhookController::class, 'create']);
        $r->get('/admin/webhooks/{id}', [AdminWebhookController::class, 'show']);
        $r->post('/admin/webhooks/{id}', [AdminWebhookController::class, 'update']);
        $r->post('/admin/webhooks/{id}/toggle', [AdminWebhookController::class, 'toggle']);
        $r->post('/admin/webhooks/{id}/rotate', [AdminWebhookController::class, 'rotate']);
        $r->post('/admin/webhooks/{id}/test', [AdminWebhookController::class, 'test']);
        $r->post('/admin/webhooks/{id}/delete', [AdminWebhookController::class, 'delete']);
        $r->post('/admin/webhooks/{id}/deliveries/{deliveryId}/replay', [AdminWebhookController::class, 'replay']);
```

(Register `/admin/webhooks/{id}` AFTER the literal `/admin/webhooks` POST/GET — first match wins; `{id}` is `\d+` so it won't shadow the collection routes anyway.)

- [ ] **Step 3c: Controller.** Create `src/Controller/AdminWebhookController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Security\WebhookEvents;
use App\Service\RateLimitService;
use App\Service\WebhookService;

final class AdminWebhookController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('webhooks')) {
            throw new NotFoundException();
        }
    }

    private function service(): WebhookService
    {
        return $this->container->get(WebhookService::class);
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->view('admin/webhooks', [
            'webhooks' => $this->service()->list(),
            'events_catalogue' => WebhookEvents::all(),
            'errors' => [],
            'old' => [],
            'new_secret' => null,
        ]);
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->service();
        try {
            $result = $service->register(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                $request->str('url'),
                (array) $request->post('events', []),
            );
            // One-time plaintext: render DIRECTLY (never via Flash — that would leak
            // the secret into a Set-Cookie header). A later GET has no new_secret.
            return $this->view('admin/webhooks', [
                'webhooks' => $service->list(),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => [],
                'old' => [],
                'new_secret' => $result['secret'],
            ]);
        } catch (ValidationException $e) {
            return $this->view('admin/webhooks', [
                'webhooks' => $service->list(),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => $e->errors,
                'old' => $e->old + ['name' => $request->str('name'), 'url' => $request->str('url'), 'events' => (array) $request->post('events', [])],
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params, ?string $newSecret = null, int $status = 200): Response
    {
        $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $webhook = $this->service()->get($id);
        if ($webhook === null) {
            throw new NotFoundException();
        }
        return $this->view('admin/webhook_detail', [
            'webhook' => $webhook,
            'deliveries' => $this->service()->deliveriesFor($id),
            'events_catalogue' => WebhookEvents::all(),
            'errors' => [],
            'old' => [],
            'new_secret' => $newSecret,
        ], $status);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service()->update($admin, $id, $request->str('name'), $request->str('url'), (array) $request->post('events', []));
            return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Webhook updated.');
        } catch (ValidationException $e) {
            $webhook = $this->service()->get($id);
            if ($webhook === null) {
                throw new NotFoundException();
            }
            return $this->view('admin/webhook_detail', [
                'webhook' => $webhook,
                'deliveries' => $this->service()->deliveriesFor($id),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => $e->errors,
                'old' => $e->old + ['name' => $request->str('name'), 'url' => $request->str('url'), 'events' => (array) $request->post('events', [])],
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function toggle(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $this->service()->setActive($admin, $id, (string) $request->post('active', '0') === '1');
        return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Webhook updated.');
    }

    /** @param array<string,string> $params */
    public function rotate(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $secret = $this->service()->rotateSecret($admin, (string) $request->post('current_password', ''), $id);
            return $this->show($request, $params, $secret);
        } catch (ValidationException $e) {
            $webhook = $this->service()->get($id);
            if ($webhook === null) {
                throw new NotFoundException();
            }
            return $this->view('admin/webhook_detail', [
                'webhook' => $webhook,
                'deliveries' => $this->service()->deliveriesFor($id),
                'events_catalogue' => WebhookEvents::all(),
                'errors' => $e->errors,
                'old' => [],
                'new_secret' => null,
            ], 422);
        }
    }

    /** @param array<string,string> $params */
    public function test(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        // Throttle the test-send so the button can't be used to fan out junk (429 on abuse).
        $this->container->get(RateLimitService::class)->enforce('webhook_test', $request, $admin);
        $id = (int) ($params['id'] ?? 0);
        $this->service()->sendTestEvent($admin, $id);
        return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Test event queued. Run the webhook worker to deliver it.');
    }

    /** @param array<string,string> $params */
    public function delete(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->service()->delete($admin, (int) ($params['id'] ?? 0));
        return $this->redirectWithFlash('/admin/webhooks', 'Webhook deleted.');
    }

    /** @param array<string,string> $params */
    public function replay(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $this->service()->replay($admin, $id, (int) ($params['deliveryId'] ?? 0));
        return $this->redirectWithFlash('/admin/webhooks/' . $id, 'Delivery re-queued.');
    }
}
```

> NOTE: `show()` takes optional `$newSecret`/`$status` so `rotate()` can re-render the detail page with the one-time secret. The router calls `show($request, $params)` for the GET route (defaults apply).

- [ ] **Step 3d: Templates.** Create `templates/admin/webhooks.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhooks');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Webhooks</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/webhooks">Webhooks</a>
    </nav>

    <?php if (!empty($new_secret)): ?>
        <div class="flash" role="status">
            <strong>Copy this signing secret now — it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <p class="muted">Only the <code>ping</code> (test) event fires in this release. Domain events activate when event sources land (B2 sub-project 4).</p>

    <section class="card">
        <h2>Register an endpoint</h2>
        <form method="post" action="/admin/webhooks" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>

            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? '') ?>" required>
            </label>
            <?php if (!empty($errors['url'])): ?><p class="field-error"><?= $e($errors['url']) ?></p><?php endif; ?>

            <fieldset>
                <legend>Events</legend>
                <?php $selectedEvents = (array) ($old['events'] ?? []); ?>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selectedEvents, true) ? 'checked' : '' ?>> <?= $e($event) ?> — <?= $e($desc) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['events'])): ?><p class="field-error"><?= $e($errors['events']) ?></p><?php endif; ?>

            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>

            <div class="form-actions"><button class="btn" type="submit">Register endpoint</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Endpoints</h2>
        <table class="audit">
            <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Last status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($webhooks as $w): ?>
                <tr>
                    <td><?= $e($w['name']) ?></td>
                    <td><?= $e($w['url']) ?></td>
                    <td><?= $w['is_active'] ? 'active' : 'paused' ?></td>
                    <td><?= $e((string) ($w['last_status'] ?? '—')) ?></td>
                    <td><a href="/admin/webhooks/<?= (int) $w['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
```

Create `templates/admin/webhook_detail.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Webhook: ' . $webhook['name']);
$id = (int) $webhook['id'];
$selected = isset($old['events']) ? (array) $old['events'] : (json_decode((string) $webhook['events'], true) ?: []);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Webhook: <?= $e($webhook['name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/webhooks">Webhooks</a>
    </nav>

    <?php if (!empty($new_secret)): ?>
        <div class="flash" role="status">
            <strong>Copy this signing secret now — it will not be shown again:</strong>
            <code><?= $e($new_secret) ?></code>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Configuration</h2>
        <form method="post" action="/admin/webhooks/<?= $id ?>" class="stacked">
            <?= $this->csrfField() ?>
            <label>Name
                <input type="text" name="name" maxlength="80" value="<?= $e($old['name'] ?? $webhook['name']) ?>" required>
            </label>
            <?php if (!empty($errors['name'])): ?><p class="field-error"><?= $e($errors['name']) ?></p><?php endif; ?>
            <label>URL
                <input type="url" name="url" maxlength="512" value="<?= $e($old['url'] ?? $webhook['url']) ?>" required>
            </label>
            <?php if (!empty($errors['url'])): ?><p class="field-error"><?= $e($errors['url']) ?></p><?php endif; ?>
            <fieldset>
                <legend>Events</legend>
                <?php foreach ($events_catalogue as $event => $desc): ?>
                    <label><input type="checkbox" name="events[]" value="<?= $e($event) ?>" <?= in_array($event, $selected, true) ? 'checked' : '' ?>> <?= $e($event) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <?php if (!empty($errors['events'])): ?><p class="field-error"><?= $e($errors['events']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Save</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Actions</h2>
        <div class="form-actions">
            <form method="post" action="/admin/webhooks/<?= $id ?>/toggle" class="inline">
                <?= $this->csrfField() ?>
                <input type="hidden" name="active" value="<?= $webhook['is_active'] ? '0' : '1' ?>">
                <button class="btn" type="submit"><?= $webhook['is_active'] ? 'Pause' : 'Resume' ?></button>
            </form>
            <form method="post" action="/admin/webhooks/<?= $id ?>/test" class="inline">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Send test event</button>
            </form>
            <form method="post" action="/admin/webhooks/<?= $id ?>/delete" class="inline">
                <?= $this->csrfField() ?>
                <button class="linkbtn danger" type="submit">Delete</button>
            </form>
        </div>
        <h3>Rotate signing secret</h3>
        <form method="post" action="/admin/webhooks/<?= $id ?>/rotate" class="stacked">
            <?= $this->csrfField() ?>
            <label>Confirm your password
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <?php if (!empty($errors['current_password'])): ?><p class="field-error"><?= $e($errors['current_password']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Rotate secret</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Recent deliveries</h2>
        <table class="audit">
            <thead><tr><th>Event</th><th>Status</th><th>Attempts</th><th>Last response</th><th>Error</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= $e($d['event_type']) ?></td>
                    <td><?= $e($d['status']) ?></td>
                    <td><?= (int) $d['attempt_count'] ?>/<?= (int) $d['max_attempts'] ?></td>
                    <td><?= $e((string) ($d['response_status'] ?? '—')) ?></td>
                    <td><?= $e((string) ($d['error'] ?? '')) ?></td>
                    <td>
                        <?php if ($d['status'] === 'dead'): ?>
                        <form method="post" action="/admin/webhooks/<?= $id ?>/deliveries/<?= (int) $d['id'] ?>/replay" class="inline">
                            <?= $this->csrfField() ?>
                            <button class="linkbtn" type="submit">Replay</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
```

- [ ] **Step 3e: Dashboard link.** In `templates/admin/dashboard.php`, next to the `api_tokens` link (line ~12), add:

```php
<?php if (!empty($features['webhooks'])): ?><a href="/admin/webhooks">Webhooks</a><?php endif; ?>
```

- [ ] **Step 4: Run it to verify it passes**

Run: `vendor/bin/phpunit tests/Integration/Admin/AdminWebhookTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Core/App.php src/Controller/AdminWebhookController.php templates/admin/webhooks.php templates/admin/webhook_detail.php templates/admin/dashboard.php tests/Integration/Admin/AdminWebhookTest.php
git commit -m "$(printf 'feat(webhooks): admin UI + container/route wiring (+SecretVault binding)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 13: Playwright evidence (desktop + mobile)

**Files:**
- Modify: `tests/browser/seed.php` (enable both flags)
- Modify: `tests/browser/playwright.config.ts` (pass webhook env to the web server + worker)
- Modify: `tests/browser/gate-a.spec.ts` (register → show-once → send test → run worker → delivery log)

**Interfaces:**
- Consumes: the full stack (Tasks 1–12). Produces: `docs/evidence/browser/{desktop,mobile}/22-admin-webhook-registered.png` and `23-admin-webhook-delivery-log.png`.

- [ ] **Step 1: Seed both flags.** In `tests/browser/seed.php`, change the features line to:

```php
    $settings->set('features', ['api_tokens' => true, 'webhooks' => true, 'service_secrets' => true]); // B2 admin pages for evidence
```

- [ ] **Step 2: Pass the egress env to the harness.** In `tests/browser/playwright.config.ts`, prepend the webhook env to the `webServer.command` (so the app + any shelled worker allow the loopback receiver):

```ts
    command: `DB_DATABASE=${database} SESSION_SECURE=false MAIL_DRIVER=array APP_URL=${baseURL} WEBHOOK_ALLOW_HTTP=true WEBHOOK_ALLOWED_PRIVATE_CIDRS=127.0.0.1/32 php -S 127.0.0.1:${PORT} -t public public/index.php`,
```

- [ ] **Step 3: Write the spec.** Reuse the file's EXISTING helpers — `shot(page, info, name)` (writes to the absolute `EVIDENCE_DIR = path.resolve(__dirname,'..','..','docs/evidence/browser')`), `login(page, email)`, and `visit(page, url)` — so screenshots land in the repo-root evidence dir (NOT `tests/browser/docs/...`). `test`, `expect`, and `path` are already imported at the top of the file; only ADD these two imports:

```ts
import { execSync } from 'node:child_process';
import http from 'node:http';
```

Then add the test at top level (after the api-token test):

```ts
test('admin webhooks: register shows the secret once, test event delivers', async ({ page }, info) => {
  // A tiny loopback receiver that records one POST. Port is random; 127.0.0.1/32
  // is allowlisted (relaxed tier => http + any port).
  let received = false;
  const server = http.createServer((req, res) => { received = true; res.statusCode = 200; res.end('ok'); });
  await new Promise<void>((r) => server.listen(0, '127.0.0.1', () => r()));
  const hookUrl = `http://127.0.0.1:${(server.address() as import('node:net').AddressInfo).port}/hook`;

  try {
    await login(page, 'admin@retro.test'); // existing helper (seeded admin / password123)
    await visit(page, '/admin');
    await page.getByRole('link', { name: 'Webhooks' }).click();

    await page.fill('input[name="name"]', `Evidence webhook (${info.project.name})`);
    await page.fill('input[name="url"]', hookUrl);
    await page.check('input[name="events[]"][value="ping"]');
    await page.fill('input[name="current_password"]', 'password123');
    await page.getByRole('button', { name: 'Register endpoint' }).click();

    await expect(page.getByText(/will not be shown again/)).toBeVisible();
    await shot(page, info, '22-admin-webhook-registered'); // existing helper -> EVIDENCE_DIR

    await page.getByRole('link', { name: 'Manage' }).first().click();
    await page.getByRole('button', { name: 'Send test event' }).click();

    // Drain the queue out-of-band against the same e2e DB, with egress allowed.
    const repoRoot = path.resolve(__dirname, '..', '..');
    execSync('php bin/console worker:webhooks', {
      cwd: repoRoot,
      env: { ...process.env, DB_DATABASE: process.env.DB_DATABASE ?? 'retroboards_e2e', WEBHOOK_ALLOW_HTTP: 'true', WEBHOOK_ALLOWED_PRIVATE_CIDRS: '127.0.0.1/32', MAIL_DRIVER: 'array' },
    });
    expect(received).toBe(true);

    await page.reload();
    await expect(page.getByText('delivered')).toBeVisible();
    await shot(page, info, '23-admin-webhook-delivery-log');
  } finally {
    await new Promise<void>((r) => server.close(() => r()));
  }
});
```

> FALLBACK (if the loopback receiver/worker shell-out proves flaky in the harness): drop the `execSync`/`received`/reload block and `shot(page, info, '23-admin-webhook-delivery-log')` on the `queued` test delivery row instead. The register + show-once screenshot is the primary evidence either way. Note any reduction with a one-line comment in the spec (no silent caps).

- [ ] **Step 4: Run it to verify it passes**

Run: `cd tests/browser && npm install && npx playwright install --with-deps chromium && npm run evidence`
Expected: the run completes; four new PNGs exist:
`ls docs/evidence/browser/desktop/2{2,3}-*.png docs/evidence/browser/mobile/2{2,3}-*.png`

- [ ] **Step 5: Commit**

```bash
git add tests/browser/seed.php tests/browser/playwright.config.ts tests/browser/gate-a.spec.ts docs/evidence/browser/desktop/2*-admin-webhook-*.png docs/evidence/browser/mobile/2*-admin-webhook-*.png
git commit -m "$(printf 'test(webhooks): Playwright evidence (register/show-once/deliver)\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

### Task 14: Status update + full-suite verification

**Files:**
- Modify: `PHASE_5_STATUS.md` (mark SP3 landed; B2 now 3/4; note producer wiring deferred to SP4)
- Verify: whole suite green.

**Interfaces:** none (closeout).

- [ ] **Step 1: Update PHASE_5_STATUS.md.** Mark B2 sub-project 3 (webhook delivery) landed deploy-dark (engine + admin UI + test-event), B2 now **3/4**, and record that real domain-event producer wiring is deferred to SP4 (the first-party hook registry). Add the new console command to any worker/cron list and point the definition-of-done items at their tests + the `22/23` browser artifacts.

- [ ] **Step 2: Run the full unit + integration suite**

Run: `composer test`
Expected: PASS (no failures, no risky/warnings). If a pre-existing unrelated failure surfaces, confirm it predates this branch (`git stash` + rerun on the base) before proceeding.

- [ ] **Step 3: Verify the flag is dark end-to-end (no behavior leaked on)**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark` and `vendor/bin/phpunit tests/Integration/Admin/AdminWebhookTest.php`
Expected: PASS — routes 404 when dark; flag declared and false by default.

- [ ] **Step 4: Rehearse the additive upgrade on populated data**

Run: `php bin/console verify:upgrade --force` (against a throwaway DB with `APP_ENV!=production`)
Expected: `PASS ✓` — `0057` applies cleanly on a seeded upgrade.

- [ ] **Step 5: Commit**

```bash
git add PHASE_5_STATUS.md
git commit -m "$(printf 'docs(webhooks): mark B2 SP3 landed (3/4); webhook delivery engine\n\nCo-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>')"
```

---

## Notes for the executor

- **Test DB must be reachable** before any integration/`composer test` run (`docker start forum-software-db-1`; port 3307, retro/retro). `tests/bootstrap.php` drops + re-migrates `DB_TEST_DATABASE` each run.
- **Task order matters:** Task 2 (migration) must land before Tasks 8–12 (they need the tables); Tasks 3→5 (Cidr→EgressGuard) and the transport (7) precede the worker (11); the service (10) precedes the admin UI (12).
- **Run the named test after each task**, then `composer test` at the end (Task 14). Never claim green without running it.
- **Secrets discipline:** never add the signing secret, revealed plaintext, or signature/auth headers to a log, audit row, exception message, or the delivery `error` column.
