<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserPreferenceRepository;

/**
 * Member-owned email delivery preferences stored in the user preference blob.
 * This is intentionally narrower than the admin suppression list: a member pause
 * can be toggled without clearing bounce/complaint/unsubscribe suppressions.
 */
final class EmailPreferenceService
{
    public function __construct(private UserPreferenceRepository $prefs)
    {
    }

    public function pauseAllEmail(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $value = $this->prefs->get($userId)['pause_all_email'] ?? false;
        return $value === true || $value === 1 || $value === '1';
    }

    public function setPauseAllEmail(int $userId, bool $paused): void
    {
        $this->prefs->merge($userId, ['pause_all_email' => $paused]);
    }
}
