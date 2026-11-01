<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Response;
use App\Security\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_sensitive_pages_can_keep_the_stricter_no_referrer_policy(): void
    {
        $response = new Response();
        $response->header('Referrer-Policy', 'no-referrer');
        SecurityHeaders::apply($response, false);
        self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
        self::assertStringContainsString("form-action 'self'", $response->getHeader('Content-Security-Policy'));
    }

    public function test_the_baseline_policy_replaces_a_weaker_page_policy(): void
    {
        $response = new Response();
        $response->header('Referrer-Policy', 'unsafe-url');
        SecurityHeaders::apply($response, false);
        self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
    }
}
