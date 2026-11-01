<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\OAuthIdentityRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\EmailChangeService;
use App\Service\OAuth\NormalizedIdentity;
use App\Service\OAuth\ProviderRegistry;
use App\Service\PasskeyService;
use App\Service\RateLimitService;

final class EmailChangeController extends Controller
{
    public function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('email_change')) {
            throw new NotFoundException('Not found.');
        }
    }

    public function form(Request $request, array $params): Response
    {
        $this->gate();
        return $this->formView($this->requireUser());
    }

    /** Safe old input is limited to the candidate address, never authentication factors. */
    public function formView(User $user, array $errors = [], string $newEmail = '', int $status = 200): Response
    {
        $this->gate();
        $flags = $this->container->get(FeatureFlags::class);
        $providers = [];
        if ($flags->enabled('oauth')) {
            $registry = $this->container->get(ProviderRegistry::class);
            foreach ($this->container->get(OAuthIdentityRepository::class)->forUser($user->id()) as $row) {
                $provider = $registry->get((string) $row['provider']);
                if ($provider !== null && $provider->isConfigured()) {
                    $providers[] = ['name' => $provider->name(), 'label' => $provider->label()];
                }
            }
        }
        $hasPasskey = $flags->enabled('passkeys')
            && $this->container->get(WebAuthnCredentialRepository::class)->activeForUser($user->id()) !== [];

        return $this->privateResponse($this->view('account/email_change', [
            'email' => $user->email(),
            'pending' => $this->container->get(EmailChangeService::class)->pending($user),
            'new_email' => $newEmail,
            'has_password' => $user->passwordHash() !== null,
            'has_passkey' => $hasPasskey,
            'providers' => $providers,
            'errors' => $errors,
        ], $status));
    }

    public function requestChange(Request $request, array $params): Response
    {
        $this->gate();
        $user = $this->requireUser();
        $email = $request->str('new_email');
        try {
            $this->limitRequest($request, $user, $email);
            $this->container->get(EmailChangeService::class)->issue(
                $user,
                $email,
                $this->rawString($request->post('current_password')),
                $this->rawString($request->post('passkey_assertion')),
                PasskeyService::sessionBinding($this->session()),
            );
        } catch (ValidationException $error) {
            return $this->formView($user, $error->errors, $email, 422);
        } catch (HttpException $error) {
            if ($error->statusCode() !== 429) { throw $error; }
            return $this->formView($user, ['new_email' => $error->getMessage()], $email, 429);
        }
        return $this->privateResponse($this->redirectWithFlash('/settings/account/email', 'Check your new inbox for a confirmation link. Your current email is unchanged.'));
    }

    public function limitRequest(Request $request, User $user, string $email): void
    {
        $limits = $this->container->get(RateLimitService::class);
        $limits->enforce('email_change', $request, $user);
        $limits->enforceGlobalSubject('email_change_destination', $email);
    }

    /** Called only after signed OAuth state and the provider code exchange are verified. */
    public function completeOAuth(Request $request, array $state, NormalizedIdentity $identity): Response
    {
        $this->gate();
        $user = $this->requireUser();
        $email = is_string($state['email'] ?? null) ? $state['email'] : '';
        try {
            $this->validateOAuthState($state);
            $this->limitRequest($request, $user, $email);
            $this->container->get(EmailChangeService::class)->issue(
                $user, $email, null, null,
                PasskeyService::sessionBinding($this->session()), $identity,
            );
        } catch (ValidationException $error) {
            return $this->formView($user, $error->errors, $email, 422);
        } catch (HttpException $error) {
            if ($error->statusCode() !== 429) { throw $error; }
            return $this->formView($user, ['new_email' => $error->getMessage()], $email, 429);
        }
        return $this->privateResponse($this->redirectWithFlash('/settings/account/email', 'Check your new inbox for a confirmation link. Your current email is unchanged.'));
    }

    /** Reject session changes before the callback exchanges any provider code. */
    public function validateOAuthState(array $state): void
    {
        $this->gate();
        $user = $this->requireUser();
        if (($state['user_id'] ?? null) !== $user->id()
            || !is_int($state['expires_at'] ?? null) || $state['expires_at'] <= time()
            || $state['expires_at'] > time() + 600
            || !is_string($state['session'] ?? null)
            || !hash_equals(PasskeyService::sessionBinding($this->session()), $state['session'])) {
            throw new ValidationException(['authentication' => 'This confirmation expired or belongs to another session. Start again.']);
        }
    }

    public function confirmation(Request $request, array $params): Response
    {
        $this->gate();
        $token = $request->str('token');
        if ($this->currentUser() === null) {
            $next = '/settings/account/email/confirm';
            if (preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1) {
                $next .= '?token=' . $token;
            }
            return $this->privateResponse($this->redirect('/login?next=' . rawurlencode($next), 302));
        }
        return $this->confirmationView($this->requireUser(), $token);
    }

    public function confirm(Request $request, array $params): Response
    {
        $this->gate();
        $user = $this->requireUser();
        $token = $request->str('token');
        try {
            $this->container->get(RateLimitService::class)->enforce('email_change_confirm', $request, $user);
            $this->container->get(EmailChangeService::class)->confirm($user, $token, $this->session()->currentSessionId());
        } catch (ValidationException $error) {
            return $this->confirmationView($user, $token, $error->errors, 422);
        } catch (HttpException $error) {
            if ($error->statusCode() !== 429) { throw $error; }
            return $this->confirmationView($user, $token, ['token' => $error->getMessage()], 429);
        }
        $this->session()->refreshUser();
        return $this->privateResponse($this->redirectWithFlash('/settings/account', 'Your email address is updated. Other sessions have been signed out.'));
    }

    private function confirmationView(User $user, string $token, array $errors = [], int $status = 200): Response
    {
        $verification = $this->container->get(EmailChangeService::class)->findConfirmation($user, $token);
        return $this->privateResponse($this->view('account/email_change_confirm', [
            'verification' => $verification,
            'token' => $verification !== null ? $token : '',
            'email' => $user->email(),
            'errors' => $errors,
        ], $status));
    }

    public function cancel(Request $request, array $params): Response
    {
        $this->gate();
        $user = $this->requireUser();
        $this->container->get(RateLimitService::class)->enforce('email_change_confirm', $request, $user);
        $this->container->get(EmailChangeService::class)->cancel($user);
        return $this->privateResponse($this->redirectWithFlash('/settings/account/email', 'The pending email change was cancelled. Your current email is unchanged.'));
    }

    private function privateResponse(Response $response): Response
    {
        return $this->noindex($response)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function rawString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
