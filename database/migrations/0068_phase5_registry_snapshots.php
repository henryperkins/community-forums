<?php

declare(strict_types=1);

/**
 * 0068 - Phase 5 Increment 2 (P5-01 SP4): verified-snapshot offline cache +
 * moderation_log target widen for registry/package audit rows.
 *
 * ADDITIVE. `registry_snapshots` caches verified signed catalogue snapshots per
 * registry: exact document bytes + detached signature survive registry outage,
 * and generated_at history is the anti-replay watermark. Only documents that
 * passed TrustChainVerifier are ever written here.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE registry_snapshots (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              registry_id  BIGINT UNSIGNED NOT NULL,
              digest       CHAR(64)        NOT NULL,             -- sha256 hex of the exact signed document bytes
              document     MEDIUMTEXT      NOT NULL,             -- offline cache of the verified snapshot JSON
              signature    VARBINARY(1024) NOT NULL,             -- detached ed25519 signature over `document`
              key_id       VARCHAR(190)    NOT NULL,             -- registry_trust_keys.key_id that verified it
              generated_at DATETIME        NOT NULL,             -- doc-declared; anti-replay watermark
              expires_at   DATETIME        NOT NULL,             -- doc-declared freshness window
              applied_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_snapshot_digest (registry_id, digest),
              KEY idx_snapshot_generated (registry_id, generated_at),
              CONSTRAINT fk_snapshot_registry FOREIGN KEY (registry_id) REFERENCES package_registries(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook','registry','package') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type IN ('registry','package')");
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting','service_secret','api_token','webhook') NOT NULL
        SQL);
        $pdo->exec('DROP TABLE IF EXISTS registry_snapshots');
    }
};
