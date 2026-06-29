<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;

final class SinceLastReadContextService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function forThread(int $userId, int $threadId, int $limit = 6): ?array
    {
        $lastRead = $this->db->fetchValue(
            'SELECT last_read_post_id FROM thread_user WHERE user_id = ? AND thread_id = ?',
            [$userId, $threadId],
        );
        $fromPostId = $lastRead !== false && $lastRead !== null ? (int) $lastRead : 0;
        if ($fromPostId <= 0) {
            return null;
        }

        $limit = max(1, min(20, $limit));
        $window = $this->db->fetch(
            'SELECT COUNT(*) AS post_count, MAX(id) AS to_post_id
             FROM posts
             WHERE thread_id = ? AND id > ? AND is_deleted = 0 AND is_pending = 0',
            [$threadId, $fromPostId],
        );
        $postCount = (int) ($window['post_count'] ?? 0);
        if ($postCount <= 0) {
            return null;
        }

        $posts = $this->db->fetchAll(
            'SELECT p.id, p.body, p.body_html, p.created_at,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = ? AND p.id > ? AND p.is_deleted = 0 AND p.is_pending = 0
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT ' . $limit,
            [$threadId, $fromPostId],
        );

        $items = [];
        foreach ($posts as $post) {
            $author = (string) (($post['author_display_name'] ?? '') !== '' ? $post['author_display_name'] : $post['author_username']);
            $items[] = [
                'post_id' => (int) $post['id'],
                'author' => $author,
                'excerpt' => $this->excerpt((string) (($post['body_html'] ?? '') !== '' ? $post['body_html'] : $post['body'])),
            ];
        }

        $toPostId = (int) ($window['to_post_id'] ?? 0);
        $contextText = implode("\n", array_map(
            static fn (array $item): string => '@' . $item['author'] . ': ' . $item['excerpt'],
            $items,
        ));

        $this->db->run(
            'INSERT INTO since_last_read_context
                (user_id, thread_id, from_post_id, to_post_id, post_count, context_text, generated_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 14 DAY))
             ON DUPLICATE KEY UPDATE
                post_count = VALUES(post_count),
                context_text = VALUES(context_text),
                generated_at = UTC_TIMESTAMP(),
                expires_at = VALUES(expires_at)',
            [$userId, $threadId, $fromPostId, $toPostId, $postCount, $contextText],
        );

        return [
            'from_post_id' => $fromPostId,
            'to_post_id' => $toPostId,
            'post_count' => $postCount,
            'context_text' => $contextText,
            'items' => $items,
        ];
    }

    private function excerpt(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        return mb_strimwidth($text, 0, 180, '...');
    }
}
