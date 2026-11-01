<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\IdentityProviderRepository;
use App\Repository\OAuthIdentityRepository;
use App\Service\AccountService;
use App\Service\EmailChangeService;
use App\Service\MfaService;
use App\Service\OAuth\ProviderRegistry;
use App\Service\OAuthService;
use App\Service\PasskeyService;
use App\Service\RateLimitService;
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

        try {
            $url = $provider->authorizeUrl($this->callbackUri($provider->name()), $state, $challenge, $nonce);
        } catch (Throwable) {
            // Generic OIDC resolves its endpoints via discovery; a provider
            // outage on the redirect leg must fail soft, never 500.
            return $this->failFlow('That sign-in option is temporarily unavailable. Please try again shortly.');
        }

        $response = $this->redirect($url, 302);
        $this->writeStateCookie($response, [
            'p' => $provider->name(), 'state' => $state, 'verifier' => $verifier, 'nonce' => $nonce, 'link' => $link,
        ]);
        return $response;
    }

    /** Start fresh authentication for a contact change; never link or provision an account. */
    public function startEmailChange(Request $request, array $params): Response
    {
        $emailController = new EmailChangeController($this->container);
        $emailController->gate();
        $provider = $this->provider((string) ($params['provider'] ?? ''));
        $user = $this->requireUser();
        $email = $request->str('new_email');
        try {
            $this->container->get(RateLimitService::class)->enforce('email_change_auth', $request, $user);
            if (!$this->container->get(OAuthIdentityRepository::class)->existsForUserProvider($user->id(), $provider->name())) {
                throw new ValidationException(['authentication' => 'Use a sign-in provider already connected to your account.']);
            }
            $email = $this->container->get(EmailChangeService::class)->prepare($user, $email);
        } catch (ValidationException $error) {
            return $emailController->formView($user, $error->errors, $email, 422);
        } catch (\App\Core\HttpException $error) {
            if ($error->statusCode() !== 429) { throw $error; }
            return $emailController->formView($user, ['new_email' => $error->getMessage()], $email, 429);
        }

        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $verifier = $this->base64url(random_bytes(32));
        try {
            $url = $provider->authorizeUrl($this->callbackUri($provider->name()), $state,
                $this->base64url(hash('sha256', $verifier, true)), $nonce);
        } catch (Throwable) {
            return $emailController->formView($user, ['authentication' => 'That sign-in option is temporarily unavailable. Please try again shortly.'], $email, 422);
        }
        // A native link starts a fresh navigation. Redirecting a POST to the
        // provider would be blocked by form-action 'self' in some browsers.
        $response = $this->noindex($this->view('account/email_change_provider', [
            'provider_label' => $provider->label(), 'authorize_url' => $url, 'new_email' => $email,
        ]))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        $this->writeStateCookie($response, [
            'p' => $provider->name(), 'state' => $state, 'verifier' => $verifier, 'nonce' => $nonce, 'link' => false,
            'email_change' => ['email' => $email, 'user_id' => $user->id(),
                'session' => PasskeyService::sessionBinding($this->session()), 'expires_at' => time() + self::STATE_TTL],
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

        $emailController = null;
        if (array_key_exists('email_change', $payload)) {
            if (!is_array($payload['email_change'])) {
                return $this->failFlow('That confirmation expired or was invalid. Please try again.');
            }
            $emailController = new EmailChangeController($this->container);
            $emailController->gate();
            $expires = $payload['email_change']['expires_at'] ?? null;
            if (!is_int($expires) || $expires <= time() || $expires > time() + self::STATE_TTL) {
                return $this->failFlow('That confirmation expired. Please start again from email settings.');
            }
            if (!isset($payload['email_change_code'])) {
                $returnedCode = $request->str('code');
                if ($returnedCode === '' || strlen($returnedCode) > 2048) {
                    return $this->failFlow('Provider confirmation was cancelled or invalid. Your email is unchanged.');
                }
                $payload['email_change_code'] = $returnedCode;
                return $this->emailChangeHandoff($payload, $provider->label(), true);
            }
            // Native same-origin POST restores the SameSite=Lax member cookie
            // after Apple's cross-site form_post. GET never issues email here.
            if (!$request->isPost()) {
                return $this->emailChangeHandoff($payload, $provider->label(), false);
            }
            try {
                $emailController->validateOAuthState($payload['email_change']);
            } catch (ValidationException $error) {
                $response = $emailController->formView($this->requireUser(), $error->errors, '', 422);
                $this->forgetStateCookie($response);
                return $response;
            }
        }

        $code = $emailController !== null ? (string) $payload['email_change_code']
            : ($request->isPost() ? (string) $request->post('code', '') : (string) $request->query('code', ''));
        if ($code === '') {
            if ($emailController !== null) {
                return $this->emailChangeFailure($emailController, $payload['email_change'], 'Provider confirmation was cancelled. Your email is unchanged.');
            }
            return $this->failFlow('Sign-in was cancelled or failed.');
        }

        try {
            $tokens = $provider->exchange($code, (string) ($payload['verifier'] ?? ''), $this->callbackUri($provider->name()));
            $identity = $provider->identity($tokens, (string) ($payload['nonce'] ?? ''));
        } catch (Throwable) {
            if ($emailController !== null) {
                return $this->emailChangeFailure($emailController, $payload['email_change'], 'We could not confirm your account with that provider. Please try again.');
            }
            return $this->failFlow('We could not complete sign-in with that provider. Please try again.');
        }

        if ($emailController !== null) {
            $response = $emailController->completeOAuth($request, $payload['email_change'], $identity);
            $this->forgetStateCookie($response);
            return $response;
        }

        $current = !empty($payload['link']) ? $this->currentUser() : null;
        $outcome = $this->container->get(OAuthService::class)->resolve($identity, $current);

        return $this->handleOutcome($outcome, $provider->label());
    }

    /** Connections settings page: linked providers + available ones + set-password. */
    public function connections(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('oauth')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();

        return $this->connectionsView($user, [], 200);
    }

    /**
     * The connections pane, rebuilt from scratch so a 422 re-render shows the
     * same provider rows as the GET. Extracted for setPassword(), which used to
     * discard its field errors into a flash redirect.
     *
     * @param array<string,string> $errors
     */
    private function connectionsView(User $user, array $errors, int $status): Response
    {
        $registry = $this->container->get(ProviderRegistry::class);
        $linked = [];
        foreach ($this->container->get(OAuthIdentityRepository::class)->forUser($user->id()) as $row) {
            $linked[(string) $row['provider']] = $row;
        }
        $providers = [];
        foreach ($registry->all() as $name => $p) {
            $providers[] = ['name' => $name, 'label' => $p->label(), 'configured' => $p->isConfigured(), 'linked' => isset($linked[$name])];
        }
        // A linked identity whose registry provider was since disabled (or the
        // flag rolled back) is retained by design — it must stay visible and
        // disconnectable, never silently vanish from the page.
        foreach (array_keys($linked) as $name) {
            if ($registry->get((string) $name) === null) {
                $providers[] = ['name' => (string) $name, 'label' => $this->providerLabel((string) $name), 'configured' => false, 'linked' => true];
            }
        }

        return $this->view('account/connections', [
            'providers' => $providers,
            'linked' => $linked,
            'has_password' => $user->passwordHash() !== null,
            'errors' => $errors,
        ], $status);
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
        return $this->redirectWithFlash('/settings/connections', 'Disconnected ' . $this->providerLabel($provider) . '.');
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
            // Re-render at 422 rather than redirecting: setInitialPassword can
            // fail on new_password_confirm ("The passwords do not match."), and
            // a flash could name only one field with no linkage to it. No ->old
            // is replayed — these are password fields.
            return $this->connectionsView($user, $e->errors, 422);
        }
        // Setting the first password logs out every other session (SESS-1).
        $this->revokeOtherSessionsFor($user);
        return $this->redirectWithFlash('/settings/connections', 'Password set — you can now sign in with your email.');
    }

    // ---- internals --------------------------------------------------------

    /** No exchange or account mutation occurs until the member submits this form. */
    private function emailChangeHandoff(array $payload, string $label, bool $writeCookie): Response
    {
        $response = $this->noindex($this->view('account/email_change_provider_return', [
            'provider_label' => $label,
            'callback_path' => '/auth/' . $payload['p'] . '/callback',
            'state' => $payload['state'],
        ]))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
        if ($writeCookie) {
            $this->writeStateCookie($response, $payload);
        }
        return $response;
    }

    private function emailChangeFailure(EmailChangeController $controller, array $state, string $message): Response
    {
        $response = $controller->formView($this->requireUser(), ['authentication' => $message], (string) $state['email'], 422);
        $this->forgetStateCookie($response);
        return $response;
    }

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
    private function handleOutcome(array $outcome, string $label): Response
    {
        switch ($outcome['action']) {
            case 'login':
                if ($this->container->get(MfaService::class)->enabledForUser($outcome['user']->id())) {
                    $token = $this->container->get(MfaService::class)
                        ->beginLoginChallenge($outcome['user'], $this->request(), $this->authenticatedHome());
                    $response = $this->view('auth/mfa', [
                        'token' => $token,
                        'next' => $this->authenticatedHome(),
                        'errors' => [],
                    ]);
                    break;
                }
                $this->session()->login($outcome['user']);
                $response = $this->redirectWithFlash($this->authenticatedHome(), 'Signed in with ' . $label . '.');
                break;
            case 'created':
                $this->session()->login($outcome['user']);
                $response = $this->redirectWithFlash($this->authenticatedHome(), 'Welcome! Your account is ready.');
                break;
            case 'linked':
                $response = $this->redirectWithFlash('/settings/connections', 'Connected ' . $label . '.');
                break;
            case 'already_linked':
                $response = $this->redirectWithFlash('/settings/connections', $label . ' is already connected.');
                break;
            case 'already_linked_elsewhere':
                $response = $this->redirectWithFlash('/settings/connections', 'That ' . $label . ' account is linked to a different user.');
                break;
            case 'collision':
                $response = $this->redirectWithFlash('/login', 'An account with this email already exists. Log in, then connect ' . $label . ' from settings.');
                break;
            case 'registration_closed':
                $response = $this->redirectWithFlash('/login', 'New registrations are currently closed, so an account could not be created.');
                break;
            case 'registration_invite_only':
                $response = $this->redirectWithFlash('/login', 'New accounts require an invitation. Use your invitation link to sign up first, then connect ' . $label . ' from settings.');
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

    /**
     * Member-facing display name for a provider key. Live providers answer
     * via the registry seam; a disabled/removed registry provider still has
     * its operator-set display name on the row; ucfirst(key) is the last
     * resort (and the pre-P5-12 behavior for unconfigured builtins).
     */
    private function providerLabel(string $provider): string
    {
        $label = $this->container->get(ProviderRegistry::class)->get($provider)?->label();
        if ($label !== null && $label !== '') {
            return $label;
        }
        try {
            $row = $this->container->get(IdentityProviderRepository::class)->findByKey($provider);
            $stored = is_array($row) ? (string) ($row['display_name'] ?? '') : '';
            if ($stored !== '') {
                return $stored;
            }
        } catch (Throwable) {
            // pre-migration table — fall through to the generic form
        }
        return ucfirst($provider);
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
