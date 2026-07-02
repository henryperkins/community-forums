<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Registry;

use App\Security\Registry\PackageIdentity;
use PHPUnit\Framework\TestCase;

final class PackageIdentityTest extends TestCase
{
    public function test_uid_validation_is_fail_closed(): void
    {
        self::assertTrue(PackageIdentity::isValidUid('acme/midnight-theme'));
        self::assertTrue(PackageIdentity::isValidUid('a1/b2.c-d_e'));

        foreach ([
            '', 'acme', '/theme', 'acme/', 'Acme/Theme', 'acme//theme',
            'acme/theme/extra', '-acme/theme', 'acme/-theme', "acme/th\u{00e9}me",
            'acme/' . str_repeat('x', 94), '../etc/passwd', "acme/theme\n",
        ] as $bad) {
            self::assertFalse(PackageIdentity::isValidUid($bad), "should reject: $bad");
        }
    }

    public function test_publisher_uid_is_the_namespace_prefix(): void
    {
        self::assertSame('acme', PackageIdentity::publisherUid('acme/midnight-theme'));
    }

    public function test_source_id_validation(): void
    {
        self::assertTrue(PackageIdentity::isValidSourceId('rb-test'));
        self::assertFalse(PackageIdentity::isValidSourceId(''));
        self::assertFalse(PackageIdentity::isValidSourceId('RB Test'));
        self::assertFalse(PackageIdentity::isValidSourceId('-rb'));
        self::assertFalse(PackageIdentity::isValidSourceId("rb-test\n"));
    }
}
