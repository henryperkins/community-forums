<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Response;

/**
 * Baseline P0 security headers applied to every response. The CSP is strict and
 * self-only with no inline scripts or styles (the app ships an external
 * stylesheet and a single external progressive-enhancement script), so no
 * 'unsafe-inline' is needed.
 */
final class SecurityHeaders
{
    private const CSP = "default-src 'self'; base-uri 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; "
        . "script-src 'self'; style-src 'self'";

    public static function apply(Response $response, bool $hsts): Response
    {
        $response->header('Content-Security-Policy', self::CSP);
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        if ($hsts) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        return $response;
    }
}
