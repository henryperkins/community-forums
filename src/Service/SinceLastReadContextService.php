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
        $cursor = $this->db->fetch(
            'SELECT cursor_post.id, cursor_post.created_at
             FROM thread_user tu
             JOIN posts cursor_post
               ON cursor_post.id = tu.last_read_post_id
              AND cursor_post.thread_id = tu.thread_id
              AND cursor_post.is_deleted = 0
              AND cursor_post.is_pending = 0
             WHERE tu.user_id = ? AND tu.thread_id = ?',
            [$userId, $threadId],
        );
        if ($cursor === null) {
            return null;
        }
        $fromPostId = (int) $cursor['id'];
        $fromCreatedAt = (string) $cursor['created_at'];

        $limit = max(1, min(20, $limit));
        $posts = $this->db->fetchAll(
            'SELECT p.id, p.body, p.body_html, p.created_at, p.is_anonymous,
                    u.username AS author_username, u.display_name AS author_display_name,
                    u.role AS author_role,
                    COUNT(*) OVER () AS unread_post_count,
                    FIRST_VALUE(p.id) OVER (ORDER BY p.created_at DESC, p.id DESC) AS unread_to_post_id
             FROM posts p
             JOIN users u ON u.id = p.user_id
             WHERE p.thread_id = ?
               AND p.is_deleted = 0
               AND p.is_pending = 0
               AND (p.created_at > ? OR (p.created_at = ? AND p.id > ?))
             ORDER BY p.created_at ASC, p.id ASC
             LIMIT ' . $limit,
            [$threadId, $fromCreatedAt, $fromCreatedAt, $fromPostId],
        );
        if ($posts === []) {
            return null;
        }
        $postCount = (int) $posts[0]['unread_post_count'];

        $items = [];
        foreach ($posts as $post) {
            $isAnonymous = (int) ($post['is_anonymous'] ?? 0) === 1;
            $author = \mask_author(
                $post['author_display_name'] ?? null,
                $post['author_username'] ?? null,
                $post['author_role'] ?? 'user',
                $isAnonymous,
            );
            $items[] = [
                'post_id' => (int) $post['id'],
                'author' => $author['label'],
                'author_is_anonymous' => $isAnonymous,
                'excerpt' => $this->excerpt((string) (($post['body_html'] ?? '') !== '' ? $post['body_html'] : $post['body'])),
            ];
        }

        $toPostId = (int) $posts[0]['unread_to_post_id'];
        $contextText = implode("\n", array_map(
            static fn (array $item): string => ($item['author_is_anonymous'] ? '' : '@') . $item['author'] . ': ' . $item['excerpt'],
            $items,
        ));

        $this->db->run(
            'INSERT INTO since_last_read_context
                (user_id, thread_id, from_post_id, to_post_id, post_count, context_text, generated_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 14 DAY))
             ON DUPLICATE KEY UPDATE
                from_post_id = VALUES(from_post_id),
                to_post_id = VALUES(to_post_id),
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
