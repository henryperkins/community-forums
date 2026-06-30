<?php

declare(strict_types=1);

namespace App\Service\Extension;

final class ServerExtensionManifestValidator
{
    /** @param array<string,mixed> $manifest @return array<string,mixed> */
    public function validate(array $manifest): array
    {
        if (($manifest['schema'] ?? '') !== 'server_extension.v1') {
            throw new \InvalidArgumentException('Unsupported server extension manifest schema.');
        }

        $entrypoint = trim((string) ($manifest['entrypoint'] ?? ''));
        if ($entrypoint === '' || str_contains($entrypoint, '/') || str_contains($entrypoint, '\\') || str_contains($entrypoint, '..')) {
            throw new \InvalidArgumentException('Extension entrypoint must be a package-local file name.');
        }

        $events = $this->stringList($manifest['events'] ?? []);
        $jobs = $this->stringList($manifest['jobs'] ?? []);
        if ($events === [] && $jobs === []) {
            throw new \InvalidArgumentException('Server extensions must declare at least one event or job handler.');
        }

        $permissions = is_array($manifest['permissions'] ?? null) ? $manifest['permissions'] : [];
        $outboundHosts = $this->stringList($permissions['outbound_hosts'] ?? []);
        if ($outboundHosts !== []) {
            throw new \InvalidArgumentException('Outbound network access is disabled by default for public server extensions.');
        }

        $limits = is_array($manifest['resource_limits'] ?? null) ? $manifest['resource_limits'] : [];
        $normalizedLimits = [
            'cpu_ms' => $this->positiveInt($limits['cpu_ms'] ?? 500, 1, 5_000),
            'memory_mb' => $this->positiveInt($limits['memory_mb'] ?? 64, 16, 256),
            'time_ms' => $this->positiveInt($limits['time_ms'] ?? 1000, 50, 10_000),
            'output_kb' => $this->positiveInt($limits['output_kb'] ?? 64, 1, 1024),
            'disk_kb' => $this->positiveInt($limits['disk_kb'] ?? 512, 0, 10240),
        ];

        return [
            'entrypoint' => $entrypoint,
            'events' => $events,
            'jobs' => $jobs,
            'permissions' => [
                'broker' => $this->stringList($permissions['broker'] ?? []),
                'outbound_hosts' => [],
            ],
            'resource_limits' => $normalizedLimits,
            'storage_quota_bytes' => $this->positiveInt($manifest['storage_quota_kb'] ?? 0, 0, 102400) * 1024,
        ];
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item !== '' && preg_match('/^[a-zA-Z0-9._:-]+$/', $item) === 1) {
                $out[] = $item;
            }
        }
        return array_values(array_unique($out));
    }

    private function positiveInt(mixed $value, int $min, int $max): int
    {
        $n = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $n));
    }
}
