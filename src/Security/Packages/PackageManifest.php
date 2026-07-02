<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Support\CoreVersion;

/** A validated rb-manifest.v2 package manifest. */
final class PackageManifest
{
    /**
     * @param list<array{kind:string,key:string,risk:string,label:string}> $permissions
     * @param ?array{fields:list<array<string,mixed>>} $settingsSchema
     * @param array<string,string> $support
     */
    public function __construct(
        public readonly string $uid,
        public readonly string $type,
        public readonly string $version,
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $license,
        public readonly string $coreMin,
        public readonly ?string $coreMax,
        public readonly array $permissions,
        public readonly ?array $settingsSchema,
        public readonly int $storageQuotaKb,
        public readonly ?int $retentionDays,
        public readonly array $support,
    ) {
    }

    public function coreCompatible(): bool
    {
        return CoreVersion::satisfies($this->coreMin, $this->coreMax);
    }
}
