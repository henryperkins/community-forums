<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\FeatureFlags;
use App\Core\HttpException;
use App\Core\NotFoundException;
use App\Security\RateLimiter;
use App\Security\RegistrationPolicy;
use App\Security\WebAuthn\WebAuthnException;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use App\Service\InvitationService;
use App\Service\MfaService;
use App\Service\PasskeyService;
use App\Service\PasswordResetService;
use App\Service\RateLimitService;

/**
 * Registration, login, logout. Login and registration are rate-limited; login
 * failures are generic (no account enumeration) and banned accounts cannot sign
 * in (suspended accounts can, but are write-gated everywhere else).
 */
final class AuthController extends Controller
{
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

        $limiter = $this->container->get(RateLimitService::class);
        $email = $request->str('email');
        $subject = strtolower($email);
        try {
            $limiter->enforceSubject('login', $request, $subject);
        } catch (HttpException) {
            return $this->view('auth/login', [
                'next' => $this->safeNext((string) $request->input('next', '')),
                'errors' => ['email' => 'Too many attempts. Please wait a few minutes and try again.'],
                'old' => ['email' => $email],
            ], 429);
        }

        $user = $this->container->get(AuthService::class)->attempt($email, (string) $request->post('password', ''));

        if ($user === null || $user->isBanned()) {
            $message = $user !== null && $user->isBanned()
                ? 'This account is not permitted to sign in.'
                : 'The email or password you entered is incorrect.';
            return $this->view('auth/login', [
                'next' => $this->safeNext((string) $request->input('next', '')),
                'errors' => ['email' => $message],
                'old' => ['email' => $email],
            ], 422);
        }

        $limiter->clearSubject('login', $request, $subject);

        $mfa = $this->container->get(MfaService::class);
        if ($mfa->enabledForUser($user->id())) {
            $token = $mfa->beginLoginChallenge($user, $request, $this->safeNext((string) $request->input('next', '')));
            return $this->view('auth/mfa', [
                'token' => $token,
                'next' => $this->safeNext((string) $request->input('next', '')),
                'errors' => [],
            ]);
        }

        $this->session()->login($user);

