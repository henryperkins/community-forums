<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\Registry\RegistryVerificationException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Install, consent, enable, and disable lifecycle state machine.
 *
 * Install follows validate-first discipline: fetch, crypto, manifest, policy,
 * and compatibility checks complete before any install row is written. Refused
 * installs record the exact failed stage for operator evidence.
 */
final class PackageLifecycleService
{
    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageRegistryRepository $registries,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private PackageReviewDecisionRepository $reviewDecisions,
        private PackageAcquisitionService $acquisition,
        private PackageSecurityGate $gate,
        private PackageArtifactStore $artifacts,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private int $retentionDays = 30,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /**
     * @return array{
     *   package:array<string,mixed>,release:array<string,mixed>,registry:?array<string,mixed>,
     *   manifest:?PackageManifest,permissions:list<array<string,mixed>>,compatible:?bool,
     *   installed:?array<string,mixed>,refusal:?array{code:string,message:string},warnings:list<string>
     * }
     */
    public function plan(User $admin, int $packageId, ?int $releaseId = null): array
    {
        [$package, $release, $registry] = $this->resolveTarget($packageId, $releaseId);
        $installed = $this->installs->findByPackage($packageId);
        $manifest = null;
        $refusal = null;
        $warnings = [];

        try {
            if ($registry === null) {
                throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
            }
            $manifest = $this->acquisition->ensureVerified($registry, $package, $release);
            $release = $this->releases->find((int) $release['id']) ?? $release;
            $this->gate->assertInstallable($package, $release);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            $refusal = ['code' => $this->exceptionCode($e), 'message' => $e->getMessage()];
        }

        if (($package['advisory_status'] ?? 'none') === 'warned' || ($release['advisory_status'] ?? 'none') === 'warned') {
            $warnings[] = 'An advisory warns about this package. Review the trust console before installing.';
        }

        return [
            'package' => $package,
            'release' => $release,
            'registry' => $registry,
            'manifest' => $manifest,
            'permissions' => $manifest?->permissions ?? [],
            'compatible' => $manifest?->coreCompatible(),
            'installed' => $installed,
            'refusal' => $refusal,
            'warnings' => $warnings,
        ];
    }

    public function install(User $admin, string $currentPassword, int $packageId, ?int $releaseId = null): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        [$package, $release, $registry] = $this->resolveTarget($packageId, $releaseId);
        $existing = $this->installs->findByPackage($packageId);
        if ($existing !== null && $existing['state'] !== 'uninstalled') {
            throw new PackagePolicyException('already_installed', 'This package already has a local installation.');
        }

