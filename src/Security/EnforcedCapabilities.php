<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Keys with LIVE route enforcement after Increment 6. The role editor refuses
 * to grant anything outside this set ("honesty clamp"): a granted capability
 * must actually work. Grows as later increments cut more surface over
 * (remaining admin-console keys are the recorded follow-up). Spec:
 * docs/superpowers/plans/2026-07-04-inc6-resolver-enforcement-cutover.md §3.
 */
final class EnforcedCapabilities
{
    /** @var list<string> */
    private const KEYS = [
        Cap::THREAD_CREATE, Cap::POST_CREATE, Cap::THREAD_TAG,
        Cap::THREAD_MARK_SOLVED, Cap::POLL_MANAGE, Cap::THREAD_MANAGE_WORKFLOW,
        Cap::POST_DELETE_ANY, Cap::POST_RESTORE, Cap::THREAD_LOCK, Cap::THREAD_PIN,
        Cap::THREAD_MOVE, Cap::THREAD_SPLIT_MERGE, Cap::POST_REVEAL_AUTHOR,
        Cap::CONTENT_APPROVE, Cap::CONTENT_VIEW_PENDING, Cap::REPORT_HANDLE,
        Cap::APPEAL_RESOLVE_CONTENT, Cap::MEMORY_CURATE, Cap::USER_WARN,
        Cap::BOARD_ASSIGN_MODERATORS, Cap::BOARD_MANAGE_MEMBERS,
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return self::KEYS;
    }

    public static function has(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }
}
