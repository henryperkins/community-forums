<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use App\Security\CapabilityCatalog;
use Tests\Support\TestCase;

final class AppPhase5CapabilitySeedTest extends TestCase
{
    public function test_0066_seeds_the_full_catalogue_matching_the_code(): void
    {
        self::assertSame(54, (int) $this->db->fetchValue('SELECT COUNT(*) FROM capabilities'));

        foreach (CapabilityCatalog::all() as $key => $meta) {
            $row = $this->db->fetch('SELECT scope_type, risk_class, is_delegable, is_protected FROM capabilities WHERE capability_key = ?', [$key]);
            self::assertNotNull($row, "capability $key not seeded");
            self::assertSame($meta['scope'], $row['scope_type'], "$key scope_type");
            self::assertSame($meta['risk'], $row['risk_class'], "$key risk_class");
            self::assertSame($meta['delegable'] ? 1 : 0, (int) $row['is_delegable'], "$key is_delegable");
            self::assertSame($meta['protected'] ? 1 : 0, (int) $row['is_protected'], "$key is_protected");
        }
    }

    public function test_role_capabilities_reproduce_cumulative_authority(): void
    {
        $expected = ['system.guest' => 1, 'system.user' => 15, 'system.moderator' => 28, 'system.admin' => 49];
        foreach ($expected as $roleKey => $count) {
            $actual = (int) $this->db->fetchValue(
                'SELECT COUNT(*) FROM role_capabilities rc
                 JOIN roles r ON r.id = rc.role_id
                 WHERE r.role_key = ?',
                [$roleKey],
            );
            self::assertSame($count, $actual, "$roleKey capability count");
        }
    }

    public function test_no_protected_capability_is_ever_role_mapped(): void
    {
        $mapped = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM role_capabilities rc
             JOIN capabilities c ON c.id = rc.capability_id
             WHERE c.is_protected = 1",
        );
        self::assertSame(0, $mapped, 'protected capabilities must never appear in role_capabilities');
    }

    public function test_seeding_the_catalogue_does_not_enable_the_capabilities_flag(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertFalse($flags->enabled('capabilities'), 'catalogue seed must stay deploy-dark');
    }
}
