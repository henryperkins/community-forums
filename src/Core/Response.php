<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object. Controllers return one of these; the front
 * controller sends it. Tests inspect it directly.
 */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    /** @var list<string> raw Set-Cookie header values */
    private array $cookies = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    /** Post/Redirect/Get uses 303; slug moves use 301. */
    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        $parts = [rawurlencode($name) . '=' . rawurlencode($value)];
        if ($expires !== 0) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s T', $expires);
            $parts[] = 'Max-Age=' . max(0, $expires - time());
        }
        $parts[] = 'Path=' . $path;
        if ($secure) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        $parts[] = 'SameSite=' . $sameSite;
        $this->cookies[] = implode('; ', $parts);
        return $this;
    }

    public function forgetCookie(string $name, bool $secure = true): self
    {
        return $this->setCookie($name, '', time() - 3600, '/', $secure);
    }

    /** @return list<string> */
    public function cookieHeaders(): array
    {
        return $this->cookies;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($this->normalizeHeaderName($name) . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }
        echo $this->body;
    }

    private function normalizeHeaderName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
