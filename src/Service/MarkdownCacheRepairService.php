<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Support\MarkdownRenderer;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Rebuilds derived Markdown HTML without changing canonical bodies or edit metadata. */
final class MarkdownCacheRepairService
{
    /** @var array<string,array{mentions:bool}> */
    private const CACHES = [
        'posts' => ['mentions' => true],
        'dm_messages' => ['mentions' => true],
        'thread_summaries' => ['mentions' => false],
        'post_revisions' => ['mentions' => true],
    ];

    public function __construct(private Database $db, private MarkdownRenderer $markdown)
    {
    }

    /**
     * @return array<string,array{scanned:int,changed:int}>
     */
    public function rebuild(int $batchSize = 500, bool $dryRun = false): array
    {
        if ($batchSize < 1 || $batchSize > 5000) {
            throw new InvalidArgumentException('Render-cache batch size must be between 1 and 5000.');
        }

        $stats = [];
        foreach (self::CACHES as $table => $settings) {
            $stats[$table] = $this->rebuildTable($table, $settings['mentions'], $batchSize, $dryRun);
        }
        return $stats;
    }

    /** @return array{scanned:int,changed:int} */
    private function rebuildTable(string $table, bool $linkMentions, int $batchSize, bool $dryRun): array
    {
        $scanned = 0;
        $changed = 0;
        $afterId = 0;

        while (true) {
            $rows = $this->db->fetchAll(
                "SELECT id, body, body_html FROM {$table} WHERE id > ? ORDER BY id ASC LIMIT {$batchSize}",
                [$afterId],
            );
            if ($rows === []) {
                break;
            }

            $updates = [];
            foreach ($rows as $row) {
                $afterId = (int) $row['id'];
                $scanned++;
                $body = (string) $row['body'];
                $oldHtml = (string) ($row['body_html'] ?? '');
                try {
                    $newHtml = $this->markdown->render($body, ['link_mentions' => $linkMentions]);
                } catch (Throwable $exception) {
                    throw $this->failure($table, (int) $row['id'], $exception);
                }
                if ($newHtml !== $oldHtml) {
                    $updates[] = [
                        'id' => (int) $row['id'],
                        'body' => $body,
                        'old_html' => $oldHtml,
                        'new_html' => $newHtml,
                    ];
                }
            }

            if ($dryRun) {
                $changed += count($updates);
                continue;
            }
            if ($updates === []) {
                continue;
            }

            $changed += $this->db->transaction(function () use ($table, $updates): int {
                $written = 0;
                foreach ($updates as $update) {
                    try {
                        $written += $this->db->run(
                            "UPDATE {$table} SET body_html = ?
                             WHERE id = ? AND BINARY body = BINARY ? AND BINARY COALESCE(body_html, '') = BINARY ?",
                            [$update['new_html'], $update['id'], $update['body'], $update['old_html']],
                        )->rowCount();
                    } catch (Throwable $exception) {
                        throw $this->failure($table, (int) $update['id'], $exception);
                    }
                }
                return $written;
            });
        }

        return ['scanned' => $scanned, 'changed' => $changed];
    }

    private function failure(string $table, int $rowId, Throwable $exception): RuntimeException
    {
        return new RuntimeException(
            "Render-cache rebuild failed at {$table} row {$rowId}: {$exception->getMessage()}",
            0,
            $exception,
        );
    }
}
