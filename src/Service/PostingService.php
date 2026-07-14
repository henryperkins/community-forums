<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\DuplicateSubmissionException;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\AttachmentRepository;
use App\Repository\BoardRepository;
use App\Repository\IdempotencyRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WebhookEvents;
use App\Security\WriteGate;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Support\Markdown;
use App\Support\MentionParser;
use App\Support\Str;

/**
 * Create/edit/delete posts and threads. Every write runs through the account
 * write gate, sanitises Markdown to body_html, and maintains the denormalised
 * counters (board.thread_count/post_count, thread.reply_count/last_post_*,
 * users.post_count) inside one transaction so they can never drift.
 */
final class PostingService
{
    public function __construct(
        private Database $db,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BoardRepository $boards,
        private UserRepository $users,
        private Markdown $markdown,
        private WriteGate $writeGate,
        private BoardPolicy $policy,
        private Config $config,
        private ?NotificationService $notifications = null,
        private ?AntiAbuseService $antiAbuse = null,
        private ?IdempotencyRepository $idempotency = null,
        private ?AttachmentRepository $attachments = null,
        private ?ReputationLedgerService $reputation = null,
        private ?FirstPartyHookRegistry $hooks = null,
        private ?ContentReferenceService $contentReferences = null,
        private ?LinkPreviewService $linkPreviews = null,
        private ?AuthorityGate $authority = null,
        private ?ThreadIntelligenceQueue $threadIntelligence = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    /**
     * Bind any /media/{id} images the body references to this post + visibility
     * (P3-04). Only the author's own temp uploads are finalized; media in a
     * private/hidden board is marked private so delivery stays access-gated.
     */
    private function finalizeAttachments(int $ownerId, int $postId, string $body, string $boardVisibility): void
    {
        if ($this->attachments === null) {
            return;
        }
        $ids = \App\Service\AttachmentService::referencedIds($body);
        if ($ids === []) {
            return;
        }
        $visibility = $boardVisibility === 'public' ? 'public' : 'private';
        $this->attachments->finalizeForPost($ownerId, $postId, $ids, $visibility);
    }

    /**
     * @param array<string,mixed> $input board_id, title, body
     * @return array{thread_id:int, slug:string}
     */
    public function createThread(User $user, array $input): array
    {
        $this->writeGate->assertCanWrite($user);

        $boardId = (int) ($input['board_id'] ?? 0);
        $board = $this->boards->find($boardId);
        if ($board === null) {
            throw new ValidationException(['board_id' => 'Choose a board to post in.'], $input);
        }
        if (!$this->gate()->allows(
            fn (): bool => $this->policy->canPost($board, $user, $this->isBoardMember($boardId, $user->id())),
            $user,
            Cap::THREAD_CREATE,
            ['board_id' => $boardId],
            'PostingService::createThread',
        )) {
            throw new ForbiddenException('You cannot post in this board.');
        }

        $title = trim((string) ($input['title'] ?? ''));
        $body = (string) ($input['body'] ?? '');
        $this->validate($title, $body, $input, requireTitle: true);

        // Idempotent submit (P3-03): replay the original result if this client
        // token already produced a thread (double-click, retry, browser resend).
        $idemKey = $this->idempotency?->hash($input['idempotency_key'] ?? null);
        if ($idemKey !== null) {
            $existing = $this->idempotency->findWithContext($user->id(), $idemKey);
            if ($existing !== null && $existing['context'] === 'thread' && $existing['result_type'] === 'thread') {
                $prior = $this->threads->find($existing['result_id']);
                if ($prior !== null) {
                    return ['thread_id' => (int) $prior['id'], 'slug' => (string) $prior['slug'], 'pending' => (int) $prior['is_pending'] === 1, 'duplicate' => true];
                }
            }
        }

        // Central anti-abuse evaluation (P3-05). block ⇒ reject (no content);
        // hold ⇒ create pending; flag/observe ⇒ create + audit only.
        $decision = $this->antiAbuse?->evaluate($user, 'thread', $body, $title);
        if ($decision !== null && $decision->blocks()) {
            $this->antiAbuse->audit($decision, 'thread', 0, (string) ($board['visibility'] ?? 'public'));
            throw new ValidationException(['body' => 'Your post couldn’t be published by the automated content filters. Please revise it and try again.'], $input);
        }
        $pending = ($decision !== null && $decision->holds()) || (int) ($board['require_approval'] ?? 0) === 1;

        // Anonymity is granted only when the board allows it AND the author opted
        // in — the server is the sole source of truth; the form value alone is
        // never trusted (ADMIN §1.3 masked-identity posting).
        $anon = !empty($input['is_anonymous']) && (int) ($board['allow_anonymous'] ?? 0) === 1;

        try {
            $result = $this->db->transaction(function () use ($user, $board, $boardId, $title, $body, $anon, $pending, $decision, $idemKey, $input): array {
                $now = gmdate('Y-m-d H:i:s');
                $slug = Str::slug($title, 180);
                $threadId = $this->threads->create($boardId, $user->id(), $title, $slug, $pending);

                $postId = $this->posts->create([
                    'thread_id' => $threadId,
                    'user_id' => $user->id(),
                    'body' => $body,
                    'body_html' => $this->markdown->render($body, ['link_mentions' => true]),
                    'is_op' => true,
                    'is_anonymous' => $anon,
                    'is_pending' => $pending,
                    'ip' => $input['ip'] ?? null,
                ]);

                // Claim the idempotency key BEFORE any further side effects. Under a
                // concurrent double-submit the unique-key INSERT blocks until the
                // winner commits, then collides here — we throw to roll the whole
                // transaction back (undoing this thread) and replay the original.
                if ($idemKey !== null && !$this->idempotency->record($user->id(), $idemKey, 'thread', 'thread', $threadId)) {
                    throw new DuplicateSubmissionException();
                }

                $this->finalizeAttachments($user->id(), $postId, $body, (string) ($board['visibility'] ?? 'public'));
                $this->contentReferences?->capture('post', $postId, $body);
                $this->linkPreviews?->queueFromBody('post', $postId, $body);

                // A held thread does not yet touch board counters, last-post, the
                // author's post_count, or notification fan-out — those run only when a
                // moderator releases it (PHASE_3_PLAN §8.5: media/holds become visible
                // only after approval). The author still auto-subscribes.
                if (!$pending) {
                    $this->threads->updateLastPost($threadId, $postId, $user->id(), $now);
                    $this->boards->onThreadCreated($boardId, $threadId, $now);
                    $this->users->incrementPostCount($user->id(), 1);
                    if ($this->notifications !== null) {
                        $threadCtx = ['id' => $threadId, 'board_id' => $boardId, 'board_visibility' => $board['visibility'] ?? 'public'];
                        $this->notifications->autoSubscribeAuthor($user->id(), $threadId);
                        $this->notifications->fanOutNewPost($user->id(), $threadCtx, $postId, true, $body);
                    }
                } elseif ($this->notifications !== null) {
                    $this->notifications->autoSubscribeAuthor($user->id(), $threadId);
                }

                // Audit + hold commit together (immutable system-actor record).
                if ($decision !== null) {
                    $this->antiAbuse->audit($decision, 'thread', $threadId, (string) ($board['visibility'] ?? 'public'));
                }

                if (!$pending) {
                    $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
                }

                return ['thread_id' => $threadId, 'slug' => $slug, 'pending' => $pending, 'post_id' => $postId];
            });
            if (empty($result['pending']) && (string) ($board['visibility'] ?? 'public') === 'public') {
                $this->emitTopicCreated((int) $result['thread_id'], (int) $result['post_id'], $boardId, $user->id(), $anon);
            }
            return $result;
        } catch (DuplicateSubmissionException) {
            $prior = $idemKey !== null ? $this->idempotency->findWithContext($user->id(), $idemKey) : null;
            // Only replay when the stored result is actually a thread — a token
            // reused across a thread and a reply must not cross result types.
            $thread = ($prior !== null && $prior['context'] === 'thread' && $prior['result_type'] === 'thread')
                ? $this->threads->find($prior['result_id'])
                : null;
            if ($thread !== null) {
                return ['thread_id' => (int) $thread['id'], 'slug' => (string) $thread['slug'], 'pending' => (int) $thread['is_pending'] === 1, 'duplicate' => true];
            }
            throw new ValidationException(['body' => 'That post was already submitted.'], $input);
        }
    }

    /**
     * @param array<string,mixed> $input body
     * @return int new post id
     */
    public function reply(User $user, int $threadId, array $input): int
    {
        $this->writeGate->assertCanWrite($user);

        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $board = [
            'visibility' => $thread['board_visibility'],
            'post_min_role' => $thread['board_post_min_role'] ?? 'user',
            'is_archived' => (int) ($thread['board_is_archived'] ?? 0),
        ];
        if (!$this->gate()->allows(
            fn (): bool => $this->policy->canPost($board, $user, $this->isBoardMember((int) $thread['board_id'], $user->id())),
            $user,
            Cap::POST_CREATE,
            ['board_id' => (int) $thread['board_id']],
            'PostingService::reply',
        )) {
            throw new ForbiddenException('You cannot post in this board.');
        }
        if ((int) $thread['is_locked'] === 1) {
            throw new ForbiddenException('This thread is locked and is not accepting replies.');
        }

        $body = (string) ($input['body'] ?? '');
        $this->validate(null, $body, $input, requireTitle: false);

        // Idempotent submit (P3-03): replay the original reply on a duplicate.
        $idemKey = $this->idempotency?->hash($input['idempotency_key'] ?? null);
        if ($idemKey !== null) {
            $existing = $this->idempotency->findWithContext($user->id(), $idemKey);
            if ($existing !== null && $existing['context'] === 'reply' && $existing['result_type'] === 'post') {
                return $existing['result_id'];
            }
        }

        // Anti-abuse (P3-05): block ⇒ reject; hold/board-approval ⇒ pending reply.
        $decision = $this->antiAbuse?->evaluate($user, 'reply', $body);
        if ($decision !== null && $decision->blocks()) {
            $this->antiAbuse->audit($decision, 'post', 0, (string) ($thread['board_visibility'] ?? 'public'));
            throw new ValidationException(['body' => 'Your reply couldn’t be published by the automated content filters. Please revise it and try again.'], $input);
        }
        $pending = ($decision !== null && $decision->holds()) || (int) ($thread['board_require_approval'] ?? 0) === 1;

        // Server-side anonymity gate (board allows it AND author opted in). The
        // board flag comes from findWithBoard's board_allow_anonymous alias, so
        // the synthetic $board above is never trusted for this decision.
        $anon = !empty($input['is_anonymous']) && (int) ($thread['board_allow_anonymous'] ?? 0) === 1;

        try {
            $postId = $this->db->transaction(function () use ($user, $thread, $threadId, $body, $anon, $pending, $decision, $idemKey, $input): int {
                $now = gmdate('Y-m-d H:i:s');
                $postId = $this->posts->create([
                    'thread_id' => $threadId,
                    'user_id' => $user->id(),
                    'body' => $body,
                    'body_html' => $this->markdown->render($body, ['link_mentions' => true]),
                    'is_op' => false,
                    'is_anonymous' => $anon,
                    'is_pending' => $pending,
                    'ip' => $input['ip'] ?? null,
                ]);

                // Claim the idempotency key before side effects (see createThread).
                if ($idemKey !== null && !$this->idempotency->record($user->id(), $idemKey, 'reply', 'post', $postId)) {
                    throw new DuplicateSubmissionException();
                }

                $this->finalizeAttachments($user->id(), $postId, $body, (string) ($thread['board_visibility'] ?? 'public'));
                $this->contentReferences?->capture('post', $postId, $body);
                $this->linkPreviews?->queueFromBody('post', $postId, $body);

                // A held reply defers reply-count, last-post, board activity, the
                // author's post_count, and fan-out until a moderator releases it.
                if (!$pending) {
                    $this->threads->incrementReplyCount($threadId, 1);
                    $this->threads->updateLastPost($threadId, $postId, $user->id(), $now);
                    $this->boards->onReplyCreated((int) $thread['board_id'], $now);
                    $this->users->incrementPostCount($user->id(), 1);
                    if ($this->notifications !== null) {
                        $threadCtx = [
                            'id' => $threadId,
                            'board_id' => (int) $thread['board_id'],
                            'board_visibility' => $thread['board_visibility'] ?? 'public',
                        ];
                        $this->notifications->fanOutNewPost($user->id(), $threadCtx, $postId, false, $body);
                    }
                }

                if ($decision !== null) {
                    $this->antiAbuse->audit($decision, 'post', $postId, (string) ($thread['board_visibility'] ?? 'public'));
                }

                if (!$pending) {
                    $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_POST_CREATED);
                }

                return $postId;
            });
            if (!$pending && (string) ($thread['board_visibility'] ?? 'public') === 'public') {
                $this->emitReplyCreated($postId, $threadId, (int) $thread['board_id'], $user->id(), $anon);
            }
            return $postId;
        } catch (DuplicateSubmissionException) {
            $prior = $idemKey !== null ? $this->idempotency->findWithContext($user->id(), $idemKey) : null;
            if ($prior !== null && $prior['context'] === 'reply' && $prior['result_type'] === 'post') {
                return $prior['result_id'];
            }
            throw new ValidationException(['body' => 'That reply was already submitted.'], $input);
        }
    }

