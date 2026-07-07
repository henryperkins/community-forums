<?php

declare(strict_types=1);

namespace App\Security;

use App\Domain\User;

/**
 * Pure union-then-narrow capability decision core. It performs no I/O; callers
 * fetch grants and target context, then this class applies code-owned rules.
 */
final class CapabilityRules
{
    /** @var list<string> */
    private const STATE_EXEMPT = [Cap::BOARD_READ, Cap::ACCOUNT_MANAGE_SELF];

    /** @var list<string> */
    private const DUAL_PATH = [Cap::THREAD_MARK_SOLVED, Cap::POLL_MANAGE, Cap::THREAD_MANAGE_WORKFLOW];

    /**
     * Roles whose grants confer BOARD-WIDE dual-path authority (acting on other
     * members' threads). Everything else — the baseline system.user/system.guest
     * bundles and every custom role — confers only the author path (taxonomy
     * §4.2: "board-wide use comes only through a board-scoped moderator
     * assignment"). An allowlist, so a clone of system.user cannot silently
     * carry board-wide authority once the resolver enforces (Inc 6).
     *
     * @var list<string>
     */
    private const DUAL_PATH_BOARD_AUTHORITY = ['system.moderator', 'system.admin'];

    /** @var list<string> */
    private const CAN_POST_GATED = [Cap::THREAD_CREATE, Cap::POST_CREATE, Cap::THREAD_TAG];

    /** @var list<string> */
    private const READ_GATED = [Cap::BOARD_READ, Cap::CONTENT_REACT, Cap::CONTENT_REPORT];

    /**
     * @param array{scope:string,risk:string,delegable:bool,protected:bool} $meta
     * @param list<array{kind:string,role_key:?string,capability_key:?string,scope_type:string,scope_id:?int,starts_at:?string,ends_at:?string,source:string}> $grants
     * @param list<string> $rolesHoldingKey
     * @param array{board:?array<string,mixed>,board_member:bool,board_readable:bool,owner_id:?int,user_id:?int,category_id:?int} $ctx
     */
    public static function decide(
        string $capability,
        array $meta,
        ?User $actor,
        bool $actorCanWrite,
        bool $actorIsActiveOwner,
        int $siteRank,
        array $grants,
        array $rolesHoldingKey,
        array $ctx,
        \DateTimeImmutable $at,
    ): CapabilityDecision {
        if ($meta['protected']) {
            if ($actor !== null && $actorIsActiveOwner && $actorCanWrite) {
                return CapabilityDecision::allow($capability, 'protected', 'Actor is an active protected owner.');
            }

            return CapabilityDecision::deny($capability, 'protected', 'Held only by active protected owners; never role-mapped or delegable.');
        }

        if ($actor !== null && !$actorCanWrite && !in_array($capability, self::STATE_EXEMPT, true)) {
            return CapabilityDecision::deny($capability, 'state', 'Account state blocks this action.');
        }

        if (in_array($capability, self::DUAL_PATH, true)
            && $actor !== null
            && $ctx['owner_id'] !== null
            && $actor->owns((int) $ctx['owner_id'])) {
            $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, 'any', $ctx, $at);
            if ($g !== null) {
                return CapabilityDecision::allow($capability, 'grant', 'Actor owns the target.', $g['role_key'], 'self', null);
            }
        }