        $stage = 'acquire';
        try {
            if ($registry === null) {
                throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
            }

            $manifest = $this->acquisition->ensureVerified($registry, $package, $release);
            $release = $this->releases->find((int) $release['id']) ?? $release;

            $stage = 'policy';
            $this->gate->assertInstallable($package, $release);

            $stage = 'compatibility';
            if (!$manifest->coreCompatible()) {
                throw new PackagePolicyException('incompatible_core', 'This release does not support the running core version.');
            }

            $stage = 'persist';
            $row = [
                'package_id' => (int) $package['id'],
                'release_id' => (int) $release['id'],
                'digest' => (string) $release['digest'],
                'source_registry_id' => (int) $registry['id'],
                'publisher_id' => $package['publisher_id'] !== null ? (int) $package['publisher_id'] : null,
                'trust_class' => (string) $package['trust_class'],
                'review_status' => (string) $release['review_status'],
                'compat_min' => $manifest->coreMin,
                'compat_max' => $manifest->coreMax,
                'installed_by' => $admin->id(),
            ];

            $installedId = $this->db->transaction(function () use ($existing, $row, $package, $release, $manifest, $admin): int {
                if ($existing !== null) {
                    $installedId = (int) $existing['id'];
                    $this->installs->reviveForInstall($installedId, $row);
                } else {
                    $installedId = $this->installs->create($row);
                }

                $this->permissions->replaceDeclared($installedId, $this->permissionRows($manifest));
                $this->history->record([
                    'package_id' => (int) $package['id'],
                    'installed_package_id' => $installedId,
                    'event' => 'install',
                    'actor_id' => $admin->id(),
                    'new_version' => (string) $release['version'],
                    'new_digest' => (string) $release['digest'],
                    'permission_snapshot_json' => json_encode($manifest->permissions, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
                $this->transparency->record([
                    'package_uid' => (string) $package['package_uid'],
                    'version' => (string) $release['version'],
                    'digest' => (string) $release['digest'],
                    'event' => 'install',
                    'source' => 'local',
                    'actor_id' => $admin->id(),
                    'registry_id' => $row['source_registry_id'],
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_install',
                    'target_type' => 'package',
                    'target_id' => (int) $package['id'],
                    'after' => ['version' => (string) $release['version'], 'digest' => (string) $release['digest']],
                ]);

                return $installedId;
            });
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            $this->history->record([
                'package_id' => (int) $package['id'],
                'installed_package_id' => $existing !== null ? (int) $existing['id'] : null,
                'event' => 'install',
                'actor_id' => $admin->id(),
                'new_version' => (string) $release['version'],
                'new_digest' => (string) $release['digest'],
                'failure_stage' => $stage,
                'detail' => $this->exceptionCode($e),
            ]);
            $this->telemetry?->emit('package.install', [
                'package' => (string) $package['package_uid'],
                'result' => 'refused',
                'stage' => $stage,
                'reason' => $this->exceptionCode($e),
            ]);
            throw $e;
        }

        $this->telemetry?->emit('package.install', [
            'package' => (string) $package['package_uid'],
            'version' => (string) $release['version'],
            'digest' => (string) $release['digest'],
            'result' => 'installed',
        ]);

        return $installedId;
    }

    public function consent(User $admin, string $currentPassword, int $installedId): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'enabled', 'disabled']);
        if ($install['staged_release_id'] !== null) {
            throw new PackagePolicyException('stage_pending', 'A staged update is awaiting re-consent; approve or cancel it instead.');
        }
        if ($this->permissions->ungrantedCount($installedId) === 0) {
            throw new PackagePolicyException('invalid_state', 'There are no pending permission grants.');
        }
        $package = $this->packages->find((int) $install['package_id']);

