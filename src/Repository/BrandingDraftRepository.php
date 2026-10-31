<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Private branding drafts (ADR 0054, increment 2b): at most one unpublished
 * branding document per administrator, with the revision it started from.
 */
final class BrandingDraftRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array{admin_id:int,document:array<string,mixed>,base_revision_id:int,created_at:string,updated_at:string}|null */
    public function find(int $adminId): ?array
    {
        $row = $this->db->fetch('SELECT * FROM branding_drafts WHERE admin_id = ?', [$adminId]);
        if ($row === null) {
            return null;
        }
        $document = json_decode((string) $row['document_json'], true);
        return [
            'admin_id' => (int) $row['admin_id'],
            'document' => is_array($document) ? $document : [],
            'base_revision_id' => (int) $row['base_revision_id'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @param array<string,mixed> $document */
    public function save(int $adminId, array $document, int $baseRevision): void
    {
        $this->db->run(
            'INSERT INTO branding_drafts (admin_id, document_json, base_revision_id, created_at, updated_at)
             VALUES (:admin, :document, :base, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE document_json = VALUES(document_json),
                 base_revision_id = VALUES(base_revision_id), updated_at = UTC_TIMESTAMP()',
            [
                'admin' => $adminId,
                'document' => json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'base' => max(0, $baseRevision),
            ],
        );
    }

    public function delete(int $adminId): void
    {
        $this->db->run('DELETE FROM branding_drafts WHERE admin_id = ?', [$adminId]);
    }
}
