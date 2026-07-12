<?php

declare(strict_types=1);

namespace Tests\Integration\Controller;

use Tests\Support\TestCase;

final class AuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin makes the install "initialised" so auth routes are reachable.
        $this->makeAdmin();
    }

    public function test_visitor_can_register_and_is_signed_in(): void
    {
        $this->get('/register');
        $response = $this->post('/register', [
            'username' => 'newcomer',
            'email' => 'newcomer@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertRedirect($response, '/inbox');

        $row = $this->users()->findByUsername('newcomer');
        self::assertNotNull($row);
        self::assertNotNull($row['password_hash']);
        self::assertNull($row['email_verified_at']); // verification flow is Phase 2

        // The new account is authenticated.
        $home = $this->get('/');
        $this->assertSeeText($home, 'Log out');
    }

    public function test_registration_validation_errors_preserve_input(): void
    {
        $this->get('/register');
        $response = $this->post('/register', [
            'username' => 'ok_name',
            'email' => 'mismatch@example.test',
            'password' => 'password123',
            'password_confirm' => 'different',
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'passwords do not match');
        $this->assertSeeText($response, 'value="ok_name"'); // old input kept
    }

    public function test_valid_credentials_log_in(): void
    {
        $this->makeUser(['email' => 'member@example.test', 'password' => 'password123']);
        $this->get('/login');
        $response = $this->post('/login', ['email' => 'member@example.test', 'password' => 'password123']);
        $this->assertRedirect($response, '/inbox');
        $this->assertSeeText($this->get('/'), 'Log out');
    }

    public function test_login_preserves_an_explicit_safe_next_destination(): void
    {
        $this->makeUser(['email' => 'safe-next@example.test', 'password' => 'password123']);
        $this->get('/login', ['next' => '/']);

        $response = $this->post('/login', [
            'email' => 'safe-next@example.test',
            'password' => 'password123',
            'next' => '/',
        ]);

        $this->assertRedirect($response, '/');
    }

    public function test_login_rejects_an_unsafe_next_destination_to_the_inbox(): void
    {
        $this->makeUser(['email' => 'unsafe-next@example.test', 'password' => 'password123']);
        $this->get('/login', ['next' => '//evil.example']);

        $response = $this->post('/login', [
            'email' => 'unsafe-next@example.test',
            'password' => 'password123',
            'next' => '//evil.example',
        ]);

        $this->assertRedirect($response, '/inbox');
    }

    public function test_authenticated_login_and_register_pages_redirect_to_the_inbox(): void
    {
        $this->actingAs($this->makeUser());

        $this->assertRedirect($this->get('/login'), '/inbox');
        $this->assertRedirect($this->get('/register'), '/inbox');
    }

    public function test_invalid_credentials_fail_without_enumeration(): void
    {
        $this->makeUser(['email' => 'real@example.test', 'password' => 'password123']);

        $this->get('/login');
        $wrongPassword = $this->post('/login', ['email' => 'real@example.test', 'password' => 'nope']);
        $this->assertStatus(422, $wrongPassword);

        $this->get('/login');
        $unknownEmail = $this->post('/login', ['email' => 'ghost@example.test', 'password' => 'whatever']);
        $this->assertStatus(422, $unknownEmail);

        // Identical generic message → no account enumeration.
        self::assertStringContainsString('email or password you entered is incorrect', $wrongPassword->body());
        self::assertStringContainsString('email or password you entered is incorrect', $unknownEmail->body());
        $this->assertDontSeeText($wrongPassword, 'Log out');
    }

    public function test_csrf_failure_rejects_login(): void
    {
        $this->makeUser(['email' => 'csrf@example.test', 'password' => 'password123']);
        $this->get('/login');
        $response = $this->post('/login', [
            'email' => 'csrf@example.test',
            'password' => 'password123',
            '_token' => 'forged-token',
        ]);
        $this->assertStatus(403, $response);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser(['email' => 'target@example.test', 'password' => 'password123']);
        $this->get('/login');

        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'target@example.test', 'password' => 'wrong']);
        }
        $blocked = $this->post('/login', ['email' => 'target@example.test', 'password' => 'wrong']);
        $this->assertStatus(429, $blocked);
        $this->assertSeeText($blocked, 'Too many attempts');
    }

    public function test_login_limiter_uses_trusted_forwarded_client_ip(): void
    {
        $this->config = new \App\Core\Config(array_replace_recursive($this->config->all(), [
            'trusted_proxies' => ['10.0.0.0/8'],
            'rate_limits' => ['login' => [1, 900]],
        ]));
        $this->app = new \App\Core\App($this->config, $this->db, $this->rateLimiter);
        $this->makeUser(['email' => 'proxy@example.test', 'password' => 'password123']);
        $this->get('/login');

        $serverA = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.10'];
        $serverB = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.11'];
        $this->postWithServer('/login', ['email' => 'proxy@example.test', 'password' => 'wrong'], $serverA);

        $blocked = $this->postWithServer('/login', ['email' => 'proxy@example.test', 'password' => 'wrong'], $serverA);
        $this->assertStatus(429, $blocked);

        $otherClient = $this->postWithServer('/login', ['email' => 'proxy@example.test', 'password' => 'wrong'], $serverB);
        $this->assertStatus(422, $otherClient);
    }

    public function test_registration_is_rate_limited(): void
    {
        // Each attempt fails validation (mismatched passwords) so the client
        // stays a guest and the limiter — which counts every attempt — accrues.
        $this->get('/register');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', [
                'username' => 'user' . $i,
                'email' => 'user' . $i . '@example.test',
                'password' => 'password123',
                'password_confirm' => 'WRONG',
            ]);
        }
        $blocked = $this->post('/register', [
            'username' => 'user6',
            'email' => 'user6@example.test',
            'password' => 'password123',
            'password_confirm' => 'WRONG',
        ]);
        $this->assertStatus(429, $blocked);
        $this->assertSeeText($blocked, 'Too many sign-up attempts');
    }

    public function test_banned_account_cannot_sign_in(): void
    {
        $this->makeUser(['email' => 'banned@example.test', 'password' => 'password123', 'status' => 'banned']);
        $this->get('/login');
        $response = $this->post('/login', ['email' => 'banned@example.test', 'password' => 'password123']);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'not permitted to sign in');
        $this->assertDontSeeText($this->get('/'), 'Log out');
    }

    public function test_suspended_account_can_sign_in_and_read(): void
    {
        $this->makeUser(['email' => 'susp@example.test', 'password' => 'password123', 'status' => 'suspended']);
        $this->get('/login');
        $response = $this->post('/login', ['email' => 'susp@example.test', 'password' => 'password123']);
        $this->assertRedirect($response, '/inbox');
        $this->assertSeeText($this->get('/'), 'Log out');
    }

    public function test_logout_ends_the_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->get('/'); // refresh CSRF from the session
        $this->assertRedirect($this->post('/logout'), '/');
        $this->assertDontSeeText($this->get('/'), 'Log out');
    }
}
