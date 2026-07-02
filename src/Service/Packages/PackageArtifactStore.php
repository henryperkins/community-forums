<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Security\Packages\PackagePolicyException;

/**
 * Content-addressed cache for exact signed rb-release.v1 document bytes.
 * Digest validation makes path traversal structurally impossible.
 */
final class PackageArtifactStore
{
    public function __construct(private string $storagePath)
    {
    }

    public function pathFor(string $digest): string
    {
        $this->assertDigest($digest);

        return rtrim($this->storagePath, '/') . '/' . $digest . '.json';
    }

    public function put(string $digest, string $bytes): void
    {
        $this->assertDigest($digest);
        if (!hash_equals($digest, hash('sha256', $bytes))) {
            throw new PackagePolicyException('artifact_digest', 'Artifact bytes do not match the pinned digest.');
        }
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)) {
            throw new \RuntimeException('Cannot create the package artifact directory.');
        }

        $path = $this->pathFor($digest);
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $bytes) !== strlen($bytes) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to persist the package artifact.');
        }
    }

    public function has(string $digest): bool
    {
        return is_file($this->pathFor($digest));
    }

    public function get(string $digest): ?string
    {
        $path = $this->pathFor($digest);
        if (!is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);
        return $bytes === false ? null : $bytes;
    }

    public function verify(string $digest): bool
    {
        $bytes = $this->get($digest);

        return $bytes !== null && hash_equals($digest, hash('sha256', $bytes));
    }

    public function remove(string $digest): void
    {
        $path = $this->pathFor($digest);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function assertDigest(string $digest): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $digest) !== 1) {
            throw new PackagePolicyException('artifact_digest', 'Artifact digests must be 64 lowercase hex characters.');
        }
    }
}
