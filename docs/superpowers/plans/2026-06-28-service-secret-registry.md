# Encrypted Service-Secret Registry — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a reversible-secret vault seam (`SecretVault`) over the existing `SecretBox` that hands out opaque references, with versioned rotation + grace window, revoke, prune, redaction, non-lossy audit, and a deploy-dark kill switch — the foundation B2 sub-project that webhooks and the provider registry will consume.

**Architecture:** Two additive tables (`service_secrets` reference + `service_secret_versions` encrypted material) behind a new dark `service_secrets` flag. A thin `ServiceSecretRepository` (single-table SQL) plus a `SecretVault` service that takes `Database` and owns its transactions (à la `ThreadWorkflowService`), spanning the secret repo + `ModerationLogRepository` in one txn. Rotate/revoke serialize on the parent row via `SELECT … FOR UPDATE`; `prune` is a worker drain behind a `GET_LOCK` advisory lock with an idempotent, row-count-gated destroy.

**Tech Stack:** Vanilla PHP 8.2, MySQL/MariaDB via PDO (native prepares), PHPUnit. No framework. Namespace `App\` → `src/`, `Tests\` → `tests/`.

**Design spec:** `docs/superpowers/specs/2026-06-28-service-secret-registry-design.md` (commits `6eb70d9`, `63aeae8`, `ae0a233`).

## Global Constraints

- **PHP 8.2+, vanilla, no framework.** Repositories are `final`, constructor `(private Database $db)`, prepared statements only, return associative arrays.
- **Services own transactions.** Every multi-table mutation runs inside `$this->db->transaction(fn)`; the audit row is written **inside the same transaction** as its mutation (D11: "no high-impact audit write may be skipped silently").
- **PDO `EMULATE_PREPARES=false`:** never bind `LIMIT`/`OFFSET` or an `INTERVAL` quantity — cast to int and concatenate after clamping. Never reuse a named placeholder.
- **UTC everywhere:** `UTC_TIMESTAMP()` in SQL, `gmdate()` in PHP.
- **Migrations additive-only / forward-only.** Next number is `0055`. Anonymous class returning `up(\PDO)/down(\PDO)`, DDL in a `<<<'SQL'` nowdoc. After it lands, hand-update `SCHEMA.md` (shape + §9 changelog + version bump).
- **Deploy-dark:** new subsystem flag defaults `false`; a regression test asserts it's dark.
- **Redaction:** plaintext/ciphertext never appears in logs, audit JSON, exports, exception messages, or `metadata()`.
- **PHPUnit is strict** (`failOnWarning`, `failOnRisky`, `beStrictAboutOutputDuringTests`): every test needs ≥1 assertion, no stray output, no warnings. Integration tests extend `Tests\Support\TestCase`; per-test isolation is one outer transaction rolled back in tearDown, and `Database::transaction` is reentrant — **assert observable behavior, not row counts** of code that "rolls back" internally.
- **Test DB must be reachable:** local container `forum-software-db-1` (mysql:8.4, host port 3307, `retro`/`retro`); a gitignored `.env` points at it. `tests/bootstrap.php` runs `migrate:fresh` on `retroboards_test` every run, so a new migration is applied automatically by any `phpunit` invocation.
- **Commit messages** end with: `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- **Staging:** the working tree carries unrelated dirty multi-agent files. Stage only the files each task names — never `git add -A`.

---

### Task 1: Deploy-dark feature flag `service_secrets`

**Files:**
- Modify: `src/Core/FeatureFlags.php` (DEFAULTS map, after line 67)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (extend `test_phase5_foundation_flags_default_dark`)

**Interfaces:**
- Consumes: nothing.
- Produces: flag key `service_secrets` (bool, default `false`), readable via `FeatureFlags::enabled('service_secrets')`.

- [ ] **Step 1: Write the failing assertion.** In `tests/Integration/Core/AppFeatureFlagTest.php`, inside `test_phase5_foundation_flags_default_dark`, add `'service_secrets'` to the asserted-dark list. Change the `$phase5` array's Gate A line to:

```php
            // Gate A
            'package_registry', 'package_themes', 'capabilities', 'passkeys',
            'provider_registry', 'invitations', 'service_secrets',
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark tests/Integration/Core/AppFeatureFlagTest.php`
Expected: FAIL — `service_secrets should deploy dark by default` (the key is unknown so `enabled()` returns false, which actually passes… so to force a real RED, also assert the key is *present* in `all()`):

Add immediately after the `foreach` loop in that test:

```php
        self::assertArrayHasKey('service_secrets', $flags->all(), 'service_secrets must be a declared flag, not an unknown-key false');
```

Re-run. Expected: FAIL — `assertArrayHasKey` fails because the key isn't in DEFAULTS yet.

- [ ] **Step 3: Add the flag to DEFAULTS.** In `src/Core/FeatureFlags.php`, after the `'invitations' => false,` line (67), add a B2-foundation block:

```php

        // ── Phase 5 Gate A — B2 trusted-extension foundation (deploy-dark) ─
        // Encrypted service-secret registry (SecretVault). Doubles as a write
        // kill switch: dark blocks store/rotate; reveal/revoke/prune still work.
        'service_secrets' => false,   // reversible secret vault for providers/webhooks (B2 sub-project 1)
```

- [ ] **Step 4: Run the test to verify it passes.**

Run: `vendor/bin/phpunit --filter test_phase5_foundation_flags_default_dark tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add src/Core/FeatureFlags.php tests/Integration/Core/AppFeatureFlagTest.php
git commit -m "Add deploy-dark service_secrets feature flag (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Migration `0055` + schema-shape test + SCHEMA.md

**Files:**
- Create: `database/migrations/0055_phase5_service_secrets.php`
- Create: `tests/Integration/Core/AppServiceSecretsSchemaTest.php`
- Modify: `SCHEMA.md` (new shape subsection + §9 changelog + version bump)

**Interfaces:**
- Consumes: nothing.
- Produces: tables `service_secrets` and `service_secret_versions` with the columns/indexes/FKs below.

- [ ] **Step 1: Write the failing schema-shape test.** Create `tests/Integration/Core/AppServiceSecretsSchemaTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Schema-shape checks for the B2 service-secret registry (migration 0055).
 * Additive + inert: the tables exist and match the documented shape; behavior
 * lives in SecretVaultTest. "Inert schema is not evidence" (DESIGN §13).
 */
