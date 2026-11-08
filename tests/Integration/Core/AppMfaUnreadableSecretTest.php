<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Controller\UnsubscribeController;
use App\Core\App;
use App\Core\Config;
use App\Core\Response;
use App\Repository\MfaRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\SecretBox;
use App\Security\SecretUnavailableException;
use App\Security\Totp;
use App\Security\WriteGate;
use App\Service\MfaService;
use RuntimeException;
use Tests\Support\TestCase;

/**
 * Two-factor sign-in when the stored TOTP secret cannot be opened, because it
 * was encrypted under an application key this deployment no longer has. The
 * unreadable secret must never satisfy MFA, must not turn into a server error,
 * and must not stop an otherwise valid recovery code from being evaluated.
 *
 * Every key here is a dummy value. K1 is the active key unless a test switches.
 * Grown from docs/evidence/app-key-rotation-review-2026-11-08/diagnostics.
 */
final class AppMfaUnreadableSecretTest extends TestCase
{
    private const GUIDANCE = 'Authenticator codes cannot be checked for this account right now.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useAppKey(self::k1());
    }

    public function test_valid_recovery_code_signs_in_when_the_totp_secret_cannot_be_opened(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());

        $token = $this->beginChallenge($user);
        $signedIn = $this->post('/login/mfa', ['mfa_token' => $token, 'code' => $codes[0]]);

        $this->assertRedirect($signedIn, '/inbox');
        self::assertSame((int) $user['id'], $this->sessionUserId(), 'The session must belong to the challenge account.');
        self::assertSame(9, $this->unusedRecoveryCodes($user));
        self::assertNotNull($this->challengeConsumedAt($token));
        self::assertSame(['method' => 'recovery', 'totp_unavailable' => true], $this->lastAudit($user, 'mfa_recovery_login'));
    }

    public function test_authenticator_code_is_denied_with_guidance_and_the_challenge_stays_usable(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());

        $token = $this->beginChallenge($user);
        // The real, current authenticator code: it must not satisfy MFA because
        // the server cannot open the secret to check it.
        $denied = $this->post('/login/mfa', ['mfa_token' => $token, 'code' => (new Totp())->code($secret, time() + 30)]);

        $this->assertStatus(422, $denied);
        $this->assertSeeText($denied, self::GUIDANCE);
        self::assertNull($this->sessionUserId());
        self::assertSame(10, $this->unusedRecoveryCodes($user));
        self::assertNull($this->challengeConsumedAt($token));
        self::assertSame(['method' => 'unknown', 'totp_unavailable' => true], $this->lastAudit($user, 'mfa_login_failed'));

        $this->assertRedirect($this->post('/login/mfa', ['mfa_token' => $token, 'code' => $codes[1]]), '/inbox');
        self::assertSame((int) $user['id'], $this->sessionUserId());
    }

    public function test_invalid_used_and_foreign_recovery_codes_follow_the_normal_denial_path(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        $other = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        [, $otherCodes] = $this->enroll($other);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());

        $token = $this->beginChallenge($user);
        foreach (['ZZZZZ-ZZZZZ', '00000-00000', $otherCodes[0]] as $wrong) {
            $denied = $this->post('/login/mfa', ['mfa_token' => $token, 'code' => $wrong]);
            $this->assertStatus(422, $denied);
            $this->assertSeeText($denied, self::GUIDANCE);
        }
        self::assertSame(10, $this->unusedRecoveryCodes($other), "Another account's code must not be consumed.");
        self::assertSame(10, $this->unusedRecoveryCodes($user));
        self::assertNull($this->sessionUserId());

        $this->assertRedirect($this->post('/login/mfa', ['mfa_token' => $token, 'code' => $codes[2]]), '/inbox');

        $again = $this->beginChallenge($user);
        $reused = $this->post('/login/mfa', ['mfa_token' => $again, 'code' => $codes[2]]);
        $this->assertStatus(422, $reused);
        self::assertNull($this->sessionUserId());
        self::assertNull($this->challengeConsumedAt($again));
        self::assertSame(9, $this->unusedRecoveryCodes($user));
    }

    public function test_expired_challenge_rejects_a_valid_recovery_code_without_consuming_it(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());

        $token = $this->beginChallenge($user);
        $this->db->run(
            'UPDATE mfa_login_challenges SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE token_hash = ?',
            [hash('sha256', $token)],
        );
        $expired = $this->post('/login/mfa', ['mfa_token' => $token, 'code' => $codes[0]]);

        $this->assertStatus(422, $expired);
        $this->assertSeeText($expired, 'That two-factor challenge expired. Please sign in again.');
        self::assertNull($this->sessionUserId());
        self::assertSame(10, $this->unusedRecoveryCodes($user));
    }

    public function test_attempt_limit_still_applies_when_the_totp_secret_cannot_be_opened(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());

        $blockedAfter = null;
        for ($i = 1; $i <= 12; $i++) {
            $attempt = $this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => '000000']);
            if ($attempt->status() === 429) {
                $blockedAfter = $i;
                break;
            }
            $this->assertStatus(422, $attempt);
        }
        self::assertNotNull($blockedAfter, 'The per-account MFA limit must still bite.');

        $token = $this->beginChallenge($user);
        $this->assertStatus(429, $this->post('/login/mfa', ['mfa_token' => $token, 'code' => $codes[0]]));
        self::assertNull($this->sessionUserId());
        self::assertSame(10, $this->unusedRecoveryCodes($user), 'A throttled attempt must not consume a recovery code.');
    }

    public function test_recovery_code_disables_two_factor_when_the_totp_secret_cannot_be_opened(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        $this->wrapTotpSecretUnder($user, $secret, self::k2());
        $this->actingAs($user);

        $denied = $this->post('/settings/security/totp/disable', [
            'current_password' => 'password123',
            'disable_code' => (new Totp())->code($secret, time() + 30),
        ]);
        $this->assertStatus(422, $denied);
        $this->assertSeeText($denied, self::GUIDANCE);
        self::assertTrue((new MfaRepository($this->db))->enabledForUser((int) $user['id']));

        $this->assertRedirect($this->post('/settings/security/totp/disable', [
            'current_password' => 'password123',
            'disable_code' => $codes[0],
        ]), '/settings/security');
        self::assertFalse((new MfaRepository($this->db))->enabledForUser((int) $user['id']));
        self::assertSame(['enabled' => false, 'totp_unavailable' => true], $this->lastAudit($user, 'mfa_totp_disabled'));

        // The member can then enroll again under the key this deployment has.
        [, $fresh] = $this->enroll($user);
        self::assertCount(10, $fresh);
        self::assertTrue((new MfaRepository($this->db))->enabledForUser((int) $user['id']));
    }

    public function test_unreadable_pending_enrollment_asks_for_a_restart_and_restart_succeeds(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $start);
        $first = $this->authenticatorSecret($start);
        $this->wrapTotpSecretUnder($user, $first, self::k2());

        $stale = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($first),
        ]);
        $this->assertStatus(422, $stale);
        $this->assertSeeText($stale, 'This setup can no longer be verified. Restart setup to get a new authenticator secret.');
        self::assertFalse((new MfaRepository($this->db))->enabledForUser((int) $user['id']));

        $page = $this->get('/settings/security');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Restart setup');

        $restart = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $restart);
        $second = $this->authenticatorSecret($restart);
        self::assertNotSame($first, $second);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($second),
        ]);
        $this->assertStatus(200, $confirm);
        self::assertCount(10, $this->recoveryCodesIn($confirm));
        self::assertTrue((new MfaRepository($this->db))->enabledForUser((int) $user['id']));
    }

    /**
     * The original diagnostic: APP_KEY switched from K1 to K2 with no keyring.
     * Nothing may answer 500; a K1 recovery code no longer verifies (its HMAC was
     * keyed by K1), and the K1 unsubscribe link still needs K1. Restoring those
     * belongs to the keyring work, not to this fix.
     */
    public function test_switching_app_key_without_a_keyring_fails_closed_without_server_errors(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enroll($user);
        parse_str((string) parse_url(
            UnsubscribeController::link('https://forum.example.test', (string) $user['email'], self::k1()),
            PHP_URL_QUERY,
        ), $unsubscribe);

        $this->useAppKey(self::k2());
        $totp = $this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => (new Totp())->code($secret, time() + 30)]);
        $recovery = $this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => $codes[0]]);
        $this->logoutClient();
        $unsubscribeUnderK2 = $this->get('/unsubscribe', $unsubscribe);

        $this->assertStatus(422, $totp);
        $this->assertSeeText($totp, self::GUIDANCE);
        $this->assertStatus(422, $recovery);
        self::assertSame(10, $this->unusedRecoveryCodes($user));
        $this->assertStatus(404, $unsubscribeUnderK2);

        $this->useAppKey(self::k1());
        $this->assertStatus(200, $this->get('/unsubscribe', $unsubscribe));
    }

    public function test_recovery_code_issued_under_the_active_key_works_while_the_totp_secret_needs_the_previous_key(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret] = $this->enroll($user);

        // The active key is K2 and the member regenerated codes under it, but the
        // TOTP secret is still wrapped under K1.
        $this->useAppKey(self::k2());
        $this->actingAs($user);
        $rotated = $this->post('/settings/security/totp/recovery/rotate', ['current_password' => 'password123']);
        $this->assertStatus(200, $rotated);
        $codes = $this->recoveryCodesIn($rotated);
        self::assertCount(10, $codes);

        $this->assertRedirect($this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => $codes[0]]), '/inbox');
        self::assertSame((int) $user['id'], $this->sessionUserId());

        // Control: re-wrapping the SAME secret under K2 restores the authenticator.
        $this->wrapTotpSecretUnder($user, $secret, self::k2());
        $this->assertRedirect($this->post('/login/mfa', [
            'mfa_token' => $this->beginChallenge($user),
            'code' => (new Totp())->code($secret, time() + 30),
        ]), '/inbox');
    }

    public function test_missing_app_key_is_a_configuration_failure_not_a_denial(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [, $codes] = $this->enroll($user);
        $token = $this->beginChallenge($user);

        $items = $this->config->all();
        $items['app']['key'] = '';
        $service = new MfaService(
            new MfaRepository($this->db),
            new UserRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new SecretBox(''),
            new Totp(),
            new WriteGate(),
            new ModerationLogRepository($this->db),
            new Config($items),
        );

        try {
            $service->completeLoginChallenge($token, $codes[0]);
            self::fail('A missing APP_KEY must not be treated as an unreadable record.');
        } catch (RuntimeException $e) {
            self::assertNotInstanceOf(SecretUnavailableException::class, $e);
            self::assertSame('APP_KEY is required for secret encryption.', $e->getMessage());
        }
        self::assertSame(10, $this->unusedRecoveryCodes($user));
        self::assertNull($this->challengeConsumedAt($token));
    }

    private static function k1(): string
    {
        return str_repeat('1a', 32);
    }

    private static function k2(): string
    {
        return str_repeat('2b', 32);
    }

    private function useAppKey(string $key): void
    {
        $items = $this->config->all();
        $items['app']['key'] = $key;
        $this->config = new Config($items);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    /** @param array<string,mixed> $user @return array{0:string,1:list<string>} */
    private function enroll(array $user): array
    {
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $start);
        $secret = $this->authenticatorSecret($start);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        $codes = $this->recoveryCodesIn($confirm);
        self::assertCount(10, $codes);
        $this->logoutClient();
        return [$secret, $codes];
    }

    /** Re-encrypt the member's stored TOTP secret under another key, leaving the row otherwise unchanged. */
    private function wrapTotpSecretUnder(array $user, string $secret, string $key): void
    {
        $wrapped = (new SecretBox($key))->encrypt($secret);
        $this->db->run(
            'UPDATE user_totp_credentials SET secret_ciphertext = ?, secret_nonce = ?, secret_tag = ? WHERE user_id = ?',
            [$wrapped['ciphertext'], $wrapped['nonce'], $wrapped['tag'], (int) $user['id']],
        );
    }

    /** @param array<string,mixed> $user */
    private function beginChallenge(array $user): string
    {
        $this->logoutClient();
        $this->get('/login');
        $password = $this->post('/login', ['email' => $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $password);
        self::assertMatchesRegularExpression('/name="mfa_token" value="([a-f0-9]+)"/', $password->body());
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $password->body(), $m);
        return $m[1];
    }

    private function sessionUserId(): ?int
    {
        $raw = $this->cookies['rb_session'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $id = $this->db->fetchValue('SELECT user_id FROM sessions WHERE id = ? AND revoked_at IS NULL', [hash('sha256', $raw)]);
        return $id === false || $id === null ? null : (int) $id;
    }

    private function challengeConsumedAt(string $token): ?string
    {
        $value = $this->db->fetchValue('SELECT consumed_at FROM mfa_login_challenges WHERE token_hash = ?', [hash('sha256', $token)]);
        return $value === false || $value === null ? null : (string) $value;
    }

    /** @param array<string,mixed> $user */
    private function unusedRecoveryCodes(array $user): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [(int) $user['id']],
        );
    }

    /** @param array<string,mixed> $user @return array<string,mixed>|null */
    private function lastAudit(array $user, string $action): ?array
    {
        $after = $this->db->fetchValue(
            'SELECT after_json FROM moderation_log WHERE action = ? AND target_id = ? ORDER BY id DESC LIMIT 1',
            [$action, (int) $user['id']],
        );
        return is_string($after) ? json_decode($after, true) : null;
    }

    private function authenticatorSecret(Response $response): string
    {
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());
        $input = (new \DOMXPath($document))->query('//label[span[normalize-space(.) = "Authenticator secret"]]/input[@readonly]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $input);
        return $input->getAttribute('value');
    }

    /** @return list<string> */
    private function recoveryCodesIn(Response $response): array
    {
        preg_match_all('/<code>([A-F0-9]{5}-[A-F0-9]{5})<\/code>/', $response->body(), $m);
        return $m[1];
    }
}
