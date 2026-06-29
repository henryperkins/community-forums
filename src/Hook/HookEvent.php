<?php

declare(strict_types=1);

namespace App\Hook;

/** Immutable event object passed to first-party hook listeners. */
final readonly class HookEvent
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public string $name,
        public string $id,
        public array $data,
    ) {
    }
}