final class AppServiceSecretsSchemaTest extends TestCase
{
    private function tableExists(string $table): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        ) === 1;
    }

    /** @return array{type:string,column_type:string,nullable:string}|null */
    private function column(string $table, string $column): ?array
    {
        $row = $this->db->fetch(
            'SELECT DATA_TYPE AS type, COLUMN_TYPE AS column_type, IS_NULLABLE AS nullable
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );
        return $row === null ? null : [
            'type' => (string) $row['type'],
            'column_type' => (string) $row['column_type'],
            'nullable' => (string) $row['nullable'],
        ];
    }

    private function indexIsUnique(string $table, string $index): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0",
            [$table, $index],
        ) > 0;
    }

    public function test_service_secret_tables_exist(): void
    {
        self::assertTrue($this->tableExists('service_secrets'));
        self::assertTrue($this->tableExists('service_secret_versions'));
    }

    public function test_reference_table_shape(): void
    {
        $ref = $this->column('service_secrets', 'secret_ref');
        self::assertNotNull($ref);
        self::assertSame('varchar', $ref['type']);
        self::assertSame('varchar(64)', $ref['column_type']);
        self::assertTrue($this->indexIsUnique('service_secrets', 'uq_service_secret_ref'), 'secret_ref must be uniquely indexed');

        $latest = $this->column('service_secrets', 'latest_version');
        self::assertNotNull($latest);
        self::assertSame('int', $latest['type']);

        $status = $this->column('service_secrets', 'status');
        self::assertNotNull($status);
        self::assertSame("enum('active','revoked')", $status['column_type']);
    }

    public function test_version_table_stores_binary_material(): void
    {
        foreach (['ciphertext', 'nonce', 'tag'] as $col) {
            $c = $this->column('service_secret_versions', $col);
            self::assertNotNull($c, "missing column service_secret_versions.$col");
            self::assertSame('varbinary', $c['type'], "$col must be VARBINARY");
        }
        $state = $this->column('service_secret_versions', 'state');
        self::assertNotNull($state);
        self::assertSame("enum('current','retired','destroyed')", $state['column_type']);
        self::assertTrue(
            $this->indexIsUnique('service_secret_versions', 'uq_service_secret_version'),
            '(secret_id, version) must be uniquely indexed',
        );
        // The prune index exists (non-unique).
        self::assertSame(
            1,
            (int) $this->db->fetchValue(
                "SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'service_secret_versions'
                   AND INDEX_NAME = 'idx_service_secret_prune'",
            ),
        );
    }

    public function test_version_fk_cascades_from_parent(): void
    {
        self::assertSame(
            'CASCADE',
            $this->db->fetchValue(
                "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'service_secret_versions'
                   AND CONSTRAINT_NAME = 'fk_service_secret_version_secret'",
            ),
        );
    }
}
```

- [ ] **Step 2: Run it to verify it fails.**

Run: `vendor/bin/phpunit tests/Integration/Core/AppServiceSecretsSchemaTest.php`
Expected: FAIL — `service_secrets` table does not exist (the migration isn't written yet).

- [ ] **Step 3: Write the migration.** Create `database/migrations/0055_phase5_service_secrets.php`:

```php
<?php

declare(strict_types=1);

