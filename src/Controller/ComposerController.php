<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\ComposerSuggestion;
use App\Service\ComposerSuggestionService;
use App\Service\RateLimitService;
use App\Support\Markdown;

/**
 * Shared composer support (P3-02). The live preview goes through the SAME server
 * render + sanitize pipeline as a real post, so what a user previews is exactly
 * what will be stored and shown — no client-side Markdown engine to drift from
 * the canonical one. Auth-only, CSRF-protected, and rate-limited.
 */
final class ComposerController extends Controller
{
    public function preview(Request $request): Response
    {
        $user = $this->requireUser();
        if (!$this->container->get(FeatureFlags::class)->enabled('rich_composer')) {
            return Response::json(['ok' => false, 'error' => 'Preview is unavailable.'], 403);
        }
        $this->container->get(RateLimitService::class)->enforce('composer_preview', $request, $user);

        $body = (string) $request->input('body', '');
        $max = (int) $this->config()->get('limits.post_body_max', 20000);
        if (mb_strlen($body) > $max) {
            $body = mb_substr($body, 0, $max);
        }
        $html = $this->container->get(Markdown::class)->render($body, ['link_mentions' => true]);

        return Response::json(['ok' => true, 'html' => $html]);
    }

    public function suggest(Request $request): Response
    {
        $user = $this->requireUser();
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('rich_composer')) {
            throw new NotFoundException('Not found.');
        }
        $this->container->get(RateLimitService::class)->enforce('composer_suggest', $request, $user);

        $trigger = (string) $request->query('trigger', '');
        $q = (string) $request->query('q', '');
        $context = (string) $request->query('context', '');
        $targetId = (int) $request->query('target_id', 0);

        if (!in_array($trigger, ['@', '#', ':'], true)) {
            return Response::json(['ok' => false, 'error' => 'Unsupported trigger.'], 422);
        }

        $items = $this->container->get(ComposerSuggestionService::class)
            ->suggest($trigger, $q, $context, $targetId, $user);

        return Response::json([
            'ok' => true,
            'items' => array_map(static fn (ComposerSuggestion $item): array => $item->toArray(), $items),
        ]);
    }
}
