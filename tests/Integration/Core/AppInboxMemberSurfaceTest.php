<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

final class AppInboxMemberSurfaceTest extends TestCase
{
    private const SCOPES = [
        'for_you',
        'unread',
        'mentions',
        'replies',
        'watching',
        'assigned',
        'starred',
        'mine',
        'snoozed',
        'needs_answer',
        'decisions',
        'solved',
    ];

    private const ORDERS = ['active', 'newest', 'commended'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function threadUsers(): ThreadUserRepository
    {
        return new ThreadUserRepository($this->db);
    }

    public function test_repository_crosses_every_scope_with_every_order_and_keeps_pins_first(): void
    {
        $viewer = $this->makeUser();
        $author = $this->makeUser();
        $reactor = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $pinned = $this->makeThread($board, $author, 'Pinned old topic', 'Pinned opening.');
        $commended = $this->makeThread($board, $author, 'Commended topic', 'Commended opening.');
        $recent = $this->makeThread($board, $author, 'Recent topic', 'Recent opening.');

        $repo = $this->threadUsers();
        foreach ([$pinned, $commended, $recent] as $thread) {
            $repo->setStar((int) $viewer['id'], (int) $thread['thread_id'], true);
        }
        $this->db->run(
            "UPDATE threads SET is_pinned = 1, created_at = '2025-01-01 10:00:00', last_post_at = '2025-01-01 10:00:00' WHERE id = ?",
            [(int) $pinned['thread_id']],
        );
        $this->db->run(
            "UPDATE threads SET created_at = '2026-01-01 10:00:00', last_post_at = '2026-01-01 10:00:00' WHERE id = ?",
            [(int) $commended['thread_id']],
        );
        $this->db->run(
            "UPDATE threads SET created_at = '2026-02-01 10:00:00', last_post_at = '2026-02-01 10:00:00' WHERE id = ?",
            [(int) $recent['thread_id']],
        );
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [(int) $commended['thread_id']],
        );
        $this->db->run(
            'INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP()), (?, ?, ?, UTC_TIMESTAMP())',
            [$opId, (int) $reactor['id'], 'agree', $opId, (int) $author['id'], 'self'],
        );

        foreach (self::SCOPES as $scope) {
            foreach (self::ORDERS as $order) {
                $rows = $repo->inbox(
                    (int) $viewer['id'],
                    $scope,
                    $order,
                    false,
                    ThreadUserRepository::NO_CUTOVER,
                    100,
                    0,
                );
                self::assertSame(
                    $repo->countInbox(
                        (int) $viewer['id'],
                        $scope,
                        $order,
                        false,
                        ThreadUserRepository::NO_CUTOVER,
                    ),
                    count($rows),
                    $scope . ' / ' . $order,
                );
            }
        }

        $commendedRows = $repo->inbox(
            (int) $viewer['id'],
            'starred',
            'commended',
            false,
            ThreadUserRepository::NO_CUTOVER,
            20,
            0,
        );
        self::assertSame(['Pinned old topic', 'Commended topic', 'Recent topic'], array_column($commendedRows, 'title'));
        self::assertSame([0, 1, 0], array_map('intval', array_column($commendedRows, 'commend_count')));

        $activeRows = $repo->inbox(
            (int) $viewer['id'],
            'starred',
            'active',
            false,
            ThreadUserRepository::NO_CUTOVER,
            20,
            0,
        );
        self::assertSame(['Pinned old topic', 'Recent topic', 'Commended topic'], array_column($activeRows, 'title'));

        $pageTwo = $repo->inbox(
            (int) $viewer['id'],
            'starred',
            'commended',
            false,
            ThreadUserRepository::NO_CUTOVER,
            1,
            1,
        );
        self::assertSame(['Commended topic'], array_column($pageTwo, 'title'));

        self::assertSame(0, $repo->countInbox(
            (int) $viewer['id'],
            'mentions',
            'active',
            false,
            ThreadUserRepository::NO_CUTOVER,
            true,
            false,
        ));
        self::assertSame(0, $repo->countInbox(
            (int) $viewer['id'],
            'needs_answer',
            'active',
            false,
            ThreadUserRepository::NO_CUTOVER,
            false,
            true,
        ));
    }

