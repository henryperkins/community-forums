<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Service\PresenceService;
use App\Service\RateLimitService;

/**
 * Privacy-respecting presence (P2-11, remediated by ADR 0031).
 *
 * Two surfaces over one service: a short-poll JSON roster and the /users-online
 * directory. Neither re-derives visibility — {@see PresenceService} owns the
 * whole ladder (feature flag, banned, show_presence, profile_visibility, the
 * window, blocks both ways), so these methods only marshal input and shape a
 * response. Heartbeats are written by the kernel on normal requests.
 *
 * Both routes are rate limited under one `presence` policy. /presence is the
 * only polled endpoint open to guests, and /users-online is now paginated and
 * searchable, so they share a bucket: throttling the poll while leaving
 * paginated enumeration wide open would be theatre.
 */
final class PresenceController extends Controller
{
    public function page(Request $request): Response
    {
        $this->requirePresence();
        // A human gets the kernel's HTML error page; only the poller needs JSON.
        $this->container->get(RateLimitService::class)->enforce('presence', $request, $this->currentUser());

        // ?filter[]=x makes query() hand back an array, which would fatal on a
        // (string) cast — take only scalars and let the service clamp the rest.
        $directory = $this->container->get(PresenceService::class)->directory(
            $this->currentUser(),
            self::queryString($request, 'filter'),
            self::queryString($request, 'q'),
            max(1, (int) self::queryString($request, 'page')),
        );

        // no-cache, not no-store: the body is viewer-specific so it must be
        // revalidated, but no-store also kills the bfcache, and roster → profile
        // → Back is this page's primary journey. Vary: Cookie because `private`
        // alone is a bet on well-behaved intermediaries.
        return $this->view('users_online', ['directory' => $directory])
            ->header('Cache-Control', 'private, no-cache')
            ->header('Vary', 'Cookie');
    }

    public function index(Request $request): Response
    {
        $this->requirePresence();
        $throttled = $this->throttleJson($request);
        if ($throttled !== null) {
            return $throttled;
        }

        $snapshot = $this->container->get(PresenceService::class)->snapshot($this->currentUser());

        return Response::json([
            // `count` is HERE-NOW, not the whole roster: the badge would lie the
            // moment away members entered it. `here` is its permanent name;
            // `count` stays one release as the alias a cached script still reads.
            'count' => $snapshot['here'],
            'here' => $snapshot['here'],
            'away' => $snapshot['away'],
            'total' => $snapshot['total'],
            'capped' => $snapshot['capped'],
            // No last_seen_at. Publishing second-precision activity timestamps
            // for every opted-in member to anonymous clients was never what
            // "Show when I'm online" asked for, and nothing rendered it.
            'online' => $snapshot['members'],
        ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Cookie');
    }

    private static function queryString(Request $request, string $key): string
    {
        $value = $request->query($key, '');
        return is_scalar($value) ? (string) $value : '';
    }

    private function requirePresence(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('presence')) {
            throw new NotFoundException('Not found.');
        }
    }

    /**
     * A 429 rendered as the kernel's HTML error page is useless to a fetch()
     * loop — it reads as a failed request and the poller keeps hammering at its
     * normal cadence. Answer in JSON with a Retry-After the client can obey.
     */
    private function throttleJson(Request $request): ?Response
    {
        $limits = $this->container->get(RateLimitService::class);
        try {
            $limits->enforce('presence', $request, $this->currentUser());
        } catch (HttpException) {
            $retryAfter = max(1, $limits->retryAfter('presence', $request, $this->currentUser()));
            return Response::json(['error' => 'rate_limited', 'retry_after' => $retryAfter], 429)
                ->header('Retry-After', (string) $retryAfter)
                ->header('Cache-Control', 'private, no-store');
        }
        return null;
    }
}
