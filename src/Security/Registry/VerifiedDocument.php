<?php

declare(strict_types=1);

namespace App\Security\Registry;

/** A signature-verified, format-checked rb-*.v1 document. */
final class VerifiedDocument
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public readonly string $format,
        public readonly array $payload,
        public readonly string $keyId,
    ) {
    }
}
