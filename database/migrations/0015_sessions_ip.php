<?php

declare(strict_types=1);

/**
 * 0015 · sessions — Phase 2 addition: ip (login IP, ban-evasion signal —
 * Admin-only/audited, 90-day retention purge is a Phase 3 seam). SCHEMA §7 #11.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE sessions ADD COLUMN ip VARBINARY(16) NULL AFTER csrf_secret');
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('ALTER TABLE sessions DROP COLUMN ip');
    }
};
