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

    /**
     * Suppression list for the admin dashboard. LIMIT/OFFSET clamped + concatenated.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(int $limit, int $offset, ?string $reason = null): array
    {
        $limit = max(1, min(10000, $limit));
        $offset = max(0, $offset);
        $where = '';
        $params = [];
        if ($reason !== null && $reason !== '') {
            $where = ' WHERE reason = :reason';
            $params['reason'] = $reason;
        }
        return $this->db->fetchAll(
            'SELECT email, reason, created_at FROM email_suppressions' . $where
            . ' ORDER BY created_at DESC, email ASC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        );
    }

    public function count(?string $reason = null): int
    {
        $where = '';
        $params = [];
        if ($reason !== null && $reason !== '') {
            $where = ' WHERE reason = :reason';
            $params['reason'] = $reason;
        }
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM email_suppressions' . $where, $params);
    }
}
