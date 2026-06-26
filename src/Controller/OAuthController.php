<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\OAuthIdentityRepository;
use App\Service\AccountService;
use App\Service\OAuth\ProviderRegistry;
use App\Service\OAuthService;
use Throwable;

/**
 * OAuth sign-in / account linking (USER §2, P2-10). The core handles the shared
 * mechanics: a signed `state` cookie (anti-CSRF), PKCE, and nonce on the
 * redirect; strict state verification, token exchange, and account resolution on
 * the callback. Providers only map their quirks (in App\Service\OAuth). Tokens
 * are never persisted; banned accounts cannot sign in via a provider.
 */
final class OAuthController extends Controller
{
    private const STATE_COOKIE = 'rb_oauth';
    private const STATE_TTL = 600;

    /** Begin a flow: redirect to the provider with state + PKCE + nonce. */
    public function start(Request $request, array $params): Response
    {
        $provider = $this->provider((string) ($params['provider'] ?? ''));

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = $this->base64url(random_bytes(32));
        $challenge = $this->base64url(hash('sha256', $verifier, true));
        $link = $this->currentUser() !== null;

        $url = $provider->authorizeUrl($this->callbackUri($provider->name()), $state, $challenge, $nonce);

        $response = $this->redirect($url, 302);
        $this->writeStateCookie($response, [
            'p' => $provider->name(), 'state' => $state, 'verifier' => $verifier, 'nonce' => $nonce, 'link' => $link,
        ]);
        return $response;
    }

    /** Provider callback: verify state, exchange the code, resolve the account. */
    public function callback(Request $request, array $params): Response
    {
        $provider = $this->provider((string) ($params['provider'] ?? ''));
        $payload = $this->readStateCookie($request);

        // Apple uses form_post; everyone else uses query params.
        $stateParam = $request->isPost() ? (string) $request->post('state', '') : (string) $request->query('state', '');
        if ($payload === null
            || ($payload['p'] ?? '') !== $provider->name()
            || $stateParam === ''
            || !hash_equals((string) ($payload['state'] ?? ''), $stateParam)
        ) {
            return $this->failFlow('That sign-in attempt expired or was invalid. Please try again.');
        }

        $code = $request->isPost() ? (string) $request->post('code', '') : (string) $request->query('code', '');
        if ($code === '') {
            return $this->failFlow('Sign-in was cancelled or failed.');
        }

        try {
            $tokens = $provider->exchange($code, (string) ($payload['verifier'] ?? ''), $this->callbackUri($provider->name()));
            $identity = $provider->identity($tokens);
        } catch (Throwable) {
            return $this->failFlow('We could not complete sign-in with that provider. Please try again.');
        }

        $current = !empty($payload['link']) ? $this->currentUser() : null;
        $outcome = $this->container->get(OAuthService::class)->resolve($identity, $current);

        return $this->handleOutcome($outcome, $provider->name());
    }

    /** Connections settings page: linked providers + available ones + set-password. */
    public function connections(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('oauth')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();

        $registry = $this->container->get(ProviderRegistry::class);
        $linked = [];
        foreach ($this->container->get(OAuthIdentityRepository::class)->forUser($user->id()) as $row) {
            $linked[(string) $row['provider']] = $row;
        }
        $providers = [];
        foreach ($registry->all() as $name => $p) {
            $providers[] = ['name' => $name, 'configured' => $p->isConfigured(), 'linked' => isset($linked[$name])];
        }

        return $this->view('account/connections', [
            'providers' => $providers,
            'linked' => $linked,
            'has_password' => $user->passwordHash() !== null,
            'errors' => [],
        ]);
    }

    public function unlink(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('oauth')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $provider = (string) $request->post('provider', '');
        try {
            $this->container->get(OAuthService::class)->unlink($user, $provider);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/settings/connections', $e->first());
        }
        return $this->redirectWithFlash('/settings/connections', 'Disconnected ' . ucfirst($provider) . '.');
    }