        return $this->redirect($this->safeNext((string) $request->input('next', '')));
    }

    /** @param array<string,string> $params */
    public function completeMfa(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        $token = (string) $request->post('mfa_token', '');
        $limiter = $this->container->get(RateLimitService::class);
        $mfa = $this->container->get(MfaService::class);
        // Resolve the challenge's account (without consuming it) so the guess
        // budget is bounded PER ACCOUNT. Otherwise an attacker who already has the
        // password mints a fresh token per attempt and each token would hand out a
        // fresh per-token budget — an unbounded, silent 2FA brute-force.
        $account = $mfa->challengeUser($token);
        try {
            if ($account !== null) {
                $limiter->enforce('mfa_account', $request, $account);
            } else {
                // Unknown/expired token: throttle by client IP so garbage attempts can't be hammered.
                $limiter->enforceSubject('mfa_login', $request, hash('sha256', $token));
            }
            $result = $mfa->completeLoginChallenge($token, (string) $request->post('code', ''));
        } catch (HttpException) {
            return $this->view('auth/mfa', [
                'token' => $token,
                'next' => $this->safeNext((string) $request->post('next', '')),
                'errors' => ['code' => 'Too many attempts. Please sign in again in a few minutes.'],
            ], 429);
        } catch (ValidationException $e) {
            return $this->view('auth/mfa', [
                'token' => $token,
                'next' => $this->safeNext((string) $request->post('next', '')),
                'errors' => $e->errors,
            ], 422);
        }

        if ($account !== null) {
            $limiter->clear('mfa_account', $request, $account);
        }
        $this->session()->login($result['user']);
        return $this->redirect($this->safeNext($result['next']));
    }

    /** @param array<string,string> $params */
    public function passkeyChallenge(Request $request, array $params = []): Response
    {
        $this->gatePasskeys();
        if ($this->currentUser() !== null) {
            return Response::json(['ok' => false, 'errors' => ['email' => 'Already signed in.']], 422);
        }

        $email = strtolower(trim((string) ($request->post('email') ?? '')));
        $subject = $email !== '' ? $email : 'anonymous';
        try {
            $this->container->get(RateLimitService::class)->enforceSubject('passkey_challenge', $request, $subject);
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }

        try {
            $options = $this->container->get(PasskeyService::class)
                ->beginLogin($email, PasskeyService::sessionBinding($this->session()));
        } catch (WebAuthnException $e) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => $e->getMessage()], 'code' => $e->code], 422);
        }

        return Response::json(['ok' => true, 'options' => $options]);
    }

    /** @param array<string,string> $params */
    public function passkeyLogin(Request $request, array $params = []): Response
    {
        $this->gatePasskeys();
        if ($this->currentUser() !== null) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => 'Already signed in.']], 422);
        }

        $email = strtolower(trim((string) ($request->post('email') ?? '')));
        $subject = $email !== '' ? $email : 'anonymous';
        $limiter = $this->container->get(RateLimitService::class);
        try {
            $limiter->enforceSubject('passkey_login', $request, $subject);
        } catch (HttpException $e) {
            return Response::json(['ok' => false, 'errors' => ['rate_limit' => $e->getMessage()]], $e->statusCode());
        }

        try {
            $result = $this->container->get(PasskeyService::class)->completeLogin(
                (string) ($request->post('credential') ?? ''),
                PasskeyService::sessionBinding($this->session()),
            );
        } catch (ValidationException $e) {
            return Response::json(['ok' => false, 'errors' => $e->errors], 422);
        } catch (WebAuthnException) {
            return Response::json(['ok' => false, 'errors' => ['passkey' => 'That passkey could not be used to sign in.']], 422);
        }

        $limiter->clearSubject('passkey_login', $request, $subject);
        $this->session()->login($result['user']);
        return Response::json(['ok' => true, 'redirect' => $this->safeNext((string) ($request->post('next') ?? '/'))]);
    }

    /** @param array<string,string> $params */
    public function showRegister(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }
        $mode = $this->container->get(RegistrationPolicy::class)->effectiveMode();
        $raw = $request->query('invite', '');
        $rawToken = is_string($raw) ? trim($raw) : ''; // ?invite[]=x must not warn/coerce to 'Array'
        $token = $this->container->get(FeatureFlags::class)->enabled('invitations') ? $rawToken : '';

        if ($token !== '') {
            // Probing is limited BEFORE any token lookup (TM-IN-01), and the
            // limited response is uniform: no preview — the token is kept via
            // `old` so a legitimate invitee's later submit still carries the
            // grant instead of silently degrading to a plain signup.
            try {
                $this->container->get(RateLimitService::class)->enforce(InvitationService::LIMIT_REDEEM, $request);
            } catch (HttpException) {
                return $this->registerView($mode, ['token' => $token, 'valid' => true],
                    ['invite' => 'Too many invitation attempts. Please try again later.'],
                    ['invite' => $token], 429, true);
            }
        }

        $invite = $this->inviteContext($token);
        $errors = $token !== '' && !$invite['valid']
            ? ['invite' => InvitationService::INVALID_MESSAGE]
            : [];
        return $this->registerView($mode, $invite, $errors, [], 200, $rawToken !== '');
    }

    /**
     * Public invite landing (P5-13): a pure normalizing redirect into
     * /register, which owns both the rate limit and the uniform verdict.
     * No token work happens here, so an exhausted client still lands on the
     * friendly register page instead of a bare kernel 429, and a legitimate
     * journey is charged once per request that actually evaluates the token.
     */
    public function invite(Request $request, array $params): Response
    {
        $this->gateInvitations();
        $token = trim((string) ($params['token'] ?? ''));
        return $this->noindex($this->redirect('/register?invite=' . urlencode($token)));
    }

    /** @param array<string,string> $params */
    public function register(Request $request, array $params): Response
    {
        if ($this->currentUser() !== null) {
            return $this->redirect('/');
        }

        $mode = $this->container->get(RegistrationPolicy::class)->effectiveMode();
        $raw = $request->post('invite', '');
        $rawToken = is_string($raw) ? trim($raw) : ''; // array-shaped input = no token
        $inviteToken = $this->container->get(FeatureFlags::class)->enabled('invitations') ? $rawToken : '';
        $tokenInRequest = $rawToken !== '';

        // Registration mode (P3-05 / P5-13), default-deny: only `open` and
        // `invite` admit anyone — a future restrictive mode must not fail
        // open — and `closed` is absolute (a valid invitation cannot reopen
        // it). Blocked notices come from the template's own state chain; a
        // typed draft still re-renders inside the form (anti-draft-loss).
        if ($mode !== 'open' && $mode !== 'invite') {
            return $this->registerView($mode, ['token' => '', 'valid' => false], [], $this->oldRegister($request), 403, $tokenInRequest);
        }
        if ($mode === 'invite' && $inviteToken === '') {
            return $this->registerView($mode, ['token' => '', 'valid' => false], [], $this->oldRegister($request), 403, $tokenInRequest);
        }

        $limiter = $this->container->get(RateLimitService::class);
        if ($inviteToken !== '') {
            // Invite redemptions are governed SOLELY by the dedicated invite_redeem
            // policy — never also by the stricter public-signup `register` cap,
            // which would lock invited teammates out behind a shared NAT well
            // within the invite budget. The admin-issued, use-capped invitation is
            // itself the abuse control on this path.
            try {
                $limiter->enforce(InvitationService::LIMIT_REDEEM, $request);
            } catch (HttpException) {
                // Uniform limited response (TM-IN-01): no token lookup, and
                // both the draft and the token survive for the retry.
                return $this->registerView($mode, ['token' => $inviteToken, 'valid' => true],
                    ['invite' => 'Too many invitation attempts. Please try again later.'],
                    $this->oldRegister($request) + ['invite' => $inviteToken], 429, true);
            }
        } else {
            try {
                $limiter->enforce('register', $request);
            } catch (HttpException) {
                return $this->registerView($mode, ['token' => '', 'valid' => false],
                    ['email' => 'Too many sign-up attempts from your network. Please try again later.'],
                    $this->oldRegister($request), 429, $tokenInRequest);
            }
        }

        try {
            $user = $inviteToken !== ''
                ? $this->container->get(InvitationService::class)->redeem($inviteToken, $request->allInput(), $request->ip())
                : $this->container->get(AuthService::class)->register($request->allInput());
        } catch (ValidationException $e) {
            $old = $e->old;
            if (isset($e->errors['invite'])) {
                // The token itself is dead: drop it so the re-rendered form's
                // next submit can succeed (open mode) instead of re-failing
                // forever on an unremovable hidden field.
                unset($old['invite']);
                $invite = ['token' => '', 'valid' => false];
            } else {
                // Field errors: the transaction rolled the consumed use back,
                // so the token is still live — keep it without re-probing.
                $invite = ['token' => $inviteToken, 'valid' => $inviteToken !== ''];
            }
            return $this->registerView($mode, $invite, $e->errors, $old, 422, $tokenInRequest);
        }

        $this->session()->login($user);
        $this->container->get(EmailVerificationService::class)->issue($user->id(), $user->email());
        return $this->redirectWithFlash('/', 'Welcome to the community, ' . $user->displayName() . '! Please check your email to verify your address.');
    }

    private function gateInvitations(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            throw new NotFoundException('Not found.');
        }
    }

    /**
     * Resolve an invite token for display. `valid` collapses every invalid
     * reason to false (uniform — TM-IN-01); dark flag means no token at all.
     *
     * @return array{token:string, valid:bool}
     */
    private function inviteContext(string $token): array
    {
        if ($token === '' || !$this->container->get(FeatureFlags::class)->enabled('invitations')) {
            return ['token' => '', 'valid' => false];
        }
        $valid = $this->container->get(InvitationService::class)->preview($token) !== null;
        return ['token' => $token, 'valid' => $valid];
    }

    /**
     * Render auth/register for the mode × invite matrix. The form is
     * suppressed only on PRISTINE blocked renders (closed, or invite-mode
     * without a currently-valid token): when a typed draft is present the
     * form always renders — anti-draft-loss beats the blocked state.
     *
     * @param array{token:string, valid:bool} $invite
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function registerView(string $mode, array $invite, array $errors, array $old, int $status = 200, bool $tokenInRequest = false): Response
    {
        $hasDraft = trim((string) ($old['username'] ?? '')) !== ''
            || trim((string) ($old['email'] ?? '')) !== ''
            || trim((string) ($old['display_name'] ?? '')) !== '';
        $blocked = ($mode === 'closed' || ($mode === 'invite' && !$invite['valid'])) && !$hasDraft;
        $response = $this->view('auth/register', [
            'errors' => $errors,
            'old' => $old,
            'registration_mode' => $mode,
            'invite_token' => $invite['valid'] ? $invite['token'] : '',
            'invite_valid' => $invite['valid'],
            'registration_blocked' => $blocked,
        ], $status);
        // Invitation-bearing renders stay out of indexes (PHASE_5_PLAN §103),
        // keyed on the RAW request token too so limiter or flag state can
        // never drop the header from a URL that carries a secret.
        return ($tokenInRequest || $invite['token'] !== '' || ($old['invite'] ?? '') !== '')
            ? $this->noindex($response)
            : $response;
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

        $limiter = $this->container->get(RateLimitService::class);
        $email = $request->str('email');
        try {
            $limiter->enforce('password_reset', $request);
        } catch (HttpException) {
            return $this->view('auth/forgot', [
                'errors' => ['email' => 'Too many requests. Please wait a while and try again.'],
                'old' => ['email' => $email],
                'sent' => false,
            ], 429);
        }

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

    private function gatePasskeys(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('passkeys')) {
            throw new NotFoundException('Not found.');
        }
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
