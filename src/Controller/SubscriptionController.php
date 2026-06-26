<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;

/**
 * Thread/board subscription controls (P2-03). A thread subscription overrides
 * the board one for that thread. Channels (in-app/email) and frequency
 * (instant/daily/off) are set per subscription; 'off' silences fan-out.
 */
final class SubscriptionController extends Controller
{
    private const FREQ = ['instant', 'daily', 'off'];

    /** @param array<string,string> $params */
    public function subscribeThread(Request $request, array $params): Response
    {
        $user = $this->requireNotifications();
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

        $this->apply($user->id(), 'thread', $threadId, $request);
        return $this->redirectWithFlash('/t/' . $threadId . '-' . $thread['slug'], 'Subscription updated.');
    }

    /** @param array<string,string> $params */
    public function subscribeBoard(Request $request, array $params): Response
    {
        $user = $this->requireNotifications();
        $boardId = (int) ($params['id'] ?? 0);

        $board = $this->container->get(BoardRepository::class)->find($boardId);
        $isMember = $board !== null && $this->container->get(\App\Repository\BoardMemberRepository::class)
            ->isMember((int) $board['id'], $user->id());
        if ($board === null || !$this->container->get(BoardPolicy::class)->canRead($board, $user, $isMember)) {
            throw new NotFoundException('Board not found.');
        }

        $this->apply($user->id(), 'board', $boardId, $request);
        return $this->redirectWithFlash('/c/' . $board['slug'], 'Subscription updated.');
    }

    private function apply(int $userId, string $type, int $targetId, Request $request): void
    {
        $frequency = (string) $request->post('frequency', 'instant');
        if (!in_array($frequency, self::FREQ, true)) {
            $frequency = 'instant';
        }
        $subs = $this->container->get(SubscriptionRepository::class);
        if ($frequency === 'off') {
            // An explicit Off row suppresses an inherited board subscription too.
            $subs->set($userId, $type, $targetId, false, false, 'off');
            return;
        }
        $inApp = $request->post('in_app') !== null;
        $email = $request->post('email') !== null;
        if (!$inApp && !$email) {
            $inApp = true; // a live subscription needs at least one channel
        }
        $subs->set($userId, $type, $targetId, $inApp, $email, $frequency);
    }

    private function requireNotifications(): \App\Domain\User
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('notifications')) {
            throw new NotFoundException('Not found.');
        }
        return $this->requireUser();
    }
}
