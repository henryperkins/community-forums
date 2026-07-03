<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Local operator review console (P5-07-A / GA-DOD-09). Surfaces the signed
 * review evidence PackageAcquisitionService cached and records LOCAL manual
 * decisions bound to the exact package/release/version/digest. Local decisions
 * may only TIGHTEN: a local 'approved' cannot override a signed reject/revoke of
 * the same digest. Every mutation is WriteGate + password reauth + audit +
 * transparency, in one transaction.
 */
final class PackageReviewConsoleService
{
    private const DECISIONS = ['approved', 'rejected', 'revoked'];

    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageTransparencyLogRepository $transparency,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
    ) {
    }

    /** @return array<int,array<string,mixed>> cached signed + local decisions, newest first. */
    public function decisionsFor(int $packageId): array
    {
        return $this->reviewDecisions->forPackage($packageId);
    }

    public function recordDecision(
        User $admin,
        string $currentPassword,
        int $packageId,
        int $releaseId,
        string $decision,
        ?string $note,
    ): int {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        if (!in_array($decision, self::DECISIONS, true)) {
            throw new ValidationException(['decision' => 'Choose approved, rejected, or revoked.']);
        }

        $package = $this->packages->find($packageId);
        if ($package === null) {
            throw new ValidationException(['package' => 'Package not found.']);
        }

        // Exact binding: the decision must name a release that belongs to this package.
        $release = $this->releases->find($releaseId);
        if ($release === null || (int) $release['package_id'] !== $packageId) {
            throw new ValidationException(['release_id' => 'Select a release of this package.']);
        }

        $note = $note === null ? '' : trim($note);
        if (mb_strlen($note) > 1000) {
            throw new ValidationException(['note' => 'Keep the review note under 1000 characters.'], ['note' => $note]);
        }

        $digest = (string) $release['digest'];
        $version = (string) $release['version'];

        // Tightening only: a local approval cannot override a signed reject/revoke of the same digest.
        if ($decision === 'approved' && $this->signedNegativeExists($packageId, $digest)) {
            throw new PackagePolicyException(
                'review_conflict',
                'A signed rejection or revocation already covers this digest; a local approval cannot override it.',
            );
        }

        return $this->db->transaction(function () use ($admin, $package, $packageId, $releaseId, $digest, $version, $decision, $note): int {
            $decisionId = $this->reviewDecisions->record([
                'package_id' => $packageId,
                'release_id' => $releaseId,
                'version' => $version,
                'digest' => $digest,
                'decision' => $decision,
                'decided_at' => gmdate('Y-m-d H:i:s'),
                'source' => 'local',
                'evidence_json' => json_encode([
                    'source' => 'local',
                    'reviewer_id' => $admin->id(),
                    'reviewer' => $admin->username(),
                    'note' => $note,
                    'decided_at' => gmdate('c'),
                ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);

            $this->releases->setReviewStatus($releaseId, $decision);

            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => $version,
                'digest' => $digest,
                'event' => $decision === 'approved' ? 'release_verified' : 'revoked',
                'source' => 'local',
                'actor_id' => $admin->id(),
                'detail' => 'local_review=' . $decision,
            ]);

            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_review',
                'target_type' => 'package',
                'target_id' => $packageId,
                'after' => ['release_id' => $releaseId, 'digest' => $digest, 'decision' => $decision, 'source' => 'local'],
            ]);

            return $decisionId;
        });
    }

    /** True when a non-local (signed) reject/revoke already covers the exact digest. */
    private function signedNegativeExists(int $packageId, string $digest): bool
    {
        foreach ($this->reviewDecisions->forPackage($packageId) as $row) {
            if ((string) $row['digest'] === $digest
                && (string) $row['source'] !== 'local'
                && in_array((string) $row['decision'], ['rejected', 'revoked'], true)
            ) {
                return true;
            }
        }

        return false;
    }
}
