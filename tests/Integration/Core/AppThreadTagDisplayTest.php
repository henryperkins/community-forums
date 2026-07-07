<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\TagRepository;
use Tests\Support\TestCase;

/**
 * Inc 6 follow-up (V7): tag-edit display must match the tag-write gate
 * one-to-one. TagController::updateThread deliberately has NO staff
 * carve-out ("posting rights are the single tagging gate", [STATE-KEEP]),
 * so a moderator arm on the display flag is a phantom control: staff who
 * cannot post into a board saw an Edit-tags control whose submit 403'd —
 * and, paired with the user-baseline core.thread.tag key, it emitted a
 * resolver shadow mismatch on virtually every member thread view, making
 * a clean shadow soak unreachable.
 */
final class AppThreadTagDisplayTest extends TestCase
{
    /** @return array{thread:array<string,mixed>,url:string} */
    private function threadUrl(array $thread): array
    {
        $slug = (string) $this->db->fetchValue('SELECT slug FROM threads WHERE id = ?', [(int) $thread['thread_id']]);

        return ['thread' => $thread, 'url' => '/t/' . (int) $thread['thread_id'] . '-' . $slug];
    }

    public function test_member_who_can_post_sees_the_editor_and_can_save(): void
    {
        $this->makeAdmin(); // installed site (setup gate)
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $member);
        $tagId = (new TagRepository($this->db))->create('display-guard', 'Display Guard', null, (int) $member['id']);

        $this->actingAs($member);
        $page = $this->get($this->threadUrl($thread)['url']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Edit tags');

        $resp = $this->post('/t/' . (int) $thread['thread_id'] . '/tags', ['tag_ids' => [$tagId]]);
        $this->assertRedirect($resp);
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_tags WHERE thread_id = ? AND tag_id = ?',
            [(int) $thread['thread_id'], $tagId],
        ));
    }

    public function test_staff_without_posting_rights_get_no_phantom_tag_control(): void
    {
        $admin = $this->makeAdmin();
        $deputy = $this->makeUser(['username' => 'tagdeputy']);
        $board = $this->makeBoard($this->makeCategory(), ['post_min_role' => 'moderator']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $deputy['id']);
        $thread = $this->makeThread($board, $admin);
        (new TagRepository($this->db))->create('phantom-probe', 'Phantom Probe', null, (int) $admin['id']);

        // The write gate has no staff carve-out: a plain-user board moderator
        // cannot post past the board floor, so tagging is denied…
        $this->actingAs($deputy);
        $this->assertStatus(403, $this->post('/t/' . (int) $thread['thread_id'] . '/tags', ['tag_ids' => []]));

        // …therefore the thread view must not render the control either.
        $page = $this->get($this->threadUrl($thread)['url']);
        $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, 'Edit tags');
    }

    public function test_archived_board_shows_no_tag_control_even_to_admins(): void
    {
        $admin = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $admin);
        (new TagRepository($this->db))->create('archive-probe', 'Archive Probe', null, (int) $admin['id']);
        $this->db->run('UPDATE boards SET is_archived = 1 WHERE id = ?', [(int) $board['id']]);

        $this->actingAs($admin);
        $this->assertStatus(403, $this->post('/t/' . (int) $thread['thread_id'] . '/tags', ['tag_ids' => []]));

        $page = $this->get($this->threadUrl($thread)['url']);
        $this->assertStatus(200, $page);
        $this->assertDontSeeText($page, 'Edit tags');
    }

    public function test_admin_who_can_post_still_sees_the_editor(): void
    {
        $admin = $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory(), ['post_min_role' => 'moderator']);
        $thread = $this->makeThread($board, $admin);
        (new TagRepository($this->db))->create('admin-guard', 'Admin Guard', null, (int) $admin['id']);

        $this->actingAs($admin);
        $page = $this->get($this->threadUrl($thread)['url']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Edit tags');
    }
}