        $granted = $this->db->transaction(function () use ($installedId, $install, $admin): int {
            $granted = $this->permissions->grantAll($installedId, $admin->id());
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'consent',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
                'permission_snapshot_json' => json_encode($this->permissions->forInstall($installedId), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_consent',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'after' => ['granted' => $granted],
            ]);

            return $granted;
        });

        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'consent',
            'package' => (string) ($package['package_uid'] ?? ''),
            'granted' => $granted,
        ]);

        return $granted;
    }

    public function enable(User $admin, string $currentPassword, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'disabled']);
        if ($this->permissions->ungrantedCount($installedId) > 0) {
            throw new PackagePolicyException('not_consented', 'Declared permissions are not consented yet; review and grant them first.');
        }

        $package = $this->packages->find((int) $install['package_id']);
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        if ($package === null || $release === null) {
            throw new PackagePolicyException('invalid_state', 'The installed release is no longer resolvable.');
        }

        $this->gate->assertEnableable($package, $release);

        $digest = (string) $install['digest'];
        if (!$this->artifacts->has($digest)) {
            throw new PackagePolicyException('artifact_missing', 'The verified artifact is missing from the local store.');
        }
        if (!$this->artifacts->verify($digest)) {
            $this->quarantine($install, $package, $admin->id(), 'digest mismatch on enable');
            throw new PackagePolicyException('artifact_tampered', 'Installed bytes no longer match the reviewed digest; the package was quarantined.');
        }

        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->setState($installedId, 'enabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'enable',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_enable',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
            ]);
        });

        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'enable',
            'package' => (string) $package['package_uid'],
        ]);
    }

    public function disable(User $admin, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);

        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['enabled']);
        $package = $this->packages->find((int) $install['package_id']);

        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->setState($installedId, 'disabled');
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'disable',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_disable',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
            ]);
        });

        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'disable',
            'package' => (string) ($package['package_uid'] ?? ''),
        ]);
    }

    public function setPinned(User $admin, int $installedId, bool $pinned): void
    {
        $this->writeGate->assertCanWrite($admin);

        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'enabled', 'disabled', 'quarantined']);

        $this->db->transaction(function () use ($install, $installedId, $pinned, $admin): void {
            $this->installs->setPinned($installedId, $pinned);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => $pinned ? 'pin' : 'unpin',
                'actor_id' => $admin->id(),
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $pinned ? 'package_pin' : 'package_unpin',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
            ]);
        });
    }

    public function setUpdatePolicy(User $admin, int $installedId, string $policy): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!in_array($policy, ['manual', 'notify'], true)) {
            throw new PackagePolicyException('update_policy', 'Update policy must be manual or notify.');
        }

        $install = $this->requireInstall($installedId);
        $this->assertState($install, ['installed', 'enabled', 'disabled', 'quarantined']);

        $this->db->transaction(function () use ($install, $installedId, $policy, $admin): void {
            $this->installs->setUpdatePolicy($installedId, $policy);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_update_policy',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'after' => ['policy' => $policy],
            ]);
        });
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:?array<string,mixed>} package, release, registry */
    private function resolveTarget(int $packageId, ?int $releaseId): array
    {
        $package = $this->packages->find($packageId);
        if ($package === null) {
            throw new NotFoundException();
        }

        if ($releaseId !== null) {
            $release = $this->releases->find($releaseId);
            if ($release === null || (int) $release['package_id'] !== $packageId) {
                throw new PackagePolicyException('release_identity', 'That release does not belong to this package.');
            }
        } else {
            $latestId = $package['latest_release_id'] !== null ? (int) $package['latest_release_id'] : 0;
            $release = $latestId > 0 ? $this->releases->find($latestId) : null;
            if ($release === null) {
                $release = $this->releases->forPackage($packageId)[0] ?? null;
            }
            if ($release === null) {
                throw new PackagePolicyException('no_release', 'This package has no installable release.');
            }
        }

        $registry = $package['registry_id'] !== null ? $this->registries->find((int) $package['registry_id']) : null;

        return [$package, $release, $registry];
    }

    /** @return array<string,mixed> */
    private function requireInstall(int $installedId): array
    {
        $install = $this->installs->find($installedId);
        if ($install === null) {
            throw new PackagePolicyException('not_installed', 'No local installation with that id exists.');
        }

        return $install;
    }

    /**
     * @param array<string,mixed> $install
     * @param list<string> $allowed
     */
    private function assertState(array $install, array $allowed): void
    {
        if (!in_array((string) $install['state'], $allowed, true)) {
            throw new PackagePolicyException(
                'invalid_state',
                'This action is not available while the package is ' . (string) $install['state'] . '.',
            );
        }
    }

    /** @return list<array{kind:string,key:string,risk:string}> */
    private function permissionRows(PackageManifest $manifest): array
    {
        return array_map(
            static fn (array $permission): array => [
                'kind' => $permission['kind'],
                'key' => $permission['key'],
                'risk' => $permission['risk'],
            ],
            $manifest->permissions,
        );
    }

    /** @param array<string,mixed> $install */
    private function versionOf(array $install): ?string
    {
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;

        return $release !== null ? (string) $release['version'] : null;
    }

    /**
     * @param array<string,mixed> $install
     * @param array<string,mixed> $package
     */
    private function quarantine(array $install, array $package, ?int $actorId, string $reason): void
    {
        $installedId = (int) $install['id'];
        $this->db->transaction(function () use ($install, $installedId, $package, $actorId, $reason): void {
            $this->installs->setState($installedId, 'quarantined');
            $this->installs->setHealth($installedId, 'failed', $reason);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'quarantine',
                'actor_id' => $actorId,
                'new_version' => $this->versionOf($install),
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => $this->versionOf($install),
                'digest' => (string) $install['digest'],
                'event' => 'quarantine',
                'source' => 'local',
                'actor_id' => $actorId,
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => $actorId,
                'action' => 'package_quarantine',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);
        });

        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'quarantine',
            'package' => (string) $package['package_uid'],
            'reason' => $reason,
        ]);
    }

    private function exceptionCode(PackagePolicyException | RegistryVerificationException $e): string
    {
        return (string) $e->code;
    }
}
