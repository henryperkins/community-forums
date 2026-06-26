<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\ModerationService;

/**
 * Inline admin moderation actions invoked from the thread view: pin/unpin and
 * lock/unlock. Each writes a moderation_log row. Admin-only (enforced in the
 * service and via requireAdmin); soft-deleting any post lives in
 * PostController::delete.
 */
final class ModerationController extends Controller
{
    /** @param array<string,string> $params */
    public function pin(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $threadId = (int) ($params['id'] ?? 0);
        $result = $this->container->get(ModerationService::class)->togglePin($admin, $threadId);
        return $this->redirectWithFlash(
            $this->threadUrl($result['thread']),
            $result['pinned'] ? 'Thread pinned.' : 'Thread unpinned.',
        );
    }

    /** @param array<string,string> $params */
    public function lock(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $threadId = (int) ($params['id'] ?? 0);
        $result = $this->container->get(ModerationService::class)->toggleLock($admin, $threadId);
        return $this->redirectWithFlash(
            $this->threadUrl($result['thread']),
            $result['locked'] ? 'Thread locked.' : 'Thread unlocked.',
        );
    }

    /** @param array<string,mixed> $thread */
    private function threadUrl(array $thread): string
    {
        return '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
    }
}
