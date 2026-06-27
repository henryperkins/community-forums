<?php

declare(strict_types=1);

namespace App\Worker;

use App\Repository\AttachmentRepository;
use App\Service\AttachmentService;

/**
 * Reclaims attachment storage (P3-04). Two classes of orphan are swept:
 *  - temp uploads abandoned before being attached (older than the configured
 *    TTL — e.g. a draft that was never posted);
 *  - finalized media whose parent post has since been soft-deleted.
 *
 * Both delete the file from disk and mark the row deleted. The sweep is bounded
 * per run and idempotent.
 */
final class OrphanAttachmentCleaner
{
    public function __construct(
        private AttachmentRepository $repo,
        private AttachmentService $service,
        private int $tempTtlHours = 24,
        private int $deletedGraceDays = 30,
    ) {
    }

    /**
     * @param string|null $now UTC "Y-m-d H:i:s"; defaults to current time.
     * @return array{temp:int,deleted_parent:int}
     */
    public function run(?string $now = null): array
    {
        $base = $now !== null ? strtotime($now . ' UTC') : time();
        $base = $base !== false ? $base : time();
        $tempCutoff = gmdate('Y-m-d H:i:s', $base - max(0, $this->tempTtlHours) * 3600);
        $deletedCutoff = gmdate('Y-m-d H:i:s', $base - max(0, $this->deletedGraceDays) * 86400);

        $temp = 0;
        foreach ($this->repo->tempOlderThan($tempCutoff) as $att) {
            $this->service->purge($att);
            $temp++;
        }

        $deletedParent = 0;
        foreach ($this->repo->finalizedWithDeletedPost($deletedCutoff) as $att) {
            $this->service->purge($att);
            $deletedParent++;
        }

        return ['temp' => $temp, 'deleted_parent' => $deletedParent];
    }
}
