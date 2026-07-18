<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use Tests\Support\TestCase;

/**
 * PR #44 remediation (spec §1): every moderation-route render — including the
 * failure re-renders — runs behind the same thread read gate as GET /t/{id},
 * and checks order read (404) → authority (403) → validation (422) so no
 * response distinguishes "exists but unreadable" from "does not exist".
 * Board-moderator assignment counts as readable in moderation flows.
 */
final class AppModerationReadGateTest extends TestCase
{
    private const TITLE = 'Secret migration postmortem zq1';
    private const BODY = 'Sensitive opening post body zq2.';

    /**
     * @return array{board:array<string,mixed>,thread_id:int,slug:string,author:array<string,mixed>}
     */
    private function seedPrivateThread(): array
    {
        $this->makeAdmin(); // past the first-run setup gate
        $board = $this->makeBoard($this->makeCategory('Vault'), ['visibility' => 'private']);
        $author = $this->makeUser(['username' => 'vaultauthor']);
        (new BoardMemberRepository($this->db))->add((int) $board['id'], (int) $author['id'], null);
        $made = $this->makeThread($board, $author, self::TITLE, self::BODY);
        return ['board' => $board, 'thread_id' => (int) $made['thread_id'], 'slug' => (string) $made['slug'], 'author' => $author];
    }

