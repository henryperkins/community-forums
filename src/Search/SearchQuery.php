<?php

declare(strict_types=1);

namespace App\Search;

/** Immutable, normalized options for the replaceable forum-search seam. */
final readonly class SearchQuery
{
    public const SCOPES = ['everything', 'topics', 'replies', 'mine'];
    public const ORDERS = ['relevance', 'newest'];

    public string $query;
    public string $scope;
    public string $order;
    public int $limit;

    public function __construct(
        string $query,
        string $scope = 'everything',
        string $order = 'relevance',
        int $limit = 20,
    ) {
        $this->query = trim($query);
        $this->scope = in_array($scope, self::SCOPES, true) ? $scope : 'everything';
        $this->order = in_array($order, self::ORDERS, true) ? $order : 'relevance';
        $this->limit = max(1, min(50, $limit));
    }

    public function validationError(): ?string
    {
        if ($this->query === '') {
            return 'Enter a search phrase.';
        }
        if (mb_strlen($this->query) < 3) {
            return 'Search phrases must be at least 3 characters.';
        }
        return null;
    }

    public function isSearchable(): bool
    {
        return $this->validationError() === null;
    }

    public function url(?string $scope = null, ?string $order = null): string
    {
        $target = new self($this->query, $scope ?? $this->scope, $order ?? $this->order, $this->limit);
        return '/search?' . http_build_query([
            'q' => $target->query,
            'scope' => $target->scope,
            'order' => $target->order,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
