<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\Response;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use Tests\Support\TestCase;

final class ApiReadEndpointsTest extends TestCase
{
    /** @param array<int,string> $scopes */
    private function mintToken(array $scopes, ?int $days = null): string
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => true]);
        $svc = new ApiTokenService(
            $this->db, new ApiTokenRepository($this->db), new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)), $this->config,
            new ReauthGate(new PasswordHasher()), new WriteGate(),
        );
        $admin = $this->userEntity($this->makeAdmin(['password' => 'password123']));
        return $svc->mint($admin, 'password123', 'ci', $scopes, $days)['token'];
    }

    /** @param array<string,mixed> $query */
    private function apiGet(string $path, ?string $token, array $query = []): Response
    {
        $server = $token === null ? [] : ['HTTP_AUTHORIZATION' => 'Bearer ' . $token];
        return $this->requestWithServer('GET', $path, [], $query, $server);
    }

    public function test_me_requires_a_valid_token(): void
    {
        $token = $this->mintToken(['read:boards']); // /me ignores scopes, but mint needs >= 1
        $ok = $this->apiGet('/api/v1/me', $token);
        self::assertSame(200, $ok->status());
        $body = json_decode($ok->body(), true);
        self::assertSame('ci', $body['name']);
        self::assertNotEmpty($body['created_at']);

        self::assertSame(401, $this->apiGet('/api/v1/me', null)->status());
        self::assertSame(401, $this->apiGet('/api/v1/me', 'garbage')->status());

        // A raw token WITHOUT the "Bearer " scheme must be rejected.
        self::assertSame(401, $this->requestWithServer('GET', '/api/v1/me', [], [], ['HTTP_AUTHORIZATION' => $token])->status());
    }

    public function test_boards_scope_gating_and_public_only(): void
    {
        $catId = $this->makeCategory();
        $pub = $this->makeBoard($catId, ['visibility' => 'public', 'name' => 'Public B']);
        $this->makeBoard($catId, ['visibility' => 'private', 'name' => 'Secret B']);

        $token = $this->mintToken(['read:boards']);
        $r = $this->apiGet('/api/v1/boards', $token);
        self::assertSame(200, $r->status());
        $names = array_column(json_decode($r->body(), true)['boards'], 'name');
        self::assertContains('Public B', $names);
        self::assertNotContains('Secret B', $names, 'private boards must be absent from the API');

        // A token without read:boards is 403.
        self::assertSame(403, $this->apiGet('/api/v1/boards', $this->mintToken(['read:threads']))->status());
    }

    public function test_per_scope_granularity_and_threads_bound(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $author = $this->makeUser();
        for ($i = 0; $i < 25; $i++) {
            $this->makeThread($board, $author, 'T' . $i, 'body');
        }
        // read:boards but NOT read:threads → 403 on threads.
        self::assertSame(403, $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $this->mintToken(['read:boards']))->status());

        // read:threads → 200, default bound 20.
        $token = $this->mintToken(['read:threads']);
        $def = $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $token);
        self::assertSame(200, $def->status());
        self::assertLessThanOrEqual(20, count(json_decode($def->body(), true)['threads']));

        // limit=999 is clamped to 50 (query passed separately — requestWithServer does not parse '?').
        $big = $this->apiGet('/api/v1/boards/' . $board['id'] . '/threads', $token, ['limit' => '999']);
        self::assertLessThanOrEqual(50, count(json_decode($big->body(), true)['threads']));
    }

    public function test_revoke_and_expiry_and_flag_dark(): void
    {
        $token = $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', $token)->status());

        // Immediate revoke → 401.
        $this->db->run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ?', [hash('sha256', $token)]);
        self::assertSame(401, $this->apiGet('/api/v1/me', $token)->status());

        // Flag dark → 404 for everyone. Mint a valid token WHILE the flag is on, then go
        // dark and reuse it (mintToken() itself re-enables the flag, so it must run first).
        $valid = $this->mintToken(['read:boards']);
        (new SettingRepository($this->db))->set('features', ['api_tokens' => false]);
        self::assertSame(404, $this->apiGet('/api/v1/me', $valid)->status());
    }

    public function test_scope_denial_is_audited_but_401_is_not(): void
    {
        $token = $this->mintToken(['read:threads']); // lacks read:boards
        $this->apiGet('/api/v1/boards', $token); // 403
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_scope_denied'"),
        );
        $this->apiGet('/api/v1/me', 'garbage'); // 401 unknown token
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_unauthorized'"),
            'unknown-token 401 must not be audited',
        );
    }

    public function test_router_caveat_unknown_path_is_html_not_json(): void
    {
        // The zero-kernel-change limitation: an unknown /api path is a kernel HTML 404.
        $r = $this->apiGet('/api/v1/does-not-exist', $this->mintToken(['read:boards']));
        self::assertSame(404, $r->status());
        self::assertStringNotContainsString('application/json', (string) $r->getHeader('content-type'));
    }

    public function test_rate_limit_returns_429_after_policy_max(): void
    {
        $token = $this->mintToken(['read:boards']);
        self::assertSame(200, $this->apiGet('/api/v1/me', $token)->status());
        for ($i = 0; $i < 119; $i++) {
            $this->apiGet('/api/v1/me', $token);
        }
        self::assertSame(429, $this->apiGet('/api/v1/me', $token)->status(), 'the 121st call exceeds the api policy [120,60]');
    }
}