    /** @param array<string,mixed> $input body */
    public function editOwnPost(User $user, int $postId, array $input): array
    {
        $this->writeGate->assertCanWrite($user);

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        if (!$user->owns((int) $post['user_id'])) {
            throw new ForbiddenException('You can only edit your own posts.');
        }
        $editBoard = $this->boards->find((int) $post['board_id']);
        if ($editBoard !== null && $this->policy->isArchived($editBoard)) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }

        $body = (string) ($input['body'] ?? '');
        $this->validate(null, $body, $input, requireTitle: false);
        $changed = $body !== (string) $post['body'];
        $idemKey = $this->idempotency?->hash($input['idempotency_key'] ?? null);
        if ($idemKey !== null) {
            $existing = $this->idempotency->findWithContext($user->id(), $idemKey);
            if ($existing !== null && $existing['context'] === 'post_edit'
                && $existing['result_type'] === 'post' && $existing['result_id'] === $postId) {
                return $post;
            }
        }

        // Only NEW @mentions introduced by the edit are notified; existing ones
        // are not resent (PHASE_2_PLAN §8 "Mention edit").
        $before = MentionParser::parse((string) $post['body']);
        $after = MentionParser::parse($body);
        $beforeLower = array_map('strtolower', $before);
        $added = array_values(array_filter($after, static fn (string $h): bool => !in_array(strtolower($h), $beforeLower, true)));

