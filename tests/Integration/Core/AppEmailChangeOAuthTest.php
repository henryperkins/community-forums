<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Controller\OAuthController;
use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SettingRepository;
use App\Security\Session;
use App\Service\OAuth\NormalizedIdentity;
use App\Service\OAuth\OAuthProvider;
use App\Service\OAuth\ProviderRegistry;
use Tests\Support\TestCase;

final class AppEmailChangeOAuthTest extends TestCase
{
    private array $user;
    private ArrayMailer $mail;
    private \stdClass $providerState;
    private int $codeSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        (new SettingRepository($this->db))->set('features', ['email_change' => true, 'oauth' => true]);
        $this->user = $this->makeUser(['email' => 'oauth-owner@example.test']);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$this->user['id']]);
        $this->user = $this->users()->find((int) $this->user['id']);
        (new OAuthIdentityRepository($this->db))->create(['user_id' => $this->user['id'], 'provider' => 'fixture', 'provider_user_id' => 'owned-subject']);
        $this->actingAs($this->user);
        $this->mail = new ArrayMailer();
        $this->providerState = (object) ['used_codes' => []];
    }

    private function provider(string $subject): OAuthProvider
    {
        return new class($subject, $this->providerState) implements OAuthProvider {
            public function __construct(private string $subject, private \stdClass $state) {}
            public function name(): string { return 'fixture'; }
            public function label(): string { return 'Fixture provider'; }
            public function isConfigured(): bool { return true; }
            public function authorizeUrl(string $redirectUri, string $state, string $codeChallenge, string $nonce): string { return 'https://provider.example.test/authorize?state=' . $state; }
            public function exchange(string $code, string $codeVerifier, string $redirectUri): array {
                if (preg_match('/\Averified-provider-code(?:-[0-9]+)?\z/D', $code) !== 1 || $codeVerifier === ''
                    || isset($this->state->used_codes[$code])) { throw new \RuntimeException('Unbound or reused provider code.'); }
                $this->state->used_codes[$code] = true;
                return ['subject' => $this->subject];
            }
            public function identity(array $tokens, ?string $expectedNonce = null): NormalizedIdentity { return new NormalizedIdentity('fixture', $tokens['subject'], 'different-provider-email@example.test', true, 'Fixture member', null); }
        };
    }

    private function controller(Request $request, string $subject = 'owned-subject'): OAuthController
    {
        $container = (new \ReflectionMethod($this->app, 'buildContainer'))->invoke($this->app, $request);
        self::assertInstanceOf(Container::class, $container);
        $container->get(Session::class)->start($request);
        $provider = $this->provider($subject);
        $container->instance(ProviderRegistry::class, new ProviderRegistry([], null, static fn (): array => ['fixture' => $provider]));
        $container->instance(Mailer::class, $this->mail);
        $container->get(View::class)->beforeRender(function () use ($container, $request): void {
            (new \ReflectionMethod($this->app, 'shareViewGlobals'))->invoke($this->app, $container, $request);
        });
        return new OAuthController($container);
    }

    private function start(): array
    {
        $request = new Request('POST', '/settings/account/email/oauth/fixture', [], ['new_email' => 'new-oauth@example.test'], $this->cookies);
        $response = $this->controller($request)->startEmailChange($request, ['provider' => 'fixture']);
        $this->assertStatus(200, $response);
        self::assertStringContainsString('href="https://provider.example.test/authorize?', $response->body());
        $cookie = $this->stateCookie($response);
        [$encoded] = explode('.', $cookie, 2);
        return [$cookie, json_decode(base64_decode(strtr($encoded, '-_', '+/')), true)];
    }

    private function providerReturn(string $cookie, array $payload, string $subject = 'owned-subject'): Response
    {
        $before = $this->mail->count();
        $request = new Request('GET', '/auth/fixture/callback', ['state' => $payload['state'], 'code' => 'verified-provider-code-' . ++$this->codeSequence], [], $this->cookies + ['rb_oauth' => $cookie]);
        $response = $this->controller($request, $subject)->callback($request, ['provider' => 'fixture']);
        if ($response->status() !== 200) { return $response; }
        self::assertSame($before, $this->mail->count(), 'A provider GET return cannot issue a verification link.');
        $cookie = $this->stateCookie($response);
        $post = new Request('POST', '/auth/fixture/callback', [], ['state' => $payload['state'], 'code' => 'untrusted-post-code'], $this->cookies + ['rb_oauth' => $cookie]);
        return $this->controller($post, $subject)->callback($post, ['provider' => 'fixture']);
    }

    private function stateCookie(Response $response): string
    {
        foreach ($response->cookieHeaders() as $value) {
            if (preg_match('/^rb_oauth=([^;]+)/', $value, $match)) { return rawurldecode($match[1]); }
        }
        self::fail('A signed OAuth state cookie was expected.');
    }

    public function test_passwordless_owner_can_reauthenticate_with_exact_linked_provider_without_changing_contact(): void
    {
        [$cookie, $payload] = $this->start();
        self::assertSame('new-oauth@example.test', $payload['email_change']['email']);
        $this->assertRedirect($this->providerReturn($cookie, $payload), '/settings/account/email');
        self::assertCount(1, $this->mail->to('new-oauth@example.test'));
        self::assertSame('oauth-owner@example.test', $this->users()->find((int) $this->user['id'])['email']);
        self::assertSame(1, (new OAuthIdentityRepository($this->db))->countForUser((int) $this->user['id']));
    }

    public function test_an_unlinked_provider_subject_cannot_reauthenticate_or_be_linked(): void
    {
        [$cookie, $payload] = $this->start();
        $this->assertStatus(422, $this->providerReturn($cookie, $payload, 'attacker-subject'));
        self::assertSame(0, $this->mail->count());
        self::assertSame(1, (new OAuthIdentityRepository($this->db))->countForUser((int) $this->user['id']));
    }

    public function test_another_session_of_the_same_owner_cannot_use_the_provider_return(): void
    {
        [$cookie, $payload] = $this->start();
        $this->actingAs($this->user);
        $this->assertStatus(422, $this->providerReturn($cookie, $payload));
        self::assertSame(0, $this->mail->count());
    }

    public function test_expired_state_or_changed_candidate_is_refused_without_issuing_a_link(): void
    {
        [$cookie, $payload] = $this->start();
        $tampered = $payload;
        $tampered['email_change']['email'] = 'attacker@example.test';
        $encoded = rtrim(strtr(base64_encode(json_encode($tampered)), '+/', '-_'), '=');
        $response = $this->providerReturn($encoded . '.' . explode('.', $cookie, 2)[1], $tampered);
        $this->assertRedirect($response, '/login');
        $payload['email_change']['expires_at'] = time() - 1;
        $encoded = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signed = $encoded . '.' . hash_hmac('sha256', $encoded, (string) $this->config->get('app.key'));
        $this->assertRedirect($this->providerReturn($signed, $payload), '/login');
        self::assertSame(0, $this->mail->count());
    }

    public function test_cross_site_post_return_restores_the_lax_session_before_exchanging_a_code(): void
    {
        [$cookie, $payload] = $this->start();
        // Browsers omit the Lax session on Apple's cross-site form_post, while
        // the signed SameSite=None OAuth cookie is available.
        $external = new Request('POST', '/auth/fixture/callback', [], ['state' => $payload['state'], 'code' => 'verified-provider-code'], ['rb_oauth' => $cookie]);
        $response = $this->controller($external)->callback($external, ['provider' => 'fixture']);
        $this->assertStatus(200, $response);
        self::assertSame('no-referrer', $response->getHeader('Referrer-Policy'));
        self::assertStringContainsString('method="post" action="/auth/fixture/callback"', $response->body());
        self::assertStringNotContainsString('verified-provider-code', $response->body());
        self::assertSame(0, $this->mail->count());
        $cookie = $this->stateCookie($response);
        $native = new Request('POST', '/auth/fixture/callback', [], ['state' => $payload['state']], $this->cookies + ['rb_oauth' => $cookie]);
        $this->assertRedirect($this->controller($native)->callback($native, ['provider' => 'fixture']), '/settings/account/email');
        self::assertCount(1, $this->mail->to('new-oauth@example.test'));
    }

    public function test_another_account_cannot_complete_a_return_and_no_provider_is_provisioned(): void
    {
        [$cookie, $payload] = $this->start();
        $other = $this->makeUser();
        $this->actingAs($other);
        $this->assertStatus(422, $this->providerReturn($cookie, $payload));
        self::assertSame(0, $this->mail->count());
        self::assertSame(0, (new OAuthIdentityRepository($this->db))->countForUser((int) $other['id']));
    }

    public function test_replaying_a_retained_provider_intent_cannot_bypass_actual_delivery_limits(): void
    {
        [$cookie, $payload] = $this->start();
        for ($i = 0; $i < 3; $i++) {
            // A caller can retain its signed start cookie. Real providers issue
            // fresh one-use codes for repeated authorization using that intent.
            $this->assertRedirect($this->providerReturn($cookie, $payload), '/settings/account/email');
        }
        $this->assertStatus(429, $this->providerReturn($cookie, $payload));
        self::assertCount(3, $this->mail->sent);
    }
}
