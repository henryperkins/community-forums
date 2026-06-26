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
use App\Service\SolvedAnswerService;

/**
 * Accept / un-accept a thread's answer (COMMUNITY §11). Authorized to the topic
 * author and board moderators (enforced in SolvedAnswerService). PRG back to the
 * thread for the no-JavaScript path.
 */
final class SolvedController extends Controller
{
    /** Accept the post {id} as its thread's answer. @param array<string,string> $params */
    public function accept(Request $request, array $params): Response
    {
        $this->requireCommunity();
        $user = $this->requireUser();

        $postId = (int) ($params['id'] ?? 0);
        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('That answer could not be found.');
        }
        $threadId = (int) $post['thread_id'];
        $url = '/t/' . $threadId . '-' . (string) $post['thread_slug'];

        try {
            $this->container->get(SolvedAnswerService::class)->mark($user, $threadId, $postId);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($url . '#p' . $postId, $e->first());
        }
        return $this->redirectWithFlash($url . '#p' . $postId, 'Marked as the accepted answer.');
    }

    /** Clear the accepted answer of thread {id}. @param array<string,string> $params */
    public function unaccept(Request $request, array $params): Response
    {
        $this->requireCommunity();
        $user = $this->requireUser();

        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->container->get(ThreadRepository::class)->find($threadId);
        if ($thread === null) {
            throw new NotFoundException('Thread not found.');
        }
        $url = '/t/' . $threadId . '-' . (string) $thread['slug'];

        $this->container->get(SolvedAnswerService::class)->unmark($user, $threadId);
        return $this->redirectWithFlash($url, 'Cleared the accepted answer.');
    }

    private function requireCommunity(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('community')) {
            throw new NotFoundException('Not found.');
        }
    }
}
