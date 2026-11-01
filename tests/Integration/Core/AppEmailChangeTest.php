<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Core\Request;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\SettingRepository;
use App\Security\Session;
use App\Service\EmailChangeService;
use Tests\Support\TestCase;

final class AppEmailChangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['email_change' => true]);
    }

    /** Issue through the real service and inspect the synthetic mail capture. */
    private function issue(array $user): string
    {
        $request = new Request('GET', '/settings/account/email', [], [], $this->cookies);
        $container = (new \ReflectionMethod($this->app, 'buildContainer'))->invoke($this->app, $request);
        $container->get(Session::class)->start($request);
        $mail = new ArrayMailer();
        $container->instance(Mailer::class, $mail);
        $container->get(EmailChangeService::class)->issue($this->userEntity($user), 'next@example.test', 'password123', null, 'test-binding');
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $mail->sent[0]['text'], $match));
        return $match[1];
    }

    public function test_an_explicit_off_override_keeps_all_email_change_routes_unavailable(): void
    {
        (new SettingRepository($this->db))->set('features', ['email_change' => false]);
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('email_change', $flags->all());
        self::assertFalse($flags->enabled('email_change'));
        $this->actingAs($this->makeUser());
        foreach (['/settings/account/email', '/settings/account/email/confirm'] as $path) {
            $this->assertStatus(404, $this->get($path));
            $this->assertStatus(404, $this->post($path));
        }
        $this->assertStatus(404, $this->post('/settings/account/email/cancel'));
        $this->assertStatus(404, $this->post('/settings/account/email/oauth/google'));
        self::assertStringNotContainsString('href="/settings/account/email"', $this->get('/settings/account')->body());
    }

    public function test_default_account_page_exposes_a_separate_email_form_without_an_override(): void
    {
        $settings = new SettingRepository($this->db);
        self::assertArrayNotHasKey('email_change', $settings->get('features', []));
        self::assertTrue((new FeatureFlags($settings))->enabled('email_change'));
        $this->actingAs($this->makeUser());
        self::assertStringContainsString('href="/settings/account/email"', $this->get('/settings/account')->body());
        $response = $this->get('/settings/account/email');
        $this->assertStatus(200, $response);
        self::assertStringContainsString('name="new_email"', $response->body());
        self::assertStringContainsString('autocomplete="current-password"', $response->body());
        self::assertStringContainsString('action="/settings/account/email"', $response->body());
    }

    public function test_request_requires_a_current_factor_and_preserves_only_the_address(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test', 'password' => 'correct-current-password']);
        $this->actingAs($user);
        $response = $this->post('/settings/account/email', ['new_email' => 'next@example.test', 'current_password' => 'wrong-private-password']);
        $this->assertStatus(422, $response);
        self::assertStringContainsString('value="next@example.test"', $response->body());
        self::assertStringNotContainsString('wrong-private-password', $response->body());
        self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
    }

    public function test_request_keeps_the_old_contact_and_shows_pending_address(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test', 'password' => 'correct-current-password']);
        $this->actingAs($user);
        $response = $this->post('/settings/account/email', ['new_email' => 'next@example.test', 'current_password' => 'correct-current-password']);
        $this->assertRedirect($response, '/settings/account/email');
        self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
        self::assertStringContainsString('next@example.test', $this->get('/settings/account/email')->body());
    }

    public function test_confirmation_get_is_private_and_read_only_and_post_refreshes_the_session(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test']);
        $this->actingAs($user);
        $token = $this->issue($user);
        foreach ([1, 2] as $_) {
            $response = $this->get('/settings/account/email/confirm', ['token' => $token]);
            $this->assertStatus(200, $response);
            self::assertSame('private, no-store', $response->getHeader('Cache-Control'));
            self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
            self::assertStringContainsString('name="token" value="' . $token . '"', $response->body());
            self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
        }
        $this->assertRedirect($this->post('/settings/account/email/confirm', ['token' => $token]), '/settings/account');
        self::assertSame('next@example.test', $this->users()->find((int) $user['id'])['email']);
        self::assertStringContainsString('value="next@example.test"', $this->get('/settings/account')->body());
        $this->assertStatus(422, $this->post('/settings/account/email/confirm', ['token' => $token]));
    }

    public function test_another_owner_cannot_preview_or_confirm_a_link(): void
    {
        $this->enable();
        $owner = $this->makeUser(['email' => 'original@example.test']);
        $this->actingAs($owner);
        $token = $this->issue($owner);
        $this->actingAs($this->makeUser());
        $response = $this->get('/settings/account/email/confirm', ['token' => $token]);
        self::assertStringNotContainsString('next@example.test', $response->body());
        self::assertStringNotContainsString($token, $response->body());
        $this->assertStatus(422, $this->post('/settings/account/email/confirm', ['token' => $token]));
        self::assertSame('original@example.test', $this->users()->find((int) $owner['id'])['email']);
    }

    public function test_a_signed_out_owner_returns_to_the_same_confirmation_link_after_login(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test']);
        $this->actingAs($user);
        $token = $this->issue($user);
        $this->logoutClient();
        $next = '/settings/account/email/confirm?token=' . $token;
        $response = $this->get('/settings/account/email/confirm', ['token' => $token]);
        $this->assertRedirect($response, '/login?next=' . rawurlencode($next));
        self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
        $this->get('/login', ['next' => $next]);
        $this->assertRedirect($this->post('/login', ['email' => 'original@example.test', 'password' => 'password123', 'next' => $next]), $next);
        self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
    }

    public function test_csrf_protects_every_write_including_provider_start(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser());
        foreach (['/settings/account/email', '/settings/account/email/confirm', '/settings/account/email/cancel', '/settings/account/email/oauth/google'] as $path) {
            $this->assertStatus(403, $this->post($path, [], false));
        }
    }

    public function test_cancel_retires_the_link_and_suspended_owners_cannot_confirm(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test']);
        $this->actingAs($user);
        $token = $this->issue($user);
        $this->assertRedirect($this->post('/settings/account/email/cancel'), '/settings/account/email');
        $this->assertStatus(422, $this->post('/settings/account/email/confirm', ['token' => $token]));
        $token = $this->issue($user);
        $this->db->run("UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE id = ?", [$user['id']]);
        $this->assertStatus(403, $this->post('/settings/account/email/confirm', ['token' => $token]));
        self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
    }

    public function test_invalid_and_oversized_input_re_render_safely(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser());
        foreach ([['new_email' => ['not-a-string'], 'current_password' => []], ['new_email' => str_repeat('a', 256) . '@example.test', 'current_password' => 'password123']] as $input) {
            $this->assertStatus(422, $this->post('/settings/account/email', $input));
        }
    }

    public function test_abusive_requests_receive_a_usable_rate_limit_page_without_changing_contact(): void
    {
        $this->enable();
        $user = $this->makeUser(['email' => 'original@example.test']);
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->assertStatus(422, $this->post('/settings/account/email', ['new_email' => 'candidate' . $i . '@example.test', 'current_password' => 'incorrect']));
        }
        $response = $this->post('/settings/account/email', ['new_email' => 'last@example.test', 'current_password' => 'password123']);
        $this->assertStatus(429, $response);
        self::assertStringContainsString('value="last@example.test"', $response->body());
        self::assertSame('original@example.test', $this->users()->find((int) $user['id'])['email']);
    }

    public function test_destination_throttle_is_shared_by_accounts_and_ips_without_storing_raw_email(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser());
        for ($i = 0; $i < 3; $i++) {
            $this->assertStatus(422, $this->post('/settings/account/email', ['new_email' => 'shared@example.test', 'current_password' => 'incorrect']));
        }
        $this->actingAs($this->makeUser());
        $response = $this->postWithServer('/settings/account/email', ['new_email' => 'SHARED@example.test', 'current_password' => 'password123'], ['REMOTE_ADDR' => '192.0.2.34']);
        $this->assertStatus(429, $response);
    }
}
