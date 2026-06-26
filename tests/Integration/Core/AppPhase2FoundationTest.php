<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\BlockRepository;
use App\Repository\SettingRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * Milestone 0 foundation: feature flags, the block predicate, and counter
 * reconciliation. These shared services underpin every later Phase 2 milestone.
 */
final class AppPhase2FoundationTest extends TestCase
{
    public function testFeatureFlagsDefaultOnAndSettingOverridesPerFlag(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('features', ['dms' => false]);

        $flags = new FeatureFlags($settings);
        self::assertFalse($flags->enabled('dms'), 'settings override disables a flag');
        self::assertTrue($flags->enabled('search'), 'un-overridden flags keep their ON default');
        self::assertTrue($flags->enabled('engagement'));
        self::assertFalse($flags->enabled('not_a_real_flag'), 'unknown flag is off');
    }

    public function testBlockPredicateIsDirectionalAndSymmetricVariants(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $blocks = new BlockRepository($this->db);

        self::assertFalse($blocks->blockedEitherWay((int) $a['id'], (int) $b['id']));

        $blocks->block((int) $a['id'], (int) $b['id']);
        self::assertTrue($blocks->blocks((int) $a['id'], (int) $b['id']), 'directional: a blocked b');
        self::assertFalse($blocks->blocks((int) $b['id'], (int) $a['id']), 'directional: b did not block a');
        self::assertTrue($blocks->blockedEitherWay((int) $a['id'], (int) $b['id']), 'symmetric predicate sees the block');
        self::assertTrue($blocks->blockedEitherWay((int) $b['id'], (int) $a['id']), 'order does not matter');

        $map = $blocks->blockedMap((int) $b['id'], [(int) $a['id']]);
        self::assertArrayHasKey((int) $a['id'], $map, 'blockedMap surfaces the reverse block');

        $blocks->unblock((int) $a['id'], (int) $b['id']);
        self::assertFalse($blocks->blockedEitherWay((int) $a['id'], (int) $b['id']));
    }

    public function testSelfBlockIsANoOp(): void
    {
        $a = $this->makeUser();
        $blocks = new BlockRepository($this->db);
        $blocks->block((int) $a['id'], (int) $a['id']);
        self::assertFalse($blocks->blockedEitherWay((int) $a['id'], (int) $a['id']));
        self::assertSame([], $blocks->listBlocked((int) $a['id']));
    }

    public function testRepairFixesDriftedPostAndThreadCounters(): void
    {
        $author = $this->makeUser();
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat);
        $thread = $this->makeThread($board, $author, 'Counters', 'Body of the OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'A reply.']);

        // Deliberately corrupt the denormalised counters.
        $this->db->run('UPDATE users SET post_count = 99 WHERE id = ?', [(int) $author['id']]);
        $this->db->run('UPDATE threads SET reply_count = 0 WHERE id = ?', [$thread['thread_id']]);
        $this->db->run('UPDATE boards SET post_count = 0, thread_count = 0 WHERE id = ?', [(int) $board['id']]);

        (new RepairService($this->db))->repairAll();

        self::assertSame(2, (int) $this->db->fetchValue('SELECT post_count FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$thread['thread_id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT thread_count FROM boards WHERE id = ?', [(int) $board['id']]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT post_count FROM boards WHERE id = ?', [(int) $board['id']]));
    }

    public function testRepairReputationCountsReceivedReactionsButNotSelf(): void
    {
        $author = $this->makeUser();
        $fan = $this->makeUser();
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat);
        $thread = $this->makeThread($board, $author, 'Rep', 'OP body.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        // One reaction from another user (+1) and one self-reaction (0).
        $this->db->run('INSERT INTO reactions (post_id,user_id,emoji,created_at) VALUES (?,?,?,UTC_TIMESTAMP())', [$opId, (int) $fan['id'], '👍']);
        $this->db->run('INSERT INTO reactions (post_id,user_id,emoji,created_at) VALUES (?,?,?,UTC_TIMESTAMP())', [$opId, (int) $author['id'], '🎉']);

        (new RepairService($this->db))->repairReputation();

        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]), 'self-reaction excluded from reputation');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $fan['id']]));
    }
}
