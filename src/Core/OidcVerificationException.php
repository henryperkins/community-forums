<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A generic-OIDC verification refusal (P5-12). `$reason` is a stable
 * machine-readable code (`issuer_mismatch`, `unknown_kid`, …) — safe to log,
 * never user-facing. The kernel does not catch this; the OAuth callback's
 * catch-all turns it into the neutral "could not complete sign-in" flow.
 */
final class OidcVerificationException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
