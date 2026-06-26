<?php

declare(strict_types=1);

namespace App\Security;

/**
 * File-backed fixed-window limiter. One small JSON file per key under a storage
 * directory. Suitable for a single VPS where APCu may be unavailable.
 */
final class FileRateLimiter implements RateLimiter
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $state = $this->read($key);
        if ($state === null) {
            return false;
        }
        return $state['count'] >= $maxAttempts;
    }

    public function hit(string $key, int $decaySeconds): int
    {
        $path = $this->path($key);
        $now = time();

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            return 1; // fail-open on storage error rather than locking users out
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle) ?: '';
            $state = $this->decode($raw);

            if ($state === null || $state['reset_at'] <= $now) {
                $state = ['count' => 0, 'reset_at' => $now + $decaySeconds];
            }
            $state['count']++;

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
            return $state['count'];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function clear(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function availableIn(string $key): int
    {
        $state = $this->read($key);
        if ($state === null) {
            return 0;
        }
        return max(0, $state['reset_at'] - time());
    }

    /** @return array{count:int,reset_at:int}|null */
    private function read(string $key): ?array
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }
        $state = $this->decode((string) @file_get_contents($path));
        if ($state === null || $state['reset_at'] <= time()) {
            return null;
        }
        return $state;
    }

    /** @return array{count:int,reset_at:int}|null */
    private function decode(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['count'], $data['reset_at'])) {
            return null;
        }
        return ['count' => (int) $data['count'], 'reset_at' => (int) $data['reset_at']];
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }
}
