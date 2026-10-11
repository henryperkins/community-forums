<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\ValidationException;

/** The two Inbox hiding intents and their explicit restore state. */
final class InboxSnooze
{
    public static function hidden(?string $until, bool $indefinite = false): bool
    {
        return $indefinite || ($until !== null && trim($until) !== '' && (strtotime($until . ' UTC') ?: 0) > time());
    }

    /** @return array{until:?string,indefinite:bool} */
    public static function state(string $intent): array
    {
        return match ($intent) {
            // Preserve the existing relative 24-hour deadline; the label makes
            // no new promise about a particular morning or member time zone.
            'tomorrow' => ['until' => gmdate('Y-m-d H:i:s', time() + 24 * 3600), 'indefinite' => false],
            'manual' => ['until' => null, 'indefinite' => true],
            '' => ['until' => null, 'indefinite' => false],
            default => throw new ValidationException(['until' => 'Choose Til tomorrow or hide until you restore the topic.']),
        };
    }
}
