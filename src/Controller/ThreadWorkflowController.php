<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\ThreadWorkflowService;

final class ThreadWorkflowController extends Controller
{
    /** @param array<string,string> $params */
    public function status(Request $request, array $params): Response
    {
        $this->requireWorkflow();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $url = $this->threadUrl($threadId);

        try {
            $this->container->get(ThreadWorkflowService::class)->setStatus(
                $user,
                $threadId,
                (string) $request->post('status', ''),
                (string) $request->post('reason', ''),
            );
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($url, $e->first());
        }

        return $this->redirectWithFlash($url, 'Topic status updated.');
    }

    /** @param array<string,string> $params */
    public function snooze(Request $request, array $params): Response
    {
        $this->requireWorkflow();
        $user = $this->requireUser();
        // State beats role: suspended/banned/deactivated accounts cannot write,
        // even a personal snooze. Status/assign gate this via ThreadWorkflowService;
        // snooze writes the per-user row directly, so it must gate here too.
        $this->container->get(WriteGate::class)->assertCanWrite($user);
        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->readableThread($threadId);

        $until = $this->parseSnooze((string) $request->post('until', ''));
        $this->container->get(ThreadUserRepository::class)->setSnooze($user->id(), $threadId, $until);

        $message = $until === null ? 'Snooze cleared.' : 'Topic snoozed.';
        return $this->redirectWithFlash('/t/' . $threadId . '-' . (string) $thread['slug'], $message);
    }

    /** @param array<string,string> $params */
    public function assign(Request $request, array $params): Response
    {
        $this->requireWorkflow();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $url = $this->threadUrl($threadId);
        $service = $this->container->get(ThreadWorkflowService::class);

        try {
            if ((string) $request->post('action', '') === 'unassign') {
                $service->unassign($user, $threadId, (string) $request->post('reason', ''));
            } else {
                $assigneeId = $this->resolveAssignee($request);
                $service->assign($user, $threadId, $assigneeId, (string) $request->post('reason', ''));
            }
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($url, $e->first());
        }

        return $this->redirectWithFlash($url, 'Assignment updated.');
    }

    private function requireWorkflow(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('topic_workflow')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @return array<string,mixed> */
    private function readableThread(int $threadId): array
    {
        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($threadId);
        if ($thread === null || (int) $thread['is_deleted'] === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $user = $this->currentUser();
        $isMember = $user !== null
            && $this->container->get(BoardMemberRepository::class)->isMember((int) $thread['board_id'], $user->id());
        if (!$this->container->get(BoardPolicy::class)->canRead(['visibility' => $thread['board_visibility']], $user, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    private function threadUrl(int $threadId): string
    {
        $thread = $this->readableThread($threadId);
        return '/t/' . $threadId . '-' . (string) $thread['slug'];
    }

    private function parseSnooze(string $value): ?string
    {
        return match ($value) {
            'later_today' => gmdate('Y-m-d H:i:s', time() + 4 * 3600),
            'tomorrow' => gmdate('Y-m-d H:i:s', time() + 24 * 3600),
            'week' => gmdate('Y-m-d H:i:s', time() + 7 * 24 * 3600),
            default => null,
        };
    }

    private function resolveAssignee(Request $request): int
    {
        $rawId = (int) $request->post('assignee_id', 0);
        if ($rawId > 0) {
            return $rawId;
        }
        $username = ltrim(trim((string) $request->post('assignee', '')), '@');
        if ($username === '') {
            $user = $this->currentUser();
            if ($user !== null && (string) $request->post('self', '') === '1') {
                return $user->id();
            }
            throw new ValidationException(['assignee' => 'Enter a member to assign.']);
        }
        $row = $this->container->get(UserRepository::class)->findByUsername($username);
        if ($row === null) {
            throw new ValidationException(['assignee' => 'No member found with that username.']);
        }
        return (int) $row['id'];
    }
}