/**
 * 0055 · Phase 5 Gate A prerequisite (B2) — encrypted service-secret registry.
 *
 * ADDITIVE + INERT. Reversible-secret vault (SecretVault) built on SecretBox.
 * service_secrets holds opaque references; service_secret_versions holds the
 * AES-256-GCM material per version. Nothing reads these until a consumer
 * (provider/webhook) and the dark `service_secrets` flag turn on.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE service_secrets (
              id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
              secret_ref      VARCHAR(64)      NOT NULL,
              owner_type      VARCHAR(32)      NOT NULL DEFAULT 'generic',
              owner_id        BIGINT UNSIGNED  NULL,
              label           VARCHAR(190)     NOT NULL,
              status          ENUM('active','revoked') NOT NULL DEFAULT 'active',
              latest_version  INT UNSIGNED     NOT NULL DEFAULT 0,
              created_by      BIGINT UNSIGNED  NULL,
              revoked_by      BIGINT UNSIGNED  NULL,
              created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              revoked_at      DATETIME         NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_service_secret_ref (secret_ref),
              KEY idx_service_secret_owner (owner_type, owner_id),
              CONSTRAINT fk_service_secret_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_service_secret_revoked_by FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE service_secret_versions (
              id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
              secret_id     BIGINT UNSIGNED  NOT NULL,
              version       INT UNSIGNED     NOT NULL,
              ciphertext    VARBINARY(4096)  NOT NULL,
              nonce         VARBINARY(12)    NOT NULL,
              tag           VARBINARY(16)    NOT NULL,
              cipher        VARCHAR(32)      NOT NULL DEFAULT 'aes-256-gcm',
              key_version   INT UNSIGNED     NOT NULL DEFAULT 1,
              state         ENUM('current','retired','destroyed') NOT NULL DEFAULT 'current',
              created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              retire_after  DATETIME         NULL,
              retired_at    DATETIME         NULL,
              destroyed_at  DATETIME         NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_service_secret_version (secret_id, version),
              KEY idx_service_secret_prune (state, retire_after),
              CONSTRAINT fk_service_secret_version_secret FOREIGN KEY (secret_id) REFERENCES service_secrets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS service_secret_versions');
        $pdo->exec('DROP TABLE IF EXISTS service_secrets');
    }
};
```

- [ ] **Step 4: Run the schema test to verify it passes.**

Run: `vendor/bin/phpunit tests/Integration/Core/AppServiceSecretsSchemaTest.php`
Expected: PASS (the bootstrap re-migrates the test DB, applying `0055`).

- [ ] **Step 5: Update SCHEMA.md.** Open `SCHEMA.md`. (a) After the §5A foundation subsection, add a subsection titled `### 5B. B2 service-secret registry (migration 0055)` containing the two column tables from the design spec §2 (copy them verbatim). (b) Add a §9 changelog row: `- 0055 — B2 encrypted service-secret registry: service_secrets (opaque refs, status, latest_version) + service_secret_versions (AES-256-GCM material, versioned, grace/destroy lifecycle).` (c) Bump the document version in the header to the next minor (read the current value from the top of the file and increment).

- [ ] **Step 6: Rehearse the additive upgrade on seeded data.**

Run: `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`
Expected: `Result: PASS ✓` (all checks pass; `0055` applies additively on seeded Phase-1 data).

- [ ] **Step 7: Commit.**

```bash
git add database/migrations/0055_phase5_service_secrets.php tests/Integration/Core/AppServiceSecretsSchemaTest.php SCHEMA.md
git commit -m "Add migration 0055: service-secret registry tables (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Exceptions, config, repository core + `SecretVault::store`/`reveal` + wiring

**Files:**
- Create: `src/Core/SecretNotFoundException.php`, `src/Core/SecretRevokedException.php`, `src/Core/SecretsDisabledException.php`
- Modify: `config/config.php` (add a `secrets` block after `rate_limits`)
- Create: `src/Repository/ServiceSecretRepository.php` (store/read methods now; more added in Tasks 4–5)
- Create: `src/Service/SecretVault.php` (`store` + `reveal` now)
- Create: `tests/Integration/Service/SecretVaultTest.php`

> **No `App.php` / container binding in this increment.** Nothing resolves `SecretVault`
> from the container yet (the console case and tests construct it directly), and
> `App::buildContainer()` is `private` with no test seam — a binding added now would be
> unexercised wiring. The binding is **deferred to the first consumer sub-project**
> (webhooks/providers), which will resolve it through a real route + test.

**Interfaces:**
- Consumes: `service_secrets`/`service_secret_versions` tables (Task 2); `service_secrets` flag (Task 1); `App\Security\SecretBox::encrypt(string): array{ciphertext,nonce,tag}` / `decrypt(string,string,string): string`; `App\Repository\ModerationLogRepository::log(array): int`; `App\Core\Database::transaction(callable)`, `insert/run/fetch/fetchValue`; `App\Core\Config::get(string, mixed)`; `App\Core\FeatureFlags::enabled(string): bool`; `App\Domain\User::id(): int`.
- Produces:
  - `ServiceSecretRepository::insertSecret(string $ref, string $ownerType, ?int $ownerId, string $label, ?int $createdBy): int`
  - `ServiceSecretRepository::insertCurrentVersion(int $secretId, int $version, array $enc): int`
  - `ServiceSecretRepository::findSecretByRef(string $ref): ?array`
  - `ServiceSecretRepository::currentVersionRow(int $secretId): ?array`
  - `SecretVault::store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor = null): string`
  - `SecretVault::reveal(string $ref): string`
  - `SecretVault::__construct(Database, ServiceSecretRepository, SecretBox, ModerationLogRepository, FeatureFlags, Config)`
  - Exceptions `App\Core\SecretNotFoundException`, `SecretRevokedException`, `SecretsDisabledException` (all extend `RuntimeException`).

- [ ] **Step 1: Create the three exceptions.** Each in `src/Core/`, matching the existing exception style:

`src/Core/SecretNotFoundException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when a secret reference is unknown. Messages never contain a secret value. */
final class SecretNotFoundException extends RuntimeException
{
}
```

`src/Core/SecretRevokedException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when reading or rotating a revoked secret reference. */
final class SecretRevokedException extends RuntimeException
{
}
```

`src/Core/SecretsDisabledException.php`:
```php
<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown by store()/rotate() when the service_secrets kill switch is dark. */
final class SecretsDisabledException extends RuntimeException
{
}
```

- [ ] **Step 2: Add the config block.** In `config/config.php`, immediately after the `'rate_limits' => [ … ],` block (ends ~line 202), add:

```php
    'secrets' => [
        // B2 service-secret registry (SecretVault, built on SecretBox).
        'rotation_grace_seconds' => 86400, // retired versions stay decryptable this long for rotation overlap
        'max_secret_bytes' => 4096,        // plaintext ceiling (fits VARBINARY(4096) ciphertext)
    ],
```

- [ ] **Step 3: Write the failing test (store/reveal round-trip + edges).** Create `tests/Integration/Service/SecretVaultTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretsDisabledException;
use App\Core\ValidationException;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use Tests\Support\TestCase;

