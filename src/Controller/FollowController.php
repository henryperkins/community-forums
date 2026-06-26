<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;
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

    private function requireCommunity(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('community')) {
            throw new NotFoundException('Not found.');
        }
    }
}
