<?php

declare(strict_types=1);
namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Service\SavedFeedService;

final class SavedFeedController extends Controller
{
    public function show(Request $request, array $params): Response
    {
        $feed = $this->container->get(SavedFeedService::class)->open($this->requireUser(), (int) $params['id'], $request->int('page', 1));
        return $this->view('feed', $feed + ['saved_feed' => $feed, 'feed_view' => 'saved']);
    }
}
