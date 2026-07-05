<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CapabilityCatalog;
use App\Security\EnforcedCapabilities;
use PHPUnit\Framework\TestCase;

final class EnforcedCapabilitiesTest extends TestCase
{
    public function test_exact_enforced_set_matches_the_inc6_spec(): void
    {
        $expected = [
            'core.thread.create', 'core.post.create', 'core.thread.tag',
            'core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow',
            'core.post.delete_any', 'core.post.restore', 'core.thread.lock', 'core.thread.pin',
            'core.thread.move', 'core.thread.split_merge', 'core.post.reveal_author',
            'core.content.approve', 'core.content.view_pending', 'core.report.handle',
            'core.appeal.resolve_content', 'core.memory.curate', 'core.user.warn',
            'core.board.assign_moderators', 'core.board.manage_members',
        ];
        sort($expected);
        $actual = EnforcedCapabilities::keys();
        sort($actual);
        self::assertSame($expected, $actual);
    }

    public function test_every_enforced_key_is_catalogued_and_delegable(): void
    {
        foreach (EnforcedCapabilities::keys() as $key) {
            $meta = CapabilityCatalog::all()[$key] ?? null;
            self::assertNotNull($meta, $key);
            self::assertTrue($meta['delegable'], $key);
            self::assertFalse($meta['protected'], $key);
        }
    }
}
