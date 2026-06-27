<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\SettingRepository;
use App\Security\RateLimiter;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;

/**
 * Registration, login, logout. Login and registration are rate-limited; login
 * failures are generic (no account enumeration) and banned accounts cannot sign
 * in (suspended accounts can, but are write-gated everywhere else).
 */
final class AuthController extends Controller
{
    private const LOGIN_MAX = 5;
    private const LOGIN_WINDOW = 900;      // 15 minutes
    private const REGISTER_MAX = 5;
    private const REGISTER_WINDOW = 3600;  // 1 hour
    private const FORGOT_MAX = 5;
    private const FORGOT_WINDOW = 3600;    // 1 hour
    private const VERIFY_RESEND_MAX = 3;
    private const VERIFY_RESEND_WINDOW = 3600; // 1 hour

    /** @param array<string,string> $params */
    public function showLogin(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        return $this->view('auth/login', [
            'next' => $this->safeNext((string) $request->query('next', '')),
            'errors' => [],
            'old' => [],
        ]);
    }

    /** @param array<string,string> $params */
    public function login(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        $limiter = $this->container->get(RateLimiter::class);
        $email = $request->str('email');
        $key = 'login:' . $request->ip() . ':' . strtolower($email);

        if ($limiter->tooManyAttempts($key, self::LOGIN_MAX)) {
            return $this->view('auth/login', [
                'next' => $this->safeNext((string) $request->input('next', '')),
                'errors' => ['email' => 'Too many attempts. Please wait a few minutes and try again.'],
                'old' => ['email' => $email],
            ], 429);
        }

        $user = $this->container->get(AuthService::class)->attempt($email, (string) $request->post('password', ''));

        if ($user === null || $user->isBanned()) {
            $limiter->hit($key, self::LOGIN_WINDOW);
            $message = $user !== null && $user->isBanned()
                ? 'This account is not permitted to sign in.'
                : 'The email or password you entered is incorrect.';
            return $this->view('auth/login', [
                'next' => $this->safeNext((string) $request->input('next', '')),
                'errors' => ['email' => $message],
                'old' => ['email' => $email],
            ], 422);
        }

        $limiter->clear($key);
        $this->session()->login($user);

        return $this->redirect($this->safeNext((string) $request->input('next', '')));
    }

    /** @param array<string,string> $params */
    public function showRegister(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        return $this->view('auth/register', ['errors' => [], 'old' => [], 'registration_closed' => $this->registrationClosed()]);
    }

    /** Whether an admin has closed public sign-ups (P3-05 registration mode). */
    private function registrationClosed(): bool
    {
        return $this->container->get(SettingRepository::class)->getString('registration_mode', 'open') === 'closed';
    }

    /** @param array<string,string> $params */
    public function register(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        // Registration mode (P3-05): an admin may close public sign-ups entirely.
        if ($this->registrationClosed()) {
            return $this->view('auth/register', [
                'errors' => ['email' => 'Registration is currently closed.'],
                'old' => [],
                'registration_closed' => true,
            ], 403);
        }

        $limiter = $this->container->get(RateLimiter::class);
        $key = 'register:' . $request->ip();
        if ($limiter->tooManyAttempts($key, self::REGISTER_MAX)) {
            return $this->view('auth/register', [
                'errors' => ['email' => 'Too many sign-up attempts from your network. Please try again later.'],
                'old' => $this->oldRegister($request),
            ], 429);
        }
        $limiter->hit($key, self::REGISTER_WINDOW);

        try {
            $user = $this->container->get(AuthService::class)->register($request->allInput());
        } catch (ValidationException $e) {
            return $this->view('auth/register', ['errors' => $e->errors, 'old' => $e->old], 422);
        }

        $this->session()->login($user);
        $this->container->get(EmailVerificationService::class)->issue($user->id(), $user->email());
        return $this->redirectWithFlash('/', 'Welcome to the community, ' . $user->displayName() . '! Please check your email to verify your address.');
    }

    /** @param array<string,string> $params */
    public function logout(Request $request, array $params): Response
    {
        // Logout is allowed for any session (incl. suspended/banned) — it is not
        // a content write. CSRF is already enforced by the kernel.
        $this->session()->logout();
        return $this->redirectWithFlash('/', 'You have been signed out.');
    }

