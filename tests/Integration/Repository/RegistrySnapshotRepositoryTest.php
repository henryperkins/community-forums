<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ModerationLogRepository;
use App\Repository\RegistrySnapshotRepository;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistrySnapshotRepositoryTest extends TestCase
{
    public function test_snapshot_rows_round_trip_and_latest_is_by_generated_at(): void
    {
        $ids = RegistryFixtures::seed($this->db, SigningHarness::generate('root-1'));
        $repo = new RegistrySnapshotRepository($this->db);
        $registryId = $ids['registry_id'];

        self::assertNull($repo->latestFor($registryId));

        $repo->record($registryId, str_repeat('a', 64), '{"v":1}', 'sig-a', 'root-1', '2026-07-01 00:00:00', '2026-07-02 00:00:00');
        $repo->record($registryId, str_repeat('b', 64), '{"v":2}', 'sig-b', 'root-1', '2026-07-02 00:00:00', '2026-07-03 00:00:00');

        $latest = $repo->latestFor($registryId);
        self::assertNotNull($latest);
        self::assertSame(str_repeat('b', 64), $latest['digest']);
        self::assertSame('{"v":2}', $latest['document']);

        self::assertNotNull($repo->findByDigest($registryId, str_repeat('a', 64)));
        self::assertNull($repo->findByDigest($registryId, str_repeat('c', 64)));
    }

    public function test_moderation_log_accepts_the_new_target_types(): void
    {
        $log = new ModerationLogRepository($this->db);
        $a = $log->log(['actor_id' => null, 'action' => 'registry_pin_key', 'target_type' => 'registry', 'target_id' => 1]);
        $b = $log->log(['actor_id' => null, 'action' => 'package_block', 'target_type' => 'package', 'target_id' => 0]);
        self::assertGreaterThan(0, $a);
        self::assertGreaterThan($a, $b);
    }
}
