<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Response;
use App\Repository\SessionRepository;
use App\Security\Totp;
use Tests\Support\TestCase;

final class AppMfaTest extends TestCase
{
    public function test_user_enrolls_totp_uses_recovery_code_and_disables_without_javascript(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'mfauser', 'password' => 'password123']);
        $this->actingAs($user);

        $start = $this->post('/settings/security/totp/enroll', [
            'current_password' => 'password123',
        ]);
        $this->assertStatus(200, $start);
        $secret = $this->extractAuthenticatorSecret($start);

        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        $codes = $this->extractRecoveryCodes($confirm);
        self::assertCount(10, $codes);

        $stored = $this->db->fetch('SELECT secret_ciphertext FROM user_totp_credentials WHERE user_id = ?', [(int) $user['id']]);
        self::assertIsArray($stored);
        self::assertNotSame($secret, (string) $stored['secret_ciphertext']);
        self::assertFalse($this->db->fetchValue('SELECT 1 FROM user_recovery_codes WHERE code_hash = ? LIMIT 1', [$codes[0]]));

        $this->logoutClient();
        $this->get('/login');
        $password = $this->post('/login', [
            'email' => $user['email'],
            'password' => 'password123',
        ]);
        $this->assertStatus(200, $password);
        $this->assertSeeText($password, 'Two-factor verification');

        $mfa = $this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($password),
            'code' => strtolower($codes[0]),
        ]);
        $this->assertRedirect($mfa, '/inbox');
        self::assertSame(9, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [(int) $user['id']],
        ));

        $this->logoutClient();
        $this->get('/login');
        $again = $this->post('/login', [
            'email' => $user['email'],
            'password' => 'password123',
        ]);
        $reused = $this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($again),
            'code' => $codes[0],
        ]);
        $this->assertStatus(422, $reused);

        $this->actingAs($user);
        $rotated = $this->post('/settings/security/totp/recovery/rotate', [
            'current_password' => 'password123',
        ]);
        $this->assertStatus(200, $rotated);
        $newCodes = $this->extractRecoveryCodes($rotated);
        self::assertCount(10, $newCodes);

        $this->logoutClient();
        $this->get('/login', ['next' => '/settings/security']);
        $withNext = $this->post('/login', [
            'email' => $user['email'],
            'password' => 'password123',
            'next' => '/settings/security',
        ]);
        $completedWithNext = $this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($withNext),
            'code' => $newCodes[0],
            'next' => '/settings/security',
        ]);
        $this->assertRedirect($completedWithNext, '/settings/security');

        $disabled = $this->post('/settings/security/totp/disable', [
            'current_password' => 'password123',
            'disable_code' => $newCodes[1],
        ]);
        $this->assertRedirect($disabled, '/settings/security');
        self::assertFalse((bool) $this->db->fetchValue(
            'SELECT 1 FROM user_totp_credentials WHERE user_id = ? AND enabled_at IS NOT NULL AND disabled_at IS NULL',
            [(int) $user['id']],
        ));

        $this->logoutClient();
        $this->get('/login');
        $plain = $this->post('/login', [
            'email' => $user['email'],
            'password' => 'password123',
        ]);
        $this->assertRedirect($plain, '/inbox');
    }

    public function test_totp_mfa_code_guessing_is_throttled_per_account_across_fresh_login_tokens(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'brutetarget', 'password' => 'password123']);
        $secret = $this->enrollTotp($user, 'password123');
        $this->logoutClient();

        $wrong = $this->wrongCode($secret);

        // The attacker already knows the password. Each iteration mints a FRESH
        // challenge token — which today grants a fresh per-token guess budget —
        // and submits one wrong code. A per-account throttle must still bite.
        $blocked = false;
        for ($i = 0; $i < 12; $i++) {
            $this->get('/login');
            $password = $this->post('/login', ['email' => $user['email'], 'password' => 'password123']);
            $this->assertStatus(200, $password);
            $attempt = $this->post('/login/mfa', [
                'mfa_token' => $this->extractMfaToken($password),
                'code' => $wrong,
            ]);
            if ($attempt->status() === 429) {
                $blocked = true;
                break;
            }
            $this->assertStatus(422, $attempt);
        }

        self::assertTrue(
            $blocked,
            'Per-account MFA throttle must bound code guessing even when a fresh challenge token is minted each time.',
        );
    }

    public function test_failed_totp_login_is_audited_not_silent(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'audituser', 'password' => 'password123']);
        $secret = $this->enrollTotp($user, 'password123');
        $this->logoutClient();

        $this->get('/login');
        $password = $this->post('/login', ['email' => $user['email'], 'password' => 'password123']);
        $attempt = $this->post('/login/mfa', [
            'mfa_token' => $this->extractMfaToken($password),
            'code' => $this->wrongCode($secret),
        ]);
        $this->assertStatus(422, $attempt);

        self::assertGreaterThan(
            0,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM moderation_log WHERE action = 'mfa_login_failed' AND target_id = ?",
                [(int) $user['id']],
            ),
            'A failed second-factor attempt must leave an audit trail.',
        );
    }

    public function test_totp_settings_endpoints_are_rate_limited(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'reauthtarget', 'password' => 'password123']);
        $this->actingAs($user);

        // Ten wrong-password reauth attempts against a TOTP settings endpoint...
        for ($i = 0; $i < 10; $i++) {
            $this->post('/settings/security/totp/enroll', ['current_password' => 'wrong-password']);
        }
        // ...must exhaust the mfa_settings window (parity with the passkey endpoints).
        $blocked = $this->post('/settings/security/totp/enroll', ['current_password' => 'wrong-password']);
        $this->assertStatus(429, $blocked);
    }

    public function test_password_change_reauth_is_rate_limited(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'pwtarget', 'password' => 'password123']);
        $this->actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->post('/settings/security', [
                'current_password' => 'wrong-password',
                'new_password' => 'brand-new-pass-1',
                'new_password_confirm' => 'brand-new-pass-1',
            ]);
        }
        $blocked = $this->post('/settings/security', [
            'current_password' => 'wrong-password',
            'new_password' => 'brand-new-pass-1',
            'new_password_confirm' => 'brand-new-pass-1',
        ]);
        $this->assertStatus(429, $blocked);
    }

    public function test_enabling_totp_revokes_other_sessions(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'enabletotp', 'password' => 'password123']);
        $this->actingAs($user);
        $other = $this->createExtraSessionFor($user);
        self::assertNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$other]));

        $this->enrollTotp($user, 'password123');

        self::assertNotNull(
            $this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$other]),
            'Enabling a second factor must evict other sessions that never satisfied it.',
        );
        $this->assertStatus(200, $this->get('/settings/security'));
    }

    public function test_disabling_totp_revokes_other_sessions(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'disabletotp', 'password' => 'password123']);
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $secret = $this->extractAuthenticatorSecret($start);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $recovery = $this->extractRecoveryCodes($confirm);

        // Create the other session AFTER enrolment (enabling now revokes too).
        $other = $this->createExtraSessionFor($user);
        self::assertNull($this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$other]));

        // Disable with a recovery code — a same-window TOTP code would be a replay.
        $disabled = $this->post('/settings/security/totp/disable', [
            'current_password' => 'password123',
            'disable_code' => $recovery[0],
        ]);
        $this->assertRedirect($disabled, '/settings/security');

        self::assertNotNull(
            $this->db->fetchValue('SELECT revoked_at FROM sessions WHERE id = ?', [$other]),
            'Disabling a second factor must evict other sessions.',
        );
    }

    private function createExtraSessionFor(array $user): string
    {
        $id = hash('sha256', 'extra-session-' . bin2hex(random_bytes(16)));
        (new SessionRepository($this->db))->create([
            'id' => $id,
            'user_id' => (int) $user['id'],
            'csrf_secret' => bin2hex(random_bytes(32)),
            'user_agent' => 'phpunit-other-session',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);
        return $id;
    }

    /** @param array<string,mixed> $user */
    private function enrollTotp(array $user, string $password): string
    {
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => $password]);
        $this->assertStatus(200, $start);
        $secret = $this->extractAuthenticatorSecret($start);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => $password,
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        return $secret;
    }

    /** A 6-digit code guaranteed to differ from the authenticator's current code. */
    private function wrongCode(string $secret): string
    {
        return (new Totp())->code($secret) === '000000' ? '111111' : '000000';
    }

    private function extractAuthenticatorSecret(Response $response): string
    {
        self::assertMatchesRegularExpression('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body());
        preg_match('/Authenticator secret.*?<input class="input" value="([A-Z2-7]+)"/s', $response->body(), $m);
        return $m[1];
    }

    /** @return list<string> */
    private function extractRecoveryCodes(Response $response): array
    {
        preg_match_all('/<code>([A-F0-9-]+)<\/code>/', $response->body(), $m);
        return $m[1];
    }

    private function extractMfaToken(Response $response): string
    {
        self::assertMatchesRegularExpression('/name="mfa_token" value="([a-f0-9]+)"/', $response->body());
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $response->body(), $m);
        return $m[1];
    }
}
