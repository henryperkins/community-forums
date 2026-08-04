<?php

declare(strict_types=1);

/**
 * 0080 · Drop the two remaining prefix-redundant secondary indexes.
 *
 * Each of these is a strict leftmost prefix of a wider index on the same table,
 * so InnoDB can answer every lookup they served from the covering index alone.
 * They only cost insert throughput, memory and storage — and in one case an
 * actively worse plan:
 *
 *  - `conversation_participants.idx_cp_user (user_id)` — added by 0025, then
 *    superseded by 0048's `idx_cp_active_user (user_id, left_at)`. Keeping both
 *    made the optimizer pick `index_merge intersect(idx_cp_user,
 *    idx_cp_active_user)` for the inbox's `WHERE user_id = ? AND left_at IS NULL`
 *    instead of a single ref lookup on the composite. `user_id` stays leftmost on
 *    `idx_cp_active_user`, so `fk_cp_user` keeps its required index.
 *  - `link_previews.idx_preview_source (source_type, source_id)` — a prefix of
 *    `uq_preview_source_url (source_type, source_id, url_hash)` created by the
 *    same migration (0058). The optimizer already preferred the unique key for
 *    `cardsForSources()`; the upsert in `LinkPreviewService::queueForBody()`
 *    resolves `ON DUPLICATE KEY` through that unique key, which is untouched.
 *
 * Both directions are guarded by information_schema so an out-of-band drop or
 * re-add (e.g. a PlanetScale schema recommendation applied from the console)
 * cannot break the run. Companion to 0079, which dropped the exact-duplicate
 * `user_profile_fields.idx_user_profile_field_user`.
 */
return new class {
    /** @var array<string,array{table:string,definition:string}> index name => where it lived */
    private const REDUNDANT = [
        'idx_cp_user' => [
            'table' => 'conversation_participants',
            'definition' => '(user_id)',
        ],
        'idx_preview_source' => [
            'table' => 'link_previews',
            'definition' => '(source_type, source_id)',
        ],
    ];

    public function up(\PDO $pdo): void
    {
        foreach (self::REDUNDANT as $index => $spec) {
            if (!$this->indexExists($pdo, $spec['table'], $index)) {
                continue;
            }
            $pdo->exec("ALTER TABLE {$spec['table']} DROP INDEX {$index}");
        }
    }

    public function down(\PDO $pdo): void
    {
        foreach (self::REDUNDANT as $index => $spec) {
            if ($this->indexExists($pdo, $spec['table'], $index)) {
                continue;
            }
            $pdo->exec("ALTER TABLE {$spec['table']} ADD KEY {$index} {$spec['definition']}");
        }
    }

    private function indexExists(\PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND INDEX_NAME = :index
        SQL);
        $stmt->execute([':table' => $table, ':index' => $index]);

        return (int) $stmt->fetchColumn() > 0;
    }
};
