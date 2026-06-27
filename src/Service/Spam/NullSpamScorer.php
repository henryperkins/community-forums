<?php

declare(strict_types=1);

namespace App\Service\Spam;

use App\Domain\User;

/**
 * The default spam scorer (Gate A): it abstains on everything, so the seam is
 * wired and ready without changing anti-abuse behaviour or introducing an
 * external dependency. Enable real scoring by rebinding {@see SpamScorer} to a
 * provider implementation (Gate B / P3-13).
 */
final class NullSpamScorer implements SpamScorer
{
    public function score(User $user, string $context, string $text): ?SpamVerdict
    {
        return null;
    }
}
