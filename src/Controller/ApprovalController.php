<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Security\AuthorityGate;
use App\Security\Cap;
use App\Service\ModerationService;
use App\Service\PostingService;

/**
 * Approval queue for content held by anti-abuse rules or board approval (P3-05).
 * Board-scoped like the reports queue: an admin sees everything, a scoped
 * moderator sees only their boards. Releasing runs the deferred counters/fan-out;
 * rejecting soft-deletes. Every decision is audited with the acting moderator.
 */
final class ApprovalController extends Controller
{
    public function queue(Request $request): Response
    {
        $this->requireModerationQueue();
        $user = $this->requireUser();
        $gate = $this->container->get(AuthorityGate::class);
        // Row scope: the boards whose pending rows this actor may act on
        // (core.content.approve — the queue's action key), discovered through
        // the gate per board. Legacy/shadow reproduce admin-or-assigned
        // exactly; under enforce a custom deputy's grants surface here.
        $scope = $this->container->get(ModerationService::class)->moderableBoardIds($user, Cap::CONTENT_APPROVE);
        $sitePass = $gate->allows(
            fn (): bool => $user->isModerator(),
            $user,
            Cap::CONTENT_VIEW_PENDING,
            [], // site probe: no board target — board-scoped grants correctly do not qualify
            'ApprovalController::index',
        );
        // Door: the site probe (kept for the role-moderator personas it admits,
        // including the shadow-mode suspended-staff quirk pinned in
        // AppEnforcementCutoverTest), OR — in every mode — an actor whose scope
        // surfaced at least one board's rows. The scope arm is what lets an
        // assigned board moderator through in legacy/shadow, matching the
        // reports queue this page sits beside (ADMIN §3.2; 2026-07-17 audit N1).
        if (!$sitePass && $scope === []) {
            // Zero-authority BROWSE of a staff surface hides its existence —
            // byte-identical to /mod/reports' empty-scope 404 (ADMIN §9.4,
            // round-2 audit posture rule, ADR 0023). Actions below stay 403.
            throw new NotFoundException('Not found.');
        }

        return $this->view('mod/approvals', [
            'pending_threads' => $this->container->get(ThreadRepository::class)->listPending($scope, 100),
            'pending_posts' => $this->container->get(PostRepository::class)->listPending($scope, 100),
        ]);
    }

    /** @param array<string,string> $params */
    public function approveThread(Request $request, array $params): Response
    {
        return $this->actThread($params, approve: true);
    }

    /** @param array<string,string> $params */
    public function rejectThread(Request $request, array $params): Response
    {
        return $this->actThread($params, approve: false);
    }

    /** @param array<string,string> $params */
    public function approvePost(Request $request, array $params): Response
    {
        return $this->actPost($params, approve: true);
    }

    /** @param array<string,string> $params */
    public function rejectPost(Request $request, array $params): Response
    {
        return $this->actPost($params, approve: false);
    }

    /** @param array<string,string> $params */
    private function actThread(array $params, bool $approve): Response
    {
        $this->requireModerationQueue();
        $mod = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        $this->assertCanModerate($mod, (int) $thread['board_id']);

        $posting = $this->container->get(PostingService::class);
        $ok = $approve ? $posting->approvePendingThread($threadId) : $posting->rejectPendingThread($threadId, $mod->id());
        if ($ok) {
            $this->audit($mod->id(), $approve ? 'approve' : 'reject', 'thread', $threadId);
        }
        $message = $ok
            ? ($approve ? 'Topic approved and published.' : 'Topic rejected.')
            : 'Topic was already handled.';
        return $this->redirectWithFlash('/mod/approvals', $message);
    }

    /** @param array<string,string> $params */
    private function actPost(array $params, bool $approve): Response
    {
        $this->requireModerationQueue();
        $mod = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }
        $this->assertCanModerate($mod, (int) $post['board_id']);

        $posting = $this->container->get(PostingService::class);
        $ok = $approve ? $posting->approvePendingPost($postId) : $posting->rejectPendingPost($postId, $mod->id());
        if ($ok) {
            $this->audit($mod->id(), $approve ? 'approve' : 'reject', 'post', $postId);
        }
        $message = $ok
            ? ($approve ? 'Reply approved and published.' : 'Reply rejected.')
            : 'Reply was already handled.';
        return $this->redirectWithFlash('/mod/approvals', $message);
    }

    private function assertCanModerate(\App\Domain\User $mod, int $boardId): void
    {
        if (!$this->container->get(ModerationService::class)->canModerate($mod, $boardId, Cap::CONTENT_APPROVE)) {
            throw new ForbiddenException('You do not moderate that board.');
        }
    }

    private function audit(int $actorId, string $action, string $targetType, int $targetId): void
    {
        $this->container->get(ModerationLogRepository::class)->log([
            'actor_id' => $actorId,
            'action' => $action . '_pending',
            'target_type' => $targetType,
            'target_id' => $targetId,
        ]);
    }
}
