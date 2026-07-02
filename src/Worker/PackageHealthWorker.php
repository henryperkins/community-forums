<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Telemetry;
use App\Service\Packages\PackageHealthService;

final class PackageHealthWorker
{
    public function __construct(
        private PackageHealthService $health,
        private bool $enabled,
        private ?Telemetry $telemetry = null,
    ) {
    }

    /** @return array{checked:int,quarantined:int,disabled:int,purged:int,updates:int,skipped:int} */
    public function run(): array
    {
        if (!$this->enabled) {
            return ['checked' => 0, 'quarantined' => 0, 'disabled' => 0, 'purged' => 0, 'updates' => 0, 'skipped' => 1];
        }

        return $this->health->checkAll() + ['skipped' => 0];
    }
}
