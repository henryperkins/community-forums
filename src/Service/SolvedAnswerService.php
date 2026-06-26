<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;

/**
 * Accepted/"solved" answer selection (COMMUNITY §11, P2-09). The thread OP or an
 * authorized moderator may mark one reply as the accepted answer. The answer's
 * author receives a one-time reputation bonus and the Problem Solver badge, plus
 * an in-app + email notification — all in a single transaction with the audit
 * row (PHASE_2_PLAN §7.3). Self-answers (the OP's own reply) earn no bonus, which
 * also closes the obvious self-reputation-farming path; the thread can still be
 * marked solved.
 */
final class SolvedAnswerService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private UserRepository $users,
        private BoardModeratorRepository $boardModerators,
        private ModerationLogRepository $log,
        private BadgeService $badges,
        private NotificationService $notifications,
        private WriteGate $writeGate,
        private int $solvedBonus = 5,
    ) {
    }

    /** Mark $postId as the accepted answer of $threadId. */
    public function mark(User $actor, int $threadId, int $postId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->requireThread($threadId);
        $this->authorize($actor, $thread);

        $post = $this->posts->find($postId);
        if ($post === null || (int) $post['is_deleted'] === 1 || (int) $post['thread_id'] !== $threadId) {
            throw new NotFoundException('That answer could not be found.');
        }
        if ((int) $post['is_op'] === 1) {
            throw new ValidationException(['post' => 'The opening post cannot be the accepted answer.']);
        }

        $previous = $thread['accepted_answer_post_id'] !== null ? (int) $thread['accepted_answer_post_id'] : null;
        if ($previous === $postId) {
            return; // already the accepted answer — idempotent no-op
        }

        $opId = (int) $thread['user_id'];
        $answerAuthorId = (int) $post['user_id'];

        $this->db->transaction(function () use ($thread, $threadId, $postId, $previous, $opId, $answerAuthorId, $actor): void {
            // Moving the accepted answer: take the bonus back off the old author.
            if ($previous !== null) {
                $this->adjustBonusFor($previous, $opId, -$this->solvedBonus);
            }
            $this->threads->setAcceptedAnswer($threadId, $postId);

            $bonusApplies = $answerAuthorId !== $opId;
            if ($bonusApplies) {
                $this->users->incrementReputation($answerAuthorId, $this->solvedBonus);
                // problem-solver / trusted-answerer derive from the now-updated count.
                $this->badges->evaluateForUser($answerAuthorId);
                $this->notifications->notifySolved($answerAuthorId, $actor->id(), $threadId, $postId);
            }

            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'thread.solved',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['accepted_answer_post_id' => $previous],
                'after' => ['accepted_answer_post_id' => $postId],
            ]);
        });
    }

    /** Clear the accepted answer of $threadId. */
    public function unmark(User $actor, int $threadId): void
    {
        $this->writeGate->assertCanWrite($actor);
        $thread = $this->requireThread($threadId);
        $this->authorize($actor, $thread);

        $previous = $thread['accepted_answer_post_id'] !== null ? (int) $thread['accepted_answer_post_id'] : null;
        if ($previous === null) {
            return;
        }
        $opId = (int) $thread['user_id'];

        $this->db->transaction(function () use ($threadId, $previous, $opId, $actor): void {
            $this->adjustBonusFor($previous, $opId, -$this->solvedBonus);
            $this->threads->setAcceptedAnswer($threadId, null);
            // The Problem Solver badge is permanent — recognition isn't revoked.
            $this->log->log([
                'actor_id' => $actor->id(),
                'action' => 'thread.unsolved',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['accepted_answer_post_id' => $previous],
                'after' => ['accepted_answer_post_id' => null],
            ]);
        });
    }

    /** Reputation only changed when the post still exists and wasn't a self-answer. */
    private function adjustBonusFor(int $postId, int $opId, int $delta): void
    {
        $post = $this->posts->find($postId);
        if ($post === null) {
            return;
        }
        if ((int) $post['user_id'] !== $opId) {
            $this->users->incrementReputation((int) $post['user_id'], $delta);
        }
    }

    /** @return array<string,mixed> */
    private function requireThread(int $threadId): array
    {
        $thread = $this->threads->find($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    /** @param array<string,mixed> $thread */
    private function authorize(User $actor, array $thread): void
    {
        $isOp = (int) $thread['user_id'] === $actor->id();
        $isMod = $actor->isAdmin()
            || $this->boardModerators->isModerator((int) $thread['board_id'], $actor->id());
        if (!$isOp && !$isMod) {
            throw new ForbiddenException('Only the topic author or a moderator can accept an answer.');
        }
    }
}
