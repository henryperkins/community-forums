<?php

declare(strict_types=1);

namespace App\Security;

/**
 * In-memory limiter for tests (no filesystem state to clean up between cases).
 */
final class ArrayRateLimiter implements RateLimiter
{
    /** @var array<string, array{count:int,reset_at:int}> */
    private array $store = [];

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $state = $this->active($key);
        return $state !== null && $state['count'] >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $state = $this->active($key) ?? ['count' => 0, 'reset_at' => time() + $decaySeconds];
        $state['count']++;
        $this->store[$key] = $state;
        return $state['count'];
    }

    public function clear(string $key): void
    {
        unset($this->store[$key]);
    }

    public function availableIn(string $key): int
    {
        $state = $this->active($key);
        return $state === null ? 0 : max(0, $state['reset_at'] - time());
    }

    /** @return array{count:int,reset_at:int}|null */
    private function active(string $key): ?array
    {
        $state = $this->store[$key] ?? null;
        if ($state === null || $state['reset_at'] <= time()) {
            unset($this->store[$key]);
            return null;
        }
        return $state;
    }
}
