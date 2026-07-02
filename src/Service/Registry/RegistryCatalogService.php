<?php

declare(strict_types=1);

namespace App\Service\Registry;

use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Support\CoreVersion;

/**
 * Read model for the staff-only catalogue browse. Read-only by construction:
 * it exposes no mutation and the controller registers no POST.
 */
final class RegistryCatalogService
{
    public function __construct(
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private PackageAdvisoryRepository $advisories,
        private PackageRegistryRepository $registries,
        private RegistrySnapshotService $snapshots,
        private LocalBlocklistService $blocklist,
    ) {
    }

    /** @return array{registries:list<array<string,mixed>>,packages:list<array<string,mixed>>} */
    public function overview(?\DateTimeImmutable $now = null): array
    {
        $registries = [];
        foreach ($this->registries->all() as $registry) {
            $registry['fresh'] = $this->snapshots->isFresh($registry, $now);
            $registries[] = $registry;
        }

        $packages = [];
        foreach ($this->packages->catalog() as $package) {
            $latest = $package['latest_release_id'] === null ? null : $this->releases->find((int) $package['latest_release_id']);
            $package['latest'] = $latest;
            $package['compatible'] = $latest === null
                ? null
                : CoreVersion::satisfies(
                    $latest['core_min'] !== null ? (string) $latest['core_min'] : null,
                    $latest['core_max'] !== null ? (string) $latest['core_max'] : null,
                );
            $package['blocked'] = $this->blocklist->isBlocked(
                $latest === null ? null : (string) $latest['digest'],
                (string) $package['package_uid'],
            );
            $packages[] = $package;
        }

        return ['registries' => $registries, 'packages' => $packages];
    }

    /**
     * @return array{package:array<string,mixed>,registry:?array<string,mixed>,releases:list<array<string,mixed>>,advisories:list<array<string,mixed>>,blocked:bool}|null
     */
    public function detail(int $packageId): ?array
    {
        $package = $this->packages->find($packageId);
        if ($package === null) {
            return null;
        }

        $releases = [];
        foreach ($this->releases->forPackage($packageId) as $release) {
            $release['compatible'] = CoreVersion::satisfies(
                $release['core_min'] !== null ? (string) $release['core_min'] : null,
                $release['core_max'] !== null ? (string) $release['core_max'] : null,
            );
            $release['blocked'] = $this->blocklist->isBlocked((string) $release['digest'], (string) $package['package_uid']);
            $releases[] = $release;
        }

        return [
            'package' => $package,
            'registry' => $package['registry_id'] === null ? null : $this->registries->find((int) $package['registry_id']),
            'releases' => $releases,
            'advisories' => $this->advisories->forPackage($packageId),
            'blocked' => $this->blocklist->isBlocked(null, (string) $package['package_uid']),
        ];
    }
}
