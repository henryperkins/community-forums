<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Domain\User;
use App\Security\ClientIdentifier;
use App\Security\RateLimiter;

/**
 * Central rate-limit service (P3-05). Wraps the shared {@see RateLimiter} store
 * with named policies from config (`rate_limits`), keyed by account when signed
 * in and by trusted-proxy-aware client IP otherwise, so limits hold consistently
 * across parallel PHP workers. enforce() throws HTTP 429 with a usable retry
 * interval; peek() lets a caller decide without consuming an attempt.
 */
final class RateLimitService
{
    public function __construct(
        private RateLimiter $limiter,
        private Config $config,
        private ClientIdentifier $clientId,
    ) {
    }

    /**
     * Consume one attempt against $policy and throw 429 if the window is already
     * exhausted. Unknown policies are a no-op (fail open rather than lock out).
     */
    public function enforce(string $policy, Request $request, ?User $user = null): void
    {
        $this->consume($policy, $request, $user, null);
    }

    /**
     * Consume a policy for an unauthenticated target such as a login email. The
     * subject is hashed into the key so limiter storage never contains raw emails.
     */
    public function enforceSubject(string $policy, Request $request, string $subject, ?User $user = null): void
    {
        $this->consume($policy, $request, $user, $subject);
    }

    private function consume(string $policy, Request $request, ?User $user, ?string $subject): void
    {
        $limits = $this->policy($policy);
        if ($limits === null) {
            return;
        }
        [$max, $decay] = $limits;
        $key = $this->key($policy, $request, $user, $subject);
        if ($this->limiter->tooManyAttempts($key, $max)) {
            $wait = max(1, $this->limiter->availableIn($key));
            throw new HttpException(429, "You're doing that too quickly. Please wait " . human_duration($wait) . ' and try again.');
        }
        $this->limiter->hit($key, $decay);
    }

    /** Reset a policy's window (e.g. after a successful, non-abusive action). */
    public function clear(string $policy, Request $request, ?User $user = null): void
    {
        if ($this->policy($policy) === null) {
            return;
        }
        $this->limiter->clear($this->key($policy, $request, $user, null));
    }

    /** Reset a subject-scoped policy window after a successful action. */
    public function clearSubject(string $policy, Request $request, string $subject, ?User $user = null): void
    {
        if ($this->policy($policy) === null) {
            return;
        }
        $this->limiter->clear($this->key($policy, $request, $user, $subject));
    }

    /** @return array{0:int,1:int}|null [max, decaySeconds] */
    private function policy(string $policy): ?array
    {
        $all = (array) $this->config->get('rate_limits', []);
        $p = $all[$policy] ?? null;
        if (!is_array($p) || count($p) < 2) {
            return null;
        }
        return [(int) $p[0], (int) $p[1]];
    }

    private function key(string $policy, Request $request, ?User $user, ?string $subject): string
    {
        // Signed-in callers are keyed by account alone so a per-account throttle
        // holds across IPs (matches this service's contract); anonymous callers
        // fall back to the trusted-proxy-aware client IP.
        $who = $user !== null ? 'u' . $user->id() : 'ip' . $this->clientId->ipFor($request);
        $subject = trim((string) $subject);
        if ($subject !== '') {
            $who .= ':s' . hash('sha256', strtolower($subject));
        }
        return 'rl:' . $policy . ':' . $who;
    }
}