    /** @param array<string,string> $params */
    public function showForgot(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        return $this->view('auth/forgot', ['errors' => [], 'old' => [], 'sent' => false]);
    }

    /**
     * Request a reset link. The response is identical whether or not the email
     * belongs to an account (no enumeration); only the IP is rate-limited.
     *
     * @param array<string,string> $params
     */
    public function forgot(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        $limiter = $this->container->get(RateLimiter::class);
        $email = $request->str('email');
        $key = 'pwreset:' . $request->ip();

        if ($limiter->tooManyAttempts($key, self::FORGOT_MAX)) {
            return $this->view('auth/forgot', [
                'errors' => ['email' => 'Too many requests. Please wait a while and try again.'],
                'old' => ['email' => $email],
                'sent' => false,
            ], 429);
        }
        $limiter->hit($key, self::FORGOT_WINDOW);

        $this->container->get(PasswordResetService::class)->request($email);

        return $this->view('auth/forgot', ['errors' => [], 'old' => [], 'sent' => true]);
    }

    /** @param array<string,string> $params */
    public function showReset(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        $token = (string) $request->query('token', '');
        $valid = $this->container->get(PasswordResetService::class)->findValid($token) !== null;
        return $this->view('auth/reset', ['token' => $token, 'valid' => $valid, 'errors' => []]);
    }

    /** @param array<string,string> $params */
    public function reset(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        $service = $this->container->get(PasswordResetService::class);
        $token = (string) $request->post('token', '');
        $verification = $service->findValid($token);
        if ($verification === null) {
            return $this->view('auth/reset', ['token' => $token, 'valid' => false, 'errors' => []], 400);
        }

        try {
            $service->reset(
                $verification,
                (string) $request->post('password', ''),
                (string) $request->post('password_confirm', ''),
            );
        } catch (ValidationException $e) {
            return $this->view('auth/reset', ['token' => $token, 'valid' => true, 'errors' => $e->errors], 422);
        }

        return $this->redirectWithFlash('/login', 'Your password has been updated. Please sign in.');
    }

    /**
     * Confirm an email address from the link in the verification email. GET is
     * required (it is clicked from an email); confirming is idempotent and only
     * ever sets the verified flag for the link's own account, so prefetch is safe.
     *
     * @param array<string,string> $params
     */
    public function verifyEmail(Request $request, array $params): Response
    {
        $service = $this->container->get(EmailVerificationService::class);
        $verification = $service->findValid((string) $request->query('token', ''));
        if ($verification === null) {
            return $this->view('auth/verify', ['ok' => false], 400);
        }
        $service->verify($verification);
        return $this->view('auth/verify', ['ok' => true]);
    }

    /**
     * Re-send the verification email to the signed-in user (rate-limited).
     *
     * @param array<string,string> $params
     */
    public function resendVerification(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        if ($user->isEmailVerified()) {
            return $this->redirectWithFlash('/settings/account', 'Your email address is already verified.');
        }

        $limiter = $this->container->get(RateLimiter::class);
        $key = 'verify-resend:' . $user->id();
        if ($limiter->tooManyAttempts($key, self::VERIFY_RESEND_MAX)) {
            return $this->redirectWithFlash('/settings/account', 'Please wait a little before requesting another verification email.');
        }
        $limiter->hit($key, self::VERIFY_RESEND_WINDOW);

        $this->container->get(EmailVerificationService::class)->issue($user->id(), $user->email());
        return $this->redirectWithFlash('/settings/account', 'Verification email sent — check your inbox.');
    }

    /**
     * Only permit same-site relative redirect targets. Rejects protocol-relative
     * forms in every slash/backslash variant (//, /\, \/, \\) — browsers
     * normalise backslashes, so those would redirect off-site.
     */
    private function safeNext(string $next): string
    {
        if ($next === '' || $next[0] !== '/' || preg_match('~^[\\\\/]{2}~', $next) === 1) {
            return '/';
        }
        return $next;
    }

    /** @return array<string,mixed> */
    private function oldRegister(Request $request): array
    {
        return [
            'username' => $request->str('username'),
            'email' => $request->str('email'),
            'display_name' => $request->str('display_name'),
        ];
    }
}
