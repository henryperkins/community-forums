<?php

declare(strict_types=1);

namespace App\Core;

/**
 * One-shot flash messages carried in a short-lived cookie so they survive a
 * PRG redirect for both guests and authenticated users (no DB session needed).
 */
final class Flash
{
    private const COOKIE = 'rb_flash';

    private ?string $pending = null;
    private ?string $current = null;

    public function __construct(private bool $secure)
    {
    }

    public function load(Request $request): void
    {
        $value = $request->cookie(self::COOKIE);
        $this->current = $value !== null && $value !== '' ? $value : null;
    }

    public function current(): ?string
    {
        return $this->current;
    }

    public function add(string $message): void
    {
        $this->pending = $message;
    }

    public function commit(Response $response): void
    {
        if ($this->pending !== null) {
            $response->setCookie(self::COOKIE, $this->pending, time() + 60, '/', $this->secure, true, 'Lax');
        } elseif ($this->current !== null) {
            // Shown this request; clear it so it doesn't repeat.
            $response->forgetCookie(self::COOKIE, $this->secure);
        }
    }
}
