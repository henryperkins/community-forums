<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Service\Registry\RegistryAdvisoryService;

final class PackageSecurityGate
{
    private const INSTALLABLE_TYPES = ['theme', 'automation', 'remote_app'];
    private const INSTALLABLE_TRUST_CLASSES = ['reviewed_declarative', 'reviewed_remote'];

    public function __construct(
        private LocalPackageBlockRepository $blocks,
        private PackageAdvisoryRepository $advisories,
    ) {
    }

    /**
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     */
    public function assertInstallable(array $package, array $release): void
    {
        $this->assertTypePolicy($package);
        $this->assertNotLocallyBlocked($package, $release);
        $this->assertAdvisoriesAllow($package, $release, ['block_new', 'force_disable', 'revoke']);
        $this->assertReviewApproved($release);
    }

    /**
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     */
    public function assertEnableable(array $package, array $release): void
    {
        $this->assertTypePolicy($package);
        $this->assertNotLocallyBlocked($package, $release);
        $this->assertAdvisoriesAllow($package, $release, ['force_disable', 'revoke']);
        $this->assertReviewApproved($release);
    }

    /** @param array<string,mixed> $package */
    private function assertTypePolicy(array $package): void
    {
        $type = (string) ($package['type'] ?? '');
        if (!in_array($type, self::INSTALLABLE_TYPES, true)) {
            throw new PackagePolicyException('type_forbidden', 'This package type cannot be installed in Gate A.');
        }

        $trustClass = (string) ($package['trust_class'] ?? '');
        if (!in_array($trustClass, self::INSTALLABLE_TRUST_CLASSES, true)) {
            throw new PackagePolicyException('trust_class_forbidden', 'This package trust class cannot be installed in Gate A.');
        }
    }

    /**
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     */
    private function assertNotLocallyBlocked(array $package, array $release): void
    {
        $digest = isset($release['digest']) ? (string) $release['digest'] : null;
        $packageUid = isset($package['package_uid']) ? (string) $package['package_uid'] : null;

        if ($this->blocks->isBlocked($digest, $packageUid)) {
            throw new PackagePolicyException('locally_blocked', 'This package or release is locally blocked.');
        }
    }

    /**
     * @param array<string,mixed> $package
     * @param array<string,mixed> $release
     * @param array<int,string> $blockingActions
     */
    private function assertAdvisoriesAllow(array $package, array $release, array $blockingActions): void
    {
        foreach ($this->advisories->forPackage((int) ($package['id'] ?? 0)) as $advisory) {
            $action = (string) ($advisory['action'] ?? '');
            if (!in_array($action, $blockingActions, true) || !$this->advisoryMatches($advisory, $release)) {
                continue;
            }

            if ($action === 'revoke') {
                throw new PackagePolicyException('advisory_revoked', 'This release has been revoked by advisory.');
            }

            throw new PackagePolicyException('advisory_blocked', 'This release is blocked by advisory.');
        }
    }

    /**
     * @param array<string,mixed> $advisory
     * @param array<string,mixed> $release
     */
    private function advisoryMatches(array $advisory, array $release): bool
    {
        $digest = (string) ($release['digest'] ?? '');
        $affectedDigest = isset($advisory['affected_digest']) ? (string) $advisory['affected_digest'] : '';
        if ($affectedDigest !== '') {
            return hash_equals($affectedDigest, $digest);
        }

        $range = isset($advisory['affected_version_range']) ? (string) $advisory['affected_version_range'] : null;
        return RegistryAdvisoryService::affectsRelease([
            'affected_digest' => $affectedDigest,
            'affected_version_range' => $range,
        ], $digest, isset($release['version']) ? (string) $release['version'] : null);
    }

    /** @param array<string,mixed> $release */
    private function assertReviewApproved(array $release): void
    {
        if (($release['review_status'] ?? null) !== 'approved') {
            throw new PackagePolicyException('review_not_approved', 'This release has not been approved for installation.');
        }
    }
}
