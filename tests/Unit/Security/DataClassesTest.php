<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\DataClasses;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F4 - the approved local data-class catalogue (ADR 0004 D4,
 * PHASE_5_PLAN section 5 #8). Mirrors CapabilityCatalogTest's invariants:
 * pinned count, namespaced keys, closed risk vocabulary, protected iff
 * non-grantable iff no consent string, and the six high-risk classes present.
 */
final class DataClassesTest extends TestCase
{
    public function test_catalogue_has_exactly_10_keys(): void
    {
        self::assertCount(10, DataClasses::all());
        self::assertCount(10, DataClasses::keys());
    }

    public function test_every_key_is_namespaced_with_valid_risk(): void
    {
        foreach (DataClasses::all() as $key => $def) {
            self::assertMatchesRegularExpression('/^[a-z]+(?:\.[a-z_]+)+$/', $key);
            self::assertContains($def[0], ['low', 'medium', 'high', 'protected'], "$key risk");
            self::assertNotSame('', trim($def[1]), "$key description");
        }
    }

    public function test_protected_invariant_holds(): void
    {
        foreach (DataClasses::keys() as $key) {
            $protected = DataClasses::isProtected($key);
            self::assertSame($protected, DataClasses::risk($key) === 'protected', $key);
            self::assertSame($protected, !DataClasses::grantable($key), $key);
            if ($protected) {
                self::assertNull(DataClasses::consent($key), "$key must have no consent string");
            } else {
                self::assertNotNull(DataClasses::consent($key), $key);
                self::assertNotSame('', trim((string) DataClasses::consent($key)), $key);
            }
        }
    }

    public function test_the_six_high_risk_classes_from_spec_are_present(): void
    {
        // PHASE_5_PLAN section 5 #8, verbatim set. security.config is protected.
        foreach (['content.private', 'messages.direct', 'user.pii', 'moderation.records', 'auth.events'] as $key) {
            self::assertTrue(DataClasses::has($key), $key);
            self::assertSame('high', DataClasses::risk($key), $key);
        }
        self::assertTrue(DataClasses::has('security.config'));
        self::assertSame('protected', DataClasses::risk('security.config'));
    }

    public function test_unknown_key_is_rejected(): void
    {
        self::assertFalse(DataClasses::has('content.everything'));
    }
}
