<?php

declare(strict_types=1);

namespace App\Worker;

use App\Repository\ServerExtensionRepository;
use App\Service\Extension\ExtensionSandbox;
use Throwable;

final class ServerExtensionWorker
{
    public function __construct(
        private ServerExtensionRepository $extensions,
        private ExtensionSandbox $sandbox,
        private bool $enabled,
    ) {
    }

    /** @return array{ran:int,failed:int,skipped:int,quarantined:int} */
    public function run(int $limit = 50): array
    {
        $stats = ['ran' => 0, 'failed' => 0, 'skipped' => 0, 'quarantined' => 0];
        if (!$this->enabled) {
            $stats['skipped']++;
            return $stats;
        }
        $probe = $this->sandbox->probe();
        if (!$probe['supported']) {
            $stats['skipped']++;
            return $stats;
        }

        foreach ($this->extensions->claim($limit) as $job) {
            try {
                $result = $this->sandbox->run($job, $job);
                $this->extensions->recordRun($job, $result);
                if (($result['status'] ?? '') === 'succeeded') {
                    $stats['ran']++;
                } else {
                    $stats['failed']++;
                }
            } catch (Throwable $e) {
                $this->extensions->recordRun($job, [
                    'status' => 'failed',
                    'exit_code' => null,
                    'duration_ms' => 0,
                    'output_bytes' => 0,
                    'stdout_json' => null,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }
        return $stats;
    }
}