final class SecretVaultTest extends TestCase
{
    private function vault(bool $enabled = true): SecretVault
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => $enabled]);
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    public function test_store_then_reveal_round_trips(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'a label', 'PLAINTEXT-AAA-9f3');
        self::assertStringStartsWith('svcsec_', $ref);
        self::assertSame('PLAINTEXT-AAA-9f3', $v->reveal($ref));
    }

    public function test_ciphertext_at_rest_is_not_the_plaintext(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'l', 'PLAINTEXT-AAA-9f3');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $cipher = (string) $this->db->fetchValue('SELECT ciphertext FROM service_secret_versions WHERE secret_id = ?', [$id]);
        self::assertGreaterThan(0, strlen($cipher));
        self::assertStringNotContainsString('PLAINTEXT-AAA-9f3', $cipher);
    }

    public function test_unknown_ref_reveal_throws_not_found(): void
    {
        $this->expectException(SecretNotFoundException::class);
        $this->vault()->reveal('svcsec_does_not_exist');
    }

    public function test_oversize_plaintext_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $max = (int) $this->config->get('secrets.max_secret_bytes', 4096);
        $this->vault()->store('generic', null, 'big', str_repeat('x', $max + 1));
    }

    public function test_two_stores_yield_distinct_refs(): void
    {
        $v = $this->vault();
        self::assertNotSame(
            $v->store('generic', null, 'one', 's1'),
            $v->store('generic', null, 'two', 's2'),
        );
    }

    public function test_store_is_blocked_when_flag_dark(): void
    {
        $this->expectException(SecretsDisabledException::class);
        $this->vault(false)->store('generic', null, 'l', 's');
    }

    public function test_reveal_still_works_when_flag_dark(): void
    {
        // Store with the flag on, then flip it dark: existing secrets stay readable.
        $ref = $this->vault(true)->store('generic', null, 'l', 'KEEP-READABLE-BBB');
        self::assertSame('KEEP-READABLE-BBB', $this->vault(false)->reveal($ref));
    }
}
```

- [ ] **Step 4: Run it to verify it fails.**

Run: `vendor/bin/phpunit tests/Integration/Service/SecretVaultTest.php`
Expected: FAIL — `Class "App\Repository\ServiceSecretRepository" not found` / `App\Service\SecretVault` not found.

- [ ] **Step 5: Create the repository (store/read methods).** Create `src/Repository/ServiceSecretRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Single-table SQL for the B2 service-secret registry (service_secrets +
 * service_secret_versions). All times are UTC; LIMIT/INTERVAL quantities are
 * int-cast and concatenated, never bound (PDO EMULATE_PREPARES=false).
 */
final class ServiceSecretRepository
{
    public function __construct(private Database $db)
    {
    }

    public function insertSecret(string $ref, string $ownerType, ?int $ownerId, string $label, ?int $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO service_secrets (secret_ref, owner_type, owner_id, label, status, latest_version, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, \'active\', 1, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
            [$ref, $ownerType, $ownerId, $label, $createdBy],
        );
    }

    /** @param array{ciphertext:string,nonce:string,tag:string} $enc */
    public function insertCurrentVersion(int $secretId, int $version, array $enc): int
    {
        return $this->db->insert(
            'INSERT INTO service_secret_versions (secret_id, version, ciphertext, nonce, tag, cipher, key_version, state, created_at)
             VALUES (?, ?, ?, ?, ?, \'aes-256-gcm\', 1, \'current\', UTC_TIMESTAMP())',
            [$secretId, $version, $enc['ciphertext'], $enc['nonce'], $enc['tag']],
        );
    }

    public function findSecretByRef(string $ref): ?array
    {
        return $this->db->fetch('SELECT * FROM service_secrets WHERE secret_ref = ?', [$ref]);
    }

    public function currentVersionRow(int $secretId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM service_secret_versions WHERE secret_id = ? AND state = 'current' ORDER BY version DESC LIMIT 1",
            [$secretId],
        );
    }
}
```

- [ ] **Step 6: Create the service (store + reveal).** Create `src/Service/SecretVault.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretRevokedException;
use App\Core\SecretsDisabledException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Security\SecretBox;

/**
 * Reversible-secret vault seam over SecretBox. Hands out opaque references;
 * plaintext is write-only (revealed only server-side at point of use), never
 * logged/exported. Versioned rotation with a grace window, revoke, prune.
 *
 * Owns its transactions (ThreadWorkflowService pattern): a single txn spans
 * ServiceSecretRepository + ModerationLogRepository. Callers MUST invoke
 * store()/rotate() inside their own consumer-save txn so a failed consumer
 * write rolls the secret back (Database::transaction is reentrant).
 */
final class SecretVault
{
    public function __construct(
        private Database $db,
        private ServiceSecretRepository $secrets,
        private SecretBox $box,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
    ) {
    }

    public function store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor = null): string
    {
        $this->assertEnabled();
        $this->assertSize($plaintext);
        $ref = 'svcsec_' . bin2hex(random_bytes(16));
        $enc = $this->box->encrypt($plaintext);
        $actorId = $actor?->id();

        $this->db->transaction(function () use ($ref, $ownerType, $ownerId, $label, $enc, $actorId): void {
            $secretId = $this->secrets->insertSecret($ref, $ownerType, $ownerId, $label, $actorId);
            $this->secrets->insertCurrentVersion($secretId, 1, $enc);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_stored',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'after' => ['ref' => $ref, 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'label' => $label, 'version' => 1],
            ]);
        });

        return $ref;
    }

    public function reveal(string $ref): string
    {
        $secret = $this->requireActive($ref);
        $row = $this->secrets->currentVersionRow((int) $secret['id']);
        if ($row === null) {
            throw new SecretNotFoundException('No current version for this secret reference.');
        }
        return $this->box->decrypt((string) $row['ciphertext'], (string) $row['nonce'], (string) $row['tag']);
    }

    /** @return array<string,mixed> */
    private function requireActive(string $ref): array
    {
        $secret = $this->secrets->findSecretByRef($ref);
        if ($secret === null) {
            throw new SecretNotFoundException('Unknown secret reference.');
        }
        if ((string) ($secret['status'] ?? '') === 'revoked') {
            throw new SecretRevokedException('This secret reference is revoked.');
        }
        return $secret;
    }

    private function assertEnabled(): void
    {
        if (!$this->flags->enabled('service_secrets')) {
            throw new SecretsDisabledException('The service-secret store is disabled.');
        }
    }

    private function assertSize(string $plaintext): void
    {
        $max = (int) $this->config->get('secrets.max_secret_bytes', 4096);
        if (strlen($plaintext) > $max) {
            throw new ValidationException(['secret' => "Secret exceeds the {$max}-byte maximum."]);
        }
    }
}
```

- [ ] **Step 7: Run the test to verify it passes.**

Run: `vendor/bin/phpunit tests/Integration/Service/SecretVaultTest.php`
Expected: PASS (7 tests).

> No container binding in this increment (see the note under **Files**). The console
> command (Task 5) and these tests construct `SecretVault` directly; the binding is
> deferred to the first consumer sub-project.

- [ ] **Step 8: Commit.**

```bash
git add src/Core/SecretNotFoundException.php src/Core/SecretRevokedException.php src/Core/SecretsDisabledException.php config/config.php src/Repository/ServiceSecretRepository.php src/Service/SecretVault.php tests/Integration/Service/SecretVaultTest.php
git commit -m "SecretVault store/reveal over SecretBox with dark kill switch (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: `rotate` + `usableSecrets` + `metadata` (FOR UPDATE serialization)

