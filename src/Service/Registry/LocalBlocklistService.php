<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageRepository;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Packages\PackageHealthService;

/**
 * Registry-independent local emergency brake. Adding a block is deliberately
 * friction-free; removing one needs reauth because it re-enables eligibility.
 */
final class LocalBlocklistService
{
    public function __construct(
        private LocalPackageBlockRepository $blocks,
        private PackageRepository $packages,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private ?PackageHealthService $enforcement = null,
    ) {
    }

    public function block(User $admin, ?string $digest, ?string $packageUid, ?string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $digest = $digest === null ? null : strtolower(trim($digest));
        $packageUid = $packageUid === null ? null : trim($packageUid);
        $digest = $digest === '' ? null : $digest;
        $packageUid = $packageUid === '' ? null : $packageUid;

        $errors = [];
        if ($digest === null && $packageUid === null) {
            $errors['target'] = 'Block a release digest, a package uid, or both.';
        }
        if ($digest !== null && preg_match('/^[0-9a-f]{64}$/', $digest) !== 1) {
            $errors['digest'] = 'Digest must be 64 hex characters (sha256).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, [
                'digest' => (string) $digest,
                'package_uid' => (string) $packageUid,
                'reason' => (string) $reason,
            ]);
        }

        $package = $packageUid === null ? null : $this->packages->findByUid($packageUid);
        $cleanReason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255);
        $blockId = $this->blocks->add($digest, $packageUid, $cleanReason, $admin->id());
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => 'package_block',
            'target_type' => 'package',
            'target_id' => $package === null ? 0 : (int) $package['id'],
            'after' => ['digest' => $digest, 'package_uid' => $packageUid],
            'reason' => $cleanReason,
        ]);
        $this->enforcement?->enforcePolicy();

        return $blockId;
    }

    public function unblock(User $admin, string $currentPassword, int $blockId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $block = $this->blocks->find($blockId);
        if ($block === null) {
            throw new ValidationException(['block' => 'Blocklist entry not found.']);
        }

        $package = isset($block['package_uid']) && $block['package_uid'] !== null
            ? $this->packages->findByUid((string) $block['package_uid'])
            : null;
        $this->blocks->remove($blockId);
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => 'package_unblock',
            'target_type' => 'package',
            'target_id' => $package === null ? 0 : (int) $package['id'],
            'before' => ['digest' => $block['digest'], 'package_uid' => $block['package_uid']],
        ]);
    }

    public function isBlocked(?string $digest, ?string $packageUid): bool
    {
        return $this->blocks->isBlocked($digest, $packageUid);
    }
}
