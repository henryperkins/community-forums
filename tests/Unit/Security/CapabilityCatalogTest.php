<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\CapabilityCatalog;
use PHPUnit\Framework\TestCase;

final class CapabilityCatalogTest extends TestCase
{
    private const SCOPES = ['site', 'category', 'board', 'self'];
    private const RISKS  = ['low', 'medium', 'high', 'protected'];

    public function test_catalogue_has_exactly_54_keys(): void
    {
        self::assertCount(54, CapabilityCatalog::all(), 'A1 taxonomy defines 54 core.* keys');
    }

    public function test_every_key_is_namespaced_core_with_valid_scope_and_risk(): void
    {
        foreach (CapabilityCatalog::all() as $key => $meta) {
            self::assertStringStartsWith('core.', $key, "$key must be core-namespaced");
            self::assertContains($meta['scope'], self::SCOPES, "$key scope");
            self::assertContains($meta['risk'], self::RISKS, "$key risk");
        }
    }

    public function test_protected_invariant_holds(): void
    {
        // §2 invariant: risk='protected' <=> is_protected <=> NOT is_delegable.
        self::assertCount(5, CapabilityCatalog::PROTECTED);
        foreach (CapabilityCatalog::all() as $key => $meta) {
            $isProtected = in_array($key, CapabilityCatalog::PROTECTED, true);
            self::assertSame($isProtected, $meta['protected'], "$key protected flag");
            self::assertSame($isProtected, $meta['risk'] === 'protected', "$key risk<=>protected");
            self::assertSame(!$isProtected, $meta['delegable'], "$key delegable<=>!protected");
        }
    }

    public function test_every_non_protected_key_has_a_non_empty_consent_string(): void
    {
        foreach (CapabilityCatalog::all() as $key => $meta) {
            if (in_array($key, CapabilityCatalog::PROTECTED, true)) {
                self::assertNull($meta['consent'], "$key (protected) must have no consent string");
                continue;
            }
            self::assertIsString($meta['consent']);
            self::assertNotSame('', trim((string) $meta['consent']), "$key needs a consent string");
        }
    }

    public function test_role_capabilities_are_cumulative_with_expected_counts(): void
    {
        $roles = CapabilityCatalog::roleCapabilities();
        self::assertCount(1, $roles['system.guest']);
        self::assertCount(15, $roles['system.user']);       // guest(1) + §4.2 (14)
        self::assertCount(28, $roles['system.moderator']);  // user(15) + §4.3 (13)
        self::assertCount(49, $roles['system.admin']);      // moderator(28) + §4.4 (21)

        // Cumulative: each tier is a superset of the previous.
        self::assertSame([], array_diff($roles['system.guest'], $roles['system.user']));
        self::assertSame([], array_diff($roles['system.user'], $roles['system.moderator']));
        self::assertSame([], array_diff($roles['system.moderator'], $roles['system.admin']));

        // Every role key is catalogued and no protected key is ever role-mapped.
        foreach ($roles['system.admin'] as $key) {
            self::assertTrue(CapabilityCatalog::has($key), "$key mapped but not catalogued");
            self::assertNotContains($key, CapabilityCatalog::PROTECTED, "$key protected keys are never role-mapped");
        }
    }
}