**Files:**
- Modify: `src/Repository/ServiceSecretRepository.php` (add methods)
- Modify: `src/Service/SecretVault.php` (add methods)
- Modify: `tests/Integration/Service/SecretVaultTest.php` (add tests)

**Interfaces:**
- Consumes: Task 3 methods.
- Produces:
  - `ServiceSecretRepository::lockSecretByRef(string $ref): ?array` (FOR UPDATE)
  - `ServiceSecretRepository::retireCurrentVersion(int $secretId, int $graceSeconds): void`
  - `ServiceSecretRepository::bumpLatestVersion(int $secretId, int $version): void`
  - `ServiceSecretRepository::usableVersionRows(int $secretId): array`
  - `ServiceSecretRepository::versionCount(int $secretId): int`
  - `SecretVault::rotate(string $ref, string $newPlaintext, ?User $actor = null, ?int $graceSeconds = null): int`
  - `SecretVault::usableSecrets(string $ref): array` (string[], newest-first)
  - `SecretVault::metadata(string $ref): array`

- [ ] **Step 1: Write the failing tests.** Append to `tests/Integration/Service/SecretVaultTest.php` (add `use App\Core\SecretRevokedException;` to the imports if not present — it will be needed in Task 5; add it now):

```php
    public function test_rotate_reveals_new_and_keeps_old_within_grace(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rot', 'old-secret');
        $version = $v->rotate($ref, 'new-secret');
        self::assertSame(2, $version);
        self::assertSame('new-secret', $v->reveal($ref));
        self::assertSame(['new-secret', 'old-secret'], $v->usableSecrets($ref), 'current + in-grace retired, newest first');
    }

    public function test_sequential_rotations_are_deterministic_and_single_current(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rot', 'v1-secret');
        for ($i = 2; $i <= 4; $i++) {
            self::assertSame($i, $v->rotate($ref, "v{$i}-secret"));
            $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
            self::assertSame(
                1,
                (int) $this->db->fetchValue("SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ? AND state = 'current'", [$id]),
                'exactly one current version after each rotation',
            );
        }
        self::assertSame('v4-secret', $v->reveal($ref));
    }

    public function test_metadata_reports_status_without_plaintext(): void
    {
        $v = $this->vault();
        $ref = $v->store('provider', 7, 'client secret', 'SENSITIVE-CCC');
        $v->rotate($ref, 'SENSITIVE-DDD');
        $meta = $v->metadata($ref);
        self::assertSame('active', $meta['status']);
        self::assertSame(2, $meta['latest_version']);
        self::assertTrue($meta['has_live_version']);
        self::assertSame('provider', $meta['owner_type']);
        self::assertSame(7, $meta['owner_id']);
        self::assertSame('client secret', $meta['label']);
        self::assertStringNotContainsString('SENSITIVE', json_encode($meta) ?: '');
    }
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `vendor/bin/phpunit --filter test_rotate_reveals_new_and_keeps_old_within_grace tests/Integration/Service/SecretVaultTest.php`
Expected: FAIL — `Call to undefined method App\Service\SecretVault::rotate()`.

- [ ] **Step 3: Add the repository methods.** Append to `ServiceSecretRepository`:

```php
    public function lockSecretByRef(string $ref): ?array
    {
        return $this->db->fetch(
            'SELECT id, latest_version, status FROM service_secrets WHERE secret_ref = ? FOR UPDATE',
            [$ref],
        );
    }

    public function retireCurrentVersion(int $secretId, int $graceSeconds): void
    {
        $grace = max(0, $graceSeconds);
        $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'retired', retired_at = UTC_TIMESTAMP(),
                 retire_after = DATE_ADD(UTC_TIMESTAMP(), INTERVAL " . $grace . " SECOND)
             WHERE secret_id = ? AND state = 'current'",
            [$secretId],
        );
    }

    public function bumpLatestVersion(int $secretId, int $version): void
    {
        $this->db->run(
            'UPDATE service_secrets SET latest_version = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?',
            [$version, $secretId],
        );
    }

    /** @return array<int,array<string,mixed>> current + in-grace retired, newest version first */
    public function usableVersionRows(int $secretId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM service_secret_versions
             WHERE secret_id = ?
               AND (state = 'current' OR (state = 'retired' AND retire_after IS NOT NULL AND retire_after > UTC_TIMESTAMP()))
             ORDER BY version DESC",
            [$secretId],
        );
    }

    public function versionCount(int $secretId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ?', [$secretId]);
    }
