<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Security\CapabilityCatalog;
use App\Service\CapabilityInventoryService;
use Tests\Support\TestCase;

final class CapabilityInventoryCoverageTest extends TestCase
{
    private const EXCLUSION_REASONS = [
        'account_state', 'board_read_gate', 'feature_flag', 'api_scope',
        'reputation_badges', 'profile_fields', 'bootstrap_auth', 'structural_invariant',
    ];

    public function test_every_non_protected_catalogued_key_has_at_least_one_call_site(): void
    {
        $svc = new CapabilityInventoryService();
        $matrix = $svc->matrix();
        foreach (CapabilityCatalog::keys() as $key) {
            if (CapabilityCatalog::isProtected($key)) {
                self::assertArrayNotHasKey($key, $matrix, "$key is protected — never a role/route call site");
                continue;
            }
            self::assertArrayHasKey($key, $matrix, "$key has no authoritative call-site anchor");
            self::assertNotEmpty($matrix[$key], "$key anchor list is empty");
        }
    }

    public function test_matrix_references_no_unknown_capability(): void
    {
        foreach (array_keys((new CapabilityInventoryService())->matrix()) as $key) {
            self::assertTrue(CapabilityCatalog::has($key), "matrix references uncatalogued key $key");
            self::assertFalse(CapabilityCatalog::isProtected($key), "matrix must not map protected key $key");
        }
    }

    public function test_exclusions_use_only_recorded_section_8_reasons(): void
    {
        foreach ((new CapabilityInventoryService())->exclusions() as $site => $reason) {
            self::assertContains($reason, self::EXCLUSION_REASONS, "$site uses an unrecorded exclusion reason '$reason'");
        }
    }
}
