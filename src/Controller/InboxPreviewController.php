<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\BoardMemberRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadUserRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WriteGate;
use App\Service\ThreadReadService;

final class InboxPreviewController extends Controller
{
    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('engagement')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->container->get(ThreadReadService::class)->loadForUser($user, $threadId);
        if ((int) ($thread['is_pending'] ?? 0) === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $posts = $this->container->get(PostRepository::class)->listByThread($threadId, 4, 0);
        $totalPosts = $this->container->get(PostRepository::class)->countByThread($threadId);

        $isMember = $this->container->get(BoardMemberRepository::class)
            ->isMember((int) $thread['board_id'], $user->id());
        $policy = $this->container->get(BoardPolicy::class);
        $canReply = $this->container->get(WriteGate::class)->canWrite($user)
            && $this->container->get(AuthorityGate::class)->allows(
                fn (): bool => $policy->canPost([
                    'visibility' => $thread['board_visibility'],
                    'post_min_role' => $thread['board_post_min_role'] ?? 'user',
                    'is_archived' => $thread['board_is_archived'] ?? 0,
                ], $user, $isMember),
                $user,
                Cap::POST_CREATE,
                ['board_id' => (int) $thread['board_id']],
                'InboxPreviewController::show',
            )
            && (int) $thread['is_locked'] === 0;

        $html = $this->container->get(View::class)->partial('partials/inbox_preview', [
            'thread' => $thread,
            'posts' => $posts,
            'total_posts' => $totalPosts,
            'can_reply' => $canReply,
        ]);
        $lastPostId = (int) ($thread['last_post_id'] ?? 0);
        if ($lastPostId > 0) {
            $this->container->get(ThreadUserRepository::class)->markRead($user->id(), $threadId, $lastPostId);
        }
        return Response::html($html);
    }
}
