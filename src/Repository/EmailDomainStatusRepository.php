<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

final class EmailDomainStatusRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(string $domain): ?array
    {
        $row = $this->db->fetch('SELECT * FROM email_domain_status WHERE domain = ?', [strtolower($domain)]);
        if ($row === null) {
            return null;
        }
        $details = json_decode((string) ($row['details'] ?? 'null'), true);
        $row['details'] = is_array($details) ? $details : [];
        return $row;
    }

    /** @param array<string,mixed> $details */
    public function save(string $domain, string $selector, string $spfStatus, string $dkimStatus, array $details): void
    {
        $this->db->run(
            'INSERT INTO email_domain_status (domain, dkim_selector, spf_status, dkim_status, details, checked_at)
             VALUES (:domain, :selector, :spf, :dkim, :details, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
               dkim_selector = VALUES(dkim_selector),
               spf_status = VALUES(spf_status),
               dkim_status = VALUES(dkim_status),
               details = VALUES(details),
               checked_at = VALUES(checked_at),
               updated_at = UTC_TIMESTAMP()',
            [
                'domain' => strtolower($domain),
                'selector' => $selector,
                'spf' => $spfStatus,
                'dkim' => $dkimStatus,
                'details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        );
    }
}
