<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\RepairService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class RegistryAdvisoryServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;

    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function advisories(): RegistryAdvisoryService
    {
        return new RegistryAdvisoryService(
            $this->db,
            new TrustChainVerifier(),
            new RegistryTrustKeyRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new ModerationLogRepository($this->db),
        );
    }

    private function blocklist(): LocalBlocklistService
    {
        return new LocalBlocklistService(
            new LocalPackageBlockRepository($this->db),
            new PackageRepository($this->db),
            $this->reauthGate(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function reauthGate(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    public function test_the_escalation_ladder_is_escalate_only(): void
    {
        self::assertSame('warned', RegistryAdvisoryService::escalate('none', 'warned'));
        self::assertSame('blocked', RegistryAdvisoryService::escalate('warned', 'blocked'));
        self::assertSame('revoked', RegistryAdvisoryService::escalate('blocked', 'revoked'));
        self::assertSame('revoked', RegistryAdvisoryService::escalate('revoked', 'warned'), 'never de-escalates');
        self::assertSame('blocked', RegistryAdvisoryService::escalate('blocked', 'none'));
    }

    public function test_version_range_grammar_fails_toward_affected(): void
    {
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<=1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<=1.0.0', '0.9.0'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('<=1.0.0', '1.1.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('<2.0.0', '1.9.9'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('>1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('>=1.0.0', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('1.0.0', '1.0.0'));
        self::assertFalse(RegistryAdvisoryService::affectsVersion('=1.0.0', '1.0.1'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('*', '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion(null, '1.0.0'));
        self::assertTrue(RegistryAdvisoryService::affectsVersion('~nonsense~', '1.0.0'), 'malformed ranges affect everything (fail closed)');
    }

    public function test_signed_advisory_ingests_and_escalates_statuses(): void
    {
        $adv = $this->root->mintAdvisory(['action' => 'block_new', 'severity' => 'high']);
        $out = $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);
        self::assertSame('block_new', $out['action']);

        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        $rel = (new PackageReleaseRepository($this->db))->find($this->ids['release_id']);
        self::assertSame('blocked', $pkg['advisory_status']);
        self::assertSame('blocked', $rel['advisory_status']);

        $rev = $this->root->mintAdvisory(['action' => 'revoke', 'severity' => 'critical']);
        $out2 = $this->advisories()->ingest($this->ids['registry_id'], $rev['json'], $rev['signature'], $rev['key_id']);
        self::assertSame($out['advisory_id'], $out2['advisory_id']);
        self::assertSame('revoked', (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status']);

        $bad = $this->root->mintAdvisory(['advisory_uid' => 'RB-TEST-0002']);
        $this->expectException(\App\Security\Registry\RegistryVerificationException::class);
        $this->advisories()->ingest($this->ids['registry_id'], SigningHarness::tamper($bad['json']), $bad['signature'], $bad['key_id']);
    }

    public function test_acknowledge_records_actor_and_audit(): void
    {
        $adv = $this->root->mintAdvisory();
        $out = $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);

        $this->advisories()->acknowledge($this->admin, $out['advisory_id']);
        $row = (new PackageAdvisoryRepository($this->db))->findByUid('RB-TEST-0001');
        self::assertNotNull($row['acknowledged_at']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'advisory_ack'"));
    }

    public function test_blocklist_add_is_frictionless_and_remove_needs_reauth(): void
    {
        $bl = $this->blocklist();
        $digest = (string) (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['digest'];

        $blockId = $bl->block($this->admin, $digest, null, 'incident drill');
        self::assertTrue($bl->isBlocked($digest, null));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_block'"));

        try {
            $bl->unblock($this->admin, 'wrong-password', $blockId);
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertTrue($bl->isBlocked($digest, null));

        $bl->unblock($this->admin, 'password123', $blockId);
        self::assertFalse($bl->isBlocked($digest, null));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'package_unblock'"));

        try {
            $bl->block($this->admin, null, null, 'nothing targeted');
            self::fail('expected ValidationException');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('target', $e->errors);
        }
    }

    public function test_repair_recomputes_the_denormalized_columns(): void
    {
        $adv = $this->root->mintAdvisory(['action' => 'block_new']);
        $this->advisories()->ingest($this->ids['registry_id'], $adv['json'], $adv['signature'], $adv['key_id']);

        $this->db->run('UPDATE packages SET latest_release_id = NULL, advisory_status = \'none\' WHERE id = ?', [$this->ids['package_id']]);
        $this->db->run('UPDATE package_releases SET advisory_status = \'none\' WHERE id = ?', [$this->ids['release_id']]);

        $repair = new RepairService($this->db);
        self::assertGreaterThan(0, $repair->repairPackageLatestReleases());
        self::assertGreaterThan(0, $repair->repairPackageAdvisoryStatuses());

        $pkg = (new PackageRepository($this->db))->find($this->ids['package_id']);
        self::assertSame($this->ids['release_id'], (int) $pkg['latest_release_id']);
        self::assertSame('blocked', $pkg['advisory_status']);
        self::assertSame('blocked', (new PackageReleaseRepository($this->db))->find($this->ids['release_id'])['advisory_status']);
    }
}
