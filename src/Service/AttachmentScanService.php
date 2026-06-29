<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AttachmentRepository;

final class AttachmentScanService
{
    public function __construct(private AttachmentRepository $attachments)
    {
    }

    public function markClean(int $attachmentId): void
    {
        $this->attachments->markScanClean($attachmentId);
    }

    public function quarantine(int $attachmentId, string $reason): void
    {
        $this->attachments->quarantine($attachmentId, $reason);
    }

    /** @return array{failed:int, pending:int} */
    public function cleanupStalePending(int $olderThanMinutes = 30, int $limit = 500): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $olderThanMinutes) * 60);
        $failed = $this->attachments->failPendingOlderThan($cutoff, $limit);
        return [
            'failed' => $failed,
            'pending' => count($this->attachments->pendingScans($limit)),
        ];
    }
}