        try {
            $this->db->transaction(function () use ($post, $postId, $body, $user, $added, $changed, $idemKey): void {
                if ($idemKey !== null && !$this->idempotency->record($user->id(), $idemKey, 'post_edit', 'post', $postId)) {
                    throw new DuplicateSubmissionException();
                }
                $this->posts->update($postId, $body, $this->markdown->render($body, ['link_mentions' => true]), $user->id());
                // Bind images the edit newly references. The edit composer uploads
                // pasted/dropped images as temp attachments exactly like create/reply;
                // without finalizing here they stay 'temp' (invisible to other readers)
                // and the orphan sweep permanently purges them while the live post
                // still links /media/{id}. finalizeForPost is owner/temp-scoped, so
                // re-finalizing already-bound images is a harmless no-op.
                $this->finalizeAttachments($user->id(), $postId, $body, (string) ($post['board_visibility'] ?? 'public'));
                $this->contentReferences?->capture('post', $postId, $body);
                $this->linkPreviews?->queueFromBody('post', $postId, $body);
                if ($this->notifications !== null && $added !== []) {
                    $threadCtx = [
                        'id' => (int) $post['thread_id'],
                        'board_id' => (int) $post['board_id'],
                        'board_visibility' => $post['board_visibility'] ?? 'public',
                    ];
                    $this->notifications->notifyMentions($user->id(), $threadCtx, $postId, $added);
                }
                if ($changed) {
                    $this->threadIntelligence?->markStale(
                        (int) $post['thread_id'],
                        ThreadIntelligenceQueue::TRIGGER_POST_EDITED,
                    );
                }
            });
        } catch (DuplicateSubmissionException) {
            $prior = $idemKey !== null ? $this->idempotency->findWithContext($user->id(), $idemKey) : null;
            if ($prior !== null && $prior['context'] === 'post_edit'
                && $prior['result_type'] === 'post' && $prior['result_id'] === $postId) {
                return $post;
            }
            throw new ValidationException(['body' => 'That edit was already submitted.'], $input);
        }