```

- [ ] **Step 4: Add the service methods.** Append to `SecretVault` (before the private helpers):

```php
    public function rotate(string $ref, string $newPlaintext, ?User $actor = null, ?int $graceSeconds = null): int
    {
        $this->assertEnabled();
        $this->assertSize($newPlaintext);
        $grace = max(0, $graceSeconds ?? (int) $this->config->get('secrets.rotation_grace_seconds', 86400));
        $enc = $this->box->encrypt($newPlaintext);
        $actorId = $actor?->id();

        return $this->db->transaction(function () use ($ref, $enc, $grace, $actorId): int {
            $secret = $this->secrets->lockSecretByRef($ref);
            if ($secret === null) {
                throw new SecretNotFoundException('Unknown secret reference.');
            }
            if ((string) ($secret['status'] ?? '') === 'revoked') {
                throw new SecretRevokedException('This secret reference is revoked.');
            }
            $secretId = (int) $secret['id'];
            $newVersion = (int) $secret['latest_version'] + 1;
            $this->secrets->retireCurrentVersion($secretId, $grace);
            $this->secrets->insertCurrentVersion($secretId, $newVersion, $enc);
            $this->secrets->bumpLatestVersion($secretId, $newVersion);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_rotated',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'before' => ['version' => (int) $secret['latest_version']],
                'after' => ['version' => $newVersion, 'grace_seconds' => $grace],
            ]);
            return $newVersion;
        });
    }

    /** @return array<int,string> current + in-grace retired plaintexts, newest first */
    public function usableSecrets(string $ref): array
    {
        $secret = $this->requireActive($ref);
        return array_map(
            fn (array $r): string => $this->box->decrypt((string) $r['ciphertext'], (string) $r['nonce'], (string) $r['tag']),
            $this->secrets->usableVersionRows((int) $secret['id']),
        );
    }

    /** @return array<string,mixed> never contains plaintext or ciphertext */
    public function metadata(string $ref): array
    {
        $secret = $this->secrets->findSecretByRef($ref);
        if ($secret === null) {
            throw new SecretNotFoundException('Unknown secret reference.');
        }
        $secretId = (int) $secret['id'];
        $hasCurrent = $this->secrets->currentVersionRow($secretId) !== null;
        return [
            'ref' => (string) $secret['secret_ref'],
            'status' => (string) $secret['status'],
            'latest_version' => (int) $secret['latest_version'],
            'has_live_version' => $hasCurrent && (string) $secret['status'] === 'active',
            'owner_type' => (string) $secret['owner_type'],
            'owner_id' => $secret['owner_id'] === null ? null : (int) $secret['owner_id'],
            'label' => (string) $secret['label'],
            'version_count' => $this->secrets->versionCount($secretId),
            'created_at' => (string) $secret['created_at'],
            'updated_at' => (string) $secret['updated_at'],
            'revoked_at' => $secret['revoked_at'] === null ? null : (string) $secret['revoked_at'],
        ];
    }
```

- [ ] **Step 5: Run the test file to verify it passes.**

Run: `vendor/bin/phpunit tests/Integration/Service/SecretVaultTest.php`
Expected: PASS (10 tests).

- [ ] **Step 6: Commit.**

```bash
git add src/Repository/ServiceSecretRepository.php src/Service/SecretVault.php tests/Integration/Service/SecretVaultTest.php
git commit -m "SecretVault rotate/usableSecrets/metadata with FOR UPDATE serialization (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: `revoke` + `prune` + `worker:secret-prune` + redaction

**Files:**
- Modify: `src/Repository/ServiceSecretRepository.php` (add methods)
- Modify: `src/Service/SecretVault.php` (add methods)
- Modify: `bin/console` (add `worker:secret-prune` case + imports + help line)
- Modify: `tests/Integration/Service/SecretVaultTest.php` (add tests)

**Interfaces:**
- Consumes: Tasks 3–4 methods.
- Produces:
  - `ServiceSecretRepository::markRevoked(int $secretId, ?int $actorId): void`
  - `ServiceSecretRepository::retireAllVersions(int $secretId): void`
  - `ServiceSecretRepository::pruneCandidates(int $limit): array` (rows of `id, secret_id, version`)
  - `ServiceSecretRepository::destroyVersion(int $versionId): int` (affected-row count)
  - `ServiceSecretRepository::acquirePruneLock(): bool` / `releasePruneLock(): void`
  - `SecretVault::revoke(string $ref, ?User $actor = null): void`
  - `SecretVault::prune(int $limit = 100): int`

- [ ] **Step 1: Write the failing tests.** Append to `tests/Integration/Service/SecretVaultTest.php`:

```php
    public function test_revoke_blocks_reads_and_marks_metadata(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rev', 'to-be-revoked');
        $v->revoke($ref);
        self::assertSame('revoked', $v->metadata($ref)['status']);
        $this->expectException(SecretRevokedException::class);
        $v->reveal($ref);
    }

    public function test_revoke_works_when_flag_dark(): void
    {
        $ref = $this->vault(true)->store('generic', null, 'rev', 'secret');
        $this->vault(false)->revoke($ref); // must not throw SecretsDisabledException
        self::assertSame('revoked', $this->vault(false)->metadata($ref)['status']);
    }

    public function test_prune_destroys_expired_retired_version_fully(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'g', 'old-secret');
        $v->rotate($ref, 'new-secret');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);

        // Force the retired v1 past its grace window.
        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );

        // BEFORE prune: grace expiry alone removes the old value from use, but ciphertext remains.
        self::assertSame(['new-secret'], $v->usableSecrets($ref));
        self::assertGreaterThan(
            0,
            (int) $this->db->fetchValue('SELECT LENGTH(ciphertext) FROM service_secret_versions WHERE secret_id = ? AND version = 1', [$id]),
        );

        // AFTER prune: row retained, fully destroyed.
        self::assertSame(1, $v->prune(100));
        $row = $this->db->fetch(
            'SELECT state, destroyed_at, LENGTH(ciphertext) AS cl, LENGTH(nonce) AS nl, LENGTH(tag) AS tl
             FROM service_secret_versions WHERE secret_id = ? AND version = 1',
            [$id],
        );
        self::assertNotNull($row, 'destroyed version row is retained for audit history');
        self::assertSame('destroyed', $row['state']);
        self::assertNotNull($row['destroyed_at']);
        self::assertSame(0, (int) $row['cl']);
        self::assertSame(0, (int) $row['nl']);
        self::assertSame(0, (int) $row['tl']);
    }

    public function test_prune_is_idempotent_across_overlapping_runs(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'p', 'v1');
        $v->rotate($ref, 'v2');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );

        self::assertSame(1, $v->prune(100));
        $auditAfterFirst = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'service_secret' AND action = 'service_secret_version_destroyed' AND target_id = ?",
            [$id],
        );
        // A second (overlapping) run destroys nothing and writes no new audit row.
        self::assertSame(0, $v->prune(100));
        self::assertSame(
            $auditAfterFirst,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'service_secret' AND action = 'service_secret_version_destroyed' AND target_id = ?",
                [$id],
            ),
            'a second prune must not double-audit',
        );
    }

    public function test_no_plaintext_leaks_into_audit(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'label only', 'PLAINTEXT-AAA-9f3');
        $v->rotate($ref, 'PLAINTEXT-BBB-7c1');
        $v->revoke($ref);
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $rows = $this->db->fetchAll(
            "SELECT before_json, after_json FROM moderation_log WHERE target_type = 'service_secret' AND target_id = ?",
            [$id],
        );
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            $blob = (string) ($row['before_json'] ?? '') . (string) ($row['after_json'] ?? '');
            self::assertStringNotContainsString('PLAINTEXT-AAA-9f3', $blob);
            self::assertStringNotContainsString('PLAINTEXT-BBB-7c1', $blob);
        }
    }

    public function test_no_plaintext_leaks_into_exception_messages(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'l', 'SECRET-IN-MSG-EEE');
        $v->revoke($ref);
        try {
            $v->reveal($ref);
            self::fail('expected SecretRevokedException');
        } catch (SecretRevokedException $e) {
            self::assertStringNotContainsString('SECRET-IN-MSG-EEE', $e->getMessage());
        }
    }

    public function test_revoke_makes_versions_immediately_prunable(): void
    {
        // No retire_after manipulation: revoke alone must make versions prunable in
        // the same run. Fails under the old >=/< boundary (same-second skip).
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rev', 'doomed-secret');
        $v->revoke($ref);
        self::assertSame(1, $v->prune(100));
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        self::assertSame(
            'destroyed',
            (string) $this->db->fetchValue("SELECT state FROM service_secret_versions WHERE secret_id = ? AND version = 1", [$id]),
        );
    }
```

