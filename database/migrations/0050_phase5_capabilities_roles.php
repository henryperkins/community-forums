<?php

declare(strict_types=1);

/**
 * 0050 · Phase 5 foundation — capability registry, protected roles, scoped role
 * assignments, audit, and protected-owner authority (PHASE_5_PLAN §8.2 #8/#9/#13,
 * §8.3 grp 3).
 *
 * ADDITIVE + INERT. This is the data shape for the database-backed least-privilege
 * model (P5-08/09/10). It is created deploy-dark: the live authorization path
 * keeps using `users.role` / `board_moderators` / `boards.post_min_role` until the
 * resolver is built, shadow-compared, and the `capabilities` flag is enabled
 * (§13.1 steps 2/7). `users.role` and the per-board tables are preserved in
 * parallel as the rollback/compatibility source (decision #18/#41, §8.4).
 *
 * The four built-in roles ARE seeded here as protected compatibility anchors
 * (decision #18) — they are inert reference rows; nothing resolves against them
 * yet. The capability *catalogue* is intentionally NOT seeded: the permission
 * taxonomy + non-delegable list are Milestone-0 owner-approved policy and land
 * with the resolver, not the schema.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Capability registry (§8.2 #8) ────────────────────────────────────
        // Core capability *meaning* stays code-owned; this table is the catalogue
        // of known keys. Extension capabilities are namespaced and can never
        // alias/override a protected core capability (decision #22, risk row).
        $pdo->exec(<<<'SQL'
            CREATE TABLE capabilities (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              capability_key VARCHAR(190)    NOT NULL,                  -- e.g. 'core.thread.post' (namespaced)
              namespace      VARCHAR(64)     NOT NULL DEFAULT 'core',
              scope_type     ENUM('site','category','board','self') NOT NULL,  -- broadest scope this capability applies at
              risk_class     ENUM('low','medium','high','protected') NOT NULL DEFAULT 'low',
              is_delegable   TINYINT(1)      NOT NULL DEFAULT 1,
              is_protected   TINYINT(1)      NOT NULL DEFAULT 0,        -- non-delegable protected capability
              source         ENUM('core','extension') NOT NULL DEFAULT 'core',
              source_version VARCHAR(32)     NULL,
              description    VARCHAR(255)    NULL,
              retired_at     DATETIME        NULL,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_capability_key (capability_key),
              KEY idx_capability_namespace (namespace)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Roles (§8.2 #8) ──────────────────────────────────────────────────
        // `version` bumps on any capability-mapping change so permission caches +
        // assignments key off a definite role version (decision #24).
        $pdo->exec(<<<'SQL'
            CREATE TABLE roles (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              role_key     VARCHAR(190)    NOT NULL,                    -- 'system.guest'|'system.user'|... or custom
              name         VARCHAR(190)    NOT NULL,
              kind         ENUM('system','custom') NOT NULL DEFAULT 'custom',
              is_protected TINYINT(1)      NOT NULL DEFAULT 0,          -- system roles cannot be deleted/weakened
              role_rank    INT             NOT NULL DEFAULT 0,          -- maps boards.post_min_role floor (guest<user<mod<admin); `rank` is reserved in MySQL 8
              version      INT UNSIGNED    NOT NULL DEFAULT 1,
              description  VARCHAR(255)    NULL,
              created_by   BIGINT UNSIGNED NULL,
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_role_key (role_key),
              KEY idx_role_kind (kind),
              CONSTRAINT fk_role_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Role → capability mapping (§8.2 #8) ──────────────────────────────
        $pdo->exec(<<<'SQL'
            CREATE TABLE role_capabilities (
              role_id       BIGINT UNSIGNED NOT NULL,
              capability_id BIGINT UNSIGNED NOT NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (role_id, capability_id),
              KEY idx_rolecap_capability (capability_id),
              CONSTRAINT fk_rolecap_role FOREIGN KEY (role_id)       REFERENCES roles(id)        ON DELETE CASCADE,
              CONSTRAINT fk_rolecap_cap  FOREIGN KEY (capability_id) REFERENCES capabilities(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Scoped, time-bounded assignments (§8.2 #9) ───────────────────────
        // subject_id / scope_id are polymorphic (user|group, category|board) so
        // they carry NO FK; integrity is enforced in the resolver/service. The
        // resolver enforces ends_at directly (decision #24) — expiry does not wait
        // for a cleanup job. assignment_version feeds cache invalidation.
        $pdo->exec(<<<'SQL'
            CREATE TABLE role_assignments (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              subject_type       ENUM('user','group') NOT NULL DEFAULT 'user',
              subject_id         BIGINT UNSIGNED NOT NULL,
              role_id            BIGINT UNSIGNED NOT NULL,
              scope_type         ENUM('site','category','board') NOT NULL DEFAULT 'site',
              scope_id           BIGINT UNSIGNED NULL,                  -- NULL when scope_type='site'
              grantor_id         BIGINT UNSIGNED NULL,
              reason             VARCHAR(255)    NULL,
              approval_ref       VARCHAR(190)    NULL,
              starts_at          DATETIME        NULL,
              ends_at            DATETIME        NULL,                  -- temporary grants expire here
              revoked_at         DATETIME        NULL,
              revoked_by         BIGINT UNSIGNED NULL,
              assignment_version INT UNSIGNED    NOT NULL DEFAULT 1,
              created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_assign_subject (subject_type, subject_id),
              KEY idx_assign_role (role_id),
              KEY idx_assign_scope (scope_type, scope_id),
              KEY idx_assign_expiry (ends_at),
              CONSTRAINT fk_assign_role    FOREIGN KEY (role_id)    REFERENCES roles(id) ON DELETE CASCADE,
              CONSTRAINT fk_assign_grantor FOREIGN KEY (grantor_id) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_assign_revoker FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Immutable assignment / role-change audit (§5 def-of-done) ─────────
        $pdo->exec(<<<'SQL'
            CREATE TABLE role_assignment_history (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              assignment_id BIGINT UNSIGNED NULL,
              event         ENUM('grant','renew','expire','revoke','modify','role_edit') NOT NULL,
              actor_id      BIGINT UNSIGNED NULL,
              subject_type  ENUM('user','group') NULL,
              subject_id    BIGINT UNSIGNED NULL,
              role_id       BIGINT UNSIGNED NULL,
              scope_type    ENUM('site','category','board') NULL,
              scope_id      BIGINT UNSIGNED NULL,
              before_json   TEXT            NULL,
              after_json    TEXT            NULL,
              reason        VARCHAR(255)    NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_rah_assignment (assignment_id, created_at),
              KEY idx_rah_subject (subject_type, subject_id, created_at),
              CONSTRAINT fk_rah_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Protected owner authority (§8.2 #13) ─────────────────────────────
        // The "at least one active recoverable owner" invariant is enforced
        // transactionally by code; this table makes the owner set explicit
        // without inventing a cosmetic public role.
        $pdo->exec(<<<'SQL'
            CREATE TABLE protected_owners (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id         BIGINT UNSIGNED NOT NULL,
              is_active       TINYINT(1)      NOT NULL DEFAULT 1,
              recovery_status ENUM('ok','at_risk','recovering') NOT NULL DEFAULT 'ok',
              designated_by   BIGINT UNSIGNED NULL,
              designated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_owner_user (user_id),
              KEY idx_owner_active (is_active),
              CONSTRAINT fk_owner_user      FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_owner_designator FOREIGN KEY (designated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE owner_transfer_history (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              from_user_id BIGINT UNSIGNED NULL,
              to_user_id   BIGINT UNSIGNED NULL,
              actor_id     BIGINT UNSIGNED NULL,
              reason       VARCHAR(255)    NULL,
              created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_owner_transfer (created_at),
              CONSTRAINT fk_oth_from  FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_oth_to    FOREIGN KEY (to_user_id)   REFERENCES users(id) ON DELETE SET NULL,
              CONSTRAINT fk_oth_actor FOREIGN KEY (actor_id)     REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Seed the protected built-in role anchors (decision #18) ──────────
        // Ranks map the legacy `boards.post_min_role` floor (decision #41).
        // INSERT IGNORE keeps this idempotent (pattern: 0040_seed_badges).
        $pdo->exec(<<<'SQL'
            INSERT IGNORE INTO roles (role_key, name, kind, is_protected, role_rank, description) VALUES
              ('system.guest',     'Guest',     'system', 1,  0, 'Unauthenticated visitor (compatibility anchor)'),
              ('system.user',      'User',      'system', 1, 10, 'Authenticated member (compatibility anchor)'),
              ('system.moderator', 'Moderator', 'system', 1, 20, 'Site moderator (compatibility anchor)'),
              ('system.admin',     'Admin',     'system', 1, 30, 'Administrator (compatibility anchor)')
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        foreach ([
            'owner_transfer_history',
            'protected_owners',
            'role_assignment_history',
            'role_assignments',
            'role_capabilities',
            'roles',
            'capabilities',
        ] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
};
