<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Service\CommunityMemoryService;

final class CommunityMemoryController extends Controller
{
    /** @param array<string,string> $params */
    public function refreshSummary(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $result = $this->container->get(CommunityMemoryService::class)->requestRefresh($user, $threadId);
        return $this->redirectWithFlash($this->threadUrl($threadId), $result->message);
    }

    /** @param array<string,string> $params */
    public function resumeAutomation(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->resumeAutomation($user, $threadId),
            $this->threadUrl($threadId),
            'Automatic refresh resumed.',
        );
    }

    /** @param array<string,string> $params */
    public function summary(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->publishSummary(
                $user,
                $threadId,
                (string) $request->post('body', ''),
                $this->idList((string) $request->post('source_post_ids', '')),
            ),
            $this->threadUrl($threadId),
            'Summary published.',
        );
    }

    /** @param array<string,string> $params */
    public function related(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->addRelated(
                $user,
                $threadId,
                (int) $request->post('related_thread_id', 0),
                (string) $request->post('reason', ''),
            ),
            $this->threadUrl($threadId),
            'Related topic added.',
        );
    }

    /** @param array<string,string> $params */
    public function retireSummary(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->retireSummary($user, $threadId),
            $this->threadUrl($threadId),
            'Summary retired.',
        );
    }

    /** @param array<string,string> $params */
    public function republishSummary(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->republishSummary(
                $user,
                (int) $request->post('summary_id', 0),
                $threadId,
            ),
            $this->threadUrl($threadId),
            'Summary restored.',
        );
    }

    /** @param array<string,string> $params */
    public function makeWiki(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->postOrFail($postId);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->makeWiki($user, $postId),
            $this->threadUrl((int) $post['thread_id']),
            'Wiki editing enabled for that post.',
        );
    }

    /** @param array<string,string> $params */
    public function editWiki(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->postOrFail($postId);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->editWiki(
                $user,
                $postId,
                (string) $request->post('body', ''),
                (string) $request->post('reason', ''),
                $request->post('idempotency_key'),
            ),
            $this->threadUrl((int) $post['thread_id']) . '#p' . $postId,
            'Wiki post updated.',
        );
    }

    /** @param array<string,string> $params */
    public function revertWiki(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $post = $this->postOrFail($postId);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->revertWiki(
                $user,
                $postId,
                (int) $request->post('revision_id', 0),
            ),
            $this->threadUrl((int) $post['thread_id']) . '#p' . $postId,
            'Wiki revision restored.',
        );
    }

    private function requireMemory(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('community_memory')) {
            throw new NotFoundException('Not found.');
        }
    }

    private function run(callable $action, string $redirect, string $success): Response
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($redirect, $e->first());
        }
        return $this->redirectWithFlash($redirect, $success);
    }

    private function threadUrl(int $threadId): string
    {
        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        return '/t/' . $threadId . '-' . (string) $thread['slug'];
    }

    /** @return array<string,mixed> */
    private function postOrFail(int $postId): array
    {
        $post = $this->container->get(PostRepository::class)->find($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }
        return $post;
    }

    /** @return list<int> */
    private function idList(string $raw): array
    {
        return array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $raw) ?: []), fn (int $id): bool => $id > 0));
    }
}
