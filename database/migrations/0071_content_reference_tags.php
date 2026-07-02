<?php

declare(strict_types=1);

/**
 * 0071 - Allow content reference cards for public tags.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post','tag') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM content_references WHERE target_type = 'tag'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post') NOT NULL
        SQL);
    }
};
