<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cidr;
use PHPUnit\Framework\TestCase;

final class CidrTest extends TestCase
{
    public function test_ipv4_containment(): void
    {
        self::assertTrue(Cidr::contains('10.1.2.3', '10.0.0.0/8'));
        self::assertFalse(Cidr::contains('11.0.0.1', '10.0.0.0/8'));
        self::assertTrue(Cidr::contains('192.168.1.5', '192.168.0.0/16'));
        self::assertTrue(Cidr::contains('169.254.169.254', '169.254.0.0/16'));
    }

    public function test_exact_match_without_slash(): void
    {
        self::assertTrue(Cidr::contains('1.2.3.4', '1.2.3.4'));
        self::assertFalse(Cidr::contains('1.2.3.5', '1.2.3.4'));
    }

    public function test_ipv6_and_family_mismatch(): void
    {
        self::assertTrue(Cidr::contains('::1', '::1/128'));
        self::assertTrue(Cidr::contains('fe80::1', 'fe80::/10'));
        self::assertFalse(Cidr::contains('10.0.0.1', '::1/128'), 'v4 vs v6 must not match');
        self::assertFalse(Cidr::contains('garbage', '10.0.0.0/8'));
    }
}
