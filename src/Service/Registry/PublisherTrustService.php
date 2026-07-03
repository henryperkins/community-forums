<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;
use App\Service\Packages\PackageHealthService;

/**
 * Local operator trust lifecycle for package publishers (P5-07-A). Verify,
 * suspend (cascading force-disable through the one enforcement engine), and
 * reinstate publishers; pin/rotate/revoke their public Ed25519 signing keys.
 * Every mutation is WriteGate + password reauth + moderation audit. Only public
 * key material ever reaches this service; the signing root stays operator-held.
 */
final class PublisherTrustService
{
    public function __construct(
        private Database $db,
        private PackagePublisherRepository $publishers,
        private PublisherSigningKeyRepository $keys,
        private PackageRepository $packages,
        private PackageTransparencyLogRepository $transparency,
        private TrustChainVerifier $verifier,
        private PackageHealthService $enforcement,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    public function verifyPublisher(User $admin, string $currentPassword, int $publisherId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $this->db->transaction(function () use ($admin, $publisher, $publisherId): void {
            $this->publishers->setStatus($publisherId, 'active');
            $this->publishers->markVerified($publisherId, $admin->id());
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_verify',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status'], 'verified_at' => $publisher['verified_at']],
                'after' => ['status' => 'active', 'verified' => true],
            ]);
        });
    }

    /** Suspending is the defensive escalation: set status, then force-disable every install of this publisher's packages through enforcePolicy(). */
    public function suspendPublisher(User $admin, string $currentPassword, int $publisherId, string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A suspension reason between 1 and 255 characters is required.']);
        }

        return $this->db->transaction(function () use ($admin, $publisher, $publisherId, $reason): int {
            $this->publishers->setStatus($publisherId, 'suspended');
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_suspend',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status']],
                'after' => ['status' => 'suspended', 'reason' => $reason],
            ]);

            // The one enforcement engine now sees the suspended publisher and
            // force-disables each enabled install (writing per-install force_disable
            // transparency + history + audit and driving the theme/integration seams).
            // It runs in this same transaction; any failure rolls back the
            // publisher status and every package state/credential change.
            return $this->enforcement->enforcePolicy();
        });
    }

    /** Reinstating clears the suspension but deliberately never auto-re-enables installs — the operator re-enables each explicitly. */
    public function reinstatePublisher(User $admin, string $currentPassword, int $publisherId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $publisher = $this->requirePublisher($publisherId);

        $this->db->transaction(function () use ($admin, $publisher, $publisherId): void {
            $this->publishers->setStatus($publisherId, 'active');
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_reinstate',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['status' => (string) $publisher['status']],
                'after' => ['status' => 'active', 'reenabled_installs' => false],
            ]);
        });
    }

    public function pinKey(User $admin, string $currentPassword, int $publisherId, string $keyId, string $publicKeyBase64, ?string $validFrom, ?string $validUntil): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requirePublisher($publisherId);

        $keyId = trim($keyId);
        $errors = [];
        if ($keyId === '' || mb_strlen($keyId) > 190) {
            $errors['key_id'] = 'A key id between 1 and 190 characters is required.';
        } elseif ($this->keys->findKey($publisherId, $keyId) !== null) {
            $errors['key_id'] = 'This key id is already pinned for the publisher.';
        }
        $material = base64_decode(trim($publicKeyBase64), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $errors['public_key'] = 'Public key must be the base64 of exactly 32 Ed25519 public-key bytes.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, ['key_id' => $keyId, 'public_key' => $publicKeyBase64]);
        }

        return $this->db->transaction(function () use ($admin, $publisherId, $keyId, $material, $validFrom, $validUntil): int {
            $rowId = $this->keys->pin($publisherId, $keyId, (string) $material, $validFrom, $validUntil);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_pin_key',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'after' => ['key_id' => $keyId, 'fingerprint' => substr(hash('sha256', (string) $material), 0, 16)],
            ]);

            return $rowId;
        });
    }

    public function applyKeyRotation(User $admin, string $currentPassword, int $publisherId, string $documentJson, string $signature, string $keyId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requirePublisher($publisherId);

        $successor = $this->verifier->verifyRotation(
            $documentJson,
            $signature,
            $keyId,
            $this->keys->forPublisher($publisherId),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($this->keys->findKey($publisherId, $successor['key_id']) !== null) {
            throw new ValidationException(['rotation' => 'The successor key id is already pinned.']);
        }
        $oldRow = $this->keys->findKey($publisherId, $keyId);

        return $this->db->transaction(function () use ($admin, $publisherId, $successor, $oldRow, $keyId): int {
            $rowId = $this->keys->pin($publisherId, $successor['key_id'], $successor['public_key'], null, null);
            if ($oldRow !== null) {
                $this->keys->markRotated((int) $oldRow['id']);
            }
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_rotate_key',
                'target_type' => 'publisher',
                'target_id' => $publisherId,
                'before' => ['key_id' => $keyId],
                'after' => ['key_id' => $successor['key_id']],
            ]);

            return $rowId;
        });
    }

    public function revokeKey(User $admin, string $currentPassword, int $keyRowId, string $reason): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $key = $this->keys->find($keyRowId);
        if ($key === null) {
            throw new ValidationException(['key' => 'Publisher signing key not found.']);
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A revocation reason between 1 and 255 characters is required.']);
        }

        $this->db->transaction(function () use ($admin, $key, $keyRowId, $reason): void {
            $this->keys->revoke($keyRowId, $reason);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'publisher_revoke_key',
                'target_type' => 'publisher',
                'target_id' => (int) $key['publisher_id'],
                'before' => ['key_id' => (string) $key['key_id'], 'status' => (string) $key['status']],
                'after' => ['status' => 'revoked', 'reason' => $reason],
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function requirePublisher(int $publisherId): array
    {
        $publisher = $this->publishers->find($publisherId);
        if ($publisher === null) {
            throw new ValidationException(['publisher' => 'Publisher not found.']);
        }

        return $publisher;
    }
}
