<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Config;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\SettingRepository;
use App\Service\Registry\RegistryAdvisoryService;

final class PackageCredentialAuthGuard
{
    public function __construct(
        private InstalledPackageCredentialRepository $credentials,
        private InstalledPackageRepository $installs,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageAdvisoryRepository $advisories,
        private LocalPackageBlockRepository $blocks,
        private SettingRepository $settings,
        private Config $config,
    ) {
    }

    public function allowsApiToken(int $apiTokenId): bool
    {
        $link = $this->credentials->findByApiToken($apiTokenId);
        if ($link === null) {
            return true; // not package-owned
        }
        if ($link['revoked_at'] !== null) {
            return false;
        }
        if ($this->isExecutionDisabled()) {
            return false;
        }

        $install = $this->installs->find((int) $link['installed_package_id']);
        if ($install === null || (string) $install['state'] !== 'enabled') {
            return false;
        }
        if ((string) ($install['review_status'] ?? '') !== 'approved') {
            return false;
        }

        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null) {
            return false;
        }
        if (in_array((string) ($package['advisory_status'] ?? 'none'), ['blocked', 'revoked'], true)) {
            return false;
        }
        if ($this->blocks->isBlocked((string) $install['digest'], (string) $package['package_uid'])) {
            return false;
        }

        $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
        if ($release === null || (string) ($release['review_status'] ?? 'approved') !== 'approved') {
            return false;
        }
        if (in_array((string) ($release['advisory_status'] ?? 'none'), ['blocked', 'revoked'], true)) {
            return false;
        }

        $reason = RegistryAdvisoryService::blockingAdvisoryReason(
            $this->advisories->forPackage((int) $install['package_id']),
            ['force_disable', 'revoke'],
            (string) $install['digest'],
            (string) ($release['version'] ?? ''),
        );

        return $reason === null;
    }

    public function isExecutionDisabled(): bool
    {
        try {
            if ($this->settings->getString('package_execution_disabled', '') === '1') {
                return true;
            }
        } catch (\Throwable) {
            // fall through to config break-glass
        }

        return (bool) $this->config->get('packages.execution_disabled', false);
    }
}