    public function test_route_renders_independent_axes_grouped_counts_and_canonical_fallbacks(): void
    {
        $viewer = $this->makeUser();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'inbox-contract']);
        $thread = $this->makeThread($board, $author, 'An inbox topic', 'A useful opening.');
        $this->threadUsers()->setStar((int) $viewer['id'], (int) $thread['thread_id'], true);
        $this->actingAs($viewer);

        $response = $this->get('/inbox', ['scope' => 'starred', 'order' => 'commended']);
        $this->assertStatus(200, $response);
        self::assertStringContainsString('data-inbox-scope="starred"', $response->body());
        self::assertStringContainsString('data-inbox-order="commended"', $response->body());
        self::assertStringContainsString('Your queue', $response->body());
        self::assertStringContainsString('Yours', $response->body());
        self::assertStringContainsString('Topic state', $response->body());
        self::assertStringContainsString('data-inbox-scope-count="starred">1<', $response->body());
        self::assertStringContainsString('/inbox?scope=starred&amp;order=active', $response->body());
        self::assertStringContainsString('/inbox?scope=starred&amp;order=newest', $response->body());
        self::assertStringContainsString('action="/inbox/bulk"', $response->body());
        self::assertStringContainsString('name="thread_ids[]" value="' . (int) $thread['thread_id'] . '"', $response->body());
        self::assertStringContainsString('data-inbox-preview-url="/inbox/preview/' . (int) $thread['thread_id'] . '"', $response->body());
        self::assertStringContainsString('href="/t/' . (int) $thread['thread_id'] . '-' . $thread['slug'] . '"', $response->body());

        preg_match_all('/\sid="([^"]+)"/', $response->body(), $ids);
        self::assertSame(array_values(array_unique($ids[1])), $ids[1], 'Rendered ids must be unique.');

        $legacy = $this->get('/inbox', ['filter' => 'newest']);
        $this->assertRedirectContains($legacy, '/inbox?scope=for_you&order=newest');

        $invalid = $this->get('/inbox', ['scope' => 'not-a-scope', 'order' => 'not-an-order']);
        $this->assertStatus(200, $invalid);
        self::assertStringContainsString('data-inbox-scope="for_you"', $invalid->body());
        self::assertStringContainsString('data-inbox-order="active"', $invalid->body());
    }

    public function test_snoozed_scope_reapplies_board_visibility_after_membership_is_revoked(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeUser();
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $thread = $this->makeThread($private, $admin, 'Revoked snooze', 'Private.');
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $private['id'], (int) $viewer['id'], (int) $admin['id']);
        $this->threadUsers()->setSnooze((int) $viewer['id'], (int) $thread['thread_id'], '2999-01-01 00:00:00');

        $visible = $this->threadUsers()->inbox(
            (int) $viewer['id'],
            'snoozed',
            'active',
            false,
            ThreadUserRepository::NO_CUTOVER,
            20,
            0,
        );
        self::assertSame(['Revoked snooze'], array_column($visible, 'title'));

        $members->remove((int) $private['id'], (int) $viewer['id']);
        $revoked = $this->threadUsers()->inbox(
            (int) $viewer['id'],
            'snoozed',
            'active',
            false,
            ThreadUserRepository::NO_CUTOVER,
            20,
            0,
        );
        self::assertSame([], $revoked);
    }

    public function test_empty_states_name_the_scope_without_conflating_order(): void
    {
        $viewer = $this->makeUser();
        $this->actingAs($viewer);

        $forYou = $this->get('/inbox', ['scope' => 'for_you', 'order' => 'commended']);
        $this->assertSeeText($forYou, 'Nothing needs your attention right now.');
        $this->assertSeeText($forYou, 'Order (most commended) changes the sequence, never what is included.');

        $unread = $this->get('/inbox', ['scope' => 'unread', 'order' => 'newest']);
        self::assertStringContainsString('You&#039;re all caught up — nothing unread.', $unread->body());

        $starred = $this->get('/inbox', ['scope' => 'starred', 'order' => 'active']);
        $this->assertSeeText($starred, 'Nothing in Starred.');
        self::assertStringContainsString('/inbox?scope=for_you&amp;order=active', $starred->body());
    }

    public function test_preview_is_read_gated_bounded_anonymous_and_uses_the_canonical_reply_path(): void
    {
        $viewer = $this->makeUser();
        $author = $this->makeUser(['display_name' => 'Visible Real Name']);
        $replier = $this->makeUser();
        $public = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $hidden = $this->makeBoard($this->makeCategory(), ['visibility' => 'hidden']);
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $author['id'], null);
        $thread = $this->makeThread($public, $author, 'Bounded preview', 'Secret starter identity.');
        for ($i = 1; $i <= 5; $i++) {
            $this->posting()->reply($this->userEntity($replier), (int) $thread['thread_id'], ['body' => 'Preview reply ' . $i]);
        }
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE thread_id = ? AND is_op = 1', [(int) $thread['thread_id']]);
        $privateThread = $this->makeThread($private, $author, 'Private preview', 'Private.');
        $hiddenThread = $this->makeThread($hidden, $author, 'Hidden preview', 'Hidden but directly readable.');

        $this->actingAs($viewer);
        $response = $this->get('/inbox/preview/' . (int) $thread['thread_id']);
        $this->assertStatus(200, $response);
        self::assertStringContainsString('data-inbox-preview="' . (int) $thread['thread_id'] . '"', $response->body());
        self::assertStringContainsString('Anonymous', $response->body());
        self::assertStringNotContainsString('Visible Real Name', $response->body());
        self::assertStringContainsString('href="/t/' . (int) $thread['thread_id'] . '-' . $thread['slug'] . '"', $response->body());
        self::assertStringContainsString('action="/t/' . (int) $thread['thread_id'] . '/reply"', $response->body());
        self::assertSame(4, substr_count($response->body(), 'data-inbox-preview-post'));
        self::assertStringNotContainsString('Preview reply 4', $response->body());
        self::assertStringNotContainsString('<html', $response->body());
        self::assertStringNotContainsString('/status', $response->body());
        self::assertStringNotContainsString('/pin', $response->body());

        $this->assertStatus(404, $this->get('/inbox/preview/' . (int) $privateThread['thread_id']));
        $this->assertStatus(200, $this->get('/inbox/preview/' . (int) $hiddenThread['thread_id']));
        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $viewer['id'], null);
        $this->assertStatus(200, $this->get('/inbox/preview/' . (int) $privateThread['thread_id']));

        $this->db->run('UPDATE threads SET is_locked = 1 WHERE id = ?', [(int) $thread['thread_id']]);
        $locked = $this->get('/inbox/preview/' . (int) $thread['thread_id']);
        $this->assertSeeText($locked, 'This topic is locked.');
        self::assertStringNotContainsString('action="/t/' . (int) $thread['thread_id'] . '/reply"', $locked->body());

        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [(int) $thread['thread_id']]);
        $this->assertStatus(404, $this->get('/inbox/preview/' . (int) $thread['thread_id']));
        $this->db->run('UPDATE threads SET is_pending = 0, is_deleted = 1 WHERE id = ?', [(int) $thread['thread_id']]);
        $this->assertStatus(404, $this->get('/inbox/preview/' . (int) $thread['thread_id']));
    }

    public function test_bulk_actions_are_current_view_scoped_atomic_and_preserve_read_only_suspension(): void
    {
        $member = $this->makeUser();
        $other = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $outsideBoard = $this->makeBoard($this->makeCategory());
        $first = $this->makeThread($board, $member, 'Mine one', 'First.');
        $second = $this->makeThread($board, $member, 'Mine two', 'Second.');
        $outside = $this->makeThread($outsideBoard, $other, 'Not mine', 'Outside.');
        $firstId = (int) $first['thread_id'];
        $secondId = (int) $second['thread_id'];
        $outsideId = (int) $outside['thread_id'];

        $this->actingAs($member);
        $csrf = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'star',
            'thread_ids' => [$firstId],
        ], false);
        $this->assertStatus(403, $csrf);

        $missing = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'star',
        ]);
        $this->assertRedirectContains($missing, '/inbox?scope=mine&order=active');
        self::assertFalse($this->threadUsers()->isStarred((int) $member['id'], $firstId));

        $partial = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'star',
            'thread_ids' => [$firstId, $outsideId],
        ]);
        $this->assertRedirectContains($partial, '/inbox?scope=mine&order=active');
        self::assertFalse($this->threadUsers()->isStarred((int) $member['id'], $firstId));
        self::assertFalse($this->threadUsers()->isStarred((int) $member['id'], $outsideId));

        $unread = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'unread',
            'thread_ids' => [$firstId, $secondId],
        ]);
        $this->assertRedirectContains($unread, '/inbox?scope=mine&order=active');
        self::assertTrue($this->threadUsers()->unreadFlags(
            (int) $member['id'],
            [$firstId, $secondId],
            ThreadUserRepository::NO_CUTOVER,
        )[$firstId]);

        $read = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'read',
            'thread_ids' => [$firstId, $secondId],
        ]);
        $this->assertRedirectContains($read, '/inbox?scope=mine&order=active');
        self::assertFalse($this->threadUsers()->unreadFlags(
            (int) $member['id'],
            [$firstId, $secondId],
            ThreadUserRepository::NO_CUTOVER,
        )[$firstId]);

        $star = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'star',
            'thread_ids' => [$firstId, $secondId],
        ]);
        $this->assertRedirectContains($star, '/inbox?scope=mine&order=active');
        self::assertTrue($this->threadUsers()->isStarred((int) $member['id'], $firstId));
        self::assertTrue($this->threadUsers()->isStarred((int) $member['id'], $secondId));

        $snooze = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'snooze',
            'thread_ids' => [$firstId],
            'until' => 'monday',
        ]);
        $this->assertRedirectContains($snooze, '/inbox?scope=mine&order=active');
        self::assertNotNull($this->threadUsers()->find((int) $member['id'], $firstId)['snoozed_until']);

        $suspended = $this->makeUser();
        $suspendedThread = $this->makeThread($board, $suspended, 'Suspended reader topic', 'Readable.');
        $this->users()->setStatus((int) $suspended['id'], 'suspended', '2999-01-01 00:00:00');
        $suspended = $this->users()->find((int) $suspended['id']);
        self::assertNotNull($suspended);
        $this->actingAs($suspended);
        $suspendedUnread = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'unread',
            'thread_ids' => [(int) $suspendedThread['thread_id']],
        ]);
        $this->assertRedirectContains($suspendedUnread, '/inbox?scope=mine&order=active');
        $suspendedStar = $this->post('/inbox/bulk', [
            'scope' => 'mine',
            'order' => 'active',
            'action' => 'star',
            'thread_ids' => [(int) $suspendedThread['thread_id']],
        ]);
        $this->assertStatus(403, $suspendedStar);
    }
}
