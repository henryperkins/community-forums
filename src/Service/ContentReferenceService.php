<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;

final class ContentReferenceService
{
    public function __construct(
        private Database $db,
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private TagRepository $tags,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private bool $tagsEnabled,
    ) {
    }

    public function capture(string $sourceType, int $sourceId, string $body): void
    {
        if (!in_array($sourceType, ['post', 'dm_message', 'summary'], true)) {
            return;
        }
        $refs = $this->extract($body);
        $this->db->run('DELETE FROM content_references WHERE source_type = ? AND source_id = ?', [$sourceType, $sourceId]);
        foreach ($refs as $ref) {
            $resolved = $this->resolve($ref['target_type'], $ref['token']);
            $this->db->run(
                'INSERT INTO content_references
                    (source_type, source_id, target_type, target_id, token, resolved_at, unavailable, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
                [
                    $sourceType,
                    $sourceId,
                    $ref['target_type'],
                    $resolved,
                    $ref['token'],
                    $resolved !== null ? gmdate('Y-m-d H:i:s') : null,
                    $resolved === null ? 1 : 0,
                ],
            );
        }
    }

    /**
     * @param list<int> $sourceIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    public function cardsForSources(string $sourceType, array $sourceIds, ?User $viewer): array
    {
        $sourceIds = array_values(array_unique(array_filter(array_map('intval', $sourceIds), fn (int $id): bool => $id > 0)));
        if ($sourceIds === []) {
            return [];
        }
        $place = implode(',', array_fill(0, count($sourceIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT * FROM content_references
             WHERE source_type = ? AND source_id IN ($place) AND target_id IS NOT NULL AND unavailable = 0
             ORDER BY id ASC",
            array_merge([$sourceType], $sourceIds),
        );

        $out = [];
        foreach ($rows as $row) {
            $card = $this->card($row, $viewer);
            if ($card !== null) {
                $out[(int) $row['source_id']][] = $card;
            }
        }
        return $out;
    }

    /**
     * @return list<array{target_type:string,token:string}>
     */
    private function extract(string $body): array
    {
        $refs = [];
        if (preg_match_all('~(?:https?://[^\s)\]]+)?/t/(\d+)(?:-[A-Za-z0-9-]+)?(?:#p(\d+))?~', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $refs[] = !empty($m[2])
                    ? ['target_type' => 'post', 'token' => (string) $m[2]]
                    : ['target_type' => 'thread', 'token' => (string) $m[1]];
            }
        }
        if (preg_match_all('~(?:https?://[^\s)\]]+)?/c/([A-Za-z0-9-]+)~', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $refs[] = ['target_type' => 'board', 'token' => (string) $m[1]];
            }
        }
        if (preg_match_all('~(?:https?://[^\s)\]]+)?/tags/([A-Za-z0-9-]+)~', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $refs[] = ['target_type' => 'tag', 'token' => (string) $m[1]];
            }
        }

        $seen = [];
        return array_values(array_filter($refs, static function (array $ref) use (&$seen): bool {
            $key = $ref['target_type'] . ':' . $ref['token'];
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
    }

    private function resolve(string $targetType, string $token): ?int
    {
        return match ($targetType) {
            'board' => ($row = $this->boards->findBySlug($token)) !== null ? (int) $row['id'] : null,
            'thread' => ($row = $this->threads->find((int) $token)) !== null ? (int) $row['id'] : null,
            'post' => ($row = $this->posts->find((int) $token)) !== null ? (int) $row['id'] : null,
            'tag' => ($row = $this->tags->visiblePublicBySlug($token)) !== null ? (int) $row['id'] : null,
            default => null,
        };
    }

    /** @param array<string,mixed> $row @return array<string,mixed>|null */
    private function card(array $row, ?User $viewer): ?array
    {
        return match ((string) $row['target_type']) {
            'board' => $this->boardCard((int) $row['target_id'], $viewer),
            'thread' => $this->threadCard((int) $row['target_id'], $viewer),
            'post' => $this->postCard((int) $row['target_id'], $viewer),
            'tag' => $this->tagCard((int) $row['target_id']),
            default => null,
        };
    }

    /** @return array<string,mixed>|null */
    private function boardCard(int $boardId, ?User $viewer): ?array
    {
        $board = $this->boards->find($boardId);
        if ($board === null) {
            return null;
        }
        $isMember = $viewer !== null && $this->members->isMember($boardId, $viewer->id());
        if (!$this->policy->canRead($board, $viewer, $isMember)) {
            return null;
        }
        return [
            'type' => 'Board',
            'title' => (string) $board['name'],
            'url' => '/c/' . (string) $board['slug'],
            'meta' => '#' . (string) $board['slug'],
        ];
    }

    /** @return array<string,mixed>|null */
    private function threadCard(int $threadId, ?User $viewer): ?array
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1 || (int) $thread['is_pending'] === 1) {
            return null;
        }
        $isMember = $viewer !== null && $this->members->isMember((int) $thread['board_id'], $viewer->id());
        if (!$this->policy->canRead(['visibility' => $thread['board_visibility']], $viewer, $isMember)) {
            return null;
        }
        return [
            'type' => 'Thread',
            'title' => (string) $thread['title'],
            'url' => '/t/' . $threadId . '-' . (string) $thread['slug'],
            'meta' => '#' . (string) $thread['board_slug'],
        ];
    }

    /** @return array<string,mixed>|null */
    private function postCard(int $postId, ?User $viewer): ?array
    {
        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1 || (int) $post['is_pending'] === 1) {
            return null;
        }
        $isMember = $viewer !== null && $this->members->isMember((int) $post['board_id'], $viewer->id());
        if (!$this->policy->canRead(['visibility' => $post['board_visibility']], $viewer, $isMember)) {
            return null;
        }
        $excerpt = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($post['body_html'] ?? ''))) ?? '');
        return [
            'type' => 'Post',
            'title' => 'Post in ' . (string) $post['thread_slug'],
            'url' => '/t/' . (int) $post['thread_id'] . '-' . (string) $post['thread_slug'] . '#p' . $postId,
            'meta' => mb_strimwidth($excerpt, 0, 120, '...'),
        ];
    }

    /** @return array<string,mixed>|null */
    private function tagCard(int $tagId): ?array
    {
        if (!$this->tagsEnabled) {
            return null;
        }
        $tag = $this->tags->find($tagId);
        if ($tag === null || (int) ($tag['is_enabled'] ?? 0) !== 1 || (string) ($tag['visibility'] ?? '') !== 'public') {
            return null;
        }
        $description = trim((string) ($tag['description'] ?? ''));
        $count = $this->tags->publicThreadCount($tagId);

        return [
            'type' => 'Tag',
            'title' => (string) $tag['name'],
            'url' => '/tags/' . (string) $tag['slug'],
            'meta' => $description !== '' ? $description : ($count . ' visible topic' . ($count === 1 ? '' : 's')),
        ];
    }
}
