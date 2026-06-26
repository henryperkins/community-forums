<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Search\SearchService;

/**
 * Full-text search (P2-06). Public for guests (public content only); a member's
 * results also include private boards they belong to. The read gate lives in
 * the SearchService, applied before any result is returned or linked.
 */
final class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('search')) {
            throw new NotFoundException('Not found.');
        }

        $query = trim((string) $request->query('q', ''));
        $results = [];
        $searched = false;
        if ($query !== '') {
            $searched = true;
            $results = $this->container->get(SearchService::class)->search($query, $this->currentUser(), 20);
        }

        return $this->view('search', [
            'query' => $query,
            'results' => $results,
            'searched' => $searched,
        ]);
    }
}
