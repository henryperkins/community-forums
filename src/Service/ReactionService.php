<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;

/**
 * Reaction toggling with transactional reputation accounting (P2-02).
 *
 * Invariants (PHASE_2_PLAN §2, §7.3):
 *  - one reaction per (user, post, emoji); toggling removes it (idempotent).
 *  - a received reaction is +1 reputation to the post author; self-reactions
 *    contribute zero.
 *  - the reaction row and the reputation counter commit or roll back together.
 */
final class ReactionService
{
    /** Fixed, expressive reaction set (COMMUNITY §3). */
    public const ALLOWED = ['👍', '❤️', '😂', '🎉', '🔥', '💯', '😮', '😢', '👀'];

    public function __construct(
        private Database $db,
        private ReactionRepository $reactions,
        private PostRepository $posts,
        private UserRepository $users,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
        private ?NotificationService $notifications = null,
        private ?ReputationLedgerService $reputation = null,
        private ?CustomEmojiService $customEmoji = null,
    ) {
    }

    public function isAllowed(string $emoji): bool
    {
        if (in_array($emoji, self::ALLOWED, true)) {
            return true;
        }
        return $this->customEmoji !== null
            && preg_match('/^:[a-z0-9_+-]{2,40}:$/', $emoji) === 1
            && $this->customEmoji->isReactionAllowed($emoji);
    }

    /**
     * Toggle the user's $emoji reaction on $postId.
     *
     * @return array{state:string, counts:array<string,int>, post:array<string,mixed>, notify_author:bool}
     */
    public function toggle(User $user, int $postId, string $emoji): array
    {
        $this->writeGate->assertCanWrite($user);

        if (!$this->isAllowed($emoji)) {
            throw new ValidationException(['emoji' => 'That reaction is not available.']);
        }

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        $isMember = $this->users->isBoardMember((int) $post['board_id'], $user->id());
        if (!$this->policy->canRead(['visibility' => $post['board_visibility']], $user, $isMember)) {
            throw new NotFoundException('Post not found.');
        }
        // Archived boards are frozen for everyone: reacting mutates reactions +
        // reputation, so it is closed until the board is unarchived.
        if ($this->policy->isArchived(['is_archived' => $post['board_is_archived'] ?? 0])) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }

        $authorId = (int) $post['user_id'];
        $isSelf = $user->id() === $authorId;

        $state = $this->db->transaction(function () use ($user, $post, $postId, $emoji, $authorId, $isSelf): string {
            $state = $this->reactions->toggle($postId, $user->id(), $emoji);
            if (!$isSelf) {
                // Self-reactions never affect reputation (COMMUNITY §2.1).
                $key = 'reaction:' . $postId . ':' . $user->id() . ':' . sha1($emoji);
                if ($this->reputation !== null) {
                    if ($state === 'added') {
                        $this->reputation->apply($authorId, (int) $post['board_id'], 'reaction', $postId, $key, 1);
                    } else {
                        $this->reputation->reverse($key, $user->id(), 'reaction_removed');
                    }
                } else {
                    $this->users->incrementReputation($authorId, $state === 'added' ? 1 : -1);
                }
                // Notify the author once per (recipient, actor, post) on add.
                if ($state === 'added' && $this->notifications !== null) {
                    $this->notifications->notifyReaction($user->id(), $post);
                }
            }
            return $state;
        });

        return [
            'state' => $state,
            'counts' => $this->reactions->countsForPost($postId),
            'post' => $post,
            // A new reaction from someone other than the author triggers a
            // 'reaction' notification (wired in M2 / P2-03).
            'notify_author' => $state === 'added' && !$isSelf,
        ];
    }
}
