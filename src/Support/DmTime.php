<?php

declare(strict_types=1);

namespace App\Support;

/** UTC fallback labels. The browser relabels these exact instants in its zone. */
final class DmTime
{
    public static function timestamp(?string $value): int
    {
        return $value === null || $value === '' ? 0 : (strtotime($value . ' UTC') ?: 0);
    }

    public static function iso(?string $value): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', self::timestamp($value));
    }

    public static function label(?string $value, string $mode = 'relative'): string
    {
        $at = self::timestamp($value);
        if ($at === 0) { return ''; }
        if ($mode === 'clock') { return gmdate('H:i', $at); }
        $days = (int) ((strtotime(gmdate('Y-m-d') . ' UTC') - strtotime(gmdate('Y-m-d', $at) . ' UTC')) / 86400);
        if ($mode === 'day') {
            return $days === 0 ? 'Today' : ($days === 1 ? 'Yesterday' : gmdate(gmdate('Y', $at) === gmdate('Y') ? 'M j' : 'M j, Y', $at));
        }
        $age = max(0, time() - $at);
        if ($age < 60) { return 'just now'; }
        if ($age < 3600) { $n = (int) floor($age / 60); return $n . ' minute' . ($n === 1 ? '' : 's') . ' ago'; }
        if ($days === 0) { $n = (int) floor($age / 3600); return $n . ' hour' . ($n === 1 ? '' : 's') . ' ago'; }
        if ($days === 1) { return 'yesterday'; }
        if ($days < 7) { return $days . ' days ago'; }
        return gmdate(gmdate('Y', $at) === gmdate('Y') ? 'M j' : 'M j, Y', $at);
    }
}
