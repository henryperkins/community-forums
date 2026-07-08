<?php

declare(strict_types=1);

/**
 * 0076 · Phase 5 Inc 9 (P5-13) — audit target for the invitation lifecycle.
 * Widens `moderation_log.target_type` with `invitation` so issuance/revoke/
 * redemption land in the standing audit trail (mirrors the 0075
 * `identity_provider` widen). Additive; `down()` removes the rows then narrows.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher',
                                      'identity_provider','invitation') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM moderation_log WHERE target_type = 'invitation'");

        $pdo->exec(<<<'SQL'
            ALTER TABLE moderation_log
              MODIFY target_type ENUM('thread','post','user','board','category','setting',
                                      'service_secret','api_token','webhook','registry','package','publisher',
                                      'identity_provider') NOT NULL
        SQL);
    }
};
