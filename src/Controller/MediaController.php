<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\AttachmentRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\ConversationRepository;
use App\Repository\PostRepository;
use App\Security\BoardPolicy;
use App\Service\AttachmentService;
use App\Service\RateLimitService;

/**
 * Image upload + authorization-gated delivery (P3-04). Uploads are rate-limited,
 * content-sniffed, and re-encoded by AttachmentService. Delivery re-checks the
 * CURRENT access to the parent content on every request, so a copied media URL
 * from a private board or DM returns nothing to a guest, non-member, removed
 * member, or a user whose access was revoked after upload.
 */
final class MediaController extends Controller
{
    public function upload(Request $request): Response
    {
        $user = $this->requireUser();
        if (!$this->container->get(FeatureFlags::class)->enabled('uploads')) {
            return Response::json(['ok' => false, 'error' => 'Uploads are disabled.'], 403);
        }
        $this->container->get(RateLimitService::class)->enforce('upload', $request, $user);

        // New-account upload throttle (anti-abuse).
        $minPosts = (int) $this->config()->get('uploads.new_user_min_posts', 0);
        if ($minPosts > 0 && !$user->isModerator()) {
            $posts = (int) ($this->container->get(\App\Repository\UserRepository::class)->find($user->id())['post_count'] ?? 0);
            if ($posts < $minPosts) {
                return Response::json(['ok' => false, 'error' => 'New accounts cannot upload images yet.'], 403);
            }
        }

        $file = $request->file('image');
        if ($file === null) {
            return Response::json(['ok' => false, 'error' => 'No image was uploaded.'], 422);
        }

        $purpose = $request->str('purpose') === 'dm' ? 'dm' : 'post';
        try {
            $row = $this->container->get(AttachmentService::class)->storeUpload($user->id(), $file, $purpose);
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'error' => $e->first()], 422);
        }

        return Response::json([
            'ok' => true,
            'id' => (int) $row['id'],
            'url' => '/media/' . (int) $row['id'],
            'markdown' => '![](/media/' . (int) $row['id'] . ')',
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
        ]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $id = (int) ($params['id'] ?? 0);
        $att = $this->container->get(AttachmentRepository::class)->find($id);
        if ($att === null || $att['status'] === 'deleted') {
            throw new NotFoundException('Media not found.');
        }
        // Cacheability is decided by the LIVE authorization result, not the stored
        // visibility/status columns: access-restricted media (held-post images, or
        // boards flipped to private after upload) must never be served with a
        // public, long-lived cache header that survives a later revocation.
        $publicCacheable = $this->authorize($att);

        $bytes = $this->container->get(AttachmentService::class)->readBytes($att);
        if ($bytes === null) {
            throw new NotFoundException('Media not found.');
        }

        return (new Response($bytes, 200, [
            'Content-Type' => (string) $att['mime'],
            'Content-Length' => (string) strlen($bytes),
            'Content-Disposition' => 'inline',
            // Private media is never shared-cacheable; public media may be cached.
            'Cache-Control' => $publicCacheable ? 'public, max-age=31536000, immutable' : 'private, no-store',
        ]));
    }

    /**
     * Authorize the current viewer for this attachment (throws on denial) and
     * report whether the bytes are safe to serve with a public, long-lived cache.
     * Public-cacheable is true ONLY when access derives from genuinely public
     * content (a public, non-pending board post, or a public parentless asset),
     * never merely because the viewer happens to be the owner/a DM participant —
     * so a later access revocation is never served from a stale shared cache.
     *
     * @param array<string,mixed> $att
     */
    private function authorize(array $att): bool
    {
        $user = $this->currentUser();
        $owner = $user !== null && (int) $att['user_id'] === $user->id();

        // Unbound temp uploads are visible only to their owner; never public.
        if ($att['status'] === 'temp') {
            if (!$owner) {
                throw new NotFoundException('Media not found.');
            }
            return false;
        }

        // DM media: only conversation participants (the owner is always one).
        if ($att['dm_message_id'] !== null) {
            if (!$owner) {
                $msg = $this->container->get(\App\Repository\DmMessageRepository::class)->find((int) $att['dm_message_id']);
                $convId = $msg !== null ? (int) $msg['conversation_id'] : 0;
                if ($user === null || $convId === 0
                    || !$this->container->get(ConversationRepository::class)->isParticipant($convId, $user->id())) {
                    throw new NotFoundException('Media not found.');
                }
            }
            return false; // DM media is always private.
        }

        // Post media: re-check current access to the parent post's board.
        if ($att['post_id'] !== null) {
            $post = $this->container->get(PostRepository::class)->findWithContext((int) $att['post_id']);
            if ($post === null || (int) $post['is_deleted'] === 1) {
                throw new NotFoundException('Media not found.');
            }
            $pending = (int) ($post['is_pending'] ?? 0) === 1;
            if (!$owner) {
                // Held (pending) post media is restricted to the owner/moderators.
                if ($pending && !($user !== null && $user->isModerator())) {
                    throw new NotFoundException('Media not found.');
                }
                $board = ['id' => (int) $post['board_id'], 'visibility' => (string) $post['board_visibility']];
                $isMember = $user !== null
                    && in_array((int) $post['board_id'], $this->container->get(BoardMemberRepository::class)->boardIdsFor($user->id()), true);
                if (!$this->container->get(BoardPolicy::class)->canRead($board, $user, $isMember)) {
                    // 404 (not 403) so an unauthorized viewer can't distinguish
                    // "exists but forbidden" from "absent" by enumerating ids.
                    throw new NotFoundException('Media not found.');
                }
            }
            // Cacheable as public only for a public, non-pending board post.
            return !$pending && (string) $post['board_visibility'] === 'public';
        }

        // Public finalized media with no private parent.
        if ($att['visibility'] === 'public') {
            return true;
        }
        if (!$owner) {
            throw new NotFoundException('Media not found.');
        }
        return false;
    }
}
