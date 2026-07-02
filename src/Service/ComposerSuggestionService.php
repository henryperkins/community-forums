<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\FeatureFlags;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Search\SearchService;
use App\Security\BoardPolicy;

final class ComposerSuggestionService
{
    public function __construct(
        private UserRepository $users,
        private BoardRepository $boards,
        private TagRepository $tags,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private SearchService $search,
        private FeatureFlags $flags,
    ) {
    }

    /**
     * @return list<ComposerSuggestion>
     */
    public function suggest(string $trigger, string $query, string $context, int $targetId, User $viewer): array
    {
        $query = $this->normalizeQuery($query, $trigger);
        if ($query === '') {
            return [];
        }

        $items = match ($trigger) {
            '@' => $this->userSuggestions($query, $context, $targetId, $viewer),
            '#' => $this->hashSuggestions($query, $viewer),
            default => [],
        };

        usort($items, static function (ComposerSuggestion $a, ComposerSuggestion $b): int {
            $rank = $b->rank <=> $a->rank;
            return $rank !== 0 ? $rank : strcasecmp($a->label, $b->label);
        });

        return array_slice($items, 0, 20);
    }

    private function normalizeQuery(string $query, string $trigger): string
    {
        $query = trim($query);
        if ($trigger !== '' && str_starts_with($query, $trigger)) {
            $query = substr($query, strlen($trigger));
        }
        return mb_substr(trim($query), 0, 80);
    }

    /** @return list<ComposerSuggestion> */
    private function userSuggestions(string $query, string $context, int $targetId, User $viewer): array
    {
        $threadId = $this->readableContextThreadId($context, $targetId, $viewer);
        $participantRanks = $threadId !== null ? $this->posts->nonAnonymousParticipantRanks($threadId) : [];
        $out = [];

        foreach ($this->users->suggestByPrefix($query, 25) as $row) {
            $username = (string) $row['username'];
            $display = trim((string) ($row['display_name'] ?? ''));
            $rank = 100 + ($participantRanks[(int) $row['id']] ?? 0);
            $out[] = new ComposerSuggestion(
                type: 'user',
                id: (int) $row['id'],
                label: '@' . $username,
                token: '@' . $username,
                url: '/u/' . $username,
                markdown: '@' . $username,
                meta: $display !== '' && $display !== $username ? $display : '',
                group: 'People',
                rank: $rank,
            );
        }

        return $out;
    }

    /** @return list<ComposerSuggestion> */
    private function hashSuggestions(string $query, User $viewer): array
    {
        $out = [];

        foreach ($this->boards->suggestByPrefix($query, 25) as $board) {
            $boardId = (int) $board['id'];
            $isMember = $this->members->isMember($boardId, $viewer->id());
            if (!$this->policy->canRead($board, $viewer, $isMember)) {
                continue;
            }
            $slug = (string) $board['slug'];
            $out[] = new ComposerSuggestion(
                type: 'board',
                id: $boardId,
                label: '#' . $slug,
                token: '#' . $slug,
                url: '/c/' . $slug,
                markdown: '[#' . $slug . '](/c/' . $slug . ')',
                meta: (string) $board['name'],
                group: 'Boards',
                rank: 200,
            );
        }

        if ($this->flags->enabled('tags')) {
            foreach ($this->tags->suggestByPrefix($query, 25) as $tag) {
                $slug = (string) $tag['slug'];
                $out[] = new ComposerSuggestion(
                    type: 'tag',
                    id: (int) $tag['id'],
                    label: '#' . $slug,
                    token: '#' . $slug,
                    url: '/tags/' . $slug,
                    markdown: '[#' . $slug . '](/tags/' . $slug . ')',
                    meta: (string) ($tag['name'] ?? ''),
                    group: 'Tags',
                    rank: 190,
                );
            }
        }

        if (mb_strlen($query) >= 3) {
            foreach ($this->search->search($query, $viewer, 20) as $result) {
                $out[] = $this->fromSearchResult($result);
            }
        }

        return $this->dedupe($out);
    }

    /** @param array<string,mixed> $result */
    private function fromSearchResult(array $result): ComposerSuggestion
    {
        $url = (string) $result['url'];
        if ((string) $result['type'] === 'post') {
            $postId = 0;
            if (preg_match('/#p(\d+)$/', $url, $m)) {
                $postId = (int) $m[1];
            }
            $title = (string) $result['title'];
            return new ComposerSuggestion(
                type: 'post',
                id: $postId,
                label: 'Post in ' . $title,
                token: '#' . $title,
                url: $url,
                markdown: '[' . $this->markdownLabel('Post in ' . $title) . '](' . $url . ')',
                meta: trim(strip_tags((string) ($result['snippet'] ?? ''))),
                group: 'Posts',
                rank: 140 + (int) floor((float) ($result['score'] ?? 0)),
            );
        }

        $title = (string) $result['title'];
        return new ComposerSuggestion(
            type: 'thread',
            id: (int) $result['thread_id'],
            label: $title,
            token: '#' . $title,
            url: $url,
            markdown: '[' . $this->markdownLabel($title) . '](' . $url . ')',
            meta: '#' . (string) $result['board_slug'],
            group: 'Topics',
            rank: 160 + (int) floor((float) ($result['score'] ?? 0)),
        );
    }

    private function readableContextThreadId(string $context, int $targetId, User $viewer): ?int
    {
        if ($targetId <= 0 || !in_array($context, ['thread', 'reply'], true)) {
            return null;
        }
        $thread = $this->threads->findWithBoard($targetId);
        if ($thread === null || (int) $thread['is_deleted'] === 1 || (int) $thread['is_pending'] === 1) {
            return null;
        }
        $boardId = (int) $thread['board_id'];
        $isMember = $this->members->isMember($boardId, $viewer->id());
        if (!$this->policy->canRead(['visibility' => $thread['board_visibility']], $viewer, $isMember)) {
            return null;
        }
        return (int) $thread['id'];
    }

    private function markdownLabel(string $label): string
    {
        return str_replace([']', '['], ['\]', '\['], $label);
    }

    /**
     * @param list<ComposerSuggestion> $items
     * @return list<ComposerSuggestion>
     */
    private function dedupe(array $items): array
    {
        $seen = [];
        $out = [];
        foreach ($items as $item) {
            $key = $item->type . ':' . $item->url;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }
}
