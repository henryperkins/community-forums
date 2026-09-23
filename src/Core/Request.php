<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish wrapper over an HTTP request. Built from PHP globals in
 * production and constructed directly in tests (no real HTTP needed).
 */
final class Request
{
    /** Malformed upload metadata, distinct from every PHP upload error. */
    public const UPLOAD_ERR_INVALID = -1;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $cookies
     * @param array<string,string> $server
     * @param array<string,mixed> $files normalized $_FILES entries
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $post = [],
        private array $cookies = [],
        private array $server = [],
        private array $files = [],
    ) {
        $this->method = strtoupper($method);
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        /** @var array<string,string> $server */
        $server = array_filter($_SERVER, 'is_string');

        return new self(
            (string) $method,
            self::normalizePath($path),
            $_GET,
            $_POST,
            $_COOKIE,
            $server,
            $_FILES,
        );
    }

    /**
     * A single uploaded file entry by field name (the $_FILES shape:
     * name/type/tmp_name/error/size), or null when absent or errored.
     *
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}|null
     */
    public function file(string $key): ?array
    {
        if ($this->fileError($key) !== UPLOAD_ERR_OK) {
            return null;
        }
        $f = $this->files[$key];
        return [
            'name' => (string) ($f['name'] ?? ''),
            'type' => (string) ($f['type'] ?? ''),
            'tmp_name' => (string) $f['tmp_name'],
            'error' => (int) $f['error'],
            'size' => (int) ($f['size'] ?? 0),
        ];
    }

    public function fileError(string $key): ?int
    {
        if (!array_key_exists($key, $this->files)) {
            return null;
        }
        $file = $this->files[$key];
        if (!is_array($file) || !isset($file['error'], $file['tmp_name'])
            || !is_int($file['error']) || !is_string($file['tmp_name'])
            || (isset($file['name']) && !is_string($file['name']))
            || (isset($file['type']) && !is_string($file['type']))
            || (isset($file['size']) && (!is_int($file['size']) || $file['size'] < 0))
            || ($file['error'] === UPLOAD_ERR_OK && $file['tmp_name'] === '')) {
            return self::UPLOAD_ERR_INVALID;
        }
        return $file['error'];
    }

    /** Only a reliable, bounded Content-Length can diagnose a discarded POST. */
    public function contentLength(): ?int
    {
        $value = $this->server['CONTENT_LENGTH'] ?? null;
        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }
        $digits = ltrim($value, '0');
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }
        return (int) $digits;
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        // Strip a single trailing slash (but keep root "/").
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path === '' ? '/' : $path;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function path(): string
    {
        return $this->path;
    }

    /** POST value, falling back to query, with default. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** Trimmed string input (form fields). */
    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_string($value) ? trim($value) : $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /** @return array<string,mixed> */
    public function allInput(): array
    {
        return $this->post + $this->query;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function userAgent(): ?string
    {
        $ua = $this->server['HTTP_USER_AGENT'] ?? null;
        return $ua !== null ? substr($ua, 0, 255) : null;
    }

    /** A request header by name (e.g. "Accept", "X-Requested-With"). */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    /**
     * Progressive enhancement: a request prefers JSON when it sets
     * ?format=json, Accept: application/json, or the AJAX header. Lets each
     * engagement endpoint serve a no-JS redirect or a JSON fragment.
     */
    public function wantsJson(): bool
    {
        if ((string) ($this->input('format') ?? '') === 'json') {
            return true;
        }
        $accept = (string) ($this->header('Accept') ?? '');
        $requestedWith = strtolower((string) ($this->header('X-Requested-With') ?? ''));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    /** Client IP for rate-limit keying only (never stored in Phase 1). */
    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function scheme(): string
    {
        $https = $this->server['HTTPS'] ?? '';
        if ($https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }
        if (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return 'https';
        }
        return 'http';
    }
}
