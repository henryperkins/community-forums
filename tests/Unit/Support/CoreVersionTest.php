<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Core\App;
use App\Support\CoreVersion;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F2 - the compatibility-version primitive the Inc-2 resolver
 * (P5-01 SP3; §10.3 target evidence "CompatibilityResolverTest") builds on.
 * `package_releases.core_min/core_max` (0049) are "semver-ish" strings; this
 * pins the exact semantics: MAJOR.MINOR.PATCH[-prerelease], inclusive bounds,
 * version_compare ordering, malformed input fails closed.
 */
final class CoreVersionTest extends TestCase
{
    public function test_core_version_constant_is_wellformed(): void
    {
        self::assertTrue(CoreVersion::isValid(App::CORE_VERSION));
        self::assertSame(App::CORE_VERSION, CoreVersion::current());
    }

    public function test_null_bounds_are_unbounded(): void
    {
        self::assertTrue(CoreVersion::satisfies(null, null, '0.5.0'));
    }

    public function test_bounds_are_inclusive(): void
    {
        self::assertTrue(CoreVersion::satisfies('0.5.0', '0.5.0', '0.5.0'));
        self::assertTrue(CoreVersion::satisfies('0.4.0', '0.6.0', '0.5.0'));
    }

    public function test_outside_bounds_fail(): void
    {
        self::assertFalse(CoreVersion::satisfies('0.6.0', null, '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, '0.4.9', '0.5.0'));
    }

    public function test_dev_prerelease_orders_before_the_bare_release(): void
    {
        // A '-dev' core must NOT satisfy a package that requires the released
        // core (fail closed), but must satisfy one accepting the prior minor.
        self::assertFalse(CoreVersion::satisfies('0.5.0', null, '0.5.0-dev'));
        self::assertTrue(CoreVersion::satisfies('0.4.0', null, '0.5.0-dev'));
    }

    public function test_malformed_input_fails_closed(): void
    {
        self::assertFalse(CoreVersion::satisfies('banana', null, '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, '1.0', '0.5.0'));
        self::assertFalse(CoreVersion::satisfies(null, null, 'not-a-version'));
        self::assertFalse(CoreVersion::isValid('1.0'));
        self::assertFalse(CoreVersion::isValid('v1.0.0'));
        self::assertTrue(CoreVersion::isValid('1.0.0-rc.1'));
    }

    public function test_default_version_is_the_core_constant(): void
    {
        self::assertTrue(CoreVersion::satisfies('0.1.0', null));
        self::assertFalse(CoreVersion::satisfies('99.0.0', null));
    }
}
