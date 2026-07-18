<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\BoardRepository;
use Tests\Support\TestCase;

/**
 * Board-settings coverage for the 2026-07-18 remediation: post_min_role is
 * UI-configurable and never silently reset, the edit window is configurable
 * and enforced, and a non-empty board can be deleted by moving its threads.
 */
final class AdminBoardSettingsTest extends TestCase
{
    /** @return array{admin:array<string,mixed>,board:array<string,mixed>,category:int} */
    private function seedBoard(array $attrs = []): array
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $category = $this->makeCategory('Structure');
        $board = $this->makeBoard($category, $attrs);
        return ['admin' => $admin, 'board' => $board, 'category' => $category];
    }

    /** The UI-shaped update POST (every field the form submits). @return array<string,mixed> */
    private function uiUpdateBody(array $board, array $overrides = []): array
    {
        return $overrides + [
            'category_id' => (string) $board['category_id'],
            'name' => (string) $board['name'],
            'slug' => (string) $board['slug'],
            'description' => (string) ($board['description'] ?? ''),
            'visibility' => (string) $board['visibility'],
            'post_min_role' => (string) ($board['post_min_role'] ?? 'user'),
            'edit_window_minutes' => (string) intdiv((int) ($board['edit_window_seconds'] ?? 0), 60),
            'assignment_mode' => (string) ($board['assignment_mode'] ?? 'off'),
            'wiki_enabled' => '',
        ];
    }

    public function test_ui_update_without_post_min_role_field_preserves_stored_value(): void
    {
        $seed = $this->seedBoard(['post_min_role' => 'admin', 'name' => 'Announcements']);
        $this->actingAs($seed['admin']);

        // A form that omits the field entirely (the pre-fix regression shape)
        // must not silently re-open an admins-only board.
        $body = $this->uiUpdateBody($seed['board'], ['description' => 'edited']);
        unset($body['post_min_role'], $body['edit_window_minutes']);
        $res = $this->post('/admin/boards/' . (int) $seed['board']['id'], $body);

        $this->assertRedirectContains($res, '/admin/structure');
        $row = (new BoardRepository($this->db))->find((int) $seed['board']['id']);
        self::assertSame('admin', $row['post_min_role']);
    }

    public function test_post_min_role_is_settable_from_the_form(): void
    {
        $seed = $this->seedBoard();
        $this->actingAs($seed['admin']);

        $res = $this->post(
            '/admin/boards/' . (int) $seed['board']['id'],
            $this->uiUpdateBody($seed['board'], ['post_min_role' => 'admin']),
        );

        $this->assertRedirectContains($res, '/admin/structure');
        $row = (new BoardRepository($this->db))->find((int) $seed['board']['id']);
        self::assertSame('admin', $row['post_min_role']);
    }

    public function test_invalid_post_min_role_is_a_422_and_keeps_the_stored_value(): void
    {
        $seed = $this->seedBoard(['post_min_role' => 'moderator']);
        $this->actingAs($seed['admin']);

        $res = $this->post(
            '/admin/boards/' . (int) $seed['board']['id'],
            $this->uiUpdateBody($seed['board'], ['post_min_role' => 'overlord']),
        );

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Choose who can post');
        $row = (new BoardRepository($this->db))->find((int) $seed['board']['id']);
        self::assertSame('moderator', $row['post_min_role']);
    }

    public function test_edit_window_minutes_persist_as_seconds(): void
    {
        $seed = $this->seedBoard();
        $this->actingAs($seed['admin']);

        $res = $this->post(
            '/admin/boards/' . (int) $seed['board']['id'],
            $this->uiUpdateBody($seed['board'], ['edit_window_minutes' => '15']),
        );

        $this->assertRedirectContains($res, '/admin/structure');
        $row = (new BoardRepository($this->db))->find((int) $seed['board']['id']);
        self::assertSame(900, (int) $row['edit_window_seconds']);
    }

    public function test_expired_edit_window_blocks_member_edit_with_422_and_preserves_text(): void
    {
        $seed = $this->seedBoard();
        $author = $this->makeUser(['password' => 'password123']);
        $made = $this->makeThread($seed['board'], $author, 'Window topic', 'Original body text.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$made['thread_id']]);

        // One-minute window; the post is backdated an hour.
        $this->db->run('UPDATE boards SET edit_window_seconds = 60 WHERE id = ?', [(int) $seed['board']['id']]);
        $this->db->run('UPDATE posts SET created_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = ?', [$postId]);

        $this->actingAs($author);
        $res = $this->post('/posts/' . $postId . '/edit', ['body' => 'My carefully rewritten body.']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'edit window');
        $this->assertSeeText($res, 'My carefully rewritten body.');
        self::assertSame('Original body text.', (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$postId]));
    }

    public function test_staff_are_exempt_from_the_edit_window(): void
    {
        $seed = $this->seedBoard();
        $adminAuthor = $this->makeAdmin(['password' => 'password123']);
        $made = $this->makeThread($seed['board'], $adminAuthor, 'Staff topic', 'Original.');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$made['thread_id']]);
        $this->db->run('UPDATE boards SET edit_window_seconds = 60 WHERE id = ?', [(int) $seed['board']['id']]);
        $this->db->run('UPDATE posts SET created_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR WHERE id = ?', [$postId]);

        $this->actingAs($adminAuthor);
        $res = $this->post('/posts/' . $postId . '/edit', ['body' => 'Staff edit lands.']);

        $this->assertRedirect($res);
        self::assertSame('Staff edit lands.', (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$postId]));
    }

    public function test_delete_with_move_relocates_threads_recounts_and_deletes(): void
    {
        $seed = $this->seedBoard(['name' => 'Retiring']);
        $dest = $this->makeBoard($seed['category'], ['name' => 'Destination']);
        $author = $this->makeUser();
        $this->makeThread($seed['board'], $author, 'Carries over', 'Body.');
        $srcId = (int) $seed['board']['id'];
        $destId = (int) $dest['id'];

        $this->actingAs($seed['admin']);
        $res = $this->post('/admin/boards/' . $srcId . '/delete', [
            'confirm' => (string) $seed['board']['slug'],
            'move_to_board_id' => (string) $destId,
        ]);

        $this->assertRedirectContains($res, '/admin/structure');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM boards WHERE id = ?', [$srcId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [$destId]));
        $destRow = (new BoardRepository($this->db))->find($destId);
        self::assertSame(1, (int) $destRow['thread_count']);
        self::assertSame(1, (int) $destRow['post_count']);
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'move_board_content' AND target_id = ?", [$srcId]),
        );
    }

    public function test_delete_with_threads_and_no_destination_is_refused(): void
    {
        $seed = $this->seedBoard();
        $author = $this->makeUser();
        $this->makeThread($seed['board'], $author, 'Still here', 'Body.');

        $this->actingAs($seed['admin']);
        $res = $this->post('/admin/boards/' . (int) $seed['board']['id'] . '/delete', [
            'confirm' => (string) $seed['board']['slug'],
        ]);

        $this->assertStatus(422, $res);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM boards WHERE id = ?', [(int) $seed['board']['id']]));
    }

    public function test_confirm_page_offers_destination_for_non_empty_board(): void
    {
        $seed = $this->seedBoard();
        $this->makeBoard($seed['category'], ['name' => 'Elsewhere']);
        $author = $this->makeUser();
        $this->makeThread($seed['board'], $author, 'Occupied', 'Body.');

        $this->actingAs($seed['admin']);
        $res = $this->get('/admin/boards/' . (int) $seed['board']['id'] . '/delete');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'move_to_board_id');
        $this->assertSeeText($res, 'Move threads and delete board');
    }

    public function test_hidden_content_board_previews_the_authoritative_count_and_deletes_as_previewed(): void
    {
        // PR #44 spec §3: the old preview read the denormalised thread_count
        // (excludes hidden/held/deleted rows) while the POST gate counted raw
        // rows — a soft-deleted-only board previewed as "0 threads, deletable"
        // then dead-ended at 422 with no destination select on the page.
        $seed = $this->seedBoard(['name' => 'Shadowed']);
        $dest = $this->makeBoard($seed['category'], ['name' => 'Receiver']);
        $author = $this->makeUser();
        $made = $this->makeThread($seed['board'], $author, 'Hidden cargo', 'Body.');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [(int) $made['thread_id']]);
        $this->db->run('UPDATE boards SET thread_count = 0, post_count = 0 WHERE id = ?', [(int) $seed['board']['id']]);
        $srcId = (int) $seed['board']['id'];
        $destId = (int) $dest['id'];

        $this->actingAs($seed['admin']);
        $confirm = $this->get('/admin/boards/' . $srcId . '/delete');
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, '1 (including hidden, held, and deleted)');
        $this->assertSeeText($confirm, 'move_to_board_id');

        // Without a destination the delete is refused — as the preview said.
        $refused = $this->post('/admin/boards/' . $srcId . '/delete', ['confirm' => (string) $seed['board']['slug']]);
        $this->assertStatus(422, $refused);

        // With one, it completes exactly as previewed: the hidden row moves,
        // and the destination's counters exclude it (recount predicates).
        $ok = $this->post('/admin/boards/' . $srcId . '/delete', [
            'confirm' => (string) $seed['board']['slug'],
            'move_to_board_id' => (string) $destId,
        ]);
        $this->assertRedirectContains($ok, '/admin/structure');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM boards WHERE id = ?', [$srcId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [$destId]));
        $destRow = (new BoardRepository($this->db))->find($destId);
        self::assertSame(0, (int) $destRow['thread_count']);
        self::assertSame(0, (int) $destRow['post_count']);
        // The flash reports the actual moved count, not the stale denorm.
        $this->assertSeeText($this->get('/admin/structure'), 'Moved 1 thread and deleted the board.');
    }

    public function test_pending_content_board_previews_and_deletes_the_held_thread(): void
    {
        $seed = $this->seedBoard(['name' => 'Holding']);
        $dest = $this->makeBoard($seed['category'], ['name' => 'Landing']);
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $seed['board']['id']]);
        $author = $this->makeUser();
        $made = $this->makeThread($seed['board'], $author, 'Held cargo', 'Body.');
        self::assertSame(
            1,
            (int) $this->db->fetchValue('SELECT is_pending FROM threads WHERE id = ?', [(int) $made['thread_id']]),
            'fixture: the thread must be held',
        );
        $srcId = (int) $seed['board']['id'];
        $destId = (int) $dest['id'];

        $this->actingAs($seed['admin']);
        $confirm = $this->get('/admin/boards/' . $srcId . '/delete');
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, '1 (including hidden, held, and deleted)');

        $refused = $this->post('/admin/boards/' . $srcId . '/delete', ['confirm' => (string) $seed['board']['slug']]);
        $this->assertStatus(422, $refused);

        $ok = $this->post('/admin/boards/' . $srcId . '/delete', [
            'confirm' => (string) $seed['board']['slug'],
            'move_to_board_id' => (string) $destId,
        ]);
        $this->assertRedirectContains($ok, '/admin/structure');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [$destId]));
        $destRow = (new BoardRepository($this->db))->find($destId);
        self::assertSame(0, (int) $destRow['thread_count'], 'held rows stay out of the visible counters');
        self::assertSame(0, (int) $destRow['post_count']);
    }
}