    /**
     * @return array{board:array<string,mixed>,thread_id:int,slug:string,author:array<string,mixed>}
     */
    private function seedPendingThread(): array
    {
        $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory('Held'));
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $board['id']]);
        $author = $this->makeUser(['username' => 'heldauthor']);
        $made = $this->makeThread($board, $author, self::TITLE, self::BODY);
        self::assertSame(
            1,
            (int) $this->db->fetchValue('SELECT is_pending FROM threads WHERE id = ?', [(int) $made['thread_id']]),
            'fixture: the thread must be held for approval',
        );
        return ['board' => $board, 'thread_id' => (int) $made['thread_id'], 'slug' => (string) $made['slug'], 'author' => $author];
    }

    // ---- private-thread disclosure through moderation failure paths --------

    public function test_move_with_invalid_destination_is_404_for_an_unreadable_actor(): void
    {
        $seed = $this->seedPrivateThread();
        $this->actingAs($this->makeUser(['username' => 'outsider1']));

        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/move', ['board_id' => '0']);

        $this->assertStatus(404, $res);
        $this->assertDontSeeText($res, self::TITLE);
        $this->assertDontSeeText($res, self::BODY);
    }

    public function test_same_board_move_is_404_for_an_unreadable_actor_not_a_slug_leaking_redirect(): void
    {
        $seed = $this->seedPrivateThread();
        $this->actingAs($this->makeUser(['username' => 'outsider2']));

        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/move', ['board_id' => (string) (int) $seed['board']['id']]);

        $this->assertStatus(404, $res);
        self::assertStringNotContainsString($seed['slug'], (string) $res->getHeader('location'));
    }

    public function test_merge_with_same_target_is_404_for_an_unreadable_actor(): void
    {
        $seed = $this->seedPrivateThread();
        $this->actingAs($this->makeUser(['username' => 'outsider3']));

        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/merge', ['target_thread_id' => (string) $seed['thread_id']]);

        $this->assertStatus(404, $res);
        $this->assertDontSeeText($res, self::TITLE);
    }

    public function test_pin_is_404_for_an_unreadable_actor_not_a_403_existence_oracle(): void
    {
        $seed = $this->seedPrivateThread();
        $this->actingAs($this->makeUser(['username' => 'outsider4']));

        $this->assertStatus(404, $this->post('/mod/t/' . $seed['thread_id'] . '/pin'));
    }

    // ---- pending (held) thread variants ------------------------------------

    public function test_pending_thread_move_failure_is_404_for_a_non_author(): void
    {
        $seed = $this->seedPendingThread();
        $this->actingAs($this->makeUser(['username' => 'nosy1']));

        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/move', ['board_id' => '0']);

        $this->assertStatus(404, $res);
        $this->assertDontSeeText($res, self::TITLE);
    }

    public function test_pending_thread_merge_failure_is_404_for_a_non_author(): void
    {
        $seed = $this->seedPendingThread();
        $this->actingAs($this->makeUser(['username' => 'nosy2']));

        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/merge', ['target_thread_id' => (string) $seed['thread_id']]);

        $this->assertStatus(404, $res);
        $this->assertDontSeeText($res, self::TITLE);
    }

    // ---- assignment ⇒ readable (spec §1 decision) --------------------------

    public function test_assigned_non_member_moderator_can_read_the_private_thread(): void
    {
        $seed = $this->seedPrivateThread();
        $mod = $this->makeUser(['username' => 'assignedmod']);
        (new BoardModeratorRepository($this->db))->assign((int) $seed['board']['id'], (int) $mod['id']);

        $this->actingAs($mod);
        $res = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, self::TITLE);
    }

    public function test_assigned_non_member_moderator_move_failure_is_a_422_with_context_preserved(): void
    {
        $seed = $this->seedPrivateThread();
        $mod = $this->makeUser(['username' => 'assignedmod2']);
        (new BoardModeratorRepository($this->db))->assign((int) $seed['board']['id'], (int) $mod['id']);
        // A second moderated board so the move form (which carries the 422
        // error) has a destination to offer.
        $other = $this->makeBoard($this->makeCategory('Annex'));
        (new BoardModeratorRepository($this->db))->assign((int) $other['id'], (int) $mod['id']);

        $this->actingAs($mod);
        $res = $this->post('/mod/t/' . $seed['thread_id'] . '/move', ['board_id' => '0']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Choose a destination board.');
        $this->assertSeeText($res, self::TITLE);
    }

    // ---- authority after read ----------------------------------------------

    public function test_readable_non_moderator_move_is_403_after_the_read_gate(): void
    {
        $this->makeAdmin();
        $board = $this->makeBoard($this->makeCategory('Open'));
        $author = $this->makeUser();
        $made = $this->makeThread($board, $author, 'Public topic', 'Public body.');

        $this->actingAs($this->makeUser(['username' => 'plainreader']));
        $res = $this->post('/mod/t/' . (int) $made['thread_id'] . '/move', ['board_id' => '0']);

        $this->assertStatus(403, $res);
    }

    // ---- merge-target resolution (no oracle, source-scoped authority) ------

    public function test_merge_target_missing_and_unreadable_produce_the_same_422(): void
    {
        $this->makeAdmin();
        $srcBoard = $this->makeBoard($this->makeCategory('Src'));
        $author = $this->makeUser();
        $source = $this->makeThread($srcBoard, $author, 'Merge source topic', 'Source body.');
        $mod = $this->makeUser(['username' => 'srcmod']);
        (new BoardModeratorRepository($this->db))->assign((int) $srcBoard['id'], (int) $mod['id']);

        $privateSeed = $this->seedPrivateThread(); // a target the mod cannot read

        $this->actingAs($mod);
        $missing = $this->post('/mod/t/' . (int) $source['thread_id'] . '/merge', ['target_thread_id' => '999999']);
        $unreadable = $this->post('/mod/t/' . (int) $source['thread_id'] . '/merge', ['target_thread_id' => (string) $privateSeed['thread_id']]);

        $this->assertStatus(422, $missing);
        $this->assertStatus(422, $unreadable);
        $this->assertSeeText($missing, 'Choose a valid target thread.');
        $this->assertSeeText($unreadable, 'Choose a valid target thread.');
        $this->assertDontSeeText($unreadable, self::TITLE);
    }

    public function test_merge_into_a_readable_unmoderated_board_is_403(): void
    {
        $this->makeAdmin();
        $srcBoard = $this->makeBoard($this->makeCategory('SrcB'));
        $otherBoard = $this->makeBoard($this->makeCategory('OtherB'));
        $author = $this->makeUser();
        $source = $this->makeThread($srcBoard, $author, 'Merge source B', 'Source body B.');
        $target = $this->makeThread($otherBoard, $author, 'Merge target B', 'Target body B.');
        $mod = $this->makeUser(['username' => 'srconlymod']);
        (new BoardModeratorRepository($this->db))->assign((int) $srcBoard['id'], (int) $mod['id']);

        $this->actingAs($mod);
        $res = $this->post('/mod/t/' . (int) $source['thread_id'] . '/merge', ['target_thread_id' => (string) (int) $target['thread_id']]);

        $this->assertStatus(403, $res);
    }

    // ---- reply failure re-render -------------------------------------------

    public function test_pending_thread_reply_failure_is_404_for_a_stranger(): void
    {
        $seed = $this->seedPendingThread();
        $this->actingAs($this->makeUser(['username' => 'replystranger']));

        $res = $this->post('/t/' . $seed['thread_id'] . '/reply', ['body' => '']);

        $this->assertStatus(404, $res);
        $this->assertDontSeeText($res, self::TITLE);
    }

    public function test_pending_thread_reply_failure_still_422_with_draft_for_the_author(): void
    {
        $seed = $this->seedPendingThread();
        $this->actingAs($seed['author']);

        $res = $this->post('/t/' . $seed['thread_id'] . '/reply', ['body' => '']);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, self::TITLE);
    }
}
