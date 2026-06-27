<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Mail\ArrayMailer;
use App\Repository\BadgeRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Service\BadgeService;
use App\Service\EmailVerificationService;
use Tests\Support\TestCase;

/**
 * Registration email verification (Gate A): a single-use, hashed, time-boxed
 * link confirms an address, setting users.email_verified_at and granting the
 * welcome badge. Token issuing is exercised at the service layer (to capture the
 * raw token from the mailer); the verify/resend paths run end-to-end over HTTP.
 * Verification is soft — it does not gate writes.
 */
final class AppEmailVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist or the kernel stays in fresh-install (/setup) mode.
        $this->makeAdmin(['username' => 'siteadmin']);
    }

    private function verifyService(ArrayMailer $mailer): EmailVerificationService
    {
        return new EmailVerificationService(
            new UserRepository($this->db),
            new VerificationRepository($this->db),
            new BadgeService($this->db, new BadgeRepository($this->db), new UserRepository($this->db)),
            $mailer,
            $this->config,
        );
    }

    private function tokenFromEmail(ArrayMailer $mailer, string $email): string
    {
        $messages = $mailer->to($email);
        self::assertNotEmpty($messages, "expected a verification email to {$email}");
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $messages[0]['text'], $m));
        return $m[1];
    }

    public function test_issue_emails_link_and_stores_only_the_hash(): void
    {
        $user = $this->makeUser(['username' => 'ann', 'email' => 'ann@example.test']);
        $mailer = new ArrayMailer();
        $this->verifyService($mailer)->issue((int) $user['id'], 'ann@example.test');

        self::assertCount(1, $mailer->to('ann@example.test'));
        $row = $this->db->fetch(
            'SELECT * FROM verifications WHERE user_id = ? AND type = ?',
            [(int) $user['id'], 'email_verify'],
        );
        self::assertNotNull($row);
        self::assertNull($row['used_at']);
        $token = $this->tokenFromEmail($mailer, 'ann@example.test');
        self::assertSame(hash('sha256', $token), $row['token_hash']);
        self::assertNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]));
    }

    public function test_clicking_link_verifies_and_consumes_the_token(): void
    {
        $user = $this->makeUser(['username' => 'ben', 'email' => 'ben@example.test']);
        $mailer = new ArrayMailer();
        $this->verifyService($mailer)->issue((int) $user['id'], 'ben@example.test');
        $token = $this->tokenFromEmail($mailer, 'ben@example.test');

        $resp = $this->get('/verify', ['token' => $token]);
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Email verified');

        self::assertNotNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]));
        self::assertNotNull($this->db->fetchValue('SELECT used_at FROM verifications WHERE user_id = ?', [(int) $user['id']]));
    }

    public function test_verifying_grants_the_welcome_badge(): void
    {
        $user = $this->makeUser(['username' => 'cy', 'email' => 'cy@example.test']);
        $mailer = new ArrayMailer();
        $svc = $this->verifyService($mailer);
        $svc->issue((int) $user['id'], 'cy@example.test');
        $verification = $svc->findValid($this->tokenFromEmail($mailer, 'cy@example.test'));
        self::assertNotNull($verification);
        $svc->verify($verification);

        $has = $this->db->fetchValue(
            'SELECT 1 FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = ? AND b.slug = ?',
            [(int) $user['id'], 'welcome'],
        );
        self::assertNotFalse($has, 'welcome badge should be granted on verification');
    }

    public function test_invalid_token_shows_error_and_changes_nothing(): void
    {
        $user = $this->makeUser(['username' => 'dot', 'email' => 'dot@example.test']);

        $resp = $this->get('/verify', ['token' => str_repeat('a', 64)]);
        $this->assertStatus(400, $resp);
        $this->assertSeeText($resp, 'invalid or has expired');
        self::assertNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]));
    }

    public function test_registration_issues_a_verification_token_for_an_unverified_account(): void
    {
        $this->get('/register'); // seed guest CSRF
        $resp = $this->post('/register', [
            'username' => 'newbie',
            'email' => 'newbie@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertRedirect($resp, '/');

        $uid = (int) $this->db->fetchValue('SELECT id FROM users WHERE email = ?', ['newbie@example.test']);
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = ?',
            [$uid, 'email_verify'],
        ));
        self::assertNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [$uid]));
    }

    public function test_settings_shows_notice_and_resend_issues_a_token(): void
    {
        $user = $this->makeUser(['username' => 'eve', 'email' => 'eve@example.test']);
        $this->actingAs($user);

        $page = $this->get('/settings/account');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Verify your email address');

        $resp = $this->post('/verify/resend');
        $this->assertRedirect($resp, '/settings/account');
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = ?',
            [(int) $user['id'], 'email_verify'],
        ));
    }

    public function test_resend_is_a_noop_for_an_already_verified_user(): void
    {
        $user = $this->makeUser(['username' => 'fay', 'email' => 'fay@example.test']);
        $this->users()->markEmailVerified((int) $user['id']);
        $this->actingAs($user);

        $page = $this->get('/settings/account');
        $this->assertDontSeeText($page, 'Verify your email address');

        $resp = $this->post('/verify/resend');
        $this->assertRedirect($resp, '/settings/account');
        self::assertSame(0, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = ?',
            [(int) $user['id'], 'email_verify'],
        ));
    }

    public function test_used_verification_token_cannot_be_reused(): void
    {
        $user = $this->makeUser(['username' => 'gus', 'email' => 'gus@example.test']);
        $mailer = new ArrayMailer();
        $this->verifyService($mailer)->issue((int) $user['id'], 'gus@example.test');
        $token = $this->tokenFromEmail($mailer, 'gus@example.test');

        // First click verifies and consumes the single-use token.
        $first = $this->get('/verify', ['token' => $token]);
        $this->assertStatus(200, $first);
        $this->assertSeeText($first, 'Email verified');
        $verifiedAt = $this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]);
        self::assertNotNull($verifiedAt);

        // Re-using the same link fails closed, and the verified timestamp is left
        // untouched (no second processing of the token).
        $second = $this->get('/verify', ['token' => $token]);
        $this->assertStatus(400, $second);
        $this->assertSeeText($second, 'invalid or has expired');
        self::assertSame($verifiedAt, $this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]));
    }

    public function test_expired_verification_token_is_rejected(): void
    {
        $user = $this->makeUser(['username' => 'hal', 'email' => 'hal@example.test']);
        $mailer = new ArrayMailer();
        $this->verifyService($mailer)->issue((int) $user['id'], 'hal@example.test');
        $token = $this->tokenFromEmail($mailer, 'hal@example.test');

        // Push the still-unused token past its expiry window.
        $this->db->run(
            "UPDATE verifications SET expires_at = ? WHERE user_id = ? AND type = 'email_verify'",
            [gmdate('Y-m-d H:i:s', time() - 3600), (int) $user['id']],
        );

        $resp = $this->get('/verify', ['token' => $token]);
        $this->assertStatus(400, $resp);
        $this->assertSeeText($resp, 'invalid or has expired');
        self::assertNull($this->db->fetchValue('SELECT email_verified_at FROM users WHERE id = ?', [(int) $user['id']]));
    }

    public function test_resend_is_rate_limited_after_the_cap(): void
    {
        $user = $this->makeUser(['username' => 'ivy', 'email' => 'ivy@example.test']);
        $this->actingAs($user);

        // The cap is 3 resends per hour (AuthController::VERIFY_RESEND_MAX); each
        // accepted resend issues a fresh token (retiring the prior one).
        for ($i = 0; $i < 3; $i++) {
            $this->assertRedirect($this->post('/verify/resend'), '/settings/account');
        }
        self::assertSame(3, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = 'email_verify'",
            [(int) $user['id']],
        ));

        // The fourth request is throttled: it still redirects gracefully but issues
        // no further token — only the latest of the three remains live.
        $this->assertRedirect($this->post('/verify/resend'), '/settings/account');
        self::assertSame(3, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = 'email_verify'",
            [(int) $user['id']],
        ));
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM verifications WHERE user_id = ? AND type = 'email_verify' AND used_at IS NULL",
            [(int) $user['id']],
        ));
    }
}
