<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;

/**
 * Block list management (USER §4.7). Blocking is a protective control, so it is
 * permitted regardless of account state (like logging out). Blocking also tears
 * down any follow edges in either direction so a blocked pair no longer surfaces
 * in each other's feed or follower lists.
 */
final class BlockController extends Controller
{
    /** The block-list settings page. */
    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->view('account/blocks', [
            'blocked' => $this->container->get(BlockRepository::class)->listBlocked($user->id()),
        ]);
    }

    /** Toggle a block on the member {username}. @param array<string,string> $params */
    public function toggle(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $target = $this->container->get(UserRepository::class)->findByUsername((string) ($params['username'] ?? ''));
        if ($target === null) {
            throw new NotFoundException('That member could not be found.');
        }
        $targetId = (int) $target['id'];
        if ($targetId === $user->id()) {
            return $this->redirectWithFlash('/u/' . (string) $target['username'], 'You cannot block yourself.');
        }

        $blocks = $this->container->get(BlockRepository::class);
        $follows = $this->container->get(FollowRepository::class);

        // A form that names its intent is applied idempotently, so a double
        // submit or a stale tab's "Block" can never undo a block. A form
        // without one keeps the toggle.
        $blocking = $blocks->blocks($user->id(), $targetId);
        $intent = $request->post('intent');
        $block = $intent === 'block' || ($intent !== 'unblock' && !$blocking);

        if (!$block) {
            if ($blocking) {
                $blocks->unblock($user->id(), $targetId);
            }
            $message = 'Unblocked.';
        } else {
            if (!$blocking) {
                $blocks->block($user->id(), $targetId);
                // Sever any follow relationship both ways.
                $follows->unfollow($user->id(), $targetId);
                $follows->unfollow($targetId, $user->id());
            }
            $message = 'Blocked. They can no longer message or @mention you.';
        }

        $return = $this->localReturn($request, '/u/' . (string) $target['username']);
        return $this->redirectWithFlash($return, $message);
    }
}
