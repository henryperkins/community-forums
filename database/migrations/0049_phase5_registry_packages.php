<?php

declare(strict_types=1);

/**
 * 0049 · Phase 5 foundation — signed registry, packages, releases, installs,
 * permissions, history, and advisories (PHASE_5_PLAN §8.2 #1–5, §8.3 grp 1).
 *
 * ADDITIVE + INERT. These tables back the public package ecosystem
 * (P5-01/02/04/07). They are created deploy-dark: no application code reads or
 * writes them while the `package_registry` / `package_themes` flags are off, and
 * the Milestone-0 registry trust roots, signing-key custody, and review policy
 * are NOT encoded here (they remain owner-approved policy, with private keys kept
 * out of the application database per §8.2 #1).
 *
 * Registry *metadata* (registries/keys/publishers/packages/releases/advisories)
 * is kept deliberately separate from local *installation* state
 * (installed_packages/permissions/history) so a registry rollback never rewrites
 * the recorded digest of an installed release (§13.2).
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Registry sources + trust roots (§8.2 #1) ─────────────────────────
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_registries (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              source_id            VARCHAR(190)    NOT NULL,            -- canonical, globally-namespaced registry id
              display_name         VARCHAR(190)    NOT NULL,
              base_url             VARCHAR(512)    NOT NULL,
              is_enabled           TINYINT(1)      NOT NULL DEFAULT 0,  -- deploy-dark; refresh disabled until approved
              last_snapshot_digest CHAR(64)        NULL,                -- sha256 hex of last verified catalogue snapshot
              last_snapshot_at     DATETIME        NULL,
              snapshot_expires_at  DATETIME        NULL,                -- freshness/expiry window
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_registry_source (source_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // Trust roots / signing keys (PUBLIC key material only — private trust-root
        // keys never belong in the application database, §8.2 #1). Supports key
        // rotation + revocation with validity windows.
        $pdo->exec(<<<'SQL'
            CREATE TABLE registry_trust_keys (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              registry_id   BIGINT UNSIGNED NOT NULL,
              key_id        VARCHAR(190)    NOT NULL,                   -- publisher/registry key version identifier
              algorithm     VARCHAR(32)     NOT NULL,                   -- e.g. 'ed25519'
              public_key    VARBINARY(1024) NOT NULL,                  -- PUBLIC key bytes only
              status        ENUM('active','rotated','revoked') NOT NULL DEFAULT 'active',
              valid_from    DATETIME        NULL,
              valid_until   DATETIME        NULL,
              revoked_at    DATETIME        NULL,
              revoked_reason VARCHAR(255)   NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_trustkey (registry_id, key_id),
              KEY idx_trustkey_status (registry_id, status),
              CONSTRAINT fk_trustkey_registry FOREIGN KEY (registry_id) REFERENCES package_registries(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Publishers (§8.2 #2/#5) ──────────────────────────────────────────
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_publishers (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              publisher_uid VARCHAR(190)    NOT NULL,                   -- globally-namespaced publisher identity
              display_name  VARCHAR(190)    NOT NULL,
              verified_at   DATETIME        NULL,
              status        ENUM('active','suspended','revoked') NOT NULL DEFAULT 'active',
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_publisher_uid (publisher_uid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Packages = registry identity (§8.2 #2) ───────────────────────────
        // Package identity is globally namespaced; trust class can never be
        // implied merely by being installable (§4 def-of-done). `registry_id`
        // NULL marks a local/first-party package not sourced from a public
        // registry. `latest_release_id` is a denormalised pointer (no FK to avoid
        // a circular dependency with releases; reconciled by RepairService later).
        $pdo->exec(<<<'SQL'
            CREATE TABLE packages (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_uid      VARCHAR(190)    NOT NULL,                -- globally-namespaced package identity
              registry_id      BIGINT UNSIGNED NULL,
              publisher_id     BIGINT UNSIGNED NULL,
              name             VARCHAR(190)    NOT NULL,
              type             ENUM('theme','automation','remote_app','server_extension','local') NOT NULL,
              trust_class      ENUM('first_party','vetted','reviewed_declarative','reviewed_remote','isolated_server','local_dev') NOT NULL,
              advisory_status  ENUM('none','warned','blocked','revoked') NOT NULL DEFAULT 'none',
              latest_release_id BIGINT UNSIGNED NULL,
              created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_package_uid (package_uid),
              KEY idx_package_registry (registry_id),
              KEY idx_package_publisher (publisher_id),
              KEY idx_package_latest (latest_release_id),
              CONSTRAINT fk_package_registry  FOREIGN KEY (registry_id)  REFERENCES package_registries(id)  ON DELETE SET NULL,
              CONSTRAINT fk_package_publisher FOREIGN KEY (publisher_id) REFERENCES package_publishers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Immutable releases (§8.2 #2; §8.5) ───────────────────────────────
        // One release = one exact (version, digest, manifest, signature). Review
        // approval binds to an exact digest (decision #16): any byte change is a
        // new release, never an in-place replacement.
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_releases (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_id      BIGINT UNSIGNED NOT NULL,
              version         VARCHAR(64)     NOT NULL,
              digest          CHAR(64)        NOT NULL,                 -- sha256 hex content digest (immutable identity)
              source_url      VARCHAR(512)    NULL,                     -- pinned artifact source
              license         VARCHAR(190)    NULL,
              core_min        VARCHAR(32)     NULL,                     -- compatibility range (semver-ish)
              core_max        VARCHAR(32)     NULL,
              manifest_json   MEDIUMTEXT      NULL,                     -- manifest v2 (validated at install)
              dependency_json MEDIUMTEXT      NULL,                     -- locked dependency inventory / SBOM
              signature       VARBINARY(1024) NULL,                    -- signed release metadata
              signed_key_id   VARCHAR(190)    NULL,                     -- registry_trust_keys.key_id used
              review_status   ENUM('unreviewed','submitted','approved','rejected','revoked') NOT NULL DEFAULT 'unreviewed',
              channel         ENUM('stable','beta','dev') NOT NULL DEFAULT 'stable',
              advisory_status ENUM('none','warned','blocked','revoked') NOT NULL DEFAULT 'none',
              published_at    DATETIME        NULL,
              created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_release_version (package_id, version),
              KEY idx_release_digest (digest),
              KEY idx_release_review (review_status),
              CONSTRAINT fk_release_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Local installation state (§8.2 #2/#3) ────────────────────────────
        // Records the full provenance every installed package must carry
        // (§4 def-of-done). One install per package.
        $pdo->exec(<<<'SQL'
            CREATE TABLE installed_packages (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_id         BIGINT UNSIGNED NOT NULL,
              release_id         BIGINT UNSIGNED NULL,                  -- currently active release
              digest             CHAR(64)        NOT NULL,             -- installed bytes digest
              source_registry_id BIGINT UNSIGNED NULL,
              publisher_id       BIGINT UNSIGNED NULL,
              trust_class        ENUM('first_party','vetted','reviewed_declarative','reviewed_remote','isolated_server','local_dev') NOT NULL,
              review_status      ENUM('unreviewed','submitted','approved','rejected','revoked') NOT NULL DEFAULT 'unreviewed',
              state              ENUM('installed','enabled','disabled','quarantined','uninstalling') NOT NULL DEFAULT 'installed',
              health             ENUM('unknown','ok','degraded','failed') NOT NULL DEFAULT 'unknown',
              compat_min         VARCHAR(32)     NULL,
              compat_max         VARCHAR(32)     NULL,
              installed_by       BIGINT UNSIGNED NULL,
              installed_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_installed_package (package_id),
              KEY idx_installed_state (state),
              KEY idx_installed_registry (source_registry_id),
              CONSTRAINT fk_installed_package   FOREIGN KEY (package_id)         REFERENCES packages(id)            ON DELETE CASCADE,
              CONSTRAINT fk_installed_release   FOREIGN KEY (release_id)         REFERENCES package_releases(id)    ON DELETE SET NULL,
              CONSTRAINT fk_installed_registry  FOREIGN KEY (source_registry_id) REFERENCES package_registries(id)  ON DELETE SET NULL,
              CONSTRAINT fk_installed_publisher FOREIGN KEY (publisher_id)       REFERENCES package_publishers(id)  ON DELETE SET NULL,
              CONSTRAINT fk_installed_actor     FOREIGN KEY (installed_by)       REFERENCES users(id)               ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Normalised declared vs granted permissions (§8.2 #4) ─────────────
        // `declared` is the manifest ceiling; `granted` is the actual local
        // authority. An update preserves the exact prior grant until re-consent
        // (decision #7, §8.5). Permission *reduction* takes effect on activation.
        $pdo->exec(<<<'SQL'
            CREATE TABLE installed_package_permissions (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              installed_package_id BIGINT UNSIGNED NOT NULL,
              kind                 ENUM('capability','data_class','outbound_host','job','broker_service') NOT NULL,
              permission_key       VARCHAR(190)    NOT NULL,
              risk_class           ENUM('low','medium','high') NOT NULL DEFAULT 'low',
              declared             TINYINT(1)      NOT NULL DEFAULT 1,
              granted              TINYINT(1)      NOT NULL DEFAULT 0,
              granted_at           DATETIME        NULL,
              granted_by           BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_install_perm (installed_package_id, kind, permission_key),
              CONSTRAINT fk_perm_install FOREIGN KEY (installed_package_id) REFERENCES installed_packages(id) ON DELETE CASCADE,
              CONSTRAINT fk_perm_grantor FOREIGN KEY (granted_by)           REFERENCES users(id)              ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Immutable install/update history (§8.2 #3) ───────────────────────
        // installed_package_id is kept WITHOUT an FK so history survives uninstall
        // (§8.5 retention). package_id keeps an FK with SET NULL.
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_history (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_id           BIGINT UNSIGNED NULL,
              installed_package_id BIGINT UNSIGNED NULL,
              event                ENUM('install','update','pin','unpin','rollback','enable','disable','quarantine','uninstall','consent','health') NOT NULL,
              actor_id             BIGINT UNSIGNED NULL,
              prior_version        VARCHAR(64)     NULL,
              new_version          VARCHAR(64)     NULL,
              prior_digest         CHAR(64)        NULL,
              new_digest           CHAR(64)        NULL,
              permission_snapshot_json MEDIUMTEXT  NULL,
              approval_ref         VARCHAR(190)    NULL,
              failure_stage        VARCHAR(190)    NULL,                -- exact stage a failed install/update stopped at
              detail               VARCHAR(512)    NULL,
              created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_pkg_history (package_id, created_at),
              KEY idx_pkg_history_install (installed_package_id, created_at),
              CONSTRAINT fk_history_package FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
              CONSTRAINT fk_history_actor   FOREIGN KEY (actor_id)   REFERENCES users(id)    ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Advisories (§8.2 #5) ─────────────────────────────────────────────
        // The local install caches the signed evidence it relied on. Actions
        // escalate warn → block_new → force_disable → revoke (§3 def-of-done).
        $pdo->exec(<<<'SQL'
            CREATE TABLE package_advisories (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              advisory_uid          VARCHAR(190)    NOT NULL,
              registry_id           BIGINT UNSIGNED NULL,
              package_id            BIGINT UNSIGNED NULL,
              affected_version_range VARCHAR(190)   NULL,
              affected_digest       CHAR(64)        NULL,
              severity              ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
              action                ENUM('warn','block_new','force_disable','revoke') NOT NULL DEFAULT 'warn',
              summary               VARCHAR(512)    NULL,
              signed_evidence       MEDIUMTEXT      NULL,               -- cached signed advisory payload
              issued_at             DATETIME        NULL,
              acknowledged_at       DATETIME        NULL,
              acknowledged_by       BIGINT UNSIGNED NULL,
              created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_advisory_uid (advisory_uid),
              KEY idx_advisory_package (package_id),
              CONSTRAINT fk_advisory_registry FOREIGN KEY (registry_id)     REFERENCES package_registries(id) ON DELETE SET NULL,
              CONSTRAINT fk_advisory_package  FOREIGN KEY (package_id)      REFERENCES packages(id)           ON DELETE SET NULL,
              CONSTRAINT fk_advisory_ackuser  FOREIGN KEY (acknowledged_by) REFERENCES users(id)              ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        // ── Registry-independent local emergency blocklist (§3 def-of-done) ──
        // A locally-blocked digest/package cannot be newly enabled regardless of
        // registry availability. Independent of trust-root state.
        $pdo->exec(<<<'SQL'
            CREATE TABLE local_package_blocks (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              digest      CHAR(64)        NULL,                         -- block one exact release...
              package_uid VARCHAR(190)    NULL,                         -- ...or an entire package identity
              reason      VARCHAR(255)    NULL,
              created_by  BIGINT UNSIGNED NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_block_digest (digest),
              KEY idx_block_package (package_uid),
              CONSTRAINT fk_block_actor FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        // Drop children before parents (FKs only reference within this group +
        // the external users table, which is left intact).
        foreach ([
            'local_package_blocks',
            'package_advisories',
            'package_history',
            'installed_package_permissions',
            'installed_packages',
            'package_releases',
            'packages',
            'package_publishers',
            'registry_trust_keys',
            'package_registries',
        ] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . $table);
        }
    }
};
