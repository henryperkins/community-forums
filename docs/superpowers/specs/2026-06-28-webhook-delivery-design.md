# Webhook Delivery — B2 Sub-project 3 (Design)

- **Status:** Approved design, pre-implementation
- **Date:** 2026-06-28
- **Program:** Phase 5 Gate A · ADR 0004 Part B (B2 "trusted hook/webhook/API-token/service-secret foundation")
- **Position:** Sub-project 3 of 4. SP1 (service-secret registry, `0055`) and SP2 (read-only API tokens, `0056`) have landed deploy-dark. SP4 (first-party hook registry) follows.
- **Branch:** `b2-webhook-delivery` (off the SP1+SP2 HEAD; `main` does not yet contain `0055`/`0056`).
- **Precedence:** `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > surface specs. Where this spec and an authoritative doc disagree, the authoritative doc wins.

---

## 0. Summary

Build the webhook **delivery engine + operator admin UI**, deploy-dark behind a new `webhooks` feature flag (default OFF, doubling as a kill switch). The engine: operator-registered HTTPS endpoints, HMAC-SHA256-signed payloads, a durable per-attempt delivery ledger with retry/backoff/dead-letter, idempotent event identity, SSRF/egress control, a 5-second delivery timeout (ADR 0004 D11), and a `GET_LOCK`-drained cron worker.

The producer seam (`WebhookService::dispatch()`) is **built and tested** in this slice, but the wiring that fires real domain events (`topic.created`, `reply.created`, `moderation.*`) from `PostingService`/`ModerationService`/etc. is **deferred to SP4 (the first-party hook registry)**. This keeps SP3 self-contained and out of the hot write paths. End-to-end proof comes from an admin **"send test event"** action that dispatches a `ping` through the full pipeline.

No HTTP-kernel changes: admin routes are ordinary CSRF-protected, admin-gated HTML routes. We are the *sender*, not a receiver, so no CSRF exemption is needed.

### Decisions locked in this spec

1. **Engine-only scope.** Real domain-event producer wiring is SP4's job; SP3 ships the engine, the `dispatch()` seam, and a `ping` test path. (Approved.)
2. **Egress posture: deny-private-by-default + opt-in allowlist.** HTTPS-only, block loopback/private/link-local/metadata ranges, resolve-then-pin; operators may opt specific hosts/CIDRs back in via env. (Approved.)
3. **Retry policy: moderate.** ~6 attempts, exponential backoff `1m → 5m → 25m → 2h → 6h`, then `dead`; plus a circuit-breaker that auto-pauses an endpoint after sustained consecutive failures. (Approved.)
4. **Signature: GitHub-style two-header + timestamp**, with multiple comma-separated signatures during a secret-rotation grace window (zero-downtime rotation). (Approved.)
5. **Secret storage: SecretVault reference, not plaintext.** The legacy documented `webhooks.secret VARCHAR(128)` plaintext column is replaced by a `secret_ref` (`svcsec_*`). SP3 is SecretVault's first consumer and adds its deferred container binding.
6. **No `delivering` ledger state** (crash-safety over fine-grained observability — see §3).

---

## 1. Surrounding seams (verified against the code, 2026-06-28)

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

## 2. Components

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

## 3. Schema (`0057_phase5_webhooks.php`)

Additive, anonymous-class `up(\PDO)/down(\PDO)`, raw `$pdo->exec(<<<'SQL' … SQL)`, `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

### `webhooks` (endpoint config — reconciled from the legacy doc shape)

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

### `webhook_deliveries` (durable per-attempt ledger — new; closes the PHASE_3_PLAN §8.2 gap)

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

### Audit enum extension (mirrors `0055`/`0056`)

```sql
ALTER TABLE moderation_log
  MODIFY target_type ENUM('thread','post','user','board','category','setting',
                          'service_secret','api_token','webhook') NOT NULL;
```

`down()` drops both tables (FK-aware order), reverts the enum, and deletes `moderation_log` rows with `target_type='webhook'`.

**Why no `delivering` state.** With a single-drainer `GET_LOCK` worker processing rows synchronously, an intermediate `delivering` state would be left stranded if the worker crashed mid-POST (no reaper exists in this codebase). Instead a row stays `queued` (with `next_attempt_at`) between attempts — a crash simply leaves it claimable on the next run, exactly as the email pattern behaves. "Retrying" is derivable as `status='queued' AND attempt_count>0`.

