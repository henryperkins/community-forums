<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Response;
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
        $this->assertRedirect($mfa, '/');
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

        $disabled = $this->post('/settings/security/totp/disable', [
            'current_password' => 'password123',
            'disable_code' => $newCodes[0],
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
        $this->assertRedirect($plain, '/');
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
