<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Mail\ArrayMailer;
use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use App\Repository\ReportRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\TagRepository;
use App\Repository\UserPreferenceRepository;
use App\Security\WriteGate;
use App\Service\DirectMessageService;
use App\Service\NotificationService;
use App\Service\RepairService;
use App\Service\ReputationLedgerService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use Tests\Support\TestCase;

final class AppPhase4GateATest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', [
            'topic_workflow' => true,
            'group_dms' => true,
            'tags' => true,
            'expanded_feeds' => true,
            'reputation_ledger' => true,
            'badge_rules' => true,
            'community_memory' => true,
        ]);
        $this->makeAdmin();
    }

    private function established(array $attrs = []): array
    {
        $u = $this->makeUser($attrs);
        $board = $this->makeBoard($this->makeCategory());
        $this->makeThread($board, $u, 'Established', 'first post');
        return $this->users()->find((int) $u['id']);
    }

    private function dm(): DirectMessageService
    {
        $notifs = new NotificationService(
            $this->db,
            new NotificationRepository($this->db),
            new SubscriptionRepository($this->db),
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new BlockRepository($this->db),
            $this->users(),
            new FeatureFlags(new SettingRepository($this->db)),
            new ArrayMailer(),
        );
        return new DirectMessageService(
            $this->db,
            new ConversationRepository($this->db),
            new DmMessageRepository($this->db),
            $this->users(),
            new BlockRepository($this->db),
            new WriteGate(),
            new Markdown(new HtmlSanitizer()),
            $notifs,
            $this->config,
        );
    }

    public function testTopicWorkflowStatusSnoozeAssignmentAndInboxFilters(): void
    {
        $author = $this->makeUser(['username' => 'workflowauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Workflow']);
        $this->db->run("UPDATE boards SET assignment_mode = 'self' WHERE id = ?", [(int) $board['id']]);
        $thread = $this->makeThread($board, $author, 'Needs triage', 'Opening body');
        $threadId = $thread['thread_id'];

        $this->actingAs($author);
        $this->get('/t/' . $threadId . '-' . $thread['slug']);
        $this->assertRedirect($this->post('/t/' . $threadId . '/status', ['status' => 'needs_answer', 'reason' => 'needs help']));
        self::assertSame('needs_answer', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$threadId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM thread_status_history WHERE thread_id = ? AND new_status = 'needs_answer'", [$threadId]));

        $this->post('/t/' . $threadId . '/assign', ['self' => '1']);
        self::assertSame((int) $author['id'], (int) $this->db->fetchValue('SELECT assigned_user_id FROM thread_assignments WHERE thread_id = ?', [$threadId]));

        $assigned = $this->get('/inbox', ['filter' => 'assigned']);
        $this->assertStatus(200, $assigned);
        $this->assertSeeText($assigned, 'Needs triage');

        $this->post('/t/' . $threadId . '/snooze', ['until' => 'tomorrow']);
        $assignedAfterSnooze = $this->get('/inbox', ['filter' => 'assigned']);
        $this->assertDontSeeText($assignedAfterSnooze, 'Needs triage');
        $snoozed = $this->get('/inbox', ['filter' => 'snoozed']);
        $this->assertSeeText($snoozed, 'Needs triage');
    }

    public function testTopicAuthorCannotReopenStaffSetStatus(): void
    {
        $admin = $this->makeAdmin(['username' => 'staffstatusadmin']);
        $author = $this->makeUser(['username' => 'staffstatusauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Staff Status']);
        $thread = $this->makeThread($board, $author, 'Staff decision', 'Opening body');

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/status', ['status' => 'decision_made', 'reason' => 'moderated']));
        self::assertSame('decision_made', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$thread['thread_id']]));

        $this->actingAs($author);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/status', ['status' => 'open', 'reason' => 'undo']));
        self::assertSame('decision_made', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function testSuspendedMemberCannotSnooze(): void
    {
        $author = $this->makeUser(['username' => 'snoozeauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Snooze Gate']);
        $thread = $this->makeThread($board, $author, 'Snooze gate', 'Opening body');

        // An active member may snooze (redirect, not 403).
        $active = $this->makeUser(['username' => 'snoozeactive']);
        $this->actingAs($active);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/snooze', ['until' => 'tomorrow']));

        // State beats role: a suspended account cannot write — including a personal
        // snooze. Status/assign gate this in ThreadWorkflowService; snooze writes
        // the per-user row directly, so the controller must gate it too.
        $suspended = $this->makeUser(['username' => 'snoozesuspended', 'status' => 'suspended']);
        $this->actingAs($suspended);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/snooze', ['until' => 'tomorrow']));
    }

    public function testStatusHistoryRendersOnThreadPage(): void
    {
        $author = $this->makeUser(['username' => 'historyauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'History']);
        $thread = $this->makeThread($board, $author, 'History thread', 'Opening body');
        $slug = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        $this->actingAs($author);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/status', ['status' => 'needs_answer', 'reason' => 'please advise']));

        // The status change is now surfaced on the thread page (audit trail), not
        // just recorded — the transition, reason, and actor render.
        $page = $this->get($slug);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Status history');
        $this->assertSeeText($page, 'please advise');
    }

    public function testAcceptingAnswerSkipsStatusWhenWorkflowDarkAndRepairReconciles(): void
    {
        // Fix 3 (Option A): the community accept-answer path syncs threads.status
        // only when topic_workflow is on. Disabling the flag freezes the status
        // projection; `repair` reconciles it from the accepted-answer marker.
        (new SettingRepository($this->db))->set('features', ['topic_workflow' => false]);

        $op = $this->makeUser(['username' => 'darkacceptop']);
        $answerer = $this->makeUser(['username' => 'darkacceptanswerer']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Dark Accept']);
        $thread = $this->makeThread($board, $op, 'Need an answer', 'Opening body');
        $threadId = $thread['thread_id'];
        $replyId = $this->posting()->reply($this->userEntity($answerer), $threadId, ['body' => 'Do it like this.']);

        $this->actingAs($op);
        $this->assertRedirectContains($this->post('/posts/' . $replyId . '/accept'), '/t/' . $threadId);

        // Accepted-answer marker is set (community), but status is NOT synced while dark.
        self::assertSame($replyId, (int) $this->db->fetchValue('SELECT accepted_answer_post_id FROM threads WHERE id = ?', [$threadId]));
        self::assertSame('open', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$threadId]));

        // repair reconciles the projection from the accepted answer.
        self::assertSame(1, (new RepairService($this->db))->repairThreadStatuses());
        self::assertSame('solved', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$threadId]));

        // Unaccepting while dark clears the marker but leaves status frozen…
        $this->assertRedirectContains($this->post('/t/' . $threadId . '/unaccept'), '/t/' . $threadId);
        self::assertNull($this->db->fetchValue('SELECT accepted_answer_post_id FROM threads WHERE id = ?', [$threadId]));
        self::assertSame('solved', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$threadId]));

        // …until repair reconciles it back to open.
        self::assertSame(1, (new RepairService($this->db))->repairThreadStatuses());
        self::assertSame('open', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$threadId]));
    }

    public function testRepairThreadStatusesPreservesStaffSetStatus(): void
    {
        // The reconcile maps only open/needs_answer ⇄ solved; a staff-set
        // decision_made/archived is never clobbered even with an accepted answer.
        $op = $this->makeUser(['username' => 'preserveop']);
        $answerer = $this->makeUser(['username' => 'preserveanswerer']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Staff Preserve']);
        $thread = $this->makeThread($board, $op, 'Decided', 'Opening body');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $thread['thread_id'], ['body' => 'answer']);

        $this->db->run(
            'UPDATE threads SET accepted_answer_post_id = ?, status = ? WHERE id = ?',
            [$replyId, 'decision_made', $thread['thread_id']],
        );

        self::assertSame(0, (new RepairService($this->db))->repairThreadStatuses());
        self::assertSame('decision_made', (string) $this->db->fetchValue('SELECT status FROM threads WHERE id = ?', [$thread['thread_id']]));
    }

    public function testGroupDmMembershipBoundaries(): void
    {
        $owner = $this->established(['username' => 'groupowner']);
        $bob = $this->makeUser(['username' => 'groupbob']);
        $carol = $this->makeUser(['username' => 'groupcarol']);
        $dave = $this->makeUser(['username' => 'groupdave']);

        $created = $this->dm()->startGroup($this->userEntity($owner), [(int) $bob['id'], (int) $carol['id']], 'Project room', 'Message before Dave');
        $convId = $created['conversation_id'];
        $this->dm()->addParticipant($this->userEntity($owner), $convId, (int) $dave['id']);
        $this->dm()->reply($this->userEntity($owner), $convId, 'Message after Dave');

        $this->actingAs($dave);
        $res = $this->get('/messages/' . $convId);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'Message after Dave');
        $this->assertDontSeeText($res, 'Message before Dave');
    }

    public function testGroupDmReportQueueIncludesMessageContextAndNotifiesAdmins(): void
    {
        $admin = $this->makeAdmin(['username' => 'dmreportadmin']);
        $owner = $this->established(['username' => 'dmreportowner']);
        $bob = $this->makeUser(['username' => 'dmreportbob']);
        $carol = $this->makeUser(['username' => 'dmreportcarol']);

        $dm = $this->dm();
        $created = $dm->startGroup($this->userEntity($owner), [(int) $bob['id'], (int) $carol['id']], 'Report room', 'Opening note');
        $messageId = $dm->reply($this->userEntity($bob), (int) $created['conversation_id'], 'reported-dm-body-marker');
        $dm->reportMessage($this->userEntity($owner), $messageId, 'harassment', 'Please review this.');

        $queue = (new ReportRepository($this->db))->queue(true, [], 20);
        $reported = array_values(array_filter($queue, static fn (array $row): bool => (int) ($row['dm_message_id'] ?? 0) === $messageId));
        self::assertCount(1, $reported);
        self::assertSame('reported-dm-body-marker', (string) $reported[0]['dm_body']);
        self::assertSame('dmreportbob', (string) $reported[0]['dm_sender_username']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'mod' AND conversation_id = ?",
            [(int) $admin['id'], (int) $created['conversation_id']],
        ));

        $this->actingAs($admin);
        $page = $this->get('/mod/reports');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'reported-dm-body-marker');
        $this->assertSeeText($page, 'Report room');
    }

    public function testDmReportEndpointIsRateLimited(): void
    {
        $owner = $this->established(['username' => 'dmratelimitowner']);
        $bob = $this->established(['username' => 'dmratelimitbob']);
        $created = $this->dm()->start($this->userEntity($owner), (int) $bob['id'], 'Opening note');
        $messageIds = [];
        for ($i = 0; $i < 11; $i++) {
            $messageIds[] = $this->dm()->reply($this->userEntity($bob), (int) $created['conversation_id'], 'Reportable note ' . $i);
        }

        $this->actingAs($owner);
        for ($i = 0; $i < 10; $i++) {
            $this->assertRedirect($this->post('/dm/' . $messageIds[$i] . '/report', ['reason_code' => 'harassment', 'reason' => 'Review']));
        }
        $this->assertStatus(429, $this->post('/dm/' . $messageIds[10] . '/report', ['reason_code' => 'harassment', 'reason' => 'Review']));
    }

    public function testGroupDmAddParticipantRejectsInactiveAccounts(): void
    {
        $owner = $this->established(['username' => 'inactiveowner']);
        $bob = $this->makeUser(['username' => 'inactivebob']);
        $banned = $this->makeUser(['username' => 'inactivebanned', 'status' => 'banned']);

        $created = $this->dm()->startGroup($this->userEntity($owner), [(int) $bob['id']], 'Account state room', 'Opening note');

        $this->expectException(ForbiddenException::class);
        try {
            $this->dm()->addParticipant($this->userEntity($owner), (int) $created['conversation_id'], (int) $banned['id']);
        } finally {
            self::assertSame(0, (int) $this->db->fetchValue(
                'SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND left_at IS NULL',
                [(int) $created['conversation_id'], (int) $banned['id']],
            ));
        }
    }

    public function testAdvancedMarkdownTablesAndTaskListsAreSanitized(): void
    {
        $html = (new Markdown(new HtmlSanitizer()))->render("- [x] done\n\n| A | B |\n| - | - |\n| 1 | 2 |\n\n---");
        self::assertStringContainsString('<table>', $html);
        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('<hr', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testBoardAndTagFollowsFeedWithoutSubscriptions(): void
    {
        $reader = $this->makeUser(['username' => 'feedreader']);
        $author = $this->makeUser(['username' => 'feedauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Feed Board']);
        $thread = $this->makeThread($board, $author, 'Feed target', 'Visible through follows');

        $this->actingAs($reader);
        $this->get('/c/' . $board['slug']);
        $this->post('/b/' . (int) $board['id'] . '/follow');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM subscriptions WHERE user_id = ?', [(int) $reader['id']]));

        $feed = $this->get('/feed');
        $this->assertStatus(200, $feed);
        $this->assertSeeText($feed, 'Feed target');

        $tagId = (new TagRepository($this->db))->create('release-notes', 'Release Notes', null, (int) $reader['id']);
        (new TagRepository($this->db))->setForThread($thread['thread_id'], [$tagId], (int) $reader['id']);
        (new FollowRepository($this->db))->unfollowTarget((int) $reader['id'], 'board', (int) $board['id']);
        $this->post('/tags/release-notes/follow');
        $tagFeed = $this->get('/feed');
        $this->assertSeeText($tagFeed, 'Feed target');
    }

    public function testMembersCanTagThreadsButBoardToggleIsEnforced(): void
    {
        $member = $this->makeUser(['username' => 'tagmember']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Tag Toggle']);
        $thread = $this->makeThread($board, $member, 'Taggable topic', 'Tag me');
        $tagId = (new TagRepository($this->db))->create('member-tag', 'Member Tag', null, (int) $member['id']);

        $this->actingAs($member);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/tags', ['tag_ids' => [$tagId]]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_tags WHERE thread_id = ? AND tag_id = ?',
            [$thread['thread_id'], $tagId],
        ));

        $this->db->run('UPDATE boards SET tags_enabled = 0 WHERE id = ?', [(int) $board['id']]);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/tags', ['tag_ids' => []]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM thread_tags WHERE thread_id = ? AND tag_id = ?',
            [$thread['thread_id'], $tagId],
        ));
    }

    public function testAdminCanHideDisableAndMergeTags(): void
    {
        $admin = $this->makeAdmin(['username' => 'tagadmin']);
        $author = $this->makeUser(['username' => 'tagmergeauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Tag Lifecycle']);
        $thread = $this->makeThread($board, $author, 'Tagged merge target', 'Body');
        $repo = new TagRepository($this->db);
        $oldId = $repo->create('old-tag', 'Old Tag', null, (int) $admin['id']);
        $newId = $repo->create('new-tag', 'New Tag', null, (int) $admin['id']);
        $repo->setForThread($thread['thread_id'], [$oldId], (int) $admin['id']);
        (new FollowRepository($this->db))->followTarget((int) $author['id'], 'tag', $oldId);

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/admin/tags/' . $oldId . '/merge', ['target_id' => $newId]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_enabled FROM tags WHERE id = ?', [$oldId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE thread_id = ? AND tag_id = ?', [$thread['thread_id'], $newId]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'tag' AND target_id = ?", [(int) $author['id'], $newId]));
        self::assertSame($newId, (int) $this->db->fetchValue('SELECT tag_id FROM tag_aliases WHERE alias_slug = ?', ['old-tag']));

        $this->assertRedirect($this->post('/admin/tags/' . $newId, [
            'name' => 'New Tag',
            'slug' => 'new-tag',
            'description' => '',
            'visibility' => 'hidden',
            'enabled' => '1',
        ]));
        $index = $this->get('/tags');
        $this->assertDontSeeText($index, 'New Tag');
        $this->assertStatus(404, $this->get('/tags/new-tag'));

        $this->actingAs($author);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/tags', ['tag_ids' => [$newId]]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE thread_id = ?', [$thread['thread_id']]));
    }

    public function testWikiBoardToggleIsEnforced(): void
    {
        $admin = $this->makeAdmin(['username' => 'wikiadmin']);
        $author = $this->makeUser(['username' => 'wikiauthor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Wiki Toggle']);
        $thread = $this->makeThread($board, $author, 'Wiki topic', 'Original body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($admin);
        $this->assertStatus(403, $this->post('/posts/' . $postId . '/wiki'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_wiki FROM posts WHERE id = ?', [$postId]));

        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $this->assertRedirect($this->post('/posts/' . $postId . '/wiki'));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_wiki FROM posts WHERE id = ?', [$postId]));
    }

    public function testReputationLedgerTracksReactionApplyAndReverse(): void
    {
        $author = $this->makeUser(['username' => 'repauthor']);
        $reactor = $this->makeUser(['username' => 'reactor']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Reputation target', 'React here');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($reactor);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->post('/posts/' . $postId . '/react', ['emoji' => '👍']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reputation_events WHERE user_id = ? AND reversed_at IS NULL', [(int) $author['id']]));

        $this->post('/posts/' . $postId . '/react', ['emoji' => '👍']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM reputation_events WHERE user_id = ? AND reversed_at IS NOT NULL', [(int) $author['id']]));
    }

    public function testWindowedLeaderboardUsesLedgerAndHonorsOptOut(): void
    {
        $author = $this->makeUser(['username' => 'windowauthor', 'display_name' => 'Window Author']);
        $reactor = $this->makeUser(['username' => 'windowreactor']);
        $board = $this->makeBoard($this->makeCategory(), ['name' => 'Leaderboard Board']);
        $thread = $this->makeThread($board, $author, 'Windowed reputation', 'React here');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($reactor);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->post('/posts/' . $postId . '/react', ['emoji' => '👍']);

        $week = $this->get('/leaderboard', ['window' => 'week', 'board_id' => (int) $board['id']]);
        $this->assertStatus(200, $week);
        $this->assertSeeText($week, 'Window Author');

        (new UserPreferenceRepository($this->db))->merge((int) $author['id'], ['hide_from_leaderboard' => true]);
        $hidden = $this->get('/leaderboard', ['window' => 'week', 'board_id' => (int) $board['id']]);
        $this->assertStatus(200, $hidden);
        $this->assertDontSeeText($hidden, 'Window Author');
    }

    public function testBoardScopedLeaderboardDoesNotLeakPrivateBoardContributorsToOutsiders(): void
    {
        // reputation_ledger is ON (setUp). The board-scoped leaderboard filter
        // (?board_id=) must be read-gated: a private board's contributors must
        // never be enumerable by someone who cannot read the board, even though
        // /leaderboard is a public route.
        $admin = $this->makeAdmin(['username' => 'privlbadmin']);
        $author = $this->makeUser(['username' => 'privlbauthor', 'display_name' => 'Private Contributor']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'secret-lb', 'visibility' => 'private']);
        (new ReputationLedgerService($this->db, $this->users()))->apply(
            (int) $author['id'],
            (int) $board['id'],
            'reaction',
            1,
            'reaction:privlb:' . (int) $author['id'],
            5,
        );

        // Outsider (guest): scoping to the private board must 404, not leak.
        $leak = $this->get('/leaderboard', ['window' => 'week', 'board_id' => (int) $board['id']]);
        $this->assertStatus(404, $leak);
        $this->assertDontSeeText($leak, 'Private Contributor');
    }

    public function testBoardScopedLeaderboardStillServesAuthorizedViewersOfThePrivateBoard(): void
    {
        // The read-gate must not over-block: an admin (or member) who can read
        // the private board still gets the board-scoped windowed leaderboard.
        $admin = $this->makeAdmin(['username' => 'privlbadmin2']);
        $author = $this->makeUser(['username' => 'privlbauthor2', 'display_name' => 'Trusted Contributor']);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'secret-lb-2', 'visibility' => 'private']);
        (new ReputationLedgerService($this->db, $this->users()))->apply(
            (int) $author['id'],
            (int) $board['id'],
            'reaction',
            1,
            'reaction:privlb2:' . (int) $author['id'],
            5,
        );

        $this->actingAs($admin);
        $ok = $this->get('/leaderboard', ['window' => 'week', 'board_id' => (int) $board['id']]);
        $this->assertStatus(200, $ok);
        $this->assertSeeText($ok, 'Trusted Contributor');
    }

    public function testDeleteRestoreAndRebuildKeepReputationLedgerCanonical(): void
    {
        $admin = $this->makeAdmin(['username' => 'repadmin']);
        $op = $this->makeUser(['username' => 'repop']);
        $author = $this->makeUser(['username' => 'restoreauthor']);
        $reactor = $this->makeUser(['username' => 'restorereactor']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $op, 'Restore reputation', 'Question');
        $replyId = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Answer']);

        $this->actingAs($reactor);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->post('/posts/' . $replyId . '/react', ['emoji' => '👍']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));

        $this->actingAs($author);
        $this->post('/posts/' . $replyId . '/delete');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reputation_events WHERE source_id = ? AND source_type = \'reaction\' AND reversed_at IS NOT NULL',
            [$replyId],
        ));

        $this->actingAs($admin);
        $this->post('/mod/p/' . $replyId . '/restore');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reputation_events WHERE source_id = ? AND source_type = \'reaction\' AND reversed_at IS NULL',
            [$replyId],
        ));

        $manualAuthor = $this->makeUser(['username' => 'manualauthor']);
        $manualReactor = $this->makeUser(['username' => 'manualreactor']);
        $manualThread = $this->makeThread($board, $manualAuthor, 'Manual backfill', 'Manual body');
        $manualPostId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$manualThread['thread_id']]);
        $this->db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$manualPostId, (int) $manualReactor['id'], '👍']);
        $this->db->run('UPDATE users SET reputation = 999 WHERE id = ?', [(int) $manualAuthor['id']]);

        (new ReputationLedgerService($this->db, $this->users()))->rebuildFromCanonical(5);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $manualAuthor['id']]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reputation_events WHERE user_id = ? AND source_id = ? AND reversed_at IS NULL',
            [(int) $manualAuthor['id'], $manualPostId],
        ));
    }

    public function testLegacyReputationRepairRebuildsLedger(): void
    {
        $author = $this->makeUser(['username' => 'legacyrepairauthor']);
        $reactor = $this->makeUser(['username' => 'legacyrepairreactor']);
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Legacy repair target', 'React here');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $this->db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())', [$postId, (int) $reactor['id'], '👍']);
        $this->db->run('UPDATE users SET reputation = 999 WHERE id = ?', [(int) $author['id']]);

        (new RepairService($this->db, 5))->repairReputation();

        self::assertSame(1, (int) $this->db->fetchValue('SELECT reputation FROM users WHERE id = ?', [(int) $author['id']]));
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM reputation_events WHERE user_id = ? AND source_type = \'reaction\' AND source_id = ? AND reversed_at IS NULL',
            [(int) $author['id'], $postId],
        ));
    }

    public function testProfileOwnerCanRemoveFollower(): void
    {
        $owner = $this->makeUser(['username' => 'followowner']);
        $follower = $this->makeUser(['username' => 'followremove']);

        $this->actingAs($follower);
        $this->assertRedirect($this->post('/u/followowner/follow'));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'user' AND target_id = ?",
            [(int) $follower['id'], (int) $owner['id']],
        ));

        $this->actingAs($owner);
        $page = $this->get('/u/followowner/followers');
        $this->assertSeeText($page, 'Remove');
        $this->assertRedirect($this->post('/u/followowner/followers/' . (int) $follower['id'] . '/remove'));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM follows WHERE user_id = ? AND target_type = 'user' AND target_id = ?",
            [(int) $follower['id'], (int) $owner['id']],
        ));
    }

    public function testCommunityMemorySummaryRelatedAndWikiRevision(): void
    {
        $admin = $this->makeAdmin(['username' => 'memoryadmin']);
        $author = $this->makeUser(['username' => 'memoryauthor']);
        $board = $this->makeBoard($this->makeCategory());
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $thread = $this->makeThread($board, $author, 'Memory topic', 'Original body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        $related = $this->makeThread($board, $author, 'Related topic', 'Related body');

        $this->actingAs($admin);
        $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->post('/t/' . $thread['thread_id'] . '/summary', ['body' => 'Canonical summary', 'source_post_ids' => (string) $postId]);
        $this->post('/t/' . $thread['thread_id'] . '/related', ['related_thread_id' => $related['thread_id'], 'reason' => 'Same decision']);
        $this->post('/posts/' . $postId . '/wiki');
        $this->post('/posts/' . $postId . '/wiki/edit', ['body' => 'Updated wiki body', 'reason' => 'clarify']);

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertSeeText($page, 'Canonical summary');
        $this->assertSeeText($page, 'Related topic');
        $this->assertSeeText($page, 'Updated wiki body');
        self::assertGreaterThanOrEqual(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM post_revisions WHERE post_id = ?', [$postId]));
    }

    public function testCommunityMemorySummaryRollbackSourcesAndWikiRevert(): void
    {
        $admin = $this->makeAdmin(['username' => 'rollbackadmin']);
        $author = $this->makeUser(['username' => 'rollbackauthor']);
        $board = $this->makeBoard($this->makeCategory());
        $this->db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $thread = $this->makeThread($board, $author, 'Rollback topic', 'Original body');
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/summary', ['body' => 'First summary', 'source_post_ids' => (string) $postId]));
        $firstSummaryId = (int) $this->db->fetchValue('SELECT id FROM thread_summaries WHERE thread_id = ? AND version = 1', [$thread['thread_id']]);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/summary', ['body' => 'Second summary', 'source_post_ids' => '']));
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertSeeText($page, 'Second summary');

        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/summary/restore', ['summary_id' => $firstSummaryId]));
        $restored = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertSeeText($restored, 'First summary');
        $this->assertSeeText($restored, 'Source');
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/summary/retire'));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND status = 'published'", [$thread['thread_id']]));

        $this->assertRedirect($this->post('/posts/' . $postId . '/wiki'));
        $originalRevisionId = (int) $this->db->fetchValue('SELECT MIN(id) FROM post_revisions WHERE post_id = ?', [$postId]);
        $this->assertRedirect($this->post('/posts/' . $postId . '/wiki/edit', ['body' => 'Updated wiki body', 'reason' => 'change']));
        self::assertSame('Updated wiki body', (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$postId]));
        $this->assertRedirect($this->post('/posts/' . $postId . '/wiki/revert', ['revision_id' => $originalRevisionId]));
        self::assertSame('Original body', (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$postId]));
    }

    public function testSummarySourceMasksAnonymousAuthor(): void
    {
        $admin = $this->makeAdmin(['username' => 'memoryanonadmin']);
        $author = $this->makeUser(['username' => 'memoryanonauthor', 'display_name' => 'Anon Author Real']);
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'],
            'title' => 'Anon source topic',
            'body' => 'Anon source body',
            'is_anonymous' => '1',
        ]);
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']]);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_anonymous FROM posts WHERE id = ?', [$postId]));

        $this->actingAs($admin);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/summary', [
            'body' => 'Summary citing an anonymous post',
            'source_post_ids' => (string) $postId,
        ]));

        // The summary source list renders to every viewer (not just curators), so an
        // anonymously-posted source must show "Anonymous", never the real author.
        $reader = $this->makeUser(['username' => 'memoryanonreader']);
        $this->actingAs($reader);
        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Summary citing an anonymous post');
        $this->assertSeeText($page, 'Anonymous');
        $this->assertDontSeeText($page, '@memoryanonauthor');
        $this->assertDontSeeText($page, 'Anon Author Real');
        $this->assertDontSeeText($page, '/u/memoryanonauthor');
    }
}
