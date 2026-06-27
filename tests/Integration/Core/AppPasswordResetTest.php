<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Mail\ArrayMailer;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Service\AuthService;
use App\Service\PasswordResetService;
use Tests\Support\TestCase;

/**
 * Forgotten-password recovery (Gate A "account recovery"): a single-use, hashed,
 * time-boxed token issued by email, with no account enumeration, that rotates
 * the password and revokes every session. Token-issuing is exercised at the
 * service layer (to capture the raw token from the mailer); reset submission is
 * exercised end-to-end over HTTP.
 */
final class AppPasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist or the kernel stays in fresh-install (/setup) mode.
        $this->makeAdmin(['username' => 'siteadmin']);
    }

    private function resetService(ArrayMailer $mailer): PasswordResetService
    {
        return new PasswordResetService(
            new UserRepository($this->db),
            new \App\Repository\VerificationRepository($this->db),
            new SessionRepository($this->db),
            new PasswordHasher(),
            $mailer,
            $this->config,
        );
    }

    private function tokenFromEmail(ArrayMailer $mailer, string $email): string
    {
        $messages = $mailer->to($email);
        self::assertNotEmpty($messages, "expected a reset email to {$email}");
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $messages[0]['text'], $m));
        return $m[1];
    }

    public function test_request_emails_a_link_and_stores_only_the_hash(): void
    {
        $user = $this->makeUser(['username' => 'amy', 'email' => 'amy@example.test']);
        $mailer = new ArrayMailer();

        $this->resetService($mailer)->request('amy@example.test');

        self::assertCount(1, $mailer->to('amy@example.test'));
        $row = $this->db->fetch(
            'SELECT * FROM verifications WHERE user_id = ? AND type = ?',
            [(int) $user['id'], 'password_reset'],
        );
        self::assertNotNull($row);
        self::assertNull($row['used_at']);

        // The DB holds the SHA-256 of the raw token, never the token itself.
        $token = $this->tokenFromEmail($mailer, 'amy@example.test');
        self::assertSame(hash('sha256', $token), $row['token_hash']);
        self::assertStringNotContainsString($token, (string) $row['token_hash']);
    }

    public function test_request_for_unknown_email_is_silent(): void
    {
        $mailer = new ArrayMailer();
        $this->resetService($mailer)->request('nobody@example.test');

        self::assertSame(0, $mailer->count());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM verifications'));
    }

    public function test_only_one_outstanding_token_per_user(): void
    {
        $user = $this->makeUser(['username' => 'rea', 'email' => 'rea@example.test']);
        $svc = $this->resetService(new ArrayMailer());
        $svc->request('rea@example.test');
        $svc->request('rea@example.test'); // second request retires the first

        $live = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM verifications WHERE user_id = ? AND used_at IS NULL',
            [(int) $user['id']],
        );
        self::assertSame(1, $live);
    }

    public function test_full_reset_flow_changes_password_and_consumes_token(): void
    {
        $user = $this->makeUser(['username' => 'bob', 'email' => 'bob@example.test', 'password' => 'oldpassword1']);
        $mailer = new ArrayMailer();
        $this->resetService($mailer)->request('bob@example.test');
        $token = $this->tokenFromEmail($mailer, 'bob@example.test');

        // The link shows the new-password form (and seeds the guest CSRF cookie).
        $show = $this->get('/reset', ['token' => $token]);
        $this->assertStatus(200, $show);
        $this->assertSeeText($show, 'Choose a new password');

        $resp = $this->post('/reset', [
            'token' => $token,
            'password' => 'newpassword9',
            'password_confirm' => 'newpassword9',
        ]);
        $this->assertRedirect($resp, '/login');

        // Token consumed (single-use).
        $row = $this->db->fetch('SELECT * FROM verifications WHERE user_id = ?', [(int) $user['id']]);
        self::assertNotNull($row['used_at']);

        // Old password rejected, new password accepted.
        $auth = new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config);
        self::assertNull($auth->attempt('bob@example.test', 'oldpassword1'));
        self::assertNotNull($auth->attempt('bob@example.test', 'newpassword9'));
    }

    public function test_reset_revokes_all_existing_sessions(): void
    {
        $user = $this->makeUser(['username' => 'sid', 'email' => 'sid@example.test', 'password' => 'oldpassword1']);
        $this->actingAs($user); // creates an active session row for the user
        $sessions = new SessionRepository($this->db);
        self::assertCount(1, $sessions->listActiveForUser((int) $user['id']));

        $mailer = new ArrayMailer();
        $svc = $this->resetService($mailer);
        $svc->request('sid@example.test');
        $verification = $svc->findValid($this->tokenFromEmail($mailer, 'sid@example.test'));
        self::assertNotNull($verification);
        $svc->reset($verification, 'newpassword9', 'newpassword9');

        self::assertCount(0, $sessions->listActiveForUser((int) $user['id']));
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $user = $this->makeUser(['username' => 'rue', 'email' => 'rue@example.test', 'password' => 'oldpassword1']);
        $mailer = new ArrayMailer();
        $this->resetService($mailer)->request('rue@example.test');
        $token = $this->tokenFromEmail($mailer, 'rue@example.test');

        $this->get('/reset', ['token' => $token]); // seed CSRF
        $this->assertRedirect($this->post('/reset', [
            'token' => $token, 'password' => 'newpassword9', 'password_confirm' => 'newpassword9',
        ]), '/login');

        // Reusing the same link now fails closed.
        $reuse = $this->post('/reset', [
            'token' => $token, 'password' => 'evilpassword9', 'password_confirm' => 'evilpassword9',
        ]);
        $this->assertStatus(400, $reuse);
        $this->assertSeeText($reuse, 'invalid or has expired');

        // The second attempt did not change the password again.
        $auth = new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config);
        self::assertNotNull($auth->attempt('rue@example.test', 'newpassword9'));
        self::assertNull($auth->attempt('rue@example.test', 'evilpassword9'));
    }

    public function test_invalid_token_shows_error_and_changes_nothing(): void
    {
        $user = $this->makeUser(['username' => 'ned', 'email' => 'ned@example.test', 'password' => 'oldpassword1']);

        $show = $this->get('/reset', ['token' => str_repeat('a', 64)]);
        $this->assertStatus(200, $show);
        $this->assertSeeText($show, 'invalid or has expired');

        $resp = $this->post('/reset', [
            'token' => str_repeat('a', 64), 'password' => 'newpassword9', 'password_confirm' => 'newpassword9',
        ]);
        $this->assertStatus(400, $resp);

        $auth = new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config);
        self::assertNotNull($auth->attempt('ned@example.test', 'oldpassword1'));
    }

    public function test_weak_password_is_rejected_without_consuming_the_token(): void
    {
        $user = $this->makeUser(['username' => 'wes', 'email' => 'wes@example.test', 'password' => 'oldpassword1']);
        $mailer = new ArrayMailer();
        $this->resetService($mailer)->request('wes@example.test');
        $token = $this->tokenFromEmail($mailer, 'wes@example.test');

        $this->get('/reset', ['token' => $token]); // seed CSRF
        $resp = $this->post('/reset', ['token' => $token, 'password' => 'short', 'password_confirm' => 'short']);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'at least 8 characters');

        // Token still usable (not burned by a validation failure).
        $row = $this->db->fetch('SELECT * FROM verifications WHERE user_id = ?', [(int) $user['id']]);
        self::assertNull($row['used_at']);

        $auth = new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config);
        self::assertNotNull($auth->attempt('wes@example.test', 'oldpassword1'));
    }

    public function test_expired_token_is_rejected_and_changes_nothing(): void
    {
        $user = $this->makeUser(['username' => 'eli', 'email' => 'eli@example.test', 'password' => 'oldpassword1']);
        $mailer = new ArrayMailer();
        $this->resetService($mailer)->request('eli@example.test');
        $token = $this->tokenFromEmail($mailer, 'eli@example.test');

        // Push the still-unused token past its TTL.
        $this->db->run(
            "UPDATE verifications SET expires_at = ? WHERE user_id = ? AND type = 'password_reset'",
            [gmdate('Y-m-d H:i:s', time() - 3600), (int) $user['id']],
        );

        // The form treats the expired link as invalid (and seeds the guest CSRF cookie)…
        $show = $this->get('/reset', ['token' => $token]);
        $this->assertStatus(200, $show);
        $this->assertSeeText($show, 'invalid or has expired');

        // …and submitting it fails closed without rotating the password.
        $resp = $this->post('/reset', [
            'token' => $token, 'password' => 'newpassword9', 'password_confirm' => 'newpassword9',
        ]);
        $this->assertStatus(400, $resp);

        $auth = new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config);
        self::assertNotNull($auth->attempt('eli@example.test', 'oldpassword1'));
        self::assertNull($auth->attempt('eli@example.test', 'newpassword9'));
    }

    public function test_http_forgot_is_generic_and_only_issues_for_real_accounts(): void
    {
        $user = $this->makeUser(['username' => 'cara', 'email' => 'cara@example.test']);

        $this->get('/forgot'); // seed CSRF
        $real = $this->post('/forgot', ['email' => 'cara@example.test']);
        $this->assertStatus(200, $real);
        $this->assertSeeText($real, "we've sent a link");

        $ghost = $this->post('/forgot', ['email' => 'ghost@example.test']);
        $this->assertStatus(200, $ghost);
        $this->assertSeeText($ghost, "we've sent a link"); // identical response

        // A row was created for the real account; none for the unknown address.
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM verifications WHERE user_id = ?',
            [(int) $user['id']],
        ));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM verifications'));
    }
}
