<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Service\LegacyAuthorityProjection;

/**
 * Database-backed capability resolver. It unions the legacy-authority
 * projection with real role_assignments, then delegates pure decisions to
 * CapabilityRules.
 */
final class CapabilityResolver
{
    public function __construct(
        private RoleCapabilityRepository $roleCapabilities,
        private RoleAssignmentRepository $assignments,
        private LegacyAuthorityProjection $projection,
        private ProtectedOwnerRepository $owners,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
    ) {
    }

    /** @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target */
    public function can(?User $actor, string $capability, array $target = [], ?\DateTimeImmutable $at = null): CapabilityDecision
    {
        $meta = CapabilityCatalog::all()[$capability] ?? null;
        if ($meta === null) {
            return CapabilityDecision::deny($capability, 'unknown_capability', 'Unknown capability keys fail dark.');
        }

        $at ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $actorCanWrite = $actor !== null && $this->writeGate->canWrite($actor);
        $isActiveOwner = $meta['protected'] && $actor !== null && $this->owners->isActiveOwner($actor->id());

        $bundle = $this->projection->bundleFor($actor);
        $grants = $bundle['grants'];
        if ($actor !== null) {
            foreach ($this->assignments->rowsForUser($actor->id()) as $row) {
                $grants[] = [
                    'kind' => 'role',
                    'role_key' => (string) $row['role_key'],
                    'capability_key' => null,
                    'scope_type' => (string) $row['scope_type'],
                    'scope_id' => $row['scope_id'] === null ? null : (int) $row['scope_id'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'source' => 'assignment',
                ];
            }
        }

        $ctx = [
            'board' => null,
            'board_member' => false,
            'board_readable' => false,
            'owner_id' => isset($target['owner_id']) ? (int) $target['owner_id'] : null,
            'user_id' => isset($target['user_id']) ? (int) $target['user_id'] : null,
            'category_id' => isset($target['category_id']) ? (int) $target['category_id'] : null,
        ];

        $boardId = (int) ($target['board_id'] ?? 0);
        if ($boardId > 0) {
            $board = $this->boards->find($boardId);
            if ($board !== null) {
                $isMember = $actor !== null && $this->members->isMember($boardId, $actor->id());
                $ctx['board'] = $board;
                $ctx['board_member'] = $isMember;
                $ctx['board_readable'] = $this->policy->canRead($board, $actor, $isMember);
                $ctx['category_id'] ??= (int) $board['category_id'];
            }
        }

        return CapabilityRules::decide(
            $capability,
            $meta,
            $actor,
            $actorCanWrite,
            $isActiveOwner,
            $bundle['site_rank'],
            $grants,
            $this->roleCapabilities->roleKeysHolding($capability),
            $ctx,
            $at,
        );
    }
}
