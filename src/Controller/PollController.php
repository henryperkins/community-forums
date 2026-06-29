<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Service\PollService;

final class PollController extends Controller
{
    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $this->requirePolls();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PollService::class)->create($user, $threadId, $request->allInput());
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($this->threadUrl($threadId), $e->first());
        }
        return $this->redirectWithFlash($this->threadUrl($threadId), 'Poll created.');
    }

    /** @param array<string,string> $params */
    public function vote(Request $request, array $params): Response
    {
        $this->requirePolls();
        $user = $this->requireUser();
        $pollId = (int) ($params['id'] ?? 0);
        $poll = $this->container->get(PollService::class)->pollOrFail($pollId);
        $raw = $request->post('option_ids', []);
        $optionIds = is_array($raw) ? array_map('intval', $raw) : [(int) $raw];
        try {
            $this->container->get(PollService::class)->vote($user, $pollId, $optionIds);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($this->threadUrl((int) $poll['thread_id']), $e->first());
        }
        return $this->redirectWithFlash($this->threadUrl((int) $poll['thread_id']), 'Vote recorded.');
    }

    /** @param array<string,string> $params */
    public function close(Request $request, array $params): Response
    {
        $this->requirePolls();
        $user = $this->requireUser();
        $pollId = (int) ($params['id'] ?? 0);
        $poll = $this->container->get(PollService::class)->pollOrFail($pollId);
        $this->container->get(PollService::class)->close($user, $pollId);
        return $this->redirectWithFlash($this->threadUrl((int) $poll['thread_id']), 'Poll closed.');
    }

    private function requirePolls(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('polls')) {
            throw new NotFoundException('Not found.');
        }
    }

    private function threadUrl(int $threadId): string
    {
        $thread = $this->container->get(\App\Repository\ThreadRepository::class)->find($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        return '/t/' . $threadId . '-' . (string) $thread['slug'];
    }
}
