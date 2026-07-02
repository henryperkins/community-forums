<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\PackageIdentity;
use App\Security\Registry\TrustChainVerifier;
use App\Security\WriteGate;

/**
 * Trust-root lifecycle for package registries.
 *
 * Every high-impact mutation is WriteGate + password reauth + moderation audit.
 * Disabling a registry is the deliberate defensive exception and does not ask
 * for a password. Only public Ed25519 key material enters this service.
 */
final class RegistryTrustService
{
    public function __construct(
        private Database $db,
        private PackageRegistryRepository $registries,
        private RegistryTrustKeyRepository $trustKeys,
        private TrustChainVerifier $verifier,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    public function createRegistry(User $admin, string $currentPassword, string $sourceId, string $displayName, string $baseUrl): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $sourceId = trim($sourceId);
        $displayName = trim($displayName);
        $baseUrl = trim($baseUrl);
        $errors = [];

        if (!PackageIdentity::isValidSourceId($sourceId)) {
            $errors['source_id'] = 'Source id must be one lowercase label (a-z, 0-9, ., -, _).';
        } elseif ($this->registries->findBySourceId($sourceId) !== null) {
            $errors['source_id'] = 'A registry with this source id already exists.';
        }

        if ($displayName === '' || mb_strlen($displayName) > 190) {
            $errors['display_name'] = 'A display name between 1 and 190 characters is required.';
        }

        $scheme = strtolower((string) (parse_url($baseUrl, PHP_URL_SCHEME) ?? ''));
        if (strlen($baseUrl) > 512 || !in_array($scheme, ['https', 'http'], true) || parse_url($baseUrl, PHP_URL_HOST) === null) {
            $errors['base_url'] = 'Base URL must be a valid http(s) URL.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors, ['source_id' => $sourceId, 'display_name' => $displayName, 'base_url' => $baseUrl]);
        }

        return $this->db->transaction(function () use ($admin, $sourceId, $displayName, $baseUrl): int {
            $id = $this->registries->create($sourceId, $displayName, $baseUrl);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_create',
                'target_type' => 'registry',
                'target_id' => $id,
                'after' => ['source_id' => $sourceId, 'base_url' => $baseUrl],
            ]);

            return $id;
        });
    }

    /** Enabling is a trust decision; disabling is the defensive direction. */
    public function setEnabled(User $admin, ?string $currentPassword, int $registryId, bool $enabled): void
    {
        $this->writeGate->assertCanWrite($admin);
        if ($enabled) {
            $this->reauth->requirePassword($admin, (string) $currentPassword);
        }
        $registry = $this->requireRegistry($registryId);

        $this->db->transaction(function () use ($admin, $registry, $registryId, $enabled): void {
            $this->registries->setEnabled($registryId, $enabled);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $enabled ? 'registry_enable' : 'registry_disable',
                'target_type' => 'registry',
                'target_id' => $registryId,
                'before' => ['is_enabled' => (int) $registry['is_enabled']],
                'after' => ['is_enabled' => $enabled ? 1 : 0],
            ]);
        });
    }

    public function pinKey(
        User $admin,
        string $currentPassword,
        int $registryId,
        string $keyId,
        string $publicKeyBase64,
        ?string $validFrom,
        ?string $validUntil,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requireRegistry($registryId);

        $keyId = trim($keyId);
        $errors = [];
        if ($keyId === '' || mb_strlen($keyId) > 190) {
            $errors['key_id'] = 'A key id between 1 and 190 characters is required.';
        } elseif ($this->trustKeys->findKey($registryId, $keyId) !== null) {
            $errors['key_id'] = 'This key id is already pinned for the registry.';
        }

        $material = base64_decode(trim($publicKeyBase64), true);
        if ($material === false || strlen($material) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            $errors['public_key'] = 'Public key must be the base64 of exactly 32 Ed25519 public-key bytes.';
        }

        $validFrom = $this->normalizeDate($validFrom, $errors, 'valid_from');
        $validUntil = $this->normalizeDate($validUntil, $errors, 'valid_until');
        if ($errors !== []) {
            throw new ValidationException($errors, ['key_id' => $keyId, 'public_key' => $publicKeyBase64]);
        }

        return $this->db->transaction(function () use ($admin, $registryId, $keyId, $material, $validFrom, $validUntil): int {
            $rowId = $this->trustKeys->pin($registryId, $keyId, (string) $material, $validFrom, $validUntil);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_pin_key',
                'target_type' => 'registry',
                'target_id' => $registryId,
                'after' => ['key_id' => $keyId, 'fingerprint' => substr(hash('sha256', (string) $material), 0, 16)],
            ]);

            return $rowId;
        });
    }

    public function applyRotation(
        User $admin,
        string $currentPassword,
        int $registryId,
        string $documentJson,
        string $signature,
        string $keyId,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $this->requireRegistry($registryId);

        $successor = $this->verifier->verifyRotation(
            $documentJson,
            $signature,
            $keyId,
            $this->trustKeys->forRegistry($registryId),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        if ($this->trustKeys->findKey($registryId, $successor['key_id']) !== null) {
            throw new ValidationException(['rotation' => 'The successor key id is already pinned.']);
        }
        $oldRow = $this->trustKeys->findKey($registryId, $keyId);

        return $this->db->transaction(function () use ($admin, $registryId, $successor, $oldRow, $keyId): int {
            $rowId = $this->trustKeys->pin($registryId, $successor['key_id'], $successor['public_key'], null, null);
            if ($oldRow !== null) {
                $this->trustKeys->markRotated((int) $oldRow['id']);
            }
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_rotate_key',
                'target_type' => 'registry',
                'target_id' => $registryId,
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
        $key = $this->trustKeys->find($keyRowId);
        if ($key === null) {
            throw new ValidationException(['key' => 'Trust key not found.']);
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255) {
            throw new ValidationException(['reason' => 'A revocation reason between 1 and 255 characters is required.']);
        }

        $this->db->transaction(function () use ($admin, $key, $keyRowId, $reason): void {
            $this->trustKeys->revoke($keyRowId, $reason);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'registry_revoke_key',
                'target_type' => 'registry',
                'target_id' => (int) $key['registry_id'],
                'before' => ['key_id' => (string) $key['key_id'], 'status' => (string) $key['status']],
                'after' => ['status' => 'revoked', 'reason' => $reason],
            ]);
        });
    }

    /** @return array<string,mixed> */
    private function requireRegistry(int $registryId): array
    {
        $registry = $this->registries->find($registryId);
        if ($registry === null) {
            throw new ValidationException(['registry' => 'Registry not found.']);
        }

        return $registry;
    }

    /** @param array<string,string> $errors */
    private function normalizeDate(?string $value, array &$errors, string $field): ?string
    {
        $value = $value === null ? '' : trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            $errors[$field] = 'Use a UTC datetime (YYYY-MM-DD HH:MM:SS) or leave blank.';

            return null;
        }
    }
}
