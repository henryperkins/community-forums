<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Domain\User;
use App\Repository\UserRepository;
use App\Security\CapabilityResolver;
use App\Security\PasswordHasher;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;

/**
 * Foundation F9 — measures baseline metrics on the Phase5FixtureSeeder corpus,
 * emitting the PHASE_5_PLAN §11.3 measurement envelope. The one hot path
 * measurable at Foundation is the legacy authorization read (user role/status +
 * board-moderator membership + board posting floor) — the exact path Increment
 * 1's capability resolver replaces, so its p50/p95/p99 is the baseline the `5ms`
 * resolver budget must beat. The legacy/resolver/signature samplers are
 * read-only; later package lifecycle samplers seed synthetic rows and are
 * intended to run inside the caller's rollback transaction.
 */
final class BaselineMetricsService
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> the §11.3 envelope */
    public function measureLegacyAuthorityRead(int $iterations = 200): array
    {
        $iterations = max(1, $iterations);
        $users = $this->db->fetchAll("SELECT id, role, status FROM users WHERE username LIKE 'p5fix_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT id, post_min_role FROM boards WHERE slug LIKE 'p5fix_%' ORDER BY id ASC");

        $samples = [];
        $queryCount = 0;
        $errors = 0;

        if ($users !== [] && $boards !== []) {
            for ($i = 0; $i < $iterations; $i++) {
                $u = $users[$i % count($users)];
                $b = $boards[$i % count($boards)];
                $t0 = hrtime(true);
                try {
                    // The legacy authority triplet (3 statements per decision).
                    $this->db->fetch('SELECT role, status FROM users WHERE id = ?', [(int) $u['id']]);
                    $this->db->fetchValue('SELECT 1 FROM board_moderators WHERE board_id = ? AND user_id = ?', [(int) $b['id'], (int) $u['id']]);
                    $this->db->fetchValue('SELECT post_min_role FROM boards WHERE id = ?', [(int) $b['id']]);
                    $queryCount += 3;
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000; // ns → ms
            }
        }

        return [
            'route_or_job' => 'legacy_authority_read',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . \App\Service\Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => $queryCount,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /**
     * Measures the new resolver on the same fixture envelope as the legacy
     * baseline. Each sample is one board-target write capability decision.
     *
     * @return array<string,mixed> the PHASE_5_PLAN measurement envelope
     */
    public function measureResolver(CapabilityResolver $resolver, int $iterations = 200): array
    {
        $iterations = max(1, $iterations);
        $users = $this->db->fetchAll("SELECT * FROM users WHERE username LIKE 'p5fix\\_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT id FROM boards WHERE slug LIKE 'p5fix\\_%' ORDER BY id ASC");

        $samples = [];
        $errors = 0;

        if ($users !== [] && $boards !== []) {
            for ($i = 0; $i < $iterations; $i++) {
                $user = User::fromRow($users[$i % count($users)]);
                $boardId = (int) $boards[$i % count($boards)]['id'];
                $t0 = hrtime(true);
                try {
                    $resolver->can($user, 'core.thread.create', ['board_id' => $boardId]);
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000;
            }
        }

        return [
            'route_or_job' => 'capability_resolver_can',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => count($samples) * 5,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /**
     * Measures Ed25519 verification through the real TrustChainVerifier on an
     * in-memory 100-package snapshot. The dev signing harness is optional; when
     * absent, callers keep the budget row pending.
     *
     * @return array<string,mixed>|null the PHASE_5_PLAN measurement envelope
     */
    public function measureSignatureVerify(\App\Security\Registry\TrustChainVerifier $verifier, int $iterations = 200): ?array
    {
        if (!class_exists(\Tests\Support\Phase5\SigningHarness::class)) {
            return null;
        }

        $iterations = max(1, $iterations);
        $root = \Tests\Support\Phase5\SigningHarness::generate('bench-root');
        $packages = [];
        for ($i = 0; $i < 100; $i++) {
            $packages[] = [
                'uid' => "bench/pkg-$i",
                'type' => 'theme',
                'releases' => [[
                    'version' => '1.0.' . $i,
                    'digest' => hash('sha256', "bench-artifact-$i"),
                    'core_min' => '0.1.0',
                    'core_max' => null,
                    'channel' => 'stable',
                    'advisory' => 'none',
                ]],
            ];
        }

        $snap = $root->mintSnapshot(['packages' => $packages]);
        $keyRow = [
            'key_id' => 'bench-root',
            'algorithm' => 'ed25519',
            'public_key' => $root->publicKey(),
            'status' => 'active',
            'valid_from' => null,
            'valid_until' => null,
        ];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $samples = [];
        $errors = 0;
        for ($i = 0; $i < $iterations; $i++) {
            $t0 = hrtime(true);
            try {
                $verifier->verify($snap['json'], $snap['signature'], 'bench-root', [$keyRow], 'rb-registry-snapshot.v1', $now);
            } catch (\Throwable) {
                $errors++;
            }
            $samples[] = (hrtime(true) - $t0) / 1_000_000;
        }

        return [
            'route_or_job' => 'registry_signature_verify',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'in-memory rb-registry-snapshot.v1 (100 packages, ' . strlen($snap['json']) . ' bytes)',
            'role_assignment_count' => 0,
            'installed_package_count' => 100,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => $iterations . ' iterations',
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => 0,
            'query_time_ms' => 0.0,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /** @return array<string,mixed> */
    public function measureInstallUpdate(
        PackageLifecycleService $lifecycle,
        PackageUpdateService $updates,
        Database $db,
        PackageArtifactStore $store,
        int $samples = 8,
    ): array {
        $samples = max(1, $samples);
        $prefix = 'bench' . bin2hex(random_bytes(4));
        $pair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($pair);
        $secretKey = sodium_crypto_sign_secretkey($pair);
        $keyId = $prefix . '-root';
        $admin = $this->benchAdmin($db, $prefix);

        $durations = [];
        for ($i = 0; $i < $samples; $i++) {
            $seeded = $this->seedBenchPackage($db, $store, $prefix, $i, $keyId, $publicKey, $secretKey);

            $t0 = hrtime(true);
            $installedId = $lifecycle->install($admin, 'password123', $seeded['package_id'], $seeded['release_v1_id']);
            $lifecycle->consent($admin, 'password123', $installedId);
            $lifecycle->enable($admin, 'password123', $installedId);
            $updates->update($admin, 'password123', $installedId, $seeded['release_v2_id']);
            $durations[] = (hrtime(true) - $t0) / 1_000_000;
        }

        return [
            'route_or_job' => 'package_install_update',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'synthetic rb-release.v1 package lifecycle samples',
            'role_assignment_count' => 0,
            'installed_package_count' => $samples,
            'concurrency' => 1,
            'cache_state' => 'warm artifact cache',
            'window' => $samples . ' samples',
            'samples' => $samples,
            'p50' => self::percentile($durations, 50),
            'p95' => self::percentile($durations, 95),
            'p99' => self::percentile($durations, 99),
            'query_count' => null,
            'query_time_ms' => round(array_sum($durations), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => 0.0,
        ];
    }

    private function benchAdmin(Database $db, string $prefix): User
    {
        $users = new UserRepository($db);
        $id = $users->create([
            'username' => $prefix . '_admin',
            'email' => $prefix . '_admin@example.test',
            'password_hash' => (new PasswordHasher())->hash('password123'),
            'display_name' => 'Package Budget Admin',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin = $users->findEntity($id);
        if ($admin === null) {
            throw new \RuntimeException('Unable to create package budget admin.');
        }

        return $admin;
    }

    /**
     * @return array{package_id:int,release_v1_id:int,release_v2_id:int}
     */
    private function seedBenchPackage(
        Database $db,
        PackageArtifactStore $store,
        string $prefix,
        int $i,
        string $keyId,
        string $publicKey,
        string $secretKey,
    ): array {
        $uid = 'bench/pkg-' . $prefix . '-' . $i;
        $registryId = $db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 0)',
            [$prefix . '-registry-' . $i, 'Budget Registry ' . $i, 'https://registry.invalid'],
        );
        $db->insert(
            'INSERT INTO registry_trust_keys (registry_id, key_id, algorithm, public_key, status, valid_from) VALUES (?, ?, ?, ?, \'active\', UTC_TIMESTAMP())',
            [$registryId, $keyId, 'ed25519', $publicKey],
        );
        $publisherId = $db->insert(
            'INSERT INTO package_publishers (publisher_uid, display_name, verified_at) VALUES (?, ?, UTC_TIMESTAMP())',
            [$prefix . '-publisher-' . $i, 'Budget Publisher ' . $i],
        );
        $packageId = $db->insert(
            'INSERT INTO packages (package_uid, registry_id, publisher_id, name, type, trust_class) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $registryId, $publisherId, 'Budget Package ' . $i, 'theme', 'reviewed_declarative'],
        );

        $v1 = $this->mintBenchRelease($uid, '1.0.0', 'Budget Package ' . $i, $keyId, $secretKey);
        $v1Id = $this->insertBenchRelease($db, $packageId, $v1);
        $store->put($v1['digest'], $v1['json']);

        $v2 = $this->mintBenchRelease($uid, '1.1.0', 'Budget Package ' . $i, $keyId, $secretKey);
        $v2Id = $this->insertBenchRelease($db, $packageId, $v2);
        $store->put($v2['digest'], $v2['json']);

        $db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$v2Id, $packageId]);

        return ['package_id' => (int) $packageId, 'release_v1_id' => (int) $v1Id, 'release_v2_id' => (int) $v2Id];
    }

    /**
     * @return array{json:string,signature:string,key_id:string,digest:string,manifest:array<string,mixed>,manifest_json:string,version:string}
     */
    private function mintBenchRelease(string $uid, string $version, string $name, string $keyId, string $secretKey): array
    {
        $manifest = [
            'format' => 'rb-manifest.v2',
            'uid' => $uid,
            'type' => 'theme',
            'version' => $version,
            'name' => $name,
            'description' => 'Synthetic package lifecycle budget fixture.',
            'license' => 'MIT',
            'core' => ['min' => '0.1.0', 'max' => null],
            'permissions' => [
                'capabilities' => [],
                'data_classes' => ['package.own_storage'],
                'api_scopes' => [],
                'events' => [],
                'outbound_hosts' => [],
                'jobs' => [],
            ],
            'storage_quota_kb' => 64,
            'support' => ['homepage' => 'https://example.test/package-budget'],
        ];
        $json = json_encode([
            'format' => 'rb-release.v1',
            'uid' => $uid,
            'version' => $version,
            'review' => [
                'status' => 'approved',
                'decided_at' => '2026-07-01T00:00:00Z',
            ],
            'manifest' => $manifest,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'json' => $json,
            'signature' => sodium_crypto_sign_detached($json, $secretKey),
            'key_id' => $keyId,
            'digest' => hash('sha256', $json),
            'manifest' => $manifest,
            'manifest_json' => json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'version' => $version,
        ];
    }

    /** @param array{json:string,signature:string,key_id:string,digest:string,manifest:array<string,mixed>,manifest_json:string,version:string} $release */
    private function insertBenchRelease(Database $db, int $packageId, array $release): int
    {
        return $db->insert(
            'INSERT INTO package_releases (package_id, version, digest, source_url, license, core_min, core_max, manifest_json, signature, signed_key_id, review_status, channel, advisory_status, published_at)'
            . ' VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, \'approved\', \'stable\', \'none\', UTC_TIMESTAMP())',
            [
                $packageId,
                $release['version'],
                $release['digest'],
                $release['manifest']['license'] ?? null,
                $release['manifest']['core']['min'] ?? null,
                $release['manifest']['core']['max'] ?? null,
                $release['manifest_json'],
                $release['signature'],
                $release['key_id'],
            ],
        );
    }

    /** @param list<float> $samples */
    private static function percentile(array $samples, int $p): float
    {
        if ($samples === []) {
            return 0.0;
        }
        sort($samples);
        $rank = (int) ceil(($p / 100) * count($samples)) - 1;
        $rank = max(0, min($rank, count($samples) - 1));
        return round($samples[$rank], 4);
    }
}
