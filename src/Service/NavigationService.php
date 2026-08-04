<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserBoardPrefRepository;
use App\Security\BoardPolicy;

final class NavigationService
{
    /**
     * @var array<string,array{
     *     categories:array<int,array<string,mixed>>,
     *     boards:array<int,array<string,mixed>>,
     *     member_ids:array<int,int>,
     *     muted_ids:array<int,int>
     * }>
     */
    private array $snapshots = [];

    public function __construct(
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private UserBoardPrefRepository $boardPrefs,
        private BoardPolicy $policy,
    ) {
    }

    /** @return array<int,array{category:array<string,mixed>,boards:array<int,array<string,mixed>>}> */
    public function sidebar(?User $user): array
    {
        $snapshot = $this->snapshot($user);
        $nav = [];
        foreach ($snapshot['categories'] as $category) {
            $boards = array_values(array_filter(
                $snapshot['boards'],
                fn (array $board): bool => (int) $board['category_id'] === (int) $category['id']
                    && !isset($snapshot['muted_ids'][(int) $board['id']])
                    && $this->policy->isListed(
                        $board,
                        $user,
                        isset($snapshot['member_ids'][(int) $board['id']]),
                    ),
            ));
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
     * @return array{
     *     categories:array<int,array<string,mixed>>,
     *     boards:array<int,array<string,mixed>>,
     *     member_ids:array<int,int>,
     *     muted_ids:array<int,int>
     * }
     */
    private function snapshot(?User $user): array
    {
        $key = $user === null ? 'guest' : 'user:' . $user->id();
        if (isset($this->snapshots[$key])) {
            return $this->snapshots[$key];
        }

        $memberIds = [];
        $mutedIds = [];
        if ($user !== null) {
            $memberIds = array_flip($this->members->boardIdsFor($user->id()));
            $mutedIds = array_flip($this->boardPrefs->mutedBoardIds($user->id()));
        }

        return $this->snapshots[$key] = [
            'categories' => $this->categories->all(),
            'boards' => $this->boards->allOrdered(),
            'member_ids' => $memberIds,
            'muted_ids' => $mutedIds,
        ];
    }
}