---

## 4. Event catalogue (`WebhookEvents`)

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

## 5. `WebhookService` (public surface)

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
- `list(): array` / `get(int $id): ?array` / `deliveriesFor(int $webhookId, int $limit): array` / `replay(User $admin, int $webhookId, int $deliveryId): void` (re-queue a `dead`/exhausted delivery: `status='queued'`, `attempt_count=0`, `next_attempt_at=now`; audit `webhook_delivery_replayed`).

**Two distinct off-switches — don't conflate them:**

1. **The `webhooks` flag = the deploy-dark gate.** When dark: the admin UI 404s (`gate()`, §9), `dispatch()` no-ops, the worker is idle, and `register`/`rotateSecret` throw `WebhooksDisabledException`. This is the api-token precedent: "feature not released / globally off → nothing happens and nothing is reachable." Re-enabling resumes the worker drain of any already-queued rows. (For defense-in-depth the read/admin service methods — `list`/`get`/`deliveriesFor`/`setActive`/`delete`/`replay` — do **not** throw when dark, so the console/worker/tests still function, but they are not reachable through the 404'd UI.)
2. **Per-endpoint `is_active` toggle + circuit breaker = the operational pause (decision #40's "independent disable path").** While the flag is ON, an operator pauses a single misbehaving endpoint (or the breaker auto-pauses it) *without* losing the subsystem or its delivery log. This is the "pause without losing control" path — at endpoint granularity — not the global flag.

---

## 6. Signing (`WebhookSigner`) — GitHub-style, rotation-safe

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

## 7. Egress / SSRF (`EgressGuard` + `CurlWebhookTransport`)

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

## 8. `WebhookDeliveryWorker` + `worker:webhooks`

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

## 9. Admin UI

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

## 10. Error handling

- `WebhooksDisabledException` / `EgressBlockedException` — new `final extends RuntimeException` under `src/Core/`. The kernel does not catch `RuntimeException`; `WebhooksDisabledException` only surfaces from admin actions that already gate on the flag (so it is effectively unreachable from the UI but guards direct service use + tests), and `EgressBlockedException` is caught inside the worker/service.
- `ValidationException` (bad URL, unknown event, name length, failed reauth) — caught by the controller → `422` re-render with typed fields preserved (anti-draft-loss).
- Audit actions (`target_type='webhook'`): `webhook_registered`, `webhook_updated`, `webhook_rotated`, `webhook_enabled`, `webhook_disabled`, `webhook_auto_disabled`, `webhook_deleted`, `webhook_delivery_replayed`. Before/after JSON carries only non-secret metadata (id, name, url, events, status, attempt counts) — never the secret, the `reveal()`ed plaintext, or full headers.

---

## 11. Container & config wiring

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

## 12. Testing & evidence (target R2 + R3, plus Playwright for the UI)

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

## 13. Documentation & status updates

- **SCHEMA.md:** replace the legacy `webhooks` DDL (plaintext `secret`) with the reconciled shape (`secret_ref`); add the `webhook_deliveries` DDL; remove "durable webhook-delivery ledger" from the §8.2 schema-gap list; add a §9 changelog entry + version bump.
- **PHASE_5_STATUS.md:** mark SP3 landed; B2 now 3/4; note the deferral of producer wiring to SP4.
- **ADMIN.md:** note the secret is a vault reference (not the plaintext column the old §10 DDL shows); the §8.6 behaviour description is otherwise accurate.
- **This spec** records the design decisions (engine-only / defer producers to SP4 / deny-private-default + opt-in allowlist / dual-signature rotation / no `delivering` state) so they are not implied work (ADR 0004 carryover discipline).

---

## 14. Out of scope (explicit)

- Real domain-event producer wiring (`PostingService`/`ModerationService`/etc. calling `dispatch()`) — **SP4 (first-party hook registry)**.
- Inbound webhook *receivers* (the email ESP bounce/complaint webhooks in ADMIN §7 are a separate, unrelated concept).
- Per-destination-host rate limiting beyond the worker batch limit + circuit breaker.
- APP_KEY / envelope-key rotation (a SecretVault operational runbook concern, already out of scope there).
- Reaching R4/R5 (acceptance): browser/no-JS/security/perf/operating sign-off + product-owner acceptance — this is a deploy-dark R2+R3 landing.
- Untrusted/sandboxed PHP execution (Gate B, flag `server_extensions`).
