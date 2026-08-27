<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\BadgeService;
use App\Service\ReactionService;

/**
 * Personal engagement actions: react to a post, star a thread (P2-01/P2-02),
 * and set read state by hand — one topic, or a whole board.
 *
 * Every action is a CSRF-protected POST with two response paths — a JSON
 * fragment for the enhanced UI and a Post/Redirect/Get back to the thread for
 * the no-JavaScript path (PHASE_2_PLAN §8 "No-JS operation").
 */
final class EngagementController extends Controller
{
    /** @param array<string,string> $params */
    public function react(Request $request, array $params): Response
    {
        $this->requireEngagement();
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $emoji = (string) $request->post('emoji', '');

        try {
            $result = $this->container->get(ReactionService::class)->toggle($user, $postId, $emoji);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => $e->first()], 422);
            }
            throw new NotFoundException('That reaction is not available.');
        }

        $post = $result['post'];

        // A received reaction may cross a reputation badge milestone for the
        // post author (Appreciated / Well-Liked). Idempotent + cheap when not due.
        if ($result['state'] === 'added' && (int) $post['user_id'] !== $user->id()
            && $this->container->get(FeatureFlags::class)->enabled('community')) {
            $this->container->get(BadgeService::class)->evaluateForUser((int) $post['user_id']);
        }

        if ($request->wantsJson()) {
            return Response::json([
                'ok' => true,
                'state' => $result['state'],
                'emoji' => $emoji,
                'counts' => $result['counts'],
            ]);
        }

        return $this->redirect($this->postLocation((int) $post['thread_id'], (string) $post['thread_slug'], $postId));
    }

    /** @param array<string,string> $params */
    public function star(Request $request, array $params): Response
    {
        $this->requireEngagement();
        $user = $this->requireUser();
        // Stars are persistent state, so suspended/banned accounts are blocked
        // here too, matching the reaction path and the central write gate.
        $this->container->get(WriteGate::class)->assertCanWrite($user);
        $threadId = (int) ($params['id'] ?? 0);

        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $isMember = $this->container->get(\App\Repository\BoardMemberRepository::class)
            ->isMember((int) $thread['board_id'], $user->id());
        if (!$this->container->get(BoardPolicy::class)->canRead(['visibility' => $thread['board_visibility']], $user, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }

        $starred = $this->container->get(ThreadUserRepository::class)->toggleStar($user->id(), $threadId);

        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'starred' => $starred]);
        }

        $return = $this->safeReturn($request, '/t/' . $threadId . '-' . $thread['slug']);
        return $this->redirectWithFlash($return, $starred ? 'Thread starred.' : 'Star removed.');
    }

    /**
     * Set the viewer's read state on one topic by hand — the board row's
     * gutter marker. `state` is explicit rather than a toggle so a no-JS
     * double-submit, or a back-then-resubmit, lands on the state the member
     * actually clicked instead of flipping past it.
     *
     * Deliberately NOT behind WriteGate, unlike star(): opening a topic already
     * advances this same watermark for a suspended member, and suspension means
     * "read but not write" — refusing the manual marker while the automatic one
     * still fires would contradict itself.
     *
     * @param array<string,string> $params
     */
    public function readState(Request $request, array $params): Response
    {
        $this->requireEngagement();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $unread = (string) $request->post('state', 'read') === 'unread';

        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $isMember = $this->container->get(\App\Repository\BoardMemberRepository::class)
            ->isMember((int) $thread['board_id'], $user->id());
        if (!$this->container->get(BoardPolicy::class)->canRead(['visibility' => $thread['board_visibility']], $user, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }

        $threadUsers = $this->container->get(ThreadUserRepository::class);
        if ($unread) {
            $threadUsers->markUnread($user->id(), $threadId);
        } else {
            // A null last_post_id means the topic has no posts to be read past.
            $lastPostId = (int) ($thread['last_post_id'] ?? 0);
            if ($lastPostId > 0) {
                $threadUsers->markRead($user->id(), $threadId, $lastPostId);
            }
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'unread' => $unread]);
        }

        $return = $this->safeReturn($request, '/t/' . $threadId . '-' . $thread['slug']);
        return $this->redirectWithFlash($return, $unread ? 'Marked unread.' : 'Marked read.');
    }

    /**
     * Clear every unread topic on one board — the topics header's "Mark all
     * read". Read-gated like the board page itself, so it can never confirm the
     * existence of a board the member cannot see.
     *
     * @param array<string,string> $params
     */
    public function markBoardRead(Request $request, array $params): Response
    {
        $this->requireEngagement();
        $user = $this->requireUser();

        $board = $this->container->get(BoardRepository::class)
            ->findBySlug((string) ($params['slug'] ?? ''));
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $isMember = $this->container->get(\App\Repository\BoardMemberRepository::class)
            ->isMember((int) $board['id'], $user->id());
        if (!$this->container->get(BoardPolicy::class)->canRead($board, $user, $isMember)) {
            throw new NotFoundException('Board not found.');
        }

        // One statement, but it touches every topic on the board — the project's
        // rule is that a multi-row mutation runs inside a transaction.
        $this->container->get(Database::class)->transaction(
            fn () => $this->container->get(ThreadUserRepository::class)
                ->markBoardRead($user->id(), (int) $board['id']),
        );

        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }

        $return = $this->safeReturn($request, '/c/' . $board['slug']);
        return $this->redirectWithFlash($return, 'Board marked read.');
    }

    /**
     * Validate a caller-supplied return path so it can only be a local redirect.
     * Must be a single leading slash NOT followed by '/' or '\' — browsers
     * normalise "/\evil.com" to the protocol-relative "//evil.com", so a bare
     * !str_starts_with('//') check is bypassable.
     */
    private function safeReturn(Request $request, string $default): string
    {
        $return = (string) $request->post('return', '');
        if ($return !== '' && preg_match('#^/(?![/\\\\])#', $return) === 1) {
            return $return;
        }
        return $default;
    }

    private function requireEngagement(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
    }
}
