<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Service\FollowService;

/**
 * Follow / unfollow a member (COMMUNITY §4). One CSRF-protected POST that toggles
 * the follow edge, with a JSON path for the enhanced button and a Post/Redirect/Get
 * back to the profile for the no-JavaScript path.
 */
final class FollowController extends Controller
{
    /** @param array<string,string> $params */
    public function toggle(Request $request, array $params): Response
    {
        $this->requireCommunity();
        $user = $this->requireUser();

        $target = $this->container->get(UserRepository::class)->findByUsername((string) ($params['username'] ?? ''));
        if ($target === null) {
            throw new NotFoundException('That member could not be found.');
        }
        $targetId = (int) $target['id'];
        $profileUrl = '/u/' . (string) $target['username'];

        try {
            $following = $this->container->get(FollowService::class)->toggle($user, $targetId);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => $e->first()], 422);
            }
            return $this->redirectWithFlash($profileUrl, $e->first());
        }

        if ($request->wantsJson()) {
            return Response::json([
                'ok' => true,
                'following' => $following,
                'followers' => $this->container->get(FollowRepository::class)->followerCount($targetId),
            ]);
        }
        return $this->redirectWithFlash($profileUrl, $following ? 'You are now following this member.' : 'You unfollowed this member.');
    }

    /** @param array<string,string> $params */
    public function toggleBoard(Request $request, array $params): Response
    {
        $this->requireCommunity();
        if (!$this->container->get(FeatureFlags::class)->enabled('expanded_feeds')) {
            throw new NotFoundException('Board not found.');
        }
        $user = $this->requireUser();
        $boardId = (int) ($params['id'] ?? 0);
        $board = $this->container->get(BoardRepository::class)->find($boardId);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $isMember = $this->container->get(BoardMemberRepository::class)->isMember($boardId, $user->id());
        if (!$this->container->get(BoardPolicy::class)->canRead($board, $user, $isMember)) {
            throw new NotFoundException('Board not found.');
        }
        $following = $this->container->get(FollowService::class)->toggleTarget($user, 'board', $boardId);
        return $this->redirectWithFlash('/c/' . (string) $board['slug'], $following ? 'Board followed.' : 'Board unfollowed.');
    }

    /** @param array<string,string> $params */
    public function removeFollower(Request $request, array $params): Response
    {
        $this->requireCommunity();
        $user = $this->requireUser();

        $profile = $this->container->get(UserRepository::class)->findByUsername((string) ($params['username'] ?? ''));
        if ($profile === null) {
            throw new NotFoundException('That member could not be found.');
        }
        if ((int) $profile['id'] !== $user->id()) {
            throw new ForbiddenException('You can only remove followers from your own profile.');
        }

        try {
            $this->container->get(FollowService::class)->removeFollower($user, (int) ($params['id'] ?? 0));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/u/' . (string) $profile['username'] . '/followers', $e->first());
        }
        return $this->redirectWithFlash('/u/' . (string) $profile['username'] . '/followers', 'Follower removed.');
    }

    private function requireCommunity(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('community')) {
            throw new NotFoundException('Not found.');
        }
    }
}
