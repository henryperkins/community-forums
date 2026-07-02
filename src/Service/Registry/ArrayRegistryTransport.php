<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** Canned in-memory transport for tests. Unknown URL = 404. */
final class ArrayRegistryTransport implements RegistryTransport
{
    /** @param array<string,RegistryFetchResult> $responses */
    public function __construct(private array $responses)
    {
    }

    public function fetch(string $url): RegistryFetchResult
    {
        return $this->responses[$url] ?? new RegistryFetchResult(404, '', null);
    }
}
