<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * PR #44 spec §3: board-delete validation runs INSIDE the delete transaction
 * against exclusively locked rows, so a destination that changed after the
 * preview offered it is re-judged from the locked row. Deterministic
 * deterministic state drift between preview and submit, while retaining the
 * normal per-test rollback transaction and shared-database isolation.
 */
#[Group('nonparallel')]
final class AdminBoardDeleteConcurrencyTest extends TestCase
{
    public function test_destination_archived_after_the_preview_is_refused_by_the_locked_revalidation(): void
    {
        $admin = $this->makeAdmin(['username' => 'raceadmin']);
        $category = $this->makeCategory('Race');
        $src = $this->makeBoard($category, ['name' => 'Race Source']);
        $dest = $this->makeBoard($category, ['name' => 'Race Destination']);
        // A second unarchived candidate so the 422 re-render surfaces the
        // archived-destination error (with no alternative at all it would
        // truthfully switch to the "blocked" banner instead).
        $this->makeBoard($category, ['name' => 'Race Bystander']);
        $author = $this->makeUser();
        $this->makeThread($src, $author, 'Race cargo', 'Body.');
        $this->actingAs($admin);

        // The preview offers the destination while it is still unarchived.
        $confirm = $this->get('/admin/boards/' . (int) $src['id'] . '/delete');
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, 'Race Destination');

        // State changes AFTER the preview offered it. The submit must rebuild
        // its decision from the transaction-locked row.
        $this->db->run('UPDATE boards SET is_archived = 1 WHERE id = ?', [(int) $dest['id']]);

        $res = $this->post('/admin/boards/' . (int) $src['id'] . '/delete', [
            'confirm' => (string) $src['slug'],
            'move_to_board_id' => (string) (int) $dest['id'],
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'archived (read-only)');
        // Nothing moved, nothing deleted, nothing audited.
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM boards WHERE id = ?', [(int) $src['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [(int) $src['id']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [(int) $dest['id']]));
        $destRow = $this->db->fetch('SELECT thread_count, post_count, is_archived FROM boards WHERE id = ?', [(int) $dest['id']]);
        self::assertSame(0, (int) $destRow['thread_count']);
        self::assertSame(0, (int) $destRow['post_count']);
        self::assertSame(1, (int) $destRow['is_archived']);
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action IN ('move_board_content', 'delete_board')"),
        );
    }
}
