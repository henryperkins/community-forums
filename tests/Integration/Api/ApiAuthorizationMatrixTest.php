<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Core\FeatureFlags;
use App\Core\Response;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

/**
 * SP0 release-evidence retrofit for the landed api_tokens seam (SLICE-API-TOKENS).
 * A direct-request authorization matrix over the three read-only /api/v1 endpoints:
 * per-scope denial (403, audited via auditScopeDenied), Bearer-scheme enforcement,
 * private-board absence, flag-dark 404, and 429 rate-limiting. No production code is
 * touched — this file is the characterization evidence that raises R3→R4.
 */
final class ApiAuthorizationMatrixTest extends TestCase
{
    private function setApiTokens(bool $on): void
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => $on]);
    }

    /** @param array<int,string> $scopes */
    private function mintToken(array $scopes): string
    {
        $this->setApiTokens(true); // mint requires the flag ON
        $svc = new ApiTokenService(
            $this->db,
            new ApiTokenRepository($this->db),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
        );
        $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
        return $svc->mint($admin, 'password123', 'matrix', $scopes, null)['token'];
    }

    /** @param array<string,mixed> $query */
    private function apiGet(string $path, ?string $authorization, array $query = []): Response
    {
        $server = $authorization === null ? [] : ['HTTP_AUTHORIZATION' => $authorization];
        return $this->requestWithServer('GET', $path, [], $query, $server);
    }

    private function publicBoardId(): int
    {
        return (int) $this->makeBoard($this->makeCategory(), ['visibility' => 'public'])['id'];
    }

    /** @return array<string,array{0:list<string>,1:string,2:int}> */
    public static function scopeMatrix(): array
    {
        return [
            'boards, read:boards granted → 200'   => [['read:boards'],  '/api/v1/boards',            200],
            'boards, only read:threads → 403'     => [['read:threads'], '/api/v1/boards',            403],
            'threads, read:threads granted → 200' => [['read:threads'], '/api/v1/boards/%d/threads', 200],
            'threads, only read:boards → 403'     => [['read:boards'],  '/api/v1/boards/%d/threads', 403],
            'me is scope-agnostic → 200'          => [['read:threads'], '/api/v1/me',                200],
        ];
    }

    /** @param list<string> $grantedScopes */
    #[DataProvider('scopeMatrix')]
    public function test_scope_matrix_enforces_least_privilege(array $grantedScopes, string $endpoint, int $expected): void
    {
        $path = str_contains($endpoint, '%d') ? sprintf($endpoint, $this->publicBoardId()) : $endpoint;
        $token = $this->mintToken($grantedScopes);
        self::assertSame($expected, $this->apiGet($path, 'Bearer ' . $token)->status());
    }

    public function test_scope_denial_is_audited_via_audit_scope_denied(): void
    {
        $before = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'",
        );
        $r = $this->apiGet('/api/v1/boards', 'Bearer ' . $this->mintToken(['read:threads'])); // lacks read:boards
        self::assertSame(403, $r->status());
        self::assertSame('read:boards', json_decode($r->body(), true)['scope']);
        self::assertSame(
            $before + 1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'"),
            'a scope denial must write exactly one auditScopeDenied row',
        );
    }

    public function test_bearer_scheme_is_mandatory_and_401s_are_not_audited(): void
    {
        $token = $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', 'Bearer ' . $token)->status()); // correct scheme
        self::assertSame(401, $this->apiGet('/api/v1/me', $token)->status());             // raw token, no scheme
        self::assertSame(401, $this->apiGet('/api/v1/me', 'Basic ' . $token)->status());  // wrong scheme
        self::assertSame(401, $this->apiGet('/api/v1/me', null)->status());               // absent header
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_unauthorized'"),
            'unknown/absent-token 401s must never be audited',
        );
    }

    public function test_private_board_is_absent_and_its_threads_are_404(): void
    {
        $cat = $this->makeCategory();
        $this->makeBoard($cat, ['visibility' => 'public', 'name' => 'Public Matrix']);
        $private = $this->makeBoard($cat, ['visibility' => 'private', 'name' => 'Private Matrix']);
        $token = 'Bearer ' . $this->mintToken(['read:boards', 'read:threads']);

        $names = array_column(json_decode($this->apiGet('/api/v1/boards', $token)->body(), true)['boards'], 'name');
        self::assertContains('Public Matrix', $names);
        self::assertNotContains('Private Matrix', $names, 'private boards must never appear in the API listing');

        // Even holding read:threads, a private board is a 404 — never a 403/200 existence leak.
        self::assertSame(404, $this->apiGet('/api/v1/boards/' . (int) $private['id'] . '/threads', $token)->status());
    }

    public function test_flag_dark_makes_every_endpoint_404_even_with_a_valid_token(): void
    {
        $token = 'Bearer ' . $this->mintToken(['read:boards', 'read:threads']); // minted while flag ON
        $boardId = $this->publicBoardId();
        $this->setApiTokens(false); // kill switch

        foreach (['/api/v1/me', '/api/v1/boards', '/api/v1/boards/' . $boardId . '/threads'] as $path) {
            $r = $this->apiGet($path, $token);
            self::assertSame(404, $r->status(), "$path must 404 while api_tokens is dark");
            self::assertSame('not_found', json_decode($r->body(), true)['error']);
        }
    }

    public function test_rate_limit_returns_429_after_the_api_policy_max(): void
    {
        $bearer = 'Bearer ' . $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', $bearer)->status()); // call #1
        for ($i = 0; $i < 119; $i++) {                                          // calls #2..#120
            $this->apiGet('/api/v1/me', $bearer);
        }
        self::assertSame(429, $this->apiGet('/api/v1/me', $bearer)->status(), 'the 121st call exceeds api [120,60]');
    }
}
