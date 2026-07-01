<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\LogRedactor;

/**
 * Config-gated structured telemetry with per-request correlation IDs
 * (Foundation F8). Dark by default: emit() is a no-op unless
 * `telemetry.enabled` is true, so the seam ships with no behavior change.
 * Every context passes through LogRedactor before encoding.
 */
final class Telemetry
{
    private ?string $correlationId = null;

    /** @var (callable(string):void)|null */
    private $sink;

    public function __construct(private Config $config, ?callable $sink = null)
    {
        $this->sink = $sink;
    }

    public function enabled(): bool
    {
        return (bool) $this->config->get('telemetry.enabled', false);
    }

    public function correlationId(): string
    {
        return $this->correlationId ??= bin2hex(random_bytes(8));
    }

    /** @param array<string,mixed> $context */
    public function emit(string $event, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        $line = json_encode([
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'cid' => $this->correlationId(),
            'event' => $event,
        ] + LogRedactor::redact($context), JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }

        if ($this->sink !== null) {
            ($this->sink)($line);
            return;
        }

        error_log('[RetroBoards telemetry] ' . $line);
    }
}
