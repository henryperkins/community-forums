<?php

declare(strict_types=1);

namespace App\Service\Extension;

final class BubblewrapSandboxAdapter implements ExtensionSandbox
{
    public function __construct(private string $binary = 'bwrap')
    {
    }

    public function probe(): array
    {
        $path = trim((string) shell_exec('command -v ' . escapeshellarg($this->binary) . ' 2>/dev/null'));
        if ($path === '') {
            return ['supported' => false, 'adapter' => 'bubblewrap', 'reason' => 'bubblewrap binary not found'];
        }
        $version = trim((string) shell_exec(escapeshellarg($path) . ' --version 2>/dev/null'));
        if ($version === '') {
            return ['supported' => false, 'adapter' => 'bubblewrap', 'reason' => 'bubblewrap probe failed'];
        }
        return ['supported' => true, 'adapter' => 'bubblewrap', 'reason' => null];
    }

    public function run(array $handler, array $job): array
    {
        $probe = $this->probe();
        if (!$probe['supported']) {
            throw new \RuntimeException((string) $probe['reason']);
        }

        // The public runtime is intentionally async-only and brokered. The
        // first implementation records a fail-closed placeholder until package
        // bytes and broker RPC transport are approved for the host.
        return [
            'status' => 'failed',
            'exit_code' => null,
            'duration_ms' => 0,
            'output_bytes' => 0,
            'stdout_json' => null,
            'error' => 'extension execution adapter not configured',
        ];
    }
}
