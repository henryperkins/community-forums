<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Per-request CSRF token derived from the active secret (session csrf_secret
 * for authenticated requests, or the guest CSRF cookie). The token is a stable
 * HMAC of the secret, so it round-trips across the GET that renders a form and
 * the POST that submits it, and is verified with a timing-safe comparison.
 */
final class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        return hash_hmac('sha256', 'rb-csrf-token', $this->session->csrfSecret());
    }

    public function verify(?string $submitted): bool
    {
        if (!is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($this->token(), $submitted);
    }
}
