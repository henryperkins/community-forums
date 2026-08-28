<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\User;
use App\Repository\BlockRepository;
use App\Repository\UserRepository;

/**
 * Builds the privacy-filtered public presence roster used by both the shared
 * server-rendered rail and the progressive-enhancement JSON endpoint.
 */
final class PresenceService
{
    /** @var array<string,list<array{username:string,display_name:string,last_seen_at:string}>> */
    private array $rosters = [];

    public function __construct(
        private UserRepository $users,
        private BlockRepository $blocks,
        private int $onlineWindowSeconds,
    ) {
    }

    /** @return list<array{username:string,display_name:string,last_seen_at:string}> */
    public function roster(?User $viewer): array
    {
        $cacheKey = $viewer === null ? 'guest' : 'user:' . $viewer->id();
        if (array_key_exists($cacheKey, $this->rosters)) {
            return $this->rosters[$cacheKey];
        }

        $since = gmdate('Y-m-d H:i:s', time() - max(1, $this->onlineWindowSeconds));
        $rows = $this->users->onlineSince($since);
        $blocked = [];
        if ($viewer !== null) {
            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $blocked = $this->blocks->blockedMap($viewer->id(), $ids);
        }

        $online = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (($viewer !== null && $id === $viewer->id()) || isset($blocked[$id])) {
                continue;
            }
            $online[] = [
                'username' => (string) $row['username'],
                'display_name' => ($row['display_name'] ?? '') !== ''
                    ? (string) $row['display_name']
                    : (string) $row['username'],
                'last_seen_at' => (string) $row['last_seen_at'],
            ];
        }
        return $this->rosters[$cacheKey] = $online;
    }
}
