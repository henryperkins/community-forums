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
use App\Service\ThreadReadService;
use App\Service\ThreadWorkflowService;
use App\Support\InboxSnooze;

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
        $thread = $this->container->get(ThreadReadService::class)->loadForUser($user, $threadId);
        $return = $this->localReturn($request, '/t/' . $threadId . '-' . (string) $thread['slug']);

        try {
            $intent = $request->post('until');
            if (!is_string($intent)) {
                throw new ValidationException(['until' => 'Choose an available Inbox action.']);
            }
            $state = InboxSnooze::state($intent);
            $this->container->get(ThreadUserRepository::class)->setSnooze(
                $user->id(),
                $threadId,
                $state['until'],
                $state['indefinite'],
            );
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($return, $e->first());
        }

        $message = match ($intent) {
            'manual' => 'Topic hidden from Inbox until you restore it.',
            'tomorrow' => 'Topic snoozed til tomorrow.',
            default => 'Topic restored to Inbox.',
        };
        return $this->redirectWithFlash($return, $message);
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

        return $this->redirectWithFlash($this->localReturn($request, $url), 'Assignment updated.');
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
