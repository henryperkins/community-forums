<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Search\SearchService;
use App\Search\SearchQuery;
use App\Security\BoardPolicy;
use App\Support\EmojiCatalog;

final class ComposerSuggestionService
{
    public function __construct(
        private UserRepository $users,
        private BoardRepository $boards,
        private TagRepository $tags,
        private ThreadReadService $threadRead,
        private PostRepository $posts,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private SearchService $search,
        private FeatureFlags $flags,
        private CustomEmojiService $customEmoji,
    ) {
    }

    /**
     * @return list<ComposerSuggestion>
     */
    public function suggest(string $trigger, string $query, string $context, int $targetId, User $viewer): array
    {
        $query = $this->normalizeQuery($query, $trigger);
        if ($query === '' && !in_array($trigger, ['@', ':'], true)) {
            return [];
        }

        $items = match ($trigger) {
            '@' => $this->userSuggestions($query, $context, $targetId, $viewer),
            '#' => $this->hashSuggestions($query, $viewer),
            ':' => $this->emojiSuggestions($query),
            default => [],
        };

        usort($items, static function (ComposerSuggestion $a, ComposerSuggestion $b): int {
            $rank = $b->rank <=> $a->rank;
            return $rank !== 0 ? $rank : strcasecmp($a->label, $b->label);
        });

        return $trigger === ':' && $query === '' ? $items : array_slice($items, 0, 20);
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
        $thread = $this->readableContextThread($context, $targetId, $viewer);
        $participantRanks = $thread !== null ? $this->posts->nonAnonymousParticipantRanks((int) $thread['id']) : [];
        $privateBoardId = $thread !== null && $thread['board_visibility'] === 'private' ? (int) $thread['board_id'] : null;
        if ($query === '' && $participantRanks === []) {
            return [];
        }
        $out = [];

        if ($context === 'dm-recipient') {
            $candidates = $this->users->suggestDmRecipients($query, $viewer->id(), $viewer->isAdmin());
        } else {
            // Fetch participant matches separately so a matching participant
            // beyond the alphabetical global cap still reaches the picker. The
            // bare-@ list offers only people a mention can notify, so it omits
            // the viewer and either-way blocks; typed queries keep ordinary
            // matches (only the DM recipient picker filters blocks).
            $candidates = $this->users->suggestByPrefix($query, 20, array_keys($participantRanks), $privateBoardId, $query === '' ? $viewer->id() : null);
            if ($query !== '') {
                $candidates = array_merge($candidates, $this->users->suggestByPrefix($query, 20, null, $privateBoardId));
            }
        }
        foreach ($candidates as $row) {
            $username = (string) $row['username'];
            $display = trim((string) ($row['display_name'] ?? ''));
            $participant = isset($participantRanks[(int) $row['id']]);
            $meta = $display !== '' && $display !== $username ? $display : '';
            if ($participant) {
                $meta = $meta !== '' ? $meta . ' · in this topic' : 'in this topic';
            }
            $avatar = trim((string) ($row['avatar_path'] ?? ''));
            $out[] = new ComposerSuggestion(
                type: 'user',
                id: (int) $row['id'],
                label: '@' . $username,
                token: '@' . $username,
                url: '/u/' . $username,
                markdown: '@' . $username,
                meta: $meta,
                group: 'People',
                rank: $participant ? 200 : 100,
                initials: monogram_initials($display !== '' ? $display : $username),
                mono: monogram_class($username),
                avatar: $avatar !== '' ? $avatar : null,
                participant: $participant,
            );
        }

        return $this->dedupe($out);
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
            foreach ($this->search->search(new SearchQuery($query), $viewer) as $result) {
                $out[] = $this->fromSearchResult($result);
            }
        }

        return $this->dedupe($out);
    }

    /** @return list<ComposerSuggestion> */
    private function emojiSuggestions(string $query): array
    {
        $catalog = EmojiCatalog::all();
        $sourceIds = [];
        foreach ($catalog as $index => $row) {
            $sourceIds[$row['shortcodes'][0]] = $index + 1;
        }

        $rows = $query === '' ? $catalog : EmojiCatalog::search($query);
        $out = [];
        foreach ($rows as $position => $row) {
            $shortcode = $row['shortcodes'][0];
            $token = ':' . $shortcode . ':';
            $out[] = new ComposerSuggestion(
                type: 'emoji',
                id: $sourceIds[$shortcode],
                label: $row['name'],
                token: $token,
                url: '',
                markdown: $row['emoji'],
                meta: $token,
                group: $row['category'],
                rank: 100_000 - $position,
            );
        }

        if (!$this->flags->enabled('custom_emoji')) {
            return $out;
        }

        $needle = mb_strtolower(trim($query, ':'));
        $customPosition = 0;
        foreach ($this->customEmoji->catalogue() as $row) {
            if ((int) $row['is_enabled'] !== 1) {
                continue;
            }
            $shortcode = (string) $row['shortcode'];
            $name = (string) $row['name'];
            if ($needle !== ''
                && !str_contains(mb_strtolower($shortcode), $needle)
                && !str_contains(mb_strtolower($name), $needle)) {
                continue;
            }
            $token = ':' . $shortcode . ':';
            $out[] = new ComposerSuggestion(
                type: 'custom_emoji',
                id: 1_000_000 + (int) hexdec(substr(hash('sha256', $shortcode), 0, 7)),
                label: $name,
                token: $token,
                url: (string) $row['image_path'],
                markdown: $token,
                meta: $token,
                group: 'Custom',
                rank: 50_000 - $customPosition,
            );
            $customPosition++;
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

    /** @return array<string,mixed>|null */
    private function readableContextThread(string $context, int $targetId, User $viewer): ?array
    {
        if ($targetId <= 0 || !in_array($context, ['thread', 'reply'], true)) {
            return null;
        }
        try {
            $thread = $this->threadRead->loadForUser($viewer, $targetId);
        } catch (NotFoundException) {
            return null;
        }
        if ((int) $thread['is_pending'] === 1) {
            return null;
        }
        return $thread;
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
            $key = $item->type . ':' . $item->token;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }
}
