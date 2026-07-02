<?php

declare(strict_types=1);

/**
 * Phase 5 Increment 3 (P5-07-A part 1): review-enforcement and
 * security-response schema.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE publisher_signing_keys (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              publisher_id   BIGINT UNSIGNED NOT NULL,
              key_id         VARCHAR(190)    NOT NULL,
              algorithm      VARCHAR(32)     NOT NULL,
              public_key     VARBINARY(1024) NOT NULL,
              status         ENUM('active','rotated','revoked') NOT NULL DEFAULT 'active',
              valid_from     DATETIME        NULL,
              valid_until    DATETIME        NULL,
              revoked_at     DATETIME        NULL,
              revoked_reason VARCHAR(255)    NULL,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_publisher_key (publisher_id, key_id),
              CONSTRAINT fk_pubkey_publisher FOREIGN KEY (publisher_id)
                REFERENCES package_publishers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE package_review_decisions (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_id    BIGINT UNSIGNED NOT NULL,
              release_id    BIGINT UNSIGNED NULL,
              version       VARCHAR(64)     NOT NULL,
              digest        CHAR(64)        NOT NULL,
              decision      ENUM('approved','rejected','revoked') NOT NULL,
              decided_at    DATETIME        NULL,
              source        ENUM('release_document','advisory','local') NOT NULL DEFAULT 'release_document',
              evidence_json MEDIUMTEXT      NULL,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_review_decision (package_id, digest),
              CONSTRAINT fk_review_package FOREIGN KEY (package_id) REFERENCES packages(id)         ON DELETE CASCADE,
              CONSTRAINT fk_review_release FOREIGN KEY (release_id) REFERENCES package_releases(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            CREATE TABLE package_transparency_log (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              package_uid VARCHAR(190)    NOT NULL,
              version     VARCHAR(64)     NULL,
              digest      CHAR(64)        NULL,
              event       ENUM('release_verified','install','update','rollback','uninstall','quarantine','force_disable','revoked') NOT NULL,
              source      ENUM('snapshot','release_document','advisory','local') NOT NULL,
              actor_id    BIGINT UNSIGNED NULL,
              registry_id BIGINT UNSIGNED NULL,
              detail      VARCHAR(512)    NULL,
              created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_transparency_package (package_uid, created_at),
              KEY idx_transparency_digest (digest),
              CONSTRAINT fk_transparency_actor    FOREIGN KEY (actor_id)    REFERENCES users(id)              ON DELETE SET NULL,
              CONSTRAINT fk_transparency_registry FOREIGN KEY (registry_id) REFERENCES package_registries(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS package_transparency_log');
        $pdo->exec('DROP TABLE IF EXISTS package_review_decisions');
        $pdo->exec('DROP TABLE IF EXISTS publisher_signing_keys');
    }
};
