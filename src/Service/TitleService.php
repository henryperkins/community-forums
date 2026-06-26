<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Cosmetic title/rank resolution (COMMUNITY §8). A title is derived from a
 * member's reputation against tunable thresholds (New → … → Legend), or taken
 * from the admin-set `users.title` override. It is PURELY flavour — it grants no
 * powers and gates nothing (COMMUNITY §12).
 */
final class TitleService
{
    /** @var array<int,string> reputation-threshold => label, ascending */
    private array $thresholds;

    /** @param array<int|string,string> $thresholds */
    public function __construct(array $thresholds)
    {
        $normalised = [];
        foreach ($thresholds as $min => $label) {
            $normalised[(int) $min] = (string) $label;
        }
        ksort($normalised);
        // Always have a floor entry so derive() is total.
        if (!isset($normalised[0])) {
            $normalised[0] = 'New';
        }
        $this->thresholds = $normalised;
    }

    /** The cosmetic title for a user: the admin override if set, else derived. */
    public function resolve(?string $override, int $reputation): string
    {
        $override = $override !== null ? trim($override) : '';
        if ($override !== '') {
            return $override;
        }
        return $this->derive($reputation);
    }

    /** The highest threshold label whose minimum the reputation meets. */
    public function derive(int $reputation): string
    {
        $label = $this->thresholds[0];
        foreach ($this->thresholds as $min => $name) {
            if ($reputation >= $min) {
                $label = $name;
            } else {
                break;
            }
        }
        return $label;
    }
}
