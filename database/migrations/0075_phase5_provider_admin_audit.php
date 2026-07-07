<?php

declare(strict_types=1);

/**
 * 0075 · Phase 5 Inc 8 (P5-12) — audit target for the provider console.
 * Widens `moderation_log.target_type` with `identity_provider` so provider
 * create/enable/disable actions land in the standing audit trail (mirrors the
 * 0073 `publisher` widen). Additive; `down()` removes the rows then narrows.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher',
                                      'identity_provider') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'identity_provider'");

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher') NOT NULL
        SQL);
    }
};
