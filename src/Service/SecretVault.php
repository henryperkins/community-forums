<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretRevokedException;
use App\Core\SecretsDisabledException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Security\SecretBox;

/**
 * Reversible-secret vault seam over SecretBox. Hands out opaque references;
 * plaintext is write-only, never logged/exported. Versioned rotation with a
 * grace window, revoke, and prune.
 *
 * Owns transactions: each multi-table mutation spans ServiceSecretRepository
 * and ModerationLogRepository in one Database::transaction() call.
 */
final class SecretVault
{
    public function __construct(
        private Database $db,
        private ServiceSecretRepository $secrets,
        private SecretBox $box,
        private ModerationLogRepository $log,
        private FeatureFlags $flags,
        private Config $config,
    ) {
    }

    public function store(string $ownerType, ?int $ownerId, string $label, string $plaintext, ?User $actor = null): string
    {
        $this->assertEnabled();
        $this->assertSize($plaintext);
        $ref = 'svcsec_' . bin2hex(random_bytes(16));
        $enc = $this->box->encrypt($plaintext);
        $actorId = $actor?->id();

        $this->db->transaction(function () use ($ref, $ownerType, $ownerId, $label, $enc, $actorId): void {
            $secretId = $this->secrets->insertSecret($ref, $ownerType, $ownerId, $label, $actorId);
            $this->secrets->insertCurrentVersion($secretId, 1, $enc);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_stored',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'after' => ['ref' => $ref, 'owner_type' => $ownerType, 'owner_id' => $ownerId, 'label' => $label, 'version' => 1],
            ]);
        });

        return $ref;
    }

    public function reveal(string $ref): string
    {
        $secret = $this->requireActive($ref);
        $row = $this->secrets->currentVersionRow((int) $secret['id']);
        if ($row === null) {
            throw new SecretNotFoundException('No current version for this secret reference.');
        }
        return $this->box->decrypt((string) $row['ciphertext'], (string) $row['nonce'], (string) $row['tag']);
    }

    public function rotate(string $ref, string $newPlaintext, ?User $actor = null, ?int $graceSeconds = null): int
    {
        $this->assertEnabled();
        $this->assertSize($newPlaintext);
        $grace = max(0, $graceSeconds ?? (int) $this->config->get('secrets.rotation_grace_seconds', 86400));
        $enc = $this->box->encrypt($newPlaintext);
        $actorId = $actor?->id();

        return $this->db->transaction(function () use ($ref, $enc, $grace, $actorId): int {
            $secret = $this->secrets->lockSecretByRef($ref);
            if ($secret === null) {
                throw new SecretNotFoundException('Unknown secret reference.');
            }
            if ((string) ($secret['status'] ?? '') === 'revoked') {
                throw new SecretRevokedException('This secret reference is revoked.');
            }
            $secretId = (int) $secret['id'];
            $newVersion = (int) $secret['latest_version'] + 1;
            $this->secrets->retireCurrentVersion($secretId, $grace);
            $this->secrets->insertCurrentVersion($secretId, $newVersion, $enc);
            $this->secrets->bumpLatestVersion($secretId, $newVersion);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_rotated',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'before' => ['version' => (int) $secret['latest_version']],
                'after' => ['version' => $newVersion, 'grace_seconds' => $grace],
            ]);
            return $newVersion;
        });
    }

    /** @return array<int,string> current + in-grace retired plaintexts, newest first */
    public function usableSecrets(string $ref): array
    {
        $secret = $this->requireActive($ref);
        return array_map(
            fn (array $r): string => $this->box->decrypt((string) $r['ciphertext'], (string) $r['nonce'], (string) $r['tag']),
            $this->secrets->usableVersionRows((int) $secret['id']),
        );
    }

    /** @return array<string,mixed> never contains plaintext or ciphertext */
    public function metadata(string $ref): array
    {
        $secret = $this->secrets->findSecretByRef($ref);
        if ($secret === null) {
            throw new SecretNotFoundException('Unknown secret reference.');
        }
        $secretId = (int) $secret['id'];
        $hasCurrent = $this->secrets->currentVersionRow($secretId) !== null;
        return [
            'ref' => (string) $secret['secret_ref'],
            'status' => (string) $secret['status'],
            'latest_version' => (int) $secret['latest_version'],
            'has_live_version' => $hasCurrent && (string) $secret['status'] === 'active',
            'owner_type' => (string) $secret['owner_type'],
            'owner_id' => $secret['owner_id'] === null ? null : (int) $secret['owner_id'],
            'label' => (string) $secret['label'],
            'version_count' => $this->secrets->versionCount($secretId),
            'created_at' => (string) $secret['created_at'],
            'updated_at' => (string) $secret['updated_at'],
            'revoked_at' => $secret['revoked_at'] === null ? null : (string) $secret['revoked_at'],
        ];
    }

    public function revoke(string $ref, ?User $actor = null): void
    {
        $actorId = $actor?->id();
        $this->db->transaction(function () use ($ref, $actorId): void {
            $secret = $this->secrets->lockSecretByRef($ref);
            if ($secret === null) {
                throw new SecretNotFoundException('Unknown secret reference.');
            }
            if ((string) ($secret['status'] ?? '') === 'revoked') {
                return;
            }
            $secretId = (int) $secret['id'];
            $this->secrets->markRevoked($secretId, $actorId);
            $this->secrets->retireAllVersions($secretId);
            $this->log->log([
                'actor_id' => $actorId,
                'action' => 'service_secret_revoked',
                'target_type' => 'service_secret',
                'target_id' => $secretId,
                'before' => ['status' => 'active'],
                'after' => ['status' => 'revoked'],
            ]);
        });
    }

    public function prune(int $limit = 100): int
    {
        $limit = max(1, $limit);
        if (!$this->secrets->acquirePruneLock()) {
            return 0;
        }
        try {
            $destroyed = 0;
            foreach ($this->secrets->pruneCandidates($limit) as $row) {
                $this->db->transaction(function () use ($row, &$destroyed): void {
                    if ($this->secrets->destroyVersion((int) $row['id']) !== 1) {
                        return;
                    }
                    $this->log->log([
                        'actor_id' => null,
                        'action' => 'service_secret_version_destroyed',
                        'target_type' => 'service_secret',
                        'target_id' => (int) $row['secret_id'],
                        'before' => ['version' => (int) $row['version'], 'state' => 'retired'],
                        'after' => ['version' => (int) $row['version'], 'state' => 'destroyed'],
                    ]);
                    $destroyed++;
                });
            }
            return $destroyed;
        } finally {
            $this->secrets->releasePruneLock();
        }
    }

    /** @return array<string,mixed> */
    private function requireActive(string $ref): array
    {
        $secret = $this->secrets->findSecretByRef($ref);
        if ($secret === null) {
            throw new SecretNotFoundException('Unknown secret reference.');
        }
        if ((string) ($secret['status'] ?? '') === 'revoked') {
            throw new SecretRevokedException('This secret reference is revoked.');
        }
        return $secret;
    }

    private function assertEnabled(): void
    {
        if (!$this->flags->enabled('service_secrets')) {
            throw new SecretsDisabledException('The service-secret store is disabled.');
        }
    }

    private function assertSize(string $plaintext): void
    {
        $max = (int) $this->config->get('secrets.max_secret_bytes', 4096);
        if (strlen($plaintext) > $max) {
            throw new ValidationException(['secret' => "Secret exceeds the {$max}-byte maximum."]);
        }
    }
}
