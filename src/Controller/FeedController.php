<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\FeedService;

/**
 * The Following feed (COMMUNITY §5): recent activity from the people you follow,
 * computed at read time and gated to content you can actually access.
 */
final class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        $flags = $this->container->get(FeatureFlags::class);
        if (!$flags->enabled('community')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $expanded = $flags->enabled('expanded_feeds');

        $view = (string) $request->query('view', 'following');
        if (!in_array($view, ['following', 'latest'], true)) {
            $view = 'following';
        }
        if (!$expanded && $view === 'latest') {
            $view = 'following';
        }
        $perPage = (int) $this->config()->get('community.feed_per_page', 20);
        $service = $this->container->get(FeedService::class);
        $feed = $view === 'latest'
            ? $service->latest($user->id(), $request->int('page', 1), $perPage)
            : $service->forUser($user->id(), $request->int('page', 1), $perPage, $expanded);

        return $this->view('feed', [
            'feed_view' => $view,
            'items' => $feed['items'],
            'page' => $feed['page'],
            'has_more' => $feed['has_more'],
            'expanded_feeds' => $expanded,
        ]);
    }
}
