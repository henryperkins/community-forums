<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\ReactionService;

/**
 * Personal engagement actions: react to a post, star a thread (P2-01/P2-02).
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
        $threadUrl = '/t/' . (int) $post['thread_id'] . '-' . $post['thread_slug'];

        if ($request->wantsJson()) {
            return Response::json([
                'ok' => true,
                'state' => $result['state'],
                'emoji' => $emoji,
                'counts' => $result['counts'],
            ]);
        }

        return $this->redirect($threadUrl . '#p' . $postId);
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
        if (!$this->container->get(BoardPolicy::class)->canRead(['visibility' => $thread['board_visibility']], $user)) {
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
