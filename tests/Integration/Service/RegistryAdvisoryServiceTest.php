<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\Registry\RegistryVerificationException;
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

    public function test_advisory_replay_with_older_issued_at_is_refused(): void
    {
        // A strong, recent advisory lands first and revokes the package.
        $recent = $this->root->mintAdvisory(['action' => 'revoke', 'severity' => 'critical', 'issued_at' => '2026-07-03T00:00:00Z']);
        $this->advisories()->ingest($this->ids['registry_id'], $recent['json'], $recent['signature'], $recent['key_id']);
        self::assertSame('revoked', (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status']);

        // Replaying an OLDER, weaker signed advisory for the same uid must be
        // refused — replay needs no key, only possession of the stale document,
        // and would otherwise downgrade the auto-block revoke -> blocked.
        $stale = $this->root->mintAdvisory(['action' => 'block_new', 'severity' => 'high', 'issued_at' => '2026-07-01T00:00:00Z']);
        try {
            $this->advisories()->ingest($this->ids['registry_id'], $stale['json'], $stale['signature'], $stale['key_id']);
            self::fail('expected the stale advisory to be refused as a replay');
        } catch (RegistryVerificationException) {
            self::assertTrue(true);
        }
        self::assertSame(
            'revoked',
            (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status'],
            'a refused replay must not downgrade the package advisory status',
        );
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

    public function test_a_second_registry_cannot_overwrite_or_target_another_registrys_advisory(): void
    {
        // rb-test owns RB-TEST-0001 with action=revoke.
        $owned = $this->root->mintAdvisory(['action' => 'revoke', 'severity' => 'critical']);
        $this->advisories()->ingest($this->ids['registry_id'], $owned['json'], $owned['signature'], $owned['key_id']);
        self::assertSame('revoked', (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status']);

        // A second, independently-trusted registry.
        $evilRoot = SigningHarness::generate('evil-root');
        $evilRegistryId = (new PackageRegistryRepository($this->db))->create('rb-evil', 'Evil Mirror', 'https://mirror.invalid');
        (new RegistryTrustKeyRepository($this->db))->pin($evilRegistryId, 'evil-root', $evilRoot->publicKey(), null, null);

        // (a) Re-signing the SAME advisory_uid to de-escalate is refused.
        $downgrade = $evilRoot->mintAdvisory(['action' => 'warn', 'severity' => 'low']);
        try {
            $this->advisories()->ingest($evilRegistryId, $downgrade['json'], $downgrade['signature'], 'evil-root');
            self::fail('expected advisory_registry_conflict');
        } catch (RegistryVerificationException $e) {
            self::assertSame('advisory_registry_conflict', $e->code);
        }

        // (b) A fresh advisory_uid targeting rb-test's package is also refused.
        $grief = $evilRoot->mintAdvisory(['advisory_uid' => 'RB-EVIL-0001', 'action' => 'revoke']);
        try {
            $this->advisories()->ingest($evilRegistryId, $grief['json'], $grief['signature'], 'evil-root');
            self::fail('expected advisory_package_conflict');
        } catch (RegistryVerificationException $e) {
            self::assertSame('advisory_package_conflict', $e->code);
        }

        // rb-test's revoke status is untouched by either attempt.
        self::assertSame('revoked', (new PackageRepository($this->db))->find($this->ids['package_id'])['advisory_status']);
    }
}
