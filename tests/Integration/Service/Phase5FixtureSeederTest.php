<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Service\Phase5FixtureSeeder;
use Tests\Support\TestCase;

final class Phase5FixtureSeederTest extends TestCase
{
    private function seeder(string $env = 'testing'): Phase5FixtureSeeder
    {
        return new Phase5FixtureSeeder($this->db, new SettingRepository($this->db), $env);
    }

    public function test_seed_builds_the_representative_corpus(): void
    {
        $out = $this->seeder()->seed();

        self::assertFalse($out['skipped']);
        self::assertSame(10, $out['users']);
        self::assertSame(4, $out['boards']);
        self::assertSame(2, $out['moderators']);
        self::assertSame(4, $out['assignments']);
        self::assertSame(1, $out['owners']);

        // Temporal spread is present: one already-expired, one still-future assignment.
        $expired = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM role_assignments WHERE ends_at IS NOT NULL AND ends_at < UTC_TIMESTAMP()',
        );
        $future = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM role_assignments WHERE starts_at IS NOT NULL AND starts_at > UTC_TIMESTAMP()',
        );
        self::assertSame(1, $expired, 'exactly one expired assignment');
        self::assertSame(1, $future, 'exactly one future assignment');

        self::assertTrue((new ProtectedOwnerRepository($this->db))->hasAnyActiveOwner());
    }

    public function test_seed_is_idempotent(): void
    {
        $s = $this->seeder();
        $s->seed();
        self::assertTrue($s->isSeeded());

        $second = $s->seed();
        self::assertTrue($second['skipped']);
        self::assertSame(0, $second['users']);
        self::assertSame(10, (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE username LIKE 'p5fix_%'"));
    }

    public function test_seed_refuses_production(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->seeder('production')->seed();
    }
}
