<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\ValidationException;

final class ServerDraftRepository
{
    private const RETENTION_DAYS = 90;
    private const QUOTA_PER_USER = 50;

    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findByContext(int $userId, string $contextKey): ?array
    {
        $this->purgeExpiredForUser($userId);
        return $this->decodeRow($this->db->fetch(
            'SELECT * FROM server_drafts
             WHERE user_id = ? AND context_key = ? AND expires_at > UTC_TIMESTAMP()
             LIMIT 1',
            [$userId, $contextKey],
        ));
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array{status:string,draft?:array<string,mixed>,server?:array<string,mixed>}
     */
    public function save(int $userId, string $contextKey, int $expectedRevision, string $title, string $body, array $metadata, ?int $expectedId = null): array
    {
        // Keep the response on the same locked write: another writer must not
        // lend its later revision to this client's earlier snapshot.
        $ownsTransaction = !$this->db->pdo()->inTransaction();
        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->db->transaction(fn (): array => $this->saveWithinTransaction($userId, $contextKey, $expectedRevision, $title, $body, $metadata, $expectedId));
            } catch (\PDOException $e) {
                // Retry only our rolled-back transaction with its original CAS.
                // An outer transaction may have other work that we cannot replay.
                if (!$ownsTransaction || $attempt >= 2 || $this->db->pdo()->inTransaction()
                    || !in_array((int) ($e->errorInfo[1] ?? 0), [1020, 1213], true)) {
                    throw $e;
                }
            }
        }
    }

    private function saveWithinTransaction(int $userId, string $contextKey, int $expectedRevision, string $title, string $body, array $metadata, ?int $expectedId): array
    {
        $this->purgeExpiredForUser($userId);
        $contextKey = $this->normalizeContextKey($contextKey);
        $title = mb_substr(trim($title), 0, 255);
        if (mb_strlen($body) > 20000) {
            throw new ValidationException(['body' => 'Draft body must be 20000 characters or fewer.']);
        }

        $existing = $this->findByContext($userId, $contextKey);
        if ($expectedId !== null && $expectedId !== (int) ($existing['id'] ?? 0)) {
            return $this->conflict($userId, $contextKey);
        }
        if ($existing !== null) {
            $currentRevision = (int) $existing['revision'];
            if ($expectedRevision !== $currentRevision) {
                return $this->conflict($userId, $contextKey);
            }
            $nextRevision = $currentRevision + 1;
            $updated = $this->db->run(
                'UPDATE server_drafts
                 SET revision = ?, title = ?, body = ?, metadata = ?, updated_at = UTC_TIMESTAMP(),
                     expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY)
                 WHERE id = ? AND revision = ?',
                [
                    $nextRevision,
                    $title !== '' ? $title : null,
                    $body,
                    $this->encodeMetadata($metadata),
                    self::RETENTION_DAYS,
                    (int) $existing['id'],
                    $expectedRevision,
                ],
            );
            if ($updated->rowCount() === 0) {
                return $this->conflict($userId, $contextKey);
            }
            return ['status' => 'saved', 'draft' => $this->findByContext($userId, $contextKey)];
        }

        if ($expectedRevision !== 0) {
            return $this->conflict($userId, $contextKey);
        }
        $count = (int) $this->db->fetchValue('SELECT COUNT(*) FROM server_drafts WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()', [$userId]);
        if ($count >= self::QUOTA_PER_USER) {
            throw new ValidationException(['drafts' => 'You can keep up to 50 server drafts. Discard one before saving another.']);
        }

        try {
            $id = $this->db->insert(
                'INSERT INTO server_drafts
                   (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
                 VALUES (?, ?, 1, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY))',
                [$userId, $contextKey, $title !== '' ? $title : null, $body, $this->encodeMetadata($metadata), self::RETENTION_DAYS],
            );
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
            return $this->conflict($userId, $contextKey);
        }

        return ['status' => 'saved', 'draft' => $this->find((int) $id)];
    }

    /** Current read: a failed CAS must not reuse the transaction's older snapshot. */
    private function conflict(int $userId, string $contextKey): array
    {
        $server = $this->decodeRow($this->db->fetch(
            'SELECT * FROM server_drafts WHERE user_id = ? AND context_key = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE',
            [$userId, $contextKey],
        ));
        return ['status' => 'conflict', 'server' => $server];
    }

    public function discardByContext(int $userId, string $contextKey): bool
    {
        return $this->db->run(
            'DELETE FROM server_drafts WHERE user_id = ? AND context_key = ?',
            [$userId, $this->normalizeContextKey($contextKey)],
        )->rowCount() > 0;
    }

    /** Empty autosave may only remove the revision it actually loaded. */
    public function discardAtRevision(int $userId, string $contextKey, int $revision, int $draftId): array
    {
        $contextKey = $this->normalizeContextKey($contextKey);
        $this->db->run('DELETE FROM server_drafts WHERE user_id = ? AND context_key = ? AND revision = ? AND id = ?', [$userId, $contextKey, $revision, $draftId]);
        $server = $this->findByContext($userId, $contextKey);
        return $server === null ? ['status' => 'discarded'] : ['status' => 'conflict', 'server' => $server];
    }

    /** A successful send must not discard a newer edit from another tab. */
    public function discardSubmitted(int $userId, string $contextKey, string $body, ?string $title = null, ?array $input = null): void
    {
        $draft = $this->findByContext($userId, $this->normalizeContextKey($contextKey));
        if ($draft === null || !\App\Support\ComposerDraft::matchesSubmission($draft, $input ?? [])) { return; }
        $sql = 'DELETE FROM server_drafts WHERE user_id = ? AND context_key = ? AND BINARY body = BINARY ? AND revision = ? AND id = ?';
        $params = [$userId, $contextKey, $body, (int) $draft['revision'], (int) $draft['id']];
        // Versioned drafts without a title field use title as a list label
        // (for example "New direct message"). A hidden, empty group-title
        // input must not prevent completion of that otherwise exact copy.
        $metadata = is_array($draft['metadata'] ?? null) ? $draft['metadata'] : [];
        if ($title !== null && (!isset($metadata['state']) || array_key_exists('title', $metadata['state']['fields']))) {
            $sql .= ' AND BINARY COALESCE(title, \'\') = BINARY ?';
            $params[] = trim($title);
        }
        $this->db->run($sql, $params);
    }

    public function discardById(int $userId, int $id): bool
    {
        return $this->db->run('DELETE FROM server_drafts WHERE user_id = ? AND id = ?', [$userId, $id])->rowCount() > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function listForUser(int $userId): array
    {
        $this->purgeExpiredForUser($userId);
        return array_values(array_filter(array_map(
            fn (array $row): ?array => $this->decodeRow($row),
            $this->db->fetchAll(
                'SELECT * FROM server_drafts
                 WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 50',
                [$userId],
            ),
        )));
    }

    /** @return array<int,array<string,mixed>> */
    public function exportForUser(int $userId): array
    {
        return array_map(
            static fn (array $row): array => [
                'context_key' => (string) $row['context_key'],
                'revision' => (int) $row['revision'],
                'title' => $row['title'] !== null ? (string) $row['title'] : null,
                'body' => (string) $row['body'],
                'metadata' => $row['metadata'],
                'updated_at' => (string) $row['updated_at'],
                'expires_at' => (string) $row['expires_at'],
            ],
            $this->listForUser($userId),
        );
    }

    public function purgeForUser(int $userId): int
    {
        return $this->db->run('DELETE FROM server_drafts WHERE user_id = ?', [$userId])->rowCount();
    }

    public function purgeExpired(): int
    {
        return $this->db->run('DELETE FROM server_drafts WHERE expires_at <= UTC_TIMESTAMP()')->rowCount();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->decodeRow($this->db->fetch('SELECT * FROM server_drafts WHERE id = ?', [$id]));
    }

    public function findForUser(int $id, int $userId): ?array
    {
        return $this->decodeRow($this->db->fetch('SELECT * FROM server_drafts WHERE id = ? AND user_id = ? AND expires_at > UTC_TIMESTAMP()', [$id, $userId]));
    }

    private function purgeExpiredForUser(int $userId): int
    {
        return $this->db->run('DELETE FROM server_drafts WHERE user_id = ? AND expires_at <= UTC_TIMESTAMP()', [$userId])->rowCount();
    }

    private function normalizeContextKey(string $contextKey): string
    {
        $contextKey = trim($contextKey);
        if ($contextKey === '' || mb_strlen($contextKey) > 191 || str_contains($contextKey, '/')) {
            throw new ValidationException(['context_key' => 'Draft context is invalid.']);
        }
        return $contextKey;
    }

    /** @param array<string,mixed> $metadata */
    private function encodeMetadata(array $metadata): string
    {
        return json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /** @param array<string,mixed>|false|null $row @return array<string,mixed>|null */
    private function decodeRow(array|false|null $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }
        $raw = $row['metadata'] ?? null;
        $row['metadata'] = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: new \stdClass()) : new \stdClass();
        return $row;
    }
}
