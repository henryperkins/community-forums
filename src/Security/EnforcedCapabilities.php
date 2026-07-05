<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Keys with LIVE route enforcement after Increment 6. The role editor refuses
 * to grant anything outside this set ("honesty clamp"): a granted capability
 * must actually work. Grows as later increments cut more surface over
 * (remaining admin-console keys are the recorded follow-up). Spec:
 * docs/superpowers/specs/2026-07-04-inc6-resolver-enforcement-cutover-design.md §3.
 */
final class EnforcedCapabilities
{
    /** @var list<string> */
    private const KEYS = [
        'core.thread.create', 'core.post.create', 'core.thread.tag',
        'core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow',
        'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
        'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author',
        'core.content.approve', 'core.content.view_pending', 'core.report.handle',
        'core.appeal.resolve_content', 'core.memory.curate', 'core.user.warn',
        'core.board.assign_moderators', 'core.board.manage_members',
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
