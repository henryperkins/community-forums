<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CapabilityRepository;
use App\Repository\CategoryRepository;
use App\Repository\IdentityProviderRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityResolver;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Service\OAuth\HttpClient as OAuthHttpClient;
use App\Service\OAuth\Oidc\ClaimMapper;
use App\Service\OAuth\Oidc\JwksCache;
use App\Service\OAuth\Oidc\JwtVerifier;
use App\Service\OAuth\Oidc\OidcDiscovery;
use App\Service\OAuth\Oidc\OidcProvider;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnVerifier;
use App\Security\WriteGate;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Packages\ThemeStateService;
use App\Support\Base64Url;

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
     * §11.3 "assignment-change propagation" — measured-only (ADR 0004 D11 sets no
     * gate). Structurally near-zero: decisions read the live `role_assignments`
     * table and `RoleAssignmentService::revoke()` calls `$resolver->invalidate()`
     * in-request, so there is no cache to go stale. What's timed is the wall-clock
     * of that revoke-then-decide pair, with `RoleAssignmentService` +
     * `CapabilityResolver` constructed exactly as
     * `tests/Integration/Service/RoleAssignmentServiceTest.php` builds them. The
     * `anomalies` count (decisions still `allowed` after revoke) is a correctness
     * sentinel — expected 0. Mutates, so it must run inside the caller's rollback
     * transaction (same contract as measureInstallUpdate/measureThemeBuildApply).
     *
     * @return array<string,mixed> the PHASE_5_PLAN measurement envelope
     */
    public function measureAssignmentChangePropagation(int $iterations = 200): array
    {
        if (!$this->db->pdo()->inTransaction()) {
            throw new \RuntimeException('Assignment-change propagation sampling must run inside a caller-owned rollback transaction.');
        }
        $iterations = max(1, $iterations);

        $users = new UserRepository($this->db);
        $roles = new RoleRepository($this->db);
        $roleCapabilities = new RoleCapabilityRepository($this->db);
        $assignments = new RoleAssignmentRepository($this->db);
        $resolver = $this->buildResolver();
        $service = new RoleAssignmentService(
            $this->db,
            $roles,
            $roleCapabilities,
            $assignments,
            new RoleAssignmentHistoryRepository($this->db),
            $users,
            new BoardRepository($this->db),
            new CategoryRepository($this->db),
            $resolver,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            new ModerationLogRepository($this->db),
        );

        // One-time fixture (untimed): an admin revoker, a subject, and a custom
        // role holding an enforced capability so pre-revoke the grant is live.
        $suffix = bin2hex(random_bytes(4));
        $hash = (new PasswordHasher())->hash('password123');
        $adminId = $users->create([
            'username' => 'propbench_admin_' . $suffix,
            'email' => 'propbench_admin_' . $suffix . '@example.test',
            'password_hash' => $hash,
            'display_name' => null,
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin = User::fromRow((array) $users->find($adminId));
        $subjectId = $users->create([
            'username' => 'propbench_subject_' . $suffix,
            'email' => 'propbench_subject_' . $suffix . '@example.test',
            'password_hash' => $hash,
            'display_name' => null,
            'role' => 'user',
            'status' => 'active',
        ]);
        $subject = User::fromRow((array) $users->find($subjectId));
        $roleId = $roles->create([
            'role_key' => 'custom.propbench_' . $suffix,
            'name' => 'Propagation Bench Role',
            'description' => null,
            'created_by' => null,
        ]);
        $roleCapabilities->replaceForRole(
            $roleId,
            array_values((new CapabilityRepository($this->db))->idsByKeys(['core.thread.lock'])),
        );

        $samples = [];
        $anomalies = 0;
        for ($i = 0; $i < $iterations; $i++) {
            // Untimed: a fresh site-scope assignment to revoke this iteration.
            $assignmentId = $assignments->create([
                'subject_id' => $subjectId,
                'role_id' => $roleId,
                'scope_type' => 'site',
                'scope_id' => null,
            ]);

            $t0 = hrtime(true);
            $service->revoke($admin, $assignmentId, 'propagation-bench');
            $decision = $resolver->can($subject, 'core.thread.lock', []);
            $samples[] = (hrtime(true) - $t0) / 1_000_000;

            if ($decision->allowed !== false) {
                $anomalies++;
            }
        }

        return [
            'route_or_job' => 'role_assignment_change_propagation',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'synthetic custom-role revoke→can pairs',
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold (invalidate() per revoke)',
            'window' => $iterations . ' iterations',
            'samples' => count($samples),
            'anomalies' => $anomalies,
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => null,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($anomalies / count($samples), 4),
        ];
    }

    /**
     * §11.3 "simulator duration" — measured-only (ADR 0004 D11 sets no gate).
     * Times `PermissionSimulatorService::simulate()` on the Foundation F9 fixture
     * (the same corpus the resolver budget measures on), with the simulator
     * constructed exactly as `tests/Integration/Service/PermissionSimulatorTest.php`
     * builds it. Read-only; when the F9 fixture is absent it returns an empty
     * sample (p95 0.0) rather than throwing, matching measureResolver.
     *
     * @return array<string,mixed> the PHASE_5_PLAN measurement envelope
     */
    public function measureSimulatorDuration(int $iterations = 200): array
    {
        $iterations = max(1, $iterations);

        $userRepo = new UserRepository($this->db);
        $boardRepo = new BoardRepository($this->db);
        $simulator = new PermissionSimulatorService(
            $this->buildResolver(),
            $userRepo,
            $boardRepo,
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
        );

        $fixtureUsers = $this->db->fetchAll("SELECT username FROM users WHERE username LIKE 'p5fix\\_%' ORDER BY id ASC");
        $fixtureBoards = $this->db->fetchAll("SELECT id FROM boards WHERE slug LIKE 'p5fix\\_%' ORDER BY id ASC");
        $viewerRow = $userRepo->findByUsername('p5fix_admin');
        $viewer = $viewerRow !== null ? User::fromRow($viewerRow) : null;

        $samples = [];
        $errors = 0;
        if ($fixtureUsers !== [] && $fixtureBoards !== [] && $viewer !== null) {
            for ($i = 0; $i < $iterations; $i++) {
                $actorRef = (string) $fixtureUsers[$i % count($fixtureUsers)]['username'];
                $boardId = (int) $fixtureBoards[$i % count($fixtureBoards)]['id'];

                $t0 = hrtime(true);
                $result = $simulator->simulate($viewer, $actorRef, 'core.thread.lock', $boardId, null);
                $samples[] = (hrtime(true) - $t0) / 1_000_000;

                if ($result['error'] !== null) {
                    $errors++;
                }
            }
        }

        return [
            'route_or_job' => 'permission_simulator_simulate',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'phase5_fixture_v' . Phase5FixtureSeeder::FIXTURE_VERSION,
            'role_assignment_count' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM role_assignments'),
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => count($samples) . ' iterations',
            'samples' => count($samples),
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => null,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /**
     * Builds a fresh DB-backed resolver from `$this->db` — the same eight-arg
     * construction the resolver family of tests uses. Each call returns a new
     * instance (its own decision memo), which is what the simulator/propagation
     * samplers want.
     */
    private function buildResolver(): CapabilityResolver
    {
        return new CapabilityResolver(
            new RoleCapabilityRepository($this->db),
            new RoleAssignmentRepository($this->db),
            new LegacyAuthorityProjection(new BoardModeratorRepository($this->db)),
            new ProtectedOwnerRepository($this->db),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
        );
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

    /**
     * Inc 8 (P5-12) — the D11 `oidc.discovery_p95_cached/cold` samplers. Builds
     * its own bench provider row (removed afterwards; run inside the caller's
     * rollback transaction for byte-identical DBs).
     *
     * Cached: the per-sign-in hot path — provider-row load, cached discovery
     * document through the real OidcProvider::authorizeUrl(), and a JWKS cache
     * hit. The bench transport throws on ANY fetch, proving the path is
     * cache-only. Cold: the full resolution per iteration — discovery fetch +
     * validation + persist, then the pinned JWKS refresh + persist — over an
     * in-process canned transport. Remote-IdP network RTT is environment-owned
     * and excluded; the D11 ceilings exist to catch app-side pathology
     * (per-login refetch loops, quadratic parsing), which this measures.
     *
     * @return array<string,mixed> §11.3 measurement-envelope row
     */
    public function measureOidcDiscovery(bool $cold, int $iterations = 200): array
    {
        $providers = new IdentityProviderRepository($this->db);
        $issuer = 'https://oidc-bench.idp.test';
        $wellKnown = $issuer . '/.well-known/openid-configuration';
        $jwksUri = $issuer . '/oauth/discovery/keys';
        $doc = [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'jwks_uri' => $jwksUri,
        ];
        $jwks = ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'kid' => 'bench-k1', 'n' => 'AQAB', 'e' => 'AQAB']]];

        // Canned in-process transport; unscripted URLs throw (the cached arm
        // scripts nothing, so any fetch there fails the sample loudly).
        $transport = new class($cold ? [$wellKnown => $doc, $jwksUri => $jwks] : []) extends OAuthHttpClient {
            /** @param array<string,array<string,mixed>> $responses */
            public function __construct(private array $responses)
            {
            }

            public function getJson(string $url, ?string $bearer = null): array
            {
                return $this->responses[$url]
                    ?? throw new \RuntimeException('oidc bench: unexpected fetch ' . $url);
            }

            public function postForm(string $url, array $form, array $headers = []): array
            {
                throw new \RuntimeException('oidc bench: unexpected POST ' . $url);
            }
        };

        $benchKey = 'oidc-bench-' . bin2hex(random_bytes(4));
        $id = $providers->create([
            'provider_key' => $benchKey,
            'display_name' => 'OIDC bench IdP',
            'issuer' => $issuer,
            'client_id' => 'bench-client',
            'client_secret_ref' => 'svcsec_bench',
        ]);

        $discovery = new OidcDiscovery($transport);
        $jwksCache = new JwksCache($providers, $transport);
        // Never invoked by the discovery scope; present because OidcProvider
        // requires the collaborator.
        $vault = new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            new Config([]),
        );

        if (!$cold) {
            $providers->cacheDiscovery($id, (string) json_encode($doc));
            $providers->cacheJwks($id, (string) json_encode($jwks));
        }

        $samples = [];
        $errors = 0;
        for ($i = 0; $i < $iterations; $i++) {
            if ($cold) {
                // Bench scaffolding, outside the timer: force every iteration cold.
                $this->db->run(
                    'UPDATE identity_providers SET discovery_cache_json = NULL, discovery_cached_at = NULL,
                            jwks_cache_json = NULL, jwks_cached_at = NULL WHERE id = ?',
                    [$id],
                );
            }

            $t0 = hrtime(true);
            try {
                $row = $providers->find($id) ?? throw new \RuntimeException('bench row vanished');
                if ($cold) {
                    $fetched = $discovery->fetch($issuer);
                    $providers->cacheDiscovery($id, (string) json_encode($fetched));
                    $jwksCache->refresh($row, (string) $fetched['jwks_uri']);
                } else {
                    $provider = new OidcProvider(
                        $row,
                        $providers,
                        $discovery,
                        $jwksCache,
                        new JwtVerifier(),
                        new ClaimMapper(),
                        $vault,
                        $transport,
                    );
                    $provider->authorizeUrl('https://forum.test/auth/' . $benchKey . '/callback', 'state', 'challenge', 'nonce');
                    $jwksCache->keys($row, $jwksUri);
                }
            } catch (\Throwable) {
                $errors++;
            }
            $samples[] = (hrtime(true) - $t0) / 1_000_000;
        }

        $this->db->run('DELETE FROM identity_providers WHERE id = ?', [$id]);

        return [
            'route_or_job' => $cold ? 'oidc_discovery_cold' : 'oidc_discovery_cached',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => $cold
                ? 'bench generic-OIDC row; full discovery+JWKS fetch/validate/persist per iteration; in-process transport (remote RTT excluded)'
                : 'bench generic-OIDC row; row load + cached discovery via OidcProvider::authorizeUrl + JWKS cache hit (transport throws on any fetch)',
            'role_assignment_count' => 0,
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => $cold ? 'cold' : 'warm',
            'window' => $iterations . ' iterations',
            'samples' => count($samples),
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => $cold ? 4 * $iterations : 1 * $iterations,
            'query_time_ms' => round(array_sum($samples), 4),
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /**
     * D11 `invitation.redemption_p95` (P5-13, Inc 9): the full production
     * redemption path per iteration — uniform token check, guarded consume,
     * `AuthService::register` INCLUDING a PRODUCTION-COST Argon2id hash
     * (which dominates), redemption row, board grant, audit. Both the test
     * bootstrap and `verify:phase5-budgets` weaken the process-wide hasher
     * for fixture work, so the bench suspends that override for its timed
     * region and restores it afterwards — otherwise the published number
     * understates production by orders of magnitude. Issuance happens
     * outside the timer (its budget is not the redemption budget).
     *
     * Self-fixturing (bench admin + board from `$this->db`), but it CREATES
     * user rows, so it must run inside the caller's rollback transaction
     * (mirrors measureInstallUpdate).
     *
     * @return array<string,mixed> §11.3 measurement-envelope row
     */
    public function measureInvitationRedemption(int $iterations = 60): array
    {
        if (!$this->db->pdo()->inTransaction()) {
            throw new \RuntimeException('Invitation redemption sampling must run inside a caller-owned rollback transaction.');
        }

        $savedHashOptions = PasswordHasher::defaultOptions();
        PasswordHasher::setDefaultOptions(null); // production-cost Argon2id inside the timed region

        try {
            $users = new UserRepository($this->db);
            $service = new InvitationService(
                $this->db,
                new InvitationRepository($this->db),
                new AuthService($users, new PasswordHasher(), new Config([])),
                new BoardRepository($this->db),
                new BoardMemberRepository($this->db),
                new ModerationLogRepository($this->db),
            );

            $suffix = bin2hex(random_bytes(4));
            $adminId = $users->create([
                'username' => 'invbenchadmin' . $suffix,
                'email' => 'invbenchadmin' . $suffix . '@bench.test',
                'password_hash' => null,
                'display_name' => null,
                'role' => 'admin',
                'status' => 'active',
            ]);
            $admin = $users->findEntity($adminId) ?? throw new \RuntimeException('bench admin vanished');

            $categoryId = (new CategoryRepository($this->db))->create('Invite bench ' . $suffix, 999);
            $boardId = (new BoardRepository($this->db))->create([
                'category_id' => $categoryId,
                'name' => 'Invite bench board',
                'slug' => 'invite-bench-' . $suffix,
                'visibility' => 'public',
            ]);

            $samples = [];
            $errors = 0;
            for ($i = 0; $i < $iterations; $i++) {
                // Bench scaffolding, outside the timer: a fresh single-use invitation.
                $invite = $service->create($admin, ['onboarding_board_id' => (string) $boardId]);
                $input = [
                    'username' => 'invbench' . $i . $suffix,
                    'email' => 'invbench' . $i . $suffix . '@bench.test',
                    'password' => 'bench-password-123',
                    'password_confirm' => 'bench-password-123',
                ];

                $t0 = hrtime(true);
                try {
                    $service->redeem($invite['token'], $input, '203.0.113.7');
                } catch (\Throwable) {
                    $errors++;
                }
                $samples[] = (hrtime(true) - $t0) / 1_000_000;
            }
        } finally {
            PasswordHasher::setDefaultOptions($savedHashOptions);
        }

        return [
            'route_or_job' => 'invitation_redemption',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'fresh single-use board-granting invitation per iteration; timed region = preview + guarded consume + register with production-cost Argon2id hash (harness weakening suspended) + redemption row + board grant + audit',
            'role_assignment_count' => 0,
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'warm',
            'window' => $iterations . ' iterations',
            'samples' => count($samples),
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => null, // not measured (the region is hash-dominated, not query-dominated)
            'query_time_ms' => null,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'queue_age' => null,
            'error_rate' => $samples === [] ? 0.0 : round($errors / count($samples), 4),
        ];
    }

    /**
     * Measures full server-side WebAuthn assertion verification from a committed
     * public-only fixture: base64url decode, CBOR/COSE parse, and OpenSSL verify.
     *
     * @return array<string,mixed> the PHASE_5_PLAN measurement envelope
     */
    public function measureWebauthnCeremony(?string $fixturePath = null): array
    {
        $rp = new RelyingParty('http://localhost:8000', null, 'testing');
        $verifier = new WebAuthnVerifier($rp);

        $path = $fixturePath ?? dirname(__DIR__, 2) . '/docs/evidence/phase5/webauthn-budget-fixture.json';
        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $cose = Base64Url::decode((string) ($fixture['public_key_cose'] ?? ''));
        if ($cose === null || $cose === '') {
            throw new \RuntimeException('Invalid public_key_cose in WebAuthn budget fixture.');
        }

        $samples = [];
        $errors = 0;
        foreach ((array) ($fixture['samples'] ?? []) as $sample) {
            $challenge = Base64Url::decode((string) ($sample['challenge'] ?? ''));
            $payload = $sample['payload'] ?? null;
            if ($challenge === null || $challenge === '' || !is_array($payload)) {
                throw new \RuntimeException('Invalid WebAuthn budget sample.');
            }
            $t0 = hrtime(true);
            try {
                $verifier->verifyAssertion($payload, $challenge, $cose, (int) ($sample['stored_sign_count'] ?? 0), false);
            } catch (\Throwable) {
                $errors++;
            }
            $samples[] = (hrtime(true) - $t0) / 1_000_000;
        }

        return [
            'route_or_job' => 'webauthn_ceremony_assertion_verify',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($this->db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'public-only webauthn-budget-fixture.json assertions',
            'role_assignment_count' => 0,
            'installed_package_count' => 0,
            'concurrency' => 1,
            'cache_state' => 'cold',
            'window' => count($samples) . ' assertions',
            'samples' => count($samples),
            'p50' => self::percentile($samples, 50),
            'p95' => self::percentile($samples, 95),
            'p99' => self::percentile($samples, 99),
            'query_count' => 0,
            'query_time_ms' => round(array_sum($samples), 4),
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
        if (!$db->pdo()->inTransaction()) {
            throw new \RuntimeException('Package install/update sampling must run inside a caller-owned rollback transaction.');
        }

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

    /** @return array<string,mixed> */
    public function measureThemeBuildApply(
        PackageLifecycleService $lifecycle,
        ThemeStateService $themes,
        Database $db,
        PackageArtifactStore $store,
        int $samples = 8,
    ): array {
        if (!$db->pdo()->inTransaction()) {
            throw new \RuntimeException('Theme build/apply sampling must run inside a caller-owned rollback transaction.');
        }

        $samples = max(1, $samples);
        $prefix = 'themebench' . (int) $db->fetchValue("SELECT COUNT(*) FROM packages WHERE package_uid LIKE 'bench/pkg-themebench%'");
        $pair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($pair);
        $secretKey = sodium_crypto_sign_secretkey($pair);
        $keyId = $prefix . '-root';
        $admin = $this->benchAdmin($db, $prefix);

        $durations = [];
        for ($i = 0; $i < $samples; $i++) {
            $seeded = $this->seedBenchPackage(
                $db,
                $store,
                $prefix . '-theme',
                $i,
                $keyId,
                $publicKey,
                $secretKey,
                self::themeBenchAccent($i),
            );
            $installedId = $lifecycle->install($admin, 'password123', $seeded['package_id'], $seeded['release_v1_id']);
            $lifecycle->consent($admin, 'password123', $installedId);
            $lifecycle->enable($admin, 'password123', $installedId);

            $t0 = hrtime(true);
            $themes->activate($admin, 'password123', $installedId);
            $durations[] = (hrtime(true) - $t0) / 1_000_000;
        }

        return [
            'route_or_job' => 'theme_build_apply',
            'hardware_class' => getenv('RB_HARDWARE_CLASS') ?: 'unknown',
            'os_isolation_profile' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'db_version' => (string) ($db->fetchValue('SELECT VERSION()') ?? ''),
            'data_fixture' => 'synthetic theme package build/activate samples',
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
        string $accent = '#8f3d12',
    ): array {
        $uid = 'bench/pkg-' . $prefix . '-' . $i;
        $registryId = $db->insert(
            'INSERT INTO package_registries (source_id, display_name, base_url, is_enabled) VALUES (?, ?, ?, 1)',
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

        $v1 = $this->mintBenchRelease($uid, '1.0.0', 'Budget Package ' . $i, $keyId, $secretKey, $accent);
        $v1Id = $this->insertBenchRelease($db, $packageId, $v1);
        $store->put($v1['digest'], $v1['json']);

        $v2 = $this->mintBenchRelease($uid, '1.1.0', 'Budget Package ' . $i, $keyId, $secretKey, $accent);
        $v2Id = $this->insertBenchRelease($db, $packageId, $v2);
        $store->put($v2['digest'], $v2['json']);

        $db->run('UPDATE packages SET latest_release_id = ? WHERE id = ?', [$v2Id, $packageId]);

        return ['package_id' => (int) $packageId, 'release_v1_id' => (int) $v1Id, 'release_v2_id' => (int) $v2Id];
    }

    /**
     * @return array{json:string,signature:string,key_id:string,digest:string,manifest:array<string,mixed>,manifest_json:string,version:string}
     */
    private function mintBenchRelease(
        string $uid,
        string $version,
        string $name,
        string $keyId,
        string $secretKey,
        string $accent = '#8f3d12',
    ): array
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
            'theme' => [
                'schema_version' => 1,
                'tokens' => ['--accent' => $accent, '--surface' => '#fff7dc', '--text' => '#241706'],
                'dark_tokens' => ['--accent' => '#d2b062', '--surface' => '#283440', '--text' => '#ece4d2'],
                'assets' => [],
            ],
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

    private static function themeBenchAccent(int $i): string
    {
        $accents = ['#8f3d12', '#1f4fbf', '#285f56', '#7346a2', '#9a3412', '#2563eb', '#4d6f10', '#7c2d12'];

        return $accents[$i % count($accents)];
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
