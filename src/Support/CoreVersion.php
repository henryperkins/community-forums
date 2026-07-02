<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;

/**
 * Semver-ish core-version comparisons (Foundation F2). Compatibility bounds
 * are `MAJOR.MINOR.PATCH[-prerelease]` strings; anything else is malformed
 * and FAILS CLOSED (never satisfies). Ordering is PHP's version_compare -
 * notably '0.5.0-dev' < '0.5.0'. Consumed by the Inc-2 compatibility
 * resolver (P5-01 SP3) and Inc-3 manifest validation (P5-02).
 */
final class CoreVersion
{
    private const MAX_LENGTH = 32;
    private const PATTERN = '/\A\d+\.\d+\.\d+(?:-[0-9A-Za-z][0-9A-Za-z.\-]*)?\z/';

    public static function current(): string
    {
        return App::CORE_VERSION;
    }

    public static function isValid(string $version): bool
    {
        if (mb_strlen($version) > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::PATTERN, $version) === 1;
    }

    /** Inclusive-bounds range check; null bound = unbounded; malformed input fails closed. */
    public static function satisfies(?string $min, ?string $max, ?string $version = null): bool
    {
        $version ??= self::current();
        if (!self::isValid($version)) {
            return false;
        }
        if ($min !== null && (!self::isValid($min) || version_compare($version, $min, '<'))) {
            return false;
        }
        if ($max !== null && (!self::isValid($max) || version_compare($version, $max, '>'))) {
            return false;
        }

        return true;
    }
}
