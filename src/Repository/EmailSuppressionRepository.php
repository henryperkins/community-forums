<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Email suppression list (P2-04). Fan-out and the worker skip any address here;
 * one-click unsubscribe and bounce/complaint handling add rows.
 */
final class EmailSuppressionRepository
{
    public function __construct(private Database $db)
    {
    }

    public function isSuppressed(string $email): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM email_suppressions WHERE email = ? LIMIT 1',
            [strtolower($email)],
        ) !== false;
    }

    public function suppress(string $email, string $reason): void
    {
        $this->db->run(
            'INSERT IGNORE INTO email_suppressions (email, reason, created_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [strtolower($email), $reason],
        );
    }

    public function unsuppress(string $email): void
    {
        $this->db->run('DELETE FROM email_suppressions WHERE email = ?', [strtolower($email)]);
    }
}
