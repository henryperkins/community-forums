<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;

final class NavigationService
{
    /**
     * @var array<string,array{
     *     categories:array<int,array<string,mixed>>,
     *     boards:array<int,array<string,mixed>>,
     *     member_ids:array<int,int>
     * }>
     */
    private array $snapshots = [];

    public function __construct(
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private ThreadRepository $threads,
    ) {
    }

    /** @return array<int,array{category:array<string,mixed>,boards:array<int,array<string,mixed>>}> */
    public function sidebar(?User $user, array $unreadCounts = []): array
    {
        $snapshot = $this->snapshot($user);
        $nav = [];
        foreach ($snapshot['categories'] as $category) {
            $boards = array_values(array_filter(
                $snapshot['boards'],
                fn (array $board): bool => (int) $board['category_id'] === (int) $category['id']
                    && $this->policy->isListed(
                        $board,
                        $user,
                        isset($snapshot['member_ids'][(int) $board['id']]),
                    ),
            ));
            foreach ($boards as &$board) {
                $board['unread_count'] = (int) ($unreadCounts[(int) $board['id']] ?? 0);
            }
            unset($board);
            if ($boards !== []) {
                $nav[] = ['category' => $category, 'boards' => $boards];
            }
        }
        return $nav;
    }

    /** @return array<int,array{category:array<string,mixed>,boards:array<int,array<string,mixed>>}> */
    public function homeSections(?User $user): array
    {
        $snapshot = $this->snapshot($user);
        $sections = [];
        foreach ($snapshot['categories'] as $category) {
            $boards = array_values(array_filter(
                $snapshot['boards'],
                fn (array $board): bool => (int) $board['category_id'] === (int) $category['id']
                    && $this->policy->isListed(
                        $board,
                        $user,
                        isset($snapshot['member_ids'][(int) $board['id']]),
                    ),
            ));
            $sections[] = ['category' => $category, 'boards' => $boards];
        }
        return $sections;
    }

    /**
     * Place-oriented Board Index groups. Category order preserves the operator's
     * taxonomy; every other order dissolves the categories into one public rank.
     * Personal unread/star/mute state never enters this model.
     *
     * @return array<int,array{
     *     category:array<string,mixed>|null,
     *     show_heading:bool,
     *     boards:list<array<string,mixed>>
     * }>
     */
    public function directory(?User $user, string $sort, int $peek): array
    {
        $sections = $this->homeSections($user);
        $boardIds = [];
        foreach ($sections as $section) {
            foreach ($section['boards'] as $board) {
                $boardIds[] = (int) $board['id'];
            }
        }
        $signals = $this->threads->directorySignals($boardIds, $sort, $peek);

        foreach ($sections as &$section) {
            foreach ($section['boards'] as &$board) {
                $board += $signals[(int) $board['id']] ?? $this->emptyDirectorySignal();
            }
            unset($board);
            $section['show_heading'] = true;
        }
        unset($section);

        if ($sort === 'category') {
            return $sections;
        }

        $ranked = [];
        foreach ($sections as $section) {
            foreach ($section['boards'] as $board) {
                $ranked[] = $board;
            }
        }
        usort($ranked, fn (array $left, array $right): int => $this->compareDirectoryBoards($left, $right, $sort));

        return [[
            'category' => null,
            'show_heading' => false,
            'boards' => $ranked,
        ]];
    }

    /** @return array<string,mixed> */
    private function emptyDirectorySignal(): array
    {
        return [
            'latest_activity_at' => null,
            'newest_thread_at' => null,
            'unanswered_count' => 0,
            'top_commend_count' => 0,
            'settled_count' => 0,
            'latest_settled_at' => null,
            'topics' => [],
        ];
    }

    /**
     * Deterministic public ranks. The operator's category/position order is the
     * final tie-break so equal signals never jump around between requests.
     *
     * @param array<string,mixed> $left
     * @param array<string,mixed> $right
     */
    private function compareDirectoryBoards(array $left, array $right, string $sort): int
    {
        $comparison = match ($sort) {
            'unanswered' => ((int) $right['unanswered_count']) <=> ((int) $left['unanswered_count']),
            'top' => ((int) $right['top_commend_count']) <=> ((int) $left['top_commend_count']),
            'solved' => ((int) $right['settled_count']) <=> ((int) $left['settled_count']),
            'newest' => $this->compareLatest($left['newest_thread_at'] ?? null, $right['newest_thread_at'] ?? null),
            default => $this->compareLatest($left['latest_activity_at'] ?? null, $right['latest_activity_at'] ?? null),
        };
        if ($comparison !== 0) {
            return $comparison;
        }

        if ($sort === 'unanswered') {
            $comparison = $this->compareLatest($left['latest_activity_at'] ?? null, $right['latest_activity_at'] ?? null);
        } elseif ($sort === 'top') {
            $comparison = $this->compareLatest($left['latest_activity_at'] ?? null, $right['latest_activity_at'] ?? null);
        } elseif ($sort === 'solved') {
            $comparison = $this->compareLatest($left['latest_settled_at'] ?? null, $right['latest_settled_at'] ?? null);
        }
        if ($comparison !== 0) {
            return $comparison;
        }

        return [(int) $left['category_id'], (int) $left['position'], (int) $left['id']]
            <=> [(int) $right['category_id'], (int) $right['position'], (int) $right['id']];
    }

    private function compareLatest(mixed $left, mixed $right): int
    {
        $leftValue = is_string($left) ? $left : '';
        $rightValue = is_string($right) ? $right : '';
        return $rightValue <=> $leftValue;
    }

    /**
     * @return array{
     *     categories:array<int,array<string,mixed>>,
     *     boards:array<int,array<string,mixed>>,
     *     member_ids:array<int,int>
     * }
     */
    private function snapshot(?User $user): array
    {
        $key = $user === null ? 'guest' : 'user:' . $user->id();
        if (isset($this->snapshots[$key])) {
            return $this->snapshots[$key];
        }

        $memberIds = [];
        if ($user !== null) {
            $memberIds = array_flip($this->members->boardIdsFor($user->id()));
        }

        return $this->snapshots[$key] = [
            'categories' => $this->categories->all(),
            'boards' => $this->boards->allOrdered(),
            'member_ids' => $memberIds,
        ];
    }
}