        if ($meta['scope'] === 'self') {
            $subject = $ctx['user_id'] ?? $ctx['owner_id'];
            if ($subject !== null && ($actor === null || !$actor->owns((int) $subject))) {
                return CapabilityDecision::deny($capability, 'scope', "Self-scoped capability applies only to the actor's own account or content.");
            }

            $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, 'any', $ctx, $at);
            return $g !== null
                ? CapabilityDecision::allow($capability, 'grant', 'Baseline self capability held via role grant.', $g['role_key'], 'self', null)
                : CapabilityDecision::deny($capability, 'no_grant', 'No active grant provides this capability.');
        }

        // Non-owner dual-path resolution: the author branch above did not match,
        // so only the moderation-tier roles may satisfy the key from here on —
        // regardless of whether an owner context was supplied (a bare board
        // target is a "held board-wide?" probe and must answer the same way).
        if (in_array($capability, self::DUAL_PATH, true)) {
            $rolesHoldingKey = array_values(array_intersect($rolesHoldingKey, self::DUAL_PATH_BOARD_AUTHORITY));
        }

        $g = self::firstQualifyingGrant($capability, $grants, $rolesHoldingKey, $meta['scope'], $ctx, $at);
        if ($g === null) {
            return CapabilityDecision::deny($capability, 'no_grant', 'No active grant provides this capability at this scope.');
        }

        $board = $ctx['board'];
        if ($board !== null) {
            if (in_array($capability, self::CAN_POST_GATED, true)) {
                if ((int) ($board['is_archived'] ?? 0) === 1) {
                    return CapabilityDecision::deny($capability, 'archived', 'The board is archived; write paths are closed.');
                }
                if (!$ctx['board_readable']) {
                    return CapabilityDecision::deny($capability, 'read_gate', 'The board read gate denies access to this board.');
                }
                if ($siteRank < self::floorRank((string) ($board['post_min_role'] ?? 'user'))) {
                    return CapabilityDecision::deny($capability, 'floor', "The board's minimum posting role is not met.");
                }
            } elseif (in_array($capability, self::READ_GATED, true) && !$ctx['board_readable']) {
                return CapabilityDecision::deny($capability, 'read_gate', 'The board read gate denies access to this board.');
            }
        }

        return CapabilityDecision::allow(
            $capability,
            'grant',
            'Active grant provides this capability at the required scope.',
            $g['role_key'] ?? null,
            $g['scope_type'],
            $g['scope_id'] === null ? null : (int) $g['scope_id'],
        );
    }

    /**
     * @param list<array<string,mixed>> $grants
     * @param list<string> $rolesHoldingKey
     * @param array{board:?array<string,mixed>,category_id:?int} $ctx
     * @return array<string,mixed>|null
     */
    private static function firstQualifyingGrant(
        string $capability,
        array $grants,
        array $rolesHoldingKey,
        string $scopeClass,
        array $ctx,
        \DateTimeImmutable $at,
    ): ?array {
        foreach ($grants as $grant) {
            if (!self::windowValid($grant, $at)) {
                continue;
            }

            $holds = ($grant['kind'] ?? 'role') === 'capability'
                ? ($grant['capability_key'] ?? null) === $capability
                : in_array((string) ($grant['role_key'] ?? ''), $rolesHoldingKey, true);

            if ($holds && self::scopeSatisfies($grant, $scopeClass, $ctx)) {
                return $grant;
            }
        }

        return null;
    }

    /** @param array<string,mixed> $grant */
    private static function windowValid(array $grant, \DateTimeImmutable $at): bool
    {
        $ts = $at->getTimestamp();
        $starts = $grant['starts_at'] ?? null;
        if ($starts !== null && strtotime((string) $starts . ' UTC') > $ts) {
            return false;
        }

        $ends = $grant['ends_at'] ?? null;
        return $ends === null || strtotime((string) $ends . ' UTC') > $ts;
    }

    /**
     * @param array<string,mixed> $grant
     * @param array{board:?array<string,mixed>,category_id:?int} $ctx
     */
    private static function scopeSatisfies(array $grant, string $scopeClass, array $ctx): bool
    {
        if ($scopeClass === 'any') {
            return true;
        }

        $grantScope = (string) $grant['scope_type'];
        $grantId = $grant['scope_id'] === null ? null : (int) $grant['scope_id'];

        if ($scopeClass === 'site') {
            return $grantScope === 'site';
        }

        if ($scopeClass === 'category') {
            return match ($grantScope) {
                'site' => true,
                // Fail closed: without a category target a category-scoped grant
                // cannot be confirmed to apply, so it must not satisfy the key.
                'category' => $ctx['category_id'] !== null && $grantId === (int) $ctx['category_id'],
                default => false,
            };
        }

        $board = $ctx['board'];
        if ($board === null) {
            // Fail closed: with no board target, only a genuinely site-wide grant
            // holds. A board/category-scoped grant must NOT read as authority over
            // every board (that fail-open would become a live bypass at Inc 6).
            return $grantScope === 'site';
        }

        return match ($grantScope) {
            'site' => true,
            'category' => $grantId === (int) ($board['category_id'] ?? 0),
            'board' => $grantId === (int) ($board['id'] ?? 0),
            default => false,
        };
    }

    private static function floorRank(string $postMinRole): int
    {
        return match ($postMinRole) {
            'admin' => 30,
            'moderator' => 20,
            default => 10,
        };
    }
}
