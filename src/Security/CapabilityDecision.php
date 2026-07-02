<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Immutable capability decision. `source` names the decisive rule so shadow
 * parity and simulator surfaces can explain why without re-deriving it.
 */
final class CapabilityDecision
{
    private function __construct(
        public readonly bool $allowed,
        public readonly string $capability,
        public readonly string $source,
        public readonly string $reason,
        public readonly ?string $roleKey = null,
        public readonly ?string $scopeType = null,
        public readonly ?int $scopeId = null,
    ) {
    }

    public static function allow(
        string $capability,
        string $source,
        string $reason,
        ?string $roleKey = null,
        ?string $scopeType = null,
        ?int $scopeId = null,
    ): self {
        return new self(true, $capability, $source, $reason, $roleKey, $scopeType, $scopeId);
    }

    public static function deny(string $capability, string $source, string $reason): self
    {
        return new self(false, $capability, $source, $reason);
    }
}
