<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Database;
use App\Core\Telemetry;
use App\Domain\User;
use App\Repository\InstalledPackageRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageThemeRepository;
use App\Repository\SettingRepository;
use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PackageSecurityGate;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Owns the singleton package-theme activation state and its emergency safe mode.
 */
final class ThemeStateService
{
    public function __construct(
        private Database $db,
        private PackageThemeRepository $themes,
        private InstalledPackageRepository $installs,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageArtifactStore $artifacts,
        private PackageSecurityGate $gate,
        private ThemeBuildService $builder,
        private WriteGate $writeGate,
        private ReauthGate $reauth,
        private SettingRepository $settings,
        private ModerationLogRepository $audit,
        private PackageHistoryRepository $history,
        private ?Telemetry $telemetry = null,
        private bool $forcedSafeMode = false,
    ) {
    }

    public function safeMode(): bool
    {
        if ($this->forcedSafeMode) {
            return true;
        }

        try {
            return $this->settings->getString('theme_safe_mode', '') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    public function setSafeMode(User $admin, bool $enabled, ?string $currentPassword = null): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$enabled) {
            $this->reauth->requirePassword($admin, (string) $currentPassword);
        }

        $this->settings->set('theme_safe_mode', $enabled ? '1' : '');
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => $enabled ? 'theme_safe_mode_enter' : 'theme_safe_mode_exit',
            'target_type' => 'setting',
            'target_id' => 0,
        ]);
        $this->telemetry?->emit('theme.lifecycle', ['action' => $enabled ? 'safe_mode_enter' : 'safe_mode_exit']);
    }

    /** @return array<string,mixed>|null */
    public function activeBuild(): ?array
    {
        if ($this->safeMode()) {
            return null;
        }

        $id = $this->themes->state()['active_build_id'];
        if ($id === null) {
            return null;
        }

        return $this->serveableBuild($id);
    }

    /** @return array<string,mixed>|null */
    public function previewBuildFor(?int $buildId): ?array
    {
        return $buildId !== null ? $this->serveableBuild($buildId) : null;
    }

    /** @return array<string,mixed> */
    public function preview(User $admin, int $installedId): array
    {
        $this->writeGate->assertCanWrite($admin);

        return $this->buildForInstall($admin, $installedId);
    }

    /** @return array<string,mixed> */
    public function activate(User $admin, string $currentPassword, int $installedId): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $build = $this->buildForInstall($admin, $installedId);
        $state = $this->themes->state();
        $priorBuild = $state['active_build_id'] !== null ? $this->themes->findBuild($state['active_build_id']) : null;
        $lkg = $state['active_build_id'] !== null && $state['active_build_id'] !== (int) $build['id']
            ? $state['active_build_id']
            : $state['lkg_build_id'];

        $this->db->transaction(function () use ($admin, $build, $priorBuild, $lkg): void {
            $this->themes->setState((int) $build['id'], $lkg, $admin->id());
            $this->history->record([
                'package_id' => (int) $build['package_id'],
                'installed_package_id' => (int) $build['installed_package_id'],
                'event' => 'theme_activate',
                'actor_id' => $admin->id(),
                'prior_digest' => $priorBuild !== null ? (string) $priorBuild['css_digest'] : null,
                'new_digest' => (string) $build['css_digest'],
                'detail' => json_encode(['build_id' => (int) $build['id']], JSON_UNESCAPED_SLASHES),
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'theme_activate',
                'target_type' => 'package',
                'target_id' => (int) $build['package_id'],
                'before' => $priorBuild !== null ? ['css_digest' => (string) $priorBuild['css_digest']] : null,
                'after' => ['css_digest' => (string) $build['css_digest'], 'build_id' => (int) $build['id']],
            ]);
        });

        $this->telemetry?->emit('theme.lifecycle', [
            'action' => 'activate',
            'package_id' => (int) $build['package_id'],
            'css_digest' => (string) $build['css_digest'],
        ]);

        return $build;
    }

    /** @return array<string,mixed> */
    public function rollback(User $admin, string $currentPassword): array
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $state = $this->themes->state();
        $targetId = $state['lkg_build_id'];
        if ($targetId === null) {
            throw new PackagePolicyException('theme_lkg_missing', 'No last-known-good theme build is available.');
        }

        $target = $this->serveableBuild($targetId);
        if ($target === null) {
            throw new PackagePolicyException('theme_lkg_invalid', 'The last-known-good theme build is no longer serveable.');
        }

        $prior = $state['active_build_id'] !== null ? $this->themes->findBuild($state['active_build_id']) : null;
        $this->db->transaction(function () use ($admin, $target, $prior): void {
            $this->themes->setState((int) $target['id'], $prior !== null ? (int) $prior['id'] : null, $admin->id());
            $this->history->record([
                'package_id' => (int) $target['package_id'],
                'installed_package_id' => (int) $target['installed_package_id'],
                'event' => 'theme_rollback',
                'actor_id' => $admin->id(),
                'prior_digest' => $prior !== null ? (string) $prior['css_digest'] : null,
                'new_digest' => (string) $target['css_digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'theme_rollback',
                'target_type' => 'package',
                'target_id' => (int) $target['package_id'],
                'after' => ['css_digest' => (string) $target['css_digest']],
            ]);
        });

        $this->telemetry?->emit('theme.lifecycle', ['action' => 'rollback', 'css_digest' => (string) $target['css_digest']]);

        return $target;
    }

    public function deactivate(User $admin): void
    {
        $this->writeGate->assertCanWrite($admin);
        $state = $this->themes->state();
        $active = $state['active_build_id'] !== null ? $this->themes->findBuild($state['active_build_id']) : null;
        if ($active === null) {
            return;
        }

        $this->db->transaction(function () use ($admin, $active, $state): void {
            $this->themes->setState(null, $state['active_build_id'], $admin->id());
            $this->history->record([
                'package_id' => (int) $active['package_id'],
                'installed_package_id' => (int) $active['installed_package_id'],
                'event' => 'theme_deactivate',
                'actor_id' => $admin->id(),
                'prior_digest' => (string) $active['css_digest'],
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'theme_deactivate',
                'target_type' => 'package',
                'target_id' => (int) $active['package_id'],
            ]);
        });

        $this->telemetry?->emit('theme.lifecycle', ['action' => 'deactivate']);
    }

    public function onInstallIneligible(int $installedId, ?int $actorId = null, string $reason = 'install_ineligible'): void
    {
        $state = $this->themes->state();
        $active = $state['active_build_id'] !== null ? $this->themes->findBuild($state['active_build_id']) : null;
        $lkg = $state['lkg_build_id'] !== null ? $this->serveableBuild($state['lkg_build_id']) : null;

        if ($active !== null && (int) $active['installed_package_id'] === $installedId) {
            $next = $lkg !== null && (int) $lkg['installed_package_id'] !== $installedId ? (int) $lkg['id'] : null;
            $this->themes->setState($next, null, $actorId);
            $this->history->record([
                'package_id' => (int) $active['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'theme_deactivate',
                'actor_id' => $actorId,
                'prior_digest' => (string) $active['css_digest'],
                'new_digest' => $next !== null && $lkg !== null ? (string) $lkg['css_digest'] : null,
                'detail' => $reason,
            ]);
        } elseif ($lkg !== null && (int) $lkg['installed_package_id'] === $installedId) {
            $this->themes->setState($state['active_build_id'], null, $actorId);
        }
    }

    /** @return array{cleared_active:bool,cleared_lkg:bool} */
    public function repair(): array
    {
        $state = $this->themes->state();
        $activeOk = $state['active_build_id'] === null || $this->serveableBuild($state['active_build_id']) !== null;
        $lkgOk = $state['lkg_build_id'] === null || $this->serveableBuild($state['lkg_build_id']) !== null;
        if ($activeOk && $lkgOk) {
            return ['cleared_active' => false, 'cleared_lkg' => false];
        }

        $this->themes->setState(
            $activeOk ? $state['active_build_id'] : null,
            $lkgOk ? $state['lkg_build_id'] : null,
            null,
        );

        return ['cleared_active' => !$activeOk, 'cleared_lkg' => !$lkgOk];
    }

    /** @return array<string,mixed> */
    private function buildForInstall(User $admin, int $installedId): array
    {
        $install = $this->installs->find($installedId);
        if ($install === null || (string) $install['state'] !== 'enabled') {
            throw new PackagePolicyException('invalid_state', 'Theme packages must be enabled before activation.');
        }

        $package = $this->packages->find((int) $install['package_id']);
        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        if ($package === null || $release === null) {
            throw new PackagePolicyException('invalid_state', 'The installed theme release is no longer resolvable.');
        }
        if ((string) $package['type'] !== 'theme') {
            throw new PackagePolicyException('theme_type', 'Only theme packages can be activated as a site theme.');
        }

        $this->gate->assertEnableable($package, $release);

        $manifest = $this->manifestFromArtifact($install);

        return $this->builder->ensureBuild($install, (string) $package['package_uid'], $manifest, $admin->id());
    }

    /**
     * @param array<string,mixed> $install
     * @return array<string,mixed>
     */
    private function manifestFromArtifact(array $install): array
    {
        $digest = (string) $install['digest'];
        if (!$this->artifacts->has($digest)) {
            throw new PackagePolicyException('artifact_missing', 'The verified artifact is missing from the local store.');
        }
        if (!$this->artifacts->verify($digest)) {
            throw new PackagePolicyException('artifact_tampered', 'Installed bytes no longer match the reviewed digest.');
        }

        $bytes = $this->artifacts->get($digest);
        if ($bytes === null) {
            throw new PackagePolicyException('artifact_missing', 'The verified artifact is missing from the local store.');
        }

        try {
            $document = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new PackagePolicyException('artifact_malformed', 'The verified artifact is not valid JSON.');
        }
        if (!is_array($document) || ($document['format'] ?? null) !== 'rb-release.v1' || !is_array($document['manifest'] ?? null)) {
            throw new PackagePolicyException('artifact_malformed', 'The verified artifact is not an rb-release document.');
        }

        return $document['manifest'];
    }

    /** @return array<string,mixed>|null */
    private function serveableBuild(int $buildId): ?array
    {
        $build = $this->themes->findBuild($buildId);
        if ($build === null || !hash_equals((string) $build['css_digest'], hash('sha256', (string) $build['css']))) {
            return null;
        }

        $install = $this->installs->find((int) $build['installed_package_id']);
        if ($install === null || (string) $install['state'] !== 'enabled') {
            return null;
        }

        return $build;
    }
}
