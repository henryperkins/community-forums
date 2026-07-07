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
    /** @var array<string,CapabilityDecision> */
    private array $decisionMemo = [];
    /** @var array<string,array{grants:list<array<string,mixed>>,site_rank:int}> */
    private array $bundleMemo = [];
    /** @var array<int,?array<string,mixed>> */
    private array $boardMemo = [];
    /** @var array<string,bool> "b{boardId}|{actorKey}" => isMember */
    private array $memberMemo = [];
    /** @var array<string,list<string>> capability => role keys holding it */
    private array $roleKeysMemo = [];

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
        $memoKey = implode('|', [
            $capability,
            $actor?->id() ?? 'guest',
            $target['board_id'] ?? '',
            $target['owner_id'] ?? '',
            $target['user_id'] ?? '',
            $target['category_id'] ?? '',
            $at?->format('YmdHi') ?? 'now',
        ]);
        if (isset($this->decisionMemo[$memoKey])) {
            return $this->decisionMemo[$memoKey];
        }

        $meta = CapabilityCatalog::all()[$capability] ?? null;
        if ($meta === null) {
            $decision = CapabilityDecision::deny($capability, 'unknown_capability', 'Unknown capability keys fail dark.');
            return $this->decisionMemo[$memoKey] = $decision;
        }

        $at ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $actorCanWrite = $actor !== null && $this->writeGate->canWrite($actor);
        $isActiveOwner = $meta['protected'] && $actor !== null && $this->owners->isActiveOwner($actor->id());

        $actorKey = $actor !== null ? 'u' . $actor->id() : 'guest';
        if (isset($this->bundleMemo[$actorKey])) {
            $bundle = $this->bundleMemo[$actorKey];
        } else {
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
            $bundle = ['grants' => $grants, 'site_rank' => $bundle['site_rank']];
            $this->bundleMemo[$actorKey] = $bundle;
        }
        $grants = $bundle['grants'];

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
            if (array_key_exists($boardId, $this->boardMemo)) {
                $board = $this->boardMemo[$boardId];
            } else {
                $board = $this->boards->find($boardId);
                $this->boardMemo[$boardId] = $board;
            }
            if ($board !== null) {
                $isMember = $actor !== null
                    && ($this->memberMemo['b' . $boardId . '|' . $actorKey]
                        ??= $this->members->isMember($boardId, $actor->id()));
                $ctx['board'] = $board;
                $ctx['board_member'] = $isMember;
                $ctx['board_readable'] = $this->policy->canRead($board, $actor, $isMember);
                $ctx['category_id'] ??= (int) $board['category_id'];
            }
        }

        $decision = CapabilityRules::decide(
            $capability,
            $meta,
            $actor,
            $actorCanWrite,
            $isActiveOwner,
            $bundle['site_rank'],
            $grants,
            $this->roleKeysMemo[$capability] ??= $this->roleCapabilities->roleKeysHolding($capability),
            $ctx,
            $at,
        );

        return $this->decisionMemo[$memoKey] = $decision;
    }

    /**
     * Pre-seed the board-row memo from rows the caller already fetched
     * (e.g. PostController::postableBoards iterating allOrdered()), so
     * per-board decisions skip the per-id re-fetch.
     *
     * @param list<array<string,mixed>> $rows board rows keyed by their 'id'
     */
    public function primeBoards(array $rows): void
    {
        foreach ($rows as $row) {
            $this->boardMemo[(int) $row['id']] = $row;
        }
    }

    /**
     * Pre-seed the membership memo from a single boardIdsFor() fetch: every
     * id in $memberBoardIds is a membership, every other id in $allBoardIds
     * is a non-membership.
     *
     * @param list<int> $memberBoardIds
     * @param list<int> $allBoardIds
     */
    public function primeMembership(int $userId, array $memberBoardIds, array $allBoardIds): void
    {
        $memberSet = array_flip($memberBoardIds);
        foreach ($allBoardIds as $boardId) {
            $this->memberMemo['b' . (int) $boardId . '|u' . $userId] = isset($memberSet[(int) $boardId]);
        }
    }

    /**
     * Clears all per-request memos. Callers (grant/revoke/role-edit/change-role)
     * must invoke this after any authority mutation so it is observed within the
     * same request; between requests the resolver is a fresh instance.
     */
    public function invalidate(): void
    {
        $this->decisionMemo = [];
        $this->bundleMemo = [];
        $this->boardMemo = [];
        $this->memberMemo = [];
        $this->roleKeysMemo = [];
    }
}
