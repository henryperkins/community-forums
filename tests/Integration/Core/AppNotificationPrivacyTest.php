<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\NotificationRepository;
use App\Repository\SubscriptionRepository;
use Tests\Support\TestCase;

final class AppNotificationPrivacyTest extends TestCase
{
    public function test_revoked_content_is_absent_from_every_notification_surface(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Original title');
        (new NotificationRepository($this->db))->create([
            'user_id' => (int) $member['id'], 'type' => 'reply',
            'actor_id' => (int) $author['id'], 'thread_id' => $thread['thread_id'],
        ]);
        (new SubscriptionRepository($this->db))->set((int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
        $this->db->run('UPDATE threads SET title = ? WHERE id = ?', ['PRIVATE-AFTER-REVOCATION', $thread['thread_id']]);
        $this->actingAs($member);
        foreach ([['/notifications', []], ['/', ['pane' => 'notices']], ['/notifications/bell', []], ['/settings/notifications', []]] as [$path, $query]) {
            $response = $this->get($path, $query);
            $this->assertStatus(200, $response);
            if ($path === '/') {
                self::assertStringContainsString('data-directory-pane="notices"', $response->body());
            }
            self::assertStringNotContainsString('PRIVATE-AFTER-REVOCATION', $response->body());
        }
        $payload = json_decode($this->get('/notifications/bell')->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(0, $payload['unread']);
    }
    private function reader(): \App\Service\NotificationReadService
    {
        return new \App\Service\NotificationReadService(
            new NotificationRepository($this->db), new \App\Service\NotificationVisibilityService($this->db),
        );
    }

    public function test_board_scope_matches_canonical_read_gate_and_preserves_suspended_assignments(): void
    {
        $admin = $this->makeAdmin();
        $author = $this->makeUser();
        $member = $this->makeUser();
        $assigned = $this->makeUser(['role' => 'moderator']);
        $ordinary = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        (new \App\Repository\BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $assigned['id']);
        $this->db->run('INSERT INTO board_members (board_id, user_id) VALUES (?, ?)', [$board['id'], $member['id']]);
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = '2099-01-01' WHERE id = ?", [$assigned['id']]);
        $assigned = $this->users()->find((int) $assigned['id']);
        $canonical = new \App\Service\ThreadReadService(
            $this->threads(), new \App\Security\BoardPolicy(), new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardAuthority(new \App\Security\WriteGate(), new \App\Repository\BoardModeratorRepository($this->db), new \App\Repository\BoardRepository($this->db)),
        );
        foreach ([$admin, $member, $assigned, $ordinary] as $recipient) {
            (new NotificationRepository($this->db))->create(['user_id' => (int) $recipient['id'], 'type' => 'reply', 'actor_id' => (int) $author['id'], 'thread_id' => $thread['thread_id']]);
        }
        foreach (['public', 'hidden', 'private'] as $visibility) {
            $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', [$visibility, $board['id']]);
            foreach ([$admin, $member, $assigned, $ordinary] as $recipient) {
                $viewer = $this->userEntity($recipient);
                $expected = $visibility !== 'private' || $recipient['id'] !== $ordinary['id'];
                try {
                    $canonical->loadForUser($viewer, $thread['thread_id']);
                    $readable = true;
                } catch (\App\Core\NotFoundException) {
                    $readable = false;
                }
                self::assertSame($expected, $readable);
                $page = $this->reader()->page($viewer);
                self::assertCount($expected ? 1 : 0, $page['items']);
                self::assertSame($expected ? 1 : 0, $page['unread']);
            }
        }
        $this->actingAs($assigned);
        $notice = (new NotificationRepository($this->db))->recent((int) $assigned['id'])[0];
        $this->assertRedirect($this->post('/notifications/' . $notice['id'] . '/read'), '/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    }

    public function test_blocks_exemptions_and_anonymity_are_consistent_in_html_json_and_count(): void
    {
        $admin = $this->makeAdmin();
        $member = $this->makeUser();
        $other = $this->makeUser();
        $repo = new NotificationRepository($this->db);
        $blocks = new \App\Repository\BlockRepository($this->db);
        $blocks->block((int) $member['id'], (int) $admin['id']);
        $blocks->block((int) $other['id'], (int) $member['id']);
        $repo->broadcastAnnouncement((int) $admin['id']);
        $repo->create(['user_id' => (int) $member['id'], 'type' => 'mod', 'actor_id' => (int) $admin['id']]);
        $repo->create(['user_id' => (int) $member['id'], 'type' => 'badge']);
        foreach ([$admin, $other] as $actor) {
            $repo->create(['user_id' => (int) $member['id'], 'type' => 'follow', 'actor_id' => (int) $actor['id']]);
        }
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $admin, 'FORBIDDEN-TARGET');
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
        foreach (['mod', 'announcement', 'badge'] as $type) {
            $repo->create(['user_id' => (int) $member['id'], 'type' => $type, 'actor_id' => (int) $admin['id'], 'thread_id' => $thread['thread_id']]);
        }
        $page = $this->reader()->page($this->userEntity($member));
        self::assertSame(3, $page['unread']);
        self::assertSame(['badge', 'mod', 'announcement'], array_column($page['items'], 'type'));
        $this->actingAs($member);
        foreach (['/notifications', '/notifications/bell'] as $path) {
            $response = $this->get($path);
            $this->assertStatus(200, $response);
            self::assertStringNotContainsString('FORBIDDEN-TARGET', $response->body());
        }
        $bell = json_decode($this->get('/notifications/bell')->body(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(3, $bell['unread']);
        self::assertSame(['badge', 'mod', 'announcement'], array_column($bell['items'], 'type'));

        $anon = $this->makeUser(['username' => 'secretanonhandle']);
        $public = $this->makeBoard($this->makeCategory());
        $topic = $this->makeThread($public, $anon);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$topic['thread_id']]);
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE id = ?', [$postId]);
        $repo->create(['user_id' => (int) $member['id'], 'type' => 'new_thread', 'actor_id' => (int) $anon['id'], 'thread_id' => $topic['thread_id'], 'post_id' => $postId]);
        $response = $this->get('/notifications/bell');
        self::assertStringContainsString('Anonymous', $response->body());
        self::assertStringNotContainsString('secretanonhandle', $response->body());
        self::assertNull($this->reader()->page($this->userEntity($member))['items'][0]['actor_id']);
    }

    public function test_deleted_pending_and_moved_content_disappears_before_limit(): void
    {
        $author = $this->makeUser();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $repo = new NotificationRepository($this->db);
        $viewer = $this->userEntity($member);
        for ($i = 0; $i < 35; $i++) {
            $repo->create(['user_id' => $viewer->id(), 'type' => 'reply', 'thread_id' => $thread['thread_id']]);
        }
        $hidden = $this->makeThread($board, $author);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$hidden['thread_id']]);
        for ($i = 0; $i < 40; $i++) {
            $repo->create(['user_id' => $viewer->id(), 'type' => 'reply', 'thread_id' => $hidden['thread_id'], 'post_id' => $postId]);
        }
        foreach ([['posts', 'is_deleted', 1], ['posts', 'is_pending', 1], ['threads', 'is_deleted', 1], ['threads', 'is_pending', 1], ['threads', 'board_id', $private['id']]] as [$table, $column, $value]) {
            $id = $table === 'posts' ? $postId : $hidden['thread_id'];
            $this->db->run("UPDATE $table SET $column = ? WHERE id = ?", [$value, $id]);
            $page = $this->reader()->page($viewer);
            self::assertSame(35, $page['unread']);
            self::assertCount(30, $page['items']);
            self::assertNotNull($page['next_before']);
            $older = $this->reader()->page($viewer, true, $page['next_before']);
            self::assertCount(5, $older['items']);
            self::assertNull($older['next_before']);
            $this->db->run("UPDATE $table SET $column = ? WHERE id = ?", [$column === 'board_id' ? $board['id'] : 0, $id]);
        }
    }

    public function test_scope_and_count_are_request_local_and_explicit_refresh_reloads_access(): void
    {
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $viewer = $this->userEntity($member);
        $visibility = new \App\Service\NotificationVisibilityService($this->db);
        $first = $visibility->scope($viewer);
        $this->db->run('INSERT INTO board_members (board_id, user_id) VALUES (?, ?)', [$board['id'], $member['id']]);
        self::assertSame($first, $visibility->scope($viewer));
        self::assertContains((int) $board['id'], $visibility->scope($viewer, true)['member_board_ids']);
        $reader = $this->reader();
        $reader->unreadCount($viewer);
        $before = $this->db->metrics()['queries'];
        self::assertSame(0, $reader->unreadCount($viewer));
        self::assertSame($before, $this->db->metrics()['queries']);
    }

    public function test_dm_historical_membership_flags_and_report_authority(): void
    {
        $admin = $this->makeAdmin();
        $author = $this->makeUser();
        $member = $this->makeUser();
        $outsider = $this->makeUser(['role' => 'moderator']);
        $conversations = new \App\Repository\ConversationRepository($this->db);
        $conversation = $conversations->createGroup((int) $author['id'], 'Retained group', [(int) $member['id']]);
        (new \App\Repository\DmMessageRepository($this->db))->create($conversation, (int) $author['id'], 'Old history', '<p>Old history</p>');
        $repo = new NotificationRepository($this->db);
        foreach ([$member, $outsider] as $recipient) {
            $repo->create(['user_id' => (int) $recipient['id'], 'type' => 'dm', 'actor_id' => (int) $author['id'], 'conversation_id' => $conversation]);
        }
        foreach ([$admin, $outsider] as $recipient) {
            $repo->create(['user_id' => (int) $recipient['id'], 'type' => 'mod', 'actor_id' => (int) $author['id'], 'conversation_id' => $conversation]);
        }
        $this->db->run('UPDATE conversation_participants SET left_at = UTC_TIMESTAMP() WHERE conversation_id = ? AND user_id = ?', [$conversation, $member['id']]);
        (new \App\Repository\SettingRepository($this->db))->set('features', ['group_dms' => false]);
        self::assertSame(1, $this->reader()->unreadCount($this->userEntity($member)));
        self::assertSame(1, $this->reader()->unreadCount($this->userEntity($admin)));
        self::assertSame(0, $this->reader()->unreadCount($this->userEntity($outsider)));
        $this->actingAs($member);
        $this->assertStatus(200, $this->get('/messages/' . $conversation));
        (new \App\Repository\SettingRepository($this->db))->set('features', ['dms' => false, 'moderation_queue' => false]);
        self::assertSame(0, $this->reader()->unreadCount($this->userEntity($member)));
        self::assertSame(0, $this->reader()->unreadCount($this->userEntity($admin)));
    }
    public function test_dm_notice_must_fall_within_retained_membership_interval(): void
    {
        $author = $this->makeUser();
        $member = $this->makeUser();
        $conversation = (new \App\Repository\ConversationRepository($this->db))->findOrCreateBetween((int) $author['id'], (int) $member['id']);
        $this->db->run("UPDATE conversation_participants SET joined_at = '2026-09-20 02:00:00', left_at = '2026-09-20 04:00:00' WHERE conversation_id = ? AND user_id = ?", [$conversation, $member['id']]);
        $repo = new NotificationRepository($this->db);
        foreach (['2026-09-20 01:00:00', '2026-09-20 03:00:00', '2026-09-20 05:00:00'] as $created) {
            $id = $repo->create(['user_id' => (int) $member['id'], 'type' => 'dm', 'actor_id' => (int) $author['id'], 'conversation_id' => $conversation]);
            $this->db->run('UPDATE notifications SET created_at = ? WHERE id = ?', [$created, $id]);
        }
        $page = $this->reader()->page($this->userEntity($member));
        self::assertSame(1, $page['unread']);
        self::assertSame('2026-09-20 03:00:00', $page['items'][0]['created_at']);
    }
}
