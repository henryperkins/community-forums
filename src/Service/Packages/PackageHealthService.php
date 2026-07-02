<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Service\Registry\RegistryAdvisoryService;

final class PackageHealthService
{
    public function __construct(
        private Database $db,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageAdvisoryRepository $advisories,
        private LocalPackageBlockRepository $blocks,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private PackageArtifactStore $artifacts,
        private ModerationLogRepository $audit,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{checked:int,quarantined:int,disabled:int,purged:int,updates:int} */
    public function checkAll(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $checked = 0;
        $quarantined = 0;
        $updates = 0;

        foreach ($this->installs->activeWithContext() as $install) {
            if (in_array((string) $install['state'], ['installed', 'enabled', 'disabled'], true)) {
                $checked++;
                $digest = (string) $install['digest'];
                if (!$this->artifacts->has($digest)) {
                    $this->installs->setHealth((int) $install['id'], 'degraded', 'artifact missing during health check', $now->format('Y-m-d H:i:s'));
                    continue;
                }
                if (!$this->artifacts->verify($digest)) {
                    if ($this->quarantine($install, 'digest mismatch during health check')) {
                        $quarantined++;
                    }
                    continue;
                }
                $this->installs->setHealth((int) $install['id'], 'ok', null, $now->format('Y-m-d H:i:s'));
            }

            if ((string) $install['update_policy'] === 'notify' && $this->updateAvailable($install)) {
                $updates++;
            }
        }

        $disabled = $this->enforcePolicy();
        $purged = $this->purgeExpired($now);

        $stats = [
            'checked' => $checked,
            'quarantined' => $quarantined,
            'disabled' => $disabled,
            'purged' => $purged,
            'updates' => $updates,
        ];
        $this->telemetry?->emit('package.health', $stats);

        return $stats;
    }

    public function enforcePolicy(): int
    {
        $changed = 0;
        foreach ($this->installs->activeWithContext() as $install) {
            $version = $install['release_version'] !== null ? (string) $install['release_version'] : '';
            if ((string) $install['state'] === 'enabled') {
                $reason = $this->blockingReason(
                    (int) $install['package_id'],
                    (string) $install['package_uid'],
                    (string) $install['digest'],
                    $version,
                    ['force_disable', 'revoke'],
                );
                if ($reason !== null) {
                    if ($this->securityDisable($install, $reason)) {
                        $changed++;
                    }
                }
            }

            if ($install['staged_digest'] !== null) {
                $reason = $this->blockingReason(
                    (int) $install['package_id'],
                    (string) $install['package_uid'],
                    (string) $install['staged_digest'],
                    $this->stagedVersion($install),
                    ['block_new', 'force_disable', 'revoke'],
                );
                if ($reason !== null) {
                    $this->cancelStage($install, $reason);
                    $changed++;
                }
            }
        }

        return $changed;
    }

    public function purgeExpired(?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $purged = 0;

        foreach ($this->installs->purgeable($now->format('Y-m-d H:i:s')) as $install) {
            $digest = (string) $install['digest'];
            $this->db->transaction(function () use ($install): void {
                $this->permissions->deleteFor((int) $install['id']);
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => (int) $install['id'],
                    'event' => 'purge',
                    'detail' => 'retention window lapsed',
                ]);
                $this->installs->delete((int) $install['id']);
            });
            $this->artifacts->remove($digest);
            $purged++;
        }

        return $purged;
    }

    /** @param list<string> $actions */
    private function blockingReason(int $packageId, string $packageUid, string $digest, string $version, array $actions): ?string
    {
        if ($this->blocks->isBlocked($digest, $packageUid)) {
            return 'local blocklist';
        }

        foreach ($this->advisories->forPackage($packageId) as $advisory) {
            if (!in_array((string) $advisory['action'], $actions, true)) {
                continue;
            }
            if (RegistryAdvisoryService::affectsRelease($advisory, $digest, $version)) {
                return 'advisory ' . (string) $advisory['advisory_uid'] . ' (' . (string) $advisory['action'] . ')';
            }
        }

        return null;
    }

    /** @param array<string,mixed> $install */
    private function securityDisable(array $install, string $reason): bool
    {
        $changed = $this->db->transaction(function () use ($install, $reason): bool {
            if (!$this->installs->setStateIfCurrent((int) $install['id'], (string) $install['state'], 'disabled')) {
                return false;
            }
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'disable',
                'new_version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $install['package_uid'],
                'version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'digest' => (string) $install['digest'],
                'event' => 'force_disable',
                'source' => 'local',
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => null,
                'action' => 'package_force_disable',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);

            return true;
        });
        if (!$changed) {
            return false;
        }
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'force_disable',
            'package' => (string) $install['package_uid'],
            'reason' => $reason,
        ]);

        return true;
    }

    /** @param array<string,mixed> $install */
    private function cancelStage(array $install, string $reason): void
    {
        $this->db->transaction(function () use ($install, $reason): void {
            $this->installs->stageRelease((int) $install['id'], null, null);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'update_staged',
                'detail' => 'cancelled: ' . $reason,
            ]);
        });
    }

    /** @param array<string,mixed> $install */
    private function quarantine(array $install, string $reason): bool
    {
        $changed = $this->db->transaction(function () use ($install, $reason): bool {
            if (!$this->installs->setStateIfCurrent((int) $install['id'], (string) $install['state'], 'quarantined')) {
                return false;
            }
            $this->installs->setHealth((int) $install['id'], 'failed', $reason);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => (int) $install['id'],
                'event' => 'quarantine',
                'new_version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'new_digest' => (string) $install['digest'],
                'detail' => $reason,
            ]);
            $this->transparency->record([
                'package_uid' => (string) $install['package_uid'],
                'version' => $install['release_version'] !== null ? (string) $install['release_version'] : null,
                'digest' => (string) $install['digest'],
                'event' => 'quarantine',
                'source' => 'local',
                'detail' => $reason,
            ]);
            $this->audit->log([
                'actor_id' => null,
                'action' => 'package_quarantine',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'reason' => $reason,
            ]);

            return true;
        });
        if (!$changed) {
            return false;
        }
        $this->telemetry?->emit('package.lifecycle', [
            'action' => 'quarantine',
            'package' => (string) $install['package_uid'],
            'reason' => $reason,
        ]);

        return true;
    }

    /** @param array<string,mixed> $install */
    private function updateAvailable(array $install): bool
    {
        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null || $package['latest_release_id'] === null) {
            return false;
        }
        $latest = $this->releases->find((int) $package['latest_release_id']);

        return $latest !== null && (string) $latest['digest'] !== (string) $install['digest'];
    }

    /** @param array<string,mixed> $install */
    private function stagedVersion(array $install): string
    {
        if ($install['staged_release_id'] === null) {
            return '';
        }
        $staged = $this->releases->find((int) $install['staged_release_id']);

        return $staged !== null ? (string) $staged['version'] : '';
    }
}
