<?php

declare(strict_types=1);

namespace Tests\Unit\Service\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Service\Packages\PackageArtifactStore;
use PHPUnit\Framework\TestCase;

final class PackageArtifactStoreTest extends TestCase
{
    private string $dir;
    private PackageArtifactStore $store;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/rb-artifact-store-' . bin2hex(random_bytes(4));
        $this->store = new PackageArtifactStore($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    public function test_put_get_verify_remove_round_trip(): void
    {
        $bytes = '{"format":"rb-release.v1","uid":"acme/x"}';
        $digest = hash('sha256', $bytes);

        self::assertFalse($this->store->has($digest));
        $this->store->put($digest, $bytes);
        self::assertTrue($this->store->has($digest));
        self::assertSame($bytes, $this->store->get($digest));
        self::assertTrue($this->store->verify($digest));
        self::assertSame(rtrim($this->dir, '/') . '/' . $digest . '.json', $this->store->pathFor($digest));

        $this->store->remove($digest);
        self::assertFalse($this->store->has($digest));
        self::assertNull($this->store->get($digest));
        self::assertFalse($this->store->verify($digest));
    }

    public function test_put_refuses_bytes_that_do_not_match_the_digest(): void
    {
        try {
            $this->store->put(hash('sha256', 'expected'), 'actual');
            self::fail('expected artifact_digest refusal');
        } catch (PackagePolicyException $e) {
            self::assertSame('artifact_digest', $e->code);
        }
    }

    public function test_malformed_digest_refuses_before_touching_the_filesystem(): void
    {
        foreach (['../../etc/passwd', strtoupper(str_repeat('a', 64)), 'short', str_repeat('a', 63) . '/'] as $bad) {
            try {
                $this->store->pathFor($bad);
                self::fail('expected artifact_digest refusal for ' . $bad);
            } catch (PackagePolicyException $e) {
                self::assertSame('artifact_digest', $e->code);
            }
        }
    }

    public function test_verify_detects_on_disk_tampering(): void
    {
        $bytes = '{"format":"rb-release.v1"}';
        $digest = hash('sha256', $bytes);
        $this->store->put($digest, $bytes);

        file_put_contents($this->store->pathFor($digest), $bytes . ' ');
        self::assertFalse($this->store->verify($digest), 'a flipped/extra byte must fail verification');
    }
}
