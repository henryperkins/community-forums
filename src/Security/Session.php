<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;

/**
 * DB-backed session + identity. The cookie holds an opaque random token; the
 * database stores only its SHA-256 (sessions.id). A guest is simply a request
 * with no valid session row — guests never get a sessions row. CSRF secrets:
 * authenticated requests use the per-session csrf_secret; guests use a separate
 * signed-by-randomness cookie so the pre-auth forms (login/register/setup) are
 * still CSRF-protected.
 */
final class Session
{
    private const GUEST_CSRF_COOKIE = 'rb_csrf';
    private const PREVIEW_COOKIE = 'rb_theme_preview_build';
    /** @var array<string,string> */
    private const COOKIE_KEYS = [
        'theme_preview_build' => self::PREVIEW_COOKIE,
    ];

    private ?Request $request = null;
    /** @var array<string,mixed>|null */
    private ?array $sessionRow = null;
    private ?User $user = null;
    private ?string $guestSecret = null;
    /** @var array<string,mixed> */
    private array $cookieValues = [];
    /** @var array<string,bool> */
    private array $forgottenCookieValues = [];

    /** @var list<callable(Response):void> */
    private array $pending = [];

    /** @param array{name:string,secure:bool,lifetime_days:int} $config */
    public function __construct(
        private SessionRepository $sessions,
        private UserRepository $users,
        private array $config,
    ) {
    }

    public function start(Request $request): void
    {
        $this->request = $request;

        $token = $request->cookie($this->config['name']);
        if ($token === null || $token === '') {
            return;
        }

        $id = hash('sha256', $token);
        $row = $this->sessions->findActive($id);
        if ($row === null) {
            return;
        }

        $user = $this->users->findEntity((int) $row['user_id']);
        if ($user === null) {
            return;
        }

        $this->sessionRow = $row;
        $this->user = $user;
        $this->sessions->touch($id);
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function userId(): ?int
    {
        return $this->user?->id();
    }

    /** The current session row id (SHA-256 of the cookie token), or null for guests. */
    public function currentSessionId(): ?string
    {
        return isset($this->sessionRow['id']) ? (string) $this->sessionRow['id'] : null;
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->cookieValues)) {
            return $this->cookieValues[$key];
        }
        if (isset($this->forgottenCookieValues[$key])) {
            return null;
        }
        $cookie = self::COOKIE_KEYS[$key] ?? null;
        if ($cookie === null) {
            return null;
        }

        $value = $this->request?->cookie($cookie);
        if ($key === 'theme_preview_build') {
            return is_string($value) && ctype_digit($value) ? (int) $value : null;
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $cookie = self::COOKIE_KEYS[$key] ?? null;
        if ($cookie === null) {
            throw new \InvalidArgumentException('Unsupported session key: ' . $key);
        }

        $encoded = (string) $value;
        $this->cookieValues[$key] = $value;
        unset($this->forgottenCookieValues[$key]);
        $this->pending[] = function (Response $r) use ($cookie, $encoded): void {
            $r->setCookie($cookie, $encoded, 0, '/', $this->config['secure'], true, 'Lax');
        };
    }

    public function forget(string $key): void
    {
        $cookie = self::COOKIE_KEYS[$key] ?? null;
        if ($cookie === null) {
            return;
        }

        unset($this->cookieValues[$key]);
        $this->forgottenCookieValues[$key] = true;
        $this->pending[] = function (Response $r) use ($cookie): void {
            $r->forgetCookie($cookie, $this->config['secure']);
        };
    }

    /** Create a session for the user and queue the cookie. Rotates the id. */
    public function login(User $user): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $id = hash('sha256', $rawToken);
        $csrfSecret = bin2hex(random_bytes(32));
        $lifetime = max(1, $this->config['lifetime_days']) * 86400;
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $lifetime);

        $this->sessions->create([
            'id' => $id,
            'user_id' => $user->id(),
            'csrf_secret' => $csrfSecret,
            'user_agent' => $this->request?->userAgent(),
            'ip' => $this->request?->ip(),
            'expires_at' => $expiresAt,
        ]);

        $this->sessionRow = ['id' => $id, 'csrf_secret' => $csrfSecret, 'user_id' => $user->id()];
        $this->user = $user;

        $this->pending[] = function (Response $r) use ($rawToken, $lifetime): void {
            $r->setCookie(
                $this->config['name'],
                $rawToken,
                time() + $lifetime,
                '/',
                $this->config['secure'],
                true,
                'Lax',
            );
            // Guest CSRF cookie no longer applies once authenticated.
            $r->forgetCookie(self::GUEST_CSRF_COOKIE, $this->config['secure']);
        };
    }

    public function logout(): void
    {
        if ($this->sessionRow !== null) {
            $this->sessions->revoke((string) $this->sessionRow['id']);
        }
        $this->sessionRow = null;
        $this->user = null;

        $this->pending[] = function (Response $r): void {
            $r->forgetCookie($this->config['name'], $this->config['secure']);
        };
    }

    /** The secret backing CSRF tokens for this request (session or guest). */
    public function csrfSecret(): string
    {
        if ($this->sessionRow !== null) {
            return (string) $this->sessionRow['csrf_secret'];
        }
        return $this->guestSecret();
    }

    private function guestSecret(): string
    {
        if ($this->guestSecret !== null) {
            return $this->guestSecret;
        }

        $cookie = $this->request?->cookie(self::GUEST_CSRF_COOKIE);
        if (is_string($cookie) && ctype_xdigit($cookie) && strlen($cookie) >= 32) {
            return $this->guestSecret = $cookie;
        }

        $secret = bin2hex(random_bytes(32));
        $this->guestSecret = $secret;
        $this->pending[] = function (Response $r) use ($secret): void {
            $r->setCookie(
                self::GUEST_CSRF_COOKIE,
                $secret,
                time() + 7200,
                '/',
                $this->config['secure'],
                true,
                'Lax',
            );
        };

        return $secret;
    }

    /** Apply queued cookie operations to the outgoing response. */
    public function commit(Response $response): void
    {
        foreach ($this->pending as $apply) {
            $apply($response);
        }
        $this->pending = [];
    }
}
