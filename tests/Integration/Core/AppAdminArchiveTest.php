<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use Tests\Support\TestCase;

final class AppAdminArchiveTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->categoryId = $this->makeCategory('General');
    }

    public function test_member_cannot_create_thread_or_reply_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcboard', 'name' => 'ArcBoard']);
        $thread = $this->makeThread($board, $member, 'Pre-archive topic'); // seeded BEFORE archive
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $create = $this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'New topic after archive',
            'body' => 'Should be rejected.',
        ]);
        $this->assertStatus(403, $create);

        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Should be rejected too.']);
        $this->assertStatus(403, $reply);
    }

    public function test_owner_cannot_edit_or_delete_a_post_in_archived_board(): void
    {
        $member = $this->makeUser(['username' => 'arcowner']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcedit', 'name' => 'ArcEdit']);
        $thread = $this->makeThread($board, $member, 'Editable topic', 'Original body here.');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/edit', ['body' => 'Trying to edit after archive.']));
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/delete'));
    }

    public function test_admin_and_board_moderator_are_also_blocked_from_writing(): void
    {
        $author = $this->makeUser(['username' => 'arcauth']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcmod', 'name' => 'ArcMod']);
        $thread = $this->makeThread($board, $author, 'Topic in a soon-archived board');
        $mod = $this->makeUser(['username' => 'arcmoduser']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'admin reply blocked']));

        $this->logoutClient();
        $this->actingAs($mod);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'mod reply blocked']));
    }

    public function test_unarchive_restores_writability(): void
    {
        $member = $this->makeUser(['username' => 'rearcmember']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'rearc', 'name' => 'ReArc']);
        $thread = $this->makeThread($board, $member, 'Reopenable topic');

        // Archive then unarchive through the admin service path (not raw SQL).
        $this->actingAs($this->admin);
        $this->get('/admin/structure');
        $this->post('/admin/boards/' . $board['id'] . '/archive');
        $this->post('/admin/boards/' . $board['id'] . '/unarchive');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unarchive_board'"));

        $this->logoutClient();
        $this->actingAs($member);
        $reply = $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Posting works again.']);
        $this->assertRedirectContains($reply, '/t/' . $thread['thread_id']);
    }

    public function test_thread_status_change_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wfauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wfboard', 'name' => 'WFBoard']);
        $thread = $this->makeThread($board, $author, 'Workflow topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $svc = new \App\Service\ThreadWorkflowService(
            $this->db,
            new \App\Repository\ThreadRepository($this->db),
            new \App\Repository\ThreadAssignmentRepository($this->db),
            new \App\Repository\UserRepository($this->db),
            new \App\Repository\BoardModeratorRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
            new \App\Security\WriteGate(),
            new \App\Core\FeatureFlags(new \App\Repository\SettingRepository($this->db)),
        );

        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->setStatus($this->userEntity($author), $thread['thread_id'], 'solved');
    }

    public function test_wiki_make_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wikiauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wikiboard', 'name' => 'WikiBoard']);
        $thread = $this->makeThread($board, $author, 'Wiki candidate');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        // Enable wiki on the board so the archive guard — not the wiki-disabled
        // check — is the sole reason makeWiki is rejected (genuine RED->GREEN).
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $svc = new \App\Service\CommunityMemoryService(
            $this->db,
            new \App\Repository\ThreadRepository($this->db),
            new \App\Repository\PostRepository($this->db),
            new \App\Repository\BoardModeratorRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardPolicy(),
            new \App\Security\WriteGate(),
            new \App\Support\Markdown(new \App\Support\HtmlSanitizer()),
        );

        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->makeWiki($this->userEntity($this->admin), $opId);
    }

    public function test_archived_board_page_is_readable_with_banner_and_no_new_topic(): void
    {
        $author = $this->makeUser(['username' => 'arcreader']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'arcread', 'name' => 'ArcRead']);
        $this->makeThread($board, $author, 'Still readable topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($author);
        $res = $this->get('/c/arcread');

        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Still readable topic');   // content preserved + readable
        $this->assertSeeText($res, 'retired and read-only');  // banner copy
        $this->assertDontSeeText($res, 'New Topic');          // affordance suppressed
    }

    // ---- Group B: every remaining content-mutation path is frozen ---------

    public function test_reaction_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'reactauthor']);
        $reactor = $this->makeUser(['username' => 'reactor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'reactarc', 'name' => 'ReactArc']);
        $thread = $this->makeThread($board, $author, 'Reactable topic');
        $opId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($reactor);
        $this->assertStatus(403, $this->post('/posts/' . $opId . '/react', ['emoji' => '👍']));
        // The mutation (reaction row + author reputation) never happened.
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reactions WHERE post_id = ?', [$opId]));
    }

    public function test_tagging_is_blocked_on_archived_board_with_no_carveout(): void
    {
        (new SettingRepository($this->db))->set('features', ['tags' => true]);
        $author = $this->makeUser(['username' => 'tagauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'tagarc', 'name' => 'TagArc']);
        $thread = $this->makeThread($board, $author, 'Taggable topic');
        $tagId = (new TagRepository($this->db))->create('howto', 'How-to', null, (int) $this->admin['id']);

        // Happy path: a privileged actor (admin) CAN tag while the board is open.
        $this->actingAs($this->admin);
        $ok = $this->post('/t/' . $thread['thread_id'] . '/tags', ['tag_ids' => [$tagId]]);
        $this->assertRedirectContains($ok, '/t/' . $thread['thread_id']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE thread_id = ?', [$thread['thread_id']]));

        // Archive: even the admin (no role carve-out) is now frozen out.
        $this->boards()->setArchived((int) $board['id'], true);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/tags', ['tag_ids' => []]));
        // The attempted clear was rejected — the tag set is unchanged.
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE thread_id = ?', [$thread['thread_id']]));
    }

    public function test_admin_pin_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'pinauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'pinarc', 'name' => 'PinArc']);
        $thread = $this->makeThread($board, $author, 'Pinnable topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/mod/t/' . $thread['thread_id'] . '/pin'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_pinned FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_admin_lock_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'lockauthor']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'lockarc', 'name' => 'LockArc']);
        $thread = $this->makeThread($board, $author, 'Lockable topic');
        $this->boards()->setArchived((int) $board['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/mod/t/' . $thread['thread_id'] . '/lock'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_locked FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_admin_delete_post_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'delauthor']);
        $replier = $this->makeUser(['username' => 'delreplier']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'delarc', 'name' => 'DelArc']);
        $thread = $this->makeThread($board, $author, 'Deletable topic');
        $this->actingAs($replier);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'A reply to moderate.']);
        $replyId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$thread['thread_id']]);
        $this->boards()->setArchived((int) $board['id'], true);

        // Admin is not the author → the mod-delete path (ModerationService::deletePost).
        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/posts/' . $replyId . '/delete', ['reason' => 'cleanup']));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_deleted FROM posts WHERE id = ?', [$replyId]));
    }

    public function test_admin_restore_post_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'resauthor']);
        $replier = $this->makeUser(['username' => 'resreplier']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'resarc', 'name' => 'ResArc']);
        $thread = $this->makeThread($board, $author, 'Restorable topic');
        $this->actingAs($replier);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'A reply to delete then restore.']);
        $replyId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$thread['thread_id']]);

        // Delete it while the board is open (mod path), then archive.
        $this->actingAs($this->admin);
        $this->post('/posts/' . $replyId . '/delete', ['reason' => 'temp']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_deleted FROM posts WHERE id = ?', [$replyId]));
        $this->boards()->setArchived((int) $board['id'], true);

        $this->assertStatus(403, $this->post('/mod/p/' . $replyId . '/restore', ['reason' => 'oops']));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_deleted FROM posts WHERE id = ?', [$replyId]));
    }

    public function test_move_thread_is_blocked_when_source_board_is_archived(): void
    {
        $author = $this->makeUser(['username' => 'mvsrcauthor']);
        $src = $this->makeBoard($this->categoryId, ['slug' => 'mvsrc', 'name' => 'MvSrc']);
        $dst = $this->makeBoard($this->categoryId, ['slug' => 'mvdst', 'name' => 'MvDst']);
        $thread = $this->makeThread($src, $author, 'Movable topic');
        $this->boards()->setArchived((int) $src['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/mod/t/' . $thread['thread_id'] . '/move', ['board_id' => (int) $dst['id']]));
        self::assertSame((int) $src['id'], (int) $this->db->fetchValue('SELECT board_id FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_move_thread_is_blocked_when_destination_board_is_archived(): void
    {
        $author = $this->makeUser(['username' => 'mvdstauthor']);
        $src = $this->makeBoard($this->categoryId, ['slug' => 'mvsrc2', 'name' => 'MvSrc2']);
        $dst = $this->makeBoard($this->categoryId, ['slug' => 'mvdst2', 'name' => 'MvDst2']);
        $thread = $this->makeThread($src, $author, 'Movable topic two');
        $this->boards()->setArchived((int) $dst['id'], true);

        $this->actingAs($this->admin);
        $this->assertStatus(403, $this->post('/mod/t/' . $thread['thread_id'] . '/move', ['board_id' => (int) $dst['id']]));
        self::assertSame((int) $src['id'], (int) $this->db->fetchValue('SELECT board_id FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_accept_answer_remark_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'solveop']);
        $answererA = $this->makeUser(['username' => 'ansA']);
        $answererB = $this->makeUser(['username' => 'ansB']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'solvarc', 'name' => 'SolvArc']);
        $thread = $this->makeThread($board, $author, 'Question topic');

        $this->actingAs($answererA);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Answer A.']);
        $this->actingAs($answererB);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'Answer B.']);
        $postA = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND user_id = ?', [$thread['thread_id'], (int) $answererA['id']]);
        $postB = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND user_id = ?', [$thread['thread_id'], (int) $answererB['id']]);

        // Happy path: the OP accepts answer A while the board is open.
        $this->actingAs($author);
        $accept = $this->post('/posts/' . $postA . '/accept');
        $this->assertRedirectContains($accept, '/t/' . $thread['thread_id']);
        self::assertSame($postA, (int) $this->db->fetchValue('SELECT accepted_answer_post_id FROM threads WHERE id = ?', [$thread['thread_id']]));

        // Archive, then re-mark B: this once slipped through and moved reputation.
        $this->boards()->setArchived((int) $board['id'], true);
        $this->assertStatus(403, $this->post('/posts/' . $postB . '/accept'));
        self::assertSame($postA, (int) $this->db->fetchValue('SELECT accepted_answer_post_id FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function test_thread_assign_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wfassignop']);
        $assignee1 = $this->makeUser(['username' => 'wfassignee1']);
        $assignee2 = $this->makeUser(['username' => 'wfassignee2']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wfassignb', 'name' => 'WFAssign']);
        $this->db->run("UPDATE boards SET assignment_mode = 'staff' WHERE id = ?", [(int) $board['id']]);
        $thread = $this->makeThread($board, $author, 'Assignable topic');

        $svc = $this->workflowService();

        // Happy path (open board): staff (admin) can assign — guard must not break this.
        $svc->assign($this->userEntity($this->admin), $thread['thread_id'], (int) $assignee1['id']);
        self::assertSame((int) $assignee1['id'], (int) $this->db->fetchValue(
            'SELECT assigned_user_id FROM thread_assignments WHERE thread_id = ?',
            [$thread['thread_id']],
        ));

        // Archive → re-assignment is rejected for everyone, including admin.
        $this->boards()->setArchived((int) $board['id'], true);
        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->assign($this->userEntity($this->admin), $thread['thread_id'], (int) $assignee2['id']);
    }

    public function test_thread_unassign_is_blocked_on_archived_board(): void
    {
        $author = $this->makeUser(['username' => 'wfunassignop']);
        $assignee = $this->makeUser(['username' => 'wfunassignee']);
        $board = $this->makeBoard($this->categoryId, ['slug' => 'wfunassignb', 'name' => 'WFUnassign']);
        $this->db->run("UPDATE boards SET assignment_mode = 'staff' WHERE id = ?", [(int) $board['id']]);
        $thread = $this->makeThread($board, $author, 'Unassignable topic');

        $svc = $this->workflowService();
        $svc->assign($this->userEntity($this->admin), $thread['thread_id'], (int) $assignee['id']);
        $this->boards()->setArchived((int) $board['id'], true);

        $this->expectException(\App\Core\ForbiddenException::class);
        $svc->unassign($this->userEntity($this->admin), $thread['thread_id']);
    }

    private function workflowService(): \App\Service\ThreadWorkflowService
    {
        return new \App\Service\ThreadWorkflowService(
            $this->db,
            new \App\Repository\ThreadRepository($this->db),
            new \App\Repository\ThreadAssignmentRepository($this->db),
            new \App\Repository\UserRepository($this->db),
            new \App\Repository\BoardModeratorRepository($this->db),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Repository\ModerationLogRepository($this->db),
            new \App\Security\WriteGate(),
            new \App\Core\FeatureFlags(new \App\Repository\SettingRepository($this->db)),
        );
    }
}
