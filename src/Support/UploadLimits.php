<?php

declare(strict_types=1);

namespace App\Support;

final class UploadLimits
{
    /** A finite PHP INI cap in bytes; unknown/unlimited quantities are null. */
    public static function phpBytes(string|false $value): ?int
    {
        if ($value === false || preg_match('/^([0-9]+)([KMG]?)$/iD', trim($value), $parts) !== 1) {
            return null;
        }
        $multiplier = match (strtoupper($parts[2])) {
            'K' => 1024,
            'M' => 1048576,
            'G' => 1073741824,
            default => 1,
        };
        $digits = ltrim($parts[1], '0');
        $max = (string) intdiv(PHP_INT_MAX, $multiplier);
        if ($digits === '' || strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            return null;
        }
        return (int) $digits * $multiplier;
    }

    public static function label(int $bytes): string
    {
        $unit = $bytes >= 1048576 ? 1048576 : 1024;
        return rtrim(rtrim(number_format($bytes / $unit, 2, '.', ''), '0'), '.')
            . ($unit === 1048576 ? ' MiB' : ' KiB');
    }
}
