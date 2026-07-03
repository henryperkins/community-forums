<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageHealthService;
use App\Service\Registry\PublisherTrustService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** GA-DOD-09 publisher/key lifecycle + publisher-compromise cascade. */
final class PublisherTrustServiceTest extends TestCase
{
    private SigningHarness $root;
    private User $admin;
    /** @var array{registry_id:int,trust_key_id:int,publisher_id:int,package_id:int,release_id:int,release_digest:string,release_document:string} */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = SigningHarness::generate('pub-root-1');
        $this->ids = RegistryFixtures::seed($this->db, $this->root);
        $this->admin = User::fromRow($this->makeAdmin(['password' => 'password123']));
    }

    private function service(): PublisherTrustService
    {
        return new PublisherTrustService(
            $this->db,
            new PackagePublisherRepository($this->db),
            new PublisherSigningKeyRepository($this->db),
            new PackageRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new TrustChainVerifier(),
            $this->health(),
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function health(): PackageHealthService
    {
        return new PackageHealthService(
            $this->db,
            new InstalledPackageRepository($this->db),
            new InstalledPackagePermissionRepository($this->db),
            new PackageRepository($this->db),
            new PackageReleaseRepository($this->db),
            new PackageAdvisoryRepository($this->db),
            new LocalPackageBlockRepository($this->db),
            new PackageHistoryRepository($this->db),
            new PackageTransparencyLogRepository($this->db),
            new PackageArtifactStore(sys_get_temp_dir()),
            new ModerationLogRepository($this->db),
        );
    }

    private function enabledInstall(): int
    {
        return (int) $this->db->insert(
            "INSERT INTO installed_packages (package_id, release_id, digest, publisher_id, trust_class, review_status, state, installed_at)
             VALUES (?, ?, ?, ?, 'reviewed_declarative', 'approved', 'enabled', UTC_TIMESTAMP())",
            [$this->ids['package_id'], $this->ids['release_id'], $this->ids['release_digest'], $this->ids['publisher_id']],
        );
    }

    private function auditCount(string $action): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = ? AND target_type = 'publisher'",
            [$action],
        );
    }

    public function test_pin_rotate_and_revoke_walk_the_key_status_machine(): void
    {
        $svc = $this->service();
        $keyRow = $svc->pinKey($this->admin, 'password123', $this->ids['publisher_id'], 'pub-root-1', base64_encode($this->root->publicKey()), null, null);
        $keys = new PublisherSigningKeyRepository($this->db);
        self::assertSame('active', (string) $keys->find($keyRow)['status']);

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        $newRow = $svc->applyKeyRotation($this->admin, 'password123', $this->ids['publisher_id'], $rotation['json'], $rotation['signature'], 'pub-root-1');

        self::assertSame('rotated', (string) $keys->find($keyRow)['status']);
        self::assertSame('active', (string) $keys->find($newRow)['status']);
        self::assertSame('pub-root-2', (string) $keys->find($newRow)['key_id']);

        $svc->revokeKey($this->admin, 'password123', $newRow, 'suspected compromise');
        self::assertSame('revoked', (string) $keys->find($newRow)['status']);
        self::assertSame(1, $this->auditCount('publisher_pin_key'));
        self::assertSame(1, $this->auditCount('publisher_rotate_key'));
        self::assertSame(1, $this->auditCount('publisher_revoke_key'));
    }

    public function test_forged_rotation_is_refused_and_pins_nothing(): void
    {
        $svc = $this->service();
        $svc->pinKey($this->admin, 'password123', $this->ids['publisher_id'], 'pub-root-1', base64_encode($this->root->publicKey()), null, null);

        $successor = SigningHarness::generate('pub-root-2');
        $rotation = $this->root->mintRotation($successor);
        try {
            $svc->applyKeyRotation($this->admin, 'password123', $this->ids['publisher_id'], $rotation['json'], SigningHarness::tamper($rotation['signature']), 'pub-root-1');
            self::fail('forged rotation should be refused');
        } catch (RegistryVerificationException $e) {
            self::assertSame('bad_signature', $e->code);
        }
        $keyIds = array_map(static fn (array $r): string => (string) $r['key_id'], (new PublisherSigningKeyRepository($this->db))->forPublisher($this->ids['publisher_id']));
        self::assertSame(['pub-root-1'], $keyIds);
    }

    public function test_suspend_force_disables_installs_and_reinstate_does_not_revive_them(): void
    {
        $installId = $this->enabledInstall();
        $svc = $this->service();

        $affected = $svc->suspendPublisher($this->admin, 'password123', $this->ids['publisher_id'], 'signing key exfiltrated');
        self::assertGreaterThanOrEqual(1, $affected);
        self::assertSame('suspended', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
        self::assertSame(1, $this->auditCount('publisher_suspend'));
        self::assertGreaterThanOrEqual(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM package_transparency_log WHERE package_uid = 'acme/midnight-theme' AND event = 'force_disable'",
        ));

        $svc->reinstatePublisher($this->admin, 'password123', $this->ids['publisher_id']);
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('disabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]), 'reinstate must not silently re-enable');
    }

    public function test_wrong_password_changes_nothing(): void
    {
        $installId = $this->enabledInstall();
        try {
            $this->service()->suspendPublisher($this->admin, 'wrong-password', $this->ids['publisher_id'], 'x');
            self::fail('reauth should refuse');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertSame('active', (string) (new PackagePublisherRepository($this->db))->find($this->ids['publisher_id'])['status']);
        self::assertSame('enabled', (string) $this->db->fetchValue('SELECT state FROM installed_packages WHERE id = ?', [$installId]));
    }

    public function test_verify_marks_publisher_active_and_verified(): void
    {
        $id = (int) $this->db->insert(
            "INSERT INTO package_publishers (publisher_uid, display_name, status) VALUES ('acme/pending', 'Pending Publisher', 'suspended')",
        );
        $this->service()->verifyPublisher($this->admin, 'password123', $id);
        $row = (new PackagePublisherRepository($this->db))->find($id);
        self::assertSame('active', (string) $row['status']);
        self::assertNotNull($row['verified_at']);
        self::assertSame(1, $this->auditCount('publisher_verify'));
    }
}
