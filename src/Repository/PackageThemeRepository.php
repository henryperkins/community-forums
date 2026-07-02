<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/** Theme package build/assets/state persistence (migration 0072). */
final class PackageThemeRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function findBuild(int $buildId): ?array
    {
        return $this->db->fetch('SELECT * FROM package_theme_builds WHERE id = ?', [$buildId]);
    }

    /** @return array<string,mixed>|null */
    public function findBuildFor(int $installedId, string $sourceDigest): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM package_theme_builds WHERE installed_package_id = ? AND source_digest = ?',
            [$installedId, $sourceDigest],
        );
    }

    /** @param array<string,mixed> $row */
    public function createBuild(array $row): int
    {
        return $this->db->insert(
            'INSERT INTO package_theme_builds
                (installed_package_id, package_id, release_id, source_digest, token_schema_version,
                 tokens_json, validation_json, css, css_digest, built_by)
             VALUES (:installed_package_id, :package_id, :release_id, :source_digest, :token_schema_version,
                     :tokens_json, :validation_json, :css, :css_digest, :built_by)',
            [
                'installed_package_id' => $row['installed_package_id'],
                'package_id' => $row['package_id'],
                'release_id' => $row['release_id'],
                'source_digest' => $row['source_digest'],
                'token_schema_version' => $row['token_schema_version'],
                'tokens_json' => $row['tokens_json'],
                'validation_json' => $row['validation_json'],
                'css' => $row['css'],
                'css_digest' => $row['css_digest'],
                'built_by' => $row['built_by'] ?? null,
            ],
        );
    }

    public function addAsset(int $buildId, string $name, string $mime, string $bytes, string $digest): int
    {
        return $this->db->insert(
            'INSERT INTO package_theme_assets (build_id, name, mime, bytes, byte_len, digest)
             VALUES (:build_id, :name, :mime, :bytes, :byte_len, :digest)',
            [
                'build_id' => $buildId,
                'name' => $name,
                'mime' => $mime,
                'bytes' => $bytes,
                'byte_len' => strlen($bytes),
                'digest' => $digest,
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function assetsFor(int $buildId): array
    {
        return $this->db->fetchAll(
            'SELECT id, build_id, name, mime, byte_len, digest
             FROM package_theme_assets
             WHERE build_id = ?
             ORDER BY id',
            [$buildId],
        );
    }

    /** @return array<string,mixed>|null */
    public function findAssetByDigest(string $digest): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM package_theme_assets WHERE digest = ? ORDER BY id DESC LIMIT 1',
            [$digest],
        );
    }

    /** @return array<string,mixed>|null */
    public function findCssByDigest(string $cssDigest): ?array
    {
        return $this->db->fetch(
            'SELECT b.*, ip.state AS install_state, p.package_uid, p.name AS package_name
             FROM package_theme_builds b
             JOIN installed_packages ip ON ip.id = b.installed_package_id
             JOIN packages p ON p.id = b.package_id
             WHERE b.css_digest = ?
             ORDER BY b.id DESC
             LIMIT 1',
            [$cssDigest],
        );
    }

    /** @return array{active_build_id:?int,lkg_build_id:?int,activated_at:?string,activated_by:?int} */
    public function state(): array
    {
        $row = $this->db->fetch('SELECT active_build_id, lkg_build_id, activated_at, activated_by FROM theme_state WHERE id = 1');

        return [
            'active_build_id' => $row === null || $row['active_build_id'] === null ? null : (int) $row['active_build_id'],
            'lkg_build_id' => $row === null || $row['lkg_build_id'] === null ? null : (int) $row['lkg_build_id'],
            'activated_at' => $row === null || $row['activated_at'] === null ? null : (string) $row['activated_at'],
            'activated_by' => $row === null || $row['activated_by'] === null ? null : (int) $row['activated_by'],
        ];
    }

    public function setState(?int $activeBuildId, ?int $lkgBuildId, ?int $actorId): void
    {
        $this->db->run(
            'INSERT INTO theme_state
                (id, active_build_id, lkg_build_id, activated_by, activated_at, updated_at)
             VALUES
                (1, :active_build_id, :lkg_build_id, :activated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                active_build_id = VALUES(active_build_id),
                lkg_build_id = VALUES(lkg_build_id),
                activated_by = VALUES(activated_by),
                activated_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()',
            [
                'active_build_id' => $activeBuildId,
                'lkg_build_id' => $lkgBuildId,
                'activated_by' => $actorId,
            ],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function buildsForInstall(int $installedId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM package_theme_builds WHERE installed_package_id = ? ORDER BY id DESC',
            [$installedId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function themeInstalls(): array
    {
        return $this->db->fetchAll(
            "SELECT ip.*, p.package_uid, p.name AS package_name, p.type AS package_type,
                    r.version AS release_version
             FROM installed_packages ip
             JOIN packages p ON p.id = ip.package_id
             LEFT JOIN package_releases r ON r.id = ip.release_id
             WHERE p.type = 'theme' AND ip.state IN ('installed','enabled','disabled')
             ORDER BY p.package_uid",
        );
    }
}