- [ ] **Step 2: Run them to verify they fail.**

Run: `vendor/bin/phpunit --filter test_revoke_blocks_reads_and_marks_metadata tests/Integration/Service/SecretVaultTest.php`
Expected: FAIL — `Call to undefined method App\Service\SecretVault::revoke()`.

- [ ] **Step 3: Add the repository methods.** Append to `ServiceSecretRepository`:

```php
    public function markRevoked(int $secretId, ?int $actorId): void
    {
        $this->db->run(
            "UPDATE service_secrets SET status = 'revoked', revoked_at = UTC_TIMESTAMP(), revoked_by = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?",
            [$actorId, $secretId],
        );
    }

    public function retireAllVersions(int $secretId): void
    {
        // Make every non-destroyed version immediately prunable: retire_after = now,
        // and pruneCandidates uses retire_after <= now, so a same-second prune destroys them.
        $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'retired',
                 retired_at = COALESCE(retired_at, UTC_TIMESTAMP()),
                 retire_after = UTC_TIMESTAMP()
             WHERE secret_id = ? AND state IN ('current', 'retired')",
            [$secretId],
        );
    }

    /** @return array<int,array<string,mixed>> id/secret_id/version of retired versions past grace */
    public function pruneCandidates(int $limit): array
    {
        $limit = max(1, $limit);
        return $this->db->fetchAll(
            "SELECT id, secret_id, version FROM service_secret_versions
             WHERE state = 'retired' AND retire_after IS NOT NULL AND retire_after <= UTC_TIMESTAMP()
             ORDER BY id LIMIT " . $limit,
        );
    }

    /** Idempotent: only a still-`retired` row transitions. Returns affected rows. */
    public function destroyVersion(int $versionId): int
    {
        return $this->db->run(
            "UPDATE service_secret_versions
             SET state = 'destroyed', destroyed_at = UTC_TIMESTAMP(), ciphertext = '', nonce = '', tag = ''
             WHERE id = ? AND state = 'retired'",
            [$versionId],
        )->rowCount();
    }

    public function acquirePruneLock(): bool
    {
        return (int) $this->db->fetchValue("SELECT GET_LOCK('rb_secret_prune', 0)") === 1;
    }

    public function releasePruneLock(): void
    {
        $this->db->run("SELECT RELEASE_LOCK('rb_secret_prune')");
    }
```

- [ ] **Step 4: Add the service methods.** Append to `SecretVault` (before the private helpers):

```php
    public function revoke(string $ref, ?User $actor = null): void
    {
        $actorId = $actor?->id();
        $this->db->transaction(function () use ($ref, $actorId): void {
            $secret = $this->secrets->lockSecretByRef($ref);
            if ($secret === null) {
                throw new SecretNotFoundException('Unknown secret reference.');
            }
            if ((string) ($secret['status'] ?? '') === 'revoked') {
                return; // idempotent
            }
            $secretId = (int) $secret['id'];
            $this->secrets->markRevoked($secretId, $actorId);
            $this->secrets->retireAllVersions($secretId);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_revoked',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'before' => ['status' => 'active'],
                'after' => ['status' => 'revoked'],
            ]);
        });
    }

    public function prune(int $limit = 100): int
    {
        $limit = max(1, $limit);
        if (!$this->secrets->acquirePruneLock()) {
            return 0; // another worker owns the drain
        }
        try {
            $destroyed = 0;
            foreach ($this->secrets->pruneCandidates($limit) as $row) {
                $this->db->transaction(function () use ($row, &$destroyed): void {
                    if ($this->secrets->destroyVersion((int) $row['id']) !== 1) {
                        return; // already destroyed by a racing run; do not double-audit
                    }
                    $this->log->log([
                        'actor_id' => null,
                        'action' => 'service_secret_version_destroyed',
                        'target_type' => 'service_secret',
                        'target_id' => (int) $row['secret_id'],
                        'before' => ['version' => (int) $row['version'], 'state' => 'retired'],
                        'after' => ['version' => (int) $row['version'], 'state' => 'destroyed'],
                    ]);
                    $destroyed++;
                });
            }
            return $destroyed;
        } finally {
            $this->secrets->releasePruneLock();
        }
    }
```

- [ ] **Step 5: Run the test file to verify it passes.**

