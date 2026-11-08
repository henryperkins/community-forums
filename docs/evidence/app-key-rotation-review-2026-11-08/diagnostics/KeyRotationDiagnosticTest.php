<?php

declare(strict_types=1);

namespace Tests\Diagnostics;

use App\Controller\UnsubscribeController;
use App\Core\App;
use App\Core\Config;
use App\Core\Response;
use App\Repository\MfaRepository;
use App\Security\SecretBox;
use App\Security\Totp;
use Tests\Support\TestCase;

/**
 * REVIEW DIAGNOSTIC, not part of the suite. Characterizes what the CURRENT code
 * does when APP_KEY changes from K1 to K2 with no keyring, through the real
 * kernel, using dummy keys only. Assertions pin today's behavior so the run is
 * reproducible; they are not the desired behavior. Writes RB_DIAG_OUT JSON.
 */
final class KeyRotationDiagnosticTest extends TestCase
{
    /** @var array<string,mixed> */
    private static array $results = [];

    public static function tearDownAfterClass(): void
    {
        $out = getenv('RB_DIAG_OUT');
        if (is_string($out) && $out !== '') {
            file_put_contents($out, json_encode(self::$results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        }
    }

    public function test_switching_app_key_without_a_keyring(): void
    {
        $this->useAppKey(self::k1());
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret, $codes] = $this->enrollUnderActiveKey($user);
        // TestCase::get() takes query parameters as an array, not in the path.
        parse_str((string) parse_url(
            UnsubscribeController::link('https://forum.example.test', (string) $user['email'], self::k1()),
            PHP_URL_QUERY,
        ), $query);

        $this->useAppKey(self::k2());
        $totp = $this->mfaAttempt($user, (new Totp())->code($secret, time() + 30));
        $recovery = $this->mfaAttempt($user, $codes[0]);
        $this->logoutClient();
        $unsubscribeUnderK2 = $this->get('/unsubscribe', $query);
        $unusedAfter = $this->unusedRecoveryCodes($user);

        $this->useAppKey(self::k1());
        $unsubscribeUnderK1 = $this->get('/unsubscribe', $query);

        self::$results['switch_without_keyring'] = [
            'totp_code_under_k2_status' => $totp->status(),
            'k1_recovery_code_under_k2_status' => $recovery->status(),
            'unused_recovery_codes_after_attempts' => $unusedAfter,
            'k1_unsubscribe_link_under_k2_status' => $unsubscribeUnderK2->status(),
            'k1_unsubscribe_link_under_k2_message_present' => str_contains($unsubscribeUnderK2->body(), 'invalid or has expired'),
            'k1_unsubscribe_link_under_k1_status' => $unsubscribeUnderK1->status(),
        ];

        self::assertSame(500, $totp->status());
        self::assertSame(500, $recovery->status());
        self::assertSame(10, $unusedAfter);
        self::assertSame(404, $unsubscribeUnderK2->status());
        self::assertSame(200, $unsubscribeUnderK1->status());
    }

    public function test_undecryptable_totp_secret_blocks_a_recovery_code_valid_under_the_active_key(): void
    {
        $this->useAppKey(self::k1());
        $this->makeAdmin();
        $user = $this->makeUser(['password' => 'password123']);
        [$secret] = $this->enrollUnderActiveKey($user);

        // Active key is now K2 and the member holds recovery codes issued under K2,
        // but the TOTP secret is still wrapped under K1.
        $this->useAppKey(self::k2());
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = sprintf('%05X-%05X', $i, 0xABCDE + $i);
        }
        (new MfaRepository($this->db))->replaceRecoveryCodes(
            (int) $user['id'],
            array_map(static fn (string $c): string => hash_hmac('sha256', str_replace('-', '', $c), self::k2()), $codes),
        );
        $blocked = $this->mfaAttempt($user, $codes[0]);
        $unusedWhileBlocked = $this->unusedRecoveryCodes($user);

        // Control: re-wrap the SAME TOTP secret under K2 (the migration primitive).
        $row = $this->db->fetch('SELECT * FROM user_totp_credentials WHERE user_id = ?', [(int) $user['id']]);
        self::assertIsArray($row);
        $plain = (new SecretBox(self::k1()))->decrypt((string) $row['secret_ciphertext'], (string) $row['secret_nonce'], (string) $row['secret_tag']);
        $rewrapped = (new SecretBox(self::k2()))->encrypt($plain);
        $this->db->run(
            'UPDATE user_totp_credentials SET secret_ciphertext = ?, secret_nonce = ?, secret_tag = ? WHERE id = ?',
            [$rewrapped['ciphertext'], $rewrapped['nonce'], $rewrapped['tag'], (int) $row['id']],
        );
        $recoveryAfterRewrap = $this->mfaAttempt($user, $codes[1]);
        $totpAfterRewrap = $this->mfaAttempt($user, (new Totp())->code($secret, time() + 30));

        self::$results['undecryptable_totp_blocks_recovery'] = [
            'k2_recovery_code_with_k1_wrapped_totp_status' => $blocked->status(),
            'unused_recovery_codes_while_blocked' => $unusedWhileBlocked,
            'totp_secret_unchanged_by_rewrap' => $plain === $secret,
            'k2_recovery_code_after_rewrap_status' => $recoveryAfterRewrap->status(),
            'k2_recovery_code_after_rewrap_location' => $recoveryAfterRewrap->getHeader('location'),
            'totp_code_after_rewrap_status' => $totpAfterRewrap->status(),
            'totp_code_after_rewrap_location' => $totpAfterRewrap->getHeader('location'),
        ];

        self::assertSame(500, $blocked->status());
        self::assertSame(10, $unusedWhileBlocked);
        self::assertSame($secret, $plain);
        $this->assertRedirect($recoveryAfterRewrap, '/inbox');
        $this->assertRedirect($totpAfterRewrap, '/inbox');
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
    private function enrollUnderActiveKey(array $user): array
    {
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $start);
        $document = new \DOMDocument();
        @$document->loadHTML($start->body());
        $input = (new \DOMXPath($document))->query('//label[span[normalize-space(.) = "Authenticator secret"]]/input[@readonly]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $input);
        $secret = $input->getAttribute('value');
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        preg_match_all('/<code>([A-F0-9-]+)<\/code>/', $confirm->body(), $m);
        self::assertCount(10, $m[1]);
        return [$secret, $m[1]];
    }

    /** @param array<string,mixed> $user */
    private function mfaAttempt(array $user, string $code): Response
    {
        $this->logoutClient();
        $this->get('/login');
        $password = $this->post('/login', ['email' => $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $password);
        preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $password->body(), $m);
        self::assertNotEmpty($m[1] ?? '');
        return $this->post('/login/mfa', ['mfa_token' => $m[1], 'code' => $code]);
    }

    /** @param array<string,mixed> $user */
    private function unusedRecoveryCodes(array $user): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL',
            [(int) $user['id']],
        );
    }
}
