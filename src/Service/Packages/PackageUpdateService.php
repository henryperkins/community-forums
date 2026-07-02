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
use App\Repository\PackageTransparencyLogRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageManifest;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\Packages\PermissionDiff;
use App\Security\Registry\RegistryVerificationException;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Staged updates and rollback over reviewed, content-addressed releases.
 *
 * Permission expansion stages until fresh consent. Reductions and unchanged
 * permission sets apply immediately. Rollback is limited to previously
 * activated digests whose artifacts are still cached.
 */
final class PackageUpdateService
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
        private PackageAcquisitionService $acquisition,
        private PackageSecurityGate $gate,
        private ManifestValidator $manifests,
        private PackageArtifactStore $artifacts,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function updatePlan(User $admin, int $installedId, ?int $targetReleaseId = null): array
    {
        $install = $this->requireInstall($installedId);
        [$package, $target, $registry, $current] = $this->resolveUpdateTargetForPlan($install, $targetReleaseId);
        $manifest = null;
        $refusal = null;
        $diff = ['added' => [], 'removed' => [], 'unchanged' => []];

        try {
            $this->assertEnabledRegistry($registry);
            $manifest = $this->manifestFromHydratedRelease($package, $target);
            $this->gate->assertInstallable($package, $target);
            $this->assertCompatible($manifest);
            $diff = $this->diffAgainstCurrent($installedId, $manifest);
        } catch (PackagePolicyException | RegistryVerificationException $e) {
            $refusal = ['code' => $this->exceptionCode($e), 'message' => $e->getMessage()];
        }

        return [
            'install' => $install,
            'package' => $package,
            'current_release' => $current,
            'target' => $target,
            'manifest' => $manifest,
            'diff' => $diff,
            'requires_consent' => $diff['added'] !== [],
            'compatible' => $manifest?->coreCompatible(),
            'refusal' => $refusal,
        ];
    }

    /** @return array{status:'staged'|'applied'} */
    public function update(User $admin, string $currentPassword, int $installedId, ?int $targetReleaseId = null): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->requireInstall($installedId);
        $this->assertUpdatable($install);
        $this->assertNotPinned($install);

        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, $targetReleaseId);

        return $this->stageOrApply($admin, $install, $package, $registry, $current, $target);
    }

    public function applyStaged(User $admin, string $currentPassword, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->requireInstall($installedId);
        $this->assertUpdateState($install);
        $this->assertNotPinned($install);
        if ($install['staged_release_id'] === null) {
            throw new PackagePolicyException('no_staged_update', 'There is no staged update to approve.');
        }

        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, (int) $install['staged_release_id']);
        $stagedDigest = $install['staged_digest'] !== null ? (string) $install['staged_digest'] : '';
        if ($stagedDigest === '' || !hash_equals($stagedDigest, (string) $target['digest'])) {
            throw new PackagePolicyException(
                'stage_digest_mismatch',
                'The staged release no longer matches the digest that was staged; cancel and restage the update.',
            );
        }
        $manifest = $this->verifyTarget($registry, $package, $target);
        $target = $this->releases->find((int) $target['id']) ?? $target;
        // Approving a stage installs new bytes, so it faces the same gate as
        // update()/rollback() - including block_new advisories.
        $this->gate->assertInstallable($package, $target);
        $this->assertCompatible($manifest);

        $this->activate($admin, $install, $package, $current, $target, $manifest, grantAdded: true);
    }

    public function cancelStaged(User $admin, int $installedId): void
    {
        $this->writeGate->assertCanWrite($admin);

        $install = $this->requireInstall($installedId);
        if ($install['staged_release_id'] === null) {
            throw new PackagePolicyException('no_staged_update', 'There is no staged update to cancel.');
        }

        $this->db->transaction(function () use ($install, $installedId, $admin): void {
            $this->installs->stageRelease($installedId, null, null);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'update_staged',
                'actor_id' => $admin->id(),
                'detail' => 'cancelled',
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_update_cancel',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
            ]);
        });
    }

    /** @return list<array<string,mixed>> */
    public function rollbackTargets(int $installedId): array
    {
        $install = $this->requireInstall($installedId);
        $verified = array_flip($this->history->verifiedDigestsFor((int) $install['package_id']));
        $targets = [];

        foreach ($this->releases->forPackage((int) $install['package_id']) as $release) {
            $digest = (string) $release['digest'];
            if ($digest === (string) $install['digest'] || !isset($verified[$digest]) || !$this->artifacts->has($digest)) {
                continue;
            }
            $targets[] = $release;
        }

        return $targets;
    }

    /** @return array{status:'staged'|'applied'} */
    public function rollback(User $admin, string $currentPassword, int $installedId, int $targetReleaseId): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->requireInstall($installedId);
        $this->assertUpdatable($install);
        $this->assertNotPinned($install);

        $eligible = array_map('intval', array_column($this->rollbackTargets($installedId), 'id'));
        if (!in_array($targetReleaseId, $eligible, true)) {
            throw new PackagePolicyException(
                'rollback_target',
                'Rollback targets must be previously verified digests with cached artifacts.',
            );
        }

        [$package, $target, $registry, $current] = $this->resolveUpdateTarget($install, $targetReleaseId);

        return $this->stageOrApply($admin, $install, $package, $registry, $current, $target);
    }

    /**
     * @param array<string,mixed> $install
     * @param array<string,mixed> $package
     * @param array<string,mixed> $registry
     * @param ?array<string,mixed> $current
     * @param array<string,mixed> $target
     * @return array{status:'staged'|'applied'}
     */
    private function stageOrApply(
        User $admin,
        array $install,
        array $package,
        array $registry,
        ?array $current,
        array $target,
    ): array {
        $installedId = (int) $install['id'];
        $manifest = $this->verifyTarget($registry, $package, $target);
        $target = $this->releases->find((int) $target['id']) ?? $target;
        $this->gate->assertInstallable($package, $target);
        $this->assertCompatible($manifest);

        $diff = $this->diffAgainstCurrent($installedId, $manifest);
        if ($diff['added'] !== []) {
            $this->db->transaction(function () use ($install, $installedId, $target, $diff, $admin): void {
                $this->installs->stageRelease($installedId, (int) $target['id'], (string) $target['digest']);
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => $installedId,
                    'event' => 'update_staged',
                    'actor_id' => $admin->id(),
                    'prior_version' => $this->versionOf($install),
                    'new_version' => (string) $target['version'],
                    'prior_digest' => (string) $install['digest'],
                    'new_digest' => (string) $target['digest'],
                    'permission_snapshot_json' => json_encode($diff, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_update_staged',
                    'target_type' => 'package',
                    'target_id' => (int) $install['package_id'],
                    'after' => ['version' => (string) $target['version'], 'added' => count($diff['added'])],
                ]);
            });
            $this->telemetry?->emit('package.update', [
                'package' => (string) $package['package_uid'],
                'from' => $this->versionOf($install),
                'to' => (string) $target['version'],
                'result' => 'staged',
                'added' => count($diff['added']),
            ]);

            return ['status' => 'staged'];
        }

        $this->activate($admin, $install, $package, $current, $target, $manifest, grantAdded: false);

        return ['status' => 'applied'];
    }

    /**
     * @param array<string,mixed> $install
     * @param array<string,mixed> $package
     * @param ?array<string,mixed> $current
     * @param array<string,mixed> $target
     */
    private function activate(
        User $admin,
        array $install,
        array $package,
        ?array $current,
        array $target,
        PackageManifest $manifest,
        bool $grantAdded,
    ): void {
        $installedId = (int) $install['id'];
        $digest = (string) $target['digest'];
        if (!$this->artifacts->verify($digest)) {
            throw new PackagePolicyException('artifact_tampered', 'The target artifact failed its digest check.');
        }

        $priorVersion = $current !== null ? (string) $current['version'] : null;
        $event = ($priorVersion !== null && version_compare((string) $target['version'], $priorVersion, '<')) ? 'rollback' : 'update';

        $declared = [];
        $previouslyGranted = [];
        foreach ($this->permissions->forInstall($installedId) as $row) {
            $key = $row['kind'] . ':' . $row['permission_key'];
            $declared[$key] = true;
            if ((int) $row['granted'] === 1) {
                $previouslyGranted[$key] = true;
            }
        }

        // The staged-approval ceremony consents to the expansion it displayed;
        // declarations that were pending before the update stay pending.
        $rows = [];
        foreach ($manifest->permissions as $permission) {
            $key = $permission['kind'] . ':' . $permission['key'];
            $rows[] = [
                'kind' => $permission['kind'],
                'key' => $permission['key'],
                'risk' => $permission['risk'],
                'granted' => isset($previouslyGranted[$key]) || ($grantAdded && !isset($declared[$key])),
            ];
        }
        $priorSnapshot = json_encode($this->permissions->forInstall($installedId), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->db->transaction(function () use (
            $install,
            $installedId,
            $package,
            $target,
            $manifest,
            $rows,
            $admin,
            $event,
            $priorVersion,
            $priorSnapshot,
        ): void {
            $this->installs->activateRelease(
                $installedId,
                (int) $target['id'],
                (string) $target['digest'],
                $manifest->coreMin,
                $manifest->coreMax,
                (string) $target['review_status'],
            );
            $this->permissions->replaceWithGrants($installedId, $rows, $admin->id());
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => $event,
                'actor_id' => $admin->id(),
                'prior_version' => $priorVersion,
                'new_version' => (string) $target['version'],
                'prior_digest' => (string) $install['digest'],
                'new_digest' => (string) $target['digest'],
                'permission_snapshot_json' => $priorSnapshot,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $package['package_uid'],
                'version' => (string) $target['version'],
                'digest' => (string) $target['digest'],
                'event' => $event,
                'source' => 'local',
                'actor_id' => $admin->id(),
                'registry_id' => $install['source_registry_id'] !== null ? (int) $install['source_registry_id'] : null,
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $event === 'rollback' ? 'package_rollback' : 'package_update',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'before' => ['version' => $priorVersion, 'digest' => (string) $install['digest']],
                'after' => ['version' => (string) $target['version'], 'digest' => (string) $target['digest']],
            ]);
        });

        $this->telemetry?->emit('package.update', [
            'package' => (string) $package['package_uid'],
            'from' => $priorVersion,
            'to' => (string) $target['version'],
            'result' => $event,
        ]);
    }

    /**
     * @param array<string,mixed> $registry
     * @param array<string,mixed> $package
     * @param array<string,mixed> $target
     */
    private function verifyTarget(array $registry, array $package, array $target): PackageManifest
    {
        return $this->acquisition->ensureVerified($registry, $package, $target);
    }

    private function assertCompatible(PackageManifest $manifest): void
    {
        if (!$manifest->coreCompatible()) {
            throw new PackagePolicyException('incompatible_core', 'The target release does not support the running core version.');
        }
    }

    /** @return array{added:list<array<string,mixed>>,removed:list<array<string,mixed>>,unchanged:list<array<string,mixed>>} */
    private function diffAgainstCurrent(int $installedId, PackageManifest $target): array
    {
        $current = array_map(
            static fn (array $row): array => ['kind' => (string) $row['kind'], 'key' => (string) $row['permission_key']],
            $this->permissions->forInstall($installedId),
        );

        return PermissionDiff::diff($current, $target->permissions);
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

    /** @param array<string,mixed> $install */
    private function assertUpdatable(array $install): void
    {
        $this->assertUpdateState($install);
        if ($install['staged_release_id'] !== null) {
            throw new PackagePolicyException('stage_pending', 'A staged update is already pending; approve or cancel it first.');
        }
    }

    /** @param array<string,mixed> $install */
    private function assertUpdateState(array $install): void
    {
        if (!in_array((string) $install['state'], ['installed', 'enabled', 'disabled'], true)) {
            throw new PackagePolicyException(
                'invalid_state',
                'Updates are not available while the package is ' . (string) $install['state'] . '.',
            );
        }
    }

    /** @param array<string,mixed> $install */
    private function assertNotPinned(array $install): void
    {
        if ((int) $install['pinned'] === 1) {
            throw new PackagePolicyException('pinned', 'This installation is pinned; unpin it before updating.');
        }
    }

    /**
     * @param array<string,mixed> $install
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>,3:?array<string,mixed>}
     */
    private function resolveUpdateTarget(array $install, ?int $targetReleaseId): array
    {
        [$package, $target, $registry, $current] = $this->resolveUpdateTargetForPlan($install, $targetReleaseId);
        $this->assertEnabledRegistry($registry);

        return [$package, $target, $registry, $current];
    }

    /**
     * @param array<string,mixed> $install
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:?array<string,mixed>,3:?array<string,mixed>}
     */
    private function resolveUpdateTargetForPlan(array $install, ?int $targetReleaseId): array
    {
        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null) {
            throw new NotFoundException();
        }

        if ($targetReleaseId !== null) {
            $target = $this->releases->find($targetReleaseId);
            if ($target === null || (int) $target['package_id'] !== (int) $package['id']) {
                throw new PackagePolicyException('release_identity', 'That release does not belong to this package.');
            }
        } else {
            $latestId = $package['latest_release_id'] !== null ? (int) $package['latest_release_id'] : 0;
            $target = $latestId > 0 ? $this->releases->find($latestId) : null;
            if ($target === null) {
                $target = $this->releases->forPackage((int) $package['id'])[0] ?? null;
            }
            if ($target === null) {
                throw new PackagePolicyException('no_release', 'This package has no release to update to.');
            }
        }

        if ((string) $target['digest'] === (string) $install['digest']) {
            throw new PackagePolicyException('same_release', 'The installation is already on that release.');
        }

        $registry = $package['registry_id'] !== null ? $this->registries->find((int) $package['registry_id']) : null;
        $current = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;

        return [$package, $target, $registry, $current];
    }

    /** @param ?array<string,mixed> $registry */
    private function assertEnabledRegistry(?array $registry): void
    {
        if ($registry === null) {
            throw new PackagePolicyException('source_mismatch', 'This package has no pinned registry source.');
        }
        if ((int) ($registry['is_enabled'] ?? 0) !== 1) {
            throw new PackagePolicyException('registry_disabled', 'This package registry is disabled.');
        }
    }

    /** @param array<string,mixed> $package @param array<string,mixed> $release */
    private function manifestFromHydratedRelease(array $package, array $release): PackageManifest
    {
        $json = $release['manifest_json'] ?? null;
        if (!is_string($json) || $json === '') {
            throw new PackagePolicyException(
                'release_unverified',
                'This release has no hydrated manifest metadata; submit the update again to re-verify it.',
            );
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PackagePolicyException('release_unverified', 'The hydrated manifest metadata is malformed.');
        }
        if (!is_array($payload)) {
            throw new PackagePolicyException('release_unverified', 'The hydrated manifest metadata is malformed.');
        }

        return $this->manifests->validate(
            $payload,
            (string) $package['package_uid'],
            (string) $release['version'],
        );
    }

    /** @param array<string,mixed> $install */
    private function versionOf(array $install): ?string
    {
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;

        return $release !== null ? (string) $release['version'] : null;
    }

    private function exceptionCode(PackagePolicyException | RegistryVerificationException $e): string
    {
        return (string) $e->code;
    }
}
