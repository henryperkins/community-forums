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
    public static function apply(Response $response, bool $hsts, bool $allowGiphy = false): Response
    {
        $response->header('Content-Security-Policy', self::csp($allowGiphy));
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Cross-Origin-Opener-Policy', 'same-origin');
        if ($hsts) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        return $response;
    }

    private static function csp(bool $allowGiphy): string
    {
        $img = "img-src 'self' data:";
        $connect = "connect-src 'self'";
        if ($allowGiphy) {
            $img .= ' https://*.giphy.com';
            $connect .= ' https://api.giphy.com';
        }

        return "default-src 'self'; base-uri 'self'; form-action 'self'; "
            . "frame-ancestors 'none'; " . $img . "; object-src 'none'; "
            . "script-src 'self'; style-src 'self'; " . $connect;
    }
}
