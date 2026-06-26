<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Fixed-window rate limiter. Phase 1 keeps counters in a fast process/shared
 * store (file or array) behind this interface — NOT a DB table. The
 * configurable MySQL-backed limiter is Phase 3 (P3-05).
 */
interface RateLimiter
{
    /** True if the key has already reached its allowed attempts this window. */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /** Record one attempt; returns the new count. Starts a window of $decaySeconds. */
    public function hit(string $key, int $decaySeconds): int;

    /** Reset a key (e.g. after a successful login). */
    public function clear(string $key): void;

    /** Seconds until the current window resets (0 if no active window). */
    public function availableIn(string $key): int;
}
