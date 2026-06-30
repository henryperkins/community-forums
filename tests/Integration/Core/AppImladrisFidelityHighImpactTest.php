<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * High-impact Imladris fidelity pass (closing the verified export↔repo gaps):
 *  - poll "Closes" control persists the already-supported polls.closes_at (#20)
 *  - "In council" eyebrow over the participant stack (#1)
 *  - notification per-type icon + body wrapper + unread dot (#2)
 *  - connections @handle uses the mono .handle class (#3)
 *  - admin Users roster + record render .role-pill + .state status chips (#10/#11)
 *  - DM gilt monograms: group rows + open-letter head (#16)
 *
 * Pure-CSS items in the same pass — .flash-error (#9) and .admin-cat-head (#8) —
 * have no PHPUnit-observable behaviour (the classes are already emitted; only the
 * stylesheet changes), so they are verified with browser evidence, not here.
 */
final class AppImladrisFidelityHighImpactTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    // ── #20 poll Closes control ──────────────────────────────────────────────

    public function test_poll_create_persists_a_closing_window_when_chosen(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'closes_author']);
        $board = $this->makeBoard($this->makeCategory('Closing Polls'));
        $thread = $this->makeThread($board, $author, 'Closing poll', 'body');
        $this->actingAs($author);

        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Advance which proposal?',
            'mode' => 'single',
            'options' => "Tags\nFeeds",
            'closes_in' => '1d',
        ]), '/t/' . $thread['thread_id']);

        $row = $this->db->fetch('SELECT closes_at FROM polls WHERE thread_id = ?', [$thread['thread_id']]);
        self::assertNotNull($row);
        self::assertNotNull($row['closes_at'], 'closes_at should be persisted when a window is chosen');
        // The window is in the future, so the poll is still open right now.
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT closes_at > UTC_TIMESTAMP() FROM polls WHERE thread_id = ?',
            [$thread['thread_id']],
        ));
    }

    public function test_poll_create_leaves_the_window_open_ended_by_default(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'never_author']);
        $board = $this->makeBoard($this->makeCategory('Open Polls'));
        $thread = $this->makeThread($board, $author, 'Open poll', 'body');
        $this->actingAs($author);

        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Open ended?',
            'mode' => 'single',
            'options' => "Yes\nNo",
            'closes_in' => 'never',
        ]), '/t/' . $thread['thread_id']);

        self::assertNull(
            $this->db->fetchValue('SELECT closes_at FROM polls WHERE thread_id = ?', [$thread['thread_id']]),
            'closes_at stays NULL when no closing window is chosen',
        );
    }

    public function test_poll_builder_offers_a_closing_window_control(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'builder_author']);
        $board = $this->makeBoard($this->makeCategory('Builder Polls'));
        $thread = $this->makeThread($board, $author, 'Builder poll', 'body');
        $this->actingAs($author);

        $res = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('name="closes_in"', $res->body());
    }

    // ── #1 "In council" participant-stack label ──────────────────────────────

    public function test_thread_header_labels_the_participant_stack(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'council_op']);
        $replier = $this->makeUser(['username' => 'council_voice']);
        $board = $this->makeBoard($this->makeCategory('Council'));
        $thread = $this->makeThread($board, $author, 'A matter for counsel', 'Opening.');

        $this->actingAs($replier);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'A considered reply.']);

        $res = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('thread-participants-label', $res->body());
        self::assertStringContainsString('In council', $res->body());
    }

    // ── #2 notification icon + body + unread dot ──────────────────────────────

    public function test_notifications_render_a_type_icon_body_and_unread_dot(): void
    {
        $this->makeAdmin();
        $target = $this->makeUser(['username' => 'notif_target']);
        $actor = $this->makeUser(['username' => 'notif_actor']);

        // A follow seeds exactly one 'follow' notification for the target.
        $this->actingAs($actor);
        $this->post('/u/notif_target/follow');

        $this->actingAs($target);
        $res = $this->get('/notifications');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('class="notif-icon"', $res->body());
        self::assertStringContainsString('<svg', $res->body());
        self::assertStringContainsString('class="notif-body"', $res->body());
        self::assertStringContainsString('notif-dot', $res->body());
    }

    // ── #3 connections @handle mono class ─────────────────────────────────────

    public function test_connections_list_uses_the_mono_handle_class(): void
    {
        $this->makeAdmin();
        $profile = $this->makeUser(['username' => 'conn_profile']);
        $follower = $this->makeUser(['username' => 'conn_follower']);

        $this->actingAs($follower);
        $this->post('/u/conn_profile/follow');

        $res = $this->get('/u/conn_profile/followers');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('class="handle"', $res->body());
    }

    // ── #10/#11 admin role-pill + state chip ──────────────────────────────────

    public function test_admin_users_roster_uses_role_pill_and_state_chip(): void
    {
        $admin = $this->makeAdmin(['username' => 'roster_admin']);
        $this->makeUser(['username' => 'roster_member']);
        $this->actingAs($admin);

        $res = $this->get('/admin/users');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('role-pill', $res->body());
        self::assertStringContainsString('class="state state-', $res->body());
    }

    public function test_admin_user_record_uses_role_pill_and_state_chip(): void
    {
        $admin = $this->makeAdmin(['username' => 'record_admin']);
        $subject = $this->makeUser(['username' => 'record_subject']);
        $this->actingAs($admin);

        $res = $this->get('/admin/users/' . (int) $subject['id']);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('role-pill', $res->body());
        self::assertStringContainsString('class="state state-', $res->body());
    }

    // ── #16 DM gilt monograms ─────────────────────────────────────────────────

    public function test_dm_open_letter_head_uses_a_gilt_monogram(): void
    {
        // The sender is an admin: exempt from the new-account DM throttle and
        // satisfies the first-run setup gate. The gilt monogram is role-independent.
        $alice = $this->makeAdmin(['username' => 'gilt_alice']);
        $this->makeUser(['username' => 'gilt_bob']);
        $this->actingAs($alice);

        $start = $this->post('/messages', ['to' => 'gilt_bob', 'body' => 'A first letter.']);
        $this->assertRedirectContains($start, '/messages/');
        $convId = (int) preg_replace('#^.*/messages/#', '', (string) $start->getHeader('location'));

        $res = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('monogram-gilt', $res->body());
    }

    public function test_dm_group_row_uses_a_gilt_monogram(): void
    {
        $this->setFlags(['group_dms' => true]);
        // Admin owner: exempt from the new-account DM throttle (and seeds the setup gate).
        $owner = $this->makeAdmin(['username' => 'circle_owner']);
        $this->makeUser(['username' => 'circle_one']);
        $this->makeUser(['username' => 'circle_two']);
        $this->actingAs($owner);

        $start = $this->post('/messages', [
            'to' => 'circle_one, circle_two',
            'title' => 'Council circle',
            'body' => 'Gather, counsellors.',
        ]);
        $this->assertRedirectContains($start, '/messages/');

        $res = $this->get('/messages');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('monogram-gilt', $res->body());
    }
}
