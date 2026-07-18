<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ApiTokensDisabledException;
use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\ApiTokenRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Core\DuplicateSubmissionException;
use App\Repository\IdempotencyRepository;
use Tests\Support\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    private function service(bool $enabled = true): ApiTokenService
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => $enabled]);
        return new ApiTokenService(
            $this->db,
            new ApiTokenRepository($this->db),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
            new ReauthGate(new PasswordHasher()),
            new WriteGate(),
            null,
            null,
            new IdempotencyRepository($this->db),
        );
    }

    private function admin(): \App\Domain\User
    {
        return $this->userEntity($this->makeAdmin(['password' => 'password123']));
    }

    public function test_mint_returns_plaintext_and_stores_only_hash(): void
    {
        $res = $this->service()->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        self::assertStringStartsWith('rbt_', $res['token']);
        $stored = (string) $this->db->fetchValue('SELECT token_hash FROM api_tokens WHERE id = ?', [$res['id']]);
        self::assertSame(hash('sha256', $res['token']), $stored);
    }

    public function test_authenticate_round_trips_then_revoke_and_expiry_deny(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $res = $svc->mint($admin, 'password123', 'ci', ['read:boards'], null);
        $p = $svc->authenticate('Bearer ' . $res['token']);
        self::assertNotNull($p);
        self::assertSame(['read:boards'], $p->scopes());
        self::assertTrue($p->hasScope('read:boards'));

        $svc->revoke($admin, $res['id']);
        self::assertNull($svc->authenticate('Bearer ' . $res['token']), 'revoked token must not authenticate');

        $res2 = $svc->mint($admin, 'password123', 'ci2', ['read:boards'], null);
        $this->db->run('UPDATE api_tokens SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?', [$res2['id']]);
        self::assertNull($svc->authenticate('Bearer ' . $res2['token']), 'expired token must not authenticate');
    }

    public function test_revoke_is_idempotent_and_audits_only_real_changes(): void
    {
        $svc = $this->service();
        $admin = $this->admin();
        $res = $svc->mint($admin, 'password123', 'ci', ['read:boards'], null);

        $revokedRows = fn (int $id): int => (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_revoked' AND target_id = ?",
            [$id],
        );

        $svc->revoke($admin, $res['id']);
        self::assertSame(1, $revokedRows($res['id']), 'a real revoke writes exactly one audit row');

        // Already revoked -> repo affects 0 rows -> no second audit row (idempotent).
        $svc->revoke($admin, $res['id']);
        self::assertSame(1, $revokedRows($res['id']), 'a no-op revoke forges no audit row');

        // Unknown id -> nothing changes, nothing audited.
        $svc->revoke($admin, 999999);
        self::assertSame(0, $revokedRows(999999), 'revoking an unknown id forges no audit row');
    }

    public function test_flag_dark_kill_switch_on_authenticate(): void
    {
        $res = $this->service(true)->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        self::assertNull($this->service(false)->authenticate('Bearer ' . $res['token']));
    }

    public function test_wrong_password_blocks_mint(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->mint($this->admin(), 'WRONG', 'ci', ['read:boards'], null);
    }

    public function test_suspended_admin_cannot_mint(): void
    {
        $admin = $this->userEntity($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->expectException(ForbiddenException::class);
        $this->service()->mint($admin, 'password123', 'ci', ['read:boards'], null);
    }

    public function test_flag_dark_blocks_mint(): void
    {
        $this->expectException(ApiTokensDisabledException::class);
        $this->service(false)->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
    }

    /** @return array<string,array{0:array<int,mixed>,1:string,2:?int}> name => [scopes, name, expiresInDays] */
    public static function invalidMintCases(): array
    {
        return [
            'empty scopes' => [[], 'ci', null],
            'duplicate scopes' => [['read:boards', 'read:boards'], 'ci', null],
            'unknown scope' => [['write:all'], 'ci', null],
            'blank name' => [['read:boards'], '   ', null],
            'long name' => [['read:boards'], str_repeat('x', 81), null],
            'expiry too big' => [['read:boards'], 'ci', 400],
            'expiry zero' => [['read:boards'], 'ci', 0],
        ];
    }

    /**
     * @param array<int,mixed> $scopes
     */
    #[DataProvider('invalidMintCases')]
    public function test_mint_validation_rejects(array $scopes, string $name, ?int $days): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->mint($this->admin(), 'password123', $name, $scopes, $days);
    }

    public function test_duplicate_mint_key_throws_and_keeps_one_token(): void
    {
        // PR #44 spec §7: a duplicate key must never mint a second credential.
        // Unlike the composer there is no replay — the plaintext is not
        // stored — so the duplicate is refused outright.
        $svc = $this->service();
        $admin = $this->admin();
        $key = bin2hex(random_bytes(16));

        $first = $svc->mint($admin, 'password123', 'ci', ['read:boards'], null, $key);
        self::assertStringStartsWith('rbt_', $first['token']);

        try {
            $svc->mint($admin, 'password123', 'ci-again', ['read:boards'], null, $key);
            self::fail('a duplicate mint key must not mint a second credential');
        } catch (DuplicateSubmissionException) {
        }
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_minted'"));
    }

    public function test_mint_writes_audit_without_secret(): void
    {
        $res = $this->service()->mint($this->admin(), 'password123', 'ci', ['read:boards'], null);
        $row = $this->db->fetch(
            "SELECT after_json FROM moderation_log WHERE action = 'api_token_minted' AND target_id = ?",
            [$res['id']],
        );
        self::assertNotNull($row);
        self::assertStringNotContainsString($res['token'], (string) $row['after_json']);
        self::assertStringNotContainsString('password123', (string) $row['after_json']);
    }
}
