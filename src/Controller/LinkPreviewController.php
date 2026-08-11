<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\LinkPreviewRepository;
use App\Repository\PostRepository;
use App\Service\LinkPreviewService;

/**
 * Author-facing preview control (ADR 0025). A member decides whether their own
 * post carries an unfurled card; board moderators can act on posts they
 * moderate. Removal is sticky — editing the post does not bring it back.
 *
 * No-JS by design: both actions are plain POSTs that redirect back to the post
 * anchor, exactly like reactions and edits.
 */
final class LinkPreviewController extends Controller
{
    /** @param array<string,string> $params */
    public function remove(Request $request, array $params): Response
    {
        return $this->act($request, $params, 'remove');
    }

    /** @param array<string,string> $params */
    public function restore(Request $request, array $params): Response
    {
        return $this->act($request, $params, 'restore');
    }

    /** @param array<string,string> $params */
    private function act(Request $request, array $params, string $action): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('link_previews')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $postId = (int) ($params['id'] ?? 0);
        $previewId = (int) ($params['preview'] ?? 0);

        $service = $this->container->get(LinkPreviewService::class);
        // The route carries the post so the redirect target is trustworthy; the
        // service still resolves authority from the preview's own source, and a
        // mismatched pair is a 404 rather than a silent cross-post edit.
        $row = $this->container->get(LinkPreviewRepository::class)->find($previewId);
        if ($row === null || (string) $row['source_type'] !== 'post' || (int) $row['source_id'] !== $postId) {
            throw new NotFoundException('Preview not found.');
        }

        if ($action === 'remove') {
            $service->remove($user, $previewId);
            $message = 'Link preview removed.';
        } else {
            $service->restore($user, $previewId);
            $message = 'Link preview restored; it will reappear once refetched.';
        }

        $post = $this->container->get(PostRepository::class)->findWithContext($postId);
        if ($post === null) {
            throw new NotFoundException('Post not found.');
        }

        return $this->redirectWithFlash(
            $this->postLocation((int) $post['thread_id'], (string) $post['thread_slug'], $postId),
            $message,
        );
    }
}
