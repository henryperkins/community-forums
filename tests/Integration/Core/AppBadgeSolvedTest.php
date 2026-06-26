<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BadgeRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\RepairService;
use Tests\Support\TestCase;

/**
 * Badges (auto award) + accepted/"solved" answers (P2-09): idempotent awards,
 * the +5 reputation bonus with self-answer exclusion, scoped authorization, and
 * reputation reconciliation including the solved bonus.
 */
final class AppBadgeSolvedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(); // satisfy the first-run setup gate
    }

    private function badges(int $conv = 2, int $trusted = 2): BadgeService
    {
        return new BadgeService(
            $this->db,
            new BadgeRepository($this->db),
            new UserRepository($this->db),
            null,
            $conv,
            $trusted,
            100,
            1000,
        );
    }

    public function test_post_milestone_badges_award_idempotently(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'b1']);
        $user = $this->makeUser(['username' => 'poster']);

        $t = $this->makeThread($board, $user, 'My first topic');
        $this->posting()->reply($this->userEntity($user), $t['thread_id'], ['body' => 'my first reply']);

        $badges = $this->badges();
        $awarded = $badges->evaluateForUser((int) $user['id']);
        sort($awarded);
        self::assertContains('first-thread', $awarded);
        self::assertContains('first-post', $awarded);
        self::assertNotContains('conversation-starter', $awarded); // only 1 thread, threshold 2

        // Re-evaluating awards nothing new (idempotent).
        self::assertSame([], $badges->evaluateForUser((int) $user['id']));

        $repo = new BadgeRepository($this->db);
        self::assertTrue($repo->hasBadgeSlug((int) $user['id'], 'first-thread'));
        self::assertTrue($repo->hasBadgeSlug((int) $user['id'], 'first-post'));
    }

    public function test_conversation_starter_at_threshold(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'b2']);
        $user = $this->makeUser(['username' => 'chatty']);
        $this->makeThread($board, $user, 'one');
        $this->makeThread($board, $user, 'two');

        $awarded = $this->badges(2)->evaluateForUser((int) $user['id']);
        self::assertContains('conversation-starter', $awarded);
    }

    public function test_reputation_and_time_badges(): void
    {
        $appreciated = $this->makeUser(['username' => 'liked']);
        $this->db->run('UPDATE users SET reputation = 100 WHERE id = ?', [(int) $appreciated['id']]);
        self::assertContains('appreciated', $this->badges()->evaluateForUser((int) $appreciated['id']));

        $verified = $this->makeUser(['username' => 'verified']);
        $this->users()->markEmailVerified((int) $verified['id']);
        self::assertContains('welcome', $this->badges()->evaluateForUser((int) $verified['id']));

        $veteran = $this->makeUser(['username' => 'oldtimer']);
        $this->db->run('UPDATE users SET created_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 400 * 86400), (int) $veteran['id']]);
        self::assertContains('anniversary', $this->badges()->evaluateForUser((int) $veteran['id']));
    }

    public function test_accept_answer_awards_bonus_badge_and_notifies(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa']);
        $asker = $this->makeUser(['username' => 'asker']);
        $answerer = $this->makeUser(['username' => 'answerer']);

        $t = $this->makeThread($board, $asker, 'How do I X?');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'Do it like this.']);

        $this->actingAs($asker);
        $res = $this->post('/posts/' . $replyId . '/accept');
        $this->assertRedirectContains($res, '/t/' . $t['thread_id']);

        $thread = $this->threads()->find($t['thread_id']);
        self::assertSame($replyId, (int) $thread['accepted_answer_post_id']);

        // Answerer earns +5 rep, the Problem Solver badge, and a solved notification.
        self::assertSame(5, (int) $this->users()->find((int) $answerer['id'])['reputation']);
        self::assertTrue((new BadgeRepository($this->db))->hasBadgeSlug((int) $answerer['id'], 'problem-solver'));

        $types = array_column((new NotificationRepository($this->db))->recent((int) $answerer['id'], 50), 'type');
        self::assertContains('solved', $types);

        // Audited.
        $logged = $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'thread.solved' AND target_id = ?",
            [$t['thread_id']],
        );
        self::assertSame(1, (int) $logged);
    }

    public function test_accept_is_idempotent_for_reputation(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa2']);
        $asker = $this->makeUser(['username' => 'q']);
        $answerer = $this->makeUser(['username' => 'a']);
        $t = $this->makeThread($board, $asker, 'Q');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A']);

        $this->actingAs($asker);
        $this->post('/posts/' . $replyId . '/accept');
        $this->post('/posts/' . $replyId . '/accept');

        self::assertSame(5, (int) $this->users()->find((int) $answerer['id'])['reputation']);
    }

    public function test_self_answer_earns_no_bonus(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa3']);
        $op = $this->makeUser(['username' => 'selfsolver']);
        $t = $this->makeThread($board, $op, 'Self Q');
        $replyId = $this->posting()->reply($this->userEntity($op), $t['thread_id'], ['body' => 'Self A']);

        $this->actingAs($op);
        $this->post('/posts/' . $replyId . '/accept');

        $thread = $this->threads()->find($t['thread_id']);
        self::assertSame($replyId, (int) $thread['accepted_answer_post_id']); // still marked solved
        self::assertSame(0, (int) $this->users()->find((int) $op['id'])['reputation']); // but no self bonus
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug((int) $op['id'], 'problem-solver'));
    }

    public function test_unaccept_removes_bonus_but_keeps_badge(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa4']);
        $asker = $this->makeUser(['username' => 'q4']);
        $answerer = $this->makeUser(['username' => 'a4']);
        $t = $this->makeThread($board, $asker, 'Q4');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A4']);

        $this->actingAs($asker);
        $this->post('/posts/' . $replyId . '/accept');
        self::assertSame(5, (int) $this->users()->find((int) $answerer['id'])['reputation']);

        $this->post('/t/' . $t['thread_id'] . '/unaccept');
        $thread = $this->threads()->find($t['thread_id']);
        self::assertNull($thread['accepted_answer_post_id']);
        self::assertSame(0, (int) $this->users()->find((int) $answerer['id'])['reputation']);
        // Badge is permanent recognition.
        self::assertTrue((new BadgeRepository($this->db))->hasBadgeSlug((int) $answerer['id'], 'problem-solver'));
    }

    public function test_non_op_non_mod_cannot_accept(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa5']);
        $asker = $this->makeUser(['username' => 'q5']);
        $answerer = $this->makeUser(['username' => 'a5']);
        $stranger = $this->makeUser(['username' => 'nosy']);
        $t = $this->makeThread($board, $asker, 'Q5');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A5']);

        $this->actingAs($stranger);
        $res = $this->post('/posts/' . $replyId . '/accept');
        $this->assertStatus(403, $res);
        self::assertNull($this->threads()->find($t['thread_id'])['accepted_answer_post_id']);
    }

    public function test_board_moderator_can_accept(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa6']);
        $asker = $this->makeUser(['username' => 'q6']);
        $answerer = $this->makeUser(['username' => 'a6']);
        $mod = $this->makeUser(['username' => 'mod6']);
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $mod['id']);

        $t = $this->makeThread($board, $asker, 'Q6');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A6']);

        $this->actingAs($mod);
        $this->post('/posts/' . $replyId . '/accept');
        self::assertSame($replyId, (int) $this->threads()->find($t['thread_id'])['accepted_answer_post_id']);
    }

    public function test_cannot_accept_opening_post(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa7']);
        $op = $this->makeUser(['username' => 'q7']);
        $t = $this->makeThread($board, $op, 'Q7');
        $opPostId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$t['thread_id']]);

        $this->actingAs($op);
        $this->post('/posts/' . $opPostId . '/accept');
        self::assertNull($this->threads()->find($t['thread_id'])['accepted_answer_post_id']);
    }

    public function test_deleting_accepted_answer_reverses_bonus_and_clears_solved(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa9']);
        $asker = $this->makeUser(['username' => 'q9']);
        $answerer = $this->makeUser(['username' => 'a9']);
        $t = $this->makeThread($board, $asker, 'Q9');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A9']);

        $this->actingAs($asker);
        $this->post('/posts/' . $replyId . '/accept');
        self::assertSame(5, (int) $this->users()->find((int) $answerer['id'])['reputation']);

        // The answerer deletes their own accepted post: the bonus must come back
        // off and the thread is no longer solved (matches RepairService).
        $this->actingAs($answerer);
        $this->post('/posts/' . $replyId . '/delete');

        self::assertNull($this->threads()->find($t['thread_id'])['accepted_answer_post_id']);
        self::assertSame(0, (int) $this->users()->find((int) $answerer['id'])['reputation']);

        // Reconciliation agrees (no drift).
        (new RepairService($this->db, 5))->repairAll();
        self::assertSame(0, (int) $this->users()->find((int) $answerer['id'])['reputation']);
    }

    public function test_self_accepted_answer_does_not_count_toward_problem_solver(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa10']);
        $op = $this->makeUser(['username' => 'selfbadge']);
        $t = $this->makeThread($board, $op, 'Self Q10');
        $replyId = $this->posting()->reply($this->userEntity($op), $t['thread_id'], ['body' => 'Self A10']);

        $this->actingAs($op);
        $this->post('/posts/' . $replyId . '/accept');

        // Self-accepts must not count, so evaluating badges never awards Problem Solver.
        self::assertSame(0, $this->users()->solvedAnswerCount((int) $op['id']));
        $this->badges()->evaluateForUser((int) $op['id']);
        self::assertFalse((new BadgeRepository($this->db))->hasBadgeSlug((int) $op['id'], 'problem-solver'));
    }

    public function test_reputation_repair_includes_solved_bonus(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'qa8']);
        $asker = $this->makeUser(['username' => 'q8']);
        $answerer = $this->makeUser(['username' => 'a8']);
        $t = $this->makeThread($board, $asker, 'Q8');
        $replyId = $this->posting()->reply($this->userEntity($answerer), $t['thread_id'], ['body' => 'A8']);

        $this->actingAs($asker);
        $this->post('/posts/' . $replyId . '/accept');

        // Corrupt the counter, then reconcile: it should rebuild to the solved bonus.
        $this->db->run('UPDATE users SET reputation = 999 WHERE id = ?', [(int) $answerer['id']]);
        $repair = new RepairService($this->db, 5);
        $repair->repairReputation();
        $repair->reputationSolvedBonus();

        self::assertSame(5, (int) $this->users()->find((int) $answerer['id'])['reputation']);
    }
}