    /** OAuth-only accounts add an email/password method (USER §2.4). */
    public function setPassword(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('oauth')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        try {
            $this->container->get(AccountService::class)->setInitialPassword($user, $request->allInput());
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/settings/connections', $e->first());
        }
        // Setting the first password logs out every other session (SESS-1).
        $this->revokeOtherSessionsFor($user);
        return $this->redirectWithFlash('/settings/connections', 'Password set — you can now sign in with your email.');
    }

    // ---- internals --------------------------------------------------------

    /** @return \App\Service\OAuth\OAuthProvider */
    private function provider(string $name)
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('oauth')) {
            throw new NotFoundException('Not found.');
        }
        $provider = $this->container->get(ProviderRegistry::class)->get($name);
        if ($provider === null || !$provider->isConfigured()) {
            throw new NotFoundException('That sign-in option is not available.');
        }
        return $provider;
    }

    /** @param array{action:string, user?:\App\Domain\User, email?:string} $outcome */
    private function handleOutcome(array $outcome, string $provider): Response
    {
        switch ($outcome['action']) {
            case 'login':
            case 'created':
                $this->session()->login($outcome['user']);
                $response = $this->redirectWithFlash('/', $outcome['action'] === 'created'
                    ? 'Welcome! Your account is ready.'
                    : 'Signed in with ' . ucfirst($provider) . '.');
                break;
            case 'linked':
                $response = $this->redirectWithFlash('/settings/connections', 'Connected ' . ucfirst($provider) . '.');
                break;
            case 'already_linked':
                $response = $this->redirectWithFlash('/settings/connections', ucfirst($provider) . ' is already connected.');
                break;
            case 'already_linked_elsewhere':
                $response = $this->redirectWithFlash('/settings/connections', 'That ' . ucfirst($provider) . ' account is linked to a different user.');
                break;
            case 'collision':
                $response = $this->redirectWithFlash('/login', 'An account with this email already exists. Log in, then connect ' . ucfirst($provider) . ' from settings.');
                break;
            case 'banned':
                $response = $this->redirectWithFlash('/login', 'This account is not permitted to sign in.');
                break;
            default:
                $response = $this->redirectWithFlash('/login', 'We could not complete sign-in. Please try again.');
        }
        $this->forgetStateCookie($response);
        return $response;
    }

    private function failFlow(string $message): Response
    {
        $response = $this->redirectWithFlash('/login', $message);
        $this->forgetStateCookie($response);
        return $response;
    }

    private function callbackUri(string $provider): string
    {
        $base = rtrim((string) $this->config()->get('app.url', ''), '/');
        return $base . '/auth/' . $provider . '/callback';
    }

    /** @param array<string,mixed> $payload */
    private function writeStateCookie(Response $response, array $payload): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $b64 = $this->base64url((string) $json);
        $sig = hash_hmac('sha256', $b64, $this->key());
        $secure = (bool) $this->config()->get('session.secure', true);
        // Apple uses response_mode=form_post, so its callback is a cross-site
        // top-level POST — a SameSite=Lax cookie would NOT be sent and the flow
        // would fail closed. SameSite=None (requires Secure) survives the POST and
        // does not weaken CSRF protection (the value is HMAC-signed + state-matched).
        // Fall back to Lax for non-secure local dev (None without Secure is rejected).
        $sameSite = $secure ? 'None' : 'Lax';
        $response->setCookie(
            self::STATE_COOKIE,
            $b64 . '.' . $sig,
            time() + self::STATE_TTL,
            '/',
            $secure,
            true,
            $sameSite,
        );
    }

    /** @return array<string,mixed>|null */
    private function readStateCookie(Request $request): ?array
    {
        $raw = (string) ($request->cookie(self::STATE_COOKIE) ?? '');
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }
        [$b64, $sig] = explode('.', $raw, 2);
        if (!hash_equals(hash_hmac('sha256', $b64, $this->key()), $sig)) {
            return null;
        }
        $json = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function forgetStateCookie(Response $response): void
    {
        $response->forgetCookie(self::STATE_COOKIE, (bool) $this->config()->get('session.secure', true));
    }

    private function key(): string
    {
        return (string) $this->config()->get('app.key', '');
    }

    private function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
