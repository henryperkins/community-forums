<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Registry\RegistryTrustService;
use Tests\Support\Phase5\RegistryFixtures;
use Tests\Support\Phase5\SigningHarness;
use Tests\Support\TestCase;

/** TM-SC-04 end-to-end: only a transition signed by the active root can rotate. */
final class RegistryTrustServiceTest extends TestCase
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

    private function service(): RegistryTrustService
    {
        return new RegistryTrustService(
            $this->db,
            new PackageRegistryRepository($this->db),
            new RegistryTrustKeyRepository($this->db),
            new TrustChainVerifier(),
            $this->reauthGate(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );
    }

    private function reauthGate(): ReauthGate
    {
        return new ReauthGate(new PasswordHasher());
    }

    private function auditCount(string $action): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_log WHERE action = ?', [$action]);
    }

    public function test_create_enable_and_disable_a_registry(): void
    {
        $svc = $this->service();
        $id = $svc->createRegistry($this->admin, 'password123', 'rb-main', 'Main Registry', 'https://registry.example');
        $registries = new PackageRegistryRepository($this->db);
        self::assertSame(0, (int) $registries->find($id)['is_enabled']);

        $svc->setEnabled($this->admin, 'password123', $id, true);
        self::assertSame(1, (int) $registries->find($id)['is_enabled']);

        $svc->setEnabled($this->admin, null, $id, false);
        self::assertSame(0, (int) $registries->find($id)['is_enabled']);
        self::assertSame(1, $this->auditCount('registry_create'));
        self::assertSame(1, $this->auditCount('registry_enable'));
        self::assertSame(1, $this->auditCount('registry_disable'));

        foreach ([['rb main', 'https://x.example'], ['rb-main', 'https://dup.example'], ['rb-two', 'ftp://x.example']] as [$sourceId, $url]) {
            try {
                $svc->createRegistry($this->admin, 'password123', $sourceId, 'X', $url);
                self::fail("expected ValidationException for $sourceId $url");
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_reauth_gates_every_trust_mutation(): void
    {
        $svc = $this->service();
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);

        foreach ([
            fn () => $svc->createRegistry($this->admin, 'wrong', 'rb-x', 'X', 'https://x.example'),
            fn () => $svc->setEnabled($this->admin, 'wrong', $this->ids['registry_id'], true),
            fn () => $svc->pinKey($this->admin, 'wrong', $this->ids['registry_id'], 'k2', base64_encode($successor->publicKey()), null, null),
            fn () => $svc->applyRotation($this->admin, 'wrong', $this->ids['registry_id'], $rotation['json'], $rotation['signature'], $rotation['key_id']),
            fn () => $svc->revokeKey($this->admin, 'wrong', $this->ids['trust_key_id'], 'drill'),
        ] as $attempt) {
            try {
                $attempt();
                self::fail('expected ValidationException (reauth)');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('current_password', $e->errors);
            }
        }
    }

    public function test_pin_key_validates_material_and_uniqueness(): void
    {
        $svc = $this->service();
        $fresh = SigningHarness::generate('root-2');
        $keyRowId = $svc->pinKey($this->admin, 'password123', $this->ids['registry_id'], 'root-2', base64_encode($fresh->publicKey()), null, null);
        $row = (new RegistryTrustKeyRepository($this->db))->find($keyRowId);
        self::assertSame('active', $row['status']);
        self::assertSame($fresh->publicKey(), $row['public_key']);
        self::assertSame(1, $this->auditCount('registry_pin_key'));

        foreach ([
            ['root-3', base64_encode('too-short')],
            ['root-3', 'not-base64!!'],
            ['root-1', base64_encode($fresh->publicKey())],
            ['', base64_encode($fresh->publicKey())],
        ] as [$keyId, $material]) {
            try {
                $svc->pinKey($this->admin, 'password123', $this->ids['registry_id'], $keyId, $material, null, null);
                self::fail("expected ValidationException for key '$keyId'");
            } catch (ValidationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_signed_rotation_pins_the_successor_and_retires_the_old_key(): void
    {
        $svc = $this->service();
        $successor = SigningHarness::generate('root-2');
        $rotation = $this->root->mintRotation($successor);

        $newRowId = $svc->applyRotation($this->admin, 'password123', $this->ids['registry_id'], $rotation['json'], $rotation['signature'], $rotation['key_id']);

        $keys = new RegistryTrustKeyRepository($this->db);
        self::assertSame('active', $keys->find($newRowId)['status']);
        self::assertSame('root-2', $keys->find($newRowId)['key_id']);
        self::assertSame('rotated', $keys->find($this->ids['trust_key_id'])['status']);
        self::assertSame(1, $this->auditCount('registry_rotate_key'));
    }

    public function test_forged_rotation_is_rejected_and_pins_nothing(): void
    {
        $svc = $this->service();
        $attacker = SigningHarness::generate('evil-1');
        $forged = $attacker->mintRotation(SigningHarness::generate('root-2'));

        try {
            $svc->applyRotation($this->admin, 'password123', $this->ids['registry_id'], $forged['json'], $forged['signature'], $forged['key_id']);
            self::fail('expected RegistryVerificationException');
        } catch (RegistryVerificationException $e) {
            self::assertSame('unknown_key', $e->code);
        }
        self::assertNull((new RegistryTrustKeyRepository($this->db))->findKey($this->ids['registry_id'], 'root-2'));
        self::assertSame(0, $this->auditCount('registry_rotate_key'));
    }

    public function test_revoked_key_fails_verification_afterwards(): void
    {
        $svc = $this->service();
        $svc->revokeKey($this->admin, 'password123', $this->ids['trust_key_id'], 'compromise drill');

        $snap = $this->root->mintSnapshot();
        try {
            (new TrustChainVerifier())->verify(
                $snap['json'],
                $snap['signature'],
                'root-1',
                (new RegistryTrustKeyRepository($this->db))->forRegistry($this->ids['registry_id']),
                'rb-registry-snapshot.v1',
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
            self::fail('expected revoked_key');
        } catch (RegistryVerificationException $e) {
            self::assertSame('revoked_key', $e->code);
        }
        self::assertSame(1, $this->auditCount('registry_revoke_key'));
    }
}
