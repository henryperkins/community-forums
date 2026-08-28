<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Search\SearchQuery;
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

        $rawQuery = $request->query('q');
        $rawScope = $request->query('scope', 'everything');
        $rawOrder = $request->query('order', 'relevance');
        $searchQuery = new SearchQuery(
            is_string($rawQuery) ? $rawQuery : '',
            is_string($rawScope) ? $rawScope : 'everything',
            is_string($rawOrder) ? $rawOrder : 'relevance',
            20,
        );
        $submitted = $rawQuery !== null;
        $error = $submitted ? $searchQuery->validationError() : null;
        $results = [];
        if ($submitted && $error === null) {
            $results = $this->container->get(SearchService::class)->search($searchQuery, $this->currentUser());
        }

        return $this->view('search', [
            'query' => $searchQuery->query,
            'search_query' => $searchQuery,
            'scope' => $searchQuery->scope,
            'order' => $searchQuery->order,
            'results' => $results,
            'submitted' => $submitted,
            'error' => $error,
        ]);
    }
}
