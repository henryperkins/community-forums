<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Read-only configuration accessor with dot-path lookup.
 */
final class Config
{
    /** @param array<string,mixed> $items */
    public function __construct(private array $items)
    {
    }

    public static function fromFile(string $path): self
    {
        /** @var array<string,mixed> $items */
        $items = require $path;
        return new self($items);
    }

    /** Fetch a value by dot path, e.g. config('db.host'). */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
