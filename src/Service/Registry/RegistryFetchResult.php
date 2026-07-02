<?php

declare(strict_types=1);

namespace App\Service\Registry;

/** One outbound registry fetch: status 0 + error = transport-level failure. */
final class RegistryFetchResult
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $error,
    ) {
    }
}