        if ($changed) {
            $updated = $this->posts->findWithContext($postId);
            if ($updated !== null) {
                $this->emitPostEdited($updated);
            }
        }

        return $post;
    }

    /**
     * Owner self-delete (no audit log — that is the moderation path). Returns
     * the post row for the controller's redirect.
     *
     * @return array<string,mixed>
     */
    public function deleteOwnPost(User $user, int $postId): array
    {
        $this->writeGate->assertCanWrite($user);

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        if (!$user->owns((int) $post['user_id'])) {
            throw new ForbiddenException('You can only delete your own posts.');
        }
        $deleteBoard = $this->boards->find((int) $post['board_id']);
        if ($deleteBoard !== null && $this->policy->isArchived($deleteBoard)) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }

        // Deleting the opening post retracts the whole topic instead of leaving a
        // headless, still-listed thread (the board list keys on the thread row,
        // not the OP). Allowed only while the opener is the sole participant, so
        // it can never erase another member's replies; once others have joined,
        // refuse and point them at a moderator who can remove the topic.
        if ((int) $post['is_op'] === 1) {
            if ($this->posts->hasOtherParticipants((int) $post['thread_id'], (int) $post['user_id'])) {
                throw new ValidationException([
                    'post' => 'You can’t delete the opening post once others have replied. Ask a moderator to remove the topic.',
                ]);
            }
            $this->purgeThread((int) $post['thread_id'], (int) $post['board_id'], (int) $post['user_id']);
            return $post + ['topic_retracted' => true];
        }

        $deleted = $this->db->transaction(function () use ($post, $postId, $user): bool {
            // Only adjust counters if this call actually performed the delete
            // (guards against a concurrent/duplicate delete double-decrementing).
            if ($this->posts->softDelete($postId, $user->id()) > 0) {
                $this->applyDeletionCounters($post);
                $this->threadIntelligence?->markStale(
                    (int) $post['thread_id'],
                    ThreadIntelligenceQueue::TRIGGER_POST_DELETED,
                );
                return true;
            }
            return false;
        });

        if ($deleted) {
            $deletedPost = $this->posts->findWithContext($postId);
            if ($deletedPost !== null) {
                $this->emitPostDeleted($deletedPost);
            }
        }

        return $post;
    }

    /**
     * Soft-delete an entire topic: every live post (reversing the counters and
     * reputation each one contributed) plus the thread row, then drop it from
     * the board's thread tally. Reuses the per-post deletion accounting so
     * counters stay in lockstep with {@see RepairService}. Shared by the
     * opener's topic retraction ({@see deleteOwnPost}) and the moderator
     * remove-topic path ({@see \App\Service\ModerationService::deletePost});
     * each caller owns its own audit logging.
     */
    public function purgeThread(int $threadId, int $boardId, int $actorId): void
    {
        $rows = $this->db->fetchAll(
            'SELECT id, user_id, is_op, is_pending FROM posts WHERE thread_id = ? AND is_deleted = 0',
            [$threadId],
        );

        $this->db->transaction(function () use ($rows, $threadId, $boardId, $actorId): void {
            foreach ($rows as $row) {
                if ($this->posts->softDelete((int) $row['id'], $actorId) === 0) {
                    continue; // deleted concurrently — don't double-count
                }
                // Held (is_pending=1) posts never reached the denormalized counters,
                // so only reverse accounting for the posts that actually did.
                if ((int) $row['is_pending'] === 0) {
                    $this->applyDeletionCounters([
                        'id' => (int) $row['id'],
                        'user_id' => (int) $row['user_id'],
                        'is_op' => (int) $row['is_op'],
                        'thread_id' => $threadId,
                        'board_id' => $boardId,
                        'deleted_by' => $actorId,
                    ]);
                }
            }
            $this->threads->softDelete($threadId, $actorId);
            $this->boards->decrementThreadCount($boardId, 1);
        });
    }

    /**
     * Counter maintenance after a post is soft-deleted. Public so the
     * moderation path (admin delete-any) reuses the exact same accounting.
     *
     * @param array<string,mixed> $post
     */
    public function applyDeletionCounters(array $post): void
    {
        $this->boards->decrementPostCount((int) $post['board_id'], 1);
        $this->users->incrementPostCount((int) $post['user_id'], -1);
        if ((int) $post['is_op'] === 0) {
            $this->threads->incrementReplyCount((int) $post['thread_id'], -1);
        }
        // Reputation derives from reactions received; remove this post's
        // contribution (non-self reactions) so rep recomputes downward
        // (COMMUNITY §2.1, PHASE_2_PLAN §7.3).
        $received = $this->receivedReactionEvents((int) $post['id'], (int) $post['user_id']);
        if ($received !== []) {
            if ($this->reputation !== null) {
                $reversed = 0;
                foreach ($received as $reaction) {
                    if ($this->reputation->reverse(
                        $this->reactionLogicalKey((int) $post['id'], (int) $reaction['user_id'], (string) $reaction['emoji']),
                        (int) ($post['deleted_by'] ?? 0) ?: null,
                        'post_deleted',
                    )) {
                        $reversed++;
                    }
                }
                $missing = count($received) - $reversed;
                if ($missing > 0) {
                    $this->users->incrementReputation((int) $post['user_id'], -$missing);
                }
            } else {
                $this->users->incrementReputation((int) $post['user_id'], -count($received));
            }
        }
        // If this post was the thread's accepted answer, clear it and reverse the
        // solved bonus (author != OP). RepairService::reputationSolvedBonus and
        // solvedAnswerCount both already exclude deleted accepted answers, so the
        // runtime path must match or reputation drifts on the next repair.
        $thread = $this->threads->find((int) $post['thread_id']);
        if ($thread !== null && (int) ($thread['accepted_answer_post_id'] ?? 0) === (int) $post['id']) {
            $this->threads->setAcceptedAnswer((int) $post['thread_id'], null);
            if ((int) $post['user_id'] !== (int) $thread['user_id']) {
                if ($this->reputation !== null) {
                    $reversed = $this->reputation->reverse(
                        'accepted_answer:' . (int) $post['thread_id'],
                        (int) ($post['deleted_by'] ?? 0) ?: null,
                        'accepted_answer_deleted',
                    );
                    if (!$reversed) {
                        $this->users->incrementReputation((int) $post['user_id'], -(int) $this->config->get('community.solved_bonus', 5));
                    }
                } else {
                    $this->users->incrementReputation((int) $post['user_id'], -(int) $this->config->get('community.solved_bonus', 5));
                }
            }
        }
        $this->threads->recomputeLastPost((int) $post['thread_id']);
        $this->boards->recomputeLastPost((int) $post['board_id']);
    }

    /**
     * Inverse of applyDeletionCounters for a restored post (moderation restore,
     * P2-08). Re-adds counters and the post's reputation contribution.
     *
     * @param array<string,mixed> $post
     */
    public function applyRestorationCounters(array $post): void
    {
        $this->boards->incrementPostCount((int) $post['board_id'], 1);
        $this->users->incrementPostCount((int) $post['user_id'], 1);
        if ((int) $post['is_op'] === 0) {
            $this->threads->incrementReplyCount((int) $post['thread_id'], 1);
        }
        $received = $this->receivedReactionEvents((int) $post['id'], (int) $post['user_id']);
        if ($received !== []) {
            if ($this->reputation !== null) {
                foreach ($received as $reaction) {
                    $this->reputation->apply(
                        (int) $post['user_id'],
                        (int) $post['board_id'],
                        'reaction',
                        (int) $post['id'],
                        $this->reactionLogicalKey((int) $post['id'], (int) $reaction['user_id'], (string) $reaction['emoji']),
                        1,
                        (string) $reaction['created_at'],
                    );
                }
            } else {
                $this->users->incrementReputation((int) $post['user_id'], count($received));
            }
        }
        $this->threads->recomputeLastPost((int) $post['thread_id']);
        $this->boards->recomputeLastPost((int) $post['board_id']);
    }

    /**
     * Release a held thread (P3-05): clear is_pending on the thread + its OP, then
     * run the counter/notification work that was deferred at submit time. Returns
     * false if it is not a live pending thread (idempotent against double-approve).
     */
    public function approvePendingThread(int $threadId): bool
    {
        $approved = $this->db->transaction(function () use ($threadId): bool {
            $thread = $this->threads->find($threadId);
            if ($thread === null || (int) $thread['is_pending'] !== 1 || (int) $thread['is_deleted'] === 1) {
                return false;
            }
            $op = $this->db->fetch('SELECT * FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$threadId]);
            if ($op === null) {
                return false;
            }
            $board = $this->boards->find((int) $thread['board_id']);
            $now = gmdate('Y-m-d H:i:s');

            $this->threads->setPending($threadId, false);
            $this->posts->setPending((int) $op['id'], false);
            $this->linkPreviews?->queueFromBody('post', (int) $op['id'], (string) $op['body']);
            $this->threads->updateLastPost($threadId, (int) $op['id'], (int) $thread['user_id'], $now);
            $this->boards->onThreadCreated((int) $thread['board_id'], $threadId, $now);
            $this->users->incrementPostCount((int) $thread['user_id'], 1);

            if ($this->notifications !== null) {
                $threadCtx = ['id' => $threadId, 'board_id' => (int) $thread['board_id'], 'board_visibility' => $board['visibility'] ?? 'public'];
                $this->notifications->fanOutNewPost((int) $thread['user_id'], $threadCtx, (int) $op['id'], true, (string) $op['body']);
            }
            $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_POST_APPROVED);
            return true;
        });
        if ($approved) {
            $thread = $this->threads->findWithBoard($threadId);
            $op = $this->db->fetch('SELECT id, is_anonymous FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$threadId]);
            if ($thread !== null && $op !== null && (string) ($thread['board_visibility'] ?? 'public') === 'public') {
                $this->emitTopicCreated($threadId, (int) $op['id'], (int) $thread['board_id'], (int) $thread['user_id'], (int) ($op['is_anonymous'] ?? 0) === 1);
            }
        }
        return $approved;
    }

    /**
     * Release a held reply (P3-05): clear is_pending and run the deferred
     * reply-count, last-post, board activity, post_count, and fan-out work.
     */
    public function approvePendingPost(int $postId): bool
    {
        $approved = $this->db->transaction(function () use ($postId): bool {
            $post = $this->posts->findWithContext($postId);
            if ($post === null || (int) $post['is_pending'] !== 1 || (int) $post['is_deleted'] === 1) {
                return false;
            }
            $threadId = (int) $post['thread_id'];
            $now = gmdate('Y-m-d H:i:s');

            $this->posts->setPending($postId, false);
            $this->linkPreviews?->queueFromBody('post', $postId, (string) $post['body']);
            $this->threads->incrementReplyCount($threadId, 1);
            $this->threads->updateLastPost($threadId, $postId, (int) $post['user_id'], $now);
            $this->boards->onReplyCreated((int) $post['board_id'], $now);
            $this->users->incrementPostCount((int) $post['user_id'], 1);

            if ($this->notifications !== null) {
                $threadCtx = ['id' => $threadId, 'board_id' => (int) $post['board_id'], 'board_visibility' => $post['board_visibility'] ?? 'public'];
                $this->notifications->fanOutNewPost((int) $post['user_id'], $threadCtx, $postId, false, (string) $post['body']);
            }
            $this->threadIntelligence?->markStale($threadId, ThreadIntelligenceQueue::TRIGGER_POST_APPROVED);
            return true;
        });
        if ($approved) {
            $post = $this->posts->findWithContext($postId);
            if ($post !== null && (string) ($post['board_visibility'] ?? 'public') === 'public') {
                $this->emitReplyCreated($postId, (int) $post['thread_id'], (int) $post['board_id'], (int) $post['user_id'], (int) ($post['is_anonymous'] ?? 0) === 1);
            }
        }
        return $approved;
    }

    /** Reject a held thread: soft-delete it + its OP (no counters were applied). */
    public function rejectPendingThread(int $threadId, int $byUserId): bool
    {
        return $this->db->transaction(function () use ($threadId, $byUserId): bool {
            $thread = $this->threads->find($threadId);
            if ($thread === null || (int) $thread['is_pending'] !== 1 || (int) $thread['is_deleted'] === 1) {
                return false;
            }
            $this->threads->softDelete($threadId, $byUserId);
            $op = $this->db->fetch('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$threadId]);
            if ($op !== null) {
                $this->posts->softDelete((int) $op['id'], $byUserId);
            }
            return true;
        });
    }

    /** Reject a held reply: soft-delete it (no counters were applied). */
    public function rejectPendingPost(int $postId, int $byUserId): bool
    {
        return $this->db->transaction(function () use ($postId, $byUserId): bool {
            $post = $this->posts->find($postId);
            if ($post === null || (int) $post['is_pending'] !== 1 || (int) $post['is_deleted'] === 1) {
                return false;
            }
            $this->posts->softDelete($postId, $byUserId);
            return true;
        });
    }

    /** Private-board membership (board_members) — gates posting in private boards. */
    private function isBoardMember(int $boardId, int $userId): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM board_members WHERE board_id = ? AND user_id = ? LIMIT 1',
            [$boardId, $userId],
        ) !== false;
    }

    private function emitTopicCreated(int $threadId, int $postId, int $boardId, int $authorId, bool $isAnonymous = false): void
    {
        $this->hooks?->emit('topic.created', WebhookEvents::maskAnonymousAuthor([
            'thread_id' => $threadId,
            'post_id' => $postId,
            'board_id' => $boardId,
            'author_id' => $authorId,
        ], $isAnonymous, ['author_id']), 'thread:' . $threadId . ':created');
    }

    private function emitReplyCreated(int $postId, int $threadId, int $boardId, int $authorId, bool $isAnonymous = false): void
    {
        $this->hooks?->emit('reply.created', WebhookEvents::maskAnonymousAuthor([
            'post_id' => $postId,
            'thread_id' => $threadId,
            'board_id' => $boardId,
            'author_id' => $authorId,
        ], $isAnonymous, ['author_id']), 'post:' . $postId . ':created');
    }

    /** @param array<string,mixed> $post */
    private function emitPostEdited(array $post): void
    {
        if (!$this->isPublicBoardPost($post) || (int) ($post['is_deleted'] ?? 0) === 1) {
            return;
        }
        $editedAt = (string) ($post['edited_at'] ?? '');
        if ($editedAt === '') {
            return;
        }
        $hash = substr(hash('sha256', (string) ($post['body'] ?? '')), 0, 12);
        $postId = (int) $post['id'];
        $this->hooks?->emit('post.edited', WebhookEvents::maskAnonymousAuthor([
            'post_id' => $postId,
            'thread_id' => (int) $post['thread_id'],
            'board_id' => (int) $post['board_id'],
            'author_id' => (int) $post['user_id'],
            'edited_by_id' => (int) ($post['edited_by'] ?? 0),
            'is_op' => (int) ($post['is_op'] ?? 0) === 1,
        ], (int) ($post['is_anonymous'] ?? 0) === 1, ['author_id'], ['edited_by_id']), 'post:' . $postId . ':edited:' . $editedAt . ':' . $hash);
    }

    /** @param array<string,mixed> $post */
    private function emitPostDeleted(array $post): void
    {
        if (!$this->isPublicBoardPost($post)) {
            return;
        }
        $deletedAt = (string) ($post['deleted_at'] ?? '');
        if ($deletedAt === '') {
            return;
        }
        $postId = (int) $post['id'];
        $this->hooks?->emit('post.deleted', WebhookEvents::maskAnonymousAuthor([
            'post_id' => $postId,
            'thread_id' => (int) $post['thread_id'],
            'board_id' => (int) $post['board_id'],
            'author_id' => (int) $post['user_id'],
            'deleted_by_id' => (int) ($post['deleted_by'] ?? 0),
            'is_op' => (int) ($post['is_op'] ?? 0) === 1,
        ], (int) ($post['is_anonymous'] ?? 0) === 1, ['author_id'], ['deleted_by_id']), 'post:' . $postId . ':deleted:' . $deletedAt);
    }

    /** @param array<string,mixed> $post */
    private function isPublicBoardPost(array $post): bool
    {
        return (string) ($post['board_visibility'] ?? 'public') === 'public'
            && (int) ($post['is_pending'] ?? 0) === 0;
    }

    /** @return array<int,array{user_id:int,emoji:string,created_at:string}> reactions contributing reputation */
    private function receivedReactionEvents(int $postId, int $authorId): array
    {
        return $this->db->fetchAll(
            'SELECT user_id, emoji, created_at FROM reactions WHERE post_id = ? AND user_id <> ?',
            [$postId, $authorId],
        );
    }

    private function reactionLogicalKey(int $postId, int $reactorId, string $emoji): string
    {
        return 'reaction:' . $postId . ':' . $reactorId . ':' . sha1($emoji);
    }

    /**
     * @param array<string,mixed> $input
     * @throws ValidationException
     */
    private function validate(?string $title, string $body, array $input, bool $requireTitle): void
    {
        $errors = [];

        if ($requireTitle) {
            $title ??= '';
            if ($title === '') {
                $errors['title'] = 'Enter a title.';
            } elseif (mb_strlen($title) > (int) $this->config->get('limits.thread_title_max', 160)) {
                $errors['title'] = 'Title is too long (max 160).';
            }
        }

        $trimmed = trim($body);
        if ($trimmed === '') {
            $errors['body'] = 'Write something before posting.';
        } elseif (mb_strlen($body) > (int) $this->config->get('limits.post_body_max', 20000)) {
            $errors['body'] = 'Your post is too long.';
        }

        // Enforce the per-post image ceiling (uploads.per_post_max) before any
        // write, so an over-cap post is rejected and never partially created.
        $maxImages = (int) $this->config->get('uploads.per_post_max', 10);
        if ($maxImages > 0 && count(\App\Service\AttachmentService::referencedIds($body)) > $maxImages) {
            $errors['body'] = "You can attach at most {$maxImages} images to a post.";
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }
    }
}
