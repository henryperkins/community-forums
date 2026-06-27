<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-05: in the default observe mode a rule can fire and be recorded without
 * changing visibility. The automated decision writes an immutable audit row with
 * a system actor (NULL), the rule, the reason, and the mode.
 */
final class AppAutomationAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    public function test_observe_mode_audits_without_holding(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'observe']);
        $author = $this->makeUser(['username' => 'linker']); // new account (0 posts)
        $this->actingAs($author);

        // A new user posting more links than the new-user ceiling (2) trips the
        // excessive-links rule (hold-worthy), but observe mode clamps to allow.
        $body = 'see http://a.example http://b.example http://c.example';
        $res = $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Links', 'body' => $body]);
        $this->assertRedirectContains($res, '/t/'); // created + visible

        $thread = $this->db->fetch('SELECT * FROM threads WHERE user_id = ?', [(int) $author['id']]);
        self::assertSame(0, (int) $thread['is_pending']); // not held in observe mode

        $row = $this->db->fetch(
            "SELECT * FROM moderation_log WHERE action = 'auto_allow' AND target_type = 'thread' AND target_id = ? AND actor_id IS NULL",
            [(int) $thread['id']],
        );
        self::assertNotNull($row, 'expected a system-actor audit row');
        $after = json_decode((string) $row['after_json'], true);
        self::assertSame('excessive_links', $after['rule']);
        self::assertSame('observe', $after['mode']);
        self::assertSame('hold', $after['natural']);
        self::assertStringContainsString('too many links', (string) $row['reason']);
    }

    public function test_new_user_throttle_requires_clearing_both_thresholds(): void
    {
        // config.php documents a new user as "below either threshold", so an
        // account is only established once it clears BOTH the post-count AND the
        // account-age minimums. With both configured, the strict link ceiling must
        // still apply to a young account even if it has many posts.
        $config = new \App\Core\Config(['antiabuse' => [
            'mode' => 'hold',
            'new_user_min_posts' => 3,
            'new_user_min_age_minutes' => 60,
            'new_user_max_links' => 2,
            'max_links' => 25,
        ]]);
        $svc = new \App\Service\AntiAbuseService(
            $this->db,
            $config,
            new \App\Repository\SettingRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
        );
        $body = 'see http://a.example http://b.example http://c.example'; // 3 links

        // Clears the post threshold (9 posts) but the account is seconds old →
        // still a new user, so the strict ceiling (2) holds the 3-link post.
        $young = \App\Domain\User::fromRow([
            'id' => 90001, 'role' => 'user', 'post_count' => 9,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        self::assertTrue($svc->evaluate($young, 'thread', $body)->holds());

        // Clears BOTH thresholds → established → the relaxed 25-link limit applies.
        $established = \App\Domain\User::fromRow([
            'id' => 90002, 'role' => 'user', 'post_count' => 9,
            'created_at' => gmdate('Y-m-d H:i:s', time() - 7200),
        ]);
        self::assertFalse($svc->evaluate($established, 'thread', $body)->holds());
    }

    public function test_clean_post_writes_no_audit(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'cleanboard']);
        $author = $this->makeUser(['username' => 'cleanposter']);
        $this->actingAs($author);

        $this->post('/threads', ['board_id' => (int) $board['id'], 'title' => 'Normal', 'body' => 'Just a normal post.']);
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action LIKE 'auto_%'",
        ));
    }
}
