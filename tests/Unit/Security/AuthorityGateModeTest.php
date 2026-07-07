<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\AuthorityGate;
use PHPUnit\Framework\TestCase;

/**
 * Inc 6 follow-up: CAPABILITIES_MODE config values are normalized (trim +
 * case-fold) and unknown values are detectable instead of silently running
 * shadow. The config vocabulary is shadow|enforce only — `legacy` is the
 * flag-off state, not a configurable posture.
 */
final class AuthorityGateModeTest extends TestCase
{
    public function test_normalize_accepts_case_and_whitespace_variants(): void
    {
        self::assertSame(AuthorityGate::MODE_ENFORCE, AuthorityGate::normalizeConfigMode(' ENFORCE '));
        self::assertSame(AuthorityGate::MODE_ENFORCE, AuthorityGate::normalizeConfigMode('enforce'));
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode('Shadow'));
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode("shadow\n"));
    }

    public function test_normalize_maps_unknown_values_to_shadow(): void
    {
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode('enfroce'));
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode(''));
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode('legacy'));
        self::assertSame(AuthorityGate::MODE_SHADOW, AuthorityGate::normalizeConfigMode('enforce mode'));
    }

    public function test_is_known_config_mode_matches_the_normalized_vocabulary(): void
    {
        self::assertTrue(AuthorityGate::isKnownConfigMode('shadow'));
        self::assertTrue(AuthorityGate::isKnownConfigMode(' ENFORCE '));
        self::assertFalse(AuthorityGate::isKnownConfigMode('enfroce'));
        self::assertFalse(AuthorityGate::isKnownConfigMode('legacy'));
        self::assertFalse(AuthorityGate::isKnownConfigMode(''));
    }
}