Run: `vendor/bin/phpunit tests/Integration/Service/SecretVaultTest.php`
Expected: PASS (17 tests).

- [ ] **Step 6: Add the console command.** In `bin/console`, add to the `use` block (after line 24):

```php
use App\Core\FeatureFlags;
use App\Repository\ServiceSecretRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
```

Add a case before the `case 'help':` block (after the `engagement:cutover` case, ~line 251):

```php
        case 'worker:secret-prune':
            // Destroy retired service-secret versions whose grace window has
            // lapsed (B2). Ciphertext/nonce/tag are emptied; the row is kept
            // for audit (state=destroyed). Behind the rb_secret_prune lock.
            $db = $database();
            $vault = new SecretVault(
                $db,
                new ServiceSecretRepository($db),
                new SecretBox((string) $config->get('app.key', '')),
                new ModerationLogRepository($db),
                new FeatureFlags(new SettingRepository($db)),
                $config,
            );
            $destroyed = $vault->prune(isset($argv[2]) ? (int) $argv[2] : 100);
            $log(sprintf('Service-secret prune: destroyed=%d', $destroyed));
            break;
```

Add a help line after the `worker:attachments` help line (~line 273):

```php
            $log('  worker:secret-prune [limit]  Destroy expired retired service-secret versions (default 100)');
```

- [ ] **Step 7: Syntax-check the console command (hermetic).**

Run: `php -l bin/console`
Expected: `No syntax errors detected in bin/console`.

> Do **not** run `php bin/console worker:secret-prune` as a verification step — it loads
> the configured application DB and `prune()` mutates it (destroys versions + writes
> audit rows), so the result is non-hermetic and non-repeatable. `prune()` behavior is
> proven by `SecretVaultTest` (tests 4/13/14) against the isolated test DB; the console
> case is thin glue over the same `new SecretVault(...)` signature those tests exercise.

- [ ] **Step 8: Commit.**

```bash
git add src/Repository/ServiceSecretRepository.php src/Service/SecretVault.php bin/console tests/Integration/Service/SecretVaultTest.php
git commit -m "SecretVault revoke/prune + worker:secret-prune drain lock (B2)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Status docs + full-suite + upgrade-rehearsal closeout

**Files:**
- Modify: `PHASE_5_STATUS.md` (record this increment + the B2 decomposition)

**Interfaces:** none (documentation + verification).

- [ ] **Step 1: Run the full suite.**

Run: `./vendor/bin/phpunit`
Expected: PASS (all green; the prior 468 plus the new `SecretVaultTest` (17) + `AppServiceSecretsSchemaTest` (4) + the extended flag assertion). Record the exact totals from the output.

- [ ] **Step 2: Rehearse the populated upgrade.**

Run: `DB_DATABASE=retroboards_upgrade_verify php bin/console verify:upgrade --force`
Expected: `Result: PASS ✓`.

- [ ] **Step 3: Update PHASE_5_STATUS.md.** Add a "Landed in this increment" bullet group describing the service-secret registry (migration `0055`; `SecretVault`/`ServiceSecretRepository`; `service_secrets` dark flag + kill-switch semantics; rotation grace + prune drain; `worker:secret-prune`; redaction + audit). Record the B2 program decomposition (4 sub-projects: **1) service-secret registry — landed**, 2) API tokens + scopes, 3) webhook delivery, 4) first-party hook registry), citing the design spec path. Update the suite line with the exact totals from Step 1. Note B3 moves from PARTIAL toward DONE for the storage layer (still consumed by no provider/webhook yet).

- [ ] **Step 4: Commit.**

```bash
git add PHASE_5_STATUS.md
git commit -m "Record B2 service-secret registry increment + decomposition (Phase 5)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage** (design spec §→ task):
- §2 data model → Task 2 (migration + schema test).
- §3 repository + `SecretVault` API (store/reveal/usableSecrets/rotate/revoke/metadata/prune) → Tasks 3 (store/reveal), 4 (rotate/usableSecrets/metadata), 5 (revoke/prune); console → Task 5.
- §3 Concurrency (FOR UPDATE) → Task 4 (`lockSecretByRef`, determinism test). Prune drain lock + idempotent destroy → Task 5 (`acquirePruneLock`/`destroyVersion`, idempotency test).
- §3 Transactional ownership contract → honored by service-owned `Database::transaction` (Task 3 `store`, reentrant); documented in spec; no consumer in this increment.
- §4 error handling → Task 3 (exceptions, oversize→`ValidationException`, audit-in-txn); GCM mismatch is `SecretBox`'s existing throw.
- §5 flag/kill switch → Task 1 (dark default) + Task 3 (`assertEnabled`) + Task 5 (revoke works dark).
- §6 config → Task 3 (`secrets` config block). **Container binding deferred** to the first consumer sub-project (no `App.php` change here — `buildContainer` is private/untestable and nothing resolves the vault yet).
- §3 grace boundary (`usableVersionRows` `> now` / `pruneCandidates` `<= now`, complementary) → Tasks 4 & 5; revoke-immediately-prunable → Task 5 test 16.
- §7 evidence: 15 vault behaviors → Tasks 3–5 test methods (incl. exception-message redaction + revoke-immediate-prune); flag-dark → Task 1; schema-shape → Task 2; full suite + verify:upgrade → Tasks 2 & 6; docs → Tasks 2 & 6.

**Placeholder scan:** none — every code step shows complete code; every run step shows the command + expected output.

**Type consistency:** `SecretVault.__construct(Database, ServiceSecretRepository, SecretBox, ModerationLogRepository, FeatureFlags, Config)` is identical in the test helper (Task 3), the container binding (Task 3), and the console case (Task 5). `insertCurrentVersion(int,int,array)`, `lockSecretByRef→latest_version`, `destroyVersion(): int`, `pruneCandidates` row keys (`id`/`secret_id`/`version`) match between repo definitions and service call sites. `metadata()` keys asserted in Task 4 match the array built in Task 4.
