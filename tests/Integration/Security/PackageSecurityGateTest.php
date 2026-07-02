<?php

declare(strict_types=1);

namespace Tests\Integration\Security;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

final class PackageSecurityGateTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $seeded;

    /** @var array<string,mixed> */
    private array $package;

    /** @var array<string,mixed> */
    private array $release;

    private int $advisoryCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seeded = RegistryFixtures::seed($this->db, SigningHarness::generate());
        $this->package = (new PackageRepository($this->db))->find($this->seeded['package_id']);
        $this->release = (new PackageReleaseRepository($this->db))->find($this->seeded['release_id']);
    }

    private function gate(): PackageSecurityGate
    {
        return new PackageSecurityGate(
            new LocalPackageBlockRepository($this->db),
            new PackageAdvisoryRepository($this->db),
        );
    }

    private function assertGateRefusal(string $code, callable $call): void
    {
        try {
            $call();
            self::fail('expected package policy refusal ' . $code);
        } catch (PackagePolicyException $e) {
            self::assertSame($code, $e->code);
        }
    }

    private function seedAdvisory(string $action, ?string $digest = null, ?string $range = null): void
    {
        ++$this->advisoryCounter;
        (new PackageAdvisoryRepository($this->db))->upsert([
            'advisory_uid' => 'RB-TEST-' . strtoupper(str_replace('_', '-', $action)) . '-' . $this->advisoryCounter,
            'registry_id' => $this->seeded['registry_id'],
            'package_id' => $this->seeded['package_id'],
            'affected_version_range' => $range,
            'affected_digest' => $digest,
            'severity' => 'high',
            'action' => $action,
            'summary' => 'test advisory',
            'signed_evidence' => null,
            'issued_at' => '2026-07-01 00:00:00',
        ]);
    }

    public function test_approved_reviewed_release_passes_both_gates(): void
    {
        $this->gate()->assertInstallable($this->package, $this->release);
        $this->gate()->assertEnableable($this->package, $this->release);
        self::assertTrue(true);
    }

    public function test_locally_blocked_digest_and_uid_refuse_install_and_enable(): void
    {
        $blocks = new LocalPackageBlockRepository($this->db);
        $blocks->add($this->release['digest'], null, 'digest blocked', null);

        $this->assertGateRefusal('locally_blocked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('locally_blocked', fn () => $this->gate()->assertEnableable($this->package, $this->release));

        $blocks->add(null, $this->package['package_uid'], 'package blocked', null);
        $other = RegistryFixtures::seedRelease($this->db, SigningHarness::generate(), $this->seeded);
        $otherRelease = (new PackageReleaseRepository($this->db))->find($other['release_id']);

        $this->assertGateRefusal('locally_blocked', fn () => $this->gate()->assertInstallable($this->package, $otherRelease));
        $this->assertGateRefusal('locally_blocked', fn () => $this->gate()->assertEnableable($this->package, $otherRelease));
    }

    public function test_block_new_advisory_blocks_install_but_not_enable(): void
    {
        $this->seedAdvisory('block_new', null, '<=1.0.0');

        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->gate()->assertEnableable($this->package, $this->release);
        self::assertTrue(true);
    }

    public function test_force_disable_and_revoke_advisories_block_both(): void
    {
        $this->seedAdvisory('force_disable', null, '<=1.0.0');

        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate()->assertEnableable($this->package, $this->release));

        $this->seedAdvisory('revoke', null, '<=1.0.0');
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate()->assertEnableable($this->package, $this->release));
    }

    public function test_revoked_digest_refuses_install_and_enable_with_revoked_code(): void
    {
        $this->seedAdvisory('revoke', $this->release['digest'], null);

        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_revoked', fn () => $this->gate()->assertEnableable($this->package, $this->release));
    }

    public function test_advisory_with_no_digest_and_no_range_affects_every_version(): void
    {
        $this->seedAdvisory('force_disable');

        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate()->assertInstallable($this->package, $this->release));
        $this->assertGateRefusal('advisory_blocked', fn () => $this->gate()->assertEnableable($this->package, $this->release));
    }

    public function test_non_matching_advisory_does_not_block(): void
    {
        $this->seedAdvisory('revoke', null, '<1.0.0');

        $this->gate()->assertInstallable($this->package, $this->release);
        $this->gate()->assertEnableable($this->package, $this->release);
        self::assertTrue(true);
    }

    public function test_unapproved_review_refuses(): void
    {
        $this->db->run('UPDATE package_releases SET review_status = ? WHERE id = ?', ['rejected', $this->seeded['release_id']]);
        $release = (new PackageReleaseRepository($this->db))->find($this->seeded['release_id']);

        $this->assertGateRefusal('review_not_approved', fn () => $this->gate()->assertInstallable($this->package, $release));
        $this->assertGateRefusal('review_not_approved', fn () => $this->gate()->assertEnableable($this->package, $release));
    }

    public function test_gate_a_type_and_trust_class_policy(): void
    {
        $package = $this->package;
        $package['type'] = 'plugin';
        $this->assertGateRefusal('type_forbidden', fn () => $this->gate()->assertInstallable($package, $this->release));

        $this->db->run('UPDATE packages SET trust_class = ? WHERE id = ?', ['local_dev', $this->seeded['package_id']]);
        $package = (new PackageRepository($this->db))->find($this->seeded['package_id']);
        $this->assertGateRefusal('trust_class_forbidden', fn () => $this->gate()->assertEnableable($package, $this->release));
    }
}
