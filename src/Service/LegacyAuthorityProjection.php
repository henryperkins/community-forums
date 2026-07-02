<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BoardModeratorRepository;

/**
 * Derives virtual grants from legacy authority tables so the resolver can run
 * before real role assignments are imported.
 */
final class LegacyAuthorityProjection
{
    public function __construct(private BoardModeratorRepository $boardModerators)
    {
    }

    /** @return array{grants: list<array<string,mixed>>, site_rank: int} */
    public function bundleFor(?User $user): array
    {
        $grant = static fn (
            string $kind,
            ?string $roleKey,
            ?string $capabilityKey,
            string $scopeType,
            ?int $scopeId,
        ): array => [
            'kind' => $kind,
            'role_key' => $roleKey,
            'capability_key' => $capabilityKey,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'starts_at' => null,
            'ends_at' => null,
            'source' => 'legacy',
        ];

        $grants = [$grant('role', 'system.guest', null, 'site', null)];
        $siteRank = 0;

        if ($user !== null) {
            $grants[] = $grant('role', 'system.user', null, 'site', null);
            $siteRank = 10;

            $moderatedBoards = $this->boardModerators->boardsFor($user->id());
            foreach ($moderatedBoards as $boardId) {
                $grants[] = $grant('role', 'system.moderator', null, 'board', $boardId);
            }
            if ($moderatedBoards !== []) {
                $grants[] = $grant('capability', null, 'core.user.warn', 'site', null);
            }

            if ($user->role() === 'moderator') {
                $siteRank = 20;
                $grants[] = $grant('capability', null, 'core.content.view_pending', 'site', null);
            }

            if ($user->isAdmin()) {
                $siteRank = 30;
                $grants[] = $grant('role', 'system.admin', null, 'site', null);
            }
        }

        return ['grants' => $grants, 'site_rank' => $siteRank];
    }
}
