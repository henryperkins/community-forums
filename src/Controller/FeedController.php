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
        if (!$this->container->get(FeatureFlags::class)->enabled('community')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();

        $perPage = (int) $this->config()->get('community.feed_per_page', 20);
        $feed = $this->container->get(FeedService::class)->forUser($user->id(), $request->int('page', 1), $perPage);

        return $this->view('feed', [
            'items' => $feed['items'],
            'page' => $feed['page'],
            'has_more' => $feed['has_more'],
        ]);
    }
}
